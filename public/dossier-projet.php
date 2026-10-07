<?php

declare(strict_types=1);

/**
 * Le Dossier Projet — le projet de la personne, à imprimer ou à enregistrer en PDF.
 *
 * Voir `chantier-dossier-projet.md`. Phase 1 : la couverture et la Définition (vision, objectifs, moyens, site), le
 * choix des chapitres à imprimer, la mise en page papier. Phase 2 : la Conception — le terrain (les tracés de la carte,
 * avec échelle et nord, sans fond de carte) et une page par parcelle (ce qui la définit, son plan, son scénario). Les
 * dessins viennent de `DessinsDossier`. Phase 3 : dans chaque parcelle, ce qu'il faut se procurer et l'eau (les comptes
 * de l'étape « Matériel et eau », par `MaterielDuSite` et `BesoinEau`), puis le récapitulatif du projet, le calendrier
 * et les participants. Phase 4 : la Réalisation (préparation, matériel végétal, outils et plans, consignation) et le
 * Suivi (arrosage et germination, le journal des perturbations et les constats du saut du chat de chaque parcelle, la
 * consignation) — ce que ces étapes enregistrent, rien de plus.
 *
 * DÉLIBÉRÉMENT HORS DU GABARIT « LE BUREAU » — la seconde exception, admise par le porteur le 2026-09-19, comme
 * `releve-vierge.php` : c'est un document qu'on imprime, pas une page qu'on visite, et il fait plusieurs pages ; le
 * gabarit verrouille tout défilement. La page reprend seulement les polices et les couleurs du bureau. N'en faites pas
 * un modèle pour autre chose qu'un document destiné au papier.
 *
 * **Le choix des chapitres** se fait par des cases, en CSS pur (`:has()`) : une case décochée retire son chapitre de
 * l'écran comme du papier — ce qu'on voit est ce qui s'imprime. Il ne s'enregistre pas (décision du porteur) : toutes
 * les cases sont cochées à chaque ouverture.
 *
 * **Les réponses** sont celles de la personne qui ouvre le dossier, à défaut celles du porteur
 * (`Reponses::definitionDuProjet()`) ; le dossier dit de qui elles viennent. Il n'écrit rien : un lecteur l'imprime
 * comme un porteur. Un chapitre sans réponse le dit, et dit où il se remplit — jamais un chapitre vide.
 */

require __DIR__ . '/../src/autoload.php';
require __DIR__ . '/../src/Configuration.php';
require __DIR__ . '/../src/Database.php';

use Syntropia\BesoinEau;
use Syntropia\Bordures;
use Syntropia\Conduite;
use Syntropia\Configuration;
use Syntropia\DessinsDossier;
use Syntropia\Materiel;
use Syntropia\Perturbation;
use Syntropia\Planches;
use Syntropia\Relief;
use Syntropia\Role;
use Syntropia\SautDuChat;
use Syntropia\Stade;
use Syntropia\Tortue;
use Syntropia\Web\Authentification;
use Syntropia\Web\Calques;
use Syntropia\Web\Comptes;
use Syntropia\Web\Constats;
use Syntropia\Web\Cultures;
use Syntropia\Web\Especes;
use Syntropia\Web\Heritage;
use Syntropia\Web\Interventions;
use Syntropia\Web\LignesPlantation;
use Syntropia\Web\MaterielDuSite;
use Syntropia\Web\Parcelles;
use Syntropia\Web\PlanImprime;
use Syntropia\Web\ReleveSol;
use Syntropia\Web\Reponses;
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

$definition = (new Reponses())->definitionDuProjet($siteId, $compteId);
$r = $definition['reponses'];

$porteurs = array_values(array_filter($sites->membres($siteId), static fn (array $m): bool => $m['role'] === 'porteur'));
$noms = (new Comptes())->nomsParIds(array_column($porteurs, 'compte_id'));
$nomsPorteurs = array_values(array_filter(array_map(static fn (array $m): string => trim((string) ($noms[$m['compte_id']]['nom_affiche'] ?? '')) !== ''
    ? (string) $noms[$m['compte_id']]['nom_affiche'] : (string) ($noms[$m['compte_id']]['identifiant'] ?? ''), $porteurs)));

$conduite = Conduite::tryFrom((string) ($site['conduite'] ?? ''));

$mois = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
$aujourdhui = new DateTimeImmutable('now', new DateTimeZone('Europe/Paris'));
$dateEdition = (int) $aujourdhui->format('j') . ($aujourdhui->format('j') === '1' ? 'er' : '') . ' '
    . $mois[(int) $aujourdhui->format('n') - 1] . ' ' . $aujourdhui->format('Y');

/** Le texte sûr, ses retours à la ligne gardés. */
$h = static fn (string $t): string => nl2br(htmlspecialchars($t, ENT_QUOTES, 'UTF-8'), false);

/** Une réponse libre, ou `null` si elle n'est pas donnée. */
$texte = static function (string $cle) use ($r): ?string {
    $v = trim((string) ($r[$cle] ?? ''));
    return $v === '' ? null : $v;
};

/** Une réponse à choix, dite par son libellé — `null` si elle n'est pas donnée ou n'est plus proposée. */
$choix = static function (string $cle, array $libelles) use ($texte): ?string {
    $v = $texte($cle);
    return $v === null ? null : ($libelles[$v] ?? null);
};

$destinations = ['loisir' => 'Loisir', 'vivrier' => 'Vivrier', 'commercial' => 'Commercial', 'social' => 'Social'];
$listeDestinations = array_values(array_filter(array_map(
    static fn (string $d): ?string => $destinations[trim($d)] ?? null,
    explode(',', (string) ($r['objectifs_destinations'] ?? ''))
)));

$position = ($site['latitude'] ?? null) !== null && ($site['longitude'] ?? null) !== null
    ? number_format((float) $site['latitude'], 5, ',', '') . ' ; ' . number_format((float) $site['longitude'], 5, ',', '')
    : null;

/**
 * Les chapitres de la Définition : titre, l'étape où ils se remplissent, et leurs lignes (question → réponse, `null`
 * si elle n'est pas donnée).
 */
$chapitres = [
    'vision' => ['Vision', 'definition-vision.php', [
        'Qui êtes-vous ?'                => $choix('vision_qui', ['seul' => 'Seul', 'groupe' => 'En groupe']),
        'Votre motivation'               => $texte('vision_motivation'),
        'L’intention, au-delà de vous'   => $texte('vision_intention'),
    ]],
    'objectifs' => ['Objectifs', 'definition-objectifs.php', [
        'Quelles productions ?'          => $texte('objectifs_productions'),
        'Pour quelle destination ?'      => $listeDestinations === [] ? null : implode(', ', $listeDestinations),
        'À quel terme ?'                 => $choix('objectifs_terme', ['court' => 'Court — environ 3 ans', 'moyen' => 'Moyen — 5 à 10 ans', 'long' => 'Long — plus de 10 ans']),
    ]],
    'moyens' => ['Moyens', 'definition-moyens.php', [
        'Ce que vous savez déjà faire'   => $texte('moyens_connaissances'),
        'Le temps que vous pouvez y consacrer' => $texte('moyens_temps'),
        'Vos moyens financiers'          => $choix('moyens_financier', ['faible' => 'Faibles', 'modere' => 'Modérés', 'confortable' => 'Confortables']),
        'Le terrain'                     => $choix('moyens_terrain', ['oui' => 'Je l’ai déjà', 'non' => 'Je le cherche encore']),
    ]],
    'site' => ['Le site', 'definition-site.php', [
        'Commune'                        => $texte('site_commune'),
        'Position (latitude ; longitude)' => $position,
        'Superficie'                     => $texte('site_superficie') === null ? null
            : (is_numeric($texte('site_superficie')) ? number_format((float) $texte('site_superficie'), fmod((float) $texte('site_superficie'), 1.0) == 0.0 ? 0 : 2, ',', ' ') : $texte('site_superficie')) . ' m²',
        'Zone climatique USDA'           => $texte('site_usda'),
        'Exposition dominante'           => $choix('site_exposition', ['nord' => 'Nord', 'sud' => 'Sud', 'est' => 'Est', 'ouest' => 'Ouest', 'plat' => 'Plat']),
        'Pente'                          => $choix('site_pente', ['plate' => 'Plate', 'legere' => 'Légère', 'marquee' => 'Marquée', 'forte' => 'Forte']),
        'Conditions climatiques particulières' => $texte('site_climat'),
        'Conduite'                       => $conduite?->libelle(),
    ]],
];

$nomProjet = (string) $site['nom'];

/** Une date de la base (`AAAA-MM-JJ`) en mots ; `null` si elle n'est pas donnée. */
$dateEnMots = static function (?string $iso) use ($mois): ?string {
    $d = $iso === null ? false : DateTimeImmutable::createFromFormat('!Y-m-d', substr($iso, 0, 10));
    return $d === false ? null : (int) $d->format('j') . ($d->format('j') === '1' ? 'er' : '') . ' ' . $mois[(int) $d->format('n') - 1] . ' ' . $d->format('Y');
};

/* ------------------------------------------------------------------ la Conception : le terrain */

$elements = (new Calques())->dUnSite($siteId);
// Les courbes de niveau ne se stockent pas : elles se tirent de la grille d'altitudes, comme dans `conception-etude-site.php`.
// Le libellé d'un relevé se recompose depuis son pas : ceux d'avant le 2026-10-03 portaient le pas sans arrondi.
foreach ($elements as $rang => $el) {
    if ($el['calque'] === 'relief' && $el['type'] === 'grille' && is_array($el['proprietes']['z'] ?? null) && $el['geometrie'] !== []) {
        $elements[$rang]['proprietes']['courbes'] = Relief::courbesGeo($el['proprietes']['z'], (int) ($el['proprietes']['cote'] ?? 0),
            (float) ($el['proprietes']['pas'] ?? 10), $el['geometrie'][0]);
        if (is_numeric($el['proprietes']['pas'] ?? null)) {
            $elements[$rang]['libelle'] = 'Relief IGN — pas de ' . DessinsDossier::nombre((float) $el['proprietes']['pas'], 1) . ' m';
        }
    }
}
$dessinTerrain = DessinsDossier::terrain($elements, Calques::CALQUES);
/** Ce qui a été tracé, calque par calque, dans l'ordre du voyage : le libellé et la mesure de chaque élément. */
$traces = [];
foreach ($elements as $el) {
    $mesure = match ($el['type']) {
        'polygone' => ($m = Calques::surfaceM2($el['geometrie'])) >= 10000 ? DessinsDossier::nombre($m / 10000) . ' ha' : DessinsDossier::nombre($m, 0) . ' m²',
        'ligne' => DessinsDossier::nombre(Calques::longueurM($el['geometrie']), 0) . ' m',
        'grille' => count((array) ($el['proprietes']['courbes'] ?? [])) > 0 ? 'courbes de niveau' : 'grille d’altitudes',
        default => is_numeric($el['proprietes']['hauteur_m'] ?? null) ? DessinsDossier::nombre((float) $el['proprietes']['hauteur_m']) . ' m de haut' : '',
    };
    $type = ['polygone' => 'zone', 'ligne' => 'ligne', 'point' => 'point', 'grille' => 'relief'][$el['type']] ?? $el['type'];
    $traces[$el['calque']][] = ['libelle' => trim((string) ($el['libelle'] ?? '')), 'type' => $type, 'mesure' => $mesure];
}

/* ------------------------------------------------------------------ la Conception : les parcelles */

$parcellesService = new Parcelles();
$lignesService = new LignesPlantation();
$materielService = new MaterielDuSite();
$releves = new ReleveSol();
$entreesProjet = [];
$planImprime = new PlanImprime();
$parcelles = [];
foreach ($parcellesService->dUnSite($siteId) as $p) {
    $idP = (int) $p['id'];
    ['conduite' => $cParcelle, 'niveau' => $niveau] = Heritage::conduite($p, $site);
    $L = $p['longueur_m'] !== null ? (float) $p['longueur_m'] : null;
    $l = $p['largeur_m'] !== null ? (float) $p['largeur_m'] : null;
    $zone = Planches::zone($p);

    // Le plan, ses plants et leur légende : les mêmes que sur le feuillet de chantier (`PlanImprime`).
    $imprime = $planImprime->dUneParcelle($p, $siteId);
    $bordures = [];
    foreach ($imprime['etat'] as $cle => $b) {
        if ($b['places'] === []) {
            continue;
        }
        $compte = $b['compte'];
        $bordures[] = ['libelle' => Bordures::libelle($cle), 'serie' => array_map(static fn (array $pl): string => $pl['nom'] . ($pl['variete'] !== '' ? ' (' . $pl['variete'] . ')' : ''), $b['places']),
            'total' => $compte !== null && $compte['ok'] ? $compte['total'] : null, 'hors_plan' => $b['geometrie'] === null];
    }

    // Les lignes des planches qui portent une plante de service.
    $natures = Planches::natures($p);
    $planches = [];
    if ($zone !== null) {
        foreach ($lignesService->etatPlanches($p, $siteId) as $numero => $ep) {
            $lignes = array_filter($ep['lignes']);
            $nat = $natures[$numero] ?? null;
            $planches[] = ['numero' => $numero,
                'nature' => $nat === null ? null : ($nat['bandes'] !== null
                    ? implode(' puis ', array_map(static fn (string $n): string => mb_strtolower(Planches::NATURES[$n]), $nat['bandes']))
                    : mb_strtolower(Planches::NATURES[$nat['nature']])),
                'lignes' => $lignes, 'nbLignes' => count($ep['lignes']),
                'centrale' => Planches::centraleDe($p, $numero) + 1];
        }
    }

    // Le scénario : la frise en syntropie, les strates en jardin-forêt, les cultures par planche pour un potager.
    $potager = ($p['type'] ?? '') === 'potager';
    $cultures = $potager ? (new Cultures())->dUneParcelle($idP) : [];
    $lignesScenario = $potager ? [] : $parcellesService->scenario($idP);
    $strates = $cParcelle->strates();
    $groupes = array_fill_keys(array_keys($strates), []);
    foreach ($lignesScenario as $ls) {
        if (isset($groupes[(string) $ls['strate']])) {
            $groupes[(string) $ls['strate']][] = $ls;
        } else {
            $strates['__reclasser'] = 'À reclasser';
            $groupes['__reclasser'][] = $ls;
        }
    }
    foreach ($groupes as &$gr) {
        usort($gr, static fn (array $a, array $b): int =>
            [(int) \Syntropia\Scenario::formulaire($a)['debut'], (int) $a['id']] <=> [(int) \Syntropia\Scenario::formulaire($b)['debut'], (int) $b['id']]);
    }
    unset($gr);

    // Ce qu'il faut se procurer, et l'eau : les comptes de l'étape « Matériel et eau », parcelle par parcelle.
    $materiel = $materielService->entrees($siteId, [$p]);
    $entreesProjet = array_merge($entreesProjet, $materiel['entrees']);
    $surface = BesoinEau::surfaceM2($p);
    $releve = $releves->recents($idP, 1)[0] ?? null;

    $parcelles[] = [
        'id' => $idP, 'code' => (string) $p['code'], 'nom' => (string) $p['nom'], 'potager' => $potager,
        'identite' => [
            'Type' => $potager ? 'Potager' : 'Pérenne',
            'Conduite' => $niveau === 'aucun' ? null : $cParcelle->libelle() . ($niveau === 'site' ? ' (celle du site)' : ''),
            'Type de tortue' => Tortue::depuis($p['tortue'] ?? null)?->libelle(),
            'Stade du terrain' => Stade::depuis($p['stade'] ?? null)?->libelle(),
            'Dimensions' => $L !== null && $l !== null ? DessinsDossier::nombre($L) . ' m × ' . DessinsDossier::nombre($l) . ' m, soit ' . DessinsDossier::nombre($L * $l, 1) . ' m²' : null,
            'Planches' => $zone === null ? null : $zone['disp']['n'] . ' planche' . ($zone['disp']['n'] > 1 ? 's' : '') . ', dans le sens ' . ($zone['nordSud'] ? 'nord-sud' : 'est-ouest'),
            'Campagne de plantation' => $dateEnMots($p['date_plantation'] ?? null) === null ? null
                : 'commencée le ' . $dateEnMots($p['date_plantation']) . (($f = $dateEnMots($p['fin_plantation'] ?? null)) !== null ? ', finie le ' . $f : ''),
        ],
        'plan' => $imprime['plan'],
        'legendePlan' => $imprime['legendePlan'],
        'legende' => $imprime['legende'],
        'bordures' => $bordures, 'planches' => $planches,
        'cultures' => $cultures,
        'materiel' => Materiel::regrouper($materiel['entrees']),
        'eau' => $surface === null ? null : ['surface' => $surface, 'besoins' => BesoinEau::besoins($surface),
            'pointe' => BesoinEau::pointe($surface), 'releve' => $releve, 'freq' => BesoinEau::frequence($releve)],
        'scenario' => $lignesScenario === [] ? null : ($cParcelle->scenarioADesTemps() ? ['frise' => DessinsDossier::frise($strates, $groupes)] : ['strates' => $strates, 'groupes' => $groupes]),
    ];
}

/* ------------------------------------------------------------------ la Conception : matériel et eau, calendrier */

// Tout le projet : la liste d'achats commune, la surface arrosée et la ressource en eau du site.
$recapProjet = Materiel::regrouper($entreesProjet);
$syntheseProjet = Materiel::synthese($recapProjet);
$aSacrifier = array_sum(array_column($recapProjet, 'biomasse'));
$surfaceProjet = array_sum(array_map(static fn (array $pc): float => $pc['eau']['surface'] ?? 0.0, $parcelles));
$besoinsProjet = $surfaceProjet > 0 ? BesoinEau::besoins($surfaceProjet) : [];
$sansDimensions = array_column(array_filter($parcelles, static fn (array $pc): bool => $pc['eau'] === null), 'code');
$ressource = (string) ($site['ressource_eau'] ?? '');
$ressourceDetail = trim((string) ($site['ressource_eau_detail'] ?? ''));

$calendrier = (new Reponses())->calendrierDuProjet($siteId, $compteId);
$saisons = ['calendrier_printemps' => 'Printemps', 'calendrier_ete' => 'Été', 'calendrier_automne' => 'Automne', 'calendrier_hiver' => 'Hiver'];

/* ------------------------------------------------------------------ la Réalisation et le Suivi */

// Ce que ces deux phases enregistrent (décision 5 du porteur : ce qui n'est pas enregistré ne s'imprime pas) : des
// réponses — celles de la personne, à défaut celles du porteur — et, par parcelle, le journal des perturbations et les
// constats du saut du chat. Les deux consignations ne gardent que leur dernière saisie : le dossier imprime celle-là.
$realisation = (new Reponses())->realisationDuProjet($siteId, $compteId);
$suivi = (new Reponses())->suiviDuProjet($siteId, $compteId);
$rep = static function (array $source, string $cle): ?string {
    $v = trim((string) ($source['reponses'][$cle] ?? ''));
    return $v === '' ? null : $v;
};
$repChoix = static fn (array $source, string $cle, array $libelles): ?string => ($v = $rep($source, $cle)) === null ? null : ($libelles[$v] ?? null);
$repDate = static fn (array $source, string $cle): ?string => ($v = $rep($source, $cle)) === null ? null : ($dateEnMots($v) ?? $v);

/** Les chapitres de la Réalisation et du Suivi, comme ceux de la Définition : titre, étape, lignes (`null` : le journal). */
$chapitresRealisation = [
    'preparation' => ['Préparation de la parcelle', 'realisation-preparation-parcelle.php', [
        'La technique' => $repChoix($realisation, 'preparation_technique', SautDuChat::PREPARATIONS),
        'Réalisée'     => $repChoix($realisation, 'preparation_realisee', ['manuellement' => 'Manuellement', 'animaux' => 'Par un ou plusieurs animaux',
            'machine' => 'Avec une machine', 'les-deux' => 'Plusieurs moyens', 'autre' => 'Autrement']),
        'Précisions'   => $rep($realisation, 'preparation_precisions'),
    ]],
    'materiel-vegetal' => ['Matériel végétal', 'realisation-materiel-vegetal.php', [
        'Ce que vous multipliez vous-même' => $rep($realisation, 'materiel_multiplication'),
        'Ce que vous allez vous procurer'  => $rep($realisation, 'materiel_achat'),
    ]],
    'outils' => ['Outils et plans', 'realisation-outils-et-plans.php', [
        'Les outils nécessaires'           => $rep($realisation, 'outils_necessaires'),
        'Qui vient, et combien'            => $rep($realisation, 'outils_moyens_humains'),
        'Notes sur le plan d’implantation' => $rep($realisation, 'outils_plan_notes'),
    ]],
    'consignation-realisation' => ['Consignation', 'realisation-consignation.php', [
        'Quand'                   => $repDate($realisation, 'consignation_realisation_date'),
        'Ce qui a été fait'       => $rep($realisation, 'consignation_realisation_fait'),
        'Dans quelles conditions' => $rep($realisation, 'consignation_realisation_conditions'),
    ]],
];
$chapitresSuivi = [
    'arrosage' => ['Arrosage et germination', 'suivi-arrosage-germination.php', [
        'L’arrosage'                    => $rep($suivi, 'suivi_arrosage'),
        'Les boutures ont-elles pris ?' => $repChoix($suivi, 'suivi_boutures', ['oui' => 'Oui', 'en-cours' => 'En cours', 'non' => 'Non', 'sans-objet' => 'Sans objet']),
        'Germination visible ?'         => $repChoix($suivi, 'suivi_germination', ['oui' => 'Oui', 'pas-encore' => 'Pas encore', 'sans-objet' => 'Sans objet']),
        'Notes'                         => $rep($suivi, 'suivi_arrosage_notes'),
    ]],
    'journal' => ['Perturbations et saut du chat', 'suivi-perturbation.php', null],
    'consignation-suivi' => ['Consignation', 'suivi-consignation.php', [
        'Quand'              => $repDate($suivi, 'consignation_suivi_date'),
        'Ce qui est observé' => $rep($suivi, 'consignation_suivi_observations'),
        'Les résultats'      => $rep($suivi, 'consignation_suivi_resultats'),
    ]],
];

// Le journal de chaque parcelle, dans l'ordre des jours — un papier se lit du début à la fin. La hauteur d'un constat se
// dit contre la hauteur adulte d'Espèces, comme à l'écran.
$interventionsService = new Interventions();
$constatsService = new Constats();
$especesService = new Especes();
$journaux = [];
foreach ($parcelles as $pc) {
    $constatsP = array_reverse($constatsService->dUneParcelle($pc['id']));
    foreach ($constatsP as $k => $c) {
        $fiche = $c['hauteur_cm'] !== null && (string) ($c['vegetal'] ?? '') !== '' ? $especesService->parNomUsage((string) $c['vegetal'], $siteId) : null;
        $constatsP[$k]['adulte'] = $fiche !== null && ($fiche['hauteur_m'] ?? null) !== null ? (float) $fiche['hauteur_m'] : null;
    }
    $journaux[] = ['code' => $pc['code'], 'nom' => $pc['nom'], 'id' => $pc['id'],
        'interventions' => array_reverse($interventionsService->dUneParcelle($pc['id'])), 'constats' => $constatsP];
}

/** Les chapitres de la Conception, pour les cases : le terrain, une page par parcelle, le matériel et l'eau, le calendrier. */
$chapitresConception = ['terrain' => 'Le terrain'] + ($parcelles === [] ? ['parcelles' => 'Les parcelles'] : []);
foreach ($parcelles as $pc) {
    $chapitresConception['parcelle-' . $pc['id']] = 'Parcelle ' . $pc['code'];
}
$chapitresConception['materiel'] = 'Matériel et eau';
$chapitresConception['calendrier'] = 'Le calendrier';

/* ------------------------------------------------------------------ les participants */

// Qui a accès au projet, et depuis quand : visible de tout membre (le porteur, 2026-10-04), pas seulement du porteur
// comme dans `membres.php`, où l'on retire un accès.
$membres = $sites->membres($siteId);
$nomsMembres = (new Comptes())->nomsParIds(array_column($membres, 'compte_id'));
$roles = ['porteur' => 'Porteur', 'contributeur' => 'Contributeur', 'lecteur' => 'Lecteur'];
$participants = array_map(static fn (array $m): array => [
    'nom' => trim((string) ($nomsMembres[$m['compte_id']]['nom_affiche'] ?? '')) !== ''
        ? (string) $nomsMembres[$m['compte_id']]['nom_affiche'] : (string) ($nomsMembres[$m['compte_id']]['identifiant'] ?? ''),
    'role' => $roles[$m['role']] ?? $m['role'],
    'depuis' => $m['depuis_le']], $membres);

/** Une quantité du récapitulatif, telle que l'écran la dit : estimée, minimale, ou à déterminer. */
$quantite = static function (array $r): string {
    if ($r['n'] !== null) {
        return ($r['estime'] ? '≈ ' : '') . DessinsDossier::nombre((float) $r['n'], 0) . ($r['estime'] ? ' <span class="vide">estimé</span>' : '')
            . ($r['graines'] > 0 ? ' <span class="vide">· ≈ ' . DessinsDossier::nombre((float) $r['graines'], 0) . ' graines</span>' : '');
    }
    return $r['connu'] > 0 ? '≥ ' . DessinsDossier::nombre((float) $r['connu'], 0) . ' <span class="vide">, le reste à préciser</span>' : '<span class="vide">à déterminer</span>';
};

/** Un texte dans une chaîne CSS : dans `<style>`, rien n'est décodé — on échappe à la manière du CSS (`\22 `), ce qui
 *  écarte aussi toute fermeture de la balise. */
$cssTexte = static fn (string $t): string => (string) preg_replace_callback('/["\\\\<>&\r\n]/',
    static fn (array $m): string => '\\' . dechex(ord($m[0])) . ' ', $t);
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dossier Projet — <?= htmlspecialchars($nomProjet, ENT_QUOTES, 'UTF-8') ?></title>
<link rel="stylesheet" href="assets/css/dessins-papier.css?v=<?= (int) @filemtime(__DIR__ . '/assets/css/dessins-papier.css') ?>">
<style>
  @font-face{ font-family:'Clash Display'; src:url('assets/polices/ClashDisplay-Semibold.woff2') format('woff2'); font-weight:600; font-style:normal; font-display:swap; }
  @font-face{ font-family:'Archivo'; src:url('assets/polices/Archivo-Regular.woff2') format('woff2'); font-weight:400; font-style:normal; font-display:swap; }
  @font-face{ font-family:'Archivo'; src:url('assets/polices/Archivo-Medium.woff2') format('woff2'); font-weight:500; font-style:normal; font-display:swap; }
  :root{
    --ink:#26312A; --muted:#4E5A50; --line:#C7D2BE;
    --fond:#F6EEDB; --surface:#FFFFFF; --accent-titre:#84651F; --accent:#C89B3C;
    --titre:'Clash Display', system-ui, sans-serif; --texte:'Archivo', system-ui, sans-serif;
  }
  *{ box-sizing:border-box; }
  body{ margin:0; background:var(--fond); color:var(--ink); font:400 .95rem/1.5 var(--texte); }

  .barre{
    position:sticky; top:0; z-index:2; display:flex; flex-wrap:wrap; gap:.6rem 1rem; justify-content:space-between;
    align-items:center; background:var(--surface); border-bottom:1px solid var(--line); padding:.7rem 1rem;
  }
  .barre a{ color:var(--accent-titre); font:600 .85rem var(--titre); text-decoration:none; }
  .barre a:hover{ text-decoration:underline; }
  .barre button{
    padding:.45rem .9rem; border:0; border-radius:.5rem; background:var(--accent);
    color:#fff; font:600 .85rem var(--titre); cursor:pointer;
  }
  .barre button:focus-visible, .reglage input:focus-visible + span{ outline:2px solid var(--accent-titre); outline-offset:2px; }

  .reglage{ max-width:46rem; width:calc(100% - 2rem); margin:1rem auto 0; padding:.9rem 1rem; background:var(--surface); border:1px solid var(--line); border-radius:.6rem; }
  .reglage p{ margin:0 0 .5rem; }
  .reglage .titre-reglage{ font:600 .85rem var(--titre); text-transform:uppercase; letter-spacing:.04em; color:var(--accent-titre); }
  .reglage .cases{ display:flex; flex-wrap:wrap; gap:.4rem 1.2rem; }
  .reglage label{ display:inline-flex; align-items:center; gap:.4rem; cursor:pointer; }
  .reglage input{ position:absolute; opacity:0; width:1px; height:1px; }
  .reglage input + span::before{
    content:''; display:inline-block; width:1rem; height:1rem; margin-right:.4rem; vertical-align:-.15rem;
    border:1.5px solid var(--accent); border-radius:.25rem; background:var(--surface);
  }
  .reglage input:checked + span::before{ background:var(--accent) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 10 10'%3E%3Cpolyline points='2,5 4.2,7.2 8,2.8' fill='none' stroke='white' stroke-width='1.6'/%3E%3C/svg%3E") center/80% no-repeat; }
  .reglage .note{ margin:.5rem 0 0; color:var(--muted); font-size:.85rem; }

  .document{ max-width:46rem; margin:1rem auto 3rem; padding:0 1rem; }
  .feuille{ background:var(--surface); border:1px solid var(--line); border-radius:.6rem; padding:1.6rem 1.8rem; margin-bottom:1rem; }

  .couverture{ text-align:center; padding:4rem 1.8rem; }
  .couverture .sur-titre{ font:600 .9rem var(--titre); letter-spacing:.12em; text-transform:uppercase; color:var(--accent-titre); margin:0 0 1.5rem; }
  .couverture h1{ font:600 2.2rem/1.15 var(--titre); margin:0 0 1rem; }
  .couverture dl{ display:inline-grid; grid-template-columns:auto auto; gap:.3rem 1rem; text-align:left; margin:1.5rem 0 0; }
  .couverture dt{ color:var(--muted); }
  .couverture dd{ margin:0; font-weight:500; }

  h2{ font:600 1.35rem/1.2 var(--titre); margin:0 0 .3rem; color:var(--accent-titre); }
  .phase{ font:600 .75rem var(--titre); letter-spacing:.1em; text-transform:uppercase; color:var(--muted); margin:0 0 .2rem; }
  .titre-phase{ display:none; font:600 1.6rem/1.2 var(--titre); letter-spacing:.06em; text-transform:uppercase; margin:0 0 6mm; padding-bottom:3mm; border-bottom:1.5px solid var(--accent); }
  .provenance{ color:var(--muted); font-size:.85rem; margin:0 0 1rem; }
  .reponses{ margin:0; }
  .reponses div{ padding:.55rem 0; border-top:1px solid var(--line); break-inside:avoid; }
  .reponses dt{ font-weight:500; color:var(--muted); font-size:.85rem; }
  .reponses dd{ margin:.15rem 0 0; }
  .vide{ color:var(--muted); font-style:italic; }
  .a-faire{ margin:.8rem 0 0; color:var(--muted); font-size:.85rem; }

  /* Le choix des chapitres : une case décochée retire son chapitre, à l'écran comme sur le papier. */
<?php foreach (array_merge(['couverture'], array_keys($chapitres), array_keys($chapitresConception), array_keys($chapitresRealisation), array_keys($chapitresSuivi), ['participants']) as $id): ?>
  body:has(#imprimer-<?= $id ?>:not(:checked)) #chapitre-<?= $id ?>{ display:none; }
<?php endforeach; ?>
  .aucun{ display:none; }
  body:not(:has(<?= implode(', ', array_map(static fn (string $id): string => '#imprimer-' . $id . ':checked', array_keys($chapitres))) ?>)) #phase-definition{ display:none !important; }
  body:not(:has(<?= implode(', ', array_map(static fn (string $id): string => '#imprimer-' . $id . ':checked', array_keys($chapitresConception))) ?>)) #phase-conception{ display:none !important; }
  body:not(:has(<?= implode(', ', array_map(static fn (string $id): string => '#imprimer-' . $id . ':checked', array_keys($chapitresRealisation))) ?>)) #phase-realisation{ display:none !important; }
  body:not(:has(<?= implode(', ', array_map(static fn (string $id): string => '#imprimer-' . $id . ':checked', array_keys($chapitresSuivi))) ?>)) #phase-suivi{ display:none !important; }
  body:not(:has(.reglage input:checked)) .aucun{ display:block; }

  .reglage .groupe{ font-weight:500; color:var(--muted); font-size:.85rem; margin:.4rem 0 .2rem; }
  h3{ font:600 1rem/1.2 var(--titre); margin:1.4rem 0 .5rem; color:var(--ink); break-after:avoid; page-break-after:avoid; }
  .sous-titre{ color:var(--muted); margin:0 0 1rem; }
  figure{ margin:.5rem 0 0; break-inside:avoid; }
  /* Un titre et ce qu'il annonce ne se séparent pas : Firefox ignore `break-after:avoid` sur le titre seul. */
  .bloc{ break-inside:avoid; page-break-inside:avoid; }
  figcaption{ color:var(--muted); font-size:.85rem; margin-top:.3rem; }
  table.liste{ width:100%; border-collapse:collapse; font-size:.9rem; }
  table.liste th, table.liste td{ text-align:left; vertical-align:top; padding:.35rem .5rem .35rem 0; border-top:1px solid var(--line); }
  table.liste th{ font-weight:500; color:var(--muted); font-size:.8rem; }
  table.liste tr{ break-inside:avoid; }
  table.liste.serre{ font-size:.8rem; }
  .feuille.journal{ break-inside:auto; }
  .feuille.journal table + .sous-titre{ margin-top:1.1rem; }
  .nombre{ text-align:right !important; white-space:nowrap; }
  .note-dossier{ color:var(--muted); font-size:.85rem; margin:.5rem 0 0; }

  @media (max-width:30rem){
    .feuille{ padding:1.1rem 1rem; }
    .couverture{ padding:2.5rem 1rem; }
    .couverture h1{ font-size:1.7rem; }
  }

  @page{
    size:A4 portrait; margin:15mm;
    @bottom-center{ content:"<?= $cssTexte($nomProjet) ?> — Dossier Projet — page " counter(page) " sur " counter(pages); font:400 8pt 'Archivo', sans-serif; color:#4E5A50; }
  }
  @page :first{ @bottom-center{ content:none; } }
  @media print{
    body{ background:#fff; font-size:10.5pt; }
    .barre, .reglage, .aucun{ display:none !important; }
    .document{ max-width:none; margin:0; padding:0; }
    /* Un saut de page après la couverture, et avant chaque phase (une seule pour l'instant) ; les chapitres d'une même
       phase s'enchaînent, un chapitre ne se coupe pas s'il tient sur une page. */
    .feuille{ border:0; border-radius:0; padding:0; margin:0 0 9mm; break-inside:avoid; }
    .couverture{ padding-top:35vh; break-after:page; margin:0; }
    .phase{ display:none; }
    .titre-phase{ display:block; }
    #phase-conception, #phase-realisation, #phase-suivi{ break-before:page; }
    .feuille.parcelle, .feuille.a-part{ break-before:page; break-inside:auto; }
    .feuille.terrain{ break-inside:auto; }
    a{ color:inherit; text-decoration:none; }
    .lien-etape{ display:none; }
  }
</style>
</head>
<body>

<div class="barre">
  <a href="conception-parcelles.php" id="retour">← Retour à l’atelier</a>
  <button type="button" id="imprimer">Imprimer / Enregistrer en PDF</button>
</div>

<div class="reglage">
  <p class="titre-reglage">Chapitres à imprimer</p>
  <div class="cases">
    <label><input type="checkbox" id="imprimer-couverture" checked><span>Couverture</span></label>
<?php foreach ($chapitres as $id => [$titre]): ?>
    <label><input type="checkbox" id="imprimer-<?= $id ?>" checked><span><?= htmlspecialchars($titre, ENT_QUOTES, 'UTF-8') ?></span></label>
<?php endforeach; ?>
  </div>
  <p class="groupe">Conception</p>
  <div class="cases">
<?php foreach ($chapitresConception as $id => $titre): ?>
    <label><input type="checkbox" id="imprimer-<?= $id ?>" checked><span><?= htmlspecialchars($titre, ENT_QUOTES, 'UTF-8') ?></span></label>
<?php endforeach; ?>
  </div>
  <p class="groupe">Réalisation</p>
  <div class="cases">
<?php foreach ($chapitresRealisation as $id => [$titre]): ?>
    <label><input type="checkbox" id="imprimer-<?= $id ?>" checked><span><?= htmlspecialchars($titre, ENT_QUOTES, 'UTF-8') ?></span></label>
<?php endforeach; ?>
  </div>
  <p class="groupe">Suivi</p>
  <div class="cases">
<?php foreach ($chapitresSuivi as $id => [$titre]): ?>
    <label><input type="checkbox" id="imprimer-<?= $id ?>" checked><span><?= htmlspecialchars($titre, ENT_QUOTES, 'UTF-8') ?></span></label>
<?php endforeach; ?>
  </div>
  <p class="groupe">Le projet</p>
  <div class="cases">
    <label><input type="checkbox" id="imprimer-participants" checked><span>Les participants</span></label>
  </div>
  <p class="note">Ce que vous voyez ici est ce qui s’imprime. Pour un PDF, choisissez « Enregistrer en PDF » comme
     imprimante. Le choix des chapitres ne se garde pas : tout est coché à chaque ouverture.</p>
</div>

<main class="document">
  <p class="feuille aucun">Aucun chapitre n’est coché : rien à imprimer.</p>

  <section class="feuille couverture" id="chapitre-couverture">
    <p class="sur-titre">Dossier Projet</p>
    <h1><?= htmlspecialchars($nomProjet, ENT_QUOTES, 'UTF-8') ?></h1>
    <dl>
      <dt><?= count($nomsPorteurs) > 1 ? 'Porteurs' : 'Porteur' ?></dt>
      <dd><?= $nomsPorteurs === [] ? '<span class="vide">non dit</span>' : htmlspecialchars(implode(', ', $nomsPorteurs), ENT_QUOTES, 'UTF-8') ?></dd>
<?php if ($texte('site_commune') !== null): ?>
      <dt>Commune</dt>
      <dd><?= htmlspecialchars((string) $texte('site_commune'), ENT_QUOTES, 'UTF-8') ?></dd>
<?php endif; ?>
      <dt>Conduite</dt>
      <dd><?= $conduite !== null ? htmlspecialchars($conduite->libelle(), ENT_QUOTES, 'UTF-8') : '<span class="vide">pas encore choisie</span>' ?></dd>
      <dt>Édité le</dt>
      <dd><?= $dateEdition ?></dd>
    </dl>
  </section>

  <h2 class="titre-phase" id="phase-definition">Définition</h2>
<?php foreach ($chapitres as $id => [$titre, $etape, $lignes]): ?>
<?php $renseignees = array_filter($lignes, static fn (?string $v): bool => $v !== null); ?>
  <section class="feuille" id="chapitre-<?= $id ?>">
    <p class="phase">Définition</p>
    <h2><?= htmlspecialchars($titre, ENT_QUOTES, 'UTF-8') ?></h2>
<?php if ($definition['nom'] !== null): ?>
    <p class="provenance">Réponses de <?= htmlspecialchars($definition['nom'], ENT_QUOTES, 'UTF-8') ?>.</p>
<?php endif; ?>
<?php if ($renseignees === []): ?>
    <p class="vide">Pas encore renseigné. Cela se fait à l’étape « <?= htmlspecialchars($titre, ENT_QUOTES, 'UTF-8') ?> » de la Définition.</p>
    <p class="a-faire lien-etape"><a href="<?= $etape ?>">Aller à cette étape →</a></p>
<?php else: ?>
    <dl class="reponses">
<?php foreach ($lignes as $question => $reponse): ?>
      <div>
        <dt><?= htmlspecialchars($question, ENT_QUOTES, 'UTF-8') ?></dt>
        <dd><?= $reponse !== null ? $h($reponse) : '<span class="vide">pas encore renseigné</span>' ?></dd>
      </div>
<?php endforeach; ?>
    </dl>
<?php endif; ?>
  </section>
<?php endforeach; ?>

  <h2 class="titre-phase" id="phase-conception">Conception</h2>
  <section class="feuille terrain" id="chapitre-terrain">
    <p class="phase">Conception</p>
    <h2>Le terrain</h2>
<?php if ($dessinTerrain === null): ?>
    <p class="vide">Pas encore tracé. Cela se fait à l’étape « Étude du site » de la Conception.</p>
    <p class="a-faire lien-etape"><a href="conception-etude-site.php">Aller à cette étape →</a></p>
<?php else: ?>
    <p class="sous-titre">Les tracés de la carte, sur fond blanc, le nord en haut.</p>
    <figure>
      <?= $dessinTerrain ?>
      <ul class="legende">
<?php foreach (array_keys($traces) as $calque): ?>
        <li><i class="trait" style="background:<?= Calques::CALQUES[$calque]['teinte'] ?>"></i><?= htmlspecialchars(Calques::CALQUES[$calque]['libelle'], ENT_QUOTES, 'UTF-8') ?></li>
<?php endforeach; ?>
      </ul>
    </figure>
    <h3>Ce qui a été tracé</h3>
    <table class="liste">
      <thead><tr><th>Calque</th><th>Élément</th><th class="nombre">Mesure</th></tr></thead>
      <tbody>
<?php foreach ($traces as $calque => $liste): ?>
<?php foreach ($liste as $k => $tr): ?>
        <tr>
          <td><?= $k === 0 ? htmlspecialchars(Calques::CALQUES[$calque]['libelle'], ENT_QUOTES, 'UTF-8') : '' ?></td>
          <td><?= $tr['libelle'] !== '' ? htmlspecialchars($tr['libelle'], ENT_QUOTES, 'UTF-8') . ' <span class="vide">(' . $tr['type'] . ')</span>' : '<span class="vide">' . $tr['type'] . ' sans nom</span>' ?></td>
          <td class="nombre"><?= htmlspecialchars($tr['mesure'], ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
<?php endforeach; ?>
<?php endforeach; ?>
      </tbody>
    </table>
<?php endif; ?>
  </section>

<?php if ($parcelles === []): ?>
  <section class="feuille" id="chapitre-parcelles">
    <p class="phase">Conception</p>
    <h2>Les parcelles</h2>
    <p class="vide">Pas encore de parcelle. Cela se fait à l’étape « Parcelles » de la Conception.</p>
    <p class="a-faire lien-etape"><a href="conception-parcelles.php">Aller à cette étape →</a></p>
  </section>
<?php endif; ?>
<?php foreach ($parcelles as $pc): ?>
  <section class="feuille parcelle" id="chapitre-parcelle-<?= $pc['id'] ?>">
    <p class="phase">Conception</p>
    <h2>Parcelle <?= htmlspecialchars($pc['code'], ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($pc['nom'], ENT_QUOTES, 'UTF-8') ?></h2>
    <dl class="reponses">
<?php foreach ($pc['identite'] as $question => $reponse): ?>
      <div>
        <dt><?= htmlspecialchars($question, ENT_QUOTES, 'UTF-8') ?></dt>
        <dd><?= $reponse !== null ? $h($reponse) : '<span class="vide">pas encore renseigné</span>' ?></dd>
      </div>
<?php endforeach; ?>
    </dl>

    <div class="bloc">
    <h3>Le plan</h3>
<?php if ($pc['plan'] === null): ?>
    <p class="vide">Pas encore dessiné. Les dimensions se donnent à l’étape « Parcelles » de la Conception.</p>
    <p class="a-faire lien-etape"><a href="conception-parcelles.php?parcelle=<?= $pc['id'] ?>">Aller à cette étape →</a></p>
<?php else: ?>
    <figure>
      <?= $pc['plan'] ?>
<?php if ($pc['legende'] !== []): ?>
      <ul class="legende">
<?php foreach ($pc['legende'] as $e): ?>
        <li><i style="background:<?= $e['couleur'] ?>"></i><b><?= htmlspecialchars($e['sigle'], ENT_QUOTES, 'UTF-8') ?></b>
          <?= htmlspecialchars($e['nom'] . ($e['variete'] !== '' ? ' (' . $e['variete'] . ')' : ''), ENT_QUOTES, 'UTF-8') ?> : <?= (int) $e['n'] ?> plants</li>
<?php endforeach; ?>
      </ul>
<?php endif; ?>
      <figcaption><?= htmlspecialchars($pc['legendePlan'], ENT_QUOTES, 'UTF-8') ?></figcaption>
    </figure>
<?php endif; ?>
    </div>
<?php if ($pc['bordures'] !== []): ?>
    <div class="bloc">
    <h3>Les bordures</h3>
    <table class="liste">
      <thead><tr><th>Bordure</th><th>Série plantée, dans l’ordre</th><th class="nombre">Plants</th></tr></thead>
      <tbody>
<?php foreach ($pc['bordures'] as $b): ?>
        <tr>
          <td><?= htmlspecialchars($b['libelle'], ENT_QUOTES, 'UTF-8') ?><?= $b['hors_plan'] ? ' <span class="vide">(côté retiré du plan)</span>' : '' ?></td>
          <td><?= htmlspecialchars(implode(' · ', $b['serie']), ENT_QUOTES, 'UTF-8') ?></td>
          <td class="nombre"><?= $b['total'] !== null ? $b['total'] : '<span class="vide">non compté</span>' ?></td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
    </div>
<?php endif; ?>
<?php if (array_filter($pc['planches'], static fn (array $pl): bool => $pl['lignes'] !== [] || $pl['nature'] !== null) !== []): ?>
    <div class="bloc">
    <h3>Les planches</h3>
    <table class="liste">
      <thead><tr><th>Planche</th><th>Lignes de service</th></tr></thead>
      <tbody>
<?php foreach ($pc['planches'] as $pl): ?>
        <tr>
          <td><?= $pl['numero'] ?><?= $pl['nature'] !== null ? ', ' . htmlspecialchars($pl['nature'], ENT_QUOTES, 'UTF-8') : '' ?></td>
          <td><?php if ($pl['lignes'] === []): ?><span class="vide">aucune plante de service</span><?php else: ?>
<?= implode('<br>', array_map(static fn (int $n, array $li): string => 'Ligne ' . $n . ($n === $pl['centrale'] ? ' (centrale)' : '') . ' : '
    . htmlspecialchars($li['nom'], ENT_QUOTES, 'UTF-8') . ($li['compte'] !== null ? ', ' . $li['compte'] . ' plants' : ''), array_keys($pl['lignes']), $pl['lignes'])) ?>
<?php endif; ?></td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
    </div>
<?php endif; ?>

<?php if ($pc['potager']): ?>
    <div class="bloc">
    <h3>Le plan de culture</h3>
<?php if ($pc['cultures'] === []): ?>
    <p class="vide">Pas encore renseigné. Cela se fait à l’étape « Potager » de la Conception.</p>
    <p class="a-faire lien-etape"><a href="conception-potager.php?parcelle=<?= $pc['id'] ?>">Aller à cette étape →</a></p>
<?php else: ?>
    <table class="liste">
      <thead><tr><th>Planche</th><th>Culture</th><th>Strate</th><th>Mise en place</th><th>Récolte</th><th class="nombre">Plants</th></tr></thead>
      <tbody>
<?php foreach ($pc['cultures'] as $c): ?>
        <tr>
          <td><?= $c['planche'] !== null ? (int) $c['planche'] : '<span class="vide">pas placée</span>' ?></td>
          <td><?= htmlspecialchars((string) $c['espece'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars(Cultures::STRATES[(string) $c['strate']] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($dateEnMots($c['date_semis_repiquage']) ?? '', ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($dateEnMots($c['date_recolte']) ?? '', ENT_QUOTES, 'UTF-8') ?></td>
          <td class="nombre"><?= $c['nb_plants'] !== null ? (int) $c['nb_plants'] : '' ?></td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
<?php endif; ?>
    </div>
<?php else: ?>
    <div class="bloc">
    <h3>Le scénario</h3>
<?php if ($pc['scenario'] === null): ?>
    <p class="vide">Pas encore renseigné. Cela se fait à l’étape « Scénario » de la Conception.</p>
    <p class="a-faire lien-etape"><a href="conception-scenario.php?parcelle=<?= $pc['id'] ?>">Aller à cette étape →</a></p>
<?php elseif (isset($pc['scenario']['frise'])): ?>
    <figure>
      <?= $pc['scenario']['frise'] ?>
      <figcaption>Chaque végétal, de son entrée à sa sortie du scénario ; hachuré après son retrait. Contour épais (rouge) :
        une production ; en tirets (orange) : production et service ; trait fin (vert) : un service ; en pointillés (bleu) :
        une couleur choisie à la main.</figcaption>
    </figure>
<?php else: ?>
    <dl class="reponses">
<?php foreach ($pc['scenario']['strates'] as $code => $nomStrate): ?>
<?php if ($pc['scenario']['groupes'][$code] !== []): ?>
      <div>
        <dt><?= htmlspecialchars($nomStrate, ENT_QUOTES, 'UTF-8') ?></dt>
        <dd><?= htmlspecialchars(implode(' · ', array_map(static fn (array $l): string => (string) $l['espece'] . ($l['nb_pieds'] === null ? '' : ' ×' . (int) $l['nb_pieds']), $pc['scenario']['groupes'][$code])), ENT_QUOTES, 'UTF-8') ?></dd>
      </div>
<?php endif; ?>
<?php endforeach; ?>
    </dl>
<?php endif; ?>
    </div>
<?php endif; ?>

    <div class="bloc">
    <h3>Ce qu’il faut se procurer</h3>
<?php if ($pc['materiel'] === []): ?>
    <p class="vide">Aucun végétal n’est encore placé. La liste se déduit <?= $pc['potager'] ? 'du plan de culture' : 'du scénario et des lignes du plan' ?>.</p>
<?php else: ?>
    <table class="liste">
      <thead><tr><th>Végétal</th><th>Origine</th><th class="nombre">Quantité</th></tr></thead>
      <tbody>
<?php foreach ($pc['materiel'] as $r): ?>
        <tr>
          <td><?= htmlspecialchars($r['espece'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars(Materiel::ORIGINES[$r['origine']] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
          <td class="nombre"><?= $quantite($r) ?></td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
<?php endif; ?>
    </div>

    <div class="bloc">
    <h3>L’eau</h3>
<?php if ($pc['eau'] === null): ?>
    <p class="vide">Le besoin en eau se calcule sur la surface : la parcelle n’a pas encore de dimensions.</p>
<?php else: $eau = $pc['eau']; $f = $eau['freq']; ?>
    <table class="liste">
      <thead><tr><th>Sur <?= DessinsDossier::nombre($eau['surface'], 0) ?> m², si le sol est…</th><th class="nombre">Par an</th><th class="nombre">Mare creusée</th></tr></thead>
      <tbody>
<?php foreach ($eau['besoins'] as $b): ?>
        <tr>
          <td><?= htmlspecialchars($b['titre'], ENT_QUOTES, 'UTF-8') ?></td>
<?php if ($b['m3_an'] !== null): ?>
          <td class="nombre"><?= DessinsDossier::nombre($b['m3_an'], 1) ?> m³</td><td class="nombre"><?= DessinsDossier::nombre($b['mare_m3'], 1) ?> m³</td>
<?php else: ?>
          <td class="nombre" colspan="2">moins, non chiffré</td>
<?php endif; ?>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
    <p class="note-dossier">En pointe d’été : <?= DessinsDossier::nombre($eau['pointe']['m3_jour'], 2) ?> m³ par jour (5 mm par jour). Ce sont des
       ordres de grandeur pour dimensionner, tirés de Lopez (maraîchage d’annuelles).</p>
<?php if ($eau['releve'] === null): ?>
    <p class="note-dossier">Aucun test de sol n’est noté : le rythme d’arrosage reste à lire. L’été demande
       <?= DessinsDossier::nombre($f['semaines'][0]['mm'], 0) ?> mm par semaine en moyenne, et <?= DessinsDossier::nombre($f['semaines'][1]['mm'], 0) ?> mm en pointe.</p>
<?php else: ?>
    <p class="note-dossier">Test de sol du <?= htmlspecialchars($dateEnMots((string) $eau['releve']['date']) ?? '', ENT_QUOTES, 'UTF-8') ?><?= trim((string) $eau['releve']['point']) !== '' ? ', ' . htmlspecialchars((string) $eau['releve']['point'], ENT_QUOTES, 'UTF-8') : '' ?>.
<?php if ($f['classe'] !== null): ?>
       Infiltration de <?= DessinsDossier::nombre((float) $eau['releve']['infiltration_mm_h'], 1) ?> mm/h : <?= htmlspecialchars($f['classe']['titre'], ENT_QUOTES, 'UTF-8') ?>, comme un sol de type <?= htmlspecialchars($f['classe']['texture'], ENT_QUOTES, 'UTF-8') ?>.
<?php endif; ?>
<?php if ($f['ressuyage'] !== null): ?>
       Ressuyage <?= htmlspecialchars($f['ressuyage']['titre'], ENT_QUOTES, 'UTF-8') ?> : <?= htmlspecialchars($f['ressuyage']['lecture'], ENT_QUOTES, 'UTF-8') ?>.
<?php endif; ?>
<?php foreach ($f['semaines'] as $sem): ?>
       <?= htmlspecialchars($sem['titre'], ENT_QUOTES, 'UTF-8') ?> : <?= DessinsDossier::nombre($sem['mm'], 0) ?> mm.
<?php endforeach; ?>
<?php if (($f['classe']['famille'] ?? '') === 'drainant'): ?>
       Ce sol boit vite : il demande des passages plus courts et plus fréquents.
<?php elseif (($f['classe']['famille'] ?? '') === 'tampon'): ?>
       Ce sol fait tampon : il supporte un arrosage long et espacé, en plusieurs cycles courts.
<?php endif; ?>
    </p>
<?php endif; ?>
<?php endif; ?>
    </div>
  </section>
<?php endforeach; ?>

  <section class="feuille a-part" id="chapitre-materiel">
    <p class="phase">Conception</p>
    <h2>Matériel et eau, tout le projet</h2>
<?php if ($recapProjet === []): ?>
    <p class="vide">Aucun végétal n’est encore placé sur les parcelles. Cela se fait aux étapes « Scénario » et « Potager » de la Conception.</p>
    <p class="a-faire lien-etape"><a href="conception-materiel-et-eau.php">Aller à l’étape « Matériel et eau » →</a></p>
<?php else: ?>
    <p class="sous-titre"><?= implode(' · ', array_map(static fn (string $cle): string => htmlspecialchars(Materiel::ORIGINES[$cle], ENT_QUOTES, 'UTF-8') . ' : '
        . $syntheseProjet[$cle]['especes'] . ' végéta' . ($syntheseProjet[$cle]['especes'] > 1 ? 'ux' : 'l')
        . ($syntheseProjet[$cle]['plants'] > 0 ? ', ' . ($syntheseProjet[$cle]['incomplet'] ? 'au moins ' : '') . DessinsDossier::nombre((float) $syntheseProjet[$cle]['plants'], 0) . ' plants' : ''),
        array_values(array_filter(array_keys(Materiel::ORIGINES), static fn (string $cle): bool => isset($syntheseProjet[$cle]))))) ?>.<?= $aSacrifier > 0
        ? ' Dont ' . DessinsDossier::nombre((float) $aSacrifier, 0) . ' plants de biomasse, à sacrifier à la perturbation.' : '' ?></p>
    <table class="liste">
      <thead><tr><th>Végétal</th><th>Origine</th><th>Parcelles</th><th class="nombre">Quantité</th></tr></thead>
      <tbody>
<?php foreach ($recapProjet as $r): ?>
        <tr>
          <td><?= htmlspecialchars($r['espece'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars(Materiel::ORIGINES[$r['origine']] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars(implode(', ', $r['parcelles']), ENT_QUOTES, 'UTF-8') ?></td>
          <td class="nombre"><?= $quantite($r) ?></td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
    <p class="note-dossier">Sur la ligne centrale d’une parcelle pérenne, une quantité « estimée » se compte en série : les
       productions à leur espacement de production, les plantes de service dans les cases qui restent. Ailleurs, elle
       compte une ligne de plants par planche ou par bande. Une quantité saisie la remplace. Les plants posés sur les
       lignes du plan sont comptés tels quels.</p>
<?php endif; ?>

    <div class="bloc">
    <h3>L’eau du site</h3>
    <dl class="reponses">
      <div>
        <dt>Ressource en eau</dt>
        <dd><?= $ressource === '' ? '<span class="vide">pas encore renseigné</span>'
            : htmlspecialchars(BesoinEau::RESSOURCES[$ressource] ?? $ressource, ENT_QUOTES, 'UTF-8') . ($ressourceDetail !== '' ? ', ' . htmlspecialchars($ressourceDetail, ENT_QUOTES, 'UTF-8') : '') ?></dd>
      </div>
<?php if (isset(BesoinEau::CONSEIL_RESSOURCE[$ressource])): ?>
      <div>
        <dt>Ce qu’elle change</dt>
        <dd><?= htmlspecialchars(BesoinEau::CONSEIL_RESSOURCE[$ressource], ENT_QUOTES, 'UTF-8') ?></dd>
      </div>
<?php endif; ?>
<?php if ($surfaceProjet > 0): ?>
      <div>
        <dt>Besoin annuel, sur <?= DessinsDossier::nombre($surfaceProjet, 0) ?> m² arrosés</dt>
        <dd><?= implode(' ; ', array_map(static fn (array $b): string => htmlspecialchars(mb_strtolower(mb_substr($b['titre'], 0, 1)) . mb_substr($b['titre'], 1), ENT_QUOTES, 'UTF-8') . ' : '
            . ($b['m3_an'] !== null ? DessinsDossier::nombre($b['m3_an'], 1) . ' m³ (mare creusée de ' . DessinsDossier::nombre($b['mare_m3'], 1) . ' m³)' : 'moins, non chiffré'), $besoinsProjet)) ?>.</dd>
      </div>
<?php endif; ?>
    </dl>
<?php if ($sansDimensions !== []): ?>
    <p class="note-dossier">Parcelles sans dimensions, non comptées : <?= htmlspecialchars(implode(', ', $sansDimensions), ENT_QUOTES, 'UTF-8') ?>.</p>
<?php endif; ?>
<?php if ($ressource === ''): ?>
    <p class="a-faire lien-etape"><a href="conception-materiel-et-eau.php?onglet=eau">Noter la ressource en eau →</a></p>
<?php endif; ?>
    </div>
  </section>

  <section class="feuille" id="chapitre-calendrier">
    <p class="phase">Conception</p>
    <h2>Le calendrier</h2>
<?php if ($calendrier['reponses'] === []): ?>
    <p class="vide">Pas encore renseigné. Cela se fait à l’étape « Calendrier » de la Conception.</p>
    <p class="a-faire lien-etape"><a href="conception-calendrier.php">Aller à cette étape →</a></p>
<?php else: ?>
    <p class="provenance">Réponses de <?= htmlspecialchars((string) $calendrier['nom'], ENT_QUOTES, 'UTF-8') ?>.</p>
    <dl class="reponses">
<?php foreach ($saisons as $cle => $saison): ?>
      <div>
        <dt><?= $saison ?></dt>
        <dd><?= isset($calendrier['reponses'][$cle]) ? $h($calendrier['reponses'][$cle]) : '<span class="vide">pas encore renseigné</span>' ?></dd>
      </div>
<?php endforeach; ?>
    </dl>
<?php endif; ?>
  </section>

<?php foreach (['realisation' => ['Réalisation', $chapitresRealisation, $realisation], 'suivi' => ['Suivi', $chapitresSuivi, $suivi]] as $cle => [$phase, $liste, $source]): ?>
  <h2 class="titre-phase" id="phase-<?= $cle ?>"><?= $phase ?></h2>
<?php foreach ($liste as $id => [$titre, $etape, $lignes]): ?>
<?php if ($lignes === null): ?>
  <section class="feuille journal" id="chapitre-<?= $id ?>">
    <p class="phase"><?= $phase ?></p>
    <h2><?= htmlspecialchars($titre, ENT_QUOTES, 'UTF-8') ?></h2>
    <p class="sous-titre">Le journal des perturbations et les constats du saut du chat, parcelle par parcelle, dans l’ordre des jours.</p>
<?php if ($journaux === []): ?>
    <p class="vide">Aucune parcelle n’est encore ouverte. Cela se fait à l’étape « La palette » de la Conception.</p>
<?php endif; ?>
<?php foreach ($journaux as $j): ?>
    <div class="bloc">
      <h3>Parcelle <?= htmlspecialchars($j['code'], ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($j['nom'], ENT_QUOTES, 'UTF-8') ?></h3>
<?php if ($j['interventions'] === [] && $j['constats'] === []): ?>
      <p class="vide">Rien n’est encore noté. Cela se fait aux étapes « Perturbation » et « Saut du chat » du Suivi.</p>
      <p class="a-faire lien-etape"><a href="suivi-perturbation.php?parcelle=<?= $j['id'] ?>">Aller au journal →</a></p>
<?php endif; ?>
<?php if ($j['interventions'] !== []): ?>
      <p class="sous-titre">Les perturbations</p>
      <table class="liste serre">
        <thead><tr><th>Date</th><th>Geste</th><th>Végétaux</th><th>État des plantes</th><th>Eau</th><th>Biomasse</th><th>Constaté ensuite</th></tr></thead>
        <tbody>
<?php foreach ($j['interventions'] as $i): ?>
          <tr>
            <td><?= htmlspecialchars($dateEnMots((string) $i['date']) ?? '', ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars(Perturbation::libelle(Perturbation::TYPES, $i['type']), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars(Perturbation::joindre(Perturbation::vegetaux($i['especes'] ?? '')) . ((string) ($i['zone'] ?? '') !== '' ? ' (' . $i['zone'] . ')' : ''), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars(Perturbation::libelle(Perturbation::STADES, $i['stade_phenologique']), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars(Perturbation::libelle(Perturbation::EAUX, $i['conditions_eau']), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars(Perturbation::libelle(Perturbation::BIOMASSES, $i['biomasse_deposee']), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= $h((string) ($i['resultat_constate'] ?? '')) ?></td>
          </tr>
<?php endforeach; ?>
        </tbody>
      </table>
<?php endif; ?>
<?php if ($j['constats'] !== []): ?>
      <p class="sous-titre">Les constats du saut du chat</p>
      <table class="liste serre">
        <thead><tr><th>Date</th><th>Visible</th><th>Plante</th><th>Pousse</th><th>Hauteur</th><th>Biomasse</th><th>Sol</th><th>Adventices</th><th>Notes</th></tr></thead>
        <tbody>
<?php foreach ($j['constats'] as $c): ?>
          <tr>
            <td><?= htmlspecialchars($dateEnMots((string) $c['date']) ?? '', ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= $c['visible'] === 'oui' ? 'Oui' : 'Pas encore' ?></td>
            <td><?= htmlspecialchars((string) ($c['vegetal'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= $c['pousse_cm'] !== null ? htmlspecialchars(DessinsDossier::nombre((float) $c['pousse_cm'], 1) . ' cm en ' . (int) $c['sur_jours'] . ' j', ENT_QUOTES, 'UTF-8') : '' ?></td>
            <td><?= $c['hauteur_cm'] !== null ? htmlspecialchars(SautDuChat::hauteurDite((float) $c['hauteur_cm'], $c['adulte']), ENT_QUOTES, 'UTF-8') : '' ?></td>
            <td><?= htmlspecialchars(SautDuChat::BIOMASSES[$c['biomasse'] ?? ''] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars(SautDuChat::SOLS[$c['sol'] ?? ''] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars(SautDuChat::ADVENTICES[$c['adventices'] ?? ''] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= $h((string) ($c['notes'] ?? '')) ?></td>
          </tr>
<?php endforeach; ?>
        </tbody>
      </table>
<?php endif; ?>
    </div>
<?php endforeach; ?>
  </section>
<?php else: ?>
<?php $renseignees = array_filter($lignes, static fn (?string $v): bool => $v !== null); ?>
  <section class="feuille" id="chapitre-<?= $id ?>">
    <p class="phase"><?= $phase ?></p>
    <h2><?= htmlspecialchars($titre, ENT_QUOTES, 'UTF-8') ?></h2>
<?php if ($renseignees === []): ?>
    <p class="vide">Pas encore renseigné. Cela se fait à l’étape « <?= htmlspecialchars($titre, ENT_QUOTES, 'UTF-8') ?> » <?= $cle === 'suivi' ? 'du Suivi' : 'de la Réalisation' ?>.</p>
    <p class="a-faire lien-etape"><a href="<?= $etape ?>">Aller à cette étape →</a></p>
<?php else: ?>
<?php if ($source['nom'] !== null): ?>
    <p class="provenance">Réponses de <?= htmlspecialchars($source['nom'], ENT_QUOTES, 'UTF-8') ?>.</p>
<?php endif; ?>
    <dl class="reponses">
<?php foreach ($lignes as $question => $reponse): ?>
      <div>
        <dt><?= htmlspecialchars($question, ENT_QUOTES, 'UTF-8') ?></dt>
        <dd><?= $reponse !== null ? $h($reponse) : '<span class="vide">pas encore renseigné</span>' ?></dd>
      </div>
<?php endforeach; ?>
    </dl>
<?php endif; ?>
  </section>
<?php endif; ?>
<?php endforeach; ?>
<?php endforeach; ?>

  <section class="feuille" id="chapitre-participants">
    <p class="phase">Le projet</p>
    <h2>Les participants</h2>
    <p class="sous-titre">Les personnes qui ont accès au projet dans l’atelier.</p>
    <table class="liste">
      <thead><tr><th>Nom</th><th>Rôle</th><th>Depuis le</th></tr></thead>
      <tbody>
<?php foreach ($participants as $m): ?>
        <tr>
          <td><?= $m['nom'] !== '' ? htmlspecialchars($m['nom'], ENT_QUOTES, 'UTF-8') : '<span class="vide">sans nom</span>' ?></td>
          <td><?= htmlspecialchars($m['role'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($dateEnMots($m['depuis']) ?? '', ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
  </section>
</main>

<script>
  document.getElementById('imprimer').addEventListener('click', function(){ window.print(); });
  // Revenir là d'où l'on vient, s'il s'agit de l'atelier ; sinon le lien mène au plan des parcelles.
  document.getElementById('retour').addEventListener('click', function(e){
    if (document.referrer && new URL(document.referrer).origin === location.origin && history.length > 1){
      e.preventDefault(); history.back();
    }
  });
</script>
</body>
</html>
