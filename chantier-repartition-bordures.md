# Chantier — répartir une liste de plantes sur les bordures d'une parcelle

*Proposé le 2026-10-01, à la demande du porteur. Toutes les questions sont tranchées le même jour. **Phase 1 faite le
2026-10-01** (la règle, `src/RepartitionBordures.php`) ; Phases 2 à 4 pas commencées.*

## L'intention

Aujourd'hui, la personne compose les bordures **côté par côté** (`conception-parcelles.php`) : pour chaque côté, elle
choisit une série d'espèces qui alternent, ou elle demande la proposition de l'atelier, qui lui montre **toutes** les
espèces du référentiel remplissant la fonction de la bordure (`Bordures::proposer()`), sans ordre de préférence.

Le porteur veut un second chemin : la personne choisit **une liste de plantes pour toute la parcelle**, et l'atelier
**dispose ces plantes sur les bordures qu'elle a définies**, d'après les règles d'espacement, de strate et de diversité
(l'alternance des espèces).

## Ce qui existe et sert tel quel

| Pièce | Où | Ce qu'elle apporte |
|---|---|---|
| Les bordures définies par la personne | `parcelle.plan` (externe, interne ; côtés actifs, largeurs) | les côtés à remplir et leur longueur (`Bordures::lignes()`) |
| Une ligne par côté, une série qui alterne | `ligne_plantation`, `Web/LignesPlantation::enregistrer()` | l'écriture d'un côté, sous verrou de version |
| L'espacement | `Bordures::compter()` | écart = moyenne des espacements voisins, moitié aux bouts ; le compte des plants |
| La fonction d'une bordure | `Bordures::CADRES`, `Bordures::convient()` | externe : bloque-herbe, couvre-sol, vent ; interne : biomasse |
| Les pérennes seulement | `Bordures::CYCLES_EXCLUS` | annuelles et bisannuelles écartées |
| La strate de service | `LignesDeService::stratesService()` | la strate d'une plante quand elle rend un service (schéma 18) |
| L'état d'un côté de bordure | `parcelle.plan.bordures.externe.<côté>` (`actif`, `largeur`) | là où se range la nouvelle coche « brise-vent » |

## Décisions du porteur (2026-10-01)

1. **Une plante qui convient aux deux bordures va sur les deux** (consoude, chalef, topinambour, menthe…).
2. **Le brise-vent se place du côté d'où vient le vent.** Seconde réponse, même jour : **« brise-vent » est un attribut
   d'un côté de la bordure externe, que la personne coche dans le plan — décoché par défaut.** L'atelier ne déduit pas
   le vent de la carte : la rose des vents (`Web/Vents.php`) n'est jamais enregistrée, et le calque « Les vents » reste
   un dessin. C'est la personne, qui connaît son terrain, qui dit quel côté arrête le vent.
3. **Une espèce « colonne vertébrale »** revient plus souvent que les autres dans l'alternance. Le ch. 14 de Théry fait
   du goji « la colonne vertébrale des bordures » (forte biomasse équilibrée, racines plongeantes, plusieurs strates).
   Seconde réponse, même jour : **la personne la désigne, une par bordure** (une pour l'externe, une pour l'interne, au
   plus), et elle revient **un plant sur deux** : goji, A, goji, B, goji, C…
4. **La répartition se fait sur des bordures vides** : ou bien la personne compose elle-même toutes les bordures, ou
   bien l'atelier part de bordures toutes vides. Pas de mélange des deux. Une fois la répartition acceptée, la personne
   peut retoucher un côté à la main, comme aujourd'hui.
5. **Une seule liste pour toute la parcelle**, et cette liste doit **couvrir toutes les fonctions de service des
   bordures** définies (bloque-herbe pour l'externe, biomasse pour l'interne, et le vent si un côté est coché
   « brise-vent »). Seconde réponse, même jour : **sinon, l'atelier refuse de répartir** — « la diversité fait partie de
   la réussite du système syntropique » (le porteur). Le refus dit ce qui manque : la fonction, la bordure, le côté.
   Troisième réponse, même jour : **deux espèces au moins par côté**, la colonne vertébrale comptant pour une — sinon,
   refus.
6. **Une plante sans espacement au référentiel est écartée** : le placement ne peut se faire au hasard. Elle n'est pas
   proposée dans la liste, ou elle y figure grisée, avec la raison (« pas d'espacement au référentiel — à compléter
   dans Espèces »).

## Les règles de la répartition

Pour chaque côté actif de chaque bordure, l'atelier retient les espèces de la liste qui :

1. **remplissent la fonction de la bordure** (`Bordures::convient()`), et ne sont pas annuelles ni bisannuelles ;
2. **respectent la strate** : sur les faces **sud et ouest de la bordure externe**, des **émergentes seulement** (ch. 14
   § 4.2, rangé par l'autrice parmi « ce qui ne bouge pas ») — aujourd'hui une simple réserve affichée, ici une règle
   qui décide ; les côtés nord et est peuvent porter des strates plus basses ;
3. **pour le vent** : un côté coché « brise-vent » reçoit **en alternance des brise-vent et des bloque-herbe**, avec
   **au moins une espèce brise-vent** (décision du porteur, 2026-10-01 — le bloque-herbe reste la fonction première de
   l'externe, ch. 14) ; une espèce dont le seul rôle de bordure est `vent` ne va **que** sur un côté coché.

Puis il compose la série du côté :

4. **la diversité** : les espèces retenues alternent, et deux plants voisins ne sont jamais de la même espèce ;
5. **la colonne vertébrale** de la bordure, si elle est désignée et convient au côté, occupe **un plant sur deux** ;
   les autres espèces alternent entre ses plants ;
6. **l'espacement** : le compte des plants suit `Bordures::compter()`, sans changement.

**Le refus** (décision 5) : l'atelier ne répartit rien tant qu'un côté actif n'a pas **deux espèces au moins** qui lui
conviennent — par exemple une seule émergente pour la face sud de l'externe, aucune biomasse pour l'interne, aucune
brise-vent pour un côté coché. Le message nomme chaque côté et ce qui lui manque. Une espèce de la liste qui ne trouve sa place sur **aucun**
côté (une plante basse quand seuls les côtés sud et ouest de l'externe sont actifs) est dite aussi, sans bloquer.

**Ce qui n'est pas codé, faute de source** : une alternance de strates le long d'une ligne (haute, basse…) ; une
hauteur de bordure calculée d'après les productions de l'intérieur (le ch. 14 dit de « proportionner » l'une à
l'autre, sans chiffre). La ligne des lianes (tortue liane) n'entre pas dans la répartition : elle garde son chemin
propre.

## Le vent : une coche sur un côté de l'externe

Chaque côté actif de la bordure externe porte une coche **« brise-vent »**, décochée par défaut, rangée dans
`parcelle.plan` à côté de `actif` et `largeur` (aucun changement de schéma : le plan est un JSON). Elle s'enregistre
avec le plan, sous le même verrou de version. Elle ne sert, pour l'instant, qu'à la répartition ; le dessin du plan
peut la montrer (un trait de plus sur le côté). L'export et l'import la portent sans rien changer (`parcelle.plan` sort
en entier).

## Phases

### Phase 1 — La règle de répartition (pure)

Une classe pure `RepartitionBordures` : en entrée, la liste d'espèces, les côtés actifs (bordure, côté, longueur), la
colonne vertébrale éventuelle, les côtés exposés au vent (ou « inconnu ») ; en sortie, la série de chaque côté, le
compte des plants par espèce, et ce qui n'a pas trouvé de place, avec la raison. Le contrôle de couverture
(décision 5) en fait partie. Des contrôles purs, comme pour `PlanDeCulture`.

**Faite le 2026-10-01.** `src/RepartitionBordures.php` : `repartir($parcelle, $especes, $colonnes)` rend, pour chaque
côté actif de l'externe et de l'interne, la série, la colonne, le compte (`Bordures::compter()`), les plants par espèce,
et le refus éventuel ; `ok` est faux dès qu'un côté est refusé (rien ne doit alors être posé). À part : les espèces
écartées (et pourquoi), celles qui n'ont trouvé place sur aucun côté (une raison par côté), des notes (colonne qui ne
convient pas à un côté, colonne hors liste). `cotesBriseVent()` lit déjà la coche dans `parcelle.plan` — la Phase 2 n'a
plus qu'à l'écrire. Sur un côté coché, la série prend une brise-vent, puis une autre espèce, et ainsi de suite ; la
colonne vertébrale se glisse entre chacune (C, A, C, B…). **Aucun changement de schéma.** 77 contrôles purs (écartées,
deux bordures, faces exposées, refus, brise-vent, colonne, voisins jamais identiques, sans place, compte de 21 plants
sur 12 m recalculé à la main), dans le dossier temporaire de la session — non conservés, comme ceux de `PlanDeCulture`.

**Écart tranché en codant** : sur les faces sud et ouest de l'externe, une espèce **dont la strate n'est pas notée** est
exclue (« sa strate n'est pas notée ») — la règle demande des émergentes, et l'atelier ne devine pas. `Bordures::
proposer()` ne la signalait pas ; la répartition, qui décide, ne peut pas la laisser passer.

**Constat, essayé sur le cadre réel (base locale, lecture seule)** : sur les **64 espèces de bordure** du cadre (pérennes,
avec une fonction de bordure), **46 n'ont pas d'espacement** au référentiel — dont la plupart des ligneux de biomasse
(saule, noisetier, aubépine, peuplier…) et presque tous les brise-vent (argousier, miscanthus, feijoa, ragouminier ; seuls
le chalef et le topinambour en ont un). Avec la décision 6, elles sont écartées : **un côté coché « brise-vent » sera
le plus souvent refusé** tant que ces espacements manquent. À compléter dans Espèces (ou au CSV du cadre) avant que la
répartition serve vraiment.

### Phase 2 — La coche « brise-vent »

Sur `conception-parcelles.php`, une case « brise-vent » à chaque côté de la bordure externe, décochée par défaut,
enregistrée avec le plan (`Parcelles::enregistrerPlan()`, même verrou), relue au rechargement ; un côté inactif garde
sa coche sans qu'elle compte. Vérifié en CDP, `verifier-export` identique.

### Phase 3 — La page

Dans le panneau Données de `conception-parcelles.php`, **seulement si toutes les bordures de la parcelle sont vides**
(décision 4) : « Répartir une liste de plantes ». La personne coche ses espèces (celles qui conviennent à au moins une
bordure définie et ont un espacement ; les autres, grisées avec la raison), désigne la colonne vertébrale de chaque
bordure parmi elles (facultatif), et demande un
**aperçu** : la série de chaque côté et son compte, dessinés sur le plan, et ce qui n'a pas trouvé de place. Une liste incomplète est **refusée**, avec ce qui manque. **Rien
n'est écrit avant l'accord** ; « Poser ces bordures » écrit tous les côtés **en une transaction**, et refuse si une
bordure a été remplie entretemps (« les bordures ont changé, recalculez »). Lecteur : rien de visible, POST forgé 403.
La liste elle-même n'est pas enregistrée : seules les séries posées le sont (aucun changement de schéma).

### Phase 4 — Le livret et les vérifications

Le chapitre du livret « L'atelier » qui décrit les bordures, mis à jour dans la même vague. Vérifié en local (CDP) :
répartition sur une parcelle à externe et interne, côtés sud/ouest seuls, côté brise-vent coché ou non, colonne
vertébrale (un plant sur deux), liste qui ne couvre pas une fonction (refus), bordures déjà remplies (fonction absente), conflit entre l'aperçu et l'écriture,
lecteur, aucun débordement de 1280×720 à 320×568.

## Questions ouvertes

Aucune. Tranchées le 2026-10-01 : la colonne vertébrale (la personne, une par bordure, un plant sur deux) ; le vent (une
coche « brise-vent » par côté de l'externe, décochée par défaut ; sur un côté coché, brise-vent et bloque-herbe en
alternance, au moins une brise-vent) ; la liste incomplète (refus) ; deux espèces au moins par côté (sinon refus).
