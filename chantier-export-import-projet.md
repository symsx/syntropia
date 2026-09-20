# Exporter et importer un projet — plan

> **État au 2026-09-20 — Phases 1 (exporter), 2 (importer) et 3 (aller-retour, avec son script
> `bin/verifier-export-import.php`) faites.** Phase 4 (GeoJSON) facultative, pas commencée. Demandes du porteur le 2026-09-20 : « commence chantier-export-import-projet.md », puis
> versionnement et somme de contrôle, puis « continue avec la phase 2 ». Ce qui est construit et les écarts
> avec le plan : § Phase 1, § Versionner l'atelier, § Phase 2 — faite, en fin de document.

**Demandé par le porteur le 2026-09-18. Rien n'est construit, et cela ne sera
construit qu'à la fin de la première version fonctionnelle de Syntropia** —
décision du porteur, le même jour : le modèle d'information peut encore changer,
et un export doit refléter le schéma tel qu'il sera. Ce document garde les
spécifications, toutes tranchées ; il attend son heure.

**Quand le reprendre** : quand le porteur juge la première version fonctionnelle
atteinte (le parcours de bout en bout, les étapes reportées faites ou
écartées). Avant de coder, **relire `sql/01-syntropia.sql`** : la liste des
tables et des colonnes ci-dessous date du 2026-09-18 et sera probablement
périmée.

## Pourquoi

- **Sauvegarder** un projet et le **redonner** : un projet vit des mois, et
  rien ne permet aujourd'hui d'en garder une copie datée, ni de revenir à un
  état antérieur.
- **Passer un projet d'une instance à l'autre** — poste local ↔ production.
  Le 2026-09-18, le projet réel a été rapatrié de la production vers la base
  locale par un script PHP ponctuel, en PDO direct vers les deux bases, « non
  conservé, ce n'est pas un outil du dépôt » (CLAUDE.md). C'est exactement ce
  besoin, fait à la main et sans filet.
- **Partager un projet exemple**, ou le confier à quelqu'un qui n'a pas de
  compte ici.

## Le format proposé : un fichier JSON, pas du XML

`nom-du-projet_2026-09-18.syntropia.json` — UTF-8, un seul fichier.

Pourquoi JSON plutôt que XML :
- **C'est déjà ce que l'application stocke** : `parcelle.plan`,
  `calque_element.proprietes`, `scenario_ligne.details`, la géométrie des
  tracés sont des JSON dans des colonnes texte. En XML, chacun deviendrait un
  texte échappé à l'intérieur d'un élément — un format dans un format.
- PHP le lit et l'écrit sans extension ni bibliothèque (`json_decode`,
  `json_encode`), comme le reste du dépôt qui n'a aucune dépendance.
- Ce que XML apporterait (un schéma XSD, des commentaires) ne compense pas sa
  verbosité ; la validation se fait ici en PHP, champ par champ, de toute façon.

Un **complément facultatif** : les tracés de la carte en **GeoJSON**
(`.geojson`), lisible par QGIS, ContourBuddy et le formulaire d'import de la
carte — le lecteur (`Geojson::lire()`) existe déjà, il resterait l'écriture.
Ce n'est pas la sauvegarde : elle ne porte que la géométrie.

### Ce que le fichier contient

```json
{
  "format": "syntropia-projet",
  "version": 1,
  "exporte_le": "2026-09-18T21:30:00Z",
  "application": { "commit": "…", "schema": "…" },
  "projet": { "nom": "…", "latitude": 45.0, "longitude": 1.0, "conduite": "syntropique" },
  "participants": [ { "ref": "p1", "nom": "…", "role_projet": "…" } ],
  "reponses": [ { "participant": "p1", "phase": "definition", "question": "…", "texte": "…" } ],
  "parcelles": [ { "ref": "a", "code": "P1", "nom": "…", "plan": { }, "scenario": [ ] } ],
  "calques": [ { "calque": "eau", "type": "polygone", "libelle": "…", "geometrie": [ ], "proprietes": { } } ],
  "especes_locales": [ ]
}
```

- **`exporte_le`** : la date d'export, en ISO 8601 UTC — demandée. Elle
  s'affiche à l'export (« exporté le… »), à l'import (avant de valider : « ce
  fichier date du… ») et dans le nom du fichier.
- **`version`** : un entier. Un fichier plus récent que l'application est
  refusé avec son motif ; un plus ancien passe par des étapes de migration.
- **`application.commit`/`schema`** : avec quelle version il a été écrit —
  pour comprendre plus tard un fichier qui ne s'importe plus.
- **Des références de fichier** (`ref`) à la place des identifiants de base :
  les numéros n'ont de sens que dans la base d'origine.
- **Ce que porte un projet** (les tables reliées à `site` ou `parcelle`) :
  `site`, `parcelle`, `participant`, `reponse`, `calque_element`,
  `scenario_ligne`, `succession_ligne`, `releve_sol`, `intervention`,
  `celebration`, `reconnaissance`, `commande`, `gisement`, `ressource`,
  `synthese`, et les **surcharges d'espèces du site** (`espece` où
  `site_id` ≠ 0 ; le cadre partagé n'est pas exporté, il est déjà là).

### Ce que le fichier ne contient jamais

- **Aucun compte** : ni identifiant, ni courriel, ni empreinte de mot de
  passe. Les participants sortent avec leur **nom seulement**, et rentrent
  comme des personnes **sans compte** (le schéma le prévoit : `participant.
  compte_id` peut être nul).
- **Aucun accès** (`membre_site`) ni invitation : qui a le droit de voir le
  projet se décide à l'arrivée, pas dans le fichier.
- Ce qui se **recalcule** au chargement (les courbes de niveau d'un relevé
  d'altitude, par exemple) : seules les altitudes sont gardées.

## L'import

- **Il crée toujours un projet neuf** — jamais une fusion, jamais un
  écrasement. Celui qui importe en devient le porteur. La fusion de deux états
  d'un même projet est un autre problème, qu'on ne résout pas ici.
- **Un fichier est une entrée non fiable**, comme un GeoJSON : taille
  plafonnée, profondeur JSON limitée, chaque champ validé (type, longueur de
  colonne, énumérations) avant la moindre écriture, aucune désérialisation
  d'objet. Tout dans **une transaction** : un import qui échoue ne laisse rien.
- **Un aperçu avant d'écrire** : nom du projet, date d'export, ce que le
  fichier contient (nombre de parcelles, de tracés, de végétaux, de
  participants) et ce qu'il ne contient pas (comptes, accès).
- Les identifiants sont remappés ; le `code` du site est recalculé.

## Phases envisagées

```
Phase 1  Exporter — bouton pour le porteur, téléchargement du .syntropia.json daté
Phase 2  Importer — aperçu, validation, création du projet dans une transaction
Phase 3  Aller-retour — export → import → export : identique aux identifiants et aux dates près
Phase 4  (facultatif) Les tracés en GeoJSON, pour QGIS et les autres outils
```

## Décidé le 2026-09-18

1. **Le porteur seul exporte.** Le fichier porte les réponses de **tous** les
   participants, pas seulement les siennes : ce n'est pas un droit à donner à
   un contributeur ou à un lecteur.
2. **La date d'export s'affiche à l'écran** : dans le fichier (`exporte_le`),
   à l'export (« exporté le… ») et **à l'aperçu d'import**, avant de valider.
   Pas de « dernier export » sur le projet : cela demanderait une colonne,
   et le porteur n'en a pas fait la demande.
3. **La conduite du site et les surcharges d'espèces sont reprises telles
   quelles**, sans revalidation à l'arrivée.
4. **Le plafond d'import est bas** — voir la mesure ci-dessous.

### Le volume d'un projet, mesuré

Sur le projet réel « La Vadaux » (12 réponses, 1 parcelle, 8 tracés) : **environ
14 Ko de données**, dont l'essentiel est la géométrie des tracés. Le plus gros
tracé de tout le dépôt local fait **11 Ko**. Un projet riche — cinquante végétaux,
une vingtaine de parcelles, des centaines de tracés — reste très en dessous
de **1 Mo**. Un relevé d'altitudes (une grille d'environ 40 × 40 points) pèse
une dizaine de Ko.

**Plafond proposé : 1 Mo** (soit près de 70 fois le projet réel) — bien sous le
plafond d'envoi de PHP (2 Mo en local, 256 Mo chez alwaysdata). Le seul moyen
de le dépasser serait d'avoir importé des milliers de tracés GeoJSON (le
formulaire d'import de la carte en accepte jusqu'à 300 de 1 500 points). Dans ce
cas, **l'export refuse de produire un fichier qu'on ne pourrait pas réimporter**
et dit quelle part est trop lourde — plutôt qu'un fichier muet qui échoue à
l'arrivée.

## La trace d'origine — décidé le 2026-09-18

Une **colonne facultative `site.origine`** : un texte JSON portant le **nom
d'origine** du projet, la **date d'export** du fichier (`exporte_le`) et la
**date d'import**. Une migration additive, du même type que
`invitation.role_site` (rejouable sur MySQL et MariaDB par `information_schema`
+ `PREPARE`) — à poser avec la Phase 2, pas avant : un projet qui n'a jamais été
importé la laisse vide.

## Fichiers critiques

- `sql/01-syntropia.sql` — la liste des tables et leurs liens ; **tout ajout de
  colonne ou de table doit être répercuté dans l'export**, sans quoi une
  sauvegarde perdrait des données en silence.
- `src/Geojson.php` — le lecteur de GeoJSON, modèle de lecture d'un fichier non
  fiable.
- `src/Web/Sites.php`, `Parcelles.php`, `Calques.php`, `Scenarios.php` — les
  services par lesquels l'import doit écrire, pour ne pas contourner leurs
  validations.

## Phase 1 — Exporter : faite le 2026-09-20

**Construit** : `src/Web/Export.php` (le service, lecture seule), `public/exporter.php` (la page, sur
le gabarit, hors parcours, comme `membres.php`), et l'entrée « Exporter le projet » du menu du compte
pour le porteur (`gabarit.js`, à côté de « Participants »).

**La page** annonce avant de produire : le nombre de participants, réponses, parcelles, végétaux,
tracés… que le fichier contiendra, ce qu'il **ne contiendra jamais** (comptes, accès, invitations),
et sa taille. « Télécharger le fichier » est un **POST à jeton** lu par `fetch` : le fichier
se télécharge, et la page dit alors « Exporté le 20/09/2026 à 09:52 — nom.syntropia.json » (la date
d'export s'affiche donc à l'export, comme décidé). Le porteur seul : un contributeur ou un lecteur
voit une phrase, sans bouton, et son POST reçoit 403.

**Écart avec le plan : le fichier suit le schéma, il n'est pas écrit à la main.** Le plan listait
les tables une à une ; le risque qu'il nommait — « une sauvegarde perdrait des données en silence »
quand une colonne s'ajoute — est réglé à la racine : chaque table est lue en entier (`SELECT *`) et
seul est retiré ce qui ne doit jamais sortir. Une colonne ajoutée plus tard sort **sans qu'on y
touche**. Ce qui est retiré, partout : `id`, les clés étrangères (remplacées par des références de
fichier `p1`, `a1`…), `compte_id`, **`saisi_par`** (un compte, dans `intervention` et
`celebration` ; retiré aussi de `releve_sol`, où `ReleveSol` y met un participant — les deux usages
ne se recoupent pas et aucun ne doit sortir), et pour le site : `code`, `code_invitation`,
`role_invitation`. **Vérifié par un test de fuite** : ni `compte_id`, ni `saisi_par`, ni
`empreinte`, ni un identifiant de compte, ni un `"id"`, ni `site_id` n'apparaissent dans un fichier
produit avec trois comptes différents.

**Le format**, tel que produit : `format: "syntropia-projet"`, `exporte_le` (ISO 8601 UTC),
`versions` (`format`, `schema`, `application` — voir § Versionner l'atelier), `application` (`nom`,
`commit` = le commit du code sur un poste de développement, `null` en production où `.git` n'est pas
déployé, `empreinte_schema` = une empreinte des colonnes et de leurs types), puis `projet`, `participants`, `reponses`, `syntheses`, `ressources`, `gisements`, `commandes`,
`especes_locales`, `parcelles` (chacune avec `scenario`, `succession`, `releves_sol`,
`interventions`, `celebrations` et leurs `reconnaissances`) et `calques`, et en dernier `integrite` (SHA-256 du contenu). Les colonnes JSON stockées
en texte (`plan`, `details`, `geometrie`, `proprietes`) sortent **décodées**, les nombres en
nombres (types lus dans `information_schema`), les dates telles que la base les garde. Les
participants sortent avec leur nom, leur rôle de projet, leur engagement — jamais leur compte. Les
réponses désignent leur participant par référence. Un tracé rattaché à une parcelle la désigne par
la sienne. Le nom : `nom-du-projet_2026-09-20.syntropia.json` (sans accent).

**Une écriture plus compacte** : `JSON_PRETTY_PRINT` met un nombre par ligne, et un tracé de la
carte est une liste de couples `[lat, lon]` — le fichier de « La Vadaux » pesait 65 Ko. Une liste de
nombres tient maintenant sur une ligne (`[45.82813, 1.10363]`) : **26 Ko**, contenu identique
(comparé après décodage). Le motif ne peut pas toucher un texte de la personne : il exige de vrais
sauts de ligne autour d'une liste de nombres, et un texte JSON n'en contient jamais (« `\n` » y
est échappé) — vérifié avec une note qui contient précisément `[`, un saut de ligne, `1, 2`.

**Le plafond de 1 Mo** est appliqué : au-delà, `Export::encoder()` refuse et **la page dit ce qui
pèse** (« les commandes (1 473 Ko), les parcelles (5 Ko)… »), le bouton est grisé, et un POST direct
reçoit 413 avec le même message. Vérifié avec 25 commandes de 30 Ko. Mesure sur les projets locaux
réels : La Vadaux 26 Ko, Mon Paradis 6 Ko, un projet de validation 7 Ko.

**Vérifié en CDP** (base locale de test, supprimée ensuite) : la page pour un porteur ; le clic
réel sur le bouton (message daté, nom du fichier) ; le corps du fichier lu par `fetch` (clés,
participants, célébration avec sa reconnaissance rattachée au bon participant, tracé rattaché à
`a1`, tracé libre sans parcelle) ; lecteur et contributeur (pas de bouton, pas d'entrée de menu,
POST 403 avant même le jeton) ; jeton faux 403 ; anonyme redirigé ; trop gros 413 ; **aucun
débordement** de 1280×720 à 320×568, message compris (le bureau centre son contenu, donc
`#bureau{align-items:stretch}` et un `zoom` qui se réajuste jusqu'à tenir — comme le scénario).

**Reste pour la suite** :
- **Phase 2 — Importer** : faite, voir § Phase 2. Décidés par le porteur le 2026-09-20 : **les dates
  d'origine sont gardées** ; **validation des colonnes lue dans le schéma** (option A de § Validation
  des colonnes — « je ne sais pas » : recommandation suivie).
- **Phase 3 — Aller-retour** : la comparaison se fera sur les documents décodés, sans les
  identifiants ni les dates.
- **`ReleveSol::ajouter()`** écrit `saisi_par` = un **participant**, quand `intervention` et
  `celebration` y mettent un **compte** (commentaire du schéma). Incohérence de ma Phase « Matériel et
  eau », sans conséquence pour l'export (il le retire partout) ; à aligner si `saisi_par` devient un
  jour affiché.
- **Pas déployé** : à faire avec le porteur, `fichiers` seul (aucun changement de schéma).

## Validation des colonnes à l'import — ce qui est en jeu (2026-09-20)

**Pourquoi il faut valider.** L'export lit la base, donc tout ce qu'il écrit est déjà valide. L'import
lit **un fichier** : quelqu'un a pu le modifier à la main, il peut être tronqué, venir d'une version
plus ancienne ou plus récente de Syntropia, ou n'être pas un fichier Syntropia du tout. Chaque valeur
qu'il porte doit être vérifiée **avant** la moindre écriture, sinon l'un de ces trois cas arrive :
1. **La base refuse au milieu de l'import** (`conduite = "syntropie"` alors que la colonne n'accepte que
   `syntropique` ou `jardin-foret`) : la transaction annule tout, mais le message est celui de MySQL, illisible.
2. **La base accepte en abîmant** (une valeur trop longue tronquée sans rien dire, selon le mode du serveur).
3. **La base accepte, et c'est la page qui casse plus tard** : c'est le cas des **quatre colonnes de JSON**
   (`parcelle.plan`, `scenario_ligne.details`, `calque_element.geometrie`, `calque_element.proprietes`) —
   MySQL y voit du simple texte, mais la carte attend une liste de couples `[lat, lon]`, le plan de parcelle
   attend des bordures à quatre côtés. Un `geometrie` mal formé n'est pas refusé par la base : c'est la carte
   qui n'affiche plus rien.

**Ce que cela touche** : toutes les colonnes que l'export écrit — environ 200, dans 16 tables. Elles se
rangent en cinq familles : des **textes** (une longueur maximale : `nom` 160, `libelle` 160…), des
**nombres** (un intervalle : `latitude` de −90 à 90, `nb_pieds` de 1 à 65 535), des **énumérations** (une
liste fermée : `type`, `strate`, `origine`, `role`…), des **dates**, et le **JSON**.

**Trois façons de faire** :
- **A. Lire les règles dans le schéma (recommandée).** Comme l'export : `information_schema` donne pour
  chaque colonne son type, sa longueur, ses valeurs permises et si elle accepte le vide. Une seule
  vérification générique couvre les ~200 colonnes, et **suit le schéma** quand il change. S'y ajoutent
  des contrôles **écrits à la main pour les quatre colonnes JSON seulement** (en réutilisant
  `Calques::valider()` pour la géométrie, qui existe déjà). Un fichier qui porte une colonne **inconnue**
  de la base est refusé avec son motif ; une colonne **absente** prend la valeur par défaut.
- **B. Une liste blanche écrite à la main**, colonne par colonne. Plus précise, mais deux cents lignes à
  tenir à jour à chaque évolution du schéma — c'est exactement le risque que l'export a écarté.
- **C. Faire confiance et laisser la base refuser.** Le plus court à écrire ; les cas 1 et 3 ci-dessus
  restent, et le message d'erreur est celui de MySQL.

**Recommandation : A.** Elle demande un peu plus qu'C, beaucoup moins que B, et donne un refus lisible
(« la parcelle a1 : la longueur du nom dépasse 160 caractères »), tout en réglant le cas des quatre
colonnes de JSON. À décider avec le porteur avant d'écrire la Phase 2.

## Versionner l'atelier — demandé le 2026-09-20

Demande du porteur : « versionner l'atelier (base et page séparément ? à toi de donner ton avis).
Inclure la ou les versions dans le fichier exporté. Ajouter un contrôle hashage et interdire le
chargement d'un fichier modifié. Recharger uniquement si versions compatibles. » **Fait pour
l'export et la porte d'entrée de l'import** (`Export::controler()`, sans écriture) ; l'import
lui-même reste la Phase 2.

### Mon avis : trois versions, dont une seule décide

**Oui, séparer** — parce que le code et la base **se déploient séparément** (`fichiers` d'un côté,
`base` de l'autre : c'est ce qui a donné l'incident `espece.variete`), donc peuvent ne pas
s'accorder, et parce qu'elles ne répondent pas à la même question. Mais pas deux : **trois**,
et une seule empêche de charger un fichier.

| Version | Ce qu'elle dit | Où elle vit | Bloque ? |
|---|---|---|---|
| **`format`** | la forme du fichier (les clés, leur sens) | `Version::FORMAT` | oui : un format plus récent ne se lit pas ; un plus ancien non plus, tant qu'aucune migration n'existe |
| **`schema`** | la forme des données — les tables et colonnes | **la base** (`atelier_version`) **et** `Version::SCHEMA` | **oui, c'est elle qui décide** (fenêtre ci-dessous) |
| **`application`** | le numéro des pages et du code | `Version::APPLICATION` | **non** — informatif : un changement de page ne change pas la forme des données |

La version de **schéma est écrite dans la base**, par le même script qui la façonne : une ligne
`INSERT … ON DUPLICATE KEY UPDATE` à la fin de `sql/01-syntropia.sql`, rejouée par `dev.py init` et
`deployer.py base`. La base dit donc le schéma **qu'elle a réellement reçu**, pas celui que le code
croit déployé. Si les deux divergent (code déployé, base non rejouée — ou l'inverse), **l'export et
l'import refusent** (`Export::accordCodeEtBase()`) plutôt que de produire ou charger un fichier
qui décrirait mal ses données. Vérifié en local : base mise au schéma 2 avec un code au schéma 1 →
bouton grisé, message, POST direct 409.

**La fenêtre de compatibilité** (`Version.php`) : un fichier se recharge si son schéma est entre
`SCHEMA_IMPORTABLE_DEPUIS` et `SCHEMA` (celui de la base qui reçoit). Plus récent → « mettez d'abord
cet atelier à jour » ; plus ancien que la fenêtre → « trop ancien pour se charger tel quel ». **Un
ajout de colonne facultative ne referme pas la fenêtre** : `SCHEMA` monte, `SCHEMA_IMPORTABLE_DEPUIS`
reste, et un ancien fichier se charge (la colonne qui lui manque prend sa valeur par défaut). Un
changement qui casse (colonne retirée, changée de sens) monte les deux — et demande une migration.
**Règle de travail** : tout changement de `sql/01-syntropia.sql` monte `SCHEMA` **dans le même commit**,
dans `src/Version.php` et dans la ligne `atelier_version` du SQL (les trois s'accordent, et le contrôle
ci-dessus le voit si l'une est oubliée).

**Le numéro d'atelier `0.9.0` est confirmé par le porteur** (informatif, il n'engage rien). Le schéma
était à `1` au premier versionnement, il est à **`2`** depuis la Phase 2 (`site.origine`).

### La somme de contrôle — ce qu'elle garantit, et sa limite

`integrite = SHA-256` du contenu, sans son propre champ, calculé sur l'**écriture compacte de la
structure** et non sur les octets : un fichier rouvert et réenregistré par un éditeur, ou réécrit
par un autre outil (Python, indentation différente, `\u` échappés) **reste accepté** — vérifié ;
une lettre changée, une coordonnée modifiée à la 5e décimale, la date d'export changée, la somme
retirée ou fausse : **refusés** (vérifié, 17 cas). L'ordre du contrôle : c'est un export, **puis
non modifié**, puis les versions — un fichier retouché ne doit pas se faire passer pour « trop
ancien ».

**La limite, à dire franchement : une somme *dans* le fichier n'interdit pas la falsification
délibérée.** Qui connaît le format peut modifier le fichier *et recalculer la somme* (vérifié :
accepté). Elle protège d'un fichier abîmé, tronqué, ou retouché sans précaution — pas de quelqu'un
qui le veut. Deux conséquences : (1) **l'import valide chaque valeur de toute façon** (§ Validation
des colonnes) — la somme dit qu'un contenu est intact, pas qu'il est *valide* ; (2) si le porteur veut
qu'un fichier soit **infalsifiable**, la seule voie est une **signature à clé secrète** (HMAC-SHA-256,
un secret dans `config/base.php`/`local.env`) : sans le secret, impossible de recalculer. Son prix : le
secret doit être **le même sur toute instance qui importe** (poste local et production), et un
fichier signé par une instance dont le secret est perdu n'est plus rechargeable. **Tranché par le
porteur le 2026-09-20 : somme seule** (portable, sans secret) — « pas la peine d'aller plus loin ».
Numéro d'atelier **0.9.0 confirmé**.

### À faire au déploiement

- **`bin/deployer.py base` d'abord, puis `fichiers`** : la table `atelier_version` et la colonne
  `site.origine` sont nouvelles (deux migrations additives, rejouables, sur MySQL comme MariaDB). Sans
  elles, l'export et l'import refusent et le disent (« la base n'a pas encore sa version de schéma »).
- Livret « L'atelier » : la phrase sur le fichier qui dit d'où il vient et ne se recharge pas modifié est
  ajoutée (2026-09-20, le porteur ayant reporté ses propres ajouts).

## Phase 2 — Importer : faite le 2026-09-20

**Construit** : `src/Web/Import.php` (le service : `examiner()` ne lit que le schéma et n'écrit rien ;
`creer()` écrit tout dans une transaction), `public/importer.php` (la page, sur le gabarit, hors parcours),
l'entrée « Importer un projet » du menu du compte pour **tout compte connecté** (`gabarit.js`), la colonne
**`site.origine`** (migration additive portable, dans `CREATE TABLE` pour une base neuve et par
`information_schema` + `PREPARE` pour une base existante), `Sites::codePour()`, et **`Version::SCHEMA` = 2**
(la fenêtre ne se referme pas : un export du schéma 1 se recharge).

**Le parcours de la page, en trois temps** : (1) on envoie le fichier — `examiner()` le contrôle en entier ;
refusé, la page dit **quoi** (jusqu'à 6 lignes, puis « … et N autres ») ; (2) valide, un **aperçu** montre ce
qu'il contient, sa date d'export, ses versions, ce qu'il ne porte pas ; on y choisit le **nom du projet**
(celui du fichier, suffixé « (import du JJ/MM/AAAA) » si l'on a déjà un projet de ce nom) et **qui l'on est
parmi les participants** (présélectionné si le nom correspond au nom affiché) ; (3) « Créer le projet ».
Entre (1) et (2) le fichier attend dans la session ; **il est examiné de nouveau à la confirmation** — on
n'écrit que ce qui vient d'être vérifié. « Annuler » vide la session. À la fin, le projet importé devient le
**projet actif** et un lien mène à « Ouvrir le projet ».

**Le projet importé** : toujours neuf ; celui qui importe en est le **porteur** ; le `code` du site est
recalculé ; les identifiants sont refaits (les références `p1`/`a1` du fichier sont remappées) ; **les dates
d'origine sont gardées** (vérifié : une parcelle datée du 2026-08-01 garde cette date) ; `site.origine`
porte le nom d'origine, la date d'export, la date d'import et les versions du fichier. **Les participants
entrent sans compte**, sauf celui que l'importateur dit être : son `participant.compte_id` est posé, et il
**retrouve ses réponses** (vérifié : `Reponses::pourParticipant` rend « vision » et « objectifs_productions »).
Sans choix, il arrive comme nouveau participant (`participantDuCompte` le crée, avec son numéro de compte en
suffixe si le nom est déjà pris). `origine` n'est **jamais réexporté** (ajouté à `Export::SITE_JAMAIS`).

**La validation, option A, telle que construite** : les règles de chaque colonne sont lues dans
`information_schema` (type, longueur, plage des entiers signés ou non, précision des décimaux, valeurs des
énumérations, vide permis ou non, colonne exigée ou non) — **aucune liste à tenir à la main**. Une clé
inconnue du schéma **refuse** le fichier ; une clé qui ne doit jamais entrer (`id`, `compte_id`, `saisi_par`,
`code_invitation`…) aussi ; une colonne absente prend sa valeur par défaut, sauf si elle est exigée. Les noms
de colonnes écrits en SQL viennent d'`information_schema`, jamais du fichier ; les valeurs sont liées. À cela
s'ajoutent : **six plages de sens** que le schéma ne dit pas (`site.latitude` ±90, `longitude` ±180,
les deux pourcentages de 0 à 100, `ph` de 0 à 14) — trouvé en testant : une latitude de 999 tenait dans
`DECIMAL(8,5)` — et **le contrôle écrit des quatre colonnes de JSON** : `parcelle.plan` (bordures à quatre
côtés, largeur de 0,05 à 5 m, rattrapage connu, aucune clé inconnue), `scenario_ligne.details` (emplacement,
fonctions, catégorie, couleur, repères 0-28 : clés connues vérifiées, clé de plus tolérée),
`calque_element.geometrie` (`Calques::pointsDepuis()`, minimum de points par type) et `.proprietes` (position
d'étiquette et centre sur Terre).

**Vérifié** (base locale de test, supprimée ensuite, jamais la production) :
- **Aller-retour identique** sur quatre projets — un projet riche bâti pour l'occasion (deux participants, deux
  parcelles, scénario, succession, relevé, intervention, célébration avec sa reconnaissance, ressource,
  gisement, commande, synthèse, espèce locale, trois tracés dont une grille) et trois projets réels locaux : le
  fichier exporté du projet importé est identique à l'original, aux identifiants et à la date d'export près
  (un chiffre écrit `45.0` d'un côté et `45` de l'autre, égaux, ne comptent pas).
- **41 refus** de fichiers dont la somme de contrôle avait été **recalculée** — pour tester la validation, non la
  somme : champ inconnu, `compte_id`/`saisi_par`/`id`/`code_invitation`, texte trop long, énumération hors liste,
  nombre en texte ou décimal ou négatif, date impossible, champ exigé vide ou absent, caractère nul,
  participant ou parcelle inconnus, référence en double, géométrie illisible ou hors Terre ou trop courte,
  étiquette en texte, plan et détails mal formés, partie inconnue à la racine, pas une liste. Une seule fuite
  trouvée (la latitude), corrigée. Au-delà de 15 erreurs : « … et N autres ».
- **Un doublon que seule la base voit** (deux parcelles au même code) : l'examen l'accepte, l'écriture le
  refuse avec un message lisible et **rien ne reste** (nombre de sites et d'accès inchangé).
- **Dans la page, en CDP** : les quatre refus de l'étape 1 (fichier modifié, schéma plus récent, valeurs
  invalides, pas du JSON) avec leur message ; l'aperçu ; « Annuler » ; nom vide refusé sans perdre le fichier ;
  création réelle ; projet actif ; parcelles et matériel du projet importé lisibles ; jeton faux redirigé.
  **Aucun débordement** de 1280×720 à 320×568 à chaque étape (étape 1, erreurs, aperçu) — le cas le plus bas
  (16 erreurs à 320×568) débordait de 94 px même réduit : la liste est plafonnée à 6 lignes.

**Ce que l'import ne fait pas** : fusionner (un projet neuf, toujours) ; rattacher un compte à un participant
autrement que celui que l'importateur désigne ; importer un fichier du schéma 0 ou d'un format autre que 1 (il
n'existe pas encore de migration) ; **rejouer sa décision sur une base qui n'est pas au schéma du code**
(refus, comme l'export). Non testé : le comportement de `upload_max_filesize` sous 1 Mo (le plafond de PHP local
est de 2 Mo).

**Phase 3 — Aller-retour : faite, et le script est conservé** (2026-09-20, demande du porteur) :
`bin/verifier-export-import.php`, lancé par **`python3 bin/dev.py verifier-export [id…]`** — jamais en direct
(il refuse de tourner si `SYNTROPIA_DB_HOTE` n'est pas ce poste). **À rejouer à chaque changement de schéma** :
c'est ce qui dit qu'une colonne nouvelle sort de l'export *et* rentre par l'import. Pour chaque projet de la base
locale — plus un **projet riche bâti pour l'occasion** (une ligne de chaque table que l'export lit) — il
exporte, examine, importe sous un **compte de passage**, exporte la copie et compare (aux identifiants et à la
date d'export près ; `45.0` et `45` sont égaux), puis **supprime la copie et le compte, même en cas
d'erreur** ; les projets d'origine ne sont que lus. Il rejoue aussi trois refus (fichier modifié d'une lettre,
schéma plus récent, colonne interdite — la somme recalculée pour que ce soit la validation qui refuse). Code de
sortie 1 au moindre écart. **Le projet riche est écrit colonne par colonne** : si le schéma change au point
qu'il ne s'insère plus, le script le dit — c'est alors lui qu'il faut mettre à jour. Résultat du 2026-09-20 :
les cinq projets locaux et le projet riche, identiques ; les trois refus tiennent.
**Phase 4 — Les tracés en GeoJSON** (facultative) : pas commencée.

