<?php

declare(strict_types=1);

namespace Syntropia;

/**
 * Proposer le placement des cultures d'un potager sur les planches de son plan — pur, sans base.
 *
 * Voir `chantier-plan-de-culture.md` (Phase 3) et `chantier-associations.md`. Le porteur (2026-09-20) : le placement se
 * fait **par planche**, selon les associations — regrouper ce qui s'entend bien, **éviter** certains voisinages.
 * L'atelier **propose**, la personne accepte, déplace ou ignore : rien ne s'écrit sans elle.
 *
 * **Un algorithme simple, que l'on peut relire.**
 * 1. Une culture **déjà placée ne bouge jamais** : la proposition ne concerne que les cultures non placées.
 * 2. On traite d'abord les plus contraintes — celles dont on connaît le plus de relations avec les autres cultures —,
 *    puis par nom : le résultat est le même à chaque fois.
 * 3. Une planche est **écartée** si elle porte déjà un voisin à éviter (ce que les tables de Weinrich disent défavorable,
 *    ou contradictoire). Si toutes le sont, la culture **reste non placée**, et la raison est dite : mieux vaut le dire que
 *    forcer un voisinage néfaste — c'est à la personne d'ajouter une planche ou d'en décider autrement.
 * 4. Parmi les planches restantes, on choisit celle où **le plus de voisins s'entendent bien** avec elle ; à égalité, la
 *    moins chargée, puis la première. Une paire **sans relation connue ne compte pas** : inconnue n'est pas neutre, mais
 *    elle ne pèse ni pour ni contre.
 * 5. Les cultures se **répartissent** sur les planches du plan : aucune ne dépasse `ceil(total / planches)`, ni **moins de
 *    deux** (une association, c'est plusieurs cultures sur une même planche), ni ce qu'une planche porte déjà. Sans cela, tout ce
 *    qui s'entend bien s'entasserait sur une seule planche. C'est un équilibre, non un plafond agronomique : le corpus ne dit pas
 *    combien de cultures une planche peut porter.
 *
 * Les tables ne sont pas symétriques et ne sont pas des preuves : `Associations::relation()` lit une relation dès qu'une
 * des deux plantes la cite, et ce sont des ordres de conduite.
 */
final class PlanDeCulture
{
    /**
     * @param list<array{id: int, espece: string, planche: int|null, nature?: ?string}> $cultures toutes les cultures de la
     *        parcelle ; `nature` (`perenne`/`annuelle`, d'après le cycle du référentiel) quand on la connaît
     * @param int $nbPlanches les planches du plan
     * @param array<int, array{nature: ?string, bandes: ?array}> $natures `Planches::natures()` — tortue zèbre : une plante
     *        ne va que sur une planche de sa nature ; tortue abeille : elle va dans la bande de sa nature
     * @return array{placements: list<array{id: int, espece: string, planche: int, bande: ?string, raison: string}>, non_places: list<array{id: int, espece: string, raison: string}>}
     */
    public static function proposer(array $cultures, int $nbPlanches, ?string $dossierAssociations = null, array $natures = []): array
    {
        $vide = ['placements' => [], 'non_places' => []];
        if ($nbPlanches < 1) {
            return $vide;
        }
        $aPlacer = array_values(array_filter($cultures, static fn (array $c): bool => $c['planche'] === null));
        if ($aPlacer === []) {
            return $vide;
        }

        // Ce que chaque planche porte : les cultures déjà placées d'abord, puis celles que l'on propose.
        $planches = array_fill(1, $nbPlanches, []);
        foreach ($cultures as $c) {
            if ($c['planche'] !== null && isset($planches[$c['planche']])) {
                $planches[$c['planche']][] = (string) $c['espece'];
            }
        }
        // Jamais moins de deux : une association, c'est plusieurs cultures sur une même planche.
        $cap = max(2, (int) ceil(count($cultures) / $nbPlanches), ...array_map('count', $planches));

        // Les plus contraintes d'abord : le plus de relations connues avec les autres cultures, puis le nom.
        $contrainte = static function (array $c) use ($cultures, $dossierAssociations): int {
            $n = 0;
            foreach ($cultures as $autre) {
                if ($autre['id'] !== $c['id'] && Associations::relation((string) $c['espece'], (string) $autre['espece'], $dossierAssociations)['relation'] !== Associations::INCONNUE) {
                    $n++;
                }
            }
            return $n;
        };
        $poids = [];
        foreach ($aPlacer as $c) {
            $poids[$c['id']] = $contrainte($c);
        }
        usort($aPlacer, static fn (array $a, array $b): int => [$poids[$b['id']], mb_strtolower((string) $a['espece']), $a['id']]
            <=> [$poids[$a['id']], mb_strtolower((string) $b['espece']), $b['id']]);

        $placements = [];
        $nonPlaces = [];
        foreach ($aPlacer as $c) {
            $nom = (string) $c['espece'];
            $meilleure = null;
            $bloquees = [];
            $autreNature = false;
            $natureC = $c['nature'] ?? null;
            foreach ($planches as $numero => $noms) {
                if (count($noms) >= $cap) {
                    continue;
                }
                // Tortue zèbre : une plante pérenne va sur une planche pérenne, une annuelle sur une annuelle (Phase 5 du
                // chantier « peuplement de la parcelle »). Une nature inconnue — cycle non noté — ne contraint rien.
                $natureP = $natures[$numero]['nature'] ?? null;
                if ($natureC !== null && $natureP !== null && $natureC !== $natureP) {
                    $autreNature = true;
                    continue;
                }
                $bien = [];
                $eviter = [];
                foreach ($noms as $voisin) {
                    $r = Associations::relation($nom, $voisin, $dossierAssociations)['relation'];
                    if ($r === Associations::FAVORABLE) {
                        $bien[] = $voisin;
                    } elseif ($r === Associations::DEFAVORABLE || $r === Associations::DISCORDANT) {
                        $eviter[] = $voisin;
                    }
                }
                if ($eviter !== []) {
                    $bloquees[$numero] = $eviter;
                    continue;
                }
                $cle = [count($bien), -count($noms), -$numero];
                if ($meilleure === null || $cle > $meilleure['cle']) {
                    $meilleure = ['cle' => $cle, 'numero' => $numero, 'bien' => $bien];
                }
            }
            if ($meilleure === null) {
                $voisins = array_values(array_unique(array_merge(...array_values($bloquees) ?: [[]])));
                $nonPlaces[] = ['id' => $c['id'], 'espece' => $nom, 'raison' => $bloquees !== []
                    ? 'Chaque planche a un voisin à éviter (' . implode(', ', $voisins) . ') : ajoutez une planche, ou décidez vous-même.'
                    : ($autreNature
                        ? 'Plante ' . ($natureC === 'perenne' ? 'pérenne' : 'annuelle') . ' : aucune planche ' . ($natureC === 'perenne' ? 'pérenne' : 'annuelle') . ' n’a de place — changez la nature d’une planche dans le plan, ou décidez vous-même.'
                        : 'Toutes les planches sont pleines.')];
                continue;
            }
            $planches[$meilleure['numero']][] = $nom;
            // Tortue abeille : la plante va dans la bande de sa nature, si on la connaît.
            $bande = isset($natures[$meilleure['numero']]['bandes']) && $natureC !== null ? $natureC : null;
            $placements[] = ['id' => $c['id'], 'espece' => $nom, 'planche' => $meilleure['numero'], 'bande' => $bande,
                'raison' => ($meilleure['bien'] !== []
                    ? 'S’entend bien avec ' . implode(', ', $meilleure['bien']) . '.'
                    : 'Aucun voisin connu sur cette planche : placée là où il y a de la place.')
                    . ($bande !== null ? ' Dans la bande ' . ($bande === 'perenne' ? 'pérenne' : 'annuelle') . '.' : '')];
        }
        usort($placements, static fn (array $a, array $b): int => [$a['planche'], mb_strtolower($a['espece'])] <=> [$b['planche'], mb_strtolower($b['espece'])]);
        return ['placements' => $placements, 'non_places' => $nonPlaces];
    }

    /** L'empreinte de l'état des placements : une proposition ne s'applique que sur l'état qu'on a vu. */
    public static function empreinte(array $cultures): string
    {
        $etat = array_map(static fn (array $c): array => [(int) $c['id'], $c['planche'] === null ? null : (int) $c['planche'], (string) $c['espece']], $cultures);
        usort($etat, static fn (array $a, array $b): int => $a[0] <=> $b[0]);
        return hash('sha256', json_encode($etat, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}
