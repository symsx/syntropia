# Les textes de l'atelier — réécriture dans le style du porteur

**Demandé par le porteur le 2026-10-03 ; planifié, pas commencé.** Ce chantier passe avant la Phase 3 du Dossier
Projet (décision du porteur, le même jour).

## La demande

Le porteur a corrigé une présentation de l'atelier dans son style (`contenu/presentation-utilisateurs.html`). Les
textes de l'atelier sont réécrits selon les mêmes règles. Ces règles sont notées dans la mémoire de Claude
(`style-ecriture-du-porteur`) et rappelées ici.

## Les règles

- **Des titres simples**, avec un article, qui nomment la chose : « Le bureau », « La définition du projet ».
- **Des phrases complètes, avec un verbe.** Pas d'enchaînements de fragments séparés par des tirets ou des
  deux-points.
- **Pas de formule toute faite** (« Une place à imaginer », « Cette étape ne juge rien »).
- **Pas de vocabulaire inventé.** Les mots et les définitions du porteur sont repris.
- **Définir directement**, par ce que sont les choses. Pas de définition par la négation (« n'est pas un
  accident »).
- **Un ton sobre et factuel**, sans promesse ni familiarité. Les limites sont dites franchement.
- **Le concret et les chiffres** : ce que fait l'outil, avec ses taux et ses nombres.
- **Les sources sont nommées.**
- **Dire à quoi sert une information** au moment où elle est demandée.

## Le périmètre

| Ensemble | Volume au 2026-10-03 |
|---|---|
| Les pages de l'atelier (étapes, panneau Informations, panneau Données, messages, bulles) | ~19 000 mots, 40 pages |
| Les textes produits par le code (`src/` : libellés et explications de `Stade`, `Tortue`, `Conduite`, `Scenario`…, messages d'erreur ; textes des scripts) | ~150 phrases longues, et les messages courts |
| Le livret « L'atelier » (`contenu/documentation-atelier.md`) | ~5 900 mots |
| Le livret « Les sources » (`contenu/sources-de-l-atelier.md`) | ~2 000 mots |
| Le glossaire (`contenu/glossaire.md`) | ~740 mots |

**Hors du chantier** : le livret SAF et le chapitre « L'intention » du livret « L'atelier », écrits par le porteur ;
les commentaires du code ; la mention « Syntropia — en construction » du pied de page, que le porteur garde.

## Ce qui ne change pas

- **Le sens.** Une phrase qui porte une règle sourcée (Théry, Joala, Lopez, Weinrich) ou une décision du porteur
  garde sa règle et sa source. Quand une phrase demande un choix de fond plutôt que de forme, la question va au
  porteur.
- **La place.** Le bureau ne défile pas. Un texte réécrit tient dans le même espace, à 320 × 568 comme sur grand
  écran.
- **Les livrets** suivent leur format : un chapitre par ligne `# `, des paragraphes de 300 caractères au plus (220
  pour tenir à 320 × 568).
- **Le livret « L'atelier »** reste à jour avec ce qui est en production : un libellé de bouton ou d'étape qui
  change se change aussi dans le livret, dans la même vague.

## Les vagues

Une vague par ensemble cohérent, dans l'ordre du parcours. Chaque vague se termine par une vérification sur ce poste
(capture d'écran à 320 × 568 et sur grand écran, pages qui tiennent sans défiler, syntaxe PHP), un commit, puis le
déploiement sur accord du porteur. **Le porteur relit directement les pages**, sans relevé avant / après.

1. **La Définition et les pages publiques** : accueil, connexion, inscription, `definition-projet`, vision,
   objectifs, moyens, site. C'est ce qu'un nouvel utilisateur voit d'abord.
2. **La Conception** : étude du site, étude du sol, parcelles, scénario, potager, matériel et eau, calendrier, et
   les textes de `src/` qu'elles affichent.
3. **La Réalisation et le Suivi** : les quatre étapes de chaque phase, le journal des perturbations, le saut du
   chat.
4. **Les comptes et le partage** : compte, inviter, rejoindre, membres, exporter, importer, espèces, journal des
   connexions, Dossier Projet, relevé vierge.
5. **Les livrets et le glossaire** : « L'atelier » (hors « L'intention »), « Les sources », le glossaire.

## Avant de commencer chaque vague

- Relire la présentation corrigée par le porteur et ses propres textes (`directives.txt`, `INTENTION.md`,
  « L'intention »), pour reprendre son vocabulaire.
- Relever les libellés qui changent (boutons, titres d'étape, entrées de menu) : ce sont eux que le livret et le
  glossaire doivent suivre.
