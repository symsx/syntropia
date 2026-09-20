-- =====================================================================
-- Base `syntropia` — sites, parcelles, scénarios, suivi
--
-- Copié de `application/sql/01-syntropie.sql` (dépôt de référence) et
-- adapté le 2026-09-13 — voir architecture-technique.md § Le backend.
-- Les colonnes ne sont pas inventées : ce sont celles des CSV de gabarit
-- de `projets/000-modele/` dans le dépôt de référence, qui ont servi
-- avant d'être mises en table. Chaque table dit de quel fichier elle vient.
--
-- ⚠ NON ADAPTÉ ICI, à trancher avant de s'en servir : `site.statut`
-- (ENUM reve/diagnostic/conception/commande/chantier/suivi) reprend le
-- modèle à 7 phases du dépôt de référence, et `reponse.phase` /
-- `synthese.phase` (VARCHAR, valeurs en commentaire) reprennent le
-- vocabulaire Dragon Dreaming (rever/planifier/agir/celebrer) — deux
-- vocabulaires que `plan-du-site.md` a remplacés par les 4 phases
-- retenues (définition/conception/réalisation/suivi), voir CLAUDE.md §
-- Point de reprise. Colonnes laissées telles quelles en attendant que le
-- porteur tranche la correspondance, plutôt que d'en deviner une.
--
-- CIBLE : MariaDB 10.6.27. Ni déclencheur, ni vue, ni procédure — voir
-- application/conception/portabilite-mariadb.md, § 3.
--
-- NI `CREATE DATABASE` NI `USE` : la base et l'utilisateur sont créés à
-- la main (voir architecture-technique.md § Le backend) ou, en
-- production, depuis cPanel avec un préfixe imposé.
-- =====================================================================

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_520_ci;
SET time_zone = '+00:00';
SET SESSION sql_mode =
  'STRICT_TRANS_TABLES,ONLY_FULL_GROUP_BY,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';


-- ---------------------------------------------------------------------
-- LE SITE — `projets/*/CLAUDE.md`
--
-- Un site porte le contexte pédoclimatique, l'intention et la pépinière ;
-- ils sont communs à toutes ses parcelles. **Les valeurs locales priment
-- sur celles du corpus** : c'est la règle du dépôt, et cette table est
-- l'endroit où elle s'applique.
--
-- La LATITUDE n'est pas décorative : elle commande la géométrie solaire.
-- À 45°N l'ombre de midi va de 0,4 H en été à 2,5 H en hiver — facteur 6,
-- contre 0,43 H toute l'année sous l'équateur. C'est ce qui interdit de
-- transposer la stratification tropicale.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `site` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`            VARCHAR(32)  NOT NULL,      -- `001-nom-du-site`
  `nom`             VARCHAR(160) NOT NULL,
  `lieu`            VARCHAR(160) NULL,
  `latitude`        DECIMAL(8,5) NULL,
  `longitude`       DECIMAL(8,5) NULL,
  `altitude_m`      SMALLINT     NULL,
  `surface_totale_m2` DECIMAL(10,2) NULL,
  -- Les 4 phases retenues pour syntropia (`plan-du-site.md`), pas le
  -- vocabulaire à 7 valeurs du dépôt de référence — voir CLAUDE.md §
  -- Point de reprise, tranché le 2026-09-13.
  `statut`          ENUM('definition','conception','realisation','suivi')
                    NOT NULL DEFAULT 'definition',

  -- LA CONDUITE : dans quel monde ce site se trouve.
  --
  -- Les deux disciplines PARTAGENT LE TERRAIN et divergent sur la conduite.
  -- Un sol se lit pareil, un climat se releve pareil, une bouture se prend
  -- pareil. Ce qui change : le vocabulaire des strates (quatre par besoin en
  -- lumiere, ou neuf par place et hauteur), la presence d'un axe du temps
  -- dans le scenario, l'unite de conception (consortium ou guilde), et le
  -- sens de la taille — moteur d'un cote, soin de l'autre.
  --
  -- NOT NULL : un site est conduit d'une maniere ou d'une autre, il n'y a
  -- pas d'etat « indetermine ». Le defaut est la syntropie parce que c'est
  -- ce que le corpus porte a neuf dixiemes, non parce qu'elle vaudrait mieux.
  -- Voir referentiels/articulation-jardin-foret.md
  -- NULL = pas encore choisi. Facultative depuis le 2026-09-07 : le choix
  -- est un chapitre du voyage, a franchir avant la conception, non une
  -- case de la creation. Voir evolutions/2026-09-07-conduite-facultative.sql.
  `conduite`        ENUM('syntropique','jardin-foret') NULL DEFAULT NULL,

  -- Contexte pédoclimatique. Tout est nullable : un site s'ouvre par le
  -- rêve, et le diagnostic vient après — il a même le droit de le contredire.
  `ph`              DECIMAL(3,1) NULL,
  -- Texture et drainage sont deux constats distincts : un sol peut être
  -- argileux ET drainant, sableux ET engorgé par un horizon imperméable.
  -- Les confondre dans un champ ferait perdre celui des deux qu'on n'écrit
  -- pas. Nomenclatures : outils/fiches-terrain/07-texture-et-drainage.md
  `texture`         VARCHAR(60)  NULL,
  `drainage`        VARCHAR(60)  NULL,
  `hydromorphie`    VARCHAR(60)  NULL,
  `hydromorphie_cm` SMALLINT UNSIGNED NULL,
  `pluviometrie_mm` SMALLINT UNSIGNED NULL,
  `repartition_pluies` VARCHAR(190) NULL,
  `dernier_gel_printemps` VARCHAR(60) NULL,
  `entree_dormance` VARCHAR(60) NULL,
  `extremes_releves` VARCHAR(120) NULL,
  `vents_dominants` VARCHAR(120) NULL,

  -- Le MASQUE D'HORIZON : l'ombre que le site subit, distincte de celle que
  -- les plantes se portent. Un obstacle au sud trois fois plus éloigné qu'il
  -- n'est haut ne masque jamais le soleil de midi ; en deçà, la période
  -- s'allonge vite. L'observation prime, le calcul sert quand on ne sait pas.
  -- LA RESSOURCE EN EAU, et elle décide plus qu'on ne croit.
  --
  -- Un plant transplanté a perdu ses racines fines et doit les refaire : ce
  -- sont les trois ans d'irrigation qui paient cette dette. Sans ressource,
  -- le curseur se déplace vers la RNA — semer plutôt que transplanter,
  -- sélectionner ce qui repousse déjà, étaler l'implantation. Le système
  -- reste possible ; il sera plus lent et dépendant des précipitations.
  -- Voir referentiels/articulation-rna.md § 3.6
  `ressource_eau`   ENUM('reseau','puits_forage','reserve','aucune') NULL,
  `ressource_eau_detail` VARCHAR(190) NULL,

  `masque_observe`  VARCHAR(120) NULL,
  `obstacle_hauteur_m`  DECIMAL(5,2) NULL,
  `obstacle_distance_m` DECIMAL(6,2) NULL,
  `fenetre_intervention` ENUM('large','moyenne','etroite') NULL,

  -- Les réglages tranchés ici, qui remplacent les dates du corpus.
  `fenetre_bachage` VARCHAR(120) NULL,
  `fenetre_plantation_ligneux` VARCHAR(120) NULL,
  `fin_tailles_vert` VARCHAR(60) NULL,
  `fenetre_tailles_ligneux` VARCHAR(120) NULL,
  `nb_fauches_couvert` TINYINT UNSIGNED NULL,
  `espacement_lignes_m` DECIMAL(4,2) NULL,
  `orientation_lignes` VARCHAR(60) NULL DEFAULT 'nord-sud',

  -- Le curseur achat / multiplication. C'est lui qui fixe si le décalage
  -- est d'un an ou de deux.
  `part_achat_pct`  TINYINT UNSIGNED NULL,
  `part_multiplication_pct` TINYINT UNSIGNED NULL,

  `cree_le`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `notes`           TEXT NULL,

  -- REJOINDRE UN PROJET SANS DONNER SON ADRESSE.
  --
  -- Un cercle de rêve compte jusqu'à huit personnes ; leur demander à
  -- chacune une adresse pour les inviter serait une barrière. Le code se
  -- transmet de vive voix — d'où son alphabet sans I, O, 0 ni 1, que
  -- `Reves::codeInvitation()` pose — et se régénère quand on veut fermer
  -- la porte.
  --
  -- L'unicité doit être garantie par la base : le tirage recommence sur
  -- collision, et sans l'index il ne le saurait pas. `NULL` y est permis,
  -- et MySQL comme MariaDB traitent chaque `NULL` comme distinct — c'est
  -- ici le comportement voulu : un site sans code n'en bloque aucun autre.
  `code_invitation` VARCHAR(32) NULL,
  `role_invitation` ENUM('contributeur','lecteur') NOT NULL DEFAULT 'contributeur',

  -- LA TRACE D'UN IMPORT. Vide pour un projet ouvert ici ; pour un projet importé, un JSON :
  -- le nom qu'il portait, la date de son export, celle de son import, les versions du fichier.
  -- Jamais réexportée : c'est l'import qui l'écrit, pas le projet qui la porte.
  `origine`         TEXT NULL,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_site_code` (`code`),
  UNIQUE KEY `uq_site_invitation` (`code_invitation`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;


-- ---------------------------------------------------------------------
-- QUI TRAVAILLE SUR CE SITE
--
-- Le niveau de droit qui manquait : un projet syntropique est souvent
-- collectif — le cercle de rêve, le chantier, le suivi sur des années.
-- Sans lui, un utilisateur verrait les sites de tout le monde ou n'en
-- partagerait aucun ; les deux sont faux.
--
-- `compte_id` pointe vers la base `comptes`, SANS clé étrangère : ce sont
-- deux bases distinctes, et la jointure est applicative. C'est le prix de
-- la séparation, et il est modeste.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `membre_site` (
  `site_id`    INT UNSIGNED NOT NULL,
  `compte_id`  INT UNSIGNED NOT NULL,
  `role`       ENUM('porteur','contributeur','lecteur') NOT NULL DEFAULT 'lecteur',
  `depuis_le`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`site_id`, `compte_id`),
  KEY `ix_membre_compte` (`compte_id`),
  CONSTRAINT `fk_membre_site` FOREIGN KEY (`site_id`)
    REFERENCES `site` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- Un contributeur doit pouvoir écrire dans le rêve sans qu'on lui ouvre le
-- scénario ni le chiffrage : le rêve se fait à plusieurs, et avant le terrain.


-- ---------------------------------------------------------------------
-- QUI PARTICIPE AU PROJET — de 1 à n personnes
--
-- Distinct de `membre_site`, qui dit **qui a des droits sur l'application**.
-- Ici on nomme **qui participe au projet**, et les deux ne coïncident pas :
-- un cercle de rêve compte jusqu'à huit personnes, et leur ouvrir un compte
-- à chacune serait une barrière à l'entrée, non un service.
--
-- `compte_id` est donc NULLable : une personne peut exister dans le projet
-- sans compte, avec son seul nom. Si elle en a un, il la relie à ses propres
-- réponses.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `participant` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `site_id`    INT UNSIGNED NOT NULL,
  `nom`        VARCHAR(120) NOT NULL,
  `compte_id`  INT UNSIGNED NULL,          -- base `comptes`, jointure applicative
  `role_projet` VARCHAR(120) NULL,          -- ce qu'elle fait, en toutes lettres

  -- « Ne pas confondre engagement et sacrifice » [DD]. Donner du temps ou de
  -- l'argent n'est pas accepter de couvrir les pertes — et un projet agricole
  -- y est exposé dès la première commande de racines nues. L'engagement donne
  -- aussi un droit de regard sur les dépenses, à définir par écrit.
  `engagement` ENUM('participation','engagement') NOT NULL DEFAULT 'participation',

  `depuis_le`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  -- Le départ, sans effacement : un projet sur soixante ans verra passer des
  -- gens, et effacer leur trace effacerait une partie de ce qui explique le
  -- lieu. Leurs réponses restent — elles ont compté quand elles ont été écrites.
  `jusqu_au`   DATE NULL,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_participant` (`site_id`, `nom`),
  KEY `ix_participant_compte` (`compte_id`),
  CONSTRAINT `fk_participant_site` FOREIGN KEY (`site_id`)
    REFERENCES `site` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;


-- ---------------------------------------------------------------------
-- LES RESSOURCES — le mandala, ce qu'on a compté sans jugement
--
-- Le chapitre 3 du voyage (`conception/le-voyage.md`, 2026-09-07). Le
-- cadre portait le mandala des ressources [Alvéoles] dans le gabarit
-- `2-objectifs/README.md` sans qu'aucun écran ne le recueille.
--
-- Même géométrie que le mandala des bordures : `zone` dit si la ressource
-- est AU CENTRE — directement accessible, ou interne — ou EN PÉRIPHÉRIE —
-- elle dépend d'un tiers. `manque` marque ce qu'on n'a pas : à acquérir,
-- ou à rechercher — ou dont on se passera.
--
-- Les personnes ne sont pas ici : elles sont dans `participant`, et leur
-- temps est une réponse du socle du rêve (`mon_rythme`).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ressource` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `site_id`     INT UNSIGNED NOT NULL,
  `categorie`   ENUM('materielle','relationnelle','spirituelle',
                     'savoir_etre','savoir_faire','intellectuelle') NOT NULL,
  `zone`        ENUM('centre','peripherie') NOT NULL DEFAULT 'centre',
  `libelle`     VARCHAR(190) NOT NULL,
  `condition_acces` VARCHAR(190) NULL,    -- temps, distance, coût, accord d'un tiers
  `mobilisation`    VARCHAR(190) NULL,    -- comment la mobiliser
  `manque`      TINYINT(1) NOT NULL DEFAULT 0,
  `cree_le`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_ressource_site` (`site_id`),
  CONSTRAINT `fk_ressource_site` FOREIGN KEY (`site_id`)
    REFERENCES `site` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;


-- ---------------------------------------------------------------------
-- LES RÉPONSES — le patrimoine documentaire du projet
--
-- **Chaque personne répond séparément**, avant toute mise en commun : c'est
-- la règle du ch. 8 et du cercle de rêve. La clé porte donc le participant.
--
-- `question` est une clé de questionnaire, définie dans `Syntropia\
-- Questionnaire` avec son libellé et sa source. Les questions vivent dans le
-- code — elles sont du cadre ; les réponses vivent ici — elles sont du projet.
--
-- La table n'est pas propre au rêve : `phase` la rend utilisable pour toutes
-- les étapes du cycle, à mesure que leurs questionnaires seront écrits.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `reponse` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `site_id`        INT UNSIGNED NOT NULL,
  `participant_id` INT UNSIGNED NOT NULL,
  `phase`          VARCHAR(24)  NOT NULL,     -- definition, conception, realisation, suivi
  `question`       VARCHAR(64)  NOT NULL,
  `texte`          TEXT         NOT NULL,
  `ecrit_le`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modifie_le`     DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_reponse` (`site_id`, `participant_id`, `question`),
  KEY `ix_reponse_question` (`site_id`, `phase`, `question`),
  CONSTRAINT `fk_reponse_site` FOREIGN KEY (`site_id`)
    REFERENCES `site` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reponse_participant` FOREIGN KEY (`participant_id`)
    REFERENCES `participant` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- **On ne modifie que ses propres réponses**, et cela se garde dans le code :
-- « ne jamais dire non à un rêve » signifie aussi qu'on ne corrige pas celui
-- d'un autre. La règle vaut même pour le porteur du site.


-- ---------------------------------------------------------------------
-- LA SYNTHÈSE — ce qui devient commun
--
-- Elle **s'ajoute** aux réponses individuelles, elle ne les remplace pas :
-- les contradictions se gardent, elles signalent des besoins différents à
-- intégrer plus tard.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `synthese` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `site_id`    INT UNSIGNED NOT NULL,
  `phase`      VARCHAR(24) NOT NULL,
  `question`   VARCHAR(64) NOT NULL,
  `texte`      TEXT NOT NULL,
  `ecrit_le`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modifie_le` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_synthese` (`site_id`, `question`),
  CONSTRAINT `fk_synthese_site` FOREIGN KEY (`site_id`)
    REFERENCES `site` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;


-- ---------------------------------------------------------------------
-- LA PARCELLE — `parcelles/*/PARCELLE.md`
--
-- Une parcelle = une tortue = un cycle d'apprentissage complet. On ne les
-- ouvre pas toutes d'un coup : on observe, on capitalise, on ouvre la
-- suivante en corrigeant.
--
-- D'où deux colonnes qui ne sont pas du remplissage : `ce_qu_elle_teste` et
-- `temoin_emplacement`. Une parcelle qui ne nomme pas ce qu'elle teste et
-- n'a pas de témoin planté classiquement n'apprendra rien — c'est la seule
-- manière de mesurer le saut du chat.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `parcelle` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `site_id`     INT UNSIGNED NOT NULL,
  `code`        VARCHAR(32)  NOT NULL,          -- `A`, `B`, `P1`…
  `nom`         VARCHAR(160) NOT NULL,
  `type`        ENUM('perenne','potager') NOT NULL DEFAULT 'perenne',

  -- La conduite de CETTE parcelle. NULL = herite du site.
  --
  -- Une parcelle peut etre conduite autrement que son site : un jardin-foret
  -- au nord de la maison, des lignes syntropiques dans le champ. C'est meme
  -- l'interet du modele — le principe de l'elevage de tortues invite a
  -- essayer sur UNE parcelle avant d'engager le reste.
  `conduite`    ENUM('syntropique','jardin-foret') NULL,
  `surface_m2`  DECIMAL(10,2) NULL,
  `forme`       ENUM('zebre','abeille','liane','autre') NULL,
  `statut`      ENUM('conception','commande','plantee','suivi','close')
                NOT NULL DEFAULT 'conception',
  -- LA CAMPAGNE DE PLANTATION : `date_plantation` en est le DÉBUT — le point zéro —, `fin_plantation` la fin,
  -- facultative. Une plantation dense se fait dans une même période, qui s'étale sur trois saisons
  -- (fin d'automne, hiver, printemps) selon les besoins des végétaux : ce que les deux écoles ont en
  -- commun est un point zéro unique, aucune structurante ajoutée après coup, non une date unique
  -- (chantier-saut-du-chat.md, décision 6 ; schéma 4).
  `date_plantation` DATE NULL,
  `fin_plantation`  DATE NULL,

  -- La géométrie, telle que `outils/calcul-plants.py` l'attend.
  -- LA GEOMETRIE PART DE LA TAILLE DE LA PARCELLE, non du nombre de
  -- planches — decision du porteur, 2026-09-07. `nb_planches` reste, mais
  -- comme RESULTAT du calcul, pour que le dossier et la carte le lisent
  -- sans refaire la geometrie. Voir `conception/le-plan-de-parcelle.md`.
  `nb_planches`      TINYINT UNSIGNED NULL,
  `longueur_m`       DECIMAL(6,2) NULL,
  `largeur_m`        DECIMAL(6,2) NULL,
  `largeur_planche_m` DECIMAL(4,2) NULL,
  `passe_pied_m`     DECIMAL(4,2) NULL,
  -- Les bordures — huit cotes activables et leurs largeurs —, le
  -- passe-pied de rive, le rattrapage du reste, et les lignes de
  -- plantation planche par planche. Du JSON dans un TEXT : ces reglages
  -- n'ont de sens qu'ensemble et ne se requetent jamais un par un.
  `plan`             TEXT NULL,

  -- LE SOL ET L'EXPOSITION SONT DE LA PARCELLE, non du site.
  --
  -- Deux parcelles d'un même lieu peuvent différer complètement : un
  -- bas-fond argileux et un haut de coteau sableux sont deux mondes. Le
  -- climat, lui, reste au site — une station météo ne les distingue pas.
  --
  -- Tout est NULL par défaut, et NULL signifie **hérite du site**. On ne
  -- descend à ce niveau que là où la parcelle diffère vraiment.
  -- Voir referentiels/site-et-parcelle.md
  `ph`              DECIMAL(3,1) NULL,
  `texture`         VARCHAR(60)  NULL,
  `drainage`        VARCHAR(60)  NULL,
  `hydromorphie`    VARCHAR(60)  NULL,
  `hydromorphie_cm` SMALLINT UNSIGNED NULL,

  `masque_observe`      VARCHAR(120) NULL,
  `obstacle_hauteur_m`  DECIMAL(5,2) NULL,
  `obstacle_distance_m` DECIMAL(6,2) NULL,

  -- La pente et l'orientation du versant manquaient aux deux niveaux. Elles
  -- sont de la parcelle : elles changent l'exposition, l'érosion, le sens du
  -- travail — et peuvent commander l'orientation des lignes contre le soleil.
  -- La distance de la bordure extérieure à la première ligne de production.
  -- Elle décide de la hauteur maximale admissible pour cette bordure : son
  -- ombre ne doit pas atteindre la ligne pendant que celle-ci en a besoin.
  -- Voir referentiels/geometrie-solaire.md § 5.3bis
  `distance_bordure_m` DECIMAL(4,2) NULL,

  `pente_pct`       DECIMAL(4,1) NULL,
  `exposition`      ENUM('plat','nord','nord-est','est','sud-est',
                         'sud','sud-ouest','ouest','nord-ouest') NULL,

  `production_principale` VARCHAR(190) NULL,
  `production_secondaire` VARCHAR(190) NULL,
  -- Au site par défaut. À la parcelle quand la pente commande : sur un
  -- versant marqué, on suit les courbes de niveau plutôt que le soleil.
  `orientation_lignes` VARCHAR(60) NULL,
  `espacement_lignes_m` DECIMAL(4,2) NULL,

  `ce_qu_elle_teste`  TEXT NULL,
  `temoin_emplacement` VARCHAR(190) NULL,

  `cree_le`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `notes`       TEXT NULL,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_parcelle_code` (`site_id`, `code`),
  CONSTRAINT `fk_parcelle_site` FOREIGN KEY (`site_id`)
    REFERENCES `site` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;


-- ---------------------------------------------------------------------
-- LA COUCHE SYNTROPIQUE DES ESPÈCES — `referentiels/especes.csv`
--
-- **La base dit ce qu'est la plante. Ce dépôt dit ce qu'on lui fait faire.**
-- Aucune donnée botanique ici : hauteur, houppier, pH toléré, rusticité et
-- multiplication se lisent chez Plantae, par `nom_scientifique`.
--
-- La jointure est APPLICATIVE — deux bases, deux collations. Le cas d'école
-- du partage est la strate : Plantae porte `strate_jardin_foret`, mais avec
-- des valeurs morphologiques. La strate syntropique est un BESOIN EN
-- LUMIÈRE, elle change avec le lieu et avec le résultat attendu, et les
-- deux ne se déduisent pas l'une de l'autre.
--
-- `site_id` = 0 : valeur du cadre, partagée. Renseigné : surcharge locale,
-- et elle prime — c'est la règle des trois couches, portée en table.
--
-- POURQUOI 0 ET NON NULL, et pourquoi pas de clé étrangère : un index
-- UNIQUE traite chaque NULL comme distinct. Avec `site_id` NULL, deux
-- lignes de cadre pour la même espèce passeraient sans bruit, et
-- l'interface en afficherait une au hasard. Un doublon silencieux qui donne
-- deux valeurs contradictoires est plus nuisible qu'une ligne orpheline
-- après la suppression d'un site — laquelle se nettoie applicativement.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `espece` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `site_id`          INT UNSIGNED NOT NULL DEFAULT 0,
  `nom_scientifique` VARCHAR(160) NOT NULL,     -- la clé vers Plantae
  -- UNE VARIÉTÉ N'EST PAS UNE AUTRE ESPÈCE, mais elle peut changer la
  -- strate ou le rôle du tout au tout — remarque du porteur, 2026-09-18 :
  -- le fraisier des bois et le fraisier du commerce n'occupent pas la
  -- même strate ; un tournesol conduit à une fleur (peu de branches,
  -- surtout du feuillage) se préfère pour la biomasse, un tournesol
  -- classique et ramifié pour la graine. Même `nom_scientifique`,
  -- lignes distinctes dans ce référentiel.
  --
  -- NOT NULL DEFAULT '' et non NULL : même raison que `site_id = 0`
  -- plus bas — un index UNIQUE traite chaque NULL comme distinct, deux
  -- lignes « variété non précisée » pour la même espèce passeraient
  -- sans bruit. '' est le cas courant, l'espèce sans variété notée.
  `variete`          VARCHAR(120) NOT NULL DEFAULT '',
  `nom_usuel`        VARCHAR(160) NULL,

  -- Multivalué par `|` — une consoude est bordure ET biomasse. Un ENUM
  -- l'interdirait, et le rôle est justement ce qui se cumule.
  `role`             VARCHAR(120) NULL,
  -- LES STRATES POSSIBLES, multivaluées par « | » comme le rôle.
  --
  -- Une espèce n'a pas UNE strate : elle en a une par rôle. Le chalef en
  -- émergente fructifie, en haute il ne fait que de la biomasse. Le wiki
  -- syntropie donne trois strates à l'artichaut. Le référentiel liste donc
  -- les possibles ; c'est `scenario_ligne.strate` qui tranche, et lui reste
  -- une valeur unique.
  --
  -- D'où un VARCHAR et non un ENUM : un ENUM interdirait la combinaison.
  `strates_possibles` VARCHAR(120) NULL,
  `systeme`          ENUM('colonisation','accumulation','abondance') NULL,
  `entree_scenario`  VARCHAR(24) NULL,          -- placenta1…climax
  `sortie_scenario`  VARCHAR(24) NULL,

  -- L'apport propre du corpus : l'espacement en monoculture est le point de
  -- départ à partir duquel on densifie. Il est ABSENT de Plantae, et sans
  -- lui il n'y a pas de calcul de plants.
  `espacement_monoculture_m` DECIMAL(5,2) NULL,
  `espacement_retenu_m`      DECIMAL(5,2) NULL,
  `plants_par_ml`            DECIMAL(6,2) NULL,

  `biomasse`         VARCHAR(8) NULL,           -- + / ++ / +++
  `rapport_cn`       VARCHAR(24) NULL,
  `conduite_taille`  VARCHAR(120) NULL,
  `periode_taille`   VARCHAR(120) NULL,
  `type_plant`       VARCHAR(60) NULL,
  `origine`          ENUM('achat','multiplication','rna','mixte') NULL,
  `serre_necessaire` TINYINT(1) NULL,
  `bloque_herbe`     TINYINT(1) NULL,
  `orientation_bordure` VARCHAR(60) NULL,
  `deplacable`       TINYINT(1) NULL,

  -- D'où vient la valeur, et ce qu'elle vaut. Ces deux colonnes ne sont pas
  -- de la documentation : l'interface les affiche, et une valeur sans
  -- source ne peut pas être présentée comme acquise.
  `source`           VARCHAR(120) NULL,
  `statut_local`     VARCHAR(60) NULL,
  `notes`            TEXT NULL,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_espece` (`site_id`, `nom_scientifique`, `variete`),
  KEY `ix_espece_nom` (`nom_scientifique`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;


-- ---------------------------------------------------------------------
-- LA CARTE À CALQUES — ce qu'on dessine sur le terrain
--
-- Le chantier n° 11 (`conception/le-voyage.md` § 6, 2026-09-07). Le plan
-- des secteurs [Alvéoles] et la superposition de cartes [Curci] : un calque
-- par thème, superposés pour croiser. Ici chaque élément dessiné est une
-- ligne — un point, une ligne, un polygone —, avec son calque.
--
-- `geometrie` est du JSON en TEXT, non un type JSON : la production est
-- MariaDB 10.6, et le dépôt n'emploie rien que les deux moteurs ne
-- partagent. Une liste de couples [latitude, longitude], cinq décimales.
--
-- `parcelle_id` relie un polygone du calque « parcelles » à sa parcelle :
-- c'est ainsi qu'une parcelle se PLACE sur le terrain. `ON DELETE SET
-- NULL` : le tracé survit à la parcelle, il redevient un simple contour.
--
-- Le calque « relief » porte un élément de type `grille` : la grille
-- d'altitudes demandée à l'IGN, pour la vue en trois dimensions.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `calque_element` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `site_id`     INT UNSIGNED NOT NULL,
  `parcelle_id` INT UNSIGNED NULL,
  `calque`      ENUM('fond','relief','ombres','vents','eau','sol',
                     'circulations','vegetation','ressenti','parcelles') NOT NULL,
  `type`        ENUM('point','ligne','polygone','grille') NOT NULL,
  `libelle`     VARCHAR(160) NULL,
  `geometrie`   TEXT NOT NULL,                 -- JSON : [[lat, lon], ...]
  `proprietes`  TEXT NULL,                     -- JSON : hauteur_m, note, altitudes...
  `cree_le`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modifie_le`  DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_calque_site` (`site_id`, `calque`),
  KEY `ix_calque_parcelle` (`parcelle_id`),
  CONSTRAINT `fk_calque_site` FOREIGN KEY (`site_id`)
    REFERENCES `site` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_calque_parcelle` FOREIGN KEY (`parcelle_id`)
    REFERENCES `parcelle` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;


-- ---------------------------------------------------------------------
-- LE SCÉNARIO — `parcelles/*/conception/scenario.csv`
--
-- Le tableau temps × strates du ch. 11. `ordre` n'est pas un simple rang
-- d'affichage : il porte l'ordre de remplissage, qui est prescrit —
-- production principale, secondaire, biomasse, PLACENTAIRES EN DERNIER.
-- Remplir dans un autre ordre produit un scénario où la production s'ajuste
-- à la biomasse au lieu de l'inverse.
--
-- `source_valeur` dit d'où vient l'espacement retenu. Un espacement sans
-- source est un espacement inventé, et le calcul de plants le refuse.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `scenario_ligne` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parcelle_id`  INT UNSIGNED NOT NULL,
  `ordre`        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  -- LA STRATE, DANS LES DEUX VOCABULAIRES.
  --
  -- Un scenario appartient a une parcelle, et la parcelle a une CONDUITE.
  -- En syntropie : quatre strates, par besoin en lumiere. En jardin-foret :
  -- neuf, par place et hauteur adulte. LES DEUX NE SE CONVERTISSENT PAS —
  -- deux plantes de meme hauteur peuvent occuper deux strates syntropiques
  -- differentes. L'enumeration porte donc les treize valeurs cote a cote.
  --
  -- La base ne peut pas savoir laquelle est legitime ici : cela depend de
  -- `parcelle.conduite`, qu'elle ne voit pas d'ici. C'est `Conduite::strates()`
  -- qui restreint au bon vocabulaire, et l'ENUM qui interdit le reste.
  --
  -- `rampante` a disparu le 2026-08-29 : elle citait le ch. 5, QUI N'EN DONNE
  -- QUE QUATRE. Ce qui rampe se dit par le role — couvre-sol —, non par la
  -- strate. La correction etait faite dans especes-colonnes.md le matin meme
  -- et n'avait jamais atteint le schema.
  `strate`       ENUM('emergente','haute','moyenne','basse',
                      '1_canopee','2_arbre','3_buisson','4_herbacee',
                      '5_couvre_sol','6_racine','7_liane','8_aquatique',
                      '9_epiphyte') NULL,
  `espece`       VARCHAR(160) NOT NULL,
  `role`         VARCHAR(120) NULL,
  `couleur_plan` ENUM('rouge','orange','vert','bleu','autre') NULL,
  `type_plant`   VARCHAR(60) NULL,
  `origine`      ENUM('achat','multiplication','rna','mixte') NULL,
  `entree`       VARCHAR(24) NULL,
  `sortie`       VARCHAR(24) NULL,
  `espacement_m` DECIMAL(5,2) NULL,
  `nb_pieds`     SMALLINT UNSIGNED NULL,
  -- `conduite_taille`, non `conduite` : depuis que `parcelle.conduite` designe
  -- la DISCIPLINE — syntropie ou jardin-foret —, une colonne `conduite` qui
  -- veut dire « comment on taille ce pied » est un piege. Le nom est celui
  -- qu'`espece` porte deja.
  `conduite_taille` VARCHAR(120) NULL,
  `source_valeur` VARCHAR(120) NULL,
  `notes`        TEXT NULL,
  -- Fonctions cumulables, production choisie et repères fins du tableau.
  `details`      TEXT NULL,

  PRIMARY KEY (`id`),
  KEY `ix_scenario_parcelle` (`parcelle_id`, `ordre`),
  CONSTRAINT `fk_scenario_parcelle` FOREIGN KEY (`parcelle_id`)
    REFERENCES `parcelle` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;


-- ---------------------------------------------------------------------
-- LE CONSTAT DU SAUT DU CHAT — schéma 3, 2026-09-20 (chantier-saut-du-chat.md, Phase 2)
--
-- Le saut du chat est l'EFFET de la perturbation : il se reconnaît, il ne se décrète pas, et il
-- se MESURE PAR COMPARAISON avec un témoin conduit classiquement dans le même sol (Théry,
-- entretien « 11 questions sur la syntropie »). Un constat est donc daté, dit s'il est visible,
-- et — quand on l'a mesuré — donne la pousse d'une plante sur une durée, à côté de celle du
-- témoin. Sans durée, une pousse ne dit rien ; sans pousse, un témoin ne compare rien : la
-- validation (`Web/Constats`) l'exige. Les ordres de grandeur du corpus (maïs jusqu'à 30 cm en
-- 24 h…) sont des repères dits à l'écran, jamais des seuils.
--
-- La perturbation qui précède n'est PAS une clé étrangère : elle se lit dans le journal
-- (`intervention`) par sa date, ce qui garde l'export sans référence entre deux tables filles.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `constat` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parcelle_id`      INT UNSIGNED NOT NULL,
  `date`             DATE NOT NULL,
  `visible`          ENUM('oui','pas_encore') NOT NULL,
  `vegetal`          VARCHAR(160) NULL,
  `pousse_cm`        DECIMAL(7,1) NULL,
  `sur_jours`        SMALLINT UNSIGNED NULL,
  `temoin_decrit`    VARCHAR(190) NULL,
  `temoin_pousse_cm` DECIMAL(7,1) NULL,
  `notes`            TEXT NULL,
  `saisi_par`        INT UNSIGNED NULL,            -- compte, base `comptes`

  PRIMARY KEY (`id`),
  KEY `ix_constat_parcelle` (`parcelle_id`, `date`),
  CONSTRAINT `fk_constat_parcelle` FOREIGN KEY (`parcelle_id`)
    REFERENCES `parcelle` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;


-- ---------------------------------------------------------------------
-- LA SUCCESSION DU POTAGER — `P-modele-potager/succession.csv`
--
-- Une saison, deux demi-planches en miroir, deux successions du ch. 8. Ce
-- n'est pas un scénario en réduction : le potager recrée sa biomasse chaque
-- année par des annuelles, là où la tortue pérenne la produit par ses
-- ligneux, de façon croissante.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `succession_ligne` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parcelle_id`   INT UNSIGNED NOT NULL,
  `demi_planche`  VARCHAR(24) NULL,
  `code`          VARCHAR(16) NULL,             -- 7.1 … 7.6, ch. 8
  `espece`        VARCHAR(160) NOT NULL,
  `role`          VARCHAR(120) NULL,
  `couleur_plan`  ENUM('rouge','orange','vert') NULL,
  -- Quatre, et seulement quatre : une succession de potager est syntropique
  -- par construction — c'est le ch. 8. `rampante` otee le 2026-08-29, le
  -- ch. 5 n'en donnant que quatre.
  `strate`        ENUM('emergente','haute','moyenne','basse') NULL,
  `emplacement`   VARCHAR(120) NULL,
  `espacement_m`  DECIMAL(5,2) NULL,
  `date_semis_repiquage` DATE NULL,
  `date_perturbation`    DATE NULL,
  `date_recolte`         DATE NULL,
  `source`        VARCHAR(120) NULL,
  `notes`         TEXT NULL,

  PRIMARY KEY (`id`),
  KEY `ix_succession_parcelle` (`parcelle_id`),
  CONSTRAINT `fk_succession_parcelle` FOREIGN KEY (`parcelle_id`)
    REFERENCES `parcelle` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;


-- ---------------------------------------------------------------------
-- LES INTERVENTIONS — `parcelles/*/suivi/interventions.csv`
--
-- **C'est ici que se comblera le manque le plus lourd du dépôt.** Le temps
-- de travail par geste et par outil n'existe nulle part : ni Théry, ni
-- Faraj, ni personne ne l'a mesuré. `duree_h` et `personnes` sont les deux
-- colonnes qui y répondront, et elles ne valent que si l'outil employé est
-- noté — c'est la variable qui change tout, d'un ordre de grandeur.
--
-- `stade_phenologique`, `conditions_eau` et `pluies_a_venir` ne sont pas du
-- confort : deux acquis en dépendent. On ne taille jamais sans eau
-- disponible dans le sol, et on perturbe avant le déclin qui suit la
-- floraison. Une durée notée sans eux ne dit rien.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `intervention` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parcelle_id`   INT UNSIGNED NOT NULL,
  `date`          DATE NOT NULL,
  `campagne`      VARCHAR(24) NULL,
  `type`          VARCHAR(60) NOT NULL,
  `zone`          VARCHAR(120) NULL,
  `especes`       VARCHAR(255) NULL,
  `stade_phenologique` VARCHAR(120) NULL,
  `conditions_eau` VARCHAR(120) NULL,
  `pluies_a_venir` VARCHAR(120) NULL,
  `outil`         VARCHAR(120) NULL,
  `premiere_fois` TINYINT(1) NOT NULL DEFAULT 0,
  `duree_h`       DECIMAL(5,2) NULL,
  `personnes`     TINYINT UNSIGNED NULL,
  `quantite_traitee` DECIMAL(8,2) NULL,
  `unite_quantite` ENUM('m','m2') NULL,
  `biomasse_deposee` VARCHAR(120) NULL,
  `motif`         TEXT NULL,
  `resultat_constate` TEXT NULL,
  `notes`         TEXT NULL,
  `saisi_par`     INT UNSIGNED NULL,            -- compte, base `comptes`

  PRIMARY KEY (`id`),
  KEY `ix_intervention_parcelle` (`parcelle_id`, `date`),
  CONSTRAINT `fk_intervention_parcelle` FOREIGN KEY (`parcelle_id`)
    REFERENCES `parcelle` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- Quatre colonnes s'ajoutent aux CSV : `outil`, `premiere_fois`,
-- `quantite_traitee` et `unite_quantite`. Elles viennent du protocole de
-- mesure : sans quantité on ne rapporte à rien, et une cadence établie sur
-- une première fois est fausse — le premier passage d'un geste nouveau
-- prend deux à trois fois plus longtemps que le dixième.


-- ---------------------------------------------------------------------
-- L'ÉTAT DU SOL — `parcelles/*/suivi/etat-du-sol.csv`
--
-- Le corpus syntropique pose « un sol plus fertile après récolte qu'avant
-- plantation » SANS DONNER AUCUN MOYEN DE LE VÉRIFIER. Ces colonnes le
-- donnent, et elles viennent d'ailleurs — du diagnostic régénératif et des
-- protocoles de terrain.
--
-- `temoin` est ce qui rend la mesure interprétable : sans zone conduite
-- classiquement à côté, on ne saura pas ce qui revient au système et ce qui
-- revient à l'année.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `releve_sol` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parcelle_id`   INT UNSIGNED NOT NULL,
  `date`          DATE NOT NULL,
  `campagne`      VARCHAR(24) NULL,             -- point zéro, N+1…
  `point`         VARCHAR(60) NOT NULL,
  `temoin`        TINYINT(1) NOT NULL DEFAULT 0,

  `profondeur_cm`     TINYINT UNSIGNED NULL,
  `etat_humidite`     VARCHAR(60) NULL,
  `jours_depuis_pluie` SMALLINT UNSIGNED NULL,
  `densite_apparente` DECIMAL(4,2) NULL,
  `infiltration_mm_h` DECIMAL(7,2) NULL,
  `slake_verdict`     VARCHAR(60) NULL,
  `structure_dominante` VARCHAR(60) NULL,
  `semelle_profondeur_cm` TINYINT UNSIGNED NULL,
  `racines_arret_cm`  TINYINT UNSIGNED NULL,
  `porosite`          VARCHAR(60) NULL,
  `hydromorphie_cm`   TINYINT UNSIGNED NULL,
  `odeur`             VARCHAR(120) NULL,
  `vers_par_bloc`     SMALLINT UNSIGNED NULL,
  `volume_bloc_cm`    SMALLINT UNSIGNED NULL,
  `dont_aneciques`    SMALLINT UNSIGNED NULL,
  `turricules_50x50`  SMALLINT UNSIGNED NULL,
  `photo`             VARCHAR(190) NULL,
  `notes`             TEXT NULL,
  `saisi_par`         INT UNSIGNED NULL,

  PRIMARY KEY (`id`),
  KEY `ix_releve_parcelle` (`parcelle_id`, `date`),
  CONSTRAINT `fk_releve_parcelle` FOREIGN KEY (`parcelle_id`)
    REFERENCES `parcelle` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- `jours_depuis_pluie` conditionne l'interprétation de l'infiltration et de
-- la densité apparente : le même sol ne donne pas la même mesure sec ou
-- ressuyé. Un relevé sans cette colonne n'est pas comparable au suivant.


-- ---------------------------------------------------------------------
-- LES GISEMENTS — `3-pepiniere/gisements.csv`
--
-- Le pied-mère n'est pas forcément chez soi : un voisin qui a trop de
-- consoude, un bord de bois, un rond-point communal taillé chaque année.
--
-- Trois temps, et ILS NE TOMBENT PAS ENSEMBLE. Repérer quand la plante est
-- identifiable — printemps-été. Demander l'autorisation dans la foulée.
-- Prélever quand elle dort — mi-novembre à mi-février. D'où `marquage` :
-- un sujet repéré en juillet doit être retrouvable en janvier.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `gisement` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `site_id`       INT UNSIGNED NOT NULL,
  `lieu`          VARCHAR(190) NOT NULL,
  `type`          ENUM('prive_ami','prive_voisin','nature','paysager_public',
                       'friche','bord_de_route','pepiniere') NULL,
  `proprietaire`  VARCHAR(160) NULL,
  `espece`        VARCHAR(160) NOT NULL,
  `nb_sujets`     SMALLINT UNSIGNED NULL,
  `prelevement`   ENUM('bouture','bouture_racine','plancon','graine','marcotte',
                       'eclat_souche','drageon','selection_sur_place') NULL,
  `autorisation`  ENUM('non_requise','a_demander','accordee','refusee')
                  NOT NULL DEFAULT 'a_demander',
  `date_demande`  DATE NULL,
  `reponse`       VARCHAR(190) NULL,
  `fenetre_reperage`    VARCHAR(120) NULL,
  `fenetre_prelevement` VARCHAR(120) NULL,
  `marquage`      VARCHAR(190) NULL,
  `acces`         VARCHAR(190) NULL,
  `quantite_prelevee` SMALLINT UNSIGNED NULL,
  `annee`         SMALLINT UNSIGNED NULL,
  `notes`         TEXT NULL,

  PRIMARY KEY (`id`),
  KEY `ix_gisement_site` (`site_id`),
  CONSTRAINT `fk_gisement_site` FOREIGN KEY (`site_id`)
    REFERENCES `site` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;


-- ---------------------------------------------------------------------
-- LES COMMANDES — `3-pepiniere/commandes.csv`
--
-- `date_precommande` porte la seule échéance du calendrier qui NE SE DÉCALE
-- PAS : l'ouverture des précommandes de racines nues, en septembre-octobre.
-- Elle relève du marché, pas du climat, et le stock est épuisé en trois
-- jours chez certains.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `commande` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `site_id`       INT UNSIGNED NOT NULL,
  `espece`        VARCHAR(160) NOT NULL,
  `variete`       VARCHAR(160) NULL,
  `porte_greffe`  VARCHAR(160) NULL,
  `quantite`      SMALLINT UNSIGNED NULL,
  `fournisseur`   VARCHAR(160) NULL,
  `date_precommande` DATE NULL,
  `date_livraison`   DATE NULL,
  `prix_unitaire` DECIMAL(8,2) NULL,
  `total`         DECIMAL(10,2) NULL,
  `statut`        ENUM('a_commander','precommandee','confirmee','livree','annulee')
                  NOT NULL DEFAULT 'a_commander',
  `notes`         TEXT NULL,

  PRIMARY KEY (`id`),
  KEY `ix_commande_site` (`site_id`, `date_precommande`),
  CONSTRAINT `fk_commande_site` FOREIGN KEY (`site_id`)
    REFERENCES `site` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;


-- ---------------------------------------------------------------------
-- LA CÉLÉBRATION — `parcelles/*/suivi/fin-de-cycle.md`
--
-- Le quatrième temps du cycle `[DD]`. **Le mot n'existe pas dans le corpus
-- syntropique** : c'est un apport de Dragon Dreaming, et la méthode lui
-- demande environ un quart de l'énergie du projet.
--
-- CE QUI A DICTÉ CETTE TABLE : célébrer est un MOMENT, non un état. Cinq
-- des six jalons tombent pendant que la parcelle est au statut `suivi`.
-- Une parcelle a donc plusieurs célébrations, et la clé unique porte sur
-- le couple (parcelle, jalon) — on ne célèbre pas deux fois le même.
--
-- La colonne `sortie_espece` existe pour le seul jalon qui se répète
-- vraiment : une espèce sort, puis une autre. Sans elle, la clé unique
-- interdirait la seconde.
--
-- LE CONTENU vient de deux sources. Ce qu'on célèbre et ce qu'on reconnaît
-- viennent de Dragon Dreaming. Les deux boucles — `mieux_faire` et
-- `pourquoi_ainsi` — viennent de la Roue de la Création `[Gigand]`, points
-- 5 Observation et 6 Évaluation, qui donnent à la célébration le contenu
-- que Dragon Dreaming ne dit pas.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `celebration` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parcelle_id` INT UNSIGNED NOT NULL,
  `jalon`       ENUM('chantier','reprise','saut-du-chat','recolte','sortie','fin-placenta','conception')
                NOT NULL,
  `sortie_espece` VARCHAR(160) NOT NULL DEFAULT '',
  `date`        DATE NOT NULL,

  -- Ce qu'on célèbre.
  `presents`    TEXT NULL,                 -- qui était là, en toutes lettres
  `fait_ensemble` TEXT NULL,
  `plus_dur`    TEXT NULL,
  `plus_facile` TEXT NULL,

  -- Observer, avant d'évaluer `[Gigand]` — point 5.
  -- Le fait, distingué de l'interprétation : « trois plants sur dix n'ont
  -- pas repris » est une observation, « le sol ne convient pas » n'en est
  -- pas une.
  `faits`       TEXT NULL,

  -- Évaluer `[Gigand]` — point 6, et le double apprentissage.
  `au_regard_du_reve` TEXT NULL,           -- le résultat comparé au rêve initial
  `mieux_faire`  TEXT NULL,                -- première boucle
  `pourquoi_ainsi` TEXT NULL,              -- seconde boucle
  `refaire`     TEXT NULL,
  `ne_plus_refaire` TEXT NULL,
  `competence_a_acquerir` TEXT NULL,

  -- La relance. C'est ici que le cycle repart, ou qu'il s'arrête — règle du
  -- ch. 15 : on n'ouvre la suivante qu'après avoir capitalisé sur celle-ci.
  `ouvre_suivante` ENUM('oui','pas_encore','non') NULL,
  `qu_attend_on` TEXT NULL,
  `ce_que_testera_la_suivante` TEXT NULL,

  -- Ce qui remonte. Coché quand le constat a été porté dans le cadre —
  -- `referentiels/retours-experience.md`, et lui seul reçoit du `[terrain]`.
  `remonte_le`  DATE NULL,
  `remonte_quoi` TEXT NULL,

  `ecrit_le`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modifie_le`  DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  `saisi_par`   INT UNSIGNED NULL,         -- compte, base `comptes`

  PRIMARY KEY (`id`),
  -- Un jalon ne se célèbre qu'une fois — sauf la sortie d'une espèce, que
  -- `sortie_espece` distingue. La chaîne vide, non NULL : en SQL, deux NULL
  -- sont distincts, et la contrainte laisserait passer les doublons.
  UNIQUE KEY `uq_celebration` (`parcelle_id`, `jalon`, `sortie_espece`),
  KEY `ix_celebration_date` (`parcelle_id`, `date`),
  CONSTRAINT `fk_celebration_parcelle` FOREIGN KEY (`parcelle_id`)
    REFERENCES `parcelle` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;


-- ---------------------------------------------------------------------
-- CE QUE CHAQUE PERSONNE A PORTÉ — la reconnaissance
--
-- `fin-de-cycle.md` le dit : nommer le travail fourni personne par
-- personne est **le point que la remontée de constats ne fait jamais**.
-- Un fichier qui le demande reste vide ; une page qui liste les
-- participants déjà inscrits et pose la question par personne se remplit.
--
-- Table séparée, et non colonnes de `celebration` : le nombre de
-- personnes va de 1 à n.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `reconnaissance` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `celebration_id` INT UNSIGNED NOT NULL,
  `participant_id` INT UNSIGNED NOT NULL,
  `a_porte`        TEXT NULL,
  `a_appris`       TEXT NULL,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_reconnaissance` (`celebration_id`, `participant_id`),
  CONSTRAINT `fk_reconnaissance_celebration` FOREIGN KEY (`celebration_id`)
    REFERENCES `celebration` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reconnaissance_participant` FOREIGN KEY (`participant_id`)
    REFERENCES `participant` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;


-- Rattrapage d'une table `site` créée avant `origine` — schéma 2. Même forme que
-- `invitation.role_site` (00-comptes.sql) : `information_schema` + `PREPARE`, parce que
-- `ADD COLUMN IF NOT EXISTS` n'existe que sur MariaDB et que ce fichier tourne aussi sur MySQL.
SET @origine_existe = (SELECT COUNT(*) FROM information_schema.COLUMNS
                        WHERE TABLE_SCHEMA = DATABASE()
                          AND TABLE_NAME = 'site'
                          AND COLUMN_NAME = 'origine');
SET @ajout_origine = IF(@origine_existe = 0,
                        'ALTER TABLE `site` ADD COLUMN `origine` TEXT NULL',
                        'DO 0');
PREPARE ajout_origine FROM @ajout_origine;
EXECUTE ajout_origine;
DEALLOCATE PREPARE ajout_origine;


-- Rattrapage d'une table `parcelle` créée avant `fin_plantation` — schéma 4. Même forme que ci-dessus.
SET @fin_existe = (SELECT COUNT(*) FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = 'parcelle'
                      AND COLUMN_NAME = 'fin_plantation');
SET @ajout_fin = IF(@fin_existe = 0,
                    'ALTER TABLE `parcelle` ADD COLUMN `fin_plantation` DATE NULL AFTER `date_plantation`',
                    'DO 0');
PREPARE ajout_fin FROM @ajout_fin;
EXECUTE ajout_fin;
DEALLOCATE PREPARE ajout_fin;


-- ---------------------------------------------------------------------
-- LA VERSION DU SCHÉMA — de quelle forme sont les données de CETTE base
--
-- Une ligne par clé. `schema` égale `Syntropia\Version::SCHEMA` : à monter dans le
-- même commit que tout changement de schéma, ici et dans `src/Version.php`. C'est ce
-- qui décide si un fichier d'export peut être rechargé (voir `Syntropia\Version`).
--
-- Cette ligne est du DML rejoué à chaque `bin/dev.py init` et `bin/deployer.py base` :
-- la base dit donc la version du schéma **qu'elle a réellement reçu**, pas celle que le
-- code croit déployée — et une base rejouée en retard sur le code se voit.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `atelier_version` (
  `cle`    VARCHAR(32) NOT NULL,
  `valeur` VARCHAR(64) NOT NULL,
  PRIMARY KEY (`cle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- Historique : 1 = premier versionnement ; 2 = `site.origine` (l'import d'un projet) ;
-- 3 = la table `constat` (le constat du saut du chat — table facultative, la fenêtre d'import ne se referme pas) ;
-- 4 = `parcelle.fin_plantation` (la fin de la campagne de plantation — colonne facultative, idem).
INSERT INTO `atelier_version` (`cle`, `valeur`) VALUES ('schema', '4')
  ON DUPLICATE KEY UPDATE `valeur` = VALUES(`valeur`);
