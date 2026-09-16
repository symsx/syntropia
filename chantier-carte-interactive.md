# Carte interactive du terrain — `conception-etude-site.php`, réplique complète

Plan approuvé le 2026-09-15, phases 1 à 3 faites le même jour. Conservé ici
(déplacé de `~/.claude/plans/`, hors dépôt) pour qu'une reprise ultérieure
le retrouve — voir CLAUDE.md § Point de reprise pour l'état courant résumé.

**État : chantier terminé, les dix phases sont faites (2026-09-16).**
✅ Phase 1 (carte.js) — ✅ Phase 2 (site.latitude/longitude) — ✅ Phase 3
(vraies parcelles) — ✅ Phase 4 (backend calques) — ✅ Phase 5 (page
vivante, `pret:true`) — ✅ Phase 6 (cadastre) — ✅ Phase 7 (eau) —
✅ Phase 8 (vents) — ✅ Phase 9 (relief) — ✅ Phase 10 (import GeoJSON).

## Contexte

`conception-etude-site.php` est la dernière des trois étapes volontairement
reportées (avec `conception-scenario` et `conception-materiel-et-eau`),
`pret` absent d'`etapes.js` depuis le début de la refonte — un chantier trop
gros pour être construit « en version allégée » sans trahir la page qu'il
remplace (`carte.php` côté référence : calques, dessin, vents, relief,
cadastre, import GeoJSON — 379+262+785 lignes rien que pour le cœur
PHP+JS). Le porteur a explicitement choisi la réplique complète (API
externes comprises) plutôt qu'une première tranche allégée, en connaissance
du prérequis qu'elle entraîne : `conception-parcelles.php` était alors un
mockup statique (« La Vadaux », « Parcelle A » codés en dur, aucune
lecture en base) — la carte ne peut placer des parcelles réelles sans lui.
**Ce prérequis est maintenant résolu, voir Phase 3 ci-dessous.**

Le schéma SQL (`parcelle`, `calque_element`) est déjà porté et vérifié
identique à la référence — rien à migrer là-dessus. Tout le reste (backend
PHP, moteur de carte JS, moteur de dessin JS, quatre intégrations externes)
est à construire depuis zéro, en respectant strictement le gabarit « le
bureau » (aucune exception, voir CLAUDE.md) — la coquille HTML/CSS de la
référence (`carte.php`, page plate avec son propre bandeau) ne se copie pas ;
seule sa logique PHP/JS se porte.

Recherche préalable menée en deux passes (un agent Explore a lu en entier
`Calques.php`, `carte.js`, `calques.js`, les trois API, `Cadastre.php`,
`Eau.php`, `Vents.php`, `Relief.php`, `Altimetrie.php`, `Geojson.php`,
`Parcelles.php`, `parcelle.php`, le schéma SQL ; un agent Plan a ensuite
vérifié ces constats directement sur les deux dépôts et dessiné le
phasage) — signatures, tables, endpoints et limites externes ci-dessous
sont vérifiés, pas supposés.

## Décisions tranchées avant de coder

- **Plusieurs parcelles, pas une seule.** `directives.txt` (lignes 40, 55) :
  « un site et *n* parcelles », « phase 2 : conception (un ou plusieurs
  parcelles dites "tortues") ». **Fait en Phase 3** : vraie liste
  (créer/nommer/typer plusieurs parcelles) — la section « Parcelle A »
  codée en dur du panneau gauche est devenue une vraie liste.
- **Emplacement des outils de dessin dans « le bureau ».** Suit le
  précédent déjà posé par `conception-parcelles.php` (seule page du dépôt
  cible déjà « outillée ») : bandeau d'outils = modes de dessin (radios,
  comme Sélection/Déplacer un bord existants), panneau droit « Données »
  = calque actif + champs du tracé en cours + résultats (surface,
  longueur), panneau Informations = aide du calque sélectionné
  (`Calques::CALQUES[...]['aide']`, déjà écrite pour ça côté référence),
  bulle = message ponctuel. Pas un nouveau système à inventer.
- **HTTP sortant** : un `curl_init` dupliqué par classe (Cadastre, Eau,
  Vents, Altimetrie), comme la référence — pas de classe `Http.php`
  partagée à inventer. Mêmes délais (10s/3s, 30s/5s, 25s/5s, 6s/3s).
  User-Agent adapté : `'syntropia (atelier agroforestier syntropique)'`.
- **`site_coordonnees` déjà en texte libre** (table `reponse`) : abandonné
  sans migration automatique — **fait en Phase 2**, remplacé par le
  point-carte réel (`site.latitude`/`longitude`).
- **`Relief::iso()` (vue 3D) et `relief.php` exclus** de ce chantier :
  seules les courbes de niveau 2D s'affichent sur le calque `relief`.
  Motif : ce sont les pièces les plus coûteuses et les moins utiles à un
  premier tour utilisable ; à reconsidérer une fois le reste en usage réel.
- **Import GeoJSON dépend du JS** (contrairement à la référence, qui garde
  `carte.php` lisible sans script) — cohérent avec « le bureau », qui
  suppose déjà du JS partout (thème, tiroirs, et depuis Phase «
  documentation », la pagination des livrets). Seule l'action `retirer`
  un tracé reste un vrai `<form method="post">` sans dépendance JS, comme
  la référence — irréversible, donc jamais bloqué par un script absent.

## Vue d'ensemble des dix phases

```
Phase 1  ✅ carte.js (moteur de tuiles) — pilote sur definition-site.php
Phase 2  ✅ site.latitude/longitude réels (Sites::enregistrerParametres)
Phase 3  ✅ Vraies parcelles, en liste (CRUD minimal) — condition commune
Phase 4  ✅ Backend calques (Calques.php + calque_element + api-calques.php)
Phase 5  ✅ conception-etude-site.php + calques.js — page vivante, pret:true
Phase 6  ✅ Cadastre (IGN API Carto)
Phase 7  ✅ Eau (Overpass)
Phase 8  ✅ Vents (Open-Meteo)
Phase 9  ✅ Relief (IGN Altimétrie + Relief.php, sans iso())
Phase 10 ✅ Import GeoJSON
```

### Phase 1 — `carte.js`, moteur de tuiles ✅

Fait : `public/assets/js/carte.js` (chemin final — le plan disait
`public/assets/carte.js`, corrigé pour suivre la convention déjà en place,
voir `assets/js/README.md`). Port quasi identique de la référence, aucune
dépendance métier. Utilisé par `definition-site.php` (Phase 2) — un
second usage viendra avec `conception-etude-site.php` (Phase 5).

### Phase 2 — `site.latitude`/`site.longitude` réels ✅

Fait : `Sites::enregistrerParametres()` (whitelist `latitude`/`longitude`,
validation manuelle -90..90/-180..180, arrondi 5 décimales) ;
`definition-site.php` pose le point sur une vraie carte au lieu d'un champ
texte libre, POST écrit les deux colonnes.

### Phase 3 — Vraies parcelles, en liste ✅

Fait : nouveau `src/Web/Parcelles.php` (`dUnSite()`, `parId()`, `creer()`,
`enregistrer()` — whitelist code/nom/type à la création, puis
`nb_planches`/`largeur_planche_m`/`passe_pied_m`). `conception-parcelles.php`
lit le vrai site et la vraie liste de parcelles ; sans parcelle (ou sur
`?nouvelle=1`), un formulaire remplace le plan SVG ; le panneau gauche
liste les parcelles réelles (`?parcelle=ID`). **Reste un prototype,
volontairement** : le plan lui-même (bordures, planches dessinées dans le
SVG, piloté par `conception-parcelles.js` côté client) — `parcelle.plan`
toujours pas branché, chantier séparé (`plan.php`/`ImplantationBordures`
côté référence).

### Phase 4 — Backend calques ✅ (fait le 2026-09-16)

**But** : toute la logique serveur des calques, testable au `curl` avant
toute UI de dessin.

**Fichiers**
- Nouveau `src/Web/Calques.php` — port proche (379 lignes) : `CALQUES`/
  `TYPES` identiques (ENUM déjà identique en base), `dUnSite()`,
  `parId()`, `parcellesAPlacer()` (dépend de `Web\Parcelles`, fait),
  `empriseDuTerrain()`, `ajouter()`/`modifier()`/`retirer()`, statiques
  `pointsDepuis()`/`metresParDegre()`/`surfaceM2()`/`longueurM()`, privé
  `valider()`.
- Nouveau `public/api-calques.php` — port proche (83 lignes) : POST seul,
  `Authentification::exige()` + `jetonValide()` + `Sites::peutEcrireSur()`,
  actions `ajouter`/`modifier`/`retirer`, réponses JSON `{ok, id?,
  element?, message, erreurs?}`.

**Test** : POST direct avec CSRF valide — ajouter un point sur `ressenti`,
le relire, le retirer ; vérifier les refus (lecture seule, calque/type
invalides, points insuffisants pour un polygone).

### Phase 5 — `conception-etude-site.php` + `calques.js` — la page vivante ✅ (fait le 2026-09-16)

**But** : la carte réellement dessinable dans « le bureau », avec tous les
modes de dessin et le placement des parcelles réelles. **Fait basculer
`pret: true`.**

**Fichiers**
- Nouveau `public/conception-etude-site.php` — gabarit complet sur le
  moule exact de `conception-parcelles.php` ; construit côté serveur le
  `data-calques` JSON (`siteId, csrf, api, apiCadastre, apiEau,
  peutEcrire, calques: Calques::CALQUES, elements, parcelles, centre`) —
  sans `apiCadastre`/`apiEau` branchés avant les Phases 6/7 (le JS doit
  tolérer leur absence dès cette phase).
- Nouveau `public/assets/js/calques.js` (chemin `assets/js/`, comme
  `carte.js` — pas `assets/calques.js`) — port adapté (785 lignes) :
  logique de dessin/état/rendu SVG portée de près ; la coquille change
  (pas de `.carte-atelier`/`.carte-panneau` — monte sur les zones du
  bureau) ; branché sur `Gabarit.onOutilChange()` (déjà exposé par
  `gabarit.js`) plutôt qu'une gestion de bandeau d'outils réinventée ;
  conserve le placement de parcelle par rectangle (rotation
  locale-mètres) tel quel.
- `public/assets/js/etapes.js` (modifié) — `conception-etude-site` passe
  `pret: true`, en toute fin de phase seulement.

**Pourquoi ici** : dépend strictement des Phases 2 (centre de carte), 3
(parcelles à placer) et 4 (API). Ordre de construction interne recommandé :
(a) montage carte + calque `fond` (déclencheur de `empriseDuTerrain()`,
dont les Phases 7/9 dépendront) ; (b) placement de parcelle (rectangle) ;
(c) les autres calques point/ligne/polygone génériques ; (d) libre
(freehand) et retrait.

**Test** : tracer les limites du terrain, créer + placer une parcelle par
rectangle avec rotation, dessiner un point sur `ressenti`, tout retirer,
recharger (persistance réelle), vérifier qu'un compte lecture seule ne
peut pas écrire — tout vérifié (Chrome headless piloté en CDP), y compris
le cas lecture seule (bandeau-outils et panneau Données absents du HTML
rendu, `api-calques.php` refuse quand même le POST direct).

**Deux écarts trouvés en testant, corrigés dans cette même phase** :

- `Parcelles::enregistrer()` (Phase 3) ne captait jamais `longueur_m` —
  avec `largeur_planche_m`/`nb_planches`/`passe_pied_m`,
  `Calques::parcellesAPlacer()` en a besoin en plus de `largeur_m`
  (calculée) pour proposer l'outil rectangle ; sans lui, aucune parcelle
  créée par le vrai formulaire n'était jamais « dimensionnée ». Ajouté
  à `Parcelles::enregistrer()` et au formulaire de
  `conception-parcelles.php`.
- Le clic sur la carte ne faisait rien tant que le panneau Données était
  ouvert : son `scrim` (fond assombri commun à tous les tiroirs,
  « clic en dehors = ferme », voir CLAUDE.md § Architecture) couvre tout
  le bureau et absorbe le clic avant qu'il n'atteigne le SVG — or cette
  page est la première où panneau et carte s'utilisent *en même temps*
  (choisir un calque, cliquer pour poser un point/une parcelle, revenir
  au panneau pour enregistrer). `#scrim-droit` neutralisé (`pointer-
  events:none`) dans le `<style>` propre à `conception-etude-site.php`
  seulement — les autres pages gardent le comportement uniforme des
  tiroirs ; fermeture toujours possible par la tirette ou Échap.

### Phase 6 — Cadastre (IGN API Carto) ✅ (fait le 2026-09-16)

Superposition éphémère des parcelles cadastrales IGN, jamais stockée.
`src/Web/Cadastre.php` (port proche, `COTE_MAX_M=600`), `public/api-cadastre.php`
(port proche, lecture seule — `roleSur() !== null`, body `site_id, sud,
ouest, nord, est`, réponse allégée `{parcelles:[{libelle, contenance_m2,
points}]}`). `conception-etude-site.php` : ajoute `apiCadastre` au JSON de
config, et le bloc `.cadastre-bascule` (case à cocher « Voir le cadastre »,
`#cadastre-etat`) dans le panneau Données — sur le modèle exact de la
référence.

**Rien à faire côté `calques.js`** : tout le mécanisme (fetch débouncé
500 ms sur `carte:vue`, rendu du calque cadastre en trait fin sous les
autres, dessin du numéro de parcelle si la place le permet) avait déjà été
porté à la Phase 5, gardé derrière `cfg.apiCadastre` et `$('cadastre-
visible')` — exactement le pari fait alors : « le JS doit tolérer leur
absence dès cette phase », vérifié payant ici, zéro ligne de JS à changer.

**Test** : vérifié en Chrome headless (CDP) sur le Champ-de-Mars (une zone
française connue, coordonnées 48.85837/2.29448) — la couche apparaît (50
parcelles, contours en pointillés + numéros CR 1-4 visibles), capture
d'écran à l'appui ; `SELECT COUNT(*) FROM calque_element` confirme zéro
enregistrement après usage ; la garde `COTE_MAX_M` refuse poliment une
emprise de 14 650 m de côté (« Zoomez pour voir le cadastre… ») sans
jamais lever d'erreur ; un compte anonyme et un site qui n'appartient pas
au compte sont tous deux refusés (403).

### Phase 7 — Eau (Overpass) ✅ (fait le 2026-09-16)

Propose automatiquement les éléments d'eau proches, à partir de l'emprise
déjà tracée. `src/Web/Eau.php` (port proche, `COTE_MAX_M=3000`,
`MARGE_M=100`, gestion HTTP 429/504 dédiée), `public/api-eau.php` (port
proche — dérive la bbox de `Calques::empriseDuTerrain($siteId,
Eau::MARGE_M)`, message dédié si `null`, dédup par `proprietes.osm`, écrit
via `Calques::ajouter()`). `conception-etude-site.php` ajoute `apiEau` à
la config JSON et le bloc `.chercher-eau` (bouton + aide) dans le panneau
Données, avant la case cadastre — même ordre que la référence.

**Encore une fois, rien à changer dans `calques.js`** : le bouton
`#eau-chercher`, déjà gardé derrière `cfg.apiEau` depuis la Phase 5,
s'est allumé de lui-même dès que la page a fourni la clé et le HTML.

**Test** : un fond tracé sur le Canal Saint-Martin (Paris, une zone à
l'eau bien cartographiée) puis « Chercher l'eau » a posé 4 éléments réels
(un canal en deux tronçons, deux plans d'eau) ; un second clic a
correctement dédupliqué (0 posé, 4 déjà là) ; retirer le fond puis
chercher renvoie le message dédié (« Tracez d'abord les limites du
terrain… ») sans erreur HTTP ; un site étranger au compte et un accès
anonyme sont refusés (403) comme pour les autres API. **Le 429/504 s'est
présenté pour de vrai**, pas simulé : à force de solliciter Overpass en
rafale pendant ce test, le service a fini par répondre occupé — message
exact affiché, bouton réactivé ensuite, aucune erreur JS.

### Phase 8 — Vents (Open-Meteo) ✅ (fait le 2026-09-16)

Rose des vents calculée à la demande depuis `site.latitude`/`longitude`,
jamais persistée. `src/Web/Vents.php` (port proche, `ANNEES_DEFAUT=5`,
16 secteurs, `SEUIL_FORT_KMH=20.0` flag app). **Même source (Open-Meteo)
envisagée pour la zone climatique USDA** — voir CLAUDE.md § Point de
reprise, chantier séparé mais lié.

**Différence assumée avec la référence** : `carte.php` affiche la rose
dans une longue section documentaire de la page, toujours visible ;
`conception-etude-site.php` la range dans le panneau Données
(`#vents-rose-bloc`), visible seulement quand le calque « vents » est
choisi — cohérent avec le reste de la page (calque actif → champs qui le
concernent). Le calcul lui-même reste un simple `<form method="post">`
qui rend la même page (action `rose`), sans API JSON séparée, exactement
comme la référence — le panneau Données et la sélection du calque
« vents » se rouvrent tout seuls après l'envoi (`aside.ouvert`, `<option
selected>` posés côté serveur) pour que le résultat soit visible sans
reclic.

**Rien à faire côté dessin des flèches** : `dessinerElement()` savait déjà
tracer une flèche de vent avec épaisseur selon l'intensité
(`INTENSITES`/`trait()`/`pointe()`) depuis le port initial de la Phase 5 —
ce n'était pas encore branché à une vraie donnée avant cette phase, mais
le code n'a pas bougé.

**Test** : vérifié sur un point réel (Limoges, coordonnées 45.85/1.25,
la même station SYNOP que la référence a utilisée pour vérifier sa
convention météorologique) — 43 824 heures relevées sur 5 ans, vent
dominant nord-est, rose SVG + tableau des 5 secteurs affichés
correctement (capture d'écran à l'appui) ; sans coordonnées, le
formulaire disparaît et seul le message « Posez d'abord le point du
site » s'affiche, sans erreur. **Open-Meteo a répondu « n'a pas répondu »
pour de vrai** à deux reprises pendant les essais rapprochés (comme
Overpass en Phase 7) — message dégradé correct à chaque fois, aucune
exception JS ni PHP.

### Phase 9 — Relief (altitude + courbes de niveau, sans vue 3D) ✅ (fait le 2026-09-16)

Grille d'altitudes IGN + courbes de niveau 2D sur le calque `relief`.
`src/Relief.php` (namespace `Syntropia`, classe plate comme
`FicheTerrain.php` — `pente()`, `rose()`, `ceQuElleCommande()`,
`courbe()`, `niveaux()`, `courbesGeo()`, `iso()` non porté),
`src/Web/Altimetrie.php` (port proche, filtrage strict du sentinel
`z=-99999`), `public/api-altitude.php` (nouveau point d'entrée JSON, sans
équivalent direct côté référence — voir plus bas).

**Écart assumé avec la référence, trouvé en lisant `carte.php` et
`relief.php` de près** : le dépôt de référence relève sa grille sur une
page séparée (`relief.php`, avec vue 3D et côté/pas saisis à la main —
tous deux hors périmètre ici) ; ce que le plan appelait
`api-altitude.php` n'a d'ailleurs aucun rapport avec le fichier du même
nom côté référence, qui sert une tout autre fonction (altitude d'un
point pour une fiche de site). Ici, comme pour l'eau (Phase 7), le carré
à relever se déduit de l'emprise du fond déjà tracé
(`Calques::empriseDuTerrain()`) plutôt que d'être saisi — rien à régler,
le pas s'ajuste automatiquement pour viser une grille d'une quarantaine
de points de côté (sous le plafond de 2 000 points d'`Altimetrie`). Les
courbes ne sont pas stockées : `conception-etude-site.php` les
recalcule à chaque chargement depuis les altitudes brutes (même logique
que la référence sur `carte.php`), et `api-altitude.php` les calcule
aussi dans sa réponse pour un affichage immédiat sans recharger la page.
Un seul relevé de relief par site : le suivant remplace le précédent,
côté serveur et côté client.

**Rendu déjà en place depuis la Phase 5** : `dessinerElement()` savait
déjà tracer un élément `grille` (bbox pointillée + segments de contour +
étiquettes d'altitude) — jamais exercé avant cette phase faute de
donnée réelle, mais aucune ligne à changer.

**Test** : relevé réel sur un terrain de montagne (Hautes-Alpes,
44.9319/6.2331) — 1681 points en 12 appels IGN, dénivelé de 629 m,
pente moyenne 108,7 % exposée nord-ouest, 538 segments de courbes
affichés avec leurs altitudes (capture d'écran à l'appui) ; un second
relevé remplace le premier (un seul élément `relief`/`grille` en base à
la fois) ; sans fond tracé, message dédié ; hors couverture IGN
(Londres) : message « Aucun point n'est dans la couverture » et rien
n'est enregistré ; site étranger et accès anonyme refusés (403).
**L'amincissement au-delà du plafond de segments vérifié directement**
sur les données réelles récupérées (fonction pure, sans base ni
réseau) : `maximum=100` fait passer 538 segments à 67 en espaçant les
niveaux de 1 sur 6 — confirme le `do…while ($saut <= 6)`.

### Phase 10 — Import GeoJSON ✅ (fait le 2026-09-16 — dernière phase du chantier)

Importer des tracés externes (ContourBuddy, QGIS, Géoportail, traces GPS)
directement sur un calque. `src/Geojson.php` (namespace `Syntropia`, port
proche — inversion `[lon,lat]`→`[lat,lon]`, limites 4 Mo / 1500 points par
tracé / 300 tracés). `conception-etude-site.php` gère l'action POST
`importer` (upload multipart, calque cible choisi dans le formulaire lui-
même, indépendant du calque actif à l'écran) — un simple POST qui rend la
même page, exactement comme la référence sur `carte.php` : un fichier ne
se poste pas en `fetch()` sans complexité ajoutée, et contrairement au
cadastre/à l'eau/aux vents/au relief (des services distants interrogés
depuis la page), importer est un geste ponctuel qui n'a pas besoin de
rester sur une vue précise de la carte.

**Test** : import réel d'un GeoJSON (polygone + ligne avec `elevation`)
sur le calque « végétation » — 2 tracés posés, inversion lon/lat vérifiée
en base, anneau fermé dédupliqué, l'altitude devenue libellé (« 145,5
m ») ; coordonnées façon Lambert 93 refusées avec le message dédié, rien
n'écrit en base ; 350 tracés synthétiques → exactement 300 posés avec le
message de troncature. Limite des 4 Mo et cas d'entrée (vide, JSON
illisible, sans géométrie, type inconnu) vérifiés en appelant
`Geojson::lire()` directement (fonction pure, sans base ni réseau) —
`upload_max_filesize` du serveur local (2 Mo) est de toute façon plus
bas que le plafond de la classe (4 Mo), donc un vrai upload HTTP ne
peut pas exercer ce cas précis ici. L'allègement à 1500 points vérifié
sur une ligne à 3000 points (1501 points gardés — les deux extrémités
sont toujours conservées, d'où le +1 possible sur la cible : comportement
voulu, pas un bug, voir le commentaire de `alleger()`). Formulaire
d'import à l'intérieur du même panneau Données que tout le reste,
donc soumis à la même garde `$peutEcrire` déjà vérifiée en Phase 5.

## Ce que ce plan exclut explicitement

- `plan.php`/`ImplantationBordures` (éditeur de planches, persistance
  `parcelle.plan`, concurrence optimiste) — chantier séparé, le prototype
  SVG reste un prototype.
- `conception-scenario.php`, `conception-materiel-et-eau.php` — étapes
  reportées séparément.
- La fiche d'attributs complète de la parcelle (sol, exposition, héritage
  site↔parcelle) au-delà des trois colonnes de placement.
- `Relief::iso()` (vue 3D) et `relief.php`.

## Vérification globale

Après chaque phase, vérifier sur `bin/dev.py serve` (schéma déjà rejoué,
idempotent) : le parcours reste navigable de bout en bout sans lien mort
(`etapes.js` cohérent avec les pages réellement `pret`), aucune régression
sur les phases déjà construites, et — seulement une fois en ligne via
`bin/deployer.py` — que les appels sortants (OSM tuiles, IGN, Overpass,
Open-Meteo) fonctionnent réellement depuis le serveur alwaysdata (curl
sortant parfois filtré chez certains hébergeurs mutualisés).

**Méthode de vérification visuelle établie pendant les Phases 1-3** :
Chrome headless piloté en Python pur via le protocole DevTools (pas de
Playwright/Node disponibles ici) — voir CLAUDE.md § Commandes et le
harnais dans le scratchpad de session. Toujours nettoyer les cookies
entre deux comptes de test dans le même profil Chrome (sinon une session
d'un compte déjà supprimé fausse le test) ; toujours vérifier `python3
bin/dev.py compte` plutôt que `php bin/compte.php` direct (sinon
`config/base.php`, la production, l'emporte silencieusement sur
`config/local.env`).
