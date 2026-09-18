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
  carte interactive du terrain, terminé le 2026-09-16 — voir § Point
  de reprise.
- `chantier-scenario-plantation.md` — le plan détaillé (4 phases) du
  scénario de plantation et de la base de végétaux, proposé le
  2026-09-18, pas encore commencé — voir § Point de reprise.
- `chantier-plan-de-parcelle.md` — le plan détaillé (4 phases) de la
  persistance du plan de parcelle (bordures externe et interne, sens des
  planches, dimensions, rattrapage), proposé le 2026-09-18. **Les quatre
  phases sont faites, chantier terminé le 2026-09-18** — réplique
  partielle et assumée de la référence, voir § Point de reprise.
- `chantier-inscription-parrainage.md` — le plan détaillé (4 phases) de
  l'inscription par code de parrainage : inviter quelqu'un à créer un
  compte, ou à rejoindre un projet (et un compte peut alors avoir
  plusieurs projets). **Les quatre phases sont faites (2026-09-18)** — voir § Point
  de reprise.
- `chantier-export-import-projet.md` — le plan (4 phases) pour exporter et
  importer un projet en un fichier JSON daté, demandé le 2026-09-18 ;
  **noté, pas commencé** — format JSON (plutôt que XML) ; décisions du
  2026-09-18 consignées, un point reste à confirmer (la trace d'origine).
- `dossier-qualification.md` — le brief transmis à un tiers (cgpt) tenant
  le rôle de responsable qualification, pour produire des scénarios de
  test à partir de l'état réel du dépôt.
- `skills-map/` — carte des compétences métier nécessaires aux étapes de
  l'atelier (skill `organisation-skills`), dont les noeuds citent
  `connaissances/`.
- `connaissances/` — la base sourcée par `skills-map/` : importée telle
  quelle du projet `syntropie` le 2026-09-17, celui-ci étant voué à
  disparaître. Avant cette date, c'était un corpus externe en lecture
  seule (`/home/sylvain/projets/syntropie/connaissances/`), jamais
  recopié ; il vit maintenant ici, modifiable comme le reste du dépôt.

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
  Relief.php              pente, exposition, courbes de niveau (marching squares) — classe plate, sans iso() (vue 3D)
  ShadeMap.php            liens ShadeMap sur le terrain (solstices, équinoxe, exposition annuelle, au midi solaire du lieu) — un lien, jamais un import
  Geojson.php             lit un GeoJSON externe (ContourBuddy, QGIS, Géoportail…), un tracé par morceau — inversion [lon,lat]→[lat,lon]
  Documentation.php      lit contenu/*.md, un chapitre par ligne « # » — livrets et glossaire
  Web/
    Comptes.php            dépôt des comptes (base syntropia_comptes)
    Authentification.php   session, connexion, CSRF, garde-page
    Invitations.php         codes d'invitation — condition de l'inscription
    Sites.php                les sites d'un compte, et son projet actif — voir chantier-inscription-parrainage.md
    Reponses.php             lit/écrit la table reponse — question ⇒ texte
    Parcelles.php            les parcelles d'un site — création, et les trois mesures pour la carte à venir
    Calques.php              calque_element — CRUD + géométrie (surface, longueur, emprise du terrain) ; appelé par api-calques.php
    Cadastre.php             parcelles cadastrales IGN (API Carto) — éphémère, jamais stocké ; appelé par api-cadastre.php
    Eau.php                  l'eau OpenStreetMap (Overpass) autour du fond tracé — devient une donnée du projet ; appelé par api-eau.php
    Vents.php                rose des vents calculée à la demande (Open-Meteo) — jamais persistée ; appelé par conception-etude-site.php
    Altimetrie.php           altitude IGN — un point, ou une grille pour le relief ; filtre le sentinel z=-99999 ; appelé par api-altitude.php
    ZoneUsda.php             zone de rusticité USDA estimée depuis 30 ans de minima (Open-Meteo, 1991-2020) — jamais persistée seule ; appelé par api-usda.php
    Especes.php              référentiel de végétaux — cadre partagé (site_id=0) + surcharges par site, clé (nom_scientifique, variete)
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
  00-comptes.sql          4 tables — compte, acces, tentative, invitation (+ migration portable de invitation.role_site)
  01-syntropia.sql        le schéma — site, parcelle, calque_element, espece, scenario_ligne…
  02-especes-seed.sql     DML, pas du schéma — 46 espèces curatées, site_id=0 ; rejoué à chaque bin/dev.py init
bin/
  .htaccess              refuse tout accès web — des scripts, pas des pages
  dev.py                  serveur de développement — voir Commandes
  compte.php              crée/modifie un compte en CLI — appelé par `dev.py compte`, JAMAIS en direct (voir § Point de reprise)
  deployer.py             déploiement vers alwaysdata — etat/fichiers/config/base/preparer
  catalogue-plantae.py    exporte les noms botaniques de plantae-corpus vers contenu/catalogue-scenario.json (autocomplete du scénario)
  lancer-local.sh          lance dev.py et ouvre Firefox dès que le port répond — pour le lanceur de bureau, pas l'usage courant (dev.py seul suffit)
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
  conception-scenario.php    étape 4 (Conception) — le tableau temps × strates d'une parcelle (`?parcelle=ID`) : frise SVG, ou pastilles par strate ; saisie dans le panneau Données
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
  api-usda.php               POST estime la zone USDA d'un point — lecture seule, appelée par le bouton « Estimer » de definition-site.php
  rejoindre.php              rejoindre un projet avec un code — pour un compte existant ; menu du compte (gabarit.js), et lien d'invitation ouvert connecté (inscription.php)
  compte.php                 Préférences : récapitulatif du compte et de ses projets, nom affiché, mot de passe — 3 onglets ; `exige()` y renvoie un compte dont le mot de passe doit changer (?mdp=1)
  membres.php                les participants du projet actif, et le retrait d'un accès — réservée au porteur ; menu du compte (gabarit.js)
  inviter.php                ouvrir/lister/retirer des codes de parrainage — hors parcours, sur le gabarit ; appelée par le menu du compte (gabarit.js)
  api-projets.php            GET les projets du compte + le projet actif ; POST change le projet actif — appelée par gabarit.js sur toute page connectée
  api-altitude.php           POST relève une grille d'altitudes IGN sur l'emprise du fond tracé — un seul relevé par site, le suivant remplace le précédent
  assets/
    css/gabarit.css        la coquille du bureau — TOUTES les pages, publiques comprises ; `.terme` (mot expliqué au survol) y vit aussi
    js/carte.js              moteur de tuiles sans dépendance — OpenStreetMap par défaut, fonds IGN au choix sur la grande carte (table `FONDS`) — partagé par toute carte
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
                         de l'eau, forêt-jardin…), cite connaissances/
connaissances/         la base citée par skills-map/ — un dossier par domaine,
                       importée du projet syntropie le 2026-09-17
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
  § Le backend. Un lanceur de bureau (« Syntropia (local) », hors dépôt —
  propre à ce poste) appelle `bin/lancer-local.sh`, qui fait la même
  chose puis ouvre Firefox dès que le port répond.
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
- **Vérifie sur ce poste (`bin/dev.py` + Chrome headless/CDP), pas sur le
  site de production** — demandé le 2026-09-18. Le déploiement
  (`bin/deployer.py`) et une vérification `curl` que les fichiers déployés
  correspondent restent normaux ; ce qui change, c'est de ne plus explorer
  ou diagnostiquer un comportement directement en production quand un
  test local peut répondre à la même question.
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
- **Un compte peut avoir plusieurs projets** (le sien, ceux rejoints sur
  invitation) — décision rouverte par le porteur le 2026-09-18, qui
  remplace « un seul projet par compte ». Un **projet actif** (cookie
  `syntropia_projet`, revérifié contre `membre_site`) : `Sites::
  pourCompte()` renvoie toujours UN site, les pages n'ont pas changé. Un
  premier site s'ouvre sur `definition-projet.php` (passage obligé, hors
  `etapes.js`), avant la première question de Définition ; un projet de
  plus, sur `definition-projet.php?nouveau=1`.
- **Une étape reste volontairement reportée**, à part entière — pas
  construite en version allégée, `pret` absent dans `etapes.js` :
  `conception-materiel-et-eau.php` (`directives.txt` : « pour chaque
  parcelle »). Elle s'appuiera sur le scénario, qui existe depuis le
  2026-09-18 : `conception-scenario.php` (scénario de plantation) est
  `pret: true` — voir `chantier-scenario-plantation.md`.
  `conception-etude-site.php` (la carte interactive) n'est plus
  reportée du tout : `pret: true` depuis la Phase 5, et le chantier
  « carte interactive du terrain » est maintenant terminé, dix phases
  faites — cadastre IGN, eau OpenStreetMap, rose des vents, relief IGN
  et import GeoJSON tous en place (voir `chantier-carte-interactive.md`).
  Chaque étape reste accessible en la sautant : le mécanisme « phase →
  première étape prête » (`gabarit.js`) passe par-dessus sans lien mort.

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
  (`initCompteMenu()`) : Mon projet, Préférences (`compte.php`, faite le
  2026-09-18), puis les entrées ajoutées par `initProjets()`, Se
  déconnecter. `initTermes()` arme la bulle de tout
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
  `parReference()`, `COTE_MAX_M` — 600 à l'origine, voir correction
  ci-dessous) et `public/api-cadastre.php`
  (POST, lecture seule — `roleSur() !== null`, pas `peutEcrireSur()` : on
  regarde, on n'écrit rien). `conception-etude-site.php` ajoute
  `apiCadastre` à la config JSON et la case « Voir le cadastre » dans le
  panneau Données — `calques.js` savait déjà s'en servir depuis la
  Phase 5 (gardé derrière `cfg.apiCadastre`), aucune ligne de JS à
  changer. Vérifié en Chrome headless (CDP) sur le Champ-de-Mars : 50
  parcelles IGN affichées en trait fin par-dessus la carte, `SELECT
  COUNT(*) FROM calque_element` reste à zéro après usage, la garde des
  600 m de côté refuse poliment une emprise trop large, anonyme et site
  non ouvert au compte tous deux refusés (403). **Correction du
  2026-09-18** : 600 m était en pratique injoignable — au zoom maximal
  de `carte.js` (18), un écran ordinaire affiche déjà 700 à 900 m de
  côté, donc le message « zoomez » restait affiché même zoom au
  maximum (signalé par le porteur, capture à l'appui). `COTE_MAX_M`
  remonté à 2000.
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
- **La rose des vents — Phase 8 du chantier carte, 2026-09-16.**
  `src/Web/Vents.php` (port proche : `rose()`/`agreger()`, 16 secteurs,
  `ANNEES_DEFAUT=5`, `SEUIL_FORT_KMH=20.0`). Pas d'API JSON séparée — un
  simple `<form method="post" action="rose">` qui rend la même page,
  comme la référence, mais rangé dans le panneau Données
  (`#vents-rose-bloc`) plutôt qu'une section documentaire toujours
  visible : il n'apparaît que calque « vents » choisi, et le panneau se
  rouvre tout seul sur ce calque après l'envoi pour que le résultat soit
  visible sans reclic. `dessinerElement()` savait déjà tracer les flèches
  de vent avec épaisseur selon l'intensité depuis la Phase 5 — non
  branché à une vraie rose jusqu'ici, mais aucune ligne de JS à changer.
  Vérifié sur un point réel (Limoges — même station que la vérification
  de convention météorologique du dépôt de référence) : rose SVG +
  tableau des 5 secteurs dominants affichés, message dédié si les
  coordonnées manquent. Open-Meteo a répondu « occupé » pour de vrai
  pendant des essais rapprochés — dégradation correcte, comme Overpass en
  Phase 7.
- **Le relief sur la carte — Phase 9 du chantier carte, 2026-09-16.**
  `src/Relief.php` (port proche, sans `iso()` — la vue 3D reste hors
  périmètre) et `src/Web/Altimetrie.php` (port proche, filtre le
  sentinel IGN `z=-99999`). `public/api-altitude.php` — **sans
  équivalent direct côté référence**, qui relève sa grille sur une page
  séparée (`relief.php`, avec vue 3D et côté/pas saisis à la main, tous
  deux exclus ici) ; le fichier référence du même nom sert une tout
  autre fonction. Ici le carré à relever se déduit de l'emprise du fond
  déjà tracé (`Calques::empriseDuTerrain()`), comme l'eau en Phase 7 —
  rien à saisir, le pas s'ajuste pour viser ~40×40 points. Un seul
  relevé par site, le suivant remplace le précédent. Les courbes ne sont
  pas stockées : `conception-etude-site.php` les recalcule à chaque
  chargement (même logique que la référence sur `carte.php`), et
  `api-altitude.php` les calcule aussi dans sa réponse pour un affichage
  immédiat. `dessinerElement()` savait déjà tracer un élément `grille`
  depuis la Phase 5, jamais exercé faute de donnée réelle jusqu'ici.
  Vérifié sur un relevé réel en haute montagne (Hautes-Alpes,
  44.9319/6.2331) : 1681 points en 12 appels IGN, 538 courbes de niveau
  affichées avec altitudes, pente moyenne 108,7 % exposée nord-ouest ;
  un second relevé remplace le premier ; hors couverture IGN (testé sur
  Londres) le message dédié apparaît et rien ne s'enregistre ; site
  étranger et accès anonyme refusés (403). L'amincissement au-delà du
  plafond de segments vérifié directement sur les données réelles
  récupérées (fonction pure) : 538 segments ramenés à 67 avec un plafond
  de 100.
- **Import GeoJSON — Phase 10 du chantier carte, dernière phase,
  2026-09-16. Le chantier « carte interactive du terrain » est
  terminé, dix phases faites.** `src/Geojson.php` (port proche :
  `FeatureCollection`/`Feature`/`GeometryCollection`, géométries simples
  et multiples — un multiple donne un tracé par morceau —, inversion
  `[lon,lat]`→`[lat,lon]`, limites 4 Mo / 1500 points par tracé / 300
  tracés). `conception-etude-site.php` gère l'action POST `importer`
  (upload multipart, calque cible choisi dans le formulaire, indépendant
  du calque actif à l'écran) — un simple POST qui rend la même page,
  comme la référence sur `carte.php` : contrairement au cadastre/à
  l'eau/aux vents/au relief, importer est un geste ponctuel qui n'a pas
  besoin de rester sur une vue précise. Formulaire dans le même panneau
  Données que tout le reste, donc sous la même garde `$peutEcrire`.
  Vérifié : import réel (polygone + ligne avec `elevation`) — inversion
  lon/lat correcte en base, anneau fermé dédupliqué, altitude devenue
  libellé ; coordonnées Lambert 93 refusées avec le message dédié, rien
  écrit ; 350 tracés synthétiques → exactement 300 posés avec message de
  troncature ; cas limites (fichier vide, JSON illisible, sans
  géométrie, type inconnu, trop gros, ligne à 3000 points allégée à
  1500±1) vérifiés en appelant `Geojson::lire()` directement — le
  plafond `upload_max_filesize` du serveur local (2 Mo) est de toute
  façon plus bas que celui de la classe (4 Mo), un vrai upload ne peut
  donc pas exercer ce cas précis ici.
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
- **Le chantier « carte interactive du terrain » est déployé —
  2026-09-16.** `bin/deployer.py fichiers` a envoyé les 20 fichiers des
  Phases 4 à 10 (10 dans `public/`, 10 dans `src/`), sans changement de
  schéma ni de `config/base.php`. Vérifié en ligne au `curl` juste
  après : page protégée redirige l'anonyme (302), les quatre API
  (`api-calques`/`api-cadastre`/`api-eau`/`api-altitude`) refusent le
  POST anonyme (403), `assets/js/calques.js` se sert (200), `src/`
  reste protégé (403). **Reste à vérifier pour de vrai** : que les
  appels sortants du chantier (tuiles OSM, IGN Carto/Altimétrie,
  Overpass, Open-Meteo) passent bien depuis les serveurs alwaysdata —
  un hébergement mutualisé filtre parfois le curl sortant, et rien de
  cela n'a encore été testé en production, seulement en local. À faire
  à la prochaine reprise avant de considérer le chantier vraiment fini
  en ligne.
- **La base de végétaux — Phase 1 du chantier « scénario de
  plantation », 2026-09-18.** `src/Web/Especes.php` sur la table
  `espece` (déjà en base depuis un chantier antérieur, mais jamais
  utilisée — pas même côté référence, où le formulaire du scénario
  reste un champ texte libre avec une liste de suggestion sans lien
  vers la table). Cadre partagé (`site_id=0`, 46 espèces curatées dans
  `sql/02-especes-seed.sql`, DML rejoué par `bin/dev.py init`) +
  surcharges par site, la surcharge primant toujours. `bin/
  catalogue-plantae.py` exporte 1271 noms depuis `plantae-corpus` vers
  `contenu/catalogue-scenario.json`, pour la suggestion de saisie de la
  Phase 4 — aucune donnée agronomique copiée, seulement des noms, comme
  la référence. **Colonne `variete` ajoutée à `espece`** en cours de
  Phase 1 (`uq_espece` étendue à `(site_id, nom_scientifique,
  variete)`) : signalé par le porteur qu'une variété peut changer la
  strate ou le rôle du tout au tout (tournesol conduit à une fleur, pour
  la biomasse, contre tournesol classique) — la clé n'aurait pas pu le
  porter sinon. **Bug trouvé en testant** : `retirer()` ne protégeait
  le cadre que par sa clause SQL (`WHERE site_id = ?`), qui matcherait
  `site_id = 0` aussi bien qu'un site réel — corrigé par un refus
  explicite avant toute requête. Vérifié directement (pas de page
  encore) : liste des 46 espèces, priorité d'une surcharge locale sur
  le cadre pour un même couple `(nom_scientifique, variete)`, espèce
  absente des deux niveaux renvoyant `null` sans erreur (reste
  saisissable en texte libre), retrait d'une surcharge qui rend la
  lecture au cadre, refus de `retirer()` sur le cadre. Détail complet :
  `chantier-scenario-plantation.md` § Phase 1.
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
- **Le parcours au complet, sauf une étape reportée** —
  dix-neuf des vingt étapes de `etapes.js` portent `pret: true` (reste
  `conception-materiel-et-eau`).
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
  compétence métier, groupés par phase, citant `connaissances/`.
- **`connaissances/` importée du projet `syntropie` — 2026-09-17.**
  89 fiches markdown + 1 JSON, copiées telles quelles (`diff -rq` contre
  la source : identiques) depuis
  `/home/sylvain/projets/syntropie/connaissances/`, ce projet étant voué
  à disparaître. Les fichiers de `skills-map/nodes/*.md` citent déjà
  `connaissances/<domaine>/` en chemin relatif — rien à réécrire là,
  ces citations résolvent maintenant en local. **Restent externes,
  non importées à la demande du porteur** : `application/` (le dépôt de
  référence PHP) et les deux skills `atelier-syntropie-interface-
  layout`/`atelier-syntropie-uiux` (déjà synchronisés au niveau du
  compte Claude, donc probablement disponibles même après disparition
  du dossier). Quelques noeuds de `skills-map/` citent aussi
  `outils/fiches-terrain/` ou `referentiels/` du dépôt `syntropie`
  (`diagnostic-des-sols.md`, `formation-joala.md`,
  `cultures-associees.md`) — ces renvois-là resteront pendants tant que
  ces dossiers-là ne sont pas eux aussi importés.
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
- **Le premier compte réel en production.** `sylvain` créé sur
  `https://jubile.alwaysdata.net/syntropia/` (hash calculé en local par un
  simple `password_hash()`, jamais via `php bin/compte.php` en direct —
  voir le footgun plus haut), rôle administrateur, connexion vérifiée de
  bout en bout. La base de production ne contenait jusque-là aucun compte
  ni aucun site.
- **Le chantier « scénario de plantation » (Phase 1) déployé en
  production.** `bin/deployer.py fichiers` puis `base`. La colonne
  `espece.variete` n'existait pas encore côté serveur — la table `espece`
  y avait été créée par un déploiement antérieur, avant l'ajout de cette
  colonne, et `CREATE TABLE IF NOT EXISTS` ne rattrape jamais une table
  déjà là. Un premier essai (`DROP TABLE` pour repartir propre) a été
  bloqué par le classificateur de sécurité du mode automatique — à
  raison, action destructive en production — puis reposé et exécuté en
  `ALTER TABLE … ADD COLUMN IF NOT EXISTS` après accord explicite du
  porteur. Écart trouvé en déployant, corrigé au passage : `bin/
  deployer.py` a sa propre liste de schémas dans sa fonction `base()`,
  distincte de celle de `bin/dev.py` — `02-especes-seed.sql` n'y figurait
  pas.
- **Le projet réel rapatrié de la production vers la base locale —
  2026-09-18.** Le compte local `sylvain` portait déjà un site du même nom
  (« La Vadaux ») mais avec un contenu divergent et plus pauvre : pas de
  coordonnées, pas de parcelle, des réponses de test différentes.
  Remplacement du contenu local par celui de production plutôt que
  fusion : coordonnées du site, les 9 réponses réelles (les 10 réponses de
  test locales, différentes, ont été écrasées — pas fusionnées, choix
  assumé), la parcelle P1 (sans dimensions, comme en production), les
  deux tracés réels du calque (le fond du terrain, un plan d'eau). Fait
  par un script PHP ponctuel, PDO en direct vers les deux bases — la
  production est joignable directement depuis ce poste, pas seulement
  depuis le serveur — non conservé, ce n'est pas un outil du dépôt.
- **Plusieurs bugs réels trouvés et corrigés sur `conception-etude-
  site.php` et `conception-parcelles.php`, tous signalés par le porteur
  en testant pour de vrai — 2026-09-18.**
  - Le rappel du cadastre (zoom trop grand, IGN injoignable…) ne vivait
    que dans une ligne discrète du panneau Données, facile à manquer —
    reparaît maintenant en bandeau sur la carte, même traitement que
    l'alerte « le lieu n'est pas encore posé ».
  - Le contour des étiquettes de la carte (`.calque-etiquette`) calait sa
    couleur sur le thème de l'appli (sombre en thème sombre) alors que
    les tuiles OpenStreetMap, elles, restent toujours claires — fixé en
    blanc, comme le fait déjà le cercle d'un point.
  - Le panneau « Chercher l'eau » gardait toute l'explication détaillée
    (pourquoi 100 m, ce qui se pose sur le calque…) affichée en
    permanence — raccourci au seul geste requis (« Tracez d'abord les
    limites du terrain »), le reste ayant sa place dans la documentation,
    pas répété à chaque ouverture.
  - Sur `conception-parcelles.php` : la mesure de largeur et les repères
    nord/sud/est/ouest du plan restaient calés sur les valeurs par défaut
    (12 × 8 m) après une saisie — recalculés maintenant à chaque
    redessin.
  - Ajout d'un sens des planches (Nord ↔ Sud par défaut, Est ↔ Ouest en
    option) — `connaissances/livre-bienvenue-en-syntropie/ch08` note que
    le sens compte peu dans un petit potager syntropique (les strates
    émergentes dominent la lumière quel que soit le sens), d'où un choix
    plutôt qu'un sens imposé.
  - « Déplacer un bord » n'avait qu'un minimum (0,1 m) — une bordure
    pouvait dépasser le bord opposé et sortir de la parcelle. Plafonné à
    ce que l'autre bord du même axe laisse.
  - « Enregistrer ces mesures » (pour la carte du terrain) rechargeait la
    page à chaque clic, perdant tout le plan dessiné au-dessus (purement
    client, jamais persisté) — passe maintenant par `fetch()`, sans
    rechargement. Un vrai bug trouvé au passage : `form.action` renvoyait
    l'input caché nommé `action` (masquant la propriété native du même
    nom) plutôt que l'URL — corrigé en utilisant `location.href`.
- **Le plan de la parcelle (bordures, planches, dimensions) reste un
  prototype non persisté — signalé de nouveau par le porteur (« P1 fait
  toujours 8 × 12 m ») après les corrections ci-dessus, qui n'y changent
  rien : ce n'est pas un bug, c'était la limite déjà documentée depuis la
  Phase 3 du chantier carte.** Plan phasé posé dans
  `chantier-plan-de-parcelle.md` (4 phases).
- **Le plan de parcelle persiste enfin — Phase 1 du chantier « plan de
  parcelle », 2026-09-18.** `src/Web/Parcelles.php` gagne
  `enregistrerPlan()` (longueur, largeur, bordure externe des quatre
  côtés en JSON dans `parcelle.plan`, sens des planches) avec un verrou
  optimiste à l'identique de la référence — hash de la ligne `parcelle`
  entière, `SELECT … FOR UPDATE`, message dédié si le plan a changé
  entretemps plutôt qu'un écrasement silencieux. `conception-
  parcelles.php` préremplit tout depuis la base à l'ouverture (une
  parcelle jamais enregistrée garde les valeurs par défaut d'avant,
  12 × 8 m, sans erreur) ; un bouton « Enregistrer le plan » à côté des
  champs, `conception-parcelles.js` l'envoie par `fetch()` sans
  recharger la page (le dessin resterait sinon désynchronisé de ce qui
  vient d'être écrit). **Écart avec le plan initial, tranché en
  codant** : `largeur_m` rejoint `longueur_m` dans `enregistrerPlan()`
  plutôt que dans `enregistrer()` (« Pour la carte du terrain ») — les
  deux formulaires auraient sinon porté chacun un champ pour la même
  colonne `longueur_m`, pouvant diverger sans avertissement ; le champ
  « Longueur d'une planche (m) » du second formulaire est retiré,
  redondant avec « La parcelle » juste au-dessus. **Bug préexistant
  trouvé en testant** : le message d'erreur de « Pour la carte du
  terrain » utilisait `.join()` sur `$erreurs`, un tableau PHP à clés
  nommées donc un objet une fois décodé en JSON — `.join()` y échoue
  silencieusement, rattrapé par un `.catch()` qui affichait un message
  trop générique ; corrigé (`Object.values(...).join(...)`) aux deux
  formulaires. Vérifié en CDP : dimensionner puis recharger fait
  revenir chaque valeur telle quelle ; un jeton de version périmé
  refuse l'écriture avec le message dédié, rien n'est changé en base ;
  le sélecteur « Poser une parcelle » de la carte interactive continue
  de fonctionner sans erreur. Détail complet :
  `chantier-plan-de-parcelle.md` § Phase 1.
- **`nb_planches` calculé, plus saisi à la main — Phase 2 du chantier
  « plan de parcelle », 2026-09-18.**
  `Parcelles::enregistrerPlan()` calcule `nb_planches` lui-même (mêmes
  constantes `PLANCHE_M`/`PASSE_PIED_M` que le calcul JS, exposées au
  client par `conception-parcelles.php` plutôt que recopiées en dur une
  seconde fois — sans quoi les deux auraient pu diverger) ;
  `Calques::parcellesAPlacer()` lit `largeur_m` directement plutôt que
  de la recalculer. **Écart avec le plan initial, question posée au
  porteur avant de coder** : une fois `nb_planches` calculé,
  `largeur_planche_m`/`passe_pied_m` (les deux derniers champs de
  « Pour la carte du terrain ») ne nourrissaient plus rien nulle part —
  toute la section a été retirée plutôt que de garder deux champs qui
  ont l'air de compter mais ne comptent plus (choix confirmé par le
  porteur, pas supposé). Les deux colonnes restent en base, non
  migrées, simplement plus lues ni écrites. Vérifié en CDP : un plan
  enregistré (15 × 6 m, nord-sud, bordures à 0,40 m) écrit
  `nb_planches = 8` en base, exactement le compte affiché en direct
  par le plan avant l'enregistrement ; le sélecteur « Poser une
  parcelle » de la carte interactive affiche aussitôt « 6 × 15 m » sans
  qu'aucun champ séparé n'ait été rempli. Détail complet :
  `chantier-plan-de-parcelle.md` § Phase 2.
- **Orientation d'une parcelle posée par rectangle, corrigée —
  2026-09-18.** Signalé par le porteur juste après la Phase 2
  ci-dessus : une parcelle de 17 × 4 m se posait tournée de 90°.
  `coins()` (`assets/js/calques.js`, Phase 5 du chantier carte, écrite
  avant que `largeur_m`/`longueur_m` aient un sens de direction fixe)
  faisait « largeur d'est en ouest, longueur du sud au nord » —
  l'inverse exact des libellés de `conception-parcelles.php`
  (« Longueur (est ↔ ouest) », « Largeur (nord ↔ sud) »). Invisible tant
  que ces deux colonnes n'étaient que des valeurs recalculées sans
  direction fixe ; visible dès qu'elles en portent une vraie (Phases 1-2
  du chantier « plan de parcelle »). N'affecte que les nouveaux
  placements — un tracé déjà enregistré garde ses points géographiques
  tels quels, non recalculés à l'affichage. Vérifié en CDP : 17
  (longueur, est-ouest) × 4 (largeur, nord-sud) se pose maintenant en
  rectangle large et court, dans le bon sens.
- **Rattrapage homogène et planche de bord à portée simple — Phase 3 du
  chantier « plan de parcelle », 2026-09-18.** Signalé par le porteur
  après usage réel de la Phase 1 : les planches se répartissaient par pas
  fixe (1,20 m) sans absorber le reste, et une planche qui touche une
  bordure par sa longueur (au lieu d'un passe-pied) restait aussi large
  qu'une planche intérieure — inatteignable sur plus d'un mètre depuis le
  passe-pied voisin. `BORD_PLANCHE_M=0.6` fixe désormais la première et la
  dernière planche de chaque rang ; le reste (`disponible −
  2×BORD_PLANCHE_M − idéal des planches intérieures`) se distribue au
  choix de l'utilisateur sur les planches intérieures ou sur les
  passe-pieds (`rattrapage`, stocké dans `parcelle.plan`), jamais les
  deux à la fois. Vérifié en CDP sur 12 × 8 m (bordures 0,40 m,
  nord-sud) : les deux modes remplissent exactement la largeur
  disponible, les deux planches de bord mesurent 0,60 m dans les deux
  cas. Détail complet : `chantier-plan-de-parcelle.md` § Phase 3.
- **Bordure interne, pour la biomasse — Phase 4 du chantier « plan de
  parcelle », dernière phase, 2026-09-18. Le chantier « plan de
  parcelle » est terminé, les quatre phases faites.** Signalé par le
  porteur : les « tortues » d'Anaëlle Théry portent une bordure externe
  (bloque-herbe, coupe-vent, défense contre les animaux) et une bordure
  interne (fournisseurs de biomasse), or seule l'externe existait dans
  l'atelier. Vérification préalable contre `connaissances/
  livre-bienvenue-en-syntropie/ch14-une-histoire-de-tortue.md` : la
  source décrit en réalité une bande unique à deux rangs de plantation,
  pas deux anneaux séparés — écart signalé au porteur, qui a confirmé
  vouloir malgré tout deux bordures géométriquement distinctes, plus
  visuel. `bordures.interne` rejoint `bordures.externe` dans
  `parcelle.plan`, même forme (quatre côtés, actif/largeur), positionnée
  immédiatement à l'intérieur de l'externe (ou dès le bord de la
  parcelle si l'externe est inactive sur ce côté) ; géométrie seulement,
  aucune espèce assignée — ça suppose `scenario_ligne`, toujours absent.
  Distincte visuellement (vert biomasse contre or de l'externe) ;
  modifiable par les champs texte seulement, pas de glisser-déposer sur
  le dessin (scopé à l'externe). Vérifié en CDP, y compris le cas limite
  laissé pour la fin (bordure interne active sur un côté où l'externe
  est inactive) : le rectangle interne part bien du bord réel de la
  parcelle, sans erreur, et l'état survit à un rechargement complet.
  Détail complet : `chantier-plan-de-parcelle.md` § Phase 4.
- **Bordure interne : un vrai anneau, corrigé — 2026-09-18.** Signalé par
  le porteur : les côtés est/ouest de la bordure interne allaient
  jusqu'au bord de la parcelle et recouvraient les bordures externes
  nord/sud (et nord/sud jusqu'aux externes est/ouest). Chaque côté
  interne s'arrête maintenant aux bordures externes voisines, et
  est/ouest s'arrêtent aux côtés nord/sud internes plutôt que de les
  recouvrir ; un côté dont l'externe est inactive part du bord de la
  parcelle. Dessin seulement (`redessiner()`), aucune donnée ni
  calcul changés. Vérifié en CDP (12 × 8 m, externe 0,40, interne 0,30) :
  rectangles aux bonnes coordonnées, capture sans recouvrement, et cas
  externe nord+ouest inactives.
- **Surface des planches de culture affichée séparément — 2026-09-18.**
  Demandé par le porteur : « Surface utile » (déjà affichée) compte tout
  ce que les bordures laissent, passe-pieds compris — pas la vraie
  surface cultivable. Nouvelle ligne « Surface des planches » dans « Ce
  que la géométrie donne » (`conception-parcelles.php`/`.js`) : somme des
  largeurs de planches du rang, multipliée par la profondeur utile
  perpendiculaire au sens des planches — recalculée à chaque redessin,
  comme le reste du tableau. Vérifié en CDP : distincte de la surface
  utile, se met à jour correctement au changement de dimensions et de
  sens des planches.
- **Zone climatique USDA estimée, plus seulement saisie — 2026-09-18.**
  `src/Web/ZoneUsda.php` + `public/api-usda.php` (POST, connecté + jeton +
  `roleSur()`, lecture seule) ; bouton « Estimer » à côté du champ de
  `definition-site.php`, qui remplit le champ — toujours modifiable, la
  saisie de la personne prime — depuis la position du formulaire (pas
  besoin qu'elle soit déjà enregistrée). **Méthode** : la définition USDA
  elle-même refaite — moyenne, sur 1991-2020 (période de la carte USDA
  2023), du minimum annuel de température, rangée par tranches de 5 °F
  (1a dès −60 °F) ; un seul appel Open-Meteo (`temperature_2m_min`
  quotidien, même source que la rose des vents, ~190 Ko). Aucun service
  USDA n'accepte de coordonnées hors États-Unis, d'où le calcul maison.
  **Estimation, pas mesure** : maille de quelques km, une cuvette à air
  froid sera plus froide — dit à l'écran (panneau Informations), un
  écart d'une demi-zone avec une carte publiée est normal. Contrôle sur
  des lieux connus : Bordeaux 9a, Paris 8b, Marseille 10a, Limoges 9a
  (limite 8b/9a, 20,1 °F). Vérifié en CDP : sans position → message
  dédié, position posée → champ rempli et détail affiché à la place de
  l'aide (une ligne, aucune hauteur ajoutée à l'état par défaut),
  saisie manuelle enregistrée et rechargée, seuils de conversion
  vérifiés (−40 °C → 3a, plafonds 1a/13b). **Trouvé en testant** :
  résolution DNS locale parfois > 5 s (`CONNECTTIMEOUT` porté à 10 s ici ;
  `Vents.php` garde 5 s). **Constat préexistant** : à 412×800 et
  360×740, `definition-site.php` débordait déjà avant ce changement
  (« Continuer » à 790 px pour un bureau finissant à 757 px) — à 412×915
  ça tient ; à traiter à part si les petits écrans bas comptent. Après
  une estimation, le message ajoute environ 15 px de plus.
- **Plusieurs projets par compte — Phase 1 du chantier « inscription par
  parrainage », 2026-09-18.** Le porteur veut deux cas : inviter
  quelqu'un à créer un compte (il ouvre son projet), et inviter quelqu'un
  à rejoindre un projet existant (il peut aussi ouvrir le sien) — le
  second rouvre « un seul projet par compte », décision retirée plus haut.
  Décisions tranchées avec lui : tout utilisateur connecté peut inviter à
  créer un compte ; le porteur seul invite à son projet, en choisissant
  `contributeur` ou `lecteur` ; passage d'un projet à l'autre par un
  sélecteur du panneau gauche, pas par un écran de choix à la connexion.
  `Sites::deCompte()`/`activer()`, `pourCompte()` renvoie le projet actif ;
  `public/api-projets.php` ; `gabarit.js` (`initProjets()`) pose « Nouveau
  projet » dans le menu du compte et, s'il y a plusieurs projets, la
  section « Mes projets » en tête du panneau gauche — **aucune des ~27
  pages n'a été touchée** (le menu du compte est dupliqué dans chacune,
  d'où l'injection JS). Vérifié en CDP : sélecteur absent avec un seul
  projet, deux projets, bascule retenue au rechargement, réponses de
  Définition bien séparées par projet, cookie forgé vers un projet
  étranger sans effet (retombe sur le premier), POST d'activation d'un
  projet étranger 403, anonyme 403. Phases 2 à 4 (page « Inviter »,
  inscription rattachée à un projet, rejoindre avec un compte existant)
  pas commencées ; détail et points à vérifier (droits d'un lecteur sur
  ses propres réponses, retrait d'un membre) dans
  `chantier-inscription-parrainage.md`.
- **Page « Inviter » — Phase 2 du chantier « inscription par
  parrainage », 2026-09-18.** `public/inviter.php` (hors parcours, sur le
  gabarit) : ouvrir un code pour un compte seul, ou — pour le porteur du
  projet actif — pour rejoindre ce projet en `contributeur` ou `lecteur` ;
  lister les siens (états, paginés par 3) et retirer ceux qui n'ont pas
  servi. `Invitations::ouvrir()` porte les garde-fous (porteur seulement,
  rôle jamais « porteur », **10 codes ouverts au plus** — ajout non prévu au
  plan, sans quoi la page est un robinet à codes) ; nouvelle colonne
  `invitation.role_site`. « Inviter » s'ajoute au menu du compte par
  `gabarit.js`. **Piège trouvé** : `ADD COLUMN IF NOT EXISTS` (celui de
  l'incident `espece.variete`) n'existe que sur MariaDB, or le poste local
  est en MySQL 8 — la migration passe par `information_schema` + `PREPARE`
  et tourne des deux côtés ; **en production, `bin/deployer.py base` l'a
  posée le 2026-09-18** (MariaDB 11.4). Vérifié en CDP, y compris les refus (contributeur, POST forgé,
  11e code, code d'un autre parrain, anonyme). Un mauvais cas de mise en
  page trouvé et corrigé en mesurant : à 1024×600, le cadre était tronqué
  en silence par `overflow:hidden` — jamais visible sans mesure directe du
  cadre contre le bureau. **Pas encore câblé** : `inscription.php` ignore
  toujours `site_id` (un code « Rejoindre » ouvre un compte sans
  rattachement) — Phase 3. (Phases 2 à 4 déployées ensemble.)
  Reste non tenu : 320×568. Détail : `chantier-inscription-parrainage.md`.
- **S'inscrire avec un code de projet — Phase 3 du chantier « inscription
  par parrainage », 2026-09-18.** `inscription.php` dit le projet et le
  rôle avant toute question, puis, une fois le code consommé,
  `Sites::rejoindre()` pose `membre_site` et `participant`. **Le rôle
  « lecteur » n'existait pas vraiment** : sur 14 pages qui écrivent,
  une seule vérifiait le droit d'écriture — la garde est maintenant au
  point d'écriture (`Reponses::enregistrer()` ignore les réponses d'un
  lecteur ; `definition-site.php` et `conception-parcelles.php` refusent
  leurs écritures, 403 JSON pour l'AJAX du plan), `gabarit.js` préfixe le
  titre « Lecture seule — » et grise les champs, **sur les pages du
  parcours seulement** (première version, aussi appliquée à « Nouveau
  projet »/« Inviter » : un lecteur ne pouvait plus ouvrir son projet,
  un champ grisé n'étant pas envoyé — trouvé en testant). Compte et projet
  sont dans deux bases : compte → **code consommé** (garde atomique) →
  rattachement ; un échec de rattachement laisse le compte et le dit.
  Vérifié en CDP : lecteur (POST forcé sans effet, mais son propre projet
  s'ouvre et s'écrit), contributeur, compte seul, projet disparu, code
  réutilisé. **Correction de ma vérification de la Phase 2** : la garde de
  `Invitations::ouvrir()` avait été « testée » par un `php -r` direct — donc
  sur la **production** (`config/base.php` l'emporte), deux `SELECT` sur
  `membre_site`, aucune écriture — et ne prouvait rien ; refaite en local
  avec `config/local.env` exporté. Reste ouvert : un *contributeur* peut modifier coordonnées et parcelles du projet.
- **Voir et retirer les participants d'un projet — 2026-09-18.** Demandé
  par le porteur entre les Phases 3 et 4. `public/membres.php` (réservée au
  porteur du projet actif, menu « Participants » posé par `gabarit.js`) :
  liste paginée par 4 (nom, rôle, depuis quand) et « Retirer l'accès » avec
  confirmation. `Sites::membres()`/`retirerMembre()` — garde-fous dans le
  service : seul un porteur retire, jamais un porteur, refus si déjà
  retiré ; `Comptes::nomsParIds()`. **Retirer ne supprime que
  `membre_site`** : la personne (`participant`) et ses réponses restent, et
  réinvitée elle les retrouve. **Bug de la Phase 3 trouvé et corrigé** :
  `participant` est unique sur `(site_id, nom)` — deux « Jean » dans un projet
  faisaient échouer `rejoindre()` sans cause visible ; le second devient
  « Jean (72) ». Vérifié en CDP (pagination, annulation, retrait, refus par
  POST direct de porteur/étranger/déjà retiré/contributeur, retiré → « ouvrir
  un projet », réinvitation). Non tenu : 320×568. Reste à décider : changer un
  rôle sans retirer, quitter soi-même un projet, invitations ouvertes d'un
  membre retiré. Voir `chantier-inscription-parrainage.md`.
- **Rejoindre un projet avec un compte existant — Phase 4, dernière, du
  chantier « inscription par parrainage », 2026-09-18. Le chantier est
  terminé.** `public/rejoindre.php` : on lit le code, on **montre** le projet,
  le parrain et le rôle, puis on confirme ; le projet s'ajoute à ceux du
  compte et devient actif. Un lien d'invitation ouvert par un compte connecté
  y mène (au lieu de le renvoyer sur ses parcelles), un compte sans projet en
  trouve le lien sur « ouvrir un projet », et le menu du compte a
  « Rejoindre un projet ». **On ne brûle pas un code pour rien** : code d'un
  compte seul, projet déjà accessible (dont le code du porteur lui-même),
  projet disparu — tous détectés avant de consommer, et le message dit que le
  code reste valable. Aucun service nouveau (`parCode`/`consommer` +
  `Sites::rejoindre`/`activer`). Vérifié en CDP sur tous ces cas, plus code
  consommé entre l'aperçu et la confirmation, POST à jeton faux, et un compte
  sans projet qui rejoint en contributeur et écrit. Tient à 320×568.
  **Déployé le 2026-09-18** — voir la puce « Déploiement du parrainage »
  plus bas. Reste hors plan : changer un rôle sans retirer, quitter
  soi-même un projet, invitations ouvertes d'un membre retiré.
- **Déploiement du parrainage, de la zone USDA et de la bordure interne —
  2026-09-18.** Huit commits, 17 fichiers (11 `public/`, 5 `src/`,
  `sql/00-comptes.sql`). **Ordre suivi : `base` d'abord** (la colonne
  `invitation.role_site` est nullable — l'ancien code tournait encore
  pendant l'intervalle), **puis `fichiers`**. Avant : l'état de la production
  lu sans rien changer (MariaDB 11.4.13, PHP 8.4.25, un compte, un projet, zéro
  invitation, l'utilisateur a tous les droits) ; la migration n'avait tourné
  que sur MySQL — sa forme `information_schema` + `PREPARE` a été validée sur
  MariaDB **sans l'exécuter** (l'`ALTER` seulement préparé) ; Open-Meteo
  joignable depuis alwaysdata (200 en 0,28 s, curl PHP présent). Après : la
  colonne existe, données inchangées (1 compte, 0 invitation, 1 projet), les
  17 fichiers identiques à la copie locale (`diff` par SSH), pages du
  parcours 302 pour un anonyme, `api-projets`/`api-usda` 403/405, `src/`,
  `sql/` et `config/base.php` 403, `inscription.php` 200. **Non vérifié en
  production** (règle du 2026-09-18 : on teste en local) : les parcours
  eux-mêmes — inviter, s'inscrire avec un code, rejoindre, retirer, estimer
  la zone USDA — à essayer avec un vrai compte. Retour arrière : la colonne
  est inoffensive pour l'ancien code, re-déployer les fichiers d'un état
  antérieur suffit.
- **Préférences — `compte.php`, 2026-09-18.** Le lien existait, grisé,
  dans le menu du compte de 25 pages depuis le début ; `Authentification::
  exige()` y renvoyait déjà un compte dont le mot de passe doit changer
  (`?mdp=1`) — un lien qui menait à une 404. Trois onglets en CSS pur
  (`:has()`, un panneau à la fois, rien ne défile) : **Mon compte**
  (nom, identifiant, courriel, rôle, date d'inscription, « arrivé par
  l'invitation de… », projets avec le rôle sur chacun, le projet actif
  marqué, trois visibles puis « et N autres »), **Nom affiché**, **Mot de
  passe** (l'actuel exigé). Choix tranchés avec le porteur : le courriel
  reste en lecture seule (rien ne l'envoie ni ne le vérifie). Le mot de
  passe compte ses échecs et se ralentit comme la connexion (une session
  volée ne doit pas pouvoir deviner le mot de passe pour le changer),
  regénère l'identifiant de session, et efface le drapeau `doit_changer_mdp`.
  `parrainDe()` — jamais appelée jusque-là — sert enfin, corrigée : la
  **première** invitation utilisée, puisqu'un compte qui rejoint d'autres
  projets par code en accumule d'autres. Le lien du menu est activé dans les
  25 pages d'un seul remplacement (le balisage `a-venir` y était identique).
  **Choix assumé** : changer le nom affiché ne touche pas `participant.nom`,
  le nom que la personne portait en entrant dans un projet — c'est lui qui
  identifie ses réponses ; la liste des participants montre, elle, le nom
  affiché. **Non fait** : les autres sessions ouvertes du compte ne sont pas
  fermées au changement de mot de passe (sessions PHP en fichiers, non
  énumérables). Vérifié en CDP : récapitulatif d'un compte invité à cinq
  projets et d'un compte sans parrain ni projet, onglets, nom vide/trop
  long/valide (bandeau mis à jour, message flash lu une fois), mot de passe
  faux/court/différent/identique/rafale (ralentissement)/valide, ancien
  mot de passe refusé et nouveau accepté, jeton forgé refusé, changement
  forcé (toute autre page renvoie vers `compte.php?mdp=1`, redirection après),
  anonyme → connexion. Mise en page mesurée jusqu'à 320×568 : le
  récapitulatif la tronquait de 14 px — corrigé en retirant une note
  redondante avec le panneau Informations et en masquant le titre (qui
  répète le bandeau) sur les écrans peu hauts.
- **La conduite — Phase 2 du chantier « scénario de plantation »,
  2026-09-18.** `src/Conduite.php` (syntropie ou jardin-forêt : vocabulaire
  des strates 4 ou 9, unité de conception, sens de la taille, et une
  `reserve()` honnête : les textes d'aide sont écrits pour la syntropie),
  `src/Web/Heritage.php` (le plus précis gagne : parcelle, puis site, puis
  syntropie par défaut). Choix du site dans `definition-site.php`, choix de
  la parcelle dans le panneau « Plan » de `conception-parcelles.php` (vide =
  hérite du site), enregistré avec le plan sous le même verrou de version.
  Décidé avec le porteur : les deux conduites, aide sur le syntropique
  seulement. Les treize strates de `Conduite` sont identiques à l'énumération
  SQL de `scenario_ligne.strate`. Vérifié en CLI (local) et en CDP, dont le
  conflit de version et le lecteur. **Constat préexistant, non corrigé** :
  `definition-site.php` déborde sur les écrans courts (à 1280×720 l'en-tête
  passe sous le bandeau, « Continuer » chevauche le pied — même mesure sans
  le bloc de conduite) ; mon bloc y est compacté sur téléphone pour ne pas
  aggraver 412×915, où la page tenait. Détail :
  `chantier-scenario-plantation.md` § Phase 2. Pas encore déployé.
- **Le backend du scénario — Phase 3 du chantier « scénario de plantation »,
  2026-09-18.** `src/Scenario.php` (les règles : périodes, 24 fonctions,
  emplacements, validation tout-ou-rien, position 0-28 dans le temps, ordre de
  remplissage), `src/Web/Scenarios.php` (`souhaitsDuSite()`, `productions()`,
  `enregistrer()` — ajout, modification, retrait, chacun sous `FOR UPDATE` et
  verrou de version), `Parcelles::scenario()`. Port **réduit** : rien ne touche
  `parcelle.plan` (`ImplantationBordures` reste hors périmètre). Écarts :
  en jardin-forêt le temps n'est pas lu (de placenta1 au climax) ;
  `productions()` refuse plutôt que tronquer les `VARCHAR(190)` ; les
  souhaits viennent de `objectifs_productions` du participant, à défaut du
  porteur. Testé par un script sur la **base locale** (82 vérifications, dont
  23 refus de saisie et le verrou de version) — pas d'endpoint, donc pas de
  `curl` ; le script s'arrête si l'hôte n'est pas `127.0.0.1`. Pas encore
  déployé. Détail : `chantier-scenario-plantation.md` § Phase 3.
- **La page du scénario — Phase 4, dernière, du chantier « scénario de
  plantation », 2026-09-18. Le chantier est terminé, quatre phases.**
  `public/conception-scenario.php`, étape 4 de Conception, `pret: true` ;
  « Le scénario » est devenu un vrai lien dans le panneau gauche de
  `conception-parcelles.php`. **Le tableau tient dans le bureau sans
  défiler** : en syntropie, une **frise SVG** (les sept périodes en
  colonnes, une voie par plante, hachures pour le retrait progressif) qui
  s'ajuste toujours à la place — la référence, elle, était un long
  document ; sur téléphone et en jardin-forêt (qui n'a pas d'axe du
  temps), une **vue par strate en pastilles** dont la taille se réduit
  jusqu'à tenir. Saisie dans le panneau Données, en deux onglets CSS purs
  (un végétal ; les productions de la parcelle), le guide de composition
  dans le panneau Informations (ordre de remplissage, 70-80 % de biomasse,
  au moins deux espèces par strate, lumière par strate selon Joala — avec
  la réserve de la source). Toute écriture passe par `Scenarios` ; POST
  suivi d'une redirection, la saisie n'est perdue que si elle a réussi.
  Vérifié en CDP : productions (dont « une autre production »), ajout /
  modification / « ajouter comme nouveau » / renommage avec et sans
  confirmation / retrait avec confirmation, **conflit de version**, strate
  d'un autre vocabulaire, conduite non choisie, jardin-forêt sans champs de
  temps, végétaux à « reclasser » quand la conduite change, lecteur (barres
  lisibles, tout le reste absent, POST forgé 403), envois forgés (jeton,
  action inconnue, production hors propositions, id d'une autre parcelle),
  17 végétaux sans défilement de 1024×600 à 1440×900 et sur téléphone
  jusqu'à 320×568, thème sombre. **Trois défauts trouvés en mesurant et
  corrigés** : le bureau centre son contenu (`align-items:center`), donc
  ni la frise ni les pastilles ne pouvaient se réduire — le cadre
  débordait de 134 px avec 17 végétaux ; l'onglet « Informations »
  recouvrait l'en-tête « Placenta 3 » (une page sans bandeau d'outils
  doit redéfinir `--haut-bandeaux`, comme `inviter.php`) ; et un compte
  sans parcelle voyait une page vide. Détail :
  `chantier-scenario-plantation.md` § Phase 4. **Pas encore déployé.**
- **Déplacer les étiquettes de la carte — outil « Étiquettes », 2026-09-18.**
  Demandé par le porteur : les noms des objets gênent parfois la lisibilité.
  Nouvel outil du bandeau (`⇄ Étiquettes`, en dernier, après un séparateur) :
  on **glisse** le nom d'un objet, un **trait pointillé** le relie encore à son
  objet dès qu'il s'en éloigne de plus de 24 px, un **double-clic** le remet à
  sa place. Sa place est `proprietes.etiquette = [lat, lon]` — une position sur
  Terre, pas un décalage en pixels, donc fidèle au zoom (vérifié : l'origine du
  texte se réduit exactement comme la distance entre objets) ; **aucun changement
  de schéma**. Seules les étiquettes qui appartiennent à un objet bougent
  (points, lignes, polygones, parcelles) ; pas les altitudes des courbes, le
  cadastre ni l'aperçu d'une parcelle. Une écriture par geste, au relâchement ;
  les autres propriétés (`hauteur_m`, `angle`…) voyagent avec, le serveur
  remplaçant `proprietes` en bloc. La carte reste libre de se faire glisser :
  seules les étiquettes reprennent la souris (`pointer-events` rendu au texte
  seul, le reste du SVG restant transparent). **Validation serveur ajoutée**
  (`Calques::valider()`) : la position doit être un couple sur Terre, sinon
  refus — l'API acceptait sinon `["a","b"]`, et l'étiquette disparaissait ; le
  client ignore de toute façon une position illisible. **Bug trouvé en
  testant** : un simple clic redessinait tout le calque, si bien que le texte
  visé au 2ᵉ clic d'un double-clic n'existait plus ; le clic sans mouvement ne
  redessine plus rien. Vérifié en CDP avec de vrais gestes souris : clic sans
  effet, glisser, persistance au rechargement, trait de rappel, zoom, glisser de
  la carte en mode Étiquettes, double-clic (remise en place, sans écriture sur une
  étiquette jamais déplacée), ligne et polygone, retour aux autres outils, lecteur
  (pas de barre d'outils, POST direct 403), valeurs forgées refusées.
- **Relief et ombres depuis des sites externes — 2026-09-18.** Ce que fait la
  référence (`syntropie`) : pour le relief, **ContourBuddy** génère des courbes de
  niveau depuis la même altimétrie IGN et les **exporte en GeoJSON** ; pour les
  ombres, **ShadeMap** n'est qu'un **lien** (aucun format d'export lisible n'y a
  été trouvé). Dans syntropia, l'import GeoJSON existait déjà (Phase 10) mais
  tout en bas du panneau, sans rapport avec le calque regardé. Fait :
  (1) **l'import suit le calque actif** (« sur le calque » reprend celui qu'on a
  choisi), et les blocs « relief » et « ombres » ont chacun un lien « Importer un
  fichier ↓ » qui règle le calque ; le bloc relief renvoie vers ContourBuddy ;
  (2) **`src/ShadeMap.php`** porté, et un **bloc « ombres »** dans le panneau
  Données (visible quand le calque est choisi) : les quatre liens ShadeMap —
  21 décembre, 21 juin, 22 septembre, exposition annuelle — ouverts sur le
  terrain **au midi solaire du lieu** (vérifié : 11:58 à Greenwich le 21
  décembre, 12:01 le 21 juin, une heure de moins à 15° E), en `_blank` +
  `noopener`, et **sans lieu posé** une invitation à le faire. **Ce qui n'est
  pas possible** : importer de ShadeMap — il ne livre pas de tracés ; ce qu'on y
  lit se note à la main dans le nom du tracé (« chêne, ombre du 28 oct. au 8
  fév. »), et le bloc le dit. Un import réel a été vérifié : un GeoJSON façon
  ContourBuddy (3 courbes `elevation` + un polygone) pose 4 tracés, les courbes
  nommées « 312 m »… avec `[lon,lat]` correctement inversé ; un fichier en
  Lambert 93 est refusé avec son motif. Non vérifié : le format réel d'un export
  ContourBuddy (aucun accès au site depuis ce poste) — `Geojson::lire()` lit le
  format, pas l'outil.
- **Le bloc ShadeMap « introuvable » — 2026-09-18.** Signalé par le porteur avec
  une capture : le calque était bien choisi (le formulaire d'import l'avait
  repris), mais le panneau Données, long, était défilé tout en bas — le bloc,
  juste sous « Sur quel calque », était hors de vue. Le choix d'un calque qui a
  son bloc (vents, relief, ombres) **remonte maintenant le panneau** jusqu'à lui.
  Les scripts de la carte portent un `?v=filemtime` (un navigateur gardait
  l'ancien `calques.js`) et l'aide du calque renvoie vers les liens. **Calque
  renommé** à la demande du porteur : « Les ombres — ce qui en fait » devient
  **« Projection des ombres »** (`Calques::CALQUES`, seul endroit).
- **Fonds de carte au choix — 2026-09-18.** Demandé par le porteur après une
  question sur les fonds gratuits avec chemins ou images satellite. Sur la
  **grande carte** (`conception-etude-site.php`), un menu en bas à gauche :
  Carte OpenStreetMap (défaut), **Plan IGN**, **Photographie aérienne**,
  **Infrarouge (végétation)**, et trois **photos anciennes** (1950-1965,
  1965-1980, 1980-1995). Tout est dans `carte.js` : une table `FONDS` (adresse
  des tuiles, crédit, zoom maximal, `partiel`), le moteur ne change pas — tout
  est en Web Mercator, tuiles de 256 px. Les fonds IGN viennent de la
  **Géoplateforme** (`data.geopf.fr`, WMTS, sans clé) ; **chaque zoom maximal
  vient de son catalogue** (`GetCapabilities`) : 19 pour le plan, la photo et
  l'infrarouge (contre 18 pour OpenStreetMap — la photo va un cran plus près),
  18 pour les photos anciennes. Le choix est retenu par visiteur
  (`localStorage`, `syntropia_fond`) ; un stockage indisponible ou une valeur
  inconnue retombent sur OpenStreetMap, sans erreur. La petite carte du
  formulaire de site n'a **pas** de menu et reste sur OpenStreetMap. Changer de
  fond retire les tuiles de l'ancien, recule le zoom si le nouveau fond ne va
  pas aussi près (centre inchangé), met le crédit à jour (« © IGN ») et
  redessine les tracés. **Photos anciennes** : leur couverture est partielle —
  des tuiles absentes n'y sont pas une panne, le message le dit (« ne couvre
  pas cet endroit — essayez une autre période ») au lieu du message générique.
  Les réglages posés sur la carte (échelle, fond, cadrage) ne la font plus
  glisser ni zoomer. Vérifié en CDP, avec de vraies tuiles : les sept fonds
  chargent (18/18 tuiles) sauf 1965-1980 et 1980-1995, qui ne couvrent pas le
  point de test — et c'est le message dédié qui s'affiche ; zoom 19 atteint sur
  la photo, plafonné à 18 sur OpenStreetMap ; repli 19 → 18 au changement de
  fond ; mémorisation au rechargement ; valeur invalide et stockage bloqué ;
  lecteur (le menu est là, la carte se lit) ; tracés et étiquettes suivent.
  **Non vérifié : les conditions d'utilisation des fonds IGN** (je crois à la
  Licence ouverte avec la mention « © IGN », mais je ne l'ai pas lue) —
  à confirmer sur geoservices.ign.fr avant un usage public. La photo anciennes
  et l'infrarouge ne couvrent que la France. Pas encore déployé.
- **Racine du compte alwaysdata (`https://jubile.alwaysdata.net/`), hors
  dépôt syntropia — 2026-09-18.** Le porteur y a posé une page d'accueil
  générale du compte ; complétée avec un lien vers l'atelier et une mise
  en forme reprenant la palette du bureau. Les vidéos de formation Joala
  qui y sont hébergées (`~/www/video/`, listing déjà refusé — 403)
  protégées de l'indexation par les robots (`robots.txt` + en-tête
  `X-Robots-Tag`), sans mot de passe — l'URL reste la seule barrière,
  comme demandé. Rien de tout cela n'est dans ce dépôt git ; à retrouver
  directement sur le compte si besoin d'y retoucher.
- **`bin/lancer-local.sh` — lanceur de bureau, 2026-09-18.** Lance
  `bin/dev.py` et ouvre Firefox sur l'atelier dès que le port répond.
  Appelé par un fichier `.desktop` sur le Bureau de ce poste, hors dépôt.

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

1. **Chantier « inscription par parrainage »** — plan dans
   `chantier-inscription-parrainage.md` — **terminé**, quatre phases plus
   « Participants » ; **déployé le 2026-09-18**.
2. **Chantier « scénario de plantation »** — plan dans
   `chantier-scenario-plantation.md` — **terminé le 2026-09-18**, quatre
   phases (la base de végétaux ; la conduite ; le backend de
   `scenario_ligne` ; la page). **Pas encore déployé.**
3. **Zone climatique USDA** — faite le 2026-09-18 (voir plus haut) ;
   reste à en tirer parti, mais **la donnée manque** : la table `espece`
   ne porte volontairement aucune rusticité (« elle se lit chez
   Plantae », dit le schéma). Filtrer les suggestions du scénario par
   zone suppose de joindre `plantae-corpus` (une base externe, jointure
   applicative) ou de curater la rusticité des 46 espèces du cadre — à
   décider avec le porteur, pas avant.
4. **Exporter et importer un projet** — noté le 2026-09-18, plan dans
   `chantier-export-import-projet.md` : un fichier JSON avec sa **date
   d'export**, sans comptes ni accès, importé en un projet neuf. Le besoin est
   déjà réel : le projet de production a été rapatrié en local par un script
   ponctuel non conservé. **Décidé le 2026-09-18** : le porteur seul exporte ;
   la date d'export s'affiche dans le fichier et à l'aperçu d'import ;
   conduite et espèces reprises telles quelles ; plafond d'import 1 Mo (un
   vrai projet pèse ≈ 14 Ko). **Reste à confirmer** : la trace d'origine
   (colonne `site.origine` proposée).
5. **`contenu/documentation-saf.md`** — Sylvain écrit les vrais
   chapitres à son rythme, en markdown, directement dans ce fichier.
6. **`autres-documentations.php`** — page publique restante, non
   commencée.
7. **`compte.php` / Préférences** — faite le 2026-09-18 (voir plus haut) ;
   pas encore déployée.
8. **Au-delà du premier tour** : rien ne relit encore les réponses
   enregistrées pour les présenter (récapitulatif, export, tableau de
   bord) — à voir si/quand le besoin s'en fait sentir.
9. **Campagne de qualification avec cgpt** — `dossier-qualification.md`
   transmis, en cours. Confronter ses scénarios au code réel une fois
   produits.
10. Aucune procédure n'automatise `bin/deployer.py` après chaque
   changement — à relancer à la main.
