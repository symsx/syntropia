# Carte interactive du terrain — `conception-etude-site.php`, réplique complète

Plan approuvé le 2026-09-15, phases 1 à 3 faites le même jour. Conservé ici
(déplacé de `~/.claude/plans/`, hors dépôt) pour qu'une reprise ultérieure
le retrouve — voir CLAUDE.md § Point de reprise pour l'état courant résumé.

**État** : ✅ Phase 1 (carte.js) — ✅ Phase 2 (site.latitude/longitude) —
✅ Phase 3 (vraies parcelles) — ⏳ Phase 4 (backend calques) à faire ensuite
— Phases 5 à 10 non commencées.

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
Phase 4  ⏳ Backend calques (Calques.php + calque_element + api-calques.php)
Phase 5  ⏳ conception-etude-site.php + calques.js — page vivante, pret:true
Phase 6  ⏳ Cadastre (IGN API Carto)
Phase 7  ⏳ Eau (Overpass)
Phase 8  ⏳ Vents (Open-Meteo)
Phase 9  ⏳ Relief (IGN Altimétrie + Relief.php, sans iso())
Phase 10 ⏳ Import GeoJSON
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

### Phase 4 — Backend calques ⏳ (prochaine étape du chantier)

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

### Phase 5 — `conception-etude-site.php` + `calques.js` — la page vivante ⏳

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
peut pas écrire.

### Phase 6 — Cadastre (IGN API Carto) ⏳

Superposition éphémère des parcelles cadastrales IGN, jamais stockée.
`src/Web/Cadastre.php` (264 lignes, `COTE_MAX_M=600`), `public/api-cadastre.php`
(77 lignes, lecture seule — `roleSur() !== null`, body `site_id, sud,
ouest, nord, est`, réponse allégée `{parcelles:[{libelle, contenance_m2,
points}]}`). `calques.js` : fetch débouncé (500ms) sur `carte:vue`.

### Phase 7 — Eau (Overpass) ⏳

Propose automatiquement les éléments d'eau proches, à partir de l'emprise
déjà tracée. `src/Web/Eau.php` (277 lignes, `COTE_MAX_M=3000`,
`MARGE_M=100`, gestion HTTP 429/504 dédiée), `public/api-eau.php`
(113 lignes — dérive la bbox de `Calques::empriseDuTerrain()`, dédup par
`proprietes.osm`). Indépendante du Cadastre, ordre non contraint entre
les deux.

### Phase 8 — Vents (Open-Meteo) ⏳

Rose des vents calculée à la demande depuis `site.latitude`/`longitude`,
jamais persistée. `src/Web/Vents.php` (187 lignes, `ANNEES_DEFAUT=5`,
16 secteurs, `SEUIL_FORT_KMH=20.0` flag app). Action POST `rose` sur
`conception-etude-site.php`. **Même source (Open-Meteo) envisagée pour
la zone climatique USDA** — voir CLAUDE.md § Point de reprise, chantier
séparé mais lié.

### Phase 9 — Relief (altitude + courbes de niveau, sans vue 3D) ⏳

Grille d'altitudes IGN + courbes de niveau 2D sur le calque `relief`.
`src/Relief.php` (namespace `Syntropia`, classe plate comme
`FicheTerrain.php` — `pente()`, `courbe()`, `niveaux()`, `courbesGeo()`,
`iso()` non porté), `src/Web/Altimetrie.php` (225 lignes, filtrage strict
du sentinel `z=-99999`), `public/api-altitude.php` (54 lignes). La plus
lourde en calcul serveur (marching squares) — placée après les API plus
simples.

### Phase 10 — Import GeoJSON ⏳

Importer des tracés externes (ContourBuddy, QGIS, Géoportail, traces GPS)
directement sur un calque. `src/Geojson.php` (namespace `Syntropia`,
263 lignes — inversion `[lon,lat]`→`[lat,lon]`, limites 4 Mo / 1500 points
par tracé / 300 tracés). Zéro dépendance sur Cadastre/Eau/Vents/Relief —
peut en réalité être menée n'importe quand après la Phase 5.

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
