# Le saut du chat — plan

> **État au 2026-09-21 — Phases 1 (le journal des perturbations), 2 (le constat et le diagnostic), 2 bis (la
> préparation et la campagne de plantation), 2 ter (les adventices) et 5 (la conception qui prépare le saut du chat) faites, pas encore déployées** ; les Phases 3
> (le stade du terrain, **premier incrément fait le 2026-09-21** : deux stades, par parcelle) et 4 (l'itinéraire technique) **attendent des décisions du porteur et de nouveaux versements** — voir § « Phase 5 — faite … ». Chantier demandé par le porteur le 2026-09-20,
> qui le tient pour **la clé de voûte du système** : « le saut du chat est un objectif intermédiaire
> essentiel pour les porteurs de projet ». Nées d'une étude (deux documents d'IA, `~/Documents/la_perturbation/`)
> confrontée au code et au corpus, les spécifications ci-dessous sont **tranchées pour ce qui est établi,
> ouvertes pour le reste** : la formation Joala se poursuit et ses versements (`~/projets/input/`) en
> fermeront d'autres. **Le chantier avance par incréments** — voir § Manière de procéder.

## Pourquoi

Le saut du chat est **l'effet de la perturbation** : quand on taille la biomasse, le sol est nourri et
l'information « il faut que ça pousse » gagne les plantes alentour ; il en résulte une pousse
extraordinaire, conjointe du sol et de l'aérien (`connaissances/livre-bienvenue-en-syntropie/
ch11-…md`, note de corpus ; livret SAF, § La perturbation — le mécanisme hormonal y est dit « selon la
formation », et l'atelier garde cette réserve). C'est un **effet**, pas un objectif indépendant : il se
déclenche par la **taille**, non par la seule densité, et se mesure **par comparaison à un témoin**.

Aujourd'hui l'atelier le nomme sans l'outiller :

- [suivi-perturbation.php](public/suivi-perturbation.php) : trois champs libres (quand, quoi, comment),
  **une seule saisie par participant, écrasée à chaque envoi** — impossible de garder l'historique de
  coupes qui se répètent (la consoude se coupe jusqu'à six fois par an).
- [suivi-saut-du-chat.php](public/suivi-saut-du-chat.php) : un oui/non et un champ libre ; sur « Pas encore »
  un texte qui attribue le déclenchement à la densité — **ce que le corpus ne dit pas**.
- [conception-calendrier.php](public/conception-calendrier.php) : quatre zones de texte, sans lien avec
  le scénario.
- Le schéma contient déjà de quoi faire mieux, **sans qu'aucune page l'écrive** : la table `intervention`
  (stade phénologique, conditions d'eau, pluies à venir, biomasse déposée, outil, résultat constaté),
  `parcelle.date_plantation`, `releve_sol.temoin`.

## Décisions du porteur, 2026-09-20

1. **L'eau à la perturbation : on ne s'interdit pas de couper, on coupe au bon moment et on arrose
   si la pluie ne vient pas.** Ce n'est pas « pas d'eau, on ne coupe pas ». **Le moment est dicté par
   l'état végétatif des végétaux de biomasse.** (Le corpus dit la même chose sous deux formes : Théry,
   « jamais perturber sans apport d'eau en climat sec » ; Faraj, « s'il n'y a pas d'eau, on ne coupe pas ».
   La lecture du porteur les concilie : l'apport d'eau, pluie ou arrosage, fait partie du geste.)
   **Conséquence de conception : l'atelier n'interdit jamais, il dit ce que les sources disent et
   pourquoi**, et le besoin d'arroser au moment de la perturbation se **prévoit** (ressource en eau
   du site, fréquence d'arrosage).
2. **La fréquence de coupe n'est pas fixée d'avance.** La première perturbation, en **juin**, est
   évidente ; ensuite il faut **avoir de quoi couper** — la fréquence suit le développement des végétaux
   de biomasse — et **arroser si pas de pluie**. L'observation du terrain guide ; la formation Joala
   précisera. L'atelier aide à observer (temps écoulé depuis la dernière coupe, état de la repousse),
   il ne décrète pas d'intervalle.
3. **« Que planter » a déjà des réponses**, à consolider et à préciser **selon l'itinéraire technique
   choisi** (tortues, pionniers, autres ?).
4. **Ce point rejoint le 3** : le rattrapage (« planter plus, quoi selon la période ») dépend de
   l'itinéraire — à définir et consolider.
5. **Le stade du terrain se demande** : il dit d'où le porteur part, ce qu'il faut réaliser en premier
   et comment.
6. **La préparation est primordiale, avec la plantation dense dans une même période** (ajout du porteur, le
   2026-09-20, après la Phase 2). La « même période » n'est pas un jour : elle **s'étale sur trois saisons —
   fin d'automne, hiver, printemps — selon les besoins des végétaux**. Le corpus dit la même chose : la campagne
   d'implantation s'étale sur environ six mois, contrainte par des fenêtres physiologiques qui ne coïncident pas
   (racines nues à partir de mi-novembre, boutures ligneuses au repos végétatif, placentaires au printemps selon la
   température du sol — 6 °C pour le maïs, 5 °C pour le tournesol) ; **ce que les deux écoles ont en commun, c'est un
   point zéro unique — aucune espèce structurante ajoutée après coup — non une date unique** (fiche transversale des
   deux écoles, note de corpus du 2026-08-27 ; ch. 11, « tout est planté dans la même saison »). Ce qu'on paie à
   planter après : dans la formation, des plants ajoutés l'hiver suivant ont un très beau départ, « mais s'ils avaient
   été plantés en même temps que le reste, leur occupation du sol aurait été bien plus importante » (Joala, 4-1).
7. **Les adventices** (ajout du porteur, le 2026-09-20). La préparation du sol est importante pour les tortues :
   **les semis se font sur un sol exempt d'adventices**. Pour la plantation de **pionniers** (mettre en place un
   système de succession), **la concurrence des adventices est un frein au saut du chat**. Le corpus va dans le même
   sens : Lopez pose comme règle de semer dans un sol exempt d'adventices ; la régénération naturelle assistée prévoit
   l'élimination préalable des adventices et des faux-semis (un à quatre passages) pour épuiser la banque de graines ;
   Théry (ch. 3) note que les racines de graminées concurrencent fortement les arbres, d'où l'on arrache les graminées
   et l'on coupe le reste au pied. **Inscrit** dans la condition « Le sol est préparé » et dans l'aide de la page
   « Préparation de la parcelle ». **Pas encore mesuré** : rien n'enregistre aujourd'hui si les adventices concurrencent
   — voir Phase 2 ter, où le porteur a ensuite précisé la règle : les adventices qui reviennent sont le signe d'une
   plantation pas assez dense.
8. **Potager et maraîchage** (ajout du porteur, le 2026-09-20). Pour le **calendrier de plantation**, le **plan de
   culture de Lopez** peut aider (`connaissances/maraichage-naturel/lopez-plan-de-culture.md` : maraîcher en
   Haute-Vienne, la première source du dépôt à la bonne latitude — ses dates se transposent). Pour **l'association sur
   une même planche**, on s'appuie sur le nœud `cultures-associees` de la carte des compétences (Weinrich : des ordres de
   conduite, non des preuves ; contexte Jura souabe et Hesse). **Deux constats de dépôt** : (a) les tables
   d'associations (`associations-potager|verger|succession.csv`, 77 lignes en tout) n'étaient que dans le projet `syntropie` ;
   **le porteur a décidé de les importer** et de proposer le placement des plantes de production selon les associations
   (ou leur dissociation) — **fait pour l'import**, la suite est dans `chantier-associations.md` ; (b) une parcelle de type « potager » peut se créer,
   mais **aucune page ne compose sa succession** (`succession_ligne` est au schéma, jamais écrite) : elle reçoit
   aujourd'hui le même scénario qu'une parcelle pérenne. C'est un chantier à part entière — voir § Le potager.

## Ce qui existe et se réutilise

| Existant | Usage dans ce chantier |
|---|---|
| Table `intervention` (`sql/01-syntropia.sql`) — déjà exportée et importée (`Export`, `Import`) | Le journal des perturbations. Aucune colonne à ajouter pour la Phase 1. |
| `Scenario::FONCTIONS` — `perturbation`, `biomasse`, `nourrice` ; `scenario_ligne.conduite_taille` | Quelles plantes sont à perturber ; le geste (basitone, cépée, trogne). |
| `BesoinEau`, `site.ressource_eau`, `releve_sol` — l'onglet eau de `conception-materiel-et-eau.php` | Prévoir l'arrosage de perturbation ; la ressource change le conseil. |
| `releve_sol.temoin`, glossaire « Témoin » | Le témoin de comparaison du saut du chat. |
| `Heritage` (parcelle → site → défaut), `Conduite` | Modèle pour un réglage qui peut différer d'une parcelle à l'autre (stade du terrain, itinéraire). |
| `parcelle.date_plantation`, `parcelle.statut` | Le point zéro : situer le projet dans son film (Placenta 1 = 90 jours…). |
| Glossaire (`# Saut du chat`, `# Perturbation`, `# Témoin`) | À préciser, pas à réécrire de zéro. |

## Ce que la confrontation a écarté ou corrigé

Les deux documents d'origine sont des réponses d'IA à partir du web, non sourcées de façon vérifiable.
Ce qui n'est **pas** repris tel quel :

- **Règle L3 « eau critique ⇒ tailler en fin d'hiver / début d'automne ».** Contredite par le corpus
  (l'automne est en réserve pour la biomasse ligneuse, `calendrier-et-taille-hivernale.md`) et par la
  décision 1 : la réponse à un manque d'eau est l'arrosage, pas un autre calendrier.
- **La fenêtre « mai – mi-juin » comme seule fenêtre.** Joala : mars à mi-juin en vert, taille d'hiver
  avant le débourrement et hors gel, réserve de mi-juin à mi-novembre sur le ligneux — **fenêtres à lire
  avec l'état réel des bourgeons**, non des dates.
- **« La plante taillée doit être en strate supérieure ou égale à la cible ».** Absente du corpus. Ce qui
  s'y trouve : les plantes alliées sont plantées pour être perturbées, on n'attend pas de biomasse d'un
  arbre de production principale (ch. 12), et il y a un **seuil de biomasse** — le framboisier est écarté
  des bordures vivantes faute de masse suffisante (ch. 15).
- **Les plantes indicatrices du stade du terrain.** Le document classe ronces et bouleaux en colonisation ;
  Joala met le bouleau en accumulation et la ronce à la transition accumulation → abondance. **On suit Joala.**
- **Le moteur de recommandation à quatre variables binaires.** Il écraserait les quatre valeurs de
  `ressource_eau`, ignorerait les tests de sol, et « interdit » là où l'atelier propose. Les règles utiles
  sont reprises comme **repères dits à l'écran, avec leur source**.
- **« Densité extrême ⇒ saut du chat ».** Le texte actuel de `suivi-saut-du-chat.php` va dans ce sens ;
  il est corrigé en Phase 2. **Décision du porteur (2026-09-20)** : le conseil « planter davantage, selon la
  période » reste, dans sa formulation d'origine, **sans la cause** (la formation y voit un secours — doubler les
  reprises, 4-8 : « pas de back-up suffisants » —, non un déclencheur).
- **La conception « lignes + inter-rang » (Livrable 2, option B).** Hors périmètre : le plan de parcelle
  est celui des planches. À rouvrir séparément si l'itinéraire « lignes » est retenu (Phase 4).

## Manière de procéder

**Itérative et incrémentale**, à la demande du porteur : chaque phase tient debout avec le savoir
d'aujourd'hui et se déploie seule ; une règle sans réponse sourcée **ne se code pas**, elle se range en
§ Questions ouvertes. À chaque versement de la formation : comparer les empreintes de `~/projets/input/` à
`connaissances/formation-joala/inventaire.json`, ne lire que le nouveau, rattacher au corpus par une fiche
datée, puis voir quelles questions ouvertes il ferme et en tirer l'incrément suivant.

Règles du dépôt qui s'appliquent à chaque phase : gabarit « le bureau », **aucun défilement** (une liste
se pagine), un repère est dit à l'écran avec son pourquoi, le lecteur ne peut rien écrire (UI absente **et**
POST refusé), le livret « L'atelier » est mis à jour **dans la même vague** et ne décrit que le déployé,
tout changement de `sql/01-syntropia.sql` **monte `Version::SCHEMA`** dans le même commit puis on rejoue
`python3 bin/dev.py verifier-export`. Vérification sur ce poste (`bin/dev.py` + Chrome headless/CDP) de
1280×720 à 320×568.

## Phase 1 — Le journal des perturbations

*Ne dépend d'aucune question ouverte. Aucun changement de schéma attendu.*

`suivi-perturbation.php` cesse d'être trois champs écrasés : c'est le **journal d'une parcelle**
(`?parcelle=ID`, sélecteur dans le panneau gauche comme `conception-scenario.php`), une ligne par
intervention dans `intervention`. Saisie dans le panneau Données, liste paginée dans le bureau.

**Ce qu'on note** (colonnes existantes) : date ; type (coupe en vert, taille d'hiver, fauche, recépage,
« affaiblissement » d'une couverture — voir plus bas) ; zone et végétaux touchés (à choisir parmi les lignes
du scénario portant `perturbation`/`biomasse`, ou libre) ; **stade végétatif** (repères du corpus : pleine
croissance, avant floraison, fleurs fanées et butineurs qui s'en désintéressent — critère de Théry et de Faraj —,
repos d'hiver avant débourrement, après un gel ou un dégât) ; **l'eau** (`conditions_eau` : pluie avant, pluie
après, arrosé, sec sans arrosage — un vocabulaire fermé côté PHP, dans la colonne texte existante) et
`pluies_a_venir` ; **biomasse déposée au sol** (ou exportée — un repère : « ne jamais exporter », ch. 12 et 2-4) ;
outil ; durée, personnes ; résultat constaté ; notes.

**Ce que la page dit, sans jamais bloquer** :
- au choix « sec sans arrosage » : « la taille est déjà un stress ; sans eau, une grosse partie du bénéfice
  se perd (ch. 12) — prévoyez de quoi arroser, ou attendez le moment de l'eau » — décision 1 ;
- **temps écoulé depuis la dernière coupe** de chaque zone, **sans seuil** (décision 2), avec, dans le panneau
  Informations, les **exemples** de la formation donnés comme exemples et non comme fréquences : consoude
  jusqu'à six coupes par an, mélisse trois ou quatre, nouvelle taille possible sous trois semaines dans un
  potager en transition (`calendrier-et-taille-hivernale.md`, `tortues-et-succession.md`) ;
- **couper ≠ affaiblir** : sur une couverture spontanée qu'on veut planter, la taille relance la repousse
  (4-3) ; le type « affaiblissement » (coucher sous paille) s'enregistre à part et **ne compte pas** comme
  perturbation pour le diagnostic de la Phase 2.

**Anciennes réponses** `perturbation_quand/quoi/comment` : jamais supprimées ; affichées comme « votre note
précédente » tant que le journal est vide, avec un geste pour en faire une intervention.

**Vérifier** : plusieurs interventions par parcelle, pagination, lecteur (POST forgé refusé), `verifier-export`
avec des lignes `intervention` (jamais exercées jusqu'ici), aucun débordement.

### Phase 1 — faite (2026-09-20)

`src/Perturbation.php` (le vocabulaire fermé et ce qu'on en lit — pur), `src/Web/Interventions.php` (lire,
ajouter, modifier, retirer), `public/suivi-perturbation.php` réécrite. **Aucun changement de schéma**
(`Version::SCHEMA` reste à 2). Livret, glossaire (« Perturbation ») et garde-fou complétés.

Ce qui s'écarte du plan, ou le précise :
- **Modifier prend un verrou de version** (hash de la ligne, comme `Scenario::version()`) : une intervention
  changée entre-temps refuse l'écrasement. Le plan n'en parlait pas ; « compléter plus tard » (le résultat
  constaté) suppose de pouvoir rouvrir une ligne, et deux membres peuvent le faire.
- **`saisi_par` reçoit le compte**, comme le dit le schéma. `ReleveSol` y écrivait l'identifiant du
  *participant* : **corrigé le 2026-09-20** (vérifié : compte 124, participant 127). Les relevés déjà écrits
  avant cette date gardent l'ancienne valeur ; aucun n'existe en local, et `saisi_par` n'est jamais exporté.
- **Les végétaux se lient par leur nom** (`intervention.especes`, séparés par « · », comparaison sans casse) :
  la colonne est un texte, pas une clé. Le temps écoulé n'est montré que pour les lignes du scénario portant
  `biomasse` ou `perturbation` ; un végétal saisi à la main est noté, sans compteur.
- **Vocabulaire posé** (cinq gestes, cinq états végétatifs, cinq situations d'eau, deux réponses sur les
  pluies, trois devenirs de la biomasse) : il se complète au fil de la formation, **les clés ne changent
  jamais** (un export en porte la valeur brute).
- **L'ancienne note** (`perturbation_quand/quoi/comment`) s'affiche tant que le journal de la parcelle est
  vide ; « En faire une intervention » préremplit le panneau (quand → motif, quoi et comment → notes) et ne
  supprime rien.
- **« Toute la zone d'un coup »** n'est pas un champ : la Phase 2 devra dire comment le lire (`zone` est un
  texte libre). Le journal ne fait que rappeler la règle.
- `bin/verifier-export-import.php` compte maintenant les interventions dans son récapitulatif.

Vérifié en local (`bin/dev.py` + Chrome headless/CDP, compte et projet de test supprimés ensuite) :
saisie complète par le vrai formulaire ; refus du serveur (date future ou invalide, geste inconnu ou absent,
durée, personnes, quantité sans unité, zone et notes trop longues, valeurs hors vocabulaire en POST brut,
jeton faux 403, action inconnue 400) ; modification, **conflit de version**, retrait avec confirmation refusée
puis acceptée, retrait d'une ligne déjà partie ; **cloisonnement** (modifier ou retirer l'identifiant d'une
autre parcelle : refusé, la ligne intacte) ; **un affaiblissement plus récent ne remet pas le compteur à
zéro** ; lecteur (ni panneau ni lien d'édition, POST forgé 403) et anonyme (renvoyé à la connexion) ; reprise
de l'ancienne note ; aller-retour `verifier-export` identique avec 26 interventions ; aucun débordement de
1280×720 à 320×568 (vingt-quatre lignes paginées). **Deux défauts trouvés en mesurant et corrigés** : la
police du tableau était en `rem`, donc le réglage de la zone n'avait aucun effet (la page paginait tout de
suite) ; et à 320 px la colonne « État des plantes » débordait derrière la tirette (masquée sous 40 rem).
Le chapitre du livret, allongé, se pagine sans débordement.

Non fait : les liens « Le suivi » des panneaux gauches de `conception-parcelles.php`, `conception-scenario.php`
et `conception-materiel-et-eau.php` restent grisés (rien n'a été demandé de ce côté) ; le journal s'atteint
par les flèches d'étape de Suivi.

## Phase 2 — Constater le saut du chat, et le diagnostiquer

*Dépend de la Phase 1. Une décision de schéma à prendre au début.*

`suivi-saut-du-chat.php` : de « oui / pas encore » à **un constat daté, mesuré, comparé**.

- **Constat** : une plante cible (ou une zone), une **pousse mesurée** sur une durée (le corpus donne des ordres
  de grandeur — maïs jusqu'à 30 cm en 24 h, tournesols +1,50 m, pérennes jusqu'à deux fois plus vite — comme
  repères, jamais comme seuils), **le témoin** à côté (même sol, conduit classiquement), et **la perturbation
  qui précède** (lien vers le journal). **À trancher en ouvrant la phase** : où stocker (une nouvelle petite
  table — donc `Version::SCHEMA` à 3, `Export`/`Import` à compléter — ou des lignes `intervention` de type
  observation, qui n'ont pas de colonne pour une mesure). Le plus propre est probablement la table.
- **Point zéro** : `parcelle.date_plantation` demandée (là où la plantation se consigne — à confirmer en
  codant : `realisation-consignation.php` ou le panneau Plan). Elle situe le projet dans son film et permet de
  dire « dès les premiers mois, dès la première année » (ch. 15, ch. 16) sans en faire une échéance.
- **« Pas encore » → un diagnostic, pas un conseil générique.** Chaque condition se lit dans ce que l'atelier
  sait déjà, et s'affiche **en place / à préciser / rien d'enregistré**, jamais « échec » :
  1. une perturbation enregistrée (Phase 1, type perturbation) ;
  2. au bon moment — le stade végétatif noté ;
  3. l'eau : pluie ou arrosage autour de la coupe ;
  4. la biomasse laissée au sol ;
  5. **toute la zone coupée d'un coup** (règle centrale, ch. 12 ; consigne de 3-7) ;
  6. **de quoi couper** : des alliées en quantité suffisante — plantes `perturbation`/`biomasse` du scénario
     (seuil de biomasse, ch. 15) ;
  7. au démarrage, les **annuelles placentaires** (maïs, tournesols, féveroles — ch. 14, 15).
- **Le texte du rattrapage est corrigé** : il ne dit plus que la densité déclenche le saut du chat. **Le porteur a
  tranché (2026-09-20) : on garde sa formulation d'origine, sans la cause** — « planter davantage peut aider, selon
  la période ». Le « quoi » détaillé attend la Phase 4.
- Glossaire : « Saut du chat » précisé (effet de la taille, pas de la seule densité ; se mesure par un témoin).

### Phase 2 — faite (2026-09-20)

`src/SautDuChat.php` (vocabulaire, comparaison au témoin, conditions — pur), `src/Web/Constats.php`,
`Parcelles::enregistrerDatePlantation()`, `public/suivi-saut-du-chat.php` réécrite, et **la table `constat`
(`Version::SCHEMA` passe à 3)**. Export, import, page d'export et d'import, script `verifier-export` : la table est
partout où `intervention` l'est. Livret, glossaire (« Saut du chat »), garde-fou complétés.

**Décision du porteur, 2026-09-20 (question posée avant d'écrire) : le conseil « Pas encore » garde sa
formulation d'origine, sans la cause** — « Rien d'anormal à attendre — le saut du chat vient avec le temps. En
attendant, planter davantage peut aider, selon la période. » L'affirmation que la densité déclenche la bascule
est retirée ; le « quoi planter » reste pour la Phase 4.

Ce qui s'écarte du plan, ou le précise :
- **Une table, pas des lignes d'`intervention`** : une intervention n'a pas de colonne pour une pousse en
  centimètres. `constat` : date, visible (oui / pas encore), plante, pousse (cm) sur N jours, témoin (description et
  pousse), notes. **La perturbation qui précède n'est pas une clé étrangère** : elle se lit dans le journal par sa
  date, ce qui évite une référence entre deux tables filles dans l'export.
- **Une pousse sans durée est refusée, un témoin sans pousse aussi** (« sans durée, une pousse ne dit rien ;
  sans pousse, un témoin ne compare rien ») ; rien d'autre n'est exigé. Le rapport à la pousse du témoin s'affiche
  quand les deux sont connus. Les ordres de grandeur de l'entretien de Théry restent des repères dits à l'écran.
- **La date de plantation se saisit ici**, dans l'onglet « La plantation » du panneau Données, et non sur la
  consignation de la Réalisation (site entier, en réponses) : c'est là qu'elle sert. Elle s'efface en la vidant.
- **Sept conditions, lues dans les données existantes**, chacune « en place », « à regarder » ou « rien de noté »
  — jamais « échec » : une perturbation notée, de quoi couper (plantes de biomasse au scénario), l'état des plantes,
  l'eau, la biomasse au sol, **toutes les plantes de biomasse coupées ensemble** (à trois jours près de la dernière
  coupe : c'est ainsi que « toute la zone d'un coup » se lit, faute d'un champ), des plantes de démarrage (celles qui
  ne restent pas au-delà du Placenta 3). La **dernière perturbation** du journal sert de référence ; un
  affaiblissement n'en est pas une. « Au bon moment » est devenu « l'état des plantes est noté » : l'atelier ne sait
  pas juger le moment, il sait s'il a été noté.
- Un ancien fichier (schéma 2, sans constats) **se charge toujours** : `SCHEMA_IMPORTABLE_DEPUIS` reste à 1 (testé).
- `verifier-export-import.php` : le récapitulatif compte les constats, et le « projet riche » en porte un.

Vérifié en local (`bin/dev.py` + Chrome headless/CDP, compte et projet de test supprimés ensuite) : constat mesuré
(× 2,5 affiché), bascule de la vue par défaut (conditions tant que « pas encore », constats dès qu'il est
« oui »), refus (pousse sans durée, témoin sans pousse, date future, valeurs hors bornes, textes trop longs,
réponse absente ou forgée, jeton faux 403, action inconnue 400), modification, **conflit de version**, retrait
avec confirmation, **cloisonnement** entre parcelles, date de plantation (posée, future, trop ancienne, invalide en
POST brut, effacée, sans effet sur une autre parcelle), reprise de l'ancienne note, lecteur et anonyme, conditions
sur un journal réaliste (eau sèche, biomasse sortie, plante oubliée, végétaux non notés), `verifier-export`
identique sur tous les projets locaux, aucun débordement de 1280×720 à 320×568. **Un défaut trouvé en mesurant** :
à 320 px, cinq colonnes ne tenaient pas ; « Saut du chat » est devenu « Visible », les marges se resserrent, et
sous 26 rem le nom de la plante passe sous la date.

**Au déploiement : `bin/deployer.py base` d'abord** (nouvelle table `constat` et ligne `schema` = 3), puis `fichiers`.

## Phase 2 bis — La préparation et la campagne de plantation — faite (2026-09-20)

Ajoutée après la Phase 2, d'après la décision 6 : la condition manquait à ses sept. **Décisions du porteur, le
2026-09-20 (deux questions posées avant d'écrire) : « la préparation » couvre la plantation dense dans la période
ET la préparation du sol ; la campagne se note par deux dates, début et fin.**

Ce qui est construit :
- **`parcelle.fin_plantation`** (colonne facultative — `Version::SCHEMA` passe à **4**, la fenêtre d'import ne se
  referme pas ; un fichier du schéma 3 se charge, testé). `date_plantation` en est le début, le point zéro.
  Migration additive `information_schema` + `PREPARE`, rejouée sans erreur.
- **`Parcelles::enregistrerPlantation()`** remplace `enregistrerDatePlantation()` : la fin ne se note pas sans début
  et ne le précède pas ; ni l'une ni l'autre dans le futur, ni avant 2000 ; vidées, les dates s'effacent.
  L'onglet « La plantation » du panneau Données a les deux dates, et dit les fenêtres du corpus (racines nues à
  partir de mi-novembre ; boutures ligneuses au repos végétatif ; placentaires au printemps selon la température du
  sol — 6 °C pour le maïs, 5 °C pour le tournesol).
- **Deux conditions de plus dans `SautDuChat::conditions()`**, en tête, donc **neuf** : « Le sol est préparé » et
  « La plantation est faite dans une même période ».
  - *Le sol est préparé* lit ce que la Réalisation a noté (`preparation_technique`, `preparation_realisee`) —
    celui de la personne, à défaut celui du porteur (`Reponses::preparationDuSite()`, même règle que les souhaits du
    scénario : un lecteur n'écrit rien lui-même). Ce que le corpus dit de la préparation s'affiche : préparer tôt mais
    toujours couvrir le sol en attendant la plantation ; ne pas brûler cette étape (Théry, ch. 14).
  - *La plantation dans une même période* : *en place* si la campagne tient dans environ six mois (190 jours, le
    repère du corpus — un repère, non un seuil de refus), *à regarder* au-delà (des plants ajoutés bien après le
    point zéro occupent moins le sol — Joala, 4-1), *rien de noté* sans les deux dates. **« Dense » n'est pas
    chiffré** : le corpus donne des principes, pas un seuil, et la condition le dit.
- **La page « Préparation de la parcelle »** (Réalisation) dit désormais à quoi sert ce qu'elle demande — se relire
  parmi ce qui rend le saut du chat possible — et ce que disent les sources : bâcher sur sol le plus humide possible, le
  lendemain matin d'une pluie ; bâcher aussi les chemins, une saison, avant d'y semer du trèfle blanc ; l'idéal, un
  couvert semé un ou deux ans avant (Faraj) ; plus de travail du sol après la plantation ; la préparation est la première
  chose que le climat empêche, prévoir un repli manuel.

**Ce qui n'est pas fait, et pourquoi** : le « ce qui compte » de la préparation du sol n'a pas été détaillé par le
porteur (il a choisi « plus la préparation du sol » sans le décrire) ; **l'atelier ne lit que ce qu'il enregistre déjà**
— la technique et qui l'a réalisée — et donne les repères du corpus. Détecter une structurante *ajoutée après coup*
suppose une date de plantation par végétal : la campagne à deux dates en est l'approximation, et une saison par végétal
resterait une piste si le besoin se fait sentir. La préparation n'a pas de date propre (« préparé tôt, couvert en
attendant » ne se lit pas).

Vérifié en local (`bin/dev.py` + Chrome headless/CDP, compte et projet de test supprimés ensuite) : campagne de
5 mois (en place), de 8 mois (à regarder), début seul et rien (rien de noté), fin avant début, fin sans début, fin
future, début trop ancien, date invalide en POST brut, dates effacées ; préparation non notée, bâchage manuel,
déchaumage et animaux, **repli sur le porteur pour un lecteur** ; `verifier-export` identique sur tous les projets
locaux (le « projet riche » porte les deux dates) ; fichier du schéma 3 sans `fin_plantation` accepté ; aucun
débordement de 1280×720 à 320×568 (neuf conditions paginées), panneau de saisie lisible à 320 px.

**Au déploiement : `bin/deployer.py base` d'abord** (la colonne `fin_plantation` et la ligne `schema` = 4), puis `fichiers`.

## Phase 2 ter — Les adventices — faite (2026-09-20)

**La règle du porteur, qui remplace ma première proposition** (mesurer la concurrence des herbes) : **les adventices
se traitent à la préparation de la parcelle par déchaumage — ce n'est pas que du désherbage —, avec les ressources dont
la personne dispose (bâche, motoculteur, animaux, ou autre). Si des adventices reviennent en cours de projet, c'est le
signe que la plantation n'est pas assez dense** : on désherbe à la main, **et** l'on densifie avec des plantes du système
d'accumulation, car cela veut dire qu'une partie des plantes de colonisation sont encore là.

Ce qui est construit :
- **`constat.adventices`** (`non` / `oui`, facultative — `Version::SCHEMA` passe à **5** ; un fichier du schéma 4 se
  charge, testé). Une question au constat : « des adventices reviennent-elles ? », avec, sur « oui », la lecture du
  porteur dite à l'écran.
- **Une dixième condition** : « Les adventices ne reviennent pas ». *Rien de noté* sans réponse ; *en place* si la
  dernière réponse notée est « non » ; **à regarder** si elle est « oui », avec l'**action** : désherber à la main et
  densifier avec des plantes d'accumulation — **le support de formation cite** saule, peuplier, bouleau, noisetier,
  aubépine, prunellier (Joala, 2-2) : des exemples, non une liste à suivre. La condition lit la **dernière réponse
  notée**, pas forcément celle du dernier constat.
- **Un geste « Désherbage à la main »** dans le journal des perturbations : il se note, et **ne compte pas comme une
  perturbation** (comme « affaiblir » : le temps écoulé depuis la dernière coupe ne bouge pas). Son conseil s'affiche au
  choix du geste.
- **La préparation dit ses moyens** : la Réalisation offrait manuellement, animaux, ou les deux ; elle offre maintenant
  aussi **une machine (motoculteur…)** et **autrement** (« les deux » devient « plusieurs moyens », même valeur). La page
  dit que le déchaumage n'est pas que du désherbage, et à quoi sert sa donnée. Elle a été **resserrée pour les écrans peu
  hauts** : ses deux nouveaux choix la faisaient déborder de 102 px à 320×568 (mesuré, corrigé).
- Le corpus dit la même chose sur le fond : Lopez (semer dans un sol exempt d'adventices), RNA (élimination préalable,
  faux-semis), Théry ch. 3 (les racines de graminées concurrencent les arbres).

**Ce qui ne se code pas** : la fréquence du désherbage. Les sources divergent — Théry : il disparaît presque une fois le
système en place ; Faraj : une tâche récurrente, critique les trois à quatre premières années ; Crawford : une tournée
toutes les trois à quatre semaines, puis un désherbage sélectif qui s'éteint. Question ouverte n° 12.

Vérifié en local (`bin/dev.py` + Chrome headless/CDP, compte et projet de test supprimés ensuite) : la condition dans ses
trois états, la dernière réponse qui prime, conseil au choix « oui », valeur forgée refusée, édition qui relit la
réponse, geste de désherbage sans effet sur le temps écoulé ni sur la condition « perturbation », les cinq moyens de
préparation lus par la condition, `verifier-export` identique, fichier du schéma 4 accepté, dix conditions paginées sans
débordement de 1280×720 à 320×568, page de préparation sans débordement.

**Au déploiement : `bin/deployer.py base` d'abord** (la colonne `adventices` et la ligne `schema` = 5), puis `fichiers`.

## Le potager — un chantier à part, à ouvrir

D'après la décision 8. Hors du saut du chat proprement dit (qui se joue sur les ligneux et les bordures vivantes), mais
il en est un cas : le potager « recrée sa biomasse chaque année par des annuelles » (schéma, `succession_ligne`). Ce que
demanderait un `chantier-plan-de-culture.md` : **composer la succession d'une parcelle « potager »** (aujourd'hui sans
page), un **calendrier de plantation** tiré du plan de culture de Lopez (semis, repiquage, dates transposables en
Haute-Vienne), et les **associations d'une même planche** (Weinrich). *Préalable levé le 2026-09-20* : le porteur a décidé d'**importer** les trois
tables `associations-*.csv` (fait, `contenu/associations/`) et de **proposer le placement des plantes de production selon
les associations** — la suite est dans `chantier-associations.md`, dont la Phase 5 (la succession : préculture et postculture)
se joint à ce chantier. **Ce chantier a maintenant son plan : `chantier-plan-de-culture.md`.**

## Phase 3 — Le stade du terrain : d'où l'on part

> **Faite le 2026-09-21, premier incrément** — **deux stades, par parcelle** (décision du porteur, 2026-09-21 : la **colonisation** et l'**accumulation**, comme le document d'origine ; l'abondance, système des milieux
> mûrs, reste l'horizon de la grille, non un point de départ). **`parcelle.stade`** (`Version::SCHEMA` passe à **9**, colonne facultative), `src/Stade.php` (pur : explication, ce qui s'observe, ce que le corpus permet d'affirmer),
> un choix « Le stade du terrain » dans le panneau Données de `conception-parcelles.php` (enregistré avec le plan, sous le même verrou de version ; vide = pas dit ; une valeur forgée est refusée), et **une ligne du panneau
> « Ce scénario prépare-t-il le saut du chat ? »** : le stade dit, avec ce que la formation en dit, ou une invitation à le dire. **Ce que le stade change aujourd'hui est mince, et l'écran le dit** : *ce qu'il faut réaliser en premier à
> chaque stade* n'est pas au corpus (question n° 4) — « pas encore de repère ». Plantes repères **selon Joala** (saule, peuplier, bouleau, noisetier, aubépine, prunellier en accumulation ; mousses, lichens en colonisation ; la ronce à la
> transition vers l'abondance). Ce qu'il reste : la règle « premier geste par stade » et son usage dans l'itinéraire (Phase 4), aux versements de la formation.
>
> **Second incrément, 2026-10-02 — trois stades, et ce qu'on fait à chacun.** Après le module 6 (`6-8` : densité de plantation et fréquence de taille réglées sur l'état du sol), le porteur
> a tranché : **sol pauvre = colonisation, intermédiaire = accumulation, vers le climax = abondance** — l'abondance devient un stade (`parcelle.stade`, **`Version::SCHEMA` passe à 19**, une valeur de
> plus à l'ENUM, migration qui l'élargit sans toucher aux lignes). `Stade::repere()` remplace « pas encore de repère » : planter très dense et tailler très souvent en colonisation (les trois premières
> années surtout) ; densité modérée et tailles qui s'espacent en accumulation ; strates stables et peu de taille en abondance. **Un sens, sans chiffre** — la source n'en donne pas. Affiché sous le
> choix du stade (`conception-parcelles.php`) et dans le panneau du scénario. Glossaire : « Abondance ».

*Texte d'origine du plan, gardé pour mémoire :*

*Décidé (5), les règles qui en découlent se consolident avec la formation.*

Une question de **Définition** (`definition-site.php`, onglet Observations, ou la parcelle) : **le stade
du terrain** — dans quel système le lieu se trouve aujourd'hui. La grille enseignée est celle de Joala
(`formation-joala/systemes-et-abondance.md`) : **colonisation**, **accumulation**, **abondance** ; le document
d'origine n'en gardait que deux. *Question de vocabulaire à trancher* : trois stades (Joala) ou deux ?
Par défaut, **trois**, puisque le corpus les donne.

- **À l'écran** : ce qui s'observe à chaque stade (plantes repères **selon Joala** — voir § écarts), pourquoi
  la question est posée (« elle dit d'où vous partez, ce qu'il y a à faire en premier, et comment »), et **ce
  qu'elle change** : d'abord, seulement ce que le corpus permet d'affirmer (le rôle du stade dans la succession,
  la protection des jeunes plants par les épineux d'accumulation, la préparation du sol, `RNA`). Ce qu'on ne
  peut pas encore dire s'affiche comme **« pas encore de repère »**, pas comme un silence.
- **Par parcelle ou pour le site ?** Un terrain a souvent plusieurs stades (une prairie usée, une lisière).
  Le modèle `Heritage` (parcelle → site) le permettrait, au prix de colonnes — donc d'un `Version::SCHEMA`.
  **À décider en ouvrant la phase** ; sinon un premier incrément possible : la saisie au niveau du site dans
  `reponse`, promue en colonne plus tard.
- Relie `definition-moyens.php` (moyens faibles ⇒ RNA), déjà construit.

## Phase 4 — L'itinéraire technique

*Décision (3) et (4). Le plus dépendant de la formation.*

Un **itinéraire technique** est la manière d'engager le lieu : **tortues** (Théry), **pionniers et RNA**
(colonisation, peu d'eau, peu de moyens), **lignes et consortiums** (Faraj, échelle agricole), **autres à
identifier** — la liste est ouverte. À distinguer de `parcelle.conduite` (syntropie ou jardin-forêt, la
discipline) et de `parcelle.forme` (zèbre, abeille…, la forme d'une tortue). Choix **par parcelle** (« essayer
sur UNE parcelle avant d'engager le reste »), avec héritage du site, comme `Conduite`.

- **Ce qu'il change** : *quoi planter* et *quand* (le tableau « itinéraire × période → quoi planter »), et
  *le calendrier de perturbation*. **Ce tableau se construit uniquement depuis le corpus**, chaque case avec sa
  source ; une case sans source reste vide (« pas encore de repère ») et se remplit aux versements suivants.
  C'est le lieu naturel du « rattrapage » de la Phase 2.
- **Une suggestion, jamais une imposition** : l'atelier propose l'itinéraire que le stade et les moyens
  rendent plausibles (règle du document d'origine, L2 : *non vérifiée dans le corpus lu* — à confirmer avant
  de la coder), et la personne choisit.
- Schéma : une colonne sur `site` et `parcelle` (`Version::SCHEMA` monte).

## Phase 5 — La conception qui prépare le saut du chat

*Réunit ce que les phases précédentes ont rendu possible.*

- **Un panneau sur `conception-scenario.php` : « ce scénario permet-il le saut du chat ? »** Indicateurs non
  bloquants tirés des données existantes — alliées à perturber présentes, part de biomasse (repère 70-80 %),
  au moins deux espèces par strate, annuelles placentaires au démarrage, eau (ressource + tests de sol). L'usage
  d'une donnée se montre (CLAUDE.md § L'esprit).
- **Le geste de taille par ligne** : `scenario_ligne.conduite_taille`, aujourd'hui texte libre, devient un
  choix — basitone (coupe à 3-4 cm de la souche), cépée (réserve dans les racines), trogne (le « contrat » d'une
  taille tous les 1 à 2 ans) — d'après `connaissances/transversales/fiche-conduites-de-taille-*.md` ;
  ce que `skills-map/nodes/deux-ecoles-et-conduites-de-taille.md` annonçait à l'étape suivi-perturbation.
- **Le calendrier de perturbation** (`conception-calendrier.php`) : sous chaque saison, **les fenêtres du
  corpus** comme repères, à lire avec l'état des bourgeons et le décalage local de la reprise ; **la première
  perturbation en juin**, puis « quand il y a de quoi couper » ; par plante, les exemples de fréquence de la
  formation. Le calendrier reste saisi par la personne ; il est **proposé**, non imposé. Pour un **potager**, le calendrier
  de plantation viendra du plan de culture de Lopez (décision 8), dans le chantier dédié.
- **L'eau de la perturbation** (`conception-materiel-et-eau.php`) : une ligne qui **prévoit** de quoi arroser
  aux dates de perturbation, selon `ressource_eau` (sans eau : le conseil RNA existe déjà) — **sans chiffre
  inventé** : le corpus ne donne pas de dose pour la perturbation. Et la **boucle** que 4-6 décrit (plus de
  densité et de perturbation ⇒ plus d'eau dans le système) dite dans le panneau Informations ; les repères
  chiffrés de 4-6 (1 % de matière organique = 20 L/m², 36 L par kg de biomasse) restent des **explications**
  d'une synthèse générée, pas des entrées de calcul.

### Phase 5 — faite (2026-09-21), et ce qui empêche de faire les Phases 3 et 4

**Pourquoi la Phase 5 seule.** Le porteur a demandé de commencer les Phases 3 à 5 « si j'ai tout ce qu'il faut ». La Phase 5 n'a besoin que de repères déjà au corpus ; les Phases 3 et 4 demandent ce qui manque encore
(§ Questions ouvertes n° 3, 4, 7) — elles ne se codent pas sans le porteur ou un nouveau versement.

- **Le panneau « Ce scénario prépare-t-il le saut du chat ? »** (panneau Informations de `conception-scenario.php`, lisible par un lecteur) : `SautDuChat::conception()` (pur, 13 contrôles) — des alliées à perturber, la **part de
  biomasse** (`PART_BIOMASSE_MIN` = 70 %, le bas du repère de Théry ch. 11 « 70 à 80 % » ; comptée en **végétaux du scénario**, non en plants — dit à l'écran), au moins deux espèces par strate occupée, des plantes de démarrage
  (seulement en syntropie : le jardin-forêt n'a pas de temps), un geste de taille noté pour les plantes de biomasse, de quoi arroser (`site.ressource_eau`). « En place », « à regarder » ou « rien de noté », jamais un refus.
- **Le geste de taille devient un choix** : `Scenario::GESTES` (basitone, cépée, trogne — la fiche des conduites de taille, Théry ch. 12), écrit en tête de `scenario_ligne.conduite_taille` (« cépée — tous les 3 ans ») ; **aucun changement
  de schéma**, un texte libre d'avant reste entier dans la précision (`Scenario::separerGeste()`). Glossaire : Basitone, Cépée, Trogne.
- **Le calendrier de perturbation** (`conception-calendrier.php`, saisie libre inchangée) : son panneau Informations rappelle les **quatre moments de la taille** (ch. 12), les **trois périodes où l'on ne taille pas les ligneux**, le
  noisetier avant janvier, « la première en juin, ensuite quand il y a de quoi couper », et **ce qui est à recaler** (fin juin et mi-novembre à fin février sont des traductions locales ; les trois périodes à éviter sont phénologiques).
  **Pas fait** : les exemples de fréquence **par plante** — la question n° 1 n'est pas fermée, un repère par espèce ne se code pas.
- **L'eau de la perturbation** (`conception-materiel-et-eau.php`) : une ligne sous la ressource — on coupe avant ou après une pluie et l'on arrose si elle ne vient pas ; **aucun volume**, le corpus n'en donne pas. La **boucle** (densité et perturbation
  ⇒ biomasse ⇒ matière organique ⇒ sol plus éponge) est dite dans le panneau Informations, avec « 1 % de matière organique = 20 L/m² » présenté comme **un ordre de grandeur non recoupé, pas une entrée de calcul** ; le « 36 L par kg de
  biomasse » n'est **pas repris** : il ne recoupe pas la fiche ISI (`connaissances/cycles-de-l-eau/joala-hydrologie-regenerative.md`).

**Ce qui manque pour la Phase 3 (le stade du terrain)** : la grille (colonisation, accumulation, abondance) est au corpus, mais **ce qu'on fait en premier à chaque stade** (question n° 4) ne l'est pas : une question de Définition dont
rien ne dépendrait violerait la règle « une donnée demandée sert une étape plus loin ». À trancher avec le porteur : **trois stades ou deux** (n° 7) ; **par parcelle ou pour le site**.
**Ce qui manque pour la Phase 4 (l'itinéraire technique)** : la **liste** des itinéraires et **les critères de choix** (n° 3), le **tableau « itinéraire × période → quoi planter »** (n° 2 : construit uniquement depuis le corpus, case par
case) et la règle « l'atelier propose l'itinéraire que le stade et les moyens rendent plausibles », **non vérifiée dans le corpus lu**. Ce sont des versements de la formation, ou des décisions du porteur.

## Questions ouvertes — en attente de la formation

| # | Question | Ce qui est acquis | Ce qui manque |
|---|---|---|---|
| 1 | Fréquence de coupe par plante | Décision 2 : suit le développement, première coupe en juin ; exemples consoude 6/an, mélisse 3-4 | Repères par espèce et par itinéraire |
| 2 | Quoi planter, quand (rattrapage compris) | Succession de 4-8 (un site) ; annuelles placentaires (ch. 14-15) | Le tableau par itinéraire (Phase 4) |
| 3 | Quels itinéraires ? | Tortues, pionniers/RNA, lignes ; « autres ? » | La liste complète et les critères de choix |
| 4 | ~~Stade du terrain → premier geste~~ | **Tranché le 2026-10-02** : densité et fréquence de taille par stade (Joala 6-8, correspondance du porteur) | Un chiffre (densité, fréquence) — la source n'en donne pas |
| 5 | Végétation spontanée : alliée à perturber ou couverture à affaiblir ? | Accumulation (sureau, saule, noisetier) = supports ; 4-3 : ne pas tailler ce qu'on veut planter | Où passe la limite, par stade |
| 6 | Combien de biomasse suffit ? | Le seuil existe (framboisier écarté, ch. 15) ; le scénario dit 70-80 % | Un repère par itinéraire |
| 7 | ~~Trois stades ou deux ?~~ | **Deux le 2026-09-21, trois le 2026-10-02** (le porteur ajoute l'abondance), par parcelle | — |
| 8 | Mesure du saut du chat | Ordres de grandeur de l'entretien de Théry | Protocole simple à proposer (durée, témoin) |
| 9 | Ce qui compte dans la préparation du sol | Décision 6 : la préparation couvre le sol et la plantation dense dans la période ; repères du corpus affichés | Ce que le porteur juge déterminant, à lire dans ce qu'il note |
| 10 | À partir de quelle densité une plantation est-elle « dense » ? | Principes : « plante deux fois plus » ; 10 cm² de vide = un désert | Un repère chiffré, par itinéraire |
| 11 | Étalement acceptable de la campagne | Environ six mois, de fin d'automne au printemps (corpus) ; point zéro unique | Le repère exact selon les végétaux et le climat |
| 12 | Fréquence du désherbage | Sources divergentes : Théry (disparaît presque), Faraj (critique 3-4 ans), Crawford (tournée toutes les 3-4 semaines, puis sélectif) | Un repère par itinéraire |
| 13 | Associations sur une même planche | Importées le 2026-09-20 (`contenu/associations/`) ; lecture par `src/Associations.php` | Le modèle de placement — voir `chantier-associations.md` |
| 14 | Plan de culture d'un potager | Lopez (Haute-Vienne), fiche au corpus ; `succession_ligne` au schéma | Un chantier dédié ; aucune page ne compose la succession |

**Mise à jour du 2026-09-20 (versement du module 5 — `connaissances/formation-joala/potager-syntropique.md`)** — sans fermer de question, elle en documente trois : **la fréquence de coupe (n° 1)**,
en potager : « jusqu'à cinq tailles majeures dans la saison » (un site, une saison) ; **la mesure du saut du chat (n° 8)** : tournesols « +70 cm en 8 jours » après une taille, maïs « +30 à +50 cm en une
semaine », « +5 à 10 cm en trois jours » pour les autres plantes — des repères de plus, à côté de ceux de Théry, non des seuils ; **quoi planter et quand (n° 2)** : les successions d'été du potager, voir
`chantier-plan-de-culture.md`. **Deux points nouveaux** : (a) le module définit le saut du chat comme *le moment où le sol bascule d'un support inerte vers une dynamique de fertilité auto-entretenue* (et
parle d'un « saut de système »), quand le glossaire de l'atelier suit la formulation de Théry (*l'effet de la perturbation*) — les deux ne se contredisent pas ; **la définition du glossaire est au porteur** ;
(b) **le journal et le diagnostic ne voient pas les cultures d'un potager** (ils lisent `scenario_ligne`) — voir `chantier-plan-de-culture.md` § « Ce que le module 5 apporte », point 5.

**Mise à jour du 2026-10-02 (versement du module 6 — `connaissances/formation-joala/printemps-mini-foret-et-sol.md`)** — aucune question fermée,
cinq documentées. **N° 1 (fréquence de coupe)** : cinq tailles depuis la plantation d'hiver sur une mini-forêt de 42 m², la 4e fin juin ; plusieurs
coupes de l'artichaut avant avril ; et une règle d'ensemble — **perturber fort les trois premières années, puis de moins en moins** (« plus ça va,
moins on taille ») ; toujours pas de repère par espèce. **N° 4 (stade → premier geste)** : le plus proche d'une réponse — un tableau qui règle la
**densité** et la **fréquence de taille** sur l'**état du sol** (pauvre : plantation très dense, taille très fréquente ; intermédiaire : modérée, en
baisse ; vers le climax : stable, faible) ; mais en trois états de sol, non les deux stades de l'atelier : **la correspondance est au porteur**.
**N° 5 (végétation spontanée)** : alliée, à sa place, matière de la taille ; elle remplace les plantes introduites là où elle s'impose (le lierre
terrestre à la place de la menthe) ; pour **affaiblir** une plante, la couper à ras, plusieurs fois, sous une couvrante (consoude) — la limite par
stade n'est toujours pas donnée. **N° 10 (densité)** : topinambour à 15 cm la 1re année, éclairci à 10-12 cm entre tiges ; un plant tous les
40 cm ajusté ensuite ; « plus le sol est pauvre […] plus on plante » — pas de chiffre par itinéraire. **N° 12 (désherbage)** : l'herbe entre par
les chemins, pas par le centre dense ; sans gravité dès la 4e année — rejoint la règle des adventices (Phase 2 ter). **Précision du porteur** :
Joala est la structure d'Anaëlle Théry ; les deux formulations du saut du chat citées plus haut (Théry, Joala 5-1) sont donc d'une même autrice.

## Documentation, à chaque phase

- Livret « L'atelier » (`contenu/documentation-atelier.md`) : le chapitre concerné **dans la même vague**,
  seulement ce qui est en production ; `bin/verifier-documentation.py` complété.
- Glossaire : « Saut du chat » (Phase 2), « Perturbation » (Phase 1, la nuance couper/affaiblir), les stades
  (Phase 3).
- `skills-map/` : le nœud `deux-ecoles-et-conduites-de-taille` et `fondamentaux-syntropiques` renvoient à ce
  chantier ; le nœud `formation-joala` à chaque nouveau versement.
- `CLAUDE.md` : la ligne de ce chantier dans § Où sont les choses, et l'état dans § Point de reprise à chaque phase.
- Le livret « SAF » reste au porteur.
