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
- `chantiers.md` — l'état détaillé de chaque chantier ; `chantier-*.md` — le
  plan de chacun. En bref :
  - **Terminés** : `carte-interactive` (2026-09-16), `plan-de-parcelle`,
    `scenario-plantation`, `inscription-parrainage` (2026-09-18),
    `plan-de-culture` (2026-09-21), `caracteristiques-vegetaux`,
    `peuplement-parcelle` (2026-09-23), `repartition-bordures` (2026-10-01 —
    face sud de l'externe en jardin-forêt rouverte, non tranchée),
    `textes-atelier` (2026-10-03).
  - **En cours** : `dossier-projet` (Phases 1 et 2 faites et déployées, Phase 3
    suivante — ordre du porteur) ; `saut-du-chat` (Phases 1, 2, 2 bis, 2 ter,
    3, 5 faites ; **Phase 4 bloquée**, attend le porteur et la formation — une
    règle sans réponse sourcée ne se code pas) ; `film-de-la-parcelle`
    (Phases 1, 2, 2 bis faites) ; `associations` (Phase 1 faite) ;
    `export-import-projet` (Phases 1-3 faites, Phase 4 GeoJSON facultative) ;
    `mises-a-jour` (Phases 1-4 faites, 5-7 avant la version 1) ;
    `documentation-atelier` (maintenance continue, voir § Directives).
  - **Planifiés, pas commencés** : `suppression-projet` (sans date — personne
    ne peut supprimer un projet aujourd'hui), `manuel-utilisateur` (sur
    demande du porteur, à priori à la version 1.0), `presentation` (2026-10-03, questions
    tranchées, prête à commencer).
- `skills-map/` — carte des compétences métier nécessaires aux étapes de
  l'atelier (skill `organisation-skills`), dont les noeuds citent
  `connaissances/`.
- `connaissances/` — la base sourcée par `skills-map/` : importée telle
  quelle du projet `syntropie` le 2026-09-17, celui-ci étant voué à
  disparaître. Avant cette date, c'était un corpus externe en lecture
  seule (`/home/sylvain/projets/syntropie/connaissances/`), jamais
  recopié ; il vit maintenant ici, modifiable comme le reste du dépôt.

## Arborescence

Le détail, fichier par fichier, est dans `arborescence.md` — à lire avant de
créer un fichier ou de chercher où vit une logique, et à tenir à jour.

```
public/        seule racine servable (.htaccess) — les pages, api-*.php, assets/
src/           backend PHP (namespace Syntropia\), classes « pures » + src/Web/ (accès base)
sql/           00-comptes.sql, 01-syntropia.sql (schéma + migrations), 02-especes-seed.sql
contenu/       livrets, glossaire, CSV et instantanés (cadre des espèces, Lopez, Weinrich, guildes)
config/        base.php (production, identifiants), local.env (local, hors dépôt)
bin/           dev.py, deployer.py, scripts appelés par eux — jamais en direct
composants/    un dossier par zone du gabarit
le-bureau/     la maquette validée
skills-map/    nœuds de compétence métier ; connaissances/ : le corpus qu'ils citent
```

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
- Vérifier l'aller-retour d'un projet : `python3 bin/dev.py verifier-export [id…]` —
  exporte, importe sous un compte de passage, ré-exporte et compare ; **à rejouer à
  chaque changement de schéma**. **Jamais `php bin/verifier-export-import.php` en direct**
  (même piège que `compte.php` ; le script refuse hors de ce poste). Voir
  `chantier-export-import-projet.md` § Phase 3.
- Déployer vers alwaysdata : **`python3 bin/deployer.py mettre-a-jour`** — la voie normale depuis le 2026-10-01
  (des bêta-testeurs en production) : maintenance (« Mise à jour en cours », 503), sauvegarde des bases dans
  `../syntropia-sauvegardes/`, `base`, `fichiers`, réouverture ; une étape en échec laisse l'atelier fermé. Voir
  `chantier-mises-a-jour.md`. Actions séparées : `sauvegarde`, `maintenance on|off|etat`, `etat`
  (connectivité), `fichiers` (`--dry-run` pour prévisualiser, sinon
  envoie), `config` (pousse `config/base.php` seul), `base` (sauvegarde, puis rejoue le
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
- **Le livret « L'atelier » se tient à jour avec le code — demandé le 2026-09-19.**
  `contenu/documentation-atelier.md` est **à ma charge** (le porteur n'a pas le temps de
  le faire correspondre au déployé). Toute évolution visible d'un utilisateur — une page,
  un outil, un rôle, un libellé — met à jour le chapitre concerné **dans la même vague**
  que le code, et **le livret ne décrit que ce qui est en production**, jamais le prévu.
  Format du moteur : un chapitre par ligne `# `, ni liste ni `##`. Les chapitres écrits
  par le porteur (« L'intention ») ne se réécrivent pas. Un chantier n'est « terminé »
  qu'une fois le livret relu. Plan et garde-fou : `chantier-documentation-atelier.md`.
  Le livret « SAF » (les principes), lui, est au porteur.
- Définir par ce que sont les choses et taire ce qu'elles ne sont pas
- **Construis toute page sur le gabarit « le bureau ». Aucune exception,
  aucune page publique à part.** Voir § Architecture.
  **Deux exceptions posées, toutes deux des documents à imprimer :**
  `releve-vierge.php` (le 2026-09-14) et `dossier-projet.php` (admise par
  le porteur le 2026-09-19, faite le 2026-10-02) — voir leur en-tête. Ne
  pas s'en servir de précédent pour autre chose qu'un document destiné au
  papier.

## Point de reprise

*L'historique détaillé (ce qui a été construit, vérifié, déployé, vague par
vague) est dans `journal-de-reprise.md` ; ici, ce qui sert à chaque séance.*

### Décidé, à ne pas rouvrir

- 4 phases (définition, conception, réalisation, suivi) — `plan-du-site.md`,
  tranché par `directives.txt`.
- Le backend adapte `application/` (dépôt de référence), sans rien partager
  avec `syntropie` : bases et vocabulaire propres — `architecture-technique.md`
  § Le backend.
- **Le site EST le bureau**, aucun défilement — voir § Architecture. Deux
  exceptions seulement, des documents à imprimer : `releve-vierge.php` et
  `dossier-projet.php`.
- Nom affiché : **Syntropia**. Menus : du plus utilisé au moins utilisé, sauf
  les listes de parcours.
- **Un compte peut avoir plusieurs projets** ; un **projet actif** (cookie
  `syntropia_projet`, revérifié contre `membre_site`) — `Sites::pourCompte()`
  renvoie toujours UN site. Premier site : `definition-projet.php` ; un de
  plus : `definition-projet.php?nouveau=1`.
- Les vingt étapes de `etapes.js` portent `pret: true`.
- Export : **somme de contrôle seule** (pas de HMAC) ; un import crée
  toujours un projet neuf, dates d'origine gardées.
- Stade du terrain : **trois stades, par parcelle** (colonisation,
  accumulation, abondance — schéma 19).
- `[Joala]` et `[Théry]` ne sont **jamais deux sources indépendantes**
  (Joala est la structure d'Anaëlle Théry) ; un écart = une pratique qui a
  évolué.

### Pièges vérifiés — à ne pas répéter

- **Avant tout `php -r`, script ponctuel ou code qui touche `Database::*` :
  exporter `config/local.env` dans le shell.** Sinon `config/base.php`
  l'emporte et l'écriture part **en production** — arrivé trois fois (dont un
  compte de test créé en production le 2026-09-22). D'où : jamais
  `php bin/compte.php` ni `php bin/verifier-export-import.php` en direct,
  toujours par `bin/dev.py`.
- **Tout changement de `sql/01-syntropia.sql` monte `Version::SCHEMA`** dans
  le même commit (`src/Version.php` et la ligne `atelier_version` du SQL),
  puis `python3 bin/dev.py verifier-export`.
- Migrations : `ADD COLUMN IF NOT EXISTS` n'existe que sur MariaDB (le poste
  local est en MySQL 8) — passer par `information_schema` + `PREPARE`.
  `CREATE TABLE IF NOT EXISTS` ne rattrape jamais une colonne ajoutée depuis.
  `bin/deployer.py` a **sa propre liste de schémas**, distincte de `dev.py`.
- `INSERT IGNORE` tronque sans rien dire (`espece.source` ≤ 120,
  `statut_local` ≤ 60 caractères). Une surcharge d'espèce **remplace toute la
  fiche du cadre**, espacement compris.
- Fichiers partagés (`gabarit.css/js`, `etapes.js`, `carte.js`, `livret.js`…)
  chargés avec `?v=filemtime`, sinon un navigateur garde l'ancien.
- Tuer Chrome par le PID lu avec `pgrep -o`, jamais un numéro recopié.
- Ne pas « réparer » la règle de `~/www/.htaccess` vers
  `www.jubile.alwaysdata.net` (hors dépôt) : c'est elle qui garde le compte
  joignable.

### Règles de travail

- **Déployer : `python3 bin/deployer.py mettre-a-jour`** (maintenance,
  sauvegarde, `base` puis `fichiers`, réouverture). **Des bêta-testeurs
  travaillent en production depuis le 2026-10-01 : aucune mise à jour ne doit
  effacer leurs données.** Avant un déploiement, lire l'état de la production
  sans rien changer ; après, vérifier (`rsync -c`, syntaxe PHP distante, 302
  / 403 attendus).
- Les parcours ne se testent pas en production : ils restent à essayer par le
  porteur avec son vrai compte — le lui dire à chaque déploiement.
- **Livrets** : un chapitre par ligne `# `, ni liste ni `##` ; paragraphes de
  300 caractères au plus (220 pour tenir à 320×568), titres de chapitre
  courts.
- **Compléter le cadre des espèces** : modifier `contenu/especes-cadre.csv`,
  régénérer le dernier bloc de `sql/02-especes-seed.sql`, rejouer, comparer
  case à case — méthode détaillée dans `journal-de-reprise.md` (2026-09-30).
- **Nouveau versement de la formation Joala** : comparer les sha256 de
  `~/Bureau/Projets/input/` à `connaissances/formation-joala/inventaire.json`,
  ne lire que le nouveau (jusqu'au mot « transcription »), le rattacher au
  corpus et voir quelles questions ouvertes il ferme.
- **Livret « Les sources »** (`contenu/sources-de-l-atelier.md`) : à mettre à
  jour quand une source nouvelle entre dans une règle — demander au porteur.

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

### Plantae — ce qui nous engage

Plantae est sur le même compte alwaysdata (`/plantae/`). **Ne jamais toucher
d'ici** `~/www/plantae/` ni la base `jubile_plantae`. Syntropia lit Plantae
par instantanés (`bin/catalogue-plantae.py`). Plantae **lira
`jubile_syntropia_comptes`** en lecture seule (`compte`, `acces`) : prévenir
Plantae avant de renommer ou retirer une colonne de `compte`/`acces` ou une
valeur de `acces.role` ; le moment venu, donner au porteur une ligne `acces`
`('plantae', 'administrateur')` ; réponse sur `acces.application` **pas
encore transmise à Plantae**. Détail : `journal-de-reprise.md`, point 13.

### État et prochaine étape — au 2026-10-03 au soir

Tout est commité et poussé (`origin/main`) ; la production est à jour
(commit `ef8da1e`, **schéma 19**, inchangé), déployée le 2026-10-03 avec
sauvegarde. Ce déploiement porte le Dossier Projet Phase 2, la ligne centrale
de la demi-planche de fin, le retrait des phrases de développement et la
réécriture de tous les textes de l'atelier (`chantier-textes-atelier.md`,
terminé). Parcours à essayer par le porteur : imprimer le Dossier Projet
(terrain, parcelles), relire le livret « L'atelier » sur téléphone.

**La suite, dans l'ordre du porteur** : le Dossier Projet, Phases 3 à 5 (la 5 :
le feuillet de chantier de chaque parcelle), puis la présentation
(`chantier-presentation.md` ; sa Phase 1 corrigera la description des doses
d'arrosage, retirées).

**En attente du porteur ou de la formation** :
- Saut du chat, Phase 4 (itinéraire technique) : la liste des itinéraires et
  leurs critères, le tableau « itinéraire × période ». Ne pas coder sans.
- Décisions en suspens (`chantier-plan-de-culture.md` § À trancher,
  `chantier-saut-du-chat.md`) : repères d'été Joala, alias « maïs », règle de
  famille, définition du saut du chat au glossaire, correspondance du fichier
  de semences du porteur avec l'import d'`especes.php`.
- Zone USDA : filtrer par rusticité suppose une donnée absente (à décider).
- Proposé sans réponse : agrandir `espece.statut_local` ; faire du cadre
  complet le modèle téléchargeable d'`especes.php` ; supprimer
  `../syntropia-depot` (périmé).

**Ouvert, à reprendre un jour** :
- Repérer les végétaux des planches comme ceux des bordures (sigles,
  légende, survol) — demandé par le porteur.
- Le porteur doit cliquer « Enregistrer le plan » de La Vadaux P1 en
  production (9 → 10 planches).
- Livrets : le livret SAF déborde de ~50 px à 320×568 ; la
  tirette « Informations » recouvre « Chapitre n sur N » sur téléphone. Le
  panneau gauche défile déjà à 800 px de haut.
- `chantier-mises-a-jour.md` Phases 5-7 — dont **une vraie restauration de
  sauvegarde, jamais essayée**.
- La hauteur des espèces servira à l'ombre portée et à la vue de côté — à
  cadrer avec le porteur avant de coder.
- `autres-documentations.php`, page publique, non commencée.
