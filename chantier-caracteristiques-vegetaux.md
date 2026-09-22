# Les caractéristiques des végétaux pour le calendrier de plantation — plan

> **État au 2026-09-22 — Phase 1 (étendre `espece`) faite, pas déployée ; Phases 2 et 3 pas commencées.**
> Demandé par le porteur : pour composer un calendrier de plantation, l'atelier doit connaître les
> caractéristiques des plantes que la personne veut cultiver — de production, ou de service (biomasse,
> bloque-herbe, tuteur pour grimpantes…). Deux sources possibles : ce que l'atelier connaît déjà en interne,
> ou ce que le porteur de projet importe. Quatre décisions du porteur cadrent ce chantier (2026-09-22) — voir
> § Décisions.

## Ce que le porteur demande

Le calendrier de plantation d'un potager (`conception-potager.php`, onglet Calendrier) ne connaît aujourd'hui
que dix-sept cultures, celles du tableau de Lopez (`CalendrierLopez`) — tout le reste n'a « pas de repère »,
la date est à la personne. Le porteur propose d'élargir ce que l'atelier sait, par deux voies qui se
complètent :

1. **Ce que l'atelier connaît déjà** — le référentiel `espece` (cadre partagé de 46 espèces + surcharges par
   site) existe, mais il n'a **aucune colonne de calendrier** (semis, plantation, récolte). Il porte déjà
   `role`, `strates_possibles`, `espacement_*`, pensés pour le scénario pérenne.
2. **Ce que le porteur importe** — un fichier de ses propres semences ou plantes (exemple fourni :
   `base_semences_planification_maraichage.csv`, une banque personnelle de 37 variétés, triée séparément —
   voir `base_semences_planification_maraichage_nettoyee.csv` et son rapport, hors de ce dépôt).

## Décisions du porteur, 2026-09-22

1. **Portée : le potager d'abord.** Ce chantier nourrit `succession_ligne` (les cultures d'un potager, placées
   par planche) — pas le scénario pérenne (`scenario_ligne`, verger, jardin-forêt) pour l'instant. Une
   extension au pérenne resterait à ouvrir séparément.
2. **Mécanisme : étendre le référentiel `espece`.** Les caractéristiques de calendrier rejoignent la table
   `espece` existante (cadre partagé `site_id=0` + surcharges par site) plutôt qu'une table séparée. Ce
   référentiel a un service (`Especes::enregistrer()`/`retirer()`) **jamais relié à une page** — ce chantier
   construit enfin cette page (consultation, édition manuelle, et import).
3. **Le fichier fourni : nettoyé à part, pas encore importé.** `base_semences_planification_maraichage.csv`
   a servi de premier cas réel pour comprendre la forme d'un import (voir le nettoyage, hors de ce dépôt : une
   seule anomalie structurelle, répétée dix fois — une colonne vide en trop avant la note — corrigée
   mécaniquement ; rien d'agronomique n'a été changé ni inventé). Il **n'est pas encore importé dans
   l'atelier** : la mécanique d'import reste à construire (Phase 2), et ce fichier en sera le premier essai
   réel une fois prête.
4. **Le vocabulaire des rôles de service : celui du scénario pérenne.** Une culture de potager qui sert de
   biomasse, de bloque-herbe ou de tuteur pour une grimpante reprend `Scenario::FONCTIONS` (déjà écrit pour
   `scenario_ligne`), plutôt qu'un vocabulaire propre au potager. **Fait en Phase 1** (voir § Phases) :
   `succession_ligne.role` — une seule valeur (`biomasse`) depuis le 2026-09-21 pour le saut du chat — devient
   multivalué par `|`, comme `espece.role`. **N'a, en revanche, pas touché `espece.role`** : ce champ garde
   son vocabulaire propre, plus large (voir l'écart noté en Phase 1).

## Ce qui existe déjà, à réutiliser

| Existant | Usage dans ce chantier |
|---|---|
| `espece` (cadre + surcharges par site), `src/Web/Especes.php` (`toutes()`, `parNom()`, `enregistrer()`, `retirer()`) | Le référentiel à étendre (colonnes de calendrier) et la page qui manque |
| `CalendrierLopez::periode()` — lit une fenêtre de dates en mots (« fin avril » → jours de début/mi/fin de mois) | Le modèle pour lire des dates en texte libre, comme celles du fichier fourni (« avril-mi-juin ») |
| `Scenario::FONCTIONS` | Le vocabulaire des rôles de service, étendu au potager (décision 4) |
| `Import::examiner()`/aperçu/`creer()` (export-import de projet) | Le modèle en trois temps pour un import tolérant : examiner sans écrire, montrer un aperçu, puis écrire en transaction |
| `Cultures::valider()`, `conception-potager.php` (repère de Lopez + « Reprendre ces dates ») | Le point d'accroche : si l'espèce saisie est connue du référentiel, proposer ses repères, comme Lopez aujourd'hui |
| `Associations::normaliser()`/`memeNom()` | Rapprocher un nom de variété ou un nom usuel du nom scientifique du référentiel |

## Phases

1. **Étendre `espece` — faite (2026-09-22).** `espece.periode_semis`/`periode_repiquage`/`periode_recolte`
   (texte libre, lues comme `CalendrierLopez::periode()` — la même fonction, réutilisée telle quelle) ;
   `Version::SCHEMA` passe à **10** (trois colonnes facultatives, la fenêtre d'import ne se referme pas).
   **Écart avec le plan, trouvé en codant** : je n'ai **pas** touché à `espece.role` — le seed curé de 46
   espèces y porte déjà `production`, une valeur hors de `Scenario::FONCTIONS` (`sql/02-especes-seed.sql` :
   « biomasse\|production »), et la contraindre à ce vocabulaire aurait invalidé des données existantes,
   sourcées, sans que la décision 4 le demande — elle parle des cultures de potager, pas du référentiel. La
   décision 4 porte donc **uniquement sur `succession_ligne.role`** : il devient multivalué par `\|`, comme
   `espece.role`, avec les clés de `Scenario::FONCTIONS` (`Scenario::fonctionsDe()`, nouvelle méthode pure,
   partagée) — remplace la case unique « plante de biomasse » du 2026-09-21, sans changement de schéma pour
   cette partie (`role` restait déjà une colonne texte). `SautDuChat::plantesDeBiomasse()` et
   `conception-potager.php` (case à case devient un `<details>` de fonctions, même modèle que le scénario
   pérenne) mis à jour en conséquence. Vérifié en local : plusieurs fonctions sur une culture, lecture après
   modification, refus d'une clé inconnue, lecteur (case absente, POST forgé 403), `verifier-export`
   identique, aller-retour d'un fichier du schéma 9 accepté. **Incident en testant, corrigé** : un `php -r`
   direct pour créer un compte lecteur de test a été lancé sans exporter `config/local.env` — il a créé ce
   compte **en production** (`config/base.php` l'emporte silencieusement, piège déjà documenté). Trouvé
   immédiatement, confirmé par une lecture ciblée puis supprimé sur autorisation du porteur (compte + accès,
   vérifié : aucun autre compte touché, aucun rattachement à un site n'avait eu lieu côté production).
2. **La page qui manque.** Une page pour voir et corriger les espèces d'un site (cadre + surcharges), avec
   édition manuelle **et** import d'un fichier — en trois temps comme l'import de projet : examiner (tolérant
   aux champs vides, aux dates en mots), aperçu, écrire. Le fichier fourni sert de premier essai réel.
3. **Brancher au potager.** À l'ajout d'une culture dans `conception-potager.php`, si l'espèce est connue du
   référentiel (cadre ou surcharge du site), proposer ses repères de calendrier et ses rôles de service — même
   mécanique que le repère de Lopez aujourd'hui, généralisée à toute espèce connue.
4. *Plus tard, hors de ce chantier si demandé* : étendre au scénario pérenne (décision 1) ; une source
   d'espacement et de germination plus riche pourrait aussi nourrir `Materiel::quantiteCulture()` (taux de
   réussite par espèce, plutôt que la convention à 80 % de Lopez).

## Manière de procéder

Gabarit « le bureau », aucun défilement, un repère est dit avec son pourquoi et sa source, le lecteur ne peut
rien écrire, le livret « L'atelier » se met à jour dans la même vague, tout changement de
`sql/01-syntropia.sql` monte `Version::SCHEMA` puis `python3 bin/dev.py verifier-export`. Aucune valeur
agronomique n'est inventée : un champ vide reste vide, une donnée importée sans source reste dite « importée »,
jamais présentée comme acquise.
