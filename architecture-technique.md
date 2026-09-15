# Architecture technique — le framework de l'atelier

*Validée et construite le 2026-09-13. Répond à la demande de
`directives.txt` : « des composants mutualisés et réutilisables pour les
différentes étapes ».*

## Le constat, en regardant `le-bureau/index.html`

La maquette contient déjà, mélangés dans un seul fichier, deux choses de
nature différente :

| Ce qui est **fixe**, quelle que soit l'étape | Ce qui **varie** d'une étape à l'autre |
|---|---|
| La coquille : bandeau général, bandeau d'outils (structure), les trois panneaux, la bulle, le pied | Le titre contextuel, l'étape précédente/suivante |
| Le CSS des tokens, de la grille, des tirettes, des cases à cocher | La phase active dans « Le voyage », les pages de l'élément en cours |
| Le JS des tiroirs (ouverture/fermeture, Échap), du thème, du menu d'outils responsive, du positionnement générique de la bulle | La liste des outils du bandeau, et lequel est actif |
| — | **Le contenu du bureau lui-même** — le cœur métier de l'étape (ici : la géométrie d'une parcelle) |
| — | Les champs du panneau Données, et ce qu'ils calculent |
| — | Le texte du panneau Informations |

**Le framework, c'est isoler la première colonne une bonne fois, et
donner à chaque étape un moyen simple de ne fournir que la seconde.**

## Proposition : un gabarit chargé une fois, une étape = une config + un contenu

```
public/
  assets/
    css/gabarit.css       coquille — tokens, grille, panneaux, tirettes, cases
    js/gabarit.js          coquille — tiroirs, thème, menu outils, bulle générique
  <etape>.html              une page = un fichier, sur le modèle public/*.php
                            du dépôt de référence (une route = un fichier)
composants/                 briques plus petites que la coquille, réutilisées
  ...                       par plusieurs étapes sans être le gabarit lui-même
```

**`gabarit.js` expose une seule fonction**, `Gabarit.monter(config)`, qui
construit toute la coquille à partir d'un objet :

```js
Gabarit.monter({
  site: "La Vadaux",
  titre: "Créer les parcelles",
  phase: "conception",              // définition | conception | réalisation | suivi
  precedente: "conception-etude-sol.html",
  suivante:   "conception-scenario.html",
  outils: [
    { id: "selection", icone: "↖", libelle: "Sélection" },
    { id: "deplacer",  icone: "⇔", libelle: "Déplacer un bord" },
    { id: "ligne",     icone: "✎", libelle: "Tracer une ligne", aVenir: true }
  ],
  panneauInfo: "Vous allez …",       // le texte d'accompagnement, 3 temps (CLAUDE.md § « accompagner, pas questionner »)
});
```

La page elle-même ne contient plus que ce qui lui est propre : son
`<main class="bureau">`, les champs de son panneau Données, et son
script d'étape (ex. la géométrie de la parcelle) — chargé à part, jamais
mélangé à `gabarit.js`.

**Le manifeste des étapes** (`plan-du-site.md` rendu machine-lisible,
même contenu, un seul endroit) donne à `Gabarit.monter()` de quoi
construire « Le voyage » et les boutons précédente/suivante sans que
chaque page ait à recopier la liste des quatre phases — exactement ce
que demande `directives.txt`. Un fichier, `public/assets/js/etapes.js`,
un tableau de données, rien de plus.

## Rôle de `composants/`

Pas la coquille (elle est dans `gabarit.js`), mais les briques plus
petites qui reviennent d'une étape à l'autre sans être universelles :
un champ numérique avec unité (`pd-ligne`), un tableau de résultats
calculés (`pd-resultat`), une bulle positionnable sur un objet du bureau.
Chaque dossier déjà créé (`bandeau-general/`, `panneau-droit/`…)
accueillera un fragment HTML/CSS/JS minimal plutôt que du code dupliqué
à chaque étape.

## Ce que ça ne tranche pas

- **Sans backend pour l'instant.** Cette proposition reste statique —
  HTML/CSS/JS, sans dépendance, ouvrable directement au navigateur,
  dans la continuité de `le-bureau/`. Elle ne dit rien de comment une
  étape lira ou écrira des données réelles.
- **La suite naturelle**, une fois une étape validée ainsi : la reprendre
  en PHP dans `application/public/<étape>.php`, sur le modèle déjà en
  cours dans le dépôt de référence (`Vue::posteOuvre()`/`posteFerme()`,
  voir `.claude/skills/atelier-syntropie-interface-layout/SKILL.md` § 8)
  — **la même trajectoire que « le bureau » lui-même**, maquette d'abord,
  généralisation ensuite. Pas décidé ici : à quel moment cette reprise a
  lieu, étape par étape ou en bloc.

## Ce qui est construit

- `public/assets/css/gabarit.css` — la coquille, extraite de
  `le-bureau/index.html`.
- `public/assets/js/etapes.js` — le manifeste des 4 phases et de leurs
  18 étapes, repris de `plan-du-site.md`.
- `public/assets/js/gabarit.js` — `Gabarit.init({ site, etape, contexte })`,
  légèrement différent de la signature proposée plus haut
  (`Gabarit.monter`) : remplit le titre contextuel, active/désactive
  « étape précédente/suivante », construit « Phase projet », et pose
  toute l'interaction générique (thème, tiroirs, Échap, menu d'outils,
  positionnement de la bulle via `Gabarit.bulle.montrer(élément, texte,
  orientation)`).
- **Première étape construite dessus** : `public/conception-parcelles.html`
  et `public/conception-parcelles.js` — la géométrie de la parcelle,
  reprise telle quelle depuis la maquette. Vérifiée par rendu (capture
  d'écran identique à la maquette) et par un cas limite (première étape
  du parcours : bouton précédente bien grisé).
- `public/assets/polices/` — Clash Display et Archivo copiées depuis
  `le-bureau/fonts/`, avec leurs licences.

## Le backend — tranché le 2026-09-13

**Décision du porteur : s'appuyer sur `application/` et l'adapter, pas
repartir de zéro.** Le HTML des étapes était la solution de mise au
point de la maquette — pas l'implémentation réelle. La trajectoire
n'est donc plus « valider en HTML puis reprendre dans `application/` » :
c'est l'inverse, le backend de `application/` vient **ici**, adapté.

Ce qui a été vérifié dans `application/src/` (dépôt de référence) et ce
qui en a été repris :

| Fichier de référence | Statut dans syntropia |
|---|---|
| `src/autoload.php` | Copié, préfixe `Syntropie\` → `Syntropia\` |
| `src/Configuration.php` | Copié, variables d'env `SYNTROPIE_` → `SYNTROPIA_` |
| `src/Database.php` | Copié et **adapté** : `comptes()` et `syntropia()` pointent chacune une base **propre à syntropia**, rien de partagé avec `syntropie` — voir décision ci-dessous |
| `src/Web/Vue.php` (`entete`/`pied`/`posteOuvre`) | **Ne sera pas repris** — remplacé par le gabarit HTML/CSS/JS déjà construit (`gabarit.css`, `gabarit.js`) |
| `src/Web/Authentification.php`, `Web/Comptes.php`, `Role.php`, `Profil.php` | **Copiés et adaptés** — cookie `SYNTROPIA`, `Comptes::APPLICATION = 'syntropia'` ; voir § suivant |
| `Chapitre.php`, `Statut.php`, `Plan.php`, `Referentiel.php`, services `Web/*` (`Sites`, `Parcelles`, `Scenarios`…) | Pas encore repris — réutilisables tels quels ou presque, à porter étape par étape, au moment où une page en a besoin |
| `bin/dev.py` | **Copié et allégé** (709 → ~200 lignes) — pas de création de base/utilisateur (droits d'administration requis, déjà fait à la main), pas d'évolutions à rejouer (aucune encore écrite). Garde : vérification PHP, vérification des deux bases, rejeu de schéma idempotent, lancement du serveur. Actions : `etat`, `init`, `serve`, et l'action par défaut qui enchaîne les trois |

### Décision révisée le 2026-09-13 : rien de partagé avec `syntropie`

Premier arbitrage (comptes partagés avec `syntropie`) **corrigé par le
porteur** : `syntropie` a vocation à être supprimé ou archivé, pas à
rester opérationnel à côté — syntropia doit avoir ses propres comptes,
pas les emprunter à un projet appelé à disparaître.

Construit dans `syntropia/` : `src/autoload.php`, `src/Configuration.php`,
`src/Database.php`, `config/base.php.exemple`, `config/.htaccess`,
`config/local.env`. Deux bases, deux noms propres à syntropia :

| Base | Rôle | Utilisateur MySQL |
|---|---|---|
| `syntropia` | Sites, parcelles, scénarios — le métier | `syntropia`@`localhost` |
| `syntropia_comptes` | Qui existe, et ce que chacun peut | `syntropia`@`localhost` |

**État — fait le 2026-09-13** : les deux bases et l'utilisateur
`syntropia`@`localhost` créés par le porteur, vérifiés de bout en bout en
PHP (`Database::comptes()` et `Database::syntropia()` se connectent et
listent leurs tables sans erreur).

**Le schéma est posé** : `sql/00-comptes.sql` (4 tables : `compte`,
`acces`, `tentative`, `invitation`) et `sql/01-syntropia.sql` (17 tables :
`site`, `parcelle`, `scenario_ligne`, `celebration`…) — copiés de
`application/sql/`, appliqués sans erreur, et **le vocabulaire de phases
corrigé** : décision du porteur, 2026-09-13, *« ne pas garder du
vocabulaire provenant de syntropie qui n'a pas de raison d'être »*.

- `site.statut` : `ENUM('definition','conception','realisation','suivi')`,
  défaut `'definition'` — les 4 phases de `plan-du-site.md`, à la place
  des 7 valeurs du dépôt de référence (`reve`/`diagnostic`/`conception`/
  `commande`/`chantier`/`suivi`). Appliqué à la table déjà créée (vide,
  aucune donnée à migrer).
- `reponse.phase` / `synthese.phase` (`VARCHAR`, pas d'`ENUM`) : commentaire
  mis à jour sur les mêmes 4 valeurs, à la place du vocabulaire Dragon
  Dreaming (`rever`/`planifier`/`agir`/`celebrer`).
- **Volontairement non touché** : le vocabulaire propre à la méthode
  syntropique elle-même — `jalon` de `celebration` (`chantier`,
  `saut-du-chat`, `fin-placenta`…), `parcelle.statut`
  (`conception`/`commande`/`plantee`/`suivi`/`close` — le cycle de
  construction d'UNE parcelle, distinct du parcours du site), `rêve` dans
  les noms de colonnes (`au_regard_du_reve`). Ce n'est pas du vocabulaire
  de `syntropie`-l'ancien-site à remplacer, c'est du vocabulaire de la
  syntropie-la-discipline, qui reste valide.

## L'authentification et la première page réelle — fait le 2026-09-13

`src/Web/Authentification.php`, `src/Web/Comptes.php`, `src/Role.php`,
`src/Profil.php` adaptés de `application/src/` — namespace `Syntropia\`,
cookie de session `SYNTROPIA` (au lieu de `SYNTROPIE`), constante
`Comptes::APPLICATION = 'syntropia'`. Le commentaire d'origine sur
l'isolation de session avec Plantae est reformulé : `syntropia_comptes`
n'est déjà plus partagée avec personne, mais le nom de cookie propre et
le cantonnement sous une clé à soi restent posés — pour ne rien avoir à
repenser le jour où une autre application s'y relierait.

`public/conception-parcelles.html` devient `public/conception-parcelles.php` :

- `Authentification::demarrer($_SERVER)` puis `$auth->exige(Role::Utilisateur)`
  en tête de fichier — la page se ferme elle-même, comme dans le dépôt
  de référence (« c'est la page qui garde »).
- Le nom réel de la personne connectée remplace le « Sylvain » écrit en
  dur du prototype (`compte-nom`, et ses initiales pour `compte-avatar`).
- Deux pages minimales ajoutées pour que la boucle soit testable, pas
  seulement portée sur la foi du code : `public/connexion.php`
  (formulaire, jeton CSRF, redirige vers la page demandée) et
  `public/deconnexion.php`. **Toutes deux provisoires et sans mise en
  forme** — la partie publique du site (accueil, connexion, inscription)
  n'a pas encore son propre design, voir `plan-du-site.md`.

**Vérifié de bout en bout**, avec un compte de test créé puis supprimé :
accès sans session → redirection vers `connexion.php` ; jeton CSRF
invalide refusé ; connexion réussie → redirection vers la page demandée,
nom affiché correct dans le bandeau et le panneau Informations ;
déconnexion → nouvel accès refusé. `Profil::accueil()` pointe
provisoirement `definition-vision.php` pour les trois profils, à
différencier une fois que chaque profil aura une vraie page d'accueil.

## `accueil.php` et `bin/dev.py` — fait le 2026-09-13, corrigé le 2026-09-14

**Erreur commise, puis corrigée** : `accueil.php` a d'abord été construit
hors du gabarit « le bureau », sur une feuille de style séparée
(`public.css`, une page de contenu classique avec défilement de page).
**Le porteur a corrigé le tir** : `directives.txt` décrit le bandeau
général et le bureau AVANT de distinguer partie publique et partie
connectée — c'est l'habillage de tout le site, accueil compris. Voir
CLAUDE.md § Architecture, désormais écrit en impératif : *« Le site EST
le bureau »*.

`public.css` est supprimé. `accueil.php` et `connexion.php` sont
reconstruits sur `gabarit.css` — bandeau général (hamburger, logo,
titre, thème, compte qui s'adapte à la session), panneau gauche (nav
propre à la partie publique), tirette/panneau Informations pour
`accueil.php`. **Sans bandeau d'outils** (rien à y choisir sur ces deux
pages) — ce qui a exigé de recaler `--haut-bandeaux` à `3.4rem` en tête
de page, cette variable étant calée par défaut sur deux bandeaux.

### Seconde correction, le 2026-09-14 : le bureau ne défile jamais

Première reconstruction encore fautive : le contenu du bureau défilait
en interne (`overflow-y:auto`) pour loger un texte plus long qu'un
écran. **Refusé par le porteur** — *« Syntropia n'est pas une page web,
c'est un atelier, comme une vue de dessus d'un bureau où il va
travailler pour concevoir son projet. »* Un bureau réel n'a pas de
défilement caché sous la table : ce qu'on y pose doit tenir, ou se
réduire. CLAUDE.md § Architecture le dit maintenant sans ambiguïté.

`accueil.php` réduit en conséquence à trois éléments, sur demande
explicite : un titre (« Syntropia »), un sous-titre (« Atelier pour
Système Agroforestier Syntropique »), trois cartes — **Se connecter**
(devient *Mon projet* connecté), **S'inscrire**, **Documentation** (les
trois cartes de documentation distinctes, fusionnées en une). Le
contenu retiré de la vue principale (pourquoi/pour qui, qu'est-ce que
la syntropie) est déplacé dans le panneau Informations plutôt que
supprimé — c'est son rôle. `connexion.php` : même correction, son
enveloppe `overflow-y:auto` retirée (le formulaire tenait déjà, mais la
règle ne souffre pas d'exception).

**Renommage** `Atelier Syntropie` → `Syntropia`, partout où le nom
apparaît dans l'interface (`accueil.php`, `connexion.php`,
`conception-parcelles.php`, `le-bureau/index.html`). `directives.txt`
non touché — c'est l'écrit du porteur.

`bin/dev.py` : voir le tableau plus haut. Remplace l'invocation manuelle
par une commande unique, idempotente, qui vérifie avant de servir.
**Bug trouvé en testant, corrigé** : `serve()` lançait `php` en enfant
direct de `subprocess.run` — un signal reçu par le script autrement que
Ctrl+C (un `kill`, un arrêt d'arrière-plan pendant cette session) ne se
propageait pas à `php`, qui restait orphelin, port occupé pour la
tentative suivante. Corrigé par un groupe de processus dédié
(`start_new_session=True`) et un relais explicite de SIGTERM.

**Vérifié** : lint PHP, rendu par capture d'écran (bandeau, panneau
Informations bien positionné, panneau gauche avec une seule entrée
Documentation), le parcours de connexion rejoué de bout en bout sur les
pages reconstruites, et `kill` sur `bin/dev.py serve` ne laissant plus
aucun `php` derrière.

## `definition-vision.php` — fait le 2026-09-14

Première étape de la phase Définition. Bureau centré, sans bandeau
d'outils (la saisie est le seul geste de la page), sans défilement :
trois champs (qui êtes-vous, motivation, intention au-delà de vous),
annoncés avant d'être demandés (« trois questions courtes, deux
minutes »), pas encore reliés à une sauvegarde — bouton « Continuer »
volontairement désactivé, avec la mention explicite plutôt qu'un geste
qui ne ferait rien.

**Deux corrections trouvées en la construisant :**

- `gabarit.js` pointait `<étape>.html` pour précédente/suivante — reste
  d'avant le passage en PHP. Corrigé en `.php`.
- Sans marqueur de ce qui existe vraiment, précédente/suivante
  auraient mené vers 16 pages qui n'existent pas. **`etapes.js` porte
  désormais `pret: true`** par étape construite ; `gabarit.js` grise le
  bouton vers une voisine présente dans le manifeste mais pas encore
  construite, plutôt que d'y mener. Seules `definition-vision` et
  `conception-parcelles` l'ont pour l'instant — **toute nouvelle étape
  doit poser ce drapeau en même temps que sa page**.

**Ajouté** : `.radio` dans `gabarit.css`, bouton radio thématisé (même
logique que `.case`), pour le premier choix binaire du parcours.

**Vérifié** : garde-page (refusé sans session), rendu par capture
d'écran, précédente ET suivante grisés (aucune voisine construite dans
les deux sens), compte de test créé puis supprimé.

## `definition-objectifs.php` — fait le 2026-09-14

Deuxième étape (Définition). Trois champs : productions (texte libre),
destination — loisir/vivrier/commercial/social, cases à cocher
cumulables — et terme — court/moyen/long, choix unique. Même gabarit
sans bandeau d'outils que `definition-vision.php`, même bouton
« Continuer » désactivé (rien à sauvegarder encore).

**Un vrai lien mort trouvé en la construisant, corrigé partout** :
`.pg-liste a.a-venir` n'était que grisé par CSS — un `href` réel vers
une page qui n'existe pas restait cliquable, juste discret. Ajouté
`pointer-events:none` à la règle : « à venir » veut dire non cliquable
partout dans le panneau gauche, pas seulement dans le bandeau d'outils
et les cartes d'accueil qui l'avaient déjà.

**Refactor, avant que la duplication ne devienne un vrai problème** :
avec une deuxième étape dans la même phase, la liste « Définition » du
panneau gauche codée en dur dans chaque page allait exiger de modifier
*toutes* les pages sœurs à chaque nouvelle étape. Remplacé par
`remplirEtapesPhase()` dans `gabarit.js` — lit `etapes.js`, construit la
liste des étapes de la phase en cours, marque l'active, lie les
`pret: true`, grise et neutralise (`tabindex="-1"`,
`aria-disabled="true"`) les autres. Une page ne porte plus qu'un
`<ul class="pg-liste pg-etapes-phase"></ul>` vide. `conception-parcelles.php`
garde sa liste « Parcelle A » telle quelle — un autre besoin (les pages
d'une parcelle, pas les étapes d'une phase), pas concerné.

**Vérifié** : garde-page, précédente/suivante dans les deux sens sur
les deux pages de Définition (Vision ↔ Objectifs navigables, Moyens/Site
grisés et non cliquables des deux côtés), le panneau gauche généré
correctement sur les deux pages, rendu par capture d'écran, compte de
test créé puis supprimé, serveur arrêté proprement.

## `definition-moyens.php` — fait le 2026-09-14

Troisième étape (Définition). Quatre champs : connaissances (texte
libre), temps disponible (texte libre), moyens financiers
(faible/modéré/confortable, choix unique), terrain (déjà en main ou
encore cherché). Même gabarit que les deux précédentes.

**Ajout notable, sans une ligne de JavaScript** : quand « Faibles » est
coché pour les moyens financiers, une suggestion apparaît — la
régénération naturelle assistée, une voie pour commencer avec peu.
Affichée par un pur sélecteur CSS, `fieldset:has(#financier-faible:checked)`,
dans le même esprit que le reste du gabarit (`:has()` plutôt qu'une
bibliothèque). Offrir une possibilité au moment précis où elle sert,
plutôt qu'un texte général en avait — voir `INTENTION.md`. Testé avec
les deux états (coché/décoché), capture d'écran des deux.

**Vérifié, et ça confirme que le refactor précédent tient** : sans
toucher à `definition-objectifs.php`, son bouton « suivante » s'est
activé tout seul dès que `definition-moyens` a reçu `pret: true` —
`remplirEtapesPhase()` et `configurerBoutonEtape()` lisent le manifeste
à chaque chargement, aucune page n'a d'état à elle à mettre à jour.
Garde-page, navigation dans les deux sens, compte de test créé puis
supprimé, serveur arrêté proprement.

## Une donnée sert une étape plus loin — principe posé le 2026-09-14

Précision du porteur sur la suggestion RNA de `definition-moyens.php` :
ce n'est pas une astuce isolée, c'est le principe général du parcours —
chaque donnée demandée doit avoir un usage repérable plus loin, et le
dire à l'écran motive une réponse plus pertinente qu'une question qui
paraît arbitraire. Écrit en toutes lettres dans CLAUDE.md § L'esprit à
garder dans l'interface.

Appliqué à `definition-objectifs.php`, avec le texte que
`directives.txt` donne déjà pour deux champs (rien d'inventé) :
« Pour quelle destination ? » porte maintenant *« Guide le « comment »
pour atteindre vos objectifs »*, et « À quel terme ? » porte *« Mesure
votre progression et vérifie que le projet reste réaliste »* — une
ligne visible sous chaque question, pas relégué dans le panneau
Informations. Les autres champs déjà construits
(`definition-vision.php`, `productions` et `connaissances`/`temps`/
`terrain` de `definition-moyens.php`) n'ont pas d'usage explicite dans
`directives.txt` — rien n'y a été ajouté, pour ne pas inventer un usage
qui n'est pas encore décidé.

**À tenir pour toute étape à venir** : quand `directives.txt` ou une
articulation du dépôt de référence donne l'usage d'un champ, l'écrire
à côté du champ. Quand l'usage n'est pas encore su, ne rien inventer —
silence plutôt que fausse justification.

**Vérifié** : rendu par capture d'écran (les deux hints visibles, tient
toujours sans défilement), compte de test créé puis supprimé, serveur
arrêté proprement.

## Arborescence et .htaccess — fait le 2026-09-14

Deux demandes du porteur, liées : le sous-titre de l'accueil, chaque
mot sur sa ligne (`<br>` entre chaque mot de « Atelier pour Système
Agroforestier Syntropique » — pas de CSS `white-space` ou de
`flex-direction: column`, le porteur voulait le texte lui-même coupé) ;
et l'arborescence protégée par `.htaccess`, **comme dans le dépôt de
référence** — l'accueil doit se servir par une URL sans nom de fichier.

**`accueil.php` → `public/index.php`.** `index.php` est le document par
défaut pour Apache comme pour le serveur intégré de PHP : `/` sert
maintenant la page d'accueil sans rien écrire de plus dans l'URL.
Toutes les références internes corrigées (`connexion.php`, le lien
« Accueil » du panneau gauche → `href="./"`, `bin/dev.py` qui annonce
l'URL). **Coupure nette, pas de redirection** : `accueil.php` répond
404 — rien ne le référençait plus une fois les liens internes corrigés,
et personne d'autre ne le lie encore de l'extérieur.

**L'arborescence, copiée du mécanisme de
`application/bin/preparer-depot.py`** (dépôt de référence), pas
réinventée :

| Fichier | Rôle |
|---|---|
| `.htaccess` (racine du dépôt) | Réécrit toute requête vers `public/`, **ne refuse rien** |
| `public/.htaccess` | Seul dossier autorisé ; type MIME et cache long pour les polices |
| `src/`, `config/`, `sql/`, `bin/` — un `.htaccess` chacun | Refusent tout accès web |

**La leçon du dépôt de référence, reprise telle quelle** : le contrôle
d'accès s'exécute *avant* la réécriture Apache. Un `Require all denied`
posé à la racine bloquerait la requête avant qu'elle n'atteigne
`public/`, et le site entier répondrait « Forbidden » — payé en ligne
le 2026-08-17 sur le dépôt de référence. D'où la règle : le refus vit
dans les dossiers qui n'ont rien à faire sur le web, jamais à la
racine, qui ne fait que rediriger.

**`RewriteBase /`** — à corriger si syntropia est un jour déployé sous
un sous-répertoire plutôt qu'à la racine d'un domaine (le dépôt de
référence utilise `/syntropie/`, imposé par son hébergement mutualisé).
Aucun hébergement n'est encore choisi pour syntropia — commenté dans le
fichier, pas deviné.

**Non vérifiable en local, et il faut le savoir** : `bin/dev.py` sert
`public/` directement avec `php -S`, qui ignore purement et simplement
les `.htaccess` — ni la réécriture ni les refus ne s'y exercent. Ce qui
a été vérifié : `/`, `/index.php` et `/accueil.php` (200, 200, 404)
via le serveur intégré, et la connexion rejouée de bout en bout depuis
la racine. **Ce que ce test ne prouve pas** : que le rewrite Apache
fonctionne réellement — seul un vrai Apache (ou le dépôt une fois en
ligne) peut l'exercer.

## Trois retours du porteur — fait le 2026-09-14

**`.bg-centre-titre` pas toujours centré.** Cause : `.bandeau-general`
utilise une grille `auto 1fr auto`, et `.bg-centre` était centré *dans
sa colonne* — dont la largeur dépend de gauche et droite. Sur
`connexion.php`, gauche (hamburger + logo + « Syntropia ») est bien
plus large que droite (le seul bouton thème, pas de pastille de
compte) : la colonne du milieu n'est donc pas centrée sur la fenêtre,
et le titre glisse visiblement vers la droite. Corrigé en sortant
`.bg-centre` du flux de la grille (`position:absolute; left:50%;
top:50%; transform:translate(-50%,-50%)`, `.bandeau-general` passé en
`position:relative`) — centré sur le bandeau entier, quelle que soit la
largeur de gauche et de droite. `max-width:min(55vw, 30rem)` l'empêche
de chevaucher les côtés sur un titre long ; `text-overflow:ellipsis`
prend le relais. Corrigé dans `gabarit.css` **et** dans
`le-bureau/index.html`, qui a la même structure et le même bug — non
remarqué là-bas parce que gauche et droite y sont proches en largeur
(pastille de compte complète des deux côtés). Vérifié par capture
d'écran, sur `connexion.php` et `index.php`, à 1400px.

**« Syntropia » doit ramener à l'accueil.** `<span class="marque-nom">`
devenu `<a class="marque-nom" href="./">`, dans le bandeau général et
dans l'en-tête du panneau gauche, sur les six pages réelles.
`le-bureau/index.html` non touché — maquette à fichier unique, « revenir
à l'accueil » n'y a pas de sens. Vérifié par script (les deux liens
trouvés, `href="./"` sur les deux).

**Le bouton thème signalé cassé — testé, pas reproduit.** Un script
injecté dans le HTML tel que servi par `connexion.php` et `index.php`
(pas un test isolé : la vraie page, chargée par le vrai `bin/dev.py`)
clique le bouton trois fois et lit `data-theme` et la couleur de fond
calculée après chacun :

```
initial: attr=null   | bg=rgb(237, 242, 231)
clic 1:  attr=dark   | bg=rgb(18, 23, 16)
clic 2:  attr=light  | bg=rgb(237, 242, 231)
clic 3:  attr=null   | bg=rgb(237, 242, 231)
```

Le cycle système → sombre → clair → système fonctionne, et la couleur
suit. **Le premier test avait donné un faux positif** : le fichier de
test avait été enregistré deux niveaux plus bas que `public/`, cassant
les chemins relatifs vers `gabarit.js`/`gabarit.css` — `Gabarit` restait
`undefined`, rien ne s'initialisait. Refait au bon endroit, tout
fonctionne. Cause la plus probable du signalement : un cache navigateur
sur une version antérieure de `gabarit.js`.

**Une vraie divergence avec le dépôt de référence, à trancher** :
`application/src/Theme.php` fait ce choix **côté serveur** — un cookie
(`SYNTROPIE_THEME`), un lien `?theme=...` qui recharge la page, le bon
`data-theme` écrit dans le premier octet de la réponse PHP. Aucun
JavaScript, aucun clignotement au chargement (le mauvais thème ne
s'affiche jamais, même une fraction de seconde). Notre mécanisme est
**côté client** (`localStorage`, un clic JS, pas de rechargement) : plus
simple à ce stade, mais un flash du thème par défaut est possible avant
que le JS ne s'exécute, et le choix ne survit pas à JavaScript désactivé.
**Ni construit ni tranché** : demander au porteur avant de porter
l'architecture serveur, qui touche `Configuration`, chaque page PHP, et
`gabarit.css` (les sélecteurs `:root[data-theme=...]` resteraient les
mêmes, seule la façon de poser l'attribut changerait).

## L'icône du bouton thème, et le sous-titre de l'accueil — fait le 2026-09-14

**Précision du porteur sur le bouton thème** : « dans syntropie l'icône
changeait de motif et il y avait "auto" qui se base sur le paramétrage
du navigateur ». Le second point était déjà vrai (le mécanisme « système »
lit `prefers-color-scheme`, voir plus haut) ; le premier ne l'était pas
— le SVG du soleil restait affiché quel que soit l'état, le bouton
changeait le thème sans jamais montrer lequel était actif.

**Corrigé, en reprenant le choix du dépôt de référence plutôt qu'en
inventant un jeu d'icônes** : `application/src/Theme.php` affiche
`☀` (clair), `☾` (sombre), `◐` (système) — trois caractères, pas de
SVG. Le SVG à trois branches du bouton devient un `<span
id="theme-icone" class="theme-icone">`, dont le texte change dans
`initTheme()` (`gabarit.js`) à chaque application du thème — au
chargement comme au clic. Titre et `aria-label` du bouton suivent
aussi (« Thème système — cliquer pour changer », etc.), pour que
l'état soit lisible même sans voir le glyphe. Appliqué aux six pages
réelles et à `le-bureau/index.html` (qui porte sa propre copie du
script, hors `gabarit.js`).

**Vérifié** : script injecté dans `index.php` tel que servi, quatre
clics :

```
initial: attr=null  | icone=◐ | title=Thème système — cliquer pour changer
clic 1:  attr=dark  | icone=☾ | title=Thème sombre — cliquer pour changer
clic 2:  attr=light | icone=☀ | title=Thème clair — cliquer pour changer
clic 3:  attr=null  | icone=◐ | title=Thème système — cliquer pour changer
clic 4:  attr=dark  | icone=☾ | title=Thème sombre — cliquer pour changer
```

L'icône suit l'attribut à chaque clic, dans les deux sens.

**Sous-titre de l'accueil** : « Atelier pour » fusionné sur une seule
ligne (au lieu de « Atelier » puis « pour » séparément), les trois
lignes suivantes inchangées — 4 lignes au lieu de 5. Tient toujours
sans défilement.

## `inscription.php` et `documentation-site.php` — fait le 2026-09-14

**`inscription.php`** — inscription sur invitation, adaptée de
`application/public/inscription.php` (dépôt de référence) sur le
gabarit du bureau, sans changement de logique. Deux temps dans le même
fichier : un code de huit caractères (`GET ?code=`) ouvre une
invitation, puis un formulaire (`POST`) crée le compte.

- `src/Web/Invitations.php` — porté du dépôt de référence
  (`ouvrir`, `revoquer`, `parCode`, `dUnParrain`, `parrainDe`, `etat`,
  `consommer`), aucun changement de fond : la table `invitation`
  existait déjà dans `sql/00-comptes.sql`, seul le namespace change.
  Rien n'appelle encore `ouvrir()` — il n'existe pas de page « inviter
  quelqu'un » — mais elle est prête pour `compte.php`.
- Le rattachement à un site (`invitation.site_id`,
  `Sites::ajouterMembre()` dans le dépôt de référence) n'est **pas**
  porté : le service `Sites` n'existe pas encore côté syntropia. Une
  invitation à un site précis ne fait aujourd'hui que créer le compte,
  sans l'ajouter à un projet.
- CSRF sur le `POST` (`$auth->jeton()`/`jetonValide()`) — absent de la
  page de référence, ajouté ici pour rester cohérent avec
  `connexion.php`, qui le fait déjà.
- Formulaire compact (marges réduites par rapport à `connexion.php`)
  pour tenir les cinq champs dans le bureau sans défilement — vérifié
  par capture d'écran aux deux temps du parcours.
- Testé de bout en bout avec un vrai compte parrain et un vrai code :
  ouverture d'invitation, création de compte (rôle `utilisateur`,
  ligne `acces` correcte), consommation de l'invitation
  (`filleul_id`/`utilise_le` posés), connexion réussie avec le nouveau
  compte — puis compte, accès et invitation de test supprimés.
- Les liens « S'inscrire » d'`index.php`/`connexion.php` (carte
  d'accueil, panneau gauche) ne sont plus grisés.

**`documentation-site.php`** — première version de la documentation du
fonctionnement du site (élaborée et maintenue par Claude, voir
`plan-du-site.md`), distincte de `documentation-saf.php` et
`autres-documentations.php` (fournies par Sylvain, toujours à venir).
Quatre sujets (Le principe, Les quatre phases, Le bureau, Les comptes),
un seul affiché à la fois : bascule en CSS pur
(`.doc-corps:has(#sujet:checked) .doc-panneau[data-sujet="…"]`), sans
JavaScript — même principe que la suggestion RNA de
`definition-moyens.php`, appliqué ici à un choix parmi quatre plutôt
qu'une condition binaire. Vérifié par capture d'écran, y compris après
un clic simulé sur un second sujet. Page publique, sans logique de
compte. Les liens « Documentation » (carte d'accueil, panneau gauche
de toutes les pages publiques) ne sont plus grisés ; `connexion.php`
n'avait pas encore ce lien — ajouté à cette occasion pour rester
cohérent avec `index.php`/`inscription.php`.

## `definition-site.php`, `conception-etude-sol.php` — fait le 2026-09-14

**`definition-site.php`** — quatrième et dernière étape de Définition.
Deux colonnes plutôt qu'une (`.site-colonnes`, `flex`) : c'est l'étape
avec le plus de champs jusqu'ici (commune, coordonnées, superficie,
zone USDA, exposition, pente, conditions climatiques particulières, ce
qui est déjà en présence sur le terrain — pieds-mères, graines,
végétaux utiles), une seule colonne l'aurait fait déborder du bureau.
Pas d'usage inventé par champ : `directives.txt` ne donne pas de
phrase-usage pour cette étape (contrairement à `definition-objectifs`),
donc rien n'est affirmé au-delà de définir ce qu'est une zone USDA.
Testée avec un compte jetable, capture d'écran à l'appui, puis nettoyée.

**`conception-etude-site.php` (la carte), reportée** — décision prise
avec le porteur après avoir lu `application/public/carte.php` : c'est
un outil cartographique complet (Leaflet, calques dessinables,
cadastre, import GeoJSON, rose des vents via Open-Meteo, relief IGN),
plusieurs services (`Calques`, `Vents`, `Relief`, `Cadastre`, `Eau`),
un ordre de grandeur au-dessus des formulaires construits jusqu'ici.
Trois options posées (repousser à un chantier séparé / version allégée
sans carte / port complet maintenant) — le porteur a choisi de repousser
et de continuer l'ordre des étapes. L'étape reste donc absente de
`etapes.js` (pas de `pret`), grisée comme toute étape non construite ;
rien d'autre à faire ici tant que ce chantier n'est pas ouvert.

**`conception-etude-sol.php`** — étape 2 de Conception. Contrairement à
la carte, son équivalent de référence (`releve-vierge.php` +
`FicheTerrain.php`) est un contenu structuré et factuel (protocoles de
relevé de sol : champs, unités, grilles de lecture), sans service ni
dépendance externe — un port direct, dans l'esprit de
`Web/Invitations.php`, pas une nouvelle architecture. Deux pièces :

- `src/FicheTerrain.php` — porté de `application/src/FicheTerrain.php`,
  contenu inchangé (sept fiches : flore, profil, densité, infiltration,
  slake test, vers de terre, texture — dans l'ordre du terrain, pas des
  numéros de fiche). Namespace adapté, rien d'autre : un protocole de
  relevé de sol ne dépend pas du nom de l'application.
- `public/conception-etude-sol.php` — sur le gabarit, aucun défilement :
  une annonce (pourquoi sept relevés, ce qu'ils permettent de décider —
  répond à la demande de `plan-du-site.md`, « dire ce qu'on va
  déterminer et pourquoi »), un aperçu compact des sept fiches (numéro,
  titre, quand), un bouton vers `releve-vierge.php`.

**`releve-vierge.php` — seule page HORS du gabarit, délibérément.** Le
gabarit verrouille `overflow:hidden` sur `html,body` pour qu'aucun
contenu ne défile jamais ; une fiche de sept relevés faite pour être
lue au crayon sur le terrain, puis imprimée sur plusieurs pages, a
besoin de l'inverse exact. Elle ne charge donc pas `gabarit.css` :
polices et couleurs reprises à la main dans son propre `<style>`
(cohérence visuelle), sans la grille ni le verrou de défilement. Barre
d'action (retour, imprimer) masquée en `@media print`. Vérifié en PDF
réel (`google-chrome --print-to-pdf`) : neuf pages, une par fiche
(`break-before:page`), barre absente, mise en page lisible. Documenté
comme exception unique dans `CLAUDE.md` § Directives de travail, pour
qu'elle ne serve pas de précédent à autre chose qu'un document destiné
au papier.

## La navigation « Phase projet » ne menait nulle part — fait le 2026-09-14

Signalé par le porteur : *« je n'arrive pas à aller sur definition-site »*.
`remplirPhaseProjet()` (`gabarit.js`) posait `a.href = '#'` sur les
quatre entrées de la liste « Phase projet », sans jamais le changer —
seule la classe (`active` / `a-venir`) variait. Un utilisateur connecté
arrive sur `conception-parcelles.php` (phase Conception) : la seule
porte vers la phase Définition, le lien « Définition » du panneau
gauche, ne menait donc nulle part — ni grisé (il n'avait pas la classe
`a-venir`, réservée aux phases *futures*), ni fonctionnel. Aucun autre
chemin n'existe pour remonter à une phase antérieure : `pg-etapes-phase`
ne liste que les étapes de la phase courante.

Corrigé : chaque phase autre que l'active mène désormais à sa première
étape `pret: true` (`premiereEtapePreteDeLaPhase()`) ; sans étape prête
dans la phase, le lien reste `a-venir` (grisé, `pointer-events:none`,
comme partout ailleurs). Pas de mémoire de la dernière étape visitée
dans cette phase — aucune persistance n'existe encore pour la porter ;
« Définition » ramène toujours à `definition-vision.php`, son point
d'entrée naturel. Vérifié avec un compte jetable : depuis
`conception-parcelles.php`, « Définition » mène maintenant à
`definition-vision.php` ; depuis là, « Site » (liste des étapes de la
phase) mène à `definition-site.php` ; « Conception » depuis une page de
Définition mène à `conception-etude-sol.php` (première étape prête,
`conception-etude-site` n'existant pas encore) ; Réalisation et Suivi
restent correctement grisés. `le-bureau/index.html` ne porte pas ce
mécanisme (maquette statique) — rien à y répercuter.

## La persistance réelle — fait le 2026-09-14

Signalé par le porteur : *« le bouton continuer n'est pas cliquable
après saisie des questions. Les réponses ne sont pas conservées lorsqu'on
change d'étape »*. C'était l'état documenté (« chaque étape saisit, sans
encore sauvegarder »), pas un bug — mais c'était le prochain vrai
chantier, et le porteur voulait avancer dessus maintenant plutôt que
continuer à construire des étapes sur du sable.

**Question ouverte avant d'écrire du code** : comment un site (le
projet) se crée-t-il ? `directives.txt` tranche « un seul projet par
utilisateur avec un site et *n* parcelles », mais pas *quand* ni
*comment* ce site apparaît. Deux options posées au porteur — créer le
site en silence au premier `Continuer`, ou une courte étape explicite
avant Vision. **Choisi : l'étape explicite** — `definition-projet.php`.

### Le modèle, repris du dépôt de référence sans l'alourdir

Le schéma (`sql/01-syntropia.sql`) portait déjà tout ce qu'il fallait :
`site`, `membre_site` (l'accès — qui peut lire/écrire), `participant`
(une personne dont les réponses comptent — distincte de l'accès, comme
le note déjà `Sites::ajouterMembre()` du dépôt de référence : « l'un
ouvre les données, l'autre nomme une personne qui compte »), `reponse`
(clé `site_id` + `participant_id` + `question`, une ligne par réponse).
Rien à inventer là — juste à écrire les deux services qui en lisent et
écrivent :

- **`src/Web/Sites.php`** — inspiré d'`application/src/Web/Sites.php`
  (dépôt de référence), réduit à ce qu'un seul projet par compte
  demande : `pourCompte()` (le site existant, ou `null`), `creer()`
  (transaction : site + `membre_site` porteur + premier `participant`,
  en une fois), `participantDuCompte()`. Ce qui a été volontairement
  laissé de côté : la liste de plusieurs sites (`pourCompte` du dépôt
  de référence), le code d'invitation à un site précis, la mise à jour
  des paramètres du site (`enregistrerParametres`) — reviendra avec
  `definition-site.php` câblé à de vraies colonnes de `site`, pas
  seulement à `reponse`.
- **`src/Web/Reponses.php`** — inspiré de la partie « réponses »
  d'`application/src/Web/Reves.php`, réduit de même : pas de synthèse
  commune, pas de second participant, pas de code d'invitation. Deux
  méthodes : `pourParticipant()` (question → texte, pour préremplir un
  formulaire) et `enregistrer()`/`enregistrerLot()` — `ON DUPLICATE KEY
  UPDATE` si le texte n'est pas vide, `DELETE` sinon. **Un champ vidé
  n'est pas un champ jamais rempli** : la ligne disparaît plutôt que de
  garder un `''`, même règle que le dépôt de référence.
- **Les clés de question ne vivent nulle part à part** : ce sont les
  `name` déjà posés sur les champs des formulaires, préfixés par
  l'étape (`vision_motivation`, `objectifs_terme`, `site_commune`…)
  pour rester uniques dans une même table `reponse` partagée entre
  étapes. Pas de classe `Questionnaire` séparée comme dans le dépôt de
  référence — inutile tant qu'il n'y a ni double registre de questions
  ni anciennes clés à faire correspondre.

### `definition-projet.php`

Nouvelle page, volontairement **hors du manifeste `etapes.js`** : ce
n'est pas une étape qu'on retraverse, c'est un passage obligé une seule
fois. Elle redirige elle-même vers `definition-vision.php` si le compte
a déjà un site (jamais revue une deuxième fois), et chaque étape de
Définition y redirige si le compte n'en a pas encore. Un seul champ
(nom du projet) ; `Sites::creer()` fait le reste. `plan-du-site.md` mis
à jour pour la citer sous Phase 1.

### Les quatre étapes de Définition, câblées

`definition-vision.php`, `definition-objectifs.php`,
`definition-moyens.php`, `definition-site.php` suivent maintenant
toutes le même schéma :

1. Garde : pas de site → redirection vers `definition-projet.php`.
2. `POST` valide (CSRF vérifié) → `Reponses::enregistrerLot()`, puis
   redirection vers l'étape suivante (`definition-site.php` redirige
   vers `conception-etude-sol.php`, première étape prête de la phase
   suivante — même logique que le correctif de navigation du
   panneau gauche, quelques messages plus tôt).
3. `GET` (ou retour après une erreur) → `Reponses::pourParticipant()`
   préremplit chaque champ, y compris les groupes de cases à cocher
   (`destination[]`, `present[]`, rejointes en une chaîne séparée par
   des virgules dans une seule ligne `reponse`).
4. Le bouton « Continuer » n'est plus désactivé : fond `--accent`,
   comme les boutons déjà réels de `connexion.php`/`inscription.php`.
   La note sous le bouton dit maintenant « Enregistré dès que vous
   continuez — vous pouvez revenir sans rien perdre ».

**Vérifié de bout en bout avec un compte jetable** : création du
projet, remplissage des quatre étapes avec des valeurs différentes,
navigation en arrière vers chacune — chaque champ, chaque case, chaque
bouton radio revient exactement comme laissé, y compris le radio
`#financier-faible` dont dépend la suggestion RNA en `:has()`. Testé
aussi qu'un champ reréécrit vide efface bien sa ligne plutôt que d'y
laisser une chaîne vide. Lignes en base inspectées directement
(`site`, `participant`, `reponse`) avant nettoyage.

## L'aperçu de `conception-etude-sol.php`, trié par numéro — fait le 2026-09-14

Demande du porteur. L'aperçu affiché à l'écran trie maintenant les sept
fiches par numéro croissant (01 → 07) — un tri local à la page
(`usort` sur une copie de `FicheTerrain::fiches()`), qui laisse intacts
et la donnée source et `releve-vierge.php`, toujours dans l'ordre du
terrain (flore avant tout piétinement, puis le profil dont le trou sert
aux deux mesures suivantes…) : cet ordre-là a une raison d'être
physique, documentée dans `FicheTerrain::fiches()`, et n'avait pas de
raison de changer. Le panneau Informations de `conception-etude-sol.php`
précise maintenant la distinction, pour ne pas laisser croire que
l'aperçu et la fiche imprimée se lisent dans le même ordre.

## Le menu utilisateur — fait le 2026-09-14

Demande du porteur : ajouter la déconnexion et les préférences dans le
menu utilisateur. L'élément `.compte` (avatar + nom, en haut à droite)
était jusque-là un lien direct vers `conception-parcelles.php` sur les
sept pages connectées, ou un `<button>` sans effet sur
`conception-parcelles.php` elle-même — nulle part un vrai menu.

**Mécanique ajoutée au gabarit**, réutilisable par toute page qui montre
le compte connecté :

- `gabarit.css` — `.compte` devient le déclencheur d'un `.compte-menu`
  (`position:absolute`, ancré sous l'avatar), avec les mêmes conventions
  que le reste du bureau : `.compte-menu-item.a-venir` grisé et
  `pointer-events:none` (comme `.pg-liste a.a-venir`), séparateur avant
  l'action de sortie.
- `gabarit.js` — `initCompteMenu()`, calquée sur `initOutils()` : ouvre
  au clic, ferme au clic dehors, s'ajoute à `fermetures` pour qu'Échap
  la ferme aussi comme n'importe quel tiroir.

**Contenu du menu** — « Mon projet » (le lien qui existait déjà,
préservé plutôt que perdu en convertissant l'élément), « Préférences »
(`compte.php`, encore à construire — grisée, `a-venir`, comme toute
page absente ailleurs sur le site), puis, séparée, « Se déconnecter »
(`deconnexion.php`, déjà réel). Ordre : le plus utilisé d'abord
(loi de Jakob, § CLAUDE.md), la sortie en dernier avec un séparateur —
convention universelle des menus de compte, pas seulement une question
de fréquence.

Appliqué aux neuf pages qui affichent le compte : les sept étapes
connectées (`definition-projet.php` à `conception-etude-sol.php`,
`conception-parcelles.php`), et la branche « connecté » d'`index.php`
et `documentation-site.php` — la branche « non connecté » de ces deux
dernières garde un simple lien vers `connexion.php`, rien à y ouvrir.
`le-bureau/index.html` (maquette statique, sans backend) non touché —
« Se déconnecter » n'y aurait aucun sens.

Vérifié avec un compte jetable : ouverture au clic, fermeture au clic
dehors, réouverture, fermeture à Échap, « Préférences » réellement
non cliquable (`pointer-events:none` confirmé), « Se déconnecter »
termine réellement la session (page suivante gardée redirige vers
`connexion.php`).

## `connexion.php` sans titre en double — fait le 2026-09-14

Demande du porteur : le `<h1>Se connecter</h1>` au-dessus du formulaire
répétait le texte du bouton juste en dessous — la page portait déjà
« Connexion » dans le bandeau général, et « Se connecter » sur le
bouton. Retiré, avec la règle CSS `.connexion-cadre h1` devenue sans
objet. Vérifié par capture d'écran.

## Les documentations en livrets markdown — fait le 2026-09-14

Deux demandes du porteur, liées. D'abord : présenter les deux
documentations (L'agroforesterie syntropique, L'atelier) comme « des
pages que l'on tourne sur le bureau », en chapitres. Puis, la voir
construite : puisque ces textes sont voués à évoluer, les fournir en
markdown plutôt que de rouvrir du PHP à chaque correction.

### Le livret — CSS pur, comme le reste du gabarit

`documentation-site.php` avait déjà une bascule à onglets en `:has()`
(voir plus haut, § quatre sujets). Le livret en garde le principe et
ajoute ce qu'un onglet n'a pas : un repère de position (« Chapitre 2
sur 4 ») et deux boutons « tourner la page » qui mènent au chapitre
voisin — pas de bibliothèque, pas de JavaScript, juste des
`<label for="…">` supplémentaires pointant vers le radio du chapitre
suivant ou précédent.

**Piège trouvé en testant** : les quatre chapitres empilés avec
`position:absolute` demandaient une hauteur fixe au conteneur — et
celle choisie tronquait le second paragraphe du premier chapitre au
milieu d'une phrase (capture d'écran à l'appui, avant correction).
Remplacé par une grille CSS où les quatre `<article>` occupent la
MÊME cellule (`grid-column:1; grid-row:1`, sans `position:absolute`) :
la grille prend d'elle-même la hauteur du plus grand des chapitres,
aucun ne peut plus être coupé. Piège du même ordre que celui de
`.bg-centre-titre` (2026-09-14, plus haut) : une valeur choisie à vue
au lieu de laissée au navigateur.

**Une paire de boutons « tourner » par chapitre**, montrée seulement
quand ce chapitre est actif (même mécanique `:has()` que le contenu) :
en CSS pur, un bouton ne peut pas recalculer tout seul « le suivant »
selon l'état — il faut une règle par chapitre. Accepté comme le
`:has()` de `configurerBoutonEtape()` : explicite plutôt que calculé,
cohérent avec le reste du gabarit.

### Le contenu — markdown, hors de public/, lu par une classe

`src/Documentation.php` lit `contenu/*.md` et rend une liste de
chapitres (`id`, `titre`, `paragraphes` déjà échappés). Format
volontairement pauvre, sur le même principe que `FicheTerrain.php` —
**les questions vivent dans le code, les réponses dans la base**,
ici la variante « le texte vit dans un fichier, pas dans le code » :

- un chapitre commence à chaque ligne `# Titre` ;
- une ligne vide sépare deux paragraphes ; les sauts de ligne à
  l'intérieur d'un paragraphe ne sont qu'une largeur de colonne
  d'édition, ils disparaissent à l'affichage (`preg_replace('/\s+/', ' ', …)`) ;
- `**gras**` est le seul marquage reconnu ;
- rien d'autre — pas de listes, pas de liens, pas de sous-titres.
  Étendre `Documentation.php` le jour où un chapitre en a vraiment
  besoin, plutôt que de laisser le fichier markdown inventer sa propre
  syntaxe au fil de l'écriture.

`documentation-site.php` et `documentation-saf.php` ne diffèrent plus
que par le fichier `.md` qu'ils lisent (`documentation-atelier.md` /
`documentation-saf.md`) et le titre du bandeau — tout le reste (CSS,
boucles de rendu du livret) est dupliqué à l'identique entre les deux
pages, dans l'esprit du dépôt (chaque page reste autonome, pas de
partial PHP partagé). Le nombre de chapitres n'est plus fixé dans le
code : les règles CSS `:has()` par chapitre et les paires de boutons
« tourner » sont maintenant générées par une boucle PHP à partir de ce
que `Documentation::chapitres()` renvoie — ajouter un chapitre dans le
`.md` suffit, aucune page ne se rouvre.

**Deux fichiers posés comme modèle**, à la demande du porteur :

- `contenu/documentation-atelier.md` — le texte déjà écrit (quatre
  chapitres : Le principe, Les quatre phases, Le bureau, Les comptes),
  reformaté en markdown propre. Sert à la fois de contenu réel et
  d'exemple complet du format.
- `contenu/documentation-saf.md` — un modèle à remplacer : son premier
  chapitre explique le format dans le fichier lui-même (pas seulement
  ici), le second est un squelette à deux paragraphes. `documentation-
  saf.php` passe de `a-venir` à un lien réel dans `etapes.js`
  (`DOCUMENTATION` dans `gabarit.js`) : la mécanique fonctionne
  réellement, même si son texte n'est encore qu'un modèle — assumé et
  dit tel quel dans le livret.

Vérifié : le parseur sur les deux fichiers (identifiants générés,
comptage des paragraphes), capture d'écran des deux pages, navigation
simulée — clic sur un chapitre du sommaire puis sur « suivant » puis
sur « précédent », vérifiée par l'état réel du radio coché plutôt que
par une supposition ; confirmé que le premier chapitre n'a pas de
bouton « précédent » et le dernier pas de bouton « suivant ».

## Le parcours complet, de Calendrier à Suivi — fait le 2026-09-15

Demande du porteur, à la reprise : « continue la création des pages
manquantes ». `directives.txt` a tranché deux choses avant d'écrire du
code :

- `conception-scenario` (étape 4) est **parcelle-scoped**, et le dépôt
  de référence le confirme (`scenario.php`, 336 lignes : espèces,
  strates, associations culturales, implantation des bordures —
  `ImplantationBordures`, `Scenario`, une base d'espèces). Le même
  ordre de grandeur que `carte.php`, déjà écarté le 2026-09-14.
- `conception-materiel-et-eau` (étape 5) l'est tout autant :
  `directives.txt` dit explicitement « pour chaque parcelle » — un
  récapitulatif qui suppose un scénario déjà choisi, donc les mêmes
  données absentes.

**Les deux restent donc reportées**, avec `conception-etude-site`,
pour le même chantier futur (parcelles et scénario réels) — non
construites en version allégée, `pret` absent de `etapes.js`. Rien
d'autre dans le dépôt de référence ne correspond aux étapes restantes
(`materiel.php`, `calendrier.php`, `preparation.php`… n'existent pas) :
pas de contenu « déjà présent » à porter, ces neuf pages sont écrites
depuis `directives.txt` directement, sur le modèle déjà établi par la
phase Définition (`Reponses::enregistrerLot()` / `pourParticipant()`).

**`conception-calendrier.php`** — seule étape de Conception construite
cette fois, parce que son objet (« calendrier du projet ») reste au
niveau du site, pas de la parcelle. Quatre saisons en grille 2×2,
chacune une question ouverte — pas encore calculé à partir d'un vrai
scénario, dit tel quel dans le panneau Informations.

**Les quatre étapes de Réalisation** — `preparation-parcelle`
(déchaumage/bâchage, manuel/animaux, en radios + précisions),
`materiel-vegetal` (multiplication vs achat, avec une info factuelle sur
bouturage/semis/division — définir, pas inventer, même principe que la
zone USDA de `definition-site.php`), `outils-et-plans` (outils, moyens
humains, notes de plan — le vrai plan viendra du scénario, absent),
`consignation` (date + ce qui a été fait + conditions).

**Les quatre étapes de Suivi** — `arrosage-germination` (arrosage,
boutures, germination), `perturbation` (quand/quoi/comment — la taille
comme geste qui relance, pas qui soigne), `saut-du-chat` (visible ou
non, avec une suggestion `:has()` — planter davantage — si « pas
encore », même mécanique que la suggestion RNA de
`definition-moyens.php`), `consignation` (date, observations, résultats
— redirige vers `conception-parcelles.php`, faute d'étape suivante :
c'est la fin du parcours construit, et la seule page qui se redemande
sciemment — le suivi se répète, contrairement aux étapes amont).

Dix-huit étapes désormais `pret: true` sur vingt dans `etapes.js` — il
ne manque que les deux écartées ci-dessus. Vérifié de bout en bout avec
un compte jetable : création du site, les neuf formulaires remplis à la
chaîne (chaque redirection vérifiée par son en-tête `Location`), les
vingt-six réponses relues directement en base, capture d'écran de deux
pages (la grille du calendrier, la suggestion conditionnelle du saut du
chat) — puis tout supprimé.

## Hébergement — communiqué par le porteur le 2026-09-15

- URL : http://jubile.alwaysdata.net/
- Serveurs DNS : `dns1.alwaysdata.com`, `dns2.alwaysdata.com`

**Différent du dépôt de référence** : `application/` est hébergé sur du
mutualisé cPanel (`application/conception/mise-en-ligne.md`), qui
impose son propre préfixe (`hngaenpk_`) sur les noms de base et
d'utilisateur MySQL — visible dans `application/config/base.php.exemple`.
`config/base.php.exemple` de syntropia portait encore ce même préfixe,
copié tel quel sans être adapté : sans rapport avec alwaysdata, un
hébergeur différent.

**Retrouvé dans `syntropie/JOURNAL.md`, entrée du 2026-09-11** : le
compte alwaysdata a été ouvert ce jour-là pour y déposer Syntropie et
Plantae — français, PHP 8.2 à 8.5 au choix, MySQL sans limite de bases,
offre gratuite non commerciale — sous-domaine retenu
`jubile.alwaysdata.net`. **Rien n'était déployé à cette date** ; le
journal note explicitement « accès SSH d'alwaysdata à confirmer,
procédure de dépôt à écrire » — la migration elle-même restait à
instruire.

### L'accès SSH, retrouvé et vérifié — 2026-09-15

`~/.ssh/config` (niveau machine, pas propre à un dépôt — déjà valable
ici sans rien recopier) porte un alias déjà posé le 2026-09-11 :

```
Host ssh-jubile.alwaysdata.net jubile
    HostName ssh-jubile.alwaysdata.net
    User jubile_dev
    IdentityFile ~/.ssh/id_ed25519_alwaysdata
    IdentitiesOnly yes
```

**Connexion vérifiée** (`ssh jubile`) — et l'exploration en lecture
seule qui a suivi confirme ou corrige ce que la section précédente ne
faisait encore que déduire :

- `~/www/` est le racine web du compte — **vide** (seule la page
  d'accueil par défaut d'alwaysdata), rien n'y a jamais été déposé.
  `~/admin/` porte la disposition alwaysdata standard (`config/`,
  `logs/`, `data/`, `backup` et `mail` en liens vers du NFS partagé).
- PHP en ligne de commande : **8.4.25** — au-delà du minimum 8.1 visé
  par syntropia, rien à ajuster.
- **Pas de socket MySQL local** (`/run/mysqld/mysqld.sock` absent) :
  MySQL vit sur un hôte séparé, jamais `localhost` — confirme et
  corrige `config/base.php.exemple`, qui portait encore la valeur
  héritée du dépôt de référence (cPanel, où PHP et MySQL partagent la
  même machine).
- `mysql-jubile.alwaysdata.net` **résout** (`getent hosts`) : la
  convention de nommage alwaysdata (hôte MySQL = `mysql-<compte>.
  alwaysdata.net`) se confirme pour ce compte précis, pas seulement en
  théorie. `config/base.php.exemple` mis à jour avec cette valeur.
- **Aucune base ni identifiant MySQL retrouvés** par cet accès — ni
  `~/.my.cnf`, ni variable d'environnement, ni base déjà créée. Les
  bases n'existent probablement pas encore : `mysql -e "SHOW
  DATABASES;"` échoue faute de socket local, et aucune tentative de
  connexion distante n'a été faite sans mot de passe connu. Le
  préfixe `jubile_` de `config/base.php.exemple` reste donc une
  déduction (convention alwaysdata + nom de compte confirmé), pas une
  valeur lue — à vérifier dans le panneau alwaysdata au moment de
  créer les bases.

**Ce que ça ne fait pas encore** : aucune procédure de dépôt n'existe
pour syntropia — ni script, ni `rsync`, ni équivalent du
`preparer-depot.py` du dépôt de référence poussé quelque part (celui-ci
ne fait que préparer l'arborescence locale, il ne s'est jamais servi de
cet accès SSH non plus). À écrire le jour où un premier dépôt réel est
souhaité.

`config/base.php.exemple` corrigé en conséquence : préfixe `jubile_`
(alwaysdata préfixe bases et utilisateur MySQL du login du compte, et
le sous-domaine par défaut reprend ce login — déduction, pas une valeur
lue telle quelle) et `'base' => '/'` (l'URL communiquée n'a pas de
sous-dossier). **Deux points non confirmés**, marqués dans le
commentaire du fichier : le préfixe exact (à vérifier dans le panneau
alwaysdata une fois les bases réellement créées) et `hote` — resté
`localhost`, mais alwaysdata sépare parfois le service MySQL sous son
propre nom d'hôte (`mysql-jubile.alwaysdata.net`).

## Premier déploiement réel, et la vérification `.htaccess` enfin possible — fait le 2026-09-15

Demande du porteur : « tu peux travailler sur le déploiement de la base
de données sur le serveur cible (jubile) et également sur le
déploiement des fichiers www ». Pas de CLI/API alwaysdata retrouvée
pour créer des bases (§ ci-dessus, L'accès SSH) — donc rien de fait
côté bases sans un premier geste du porteur (voir la question posée en
fin de section). Côté fichiers, en revanche, tout était déjà à portée
de l'accès SSH retrouvé.

**`bin/deployer.py`**, sur le même moule que `bin/dev.py` : `etat`
(diagnostic SSH + PHP distant + bases), `fichiers` (`rsync` vers
`jubile:~/www/`, `--dry-run` pour prévisualiser), `config` (envoie
`config/base.php` — séparément de `fichiers`, jamais écrasé sans le
demander explicitement, pour ne jamais effacer une configuration de
production déjà en place par un envoi de routine), `base` (rejoue
`sql/00-comptes.sql` et `sql/01-syntropia.sql` sur les bases distantes,
`mysql` exécuté **sur le serveur lui-même** via SSH plutôt qu'une
connexion distante depuis ce poste — plus sûr si alwaysdata restreint
l'accès MySQL au réseau interne, pas vérifié dans un sens ou l'autre
mais aucune raison de le supposer ouvert).

Liste explicite de ce qui part (`.htaccess`, `bin/`, `config/.htaccess`,
`config/base.php.exemple`, `contenu/`, `public/`, `src/`, `sql/`) —
plutôt qu'une exclusion : dire ce qui part est plus sûr que d'espérer
avoir exclu tout ce qui ne doit pas partir. Volontairement absents :
`le-bureau/` (maquette de référence, de toute façon inatteignable une
fois le rewrite actif), `skills-map/`, `composants/` (des `README.md`
vides, jamais peuplés), et toute la documentation de développement
(`CLAUDE.md`, `architecture-technique.md`, `plan-du-site.md`,
`directives.txt`) — rien de tout cela n'est lu par le site.

**Deux bugs trouvés et corrigés en testant** (`--dry-run` d'abord,
toujours) :

- `rsync -az --dry-run`, sans `-v`, ne produit AUCUNE sortie — le
  premier essai concluait donc « déjà à jour » sur des dossiers qui
  n'avaient jamais été envoyés. Le silence n'était pas une preuve
  d'absence de travail à faire.
- `--mkpath` a échoué pour `config/.htaccess` (« change_dir failed : No
  such file or directory », alors que dans le même souffle il annonce
  avoir créé le dossier) — capricieux pour un fichier isolé dont le
  dossier parent n'existe pas encore. Remplacé par un `mkdir -p` distant
  explicite, une fois, avant tout transfert : plus prévisible que de
  compter sur `rsync` pour deviner.

**Déployé, et vérifié en HTTP réel** (`http://jubile.alwaysdata.net/`)
— la première vérification `.htaccess` sur un vrai Apache, notée
« non vérifiable en local » depuis le 2026-09-14 :

| Requête | Attendu | Constaté |
|---|---|---|
| `/` | 200, le site (racine réécrite vers `public/`) | 200 ✓ |
| `/connexion.php` | 200 | 200 ✓ |
| `/src/autoload.php` | 403 | 403 ✓ |
| `/config/base.php.exemple` | 403 | 403 ✓ |
| `/sql/00-comptes.sql` | 403 | 403 ✓ |
| `/bin/dev.py` | 403 | 403 ✓ |
| `/contenu/documentation-atelier.md` | 403 | 403 ✓ |
| `/assets/polices/Archivo-Regular.woff2` | 200, `font/woff2`, cache 1 an | 200, `font/woff2`, `max-age=31536000, immutable` ✓ |
| `/assets/polices/` (listage) | 403 | 403 ✓ |

**Tout concorde, sans exception** — l'architecture `.htaccess` conçue
le 2026-09-14 sur le seul modèle du dépôt de référence fonctionne
telle quelle sur alwaysdata. `index.php` s'affiche correctement sans
base de données (`Authentification::reprendre()` ne s'y essaie que
si un cookie de session existe déjà — jamais le cas ici).

**Nettoyé au passage** : `public/README.md`, une note laissée quand
`public/` était vide (« à peupler au fur et à mesure ») — fausse
depuis longtemps, supprimée plutôt que corrigée.

**Ce qui reste bloqué, faute d'un geste côté porteur** : les deux
bases MySQL et l'utilisateur n'existent pas encore sur alwaysdata —
`config/base.php` ne peut donc pas être rempli avec de vraies valeurs,
et `deployer.py base`/`config` restent inutilisables. Question posée
au porteur : créer, dans le panneau alwaysdata, une base
`jubile_syntropia_comptes`, une base `jubile_syntropia` (collation
`utf8mb4_unicode_520_ci` pour les deux, comme le schéma le suppose), un
utilisateur `jubile_syntropia` avec tous les droits sur les deux — puis
donner le mot de passe choisi, pour qu'il aille dans `config/base.php`
(hors dépôt, jamais affiché ni loggé au-delà de ce fichier).

## Syntropia en ligne pour la première fois — fait le 2026-09-15

Les bases et l'utilisateur créés par le porteur dans le panneau
alwaysdata (pas par SQL brut — `jubile`, le compte propriétaire, n'a
pas le droit `CREATE DATABASE` ; le formulaire dédié du panneau l'a
fait, comme pressenti). Une friction relevée en passant, utile si le
besoin se représente : le nom d'une base doit être tapé déjà préfixé
(`jubile_syntropia_comptes`), le panneau ne le fait pas pour vous.

Une fois `jubile_syntropia_comptes`, `jubile_syntropia` et
l'utilisateur `jubile_syntropia` en place, mot de passe communiqué par
le porteur :

1. `config/base.php` rempli (jamais versionné) et vérifié joignable
   **depuis le serveur lui-même** (`deployer.py etat`) avant tout envoi.
2. `deployer.py config` — envoyé.
3. `deployer.py base` — `00-comptes.sql` et `01-syntropia.sql` rejoués
   sans erreur. Vérifié : 4 tables dans `jubile_syntropia_comptes`, 17
   dans `jubile_syntropia` — les comptes exacts attendus.
4. **Vérification de bout en bout, sur le site réel** : un compte
   jetable créé via `bin/compte.php` en SSH (pas `dev.py compte`, qui
   dépend de `config/local.env`, absent en production — la faute déjà
   commise une fois ici même, corrigée en le relançant directement),
   connexion réussie sur `http://jubile.alwaysdata.net/connexion.php`
   (redirection 302 vers `conception-parcelles.php`), page suivante
   servie avec le nom du compte affiché — puis compte supprimé.

**Syntropia répond en ligne, pour la première fois**, bases comprises —
pas seulement les fichiers statiques et le routage `.htaccess` vérifiés
plus tôt dans la journée. Tout ce que `deployer.py etat` sait vérifier
est au vert.

## Syntropia et Plantae, chacun dans son sous-répertoire — fait le 2026-09-15

Rectification du porteur, après coup : `jubile.alwaysdata.net` doit
héberger Syntropia **et** Plantae, sur le même compte — chacun dans son
propre sous-répertoire (`/syntropia/`, `/plantae/`), pas Syntropia seul
à la racine comme déployé quelques heures plus tôt dans la journée.

Trois valeurs à tenir assorties, toutes corrigées ensemble : `RewriteBase`
et le motif du `RewriteCond` dans `.htaccess` (racine du dépôt),
`'base'` dans `config/base.php` et `config/base.php.exemple`, et
`WWW_DISTANT` dans `bin/deployer.py`. Le motif du `RewriteCond` compte
autant que `RewriteBase` : `REQUEST_URI` est toujours absolu depuis la
racine du domaine, jamais relatif au fichier — un `!^/public/` oublié
en `!^/syntropia/public/` aurait laissé passer `config/` et `src/` en
clair (même piège que celui payé par le dépôt de référence le
2026-08-17, qui a une règle générée avec `{base}` pour cette raison
précise dans `preparer-depot.py`).

Ancien dépôt (fait plus tôt dans la journée, à la racine de `www/`)
supprimé par le porteur — via FileZilla en SFTP, `rm -rf` distant
refusé par la classification automatique de Claude Code, qui bloque les
suppressions distantes destructrices même appelées depuis un outil déjà
autorisé par ailleurs (`ssh`) ; contourné en le confiant au porteur
plutôt qu'en cherchant à passer outre.

**Reconstruit et revérifié sous `/syntropia/`** — même suite qu'au
premier déploiement, refaite entièrement : `.htaccess` (racine 200,
`src`/`config`/`sql`/`bin`/`contenu` tous 403, polices bien typées),
compte jetable créé en SSH, connexion réussie sur le site réel, avec
cette fois la redirection vérifiée précisément
(`/syntropia/conception-parcelles.php`, pas `/conception-parcelles.php`)
et le cookie de session confirmé cantonné au bon chemin
(`Path=/syntropia/` — sans quoi il aurait pu fuiter vers `/plantae/`
plus tard).

### `bin/deployer.py preparer` — pour qui dépose à la main

Le porteur préfère FileZilla à la ligne de commande pour ce nettoyage
— légitime, et ça pose une question que `fichiers` ne résout pas :
FileZilla a besoin d'UN dossier local à faire correspondre à `www/
syntropia/`, et rien dans le dépôt ne l'est déjà (`ELEMENTS` est une
sélection de huit entrées, pas un seul dossier — la racine du projet,
elle, porte aussi `CLAUDE.md`, `le-bureau/`, `skills-map/`… qui n'ont
rien à faire sur le serveur).

Nouvelle action, sur le modèle exact d'`application/bin/preparer-depot.py`
(dépôt de référence) : assemble `ELEMENTS` dans `../syntropia-depot`
(voisin du dépôt, jamais dedans — un `rglob` un peu large ne le
ramasserait pas par erreur, et il n'a pas besoin d'entrée `.gitignore`
puisqu'il est hors du répertoire suivi). Mêmes garde-fous que la
référence : `config/base.php` ne peut structurellement pas s'y trouver
(absent d'`ELEMENTS`, et un filet de sécurité le supprimerait quand
même s'il apparaissait) ; les fichiers binaires (polices, images) sont
listés à part avec un rappel — **un piège classique du FTP en mode
ASCII est de réécrire les fins de ligne, ce qui détruit un `.woff2`
sans que rien ne le signale : la page retombe juste sur sa police de
secours.**

## Le chantier « carte interactive du terrain », Phases 1 à 3 — fait le 2026-09-15

Sur demande explicite du porteur (« travailles maintenant sur la carte
interactive de conception-etude-site.php »), après deux reports
précédents (2026-09-14, réaffirmé au début de cette session) faute de
plan concret. Recherche préalable en deux passes d'agents (Explore puis
Plan) sur le dépôt de référence — `Calques.php`, `carte.js`, `calques.js`,
les trois API (`api-calques.php`/`api-cadastre.php`/`api-eau.php`),
`Cadastre.php`, `Eau.php`, `Vents.php`, `Relief.php`, `Altimetrie.php`,
`Geojson.php`, `Web/Parcelles.php`, `parcelle.php`, le schéma SQL — pour
partir de signatures et de tables vérifiées, pas supposées. Le porteur a
choisi la réplique complète (API externes comprises) plutôt qu'une
première tranche allégée, en toute connaissance du prérequis que ça
entraînait : `conception-parcelles.php` était un mockup statique.

Plan écrit et approuvé en 10 phases, conservé dans
`chantier-carte-interactive.md` (déplacé du répertoire de plans hors
dépôt, pour ne pas se perdre à la prochaine reprise). Phases 1 à 3
faites le jour même :

- **Phase 1** — `public/assets/js/carte.js`, port quasi identique du
  moteur de tuiles sans dépendance (Web Mercator, OpenStreetMap) de la
  référence. Chemin final sous `assets/js/`, pas `assets/` — corrigé
  après coup pour suivre la convention déjà posée par `etapes.js`/
  `gabarit.js`.
- **Phase 2** — `Sites::enregistrerParametres()` (nouveau, whitelist
  stricte `latitude`/`longitude`, pas de `Colonnes`/`Referentiel` comme
  la référence — absents côté cible et hors périmètre) ;
  `definition-site.php` pose désormais un vrai point sur la carte au
  lieu d'un champ texte libre.
- **Phase 3** — nouveau `src/Web/Parcelles.php` (`dUnSite`, `parId`,
  `creer`, `enregistrer` — whitelist code/nom/type puis les trois
  mesures `nb_planches`/`largeur_planche_m`/`passe_pied_m`, rien du
  reste de la fiche d'attributs, hors périmètre). `conception-
  parcelles.php` sort du mockup statique (« La Vadaux », « Parcelle A »
  codés en dur) : vrai site, vraie liste de parcelles, formulaire de
  création si aucune n'existe encore. Le plan SVG (bordures, planches)
  reste un prototype côté client, volontairement — `parcelle.plan` non
  branché, chantier séparé (`plan.php`/`ImplantationBordures` côté
  référence).

**Bug trouvé en testant, avant tout déploiement** : la validation
serveur des trois mesures n'acceptait pas la virgule décimale française
(`is_numeric("1,20")` vaut `false` en PHP) alors que le prototype SVG
voisin la gère déjà côté client (`nombre()` dans `conception-
parcelles.js`) — corrigé par une conversion `str_replace(',', '.', …)`
avant validation, même convention que le reste du site.

Prochaine phase du chantier : Phase 4, `src/Web/Calques.php` +
`public/api-calques.php`, testable au `curl` avant toute UI de dessin —
détail complet dans `chantier-carte-interactive.md`.

## Les mots techniques expliqués au survol — fait le 2026-09-15

Demande du porteur après un test manuel : la question « déjà en présence
sur le terrain » (pieds-mères, graines) sur `definition-site.php`
suppose un vocabulaire qu'un débutant n'a pas. Deux décisions séparées,
posées avec le porteur avant de coder :

1. **Déplacer, pas seulement expliquer** — le contenu a rejoint
   `realisation-materiel-vegetal.php`, qui posait déjà une question
   proche (bouturage/semis/division), en mieux contextualisée. Au
   passage, `definition-site.php` a été réorganisée en deux groupes
   visuellement distincts : « Le lieu » (administratif, mesurable sans
   expertise) et « Ce que vous observez » (climat) — remarque du
   porteur : séparer l'administratif du terrain.
2. **Un motif réutilisable partout** — plutôt qu'une explication au cas
   par cas. Tout mot technique se souligne en pointillés
   (`<span class="terme" data-explique="...">`) et montre sa bulle au
   survol ou au focus clavier — **réutilise la bulle déjà prévue par le
   gabarit** (`Gabarit.bulle.montrer/cacher`, jamais construite avant
   cette session) plutôt que le `title` natif du navigateur (lent, sans
   style, illisible au clavier). Nouveau `Documentation::glossaire()`/
   `::terme()` : même format que les livrets (`# Terme` + une phrase),
   nouveau fichier `contenu/glossaire.md`. Une page publique,
   `glossaire.php`, expose la même source en recherche filtrée — jamais
   de défilement même avec beaucoup de mots : le nombre de résultats
   montrés s'ajuste à la hauteur réelle disponible (mesuré en JS), pas
   à un compte fixe.

**Bug trouvé en écrivant `Documentation::glossaire()`** : le texte
brut destiné à la bulle (`bulle.textContent`, jamais du HTML) passait
par `strip_tags()` sur un texte déjà échappé par `paragraphes()` — une
apostrophe finissait donc en `&#039;` littéral dans la bulle au lieu
d'une apostrophe. Corrigé avec `html_entity_decode()` après
`strip_tags()`.

Posé sur les termes déjà rencontrés cette session (pieds-mères,
bouturage, semis, division, zone climatique USDA, RNA, saut du chat,
déchaumage, bâchage, décompacter, densimètre, labour) — sur les pages où
ils apparaissent réellement, pas partout où le mot existe dans
l'absolu, pour ne pas noyer le motif sous trop de soulignements.

## `connexion.php` ne montre plus le mockup à un compte neuf — fait le 2026-09-15

Trouvé par le porteur en testant manuellement avec un compte neuf
(`simulation`) : la connexion retombait directement sur
`conception-parcelles.php`, alors encore un mockup, sans jamais passer
par `definition-projet.php`. Cause : la redirection post-connexion était
écrite avant que `definition-projet.php` existe (passage obligé
d'ouverture de site, ajouté plus tôt dans le projet) et n'avait jamais
été mise à jour. Corrigée : `Sites::pourCompte()` détermine la
destination — sans site, `definition-projet.php` ; avec un site, comme
avant.

## Petit écran, deux vraies régressions trouvées sur capture d'écran réelle — fait le 2026-09-15

Le porteur a transmis deux captures d'écran prises sur son téléphone
(Firefox Android), à des moments différents de la session. Les deux ont
révélé de vrais bugs, ni l'un ni l'autre détectable en mesurant
`document.documentElement.scrollWidth`/`scrollHeight` — `overflow:hidden`
sur `html,body` (la règle « aucun défilement ») absorbe silencieusement
tout débordement réel sans qu'aucune mesure globale ne le signale. Seule
une vraie capture d'écran, ou la mesure directe d'un conteneur contre
son parent, le révèle — leçon retenue pour la suite, consignée dans
CLAUDE.md § Commandes.

**Première capture** — `documentation-saf.php` : la colonne « Chapitres »
(11rem fixes, `flex:0 0 11rem`) ne tenait plus à côté du contenu sous
30rem (~480px) ; combinée à `justify-content:center` sur un conteneur
plus large que l'écran, la moitié invisible se répartissait de chaque
côté, donnant l'impression que le texte du chapitre commençait déjà en
plein milieu, sans liste visible. Corrigé par un `@media (max-
width:30rem)` sur les deux livrets : la colonne passe en rangée de
pastilles qui passent à la ligne, au-dessus du contenu plutôt qu'à côté.
`definition-site.php` (construite cette session) avait le même défaut,
en pire : sous 30rem les deux colonnes s'écrasaient l'une sur l'autre,
et même une fois empilées correctement, le contenu dépassait encore —
`main.bureau` centre verticalement (`align-items:center`), donc un bloc
trop grand déborde pour moitié **au-dessus** de son propre conteneur (le
texte d'annonce chevauchait le bandeau) autant qu'en dessous (pied de
page coupé). Corrigé par un resserrement général (marges, hauteur de la
carte, zone de texte) et `align-items:flex-start` sur cette page
spécifiquement, pour que le débordement résiduel éventuel reste toujours
en bas, jamais en haut.

**Seconde capture**, plus tard la même session — `documentation-site.php` :
malgré le premier correctif, un chapitre long débordait encore
verticalement. Cause de fond différente : la longueur d'un chapitre
n'est pas bornée (c'est de la prose libre, écrite par le porteur), donc
aucun resserrement de marges ne peut suffire pour toujours — il fallait
une vraie pagination, mesurée contre le contenu réel. Nouveau
`assets/js/livret.js` : mesure la hauteur disponible (`#bureau`, moins
l'en-tête du chapitre et la barre de navigation entre chapitres),
regroupe les paragraphes en tranches qui y tiennent, montre une tranche
à la fois avec sa propre pagination (◂ 1/4 ▸) — « suivant » sur la
dernière tranche enchaîne sur le chapitre suivant, un seul mécanisme à
comprendre pour l'utilisateur. Étend explicitement la décision déjà
prise pour l'import GeoJSON de la carte (« le bureau suppose déjà du JS
partout ») : ce livret n'était sans JavaScript que tant qu'un chapitre
tenait toujours dans l'écran, ce qui n'est plus garanti dès qu'un
livret grandit. **Bug trouvé en testant avant déploiement** : le
sélecteur de pagination (`:scope > p`) happait aussi
`.doc-chapitre-repere` (« Chapitre X sur Y »), un `<p>` comme les autres
à ses yeux, la faisant disparaître de sa place — corrigé
(`:not(.doc-chapitre-repere)`). Corrigé au passage, même capture :
la tirette « Informations », fixe et centrée juste sous le bandeau,
recouvrait le haut de la page sur petit écran faute de marge — `#bureau`
gagne une marge du haut sous 30rem sur ces deux pages spécifiquement.

## `dossier-qualification.md` et la campagne avec cgpt — fait le 2026-09-15

Le porteur a confié à cgpt (ChatGPT) le rôle de responsable
qualification, pour produire des scénarios et cas d'utilisation sur 3
niveaux d'utilisateur (débutant/intermédiaire/avancé en agroforesterie
syntropique — la connaissance du domaine, pas l'aisance avec un site
web : l'app ne distingue pas encore les comptes par ce critère). Un
dossier de référence lui a été préparé (`dossier-qualification.md`,
racine du dépôt) : état réel phase par phase, ce qui est prototype vs
persisté, ce qui n'existe pas du tout — pour que les scénarios partent
de faits, pas de suppositions.

cgpt n'a pas Playwright disponible dans son environnement (pas de
Node/`npx`) ; la même limite existe ici — résolue en pilotant Chrome
headless directement via le protocole DevTools depuis Python
(`websocket-client`, déjà présent), sans aucune dépendance
supplémentaire. La technique (lancement de Chrome, driver minimal,
piège des cookies entre comptes de test) a été transmise à cgpt pour
qu'il tente de même si son environnement a Python.

**Incident, détecté et corrigé** : la campagne a signalé un
« blocage d'authentification réel » — un compte neuf (`qualification6`,
mot de passe vérifié valide) refusant la connexion sur le site local.
Vérification : les comptes `qualification` à `qualification6` avaient
tous été créés sur la base de **production** (alwaysdata), pas en
local — même piège que documenté plus haut
(`config/base.php` l'emportant sur `config/local.env` quand
`bin/compte.php` est appelé sans passer par `dev.py`), cette fois
déclenché par la campagne elle-même plutôt que par une main humaine ou
Claude. Les 6 comptes et leurs lignes associées ont été supprimés de la
production, vérifié (`SELECT COUNT(*) FROM compte` → 0). Leçon
consignée : ne jamais conclure d'un résultat de test sans vérifier
**dans quelle base** il s'est produit, surtout quand plusieurs
environnements partagent la même base de code.

## Prochaines étapes

Voir CLAUDE.md § Point de reprise § Prochaine étape pour la liste à
jour — ce fichier ne la duplique plus, pour ne pas la laisser dériver
comme la version précédente de cette section l'avait fait.
