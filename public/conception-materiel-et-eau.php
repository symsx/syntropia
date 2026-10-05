<?php

declare(strict_types=1);

/**
 * Matériel et eau — ce qu'il faut se procurer, et l'eau qu'il faudra.
 *
 * Étape 5 de Conception (`directives.txt` : « récapitulatif du matériel végétal à
 * se procurer pour chaque parcelle, estimer les besoins en eau pour l'arrosage si
 * les pluies ne sont pas suffisantes. Prévision de stockage ou d'accès à l'eau »).
 *
 * Deux vues, en onglets CSS purs, que le bureau ne fait jamais défiler :
 * - **Matériel végétal** — ce que le scénario de la parcelle demande (pour un potager, son plan de culture : plants et graines), regroupé
 *   par origine (à acheter, à multiplier, à laisser venir). Une quantité saisie
 *   prime ; sinon elle est estimée depuis l'espacement et le plan, et dite telle — la ligne centrale d'une parcelle
 *   pérenne en série (`Materiel::quantitesScenario()`).
 * - **Eau** — le besoin annuel en trois scénarios de sol (Lopez), la ressource
 *   déclarée et ce qu'elle change, et **la fréquence d'arrosage que les tests de
 *   sol suggèrent** (infiltration, ressuyage — fiche 02 de l'étude du sol).
 *
 * Une donnée demandée sert ici : la ressource en eau (colonne `site`) décide du
 * stockage à prévoir ; le test de sol (`releve_sol`) règle le rythme d'arrosage.
 * Aucun calcul n'est un verdict : voir `BesoinEau` pour ce que valent les nombres.
 */

require __DIR__ . '/../src/autoload.php';
require __DIR__ . '/../src/Configuration.php';
require __DIR__ . '/../src/Database.php';

use Syntropia\BesoinEau;
use Syntropia\Configuration;
use Syntropia\Materiel;
use Syntropia\Role;
use Syntropia\Web\Authentification;
use Syntropia\Web\MaterielDuSite;
use Syntropia\Web\Parcelles;
use Syntropia\Web\ReleveSol;
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
$participantId = $sites->participantDuCompte($siteId, $compteId, $auth->nomAffiche() ?? 'Participant');
$peutEcrire = $sites->peutEcrireSur($siteId, $compteId);
$parcellesService = new Parcelles();
$releves = new ReleveSol();

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$nombre = static fn (float $v, int $dec = 0): string => number_format($v, $dec, ',', "\u{202F}");

$parcelles = $parcellesService->dUnSite($siteId);

// Quelle vue : une parcelle, ou tout le projet (la liste d'achats est commune, la réserve d'eau aussi).
$parcelle = null;
$demande = (int) ($_GET['parcelle'] ?? 0);
foreach ($parcelles as $p) {
    if ((int) $p['id'] === $demande) {
        $parcelle = $p;
        break;
    }
}
$tout = count($parcelles) > 1 && isset($_GET['tout']) && $parcelle === null;
if (!$tout) {
    $parcelle ??= $parcelles[0] ?? null;
}
$idP = $parcelle !== null ? (int) $parcelle['id'] : 0;
$portee = $tout ? $parcelles : ($parcelle !== null ? [$parcelle] : []);
$vueEau = ($_GET['onglet'] ?? '') === 'eau';
$retour = Configuration::base() . 'conception-materiel-et-eau.php?' . ($tout ? 'tout=1' : 'parcelle=' . $idP);

$erreurs = [];
$panneau = $parcelle !== null ? 'test' : 'eau';   // 'test' ou 'eau' : l'onglet du panneau Données
$ouvre = false;
$saisie = [];
$flash = $_SESSION['syntropia_flash_materiel'] ?? null;
unset($_SESSION['syntropia_flash_materiel']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $portee !== []) {
    $action = (string) ($_POST['action'] ?? '');
    $ouvre = true;
    $saisie = $_POST;
    $panneau = $action === 'eau' ? 'eau' : 'test';
    $vueEau = true;
    if (!$peutEcrire) {
        http_response_code(403);
        $erreurs['droit'] = 'Vous lisez ce projet : il ne se modifie pas depuis votre compte.';
    } elseif (!$auth->jetonValide($_POST['csrf'] ?? null)) {
        http_response_code(403);
        $erreurs['jeton'] = 'Le formulaire a expiré. Rechargez la page.';
    } elseif ($action === 'eau') {
        if ($sites->enregistrerEau($siteId, (string) ($_POST['ressource_eau'] ?? ''), (string) ($_POST['ressource_eau_detail'] ?? ''))) {
            $_SESSION['syntropia_flash_materiel'] = ['ok', 'La ressource en eau du site est enregistrée.'];
            header('Location: ' . $retour . '&onglet=eau');
            exit;
        }
        $erreurs['eau'] = 'Choisissez une ressource proposée, et un détail de 190 caractères au plus.';
    } elseif (in_array($action, ['releve', 'retirer_releve'], true) && $parcelle === null) {
        $erreurs['releve'] = 'Un test de sol se note sur une parcelle : choisissez-en une.';
    } elseif ($action === 'releve') {
        $erreurs = $releves->ajouter($idP, $compteId, $_POST);
        if ($erreurs === []) {
            $_SESSION['syntropia_flash_materiel'] = ['ok', 'Le test de sol est enregistré : le conseil d’arrosage en tient compte.'];
            header('Location: ' . $retour . '&onglet=eau');
            exit;
        }
    } elseif ($action === 'retirer_releve') {
        $releves->retirer((int) ($_POST['releve_id'] ?? 0), $idP);
        $_SESSION['syntropia_flash_materiel'] = ['ok', 'Le relevé est retiré.'];
        header('Location: ' . $retour . '&onglet=eau');
        exit;
    } else {
        http_response_code(400);
        $erreurs['action'] = 'Cette action n’est pas reconnue.';
    }
}

/* ------------------------------------------------------------------ le matériel */

// Le compte est partagé avec le Dossier Projet (`MaterielDuSite`) : l'écran et le papier disent la même liste.
['entrees' => $entrees, 'sansScenario' => $sansScenario, 'sansCulture' => $sansCulture] = (new MaterielDuSite())->entrees($siteId, $portee);
$depuisPlan = count(array_filter($entrees, static fn (array $e): bool => ($e['qte']['mode'] ?? null) === 'plan')) > 0;
$recap = Materiel::regrouper($entrees);
$synthese = Materiel::synthese($recap);
// Les plants de biomasse : ceux qui seront sacrifiés à la perturbation (chantier-espacement-des-services.md, Phase 4).
$aSacrifier = array_sum(array_column($recap, 'biomasse'));
$aPrecise = count(array_filter($recap, static fn (array $r): bool => $r['origine'] === ''));
$estimes = count(array_filter($recap, static fn (array $r): bool => $r['estime']));
$aPotager = isset($synthese['potager']);
$tousPotagers = $portee !== [] && count(array_filter($portee, static fn (array $p): bool => ($p['type'] ?? '') === 'potager')) === count($portee);

/* ------------------------------------------------------------------ l'eau */

$surface = 0.0;
$sansDimensions = [];
foreach ($portee as $p) {
    $s = BesoinEau::surfaceM2($p);
    $s === null ? $sansDimensions[] = (string) $p['code'] : $surface += $s;
}
$besoins = $surface > 0 ? BesoinEau::besoins($surface) : [];
$pointe = $surface > 0 ? BesoinEau::pointe($surface) : null;
$ressource = (string) ($site['ressource_eau'] ?? '');
$ressourceDetail = (string) ($site['ressource_eau_detail'] ?? '');

// Le conseil d'arrosage : le relevé le plus récent de chaque parcelle de la vue.
$conseils = [];
$recents = [];
foreach ($portee as $p) {
    $r = $releves->recents((int) $p['id']);
    if ((int) $p['id'] === $idP) {
        $recents = $r;
    }
    $conseils[(int) $p['id']] = ['parcelle' => $p, 'releve' => $r[0] ?? null, 'freq' => BesoinEau::frequence($r[0] ?? null)];
}
$vegetaux = static fn (int $n): string => $n > 1 ? 'végétaux' : 'végétal';
$dateFr = static fn (string $d): string => (new DateTimeImmutable($d))->format('d/m/Y');
$duree = static fn (?int $min): string => $min === null ? '' : ($min >= 90 ? $nombre($min / 60, 1) . ' h' : $min . ' min');

$nomAffiche = $auth->nomAffiche() ?? '';
$initiales = '';
foreach (preg_split('/\s+/', trim($nomAffiche)) ?: [] as $mot) {
    if ($mot !== '') {
        $initiales .= mb_strtoupper(mb_substr($mot, 0, 1));
    }
}
$initiales = mb_substr($initiales, 0, 2) ?: '?';
$val = static fn (string $cle, string $defaut = ''): string => $h($saisie[$cle] ?? $defaut);
$titrePage = $tout ? 'Tout le projet' : ($parcelle !== null ? $parcelle['code'] . ' — ' . $parcelle['nom'] : '');
$urlParcelle = static fn (array $p): string => 'conception-materiel-et-eau.php?parcelle=' . (int) $p['id'] . ($vueEau ? '&onglet=eau' : '');
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Matériel et eau — Syntropia</title>
<link rel="stylesheet" href="assets/css/gabarit.css?v=<?= (int) @filemtime(__DIR__ . '/assets/css/gabarit.css') ?>">
<style>
  /* pas de bandeau d'outils sur cette page : les tirettes se calent sur le seul bandeau général */
  :root{ --haut-bandeaux: 3.4rem; }

  /* le bureau centre son contenu : ici il remplit toute sa place, pour que les vues puissent
     se réduire au lieu de déborder */
  #bureau{ padding:.4rem .6rem; align-items:stretch; }
  .mv-centre{ width:100%; min-height:0; display:flex; flex-direction:column; gap:.45rem; padding:.3rem .4rem .2rem; overflow:hidden; }
  .mv-entete{ display:flex; align-items:center; gap:.7rem; flex-wrap:wrap; }
  .mv-titre{ font:600 1rem var(--titre); color:var(--accent-titre); margin:0; }
  .mv-badge{ font:500 .72rem var(--titre); color:var(--muted); border:1px solid var(--line); border-radius:1rem; padding:.1rem .55rem; }
  .mv-actions{ margin-left:auto; display:flex; gap:.5rem; }
  .mv-bouton{
    font:600 .8rem var(--titre); color:var(--bouton-encre); background:var(--accent); border:0; border-radius:.5rem;
    padding:.35rem .75rem; text-decoration:none; cursor:pointer;
  }
  .mv-bouton--calme{ background:transparent; color:var(--accent-titre); border:1px solid var(--accent); }
  .mv-message{ margin:0; padding:.4rem .7rem; border-radius:.5rem; font:400 .82rem/1.35 var(--texte);
    background:var(--accent-fond); border:1px solid var(--accent); color:var(--accent-titre); }
  .mv-message--alerte{ border-color:var(--alerte); color:var(--alerte); }
  .mv-message a{ color:inherit; font-weight:600; }

  /* les deux vues du bureau : deux onglets en CSS pur */
  .mv-radio{ position:absolute; opacity:0; pointer-events:none; }
  .mv-onglets{ display:flex; gap:.3rem; border-bottom:1px solid var(--line); }
  .mv-onglet{ padding:.3rem 1rem; cursor:pointer; border-radius:.5rem .5rem 0 0; font:500 .84rem var(--titre); color:var(--muted);
    border:1px solid transparent; border-bottom:0; }
  .mv-onglet:hover{ color:var(--ink); }
  .mv-centre:has(#v-mat:checked) label[for=v-mat], .mv-centre:has(#v-eau:checked) label[for=v-eau]{
    color:var(--accent-titre); background:var(--accent-fond); border-color:var(--line); font-weight:600; }
  .mv-vue{ display:none; flex:1; min-height:0; }
  .mv-centre:has(#v-mat:checked) .mv-vue-mat, .mv-centre:has(#v-eau:checked) .mv-vue-eau{ display:flex; flex-direction:column; gap:.5rem; }

  /* le matériel */
  .mv-synthese{ display:flex; flex-wrap:wrap; gap:.4rem; }
  .mv-puce-o{ font:500 .76rem var(--texte); color:var(--ink); border:1px solid var(--line); border-radius:.5rem; padding:.2rem .6rem; background:var(--surface); }
  .mv-puce-o b{ font:600 .8rem var(--titre); color:var(--accent-titre); }
  .mv-tableau{ width:100%; border-collapse:collapse; font:400 .84rem var(--texte); color:var(--ink); }
  .mv-tableau th{ text-align:left; font:600 .72rem var(--titre); text-transform:uppercase; letter-spacing:.05em; color:var(--muted); padding:.25rem .5rem; border-bottom:1px solid var(--line); }
  .mv-tableau td{ padding:.28rem .5rem; border-bottom:1px solid var(--line); }
  .mv-tableau td.q{ white-space:nowrap; font-variant-numeric:tabular-nums; }
  .mv-tableau .ori{ font:500 .72rem var(--titre); color:var(--muted); }
  .mv-tableau small{ color:var(--muted); font-size:.75em; }
  .mv-page-nav{ display:none; align-items:center; gap:.6rem; font:400 .78rem var(--texte); color:var(--muted); }
  .mv-page-nav.actif{ display:flex; }
  .mv-page-nav button{ font:600 .78rem var(--titre); color:var(--accent-titre); background:transparent; border:1px solid var(--accent); border-radius:.4rem; padding:.15rem .6rem; cursor:pointer; }
  .mv-page-nav button:disabled{ opacity:.4; cursor:default; }
  .mv-pied-note{ font:400 .74rem/1.4 var(--texte); color:var(--muted); margin:0; }

  /* l'eau : trois blocs côte à côte, empilés sur un écran étroit */
  .mv-eau{ display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:.6rem; align-items:start; font-size:14px; }
  /* sur écran étroit, un bloc à la fois : trois blocs empilés ne tiendraient pas, même réduits */
  .mv-sous{ display:none; }
  @media (max-width:60rem){
    .mv-eau{ grid-template-columns:1fr; }
    .mv-sous{ display:flex; gap:.3rem; }
    .mv-sous-onglet{ flex:1; text-align:center; padding:.25rem .2rem; cursor:pointer; border-radius:.4rem; font:500 .74rem var(--titre);
      color:var(--muted); border:1px solid var(--line); }
    .mv-vue-eau:has(#b-1:checked) label[for=b-1], .mv-vue-eau:has(#b-2:checked) label[for=b-2], .mv-vue-eau:has(#b-3:checked) label[for=b-3]{
      color:var(--accent-titre); background:var(--accent-fond); border-color:var(--accent); font-weight:600; }
    .mv-eau .mv-bloc{ display:none; }
    .mv-vue-eau:has(#b-1:checked) .mv-bloc:nth-child(1), .mv-vue-eau:has(#b-2:checked) .mv-bloc:nth-child(2),
    .mv-vue-eau:has(#b-3:checked) .mv-bloc:nth-child(3){ display:block; }
  }
  @media (max-height:38rem){ .mv-pied-note{ display:none; } }
  @media (max-height:38rem) and (max-width:40rem){ .mv-synthese{ display:none; } }
  .mv-bloc{ border:1px solid var(--line); border-radius:.6rem; padding:.5rem .7rem; background:var(--surface); min-width:0; }
  .mv-bloc h2{ font:600 .95em var(--titre); color:var(--accent-titre); margin:0 0 .3em; }
  .mv-bloc p{ margin:.25em 0; font:400 .9em/1.4 var(--texte); color:var(--ink); }
  .mv-bloc p.doux{ color:var(--muted); font-size:.82em; }
  .mv-bloc table{ width:100%; border-collapse:collapse; font:400 .86em var(--texte); color:var(--ink); }
  .mv-bloc th{ text-align:left; font:600 .78em var(--titre); color:var(--muted); padding:.15em .3em; }
  .mv-bloc td{ padding:.2em .3em; border-top:1px solid var(--line); vertical-align:top; }
  .mv-bloc td.q{ white-space:nowrap; font-variant-numeric:tabular-nums; text-align:right; }
  .mv-bloc a{ color:var(--accent-titre); font-weight:600; }
  .mv-cle{ font-weight:600; color:var(--ink); }
  .mv-alerte{ color:var(--alerte) !important; }
  .mv-vide-page{ margin:auto; max-width:24rem; text-align:center; font:400 .92rem/1.5 var(--texte); color:var(--muted); }
  .mv-vide-page a{ color:var(--accent-titre); font-weight:600; }
  @media (max-width:40rem){ .mv-centre{ padding-top:1.8rem; } }   /* la place de l'onglet « Informations » */

  /* le panneau Données */
  .mv-form label.mv-champ{ display:block; font:500 .78rem var(--titre); color:var(--ink); margin:0 0 .5rem; }
  .mv-form label.mv-champ small{ font:400 .68rem var(--texte); color:var(--muted); }
  .mv-form input[type=text], .mv-form input[type=number], .mv-form input[type=date], .mv-form select{
    display:block; width:100%; margin-top:.15rem; padding:.36rem .5rem; font:400 .84rem var(--texte); color:var(--ink);
    background:var(--surface); border:1px solid var(--line); border-radius:.45rem;
  }
  .mv-form input:focus, .mv-form select:focus{ outline:2px solid var(--accent); outline-offset:1px; }
  .mv-deux{ display:grid; grid-template-columns:1fr 1fr; gap:.5rem; }
  .mv-note{ font:400 .74rem/1.4 var(--texte); color:var(--muted); margin:.2rem 0 .6rem; }
  .mv-erreurs{ margin:0 0 .7rem; padding:.5rem .7rem; border:1px solid var(--alerte); border-radius:.5rem; color:var(--alerte); font:400 .8rem/1.35 var(--texte); }
  .mv-erreurs ul{ margin:.2rem 0 0 1rem; padding:0; }
  .mv-recent{ display:flex; gap:.5rem; align-items:center; justify-content:space-between; padding:.3rem 0; border-top:1px solid var(--line); font:400 .78rem/1.3 var(--texte); color:var(--ink); }
  .mv-recent form{ margin:0; }
  .mv-recent button{ font:600 .72rem var(--titre); color:var(--alerte); background:transparent; border:1px solid var(--alerte); border-radius:.4rem; padding:.1rem .5rem; cursor:pointer; }
  .mv-radio-p{ position:absolute; opacity:0; pointer-events:none; }
  .mv-onglets-p{ display:flex; gap:.3rem; margin-bottom:.7rem; border-bottom:1px solid var(--line); }
  .mv-onglet-p{ flex:1; text-align:center; padding:.35rem .3rem; cursor:pointer; border-radius:.5rem .5rem 0 0;
    font:500 .8rem var(--titre); color:var(--muted); border:1px solid transparent; border-bottom:0; }
  .pd-corps:has(#o-test:checked) label[for=o-test], .pd-corps:has(#o-eau:checked) label[for=o-eau]{
    color:var(--accent-titre); background:var(--accent-fond); border-color:var(--line); font-weight:600; }
  .mv-panneau{ display:none; }
  .pd-corps:has(#o-test:checked) .mv-p-test, .pd-corps:has(#o-eau:checked) .mv-p-eau{ display:block; }
</style>
</head>
<body<?= $ouvre ? ' data-ouvre="1"' : '' ?>>
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
      <div class="pg-entete">
        <a class="marque-nom" href="./">Syntropia</a>
      </div>
      <div class="pg-corps">
        <div class="pg-section">
          <p class="pg-titre pg-nomsite"><?= $h($site['nom']) ?></p>
          <ul class="pg-liste">
            <li><a href="#" class="a-venir" tabindex="-1" aria-disabled="true"><span class="pg-puce">⌂</span><span class="pg-libelle">Le site</span></a></li>
            <li><a href="conception-etude-site.php"><span class="pg-puce">▦</span><span class="pg-libelle">La carte</span></a></li>
          </ul>
        </div>
        <div class="pg-section">
          <p class="pg-titre">Phase projet</p>
          <!-- rempli par Gabarit.init() à partir de public/assets/js/etapes.js -->
          <ul class="pg-liste pg-phase-projet"></ul>
        </div>
        <div class="pg-section">
          <p class="pg-titre">Parcelles</p>
          <ul class="pg-liste">
<?php if (count($parcelles) > 1): ?>
            <li><a href="conception-materiel-et-eau.php?tout=1<?= $vueEau ? '&amp;onglet=eau' : '' ?>"<?= $tout ? ' class="active" aria-current="page"' : '' ?>><span class="pg-puce">▦</span><span class="pg-libelle">Tout le projet</span></a></li>
<?php endif; ?>
<?php foreach ($parcelles as $p): $estActive = !$tout && $parcelle !== null && (int) $p['id'] === $idP; ?>
            <li><a href="<?= $h($urlParcelle($p)) ?>"<?= $estActive ? ' class="active" aria-current="page"' : '' ?>><span class="pg-puce">≋</span><span class="pg-libelle"><?= $h($p['code']) ?> — <?= $h($p['nom']) ?></span></a></li>
<?php endforeach; ?>
            <li><a href="conception-parcelles.php?nouvelle=1"><span class="pg-puce">+</span><span class="pg-libelle">Nouvelle parcelle</span></a></li>
          </ul>
        </div>
<?php if ($parcelle !== null): ?>
        <div class="pg-section">
          <p class="pg-titre"><?= $h($parcelle['code']) ?> — <?= $h($parcelle['nom']) ?></p>
          <ul class="pg-liste">
            <li><a href="conception-parcelles.php?parcelle=<?= $idP ?>"><span class="pg-puce">▤</span><span class="pg-libelle">Le plan</span></a></li>
            <li><a href="conception-scenario.php?parcelle=<?= $idP ?>"><span class="pg-puce">≋</span><span class="pg-libelle">Le scénario</span></a></li>
            <li><a href="conception-materiel-et-eau.php?parcelle=<?= $idP ?>" class="active" aria-current="page"><span class="pg-puce">◍</span><span class="pg-libelle">Matériel et eau</span></a></li>
            <li><a href="#" class="a-venir" tabindex="-1" aria-disabled="true"><span class="pg-puce">✎</span><span class="pg-libelle">Le suivi</span></a></li>
          </ul>
        </div>
<?php endif; ?>
        <p class="pg-pied">Ce que la parcelle demandera : les plants à se procurer, et l'eau qu'il faudra leur donner.</p>
      </div>
    </nav>

    <main class="bureau" id="bureau">
<?php if ($portee === []): ?>
      <div class="mv-centre">
        <p class="mv-vide-page">Le matériel et l'eau se comptent parcelle par parcelle, et ce site n'a pas encore de parcelle.
          <a href="conception-parcelles.php?nouvelle=1">Ouvrir la première parcelle →</a></p>
      </div>
<?php else: ?>
      <div class="mv-centre">
        <input class="mv-radio" type="radio" name="vue" id="v-mat"<?= $vueEau ? '' : ' checked' ?>>
        <input class="mv-radio" type="radio" name="vue" id="v-eau"<?= $vueEau ? ' checked' : '' ?>>
        <div class="mv-entete">
          <h1 class="mv-titre">Le matériel et l'eau, <?= $h($titrePage) ?></h1>
<?php if ($surface > 0): ?>
          <span class="mv-badge"><?= $nombre($surface) ?> m² arrosés</span>
<?php endif; ?>
<?php if ($peutEcrire): ?>
          <span class="mv-actions">
            <a class="mv-bouton mv-bouton--calme" href="<?= $h($retour) ?>&amp;onglet=eau&amp;saisie=1" id="mv-saisir">Noter une donnée</a>
          </span>
<?php endif; ?>
        </div>
<?php if (is_array($flash)): ?>
        <p class="mv-message" role="status"><?= $h($flash[1]) ?></p>
<?php endif; ?>
<?php if ($erreurs !== []): ?>
        <p class="mv-message mv-message--alerte" role="alert">Votre saisie est à reprendre. Le détail est dans le panneau Données.</p>
<?php endif; ?>
        <div class="mv-onglets" role="tablist">
          <label class="mv-onglet" for="v-mat">Matériel végétal</label>
          <label class="mv-onglet" for="v-eau">Eau</label>
        </div>

        <!-- ============ le matériel ============ -->
        <section class="mv-vue mv-vue-mat" id="mv-mat">
<?php if ($recap === []): ?>
          <p class="mv-message">Aucun végétal n'est encore placé <?= $tout ? 'sur les parcelles' : 'sur cette parcelle' ?>. La liste se déduit <?= $parcelle !== null && ($parcelle['type'] ?? '') === 'potager' ? 'du plan de culture' : 'du scénario' ?>.
            <?php if ($parcelle !== null): ?><a href="conception-<?= ($parcelle['type'] ?? '') === 'potager' ? 'potager' : 'scenario' ?>.php?parcelle=<?= $idP ?>"><?= ($parcelle['type'] ?? '') === 'potager' ? 'Composer le plan de culture' : 'Composer le scénario' ?> →</a><?php endif; ?></p>
<?php else: ?>
          <div class="mv-synthese">
<?php foreach (Materiel::ORIGINES as $cle => $titre): if (!isset($synthese[$cle])) { continue; } $s = $synthese[$cle]; ?>
            <span class="mv-puce-o"><?= $h($titre) ?> · <b><?= $s['especes'] ?></b> <?= $vegetaux($s['especes']) ?>
              <?= $s['plants'] > 0 ? '· <b>' . ($s['incomplet'] ? '≥ ' : '') . $nombre((float) $s['plants']) . '</b> plants' : '' ?></span>
<?php endforeach; ?>
<?php if ($aSacrifier > 0): ?>
            <span class="mv-puce-o">Biomasse à sacrifier · <b><?= $nombre((float) $aSacrifier) ?></b> plants</span>
<?php endif; ?>
          </div>
          <div class="mv-zone-tableau" id="mv-zone">
            <table class="mv-tableau">
              <thead><tr><th>Végétal</th><th>Origine</th><th>Quantité</th><?= $tout ? '<th>Parcelles</th>' : '' ?></tr></thead>
              <tbody>
<?php foreach ($recap as $r): ?>
                <tr class="mv-ligne">
                  <td><?= $h($r['espece']) ?></td>
                  <td class="ori"><?= $h(Materiel::ORIGINES[$r['origine']] ?? '') ?></td>
                  <td class="q"><?php if ($r['n'] !== null): ?><?= $r['estime'] ? '≈ ' : '' ?><?= $nombre((float) $r['n']) ?><?= $r['estime'] ? ' <small>estimé</small>' : '' ?><?= $r['graines'] > 0 ? ' <small>· ≈ ' . $nombre((float) $r['graines']) . ' graines</small>' : '' ?>
                    <?php elseif ($r['connu'] > 0): ?>≥ <?= $nombre((float) $r['connu']) ?> <small>, d'autres quantités restent à préciser</small>
                    <?php else: ?><small>À déterminer</small><?php endif; ?></td>
<?php if ($tout): ?>
                  <td class="ori"><?= $h(implode(', ', $r['parcelles'])) ?></td>
<?php endif; ?>
                </tr>
<?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div class="mv-page-nav" id="mv-nav">
            <button type="button" id="mv-prec">← Précédents</button><span id="mv-page"></span><button type="button" id="mv-suiv">Suivants →</button>
          </div>
          <p class="mv-pied-note">
<?php if ($estimes > 0): ?>
            Sur la ligne centrale d'une parcelle pérenne, une quantité « estimée » se compte en série : les productions à leur espacement de production, les plantes de service vivaces et ligneuses dans les cases qui restent, à leur espacement de service. Ailleurs, et pour les annuelles semées en poquets, elle compte une ligne de plants par planche ou par bande de bordure. Une quantité saisie dans le scénario ou le plan de culture la remplace.
<?php else: ?>
            Les quantités sont celles du scénario. Sans quantité ni espacement, elles restent « à déterminer ».
<?php endif; ?>
<?php if ($depuisPlan): ?>
            Les plants posés sur les lignes du <b>plan</b> (bordures, lignes de service des planches) sont comptés tels quels, à l'espacement de chaque espèce dans Espèces, d'où vient aussi leur origine. Pour les bordures, c'est ce compte qui fait foi.
<?php endif; ?>
<?php if ($aPotager): ?>
            Pour les cultures du potager, la quantité est le nombre de <b>plants</b> saisi dans le plan de culture. Sans lui, l'atelier compte une ligne de plants sur la planche, à l'espacement de la culture. Pour une culture semée à l'abri, le nombre de <b>graines</b> est calculé avec un taux de réussite de 80 %, la convention de planification de Lopez (91 % pour un poireau, 50 % pour une aubergine dans son livre). Remplacez-le par vos propres levées. Une culture semée en place n'a pas de graines chiffrées.
<?php endif; ?>
<?php if ($aSacrifier > 0): ?>
            La biomasse à sacrifier compte les plants dont la fonction est la biomasse : ils seront taillés et rendus au sol. Théry en destine 70 à 80 % à l'implantation.
<?php endif; ?>
<?php if ($aPrecise > 0): ?>
            <?= $aPrecise ?> <?= $vegetaux($aPrecise) ?> sans origine. Indiquez-la dans le scénario : elle décide de ce qu'on achète.
<?php endif; ?>
<?php if ($sansScenario !== []): ?>
            Parcelles sans scénario : <?= $h(implode(', ', $sansScenario)) ?>.
<?php endif; ?>
<?php if ($sansCulture !== []): ?>
            Parcelles sans culture au plan : <?= $h(implode(', ', $sansCulture)) ?>.
<?php endif; ?>
          </p>
<?php endif; ?>
        </section>

        <!-- ============ l'eau ============ -->
        <section class="mv-vue mv-vue-eau" id="mv-eaupage">
<?php if ($surface <= 0): ?>
          <p class="mv-message mv-message--alerte">Le besoin en eau se calcule sur la surface. Donnez la longueur et la largeur
            <?= $tout ? 'des parcelles' : 'de la parcelle' ?> <a href="conception-parcelles.php<?= $parcelle !== null ? '?parcelle=' . $idP : '' ?>">sur son plan →</a></p>
<?php else: ?>
          <input class="mv-radio" type="radio" name="bloc" id="b-1">
          <input class="mv-radio" type="radio" name="bloc" id="b-2">
          <input class="mv-radio" type="radio" name="bloc" id="b-3" checked>
          <div class="mv-sous" role="tablist">
            <label class="mv-sous-onglet" for="b-1">Combien d'eau</label>
            <label class="mv-sous-onglet" for="b-2">Où la prendre</label>
            <label class="mv-sous-onglet" for="b-3">Fréquence</label>
          </div>
          <div class="mv-eau" id="mv-eau">
            <div class="mv-bloc">
              <h2>Combien d'eau</h2>
              <table>
                <thead><tr><th>Le sol est…</th><th class="q">Par an</th><th class="q">Mare creusée</th></tr></thead>
                <tbody>
<?php foreach ($besoins as $b): ?>
                  <tr>
                    <td><span class="mv-cle"><?= $h($b['titre']) ?></span></td>
                    <?php if ($b['m3_an'] !== null): ?>
                    <td class="q"><?= $nombre($b['m3_an'], 1) ?> m³</td><td class="q"><?= $nombre($b['mare_m3'], 1) ?> m³</td>
                    <?php else: ?>
                    <td class="q" colspan="2">Moins, non chiffré</td>
                    <?php endif; ?>
                  </tr>
<?php endforeach; ?>
                </tbody>
              </table>
<?php if ($pointe !== null): ?>
              <p>En pointe d'été : <span class="mv-cle"><?= $nombre($pointe['m3_jour'], 2) ?> m³ par jour</span>, soit <?= $nombre($pointe['m3_semaine'], 1) ?> m³ dans la semaine (5 mm/jour).</p>
<?php endif; ?>
              <p class="doux">La réserve à prévoir égale le besoin annuel. Une mare se creuse un tiers plus grande, car l'évaporation en prend le quart. Ce sont des ordres de grandeur pour dimensionner, tirés de Lopez (maraîchage d'annuelles). On arrose tout, passe-pieds compris. <?= $tousPotagers ? 'Un potager d’annuelles est le cas que décrit Lopez : ces nombres s’y appliquent pour les plantes, mais le climat reste à transposer.' : 'Une pérenne demande surtout l’arrosage de ses trois premières années.' ?></p>
<?php if ($sansDimensions !== []): ?>
              <p class="doux mv-alerte">Parcelles sans dimensions, non comptées : <?= $h(implode(', ', $sansDimensions)) ?>.</p>
<?php endif; ?>
            </div>

            <div class="mv-bloc">
              <h2>Où prendre l'eau</h2>
<?php if ($ressource === ''): ?>
              <p>La ressource en eau du site n'est pas encore dite.
<?php if ($peutEcrire): ?>
                 <a href="<?= $h($retour) ?>&amp;onglet=eau" data-ouvre-panneau="eau">La noter →</a>
<?php endif; ?></p>
              <p class="doux">Elle décide du stockage à prévoir : le réseau, un puits, une réserve ou rien. Sans eau, la régénération naturelle assistée devient la voie à suivre.</p>
<?php else: ?>
              <p><span class="mv-cle"><?= $h(BesoinEau::RESSOURCES[$ressource]) ?></span><?= $ressourceDetail !== '' ? ', ' . $h($ressourceDetail) : '' ?></p>
              <p><?= $h(BesoinEau::CONSEIL_RESSOURCE[$ressource]) ?></p>
              <p class="doux"><b>À la taille de perturbation</b>, on coupe au moment que disent les plantes, avant ou après une pluie, la première fois en juin. On arrose si la pluie ne vient pas. <?= $ressource === 'aucune' ? 'Sans eau à portée, la taille se cale sur la pluie.' : 'Prévoyez de pouvoir arroser à ces dates, en plus des plants.' ?> Le corpus ne donne pas de dose pour une perturbation : aucun volume n'est calculé ici.</p>
<?php if ($ressource !== 'reseau' && isset($besoins['intermediaire']) && $besoins['intermediaire']['m3_an'] !== null): ?>
              <p class="doux">La réserve à prévoir va de <?= $nombre($besoins['intermediaire']['reserve_m3'], 0) ?> m³ en sol intermédiaire à <?= $nombre($besoins['degrade']['reserve_m3'], 0) ?> m³ en sol dégradé.</p>
<?php endif; ?>
<?php if ($ressource === 'aucune'): $aTransplanter = count(array_filter($recap, static fn (array $r): bool => in_array($r['origine'], ['achat', 'multiplication', 'mixte'], true))); ?>
<?php if ($aTransplanter > 0): ?>
              <p class="mv-alerte"><?= $aTransplanter ?> <?= $vegetaux($aTransplanter) ?> à transplanter. Chacun demandera de l'eau pendant trois ans.</p>
<?php endif; ?>
<?php endif; ?>
<?php endif; ?>
            </div>

            <div class="mv-bloc">
              <h2>À quelle fréquence arroser</h2>
<?php if ($tout): ?>
              <table>
                <tbody>
<?php foreach ($conseils as $c): $f = $c['freq']; ?>
                  <tr>
                    <td><span class="mv-cle"><?= $h($c['parcelle']['code']) ?></span></td>
                    <?php if ($c['releve'] === null): ?>
                    <td>Pas de test de sol, <a href="conception-materiel-et-eau.php?parcelle=<?= (int) $c['parcelle']['id'] ?>&amp;onglet=eau">à noter</a></td>
                    <?php else: ?>
                    <td>Infiltration <?= $h($f['classe']['titre'] ?? 'non mesurée') ?></td>
                    <?php endif; ?>
                  </tr>
<?php endforeach; ?>
                </tbody>
              </table>
              <p class="doux">Le rythme est propre à chaque sol. Ouvrez une parcelle pour son conseil détaillé.</p>
<?php else: $c = $conseils[$idP]; $f = $c['freq']; ?>
<?php if ($c['releve'] === null): ?>
              <p>Cette parcelle n'a aucun test de sol. <b>L'infiltration</b> dit à quelle vitesse le sol boit, et donc comment l'arroser. Le test dure une heure, surtout faite d'attente.
<?php if ($peutEcrire): ?> <a href="<?= $h($retour) ?>&amp;onglet=eau" data-ouvre-panneau="test">Noter un test →</a><?php endif; ?></p>
              <p class="doux">L'été demande <?= $nombre($f['semaines'][0]['mm']) ?> mm par semaine en moyenne, et <?= $nombre($f['semaines'][1]['mm']) ?> mm en pointe. <a href="releve-vierge.php" target="_blank" rel="noopener">La fiche à imprimer ↗</a></p>
<?php else: ?>
              <p class="doux">Relevé du <?= $h($dateFr((string) $c['releve']['date'])) ?>, <?= $h($c['releve']['point']) ?></p>
<?php if ($f['classe'] !== null): ?>
              <p>Infiltration <span class="mv-cle"><?= $nombre((float) $c['releve']['infiltration_mm_h'], 1) ?> mm/h</span> : <?= $h($f['classe']['titre']) ?>, comme un sol de type <?= $h($f['classe']['texture']) ?>.</p>
<?php endif; ?>
<?php if ($f['ressuyage'] !== null): ?>
              <p>Ressuyage <span class="mv-cle"><?= $h($f['ressuyage']['titre']) ?></span> : <?= $h($f['ressuyage']['lecture']) ?>.</p>
<?php endif; ?>
<?php if ($f['mesure_a_refaire']): ?>
              <p class="mv-alerte">L'infiltration se mesure sur sol ressuyé, toujours dans le même état. Refaites la mesure quand le sol est ressuyé.</p>
<?php endif; ?>
              <table>
                <tbody>
<?php foreach ($f['semaines'] as $s): ?>
                  <tr>
                    <td><?= $h($s['titre']) ?></td>
                    <td><span class="mv-cle"><?= $nombre($s['mm']) ?> mm</span></td>
                  </tr>
<?php endforeach; ?>
                </tbody>
              </table>
<?php if (($f['classe']['famille'] ?? '') === 'drainant'): ?>
              <p class="doux">Ce sol boit vite : il demande des passages plus courts et plus fréquents.</p>
<?php elseif (($f['classe']['famille'] ?? '') === 'tampon'): ?>
              <p class="doux">Ce sol fait tampon : il supporte un arrosage long et espacé. Coupez chaque passage en plusieurs cycles courts, séparés de pauses, plutôt qu'un seul long arrosage.</p>
<?php endif; ?>
              <p class="doux">Versez au rythme où le sol boit<?= $f['classe'] !== null ? ' (' . $nombre((float) $c['releve']['infiltration_mm_h'], 1) . ' mm/h)' : '' ?> : au-delà, l'eau ruisselle.</p>
<?php endif; ?>
              <p class="doux"><b>Avant chaque passage</b>, glissez la main sous le mulch, puis enfoncez-la dans le sol. S'il est frais et à peine humide, on attend. S'il est sec, on arrose. Il ne doit jamais coller.</p>
<?php endif; ?>
            </div>
          </div>
<?php endif; ?>
        </section>
      </div>
<?php endif; ?>
    </main>

<?php if ($portee !== [] && $peutEcrire): ?>
    <div class="scrim" id="scrim-droit"></div>
    <button class="tirette" id="tirette" aria-expanded="false" aria-controls="panneau-droit"
            aria-label="Afficher le panneau Données">
      <svg class="tirette-triangle" viewBox="0 0 10 10" aria-hidden="true"><polygon points="1,1 9,5 1,9" fill="currentColor"/></svg>
      <span class="tirette-texte">Données</span>
    </button>
    <aside class="panneau-droit" id="panneau-droit" aria-hidden="true">
      <div class="pd-corps mv-form">
        <input class="mv-radio-p" type="radio" name="onglet" id="o-test"<?= $panneau === 'test' && $parcelle !== null ? ' checked' : '' ?><?= $parcelle === null ? ' disabled' : '' ?>>
        <input class="mv-radio-p" type="radio" name="onglet" id="o-eau"<?= $panneau === 'eau' || $parcelle === null ? ' checked' : '' ?>>
        <div class="mv-onglets-p" role="tablist">
<?php if ($parcelle !== null): ?>
          <label class="mv-onglet-p" for="o-test">Un test de sol</label>
<?php endif; ?>
          <label class="mv-onglet-p" for="o-eau">L'eau du site</label>
        </div>
<?php if ($erreurs !== []): ?>
        <div class="mv-erreurs" role="alert"><strong>Votre saisie est à reprendre.</strong>
          <ul><?php foreach ($erreurs as $e): ?><li><?= $h($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<?php if ($parcelle !== null): ?>
        <section class="mv-panneau mv-p-test">
          <p class="pd-titre">Un test de sol, <?= $h($parcelle['code']) ?></p>
          <p class="mv-note"><b>À quoi il sert.</b> Le test règle l'arrosage des trois premières années. Un sol qui boit vite demande des passages plus courts et plus fréquents. Un sol qui fait tampon supporte un arrosage long et espacé. La méthode est sur la fiche 02, <a href="releve-vierge.php" target="_blank" rel="noopener">à imprimer ↗</a>. Comptez de trente minutes à une heure.</p>
          <form method="post" action="conception-materiel-et-eau.php?parcelle=<?= $idP ?>">
            <input type="hidden" name="csrf" value="<?= $h($auth->jeton()) ?>">
            <input type="hidden" name="action" value="releve">
            <div class="mv-deux">
              <label class="mv-champ">Date du relevé
                <input type="date" name="date" required max="<?= date('Y-m-d') ?>" value="<?= $val('date', date('Y-m-d')) ?>">
              </label>
              <label class="mv-champ">Point <small>L'endroit dans la parcelle</small>
                <input type="text" name="point" maxlength="60" value="<?= $val('point') ?>" placeholder="Point 1">
              </label>
            </div>
            <label class="mv-champ">Vitesse d'infiltration stabilisée <small>En mm/h, au double anneau</small>
              <input type="number" name="infiltration_mm_h" min="0.1" max="2000" step="0.1" inputmode="decimal" value="<?= $val('infiltration_mm_h') ?>" placeholder="Ex. 15">
            </label>
            <div class="mv-deux">
              <label class="mv-champ">État du sol
                <select name="etat_humidite">
                  <option value="">Non dit</option>
<?php foreach (BesoinEau::ETATS as $etat): ?>
                  <option value="<?= $h($etat) ?>"<?= ($saisie['etat_humidite'] ?? '') === $etat ? ' selected' : '' ?>><?= $h($etat) ?></option>
<?php endforeach; ?>
                </select>
              </label>
              <label class="mv-champ">Jours depuis la pluie
                <input type="number" name="jours_depuis_pluie" min="0" max="60" step="1" value="<?= $val('jours_depuis_pluie') ?>" placeholder="Ex. 2">
              </label>
            </div>
            <p class="mv-note">Un sol est <b>ressuyé</b> quand l'eau de la dernière pluie a fini de s'égoutter : la terre est fraîche sans coller. C'est l'état où l'on mesure. L'état et les jours disent ensemble à quelle vitesse ce sol se ressuie.</p>
            <button type="submit" class="mv-bouton">Noter ce test</button>
          </form>
<?php if ($recents !== []): ?>
          <p class="pd-titre" style="margin-top:1rem">Déjà notés</p>
<?php foreach ($recents as $r): ?>
          <div class="mv-recent">
            <span><?= $h($dateFr((string) $r['date'])) ?> · <?= $h($r['point']) ?><br>
              <?= $r['infiltration_mm_h'] !== null ? $nombre((float) $r['infiltration_mm_h'], 1) . ' mm/h' : '' ?><?= $r['infiltration_mm_h'] !== null && $r['etat_humidite'] !== null ? ' · ' : '' ?><?= $h($r['etat_humidite'] ?? '') ?><?= $r['jours_depuis_pluie'] !== null ? ', ' . (int) $r['jours_depuis_pluie'] . ' j après la pluie' : '' ?></span>
            <form method="post" action="conception-materiel-et-eau.php?parcelle=<?= $idP ?>">
              <input type="hidden" name="csrf" value="<?= $h($auth->jeton()) ?>">
              <input type="hidden" name="action" value="retirer_releve">
              <input type="hidden" name="releve_id" value="<?= (int) $r['id'] ?>">
              <button type="submit" aria-label="Retirer ce relevé">Retirer</button>
            </form>
          </div>
<?php endforeach; ?>
          <p class="mv-note">Le relevé le plus récent règle le conseil d'arrosage.</p>
<?php endif; ?>
        </section>
<?php endif; ?>

        <section class="mv-panneau mv-p-eau">
          <p class="pd-titre">L'eau du site</p>
          <p class="mv-note"><b>À quoi elle sert.</b> La ressource dit quoi prévoir pour le stockage. Sans eau à portée, le système reste possible, plus lent et dépendant des pluies : on sème plutôt que de transplanter. La réponse vaut pour tout le projet.</p>
          <form method="post" action="conception-materiel-et-eau.php?<?= $tout ? 'tout=1' : 'parcelle=' . $idP ?>">
            <input type="hidden" name="csrf" value="<?= $h($auth->jeton()) ?>">
            <input type="hidden" name="action" value="eau">
            <label class="mv-champ">De l'eau, il y a…
              <select name="ressource_eau">
                <option value="">Pas encore dit</option>
<?php foreach (BesoinEau::RESSOURCES as $cle => $titre): $choisie = ($saisie['ressource_eau'] ?? $ressource) === $cle; ?>
                <option value="<?= $h($cle) ?>"<?= $choisie ? ' selected' : '' ?>><?= $h($titre) ?></option>
<?php endforeach; ?>
              </select>
            </label>
            <label class="mv-champ">Le détail <small>Débit, volume, distance…</small>
              <input type="text" name="ressource_eau_detail" maxlength="190" value="<?= $val('ressource_eau_detail', $ressourceDetail) ?>" placeholder="Ex. mare de 200 m³ à 80 m du potager">
            </label>
            <button type="submit" class="mv-bouton">Enregistrer</button>
          </form>
        </section>
      </div>
    </aside>
<?php endif; ?>

  </div>

  <div class="scrim" id="scrim-info"></div>
  <button class="tirette-info" id="tirette-info" aria-expanded="false" aria-controls="panneau-info"
          aria-label="Afficher les informations">
    <svg class="tirette-triangle" viewBox="0 0 10 10" aria-hidden="true"><polygon points="1,1 9,5 1,9" fill="currentColor"/></svg>
    <span class="tirette-texte">Informations</span>
  </button>
  <aside class="panneau-info" id="panneau-info" aria-hidden="true">
    <div class="pi-corps">
      <p>Cette étape prépare deux choses avant de planter : <strong>ce qu'il faudra se procurer</strong> et <strong>l'eau qu'il faudra donner</strong>.
         La liste des plants se déduit du scénario de chaque parcelle et des lignes posées sur son plan (bordures, lignes de service des planches).
         Le classement « à acheter, à multiplier soi-même, régénération naturelle » vient de l'origine que vous y avez notée.</p>
      <p><strong>Les besoins en eau</strong> [Lopez, <em>Les Jardins Sauvages</em>]. On n'arrose pas les légumes, on arrose la biologie du sol.
         Le besoin annuel va d'environ 750 L/m² en sol dégradé et année sèche à 300 L/m² en sol intermédiaire. En pointe d'été, il monte jusqu'à 5 mm par jour.
         Le sol structuré demande moins, mais l'ouvrage ne le chiffre pas. <strong>Ce sont des ordres de grandeur pour dimensionner.</strong> Ils viennent d'un
         maraîchage d'annuelles en Haute-Vienne. Pour une ligne pérenne, rien n'est chiffré, et les trois premières années sont celles qui comptent.
         Un sol jamais travaillé et toujours couvert économise l'eau, mais pas dès les premières années : il faudra arroser.</p>
      <p><strong>L'eau et la perturbation</strong> [Théry, ch. 12]. La taille est déjà un stress. Sans eau, une bonne part de son bénéfice se perd et la reprise ralentit.
         On la cale donc sur la pluie, et l'on arrose si la pluie ne vient pas. La formation Joala décrit une boucle : plus de densité et de perturbation déposent plus
         de biomasse, et le sol devient plus riche en matière organique et retient mieux l'eau. Une synthèse rapporte 20 L de stockage en plus par m² pour 1 % de matière
         organique supplémentaire. C'est un ordre de grandeur non recoupé, qui n'entre dans aucun calcul. Les premières années, c'est pourtant l'arrosage qui compte.</p>
      <p><strong>Les ouvrages de stockage</strong> [Lopez, pour environ 300 m³]. Un puits ou un forage coûte environ 5 000 €, pompe comprise. Une mare de 400 m³ creusés
         en stocke 300, pour environ 1 500 € de pelle mécanique en terre argileuse et environ 1 000 € de pompe. Le prix d'une cuve est très variable, et les cuves enterrées
         sont bien plus chères. L'auteur juge qu'une piscine convertie, alimentée par un puits et la récupération de pluie, est la meilleure solution. Un mètre carré de
         toiture recueille environ un mètre cube de pluie par an [de Looze].</p>
      <p><strong>La fréquence d'arrosage</strong> se règle sur le sol. L'<strong>infiltration</strong> dit à quelle vitesse il boit. Ses classes reprennent la table de
         l'annexe 2 de l'<em>Irrigation Manual</em> de la FAO : sable au-delà de 30 mm/h, limon sableux 20 à 30, limon 10 à 20, limon argileux 5 à 10, argile 1 à 5.
         Le <strong>ressuyage</strong> dit combien de temps le sol garde l'eau de la pluie. Encore détrempé deux jours après, il ne supporte pas d'être remouillé sans cesse.
         Déjà ressuyé au bout d'un jour, il se dessèche vite. Le corpus de l'atelier dit seulement qu'un sol qui boit vite demande des passages plus courts et plus
         fréquents, et qu'un sol qui fait tampon supporte un arrosage long et espacé. L'atelier ne calcule donc ni le nombre de passages ni la dose
         d'un passage. Le vrai juge reste votre main, avant chaque arrosage.</p>
      <p><strong>Quand arroser.</strong> On arrose tard le soir, la nuit ou très tôt le matin, et le matin par beau temps pour les plantes qui craignent l'humidité sur le
         feuillage. Les semaines qui suivent un semis de couvert sont les plus importantes : la graine ne doit pas se dessécher. L'excès d'eau a aussi ses dégâts :
         asphyxie, lessivage, salinisation.</p>
    </div>
  </aside>

  <footer class="pied">
    <form class="pied-recherche" onsubmit="return false;">
      <input type="search" placeholder="Rechercher dans le site…" aria-label="Rechercher dans le site">
      <button type="submit" aria-label="Rechercher">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="10.5" cy="10.5" r="6.5"/><line x1="15.5" y1="15.5" x2="21" y2="21"/></svg>
      </button>
    </form>
    <span class="pied-spacer"></span>
  </footer>

</div>

<script src="assets/js/etapes.js?v=<?= (int) @filemtime(__DIR__ . '/assets/js/etapes.js') ?>"></script>
<script src="assets/js/gabarit.js?v=<?= (int) @filemtime(__DIR__ . '/assets/js/gabarit.js') ?>"></script>
<script>
  Gabarit.init({
    site: <?= json_encode((string) $site['nom'], JSON_UNESCAPED_UNICODE) ?>,
    etape: 'conception-materiel-et-eau'<?php if ($portee !== []): ?>,
    contexte: <?= json_encode($titrePage, JSON_UNESCAPED_UNICODE) ?><?php endif; ?>
  });

  (function(){
    var tirette = document.getElementById('tirette');
    // Le panneau Données s'ouvre quand la page en a besoin : une saisie à reprendre, une donnée à noter.
    if (tirette && (document.body.dataset.ouvre === '1' || /[?&]saisie=1/.test(location.search))) tirette.click();
    // « La noter → » : ouvre le panneau sur le bon onglet.
    document.querySelectorAll('[data-ouvre-panneau]').forEach(function(a){
      a.addEventListener('click', function(ev){
        var radio = document.getElementById('o-' + a.dataset.ouvrePanneau);
        if (!tirette || !radio || radio.disabled) return;
        ev.preventDefault();
        radio.checked = true;
        if (tirette.getAttribute('aria-expanded') !== 'true') tirette.click();
      });
    });
    var saisir = document.getElementById('mv-saisir');
    if (saisir && tirette) saisir.addEventListener('click', function(ev){ ev.preventDefault(); tirette.click(); });

    /* Le bureau ne défile jamais.
       L'eau : trois blocs dont la taille se réduit jusqu'à tenir.
       Le matériel : la même chose, puis — si même réduite la liste déborde — des pages. */
    var centre = document.querySelector('.mv-centre');
    function deborde(){ return centre.scrollHeight > centre.clientHeight + 1; }

    function ajusterEau(){
      var z = document.getElementById('mv-eau');
      if (!z || !z.offsetParent) return;
      var t = 14;
      z.style.fontSize = t + 'px';
      while (deborde() && t > 8) { t -= 0.5; z.style.fontSize = t + 'px'; }
    }

    var lignes = Array.prototype.slice.call(document.querySelectorAll('.mv-ligne'));
    var page = 0, parPage = lignes.length;
    function afficher(){
      lignes.forEach(function(l, i){ l.style.display = (i >= page * parPage && i < (page + 1) * parPage) ? '' : 'none'; });
      var nav = document.getElementById('mv-nav');
      if (!nav) return;
      var pages = Math.max(1, Math.ceil(lignes.length / parPage));
      nav.classList.toggle('actif', pages > 1);
      document.getElementById('mv-page').textContent = 'Page ' + (page + 1) + ' sur ' + pages;
      document.getElementById('mv-prec').disabled = page === 0;
      document.getElementById('mv-suiv').disabled = page >= pages - 1;
    }
    function ajusterMateriel(){
      var zone = document.getElementById('mv-zone');
      if (!zone || !zone.offsetParent) return;
      var t = 14;
      zone.style.fontSize = t + 'px';
      page = 0; parPage = lignes.length; afficher();
      while (deborde() && t > 10) { t -= 0.5; zone.style.fontSize = t + 'px'; }
      // Toujours trop long : on pagine, en montrant autant de lignes que la place le permet.
      if (deborde()) {
        var n = lignes.length;
        while (n > 1) {
          n--; parPage = n; afficher();
          if (!deborde()) break;
        }
      }
    }
    function tout(){ ajusterEau(); ajusterMateriel(); }
    var prec = document.getElementById('mv-prec'), suiv = document.getElementById('mv-suiv');
    if (prec) prec.addEventListener('click', function(){ page = Math.max(0, page - 1); afficher(); });
    if (suiv) suiv.addEventListener('click', function(){ page++; afficher(); });
    document.querySelectorAll('.mv-radio').forEach(function(r){ r.addEventListener('change', tout); });
    document.querySelectorAll('[name=bloc]').forEach(function(r){ r.addEventListener('change', tout); });
    if (centre) {
      tout();
      window.addEventListener('resize', tout);
      // Les polices arrivent après le premier calcul : leur métrique change la hauteur des lignes.
      if (document.fonts && document.fonts.ready) document.fonts.ready.then(tout);
    }
  })();
</script>
</body>
</html>
