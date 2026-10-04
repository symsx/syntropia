# L'espacement des plantes de service — une règle tirée des sources

**Demandé par le porteur le 2026-10-04 ; toutes les questions tranchées le même jour (§ Décisions). Phases 1 à 4 faites le même jour ; la 5 attend la relecture du cadre.** Le porteur a revu `espacement_retenu_m` de dix
espèces du cadre et complété quatre émergents (chêne, orme, hêtre, mûrier noir), sans source pour ces valeurs. Il
précise le sens de la colonne et demande une règle :

> `espacement_retenu_m` est l'espacement pour les plantes de service en syntropie, des plantes qui vont être
> sacrifiées pour les 9/10e. Il faudrait mener des investigations sur les plans de Joala et trouver dans leur texte
> des informations pour déterminer cet espacement de façon plus générique.

Et, en réponse à mes premières remarques :
- l'espacement retenu n'est **pas la distance entre deux plants de la même espèce**, mais **l'écart entre une plante
  de service et ses voisines**, quelles qu'elles soient (le hêtre « tous les 1,80 m » d'Agrecolibre et sa case à 0,5 m
  ne se contredisent pas) ;
- les fiches ne sont pas sourcées **parce qu'il n'y a pas de source** : c'est la raison de chercher une règle qui
  reproduise les espacements des sources ;
- une ligne porte **plusieurs espèces**, jamais une seule : **le modèle et les fonctions de calcul sont à faire
  évoluer**.

## Décisions du porteur — 2026-10-04

1. **L'espacement dépend de la fonction de la plante** : un espacement pour le service biomasse, un pour le service
   bloque-herbe, un pour le service brise-vent, un pour la production. Le porteur est en faveur d'**une colonne
   « service »** et d'**une colonne « espacement de service »** — l'actuelle `espacement_retenu_m`.
2. **Le ratio de sacrifice : 75 %**, tiré du corpus (70 à 80 %), et **pour le seul service biomasse/perturbation**.
   Les « 9/10 » étaient une estimation du porteur.
3. **La ligne centrale** : le porteur demande si celle d'une planche est faite d'une seule espèce pour la biomasse. Les
   sources distinguent deux cas (ci-dessous, § La ligne centrale).
4. **L'intervalle de structure** : voir la décision 1 — c'est l'espacement de production.
5. **Le CSV du 2026-10-04 attend la règle** : il n'entre pas au cadre avant.

### La ligne centrale — ce que disent les sources

- **Au potager** : « une ligne, une espèce » (Théry ch. 8 § 8). L'atelier l'applique déjà aux lignes de service des
  planches (`LignesDeService`), la biomasse sur la ligne centrale.
- **En plantation pérenne** : la ligne centrale **mêle les espèces et les fonctions**. Chez Théry (ch. 11, « Les
  eucalyptus »), l'eucalyptus et le frêne (biomasse), le mûrier blanc et le cassis (production), le sureau et le
  chalef (production secondaire) et des boutures (biomasse) alternent tous les 25 cm. Chez Agrecolibre (Joala 7-10),
  hêtres, charmes, saules et céleris alternent. Seul **le poquet** est d'une seule espèce : le tipi de trois variétés
  est « une erreur souvent faite » (Joala 7-7).

## Ce que disent les sources

`[Joala]` et `[Théry]` ne sont jamais deux sources indépendantes : la formation et le livre sont de la même autrice.

**Le repère des « 9/10 » n'est pas au corpus.** Le chiffre sourcé : **70 à 80 % des plantes implantées sont destinées
à la biomasse et à la perturbation** (Théry ch. 11, règle 7 ; ch. 14 ; Joala 7-7). La bibliographie du livre le
range parmi les chiffres sans référence.

**Le pas est celui de la ligne, pas celui de l'espèce.** Le plan de « Les eucalyptus » (Théry ch. 11 § 7) pose un
eucalyptus tous les 2 m, puis des mûriers blancs entre eux, des cassissiers, des sureaux, des chalefs, des boutures et
des poquets de frêne : *« Résultat : un plant, une bouture ou un nid de graines tous les 25 cm. »* Un eucalyptus et un
nid de frêne y prennent la même case.

| Ligne | Pas | Plantes | Source |
|---|---|---|---|
| Ligne centrale | 25 cm | ligneux de toutes tailles, en plant, bouture ou nid de graines | Théry ch. 11 ; Joala, production de bourgeons |
| Ligne centrale, design Agrecolibre | 45 cm | hêtres tous les 180 cm, charmes entre eux, saules tous les 90 cm, céleris tous les 45 cm | Joala 7-10 |
| Bordure extérieure | 40 cm | consoude ; lavande, hysope, romarin ; sauge | Théry ch. 11, ch. 14 (p. 117, 119, 121) |
| Bordure intérieure | 40 cm | mélisse intercalée entre les consoudes | Théry ch. 11 |
| Bordure à l'installation | 20 cm | consoude, mélisse et féverole alternées | Joala, production de bourgeons |
| Ligne intérieure de bordure | 50 cm | chalef, goji, menthe | Théry ch. 14, p. 117 |
| Ligne tressée | 20 à 25 cm | goji tressé ; mûrier sans épines | Théry ch. 14, p. 119 et 121 |
| Supports | 100 cm | sureau ; trognes de rigidification | Théry ch. 14 § 4.3, p. 121 |
| Poquets sur les bordures | 50 cm | féverole | Joala 7-7 |

**Deux espacements différents se lisent sur les plans.**
1. **L'intervalle de structure** : la distance entre deux plants de l'espèce qui structure la ligne — eucalyptus 2 m
   (marqué en biomasse chez Théry, non en production — corrigé le 2026-10-04),
   hêtres 1,80 m, mûriers 2 m, tilleuls et cédrèles 1,50 m (Joala 7-10), sureaux 1 m.
2. **Le pas de remplissage** : la structure se subdivise, et chaque case reçoit une plante de service. 2 m / 25 cm =
   8 cases chez Théry ; 180 / 90 / 45 cm chez Agrecolibre, des moitiés successives.

**Ce qui n'est pas du service garde ses distances** : les fruitiers de production principale gardent « les mêmes
écarts qu'en verger classique, voire plus » (Théry ch. 15 § 4) ; les petits fruits dépendent de l'objectif (50 à 60 cm
en bordure de biomasse, 1,20 m pour des caseilles adultes). La formation prévient : la distance entre lignes et
l'alternance sur une ligne « ne doivent pas être fusionnées en un espacement générique de verger » (production de
bourgeons).

**Les nuances** : la densité suit l'état du sol (Joala, printemps et mini-forêt) ; réduire la densité des émergents
prive le système de « backup », il faut doubler ou tripler (Joala, tortues et succession) ; « si t'as peur, plante
deux fois plus » (Götsch, cité par Théry ch. 3). Les annuelles placentaires (maïs, tournesol) se sèment dense et ne
se dessinent pas sur le plan (Théry ch. 11).

## La règle par fonction

Relue d'après la décision 1 : **l'espacement dépend de la fonction que la plante remplit sur la ligne**. Ce que les
sources donnent pour chacune :

| Fonction | Espacement | Source |
|---|---|---|
| Biomasse et perturbation, ligne centrale pérenne | 25 cm (45 cm chez Agrecolibre) | Théry ch. 11 ; Joala, production de bourgeons ; Joala 7-10 |
| Bloque-herbe, bordure | 40 cm (20 cm à l'installation) | Théry ch. 11 et 14 ; Joala, production de bourgeons |
| Brise-vent | aucun chiffre | le corpus ne nomme aucune espèce brise-vent (`conception-de-la-tortue.md`, question 4) |
| Production | l'intervalle de la structure : trognes à feuilles comestibles d'Agrecolibre (hêtre 1,80 m, mûrier 2 m, tilleul et cédrèle 1,50 m) ; fruitiers aux écarts du verger | Joala 7-10 ; Théry ch. 15 § 4, ch. 15 § 4 ; Joala 7-10 |

La première lecture, gardée pour mémoire : le pas dépendrait de la ligne et de la forme du plant.

| Forme du plant | Pas | Exemples des sources |
|---|---|---|
| Plant, bouture ou nid de graines sur la ligne centrale | 25 cm | eucalyptus, mûrier blanc, cassis, sureau, chalef, frêne |
| Vivace herbacée en touffe, en bordure | 40 cm | consoude, mélisse, sauge, lavande, hysope, romarin |
| Ligneux souple conduit serré | 20 à 25 cm | goji tressé, mûrier sans épines |
| Arbuste de bordure | 50 à 60 cm | chalef, goji (ligne intérieure), cassis |
| Support | 100 cm | sureau, trogne de rigidification |

Le noyer à 0,5 m, le frêne à 0,3 m et le charme à 0,4 m du CSV du 2026-10-04 entrent dans cette lecture : sur la
ligne centrale, un arbre prend une case comme un autre.

## Décisions du porteur, suite — 2026-10-04

6. **Une espèce qui remplit deux services : le service biomasse prime.** Son espacement, plus serré, n'empêche pas
   l'autre service, il le renforce. (Le porteur a écrit « bloque-vent » ; l'exemple posé était la consoude, biomasse et
   bloque-herbe — la règle vaut pour les deux.)
7. **L'espacement de production est `espacement_monoculture_m`**, déjà au schéma (l'apport de Faraj).
8. **Le brise-vent reste sans espacement** tant qu'aucune source ne le donne.
9. **Le ratio de 75 % sert deux fois** : vérifier qu'une planche porte bien 75 % de plants de biomasse, et compter
   dans « Matériel et eau » les plants qui seront sacrifiés.

## Le modèle qui en découle

- **Le service d'une espèce** se lit dans `role` (déjà au référentiel, plusieurs fonctions à la fois), par priorité :
  **biomasse, puis bloque-herbe (ou couvre-sol), puis brise-vent** — sans colonne « service » (décision 10).
- **L'espacement de service** est `espacement_retenu_m`, renommé à l'écran (« Espacement de service »).
- **L'espacement de production** est `espacement_monoculture_m` (« Espacement de production »).
- **Quel espacement compte sur une ligne** dépend de **la fonction de la plante sur cette ligne**, pas de l'espèce
  seule : un mûrier en production principale prend l'espacement de production ; le même, en biomasse, l'espacement de
  service. La ligne du scénario dit déjà cette fonction (production principale, secondaire, biomasse — Théry ch. 11).

## Ce qui change dans l'atelier

**Aujourd'hui** :
- **les bordures** et **les lignes de service des planches** sont déjà des séries : l'écart entre deux voisins est la
  moyenne de leurs espacements (`Bordures::compter()`, décision du 2026-09-23) ;
- **une ligne du scénario** (`Materiel::quantite()`) remplit seule toute sa bande à son espacement : un noyer à 0,5 m
  sur dix planches de 4 m donne 80 noyers. L'écran le dit (« l'estimation est haute quand plusieurs végétaux se
  partagent une ligne »), mais le compte est faux dès que la ligne est mélangée, c'est-à-dire toujours en pérenne.

**Proposé** : la ligne centrale pérenne devient une série — les productions à leur espacement de production (la
structure), les plantes de biomasse dans les cases entre elles, à leur espacement de service.

## Phases proposées

| Phase | Contenu |
|---|---|
| **1 — La règle au nœud** | La règle par fonction, ses sources et le ratio de 75 % dans `skills-map/nodes/conception-de-la-tortue.md` (§ Espacements), relue par le porteur. |
| **2 — Les deux espacements** | `espacement_retenu_m` et `espacement_monoculture_m` renommés à l'écran (`especes.php`, modèle d'import, glossaire, livret) ; le service d'une espèce lu dans `role` par priorité. Pas de changement de schéma. |
| **3 — La ligne centrale en série** | `Materiel::quantite()` compte la ligne centrale d'une parcelle pérenne comme une série : la structure de production, la biomasse entre elle. « Matériel et eau » et le Dossier Projet suivent (`MaterielDuSite`). |
| **4 — Les 75 %** | La part de plants de biomasse par planche, dite à l'écran (plan ou scénario) ; « Matériel et eau » compte les plants à sacrifier. |
| **5 — Le cadre relu** | Un espacement de service par défaut tiré de la fonction (25 cm biomasse, 40 cm bloque-herbe) pour une espèce sans valeur ; puis le CSV du 2026-10-04 intégré au cadre. |

## Décisions du porteur, fin — 2026-10-04

10. **Pas de colonne « service »** : la priorité appliquée à `role` suffit — biomasse, puis bloque-herbe (ou couvre-sol),
    puis brise-vent.
11. **Les 75 % se disent sur le plan et dans le scénario** ; en dessous, **une simple information**, jamais une alerte.
12. **Où se comptent les 75 %** : **au potager, par planche** (une ligne y porte une seule espèce) ; **en pérenne, par
    ligne**.

Plus de question ouverte : le chantier peut avancer phase par phase.

## Phase 1 — écrite le 2026-10-04, à relire par le porteur

`skills-map/nodes/conception-de-la-tortue.md`, § L'espacement selon la fonction : les deux espacements, la priorité
de `role`, la ligne pérenne en série et la ligne potagère à une espèce, le tableau par fonction avec ses sources, les
75 % (par planche au potager, par ligne en pérenne ; une information), et ce que la règle ne dit pas. Rien n'est codé.

## Phase 2 — faite le 2026-10-04, pas encore déployée

- **`src/Espacement.php`** (pur) : `service()` lit le service d'une espèce dans son rôle (biomasse ou perturbation,
  puis bloque-herbe ou couvre-sol, puis brise-vent) ; `PART_BIOMASSE` = 0,75, pour les Phases 4.
- **`especes.php`** : « Espacement de service » (`espacement_retenu_m`) et « Espacement de production »
  (`espacement_monoculture_m`), chacun expliqué au survol (`glossaire.md`) ; sous les deux champs, le service que le rôle
  donne à l'espèce ; un paragraphe « Deux espacements » au panneau Informations. Les noms de colonnes du CSV ne
  changent pas : un fichier déjà fait s'importe toujours.
- **Les bordures** (`conception-parcelles.php`, `RepartitionBordures`) disent « sans espacement de service ».
- **Livret « L'atelier »** : chapitre des espèces (les deux espacements, la priorité) et passages des bordures.
- **Corrigé en passant** dans le nœud, le chantier et le glossaire : l'eucalyptus de Théry (ch. 11) est marqué en
  **biomasse**, non en production ; l'exemple de production devient les trognes à feuilles d'Agrecolibre.
- **Vérifié** en local (compte `claudetest1`) : la fiche de quatre espèces du cadre — consoude et chalef « biomasse »,
  lavande « bloque-herbe », noyer « aucun service » — les deux mots au glossaire et au survol.
- **À voir avec le porteur** : le noyer n'a que le rôle « production » au cadre, mais le CSV du 2026-10-04 lui donne un
  espacement de service (0,5 m). Sans rôle de service, cet espacement ne servira pas.

## Phase 3 — faite le 2026-10-04, pas encore déployée

Deux questions tranchées par le porteur avant de coder :
- **Qui entre dans la série** : les plantes de service **vivaces et ligneuses** seules. Les annuelles et bisannuelles
  placentaires (maïs, tournesol, féverole) se sèment en poquets entre les pieds et ne prennent pas de case (Théry
  ch. 11) ; elles gardent l'estimation d'une ligne par planche.
- **Une quantité saisie** prime et **occupe ses cases** : les autres plantes de service se partagent le reste.

Ce qui est fait :
- **`Materiel::quantitesScenario()`** (pur) compte les lignes du scénario d'une parcelle pérenne. Sur la ligne
  centrale de chaque planche : la structure (une ligne de production) à son espacement de production (la ligne, à
  défaut `espacement_monoculture_m`) ; les quantités saisies ; puis les plantes de service pérennes en série dans la
  longueur qui reste (`Bordures::compter()`), chacune à son espacement de service (la ligne, à défaut
  `espacement_retenu_m`). Une plante de structure prend une case de la largeur moyenne des cases de service. Les lignes
  de bordure et les annuelles gardent `Materiel::quantite()`.
- **Appelée par `MaterielDuSite`** (« Matériel et eau », Dossier Projet) **et `Occupation`** (l'occupation au sol du
  scénario et du potager) : les trois disent les mêmes nombres.
- Les notes de « Matériel et eau » et du dossier, et le livret (« Concevoir : le matériel et l'eau »), disent la série.
- **Vérifié** : le plan « Les eucalyptus » de Théry rejoué sur une planche de 10 m — quatre productions tous les 2 m
  (mûrier, cassis, sureau, chalef), quatre plantes de service à 25 cm (eucalyptus, frêne, goji, lonicera) — donne 5
  plants de chaque, soit un eucalyptus tous les 2 m et huit cases de 25 cm par tronçon, comme le livre ; une quantité
  saisie réduit d'autant les autres ; une annuelle et une ligne de bordure gardent leur estimation ; une espèce
  inconnue reste « à déterminer ». Les pages qui comptent (Matériel et eau, Dossier Projet, scénario, potager, plan)
  s'ouvrent sans erreur ; le potager P1 de La Vadaux donne la même liste qu'avant.
- **Non vu sur des données réelles** : la base locale n'a aucune ligne de scénario.
- **Limites** : plusieurs productions sur la même ligne se comptent chacune à son intervalle, sans vérifier qu'elles
  tiennent ensemble ; une production sans espacement de production reste « à déterminer » (elle prenait avant
  l'espacement de service à défaut).

## Phase 4 — faite le 2026-10-04, pas encore déployée

- **`Espacement::partBiomasse()`** (pur) : la part de plants de biomasse d'un ensemble, face aux 75 % ; un plant sans
  nombre ne compte pas et se nomme. **`Espacement::ligneDeBiomasse()`** : une ligne de production ne l'est jamais ; une
  ligne de service l'est si ses fonctions, à défaut le rôle de l'espèce, donnent le service biomasse.
- **`src/Web/PartBiomasse.php`** : par planche au potager (ses cultures et ses lignes de service), sur la ligne centrale
  en pérenne (les plants de la série, `quantitesScenario()` marquant ceux qui y sont : `sur_ligne`).
- **Affichée** au panneau Informations du plan (`conception-parcelles.php`), du scénario et du potager, comme
  l'occupation au sol : ✓ à 75 % ou plus, · en dessous, ○ sans rien de compté — **sans couleur d'alerte**.
- **« Matériel et eau »** : une puce « Biomasse à sacrifier · N plants » et sa note ; le Dossier Projet le dit au
  récapitulatif. `Materiel::regrouper()` somme les plants de biomasse connus (`biomasse`), chaque entrée de
  `MaterielDuSite` disant si elle en est.
- Livret « L'atelier » : plan, scénario, potager, matériel.
- **Vérifié** : le plan « Les eucalyptus » avec une lavande bloque-herbe — 27 plants de biomasse sur 40, 67,5 %, « · » ;
  un goji au rôle « production et biomasse » posé en service compte en biomasse. La Vadaux P1 : 77 plants de biomasse à
  sacrifier (chalef 19, consoude 40, saule 18, tous de service biomasse en bordure), la planche 1 « rien de compté »
  (ses cultures n'ont pas de nombre). Pages sans erreur.
- **Laissé tel quel** : le contrôle « le saut du chat est-il préparé ? » dit toujours « aucun repère ne dit encore
  combien de masse suffit » — il parle de masse à tailler, la part de biomasse parle de nombre de plants.
- **Une phrase de synthèse retenue sans guillemets** : « 70 à 80 % des plantes implantées… » est la synthèse du
  corpus (ch. 11), non une citation vérifiée du livre ; l'écran et le nœud la disent sans guillemets.

