# Le Dossier Projet — un document imprimable du projet

**Demandé par le porteur le 2026-09-19 ; Phase 1 faite le 2026-10-02. Les six
questions ont reçu leur réponse le jour même (§ Décisions du porteur).** Le porteur l'a
formulé ainsi : « créer la fonctionnalité de création d'un Dossier Projet. Un
document imprimable pour que l'utilisateur puisse avoir son projet au format
papier. Dans le menu de gauche par exemple avant documentation. » Ce document
garde ce qui est demandé, ce que je propose, et **ce qu'il faut trancher avant
de coder** (§ Questions ouvertes). Les décisions du porteur sont regroupées juste après ; le reste du texte, avant
elles, garde la proposition d'origine et est corrigé par elles.

**Quand le reprendre** : sur demande du porteur. Rien ne le bloque techniquement —
toutes les étapes du parcours existent depuis le 2026-09-19 — mais **ce qu'il
imprime dépend de ce qui est enregistré** : relire au moment de s'y mettre ce que
chaque page persiste réellement (`sql/01-syntropia.sql`), la liste plus bas date
du 2026-09-19.

## Décisions du porteur — 2026-09-19

Réponses aux six questions, telles que données :

1. **Seconde exception au gabarit : oui.** `dossier-projet.php` sera, comme
   `releve-vierge.php`, une page hors bureau — un document destiné au papier. Le
   CLAUDE.md devra le dire (§ Directives de travail : deux exceptions, pas une), en
   gardant la même réserve : **ne pas s'en servir de précédent pour autre chose qu'un
   document destiné au papier.**
2. **Tracés seuls, pas de carte de tiers.** Aucun fond OpenStreetMap ni IGN dans le
   dossier : les tracés du terrain sur fond blanc, avec échelle et nord. Cela écarte
   aussi la question de la licence d'usage papier des fonds IGN (jamais lue).
3. **Un choix des chapitres à imprimer.** Une étape de réglage avant l'impression : des
   cases à cocher, un chapitre par case. Les valeurs par défaut : tout coché. Le choix
   n'est pas enregistré (il se refait à chaque impression) sauf demande contraire.
4. **Le nom : « Dossier Projet »**, avec deux majuscules, partout — menu, titre, livret.
5. **Réalisation et suivi : à voir à l'impression.** Pas d'information de plus pour
   l'instant : on regardera ce que ces deux phases donnent une fois le dossier
   construit, et ce qui n'est pas persisté ne s'imprimera pas.
6. **Un sous-dossier par parcelle : oui, et c'est le document de chantier pour
   l'implantation.** Ce n'est donc plus une suite facultative : c'est un livrable à
   part entière, destiné à être **emporté sur la parcelle le jour de la plantation**
   (voir § Le sous-dossier de parcelle).

### Ce que ces décisions changent au plan

- **Phases** : le choix des chapitres (3) entre dans la **Phase 1** — sans lui il n'y a
  rien à imprimer « en partie ». Le **sous-dossier de parcelle** (6) devient une
  **Phase 5**, après le dossier général ; il réutilise le plan, le scénario, le matériel
  et l'eau de la Phase 3.
- **Dessins** : la question du fond de carte (2) est close : tracés seuls, donc aucune
  tuile à charger et un PDF identique d'un poste à l'autre.
- **Vérification** : la question du fond IGN n'est plus à instruire.

### Le sous-dossier de parcelle — le document de chantier

À définir avec le porteur avant sa phase, mais ce que sa fonction impose déjà :
- **Un feuillet par parcelle**, lisible **debout sur le terrain**, au crayon : le plan à
  l'échelle avec ses planches et ses bordures, **où va quoi** (chaque végétal à son
  emplacement, à son espacement), **combien** (les quantités, estimées ou saisies, avec
  leur étiquette), **d'où ça vient** (acheté, multiplié, RNA), **quand** (les périodes du
  scénario), et de l'espace pour **noter** ce qui est fait — la consignation de la
  Réalisation pourra s'y répondre.
- **La liste de plants** prend ici sa pleine utilité : c'est la liste de courses et de
  pointage du jour J. Les quantités « estimées » doivent y rester marquées, avec de la
  place pour la quantité réellement plantée.
- **Il rejoint `realisation-outils-et-plans.php`** (« édition des plans d'implantation à
  destination des personnes qui participent au chantier », `directives.txt`) : le sous-dossier
  est cette édition. À relire au moment de s'y mettre pour ne pas construire deux fois.

## Pourquoi

Un projet agroforestier se vit dehors : sur le terrain, sans écran, avec un
crayon. Aujourd'hui tout ce que l'atelier sait du projet est éparpillé sur une
vingtaine d'écrans, et **rien ne se lit d'une traite ni ne s'emporte**. Le
Dossier Projet rassemble en un seul document daté : la vision, le lieu, les
parcelles, le scénario, ce qu'il faut se procurer, l'eau, le calendrier — de quoi
discuter avec un voisin, un financeur, un pépiniériste, ou simplement relire
sa propre intention dans un an.

**Ce que ce n'est pas** :
- **Pas la sauvegarde** : c'est `chantier-export-import-projet.md` (un JSON qui
  se ré-importe). Le dossier se lit, il ne se recharge pas.
- **Pas le manuel** : c'est `chantier-manuel-utilisateur.md` (comment se servir de
  l'outil). Le dossier parle **du projet de la personne**, pas de l'atelier.
- **Pas le livret des relevés** (`releve-vierge.php`, fiches vierges à remplir
  sur place) : lui est vide, le dossier est plein.

## Ce que je propose

### La page : un document, pas une page du bureau

`public/dossier-projet.php`, **hors gabarit**, comme `releve-vierge.php` : le
bureau verrouille tout défilement (`overflow:hidden`), l'inverse exact de ce
qu'exige un document de plusieurs pages. **C'est la seconde exception** à la
règle « le site EST le bureau », et **CLAUDE.md dit** de ne s'appuyer sur la
première que « pour un document destiné au papier » — c'est bien le cas, mais
**c'est au porteur de confirmer que cette seconde exception est admise** (§ Questions).

Ce qui reste du bureau : ses polices (Clash Display, Archivo) et ses couleurs, comme
la fiche de relevé. Une **barre d'action** hors impression (`@media print` la masque) :
« Imprimer / Enregistrer en PDF » (le navigateur sait faire le PDF — **aucune
bibliothèque à ajouter**, le dépôt n'a aucune dépendance), « Retour à l'atelier ».

### Le menu : une section qui précède « Documentation »

`gabarit.js` construit déjà la section « Documentation » en dernière position, sur
toute page (`remplirSectionDocumentation()`). Le Dossier Projet n'a pas sa place dedans :
il appartient au **projet** de la personne, pas à la documentation de l'outil, et il
n'a de sens que **connecté**. Proposition : une entrée « Dossier Projet » dans
une section propre placée **juste avant « Documentation »**, ajoutée seulement
quand la personne est connectée — le même signal que `initProjets()` (qui interroge
`api-projets.php`) sait donner. Une page publique n'affiche donc pas cette section.

### Ce que le dossier contient, dans l'ordre du parcours

Le plan suit les quatre phases (règle du parcours : l'ordre chronologique) :

1. **Couverture** : nom du projet, porteur, **date d'édition**, la conduite (syntropie
   ou jardin-forêt).
2. **Définition** : la vision, les objectifs (destinations, terme, productions), les moyens
   (connaissances, temps, budget, terrain), le site (commune, position, superficie, zone USDA,
   exposition, pente, climat).
3. **Le terrain** : la carte — les tracés du terrain (limites, eau, relief, ressenti…) — et,
   par calque, ce qui a été dessiné.
4. **Chaque parcelle** : code, nom, type, dimensions, **plan** (bordures externe et interne,
   planches, sens), **scénario** (la frise ou les strates), et pour chacune ce qu'il faut
   **se procurer** et **l'eau** (besoin, ressource, fréquence d'arrosage d'après les tests de sol).
5. **Le calendrier**, saison par saison.
6. **La réalisation et le suivi**, ce qui a été consigné.
7. **Les participants** et leur rôle.

**Un chapitre sans donnée se dit lui-même** : « pas encore renseigné — l'étape se fait ici »,
jamais un chapitre vide, jamais un chapitre omis en silence (on voit ce qui manque
au projet). Les **estimations restent étiquetées** comme à l'écran (« estimé »,
« ordre de grandeur, source Lopez ») : un papier survit à l'écran, et un chiffre
détaché de sa réserve devient une promesse.

### Les dessins : du SVG, pas des tuiles

Le plan de parcelle, la frise du scénario et les tracés de la carte sont déjà du
**SVG en unités réelles** — ils s'impriment nets à toute taille. **Les tuiles de fond
(OpenStreetMap, IGN) sont une autre affaire** : chargées depuis le navigateur du
visiteur, elles ne s'impriment pas toujours, et leur licence d'usage papier n'est pas
lue. Proposition de départ : **tracés sur fond blanc avec échelle et nord**, sans fond
de carte ; le fond IGN/OSM est une amélioration à décider (§ Questions).

### Vérifier au moment de coder

- **Pagination papier** : `break-inside: avoid` pour un tableau ou un dessin, saut avant
  chaque phase, en-tête/pied de page (projet, date, numéro) — via `@page` et
  `@media print`. **À vérifier en vrai** : Chrome headless sait produire un PDF
  (`Page.printToPDF` en CDP), ce qui permet de contrôler la pagination sans imprimante.
- **Format A4 portrait**, marges de 15 mm ; couleurs qui passent en noir et blanc
  (les fonds de frise doivent rester lisibles à l'impression monochrome — motifs plutôt
  qu'aplats seuls).
- **Lecteur** : peut lire donc imprimer (rien ne s'écrit). Le dossier n'écrit rien.
- **Un projet vide** ne plante pas : couverture et chapitres « pas encore renseigné ».

## Phase 1 — faite le 2026-10-02

Demandée par le porteur (« suivre l'ordre du chantier » plutôt qu'une impression du plan seul).
- **`public/dossier-projet.php`**, hors gabarit (en-tête : la seconde exception), polices et couleurs du
  bureau ; barre d'action (« Retour à l'atelier », « Imprimer / Enregistrer en PDF »), masquée au papier.
- **Le choix des chapitres** : une case par chapitre (couverture, vision, objectifs, moyens, site), toutes
  cochées à l'ouverture, **en CSS pur** (`body:has(#imprimer-x:not(:checked)) #chapitre-x{display:none}`) —
  ce qu'on voit est ce qui s'imprime, rien n'est enregistré, aucun aller-retour au serveur.
- **La couverture** : nom du projet, porteur(s) (nom affiché, à défaut identifiant), commune, conduite,
  date d'édition (en mots, heure de Paris). **La Définition** : chaque question avec son libellé de l'étape
  et sa réponse, les choix dits par leur libellé (« Moyen — 5 à 10 ans »), la superficie en m², la position
  du site en coordonnées ; une question sans réponse : « pas encore renseigné » ; un chapitre sans aucune
  réponse le dit et nomme l'étape où il se remplit.
- **Les réponses de qui ?** — tranché en codant, sur la règle déjà suivie (`preparationDuSite()`,
  `Scenarios::souhaitsDuSite()`) : celles de la personne si elle en a donné, à défaut celles du porteur ;
  le dossier écrit « Réponses de … ». `Reponses::definitionDuProjet()` — **lecture seule**, ne crée jamais
  de participant (vérifié : un lecteur sans participant ouvre le dossier, aucune ligne créée).
- **Le papier** : A4 portrait, marges 15 mm, pied de page « projet — Dossier Projet — page n sur N »
  (`@page` + `@bottom-center`, sans pied sur la couverture) ; **un saut de page après la couverture et
  avant chaque phase**, les chapitres d'une même phase s'enchaînent (un chapitre ne se coupe pas s'il tient
  sur une page) — une page par chapitre gaspillait le papier. Le titre de phase (« Définition ») n'apparaît
  qu'au papier, et disparaît si aucun de ses chapitres n'est coché.
- **Le menu** : `gabarit.js` ajoute la section « Dossier Projet » (« Imprimer le dossier ») juste avant
  « Documentation », quand `api-projets.php` répond un projet actif ; une page publique ne l'a pas.
- **Vérifié en local** (compte lecteur de passage sur un projet réel, supprimé ensuite) : section du menu,
  contenu fidèle aux réponses en base, PDF produit par `Page.printToPDF` et relu page par page (3 pages,
  2 avec deux chapitres décochés), « aucun chapitre coché », 360 px (marges de 16 px), anonyme renvoyé à
  la connexion. **Trouvé en testant** : le nom du porteur (un tableau, non une chaîne), la superficie sans
  unité, le nom du projet dans le pied de page — une chaîne CSS, échappée à la manière du CSS (dans
  `<style>`, une entité HTML ne se décode pas).
- **Non fait** : le chapitre « Participants » (Phase 3) — la couverture ne nomme que le porteur.

## Phase 2 — faite le 2026-10-03, pas encore déployée

- **`src/DessinsDossier.php`** (pur) : le terrain (les tracés de `calque_element`, projection locale de `Calques`, le
  nord en haut, échelle et flèche du nord, sans fond de carte ; du relief, les courbes de niveau seules), le plan d'une
  parcelle (bordures externe et interne, planches, pérennes hachurées, lignes avec la centrale en trait plein, ligne
  des lianes, plants des bordures avec leur sigle, côtés, dimensions, échelle) et la frise du scénario (même lecture
  que l'écran, sans lien). Largeur fixe de 1000 unités.
- **`dossier-projet.php`** : une phase « Conception » (saut de page avant), le chapitre « Le terrain » (dessin,
  légende des calques, tableau de ce qui a été tracé avec sa mesure) et **une page par parcelle** : ce qui la définit
  (type, conduite, tortue, stade, dimensions, planches, campagne de plantation), le plan et sa légende, les
  bordures (série et nombre de plants), les lignes de service des planches, puis le scénario (frise en syntropie,
  strates en jardin-forêt) ou, pour un potager, le plan de culture par planche. Une case par chapitre, comme en
  Phase 1.
- **Vérifié** : les trois dessins, rendus en lecture seule sur La Vadaux (base locale), puis capturés.
- **Vérifié par le porteur** en local, avec son compte : la page et un PDF de 10 pages imprimé depuis Firefox. Corrigé
  après lecture : la légende du plan ne parle plus de hachures sans planche pérenne, et nomme la ligne des lianes ; un
  titre de section (« Le plan ») ne reste plus seul en bas de page — chaque titre et son contenu forment un bloc
  insécable (`.bloc`), Firefox ignorant `break-after:avoid`. Les courbes de niveau se recalculent depuis la grille,
  comme dans `conception-etude-site.php` (elles ne sont pas stockées) ; le libellé du relief se recompose depuis le
  pas, et `api-altitude.php` l'arrondit désormais à l'écriture.
- **Corrigé en passant** (demandé par le porteur le 2026-10-03) : la demi-planche du bord est (ou sud) a sa ligne
  centrale du côté de la bordure — `Planches::estDemiPlancheDeFin()`, même règle dans `conception-parcelles.js`,
  `LignesDeService::proposer()`, `conception-potager.php` et le dossier. Vérifié à l'écran par le porteur. Le livret
  « L'atelier » (passage sur les demi-planches) est à compléter avec le déploiement.
- **Laissé tel quel, accord du porteur** : le PDF de Firefox porte ses propres en-têtes (adresse, date) et ignore le
  pied de page de `@page` — c'est un réglage de la boîte d'impression.

## Phases proposées

| Phase | Contenu | Résultat |
|---|---|---|
| **1 — Le squelette** | `dossier-projet.php` hors gabarit, **choix des chapitres à imprimer** (cases à cocher), couverture, Définition (vision, objectifs, moyens, site), barre d'action, `@page`/`@media print`, section « Dossier Projet » avant « Documentation » dans `gabarit.js`. | On imprime un début de dossier, chapitre par chapitre ; le menu est en place. |
| **2 — Le terrain et les parcelles** | Tracés de la carte en SVG (échelle, nord), une page par parcelle : dimensions, plan SVG, scénario. | Le dossier montre le projet dans l'espace. |
| **3 — Matériel, eau, calendrier** | Récapitulatif des plants et de l'eau par parcelle (réutilise `Materiel`, `BesoinEau`), calendrier, participants. | Le dossier est complet sur la Conception. |
| **4 — Réalisation, suivi, finitions** | Ce que ces deux phases donnent à l'impression (à voir), pagination vérifiée par PDF produit en CDP, impression monochrome, petits écrans (le bouton « Imprimer » reste accessible), livret « L'atelier » à jour. | Le dossier général est terminé, livret relu. |
| **5 — Le sous-dossier de parcelle** | Le feuillet de chantier de chaque parcelle : plan à l'échelle, où va quoi, combien, d'où, quand, place pour pointer (§ Le sous-dossier de parcelle). À cadrer avec le porteur avant de commencer. | Un document emportable le jour de la plantation. |

Chaque phase se vérifie en produisant un PDF (`Page.printToPDF`) et en le lisant, pas
seulement en regardant l'écran — un document se juge sur le papier.

## Questions ouvertes — toutes tranchées le 2026-09-19 (voir § Décisions du porteur)

Gardées pour mémoire, avec leur réponse :

1. ✅ **Seconde exception au gabarit** — page hors bureau, comme `releve-vierge.php` : admise ?
   (L'alternative — un mode d'impression du bureau lui-même — se heurte au verrou de
   défilement et à `height:100dvh` ; je la déconseille.)
2. ✅ **Fond de carte** — tracés seuls sur fond blanc (proposé), ou fond IGN/OSM quand il s'imprime ?
   La seconde option demande de lire les conditions d'utilisation papier des fonds IGN.
3. ✅ **Périmètre** — tout le projet d'un coup (proposé), ou un choix des chapitres à imprimer
   (cases à cocher avant l'impression) ? Le choix ajoute une page de réglage.
4. ✅ **Le nom** — « Dossier Projet » (dit par le porteur), ou « Dossier du projet » ? À fixer une fois.
5. ✅ **Ce qui est consigné pendant la Réalisation et le Suivi** — à relire dans le schéma et
   les pages avant de promettre un chapitre : ce qui n'est pas persisté ne s'imprime pas.
6. ✅ **Un dossier par parcelle en plus du dossier général ?** (un feuillet à emporter sur
   une parcelle) — probablement une suite, pas la Phase 1.

## Liens avec les autres chantiers

- `chantier-export-import-projet.md` : mêmes données, autre finalité. Le dossier peut
  **réutiliser la lecture** que l'export aura à écrire ; ne pas les coder deux fois
  si l'un des deux vient avant l'autre.
- `chantier-manuel-utilisateur.md` : ses captures d'écran pourront montrer ce dossier ;
  il n'en dépend pas.
- **Livret « L'atelier »** : un chapitre s'écrit **avec** le déploiement (règle du
  2026-09-19) — à ajouter au garde-fou `bin/verifier-documentation.py`.
