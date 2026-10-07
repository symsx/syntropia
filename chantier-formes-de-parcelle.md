# Les formes de la parcelle — plan

**Demandé par le porteur le 2026-10-05. Planifié, pas commencé. Tout est tranché par le porteur le 2026-10-07 (six
questions, puis cinq points de détail) : prêt à commencer par la Phase 1.**

## Pourquoi

Aujourd'hui une parcelle est un rectangle orienté nord-sud. Un terrain réel l'est rarement : une haie qui tourne, un
talus, une limite de propriété en biais. Et une tortue se dessine aussi en ovale : le module 7 de la formation Joala en
montre une de 34 × 20 m, avec une mare centrale
(`connaissances/formation-joala/plantations-perennes-et-trognes.md`).

Le chantier a trois objectifs, donnés par le porteur :

1. **Un contour libre** pour la parcelle, au lieu du seul rectangle.
2. **Des chemins** dans la parcelle.
3. **Des points d'eau**, une mare par exemple. Théry plante la structure dans cet ordre : « mare, bords de carapace,
   pérennes intérieures » (ch. 14, principe 16). Une première tortue qui reçoit une mare conséquente laisse aux
   suivantes de petits bassins de rappel, relais pour la faune (ch. 14).

## Décisions du porteur (2026-10-07)

- **Le rectangle reste tel qu'il est.** La parcelle polygonale est une **nouvelle forme de parcelle**, expérimentale,
  qui ne touche pas au fonctionnement actuel. **La personne choisit la forme en créant la parcelle** : rectangle ou
  polygone.
- **Le contour est un polygone quelconque** (question 1), pour rester simple :
  - des sommets qu'on place et qu'on déplace ;
  - chaque sommet **colle à une grille**, de 10 cm par défaut, pour faciliter le placement ;
  - pendant le tracé, **un indicateur donne la longueur du segment en cours** ;
  - **un clic droit annule le dernier segment** ;
  - **un segment qui se termine sur le premier point ferme le polygone**.
- **Il n'y a plus de bordures, mais des « zones de culture »** (questions 2, 3 et 4). Ce sont des polygones, tracés
  comme le contour, **à l'intérieur de la parcelle** ; une zone peut partager une limite avec la parcelle. **En fin de
  conception, tout ce qui n'est pas zone de culture est passe-pied** : les chemins ne se tracent pas, ils sont ce qui
  reste.
- **Les points d'eau sont des polygones**, placés dans la parcelle **hors des zones de culture** (question 5).
- **Une mare ou un chemin tracé sur la carte du site ne se reprend pas** dans le plan de la parcelle (question 6) : les
  deux restent séparés.

## Où en est le code

- **Le contour** : `parcelle.longueur_m` × `parcelle.largeur_m`, un rectangle. Toute la géométrie en part, en mètres,
  `x` d'ouest en est depuis le bord ouest, `y` du nord au sud (`src/Bordures.php`, `src/Planches.php`,
  `src/Materiel.php::bandes()`, `public/assets/js/conception-parcelles.js`). **Ce code reste celui du rectangle.**
- **Les bordures** : huit côtés activables (externe et interne × nord, sud, est, ouest) et leurs largeurs, dans le JSON
  `parcelle.plan`. Leurs lignes de plants (`Bordures`), leur répartition (`RepartitionBordures`), le compte des plants
  (Matériel et eau, Dossier Projet) et l'occupation au sol supposent tous quatre côtés droits. Une parcelle polygonale
  n'en a pas.
- **La création d'une parcelle** : `conception-parcelles.php?nouvelle=1` (code, nom, type), puis
  `Web/Parcelles::creer()`. Le choix de la forme s'y ajoute.
- **La colonne `parcelle.forme`** (`zebre`, `abeille`, `liane`, `autre`) existe au schéma mais n'est lue nulle part ; ses
  valeurs sont des formes de tortue, que `parcelle.tortue` porte aujourd'hui. **Ne pas la réemployer** pour le contour :
  une colonne à part (par exemple `parcelle.contour`, `rectangle` ou `polygone`) évite la confusion.
- **Les outils de la barre** : « Sélection » et « Déplacer un bord ». « Tracer une ligne » et « Gomme », venus de la
  maquette `le-bureau/`, ont été retirés de la vue le 2026-10-05 en attendant ce chantier.
- **Ce qui existe ailleurs, sans lien** : l'étude du site (`conception-etude-site.php`, `assets/js/calques.js`) trace sur la
  carte des contours, rangés par calque (`calque_element`), dont `eau` et `circulations`. Échelle du site, en latitude
  et longitude : pas repris (décision 6). Son code de tracé peut servir de modèle, pas de source de données.

## Les points de détail, tranchés (2026-10-07)

1. **Le pas de la grille se règle**, de **5 cm à 100 cm** ; 10 cm par défaut.
2. **Une parcelle polygonale est pérenne ou potager**, comme le rectangle.
3. **Un scénario par parcelle**, comme aujourd'hui. **Une zone de culture est l'équivalent d'une planche de potager**,
   en forme de polygone : elle reçoit des cultures, annuelles ou pérennes. Dans le code, une culture se rattache à sa
   planche par son numéro (`succession_ligne.planche`) ; une zone peut prendre la même place. Le compte des plants d'une
   culture (`Materiel::quantiteCulture()`, qui part des bandes du rectangle) devra partir de la surface de la zone.
4. **Un point d'eau n'a que sa surface et son emplacement** sur la parcelle.
5. **La forme se change tant que la parcelle ne contient aucun objet** (ni zone de culture, ni point d'eau). Dès qu'elle
   en contient un, le choix est fermé, et l'atelier dit pourquoi.

## Phases proposées

1. **Le choix de la forme et le contour polygonal** : la forme choisie à la création (colonne neuve, schéma 21),
   modifiable tant que la parcelle est vide ; le tracé du polygone sur le bureau (grille réglable de 5 à 100 cm,
   longueur du segment, clic droit, fermeture sur le premier point) ; le déplacement des sommets ; la surface
   recalculée. Le rectangle inchangé, vérifié de bout en bout.
2. **Les zones de culture** : leur tracé, comme le contour, et la règle « à l'intérieur de la parcelle, une limite
   partagée admise » ; leur surface ; les passe-pieds montrés comme le reste. Les cultures s'y placent comme sur une
   planche de potager.
3. **Les points d'eau** : leur tracé, hors des zones de culture et dans la parcelle ; leur surface et leur emplacement.
4. **Les étapes suivantes** : le scénario (un par parcelle), le compte des plants par la surface des zones, Matériel et
   eau, l'occupation au sol, le Dossier Projet et le feuillet de chantier, pour une parcelle polygonale.

Chaque phase met à jour le livret « L'atelier », l'export et l'import d'un projet (`bin/dev.py verifier-export`), et
monte `Version::SCHEMA` si le schéma change.
