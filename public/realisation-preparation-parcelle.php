<?php

declare(strict_types=1);

require __DIR__ . '/../src/autoload.php';
require __DIR__ . '/../src/Configuration.php';
require __DIR__ . '/../src/Database.php';

use Syntropia\Configuration;
use Syntropia\Documentation;
use Syntropia\Role;
use Syntropia\Web\Authentification;
use Syntropia\Web\Reponses;
use Syntropia\Web\Sites;

Authentification::demarrer($_SERVER);
$auth = new Authentification();
$auth->exige(Role::Utilisateur);

$sites = new Sites();
$compteId = (int) $auth->compteId();
$site = $sites->pourCompte($compteId);
if ($site === null) {
    header('Location: ' . Configuration::base() . 'definition-projet.php');
    exit;
}
$siteId = (int) $site['id'];
$participantId = $sites->participantDuCompte($siteId, $compteId, $auth->nomAffiche() ?? 'Porteur');
$reponsesService = new Reponses();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $auth->jetonValide($_POST['csrf'] ?? null)) {
    $reponsesService->enregistrerLot($siteId, $participantId, 'realisation', [
        'preparation_technique'   => (string) ($_POST['technique'] ?? ''),
        'preparation_realisee'    => (string) ($_POST['realisee'] ?? ''),
        'preparation_precisions'  => (string) ($_POST['precisions'] ?? ''),
    ]);
    header('Location: ' . Configuration::base() . 'realisation-materiel-vegetal.php');
    exit;
}
$reponses = $reponsesService->pourParticipant($participantId);
$termes = Documentation::glossaire(__DIR__ . '/../contenu/glossaire.md');
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Préparation de la parcelle — Syntropia</title>
<link rel="stylesheet" href="assets/css/gabarit.css?v=<?= (int) @filemtime(__DIR__ . '/assets/css/gabarit.css') ?>">
<style>
  :root{ --haut-bandeaux: 3.4rem; }

  .prep-cadre{ width:100%; max-width:30rem; }
  .prep-annonce{ font:400 .92rem/1.5 var(--texte); color:var(--muted); margin:0 0 1.6rem; text-align:center; }
  .prep-champ{ margin-bottom:1.1rem; border:0; padding:0; }
  .prep-champ label, .prep-champ legend{
    display:block; font:600 .85rem var(--titre); color:var(--ink); margin-bottom:.4rem; padding:0;
  }
  .prep-choix{ display:flex; gap:1rem; flex-wrap:wrap; }
  .prep-choix label{ display:flex; align-items:center; gap:.4rem; font:400 .9rem var(--texte); color:var(--ink); }
  .prep-champ textarea{
    width:100%; padding:.6rem .7rem; font:400 .9rem/1.4 var(--texte); color:var(--ink);
    background:var(--surface); border:1px solid var(--line); border-radius:.5rem; resize:none;
  }
  .prep-champ textarea:focus{ outline:2px solid var(--accent); outline-offset:1px; }
  .prep-bouton{
    width:100%; margin-top:.3rem; padding:.65rem; border:0; border-radius:.6rem;
    background:var(--accent); color:var(--bouton-encre); font:600 .95rem var(--titre); cursor:pointer;
  }
  .prep-bouton:hover{ opacity:.92; }
  .prep-note{ font:400 .78rem var(--texte); color:var(--muted); text-align:center; margin-top:.6rem; }

  /* Sept choix sur deux fieldsets : sur un écran peu haut, la page se resserre au lieu de déborder. */
  @media (max-height:46rem){
    .prep-annonce{ margin-bottom:.7rem; }
    .prep-champ{ margin-bottom:.6rem; }
    .prep-choix{ gap:.1rem .9rem; }
    .prep-choix label{ font-size:.86rem; }
    .prep-bouton{ margin-top:0; padding:.5rem; }
  }
  @media (max-height:38rem){
    .prep-annonce, .prep-note{ display:none; }
    .prep-cadre{ padding-top:1.6rem; }
    .prep-champ textarea{ padding:.4rem .6rem; }
  }
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
          <p class="pg-titre">Réalisation</p>
          <ul class="pg-liste pg-etapes-phase"></ul>
        </div>
      </div>
    </nav>

    <main class="bureau" id="bureau">
      <div class="prep-cadre">
        <p class="prep-annonce">Avant de planter, le terrain se prépare. Indiquez comment vous
           comptez vous y prendre.</p>

        <form method="post">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($auth->jeton(), ENT_QUOTES, 'UTF-8') ?>">
          <fieldset class="prep-champ">
            <legend>La technique</legend>
            <div class="prep-choix">
              <label><input type="radio" class="radio" name="technique" value="dechaumage"<?= ($reponses['preparation_technique'] ?? '') === 'dechaumage' ? ' checked' : '' ?>><?= Documentation::terme($termes, 'dechaumage', 'Déchaumage') ?></label>
              <label><input type="radio" class="radio" name="technique" value="bachage"<?= ($reponses['preparation_technique'] ?? '') === 'bachage' ? ' checked' : '' ?>>Couverture, <?= Documentation::terme($termes, 'bachage', 'bâchage') ?></label>
              <label><input type="radio" class="radio" name="technique" value="les-deux"<?= ($reponses['preparation_technique'] ?? '') === 'les-deux' ? ' checked' : '' ?>>Les deux</label>
            </div>
          </fieldset>

          <fieldset class="prep-champ">
            <legend>Réalisée</legend>
            <div class="prep-choix">
              <label><input type="radio" class="radio" name="realisee" value="manuellement"<?= ($reponses['preparation_realisee'] ?? 'manuellement') === 'manuellement' ? ' checked' : '' ?>>Manuellement</label>
              <label><input type="radio" class="radio" name="realisee" value="animaux"<?= ($reponses['preparation_realisee'] ?? '') === 'animaux' ? ' checked' : '' ?>>Par un ou plusieurs animaux</label>
              <label><input type="radio" class="radio" name="realisee" value="machine"<?= ($reponses['preparation_realisee'] ?? '') === 'machine' ? ' checked' : '' ?>>Avec une machine (motoculteur…)</label>
              <label><input type="radio" class="radio" name="realisee" value="les-deux"<?= ($reponses['preparation_realisee'] ?? '') === 'les-deux' ? ' checked' : '' ?>>Plusieurs moyens</label>
              <label><input type="radio" class="radio" name="realisee" value="autre"<?= ($reponses['preparation_realisee'] ?? '') === 'autre' ? ' checked' : '' ?>>Autrement</label>
            </div>
          </fieldset>

          <div class="prep-champ">
            <label for="precisions">Précisions</label>
            <textarea id="precisions" name="precisions" rows="2" placeholder="Quelle machine ou quels animaux, quelle durée, ce qui vous a décidé…"><?= htmlspecialchars($reponses['preparation_precisions'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
          </div>

          <button type="submit" class="prep-bouton">Continuer</button>
          <p class="prep-note">Vos réponses sont enregistrées quand vous continuez. Vous pourrez les modifier plus tard.</p>
        </form>
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
      <p>Le déchaumage retourne la couverture existante. Couvrir ou bâcher l'étouffe sans
         travailler le sol : c'est plus lent, et souvent plus respectueux de la vie qui s'y
         trouve déjà. Le choix dépend du temps disponible, de ce qui pousse déjà là, et de ce
         que vous pouvez mobiliser : bras, outils ou animaux.</p>
      <p><strong>Pourquoi elle compte.</strong> La préparation du sol et la plantation sont les deux moments les plus
         coûteux du projet, et le saut du chat en dépend. Les bâcler se paie ensuite en désherbage et en rattrapage
         (Théry, ch. 14). Ce que vous notez ici se relit à l'étape « Saut du chat », parmi ce qui le rend possible.</p>
      <p><strong>Ce que disent les sources.</strong> Préparez tôt, mais couvrez toujours le sol en attendant la plantation, sans laisser
         les bâches plus qu'il n'en faut. Bâchez sur un sol le plus humide possible, le lendemain matin d'une pluie, avant que la rosée
         s'évapore. Bâchez aussi les chemins pendant une saison, avant d'y semer du trèfle blanc. L'idéal, selon Faraj, est un couvert
         adapté semé un ou deux ans avant de planter. Une fois planté, le sol ne se travaille plus. La préparation est la première chose
         que le climat empêche : prévoyez un repli manuel.</p>
      <p><strong>Le déchaumage prépare le sol à recevoir la plantation</strong>, avec les moyens dont vous disposez : une bâche, un
         motoculteur, des animaux ou autre chose. Si des adventices reviennent ensuite, la plantation n'est pas assez dense, et des plantes
         de colonisation sont encore là. On désherbe alors à la main, et l'on densifie avec des plantes du système d'accumulation.</p>
      <p><strong>Un sol exempt d'adventices.</strong> Les semis, ceux des tortues en particulier, se font sur un sol propre. Lopez pose comme
         règle de semer dans un sol exempt d'adventices. La régénération naturelle assistée prévoit l'élimination préalable des adventices et
         des faux-semis, en un à quatre passages, pour épuiser la banque de graines. La concurrence des adventices freine le saut du chat chez
         les pionniers d'une succession, surtout celle des graminées, dont les racines gênent fortement les arbres (Théry, ch. 3).</p>
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
  Gabarit.init({ etape: 'realisation-preparation-parcelle' });
</script>
</body>
</html>
