<?php

declare(strict_types=1);

namespace Syntropia;

/**
 * Ce que quelqu'un peut faire **dans une application donnée**.
 *
 * Adapté de `application/src/Role.php` (dépôt de référence). Le rôle
 * reste par application — même si syntropia ne partage plus ses comptes
 * avec personne pour l'instant, la colonne `acces.application` garde ce
 * sens : rien n'empêche un jour un compte à la fois praticien syntropia
 * et administrateur d'une autre application, sans que l'un déteigne sur
 * l'autre.
 *
 * L'absence de ligne dans `acces` vaut absence de droit — le défaut est la
 * porte fermée, pas la porte ouverte par oubli.
 */
enum Role: string
{
    case Visiteur = 'visiteur';
    case Utilisateur = 'utilisateur';
    case Administrateur = 'administrateur';

    public function libelle(): string
    {
        return match ($this) {
            self::Visiteur => 'visiteur',
            self::Utilisateur => 'utilisateur',
            self::Administrateur => 'administrateur',
        };
    }

    /** Peut ouvrir un site, concevoir, saisir. */
    public function peutEcrire(): bool
    {
        return $this !== self::Visiteur;
    }

    public function peutAdministrer(): bool
    {
        return $this === self::Administrateur;
    }
}
