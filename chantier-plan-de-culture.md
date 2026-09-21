# Le plan de culture du potager — plan

> **État au 2026-09-20 — Phases 1 (composer la parcelle potager, par planche), 2 (les voisinages par planche), 3 (le placement
> proposé) et 4 (le calendrier) faites ; les Phases 5 et 6 ne sont pas commencées.** Né de deux ajouts du porteur, le même jour, pendant le chantier
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

## Phase 3 — faite (2026-09-20) : proposer le placement

`src/PlanDeCulture.php` (pur, 20 contrôles) et, sur `conception-potager.php`, un bouton **« Proposer un placement »** (visible s'il y a des cultures
non placées et des planches au plan).

**L'algorithme, simple et relisible** :
1. **Une culture déjà placée ne bouge jamais** : seules les non placées sont concernées.
2. On traite d'abord les **plus contraintes** (le plus de relations connues avec les autres cultures), puis par nom : le résultat est le même à
   chaque fois, quel que soit l'ordre d'entrée (testé).
3. Une planche est **écartée** si elle porte un voisin à éviter (défavorable, ou contradictoire). Si toutes le sont, la culture **reste non placée** et la
   **raison est dite** (« chaque planche a un voisin à éviter (ail) : ajoutez une planche, ou décidez vous-même ») — l'atelier ne force pas un voisinage néfaste.
4. Parmi les planches restantes : celle où **le plus de voisins s'entendent bien** ; à égalité, la moins chargée, puis la première. Une paire **sans relation
   connue ne compte ni pour ni contre**.
5. Les cultures se **répartissent** : jamais plus de `max(2, ceil(total / planches))` par planche (ni moins de deux : une association, c'est plusieurs cultures
   sur une planche), ni moins que ce qu'une planche porte déjà. **Un équilibre, non un plafond agronomique** — le corpus ne dit pas combien de cultures une planche
   peut porter. *Défaut trouvé en testant* : le plafond valait 1 pour trois cultures sur trois planches, ce qui interdisait toute association.

**L'interface** : la proposition s'affiche d'abord **en aperçu** dans les cartes (« → ail proposée »), avec dans le panneau Données, culture par culture, la planche
et **la raison** (« s'entend bien avec tomate », ou « aucun voisin connu : placée là où il y a de la place »), une case à cocher chacune, et les cultures que l'atelier
n'a pas su placer. **Rien n'est écrit avant l'accord.** « Placer la sélection » recalcule la proposition **sur l'état actuel** : l'empreinte des placements doit
correspondre à celle que la personne a vue (sinon « le plan a changé, recalculez »), et n'est posé que ce que la proposition contient encore. `Cultures::placer()` ne
touche que les cultures **encore non placées**, bornées à la parcelle.

Vérifié en local (compte et projet de test supprimés ensuite) : l'aperçu n'écrit rien ; acceptation partielle (une culture décochée reste non placée) ; **proposition
périmée** refusée ; empreinte fausse refusée ; **identifiants d'une autre parcelle** sans effet ; **une culture déjà placée forgée dans la requête ne bouge pas** ; une
seule planche (le conflit est dit) ; lecteur (ni bouton, ni aperçu, ni panneau, POST forgé 403) ; aucun débordement de 1280×720 à 320×568, avec et sans proposition.

**Au déploiement** : aucun changement de schéma pour cette phase.

**Au déploiement : `bin/deployer.py base` d'abord** (la colonne `planche`), puis `fichiers`.

**Non fait** : le calendrier de Lopez (Phase 4), la succession (Phase 5), Matériel et eau du potager (Phase 6 :
`Materiel` et `BesoinEau` ne lisent encore que le scénario pérenne — un potager y apparaît sans matériel). La page n'est pas encore décrite dans le
livret « L'atelier » comme un parcours complet : seul le principe l'est.

## Phase 4 — faite (2026-09-20) : le calendrier

**Ce que Lopez donne réellement** — plus étroit que « le calendrier de plantation » : son tableau ne couvre que **dix-sept cultures d'automne et d'hiver**
(choux, rutabaga, radis noir, fenouil, blette d'hiver, chicorée, navet, chou chinois, persil, mesclun, épinard, salade d'hiver), en **dates de mots** — « fin
avril », « mi-juin », « mi à fin juillet » —, avec deux cellules qu'il signale lui-même « à vérifier ». **Rien pour la tomate, la courgette ou la carotte.** Il est lu
comme il est : des repères, dits tels, pour ces dix-sept cultures ; pour les autres, pas de repère, et la date est à la personne.

- **`contenu/calendrier-lopez.csv`** (le tableau recopié tel quel, les deux cellules « à vérifier » vides et marquées) et **`src/CalendrierLopez.php`** (pur, 28
  contrôles) : `periode()` lit « fin avril » comme une fenêtre de jours (début 1-10, mi 11-20, fin 21-fin de mois), `pour()` retrouve la culture (sans accent
  ni casse, singulier ou pluriel — « choux » —, quelques alias sûrs : kale, chou vert), **`datesIndicatives()`** propose le **milieu de la fenêtre**, à la prochaine
  occurrence, le repiquage jamais avant le semis. Une salade ou une blette « seules » n'ont **pas** de repère : Lopez parle de la salade d'hiver et de la blette d'hiver.
- **`succession_ligne.date_semis`** (colonne facultative — `Version::SCHEMA` passe à **7**, la fenêtre d'import ne se referme pas ; un fichier du schéma 6 se charge,
  testé). Lopez distingue le **semis** (à l'abri) du **repiquage** ; la colonne existante `date_semis_repiquage` devient, dans l'interface, la **mise en place** — le
  semis en place, ou le repiquage : quand la culture occupe la planche. La mise en place ne précède pas le semis à l'abri ; la récolte ne précède ni l'un ni l'autre.
- **Sur `conception-potager.php`**, deux onglets : **Les planches** et **Le calendrier** — une frise sur une année, mois par mois, planche par planche : **S** (semis à
  l'abri), **P** (mise en place), **R** (récolte), la culture occupant la planche de sa mise en place à sa récolte. Année par défaut : celle de la première date ;
  navigation d'une année à l'autre ; « N cultures datées hors de cette année », « N sans date ». Les lignes se paginent : aucun défilement.
- **À l'édition d'une culture** : le **repère de Lopez** (semis, repiquage, en mots), la mise en garde (des dates en mots, indicatives — « un point de départ à remplacer
  par sa propre mesure »), et un bouton **« Reprendre ces dates »** qui remplit les deux champs ; pour une culture sans repère, la note dit qu'il n'y en a pas et que rien
  n'est proposé. Les dix-sept noms s'ajoutent aux suggestions de saisie.
- Vérifié en local (compte et projet de test supprimés ensuite) : repère et bouton (chou de Bruxelles : semis fin avril → 25/04/2027, repiquage mi-juin → 15/06/2027),
  enregistrement, tomate sans repère, frise (S, P, occupation, R aux bons mois), navigation d'année, refus (mise en place avant le semis à l'abri, récolte avant la mise en place,
  récolte avant le semis), lecteur (frise lisible, ni panneau ni bouton), `verifier-export` identique (le « projet riche » porte un semis à l'abri), fichier du schéma 6 accepté,
  aucun débordement de 1280×720 à 320×568 (vingt-deux lignes paginées).

**Ce qui n'est pas fait** : le **nombre de graines et de plants** (la chaîne de Lopez : marge de semis, taux de réussite — Phase 6), et un calendrier **par culture hors du
tableau de Lopez** (aucune source ne le donne : ne se code pas). Le taux de 80 % du tableau est **une convention de planification, non une mesure** (la source le dit).

**Au déploiement : `bin/deployer.py base` d'abord** (la colonne `date_semis`), puis `fichiers` (qui envoie aussi `contenu/calendrier-lopez.csv`).

## Ce que le module 5 de la formation apporte (versement du 2026-09-20)

Fiche : `connaissances/formation-joala/potager-syntropique.md` — le module « potager » de Joala (synthèses générées ; seules les synthèses ont été lues, à la demande de
Sylvain). C'est **le potager que l'atelier vise** : le potager syntropique, non le maraîchage d'annuelles de Lopez. Ce qu'il change, sans que rien soit encore construit :

1. **Le calendrier d'été existe enfin en source.** Le tableau de Lopez ne couvre que dix-sept cultures d'automne et d'hiver ; le module donne les **dates de la succession
   d'été** (Dordogne, en mots) : maïs semé fin mars-mi-avril, pommes de terre sous paille fin mars-début avril, courges, courgettes et poivrons repiqués mi-mai après les saints
   de glace, haricots de mi-mai à juillet, choux d'hiver repiqués vers la mi-juillet, mâche après les tomates mi-septembre, fèves en novembre ou février, blette semée fin mars.
   *Candidat* : un second tableau de repères (`contenu/calendrier-joala.csv`), lu comme celui de Lopez — dates en mots, donc indicatives, **Dordogne** (« sud de la Loire »),
   synthèses générées, un site. **À décider** : l'ajoute-t-on comme seconde source de repères, et laquelle prime quand les deux parlent d'une même culture ?
2. **Une succession a trois temps sur une planche** : *pré-culture* (avril-mi-mai : radis, salades, betteraves primeurs, épinards), *culture principale*, *post-culture*
   (choux d'hiver, racines d'hiver, mâche). Le calendrier de l'atelier montre déjà l'occupation d'une planche dans l'année ; la **Phase 5** (succession) peut s'appuyer sur ces trois
   temps et sur `associations-succession.csv`. **Une convergence à noter** : le module déconseille de faire précéder les haricots par fèves ou pois (même famille), et Weinrich donne
   le petit pois défavorable au haricot.
3. **Le placement peut lire les strates.** Le potager a quatre strates (émergente : maïs, tournesol ; haute : tomate, aubergine ; moyenne : haricot nain, courgette ; basse : radis,
   carotte, salade), **ajustées au climat** (plus chaud : on descend). La proposition de placement ne les lit pas encore ; les cultures portent déjà `strate`.
4. **Une ligne centrale de biomasse.** La planche du module a une **tranchée centrale** semée de maïs population (biomasse et structure), que l'on taille ; les cultures de production
   sont de part et d'autre. L'atelier n'a pas cette notion : le plan de parcelle dessine des planches, non l'axe de biomasse qui les organise.
5. **Un manque dans le code, constaté en relisant.** Pour une parcelle « potager », **ni le journal des perturbations ni le diagnostic du saut du chat ne voient les cultures** :
   l'un et l'autre lisent `scenario_ligne` (les fonctions « biomasse » et « perturbation » du scénario pérenne), alors que les cultures d'un potager sont dans `succession_ligne`,
   qui n'a pas de fonction. Un potager affiche donc « aucune plante de biomasse », alors que le maïs et le tournesol du module **sont** ses plantes de biomasse. **Corrigé le
   2026-09-21** : une case « plante de biomasse » sur la culture (`succession_ligne.role` = `biomasse`, aucun changement de schéma), lue par `SautDuChat::plantesDeBiomasse()` ; le journal et le diagnostic
   du saut du chat la comptent, et disent où marquer quand aucune ne l'est. Reste : une culture n'a qu'un rôle, aucune « fonction » (perturbation, nourrice…) comme au scénario.
6. **Deux divergences à garder visibles, sans les trancher** : le module associe le **fenouil** aux tomates, courgettes et concombres, quand Weinrich le donne défavorable à la
   tomate (et au haricot) ; et il parle de **maïs population** quand la table dit « maïs doux » (`Associations::fiche('maïs')` ne retrouve donc rien). Voir les questions ci-dessous.
7. **Une réserve de méthode que la source formule elle-même** : « ne jamais généraliser une expérience unique » — tester une association sur une petite bande avant de la déployer.
   C'est l'usage du **témoin** (constat du saut du chat) ; il vaut aussi pour une association de cultures.

## À trancher

1. ~~Par où commencer~~ — **tranché le 2026-09-20 : les planches d'abord**, la chaîne du besoin alimentaire ensuite.
2. **Une planche, plusieurs cultures** : c'est le principe même de l'association ; une planche porte donc plusieurs cultures, à des strates différentes.
3. **Le calendrier d'été** : ajouter les repères du module 5 (Dordogne, dates en mots) comme seconde source, à côté de Lopez ? Sinon, aucun repère pour tomate, courgette, haricot, maïs.
4. **Le maïs** : les tables parlent de « maïs doux », le module de « maïs population » (biomasse, grain). Le rapprocher par un alias serait une hypothèse ; ne pas le faire laisse « maïs » sans fiche.
5. **Le fenouil avec la tomate** (et ce genre de cas) : afficher, à côté de la relation de Weinrich, que la formation associe ces deux plantes (une observation d'un site) — ou ne rien afficher.
6. **Les cultures de biomasse d'un potager** : comment les marquer (un rôle « biomasse » sur la culture ?) pour que le journal et le diagnostic les voient.

## Manière de procéder

Gabarit « le bureau », aucun défilement, un repère est dit avec son pourquoi et sa source, le lecteur ne peut rien écrire, le livret « L'atelier » se met à
jour dans la même vague, tout changement de `sql/01-syntropia.sql` monte `Version::SCHEMA` puis `python3 bin/dev.py verifier-export`.
