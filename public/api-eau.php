<?php

declare(strict_types=1);

/**
 * Chercher l'eau d'OpenStreetMap, et la poser sur le calque.
 *
 * Porté de `application/public/api-eau.php` (dépôt de référence, 113
 * lignes), sans changement de fond — `Vue::param()` n'existe pas côté
 * cible, remplacé par un accès direct à `$_POST` comme le reste du dépôt.
 * Appelée par `assets/js/calques.js` quand on presse le bouton, **une fois
 * la carte cadrée** : l'emprise envoyée est celle du terrain, pas celle
 * qu'on voit. Un clic, un appel — Overpass est servi par des bénévoles.
 *
 * ⚠ **Ce qui est posé devient une donnée du projet, modifiable et
 * complétable** : OpenStreetMap est une carte faite par des gens, et le
 * fossé qu'on a creusé l'an dernier n'y est pas.
 */

require __DIR__ . '/../src/autoload.php';
require __DIR__ . '/../src/Configuration.php';
require __DIR__ . '/../src/Database.php';

use Syntropia\Web\Authentification;
use Syntropia\Web\Calques;
use Syntropia\Web\Eau;
use Syntropia\Web\Sites;

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

/** @param array<string, mixed> $corps */
function repondre(int $code, array $corps): never
{
    http_response_code($code);
    echo json_encode($corps, JSON_UNESCAPED_UNICODE);
    exit;
}

Authentification::demarrer($_SERVER);
$auth = new Authentification();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    repondre(405, ['ok' => false, 'message' => 'Méthode non permise.']);
}
if (!$auth->estConnecte()) {
    repondre(403, ['ok' => false, 'message' => 'Non autorisé.']);
}
if (!$auth->jetonValide($_POST['csrf'] ?? null)) {
    repondre(403, ['ok' => false, 'message' => 'Formulaire expiré — rechargez la page.']);
}

$siteId = (int) ($_POST['site_id'] ?? 0);
$sites = new Sites();
if ($siteId <= 0 || !$sites->peutEcrireSur($siteId, (int) $auth->compteId())) {
    repondre(403, ['ok' => false, 'message' => 'Vous ne pouvez pas écrire sur ce site.']);
}

/* L'EMPRISE VIENT DES LIMITES DU TERRAIN, NON DE LA VUE.
 *
 * Cadrer large ramènerait une rivière à vingt kilomètres. Le prérequis
 * est donc dur : sans limites tracées, on ne cherche pas. */
$calques = new Calques();
$emprise = $calques->empriseDuTerrain($siteId, Eau::MARGE_M);
if ($emprise === null) {
    repondre(200, ['ok' => false, 'message' => "Tracez d'abord les limites du terrain "
        . "— à la main sur le calque du fond, ou en suivant le cadastre. "
        . "C'est elles qui bornent la recherche : sans elles, je ramènerais "
        . "la rivière du département."]);
}

$resultat = Eau::dansEmprise([$emprise['sud'], $emprise['ouest']],
    [$emprise['nord'], $emprise['est']]);
if ($resultat['traces'] === []) {
    repondre(200, ['ok' => true, 'elements' => [], 'message' => $resultat['message']]);
}

// Ce qui est déjà posé ne se repose pas : l'identifiant OpenStreetMap sert
// de garde, pour qu'un second clic n'empile pas les mêmes rivières.
$connus = [];
foreach ($calques->dUnSite($siteId) as $element) {
    $osm = $element['proprietes']['osm'] ?? null;
    if (is_string($osm)) {
        $connus[$osm] = true;
    }
}

$poses = [];
$deja = 0;
foreach ($resultat['traces'] as $trace) {
    if (isset($connus[$trace['proprietes']['osm']])) {
        $deja++;
        continue;
    }
    $ajout = $calques->ajouter($siteId, [
        'calque' => 'eau', 'type' => $trace['type'], 'libelle' => $trace['libelle'],
        'geometrie' => json_encode($trace['points']),
        'proprietes' => json_encode($trace['proprietes']),
    ]);
    if ($ajout['id'] !== null) {
        $element = $calques->parId($ajout['id'], $siteId);
        if ($element !== null) {
            $poses[] = $element;
        }
    }
}

$message = count($poses) . ' élément' . (count($poses) > 1 ? 's posés' : ' posé')
    . ' sur le calque de l\'eau'
    . ($deja > 0 ? ', et ' . $deja . ' qui y ' . ($deja > 1 ? 'étaient' : 'était') . ' déjà' : '')
    . '. À vous de compléter : un fossé récent n\'est jamais sur la carte.';

repondre(200, ['ok' => true, 'elements' => $poses, 'message' => $message]);
