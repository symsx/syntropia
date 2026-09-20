<?php

declare(strict_types=1);

namespace Syntropia;

/**
 * Les versions de l'atelier — trois choses distinctes, parce qu'elles ne bougent pas ensemble et ne
 * décident pas des mêmes questions.
 *
 * - **`FORMAT`** : la forme du fichier d'export (les clés, leur sens). Monte quand le fichier change de forme.
 * - **`SCHEMA`** : la forme des **données** — les tables et colonnes de `sql/01-syntropia.sql`. C'est elle qui
 *   décide si un fichier peut être rechargé. Elle vit **aussi dans la base** (`atelier_version`), écrite par
 *   le script qui la façonne : la base d'une instance dit donc de quel schéma elle est, quel que soit le code
 *   qui la lit — et le code et la base se déploient séparément (on l'a vu avec `espece.variete`), donc
 *   peuvent ne pas s'accorder. Monte à chaque changement de schéma, **dans le même commit** que le SQL, et
 *   la ligne `INSERT INTO atelier_version` de `01-syntropia.sql` monte avec elle.
 * - **`APPLICATION`** : le numéro des pages et du code. **Informatif** : il s'affiche, il est écrit dans le
 *   fichier, il ne bloque jamais — un changement de page ne change pas la forme des données.
 *
 * **Quand un fichier est rechargeable** : son schéma est dans la fenêtre
 * `[SCHEMA_IMPORTABLE_DEPUIS, SCHEMA]`. Au-delà, il vient d'une instance plus récente (il faut d'abord
 * déployer ici) ; en deçà, d'un schéma trop ancien pour se charger tel quel (il faudrait le migrer). Un
 * ajout de colonne facultative ne referme pas la fenêtre : `SCHEMA` monte, `SCHEMA_IMPORTABLE_DEPUIS` reste,
 * et un ancien fichier — auquel il manque la colonne — se charge, la colonne prenant sa valeur par défaut.
 * Un changement qui casse (colonne retirée ou changée de sens) monte les deux.
 */
final class Version
{
    /** La forme du fichier d'export. */
    public const FORMAT = 1;

    /**
     * La forme des données — doit égaler la ligne `schema` de `atelier_version` (sql/01-syntropia.sql).
     * 1 : premier versionnement. 2 : `site.origine`, la trace d'un import (colonne facultative : la fenêtre
     * ne se referme pas, un export du schéma 1 se recharge). 3 : la table `constat` (le constat du saut du chat :
     * table facultative, un fichier sans elle se charge, ses constats étant simplement absents). 4 :
     * `parcelle.fin_plantation`, la fin de la campagne de plantation (colonne facultative : la fenêtre ne se referme pas). 5 :
     * `constat.adventices`, des adventices reviennent-elles ? (colonne facultative, idem).
     */
    public const SCHEMA = 5;

    /** Le plus ancien schéma dont un export se recharge encore tel quel. */
    public const SCHEMA_IMPORTABLE_DEPUIS = 1;

    /** Le numéro de l'atelier — à fixer par le porteur ; informatif. */
    public const APPLICATION = '0.9.0';
}
