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
 * peut porter plusieurs cultures. Le principe des associations de plantes est de permettre un meilleur
 * développement des plantes regroupées ensemble (le porteur, 2026-10-03). Chaque planche dit ce que les tables de
 * Weinrich savent de ses voisinages (`Associations::entre()`) : ce qui s'entend bien, ce qu'il vaut mieux éviter de
 * placer côte à côte. **Une information de conception, jamais une interdiction** — des ordres de conduite, non des
 * preuves ; rien n'est refusé.
 */

require __DIR__ . '/../src/autoload.php';
require __DIR__ . '/../src/Configuration.php';
require __DIR__ . '/../src/Database.php';

use Syntropia\Espacement;
use Syntropia\Associations;
use Syntropia\CalendrierLopez;
use Syntropia\Configuration;
use Syntropia\LignesDeService;
use Syntropia\PlanDeCulture;
use Syntropia\Planches;
use Syntropia\Role;
use Syntropia\Scenario;
use Syntropia\Strates;
use Syntropia\Succession;
use Syntropia\Web\Authentification;
use Syntropia\Web\CalendrierLignes;
use Syntropia\Web\Cultures;
use Syntropia\Web\Especes;
use Syntropia\Web\LignesPlantation;
use Syntropia\Web\Occupation;
use Syntropia\Web\Parcelles;
use Syntropia\Web\PartBiomasse;
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
$especesService = new Especes();

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
// La nature des planches — tortue zèbre (planches pérennes et annuelles) ou abeille (une bande de chaque sur chaque
// planche) : chantier « peuplement de la parcelle », Phase 5. Vide pour une autre tortue.
$natures = $parcelle !== null ? Planches::natures($parcelle) : [];
$abeille = ($parcelle['tortue'] ?? null) === 'abeille' && $natures !== [];
// La nature d'une culture se lit dans le cycle de son espèce au référentiel ; inconnue, elle ne contraint rien.
$naturesCultures = [];
$natureCulture = static function (string $espece) use (&$naturesCultures, $especesService, $siteId): ?string {
    $cle = mb_strtolower(trim($espece));
    if (!array_key_exists($cle, $naturesCultures)) {
        $naturesCultures[$cle] = Planches::natureDuCycle($especesService->parNomUsage($espece, $siteId)['cycle'] ?? null);
    }
    return $naturesCultures[$cle];
};
$libelleNature = static fn (?string $n): string => $n === 'perenne' ? 'pérenne' : 'annuelle';
// Les lignes de service de chaque planche (chantier « peuplement de la parcelle », Phase 6) : la planche dont on ouvre les
// lignes dans le panneau Données, et ce qui est posé.
$lignesService = new LignesPlantation();
$calendrierService = new CalendrierLignes();
$planchesLignes = (int) ($_POST['numero_planche'] ?? $_GET['lignes'] ?? 0);
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
        $erreurs['jeton'] = 'Le formulaire a expiré. Rechargez la page.';
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
    } elseif ($action === 'appliquer') {
        // Une proposition ne s'applique que sur l'état qu'on a vu : on la recalcule, et on ne pose que ce qu'elle contient encore.
        $etat = array_map(static fn (array $c): array => ['id' => (int) $c['id'], 'espece' => (string) $c['espece'], 'planche' => $c['planche'] === null ? null : (int) $c['planche'],
            'nature' => $natureCulture((string) $c['espece'])], $culturesService->dUneParcelle($idP));
        if (!hash_equals(PlanDeCulture::empreinte($etat), (string) ($_POST['empreinte'] ?? ''))) {
            $erreurs['proposition'] = 'Le plan a changé depuis cette proposition : recalculez-la avant de l’accepter.';
        } else {
            $proposee = [];
            $bandes = [];
            foreach (PlanDeCulture::proposer($etat, $nbPlanches, null, $natures)['placements'] as $x) {
                $proposee[$x['id']] = $x['planche'];
                if ($x['bande'] !== null) {
                    $bandes[$x['id']] = $x['bande'];
                }
            }
            $choisies = array_map('intval', is_array($_POST['ids'] ?? null) ? $_POST['ids'] : []);
            $n = $culturesService->placer($idP, $nbPlanches, array_intersect_key($proposee, array_flip($choisies)), $bandes);
            $_SESSION['syntropia_flash_potager'] = ['ok', $n === 0 ? 'Aucune culture n’a été placée.' : ($n === 1 ? 'Une culture est placée.' : $n . ' cultures sont placées.')];
            header('Location: ' . $retour);
            exit;
        }
    } elseif ($action === 'lignes_planche') {
        $choix = [];
        foreach (is_array($_POST['choix'] ?? null) ? $_POST['choix'] : [] as $k => $id) {
            $choix[(int) $k] = (int) $id;
        }
        $r = $lignesService->enregistrerPlanche($parcelle, $siteId, $planchesLignes, $choix, (string) ($_POST['version_lignes'] ?? ''));
        // Puis le calendrier prévisionnel de chaque ligne (Phase 2 bis du « film de la parcelle ») ; une ligne laissée vide
        // perd ses dates.
        if ($r['ok']) {
            foreach ($choix as $k => $id) {
                $mise = $id === 0 ? '' : (string) ($_POST['mise'][$k] ?? '');
                $fin = $id === 0 ? '' : (string) ($_POST['fin'][$k] ?? '');
                $rd = $calendrierService->enregistrerDates($idP, LignesPlantation::clePlanche($planchesLignes, $k), $mise, $fin,
                    (string) ($_POST['version_dates'][$k] ?? ''));
                if (!$rd['ok']) {
                    $r = ['ok' => false, 'erreurs' => array_map(static fn (string $e): string => 'Ligne ' . $k . ' : ' . $e, $rd['erreurs'])];
                    break;
                }
            }
        }
        if ($r['ok']) {
            $_SESSION['syntropia_flash_potager'] = ['ok', 'Les lignes de la planche ' . $planchesLignes . ' sont notées.'];
            header('Location: ' . $retour . '&lignes=' . $planchesLignes);
            exit;
        }
        $erreurs += $r['erreurs'];
    } elseif (in_array($action, ['perturbation_ajouter', 'perturbation_retirer'], true)) {
        // Les perturbations prévues d'une ligne de service (le maïs, le tournesol de la ligne centrale…) — une liste de
        // dates, chacune avec son geste (chantier « film de la parcelle », Phase 2 bis).
        $k = (int) ($_POST['ligne'] ?? 0);
        $n = \Syntropia\Planches::nombreDeLignes(json_decode((string) ($parcelle['plan'] ?? ''), true), $planchesLignes);
        if ($planchesLignes < 1 || $planchesLignes > $nbPlanches || $k < 1 || $k > $n) {
            $erreurs['ligne'] = 'Cette ligne n’existe pas sur la planche.';
        } else {
            $emplacement = LignesPlantation::clePlanche($planchesLignes, $k);
            if ($action === 'perturbation_ajouter') {
                $r = $calendrierService->ajouterPerturbation($idP, $emplacement, (string) ($_POST['date_prevue'] ?? ''), (string) ($_POST['geste'] ?? ''));
            } else {
                $calendrierService->retirerPerturbation((int) ($_POST['perturbation_id'] ?? 0), $idP);
                $r = ['ok' => true, 'erreurs' => []];
            }
            if ($r['ok']) {
                header('Location: ' . $retour . '&lignes=' . $planchesLignes);
                exit;
            }
            $erreurs += $r['erreurs'];
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
$etatPlanches = $parcelle !== null ? $lignesService->etatPlanches($parcelle, $siteId) : [];
// Le panneau des lignes d'une planche : ce qui est posé, la proposition (strates vides, biomasse au centre) et les choix.
$panneauLignes = null;
if ($parcelle !== null && $peutEcrire && isset($etatPlanches[$planchesLignes])) {
    $ouvre = true;
    $strates = [];
    foreach ($cultures as $c) {
        if ((int) ($c['planche'] ?? 0) !== $planchesLignes) {
            continue;
        }
        $connues = $c['strate'] !== null ? [(string) $c['strate']]
            : array_values(array_intersect(LignesDeService::stratesProduction($especesService->parNomUsage((string) $c['espece'], $siteId) ?? []), LignesDeService::STRATES));
        $strates = array_merge($strates, $connues);
    }
    $etatP = $etatPlanches[$planchesLignes];
    $propLignes = LignesDeService::proposer(count($etatP['lignes']), array_values(array_unique($strates)), $especesService->toutes($siteId), $natures[$planchesLignes] ?? null,
        LignesDeService::liane($parcelle, new \DateTimeImmutable('today')),
        \Syntropia\Planches::demiPlancheDeFin($parcelle, $planchesLignes));
    // Rien de posé sur la planche, ou « Reprendre la proposition » : les choix partent de la proposition.
    $partirDeLaProposition = ($_GET['proposer_lignes'] ?? '') === '1' || array_filter($etatP['lignes']) === [];
    $choixLignes = [];
    foreach ($propLignes as $l) {
        $k = $l['indice'] + 1;
        $pose = $etatP['lignes'][$k] ?? null;
        $choixLignes[$k] = $partirDeLaProposition ? (int) ($l['proposee']['id'] ?? 0) : (int) ($pose['espece']['id'] ?? 0);
    }
    if (isset($_POST['choix']) && is_array($_POST['choix'])) {
        foreach ($_POST['choix'] as $k => $id) {
            $choixLignes[(int) $k] = (int) $id;
        }
    }
    // Les dates prévues de chaque ligne : celles notées, sinon le repère du calendrier de l'espèce choisie (une proposition).
    $datesLignes = [];
    $calP = $calendrierService->dUneParcelle($idP)['dates'];
    $maintenant = new \DateTimeImmutable('today');
    foreach ($choixLignes as $k => $id) {
        $notee = $calP[LignesPlantation::clePlanche($planchesLignes, $k)] ?? null;
        $repere = $id > 0 ? CalendrierLignes::repere([$especesService->parId($id)], $maintenant) : null;
        $datesLignes[$k] = ['version' => CalendrierLignes::version($notee), 'repere' => $repere,
            'mise' => (string) ($_POST['mise'][$k] ?? $notee['mise_en_place_prevue'] ?? $repere['mise'] ?? ''),
            'fin' => (string) ($_POST['fin'][$k] ?? $notee['fin_prevue'] ?? $repere['fin'] ?? ''),
            'propose' => $notee === null && $repere !== null && !isset($_POST['mise'])];
    }
    $panneauLignes = ['numero' => $planchesLignes, 'etat' => $etatP, 'proposition' => $propLignes, 'choix' => $choixLignes, 'dates' => $datesLignes,
        'strates' => array_values(array_unique($strates)), 'depuis_proposition' => $partirDeLaProposition];
}

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

// La proposition de placement : un aperçu, rien n'est écrit tant que la personne n'a pas accepté.
$etatCultures = array_map(static fn (array $c): array => ['id' => (int) $c['id'], 'espece' => (string) $c['espece'], 'planche' => $c['planche'] === null ? null : (int) $c['planche'],
    'nature' => $natureCulture((string) $c['espece'])], $cultures);
$proposition = null;
if ($parcelle !== null && $peutEcrire && $nbPlanches >= 1 && ($_GET['proposer'] ?? '') === '1' && $saisie === null) {
    $proposition = PlanDeCulture::proposer($etatCultures, $nbPlanches, null, $natures);
    $ouvre = true;
}
$proposeesParPlanche = [];
$idsProposes = [];
foreach ($proposition['placements'] ?? [] as $x) {
    $proposeesParPlanche[$x['planche']][] = $x;
    $idsProposes[$x['id']] = true;
}

// Les planches du plan (et celles que des cultures occupent encore hors du plan), puis les cultures non placées.
$parPlanche = [];
$nonPlacees = [];
foreach ($cultures as $c) {
    $c['planche'] === null ? $nonPlacees[] = $c : $parPlanche[(int) $c['planche']][] = $c;
}
$reste = array_values(array_filter($nonPlacees, static fn (array $c): bool => !isset($idsProposes[(int) $c['id']])));
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
    $nat = $natures[$n] ?? null;
    $cartes[] = ['numero' => $n, 'libelle' => 'Planche ' . $n
        . ($nat !== null && $nat['nature'] !== null ? ' · ' . $libelleNature($nat['nature']) : '')
        . ($nat !== null && $nat['bandes'] !== null ? ' · bandes ' . $libelleNature($nat['bandes'][0]) . ' puis ' . $libelleNature($nat['bandes'][1]) : ''),
        'nature' => $nat['nature'] ?? null, 'hors_plan' => in_array($n, $horsPlan, true), 'cultures' => $liste,
        'proposees' => $proposeesParPlanche[$n] ?? [], 'relations' => $relations, 'chaine' => Succession::chaine($liste),
        'sans_fiche' => array_values(array_filter(array_unique($noms), static fn (string $x): bool => Associations::fiche($x) === null && !isset($citees[Associations::normaliser($x)])))];
}
if ($reste !== []) {
    $cartes[] = ['numero' => null, 'libelle' => 'Pas encore placées', 'hors_plan' => false, 'cultures' => $reste, 'proposees' => [], 'relations' => [], 'sans_fiche' => [], 'chaine' => null];
}

// Le formulaire. « fonctions » est une liste (les cases de service), tenue à part du reste, qui se
// reprend champ par champ en texte.
$f = ['espece' => '', 'planche' => '', 'demi_planche' => '', 'strate' => '', 'espacement_m' => '', 'nb_plants' => '', 'date_semis' => '', 'date_semis_repiquage' => '', 'date_recolte' => '', 'notes' => ''];
$fonctionsSaisie = [];
if ($saisie !== null) {
    foreach ($f as $k => $_) {
        $f[$k] = (string) ($saisie[$k] ?? '');
    }
    $fonctionsSaisie = is_array($saisie['fonctions'] ?? null) ? array_filter($saisie['fonctions'], 'is_string') : [];
} elseif ($edition !== null) {
    foreach ($f as $k => $_) {
        $f[$k] = (string) ($edition[$k] ?? '');
    }
    $fonctionsSaisie = Cultures::fonctions($edition);
} elseif (isset($_GET['planche']) && ctype_digit((string) $_GET['planche'])) {
    $f['planche'] = (string) $_GET['planche'];
}
$ficheEditee = $f['espece'] !== '' ? Associations::fiche($f['espece']) : null;
$suiteEditee = $f['espece'] !== '' && Succession::connue($f['espece'])
    ? ['avant' => Succession::avant($f['espece']), 'apres' => Succession::apres($f['espece'])] : null;
$connues = array_map(static fn (array $l): string => (string) $l['nom'], array_values(Associations::table('potager')));
// Les repères de Lopez : dix-sept cultures d'automne et d'hiver, en édition seulement (le nom est alors enregistré).
foreach (CalendrierLopez::table() as $l) {
    $connues[] = $l['culture'];
}
// Les espèces du référentiel (cadre + vos surcharges) : voir/éditer sur la page « Espèces ».
foreach ($especesService->toutes($siteId) as $e) {
    $connues[] = (string) ($e['nom_usuel'] ?: $e['nom_scientifique']);
}
$connues = array_values(array_unique($connues));
$repere = $f['espece'] !== '' && $editionId > 0 ? CalendrierLopez::pour($f['espece']) : null;
$indicatives = $repere !== null ? CalendrierLopez::datesIndicatives($repere, new DateTimeImmutable('today')) : null;
// Le référentiel des espèces — Phase 3 du chantier « caractéristiques des végétaux » : même mécanique que le
// repère de Lopez, généralisée à toute espèce connue (calendrier et rôles de service).
$especeConnue = $f['espece'] !== '' && $editionId > 0 ? $especesService->parNomUsage($f['espece'], $siteId) : null;
$indicativesEspece = $especeConnue !== null ? Especes::datesIndicatives($especeConnue, new DateTimeImmutable('today')) : null;
$fonctionsEspece = $especeConnue !== null ? Scenario::fonctionsDe((string) ($especeConnue['role'] ?? '')) : [];

// Le calendrier : une année, des mois, et pour chaque culture datée ce qui s'y passe (S semis à l'abri, P mise en place, R récolte).
$vue = ($_GET['vue'] ?? '') === 'calendrier' ? 'calendrier' : 'planches';
$datees = array_values(array_filter($cultures, static fn (array $c): bool => $c['date_semis'] !== null || $c['date_semis_repiquage'] !== null || $c['date_recolte'] !== null));
$annee = isset($_GET['annee']) && ctype_digit((string) $_GET['annee']) && (int) $_GET['annee'] >= 2000 && (int) $_GET['annee'] <= 2100 ? (int) $_GET['annee'] : null;
if ($annee === null) {
    $toutes = [];
    foreach ($datees as $c) {
        foreach (['date_semis', 'date_semis_repiquage', 'date_recolte'] as $k) {
            if ($c[$k] !== null) {
                $toutes[] = (string) $c[$k];
            }
        }
    }
    $annee = $toutes !== [] ? (int) substr(min($toutes), 0, 4) : (int) date('Y');
}
$lignesCal = [];
$horsAnnee = 0;
foreach ($datees as $c) {
    $S = $c['date_semis'] !== null ? (string) $c['date_semis'] : null;
    $P = $c['date_semis_repiquage'] !== null ? (string) $c['date_semis_repiquage'] : null;
    $R = $c['date_recolte'] !== null ? (string) $c['date_recolte'] : null;
    $departs = array_filter([$S, $P]);
    $debut = $departs !== [] ? min($departs) : $R;
    $fin = $R ?? max($departs);
    if ((int) substr($fin, 0, 4) < $annee || (int) substr($debut, 0, 4) > $annee) {
        $horsAnnee++;
        continue;
    }
    $mise = $P ?? $S;                       // là où la culture commence d'occuper la planche
    $moisDeb = $mise !== null ? substr($mise, 0, 7) : null;
    $moisFin = $R !== null ? substr($R, 0, 7) : $moisDeb;
    $mois = [];
    for ($m = 1; $m <= 12; $m++) {
        $ym = sprintf('%04d-%02d', $annee, $m);
        $mois[$m] = ['S' => $S !== null && substr($S, 0, 7) === $ym, 'P' => $P !== null && substr($P, 0, 7) === $ym,
            'R' => $R !== null && substr($R, 0, 7) === $ym,
            'occ' => $moisDeb !== null && $ym >= $moisDeb && $ym <= $moisFin];
    }
    $lignesCal[] = ['espece' => (string) $c['espece'], 'planche' => $c['planche'], 'debut' => $debut, 'mois' => $mois,
        'titre' => implode(' · ', array_filter([$S !== null ? 'semis ' . $dateFr($S) : '', $P !== null ? 'en place ' . $dateFr($P) : '', $R !== null ? 'récolte ' . $dateFr($R) : '']))];
}
// Les lignes de service des planches et les bordures ont aussi leur calendrier prévisionnel (chantier « film de la
// parcelle », Phase 2 bis) : P mise en place prévue, F fin prévue, T perturbation prévue — des prévisions.
$calendrierLignes = $parcelle !== null ? $calendrierService->dUneParcelle($idP) : ['dates' => [], 'perturbations' => []];
$nomsBordures = [];
foreach ($parcelle !== null ? $lignesService->etat($parcelle, $siteId) : [] as $cle => $b) {
    $nomsBordures[$cle] = implode(', ', array_column($b['places'], 'nom'));
}
foreach (array_unique(array_merge(array_keys($calendrierLignes['dates']), array_keys($calendrierLignes['perturbations']))) as $cle) {
    $dl = $calendrierLignes['dates'][$cle] ?? null;
    $P = $dl['mise_en_place_prevue'] ?? null;
    $F = $dl['fin_prevue'] ?? null;
    $T = array_map(static fn (array $x): string => (string) $x['date_prevue'], $calendrierLignes['perturbations'][$cle] ?? []);
    $toutes = array_filter(array_merge([$P, $F], $T));
    if ($toutes === [] || (int) substr(max($toutes), 0, 4) < $annee && $F !== null || (int) substr(min($toutes), 0, 4) > $annee) {
        continue;
    }
    if (preg_match('/^planche:(\d+):(\d+)$/', (string) $cle, $m) === 1) {
        $lp = $etatPlanches[(int) $m[1]] ?? null;
        $nom = ($lp['lignes'][(int) $m[2]]['nom'] ?? 'ligne vide') . ' · ligne ' . (int) $m[2];
        $planche = (int) $m[1];
    } else {
        $nom = \Syntropia\Bordures::libelle((string) $cle) . (($nomsBordures[$cle] ?? '') !== '' ? ' · ' . $nomsBordures[$cle] : '');
        $planche = null;
    }
    $mois = [];
    for ($mm = 1; $mm <= 12; $mm++) {
        $ym = sprintf('%04d-%02d', $annee, $mm);
        $mois[$mm] = ['P' => $P !== null && substr((string) $P, 0, 7) === $ym, 'F' => $F !== null && substr((string) $F, 0, 7) === $ym,
            'T' => in_array($ym, array_map(static fn (string $x): string => substr($x, 0, 7), $T), true),
            'occ' => $P !== null && $ym >= substr((string) $P, 0, 7) && ($F === null || $ym <= substr((string) $F, 0, 7))];
    }
    $lignesCal[] = ['espece' => $nom, 'planche' => $planche, 'debut' => (string) ($P ?? min($toutes)), 'mois' => $mois, 'service' => true,
        'titre' => 'Prévu : ' . implode(' · ', array_filter([$P !== null ? 'mise en place ' . $dateFr((string) $P) : '', $F !== null ? 'fin ' . $dateFr((string) $F) : '',
            $T !== [] ? count($T) . ' perturbation' . (count($T) > 1 ? 's' : '') : '']))];
}
usort($lignesCal, static fn (array $a, array $b): int => [$a['planche'] ?? PHP_INT_MAX, $a['debut'], \Syntropia\Associations::normaliser($a['espece'])] <=> [$b['planche'] ?? PHP_INT_MAX, $b['debut'], \Syntropia\Associations::normaliser($b['espece'])]);
$sansDate = count($cultures) - count($datees);
$lienCal = static fn (int $a): string => $retour . '&vue=calendrier&annee=' . $a;
$initiales12 = ['J', 'F', 'M', 'A', 'M', 'J', 'J', 'A', 'S', 'O', 'N', 'D'];

$nomAffiche = $auth->nomAffiche() ?? '';
$initiales = '';
foreach (preg_split('/\s+/', trim($nomAffiche)) ?: [] as $mot) {
    if ($mot !== '') {
        $initiales .= mb_strtoupper(mb_substr($mot, 0, 1));
    }
}
$initiales = mb_substr($initiales, 0, 2) ?: '?';
// Les quatre strates de lumière (Théry, ch. 05) : la strate proposée d'une culture d'après le référentiel — une culture de
// potager est une production, la plus haute de ses strates (assets/js/strates.js) — et l'occupation au sol par strate.
$stratesEspeces = [];
foreach ($especesService->toutes($siteId) as $e) {
    if (($s = Strates::pourLeNavigateur($e)) !== null) {
        foreach ([$e['nom_usuel'], $e['nom_scientifique']] as $n) {
            if ((string) $n !== '') {
                $stratesEspeces[mb_strtolower(trim((string) $n))] ??= $s;
            }
        }
    }
}
$occupation = $parcelle !== null ? (new Occupation())->dUneParcelle($parcelle, $siteId) : null;
// La part de biomasse de chaque planche, face aux 75 % (`PartBiomasse`) — une information.
$partsBiomasse = $parcelle !== null ? (new PartBiomasse())->dUneParcelle($parcelle, $siteId) : [];
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
<link rel="stylesheet" href="assets/css/gabarit.css?v=<?= (int) @filemtime(__DIR__ . '/assets/css/gabarit.css') ?>">
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
  .pt-onglets{ display:flex; gap:.3rem; border-bottom:1px solid var(--line); }
  .pt-onglet{ padding:.3rem 1rem; border-radius:.5rem .5rem 0 0; font:500 .84rem var(--titre); color:var(--muted); text-decoration:none; border:1px solid transparent; border-bottom:0; }
  .pt-onglet.actif{ color:var(--accent-titre); background:var(--accent-fond); border-color:var(--line); font-weight:600; }
  .pt-vue{ display:flex; flex-direction:column; gap:.5rem; min-height:0; }
  .pt-vue[hidden]{ display:none; }
  .pt-annees{ display:flex; align-items:baseline; gap:.5rem; flex-wrap:wrap; font:500 .9rem var(--titre); color:var(--ink); }
  .pt-annees a{ color:var(--accent-titre); text-decoration:none; font-weight:600; padding:0 .3rem; }
  .pt-legende{ margin-left:auto; font:400 .74rem var(--texte); color:var(--muted); }
  .pt-note-cal{ margin:0; font:400 .74rem/1.4 var(--texte); color:var(--muted); }
  .pt-cal{ width:100%; border-collapse:collapse; font:400 .84em var(--texte); color:var(--ink); table-layout:fixed; }
  .pt-cal th{ font:600 .78em var(--titre); color:var(--muted); padding:.2em .1em; text-align:center; border-bottom:1px solid var(--line); }
  .pt-cal th:first-child{ text-align:left; width:30%; }
  .pt-cal td{ text-align:center; padding:.22em .05em; border-bottom:1px solid var(--line); font:600 .78em var(--titre); color:var(--bouton-encre); }
  .pt-cal td.occ{ background:var(--accent); }
  .pt-cal td.occ-service{ background:var(--accent-doux); color:var(--ink); }
  .pt-cal td.nom{ text-align:left; background:transparent; color:var(--ink); font:400 1em var(--texte); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; padding-left:.2em; }
  .pt-cal td.nom small{ color:var(--muted); font-size:.8em; }
  @media (max-width:40rem){ .pt-legende{ display:none; } .pt-cal th:first-child{ width:34%; } }
  @media (max-height:38rem){ .pt-note-cal{ display:none; } }
  .pt-repere{ margin:.2rem 0 .7rem; padding:.45rem .6rem; border-radius:.45rem; border:1px dashed var(--line); font:400 .78rem/1.4 var(--texte); color:var(--ink); }
  .pt-repere small{ color:var(--muted); }
  .pi-saut{ list-style:none; margin:0 0 .9rem; padding:0; font:400 .84rem/1.45 var(--texte); }
  .pi-saut li{ margin:0 0 .35rem; padding-left:1.3rem; position:relative; }
  .pi-saut .pi-saut-signe{ position:absolute; left:0; }
  .pi-saut li.ok .pi-saut-signe{ color:var(--accent-titre); }
  .pi-saut li.attention{ color:var(--alerte); }
  .pt-repere button{ margin-top:.3rem; }
  .pt-vide{ margin:0; font:400 .82em/1.35 var(--texte); color:var(--muted); }
  .pt-cultures .pt-propose{ color:var(--accent-titre); font-style:italic; }
  .pt-carte:has(.pt-propose){ border-color:var(--accent); }
  .pt-rel{ margin:.35em 0 0; padding:.3em 0 0; border-top:1px solid var(--line); font:400 .8em/1.35 var(--texte); color:var(--ink); }
  .pt-rel p{ margin:.1em 0; }
  .pt-lignes{ margin:.3em 0 0; padding:.25em 0 0; border-top:1px dashed var(--line); font:400 .8em/1.35 var(--texte); color:var(--ink); }
  .pt-lignes a{ margin-left:.3em; color:var(--accent-titre); }
  .pt-ligne-choix{ margin:.5rem 0; padding:.4rem .5rem; border:1px solid var(--line); border-radius:.5rem; }
  .pt-ligne-choix select{ width:100%; margin-top:.25rem; }
  .pt-perturbations{ margin:.2rem 0 .5rem 1rem; padding:0; font:400 .82rem var(--texte); color:var(--ink); }
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
  .pt-suite{ margin:.35em 0 0; padding:.3em 0 0; border-top:1px solid var(--line); font:400 .8em/1.35 var(--texte); color:var(--ink); }
  .pt-suite p{ margin:.1em 0; }
  .pt-suite .bien{ color:var(--accent-titre); }
  .pt-suite .eviter{ color:var(--alerte); }
  .pt-suite small{ color:var(--muted); }
  .pt-fiche{ margin:.2rem 0 .7rem; padding:.45rem .6rem; border-radius:.45rem; background:var(--accent-fond); font:400 .78rem/1.4 var(--texte); color:var(--ink); }
  .pt-fiche .eviter{ color:var(--alerte); }
  .pt-erreurs{ margin:0 0 .7rem; padding:.5rem .7rem; border:1px solid var(--alerte); border-radius:.5rem; color:var(--alerte); font:400 .8rem/1.35 var(--texte); }
  .pt-erreurs ul{ margin:.2rem 0 0 1rem; padding:0; }
  .pt-retrait{ margin-top:1rem; padding-top:.6rem; border-top:1px solid var(--line); }
  .pt-retrait button{ font:600 .74rem var(--titre); color:var(--alerte); background:transparent; border:1px solid var(--alerte); border-radius:.4rem; padding:.2rem .6rem; cursor:pointer; }
  .pt-lien{ font:500 .76rem var(--titre); color:var(--accent-titre); }
  .pt-choix{ display:flex; gap:.5rem; align-items:flex-start; margin:0 0 .5rem; font:400 .82rem/1.35 var(--texte); color:var(--ink); }
  .pt-choix small{ color:var(--muted); font-size:.9em; }
  .pt-fonctions-detail{ margin:0 0 .6rem; }
  .pt-fonctions-detail summary{ font:500 .8rem var(--titre); color:var(--accent-titre); cursor:pointer; }
  .pt-fonctions{ display:grid; gap:.15rem; margin-top:.4rem; }
  .pt-fonctions label{ display:flex; gap:.4rem; align-items:flex-start; font:400 .76rem/1.3 var(--texte); color:var(--ink); }
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
        <p class="pg-pied">Un potager se compose par planches : ce qui est cultivé et les associations positives.</p>
      </div>
    </nav>

    <main class="bureau" id="bureau">
<?php if ($parcelle === null): ?>
      <div class="pt-centre">
        <p class="pt-vide-page">Le plan de culture se compose parcelle par parcelle, et ce site n'a pas encore de parcelle.
          <a href="conception-parcelles.php?nouvelle=1">Ouvrir la première parcelle →</a></p>
      </div>
<?php else: ?>
      <div class="pt-centre">
        <div class="pt-entete">
          <h1 class="pt-titre">Le plan de culture, <?= $h($titrePage) ?></h1>
<?php if ($cultures !== []): ?>
          <span class="pt-badge"><?= count($cultures) ?> culture<?= count($cultures) > 1 ? 's' : '' ?></span>
<?php endif; ?>
<?php if ($peutEcrire): ?>
          <span class="pt-actions">
<?php if ($nbPlanches >= 1 && $nonPlacees !== []): ?>
            <a class="pt-bouton" href="<?= $h($retour) ?>&amp;proposer=1" id="pt-proposer">Proposer un placement</a>
<?php endif; ?>
            <a class="pt-bouton pt-bouton--calme" href="<?= $h($retour) ?>&amp;saisie=1" id="pt-saisir">Ajouter une culture</a>
          </span>
<?php endif; ?>
        </div>
<?php if (is_array($flash)): ?>
        <p class="pt-message" role="status"><?= $h($flash[1]) ?></p>
<?php endif; ?>
<?php if ($erreurs !== []): ?>
        <p class="pt-message pt-message--alerte" role="alert">Votre saisie est à reprendre. Le détail est dans le panneau Données.</p>
<?php endif; ?>
<?php if ($nbPlanches < 1): ?>
        <p class="pt-message">Le plan de cette parcelle n'a pas encore de planches, et les cultures se placent par planche. <a href="conception-parcelles.php?parcelle=<?= $idP ?>">Ouvrir le plan →</a></p>
<?php else: ?>
        <p class="pt-annonce">Une planche peut porter plusieurs cultures. Chaque planche dit ce que les tables savent de ses voisinages. Ce sont des ordres de conduite, à éprouver chez soi.</p>
<?php endif; ?>

        <div class="pt-onglets" role="tablist">
          <a class="pt-onglet<?= $vue === 'planches' ? ' actif' : '' ?>" href="<?= $h($retour) ?>" role="tab">Les planches</a>
          <a class="pt-onglet<?= $vue === 'calendrier' ? ' actif' : '' ?>" href="<?= $h($lienCal($annee)) ?>" role="tab">Le calendrier</a>
        </div>
        <section class="pt-vue"<?= $vue === 'planches' ? '' : ' hidden' ?>>
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
<?php if ($c['cultures'] === [] && $c['proposees'] === []): ?>
              <p class="pt-vide">Rien encore sur cette planche.</p>
<?php else: ?>
              <ul class="pt-cultures">
<?php foreach ($c['cultures'] as $k): ?>
                <li><?php if ($peutEcrire): ?><a href="<?= $h($retour) ?>&amp;culture=<?= (int) $k['id'] ?>" title="Ouvrir pour corriger"><?= $h($k['espece']) ?></a><?php else: ?><b><?= $h($k['espece']) ?></b><?php endif; ?>
<?php $fonctionsK = Cultures::fonctions($k); if ($k['strate'] !== null || $k['date_semis'] !== null || $k['date_semis_repiquage'] !== null || $fonctionsK !== [] || ($abeille && ($k['demi_planche'] ?? null) !== null)): ?>
                  <small><?= $h(implode(' · ', array_filter(array_merge(
                    array_map(static fn (string $x): string => str_replace('_', ' ', $x), $fonctionsK),
                    [$abeille && in_array($k['demi_planche'] ?? '', ['perenne', 'annuelle'], true) ? 'bande ' . $libelleNature($k['demi_planche']) : '',
                     $k['strate'] !== null ? (Cultures::STRATES[$k['strate']] ?? '') : '', $k['date_semis'] !== null ? 'semis ' . $dateFr((string) $k['date_semis']) : '', $k['date_semis_repiquage'] !== null ? 'en place ' . $dateFr((string) $k['date_semis_repiquage']) : '']
                  )))) ?></small>
<?php endif; ?></li>
<?php endforeach; ?>
<?php foreach ($c['proposees'] as $x): ?>
                <li class="pt-propose">→ <?= $h($x['espece']) ?> <small>proposée</small></li>
<?php endforeach; ?>
              </ul>
<?php endif; ?>
<?php if ($c['relations'] !== [] || $c['sans_fiche'] !== []): ?>
              <div class="pt-rel">
<?php foreach ($c['relations'] as $r): ?>
<?php if ($r['relation'] === Associations::FAVORABLE): ?>
                <p class="bien">✓ <?= $h($r['a']) ?> et <?= $h($r['b']) ?> s'entendent bien</p>
<?php elseif ($r['relation'] === Associations::DEFAVORABLE): ?>
                <p class="eviter">⚠ <?= $h($r['a']) ?> et <?= $h($r['b']) ?> sont à éviter côte à côte</p>
<?php else: ?>
                <p class="eviter">⚠ Pour <?= $h($r['a']) ?> et <?= $h($r['b']) ?>, les tables se contredisent</p>
<?php endif; ?>
<?php endforeach; ?>
<?php if ($c['sans_fiche'] !== [] && count($c['cultures']) > 1): ?>
                <p><small>Aucun voisinage connu pour <?= $h(implode(', ', $c['sans_fiche'])) ?></small></p>
<?php endif; ?>
              </div>
<?php endif; ?>
<?php $ch = $c['chaine'] ?? null; if ($ch !== null && ($ch['suites'] !== [] || $ch['libere'] !== null)): ?>
              <div class="pt-suite">
<?php foreach (array_slice($ch['suites'], 0, 3) as $s): ?>
<?php if ($s['verdict'] === Succession::CONSEILLE): ?>
                <p class="bien">✓ <?= $h($s['avant']) ?> puis <?= $h($s['apres']) ?> : la table de succession cite cet enchaînement</p>
<?php elseif ($s['verdict'] === Succession::DECONSEILLE): ?>
                <p class="eviter">⚠ <?= $h($s['avant']) ?> puis <?= $h($s['apres']) ?> : la table exclut cet enchaînement</p>
<?php else: ?>
                <p><small><?= $h($s['avant']) ?> puis <?= $h($s['apres']) ?> : la table ne dit rien de cet enchaînement</small></p>
<?php endif; ?>
<?php endforeach; ?>
<?php if (count($ch['suites']) > 3): ?>
                <p><small>et <?= count($ch['suites']) - 3 ?> autre<?= count($ch['suites']) - 3 > 1 ? 's' : '' ?> enchaînement<?= count($ch['suites']) - 3 > 1 ? 's' : '' ?></small></p>
<?php endif; ?>
<?php if ($ch['libere'] !== null): $apres = Succession::apres($ch['libere']['espece']); ?>
                <p><small>Libérée le <?= $h($dateFr($ch['libere']['date'])) ?> par <?= $h($ch['libere']['espece']) ?>. <?= $apres !== [] ? 'La table cite pour suite : ' . $h(implode(', ', array_slice($apres, 0, 5))) . (count($apres) > 5 ? '…' : '') : 'La table ne cite pas de suite pour elle.' ?></small></p>
<?php endif; ?>
              </div>
<?php endif; ?>
<?php $lp = $c['numero'] !== null ? ($etatPlanches[$c['numero']] ?? null) : null; if ($lp !== null): ?>
              <p class="pt-lignes"><small>Lignes de service : <?php
                $morceaux = [];
                foreach ($lp['lignes'] as $k => $l) {
                    $morceaux[] = $k . ($k === \Syntropia\Planches::centraleDe($parcelle, (int) $c['numero']) + 1 ? '*' : '') . ' '
                        . ($l === null ? '—' : $l['nom'] . ($l['compte'] !== null ? ' (' . $l['compte'] . ')' : ''));
                }
                echo $h(implode(' · ', $morceaux)); ?></small>
<?php if ($peutEcrire): ?>
                <a href="<?= $h($retour) ?>&amp;lignes=<?= (int) $c['numero'] ?>">Les lignes</a>
<?php endif; ?></p>
<?php endif; ?>
            </section>
<?php endforeach; ?>
          </div>
        </div>
        <div class="pt-page-nav" id="pt-nav-planches">
          <button type="button" id="pt-prec-planches">← Précédentes</button><span id="pt-page-planches"></span><button type="button" id="pt-suiv-planches">Suivantes →</button>
        </div>
<?php endif; ?>
        </section>

        <section class="pt-vue"<?= $vue === 'calendrier' ? '' : ' hidden' ?>>
          <div class="pt-annees">
            <a href="<?= $h($lienCal($annee - 1)) ?>" aria-label="Année précédente">←</a> <b><?= $annee ?></b> <a href="<?= $h($lienCal($annee + 1)) ?>" aria-label="Année suivante">→</a>
            <span class="pt-legende"><b>S</b> semis à l'abri · <b>P</b> mise en place (semis en place ou repiquage) · <b>R</b> récolte. En clair, les lignes de service et les bordures, avec leurs dates prévues : <b>F</b> fin, <b>T</b> perturbation.</span>
          </div>
<?php if ($lignesCal === []): ?>
          <p class="pt-vide-page"><?= $datees === [] ? 'Aucune culture n’est encore datée. Notez un semis, une mise en place ou une récolte pour la voir ici.' : 'Aucune culture n’a de date en ' . $annee . '.' ?></p>
<?php else: ?>
          <div class="pt-zone" id="pt-cal-zone">
            <table class="pt-cal">
              <thead><tr><th>Culture ou ligne</th><?php foreach ($initiales12 as $i => $lettre): ?><th title="<?= $h(['janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'][$i]) ?>"><?= $lettre ?></th><?php endforeach; ?></tr></thead>
              <tbody>
<?php foreach ($lignesCal as $l): ?>
                <tr class="pt-ligne" title="<?= $h($l['titre']) ?>">
                  <td class="nom"><?= $l['planche'] !== null ? '<small>P' . (int) $l['planche'] . '</small> ' : '' ?><?= $h($l['espece']) ?></td>
<?php foreach ($l['mois'] as $c): $lettres = (!empty($c['S']) ? 'S' : '') . ($c['P'] ? 'P' : '') . (!empty($c['R']) ? 'R' : '') . (!empty($c['F']) ? 'F' : '') . (!empty($c['T']) ? 'T' : ''); ?>
                  <td class="<?= $c['occ'] || $lettres !== '' ? (!empty($l['service']) ? 'occ occ-service' : 'occ') : '' ?>"><?= $h($lettres) ?></td>
<?php endforeach; ?>
                </tr>
<?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div class="pt-page-nav" id="pt-nav-cal">
            <button type="button" id="pt-prec-cal">← Précédentes</button><span id="pt-page-cal"></span><button type="button" id="pt-suiv-cal">Suivantes →</button>
          </div>
<?php endif; ?>
          <p class="pt-note-cal"><?= $horsAnnee > 0 ? $horsAnnee . ' culture' . ($horsAnnee > 1 ? 's' : '') . ' datée' . ($horsAnnee > 1 ? 's' : '') . ' hors de ' . $annee . '. ' : '' ?><?= $sansDate > 0 ? $sansDate . ' sans date.' : '' ?>
            Les repères de Lopez (Haute-Vienne) se lisent en ouvrant une culture. Son tableau couvre dix-sept cultures d'automne et d'hiver.</p>
        </section>
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
<?php if ($proposition !== null): ?>
        <p class="pd-titre">Un placement proposé, <?= $h($parcelle['code']) ?></p>
        <p class="pt-note"><b>Comment il se fait.</b> Les cultures déjà placées <b>restent à leur place</b>. Les autres se répartissent sur les planches du plan : ce qui s'entend bien est regroupé, ce qui s'entend mal est écarté, et une paire sans relation connue ne compte ni pour ni contre. Rien n'est enregistré avant votre accord, et vous pouvez décocher ce que vous ne voulez pas.</p>
<?php if ($proposition['placements'] === [] && $proposition['non_places'] === []): ?>
        <p class="pt-note">Toutes les cultures sont placées : il n'y a rien à proposer.</p>
<?php else: ?>
        <form method="post" action="conception-potager.php?parcelle=<?= $idP ?>">
          <input type="hidden" name="csrf" value="<?= $h($auth->jeton()) ?>">
          <input type="hidden" name="action" value="appliquer">
          <input type="hidden" name="parcelle" value="<?= $idP ?>">
          <input type="hidden" name="empreinte" value="<?= $h(PlanDeCulture::empreinte($etatCultures)) ?>">
<?php foreach ($proposition['placements'] as $x): ?>
          <label class="pt-choix"><input type="checkbox" class="case" name="ids[]" value="<?= (int) $x['id'] ?>" checked>
            <span><b><?= $h($x['espece']) ?></b> → planche <?= (int) $x['planche'] ?><br><small><?= $h($x['raison']) ?></small></span></label>
<?php endforeach; ?>
<?php if ($proposition['non_places'] !== []): ?>
          <p class="pt-note" style="margin-top:.7rem"><b>Cultures non placées.</b> L'atelier ne les place pas à côté d'un voisinage à éviter.</p>
<?php foreach ($proposition['non_places'] as $x): ?>
          <p class="pt-note"><b><?= $h($x['espece']) ?></b><br><?= $h($x['raison']) ?></p>
<?php endforeach; ?>
<?php endif; ?>
<?php if ($proposition['placements'] !== []): ?>
          <button type="submit" class="pt-bouton">Placer la sélection</button>
<?php endif; ?>
          <a class="pt-lien" href="<?= $h($retour) ?>" style="margin-left:.6rem">Laisser tomber</a>
        </form>
<?php endif; ?>
<?php elseif ($panneauLignes !== null): $pl = $panneauLignes; $nL = count($pl['etat']['lignes']); ?>
        <p class="pd-titre">Les lignes de la planche <?= (int) $pl['numero'] ?>, <?= $h($parcelle['code']) ?></p>
        <p class="pt-note"><b>À quoi elles servent.</b> Les lignes de la planche portent les plantes de service, et les productions se
          plantent de part et d'autre. La ligne centrale porte la biomasse et la structure, jamais de tuteurs. Les autres lignes complètent
          les strates que les productions laissent vides, les plus hautes près du centre, en forme pyramidale. Chaque ligne porte une espèce.
          <?= $pl['strates'] !== [] ? 'Strates déjà occupées par les productions : ' . $h(implode(', ', array_map(static fn (string $x): string => LignesDeService::LIBELLES[$x] ?? $x, $pl['strates']))) . '.' : 'Les strates des productions de cette planche ne sont pas connues : toutes sont à compléter.' ?>
          Le nombre de lignes se règle sur <a href="conception-parcelles.php?parcelle=<?= $idP ?>">le plan</a>.</p>
<?php if (!$pl['depuis_proposition']): ?>
        <p class="pt-note"><a class="pt-lien" href="<?= $h($retour) ?>&amp;lignes=<?= (int) $pl['numero'] ?>&amp;proposer_lignes=1">Reprendre la proposition de l'atelier</a>. Rien n'est enregistré avant « Enregistrer les lignes ».</p>
<?php else: ?>
        <p class="pt-note">Les choix ci-dessous partent de la <b>proposition de l'atelier</b>. Rien n'est enregistré avant « Enregistrer les lignes ».</p>
<?php endif; ?>
        <form method="post" action="conception-potager.php?parcelle=<?= $idP ?>">
          <input type="hidden" name="csrf" value="<?= $h($auth->jeton()) ?>">
          <input type="hidden" name="action" value="lignes_planche">
          <input type="hidden" name="parcelle" value="<?= $idP ?>">
          <input type="hidden" name="numero_planche" value="<?= (int) $pl['numero'] ?>">
          <input type="hidden" name="version_lignes" value="<?= $h($pl['etat']['version']) ?>">
<?php foreach ($pl['proposition'] as $l): $k = $l['indice'] + 1; $pose = $pl['etat']['lignes'][$k] ?? null;
    $idsCand = array_map(static fn (array $e): int => (int) $e['id'], $l['candidats']); ?>
          <div class="pt-ligne-choix">
            <label>Ligne <?= $k ?><?= $l['centrale'] ? ', centrale' : '' ?><?= $l['strate'] !== null ? ' · strate ' . $h(LignesDeService::LIBELLES[$l['strate']] ?? $l['strate']) : '' ?>
              <?= $pose !== null && $pose['compte'] !== null ? '<small>(' . (int) $pose['compte'] . ' plants posés)</small>' : '' ?>
              <select name="choix[<?= $k ?>]">
                <option value="0">Laisser vide</option>
<?php if ($l['candidats'] !== []): ?>
                <optgroup label="Proposées pour cette ligne">
<?php foreach ($l['candidats'] as $e): ?>
                  <option value="<?= (int) $e['id'] ?>"<?= (int) $pl['choix'][$k] === (int) $e['id'] ? ' selected' : '' ?>><?= $h((string) ($e['nom_usuel'] ?: $e['nom_scientifique']) . (Espacement::deService($e) !== null ? ', ' . rtrim(rtrim(number_format((float) Espacement::deService($e), 2, ',', ''), '0'), ',') . ' m' . (Espacement::parDefaut($e) ? ' par défaut' : '') : ', sans espacement de service')) ?></option>
<?php endforeach; ?>
                </optgroup>
<?php endif; ?>
                <optgroup label="Les autres espèces">
<?php foreach ($especesService->toutes($siteId) as $e): if (in_array((int) $e['id'], $idsCand, true)) { continue; } ?>
                  <option value="<?= (int) $e['id'] ?>"<?= (int) $pl['choix'][$k] === (int) $e['id'] ? ' selected' : '' ?>><?= $h((string) ($e['nom_usuel'] ?: $e['nom_scientifique'])) ?></option>
<?php endforeach; ?>
                </optgroup>
              </select></label>
            <small class="pt-note"><?= $h($l['raison']) ?></small>
<?php $dl = $pl['dates'][$k]; ?>
            <input type="hidden" name="version_dates[<?= $k ?>]" value="<?= $h($dl['version']) ?>">
            <div class="pt-deux" style="margin-top:.3rem">
              <label class="pt-champ">Mise en place prévue
                <input type="date" name="mise[<?= $k ?>]" value="<?= $h($dl['mise']) ?>"></label>
              <label class="pt-champ">Fin prévue
                <input type="date" name="fin[<?= $k ?>]" value="<?= $h($dl['fin']) ?>"></label>
            </div>
<?php if ($dl['propose']): ?>
            <small class="pt-note">Ces dates sont proposées d'après le calendrier de <?= $h($dl['repere']['espece']) ?> dans Espèces. C'est une prévision, à corriger.</small>
<?php endif; ?>
          </div>
<?php endforeach; ?>
          <button type="submit" class="pt-bouton">Enregistrer les lignes</button>
          <a class="pt-lien" href="<?= $h($retour) ?>" style="margin-left:.6rem">Fermer</a>
        </form>
<?php $prevues = $calendrierService->dUneParcelle($idP)['perturbations']; $posees = array_filter($pl['etat']['lignes']); ?>
        <p class="pd-titre" style="margin-top:1rem">Les perturbations prévues</p>
        <p class="pt-note">Les perturbations se prévoient pour les lignes posées. Le maïs et le tournesol de la ligne centrale se
          coupent eux aussi. Ce sont des prévisions : le jour réel s'adapte au temps qu'il fait, et le journal garde ce qui a été
          fait. Les repères de Théry (ch. 12) : la première coupe en vert en juin, puis les suivantes quand il y a de quoi couper.</p>
<?php if ($posees === []): ?>
        <p class="pt-note">Posez d'abord une plante sur une ligne, puis prévoyez ses coupes.</p>
<?php else: ?>
<?php foreach ($posees as $k => $pose): $liste = $prevues[LignesPlantation::clePlanche((int) $pl['numero'], (int) $k)] ?? []; if ($liste === []) { continue; } ?>
        <p class="pt-note"><b>Ligne <?= (int) $k ?> · <?= $h($pose['nom']) ?></b></p>
        <ul class="pt-perturbations">
<?php foreach ($liste as $x): ?>
          <li><?= $h((new \DateTimeImmutable((string) $x['date_prevue']))->format('d/m/Y')) ?> : <?= $h(CalendrierLignes::libelleGeste((string) $x['geste'])) ?>
            <form method="post" action="conception-potager.php?parcelle=<?= $idP ?>" style="display:inline">
              <input type="hidden" name="csrf" value="<?= $h($auth->jeton()) ?>">
              <input type="hidden" name="action" value="perturbation_retirer">
              <input type="hidden" name="numero_planche" value="<?= (int) $pl['numero'] ?>">
              <input type="hidden" name="ligne" value="<?= (int) $k ?>">
              <input type="hidden" name="perturbation_id" value="<?= (int) $x['id'] ?>">
              <button type="submit" class="pt-lien" aria-label="Retirer cette perturbation prévue">✕</button>
            </form></li>
<?php endforeach; ?>
        </ul>
<?php endforeach; ?>
        <form method="post" action="conception-potager.php?parcelle=<?= $idP ?>" class="pt-deux">
          <input type="hidden" name="csrf" value="<?= $h($auth->jeton()) ?>">
          <input type="hidden" name="action" value="perturbation_ajouter">
          <input type="hidden" name="numero_planche" value="<?= (int) $pl['numero'] ?>">
          <label class="pt-champ">La ligne
            <select name="ligne">
<?php foreach ($posees as $k => $pose): ?>
              <option value="<?= (int) $k ?>"<?= $k === \Syntropia\Planches::centraleDe($parcelle, (int) $pl['numero']) + 1 ? ' selected' : '' ?>>Ligne <?= (int) $k ?> · <?= $h($pose['nom']) ?></option>
<?php endforeach; ?>
            </select></label>
          <label class="pt-champ">La date prévue
            <input type="date" name="date_prevue"></label>
          <label class="pt-champ">Le geste
            <select name="geste">
<?php foreach (CalendrierLignes::GESTES as $g): ?>
              <option value="<?= $g ?>"><?= $h(CalendrierLignes::libelleGeste($g)) ?></option>
<?php endforeach; ?>
            </select></label>
          <button type="submit" class="pt-bouton" style="align-self:end">Prévoir</button>
        </form>
<?php endif; ?>
<?php else: ?>
        <p class="pd-titre"><?= $editionId > 0 ? 'Corriger la culture' : 'Ajouter une culture' ?>, <?= $h($parcelle['code']) ?></p>
        <p class="pt-note"><b>À quoi elle sert.</b> Chaque culture se place sur une planche, pour que ce qui s'entend bien soit voisin et que ce qui s'entend mal soit séparé. Seul le nom est demandé. La planche, la strate et les dates se donnent quand on les connaît.</p>
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
          <p class="pt-note">Cette culture n'est pas dans les tables : <b>aucun voisinage n'est connu</b>, ce qui ne la rend pas neutre pour autant.</p>
<?php endif; ?>
<?php if ($suiteEditee !== null): ?>
          <div class="pt-fiche"><b>La succession, selon la table de « <?= $h($f['espece']) ?> »</b> [Weinrich p.115]<br>
            Avant : <?= $suiteEditee['avant'] !== [] ? $h(implode(', ', $suiteEditee['avant'])) : '<i>rien de noté</i>' ?><br>
            Après : <?= $suiteEditee['apres'] !== [] ? $h(implode(', ', $suiteEditee['apres'])) : '<i>rien de noté</i>' ?><br>
            <small>Ce sont des ordres de conduite d'Allemagne moyenne, à éprouver chez soi. Ce que la table ne dit pas n'est pas neutre pour autant.</small></div>
<?php elseif ($f['espece'] !== '' && $editionId > 0): ?>
          <p class="pt-note">Cette culture n'est pas dans la table de succession : rien n'est connu de ce qui la précède ou la suit.</p>
<?php endif; ?>
<?php if ($repere !== null): ?>
          <div class="pt-repere"><b>Repère de Lopez</b> <small>[maraîcher en Haute-Vienne, dates lues sans transposition]</small><br>
            <?= $h($repere['culture']) ?> : semis <b><?= $h($repere['semis'] !== '' ? $repere['semis'] : '—') ?></b> · repiquage <b><?= $h($repere['repiquage'] !== '' ? $repere['repiquage'] : '—') ?></b><?= $repere['a_verifier'] === 'repiquage' ? ' <small>(la source signale ce repiquage « à vérifier » : rien n’est proposé)</small>' : '' ?><br>
            <small>Ces dates en mots sont indicatives : l'atelier prend le milieu de la fenêtre. « Un point de départ à remplacer par sa propre mesure », dit le livre.</small><br>
<?php if ($indicatives['semis'] !== null || $indicatives['repiquage'] !== null): ?>
            <button type="button" class="pt-bouton pt-bouton--calme pt-reprendre-bouton" data-semis="<?= $h($indicatives['semis'] ?? '') ?>" data-repiquage="<?= $h($indicatives['repiquage'] ?? '') ?>">Reprendre ces dates<?= $indicatives['semis'] !== null ? ' (' . $h($dateFr($indicatives['semis'])) . ($indicatives['repiquage'] !== null ? ' → ' . $h($dateFr($indicatives['repiquage'])) : '') . ')' : '' ?></button>
<?php endif; ?>
          </div>
<?php elseif ($f['espece'] !== '' && $editionId > 0): ?>
          <p class="pt-note">Lopez ne donne pas de repère pour cette culture : son tableau couvre dix-sept cultures d'automne et d'hiver. Aucune date n'est proposée, et la date est à vous.</p>
<?php endif; ?>
<?php if ($especeConnue !== null && ($indicativesEspece['semis'] !== null || $indicativesEspece['repiquage'] !== null || $indicativesEspece['recolte'] !== null || $fonctionsEspece !== [])): ?>
          <div class="pt-repere"><b>Repère du référentiel</b> <small>[<?= (int) $especeConnue['site_id'] === $siteId ? 'votre surcharge' : 'cadre' ?>, <a href="especes.php?espece=<?= (int) $especeConnue['id'] ?>">voir/corriger →</a>]</small><br>
<?php if ($indicativesEspece['semis'] !== null || $indicativesEspece['repiquage'] !== null || $indicativesEspece['recolte'] !== null): ?>
            <?= $h((string) ($especeConnue['nom_usuel'] ?: $especeConnue['nom_scientifique'])) ?> :
            <?= $especeConnue['periode_semis'] !== null ? 'semis <b>' . $h((string) $especeConnue['periode_semis']) . '</b>' : '' ?>
            <?= $especeConnue['periode_repiquage'] !== null ? ' · repiquage <b>' . $h((string) $especeConnue['periode_repiquage']) . '</b>' : '' ?>
            <?= $especeConnue['periode_recolte'] !== null ? ' · récolte <b>' . $h((string) $especeConnue['periode_recolte']) . '</b>' : '' ?><br>
            <small>Ces dates en mots sont indicatives : l'atelier prend le milieu de la fenêtre, comme pour le repère de Lopez.</small><br>
            <button type="button" class="pt-bouton pt-bouton--calme pt-reprendre-bouton" data-semis="<?= $h($indicativesEspece['semis'] ?? '') ?>" data-repiquage="<?= $h($indicativesEspece['repiquage'] ?? '') ?>" data-recolte="<?= $h($indicativesEspece['recolte'] ?? '') ?>">Reprendre ces dates<?= $indicativesEspece['semis'] !== null ? ' (' . $h($dateFr($indicativesEspece['semis'])) . ($indicativesEspece['repiquage'] !== null ? ' → ' . $h($dateFr($indicativesEspece['repiquage'])) : '') . ($indicativesEspece['recolte'] !== null ? ' → ' . $h($dateFr($indicativesEspece['recolte'])) : '') . ')' : '' ?></button>
<?php endif; ?>
<?php if ($fonctionsEspece !== []): ?>
            <br><button type="button" class="pt-bouton pt-bouton--calme" id="pt-reprendre-fonctions" data-fonctions="<?= $h(implode(',', $fonctionsEspece)) ?>">Reprendre ces fonctions de service (<?= $h(implode(', ', array_map(static fn (string $x): string => str_replace('_', ' ', $x), $fonctionsEspece))) ?>)</button>
<?php endif; ?>
          </div>
<?php endif; ?>
          <div class="pt-deux">
            <label class="pt-champ">La planche
              <select name="planche">
                <option value=""><?= $nbPlanches < 1 ? 'Pas de planche au plan' : 'Pas encore placée' ?></option>
<?php for ($n = 1; $n <= $nbPlanches; $n++): ?>
                <option value="<?= $n ?>"<?= $f['planche'] === (string) $n ? ' selected' : '' ?>>Planche <?= $n ?><?= ($natures[$n]['nature'] ?? null) !== null ? ' · ' . $libelleNature($natures[$n]['nature']) : '' ?></option>
<?php endfor; ?>
              </select>
            </label>
<?php if ($abeille): ?>
            <label class="pt-champ">La bande
              <select name="demi_planche">
                <option value="">Non dite</option>
<?php foreach (Planches::NATURES as $cle => $lib): ?>
                <option value="<?= $cle ?>"<?= $f['demi_planche'] === $cle ? ' selected' : '' ?>><?= $lib ?></option>
<?php endforeach; ?>
              </select>
            </label>
<?php endif; ?>
            <label class="pt-champ">Sa strate
              <select name="strate">
                <option value="">Non dite</option>
<?php foreach (Cultures::STRATES as $cle => $lib): ?>
                <option value="<?= $h($cle) ?>"<?= $f['strate'] === $cle ? ' selected' : '' ?>><?= $h($lib) ?></option>
<?php endforeach; ?>
              </select>
            </label>
          </div>
          <p class="pt-note" id="pt-strate-repere" hidden></p>
          <details class="pt-fonctions-detail"<?= $fonctionsSaisie !== [] ? ' open' : '' ?>>
            <summary>Fonctions de service (<?= count($fonctionsSaisie) ?>)</summary>
            <p class="pt-note">Ce que la culture rend au système, au-delà de sa récolte : de la biomasse (celle qu'on taille, que le journal
              des perturbations et le saut du chat comptent), un bloque-herbe, un tuteur pour une grimpante… Le vocabulaire est celui du scénario pérenne.</p>
            <div class="pt-fonctions">
<?php foreach (Scenario::FONCTIONS as $cle => $lib): ?>
              <label><input type="checkbox" class="case" name="fonctions[]" value="<?= $h($cle) ?>"<?= in_array($cle, $fonctionsSaisie, true) ? ' checked' : '' ?>> <?= $h($lib) ?></label>
<?php endforeach; ?>
            </div>
          </details>
          <div class="pt-deux">
            <label class="pt-champ">Semis à l'abri <small>Pépinière ou godets, prévu ou fait</small>
              <input type="date" name="date_semis" value="<?= $h($f['date_semis']) ?>">
            </label>
            <label class="pt-champ">Mise en place <small>Semis en place ou repiquage</small>
              <input type="date" name="date_semis_repiquage" value="<?= $h($f['date_semis_repiquage']) ?>">
            </label>
          </div>
          <label class="pt-champ">Récolte <small>Prévue</small>
            <input type="date" name="date_recolte" value="<?= $h($f['date_recolte']) ?>">
          </label>
          <div class="pt-deux">
            <label class="pt-champ">Plants <small>Combien, facultatif</small>
              <input type="number" name="nb_plants" min="1" max="100000" step="1" inputmode="numeric" value="<?= $h($f['nb_plants']) ?>">
            </label>
            <label class="pt-champ">Espacement <small>En mètres, facultatif</small>
              <input type="number" name="espacement_m" min="0.01" max="20" step="0.01" inputmode="decimal" value="<?= $h($f['espacement_m']) ?>">
            </label>
          </div>
          <p class="pt-note">Le nombre de plants sert à l'étape suivante, <b>Matériel et eau</b>, qui en tire les graines à démarrer. Sans lui, l'atelier estime une ligne de plants sur la planche, d'après l'espacement.</p>
          <label class="pt-champ">Notes <small>Facultatif</small>
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
<?php if ($occupation !== null): ?>
      <p><strong>L'occupation au sol, par strate.</strong> C'est la surface que couvrent les plantes adultes (l'envergure, dans
         <a href="especes.php">Espèces</a>), rapportée à la parcelle. Les repères de Théry (ch. 05) sont de 20 % pour l'émergente,
         40 % pour la haute, 60 % pour la moyenne et 80 % pour la basse. La somme dépasse 100 %, car chaque strate prend la lumière
         que la précédente laisse.</p>
      <ul class="pi-saut">
<?php foreach ($occupation as $o):
    $manque = array_merge($o['sans_envergure'] !== [] ? ['sans envergure : ' . implode(', ', $o['sans_envergure'])] : [],
        $o['sans_nombre'] !== [] ? ['sans nombre de plants : ' . implode(', ', $o['sans_nombre'])] : []); ?>
        <li class="<?= $o['couverture'] === null ? 'vide' : ($o['couverture'] >= $o['repere'] ? 'ok' : 'attention') ?>"><span class="pi-saut-signe"><?= $o['couverture'] === null ? '○' : ($o['couverture'] >= $o['repere'] ? '✓' : '⚠') ?></span>
          <strong><?= $h($o['libelle']) ?></strong> : <?= $o['couverture'] === null ? 'rien de compté' : $h(str_replace('.', ',', (string) $o['couverture']) . ' %') ?> (repère <?= (int) $o['repere'] ?> %)<?= $o['plants'] > 0 ? ' · ' . (int) $o['plants'] . ($o['plants'] > 1 ? ' plants comptés' : ' plant compté') : '' ?><?= $manque !== [] ? ' · <small>' . $h(implode(' ; ', $manque)) . '</small>' : '' ?></li>
<?php endforeach; ?>
      </ul>
<?php endif; ?>
<?php if ($partsBiomasse !== []): ?>
      <p><strong>La part de biomasse.</strong> Théry destine 70 à 80 % des plantes implantées à la biomasse et à la
         perturbation (ch. 11). L'atelier vise 75 %, <?= true ? 'planche par planche' : 'sur la ligne centrale' ?>.
         C'est un repère, pas une règle.</p>
      <ul class="pi-saut">
<?php foreach ($partsBiomasse as $pb): ?>
        <li class="<?= $pb['atteinte'] ? 'ok' : 'vide' ?>"><span class="pi-saut-signe"><?= $pb['atteinte'] === null ? '○' : ($pb['atteinte'] ? '✓' : '·') ?></span>
          <strong><?= htmlspecialchars($pb['libelle'], ENT_QUOTES, 'UTF-8') ?></strong> : <?= $pb['part'] === null ? 'rien de compté'
            : (int) round($pb['part'] * 100) . ' %, ' . $pb['biomasse'] . ' plant' . ($pb['biomasse'] > 1 ? 's' : '') . ' de biomasse sur ' . $pb['total'] ?><?= $pb['sans_nombre'] !== []
            ? ' · <small>sans nombre de plants : ' . htmlspecialchars(implode(', ', $pb['sans_nombre']), ENT_QUOTES, 'UTF-8') . '</small>' : '' ?></li>
<?php endforeach; ?>
      </ul>
<?php endif; ?>
      <p>Un potager se compose <strong>par planche</strong>. Les planches viennent du plan de la parcelle, et chacune peut porter plusieurs
         cultures. Le potager syntropique recrée sa biomasse chaque année par des annuelles. Ses
         strates sont les quatre de la syntropie.</p>
      <p><strong>Ce que dit chaque planche.</strong> Les relations viennent des tables d'association de Christa Weinrich (abbaye de Fulda) :
         ce qui s'entend bien, et ce qu'il vaut mieux éviter de placer côte à côte. L'atelier conseille, il n'interdit rien.</p>
      <p><strong>Des ordres de conduite.</strong> Une relation bénéfique peut ne pas se vérifier partout. Elle s'éprouve chez soi, avec un
         témoin à côté. La source vient d'Allemagne moyenne (Jura souabe, Hesse), avec un itinéraire où le sol est ameubli chaque printemps.
         L'atelier en reprend les associations de plantes, et non l'itinéraire.</p>
      <p><strong>Le calendrier.</strong> Une culture a trois dates : le <strong>semis à l'abri</strong> (en pépinière, en godets), la
         <strong>mise en place</strong> (le semis en place, ou le repiquage) et la récolte. Les repères viennent de Yann Lopez, maraîcher en
         Haute-Vienne. Ses dates se lisent sans transposition, et son tableau couvre <strong>dix-sept cultures d'automne et d'hiver</strong>
         (choux, rutabaga, navet, épinard, mesclun…), en dates de la forme « fin avril » ou « mi à fin juillet ». Ce sont donc des dates
         <strong>indicatives</strong> : l'atelier prend le milieu de la fenêtre, « un point de départ à remplacer par sa propre mesure ».
         Une culture absente du tableau n'a pas de repère, et la date est à vous.</p>
      <p><strong>Le placement proposé.</strong> Il répartit les cultures non placées sur les planches du plan. Il regroupe ce qui s'entend
         bien, écarte ce qui s'entend mal, et laisse à leur place les cultures déjà placées. S'il ne trouve aucune planche sans voisin à
         éviter, il laisse la culture non placée et le dit. Vous pouvez alors ajouter une planche ou décider autrement. Vous acceptez la
         proposition en tout ou en partie, ou vous la laissez.</p>
      <p><strong>Les cultures absentes des tables.</strong> « Aucun voisinage connu » ne veut pas dire « sans effet ». Les tables ne sont pas
         symétriques : une relation est connue dès qu'une des deux plantes la cite.</p>
      <p>Connecté en tant que <?= $h($nomAffiche) ?>. <a href="deconnexion.php">Se déconnecter</a>.</p>
    </div>
  </aside>

  <footer class="pied">
    <span class="pied-mention">Syntropia — en construction</span>
    <span class="pied-spacer"></span>
  </footer>

</div>

<script src="assets/js/etapes.js?v=<?= (int) @filemtime(__DIR__ . '/assets/js/etapes.js') ?>"></script>
<script src="assets/js/gabarit.js?v=<?= (int) @filemtime(__DIR__ . '/assets/js/gabarit.js') ?>"></script>
<script src="assets/js/strates.js?v=<?= (int) @filemtime(__DIR__ . '/assets/js/strates.js') ?>"></script>
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

    document.querySelectorAll('.pt-reprendre-bouton').forEach(function(reprendre){
      reprendre.addEventListener('click', function(){
        var f = reprendre.closest('form') || document.querySelector('.pt-form form');
        if (reprendre.dataset.semis) f.querySelector('[name=date_semis]').value = reprendre.dataset.semis;
        if (reprendre.dataset.repiquage) f.querySelector('[name=date_semis_repiquage]').value = reprendre.dataset.repiquage;
        if (reprendre.dataset.recolte) f.querySelector('[name=date_recolte]').value = reprendre.dataset.recolte;
      });
    });
    // La strate proposée d'après le référentiel : une culture de potager est une production (Théry, ch. 05).
    var champEspece = document.querySelector('input[name=espece][list=pt-connues]');
    if (champEspece) StrateProposee({ espece: champEspece, strate: champEspece.form.querySelector('[name=strate]'),
      note: document.getElementById('pt-strate-repere'), especes: <?= json_encode((object) $stratesEspeces, JSON_UNESCAPED_UNICODE) ?>,
      libelles: <?= json_encode(Cultures::STRATES, JSON_UNESCAPED_UNICODE) ?>, production: function(){ return true; } });
    var reprendreFonctions = document.getElementById('pt-reprendre-fonctions');
    if (reprendreFonctions) reprendreFonctions.addEventListener('click', function(){
      var f = reprendreFonctions.closest('form') || document.querySelector('.pt-form form');
      var cles = reprendreFonctions.dataset.fonctions.split(',');
      cles.forEach(function(cle){
        var c = f.querySelector('[name="fonctions[]"][value="' + cle + '"]');
        if (c) c.checked = true;
      });
      var detail = f.querySelector('.pt-fonctions-detail');
      if (detail) detail.open = true;
    });

    /* Le bureau ne défile jamais : chaque vue se réduit, puis se pagine en montrant autant d'éléments que la place le permet. */
    var centre = document.querySelector('.pt-centre');
    function deborde(){ return centre.scrollHeight > centre.clientHeight + 1; }
    function pagineur(zoneId, itemsSel, suffixe){
      var items = Array.prototype.slice.call(document.querySelectorAll(itemsSel));
      var page = 0, parPage = items.length;
      function afficher(){
        items.forEach(function(l, i){ l.style.display = (i >= page * parPage && i < (page + 1) * parPage) ? '' : 'none'; });
        var nav = document.getElementById('pt-nav-' + suffixe);
        if (!nav) return;
        var pages = Math.max(1, Math.ceil(items.length / parPage));
        nav.classList.toggle('actif', pages > 1);
        document.getElementById('pt-page-' + suffixe).textContent = 'Page ' + (page + 1) + ' sur ' + pages;
        document.getElementById('pt-prec-' + suffixe).disabled = page === 0;
        document.getElementById('pt-suiv-' + suffixe).disabled = page >= pages - 1;
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
      var prec = document.getElementById('pt-prec-' + suffixe), suiv = document.getElementById('pt-suiv-' + suffixe);
      if (prec) prec.addEventListener('click', function(){ page = Math.max(0, page - 1); afficher(); });
      if (suiv) suiv.addEventListener('click', function(){ page++; afficher(); });
      return ajuster;
    }
    var aPlanches = pagineur('pt-zone', '.pt-item', 'planches');
    var aCal = pagineur('pt-cal-zone', '.pt-ligne', 'cal');
    function tout(){ aPlanches(); aCal(); }
    if (centre) {
      tout();
      window.addEventListener('resize', tout);
      if (document.fonts && document.fonts.ready) document.fonts.ready.then(tout);
    }
  })();
</script>
</body>
</html>
