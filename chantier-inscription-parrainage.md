# Inscription par parrainage — inviter, et rejoindre un projet

Demandé par le porteur le 2026-09-18. Deux cas d'usage :

1. **Inviter quelqu'un à créer un compte** pour utiliser l'atelier — la
   personne créera son propre projet.
2. **Inviter quelqu'un à rejoindre un projet existant** — la personne peut
   aussi, si elle le souhaite, créer son propre projet en plus.

## Ce qui existe déjà

- `invitation` (base `syntropia_comptes`) : code de 8 caractères, `parrain_id`,
  `filleul_id`, `site_id` (rattachement à un projet, jamais câblé), `expire_le`
  (30 jours), révocable, à usage unique. `Invitations::ouvrir()`, `parCode()`,
  `consommer()`, `dUnParrain()` — écrites, **jamais appelées** : aucune page ne
  crée un code, la première invitation n'a personne pour l'ouvrir (voir
  CLAUDE.md § Commandes, `dev.py compte`).
- `inscription.php` : crée le compte sur un code valide, mais ignore
  `site_id`.
- `membre_site` (`porteur`/`contributeur`/`lecteur`) et `participant` (une
  personne dont les réponses comptent) : le modèle multi-personnes est déjà
  là, chaque membre a ses propres réponses.
- `site.code_invitation` / `role_invitation` : un second mécanisme de code,
  côté site, prévu pour un compte existant. **Non utilisé ici** — un seul
  type de code, `invitation`, qui sert les deux cas (le schéma dit lui-même
  que ce sont « deux gestes » ; un seul code pour les deux évite d'avoir à
  choisir entre deux systèmes).

## Ce qui bloque : « un seul projet par compte »

`CLAUDE.md` tenait « un seul projet par compte » pour décidé, et 17 pages
appellent `Sites::pourCompte()`, qui renvoie *le* site du compte. Le cas 2
(rejoindre un projet **et** avoir le sien) rouvre cette décision — de fait
tranchée par le porteur le 2026-09-18.

**Solution choisie : un « projet actif »** mémorisé dans un cookie, revérifié à chaque lecture. `pourCompte()`
renvoie le projet actif (à défaut, le dernier utilisé, puis le premier) : les
17 pages continuent de fonctionner **sans être touchées**. Un sélecteur dans
le panneau gauche change le projet actif.

## Décisions tranchées (2026-09-18)

- **Qui invite à créer un compte** : tout utilisateur connecté (parrainage
  au sens propre — le code garde la trace de qui a invité qui).
- **Qui invite à un projet** : le porteur seul, qui choisit à la création du
  code le rôle donné : `contributeur` (écrit) ou `lecteur` (regarde).
- **Passer d'un projet à l'autre** : sélecteur dans le panneau gauche,
  jamais un écran de choix à chaque connexion.

## Phases

```
Phase 1  Plusieurs projets par compte — projet actif, sélecteur, « Nouveau projet »
Phase 2  Inviter — page « Inviter », création/liste/révocation des codes (cas 1 et 2)
Phase 3  S'inscrire avec un code de projet — rattachement au projet (cas 2, nouveau compte)
Phase 4  Rejoindre un projet avec un compte existant (cas 2, compte déjà là)
```

### Phase 1 — Plusieurs projets par compte — ✅ fait le 2026-09-18

**Fichiers**
- `src/Web/Sites.php` : `deCompte()` (tous les projets d'un compte, avec le
  rôle), `activer()` (retient le projet actif par cookie, refuse un projet
  dont le compte n'est pas membre), `pourCompte()` renvoie le projet actif.
  `creer()` inchangée.
- `public/api-projets.php` : GET → `{projets, actif, csrf}` ; POST →
  change le projet actif (jeton + appartenance vérifiés).
- `public/definition-projet.php` : ne renvoie plus systématiquement un compte
  qui a déjà un projet — `?nouveau=1` ouvre un projet de plus.
- `public/assets/js/gabarit.js` : au `init`, charge `api-projets.php` et
  injecte dans le panneau gauche la section « Mes projets » (sélecteur +
  « Nouveau projet ») — **aucune des ~27 pages n'a à changer**.
- `public/connexion.php` : inchangé (`pourCompte()` fait déjà le bon choix).

**Test** : deux projets sur un compte, bascule, chaque page lit bien le
projet actif ; un compte qui n'est pas membre ne peut pas activer un projet ;
un projet actif dont on a été retiré retombe sur un autre sans erreur.

**Écart avec ce qui était annoncé, tranché en codant** : la promesse « le
dernier projet utilisé se rouvre à la connexion » est tenue par un cookie
(`syntropia_projet`, un an), pas par la session — la session est vidée à
chaque connexion et déconnexion, elle aurait tout oublié. Pas de colonne
en base. Le sélecteur ne s'affiche qu'avec au moins deux projets ;
« Nouveau projet » est toujours dans le menu du compte.

**Vérifié en CDP** : un projet → pas de sélecteur ; deux projets, bascule
dans les deux sens, choix retenu au rechargement ; une commune saisie dans
le projet 1 n'apparaît pas dans le projet 2 ; cookie forgé vers le projet
d'un autre compte → retombe sur le premier sans erreur ; POST d'activation
d'un projet étranger → 403 ; anonyme → 403.

### Phase 2 — Inviter — ✅ fait le 2026-09-18

**Fichiers**
- `sql/00-comptes.sql` : `invitation.role_site ENUM('contributeur','lecteur')
  NULL` — le rôle **dans le projet**, distinct de `role_accorde` (le rôle du
  compte dans l'application). ⚠ `CREATE TABLE IF NOT EXISTS` ne rattrape pas
  une table déjà en production : prévoir un `ALTER TABLE … ADD COLUMN IF NOT
  EXISTS` à part, rejoué par `bin/deployer.py base` (voir CLAUDE.md, incident
  `espece.variete`).
- `src/Web/Invitations.php` : `ouvrir()` prend le rôle de projet ; vérifie
  que le parrain est bien **porteur** du projet visé.
- `public/inviter.php` : page sur le gabarit — créer un code (pour un
  compte seul, ou pour le projet actif si porteur, avec le rôle), lister les
  siens avec leur état, révoquer. Entrée « Inviter » ajoutée au menu du
  compte par `gabarit.js` (même mécanique que Phase 1).

**Écarts avec ce qui était annoncé, tranchés en codant**

- **Migration portable** : `ADD COLUMN IF NOT EXISTS` n'existe que sur
  MariaDB (production) ; le poste local est en MySQL 8, qui le refuse. La
  migration de `sql/00-comptes.sql` passe par `information_schema` + une
  instruction préparée, et tourne des deux côtés, rejouable. Vérifiée en
  local sur une table qui n'avait pas la colonne (elle l'a ajoutée), puis
  rejouée sans effet. **En production : `bin/deployer.py base` la posera** ;
  le `courriel_vise` de la table n'est pas exposé (rien ne le vérifie).
- **Plafond de 10 codes ouverts** par personne (`MAX_OUVERTES`), dans
  `Invitations::ouvrir()` — pas prévu au plan : sans lui, la page est un
  robinet à codes. Le plafond compte les codes *ouverts* (ni utilisés, ni
  retirés, ni expirés) : en retirer un libère une place.
- **Garde-fous dans le service, pas seulement dans la page** : porteur
  seulement, rôle dans `contributeur`/`lecteur` (jamais « porteur »).
- **Le code n'est pas dans l'URL** : flash de session lu une seule fois
  (`syntropia_code_ouvert`), pour qu'il ne reste pas dans l'historique. Le
  lien complet se compose côté navigateur, depuis l'adresse réelle.
- **Liste paginée par 3** (bureau sans défilement) ; **quand un code vient
  d'être ouvert, la liste cède la place à un lien** — sans quoi, à
  1024×600, le cadre était tronqué en silence par `overflow:hidden`
  (mesuré : haut du cadre à −1 px).

**Vérifié en CDP** : menu du compte (« Nouveau projet », « Inviter ») ; code
pour un compte seul → `site_id`/`role_site` NULL en base, code absent au
rechargement ; code pour le projet, rôle lecteur → `site_id` du projet,
`role_site = lecteur` ; le rôle ne s'affiche que si « Rejoindre » est
choisi ; 10 codes ouverts → le 11e refusé avec son message ; retirer un code
libère une place ; un contributeur ne voit que « Créer un compte », un POST
forgé `pour=projet` est traité comme compte seul, et `Invitations::ouvrir()`
appelée directement pour son compte lève l'exception ; retirer le code d'un
autre parrain est sans effet ; anonyme → connexion. Hauteurs mesurées, cadre
dans le bureau, avec et sans code : 1280×720, 1024×600, 412×800, 412×915,
360×740. **Non tenu : 320×568** (cadre 12 px trop haut) — pas un format
visé, à reprendre si les très petits écrans comptent.

**Pas encore câblé** : `inscription.php` ignore toujours `site_id`. Un code
« Rejoindre » ouvre donc aujourd'hui un compte, sans rattachement — c'est la
Phase 3.

### Phase 3 — S'inscrire avec un code de projet

- `public/inscription.php` : à la consommation d'un code portant `site_id`,
  ajoute le nouveau compte à `membre_site` (rôle du code) et lui crée son
  `participant`. Le message dit à quel projet la personne est rattachée.
- La première connexion arrive sur le projet rejoint ; « Nouveau projet »
  (Phase 1) permet d'ouvrir le sien.

### Phase 4 — Rejoindre avec un compte existant

- `public/rejoindre.php` : saisie d'un code par un compte connecté — même
  code, même consommation, mêmes garde-fous (déjà utilisé, expiré, révoqué) ;
  refuse poliment si la personne est déjà membre du projet.

## À vérifier en codant

- Les pages de Définition enregistrent les réponses par `participant` : un
  **lecteur** ne doit rien pouvoir écrire, y compris dans ses propres
  réponses — contrôler chaque page, pas seulement `peutEcrireSur()` là où il
  est déjà appelé.
- Retirer un membre / quitter un projet : hors périmètre de ce chantier, à
  noter — sans lui, un membre invité par erreur ne peut être retiré qu'à la
  main en base.
- Un porteur qui invite à son projet ne doit jamais pouvoir toucher aux
  droits de quelqu'un d'autre sur un autre projet.
