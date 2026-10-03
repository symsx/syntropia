# Les textes de l'atelier — réécriture dans le style du porteur

**Demandé par le porteur le 2026-10-03 ; en cours.** Vagues 1 et 2 faites et commitées le 2026-10-03, vagues 3, 4 et 5 écrites le même jour. Ce chantier passe avant la Phase 3 du Dossier
Projet (décision du porteur, le même jour).

## La demande

Le porteur a corrigé une présentation de l'atelier dans son style (`contenu/presentation-utilisateurs.html`). Les
textes de l'atelier sont réécrits selon les mêmes règles. Ces règles sont notées dans la mémoire de Claude
(`style-ecriture-du-porteur`) et rappelées ici.

## Les règles

- **Des titres simples**, avec un article, qui nomment la chose : « Le bureau », « La définition du projet ».
- **Des phrases complètes, avec un verbe.** Pas d'enchaînements de fragments séparés par des tirets ou des
  deux-points.
- **Pas de formule toute faite** (« Une place à imaginer », « Cette étape ne juge rien »).
- **Pas de vocabulaire inventé.** Les mots et les définitions du porteur sont repris.
- **Définir directement**, par ce que sont les choses. Pas de définition par la négation (« n'est pas un
  accident »).
- **Un ton sobre et factuel**, sans promesse ni familiarité. Les limites sont dites franchement.
- **Le concret et les chiffres** : ce que fait l'outil, avec ses taux et ses nombres.
- **Les sources sont nommées.**
- **Dire à quoi sert une information** au moment où elle est demandée.

## Le périmètre

| Ensemble | Volume au 2026-10-03 |
|---|---|
| Les pages de l'atelier (étapes, panneau Informations, panneau Données, messages, bulles) | ~19 000 mots, 40 pages |
| Les textes produits par le code (`src/` : libellés et explications de `Stade`, `Tortue`, `Conduite`, `Scenario`…, messages d'erreur ; textes des scripts) | ~150 phrases longues, et les messages courts |
| Le livret « L'atelier » (`contenu/documentation-atelier.md`) | ~5 900 mots |
| Le livret « Les sources » (`contenu/sources-de-l-atelier.md`) | ~2 000 mots |
| Le glossaire (`contenu/glossaire.md`) | ~740 mots |

**Hors du chantier** : le livret SAF et le chapitre « L'intention » du livret « L'atelier », écrits par le porteur ;
les commentaires du code ; la mention « Syntropia — en construction » du pied de page, que le porteur garde.

## Ce qui ne change pas

- **Le sens.** Une phrase qui porte une règle sourcée (Théry, Joala, Lopez, Weinrich) ou une décision du porteur
  garde sa règle et sa source. Quand une phrase demande un choix de fond plutôt que de forme, la question va au
  porteur.
- **La place.** Le bureau ne défile pas. Un texte réécrit tient dans le même espace, à 320 × 568 comme sur grand
  écran.
- **Les livrets** suivent leur format : un chapitre par ligne `# `, des paragraphes de 300 caractères au plus (220
  pour tenir à 320 × 568).
- **Le livret « L'atelier »** reste à jour avec ce qui est en production : un libellé de bouton ou d'étape qui
  change se change aussi dans le livret, dans la même vague.

## Les vagues

Une vague par ensemble cohérent, dans l'ordre du parcours. Chaque vague se termine par une vérification sur ce poste
(capture d'écran à 320 × 568 et sur grand écran, pages qui tiennent sans défiler, syntaxe PHP), un commit, puis le
déploiement sur accord du porteur. **Le porteur relit directement les pages**, sans relevé avant / après.

1. **La Définition et les pages publiques** : accueil, connexion, inscription, `definition-projet`, vision,
   objectifs, moyens, site. C'est ce qu'un nouvel utilisateur voit d'abord.
2. **La Conception** : étude du site, étude du sol, parcelles, scénario, potager, matériel et eau, calendrier, et
   les textes de `src/` qu'elles affichent.
3. **La Réalisation et le Suivi** : les quatre étapes de chaque phase, le journal des perturbations, le saut du
   chat.
4. **Les comptes et le partage** : compte, inviter, rejoindre, membres, exporter, importer, espèces, journal des
   connexions, Dossier Projet, relevé vierge.
5. **Les livrets et le glossaire** : « L'atelier » (hors « L'intention »), « Les sources », le glossaire.

## Avant de commencer chaque vague

- Relire la présentation corrigée par le porteur et ses propres textes (`directives.txt`, `INTENTION.md`,
  « L'intention »), pour reprendre son vocabulaire.
- Relever les libellés qui changent (boutons, titres d'étape, entrées de menu) : ce sont eux que le livret et le
  glossaire doivent suivre.

## Une règle ajoutée en cours de route

**Rien d'inventé.** Le porteur a fait retirer de l'accueil une phrase qui ne venait ni de lui ni d'une source (2026-10-03). Un
texte écrit auparavant par Claude n'est pas une source à garder. Ce qui ne vient ni du porteur, ni d'une source citée, ni du
fonctionnement réel de l'atelier est retiré, ou soumis au porteur (§ Questions au porteur).

## L'avancement

- **Vague 1** (2026-10-03, commitée) : accueil, connexion, inscription, création de projet, vision, objectifs, moyens, site, et les
  textes de `Conduite`, `ZoneUsda`. Deux affirmations fausses retirées : un projet ne se renomme nulle part.
- **Vague 2** (2026-10-03, écrite, à relire par le porteur) : étude du site (et `Calques`, `Relief`, `ShadeMap`, `Vents`, `Geojson`,
  `Eau`, `Cadastre`, `Altimetrie`, `calques.js`, `carte.js`), étude du sol, parcelles (et `Stade`, `Tortue`, `Bordures`,
  `RepartitionBordures`, `conception-parcelles.js`), scénario (et `SautDuChat::conception()`, `Scenario`, `strates.js`), potager (et
  `PlanDeCulture`, `LignesDeService`, `Cultures`), matériel et eau (et `Materiel`, `BesoinEau`), calendrier. Les titres des repères du
  saut du chat et les origines du matériel reprennent ceux de la présentation du porteur ; le livret suit pour les origines. Commitée après relecture du porteur.
- **Vague 3** (2026-10-03, écrite, à relire par le porteur) : les quatre étapes de la Réalisation, l'arrosage et la germination, la
  perturbation (et `Interventions`), le saut du chat (et `SautDuChat::conditions()`, `Constats`), la consignation du Suivi. Le saut du
  chat se définit par la phrase de la présentation du porteur ; « le film » devient « le scénario ». La consignation du Suivi dit
  franchement qu'elle ne garde qu'une observation.
- **Vague 4** (2026-10-03, écrite, à relire par le porteur) : compte, rejoindre, inviter, membres, exporter, importer, espèces, journal
  des connexions, glossaire, panneaux des livrets, Dossier Projet, relevé vierge (et `FicheTerrain`), et les textes partagés de
  `gabarit.js`. Les rôles reprennent les définitions de la présentation du porteur (le contributeur répond aux questions et dessine
  avec le porteur, le lecteur consulte sans modifier). Trois faits corrigés : le cadre compte 143 espèces et non 46 ; la hauteur
  adulte ne sert pas encore à l'ombre portée ; le relevé vierge renvoyait à un « carnet du site » qui n'existe pas.
- **Vague 5** (2026-10-03, écrite, à relire par le porteur) : le livret « L'atelier » réécrit à partir de « Où trouver quoi » (les
  trois premiers chapitres, dans la voix du porteur, restent tels quels), le livret « Les sources », le glossaire. Le chapitre
  « Concevoir : les parcelles et le scénario » (70 sous-pages à 320 × 568) est découpé en chapitres courts ; le livret passe à 21
  chapitres, 18 sous-pages au plus, aucun débordement à 320 × 568 (mesuré). Le Dossier Projet a son chapitre, avec la Phase 2.
  Les titres du glossaire ne changent pas : les identifiants des bulles en dépendent (vérifié, les 15 mots soulignés résolus).

## Questions au porteur

Des contenus qui ne venaient ni du porteur ni d'une source. **Réponse du porteur le 2026-10-03 : les retirer tous.**

1. **Les conseils de pente** (`Relief::ceQuElleCommande()`, étude du site) : retirés, la méthode avec. La pente, son exposition et
   son dénivelé restent affichés.
2. **Les seuils de trois et six heures de soleil** (liens ShadeMap) : retirés.
3. **« Prévoyez un mètre ruban et dix minutes sur place »** (parcelles) : retiré.
4. **Les doses par passage de 12, 18 ou 25 mm** (`BesoinEau::DOSES`) : elles calculent le nombre de passages par semaine et la
   quantité par passage du conseil d'arrosage. Le porteur a choisi de retirer le calcul seul : la page garde les millimètres par
   semaine (Lopez), la classe d'infiltration (FAO), le ressuyage et la règle sourcée (sol rapide, passages courts et fréquents ;
   sol tampon, long et espacé). Le livret suit. **La présentation du porteur décrit encore « nombre de passages par semaine,
   quantité en millimètres et durée »** : à corriger à la Phase 1 de `chantier-presentation.md`.
