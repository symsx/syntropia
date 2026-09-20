# Maraîchage naturel — la discipline des annuelles

**Lecture seule**, comme le reste de `connaissances/`.

## Pourquoi ce dossier existe

**Le dépôt désigne le potager comme sa porte d'entrée** — *« la seule chose qui puisse encore se décider pour la saison qui vient »* — et n'avait **aucune source** pour le tenir. `outils/demarrer-par-le-potager.md` était écrit à partir de ce que le corpus syntropique dit des annuelles, c'est-à-dire de peu.

Ce dossier comble ce manque, avec une source qui traite **exactement** de cela : dimensionner, semer, repiquer, récolter, peser, et corriger l'année suivante.

## Ce qui s'y trouve

**Six fiches, toutes tirées de « Les Jardins Sauvages » de Yann Lopez.** Elles se lisent dans cet ordre — le sol d'abord, la récolte en dernier.

| # | Ce que la fiche traite | Pages | Fichier |
|---|---|---|---|
| **1** | **Le sol** — fertilité contre fertilisation, engrais, compost, paillage importé, azote et carbone | **13-29** | `lopez-le-sol.md` |
| **2** | **Les couverts végétaux** — le moteur du système : semis, densité, composition, destruction, régénération d'un sol épuisé, rotations | **30-66** | `lopez-couverts-vegetaux.md` |
| **3** | **L'herbe** — la lire avant de la gérer : bioindication, succession, enherbement, limaces | **112-123** | `lopez-herbe-et-adventices.md` |
| **4** | **L'irrigation** — besoins, réserve, ouvrages et prix, contrôle d'humidité à la main | **105-111** | `lopez-irrigation.md` |
| **5** | **Les arbres et les haies** — leur place, leurs fonctions, la toiture végétale | **94-97** | `lopez-arbres-et-haies.md` |
| **6** | **Les ravageurs et les maladies** — le terrain, pas le pathogène ; la hiérarchie des trois leviers | **124-140** | `lopez-ravageurs-et-maladies.md` |
| **7** | **Le plan de culture** — du besoin alimentaire au nombre de graines | **163-207** | `lopez-plan-de-culture.md` |

*Toutes portent le marquage `[Lopez]`.*

> **Les fiches 1 à 6 ont été versées le 2026-09-05**, la septième le 2026-08-29. **L'ouvrage est versé sur l'essentiel** ; le périmètre restant est nommé dans `SOURCES.md` § 2ter — deux itinéraires techniques (p. 67-93) et quatre chapitres réflexifs.

### Ce que ce dossier a apporté au cadre, et qu'on n'attendait pas

| | |
|---|---|
| ⚡ **L'eau** | `outils/irrigation-de-demarrage.md` disait que le corpus ne traitait jamais la ressource. **Pour un potager, la chaîne est maintenant complète** — et croisée au m³ de toiture `[de Looze]`, **un m² de toit finance un m² de jardin** |
| **Une quatrième voie au point bloquant n° 1** | Le couvert annuel dense. **Et la bâche s'y compte en semaines, non en mois** |
| **Un indicateur de fertilité gratuit** | La vitesse de disparition du mulch, lue une fois par an |
| ⚡ **Deux seuils de température** | **Pasteurisation du sol à 43 °C**, sol nu d'été **au-delà de 60 °C**. L'acquis n° 1 avait un principe, **il a maintenant une mesure** |
| ⚡ **Le paillage importé, chiffré** | **≈ 10 ha pour en pailler 1.** La règle *« biomasse produite sur place »* a enfin sa démonstration |
| **Une hiérarchie face à une maladie** | **Sol → variété → itinéraire technique**, dont le troisième rang est actionnable tout de suite |

## Ce qui rend cette source particulière

**Yann Lopez est maraîcher en Haute-Vienne.** C'est la **première source du dépôt située dans la région du terrain**, et cela change la façon de la lire.

> Partout ailleurs, le dépôt impose de distinguer **constante** et **paramètre régional** : les dates du corpus sont calées sur la Dordogne ou le Tarn, et se transposent. Ici, **elles sont déjà à la bonne latitude**. Un semis « mi-septembre » se lit mi-septembre.

**Cela ne dispense d'aucune vérification de sol.** Le climat est partagé ; l'exposition et le sol restent de la parcelle.

## Ce qu'il faut savoir avant de citer

**Ce n'est ni de la syntropie, ni du jardin-forêt.** C'est du **maraîchage d'annuelles**, avec rotation, semis en pépinière et repiquage — trois gestes que la syntropie ne fait pas sur ses lignes de production. La revendication commune est l'**absence totale d'intrants**, non la méthode.

**Une pratique de maraîchage ne se cite jamais comme une pratique syntropique.** Le marquage `[Lopez]` s'accompagne, ici, de la mention du contexte : *maraîchage d'annuelles*.

**Et une divergence porte sur les arbres, à ne pas lisser** : `[Lopez]` affirme qu'aucune potagère annuelle ne pousse sous un arbre établi, quand `[Crawford]` cultive des vivaces sous canopée. **Les deux sont vrais — ce ne sont pas les mêmes plantes.** Voir `lopez-arbres-et-haies.md` § 4.

⚠ **L'ouvrage se contredit sur un chiffre**, et c'est noté : **600 km** de racines par pied de seigle p. 33, **600 mètres** p. 45. **C'est p. 33 qui concorde** avec la mesure classique.

⚠ **Et une divergence porte sur les apports azotés** : `[Lopez]` range **l'urine et le thé de compost avec les ammonitrates**, quand `[de Looze]` et `[Marval]` en font des leviers. **Le désaccord porte sur le moment, non sur le geste** — un système à relancer contre un système en croisière. Voir `lopez-le-sol.md` § 3.1.

Voir `referentiels/articulation-maraichage-naturel.md`, et `referentiels/annotations-lopez.md` pour les notes manuscrites du porteur en marge de l'exemplaire.

---

## Note — 2026-09-20 : le tableau des semis et repiquages est extrait dans `contenu/`

*Note ajoutée sans réécriture du texte ci-dessus, conformément à la règle du dépôt.*

Le tableau « des semis et repiquages » de `lopez-plan-de-culture.md` (§ 3 — dix-sept cultures d'automne et d'hiver) est recopié tel quel dans
`contenu/calendrier-lopez.csv` (déployé, refusé au web), lu par `src/CalendrierLopez.php`. Les deux cellules que la source signale « à vérifier »
(le repiquage du chou rouge, la surface de la chicorée) y restent **vides et marquées** (`a_verifier`). Usage et réserves : `chantier-plan-de-culture.md`
§ Phase 4 — des dates en mots, donc des repères indicatifs, pour dix-sept cultures seulement.

