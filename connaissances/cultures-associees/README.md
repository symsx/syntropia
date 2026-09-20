# Cultures associées — la discipline de l'association pour elle-même

**Lecture seule**, comme le reste de `connaissances/`.

## Pourquoi ce dossier existe

**Le dépôt raisonnait en critères d'association sans jamais avoir de couples nommés.** `[Crawford]` donne huit familles de critères de guilde, `[Nathié]` cinq clés d'association, `[Plantae]` les champs pour les exprimer — **et aucune source ne disait quelle plante va avec quelle autre**.

Ce dossier comble ce manque, avec une source qui ne traite que de cela.

## Ce qui s'y trouve

| Source | Fichier | Marquage |
|---|---|---|
| **Christa Weinrich**, abbaye de Fulda — mécanismes, système A/B/C, rotation par décalage, partenaires du potager et du verger | `weinrich-cultures-associees.md` | `[Weinrich]` |

**Les données sont sorties en trois tables requêtables**, dans `referentiels/` :

| Table | Contenu |
|---|---|
| `associations-potager.csv` | **31 espèces**, voisinage propice et néfaste |
| `associations-verger.csv` | **13 porteurs** — fruitiers et petits fruits |
| `associations-succession.csv` | **30 espèces**, préculture et postculture |

> **C'est la forme que `CLAUDE.md` § 3.1 demande** : des critères interrogeables, non des listes en prose. Une association se cherche par requête, comme une espèce se cherche dans `plantae-corpus`.

## Ce qu'il faut savoir avant de citer

**Ce ne sont pas des résultats expérimentaux**, et la source le dit elle-même dès sa première page : une relation bénéfique peut ne pas être attestée dans toutes les zones climatiques ni dans tous les contextes. **Plus de 50 ans d'observation répétée, dans un cadre monastique** — c'est beaucoup, et ce n'est pas un protocole.

**Le contexte est l'Allemagne moyenne** — Jura souabe et Hesse. **Les dates ne se transposent pas.** Les mécanismes, la géométrie et les couples, oui — sous réserve de vérification locale.

**Ni syntropie, ni jardin-forêt.** L'itinéraire suppose un sol ameubli chaque printemps, ce que le cadre écarte. **Ce qui se transporte, ce sont les mécanismes et les couples, jamais la conduite.**

Voir `referentiels/articulation-cultures-associees.md`.

---

## Note — 2026-09-20 : les trois tables sont importées dans ce dépôt

*Note ajoutée sans réécriture du texte ci-dessus, conformément à la règle du dépôt.*

Les trois tables citées plus haut (`associations-potager.csv`, `associations-verger.csv`, `associations-succession.csv`)
n'existaient que dans le projet `syntropie`, `referentiels/`. Elles sont maintenant **copiées telles quelles** dans
`contenu/associations/` (déployé, refusé au web — `connaissances/` ne l'est pas, et l'atelier les lit). Le nœud
`skills-map/nodes/cultures-associees.md` n'a donc plus de renvoi pendant pour elles. Lecture par `src/Associations.php` ;
usage et réserves dans `chantier-associations.md`. La note d'articulation `referentiels/articulation-cultures-associees.md`
reste dans `syntropie` : ses réserves (des ordres de conduite, la table du verger non symétrique, deux plantes toxiques,
l'itinéraire qui ne se transporte pas) sont reprises dans le chantier.

