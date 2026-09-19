# Manuel utilisateur de l'atelier — un document markdown illustré

**Demandé par le porteur le 2026-09-19** : « un manuel utilisateur de l'atelier syntropia
qui ne sera pas intégré à l'atelier mais fourni dans un document markdown. Ce manuel
devra intégrer des copies d'écran afin d'expliquer les fonctionnalités de l'atelier. »
**Planifié, pas commencé.**

## Ce que c'est — et ce que ce n'est pas

Quatre textes, quatre rôles :

| Texte | Où il vit | Dit |
|---|---|---|
| Panneau **Informations** de chaque page | dans l'atelier | le mode d'emploi **de cette page** |
| Livret **« L'atelier »** | dans l'atelier (`contenu/`) | **l'aperçu** : le parcours, ce qu'on trouve où |
| Livret **« SAF »** | dans l'atelier (`contenu/`) | les **principes** de l'agroforesterie syntropique |
| **Manuel utilisateur** (ce chantier) | **hors de l'atelier**, un document à donner | **comment faire**, pas à pas, **avec des copies d'écran** |

Le manuel est **détaillé et orienté tâches** (« comment poser une parcelle sur la
carte ? »), là où le livret « L'atelier » reste un aperçu. Il **ne recopie pas** le livret
SAF : il renvoie aux principes et montre l'outil. Il ne décrit que ce qui est **en
production** (même règle que le livret : rien de prévu, rien de « bientôt »).

**Livrable** : un dossier `manuel/` à la racine du dépôt, **non déployé** (`bin/deployer.py`
n'envoie que `public/`, `src/`, `contenu/`, `sql/`, `bin/`) :

```
manuel/
  manuel-utilisateur.md      le texte — un seul fichier, lisible tel quel
  images/                    les copies d'écran, PNG, nommées par ordre et par sujet
  README.md                  comment le lire, comment le régénérer
```

Les images sont référencées en **chemin relatif** (`![…](images/12-carte-parcelle.png)`), si
bien que le dossier se copie, s'envoie par courriel ou se convertit (PDF, HTML) sans rien
casser.

## Le point dur : des captures qui ne périment pas

L'atelier change souvent (trois vagues de déploiement en deux jours). Des captures faites à
la main deviendraient fausses en silence. La réponse : **les captures se régénèrent par un
script**, à partir d'un **projet de démonstration**, sur le site **local** — jamais la
production (règle du 2026-09-18, et le projet réel du porteur n'a pas à apparaître).

### Un projet de démonstration

- **Fictif** : un lieu public reconnaissable, un nom (« Le Verger de démonstration »), des
  réponses plausibles aux quatre étapes de Définition, **deux parcelles** dont une posée
  sur la carte avec ses planches, un scénario de quelques végétaux, un calque d'eau et de
  vents. Aucune donnée réelle, aucun courriel.
- **Trois comptes de démonstration** — un porteur, un contributeur, un lecteur — pour montrer
  les rôles côte à côte.
- **Semé par les services** (`Sites`, `Parcelles`, `Calques`, `Scenarios`), pas par du SQL :
  le semis échoue si une règle change, ce qui est exactement l'alerte voulue.
- **Sur la base locale seulement** : le script refuse de tourner si l'hôte n'est pas
  `127.0.0.1` (le piège de `config/base.php`, déjà consigné), et **nettoie** ses comptes et
  son projet à la fin.

### Un script de captures

`bin/manuel-captures.py` : Chrome headless piloté par le protocole DevTools (la méthode du
CLAUDE.md, Python pur, `websocket-client`), à partir d'un **manifeste** — pour chaque capture :
un identifiant, la page, la taille d'écran, les gestes préalables (cliquer un outil, ouvrir un
panneau, remplir un champ), le thème, et des **annotations**.

- **Annotations** : des pastilles numérotées ① ② ③ posées **par le script** sur les éléments
  (sélecteur CSS) avant la capture, que le texte du manuel explique (« ① la barre d'outils,
  ② le panneau Données »). Elles sont injectées dans la page, pas dessinées à la main : elles
  suivent l'interface quand elle change.
- **Deux formats** : ordinateur (1280×720) pour presque tout, téléphone (412×800) pour la
  section « Sur téléphone » ; un thème sombre pour une seule capture, en exemple.
- **Contrôles** : la capture échoue si un élément visé est introuvable, si la page a un
  message d'erreur inattendu, ou si le contenu déborde (la mesure de conteneur du CLAUDE.md,
  pas seulement `scrollHeight`).
- **Réseau** : la carte charge de vraies tuiles (OpenStreetMap, IGN), donc l'aspect du fond
  dépend d'un tiers ; le script attend que les tuiles soient chargées avant de capturer et
  signale une capture en échec de fond plutôt que de garder une carte blanche.
- **Reproductible** : `python3 bin/manuel-captures.py` régénère tout ; `--seulement ID`
  refait une image.

## Plan du manuel (à valider)

Une trentaine de sections, une quarantaine de captures. L'ordre suit ce que fait une
personne, non l'arborescence du code :

1. **Bienvenue** — à quoi sert l'atelier, à qui s'adresse ce manuel, comment le lire ; la note
   « aide à la conception, ne remplace pas une formation ».
2. **Premiers pas** — se connecter, s'inscrire avec un code, le bureau (bandeau, menu ☰,
   panneaux Données et Informations, thème). *Captures : connexion, bureau annoté.*
3. **Ouvrir un projet** — nommer son projet ; les projets multiples et le sélecteur.
4. **Définition** — vision, objectifs, moyens, site (les trois onglets, la carte de position,
   l'estimation de la zone climatique, la conduite).
5. **Conception : la carte du terrain** — *la plus grosse section.* Les calques et leur
   choix ; tracer un point, une ligne, un contour, à main levée ; poser une parcelle et la
   **repositionner** ; les étiquettes ; les fonds de carte ; cadastre, eau, vents, relief,
   ombres, import GeoJSON ; lire la carte en tant que lecteur.
6. **Conception : le sol** — les sept relevés et les fiches à imprimer.
7. **Conception : les parcelles** — créer, dimensionner, bordures externe et interne,
   planches, sens et rattrapage, conduite de la parcelle ; ce que le plan calcule.
8. **Conception : le scénario** — la frise, ajouter un végétal, les productions, jardin-forêt.
9. **Conception : le calendrier.**
10. **Réalisation** — préparation, matériel végétal, outils et plans, consignation.
11. **Suivi** — arrosage et germination, perturbation, saut du chat, consignation.
12. **Travailler à plusieurs** — les trois rôles, inviter, rejoindre, participants, retirer un
    accès. *Captures côte à côte : porteur / lecteur.*
13. **Mon compte** — Préférences : récapitulatif, nom affiché, mot de passe.
14. **Sur téléphone** — ce qui change (barre d'outils en menu, panneaux). *Captures 412×800.*
15. **Les documentations** — les deux livrets, le glossaire, les mots soulignés.
16. **Si quelque chose ne va pas** — les messages de l'atelier et ce qu'ils veulent dire
    (carte sans fond, cadastre « zoomez », service météo occupé, conflit de version d'un
    plan…), tirés des **vrais messages du code**, pas inventés.

Chaque section garde la même forme, pour que le manuel se consulte plus qu'il ne se lit :
**À quoi ça sert** (une phrase, avec l'usage plus loin — la règle « une donnée sert une
étape plus loin ») · **Pas à pas** (numéroté) · **La capture** · **À savoir** (un piège, une
limite, un renvoi au livret SAF).

## Phases

### Phase 1 — le plan détaillé et la liste des captures
Valider avec le porteur le plan ci-dessus ; en tirer la **liste des captures** (identifiant,
page, gestes, annotations) — c'est le manifeste du script et le sommaire des images. Vérifier
que **le code local est celui de la production** (`rsync -c`) avant de le photographier.

### Phase 2 — l'outillage
Le projet de démonstration (semis et nettoyage), le script de captures (manifeste, gestes,
annotations, contrôles), `manuel/README.md`. **Testé** : deux exécutions de suite donnent le
même résultat, un élément introuvable fait échouer la capture, la base est propre après.

### Phase 3 — les captures
Exécution, puis **relecture de chaque image** (lisibilité à la taille du manuel, pas de donnée
parasite, annotations bien placées). Poids : viser 100–200 Ko par PNG, une quarantaine
d'images, soit moins de 10 Mo — la taille de `.git` (6,7 Mo) le supporte, mais on l'a en tête.

### Phase 4 — la rédaction
Le texte, section par section, contre l'atelier **tel qu'il tourne** (les libellés exacts
des boutons, pas de mémoire) ; vouvoiement ; orthographe (`aspell -l fr`, comme pour le
livret) ; les messages d'erreur copiés du code.

### Phase 5 — vérification et remise
- Chaque image existe, est référencée, et chaque référence existe (script de contrôle des
  liens).
- Rendu vérifié (un aperçu markdown) ; export PDF proposé si le porteur le souhaite.
- **Relecture par le porteur.**
- **Version** en tête du manuel : la date et le commit sur lesquels il a été produit — un
  manuel sans date ne dit pas de quoi il est le manuel.

### Phase 6 (facultative) — le tenir à jour
Le manuel **n'est pas** couvert par la règle de maintenance du livret « L'atelier » (elle vit
dans l'atelier, à chaque évolution) : un manuel s'édite par **versions**. Proposition : le
régénérer **à chaque jalon** décidé par le porteur (fin de la première version fonctionnelle,
chantier majeur), et **étendre `bin/verifier-documentation.py`** pour dire, à ce moment-là,
quelles sections du manuel touchent des fichiers modifiés depuis la version précédente.

## Ce que ce chantier ne fait pas

- Il **n'entre pas dans l'atelier** : pas de page, pas de lien, rien déployé.
- Il **ne documente pas ce qui n'existe pas** : l'étape « Matériel et eau », l'export/import et
  la suppression d'un projet n'y figurent pas tant qu'ils ne sont pas en production.
- Il **ne montre jamais le projet réel** du porteur ni de compte réel.
- Il **ne traduit pas** (français seulement).

## Décisions à confirmer par le porteur

1. **Niveau de détail** : « comment faire », pas à pas, avec les pièges — plus complet que le
   livret (un aperçu). D'accord ?
2. **Emplacement** : `manuel/` à la racine, **non déployé**, **versionné dans git** avec ses images
   (moins de 10 Mo) — ou hors du dépôt ?
3. **Projet de démonstration fictif**, sur un lieu public que je choisis (vous pouvez en
   préférer un autre) — d'accord ?
4. **Annotations numérotées** sur les captures (pastilles ①②③ expliquées dans le texte) — oui ?
5. **Formats de sortie** : markdown seul, ou aussi un PDF ? (Le PDF se produit après coup, à
   partir du markdown et des images.)
6. **Quand ?** Après la mise à jour du livret « L'atelier » (leur vocabulaire est commun), et de
   préférence quand le porteur juge l'atelier assez stable pour qu'un manuel ne périme pas
   aussitôt.

## Fichiers critiques

- `bin/manuel-captures.py`, `manuel/manifeste` *(à créer)* — le script et la liste des captures.
- `src/Web/Sites.php`, `Parcelles.php`, `Calques.php`, `Scenarios.php` — les services par lesquels
  se sème le projet de démonstration (pas de SQL direct).
- `public/assets/js/etapes.js`, `chantier-documentation-atelier.md` § Annexe — l'inventaire
  des pages, point de départ de la liste des captures.
- `contenu/documentation-atelier.md` — le vocabulaire commun ; le manuel ne le contredit pas.
- `bin/verifier-documentation.py` — étendu en Phase 6.
