# La palette végétale d'une parcelle — plan

**Demandé par le porteur le 2026-10-05. Toutes les questions tranchées par le porteur le 2026-10-05, et la
proposition « site, projet, parcelle » confirmée le même jour, avec la palette du projet en plus. Phase 1 faite le
2026-10-07 et déployée : voir § Fait.**

## La demande

Aujourd'hui, les plantes d'une parcelle se choisissent au moment du dessin, dans une longue liste de végétaux, sans
réflexion préalable sur les plantes de service. Le porteur veut une **étape avant le plan de la parcelle**
(`conception-parcelles.php`) qui prépare **la palette végétale de la parcelle** en trois temps :

1. **les productions principales** — l'incontournable, ce que la personne veut impérativement produire ;
2. **les productions secondaires** — un « plus », non impératif ;
3. **les plantes de service, classées par fonction** — que la personne sélectionne selon ses moyens et ses compétences.

La palette dit aussi **l'origine de chaque plante** : achat, semis, bouturage, marcottage, transplantation…

## Ce que disent les sources

- **`directives.txt`**, phase de définition : « une parcelle = un objectif avec productions principales, productions
  secondaires. Quels moyens sont déjà en présence ; pieds mères pour le bouturage, graines à récolter, végétaux utiles au
  projet. » La palette est cette ligne, mise en œuvre.
- **Théry, ch. 11, et la formation, `7-7`** : l'ordre de remplissage du scénario est prescrit — les objectifs principaux
  d'abord (« tout se construit autour d'eux »), puis les productions secondaires, puis les arbres « au-delà de la
  production », la biomasse, et les semis annuels du saut du chat en dernier : « voyez loin d'abord, puis prenez soin du
  lendemain ». Les trois couleurs du tableau le disent aussi : rouge, production principale ; orange, production
  secondaire ; vert, biomasse. La palette pose cet ordre **avant** le dessin, et non pendant.

## Où en est le code

- **Les productions principales et secondaires existent**, mais elles se choisissent dans le **Scénario**, après le
  plan (onglet « Les productions », `Scenarios::productions()`). Ce sont des textes, tirés de la réponse « Quelles
  productions ? » des Objectifs, et gardés dans `parcelle.production_principale` / `production_secondaire`. Le scénario
  refuse d'ajouter un végétal tant qu'aucune production principale n'est choisie.
- **Les plantes de service se choisissent dans le référentiel entier**, à trois endroits : les bordures (proposition et
  menu d'ajout, `Bordures::proposer()`), les lignes de service des planches (`LignesDeService::proposer()`), la ligne des
  lianes. Les règles de fonction et d'orientation trient, mais la liste reste celle de toutes les espèces du site.
- **L'origine est notée par espèce, au cadre**, pas par projet : `espece.origine` (achat, multiplication, RNA, mixte) et
  `espece.type_plant`, un texte libre (« plant en conteneur », « godet sous abri, ou bouture », « éclat de touffe »…).
  « Matériel et eau » regroupe le matériel végétal par cette origine. Une personne qui bouture ce que le cadre dit
  « à acheter » ne peut pas le dire.
- **Les étapes** de la Conception : Étude du site, Étude du sol, Créer les parcelles, Scénario, Matériel et eau,
  Calendrier. **Une parcelle naît dans « Créer les parcelles »** (code, nom, type), sur la même page que son plan.
- **En production** (lu le 2026-10-05) : 3 parcelles, dont une avec ses productions choisies, 15 lignes de scénario,
  85 plantes de bordure, 4 cultures de potager. Rien de cela ne doit se perdre.

## La proposition

Une nouvelle étape de la Conception, **« La palette »**, juste avant « Créer les parcelles », parcelle par parcelle.
La page se lit dans l'ordre du ch. 11, en trois onglets :

1. **Les productions principales.** Ce que la personne veut produire, choisi parmi les espèces d'Espèces, avec sa réponse
   « Quelles productions ? » des Objectifs rappelée en tête. Au moins une, comme aujourd'hui dans le Scénario.
2. **Les productions secondaires.** Même geste, facultatif.
3. **Les plantes de service, par fonction.** Un groupe par fonction (biomasse, bloque-herbe et couvre-sol, brise-vent,
   azote…), chacun avec ce qu'il apporte à la parcelle, dit en une phrase. Pour chaque espèce, ce qu'elle demande pour
   s'obtenir (son type de plant) : la personne choisit selon ses moyens et ses compétences. Les Moyens qu'elle a déclarés
   (connaissances, temps, budget) sont rappelés en tête, et un budget faible fait apparaître la régénération naturelle,
   comme dans `definition-moyens.php`.

Pour **chaque plante retenue**, son **origine dans ce projet**, choisie par la personne (une ou plusieurs). C'est elle
que « Matériel et eau » et la Réalisation (« Matériel végétal », les conseils de multiplication) liront ensuite.

**Ce que la palette change en aval** :
- le plan (bordures, lignes de service, lianes) et le plan de culture du potager travaillent sur **les espèces de la
  palette**, avec un lien pour la compléter ;
- le Scénario prend ses productions principales et secondaires dans la palette (son onglet « Les productions » s'efface)
  et ses végétaux aussi ;
- « Matériel et eau » regroupe par les origines du projet ; une espèce sans origine se dit « à décider ».

## Décisions du porteur, 2026-10-05

- **L'étape « La palette » est acceptée**, avant « Créer les parcelles ».
- **La parcelle naît dans la palette** : son nom appelle la palette à choisir. « Créer les parcelles » garde la forme,
  les dimensions et les plantes posées.
- **Le plan travaille à partir de la palette** : dimensionnement, bordures, placements, dénombrement, comme aujourd'hui,
  mais sur la liste des espèces de la palette. On revient à la palette pour la compléter. **Une espèce utilisée dans le
  plan ne se retire pas de la palette.**
- **Une plante peut avoir plusieurs origines** : plusieurs modes de multiplication pour une même espèce.
- **La palette sert aussi au potager** : son plan de culture y prend ses productions, et les lignes de ses planches leurs
  plantes de service.
- **La ligne des lianes** d'une tortue liane prend aussi ses espèces dans la palette : il y aura des lianes de service et
  des lianes de production.
- **L'origine est la responsabilité de la personne** : c'est elle qui décide, pour chaque espèce, comment elle se la
  procure — achat, reproduction, plusieurs à la fois (par exemple achat et bouturage). **Aucune donnée de difficulté**
  au cadre, et l'atelier ne préremplit pas les origines.
- **Les parcelles existantes** (des tests pour l'instant) : leur palette se remplit au déploiement depuis ce qu'elles
  portent, pour aider le porteur.
- **Les neuf fonctions de service** proposées sont retenues : biomasse ; bloque-herbe et couvre-sol ; brise-vent ; fixer
  l'azote ; nectar et pollen ; abriter les auxiliaires ; ombrer ; tuteur vivant ; protéger de l'abroutement.

## Décisions du porteur, 2026-10-07 (Phases 4 et 6)

Lu en production le jour même : les productions du Verger sont des catégories (« fruits » en principale, « Légumes » en
secondaire) ; douze noms du scénario et du potager ne sont pas des espèces du référentiel (Amandier, fruits rouges,
mais, menthe, Nashi, passiflore, symphytum officinale, troene, chevrefeuille arbustif, fraise, maïs doux, vesce) ; la
vigne, la caseille (rôle « production » seul) et le frêne (« secours ») sont posés en bordure sans fonction de service.

- **La palette décide des productions du Scénario** : une espèce en production principale dans la palette est une
  production principale du scénario. L'onglet « Productions » et les catégories disparaissent ; la réponse des
  Objectifs reste rappelée dans la palette.
- **Un nom libre se garde et se signale** : la Phase 6 fait entrer dans la palette ce qui se reconnaît ; le reste garde
  sa ligne telle quelle, signalée « hors palette », à rattacher par la personne. Les nouvelles lignes ne viennent que de
  la palette.
- **Une plante posée sans fonction de service au référentiel** entre comme plante de service avec la fonction de sa
  ligne : bordure externe → bloque-herbe ; bordure interne et ligne de planche → biomasse.
- **Les cultures d'un potager sans fonction de service** entrent en production principale.

## Ce qui revient au projet, ce qui revient à la parcelle — proposition

Le porteur pose la question : les productions changent d'une parcelle à l'autre, mais les plantes de service peuvent
être les mêmes ; et c'est l'observation du terrain qui dit ce qui peut être bouturé, transplanté, ou semé de graines
récoltées. Proposition, en trois niveaux :

**1. Le site : ce qu'il offre.** Les ressources végétales trouvées sur le terrain ou à proximité : un sureau de la haie
nord dont on prendra des boutures, des semis spontanés de noisetier à transplanter, des consoudes à diviser, des graines
de tournesol à récolter. Chacune dit **l'espèce, ce qu'elle offre** (boutures, marcottes, éclats, plants à transplanter,
graines), **où** et **quand**. Elles se notent là où l'on observe déjà le terrain : sur la carte de l'**Étude du site**,
un point du calque « La végétation existante » reçoit une espèce et ce qu'il offre — ce calque montre déjà « les bois,
les haies, les arbres isolés et les souches qui rejettent ». Une liste simple, dans la palette, sert à qui ne dessine
pas la carte. C'est la ligne de `directives.txt` : « pieds mères pour le bouturage, graines à récolter, végétaux utiles
au projet ».

**2. Le projet : comment on obtient chaque espèce.** Pour chaque espèce employée dans le projet, **ses origines** —
achat, semis, bouturage, marcottage, division, transplantation, régénération naturelle, plusieurs à la fois. Elles se
notent **une fois pour tout le projet** : bouturer le sureau vaut pour toutes les parcelles. **La personne les choisit
elle-même** (décision du porteur) ; l'atelier lui montre seulement, à côté, ce que le site offre pour cette espèce et le
type de plant noté au cadre, comme des repères.

**3. La parcelle : sa palette.** Les espèces de la parcelle, chacune à **sa place** — production principale, production
secondaire, ou service, avec ses fonctions. Les productions sont propres à chaque parcelle. Pour les plantes de
service, la palette d'une nouvelle parcelle propose de **reprendre celles d'une parcelle existante**, d'un geste, puis
d'ajuster.

**4. La palette du projet** (demandée par le porteur, 2026-10-05). Un projet est un site : sa palette **regroupe les
espèces de toutes les palettes de ses parcelles**. Elle se construit seule, sans saisie propre : pour chaque espèce, les
parcelles où elle figure et à quelle place (production principale ici, service là), et **ses origines**, puisqu'elles
valent pour tout le projet. C'est là qu'on les choisit, ou depuis la palette d'une parcelle, qui renvoie au même
enregistrement. Une espèce qui ne figure plus dans aucune parcelle sort de la palette du projet ; ses origines restent
notées, au cas où elle y reviendrait.

**Ce que ça donne en aval.** « Matériel et eau » compte les plants par parcelle et les regroupe par origine du projet ;
le site dit d'où ils viennent (« boutures : le sureau de la haie nord »). La Réalisation (« Matériel végétal ») donne les
conseils de chaque mode — quand bouturer, quand récolter les graines — et montre les ressources du site à aller chercher.

## Les phases

**Phase 1 — La palette de la parcelle.** Une table `palette` (parcelle, espèce, place, fonctions), avec export et import ;
`Version::SCHEMA` monte. La page « La palette » : la parcelle y naît (nom, type, conduite, tortue), puis les trois
onglets — productions principales, secondaires, service par fonction. La reprise depuis une autre parcelle.
`pret: true` dans `etapes.js` ; « Créer les parcelles » ne crée plus de parcelle.

**Phase 2 — La palette du projet et ses origines.** La vue « tout le projet » de la palette : les espèces de toutes
les parcelles, où et à quelle place. Une table des origines par espèce et par projet, plusieurs modes à la fois,
choisies par la personne, rien de prérempli ; une espèce sans origine se dit « à décider ».

**Phase 3 — Ce que le site offre.** Les ressources végétales : un point du calque « végétation » qui reçoit une espèce et
ce qu'il offre, et la liste simple de la palette ; elles s'affichent comme repères à côté des origines.

**Phase 4 — Le plan et le plan de culture lisent la palette.** Bordures, lignes de service, ligne des lianes (lianes
de service et de production), cultures du potager : la palette seule, avec le lien pour la compléter ; une espèce employée ne se retire pas. Le Scénario prend
ses productions dans la palette (son onglet « Les productions » s'efface).

**Phase 5 — L'origine sert.** « Matériel et eau », le Dossier Projet et la Réalisation (« Matériel végétal ») par
origine, avec les ressources du site.

**Phase 6 — Les parcelles existantes et le livret.** Leur palette remplie depuis leurs données (les origines restent à
décider par la personne), vérifiée en local avant
tout déploiement ; le livret « L'atelier » et le glossaire dans la même vague.

## Fait

**Phase 1, le 2026-10-07 — vérifiée en local par le porteur, déployée le même jour (`5179420`, schéma 21).**

- **La table `palette`** (schéma 21) : une ligne par espèce et par parcelle, sa `place` (`principale`, `secondaire`,
  `service`) et ses `fonctions` (les neuf clés de `Palette::FONCTIONS`, multivaluées par `|`). L'espèce se désigne par
  `nom_scientifique` + `variete`, comme `ligne_plantation`. Export et import la portent (`palette`, enfant de la
  parcelle) ; `bin/dev.py verifier-export` passe, le projet de vérification compris.
- **Une production peut aussi rendre un service** (le nectar d'un fruitier) : elle garde sa place et gagne la fonction ;
  l'onglet service la montre avec son repère « production principale ». Choisir comme principale une espèce déjà
  secondaire (ou de service) la fait passer de place. Retirer une production qui rend un service la laisse comme plante
  de service ; une plante de service sans plus aucune fonction sort de la palette.
- **La page `conception-palette.php`**, étape « La palette » de `etapes.js`, avant « Créer les parcelles ». La parcelle y
  naît : code, nom, type, conduite et tortue (`Parcelles::creer()`). Puis trois onglets dans le bureau : productions
  principales, secondaires (la réponse « Quelles productions ? » des Objectifs rappelée), plantes de service (les
  Moyens rappelés, la régénération naturelle assistée quand le budget est faible ; les neuf fonctions, chacune avec ce
  qu'elle apporte ; les espèces que le référentiel associe à la fonction en tête de la liste). À côté de chaque espèce,
  son type de plant au cadre ; au survol d'une plante choisie, le même repère. La palette se réduit au lieu de défiler
  (vérifié à 320 × 568, 375 × 667, 1366 × 768, thèmes clair et sombre).
- **La reprise** : les plantes de service d'une autre parcelle du projet (celles dont la place est « service » ; les
  productions, propres à chaque parcelle, ne se reprennent pas). Une espèce déjà présente gagne les fonctions qui lui
  manquaient.
- **« Créer les parcelles » ne crée plus de parcelle** : `?nouvelle=1`, ou un projet sans parcelle, renvoie à la palette ;
  « Nouvelle parcelle » mène à la palette depuis toutes les pages ; « La palette » s'ajoute au menu de chaque parcelle
  (plan, scénario, potager, matériel et eau). Le nom de l'étape « Créer les parcelles » reste celui-là.
- **Le livret « L'atelier »** : chapitre « Concevoir : la palette », relu au déploiement. **Pas encore fait** : le
  glossaire (Phase 6). Les parcelles de production auront une palette vide jusqu'à la Phase 6 ; rien en aval ne la lit encore.

**Phases 4 et 6, le 2026-10-07 — en local, pas déployées** (pas de changement de schéma).

- **Le plan puise dans la palette** (`conception-parcelles.php`) : la proposition d'une bordure, son menu d'ajout, la
  répartition sur les bordures et les lianes ne montrent que les espèces de la palette, avec « Compléter la palette ».
  Ce qui est déjà posé reste affiché tel quel.
- **Le serveur le garantit** (`LignesPlantation::horsPalette()`) : une espèce nouvellement posée sur une bordure, une
  ligne de planche ou par la répartition doit être dans la palette ; ce qui était déjà sur la ligne peut y rester.
- **Le Scénario** (`conception-scenario.php`) : le végétal se choisit dans la palette, groupé par place, et sa production
  vient de sa place (décision du porteur) — principale, secondaire, ou accompagnement. L'onglet « Productions » et le
  choix « Production visée » disparaissent ; « La palette » remplace le bouton « Productions ». Une ligne au nom libre se
  signale « hors palette », se modifie sous son nom avec la production qu'elle portait, ne se recopie pas en nouvelle
  ligne, et se rattache en choisissant une espèce de la palette (« Je corrige le nom »). `Scenarios::productions()`,
  `Scenario::selectionDe()` et `versionProductions()` sont retirés ; les colonnes `parcelle.production_*` restent en
  base, avec leurs données, plus lues.
- **Le potager** (`conception-potager.php`) : la culture se choisit dans la palette ; son nom enregistré reste le nom
  usuel (`Palette::nomCourt()`), que les tables de voisinage et le calendrier reconnaissent. Les lignes des planches ne
  proposent que la palette, et gardent la plante déjà posée (« hors palette »). Les cultures au nom libre se signalent.
- **Une espèce employée ne sort pas de la palette** (`Palettes::emplois()`) : employée dans le plan, le scénario ou le
  potager, elle n'a pas de croix là où la retirer la ferait sortir ; le survol dit où elle sert. Elle peut changer de
  place ou perdre une de ses fonctions.
- **Phase 6** : `Palettes::remplir()` et `bin/remplir-palettes.php` (simulation par défaut, `--ecrire` pour écrire),
  aux règles du porteur ; seules les palettes vides sont remplies, relancer ne change rien. Simulé en production
  (lecture seule) le 2026-10-07 : 16 espèces pour P1, 26 pour Syntrop, 18 pour le Verger, 60 en tout ; restent hors
  palette Nashi, Amandier, fruits rouges, menthe, mais, passiflore, troene, symphytum officinale (Verger), fraise,
  chevrefeuille arbustif, maïs doux, vesce (Syntrop).
- **Au déploiement** : `mettre-a-jour`, puis `ssh jubile 'cd www/syntropia && php bin/remplir-palettes.php'` (relire),
  puis la même commande avec `--ecrire` ; le livret « L'atelier » (palette, plan, bordures, scénario, potager).

