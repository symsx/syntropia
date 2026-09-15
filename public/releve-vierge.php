<?php

declare(strict_types=1);

/**
 * Les fiches de relevé de sol, vierges, à imprimer et à emporter.
 *
 * Adaptée de `application/public/releve-vierge.php` (dépôt de référence).
 * Contenu porté depuis `src/FicheTerrain.php` (voir ce fichier pour le
 * pourquoi de chaque champ).
 *
 * DÉLIBÉRÉMENT HORS DU GABARIT « LE BUREAU ».
 *
 * `CLAUDE.md` § Architecture est catégorique — chaque page, sur le
 * gabarit, aucune exception. Celle-ci n'est pas une page qu'on visite :
 * c'est un document qu'on imprime, pour l'emporter sur le terrain, loin
 * d'un écran. Le gabarit verrouille `overflow:hidden` sur `html,body`
 * précisément pour qu'aucun contenu ne défile jamais — l'inverse exact
 * de ce qu'exige une feuille de sept fiches faite pour être lue au
 * crayon, puis imprimée sur plusieurs pages. Cette page ne charge donc
 * pas `gabarit.css` : elle reprend seulement ses polices et ses couleurs
 * (cohérence visuelle), sans sa grille ni son verrou de défilement.
 * N'utilisez pas ce fichier comme modèle pour une autre page.
 */

require __DIR__ . '/../src/autoload.php';
require __DIR__ . '/../src/Configuration.php';
require __DIR__ . '/../src/Database.php';

use Syntropia\FicheTerrain;
use Syntropia\Role;
use Syntropia\Web\Authentification;

Authentification::demarrer($_SERVER);
$auth = new Authentification();
$auth->exige(Role::Utilisateur);

$fiches = FicheTerrain::fiches();
$conditions = FicheTerrain::conditions();

/** Une ou plusieurs lignes à remplir au crayon. */
$aRemplir = static function (int $lignes = 1): string {
    return str_repeat('<span class="a-remplir"></span>', max(1, $lignes));
};

/** Un minimum de mise en forme markdown — gras seulement, le texte reste sûr. */
$g = static function (string $texte): string {
    $sur = htmlspecialchars($texte, ENT_QUOTES, 'UTF-8');
    return preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $sur) ?? $sur;
};
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Fiches de relevé de sol — Syntropia</title>
<style>
  @font-face{ font-family:'Clash Display'; src:url('assets/polices/ClashDisplay-Semibold.woff2') format('woff2'); font-weight:600; font-style:normal; font-display:swap; }
  @font-face{ font-family:'Archivo'; src:url('assets/polices/Archivo-Regular.woff2') format('woff2'); font-weight:400; font-style:normal; font-display:swap; }
  @font-face{ font-family:'Archivo'; src:url('assets/polices/Archivo-Medium.woff2') format('woff2'); font-weight:500; font-style:normal; font-display:swap; }
  :root{
    --ink:#26312A; --muted:#4E5A50; --line:#C7D2BE;
    --fond:#F6EEDB; --surface:#FFFFFF; --accent-titre:#84651F; --accent:#C89B3C; --accent-fond:#F6EEDB;
    --titre:'Clash Display', system-ui, sans-serif; --texte:'Archivo', system-ui, sans-serif;
  }
  *{ box-sizing:border-box; }
  body{ margin:0; background:var(--fond); color:var(--ink); font:400 .95rem/1.5 var(--texte); }
  .page{ max-width:46rem; margin:0 auto; padding:1.5rem 1.5rem 4rem; }

  .barre{
    position:sticky; top:0; display:flex; justify-content:space-between; align-items:center;
    background:var(--surface); border-bottom:1px solid var(--line); padding:.7rem 1.5rem;
    margin-bottom:1.5rem;
  }
  .barre a{ color:var(--accent-titre); font:600 .85rem var(--titre); text-decoration:none; }
  .barre a:hover{ text-decoration:underline; }
  .barre button{
    padding:.4rem .9rem; border:0; border-radius:.5rem; background:var(--accent);
    color:#fff; font:600 .85rem var(--titre); cursor:pointer;
  }

  h1{ font:600 1.4rem var(--titre); color:var(--accent-titre); margin:0 0 .3rem; }
  h2{ font:600 1.05rem var(--titre); color:var(--accent-titre); margin:1.6rem 0 .2rem; }
  .numero-fiche{ font:400 .75rem var(--texte); color:var(--muted); font-weight:400; }
  p{ margin:.4rem 0; }
  .chapeau{ color:var(--muted); }
  .avant, .sert{ font-size:.88rem; }
  .quand{ font-size:.82rem; color:var(--muted); }
  .note-fiche{ font-size:.82rem; color:var(--muted); font-style:italic; }

  .avant-partir{ padding-left:1.2rem; }
  .avant-partir li{ margin-bottom:.2rem; font-size:.9rem; }

  .grille{ display:grid; grid-template-columns:1fr; gap:.5rem; margin:.6rem 0 1rem; }
  .champ-releve{
    display:flex; align-items:baseline; gap:.6rem; border-bottom:1px dotted var(--line); padding-bottom:.25rem;
  }
  .champ-releve.large{ flex-direction:column; align-items:stretch; gap:.25rem; }
  .champ-libelle{ font:500 .84rem var(--titre); color:var(--ink); white-space:nowrap; }
  .champ-libelle small{ font:400 .74rem var(--texte); color:var(--muted); white-space:normal; }
  .a-remplir{
    display:inline-block; flex:1 1 auto; min-width:3rem; height:1.1em; border-bottom:1px solid var(--line);
  }
  .champ-releve.large .a-remplir{ height:1.6em; border:1px solid var(--line); border-radius:.3rem; }

  table.lecture{ width:100%; border-collapse:collapse; margin:.6rem 0 1rem; font-size:.85rem; }
  table.lecture caption{ text-align:left; font:500 .78rem var(--titre); color:var(--muted); margin-bottom:.3rem; }
  table.lecture th, table.lecture td{ text-align:left; padding:.3rem .5rem; border-bottom:1px solid var(--line); }
  table.lecture th{ font-weight:500; width:40%; }

  .saut-avant{ break-before:page; page-break-before:always; }
  .pied-dossier{ margin-top:2rem; padding-top:1rem; border-top:1px solid var(--line); font-size:.82rem; color:var(--muted); }

  @media print{
    .barre{ display:none; }
    body{ background:#fff; }
    .page{ max-width:none; padding:0; }
    a{ color:inherit; text-decoration:none; }
  }
</style>
</head>
<body>

<div class="barre">
  <a href="conception-etude-sol.php">← Étude du sol</a>
  <button type="button" onclick="window.print()">Imprimer</button>
</div>

<div class="page">
  <header>
    <h1>Relevé de sol — fiches à remplir</h1>
    <p class="chapeau">
      <strong>Ce qui va se passer.</strong> Sept mesures, à faire au même endroit et
      dans la journée pour les cinq premières. Comptez une demi-journée,
      beaucoup à genoux, les mains dans la terre — et un long moment à attendre que
      l'eau descende. Chaque fiche annonce son geste et sa durée avant sa grille.
      Si votre expérience diffère de ce qui est annoncé, notez-le : ce n'est pas
      un échec, c'est ce qui fera corriger la fiche.
    </p>
    <p class="chapeau">
      Les cinq premiers relevés se prennent sur chaque parcelle, avant de l'ouvrir —
      un relevé fait ailleurs sur le site ne dit rien de celle-ci. Et les mesures
      « avant » se prennent avant le bâchage et avant tout travail du sol :
      une fois la prairie décomposée, le point zéro est perdu définitivement.
    </p>
  </header>

  <section>
    <h2>Avant de partir</h2>
    <ul class="avant-partir">
<?php foreach (FicheTerrain::avantDePartir() as $item): ?>
      <li><?= htmlspecialchars($item, ENT_QUOTES, 'UTF-8') ?></li>
<?php endforeach; ?>
    </ul>
  </section>

  <section>
    <h2>Conditions du relevé</h2>
    <p class="sert"><?= $g(FicheTerrain::sertLesConditions()) ?></p>
    <div class="grille">
<?php foreach ($conditions as $c): ?>
      <div class="champ-releve">
        <span class="champ-libelle">
          <?= htmlspecialchars($c['libelle'], ENT_QUOTES, 'UTF-8') ?>
<?php if ($c['unite'] !== null): ?>
          <small>(<?= htmlspecialchars($c['unite'], ENT_QUOTES, 'UTF-8') ?>)</small>
<?php endif; ?>
<?php if ($c['sens'] !== null): ?>
          <br><small><?= $g($c['sens']) ?></small>
<?php endif; ?>
        </span>
        <?= $aRemplir() ?>
      </div>
<?php endforeach; ?>
    </div>
  </section>

<?php foreach ($fiches as $fiche): ?>
  <section class="saut-avant">
    <h2><?= htmlspecialchars($fiche['titre'], ENT_QUOTES, 'UTF-8') ?>
      <span class="numero-fiche">fiche <?= htmlspecialchars($fiche['numero'], ENT_QUOTES, 'UTF-8') ?></span></h2>
    <p class="avant"><strong>Ce qui va se passer.</strong> <?= $g($fiche['avant']) ?></p>
    <p class="sert"><strong>À quoi ça sert.</strong> <?= $g($fiche['sert']) ?></p>
<?php if ($fiche['quand'] !== null): ?>
    <p class="quand"><strong>Quand.</strong> <?= htmlspecialchars($fiche['quand'], ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
<?php if ($fiche['note'] !== null): ?>
    <p class="note-fiche"><?= $g($fiche['note']) ?></p>
<?php endif; ?>

    <div class="grille">
<?php foreach ($fiche['champs'] as $c): ?>
      <div class="champ-releve<?= ($c['lignes'] ?? 1) > 1 ? ' large' : '' ?>">
        <span class="champ-libelle">
          <?= htmlspecialchars($c['libelle'], ENT_QUOTES, 'UTF-8') ?>
<?php if ($c['unite'] !== null): ?>
          <small>(<?= htmlspecialchars($c['unite'], ENT_QUOTES, 'UTF-8') ?>)</small>
<?php endif; ?>
        </span>
        <?= $aRemplir((int) ($c['lignes'] ?? 1)) ?>
      </div>
<?php endforeach; ?>
    </div>

<?php if ($fiche['lecture'] !== []): ?>
    <table class="lecture">
      <caption>Lire le résultat, sur place</caption>
<?php foreach ($fiche['lecture'] as $constat => $sens): ?>
      <tr><th scope="row"><?= htmlspecialchars($constat, ENT_QUOTES, 'UTF-8') ?></th>
          <td><?= $g($sens) ?></td></tr>
<?php endforeach; ?>
    </table>
<?php endif; ?>
  </section>
<?php endforeach; ?>

  <section class="saut-avant">
    <h2>Ce qu'on a vu, et qui n'entre dans aucune case</h2>
    <p class="chapeau">La ligne la plus utile du relevé, souvent. Une odeur, une
       racine qui contourne, un changement de couleur, un doute sur un geste.</p>
    <div class="grille">
      <div class="champ-releve large">
        <span class="champ-libelle">Notes</span>
        <?= $aRemplir(6) ?>
      </div>
    </div>
  </section>

  <footer class="pied-dossier">
    <p><strong>Au retour</strong> — reporter dans le carnet du site. Les protocoles
       complets, avec le geste et les pièges, restent hors de cette fiche : elle
       aide à exécuter un protocole qu'on connaît, elle ne l'enseigne pas.</p>
  </footer>
</div>

</body>
</html>
