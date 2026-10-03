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
    $reponsesService->enregistrerLot($siteId, $participantId, 'definition', [
        'moyens_connaissances' => (string) ($_POST['connaissances'] ?? ''),
        'moyens_temps'         => (string) ($_POST['temps'] ?? ''),
        'moyens_financier'     => (string) ($_POST['financier'] ?? ''),
        'moyens_terrain'       => (string) ($_POST['terrain'] ?? ''),
    ]);
    header('Location: ' . Configuration::base() . 'definition-site.php');
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
<title>Moyens — Syntropia</title>
<link rel="stylesheet" href="assets/css/gabarit.css?v=<?= (int) @filemtime(__DIR__ . '/assets/css/gabarit.css') ?>">
<style>
  /* propre à cette étape : pas de bandeau d'outils (la saisie est le
     seul geste de la page) — recaler la tirette/panneau Informations
     sur un seul bandeau. Aucun défilement : le formulaire tient dans
     l'écran. */
  :root{ --haut-bandeaux: 3.4rem; }

  .moyens-cadre{ width:100%; max-width:30rem; }
  .moyens-annonce{ font:400 .92rem/1.5 var(--texte); color:var(--muted); margin:0 0 1.4rem; text-align:center; }
  .moyens-champ{ margin-bottom:1rem; border:0; padding:0; }
  .moyens-champ label, .moyens-champ legend{
    display:block; font:600 .85rem var(--titre); color:var(--ink); margin-bottom:.35rem; padding:0;
  }
  .moyens-champ textarea{
    width:100%; padding:.5rem .7rem; font:400 .88rem/1.35 var(--texte); color:var(--ink);
    background:var(--surface); border:1px solid var(--line); border-radius:.5rem; resize:none;
  }
  .moyens-champ textarea:focus{ outline:2px solid var(--accent); outline-offset:1px; }
  .moyens-choix{ display:flex; gap:1rem; flex-wrap:wrap; }
  .moyens-choix label{ display:flex; align-items:center; gap:.4rem; font:400 .88rem var(--texte); color:var(--ink); }

  /* la suggestion RNA — visible seulement quand « Faibles » est coché,
     sans JavaScript : :has() suffit. Offrir une possibilité plutôt
     qu'une restriction, au moment précis où elle sert. */
  .moyens-suggestion{
    display:none; margin:.55rem 0 0; padding:.55rem .7rem; background:var(--accent-fond);
    border-radius:.5rem; font:400 .8rem/1.4 var(--texte); color:var(--ink);
  }
  .moyens-champ:has(#financier-faible:checked) .moyens-suggestion{ display:block; }

  .moyens-bouton{
    width:100%; margin-top:.3rem; padding:.65rem; border:0; border-radius:.6rem;
    background:var(--accent); color:var(--bouton-encre); font:600 .95rem var(--titre); cursor:pointer;
  }
  .moyens-bouton:hover{ opacity:.92; }
  .moyens-note{ font:400 .78rem var(--texte); color:var(--muted); text-align:center; margin-top:.6rem; }
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
          <ul class="pg-liste pg-etapes-phase"></ul>
        </div>
      </div>
    </nav>

    <main class="bureau" id="bureau">
      <div class="moyens-cadre">
        <p class="moyens-annonce">Quatre questions, environ deux minutes. Vos réponses
           situent le point de départ du projet.</p>

        <form method="post">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($auth->jeton(), ENT_QUOTES, 'UTF-8') ?>">
          <div class="moyens-champ">
            <label for="connaissances">Ce que vous savez déjà faire</label>
            <textarea id="connaissances" name="connaissances" rows="1" placeholder="Jardinage, permaculture, rien encore…"><?= htmlspecialchars($reponses['moyens_connaissances'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
          </div>

          <div class="moyens-champ">
            <label for="temps">Le temps que vous pouvez y consacrer</label>
            <textarea id="temps" name="temps" rows="1" placeholder="Quelques heures par semaine, un week-end par mois…"><?= htmlspecialchars($reponses['moyens_temps'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
          </div>

          <fieldset class="moyens-champ">
            <legend>Vos moyens financiers</legend>
            <div class="moyens-choix">
              <label><input type="radio" class="radio" id="financier-faible" name="financier" value="faible"<?= ($reponses['moyens_financier'] ?? 'modere') === 'faible' ? ' checked' : '' ?>>Faibles</label>
              <label><input type="radio" class="radio" name="financier" value="modere"<?= ($reponses['moyens_financier'] ?? 'modere') === 'modere' ? ' checked' : '' ?>>Modérés</label>
              <label><input type="radio" class="radio" name="financier" value="confortable"<?= ($reponses['moyens_financier'] ?? '') === 'confortable' ? ' checked' : '' ?>>Confortables</label>
            </div>
            <p class="moyens-suggestion">Avec peu de moyens, la
               <?= Documentation::terme($termes, 'rna', 'régénération naturelle assistée') ?>
               permet de commencer. Elle consiste à sélectionner et protéger ce qui repousse
               déjà, et à semer directement plutôt que transplanter. Le semis direct coûte en
               moyenne trois fois moins cher que la transplantation.</p>
          </fieldset>

          <fieldset class="moyens-champ">
            <legend>Le terrain</legend>
            <div class="moyens-choix">
              <label><input type="radio" class="radio" name="terrain" value="oui"<?= ($reponses['moyens_terrain'] ?? 'oui') === 'oui' ? ' checked' : '' ?>>Je l'ai déjà</label>
              <label><input type="radio" class="radio" name="terrain" value="non"<?= ($reponses['moyens_terrain'] ?? '') === 'non' ? ' checked' : '' ?>>Je le cherche encore</label>
            </div>
          </fieldset>

          <button type="submit" class="moyens-bouton">Continuer</button>
          <p class="moyens-note">Vos réponses sont enregistrées quand vous continuez. Vous pourrez les modifier plus tard.</p>
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
      <p>Les moyens situent votre point de départ. L'atelier s'en sert pour proposer
         des chemins adaptés. Avec peu de moyens financiers, il propose des voies moins
         coûteuses, comme la régénération naturelle assistée. Sans terrain, les étapes
         suivantes servent à préparer sa recherche.</p>
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
  Gabarit.init({ etape: 'definition-moyens' });
</script>
</body>
</html>
