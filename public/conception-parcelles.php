<?php

declare(strict_types=1);

require __DIR__ . '/../src/autoload.php';
require __DIR__ . '/../src/Configuration.php';
require __DIR__ . '/../src/Database.php';

use Syntropia\Bordures;
use Syntropia\Conduite;
use Syntropia\Configuration;
use Syntropia\Planches;
use Syntropia\RepartitionBordures;
use Syntropia\Role;
use Syntropia\Stade;
use Syntropia\Tortue;
use Syntropia\Web\Authentification;
use Syntropia\Web\CalendrierLignes;
use Syntropia\Web\Especes;
use Syntropia\Web\Heritage;
use Syntropia\Web\LignesPlantation;
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
$parcellesService = new Parcelles();

$erreursCreation = [];
$erreursPlan = [];
// emplacement → message : une erreur sur la ligne d'une bordure (chantier « peuplement de la parcelle », Phase 2).
$erreursLigne = [];
$lignesService = new LignesPlantation();
$codeSaisi = '';
$nomSaisi = '';
$typeSaisi = 'perenne';
// Répartir une liste de plantes sur les bordures (chantier-repartition-bordures.md, Phase 3) : les choix lus d'un aperçu
// (GET) ou d'un envoi refusé (POST), et le message du refus.
$paramsRepartition = null;
$erreurRepartition = null;
// La liste choisie, résolue au référentiel du site, dans l'ordre reçu ; et la colonne vertébrale de chaque bordure.
$lireRepartition = static function (array $source, array $especes): array {
    $parId = [];
    foreach ($especes as $e) {
        $parId[(int) $e['id']] = $e;
    }
    $liste = [];
    foreach (is_array($source['choix'] ?? null) ? $source['choix'] : [] as $id) {
        if (isset($parId[(int) $id])) {
            $liste[(int) $id] = $parId[(int) $id];
        }
    }
    $colonnes = [];
    foreach (RepartitionBordures::CADRES as $cadre) {
        $id = (int) ($source['colonne_' . $cadre] ?? 0);
        $colonnes[$cadre] = $id > 0 ? $id : null;
    }
    return [array_values($liste), $colonnes];
};

// Un lecteur regarde le projet : rien ne s'écrit d'ici — chantier
// `chantier-inscription-parrainage.md`, Phase 3.
$peutEcrire = $sites->peutEcrireSur($siteId, $compteId);

$jetonOk = $_SERVER['REQUEST_METHOD'] === 'POST' && $auth->jetonValide($_POST['csrf'] ?? null);
// Un enregistrement du plan (en arrière-plan) venu d'une page d'avant une mise à jour : le dire, au lieu de rendre la
// page entière à un script qui attend du JSON (chantier-mises-a-jour.md, Phase 4).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$jetonOk && ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {
    http_response_code(409);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'erreurs' => ['jeton' => 'Cette page date d’avant une mise à jour de l’atelier : rien n’a été '
        . 'enregistré. Rechargez-la, puis refaites votre changement.'], 'version' => null], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($jetonOk) {
    $action = (string) ($_POST['action'] ?? '');
    if (!$peutEcrire) {
        $refus = ['droit' => "Vous lisez ce projet : il ne se modifie pas depuis votre compte."];
        $erreursCreation = $erreursPlan = $refus;
        if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'erreurs' => $refus, 'version' => null], JSON_UNESCAPED_UNICODE);
            exit;
        }
    } elseif ($action === 'creer') {
        $codeSaisi = (string) ($_POST['code'] ?? '');
        $nomSaisi = (string) ($_POST['nom'] ?? '');
        $typeSaisi = (string) ($_POST['type'] ?? 'perenne');
        $resultat = $parcellesService->creer($siteId, $codeSaisi, $nomSaisi, $typeSaisi);
        if ($resultat['erreurs'] === []) {
            header('Location: ' . Configuration::base() . 'conception-parcelles.php?parcelle=' . $resultat['id']);
            exit;
        }
        $erreursCreation = $resultat['erreurs'];
    } elseif ($action === 'plan') {
        $parcelleId = (int) ($_POST['parcelle_id'] ?? 0);
        $resultatPlan = $parcellesService->enregistrerPlan($parcelleId, $siteId, [
            'longueur_m'        => $_POST['longueur_m'] ?? '',
            'largeur_m'         => $_POST['largeur_m'] ?? '',
            'orientation_lignes' => $_POST['orientation_lignes'] ?? '',
            'rattrapage'        => $_POST['rattrapage'] ?? '',
            'passe_pieds_bords' => $_POST['passe_pieds_bords'] ?? '0',
            'bordures'          => $_POST['bordures'] ?? [],
            'bordures_interne'  => $_POST['bordures_interne'] ?? [],
            'conduite'          => $_POST['conduite'] ?? null,
            'stade'             => $_POST['stade'] ?? null,
            'tortue'            => $_POST['tortue'] ?? null,
            'lignes'            => $_POST['lignes'] ?? null,
            'natures'           => $_POST['natures'] ?? null,
            'liane'             => $_POST['liane'] ?? null,
        ], (string) ($_POST['version_plan'] ?? ''));
        $erreursPlan = $resultatPlan['erreurs'];
        // Appelée en tâche de fond par conception-parcelles.js : une
        // redirection rechargerait la page et retomberait sur les valeurs
        // qui viennent d'être remplacées. Le POST classique (JS absent)
        // garde la redirection.
        if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => $erreursPlan === [], 'erreurs' => $erreursPlan,
                'version' => $resultatPlan['version']], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($erreursPlan === []) {
            header('Location: ' . Configuration::base() . 'conception-parcelles.php?parcelle=' . $parcelleId);
            exit;
        }
    } elseif (in_array($action, ['bordure_date', 'perturbation_ajouter', 'perturbation_retirer'], true)) {
        // Le calendrier prévisionnel d'une bordure (chantier « film de la parcelle », Phase 2 bis) : sa plantation prévue,
        // ses perturbations prévues — une liste de dates, chacune avec son geste.
        $parcelleId = (int) ($_POST['parcelle_id'] ?? 0);
        $emplacement = (string) ($_POST['emplacement'] ?? '');
        $cal = new CalendrierLignes();
        if ($parcellesService->parId($parcelleId, $siteId) === null) {
            $r = ['ok' => false, 'erreurs' => ['parcelle' => 'Cette parcelle n’appartient pas à ce projet.']];
        } elseif (Bordures::depuisCle($emplacement) === null) {
            $r = ['ok' => false, 'erreurs' => ['emplacement' => 'Cette bordure n’existe pas.']];
        } elseif ($action === 'bordure_date') {
            $r = $cal->enregistrerDates($parcelleId, $emplacement, (string) ($_POST['mise_en_place_prevue'] ?? ''), '', (string) ($_POST['version_dates'] ?? ''));
        } elseif ($action === 'perturbation_ajouter') {
            $r = $cal->ajouterPerturbation($parcelleId, $emplacement, (string) ($_POST['date_prevue'] ?? ''), (string) ($_POST['geste'] ?? ''));
        } else {
            $cal->retirerPerturbation((int) ($_POST['perturbation_id'] ?? 0), $parcelleId);
            $r = ['ok' => true, 'erreurs' => []];
        }
        if ($r['ok']) {
            header('Location: ' . Configuration::base() . 'conception-parcelles.php?parcelle=' . $parcelleId . '&bordure=' . rawurlencode($emplacement));
            exit;
        }
        $erreursLigne[$emplacement] = implode(' ', $r['erreurs']);
    } elseif ($action === 'ligne_bordure') {
        // La ligne d'un côté de bordure : la série telle qu'affichée, et un geste — ajouter une espèce au bout,
        // retirer une place, vider. Toute la série est réécrite d'un bloc, sous le verrou de sa version.
        $parcelleId = (int) ($_POST['parcelle_id'] ?? 0);
        $emplacement = (string) ($_POST['emplacement'] ?? '');
        $serie = array_values(array_map('intval', is_array($_POST['serie'] ?? null) ? $_POST['serie'] : []));
        $geste = (string) ($_POST['geste'] ?? '');
        $message = null;
        if ($parcellesService->parId($parcelleId, $siteId) === null) {
            $message = 'Cette parcelle n’appartient pas à ce projet.';
        } elseif ($geste === 'ajouter') {
            $ajout = (int) ($_POST['ajout'] ?? 0);
            if ($ajout <= 0) {
                $message = 'Choisissez d’abord une espèce à ajouter.';
            } else {
                $serie[] = $ajout;
            }
        } elseif (preg_match('/^retirer:(\d{1,2})$/', $geste, $m) === 1 && isset($serie[(int) $m[1]])) {
            array_splice($serie, (int) $m[1], 1);
        } elseif ($geste === 'vider') {
            $serie = [];
        } elseif ($geste === 'poser') {
            // La proposition acceptée (Phase 3) : les espèces cochées, dans l'ordre proposé, remplacent la série.
            $serie = array_values(array_map('intval', is_array($_POST['choix'] ?? null) ? $_POST['choix'] : []));
            if ($serie === []) {
                $message = 'Cochez au moins une espèce de la proposition.';
            }
        } else {
            $message = 'Ce geste n’est pas reconnu.';
        }
        if ($message === null) {
            $r = $lignesService->enregistrer($parcelleId, $siteId, $emplacement, $serie, (string) ($_POST['version_ligne'] ?? ''));
            if ($r['ok']) {
                header('Location: ' . Configuration::base() . 'conception-parcelles.php?parcelle=' . $parcelleId
                    . '&bordure=' . rawurlencode($emplacement));
                exit;
            }
            $message = implode(' ', $r['erreurs']);
        }
        $erreursLigne[$emplacement] = $message;
    } elseif ($action === 'repartir_bordures') {
        // La répartition acceptée : recalculée sur l'état présent, puis posée d'un bloc (Phase 3) — rien n'est écrit si
        // le plan ou les bordures ont changé depuis l'aperçu.
        $parcelleId = (int) ($_POST['parcelle_id'] ?? 0);
        $parcelleRep = $parcellesService->parId($parcelleId, $siteId);
        if ($parcelleRep === null) {
            $erreurRepartition = 'Cette parcelle n’appartient pas à ce projet.';
        } else {
            [$liste, $colonnes] = $lireRepartition($_POST, (new Especes())->toutes($siteId));
            $rep = RepartitionBordures::repartir($parcelleRep, $liste, $colonnes);
            if (!$rep['ok']) {
                $erreurRepartition = 'Cette liste ne se répartit pas sur les bordures actuelles : voyez l’aperçu.';
            } else {
                $w = $lignesService->poserRepartition($parcelleId, $siteId, array_map(static fn (array $c): array => $c['serie'], $rep['cotes']),
                    (string) ($_POST['version_plan'] ?? ''));
                if ($w['ok']) {
                    header('Location: ' . Configuration::base() . 'conception-parcelles.php?parcelle=' . $parcelleId . '&bordure=recompte');
                    exit;
                }
                $erreurRepartition = implode(' ', $w['erreurs']);
            }
        }
        $paramsRepartition = $_POST + ['apercu' => '1'];
    }
}

$parcelles = $parcellesService->dUnSite($siteId);
$demandeNouvelle = ($_GET['nouvelle'] ?? '') === '1' || ($parcelles === [] && $erreursCreation === []);
$parcelleActive = null;
if (!$demandeNouvelle) {
    $idDemande = (int) ($_GET['parcelle'] ?? 0);
    foreach ($parcelles as $p) {
        if ((int) $p['id'] === $idDemande) {
            $parcelleActive = $p;
            break;
        }
    }
    if ($parcelleActive === null && $parcelles !== []) {
        $parcelleActive = $parcelles[0];
    }
}
if ($parcelleActive === null && $parcelles === [] && $erreursCreation !== []) {
    // Échec de création avec zéro parcelle existante : rester sur le formulaire.
    $demandeNouvelle = true;
}

/* Ce que le plan affiche à l'ouverture — les valeurs enregistrées si la
 * parcelle en a, les mêmes valeurs par défaut qu'avant sinon (12 x 8 m,
 * quatre bordures à 0,40 m, nord-sud) : une parcelle jamais enregistrée
 * (plan NULL) doit rester utilisable et identique à ce qu'elle montrait
 * déjà, pas en erreur. Chantier « plan de parcelle », Phase 1. */
$fr = static fn (float $v): string => number_format($v, 2, ',', '');
$planLongueur = '12,00';
$planLargeur = '8,00';
$planOrientation = 'nord-sud';
// « passe-pieds » par défaut — répartir le reste sur le passage plutôt
// que sur la culture, un choix par défaut aussi défendable que l'autre.
// Chantier « plan de parcelle », Phase 3 (2026-09-18).
$planRattrapage = 'passe-pieds';
$planPassePiedsBords = false;
$planBordures = [
    'nord'  => ['actif' => true, 'largeur_m' => 0.40, 'brise_vent' => false],
    'sud'   => ['actif' => true, 'largeur_m' => 0.40, 'brise_vent' => false],
    'ouest' => ['actif' => true, 'largeur_m' => 0.40, 'brise_vent' => false],
    'est'   => ['actif' => true, 'largeur_m' => 0.40, 'brise_vent' => false],
];
// Inactive par défaut — contrairement à l'externe, une bordure interne
// (les fournisseurs de biomasse) ne s'impose pas. Chantier « plan de
// parcelle », Phase 4 (2026-09-18).
$planBordureInterne = [
    'nord'  => ['actif' => false, 'largeur_m' => 0.40],
    'sud'   => ['actif' => false, 'largeur_m' => 0.40],
    'ouest' => ['actif' => false, 'largeur_m' => 0.40],
    'est'   => ['actif' => false, 'largeur_m' => 0.40],
];
$litBordures = static function (array $defaut, mixed $enregistre): array {
    if (!is_array($enregistre)) {
        return $defaut;
    }
    foreach (['nord', 'sud', 'ouest', 'est'] as $cote) {
        if (is_array($enregistre[$cote] ?? null) && isset($enregistre[$cote]['largeur_m'])) {
            $defaut[$cote] = [
                'actif' => !empty($enregistre[$cote]['actif']),
                'largeur_m' => (float) $enregistre[$cote]['largeur_m'],
            ] + (array_key_exists('brise_vent', $defaut[$cote]) ? ['brise_vent' => !empty($enregistre[$cote]['brise_vent'])] : []);
        }
    }
    return $defaut;
};
$versionPlan = '';
if ($parcelleActive !== null) {
    $versionPlan = Parcelles::versionPlan($parcelleActive);
    if ($parcelleActive['longueur_m'] !== null) {
        $planLongueur = $fr((float) $parcelleActive['longueur_m']);
    }
    if ($parcelleActive['largeur_m'] !== null) {
        $planLargeur = $fr((float) $parcelleActive['largeur_m']);
    }
    if (in_array($parcelleActive['orientation_lignes'] ?? '', ['nord-sud', 'est-ouest'], true)) {
        $planOrientation = $parcelleActive['orientation_lignes'];
    }
    $planEnregistre = json_decode((string) ($parcelleActive['plan'] ?? ''), true);
    if (in_array($planEnregistre['rattrapage'] ?? '', ['planches', 'passe-pieds'], true)) {
        $planRattrapage = $planEnregistre['rattrapage'];
    }
    $planPassePiedsBords = \Syntropia\Planches::passePiedsAuxBords($planEnregistre);
    $planBordures = $litBordures($planBordures, $planEnregistre['bordures']['externe'] ?? null);
    $planBordureInterne = $litBordures($planBordureInterne, $planEnregistre['bordures']['interne'] ?? null);
}

// Les lignes des bordures : ce qui est posé, compté sur le plan ENREGISTRÉ — les champs du plan, eux, peuvent changer à
// l'écran sans être enregistrés ; conception-parcelles.js masque alors les plants et suspend la saisie.
$lignesBordures = [];
$especesSite = [];
$calendrierBordures = ['dates' => [], 'perturbations' => []];
$bordureOuverte = (string) ($_GET['bordure'] ?? ($erreursLigne !== [] ? (string) array_key_first($erreursLigne) : ''));
// La bordure dont on regarde la proposition (Phase 3) — rien ne s'écrit avant « Poser cette série ».
$bordureProposee = ($_GET['proposer'] ?? '') === '1' ? $bordureOuverte : '';
if ($parcelleActive !== null && !$demandeNouvelle) {
    $lignesBordures = $lignesService->etat($parcelleActive, $siteId);
    $especesSite = (new Especes())->toutes($siteId);
    $calendrierBordures = (new CalendrierLignes())->dUneParcelle((int) $parcelleActive['id']);
}

// Répartir une liste de plantes (Phase 3) : seulement sur des bordures externe et interne toutes vides, et s'il y en a.
$repGeometrie = [];
$repRemplies = false;
$repOuverte = false;
$repEligibles = [];
$repGrisees = [];
$repChoix = [];
$repColonnes = ['externe' => null, 'interne' => null];
$repApercu = null;
if ($parcelleActive !== null && !$demandeNouvelle) {
    $repGeometrie = array_filter(Bordures::lignes($parcelleActive), static fn (array $l): bool => in_array($l['cadre'], RepartitionBordures::CADRES, true));
    foreach ($lignesBordures as $cle => $lb) {
        if (in_array(Bordures::depuisCle($cle)[0], RepartitionBordures::CADRES, true) && $lb['places'] !== []) {
            $repRemplies = true;
        }
    }
    foreach ($especesSite as $e) {
        if (!Bordures::convient('externe', $e) && !Bordures::convient('interne', $e)) {
            continue;
        }
        $raison = RepartitionBordures::ecartee($e);
        if ($raison === null) {
            $repEligibles[] = $e;
        } else {
            $repGrisees[] = ['espece' => $e, 'raison' => $raison];
        }
    }
    $paramsRepartition ??= ($_GET['repartir'] ?? '') === '1' ? $_GET : null;
    $repOuverte = $peutEcrire && $paramsRepartition !== null && $repGeometrie !== [] && !$repRemplies;
    if ($repOuverte) {
        [$repListe, $repColonnes] = $lireRepartition($paramsRepartition, $especesSite);
        $repChoix = array_map(static fn (array $e): int => (int) $e['id'], $repListe);
        if (($paramsRepartition['apercu'] ?? '') === '1') {
            $repApercu = $repListe === [] ? null : RepartitionBordures::repartir($parcelleActive, $repListe, $repColonnes);
        }
    }
}
$couleursPlants = ['#285738', '#8b4528', '#365b8c', '#80528a', '#675b20', '#a13d53', '#1f6f78', '#b36b00', '#556b2f', '#7a3b3b'];
// Une couleur et un sigle par espèce (nom scientifique + variété), les mêmes sur toutes les bordures de la parcelle —
// dans l'ordre où les espèces apparaissent. Au-delà de dix espèces les couleurs reviennent : le sigle (`Sigles`, calculé,
// unique sur la parcelle — décision du porteur, 2026-10-01) les distingue alors, sur le plan et dans la légende.
$especesDessin = [];   // cle => ['nom', 'variete', 'sigle', 'couleur', 'n']
$ajouterEspece = static function (string $cle, string $nom, string $variete) use (&$especesDessin): int {
    if (!isset($especesDessin[$cle])) {
        $especesDessin[$cle] = ['nom' => $nom, 'variete' => $variete, 'n' => 0, 'indice' => count($especesDessin)];
    }
    return $especesDessin[$cle]['indice'];
};
$plantsDessin = [];
$cotesDessin = [];
$poserPlants = static function (string $cleCote, array $g, array $positions, callable $especeDe) use (&$plantsDessin, &$cotesDessin, &$especesDessin) {
    $cotesDessin[] = Bordures::libelle($cleCote);
    $iCote = count($cotesDessin) - 1;
    $dx = ($g['x2'] - $g['x1']) / $g['longueur'];
    $dy = ($g['y2'] - $g['y1']) / $g['longueur'];
    foreach ($positions as [$k, $d]) {
        [$cle, $largeur, $espacement] = $especeDe($k);
        $especesDessin[$cle]['n']++;
        // [x, y, indice de l'espèce, largeur de la bande, envergure (m, ou null), espacement (m), indice du côté]
        $plantsDessin[] = [round($g['x1'] + $d * $dx, 3), round($g['y1'] + $d * $dy, 3), $especesDessin[$cle]['indice'],
            round($g['bande'], 3), $largeur, $espacement, $iCote];
    }
};
if ($repApercu !== null && $repApercu['ok']) {
    // L'aperçu sur le plan : les bordures externe et interne sont vides, leurs plants ne sont que ceux de l'aperçu.
    foreach ($repApercu['cotes'] as $cle => $c) {
        foreach ($c['serie'] as $e) {
            $ajouterEspece('#' . (int) $e['id'], (string) ($e['nom_usuel'] ?: $e['nom_scientifique']), (string) $e['variete']);
        }
        $g = $repGeometrie[$cle] ?? null;
        if ($g !== null && $c['compte']['ok']) {
            $poserPlants($cle, $g, $c['compte']['positions'], static fn (int $k): array => ['#' . (int) $c['serie'][$k]['id'],
                ($c['serie'][$k]['largeur_m'] ?? null) !== null ? (float) $c['serie'][$k]['largeur_m'] : null,
                ($c['serie'][$k]['espacement_retenu_m'] ?? null) !== null ? (float) $c['serie'][$k]['espacement_retenu_m'] : null]);
        }
    }
}
foreach ($lignesBordures as $cle => $lb) {
    foreach ($lb['places'] as $pl) {
        $ajouterEspece($pl['nom_scientifique'] . "\0" . $pl['variete'], (string) $pl['nom'], (string) $pl['variete']);
    }
    $g = $lb['geometrie'];
    if ($g !== null && $lb['compte'] !== null && $lb['compte']['ok']) {
        $poserPlants($cle, $g, $lb['compte']['positions'], static fn (int $k): array => [$lb['places'][$k]['nom_scientifique'] . "\0" . $lb['places'][$k]['variete'],
            $lb['places'][$k]['largeur'] ?? null, $lb['places'][$k]['espacement'] ?? null]);
    }
}
$sigles = \Syntropia\Sigles::calculer(array_values(array_map(static fn (array $e): array => ['nom' => $e['nom'], 'variete' => $e['variete']], $especesDessin)));
foreach (array_keys($especesDessin) as $i => $cle) {
    $especesDessin[$cle]['sigle'] = $sigles[$i];
    $especesDessin[$cle]['couleur'] = $especesDessin[$cle]['indice'] % count($couleursPlants);
}
$especeDe = static fn (array $pl): array => $especesDessin[$pl['nom_scientifique'] . "\0" . $pl['variete']];
$couleurDe = static fn (array $pl): int => $especeDe($pl)['couleur'];
// La légende : les espèces qui ont des plants sur le dessin, dans leur ordre d'apparition.
$legende = array_values(array_filter($especesDessin, static fn (array $e): bool => $e['n'] > 0));
$nombreFr = static fn (float $v): string => rtrim(rtrim(number_format($v, 2, ',', ''), '0'), ',');

$nomAffiche = $auth->nomAffiche() ?? '';
$initiales = '';
foreach (preg_split('/\s+/', trim($nomAffiche)) ?: [] as $mot) {
    if ($mot !== '') {
        $initiales .= mb_strtoupper(mb_substr($mot, 0, 1));
    }
}
$initiales = mb_substr($initiales, 0, 2) ?: '?';
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Parcelles — Syntropia</title>
<link rel="stylesheet" href="assets/css/gabarit.css?v=<?= (int) @filemtime(__DIR__ . '/assets/css/gabarit.css') ?>">
<style>
  /* propre à cette étape : la géométrie de la parcelle, en SVG */
  .bureau-carte{ width:100%; height:100%; filter:drop-shadow(0 10px 24px var(--ombre)); }
  .plan-parcelle-fond{ fill:var(--surface); stroke:var(--line); stroke-width:2; }
  .plan-bordure{ fill:var(--accent-doux); fill-opacity:.55; stroke:var(--accent); stroke-width:2; cursor:default; }
  .outil-deplacer-actif .plan-bordure{ cursor:ns-resize; }
  .outil-deplacer-actif .plan-bordure[data-cote="ouest"],
  .outil-deplacer-actif .plan-bordure[data-cote="est"]{ cursor:ew-resize; }
  .plan-bordure:hover{ fill-opacity:.78; }
  .plan-bordure.en-glissement{ fill-opacity:.9; stroke-width:3; }
  /* Le zoom du plan — mêmes réglages posés sur le bureau que la carte du terrain. */
  .plan-reglures{
    position:absolute; left:1rem; bottom:1rem; z-index:4; margin:0;
    display:flex; align-items:center; gap:.4rem;
  }
  .plan-zoom{
    min-width:2rem; padding:.3rem .6rem; border:1px solid var(--line); border-radius:.5rem; background:var(--surface);
    color:var(--accent-titre); font:600 .9rem var(--titre); line-height:1; cursor:pointer;
  }
  .plan-zoom-tout{ font-size:.72rem; text-transform:uppercase; letter-spacing:.02em; }
  .plan-zoom:hover:not(:disabled){ background:var(--accent-fond); }
  .plan-zoom:disabled{ color:var(--muted); cursor:default; opacity:.6; }
  .plan-zoom:focus-visible{ outline:2px solid var(--accent); outline-offset:1px; }
  .plan-zoome .bureau-carte{ cursor:grab; touch-action:none; }
  .plan-parcouru .bureau-carte{ cursor:grabbing; }
  /* Bordure interne — la biomasse, distincte de l'externe (bloque-herbe,
     coupe-vent, défense) : vert plutôt qu'or, même palette que les
     planches (--socle-accent). Chantier « plan de parcelle », Phase 4. */
  /* Un côté de l'externe coché « brise-vent » (chantier-repartition-bordures.md, Phase 2). */
  .pd-ligne-vent{ grid-template-columns:1fr auto 4.6rem; }
  .pd-vent{ display:flex; align-items:center; gap:.35rem; font:400 .8rem var(--texte); color:var(--muted); }
  .pd-ligne-vent:has(.bordure-active:not(:checked)) .pd-vent{ opacity:.45; }
  .plan-bordure.brise-vent{ stroke:var(--accent); stroke-width:4; stroke-dasharray:14 8; }
  .plan-bordure-interne{ fill:var(--socle-accent-doux); fill-opacity:.6; stroke:var(--socle-accent); stroke-width:2; }
  #in-conduite{
    grid-column:1 / -1; width:100%; padding:.4rem .5rem; font:400 .86rem var(--texte); color:var(--ink);
    background:var(--surface); border:1px solid var(--line); border-radius:.5rem;
  }
  #in-conduite:focus{ outline:2px solid var(--accent); outline-offset:1px; }
  #in-stade, #in-tortue{
    grid-column:1 / -1; width:100%; padding:.4rem .5rem; font:400 .86rem var(--texte); color:var(--ink);
    background:var(--surface); border:1px solid var(--line); border-radius:.5rem;
  }
  #in-stade:focus, #in-tortue:focus{ outline:2px solid var(--accent); outline-offset:1px; }
  .plan-planche{ fill:var(--socle-accent-doux); fill-opacity:.5; }
  .plan-plant{ stroke:#fff; stroke-width:1.5; }
  .plan-plant-envergure{ fill-opacity:.28; stroke-opacity:.6; stroke-width:1; }
  /* Les sigles des plants, la zone de survol, la légende (le porteur, 2026-10-01). */
  .plan-sigle{ font-family:var(--titre); font-weight:600; fill:var(--ink); stroke:var(--surface); paint-order:stroke;
    text-anchor:middle; dominant-baseline:central; pointer-events:none; }
  .plan-plant-cible{ fill:transparent; stroke:none; pointer-events:all; cursor:help; }
  .outil-deplacer-actif .plan-plant-cible{ pointer-events:none; }
  .plan-legende{
    position:absolute; left:1rem; top:2.6rem; z-index:4; width:13rem; max-width:calc(100% - 5rem);
    background:var(--surface); border:1px solid var(--line); border-radius:.5rem; padding:.3rem .6rem;
    font:400 .76rem/1.3 var(--texte); color:var(--ink); box-shadow:0 2px 8px var(--ombre);
  }
  .plan-legende summary{ cursor:pointer; font:600 .74rem var(--titre); text-transform:uppercase; letter-spacing:.03em; color:var(--accent-titre); }
  .plan-legende ul{ list-style:none; margin:.3rem 0 .1rem; padding:0; }
  .plan-legende li{ display:flex; align-items:center; gap:.35rem; break-inside:avoid; padding:.08rem 0; }
  .plan-legende b{ font:600 .74rem var(--titre); min-width:1.9rem; }
  .plan-legende-tous{ display:flex; align-items:center; gap:.4rem; margin:.35rem 0 .1rem; font:400 .76rem var(--texte); color:var(--muted); cursor:pointer; }
  /* Une pastille par espèce : pleine, son sigle s'inscrit sur le plan ; vide, il est masqué. */
  .plan-legende-pastille{ flex:none; width:.8rem; height:.8rem; padding:0; border-radius:50%; border:2px solid var(--c);
    background:var(--c); cursor:pointer; }
  .plan-legende-pastille[aria-pressed="false"]{ background:transparent; }
  .plan-legende-pastille[aria-pressed="false"] ~ b, .plan-legende-pastille[aria-pressed="false"] ~ span{ opacity:.5; }
  .plan-legende-pastille:focus-visible{ outline:2px solid var(--accent); outline-offset:1px; }
  .plan-legende small{ color:var(--muted); }
  .bl-sigle{ font:600 .74rem var(--titre); min-width:1.9rem; }
  /* Légende ouverte sur un écran assez large : le plan lui laisse sa colonne au lieu d'être recouvert (il se recadre). */
  @media (min-width:40.01rem){
    #bureau:has(#plan-legende[open]:not([hidden])) .bureau-carte{ margin-left:14.5rem; width:calc(100% - 14.5rem); }
    /* les boutons de zoom passent à droite de la colonne : une longue légende ne les recouvre pas */
    #bureau:has(#plan-legende[open]:not([hidden])) .plan-reglures{ left:15.5rem; }
  }
  .plan-planche-perenne{ fill:var(--accent); fill-opacity:.28; }
  .plan-bande-perenne{ fill:var(--accent); fill-opacity:.28; }
  #in-position-liane,
  #in-premiere-nature, .nature-planche{ padding:.2rem .3rem; font:400 .8rem var(--texte); color:var(--ink);
    background:var(--surface); border:1px solid var(--line); border-radius:.4rem; }
  .lp-liste label.lp-zebre{ flex-wrap:wrap; }
  .plan-ligne{ stroke:var(--muted); stroke-width:2; stroke-dasharray:6 6; opacity:.7; }
  .plan-ligne-centrale{ stroke:var(--accent); stroke-width:3; stroke-dasharray:none; opacity:.9; }
  /* La ligne des lianes d'une tortue liane : là où la structure porte ses lianes. */
  .plan-ligne-liane{ stroke:#80528a; stroke-width:4; stroke-dasharray:14 6; opacity:.85; }
  .lp-liste{ display:grid; grid-template-columns:repeat(auto-fill, minmax(8.5rem, 1fr)); gap:.3rem .6rem; margin:.3rem 0 .4rem; }
  .lp-liste label{ display:flex; align-items:center; justify-content:space-between; gap:.4rem; font:400 .8rem var(--texte); color:var(--ink); }
  .lp-liste small{ color:var(--muted); }
  .lp-liste input{ width:3.2rem; padding:.2rem .3rem; font:400 .82rem var(--texte); color:var(--ink); background:var(--surface);
    border:1px solid var(--line); border-radius:.4rem; text-align:right; }
  #plantations.plan-perime{ display:none; }
  .bl-cote{ border:1px solid var(--line); border-radius:.5rem; padding:.35rem .55rem; margin:.35rem 0; }
  .bl-cote > summary{ cursor:pointer; font:500 .84rem var(--texte); color:var(--ink); }
  .bl-cote > summary small{ color:var(--muted); font-weight:400; }
  .bl-serie{ list-style:none; margin:.4rem 0; padding:0; display:flex; flex-direction:column; gap:.25rem; }
  .bl-serie li{ display:flex; align-items:center; gap:.4rem; font:400 .82rem var(--texte); color:var(--ink); }
  .bl-pastille{ width:.7rem; height:.7rem; border-radius:50%; flex:none; border:1px solid var(--line); }
  .bl-serie .bl-nombre{ margin-left:auto; color:var(--muted); white-space:nowrap; }
  .bl-retirer{ background:none; border:0; color:var(--muted); cursor:pointer; font-size:.9rem; padding:0 .2rem; }
  .bl-retirer:hover, .bl-retirer:focus-visible{ color:var(--alerte); }
  .bl-ajout{ display:flex; gap:.35rem; align-items:center; margin-top:.3rem; }
  .bl-ajout select{ flex:1; min-width:0; padding:.3rem .4rem; font:400 .8rem var(--texte); color:var(--ink);
    background:var(--surface); border:1px solid var(--line); border-radius:.4rem; }
  .bl-bouton{ padding:.3rem .6rem; font:500 .78rem var(--texte); color:var(--ink); background:var(--accent-doux);
    border:1px solid var(--accent); border-radius:.4rem; cursor:pointer; }
  .bl-bouton[disabled], .bl-cote.bl-suspendu .bl-bouton, .bl-cote.bl-suspendu .bl-retirer{ opacity:.45; pointer-events:none; }
  .bl-alerte{ color:var(--alerte); font:400 .78rem var(--texte); margin:.3rem 0 0; }
  #bl-suspendu{ color:var(--alerte); }
  .bl-calendrier{ border-top:1px dashed var(--line); margin-top:.5rem; }
  .bl-date{ display:flex; flex-direction:column; gap:.15rem; font:400 .78rem var(--texte); color:var(--muted); flex:1; }
  .bl-ajout input[type=date]{ padding:.25rem .3rem; font:400 .8rem var(--texte); color:var(--ink); background:var(--surface); border:1px solid var(--line); border-radius:.4rem; min-width:0; }
  .bl-ajout{ flex-wrap:wrap; }
  .bl-proposer{ display:inline-block; margin:.2rem 0 .3rem; font:500 .8rem var(--texte); color:var(--accent); }
  .bl-proposition{ border-top:1px dashed var(--line); margin-top:.4rem; }
  .bl-choix{ list-style:none; margin:.3rem 0; padding:0; display:flex; flex-direction:column; gap:.3rem; }
  .bl-choix label{ display:flex; gap:.4rem; align-items:flex-start; font:400 .8rem/1.35 var(--texte); color:var(--ink); }
  .bl-reserve{ display:block; color:var(--alerte); }
  .bl-cote.bl-suspendu .bl-proposer{ opacity:.45; pointer-events:none; }
  /* Répartir une liste de plantes (chantier-repartition-bordures.md, Phase 3). */
  .rb-bloc{ border:1px dashed var(--line); border-radius:.5rem; padding:.35rem .55rem; margin:.35rem 0 .6rem; }
  .rb-grisee{ opacity:.5; }
  .rb-colonne{ grid-template-columns:1fr minmax(0, 9rem); }
  .rb-colonne select{ min-width:0; max-width:100%; }
  .rb-cotes{ margin:.3rem 0; padding-left:1rem; font:400 .8rem/1.4 var(--texte); color:var(--ink); }
  .rb-cotes li{ margin:.2rem 0; }
  .rb-cotes small{ display:block; }
  #plantations.plan-apercu{ opacity:.75; }
  .plan-passe-pied{ fill:transparent; }
  .plan-cote{ font:500 15px var(--titre); fill:var(--muted); }
  .plan-mesure{ font:600 13px var(--texte); fill:var(--ink); font-variant-numeric:tabular-nums; }
  .plan-fleche{ stroke:var(--muted); stroke-width:1.4; fill:none; }
  .visually-hidden{ position:absolute; width:1px; height:1px; overflow:hidden; clip:rect(0 0 0 0); white-space:nowrap; }

  /* l'état « aucune parcelle encore » — un formulaire simple, pas de SVG */
  .parc-creation{ width:100%; max-width:26rem; }
  .parc-annonce{ font:400 .92rem/1.5 var(--texte); color:var(--muted); margin:0 0 1.4rem; text-align:center; }
  .parc-champ{ margin-bottom:1rem; }
  .parc-champ label, .parc-champ legend{ display:block; font:600 .85rem var(--titre); color:var(--ink); margin-bottom:.35rem; padding:0; }
  .parc-champ small{ display:block; font:400 .78rem/1.3 var(--texte); color:var(--muted); margin-bottom:.3rem; }
  .parc-champ input[type=text]{
    width:100%; padding:.5rem .7rem; font:400 .9rem var(--texte); color:var(--ink);
    background:var(--surface); border:1px solid var(--line); border-radius:.5rem;
  }
  .parc-champ input:focus{ outline:2px solid var(--accent); outline-offset:1px; }
  .parc-choix{ display:flex; gap:1rem; }
  .parc-choix label{ display:flex; align-items:center; gap:.35rem; font:400 .86rem var(--texte); color:var(--ink); }
  .parc-erreur{
    margin:0 0 .8rem; padding:.5rem .7rem; border-radius:.5rem; background:var(--alerte);
    color:var(--bouton-encre); font:500 .82rem var(--texte);
  }
  .parc-bouton{
    width:100%; margin-top:.3rem; padding:.6rem; border:0; border-radius:.6rem;
    background:var(--accent); color:var(--bouton-encre); font:600 .92rem var(--titre); cursor:pointer;
  }
  .parc-bouton:hover{ opacity:.92; }
  .parc-annuler{ display:block; text-align:center; margin-top:.7rem; font:400 .8rem var(--texte); color:var(--muted); }

  /* le bouton d'enregistrement du plan, et ses messages d'état — noms
     hérités de « les mesures réelles », un bloc distinct disparu avec la
     Phase 2 du chantier « plan de parcelle » (voir Parcelles::enregistrer) */
  .pd-reel-note{ font:400 .74rem/1.35 var(--texte); color:var(--muted); margin:.5rem 0 .6rem; }
  .pd-reel-bouton{
    width:100%; margin-top:.2rem; padding:.5rem; border:0; border-radius:.5rem;
    background:var(--accent); color:var(--bouton-encre); font:600 .82rem var(--titre); cursor:pointer;
  }
  .pd-reel-bouton:hover{ opacity:.92; }
  .pd-reel-erreur{ font:400 .76rem/1.3 var(--texte); color:var(--alerte); margin:.4rem 0 0; }
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

<?php if (!$demandeNouvelle): ?>
  <div class="bandeau-outils" role="toolbar" aria-label="Outils du plan" id="bandeau-outils">
    <button type="button" class="outils-declencheur" id="outils-declencheur" aria-haspopup="true"
            aria-expanded="false" aria-controls="outils-liste">
      <span id="outils-icone-actif" aria-hidden="true">↖</span>
      <span id="outils-texte-actif">Sélection</span>
      <svg class="outils-declencheur-chevron" viewBox="0 0 10 10" aria-hidden="true"><polygon points="1,3 5,8 9,3" fill="currentColor"/></svg>
    </button>

    <div class="outils-liste" id="outils-liste">
      <input type="radio" name="outil" id="outil-selection" checked>
      <label class="outil-bouton" for="outil-selection">↖ Sélection</label>

      <input type="radio" name="outil" id="outil-deplacer">
      <label class="outil-bouton" for="outil-deplacer">⇔ Déplacer un bord</label>

      <span class="outil-separateur"></span>

      <input type="radio" name="outil" id="outil-ligne" disabled>
      <label class="outil-bouton a-venir" for="outil-ligne" title="À venir">✎ Tracer une ligne</label>

      <input type="radio" name="outil" id="outil-gomme" disabled>
      <label class="outil-bouton a-venir" for="outil-gomme" title="À venir">⌫ Gomme</label>
    </div>
  </div>
<?php endif; ?>

  <div class="bulle" id="bulle" role="status"></div>

  <div class="corps">
    <div class="scrim" id="scrim-gauche"></div>
    <nav class="panneau-gauche" id="panneau-gauche" aria-label="Navigation de l'atelier" aria-hidden="true">
      <div class="pg-entete">
        <a class="marque-nom" href="./">Syntropia</a>
      </div>
      <div class="pg-corps">
        <div class="pg-section">
          <p class="pg-titre pg-nomsite"><?= htmlspecialchars((string) $site['nom'], ENT_QUOTES, 'UTF-8') ?></p>
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
<?php foreach ($parcelles as $p):
        $estActive = !$demandeNouvelle && $parcelleActive !== null && (int) $p['id'] === (int) $parcelleActive['id']; ?>
            <li><a href="conception-parcelles.php?parcelle=<?= (int) $p['id'] ?>"<?= $estActive ? ' class="active" aria-current="page"' : '' ?>><span class="pg-puce">▤</span><span class="pg-libelle"><?= htmlspecialchars((string) $p['code'], ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars((string) $p['nom'], ENT_QUOTES, 'UTF-8') ?></span></a></li>
<?php endforeach; ?>
            <li><a href="conception-parcelles.php?nouvelle=1"<?= $demandeNouvelle ? ' class="active" aria-current="page"' : '' ?>><span class="pg-puce">+</span><span class="pg-libelle">Nouvelle parcelle</span></a></li>
          </ul>
        </div>
<?php if (!$demandeNouvelle && $parcelleActive !== null): ?>
        <div class="pg-section">
          <p class="pg-titre"><?= htmlspecialchars((string) $parcelleActive['code'], ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars((string) $parcelleActive['nom'], ENT_QUOTES, 'UTF-8') ?></p>
          <ul class="pg-liste">
            <li><a href="conception-parcelles.php?parcelle=<?= (int) $parcelleActive['id'] ?>" class="active" aria-current="page"><span class="pg-puce">▤</span><span class="pg-libelle">Le plan</span></a></li>
            <li><a href="conception-scenario.php?parcelle=<?= (int) $parcelleActive['id'] ?>"><span class="pg-puce">≋</span><span class="pg-libelle">Le scénario</span></a></li>
            <li><a href="conception-materiel-et-eau.php?parcelle=<?= (int) $parcelleActive['id'] ?>"><span class="pg-puce">◍</span><span class="pg-libelle">Matériel et eau</span></a></li>
            <li><a href="#" class="a-venir" tabindex="-1" aria-disabled="true"><span class="pg-puce">✎</span><span class="pg-libelle">Le suivi</span></a></li>
          </ul>
        </div>
<?php endif; ?>
        <p class="pg-pied">Les parcelles sont réelles, en base — le plan (planches, bordures) reste un prototype, voir architecture-technique.md.</p>
      </div>
    </nav>

<?php if ($demandeNouvelle): ?>
    <main class="bureau" id="bureau">
      <div class="parc-creation">
        <p class="parc-annonce"><?= $parcelles === [] ? 'Ouvrez votre première parcelle — vous pourrez en ajouter d\'autres ensuite.' : 'Ouvrez une nouvelle parcelle.' ?></p>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($auth->jeton(), ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="action" value="creer">
<?php if ($erreursCreation !== []): ?>
          <p class="parc-erreur"><?= htmlspecialchars(implode(' ', $erreursCreation), ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
          <div class="parc-champ">
            <label for="code">Code</label>
            <small>Un repère court — A, B, P1… pour la retrouver dans les listes.</small>
            <input type="text" id="code" name="code" placeholder="A" maxlength="32"
                   value="<?= htmlspecialchars($codeSaisi, ENT_QUOTES, 'UTF-8') ?>">
          </div>
          <div class="parc-champ">
            <label for="nom">Nom</label>
            <input type="text" id="nom" name="nom" placeholder="Le verger, la butte du bas…"
                   value="<?= htmlspecialchars($nomSaisi, ENT_QUOTES, 'UTF-8') ?>">
          </div>
          <fieldset class="parc-champ">
            <legend>Type</legend>
            <div class="parc-choix">
              <label><input type="radio" class="radio" name="type" value="perenne"<?= $typeSaisi === 'perenne' ? ' checked' : '' ?>>Pérenne</label>
              <label><input type="radio" class="radio" name="type" value="potager"<?= $typeSaisi === 'potager' ? ' checked' : '' ?>>Potager</label>
            </div>
          </fieldset>
          <button type="submit" class="parc-bouton">Créer la parcelle</button>
        </form>
<?php if ($parcelles !== []): ?>
        <a class="parc-annuler" href="conception-parcelles.php?parcelle=<?= (int) $parcelles[0]['id'] ?>">Annuler, revenir à mes parcelles</a>
<?php endif; ?>
      </div>
    </main>
<?php else: ?>
    <main class="bureau" id="bureau">
      <svg class="bureau-carte" id="plan" viewBox="0 0 1240 900" role="img"
           aria-label="Plan de la parcelle <?= htmlspecialchars((string) $parcelleActive['code'], ENT_QUOTES, 'UTF-8') ?> — prototype, 12 mètres sur 8">
        <g transform="translate(70,40)">
          <rect class="plan-parcelle-fond" x="0" y="0" width="1200" height="800"/>
          <g id="planches"></g>
          <g id="lignes-plan" aria-hidden="true"></g>
          <rect class="plan-bordure" data-cote="nord"  id="bordure-nord"  x="0"    y="0"   width="1200" height="40"/>
          <rect class="plan-bordure" data-cote="sud"   id="bordure-sud"   x="0"    y="760" width="1200" height="40"/>
          <rect class="plan-bordure" data-cote="ouest" id="bordure-ouest" x="0"    y="0"   width="40"   height="800"/>
          <rect class="plan-bordure" data-cote="est"   id="bordure-est"   x="1160" y="0"   width="40"   height="800"/>
          <rect class="plan-bordure-interne" data-cote="nord"  id="bordure-interne-nord"  x="0" y="0" width="1200" height="0"/>
          <rect class="plan-bordure-interne" data-cote="sud"   id="bordure-interne-sud"   x="0" y="0" width="1200" height="0"/>
          <rect class="plan-bordure-interne" data-cote="ouest" id="bordure-interne-ouest" x="0" y="0" width="0" height="800"/>
          <rect class="plan-bordure-interne" data-cote="est"   id="bordure-interne-est"   x="0" y="0" width="0" height="800"/>
          <g id="plantations" aria-hidden="true"></g>

          <line class="plan-fleche" id="fleche-longueur" x1="0" y1="-16" x2="1200" y2="-16" marker-start="url(#fl)" marker-end="url(#fl)"/>
          <text class="plan-mesure" id="mesure-longueur" x="600" y="-24" text-anchor="middle">12,00 m</text>
          <line class="plan-fleche" id="fleche-largeur" x1="-16" y1="0" x2="-16" y2="800" marker-start="url(#fl)" marker-end="url(#fl)"/>
          <text class="plan-mesure" id="mesure-largeur" x="-24" y="400" text-anchor="middle" transform="rotate(-90 -24 400)">8,00 m</text>

          <text class="plan-cote" id="cote-nord" x="600" y="24" text-anchor="middle">nord</text>
          <text class="plan-cote" id="cote-sud" x="600" y="792" text-anchor="middle">sud</text>
          <text class="plan-cote" id="cote-ouest" x="20" y="404" text-anchor="middle" transform="rotate(-90 20 404)">ouest</text>
          <text class="plan-cote" id="cote-est" x="1180" y="404" text-anchor="middle" transform="rotate(90 1180 404)">est</text>
        </g>
        <defs>
          <marker id="fl" viewBox="0 0 10 10" refX="5" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
            <path d="M1,1 L9,5 L1,9" fill="none" stroke="var(--muted)" stroke-width="1.6"/>
          </marker>
        </defs>
      </svg>
<?php if ($legende !== []): ?>
      <details class="plan-legende" id="plan-legende" open>
        <summary><?= $repApercu !== null && $repApercu['ok'] ? 'Légende de l’aperçu' : 'Légende' ?> · <?= count($legende) ?></summary>
        <label class="plan-legende-tous"><input type="checkbox" class="case" id="sigles-tous" checked> Sigles sur le plan</label>
        <ul>
<?php foreach ($legende as $e): $nomLegende = $e['nom'] . ($e['variete'] !== '' ? ' (' . $e['variete'] . ')' : ''); ?>
          <li><button type="button" class="plan-legende-pastille" data-espece="<?= (int) $e['indice'] ?>" data-nom="<?= htmlspecialchars($nomLegende, ENT_QUOTES, 'UTF-8') ?>"
                aria-pressed="true" aria-label="Sigle de <?= htmlspecialchars($nomLegende, ENT_QUOTES, 'UTF-8') ?> sur le plan"
                title="Afficher ou masquer son sigle sur le plan" style="--c:<?= $couleursPlants[$e['couleur']] ?>"></button><b><?= htmlspecialchars($e['sigle'], ENT_QUOTES, 'UTF-8') ?></b>
            <span><?= htmlspecialchars($e['nom'] . ($e['variete'] !== '' ? ' (' . $e['variete'] . ')' : ''), ENT_QUOTES, 'UTF-8') ?> <small><?= (int) $e['n'] ?></small></span></li>
<?php endforeach; ?>
        </ul>
      </details>
<?php endif; ?>
      <p class="plan-reglures" role="group" aria-label="Zoom du plan">
        <button type="button" id="plan-zoom-moins" class="plan-zoom" aria-label="Dézoomer" title="Dézoomer" disabled>−</button>
        <button type="button" id="plan-zoom-plus" class="plan-zoom" aria-label="Zoomer" title="Zoomer">+</button>
        <button type="button" id="plan-zoom-tout" class="plan-zoom plan-zoom-tout" hidden>tout voir</button>
      </p>
    </main>

    <div class="scrim" id="scrim-droit"></div>
    <button class="tirette" id="tirette" aria-expanded="false" aria-controls="panneau-droit"
            aria-label="Afficher le panneau Données">
      <svg class="tirette-triangle" viewBox="0 0 10 10" aria-hidden="true"><polygon points="1,1 9,5 1,9" fill="currentColor"/></svg>
      <span class="tirette-texte">Données</span>
    </button>
    <aside class="panneau-droit" id="panneau-droit" aria-hidden="true">
      <div class="pd-corps">
        <input type="hidden" id="version-plan" value="<?= htmlspecialchars($versionPlan, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" id="plan-csrf" value="<?= htmlspecialchars($auth->jeton(), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" id="plan-parcelle-id" value="<?= (int) $parcelleActive['id'] ?>">
<?php
$conduiteSite = Conduite::depuis($site['conduite'] ?? null);
$siteAChoisi = trim((string) ($site['conduite'] ?? '')) !== '';
$conduiteResolue = Heritage::conduite($parcelleActive, $site);
$texteConduite = static function (Conduite $c): string {
    return $c->explication() . ($c->reserve() !== null ? ' ' . $c->reserve() : '');
};
$resume = static fn (Conduite $c): string => count($c->strates()) . ' strates, par ' . $c->critereDeStrate() . '.';
?>
        <p class="pd-titre">Conduite</p>
        <div class="pd-ligne">
          <select id="in-conduite" aria-label="Conduite de la parcelle">
            <option value=""<?= trim((string) ($parcelleActive['conduite'] ?? '')) === '' ? ' selected' : '' ?> data-resume="<?= htmlspecialchars(($siteAChoisi ? '' : 'Le site n’a pas encore choisi : la syntropie s’applique en attendant. ') . $resume($conduiteSite), ENT_QUOTES, 'UTF-8') ?>">Comme le site — <?= $siteAChoisi ? htmlspecialchars($conduiteSite->libelle(), ENT_QUOTES, 'UTF-8') : 'pas encore choisie' ?></option>
<?php foreach (Conduite::toutes() as $cas): ?>
            <option value="<?= $cas->value ?>"<?= trim((string) ($parcelleActive['conduite'] ?? '')) === $cas->value ? ' selected' : '' ?> data-resume="<?= htmlspecialchars($resume($cas) . ($cas->reserve() !== null ? ' ' . $cas->reserve() : ''), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($cas->libelle(), ENT_QUOTES, 'UTF-8') ?></option>
<?php endforeach; ?>
          </select>
        </div>
        <p class="pd-note" id="conduite-resume"><?= htmlspecialchars($resume($conduiteResolue['conduite']) . ($conduiteResolue['conduite']->reserve() !== null ? ' ' . $conduiteResolue['conduite']->reserve() : ''), ENT_QUOTES, 'UTF-8') ?> Le vocabulaire des strates du scénario en dépend.</p>

        <p class="pd-titre">Le stade du terrain</p>
        <div class="pd-ligne">
          <select id="in-stade" aria-label="Stade du terrain de la parcelle">
            <option value=""<?= trim((string) ($parcelleActive['stade'] ?? '')) === '' ? ' selected' : '' ?> data-resume="Dites d’où vous partez : le scénario le rappelle, avec ce que la formation en dit. Deux stades, par parcelle.">Pas encore dit</option>
<?php foreach (Stade::tous() as $st): ?>
            <option value="<?= $st->value ?>"<?= trim((string) ($parcelleActive['stade'] ?? '')) === $st->value ? ' selected' : '' ?> data-resume="<?= htmlspecialchars($st->explication() . ' ' . $st->observe() . ' ' . Stade::premierGeste(), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($st->libelle(), ENT_QUOTES, 'UTF-8') ?></option>
<?php endforeach; ?>
          </select>
        </div>
<?php $stadeActif = Stade::depuis($parcelleActive['stade'] ?? null); ?>
        <p class="pd-note" id="stade-resume"><?= htmlspecialchars($stadeActif !== null ? $stadeActif->explication() . ' ' . $stadeActif->observe() . ' ' . Stade::premierGeste() : 'Dites d’où vous partez : le scénario le rappelle, avec ce que la formation en dit. Deux stades, par parcelle.', ENT_QUOTES, 'UTF-8') ?></p>

        <p class="pd-titre">Le type de tortue</p>
        <div class="pd-ligne">
          <select id="in-tortue" aria-label="Type de tortue de la parcelle">
            <option value=""<?= trim((string) ($parcelleActive['tortue'] ?? '')) === '' ? ' selected' : '' ?> data-resume="Ce que la parcelle portera sur ses planches : il orientera ce que l’atelier vous proposera d’y planter.">Pas encore dit</option>
<?php foreach (Tortue::tous() as $to): ?>
            <option value="<?= $to->value ?>"<?= trim((string) ($parcelleActive['tortue'] ?? '')) === $to->value ? ' selected' : '' ?> data-resume="<?= htmlspecialchars($to->explication(), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($to->libelle(), ENT_QUOTES, 'UTF-8') ?></option>
<?php endforeach; ?>
          </select>
        </div>
<?php $tortueActive = Tortue::depuis($parcelleActive['tortue'] ?? null); ?>
        <p class="pd-note" id="tortue-resume"><?= htmlspecialchars($tortueActive !== null ? $tortueActive->explication() : 'Ce que la parcelle portera sur ses planches : il orientera ce que l’atelier vous proposera d’y planter.', ENT_QUOTES, 'UTF-8') ?></p>

        <p class="pd-titre">La parcelle <span class="pd-unite">(m)</span></p>
        <div class="pd-ligne">
          <span class="pd-ligne-nom">Longueur (est ↔ ouest)</span>
          <input type="text" id="in-longueur" value="<?= htmlspecialchars($planLongueur, ENT_QUOTES, 'UTF-8') ?>" inputmode="decimal">
        </div>
        <div class="pd-ligne">
          <span class="pd-ligne-nom">Largeur (nord ↔ sud)</span>
          <input type="text" id="in-largeur" value="<?= htmlspecialchars($planLargeur, ENT_QUOTES, 'UTF-8') ?>" inputmode="decimal">
        </div>

        <p class="pd-titre">Sens des planches</p>
        <div class="parc-choix">
          <label><input type="radio" class="radio" name="sens-planches" id="sens-nord-sud" value="nord-sud"<?= $planOrientation === 'nord-sud' ? ' checked' : '' ?>>Nord ↔ Sud</label>
          <label><input type="radio" class="radio" name="sens-planches" id="sens-est-ouest" value="est-ouest"<?= $planOrientation === 'est-ouest' ? ' checked' : '' ?>>Est ↔ Ouest</label>
        </div>

        <p class="pd-titre">Rattrapage</p>
        <p class="pd-note">1,20 m de planche et 0,50 m de passe-pied ne divisent pas forcément la
           largeur disponible tout rond — le reste se répartit sur les planches, ou sur les
           passe-pieds, jamais les deux à la fois.</p>
        <div class="parc-choix">
          <label><input type="radio" class="radio" name="rattrapage" id="rattrapage-passe-pieds" value="passe-pieds"<?= $planRattrapage === 'passe-pieds' ? ' checked' : '' ?>>Sur les passe-pieds</label>
          <label><input type="radio" class="radio" name="rattrapage" id="rattrapage-planches" value="planches"<?= $planRattrapage === 'planches' ? ' checked' : '' ?>>Sur les planches</label>
        </div>

        <p class="pd-titre">Passe-pieds</p>
        <p class="pd-note">Chaque demi-planche se travaille depuis un passe-pied, sans marcher sur la planche. Sans passe-pied
           le long des bords, les planches des deux bords ne font qu'une demi-planche.</p>
        <label class="pd-ligne-nom"><input type="checkbox" class="case" id="passe-pieds-bords"<?= $planPassePiedsBords ? ' checked' : '' ?>>Un passe-pied le long des deux bords</label>

        <p class="pd-titre">Lignes par planche</p>
        <p class="pd-note">Les lignes de plantation de chaque planche, à intervalles égaux dans sa largeur ; la
           ligne centrale (en trait plein sur le plan) porte la biomasse et la structure, jamais de tuteurs. Trois
           par défaut — elles recevront les plantes de service.</p>
        <div id="lignes-planches" class="lp-liste"></div>

        <div id="natures-bloc" hidden>
          <p class="pd-titre">Planches pérennes et annuelles</p>
          <p class="pd-note" id="natures-note"></p>
          <div class="pd-ligne">
            <span class="pd-ligne-nom" id="premiere-libelle">La première planche</span>
            <select id="in-premiere-nature" aria-label="Nature de la première planche ou bande">
<?php foreach (Planches::NATURES as $cle => $lib): ?>
              <option value="<?= $cle ?>"<?= Planches::premiereNature($planEnregistre ?? null) === $cle ? ' selected' : '' ?>><?= $lib ?></option>
<?php endforeach; ?>
            </select>
          </div>
        </div>

        <div id="liane-bloc"<?= ($parcelleActive['tortue'] ?? null) === 'liane' ? '' : ' hidden' ?>>
          <p class="pd-titre">La ligne des lianes</p>
          <p class="pd-note">Tortue liane : une structure porte des lianes émergentes, et la strate haute (vigne, kiwi)
             à leur ombre ; dessous, des bandes de culture. L'atelier dessine le plan au sol, pas la structure : une ligne
             marque où montent les lianes — « à l’est ou au nord, ou au cœur de la tortue entre les poteaux centraux »
             (Théry, ch. 15). En tireté violet sur le plan.</p>
          <div class="pd-ligne">
            <span class="pd-ligne-nom">Elle court</span>
            <select id="in-position-liane" aria-label="Position de la ligne des lianes">
<?php foreach (Planches::POSITIONS_LIANE as $cle => $lib): ?>
              <option value="<?= $cle ?>"<?= Planches::positionLiane($planEnregistre ?? null) === $cle ? ' selected' : '' ?>><?= $lib ?></option>
<?php endforeach; ?>
            </select>
          </div>
        </div>

        <p class="pd-titre">Bordure externe <span class="pd-unite">(m)</span></p>
        <p class="pd-note">Bloque-herbe, coupe-vent, défense contre les animaux — une haie
           défensive en bord de parcelle. Cochez « vent » le côté d'où vient le vent dominant :
           c'est lui qui devra l'arrêter, avec des plantes brise-vent.</p>
<?php foreach (['nord' => 'Nord', 'sud' => 'Sud', 'ouest' => 'Ouest', 'est' => 'Est'] as $cote => $libelle): ?>
        <div class="pd-ligne pd-ligne-vent">
          <label class="pd-ligne-nom"><input type="checkbox"<?= $planBordures[$cote]['actif'] ? ' checked' : '' ?> data-cote="<?= $cote ?>" class="case bordure-active"><?= $libelle ?></label>
          <label class="pd-vent" title="Ce côté arrête le vent dominant"><input type="checkbox"<?= $planBordures[$cote]['brise_vent'] ? ' checked' : '' ?> data-cote="<?= $cote ?>" class="case bordure-brise-vent" aria-label="Côté <?= $cote ?> : brise-vent">vent</label>
          <input type="text" class="bordure-largeur" data-cote="<?= $cote ?>" value="<?= htmlspecialchars($fr($planBordures[$cote]['largeur_m']), ENT_QUOTES, 'UTF-8') ?>" inputmode="decimal">
        </div>
<?php endforeach; ?>

        <p class="pd-titre">Bordure interne <span class="pd-unite">(m)</span></p>
        <p class="pd-note">Les fournisseurs de biomasse, juste à l'intérieur de la bordure
           externe — inactive par défaut, une parcelle n'en a pas forcément besoin.</p>
<?php foreach (['nord' => 'Nord', 'sud' => 'Sud', 'ouest' => 'Ouest', 'est' => 'Est'] as $cote => $libelle): ?>
        <div class="pd-ligne">
          <label class="pd-ligne-nom"><input type="checkbox"<?= $planBordureInterne[$cote]['actif'] ? ' checked' : '' ?> data-cote="<?= $cote ?>" class="case bordure-interne-active"><?= $libelle ?></label>
          <input type="text" class="bordure-interne-largeur" data-cote="<?= $cote ?>" value="<?= htmlspecialchars($fr($planBordureInterne[$cote]['largeur_m']), ENT_QUOTES, 'UTF-8') ?>" inputmode="decimal">
        </div>
<?php endforeach; ?>
        <p class="pd-reel-erreur" id="plan-erreur"<?= $erreursPlan === [] ? ' hidden' : '' ?>><?= htmlspecialchars(implode(' ', $erreursPlan), ENT_QUOTES, 'UTF-8') ?></p>
        <p class="pd-reel-note" id="plan-etat" hidden></p>
        <p class="pd-reel-note">Enregistrer pose aussi cette parcelle, dimensionnée, sur
           <a href="conception-etude-site.php">la carte interactive du terrain</a> — par un
           rectangle posé d'un clic, plus rien à ressaisir.</p>
        <button type="button" class="pd-reel-bouton" id="plan-bouton">Enregistrer le plan</button>

        <p class="pd-titre" id="bl-titre">Les plantes des bordures</p>
        <p class="pd-note">Une ligne de plants par côté, au milieu de sa bande : une espèce, ou plusieurs qui
           alternent. L'écart entre deux plants voisins est la moyenne de leurs espacements, et chaque bout
           garde la moitié du sien. Les espacements viennent de <a href="especes.php">Espèces</a>.</p>
        <p class="pd-note" id="bl-suspendu" hidden>Le plan a changé : enregistrez-le d'abord — les plants se
           comptent sur le plan enregistré.</p>
<?php if ($peutEcrire && $repGeometrie !== []):
    $nomEsp = static fn (array $e): string => (string) ($e['nom_usuel'] ?: $e['nom_scientifique']) . ((string) $e['variete'] !== '' ? ' (' . $e['variete'] . ')' : '');
    $fonctionsEsp = static function (array $e): string {
        $f = [];
        if (RepartitionBordures::bloqueHerbe($e)) { $f[] = 'bloque-herbe'; }
        if (RepartitionBordures::briseVent($e)) { $f[] = 'brise-vent'; }
        if (Bordures::convient('interne', $e)) { $f[] = 'biomasse'; }
        return implode(', ', $f);
    };
    $strateEsp = static function (array $e): string {
        $s = \Syntropia\LignesDeService::stratesService($e);
        return $s === [] ? 'strate non notée' : implode(', ', array_map(static fn (string $x): string => mb_strtolower(\Syntropia\Strates::REPERES[$x]['libelle'] ?? $x), $s));
    }; ?>
        <div class="rb-bloc" id="rb">
<?php if ($repRemplies): ?>
          <p class="pd-note"><b>Répartir une liste de plantes</b> se fait sur des bordures vides : des côtés portent déjà
            des plantes. Composez les autres côté par côté, ou videz d'abord toutes les lignes des bordures.</p>
<?php elseif (!$repOuverte): ?>
          <a class="bl-proposer" href="conception-parcelles.php?parcelle=<?= (int) $parcelleActive['id'] ?>&amp;repartir=1">Répartir une liste de plantes sur toutes les bordures</a>
<?php else: ?>
          <p class="pd-titre" style="margin-top:.4rem">Répartir une liste de plantes</p>
          <p class="pd-note">Cochez les plantes de vos bordures : l'atelier les dispose sur chaque côté dont elles
            remplissent la fonction, en alternance, à leur espacement. Au sud et à l'ouest de l'externe, des émergentes
            seulement ; sur un côté coché « vent », des brise-vent entre les bloque-herbe. Il faut deux espèces au
            moins par côté — la diversité fait la réussite du système. Rien n'est écrit avant « Poser ces bordures ».</p>
<?php if ($erreurRepartition !== null): ?>
          <p class="bl-alerte" role="alert"><?= htmlspecialchars($erreurRepartition, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
<?php if (($paramsRepartition['apercu'] ?? '') === '1' && $repChoix === []): ?>
          <p class="bl-alerte">Cochez d'abord les plantes de la liste.</p>
<?php elseif ($repApercu !== null): ?>
          <div class="rb-apercu">
<?php if (!$repApercu['ok']): ?>
            <p class="bl-alerte"><b>Rien n'est réparti</b> : chaque côté doit recevoir deux espèces au moins de la liste.</p>
            <ul class="rb-cotes">
<?php foreach ($repApercu['cotes'] as $cle => $c): if ($c['refus'] === null) { continue; } ?>
              <li><b><?= htmlspecialchars(Bordures::libelle($cle), ENT_QUOTES, 'UTF-8') ?></b> : <?= htmlspecialchars($c['refus'], ENT_QUOTES, 'UTF-8') ?>.</li>
<?php endforeach; ?>
            </ul>
<?php else: ?>
            <p class="pd-note"><b>Aperçu</b>, dessiné sur le plan :</p>
            <ul class="rb-cotes">
<?php foreach ($repApercu['cotes'] as $cle => $c):
    $vus = [];
    foreach ($c['serie'] as $e) { $vus[(int) $e['id']] ??= $nomEsp($e) . ' ' . ($c['plants'][(int) $e['id']] ?? 0); } ?>
              <li><b><?= htmlspecialchars(Bordures::libelle($cle) . ($c['brise_vent'] ? ' (vent)' : ''), ENT_QUOTES, 'UTF-8') ?></b>
                — <?= htmlspecialchars($nombreFr($c['longueur']) . ' m, ' . $c['compte']['total'] . ' plants : ' . implode(', ', $vus), ENT_QUOTES, 'UTF-8') ?><?php if ($c['sans_plant'] !== []): ?>
                <small class="bl-reserve">Pas de place pour <?= htmlspecialchars(implode(', ', array_map($nomEsp, $c['sans_plant'])), ENT_QUOTES, 'UTF-8') ?>.</small><?php endif; ?></li>
<?php endforeach; ?>
            </ul>
<?php endif; ?>
<?php foreach ($repApercu['notes'] as $m): ?>
            <p class="pd-note"><?= htmlspecialchars($m, ENT_QUOTES, 'UTF-8') ?></p>
<?php endforeach; ?>
<?php if ($repApercu['sans_place'] !== []): ?>
            <p class="pd-note">Sans place sur aucun côté : <?= htmlspecialchars(implode(' ; ', array_map(static fn (array $s): string => $nomEsp($s['espece']) . ' (' . implode(', ', $s['raisons']) . ')', $repApercu['sans_place'])), ENT_QUOTES, 'UTF-8') ?>.</p>
<?php endif; ?>
<?php if ($repApercu['ok']): ?>
            <form method="post" action="conception-parcelles.php?parcelle=<?= (int) $parcelleActive['id'] ?>">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($auth->jeton(), ENT_QUOTES, 'UTF-8') ?>">
              <input type="hidden" name="action" value="repartir_bordures">
              <input type="hidden" name="parcelle_id" value="<?= (int) $parcelleActive['id'] ?>">
              <input type="hidden" name="version_plan" value="<?= htmlspecialchars($versionPlan, ENT_QUOTES, 'UTF-8') ?>">
<?php foreach ($repChoix as $id): ?>
              <input type="hidden" name="choix[]" value="<?= $id ?>">
<?php endforeach; ?>
<?php foreach ($repColonnes as $cadre => $id): if ($id === null) { continue; } ?>
              <input type="hidden" name="colonne_<?= $cadre ?>" value="<?= $id ?>">
<?php endforeach; ?>
              <button type="submit" class="bl-bouton">Poser ces bordures</button>
            </form>
<?php endif; ?>
          </div>
<?php endif; ?>
          <form method="get" action="conception-parcelles.php" class="rb-form">
            <input type="hidden" name="parcelle" value="<?= (int) $parcelleActive['id'] ?>">
            <input type="hidden" name="repartir" value="1">
            <input type="hidden" name="apercu" value="1">
            <ul class="bl-choix">
<?php foreach ($repEligibles as $e): ?>
              <li><label><input type="checkbox" class="case" name="choix[]" value="<?= (int) $e['id'] ?>"<?= in_array((int) $e['id'], $repChoix, true) ? ' checked' : '' ?>>
                <span><b><?= htmlspecialchars($nomEsp($e), ENT_QUOTES, 'UTF-8') ?></b> — <?= htmlspecialchars($fonctionsEsp($e), ENT_QUOTES, 'UTF-8') ?> ;
                  <?= htmlspecialchars($strateEsp($e) . ' ; ' . $nombreFr((float) $e['espacement_retenu_m']) . ' m', ENT_QUOTES, 'UTF-8') ?></span></label></li>
<?php endforeach; ?>
<?php foreach ($repGrisees as $g): ?>
              <li class="rb-grisee"><label><input type="checkbox" class="case" disabled>
                <span><b><?= htmlspecialchars($nomEsp($g['espece']), ENT_QUOTES, 'UTF-8') ?></b> — <?= htmlspecialchars($g['raison'], ENT_QUOTES, 'UTF-8') ?></span></label></li>
<?php endforeach; ?>
            </ul>
<?php foreach (RepartitionBordures::CADRES as $cadre): ?>
            <div class="pd-ligne rb-colonne">
              <span class="pd-ligne-nom">Colonne vertébrale — <?= $cadre ?></span>
              <select name="colonne_<?= $cadre ?>" aria-label="Colonne vertébrale de la bordure <?= $cadre ?> : une plante sur deux">
                <option value="">aucune</option>
<?php foreach ($repEligibles as $e): if (!Bordures::convient($cadre, $e)) { continue; } ?>
                <option value="<?= (int) $e['id'] ?>"<?= $repColonnes[$cadre] === (int) $e['id'] ? ' selected' : '' ?>><?= htmlspecialchars($nomEsp($e), ENT_QUOTES, 'UTF-8') ?></option>
<?php endforeach; ?>
              </select>
            </div>
<?php endforeach; ?>
            <p class="pd-note">La colonne vertébrale revient un plant sur deux ; cochez-la aussi dans la liste.</p>
            <div class="bl-ajout">
              <button type="submit" class="bl-bouton">Voir l'aperçu</button>
              <a class="bl-retirer" href="conception-parcelles.php?parcelle=<?= (int) $parcelleActive['id'] ?>">Fermer</a>
            </div>
          </form>
<?php endif; ?>
        </div>
<?php endif; ?>
<?php foreach (array_diff_key($erreursLigne, $lignesBordures) as $message): ?>
        <p class="bl-alerte" role="alert"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
<?php endforeach; ?>
<?php if ($lignesBordures === []): ?>
        <p class="pd-note">Aucune bordure sur le plan enregistré : les lignes s'appuient sur lui. Cochez les côtés
           voulus, donnez-leur une largeur, enregistrez le plan — leurs lignes apparaîtront ici.</p>
<?php endif; ?>
<?php foreach ($lignesBordures as $cle => $lb):
    [$cadre, $cote] = Bordures::depuisCle($cle);
    $geo = $lb['geometrie'];
    $compte = $lb['compte'];
    $resume = $lb['places'] === [] ? 'vide'
        : ($geo === null ? ($cadre === 'liane' ? 'pas à cette position, ou hors tortue liane — la ligne est gardée' : 'côté inactif — la ligne est gardée')
        : ($compte['ok'] ? $compte['total'] . ' plant' . ($compte['total'] > 1 ? 's' : '') : 'pas de compte'));
    $convenantes = array_values(array_filter($especesSite, static fn (array $e): bool => Bordures::convient($cadre, $e)));
    $autres = array_values(array_filter($especesSite, static fn (array $e): bool => !Bordures::convient($cadre, $e)));
    $etiquette = static function (array $e) use ($nombreFr): string {
        $nom = (string) ($e['nom_usuel'] ?: $e['nom_scientifique']) . ((string) $e['variete'] !== '' ? ' (' . $e['variete'] . ')' : '');
        return $nom . ' — ' . ($e['espacement_retenu_m'] !== null ? $nombreFr((float) $e['espacement_retenu_m']) . ' m' : 'sans espacement');
    }; ?>
        <details class="bl-cote" id="bl-<?= htmlspecialchars(str_replace(':', '-', $cle), ENT_QUOTES, 'UTF-8') ?>"<?= $bordureOuverte === $cle ? ' open' : '' ?>>
          <summary><?= htmlspecialchars(ucfirst(Bordures::CADRES[$cadre]['libelle']) . ' ' . ($cadre === 'liane' ? Planches::POSITIONS_LIANE[$cote] : $cote), ENT_QUOTES, 'UTF-8') ?>
            <small>— <?= $geo !== null ? htmlspecialchars($nombreFr($geo['longueur']) . ' m · ', ENT_QUOTES, 'UTF-8') : '' ?><?= htmlspecialchars($resume, ENT_QUOTES, 'UTF-8') ?></small></summary>
          <p class="pd-note">Pour <?= htmlspecialchars(Bordures::CADRES[$cadre]['role'], ENT_QUOTES, 'UTF-8') ?>.</p>
<?php if ($lb['places'] !== []): ?>
          <ol class="bl-serie">
<?php foreach ($lb['places'] as $k => $pl): ?>
            <li><span class="bl-pastille" style="background:<?= $couleursPlants[$couleurDe($pl)] ?>"></span><b class="bl-sigle"><?= htmlspecialchars($especeDe($pl)['sigle'], ENT_QUOTES, 'UTF-8') ?></b>
              <span><?= htmlspecialchars($pl['nom'] . ($pl['variete'] !== '' ? ' (' . $pl['variete'] . ')' : ''), ENT_QUOTES, 'UTF-8') ?></span>
              <span class="bl-nombre"><?php
                if (!$pl['connue']) { echo 'inconnue du référentiel'; }
                elseif ($pl['espacement'] === null) { echo 'sans espacement'; }
                elseif ($compte !== null && $compte['ok']) { echo (int) $compte['comptes'][$k], ' · ', htmlspecialchars($nombreFr($pl['espacement']), ENT_QUOTES, 'UTF-8'), ' m'; }
                else { echo htmlspecialchars($nombreFr($pl['espacement']), ENT_QUOTES, 'UTF-8'), ' m'; } ?></span>
<?php if ($peutEcrire): ?>
              <button type="submit" form="bl-form-<?= htmlspecialchars(str_replace(':', '-', $cle), ENT_QUOTES, 'UTF-8') ?>" name="geste" value="retirer:<?= $k ?>" class="bl-retirer" aria-label="Retirer <?= htmlspecialchars($pl['nom'], ENT_QUOTES, 'UTF-8') ?> de la série">✕</button>
<?php endif; ?>
            </li>
<?php endforeach; ?>
          </ol>
<?php if ($compte !== null && !$compte['ok'] && $compte['manquants'] !== []): ?>
          <p class="bl-alerte">Pas de compte : <?= htmlspecialchars(implode(', ', array_map(static fn (int $k): string => $lb['places'][$k]['nom'], $compte['manquants'])), ENT_QUOTES, 'UTF-8') ?>
            n'<?= count($compte['manquants']) > 1 ? 'ont' : 'a' ?> pas d'espacement — renseignez-le dans <a href="especes.php">Espèces</a>.</p>
<?php elseif ($compte !== null && $compte['ok'] && $compte['total'] === 0): ?>
          <p class="bl-alerte">La ligne est plus courte qu'un espacement : aucun plant n'y tient.</p>
<?php endif; ?>
<?php endif; ?>
<?php if ($peutEcrire && $geo !== null && $bordureProposee === $cle):
    $proposition = Bordures::proposer($cadre, $cote, $especesSite);
    $comptables = array_values(array_filter($proposition, static fn (array $pr): bool => $pr['espece']['espacement_retenu_m'] !== null));
    $apercu = $comptables !== [] ? Bordures::compter($geo['longueur'], array_map(static fn (array $pr): float => (float) $pr['espece']['espacement_retenu_m'], $comptables)) : null;
    $formProp = 'bl-prop-' . str_replace(':', '-', $cle); ?>
          <div class="bl-proposition">
            <p class="pd-titre" style="margin-top:.4rem">Ce que l'atelier propose</p>
<?php if ($proposition === [] && $cadre === 'liane'): ?>
            <p class="pd-note">Aucune liane vivace ou ligneuse dans votre référentiel. Dans <a href="especes.php">Espèces</a>,
              cochez la strate « Liane / grimpante » des lianes que vous voulez y voir.</p>
<?php elseif ($proposition === []): ?>
            <p class="pd-note">Aucune espèce de votre référentiel ne porte la fonction de cette bordure
              (<?= htmlspecialchars(Bordures::CADRES[$cadre]['role'], ENT_QUOTES, 'UTF-8') ?>), hors annuelles. Ajoutez-la
              dans <a href="especes.php">Espèces</a> : le rôle, ou la case « bloque l'herbe ».</p>
<?php else: ?>
<?php if ($cadre === 'liane'):
    $annuelles = Bordures::lianesAnnuelles($especesSite); ?>
            <p class="pd-note">Les lianes vivaces ou ligneuses de votre référentiel (strate « Liane / grimpante »), les
              émergentes d'abord : seules celles de contrées ensoleillées supportent le plein soleil en haut de la structure
              (Théry, ch. 15).<?= $annuelles !== [] ? ' La première année, doublez-les de lianes annuelles le temps qu’elles s’installent : '
              . htmlspecialchars(implode(', ', array_map(static fn (array $e): string => (string) ($e['nom_usuel'] ?: $e['nom_scientifique']), $annuelles)), ENT_QUOTES, 'UTF-8') . '.' : '' ?></p>
<?php endif; ?>
            <p class="pd-note"><?= $cadre === 'liane' ? '' : 'Toutes les espèces vivaces ou ligneuses de votre référentiel qui savent
              ' . htmlspecialchars(Bordures::CADRES[$cadre]['role'], ENT_QUOTES, 'UTF-8') . ' — les annuelles n\'y sont pas, une
              bordure est pérenne. ' ?>Gardez celles que vous voulez : elles alterneront dans cet ordre. Celles qui n'ont pas
              d'espacement ne sont pas cochées, faute de pouvoir les compter.<?= $apercu !== null && $apercu['ok'] ? ' Les '
              . count($comptables) . ' cochées donneraient ' . $apercu['total'] . ' plants sur ' . htmlspecialchars($nombreFr($geo['longueur']), ENT_QUOTES, 'UTF-8') . ' m.' : '' ?></p>
            <form method="post" id="<?= $formProp ?>">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($auth->jeton(), ENT_QUOTES, 'UTF-8') ?>">
              <input type="hidden" name="action" value="ligne_bordure">
              <input type="hidden" name="parcelle_id" value="<?= (int) $parcelleActive['id'] ?>">
              <input type="hidden" name="emplacement" value="<?= htmlspecialchars($cle, ENT_QUOTES, 'UTF-8') ?>">
              <input type="hidden" name="version_ligne" value="<?= htmlspecialchars($lb['version'], ENT_QUOTES, 'UTF-8') ?>">
              <ul class="bl-choix">
<?php foreach ($proposition as $pr): $e = $pr['espece']; ?>
                <li><label><input type="checkbox" class="case" name="choix[]" value="<?= (int) $e['id'] ?>"<?= $e['espacement_retenu_m'] !== null ? ' checked' : '' ?>>
                  <span><b><?= htmlspecialchars((string) ($e['nom_usuel'] ?: $e['nom_scientifique']) . ((string) $e['variete'] !== '' ? ' (' . $e['variete'] . ')' : ''), ENT_QUOTES, 'UTF-8') ?></b>
                    — <?= htmlspecialchars(implode(', ', array_map(static fn (string $f): string => mb_strtolower(\Syntropia\Scenario::FONCTIONS[$f] ?? $f), $pr['fonctions'])), ENT_QUOTES, 'UTF-8') ?> ;
                    <?= $e['espacement_retenu_m'] !== null ? htmlspecialchars($nombreFr((float) $e['espacement_retenu_m']) . ' m', ENT_QUOTES, 'UTF-8') : 'sans espacement' ?><?php if ($pr['reserve'] !== null): ?>
                    <small class="bl-reserve"><?= htmlspecialchars($pr['reserve'], ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?></span></label></li>
<?php endforeach; ?>
              </ul>
              <div class="bl-ajout">
                <button type="submit" name="geste" value="poser" class="bl-bouton"><?= $lb['places'] === [] ? 'Poser cette série' : 'Remplacer la ligne par cette série' ?></button>
                <a class="bl-retirer" href="conception-parcelles.php?parcelle=<?= (int) $parcelleActive['id'] ?>&amp;bordure=<?= rawurlencode($cle) ?>">Annuler</a>
              </div>
            </form>
<?php endif; ?>
          </div>
<?php elseif ($peutEcrire && $geo !== null): ?>
          <a class="bl-proposer" href="conception-parcelles.php?parcelle=<?= (int) $parcelleActive['id'] ?>&amp;bordure=<?= rawurlencode($cle) ?>&amp;proposer=1"><?= $cadre === 'liane' ? 'Proposer les lianes' : 'Proposer les espèces de cette bordure' ?></a>
<?php endif; ?>
<?php if (isset($erreursLigne[$cle])): ?>
          <p class="bl-alerte" role="alert"><?= htmlspecialchars($erreursLigne[$cle], ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
<?php if ($peutEcrire): ?>
          <form method="post" id="bl-form-<?= htmlspecialchars(str_replace(':', '-', $cle), ENT_QUOTES, 'UTF-8') ?>" class="bl-form">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($auth->jeton(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="ligne_bordure">
            <input type="hidden" name="parcelle_id" value="<?= (int) $parcelleActive['id'] ?>">
            <input type="hidden" name="emplacement" value="<?= htmlspecialchars($cle, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="version_ligne" value="<?= htmlspecialchars($lb['version'], ENT_QUOTES, 'UTF-8') ?>">
<?php foreach ($lb['places'] as $pl): ?>
            <input type="hidden" name="serie[]" value="<?= (int) ($pl['id'] ?? 0) ?>">
<?php endforeach; ?>
<?php if ($lb['places'] === [] || $geo !== null): ?>
            <div class="bl-ajout">
              <select name="ajout" aria-label="Espèce à ajouter au bout de la série">
                <option value="">Ajouter une espèce…</option>
<?php foreach ([$cadre === 'liane' ? 'Les lianes' : 'Pour cette bordure' => $convenantes, 'Les autres espèces' => $autres] as $groupe => $liste): if ($liste === []) { continue; } ?>
                <optgroup label="<?= htmlspecialchars($groupe, ENT_QUOTES, 'UTF-8') ?>">
<?php foreach ($liste as $e): ?>
                  <option value="<?= (int) $e['id'] ?>"><?= htmlspecialchars($etiquette($e), ENT_QUOTES, 'UTF-8') ?></option>
<?php endforeach; ?>
                </optgroup>
<?php endforeach; ?>
              </select>
              <button type="submit" name="geste" value="ajouter" class="bl-bouton">Ajouter</button>
            </div>
<?php endif; ?>
<?php if ($lb['places'] !== []): ?>
            <button type="submit" name="geste" value="vider" class="bl-retirer" style="margin-top:.3rem">Vider la ligne</button>
<?php endif; ?>
          </form>
<?php endif; ?>
<?php if ($lb['places'] !== []):
    $dc = $calendrierBordures['dates'][$cle] ?? null;
    $pp = $calendrierBordures['perturbations'][$cle] ?? [];
    $idsSerie = array_filter(array_column($lb['places'], 'id'));
    $repere = $dc === null ? CalendrierLignes::repere(array_map(static fn (int $i): ?array => (new Especes())->parId($i), $idsSerie), new \DateTimeImmutable('today')) : null; ?>
          <div class="bl-calendrier">
            <p class="pd-titre" style="margin-top:.5rem">Le calendrier prévu</p>
            <p class="pd-note">Des prévisions : la date réelle se donnera à la Réalisation, et le jour d'une perturbation
              s'adaptera au temps qu'il fait — le journal gardera ce qui a été fait.</p>
<?php if ($peutEcrire): ?>
            <form method="post" class="bl-ajout">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($auth->jeton(), ENT_QUOTES, 'UTF-8') ?>">
              <input type="hidden" name="action" value="bordure_date">
              <input type="hidden" name="parcelle_id" value="<?= (int) $parcelleActive['id'] ?>">
              <input type="hidden" name="emplacement" value="<?= htmlspecialchars($cle, ENT_QUOTES, 'UTF-8') ?>">
              <input type="hidden" name="version_dates" value="<?= htmlspecialchars(CalendrierLignes::version($dc), ENT_QUOTES, 'UTF-8') ?>">
              <label class="bl-date">Plantation prévue
                <input type="date" name="mise_en_place_prevue" value="<?= htmlspecialchars((string) ($dc['mise_en_place_prevue'] ?? $repere['mise'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></label>
              <button type="submit" class="bl-bouton">Noter</button>
            </form>
<?php if ($repere !== null): ?>
            <p class="pd-note">Date proposée d'après le calendrier de <?= htmlspecialchars($repere['espece'], ENT_QUOTES, 'UTF-8') ?> dans Espèces — rien n'est noté avant « Noter ».</p>
<?php endif; ?>
<?php else: ?>
            <p class="pd-note">Plantation prévue : <?= $dc !== null && $dc['mise_en_place_prevue'] !== null ? htmlspecialchars((string) $dc['mise_en_place_prevue'], ENT_QUOTES, 'UTF-8') : 'pas encore notée' ?>.</p>
<?php endif; ?>
<?php if ($pp !== []): ?>
            <ol class="bl-serie">
<?php foreach ($pp as $x): ?>
              <li><span><?= htmlspecialchars((new \DateTimeImmutable((string) $x['date_prevue']))->format('d/m/Y'), ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars(CalendrierLignes::libelleGeste((string) $x['geste']), ENT_QUOTES, 'UTF-8') ?></span>
<?php if ($peutEcrire): ?>
                <form method="post" style="margin-left:auto">
                  <input type="hidden" name="csrf" value="<?= htmlspecialchars($auth->jeton(), ENT_QUOTES, 'UTF-8') ?>">
                  <input type="hidden" name="action" value="perturbation_retirer">
                  <input type="hidden" name="parcelle_id" value="<?= (int) $parcelleActive['id'] ?>">
                  <input type="hidden" name="emplacement" value="<?= htmlspecialchars($cle, ENT_QUOTES, 'UTF-8') ?>">
                  <input type="hidden" name="perturbation_id" value="<?= (int) $x['id'] ?>">
                  <button type="submit" class="bl-retirer" aria-label="Retirer cette perturbation prévue">✕</button>
                </form>
<?php endif; ?></li>
<?php endforeach; ?>
            </ol>
<?php endif; ?>
<?php if ($peutEcrire): ?>
            <form method="post" class="bl-ajout">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($auth->jeton(), ENT_QUOTES, 'UTF-8') ?>">
              <input type="hidden" name="action" value="perturbation_ajouter">
              <input type="hidden" name="parcelle_id" value="<?= (int) $parcelleActive['id'] ?>">
              <input type="hidden" name="emplacement" value="<?= htmlspecialchars($cle, ENT_QUOTES, 'UTF-8') ?>">
              <input type="date" name="date_prevue" aria-label="Date prévue de la perturbation">
              <select name="geste" aria-label="Geste prévu">
<?php foreach (CalendrierLignes::GESTES as $g): ?>
                <option value="<?= $g ?>"><?= htmlspecialchars(CalendrierLignes::libelleGeste($g), ENT_QUOTES, 'UTF-8') ?></option>
<?php endforeach; ?>
              </select>
              <button type="submit" class="bl-bouton">Prévoir</button>
            </form>
            <p class="pd-note">Repères de Théry (ch. 12) : la première coupe en vert en juin, les suivantes quand il y a de quoi
              couper ; les tailles de ligneux en hiver, hors gel — à recaler sur l'état des bourgeons de votre lieu.</p>
<?php endif; ?>
          </div>
<?php endif; ?>
        </details>
<?php endforeach; ?>

        <p class="pd-titre">Ce que la géométrie donne</p>
        <dl class="pd-resultat" id="resultat">
          <div class="pd-champ"><dt>Surface utile</dt><dd id="r-surface">—</dd></div>
          <div class="pd-champ"><dt>Surface des planches</dt><dd id="r-surface-planches">—</dd></div>
          <div class="pd-champ"><dt>Planches</dt><dd id="r-planches">—</dd></div>
          <div class="pd-champ"><dt>Planche de bord</dt><dd id="r-planche-bord">—</dd></div>
          <div class="pd-champ"><dt>Planche intérieure</dt><dd id="r-planche-interieure">—</dd></div>
          <div class="pd-champ"><dt>Passe-pied</dt><dd id="r-passe-pied">—</dd></div>
          <div class="pd-champ"><dt>Linéaire de bordure</dt><dd id="r-lineaire">—</dd></div>
        </dl>
        <p class="pd-note">La planche de bord (la première et la dernière de chaque rang) touche
           la bordure plutôt qu'un passe-pied : elle ne se travaille que d'un seul côté, d'où sa
           largeur plus étroite — voir chantier-plan-de-parcelle.md § Phase 3.</p>
        <p class="pd-note">« Surface utile » compte tout ce que les bordures laissent, passe-pieds
           compris ; « Surface des planches » ne compte que ce qui se cultive vraiment.</p>
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
<?php if ($demandeNouvelle): ?>
      <p>Le code et le nom suffisent pour ouvrir une parcelle — tout le reste (forme,
         dimensions, sol) se précise ensuite, ici ou dans les étapes suivantes.
         <strong>Pérenne</strong> : vergers, haies, système porté par des arbres.
         <strong>Potager</strong> : cultures de légumes, annuelles.</p>
<?php else: ?>
      <p>Vous allez créer la ou les parcelles du site, en fixant leur forme et leur place.
         Ici : les dimensions de la parcelle <code><?= htmlspecialchars((string) $parcelleActive['code'], ENT_QUOTES, 'UTF-8') ?></code>,
         où courent ses bordures, et la largeur voulue pour les planches. Le nombre de
         planches se calcule seul — et avec lui les linéaires, donc le nombre de plants
         et de graines. Prévoyez un mètre ruban et dix minutes sur place.</p>
      <p><strong>Sélection</strong> — cliquez une bordure du dessin pour la choisir.
         <strong>Déplacer un bord</strong> — tirez le bord d'une bordure sur le dessin
         pour changer sa largeur ; le tableau « Ce que la géométrie donne » se met à
         jour au relâchement. <strong>Zoom</strong> — la molette, ou « − » et « + » en bas du plan ;
         agrandi, le plan se fait glisser.</p>
      <p><strong>Annuler les changements</strong> (en bas) ramène le plan tel qu'il est enregistré ;
         <strong>Réinitialiser</strong> le fait repartir des valeurs par défaut de l'atelier (12 × 8 m…). Les deux
         demandent confirmation, et aucun n'écrit rien : seul « Enregistrer le plan » écrit.</p>
      <p><strong>Les plantes des bordures</strong> — une fois le plan enregistré, chaque côté actif reçoit une
         ligne de plants : choisissez une espèce, ou plusieurs qui alternent (la diversité est le principe). La
         bordure externe bloque l'herbe et freine le vent, l'interne apporte de la biomasse : les espèces qui le
         font viennent en tête de la liste. « Proposer les espèces de cette bordure » les montre toutes (hors annuelles :
         une bordure est pérenne) ; vous gardez celles que vous voulez. L'atelier compte les plants de chaque espèce
         d'après leur espacement (<a href="especes.php">Espèces</a>) et les dessine sur le plan, chaque espèce avec sa
         couleur et son sigle : la <strong>légende</strong> les liste, les sigles s'inscrivent sur le plan quand le zoom
         le permet, et le survol d'un point donne le nom de la plante. Dans la légende, « Sigles sur le plan » les
         montre ou les retire tous, la pastille d'une espèce les siens ; « Afficher la légende », au bas de la page, la
         retire.</p>
      <p><strong>Répartir une liste de plantes</strong> — sur des bordures encore vides, cochez une seule liste pour
         toute la parcelle : l'atelier pose chaque plante sur les côtés dont elle remplit la fonction, en alternance,
         des émergentes seules au sud et à l'ouest de l'externe, des brise-vent sur les côtés cochés « vent », et la
         colonne vertébrale choisie un plant sur deux. Un côté ne garde que les plantes qu'il peut porter ; s'il en
         reste moins de deux, rien n'est réparti et l'atelier dit pourquoi. Vous voyez l'aperçu sur le plan avant de
         poser, puis vous retouchez chaque côté à la main si vous le voulez.</p>
<?php endif; ?>
      <p>Connecté en tant que <?= htmlspecialchars($nomAffiche, ENT_QUOTES, 'UTF-8') ?>. <a href="deconnexion.php">Se déconnecter</a>.</p>
    </div>
  </aside>

  <footer class="pied">
    <form class="pied-recherche" onsubmit="return false;">
      <input type="search" placeholder="Rechercher dans le site…" aria-label="Rechercher dans le site">
      <button type="submit" aria-label="Rechercher">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="10.5" cy="10.5" r="6.5"/><line x1="15.5" y1="15.5" x2="21" y2="21"/></svg>
      </button>
    </form>
<?php if (!$demandeNouvelle): ?>
    <label class="pied-option"><input type="checkbox" id="afficher-cotes" class="case" checked> Afficher les côtes</label>
<?php if ($legende !== []): ?>
    <label class="pied-option"><input type="checkbox" id="afficher-legende" class="case" checked> Afficher la légende</label>
<?php endif; ?>
<?php endif; ?>
    <span class="pied-spacer"></span>
<?php if (!$demandeNouvelle): ?>
    <button class="pied-annuler" id="pied-annuler" aria-label="Annuler les changements" title="Revenir au plan enregistré" disabled>
      <span class="pied-annuler-texte">Annuler les changements</span>
      <span class="pied-annuler-icone" aria-hidden="true">↶</span>
    </button>
    <button class="pied-reset" id="pied-reset" aria-label="Réinitialiser" title="Revenir aux valeurs par défaut de l'atelier">
      <span class="pied-reset-texte">Réinitialiser</span>
      <span class="pied-reset-icone" aria-hidden="true">R</span>
    </button>
<?php endif; ?>
  </footer>

</div>

<script src="assets/js/etapes.js?v=<?= (int) @filemtime(__DIR__ . '/assets/js/etapes.js') ?>"></script>
<script src="assets/js/gabarit.js?v=<?= (int) @filemtime(__DIR__ . '/assets/js/gabarit.js') ?>"></script>
<script>
  Gabarit.init({
    site: <?= json_encode((string) $site['nom'], JSON_UNESCAPED_UNICODE) ?>,
    etape: 'conception-parcelles',
    contexte: <?= $demandeNouvelle ? json_encode('nouvelle parcelle', JSON_UNESCAPED_UNICODE)
      : json_encode($parcelleActive['code'] . ' — ' . $parcelleActive['nom'], JSON_UNESCAPED_UNICODE) ?>
  });
</script>
<?php if (!$demandeNouvelle): ?>
<script>
  // Largeur de planche et passe-pied : une seule source, Parcelles::PLANCHE_M/
  // PASSE_PIED_M — sans quoi le calcul serveur de nb_planches et l'aperçu du
  // plan à l'écran pourraient un jour diverger. Voir chantier-plan-de-
  // parcelle.md § Phase 2.
  var PLAN_CONSTANTES = { planche: <?= Parcelles::PLANCHE_M ?>, passePied: <?= Parcelles::PASSE_PIED_M ?>,
    bordPlanche: <?= Parcelles::BORD_PLANCHE_M ?>,
    // Les lignes de chaque planche, telles qu'enregistrées (chantier « peuplement de la parcelle », Phase 4) — le
    // défaut et les bornes viennent de Planches, seule source.
    lignes: <?= json_encode((object) (is_array($planEnregistre['lignes'] ?? null) ? $planEnregistre['lignes'] : [])) ?>,
    // La nature des planches (zèbre, abeille — Phase 5) : la première, puis l'alternance ; les planches changées.
    premiereNature: <?= json_encode(Planches::premiereNature($planEnregistre ?? null)) ?>,
    natures: <?= json_encode((object) (is_array($planEnregistre['natures']['planches'] ?? null) ? $planEnregistre['natures']['planches'] : [])) ?>,    lignesDefaut: <?= Planches::LIGNES_DEFAUT ?>, lignesMin: <?= Planches::LIGNES_MIN ?>, lignesMax: <?= Planches::LIGNES_MAX ?> };
  // Les plants des bordures, sur le plan enregistré : [x, y, couleur, largeur de bande, envergure, espacement] en mètres (chantier
  // « peuplement de la parcelle », Phase 2). Et la bordure à rouvrir après un geste.
  var PLANTS_BORDURES = <?= json_encode(['plants' => $plantsDessin, 'couleurs' => $couleursPlants,
      'especes' => array_values(array_map(static fn (array $e): array => ['nom' => $e['nom'] . ($e['variete'] !== '' ? ' (' . $e['variete'] . ')' : ''),
          'sigle' => $e['sigle'], 'couleur' => $e['couleur']], $especesDessin)),
      'cotes' => $cotesDessin,
      'apercu' => $repApercu !== null && $repApercu['ok'],
      'ouvrir' => $repOuverte ? 'rb' : ($bordureOuverte === '' ? null : ($bordureOuverte === 'recompte' ? 'bl-titre' : 'bl-' . str_replace(':', '-', $bordureOuverte)))], JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="conception-parcelles.js?v=<?= (int) @filemtime(__DIR__ . '/conception-parcelles.js') ?>"></script>
<?php endif; ?>
</body>
</html>
