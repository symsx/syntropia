# Les caractéristiques des végétaux pour le calendrier de plantation — plan

> **État au 2026-09-23 — les trois phases sont faites, pas déployées. Le chantier est terminé.**
> Demandé par le porteur : pour composer un calendrier de plantation, l'atelier doit connaître les
> caractéristiques des plantes que la personne veut cultiver — de production, ou de service (biomasse,
> bloque-herbe, tuteur pour grimpantes…). Deux sources possibles : ce que l'atelier connaît déjà en interne,
> ou ce que le porteur de projet importe. Quatre décisions du porteur cadrent ce chantier (2026-09-22) — voir
> § Décisions.
>
> **Suite au chantier, tranchée et faite le 2026-09-23** : le porteur a demandé un modèle de fichier d'import.
> Proposition faite (hors de ce dépôt : `~/Bureau/Projets/organisation_cultures/entrees/especes-modele-import.
> {csv,md}`, vérifiée ligne par ligne contre `Especes::valider()`), puis deux décisions tranchées par le
> porteur : (1) `role` devient une **liste fermée, plusieurs valeurs à la fois** (`production` + les 24
> fonctions de `Scenario::FONCTIONS`) — revient sur le choix de la Phase 1 de le garder libre ; le seed curé
> (deux espèces, Mélisse et Consoude) portait une valeur hors de cette liste (`bordure`), corrigée d'après
> leur propre note sourcée. (2) une colonne de l'en-tête inconnue de l'atelier **n'empêche plus l'import** :
> elle est ignorée et dite à l'aperçu, plutôt que de faire refuser le fichier entier — pour qu'un même
> fichier serve de catalogue personnel et d'import atelier. Une troisième pièce ajoutée avec ces deux
> décisions : un **modèle téléchargeable** directement depuis `especes.php` (onglet Importer), servant
> `contenu/especes-modele-import.csv` — le même contenu que le fichier personnel ci-dessus, republié dans le
> dépôt pour que l'atelier le propose lui-même, plutôt que de renvoyer vers un fichier hors de sa portée.
> `Especes::roles()` (méthode, pas une constante — `array_keys()` sur `Scenario::FONCTIONS` ne se calcule pas
> à la déclaration d'une constante) porte la liste ; `especes.php` l'affiche en cases à cocher, comme
> `strates_possibles`. Vérifié en local (CDP) : 25 cases à cocher, plusieurs rôles enregistrés et relus,
> valeur forgée hors liste refusée (rien écrit), import avec une colonne inconnue accepté et signalé à
> l'aperçu puis réellement écrit, lecteur (formulaire absent, POST forgé 403), modèle téléchargé identique au
> fichier personnel et revalidé (0 colonne inconnue, 0 erreur), 1280×720 et 320×568 sans débordement.

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
2. **La page qui manque — faite (2026-09-22).** `public/especes.php`, hors parcours (comme `membres.php`/
   `inviter.php`), mais avec un vrai bureau (la liste, paginée sans défilement) plutôt que le simple cadre
   centré des autres pages hors parcours — le panneau Données porte deux onglets, « Une espèce » (édition
   manuelle : ajouter une espèce inconnue du cadre, ou en corriger une — même geste) et « Importer » (un CSV,
   en trois temps comme l'import de projet : `verifier` examine et stage en session sans écrire, `confirmer_
   import` réexamine puis écrit **tout ou rien** dans une transaction — `Especes::enregistrerLot()`). Ajoutée
   au menu du compte (`gabarit.js`), entre « Importer un projet » et « Inviter ».
   **Validation centralisée** : `Especes::valider()` (nouvelle, pure-ish — statique, sans PDO), utilisée à la
   fois par le formulaire et par l'import, remplace la validation partielle qu'`enregistrer()` faisait seule
   (elle ne vérifiait avant que le nom, la variété et `systeme` — un espacement, une date, une énumération
   pouvaient s'écrire sans aucun contrôle). Chaque colonne a sa règle : les trois périodes de calendrier
   doivent **parser** via `CalendrierLopez::periode()` (réutilisée telle quelle) ou sont refusées avec
   l'exemple de forme attendue ; `strates_possibles` contre `Conduite::toutesLesStrates()` ; `entree_scenario`/
   `sortie_scenario` contre `Scenario::PERIODES` ; les espacements et `plants_par_ml` en plage numérique ;
   `biomasse`/`origine`/`systeme` en énumération ; le reste en longueur. **`role` n'est toujours pas contraint**
   à `Scenario::FONCTIONS` (voir Phase 1) : un champ libre, avec les clés connues proposées en suggestion
   (`<datalist>`), pas en obligation.
   **Le fichier fourni** (`base_semences_planification_maraichage_nettoyee.csv`) **n'a pas servi de premier
   essai réel** : ses en-têtes (français, libres — « nom_variete », « periode_semis » en texte très divers
   comme « février-mars sous abri; avril-juin plein champ; mai-juin possible ») ne correspondent pas au format
   d'import (les noms de colonnes de `espece`, des dates en un seul segment « fin avril »-« mi à fin juillet »)
   — les traduire est un geste délibéré, propre à chaque colonne, qu'une conversion automatique aurait dû
   deviner sans source : **non fait, faute d'une règle de correspondance donnée par le porteur.** L'import a
   été vérifié avec des fichiers construits pour l'occasion (valide ; en-tête avec une colonne inconnue ;
   une ligne sans nom scientifique et une date non parseable — le fichier entier refusé, rien écrit).
   **Deux bogues trouvés en testant, corrigés avant tout commit** : (1) une pagination serveur mort-née
   (`$_GET['page']`) coexistait avec la pagination JS de `conception-potager.php` que j'ai copiée — sans lien
   qui fasse jamais avancer `?page=`, seules les 12 premières espèces (sur 46) auraient jamais été visibles ;
   supprimée, tout est rendu et c'est le script qui pagine, comme ailleurs. (2) Le bureau ne se resserrait
   jamais : `.bureau` centre un contenu court par défaut (`align-items:center`, `gabarit.css`) — les pages à
   vraie liste le corrigent par un `#bureau{ align-items:stretch }` plus spécifique (déjà fait dans
   `conception-potager.php`/`conception-scenario.php`/`conception-materiel-et-eau.php`), oublié ici au premier
   jet ; sans lui, `.es-centre` grandit librement et rien ne déborde jamais aux yeux du script — la page
   plafonnait la liste à 46 lignes sans jamais mesurer un vrai débordement, un `overflow:hidden` de trop sur un
   conteneur intermédiaire (`.es-zone`) absorbant tout signe, exactement le piège que le CLAUDE.md documente
   déjà pour `scrollWidth`/`scrollHeight` — trouvé seulement en mesurant la boîte de chaque conteneur en CDP,
   pas en lisant le CSS. Vérifié en local (projet et comptes de test supprimés ensuite) : ajout, édition,
   retrait d'une surcharge, import valide/refusé (colonne inconnue, ligne invalide), lecteur (panneau absent,
   POST forgé 403), `verifier-export` identique (aucun changement de schéma), captures d'écran à 1280×720,
   1024×600 et 320×568 sans débordement, pagination réelle (22/16/9 lignes visibles selon la taille).
3. **Brancher au potager — faite (2026-09-23). Le chantier est terminé, trois phases.**
   `Especes::parNomUsage()` (nouvelle) retrouve une espèce par un nom d'usage — celui tapé dans le formulaire de
   culture, pas forcément `nom_scientifique`/`variete` exacts — avec le même rapprochement que les voisinages de
   la page (`Associations::memeNom()` : sans accent ni casse, singulier ou pluriel). `Especes::
   datesIndicatives()` (nouvelle, pure) reprend la logique de `CalendrierLopez::datesIndicatives()`, étendue à
   la **récolte** — que Lopez ne donne jamais. Sur `conception-potager.php`, à l'édition d'une culture dont le
   nom est connu du référentiel (cadre ou surcharge du site) : un bloc « Repère du référentiel », **à côté** du
   repère de Lopez (les deux peuvent coexister, chacun dit sa source) — ses trois dates avec un bouton
   « Reprendre ces dates » (même mécanique que Lopez, généralisée à `date_recolte`), et si `role` porte des clés
   de `Scenario::FONCTIONS`, un bouton « Reprendre ces fonctions de service » qui coche les cases correspondantes
   et ouvre le détail. Le nom de l'espèce s'ajoute aussi aux suggestions de saisie (`datalist`), et un lien
   « voir/corriger → » renvoie vers `especes.php?espece=ID`. **Aucun changement de schéma.** Vérifié en local
   (projet de test supprimé) : espèce connue par nom usuel/scientifique/pluriel, dates et fonctions reprises par
   un vrai clic de navigateur (CDP), espèce inconnue sans bloc, lecteur (rien de visible), `verifier-export`
   identique, 1280×720 et 320×568 sans débordement.
4. *Plus tard, hors de ce chantier si demandé* : étendre au scénario pérenne (décision 1) ; une source
   d'espacement et de germination plus riche pourrait aussi nourrir `Materiel::quantiteCulture()` (taux de
   réussite par espèce, plutôt que la convention à 80 % de Lopez).

## Manière de procéder

Gabarit « le bureau », aucun défilement, un repère est dit avec son pourquoi et sa source, le lecteur ne peut
rien écrire, le livret « L'atelier » se met à jour dans la même vague, tout changement de
`sql/01-syntropia.sql` monte `Version::SCHEMA` puis `python3 bin/dev.py verifier-export`. Aucune valeur
agronomique n'est inventée : un champ vide reste vide, une donnée importée sans source reste dite « importée »,
jamais présentée comme acquise.

## Suite — les quatre strates de lumière (2026-09-30)

Relu à la demande du porteur : Théry, ch. 05, p. 38-40 (le tableau des strates, sud de la Dordogne, « sans lecture
absolue »). **Le référentiel** : la sauge reçoit sa strate (haute) ; le sureau, la fève et la pomme de terre gagnent la
seconde strate que la source leur donne (sureau haute | moyenne selon l'objectif ; fève émergente | haute et pomme de terre
émergente | moyenne selon le moment du cycle) ; le figuier, le framboisier, le groseillier et la caseille entrent au cadre.
Le noyer (émergente), l'artichaut (émergente | haute) et le romarin (émergente) reçoivent la strate que le livre leur donne ailleurs (ch. 14, ch. 15), vérifiée à la suite d'une réponse non sourcée transmise par le porteur ; le noisetier reste sans strate, le corpus n'en donne pas. Quinze espèces du cadre restent sans strate. Le corpus (`ch05-le-mousquetaire-espace.md`)
gagne le figuier, la sauge et la légende P / B du tableau.

**Intégré à la conception, deux choix du porteur** (sur quatre proposés) — `src/Strates.php`, `src/Web/Occupation.php`,
`public/assets/js/strates.js`, aucun changement de schéma :

1. **La strate proposée** : dans le scénario et le potager, nommer un végétal remplit sa strate d'après le référentiel, si
   la personne ne l'a pas choisie ; deux strates : la plus haute pour la production, la plus basse pour la biomasse et le
   service (la règle du tableau). Une culture de potager est une production.
2. **L'occupation au sol** par strate, dans le panneau Informations des deux pages : la surface des houppiers adultes
   (`espece.largeur_m`, un disque par plant) rapportée à la parcelle, à côté des repères 20 / 40 / 60 / 80 %. Les plants se
   comptent comme dans Matériel et eau (les lignes du plan font foi).

Non retenus : signaler une plante hors de ses strates notées, l'écart vertical de 1,50 à 2 m entre deux strates.
Non fait : `LignesDeService` garde son tri (la biomasse émergente au centre, d'après le ch. 08) — il ne suit pas la règle
« la plus basse pour la biomasse », qui mettrait le maïs de la ligne centrale en haute.

### La strate selon l'usage — schéma 18 (2026-09-30)

**Décision du porteur** : « tout dépend de l'utilisation de la plante » — une **strate de production** (le P du tableau
p. 38) et une **strate de service** (le B, biomasse, auquel s'ajoute le bloque-herbe), une valeur chacune :
`espece.strate_production` et `espece.strate_service` (**`Version::SCHEMA` passe à 18**, colonnes facultatives, la fenêtre
d'import ne se referme pas). Elles remplacent la déduction « la plus haute pour la production, la plus basse pour la
biomasse », qui confondait l'usage et le moment du cycle (fève, pomme de terre). Une colonne vide : la seule strate
de l'espèce ; plusieurs sans usage dit : rien n'est deviné.

**Recomposées plante par plante** (`sql/02-especes-seed.sql`, après le bloc généré de la fiche) : 32 strates de production
et 18 de service, seulement là où une source dit la strate de cet usage (ch. 05, ch. 08, ch. 14, ch. 15 ; le noisetier
d'après des études — Hampson et al. 1992, 1996 ; Şen et al. 2020 — trouvées par le serveur Consensus). Laissées vides :
le service de la fève, l'artichaut, le chalef (des sources divergent).

**Ce qui les lit** : la strate proposée du scénario et du potager ; la proposition des lignes de service
(`LignesDeService::stratesService()`) et la réserve des bordures ; l'occupation au sol (une plante de bordure ou de ligne
de service compte dans sa strate de service). **Conséquence** : sur la ligne centrale d'un potager, le tournesol (service
émergente) passe devant le maïs (service haute, d'après le ch. 05) — le ch. 08, lui, met le maïs de biomasse au centre.

**Le haricot** : le porteur signale une vidéo de Théry (meilleur rendement avec plus d'ombre en plein été) — pas au corpus,
à verser ; le livre dit déjà le haricot nain « parfaitement à l'aise en strate moyenne » (ch. 05, ch. 08) : production
moyenne.

