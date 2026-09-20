# Le plan de culture du potager — plan

> **État au 2026-09-20 — Phase 1 (composer la parcelle potager, par planche) faite, avec la lecture des voisinages par
> planche (la Phase 2, sans le placement proposé) ; les Phases 3 à 6 ne sont pas commencées.** Né de deux ajouts du porteur, le même jour, pendant le chantier
> « saut du chat » (`chantier-saut-du-chat.md`, décision 8) et le chantier des associations (`chantier-associations.md`).
> Avance **par incréments** ; une règle sans réponse sourcée ne se code pas.

## Ce que le porteur demande

1. Pour les **cultures maraîchères ou potagères**, **le plan de culture de Lopez aide pour le calendrier de plantation**.
2. **Les plantes de production se placent par planche, selon les associations** — proposer le placement quand l'association
   est connue dans la base, ou **éviter certains voisinages** (dissociation).

## Ce qui existe, et ce qui manque

| Existe | Manque |
|---|---|
| Une parcelle peut être de **type « potager »** (`conception-parcelles.php`) | **Aucune page ne compose un potager** : il reçoit aujourd'hui le même scénario qu'une parcelle pérenne |
| Le **plan de parcelle** : planches de 1,20 m, passe-pieds de 0,50 m, `nb_planches` **calculé**, bordures | Rien ne dit **quelle plante** est sur **quelle planche** |
| **`succession_ligne`** au schéma : `demi_planche`, espèce, strate (quatre), espacement, `date_semis_repiquage`, `date_perturbation`, `date_recolte`… | **Aucune page ne l'écrit** ; `Materiel` et `BesoinEau` ne lisent que `scenario_ligne` |
| **`src/Associations.php`** (les tables de Weinrich, vérifié) | Branché à aucune page |
| La fiche `connaissances/maraichage-naturel/lopez-plan-de-culture.md` | Aucun calendrier dans l'atelier |

## Le plan de culture de Lopez — ce qu'il dit

Yann Lopez, maraîcher en **Haute-Vienne** : la première source du dépôt à la bonne latitude, **ses dates se lisent sans
transposition** (contrairement à Weinrich, Allemagne moyenne). **Une boucle**, non une prévision : besoins alimentaires → quantités
à produire → rendements prudents et marge de pertes → surfaces → nombre de plants → nombre de semis → calendrier → culture et récoltes
→ **mesure des rendements réels** → bilan annuel → correction du plan de l'an suivant. À préparer **en décembre**, avant les commandes de
semences. **Cinq vérifications avant de dimensionner** : la surface, le stockage, l'eau, le temps disponible, les rendements observés.
Un jardin d'exemple : planches de 10 m × 1 m, allées de 50 cm, trois zones (printemps et conservation, été, automne et hiver) ; **les deux
tiers d'un potager ne sont pas des planches**.

**Réserve, à dire à l'écran** : Lopez traite d'un **maraîchage d'annuelles en agriculture naturelle** — ni syntropie ni jardin-forêt. *Une pratique
de maraîchage ne se cite jamais comme une pratique syntropique.* Le potager syntropique, c'est le ch. 8 de Théry : le potager recrée sa biomasse
chaque année par des annuelles (`succession_ligne` : deux demi-planches en miroir). Les deux se **croisent**, l'un ne remplace pas l'autre.

## Phases

1. **Composer la parcelle potager.** Une page pour les parcelles de type potager (`?parcelle=ID`) : choisir les cultures, **les placer sur une
   planche** (les planches viennent du plan), avec strate, espacement, dates. Écrit `succession_ligne` ; **une colonne `planche`** (facultative,
   `Version::SCHEMA` monte) — `demi_planche` existe déjà et reste. Sans elle, ni association ni calendrier n'ont de sens.
2. **Les voisinages par planche.** Pour chaque planche, les relations connues entre ses plantes (`Associations::entre()`), les toxiques signalées,
   les réserves dites (des ordres de conduite, non des preuves ; les tables ne sont pas symétriques).
3. **Proposer le placement.** Répartir les plantes non placées sur les planches selon les associations — regrouper les favorables, séparer les
   défavorables — ; la personne accepte, déplace ou ignore (`chantier-associations.md`, Phase 3).
4. **Le calendrier.** Les dates de semis, de repiquage et de récolte, avec les **repères de Lopez** (Haute-Vienne) pour chaque culture ; une frise
   par planche ; la **perturbation** des annuelles y figure (`date_perturbation`).
5. **La succession.** Ce que l'on sème quand une planche se libère (`associations-succession.csv`), le demi-planche en miroir.
6. **Matériel et eau du potager.** Graines et plants nécessaires (la chaîne de Lopez : marge de semis, taux de réussite), besoin en eau — `Materiel` et
   `BesoinEau` lisent aujourd'hui le scénario pérenne.

*Plus tard, et facultatif* : partir du **besoin alimentaire** (la chaîne complète de Lopez : quantités → surfaces → plants), et la **boucle de bilan** (mesurer
les rendements réels, corriger l'an suivant) — proche du **témoin** du constat du saut du chat.

## Phase 1 — faite (2026-09-20) : composer la parcelle potager, par planche

**Décision du porteur (deux questions posées avant de coder) : les planches d'abord**, la chaîne du besoin alimentaire ensuite ;
le placement **par planche** concerne les plantes de production du maraîchage et du potager.

- **`succession_ligne.planche`** (colonne facultative — `Version::SCHEMA` passe à **6**, la fenêtre d'import ne se referme pas ; un fichier
  du schéma 5 se charge, testé). Le numéro de la planche, de 1 à `nb_planches` ; `demi_planche` reste, non utilisée.
- **`src/Web/Cultures.php`** : lire, ajouter, modifier (verrou de version), retirer. Refuse une planche hors du plan (« le plan compte N
  planches »), et toute planche si le plan n'en a pas ; des **dates planifiées** sont permises (un plan de culture se prépare avant de
  semer) ; la récolte ne précède pas le semis ; un `select`/POST forgé est refusé côté serveur.
- **`public/conception-potager.php`** : une carte par planche (celles du plan, plus celles qu'une culture occupe encore hors du plan),
  une carte « Pas encore placées », des cultures (nom, strate, semis) que l'on ouvre pour corriger ; panneau Données pour ajouter ;
  `conception-scenario.php` **redirige** un potager ici, et cette page renvoie une parcelle pérenne à son scénario. Les cartes se réduisent puis se
  paginent : aucun défilement.
- **Les voisinages par planche** (`chantier-associations.md`, Phase 2) : sous chaque planche, ce que `Associations::entre()` sait — « s'entendent
  bien », « à éviter côte à côte » — et « pas de voisinage connu » pour une culture sans fiche propre **que rien ne cite** sur la planche. À
  l'édition, la fiche de la culture (ce qui s'entend bien avec elle, ce qu'il faut éviter). Les réserves sont dites (Informations) : des ordres de
  conduite, non des preuves ; inconnue n'est pas neutre ; l'itinéraire de la source n'est pas celui de l'atelier.
- Vérifié en local (`bin/dev.py` + Chrome headless/CDP, compte et projet de test supprimés ensuite) : six cultures placées sur trois planches
  (relations lues : tomate/carotte, tomate/ail favorables ; ail/haricot à éviter), planche 9 sur un plan de 3, planche 0, planche non numérique,
  strate et date forgées en POST brut, nom vide ou trop long, espacement hors bornes, récolte avant semis, jeton faux, action inconnue, **planche
  sur une parcelle sans plan**, édition, **conflit de version**, cloisonnement (modifier et retirer la culture d'une autre parcelle), retrait avec
  confirmation refusée puis acceptée, lecteur, anonyme, redirections dans les deux sens, `verifier-export` identique (le « projet riche » porte
  une planche), fichier du schéma 5 accepté, **aucun débordement** de 1280×720 à 320×568 (douze planches, trente-trois cultures).

**Au déploiement : `bin/deployer.py base` d'abord** (la colonne `planche`), puis `fichiers`.

**Non fait** : proposer le placement (Phase 3), le calendrier de Lopez (Phase 4), la succession (Phase 5), Matériel et eau du potager (Phase 6 :
`Materiel` et `BesoinEau` ne lisent encore que le scénario pérenne — un potager y apparaît sans matériel). La page n'est pas encore décrite dans le
livret « L'atelier » comme un parcours complet : seul le principe l'est.

## À trancher

1. ~~Par où commencer~~ — **tranché le 2026-09-20 : les planches d'abord**, la chaîne du besoin alimentaire ensuite.
2. **Une planche, plusieurs cultures** : c'est le principe même de l'association ; une planche porte donc plusieurs cultures, à des strates différentes.

## Manière de procéder

Gabarit « le bureau », aucun défilement, un repère est dit avec son pourquoi et sa source, le lecteur ne peut rien écrire, le livret « L'atelier » se met à
jour dans la même vague, tout changement de `sql/01-syntropia.sql` monte `Version::SCHEMA` puis `python3 bin/dev.py verifier-export`.
