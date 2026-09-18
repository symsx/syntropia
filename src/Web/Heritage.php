<?php

declare(strict_types=1);

namespace Syntropia\Web;

use Syntropia\Conduite;

/**
 * Une parcelle prend du site ce qu'elle ne précise pas — le plus précis gagne :
 *
 *     site            →  parcelle
 *     « Syntropie »      (hérite)  /  « Jardin-forêt » ici
 *
 * **Très réduit par rapport à `Heritage` de la référence** (chantier
 * `chantier-scenario-plantation.md`, Phase 2) : seule la conduite se résout
 * ici. Le sol et l'exposition (`ph`, `texture`, `drainage`…) restent hors
 * périmètre, comme `Parcelles.php` le note depuis la carte interactive.
 */
final class Heritage
{
    /**
     * La valeur qui s'applique, et d'où elle vient : `niveau` vaut `parcelle`,
     * `site`, ou `null` quand ni l'un ni l'autre n'a répondu.
     *
     * @param array<string, mixed> $parcelle
     * @param array<string, mixed> $site
     * @return array{valeur: ?string, niveau: ?string}
     */
    public static function resoudre(array $parcelle, array $site, string $colonne): array
    {
        foreach (['parcelle' => $parcelle, 'site' => $site] as $niveau => $source) {
            $brut = $source[$colonne] ?? null;
            // Une chaîne vide vaut « non renseigné » : « renseigné à vide » et
            // « pas encore renseigné » sont la même chose ici.
            if ($brut !== null && trim((string) $brut) !== '') {
                return ['valeur' => (string) $brut, 'niveau' => $niveau];
            }
        }
        return ['valeur' => null, 'niveau' => null];
    }

    /**
     * La conduite qui s'applique ici. Elle ne décide pas d'une valeur
     * affichée mais **du vocabulaire avec lequel toute la page parle** — les
     * strates, l'unité de conception, le sens de la taille. D'où une
     * `Conduite` rendue, non une chaîne. Ni site ni parcelle n'a choisi : le
     * vocabulaire retombe sur la syntropie (défaut de `Conduite::depuis()`) et
     * le niveau vaut `aucun` — à dire « pas encore choisi ».
     *
     * @param array<string, mixed> $parcelle
     * @param array<string, mixed> $site
     * @return array{conduite: Conduite, niveau: string}
     */
    public static function conduite(array $parcelle, array $site): array
    {
        $resolu = self::resoudre($parcelle, $site, 'conduite');
        return [
            'conduite' => Conduite::depuis($resolu['valeur']),
            'niveau' => $resolu['niveau'] ?? 'aucun',
        ];
    }
}
