<?php

declare(strict_types=1);

/**
 * Espèces — le référentiel de végétaux d'un site : le cadre partagé, et vos surcharges.
 *
 * Chantier `chantier-caracteristiques-vegetaux.md`, Phase 2. `src/Web/Especes.php` existait depuis le chantier
 * « scénario de plantation » sans qu'aucune page ne l'écrive : celle-ci est la première. Hors parcours
 * (`etapes.js`), comme `membres.php`/`inviter.php` — mais avec un vrai contenu de bureau (la liste), d'où le
 * panneau Données plutôt que le simple cadre centré des autres pages hors parcours.
 *
 * Trois choses : **voir** le référentiel fusionné (cadre + surcharges), **éditer** une surcharge à la main —
 * même geste pour ajouter une espèce inconnue du cadre ou en corriger une —, et **importer** un fichier CSV,
 * en trois temps comme l'import d'un projet (vérifier, aperçu, écrire) : tout ou rien, aucune ligne à moitié.
 *
 * Pas de verrou de version ici (à la différence de Cultures/Interventions/Constats) : une surcharge locale
 * d'espèce est une donnée de référence, pas un objet du terrain suivi au jour le jour — le risque d'écrasement
 * concurrent est faible, et l'ajouter aurait compliqué l'import en lot sans besoin exprimé.
 */

require __DIR__ . '/../src/autoload.php';
require __DIR__ . '/../src/Configuration.php';
require __DIR__ . '/../src/Database.php';

use Syntropia\CalendrierLopez;
use Syntropia\Conduite;
use Syntropia\Configuration;
use Syntropia\Role;
use Syntropia\Scenario;
use Syntropia\Web\Authentification;
use Syntropia\Web\Especes;
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
$peutEcrire = $sites->peutEcrireSur($siteId, $compteId);
$especesService = new Especes();
$retour = Configuration::base() . 'especes.php';

const MAX_OCTETS_CSV = 1_000_000;
const MAX_LIGNES_CSV = 500;

// Un modèle à télécharger — un fichier déjà valide, à compléter ou à faire compléter, plutôt que de deviner le
// format depuis la seule liste des colonnes reconnues. Un GET simple : lecture seule, sans effet, pas de jeton
// nécessaire (déjà derrière `exige()` plus haut) — comme un lien de documentation, pas un formulaire.
if (($_GET['telecharger'] ?? '') === 'modele') {
    $modele = @file_get_contents(__DIR__ . '/../contenu/especes-modele-import.csv');
    if ($modele !== false) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="especes-modele-import.csv"');
        header('Cache-Control: no-store');
        header('Content-Length: ' . strlen($modele));
        echo $modele;
        exit;
    }
}

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

/**
 * Un fichier CSV (texte brut, `;`) vers des lignes prêtes pour `Especes::enregistrerLot()` — ou les erreurs qui
 * l'en empêchent. Tolérant aux colonnes absentes (seules celles de l'en-tête sont lues, comme le formulaire) ;
 * **tout ou rien** si une ligne échoue : le fichier entier est refusé, avec le motif de chaque ligne en cause,
 * plutôt qu'un import partiel dont l'état serait difficile à relire.
 *
 * Une colonne de l'en-tête inconnue de l'atelier (une banque de semences personnelle porte souvent plus de
 * colonnes que ce que l'atelier lit — quantité de lot, semencier, taux de germinance…) n'empêche plus l'import
 * depuis le 2026-09-23 (décision du porteur) : elle est simplement ignorée, et dite comme telle à l'aperçu —
 * jamais avalée en silence, pour qu'une vraie faute de frappe dans un nom de colonne attendu reste visible.
 *
 * @return array{ok: bool, lignes: list<array<string, mixed>>, erreurs: array<int|string, string>, ignorees: list<string>, total: int}
 */
$examinerCsv = static function (string $texte): array {
    $texte = (string) preg_replace('/^\xEF\xBB\xBF/', '', $texte);
    if (trim($texte) === '') {
        return ['ok' => false, 'lignes' => [], 'erreurs' => ['Le fichier est vide.'], 'ignorees' => [], 'total' => 0];
    }
    $flux = fopen('php://temp', 'r+b');
    fwrite($flux, $texte);
    rewind($flux);
    $entete = fgetcsv($flux, 0, ';', '"', '');
    if ($entete === false || $entete === null) {
        fclose($flux);
        return ['ok' => false, 'lignes' => [], 'erreurs' => ['La première ligne (l’en-tête des colonnes) est illisible.'], 'ignorees' => [], 'total' => 0];
    }
    $entete = array_map(static fn (string $c): string => trim($c), $entete);
    $connues = array_merge(['nom_scientifique', 'variete'], Especes::COLONNES);
    $ignorees = array_values(array_diff($entete, $connues));
    if (!in_array('nom_scientifique', $entete, true)) {
        fclose($flux);
        return ['ok' => false, 'lignes' => [], 'erreurs' => ['La colonne « nom_scientifique » est obligatoire : c’est la clé de chaque espèce.'], 'ignorees' => $ignorees, 'total' => 0];
    }
    $brutes = [];
    while (($r = fgetcsv($flux, 0, ';', '"', '')) !== false) {
        if ($r === [null]) {
            continue;
        }
        if (count($r) !== count($entete)) {
            fclose($flux);
            return ['ok' => false, 'lignes' => [], 'erreurs' => ['Ligne ' . (count($brutes) + 2) . ' : ' . count($r) . ' colonnes au lieu de ' . count($entete) . '.'], 'ignorees' => $ignorees, 'total' => 0];
        }
        $ligne = array_combine($entete, $r);
        foreach ($ignorees as $col) {
            unset($ligne[$col]);
        }
        $brutes[] = $ligne;
    }
    fclose($flux);
    if ($brutes === []) {
        return ['ok' => false, 'lignes' => [], 'erreurs' => ['Aucune ligne à importer sous l’en-tête.'], 'ignorees' => $ignorees, 'total' => 0];
    }
    if (count($brutes) > MAX_LIGNES_CSV) {
        return ['ok' => false, 'lignes' => [], 'erreurs' => [MAX_LIGNES_CSV . ' lignes au plus par import — divisez le fichier.'], 'ignorees' => $ignorees, 'total' => count($brutes)];
    }

    $pretes = [];
    $erreursLignes = [];
    foreach ($brutes as $i => $ligne) {
        $nom = trim((string) ($ligne['nom_scientifique'] ?? ''));
        $variete = trim((string) ($ligne['variete'] ?? ''));
        $motifs = [];
        if ($nom === '') {
            $motifs[] = 'nom scientifique manquant';
        } elseif (mb_strlen($nom) > 160) {
            $motifs[] = 'nom scientifique trop long';
        }
        if (mb_strlen($variete) > 120) {
            $motifs[] = 'variété trop longue';
        }
        $v = Especes::valider($ligne);
        foreach ($v['erreurs'] as $cle => $message) {
            $motifs[] = "$cle : $message";
        }
        if ($motifs !== []) {
            $erreursLignes[$i + 2] = implode(' ; ', $motifs);
            continue;
        }
        $pretes[] = ['nom_scientifique' => $nom, 'variete' => $variete] + $v['valeurs'];
    }
    if ($erreursLignes !== []) {
        return ['ok' => false, 'lignes' => [], 'erreurs' => $erreursLignes, 'ignorees' => $ignorees, 'total' => count($brutes)];
    }
    return ['ok' => true, 'lignes' => $pretes, 'erreurs' => [], 'ignorees' => $ignorees, 'total' => count($pretes)];
};

$erreurs = [];
$flash = $_SESSION['syntropia_flash_especes'] ?? null;
unset($_SESSION['syntropia_flash_especes']);
$onglet = 'esp';
$ouvre = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if (!$auth->jetonValide($_POST['csrf'] ?? null)) {
        http_response_code(403);
        $erreurs['jeton'] = 'Formulaire expiré — rechargez la page.';
    } elseif (!$peutEcrire) {
        http_response_code(403);
        $erreurs['droit'] = 'Vous lisez ce projet : il ne se modifie pas depuis votre compte.';
    } elseif ($action === 'enregistrer') {
        $onglet = 'esp';
        $ouvre = true;
        $res = $especesService->enregistrer($siteId, (string) ($_POST['nom_scientifique'] ?? ''), (string) ($_POST['variete'] ?? ''), $_POST);
        if ($res['erreurs'] === []) {
            $_SESSION['syntropia_flash_especes'] = ['ok', 'L’espèce est enregistrée.'];
            header('Location: ' . $retour);
            exit;
        }
        $erreurs = $res['erreurs'];
    } elseif ($action === 'retirer') {
        $especesService->retirer((int) ($_POST['espece_id'] ?? 0), $siteId);
        $_SESSION['syntropia_flash_especes'] = ['ok', 'La surcharge est retirée : ce site retrouve la version du cadre, si elle existe.'];
        header('Location: ' . $retour);
        exit;
    } elseif ($action === 'verifier') {
        $onglet = 'imp';
        $ouvre = true;
        unset($_SESSION['syntropia_import_especes']);
        $envoi = $_FILES['fichier'] ?? null;
        if (!is_array($envoi) || ($envoi['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $erreurs['fichier'] = 'Choisissez d’abord un fichier.';
        } elseif ($envoi['error'] === UPLOAD_ERR_INI_SIZE || $envoi['error'] === UPLOAD_ERR_FORM_SIZE || ($envoi['size'] ?? 0) > MAX_OCTETS_CSV) {
            $erreurs['fichier'] = 'Ce fichier dépasse 1 Mo.';
        } elseif ($envoi['error'] !== UPLOAD_ERR_OK || !is_uploaded_file((string) $envoi['tmp_name'])) {
            $erreurs['fichier'] = 'Le fichier n’a pas pu être reçu. Réessayez.';
        } else {
            $texte = (string) file_get_contents((string) $envoi['tmp_name']);
            $examen = $examinerCsv($texte);
            if ($examen['ok']) {
                $_SESSION['syntropia_import_especes'] = ['texte' => $texte, 'fichier' => mb_substr(basename((string) $envoi['name']), 0, 120)];
            } else {
                $erreurs['import'] = $examen['erreurs'];
            }
        }
    } elseif ($action === 'annuler_import') {
        unset($_SESSION['syntropia_import_especes']);
        header('Location: ' . $retour);
        exit;
    } elseif ($action === 'confirmer_import') {
        $onglet = 'imp';
        $ouvre = true;
        $attente = $_SESSION['syntropia_import_especes'] ?? null;
        if (!is_array($attente) || !is_string($attente['texte'] ?? null)) {
            $erreurs['import'] = ['Aucun fichier en attente : envoyez-le de nouveau.'];
        } else {
            // Revérifié, comme l'import d'un projet : ce qui a attendu dans la session n'est jamais écrit sans
            // repasser par le même contrôle — un changement de schéma entre-temps, par exemple, doit se voir.
            $examen = $examinerCsv($attente['texte']);
            if (!$examen['ok']) {
                unset($_SESSION['syntropia_import_especes']);
                $erreurs['import'] = $examen['erreurs'];
            } else {
                $res = $especesService->enregistrerLot($siteId, $examen['lignes']);
                unset($_SESSION['syntropia_import_especes']);
                if ($res['ok']) {
                    $_SESSION['syntropia_flash_especes'] = ['ok', $res['ecrites'] . ' espèce' . ($res['ecrites'] > 1 ? 's' : '') . ' importée' . ($res['ecrites'] > 1 ? 's' : '') . '.'];
                    header('Location: ' . $retour);
                    exit;
                }
                $erreurs['import'] = ['Ligne ' . $res['ligne'] . ' : ' . implode(' ; ', $res['erreurs']) . ' (rien n’a été écrit).'];
            }
        }
    } else {
        http_response_code(400);
        $erreurs['action'] = 'Cette action n’est pas reconnue.';
    }
}

$attenteImport = $_SESSION['syntropia_import_especes'] ?? null;
$apercuImport = null;
if (is_array($attenteImport) && is_string($attenteImport['texte'] ?? null)) {
    $examen = $examinerCsv($attenteImport['texte']);
    $apercuImport = $examen['ok']
        ? ['fichier' => $attenteImport['fichier'], 'total' => $examen['total'],
            'noms' => array_slice(array_column($examen['lignes'], 'nom_scientifique'), 0, 10),
            'ignorees' => $examen['ignorees']]
        : null;
    if (!$examen['ok']) {
        unset($_SESSION['syntropia_import_especes']);
    }
}

// Toutes rendues d'un coup : la pagination est du ressort du script en pied de page (le bureau ne défile
// jamais), pas d'un découpage serveur qu'un lien devrait faire avancer.
$especes = $especesService->toutes($siteId);

// Édition : une espèce du cadre ou de ce site, jamais celle d'un autre site.
$editionId = (int) ($_GET['espece'] ?? 0);
$edition = null;
if ($editionId > 0) {
    $candidate = $especesService->parId($editionId);
    if ($candidate !== null && ((int) $candidate['site_id'] === 0 || (int) $candidate['site_id'] === $siteId)) {
        $edition = $candidate;
        $ouvre = true;
        $onglet = 'esp';
    }
}
if (($_GET['nouvelle'] ?? '') === '1') {
    $ouvre = true;
    $onglet = 'esp';
}

$champsTexte = ['nom_scientifique', 'variete', 'nom_usuel', 'delai_germination_j', 'periode_semis',
    'periode_repiquage', 'periode_recolte', 'espacement_monoculture_m', 'espacement_retenu_m', 'plants_par_ml',
    'rapport_cn', 'conduite_taille', 'periode_taille', 'type_plant', 'orientation_bordure', 'source', 'statut_local', 'notes'];
$champsChoix = ['cycle', 'systeme', 'entree_scenario', 'sortie_scenario', 'biomasse', 'origine'];
$champsBool = ['serre_necessaire', 'bloque_herbe', 'deplacable'];
$f = array_fill_keys(array_merge($champsTexte, $champsChoix, $champsBool), '');
$source = $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'enregistrer' ? $_POST : ($edition ?? []);
foreach ($champsTexte as $c) {
    $f[$c] = (string) ($source[$c] ?? '');
}
foreach ($champsChoix as $c) {
    $f[$c] = (string) ($source[$c] ?? '');
}
foreach ($champsBool as $c) {
    $f[$c] = in_array((string) ($source[$c] ?? ''), ['1', 'on'], true) ? '1' : '';
}
$estSurcharge = $edition !== null && (int) $edition['site_id'] === $siteId;

// Les strates possibles : celles des deux conduites, avec leur libellé, pour les cases à cocher. La source est
// soit un tableau (les cases cochées d'un renvoi de formulaire), soit un texte `|` (la ligne enregistrée).
$labelsStrates = [];
foreach (Conduite::toutes() as $c) {
    $labelsStrates += $c->strates();
}
$strateBrute = $source['strates_possibles'] ?? '';
$strateSaisie = is_array($strateBrute)
    ? array_values(array_filter(array_map('strval', $strateBrute), static fn (string $x): bool => $x !== ''))
    : array_values(array_filter(explode('|', (string) $strateBrute), static fn (string $x): bool => $x !== ''));

// Le rôle : liste fermée depuis le 2026-09-23 (décision du porteur), plusieurs valeurs à la fois — même
// double forme que strates_possibles (tableau des cases cochées, ou texte `|` venu d'un import).
$labelsRoles = ['production' => 'Production'] + Scenario::FONCTIONS;
$roleBrut = $source['role'] ?? '';
$roleSaisi = is_array($roleBrut)
    ? array_values(array_filter(array_map('strval', $roleBrut), static fn (string $x): bool => $x !== ''))
    : array_values(array_filter(explode('|', (string) $roleBrut), static fn (string $x): bool => $x !== ''));

$dateFr = static fn (string $d): string => (new DateTimeImmutable($d))->format('d/m/Y');
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Espèces — Syntropia</title>
<link rel="stylesheet" href="assets/css/gabarit.css">
<style>
  :root{ --haut-bandeaux: 3.4rem; }
  /* `.bureau` (gabarit.css) centre un contenu court (align-items:center) — ici le contenu est une vraie
     liste, qui doit remplir le bureau pour que son overflow se mesure et se pagine. Même correctif que
     conception-potager.php/conception-scenario.php/conception-materiel-et-eau.php. */
  #bureau{ padding:.4rem .6rem; align-items:stretch; }

  .es-centre{ width:100%; min-height:0; display:flex; flex-direction:column; gap:.5rem; padding:.3rem .4rem .2rem; overflow:hidden; }
  .es-entete{ display:flex; align-items:center; gap:.7rem; flex-wrap:wrap; }
  .es-titre{ font:600 1rem var(--titre); color:var(--accent-titre); margin:0; }
  .es-badge{ font:500 .72rem var(--titre); color:var(--muted); border:1px solid var(--line); border-radius:1rem; padding:.1rem .55rem; }
  .es-actions{ margin-left:auto; }
  .es-bouton{ font:600 .8rem var(--titre); color:var(--bouton-encre); background:var(--accent); border:0; border-radius:.5rem; padding:.35rem .75rem; text-decoration:none; cursor:pointer; }
  .es-message{ margin:0; padding:.4rem .7rem; border-radius:.5rem; font:400 .82rem/1.35 var(--texte); background:var(--accent-fond); border:1px solid var(--accent); color:var(--accent-titre); }
  .es-message--alerte{ border-color:var(--alerte); color:var(--alerte); }
  .es-annonce{ margin:0; font:400 .84rem/1.4 var(--texte); color:var(--muted); }
  .es-zone{ font-size:15px; }
  .es-tableau{ width:100%; border-collapse:collapse; font:400 .84em var(--texte); color:var(--ink); }
  .es-tableau th{ text-align:left; font:600 .72em var(--titre); text-transform:uppercase; letter-spacing:.05em; color:var(--muted); padding:.25em .5em; border-bottom:1px solid var(--line); }
  .es-tableau td{ padding:.28em .5em; border-bottom:1px solid var(--line); vertical-align:top; }
  .es-tableau a{ color:var(--ink); text-decoration:none; font-weight:600; }
  .es-tableau a:hover{ text-decoration:underline; }
  .es-tableau small{ color:var(--muted); font-size:.85em; display:block; }
  .es-cadre-puce{ font:500 .68em var(--titre); color:var(--muted); border:1px solid var(--line); border-radius:1em; padding:.05em .5em; white-space:nowrap; }
  .es-surcharge-puce{ font:500 .68em var(--titre); color:var(--accent-titre); border:1px solid var(--accent); border-radius:1em; padding:.05em .5em; white-space:nowrap; }
  .es-page-nav{ display:none; align-items:center; gap:.6rem; font:400 .78rem var(--texte); color:var(--muted); }
  .es-page-nav.actif{ display:flex; }
  .es-page-nav button{ font:600 .78rem var(--titre); color:var(--accent-titre); background:transparent; border:1px solid var(--accent); border-radius:.4rem; padding:.15rem .6rem; cursor:pointer; }
  .es-page-nav button:disabled{ opacity:.4; cursor:default; }
  .es-vide{ margin:auto; max-width:26rem; text-align:center; font:400 .92rem/1.5 var(--texte); color:var(--muted); }

  .es-onglets{ display:flex; gap:.3rem; margin-bottom:.7rem; border-bottom:1px solid var(--line); }
  .es-onglet{ flex:1; text-align:center; padding:.35rem .3rem; cursor:pointer; border-radius:.5rem .5rem 0 0; font:500 .8rem var(--titre); color:var(--muted); border:1px solid transparent; border-bottom:0; }
  .es-radio{ position:absolute; opacity:0; pointer-events:none; }
  .pd-corps:has(#o-esp:checked) label[for=o-esp], .pd-corps:has(#o-imp:checked) label[for=o-imp]{
    color:var(--accent-titre); background:var(--accent-fond); border-color:var(--line); font-weight:600; }
  .es-panneau{ display:none; }
  .pd-corps:has(#o-esp:checked) .es-p-esp, .pd-corps:has(#o-imp:checked) .es-p-imp{ display:block; }
  .es-form label.es-champ{ display:block; font:500 .78rem var(--titre); color:var(--ink); margin:0 0 .5rem; }
  .es-form label.es-champ small{ font:400 .68rem var(--texte); color:var(--muted); }
  .es-form input[type=text], .es-form input[type=number], .es-form input[type=file], .es-form select, .es-form textarea{
    display:block; width:100%; margin-top:.15rem; padding:.36rem .5rem; font:400 .84rem var(--texte); color:var(--ink);
    background:var(--surface); border:1px solid var(--line); border-radius:.45rem;
  }
  .es-form textarea{ resize:vertical; min-height:2.6rem; }
  .es-form input:focus, .es-form select:focus, .es-form textarea:focus{ outline:2px solid var(--accent); outline-offset:1px; }
  .es-deux{ display:grid; grid-template-columns:1fr 1fr; gap:.5rem; }
  .es-note{ font:400 .74rem/1.4 var(--texte); color:var(--muted); margin:.2rem 0 .6rem; }
  .es-fonctions-detail{ margin:0 0 .6rem; }
  .es-fonctions-detail summary{ font:500 .8rem var(--titre); color:var(--accent-titre); cursor:pointer; }
  .es-fonctions{ display:grid; gap:.15rem; margin-top:.4rem; max-height:9rem; overflow-y:auto; }
  .es-fonctions label{ display:flex; gap:.4rem; align-items:flex-start; font:400 .76rem/1.3 var(--texte); color:var(--ink); }
  .es-choix{ display:flex; gap:.5rem; align-items:flex-start; margin:0 0 .5rem; font:400 .82rem/1.35 var(--texte); color:var(--ink); }
  .es-erreurs{ margin:0 0 .7rem; padding:.5rem .7rem; border:1px solid var(--alerte); border-radius:.5rem; color:var(--alerte); font:400 .8rem/1.35 var(--texte); }
  .es-erreurs ul{ margin:.2rem 0 0 1rem; padding:0; }
  .es-retrait{ margin-top:1rem; padding-top:.6rem; border-top:1px solid var(--line); }
  .es-retrait button{ font:600 .74rem var(--titre); color:var(--alerte); background:transparent; border:1px solid var(--alerte); border-radius:.4rem; padding:.2rem .6rem; cursor:pointer; }
  .es-lien{ font:500 .76rem var(--titre); color:var(--accent-titre); }
  .es-apercu{ border:1px dashed var(--line); border-radius:.5rem; padding:.5rem .7rem; margin:.4rem 0; font:400 .82rem/1.4 var(--texte); color:var(--ink); }
  .es-apercu ul{ margin:.3rem 0; padding-left:1.1rem; }
  .es-cols{ font:400 .74rem/1.5 var(--texte); color:var(--muted); word-break:break-word; }
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
      <span class="bg-centre-titre">Espèces</span>
    </div>
    <div class="bg-droite">
      <button class="theme-bascule" id="theme-bascule" aria-label="Changer de thème" title="Thème">
        <span class="theme-icone" id="theme-icone" aria-hidden="true">◐</span>
      </button>
      <div class="compte-conteneur">
        <button class="compte" id="compte-bouton" type="button" aria-haspopup="true" aria-expanded="false" aria-controls="compte-menu">
          <span class="compte-avatar"><?= $h(mb_strtoupper(mb_substr($auth->nomAffiche() ?? '', 0, 2))) ?></span>
          <span class="compte-nom"><?= $h($auth->nomAffiche() ?? '') ?></span>
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
            <li><a href="deconnexion.php"><span class="pg-puce">⏻</span><span class="pg-libelle">Se déconnecter</span></a></li>
          </ul>
        </div>
        <p class="pg-pied">Le cadre partagé, et vos espèces — ce qui nourrit le calendrier de plantation.</p>
      </div>
    </nav>

    <main class="bureau" id="bureau">
      <div class="es-centre" id="es-centre">
        <div class="es-entete">
          <p class="es-titre">Espèces</p>
          <span class="es-badge"><?= count($especes) ?> espèce<?= count($especes) > 1 ? 's' : '' ?></span>
<?php if ($peutEcrire): ?>
          <span class="es-actions"><a class="es-bouton" href="<?= $h($retour) ?>?nouvelle=1" id="es-ajouter">Ajouter une espèce</a></span>
<?php endif; ?>
        </div>
        <p class="es-annonce">Le cadre partagé de l'atelier, et les vôtres : ce qui nourrit le calendrier de
           plantation d'un potager (semis, repiquage, récolte) et les suggestions du scénario.</p>
<?php if ($flash !== null): ?>
        <p class="es-message" role="status"><?= $h($flash[1]) ?></p>
<?php endif; ?>
<?php if ($especes === []): ?>
        <p class="es-vide">Aucune espèce pour l'instant.</p>
<?php else: ?>
        <div class="es-zone" id="es-zone">
          <table class="es-tableau">
            <thead><tr><th>Espèce</th><th>Rôle</th><th>Calendrier</th><th>Origine</th></tr></thead>
            <tbody>
<?php foreach ($especes as $e):
    $nomAffiche = (string) ($e['nom_usuel'] ?: $e['nom_scientifique']);
    $cal = array_filter([
        $e['periode_semis'] !== null ? 'S ' . $e['periode_semis'] : '',
        $e['periode_repiquage'] !== null ? 'R ' . $e['periode_repiquage'] : '',
        $e['periode_recolte'] !== null ? 'Ré ' . $e['periode_recolte'] : '',
    ]);
    $local = (int) $e['site_id'] === $siteId; ?>
              <tr class="es-item">
                <td><?php if ($peutEcrire): ?><a href="<?= $h($retour) ?>?espece=<?= (int) $e['id'] ?>"><?= $h($nomAffiche) ?></a><?php else: ?><b><?= $h($nomAffiche) ?></b><?php endif; ?>
                  <?php if ((string) $e['variete'] !== ''): ?><small><?= $h($e['variete']) ?></small><?php endif; ?>
                  <?php if (($e['cycle'] ?? null) !== null): ?><small class="es-cycle"><?= $h(mb_strtolower(explode(' (', Especes::CYCLES[$e['cycle']] ?? $e['cycle'])[0])) ?></small><?php endif; ?></td>
                <td><?= $h(implode(' · ', array_map(static fn (string $c): string => $labelsRoles[$c] ?? $c, array_filter(explode('|', (string) ($e['role'] ?? '')))))) ?></td>
                <td><?= $cal !== [] ? $h(implode(' · ', $cal)) : '' ?></td>
                <td><?= $local ? '<span class="es-surcharge-puce">votre surcharge</span>' : '<span class="es-cadre-puce">cadre</span>' ?></td>
              </tr>
<?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="es-page-nav" id="es-nav">
          <button type="button" id="es-prec">← Précédentes</button><span id="es-page"></span><button type="button" id="es-suiv">Suivantes →</button>
        </div>
<?php endif; ?>
      </div>
    </main>
  </div>

  <div class="scrim" id="scrim-droit"></div>
  <button class="tirette" id="tirette" aria-expanded="false" aria-controls="panneau-droit"
          aria-label="Afficher le panneau Données">
    <svg class="tirette-triangle" viewBox="0 0 10 10" aria-hidden="true"><polygon points="1,1 9,5 1,9" fill="currentColor"/></svg>
    <span class="tirette-texte">Données</span>
  </button>
<?php if ($peutEcrire): ?>
  <aside class="panneau-droit" id="panneau-droit" aria-hidden="true">
    <div class="pd-corps">
      <input class="es-radio" type="radio" name="onglet" id="o-esp"<?= $onglet === 'esp' ? ' checked' : '' ?>>
      <input class="es-radio" type="radio" name="onglet" id="o-imp"<?= $onglet === 'imp' ? ' checked' : '' ?>>
      <div class="es-onglets" role="tablist">
        <label class="es-onglet" for="o-esp">Une espèce</label>
        <label class="es-onglet" for="o-imp">Importer</label>
      </div>
<?php if ($erreurs !== []): ?>
      <div class="es-erreurs" role="alert"><strong>Votre saisie est à reprendre.</strong>
        <ul><?php foreach ($erreurs as $e): ?><?php foreach ((is_array($e) ? $e : [$e]) as $msg): ?><li><?= $h($msg) ?></li><?php endforeach; ?><?php endforeach; ?></ul></div>
<?php endif; ?>

      <section class="es-panneau es-p-esp">
        <p class="pd-titre"><?= $edition !== null ? 'Modifier : ' . $h((string) ($edition['nom_usuel'] ?: $edition['nom_scientifique'])) : 'Ajouter une espèce' ?></p>
<?php if ($edition !== null && !$estSurcharge): ?>
        <p class="es-note">Cette espèce vient du cadre partagé. L'enregistrer pose une <strong>surcharge</strong> propre à
           ce site — le cadre lui-même ne change pas, et les autres sites gardent sa version.</p>
<?php endif; ?>
        <form method="post" action="<?= $h($retour) ?>" class="es-form">
          <input type="hidden" name="csrf" value="<?= $h($auth->jeton()) ?>">
          <input type="hidden" name="action" value="enregistrer">
          <label class="es-champ">Nom scientifique <small>la clé vers Plantae</small>
            <input type="text" name="nom_scientifique" maxlength="160" required value="<?= $h($f['nom_scientifique']) ?>" placeholder="Ex. Symphytum officinale">
          </label>
          <div class="es-deux">
            <label class="es-champ">Variété <small>facultatif</small>
              <input type="text" name="variete" maxlength="120" value="<?= $h($f['variete']) ?>">
            </label>
            <label class="es-champ">Nom usuel
              <input type="text" name="nom_usuel" maxlength="160" value="<?= $h($f['nom_usuel']) ?>" placeholder="Ex. Consoude">
            </label>
          </div>
          <label class="es-champ">Cycle <small>annuelle ou pérenne — les bordures retiennent les vivaces et les ligneuses</small>
            <select name="cycle">
              <option value="">—</option>
<?php foreach (Especes::CYCLES as $cle => $lib): ?>
              <option value="<?= $cle ?>"<?= $f['cycle'] === $cle ? ' selected' : '' ?>><?= $h($lib) ?></option>
<?php endforeach; ?>
            </select>
          </label>
          <label class="es-champ">Délai de germination (jours) <small>annuelle ou bisannuelle semée en place : la levée le suit</small>
            <input type="number" name="delai_germination_j" min="1" max="365" step="1" inputmode="numeric" value="<?= $h($f['delai_germination_j']) ?>">
          </label>
          <p class="pd-titre" style="margin-top:.6rem">Calendrier <small style="font-weight:400">— en mots, comme « fin avril » ou « mi à fin juillet »</small></p>
          <label class="es-champ">Semis à l'abri
            <input type="text" name="periode_semis" maxlength="60" value="<?= $h($f['periode_semis']) ?>" placeholder="Ex. mars-avril">
          </label>
          <label class="es-champ">Repiquage <small>semis en place, ou repiquage</small>
            <input type="text" name="periode_repiquage" maxlength="60" value="<?= $h($f['periode_repiquage']) ?>" placeholder="Ex. mai">
          </label>
          <label class="es-champ">Récolte
            <input type="text" name="periode_recolte" maxlength="60" value="<?= $h($f['periode_recolte']) ?>">
          </label>
          <details class="es-fonctions-detail"<?= $roleSaisi !== [] ? ' open' : '' ?>>
            <summary>Rôle (<?= count($roleSaisi) ?>)</summary>
            <p class="es-note">Ce à quoi sert l'espèce, une ou plusieurs à la fois — production, ou une fonction de
              service comme le scénario et le plan de culture les connaissent déjà.</p>
            <div class="es-fonctions">
<?php foreach ($labelsRoles as $cle => $lib): ?>
              <label><input type="checkbox" class="case" name="role[]" value="<?= $h($cle) ?>"<?= in_array($cle, $roleSaisi, true) ? ' checked' : '' ?>> <?= $h($lib) ?></label>
<?php endforeach; ?>
            </div>
          </details>
          <div class="es-deux">
            <label class="es-champ">Espacement retenu (m)
              <input type="number" name="espacement_retenu_m" min="0.01" max="50" step="0.01" inputmode="decimal" value="<?= $h($f['espacement_retenu_m']) ?>">
            </label>
            <label class="es-champ">Espacement en monoculture (m)
              <input type="number" name="espacement_monoculture_m" min="0.01" max="50" step="0.01" inputmode="decimal" value="<?= $h($f['espacement_monoculture_m']) ?>">
            </label>
          </div>
          <details>
            <summary>Plus de détails</summary>
            <label class="es-champ" style="margin-top:.5rem">Strates possibles</label>
            <div class="es-fonctions">
<?php foreach ($labelsStrates as $cle => $lib): ?>
              <label><input type="checkbox" class="case" name="strates_possibles[]" value="<?= $h($cle) ?>"<?= in_array($cle, $strateSaisie, true) ? ' checked' : '' ?>> <?= $h($lib) ?></label>
<?php endforeach; ?>
            </div>
            <div class="es-deux" style="margin-top:.5rem">
              <label class="es-champ">Système
                <select name="systeme">
                  <option value="">—</option>
<?php foreach (['colonisation' => 'Colonisation', 'accumulation' => 'Accumulation', 'abondance' => 'Abondance'] as $cle => $lib): ?>
                  <option value="<?= $cle ?>"<?= $f['systeme'] === $cle ? ' selected' : '' ?>><?= $lib ?></option>
<?php endforeach; ?>
                </select>
              </label>
              <label class="es-champ">Biomasse
                <select name="biomasse">
                  <option value="">—</option>
<?php foreach (['+', '++', '+++'] as $b): ?>
                  <option value="<?= $b ?>"<?= $f['biomasse'] === $b ? ' selected' : '' ?>><?= $b ?></option>
<?php endforeach; ?>
                </select>
              </label>
            </div>
            <div class="es-deux">
              <label class="es-champ">Entrée du scénario
                <select name="entree_scenario">
                  <option value="">—</option>
<?php foreach (Scenario::PERIODES as $cle => [$lib, ]): ?>
                  <option value="<?= $cle ?>"<?= $f['entree_scenario'] === $cle ? ' selected' : '' ?>><?= $h($lib) ?></option>
<?php endforeach; ?>
                </select>
              </label>
              <label class="es-champ">Sortie du scénario
                <select name="sortie_scenario">
                  <option value="">—</option>
<?php foreach (Scenario::PERIODES as $cle => [$lib, ]): ?>
                  <option value="<?= $cle ?>"<?= $f['sortie_scenario'] === $cle ? ' selected' : '' ?>><?= $h($lib) ?></option>
<?php endforeach; ?>
                </select>
              </label>
            </div>
            <label class="es-champ">Plants par mètre linéaire
              <input type="number" name="plants_par_ml" min="0.01" max="1000" step="0.01" inputmode="decimal" value="<?= $h($f['plants_par_ml']) ?>">
            </label>
            <label class="es-champ">Rapport C/N
              <input type="text" name="rapport_cn" maxlength="24" value="<?= $h($f['rapport_cn']) ?>">
            </label>
            <label class="es-champ">Conduite de taille
              <input type="text" name="conduite_taille" maxlength="120" value="<?= $h($f['conduite_taille']) ?>">
            </label>
            <label class="es-champ">Période de taille
              <input type="text" name="periode_taille" maxlength="120" value="<?= $h($f['periode_taille']) ?>">
            </label>
            <div class="es-deux">
              <label class="es-champ">Type de plant
                <input type="text" name="type_plant" maxlength="60" value="<?= $h($f['type_plant']) ?>">
              </label>
              <label class="es-champ">Origine
                <select name="origine">
                  <option value="">—</option>
<?php foreach (['achat' => 'Achat', 'multiplication' => 'Multiplication', 'rna' => 'RNA', 'mixte' => 'Mixte'] as $cle => $lib): ?>
                  <option value="<?= $cle ?>"<?= $f['origine'] === $cle ? ' selected' : '' ?>><?= $lib ?></option>
<?php endforeach; ?>
                </select>
              </label>
            </div>
            <input type="hidden" name="serre_necessaire" value="0">
            <label class="es-choix"><input type="checkbox" class="case" name="serre_necessaire" value="1"<?= $f['serre_necessaire'] === '1' ? ' checked' : '' ?>> Serre nécessaire</label>
            <input type="hidden" name="bloque_herbe" value="0">
            <label class="es-choix"><input type="checkbox" class="case" name="bloque_herbe" value="1"<?= $f['bloque_herbe'] === '1' ? ' checked' : '' ?>> Bloque l'herbe</label>
            <input type="hidden" name="deplacable" value="0">
            <label class="es-choix"><input type="checkbox" class="case" name="deplacable" value="1"<?= $f['deplacable'] === '1' ? ' checked' : '' ?>> Déplaçable</label>
            <label class="es-champ">Orientation de bordure
              <input type="text" name="orientation_bordure" maxlength="60" value="<?= $h($f['orientation_bordure']) ?>">
            </label>
            <label class="es-champ">Source <small>d'où vient cette information</small>
              <input type="text" name="source" maxlength="120" value="<?= $h($f['source']) ?>">
            </label>
            <label class="es-champ">Statut local
              <input type="text" name="statut_local" maxlength="60" value="<?= $h($f['statut_local']) ?>">
            </label>
            <label class="es-champ">Notes
              <textarea name="notes" maxlength="2000" rows="2"><?= $h($f['notes']) ?></textarea>
            </label>
          </details>
          <button type="submit" class="pt-bouton" style="margin-top:.6rem">Enregistrer</button>
<?php if ($edition !== null): ?>
          <a class="es-lien" href="<?= $h($retour) ?>" style="margin-left:.6rem">Fermer</a>
<?php endif; ?>
        </form>
<?php if ($estSurcharge): ?>
        <div class="es-retrait">
          <form method="post" action="<?= $h($retour) ?>" onsubmit="return confirm('Retirer votre surcharge de cette espèce ? Ce site retrouvera la version du cadre, si elle existe.');">
            <input type="hidden" name="csrf" value="<?= $h($auth->jeton()) ?>">
            <input type="hidden" name="action" value="retirer">
            <input type="hidden" name="espece_id" value="<?= (int) $edition['id'] ?>">
            <button type="submit">Retirer votre surcharge</button>
          </form>
        </div>
<?php endif; ?>
      </section>

      <section class="es-panneau es-p-imp">
        <p class="pd-titre">Importer un fichier</p>
<?php if ($apercuImport !== null): ?>
        <div class="es-apercu">
          <p><strong><?= $h($apercuImport['fichier']) ?></strong> — <?= $apercuImport['total'] ?> espèce<?= $apercuImport['total'] > 1 ? 's' : '' ?> prête<?= $apercuImport['total'] > 1 ? 's' : '' ?> à écrire.</p>
          <ul><?php foreach ($apercuImport['noms'] as $n): ?><li><?= $h($n) ?></li><?php endforeach; ?><?php if ($apercuImport['total'] > count($apercuImport['noms'])): ?><li>… et <?= $apercuImport['total'] - count($apercuImport['noms']) ?> autre<?= $apercuImport['total'] - count($apercuImport['noms']) > 1 ? 's' : '' ?></li><?php endif; ?></ul>
<?php if ($apercuImport['ignorees'] !== []): ?>
          <p class="es-note">Colonne<?= count($apercuImport['ignorees']) > 1 ? 's' : '' ?> ignorée<?= count($apercuImport['ignorees']) > 1 ? 's' : '' ?> (pas lue<?= count($apercuImport['ignorees']) > 1 ? 's' : '' ?> par l'atelier) : <?= $h(implode(', ', $apercuImport['ignorees'])) ?>.</p>
<?php endif; ?>
          <p class="es-note">Une espèce déjà connue de ce site (cadre ou surcharge) verra sa surcharge mise à jour ; les autres deviennent des surcharges nouvelles. Rien n'est écrit avant confirmation.</p>
        </div>
        <form method="post" action="<?= $h($retour) ?>">
          <input type="hidden" name="csrf" value="<?= $h($auth->jeton()) ?>">
          <input type="hidden" name="action" value="confirmer_import">
          <button type="submit" class="pt-bouton">Confirmer l'import</button>
        </form>
        <form method="post" action="<?= $h($retour) ?>" style="margin-top:.4rem">
          <input type="hidden" name="csrf" value="<?= $h($auth->jeton()) ?>">
          <input type="hidden" name="action" value="annuler_import">
          <button type="submit" class="es-lien" style="background:none;border:0;cursor:pointer;padding:0">Annuler</button>
        </form>
<?php else: ?>
        <p class="es-note">Un fichier CSV (séparateur <code>;</code>), une ligne d'en-tête avec les noms de colonnes
           attendus, une ligne par espèce. Seule <code>nom_scientifique</code> est obligatoire ; les autres colonnes
           peuvent manquer, une case peut rester vide. Une colonne que l'atelier ne connaît pas n'empêche pas
           l'import : elle est simplement ignorée, et dite à l'aperçu. Le fichier est vérifié en entier avant
           d'écrire quoi que ce soit : s'il y a une erreur sur une ligne attendue, rien n'est écrit.</p>
        <p class="es-cols">Colonnes reconnues : nom_scientifique, variete, <?= $h(implode(', ', Especes::COLONNES)) ?>.</p>
        <p class="es-note"><a class="es-lien" href="?telecharger=modele">Télécharger un modèle</a> — un fichier déjà
           valide, à remplir ou à faire remplir, plutôt que de partir de la seule liste des colonnes.</p>
        <form method="post" action="<?= $h($retour) ?>" enctype="multipart/form-data" class="es-form">
          <input type="hidden" name="csrf" value="<?= $h($auth->jeton()) ?>">
          <input type="hidden" name="action" value="verifier">
          <label class="es-champ">Le fichier
            <input type="file" name="fichier" accept=".csv,text/csv" required>
          </label>
          <button type="submit" class="pt-bouton">Vérifier</button>
        </form>
<?php endif; ?>
      </section>
    </div>
  </aside>
<?php endif; ?>

  <div class="scrim" id="scrim-info"></div>
  <button class="tirette-info" id="tirette-info" aria-expanded="false" aria-controls="panneau-info"
          aria-label="Afficher les informations">
    <svg class="tirette-triangle" viewBox="0 0 10 10" aria-hidden="true"><polygon points="1,1 9,5 1,9" fill="currentColor"/></svg>
    <span class="tirette-texte">Informations</span>
  </button>
  <aside class="panneau-info" id="panneau-info" aria-hidden="true">
    <div class="pi-corps">
      <p>Le référentiel d'espèces sert à deux choses : proposer, dans le plan de culture d'un potager, le
         calendrier d'une culture connue (comme le fait déjà le tableau de Yann Lopez, pour dix-sept d'entre
         elles) ; et suggérer, dans le scénario d'une parcelle pérenne, l'espacement et le rôle d'un végétal.</p>
      <p><strong>Le cadre</strong> est partagé par tous les sites — 46 espèces curatées au départ. <strong>Une
         surcharge</strong> est propre à ce site : elle prime sur le cadre pour la même espèce, sans le changer
         pour les autres. La retirer rend au site la version du cadre, si elle existe.</p>
      <p><strong>Le calendrier</strong> se dit en mots, comme le tableau de Lopez (« fin avril », « mi à fin
         juillet ») — des repères indicatifs, pas des dates : c'est vous qui les corrigez à l'usage.</p>
      <p><strong>Le rôle</strong> se choisit dans une liste fermée, une ou plusieurs valeurs à la fois — production,
         ou une fonction de service, le même vocabulaire que le scénario pérenne et le plan de culture du
         potager (biomasse, bloque-herbe, tuteur…) : pour ne pas en tenir deux.</p>
    </div>
  </aside>

  <footer class="pied">
    <form class="pied-recherche" onsubmit="return false;">
      <input type="search" placeholder="Rechercher dans le site…" aria-label="Rechercher dans le site">
      <button type="submit" aria-label="Rechercher">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="10.5" cy="10.5" r="6.5"/><line x1="15.5" y1="15.5" x2="21" y2="21"/></svg>
      </button>
    </form>
    <span class="pied-mention">Syntropia — en construction</span>
    <span class="pied-spacer"></span>
  </footer>

</div>

<script src="assets/js/etapes.js"></script>
<script src="assets/js/gabarit.js"></script>
<script>
  Gabarit.init({ site: <?= json_encode($site['nom'] ?? '', JSON_UNESCAPED_UNICODE) ?> });
  (function(){
    var tirette = document.getElementById('tirette');
    if (tirette && document.body.dataset.ouvre === '1') tirette.click();
    var ajouter = document.getElementById('es-ajouter');
    if (ajouter && tirette) ajouter.addEventListener('click', function(ev){ ev.preventDefault(); tirette.click(); });

    /* Le bureau ne défile jamais : la liste se réduit, puis se pagine. */
    var centre = document.querySelector('.es-centre');
    var zone = document.getElementById('es-zone');
    var items = Array.prototype.slice.call(document.querySelectorAll('.es-item'));
    var page = 0, parPage = items.length;
    function deborde(){ return centre.scrollHeight > centre.clientHeight + 1; }
    function afficher(){
      items.forEach(function(l, i){ l.style.display = (i >= page * parPage && i < (page + 1) * parPage) ? '' : 'none'; });
      var nav = document.getElementById('es-nav');
      if (!nav) return;
      var pages = Math.max(1, Math.ceil(items.length / parPage));
      nav.classList.toggle('actif', pages > 1);
      document.getElementById('es-page').textContent = 'Page ' + (page + 1) + ' sur ' + pages;
      document.getElementById('es-prec').disabled = page === 0;
      document.getElementById('es-suiv').disabled = page >= pages - 1;
    }
    function ajuster(){
      if (!zone || !centre || items.length === 0) return;
      var t = 15;
      zone.style.fontSize = t + 'px';
      page = 0; parPage = items.length;
      afficher();
      while (deborde() && t > 12) { t -= 0.5; zone.style.fontSize = t + 'px'; }
      if (deborde()) {
        var n = items.length;
        while (n > 1) { n--; parPage = n; afficher(); if (!deborde()) break; }
      }
    }
    var prec = document.getElementById('es-prec'), suiv = document.getElementById('es-suiv');
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
