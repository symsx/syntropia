# Plan du site — l'arborescence telle qu'elle existe

Carte à jour des pages du site, phase par phase — pas le récit des
décisions qui y ont mené (`architecture-technique.md`) ni ce qui reste
à faire (`CLAUDE.md` § Point de reprise). Chaque page construite
s'ouvre sur le gabarit « le bureau » (voir CLAUDE.md § Architecture),
sans exception.

**Légende** : ✅ construite — ⏳ reportée (chantier à part, voir
`architecture-technique.md`) — ○ pas commencée.

## Partie publique — sans connexion

| Route | Rôle | État |
|---|---|---|
| `/` (`public/index.php`) | Présente l'atelier, oriente vers les documentations, la connexion, l'inscription. Servie à la racine, sans nom de fichier dans l'URL | ✅ |
| `documentation-site.php` | « L'atelier » — documentation du fonctionnement du site, livret en chapitres, texte dans `contenu/documentation-atelier.md` | ✅ |
| `documentation-saf.php` | « L'agroforesterie syntropique » — même format, texte dans `contenu/documentation-saf.md`, écrit par Sylvain (9 chapitres, 2026-09-19), qu'il enrichit à son rythme | ✅ |
| `glossaire.php` | Les mots techniques, en une phrase chacun — recherche filtrée, pas le livret qu'on tourne ; même source que la bulle au survol d'un mot souligné ailleurs sur le site, texte dans `contenu/glossaire.md` | ✅ |
| `autres-documentations.php` | Autres documentations — fournies par Sylvain | ○ |
| `connexion.php` | Connexion | ✅ |
| `inscription.php` | Enregistrement d'un nouvel utilisateur, sur invitation (voir `src/Web/Invitations.php`) | ✅ |
| `deconnexion.php` | Termine la session | ✅ |

## Partie connectée

| Route | Rôle | État |
|---|---|---|
| `compte.php` | Préférences : récapitule les informations de l'utilisateur et ses projets, change le nom affiché et le mot de passe. (Inviter et gérer les participants vivent dans `inviter.php`, `rejoindre.php` et `membres.php` — un compte porte plusieurs projets depuis le 2026-09-18.) | ✅ |
| `definition-projet.php` | Passage obligé, une seule fois, avant la première question de Définition : ouvre le site (nom du projet), condition pour que les réponses aient où s'enregistrer. Hors de la numérotation des étapes — ce n'est pas une étape qu'on retraverse | ✅ |

### Phase 1 — Définition

L'utilisateur décrit son projet.

| Étape | Route | Rôle | État |
|---|---|---|---|
| 1 | `definition-vision.php` | Qui est l'utilisateur — seul ou groupe ? Motivation, intention au-delà de soi | ✅ |
| 2 | `definition-objectifs.php` | Productions principales/secondaires, destinations (loisir, vivrier, commercial, social), terme (court ≈3 ans, moyen 5-10 ans, long >10 ans) | ✅ |
| 3 | `definition-moyens.php` | Connaissances, temps d'investissement, moyens financiers, terrain — oriente vers la RNA si les moyens financiers sont faibles | ✅ |
| 4 | `definition-site.php` | Trois onglets : le lieu (commune, position sur carte, superficie, zone climatique USDA estimée), les observations (exposition, pente, conditions climatiques particulières), la conduite du site (syntropie ou jardin-forêt) | ✅ |

**Une parcelle = un objectif**, avec production principale et secondaire.

### Phase 2 — Conception

Une ou plusieurs parcelles, dites « tortues » (Anaëlle Théry).

| Étape | Route | Rôle | État |
|---|---|---|---|
| 1 | `conception-etude-site.php` | La carte interactive du terrain : tracés par calques, fonds IGN, cadastre, eau OpenStreetMap, rose des vents, relief IGN, projection des ombres (liens ShadeMap), import GeoJSON, parcelles posées et repositionnées | ✅ (chantier `chantier-carte-interactive.md`, terminé le 2026-09-16) |
| 2 | `conception-etude-sol.php` + `releve-vierge.php` | Fiches de diagnostic du sol et de l'environnement, à remplir sur place et imprimables — contenu porté de `src/FicheTerrain.php` | ✅ |
| 3 | `conception-parcelles.php` | Les parcelles et leur plan : dimensions, bordures externe et interne, planches et passe-pieds, conduite, stade du terrain, type de tortue (classique, zèbre, abeille, liane) ; **les plantes des bordures** (une ligne par côté, espèces qui alternent, proposées d'après leur fonction, comptées), **les lignes de chaque planche**, planches pérennes et annuelles (zèbre) ou bandes (abeille) | ✅ (chantiers `chantier-plan-de-parcelle.md` et `chantier-peuplement-parcelle.md`) |
| 4 | `conception-scenario.php` | Scénario de plantation d'une parcelle pérenne — dimension temps (quoi, quand) et plan (quoi, où) ; frise temps × strates, saisie dans le panneau Données ; « ce scénario prépare-t-il le saut du chat ? » | ✅ |
| 4 | `conception-potager.php` | Le même pas pour une parcelle **potager** (`conception-scenario.php` y redirige) : les cultures par planche, les voisinages de Weinrich, le placement proposé (qui respecte les planches pérennes et annuelles), le calendrier avec les repères de Lopez et du référentiel, la succession, et **les plantes de service des lignes de chaque planche** | ✅ (chantiers `chantier-plan-de-culture.md` et `chantier-peuplement-parcelle.md`) |
| 5 | `conception-materiel-et-eau.php` | Récapitulatif du matériel végétal à se procurer, besoins en eau, stockage ou accès à l'eau | ✅ parcelle par parcelle ou tout le projet — matériel regroupé par origine (scénario, cultures du potager, plants des lignes du plan, qui font foi pour les bordures), besoin en eau, ressource, fréquence d'arrosage d'après les tests de sol |
| 6 | `conception-calendrier.php` | Calendrier du projet — quoi faire, quand, saison par saison | ✅ |

### Phase 3 — Réalisation

| Étape | Route | Rôle | État |
|---|---|---|---|
| 1 | `realisation-preparation-parcelle.php` | Préparation de la parcelle — déchaumer, couvrir/bâcher, manuellement ou par un ou plusieurs animaux | ✅ |
| 2 | `realisation-materiel-vegetal.php` | Préparation du matériel végétal — ce que vous multipliez (pieds-mères, graines), ce que vous procurez, ce qui est déjà utile sans rien faire | ✅ |
| 3 | `realisation-outils-et-plans.php` | Préparation des outils, notes de plan d'implantation, moyens humains | ✅ |
| 4 | `realisation-consignation.php` | Consignation de ce qui a été fait, quand, dans quelles conditions | ✅ |

### Phase 4 — Suivi

| Étape | Route | Rôle | État |
|---|---|---|---|
| 1 | `suivi-arrosage-germination.php` | Arrosage, prise des boutures, germination visible | ✅ |
| 2 | `suivi-perturbation.php` | Le journal des perturbations d'une parcelle — une ligne par geste, l'état des plantes, le temps écoulé depuis la dernière coupe | ✅ (chantier `chantier-saut-du-chat.md`) |
| 3 | `suivi-saut-du-chat.php` | Les constats du saut du chat (comparés à un témoin) et ce qui le rend possible (dix conditions lues dans le journal et le scénario) ; la date et la campagne de plantation | ✅ (chantier `chantier-saut-du-chat.md`) |
| 4 | `suivi-consignation.php` | Consignation de ce qui est observé, des résultats — dernière étape du parcours construit, redirige vers `conception-parcelles.php` faute de suite | ✅ |

## Hors du parcours — dans le menu du compte

| Route | Rôle | État |
|---|---|---|
| `inviter.php` | Ouvrir, lister, retirer des codes de parrainage — pour un compte seul, ou pour rejoindre le projet actif | ✅ |
| `rejoindre.php` | Rejoindre un projet avec un code, pour un compte existant | ✅ |
| `membres.php` | Les participants du projet actif, et le retrait d'un accès — réservée au porteur | ✅ |
| `exporter.php` | Exporter le projet en un fichier JSON daté — réservée au porteur | ✅ |
| `importer.php` | Importer un projet depuis un fichier d'export, en projet neuf | ✅ |
| `especes.php` | Le référentiel de végétaux : le cadre partagé et vos espèces (rôle, cycle, strates, espacement, calendrier), import CSV avec modèle téléchargeable | ✅ |

Les points d'entrée `api-*.php` (calques, cadastre, eau, altitude, zone USDA, projets) ne sont pas des pages : ils
répondent aux scripts des pages ci-dessus.

## Hors du site

- `le-bureau/index.html` — la maquette de référence, autonome, hors de `public/` (inatteignable une fois le site déployé, le rewrite `.htaccess` ne connaît que `public/`).
- `contenu/*.md` — le texte des deux livrets de documentation et du glossaire, lu par `documentation-site.php`/`documentation-saf.php`/`glossaire.php` (voir `src/Documentation.php`), jamais servi directement.
