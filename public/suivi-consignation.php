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
    $reponsesService->enregistrerLot($siteId, $participantId, 'suivi', [
        'consignation_suivi_date'          => (string) ($_POST['date'] ?? ''),
        'consignation_suivi_observations'  => (string) ($_POST['observations'] ?? ''),
        'consignation_suivi_resultats'     => (string) ($_POST['resultats'] ?? ''),
    ]);
    header('Location: ' . Configuration::base() . 'conception-parcelles.php');
    exit;
}
$reponses = $reponsesService->pourParticipant($participantId);
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Consignation — Syntropia</title>
<link rel="stylesheet" href="assets/css/gabarit.css?v=<?= (int) @filemtime(__DIR__ . '/assets/css/gabarit.css') ?>">
<style>
  :root{ --haut-bandeaux: 3.4rem; }

  .consuiv-cadre{ width:100%; max-width:30rem; }
  .consuiv-annonce{ font:400 .92rem/1.5 var(--texte); color:var(--muted); margin:0 0 1.6rem; text-align:center; }
  .consuiv-champ{ margin-bottom:1.1rem; }
  .consuiv-champ label{ display:block; font:600 .85rem var(--titre); color:var(--ink); margin-bottom:.4rem; }
  .consuiv-champ input[type=date]{
    width:100%; padding:.55rem .7rem; font:400 .9rem var(--texte); color:var(--ink);
    background:var(--surface); border:1px solid var(--line); border-radius:.5rem;
  }
  .consuiv-champ textarea{
    width:100%; padding:.6rem .7rem; font:400 .9rem/1.4 var(--texte); color:var(--ink);
    background:var(--surface); border:1px solid var(--line); border-radius:.5rem; resize:none;
  }
  .consuiv-champ input:focus, .consuiv-champ textarea:focus{ outline:2px solid var(--accent); outline-offset:1px; }
  .consuiv-bouton{
    width:100%; margin-top:.3rem; padding:.65rem; border:0; border-radius:.6rem;
    background:var(--accent); color:var(--bouton-encre); font:600 .95rem var(--titre); cursor:pointer;
  }
  .consuiv-bouton:hover{ opacity:.92; }
  .consuiv-note{ font:400 .78rem var(--texte); color:var(--muted); text-align:center; margin-top:.6rem; }
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
          <p class="pg-titre">Suivi</p>
          <ul class="pg-liste pg-etapes-phase"></ul>
        </div>
        <p class="pg-pied">Quatrième et dernière étape du parcours construit — voir plan-du-site.md.</p>
      </div>
    </nav>

    <main class="bureau" id="bureau">
      <div class="consuiv-cadre">
        <p class="consuiv-annonce">Ce que vous observez, noté — le suivi se relit mieux qu'il
           ne se souvient.</p>

        <form method="post">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($auth->jeton(), ENT_QUOTES, 'UTF-8') ?>">
          <div class="consuiv-champ">
            <label for="date">Quand</label>
            <input type="date" id="date" name="date" value="<?= htmlspecialchars($reponses['consignation_suivi_date'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
          </div>

          <div class="consuiv-champ">
            <label for="observations">Ce qui est observé</label>
            <textarea id="observations" name="observations" rows="2" placeholder="Croissance, floraison, ravageurs, météo notable…"><?= htmlspecialchars($reponses['consignation_suivi_observations'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
          </div>

          <div class="consuiv-champ">
            <label for="resultats">Les résultats</label>
            <textarea id="resultats" name="resultats" rows="2" placeholder="Ce que ça donne, comparé à ce qui était espéré"><?= htmlspecialchars($reponses['consignation_suivi_resultats'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
          </div>

          <button type="submit" class="consuiv-bouton">Continuer</button>
          <p class="consuiv-note">Enregistré dès que vous continuez — vous pouvez revenir sans rien perdre.</p>
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
      <p>Cette consignation-ci se répète — un projet vivant y revient
         bien plus souvent qu'aux étapes amont, réglées une fois. Rien
         n'empêche d'y revenir à volonté : vos réponses précédentes
         restent, et se réécrivent à chaque passage.</p>
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
  Gabarit.init({ etape: 'suivi-consignation' });
</script>
</body>
</html>
