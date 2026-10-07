<?php

declare(strict_types=1);

/**
 * Le glossaire — les mots techniques, en une phrase chacun.
 *
 * Le texte vit dans `contenu/glossaire.md`, même format que les deux
 * livrets (`Documentation::chapitres()` — un terme = un `# titre`, sa
 * définition = le premier paragraphe qui suit). Cette même source
 * alimente aussi la bulle au survol de tout `.terme` posé sur une autre
 * page — voir `assets/js/gabarit.js`, `initTermes()`.
 *
 * PAS le livret qu'on tourne (documentation-site.php/documentation-saf.php) :
 * on cherche UN mot, on ne lit pas trente termes dans l'ordre. Une
 * recherche qui filtre en direct, et **jamais plus d'une poignée de
 * résultats montrés à la fois** — pas une liste qui défile, une liste
 * qui se réduit. Décidé avec le porteur, 2026-09-15.
 */

require __DIR__ . '/../src/autoload.php';
require __DIR__ . '/../src/Configuration.php';
require __DIR__ . '/../src/Database.php';

use Syntropia\Documentation;
use Syntropia\Web\Authentification;

Authentification::reprendre($_SERVER);
$auth = new Authentification();
$connecte = $auth->estConnecte();

$chapitres = Documentation::chapitres(__DIR__ . '/../contenu/glossaire.md');
usort($chapitres, static fn (array $a, array $b): int => strcoll($a['titre'], $b['titre']));
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Glossaire — Syntropia</title>
<link rel="stylesheet" href="assets/css/gabarit.css?v=<?= (int) @filemtime(__DIR__ . '/assets/css/gabarit.css') ?>">
<style>
  :root{ --haut-bandeaux: 3.4rem; }

  .gloss-cadre{ width:100%; max-width:34rem; }
  .gloss-annonce{ font:400 .9rem/1.5 var(--texte); color:var(--muted); margin:0 0 1.1rem; text-align:center; }
  .gloss-champ{
    width:100%; padding:.6rem .8rem; font:400 .95rem var(--texte); color:var(--ink);
    background:var(--surface); border:1px solid var(--line); border-radius:.6rem; margin-bottom:.5rem;
  }
  .gloss-champ:focus{ outline:2px solid var(--accent); outline-offset:1px; }
  .gloss-compte{ font:400 .74rem var(--texte); color:var(--muted); margin:0 0 .8rem; min-height:1.1em; }

  /* jamais de défilement : une hauteur bornée, coupée net plutôt que
     scrollée — le compte au-dessus dit ce qui manque. */
  .gloss-liste{ margin:0; padding:0; max-height:23rem; overflow:hidden; }
  .gloss-entree{ padding:.7rem 0; border-top:1px solid var(--line); }
  .gloss-entree:first-child{ border-top:0; }
  .gloss-entree dt{ font:600 .92rem var(--titre); color:var(--accent-titre); }
  .gloss-entree dd{ margin:.2rem 0 0; font:400 .86rem/1.45 var(--texte); color:var(--ink); }
  .gloss-entree[hidden]{ display:none; }
  .gloss-vide{ font:400 .86rem var(--texte); color:var(--muted); text-align:center; padding:1.5rem 0; }
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
      <span class="bg-centre-titre">Glossaire</span>
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
      <div class="gloss-cadre" data-tenir>
        <p class="gloss-annonce">Les mots techniques de l'atelier, expliqués en une phrase
           chacun. Ce sont les mêmes définitions qu'au survol d'un mot souligné, ailleurs
           sur le site.</p>

        <input type="search" id="gloss-chercher" class="gloss-champ"
               placeholder="pieds-mères, ressuyage, RNA…" autocomplete="off" autofocus>
        <p class="gloss-compte" id="gloss-compte"></p>

<?php if ($chapitres === []): ?>
        <p class="gloss-vide">Ce glossaire n'a pas encore de mot.</p>
<?php else: ?>
        <dl class="gloss-liste" id="gloss-liste">
<?php foreach ($chapitres as $ch):
        $recherche = mb_strtolower($ch['titre'] . ' ' . implode(' ', array_map('strip_tags', $ch['paragraphes']))); ?>
          <div class="gloss-entree" data-recherche="<?= htmlspecialchars($recherche, ENT_QUOTES, 'UTF-8') ?>">
            <dt><?= htmlspecialchars($ch['titre'], ENT_QUOTES, 'UTF-8') ?></dt>
<?php foreach ($ch['paragraphes'] as $p): ?>
            <dd><?= $p /* déjà échappé par Documentation::chapitres() */ ?></dd>
<?php endforeach; ?>
          </div>
<?php endforeach; ?>
        </dl>
<?php endif; ?>
      </div>
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
      <p>Tapez un mot pour le chercher dans les titres et dans les définitions. Sans
         recherche, la page montre quelques mots pris dans l'alphabet. Si la recherche
         donne plus de résultats que la page n'en montre, précisez-la.</p>
      <p>Les mots soulignés en pointillés, ailleurs dans l'atelier, ont la même
         définition. Survolez ou touchez un de ces mots pour la voir sans quitter la
         page.</p>
    </div>
  </aside>

  <footer class="pied">
    <span class="pied-mention">Syntropia — en construction</span>
    <span class="pied-spacer"></span>
  </footer>

</div>

<script src="assets/js/etapes.js?v=<?= (int) @filemtime(__DIR__ . '/assets/js/etapes.js') ?>"></script>
<script src="assets/js/gabarit.js?v=<?= (int) @filemtime(__DIR__ . '/assets/js/gabarit.js') ?>"></script>
<script>
  Gabarit.init({});

  (function () {
    'use strict';
    var champ = document.getElementById('gloss-chercher');
    var liste = document.getElementById('gloss-liste');
    var compte = document.getElementById('gloss-compte');
    if (!champ || !liste) { return; }
    var entrees = Array.prototype.slice.call(liste.querySelectorAll('.gloss-entree'));

    function normaliser(texte) {
      return texte.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
    }

    /* Jamais un défilement, jamais une définition coupée au milieu :
       on révèle les entrées une à une et on s'arrête juste avant que
       la liste ne dépasse sa hauteur maximale — pas un compte fixe
       (8, 10…), qui coupait une entrée longue en plein mot. Remarque
       du porteur, 2026-09-15, trouvée en testant le rendu réel. */
    function filtrer() {
      var q = normaliser(champ.value.trim());
      var correspond = entrees.filter(function (e) {
        return q === '' || normaliser(e.dataset.recherche).indexOf(q) !== -1;
      });
      entrees.forEach(function (e) { e.hidden = true; });

      var maxHauteur = parseFloat(getComputedStyle(liste).maxHeight) || Infinity;
      var montres = 0;
      for (var i = 0; i < correspond.length; i++) {
        correspond[i].hidden = false;
        if (liste.scrollHeight > maxHauteur) {
          correspond[i].hidden = true;
          break;
        }
        montres++;
      }

      if (correspond.length === 0) {
        compte.textContent = q === '' ? '' : 'Aucun mot ne correspond.';
      } else if (montres < correspond.length) {
        compte.textContent = montres + ' mot' + (montres > 1 ? 's' : '') + ' sur ' + correspond.length
          + ' qui correspondent — affinez la recherche pour voir les autres.';
      } else if (q === '') {
        compte.textContent = correspond.length + ' mot' + (correspond.length > 1 ? 's' : '') + ' au total — tapez pour chercher.';
      } else {
        compte.textContent = correspond.length + ' mot' + (correspond.length > 1 ? 's' : '') + ' trouvé' + (correspond.length > 1 ? 's' : '') + '.';
      }
    }

    champ.addEventListener('input', filtrer);
    filtrer();
  })();
</script>
</body>
</html>
