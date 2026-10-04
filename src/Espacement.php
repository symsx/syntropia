<?php

declare(strict_types=1);

namespace Syntropia;

/**
 * L'espacement d'une plante selon sa fonction. Pur.
 *
 * Voir `chantier-espacement-des-services.md` et `skills-map/nodes/conception-de-la-tortue.md`, § L'espacement selon la
 * fonction. Décisions du porteur (2026-10-04) : une espèce a **deux espacements** — l'**espacement de service**
 * (`espece.espacement_retenu_m`), l'écart entre une plante de service et ses voisines, quelles qu'elles soient, et
 * l'**espacement de production** (`espece.espacement_monoculture_m`), l'intervalle entre deux plants de production.
 * **Le service d'une espèce se lit dans son rôle**, par priorité : biomasse (ou perturbation), puis bloque-herbe (ou
 * couvre-sol), puis brise-vent — l'espacement de biomasse, plus serré, renforce l'autre service au lieu de l'empêcher.
 * Pas de colonne « service » : `role` suffit (décision 10).
 */
final class Espacement
{
    /** Les services qui fixent un espacement, du plus prioritaire au moins prioritaire, et les rôles qui y mènent. */
    public const SERVICES = [
        'biomasse' => ['libelle' => 'biomasse', 'roles' => ['biomasse', 'perturbation']],
        'bloque_herbe' => ['libelle' => 'bloque-herbe', 'roles' => ['bloque_herbe', 'couvre_sol']],
        'vent' => ['libelle' => 'brise-vent', 'roles' => ['vent']],
    ];

    /**
     * La part de plants de biomasse visée à l'implantation : « 70 à 80 % » (Théry ch. 11, règle 7 ; Joala 7-7), 75 %
     * retenus par le porteur, par planche au potager et par ligne en pérenne. Sous ce seuil, une information.
     */
    public const PART_BIOMASSE = 0.75;

    /**
     * Le service d'une espèce, d'après son rôle (`espece.role`, multivalué par `|`) ; `null` si elle n'en rend aucun
     * qui fixe un espacement (une production seule, un tuteur…).
     */
    public static function service(?string $role): ?string
    {
        return self::serviceDe(Scenario::fonctionsDe($role));
    }

    /**
     * Le service que donne une liste de fonctions — celles d'une ligne du scénario, ou d'un rôle déjà lu.
     *
     * @param list<string> $fonctions
     */
    public static function serviceDe(array $fonctions): ?string
    {
        foreach (self::SERVICES as $service => $s) {
            if (array_intersect($s['roles'], $fonctions) !== []) {
                return $service;
            }
        }
        return null;
    }

    /**
     * Une ligne du scénario porte-t-elle des plants de biomasse ? Jamais une ligne de production ; une ligne de service,
     * si ses fonctions — à défaut le rôle de l'espèce au référentiel — donnent le service biomasse.
     *
     * @param array<string, mixed> $ligne     une ligne de `scenario_ligne`
     * @param array<string, mixed>|null $espece sa fiche du référentiel
     */
    public static function ligneDeBiomasse(array $ligne, ?array $espece): bool
    {
        $d = Scenario::details($ligne);
        if (($d['production'] ?? null) !== null) {
            return false;
        }
        $fonctions = is_array($d['fonctions'] ?? null) ? $d['fonctions'] : [];
        return (self::serviceDe($fonctions) ?? self::service($espece['role'] ?? null)) === 'biomasse';
    }

    /**
     * La part de plants de biomasse d'un ensemble — une planche de potager, la ligne centrale d'une parcelle pérenne.
     * Un plant sans nombre ne compte pas, et se nomme.
     *
     * @param list<array{nom: string, n: ?int, biomasse: bool}> $plantes
     * @return array{biomasse: int, total: int, part: ?float, atteinte: ?bool, sans_nombre: list<string>}
     */
    public static function partBiomasse(array $plantes): array
    {
        $biomasse = 0;
        $total = 0;
        $sansNombre = [];
        foreach ($plantes as $p) {
            if ($p['n'] === null) {
                $sansNombre[] = $p['nom'];
                continue;
            }
            $total += $p['n'];
            $biomasse += $p['biomasse'] ? $p['n'] : 0;
        }
        $part = $total > 0 ? $biomasse / $total : null;
        return ['biomasse' => $biomasse, 'total' => $total, 'part' => $part,
            'atteinte' => $part === null ? null : $part >= self::PART_BIOMASSE - 1e-9,
            'sans_nombre' => array_values(array_unique($sansNombre))];
    }
}
