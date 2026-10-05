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
     * `constat.adventices`, des adventices reviennent-elles ? (colonne facultative, idem). 6 :
     * `succession_ligne.planche`, la planche où une culture du potager est placée (colonne facultative, idem). 7 :
     * `succession_ligne.date_semis`, le semis à l'abri avant le repiquage (colonne facultative, idem). 8 :
     * `succession_ligne.nb_plants`, combien de plants une culture du potager demande (colonne facultative, idem). 9 :
     * `parcelle.stade`, le stade du terrain (colonne facultative, idem). 10 :
     * `espece.periode_semis`/`periode_repiquage`/`periode_recolte`, le calendrier d'une espèce, en mots comme
     * Lopez (trois colonnes facultatives, idem). 11 : `parcelle.tortue`, le type de tortue, et `espece.cycle`, le cycle
     * de vie (deux colonnes facultatives, idem). 12 : la table `ligne_plantation`, les lignes de plants des bordures
     * (table facultative, idem). 13 : `espece.delai_germination_j`, le délai de germination (colonne facultative, idem).
     * 14 : les tables `ligne_calendrier` et `perturbation_prevue`, le calendrier prévisionnel des lignes (facultatives, idem).
     * 15 : `espece.temperature_sol_semis_c`, la température du sol qui permet le semis (colonne facultative, idem).
     * 16 : `espece.hauteur_m`, la hauteur de la plante adulte (colonne facultative, idem).
     * 17 : `espece.largeur_m`, l'envergure de la plante adulte (colonne facultative, idem).
     * 18 : `espece.strate_production` et `espece.strate_service`, la strate selon l'usage (colonnes facultatives, idem).
     * 19 : `parcelle.stade` gagne l'abondance (une valeur de plus : un fichier plus ancien se charge tel quel).
     * 20 : le constat du saut du chat sans témoin — `constat.hauteur_cm`, `biomasse`, `sol` (colonnes facultatives) ;
     * `temoin_decrit` et `temoin_pousse_cm` retirées. Un fichier plus ancien se charge encore : l'import ignore ces deux
     * champs (`Import::RETIREES`), sans refermer la fenêtre.
     */
    public const SCHEMA = 20;

    /** Le plus ancien schéma dont un export se recharge encore tel quel. */
    public const SCHEMA_IMPORTABLE_DEPUIS = 1;

    /** Le numéro de l'atelier — à fixer par le porteur ; informatif. */
    public const APPLICATION = '0.9.0';

    /**
     * Le déploiement en ligne : une ligne que `bin/deployer.py fichiers` écrit sur le serveur à chaque envoi
     * (`contenu/deploiement.txt` — la date et le commit), jamais dans le dépôt. Vide en local. Il change à chaque mise à
     * jour, même quand `APPLICATION` ne bouge pas (chantier-mises-a-jour.md, Phase 4).
     */
    public static function deploiement(): string
    {
        static $lu = null;
        if ($lu === null) {
            $chemin = __DIR__ . '/../contenu/deploiement.txt';
            $lu = is_file($chemin) ? trim((string) file_get_contents($chemin)) : '';
        }
        return $lu;
    }

    /** Une marque courte du déploiement : ce que portent le jeton des formulaires et la réponse de `api-projets.php`. */
    public static function marqueDeploiement(): string
    {
        return substr(hash('sha256', self::deploiement()), 0, 10);
    }
}
