# Chantiers — état détaillé

*Déplacé tel quel de `CLAUDE.md` § Où sont les choses le 2026-10-03. Le résumé d'une ligne par chantier est dans `CLAUDE.md` ; le plan de chacun dans son `chantier-*.md`.*

- `chantier-carte-interactive.md` — le plan détaillé (10 phases) de la
  carte interactive du terrain, terminé le 2026-09-16 — voir § Point
  de reprise.
- `chantier-scenario-plantation.md` — le plan détaillé (4 phases) du
  scénario de plantation et de la base de végétaux, proposé le
  2026-09-18, pas encore commencé — voir § Point de reprise.
- `chantier-plan-de-parcelle.md` — le plan détaillé (4 phases) de la
  persistance du plan de parcelle (bordures externe et interne, sens des
  planches, dimensions, rattrapage), proposé le 2026-09-18. **Les quatre
  phases sont faites, chantier terminé le 2026-09-18** — réplique
  partielle et assumée de la référence, voir § Point de reprise.
- `chantier-inscription-parrainage.md` — le plan détaillé (4 phases) de
  l'inscription par code de parrainage : inviter quelqu'un à créer un
  compte, ou à rejoindre un projet (et un compte peut alors avoir
  plusieurs projets). **Les quatre phases sont faites (2026-09-18)** — voir § Point
  de reprise.
- `chantier-export-import-projet.md` — le plan (4 phases) pour exporter et
  importer un projet en un fichier JSON daté, demandé le 2026-09-18 ; format JSON
  (plutôt que XML) ; toutes les spécifications sont tranchées. **Phases 1 (exporter), 2
  (importer) et 3 (aller-retour, script `bin/verifier-export-import.php`) faites le 2026-09-20** ;
  Phase 4 (GeoJSON, facultative) pas commencée — voir § Point de reprise.
- `chantier-mises-a-jour.md` — le plan (7 phases) pour mettre l'atelier à jour sans gêner ceux qui l'utilisent :
  sauvegarde avant `base`, mode maintenance, ordre des envois, pages restées ouvertes, déploiement atomique, site
  d'essai, « Quoi de neuf ». Demandé le 2026-10-01 (bêta-testeurs en production) ; **Phases 1 à 4 faites, déployées et essayées en production le
  jour même** (le jeton des formulaires porte la marque du déploiement : une page d'avant une mise à jour n'écrit plus ;
  les personnes connectées se comptent par des traces d'activité, `contenu/actifs/`) ; phases 5 à 7 avant la version 1 (cycle maintenance, puis un `mettre-a-jour` complet) ; on garde les **cinq** dernières sauvegardes.
- `chantier-palette-vegetale.md` — le plan (5 phases) d'une étape « La palette » avant le plan de la parcelle :
  productions principales, secondaires, plantes de service par fonction, et l'origine de chaque plante dans le projet.
  Demandé le 2026-10-05 ; tout est tranché le jour même — ce que le site offre, les
  origines par projet choisies par la personne, la palette par parcelle, et la palette du projet qui les regroupe.
  **Phase 1 faite et déployée le 2026-10-07** (schéma 21) ; **Phases 4 et 6 faites le même jour, en local, pas
  déployées** (le plan, le scénario et le potager puisent dans la palette ; les palettes existantes se remplissent) : la page « La palette », où la parcelle naît.
- `chantier-releves-de-sol.md` — le plan (4 phases) pour saisir les relevés de sol de chaque parcelle et les
  comparer d'une campagne à l'autre, témoin compris (la table `releve_sol` existe, seule l'infiltration y entre, par
  « Matériel et eau ») : demandé le 2026-10-04, **planifié, pas commencé** ; six questions tranchées le 2026-10-05
  (pas de flore, pas de témoin, la campagne déduite), **reste la référence non cultivée, facultative**.
- `chantier-formes-de-parcelle.md` — le plan (4 phases) d'une nouvelle forme de parcelle, le polygone, choisie à la
  création ; le rectangle reste tel quel. Demandé le 2026-10-05, **planifié, pas commencé** ; six questions tranchées le
  2026-10-07 : un polygone dont les sommets collent à une grille de 10 cm, des **zones de culture** polygonales au lieu
  des bordures (le reste est passe-pied), des points d'eau polygonaux hors des zones, rien de repris de la carte du site.
  Les cinq points de détail tranchés le même jour (grille de 5 à 100 cm ; pérenne ou potager ; un scénario par
  parcelle, une zone = une planche de potager polygonale ; un point d'eau = surface et emplacement ; forme modifiable
  tant que la parcelle est vide) : **prêt à commencer par la Phase 1**. « Tracer une ligne » et « Gomme » retirés de la
  barre d'outils du plan en attendant.
- `chantier-suppression-projet.md` — la spécification (3 phases) pour archiver
  puis supprimer un projet, demandée le 2026-09-18 : **spécifiée, à réaliser
  plus tard, sans date** — aujourd'hui personne ne peut supprimer un projet.
- `chantier-documentation-atelier.md` — le plan (4 phases) pour mettre à jour le livret
  « L'atelier » (`contenu/documentation-atelier.md`) puis le maintenir : confié à
  Claude par le porteur le 2026-09-19, **phases 1 à 3 faites, livret pas encore déployé**
  — voir § Directives de travail pour la règle de maintenance.
- `chantier-manuel-utilisateur.md` — le plan (6 phases) d'un **manuel utilisateur illustré de
  copies d'écran**, en markdown, **hors de l'atelier** (dossier `manuel/`, non déployé) :
  demandé par le porteur le 2026-09-19, **planifié, pas commencé — à entreprendre sur sa
  demande, à priori à la version 1.0** ; markdown **et PDF** ; les captures se régénèrent
  par un script sur un projet de démonstration. Décisions toutes tranchées.
- `chantier-dossier-projet.md` — le plan (5 phases) du **Dossier Projet imprimable** : un
  document papier du projet de la personne (vision, terrain, parcelles, scénario, matériel,
  eau, calendrier), page hors gabarit comme `releve-vierge.php` (**seconde exception admise
  par le porteur**, réservée aux documents papier), chapitres à choisir avant l'impression,
  tracés seuls sans carte de tiers, entrée de menu « Dossier Projet » dans une section
  placée avant « Documentation », puis un **sous-dossier par parcelle** qui sert de
  document de chantier pour l'implantation. Demandé et **cadré le 2026-09-19** (les six
  questions sont tranchées) ; **Phase 1 faite le 2026-10-02** (la page, la couverture, la Définition, le
  choix des chapitres, le menu), **Phase 2 le 2026-10-03** (terrain, parcelles), **Phase 3 le 2026-10-04** (matériel,
  eau, calendrier, participants), **Phase 4 le 2026-10-05** (Réalisation, Suivi avec le journal de chaque parcelle,
  frise lisible en noir et blanc), **Phase 5 le 2026-10-05** (le feuillet du chantier de plantation de chaque parcelle,
  `feuillet-chantier.php`) — les cinq phases faites, reste le déploiement.
- `chantier-saut-du-chat.md` — le plan (5 phases) pour outiller le **saut du chat**, « clé de voûte du
  système » selon le porteur : journal des perturbations (table `intervention`, déjà au schéma), constat
  comparé à un témoin et diagnostic des conditions, stade du terrain, itinéraire technique, puis la
  conception qui le prépare. Demandé et planifié le 2026-09-20, **Phases 1 (le journal), 2 (le constat et le diagnostic), 2 bis (la
  préparation et la campagne de plantation) et 2 ter (les adventices) faites le 2026-09-20, la Phase 5 (la conception) le 2026-09-21**, les Phases 3 et 4 en attente du porteur et de la formation ; **développement itératif
  et incrémental** — la formation Joala se poursuit et ses versements (`~/projets/input/`) ferment peu à peu
  les questions ouvertes listées dans le chantier ; une règle sans réponse sourcée ne se code pas.
- `chantier-plan-de-culture.md` — le plan (6 phases) du **plan de culture du potager** : composer une parcelle « potager » (aujourd'hui
  aucune page ne l'écrit : `succession_ligne`, au schéma, reste vide), y **placer les plantes de production par planche selon les
  associations** (Weinrich) et poser le **calendrier de plantation** du plan de culture de Lopez (Haute-Vienne). Proposé le 2026-09-20,
  **Phases 1 à 4 faites le 2026-09-20** (composer la parcelle potager **par planche**, les voisinages de Weinrich, le **placement proposé**, le
  **calendrier** avec les repères de Lopez) ; **les Phases 5 (succession) et 6 (matériel et eau) faites le 2026-09-21** — le chantier est terminé ; né de `chantier-saut-du-chat.md` (décision 8) et de `chantier-associations.md`.
- `chantier-associations.md` — le plan (5 phases) des **associations de plantes de production**, demandé par le porteur le
  2026-09-20 : importer les tables de Weinrich (fait), puis **proposer le placement des plantes de production selon les
  associations — ou leur dissociation : éviter certains voisinages**. Phase 1 (importer et lire) faite ; les autres pas
  commencées. **Décidé le 2026-09-20** : le placement se fait **par planche** et concerne le maraîchage et le potager ; pour un verger ou
  un jardin-forêt, l'association, ce sont les **guildes et consortiums de Plantae** (`guilde.csv` : services rendus et besoins requis), lus par un
  **instantané** (`contenu/guildes-plantae.json`, `src/Guildes.php` — pas encore branché à une page). Une source
  dont les réserves se disent à l'écran : des ordres de conduite, des tables non symétriques, deux plantes toxiques.
- `chantier-caracteristiques-vegetaux.md` — le plan (3 phases) pour que l'atelier connaisse les **caractéristiques des
  végétaux** (dates de semis/plantation/récolte, rôles de service) qui nourrissent le calendrier de plantation d'un
  potager — par le référentiel `espece` et `public/especes.php`. Proposé le 2026-09-22, **les trois phases sont faites,
  chantier terminé le 2026-09-23, pas déployé**. Quatre décisions tranchées : le potager
  d'abord, pas le scénario pérenne ; le référentiel `espece` plutôt qu'une table séparée ; le vocabulaire des rôles de
  `Scenario::FONCTIONS`, repris pour **`succession_ligne.role`**. **`espece.role` a rejoint ce même vocabulaire le
  2026-09-23** — écart de la Phase 1 (le garder libre) refermé sur demande du porteur : liste fermée, plusieurs
  valeurs à la fois (`Especes::roles()`), avec un modèle CSV téléchargeable depuis `especes.php` et une tolérance
  aux colonnes inconnues d'un import (ignorées, dites à l'aperçu, plus jamais refusées) — voir § Point de reprise.
  Un premier fichier fourni par le porteur (`base_semences_planification_maraichage.csv`, une banque de semences
  personnelle) a été **nettoyé hors de ce dépôt** (une anomalie structurelle répétée dix fois, corrigée ; rien
  d'agronomique inventé ni changé) — **n'a toujours pas servi d'essai d'import** (ses colonnes libres ne
  correspondent pas au format attendu, traduire l'un vers l'autre n'a pas été décidé).
- `chantier-peuplement-parcelle.md` — le plan (7 phases) pour **peupler une parcelle dans l'ordre du
  système** : les bordures (une ligne par côté, une série d'espèces qui alternent, les plants comptés
  par espèce), les planches (fait), les productions (dont des pérennes : tortues zèbre, abeille, liane),
  puis les lignes de service sur les planches — l'atelier **propose espèce, emplacement et nombre** à
  partir du référentiel, avec des règles tirées d'un nœud de compétence à créer (`skills-map/`). Proposé
  le 2026-09-23 ; **Phases 1 (le nœud `skills-map/nodes/conception-de-la-tortue.md`) et 1 bis (`parcelle.tortue`,
  `espece.cycle`, schéma 11), 2 (les bordures peuplées : `ligne_plantation`, schéma 12) et 3 (proposer toutes les
  espèces qui remplissent la fonction de la bordure, hors annuelles), 4 (les lignes de chaque planche, `Planches.php`)
  5 (planches pérennes et annuelles en zèbre, bandes en abeille, respectées par le placement du potager) et 6 (les
  plantes de service proposées sur les lignes des planches, `LignesDeService.php`) et 7 (les plants des lignes du plan
  dans Matériel et eau, où ils font foi pour les bordures) faites le jour même — chantier terminé**,
  **déployé le 2026-09-23** (septième vague, voir § Point de reprise) ; tranché le jour même : le type de tortue (classique, zèbre, abeille,
  liane) est un réglage de la parcelle, l'écart entre deux plants voisins d'une bordure est la moyenne de
  leurs espacements (la moitié aux bouts), une colonne `espece.cycle` (annuelle, bisannuelle, vivace,
  ligneuse), les lignes dans une nouvelle table, la première règle des services validée, les lignes de bordure du
  plan font foi face au champ « Emplacement » du scénario. Les espacements cotés du corpus sont au cadre ; le rôle
  `vent` n'a toujours aucune espèce. La suite : `chantier-film-de-la-parcelle.md`.
- `chantier-film-de-la-parcelle.md` — le plan (6 phases) du **scénario en quatre dimensions** : chaque plante à son
  point de plantation au sol, sans collision ; les espacements respectés dans une même strate ; le temps en semaines,
  de la germination à la récolte, à l'échelle choisie ; les vides du calendrier montrés et comblés ; un curseur de
  temps sur la frise et sur le plan vu de dessus. Proposé le 2026-09-23, questions tranchées (une rouverte le 2026-10-01 : quand une plante change de strate — la fève, la pomme de terre) ; **Phases 1 (la compétence)
  2 (`Temps.php`, `espece.delai_germination_j` — schéma 13) et 2 bis (le calendrier prévisionnel des lignes de service
  et des bordures : `ligne_calendrier`, `perturbation_prevue` — schéma 14) faites**, et `espece.temperature_sol_semis_c`
  (schéma 15, en avance sur la Phase 7) ; **déployées le 2026-09-23** (huitième vague).
- `chantier-repartition-bordures.md` — le plan (4 phases) pour **répartir une liste de plantes sur les bordures** d'une
  parcelle : une liste pour toute la parcelle, que l'atelier dispose sur les bordures définies (fonction, émergentes au sud
  et à l'ouest de l'externe, brise-vent sur les côtés de l'externe que la personne coche, alternance, une espèce
  « colonne vertébrale » par bordure un plant sur deux, espacement), sur des bordures toutes vides ; une plante sans
  espacement est écartée ; une liste qui ne couvre pas toutes les fonctions est refusée. Deux espèces au moins par
  côté ; sur un côté brise-vent, brise-vent et bloque-herbe en alternance. Proposé et tranché le 2026-10-01 ; **chantier
  terminé et déployé le 2026-10-01** (douzième vague) — la règle (`src/RepartitionBordures.php`, pure), la coche « vent » par côté de
  l'externe (`parcelle.plan`), la page (`conception-parcelles.php`, bloc `#rb`, `LignesPlantation::poserRepartition()`) ; les espacements qui manquaient
  (46 des 64 espèces de bordure) complétés par le porteur le même jour — cadre régénéré et déployé.
- `chantier-presentation.md` — le plan (5 phases) pour mettre en ligne la présentation de l'atelier
  (`contenu/presentation-utilisateurs.html`) sur le gabarit « le bureau », une diapositive par écran. Demandé
  le 2026-10-03 ; **planifié, pas commencé** — questions tranchées le jour même : sur le bureau, publique, sans adresse mail,
  datée et mise à jour sur demande, nommée « Présentation ».
- `chantier-textes-atelier.md` — le plan (5 vagues) pour réécrire les textes de l'atelier dans le style du porteur :
  pages, textes de `src/`, livrets « L'atelier » et « Les sources », glossaire. Demandé le 2026-10-03 ; **terminé et déployé le
  même jour**, cinq vagues relues par le porteur.
- `chantier-espacement-des-services.md` — le plan (5 phases) pour **tirer des sources l'espacement des plantes** : un
  espacement par fonction (Théry ch. 11 et 14, Joala 7-10) — de service (`espacement_retenu_m` : 25 cm en biomasse,
  40 cm en bloque-herbe, rien pour le brise-vent) et de production (`espacement_monoculture_m`) ; le service lu dans
  `role` (biomasse d'abord) ; la ligne pérenne en série, la production en structure et le service entre ; **75 % de
  biomasse**, par planche au potager, par ligne en pérenne, dit comme une information et compté dans « Matériel et
  eau ». Demandé le 2026-10-04 avec un CSV du cadre revu par le porteur, **qui attend la règle** ; toutes les
  questions tranchées le jour même ; **Phase 1 (la règle au nœud `conception-de-la-tortue.md`) Phase 2 (les deux espacements nommés à l'écran, `src/Espacement.php`) Phase 3 (la ligne centrale pérenne comptée en série, `Materiel::quantitesScenario()`) Phase 4 (la part de biomasse au plan, au scénario et au potager ; la biomasse à sacrifier dans « Matériel et eau ») et Phase 5 (l'espacement de service par défaut tiré de la fonction ; le CSV intégré, le noyer seulement en production) faites** — à reprendre après la question du porteur à Théry, en janvier 2027.
