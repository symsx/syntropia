<?php

declare(strict_types=1);

namespace Syntropia;

/**
 * Le type de tortue d'une parcelle — ce qu'elle porte sur ses planches. Pur.
 *
 * Voir `chantier-peuplement-parcelle.md`, Phase 1 bis, et le nœud de compétence
 * `skills-map/nodes/conception-de-la-tortue.md`. Quatre types, décision du porteur (2026-09-23) ; les trois derniers
 * sont ceux de Théry, ch. 15 §§ 7 à 9 (`connaissances/livre-bienvenue-en-syntropie/ch15-plantations-perennes.md`).
 * Réglé par parcelle, avec la conduite et le stade ; il orientera les propositions de peuplement (planches pérennes ou
 * annuelles, bande pérenne sur la planche, biomasse annuelle en 3D ou non).
 */
enum Tortue: string
{
    case Classique = 'classique';
    case Zebre = 'zebre';
    case Abeille = 'abeille';
    case Liane = 'liane';

    public function libelle(): string
    {
        return match ($this) {
            self::Classique => 'Classique',
            self::Zebre => 'Zèbre',
            self::Abeille => 'Abeille',
            self::Liane => 'Liane',
        };
    }

    /** Ce que la tortue porte, en une phrase, d'après sa source. */
    public function explication(): string
    {
        return match ($this) {
            self::Classique => 'Des planches de production, une ligne centrale de biomasse et des bordures vivantes (Théry, ch. 8 et 11).',
            self::Zebre => 'Des planches pérennes (petits fruits, tisanes, mellifères, biomasse ligneuse) alternent avec des planches annuelles (légumes, pépinières). La biomasse annuelle en 3D doit réussir sur les lignes pérennes (Théry, ch. 15 § 7).',
            self::Abeille => 'Chaque planche porte une bande pérenne et une bande potagère. Les émergents mesurent 3,50 m au plus. Cette tortue prend deux fois la surface d’un potager classique (Théry, ch. 15 § 8).',
            self::Liane => 'C’est une tortue d’ombre, pour les étés secs et les pluies violentes, sur de petites surfaces. Une structure porte des lianes émergentes, avec la strate haute (vigne, kiwi) à leur ombre. Elle protège dessous une succession potagère sans biomasse 3D. Le maïs et le tournesol n’y poussent que les deux premières années, le temps que les lianes s’installent (Théry, ch. 15 § 9).',
        };
    }

    /** @return list<self> dans l'ordre du choix */
    public static function tous(): array
    {
        return self::cases();
    }

    /** Le type enregistré, ou `null` s'il n'est pas dit ou n'est pas reconnu. */
    public static function depuis(mixed $valeur): ?self
    {
        return is_string($valeur) ? self::tryFrom(trim($valeur)) : null;
    }
}
