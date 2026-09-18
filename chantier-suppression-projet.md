# Supprimer ou archiver un projet — spécification

**Demandé par le porteur le 2026-09-18 (« est-ce qu'un utilisateur peut
supprimer un projet ? »). Spécifiée, pas construite : à réaliser plus tard,
sans date — décision du porteur.**

## Où en est le code

Aujourd'hui, **personne ne peut supprimer un projet** : `Sites` crée un projet,
en fait rejoindre, en retire un membre — mais aucune fonction, aucune page,
aucun bouton ne supprime un site. Les seules suppressions faites l'ont été à la
main en base (les comptes et projets de test).

**Ce qui rend la suppression facile à écrire — et dangereuse** : dans le schéma,
tout ce qui appartient à un projet est en `ON DELETE CASCADE` (parcelles,
réponses, tracés, scénarios, participants, accès…). Supprimer la ligne `site`
emporterait tout d'un coup, sans retour possible.

## Ce qui est acté

1. **Le porteur seul** peut archiver et supprimer — comme il est seul à retirer
   un participant. Ni un contributeur, ni un lecteur.
2. **Deux gestes, pas un.** L'**archivage**, réversible, est le geste ordinaire ;
   la **suppression définitive** est un autre geste, irréversible, à part.
3. **Une confirmation forte pour la suppression** : le porteur **retape le nom
   du projet**, et l'écran dit **ce qui disparaît** — nombre de parcelles, de
   tracés, de végétaux, de réponses, et de participants (avec leurs noms).
4. **Le rappel de l'export** : avant de supprimer, l'écran propose de
   sauvegarder d'abord. **Ce rappel dépend du chantier export/import**
   (`chantier-export-import-projet.md`), reporté à la fin de la première version :
   tant qu'il n'existe pas, la suppression n'a aucun filet — c'est une raison de
   plus de ne pas la construire avant lui, ou de n'offrir que l'archivage d'abord.

## L'archivage

- Une **colonne facultative** sur `site` (`archive_le`, `DATETIME NULL`) — une
  migration additive, rejouable sur MySQL et MariaDB comme `invitation.role_site`
  (voir `sql/00-comptes.sql`, l'idiome `information_schema` + `PREPARE`).
- Un projet archivé **n'est plus le projet actif** et **disparaît du sélecteur**
  « Mes projets » ; il reste lisible dans une liste « Projets archivés » (dans
  Préférences, où les projets du compte sont déjà listés), d'où le porteur le
  **désarchive**.
- **Il ne s'écrit plus** tant qu'il est archivé (comme un lecteur) : ce n'est pas
  un projet qu'on continue de modifier en le croyant rangé.
- Les **autres membres** le voient marqué « archivé » plutôt que de le voir
  disparaître sans explication.

## La suppression

- Réservée au porteur, depuis Préférences (ou la page « Participants »), et
  **d'un projet qu'il a d'abord archivé** — proposition : on ne supprime que ce
  qu'on a rangé, ce qui ajoute un délai de réflexion sans rien coûter.
- Écran de confirmation (point 3 ci-dessus), puis `DELETE FROM site` — une
  transaction, tout part en cascade. **Le cadre partagé des espèces
  (`site_id = 0`) n'est jamais touché** ; les surcharges du site, elles, partent
  avec lui.
- Le projet actif retombe sur un autre projet du compte, sans erreur
  (`Sites::pourCompte()` le fait déjà quand le cookie ne correspond plus à une
  appartenance).
- Un projet dont le porteur est le **seul membre** peut se supprimer
  simplement ; un projet **avec d'autres participants** demande, en plus, de dire
  qu'ils perdent l'accès.

## Garde-fous dans le service, pas seulement dans la page

Comme pour `Sites::retirerMembre()` : les règles vivent dans `Sites` — seul un
porteur, jamais un projet dont on n'est pas porteur, refus si le nom retapé ne
correspond pas, refus d'une suppression non précédée d'un archivage. La page ne
fait que montrer ce que le service refuse.

## À décider plus tard

- **Que devient un participant à qui l'on supprime le projet ?** Rien ne
  l'avertit aujourd'hui : il verrait le projet disparaître de sa liste. Il n'y a
  pas de système de message. **Proposition** : l'écran de confirmation liste les
  participants par leur nom, pour que le porteur les prévienne lui-même ; et
  l'archivage, qui laisse le projet visible et marqué « archivé », est l'étape
  où ils s'en aperçoivent.
- **Les invitations ouvertes** d'un projet supprimé (`invitation.site_id`) : elles
  pointent vers rien — `rejoindre.php` répond déjà « le projet n'existe plus »,
  sans consommer le code. À vérifier, pas à construire.
- **Les comptes qui n'ont plus aucun projet** après suppression : ils arrivent
  sur « Ouvrir votre projet », comme un compte tout neuf.

## Phases envisagées

```
Phase 1  Archiver — colonne, désarchiver, liste des archivés, lecture seule
Phase 2  Supprimer — confirmation forte, garde-fous du service, cascade
Phase 3  Le rappel d'export, quand le chantier export/import existe
```

## Fichiers critiques

- `sql/01-syntropia.sql` — la liste des liens en cascade : **tout ce qui s'ajoute
  et se rattache à `site` ou `parcelle` doit y garder sa cascade**, sinon une
  suppression laisse des lignes orphelines.
- `src/Web/Sites.php` — où vivent les garde-fous, `pourCompte()` et `activer()`.
- `public/compte.php`, `public/membres.php` — où le geste se placerait.
- `chantier-export-import-projet.md` — dont dépend le rappel d'export.
