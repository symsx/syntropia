<?php

declare(strict_types=1);

/**
 * Ouvrir son projet — un site, avant la première question.
 *
 * Passage obligé avant toute étape de Définition : les réponses de
 * `definition-vision.php` et des suivantes appartiennent à un
 * participant, lui-même rattaché à un site (`sql/01-syntropia.sql`).
 * Sans site, rien n'a où s'enregistrer. Voir `src/Web/Sites.php` pour
 * ce que « un seul projet par utilisateur » simplifie ici.
 *
 * Un compte qui a déjà un site ne repasse jamais par cette page — les
 * étapes de Définition y redirigent directement s'il n'en a pas
 * encore, et cette page elle-même redirige la première étape s'il en a
 * déjà un.
 */

require __DIR__ . '/../src/autoload.php';
require __DIR__ . '/../src/Configuration.php';
require __DIR__ . '/../src/Database.php';

use Syntropia\Configuration;
use Syntropia\Role;
use Syntropia\Web\Authentification;
use Syntropia\Web\Sites;

Authentification::demarrer($_SERVER);
$auth = new Authentification();
$auth->exige(Role::Utilisateur);

$sites = new Sites();
$compteId = (int) $auth->compteId();

// « ?nouveau » ouvre un projet de plus, à côté de ceux qu'on a déjà — le
// sien ou ceux rejoints par invitation. Sans lui, un compte qui a déjà un
// projet n'a rien à faire ici.
$nouveau = isset($_GET['nouveau']);
$dejaUn = $sites->pourCompte($compteId) !== null;
if ($dejaUn && !$nouveau) {
    header('Location: ' . Configuration::base() . 'definition-vision.php');
    exit;
}

$erreur = null;
$nom = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$auth->jetonValide($_POST['csrf'] ?? null)) {
        $erreur = 'Jeton de sécurité invalide — réessayez.';
    } else {
        $nom = trim((string) ($_POST['nom'] ?? ''));
        if ($nom === '') {
            $erreur = 'Le nom du projet est nécessaire pour commencer.';
        } else {
            $id = $sites->creer($nom, $compteId, $auth->nomAffiche() ?? 'Porteur');
            $sites->activer($id, $compteId);
            header('Location: ' . Configuration::base() . 'definition-vision.php');
            exit;
        }
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Nouveau projet — Syntropia</title>
<link rel="stylesheet" href="assets/css/gabarit.css?v=<?= (int) @filemtime(__DIR__ . '/assets/css/gabarit.css') ?>">
<style>
  :root{ --haut-bandeaux: 3.4rem; }

  .projet-centre{ flex:1; display:flex; align-items:center; justify-content:center; padding:2rem 1.5rem; }
  .projet-cadre{ width:100%; max-width:24rem; text-align:center; }
  .projet-cadre h1{ font:600 1.3rem var(--titre); color:var(--accent-titre); margin:0 0 .6rem; }
  .projet-chapeau{ font:400 .9rem/1.5 var(--texte); color:var(--muted); margin:0 0 1.4rem; }
  .projet-champ{ margin-bottom:1rem; text-align:left; }
  .projet-champ label{ display:block; font:500 .85rem var(--titre); color:var(--ink); margin-bottom:.3rem; }
  .projet-champ input{
    width:100%; padding:.55rem .7rem; font:400 .95rem var(--texte); color:var(--ink);
    background:var(--surface); border:1px solid var(--line); border-radius:.5rem;
  }
  .projet-champ input:focus{ outline:2px solid var(--accent); outline-offset:1px; }
  .projet-bouton{
    width:100%; margin-top:.4rem; padding:.65rem; border:0; border-radius:.6rem;
    background:var(--accent); color:var(--bouton-encre); font:600 .95rem var(--titre); cursor:pointer;
  }
  .projet-bouton:hover{ opacity:.92; }
  .projet-erreur{
    background:var(--accent-fond); border:1px solid var(--alerte); color:var(--alerte);
    border-radius:.5rem; padding:.6rem .8rem; font-size:.88rem; margin-bottom:1rem; text-align:left;
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
      <span class="bg-centre-titre">Nouveau projet</span>
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
          <p class="pg-titre">Le site</p>
          <ul class="pg-liste">
            <li><a href="./"><span class="pg-puce">⌂</span><span class="pg-libelle">Accueil</span></a></li>
            <li><a href="conception-parcelles.php"><span class="pg-puce">▤</span><span class="pg-libelle">Mon projet</span></a></li>
            <li><a href="deconnexion.php"><span class="pg-puce">⏻</span><span class="pg-libelle">Se déconnecter</span></a></li>
          </ul>
        </div>
        <p class="pg-pied">Un site, avant la première question — voir plan-du-site.md.</p>
      </div>
    </nav>

    <main class="bureau" id="bureau">
      <div class="projet-centre">
        <div class="projet-cadre">
          <h1><?= $dejaUn ? 'Ouvrir un nouveau projet' : 'Ouvrir votre projet' ?></h1>
          <p class="projet-chapeau">Un nom suffit pour commencer — vous pourrez le
             préciser à l'étape « Site », dans quelques instants.<?= $dejaUn
                 ? ' Vos autres projets restent où ils sont : le sélecteur du panneau de gauche permet de passer de l\'un à l\'autre.'
                 : '' ?></p>
          <?php if ($erreur !== null): ?>
            <p class="projet-erreur"><?= htmlspecialchars($erreur, ENT_QUOTES, 'UTF-8') ?></p>
          <?php endif; ?>
          <form method="post">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($auth->jeton(), ENT_QUOTES, 'UTF-8') ?>">
            <div class="projet-champ">
              <label for="nom">Nom du projet</label>
              <input type="text" id="nom" name="nom" required autofocus
                     value="<?= htmlspecialchars($nom, ENT_QUOTES, 'UTF-8') ?>"
                     placeholder="Ex. Le jardin de la Combe">
            </div>
            <button type="submit" class="projet-bouton">Commencer</button>
          </form>
<?php if (!$dejaUn): ?>
          <p class="projet-chapeau" style="margin:.9rem 0 0;font-size:.82rem">Quelqu'un vous a donné un code ?
             <a href="rejoindre.php" style="color:var(--accent-titre);font-weight:600">Rejoindre son projet</a></p>
<?php endif; ?>
        </div>
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
      <p>Un projet, c'est un site, et autant de parcelles qu'il en faudra.
         Un compte peut en porter plusieurs, et rejoindre ceux d'autres
         personnes sur invitation. Le nom peut changer plus tard ; rien
         d'autre ne se décide ici. Vos réponses aux étapes suivantes
         s'enregistrent dès que vous les donnez, et vous les retrouverez
         en y revenant.</p>
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
</script>
</body>
</html>
