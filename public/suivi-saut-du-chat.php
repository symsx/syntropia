<?php

declare(strict_types=1);

/**
 * Saut du chat — le constat d'une parcelle, et ce qui le rend possible.
 *
 * Étape 3 de Suivi. Deux vues, en onglets CSS purs :
 * - **Les constats** : datés, avec — quand on l'a mesuré — la pousse d'une plante sur une durée, à côté
 *   de celle d'un témoin conduit classiquement dans le même sol. Le saut du chat est un EFFET, il se
 *   reconnaît en comparant.
 * - **Ce qui le rend possible** : les conditions, lues dans le journal des perturbations et dans le
 *   scénario (`SautDuChat::conditions()`). Elles disent ce que l'atelier sait, ce qu'il faut regarder et
 *   ce que personne n'a noté — jamais « échec ». C'est la vue par défaut tant que le saut du chat n'est
 *   pas visible.
 *
 * Voir `chantier-saut-du-chat.md`, Phase 2. Le conseil « planter davantage » est celui des directives, sans
 * la cause que le corpus ne donne pas : la densité ne déclenche pas le saut du chat, la taille si.
 */

require __DIR__ . '/../src/autoload.php';
require __DIR__ . '/../src/Configuration.php';
require __DIR__ . '/../src/Database.php';

use Syntropia\Configuration;
use Syntropia\Documentation;
use Syntropia\Perturbation;
use Syntropia\Role;
use Syntropia\SautDuChat;
use Syntropia\Web\Authentification;
use Syntropia\Web\Constats;
use Syntropia\Web\Interventions;
use Syntropia\Web\Parcelles;
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
$peutEcrire = $sites->peutEcrireSur($siteId, $compteId);
$parcellesService = new Parcelles();
$constatsService = new Constats();
$termes = Documentation::glossaire(__DIR__ . '/../contenu/glossaire.md');

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$dateFr = static fn (string $d): string => (new DateTimeImmutable($d))->format('d/m/Y');
$nombre = static fn (float $v, int $dec = 0): string => number_format($v, $dec, ',', "\u{202F}");

$parcelles = $parcellesService->dUnSite($siteId);
$parcelle = null;
$demande = (int) ($_GET['parcelle'] ?? $_POST['parcelle'] ?? 0);
foreach ($parcelles as $p) {
    if ((int) $p['id'] === $demande) {
        $parcelle = $p;
        break;
    }
}
$parcelle ??= $parcelles[0] ?? null;
$idP = $parcelle !== null ? (int) $parcelle['id'] : 0;
$retour = Configuration::base() . 'suivi-saut-du-chat.php?parcelle=' . $idP;

$erreurs = [];
$ouvre = false;
$onglet = 'constat';       // l'onglet du panneau Données : « constat » ou « plantation »
$saisie = null;
$editionId = 0;
$editionVersion = '';
$flash = $_SESSION['syntropia_flash_saut'] ?? null;
unset($_SESSION['syntropia_flash_saut']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $parcelle !== null) {
    $action = (string) ($_POST['action'] ?? '');
    $ouvre = true;
    $saisie = $_POST;
    $editionId = (int) ($_POST['constat_id'] ?? 0);
    $editionVersion = (string) ($_POST['version'] ?? '');
    $onglet = $action === 'plantation' ? 'plantation' : 'constat';
    if (!$peutEcrire) {
        http_response_code(403);
        $erreurs['droit'] = 'Vous lisez ce projet : il ne se modifie pas depuis votre compte.';
    } elseif (!$auth->jetonValide($_POST['csrf'] ?? null)) {
        http_response_code(403);
        $erreurs['jeton'] = 'Formulaire expiré — rechargez la page.';
    } elseif ($action === 'ajouter') {
        $erreurs = $constatsService->ajouter($idP, $compteId, $_POST);
        if ($erreurs === []) {
            $_SESSION['syntropia_flash_saut'] = ['ok', 'Le constat est noté.'];
            header('Location: ' . $retour);
            exit;
        }
    } elseif ($action === 'modifier') {
        $erreurs = $constatsService->modifier($editionId, $idP, $editionVersion, $_POST);
        if ($erreurs === []) {
            $_SESSION['syntropia_flash_saut'] = ['ok', 'Le constat est mis à jour.'];
            header('Location: ' . $retour);
            exit;
        }
    } elseif ($action === 'retirer') {
        $_SESSION['syntropia_flash_saut'] = $constatsService->retirer($editionId, $idP)
            ? ['ok', 'Le constat est retiré.']
            : ['ok', 'Ce constat n’est plus dans la liste.'];
        header('Location: ' . $retour);
        exit;
    } elseif ($action === 'plantation') {
        $erreurs = $parcellesService->enregistrerPlantation($idP, $siteId, (string) ($_POST['date_plantation'] ?? ''), (string) ($_POST['fin_plantation'] ?? ''));
        if ($erreurs === []) {
            $_SESSION['syntropia_flash_saut'] = ['ok', trim((string) ($_POST['date_plantation'] ?? '')) !== ''
                ? 'La campagne de plantation est enregistrée.' : 'Les dates de plantation sont effacées.'];
            header('Location: ' . $retour);
            exit;
        }
    } else {
        http_response_code(400);
        $erreurs['action'] = 'Cette action n’est pas reconnue.';
    }
}

$constats = $parcelle !== null ? $constatsService->dUneParcelle($idP) : [];

$edition = null;
if ($saisie === null && $peutEcrire && $parcelle !== null && isset($_GET['modifier'])) {
    $edition = $constatsService->obtenir((int) $_GET['modifier'], $idP);
    if ($edition !== null) {
        $editionId = (int) $edition['id'];
        $editionVersion = Constats::version($edition);
        $ouvre = true;
    }
}
if ($saisie === null && $peutEcrire && ($_GET['saisie'] ?? '') === 'plantation') {
    $ouvre = true;
    $onglet = 'plantation';
}

// La note d'avant : un oui/non et un champ libre, jamais supprimés.
$ancienne = [];
if ($parcelle !== null && $constats === []) {
    $r = (new Reponses())->pourParticipant($participantId);
    if (trim((string) ($r['saut_du_chat_observations'] ?? '')) !== '') {
        $ancienne['observations'] = trim((string) $r['saut_du_chat_observations']);
    }
    if (($r['saut_du_chat_visible'] ?? '') === 'oui') {
        $ancienne['visible'] = 'oui';
    }
}
$depuisNote = $peutEcrire && $ancienne !== [] && ($_GET['depuis'] ?? '') === 'note' && $saisie === null && $edition === null;
if ($depuisNote) {
    $ouvre = true;
}

// Ce que l'atelier sait déjà de cette parcelle.
$interventions = $parcelle !== null ? (new Interventions())->dUneParcelle($idP) : [];
$lignes = $parcelle !== null ? $parcellesService->scenario($idP) : [];
$aujourdhui = new DateTimeImmutable('today');
$datePlantation = $parcelle !== null ? (string) ($parcelle['date_plantation'] ?? '') : '';
$finPlantation = $parcelle !== null ? (string) ($parcelle['fin_plantation'] ?? '') : '';
$preparation = $parcelle !== null ? (new Reponses())->preparationDuSite($siteId, $participantId) : [];
$conditions = $parcelle !== null ? SautDuChat::conditions($interventions, $lignes, $aujourdhui, $datePlantation, $finPlantation, $preparation) : [];
$derniere = $constats[0] ?? null;
$visibleMaintenant = $derniere !== null && $derniere['visible'] === 'oui';
$joursPlantee = $datePlantation !== '' ? Perturbation::joursDepuis($datePlantation, $aujourdhui) : null;

$vue = (string) ($_GET['vue'] ?? '');
if (!in_array($vue, ['constats', 'conditions'], true)) {
    $vue = $visibleMaintenant ? 'constats' : 'conditions';
}

// Les valeurs du formulaire du constat.
$f = ['date' => date('Y-m-d'), 'visible' => 'pas_encore', 'vegetal' => '', 'pousse_cm' => '', 'sur_jours' => '',
    'temoin_pousse_cm' => '', 'temoin_decrit' => '', 'notes' => ''];
if ($saisie !== null) {
    foreach ($f as $k => $_) {
        $f[$k] = (string) ($saisie[$k] ?? '');
    }
} elseif ($edition !== null) {
    foreach ($f as $k => $_) {
        $f[$k] = (string) ($edition[$k] ?? '');
    }
} elseif ($depuisNote) {
    $f['visible'] = ($ancienne['visible'] ?? '') === 'oui' ? 'oui' : 'pas_encore';
    $f['notes'] = $ancienne['observations'] ?? '';
}
$dateP = $saisie !== null && $onglet === 'plantation' ? (string) ($saisie['date_plantation'] ?? '') : $datePlantation;
$finP = $saisie !== null && $onglet === 'plantation' ? (string) ($saisie['fin_plantation'] ?? '') : $finPlantation;

$nomAffiche = $auth->nomAffiche() ?? '';
$initiales = '';
foreach (preg_split('/\s+/', trim($nomAffiche)) ?: [] as $mot) {
    if ($mot !== '') {
        $initiales .= mb_strtoupper(mb_substr($mot, 0, 1));
    }
}
$initiales = mb_substr($initiales, 0, 2) ?: '?';
$titrePage = $parcelle !== null ? $parcelle['code'] . ' — ' . $parcelle['nom'] : '';
$symboles = [SautDuChat::OK => ['✓', 'en place'], SautDuChat::ATTENTION => ['!', 'à regarder'], SautDuChat::VIDE => ['○', 'rien de noté']];
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

  #bureau{ padding:.4rem .6rem; align-items:stretch; }
  .sd-centre{ width:100%; min-height:0; display:flex; flex-direction:column; gap:.5rem; padding:.3rem .4rem .2rem; overflow:hidden; }
  .sd-entete{ display:flex; align-items:center; gap:.7rem; flex-wrap:wrap; }
  .sd-titre{ font:600 1rem var(--titre); color:var(--accent-titre); margin:0; }
  .sd-badge{ font:500 .72rem var(--titre); color:var(--muted); border:1px solid var(--line); border-radius:1rem; padding:.1rem .55rem; }
  .sd-actions{ margin-left:auto; display:flex; gap:.5rem; }
  .sd-bouton{
    font:600 .8rem var(--titre); color:var(--bouton-encre); background:var(--accent); border:0; border-radius:.5rem;
    padding:.35rem .75rem; text-decoration:none; cursor:pointer;
  }
  .sd-bouton--calme{ background:transparent; color:var(--accent-titre); border:1px solid var(--accent); }
  .sd-message{ margin:0; padding:.4rem .7rem; border-radius:.5rem; font:400 .82rem/1.35 var(--texte);
    background:var(--accent-fond); border:1px solid var(--accent); color:var(--accent-titre); }
  .sd-message--alerte{ border-color:var(--alerte); color:var(--alerte); }
  .sd-message a{ color:inherit; font-weight:600; }
  .sd-temps{ margin:0; font:400 .8rem/1.4 var(--texte); color:var(--muted); }
  .sd-temps a{ color:var(--accent-titre); font-weight:600; }

  /* les deux vues : deux onglets en CSS pur */
  .sd-radio{ position:absolute; opacity:0; pointer-events:none; }
  .sd-onglets{ display:flex; gap:.3rem; border-bottom:1px solid var(--line); }
  .sd-onglet{ padding:.3rem 1rem; cursor:pointer; border-radius:.5rem .5rem 0 0; font:500 .84rem var(--titre); color:var(--muted);
    border:1px solid transparent; border-bottom:0; }
  .sd-onglet:hover{ color:var(--ink); }
  .sd-centre:has(#v-cond:checked) label[for=v-cond], .sd-centre:has(#v-const:checked) label[for=v-const]{
    color:var(--accent-titre); background:var(--accent-fond); border-color:var(--line); font-weight:600; }
  .sd-vue{ display:none; flex:1; min-height:0; }
  .sd-centre:has(#v-cond:checked) .sd-vue-cond, .sd-centre:has(#v-const:checked) .sd-vue-const{ display:flex; flex-direction:column; gap:.4rem; }

  /* les conditions — en em : le réglage de la zone s'y applique */
  .sd-conds{ list-style:none; margin:0; padding:0; font-size:15px; }
  .sd-cond{ display:grid; grid-template-columns:1.5em 1fr; gap:.15em .6em; padding:.4em .2em; border-bottom:1px solid var(--line); }
  .sd-cond:last-child{ border-bottom:0; }
  .sd-sym{ grid-row:1 / span 2; font:700 1.05em var(--titre); text-align:center; line-height:1.3; }
  .sd-cond--ok .sd-sym{ color:var(--accent-titre); }
  .sd-cond--attention .sd-sym{ color:var(--alerte); }
  .sd-cond--vide .sd-sym{ color:var(--muted); }
  .sd-cond h2{ margin:0; font:600 .95em var(--titre); color:var(--ink); }
  .sd-cond p{ margin:0; font:400 .86em/1.4 var(--texte); color:var(--ink); }
  .sd-cond p small{ color:var(--muted); font-size:.85em; }
  .sd-rien{ position:absolute; width:1px; height:1px; overflow:hidden; clip:rect(0 0 0 0); }
  .sd-si-tarde{ margin:0; font:400 .8rem/1.4 var(--texte); color:var(--muted); }

  /* les constats */
  .sd-zone{ font-size:15px; }
  .sd-tableau{ width:100%; border-collapse:collapse; font:400 .86em var(--texte); color:var(--ink); }
  .sd-tableau th{ text-align:left; font:600 .72rem var(--titre); text-transform:uppercase; letter-spacing:.05em; color:var(--muted); padding:.25rem .5rem; border-bottom:1px solid var(--line); }
  .sd-tableau td{ padding:.28rem .5rem; border-bottom:1px solid var(--line); vertical-align:top; }
  .sd-tableau td.d{ white-space:nowrap; font-variant-numeric:tabular-nums; }
  .sd-tableau td a{ color:var(--accent-titre); font-weight:600; text-decoration:none; }
  .sd-tableau td a:hover{ text-decoration:underline; }
  .sd-tableau .doux{ color:var(--muted); font-size:.85em; }
  .sd-tableau .rapport{ font-weight:600; color:var(--accent-titre); white-space:nowrap; }
  .sd-tableau .notes{ max-width:16rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .sd-page-nav{ display:none; align-items:center; gap:.6rem; font:400 .78rem var(--texte); color:var(--muted); }
  .sd-page-nav.actif{ display:flex; }
  .sd-page-nav button{ font:600 .78rem var(--titre); color:var(--accent-titre); background:transparent; border:1px solid var(--accent); border-radius:.4rem; padding:.15rem .6rem; cursor:pointer; }
  .sd-page-nav button:disabled{ opacity:.4; cursor:default; }
  .sd-vide{ margin:auto; max-width:26rem; text-align:center; font:400 .92rem/1.5 var(--texte); color:var(--muted); }
  .sd-vide a{ color:var(--accent-titre); font-weight:600; }
  .sd-ancienne{ margin:0; padding:.5rem .7rem; border:1px dashed var(--line); border-radius:.5rem; font:400 .8rem/1.4 var(--texte); color:var(--ink); }
  .sd-ancienne b{ font:600 .78rem var(--titre); color:var(--muted); }
  .sd-ancienne a{ color:var(--accent-titre); font-weight:600; }
  .sd-tableau .sous-date{ display:none; font-size:.85em; color:var(--muted); font-weight:400; }
  @media (max-width:60rem){ .sd-tableau .c-notes{ display:none; } }
  @media (max-width:46rem){ .sd-tableau .c-temoin{ display:none; } }
  @media (max-width:40rem){ .sd-centre{ padding-top:1.8rem; } .sd-tableau th, .sd-tableau td{ padding-left:.3rem; padding-right:.3rem; } }
  @media (max-width:26rem){ .sd-tableau .c-plante{ display:none; } .sd-tableau .sous-date{ display:block; } }
  @media (max-height:38rem){ .sd-temps, .sd-si-tarde{ display:none; } }

  /* le panneau Données */
  .sd-form label.sd-champ{ display:block; font:500 .78rem var(--titre); color:var(--ink); margin:0 0 .5rem; }
  .sd-form label.sd-champ small{ font:400 .68rem var(--texte); color:var(--muted); }
  .sd-form input[type=text], .sd-form input[type=number], .sd-form input[type=date], .sd-form textarea{
    display:block; width:100%; margin-top:.15rem; padding:.36rem .5rem; font:400 .84rem var(--texte); color:var(--ink);
    background:var(--surface); border:1px solid var(--line); border-radius:.45rem;
  }
  .sd-form textarea{ resize:vertical; min-height:3.2rem; }
  .sd-form input:focus, .sd-form textarea:focus{ outline:2px solid var(--accent); outline-offset:1px; }
  .sd-deux{ display:grid; grid-template-columns:1fr 1fr; gap:.5rem; }
  .sd-note{ font:400 .74rem/1.4 var(--texte); color:var(--muted); margin:.2rem 0 .6rem; }
  .sd-choix{ display:flex; flex-direction:column; gap:.2rem; margin:0 0 .6rem; padding:0; border:0; }
  .sd-choix legend{ font:500 .78rem var(--titre); color:var(--ink); padding:0; margin-bottom:.2rem; }
  .sd-choix label{ display:flex; align-items:center; gap:.4rem; font:400 .84rem var(--texte); color:var(--ink); }
  .sd-erreurs{ margin:0 0 .7rem; padding:.5rem .7rem; border:1px solid var(--alerte); border-radius:.5rem; color:var(--alerte); font:400 .8rem/1.35 var(--texte); }
  .sd-erreurs ul{ margin:.2rem 0 0 1rem; padding:0; }
  .sd-retrait{ margin-top:1rem; padding-top:.6rem; border-top:1px solid var(--line); }
  .sd-retrait button{ font:600 .74rem var(--titre); color:var(--alerte); background:transparent; border:1px solid var(--alerte); border-radius:.4rem; padding:.2rem .6rem; cursor:pointer; }
  .sd-lien{ font:500 .76rem var(--titre); color:var(--accent-titre); }
  .sd-radio-p{ position:absolute; opacity:0; pointer-events:none; }
  .sd-onglets-p{ display:flex; gap:.3rem; margin-bottom:.7rem; border-bottom:1px solid var(--line); }
  .sd-onglet-p{ flex:1; text-align:center; padding:.35rem .3rem; cursor:pointer; border-radius:.5rem .5rem 0 0;
    font:500 .8rem var(--titre); color:var(--muted); border:1px solid transparent; border-bottom:0; }
  .pd-corps:has(#o-constat:checked) label[for=o-constat], .pd-corps:has(#o-plantation:checked) label[for=o-plantation]{
    color:var(--accent-titre); background:var(--accent-fond); border-color:var(--line); font-weight:600; }
  .sd-panneau{ display:none; }
  .pd-corps:has(#o-constat:checked) .sd-p-constat, .pd-corps:has(#o-plantation:checked) .sd-p-plantation{ display:block; }
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
          <p class="pg-titre">Phase projet</p>
          <ul class="pg-liste pg-phase-projet"></ul>
        </div>
        <div class="pg-section">
          <p class="pg-titre">Suivi</p>
          <ul class="pg-liste pg-etapes-phase"></ul>
        </div>
        <div class="pg-section">
          <p class="pg-titre">Parcelles</p>
          <ul class="pg-liste">
<?php foreach ($parcelles as $p): $estActive = $parcelle !== null && (int) $p['id'] === $idP; ?>
            <li><a href="suivi-saut-du-chat.php?parcelle=<?= (int) $p['id'] ?>"<?= $estActive ? ' class="active" aria-current="page"' : '' ?>><span class="pg-puce">≋</span><span class="pg-libelle"><?= $h($p['code']) ?> — <?= $h($p['nom']) ?></span></a></li>
<?php endforeach; ?>
            <li><a href="conception-parcelles.php?nouvelle=1"><span class="pg-puce">+</span><span class="pg-libelle">Nouvelle parcelle</span></a></li>
          </ul>
        </div>
        <p class="pg-pied">Troisième étape de Suivi : reconnaître le saut du chat, et voir ce qui le rend possible.</p>
      </div>
    </nav>

    <main class="bureau" id="bureau">
<?php if ($parcelle === null): ?>
      <div class="sd-centre">
        <p class="sd-vide">Le saut du chat se constate parcelle par parcelle, et ce site n'en a pas encore.
          <a href="conception-parcelles.php?nouvelle=1">Ouvrir la première parcelle →</a></p>
      </div>
<?php else: ?>
      <div class="sd-centre">
        <input class="sd-radio" type="radio" name="vue" id="v-cond"<?= $vue === 'conditions' ? ' checked' : '' ?>>
        <input class="sd-radio" type="radio" name="vue" id="v-const"<?= $vue === 'constats' ? ' checked' : '' ?>>
        <div class="sd-entete">
          <h1 class="sd-titre">Saut du chat — <?= $h($titrePage) ?></h1>
<?php if ($derniere !== null): ?>
          <span class="sd-badge"><?= $visibleMaintenant ? 'Visible au dernier constat' : 'Pas encore visible' ?></span>
<?php endif; ?>
<?php if ($peutEcrire): ?>
          <span class="sd-actions">
            <a class="sd-bouton sd-bouton--calme" href="<?= $h($retour) ?>&amp;saisie=1" id="sd-saisir">Noter un constat</a>
          </span>
<?php endif; ?>
        </div>
<?php if (is_array($flash)): ?>
        <p class="sd-message" role="status"><?= $h($flash[1]) ?></p>
<?php endif; ?>
<?php if ($erreurs !== []): ?>
        <p class="sd-message sd-message--alerte" role="alert">Votre saisie est à reprendre — le détail est dans le panneau Données.</p>
<?php endif; ?>
<?php if ($joursPlantee !== null): ?>
        <p class="sd-temps"><?= $finPlantation !== '' ? 'Planté du ' . $h($dateFr($datePlantation)) . ' au ' . $h($dateFr($finPlantation)) . ' ; point zéro il y a ' : 'Point zéro le ' . $h($dateFr($datePlantation)) . ', il y a ' ?><?= $joursPlantee ?> jour<?= $joursPlantee > 1 ? 's' : '' ?>.
          Dans la formation, le saut du chat vient d'ordinaire dès les premiers mois, dès la première année — un repère, pas une échéance.
<?php if ($peutEcrire): ?>
          <a href="<?= $h($retour) ?>&amp;saisie=plantation" data-ouvre-panneau="plantation">Changer les dates</a>
<?php endif; ?></p>
<?php elseif ($peutEcrire): ?>
        <p class="sd-temps">Les dates de plantation situent le projet dans son film et disent depuis quand on attend le saut du chat.
          <a href="<?= $h($retour) ?>&amp;saisie=plantation" data-ouvre-panneau="plantation">Les noter →</a></p>
<?php endif; ?>

        <div class="sd-onglets" role="tablist">
          <label class="sd-onglet" for="v-cond">Ce qui le rend possible</label>
          <label class="sd-onglet" for="v-const">Les constats<?= $constats !== [] ? ' (' . count($constats) . ')' : '' ?></label>
        </div>

        <!-- ============ les conditions ============ -->
        <section class="sd-vue sd-vue-cond" id="sd-cond-vue">
          <div id="sd-cond-zone">
            <ul class="sd-conds" id="sd-conds">
<?php foreach ($conditions as $cond): [$sym, $lib] = $symboles[$cond['etat']]; ?>
              <li class="sd-cond sd-cond--<?= $h($cond['etat']) ?> sd-item-cond">
                <span class="sd-sym" aria-hidden="true"><?= $sym ?></span>
                <h2><?= $h($cond['titre']) ?><span class="sd-rien"> — <?= $h($lib) ?></span></h2>
                <p><?= $h($cond['detail']) ?> <small>[<?= $h($cond['source']) ?>]</small></p>
              </li>
<?php endforeach; ?>
            </ul>
          </div>
          <div class="sd-page-nav" id="sd-nav-cond">
            <button type="button" id="sd-prec-cond">← Précédentes</button><span id="sd-page-cond"></span><button type="button" id="sd-suiv-cond">Suivantes →</button>
          </div>
<?php if (!$visibleMaintenant): ?>
          <p class="sd-si-tarde">Rien d'anormal à attendre — le saut du chat vient avec le temps. En attendant, planter davantage peut aider, selon la période.</p>
<?php endif; ?>
        </section>

        <!-- ============ les constats ============ -->
        <section class="sd-vue sd-vue-const" id="sd-const-vue">
<?php if ($ancienne !== []): ?>
          <p class="sd-ancienne"><b>Votre note d'avant le constat</b> —
            <?= isset($ancienne['visible']) ? 'Le saut du chat était noté visible. ' : '' ?><?= isset($ancienne['observations']) ? 'Observations : ' . $h($ancienne['observations']) . '.' : '' ?>
<?php if ($peutEcrire): ?>
            <a href="<?= $h($retour) ?>&amp;depuis=note">En faire un constat →</a>
<?php endif; ?>
          </p>
<?php endif; ?>
<?php if ($constats === []): ?>
<?php if ($ancienne === []): ?>
          <p class="sd-vide">Aucun constat n'est encore noté sur cette parcelle. Le saut du chat se reconnaît en comparant : notez la pousse d'une plante sur quelques jours, à côté d'un témoin.
<?php if ($peutEcrire): ?>
            <a href="<?= $h($retour) ?>&amp;saisie=1" id="sd-saisir-vide">Noter le premier →</a>
<?php endif; ?></p>
<?php endif; ?>
<?php else: ?>
          <div class="sd-zone" id="sd-const-zone">
            <table class="sd-tableau">
              <thead><tr><th>Date</th><th>Visible</th><th class="c-plante">Plante</th><th>Pousse</th><th class="c-temoin">Témoin</th><th>Rapport</th><th class="c-notes">Notes</th></tr></thead>
              <tbody>
<?php foreach ($constats as $k): $rapport = SautDuChat::rapport($k['pousse_cm'] !== null ? (float) $k['pousse_cm'] : null, $k['temoin_pousse_cm'] !== null ? (float) $k['temoin_pousse_cm'] : null); ?>
                <tr class="sd-item-const">
                  <td class="d"><?php if ($peutEcrire): ?><a href="<?= $h($retour) ?>&amp;modifier=<?= (int) $k['id'] ?>&amp;vue=constats" title="Ouvrir pour corriger"><?= $h($dateFr((string) $k['date'])) ?></a><?php else: ?><?= $h($dateFr((string) $k['date'])) ?><?php endif; ?><?php if ((string) ($k['vegetal'] ?? '') !== ''): ?><span class="sous-date"><?= $h($k['vegetal']) ?></span><?php endif; ?></td>
                  <td><?= $k['visible'] === 'oui' ? 'Oui' : 'Pas encore' ?></td>
                  <td class="c-plante"><?= $h($k['vegetal'] ?? '') ?></td>
                  <td><?php if ($k['pousse_cm'] !== null): ?><?= $h($nombre((float) $k['pousse_cm'], 1)) ?> cm <span class="doux">en <?= (int) $k['sur_jours'] ?> j</span><?php endif; ?></td>
                  <td class="c-temoin" title="<?= $h($k['temoin_decrit'] ?? '') ?>"><?php if ($k['temoin_pousse_cm'] !== null): ?><?= $h($nombre((float) $k['temoin_pousse_cm'], 1)) ?> cm<?php endif; ?></td>
                  <td class="rapport"><?= $rapport !== null ? '× ' . $h($nombre($rapport, 1)) : '' ?></td>
                  <td class="c-notes notes" title="<?= $h($k['notes'] ?? '') ?>"><?= $h($k['notes'] ?? '') ?></td>
                </tr>
<?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div class="sd-page-nav" id="sd-nav-const">
            <button type="button" id="sd-prec-const">← Plus récents</button><span id="sd-page-const"></span><button type="button" id="sd-suiv-const">Plus anciens →</button>
          </div>
<?php endif; ?>
        </section>
      </div>
<?php endif; ?>
    </main>

<?php if ($parcelle !== null && $peutEcrire): ?>
    <div class="scrim" id="scrim-droit"></div>
    <button class="tirette" id="tirette" aria-expanded="false" aria-controls="panneau-droit"
            aria-label="Afficher le panneau Données">
      <svg class="tirette-triangle" viewBox="0 0 10 10" aria-hidden="true"><polygon points="1,1 9,5 1,9" fill="currentColor"/></svg>
      <span class="tirette-texte">Données</span>
    </button>
    <aside class="panneau-droit" id="panneau-droit" aria-hidden="true">
      <div class="pd-corps sd-form">
        <input class="sd-radio-p" type="radio" name="onglet" id="o-constat"<?= $onglet === 'constat' ? ' checked' : '' ?>>
        <input class="sd-radio-p" type="radio" name="onglet" id="o-plantation"<?= $onglet === 'plantation' ? ' checked' : '' ?>>
        <div class="sd-onglets-p" role="tablist">
          <label class="sd-onglet-p" for="o-constat">Un constat</label>
          <label class="sd-onglet-p" for="o-plantation">La plantation</label>
        </div>
<?php if ($erreurs !== []): ?>
        <div class="sd-erreurs" role="alert"><strong>Votre saisie est à reprendre.</strong>
          <ul><?php foreach ($erreurs as $e): ?><li><?= $h($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

        <section class="sd-panneau sd-p-constat">
          <p class="pd-titre"><?= $editionId > 0 ? 'Corriger le constat' : 'Noter un constat' ?> — <?= $h($parcelle['code']) ?></p>
          <p class="sd-note"><b>À quoi il sert</b> : à reconnaître le saut du chat, et à le suivre dans le temps. Il se reconnaît en <strong>comparant</strong> : la pousse d'une plante sur quelques jours, à côté d'un témoin — même sol, conduit classiquement. Seuls la date et la réponse sont demandées ; la mesure vient quand on l'a.</p>
          <form method="post" action="suivi-saut-du-chat.php?parcelle=<?= $idP ?>">
            <input type="hidden" name="csrf" value="<?= $h($auth->jeton()) ?>">
            <input type="hidden" name="action" value="<?= $editionId > 0 ? 'modifier' : 'ajouter' ?>">
            <input type="hidden" name="parcelle" value="<?= $idP ?>">
<?php if ($editionId > 0): ?>
            <input type="hidden" name="constat_id" value="<?= $editionId ?>">
            <input type="hidden" name="version" value="<?= $h($editionVersion) ?>">
<?php endif; ?>
            <label class="sd-champ">Date
              <input type="date" name="date" required max="<?= date('Y-m-d') ?>" value="<?= $h($f['date']) ?>">
            </label>
            <fieldset class="sd-choix">
              <legend>Le <?= Documentation::terme($termes, 'saut-du-chat', 'saut du chat') ?> est-il visible ?</legend>
<?php foreach (SautDuChat::VISIBLES as $cle => $lib): ?>
              <label><input type="radio" class="radio" name="visible" value="<?= $h($cle) ?>"<?= $f['visible'] === $cle ? ' checked' : '' ?>><?= $h($lib) ?></label>
<?php endforeach; ?>
            </fieldset>
            <label class="sd-champ">La plante observée <small>facultatif</small>
              <input type="text" name="vegetal" maxlength="160" value="<?= $h($f['vegetal']) ?>" placeholder="Ex. maïs, tournesol, pommier">
            </label>
            <div class="sd-deux">
              <label class="sd-champ">Sa pousse <small>cm</small>
                <input type="number" name="pousse_cm" min="0" max="2000" step="0.1" inputmode="decimal" value="<?= $h($f['pousse_cm']) ?>">
              </label>
              <label class="sd-champ">Sur combien de jours
                <input type="number" name="sur_jours" min="1" max="730" step="1" value="<?= $h($f['sur_jours']) ?>">
              </label>
            </div>
            <div class="sd-deux">
              <label class="sd-champ">Pousse du témoin <small>cm, même durée</small>
                <input type="number" name="temoin_pousse_cm" min="0" max="2000" step="0.1" inputmode="decimal" value="<?= $h($f['temoin_pousse_cm']) ?>">
              </label>
              <label class="sd-champ">Le témoin, c'est <small>facultatif</small>
                <input type="text" name="temoin_decrit" maxlength="190" value="<?= $h($f['temoin_decrit']) ?>" placeholder="Ex. mêmes plants, à 10 m, paillés">
              </label>
            </div>
            <p class="sd-note">Une pousse se mesure sur une durée ; un témoin ne compare que si la plante est mesurée aussi. Les ordres de grandeur de la formation — maïs jusqu'à 30 cm en 24 h, tournesols 1,50 m de plus que les mêmes plantés classiquement, pérennes jusqu'à deux fois plus vite — sont des <b>repères</b>, pas des seuils.</p>
            <label class="sd-champ">Ce que vous voyez <small>croissance, couleur, densité du feuillage…</small>
              <textarea name="notes" rows="3" maxlength="2000"><?= $h($f['notes']) ?></textarea>
            </label>
            <button type="submit" class="sd-bouton"><?= $editionId > 0 ? 'Enregistrer' : 'Noter ce constat' ?></button>
<?php if ($editionId > 0): ?>
            <a class="sd-lien" href="<?= $h($retour) ?>&amp;vue=constats" style="margin-left:.6rem">Fermer</a>
<?php endif; ?>
          </form>
<?php if ($editionId > 0): ?>
          <form method="post" action="suivi-saut-du-chat.php?parcelle=<?= $idP ?>" class="sd-retrait"
                onsubmit="return confirm('Retirer ce constat ?');">
            <input type="hidden" name="csrf" value="<?= $h($auth->jeton()) ?>">
            <input type="hidden" name="action" value="retirer">
            <input type="hidden" name="parcelle" value="<?= $idP ?>">
            <input type="hidden" name="constat_id" value="<?= $editionId ?>">
            <button type="submit">Retirer ce constat</button>
          </form>
<?php endif; ?>
        </section>

        <section class="sd-panneau sd-p-plantation">
          <p class="pd-titre">La plantation — <?= $h($parcelle['code']) ?></p>
          <p class="sd-note"><b>À quoi elle sert</b> : à situer le projet dans son film — les trois premières années sont celles du stade placentaire — et à dire si la plantation a été faite dans une même période. Une plantation dense s'étale sur <strong>trois saisons — fin d'automne, hiver, printemps — selon les besoins des végétaux</strong> : ce qui compte est un <strong>point zéro unique</strong>, aucune structurante ajoutée après coup. Le début est le point zéro ; la fin se note quand la plantation est terminée. Vidées, les dates s'effacent.</p>
          <form method="post" action="suivi-saut-du-chat.php?parcelle=<?= $idP ?>">
            <input type="hidden" name="csrf" value="<?= $h($auth->jeton()) ?>">
            <input type="hidden" name="action" value="plantation">
            <input type="hidden" name="parcelle" value="<?= $idP ?>">
            <div class="sd-deux">
              <label class="sd-champ">Début <small>le point zéro</small>
                <input type="date" name="date_plantation" min="2000-01-01" max="<?= date('Y-m-d') ?>" value="<?= $h($dateP) ?>">
              </label>
              <label class="sd-champ">Fin <small>facultatif</small>
                <input type="date" name="fin_plantation" min="2000-01-01" max="<?= date('Y-m-d') ?>" value="<?= $h($finP) ?>">
              </label>
            </div>
            <p class="sd-note"><b>Les fenêtres, selon les végétaux</b> [Théry, ch. 11 ; Joala] : les racines nues à partir de mi-novembre ; les boutures ligneuses au repos végétatif ; les placentaires — graines de maïs, de tournesol — au printemps, selon la température du sol (6 °C pour le maïs, 5 °C pour le tournesol). La campagne s'étale ainsi sur environ six mois — un repère de lecture, non une limite. Elles se lisent avec votre terrain et votre climat.</p>
            <button type="submit" class="sd-bouton">Enregistrer</button>
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
      <p>Le <?= Documentation::terme($termes, 'saut-du-chat', 'saut du chat') ?> est <strong>l'effet de la perturbation</strong> : quand on taille la biomasse, le sol est nourri et l'information
         « il faut que ça pousse » gagne les plantes alentour. C'est un effet, pas un objectif : il se déclenche par la <strong>taille</strong>, non par la seule densité.
         Le mécanisme (l'acide gibbérellique transmis par les racines et les mycorhizes) est celui qu'avance la formation ; l'atelier ne le donne pas pour établi.</p>
      <p><strong>Il se reconnaît en comparant.</strong> Une plante qui pousse vite peut simplement vivre une bonne année : d'où le
         <?= Documentation::terme($termes, 'temoin', 'témoin') ?>, conduit classiquement dans le même sol. Les ordres de grandeur donnés dans l'entretien de Théry — maïs perturbés en fin de printemps jusqu'à 30 cm en 24 heures,
         tournesols 1,50 m de plus que les mêmes plantés classiquement à 10 m, pérennes jusqu'à deux fois plus vite que des arbres paillés et arrosés — sont des repères de lecture, non des seuils.</p>
      <p><strong>Ce qui le rend possible</strong> se lit dans ce que vous avez déjà noté : une perturbation dans le journal, de quoi couper au scénario, l'état des plantes et l'eau à la coupe,
         la biomasse laissée au sol, toutes les plantes de biomasse coupées ensemble, des plantes de démarrage. Chaque ligne dit ce que l'atelier sait, ce qu'il faut regarder, ou que rien n'est encore noté —
         jamais « échec ». Ce que la formation n'a pas encore tranché — combien de biomasse suffit, quelle fréquence, quoi planter selon l'itinéraire — n'est pas chiffré ici.</p>
      <p><strong>Quand ?</strong> Dans les exemples de Théry, dès les premiers mois et dès la première année, quand les plantes de biomasse et les annuelles de démarrage sont réussies ; un mauvais démarrage des bordures vivantes le retarde,
         et le rattrapage coûte du travail. Ce n'est pas une échéance.</p>
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
    etape: 'suivi-saut-du-chat'<?php if ($parcelle !== null): ?>,
    contexte: <?= json_encode($titrePage, JSON_UNESCAPED_UNICODE) ?><?php endif; ?>
  });

  (function(){
    var tirette = document.getElementById('tirette');
    if (tirette && document.body.dataset.ouvre === '1') tirette.click();
    ['sd-saisir', 'sd-saisir-vide'].forEach(function(id){
      var a = document.getElementById(id);
      if (a && tirette) a.addEventListener('click', function(ev){ ev.preventDefault(); var r = document.getElementById('o-constat'); if (r) r.checked = true; tirette.click(); });
    });
    // « La noter → » : ouvre le panneau sur l'onglet de la plantation.
    document.querySelectorAll('[data-ouvre-panneau]').forEach(function(a){
      a.addEventListener('click', function(ev){
        var radio = document.getElementById('o-' + a.dataset.ouvrePanneau);
        if (!tirette || !radio) return;
        ev.preventDefault();
        radio.checked = true;
        if (tirette.getAttribute('aria-expanded') !== 'true') tirette.click();
      });
    });

    /* Le bureau ne défile jamais : chaque vue se réduit, puis se pagine en montrant autant d'éléments
       que la place le permet. */
    var centre = document.querySelector('.sd-centre');
    function deborde(){ return centre.scrollHeight > centre.clientHeight + 1; }
    function pagineur(zoneId, itemsSel, suffixe){
      var items = Array.prototype.slice.call(document.querySelectorAll(itemsSel));
      var page = 0, parPage = items.length;
      function afficher(){
        items.forEach(function(l, i){ l.style.display = (i >= page * parPage && i < (page + 1) * parPage) ? '' : 'none'; });
        var nav = document.getElementById('sd-nav-' + suffixe);
        if (!nav) return;
        var pages = Math.max(1, Math.ceil(items.length / parPage));
        nav.classList.toggle('actif', pages > 1);
        document.getElementById('sd-page-' + suffixe).textContent = 'Page ' + (page + 1) + ' sur ' + pages;
        document.getElementById('sd-prec-' + suffixe).disabled = page === 0;
        document.getElementById('sd-suiv-' + suffixe).disabled = page >= pages - 1;
      }
      function ajuster(){
        var zone = document.getElementById(zoneId);
        if (!zone || !centre || !zone.offsetParent || items.length === 0) return;
        var t = 15;
        zone.style.fontSize = t + 'px';
        page = 0; parPage = items.length; afficher();
        while (deborde() && t > 12) { t -= 0.5; zone.style.fontSize = t + 'px'; }
        if (deborde()) {
          var n = items.length;
          while (n > 1) { n--; parPage = n; afficher(); if (!deborde()) break; }
        }
      }
      var prec = document.getElementById('sd-prec-' + suffixe), suiv = document.getElementById('sd-suiv-' + suffixe);
      if (prec) prec.addEventListener('click', function(){ page = Math.max(0, page - 1); afficher(); });
      if (suiv) suiv.addEventListener('click', function(){ page++; afficher(); });
      return ajuster;
    }
    var aCond = pagineur('sd-cond-zone', '.sd-item-cond', 'cond');
    var aConst = pagineur('sd-const-zone', '.sd-item-const', 'const');
    function tout(){ aCond(); aConst(); }
    document.querySelectorAll('.sd-radio').forEach(function(r){ r.addEventListener('change', tout); });
    if (centre) {
      tout();
      window.addEventListener('resize', tout);
      if (document.fonts && document.fonts.ready) document.fonts.ready.then(tout);
    }
  })();
</script>
</body>
</html>
