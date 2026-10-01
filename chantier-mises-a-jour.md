# Chantier — mettre à jour l'atelier sans gêner ceux qui l'utilisent

*Proposé le 2026-10-01, à la demande du porteur, au moment où il invite des bêta-testeurs en production. **Phases 1
(la sauvegarde) et 2 (le mode maintenance) faites le 2026-10-01**, **vérifiées sur le serveur réel le
même jour** ; les suivantes au fil de l'eau, avant la version 1.*

## L'intention

Jusqu'ici, un déploiement ne touchait personne : la production n'avait que le compte du porteur. Dès le 2026-10-01,
des bêta-testeurs y travaillent ; ils sont prévenus que leurs travaux pourraient être supprimés lors d'une mise à jour,
en attendant la version 1. C'est une tolérance, non un projet : **une mise à jour ne doit rien effacer ni rien casser
de ce qu'une personne est en train de faire** — et, à partir de la version 1, c'est une promesse.

## Ce qui se passe aujourd'hui (constat du 2026-10-01)

| Situation | Effet |
|---|---|
| La personne est connectée | Elle le reste : les sessions sont des fichiers du serveur, le déploiement n'y touche pas |
| `base` rejoue le schéma | Migrations additives seulement ; le cadre des espèces (`site_id = 0`) est réécrit, les projets jamais |
| Entre `base` et `fichiers` | L'ancien code tourne sur la nouvelle base — prévu ; seul l'export refuse (`Export::accordCodeEtBase()`) |
| Pendant `fichiers` | Les fichiers partent un à un, `public/` **avant** `src/` : quelques secondes où une page nouvelle peut appeler une classe pas encore arrivée — une page d'erreur, rien d'écrit à moitié (transactions) |
| Une page restée ouverte sur l'ancienne version | Son formulaire part sans les champs nouveaux ; un champ absent peut remettre une valeur à zéro (la coche « vent » du plan, le 2026-10-01) |
| Une migration qui tournerait mal | Aucune sauvegarde de notre fait avant `base` ; seules celles de l'hébergeur (non vérifiées) |

## Phases

### Phase 1 — La sauvegarde avant chaque `base`

`bin/deployer.py sauvegarde` : un `mysqldump` (`--single-transaction`, sans verrou) des deux bases de production,
compressé sur le serveur, rapatrié sur ce poste, daté (`AAAA-MM-JJ_HHMMSS`), dans **`../syntropia-sauvegardes/`** —
voisin du dépôt, jamais dedans : ces fichiers contiennent les comptes (empreintes de mots de passe) et les projets des
personnes. Droits 600. Une sauvegarde n'est réputée faite que si le fichier se décompresse et se termine par la ligne
de fin de `mysqldump`. **`base` en fait une d'abord, et refuse de continuer si elle échoue.** Restaurer reste un geste à
la main (documenté ici), jamais automatique.

**Faite le 2026-10-01.** `sauvegarde()` dans `bin/deployer.py` : `mysqldump --single-transaction --no-tablespaces`, sous
`set -o pipefail` (un échec de mysqldump ne se cache pas derrière un gzip qui réussit), rapatrié dans un fichier créé en
600 ; un jeu incomplet (une base sur deux) est retiré en entier. `base` appelle `sauvegarde()` et s'arrête si elle
échoue. Vérifié : une vraie sauvegarde de la production (comptes 1 Ko, atelier 22 Ko — 22 tables, les deux projets, la
ligne de fin), et le cas d'échec (une base inexistante : refus, aucun fichier laissé). **Non vérifié : une restauration
complète** — l'utilisateur MySQL local n'a pas le droit de créer une base jetable ; à faire sur le site d'essai (Phase 6).

**Restaurer** (à la main, après avoir fermé l'atelier par `deployer.py maintenance on`) : envoyer le fichier sur le
serveur, puis `gunzip -c FICHIER.sql.gz | mysql -hHOTE -uUTILISATEUR -p NOM_DE_LA_BASE` — les identifiants sont ceux
de `config/base.php`. Le dump recrée chaque table (`DROP TABLE IF EXISTS` puis `CREATE TABLE`) : tout ce qui a été
écrit après la sauvegarde est perdu.

### Phase 2 — Le mode maintenance

Pendant un déploiement, toute page répond **« Mise à jour en cours »** (code 503, `Retry-After`), sur le gabarit du
bureau mais statique (`public/maintenance.html` : ni PHP ni JS, pour ne dépendre de rien qui soit en train d'être
remplacé). Personne ne voit de version mélangée, aucun envoi n'est reçu à moitié.

Mécanisme : **le `.htaccess` de la racine est remplacé**, le temps du déploiement, par une variante qui renvoie tout en
503 sauf la page de maintenance et ses feuilles de style et fontes ; puis le `.htaccess` normal est rétabli. Rien ne
change hors déploiement — pas de test de fichier à chaque requête — et le serveur de développement (`bin/dev.py`, qui
ignore les `.htaccess`) n'est pas concerné. La variante est générée par le déployeur, avec le même chemin
(`/syntropia/`) que le `.htaccess` normal.

`bin/deployer.py maintenance on|off|etat` ; `fichiers`, pendant une maintenance, n'écrase pas le `.htaccess` de
maintenance. **`bin/deployer.py mettre-a-jour`** enchaîne tout : vérifications, sauvegarde, maintenance, `base`,
`fichiers`, fin de maintenance, contrôle que la page d'accueil répond 200. Si une étape échoue, **l'atelier reste en
maintenance** et le déployeur dit quoi faire — mieux vaut un atelier fermé qu'à moitié à jour.

Vérifié sur un Apache réel : en local (Apache est installé sur ce poste) avant toute production.

**Faite le 2026-10-01.** `public/maintenance.html` (statique, `gabarit.css` seul : bandeau, bureau, pied — rien à
ouvrir) ; dans `bin/deployer.py` : `htaccess_maintenance()` (généré depuis le `RewriteBase` du `.htaccess` normal, une
seule source pour le chemin), `maintenance on|off|etat` (pose par fichier temporaire renommé : jamais un `.htaccess` à
moitié écrit ; `on` envoie d'abord la page), `fichiers` qui garde le `.htaccess` de maintenance s'il est en place,
`mettre-a-jour` (vérifications, maintenance, contrôle que l'accueil répond bien 503 — sinon on lève la maintenance et
l'on s'arrête —, `base` avec sa sauvegarde, `fichiers`, réouverture, contrôle du 200 ; une étape en échec laisse
l'atelier fermé et dit quoi faire). Vérifié sur un **Apache local** (instance à part, port 8091, mêmes modules que la
réécriture : `rewrite`, `headers`, `authz_core`) avec le `.htaccess` généré : accueil, pages, POST et `public/*.php` en
**503** avec la page et `Retry-After: 300`, `Cache-Control: no-store` ; feuilles de style et fontes en 200 ; `src/` et
`config/` toujours 403 ; rendu à 1280×720 et 360×740 (capture) ; `.htaccess` normal remis : l'atelier rouvre. 

**Vérifié sur le serveur réel le 2026-10-01** (à la demande du porteur, à un moment où aucun compte n'était connecté :
aucune session de Syntropia portant un compte modifiée dans les 30 minutes) : `maintenance on` — accueil, pages, POST et
`public/connexion.php` en 503 avec la page, `retry-after: 300`, `cache-control: no-store`, feuille de style et fonte en
200, `src/` et `config/` 403 ; `maintenance off` — accueil 200, page protégée 302, `.htaccess` du serveur identique au
local. Puis **`mettre-a-jour` de bout en bout** (35 s, une vingtaine fermé) : 503 contrôlé, sauvegarde, schéma, fichiers
(le `.htaccess` de maintenance gardé pendant l'envoi), réouverture, 200 contrôlé ; ensuite, données inchangées (2 projets,
1 parcelle, 12 réponses, 3 tracés, 143 espèces au cadre), aucun écart de contenu `rsync`.

### Phase 3 — L'ordre des envois, et les personnes connectées

- `src/` part avant `public/` (utile hors maintenance, et pour `fichiers` seul).
- Avant `mettre-a-jour`, compter les **sessions actives** (fichiers de session modifiés dans les 30 dernières minutes)
  et le dire — on choisit un moment calme, ou l'on prévient.

**Faite le 2026-10-01.** `ELEMENTS` envoie `sql/`, `src/`, `contenu/` avant `public/`. `sessions_actives()` compte les
sessions de Syntropia (marque `syntropia|`, le dossier est partagé avec Plantae) qui portent un compte (`compte_id`) et
ont bougé dans les 30 dernières minutes — rien d'autre n'est lu de ces fichiers ; `etat` le dit, et `mettre-a-jour`
demande confirmation au clavier s'il y a quelqu'un (lancé par un autre programme, il le dit et continue). Vérifié : en
production, personne (exact à ce moment) ; sur cinq faux fichiers de session (Syntropia connectée, Plantae, anonyme,
ancienne, vide), seule la première est comptée.

### Phase 4 — Les pages restées ouvertes

- **Un champ absent ne remet rien à zéro** : quand un formulaire n'envoie pas un champ, le serveur garde la valeur
  enregistrée. Relire chaque écriture (plan, scénario, potager, espèces…) sous cet angle.
- **« Une nouvelle version est en ligne, rechargez la page »** : chaque page porte le numéro de version de l'atelier
  (`Version::APPLICATION`) ; `gabarit.js` le compare à celui du serveur quand la page reprend la main, et le dit.

### Phase 5 — Le déploiement atomique

Chaque version dans son propre dossier, l'atelier qui bascule d'un coup par un lien symbolique, le retour arrière
immédiat. À vérifier d'abord : qu'alwaysdata sert un lien symbolique sous `~/www/`.

### Phase 6 — Un site d'essai

`/syntropia-essai/`, avec ses propres bases, où l'on rejoue une mise à jour sur une copie des données réelles avant
la production.

### Phase 7 — Prévenir

Une page « Quoi de neuf » avec le numéro de version ; les maintenances longues annoncées à l'avance.

## Règles qui valent dès maintenant

- **Ne jamais effacer de données de projet** par une mise à jour. Une colonne qui change se fait en deux temps :
  ajouter la nouvelle et recopier, retirer l'ancienne dans une version suivante.
- Avant un déploiement : penser aux personnes connectées (voir `beta-testeurs-en-production` dans la mémoire de travail).

## Questions ouvertes

Aucune. **Sauvegardes gardées : les cinq dernières** (décision du porteur, 2026-10-01) — `SAUVEGARDES_GARDEES` ; l'élagage
ne se fait qu'après une sauvegarde réussie, pour qu'un échec ne retire jamais la dernière bonne.
