# Chantier — le film de la parcelle : chaque plante à sa place, dans l'espace et dans le temps

*Proposé le 2026-09-23, à la demande du porteur. **Phases 1, 2 et 2 bis faites et déployées le 2026-09-23** (schéma 15).*

## L'intention

Le scénario se pense **en quatre dimensions** (le porteur, 2026-09-23) :

1. **Au sol (deux dimensions)** — chaque plante, de production ou de service, a **son point de plantation**, et
   **il n'y a pas de collision** entre deux points.
2. **La strate (troisième dimension)** — **les espacements se respectent entre plantes d'une même strate** ; deux
   plantes de strates différentes peuvent être proches (c'est pourquoi une production se place sans tenir compte des
   plantes de service voisines, qui sont dans une autre strate).
3. **Le temps (quatrième dimension)** — il joue avec les successions : **une plante occupe sa place de sa
   germination à sa récolte**. L'unité est **la semaine** (à affiner avec l'expérience). **L'échelle de temps se
   choisit selon le cycle des espèces**, comme on choisit l'échelle d'une carte : la semaine pour le potager, le mois
   ou l'année pour des pérennes.

Et une règle : **un espace ne reste pas vide** dans le temps. L'atelier **montre** les vides du calendrier — les voir
suffit à les signaler — et **propose** de quoi les remplir, depuis ce qu'il a en base ; une culture peut revenir
plusieurs fois d'affilée au même endroit.

C'est **un film** que l'on met en place : un **curseur de temps** surligne sur la frise ce qui est présent à ce
moment-là, et montre **le plan vu de dessus** à ce moment-là.

## Ce qui existe

| | Existe | Manque |
|---|---|---|
| Points au sol | Les plants des **bordures** (`Bordures::compter()`) et des **lignes de service** des planches (`Planches`, `LignesDeService`) ont une position calculée | Les **productions** n'ont qu'un numéro de planche (et, en abeille, une bande) ; rien ne vérifie les collisions |
| Strate | `succession_ligne.strate`, `scenario_ligne.strate`, `espece.strates_possibles` | L'espacement entre plantes d'une même strate n'est vérifié nulle part |
| Temps, potager | Les dates de chaque culture (semis à l'abri, mise en place, récolte) ; le calendrier par mois ; la succession (« libérée le … par … ») | L'unité semaine ; le temps des plantes de service et des bordures ; les vides |
| Temps, pérenne | Les sept périodes du scénario (placenta 1 au climax, en quarts de période) ; `parcelle.date_plantation` | Le lien entre périodes et calendrier ; une échelle de temps au choix |
| Curseur | — | Tout |

## Décisions du porteur (2026-09-23)

- **Plantae** : l'idée de lire les besoins des productions dans les guildes de Plantae plaît, mais c'est une dépendance
  forte à un outil qui n'est pas encore en version 1.0, pas encore installé chez alwaysdata, et qui devrait lui-même
  évoluer avec les compétences de syntropie pour être pertinent. → **Les besoins des productions viennent en dernier**
  (Phase 6), et leur source est une question ouverte (n° 1).
- **Chaque plante a son point de plantation au sol, sans collision** ; **les espacements se respectent dans une même
  strate** ; le temps va **de la germination à la récolte**, en **semaines** ; **l'échelle de temps se choisit**.
- **Montrer les vides** du calendrier, et **proposer** de les remplir depuis la base — une culture peut se répéter.
- **Curseur** : il surligne sur la frise ce qui est présent (« à essayer pour voir ce que cela donne »), et montre le
  plan vu de dessus à ce moment-là. **Semaine pour le potager, échelle adaptable pour les pérennes.**

## Décisions du porteur (2026-09-23, seconde série)

- **Plantae** : le porteur continue Plantae et l'installe chez alwaysdata — **l'atelier peut s'appuyer dessus**. Le mode
  d'accès (l'instantané `contenu/guildes-plantae.json`, réexporté, ou une lecture directe) se décide à la Phase 6.
- **Le calendrier gère aussi les plantes de service et les bordures** : les dates de semis et de plantation des lignes de
  service des planches ; pour les bordures, **quand planter et quand perturber**. Ce sont des **prévisions** : le jour
  exact d'une perturbation s'adapte ensuite au jour opportun (la pluie, notamment) — le journal des perturbations
  (`suivi-perturbation.php`) garde ce qui a été fait.
- **L'occupation d'une planche commence à la mise en place** (semis en place ou repiquage). **L'atelier ne gère pas la
  pépinière** : le semis sous abri reste l'affaire du porteur de projet (sa date se note, sans plus).
- **Un délai de germination, en jours, pour les annuelles et les bisannuelles** : une donnée du référentiel
  (`espece.delai_germination_j`), pour situer la levée après un semis en place.
- **Pas de collision au sol : 5 cm au moins** entre deux plantes de strates différentes — « uniquement mon idée, mais
  on part sur ça » (le porteur) ; à affiner avec l'expérience. Entre deux plantes de **même strate**, l'espacement ; pour
  deux espèces différentes, **la moyenne de leurs espacements**, comme sur une ligne de bordure (**confirmé par le
  porteur**).
- **Les perturbations prévues d'une bordure : une liste de dates** (le porteur), chacune avec son geste.

## Décision du porteur (2026-10-01) — une plante qui change de strate

- **Le film de la parcelle tient compte d'une plante qui passe d'une strate à une autre au fil de son cycle.** Le
  support Joala (`connaissances/formation-joala/strates-et-lumiere.md`) et Théry (ch. 05) en donnent deux exemples : la
  **fève** commence en émergente (la lumière d'hiver est faible) et finit sa production au printemps en haute, sous une
  ombre légère ; la **pomme de terre** commence en émergente et grossit ses tubercules en moyenne. Aujourd'hui le
  référentiel porte bien les deux strates (`strates_possibles`), mais l'atelier ne propose que celle du départ
  (`strate_production` = émergente) et rien ne dit quand la plante change de strate. Cela touche la Phase 3 (les
  espacements entre plantes **de même strate présentes au même moment**) et la Phase 5 (le plan vu de dessus à un
  moment donné). Voir la question ouverte n° 1.
- **Laissé de côté** : la position dans une strate (« haut de la moyenne », « bas ou haut selon l'exposition ») — elle
  compliquerait trop les règles de l'atelier.

## Le prévu et le réalisé (le porteur, 2026-09-23)

- **En Conception**, les dates forment **un calendrier prévisionnel** : ce sont des prévisions, dites comme telles.
- **En Réalisation**, le plan se met en place sur le terrain **à la période prévue** ; **la date exacte — la date de
  réalisation — se donne à ce moment-là**.
- **En Suivi**, le calendrier et le scénario **s'adaptent à l'avancement réel** : aux observations et aux actions
  réelles.
- Exemple : **la température du sol au printemps décide des semis** — la date est une prévision, la température observée
  donne la date réelle. Le corpus le dit : *« le critère n'est pas la date mais la température du sol »*, 6 °C pour le maïs,
  5 °C pour le tournesol (Théry, ch. 9, règle 9).

Conséquences pour ce chantier : **chaque date se garde en deux temps** — la prévue (Conception) et la réalisée
(Réalisation), sans que l'une n'écrase l'autre ; la frise et le curseur montrent le prévu, et le réalisé quand il existe ;
une espèce peut porter **la température de sol qui déclenche son semis**, une condition à observer plutôt qu'une date.

## Phases

### Phase 1 — La compétence : les quatre dimensions

Le nœud `skills-map/nodes/conception-de-la-tortue.md` gagne une section « Le scénario en quatre dimensions » : les
règles ci-dessus, décisions du porteur, et ce que le corpus en dit (la forme pyramidale, l'occupation de l'espace en
successions du ch. 8 et du module 5, les trois temps pré-culture / principale / post-culture de 5-C). Aucun code.

**Fait le 2026-09-23** : section « Le scénario en quatre dimensions » du nœud.

### Phase 2 — Le temps de chaque plante

Une classe pure `Temps` : la semaine comme unité (lundi ISO), la fenêtre d'occupation d'une plante en semaines, et le
changement d'échelle (semaine, mois, année). Pour une **culture du potager**, la fenêtre va de sa mise en place (semis en
place ou repiquage) à sa récolte ; la levée, pour un semis en place, après le délai de germination de l'espèce
(`espece.delai_germination_j`, nouvelle colonne — schéma 13). Pour une **plante du scénario pérenne**, ses périodes
rapportées au calendrier par la date de plantation de la parcelle.

**Fait le 2026-09-23.** `src/Temps.php` (pur, 16 contrôles) : `semaine()` (le lundi ISO), `fenetreCulture()` (de la mise
en place à la récolte ; la levée d'un semis en place — pas de semis sous abri noté — après le délai de germination),
`fenetrePeriodes()`/`dateDePosition()` (les positions du scénario pérenne rapportées à la date de plantation : placenta
1, 90 jours ; placenta 2, 180 ; placenta 3 jusqu'à 3 ans ; secondaire 1 à 15 ans ; 2 à 25 ; 3 à 60 ; le climax borné à 100
ans — les fourchettes du ch. 11 prises à leur borne haute, un ordre de grandeur), `presente()`, `pas()` (semaine, mois,
année). **`espece.delai_germination_j` — schéma 13** (colonne facultative) : validé de 1 à 365 jours entiers, un champ
dans `especes.php`, une colonne du modèle CSV. **Aucune espèce du cadre n'en reçoit** : le corpus ne donne de délai de
levée que pour des graminées de prairie. Vérifié en local : validation (vide, 0, 400, 7,5, texte refusés), saisie et
relecture dans le formulaire (320×568), `verifier-export` identique (la fixture porte un délai). Rien ne s'en sert encore
à l'écran : c'est la base des Phases 3 à 5.

### Phase 2 bis — Le calendrier des plantes de service et des bordures

Les **lignes de service des planches** reçoivent leurs dates de semis ou de plantation (et de fin) ; les **bordures**,
leur date de plantation et leurs **perturbations prévues**. L'atelier propose ces dates depuis le calendrier de l'espèce
au référentiel (et Lopez) ; la personne les corrige. Ce sont des prévisions, dites comme telles : le jour réel s'adapte
au temps qu'il fait, et le journal des perturbations garde ce qui a été fait. Un changement de schéma (des dates
**prévues** sur `ligne_plantation`, et les perturbations prévues en liste de dates) — pensé pour recevoir plus tard, à
côté de chaque date prévue, sa **date réalisée** (Phase 7).

**Fait le 2026-09-23.** **Schéma 14** : `ligne_calendrier` (une ligne — `externe:nord`, `planche:3:2` — et sa
`mise_en_place_prevue`, sa `fin_prevue`) et `perturbation_prevue` (une liste : date prévue et geste, ceux du journal sans
le désherbage) ; deux tables facultatives, couvertes par l'export, l'import et `verifier-export`. `src/Web/CalendrierLignes.php`
(dates sous verrou de version ; ajouter, retirer une perturbation ; `repere()` : la mise en place — repiquage, à défaut
semis — et la fin — récolte — tirées du calendrier de l'espèce au référentiel, première espèce de la série qui en a un).
Sur **« Le plan »**, chaque bordure peuplée a « Le calendrier prévu » : la plantation prévue (proposée d'après le
référentiel, rien noté avant « Noter »), la liste des perturbations prévues, et le rappel des repères de Théry (ch. 12 :
la première coupe en vert en juin, les suivantes quand il y a de quoi couper, les tailles de ligneux en hiver hors gel) —
**l'atelier ne propose pas de dates de perturbation** : le corpus n'en donne pas, seulement des moments. Au **potager**,
chaque ligne de service reçoit sa mise en place et sa fin prévues dans le panneau « Les lignes » (proposées d'après le
calendrier de l'espèce, écrites avec les lignes) ; **le calendrier du potager** montre, en clair, les lignes de service et
les bordures (P mise en place, F fin, T perturbation — prévues) à côté des cultures. Vérifié en local (projet supprimé) :
date de plantation notée, deux perturbations prévues puis une retirée, geste forgé, date absente et conflit de version
refusés ; maïs d'une ligne proposé de mi-mai à fin septembre, écrit, fin antérieure refusée ; les rangées du calendrier en
2026 et 2027 ; lecteur (lecture seule, POST refusé) ; 320×568 ; `verifier-export` identique. **Ajouté le même jour, à la
demande du porteur** : les perturbations prévues des lignes de service des planches (le maïs, le tournesol de la ligne
centrale), dans le panneau « Les lignes » — la ligne (la centrale par défaut), la date, le geste ; vérifié (deux lignes,
retrait, ligne forgée refusée, T au calendrier, 320×568).

### Phase 3 — Les points de plantation

Une classe pure `Implantation` : toutes les plantes d'une parcelle avec leur point au sol, leur strate, leur fenêtre
de temps. Les bordures et les lignes de service ont déjà leurs points ; **les productions d'une planche se disposent
d'après leur espacement**, en rangs le long de la planche, en évitant le point de toute autre plante (pas de collision
au sol) et en respectant l'espacement avec les plantes de **même strate** présentes **au même moment** ; celles qui ne
tiennent pas se disent. Calculé, non enregistré (comme les plants des bordures). Le nombre de plants d'une production
en découle — il remplace, dans « Matériel et eau », l'estimation « une ligne sur la planche ».

### Phase 4 — Les vides du calendrier

Le calendrier du potager passe à **la semaine** et montre, planche par planche, **les semaines où rien n'est en
place** (à l'échelle de la planche d'abord ; plus fin ensuite, si le porteur le demande). Pour chaque vide, une
**proposition** depuis la base : ce que la table de succession cite comme suite, les espèces dont le calendrier au
référentiel (et les repères de Lopez) tient dans le vide, **y compris la même culture répétée** ; un aperçu, rien
d'écrit avant l'accord. Côté pérenne, la frise signale déjà « une place à imaginer » : la même lecture, sur l'échelle
choisie.

### Phase 5 — Le curseur

Sur la frise du scénario et sur le calendrier du potager, **un curseur de temps** (échelle au choix : semaine, mois,
année) surligne ce qui est présent à ce moment-là. Sur **« Le plan »** (`conception-parcelles.php`), le même curseur
dessine **la parcelle vue de dessus à ce moment-là** : bordures, lignes de service, productions en place. À essayer,
puis ajuster.

### Phase 7 — Du prévu au réalisé

*Déjà fait, en avance, à la demande du porteur (2026-09-23)* : **`espece.temperature_sol_semis_c` — schéma 15** (la
température du sol qui permet le semis, de 0 à 40 °C : un champ dans Espèces, une colonne du modèle CSV ; le cadre porte
les deux valeurs du corpus, maïs 6 °C et tournesol 5 °C, Théry ch. 9). Le porteur a aussi demandé de retirer
`espece.origine` « si cela n'a pas d'utilité » : elle en a une — « Matériel et eau » range par elle les plants posés
sur les lignes du plan (bordures, lignes de service) — **le porteur a décidé de la garder** (2026-09-23).

La page Réalisation reçoit **la date réelle** de chaque mise en place (une culture, une ligne de service, une bordure),
à côté de la date prévue ; le Suivi **recale** le calendrier sur ce qui s'est réellement passé (les cultures suivantes
se décalent d'autant, en proposition) ; une espèce peut porter **la température du sol qui déclenche son semis**
(`espece`, une colonne — le corpus en donne deux : maïs 6 °C, tournesol 5 °C), et un relevé de température du sol en
Suivi dit si le moment est venu. À préciser avec le porteur avant de commencer : quelles pages de Réalisation et de
Suivi portent ces dates, et comment la température du sol se relève.

### Phase 6 — Les besoins des productions

Proposer les plantes de service **aussi d'après les besoins des productions** (un tuteur pour un grimpant, de l'ombre,
de l'azote…), en plus des strates vides — **en s'appuyant sur Plantae** (décision du porteur), que le porteur fait
évoluer avec les compétences de syntropie.

## Questions ouvertes

Toutes les questions du 2026-09-23 sont tranchées. Une question est ouverte depuis le 2026-10-01 :

1. **À quel moment une plante change-t-elle de strate ?** Les sources ne donnent aucune date et aucune durée : une
   saison pour la fève (de l'hiver au printemps), un stade pour la pomme de terre (le grossissement des tubercules).
   Trois pistes, à trancher avec le porteur avant la Phase 3 : une date au référentiel (une période en mots, comme
   `periode_recolte`) ; une part de la fenêtre de culture (par exemple la moitié) ; ou un nombre de semaines après la
   mise en place.
   **Élément de réponse du porteur (2026-10-01)**, à confirmer lors de la formation, où il posera la question : la plante
   change de strate **à sa maturité, quand elle entre en production** — juste avant son climax, donc pendant son propre
   secondaire. La pomme de terre grossit ses tubercules en moyenne — **sa strate de production est passée d'émergente à
   moyenne au cadre** (décision du porteur, 2026-10-01). Le noisetier garde la sienne (production haute, service
   moyenne, d'après les études citées au seed) : il fructifie dans une strate plus lumineuse que celle où il rend
   service. Si la règle se confirme, la strate de production est celle de la plante mûre, et la bascule tombe au début
   de la production (la récolte, au référentiel, en donne déjà la fin).
