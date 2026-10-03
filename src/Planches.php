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
     * Le principe (le porteur, 2026-09-29) : **chaque demi-planche a son passe-pied** — on travaille une planche depuis
     * le passe-pied, sans jamais marcher dessus. Deux dispositions le tiennent :
     *  - sans passe-pieds le long des bords (par défaut) : les planches vont d'un bord à l'autre, et celles des deux bords,
     *    qui n'ont de passe-pied que d'un côté, ne font qu'une demi-planche (`BORD_PLANCHE_M`) ;
     *  - avec (`$bords`) : un passe-pied avant la première planche et après la dernière — toutes à `PLANCHE_M`.
     * Une planche seule est **centrée** : la place de part et d'autre devient ses passe-pieds.
     *
     * `debut` : la place laissée avant la première planche (et, symétrique, après la dernière).
     *
     * @return array{n: int, largeurs: list<float>, passePieds: list<float>, debut: float}
     */
    public static function disposition(float $disponible, string $rattrapage, bool $bords = false): array
    {
        $planche = Parcelles::PLANCHE_M;
        $passe = Parcelles::PASSE_PIED_M;
        $bord = Parcelles::BORD_PLANCHE_M;
        if ($disponible <= 0) {
            return ['n' => 0, 'largeurs' => [], 'passePieds' => [], 'debut' => 0.0];
        }
        if ($disponible <= $planche) {
            return ['n' => 1, 'largeurs' => [$disponible], 'passePieds' => [], 'debut' => 0.0];
        }
        if ($bords) {
            // n planches et n + 1 passe-pieds : n × (planche + passe-pied) + passe-pied ≤ disponible. `1e-9` : qu'un compte
            // tout rond (5,60 m pour trois planches) ne tombe pas d'une planche sur une erreur d'arrondi.
            $n = max(1, (int) floor(($disponible - $passe) / ($planche + $passe) + 1e-9));
            $reste = $disponible - $n * $planche - ($n + 1) * $passe;
            $pl = $planche;
            if ($reste < 0) {
                $ppp = ($disponible - $planche) / 2; // une planche, des passe-pieds plus étroits que l'idéal
            } elseif ($rattrapage === 'planches') {
                $pl = $planche + $reste / $n;
                $ppp = $passe;
            } else {
                $ppp = $passe + $reste / ($n + 1);
            }
            return ['n' => $n, 'largeurs' => array_fill(0, $n, $pl), 'passePieds' => array_fill(0, $n - 1, $ppp), 'debut' => $ppp];
        }
        // Deux demi-planches aux bords, n − 2 planches entières, n − 1 passe-pieds (le porteur, 2026-09-29 — le compte
        // précédent traitait les planches de bord comme entières, et en posait trop peu) :
        // 2 × bord + (n − 2) × planche + (n − 1) × passe-pied ≤ disponible.
        $n = max(1, (int) floor(($disponible - 2 * $bord + 2 * $planche + $passe) / ($planche + $passe) + 1e-9));
        if ($n === 1) {
            return ['n' => 1, 'largeurs' => [$planche], 'passePieds' => [], 'debut' => ($disponible - $planche) / 2];
        }
        if ($n === 2) {
            return ['n' => 2, 'largeurs' => [$bord, $bord], 'passePieds' => [max(0.0, $disponible - 2 * $bord)], 'debut' => 0.0];
        }
        $nInt = $n - 2;
        $reste = ($disponible - 2 * $bord) - ($nInt * $planche + ($n - 1) * $passe);
        $pInt = $rattrapage === 'planches' ? $planche + $reste / $nInt : $planche;
        $ppp = $rattrapage === 'planches' ? $passe : $passe + $reste / ($n - 1);
        return ['n' => $n, 'largeurs' => array_merge([$bord], array_fill(0, $nInt, $pInt), [$bord]),
            'passePieds' => array_fill(0, $n - 1, $ppp), 'debut' => 0.0];
    }

    /** Le plan demande-t-il un passe-pied le long des deux bords (avant la première planche, après la dernière) ? */
    public static function passePiedsAuxBords(mixed $plan): bool
    {
        return is_array($plan) && ($plan['passe_pieds_bords'] ?? false) === true;
    }

    /** Le nombre de lignes d'une planche (numérotée de 1), d'après le plan enregistré ; 3 par défaut. */
    public static function nombreDeLignes(mixed $plan, int $numero): int
    {
        $n = is_array($plan) ? ($plan['lignes'][(string) $numero] ?? $plan['lignes'][$numero] ?? null) : null;
        return is_int($n) && $n >= self::LIGNES_MIN && $n <= self::LIGNES_MAX ? $n : self::LIGNES_DEFAUT;
    }

    /** La nature d'une planche, ou d'une bande de planche (tortues zèbre et abeille, Théry ch. 15 §§ 7-8). */
    public const NATURES = ['perenne' => 'Pérenne', 'annuelle' => 'Annuelle'];

    /**
     * La nature de chaque planche, selon le type de tortue (chantier « peuplement de la parcelle », Phase 5 — décisions du
     * porteur, 2026-09-23) : on choisit la nature de la **première** planche (zèbre) ou de la première bande (abeille), la
     * plus à l'ouest ou la plus au nord selon le sens des planches, **puis elles alternent**. En zèbre, chaque planche
     * peut être changée une à une ; en abeille, chaque planche a deux bandes, dans l'ordre de l'alternance — la même
     * disposition d'une planche à l'autre. Une autre tortue (classique, liane, pas dite) : `[]`, rien n'est marqué.
     *
     * @param array<string, mixed> $parcelle une ligne de `parcelle` (tortue, plan, nb_planches)
     * @return array<int, array{nature: ?string, bandes: ?array{0: string, 1: string}}> numéro de planche → nature
     */
    public static function natures(array $parcelle): array
    {
        $tortue = (string) ($parcelle['tortue'] ?? '');
        $nb = (int) ($parcelle['nb_planches'] ?? 0);
        if (!in_array($tortue, ['zebre', 'abeille'], true) || $nb < 1) {
            return [];
        }
        $plan = json_decode((string) ($parcelle['plan'] ?? ''), true);
        $premiere = self::premiereNature($plan);
        $autre = $premiere === 'perenne' ? 'annuelle' : 'perenne';
        $out = [];
        for ($n = 1; $n <= $nb; $n++) {
            if ($tortue === 'abeille') {
                $out[$n] = ['nature' => null, 'bandes' => [$premiere, $autre]];
                continue;
            }
            $choisie = is_array($plan) ? ($plan['natures']['planches'][(string) $n] ?? null) : null;
            $out[$n] = ['nature' => isset(self::NATURES[$choisie]) ? $choisie : (($n % 2 === 1) ? $premiere : $autre), 'bandes' => null];
        }
        return $out;
    }

    /** La nature de la première planche ou bande — pérenne tant qu'elle n'est pas dite, comme la tortue zèbre du ch. 15. */
    public static function premiereNature(mixed $plan): string
    {
        $p = is_array($plan) ? ($plan['natures']['premiere'] ?? null) : null;
        return isset(self::NATURES[$p]) ? $p : 'perenne';
    }

    /** La nature d'une plante d'après son cycle (`espece.cycle`) : vivace ou ligneuse → pérenne ; sinon annuelle ; `null` si le cycle n'est pas noté. */
    public static function natureDuCycle(?string $cycle): ?string
    {
        return match ($cycle) {
            'vivace', 'ligneuse' => 'perenne',
            'annuelle', 'bisannuelle' => 'annuelle',
            default => null,
        };
    }

    /**
     * L'indice (de 0) de la ligne centrale parmi `$n` lignes à intervalles égaux : celle du milieu, ou la plus proche.
     * Avec un nombre pair, deux lignes sont également proches du milieu : la plus à l'ouest (ou au nord), sauf `$miroir`,
     * qui prend l'autre.
     */
    public static function centrale(int $n, bool $miroir = false): int
    {
        return $miroir ? $n - 1 - intdiv($n - 1, 2) : intdiv($n - 1, 2);
    }

    /**
     * La demi-planche du bord est (ou sud) est-elle la planche `$numero` ? Une demi-planche de bord est une planche
     * entière coupée en son milieu : sa ligne centrale est du côté de la bordure, ses autres lignes du côté du
     * passe-pied (le porteur, 2026-10-03). Celle de l'ouest (ou du nord) l'a déjà par la règle commune ; celle de l'est
     * (ou du sud) prend la ligne en miroir.
     */
    public static function estDemiPlancheDeFin(int $numero, int $n, bool $bords): bool
    {
        return !$bords && $n >= 2 && $numero === $n;
    }

    /** La planche `$numero` d'une parcelle (numérotée de 1) est-elle sa demi-planche du bord est ou sud ? */
    public static function demiPlancheDeFin(array $parcelle, int $numero): bool
    {
        $z = self::zone($parcelle);
        return $z !== null && self::estDemiPlancheDeFin($numero, $z['disp']['n'], self::passePiedsAuxBords($z['plan']));
    }

    /** L'indice de la ligne centrale de la planche `$numero` d'une parcelle (numérotée de 1). */
    public static function centraleDe(array $parcelle, int $numero): int
    {
        $plan = json_decode((string) ($parcelle['plan'] ?? ''), true);
        return self::centrale(self::nombreDeLignes($plan, $numero), self::demiPlancheDeFin($parcelle, $numero));
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
        $z = self::zone($parcelle);
        if ($z === null) {
            return [];
        }
        ['plan' => $plan, 'nordSud' => $nordSud, 'x0' => $x0, 'y0' => $y0, 'largeurUtile' => $largeurUtile,
            'hauteurUtile' => $hauteurUtile, 'disp' => $disp] = $z;

        $out = [];
        $pos = ($nordSud ? $x0 : $y0) + $disp['debut'];
        $bords = self::passePiedsAuxBords($plan);
        foreach ($disp['largeurs'] as $k => $w) {
            $numero = $k + 1;
            $n = self::nombreDeLignes($plan, $numero);
            $c = self::centrale($n, self::estDemiPlancheDeFin($numero, $disp['n'], $bords));
            $lignes = [];
            for ($j = 0; $j < $n; $j++) {
                $d = $pos + $w * ($j + 1) / ($n + 1);
                $lignes[] = $nordSud
                    ? ['indice' => $j, 'centrale' => $j === $c, 'x1' => $d, 'y1' => $y0, 'x2' => $d, 'y2' => $y0 + $hauteurUtile]
                    : ['indice' => $j, 'centrale' => $j === $c, 'x1' => $x0, 'y1' => $d, 'x2' => $x0 + $largeurUtile, 'y2' => $d];
            }
            $out[] = $nordSud
                ? ['numero' => $numero, 'x' => $pos, 'y' => $y0, 'largeur' => $w, 'longueur' => $hauteurUtile, 'sens' => 'nord-sud', 'lignes' => $lignes]
                : ['numero' => $numero, 'x' => $x0, 'y' => $pos, 'largeur' => $w, 'longueur' => $largeurUtile, 'sens' => 'est-ouest', 'lignes' => $lignes];
            $pos += $w + ($disp['passePieds'][$k] ?? 0.0);
        }
        return $out;
    }

    /**
     * La zone des planches : ce que les bordures (externe et interne) laissent, et la disposition des planches dedans.
     * `null` sans dimensions.
     *
     * @return array{plan: mixed, nordSud: bool, x0: float, y0: float, largeurUtile: float, hauteurUtile: float,
     *               disp: array{n: int, largeurs: list<float>, passePieds: list<float>, debut: float}}|null
     */
    public static function zone(array $parcelle): ?array
    {
        $L = (float) ($parcelle['longueur_m'] ?? 0);
        $H = (float) ($parcelle['largeur_m'] ?? 0);
        if ($L <= 0 || $H <= 0) {
            return null;
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
        $largeurUtile = max(0.0, $L - $cote('ouest') - $cote('est'));
        $hauteurUtile = max(0.0, $H - $cote('nord') - $cote('sud'));
        $rattrapage = is_array($plan) && ($plan['rattrapage'] ?? '') === 'planches' ? 'planches' : 'passe-pieds';
        return ['plan' => $plan, 'nordSud' => $nordSud, 'x0' => $cote('ouest'), 'y0' => $cote('nord'),
            'largeurUtile' => $largeurUtile, 'hauteurUtile' => $hauteurUtile,
            'disp' => self::disposition($nordSud ? $largeurUtile : $hauteurUtile, $rattrapage, self::passePiedsAuxBords($plan))];
    }

    /**
     * Où court la ligne des lianes d'une tortue liane (Théry, ch. 15 § 9 — les lianes émergentes et la strate haute
     * montent sur la structure ; « orientée à l'est ou au nord, ou plantée au cœur de la tortue entre les poteaux
     * centraux »). Réglage de la parcelle, décision du porteur (2026-09-30) : `coeur` (par défaut), `nord` ou `est`.
     */
    public const POSITIONS_LIANE = ['coeur' => 'au cœur', 'nord' => 'côté nord', 'est' => 'côté est'];

    public static function positionLiane(mixed $plan): string
    {
        $p = is_array($plan) ? ($plan['liane']['position'] ?? null) : null;
        return is_string($p) && isset(self::POSITIONS_LIANE[$p]) ? $p : 'coeur';
    }

    /**
     * La géométrie de la ligne des lianes, au format de `Bordures::lignes()`. L'atelier dessine le plan au sol, pas la
     * structure (le porteur, 2026-09-30) ; la source dessine les poteaux au bord des bandes, jamais dessus — la ligne suit :
     *  - `coeur` : au milieu du passe-pied central (le plus à l'ouest, ou au nord, de deux également centraux), sur toute la
     *    longueur des planches ; sans passe-pied entre planches, celui du bord s'il existe, sinon le bord de la zone ;
     *  - `nord`, `est` : le long du bord nord, ou est, de la zone des planches — au milieu du passe-pied du bord s'il y en a un.
     * `bande` : la largeur du passe-pied où elle court, le passe-pied idéal au bord de la zone. `null` sans dimensions ni
     * planche.
     *
     * @return array{cadre: string, cote: string, longueur: float, bande: float, x1: float, y1: float, x2: float, y2: float}|null
     */
    public static function ligneDesLianes(array $parcelle): ?array
    {
        $z = self::zone($parcelle);
        if ($z === null || $z['disp']['n'] < 1 || $z['largeurUtile'] <= 0 || $z['hauteurUtile'] <= 0) {
            return null;
        }
        ['nordSud' => $ns, 'x0' => $x0, 'y0' => $y0, 'largeurUtile' => $lu, 'hauteurUtile' => $hu, 'disp' => $d] = $z;
        $position = self::positionLiane($z['plan']);
        $passe = Parcelles::PASSE_PIED_M;
        // Le passe-pied du bord, parallèle aux planches, existe quand la disposition laisse de la place avant la première.
        $debut = $d['debut'];
        if ($position === 'coeur') {
            $depart = $ns ? $x0 : $y0;
            if ($d['n'] >= 2) {
                $k = intdiv($d['n'] - 2, 2);
                $pos = $depart + $debut;
                for ($i = 0; $i <= $k; $i++) {
                    $pos += $d['largeurs'][$i] + ($i < $k ? $d['passePieds'][$i] : 0.0);
                }
                $bande = $d['passePieds'][$k];
                $pos += $bande / 2;
            } else {
                $bande = $debut > 0 ? $debut : $passe;
                $pos = $depart + ($debut > 0 ? $debut / 2 : 0.0);
            }
            $geo = $ns ? [$pos, $y0, $pos, $y0 + $hu] : [$x0, $pos, $x0 + $lu, $pos];
        } elseif ($position === 'nord') {
            // Planches est-ouest : le passe-pied du bord nord leur est parallèle.
            $y = !$ns && $debut > 0 ? $y0 + $debut / 2 : $y0;
            $bande = !$ns && $debut > 0 ? $debut : $passe;
            $geo = [$x0, $y, $x0 + $lu, $y];
        } else {
            $x = $ns && $debut > 0 ? $x0 + $lu - $debut / 2 : $x0 + $lu;
            $bande = $ns && $debut > 0 ? $debut : $passe;
            $geo = [$x, $y0, $x, $y0 + $hu];
        }
        [$x1, $y1, $x2, $y2] = $geo;
        return ['cadre' => 'liane', 'cote' => $position, 'longueur' => round(abs($x2 - $x1) + abs($y2 - $y1), 3), 'bande' => $bande,
            'x1' => $x1, 'y1' => $y1, 'x2' => $x2, 'y2' => $y2];
    }
}
