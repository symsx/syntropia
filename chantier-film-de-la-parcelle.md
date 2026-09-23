# Chantier — le film de la parcelle : chaque plante à sa place, dans l'espace et dans le temps

*Proposé le 2026-09-23, à la demande du porteur. Pas commencé.*

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

## Phases

### Phase 1 — La compétence : les quatre dimensions

Le nœud `skills-map/nodes/conception-de-la-tortue.md` gagne une section « Le scénario en quatre dimensions » : les
règles ci-dessus, décisions du porteur, et ce que le corpus en dit (la forme pyramidale, l'occupation de l'espace en
successions du ch. 8 et du module 5, les trois temps pré-culture / principale / post-culture de 5-C). Aucun code.

### Phase 2 — Le temps de chaque plante

Une classe pure `Temps` : la semaine comme unité (lundi ISO), la fenêtre d'occupation d'une plante en semaines, et le
changement d'échelle (semaine, mois, année). Pour une **culture du potager**, la fenêtre va de sa mise en place (semis en
place ou repiquage) à sa récolte (question n° 3). Pour les **lignes de service et les bordures**, voir la question
n° 2. Pour une **plante du scénario pérenne**, ses périodes rapportées au calendrier par la date de plantation de la
parcelle.

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

### Phase 6 — Les besoins des productions

Proposer les plantes de service **aussi d'après les besoins des productions** (un tuteur pour un grimpant, de l'ombre,
de l'azote…), en plus des strates vides. Source à trancher (question n° 1).

## Questions ouvertes

1. **La source des besoins** (Phase 6). L'atelier a déjà un **instantané** des guildes de Plantae
   (`contenu/guildes-plantae.json`, 179 plantes, lu par `src/Guildes.php`) : il ne dépend pas de l'installation de
   Plantae chez alwaysdata, mais il vieillit et ne porte pas encore les apprentissages de la syntropie. S'en servir
   comme repère indicatif en attendant, ou attendre Plantae 1.0 ?
2. **Le temps des plantes de service et des bordures.** Elles n'ont pas de dates aujourd'hui. Les prendre dans le
   calendrier de leur espèce au référentiel (`periode_semis`/`periode_repiquage`/`periode_recolte`) quand il y est, et
   tenir une vivace ou une ligneuse pour présente toute l'année ? Ou leur donner des dates propres, ligne par ligne ?
3. **Le début de la fenêtre d'une culture.** La planche est-elle occupée dès le **semis à l'abri** (la germination, en
   pépinière) ou seulement à la **mise en place** (semis en place ou repiquage) ? Je penche pour la mise en place : la
   germination à l'abri se fait ailleurs que sur la planche.
4. **« Pas de collision » au sol** entre deux plantes de strates différentes : une distance minimale (quelques
   centimètres ?), ou seulement que les deux points ne soient pas les mêmes ? Et entre deux plantes de même strate
   d'espèces différentes, l'écart est-il **la moyenne de leurs espacements**, comme sur une ligne de bordure ?
