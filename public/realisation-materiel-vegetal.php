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
        'materiel_multiplication' => (string) ($_POST['multiplication'] ?? ''),
        'materiel_achat'          => (string) ($_POST['achat'] ?? ''),
    ]);
    header('Location: ' . Configuration::base() . 'realisation-outils-et-plans.php');
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
<title>Matériel végétal — Syntropia</title>
<link rel="stylesheet" href="assets/css/gabarit.css?v=<?= (int) @filemtime(__DIR__ . '/assets/css/gabarit.css') ?>">
<style>
  :root{ --haut-bandeaux: 3.4rem; }

  .mveg-cadre{ width:100%; max-width:30rem; }
  .mveg-annonce{ font:400 .92rem/1.5 var(--texte); color:var(--muted); margin:0 0 1.6rem; text-align:center; }
  .mveg-champ{ margin-bottom:1.1rem; }
  .mveg-champ label{ display:block; font:600 .85rem var(--titre); color:var(--ink); margin-bottom:.4rem; }
  .mveg-champ small{ display:block; font:400 .78rem/1.35 var(--texte); color:var(--muted); margin-bottom:.3rem; }
  .mveg-champ textarea{
    width:100%; padding:.6rem .7rem; font:400 .9rem/1.4 var(--texte); color:var(--ink);
    background:var(--surface); border:1px solid var(--line); border-radius:.5rem; resize:none;
  }
  .mveg-champ textarea:focus{ outline:2px solid var(--accent); outline-offset:1px; }
  .mveg-bouton{
    width:100%; margin-top:.3rem; padding:.65rem; border:0; border-radius:.6rem;
    background:var(--accent); color:var(--bouton-encre); font:600 .95rem var(--titre); cursor:pointer;
  }
  .mveg-bouton:hover{ opacity:.92; }
  .mveg-note{ font:400 .78rem var(--texte); color:var(--muted); text-align:center; margin-top:.6rem; }
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
        <p class="pg-pied">Deuxième étape de Réalisation — voir plan-du-site.md.</p>
      </div>
    </nav>

    <main class="bureau" id="bureau">
      <div class="mveg-cadre">
        <p class="mveg-annonce">Ce qui va être planté vient de quelque part : de vous, ou
           d'ailleurs.</p>

        <form method="post">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($auth->jeton(), ENT_QUOTES, 'UTF-8') ?>">
          <div class="mveg-champ">
            <label for="multiplication">Ce que vous multipliez vous-même</label>
            <small><?= Documentation::terme($termes, 'bouturage', 'Bouturage') ?>,
               <?= Documentation::terme($termes, 'semis', 'semis') ?>,
               <?= Documentation::terme($termes, 'division', 'division') ?> — ce qui part de ce
               que vous avez déjà : des <?= Documentation::terme($termes, 'pieds-meres', 'pieds-mères') ?>
               à bouturer, des graines à récolter, sur votre terrain ou ailleurs.</small>
            <textarea id="multiplication" name="multiplication" rows="3" placeholder="Quoi, comment, à partir de quels pieds…"><?= htmlspecialchars($reponses['materiel_multiplication'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
          </div>

          <div class="mveg-champ">
            <label for="achat">Ce que vous allez vous procurer</label>
            <small>Ce qui manque encore — un végétal déjà utile, présent sur le terrain, n'a
               rien à faire ici.</small>
            <textarea id="achat" name="achat" rows="3" placeholder="Quoi, auprès de qui, en quelle quantité approximative…"><?= htmlspecialchars($reponses['materiel_achat'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
          </div>

          <button type="submit" class="mveg-bouton">Continuer</button>
          <p class="mveg-note">Enregistré dès que vous continuez — vous pouvez revenir sans rien perdre.</p>
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
      <p><strong>Trois façons de multiplier</strong> — le bouturage prélève un
         fragment de tige ou de racine d'un pied déjà en place ; le semis
         part d'une graine ; la division sépare une touffe existante en
         plusieurs plants. Les trois coûtent surtout du temps, pas de
         l'argent — une voie utile quand les moyens financiers sont
         serrés (voir l'étape Moyens, en Définition).</p>
      <p>Un végétal déjà utile au projet, présent sur le terrain sans rien
         y avoir planté, ne se multiplie ni ne s'achète — il se note
         simplement, pour mémoire, avec ce qu'il apporte.</p>
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
  Gabarit.init({ etape: 'realisation-materiel-vegetal' });
</script>
</body>
</html>
