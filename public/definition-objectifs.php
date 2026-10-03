<?php

declare(strict_types=1);

require __DIR__ . '/../src/autoload.php';
require __DIR__ . '/../src/Configuration.php';
require __DIR__ . '/../src/Database.php';

use Syntropia\Configuration;
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
    $destinations = array_map('strval', (array) ($_POST['destination'] ?? []));
    $reponsesService->enregistrerLot($siteId, $participantId, 'definition', [
        'objectifs_productions'  => (string) ($_POST['productions'] ?? ''),
        'objectifs_destinations' => implode(',', $destinations),
        'objectifs_terme'        => (string) ($_POST['terme'] ?? ''),
    ]);
    header('Location: ' . Configuration::base() . 'definition-moyens.php');
    exit;
}
$reponses = $reponsesService->pourParticipant($participantId);
$destinationsChoisies = array_filter(explode(',', $reponses['objectifs_destinations'] ?? ''));
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Objectifs — Syntropia</title>
<link rel="stylesheet" href="assets/css/gabarit.css?v=<?= (int) @filemtime(__DIR__ . '/assets/css/gabarit.css') ?>">
<style>
  /* propre à cette étape : pas de bandeau d'outils (la saisie est le
     seul geste de la page) — recaler la tirette/panneau Informations
     sur un seul bandeau. Aucun défilement : le formulaire tient dans
     l'écran. */
  :root{ --haut-bandeaux: 3.4rem; }

  .objectifs-cadre{ width:100%; max-width:30rem; }
  .objectifs-annonce{ font:400 .92rem/1.5 var(--texte); color:var(--muted); margin:0 0 1.6rem; text-align:center; }
  .objectifs-champ{ margin-bottom:1.1rem; border:0; padding:0; }
  .objectifs-champ label, .objectifs-champ legend{
    display:block; font:600 .85rem var(--titre); color:var(--ink); margin-bottom:.4rem; padding:0;
  }
  .objectifs-champ textarea{
    width:100%; padding:.6rem .7rem; font:400 .9rem/1.4 var(--texte); color:var(--ink);
    background:var(--surface); border:1px solid var(--line); border-radius:.5rem; resize:none;
  }
  .objectifs-champ textarea:focus{ outline:2px solid var(--accent); outline-offset:1px; }
  .objectifs-cases{ display:flex; gap:1rem; flex-wrap:wrap; }
  .objectifs-cases label, .objectifs-choix label{ display:flex; align-items:center; gap:.4rem; font:400 .88rem var(--texte); color:var(--ink); }
  .objectifs-choix{ display:flex; gap:1rem; flex-wrap:wrap; }
  .objectifs-bouton{
    width:100%; margin-top:.3rem; padding:.65rem; border:0; border-radius:.6rem;
    background:var(--accent); color:var(--bouton-encre); font:600 .95rem var(--titre); cursor:pointer;
  }
  .objectifs-bouton:hover{ opacity:.92; }
  .objectifs-note{ font:400 .78rem var(--texte); color:var(--muted); text-align:center; margin-top:.6rem; }
  /* l'usage de la réponse, dit au moment de la question — jamais après
     coup. Voir CLAUDE.md § L'esprit à garder dans l'interface. */
  .objectifs-usage{ font:400 .76rem/1.3 var(--texte); color:var(--muted); margin:.3rem 0 0; }
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
          <p class="pg-titre">Définition</p>
          <!-- rempli par Gabarit.init() à partir de public/assets/js/etapes.js -->
          <ul class="pg-liste pg-etapes-phase"></ul>
        </div>
      </div>
    </nav>

    <main class="bureau" id="bureau">
      <div class="objectifs-cadre">
        <p class="objectifs-annonce">Trois questions, deux minutes. Elles guident le
           « comment » — elles ne figent rien.</p>

        <form method="post">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($auth->jeton(), ENT_QUOTES, 'UTF-8') ?>">
          <div class="objectifs-champ">
            <label for="productions">Quelles productions ?</label>
            <textarea id="productions" name="productions" rows="2" placeholder="Légumes, fruits, bois, biomasse…"><?= htmlspecialchars($reponses['objectifs_productions'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
          </div>

          <fieldset class="objectifs-champ">
            <legend>Pour quelle destination ?</legend>
            <div class="objectifs-cases">
              <label><input type="checkbox" class="case" name="destination[]" value="loisir"<?= in_array('loisir', $destinationsChoisies, true) ? ' checked' : '' ?>>Loisir</label>
              <label><input type="checkbox" class="case" name="destination[]" value="vivrier"<?= in_array('vivrier', $destinationsChoisies, true) ? ' checked' : '' ?>>Vivrier</label>
              <label><input type="checkbox" class="case" name="destination[]" value="commercial"<?= in_array('commercial', $destinationsChoisies, true) ? ' checked' : '' ?>>Commercial</label>
              <label><input type="checkbox" class="case" name="destination[]" value="social"<?= in_array('social', $destinationsChoisies, true) ? ' checked' : '' ?>>Social</label>
            </div>
            <p class="objectifs-usage">Guide le « comment » pour atteindre vos objectifs.</p>
          </fieldset>

          <fieldset class="objectifs-champ">
            <legend>À quel terme ?</legend>
            <div class="objectifs-choix">
              <label><input type="radio" class="radio" name="terme" value="court"<?= ($reponses['objectifs_terme'] ?? 'court') === 'court' ? ' checked' : '' ?>>Court — environ 3 ans</label>
              <label><input type="radio" class="radio" name="terme" value="moyen"<?= ($reponses['objectifs_terme'] ?? '') === 'moyen' ? ' checked' : '' ?>>Moyen — 5 à 10 ans</label>
              <label><input type="radio" class="radio" name="terme" value="long"<?= ($reponses['objectifs_terme'] ?? '') === 'long' ? ' checked' : '' ?>>Long — plus de 10 ans</label>
            </div>
            <p class="objectifs-usage">Mesure votre progression et vérifie que le projet reste réaliste.</p>
          </fieldset>

          <button type="submit" class="objectifs-bouton">Continuer</button>
          <p class="objectifs-note">Enregistré dès que vous continuez — vous pouvez revenir sans rien perdre.</p>
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
      <p>Cette étape précise ce que le projet doit produire, pour qui ou
         pour quoi, et à quelle échéance. Le terme choisi — court, moyen ou
         long — aide ensuite à mesurer la progression, placer les étapes, et
         voir si le projet reste réaliste. Une destination peut se cumuler
         avec une autre : un même lieu peut nourrir un foyer et se vendre au
         marché.</p>
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
  Gabarit.init({ etape: 'definition-objectifs' });
</script>
</body>
</html>
