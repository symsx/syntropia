<?php

declare(strict_types=1);

/**
 * Documentation — Les sources : d'où viennent les règles de l'atelier.
 *
 * Le texte vit dans `contenu/sources-de-l-atelier.md`, pas ici — voir
 * `src/Documentation.php` pour le format (un chapitre par ligne `# `).
 * Cette page ne fait que le lire et le présenter comme un livret qu'on
 * tourne, sur le gabarit — même mécanique qu'`documentation-saf.php`,
 * dont elle est la copie (troisième livret, demandé le 2026-10-02).
 *
 * Page publique, sans logique de compte : rien à garder.
 */

require __DIR__ . '/../src/autoload.php';
require __DIR__ . '/../src/Configuration.php';
require __DIR__ . '/../src/Database.php';

use Syntropia\Documentation;
use Syntropia\Web\Authentification;

Authentification::reprendre($_SERVER);
$auth = new Authentification();
$connecte = $auth->estConnecte();

$chapitres = Documentation::chapitres(__DIR__ . '/../contenu/sources-de-l-atelier.md');
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Les sources — Syntropia</title>
<link rel="stylesheet" href="assets/css/gabarit.css?v=<?= (int) @filemtime(__DIR__ . '/assets/css/gabarit.css') ?>">
<style>
  /* propre à cette page : pas de bandeau d'outils (rien à choisir en
     haut) — recaler la tirette/panneau Informations sur un seul
     bandeau. Aucun défilement : des chapitres, un seul visible à la
     fois, posé comme une page sur le bureau — qu'on tourne d'un clic,
     en CSS pur (:has() + label[for]), sans JavaScript. Même mécanique
     qu'documentation-site.php ; voir ce fichier pour le détail.

     Le nombre de chapitres vient de contenu/sources-de-l-atelier.md (voir
     src/Documentation.php) : les règles ci-dessous qui en dépendent
     (:has(#doc-X:checked) ...) sont générées plus bas, une par
     chapitre — pas écrites à la main. */
  :root{ --haut-bandeaux: 3.4rem; }

  .doc-corps{
    display:flex; gap:1.6rem; align-items:flex-start; justify-content:center;
    width:100%; max-width:50rem; padding:1.4rem 1.5rem;
  }
  .doc-chapitres-bloc{ flex:0 0 11rem; }
  .doc-chapitres-titre{
    font:500 .68rem var(--titre); text-transform:uppercase; letter-spacing:.07em;
    color:var(--muted); margin:0 0 .5rem .8rem;
  }
  .doc-chapitres{ list-style:none; margin:0; padding:0; display:flex; flex-direction:column; gap:.3rem; }
  .doc-chapitres input{ position:absolute; opacity:0; pointer-events:none; }
  .doc-chapitres label{
    display:block; padding:.55rem .8rem; border-radius:.5rem; cursor:pointer;
    font:600 .85rem var(--titre); color:var(--muted);
  }
  .doc-chapitres label:hover{ background:var(--surface); color:var(--ink); }
  .doc-chapitres input:checked + label{ background:var(--accent-fond); color:var(--accent-titre); }
  .doc-chapitres input:focus-visible + label{ outline:2px solid var(--accent); outline-offset:1px; }

  .doc-page{
    flex:1 1 auto; min-width:0;
    background:var(--surface); border:1px solid var(--line); border-radius:.9rem;
    box-shadow:0 10px 26px var(--ombre); padding:1.3rem 1.6rem 1.1rem; min-height:14rem;
  }
  .doc-pages{ display:grid; }
  .doc-chapitre{
    grid-column:1; grid-row:1; opacity:0; pointer-events:none;
    transform:translateX(10px); transition:opacity .25s ease, transform .25s ease;
  }
  .doc-chapitre-repere{ font:500 .68rem var(--titre); text-transform:uppercase; letter-spacing:.06em; color:var(--muted); margin:0 0 .5rem; }
  .doc-chapitre h2{ font:600 1.1rem var(--titre); color:var(--accent-titre); margin:0 0 .6rem; }
  .doc-chapitre p{ font:400 .9rem/1.55 var(--texte); color:var(--ink); margin:0 0 .8rem; }
  .doc-chapitre p:last-child{ margin-bottom:0; }

  .doc-page-nav{ display:none; align-items:center; margin-top:1rem; padding-top:.8rem; border-top:1px solid var(--line); }
  .doc-tourner{
    padding:.4rem .8rem; border:1px solid var(--line); border-radius:.5rem; cursor:pointer;
    font:600 .76rem var(--titre); text-transform:uppercase; letter-spacing:.03em; color:var(--accent-titre);
  }
  .doc-tourner:hover{ background:var(--accent-fond); }
  .doc-tourner-suivant{ margin-left:auto; }

  /* pagination À L'INTÉRIEUR d'un chapitre trop long — voir
     documentation-site.php, même correctif, posé par assets/js/livret.js. */
  .doc-sous-page[hidden]{ display:none; }
  .doc-sous-nav{
    display:flex; align-items:center; justify-content:center; gap:.8rem;
    margin-top:.6rem; padding-top:.5rem; border-top:1px dashed var(--line);
  }
  .doc-sous-bouton{
    border:1px solid var(--line); background:var(--surface); border-radius:.4rem;
    width:1.8rem; height:1.8rem; display:flex; align-items:center; justify-content:center;
    cursor:pointer; font:600 .8rem var(--titre); color:var(--accent-titre); padding:0;
  }
  .doc-sous-bouton:hover{ background:var(--accent-fond); }
  .doc-sous-bouton:disabled{ opacity:.35; cursor:default; }
  .doc-sous-compte{ font:500 .72rem var(--texte); color:var(--muted); min-width:3rem; text-align:center; }

  /* écran peu haut : la colonne des chapitres se compacte (11 chapitres pour
     « L'atelier » : elle débordait de 8 px à 1024×600). */
  @media (min-width:30.01rem) and (max-height:40rem){
    .doc-chapitres{ gap:.1rem; }
    .doc-chapitres label{ padding:.3rem .7rem; font-size:.78rem; }
    .doc-chapitres-titre{ margin-bottom:.3rem; }
  }

  /* petit écran : la colonne des chapitres (11rem fixes) ne tient plus
     à côté de la page — voir documentation-site.php, même correctif.
     Remarque du porteur, 2026-09-15, capture d'écran sur téléphone à
     l'appui. */
  @media (max-width:30rem){
    main#bureau{ padding-top:2.6rem; }
    .doc-corps{ flex-direction:column; padding:.9rem .9rem .7rem; gap:.7rem; max-width:100%; }
    /* la liste des chapitres disparaît : en rangée de pastilles elle prenait
       jusqu'à cinq lignes (9 chapitres) et la page débordait, or le bureau ne
       défile jamais. On tourne alors les chapitres par les deux boutons du bas
       de la page (« chapitre précédent / suivant », des labels : ils gardent leur
       effet même si les boutons radio sont masqués). Décidé le 2026-09-19. */
    .doc-chapitres-bloc{ display:none; }
    .doc-page{ padding:1rem 1.1rem .9rem; min-height:0; }
    .doc-chapitre p{ font-size:.84rem; }
  }

  /* téléphone très bas (320×568) : la page se resserre encore. */
  @media (max-width:30rem) and (max-height:38rem){
    main#bureau{ padding-top:2.2rem; }
    .doc-page{ padding:.7rem .9rem .6rem; }
    .doc-chapitre h2{ font-size:1rem; margin-bottom:.4rem; }
    .doc-chapitre p{ font-size:.8rem; line-height:1.45; margin-bottom:.6rem; }
    .doc-page-nav{ margin-top:.5rem; padding-top:.5rem; }
  }

<?php foreach ($chapitres as $ch): $id = htmlspecialchars($ch['id'], ENT_QUOTES, 'UTF-8'); ?>
  .doc-corps:has(#doc-<?= $id ?>:checked) .doc-chapitre[data-chapitre="<?= $id ?>"]{ opacity:1; pointer-events:auto; transform:translateX(0); }
  .doc-corps:has(#doc-<?= $id ?>:checked) .doc-page-nav[data-pour="<?= $id ?>"]{ display:flex; }
<?php endforeach; ?>
</style>
</head>
<body>
<div class="app" id="app">

  <header class="bandeau-general">
    <div class="bg-gauche">
      <button class="hamburger" id="hamburger" aria-expanded="false" aria-controls="panneau-gauche"
              aria-label="Afficher le menu">
        <span></span><span></span><span></span>
      </button>
      <span class="logo-espace" aria-hidden="true" title="Emplacement du logo">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v11M12 3C8 5 6 8 6 12M12 3c4 2 6 5 6 9M6 12c0 4 2.5 7 6 8M18 12c0 4-2.5 7-6 8"/></svg>
      </span>
      <div class="marque">
        <a class="marque-nom" href="./">Syntropia</a>
      </div>
    </div>
    <div class="bg-centre">
      <span class="bg-centre-titre">Documentation — Les sources</span>
    </div>
    <div class="bg-droite">
      <button class="theme-bascule" id="theme-bascule" aria-label="Changer de thème" title="Thème">
        <span class="theme-icone" id="theme-icone" aria-hidden="true">◐</span>
      </button>
      <?php if ($connecte): ?>
        <div class="compte-conteneur">
          <button class="compte" id="compte-bouton" type="button" aria-haspopup="true" aria-expanded="false" aria-controls="compte-menu">
            <span class="compte-avatar"><?= htmlspecialchars(mb_strtoupper(mb_substr($auth->nomAffiche() ?? '', 0, 2)), ENT_QUOTES, 'UTF-8') ?></span>
            <span class="compte-nom"><?= htmlspecialchars($auth->nomAffiche() ?? '', ENT_QUOTES, 'UTF-8') ?></span>
          </button>
          <div class="compte-menu" id="compte-menu">
            <a class="compte-menu-item" href="conception-parcelles.php"><span class="compte-menu-puce">▤</span>Mon projet</a>
            <a class="compte-menu-item" href="compte.php"><span class="compte-menu-puce">⚙</span>Préférences</a>
            <div class="compte-menu-separateur"></div>
            <a class="compte-menu-item" href="deconnexion.php"><span class="compte-menu-puce">⏻</span>Se déconnecter</a>
          </div>
        </div>
      <?php else: ?>
        <a class="compte" href="connexion.php">
          <span class="compte-avatar">?</span>
          <span class="compte-nom">Se connecter</span>
        </a>
      <?php endif; ?>
    </div>
  </header>

  <div class="bulle" id="bulle" role="status"></div>

  <div class="corps">
    <div class="scrim" id="scrim-gauche"></div>
    <nav class="panneau-gauche" id="panneau-gauche" aria-label="Navigation de l'atelier" aria-hidden="true">
      <div class="pg-entete">
        <a class="marque-nom" href="./">Syntropia</a>
      </div>
      <div class="pg-corps">
        <div class="pg-section">
          <p class="pg-titre">Le site</p>
          <ul class="pg-liste">
            <li><a href="./"><span class="pg-puce">⌂</span><span class="pg-libelle">Accueil</span></a></li>
            <?php if ($connecte): ?>
              <li><a href="conception-parcelles.php"><span class="pg-puce">▤</span><span class="pg-libelle">Mon projet</span></a></li>
              <li><a href="deconnexion.php"><span class="pg-puce">⏻</span><span class="pg-libelle">Se déconnecter</span></a></li>
            <?php else: ?>
              <li><a href="connexion.php"><span class="pg-puce">→</span><span class="pg-libelle">Se connecter</span></a></li>
              <li><a href="inscription.php"><span class="pg-puce">+</span><span class="pg-libelle">S'inscrire</span></a></li>
            <?php endif; ?>
          </ul>
        </div>
      </div>
    </nav>

    <main class="bureau" id="bureau">
<?php if ($chapitres === []): ?>
      <p>Ce livret n'a pas encore de chapitre.</p>
<?php else: ?>
      <div class="doc-corps">
        <div class="doc-chapitres-bloc">
          <p class="doc-chapitres-titre">Chapitres</p>
          <ul class="doc-chapitres">
<?php foreach ($chapitres as $i => $ch): $id = htmlspecialchars($ch['id'], ENT_QUOTES, 'UTF-8'); ?>
            <li>
              <input type="radio" name="doc-chapitre" id="doc-<?= $id ?>"<?= $i === 0 ? ' checked' : '' ?>>
              <label for="doc-<?= $id ?>"><?= htmlspecialchars($ch['titre'], ENT_QUOTES, 'UTF-8') ?></label>
            </li>
<?php endforeach; ?>
          </ul>
        </div>

        <div class="doc-page">
          <div class="doc-pages">
<?php foreach ($chapitres as $i => $ch): $id = htmlspecialchars($ch['id'], ENT_QUOTES, 'UTF-8'); ?>
            <article class="doc-chapitre" data-chapitre="<?= $id ?>">
              <p class="doc-chapitre-repere">Chapitre <?= $i + 1 ?> sur <?= count($chapitres) ?></p>
              <h2><?= htmlspecialchars($ch['titre'], ENT_QUOTES, 'UTF-8') ?></h2>
<?php foreach ($ch['paragraphes'] as $p): ?>
              <p><?= $p /* déjà échappé par Documentation::chapitres() */ ?></p>
<?php endforeach; ?>
            </article>
<?php endforeach; ?>
          </div>

<?php foreach ($chapitres as $i => $ch): $id = htmlspecialchars($ch['id'], ENT_QUOTES, 'UTF-8'); ?>
          <div class="doc-page-nav" data-pour="<?= $id ?>">
<?php if ($i > 0): $prec = $chapitres[$i - 1]; ?>
            <label class="doc-tourner" for="doc-<?= htmlspecialchars($prec['id'], ENT_QUOTES, 'UTF-8') ?>">◂ <?= htmlspecialchars($prec['titre'], ENT_QUOTES, 'UTF-8') ?></label>
<?php endif; ?>
<?php if ($i < count($chapitres) - 1): $suiv = $chapitres[$i + 1]; ?>
            <label class="doc-tourner doc-tourner-suivant" for="doc-<?= htmlspecialchars($suiv['id'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($suiv['titre'], ENT_QUOTES, 'UTF-8') ?> ▸</label>
<?php endif; ?>
          </div>
<?php endforeach; ?>
        </div>
      </div>
<?php endif; ?>
    </main>
  </div>

  <div class="scrim" id="scrim-info"></div>
  <button class="tirette-info" id="tirette-info" aria-expanded="false" aria-controls="panneau-info"
          aria-label="Afficher les informations">
    <svg class="tirette-triangle" viewBox="0 0 10 10" aria-hidden="true"><polygon points="1,1 9,5 1,9" fill="currentColor"/></svg>
    <span class="tirette-texte">Informations</span>
  </button>
  <aside class="panneau-info" id="panneau-info" aria-hidden="true">
    <div class="pi-corps">
      <p>Ce livret se lit une page à la fois. Cliquez un chapitre dans la
         liste, ou tournez la page avec les boutons du bas. Il dit d'où
         viennent les règles de l'atelier (les livres, la formation, les bases
         de données) et à quoi chacune sert. L'atelier et l'agroforesterie
         syntropique ont chacun leur livret.</p>
    </div>
  </aside>

  <footer class="pied">
    <span class="pied-mention">Syntropia — en construction</span>
    <span class="pied-spacer"></span>
  </footer>

</div>

<script src="assets/js/etapes.js?v=<?= (int) @filemtime(__DIR__ . '/assets/js/etapes.js') ?>"></script>
<script src="assets/js/gabarit.js?v=<?= (int) @filemtime(__DIR__ . '/assets/js/gabarit.js') ?>"></script>
<script src="assets/js/livret.js?v=<?= (int) @filemtime(__DIR__ . '/assets/js/livret.js') ?>"></script>
<script>
  Gabarit.init({});
</script>
</body>
</html>
