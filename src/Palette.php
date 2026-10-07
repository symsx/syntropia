<?php

declare(strict_types=1);

namespace Syntropia;

/**
 * La palette végétale d'une parcelle : ses trois places et les neuf fonctions de service.
 *
 * Chantier `chantier-palette-vegetale.md`, Phase 1. L'ordre est celui du ch. 11 de Théry et de la formation (`7-7`) :
 * les productions principales d'abord (« tout se construit autour d'eux »), puis les secondaires, puis les plantes de
 * service. Les neuf fonctions sont celles retenues par le porteur le 2026-10-05 ; chacune dit ce qu'elle apporte à la
 * parcelle, et les rôles du référentiel (`espece.role`, `Scenario::FONCTIONS`) qui la désignent — ce sont eux qui
 * mettent en tête les espèces conseillées pour cette fonction.
 */
final class Palette
{
    public const PLACES = [
        'principale' => 'Productions principales',
        'secondaire' => 'Productions secondaires',
        'service' => 'Plantes de service',
    ];

    public const FONCTIONS = [
        'biomasse' => ['libelle' => 'Biomasse', 'roles' => ['biomasse', 'perturbation'],
            'apport' => 'Elle se taille souvent, et ce qu’on coupe nourrit le sol.'],
        'bloque_herbe' => ['libelle' => 'Bloque-herbe et couvre-sol', 'roles' => ['bloque_herbe', 'couvre_sol'],
            'apport' => 'Elle empêche l’herbe d’entrer dans la parcelle et garde le sol couvert.'],
        'vent' => ['libelle' => 'Brise-vent', 'roles' => ['vent'],
            'apport' => 'Elle freine le vent, qui dessèche le sol et casse les jeunes plants.'],
        'azote' => ['libelle' => 'Fixer l’azote', 'roles' => ['azote'],
            'apport' => 'Elle fixe l’azote de l’air, et ses voisines en profitent.'],
        'nectar' => ['libelle' => 'Nectar et pollen', 'roles' => ['nectar', 'pollen'],
            'apport' => 'Elle nourrit les pollinisateurs et les insectes utiles.'],
        'refuge' => ['libelle' => 'Abriter les auxiliaires', 'roles' => ['refuge'],
            'apport' => 'Elle abrite et nourrit les auxiliaires qui régulent les ravageurs.'],
        'ombre' => ['libelle' => 'Ombrer', 'roles' => ['ombre'],
            'apport' => 'Elle donne de l’ombre aux jeunes plants qui craignent les brûlures du soleil.'],
        'tuteur' => ['libelle' => 'Tuteur vivant', 'roles' => ['tuteur'],
            'apport' => 'Elle porte les lianes et les plantes grimpantes.'],
        'protection' => ['libelle' => 'Protéger de l’abroutement', 'roles' => ['protection'],
            'apport' => 'Elle protège les jeunes plants de la dent des animaux.'],
    ];

    /**
     * Les fonctions d'une ligne de palette (`palette.fonctions`, multivaluée par `|`), dans l'ordre de `FONCTIONS`. Une
     * valeur inconnue est ignorée.
     *
     * @return list<string>
     */
    public static function fonctionsDe(?string $texte): array
    {
        $lues = array_map('trim', explode('|', (string) $texte));
        return array_values(array_filter(array_keys(self::FONCTIONS), static fn (string $f): bool => in_array($f, $lues, true)));
    }

    /** @param list<string> $fonctions */
    public static function fonctionsEnTexte(array $fonctions): ?string
    {
        $fonctions = self::fonctionsDe(implode('|', $fonctions));
        return $fonctions === [] ? null : implode('|', $fonctions);
    }

    /**
     * Les fonctions de palette que désignent des rôles ou des fonctions du scénario (`espece.role`, `Scenario::FONCTIONS`,
     * `succession_ligne.role`) : « biomasse » ou « perturbation » donnent la biomasse, « nectar » ou « pollen » le nectar…
     * Un rôle sans fonction de palette (« production », « racines », « secours ») n'en donne aucune.
     *
     * @param list<string> $roles
     * @return list<string>
     */
    public static function depuisRoles(array $roles): array
    {
        $roles = array_map('trim', $roles);
        return array_values(array_filter(array_keys(self::FONCTIONS),
            static fn (string $f): bool => array_intersect(self::FONCTIONS[$f]['roles'], $roles) !== []));
    }

    /**
     * Une espèce de la palette que désigne un nom libre (une ligne du scénario, une culture du potager) : même nom
     * usuel, scientifique ou affiché, sans accent ni casse, au singulier ou au pluriel (`Associations::memeNom()`).
     *
     * @param list<array<string, mixed>> $especes les espèces de la palette, telles que le référentiel les décrit
     * @return array<string, mixed>|null
     */
    public static function trouver(string $nom, array $especes): ?array
    {
        if (trim($nom) === '') {
            return null;
        }
        foreach ($especes as $e) {
            foreach ([self::nom($e), (string) ($e['nom_usuel'] ?? ''), (string) $e['nom_scientifique']] as $n) {
                if ($n !== '' && Associations::memeNom($n, $nom)) {
                    return $e;
                }
            }
        }
        return null;
    }

    /** L'espèce du référentiel est-elle conseillée pour cette fonction, d'après son rôle ? */
    public static function conseillee(array $espece, string $fonction): bool
    {
        $roles = Scenario::fonctionsDe($espece['role'] ?? null);
        return array_intersect($roles, self::FONCTIONS[$fonction]['roles'] ?? []) !== [];
    }

    /** L'espèce est-elle notée au référentiel comme une plante de production ? */
    public static function deProduction(array $espece): bool
    {
        return in_array('production', array_map('trim', explode('|', (string) ($espece['role'] ?? ''))), true);
    }

    /**
     * Le nom qu'une ligne du scénario ou une culture du potager enregistre pour une espèce de la palette : son nom usuel,
     * ou son nom scientifique, sans la variété — celui que les tables de voisinage, le calendrier et le référentiel
     * reconnaissent (`Associations::memeNom()`, `Especes::parNomUsage()`), comme les noms déjà saisis.
     */
    public static function nomCourt(array $espece): string
    {
        return trim((string) ($espece['nom_usuel'] ?? '')) ?: (string) $espece['nom_scientifique'];
    }

    /** Le nom affiché d'une espèce : son nom usuel, ou son nom scientifique, et sa variété. */
    public static function nom(array $espece): string
    {
        $nom = trim((string) ($espece['nom_usuel'] ?? '')) ?: (string) $espece['nom_scientifique'];
        $variete = trim((string) ($espece['variete'] ?? ''));
        return $variete !== '' ? $nom . ' (' . $variete . ')' : $nom;
    }
}
