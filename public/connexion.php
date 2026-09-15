<?php

declare(strict_types=1);

require __DIR__ . '/../src/autoload.php';
require __DIR__ . '/../src/Configuration.php';
require __DIR__ . '/../src/Database.php';

use Syntropia\Configuration;
use Syntropia\Web\Authentification;

Authentification::demarrer($_SERVER);
$auth = new Authentification();

if ($auth->estConnecte()) {
    header('Location: ' . Configuration::base() . 'conception-parcelles.php');
    exit;
}

$erreur = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$auth->jetonValide($_POST['csrf'] ?? null)) {
        $erreur = 'Jeton de sécurité invalide — réessayez.';
    } else {
        $erreur = $auth->connecter(
            trim((string) ($_POST['identifiant'] ?? '')),
            (string) ($_POST['mot_de_passe'] ?? ''),
            Authentification::adresse($_SERVER)
        );
        if ($erreur === null) {
            header('Location: ' . Configuration::base() . 'conception-parcelles.php');
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
<title>Connexion — Syntropia</title>
<link rel="stylesheet" href="assets/css/gabarit.css">
<style>
  /* propre à cette page : le formulaire de connexion, dans le bureau */
  .connexion-centre{ flex:1; display:flex; align-items:center; justify-content:center; padding:2rem 1.5rem; }
  .connexion-cadre{ width:100%; max-width:22rem; }
  .connexion-champ{ margin-bottom:1rem; }
  .connexion-champ label{ display:block; font:500 .85rem var(--titre); color:var(--ink); margin-bottom:.3rem; }
  .connexion-champ input{
    width:100%; padding:.55rem .7rem; font:400 .95rem var(--texte); color:var(--ink);
    background:var(--surface); border:1px solid var(--line); border-radius:.5rem;
  }
  .connexion-champ input:focus{ outline:2px solid var(--accent); outline-offset:1px; }
  .connexion-bouton{
    width:100%; margin-top:.4rem; padding:.65rem; border:0; border-radius:.6rem;
    background:var(--accent); color:var(--bouton-encre); font:600 .95rem var(--titre); cursor:pointer;
  }
  .connexion-bouton:hover{ opacity:.92; }
  .connexion-erreur{
    background:var(--accent-fond); border:1px solid var(--alerte); color:var(--alerte);
    border-radius:.5rem; padding:.6rem .8rem; font-size:.88rem; margin-bottom:1rem;
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
      <span class="bg-centre-titre">Connexion</span>
    </div>
    <div class="bg-droite">
      <button class="theme-bascule" id="theme-bascule" aria-label="Changer de thème" title="Thème">
        <span class="theme-icone" id="theme-icone" aria-hidden="true">◐</span>
      </button>
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
            <li><a href="connexion.php" class="active" aria-current="page"><span class="pg-puce">→</span><span class="pg-libelle">Se connecter</span></a></li>
            <li><a href="inscription.php"><span class="pg-puce">+</span><span class="pg-libelle">S'inscrire</span></a></li>
          </ul>
        </div>
      </div>
    </nav>

    <main class="bureau" id="bureau">
      <div class="connexion-centre">
        <div class="connexion-cadre">
          <?php if ($erreur !== null): ?>
            <p class="connexion-erreur"><?= htmlspecialchars($erreur, ENT_QUOTES, 'UTF-8') ?></p>
          <?php endif; ?>
          <form method="post">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($auth->jeton(), ENT_QUOTES, 'UTF-8') ?>">
            <div class="connexion-champ">
              <label for="identifiant">Identifiant</label>
              <input type="text" id="identifiant" name="identifiant" required autofocus autocomplete="username">
            </div>
            <div class="connexion-champ">
              <label for="mot_de_passe">Mot de passe</label>
              <input type="password" id="mot_de_passe" name="mot_de_passe" required autocomplete="current-password">
            </div>
            <button type="submit" class="connexion-bouton">Se connecter</button>
          </form>
        </div>
      </div>
    </main>
  </div>

  <footer class="pied">
    <span class="pied-mention">Syntropia — en construction</span>
    <span class="pied-spacer"></span>
  </footer>

</div>

<script src="assets/js/etapes.js"></script>
<script src="assets/js/gabarit.js"></script>
<script>
  Gabarit.init({});
</script>
</body>
</html>
