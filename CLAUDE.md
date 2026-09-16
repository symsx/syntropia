# CLAUDE.md — refonte

## Ce que le site est

**Syntropia** — un atelier d'accompagnement permettant de concevoir et
conduire un système agroforestier syntropique. Nom affiché partout dans
l'interface (bandeau général, panneau gauche) : « Syntropia », jamais
« Atelier Syntropie » (l'ancien nom, celui du dépôt de référence).

## Où sont les choses

- `application/` — le site réel, figé, référence pour la logique et les
  données.
- `refonte/le-bureau/` — la maquette validée, premier élément posé pour
  cette refonte.
- `directives.txt` — les grands axes du projet, écrits par le porteur :
  intention, règles de développement, structure de l'interface, plan du
  site.
- `plan-du-site.md` — l'arborescence des pages qui en découle, phase par
  phase.
- `architecture-technique.md` — proposition de framework modulaire pour
  construire ces pages avec du code commun, à valider.
- `chantier-carte-interactive.md` — le plan détaillé (10 phases) de la
  carte interactive du terrain, en cours — voir § Point de reprise.
- `dossier-qualification.md` — le brief transmis à un tiers (cgpt) tenant
  le rôle de responsable qualification, pour produire des scénarios de
  test à partir de l'état réel du dépôt.
- `skills-map/` — carte des compétences métier nécessaires aux étapes de
  l'atelier (skill `organisation-skills`), sourcée depuis
  `/home/sylvain/projets/syntropie/connaissances/` — lecture seule, ce
  corpus n'est ni recopié ni modifié ici.

## Arborescence

```
.htaccess              racine du dépôt — réécrit tout vers public/, ne refuse rien
le-bureau/            la maquette validée — référence visuelle et technique
src/                   backend PHP, adapté de application/src/ (namespace Syntropia\)
  .htaccess              refuse tout accès web — la logique n'a rien à y faire
  autoload.php           PSR-4 minimal, sans Composer
  Configuration.php      réglages : environnement, puis config/local.env ou config/base.php
  Database.php           connexions PDO — syntropia_comptes, syntropia
  Role.php, Profil.php   énumérations — droits et profils
  FicheTerrain.php       contenu des 7 fiches de relevé de sol — voir releve-vierge.php
  Documentation.php      lit contenu/*.md, un chapitre par ligne « # » — livrets et glossaire
  Web/
    Comptes.php            dépôt des comptes (base syntropia_comptes)
    Authentification.php   session, connexion, CSRF, garde-page
    Invitations.php         codes d'invitation — condition de l'inscription
    Sites.php                le site d'un compte — un seul, pour l'instant
    Reponses.php             lit/écrit la table reponse — question ⇒ texte
    Parcelles.php            les parcelles d'un site — création, et les trois mesures pour la carte à venir
    Calques.php              calque_element — CRUD + géométrie (surface, longueur, emprise du terrain) ; appelé par api-calques.php
    Cadastre.php             parcelles cadastrales IGN (API Carto) — éphémère, jamais stocké ; appelé par api-cadastre.php
    Eau.php                  l'eau OpenStreetMap (Overpass) autour du fond tracé — devient une donnée du projet ; appelé par api-eau.php
contenu/
  .htaccess              refuse tout accès web
  documentation-atelier.md  livret « L'atelier » — un chapitre par ligne « # », voir Documentation.php
  documentation-saf.md      livret « L'agroforesterie syntropique » — modèle à remplacer
  glossaire.md              un mot technique = un chapitre, une phrase — alimente glossaire.php ET la bulle des `.terme` ailleurs sur le site
config/
  .htaccess              refuse tout accès web — identifiants en clair dans base.php
  base.php.exemple       gabarit de configuration de production
  local.env              configuration locale — hors dépôt (.gitignore)
sql/
  .htaccess              refuse tout accès web
  00-comptes.sql          4 tables — compte, acces, tentative, invitation
  01-syntropia.sql        17 tables — site, parcelle, scenario_ligne…
bin/
  .htaccess              refuse tout accès web — des scripts, pas des pages
  dev.py                  serveur de développement — voir Commandes
  compte.php              crée/modifie un compte en CLI — appelé par `dev.py compte`, JAMAIS en direct (voir § Point de reprise)
  deployer.py             déploiement vers alwaysdata — etat/fichiers/config/base/preparer
public/                racine servable du site en refonte
  .htaccess              seul dossier accessible ; type MIME et cache des polices
  index.php               page publique — présente l'atelier, oriente. Accueil = « / », sans nom de fichier
  connexion.php          page de connexion — redirige selon que le compte a déjà un site ou non
  deconnexion.php
  definition-projet.php      passage obligé unique — ouvre le site, hors manifeste etapes.js
  definition-vision.php      étape 1 (Définition), gardée par Authentification::exige()
  definition-objectifs.php   étape 2 (Définition), gardée par Authentification::exige()
  definition-moyens.php      étape 3 (Définition), gardée par Authentification::exige()
  definition-site.php        étape 4 (Définition) — le lieu (carte réelle) et ce qu'on observe, deux groupes
  conception-etude-site.php  étape 1 (Conception) — la carte interactive du terrain, calques.js
  conception-etude-sol.php   étape 2 (Conception), gardée par Authentification::exige()
  conception-parcelles.php   étape 7 (Conception) — vraies parcelles en liste, plan encore prototype
  conception-calendrier.php  étape 6 (Conception), gardée par Authentification::exige()
  releve-vierge.php          fiches de relevé de sol à imprimer — HORS GABARIT, voir son en-tête
  realisation-preparation-parcelle.php  étape 1 (Réalisation)
  realisation-materiel-vegetal.php      étape 2 (Réalisation)
  realisation-outils-et-plans.php       étape 3 (Réalisation)
  realisation-consignation.php          étape 4 (Réalisation)
  suivi-arrosage-germination.php        étape 1 (Suivi)
  suivi-perturbation.php                étape 2 (Suivi)
  suivi-saut-du-chat.php                étape 3 (Suivi)
  suivi-consignation.php                étape 4 (Suivi) — redirige vers conception-parcelles.php, fin du parcours construit
  inscription.php            inscription sur invitation — code de 8 caractères, voir Invitations.php
  documentation-site.php     livret « L'atelier » — lit contenu/documentation-atelier.md, pagine ses chapitres trop longs
  documentation-saf.php      livret « L'agroforesterie syntropique » — lit contenu/documentation-saf.md, même mécanique
  glossaire.php              les mots techniques, un par un — recherche filtrée, lit contenu/glossaire.md
  api-calques.php            POST ajouter/modifier/retirer un tracé — appelée par conception-etude-site.php (assets/js/calques.js)
  api-cadastre.php           POST parcelles cadastrales IGN d'une emprise — lecture seule, rien n'est enregistré
  api-eau.php                POST l'eau OpenStreetMap autour du fond tracé — pose des tracés réels sur le calque eau, dédup par proprietes.osm
  assets/
    css/gabarit.css        la coquille du bureau — TOUTES les pages, publiques comprises ; `.terme` (mot expliqué au survol) y vit aussi
    js/carte.js              moteur de tuiles sans dépendance (OpenStreetMap) — partagé par toute carte
    js/calques.js            dessin des calques sur carte.js — modes via Gabarit.onOutilChange(), placement de parcelle par rectangle
    js/etapes.js            le manifeste des phases — `pret: true` = la page existe déjà
    js/gabarit.js           Gabarit.init() ; grise précédente/suivante vers une étape non `pret` ; initTermes() arme la bulle de tout `.terme`
    js/livret.js             pagine un chapitre de documentation-{site,saf}.php trop long pour l'écran — mesuré en pixels, pas un compte de paragraphes
    polices/               Clash Display, Archivo, reprises de le-bureau/fonts/
composants/            un dossier par zone du gabarit, réutilisable entre pages
  bandeau-general/  bandeau-outils/  panneau-gauche/  panneau-droit/
  panneau-informations/  bulle/  pied-de-page/
skills-map/            carte des compétences métier — un noeud par domaine
  INDEX.md               le tronc, groupé par phase de l'atelier
  nodes/*.md             un fichier par domaine (diagnostic des sols, cycles
                         de l'eau, forêt-jardin…), sourcé sur connaissances/
.claude/skills/
  organisation-skills/    le skill qui maintient skills-map/
```

Le contenu se remplit au fur et à mesure, page par page, sur le gabarit
décrit ci-dessous. Détail des décisions et de ce qui reste à porter dans
`architecture-technique.md`.

## Commandes

- Servir le site en local : `python3 bin/dev.py` — vérifie PHP, vérifie
  que les deux bases sont joignables, rejoue le schéma (idempotent),
  sert sur `SYNTROPIA_HOTE:SYNTROPIA_PORT`. Actions séparées :
  `bin/dev.py etat` (diagnostic seul), `init` (schéma seul), `serve`
  (sert seul). Ne crée jamais les bases ou l'utilisateur MySQL — ça
  demande des droits d'administration, voir `architecture-technique.md`
  § Le backend.
- Créer ou modifier un compte : `python3 bin/dev.py compte` — demande
  identifiant/nom/rôle puis le mot de passe au clavier (deux fois, sans
  l'afficher). C'est la voie du **premier compte** : `inscription.php`
  exige une invitation, et la première invitation n'a personne pour
  l'ouvrir. **Ne jamais appeler `php bin/compte.php` directement** —
  voir § Point de reprise, piège vérifié deux fois cette session.
- Déployer vers alwaysdata : `python3 bin/deployer.py` — `etat`
  (connectivité), `fichiers` (`--dry-run` pour prévisualiser, sinon
  envoie), `config` (pousse `config/base.php` seul), `base` (rejoue le
  schéma sur le serveur distant), `preparer` (assemble un dépôt manuel
  dans `../syntropia-depot` pour qui préfère FileZilla).
- Ouvrir la maquette de référence : `le-bureau/index.html` directement
  dans un navigateur — page HTML autonome, sans PHP, sans build.
- Vérifier une largeur d'écran sous 500px (téléphone réel, pas
  `--window-size` de Chrome headless, qui plafonne à 500px et ne simule
  rien de plus petit) : lancer Chrome headless avec le protocole
  DevTools ouvert (`--remote-debugging-port`, `--remote-allow-origins=*`,
  `setsid` indispensable si le lancement vient d'un outil — sinon le
  processus meurt avec l'appel qui l'a lancé), puis le piloter en Python
  pur (`websocket-client`, déjà présent, aucune autre dépendance —
  Playwright/Node ne sont pas installés ici) : `Emulation.
  setDeviceMetricsOverride` pour la taille d'écran réelle,
  `Runtime.evaluate` pour remplir/cliquer,
  `Page.captureScreenshot` pour voir le résultat. **Ne jamais conclure
  qu'une page tient dans l'écran en mesurant seulement `document.
  documentElement.scrollWidth/scrollHeight`** — `overflow:hidden` sur
  `html,body` absorbe silencieusement tout débordement sans le signaler ;
  seule une vraie capture d'écran, ou la mesure directe d'un conteneur
  contre son parent, le révèle. Entre deux comptes de test dans le même
  profil Chrome, `Network.clearBrowserCookies` — sinon une session d'un
  compte déjà supprimé fausse silencieusement le test suivant.

## Architecture — le gabarit « le bureau »

> **Le site EST le bureau. Construis chaque page — publique ou
> connectée, accueil comprise — sur ce gabarit. N'invente jamais un
> autre habillage visuel.** `directives.txt` décrit le bandeau général
> et le bureau AVANT de distinguer partie publique et partie connectée
> — c'est l'interface de tout le site, pas seulement du travail
> authentifié. Correction du 2026-09-14, après une page d'accueil
> construite hors gabarit : ne pas répéter.

*Résumé opérationnel ; le détail complet vit dans le dépôt de référence,
`.claude/skills/atelier-syntropie-interface-layout/` et
`atelier-syntropie-uiux/`.*

**Aucun défilement, nulle part — pas même à l'intérieur du bureau.**
Grille à hauteur de viewport (`height: 100dvh`), quatre lignes fixes ;
le bureau central absorbe tout l'espace restant, toujours entièrement
visible. Syntropia n'est pas une page web qu'on parcourt : c'est un
atelier, vu de dessus comme un bureau réel où l'on va travailler.
**Un contenu trop long se réduit, ne défile jamais** — correction du
2026-09-14, après un bureau construit avec un contenu qui défilait en
interne : refusé, même pour l'accueil. **Sur petit écran, un contenu
trop haut se resserre ou se pagine, il ne déborde jamais** — deux vrais
bugs de ce type trouvés et corrigés le 2026-09-15, voir § Point de
reprise.

| Zone | Rôle | Déclencheur |
|---|---|---|
| Bandeau général | Hamburger, logo, titre « Site — Page », thème, compte | — |
| Bandeau d'outils | Outils contextuels à la page ; devient un menu déroulant sous 30rem | — |
| Le bureau | Canevas de travail, sans défilement | — |
| Panneau gauche (navigation) | Le site, le voyage, les pages de l'élément en cours | Hamburger, jamais un autre déclencheur |
| Panneau droit (« Données ») | Saisie liée au bureau | Sa propre tirette verticale |
| Panneau Informations | Mode d'emploi de la page | Sa propre tirette horizontale |
| Bulle | Indication ponctuelle liée à un objet du bureau | Survol / manipulation |

Règles à respecter pour tout nouveau panneau ou composant :

- **Une tirette par panneau, jamais partagée.** Le triangle pointe vers
  l'extérieur (bord de l'écran) quand ouvert, vers l'intérieur (bureau)
  quand fermé.
- **Les panneaux ont un z-index supérieur aux tirettes**, pour ne jamais
  être recouverts par une tirette qui n'est pas la leur.
- **Un panneau ancré en haut ou en bas se cache en animant sa hauteur**
  (`height: 0` → hauteur réelle), jamais par translation — une
  translation laisserait sa boîte pleine hauteur recouvrir les bandeaux
  fixes au-dessus.
- Fermeture uniforme : reclic sur la tirette, clic en dehors (fond
  assombri), ou **Échap** (ferme tout).
- Cases à cocher redessinées au thème (`.case`), jamais le rendu par
  défaut du navigateur.

**Sans dépendance** — `:has()` et `input:checked + label` plutôt qu'une
bibliothèque JS ; SVG en unités réelles (mètres) pour le dessin. Deux
polices seulement pour le bureau : Clash Display (titres, menus, outils —
en majuscules) et Archivo (le reste) ; fichiers et licences dans
`le-bureau/fonts/`. **Nuance posée le 2026-09-15** : « le bureau suppose
déjà du JS partout » (thème, tiroirs) est devenu un principe explicite,
pas seulement un fait — deux nouveaux mécanismes en dépendent
volontairement (les bulles de `.terme`, la pagination de `livret.js`),
là où rien ne s'y prêtait avant.

### Ordonner les menus et la barre d'outils — loi de Jakob

**Les listes d'actions indépendantes se classent du plus utilisé au
moins utilisé** — bandeau d'outils, liens de la partie publique
(Se connecter / S'inscrire / Documentation), tout menu où les entrées
ne forment pas une séquence obligée. C'est déjà l'ordre du bandeau
d'outils de `conception-parcelles.php` (Sélection, Déplacer un bord,
puis les outils à venir) : à reproduire pour toute nouvelle page.

**Exception, tranchée le 2026-09-14 : les listes qui représentent un
PARCOURS gardent l'ordre chronologique, jamais la fréquence.** Les 4
phases, et « Le plan / Le scénario / Le suivi » d'une parcelle, restent
dans l'ordre du parcours même si une étape plus tardive (le suivi) sera
revisitée bien plus souvent qu'une étape amont (le plan, réglé une
fois) : casser cet ordre casserait le repère « où en suis-je », qui est
le but même de l'atelier.

Sans mesure d'usage réelle, « le plus utilisé » se raisonne au départ —
à revoir une fois l'atelier utilisé pour de vrai.

## L'esprit à garder dans l'interface

> « Ce qui est important c'est l'intention, pas la forme. » —
> `INTENTION.md`, qui prime sur le ton de tout le reste.

- Offrir des possibilités plutôt que des restrictions.
- Dire ce qui est recherché, et pourquoi, plutôt qu'imposer.
- Annoncer avant de demander — le geste, la durée, ce qu'on va rencontrer
  — inviter à ressentir.
- **Une donnée demandée sert une étape plus loin — jamais collectée pour
  elle-même.** Le parcours est cohérent dans son « pourquoi » et son
  « comment » : les solutions se dessinent au fur et à mesure des
  étapes, pas d'un coup. Quand l'usage d'une réponse est connu, le dire
  au moment de la question — pas seulement le geste et la durée, aussi
  à quoi elle va servir. Une personne qui comprend l'usage répond de
  façon plus pertinente et plus motivée qu'à une question qui paraît
  arbitraire. Exemple déjà construit : `definition-moyens.php`, où des
  moyens financiers faibles font apparaître la piste de la régénération
  naturelle assistée — l'usage de la réponse se montre, il ne se
  raconte pas après coup. `directives.txt` donne déjà l'usage de
  plusieurs champs (`definition-objectifs` : destinations → « guide le
  comment » ; terme → « mesurer la progression, voir le réalisme ») —
  le dire à l'écran, pas seulement le savoir.
- **Un mot technique s'explique où il apparaît, pas seulement dans une
  documentation à part.** Depuis le 2026-09-15 : tout `.terme` souligné
  en pointillés montre sa définition au survol/focus (une phrase,
  `contenu/glossaire.md`) — et la même source alimente `glossaire.php`,
  une recherche pour qui veut chercher un mot directement. Voir § Point
  de reprise.

## Directives de travail pour ce dossier

- Ne complexifie pas les tâches simples.
- Si un point n'est pas clair, ne fais pas de supposition : pose une question.
- Ne fais pas de changement aléatoire, non demandé.
- Vérifie ton travail quand tu l'as terminé.
- Définir par ce que sont les choses et taire ce qu'elles ne sont pas
- **Construis toute page sur le gabarit « le bureau ». Aucune exception,
  aucune page publique à part.** Voir § Architecture.
  **Seule exception posée, le 2026-09-14 :** `releve-vierge.php`, un
  document à imprimer, pas une page à visiter — voir son en-tête. Ne pas
  s'en servir de précédent pour autre chose qu'un document destiné au
  papier.

## Point de reprise — 2026-09-15

*Historique complet des corrections et décisions dans
`architecture-technique.md` ; ce qui suit est l'état courant, pas le
récit.*

### Où en est le dépôt

**Décidé, à ne pas rouvrir** :

- 4 phases (définition, conception, réalisation, suivi) — détail dans
  `plan-du-site.md`, tranché par `directives.txt`.
- Le backend s'appuie sur `application/` (dépôt de référence) et
  l'adapte, sans rien partager avec `syntropie` : bases et vocabulaire
  propres à syntropia (`site.statut` etc. sur les 4 phases, pas les 7
  valeurs du dépôt de référence). Détail : `architecture-technique.md`
  § Le backend.
- **Le site EST le bureau** — chaque page, publique ou connectée, sur
  le gabarit `gabarit.css`/`gabarit.js` ; **aucun défilement, nulle
  part, pas même à l'intérieur du bureau** ; un contenu trop long se
  réduit ou se pagine. **Une seule exception** : `releve-vierge.php`, un
  document à imprimer, pas une page à visiter.
- Nom affiché : **Syntropia** (jamais « Atelier Syntropie »).
- Menus et barre d'outils : du plus utilisé au moins utilisé, sauf les
  listes de parcours (phases, étapes d'une parcelle) qui gardent
  l'ordre chronologique. Voir § Architecture.
- **Un seul projet par compte** — pas de liste de sites. Un site s'ouvre
  une fois, sur `definition-projet.php` (passage obligé, hors
  `etapes.js`), avant la première question de Définition.
- **Deux étapes restent volontairement reportées**, à part entière —
  pas construites en version allégée, `pret` absent dans `etapes.js` :
  `conception-scenario.php` (scénario de plantation — espèces, strates,
  associations culturales ; `scenario.php`, 336 lignes dans le dépôt de
  référence) et `conception-materiel-et-eau.php` (`directives.txt` :
  « pour chaque parcelle », mêmes données absentes que le scénario).
  Les deux demandent un vrai scénario en base, qui n'existe pas encore.
  `conception-etude-site.php` (la carte interactive) n'est plus
  reportée du tout : `pret: true` depuis la Phase 5 du chantier (voir
  `chantier-carte-interactive.md`), page vivante et dessinable — restent
  Cadastre/Eau/Vents/Relief/Import GeoJSON (Phases 6-10, enrichissent une
  page déjà utilisable, ne touchent plus `etapes.js`). Chaque étape reste
  accessible en la sautant : le mécanisme « phase → première étape
  prête » (`gabarit.js`) passe par-dessus sans lien mort.

**Construit et vérifié** :

- Le gabarit (`public/assets/css/gabarit.css`,
  `public/assets/js/{etapes,gabarit,carte,livret}.js`) —
  `Gabarit.init({ etape, site, contexte })`. Le panneau gauche construit
  sa navigation tout seul depuis `etapes.js` (`remplirPhaseProjet()`,
  `remplirEtapesPhase()`) — chaque entrée de « Phase projet » mène à la
  première étape prête de cette phase, jamais à un lien mort. Une
  section « Documentation » (`remplirSectionDocumentation()`) s'ajoute
  d'elle-même en dernière position du panneau gauche, sur TOUTE page —
  les deux livrets et le glossaire, toujours au même endroit. Le compte
  (avatar + nom, en haut à droite) ouvre un vrai menu
  (`initCompteMenu()`) : Mon projet, Préférences (`compte.php`, à
  venir — grisée), Se déconnecter. `initTermes()` arme la bulle de tout
  `.terme` (survol ou focus clavier, jamais le `title` natif).
- Le backend PHP (`src/` : `autoload`, `Configuration`, `Database`,
  `Role`, `Profil`, `FicheTerrain`, `Documentation`, `Web/Comptes`,
  `Web/Authentification`, `Web/Invitations`, `Web/Sites`,
  `Web/Reponses`, `Web/Parcelles`) et les deux bases MySQL (`syntropia`,
  `syntropia_comptes`), schéma dans `sql/`.
- **La persistance réelle de toute la phase Définition** — `site`,
  `membre_site`, `participant`, `reponse`. `definition-projet.php` ouvre
  le site ; les quatre étapes de Définition enregistrent chaque champ
  dans `reponse` et le rechargent en le revisitant — un champ vidé
  efface sa ligne plutôt que d'y garder `''`. `definition-site.php`
  écrit aussi, en plus, les colonnes propres `site.latitude`/
  `longitude` (`Sites::enregistrerParametres()`) via un vrai point posé
  sur une carte OpenStreetMap (`assets/js/carte.js`, sans dépendance) —
  le champ texte libre d'origine a disparu.
- **`conception-parcelles.php` n'est plus un mockup** — vraies parcelles
  en base (`Web/Parcelles.php`), créées/listées/sélectionnées par le
  panneau gauche (`?parcelle=ID`), quatre mesures réelles enregistrées
  (`nb_planches`, `largeur_planche_m`, `passe_pied_m`, `longueur_m` — ce
  dont la carte interactive a besoin pour l'outil rectangle, voir plus
  bas). **Reste un prototype, volontairement** : le plan lui-même
  (bordures, planches dessinées en SVG, piloté par
  `conception-parcelles.js` côté client) — `parcelle.plan` toujours pas
  branché, chantier séparé.
- **Le backend des calques** — `src/Web/Calques.php` (port proche de la
  référence : `CALQUES`/`TYPES`, `dUnSite()`, `parId()`,
  `parcellesAPlacer()`, `empriseDuTerrain()`, `ajouter()`/`modifier()`/
  `retirer()`, `pointsDepuis()`/`metresParDegre()`/`surfaceM2()`/
  `longueurM()`) et `public/api-calques.php` (POST, jeton + `Sites::
  peutEcrireSur()`, actions `ajouter`/`modifier`/`retirer`, JSON).
- **La carte interactive du terrain est vivante — chantier
  `chantier-carte-interactive.md`, Phases 1 à 5 faites, 2026-09-16,
  `pret: true`.** `conception-etude-site.php` + `public/assets/js/
  calques.js` : dessin SVG par-dessus `carte.js`, calque actif et champs
  du tracé dans le panneau Données (`#calque-choix`, `#trace-etat`,
  `#trace-libelle`…), modes de dessin (déplacer/point/ligne/polygone/
  libre) sur le bandeau d'outils partagé (`Gabarit.onOutilChange()`),
  placement d'une parcelle dimensionnée par rectangle tournant d'un clic
  (angle local-mètres). Vents/Relief/Import GeoJSON (Phases 8-10) pas
  encore branchés. Vérifié de bout en bout
  en Chrome headless piloté en CDP : tracé du fond, parcelle posée par
  rectangle + rotation + enregistrement, point sur `ressenti`, retrait
  d'un tracé, persistance à la recharge, refus d'écriture pour un compte
  lecture seule (UI absente du HTML rendu **et** `api-calques.php`
  refuse quand même le POST direct). Deux écarts trouvés et corrigés en
  testant : `Parcelles::enregistrer()` ne captait pas `longueur_m` (sans
  quoi aucune parcelle n'était jamais « dimensionnée » pour le
  rectangle) ; le `scrim` partagé de tous les tiroirs (fond assombri,
  clic en dehors = ferme) bloquait les clics sur la carte tant que le
  panneau Données restait ouvert — neutralisé (`#scrim-droit{pointer-
  events:none}`) dans le `<style>` propre à cette page seule, les autres
  pages gardent le comportement uniforme. Détail complet :
  `chantier-carte-interactive.md` § Phase 5.
- **Le cadastre sur la carte — Phase 6 du chantier carte, 2026-09-16.**
  `src/Web/Cadastre.php` (port proche : `autour()`, `dansEmprise()`,
  `parReference()`, `COTE_MAX_M=600`) et `public/api-cadastre.php`
  (POST, lecture seule — `roleSur() !== null`, pas `peutEcrireSur()` : on
  regarde, on n'écrit rien). `conception-etude-site.php` ajoute
  `apiCadastre` à la config JSON et la case « Voir le cadastre » dans le
  panneau Données — `calques.js` savait déjà s'en servir depuis la
  Phase 5 (gardé derrière `cfg.apiCadastre`), aucune ligne de JS à
  changer. Vérifié en Chrome headless (CDP) sur le Champ-de-Mars : 50
  parcelles IGN affichées en trait fin par-dessus la carte, `SELECT
  COUNT(*) FROM calque_element` reste à zéro après usage, la garde des
  600 m de côté refuse poliment une emprise trop large, anonyme et site
  non ouvert au compte tous deux refusés (403).
- **L'eau sur la carte — Phase 7 du chantier carte, 2026-09-16.**
  `src/Web/Eau.php` (port proche : `dansEmprise()`, `COTE_MAX_M=3000`,
  `MARGE_M=100`, gestion HTTP 429/504) et `public/api-eau.php` (POST,
  écriture — dérive la bbox de `Calques::empriseDuTerrain($siteId,
  Eau::MARGE_M)`, refuse poliment si le fond n'est pas tracé, dédup par
  `proprietes.osm`, écrit via `Calques::ajouter()`). `conception-
  etude-site.php` ajoute `apiEau` et le bloc « Chercher l'eau
  d'OpenStreetMap » dans le panneau Données — même pari qu'en Phase 6,
  `calques.js` inchangé. Vérifié sur le Canal Saint-Martin (Paris) : 4
  éléments réels posés (canal en deux tronçons, deux plans d'eau), un
  second clic déduplique correctement (0 posé, 4 déjà là), le message
  dédié apparaît si le fond est retiré, site étranger et accès anonyme
  refusés (403). **Le 429/504 s'est présenté pour de vrai** en sollicitant
  Overpass en rafale pendant le test — message et ré-activation du
  bouton corrects, sans code à simuler pour le vérifier.
- **Les mots techniques expliqués au survol** — tout `.terme` (span,
  `data-explique="..."`) vient de `contenu/glossaire.md`
  (`Documentation::glossaire()`/`::terme()`), même source qu'une page
  publique dédiée, `glossaire.php` (recherche filtrée en direct, jamais
  plus qu'une poignée de résultats montrés à la fois — mesuré en JS
  contre la hauteur réelle disponible, pas un compte fixe).
- `bin/dev.py` — vérifie PHP et les bases, rejoue le schéma, sert ;
  arrête proprement son `php` enfant sur n'importe quel signal.
  `bin/dev.py compte` (→ `bin/compte.php`, jamais appelé en direct — un
  appel direct retombe silencieusement sur `config/base.php`, donc la
  production, deux incidents réels cette session avant d'être compris)
  crée ou modifie un compte en CLI.
- `bin/deployer.py` — `etat`, `fichiers` (rsync vers
  `jubile:~/www/syntropia/`), `config`, `base`, `preparer`. Syntropia
  est en ligne, `https://jubile.alwaysdata.net/syntropia/` (le `https`
  fonctionne sans rien configurer — certificat Let's Encrypt wildcard
  `*.alwaysdata.net` posé par la plateforme). Sous `/syntropia/`, pas la
  racine : le compte alwaysdata héberge aussi Plantae, à `/plantae/` —
  `RewriteBase`/`RewriteCond` de `.htaccess`, `'base'` de
  `config/base.php`, et `WWW_DISTANT` de `bin/deployer.py` portent tous
  les trois ce chemin, à tenir assortis. `.htaccess` vérifié pour de
  vrai sur Apache réel (pas seulement « non vérifiable en local ») :
  racine du sous-répertoire réécrite vers `public/`, dossiers protégés
  refusés (403), polices avec bon type MIME et cache d'un an, cookie de
  session cantonné à `/syntropia/`. Reste ouvert : aucune procédure
  n'automatise `deployer.py` après chaque changement — à relancer à la
  main.
- Sept pages publiques : `index.php`, `connexion.php` (redirige selon
  que le compte a déjà un site — corrigé le 2026-09-15, il retombait
  avant sur le mockup de `conception-parcelles.php` pour un compte tout
  juste inscrit), `deconnexion.php`, `inscription.php` (sur invitation),
  `documentation-site.php`, `documentation-saf.php` et `glossaire.php`.
  Les deux livrets tournent chapitre par chapitre (CSS pur, `:has()` +
  `label[for]`) et paginent maintenant un chapitre trop long pour
  l'écran (`assets/js/livret.js`, mesuré en pixels — un chapitre est de
  la prose libre écrite par le porteur, sa longueur n'est pas bornée).
  `documentation-saf.md` n'est encore qu'un modèle, le porteur écrit son
  contenu à son rythme.
- **Le parcours au complet, sauf les deux étapes reportées** —
  dix-huit des vingt étapes de `etapes.js` portent `pret: true`.
  Détail : `architecture-technique.md` § Le parcours complet, de
  Calendrier à Suivi.
- **Petit écran, deux vrais bugs trouvés sur capture d'écran réelle et
  corrigés** : les deux livrets (colonne « Chapitres » à largeur fixe
  ne tenant plus à côté du contenu sous 30rem → rangée de pastilles
  au-dessus) et `definition-site.php` (deux colonnes qui s'écrasaient,
  puis empilées débordaient encore → resserrement + `align-items:
  flex-start` pour ne plus répartir le débordement pour moitié
  au-dessus du bandeau). Aucun des deux n'était détectable en mesurant
  `scrollWidth`/`scrollHeight` — voir § Commandes pour la méthode qui
  marche. Les autres pages du parcours, vérifiées à 412px, n'ont pas
  montré d'autre régression à ce jour.
- `skills-map/` (skill `organisation-skills`) — 14 noeuds de
  compétence métier, groupés par phase, sourcés sur
  `/home/sylvain/projets/syntropie/connaissances/`.
- Arborescence protégée par `.htaccess`, sur le modèle exact du dépôt de
  référence — vérifiée pour de vrai en ligne (voir plus haut).
- `dossier-qualification.md` — brief transmis à cgpt (rôle : responsable
  qualification), état réel du dépôt phase par phase, pour des
  scénarios de test sur 3 niveaux d'utilisateur (débutant/intermédiaire/
  avancé en agroforesterie syntropique — le critère est la connaissance
  du domaine, l'app ne distingue pas encore les comptes par ce biais).
  Campagne en cours, comptes `qualification`/`qualification7`… créés
  localement par cgpt — **leurs scénarios seront confrontés au code
  réel avant toute correction, pas supposés corrects par construction.**

**Mécanique à respecter pour toute nouvelle étape** : ajouter son
`pret: true` dans `etapes.js` en même temps que sa page — sinon les
étapes voisines restent grisées vers elle (jamais de lien mort, jamais
de lien non plus tant qu'il est absent). Quand `directives.txt` donne
l'usage d'un champ, l'écrire à côté du champ (`definition-objectifs.php`
en donne l'exemple). Pour une étape qui saisit des réponses : suivre le
schéma de `definition-vision.php` (garde-site, `POST` →
`Reponses::enregistrerLot()` → redirection, `GET` →
`Reponses::pourParticipant()` préremplit) plutôt que d'inventer un
autre mécanisme. Pour un mot technique nouveau : l'ajouter à
`contenu/glossaire.md` (`# Terme` + une phrase), puis
`Documentation::terme($termes, 'id-du-terme', 'texte affiché')` où il
apparaît — jamais partout où le mot existe dans l'absolu, seulement où
il apparaît réellement, pour ne pas noyer le motif.

### Prochaine étape

1. **Chantier « carte interactive du terrain »** — Phases 1 à 7 faites,
   `conception-etude-site.php` vivante et `pret: true`, cadastre IGN et
   recherche d'eau (Overpass) en place. **Phase 8 ensuite** : Vents
   (Open-Meteo), rose calculée à la demande depuis `site.latitude`/
   `longitude`, jamais persistée. Deux phases restent après (Relief,
   Import GeoJSON), toutes enrichissent la page déjà utilisable sans plus
   toucher `etapes.js`. Plan complet, détaillé phase par phase avec
   fichiers et tests :
   `chantier-carte-interactive.md`.
2. **Zone climatique USDA, déterminée plutôt que saisie** — chantier
   noté, pas commencé. Aucun service USDA officiel n'accepte de
   coordonnées (seulement des codes postaux américains) ; calculable
   depuis un historique météo (Open-Meteo, même source que la Phase 8
   du chantier carte — voir `chantier-carte-interactive.md`).
   Remplacerait le champ texte libre actuel de `definition-site.php`.
3. **`contenu/documentation-saf.md`** — Sylvain écrit les vrais
   chapitres à son rythme, en markdown, directement dans ce fichier.
4. **`autres-documentations.php`** — page publique restante, non
   commencée.
5. **`compte.php` / Préférences** — non commencé (lien déjà posé, grisé,
   dans le menu du compte).
6. **Au-delà du premier tour** : rien ne relit encore les réponses
   enregistrées pour les présenter (récapitulatif, export, tableau de
   bord) — à voir si/quand le besoin s'en fait sentir.
7. **Campagne de qualification avec cgpt** — `dossier-qualification.md`
   transmis, en cours. Confronter ses scénarios au code réel une fois
   produits.
8. Aucune procédure n'automatise `bin/deployer.py` après chaque
   changement — à relancer à la main.
