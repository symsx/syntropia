<?php

declare(strict_types=1);

/**
 * Le scénario de plantation d'une parcelle — le tableau temps × strates.
 *
 * Étape 4 de Conception. Chantier `chantier-scenario-plantation.md`, Phase 4.
 * Le tableau tient dans le bureau **sans défiler** : en syntropie une frise
 * SVG (la largeur du temps, une voie par plante) qui s'ajuste toujours à la
 * place disponible ; sur téléphone, et en jardin-forêt (qui n'a pas d'axe du
 * temps), une vue par strate en pastilles. La saisie vit dans le panneau
 * Données (un végétal, ou les productions de la parcelle), le guide de
 * composition dans le panneau Informations.
 *
 * Toute écriture passe par `Scenarios` (verrou et jeton de version) : cette
 * page ne fait que lire la saisie, la faire valider par `Scenario::valider()`
 * et montrer ce que le service refuse. Un POST se termine par une
 * redirection ; ce qui a été tapé n'est perdu que s'il a réussi.
 */

require __DIR__ . '/../src/autoload.php';
require __DIR__ . '/../src/Configuration.php';
require __DIR__ . '/../src/Database.php';

use Syntropia\Conduite;
use Syntropia\Configuration;
use Syntropia\Role;
use Syntropia\SautDuChat;
use Syntropia\Scenario;
use Syntropia\Stade;
use Syntropia\Web\Authentification;
use Syntropia\Web\Especes;
use Syntropia\Web\Heritage;
use Syntropia\Web\Parcelles;
use Syntropia\Web\Scenarios;
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
$scenarios = new Scenarios();

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$parcelles = $parcellesService->dUnSite($siteId);
$parcelle = null;
$demande = (int) ($_GET['parcelle'] ?? 0);
foreach ($parcelles as $p) {
    if ((int) $p['id'] === $demande) {
        $parcelle = $p;
        break;
    }
}
$parcelle ??= $parcelles[0] ?? null;
// Un potager se compose par planches, non par un scénario de strates et de temps : `conception-potager.php`.
if ($parcelle !== null && ($parcelle['type'] ?? '') === 'potager' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Location: ' . Configuration::base() . 'conception-potager.php?parcelle=' . (int) $parcelle['id']);
    exit;
}

$erreurs = [];
$idP = 0;
$peutAjouter = false;
$ouvre = false;          // le panneau Données s'ouvre tout seul
$onglet = 'veg';         // 'veg' ou 'prod'
$flash = $_SESSION['syntropia_flash_scenario'] ?? null;
unset($_SESSION['syntropia_flash_scenario']);

if ($parcelle !== null) {
    $idP = (int) $parcelle['id'];
    $retour = Configuration::base() . 'conception-scenario.php?parcelle=' . $idP;
    ['conduite' => $conduite, 'niveau' => $niveauConduite] = Heritage::conduite($parcelle, $site);
    $conduiteDefinie = $niveauConduite !== 'aucun';
    $lignes = $parcellesService->scenario($idP);
    $parId = array_column($lignes, null, 'id');
    $selection = Scenario::selectionDe($parcelle);
    $aUnePrincipale = array_filter($selection, static fn (array $s): bool => $s['priorite'] === 'principale') !== [];
    $peutAjouter = $peutEcrire && $conduiteDefinie && $aUnePrincipale;
    $souhaitsDuSite = $scenarios->souhaitsDuSite($siteId, $participantId);
    $prepareSaut = SautDuChat::conception($lignes, $conduite->scenarioADesTemps(), (string) ($site['ressource_eau'] ?? ''), $conduite->strates(), Stade::depuis($parcelle['stade'] ?? null));

    $editId = (int) ($_GET['vegetal'] ?? 0);
    if ($editId > 0 && !isset($parId[$editId])) {
        $erreurs[] = 'Ce végétal n’appartient pas à cette parcelle.';
        $editId = 0;
    }
    $form = Scenario::formulaire($parId[$editId] ?? []);
    if ($editId === 0 && isset($conduite->strates()[(string) ($_GET['strate'] ?? '')])) {
        $form['strate'] = (string) $_GET['strate'];
    }
    if ($editId > 0 || isset($_GET['strate']) || isset($_GET['nouveau'])) {
        $ouvre = true;
    }
    if (isset($_GET['productions'])) {
        $ouvre = true;
        $onglet = 'prod';
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = (string) ($_POST['action'] ?? '');
        $ouvre = true;
        $onglet = $action === 'productions' ? 'prod' : 'veg';
        if (!$peutEcrire) {
            http_response_code(403);
            $erreurs[] = 'Vous lisez ce projet : il ne se modifie pas depuis votre compte.';
        } elseif (!$auth->jetonValide($_POST['csrf'] ?? null)) {
            http_response_code(403);
            $erreurs[] = 'Formulaire expiré — rechargez la page.';
        } elseif ($action === 'productions') {
            // Ce qui est proposé : les souhaits de la personne (ou du porteur) et
            // ce que la parcelle a déjà retenu. Rien d'autre ne passe, hormis la
            // ligne « une autre production », validée par Scenarios::productions().
            $offerts = [];
            foreach (array_merge($souhaitsDuSite['souhaits'], array_column($selection, 'texte')) as $t) {
                $offerts[mb_strtolower($t)] = true;
            }
            $principales = $secondaires = [];
            $textes = is_array($_POST['texte'] ?? null) ? $_POST['texte'] : [];
            $niveaux = is_array($_POST['niveau'] ?? null) ? $_POST['niveau'] : [];
            foreach ($textes as $i => $texte) {
                $niveau = (string) ($niveaux[$i] ?? '');
                if ($niveau === '') {
                    continue;
                }
                if (!is_string($texte) || !isset($offerts[mb_strtolower($texte)]) || !in_array($niveau, ['principale', 'secondaire'], true)) {
                    $erreurs[] = 'Choisissez parmi les productions proposées.';
                    break;
                }
                $niveau === 'principale' ? $principales[] = $texte : $secondaires[] = $texte;
            }
            $autre = trim((string) ($_POST['autre'] ?? ''));
            if ($autre !== '' && $erreurs === []) {
                ($_POST['autre_niveau'] ?? '') === 'secondaire' ? $secondaires[] = $autre : $principales[] = $autre;
            }
            if ($erreurs === []) {
                try {
                    $scenarios->productions($idP, $compteId, (string) ($_POST['version'] ?? ''), $principales, $secondaires);
                    $_SESSION['syntropia_flash_scenario'] = ['ok', 'Les productions de la parcelle sont enregistrées.'];
                    header('Location: ' . $retour);
                    exit;
                } catch (\RuntimeException $e) {
                    http_response_code(409);
                    $erreurs[] = $e->getMessage();
                } catch (\PDOException $e) {
                    error_log('Scénario : ' . $e->getMessage());
                    http_response_code(500);
                    $erreurs[] = "L'enregistrement a échoué. Réessayez dans un instant.";
                }
            }
        } elseif (in_array($action, ['vegetal', 'supprimer'], true)) {
            $editId = (int) ($_POST['vegetal_id'] ?? 0);
            $form = $_POST;                       // ce qui a été tapé ne se perd pas
            $form['id'] = (string) $editId;
            $form['fonctions'] = is_array($_POST['fonctions'] ?? null) ? array_filter($_POST['fonctions'], 'is_string') : [];
            if ($editId < 0 || ($editId > 0 && !isset($parId[$editId]))) {
                $erreurs[] = 'Ce végétal n’appartient pas à cette parcelle.';
            } elseif (!$conduiteDefinie) {
                $erreurs[] = 'Choisissez la conduite de la parcelle avant de placer ses végétaux.';
            } else {
                $options = $selection;
                // Une production retirée des choix reste explicite sur les végétaux qui la portent.
                $ancien = Scenario::details($parId[$editId] ?? []);
                if (isset($ancien['production_cle'], $ancien['production']) && is_array($ancien['production'])) {
                    $options[$ancien['production_cle']] = $ancien['production'];
                }
                if ($action === 'supprimer') {
                    $validation = ['erreurs' => ($_POST['confirmer'] ?? '') === '1' ? [] : ['Confirmez le retrait de ce végétal.'], 'ligne' => null];
                } else {
                    if (!$aUnePrincipale) {
                        $erreurs[] = 'Choisissez d’abord les productions principales de cette parcelle.';
                    }
                    $validation = Scenario::valider($_POST, $conduite, $options);
                }
                $erreurs = array_merge($erreurs, $validation['erreurs']);
                if ($erreurs === []) {
                    try {
                        $ajouter = $action === 'vegetal' && ($_POST['operation'] ?? '') === 'ajouter';
                        $scenarios->enregistrer($idP, $compteId, $ajouter ? 0 : $editId,
                            $ajouter ? '' : (string) ($_POST['version'] ?? ''), $validation['ligne'],
                            ($_POST['confirmer_renommage'] ?? '') === '1');
                        $_SESSION['syntropia_flash_scenario'] = ['ok', $action === 'supprimer'
                            ? 'Le végétal a été retiré du tableau.' : 'Le végétal et ses fonctions sont enregistrés.'];
                        header('Location: ' . $retour);
                        exit;
                    } catch (\RuntimeException $e) {
                        http_response_code(409);
                        $erreurs[] = $e->getMessage();
                    } catch (\PDOException $e) {
                        error_log('Scénario : ' . $e->getMessage());
                        http_response_code(500);
                        $erreurs[] = "L'enregistrement a échoué. Réessayez dans un instant.";
                    }
                }
            }
        } else {
            http_response_code(400);
            $erreurs[] = 'Cette action n’est pas reconnue.';
        }
    }
    if ($erreurs !== []) {
        $ouvre = true;
    }

    // Les productions déjà portées par un végétal restent proposées au choix d'une ligne.
    $optionsProduction = $selection;
    $ancien = Scenario::details($parId[$editId] ?? []);
    if (isset($ancien['production_cle'], $ancien['production']) && is_array($ancien['production'])) {
        $optionsProduction[$ancien['production_cle']] = $ancien['production'];
    }
    $versionProductions = Scenario::versionProductions($parcelle);

    // Les souhaits proposés : ceux de la personne (à défaut du porteur), plus ce que la parcelle retient déjà.
    $proposes = [];
    foreach (array_merge($souhaitsDuSite['souhaits'], array_column($selection, 'texte')) as $t) {
        $proposes[mb_strtolower($t)] ??= $t;
    }
    $niveauDe = [];
    foreach ($selection as $s) {
        $niveauDe[mb_strtolower($s['texte'])] = $s['priorite'];
    }

    // Suggestions de saisie : les espèces connues de ce projet, puis les noms botaniques du catalogue.
    $suggestions = [];
    foreach ((new Especes())->toutes($siteId) as $e) {
        $nom = trim((string) ($e['nom_usuel'] ?: $e['nom_scientifique']));
        $suggestions[$nom] = (string) $e['nom_scientifique'];
    }
    $catalogue = json_decode((string) @file_get_contents(__DIR__ . '/../contenu/catalogue-scenario.json'), true);
    foreach ($catalogue['vegetaux'] ?? [] as $v) {
        $suggestions[(string) $v['nom']] ??= (string) $v['communs'];
    }
}

$nomAffiche = $auth->nomAffiche() ?? '';
$initiales = '';
foreach (preg_split('/\s+/', trim($nomAffiche)) ?: [] as $mot) {
    if ($mot !== '') {
        $initiales .= mb_strtoupper(mb_substr($mot, 0, 1));
    }
}
$initiales = mb_substr($initiales, 0, 2) ?: '?';

/* ------------------------------------------------------------------ le tableau */

/** Ce qu'un végétal montre : couleur, sigle, repères, texte de la bulle. */
$decrit = static function (array $l): array {
    $f = Scenario::formulaire($l);
    $d = Scenario::details($l);
    $debut = max(0, min(27, (int) $f['debut']));
    $fin = max($debut + 1, min(Scenario::POSITION_MAX, (int) $f['fin']));
    $categorie = $d['categorie'] ?? (str_starts_with((string) $l['role'], 'production') ? 'production' : 'service');
    $sigle = ['production' => 'P', 'service' => 'A', 'mixte' => 'P+A'][$categorie] ?? 'A';
    $couleur = in_array($l['couleur_plan'], ['rouge', 'vert', 'orange', 'bleu'], true) ? (string) $l['couleur_plan'] : 'vert';
    $retrait = $f['retrait'] === '' ? $fin : max($debut, min($fin, (int) $f['retrait']));
    $qte = $l['nb_pieds'] === null ? '' : ' ×' . (int) $l['nb_pieds'];
    $info = $l['espece'] . ' · ' . $sigle . $qte;
    if (($d['production']['texte'] ?? '') !== '') {
        $info .= ' · pour « ' . $d['production']['texte'] . ' »';
    }
    return ['debut' => $debut, 'fin' => $fin, 'retrait' => $retrait, 'sigle' => $sigle, 'couleur' => $couleur,
        'qte' => $qte, 'periode' => Scenario::libellePosition($debut) . ' → ' . Scenario::libellePosition($fin), 'info' => $info];
};

$strates = [];
$groupes = [];
if ($parcelle !== null) {
    $strates = $conduite->strates();
    $groupes = array_fill_keys(array_keys($strates), []);
    $aReclasser = [];
    foreach ($lignes as $l) {
        isset($groupes[(string) $l['strate']]) ? $groupes[(string) $l['strate']][] = $l : $aReclasser[] = $l;
    }
    if ($aReclasser !== []) {         // la conduite a changé depuis : ces végétaux attendent une strate valide
        $strates['__reclasser'] = 'À reclasser';
        $groupes['__reclasser'] = $aReclasser;
    }
    foreach ($groupes as &$g) {
        usort($g, static fn (array $a, array $b): int =>
            [(int) (Scenario::formulaire($a)['debut']), (int) $a['id']] <=> [(int) (Scenario::formulaire($b)['debut']), (int) $b['id']]);
    }
    unset($g);
}
$urlVegetal = static fn (int $id): string => 'conception-scenario.php?parcelle=' . $idP . '&vegetal=' . $id;
$urlStrate = static fn (string $code): string => 'conception-scenario.php?parcelle=' . $idP . '&strate=' . rawurlencode($code);

/** La frise SVG (syntropie). Une voie par plante ; la hauteur suit le nombre de voies. */
$frise = static function () use ($strates, $groupes, $decrit, $urlVegetal, $urlStrate, $h, $peutAjouter): string {
    $xL = 118.0;
    $xR = 992.0;
    $u = ($xR - $xL) / Scenario::POSITION_MAX;
    $enteteH = 46;
    $voie = 28;
    $barreH = 22;
    $hauteur = $enteteH;
    foreach ($strates as $code => $_) {
        $hauteur += max(1, count($groupes[$code])) * $voie + 10;
    }
    $hauteur += 6;
    $o = '<svg viewBox="0 0 1000 ' . $hauteur . '" role="img" aria-label="Tableau temps par strates" preserveAspectRatio="xMidYMid meet">'
        . '<defs><pattern id="hachure" width="6" height="6" patternUnits="userSpaceOnUse" patternTransform="rotate(45)">'
        . '<rect width="3" height="6" fill="var(--ink)" fill-opacity=".28"/></pattern></defs>';
    // les périodes : nom, durée, et un trait plein entre deux périodes
    $i = 0;
    foreach (Scenario::PERIODES as [$nom, $duree]) {
        $x = $xL + $i * 4 * $u;
        $o .= '<rect x="' . round($x, 1) . '" y="0" width="' . round(4 * $u, 1) . '" height="' . ($hauteur - 4) . '" class="sc-fondperiode' . ($i % 2 ? ' impair' : '') . '"/>'
            . '<text x="' . round($x + 2 * $u, 1) . '" y="19" class="sc-periode" text-anchor="middle">' . $h($nom) . '</text>'
            . '<text x="' . round($x + 2 * $u, 1) . '" y="35" class="sc-duree" text-anchor="middle">' . $h($duree) . '</text>';
        $i++;
    }
    $y = $enteteH;
    foreach ($strates as $code => $nom) {
        $n = max(1, count($groupes[$code]));
        $rangeeH = $n * $voie + 10;
        $o .= '<line x1="6" x2="' . $xR . '" y1="' . $y . '" y2="' . $y . '" class="sc-trait"/>'
            . '<text x="10" y="' . ($y + $rangeeH / 2 + 4) . '" class="sc-strate">' . $h($nom) . '</text>';
        if ($peutAjouter && $code !== '__reclasser') {
            $o .= '<a href="' . $h($urlStrate($code)) . '" class="sc-plus" aria-label="Ajouter un végétal en strate ' . $h($nom) . '">'
                . '<text x="' . ($xL - 16) . '" y="' . ($y + $rangeeH / 2 + 5) . '" text-anchor="middle">＋</text></a>';
        }
        if ($groupes[$code] === []) {
            $o .= '<text x="' . round($xL + 14 * $u, 1) . '" y="' . ($y + $rangeeH / 2 + 4) . '" class="sc-vide" text-anchor="middle">Une place à imaginer</text>';
        }
        foreach ($groupes[$code] as $k => $l) {
            $d = $decrit($l);
            $x = $xL + $d['debut'] * $u;
            $w = ($d['fin'] - $d['debut']) * $u;
            $by = $y + 5 + $k * $voie + ($voie - $barreH) / 2;
            $o .= '<a href="' . $h($urlVegetal((int) $l['id'])) . '" class="sc-lien" data-info="' . $h($d['info'] . ' — ' . $d['periode']) . '" aria-label="' . $h($d['info'] . ' — ' . $d['periode']) . '">'
                . '<svg x="' . round($x, 1) . '" y="' . round($by, 1) . '" width="' . round($w, 1) . '" height="' . $barreH . '" class="sc-barre b-' . $d['couleur'] . '">'
                . '<rect width="100%" height="100%" rx="5"/>';
            if ($d['retrait'] < $d['fin']) {
                $r = ($d['retrait'] - $d['debut']) / ($d['fin'] - $d['debut']) * 100;
                $o .= '<rect x="' . round($r, 2) . '%" width="' . round(100 - $r, 2) . '%" height="100%" rx="5" fill="url(#hachure)"/>';
            }
            $o .= '<text x="7" y="15">' . $h($l['espece'] . $d['qte']) . '</text></svg></a>';
        }
        $y += $rangeeH;
    }
    $o .= '<line x1="6" x2="' . $xR . '" y1="' . $y . '" y2="' . $y . '" class="sc-trait"/>';
    // les trois traits des quarts, discrets, puis la limite de chaque période
    for ($p = 0; $p <= 7; $p++) {
        $x = round($xL + $p * 4 * $u, 1);
        $o .= '<line x1="' . $x . '" x2="' . $x . '" y1="0" y2="' . $y . '" class="sc-limite"/>';
    }
    return $o . '</svg>';
};

$option = static function (array $form, string $cle, string $valeur, string $libelle) use ($h): string {
    return '<option value="' . $h($valeur) . '"' . (Scenario::texte($form, $cle) === $valeur ? ' selected' : '') . '>' . $h($libelle) . '</option>';
};
$val = static fn (array $form, string $cle): string => htmlspecialchars(Scenario::texte($form, $cle), ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Scénario — Syntropia</title>
<link rel="stylesheet" href="assets/css/gabarit.css">
<style>
  /* pas de bandeau d'outils sur cette page : les tirettes se calent sur le seul bandeau général */
  :root{ --haut-bandeaux: 3.4rem; }

  /* Les quatre couleurs des bandes — clair et sombre, comme le reste du bureau. */
  :root{ --b-rouge:#E7A99B; --b-orange:#F0C48A; --b-vert:#B5D1A5; --b-bleu:#A9C5E0; }
  @media (prefers-color-scheme: dark){
    :root:not([data-theme="light"]){ --b-rouge:#7B3A2E; --b-orange:#8A5F24; --b-vert:#3F5B34; --b-bleu:#2F4C68; }
  }
  :root[data-theme="dark"]{ --b-rouge:#7B3A2E; --b-orange:#8A5F24; --b-vert:#3F5B34; --b-bleu:#2F4C68; }

  /* le bureau centre son contenu : ici le tableau remplit toute sa place, pour que la frise
     et les pastilles puissent se réduire au lieu de déborder */
  #bureau{ padding:.4rem .6rem; align-items:stretch; }
  .sc-centre{ width:100%; min-height:0; display:flex; flex-direction:column; gap:.45rem; padding:.3rem .4rem .2rem; overflow:hidden; }
  .sc-entete{ display:flex; align-items:center; gap:.7rem; flex-wrap:wrap; }
  .sc-titre{ font:600 1rem var(--titre); color:var(--accent-titre); margin:0; }
  .sc-badge{ font:500 .72rem var(--titre); color:var(--muted); border:1px solid var(--line); border-radius:1rem; padding:.1rem .55rem; }
  .sc-actions{ margin-left:auto; display:flex; gap:.5rem; }
  .sc-bouton{
    font:600 .8rem var(--titre); color:var(--bouton-encre); background:var(--accent); border:0; border-radius:.5rem;
    padding:.35rem .75rem; text-decoration:none; cursor:pointer;
  }
  .sc-bouton--calme{ background:transparent; color:var(--accent-titre); border:1px solid var(--accent); }
  .sc-message{ margin:0; padding:.4rem .7rem; border-radius:.5rem; font:400 .82rem/1.35 var(--texte);
    background:var(--accent-fond); border:1px solid var(--accent); color:var(--accent-titre); }
  .sc-message--alerte{ border-color:var(--alerte); color:var(--alerte); }
  .sc-message a{ color:inherit; font-weight:600; }

  .sc-graphe{ flex:1; min-height:0; }
  .sc-graphe svg{ width:100%; height:100%; display:block; }
  .sc-fondperiode{ fill:var(--surface); fill-opacity:.55; }
  .sc-fondperiode.impair{ fill-opacity:.3; }
  .sc-periode{ font:600 13px var(--titre); fill:var(--ink); }
  .sc-duree{ font:400 11px var(--texte); fill:var(--muted); }
  .sc-strate{ font:600 13px var(--titre); fill:var(--ink); }
  .sc-trait{ stroke:var(--line); stroke-width:1; }
  .sc-limite{ stroke:var(--line); stroke-width:1; stroke-dasharray:2 4; }
  .sc-vide{ font:italic 12px var(--texte); fill:var(--muted); }
  .sc-plus text{ font:600 16px var(--titre); fill:var(--accent-titre); cursor:pointer; }
  .sc-lien{ cursor:pointer; }
  .sc-barre rect{ stroke:var(--ink); stroke-opacity:.35; stroke-width:1; }
  .sc-barre text{ font:500 12px var(--texte); fill:var(--ink); pointer-events:none; }
  .sc-lien:hover .sc-barre rect, .sc-lien:focus .sc-barre rect{ stroke-opacity:.9; stroke-width:2; }
  .sc-lien:focus{ outline:none; }
  .b-rouge > rect:first-child{ fill:var(--b-rouge); } .b-orange > rect:first-child{ fill:var(--b-orange); }
  .b-vert > rect:first-child{ fill:var(--b-vert); }   .b-bleu > rect:first-child{ fill:var(--b-bleu); }

  /* la vue par strate : téléphones, et jardin-forêt (pas d'axe du temps) */
  .sc-strates{ display:none; flex:1; min-height:0; font-size:14px; }
  .sc-strate-bloc{ display:flex; gap:.6em; align-items:flex-start; padding:.3em 0; border-top:1px solid var(--line); }
  .sc-strate-bloc h3{ flex:none; width:7.4em; font:600 .9em var(--titre); color:var(--ink); margin:.25em 0 0; display:flex; gap:.3em; align-items:center; }
  .sc-strate-bloc h3 a{ color:var(--accent-titre); text-decoration:none; font-size:1rem; }
  .sc-puces{ list-style:none; margin:0; padding:0; display:flex; flex-wrap:wrap; gap:.3em; }
  .sc-puce{
    display:inline-block; padding:.2em .55em; border-radius:.5em; font:500 .9em/1.25 var(--texte); color:var(--ink);
    text-decoration:none; border:1px solid color-mix(in srgb, var(--ink) 30%, transparent);
  }
  .sc-puce small{ color:var(--muted); font-size:.78em; }
  .sc-puce.b-rouge{ background:var(--b-rouge); } .sc-puce.b-orange{ background:var(--b-orange); }
  .sc-puce.b-vert{ background:var(--b-vert); }   .sc-puce.b-bleu{ background:var(--b-bleu); }
  .sc-puce:hover, .sc-puce:focus{ border-color:var(--ink); outline:none; }
  .sc-strate-vide{ font:italic .9em var(--texte); color:var(--muted); margin:.3em 0 0; }
  .sc-jf .sc-strates{ display:block; }
  .sc-jf .sc-graphe{ display:none; }
  @media (max-width:40rem){
    .sc-graphe{ display:none; }
    .sc-strates{ display:block; }
    .sc-centre{ padding-top:1.8rem; }   /* la place de l'onglet « Informations », qui recouvre le haut */
  }
  .sc-legende{ display:flex; gap:.9rem; flex-wrap:wrap; font:400 .74rem var(--texte); color:var(--muted); align-items:center; }
  .sc-legende i{ display:inline-block; width:.8rem; height:.8rem; border-radius:.2rem; margin-right:.3rem; vertical-align:-.1rem; border:1px solid color-mix(in srgb, var(--ink) 35%, transparent); }
  .sc-legende .hachure{ background:repeating-linear-gradient(45deg, transparent 0 2px, color-mix(in srgb, var(--ink) 30%, transparent) 2px 4px); }
  .pi-saut{ list-style:none; margin:0 0 .9rem; padding:0; font:400 .84rem/1.45 var(--texte); }
  .pi-saut li{ margin:0 0 .35rem; padding-left:1.3rem; position:relative; }
  .pi-saut .pi-saut-signe{ position:absolute; left:0; }
  .pi-saut li.ok .pi-saut-signe{ color:var(--accent-titre); }
  .pi-saut li.attention{ color:var(--alerte); }
  .pi-saut small{ color:var(--muted); }
  .sc-vide-page{ margin:auto; max-width:24rem; text-align:center; font:400 .92rem/1.5 var(--texte); color:var(--muted); }
  .sc-vide-page a{ color:var(--accent-titre); font-weight:600; }

  /* le panneau Données : deux onglets en CSS pur */
  .sc-radio{ position:absolute; opacity:0; pointer-events:none; }
  .sc-onglets{ display:flex; gap:.3rem; margin-bottom:.7rem; border-bottom:1px solid var(--line); }
  .sc-onglet{ flex:1; text-align:center; padding:.35rem .3rem; cursor:pointer; border-radius:.5rem .5rem 0 0;
    font:500 .8rem var(--titre); color:var(--muted); border:1px solid transparent; border-bottom:0; }
  .pd-corps:has(#o-veg:checked) label[for=o-veg], .pd-corps:has(#o-prod:checked) label[for=o-prod]{
    color:var(--accent-titre); background:var(--accent-fond); border-color:var(--line); font-weight:600; }
  .sc-panneau{ display:none; }
  .pd-corps:has(#o-veg:checked) .sc-p-veg, .pd-corps:has(#o-prod:checked) .sc-p-prod{ display:block; }
  .sc-form label.sc-champ{ display:block; font:500 .78rem var(--titre); color:var(--ink); margin:0 0 .5rem; }
  .sc-form label.sc-champ small{ font:400 .68rem var(--texte); color:var(--muted); }
  .sc-form input[type=text], .sc-form input[type=number], .sc-form select, .sc-form textarea{
    display:block; width:100%; margin-top:.15rem; padding:.36rem .5rem; font:400 .84rem var(--texte); color:var(--ink);
    background:var(--surface); border:1px solid var(--line); border-radius:.45rem;
  }
  .sc-form input:focus, .sc-form select:focus, .sc-form textarea:focus{ outline:2px solid var(--accent); outline-offset:1px; }
  .sc-deux{ display:grid; grid-template-columns:1fr 1fr; gap:.5rem; }
  .sc-form details{ margin:0 0 .6rem; border:1px solid var(--line); border-radius:.5rem; padding:.3rem .55rem; }
  .sc-form summary{ cursor:pointer; font:500 .78rem var(--titre); color:var(--accent-titre); }
  .sc-fonctions{ display:grid; gap:.15rem; margin-top:.4rem; }
  .sc-fonctions label{ display:flex; gap:.4rem; align-items:flex-start; font:400 .76rem/1.3 var(--texte); color:var(--ink); }
  .sc-note{ font:400 .74rem/1.4 var(--texte); color:var(--muted); margin:.2rem 0 .6rem; }
  .sc-erreurs{ margin:0 0 .7rem; padding:.5rem .7rem; border:1px solid var(--alerte); border-radius:.5rem; color:var(--alerte); font:400 .8rem/1.35 var(--texte); }
  .sc-erreurs ul{ margin:.2rem 0 0 1rem; padding:0; }
  .sc-actions-form{ display:flex; flex-direction:column; gap:.4rem; margin-top:.6rem; }
  .sc-retirer{ margin-top:.9rem; }
  .sc-prod-ligne{ display:grid; grid-template-columns:1fr 6.6rem; gap:.5rem; align-items:center; padding:.3rem 0; border-top:1px solid var(--line); font:400 .84rem var(--texte); color:var(--ink); }
  .sc-prod-ligne select{ margin:0 !important; }
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
          <span class="compte-avatar"><?= htmlspecialchars($initiales, ENT_QUOTES, 'UTF-8') ?></span>
          <span class="compte-nom"><?= htmlspecialchars($nomAffiche, ENT_QUOTES, 'UTF-8') ?></span>
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
<?php foreach ($parcelles as $p): $estActive = $parcelle !== null && (int) $p['id'] === (int) $parcelle['id']; ?>
            <li><a href="conception-scenario.php?parcelle=<?= (int) $p['id'] ?>"<?= $estActive ? ' class="active" aria-current="page"' : '' ?>><span class="pg-puce">≋</span><span class="pg-libelle"><?= $h($p['code']) ?> — <?= $h($p['nom']) ?></span></a></li>
<?php endforeach; ?>
            <li><a href="conception-parcelles.php?nouvelle=1"><span class="pg-puce">+</span><span class="pg-libelle">Nouvelle parcelle</span></a></li>
          </ul>
        </div>
<?php if ($parcelle !== null): ?>
        <div class="pg-section">
          <p class="pg-titre"><?= $h($parcelle['code']) ?> — <?= $h($parcelle['nom']) ?></p>
          <ul class="pg-liste">
            <li><a href="conception-parcelles.php?parcelle=<?= $idP ?>"><span class="pg-puce">▤</span><span class="pg-libelle">Le plan</span></a></li>
            <li><a href="conception-scenario.php?parcelle=<?= $idP ?>" class="active" aria-current="page"><span class="pg-puce">≋</span><span class="pg-libelle">Le scénario</span></a></li>
            <li><a href="conception-materiel-et-eau.php?parcelle=<?= $idP ?>"><span class="pg-puce">◍</span><span class="pg-libelle">Matériel et eau</span></a></li>
            <li><a href="#" class="a-venir" tabindex="-1" aria-disabled="true"><span class="pg-puce">✎</span><span class="pg-libelle">Le suivi</span></a></li>
          </ul>
        </div>
<?php endif; ?>
        <p class="pg-pied">Le scénario se compose parcelle par parcelle : ce qui grandira, à quelle strate, et pour combien de temps.</p>
      </div>
    </nav>

    <main class="bureau" id="bureau">
<?php if ($parcelle === null): ?>
      <div class="sc-centre">
        <p class="sc-vide-page">Le scénario se compose parcelle par parcelle, et ce site n'en a pas encore.
          <a href="conception-parcelles.php?nouvelle=1">Ouvrir la première parcelle →</a></p>
      </div>
<?php else: ?>
      <div class="sc-centre<?= $conduite->scenarioADesTemps() ? '' : ' sc-jf' ?>">
        <div class="sc-entete">
          <h1 class="sc-titre">Le scénario de <?= $h($parcelle['code']) ?> — <?= $h($parcelle['nom']) ?></h1>
          <span class="sc-badge"><?= $h($conduite->libelle()) ?><?= $niveauConduite === 'site' ? ' · comme le site' : ($niveauConduite === 'aucun' ? ' · pas encore choisie' : '') ?></span>
<?php if ($peutEcrire): ?>
          <span class="sc-actions">
            <a class="sc-bouton sc-bouton--calme" href="conception-scenario.php?parcelle=<?= $idP ?>&amp;productions=1">Productions</a>
<?php if ($peutAjouter): ?>
            <a class="sc-bouton" href="conception-scenario.php?parcelle=<?= $idP ?>&amp;nouveau=1">＋ Ajouter un végétal</a>
<?php endif; ?>
          </span>
<?php endif; ?>
        </div>
<?php if (is_array($flash)): ?>
        <p class="sc-message" role="status"><?= $h($flash[1]) ?></p>
<?php endif; ?>
<?php if ($erreurs !== []): ?>
        <p class="sc-message sc-message--alerte" role="alert">Votre saisie est à reprendre — le détail est dans le panneau Données.</p>
<?php endif; ?>
<?php if (!$conduiteDefinie): ?>
        <p class="sc-message sc-message--alerte">La conduite n'est pas encore choisie — la syntropie s'applique en attendant, mais aucun végétal ne se place avant.
          Choisissez-la <a href="conception-parcelles.php?parcelle=<?= $idP ?>">pour cette parcelle</a> ou <a href="definition-site.php">pour tout le site</a>.</p>
<?php elseif ($peutEcrire && !$aUnePrincipale): ?>
        <p class="sc-message sc-message--alerte">Avant les végétaux, les productions : ce que cette parcelle doit donner décide de tout le reste.
          <a href="conception-scenario.php?parcelle=<?= $idP ?>&amp;productions=1">Les choisir →</a><?= $proposes === [] ? ' Aucune n\'est encore dite : <a href="definition-objectifs.php">les objectifs</a> les demandent.' : '' ?></p>
<?php endif; ?>
<?php if (!$conduite->scenarioADesTemps()): ?>
        <p class="sc-message">En jardin-forêt, une plante est posée à sa place et y reste : le tableau se lit par strate, sans axe du temps.</p>
<?php endif; ?>

<?php if ($conduite->scenarioADesTemps()): ?>
        <div class="sc-graphe"><?= $frise() ?></div>
<?php endif; ?>
        <div class="sc-strates">
<?php foreach ($strates as $code => $nom): ?>
          <section class="sc-strate-bloc">
            <h3><?= $h($nom) ?><?php if ($peutAjouter && $code !== '__reclasser'): ?><a href="<?= $h($urlStrate($code)) ?>" aria-label="Ajouter un végétal en strate <?= $h($nom) ?>">＋</a><?php endif; ?></h3>
<?php if ($groupes[$code] === []): ?>
            <p class="sc-strate-vide">Une place à imaginer</p>
<?php else: ?>
            <ul class="sc-puces">
<?php foreach ($groupes[$code] as $l): $d = $decrit($l); ?>
              <li><a class="sc-puce b-<?= $d['couleur'] ?>" href="<?= $h($urlVegetal((int) $l['id'])) ?>" data-info="<?= $h($d['info'] . ($conduite->scenarioADesTemps() ? ' — ' . $d['periode'] : '')) ?>"><?= $h($l['espece']) ?><?= $d['qte'] !== '' || $conduite->scenarioADesTemps() ? ' <small>' . $h($d['sigle'] . $d['qte']) . '</small>' : '' ?></a></li>
<?php endforeach; ?>
            </ul>
<?php endif; ?>
          </section>
<?php endforeach; ?>
        </div>
        <div class="sc-legende">
          <span><i style="background:var(--b-rouge)"></i>P · production</span>
          <span><i style="background:var(--b-vert)"></i>A · accompagnement</span>
          <span><i style="background:var(--b-orange)"></i>P+A · les deux</span>
<?php if ($conduite->scenarioADesTemps()): ?>
          <span><i class="hachure"></i>retrait progressif</span>
<?php endif; ?>
        </div>
      </div>
<?php endif; ?>
    </main>

<?php if ($parcelle !== null): ?>
    <div class="scrim" id="scrim-droit"></div>
    <button class="tirette" id="tirette" aria-expanded="false" aria-controls="panneau-droit"
            aria-label="Afficher le panneau Données">
      <svg class="tirette-triangle" viewBox="0 0 10 10" aria-hidden="true"><polygon points="1,1 9,5 1,9" fill="currentColor"/></svg>
      <span class="tirette-texte">Données</span>
    </button>
    <aside class="panneau-droit" id="panneau-droit" aria-hidden="true">
      <div class="pd-corps sc-form">
        <input class="sc-radio" type="radio" name="onglet" id="o-veg"<?= $onglet === 'veg' ? ' checked' : '' ?>>
        <input class="sc-radio" type="radio" name="onglet" id="o-prod"<?= $onglet === 'prod' ? ' checked' : '' ?>>
        <div class="sc-onglets" role="tablist">
          <label class="sc-onglet" for="o-veg">Un végétal</label>
          <label class="sc-onglet" for="o-prod">Productions</label>
        </div>
<?php if ($erreurs !== []): ?>
        <div class="sc-erreurs" role="alert"><strong>Votre saisie est à reprendre.</strong>
          <ul><?php foreach ($erreurs as $e): ?><li><?= $h($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

        <section class="sc-panneau sc-p-veg">
          <p class="pd-titre"><?= $editId > 0 ? 'Modifier : ' . $h($parId[$editId]['espece'] ?? '') : 'Ajouter un végétal' ?></p>
<?php if (!$conduiteDefinie): ?>
          <p class="sc-note">Choisissez d'abord la conduite — <a href="conception-parcelles.php?parcelle=<?= $idP ?>">pour cette parcelle</a> ou <a href="definition-site.php">pour le site</a>.</p>
<?php elseif (!$aUnePrincipale): ?>
          <p class="sc-note">Choisissez d'abord les productions de la parcelle, dans l'onglet « Productions ».</p>
<?php elseif ($editId > 0): ?>
          <p class="sc-note">Vous modifiez <strong><?= $h($parId[$editId]['espece'] ?? '') ?></strong>. Pour une autre implantation, utilisez « Ajouter comme nouveau végétal ». <a href="conception-scenario.php?parcelle=<?= $idP ?>&amp;nouveau=1">Un formulaire vide</a>.</p>
<?php else: ?>
          <p class="sc-note">Une implantation par ligne : la même espèce à deux endroits, ou conduite de deux façons, fait deux entrées.</p>
<?php endif; ?>
          <form method="post" action="conception-scenario.php?parcelle=<?= $idP ?>">
            <input type="hidden" name="csrf" value="<?= $h($auth->jeton()) ?>">
            <input type="hidden" name="action" value="vegetal">
            <input type="hidden" name="vegetal_id" value="<?= $editId ?>">
            <input type="hidden" name="version" value="<?= $val($form, 'version') ?>">
            <fieldset style="border:0;padding:0;margin:0"<?= $peutAjouter ? '' : ' disabled' ?>>
              <label class="sc-champ">Végétal
                <input type="text" name="espece" list="sc-especes" maxlength="160" required value="<?= $val($form, 'espece') ?>" placeholder="Nom commun ou scientifique">
              </label>
<?php if ($editId > 0): ?>
              <label class="sc-fonctions"><span style="display:flex;gap:.4rem;align-items:flex-start;font:400 .74rem/1.3 var(--texte)"><input type="checkbox" class="case" name="confirmer_renommage" value="1"<?= ($_POST['confirmer_renommage'] ?? '') === '1' ? ' checked' : '' ?>> Je corrige le nom de « <?= $h($parId[$editId]['espece'] ?? '') ?> » sur cette même implantation.</span></label>
<?php endif; ?>
              <div class="sc-deux">
                <label class="sc-champ">Strate
                  <select name="strate" required>
                    <option value="">Choisir…</option>
<?php foreach ($conduite->strates() as $k => $v) { echo '                    ', $option($form, 'strate', $k, $v), "\n"; } ?>
                  </select>
                </label>
                <label class="sc-champ">Emplacement
                  <select name="emplacement">
<?php foreach (Scenario::EMPLACEMENTS as $k => $v) { echo '                    ', $option($form, 'emplacement', $k, $v), "\n"; } ?>
                  </select>
                </label>
              </div>
              <p class="sc-note">En bordure, les plants se posent et se comptent sur
                <a href="conception-parcelles.php?parcelle=<?= $idP ?>&amp;bordure=recompte">le plan</a>, côté par côté : c'est ce compte qui fait foi.</p>
              <label class="sc-champ">Production visée
                <select name="production">
                  <option value="">Accompagnement uniquement</option>
<?php foreach ($optionsProduction as $k => $v) { echo '                  ', $option($form, 'production', $k, $v['texte'] . ' — ' . $v['priorite']), "\n"; } ?>
                </select>
              </label>
              <details<?= ($form['fonctions'] ?? []) !== [] ? ' open' : '' ?>>
                <summary>Fonctions d'accompagnement (<?= count($form['fonctions'] ?? []) ?>)</summary>
                <div class="sc-fonctions">
<?php foreach (Scenario::FONCTIONS as $k => $v): ?>
                  <label><input type="checkbox" class="case" name="fonctions[]" value="<?= $k ?>"<?= in_array($k, $form['fonctions'] ?? [], true) ? ' checked' : '' ?>> <?= $h($v) ?></label>
<?php endforeach; ?>
                </div>
              </details>
<?php if ($conduite->scenarioADesTemps()): ?>
              <p class="sc-note" style="margin-bottom:.3rem">Présence dans la strate — les quarts sont des repères, pas des dates.</p>
              <div class="sc-deux">
                <label class="sc-champ">Arrivée
                  <select name="debut">
<?php for ($i = 0; $i <= 27; $i++) { echo '                    ', $option($form, 'debut', (string) $i, Scenario::libellePosition($i)), "\n"; } ?>
                  </select>
                </label>
                <label class="sc-champ">Fin
                  <select name="fin">
<?php for ($i = 1; $i <= Scenario::POSITION_MAX; $i++) { echo '                    ', $option($form, 'fin', (string) $i, Scenario::libellePosition($i)), "\n"; } ?>
                  </select>
                </label>
              </div>
              <label class="sc-champ">Début du retrait progressif <small>(facultatif)</small>
                <select name="retrait">
                  <?= $option($form, 'retrait', '', 'Aucun retrait prévu') ?>
<?php for ($i = 0; $i <= Scenario::POSITION_MAX; $i++) { echo '                  ', $option($form, 'retrait', (string) $i, Scenario::libellePosition($i)), "\n"; } ?>
                </select>
              </label>
<?php endif; ?>
              <div class="sc-deux">
                <label class="sc-champ">Nombre de plants
                  <input type="number" name="nb_pieds" min="1" max="65535" value="<?= $val($form, 'nb_pieds') ?>" placeholder="À déterminer">
                </label>
                <label class="sc-champ">Espacement (m)
                  <input type="text" name="espacement_m" inputmode="decimal" value="<?= $val($form, 'espacement_m') ?>" placeholder="À déterminer">
                </label>
              </div>
              <details>
                <summary>Plus de détails</summary>
                <label class="sc-champ" style="margin-top:.5rem">Pour qui ? <small>quels végétaux en bénéficient</small>
                  <input type="text" name="beneficiaire" maxlength="500" value="<?= $val($form, 'beneficiaire') ?>" placeholder="Ex. les jeunes pommiers de la bordure nord">
                </label>
                <label class="sc-champ">Repère sur la parcelle
                  <input type="text" name="repere" maxlength="160" value="<?= $val($form, 'repere') ?>" placeholder="Ex. planche 2, ligne centrale">
                </label>
                <label class="sc-champ">Geste de taille <small>où l'arbre stocke ses sucres — facultatif</small>
                  <select name="geste_taille">
                    <option value="">Pas de geste noté</option>
<?php foreach (Scenario::GESTES as $cle => [$nomGeste, $sens]): ?>
                    <option value="<?= $h($cle) ?>"<?= ($form['geste_taille'] ?? '') === $cle ? ' selected' : '' ?>><?= $h($nomGeste) ?> — <?= $h($sens) ?></option>
<?php endforeach; ?>
                  </select>
                </label>
<?php foreach (['plantation' => ['Plantation ou présence existante', 160], 'recolte' => ['Période de récolte', 160], 'conduite_taille' => ['Précisions sur les tailles et interventions', 120], 'type_plant' => ['Type de plant ou de semis', 60], 'source_valeur' => ['Source ou observation', 120]] as $k => [$libelle, $max]): ?>
                <label class="sc-champ"><?= $h($libelle) ?>
                  <input type="text" name="<?= $k ?>" maxlength="<?= $max ?>" value="<?= $val($form, $k) ?>">
                </label>
<?php endforeach; ?>
                <label class="sc-champ">Origine
                  <select name="origine">
<?php foreach (['' => 'À déterminer', 'achat' => 'Achat', 'multiplication' => 'Multiplication', 'rna' => 'Régénération naturelle assistée', 'mixte' => 'Mixte'] as $k => $v) { echo '                    ', $option($form, 'origine', $k, $v), "\n"; } ?>
                  </select>
                </label>
                <label class="sc-champ">Couleur de la bande
                  <select name="couleur">
<?php foreach (['auto' => 'Selon la production et les fonctions', 'rouge' => 'Rouge', 'vert' => 'Vert', 'orange' => 'Orange', 'bleu' => 'Bleu'] as $k => $v) { echo '                    ', $option($form, 'couleur', $k, $v), "\n"; } ?>
                  </select>
                </label>
                <label class="sc-champ">Notes
                  <textarea name="notes" rows="4" maxlength="12000"><?= $val($form, 'notes') ?></textarea>
                </label>
              </details>
              <div class="sc-actions-form">
                <button type="submit" class="sc-bouton" name="operation" value="<?= $editId > 0 ? 'modifier' : 'ajouter' ?>"><?= $editId > 0 ? 'Enregistrer les modifications' : 'Ajouter au tableau' ?></button>
<?php if ($editId > 0): ?>
                <button type="submit" class="sc-bouton sc-bouton--calme" name="operation" value="ajouter">Ajouter comme nouveau végétal</button>
<?php endif; ?>
              </div>
            </fieldset>
          </form>
<?php if ($editId > 0 && $peutEcrire): ?>
          <details class="sc-retirer">
            <summary>Retirer ce végétal du scénario</summary>
            <form method="post" action="conception-scenario.php?parcelle=<?= $idP ?>">
              <input type="hidden" name="csrf" value="<?= $h($auth->jeton()) ?>">
              <input type="hidden" name="action" value="supprimer">
              <input type="hidden" name="vegetal_id" value="<?= $editId ?>">
              <input type="hidden" name="version" value="<?= $val($form, 'version') ?>">
              <label class="sc-fonctions" style="margin:.4rem 0"><span style="display:flex;gap:.4rem;align-items:flex-start;font:400 .76rem/1.3 var(--texte)"><input type="checkbox" class="case" name="confirmer" value="1" required> Retirer cette implantation et ses fonctions du tableau.</span></label>
              <button type="submit" class="sc-bouton sc-bouton--calme">Retirer le végétal</button>
            </form>
          </details>
<?php endif; ?>
          <datalist id="sc-especes">
<?php foreach ($suggestions as $nom => $complement): ?>
            <option value="<?= $h($nom) ?>"><?= $h($complement) ?></option>
<?php endforeach; ?>
          </datalist>
        </section>

        <section class="sc-panneau sc-p-prod">
          <p class="pd-titre">Les productions de cette parcelle</p>
          <p class="sc-note">Ce que cette parcelle doit donner : <strong>au moins une production principale</strong> avant tout végétal.
            <?php if ($souhaitsDuSite['de'] === 'soi'): ?>Vos réponses aux objectifs, à relire dans <a href="definition-objectifs.php">Objectifs</a>.<?php elseif ($souhaitsDuSite['de'] === 'porteur'): ?>Faute de réponse de votre part, celles du porteur du projet.<?php else: ?>Rien n'est encore dit dans <a href="definition-objectifs.php">Objectifs</a> : ajoutez-en une ci-dessous.<?php endif; ?></p>
          <form method="post" action="conception-scenario.php?parcelle=<?= $idP ?>">
            <input type="hidden" name="csrf" value="<?= $h($auth->jeton()) ?>">
            <input type="hidden" name="action" value="productions">
            <input type="hidden" name="version" value="<?= $h($versionProductions) ?>">
            <fieldset style="border:0;padding:0;margin:0"<?= $peutEcrire ? '' : ' disabled' ?>>
<?php $i = 0; foreach ($proposes as $cle => $texte): $courant = $niveauDe[$cle] ?? ''; ?>
              <div class="sc-prod-ligne">
                <span><?= $h($texte) ?><input type="hidden" name="texte[<?= $i ?>]" value="<?= $h($texte) ?>"></span>
                <select name="niveau[<?= $i ?>]" aria-label="Niveau de « <?= $h($texte) ?> »">
                  <option value="">— non retenue —</option>
                  <option value="principale"<?= $courant === 'principale' ? ' selected' : '' ?>>principale</option>
                  <option value="secondaire"<?= $courant === 'secondaire' ? ' selected' : '' ?>>secondaire</option>
                </select>
              </div>
<?php $i++; endforeach; ?>
              <div class="sc-prod-ligne">
                <input type="text" name="autre" maxlength="120" placeholder="Une autre production…" aria-label="Une autre production" style="margin:0">
                <select name="autre_niveau" aria-label="Niveau de l'autre production">
                  <option value="principale">principale</option>
                  <option value="secondaire">secondaire</option>
                </select>
              </div>
              <div class="sc-actions-form">
                <button type="submit" class="sc-bouton">Garder ces productions</button>
              </div>
            </fieldset>
          </form>
          <p class="sc-note" style="margin-top:.6rem">Une production tient en un souhait, sans virgule. Les productions retirées restent lisibles sur les végétaux qui les portent.</p>
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
<?php if (($prepareSaut ?? []) !== []): ?>
      <p><strong>Ce scénario prépare-t-il le saut du chat ?</strong> Le saut du chat est l'effet de la perturbation. Des repères, non des refus : l'atelier dit ce que les sources demandent, vous décidez.</p>
      <ul class="pi-saut">
<?php foreach ($prepareSaut as $x): ?>
        <li class="<?= $h($x['etat']) ?>"><span class="pi-saut-signe"><?= ['ok' => '✓', 'attention' => '⚠', 'vide' => '○'][$x['etat']] ?? '·' ?></span> <strong><?= $h($x['titre']) ?></strong> — <?= $h($x['detail']) ?> <small>[<?= $h($x['source']) ?>]</small></li>
<?php endforeach; ?>
      </ul>
<?php endif; ?>
      <p>Vous composez ici <strong>un film</strong> : ce qui grandira sur cette parcelle, à quelle strate, et pendant combien de temps.
         Le tableau se lit de gauche à droite (le temps) et de haut en bas (de la strate la plus haute à la plus basse).</p>
      <p><strong>Un chemin pour le remplir</strong> — à suivre librement :</p>
      <ol>
        <li><em>Les productions d'abord</em> : ce que la parcelle doit donner. La principale, puis la secondaire.</li>
        <li><em>Regardez plus loin</em> : qui sera encore là à l'horizon du projet ?</li>
        <li><em>Revenez vers le démarrage</em> : qui prépare le sol, protège les jeunes plants, fournit la biomasse ?</li>
        <li><em>Les placentaires en dernier</em> : ils ne se choisissent qu'après avoir pensé les plantes de plus longue durée.</li>
        <li><em>Relisez le film</em> : qui prend le relais quand une plante est taillée ou retirée ?</li>
      </ol>
<?php if ($parcelle === null || $conduite->scenarioADesTemps()): ?>
      <p><strong>Les repères</strong> [Théry, ch. 11] : le stade placentaire dure environ trois ans en plantation, et un arbre laissé
         seul dans l'herbe prend un retard qu'il ne rattrape pas. Dans une plantation, <strong>70 à 80 % des plantes servent la
         biomasse et la perturbation</strong>. Prévoir <strong>au moins deux espèces par strate</strong> : la réussite d'un plant n'est
         jamais automatique. Diversifier les familles botaniques — les rosacées sont vite surreprésentées.</p>
      <p><strong>La strate se choisit par le besoin en lumière</strong>, et par le résultat attendu : un figuier ou un sureau cantonnés
         en strate moyenne servent la biomasse et fructifient peu. D'après la formation Joala : émergente 80 à 100 % de lumière,
         haute 60 à 80 %, moyenne 40 à 60 %, basse 20 à 40 % — un seuil donné par une seule formatrice, à éprouver sur votre lieu.</p>
      <p><strong>Les quarts</strong> sont des repères dans chaque période, pas des dates. Les colonnes ont la même largeur mais représentent
         des durées différentes. Des hachures marquent un retrait progressif prévu.</p>
<?php else: ?>
      <p><strong>En jardin-forêt</strong>, les strates disent la place et la hauteur adulte, et une plante est posée à sa place définitive :
         il n'y a pas de temps à saisir.</p>
      <p><?= $h(Conduite::JardinForet->reserve()) ?></p>
<?php endif; ?>
      <p>La strate attribuée reste <strong>subjective</strong> : elle s'affine avec les connaissances, et change d'un lieu à l'autre. Une plante
         multifonctionnelle est comptée une seule fois. Le nombre de plants sert plus tard aux commandes et au chantier.</p>
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

<script src="assets/js/etapes.js"></script>
<script src="assets/js/gabarit.js"></script>
<script>
  Gabarit.init({
    site: <?= json_encode((string) $site['nom'], JSON_UNESCAPED_UNICODE) ?>,
    etape: 'conception-scenario'<?php if ($parcelle !== null): ?>,
    contexte: <?= json_encode($parcelle['code'] . ' — ' . $parcelle['nom'], JSON_UNESCAPED_UNICODE) ?><?php endif; ?>
  });

  (function(){
    // Le panneau Données s'ouvre quand la page en a besoin : un végétal choisi, une strate à
    // remplir, une saisie à reprendre.
    if (document.body.dataset.ouvre === '1') {
      var tirette = document.getElementById('tirette');
      if (tirette) tirette.click();
    }
    // La vue par strate ne défile jamais : si elle déborde, sa taille se réduit jusqu'à tenir.
    function ajuster(){
      var z = document.querySelector('.sc-strates');
      if (!z || getComputedStyle(z).display === 'none') return;
      var c = z.closest('.sc-centre'), t = 14;
      z.style.fontSize = t + 'px';
      while (c.scrollHeight > c.clientHeight + 1 && t > 7) { t -= 0.5; z.style.fontSize = t + 'px'; }
    }
    ajuster();
    window.addEventListener('resize', ajuster);
    // Une bulle dit ce qu'est chaque bande — jamais le title natif.
    document.querySelectorAll('[data-info]').forEach(function(a){
      function montrer(){ Gabarit.bulle.montrer(a, a.dataset.info, 'horizontal'); }
      a.addEventListener('mouseenter', montrer);
      a.addEventListener('focus', montrer);
      a.addEventListener('mouseleave', Gabarit.bulle.cacher);
      a.addEventListener('blur', Gabarit.bulle.cacher);
    });
  })();
</script>
</body>
</html>
