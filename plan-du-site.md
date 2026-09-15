# Plan du site — arborescence des pages

Traduction de `directives.txt` en pages. Noms de route proposés,
minuscules et tirets, sur le modèle du dépôt de référence (`public/*.php`
= une route) — à ajuster si le porteur préfère d'autres intitulés.
Chaque page s'ouvre sur le gabarit « le bureau » (voir CLAUDE.md §
Architecture) une fois construite.

## Partie publique — sans connexion

| Page | Rôle |
|---|---|
| `accueil` — construite en `public/index.php`, pas `accueil.php` | Présente l'atelier, oriente vers les documentations, la connexion, l'inscription. Servie à la racine (`/`), sans nom de fichier dans l'URL — voir `architecture-technique.md` § Arborescence et .htaccess |
| `documentation-site` — construite en `public/documentation-site.php` | « L'atelier » — documentation du fonctionnement du site, livret en chapitres, texte dans `contenu/documentation-atelier.md` |
| `documentation-saf` — construite en `public/documentation-saf.php` | « L'agroforesterie syntropique » — même format, texte dans `contenu/documentation-saf.md` (encore un modèle, à remplacer par Sylvain) |
| `autres-documentations` | Autres documentations — fournies par Sylvain |
| `connexion` | Connexion |
| `inscription` — construite en `public/inscription.php` | Enregistrement d'un nouvel utilisateur, sur invitation (voir `src/Web/Invitations.php`) |

## Partie connectée

| Page | Rôle |
|---|---|
| `compte` | Récapitule les informations de l'utilisateur, invite d'autres personnes à rejoindre son projet — un projet par utilisateur, un site, *n* parcelles |

### Phase 1 — Définition

L'utilisateur décrit son projet. Passage obligé, une seule fois, avant
la première question : `definition-projet.php` — ouvre le site (nom
du projet), condition pour que les réponses aient où s'enregistrer ;
voir `architecture-technique.md` § La persistance réelle. Hors de la
numérotation ci-dessous : ce n'est pas une étape qu'on retraverse.

| Étape | Page | Rôle |
|---|---|---|
| 1 | `definition-vision` | Qui est l'utilisateur — seul ou groupe ? Motivation, intention au-delà de soi |
| 2 | `definition-objectifs` | Productions principales/secondaires, destinations (loisir, vivrier, commercial, social), terme (court ≈3 ans, moyen 5-10 ans, long >10 ans) |
| 3 | `definition-moyens` | Connaissances, temps d'investissement, moyens financiers, terrain — orienter vers la RNA si les moyens financiers sont faibles |
| 4 | `definition-site` — construite en `public/definition-site.php` | Commune, position, superficie, zone climatique USDA, exposition/pente, conditions climatiques particulières |

**Une parcelle = un objectif**, avec production principale et secondaire.
Cette étape recense aussi ce qui est déjà en présence : pieds-mères pour
le bouturage, graines à récolter, végétaux utiles au projet.

### Phase 2 — Conception

Une ou plusieurs parcelles, dites « tortues » (Anaëlle Théry).

| Étape | Page | Rôle |
|---|---|---|
| 1 | `conception-etude-site` | Cartographie, topologie, altitude, projections d'ombre — *déjà présent dans le dépôt de référence (`carte.php`), chantier séparé, reporté le 2026-09-14 : voir architecture-technique.md* |
| 2 | `conception-etude-sol` — construite en `public/conception-etude-sol.php` + `public/releve-vierge.php` | Fiches de diagnostic du sol et de l'environnement, à remplir sur place — contenu porté de `src/FicheTerrain.php`, imprimable ; voir `diagnostic-des-sols` |
| 3 | `conception-parcelles` — construite en `public/conception-parcelles.php` | Création des parcelles — forme et placement sur le site |
| 4 | `conception-scenario` | Scénario de plantation — dimension temps (quoi, quand) et plan (quoi, où) ; si potager ou maraîchage, propose des associations culturales (`cultures-associees`, `maraichage-naturel`) — *parcelle par parcelle (voir `scenario.php` du dépôt de référence), chantier séparé, reporté le 2026-09-15 : voir architecture-technique.md* |
| 5 | `conception-materiel-et-eau` | Récapitulatif du matériel végétal à se procurer, dimensionnement des besoins en eau, prévision de stockage ou d'accès à l'eau — *« pour chaque parcelle » (directives.txt), même report que l'étape 4* |
| 6 | `conception-calendrier` — construite en `public/conception-calendrier.php` | Calendrier du projet — quoi faire, quand |

### Phase 3 — Réalisation

| Étape | Page | Rôle |
|---|---|---|
| 1 | `realisation-preparation-parcelle` | Préparation de la parcelle — déchaumer, couvrir/bâcher, manuellement ou par un ou plusieurs animaux |
| 2 | `realisation-materiel-vegetal` | Préparation du matériel végétal — conseils de multiplication |
| 3 | `realisation-outils-et-plans` | Préparation des outils, édition des plans d'implantation pour les personnes du chantier, moyens humains |
| 4 | `realisation-consignation` | Consignation de ce qui a été fait, quand, dans quelles conditions |

Les quatre étapes ci-dessus sont construites (`public/realisation-*.php`).

### Phase 4 — Suivi

| Étape | Page | Rôle |
|---|---|---|
| 1 | `suivi-arrosage-germination` | Arrosage, prise des boutures, germination visible |
| 2 | `suivi-perturbation` | Perturbation — quand, quoi, comment tailler |
| 3 | `suivi-saut-du-chat` | Constatation du saut du chat ; sinon, conseils pour rattraper — planter plus, selon la période |
| 4 | `suivi-consignation` | Consignation de ce qui est observé, des résultats — dernière étape du parcours construit, redirige vers `conception-parcelles.php` |

Les quatre étapes ci-dessus sont construites (`public/suivi-*.php`).

## Ouvert

- **Le bandeau général de `directives.txt`** (hamburger, logo, bouton
  « étape précédente », titre contextuel, bouton « étape suivante ») ne
  cite pas exactement les mêmes éléments que le bandeau général déjà
  validé dans `le-bureau/` (hamburger, logo, titre, thème, compte — voir
  CLAUDE.md § Architecture). Navigation précédente/suivante à situer :
  dans le bandeau général, ou déportée dans le pied de page / le panneau
  gauche ? `[ouvert]`
- Noms de route à confirmer avec le porteur avant de créer les fichiers
  réels.
