<?php

declare(strict_types=1);

/**
 * Relever le relief — une grille d'altitudes IGN sur l'emprise du fond
 * déjà tracé, posée sur le calque « relief ».
 *
 * Nouveau point d'entrée propre à cette refonte : le dépôt de référence
 * relève sa grille sur une page séparée (`relief.php`, avec vue 3D et
 * côté/pas saisis à la main — tous deux hors périmètre de cette phase,
 * voir CLAUDE.md § Point de reprise). Ici, comme pour l'eau (Phase 7), le
 * carré à relever se déduit de l'emprise du terrain déjà tracé
 * (`Calques::empriseDuTerrain()`) plutôt que d'être saisi : rien à régler,
 * la carte sait déjà où chercher.
 *
 * **Un seul relevé de relief par site** : le suivant remplace le
 * précédent, sinon des grilles qu'on ne saurait plus distinguer
 * s'empileraient — même règle que `relief.php`. Les courbes de niveau ne
 * sont pas stockées : `conception-etude-site.php` les recalcule à chaque
 * chargement depuis les altitudes brutes (`Relief::courbesGeo()`), pour
 * rester à jour si le tracé du fond change.
 *
 * Fermée aux anonymes, protégée par le jeton, et vérifie qu'on peut
 * écrire sur ce site : une grille relevée est une donnée du projet.
 */

require __DIR__ . '/../src/autoload.php';
require __DIR__ . '/../src/Configuration.php';
require __DIR__ . '/../src/Database.php';

use Syntropia\Relief;
use Syntropia\Web\Altimetrie;
use Syntropia\Web\Authentification;
use Syntropia\Web\Calques;
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

$calques = new Calques();
$emprise = $calques->empriseDuTerrain($siteId);
if ($emprise === null) {
    repondre(200, ['ok' => false, 'message' => "Tracez d'abord les limites du terrain "
        . "— à la main sur le calque du fond, ou en suivant le cadastre. "
        . "C'est elles qui bornent le relevé."]);
}

$sud = $emprise['sud']; $ouest = $emprise['ouest'];
$nord = $emprise['nord']; $est = $emprise['est'];
$latitude = ($sud + $nord) / 2;
$longitude = ($ouest + $est) / 2;
$mLat = 111320.0;
$mLon = 111320.0 * cos(deg2rad($latitude));
$coteM = max(($nord - $sud) * $mLat, ($est - $ouest) * $mLon);
// Viser une grille d'une quarantaine de points de côté (≈1 600 points,
// sous le plafond de 2 000) : assez fin pour des courbes lisibles, assez
// large pour rester en un petit nombre d'appels IGN.
$pasM = max(Altimetrie::PAS_MINIMAL_M, $coteM / 40);

$resultat = Altimetrie::grille($latitude, $longitude, max(20.0, $coteM), $pasM);
if ($resultat['grille'] === null) {
    repondre(200, ['ok' => false, 'message' => $resultat['message']]);
}

$g = $resultat['grille'];
$cotes = ($g['cote'] - 1) * $g['pas'];
// La géométrie porte l'EMPRISE — deux coins —, non les points : c'est
// elle qui se dessine sur la carte. Les altitudes vivent dans les
// propriétés, où elles ne gênent aucun tracé.
$geometrie = [
    [round($g['sud_ouest'][0], 6), round($g['sud_ouest'][1], 6)],
    [round($g['sud_ouest'][0] + $cotes / $mLat, 6),
     round($g['sud_ouest'][1] + $cotes / $mLon, 6)],
];

foreach ($calques->dUnSite($siteId) as $ancien) {
    if ($ancien['calque'] === 'relief' && $ancien['type'] === 'grille') {
        $calques->retirer((int) $ancien['id'], $siteId);
    }
}

$ajout = $calques->ajouter($siteId, [
    'calque' => 'relief', 'type' => 'grille',
    'libelle' => 'Relief IGN — pas de ' . $g['pas'] . ' m',
    'geometrie' => json_encode($geometrie),
    'proprietes' => json_encode(['cote' => $g['cote'], 'pas' => $g['pas'],
        'z' => $g['z'], 'releve_le' => date('Y-m-d')]),
]);
if ($ajout['id'] === null) {
    repondre(400, ['ok' => false, 'message' => implode(' ', $ajout['erreurs'])]);
}

$element = $calques->parId($ajout['id'], $siteId);
// Les courbes ne se stockent pas — elles se recalculent ici, comme le
// fera `conception-etude-site.php` à chaque chargement de la page
// (même logique que la référence sur `carte.php`), pour que le relevé
// tout juste posé se dessine avec ses courbes sans attendre un rechargement.
if ($element !== null && $element['geometrie'] !== []) {
    $element['proprietes']['courbes'] = Relief::courbesGeo(
        $element['proprietes']['z'], (int) $element['proprietes']['cote'],
        (float) $element['proprietes']['pas'], $element['geometrie'][0]);
}
repondre(200, ['ok' => true, 'element' => $element, 'message' => $resultat['message']]);
