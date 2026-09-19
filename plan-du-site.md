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
| `documentation-saf.php` | « L'agroforesterie syntropique » — même format, texte dans `contenu/documentation-saf.md` | ✅ (contenu : modèle, à remplacer par Sylvain) |
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
| 4 | `definition-site.php` | Le lieu (commune, position sur carte, superficie, zone climatique USDA, exposition/pente) et ce que vous observez (conditions climatiques particulières) — deux groupes distincts sur la page | ✅ |

**Une parcelle = un objectif**, avec production principale et secondaire.

### Phase 2 — Conception

Une ou plusieurs parcelles, dites « tortues » (Anaëlle Théry).

| Étape | Route | Rôle | État |
|---|---|---|---|
| 1 | `conception-etude-site.php` | Cartographie, topologie, altitude, projections d'ombre | ⏳ carte interactive, chantier séparé |
| 2 | `conception-etude-sol.php` + `releve-vierge.php` | Fiches de diagnostic du sol et de l'environnement, à remplir sur place et imprimables — contenu porté de `src/FicheTerrain.php` | ✅ |
| 3 | `conception-parcelles.php` | Création des parcelles — code, nom, type, réels et en liste ; les trois mesures pour la carte (planches, passe-pied) | ✅ (le plan lui-même — bordures, planches dessinées — reste un prototype non persisté) |
| 4 | `conception-scenario.php` | Scénario de plantation — dimension temps (quoi, quand) et plan (quoi, où) ; si potager ou maraîchage, propose des associations culturales | ✅ parcelle par parcelle — frise temps × strates, saisie dans le panneau Données |
| 5 | `conception-materiel-et-eau.php` | Récapitulatif du matériel végétal à se procurer, besoins en eau, stockage ou accès à l'eau | ✅ parcelle par parcelle ou tout le projet — matériel regroupé par origine, besoin en eau, ressource, fréquence d'arrosage d'après les tests de sol |
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
| 2 | `suivi-perturbation.php` | Perturbation — quand, quoi, comment tailler | ✅ |
| 3 | `suivi-saut-du-chat.php` | Constatation du saut du chat ; sinon, suggestion pour rattraper — planter plus, selon la période | ✅ |
| 4 | `suivi-consignation.php` | Consignation de ce qui est observé, des résultats — dernière étape du parcours construit, redirige vers `conception-parcelles.php` faute de suite | ✅ |

## Hors du parcours

- `le-bureau/index.html` — la maquette de référence, autonome, hors de `public/` (inatteignable une fois le site déployé, le rewrite `.htaccess` ne connaît que `public/`).
- `contenu/*.md` — le texte des deux livrets de documentation et du glossaire, lu par `documentation-site.php`/`documentation-saf.php`/`glossaire.php` (voir `src/Documentation.php`), jamais servi directement.
