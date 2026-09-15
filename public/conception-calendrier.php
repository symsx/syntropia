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
    $reponsesService->enregistrerLot($siteId, $participantId, 'conception', [
        'calendrier_printemps' => (string) ($_POST['printemps'] ?? ''),
        'calendrier_ete'       => (string) ($_POST['ete'] ?? ''),
        'calendrier_automne'   => (string) ($_POST['automne'] ?? ''),
        'calendrier_hiver'     => (string) ($_POST['hiver'] ?? ''),
    ]);
    header('Location: ' . Configuration::base() . 'realisation-preparation-parcelle.php');
    exit;
}
$reponses = $reponsesService->pourParticipant($participantId);
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Calendrier — Syntropia</title>
<link rel="stylesheet" href="assets/css/gabarit.css">
<style>
  /* propre à cette étape : pas de bandeau d'outils (la saisie est le
     seul geste de la page) — recaler la tirette/panneau Informations
     sur un seul bandeau. Quatre saisons en grille 2×2 : ça tient dans
     l'écran sans défilement, et ça se lit comme un calendrier. */
  :root{ --haut-bandeaux: 3.4rem; }

  .calendrier-cadre{ width:100%; max-width:32rem; }
  .calendrier-annonce{ font:400 .92rem/1.5 var(--texte); color:var(--muted); margin:0 0 1.4rem; text-align:center; }
  .calendrier-grille{ display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
  .calendrier-champ label{ display:block; font:600 .85rem var(--titre); color:var(--ink); margin-bottom:.4rem; }
  .calendrier-champ textarea{
    width:100%; height:5.4rem; padding:.6rem .7rem; font:400 .88rem/1.4 var(--texte); color:var(--ink);
    background:var(--surface); border:1px solid var(--line); border-radius:.5rem; resize:none;
  }
  .calendrier-champ textarea:focus{ outline:2px solid var(--accent); outline-offset:1px; }
  .calendrier-bouton{
    width:100%; margin-top:1.1rem; padding:.65rem; border:0; border-radius:.6rem;
    background:var(--accent); color:var(--bouton-encre); font:600 .95rem var(--titre); cursor:pointer;
  }
  .calendrier-bouton:hover{ opacity:.92; }
  .calendrier-note{ font:400 .78rem var(--texte); color:var(--muted); text-align:center; margin-top:.6rem; }
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
          <a class="compte-menu-item a-venir" href="compte.php" tabindex="-1" aria-disabled="true"><span class="compte-menu-puce">⚙</span>Préférences</a>
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
        <p class="pg-pied">Dernière étape construite de Conception — voir plan-du-site.md.</p>
      </div>
    </nav>

    <main class="bureau" id="bureau">
      <div class="calendrier-cadre">
        <p class="calendrier-annonce">Saison par saison, ce qu'il y aura à faire. Une idée
           suffit pour commencer — vous préciserez en avançant.</p>

        <form method="post">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($auth->jeton(), ENT_QUOTES, 'UTF-8') ?>">
          <div class="calendrier-grille">
            <div class="calendrier-champ">
              <label for="printemps">Printemps</label>
              <textarea id="printemps" name="printemps" placeholder="Plantations, semis…"><?= htmlspecialchars($reponses['calendrier_printemps'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>
            <div class="calendrier-champ">
              <label for="ete">Été</label>
              <textarea id="ete" name="ete" placeholder="Arrosage, observation…"><?= htmlspecialchars($reponses['calendrier_ete'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>
            <div class="calendrier-champ">
              <label for="automne">Automne</label>
              <textarea id="automne" name="automne" placeholder="Récolte, couverture du sol…"><?= htmlspecialchars($reponses['calendrier_automne'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>
            <div class="calendrier-champ">
              <label for="hiver">Hiver</label>
              <textarea id="hiver" name="hiver" placeholder="Taille, préparation…"><?= htmlspecialchars($reponses['calendrier_hiver'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>
          </div>

          <button type="submit" class="calendrier-bouton">Continuer</button>
          <p class="calendrier-note">Enregistré dès que vous continuez — vous pouvez revenir sans rien perdre.</p>
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
      <p>Un premier calendrier, à main levée — pas encore calculé à partir
         d'un scénario de plantation réel (cette pièce du parcours n'est
         pas encore construite). Il donne un repère de rythme : ce qui se
         passe à chaque saison, pour ne rien découvrir au dernier moment.</p>
    </div>
  </aside>

  <footer class="pied">
    <span class="pied-mention">Syntropia — en construction</span>
    <span class="pied-spacer"></span>
  </footer>

</div>

<script src="assets/js/etapes.js"></script>
<script src="assets/js/gabarit.js"></script>
<script>
  Gabarit.init({ etape: 'conception-calendrier' });
</script>
</body>
</html>
