# La présentation de l'atelier — mise en ligne

**Demandé par le porteur le 2026-10-03 ; planifié, pas commencé. Les cinq questions ont reçu leur réponse le jour même (§ Décisions du porteur).** La demande :
« copie le document dans contenu/ et prévois de l'adapter pour le placer sur
jubile.alwaysdata/syntropia ».

## Décisions du porteur — 2026-10-03

1. **Le gabarit : le bureau**, comme toutes les pages. Aucune nouvelle exception.
2. **Une page publique.**
3. **L'adresse `jubile@alwaysdata.net` est retirée.** La ligne « Pour signaler une
   anomalie » disparaît de la diapositive « Limites et aide ». Le fichier de
   référence dans `contenu/` reste tel quel, le retrait se fait dans la source de la
   page.
4. **La présentation est datée et mise à jour sur demande du porteur.** Elle n'entre
   pas dans la maintenance continue du livret « L'atelier ». La date de la version
   s'affiche sur la page.
5. **Le nom dans le menu : « Présentation ».**

## Le document de départ

`contenu/presentation-utilisateurs.html` est une présentation de quinze diapositives.
Elle a été générée dans une session Claude, puis corrigée par le porteur le
2026-10-03. Son texte est la référence du style d'écriture du porteur. Il ne se
réécrit pas : l'adaptation change la forme, pas le texte.

Le fichier est un export autonome de 600 Ko. Sa toile mesure 1920 × 1080 et il
contient sa propre feuille de style (variables `--cds-*`), un script de défilement
des diapositives et huit ressources intégrées (`data:`). Les polices viennent de
Google Fonts, alors que l'atelier sert les siennes depuis `le-bureau/fonts/`. Le
fichier n'est pas servi : `contenu/` refuse tout accès web.

## La proposition

Une page publique, `presentation.php`, construite sur le gabarit « le bureau ».
Une diapositive occupe le bureau, sans défilement. Le bandeau d'outils porte
« Précédente », « Suivante » et le numéro de la diapositive. Les flèches du clavier
font de même. Le mécanisme suit celui de `livret.js`.

Le texte est lu depuis une source simple dans `contenu/`, comme les livrets, et non
recopié dans le PHP. Les tableaux (tortues, strates) et le schéma du bureau sont
redessinés en HTML et CSS de l'atelier, avec ses deux polices et son thème clair
ou sombre. Le schéma du bureau peut aussi être une vraie miniature du gabarit.

Le lien vers la page se place dans la partie publique, avec « Se connecter »,
« S'inscrire » et « Documentation », à la place que donne la loi de Jakob.

## Les phases

1. **Vérifier le texte contre la production.** La page ne décrit que ce qui est
   déployé, comme le livret « L'atelier ». Chaque affirmation est comparée au code
   (types de tortue, fonds de carte, rôles, 80 % de réussite des semis, dix-sept
   cultures de Lopez, limites). Un écart est soumis au porteur, jamais corrigé
   seul.
2. **Extraire le texte** dans `contenu/presentation.md` (format à fixer, proche
   des livrets : une diapositive par ligne `# `).
3. **Construire `presentation.php`** sur le gabarit, avec la pagination et les
   tableaux redessinés. Ajouter la page à `arborescence.md` et le lien dans la
   partie publique.
4. **Vérifier sur ce poste** à 1920 × 1080, 1366 × 768 et 320 × 568, par une
   capture d'écran. Une diapositive trop haute se resserre ou se coupe en deux,
   elle ne déborde jamais.
5. **Mettre à jour le livret « L'atelier »** (la page « Présentation » existe),
   puis déployer.

## Questions posées le 2026-10-03 — tranchées, voir § Décisions du porteur

1. **Le gabarit.** La proposition construit la page sur le bureau, comme toutes
   les autres. L'autre voie serait de servir la présentation telle quelle, en
   troisième exception au gabarit. Or les deux exceptions actuelles sont des
   documents à imprimer, et le CLAUDE.md interdit d'en faire un précédent.
2. **Publique ou connectée ?** Le titre dit « présentation utilisateurs ». Le
   contenu explique l'atelier à qui ne le connaît pas encore, ce qui plaide pour
   la partie publique.
3. **L'adresse `jubile@alwaysdata.net`** sur une page publique : oui, ou un autre
   moyen de signaler une anomalie ?
4. **Le texte suivra-t-il l'atelier ?** Si oui, la présentation se tient à jour
   avec le code, comme le livret « L'atelier », et c'est à ma charge. Sinon, elle
   reste datée.
5. **Le nom dans le menu** : « Présentation », « Découvrir l'atelier » ou autre.
