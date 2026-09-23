<?php

declare(strict_types=1);

namespace Syntropia;

use Syntropia\Web\Parcelles;

/**
 * Les planches d'une parcelle et leurs lignes de plantation — la géométrie seule. Pur.
 *
 * Voir `chantier-peuplement-parcelle.md`, Phase 4. La disposition des planches est **celle de `planchesLayout()`**
 * (`public/conception-parcelles.js`), portée telle quelle : deux planches de bord à `BORD_PLANCHE_M`, les intérieures à
 * `PLANCHE_M`, le reste réparti sur les planches ou sur les passe-pieds (`rattrapage`). Les deux doivent rester
 * identiques — le serveur compte ce que l'écran dessine.
 *
 * **Les lignes d'une planche** reprennent le modèle de la référence (`Plan.php`, décision du porteur, 2026-09-23) :
 * un nombre de lignes par planche, **3 par défaut** (« trois lignes découpent la planche en quatre »), de 1 à 12, à
 * intervalles égaux dans sa largeur ; **la ligne centrale** est celle qui tombe au milieu, ou la plus proche. La ligne
 * centrale porte la biomasse et la structure, et jamais de tuteurs (Théry, ch. 8 — `skills-map/nodes/
 * conception-de-la-tortue.md`) : c'est là que la Phase 6 s'appuiera.
 *
 * Coordonnées en mètres, comme `Bordures` : `x` d'ouest en est depuis le bord ouest, `y` du nord au sud.
 */
final class Planches
{
    public const LIGNES_DEFAUT = 3;
    public const LIGNES_MIN = 1;
    public const LIGNES_MAX = 12;

    /**
     * La largeur de chaque planche d'un rang et de chaque passe-pied — port de `planchesLayout()`.
     *
     * @return array{n: int, largeurs: list<float>, passePieds: list<float>}
     */
    public static function disposition(float $disponible, string $rattrapage): array
    {
        $planche = Parcelles::PLANCHE_M;
        $passe = Parcelles::PASSE_PIED_M;
        $bord = Parcelles::BORD_PLANCHE_M;
        $n = $disponible > $planche ? max(1, (int) floor(($disponible - $planche) / ($planche + $passe)) + 1) : ($disponible > 0 ? 1 : 0);
        if ($n <= 0) {
            return ['n' => 0, 'largeurs' => [], 'passePieds' => []];
        }
        if ($n === 1) {
            return ['n' => 1, 'largeurs' => [min($planche, $disponible)], 'passePieds' => []];
        }
        if ($n === 2) {
            return ['n' => 2, 'largeurs' => [$bord, $bord], 'passePieds' => [max(0.0, $disponible - 2 * $bord)]];
        }
        $nInt = $n - 2;
        $reste = ($disponible - 2 * $bord) - ($nInt * $planche + ($n - 1) * $passe);
        $pInt = $rattrapage === 'planches' ? $planche + $reste / $nInt : $planche;
        $ppp = $rattrapage === 'planches' ? $passe : $passe + $reste / ($n - 1);
        return ['n' => $n, 'largeurs' => array_merge([$bord], array_fill(0, $nInt, $pInt), [$bord]),
            'passePieds' => array_fill(0, $n - 1, $ppp)];
    }

    /** Le nombre de lignes d'une planche (numérotée de 1), d'après le plan enregistré ; 3 par défaut. */
    public static function nombreDeLignes(mixed $plan, int $numero): int
    {
        $n = is_array($plan) ? ($plan['lignes'][(string) $numero] ?? $plan['lignes'][$numero] ?? null) : null;
        return is_int($n) && $n >= self::LIGNES_MIN && $n <= self::LIGNES_MAX ? $n : self::LIGNES_DEFAUT;
    }

    /** L'indice (de 0) de la ligne centrale parmi `$n` lignes à intervalles égaux : celle du milieu, ou la plus proche. */
    public static function centrale(int $n): int
    {
        return intdiv($n - 1, 2);
    }

    /**
     * Les planches d'une parcelle, chacune avec ses lignes. `[]` sans dimensions.
     *
     * @param array<string, mixed> $parcelle une ligne de `parcelle`
     * @return list<array{numero: int, x: float, y: float, largeur: float, longueur: float, sens: string,
     *                    lignes: list<array{indice: int, centrale: bool, x1: float, y1: float, x2: float, y2: float}>}>
     */
    public static function dUneParcelle(array $parcelle): array
    {
        $L = (float) ($parcelle['longueur_m'] ?? 0);
        $H = (float) ($parcelle['largeur_m'] ?? 0);
        if ($L <= 0 || $H <= 0) {
            return [];
        }
        $plan = json_decode((string) ($parcelle['plan'] ?? ''), true);
        $cote = static function (string $c) use ($plan): float {
            $t = 0.0;
            foreach (['externe', 'interne'] as $cadre) {
                $b = is_array($plan) ? ($plan['bordures'][$cadre][$c] ?? null) : null;
                $t += is_array($b) && !empty($b['actif']) ? max(0.0, (float) ($b['largeur_m'] ?? 0)) : 0.0;
            }
            return $t;
        };
        $nordSud = ($parcelle['orientation_lignes'] ?? 'nord-sud') !== 'est-ouest';
        $x0 = $cote('ouest');
        $y0 = $cote('nord');
        $largeurUtile = max(0.0, $L - $cote('ouest') - $cote('est'));
        $hauteurUtile = max(0.0, $H - $cote('nord') - $cote('sud'));
        $rattrapage = is_array($plan) && ($plan['rattrapage'] ?? '') === 'planches' ? 'planches' : 'passe-pieds';
        $disp = self::disposition($nordSud ? $largeurUtile : $hauteurUtile, $rattrapage);

        $out = [];
        $pos = $nordSud ? $x0 : $y0;
        foreach ($disp['largeurs'] as $k => $w) {
            $numero = $k + 1;
            $n = self::nombreDeLignes($plan, $numero);
            $lignes = [];
            for ($j = 0; $j < $n; $j++) {
                $d = $pos + $w * ($j + 1) / ($n + 1);
                $lignes[] = $nordSud
                    ? ['indice' => $j, 'centrale' => $j === self::centrale($n), 'x1' => $d, 'y1' => $y0, 'x2' => $d, 'y2' => $y0 + $hauteurUtile]
                    : ['indice' => $j, 'centrale' => $j === self::centrale($n), 'x1' => $x0, 'y1' => $d, 'x2' => $x0 + $largeurUtile, 'y2' => $d];
            }
            $out[] = $nordSud
                ? ['numero' => $numero, 'x' => $pos, 'y' => $y0, 'largeur' => $w, 'longueur' => $hauteurUtile, 'sens' => 'nord-sud', 'lignes' => $lignes]
                : ['numero' => $numero, 'x' => $x0, 'y' => $pos, 'largeur' => $w, 'longueur' => $largeurUtile, 'sens' => 'est-ouest', 'lignes' => $lignes];
            $pos += $w + ($disp['passePieds'][$k] ?? 0.0);
        }
        return $out;
    }
}
