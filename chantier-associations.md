# Les associations de plantes de production — plan

> **État au 2026-09-20 — Phase 1 (importer et lire) faite ; Phase 2 (lire les voisinages au potager) faite dans `chantier-plan-de-culture.md` ;
> Phases 3 à 5 pas commencées ; l'instantané des guildes de Plantae est posé (Phase 4, données et lecteur).** Deux décisions du
> porteur, le même jour, ont recadré les phases : le placement se fait **par planche** et concerne le **maraîchage et le
> potager** ; pour un **verger ou un jardin-forêt**, les associations sont les **guildes ou consortiums de Plantae**.
> Demandé par le porteur le 2026-09-20, pendant le chantier « saut du chat » (`chantier-saut-du-chat.md`, décision 8).
> Ce chantier avance **par incréments** : il s'appuie sur une source dont il faut respecter les réserves, et sur des
> décisions de conception (le modèle de placement) qui restent à trancher.

## Ce que le porteur demande

1. **Importer** les tables d'association dans ce dépôt (décision du 2026-09-20).
2. **Gérer l'accompagnement avec les associations de plantes de production.** « L'atelier doit permettre de proposer
   le placement des plantes de production selon les associations, si elle est connue dans la base d'association — ou
   la **dissociation** : éviter certains voisinages. »

Deux verbes : **proposer** (l'atelier suggère, la personne décide) et **éviter** (un voisinage néfaste est une
information de conception, pas une interdiction — la source le dit : « une frontière que le lecteur risque de franchir »).

## La source, et ses réserves

Christa Weinrich (abbaye de Fulda, plus de 50 ans de pratique) — `connaissances/cultures-associees/
weinrich-cultures-associees.md`, et la note d'articulation du projet `syntropie` (`referentiels/
articulation-cultures-associees.md`, non importée : ses réserves sont reprises ici). **Trois tables** : le potager
(31 espèces, voisinages favorables et défavorables), le verger (13 porteurs et leurs compagnons), la succession
(30 espèces, préculture et postculture).

| Ce qui se transporte | Ce qui ne se transporte pas |
|---|---|
| Les **couples** et les **mécanismes** (les cinq clés d'une bonne entente), la rupture de continuité contre les ravageurs | **L'itinéraire** : sol ameubli chaque printemps à la binette — contraire au principe de l'atelier (sol non travaillé, couvert) |
| Le principe : placer une plante peu exigeante, ou qui améliore le sol, à côté d'une gourmande | **Les dates** : Allemagne moyenne (Jura souabe, Hesse) — elles ne se lisent pas telles quelles, contrairement à Lopez (Haute-Vienne) |

Les réserves que l'atelier **doit dire à l'écran**, et que le code respecte (`src/Associations.php`) :
- **Des ordres de conduite, jamais des preuves.** Une relation bénéfique peut ne pas se vérifier partout : elle
  s'éprouve localement — et l'atelier a le **témoin** pour cela (voir le constat du saut du chat).
- **Les tables ne sont pas symétriques.** Elles disent ce que chaque plante déclare, sans contrôle de réciprocité : 82
  relations du potager et 4 du verger ne sont pas reprises de l'autre côté. Le verger **n'est pas un graphe**. Une
  relation est donc **connue si l'une des deux plantes la cite** ; un silence n'est pas un désaccord. (Aucune contradiction
  entre deux plantes dans les données actuelles — vérifié.)
- **Deux plantes toxiques** sont données comme favorables (la **digitale** au poirier, l'**aconit** au pommier). Elles se
  **signalent à chaque affichage**, jamais recopiées dans une liste de plantation sans mention.
- **Inconnue ne veut pas dire neutre** : une paire absente des tables ne dit rien.
- **La fatigue du sol** : fruitiers à noyau et à pépins sont des Rosacées ; replanter le même type au même point expose à
  la fatigue du sol (un phénomène spécifique d'une plante) — la parade citée est une association, non une jachère (l'œillet
  d'Inde entre les rosiers). Ce qui arrive quand une tortue est remplacée. **À instruire** (Phase 4).

## Ce que les tables couvrent de l'atelier — un fait mesuré

Sur les **45 espèces du cadre** (`sql/02-especes-seed.sql`), **11 ont une fiche** dans les tables (pommier, poirier,
cognassier, cerisier, vigne, courgette, concombre, tomate, poivron, aubergine, pomme de terre) et **6 y sont seulement
citées** comme voisines (noyer, mélisse, lavande, fève, haricot, maïs). **Aucun ligneux de biomasse** (robinier, peuplier,
saule…) : les tables parlent des **plantes de production** — légumes et fruitiers —, ce qui est exactement ce que le
porteur demande, et rien d'autre. Pour les végétaux du système d'accumulation, l'atelier n'a **pas** de couples.

## Phase 1 — Importer et lire — faite (2026-09-20)

- Les trois tables **copiées telles quelles** dans `contenu/associations/` (`diff` identique ; le dossier est déployé et
  refusé au web, comme le reste de `contenu/`). `connaissances/` n'est pas déployé : les données que le code lit vivent
  donc ici.
- `src/Associations.php` (pur, sans base, sans l'extension `intl`) : `normaliser()` (sans accent ni casse, « Œillet
  d'Inde » → « oeillet d inde »), `memeNom()` (singulier et pluriel), `fiche()`, `succession()`, `relation()` (favorable,
  défavorable, discordant, inconnue — connue si l'une des deux plantes la cite), `entre()` (les relations connues d'une
  liste de plantes), `toxique()`. Vérifié : 28 contrôles (aucun couple discordant dans les données, un silence de l'autre
  côté lu comme connu, une paire absente rendue « inconnue »).
- Note datée ajoutée à `connaissances/cultures-associees/README.md` : les données sont maintenant dans ce dépôt.

## Décisions du porteur, 2026-09-20

1. **Le placement se fait par planche, et il concerne les plantes de production maraîchères ou potagères.** Les tables de
   Weinrich (potager, succession) servent à **placer** ces plantes dans les planches : regrouper ce qui s'entend bien,
   séparer ce qu'il vaut mieux éviter de mettre côte à côte.
2. **Pour un verger ou un jardin-forêt, l'association, ce sont les guildes ou consortiums — le cœur du projet Plantae.**
   Ce n'est donc **pas** la table du verger de Weinrich qui fait l'association d'une parcelle pérenne. Cette table reste une
   lecture de référence (que planter au pied d'un pommier, ses réserves), non le mécanisme.

## Ce que Plantae sait déjà des guildes — un fait mesuré

Plantae (`~/Projets/plantae-corpus`, publié sous `/plantae/`) exprime une guilde par des **besoins** et des **services** :
`exports/csv/guilde.csv`, 564 lignes, une par (plante, catégorie, relation) — **service rendu** ou **besoin requis**, avec
une intensité facultative. Sept catégories : azote, protection contre le vent, matière organique, attraction des
auxiliaires, attraction des pollinisateurs, limitation de la concurrence herbacée, ombrage. **Une guilde est une plante dont
un besoin rencontre le service d'une autre** — c'est ce qu'exprime déjà, côté atelier, `Scenario::FONCTIONS` (les vingt-quatre
choses qu'une plante fait pour le système : fixer l'azote, freiner le vent, bloquer l'herbe…), c'est-à-dire **la moitié
« service » de la guilde**.

**Couverture** : `guilde.csv` ne couvre que **179 des 1271 plantes** du catalogue, et **21 des 45 espèces du cadre**. Et la
clé existe déjà : le scénario nomme ses végétaux par le **nom scientifique** du catalogue de Plantae (`contenu/
catalogue-scenario.json`), qui est la clé de `guilde.csv`. (Au potager, c'est l'inverse : les tables de Weinrich parlent en
noms communs français.)

## Phase 2 — Lire les voisinages au potager *(aucun changement de schéma)*

Pour une parcelle **potager**, **par planche** : les relations connues entre les plantes qui la partagent
(`Associations::entre()`), celles qui vont bien, celles à éviter, les plantes **toxiques** signalées, les réserves dites.
Suppose que la parcelle potager **ait des plantes placées** : c'est le premier pas du chantier `chantier-plan-de-culture.md`
(aujourd'hui, aucune page ne compose un potager).

### Phase 2 — faite (2026-09-20)

La lecture par planche est dans `public/conception-potager.php` (voir `chantier-plan-de-culture.md` § Phase 1) : les relations connues
entre les cultures d'une même planche, les réserves dites, « pas de voisinage connu » sans ambiguïté.

## Phase 3 — Proposer le placement par planche

L'atelier **propose** de répartir les plantes de production sur les planches du plan (`nb_planches` est déjà calculé) :
regrouper les favorables, séparer les défavorables, dire ce qu'il ne parvient pas à régler ; la personne accepte, déplace, ignore.
Un **numéro de planche par plante** (colonne facultative sur `succession_ligne`, qui a déjà `demi_planche` : `Version::SCHEMA`
monte). Un algorithme **simple et lisible**, jamais une boîte noire ; une paire sans relation connue **ne compte pas** (inconnue
n'est pas neutre).

## Phase 4 — Les parcelles pérennes : les guildes et consortiums de Plantae

Pour un verger ou un jardin-forêt, **composer des consortiums** : que les besoins des plantes du scénario soient couverts par
les services des autres (azote, vent, matière organique, auxiliaires, pollinisateurs, herbes concurrentes, ombrage), et dire
ce qui reste **sans réponse**. Trois choses sont à définir : **comment l'atelier lit Plantae** (question ci-dessous), la couverture
(21 espèces du cadre sur 45 : le reste se dit « pas de guilde connue »), et le lien avec **les fonctions du scénario**, qui en sont
déjà la moitié. À rapprocher du critère du chantier « saut du chat » (des plantes de biomasse en quantité suffisante, des plantes de
démarrage) : ce sont des guildes vues du saut du chat.

**Décision du porteur (2026-09-20) : un instantané de `guilde.csv`** — l'atelier peut alors *calculer* (les besoins couverts, ce qui manque). Faite, la
part « données » :
- `bin/catalogue-plantae.py` exporte aussi `guilde.csv` (à côté de `plantes.csv`) vers **`contenu/guildes-plantae.json`** — 179 plantes, 564 lignes, la donnée
  telle que Plantae la donne, **jamais reclassée ici**. Le catalogue de noms est resté identique (aucun écart). **Il vieillit** : à réexporter quand Plantae
  change. Une dérogation assumée au principe « les critères agronomiques restent chez Plantae », dite dans le script.
- **`src/Guildes.php`** (pur) : `deLaPlante()` (services et besoins par catégorie ; un nom en cultivar se retrouve par son binôme), `categoriesAvecService()`, et
  **`bilan()`** — catégorie par catégorie, qui a un besoin, qui rend le service, quels besoins **un autre membre** couvre (une plante ne se rend pas service à
  elle-même), lesquels restent sans réponse, **quelles plantes n'ont pas de guilde connue**, et les catégories où Plantae ne connaît **aucun service** (la matière
  organique n'a que des besoins : un besoin y est « sans service connu », non « non couvert »). 21 contrôles.
- **Pas encore branché à une page** : la lecture dans le scénario d'une parcelle pérenne (composer un consortium, dire ce qui manque) reste à construire.
  Un point à trancher en la construisant : le rapprochement avec `Scenario::FONCTIONS` (les fonctions déclarées par la personne), sans reclasser la donnée de Plantae.

## Phase 5 — La succession du potager

`associations-succession.csv` : préculture et postculture (ou engrais vert) de 30 espèces — ce que l'on sème quand une planche se
libère. Se joint au chantier du potager (`chantier-plan-de-culture.md`).

## À trancher avec le porteur

1. ~~Comment l'atelier lit les guildes de Plantae.~~ **Tranché le 2026-09-20 : un instantané** (voir Phase 4). Rappel du choix : Le principe du chantier « scénario de plantation » était : *les critères
   agronomiques restent chez Plantae, aucun classement local n'est déduit* (le catalogue n'exporte que des **noms**). Les guildes
   sont des critères agronomiques. Trois voies : **(a)** un **instantané** de `guilde.csv` (564 lignes, 179 plantes) exporté par un
   script comme le catalogue, dans `contenu/`, relu par l'atelier — il peut *calculer* (besoins couverts, manques) mais peut vieillir ;
   **(b)** un **lien** vers la page de la plante chez Plantae — rien n'est copié, rien ne se calcule ici ; **(c)** les deux.
2. **Le verger de Weinrich** : le garde-t-on comme lecture de référence (au pied d'un pommier…) ou l'écarte-t-on ? Il est importé ;
   il ne sert pas au placement.

## Manière de procéder

Itérative et incrémentale, comme le chantier « saut du chat » : chaque phase tient debout et se déploie seule ; une
règle sans réponse sourcée ne se code pas. Règles du dépôt : gabarit « le bureau », aucun défilement, un repère est dit
avec son pourquoi et sa source, le lecteur ne peut rien écrire, le livret « L'atelier » se met à jour dans la même vague
(seulement ce qui est en production).
