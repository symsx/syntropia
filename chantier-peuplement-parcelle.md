# Chantier — peupler une parcelle : bordures, planches, services

*Proposé le 2026-09-23, à la demande du porteur. **Phases 1 à 6 faites le 2026-09-23**, commitées, pas déployées ; la Phase 7 (Matériel et eau) reste.*

## L'intention

Une parcelle est un **système** — une tortue. Sa conception suit un ordre, que le porteur a posé le
2026-09-23 :

1. **Les bordures.** Elles portent des plantes pérennes : la bordure **externe** bloque l'herbe et
   le vent, la bordure **interne** apporte de la biomasse. Chaque bordure est facultative ; c'est la
   personne qui les place, avec les outils du plan. Sur chaque bordure, **une seule ligne**, faite
   d'**une ou plusieurs espèces** qui alternent — le SAF syntropique privilégie la diversité — et qui
   remplissent la fonction de cette bordure. L'atelier **compte les plants de chaque espèce, bordure
   par bordure**.
2. **Les planches et les passe-pieds.** Déjà fait (`chantier-plan-de-parcelle.md`).
3. **Les productions sur les planches**, dans le scénario de plantation, avec les associations
   favorables quand c'est possible. Déjà fait pour un potager (`chantier-plan-de-culture.md`), **mais
   pas encore pour des pérennes sur les planches** : une tortue n'est pas toujours un potager
   classique (tortue zèbre, tortue abeille, tortue liane).
4. **Les plantes de service en lignes sur les planches**, une fois les productions posées : la
   biomasse de la planche, et les strates qui manquent autour des productions.

Pour les bordures (1) et les lignes de service (4), **l'atelier propose l'espèce, l'emplacement et le
nombre de plants à partir de son référentiel**. La personne accepte, corrige ou refuse.

## Décisions du porteur (2026-09-23)

- **Le potager ne porte pas les bordures.** `conception-potager.php` reste la page des planches de
  culture. Les bordures se conçoivent dans « Le plan » (`conception-parcelles.php`), qui les dessine
  déjà.
- **Des pérennes sur les planches d'une tortue potager.** Il faut pouvoir y placer des pérennes :
  tortue zèbre (des planches pérennes alternent avec des planches annuelles), tortue abeille
  (pérenne et annuel sur une même planche), tortue liane (des lianes sur une structure, avec des
  cultures en dessous). Source : `connaissances/livre-bienvenue-en-syntropie/ch15-plantations-perennes.md`,
  §§ 7 à 9.
- **Les espacements et les fonctions viennent du référentiel** : `espece.espacement_retenu_m`
  (l'espacement conseillé) et `espece.role` (bloque-herbe, vent, tuteur, biomasse…), qu'on les saisisse
  dans `especes.php` ou qu'on les importe par CSV. **L'atelier n'invente aucune valeur.** Si
  l'espacement manque, il le dit et ne compte pas.
- **Les lignes d'une planche** reprennent le modèle du dépôt de référence (`Plan.php`,
  `Biomasse.php`) : un nombre de lignes par planche (3 par défaut), une ligne centrale, **une espèce
  par ligne**.
- **Les règles de choix viennent des compétences de l'atelier** (`skills-map/`, `connaissances/`).
  Là où elles ne suffisent pas, le chantier avance **par incréments**, et la mise au point de
  l'atelier sert aussi à **affiner ces compétences** : une règle qui manque devient une question
  écrite dans le nœud de compétence, pas une règle inventée dans le code.
- **Bordures** : une seule ligne par bordure, une série d'une ou plusieurs espèces qui alternent.

## Décisions du porteur (2026-09-23, seconde série)

- **Le type de tortue est un réglage de la parcelle** : classique, zèbre, abeille, liane. Il se
  règle avec la conduite et le stade, dans le panneau Données de « Le plan », et il oriente les
  propositions (planches pérennes ou annuelles en zèbre ; bande pérenne en demi-planche en abeille ;
  pas de maïs ni de tournesol après les deux premières années en liane).
- **Espacements différents dans une même ligne** : la distance entre deux plants voisins est **la
  moyenne de leurs deux espacements**. Chalef (50 cm) puis consoude (30 cm) : (50 + 30) / 2 = 40 cm.
  Pour une série de trois espèces A, B, C qui se répète, les écarts sont (A+B)/2, (B+C)/2, (C+A)/2,
  et ainsi de suite. **Aux deux bouts de la ligne**, on laisse la moitié de l'espacement du plant
  d'extrémité, comme la référence (`ImplantationBordures`, « demi-espacement aux extrémités ») —
  **confirmé par le porteur**.
- **Une colonne `cycle` rejoint le référentiel** (`espece`, le modèle CSV, `especes.php`) : c'est une
  donnée importante de la succession syntropique. Liste fermée, **vocabulaire confirmé** :
  `annuelle`, `bisannuelle`, `vivace` (herbacée pérenne), `ligneuse`. Les bordures retiennent les
  vivaces et les ligneuses ; les lignes de biomasse, surtout les annuelles.
- **Les lignes (de bordure et de planche) vont dans une nouvelle table**, et non dans
  `parcelle.plan`.
- **Les bordures valent pour toutes les parcelles** — potager, verger, jardin-forêt —, toujours
  facultatives.
- **La première règle des services est validée** : compléter les strates vides de la planche, la
  biomasse sur la ligne centrale.

## Ce qui existe, et ce qui manque

| Étape | Existe | Manque |
|---|---|---|
| 1. Bordures | La géométrie des huit côtés, facultatifs (`parcelle.plan`) ; l'emplacement « bordure externe / interne » du scénario pérenne, d'un seul tenant ; une quantité estimée par `Materiel::quantite()` | Une ligne par côté avec sa série d'espèces ; le compte par espèce et par côté ; la proposition tirée du référentiel |
| 2. Planches | Fait | Les lignes à l'intérieur d'une planche (la référence en avait, non portées) |
| 3. Productions | Le potager, par planche, avec les associations de Weinrich | Des pérennes sur les planches (zèbre, abeille, liane) |
| 4. Services | Rien | Tout : les lignes, la proposition, le compte |

**Le référentiel du cadre (46 espèces, lu en local le 2026-09-23) ne permet encore aucun
compte** :

| Ce qu'il faut | Ce qu'il y a |
|---|---|
| Un espacement conseillé | Aucune espèce n'en a (`espacement_retenu_m`, `plants_par_ml` vides) |
| Le rôle `vent` (freiner le vent) | Aucune espèce |
| Le rôle `bloque_herbe` | 3 (consoude, artichaut ; la mélisse par la colonne `bloque_herbe`) |
| Le rôle `biomasse` | 23 |
| La strate | Environ la moitié |
| Annuelle ou pérenne | Aucune colonne (`cycle` décidée, Phase 1 bis) |

Les fonctions et les espacements arriveront par la saisie ou l'import (décision ci-dessus). Tant qu'ils
manquent, **la proposition reste vide et le dit**. Ce n'est pas un défaut du code.

**Mis à jour le 2026-09-23** (accord du porteur) : les espacements cotés du corpus (ch. 08, 11, 14) sont dans le cadre —
lavande, consoude, mélisse 0,40 m ; chalef, goji, menthe, maïs 0,50 m ; cassis 0,60 m ; sureau 1 m ; tournesol 0,20 m ;
courge 2 m — et cinq plantes de bord de carapace y entrent (hysope, romarin, lavandin, sauge — bloque-herbe, 0,40 m —
et mûrier sans épines, 0,25 m). Le rôle `vent` reste sans espèce : le corpus n'en nomme aucune.

## Ce que les sources disent déjà (à reprendre dans la compétence, Phase 1)

- **Bordure externe** (ch. 14 § 4) : d'abord une barrière **bloque-herbe**, ensuite une réserve de
  diversité. Elle « monte » le bord de la carapace et fait de l'ombre vite, **surtout au sud et à
  l'ouest** ; au nord et à l'est, elle peut être plus basse, sauf face au vent dominant. **Sur une
  face exposée, que des plantes émergentes, quelle que soit leur taille.** Sa hauteur se proportionne
  à ce qu'on produit à l'intérieur (3 à 3,50 m pour une tortue zèbre).
- **Bordure interne** (ch. 14, ch. 15 § 7.3) : la biomasse. Chalef tous les 50 cm, à 30 ou 40 cm du
  bord. Le cassis et le groseillier remplacent peu à peu le saule (même travail de taille, avec une
  récolte). Consoude avec l'artichaut, menthe et mélisse en complément.
- **Lignes d'une planche** (ch. 8, annexes ; Joala, potager syntropique) : la ligne centrale porte la
  biomasse (tournesol en poquets tous les 20 cm, ou maïs) ; le maïs va vers les bords (poquets tous
  les 50 cm) ; **une ligne, une espèce** ; **pas de tuteurs sur la ligne centrale**, qui reste celle
  de la perturbation.
- **Tortue zèbre** (ch. 15 § 7) : des planches pérennes (petits fruitiers, petits fruits, tisanes,
  mellifères, biomasse ligneuse) alternent avec des planches annuelles. La biomasse 3D annuelle
  (maïs, tournesol) doit réussir sur les lignes pérennes.
- **Tortue abeille** (ch. 15 § 8) : sur chaque planche, une bande pérenne et une bande annuelle ;
  maïs ou tournesol au centre la première année ; émergents plafonnés à 3,50 m.
- **Tortue liane** (ch. 15 § 9) : des lianes émergentes sur une structure ; plus de maïs ni de
  tournesol après les deux premières années.

**Ce que les sources ne disent pas encore** : une règle qui, à partir des strates des productions
d'une planche, désigne les espèces de service à placer autour. Elles donnent « au moins deux espèces
par strate » et la lumière selon la strate (le guide de `conception-scenario.php`), pas une règle de
choix. **Première règle** (Phase 6), **validée par le porteur le 2026-09-23** : **compléter les
strates vides de la planche**, avec la biomasse sur la ligne centrale. Elle s'affinera avec la
formation.

## Phases

### Phase 1 — La compétence « concevoir une tortue »

Un nœud `skills-map/nodes/conception-de-la-tortue.md` (skill `organisation-skills`), rangé sous
« Conception » : les règles de bordure, les lignes d'une planche, les trois tortues, chaque règle
avec sa source, et une section **« questions ouvertes »** — ce que le code aura besoin de savoir et
que le corpus ne dit pas. C'est la source des règles des Phases 3 et 6 ; elle grandit avec les
versements de la formation. **Aucun code.**

**Fait le 2026-09-23.** `skills-map/nodes/conception-de-la-tortue.md`, sous « Conception » dans `INDEX.md`. Deux
apports du corpus relevés en l'écrivant : (1) le livre décrit **une** bande de bordure d'environ 1 m portant deux ou
trois lignes — l'atelier en fait deux bordures, chacune une ligne (décision du porteur), la bordure externe répondant à
la ligne extérieure, l'interne à la ligne intérieure ; (2) **chaque planche a aussi ses bordures vivantes** (consoude sur
le bord extérieur, mélisse face au passe-pied, ch. 11) — à placer dans le modèle des lignes de la Phase 4 (question
ouverte n° 2 du nœud). Le ch. 14 donne des **espacements cotés** (lavande 40 cm, chalef 50 cm, goji tressé 20 cm,
cassis 60 cm, consoude et mélisse 40 cm…), repris dans le nœud ; **ils n'entrent dans le référentiel qu'avec l'accord du
porteur** (« indicatifs et non définitifs », dit la source).

### Phase 1 bis — Le cycle et le type de tortue

`espece.cycle` (liste fermée, `Especes::valider()`, cases dans `especes.php`, colonne du modèle CSV)
et `parcelle.tortue` (classique, zèbre, abeille, liane ; choix dans « Le plan », enregistré avec le
plan sous le même verrou de version). **Deux colonnes facultatives : `Version::SCHEMA` passe à 11**,
`verifier-export` rejoué. Le cycle des 46 espèces du cadre se renseigne dans `sql/02-especes-seed.sql`
**seulement là où la note ou la source le dit** ; les autres restent vides.

**Fait le 2026-09-23.** `sql/01-syntropia.sql` (les deux colonnes et leurs migrations), `Version::SCHEMA` = 11,
`src/Tortue.php` (pur : libellé et une phrase de Théry par type), `Parcelles::enregistrerPlan()` (clé `tortue`, même
convention que `stade`), le choix « Le type de tortue » dans le panneau Données de `conception-parcelles.php` ;
`Especes::CYCLES`, la colonne dans `COLONNES` et `valider()`, un champ « Cycle » dans `especes.php` (et le cycle sous le
nom dans la liste), une colonne `cycle` au modèle CSV. Le cadre : 23 ligneuses, 9 vivaces, 9 annuelles
(`sql/02-especes-seed.sql`, un `UPDATE … AND cycle IS NULL` commenté de ses sources) ; **cinq laissées vides** parce que
la réponse dépend du climat ou de la conduite — tomate, poivron, aubergine, pomme de terre, lavande. Vérifié en local
(compte, projet et espèces de test supprimés) : choix enregistré puis relu, vidé, valeur forgée refusée avec son message,
lecteur refusé (403) ; cycle saisi, relu, affiché, valeur forgée refusée ; import du modèle (cycles écrits) et d'un
fichier au cycle invalide (refusé, rien d'écrit) ; `verifier-export` identique (la fixture porte `tortue` et `cycle`) ;
`dev.py init` rejoué deux fois sans effet ; 1280×720, 412×800, 320×568 sans débordement. **Au déploiement :
`bin/deployer.py base` d'abord** (schémas 3 à 11 en attente en production), puis `fichiers`.

### Phase 2 — Les bordures peuplées

Sur « Le plan », pour chaque côté actif d'une bordure : **une ligne**, une série ordonnée d'une ou
plusieurs espèces (du référentiel, ou en texte libre) qui alternent, à leur espacement. Une classe
pure `Bordures` compte les plants de chaque espèce, côté par côté (port réduit
d'`ImplantationBordures` : ni portions ni décalage). Les plants se dessinent en points sur le plan.
Une espèce sans espacement est dite « sans espacement : pas de compte ». Entre deux plants voisins,
la moyenne de leurs espacements ; aux deux bouts, la moitié de l'espacement du plant d'extrémité
(décisions du porteur). Les lignes vont dans une nouvelle table (une ligne de plantation par
enregistrement : la parcelle, l'endroit — un côté de bordure ou une ligne de planche —, la série
ordonnée des espèces) ; `Version::SCHEMA` monte, export, import et `verifier-export` la couvrent.

**Fait le 2026-09-23.** Table `ligne_plantation` (**schéma 12**, table facultative) : un enregistrement par place de la
série (`emplacement` `externe:nord`…, `rang`, l'espèce désignée comme au référentiel par `nom_scientifique`/`variete` —
un nom, non un identifiant, pour qu'un export la retrouve ailleurs ; l'espacement se lit toujours dans `espece`, le
nombre de plants ne s'enregistre pas). `src/Bordures.php` (pur, 18 contrôles : géométrie des lignes au milieu de chaque
bande, compte avec la moyenne des voisins et le demi-espacement aux bouts, échantillon de 400 points pour le dessin,
`convient()` : l'espèce remplit-elle la fonction de la bordure — rôle, ou la case `bloque_herbe`) ;
`src/Web/LignesPlantation.php` (la série d'un côté réécrite d'un bloc sous verrou de version ; seules les espèces du
référentiel du projet, cadre ou surcharge). Sur « Le plan », une section « Les plantes des bordures » : un bloc par côté
(longueur, total, chaque espèce avec son nombre), ajouter une espèce au bout de la série (celles qui remplissent la
fonction en tête de liste), retirer une place, vider ; les plants dessinés en couleur sur le plan. **Les lignes se
comptent sur le plan enregistré** : dès qu'une dimension ou une bordure change à l'écran, les plants se masquent et la
saisie se suspend ; enregistrer le plan recharge la page, recomptée. Un côté devenu inactif garde sa ligne, sans compte.
Le scénario pérenne renvoie, sous « Emplacement », à ces lignes (décision (a)) ; **la reprise du compte dans « Matériel
et eau » reste à la Phase 7**. Vérifié en local (projets et comptes de test supprimés) : 30 plants sur 12 m au pas de
0,40 m (15 lavandes, 15 consoudes), 24 sur 11,2 m pour chalef et mélisse (pas de 0,45 m), recompte à 9,2 m (20),
espèce sans espacement (pas de compte, dit), retrait, ligne vidée, conflit de version, emplacement forgé, espèce d'un
autre projet, parcelle d'un autre projet, ajout sans choix, lecteur (rien à saisir, POST refusé), `verifier-export`
identique (la fixture porte trois places), 1280×720, 412×800 et 320×568. **Au déploiement : `bin/deployer.py base`
d'abord.**

### Phase 3 — Proposer les bordures

Un bouton « Proposer » par bordure, sur le même geste que le placement du potager (un aperçu, rien
n'est écrit avant l'accord). La proposition filtre le référentiel par la fonction de la bordure
(externe : `bloque_herbe`, `vent` ; interne : `biomasse`), applique les règles sourcées de la Phase 1
(des émergentes sur les faces sud et ouest exposées) et **propose plusieurs espèces**, pour la
diversité. Elle dit pourquoi chacune est retenue et ce qui manque (« aucune espèce du référentiel ne
porte la fonction *vent* »).

**Décisions du porteur (2026-09-23)** : proposer **toutes** les espèces qui remplissent la fonction de la bordure ; une
plante que jardineries et pépiniéristes destinent à la « bordure » peut être tenue pour bloque-herbe (la sauge, le thym
luisant — Thymus nitens, tapissant vigoureux, ajouté au cadre ; couvrir le sol et bloquer l'herbe y sont la même fonction —, la mélisse dont la case est confirmée).

**Précisions du porteur (2026-09-23, après la Phase 3)** : en bordure, **couvrir le sol et bloquer l'herbe sont la même
fonction** — une plante couvre-sol convient à la bordure externe (`Bordures::CADRES`) ; **une bordure n'est pas une
planche** — elle ne porte que des plantes de service, une bordure = une ligne ; les « bordures vivantes » d'une planche
du ch. 11 sont, dans l'atelier, des lignes de service de la planche (Phase 6).

**Fait le 2026-09-23.** `Bordures::proposer()` (pur) : toutes les espèces du référentiel qui conviennent (rôle, ou case
`bloque_herbe` pour l'externe), **sauf les annuelles et bisannuelles** — une bordure est pérenne, décision du porteur ;
un cycle non noté ne l'écarte pas —, chacune avec ses fonctions et, sur une face sud ou ouest de l'externe, la réserve
du ch. 14 si sa strate notée n'est pas émergente (signalée, non écartée). Dans chaque bloc de bordure, « Proposer les
espèces de cette bordure » ouvre la liste, cochée d'avance pour celles qui ont un espacement (les autres ne se
comptent pas), avec le nombre de plants que donneraient les cochées ; « Poser cette série » (ou « Remplacer la
ligne ») l'écrit sous le verrou de version, rien avant. La série passe à 30 places au plus (12 dans la référence).
Vérifié en local (projet et comptes supprimés) : externe sud, 9 espèces proposées, 7 cochées, 25 plants sur 10 m,
posées ; interne nord, 21 espèces sans maïs ni tournesol ; rien de coché refusé ; conflit de version ; lecteur (ni
lien ni proposition) ; 1280×720 et 320×568.

### Phase 4 — Les lignes des planches

Le nombre de lignes par planche (1 à 12, 3 par défaut) dans `parcelle.plan`, et la ligne centrale
repérée ; le plan dessine les lignes. Port du modèle de `Plan.php` de la référence.

**Fait le 2026-09-23.** `src/Planches.php` (pur) : la disposition des planches portée de `planchesLayout()` (le script
et le serveur doivent rester identiques), le nombre de lignes de chaque planche lu dans `parcelle.plan` (`lignes`,
numéro → nombre, 3 par défaut, de 1 à 12), la ligne centrale (celle du milieu, ou la plus proche, `centrale()`), et les
coordonnées de chaque ligne — ce dont la Phase 6 aura besoin. `Parcelles::enregistrerPlan()` reçoit `lignes` (absent :
on garde ceux du plan enregistré ; une planche qui disparaît perd son nombre), `Import::plan()` accepte et contrôle la
clé. Sur « Le plan », « Lignes par planche » : une case par planche (sa largeur à côté), les lignes dessinées en
pointillé, la centrale en trait plein. **Aucun changement de schéma** (le plan est du JSON). Vérifié en local (projet et
comptes supprimés) : 6 planches × 3 lignes par défaut, 5 lignes sur la planche 2 enregistrées et relues, gardées quand
on enregistre autre chose, 20 refusé avec son message, fichiers d'import aux lignes mal formées refusés,
`verifier-export` identique (la fixture porte des lignes), 1280×720 et 320×568, lecteur refusé. **Rappel** : les
« bordures vivantes » d'une planche (ch. 11) sont ici des lignes de service comme les autres (le porteur).

### Phase 5 — Des pérennes sur les planches

`conception-potager.php` accepte une pérenne sur une planche (sans calendrier de semis obligatoire),
et, pour la tortue abeille, sur une **demi-planche** (`succession_ligne.demi_planche`, déjà au schéma,
jamais écrite). Le type de tortue de la parcelle (Phase 1 bis) dit ce que la page propose : en zèbre,
chaque planche est marquée pérenne ou annuelle ; en abeille, chaque planche a sa bande pérenne.

**Décisions du porteur (2026-09-23)** : en zèbre, une alternance proposée par défaut ; on choisit la nature de la
première planche ou bande (à gauche — à l'ouest, ou au nord selon le sens des planches), puis elles alternent ; en
liane, l'atelier dessine le plan au sol, pas la structure — la liane est une compétence à développer (question
ouverte du nœud).

**Fait le 2026-09-23.** `Planches::natures()` (pur) : zèbre — la nature de chaque planche (première, puis alternance,
une planche changée l'emporte) ; abeille — deux bandes par planche, dans l'ordre de l'alternance ; autre tortue : rien.
`Planches::natureDuCycle()` : vivace ou ligneuse → pérenne, annuelle ou bisannuelle → annuelle, cycle non noté → rien.
Enregistré dans `parcelle.plan` (`natures` : `premiere`, et les planches changées), gardé quand l'envoi ne le porte pas,
contrôlé à l'import. Sur « Le plan », « Planches pérennes et annuelles » (visible en zèbre et en abeille), une liste
par planche en zèbre ; planches ou bandes pérennes teintées. Sur le potager, la nature s'affiche sur chaque planche ;
`PlanDeCulture::proposer()` ne met une plante pérenne que sur une planche pérenne (et l'annuelle sur l'annuelle) et, en
abeille, dans la bande de sa nature — écrite dans `succession_ligne.demi_planche` (au schéma, jamais écrite jusqu'ici),
qui se choisit aussi à la main (« La bande »). **Aucun changement de schéma.** Vérifié en local (projet supprimé) :
alternance, planche 4 changée et relue, proposition zèbre (cassis et menthe sur pérennes, courgette sur annuelle, radis
sans cycle n'importe où) acceptée, passage en abeille avec première bande annuelle, goji proposé dans la bande pérenne et
placé, première nature et bande forgées refusées, `verifier-export` identique (la fixture porte natures et bande),
1280×720 et 320×568.

### Phase 6 — Proposer les lignes de service

Pour chaque planche : lire les strates des productions placées, proposer une espèce **par ligne**
(la biomasse sur la ligne centrale, les strates vides ailleurs, aucun tuteur au centre), à partir du
référentiel (`role`, `strates_possibles`), avec le nombre de plants ou de poquets de la ligne. Même
geste qu'en Phase 3 : aperçu, raison, accord. Les règles viennent de la Phase 1 ; une règle absente
se dit (« pas encore de repère »).

**Décision du porteur (2026-09-23)** : les lignes d'une planche portent les plantes de service, **les productions se
plantent de part et d'autre** (Joala, module 5 : une tranchée centrale, les productions de part et d'autre ; précisions
dans la formation — seules les synthèses du module 5 sont lues, à la demande du porteur).

**Fait le 2026-09-23.** `src/LignesDeService.php` (pur) : la ligne centrale reçoit la biomasse (émergente d'abord) ; les
strates que les productions de la planche laissent vides se posent de la ligne la plus proche du centre vers les bords,
de la plus haute à la plus basse (la forme pyramidale, Théry ch. 8 § 5) ; une espèce par ligne (ch. 8 § 8) ; les espèces
du cycle qui convient à la ligne passent d'abord (planche ordinaire : annuelle ; zèbre : la nature de la planche ;
abeille : celle de la bande, la ligne médiane annuelle, ch. 15 § 8) — préférence, non exclusion. La strate d'une
production : celle saisie, à défaut `strates_possibles` au référentiel. `LignesPlantation::enregistrerPlanche()` —
`ligne_plantation`, emplacement `planche:N:K`, une place par ligne, toutes les lignes d'une planche réécrites sous une
seule version ; `etatPlanches()` compte les plants (longueur de la planche, espacement de l'espèce). Sur le potager :
chaque planche résume ses lignes (« 3* Maïs (14) ») ; « Les lignes » ouvre, dans le panneau Données, un choix par ligne
— la proposition si rien n'est posé (ou « Reprendre la proposition »), les candidates en tête, toutes les espèces
ensuite. **Aucun changement de schéma.** Limite : une parcelle **pérenne** (scénario) ne place pas ses productions
par planche — cette étape n'y est pas encore. Vérifié en local (projet supprimé) : courgette (moyenne) et tomate (haute
au référentiel) sur une planche de 5 lignes → maïs au centre (14 plants sur 7,2 m), consoude sur la ligne voisine (18),
les autres vides, enregistré et résumé ; conflit de version, espèce étrangère, ligne hors planche refusés ; lecteur
(résumé seul) ; 320×568 ; `verifier-export` identique (la fixture porte une ligne de planche).

### Phase 7 — Matériel et eau, livret

Les plants des bordures et des lignes de service entrent dans « Matériel et eau » (par espèce, par
origine). Le livret « L'atelier » est mis à jour au déploiement.

## Questions à trancher

1. ~~Le type de tortue~~ — **tranché** : un réglage de la parcelle.
2. ~~Des espacements différents dans une même série~~ — **tranché** : la moyenne des deux voisins,
   la moitié de l'espacement aux deux bouts.
3. ~~Où ranger les lignes~~ — **tranché** : une nouvelle table.
4. ~~Annuelle ou pérenne~~ — **tranché** : `cycle` = annuelle, bisannuelle, vivace, ligneuse.
5. **Deux façons de dire ce qui pousse dans une bordure.** Les bordures restent, pour toutes les
   parcelles (tranché). La question porte sur le **champ « Emplacement »** du formulaire d'un végétal
   du scénario pérenne (`conception-scenario.php`), qui propose déjà « Bordure externe » et
   « Bordure interne » : une fois la Phase 2 faite, la même plante pourrait être déclarée en bordure
   par ce champ (sans côté, comptée d'un seul tenant) **et** posée sur une ligne de bordure du plan
   (par côté, comptée plant par plant), avec deux nombres qui divergent. — **Tranché le 2026-09-23 :
   la ligne de bordure du plan fait foi.** Dans le scénario, « Bordure externe / interne » ne compte
   plus rien de lui-même : il renvoie aux lignes posées sur le plan et montre leurs nombres (Phase 2).
6. ~~La première règle des services~~ — **tranché** : validée.

## Hors périmètre

La bordure dessinée à la main sur la carte, la tortue ronde, les portions et le décalage d'un rang
(la référence les avait), la pesée des graines de biomasse (`Biomasse::semis()` de la référence),
les guildes de Plantae.
