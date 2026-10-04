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
        $fonctions = Scenario::fonctionsDe($role);
        foreach (self::SERVICES as $service => $s) {
            if (array_intersect($s['roles'], $fonctions) !== []) {
                return $service;
            }
        }
        return null;
    }
}
