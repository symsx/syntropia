<?php

declare(strict_types=1);

namespace Syntropia;

/**
 * Comment on parle à quelqu'un — **et rien d'autre**.
 *
 * Adapté de `application/src/Profil.php` (dépôt de référence), sans
 * changement de fond. Le profil n'est PAS un droit. Il commande l'écran
 * d'accueil et le niveau de détail ; il n'interdit rien. Trois règles :
 *
 * 1. il n'interdit rien ;
 * 2. il se change à tout moment — le passage de débutant à porteur de
 *    projet est l'objectif du dispositif, il se célèbre, il ne se subit
 *    pas ;
 * 3. il se propose, il ne se déduit jamais en silence.
 *
 * `null` — profil non choisi — est un état normal, pas un défaut à
 * combler d'office. L'application demande, et n'invente pas.
 */
enum Profil: string
{
    case Debutant = 'debutant';
    case Porteur = 'porteur';
    case Praticien = 'praticien';

    public function libelle(): string
    {
        return match ($this) {
            self::Debutant => 'débutant',
            self::Porteur => 'porteur de projet',
            self::Praticien => 'praticien',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Debutant => "Une zone, des graines, l'envie d'essayer. "
                . "Une saison suffit pour commencer.",
            self::Porteur => "Un terrain et un horizon. Concevoir, chiffrer, "
                . "commander, planter.",
            self::Praticien => "Ce qui est planté se conduit. Le calendrier, "
                . "la saisie au champ, les relevés.",
        };
    }

    /**
     * Où l'on arrive en se connectant. Une préférence, jamais une barrière.
     *
     * Provisoire : `plan-du-site.md` n'a encore qu'une seule page réelle
     * (`conception-parcelles.php`). Les trois profils pointent donc tous
     * vers la première étape du parcours, `definition-vision.php` — à
     * différencier une fois que chaque profil aura une page d'accueil qui
     * lui parle vraiment (voir `application/src/Profil.php::accueil()`
     * pour ce que ça donne une fois le site rempli).
     */
    public function accueil(): string
    {
        return 'definition-vision.php';
    }
}
