# Dossier de référence — pour le responsable qualification

Préparé le 2026-09-15 pour servir de point de départ à un tiers (ici,
ChatGPT) tenant le rôle de **responsable qualification** sur Syntropia.
Objectif : produire des scénarios et cas d'utilisation, sur 3 niveaux
d'utilisateur (débutant, intermédiaire, avancé en agroforesterie
syntropique), à partir de ce qui existe **réellement** dans le projet —
pas de ce qu'on imagine qu'il devrait contenir.

Ce document décrit l'état du dépôt à cette date. Il vieillira — à
recouper avec `plan-du-site.md` et `CLAUDE.md` § Point de reprise avant
toute campagne de test, si du temps s'est écoulé.

---

## 1. Ce qu'est Syntropia

Un atelier d'accompagnement en ligne pour concevoir et conduire un
système agroforestier syntropique — de la première idée jusqu'au suivi,
saison après saison. Pas un simple formulaire : chaque question dit à
quoi sa réponse va servir, avant de la demander. Exemple déjà en place :
sur l'étape Moyens, cocher « moyens financiers faibles » fait apparaître
une suggestion vers la régénération naturelle assistée (RNA) — l'usage
de la réponse se montre, il ne se raconte pas après coup.

**Un seul projet par compte** — un site, plusieurs parcelles. Pas de
liste de sites à gérer.

**Règle de navigation stricte** : toute page construite tient dans
l'écran, sans aucun défilement (sauf le panneau droit « Données », qui a
le droit de défiler en interne). Une étape non construite n'apparaît
jamais comme un lien mort — le menu saute directement à la première
étape prête de la phase suivante.

---

## 2. Le parcours, phase par phase — état réel

Légende : **✅ réel** (persisté en base, testable de bout en bout) —
**🧪 prototype** (fonctionne à l'écran mais rien n'est enregistré) —
**⏳ absent** (le lien n'apparaît même pas dans le menu, sauté
automatiquement).

### Phase 1 — Définition (4/4 prêtes)

| Étape | Ce qu'on y fait | État |
|---|---|---|
| Passage obligé (`definition-projet.php`) | Ouvre le site (nom du projet) — une fois, avant tout le reste | ✅ |
| Vision | Qui porte le projet (seul/groupe), motivation, intention | ✅ |
| Objectifs | Productions principales/secondaires, destinations (loisir/vivrier/commercial/social), terme (court/moyen/long) | ✅ |
| Moyens | Connaissances, temps, moyens financiers (→ suggestion RNA si faibles), terrain disponible | ✅ |
| Site | **Le lieu** (commune, position sur une vraie carte OpenStreetMap cliquable, superficie, zone climatique USDA, exposition, pente) et **ce que vous observez** (climat particulier) — deux groupes distincts sur la page | ✅ |

### Phase 2 — Conception (3/6 prêtes, +1 hors numérotation)

| Étape | Ce qu'on y fait | État |
|---|---|---|
| Étude du site | Carte interactive du terrain — calques, dessin, vents, relief, cadastre | ⏳ chantier en cours, non commencé au-delà de la préparation (point-carte du site, vraies parcelles) |
| Étude du sol | 7 relevés (densité, infiltration, slake test, profil, vers de terre, flore, texture) — fiches à imprimer, à faire sur place | ✅ (liste + fiches papier ; la saisie numérique des résultats n'existe pas encore) |
| Créer les parcelles | Ouvrir une ou plusieurs parcelles (code, nom, type pérenne/potager), leur fixer 3 mesures (planches, largeur de planche, passe-pied) | ✅ pour la création et ces 3 mesures — **🧪 le plan lui-même** (bordures, planches dessinées dans un SVG) reste un prototype, rien n'est enregistré |
| Scénario de plantation | Espèces, strates, plan de plantation, associations culturales si potager | ⏳ absent |
| Matériel et eau | Récapitulatif du matériel végétal, besoins en eau, par parcelle | ⏳ absent |
| Calendrier | Le calendrier du projet, saison par saison | ✅ |

### Phase 3 — Réalisation (4/4 prêtes)

| Étape | Ce qu'on y fait | État |
|---|---|---|
| Préparation de la parcelle | Déchaumage ou bâchage, manuel ou par des animaux | ✅ |
| Matériel végétal | Ce qu'on multiplie soi-même (bouturage, semis, division — pieds-mères, graines déjà là) vs ce qu'on procure | ✅ |
| Outils et plans | Outils, moyens humains, notes de plan | ✅ |
| Consignation | Ce qui a été fait, quand, dans quelles conditions | ✅ |

### Phase 4 — Suivi (4/4 prêtes)

| Étape | Ce qu'on y fait | État |
|---|---|---|
| Arrosage et germination | Boutures prises ? Germination visible ? | ✅ |
| Perturbation | Taille/perturbation — geste qui relance la croissance | ✅ |
| Saut du chat | Le moment où le système accélère visiblement — suggestion si pas encore observé | ✅ |
| Consignation | Ce qui est observé, les résultats — boucle vers « Créer les parcelles », le suivi se répète | ✅ |

### Hors parcours

| Page | Rôle | État |
|---|---|---|
| Accueil (`/`) | Présente l'atelier, oriente | ✅ |
| Connexion / Déconnexion | Session | ✅ |
| Inscription | Sur invitation à 8 caractères uniquement — pas d'inscription libre | ✅ |
| Documentation « L'atelier » | Livret qu'on tourne, chapitre par chapitre | ✅ (contenu réel, tenu à jour par le porteur) |
| Documentation « L'agroforesterie syntropique » | Même format | ✅ (contenu encore un modèle à remplacer) |
| **Glossaire** | Recherche filtrée parmi les mots techniques ; même source que les bulles au survol des mots soulignés en pointillés, partout sur le site | ✅ |
| Autres documentations | — | ⏳ pas commencé |
| Mon compte / Préférences | Récapitulatif, inviter d'autres personnes | ⏳ pas commencé |

---

## 3. Ce que le glossaire couvre déjà (utile pour juger le niveau « débutant »)

Le site souligne en pointillés et explique au survol/focus : **pieds-mères,
bouturage, semis, division, zone climatique USDA, RNA, saut du chat,
déchaumage, bâchage, décompacter, densimètre, labour**, plus quelques
autres termes de sol (ressuyage, témoin, hydromorphie, infiltration,
slake test, profil de sol, perturbation) visibles dans le glossaire même
s'ils ne sont pas encore soulignés partout où ils apparaissent. Page
`glossaire.php`, recherche en direct, jamais de défilement (les
résultats en trop demandent d'affiner la recherche plutôt que de
scroller).

Ce que le glossaire NE couvre PAS encore : vocabulaire des 7 fiches de
sol prises individuellement (slake test, hydromorphie sont dans le
glossaire mais pas soulignés sur la page qui liste les fiches), et tout
le vocabulaire des étapes non construites (scénario, matériel-et-eau,
carte interactive).

---

## 4. Comptes et accès — ce qui conditionne un scénario de test

- **Aucune inscription libre.** Un compte se crée soit en CLI
  (`bin/dev.py compte`, pour le tout premier compte), soit via
  `inscription.php` avec un code d'invitation à 8 caractères déjà émis.
- Une fois connecté sans site : redirection automatique vers
  `definition-projet.php` (passage obligé).
- Avec un site : redirection vers « Mon projet »
  (`conception-parcelles.php`).
- Un rôle « lecture seule » existe dans le modèle (`membre_site.role`)
  mais aucune page ne le distingue encore visuellement à part la carte
  interactive (pas construite) — à vérifier si c'est déjà pertinent pour
  un scénario.

---

## 5. Bugs récemment corrigés — bons candidats de non-régression

- Un compte tout juste inscrit ne doit **jamais** atterrir sur
  `conception-parcelles.php` avec des données d'un autre projet — c'était
  le cas avant une correction du 2026-09-15 (redirection post-connexion
  qui ignorait l'absence de site).
- Les nombres décimaux saisis à la française (virgule : « 1,20 ») doivent
  être acceptés partout où un nombre est demandé en texte libre — un cas
  y a échappé (mesures de parcelle) avant correction.
- Une définition de glossaire trop longue ne doit jamais couper le texte
  au milieu d'un mot sur la page Glossaire — le nombre de résultats
  affichés s'ajuste à la hauteur réelle, pas à un chiffre fixe.
- Vider un champ déjà rempli doit effacer la réponse enregistrée (pas la
  garder en base comme une chaîne vide) — comportement voulu, à vérifier
  qu'il tient sur chaque étape du parcours.

---

## 5. Constats de campagne et consignes pour les prochains tests

### Ce qui a été observé

- Un même navigateur Chrome, relancé sans profil isolé, conserve la session HTTP et les cookies de `SYNTROPIA` ; il n'est donc pas comparable à un utilisateur neuf.
- Un compte ayant déjà un site ne repasse plus par `definition-projet.php` : il redirige directement vers la phase de conception, ou vers la page qu'il venait de terminer. Il faut donc le considérer comme un compte "déjà avancé" et non comme un compte vierge.
- Les tests successifs sur un compte réutilisé créent un faux négatif : la page ressemble à une erreur fonctionnelle alors qu'elle est simplement dans un état métier déjà rempli.
- Le parcours logique est donc : `connexion.php` → `definition-projet.php` si pas de site → `definition-vision.php` → ... ; si la route initiale est `conception-parcelles.php`, le test n'a plus la même valeur métier.

### Consignes de test à appliquer à chaque campagne

1. Toujours partir d'un navigateur entièrement propre : tuer les Chrome headless actifs, puis relancer avec un `--user-data-dir` unique et un port de débogage unique.
2. Toujours utiliser un compte frais pour une campagne : un compte = une session de qualification. Ne pas réutiliser `qualification`, `qualification2`, etc. pour un autre test.
3. Vérifier avant tout formulaire la bonne URL d'arrivée :
   - `/connexion.php` au départ ;
   - `/definition-projet.php` si le compte est vierge ;
   - `/conception-parcelles.php` si le compte a déjà un site.
4. Si l'URL de départ annonce un compte avancé, arrêter le cas de test et créer un nouveau compte. Ne pas tenter de "forcer" le parcours débutant dans une session déjà engagée.
5. Pour chaque campagne, enregistrer le nom du compte, la date, le port de débogage et le profil Chrome, afin que le résultat reste reproductible.
6. Ne pas conclure sur un parcours sans vérifier la route réelle du navigateur : c'est la route qui dit si le compte est reset ou s'il continue un projet existant.

### Règle de vigilance pour les jours à venir

La campagne ne doit jamais être évaluée comme "échec de login" ou "échec de parcours" si le navigateur a conservé une session précédente. Dans ce projet, le vrai garde-fou est l'état du compte et le profil du navigateur, pas seulement le code HTML renvoyé.

---

## 6. Pistes pour les 3 niveaux d'utilisateur

Ces niveaux portent sur la **connaissance en agroforesterie
syntropique**, pas sur l'aisance avec un site web — l'app elle-même ne
distingue pas encore les utilisateurs par ce critère (pas de profil
« niveau » stocké). Les scénarios peuvent donc porter sur *ce que
chaque profil remarque ou attend différemment* :

- **Débutant** : n'a jamais entendu « bouturage », « RNA », « ressuyage »,
  « saut du chat ». Scénario naturel : parcourt tout le site Définition →
  Suivi en s'appuyant fortement sur les bulles au survol et le glossaire ;
  juge si le texte accompagnant chaque question (l'« usage » annoncé)
  suffit à répondre sans sortir du site chercher une définition ailleurs.
- **Intermédiaire** : connaît les gestes de base (jardinage, un peu de
  permaculture) mais pas le vocabulaire syntropique précis (strates,
  associations culturales, RNA). Scénario naturel : va plus vite,
  s'arrête sur les termes vraiment spécifiques seulement, teste si la
  suggestion RNA (étape Moyens) est perçue comme utile ou comme du bruit.
- **Avancé** : pratique déjà la syntropie. Scénario naturel : cherche
  vite les champs techniques (zone USDA, exposition, pente, les 7 fiches
  de sol, les 3 mesures de parcelle pour la carte) ; bon profil pour
  juger si les protocoles de relevé de sol (densité, infiltration, slake
  test…) sont fidèles à la pratique réelle, et si des champs utiles
  manquent.

Un même scénario de bout en bout (créer un projet, le mener de Définition
à Suivi) peut donc se rejouer trois fois, avec des points d'attention
différents à chaque niveau plutôt que trois parcours différents.

---

## 7. Ce qui n'existe pas — à ne pas inventer de scénario dessus

- Carte interactive du terrain (calques, dessin, cadastre, vents,
  relief, import GeoJSON) — chantier commencé, très partiel.
- Scénario de plantation et Matériel-et-eau (les deux étapes Conception
  restantes) — aucune page.
- Fiche d'attributs complète d'une parcelle (sol, exposition propres à
  la parcelle, historique) — seules 3 mesures minimales existent.
- Éditeur de planches persistant (le SVG de `conception-parcelles.php`
  ne sauvegarde rien).
- Page compte/préférences, invitation depuis l'interface.
- Récapitulatif ou tableau de bord global — rien ne relit encore
  l'ensemble des réponses enregistrées pour les présenter.
- Saisie numérique des résultats de relevé de sol (seules les fiches
  papier existent).

---

## 8. Retour attendu

Une fois les scénarios et cas d'utilisation produits par cgpt, les
transmettre tels quels — ils seront confrontés au code réel (garde
d'accès, validations serveur, persistance effective) avant toute
correction, plutôt que supposés corrects par construction.

---

### Correction du faux signal de 2026-09-15

Le constat initial était une fausse alerte : il ne s'agissait pas d'un bug d'authentification. La cause réelle est un mélange d'environnements :

- `php bin/compte.php` lancé directement, sans passer par `python3 bin/dev.py compte`, retombe silencieusement sur `config/base.php` — donc sur la base de production (AlwaysData) — au lieu de la config locale.
- Les comptes `qualification`, `qualification2`, ... jusqu'à `qualification6` avaient donc été créés sur la base de production, pas dans la base locale du serveur web sur `http://127.0.0.1:8081/`.
- Le test de connexion a résulté en échec uniquement parce que la base interrogée par le navigateur local et la base utilisée pour la vérification CLI étaient différentes.
- Le `password_verify()` "OK" ne prouvait pas un bug applicatif : il validait un mot de passe dans un autre environnement, pas celui de la session HTTP de test.

### Nettoyage effectué

- Les 6 comptes qualification créés en production, ainsi que leurs lignes associées, ont été supprimés.
- Vérification faite : `SELECT COUNT(*) FROM compte` sur la base de production → `0`.
- La base locale a été laissée intacte, et le souci de contamination de config a été bien documenté comme piège de test récurrent.

### Règle de sécurité pour les prochaines campagnes

1. Ne jamais créer un compte avec `php bin/compte.php` hors du bon contexte de développement.
2. Toujours passer par `python3 bin/dev.py compte` pour créer un compte local, ou vérifier explicitement le fichier de config actif.
3. Ne jamais comparer une vérification de mot de passe sur une base locale avec un test de connexion sur une autre base.
4. Avant de conclure à un bug d'authentification, vérifier la base effective, le `SYNTROPIA_DB_*` en cours, et le contexte de lancement.

Cette correction doit remplacer le précédent faux constat dans les dossiers de test et les campagnes futures.
