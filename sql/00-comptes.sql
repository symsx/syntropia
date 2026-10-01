-- =====================================================================
-- Base `syntropia_comptes` — propre à syntropia
--
-- Adapté de `application/sql/00-comptes.sql` (dépôt de référence), où
-- cette base est partagée entre Syntropie et Plantae. Décision du
-- porteur, 2026-09-13 : syntropia a ses PROPRES comptes — rien de
-- partagé avec Syntropie, qui a vocation à être supprimé ou archivé.
-- Reste ouvert : si Plantae doit un jour s'y relier, `acces.application`
-- porte déjà la valeur `'plantae'` pour cet usage, voir plus bas.
--
-- Trois tables, et rien de métier. C'est ce qui rendrait la base sûre à
-- partager de nouveau si le besoin s'en présentait : une base
-- d'authentification qui porterait aussi des données applicatives
-- obligerait chaque migration de l'une à traverser l'autre.
--
-- CIBLE : MariaDB 10.6.27 sur PlanetHoster WorldLite. Le développement se
-- fait contre MySQL 8 : ce fichier n'emploie donc que ce que les deux
-- servent. Règles et pièges : application/conception/portabilite-mariadb.md
--
-- NI `CREATE DATABASE` NI `USE` : la base et l'utilisateur sont créés à
-- la main (voir architecture-technique.md § Le backend) ou, en
-- production, depuis cPanel avec un préfixe imposé.
-- =====================================================================

-- Chaque fichier pose lui-même ses paramètres de session : une colonne
-- issue d'une expression prend la collation du CLIENT, non celle de la
-- base, et phpMyAdmin ne se connecte pas dans la même que le schéma.
-- Leçon payée par Plantae le 17 août 2026 — erreur 1271 à la comparaison.
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_520_ci;
SET time_zone = '+00:00';
SET SESSION sql_mode =
  'STRICT_TRANS_TABLES,ONLY_FULL_GROUP_BY,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- `utf8mb4_unicode_520_ci` est la collation de recherche de Plantae une fois
-- traduite pour MariaDB. Elle existe des DEUX côtés, ce qui évite ici toute
-- traduction : insensible à la casse et aux accents — « Rene » = « René ».


-- ---------------------------------------------------------------------
-- Qui existe
--
-- `identifiant` hérite de la collation insensible casse ET accents : « Rene »
-- et « René » ne peuvent pas coexister. C'est voulu — deux comptes qu'un œil
-- humain confond sont une usurpation en attente.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `compte` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `identifiant`         VARCHAR(64)  NOT NULL,
  `courriel`            VARCHAR(190) NULL,
  `nom_affiche`         VARCHAR(120) NOT NULL,

  -- `password_hash` avec PASSWORD_DEFAULT : bcrypt fait 60 caractères
  -- aujourd'hui, argon2id davantage, et l'algorithme par défaut change avec
  -- les versions de PHP. 255 laisse la place.
  `empreinte`           VARCHAR(255) NOT NULL,
  `doit_changer_mdp`    TINYINT(1)   NOT NULL DEFAULT 0,

  `actif`               TINYINT(1)   NOT NULL DEFAULT 1,
  `cree_le`             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `derniere_connexion`  DATETIME     NULL,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_compte_identifiant` (`identifiant`),
  UNIQUE KEY `uq_compte_courriel`    (`courriel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- Rattrapage d'une table `invitation` créée avant `role_site` :
-- `CREATE TABLE IF NOT EXISTS` ne touche jamais une table déjà là. Écrit avec
-- `information_schema` plutôt qu'`ADD COLUMN IF NOT EXISTS`, qui n'existe que
-- sur MariaDB — ce fichier tourne aussi sur MySQL (poste local).
SET @role_site_existe = (SELECT COUNT(*) FROM information_schema.COLUMNS
                          WHERE TABLE_SCHEMA = DATABASE()
                            AND TABLE_NAME = 'invitation'
                            AND COLUMN_NAME = 'role_site');
SET @ajout = IF(@role_site_existe = 0,
                "ALTER TABLE `invitation` ADD COLUMN `role_site` ENUM('contributeur','lecteur') NULL AFTER `role_accorde`",
                'DO 0');
PREPARE ajout_role_site FROM @ajout;
EXECUTE ajout_role_site;
DEALLOCATE PREPARE ajout_role_site;

-- Sans courriel, aucune réinitialisation de mot de passe n'est possible :
-- c'est alors un administrateur qui remet une empreinte et lève
-- `doit_changer_mdp`. Le champ reste facultatif pour ne pas exiger une
-- adresse de quelqu'un qui n'en veut pas.


-- ---------------------------------------------------------------------
-- Ce que chacun peut, application par application
--
-- Le rôle est PAR APPLICATION, et c'est la condition même du partage :
-- administrateur sur Syntropie n'implique rien sur Plantae. Mutualiser les
-- comptes ne doit jamais revenir à mutualiser les pouvoirs.
--
-- Le PROFIL vit ici aussi, parce qu'il n'a de sens que dans une application
-- donnée — « debutant » ne veut rien dire pour un catalogue botanique. Mais
-- il n'est PAS un droit : il commande l'écran d'accueil et le niveau de
-- détail, jamais l'accès. Voir la conception, § 4.3.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `acces` (
  `compte_id`     INT UNSIGNED NOT NULL,
  `application`   VARCHAR(32)  NOT NULL,        -- 'syntropia', 'plantae'
  `role`          ENUM('visiteur','utilisateur','administrateur')
                  NOT NULL DEFAULT 'utilisateur',

  `profil`        ENUM('debutant','porteur','praticien') NULL,
  `profil_choisi_le` DATETIME  NULL,

  `ouvert_le`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`compte_id`, `application`),
  CONSTRAINT `fk_acces_compte` FOREIGN KEY (`compte_id`)
    REFERENCES `compte` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- Absence de ligne = aucun accès à cette application. C'est le défaut, et
-- c'est le bon : mieux vaut une porte fermée qu'une porte ouverte par
-- oubli. Le rôle `visiteur` sert au cas distinct du compte qui existe mais
-- ne peut rien écrire ici.
--
-- `profil` NULL = pas encore choisi. L'application demande au premier
-- passage, et n'invente pas.


-- ---------------------------------------------------------------------
-- Les tentatives ratées
--
-- Plantae les compte en session ; le ralentissement se contourne alors en
-- supprimant le cookie. Ici c'est la base qui compte, sur l'identifiant
-- ESSAYÉ — pas sur le compte, qui peut ne pas exister — et sur l'adresse.
--
-- Un mot de passe seul ne résiste qu'au rythme.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tentative` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `identifiant`  VARCHAR(64)  NOT NULL,
  `adresse`      VARCHAR(45)  NOT NULL,        -- 45 = IPv6 en toutes lettres
  `application`  VARCHAR(32)  NOT NULL,
  `le`           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `ix_tentative_identifiant` (`identifiant`, `le`),
  KEY `ix_tentative_adresse`     (`adresse`, `le`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- À purger : les lignes de plus de 24 h ne servent plus à rien.
--   DELETE FROM tentative WHERE le < NOW() - INTERVAL 1 DAY;
-- Pas d'événement planifié : le planificateur n'est pas garanti sur
-- mutualisé, et de toute façon rien ne s'y administre sans terminal. La
-- purge se fait en PHP, à la connexion réussie.


-- ---------------------------------------------------------------------
-- L'INVITATION — et le parrainage qu'elle laisse derrière elle
--
-- L'inscription se fait sur invitation, jamais librement : un formulaire
-- public sur un domaine ouvert attire du bruit avant d'attirer des
-- porteurs de projet. Voir `conception/comptes-inscription.md`.
--
-- CE QUI DISTINGUE CETTE TABLE D'UN SIMPLE CODE D'ACCÈS : elle conserve le
-- lien entre celui qui invite et celui qui entre. Sur un outil qui se veut
-- le miroir de l'accompagnement d'un projet, savoir par qui quelqu'un est
-- arrivé n'est pas une donnée de police — c'est une donnée de réseau, de
-- même nature que `participant.role_projet`.
--
-- À NE PAS CONFONDRE avec `site.code_invitation`, qui ouvre LES DONNÉES
-- d'un site à quelqu'un qui a DÉJÀ un compte. Celui-ci ouvre un compte.
-- Les deux voyagent ensemble quand on invite quelqu'un à un projet — d'où
-- `site_id` ci-dessous — mais ce sont deux gestes.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `invitation` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,

  -- Huit caractères, du même alphabet que les codes de site : sans I, O,
  -- 0 ni 1, parce qu'un code se dicte à voix haute.
  `code`          VARCHAR(32)  NOT NULL,

  `parrain_id`    INT UNSIGNED NOT NULL,        -- qui invite
  `filleul_id`    INT UNSIGNED NULL,            -- qui est entré, une fois entré
  `courriel_vise` VARCHAR(190) NULL,            -- si l'invitation vise quelqu'un
  `intention`     VARCHAR(190) NULL,            -- « pour la parcelle du bas »

  -- JOINTURE APPLICATIVE, sans clé étrangère : `site` vit dans l'autre
  -- base. En production les deux n'en font qu'une, mais le schéma doit
  -- tenir dans les deux dispositions — même règle que `compte_id` côté
  -- syntropie.
  `site_id`       INT UNSIGNED NULL,

  `role_accorde`  ENUM('visiteur','utilisateur') NOT NULL DEFAULT 'utilisateur',

  -- Le rôle DANS LE PROJET visé par `site_id` — distinct de `role_accorde`,
  -- qui est le rôle du compte dans l'application. NULL quand `site_id` l'est :
  -- une invitation à créer un compte seul ne rattache à aucun projet. Jamais
  -- « porteur » : on devient porteur en ouvrant un projet, pas en y entrant.
  `role_site`     ENUM('contributeur','lecteur') NULL,
  `cree_le`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expire_le`     DATETIME NULL,
  `utilise_le`    DATETIME NULL,

  -- Une invitation se RÉVOQUE, elle ne s'efface pas : le parrainage d'une
  -- invitation jamais utilisée reste la trace de ce qui a été tenté.
  `revoque_le`    DATETIME NULL,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_invitation_code` (`code`),
  KEY `ix_invitation_parrain` (`parrain_id`, `cree_le`),
  CONSTRAINT `fk_invitation_parrain` FOREIGN KEY (`parrain_id`)
    REFERENCES `compte` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_invitation_filleul` FOREIGN KEY (`filleul_id`)
    REFERENCES `compte` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- ---------------------------------------------------------------------
-- LE JOURNAL DES CONNEXIONS — demandé par le porteur le 2026-10-01, pour
-- qu'un administrateur voie qui utilise l'atelier (journal-connexions.php).
-- Une ligne par connexion RÉUSSIE, sans adresse IP (décision du porteur :
-- moins de données personnelles) ; les lignes de plus de 90 jours
-- s'effacent à chaque nouvelle connexion (Comptes::noterConnexion()).
-- Une table ajoutée : rien ne change pour ce que Plantae lit de `compte`
-- et `acces`.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `connexion` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `compte_id`    INT UNSIGNED NOT NULL,
  `application`  VARCHAR(32)  NOT NULL,
  `le`           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `ix_connexion_le`     (`application`, `le`),
  KEY `ix_connexion_compte` (`compte_id`, `le`),
  CONSTRAINT `fk_connexion_compte` FOREIGN KEY (`compte_id`)
    REFERENCES `compte` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
