<?php

declare(strict_types=1);

namespace Syntropia;

/**
 * Les lignes de plants des bordures d'une parcelle — leur géométrie et le compte des plants. Pur.
 *
 * Voir `chantier-peuplement-parcelle.md`, Phase 2, et `skills-map/nodes/conception-de-la-tortue.md`. Décisions du
 * porteur (2026-09-23) : chaque côté actif d'une bordure porte **une seule ligne**, au milieu de sa bande ; la ligne
 * est une **série d'une ou plusieurs espèces qui alternent** ; **l'écart entre deux plants voisins est la moyenne de
 * leurs deux espacements** (chalef 0,50 m puis consoude 0,30 m : 0,40 m) ; **aux deux bouts, la moitié de l'espacement
 * du plant d'extrémité**. Les espacements viennent du référentiel (`espece.espacement_retenu_m`) : une espèce qui n'en
 * a pas empêche le compte de toute sa ligne — l'atelier le dit, il ne devine pas.
 *
 * La géométrie reprend celle du plan (`conception-parcelles.js`, `Materiel::bandes()`) : l'externe nord et sud court
 * sur toute la longueur, est et ouest entre les deux ; l'interne est dans l'externe, ses côtés est et ouest entre ses
 * côtés nord et sud. Coordonnées en mètres : `x` d'ouest en est depuis le bord ouest, `y` du nord au sud depuis le bord
 * nord — celles du dessin.
 */
final class Bordures
{
    /**
     * Les deux bordures, et les fonctions que chacune remplit (celles de `Scenario::FONCTIONS`). **En bordure, couvrir le
     * sol et bloquer l'herbe sont la même fonction** (le porteur, 2026-09-23) : une plante couvre-sol convient donc à la
     * bordure externe.
     */
    public const CADRES = [
        'externe' => ['libelle' => 'Bordure externe', 'fonctions' => ['bloque_herbe', 'couvre_sol', 'vent'],
            'role' => 'bloquer l’herbe — couvrir le sol revient au même — et freiner le vent'],
        'interne' => ['libelle' => 'Bordure interne', 'fonctions' => ['biomasse'],
            'role' => 'apporter de la biomasse'],
    ];

    public const COTES = ['nord' => 'nord', 'sud' => 'sud', 'ouest' => 'ouest', 'est' => 'est'];

    /**
     * Une série ne dépasse pas trente places — un garde-fou. La référence (`ImplantationBordures`) s'arrêtait à douze ;
     * proposer **toutes** les espèces qui conviennent (le porteur, 2026-09-23) peut aller au-delà.
     */
    public const MAX_SERIE = 30;

    /** Ce qu'une bordure ne retient pas : ses plantes sont pérennes (le porteur, 2026-09-23). */
    public const CYCLES_EXCLUS = ['annuelle', 'bisannuelle'];

    /** Au-delà, le dessin n'échantillonne plus ; les comptes restent exacts. */
    public const MAX_POINTS = 400;

    public static function cle(string $cadre, string $cote): string
    {
        return "$cadre:$cote";
    }

    /** @return array{0: string, 1: string}|null le cadre et le côté, ou `null` si la clé n'est pas celle d'une bordure */
    public static function depuisCle(string $cle): ?array
    {
        $morceaux = explode(':', $cle);
        return count($morceaux) === 2 && isset(self::CADRES[$morceaux[0]]) && isset(self::COTES[$morceaux[1]])
            ? [$morceaux[0], $morceaux[1]] : null;
    }

    public static function libelle(string $cle): string
    {
        $c = self::depuisCle($cle);
        return $c === null ? $cle : self::CADRES[$c[0]]['libelle'] . ', côté ' . $c[1];
    }

    /**
     * Les lignes possibles d'une parcelle : une par côté actif, au milieu de sa bande. `[]` tant que la parcelle n'a
     * pas de dimensions.
     *
     * @param array<string, mixed> $parcelle une ligne de `parcelle` (longueur_m, largeur_m, plan)
     * @return array<string, array{cadre: string, cote: string, longueur: float, bande: float,
     *                             x1: float, y1: float, x2: float, y2: float}>
     */
    public static function lignes(array $parcelle): array
    {
        $L = (float) ($parcelle['longueur_m'] ?? 0);
        $H = (float) ($parcelle['largeur_m'] ?? 0);
        if ($L <= 0 || $H <= 0) {
            return [];
        }
        $plan = json_decode((string) ($parcelle['plan'] ?? ''), true);
        $largeur = static function (string $cadre, string $cote) use ($plan): float {
            $b = is_array($plan) ? ($plan['bordures'][$cadre][$cote] ?? null) : null;
            return is_array($b) && !empty($b['actif']) ? max(0.0, (float) ($b['largeur_m'] ?? 0)) : 0.0;
        };
        $e = static fn (string $c): float => $largeur('externe', $c);
        $i = static fn (string $c): float => $largeur('interne', $c);

        $out = [];
        $poser = static function (string $cadre, string $cote, float $bande, float $x1, float $y1, float $x2, float $y2) use (&$out): void {
            $longueur = abs($x2 - $x1) + abs($y2 - $y1);
            if ($bande > 0 && $longueur > 0) {
                $out[self::cle($cadre, $cote)] = ['cadre' => $cadre, 'cote' => $cote, 'longueur' => round($longueur, 3),
                    'bande' => $bande, 'x1' => $x1, 'y1' => $y1, 'x2' => $x2, 'y2' => $y2];
            }
        };
        // Externe.
        $poser('externe', 'nord', $e('nord'), 0.0, $e('nord') / 2, $L, $e('nord') / 2);
        $poser('externe', 'sud', $e('sud'), 0.0, $H - $e('sud') / 2, $L, $H - $e('sud') / 2);
        $poser('externe', 'ouest', $e('ouest'), $e('ouest') / 2, $e('nord'), $e('ouest') / 2, $H - $e('sud'));
        $poser('externe', 'est', $e('est'), $L - $e('est') / 2, $e('nord'), $L - $e('est') / 2, $H - $e('sud'));
        // Interne, dans l'externe.
        $x0 = $e('ouest');
        $x9 = $L - $e('est');
        $poser('interne', 'nord', $i('nord'), $x0, $e('nord') + $i('nord') / 2, $x9, $e('nord') + $i('nord') / 2);
        $poser('interne', 'sud', $i('sud'), $x0, $H - $e('sud') - $i('sud') / 2, $x9, $H - $e('sud') - $i('sud') / 2);
        $y0 = $e('nord') + $i('nord');
        $y9 = $H - $e('sud') - $i('sud');
        $poser('interne', 'ouest', $i('ouest'), $x0 + $i('ouest') / 2, $y0, $x0 + $i('ouest') / 2, $y9);
        $poser('interne', 'est', $i('est'), $x9 - $i('est') / 2, $y0, $x9 - $i('est') / 2, $y9);
        return $out;
    }

    /**
     * Les plants d'une ligne de `$longueur` mètres, pour une série dont `$espacements` donne l'espacement de chaque
     * place, dans l'ordre (`null` : l'espèce n'en a pas). Le premier plant à la moitié de son espacement du bout ;
     * entre deux voisins, la moyenne de leurs espacements ; le dernier à la moitié du sien de l'autre bout.
     *
     * @param list<float|null> $espacements
     * @return array{ok: bool, manquants: list<int>, comptes: list<int>, total: int, positions: list<array{0: int, 1: float}>}
     *         `manquants` : les places sans espacement (rien n'est compté alors) ; `positions` : [place, distance au
     *         début], échantillonnées au-delà de MAX_POINTS
     */
    public static function compter(float $longueur, array $espacements): array
    {
        $m = count($espacements);
        $vide = ['ok' => false, 'manquants' => [], 'comptes' => array_fill(0, $m, 0), 'total' => 0, 'positions' => []];
        if ($m === 0) {
            return $vide;
        }
        $manquants = [];
        foreach ($espacements as $k => $esp) {
            if ($esp === null || $esp <= 0) {
                $manquants[] = $k;
            }
        }
        if ($manquants !== []) {
            return ['manquants' => $manquants] + $vide;
        }
        $eps = 1e-9;
        $comptes = array_fill(0, $m, 0);
        $positions = [];
        $total = 0;
        $pos = $espacements[0] / 2;
        $k = 0;
        while ($pos + $espacements[$k] / 2 <= $longueur + $eps && $total < 1_000_000) {
            $comptes[$k]++;
            $total++;
            $positions[] = [$k, round($pos, 3)];
            $suivant = ($k + 1) % $m;
            $pos += ($espacements[$k] + $espacements[$suivant]) / 2;
            $k = $suivant;
        }
        if (count($positions) > self::MAX_POINTS) {
            $pas = count($positions) / self::MAX_POINTS;
            $echantillon = [];
            for ($j = 0; $j < self::MAX_POINTS; $j++) {
                $echantillon[] = $positions[(int) floor($j * $pas)];
            }
            $positions = $echantillon;
        }
        return ['ok' => true, 'manquants' => [], 'comptes' => $comptes, 'total' => $total, 'positions' => $positions];
    }

    /**
     * Ce que l'atelier propose pour une bordure : **toutes** les espèces du référentiel qui remplissent sa fonction
     * (décision du porteur, 2026-09-23), dans l'ordre reçu, **sauf les annuelles et bisannuelles** — une bordure porte
     * des plantes pérennes ; un cycle non noté ne l'écarte pas. Chacune vient avec les fonctions qu'elle y remplit et,
     * s'il y a lieu, une réserve tirée du corpus : sur une face sud ou ouest de la bordure externe, le ch. 14 (§ 4.2,
     * règle 1) ne veut que des émergentes — une espèce dont la strate notée ne l'est pas est signalée, non écartée.
     *
     * @param list<array<string, mixed>> $especes le référentiel du site (`Especes::toutes()`)
     * @return list<array{espece: array<string, mixed>, fonctions: list<string>, reserve: ?string}>
     */
    public static function proposer(string $cadre, string $cote, array $especes): array
    {
        $out = [];
        foreach ($especes as $e) {
            if (!self::convient($cadre, $e) || in_array((string) ($e['cycle'] ?? ''), self::CYCLES_EXCLUS, true)) {
                continue;
            }
            $roles = array_filter(explode('|', (string) ($e['role'] ?? '')));
            $fonctions = [];
            foreach (self::CADRES[$cadre]['fonctions'] as $f) {
                if (in_array($f, $roles, true) || ($f === 'bloque_herbe' && (int) ($e['bloque_herbe'] ?? 0) === 1)) {
                    $fonctions[] = $f;
                }
            }
            $reserve = null;
            $strates = array_values(array_filter(explode('|', (string) ($e['strates_possibles'] ?? ''))));
            if ($cadre === 'externe' && in_array($cote, ['sud', 'ouest'], true) && $strates !== [] && !in_array('emergente', $strates, true)) {
                $reserve = 'Face ' . $cote . ', exposée : le ch. 14 n’y veut que des plantes émergentes, et sa strate notée est « '
                    . implode(', ', $strates) . ' ».';
            }
            $out[] = ['espece' => $e, 'fonctions' => $fonctions, 'reserve' => $reserve];
        }
        return $out;
    }

    /**
     * Une espèce remplit-elle la fonction de ce cadre ? D'après son rôle (clés de `Scenario::FONCTIONS`) — et, pour le
     * bloque-herbe, la case `bloque_herbe` du référentiel, qui le dit aussi.
     *
     * @param array<string, mixed> $espece une ligne de `espece`
     */
    public static function convient(string $cadre, array $espece): bool
    {
        $roles = array_filter(explode('|', (string) ($espece['role'] ?? '')));
        foreach (self::CADRES[$cadre]['fonctions'] ?? [] as $f) {
            if (in_array($f, $roles, true) || ($f === 'bloque_herbe' && (int) ($espece['bloque_herbe'] ?? 0) === 1)) {
                return true;
            }
        }
        return false;
    }
}
