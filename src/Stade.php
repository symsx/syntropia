<?php

declare(strict_types=1);

namespace Syntropia;

/**
 * Le stade du terrain — d'où l'on part. Pur.
 *
 * Voir `chantier-saut-du-chat.md`, Phase 3. La grille est celle de la formation Joala
 * (`connaissances/formation-joala/systemes-et-abondance.md`) : **colonisation**, **accumulation**, **abondance**.
 * **Le porteur a retenu deux stades, par parcelle** (2026-09-21) : la colonisation et l'accumulation — le document
 * d'origine n'en gardait que deux. L'abondance est le système des milieux mûrs : un terrain que l'on conçoit n'y est
 * pas encore ; la grille la garde comme horizon, non comme point de départ.
 *
 * **Ce que ce stade ne fait pas dire à l'atelier** : ce qu'il faut réaliser en premier à chaque stade — la formation
 * ne le donne pas encore (`chantier-saut-du-chat.md`, question n° 4). L'atelier le dit (« pas encore de repère »), il
 * ne l'invente pas. Les plantes citées sont celles de Joala : le document d'origine (non sourcé) rangeait ronces et
 * bouleaux en colonisation, Joala met le bouleau en accumulation et la ronce à la transition vers l'abondance — on suit
 * Joala. La grille « ne remplace pas un diagnostic de sol ni une fiche botanique », dit sa source.
 */
enum Stade: string
{
    case Colonisation = 'colonisation';
    case Accumulation = 'accumulation';

    public function libelle(): string
    {
        return match ($this) {
            self::Colonisation => 'Colonisation',
            self::Accumulation => 'Accumulation',
        };
    }

    /** Ce que le stade est, en une phrase. */
    public function explication(): string
    {
        return match ($this) {
            self::Colonisation => 'Une installation dans des conditions difficiles : faibles ressources, organismes peu exigeants, un sol jeune où dominent les bactéries et où les champignons manquent.',
            self::Accumulation => 'Une croissance rapide qui multiplie et accumule la biomasse : la friche qui évolue vers le boisement.',
        };
    }

    /** Ce qui s'observe, d'après Joala — des repères, non un diagnostic. */
    public function observe(): string
    {
        return match ($this) {
            self::Colonisation => 'Peu de vie dans le sol, pas de champignons ; des mousses et des lichens (des cactus et des agaves sous d’autres climats). Le relief compte : les creux gardent l’eau et les minéraux et font des microclimats abrités, les bosses restent exposées.',
            self::Accumulation => 'Des saules, peupliers, bouleaux, noisetiers, aubépines, prunelliers qui s’installent tout seuls. La ronce se tient à la transition vers l’abondance.',
        };
    }

    /** Ce que le corpus permet d'affirmer de l'usage de ce stade pour concevoir — rien de plus. */
    public function usage(): string
    {
        return match ($this) {
            self::Colonisation => 'La succession va de la colonisation vers l’accumulation, puis l’abondance : ce sont les plantes d’accumulation qui apportent supports et matière pour installer des productions plus exigeantes. Avec peu de moyens, la régénération naturelle assistée est une voie pour commencer.',
            self::Accumulation => 'Les plantes d’accumulation fournissent déjà des supports et de la matière pour installer des productions plus exigeantes ; les épineux (aubépine, prunellier) protègent les jeunes plants de l’abroutement au démarrage.',
        };
    }

    /** La phrase qui dit, sans détour, ce qu'on ne sait pas encore. */
    public static function premierGeste(): string
    {
        return 'Ce qu’il faut réaliser en premier à ce stade : pas encore de repère — la formation ne le donne pas encore, et l’atelier ne l’invente pas.';
    }

    /** Le stade, ou `null` s'il n'est pas dit (ou n'est pas l'un des deux). */
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
