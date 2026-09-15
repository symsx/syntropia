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
  compte.php              crée/modifie un compte en CLI — appelé par `dev.py compte`
public/                racine servable du site en refonte
  .htaccess              seul dossier accessible ; type MIME et cache des polices
  index.php               page publique — présente l'atelier, oriente. Accueil = « / », sans nom de fichier
  connexion.php          page de connexion — provisoire, sans mise en forme
  deconnexion.php
  definition-projet.php      passage obligé unique — ouvre le site, hors manifeste etapes.js
  definition-vision.php      étape 1 (Définition), gardée par Authentification::exige()
  definition-objectifs.php   étape 2 (Définition), gardée par Authentification::exige()
  definition-moyens.php      étape 3 (Définition), gardée par Authentification::exige()
  definition-site.php        étape 4 (Définition), gardée par Authentification::exige()
  conception-etude-sol.php   étape 2 (Conception), gardée par Authentification::exige()
  conception-parcelles.php   étape 7 (Conception), gardée par Authentification::exige()
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
  documentation-site.php     livret « L'atelier » — lit contenu/documentation-atelier.md
  documentation-saf.php      livret « L'agroforesterie syntropique » — lit contenu/documentation-saf.md
  glossaire.php              les mots techniques, un par un — recherche filtrée, lit contenu/glossaire.md
  assets/
    css/gabarit.css        la coquille du bureau — TOUTES les pages, publiques comprises ; `.terme` (mot expliqué au survol) y vit aussi
    js/carte.js              moteur de tuiles sans dépendance (OpenStreetMap) — partagé par toute carte
    js/etapes.js            le manifeste des phases — `pret: true` = la page existe déjà
    js/gabarit.js           Gabarit.init() ; grise précédente/suivante vers une étape non `pret` ; initTermes() arme la bulle de tout `.terme`
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
  l'ouvrir.
- Ouvrir la maquette de référence : `le-bureau/index.html` directement
  dans un navigateur — page HTML autonome, sans PHP, sans build.
- Vérifier une largeur d'écran sous 500px : `--window-size` de Chrome
  headless a un plancher à 500px et ne simule pas un vrai petit écran ;
  passer par le protocole DevTools (`Emulation.setDeviceMetricsOverride`)
  pour un test fiable, ou réduire la fenêtre d'un vrai navigateur.

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
interne : refusé, même pour l'accueil.

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
`le-bureau/fonts/`.

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
  réduit. **Une seule exception** : `releve-vierge.php`, un document à
  imprimer, pas une page à visiter — voir § Architecture et § Directives
  de travail. Ne pas s'en servir de précédent pour autre chose.
- Nom affiché : **Syntropia** (jamais « Atelier Syntropie »).
- Menus et barre d'outils : du plus utilisé au moins utilisé, sauf les
  listes de parcours (phases, étapes d'une parcelle) qui gardent
  l'ordre chronologique. Voir § Architecture.
- **Un seul projet par compte** — pas de liste de sites. Un site s'ouvre
  une fois, sur `definition-projet.php` (passage obligé, hors
  `etapes.js`), avant la première question de Définition.
- **Trois étapes restent volontairement reportées**, à part entière —
  pas construites en version allégée, `pret` absent dans `etapes.js` :
  `conception-etude-site.php` (la carte interactive du terrain —
  calques, dessin, vents, relief, cadastre ; `carte.php` dans le dépôt
  de référence), `conception-scenario.php` (scénario de plantation —
  espèces, strates, associations culturales ; `scenario.php`, 336
  lignes dans le dépôt de référence) et `conception-materiel-et-eau.php`
  (`directives.txt` : « pour chaque parcelle », mêmes données absentes
  que le scénario). Les trois demandent un vrai scénario en base, qui
  n'existe pas encore, et/ou la carte elle-même. **Les vraies parcelles,
  elles, existent depuis le 2026-09-15** — `conception-parcelles.php`
  n'est plus une liste codée en dur, voir plus bas (chantier « carte
  interactive du terrain », Phase 3). Chacune des trois étapes reste
  accessible en la sautant : le mécanisme « phase → première étape
  prête » (`gabarit.js`) passe par-dessus sans lien mort.

**Construit et vérifié** :

- Le gabarit (`public/assets/css/gabarit.css`,
  `public/assets/js/{etapes,gabarit}.js`) — `Gabarit.init({ etape,
  site, contexte })`. Le panneau gauche construit sa navigation tout
  seul depuis `etapes.js` (`remplirPhaseProjet()`,
  `remplirEtapesPhase()`) — y compris entre phases : chaque entrée de
  « Phase projet » mène à la première étape prête de cette phase,
  jamais à un lien mort. Une section « Documentation » (`remplirSectionDocumentation()`)
  s'ajoute d'elle-même en dernière position du panneau gauche, sur
  TOUTE page — les deux livrets, toujours au même endroit, jamais à
  dupliquer à la main. Le compte (avatar + nom, en haut à droite)
  ouvre un vrai menu (`initCompteMenu()`) : Mon projet, Préférences
  (`compte.php`, à venir — grisée), Se déconnecter.
- Le backend PHP (`src/` : `autoload`, `Configuration`, `Database`,
  `Role`, `Profil`, `FicheTerrain`, `Web/Comptes`,
  `Web/Authentification`, `Web/Invitations`, `Web/Sites`,
  `Web/Reponses`) et les deux bases MySQL (`syntropia`,
  `syntropia_comptes`), schéma dans `sql/`.
- **La persistance réelle de toute la phase Définition** — `site`,
  `membre_site`, `participant`, `reponse` (schéma déjà présent,
  jusque-là inutilisé). `definition-projet.php` ouvre le site ; les
  quatre étapes de Définition enregistrent chaque champ dans `reponse`
  et le rechargent en le revisitant — un champ vidé efface sa ligne
  plutôt que d'y garder `''`. Vérifié de bout en bout, y compris le
  retour en arrière entre étapes. Ce que ça ne couvre pas encore :
  `conception-parcelles.php` (liste « Parcelle A » toujours codée en
  dur), les colonnes propres de la table `site` (`Sites::
  enregistrerParametres()` non porté — les champs de
  `definition-site.php` vivent dans `reponse`, comme les autres étapes),
  le rattachement d'une invitation à un site (`Sites::ajouterMembre()`
  non porté).
- `bin/dev.py` — vérifie PHP et les bases, rejoue le schéma, sert ;
  arrête proprement son `php` enfant sur n'importe quel signal.
  `bin/dev.py compte` (→ `bin/compte.php`, adapté du dépôt de
  référence) crée ou modifie un compte en CLI — la voie du premier
  compte, mot de passe jamais affiché ni passé en ligne de commande.
- Six pages publiques : `index.php`, `connexion.php`, `deconnexion.php`,
  `inscription.php` (sur invitation, voir `Web/Invitations.php`),
  `documentation-site.php` et `documentation-saf.php` — deux livrets
  qu'on tourne sur le bureau, un chapitre affiché à la fois (CSS pur,
  `:has()` + `label[for]`, sans JavaScript). Le texte vit dans
  `contenu/*.md` (un chapitre par ligne `# `, voir `src/Documentation.php`),
  pas dans le PHP — ajouter un chapitre au `.md` suffit, aucune page ne
  se rouvre. `documentation-saf.md` n'est encore qu'un modèle à
  remplacer, le porteur écrira son contenu.
- **Le parcours au complet, sauf les trois étapes reportées** — dix-huit
  des vingt étapes de `etapes.js` portent `pret: true`. Phase Définition
  (4/4) : passage `definition-projet.php`, puis `vision`, `objectifs`,
  `moyens`, `site`. Conception (3/6, plus `conception-calendrier` en
  6/6) : `etude-sol` (avec `releve-vierge.php`, hors gabarit),
  `parcelles`, `calendrier` — `etude-site`(1), `scenario`(4) et
  `materiel-et-eau`(5) reportés, voir ci-dessus. Réalisation (4/4) :
  `preparation-parcelle`, `materiel-vegetal`, `outils-et-plans`,
  `consignation`. Suivi (4/4) : `arrosage-germination`, `perturbation`,
  `saut-du-chat` (suggestion `:has()` si pas encore visible, même
  principe que la RNA de `definition-moyens.php`), `consignation` —
  seule étape qui redirige ailleurs que vers une étape suivante
  (`conception-parcelles.php`, faute de suite : le suivi se répète,
  contrairement aux étapes amont). Les neuf pages écrites le
  2026-09-15 n'ont aucun contenu « déjà présent » à porter — rien de
  comparable dans le dépôt de référence — donc rédigées depuis
  `directives.txt` directement, sur le schéma déjà établi par
  Définition. Détail : `architecture-technique.md` § Le parcours
  complet, de Calendrier à Suivi.
- `skills-map/` (skill `organisation-skills`) — 14 noeuds de
  compétence métier, groupés par phase, sourcés sur
  `/home/sylvain/projets/syntropie/connaissances/`.
- Arborescence protégée par `.htaccess`, sur le modèle exact du dépôt de
  référence — refus dans les dossiers, jamais à la racine. **Non
  vérifiable en local** : le serveur intégré de PHP ignore les
  `.htaccess`, seul un vrai Apache (ou le dépôt en ligne) l'exercera.

**Mécanique à respecter pour toute nouvelle étape** : ajouter son
`pret: true` dans `etapes.js` en même temps que sa page — sinon les
étapes voisines restent grisées vers elle (jamais de lien mort, jamais
de lien non plus tant qu'il est absent). Quand `directives.txt` donne
l'usage d'un champ, l'écrire à côté du champ (`definition-objectifs.php`
en donne l'exemple) — voir § L'esprit à garder dans l'interface. Sans
usage connu, ne rien inventer. Pour une étape qui saisit des réponses :
suivre le schéma de `definition-vision.php` (garde-site, `POST` →
`Reponses::enregistrerLot()` → redirection, `GET` → `Reponses::
pourParticipant()` préremplit) plutôt que d'inventer un autre mécanisme.

### Prochaine étape

Le parcours guidé (Définition → Conception → Réalisation → Suivi) est
maintenant construit et persisté d'un bout à l'autre, à trois étapes
près. Ce qui reste :

1. **Le chantier « parcelles et scénario réels »** — regroupe les trois
   étapes reportées : `conception-etude-site` (la carte), `conception-
   scenario` (espèces, strates, plan de plantation) et `conception-
   materiel-et-eau` (récapitulatif par parcelle). Un vrai
   `conception-parcelles.php` (parcelles en base, plus une liste codée
   en dur) est leur condition commune — s'y attaquer avant, ou avec.
2. **`contenu/documentation-saf.md`** n'est encore qu'un modèle —
   Sylvain écrira les vrais chapitres, en markdown, directement dans ce
   fichier (rien à construire côté site pour ça).
3. **`autres-documentations.php`** — page publique restante, non
   commencée.
4. **Au-delà du premier tour** : rien ne relit encore les réponses
   enregistrées pour les présenter (récapitulatif, export, tableau de
   bord) — chaque étape écrit dans `reponse`, mais rien ne les
   rassemble encore. À voir si/quand le besoin s'en fait sentir.
5. **Syntropia est en ligne — `http://jubile.alwaysdata.net/syntropia/`,
   depuis le 2026-09-15**, bases comprises. **Sous `/syntropia/`, pas la
   racine** : le compte alwaysdata héberge aussi Plantae, à
   `/plantae/` — `RewriteBase`/`RewriteCond` de `.htaccess`, `'base'`
   de `config/base.php`(`.exemple`), et `WWW_DISTANT` de
   `bin/deployer.py` portent tous les trois ce chemin, à tenir assortis.
   `bin/deployer.py` (même moule que `dev.py`) : `etat`, `fichiers`
   (rsync vers `jubile:~/www/syntropia/`), `config` (envoie
   `config/base.php`, jamais mêlé à `fichiers`), `base` (rejoue le
   schéma, `mysql` exécuté sur le serveur via SSH), `preparer` (assemble
   la même arborescence dans `../syntropia-depot`, pour qui dépose à la
   main — FileZilla — plutôt qu'en ligne de commande ; avertit sur les
   fichiers binaires, qu'un dépôt FTP en mode ASCII abîme sans le dire).
   **`.htaccess`, « non vérifiable en local » depuis le 2026-09-14,
   vérifié pour de vrai** : racine du sous-répertoire réécrite vers
   `public/`, les dossiers protégés refusent tous (403 : `src/`,
   `config/`, `sql/`, `bin/`, `contenu/`), polices avec le bon type MIME
   et un cache d'un an, cookie de session cantonné à `/syntropia/`.
   **Bases créées par le porteur dans le panneau alwaysdata** (pas de
   CLI trouvée ; `jubile` n'a pas le droit `CREATE DATABASE` en SQL
   brut, le formulaire du panneau l'a fait) — `jubile_syntropia_comptes`,
   `jubile_syntropia`, utilisateur `jubile_syntropia`. Schéma rejoué
   (4 + 17 tables, conforme), et un compte jetable créé en SSH
   (`php bin/compte.php`, pas `dev.py compte` — dépend de
   `config/local.env`, absent en production) s'est réellement connecté
   sur le site réel, avant d'être supprimé — refait une seconde fois
   après le passage sous `/syntropia/`, résultat identique. Détail :
   `architecture-technique.md` § Syntropia en ligne pour la première
   fois, et § Syntropia et Plantae, chacun dans son sous-répertoire.
   Reste ouvert : aucune procédure ne rejoue `deployer.py` après chaque
   changement — à refaire à la main pour l'instant.
6. **Zone climatique USDA, déterminée plutôt que saisie** — chantier à
   prévoir, pas commencé, noté par le porteur le 2026-09-15. Aucun
   service USDA officiel n'accepte des coordonnées (seulement des codes
   postaux américains) ; une zone USDA se définissant uniquement par la
   température minimale annuelle moyenne, elle est calculable n'importe
   où depuis un historique météo. Piste retenue : Open-Meteo (réanalyse
   ERA5, sans clé), déjà prévu pour la rose des vents de la carte
   interactive (`conception-etude-site.php`, Phase 8 — voir plan de ce
   chantier) — même source, même absence de clé, même couverture
   France. Remplacerait le champ texte libre actuel de
   `definition-site.php`.
7. **Corrections et ajouts issus d'un test manuel du porteur, 2026-09-15** :
   - `connexion.php` redirigeait en dur vers `conception-parcelles.php`
     après connexion, écrit avant `definition-projet.php` — un compte
     tout juste inscrit tombait donc sur le mockup encore fictif. Corrige
     via `Sites::pourCompte()` : sans site → `definition-projet.php`,
     avec un site → comme avant.
   - `definition-site.php` sépare maintenant « Le lieu » (administratif :
     commune, position, superficie, USDA, exposition, pente) de « Ce que
     vous observez » (climat) — deux groupes visuellement distincts,
     même page. Le bloc « déjà en présence sur le terrain » (pieds-mères,
     graines, végétaux utiles) en est parti : fusionné dans
     `realisation-materiel-vegetal.php`, qui posait déjà la même question
     en mieux (vocabulaire expliqué, moment plus pertinent).
   - `conception-etude-sol.php` affiche désormais **à quoi sert** chaque
     relevé (ex. « Voir si le sol se décompacte au fil des ans »), pas sa
     méthode (« Sol ressuyé, trois prélèvements ») — la méthode reste sur
     la fiche à imprimer, où elle a sa place.
   - **Nouveau : les mots techniques expliqués au survol.** Tout `.terme`
     (span, `data-explique="..."`) se souligne en pointillés et montre sa
     bulle au survol ou au focus clavier — `Gabarit.init()` l'arme partout
     (`initTermes()`, `assets/js/gabarit.js`), jamais le `title` natif du
     navigateur. Le texte vient de `contenu/glossaire.md`
     (`Documentation::glossaire()`/`Documentation::terme()`), même source
     qu'une nouvelle page publique, `glossaire.php` — recherche filtrée
     en direct, jamais plus qu'une poignée de résultats montrés à la fois
     (mesuré en JS contre la hauteur réelle, pas un compte fixe : une
     définition longue ne se coupe jamais en plein mot). Posé sur les
     termes déjà identifiés cette session (pieds-mères, bouturage, semis,
     division, USDA, RNA, saut du chat, déchaumage, bâchage, décompacter,
     densimètre, labour) sur les pages où ils apparaissent réellement —
     pas partout où le mot existe dans l'absolu, pour ne pas noyer le
     motif sous trop de soulignements.
8. **Phase 3 du chantier « carte interactive du terrain » faite —
   `conception-parcelles.php` n'est plus un mockup, 2026-09-15.**
   Nouveau `src/Web/Parcelles.php` (`dUnSite()`, `parId()`, `creer()`,
   `enregistrer()` — whitelist étroite : code/nom/type à la création,
   puis `nb_planches`/`largeur_planche_m`/`passe_pied_m`, les trois
   mesures dont la carte aura besoin en Phase 4/5, pas le reste de la
   fiche d'attributs). La page lit désormais le vrai site
   (`Sites::pourCompte()`, gardée comme toutes les autres — elle ne
   l'était pas) et la vraie liste de parcelles ; sans parcelle, ou sur
   `?nouvelle=1`, un formulaire (code, nom, type) remplace le plan SVG.
   Le panneau gauche liste les parcelles réelles, chacune menant à
   `?parcelle=ID`. **Ce qui reste un prototype, volontairement** : le
   plan lui-même (bordures, planches, dessiné dans le SVG) — piloté par
   `conception-parcelles.js`, entièrement côté client, `parcelle.plan`
   toujours pas branché ; les trois mesures réelles vivent à côté, dans
   un bloc distinct du panneau droit (« Pour la carte du terrain »), et
   ne se mélangent pas avec le prototype. `connexion.php` corrigé au
   passage (voir point précédent) pour ne plus jamais montrer ce mockup
   à un compte tout juste inscrit. Prochaine étape du chantier : Phase 4
   (`src/Web/Calques.php` + `calque_element` + `api-calques.php`), voir
   le plan validé plus haut dans cette session.
