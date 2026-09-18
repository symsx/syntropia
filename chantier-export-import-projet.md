# Exporter et importer un projet — plan

**Demandé par le porteur le 2026-09-18. Rien n'est construit : ce document
note l'intention et propose un format, pour en décider avant de coder.**

## Pourquoi

- **Sauvegarder** un projet et le **redonner** : un projet vit des mois, et
  rien ne permet aujourd'hui d'en garder une copie datée, ni de revenir à un
  état antérieur.
- **Passer un projet d'une instance à l'autre** — poste local ↔ production.
  Le 2026-09-18, le projet réel a été rapatrié de la production vers la base
  locale par un script PHP ponctuel, en PDO direct vers les deux bases, « non
  conservé, ce n'est pas un outil du dépôt » (CLAUDE.md). C'est exactement ce
  besoin, fait à la main et sans filet.
- **Partager un projet exemple**, ou le confier à quelqu'un qui n'a pas de
  compte ici.

## Le format proposé : un fichier JSON, pas du XML

`nom-du-projet_2026-09-18.syntropia.json` — UTF-8, un seul fichier.

Pourquoi JSON plutôt que XML :
- **C'est déjà ce que l'application stocke** : `parcelle.plan`,
  `calque_element.proprietes`, `scenario_ligne.details`, la géométrie des
  tracés sont des JSON dans des colonnes texte. En XML, chacun deviendrait un
  texte échappé à l'intérieur d'un élément — un format dans un format.
- PHP le lit et l'écrit sans extension ni bibliothèque (`json_decode`,
  `json_encode`), comme le reste du dépôt qui n'a aucune dépendance.
- Ce que XML apporterait (un schéma XSD, des commentaires) ne compense pas sa
  verbosité ; la validation se fait ici en PHP, champ par champ, de toute façon.

Un **complément facultatif** : les tracés de la carte en **GeoJSON**
(`.geojson`), lisible par QGIS, ContourBuddy et le formulaire d'import de la
carte — le lecteur (`Geojson::lire()`) existe déjà, il resterait l'écriture.
Ce n'est pas la sauvegarde : elle ne porte que la géométrie.

### Ce que le fichier contient

```json
{
  "format": "syntropia-projet",
  "version": 1,
  "exporte_le": "2026-09-18T21:30:00Z",
  "application": { "commit": "…", "schema": "…" },
  "projet": { "nom": "…", "latitude": 45.0, "longitude": 1.0, "conduite": "syntropique" },
  "participants": [ { "ref": "p1", "nom": "…", "role_projet": "…" } ],
  "reponses": [ { "participant": "p1", "phase": "definition", "question": "…", "texte": "…" } ],
  "parcelles": [ { "ref": "a", "code": "P1", "nom": "…", "plan": { }, "scenario": [ ] } ],
  "calques": [ { "calque": "eau", "type": "polygone", "libelle": "…", "geometrie": [ ], "proprietes": { } } ],
  "especes_locales": [ ]
}
```

- **`exporte_le`** : la date d'export, en ISO 8601 UTC — demandée. Elle
  s'affiche à l'export (« exporté le… »), à l'import (avant de valider : « ce
  fichier date du… ») et dans le nom du fichier.
- **`version`** : un entier. Un fichier plus récent que l'application est
  refusé avec son motif ; un plus ancien passe par des étapes de migration.
- **`application.commit`/`schema`** : avec quelle version il a été écrit —
  pour comprendre plus tard un fichier qui ne s'importe plus.
- **Des références de fichier** (`ref`) à la place des identifiants de base :
  les numéros n'ont de sens que dans la base d'origine.
- **Ce que porte un projet** (les tables reliées à `site` ou `parcelle`) :
  `site`, `parcelle`, `participant`, `reponse`, `calque_element`,
  `scenario_ligne`, `succession_ligne`, `releve_sol`, `intervention`,
  `celebration`, `reconnaissance`, `commande`, `gisement`, `ressource`,
  `synthese`, et les **surcharges d'espèces du site** (`espece` où
  `site_id` ≠ 0 ; le cadre partagé n'est pas exporté, il est déjà là).

### Ce que le fichier ne contient jamais

- **Aucun compte** : ni identifiant, ni courriel, ni empreinte de mot de
  passe. Les participants sortent avec leur **nom seulement**, et rentrent
  comme des personnes **sans compte** (le schéma le prévoit : `participant.
  compte_id` peut être nul).
- **Aucun accès** (`membre_site`) ni invitation : qui a le droit de voir le
  projet se décide à l'arrivée, pas dans le fichier.
- Ce qui se **recalcule** au chargement (les courbes de niveau d'un relevé
  d'altitude, par exemple) : seules les altitudes sont gardées.

## L'import

- **Il crée toujours un projet neuf** — jamais une fusion, jamais un
  écrasement. Celui qui importe en devient le porteur. La fusion de deux états
  d'un même projet est un autre problème, qu'on ne résout pas ici.
- **Un fichier est une entrée non fiable**, comme un GeoJSON : taille
  plafonnée, profondeur JSON limitée, chaque champ validé (type, longueur de
  colonne, énumérations) avant la moindre écriture, aucune désérialisation
  d'objet. Tout dans **une transaction** : un import qui échoue ne laisse rien.
- **Un aperçu avant d'écrire** : nom du projet, date d'export, ce que le
  fichier contient (nombre de parcelles, de tracés, de végétaux, de
  participants) et ce qu'il ne contient pas (comptes, accès).
- Les identifiants sont remappés ; le `code` du site est recalculé.

## Phases envisagées

```
Phase 1  Exporter — bouton pour le porteur, téléchargement du .syntropia.json daté
Phase 2  Importer — aperçu, validation, création du projet dans une transaction
Phase 3  Aller-retour — export → import → export : identique aux identifiants et aux dates près
Phase 4  (facultatif) Les tracés en GeoJSON, pour QGIS et les autres outils
```

## À décider avant de coder

1. **Qui peut exporter ?** Proposition : le **porteur seul** — le fichier porte
   les réponses de **tous** les participants, pas seulement les siennes.
2. **Que devient la date d'export à l'écran ?** Proposition minimale : dans le
   fichier et à l'aperçu d'import. Un « dernier export : … » sur le projet
   demanderait une colonne — à trancher.
3. **Garder la trace d'origine d'un projet importé** (« importé de… le… ») :
   où, sans migration ? (dans les réponses, dans une note, ou une colonne).
4. **Les plafonds** : la limite d'envoi de PHP est de 2 Mo en local ; un projet
   riche en relevés d'altitude peut la dépasser — l'export doit rester sous la
   limite de l'import, ou la limite se relève.
5. **La conduite du site** et les surcharges d'espèces : à reprendre telles
   quelles, ou à revalider à l'arrivée ?

## Fichiers critiques

- `sql/01-syntropia.sql` — la liste des tables et leurs liens ; **tout ajout de
  colonne ou de table doit être répercuté dans l'export**, sans quoi une
  sauvegarde perdrait des données en silence.
- `src/Geojson.php` — le lecteur de GeoJSON, modèle de lecture d'un fichier non
  fiable.
- `src/Web/Sites.php`, `Parcelles.php`, `Calques.php`, `Scenarios.php` — les
  services par lesquels l'import doit écrire, pour ne pas contourner leurs
  validations.
