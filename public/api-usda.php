<?php

declare(strict_types=1);

/**
 * La zone climatique USDA d'un point, estimée depuis l'historique météo.
 *
 * Appelée par `definition-site.php` (bouton « Estimer »). Lecture seule :
 * rien n'est enregistré ici — la zone n'entre dans le projet qu'avec le
 * reste du formulaire, et la personne peut la corriger avant. Le point
 * vient du formulaire (pas de la base) : il n'est peut-être pas encore
 * enregistré. Fermée aux anonymes et protégée par le jeton, comme
 * `api-cadastre.php` — sans quoi ce serait un relais gratuit vers un
 * service tiers.
 */

require __DIR__ . '/../src/autoload.php';
require __DIR__ . '/../src/Configuration.php';
require __DIR__ . '/../src/Database.php';

use Syntropia\Web\Authentification;
use Syntropia\Web\Sites;
use Syntropia\Web\ZoneUsda;

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
    repondre(403, ['ok' => false, 'message' => 'Le formulaire a expiré. Rechargez la page.']);
}

$siteId = (int) ($_POST['site_id'] ?? 0);
if ($siteId <= 0 || (new Sites())->roleSur($siteId, (int) $auth->compteId()) === null) {
    repondre(403, ['ok' => false, 'message' => 'Ce site ne vous est pas ouvert.']);
}

$brut = static fn (string $cle): string => str_replace(',', '.', trim((string) ($_POST[$cle] ?? '')));
$latitude = $brut('latitude');
$longitude = $brut('longitude');
if (!is_numeric($latitude) || !is_numeric($longitude)
    || abs((float) $latitude) > 90 || abs((float) $longitude) > 180) {
    repondre(400, ['ok' => false, 'message' => "Posez d'abord le point du site sur la carte. "
        . 'La zone se calcule depuis sa position.']);
}

$resultat = ZoneUsda::estimer((float) $latitude, (float) $longitude);
repondre(200, ['ok' => $resultat['zone'] !== null] + $resultat);
