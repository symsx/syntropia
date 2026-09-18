# Le plan de la parcelle — persistance, réplique partielle et assumée

## Contexte

`conception-parcelles.php` dessine un plan de parcelle (longueur, largeur,
quatre bordures externes, sens des planches, planches calculées) entièrement
côté client depuis la Phase 3 du chantier « carte interactive du terrain » —
choix posé alors, documenté dans `architecture-technique.md` : *« Le plan SVG
(bordures, planches) reste un prototype côté client, volontairement —
`parcelle.plan` non branché, chantier séparé »*. Rien de ce panneau ne
survit à un rechargement : longueur et largeur retombent toujours sur
12,00 × 8,00 m, les bordures sur 0,40 m, le sens sur nord-sud — signalé par
le porteur le 2026-09-18 sur une vraie parcelle (« P1 — potager fait
toujours 8 × 12 m, on dirait que ça ne s'enregistre pas »).

Recherche préalable menée sur le dépôt de référence
(`/home/sylvain/projets/syntropie/application/`, agent Explore, tous les
fichiers cités lus en entier) avant d'écrire ce plan — signatures, forme du
JSON et mécanisme de concurrence ci-dessous sont vérifiés, pas supposés.
**Constat central : la référence est nettement plus riche que le prototype
actuel.** `src/Plan.php` (656 lignes) porte une géométrie à bordures
*externes et internes* (les « huit côtés » déjà annoncés dans le commentaire
de `sql/01-syntropia.sql` sur la colonne `plan`), des rives, un rattrapage,
un nombre de lignes de semis par planche, et une classe séparée
`ImplantationBordures` (139 lignes) qui place des rangs de vivaces le long
des bordures avec un algorithme d'espacement au millimètre — le tout croisé
avec `scenario_ligne`, table qui n'existe encore nulle part dans syntropia
puisque le chantier « scénario de plantation » n'a fait que sa Phase 1 (la
base de végétaux). Répliquer tout cela maintenant reviendrait à construire
le chantier scénario en avance, et sans lui. Ce plan ne le fait pas — voir
§ Décisions.

## Décisions tranchées avant de coder

- **On persiste ce que le prototype dessine déjà, rien de plus.** Longueur,
  largeur, les quatre bordures externes (actif + largeur), le sens des
  planches. **Explicitement hors phase 1** : bordures internes, rives,
  rattrapage, un nombre de lignes réglable par planche (le modèle actuel,
  planche de largeur fixe + passe-pied fixe, reste tel quel), et tout ce qui
  assigne une espèce (semis biomasse annuelle, pesée de graines,
  implantations en bordure) — ces trois derniers points supposent
  `scenario_ligne`, qui n'existe pas encore ici. Si le porteur veut aller
  jusque-là, ce sera après (ou en même temps que) la Phase 3 de
  `chantier-scenario-plantation.md`, pas avant.
- **Aucune migration de schéma.** Vérifié colonne par colonne : `longueur_m`,
  `largeur_m`, `plan` (TEXT), `orientation_lignes` (VARCHAR(60)) existent
  déjà sur `parcelle`, identiques à la référence (comparaison faite,
  identiques au caractère près). Seul le code applicatif manque.
- **Le verrou optimiste se porte à l'identique de la référence**, parce
  qu'il est déjà éprouvé et que le principe (une personne peut avoir la
  page ouverte, une autre modifier entretemps) vaut ici comme là-bas :
  `hash('sha256', json_encode($ligneParcelle))`, un champ caché
  `version_plan` recalculé à chaque affichage, `hash_equals()` avant
  d'écrire, `SELECT … FOR UPDATE` dans une transaction. Message si le hash
  ne correspond plus : *« Le plan a changé, rechargez la page avant de
  modifier. »* — le geste normal, jamais un écrasement silencieux.
- **`orientation_lignes` sans héritage site↔parcelle.** La référence lit ce
  champ via `Heritage::resoudre()` (site si la parcelle ne dit rien) —
  `Heritage.php` n'existe pas ici, hors périmètre depuis la Phase 3 du
  chantier carte (« l'héritage site↔parcelle reste hors périmètre »). On
  écrit donc directement sur la parcelle, sans hériter de rien — une
  simplification assumée, pas un oubli. La référence tolère aussi un champ
  libre (« suivre les courbes de niveau » par exemple) avec repli deviné ;
  ici, seuls les deux choix déjà dans l'interface (nord-sud / est-ouest)
  sont acceptés côté serveur.
- **La forme de la parcelle reste un rectangle.** `parcelle.forme` (enum
  zebre/abeille/liane/autre) existe en base, la référence l'utilise pour des
  tracés non rectangulaires — hors périmètre ici, personne ne l'a demandé et
  le prototype actuel ne dessine qu'un rectangle.
- **`nb_planches` reste un champ saisi, pas encore un résultat calculé et
  réécrit.** Côté référence, `nb_planches` est une **sortie** de
  `Plan::geometrie()`, réécrite par le serveur à chaque enregistrement —
  côté cible aujourd'hui, c'est un champ que le porteur tape à la main dans
  « Pour la carte du terrain » (`Parcelles::enregistrer()`, déjà en place,
  utilisé par `Calques::parcellesAPlacer()` pour poser un rectangle sur la
  carte interactive). Faire converger les deux — le plan calcule
  `nb_planches`, la carte le lit tel quel, plus de champ à remplir à la
  main deux fois — est une vraie simplification, mais qui touche un
  mécanisme déjà construit et vérifié (Phase 4/5 du chantier carte). Posée
  en Phase 2 ci-dessous, séparée de la persistance elle-même pour ne pas
  mélanger les risques.
- **Le glissement d'une bordure reste entièrement client**, comme
  aujourd'hui (`redessiner()` déjà correct, affiné cette session même :
  butée contre le bord opposé, mesures qui se recalent). La référence fait
  un aller-retour serveur à chaque relâchement (`action=apercu`, re-rendu
  HTML) parce que sa géométrie est plus riche (rives, rattrapage) et vit
  côté serveur par défaut. Rien ici ne justifie de perdre la fluidité déjà
  acquise pour un calcul que le client sait déjà faire correctement.

## Vue d'ensemble des phases

```
Phase 1  Parcelles::enregistrerPlan() — bordures + sens + verrou optimiste
Phase 2  Convergence avec la carte : nb_planches calculé, plus saisi à la main
Phase 3  Rattrapage homogène + planches de bord à portée simple
Phase 4  Bordure interne — deuxième anneau, biomasse plutôt que bloque-herbe
```

Phase 2 est franchement séparable — Phase 1 seule répond déjà entièrement au
signalement du porteur. Phases 3 et 4 sont posées le 2026-09-18 suite à deux
demandes du porteur après usage réel de la Phase 1 : une répartition plus
juste des planches (Phase 3), et une deuxième bordure fidèle aux « tortues »
d'Anaëlle Théry (Phase 4, décision tranchée avec le porteur après
vérification contre `connaissances/livre-bienvenue-en-syntropie/
ch14-une-histoire-de-tortue.md` — voir son § pour le détail de ce qui s'y
trouve et ce qui en diffère).

**Les quatre phases sont faites, chantier terminé le 2026-09-18.**

### Phase 1 — Persister le plan (bordures, sens, dimensions) — ✅ fait le 2026-09-18

**But** : ce qui est dessiné à l'écran survit à un rechargement, avec un
avertissement propre en cas de modification concurrente.

**Fichiers**

- `src/Web/Parcelles.php` (modifié) :
  - `enregistrer()` (déjà là) gagne `largeur_m` dans sa whitelist, exactement
    comme `longueur_m` y est déjà — c'est la moitié du bug signalé : la
    colonne existe, rien ne l'écrit.
  - Nouvelle méthode `enregistrerPlan(int $id, int $siteId, array $valeurs,
    string $versionRecue): array` — whitelist `bordures` (4 côtés,
    actif+largeur_m chacun, largeur bornée 0,05–5 m comme la référence),
    `orientation_lignes` (`nord-sud`/`est-ouest` seulement, rien d'autre
    accepté). Transaction, `SELECT … FOR UPDATE`, `hash_equals()` contre le
    hash de la ligne `parcelle` actuelle, `UPDATE` si ça correspond,
    exception explicite sinon. `plan` stocke `{"bordures":{"externe":{
    "nord":{"actif":bool,"largeur_m":float}, "est":…, "sud":…, "ouest":…}}}`
    — même clé `externe` que la référence (qui a aussi `interne`, absente
    ici), pour ne pas fermer la porte à une bordure interne plus tard sans
    changer la forme du JSON déjà écrit.
  - Nouvelle méthode statique `versionPlan(array $parcelle): string` — port
    direct de `ImplantationBordures::version()`, réduit à la seule ligne
    `parcelle` (pas de `scenario_ligne` à hasher, il n'existe pas encore).
- `public/conception-parcelles.php` (modifié) :
  - Au chargement, lit `$parcelleActive['plan']` (JSON décodé, avec repli
    sur les 4 bordures à 0,40 m actif si absent ou illisible — une parcelle
    jamais enregistrée doit rester utilisable, pas en erreur),
    `longueur_m`, `largeur_m`, `orientation_lignes` pour préremplir
    `in-longueur`/`in-largeur`/les cases et largeurs de bordure/le radio
    sens-planches — **c'est le correctif du signalement** : ces champs
    n'auront plus jamais 12,00/8,00 par défaut pour une parcelle déjà
    dimensionnée.
  - Nouveau champ caché `<input type="hidden" id="version-plan"
    value="<?= Parcelles::versionPlan($parcelleActive) ?>">`.
  - Nouvelle action POST `plan` (même repli JSON si `X-Requested-With` en-
    tête absent, même schéma que l'action `dimensions` déjà en place) —
    appelle `enregistrerPlan()`, répond `{ok, erreurs, version}` en JSON (la
    nouvelle version, pour que le champ caché se remette à jour sans
    recharger — sinon un deuxième enregistrement immédiat échouerait
    toujours par verrou périmé).
  - Bouton « Enregistrer le plan », dans le même style que « Enregistrer
    ces mesures » déjà là — panneau Données, sous « Ce que la géométrie
    donne ».
- `public/conception-parcelles.js` (modifié) : nouveau gestionnaire de
  soumission (même schéma `fetch()`/`X-Requested-With` que celui déjà
  écrit pour le formulaire « dimensions » cette session — pas de rechargement,
  le plan resterait sinon affiché mais désynchronisé de ce qui vient d'être
  enregistré) ; met à jour `#version-plan` avec la valeur renvoyée après un
  enregistrement réussi. `Gabarit.onReset()` : décision à prendre avec le
  porteur au moment de coder — réinitialiser doit-il revenir aux valeurs
  *enregistrées* (recharger la page) ou aux valeurs *par défaut* (12×8,
  comme aujourd'hui) ? Les deux se défendent ; actuellement `redessiner()`
  ne fait pas la différence entre les deux états.

**Test** : dimensionner une parcelle (longueur, largeur, bordures, sens),
enregistrer, recharger la page — tout doit revenir tel quel, plus jamais
12×8 par défaut. Ouvrir la même parcelle dans deux onglets, enregistrer dans
l'un, tenter d'enregistrer dans l'autre sans recharger : message de verrou,
rien d'écrasé. Une parcelle jamais enregistrée (comme `P1` rapatriée de
production, `plan` NULL) doit s'afficher avec les valeurs par défaut
actuelles, sans erreur.

**Écart avec ce qui précède, tranché en codant** : `largeur_m` n'a pas
rejoint la whitelist de `enregistrer()` (« Pour la carte du terrain ») comme
annoncé plus haut — elle vit avec `longueur_m` dans `enregistrerPlan()` à la
place. Les deux formulaires auraient sinon porté chacun un champ pour la
même colonne `longueur_m` (le plan et « Pour la carte du terrain » avaient
chacun le leur), pouvant diverger sans qu'aucune erreur ne le signale. Le
champ « Longueur d'une planche (m) » du second formulaire est retiré —
« La parcelle » au-dessus porte déjà la longueur réelle, pas besoin de la
ressaisir. `enregistrer()` ne garde que `nb_planches`/`largeur_planche_m`/
`passe_pied_m`.

**Vérifié en CDP** : une parcelle jamais enregistrée affiche bien les
valeurs par défaut (12×8, quatre bordures à 0,40 m, nord-sud) sans erreur ;
un enregistrement réel (longueur 15, largeur 6, sens est-ouest, bordure sud
à 1,20 m, bordure est désactivée) survit à un vrai rechargement de page,
valeur par valeur ; un jeton de version périmé refuse l'écriture avec le
message dédié, sans rien changer en base (revérifié par un rechargement) ;
le bouton « Réinitialiser » du pied de page continue de fonctionner (remet
les valeurs par défaut à l'écran, ne touche pas au plan enregistré ni au
jeton de version) ; le sélecteur « Poser une parcelle » de la carte
interactive continue de fonctionner sans erreur. **Bug trouvé en testant,
préexistant** : le message d'erreur du formulaire « Pour la carte du
terrain » (`Object.values` attendu, `.join()` employé) faisait silencieusement
échouer l'affichage d'une erreur de validation — `$erreurs` est un tableau
PHP à clés nommées, donc un objet JS une fois décodé, jamais un array ;
`.join()` y échoue, rattrapé par le `.catch` du `fetch`, qui affichait un
message trop générique. Corrigé aux deux endroits (le formulaire déjà en
place, et le nouveau).

### Phase 2 — nb_planches calculé, plus saisi à la main — ✅ fait le 2026-09-18

**But** : une seule source pour le nombre de planches, celle que le plan
calcule déjà à l'écran (`redessiner()` le fait déjà, en JS) — plus de champ
« Nombre de planches » à remplir séparément dans « Pour la carte du
terrain », dont la valeur peut aujourd'hui diverger de ce que montre le plan
juste au-dessus.

**Fichiers**

- `src/Web/Parcelles.php` (modifié) : `enregistrerPlan()` calcule
  `nb_planches` côté serveur (même formule que `redessiner()` : largeur ou
  hauteur utile selon le sens, planche 1,2 m, passe-pied 0,5 m — les deux
  constantes elles-mêmes à faire lire d'un seul endroit, JS et PHP en
  divergeraient sinon) et l'écrit dans la même transaction que le plan.
- `src/Web/Calques.php` (modifié) : `parcellesAPlacer()` n'a plus besoin de
  recalculer `largeur_m` depuis `nb_planches`/`largeur_planche_m`/
  `passe_pied_m` — il lit directement la colonne `largeur_m`, maintenant
  fiable puisque la Phase 1 l'enregistre. Revérifier tout le test déjà écrit
  pour cette méthode (Phase 4 du chantier carte) après le changement.
- `public/conception-parcelles.php` (modifié) : le champ « Nombre de
  planches » de « Pour la carte du terrain » devient une valeur affichée
  (résultat, comme sur le plan au-dessus), plus un champ de saisie.

**Pourquoi séparée de la Phase 1** : elle touche `Calques::
parcellesAPlacer()`, un mécanisme déjà construit, testé et déployé (Phase
4/5 du chantier carte interactive, en production). Un risque à part, à ne
pas mélanger avec la persistance elle-même.

**Écart avec ce qui précède, tranché avec le porteur avant de coder** :
une fois `nb_planches` calculé, `largeur_planche_m` et `passe_pied_m` (les
deux derniers champs de « Pour la carte du terrain ») ne nourrissaient plus
rien nulle part — `parcellesAPlacer()` lit `largeur_m` directement, et le
calcul de `nb_planches` utilise les constantes fixes
`Parcelles::PLANCHE_M`/`PASSE_PIED_M`, pas ces deux colonnes. Plutôt que de
garder deux champs qui ont l'air de compter mais ne comptent plus, toute la
section « Pour la carte du terrain » (le formulaire, sa route POST
`dimensions`, `Parcelles::enregistrer()`) a été retirée — question posée
au porteur, tranchée pour la suppression. Les deux colonnes restent en
base, non migrées, simplement plus lues ni écrites. « La parcelle » (le
bloc du haut) porte maintenant seule la longueur et la largeur, avec une
note qui dit à quoi ça sert aussi (poser sur la carte).

**Vérifié en CDP** : la section « Pour la carte du terrain » a bien
disparu (`#dimensions-form` absent du DOM) ; `PLAN_CONSTANTES` (1,2/0,5)
se charge côté client depuis `Parcelles::PLANCHE_M`/`PASSE_PIED_M`, pas
recopié en dur une seconde fois ; un plan enregistré (longueur 15, largeur
6, nord-sud, bordures à 0,40 m) écrit `nb_planches = 8` en base, exactement
le compte affiché en direct par `redessiner()` avant l'enregistrement ; le
sélecteur « Poser une parcelle » de la carte interactive affiche aussitôt
« 6 × 15 m » sans qu'aucun champ séparé n'ait été rempli ; la config JSON
lue par `calques.js` porte bien `largeur_m`/`longueur_m`/`nb_planches`
corrects, lus directement des colonnes.

### Phase 3 — Rattrapage homogène, et une largeur simple pour les planches de bord — ✅ fait le 2026-09-18

**But** : deux demandes du porteur après usage réel de la Phase 1.

1. Aujourd'hui, `nbPlanches` planches de 1,20 m plein pot, espacées de
   0,50 m plein pot, sont posées depuis le bord ouest (ou nord) — le reste
   de l'espace disponible, s'il y en a, est perdu (jamais dessiné, jamais
   compté). Le porteur veut répartir ce reste de façon homogène, en
   laissant le choix : soit sur les planches (chacune un peu plus large),
   soit sur les passe-pieds (chacun un peu plus large) — jamais les deux à
   la fois. Valeurs de départ (« idéales ») : 1,20 m et 0,50 m.
2. **Une planche qui touche une bordure par un de ses grands côtés — au
   lieu d'un passe-pied — n'est accessible que d'un seul côté.** Le
   jardinier peut atteindre environ 0,60 m depuis un passe-pied, contre
   1,20 m en approchant des deux côtés à la fois. Ces planches de bord (la
   première et la dernière de chaque rang, celles dont un grand côté touche
   la bordure de départ ou d'arrivée plutôt qu'un passe-pied) doivent donc
   toujours faire 0,60 m, jamais 1,20 m — quel que soit le rattrapage
   choisi pour les planches intérieures.

**Décisions tranchées avant de coder**

- **`Parcelles::BORD_PLANCHE_M = 0.60`**, une troisième constante à côté de
  `PLANCHE_M`/`PASSE_PIED_M`, même traitement (source unique, exposée au
  client par `PLAN_CONSTANTES`).
- **Le rattrapage choisi (`planches` ou `passe-pieds`) est un réglage par
  parcelle**, enregistré dans `parcelle.plan` (JSON) aux côtés de
  `bordures`/`orientation_lignes` — un nouveau radio « Rattrapage » dans le
  panneau, même schéma que « Sens des planches ».
- **`nb_planches` ne change pas de formule.** Le nombre de planches qui
  tiennent reste calculé comme en Phase 2 (les valeurs idéales déterminent
  le compte) — Phase 3 ne change que la **largeur affichée et dessinée**
  de chaque planche, pas leur nombre. Aucune modification de
  `Parcelles::enregistrerPlan()` n'est donc requise pour `nb_planches` lui-
  même ; seul l'enregistrement du choix de rattrapage s'y ajoute.
- **Cas `n ≤ 2`** : avec une ou deux planches, il n'y a pas de planche
  « intérieure » à qui laisser le rattrapage « planches » — les deux
  planches (ou l'unique) sont déjà des planches de bord, à 0,60 m. Le
  rattrapage choisi n'a alors plus d'effet : tout le reste va au(x)
  passe-pied(s), quel que soit le réglage. Pas une erreur, juste rien à
  répartir ailleurs.
- **Formule, un rang de `n ≥ 3` planches** (largeur ou hauteur utile
  `disponible`, selon le sens des planches) :
  - planches de bord (2, aux deux extrémités du rang) : `BORD_PLANCHE_M`
    chacune, fixe.
  - `n − 2` planches intérieures, `n − 1` passe-pieds — reste à
    distribuer : `disponible − 2×BORD_PLANCHE_M − ((n−2)×PLANCHE_M +
    (n−1)×PASSE_PIED_M)`.
  - Rattrapage `planches` : chaque planche intérieure = `PLANCHE_M +
    reste/(n−2)` ; passe-pieds inchangés.
  - Rattrapage `passe-pieds` : chaque passe-pied = `PASSE_PIED_M +
    reste/(n−1)` ; planches intérieures inchangées.

**Fichiers**

- `src/Web/Parcelles.php` (modifié) : `BORD_PLANCHE_M` ; `enregistrerPlan()`
  whiteliste et écrit `rattrapage` (`planches`|`passe-pieds`) dans `plan`.
- `public/conception-parcelles.php` (modifié) : radio « Rattrapage »,
  préremplissage depuis `plan.rattrapage` (défaut `passe-pieds` — répartir
  sur le passage plutôt que sur la culture, un choix par défaut aussi
  défendable que l'autre, à changer si le porteur préfère l'inverse) ;
  `BORD_PLANCHE_M` ajouté à `PLAN_CONSTANTES` ; le bloc « Ce que la
  géométrie donne » redevient entièrement piloté par JS (largeur de bord,
  largeur intérieure et passe-pied ne sont plus des constantes fixes
  affichées telles quelles, elles dépendent maintenant du rattrapage et du
  nombre de planches).
- `public/conception-parcelles.js` (modifié) : `redessiner()` calcule un
  tableau de largeurs (bord/intérieure) et de passe-pieds au lieu d'une
  largeur unique, positionne chaque planche par décalage cumulé plutôt que
  par un pas fixe.

**Test** : une largeur utile qui ne divise pas exactement par 1,70 m
(1,20 + 0,50) doit remplir tout l'espace, sans reste visible, dans les deux
modes de rattrapage ; les deux planches de bord d'un rang restent à 0,60 m
quel que soit le mode ; `n ≤ 2` bascule silencieusement tout le reste sur
le(s) passe-pied(s).

**Vérifié en CDP** : 12 × 8 m, bordures à 0,40 m, nord-sud — 6 planches,
largeur utile 11,2 m. En rattrapage « passe-pieds » : bord 0,60 m,
intérieure 1,20 m (inchangée), passe-pied 1,04 m (0,50 + le reste) — les
deux planches de bord (première et dernière rendues en SVG) mesurent bien
60 unités (0,60 m) chacune, visiblement plus étroites que les quatre
intérieures sur la capture. En rattrapage « planches » : intérieure
1,88 m, passe-pied 0,50 m (inchangé) — les deux totaux valent exactement
la largeur disponible dans les deux modes, calcul à la main confirmé.
Enregistrement et rechargement fidèles au choix de rattrapage.

### Phase 4 — Bordure interne, pour la biomasse — ✅ fait le 2026-09-18

**But** : une deuxième bordure, distincte de la bordure externe déjà
construite en Phase 1 — décision tranchée avec le porteur le 2026-09-18,
après vérification contre `connaissances/livre-bienvenue-en-syntropie/
ch14-une-histoire-de-tortue.md`. **Ce que la source dit, et où elle
diffère de ce qui est construit ici** : le chapitre décrit une **bande
unique** (« bord de carapace », ~1 m de large) portant **deux lignes de
plantation en son sein** — une ligne côté intérieur (goji, chalef, menthe)
et une ligne côté extérieur (lavande, hysope, romarin), chacune avec son
propre retrait et espacement. Le porteur préfère, pour l'atelier, **deux
bordures géométriquement distinctes** : une externe (bloque-herbe,
coupe-vent, défense contre les animaux — une haie défensive) et une
interne (fournisseurs de biomasse) — plus visuel, et plus proche de
`bordures.externe`/`bordures.interne` déjà anticipées dans la forme JSON
de la référence (`src/Plan.php`) que la Phase 1 a délibérément laissée de
côté.

**Décisions tranchées avant de coder**

- **Géométrie seulement, comme la bordure externe** — quatre côtés,
  chacun actif ou non et sa largeur (mêmes bornes 0,05–5 m). **Aucune
  assignation d'espèce** (pas de lignes de plantation, pas de retrait ni
  d'espacement façon `ImplantationBordures`) : ça suppose `scenario_ligne`,
  toujours absent, et reste hors périmètre — voir § Ce que ce plan exclut.
  Si le porteur veut aller jusqu'à assigner des espèces à chaque bordure,
  ce sera après (ou avec) la Phase 3 de `chantier-scenario-plantation.md`.
- **Position : immédiatement à l'intérieur de la bordure externe**, côté
  par côté — la bordure interne d'un côté ne réduit l'espace utile que si
  la bordure externe du même côté est active ; sinon elle part du bord de
  la parcelle sur ce côté (une bordure interne peut exister sans bordure
  externe du même côté, même si le cas est inhabituel).
- **JSON** : `plan.bordures.interne`, même forme que `plan.bordures.externe`
  — quatre côtés, `actif`/`largeur_m` chacun. `bordures.externe` reste
  inchangée ; aucune migration.
- **La largeur utile des planches (Phase 3) se calcule après les deux
  bordures** — externe puis interne, cumulées sur chaque côté actif.
- **Couleur/visuel distinct** de la bordure externe sur le plan SVG, pour
  qu'on les distingue d'un coup d'œil (la référence ne s'en soucie pas de
  la même façon, l'atelier a déjà cette exigence pour d'autres calques).

**Fichiers**

- `src/Web/Parcelles.php` (modifié) : `enregistrerPlan()` whiteliste et
  valide `bordures.interne` (même code que `bordures.externe`, dupliqué ou
  factorisé) ; le plan JSON écrit porte les deux clés.
- `public/conception-parcelles.php` (modifié) : section « Bordure interne »
  sous « Bordure externe », même structure de champs (case à cocher +
  largeur, quatre côtés) ; préremplissage depuis `plan.bordures.interne`
  (défaut : les quatre côtés inactifs — une bordure interne ne s'impose
  pas, contrairement à l'externe déjà active par défaut).
- `public/conception-parcelles.js` (modifié) : `redessiner()` dessine un
  second rectangle de bordure par côté actif, entre la bordure externe et
  la zone utile ; le calcul de la largeur/hauteur utile pour les planches
  (Phase 3) soustrait les deux bordures cumulées.

**Test** : bordure interne activée sur un ou plusieurs côtés, largeur
propre à chacun, visuellement distincte de l'externe ; la zone de planches
se resserre en conséquence ; enregistrement et rechargement fidèles ;
bordure interne active sur un côté où l'externe est inactive : part du
bord de la parcelle sans erreur.

**Vérifié en CDP** : bordure interne active à l'ouest et à l'est (0,30 m),
inactive au nord et au sud — préremplissage fidèle après rechargement,
rendu SVG distinct (teinte verte biomasse contre or de l'externe). Cas
limite délibérément gardé pour la fin : bordure externe désactivée au nord,
bordure interne activée au nord seule (0,25 m) — le rectangle interne part
bien de `y=0` (bord réel de la parcelle) avec la bonne hauteur, sans
erreur ni décalage fantôme dû à une externe absente ; enregistrement puis
rechargement complet (nouvelle navigation, pas un simple redessin)
confirment que l'état (externe nord inactive, interne nord active à 0,25 m)
survit bien tel quel. Glisser-déposer scopé à la seule bordure externe sur
le dessin (`.plan-bordure[data-cote]`) — la bordure interne reste modifiable
par les champs texte seulement, pas de conflit de glisser entre les deux
anneaux.

## Ce que ce plan exclut explicitement

- Rives — la référence en a un concept séparé (ce que devient le bord
  d'une planche côté rive : rien, passe-pied, ou demi-planche) ; ni
  demandé ni construit ici.
- Un nombre de lignes de semis réglable par planche — le modèle reste
  planche pleine (largeur homogène ou de bord depuis la Phase 3, jamais
  redécoupée en plusieurs lignes de semis).
- Toute assignation d'espèce (semis biomasse annuelle, pesée de graines,
  implantations de vivaces en bordure façon `ImplantationBordures`) —
  suppose `scenario_ligne`, à construire en Phase 3 de
  `chantier-scenario-plantation.md` d'abord. Si ce chantier-là avance,
  revoir alors si `implantations_bordures` vaut la peine d'être porté ici.
- Formes non rectangulaires (`parcelle.forme` zebre/abeille/liane) —
  personne ne l'a demandé, le prototype ne dessine qu'un rectangle.
- Héritage site↔parcelle pour `orientation_lignes` (ou quoi que ce soit
  d'autre) — `Heritage.php` reste hors périmètre, décision déjà posée.
- Aller-retour serveur pendant le glissement d'une bordure — le calcul
  reste entièrement client, comme aujourd'hui.
- Lecture des deux documents de conception de la référence
  (`conception/le-plan-de-parcelle.md`, 203 lignes, et `conception/
  implantation-des-bordures.md`, 171 lignes) — non faite pour ce plan,
  à faire avant de pousser au-delà de la Phase 2 si le porteur veut
  reprendre plus de la référence qu'ici prévu.

## Fichiers critiques

- `sql/01-syntropia.sql` — `parcelle` déjà prête, aucune évolution requise.
- `src/Web/Parcelles.php` — moule déjà posé (`enregistrer()`,
  Phase 3 du chantier carte), à étendre plutôt qu'à dupliquer.
- `public/conception-parcelles.php` / `.js` — le prototype déjà construit et
  affiné cette session (sens des planches, butée de bordure, mesures) :
  Phase 1 le branche à la base, sans changer sa mécanique de dessin.
- `application/src/Plan.php` (656 lignes) et `application/src/
  ImplantationBordures.php` (139 lignes), dépôt de référence — la forme
  JSON exacte et le calcul d'espacement millimétrique, si une phase future
  va au-delà de ce que ce plan couvre.

## Vérification globale

Après chaque phase, vérifier sur `bin/dev.py serve` : une parcelle sans
plan enregistré (le cas de `P1`, rapatriée de production, `plan` NULL)
reste utilisable sans erreur ; le verrou optimiste refuse poliment une
écriture concurrente sans rien perdre côté gagnant ; le formulaire
« Pour la carte du terrain » (Phase 3 précédente, déjà vérifié) continue de
fonctionner sans changement de comportement visible pour l'utilisateur.
