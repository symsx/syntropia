# Les formes de la parcelle — plan

**Demandé par le porteur le 2026-10-05. Planifié, pas commencé. Les règles des bordures sont à définir avec lui.**

## Pourquoi

Aujourd'hui une parcelle est un rectangle orienté nord-sud. Un terrain réel l'est rarement : une haie qui tourne, un
talus, une limite de propriété en biais. Et une tortue se dessine aussi en ovale : le module 7 de la formation Joala en
montre une de 34 × 20 m, avec une mare centrale
(`connaissances/formation-joala/plantations-perennes-et-trognes.md`).

Le chantier a trois objectifs, donnés par le porteur :

1. **Un contour brisé ou courbe** pour la parcelle, au lieu du seul rectangle ; les règles des bordures sur un tel
   contour sont à définir.
2. **Placer des chemins** dans la parcelle.
3. **Placer des points d'eau**, une mare par exemple. Théry plante la structure dans cet ordre : « mare, bords de
   carapace, pérennes intérieures » (ch. 14, principe 16). Une première tortue qui reçoit une mare conséquente laisse aux
   suivantes de petits bassins de rappel, relais pour la faune (ch. 14).

## Où en est le code

- **Le contour** : `parcelle.longueur_m` × `parcelle.largeur_m`, un rectangle. Toute la géométrie en part, en mètres,
  `x` d'ouest en est depuis le bord ouest, `y` du nord au sud (`src/Bordures.php`, `src/Planches.php`,
  `src/Materiel.php::bandes()`, `public/assets/js/conception-parcelles.js`).
- **Les bordures** : huit côtés activables (externe et interne × nord, sud, est, ouest) et leurs largeurs, dans le JSON
  `parcelle.plan`. Leurs lignes de plants (`Bordures`), leur répartition (`RepartitionBordures`), le compte des plants
  (Matériel et eau, Dossier Projet) et l'occupation au sol supposent tous quatre côtés droits.
- **Les outils de la barre** : « Sélection » et « Déplacer un bord ». « Tracer une ligne » et « Gomme », venus de la
  maquette `le-bureau/`, étaient grisés « À venir » ; retirés de la vue le 2026-10-05 en attendant ce chantier.
- **Ce qui existe déjà ailleurs** : l'étude du site (`conception-etude-site.php`, `assets/js/calques.js`) trace sur la
  carte des points, des lignes, des contours et des tracés à main levée, rangés par calque (`calque_element`), dont
  **`eau`** et **`circulations`**. C'est l'échelle du site, en latitude et longitude ; le plan de la parcelle est à
  l'échelle de la parcelle, en mètres.

## Questions à trancher avec le porteur

1. **Quelles formes de contour** : un polygone quelconque (des sommets qu'on déplace), des arcs, un ovale ou un cercle
   paramétré (deux demi-axes, comme la tortue du module 7) ? Et le rectangle reste-t-il la forme par défaut ?
2. **Les bordures sur un contour libre** : une bordure suit-elle tout le contour, ou côté par côté comme aujourd'hui ?
   Comment se nomment et s'orientent les côtés quand ils ne sont plus nord, sud, est et ouest ? Que deviennent les angles
   (une ligne de plants qui tourne, ou deux lignes qui se rejoignent) ?
3. **Les planches dans une forme libre** : restent-elles droites et parallèles, coupées par le contour (les planches de
   7,10 m de l'ovale du module 7), ou suivent-elles la courbe ?
4. **Les chemins** : une largeur par chemin ? Remplacent-ils un passe-pied, ou s'ajoutent-ils ? Coupent-ils les planches
   et les bordures, ou les contournent-ils ?
5. **Les points d'eau** : quelle forme (cercle, ovale, contour libre) ? Quelles données servent une étape plus loin :
   surface, profondeur, volume pour l'arrosage (Matériel et eau), berges plantées (le scénario) ?
6. **Le lien avec l'étude du site** : une mare ou un chemin déjà tracé sur la carte se reprend-il dans le plan de la
   parcelle, ou les deux restent-ils séparés ?

## Phases proposées (à revoir une fois les questions tranchées)

1. **Le contour libre**, sans bordures : dessiner, enregistrer, recalculer la surface ; le rectangle reste valable.
   Changement de schéma (le contour en mètres).
2. **Les bordures sur le contour** : selon la règle tranchée (question 2), lignes de plants et compte.
3. **Les planches dans le contour** : la question 3, et ce qu'elle change au compte des plants et à l'occupation au sol.
4. **Les chemins** : l'outil, leur effet sur les planches et les bordures.
5. **Les points d'eau** : l'outil, et l'usage de leurs données dans les étapes suivantes.

Chaque phase met à jour le livret « L'atelier », l'export et l'import d'un projet (`bin/dev.py verifier-export`), le
Dossier Projet et le feuillet de chantier, qui dessinent la parcelle.
