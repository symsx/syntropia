<?php

declare(strict_types=1);

require __DIR__ . '/../src/autoload.php';
require __DIR__ . '/../src/Configuration.php';
require __DIR__ . '/../src/Database.php';

use Syntropia\Web\Authentification;

Authentification::reprendre($_SERVER);
$auth = new Authentification();
$connecte = $auth->estConnecte();
$nomAffiche = $connecte ? ($auth->nomAffiche() ?? '') : '';
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Syntropia</title>
<link rel="stylesheet" href="assets/css/gabarit.css?v=<?= (int) @filemtime(__DIR__ . '/assets/css/gabarit.css') ?>">
<style>
  /* propre à cette page. Pas de bandeau d'outils (rien à choisir) : la
     tirette et le panneau d'informations, calés par défaut sur la
     hauteur de deux bandeaux, se recalent sur un seul. Le bureau reste
     centré et fixe — RÈGLE DU BUREAU : jamais de défilement, ici pas
     plus qu'ailleurs. Le contenu tient dans l'écran ou se réduit,
     jamais l'inverse. */
  :root{ --haut-bandeaux: 3.4rem; }

  .accueil-corps{ text-align:center; }
  .accueil-marque{ font:600 3rem var(--titre); color:var(--accent-titre); margin:0; line-height:1; }
  .accueil-sous-titre{ font:500 1.05rem/1.35 var(--texte); color:var(--muted); margin:.7rem 0 1.8rem; }

  .accueil-cartes{
    display:flex; gap:1rem; justify-content:center; flex-wrap:wrap;
  }
  .accueil-carte{
    display:flex; flex-direction:column; align-items:center; text-align:center;
    width:11rem; padding:1.4rem 1rem; background:var(--surface); border:1px solid var(--line);
    border-radius:.8rem; box-shadow:0 4px 12px var(--ombre); text-decoration:none;
  }
  .accueil-carte h2{ font:600 1rem var(--titre); color:var(--ink); margin:0 0 .3rem; }
  .accueil-carte p{ font-size:.82rem; color:var(--muted); margin:0; }
  .accueil-carte.a-venir{ opacity:.55; pointer-events:none; }
  .accueil-etiquette{
    display:inline-block; font:500 .62rem var(--titre); text-transform:uppercase;
    letter-spacing:.05em; color:var(--muted); border:1px solid var(--line); border-radius:1rem;
    padding:.1rem .5rem; margin-bottom:.6rem;
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
      <span class="bg-centre-titre">Accueil</span>
    </div>
    <div class="bg-droite">
      <button class="theme-bascule" id="theme-bascule" aria-label="Changer de thème" title="Thème">
        <span class="theme-icone" id="theme-icone" aria-hidden="true">◐</span>
      </button>
      <?php if ($connecte): ?>
        <div class="compte-conteneur">
          <button class="compte" id="compte-bouton" type="button" aria-haspopup="true" aria-expanded="false" aria-controls="compte-menu">
            <span class="compte-avatar"><?= htmlspecialchars(mb_strtoupper(mb_substr($nomAffiche, 0, 2)), ENT_QUOTES, 'UTF-8') ?></span>
            <span class="compte-nom"><?= htmlspecialchars($nomAffiche, ENT_QUOTES, 'UTF-8') ?></span>
          </button>
          <div class="compte-menu" id="compte-menu">
            <a class="compte-menu-item" href="conception-parcelles.php"><span class="compte-menu-puce">▤</span>Mon projet</a>
            <a class="compte-menu-item" href="compte.php"><span class="compte-menu-puce">⚙</span>Préférences</a>
            <div class="compte-menu-separateur"></div>
            <a class="compte-menu-item" href="deconnexion.php"><span class="compte-menu-puce">⏻</span>Se déconnecter</a>
          </div>
        </div>
      <?php else: ?>
        <a class="compte" href="connexion.php">
          <span class="compte-avatar">?</span>
          <span class="compte-nom">Se connecter</span>
        </a>
      <?php endif; ?>
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
            <li><a href="./" class="active" aria-current="page"><span class="pg-puce">⌂</span><span class="pg-libelle">Accueil</span></a></li>
            <?php if ($connecte): ?>
              <li><a href="conception-parcelles.php"><span class="pg-puce">▤</span><span class="pg-libelle">Mon projet</span></a></li>
              <li><a href="deconnexion.php"><span class="pg-puce">⏻</span><span class="pg-libelle">Se déconnecter</span></a></li>
            <?php else: ?>
              <li><a href="connexion.php"><span class="pg-puce">→</span><span class="pg-libelle">Se connecter</span></a></li>
              <li><a href="inscription.php"><span class="pg-puce">+</span><span class="pg-libelle">S'inscrire</span></a></li>
            <?php endif; ?>
          </ul>
        </div>
      </div>
    </nav>

    <main class="bureau" id="bureau">
      <div class="accueil-corps">
        <h1 class="accueil-marque">Syntropia</h1>
        <p class="accueil-sous-titre">Atelier<br>d'agroforesterie<br>syntropique</p>

        <div class="accueil-cartes">
          <a class="accueil-carte" href="<?= $connecte ? 'conception-parcelles.php' : 'connexion.php' ?>">
            <h2><?= $connecte ? 'Mon projet' : 'Se connecter' ?></h2>
            <p><?= $connecte ? 'Continuer' : 'Reprendre ou ouvrir un projet' ?></p>
          </a>
          <a class="accueil-carte" href="inscription.php">
            <h2>S'inscrire</h2>
            <p>Ouvrir un compte</p>
          </a>
          <a class="accueil-carte" href="documentation-site.php">
            <h2>Documentation</h2>
            <p>Comprendre l'atelier</p>
          </a>
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
      <p><strong>L'atelier.</strong> Syntropia fournit un accompagnement et des outils
         pour concevoir, créer et assurer le suivi de cultures en agroforesterie
         syntropique. Chaque étape demande des informations précises et explique à quoi
         elles servent. L'atelier conseille, il n'interdit rien.</p>
      <p><strong>La syntropie.</strong> La succession végétale naturelle conduit un lieu
         vers plus d'abondance. L'agroforesterie syntropique la reproduit en accéléré.
         Vous vous y incluez en tant que guide et perturbateur·trice positif·ve. Vous
         faites avec le vivant, vous observez et vous apprenez.</p>
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
