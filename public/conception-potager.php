<?php

declare(strict_types=1);

/**
 * Le plan de culture d'un potager — les cultures, placées par planche.
 *
 * Étape 4 de Conception, pour les parcelles de type « potager » (celles du type pérenne gardent
 * `conception-scenario.php`, qui redirige ici pour un potager). Voir `chantier-plan-de-culture.md`, Phase 1,
 * et `chantier-associations.md`.
 *
 * **Le placement se fait par planche**, selon les associations (le porteur, 2026-09-20) : une planche du plan
 * porte plusieurs cultures, c'est le principe même de l'association. Chaque planche dit ce que les tables de
 * Weinrich savent de ses voisinages (`Associations::entre()`) : ce qui s'entend bien, ce qu'il vaut mieux éviter de
 * placer côte à côte. **Une information de conception, jamais une interdiction** — des ordres de conduite, non des
 * preuves ; rien n'est refusé.
 */

require __DIR__ . '/../src/autoload.php';
require __DIR__ . '/../src/Configuration.php';
require __DIR__ . '/../src/Database.php';

use Syntropia\Associations;
use Syntropia\Configuration;
use Syntropia\Role;
use Syntropia\Web\Authentification;
use Syntropia\Web\Cultures;
use Syntropia\Web\Parcelles;
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
$peutEcrire = $sites->peutEcrireSur($siteId, $compteId);
$parcellesService = new Parcelles();
$culturesService = new Cultures();

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$dateFr = static fn (string $d): string => (new DateTimeImmutable($d))->format('d/m/Y');

$parcelles = $parcellesService->dUnSite($siteId);
$parcelle = null;
$demande = (int) ($_GET['parcelle'] ?? $_POST['parcelle'] ?? 0);
foreach ($parcelles as $p) {
    if ((int) $p['id'] === $demande) {
        $parcelle = $p;
        break;
    }
}
$potagers = array_values(array_filter($parcelles, static fn (array $p): bool => ($p['type'] ?? '') === 'potager'));
// Une parcelle pérenne se compose par son scénario ; sans parcelle demandée, on ouvre le premier potager.
if ($parcelle !== null && ($parcelle['type'] ?? '') !== 'potager') {
    header('Location: ' . Configuration::base() . 'conception-scenario.php?parcelle=' . (int) $parcelle['id']);
    exit;
}
$parcelle ??= $potagers[0] ?? null;
if ($parcelle === null && $parcelles !== []) {
    header('Location: ' . Configuration::base() . 'conception-scenario.php?parcelle=' . (int) $parcelles[0]['id']);
    exit;
}
$idP = $parcelle !== null ? (int) $parcelle['id'] : 0;
$nbPlanches = $parcelle !== null ? (int) ($parcelle['nb_planches'] ?? 0) : 0;
$retour = Configuration::base() . 'conception-potager.php?parcelle=' . $idP;

$erreurs = [];
$ouvre = false;
$saisie = null;
$editionId = 0;
$editionVersion = '';
$flash = $_SESSION['syntropia_flash_potager'] ?? null;
unset($_SESSION['syntropia_flash_potager']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $parcelle !== null) {
    $action = (string) ($_POST['action'] ?? '');
    $ouvre = true;
    $saisie = $_POST;
    $editionId = (int) ($_POST['culture_id'] ?? 0);
    $editionVersion = (string) ($_POST['version'] ?? '');
    if (!$peutEcrire) {
        http_response_code(403);
        $erreurs['droit'] = 'Vous lisez ce projet : il ne se modifie pas depuis votre compte.';
    } elseif (!$auth->jetonValide($_POST['csrf'] ?? null)) {
        http_response_code(403);
        $erreurs['jeton'] = 'Formulaire expiré — rechargez la page.';
    } elseif ($action === 'ajouter') {
        $erreurs = $culturesService->ajouter($idP, $nbPlanches, $_POST);
        if ($erreurs === []) {
            $_SESSION['syntropia_flash_potager'] = ['ok', 'La culture est notée.'];
            header('Location: ' . $retour);
            exit;
        }
    } elseif ($action === 'modifier') {
        $erreurs = $culturesService->modifier($editionId, $idP, $nbPlanches, $editionVersion, $_POST);
        if ($erreurs === []) {
            $_SESSION['syntropia_flash_potager'] = ['ok', 'La culture est mise à jour.'];
            header('Location: ' . $retour);
            exit;
        }
    } elseif ($action === 'retirer') {
        $_SESSION['syntropia_flash_potager'] = $culturesService->retirer($editionId, $idP)
            ? ['ok', 'La culture est retirée du plan.']
            : ['ok', 'Cette culture n’est plus dans le plan.'];
        header('Location: ' . $retour);
        exit;
    } else {
        http_response_code(400);
        $erreurs['action'] = 'Cette action n’est pas reconnue.';
    }
}

$cultures = $parcelle !== null ? $culturesService->dUneParcelle($idP) : [];

$edition = null;
if ($saisie === null && $peutEcrire && $parcelle !== null && isset($_GET['culture'])) {
    $edition = $culturesService->obtenir((int) $_GET['culture'], $idP);
    if ($edition !== null) {
        $editionId = (int) $edition['id'];
        $editionVersion = Cultures::version($edition);
        $ouvre = true;
    }
}
if ($saisie === null && $edition === null && $peutEcrire && ($_GET['saisie'] ?? '') === '1') {
    $ouvre = true;
}

// Les planches du plan (et celles que des cultures occupent encore hors du plan), puis les cultures non placées.
$parPlanche = [];
$nonPlacees = [];
foreach ($cultures as $c) {
    $c['planche'] === null ? $nonPlacees[] = $c : $parPlanche[(int) $c['planche']][] = $c;
}
$numeros = $nbPlanches > 0 ? range(1, $nbPlanches) : [];
$horsPlan = array_values(array_diff(array_keys($parPlanche), $numeros));
sort($horsPlan);
$cartes = [];
foreach (array_merge($numeros, $horsPlan) as $n) {
    $liste = $parPlanche[$n] ?? [];
    $noms = array_map(static fn (array $c): string => (string) $c['espece'], $liste);
    $relations = Associations::entre($noms);
    // « Pas de voisinage connu » : une culture sans fiche propre ET qu'aucune autre ne cite sur cette planche.
    $citees = [];
    foreach ($relations as $r) {
        $citees[Associations::normaliser($r['a'])] = $citees[Associations::normaliser($r['b'])] = true;
    }
    $cartes[] = ['numero' => $n, 'libelle' => 'Planche ' . $n, 'hors_plan' => in_array($n, $horsPlan, true), 'cultures' => $liste,
        'relations' => $relations,
        'sans_fiche' => array_values(array_filter(array_unique($noms), static fn (string $x): bool => Associations::fiche($x) === null && !isset($citees[Associations::normaliser($x)])))];
}
if ($nonPlacees !== []) {
    $cartes[] = ['numero' => null, 'libelle' => 'Pas encore placées', 'hors_plan' => false, 'cultures' => $nonPlacees, 'relations' => [], 'sans_fiche' => []];
}

// Le formulaire.
$f = ['espece' => '', 'planche' => '', 'strate' => '', 'espacement_m' => '', 'date_semis_repiquage' => '', 'date_recolte' => '', 'notes' => ''];
if ($saisie !== null) {
    foreach ($f as $k => $_) {
        $f[$k] = (string) ($saisie[$k] ?? '');
    }
} elseif ($edition !== null) {
    foreach ($f as $k => $_) {
        $f[$k] = (string) ($edition[$k] ?? '');
    }
} elseif (isset($_GET['planche']) && ctype_digit((string) $_GET['planche'])) {
    $f['planche'] = (string) $_GET['planche'];
}
$ficheEditee = $f['espece'] !== '' ? Associations::fiche($f['espece']) : null;
$connues = array_map(static fn (array $l): string => (string) $l['nom'], array_values(Associations::table('potager')));

$nomAffiche = $auth->nomAffiche() ?? '';
$initiales = '';
foreach (preg_split('/\s+/', trim($nomAffiche)) ?: [] as $mot) {
    if ($mot !== '') {
        $initiales .= mb_strtoupper(mb_substr($mot, 0, 1));
    }
}
$initiales = mb_substr($initiales, 0, 2) ?: '?';
$titrePage = $parcelle !== null ? $parcelle['code'] . ' — ' . $parcelle['nom'] : '';
$lienParcelle = static fn (array $p): string => (($p['type'] ?? '') === 'potager' ? 'conception-potager.php' : 'conception-scenario.php') . '?parcelle=' . (int) $p['id'];
$listeNoms = static fn (array $noms): string => implode(', ', array_map(static fn (string $n): string => Associations::toxique($n) ? $n . ' (toxique)' : $n, $noms));
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Plan de culture — Syntropia</title>
<link rel="stylesheet" href="assets/css/gabarit.css">
<style>
  :root{ --haut-bandeaux: 3.4rem; }

  #bureau{ padding:.4rem .6rem; align-items:stretch; }
  .pt-centre{ width:100%; min-height:0; display:flex; flex-direction:column; gap:.5rem; padding:.3rem .4rem .2rem; overflow:hidden; }
  .pt-entete{ display:flex; align-items:center; gap:.7rem; flex-wrap:wrap; }
  .pt-titre{ font:600 1rem var(--titre); color:var(--accent-titre); margin:0; }
  .pt-badge{ font:500 .72rem var(--titre); color:var(--muted); border:1px solid var(--line); border-radius:1rem; padding:.1rem .55rem; }
  .pt-actions{ margin-left:auto; display:flex; gap:.5rem; }
  .pt-bouton{ font:600 .8rem var(--titre); color:var(--bouton-encre); background:var(--accent); border:0; border-radius:.5rem; padding:.35rem .75rem; text-decoration:none; cursor:pointer; }
  .pt-bouton--calme{ background:transparent; color:var(--accent-titre); border:1px solid var(--accent); }
  .pt-message{ margin:0; padding:.4rem .7rem; border-radius:.5rem; font:400 .82rem/1.35 var(--texte); background:var(--accent-fond); border:1px solid var(--accent); color:var(--accent-titre); }
  .pt-message--alerte{ border-color:var(--alerte); color:var(--alerte); }
  .pt-message a{ color:inherit; font-weight:600; }
  .pt-annonce{ margin:0; font:400 .84rem/1.4 var(--texte); color:var(--muted); }

  /* les planches : des cartes, réglées en em pour que la zone puisse se réduire */
  .pt-zone{ font-size:15px; }
  .pt-cartes{ display:grid; grid-template-columns:repeat(auto-fill, minmax(13em, 1fr)); gap:.6em; align-items:start; }
  .pt-carte{ border:1px solid var(--line); border-radius:.6em; padding:.5em .7em; background:var(--surface); min-width:0; }
  .pt-carte--hors{ border-style:dashed; }
  .pt-carte h2{ margin:0 0 .25em; font:600 .95em var(--titre); color:var(--accent-titre); display:flex; justify-content:space-between; gap:.4em; align-items:baseline; }
  .pt-carte h2 a{ font:500 .75em var(--titre); color:var(--accent-titre); text-decoration:none; }
  .pt-carte h2 small{ font:400 .75em var(--texte); color:var(--muted); }
  .pt-cultures{ list-style:none; margin:0; padding:0; }
  .pt-cultures li{ font:400 .88em/1.35 var(--texte); color:var(--ink); padding:.1em 0; }
  .pt-cultures a{ color:var(--ink); text-decoration:none; font-weight:600; }
  .pt-cultures a:hover{ text-decoration:underline; }
  .pt-cultures small{ color:var(--muted); font-size:.85em; }
  .pt-vide{ margin:0; font:400 .82em/1.35 var(--texte); color:var(--muted); }
  .pt-rel{ margin:.35em 0 0; padding:.3em 0 0; border-top:1px solid var(--line); font:400 .8em/1.35 var(--texte); color:var(--ink); }
  .pt-rel p{ margin:.1em 0; }
  .pt-rel .bien{ color:var(--accent-titre); }
  .pt-rel .eviter{ color:var(--alerte); }
  .pt-rel small{ color:var(--muted); }
  .pt-page-nav{ display:none; align-items:center; gap:.6rem; font:400 .78rem var(--texte); color:var(--muted); }
  .pt-page-nav.actif{ display:flex; }
  .pt-page-nav button{ font:600 .78rem var(--titre); color:var(--accent-titre); background:transparent; border:1px solid var(--accent); border-radius:.4rem; padding:.15rem .6rem; cursor:pointer; }
  .pt-page-nav button:disabled{ opacity:.4; cursor:default; }
  .pt-vide-page{ margin:auto; max-width:26rem; text-align:center; font:400 .92rem/1.5 var(--texte); color:var(--muted); }
  .pt-vide-page a{ color:var(--accent-titre); font-weight:600; }
  @media (max-width:40rem){ .pt-centre{ padding-top:1.8rem; } }
  @media (max-height:38rem){ .pt-annonce{ display:none; } }

  /* le panneau Données */
  .pt-form label.pt-champ{ display:block; font:500 .78rem var(--titre); color:var(--ink); margin:0 0 .5rem; }
  .pt-form label.pt-champ small{ font:400 .68rem var(--texte); color:var(--muted); }
  .pt-form input[type=text], .pt-form input[type=number], .pt-form input[type=date], .pt-form select, .pt-form textarea{
    display:block; width:100%; margin-top:.15rem; padding:.36rem .5rem; font:400 .84rem var(--texte); color:var(--ink);
    background:var(--surface); border:1px solid var(--line); border-radius:.45rem; }
  .pt-form textarea{ resize:vertical; min-height:3.2rem; }
  .pt-form input:focus, .pt-form select:focus, .pt-form textarea:focus{ outline:2px solid var(--accent); outline-offset:1px; }
  .pt-deux{ display:grid; grid-template-columns:1fr 1fr; gap:.5rem; }
  .pt-note{ font:400 .74rem/1.4 var(--texte); color:var(--muted); margin:.2rem 0 .6rem; }
  .pt-fiche{ margin:.2rem 0 .7rem; padding:.45rem .6rem; border-radius:.45rem; background:var(--accent-fond); font:400 .78rem/1.4 var(--texte); color:var(--ink); }
  .pt-fiche .eviter{ color:var(--alerte); }
  .pt-erreurs{ margin:0 0 .7rem; padding:.5rem .7rem; border:1px solid var(--alerte); border-radius:.5rem; color:var(--alerte); font:400 .8rem/1.35 var(--texte); }
  .pt-erreurs ul{ margin:.2rem 0 0 1rem; padding:0; }
  .pt-retrait{ margin-top:1rem; padding-top:.6rem; border-top:1px solid var(--line); }
  .pt-retrait button{ font:600 .74rem var(--titre); color:var(--alerte); background:transparent; border:1px solid var(--alerte); border-radius:.4rem; padding:.2rem .6rem; cursor:pointer; }
  .pt-lien{ font:500 .76rem var(--titre); color:var(--accent-titre); }
</style>
</head>
<body<?= $ouvre ? ' data-ouvre="1"' : '' ?>>
<div class="app" id="app">

  <header class="bandeau-general">
    <div class="bg-gauche">
      <button class="hamburger" id="hamburger" aria-expanded="false" aria-controls="panneau-gauche" aria-label="Afficher le menu">
        <span></span><span></span><span></span>
      </button>
      <span class="logo-espace" aria-hidden="true" title="Emplacement du logo">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v11M12 3C8 5 6 8 6 12M12 3c4 2 6 5 6 9M6 12c0 4 2.5 7 6 8M18 12c0 4-2.5 7-6 8"/></svg>
      </span>
      <div class="marque"><a class="marque-nom" href="./">Syntropia</a></div>
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
          <span class="compte-avatar"><?= $h($initiales) ?></span>
          <span class="compte-nom"><?= $h($nomAffiche) ?></span>
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
      <div class="pg-entete"><a class="marque-nom" href="./">Syntropia</a></div>
      <div class="pg-corps">
        <div class="pg-section">
          <p class="pg-titre">Phase projet</p>
          <ul class="pg-liste pg-phase-projet"></ul>
        </div>
        <div class="pg-section">
          <p class="pg-titre">Parcelles</p>
          <ul class="pg-liste">
<?php foreach ($parcelles as $p): $estActive = $parcelle !== null && (int) $p['id'] === $idP; ?>
            <li><a href="<?= $h($lienParcelle($p)) ?>"<?= $estActive ? ' class="active" aria-current="page"' : '' ?>><span class="pg-puce"><?= ($p['type'] ?? '') === 'potager' ? '▥' : '≋' ?></span><span class="pg-libelle"><?= $h($p['code']) ?> — <?= $h($p['nom']) ?></span></a></li>
<?php endforeach; ?>
            <li><a href="conception-parcelles.php?nouvelle=1"><span class="pg-puce">+</span><span class="pg-libelle">Nouvelle parcelle</span></a></li>
          </ul>
        </div>
<?php if ($parcelle !== null): ?>
        <div class="pg-section">
          <p class="pg-titre"><?= $h($parcelle['code']) ?> — <?= $h($parcelle['nom']) ?></p>
          <ul class="pg-liste">
            <li><a href="conception-parcelles.php?parcelle=<?= $idP ?>"><span class="pg-puce">▤</span><span class="pg-libelle">Le plan</span></a></li>
            <li><a href="conception-potager.php?parcelle=<?= $idP ?>" class="active" aria-current="page"><span class="pg-puce">▥</span><span class="pg-libelle">Le plan de culture</span></a></li>
            <li><a href="conception-materiel-et-eau.php?parcelle=<?= $idP ?>"><span class="pg-puce">◍</span><span class="pg-libelle">Matériel et eau</span></a></li>
          </ul>
        </div>
<?php endif; ?>
        <p class="pg-pied">Un potager se compose par planches : ce qui pousse sur chacune, et ce qui s'entend bien.</p>
      </div>
    </nav>

    <main class="bureau" id="bureau">
<?php if ($parcelle === null): ?>
      <div class="pt-centre">
        <p class="pt-vide-page">Le plan de culture se compose parcelle par parcelle, et ce site n'en a pas encore.
          <a href="conception-parcelles.php?nouvelle=1">Ouvrir la première parcelle →</a></p>
      </div>
<?php else: ?>
      <div class="pt-centre">
        <div class="pt-entete">
          <h1 class="pt-titre">Plan de culture — <?= $h($titrePage) ?></h1>
<?php if ($cultures !== []): ?>
          <span class="pt-badge"><?= count($cultures) ?> culture<?= count($cultures) > 1 ? 's' : '' ?></span>
<?php endif; ?>
<?php if ($peutEcrire): ?>
          <span class="pt-actions"><a class="pt-bouton pt-bouton--calme" href="<?= $h($retour) ?>&amp;saisie=1" id="pt-saisir">Ajouter une culture</a></span>
<?php endif; ?>
        </div>
<?php if (is_array($flash)): ?>
        <p class="pt-message" role="status"><?= $h($flash[1]) ?></p>
<?php endif; ?>
<?php if ($erreurs !== []): ?>
        <p class="pt-message pt-message--alerte" role="alert">Votre saisie est à reprendre — le détail est dans le panneau Données.</p>
<?php endif; ?>
<?php if ($nbPlanches < 1): ?>
        <p class="pt-message">Le plan de cette parcelle n'a pas encore de planches : les cultures se placent par planche, sur le plan. <a href="conception-parcelles.php?parcelle=<?= $idP ?>">Ouvrir le plan →</a></p>
<?php else: ?>
        <p class="pt-annonce">Une planche porte plusieurs cultures : c'est le principe de l'association. Chaque planche dit ce que l'on sait de ses voisinages — des ordres de conduite, non des preuves.</p>
<?php endif; ?>

<?php if ($cartes === []): ?>
        <p class="pt-vide-page">Aucune culture n'est encore placée sur cette parcelle.
<?php if ($peutEcrire): ?>
          <a href="<?= $h($retour) ?>&amp;saisie=1" id="pt-saisir-vide">Ajouter la première →</a>
<?php endif; ?></p>
<?php else: ?>
        <div class="pt-zone" id="pt-zone">
          <div class="pt-cartes" id="pt-cartes">
<?php foreach ($cartes as $c): ?>
            <section class="pt-carte<?= $c['hors_plan'] ? ' pt-carte--hors' : '' ?> pt-item">
              <h2><span><?= $h($c['libelle']) ?><?= $c['hors_plan'] ? ' <small>hors du plan</small>' : '' ?></span>
<?php if ($peutEcrire && $c['numero'] !== null && !$c['hors_plan']): ?>
                <a href="<?= $h($retour) ?>&amp;saisie=1&amp;planche=<?= (int) $c['numero'] ?>" title="Ajouter une culture sur cette planche">+</a>
<?php endif; ?>
              </h2>
<?php if ($c['cultures'] === []): ?>
              <p class="pt-vide">Rien encore sur cette planche.</p>
<?php else: ?>
              <ul class="pt-cultures">
<?php foreach ($c['cultures'] as $k): ?>
                <li><?php if ($peutEcrire): ?><a href="<?= $h($retour) ?>&amp;culture=<?= (int) $k['id'] ?>" title="Ouvrir pour corriger"><?= $h($k['espece']) ?></a><?php else: ?><b><?= $h($k['espece']) ?></b><?php endif; ?>
<?php if ($k['strate'] !== null || $k['date_semis_repiquage'] !== null): ?>
                  <small><?= $h(implode(' · ', array_filter([$k['strate'] !== null ? (Cultures::STRATES[$k['strate']] ?? '') : '', $k['date_semis_repiquage'] !== null ? 'semis ' . $dateFr((string) $k['date_semis_repiquage']) : '']))) ?></small>
<?php endif; ?></li>
<?php endforeach; ?>
              </ul>
<?php endif; ?>
<?php if ($c['relations'] !== [] || $c['sans_fiche'] !== []): ?>
              <div class="pt-rel">
<?php foreach ($c['relations'] as $r): ?>
<?php if ($r['relation'] === Associations::FAVORABLE): ?>
                <p class="bien">✓ <?= $h($r['a']) ?> et <?= $h($r['b']) ?> s'entendent bien</p>
<?php elseif ($r['relation'] === Associations::DEFAVORABLE): ?>
                <p class="eviter">⚠ <?= $h($r['a']) ?> et <?= $h($r['b']) ?> : à éviter côte à côte</p>
<?php else: ?>
                <p class="eviter">⚠ <?= $h($r['a']) ?> et <?= $h($r['b']) ?> : les tables se contredisent</p>
<?php endif; ?>
<?php endforeach; ?>
<?php if ($c['sans_fiche'] !== [] && count($c['cultures']) > 1): ?>
                <p><small>Pas de voisinage connu pour : <?= $h(implode(', ', $c['sans_fiche'])) ?></small></p>
<?php endif; ?>
              </div>
<?php endif; ?>
            </section>
<?php endforeach; ?>
          </div>
        </div>
        <div class="pt-page-nav" id="pt-nav">
          <button type="button" id="pt-prec">← Précédentes</button><span id="pt-page"></span><button type="button" id="pt-suiv">Suivantes →</button>
        </div>
<?php endif; ?>
      </div>
<?php endif; ?>
    </main>

<?php if ($parcelle !== null && $peutEcrire): ?>
    <div class="scrim" id="scrim-droit"></div>
    <button class="tirette" id="tirette" aria-expanded="false" aria-controls="panneau-droit" aria-label="Afficher le panneau Données">
      <svg class="tirette-triangle" viewBox="0 0 10 10" aria-hidden="true"><polygon points="1,1 9,5 1,9" fill="currentColor"/></svg>
      <span class="tirette-texte">Données</span>
    </button>
    <aside class="panneau-droit" id="panneau-droit" aria-hidden="true">
      <div class="pd-corps pt-form">
<?php if ($erreurs !== []): ?>
        <div class="pt-erreurs" role="alert"><strong>Votre saisie est à reprendre.</strong>
          <ul><?php foreach ($erreurs as $e): ?><li><?= $h($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
        <p class="pd-titre"><?= $editionId > 0 ? 'Corriger la culture' : 'Ajouter une culture' ?> — <?= $h($parcelle['code']) ?></p>
        <p class="pt-note"><b>À quoi elle sert</b> : à placer chaque culture sur une planche, pour que ce qui s'entend bien soit voisin et que l'on évite ce qui s'entend mal. Seul le nom est demandé ; la planche, la strate et les dates viennent quand on les connaît.</p>
        <form method="post" action="conception-potager.php?parcelle=<?= $idP ?>">
          <input type="hidden" name="csrf" value="<?= $h($auth->jeton()) ?>">
          <input type="hidden" name="action" value="<?= $editionId > 0 ? 'modifier' : 'ajouter' ?>">
          <input type="hidden" name="parcelle" value="<?= $idP ?>">
<?php if ($editionId > 0): ?>
          <input type="hidden" name="culture_id" value="<?= $editionId ?>">
          <input type="hidden" name="version" value="<?= $h($editionVersion) ?>">
<?php endif; ?>
          <label class="pt-champ">La culture
            <input type="text" name="espece" list="pt-connues" required maxlength="160" value="<?= $h($f['espece']) ?>" placeholder="Ex. tomate, carotte, haricot">
          </label>
          <datalist id="pt-connues">
<?php foreach ($connues as $n): ?>
            <option value="<?= $h($n) ?>"></option>
<?php endforeach; ?>
          </datalist>
<?php if ($ficheEditee !== null): ?>
          <div class="pt-fiche"><b>Ce que les tables disent de « <?= $h($ficheEditee['nom']) ?> »</b> [<?= $h($ficheEditee['source']) ?>]<br>
            S'entend bien avec : <?= $ficheEditee['favorables'] !== [] ? $h($listeNoms($ficheEditee['favorables'])) : '<i>rien de noté</i>' ?><br>
            <span class="eviter">À éviter à côté : <?= $ficheEditee['defavorables'] !== [] ? $h($listeNoms($ficheEditee['defavorables'])) : '<i>rien de noté</i>' ?></span></div>
<?php elseif ($f['espece'] !== '' && $editionId > 0): ?>
          <p class="pt-note">Cette culture n'est pas dans les tables : <b>pas de voisinage connu</b> — ce qui ne veut pas dire neutre.</p>
<?php endif; ?>
          <div class="pt-deux">
            <label class="pt-champ">La planche
              <select name="planche">
                <option value=""><?= $nbPlanches < 1 ? 'Pas de planche au plan' : 'Pas encore placée' ?></option>
<?php for ($n = 1; $n <= $nbPlanches; $n++): ?>
                <option value="<?= $n ?>"<?= $f['planche'] === (string) $n ? ' selected' : '' ?>>Planche <?= $n ?></option>
<?php endfor; ?>
              </select>
            </label>
            <label class="pt-champ">Sa strate
              <select name="strate">
                <option value="">—</option>
<?php foreach (Cultures::STRATES as $cle => $lib): ?>
                <option value="<?= $h($cle) ?>"<?= $f['strate'] === $cle ? ' selected' : '' ?>><?= $h($lib) ?></option>
<?php endforeach; ?>
              </select>
            </label>
          </div>
          <div class="pt-deux">
            <label class="pt-champ">Semis ou repiquage <small>prévu ou fait</small>
              <input type="date" name="date_semis_repiquage" value="<?= $h($f['date_semis_repiquage']) ?>">
            </label>
            <label class="pt-champ">Récolte <small>prévue</small>
              <input type="date" name="date_recolte" value="<?= $h($f['date_recolte']) ?>">
            </label>
          </div>
          <label class="pt-champ">Espacement <small>mètres, facultatif</small>
            <input type="number" name="espacement_m" min="0.01" max="20" step="0.01" inputmode="decimal" value="<?= $h($f['espacement_m']) ?>">
          </label>
          <label class="pt-champ">Notes <small>facultatif</small>
            <textarea name="notes" rows="2" maxlength="2000"><?= $h($f['notes']) ?></textarea>
          </label>
          <button type="submit" class="pt-bouton"><?= $editionId > 0 ? 'Enregistrer' : 'Ajouter cette culture' ?></button>
<?php if ($editionId > 0): ?>
          <a class="pt-lien" href="<?= $h($retour) ?>" style="margin-left:.6rem">Fermer</a>
<?php endif; ?>
        </form>
<?php if ($editionId > 0): ?>
        <form method="post" action="conception-potager.php?parcelle=<?= $idP ?>" class="pt-retrait" onsubmit="return confirm('Retirer cette culture du plan ?');">
          <input type="hidden" name="csrf" value="<?= $h($auth->jeton()) ?>">
          <input type="hidden" name="action" value="retirer">
          <input type="hidden" name="parcelle" value="<?= $idP ?>">
          <input type="hidden" name="culture_id" value="<?= $editionId ?>">
          <button type="submit">Retirer cette culture</button>
        </form>
<?php endif; ?>
      </div>
    </aside>
<?php endif; ?>

  </div>

  <div class="scrim" id="scrim-info"></div>
  <button class="tirette-info" id="tirette-info" aria-expanded="false" aria-controls="panneau-info" aria-label="Afficher les informations">
    <svg class="tirette-triangle" viewBox="0 0 10 10" aria-hidden="true"><polygon points="1,1 9,5 1,9" fill="currentColor"/></svg>
    <span class="tirette-texte">Informations</span>
  </button>
  <aside class="panneau-info" id="panneau-info" aria-hidden="true">
    <div class="pi-corps">
      <p>Un potager se compose <strong>par planche</strong> : les planches viennent du plan de la parcelle, et chacune porte plusieurs cultures — c'est le principe même de l'association.
         Le potager syntropique recrée sa biomasse chaque année par des annuelles ; les strates sont les quatre de la syntropie.</p>
      <p><strong>Ce que dit chaque planche.</strong> Les relations viennent de tables d'association (Christa Weinrich, abbaye de Fulda) : ce qui s'entend bien, ce qu'il vaut mieux éviter de placer côte à côte.
         Savoir ce qu'il ne faut pas associer est une <strong>information de conception, pas une interdiction</strong> : rien n'est refusé.</p>
      <p><strong>Des ordres de conduite, non des preuves.</strong> Une relation bénéfique peut ne pas se vérifier partout ; elle s'éprouve chez soi — un témoin, à côté, sert à cela. Le contexte de la source est l'Allemagne
         moyenne (Jura souabe, Hesse), et son itinéraire — un sol ameubli chaque printemps — n'est pas celui de l'atelier : ce qui se transporte, ce sont les couples, jamais la conduite.</p>
      <p><strong>Une culture absente des tables n'est pas neutre</strong> : « pas de voisinage connu » ne veut pas dire « sans effet ». Les tables ne sont pas non plus symétriques — une relation est connue dès qu'une des deux plantes la cite.</p>
      <p>Connecté en tant que <?= $h($nomAffiche) ?>. <a href="deconnexion.php">Se déconnecter</a>.</p>
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
  Gabarit.init({
    site: <?= json_encode((string) $site['nom'], JSON_UNESCAPED_UNICODE) ?>,
    etape: 'conception-scenario'<?php if ($parcelle !== null): ?>,
    contexte: <?= json_encode($titrePage, JSON_UNESCAPED_UNICODE) ?><?php endif; ?>
  });

  (function(){
    var tirette = document.getElementById('tirette');
    if (tirette && document.body.dataset.ouvre === '1') tirette.click();
    ['pt-saisir', 'pt-saisir-vide'].forEach(function(id){
      var a = document.getElementById(id);
      if (a && tirette) a.addEventListener('click', function(ev){ ev.preventDefault(); tirette.click(); });
    });

    /* Le bureau ne défile jamais : les cartes se réduisent, puis se paginent en montrant autant de planches que la place le permet. */
    var centre = document.querySelector('.pt-centre');
    var cartes = Array.prototype.slice.call(document.querySelectorAll('.pt-item'));
    var page = 0, parPage = cartes.length;
    function deborde(){ return centre.scrollHeight > centre.clientHeight + 1; }
    function afficher(){
      cartes.forEach(function(l, i){ l.style.display = (i >= page * parPage && i < (page + 1) * parPage) ? '' : 'none'; });
      var nav = document.getElementById('pt-nav');
      if (!nav) return;
      var pages = Math.max(1, Math.ceil(cartes.length / parPage));
      nav.classList.toggle('actif', pages > 1);
      document.getElementById('pt-page').textContent = 'Page ' + (page + 1) + ' sur ' + pages;
      document.getElementById('pt-prec').disabled = page === 0;
      document.getElementById('pt-suiv').disabled = page >= pages - 1;
    }
    function ajuster(){
      var zone = document.getElementById('pt-zone');
      if (!zone || !centre || !zone.offsetParent || cartes.length === 0) return;
      var t = 15;
      zone.style.fontSize = t + 'px';
      page = 0; parPage = cartes.length; afficher();
      while (deborde() && t > 12) { t -= 0.5; zone.style.fontSize = t + 'px'; }
      if (deborde()) {
        var n = cartes.length;
        while (n > 1) { n--; parPage = n; afficher(); if (!deborde()) break; }
      }
    }
    var prec = document.getElementById('pt-prec'), suiv = document.getElementById('pt-suiv');
    if (prec) prec.addEventListener('click', function(){ page = Math.max(0, page - 1); afficher(); });
    if (suiv) suiv.addEventListener('click', function(){ page++; afficher(); });
    if (centre) {
      ajuster();
      window.addEventListener('resize', ajuster);
      if (document.fonts && document.fonts.ready) document.fonts.ready.then(ajuster);
    }
  })();
</script>
</body>
</html>
