# Les relevés de sol — plan

**Demandé par le porteur le 2026-10-04 (« est-ce que l'atelier consigne quelque part les relevés de terrain pour le
sol ? »). Planifié, pas commencé. Toutes les questions tranchées par le porteur le 2026-10-05.**

## Pourquoi

Le corpus syntropique promet « un sol plus fertile après récolte qu'avant plantation » sans donner aucun moyen de le
vérifier. Les sept tests de terrain de l'atelier (`src/FicheTerrain.php`, venus du diagnostic régénératif — voir
`skills-map/nodes/diagnostic-des-sols.md`) donnent ce moyen, à condition de garder les mesures et de les comparer. Le
nœud dit quand les reprendre : **au point zéro, à la fin de la première année, à la fin de la troisième**.

Aujourd'hui ces mesures restent sur le papier. Le chantier les fait entrer dans l'atelier, pour que la personne voie son
sol changer d'une campagne à l'autre.

## Où en est le code

- **La table existe** : `releve_sol` (`sql/01-syntropia.sql`), une ligne par date et par point de relevé, avec la
  campagne, la case témoin et une colonne par mesure retenue. L'export et l'import d'un projet l'emportent déjà en entier
  (`Export.php`, `Import.php`).
- **Une seule saisie l'écrit** : « Matériel et eau » note l'infiltration, l'état d'humidité et les jours depuis la pluie,
  pour régler l'arrosage (`src/Web/ReleveSol.php`). Le Dossier Projet reprend le dernier de ces relevés.
- **L'étude du sol** (`conception-etude-sol.php`) présente les sept tests et dit à quoi sert chacun ; **la fiche vierge**
  (`releve-vierge.php`) s'imprime pour être remplie sur place. Ni l'une ni l'autre n'enregistre rien.
- **Personne n'écrit ni ne lit** la densité apparente, le slake, la structure, la semelle, l'arrêt des racines, la
  porosité, l'hydromorphie, l'odeur, les vers, les anéciques, les turricules ni la photo.
- **Trois champs de la fiche n'ont pas de colonne** : la texture (test du boudin), le drainage constaté et l'hydromorphie
  observée (seule sa profondeur, `hydromorphie_cm`, en a une). Le relevé de **flore** n'a aucune colonne : quatre carrés,
  des espèces et des points.
- Le commentaire de tête de `FicheTerrain` dit encore que la table n'existe pas : à corriger avec la Phase 1.

## Ce qui guide la conception

- **Le papier reste la voie du terrain.** `FicheTerrain` le dit : on remplit la grille accroupi au bord d'un trou, les
  mains terreuses, sans réseau. La saisie se fait **après**, depuis la fiche remplie. Elle reprend donc l'ordre et les
  libellés de la fiche, pour qu'on recopie sans chercher.
- **Dire à quoi sert le test au moment de la saisie** (le `sert` de chaque fiche), comme partout dans l'atelier.
- **Où l'on est, jamais quel chemin prendre** : la comparaison montre l'écart, elle ne prescrit pas un labour.
- **Le gabarit « le bureau », sans défilement** : une visite se saisit test par test, un onglet par test, comme la fiche
  se lit page par page.

## Décisions du porteur, 2026-10-05

1. **La place dans le parcours** : la saisie du point zéro dans **l'Étude du sol** (Conception) ; une nouvelle étape
   **« Le sol »** dans le Suivi pour les campagnes suivantes et la comparaison. Une seule saisie, deux entrées.
2. **Un relevé = une visite** : une date, un point, les conditions communes, et les tests faits ce jour-là.
3. **Les résultats seulement** : densité moyenne, vitesse d'infiltration stabilisée, vers par bloc… Les mesures brutes et
   le calcul restent sur la fiche.
4. **La campagne se déduit de la phase d'avancement du projet.** Lecture proposée, à confirmer : un relevé pris avant
   le début de la campagne de plantation (`parcelle.date_plantation`) est le **point zéro** ; après, il appartient à
   l'année écoulée depuis ce début (fin de première année, deuxième, troisième…). Sans date de plantation, tout relevé
   est un point zéro. La personne ne choisit rien.
5. **Pas de relevé de flore** pour l'instant : les plantes bio-indicatrices sont hors du champ de l'accompagnement. La
   fiche papier le garde, l'atelier ne le saisit pas.
6. **La photo se note par son nom de fichier** ; le fichier lui-même reste chez le porteur de projet. Aucun envoi.
7. **Pas de témoin cultivé.** Une zone conduite avec des intrants (engrais, fumier, pesticides) ne peut pas être
   imposée à la personne. **Le témoin est une zone sans culture, identifiée par la personne, sur laquelle on ne fait
   aucune action** : on la mesure, on n'y touche pas. Il garde la colonne `temoin` de la table, dans ce sens.

## Évaluer le système — décidé le 2026-10-05

Trois moyens, qui se complètent :

- **Le sol comparé à lui-même.** Le même point, du point zéro aux campagnes suivantes. MARVAL fait de la densité
  apparente « le meilleur candidat pour un avant/après » (`plan-marval-indicateurs.md` § 2.3), et `FicheTerrain` le
  précise : ce qui compte est **l'écart**, plus que la valeur absolue, car un sol argileux et un sol sableux ne partent
  pas du même endroit.
- **Le témoin, une zone sans culture et sans action**, choisie par la personne (décision 7), mesurée comme les autres
  points. La fiche des vers prévoit déjà une « référence locale : prairie voisine, bois… », et MARVAL situe un sol très
  poreux, de densité proche de 1, « en prairie et en forêt ».
- **Les repères chiffrés du corpus**, pour situer une mesure sans autre point : densité proche de 1 en prairie et en
  forêt, de 1,5 à 1,7 pour un sol tassé (MARVAL) ; les vers, l'indicateur qui change le plus vite, dès la fin de la
  première année. Les repères disent où l'on est, sans verdict.

**Le saut du chat** n'a plus de témoin non plus (le porteur, 2026-10-05) : son constat note trois signes, dont le sol
par rapport au départ — voir `chantier-saut-du-chat.md`. Quand les relevés existeront, ce signe pourra renvoyer à la
comparaison au point zéro.

## Les phases

**Phase 1 — La table complète.** Ajouter les colonnes manquantes (`texture`, `drainage`, `hydromorphie`) ; `temoin` garde
sa place, au sens de la décision 7, par une
migration additive (`information_schema` + `PREPARE`), et monter `Version::SCHEMA` dans le même commit ; rejouer
`bin/dev.py verifier-export`. Tenir `ReleveSol::recents()` aux seuls relevés d'arrosage : l'état d'humidité se note
pour **toute** mesure, et un relevé de densité ne doit pas devenir un relevé d'arrosage dans « Matériel et eau ».

**Phase 2 — La saisie.** Une page par parcelle : la liste de ses relevés, et un relevé qui s'ouvre test par test (un
onglet par fiche, sauf la flore ; les mêmes libellés, le `sert` en tête). Les conditions communes une seule fois (date,
point, témoin ou non, humidité, jours depuis la pluie). La case témoin dit ce qu'il est : une zone sans culture, où
l'on ne fait rien. La campagne s'affiche, déduite, sans se saisir. Modifier et retirer un relevé, avec confirmation. Garde-site et rôles comme ailleurs :
un lecteur lit, un contributeur écrit. `pret: true` dans `etapes.js` en même temps que la page.

**Phase 3 — La comparaison.** Pour chaque point, les campagnes côte à côte, du point zéro à la dernière : densité,
infiltration, slake, vers, profondeur des racines. L'écart au point zéro se lit, sans verdict, avec les repères du
corpus et le témoin à côté. Une mesure prise sur un sol
dans un autre état d'humidité est signalée : elle ne se compare pas.

**Phase 4 — Ce qui s'en sert.** Le Dossier Projet reprend la comparaison de chaque parcelle ; le livret « L'atelier »
décrit la page dans la même vague ; les mots nouveaux vont au glossaire.

## Ce qui reste hors du chantier

- L'interprétation automatique (« votre sol est compacté ») : l'atelier montre où l'on est, il ne conclut pas.
- Les analyses de laboratoire (pH, matière organique, CEC) : aucune fiche ne les porte aujourd'hui.
- Le relevé de flore et la bio-indication (décision 5).
- L'envoi et le stockage des photos (décision 6).
- Un témoin cultivé, conduit avec des intrants (décision 7).
