<?php

declare(strict_types=1);

require __DIR__ . '/../src/autoload.php';
require __DIR__ . '/../src/Configuration.php';
require __DIR__ . '/../src/Database.php';

use Syntropia\Documentation;
use Syntropia\FicheTerrain;
use Syntropia\Role;
use Syntropia\Web\Authentification;

Authentification::demarrer($_SERVER);
$auth = new Authentification();
$auth->exige(Role::Utilisateur);

$termes = Documentation::glossaire(__DIR__ . '/../contenu/glossaire.md');

// Cet aperçu se lit dans l'ordre des numéros (01 → 07), plus facile à
// repérer qu'une liste ; l'ordre du terrain — différent, et pour de
// bonnes raisons — reste celui de la fiche imprimée. Voir
// FicheTerrain::fiches() et le panneau Informations de cette page.
$fiches = FicheTerrain::fiches();

// Succinct, pas la méthode — la méthode (« sol ressuyé, trois
// prélèvements ») appartient à la fiche à imprimer, pas à cet aperçu.
// Ici, la règle de l'atelier : dire à quoi sert le test avant de
// demander de le faire. Résume `FicheTerrain::fiches()[...]['sert']`,
// écrit pour la fiche papier, plus long qu'il n'en faut pour une liste.
// Remarque du porteur, 2026-09-15.
$pourquoi = [
    'flore'        => 'Choisir des espèces déjà adaptées au terrain.',
    'profil'       => "Savoir s'il faut " . Documentation::terme($termes, 'decompacter', 'décompacter') . ", et jusqu'où.",
    'densite'      => 'Voir si le sol se ' . Documentation::terme($termes, 'decompacter', 'décompacte') . ' au fil des ans.',
    'infiltration' => "Déterminer les besoins d'arrosage pendant les trois premières années.",
    'slake'        => 'Savoir si le sol se refermera à la première pluie.',
    'vers'         => "Suivre la vie du sol — l'indicateur qui bouge le plus vite.",
    'texture'      => "Choisir les espèces de bordure, régler l'arrosage.",
];
foreach ($fiches as $cle => &$fiche) {
    $fiche['pourquoi'] = $pourquoi[$cle] ?? '';
}
unset($fiche);
usort($fiches, static fn (array $a, array $b): int => $a['numero'] <=> $b['numero']);
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Étude du sol — Syntropia</title>
<link rel="stylesheet" href="assets/css/gabarit.css?v=<?= (int) @filemtime(__DIR__ . '/assets/css/gabarit.css') ?>">
<style>
  /* propre à cette étape : pas de bandeau d'outils. Cette page n'est pas
     un formulaire — c'est une annonce (ce qu'on va déterminer et
     pourquoi, voir plan-du-site.md) suivie d'une action : ouvrir les
     fiches à imprimer. Sept lignes, un aperçu, jamais le détail complet
     — celui-là vit sur la fiche elle-même (releve-vierge.php), pensée
     pour le papier, pas pour l'écran du bureau. */
  :root{ --haut-bandeaux: 3.4rem; }

  .sol-cadre{ width:100%; max-width:34rem; }
  .sol-annonce{ font:400 .92rem/1.55 var(--texte); color:var(--ink); margin:0 0 1.1rem; text-align:center; }
  .sol-liste{ list-style:none; margin:0 0 1.3rem; padding:0; border-top:1px solid var(--line); }
  .sol-ligne{
    display:flex; align-items:baseline; gap:.7rem; padding:.5rem .1rem;
    border-bottom:1px solid var(--line);
  }
  .sol-numero{
    flex:0 0 auto; font:600 .72rem var(--titre); color:var(--accent-titre);
    background:var(--accent-fond); border-radius:1rem; padding:.15rem .5rem;
  }
  .sol-titre{ flex:1 1 auto; font:600 .86rem var(--titre); color:var(--ink); }
  .sol-pourquoi{ flex:0 0 auto; font:400 .74rem var(--texte); color:var(--muted); text-align:right; max-width:15rem; }

  .sol-action{
    display:block; width:100%; padding:.65rem; border:0; border-radius:.6rem;
    background:var(--accent); color:var(--bouton-encre); font:600 .92rem var(--titre);
    text-align:center; text-decoration:none; cursor:pointer;
  }
  .sol-action:hover{ opacity:.92; }
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
      <button class="etape-bouton" id="etape-precedente" aria-label="Étape précédente" title="Étape précédente">
        <svg class="etape-chevron" viewBox="0 0 10 10" aria-hidden="true"><polygon points="7,1 2,5 7,9" fill="currentColor"/></svg>
      </button>
      <span class="bg-centre-titre">&nbsp;</span>
      <button class="etape-bouton" id="etape-suivante" aria-label="Étape suivante" title="Étape suivante">
        <svg class="etape-chevron" viewBox="0 0 10 10" aria-hidden="true"><polygon points="3,1 8,5 3,9" fill="currentColor"/></svg>
      </button>
    </div>
    <div class="bg-droite">
      <button class="theme-bascule" id="theme-bascule" aria-label="Changer de thème" title="Thème">
        <span class="theme-icone" id="theme-icone" aria-hidden="true">◐</span>
      </button>
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
          <p class="pg-titre">Phase projet</p>
          <ul class="pg-liste pg-phase-projet"></ul>
        </div>
        <div class="pg-section">
          <p class="pg-titre">Conception</p>
          <ul class="pg-liste pg-etapes-phase"></ul>
        </div>
      </div>
    </nav>

    <main class="bureau" id="bureau">
      <div class="sol-cadre">
        <p class="sol-annonce">Sept relevés déterminent où en est le sol — pas pour
           juger, pour choisir : <?= Documentation::terme($termes, 'decompacter', 'décompacter') ?>
           ou non, planter ou semer, arroser court et fréquent ou long et espacé.
           Comptez une demi-journée sur place, dont l'essentiel à attendre.</p>

        <ul class="sol-liste">
<?php foreach ($fiches as $fiche): ?>
          <li class="sol-ligne">
            <span class="sol-numero"><?= htmlspecialchars($fiche['numero'], ENT_QUOTES, 'UTF-8') ?></span>
            <span class="sol-titre"><?= htmlspecialchars($fiche['titre'], ENT_QUOTES, 'UTF-8') ?></span>
            <span class="sol-pourquoi"><?= $fiche['pourquoi'] ?? '' /* déjà échappé, voir Documentation::terme() */ ?></span>
          </li>
<?php endforeach; ?>
        </ul>

        <a class="sol-action" href="releve-vierge.php" target="_blank" rel="noopener">Ouvrir les fiches à imprimer</a>
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
      <p>Cet aperçu suit l'ordre des numéros, pour s'y repérer facilement.
         <strong>Sur le terrain, suivez l'ordre de la fiche imprimée</strong>,
         différent et volontaire : la flore se relève avant de piétiner quoi
         que ce soit, puis le <?= Documentation::terme($termes, 'profil-de-sol', 'profil') ?>
         s'ouvre — son trou sert ensuite à l'<?= Documentation::terme($termes, 'infiltration', 'infiltration') ?>
         et au comptage des vers, on ne le rebouche qu'à la fin. Chaque fiche
         annonce le geste et la durée avant sa grille, et dit ce que la mesure
         permettra de décider — jamais quel chemin prendre : un
         <?= Documentation::terme($termes, 'densimetre', 'densimètre') ?> ne prescrit pas un
         <?= Documentation::terme($termes, 'labour', 'labour') ?>.</p>
      <p>La saisie numérique de ces relevés n'existe pas encore ; la fiche
         papier, elle-même, sert dès aujourd'hui.</p>
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
  Gabarit.init({ etape: 'conception-etude-sol' });
</script>
</body>
</html>
