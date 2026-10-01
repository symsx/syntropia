<?php

declare(strict_types=1);

/**
 * Journal des connexions — qui se connecte à l'atelier, et quand. Demandé par le porteur le 2026-10-01.
 *
 * Réservé aux administrateurs de l'atelier (`acces.role = 'administrateur'`, `exige(Role::Administrateur)`) : un
 * compte ordinaire est renvoyé à son projet. Une ligne par connexion réussie, sans adresse IP, gardée 90 jours
 * (`Comptes::noterConnexion()`). Page hors parcours, dans le menu du compte (gabarit.js) pour un administrateur.
 */

require __DIR__ . '/../src/autoload.php';
require __DIR__ . '/../src/Configuration.php';
require __DIR__ . '/../src/Database.php';

use Syntropia\Role;
use Syntropia\Web\Authentification;
use Syntropia\Web\Comptes;

Authentification::demarrer($_SERVER);
$auth = new Authentification();
$auth->exige(Role::Administrateur, 'conception-parcelles.php');

const PAR_PAGE = 8;

$comptes = new Comptes();
$totaux = $comptes->journalTotaux();
$pages = max(1, (int) ceil($totaux['connexions'] / PAR_PAGE));
$page = max(1, min($pages, (int) ($_GET['page'] ?? 1)));
$lignes = $comptes->journal(PAR_PAGE, ($page - 1) * PAR_PAGE);
$compteId = (int) $auth->compteId();

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
// La base écrit en temps universel (`Database.php` : time_zone +00:00) ; on lit à l'heure de Paris, quel que soit le
// fuseau de PHP (UTC en local, Paris en production).
$paris = new DateTimeZone('Europe/Paris');
$aujourdhui = (new DateTimeImmutable('now', $paris))->format('Y-m-d');
$hier = (new DateTimeImmutable('yesterday', $paris))->format('Y-m-d');
$quand = static function (string $le) use ($paris, $aujourdhui, $hier): string {
    $t = (new DateTimeImmutable($le, new DateTimeZone('UTC')))->setTimezone($paris);
    $jour = $t->format('Y-m-d');
    return ($jour === $aujourdhui ? "aujourd'hui" : ($jour === $hier ? 'hier' : 'le ' . $t->format('d/m/Y'))) . ' à ' . $t->format('H:i');
};
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Journal des connexions — Syntropia</title>
<link rel="stylesheet" href="assets/css/gabarit.css?v=<?= (int) @filemtime(__DIR__ . '/assets/css/gabarit.css') ?>">
<style>
  :root{ --haut-bandeaux: 3.4rem; }

  .jc-centre{ flex:1; display:flex; align-items:center; justify-content:center; padding:1rem 1.5rem; overflow:hidden; }
  .jc-cadre{ width:100%; max-width:30rem; }
  .jc-cadre h1{ font:600 1.25rem var(--titre); color:var(--accent-titre); margin:0 0 .3rem; text-align:center; }
  .jc-chapeau{ font:400 .84rem/1.4 var(--texte); color:var(--muted); text-align:center; margin:0 0 .8rem; }
  .jc-liste{ list-style:none; margin:0; padding:0; }
  .jc-liste li{
    display:flex; align-items:baseline; gap:.6rem; padding:.42rem 0; border-top:1px solid var(--line);
    font:400 .86rem/1.25 var(--texte); color:var(--ink);
  }
  .jc-liste li:last-child{ border-bottom:1px solid var(--line); }
  .jc-qui{ flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .jc-qui strong{ font:600 .86rem var(--titre); }
  .jc-qui small{ color:var(--muted); font-size:.74rem; }
  .jc-quand{ flex:none; color:var(--muted); font-size:.8rem; }
  .jc-pages{ display:flex; justify-content:center; align-items:center; gap:.8rem; margin-top:.5rem; font:400 .78rem var(--texte); color:var(--muted); }
  .jc-pages a{ color:var(--accent-titre); text-decoration:none; font-weight:600; }
  .jc-vide{ font:400 .85rem/1.4 var(--texte); color:var(--muted); text-align:center; }

  @media (max-width:30rem){
    .jc-centre{ padding:.5rem 1rem; align-items:flex-start; }
    .jc-liste li{ flex-wrap:wrap; gap:0 .6rem; padding:.32rem 0; }
    .jc-qui small{ display:none; }
  }
  @media (max-height:38rem){ .jc-chapeau{ display:none; } .jc-liste li{ padding:.3rem 0; } }
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
      <span class="bg-centre-titre">Connexions</span>
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
        <p class="pg-pied">Qui se connecte à l'atelier, et quand.</p>
      </div>
    </nav>

    <main class="bureau" id="bureau">
      <div class="jc-centre">
        <div class="jc-cadre">
          <h1>Journal des connexions</h1>
          <p class="jc-chapeau"><?= $totaux['connexions'] ?> connexion<?= $totaux['connexions'] > 1 ? 's' : '' ?>
             de <?= $totaux['comptes'] ?> compte<?= $totaux['comptes'] > 1 ? 's' : '' ?> sur les
             <?= Comptes::JOURNAL_JOURS ?> derniers jours — la plus récente d'abord.</p>
<?php if ($lignes === []): ?>
          <p class="jc-vide">Aucune connexion notée pour l'instant : le journal commence à la première connexion
             qui suit sa mise en place.</p>
<?php else: ?>
          <ul class="jc-liste">
<?php foreach ($lignes as $l): ?>
            <li>
              <span class="jc-qui"><strong><?= $h($l['nom_affiche']) ?></strong><?= $l['compte_id'] === $compteId ? ' (vous)' : '' ?>
                <small>· <?= $h($l['identifiant']) ?></small></span>
              <span class="jc-quand"><?= $h($quand($l['le'])) ?></span>
            </li>
<?php endforeach; ?>
          </ul>
<?php if ($pages > 1): ?>
          <p class="jc-pages">
            <?= $page > 1 ? '<a href="journal-connexions.php?page=' . ($page - 1) . '">‹ Plus récentes</a>' : '<span></span>' ?>
            <span><?= $page ?> / <?= $pages ?></span>
            <?= $page < $pages ? '<a href="journal-connexions.php?page=' . ($page + 1) . '">Plus anciennes ›</a>' : '<span></span>' ?>
          </p>
<?php endif; ?>
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
      <p>Chaque connexion réussie à l'atelier, la plus récente d'abord : le nom affiché du compte, son
         identifiant, le jour et l'heure. Seuls les administrateurs de l'atelier voient ce journal.</p>
      <p>Le journal ne note ni les tentatives échouées, ni les déconnexions, ni l'adresse d'où l'on se
         connecte. Ses lignes s'effacent d'elles-mêmes au bout de <?= Comptes::JOURNAL_JOURS ?> jours.</p>
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
