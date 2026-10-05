<?php

declare(strict_types=1);

namespace Syntropia;

/**
 * Les quatre strates de lumière de la syntropie — ce qu'elles demandent et ce qu'elles couvrent. Pur.
 *
 * Source : Théry, ch. 05, p. 38-39 (`connaissances/livre-bienvenue-en-syntropie/ch05-le-mousquetaire-espace.md`), établi
 * dans le sud de la Dordogne — « il n'y a pas de lecture absolue pour ce tableau ». Les strates classent les plantes par
 * **besoin en lumière**, non par taille, et ne sont pas celles du jardin-forêt (`Conduite::JardinForet`).
 *
 * Deux usages, décidés par le porteur (2026-09-30) :
 *  - **proposer la strate** d'une plante d'après le référentiel : **sa strate de production, ou sa strate de service**
 *    (biomasse, bloque-herbe) — `espece.strate_production` / `strate_service`, le P et le B du tableau p. 38 (maïs P
 *    émergente, maïs B haute ; sureau et figuier P haute, B moyenne ; décision du porteur, 2026-09-30, schéma 18). Une
 *    colonne vide : la seule strate syntropique de `strates_possibles` s'il n'y en a qu'une ; plusieurs (la fève, la pomme
 *    de terre, qui changent de strate au fil du cycle) : rien n'est deviné ;
 *  - **l'occupation au sol** : la surface projetée des houppiers de chaque strate — des plants gardés jusqu'à l'âge
 *    adulte, sans la biomasse —, comparée au repère de la source (émergente 20 %, haute 40 %, moyenne 60 %, basse 80 % —
 *    la somme dépasse 100 %, « c'est voulu »).
 */
final class Strates
{
    /** De la plus haute à la plus basse : besoin en lumière (%) et part de la surface au sol couverte (%). */
    public const REPERES = [
        'emergente' => ['libelle' => 'Émergente', 'lumiere' => [80, 100], 'sol' => 20],
        'haute' => ['libelle' => 'Haute', 'lumiere' => [60, 80], 'sol' => 40],
        'moyenne' => ['libelle' => 'Moyenne', 'lumiere' => [40, 60], 'sol' => 60],
        'basse' => ['libelle' => 'Basse', 'lumiere' => [20, 40], 'sol' => 80],
    ];

    /** @return list<string> les strates syntropiques d'une espèce du référentiel, de la plus haute à la plus basse */
    public static function deLEspece(?array $espece): array
    {
        $notees = array_filter(explode('|', (string) ($espece['strates_possibles'] ?? '')));
        return array_values(array_intersect(array_keys(self::REPERES), $notees));
    }

    /**
     * La strate proposée pour une espèce et un usage : sa strate de production ou de service si le référentiel la dit,
     * sinon sa seule strate syntropique. `null` si rien ne permet de la dire sans deviner.
     */
    public static function proposee(?array $espece, bool $production): ?string
    {
        $dite = (string) ($espece[$production ? 'strate_production' : 'strate_service'] ?? '');
        if (isset(self::REPERES[$dite])) {
            return $dite;
        }
        $s = self::deLEspece($espece);
        return count($s) === 1 ? $s[0] : null;
    }

    /**
     * Ce que le navigateur reçoit d'une espèce pour proposer sa strate (`assets/js/strates.js`) : ses strates de
     * production et de service dites, et toutes ses strates syntropiques. `null` si elle n'en a aucune.
     *
     * @return array{p: ?string, s: ?string, toutes: list<string>}|null
     */
    public static function pourLeNavigateur(array $espece): ?array
    {
        $p = (string) ($espece['strate_production'] ?? '');
        $s = (string) ($espece['strate_service'] ?? '');
        $toutes = self::deLEspece($espece);
        if ($toutes === [] && !isset(self::REPERES[$p]) && !isset(self::REPERES[$s])) {
            return null;
        }
        return ['p' => isset(self::REPERES[$p]) ? $p : null, 's' => isset(self::REPERES[$s]) ? $s : null, 'toutes' => $toutes];
    }

    /**
     * L'occupation au sol par strate : la somme des houppiers (un disque de l'envergure adulte, `espece.largeur_m`, par
     * plant) rapportée à la surface. Une plante sans envergure connue ou sans nombre de plants ne compte pas — elle est
     * nommée, pour que la personne sache ce qui manque.
     *
     * **Seuls les plants gardés jusqu'à l'âge adulte comptent** (le porteur, 2026-10-05) : les plants de biomasse
     * (`biomasse` vrai), taillés ou retirés à la perturbation, n'atteignent jamais leur houppier adulte. Les compter
     * donnait des milliers de pour cent sur une plantation dense, comparés à des repères qui décrivent le système
     * installé. Ils sont comptés à part (`biomasse`), pour que la personne voie ce qui est laissé de côté.
     *
     * @param list<array{nom: string, strate: ?string, n: ?int, largeur: ?float, biomasse?: bool}> $plantes
     * @return array<string, array{libelle: string, repere: int, couverture: ?float, plants: int, biomasse: int,
     *                              sans_envergure: list<string>, sans_nombre: list<string>}>|null  `null` sans surface
     */
    public static function occupation(array $plantes, float $surfaceM2): ?array
    {
        if ($surfaceM2 <= 0) {
            return null;
        }
        $out = [];
        foreach (self::REPERES as $cle => $r) {
            $out[$cle] = ['libelle' => $r['libelle'], 'repere' => $r['sol'], 'couverture' => null, 'plants' => 0,
                'biomasse' => 0, 'sans_envergure' => [], 'sans_nombre' => []];
        }
        $aire = [];
        foreach ($plantes as $p) {
            $s = $p['strate'];
            if ($s === null || !isset($out[$s])) {
                continue;
            }
            if (!empty($p['biomasse'])) {
                $out[$s]['biomasse'] += (int) ($p['n'] ?? 0);
                continue;
            }
            if ($p['n'] === null) {
                $out[$s]['sans_nombre'][] = $p['nom'];
                continue;
            }
            if ($p['largeur'] === null || $p['largeur'] <= 0) {
                $out[$s]['sans_envergure'][] = $p['nom'];
                continue;
            }
            $out[$s]['plants'] += $p['n'];
            $aire[$s] = ($aire[$s] ?? 0.0) + $p['n'] * M_PI * ($p['largeur'] / 2) ** 2;
        }
        foreach ($out as $s => &$o) {
            $o['couverture'] = isset($aire[$s]) ? round(100 * $aire[$s] / $surfaceM2, 1) : null;
            $o['sans_envergure'] = array_values(array_unique($o['sans_envergure']));
            $o['sans_nombre'] = array_values(array_unique($o['sans_nombre']));
        }
        unset($o);
        return $out;
    }
}
