<?php

declare(strict_types=1);

namespace Syntropia;

/**
 * Répartir une liste de plantes sur les bordures d'une parcelle — la règle. Pur.
 *
 * Voir `chantier-repartition-bordures.md`. La personne choisit **une liste pour toute la parcelle** ; l'atelier compose
 * la série de chaque côté actif des bordures externe et interne (la ligne des lianes garde son chemin propre). Décisions
 * du porteur (2026-10-01) :
 *  - une espèce va sur **chaque** côté dont elle remplit la fonction (`Bordures::convient()`), les deux bordures
 *    comprises ; ni annuelle ni bisannuelle ; **sans espacement au référentiel, elle est écartée** — le placement ne se
 *    fait pas au hasard ;
 *  - sur les faces **sud et ouest de l'externe, des émergentes seulement** (Théry, ch. 14 § 4.2) — d'après la strate de
 *    service (`LignesDeService::stratesService()`) ; une strate non notée n'y entre pas, l'atelier ne la devine pas ;
 *  - **brise-vent** : une coche par côté de l'externe (`parcelle.plan.bordures.externe.<côté>.brise_vent`). Un côté
 *    coché reçoit **brise-vent et bloque-herbe en alternance, au moins une brise-vent** ; une espèce qui n'est que
 *    brise-vent ne va que sur un côté coché ;
 *  - une **colonne vertébrale** par bordure, désignée par la personne, revient **un plant sur deux** (C, A, C, B…) ;
 *  - **deux espèces au moins par côté**, la colonne comptant pour une ; sinon, **refus** : rien n'est réparti, et
 *    chaque côté dit ce qui lui manque — « la diversité fait partie de la réussite du système » (le porteur).
 *
 * Deux plants voisins ne sont jamais de la même espèce : la série ne répète aucune espèce, sauf la colonne, qui alterne
 * avec les autres. Le compte des plants est celui de `Bordures::compter()`, sans changement. **Un côté ne garde que les
 * espèces qu'il peut porter** (le porteur, 2026-10-01) : celle que la longueur n'atteint pas sort de sa série et se dit ;
 * s'il en reste moins de deux, le côté est refusé comme les autres.
 */
final class RepartitionBordures
{
    /** Les deux bordures que la répartition remplit. */
    public const CADRES = ['externe', 'interne'];

    /** Les faces de l'externe où ne vont que des émergentes (ch. 14 § 4.2). */
    public const FACES_EXPOSEES = ['sud', 'ouest'];

    /** Deux espèces au moins par côté (le porteur, 2026-10-01). */
    public const MIN_ESPECES = 2;

    /** @return list<string> les côtés de l'externe cochés « brise-vent » dans le plan */
    public static function cotesBriseVent(array $parcelle): array
    {
        $plan = json_decode((string) ($parcelle['plan'] ?? ''), true);
        $out = [];
        foreach (array_keys(Bordures::COTES) as $cote) {
            $b = is_array($plan) ? ($plan['bordures']['externe'][$cote] ?? null) : null;
            if (is_array($b) && !empty($b['brise_vent'])) {
                $out[] = $cote;
            }
        }
        return $out;
    }

    /**
     * Pourquoi une espèce de la liste ne peut servir à aucune bordure, ou `null` si elle le peut.
     *
     * @param array<string, mixed> $e une ligne de `espece`
     */
    public static function ecartee(array $e): ?string
    {
        if (in_array((string) ($e['cycle'] ?? ''), Bordures::CYCLES_EXCLUS, true)) {
            return 'annuelle ou bisannuelle : une bordure porte des plantes pérennes';
        }
        if (!self::convientAUneBordure($e)) {
            return 'ne remplit la fonction d’aucune bordure (bloque-herbe, couvre-sol, brise-vent, biomasse)';
        }
        if (Espacement::deService($e) === null) {
            return 'pas d’espacement de service au référentiel, à compléter dans Espèces';
        }
        return null;
    }

    private static function convientAUneBordure(array $e): bool
    {
        foreach (self::CADRES as $cadre) {
            if (Bordures::convient($cadre, $e)) {
                return true;
            }
        }
        return false;
    }

    /** L'espèce bloque-t-elle l'herbe (couvrir le sol revient au même en bordure, le porteur, 2026-09-23) ? */
    public static function bloqueHerbe(array $e): bool
    {
        $roles = array_filter(explode('|', (string) ($e['role'] ?? '')));
        return in_array('bloque_herbe', $roles, true) || in_array('couvre_sol', $roles, true) || (int) ($e['bloque_herbe'] ?? 0) === 1;
    }

    public static function briseVent(array $e): bool
    {
        return in_array('vent', array_filter(explode('|', (string) ($e['role'] ?? ''))), true);
    }

    /**
     * L'espèce peut-elle aller sur ce côté ? `null` si oui, sinon la raison.
     *
     * @param array<string, mixed> $e
     */
    public static function refusSurCote(string $cadre, string $cote, bool $briseVent, array $e): ?string
    {
        if (!Bordures::convient($cadre, $e)) {
            return 'ne remplit pas la fonction de la ' . mb_strtolower(Bordures::CADRES[$cadre]['libelle']);
        }
        if ($cadre === 'externe') {
            if (!$briseVent && !self::bloqueHerbe($e)) {
                return 'brise-vent seulement, et ce côté n’est pas coché « brise-vent »';
            }
            if (in_array($cote, self::FACES_EXPOSEES, true)) {
                $strates = LignesDeService::stratesService($e);
                if ($strates === []) {
                    return 'face ' . $cote . ' : des émergentes seulement, et sa strate n’est pas notée';
                }
                if (!in_array('emergente', $strates, true)) {
                    return 'face ' . $cote . ' : des émergentes seulement (ch. 14), et sa strate est « ' . implode(', ', $strates) . ' »';
                }
            }
        }
        return null;
    }

    /**
     * La série d'un côté, sans la colonne : brise-vent et bloque-herbe alternent sur un côté coché, sinon l'ordre reçu.
     *
     * @param list<array<string, mixed>> $especes
     * @return list<array<string, mixed>>
     */
    private static function ordonner(array $especes, bool $briseVent): array
    {
        if (!$briseVent) {
            return $especes;
        }
        // Les brise-vent d'un côté, les autres (bloque-herbe) de l'autre, puis l'un, l'autre, jusqu'à épuisement.
        $vent = array_values(array_filter($especes, [self::class, 'briseVent']));
        $herbe = array_values(array_filter($especes, static fn (array $e): bool => !self::briseVent($e)));
        $out = [];
        for ($k = 0; $k < max(count($vent), count($herbe)); $k++) {
            if (isset($vent[$k])) {
                $out[] = $vent[$k];
            }
            if (isset($herbe[$k])) {
                $out[] = $herbe[$k];
            }
        }
        return $out;
    }

    /**
     * Répartir la liste sur les côtés actifs des bordures externe et interne.
     *
     * @param array<string, mixed> $parcelle une ligne de `parcelle` (longueur_m, largeur_m, plan)
     * @param list<array<string, mixed>> $especes la liste choisie, dans l'ordre de la personne (lignes de `espece`)
     * @param array<string, int|null> $colonnes la colonne vertébrale de chaque bordure : ['externe' => id, 'interne' => id]
     * @return array{
     *   ok: bool,
     *   cotes: array<string, array{cadre: string, cote: string, longueur: float, brise_vent: bool,
     *                              serie: list<array<string, mixed>>, colonne: ?int, compte: array<string, mixed>,
     *                              plants: array<int, int>, sans_plant: list<array<string, mixed>>, refus: ?string}>,
     *   ecartees: list<array{espece: array<string, mixed>, raison: string}>,
     *   sans_place: list<array{espece: array<string, mixed>, raisons: list<string>}>,
     *   notes: list<string>
     * }
     *   `ok` faux : au moins un côté est refusé (`refus`), et aucune série ne doit être posée. `plants` : le nombre de
     *   plants de chaque espèce (par id) sur le côté ; `sans_plant` : les espèces qui lui convenaient mais que sa longueur
     *   n'atteint pas — retirées de sa série (le porteur, 2026-10-01).
     */
    public static function repartir(array $parcelle, array $especes, array $colonnes = []): array
    {
        $out = ['ok' => false, 'cotes' => [], 'ecartees' => [], 'sans_place' => [], 'notes' => []];
        $lignes = array_filter(Bordures::lignes($parcelle), static fn (array $l): bool => in_array($l['cadre'], self::CADRES, true));
        if ($lignes === []) {
            $out['notes'][] = 'Aucune bordure externe ou interne active sur le plan enregistré.';
            return $out;
        }
        $briseVent = self::cotesBriseVent($parcelle);

        // Une espèce une seule fois, dans l'ordre reçu ; celles qui ne peuvent servir nulle part sont écartées.
        $retenues = [];
        foreach ($especes as $e) {
            $id = (int) ($e['id'] ?? 0);
            if (isset($retenues[$id])) {
                continue;
            }
            $raison = self::ecartee($e);
            if ($raison !== null) {
                $out['ecartees'][] = ['espece' => $e, 'raison' => $raison];
                continue;
            }
            $retenues[$id] = $e;
        }

        $placees = [];
        $raisonsDe = [];
        $ok = true;
        foreach ($lignes as $cle => $l) {
            $cadre = $l['cadre'];
            $cote = $l['cote'];
            $bv = $cadre === 'externe' && in_array($cote, $briseVent, true);
            $vont = [];
            foreach ($retenues as $id => $e) {
                $r = self::refusSurCote($cadre, $cote, $bv, $e);
                if ($r === null) {
                    $vont[$id] = $e;
                } else {
                    $raisonsDe[$id][] = Bordures::libelle($cle) . ' : ' . $r;
                }
            }
            // La colonne vertébrale de la bordure, si elle va sur ce côté.
            $colId = isset($colonnes[$cadre]) ? (int) $colonnes[$cadre] : 0;
            $colonne = $colId > 0 && isset($vont[$colId]) ? $vont[$colId] : null;
            $autres = array_values(array_filter($vont, static fn (array $e): bool => (int) $e['id'] !== $colId));
            $autres = self::ordonner($autres, $bv);

            $refus = null;
            if ($bv && array_filter($vont, [self::class, 'briseVent']) === []) {
                $refus = 'coché « brise-vent », et aucune espèce brise-vent de la liste ne lui convient';
            } elseif (count($vont) < self::MIN_ESPECES) {
                $refus = count($vont) === 0
                    ? 'aucune espèce de la liste ne lui convient, alors qu’il en faut au moins deux'
                    : 'une seule espèce de la liste lui convient (' . self::nom(array_values($vont)[0]) . '), alors qu’il en faut au moins deux';
            }
            $serie = [];
            $sansPlant = [];
            $compte = Bordures::compter($l['longueur'], []);
            if ($refus === null) {
                // Une ligne compte au plus MAX_SERIE places (la colonne en prend une sur deux) : au-delà, les dernières
                // espèces de la liste n'y entrent pas — et le disent.
                $max = $colonne !== null ? intdiv(Bordures::MAX_SERIE, 2) : Bordures::MAX_SERIE;
                foreach (array_slice($autres, $max) as $e) {
                    $raisonsDe[(int) $e['id']][] = Bordures::libelle($cle) . ' : la série d’un côté compte au plus '
                        . Bordures::MAX_SERIE . ' places';
                    $sansPlant[] = $e;
                }
                $autres = array_slice($autres, 0, $max);
                $horsSerie = count($sansPlant);
                // Un côté ne garde que les espèces qu'il peut porter (le porteur, 2026-10-01) : une espèce que la longueur
                // n'atteint pas sort de la série, et l'on recompte — la fin de la série change, un plant de plus peut tenir.
                while (true) {
                    $serie = self::composer($colonne, $autres);
                    $compte = Bordures::compter($l['longueur'], array_map(static fn (array $e): float => (float) Espacement::deService($e), $serie));
                    $vides = [];
                    foreach ($autres as $j => $a) {
                        $n = 0;
                        foreach ($serie as $k => $e) {
                            $n += (int) $e['id'] === (int) $a['id'] ? ($compte['comptes'][$k] ?? 0) : 0;
                        }
                        if ($n === 0) {
                            $vides[] = $j;
                        }
                    }
                    if ($vides === []) {
                        break;
                    }
                    foreach ($vides as $j) {
                        $sansPlant[] = $autres[$j];
                    }
                    $autres = array_values(array_diff_key($autres, array_flip($vides)));
                }
                $restantes = count($autres) + ($colonne !== null ? 1 : 0);
                if ($restantes < self::MIN_ESPECES) {
                    $refus = 'trop court : il ne porte ' . ($restantes === 1 ? 'qu’une espèce' : 'aucune espèce')
                        . ' de la liste — il en faut deux au moins';
                    $serie = [];
                    $compte = Bordures::compter($l['longueur'], []);
                } elseif ($bv && array_filter($colonne !== null ? [$colonne, ...$autres] : $autres, [self::class, 'briseVent']) === []) {
                    $refus = 'coché « brise-vent », et trop court pour porter une espèce brise-vent de la liste';
                    $serie = [];
                    $compte = Bordures::compter($l['longueur'], []);
                }
                foreach (array_slice($sansPlant, $horsSerie) as $e) {
                    $raisonsDe[(int) $e['id']][] = Bordures::libelle($cle) . ' : pas de place — le côté est plein avant elle';
                }
            }
            if ($refus !== null) {
                $ok = false;
            }
            $plants = [];
            foreach ($serie as $k => $e) {
                $plants[(int) $e['id']] = ($plants[(int) $e['id']] ?? 0) + ($compte['comptes'][$k] ?? 0);
                $placees[(int) $e['id']] = true;
            }
            if ($colId > 0 && $colonne === null && isset($retenues[$colId]) && $refus === null) {
                $out['notes'][] = Bordures::libelle($cle) . ' : la colonne vertébrale (' . self::nom($retenues[$colId])
                    . ') ne lui convient pas — le côté s’en passe.';
            }
            $out['cotes'][$cle] = ['cadre' => $cadre, 'cote' => $cote, 'longueur' => $l['longueur'], 'brise_vent' => $bv,
                'serie' => $serie, 'colonne' => $colonne !== null && $serie !== [] ? $colId : null, 'compte' => $compte,
                'plants' => $plants, 'sans_plant' => $refus === null ? $sansPlant : [], 'refus' => $refus];
        }
        foreach ($colonnes as $cadre => $id) {
            if ($id !== null && (int) $id > 0 && !isset($retenues[(int) $id])) {
                $out['notes'][] = 'La colonne vertébrale de la ' . mb_strtolower(Bordures::CADRES[$cadre]['libelle'] ?? $cadre)
                    . ' n’est pas une espèce retenue de la liste.';
            }
        }
        if ($ok) {
            foreach ($retenues as $id => $e) {
                if (!isset($placees[$id])) {
                    $out['sans_place'][] = ['espece' => $e, 'raisons' => $raisonsDe[$id] ?? []];
                }
            }
        }
        $out['ok'] = $ok;
        return $out;
    }

    /**
     * La série d'un côté : la colonne un plant sur deux (C, A, C, B…), sinon les autres espèces telles quelles.
     *
     * @param list<array<string, mixed>> $autres
     * @return list<array<string, mixed>>
     */
    private static function composer(?array $colonne, array $autres): array
    {
        if ($colonne === null) {
            return $autres;
        }
        $serie = [];
        foreach ($autres as $a) {
            $serie[] = $colonne;
            $serie[] = $a;
        }
        return $serie;
    }

    private static function nom(array $e): string
    {
        return (string) (($e['nom_usuel'] ?? '') !== '' ? $e['nom_usuel'] : ($e['nom_scientifique'] ?? ''))
            . ((string) ($e['variete'] ?? '') !== '' ? ' (' . $e['variete'] . ')' : '');
    }
}
