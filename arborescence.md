# Arborescence du dépôt

*Déplacé tel quel de `CLAUDE.md` le 2026-10-03. Un fichier par ligne, avec son rôle. À tenir à jour quand un fichier apparaît ou change de rôle.*

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
  Perturbation.php        le vocabulaire du journal des perturbations (gestes, état des plantes, eau, biomasse) et le temps écoulé depuis la dernière coupe ; pur
  SautDuChat.php          le vocabulaire d'un constat, la comparaison au témoin, et les conditions qui rendent le saut du chat possible (lues dans le journal et le scénario) ; pur
  CalendrierLopez.php     lit les repères de semis et de repiquage de Lopez (`contenu/calendrier-lopez.csv` : dix-sept cultures d'automne et d'hiver, en dates de mots) et en tire des dates INDICATIVES (le milieu de la fenêtre) ; pur
  Succession.php          lit la table de succession de Weinrich (`contenu/associations/associations-succession.csv`) : ce qui précède une plante, ce qui la suit, l'enchaînement de deux cultures d'une planche (conseillé / exclu / sans repère) lu dans leurs dates ; pur
  PlanDeCulture.php       propose le placement des cultures d'un potager sur les planches du plan (regrouper ce qui s'entend bien, écarter ce qui s'entend mal, ne jamais bouger une culture déjà placée, répartir) ; pur
  Guildes.php             lit l'instantané des guildes de Plantae (`contenu/guildes-plantae.json`) : besoins et services par catégorie, et le bilan d'un ensemble de plantes (besoins couverts par un autre membre, restés sans réponse, plantes sans guilde connue) ; pur
  Associations.php        lit les tables d'association de `contenu/associations/` (potager, verger, succession) — ce qui s'entend bien, ce qu'il vaut mieux éviter de placer côte à côte ; une relation est connue si l'une des deux plantes la cite (tables non symétriques) ; signale les plantes toxiques ; pur
  Planches.php            les planches d'une parcelle et leurs lignes de plantation — disposition() est LA règle (demi-planches aux bords, ou passe-pied le long des deux bords ; planche seule centrée), planchesLayout() en JS la reproduit ; 3 lignes par défaut, la centrale ; pur
  LignesDeService.php     propose les plantes de service des lignes d'une planche — biomasse au centre, strates vides en pyramide, une espèce par ligne ; pur
  Bordures.php            les lignes de plants des bordures — géométrie (une ligne au milieu de chaque bande), compte par espèce (moyenne des espacements voisins, demi-espacement aux bouts), l'espèce convient-elle à la bordure ; pur
  Temps.php               le temps d'une plante — la semaine (lundi ISO), la fenêtre d'une culture (mise en place → récolte, levée), les périodes du scénario rapportées à la date de plantation, l'échelle (semaine, mois, année) ; pur
  RepartitionBordures.php répartit une liste de plantes sur les bordures externe et interne d'une parcelle — fonction, émergentes au sud et à l'ouest de l'externe, brise-vent sur les côtés cochés, colonne vertébrale un plant sur deux, deux espèces au moins par côté sinon refus ; pur
  Sigles.php              les sigles des plantes d'un plan (« La », « FeA ») — calculés, uniques sur le plan, deux sigles qui ne diffèrent que d'un accent comptent pour un ; pur
  Palette.php             la palette végétale d'une parcelle — ses trois places (productions principales, secondaires, service) et les neuf fonctions de service, chacune avec ce qu'elle apporte et les rôles du référentiel qui la désignent ; pur
  Tortue.php              le type de tortue d'une parcelle — classique, zèbre, abeille, liane (Théry, ch. 15) : libellé et une phrase de la source ; pur
  Stade.php               le stade du terrain — colonisation, accumulation ou abondance (trois stades, par parcelle, schéma 19) : ce qui s'observe, ce que le corpus permet d'affirmer, et ce qu'on fait à chaque stade (densité, taille — Joala 6-8, sans chiffre) ; pur
  Espacement.php          l'espacement selon la fonction — le service d'une espèce lu dans son rôle (biomasse, puis bloque-herbe, puis brise-vent), la part de biomasse visée (75 %) ; pur
  Materiel.php            le matériel végétal à se procurer — regroupe le scénario par origine, estime les quantités depuis l'espacement et le plan, la ligne centrale pérenne en série (quantitesScenario) ; pour un potager, les plants de chaque culture et les graines à démarrer (80 %, convention de Lopez) ; pur
  BesoinEau.php           besoins en eau (Lopez) et fréquence d'arrosage d'après infiltration et ressuyage ; pur
  ShadeMap.php            liens ShadeMap sur le terrain (solstices, équinoxe, exposition annuelle, au midi solaire du lieu) — un lien, jamais un import
  Geojson.php             lit un GeoJSON externe (ContourBuddy, QGIS, Géoportail…), un tracé par morceau — inversion [lon,lat]→[lat,lon]
  DessinsDossier.php      les dessins du Dossier Projet en SVG pour le papier — tracés du terrain (échelle, nord, sans fond), plan d'une parcelle, frise du scénario ; pur
  Documentation.php      lit contenu/*.md, un chapitre par ligne « # » — livrets et glossaire
  Web/
    Comptes.php            dépôt des comptes (base syntropia_comptes)
    Authentification.php   session, connexion, CSRF, garde-page
    Invitations.php         codes d'invitation — condition de l'inscription
    Sites.php                les sites d'un compte, et son projet actif — voir chantier-inscription-parrainage.md
    Cultures.php             les cultures d'un potager placées par planche (`succession_ligne`), dont leurs fonctions de service (`role`, multivalué comme espece.role) — même conduite qu'Interventions ; appelé par conception-potager.php
    LignesPlantation.php     les lignes de plants d'une parcelle (`ligne_plantation`) — la série d'un côté de bordure réécrite d'un bloc sous verrou de version, et l'état compté pour le plan ; appelé par conception-parcelles.php
    CalendrierLignes.php     le calendrier PRÉVISIONNEL des lignes (bordures, lignes de service des planches) — mise en place et fin prévues, perturbations prévues en liste de dates ; appelé par conception-parcelles.php et conception-potager.php
    Interventions.php        le journal des perturbations d'une parcelle (`intervention`) — ajouter, modifier sous verrou de version, retirer ; appelé par suivi-perturbation.php
    Constats.php             les constats du saut du chat d'une parcelle (`constat`) — même conduite qu'Interventions ; appelé par suivi-saut-du-chat.php
    Reponses.php             lit/écrit la table reponse — question ⇒ texte
    Parcelles.php            les parcelles d'un site — création (appelée par conception-palette.php), plan, campagne de plantation
    Palettes.php             la palette d'une parcelle (`palette`) — choisir une espèce pour une place ou une fonction, retirer (jamais une espèce employée), reprendre les plantes de service d'une autre parcelle ; les espèces où le plan, le scénario et le potager puisent ; remplir une palette vide depuis ce que porte la parcelle ; sous le verrou de la parcelle
    Calques.php              calque_element — CRUD + géométrie (surface, longueur, emprise du terrain) ; appelé par api-calques.php
    Cadastre.php             parcelles cadastrales IGN (API Carto) — éphémère, jamais stocké ; appelé par api-cadastre.php
    Eau.php                  l'eau OpenStreetMap (Overpass) autour du fond tracé — devient une donnée du projet ; appelé par api-eau.php
    Vents.php                rose des vents calculée à la demande (Open-Meteo) — jamais persistée ; appelé par conception-etude-site.php
    Altimetrie.php           altitude IGN — un point, ou une grille pour le relief ; filtre le sentinel z=-99999 ; appelé par api-altitude.php
    ZoneUsda.php             zone de rusticité USDA estimée depuis 30 ans de minima (Open-Meteo, 1991-2020) — jamais persistée seule ; appelé par api-usda.php
    Version.php              les trois versions de l'atelier (format du fichier, schéma des données, numéro d'application) et la fenêtre de compatibilité d'un export
    Import.php               l'import d'un fichier d'export en projet neuf — valide chaque colonne d'après le schéma réel, une transaction ; appelé par importer.php
    Export.php               l'export d'un projet en JSON — lit chaque table en entier, retire comptes/identifiants/accès, plafond 1 Mo ; appelé par exporter.php
    PlanImprime.php          le plan d'une parcelle tel qu'il s'imprime (dessin, plants des bordures avec couleur et sigle, légende) — partagé par dossier-projet.php et feuillet-chantier.php
    MaterielDuSite.php       le matériel végétal de quelques parcelles (scénario ou plan de culture, plants du plan qui font foi) avant regroupement — partagé par conception-materiel-et-eau.php et dossier-projet.php
    PartBiomasse.php         la part de plants de biomasse d'une parcelle face aux 75 % — par planche au potager, sur la ligne centrale en pérenne ; appelé par conception-parcelles.php, conception-scenario.php, conception-potager.php
    ReleveSol.php            relevés de sol d'une parcelle (`releve_sol`) — infiltration, état d'humidité, jours depuis la pluie ; appelé par conception-materiel-et-eau.php
    Especes.php              référentiel de végétaux — cadre partagé (site_id=0) + surcharges par site, clé (nom_scientifique, variete), avec un calendrier par espèce (semis/repiquage/récolte, en mots comme Lopez)
contenu/
  .htaccess              refuse tout accès web
  calendrier-lopez.csv      le tableau « des semis et repiquages » de Lopez (17 cultures d'automne et d'hiver), recopié de connaissances/maraichage-naturel/lopez-plan-de-culture.md ; deux cellules « à vérifier » vides et marquées
  especes-modele-import.csv la fiche d'espèces du porteur (21 espèces, par variété, hauteur et largeur) — modèle téléchargeable depuis especes.php
  especes-cadre.csv         LE CADRE COMPLET (143 espèces, 34 colonnes), complété par une session de recherche Claude et relu par le porteur le 2026-09-30, puis le 2026-10-01 (119 espacements, 96 strates de service) — source du dernier bloc généré de sql/02-especes-seed.sql, qui prime sur la fiche ci-dessus ; pour changer le cadre : changer ce CSV, régénérer le bloc (chaque valeur par Especes::valider(), une case vide ne touche à rien)
  guildes-plantae.json      instantané de `guilde.csv` de Plantae (179 plantes, 564 lignes) — écrit par bin/catalogue-plantae.py, jamais à la main ; vieillit, à réexporter
  associations/             trois tables CSV de Weinrich (potager, verger, succession), importées telles quelles de `syntropie` le 2026-09-20 — voir Associations.php
  documentation-atelier.md  livret « L'atelier » — un chapitre par ligne « # », voir Documentation.php
  documentation-saf.md      livret « L'agroforesterie syntropique » — modèle à remplacer
  presentation-utilisateurs.html  la présentation de l'atelier (15 diapositives), générée par Claude puis corrigée par le porteur le 2026-10-03 — référence de son style d'écriture ; non servie, à adapter (chantier-presentation.md)
  sources-de-l-atelier.md   livret « Les sources » — d'où viennent les règles de l'atelier et à quoi elles servent ; écrit à la demande du porteur le 2026-10-02
  glossaire.md              un mot technique = un chapitre, une phrase — alimente glossaire.php ET la bulle des `.terme` ailleurs sur le site
config/
  .htaccess              refuse tout accès web — identifiants en clair dans base.php
  base.php.exemple       gabarit de configuration de production
  local.env              configuration locale — hors dépôt (.gitignore)
sql/
  .htaccess              refuse tout accès web
  00-comptes.sql          4 tables — compte, acces, tentative, invitation (+ migration portable de invitation.role_site)
  01-syntropia.sql        le schéma — site, parcelle, calque_element, espece, scenario_ligne…
  01-syntropia.sql       … et à sa fin la table `atelier_version` + la ligne `schema` (DML rejoué) : à monter avec `Version::SCHEMA` ; `site.origine` (schéma 2), la table `constat` (schéma 3), `parcelle.fin_plantation` (schéma 4) et `constat.adventices` (schéma 5) et `succession_ligne.planche` (schéma 6) et `succession_ligne.date_semis` (schéma 7) et `succession_ligne.nb_plants` (schéma 8) et `parcelle.stade` (schéma 9) et `espece.periode_semis`/`periode_repiquage`/`periode_recolte` (schéma 10) y ont leur migration additive
  02-especes-seed.sql     DML, pas du schéma — 53 espèces au cadre (site_id=0), dont la fiche du porteur (contenu/especes-modele-import.csv) en fin de fichier ; rejoué à chaque bin/dev.py init
bin/
  .htaccess              refuse tout accès web — des scripts, pas des pages
  dev.py                  serveur de développement — voir Commandes
  verifier-export-import.php  l'aller-retour export → import → export d'un projet — appelé par `dev.py verifier-export`, JAMAIS en direct
  compte.php              crée/modifie un compte en CLI — appelé par `dev.py compte`, JAMAIS en direct (voir § Point de reprise)
  remplir-palettes.php    remplit les palettes VIDES depuis ce que portent les parcelles (Palettes::remplir) — simulation par défaut, `--ecrire` pour écrire ; lancé une fois en production au déploiement des Phases 4 et 6 de la palette ; sur ce poste, exporter config/local.env d'abord
  deployer.py             déploiement vers alwaysdata — etat/fichiers/config/base/preparer
  catalogue-plantae.py    exporte les noms botaniques de plantae-corpus vers contenu/catalogue-scenario.json (autocomplete du scénario), et un instantané de guilde.csv vers contenu/guildes-plantae.json
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
  definition-site.php        étape 4 (Définition) — trois onglets CSS purs : le lieu (carte réelle), les observations, la conduite
  conception-etude-site.php  étape 1 (Conception) — la carte interactive du terrain, calques.js
  conception-etude-sol.php   étape 2 (Conception), gardée par Authentification::exige()
  conception-palette.php     étape 3 (Conception) — la parcelle y naît (code, nom, type, conduite, tortue), puis sa palette en trois onglets : productions principales, secondaires, plantes de service par fonction
  conception-potager.php     étape 5 (Conception) pour une parcelle de type potager — le plan de culture par planche, les voisinages de Weinrich ; `conception-scenario.php` y redirige un potager
  conception-scenario.php    étape 5 (Conception) — le tableau temps × strates d'une parcelle (`?parcelle=ID`) : frise SVG, ou pastilles par strate ; saisie dans le panneau Données
  conception-parcelles.php   étape 4 (Conception) — le plan d'une parcelle (bordures, planches, lignes, plants des bordures à leur envergure) ; zoom ; `?nouvelle=1`, ou un projet sans parcelle, renvoie à conception-palette.php
  conception-materiel-et-eau.php  étape 6 (Conception) — deux onglets : le matériel végétal à se procurer (`?parcelle=ID` ou `?tout=1`), et l'eau (besoin, ressource, fréquence d'arrosage d'après les tests de sol)
  conception-calendrier.php  étape 7 (Conception), gardée par Authentification::exige()
  releve-vierge.php          fiches de relevé de sol à imprimer — HORS GABARIT, voir son en-tête
  dossier-projet.php         le Dossier Projet à imprimer (couverture, Définition ; chapitres cochés en CSS pur) — HORS GABARIT, seconde exception ; section « Dossier Projet » de gabarit.js, avant « Documentation »
  feuillet-chantier.php      le feuillet du chantier de plantation d'une parcelle (plan en paysage, où va quoi, colonnes à pointer) — HORS GABARIT, sous-dossier du Dossier Projet ; ouvert depuis realisation-outils-et-plans.php
  realisation-preparation-parcelle.php  étape 1 (Réalisation)
  realisation-materiel-vegetal.php      étape 2 (Réalisation)
  realisation-outils-et-plans.php       étape 3 (Réalisation)
  realisation-consignation.php          étape 4 (Réalisation)
  suivi-arrosage-germination.php        étape 1 (Suivi)
  suivi-perturbation.php                étape 2 (Suivi) — le journal des perturbations d'une parcelle (`?parcelle=ID`)
  suivi-saut-du-chat.php                étape 3 (Suivi) — les constats du saut du chat d'une parcelle, et ce qui le rend possible
  suivi-consignation.php                étape 4 (Suivi) — redirige vers conception-parcelles.php, fin du parcours construit
  inscription.php            inscription sur invitation — code de 8 caractères, voir Invitations.php
  documentation-site.php     livret « L'atelier » — lit contenu/documentation-atelier.md, pagine ses chapitres trop longs
  documentation-saf.php      livret « L'agroforesterie syntropique » — lit contenu/documentation-saf.md, même mécanique
  documentation-sources.php  livret « Les sources » — lit contenu/sources-de-l-atelier.md (d'où viennent les règles, à quoi elles servent), même mécanique
  glossaire.php              les mots techniques, un par un — recherche filtrée, lit contenu/glossaire.md
  api-calques.php            POST ajouter/modifier/retirer un tracé — appelée par conception-etude-site.php (assets/js/calques.js)
  api-cadastre.php           POST parcelles cadastrales IGN d'une emprise — lecture seule, rien n'est enregistré
  api-eau.php                POST l'eau OpenStreetMap autour du fond tracé — pose des tracés réels sur le calque eau, dédup par proprietes.osm
  api-usda.php               POST estime la zone USDA d'un point — lecture seule, appelée par le bouton « Estimer » de definition-site.php
  rejoindre.php              rejoindre un projet avec un code — pour un compte existant ; menu du compte (gabarit.js), et lien d'invitation ouvert connecté (inscription.php)
  compte.php                 Préférences : récapitulatif du compte et de ses projets, nom affiché, mot de passe — 3 onglets ; `exige()` y renvoie un compte dont le mot de passe doit changer (?mdp=1)
  membres.php                les participants du projet actif, et le retrait d'un accès — réservée au porteur ; menu du compte (gabarit.js)
  journal-connexions.php     le journal des connexions réussies (table `connexion` de la base des comptes, sans adresse IP, 90 jours) — réservée aux administrateurs de l'atelier ; menu du compte (gabarit.js, drapeau `administrateur` d'api-projets.php)
  exporter.php               exporter le projet en un fichier JSON daté — réservée au porteur ; menu du compte (gabarit.js) ; hors parcours, sur le gabarit
  importer.php               importer un projet depuis un fichier d'export — tout compte connecté ; trois temps (vérifier, aperçu, créer) ; menu du compte (gabarit.js) ; hors parcours, sur le gabarit
  especes.php                 le référentiel de végétaux d'un site : voir/éditer une surcharge, importer un CSV (tout ou rien) — hors parcours, menu du compte
  inviter.php                ouvrir/lister/retirer des codes de parrainage — hors parcours, sur le gabarit ; appelée par le menu du compte (gabarit.js)
  api-projets.php            GET les projets du compte + le projet actif ; POST change le projet actif — appelée par gabarit.js sur toute page connectée
  api-altitude.php           POST relève une grille d'altitudes IGN sur l'emprise du fond tracé — un seul relevé par site, le suivant remplace le précédent
  assets/
    css/gabarit.css        la coquille du bureau — TOUTES les pages, publiques comprises ; `.terme` (mot expliqué au survol) y vit aussi
    css/dessins-papier.css les dessins imprimés (terrain, plan, frise) et leur légende — dossier-projet.php et feuillet-chantier.php
    js/carte.js              moteur de tuiles sans dépendance — OpenStreetMap par défaut, fonds IGN au choix sur la grande carte (table `FONDS`) — partagé par toute carte
    js/calques.js            dessin des calques sur carte.js — modes via Gabarit.onOutilChange(), placement de parcelle par rectangle
    js/etapes.js            le manifeste des phases — `pret: true` = la page existe déjà
    js/gabarit.js           Gabarit.init() ; grise précédente/suivante vers une étape non `pret` ; initTermes() arme la bulle de tout `.terme`
    js/livret.js             pagine un chapitre de documentation-{site,saf}.php trop long pour l'écran — mesuré en pixels, pas un compte de paragraphes
    polices/               Clash Display, Archivo, reprises de le-bureau/fonts/
    images/lisiere.svg     la lisière dessinée par le porteur — fond du bandeau général (masque CSS, gabarit.css)
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
