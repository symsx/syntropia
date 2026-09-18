# Scénario de plantation — `conception-scenario.php`, base de végétaux comprise

Plan proposé le 2026-09-18, écrit sur le même modèle que
`chantier-carte-interactive.md` — voir CLAUDE.md § Point de reprise
pour l'état courant résumé.

**État** : ✅ Phase 1 (base de végétaux, 2026-09-18) — Phases 2 à 4
pas commencées, Phase 2 attend une décision sur l'accompagnement
jardin-forêt (§ Décisions tranchées).

## Contexte

`conception-scenario.php` est l'une des deux étapes volontairement
reportées de la refonte (avec `conception-materiel-et-eau.php`),
`pret` absent d'`etapes.js` depuis le début — parce qu'elle « demande
un vrai scénario en base, qui n'existe pas encore » (CLAUDE.md).

**Ce qui a changé, et qui déclenche ce chantier** : la formation Joala
vient d'être approfondie (`connaissances/formation-joala/
strates-et-lumiere.md`), avec un tableau chiffré des besoins en
lumière par strate et une trentaine d'espèces d'exemple classées —
exactement la pièce qui manquait pour donner un contenu réel à un
premier tableau de stratification, en complément du ch. 11 déjà présent
(`connaissances/livre-bienvenue-en-syntropie/
ch11-construire-un-scenario-planter-un-film.md`), qui donne la méthode
complète (tableau temps × strates, plan d'implantation, saut du chat,
étude de cas chiffrée sur une parcelle réelle).

**Ce que la recherche préalable a trouvé, et qui change le plan** :
`application/sql/01-syntropie.sql` porte déjà, intégralement commenté,
tout le schéma nécessaire — table `espece` (avec une colonne `systeme`
ENUM colonisation/accumulation/abondance), `scenario_ligne`,
`parcelle.conduite`/`site.conduite` (syntropique vs jardin-forêt),
`parcelle.production_principale`/`production_secondaire`. **Ce schéma
est déjà porté à l'identique dans `sql/01-syntropia.sql`**, depuis un
chantier antérieur — sans qu'aucune page ne l'utilise encore.

Mais l'exploration du code de référence (`src/Scenario.php`,
`src/Web/Scenarios.php`, `public/scenario.php`, `bin/
catalogue-scenario.py`) montre que **la table `espece` n'y est jamais
requêtée** : le formulaire de saisie d'un végétal est un simple champ
texte, avec une liste de suggestion HTML (`<datalist>`) alimentée par
un export de noms seuls depuis Plantae (1271 noms, sans aucune donnée
agronomique — *« Les critères agronomiques restent chez Plantae. Aucun
classement local n'est déduit »*, dit le script lui-même). **La table
`espece` est donc une infrastructure prévue mais jamais construite,
même côté référence.** Construire une vraie base de végétaux dans
syntropia n'est donc pas un simple port : c'est un morceau que la
référence elle-même n'a pas fini.

## Décisions tranchées avant de coder

- **La base de végétaux est un vrai chantier, distinct de la page du
  scénario** — Phase 1 ci-dessous — précisément parce qu'elle n'existe
  nulle part encore, contrairement à tout ce qui a été porté cette
  session jusqu'ici. Elle vit dans la table `espece` déjà prête, en
  deux niveaux : un **référentiel curaté** (`site_id = 0`, quelques
  dizaines d'espèces avec système/strate/rôle réels, sourcées du
  travail qu'on vient de faire sur `connaissances/`) et un **filet de
  noms seuls** venu d'un export Plantae (comme la référence), pour
  toute espèce pas encore curatée. Le formulaire du scénario propose
  toujours les deux : nom libre accepté, suggestions enrichies quand
  l'espèce est connue.
- **Oui, connaître le système (colonisation / accumulation /
  abondance) d'une espèce est utile** — la colonne existe déjà dans le
  schéma, côté référence comme côté syntropia, ce qui n'est pas un
  hasard : un scénario mélange délibérément des espèces de systèmes
  différents (le cas d'école du ch. 11 pose l'eucalyptus et le mûrier
  blanc — plutôt abondance, longévifs — à côté du sureau, du chalef et
  du goji — accumulation, biomasse, courte durée). Le système donné
  par `systemes-et-abondance.md` (qu'on vient d'enrichir) devient un
  filtre et un repère pédagogique dans le formulaire, pas une
  contrainte : une espèce garde un seul système, mais peut jouer
  plusieurs rôles.
- **Les deux conduites (syntropique / jardin-forêt) sont gardées**,
  parce que le schéma déjà porté les gardait déjà (`ENUM('syntropique',
  'jardin-foret')` sur `site` et `parcelle`, commentaires inclus) — pas
  une extension de périmètre, une continuité avec un choix déjà fait.
  **Question ouverte, à trancher avant la Phase 2** : le corpus
  jardin-forêt de `connaissances/foret-jardin/` (Crawford) existe déjà
  et couvre neuf strates par hauteur/place — mais rien dans ce qu'on
  vient de travailler (Joala, ch. 11) ne concerne le jardin-forêt.
  Construire les deux conduites dès la Phase 2 double le travail de
  validation (deux vocabulaires de strate, deux jeux de textes d'aide)
  pour un mode que ce chantier ne vient pas nourrir. **Proposition** :
  Phase 2 porte l'énumération `Conduite` en entier (les deux valeurs,
  le schéma les a déjà), mais l'UI et les textes d'aide de la Phase 4
  se concentrent sur le mode syntropique — le jardin-forêt reste
  sélectionnable et fonctionnel avec son vocabulaire de strates, mais
  sans le même niveau de contenu d'accompagnement avant qu'un corpus
  jardin-forêt dédié ne soit travaillé. **Confirmé par le porteur le
  2026-09-18** : les deux conduites, l'aide sur le syntropique seulement.
- **`plan.php`/`ImplantationBordures` restent hors périmètre**, comme
  déjà tranché pour la carte interactive — 795 lignes à eux deux côté
  référence, un chantier séparé sur la persistance de `parcelle.plan`.
  Le scénario fonctionne sans : la quantité de plants reste saisie à
  la main (`scenario_ligne.nb_pieds`), le mode calculé
  (`quantite_mode = plan_bordure`) est prévu par le schéma mais pas
  branché ici.
- **La conception « productions de la parcelle »** se source depuis
  `reponse.objectifs_productions` (`definition-objectifs.php`, déjà en
  base) plutôt que la table `reve`/`syntheses` de la référence, qui
  n'existe pas dans syntropia — syntropia n'a qu'un champ texte libre
  là où la référence distingue principales/secondaires. Le
  découpage en productions choisies se fait donc uniquement côté
  parcelle (`parcelle.production_principale`/`production_secondaire`,
  déjà en base), sans la double liste de souhaits de la référence.
- **`Marquage`/citations entre crochets** (`[Théry]`, `[Joala]`) ne
  sont pas portées comme mécanisme — syntropia n'a pas cette classe et
  aucune autre page n'en a eu besoin ; les textes d'aide citent leur
  source en prose, comme partout ailleurs dans ce dépôt.

## Vue d'ensemble des quatre phases

```
Phase 1  ✅ La base de végétaux — table `espece`, backend, seed curaté + export Plantae
Phase 2  ✅ Conduite et héritage site/parcelle — `syntropique`/`jardin-foret`
Phase 3  ✅ Backend du scénario — `scenario_ligne`, validation, positions temporelles
Phase 4  ✅ `conception-scenario.php` — la page, sur le bureau, pret:true
```

Chaque phase est livrable et testable seule. La Phase 4 dépend des
trois précédentes ; les Phases 1 et 2 n'ont pas de dépendance entre
elles et peuvent s'inverser sans perte.

### Phase 1 — La base de végétaux ✅ (fait le 2026-09-18)

**But** : la table `espece` (déjà en base, jamais utilisée) devient un
vrai référentiel consultable, avec un premier jeu d'espèces curatées.

**Fichiers**
- Nouveau `src/Web/Especes.php` — dans le moule de `Sites.php`/
  `Parcelles.php` : `toutes(siteId)` (fusionne cadre `site_id=0` et
  surcharges locales, la surcharge prime), `parNom()`, `parId()`,
  `enregistrer()` (crée ou met à jour la surcharge locale, `INSERT ...
  ON DUPLICATE KEY UPDATE`), `retirer()`. Pas de suppression du cadre
  depuis l'interface — `retirer()` refuse explicitement tout
  `siteId <= 0`, pas seulement par la clause `WHERE` (voir l'écart
  ci-dessous, trouvé en testant).
- Nouveau `bin/catalogue-plantae.py` — port proche du script de
  référence (19 lignes) : lit l'export CSV de Plantae
  (`plantae-corpus/exports/csv/plantes.csv`, déjà présent en local,
  1271 espèces), écrit `contenu/catalogue-scenario.json` (noms
  scientifiques + communs, rien d'agronomique — commis au dépôt, comme
  la référence le fait, puisque la source vit hors de tout dépôt
  déployé).
- Nouveau `sql/02-especes-seed.sql` — 46 lignes `INSERT IGNORE INTO
  espece (site_id=0, ...)`, curatées à la main : la grille
  colonisation/accumulation/abondance de `systemes-et-abondance.md`
  (8 espèces d'accumulation, 5 d'abondance — la colonisation,
  mousses/lichens/cactus, n'est pas seedée, peu utile à un scénario de
  jardin cultivé), l'étude de cas chiffrée du ch. 11 (« Les
  eucalyptus » — 12 espèces avec entrée/sortie de strate réelles), et
  les exemples Dordogne de `strates-et-lumiere.md` (17 espèces). Ajouté
  à `bin/dev.py` § `SCHEMAS`, en DML séparé du schéma pur de
  `01-syntropia.sql` — rejoué à chaque `bin/dev.py init`, idempotent
  (`INSERT IGNORE`, ne réécrase jamais une correction faite depuis
  l'interface).

**Écart trouvé en cours de route, avant tout code** : le porteur a
signalé qu'une variété peut changer la strate ou le rôle du tout au
tout — le fraisier des bois et le fraisier du commerce (déjà deux
espèces distinctes, donc déjà correct) mais surtout le tournesol
classique et le tournesol conduit à une fleur, seul pour la biomasse.
La clé unique `(site_id, nom_scientifique)` ne pouvait pas porter ça.
Ajout d'une colonne `variete` (`NOT NULL DEFAULT ''`, même raison que
`site_id = 0` — un index UNIQUE traiterait chaque `NULL` comme
distinct) et `uq_espece` étendue à `(site_id, nom_scientifique,
variete)`, avant que quoi que ce soit d'autre ne s'appuie dessus.
`Especes::parNom()`/`enregistrer()` prennent `$variete` en paramètre
explicite. Seedé avec `Helianthus annuus` en deux lignes (`''` générique
— 4,50 m en année 1 dans le ch. 11 — et `'à une fleur'`, sourcée
directement du porteur, `statut_local` le dit).

**Bug trouvé en testant, corrigé avant que quoi que ce soit n'en
dépende** : `retirer()` ne protégeait le cadre que par sa clause
`WHERE site_id = ?` — un appel `retirer($id, 0)` matchait cette clause
comme n'importe quel site réel et supprimait donc une ligne du cadre.
Le test l'a démontré en le supprimant réellement (`Ribes nigrum`,
restauré par un nouveau `bin/dev.py init`). Corrigé par un refus
explicite (`if ($siteId <= 0) { return; }`) avant toute requête —
**ce n'est pas la clause SQL qui protège le cadre, c'est ce
contrôle**, comme le dit maintenant le commentaire de la méthode.

**Pourquoi en premier** : c'est la seule pièce que rien d'autre ne
bloque, et la Phase 4 en a besoin pour son formulaire — mieux vaut
l'avoir testée seule au préalable, comme `Calques.php` avant la page de
la carte.

**Test** : vérifié directement (pas de page encore, comme pour
`Calques.php` en Phase 4 de la carte — un script isolé, exécuté avec
les variables d'environnement de `config/local.env`, jamais `php`
appelé nu). Lister les 46 espèces curatées ; une surcharge locale
(`enregistrer()` sur un site de test) prime bien sur le cadre pour le
même `(nom_scientifique, variete)` ; un autre site continue de lire le
cadre, inchangé ; une espèce absente des deux niveaux renvoie `null`
(comportement attendu — reste saisissable en texte libre côté
formulaire de la Phase 4, aucune contrainte de clé étrangère ne
l'empêche) ; `retirer()` sur la surcharge la fait disparaître et rend
la lecture au cadre ; `retirer()` avec `siteId=0` est refusé (après
correction du bug ci-dessus). L'export Plantae → JSON tourne et produit
1271 entrées, comme la référence.

### Phase 2 — Conduite et héritage site/parcelle ✅ (fait le 2026-09-18)

**But** : `site.conduite`/`parcelle.conduite` (déjà en base,
`NULL` par défaut) deviennent choisissables, avec la résolution « le
plus précis gagne ».

**Fichiers**
- Nouveau `src/Conduite.php` — port proche de l'énumération de
  référence (`Syntropique`/`JardinForet`, 278 lignes) : `libelle()`,
  `explication()`, `critereDeStrate()`, `strates()` (les quatre
  syntropiques ; les neuf jardin-forêt portées mais non détaillées en
  aide tant que la question ouverte ci-dessus n'est pas tranchée),
  `scenarioADesTemps()`, `uniteDeConception()`, `sensDeLaTaille()`,
  `depuis()`, `strateValide()`.
- Nouveau `src/Web/Heritage.php` — **très réduit** par rapport à la
  référence (113 lignes, `PROPRES_A_LA_PARCELLE` avec dix colonnes
  sol/exposition) : une seule méthode, `resoudre(parcelle, site,
  'conduite')`, pas de liste étendue — le reste de l'héritage
  site/parcelle (sol, exposition) reste hors périmètre, comme
  `Parcelles.php` le note déjà depuis la Phase 3 de la carte
  interactive.
- `public/definition-site.php` (modifié) — ajoute le choix de
  `site.conduite`, avec l'explication de chaque conduite
  (`Conduite::explication()`).
- `public/conception-parcelles.php` (modifié) — ajoute le choix de
  `parcelle.conduite` (facultatif, hérite du site si vide), dans le
  panneau où les dimensions se saisissent déjà.

**Pourquoi ici** : la Phase 4 a besoin de `Conduite::strates()` pour
construire ses colonnes de tableau — sans conduite résolue, pas de
vocabulaire de strates à proposer.

**Test** : poser la conduite du site, vérifier qu'une parcelle sans
conduite propre hérite bien de celle du site, qu'une parcelle avec sa
propre conduite prime, que les deux vocabulaires de strates
(quatre/neuf) sont bien ceux attendus selon la conduite résolue.

**Fait** : `src/Conduite.php` (port réduit : `libelle`, `explication`,
`critereDeStrate`, `strates`, `scenarioADesTemps`, `uniteDeConception`,
`sensDeLaTaille`, `depuis`, `strateValide`, `toutesLesStrates`, `toutes`, et
`reserve()` — le mot honnête à côté du choix : les textes d'aide sont écrits
pour la syntropie), `src/Web/Heritage.php` (`resoudre()`, `conduite()`),
`Sites::enregistrerConduite()`, `Parcelles::enregistrerPlan()` (clé
`conduite` : vide = hérite, absente = inchangée, sous le même verrou de
version), le choix du site sur `definition-site.php` et celui de la parcelle
dans le panneau « Plan » de `conception-parcelles.php`, avec son résumé
(« 9 strates, par la place et la hauteur adulte »).

**Écarts avec ce qui était annoncé**

- Le choix de la parcelle n'est **pas** « dans le panneau où les dimensions se
  saisissent » sous forme de champ à part : il s'y range en tête, au-dessus de
  « La parcelle », et part avec « Enregistrer le plan » — un seul bouton, un
  seul verrou de version, plutôt qu'un second formulaire pour une seule colonne.
- **Sur `definition-site.php`, le bloc est compacté sur téléphone** : titre et
  aide y disparaissent (le texte passe dans le panneau Informations). Sans
  cela, « Continuer » sortait du bureau à 412×915, où la page tenait — le bloc
  pesait 137 px sur les colonnes empilées.
- Les strates de `Conduite` sont comparées à l'énumération SQL de
  `scenario_ligne.strate` : identiques, les treize (4 syntropiques, 9
  jardin-forêt).

**Vérifié** : en CLI, sur la base locale (`config/local.env` exporté) — parcelle
vide + site jardin-forêt → jardin-forêt/site ; parcelle propre prime ; aucun
choix → syntropique/`aucun` ; valeur inconnue sans exception ; quatre et neuf
strates ; `1_canopee` valide en jardin-forêt et refusée en syntropie, `haute`
l'inverse ; axe du temps oui/non. En CDP — **site** : « pas encore choisie »,
l'aide suit le choix, enregistrement à « Continuer », rechargement fidèle,
valeur forgée refusée avec son message, retour à vide → `NULL` ; **parcelle** :
« Comme le site — pas encore choisie », puis « Comme le site — Syntropie »,
choix propre enregistré et rechargé sans toucher l'autre parcelle, retour à
« comme le site » → `NULL`, valeur forgée refusée, **conflit de version** (un
autre membre change la conduite entre-temps → « Le plan a changé »,
sa valeur conservée) ; **lecteur** : champ grisé, POST forcé sans effet.

**Constat préexistant, non corrigé — à décider** : `definition-site.php`
déborde déjà sur les écrans courts. À 1280×720 le contenu de la colonne du lieu
(640 px) dépasse le bureau (622 px) : l'en-tête passe **sous** le bandeau et
« Continuer » chevauche le pied de page — même mesure avec et sans le bloc de
conduite (708 px pour un bureau qui finit à 677 px). Idem à 1024×600, 412×800
et 360×740. Une capture réelle le montre ; c'est une des pages les plus
chargées du parcours. Une piste : les deux groupes en onglets CSS pur (comme
`compte.php`) dès que la hauteur manque.

### Phase 3 — Backend du scénario ✅ (fait le 2026-09-18)

**But** : la table `scenario_ligne` (déjà en base) devient écrivable,
avec la même logique de validation et de position temporelle que la
référence.

**Fichiers**
- Nouveau `src/Scenario.php` — port proche (209 lignes) :
  `PERIODES` (7 phases, placenta1 → climax), `FONCTIONS` (24 fonctions
  d'accompagnement), `EMPLACEMENTS`, `souhaits()` (segmentation d'un
  texte libre en items), `formulaire()`/`valider()` (position 0-28,
  encodage période × quart), `details()` (JSON de colonnes annexes).
  Adaptation : `souhaits()` s'applique à `reponse.objectifs_productions`
  (un seul champ, pas principales/secondaires séparées — voir
  Décisions tranchées) plutôt qu'aux synthèses de `Reves`.
- Nouveau `src/Web/Scenarios.php` — port réduit (115 lignes dans la
  référence, sans la méthode `bordures()` liée à `ImplantationBordures`
  qui reste hors périmètre) : `productions()` (enregistre le choix de
  productions sur la parcelle), `enregistrer()` (ajoute/modifie/retire
  une ligne de `scenario_ligne`, avec verrou optimiste par version
  hachée — même mécanisme que `Calques::modifier()`).

**Pourquoi ici** : dépend de la Phase 2 (`Conduite::strates()` pour
valider la strate choisie) ; la Phase 4 s'appuie directement dessus.

**Test** : au `curl`, avant toute UI — ajouter un végétal (espèce
libre ou curatée), vérifier le calcul de position temporelle, modifier,
retirer, vérifier le verrou de version (une modification concurrente
est refusée avec un message clair), vérifier qu'une strate hors
vocabulaire de la conduite résolue est rejetée.

**Fait** : `src/Scenario.php`, `src/Web/Scenarios.php`, `Parcelles::scenario()`.

**Écarts avec ce qui était annoncé, tranchés en codant**

- **Pas de `curl` : il n'y a pas d'endpoint.** La référence n'en a pas non plus
  — `Scenarios` est appelée par la page, pas par une API. Le test est donc un
  script PHP sur la base **locale** (82 vérifications), avec un garde-fou qui
  s'arrête si l'hôte n'est pas `127.0.0.1` : `config/base.php` l'emporte sur
  `local.env` (voir la mémoire du projet), un `php` lancé sans exporter
  `local.env` viserait la production.
- **`Scenario::selectionDe()` et `versionProductions()`** — la référence
  recalculait ces deux choses dans la page ; elles vivent ici, avec la règle,
  pour que la Phase 4 et le service disent la même chose du même jeton.
- **En jardin-forêt, la saisie du temps n'est pas lue** : la plante est là de
  `placenta1` au `climax` (repères 0 à 28, sans retrait), par
  `Conduite::scenarioADesTemps()`. La référence exigeait quand même un début
  et une fin. Ici : jamais devinés, jamais demandés.
- **`souhaitsDuSite()`** : la référence tirait deux listes (principales,
  secondaires) d'un « rêve » ; syntropia n'a qu'un champ,
  `reponse.objectifs_productions`. Ce sont les réponses **du participant
  connecté**, à défaut celles **du porteur** — un contributeur qui n'a pas
  encore répondu ne trouve pas un choix vide. La distinction principale /
  secondaire se fait parcelle par parcelle, dans `productions()`.
- **`productions()` refuse plutôt que tronquer** : `production_principale` et
  `production_secondaire` sont des `VARCHAR(190)` où les souhaits tiennent un
  par ligne — trop long, une virgule dans un souhait (il se scinderait à la
  relecture), un souhait à la fois principal et secondaire, ou aucun principal :
  autant de refus avec leur message. Vider les secondaires écrit `NULL`.
- **`souhaits()` garde la première graphie** (« tomates » puis « TOMATES » reste
  « tomates ») ; la référence gardait la dernière.
- Quantités et `quantite_mode` : `manuel` seulement, comme annoncé.

**Vérifié** (script sur la base locale, comptes et projet créés puis supprimés) :
segmentation des souhaits ; positions et libellés ; **23 refus de saisie**
(strate d'un autre vocabulaire, sans nom, ni production ni fonction, fonction
inconnue, début ≥ fin, repères hors 0-28, retrait hors présence, quantité et
espacement hors bornes, texte trop long, emplacement/couleur/origine inconnus) ;
ordre de remplissage (principale 1, secondaire 2, service ≤ placenta3 → 4,
sinon 3) et couleurs par catégorie ; jardin-forêt sans temps ; productions
(lecteur, étranger, parcelle inexistante, version périmée, écriture d'un
contributeur, parcelle voisine intacte) ; végétaux (ajout par porteur et
contributeur, lecteur et étranger refusés, **verrou de version** — la
modification concurrente est refusée et ne change rien —, renommage refusé
sans confirmation, `details` fusionnés et non remplacés, ligne d'une autre
parcelle inaccessible par son identifiant, retrait, suppression en cascade
avec la parcelle).

### Phase 4 — `conception-scenario.php` — la page ✅ (fait le 2026-09-18)

**But** : la page elle-même, sur le gabarit « le bureau » — bascule
`pret: true`.

**Fichiers**
- Nouveau `public/conception-scenario.php` — sur le moule des pages
  déjà construites cette session (bandeau d'outils si pertinent,
  panneau Données pour le formulaire d'ajout de végétal, panneau
  Informations pour le guide de composition). Contenu porté de
  `public/scenario.php` (336 lignes) : le choix des productions de la
  parcelle (depuis `objectifs_productions`), le tableau temps × strates
  (colonnes = les 7 périodes, lignes = les strates de la conduite
  résolue), le formulaire d'ajout/modification d'un végétal (nom avec
  suggestion `espece` + Plantae, strate, production visée, fonctions
  d'accompagnement, présence temporelle, quantité, notes).
- `public/assets/js/etapes.js` (modifié) — `conception-scenario` passe
  `pret: true`, en toute fin de phase seulement.

**Différence assumée avec la référence** : pas de page `.scroll`/
impression dédiée — le tableau doit tenir dans le bureau sans défiler,
contrainte déjà rencontrée et résolue ailleurs (livrets, petits
écrans) ; à revoir concrètement une fois le tableau construit, sa
largeur (7 colonnes + 1) est le vrai risque.

**Test** : composer un scénario complet sur une parcelle de test —
choisir des productions, ajouter plusieurs végétaux dans différentes
strates et périodes, modifier, retirer, vérifier la persistance au
rechargement, vérifier le refus d'écriture pour un compte lecture
seule, vérifier le rendu à 412px (bureau sans défilement, tableau
temps × strates étroit — le point le plus probable de régression).

**Fait** : `public/conception-scenario.php` ; `etapes.js` (`pret: true`) ;
`conception-parcelles.php` (le lien « Le scénario » du panneau gauche devient
réel).

**Le risque annoncé — « la largeur du tableau »** est levé par deux vues :
- **syntropie** : une **frise SVG** (`viewBox` de 1000 de large, sept périodes,
  une voie de 28 unités par plante, hachures pour le retrait progressif). Elle
  garde son rapport et se réduit pour tenir dans le bureau, quel que soit le
  nombre de végétaux — à 1024×600 avec 17 végétaux, le texte des barres tombe à
  ≈ 9 px, encore lisible ; la bulle (`Gabarit.bulle`) donne le détail ;
- **téléphone (< 40 rem) et jardin-forêt** : une **vue par strate en
  pastilles**, dont la taille de police se réduit jusqu'à tenir (`ajuster()`,
  plancher 7 px) — un tableau à sept colonnes ne se lit pas à 412 px, une
  frise réduite non plus.

**Écarts avec ce qui était annoncé**

- **La saisie est dans le panneau Données**, pas dans la page : deux onglets
  (un végétal ; les productions de la parcelle), en CSS pur comme
  `compte.php`. Le guide de composition est dans le panneau Informations.
- **Aucun JavaScript pour éditer** : chaque bande est un lien
  `?parcelle=…&vegetal=…`, le formulaire est rendu par le serveur et le panneau
  s'ouvre seul. La seule logique client : ouvrir le panneau, la bulle, et
  l'ajustement des pastilles.
- **« Une autre production »** : la référence ne proposait que les souhaits du
  rêve. Ici, un porteur qui n'a rien écrit dans Objectifs se retrouvait devant
  un choix vide — une ligne libre l'évite (validée par `Scenarios::productions()`).
- **Des végétaux à « reclasser »** : si la conduite d'une parcelle change alors
  qu'elle porte des végétaux d'un autre vocabulaire de strates, ils apparaissent
  sous une ligne « À reclasser », modifiables pour leur donner une strate valide.
- Pas de bouton « Imprimer » ni de page d'impression (la référence en avait
  un) : hors périmètre, et un tableau qui tient à l'écran s'imprime déjà.
- Pas de « quantité calculée depuis le plan » : `quantite_mode` reste `manuel`.

**Vérifié** — CDP sur la base locale : voir CLAUDE.md § Point de reprise pour
la liste ; trois défauts de mise en page trouvés en mesurant et corrigés (le
bureau centre son contenu, donc rien ne pouvait se réduire ; l'onglet
« Informations » recouvrait l'en-tête ; un compte sans parcelle voyait une
page vide).

## Chantier terminé

Les quatre phases sont faites (2026-09-18). **Reste, hors plan** : filtrer les
suggestions d'espèces par rusticité — la zone USDA du site existe, mais `espece`
ne porte aucune rusticité (elle vit chez Plantae : jointure à faire, ou données
à curater) ;
proposer la strate d'une espèce connue (`espece.strates_possibles`) ; le mode
« quantité calculée depuis le plan de bordures » ; l'aide propre au
jardin-forêt. **Déployé le 2026-09-18.**

## Ce que ce plan exclut explicitement

- `plan.php`/`ImplantationBordures` — persistance visuelle du plan de
  plantation, concurrence optimiste sur `parcelle.plan`. Chantier
  séparé, déjà exclu pour la carte interactive.
- Le corpus jardin-forêt complet dans l'aide de la Phase 4 — la
  conduite reste choisissable et fonctionnelle, mais sans le même
  niveau d'accompagnement tant qu'un travail dédié sur
  `connaissances/foret-jardin/` n'a pas eu lieu pour ce chantier.
- Toute déduction automatique du `systeme` d'une espèce non curatée —
  reste `NULL`, jamais deviné.
- La synchronisation vivante avec Plantae (API, base partagée) — reste
  un export ponctuel, comme la référence.

## Fichiers critiques

- `sql/01-syntropia.sql` — `espece`, `scenario_ligne`,
  `site.conduite`/`parcelle.conduite`,
  `parcelle.production_principale`/`production_secondaire` : déjà
  prêtes, à ne pas modifier sans relire ce plan.
- `connaissances/formation-joala/strates-et-lumiere.md` et
  `connaissances/livre-bienvenue-en-syntropie/
  ch11-construire-un-scenario-planter-un-film.md` — la méthode et les
  données de base du tableau de stratification.
- `connaissances/formation-joala/systemes-et-abondance.md` — les
  espèces par système, pour le seed de la Phase 1.
- `application/src/Conduite.php`, `application/src/Scenario.php`,
  `application/src/Web/Scenarios.php`, `application/public/
  scenario.php`, `application/bin/catalogue-scenario.py` (dépôt de
  référence) — cœur logique du portage, Phases 2 à 4.

## Vérification globale

Après chaque phase, sur `bin/dev.py serve` : le parcours reste
navigable sans lien mort, aucune régression sur les phases déjà
construites. Une fois la Phase 4 terminée et `pret: true` posé,
vérifier qu'`etapes.js` reste cohérent et que le panneau gauche
propose bien le scénario comme étape suivante de la phase Conception.
