# Les associations de plantes de production — plan

> **État au 2026-09-20 — Phase 1 (importer et lire) faite ; les Phases 2 à 5 ne sont pas commencées.**
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

## Phase 2 — Lire les voisinages dans le scénario *(aucun changement de schéma)*

Sur `conception-scenario.php`, pour **une parcelle**, un panneau « Voisinages » : pour chaque plante de production du
scénario, ce que les tables en disent (favorables, à éviter), et **les relations connues entre les plantes déjà
choisies** (`Associations::entre()`) — celles qui vont bien, celles à éloigner. Les plantes **toxiques** portent leur
mention. Les réserves ci-dessus s'affichent (Informations). Rien ne se refuse : c'est une lecture.

*À trancher avant* (question 2 ci-dessous) : comment une plante du scénario retrouve sa fiche — le nom saisi est libre
ou pris dans le catalogue de 1271 noms botaniques, quand les tables parlent en noms communs.

## Phase 3 — Proposer le placement *(le cœur de la demande ; suppose un modèle de placement)*

L'atelier **propose** où placer les plantes de production pour que les favorables voisinent et que les néfastes soient
séparés — la personne accepte, déplace, ignore. Ce qu'il faut pour cela **n'existe pas encore** : le scénario dit **où**
une plante est posée seulement par `emplacement` (planches de culture / bordure externe / bordure interne / autre), pas
**quelle planche**. Deux façons de modéliser, à trancher (question 1) :
- **par planche** : un numéro de planche par plante de production (colonne facultative, `Version::SCHEMA` monte) ; le
  voisinage est « même planche » ou « planches contiguës », ce que le plan de parcelle sait déjà dessiner ;
- **par emplacement** : sans schéma, plus grossier — deux plantes du même emplacement sont voisines.
Puis un **algorithme de proposition** simple et lisible (regrouper les favorables, séparer les défavorables, dire ce qui
ne se règle pas) — jamais une boîte noire.

## Phase 4 — Les parcelles pérennes : le verger et la tortue

`associations-verger.csv` : que planter **au pied** d'un pommier, d'un poirier… (13 porteurs et leurs compagnons), les
distances qu'elle donne (3 m entre framboisier et fruitier, 6 m autour de l'abricotier — à croiser avec les distances des
strates), et **la fatigue du sol** au remplacement d'une plante (voir ci-dessus). **Réserve propre à la syntropie** : la
source ne parle ni de syntropie ni de jardin-forêt ; ce qui se transporte, ce sont les couples, jamais la conduite.

## Phase 5 — La succession du potager

`associations-succession.csv` : préculture et postculture (ou engrais vert) de 30 espèces. **Elle se joint au chantier
« potager »** (`chantier-saut-du-chat.md` § Le potager) : composer la succession d'une parcelle « potager » (`succession_ligne`,
au schéma, aucune page ne l'écrit), avec le calendrier de plantation du plan de culture de Lopez.

## À trancher avec le porteur

1. **Le placement : par planche, ou par emplacement ?** (Phase 3.) Par planche est plus juste et plus utile ; il demande une
   colonne et une saisie de plus.
2. **Retrouver la fiche d'une plante.** Le nom saisi dans le scénario est libre ; les tables parlent en noms communs
   français. Par défaut : une comparaison sans accent ni casse, singulier ou pluriel (faite) ; le reste passe par le
   **nom usuel** du référentiel `espece` quand la plante y est. Une plante sans fiche se dit « pas de voisinage connu »,
   jamais « neutre ».
3. **Les parcelles pérennes en syntropie** : les couples du verger s'y appliquent-ils, avec leurs réserves, ou seulement au
   potager et au jardin-forêt ? (Phase 4.)

## Manière de procéder

Itérative et incrémentale, comme le chantier « saut du chat » : chaque phase tient debout et se déploie seule ; une
règle sans réponse sourcée ne se code pas. Règles du dépôt : gabarit « le bureau », aucun défilement, un repère est dit
avec son pourquoi et sa source, le lecteur ne peut rien écrire, le livret « L'atelier » se met à jour dans la même vague
(seulement ce qui est en production).
