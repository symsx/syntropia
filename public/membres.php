<?php

declare(strict_types=1);

/**
 * Participants — qui a accès au projet, et le retrait d'un accès.
 *
 * Réservé au porteur du projet actif. Retirer l'accès de quelqu'un ne
 * touche que `membre_site` : la personne et ses réponses restent dans le
 * projet (voir `Sites::retirerMembre()`, où vivent les garde-fous). Page
 * hors parcours (`etapes.js`), comme `inviter.php`.
 */

require __DIR__ . '/../src/autoload.php';
require __DIR__ . '/../src/Configuration.php';
require __DIR__ . '/../src/Database.php';

use Syntropia\Configuration;
use Syntropia\Role;
use Syntropia\Web\Authentification;
use Syntropia\Web\Comptes;
use Syntropia\Web\Sites;

Authentification::demarrer($_SERVER);
$auth = new Authentification();
$auth->exige(Role::Utilisateur);

$compteId = (int) $auth->compteId();
$sites = new Sites();
$site = $sites->pourCompte($compteId);
if ($site === null) {
    header('Location: ' . Configuration::base() . 'definition-projet.php');
    exit;
}
$siteId = (int) $site['id'];
$estPorteur = ($site['role'] ?? '') === 'porteur';

const PAR_PAGE = 4;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

if ($estPorteur && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$auth->jetonValide($_POST['csrf'] ?? null)) {
        $_SESSION['syntropia_flash_membres'] = ['erreur', 'Le formulaire a expiré. Rechargez la page.'];
    } elseif (($_POST['action'] ?? '') === 'retirer') {
        $cible = (int) ($_POST['compte_id'] ?? 0);
        $nom = (new Comptes())->nomsParIds([$cible])[$cible]['nom_affiche'] ?? 'Cette personne';
        $refus = $sites->retirerMembre($siteId, $cible, $compteId);
        $_SESSION['syntropia_flash_membres'] = $refus === null
            ? ['ok', $nom . " n'a plus accès au projet."] : ['erreur', $refus];
    }
    header('Location: ' . Configuration::base() . 'membres.php');
    exit;
}

$flash = $_SESSION['syntropia_flash_membres'] ?? null;
unset($_SESSION['syntropia_flash_membres']);

$membres = $estPorteur ? $sites->membres($siteId) : [];
$noms = (new Comptes())->nomsParIds(array_column($membres, 'compte_id'));
$pages = max(1, (int) ceil(count($membres) / PAR_PAGE));
$page = max(1, min($pages, (int) ($_GET['page'] ?? 1)));
$visibles = array_slice($membres, ($page - 1) * PAR_PAGE, PAR_PAGE);

$libelleRole = ['porteur' => 'porteur', 'contributeur' => 'contribue', 'lecteur' => 'lit seulement'];
$depuis = static fn (string $d): string => date('d/m/Y', (int) strtotime($d));
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Participants — Syntropia</title>
<link rel="stylesheet" href="assets/css/gabarit.css?v=<?= (int) @filemtime(__DIR__ . '/assets/css/gabarit.css') ?>">
<style>
  :root{ --haut-bandeaux: 3.4rem; }

  .mb-centre{ flex:1; display:flex; align-items:center; justify-content:center; padding:1rem 1.5rem; overflow:hidden; }
  .mb-cadre{ width:100%; max-width:27rem; }
  .mb-cadre h1{ font:600 1.25rem var(--titre); color:var(--accent-titre); margin:0 0 .3rem; text-align:center; }
  .mb-chapeau{ font:400 .84rem/1.4 var(--texte); color:var(--muted); text-align:center; margin:0 0 .8rem; }
  .mb-message{
    border-radius:.5rem; padding:.5rem .75rem; font-size:.84rem; margin-bottom:.7rem;
    background:var(--accent-fond); border:1px solid var(--accent); color:var(--accent-titre);
  }
  .mb-message--erreur{ border-color:var(--alerte); color:var(--alerte); }
  .mb-liste{ list-style:none; margin:0; padding:0; }
  .mb-liste li{
    display:flex; align-items:center; gap:.6rem; padding:.5rem 0; border-top:1px solid var(--line);
    font:400 .86rem/1.25 var(--texte); color:var(--ink);
  }
  .mb-liste li:last-child{ border-bottom:1px solid var(--line); }
  .mb-avatar{
    flex:none; width:2rem; height:2rem; border-radius:50%; background:var(--accent); color:var(--bouton-encre);
    display:flex; align-items:center; justify-content:center; font:600 .75rem var(--titre);
  }
  .mb-qui{ flex:1; min-width:0; }
  .mb-qui strong{ display:block; font:600 .88rem var(--titre); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .mb-qui small{ display:block; color:var(--muted); font-size:.74rem; }
  .mb-retirer{
    padding:.25rem .55rem; border:1px solid var(--line); border-radius:.4rem; background:transparent;
    color:var(--muted); font:500 .74rem var(--titre); cursor:pointer; white-space:nowrap;
  }
  .mb-retirer:hover{ border-color:var(--alerte); color:var(--alerte); }
  .mb-etat{ font:500 .72rem var(--titre); color:var(--muted); }
  .mb-pages{ display:flex; justify-content:center; align-items:center; gap:.8rem; margin-top:.5rem; font:400 .78rem var(--texte); color:var(--muted); }
  .mb-pages a{ color:var(--accent-titre); text-decoration:none; font-weight:600; }
  .mb-vide{ font:400 .85rem/1.4 var(--texte); color:var(--muted); text-align:center; }
  .mb-lien{ text-align:center; margin-top:.7rem; font:400 .82rem var(--texte); }
  .mb-lien a{ color:var(--accent-titre); font-weight:600; text-decoration:none; }

  @media (max-width:30rem){
    .mb-centre{ padding:.5rem 1rem; align-items:flex-start; }
    .mb-chapeau{ display:none; }
    .mb-liste li{ padding:.35rem 0; }
  }
  @media (max-height:38rem){ .mb-chapeau{ display:none; } }
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
      <span class="bg-centre-titre">Participants</span>
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
        <p class="pg-pied">Qui a accès au projet, et depuis quand.</p>
      </div>
    </nav>

    <main class="bureau" id="bureau">
      <div class="mb-centre">
        <div class="mb-cadre">
          <h1>Participants</h1>
<?php if (!$estPorteur): ?>
          <p class="mb-vide">La liste des personnes qui ont accès à « <?= $h($site['nom']) ?> » se
             consulte auprès de son porteur.</p>
<?php else: ?>
          <p class="mb-chapeau">Les personnes qui ont accès à « <?= $h($site['nom']) ?> ». Retirer un
             accès ferme le projet à cette personne, et ses réponses y restent.</p>
<?php if ($flash !== null): ?>
          <p class="mb-message<?= $flash[0] === 'erreur' ? ' mb-message--erreur' : '' ?>"><?= $h($flash[1]) ?></p>
<?php endif; ?>
          <ul class="mb-liste">
<?php foreach ($visibles as $m):
    $nom = $noms[$m['compte_id']]['nom_affiche'] ?? 'Compte supprimé';
    $identifiant = $noms[$m['compte_id']]['identifiant'] ?? '';
    $initiales = mb_strtoupper(mb_substr($nom, 0, 2));
    $moi = $m['compte_id'] === $compteId; ?>
            <li>
              <span class="mb-avatar" aria-hidden="true"><?= $h($initiales) ?></span>
              <span class="mb-qui">
                <strong><?= $h($nom) ?><?= $moi ? ' (vous)' : '' ?></strong>
                <small><?= $h($libelleRole[$m['role']] ?? $m['role']) ?> · depuis le <?= $h($depuis($m['depuis_le'])) ?><?= $identifiant !== '' ? ' · ' . $h($identifiant) : '' ?></small>
              </span>
<?php if ($m['role'] !== 'porteur'): ?>
              <form method="post" data-confirmer="Retirer l'accès de <?= $h($nom) ?> au projet « <?= $h($site['nom']) ?> » ? Ses réponses restent dans le projet, mais cette personne ne pourra plus l'ouvrir.">
                <input type="hidden" name="csrf" value="<?= $h($auth->jeton()) ?>">
                <input type="hidden" name="action" value="retirer">
                <input type="hidden" name="compte_id" value="<?= (int) $m['compte_id'] ?>">
                <button type="submit" class="mb-retirer">Retirer l'accès</button>
              </form>
<?php else: ?>
              <span class="mb-etat">Porteur</span>
<?php endif; ?>
            </li>
<?php endforeach; ?>
          </ul>
<?php if ($pages > 1): ?>
          <p class="mb-pages">
            <?= $page > 1 ? '<a href="membres.php?page=' . ($page - 1) . '">‹ Précédents</a>' : '<span></span>' ?>
            <span><?= $page ?> / <?= $pages ?></span>
            <?= $page < $pages ? '<a href="membres.php?page=' . ($page + 1) . '">Suivants ›</a>' : '<span></span>' ?>
          </p>
<?php endif; ?>
<?php if (count($membres) <= 1): ?>
          <p class="mb-vide" style="margin-top:.6rem">Aucune autre personne pour l'instant.</p>
<?php endif; ?>
          <p class="mb-lien"><a href="inviter.php">Inviter quelqu'un →</a></p>
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
      <p>Cette page montre les personnes qui ont accès au projet actif : le porteur (vous), les
         contributeurs et les lecteurs. Seul le porteur voit cette liste.</p>
      <p><strong>Retirer l'accès</strong> ferme le projet à cette personne : il disparaît de ses
         projets. Ses réponses aux étapes restent dans le projet, et elle les retrouve si vous
         l'invitez de nouveau. Le porteur garde toujours son accès.</p>
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

  // Retirer un accès se confirme : la personne perd la vue du projet.
  document.querySelectorAll('form[data-confirmer]').forEach(function(f){
    f.addEventListener('submit', function(e){
      if (!window.confirm(f.dataset.confirmer)) e.preventDefault();
    });
  });
</script>
</body>
</html>
