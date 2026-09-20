<?php

declare(strict_types=1);

/**
 * Perturbation — le journal d'une parcelle.
 *
 * Étape 2 de Suivi. Une ligne par geste (coupe, taille, fauche, affaiblissement, désherbage), dans la
 * table `intervention` : la consoude se coupe plusieurs fois par an, et une seule saisie
 * écrasée à chaque envoi n'en gardait rien. Voir `chantier-saut-du-chat.md`, Phase 1.
 *
 * Le journal note ce qui s'est passé ; il ne décide pas. Le moment se lit sur l'état
 * végétatif des plantes de biomasse, la fréquence suit leur développement, et l'on arrose
 * si la pluie ne vient pas (le porteur, 2026-09-20) : la page dit ce que disent les sources
 * et pourquoi, sans jamais refuser une saisie.
 */

require __DIR__ . '/../src/autoload.php';
require __DIR__ . '/../src/Configuration.php';
require __DIR__ . '/../src/Database.php';

use Syntropia\Configuration;
use Syntropia\Documentation;
use Syntropia\Perturbation;
use Syntropia\Role;
use Syntropia\Scenario;
use Syntropia\Web\Authentification;
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
$journal = new Interventions();
$termes = Documentation::glossaire(__DIR__ . '/../contenu/glossaire.md');

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
$parcelle ??= $parcelles[0] ?? null;
$idP = $parcelle !== null ? (int) $parcelle['id'] : 0;
$retour = Configuration::base() . 'suivi-perturbation.php?parcelle=' . $idP;

$erreurs = [];
$ouvre = false;
$saisie = null;          // la saisie POST à reprendre
$editionId = 0;          // l'intervention ouverte dans le panneau (0 = une nouvelle)
$editionVersion = '';
$flash = $_SESSION['syntropia_flash_perturbation'] ?? null;
unset($_SESSION['syntropia_flash_perturbation']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $parcelle !== null) {
    $action = (string) ($_POST['action'] ?? '');
    $ouvre = true;
    $saisie = $_POST;
    $editionId = (int) ($_POST['intervention_id'] ?? 0);
    $editionVersion = (string) ($_POST['version'] ?? '');
    if (!$peutEcrire) {
        http_response_code(403);
        $erreurs['droit'] = 'Vous lisez ce projet : il ne se modifie pas depuis votre compte.';
    } elseif (!$auth->jetonValide($_POST['csrf'] ?? null)) {
        http_response_code(403);
        $erreurs['jeton'] = 'Formulaire expiré — rechargez la page.';
    } elseif ($action === 'ajouter') {
        $erreurs = $journal->ajouter($idP, $compteId, $_POST);
        if ($erreurs === []) {
            $_SESSION['syntropia_flash_perturbation'] = ['ok', 'L’intervention est notée.'];
            header('Location: ' . $retour);
            exit;
        }
    } elseif ($action === 'modifier') {
        $erreurs = $journal->modifier($editionId, $idP, $editionVersion, $_POST);
        if ($erreurs === []) {
            $_SESSION['syntropia_flash_perturbation'] = ['ok', 'L’intervention est mise à jour.'];
            header('Location: ' . $retour);
            exit;
        }
    } elseif ($action === 'retirer') {
        $_SESSION['syntropia_flash_perturbation'] = $journal->retirer($editionId, $idP)
            ? ['ok', 'L’intervention est retirée du journal.']
            : ['ok', 'Cette intervention n’est plus dans le journal.'];
        header('Location: ' . $retour);
        exit;
    } else {
        http_response_code(400);
        $erreurs['action'] = 'Cette action n’est pas reconnue.';
    }
}

$interventions = $parcelle !== null ? $journal->dUneParcelle($idP) : [];

// Le panneau s'ouvre sur une intervention à compléter (?modifier=ID) ou sur un nouveau geste (?saisie=1).
$edition = null;
if ($saisie === null && $peutEcrire && $parcelle !== null && isset($_GET['modifier'])) {
    $edition = $journal->obtenir((int) $_GET['modifier'], $idP);
    if ($edition !== null) {
        $editionId = (int) $edition['id'];
        $editionVersion = Interventions::version($edition);
        $ouvre = true;
    }
}

// La note d'avant le journal : trois champs libres, écrasés à chaque envoi. Jamais supprimée.
$ancienne = [];
if ($parcelle !== null && $interventions === []) {
    $r = (new Reponses())->pourParticipant($participantId);
    foreach (['quand' => 'perturbation_quand', 'quoi' => 'perturbation_quoi', 'comment' => 'perturbation_comment'] as $k => $q) {
        if (trim((string) ($r[$q] ?? '')) !== '') {
            $ancienne[$k] = trim((string) $r[$q]);
        }
    }
}
$depuisNote = $peutEcrire && $ancienne !== [] && ($_GET['depuis'] ?? '') === 'note' && $saisie === null && $edition === null;
if ($depuisNote) {
    $ouvre = true;
}

// Les plantes qu'on coupe : celles du scénario qui portent la biomasse ou la perturbation.
$plantes = [];
if ($parcelle !== null) {
    foreach ($parcellesService->scenario($idP) as $ligne) {
        $fonctions = Scenario::details($ligne)['fonctions'] ?? [];
        if (is_array($fonctions) && (in_array('perturbation', $fonctions, true) || in_array('biomasse', $fonctions, true))) {
            $nom = trim((string) $ligne['espece']);
            if ($nom !== '') {
                $plantes[mb_strtolower($nom)] = $nom;
            }
        }
    }
}
$dernieres = Perturbation::derniereParVegetal($interventions);
$aujourdhui = new DateTimeImmutable('today');
$ilYA = static function (int $j): string {
    if ($j === 0) {
        return 'aujourd’hui';
    }
    if ($j === 1) {
        return 'hier';
    }
    return $j >= 60 ? 'il y a ' . intdiv($j, 30) . ' mois' : 'il y a ' . $j . ' j';
};

// Les valeurs du formulaire : la saisie à reprendre, l'intervention ouverte, la note d'avant, ou rien.
$f = ['date' => date('Y-m-d'), 'type' => '', 'zone' => '', 'vegetaux' => [], 'autre' => '', 'stade_phenologique' => '',
    'conditions_eau' => '', 'pluies_a_venir' => '', 'biomasse_deposee' => '', 'motif' => '', 'outil' => '', 'premiere_fois' => false,
    'duree_h' => '', 'personnes' => '', 'quantite_traitee' => '', 'unite_quantite' => '', 'resultat_constate' => '', 'notes' => ''];
$texteChamps = ['date', 'type', 'zone', 'stade_phenologique', 'conditions_eau', 'pluies_a_venir', 'biomasse_deposee', 'motif', 'outil',
    'duree_h', 'personnes', 'quantite_traitee', 'unite_quantite', 'resultat_constate', 'notes'];
if ($saisie !== null) {
    foreach ($texteChamps as $c) {
        $f[$c] = (string) ($saisie[$c] ?? '');
    }
    $f['premiere_fois'] = !empty($saisie['premiere_fois']);
    $noms = is_array($saisie['especes'] ?? null) ? array_map('strval', $saisie['especes']) : [];
    $f['autre'] = (string) ($saisie['autre_vegetal'] ?? '');
} elseif ($edition !== null) {
    foreach ($texteChamps as $c) {
        $f[$c] = (string) ($edition[$c] ?? '');
    }
    $f['premiere_fois'] = (int) $edition['premiere_fois'] === 1;
    $noms = Perturbation::vegetaux($edition['especes'] ?? '');
} else {
    $noms = [];
    if ($depuisNote) {
        $f['motif'] = $ancienne['quand'] ?? '';
        $f['notes'] = trim(
            (isset($ancienne['quoi']) ? 'Quoi : ' . $ancienne['quoi'] : '')
            . (isset($ancienne['comment']) ? "\nComment : " . $ancienne['comment'] : ''));
    }
}
// Ce qui n'est pas une plante du scénario devient le mot libre.
$horsScenario = [];
foreach ($noms as $n) {
    if (isset($plantes[mb_strtolower($n)])) {
        $f['vegetaux'][] = $plantes[mb_strtolower($n)];
    } elseif (trim($n) !== '') {
        $horsScenario[] = $n;
    }
}
if ($saisie === null && $horsScenario !== []) {
    $f['autre'] = Perturbation::joindre($horsScenario);
}
$f['vegetaux'] = array_values(array_unique($f['vegetaux']));

$nomAffiche = $auth->nomAffiche() ?? '';
$initiales = '';
foreach (preg_split('/\s+/', trim($nomAffiche)) ?: [] as $mot) {
    if ($mot !== '') {
        $initiales .= mb_strtoupper(mb_substr($mot, 0, 1));
    }
}
$initiales = mb_substr($initiales, 0, 2) ?: '?';
$titrePage = $parcelle !== null ? $parcelle['code'] . ' — ' . $parcelle['nom'] : '';
$panneauOuvert = $ouvre || (bool) preg_match('/[?&]saisie=1/', $_SERVER['QUERY_STRING'] ?? '');
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Perturbation — Syntropia</title>
<link rel="stylesheet" href="assets/css/gabarit.css">
<style>
  /* pas de bandeau d'outils : les tirettes se calent sur le seul bandeau général */
  :root{ --haut-bandeaux: 3.4rem; }

  /* le bureau centre son contenu ; ici il remplit sa place, pour que la liste puisse se réduire ou se paginer */
  #bureau{ padding:.4rem .6rem; align-items:stretch; }
  .pj-centre{ width:100%; min-height:0; display:flex; flex-direction:column; gap:.5rem; padding:.3rem .4rem .2rem; overflow:hidden; }
  .pj-entete{ display:flex; align-items:center; gap:.7rem; flex-wrap:wrap; }
  .pj-titre{ font:600 1rem var(--titre); color:var(--accent-titre); margin:0; }
  .pj-badge{ font:500 .72rem var(--titre); color:var(--muted); border:1px solid var(--line); border-radius:1rem; padding:.1rem .55rem; }
  .pj-actions{ margin-left:auto; display:flex; gap:.5rem; }
  .pj-bouton{
    font:600 .8rem var(--titre); color:var(--bouton-encre); background:var(--accent); border:0; border-radius:.5rem;
    padding:.35rem .75rem; text-decoration:none; cursor:pointer;
  }
  .pj-bouton--calme{ background:transparent; color:var(--accent-titre); border:1px solid var(--accent); }
  .pj-message{ margin:0; padding:.4rem .7rem; border-radius:.5rem; font:400 .82rem/1.35 var(--texte);
    background:var(--accent-fond); border:1px solid var(--accent); color:var(--accent-titre); }
  .pj-message--alerte{ border-color:var(--alerte); color:var(--alerte); }
  .pj-message a{ color:inherit; font-weight:600; }
  .pj-annonce{ margin:0; font:400 .86rem/1.4 var(--texte); color:var(--muted); }

  /* le temps écoulé, plante par plante — sans seuil : la fréquence suit la repousse */
  .pj-plantes{ display:flex; flex-wrap:wrap; gap:.35rem; }
  .pj-plante{ font:500 .76rem var(--texte); color:var(--ink); border:1px solid var(--line); border-radius:.5rem; padding:.2rem .6rem; background:var(--surface); }
  .pj-plante b{ font:600 .78rem var(--titre); color:var(--accent-titre); }
  .pj-plante small{ color:var(--muted); font-size:.95em; }
  .pj-note-plantes{ margin:0; font:400 .74rem/1.35 var(--texte); color:var(--muted); }

  .pj-tableau{ width:100%; border-collapse:collapse; font:400 .86em var(--texte); color:var(--ink); }   /* en em : le réglage de la zone s'y applique */
  .pj-tableau th{ text-align:left; font:600 .72rem var(--titre); text-transform:uppercase; letter-spacing:.05em; color:var(--muted); padding:.25rem .5rem; border-bottom:1px solid var(--line); }
  .pj-tableau td{ padding:.28rem .5rem; border-bottom:1px solid var(--line); vertical-align:top; }
  .pj-tableau td.d{ white-space:nowrap; font-variant-numeric:tabular-nums; }
  .pj-tableau td a{ color:var(--accent-titre); font-weight:600; text-decoration:none; }
  .pj-tableau td a:hover{ text-decoration:underline; }
  .pj-tableau .doux{ color:var(--muted); font-size:.85em; }
  .pj-tableau .constate{ max-width:16rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .pj-tableau .aff{ color:var(--muted); }
  .pj-page-nav{ display:none; align-items:center; gap:.6rem; font:400 .78rem var(--texte); color:var(--muted); }
  .pj-page-nav.actif{ display:flex; }
  .pj-page-nav button{ font:600 .78rem var(--titre); color:var(--accent-titre); background:transparent; border:1px solid var(--accent); border-radius:.4rem; padding:.15rem .6rem; cursor:pointer; }
  .pj-page-nav button:disabled{ opacity:.4; cursor:default; }
  .pj-vide{ margin:auto; max-width:26rem; text-align:center; font:400 .92rem/1.5 var(--texte); color:var(--muted); }
  .pj-vide a{ color:var(--accent-titre); font-weight:600; }
  .pj-ancienne{ margin:0; padding:.5rem .7rem; border:1px dashed var(--line); border-radius:.5rem; font:400 .8rem/1.4 var(--texte); color:var(--ink); }
  .pj-ancienne b{ font:600 .78rem var(--titre); color:var(--muted); }
  .pj-ancienne a{ color:var(--accent-titre); font-weight:600; }
  @media (max-width:60rem){ .pj-tableau .c-constate{ display:none; } }
  @media (max-width:46rem){ .pj-tableau .c-eau, .pj-tableau .c-sol{ display:none; } }
  @media (max-width:40rem){ .pj-tableau .c-etat{ display:none; } }
  @media (max-width:40rem){ .pj-centre{ padding-top:1.8rem; } }   /* la place de l'onglet « Informations » */
  @media (max-height:38rem){ .pj-annonce, .pj-note-plantes{ display:none; } }

  /* le panneau Données */
  .pj-form label.pj-champ{ display:block; font:500 .78rem var(--titre); color:var(--ink); margin:0 0 .5rem; }
  .pj-form label.pj-champ small{ font:400 .68rem var(--texte); color:var(--muted); }
  .pj-form input[type=text], .pj-form input[type=number], .pj-form input[type=date], .pj-form select, .pj-form textarea{
    display:block; width:100%; margin-top:.15rem; padding:.36rem .5rem; font:400 .84rem var(--texte); color:var(--ink);
    background:var(--surface); border:1px solid var(--line); border-radius:.45rem;
  }
  .pj-form textarea{ resize:vertical; min-height:3.2rem; }
  .pj-form input:focus, .pj-form select:focus, .pj-form textarea:focus{ outline:2px solid var(--accent); outline-offset:1px; }
  .pj-deux{ display:grid; grid-template-columns:1fr 1fr; gap:.5rem; }
  .pj-note{ font:400 .74rem/1.4 var(--texte); color:var(--muted); margin:.2rem 0 .6rem; }
  .pj-conseil{ display:none; margin:.1rem 0 .6rem; padding:.45rem .6rem; border-radius:.45rem; background:var(--accent-fond); font:400 .76rem/1.4 var(--texte); color:var(--ink); }
  .pj-form:has(select[name=conditions_eau] option[value=sec]:checked) .pj-conseil--sec,
  .pj-form:has(select[name=type] option[value=affaiblissement]:checked) .pj-conseil--aff,
  .pj-form:has(select[name=type] option[value=desherbage]:checked) .pj-conseil--desh,
  .pj-form:has(select[name=biomasse_deposee] option[value=exportee]:checked) .pj-conseil--exp{ display:block; }
  .pj-erreurs{ margin:0 0 .7rem; padding:.5rem .7rem; border:1px solid var(--alerte); border-radius:.5rem; color:var(--alerte); font:400 .8rem/1.35 var(--texte); }
  .pj-erreurs ul{ margin:.2rem 0 0 1rem; padding:0; }
  .pj-cases{ margin:0 0 .5rem; padding:0; border:0; }
  .pj-cases legend{ font:500 .78rem var(--titre); color:var(--ink); padding:0; margin-bottom:.2rem; }
  .pj-cases legend small{ font:400 .68rem var(--texte); color:var(--muted); }
  .pj-cases label.pj-case{ display:flex; align-items:center; gap:.4rem; font:400 .82rem var(--texte); color:var(--ink); margin:.15rem 0; }
  .pj-retrait{ margin-top:1rem; padding-top:.6rem; border-top:1px solid var(--line); }
  .pj-retrait button{ font:600 .74rem var(--titre); color:var(--alerte); background:transparent; border:1px solid var(--alerte); border-radius:.4rem; padding:.2rem .6rem; cursor:pointer; }
  .pj-lien{ font:500 .76rem var(--titre); color:var(--accent-titre); }
</style>
</head>
<body<?= $panneauOuvert ? ' data-ouvre="1"' : '' ?>>
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
            <li><a href="suivi-perturbation.php?parcelle=<?= (int) $p['id'] ?>"<?= $estActive ? ' class="active" aria-current="page"' : '' ?>><span class="pg-puce">≋</span><span class="pg-libelle"><?= $h($p['code']) ?> — <?= $h($p['nom']) ?></span></a></li>
<?php endforeach; ?>
            <li><a href="conception-parcelles.php?nouvelle=1"><span class="pg-puce">+</span><span class="pg-libelle">Nouvelle parcelle</span></a></li>
          </ul>
        </div>
        <p class="pg-pied">Deuxième étape de Suivi : le journal des coupes et des tailles de la parcelle.</p>
      </div>
    </nav>

    <main class="bureau" id="bureau">
<?php if ($parcelle === null): ?>
      <div class="pj-centre">
        <p class="pj-vide">Le journal des perturbations se tient parcelle par parcelle, et ce site n'en a pas encore.
          <a href="conception-parcelles.php?nouvelle=1">Ouvrir la première parcelle →</a></p>
      </div>
<?php else: ?>
      <div class="pj-centre">
        <div class="pj-entete">
          <h1 class="pj-titre">Perturbation — <?= $h($titrePage) ?></h1>
<?php if ($interventions !== []): ?>
          <span class="pj-badge"><?= count($interventions) ?> intervention<?= count($interventions) > 1 ? 's' : '' ?></span>
<?php endif; ?>
<?php if ($peutEcrire): ?>
          <span class="pj-actions">
            <a class="pj-bouton pj-bouton--calme" href="<?= $h($retour) ?>&amp;saisie=1" id="pj-saisir">Noter une intervention</a>
          </span>
<?php endif; ?>
        </div>
<?php if (is_array($flash)): ?>
        <p class="pj-message" role="status"><?= $h($flash[1]) ?></p>
<?php endif; ?>
<?php if ($erreurs !== []): ?>
        <p class="pj-message pj-message--alerte" role="alert">Votre saisie est à reprendre — le détail est dans le panneau Données.</p>
<?php endif; ?>

<?php if ($plantes !== []): ?>
        <div class="pj-plantes" aria-label="Le temps écoulé depuis la dernière perturbation de chaque plante">
<?php foreach ($plantes as $cle => $nom): ?>
          <span class="pj-plante"><?= $h($nom) ?> ·
<?php if (isset($dernieres[$cle])): ?>
            <b><?= $h($ilYA(Perturbation::joursDepuis($dernieres[$cle], $aujourdhui))) ?></b>
<?php else: ?>
            <small>pas encore coupée</small>
<?php endif; ?>
          </span>
<?php endforeach; ?>
        </div>
        <p class="pj-note-plantes">Le temps écoulé depuis la dernière perturbation, sans seuil : la prochaine se fait quand il y a de quoi couper — et de quoi arroser si la pluie ne vient pas.</p>
<?php elseif ($interventions === []): ?>
        <p class="pj-annonce">Perturber, ici, n'est pas un accident : c'est un geste qui relance la croissance. Notez chaque coupe — la première, en juin, puis chacune de celles qui suivent.
          Les plantes de biomasse du scénario apparaîtront ici avec le temps écoulé depuis leur dernière coupe.</p>
<?php endif; ?>

<?php if ($ancienne !== []): ?>
        <p class="pj-ancienne"><b>Votre note d'avant le journal</b> —
<?php foreach (['quand' => 'Quand', 'quoi' => 'Quoi', 'comment' => 'Comment'] as $k => $lib): if (isset($ancienne[$k])): ?>
          <?= $lib ?> : <?= $h($ancienne[$k]) ?>.
<?php endif; endforeach; ?>
<?php if ($peutEcrire): ?>
          <a href="<?= $h($retour) ?>&amp;depuis=note">En faire une intervention →</a>
<?php endif; ?>
        </p>
<?php endif; ?>

<?php if ($interventions === []): ?>
<?php if ($ancienne === []): ?>
        <p class="pj-vide">Aucune intervention n'est encore notée sur cette parcelle.
<?php if ($peutEcrire): ?>
          <a href="<?= $h($retour) ?>&amp;saisie=1" id="pj-saisir-vide">Noter la première →</a>
<?php endif; ?></p>
<?php endif; ?>
<?php else: ?>
        <div class="pj-zone" id="pj-zone">
          <table class="pj-tableau">
            <thead><tr><th>Date</th><th>Geste</th><th>Végétaux</th><th class="c-etat">État des plantes</th><th class="c-eau">Eau</th><th class="c-sol">Biomasse</th><th class="c-constate">Constaté</th></tr></thead>
            <tbody>
<?php foreach ($interventions as $i): $affaiblit = !Perturbation::perturbe((string) $i['type']); ?>
              <tr class="pj-ligne">
                <td class="d"><?php if ($peutEcrire): ?><a href="<?= $h($retour) ?>&amp;modifier=<?= (int) $i['id'] ?>" title="Ouvrir pour compléter ou corriger"><?= $h($dateFr((string) $i['date'])) ?></a><?php else: ?><?= $h($dateFr((string) $i['date'])) ?><?php endif; ?></td>
                <td<?= $affaiblit ? ' class="aff"' : '' ?>><?= $h(Perturbation::libelle(Perturbation::TYPES, $i['type'])) ?></td>
                <td><?= $h(Perturbation::joindre(Perturbation::vegetaux($i['especes'] ?? ''))) ?><?php if ((string) ($i['zone'] ?? '') !== ''): ?><?= (string) ($i['especes'] ?? '') !== '' ? ' ' : '' ?><span class="doux">— <?= $h($i['zone']) ?></span><?php endif; ?></td>
                <td class="c-etat"><?= $h(Perturbation::libelle(Perturbation::STADES, $i['stade_phenologique'])) ?></td>
                <td class="c-eau"><?= $h(Perturbation::libelle(Perturbation::EAUX, $i['conditions_eau'])) ?></td>
                <td class="c-sol"><?= $h(Perturbation::libelle(Perturbation::BIOMASSES, $i['biomasse_deposee'])) ?></td>
                <td class="c-constate constate" title="<?= $h($i['resultat_constate'] ?? '') ?>"><?= $h($i['resultat_constate'] ?? '') ?></td>
              </tr>
<?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="pj-page-nav" id="pj-nav">
          <button type="button" id="pj-prec">← Plus récentes</button><span id="pj-page"></span><button type="button" id="pj-suiv">Plus anciennes →</button>
        </div>
<?php endif; ?>
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
      <div class="pd-corps pj-form">
<?php if ($erreurs !== []): ?>
        <div class="pj-erreurs" role="alert"><strong>Votre saisie est à reprendre.</strong>
          <ul><?php foreach ($erreurs as $e): ?><li><?= $h($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
        <p class="pd-titre"><?= $editionId > 0 ? 'Compléter ou corriger l’intervention' : 'Noter une intervention' ?> — <?= $h($parcelle['code']) ?></p>
        <p class="pj-note"><b>À quoi elle sert</b> : à retrouver ce qui s'est fait et dans quel état étaient les plantes, pour reconnaître le saut du chat quand il vient et comprendre pourquoi il tarde. Seuls la date et le geste sont demandés ; le reste se note quand on le sait — ce qu'on constate, par exemple, vient plus tard.</p>
        <form method="post" action="suivi-perturbation.php?parcelle=<?= $idP ?>">
          <input type="hidden" name="csrf" value="<?= $h($auth->jeton()) ?>">
          <input type="hidden" name="action" value="<?= $editionId > 0 ? 'modifier' : 'ajouter' ?>">
          <input type="hidden" name="parcelle" value="<?= $idP ?>">
<?php if ($editionId > 0): ?>
          <input type="hidden" name="intervention_id" value="<?= $editionId ?>">
          <input type="hidden" name="version" value="<?= $h($editionVersion) ?>">
<?php endif; ?>
          <div class="pj-deux">
            <label class="pj-champ">Date
              <input type="date" name="date" required max="<?= date('Y-m-d') ?>" value="<?= $h($f['date']) ?>">
            </label>
            <label class="pj-champ">Geste
              <select name="type" required>
                <option value="">—</option>
<?php foreach (Perturbation::TYPES as $cle => $lib): ?>
                <option value="<?= $h($cle) ?>"<?= $f['type'] === $cle ? ' selected' : '' ?>><?= $h($lib) ?></option>
<?php endforeach; ?>
              </select>
            </label>
          </div>
          <p class="pj-conseil pj-conseil--aff"><b>Affaiblir n'est pas perturber.</b> On couche une couverture qu'on veut planter sous la paille : une taille la ferait repartir « à fond la caisse » (Joala, 4-3). Ce geste se note, mais il ne compte pas comme une perturbation.</p>
          <p class="pj-conseil pj-conseil--desh"><b>Des adventices qui reviennent disent que la plantation n'est pas assez dense.</b> Des plantes de colonisation sont encore là : désherbez à la main, et densifiez avec des plantes du système d'accumulation. Ce geste se note, mais ne compte pas comme une perturbation.</p>

<?php if ($plantes !== []): ?>
          <fieldset class="pj-cases">
            <legend>Ce qui est coupé <small>les plantes de biomasse du scénario</small></legend>
<?php foreach ($plantes as $nom): ?>
            <label class="pj-case"><input type="checkbox" class="case" name="especes[]" value="<?= $h($nom) ?>"<?= in_array($nom, $f['vegetaux'], true) ? ' checked' : '' ?>><?= $h($nom) ?></label>
<?php endforeach; ?>
          </fieldset>
<?php endif; ?>
          <label class="pj-champ"><?= $plantes !== [] ? 'Un autre végétal' : 'Ce qui est coupé' ?> <small>facultatif</small>
            <input type="text" name="autre_vegetal" maxlength="120" value="<?= $h($f['autre']) ?>" placeholder="Ex. saule, consoude">
          </label>
          <label class="pj-champ">La zone <small>facultatif</small>
            <input type="text" name="zone" maxlength="120" value="<?= $h($f['zone']) ?>" placeholder="Ex. toute la bordure sud">
          </label>
          <p class="pj-note">Le corpus le dit ainsi : perturber <b>toutes les plantes alliées d'une zone en même temps</b> — l'information de perturbation est démultipliée par la masse (Théry, ch. 12).</p>

          <label class="pj-champ">L'état des plantes <small>c'est lui qui dicte le bon moment</small>
            <select name="stade_phenologique">
              <option value="">—</option>
<?php foreach (Perturbation::STADES as $cle => $lib): ?>
              <option value="<?= $h($cle) ?>"<?= $f['stade_phenologique'] === $cle ? ' selected' : '' ?>><?= $h($lib) ?></option>
<?php endforeach; ?>
            </select>
          </label>
          <label class="pj-champ">Pourquoi maintenant <small>facultatif</small>
            <textarea name="motif" rows="2" maxlength="2000" placeholder="Ce qui vous a décidé à intervenir à ce moment"><?= $h($f['motif']) ?></textarea>
          </label>

          <div class="pj-deux">
            <label class="pj-champ">L'eau
              <select name="conditions_eau">
                <option value="">—</option>
<?php foreach (Perturbation::EAUX as $cle => $lib): ?>
                <option value="<?= $h($cle) ?>"<?= $f['conditions_eau'] === $cle ? ' selected' : '' ?>><?= $h($lib) ?></option>
<?php endforeach; ?>
              </select>
            </label>
            <label class="pj-champ">Les pluies à venir
              <select name="pluies_a_venir">
                <option value="">—</option>
<?php foreach (Perturbation::PLUIES as $cle => $lib): ?>
                <option value="<?= $h($cle) ?>"<?= $f['pluies_a_venir'] === $cle ? ' selected' : '' ?>><?= $h($lib) ?></option>
<?php endforeach; ?>
              </select>
            </label>
          </div>
          <p class="pj-conseil pj-conseil--sec"><b>Cela se note, rien ne l'interdit.</b> La taille est déjà un stress : sans eau, une bonne part du bénéfice se perd et la reprise ralentit (Théry, ch. 12). Coupez au moment que disent les plantes, et prévoyez de quoi arroser si la pluie ne vient pas.</p>
          <p class="pj-note">L'eau est un accélérateur d'information : elle porte le signal « il faut que ça pousse ». Juste avant une pluie, juste après, entre deux averses, ou avant un arrosage.</p>

          <label class="pj-champ">La biomasse coupée
            <select name="biomasse_deposee">
              <option value="">—</option>
<?php foreach (Perturbation::BIOMASSES as $cle => $lib): ?>
              <option value="<?= $h($cle) ?>"<?= $f['biomasse_deposee'] === $cle ? ' selected' : '' ?>><?= $h($lib) ?></option>
<?php endforeach; ?>
            </select>
          </label>
          <p class="pj-conseil pj-conseil--exp"><b>Ce qui sort ne nourrit plus le sol.</b> La biomasse se laisse au sol, au pied des plantes (Théry, ch. 12 ; Joala, 2-4). Avec des fagots, on en garde au moins un tiers.</p>

          <label class="pj-champ">Ce qu'on constate ensuite <small>repousse, couleur, vigueur — se complète plus tard</small>
            <textarea name="resultat_constate" rows="3" maxlength="2000" placeholder="Ce que vous voyez quelques jours après"><?= $h($f['resultat_constate']) ?></textarea>
          </label>

          <label class="pj-champ">L'outil <small>facultatif</small>
            <input type="text" name="outil" maxlength="120" value="<?= $h($f['outil']) ?>" placeholder="Ex. serpette, sécateur, faux">
          </label>
          <div class="pj-deux">
            <label class="pj-champ">Durée <small>heures</small>
              <input type="number" name="duree_h" min="0.05" max="99" step="0.05" inputmode="decimal" value="<?= $h($f['duree_h']) ?>">
            </label>
            <label class="pj-champ">Personnes
              <input type="number" name="personnes" min="1" max="99" step="1" value="<?= $h($f['personnes']) ?>">
            </label>
          </div>
          <div class="pj-deux">
            <label class="pj-champ">Quantité traitée <small>pour rapporter la durée</small>
              <input type="number" name="quantite_traitee" min="0.01" step="0.01" inputmode="decimal" value="<?= $h($f['quantite_traitee']) ?>">
            </label>
            <label class="pj-champ">Comptée en
              <select name="unite_quantite">
                <option value="">—</option>
                <option value="m"<?= $f['unite_quantite'] === 'm' ? ' selected' : '' ?>>mètres de ligne</option>
                <option value="m2"<?= $f['unite_quantite'] === 'm2' ? ' selected' : '' ?>>m²</option>
              </select>
            </label>
          </div>
          <label class="pj-champ"><span class="pj-case" style="display:flex;align-items:center;gap:.4rem"><input type="checkbox" class="case" name="premiere_fois" value="1"<?= $f['premiere_fois'] ? ' checked' : '' ?>>C'est la première fois que je fais ce geste</span>
            <small>le premier passage prend deux à trois fois plus longtemps que le dixième</small></label>
          <label class="pj-champ">Notes <small>facultatif</small>
            <textarea name="notes" rows="2" maxlength="2000"><?= $h($f['notes']) ?></textarea>
          </label>
          <button type="submit" class="pj-bouton"><?= $editionId > 0 ? 'Enregistrer' : 'Noter cette intervention' ?></button>
<?php if ($editionId > 0): ?>
          <a class="pj-lien" href="<?= $h($retour) ?>" style="margin-left:.6rem">Fermer</a>
<?php endif; ?>
        </form>
<?php if ($editionId > 0): ?>
        <form method="post" action="suivi-perturbation.php?parcelle=<?= $idP ?>" class="pj-retrait"
              onsubmit="return confirm('Retirer cette intervention du journal ?');">
          <input type="hidden" name="csrf" value="<?= $h($auth->jeton()) ?>">
          <input type="hidden" name="action" value="retirer">
          <input type="hidden" name="parcelle" value="<?= $idP ?>">
          <input type="hidden" name="intervention_id" value="<?= $editionId ?>">
          <button type="submit">Retirer cette intervention</button>
        </form>
<?php endif; ?>
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
      <p>En syntropique, tailler n'est pas soigner l'arbre : c'est imiter ce que fait la nature (une chute de branche, un passage d'animal) pour relancer sa croissance
         et nourrir le sol de ce qui tombe. Le <?= Documentation::terme($termes, 'saut-du-chat', 'saut du chat') ?>
         est l'<strong>effet</strong> de cette perturbation : il se déclenche par la taille, non par la seule densité.</p>
      <p><strong>Le bon moment, c'est l'état des plantes qui le dit.</strong> Les plantes de biomasse se coupent en pleine croissance, et au plus tard quand les fleurs se fanent et que
         les butineurs s'en désintéressent : passé ce cap, les sucres partent vers la reproduction (Théry, ch. 12 ; Faraj, « avant le déclin qui suit la floraison »).
         Les ligneux se taillent aussi l'hiver, hors gel et avant le débourrement, dont la date avance d'année en année (Joala, 3-1). La première perturbation est en juin ;
         les suivantes viennent <strong>quand il y a de quoi couper</strong>.</p>
      <p><strong>L'eau fait partie du geste.</strong> La taille est déjà un stress : la coupe se cale juste avant ou juste après une pluie, entre deux averses, ou avant un arrosage —
         et l'on arrose si la pluie ne vient pas. L'eau est ce qui porte l'information « il faut que ça pousse ».</p>
      <p><strong>Toute la zone d'un coup, et tout au sol.</strong> On perturbe ensemble les plantes alliées d'une zone, et l'on ne sort rien : feuilles et résidus restent au pied des plantes.
         Coupe à ras (3 à 4 cm de la souche) pour les arbustes en buisson ; les plantes non ligneuses (consoude, mélisse…) peuvent se faucher plusieurs fois dans l'année.</p>
      <p><strong>Des exemples, pas des fréquences pour votre site.</strong> Dans la formation : la consoude jusqu'à six coupes par an, la mélisse trois ou quatre ; dans un potager en transition vers
         le jardin-forêt, une nouvelle taille possible sous trois semaines. La vôtre suit la repousse de vos plantes.</p>
      <p><strong>Couper n'est pas affaiblir.</strong> Une couverture spontanée qu'on veut planter ne se taille pas avant d'être couverte : la coupe la fait repartir. On la couche sous la paille.
         Ce geste se note à part, et ne compte pas comme une perturbation.</p>
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
    etape: 'suivi-perturbation'<?php if ($parcelle !== null): ?>,
    contexte: <?= json_encode($titrePage, JSON_UNESCAPED_UNICODE) ?><?php endif; ?>
  });

  (function(){
    var tirette = document.getElementById('tirette');
    // Le panneau Données s'ouvre quand la page en a besoin : une saisie à reprendre, une intervention à compléter.
    if (tirette && document.body.dataset.ouvre === '1') tirette.click();
    ['pj-saisir', 'pj-saisir-vide'].forEach(function(id){
      var a = document.getElementById(id);
      if (a && tirette) a.addEventListener('click', function(ev){ ev.preventDefault(); tirette.click(); });
    });

    /* Le bureau ne défile jamais : la liste se réduit, puis se pagine en montrant autant de lignes
       que la place le permet. */
    var centre = document.querySelector('.pj-centre');
    var lignes = Array.prototype.slice.call(document.querySelectorAll('.pj-ligne'));
    var page = 0, parPage = lignes.length;
    function deborde(){ return centre.scrollHeight > centre.clientHeight + 1; }
    function afficher(){
      lignes.forEach(function(l, i){ l.style.display = (i >= page * parPage && i < (page + 1) * parPage) ? '' : 'none'; });
      var nav = document.getElementById('pj-nav');
      if (!nav) return;
      var pages = Math.max(1, Math.ceil(lignes.length / parPage));
      nav.classList.toggle('actif', pages > 1);
      document.getElementById('pj-page').textContent = 'Page ' + (page + 1) + ' sur ' + pages;
      document.getElementById('pj-prec').disabled = page === 0;
      document.getElementById('pj-suiv').disabled = page >= pages - 1;
    }
    function ajuster(){
      var zone = document.getElementById('pj-zone');
      if (!zone || !centre || !zone.offsetParent) return;
      var t = 15;
      zone.style.fontSize = t + 'px';
      page = 0; parPage = lignes.length; afficher();
      while (deborde() && t > 12) { t -= 0.5; zone.style.fontSize = t + 'px'; }
      if (deborde()) {
        var n = lignes.length;
        while (n > 1) { n--; parPage = n; afficher(); if (!deborde()) break; }
      }
    }
    var prec = document.getElementById('pj-prec'), suiv = document.getElementById('pj-suiv');
    if (prec) prec.addEventListener('click', function(){ page = Math.max(0, page - 1); afficher(); });
    if (suiv) suiv.addEventListener('click', function(){ page++; afficher(); });
    if (centre) {
      ajuster();
      window.addEventListener('resize', ajuster);
      // Les polices arrivent après le premier calcul : leur métrique change la hauteur des lignes.
      if (document.fonts && document.fonts.ready) document.fonts.ready.then(ajuster);
    }
  })();
</script>
</body>
</html>
