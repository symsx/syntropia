<?php

declare(strict_types=1);

namespace Syntropia;

/**
 * Le stade du terrain — d'où l'on part. Pur.
 *
 * Voir `chantier-saut-du-chat.md`, Phase 3. La grille est celle de la formation Joala
 * (`connaissances/formation-joala/systemes-et-abondance.md`) : **colonisation**, **accumulation**, **abondance**,
 * par parcelle. Le porteur avait retenu deux stades le 2026-09-21 ; il a ajouté l'abondance le 2026-10-02 (schéma 19).
 *
 * **Ce qu'on fait à chaque stade** (`repere()`, `chantier-saut-du-chat.md`, question n° 4) : le module 6 de la formation
 * (`6-8`, `connaissances/formation-joala/printemps-mini-foret-et-sol.md`) règle la densité de plantation et la fréquence
 * de taille sur l'**état du sol** — pauvre, intermédiaire, vers le climax. **La correspondance avec les trois stades
 * est celle du porteur** (2026-10-02) : sol pauvre = colonisation, intermédiaire = accumulation, vers le climax =
 * abondance. Rien de plus n'est chiffré : ni la densité, ni la fréquence. Les plantes citées sont celles de Joala : le document d'origine (non sourcé) rangeait ronces et
 * bouleaux en colonisation, Joala met le bouleau en accumulation et la ronce à la transition vers l'abondance — on suit
 * Joala. La grille « ne remplace pas un diagnostic de sol ni une fiche botanique », dit sa source.
 */
enum Stade: string
{
    case Colonisation = 'colonisation';
    case Accumulation = 'accumulation';
    case Abondance = 'abondance';

    public function libelle(): string
    {
        return match ($this) {
            self::Colonisation => 'Colonisation',
            self::Accumulation => 'Accumulation',
            self::Abondance => 'Abondance',
        };
    }

    /** Ce que le stade est, en une phrase. */
    public function explication(): string
    {
        return match ($this) {
            self::Colonisation => 'Une installation dans des conditions difficiles : faibles ressources, organismes peu exigeants, un sol jeune où dominent les bactéries et où les champignons manquent.',
            self::Accumulation => 'Une croissance rapide qui multiplie et accumule la biomasse : la friche qui évolue vers le boisement.',
            self::Abondance => 'Des réseaux d’interactions complexes et une circulation rapide des ressources : un sol mûr, des arbres souvent longévifs et des plantes exigeantes.',
        };
    }

    /** Ce qui s'observe, d'après Joala — des repères, non un diagnostic. */
    public function observe(): string
    {
        return match ($this) {
            self::Colonisation => 'Peu de vie dans le sol, pas de champignons ; des mousses et des lichens (des cactus et des agaves sous d’autres climats). Le relief compte : les creux gardent l’eau et les minéraux et font des microclimats abrités, les bosses restent exposées.',
            self::Accumulation => 'Des saules, peupliers, bouleaux, noisetiers, aubépines, prunelliers qui s’installent tout seuls. La ronce se tient à la transition vers l’abondance.',
            self::Abondance => 'Des arbres longévifs et exigeants (pommier, poirier, cognassier, noyer, cerisier), un sol riche en champignons, un sous-bois qui se ferme. L’abondance est relative : une prairie peut en être une.',
        };
    }

    /** Ce que le corpus permet d'affirmer de l'usage de ce stade pour concevoir — rien de plus. */
    public function usage(): string
    {
        return match ($this) {
            self::Colonisation => 'La succession va de la colonisation vers l’accumulation, puis l’abondance : ce sont les plantes d’accumulation qui apportent supports et matière pour installer des productions plus exigeantes. Avec peu de moyens, la régénération naturelle assistée est une voie pour commencer.',
            self::Accumulation => 'Les plantes d’accumulation fournissent déjà des supports et de la matière pour installer des productions plus exigeantes ; les épineux (aubépine, prunellier) protègent les jeunes plants de l’abroutement au démarrage.',
            self::Abondance => 'L’énergie y est réinjectée presque aussitôt plutôt que stockée : c’est l’horizon de la succession, où les productions exigeantes trouvent leur place.',
        };
    }

    /**
     * Ce qu'on fait à ce stade, d'après la formation (Joala 6-8) : la densité de plantation et la fréquence de taille.
     * Un sens, non un chiffre — la source n'en donne pas.
     */
    public function repere(): string
    {
        return match ($this) {
            self::Colonisation => 'À ce stade, la formation conseille de planter très dense et de tailler très souvent : « plus le sol est pauvre, plus on plante », et les trois premières années une perturbation forte lance la vie du sol.',
            self::Accumulation => 'À ce stade, la formation conseille une densité modérée et des tailles qui s’espacent : la vie du sol ralentit d’elle-même.',
            self::Abondance => 'À ce stade, les strates sont stables et l’on taille peu (« plus ça va, moins on taille ») ; l’apport de matière ralentit et se stabilise.',
        };
    }

    /** Le stade, ou `null` s'il n'est pas dit (ou n'est pas l'un des trois). */
    public static function depuis(?string $valeur): ?self
    {
        return $valeur === null ? null : self::tryFrom(trim($valeur));
    }

    /** @return list<self> */
    public static function tous(): array
    {
        return self::cases();
    }
}
