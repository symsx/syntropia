# Documentation de l'atelier — mise à jour, puis maintenance

**Confié à Claude par le porteur le 2026-09-19** : « cela me prendrait trop de temps
pour que cela corresponde à ce qui est déployé, il faut d'ailleurs la maintenir suite
aux évolutions. Prévois donc ce chantier. » **Phases 1 à 3 faites le 2026-09-19 ; le livret n'est pas encore déployé** (voir § État).

Deux choses distinctes dans cette demande, et deux réponses :
1. **Rattraper** — `contenu/documentation-atelier.md` (le livret « L'atelier », page
   `documentation-site.php`) date des premiers jours de la refonte : il décrit un site
   qui n'existe plus tel quel. Une réécriture, une fois.
2. **Maintenir** — que le livret ne redevienne plus jamais périmé. Une règle de travail
   et un garde-fou mécanique, parce qu'une bonne intention ne tient pas sur des dizaines
   de changements.

## Ce que le livret est — et n'est pas

Trois textes, trois rôles, qu'il faut ne pas mélanger :

| Texte | Dit | Écrit par |
|---|---|---|
| **Livret « L'atelier »** (`documentation-atelier.md`) | Comment **l'outil** est fait et ce qu'on y trouve : le parcours, les pages, les projets, les comptes | **Claude**, maintenu à chaque évolution |
| Livret « SAF » (`documentation-saf.md`) | Les **principes** de l'agroforesterie syntropique | Le porteur (fait, déployé le 2026-09-19) |
| Panneau **Informations** de chaque page | Le mode d'emploi **de cette page**, champ par champ | Le code de la page |

Conséquence : le livret « L'atelier » donne **la vue d'ensemble** — où l'on va, ce que
chaque phase apporte, ce qu'on peut faire sur la carte — et **renvoie** au panneau
Informations pour le détail d'une page. Il ne le recopie pas : deux textes qui disent la
même chose divergent.

**Niveau de détail : un aperçu, pas un manuel.** Le porteur l'a dit pour le livret SAF
(« le but n'est pas d'aller dans le détail mais d'avoir un *overview* ») ; je l'applique
ici, et je le lui confirme avant de rédiger.

## Ce qui ne va pas dans le livret actuel

Constaté en le relisant (83 lignes, six chapitres) :

- **Périmé** : « Un compte donne accès à un projet » (un compte en a plusieurs depuis le
  2026-09-18) ; « d'autres documentations viendront » (le livret SAF est écrit) ; rien sur
  la carte, le scénario, les parcelles, les rôles, l'invitation, les préférences.
- **Inachevé** : « Contient ; » et « retrouver … une place honorable … et » (chapitres
  « Plan du site » et « L'intention »), « en se basant sur des ressouces de référence : »
  sans suite.
- **Illisible par le moteur** : les listes (`- …`) et les sous-listes de « Plan du site »
  et de « L'intention » **fusionnent en un seul bloc** — le livret ne lit que les lignes
  `# `, le gras et les paragraphes (voir `src/Documentation.php`).
- **Fautes** : « ressouces », « citoyen.e », « Note importante ; ».

## Règles de rédaction

1. **Le livret décrit ce qui est en production** (`git` : ce qui a été déployé), **jamais
   ce qui est prévu**. C'est la règle du CLAUDE.md « définir par ce que sont les choses et
   taire ce qu'elles ne sont pas » : pas de chapitre « à venir », pas de « bientôt ». Un
   chapitre s'écrit **avec** le déploiement de ce qu'il décrit, pas avant.
2. **Format du moteur** : un chapitre par ligne `# `, paragraphes séparés par une ligne
   vide, gras en `**…**`. **Ni liste, ni `##`, ni lien, ni image.** Un chapitre est une page
   que l'on tourne : court, un paragraphe = une idée.
3. **Vouvoiement**, ton du livret actuel ; un mot technique s'écrit simplement ou se
   définit à côté (les bulles du glossaire ne fonctionnent pas dans un livret).
4. **Les chapitres écrits par le porteur ne se réécrivent pas** — « L'intention » est sa
   voix. Je corrige l'orthographe, je signale ce qui est inachevé, et je **lui** demande de
   finir.
5. **Chaque chapitre dit à quelles pages il correspond** (voir le garde-fou, Phase 3) —
   c'est ce qui permet de savoir quoi relire quand une page change.

## Plan des chapitres (à valider avec le porteur avant de rédiger)

Onze chapitres, dans l'ordre où une personne découvre l'outil :

1. **L'atelier Syntropia** — à quoi il sert ; l'avertissement « aide, pas formation ».
2. **L'intention** — la voix du porteur (à finir par lui).
3. **Où trouver quoi** — l'accueil, la connexion, les deux livrets, le glossaire ; ce qui
   est public et ce qui demande un compte (remplace « Plan du site »).
4. **Le parcours en quatre phases** — Définition, Conception, Réalisation, Suivi : ce que
   chacune apporte ; on avance dans l'ordre, on revient quand la vie du projet le demande.
5. **Définir son projet** — ouvrir un projet ; la vision, les objectifs, les moyens, le
   site (le lieu, ce qu'on observe, la conduite) ; **à quoi sert chaque réponse** plus loin.
6. **Concevoir : la carte du terrain** — les calques, tracer, placer et repositionner une
   parcelle, les étiquettes, les fonds de carte ; ce que la carte va chercher pour vous
   (cadastre, eau, vents, relief, ombres) et ce qu'elle n'enregistre pas.
7. **Concevoir : les parcelles et le scénario** — le plan d'une parcelle (bordures,
   planches), le scénario de plantation, le calendrier ; les deux conduites.
8. **Réaliser et suivre** — préparer, consigner ; l'arrosage, la perturbation, le saut du
   chat.
9. **Le bureau** — le menu, les deux panneaux, la barre d'outils, le thème ; « pas de
   défilement » ; sur téléphone.
10. **Comptes, projets et rôles** — l'inscription sur invitation, plusieurs projets,
    porteur / contributeur / lecteur, les préférences.
11. **Inviter et rejoindre** — ouvrir un code, rejoindre un projet, voir et retirer les
    participants.

Ce que le livret **ne dit pas** : l'étape « Matériel et eau » (pas encore construite), la
suppression ou l'export d'un projet (spécifiés, pas construits). Il se tait, comme la règle
1 l'exige, jusqu'à ce que ce soit en production.

## Phases

### Phase 1 — l'état des lieux, vérifié

Avant d'écrire une ligne : un **inventaire** de ce qui est réellement en ligne, page par
page, pour ne pas décrire de mémoire.

- Sources : `assets/js/etapes.js` (les 19 étapes prêtes sur 20), les pages de `public/`,
  le menu du compte (`gabarit.js`), les panneaux Informations, CLAUDE.md § Point de reprise.
- **Vérifier contre la production**, pas seulement le dépôt : `git log` depuis le dernier
  déploiement consigné, puis `rsync -c` local/distant (la méthode des trois vagues
  précédentes) pour s'assurer que ce qu'on décrit est bien en ligne.
- Livrable : un tableau **page → ce qu'elle permet → chapitre qui en parle**, en annexe de
  ce fichier. C'est aussi la première version de la table de couverture de la Phase 3.

### Phase 2 — la réécriture

- Les onze chapitres, dans le format du moteur ; orthographe et ponctuation relues.
- **Vérifié comme le livret SAF l'a été** : le moteur les lit (nombre de chapitres et de
  paragraphes), aucun chapitre ne déborde de l'écran de 1280×720 à 320×568 (mesure en CDP
  de chaque chapitre, plus captures), la navigation « précédent / suivant » tourne tous les
  chapitres.
- « L'intention » : corrigée seulement, et **question au porteur** pour les phrases
  inachevées.
- Déploiement avec l'accord du porteur, `fichiers` seul (aucun schéma) ; consigné.

### Phase 3 — le garde-fou

Une règle ne suffit pas. Deux mécanismes, légers :

1. **Une règle dans le CLAUDE.md** (§ Directives de travail) : *toute évolution visible
   d'un utilisateur — une page, un outil, un rôle, un libellé — met à jour
   `contenu/documentation-atelier.md` **dans la même vague** que le code, et le chapitre
   concerné dit ce qui est en production.* Et le rappel : ne décrire que ce qui est déployé.
2. **`bin/verifier-documentation.py`** (Python, comme `deployer.py`, sans dépendance) :
   - une **table de couverture** chapitre → pages (`definition-site.php`, `conception-etude-site.php`…) ;
   - il liste les **pages non couvertes** (une page `public/*.php` ni exclue ni rattachée à un
     chapitre) ;
   - il liste les **pages modifiées depuis la dernière mise à jour du livret** (dernier
     commit touchant la page > dernier commit touchant `documentation-atelier.md`) ;
   - sortie : une liste de chapitres à relire, code de retour non nul s'il y en a — sans rien
     bloquer.
   - **Branché sur `bin/deployer.py fichiers`** : un avertissement avant l'envoi (pas un
     refus : une correction de style n'a pas à toucher le livret).

Limite assumée : le script sait **qu'une page a changé**, pas **si le changement se voit** ;
c'est la relecture du chapitre qui tranche.

### Phase 4 — le rythme

- À chaque **déploiement** : exécuter le vérificateur, relire les chapitres signalés,
  mettre à jour, déployer le livret **avec** le code.
- À chaque **nouvelle étape ou nouvel outil** : le chapitre est écrit dans le même commit.
- À chaque **chantier terminé** (dans CLAUDE.md § Point de reprise) : la ligne « livret
  relu / mis à jour » fait partie de la définition de « terminé ».

## Décisions à confirmer par le porteur

1. **Niveau de détail** : un aperçu, comme le livret SAF — oui ?
2. **Les onze chapitres** : cet ordre, ces regroupements ?
3. **« L'intention »** : ses phrases inachevées (« et », « en se basant sur des ressources
   de référence : ») sont à finir par lui, je n'invente pas sa voix.
4. **Rien sur ce qui n'existe pas encore** (« Matériel et eau », export, suppression) — pas
   de chapitre « à venir » : d'accord ?

## État — 2026-09-19

- **Phase 1 faite** : inventaire ci-dessous (§ Annexe), tiré des pages et de leurs panneaux
  Informations.
- **Phase 2 faite** : `contenu/documentation-atelier.md` réécrit, **onze chapitres** dans l'ordre
  validé par le porteur (aperçu, comme le livret SAF). Vérifié : le moteur lit les 11
  chapitres ; aucun chapitre ne déborde de 1280×720 à 320×568, pour les deux livrets (une
  colonne de 11 chapitres débordait de 8 px à 1024×600 : compactée sur écran peu haut, dans
  `documentation-site.php` et `documentation-saf.php`) ; orthographe relue (`aspell -l fr`).
- **Phase 3 faite** : `bin/verifier-documentation.py` (table de couverture chapitre → fichiers ;
  signale les pages non couvertes, et les chapitres à relire depuis la dernière relecture notée
  dans `bin/documentation-atelier.relu` ; `--relu` la note, `--court` sert `deployer.py`, qui
  **avertit sans jamais refuser**). Testé dans un clone jetable : page non couverte (code 2),
  modification commitée et non commitée (code 1), effacement par `--relu`. La règle de
  maintenance est dans le CLAUDE.md (§ Directives de travail) et en mémoire.
- **Phase 4** : le rythme — à chaque déploiement, lancer le vérificateur.
- **À faire par le porteur avant le déploiement** : finir **« L'intention »** — la phrase qui
  finit par « et » (« retrouver en tant qu'humain une place honorable dans ses impacts et »)
  — et la phrase du premier chapitre « en se basant sur des ressources de référence : »,
  qui n'a pas de suite. Je les ai laissées telles quelles (c'est sa voix).
- **Déploiement** : en attente de l'accord du porteur (`fichiers` seul, aucun schéma).

## Annexe — l'inventaire (Phase 1)

Ce qui est en production, page par page, et le chapitre du livret qui en parle.

| Page | Ce qu'elle permet | Chapitre |
|---|---|---|
| `index`, `connexion`, `inscription`, `deconnexion` | présentation, connexion, inscription sur code | Où trouver quoi · Comptes |
| `documentation-site`, `documentation-saf`, `glossaire` | les deux livrets, la recherche de mots | Où trouver quoi |
| `definition-projet` | ouvrir un projet (nom) — passage obligé | Définir son projet |
| `definition-vision`, `-objectifs`, `-moyens` | trois questions, trois questions, quatre questions ; réponses enregistrées | Définir son projet |
| `definition-site` | le lieu (carte, zone USDA estimée), les observations, la conduite — 3 onglets | Définir son projet |
| `conception-etude-site` | la carte : 10 calques, 7 outils, 7 fonds, cadastre / eau / vents / relief / ombres / GeoJSON | la carte du terrain |
| `conception-etude-sol` | sept relevés, fiches à imprimer (`releve-vierge`) — pas de saisie en ligne | parcelles et scénario |
| `conception-parcelles` | créer une parcelle, son plan (bordures, planches, sens), sa conduite | parcelles et scénario |
| `conception-scenario` | la frise (syntropie) ou la vue par strate (jardin-forêt), les productions | parcelles et scénario |
| `conception-calendrier` | un repère de rythme saison par saison, à main levée | parcelles et scénario |
| `realisation-*` (4), `suivi-*` (4) | réponses enregistrées, une consignation qui se répète | Réaliser et suivre |
| `compte`, `api-projets` | Préférences ; changer de projet actif | Comptes, projets et rôles |
| `inviter`, `rejoindre`, `membres` | codes de parrainage ; rejoindre un projet ; participants et retrait | Inviter et rejoindre |

**Constats faits en relisant les pages** (à traiter à part, hors livret) :
- `conception-calendrier.php` et `realisation-outils-et-plans.php` disent dans leur panneau
  Informations que le **scénario de plantation** est « une pièce du parcours pas encore
  construite » : **c'est faux depuis le 2026-09-18** (il est en production). Textes à corriger.
- `conception-etude-sol.php` : « la saisie numérique de ces relevés n'existe pas encore » —
  exact, et le livret dit seulement « fiches à imprimer », sans le promettre.

## Fichiers critiques

- `contenu/documentation-atelier.md` — le livret ; le format est dans `src/Documentation.php`.
- `public/documentation-site.php` — la page qui le lit ; `assets/js/livret.js` — la
  pagination (corrigée le 2026-09-19 : chaque chapitre est mesuré, la rangée de chapitres
  est masquée sur téléphone).
- `assets/js/etapes.js` — le manifeste des étapes : ce qui est `pret` est ce qu'on décrit.
- `bin/deployer.py` — où se brancherait le vérificateur.
- `contenu/glossaire.md` — les mots expliqués au survol **ailleurs** que dans les livrets.
