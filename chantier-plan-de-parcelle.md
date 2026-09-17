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
```

Phase 2 est franchement séparable — Phase 1 seule répond déjà entièrement au
signalement du porteur. À ne poser que si Phase 1 est jugée utile telle
quelle.

### Phase 1 — Persister le plan (bordures, sens, dimensions)

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
actuelles, sans erreur. Vérifier aussi que `largeur_m` s'enregistre bien
séparément du plan (deux formulaires, deux actions).

### Phase 2 — nb_planches calculé, plus saisi à la main

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

**Test** : poser une parcelle dimensionnée par son plan sur la carte
interactive (outil rectangle) — la largeur proposée doit correspondre
exactement à ce que montre `conception-parcelles.php`, plus au calcul
séparé d'avant. Revérifier le test de non-régression déjà écrit pour cette
carte (`chantier-carte-interactive.md` § Phase 5).

## Ce que ce plan exclut explicitement

- Bordures internes, rives, rattrapage — la clé JSON `bordures.externe` du
  Phase 1 laisse la place à `bordures.interne` plus tard, sans y toucher
  maintenant.
- Un nombre de lignes de semis réglable par planche — le modèle reste
  planche pleine, largeur et passe-pied fixes.
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
