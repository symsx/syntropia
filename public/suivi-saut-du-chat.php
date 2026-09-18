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
    $reponsesService->enregistrerLot($siteId, $participantId, 'suivi', [
        'saut_du_chat_visible'      => (string) ($_POST['visible'] ?? ''),
        'saut_du_chat_observations' => (string) ($_POST['observations'] ?? ''),
    ]);
    header('Location: ' . Configuration::base() . 'suivi-consignation.php');
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
<title>Saut du chat — Syntropia</title>
<link rel="stylesheet" href="assets/css/gabarit.css">
<style>
  :root{ --haut-bandeaux: 3.4rem; }

  .saut-cadre{ width:100%; max-width:30rem; }
  .saut-annonce{ font:400 .92rem/1.5 var(--texte); color:var(--muted); margin:0 0 1.6rem; text-align:center; }
  .saut-champ{ margin-bottom:1.1rem; border:0; padding:0; }
  .saut-champ label, .saut-champ legend{
    display:block; font:600 .85rem var(--titre); color:var(--ink); margin-bottom:.4rem; padding:0;
  }
  .saut-choix{ display:flex; gap:1rem; flex-wrap:wrap; }
  .saut-choix label{ display:flex; align-items:center; gap:.4rem; font:400 .9rem var(--texte); color:var(--ink); }
  .saut-champ textarea{
    width:100%; padding:.6rem .7rem; font:400 .9rem/1.4 var(--texte); color:var(--ink);
    background:var(--surface); border:1px solid var(--line); border-radius:.5rem; resize:none;
  }
  .saut-champ textarea:focus{ outline:2px solid var(--accent); outline-offset:1px; }

  /* la suggestion — visible seulement quand « Pas encore » est coché,
     sans JavaScript : :has() suffit. Même principe que la suggestion
     RNA de definition-moyens.php. */
  .saut-suggestion{
    display:none; margin:.55rem 0 0; padding:.55rem .7rem; background:var(--accent-fond);
    border-radius:.5rem; font:400 .8rem/1.4 var(--texte); color:var(--ink);
  }
  .saut-champ:has(#visible-non:checked) .saut-suggestion{ display:block; }

  .saut-bouton{
    width:100%; margin-top:.3rem; padding:.65rem; border:0; border-radius:.6rem;
    background:var(--accent); color:var(--bouton-encre); font:600 .95rem var(--titre); cursor:pointer;
  }
  .saut-bouton:hover{ opacity:.92; }
  .saut-note{ font:400 .78rem var(--texte); color:var(--muted); text-align:center; margin-top:.6rem; }
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
        <p class="pg-pied">Troisième étape de Suivi — voir plan-du-site.md.</p>
      </div>
    </nav>

    <main class="bureau" id="bureau">
      <div class="saut-cadre">
        <p class="saut-annonce">Le moment où le système bascule visiblement — plus de
           croissance, plus de diversité, d'un coup.</p>

        <form method="post">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($auth->jeton(), ENT_QUOTES, 'UTF-8') ?>">
          <fieldset class="saut-champ">
            <legend>Le <?= Documentation::terme($termes, 'saut-du-chat', 'saut du chat') ?> est-il visible ?</legend>
            <div class="saut-choix">
              <label><input type="radio" class="radio" name="visible" value="oui"<?= ($reponses['saut_du_chat_visible'] ?? '') === 'oui' ? ' checked' : '' ?>>Oui</label>
              <label><input type="radio" class="radio" id="visible-non" name="visible" value="non"<?= ($reponses['saut_du_chat_visible'] ?? 'non') === 'non' ? ' checked' : '' ?>>Pas encore</label>
            </div>
            <p class="saut-suggestion">Rien d'anormal à attendre — le saut du chat vient avec le
               temps. En attendant, planter davantage peut aider, selon la
               période : plus de densité au départ accélère la couverture
               et la compétition qui déclenchent la bascule.</p>
          </fieldset>

          <div class="saut-champ">
            <label for="observations">Observations</label>
            <textarea id="observations" name="observations" rows="3" placeholder="Ce que vous voyez : croissance, couleur, densité du feuillage…"><?= htmlspecialchars($reponses['saut_du_chat_observations'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
          </div>

          <button type="submit" class="saut-bouton">Continuer</button>
          <p class="saut-note">Enregistré dès que vous continuez — vous pouvez revenir sans rien perdre.</p>
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
      <p>« Saut du chat » nomme le moment où le système, jusque-là discret,
         accélère nettement — signe que la conduite du terrain a pris.
         Il n'arrive pas à date fixe : cette étape sert à le repérer
         quand il se produit, pas à le forcer.</p>
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
  Gabarit.init({ etape: 'suivi-saut-du-chat' });
</script>
</body>
</html>
