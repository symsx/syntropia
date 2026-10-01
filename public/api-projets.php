<?php

declare(strict_types=1);

/**
 * Les projets d'un compte, et le choix du projet actif.
 *
 * GET : la liste (`projets`), l'actif (`actif`) et le jeton — appelée par
 * `assets/js/gabarit.js` sur chaque page connectée pour bâtir le sélecteur
 * du panneau gauche, sans qu'aucune page ait à le porter. POST : change le
 * projet actif — jeton exigé, appartenance revérifiée par `Sites::activer()`.
 * Rien de ce qui passe ici n'ouvre un droit : le cookie ne retient qu'un
 * identifiant.
 */

require __DIR__ . '/../src/autoload.php';
require __DIR__ . '/../src/Configuration.php';
require __DIR__ . '/../src/Database.php';

use Syntropia\Web\Authentification;
use Syntropia\Web\Sites;

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

/** @param array<string, mixed> $corps */
function repondre(int $code, array $corps): never
{
    http_response_code($code);
    echo json_encode($corps, JSON_UNESCAPED_UNICODE);
    exit;
}

Authentification::demarrer($_SERVER);
$auth = new Authentification();
if (!$auth->estConnecte()) {
    repondre(403, ['ok' => false, 'message' => 'Non autorisé.']);
}
$compteId = (int) $auth->compteId();
$sites = new Sites();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!$auth->jetonValide($_POST['csrf'] ?? null)) {
        repondre(403, ['ok' => false, 'message' => 'Formulaire expiré — rechargez la page.']);
    }
    if (!$sites->activer((int) ($_POST['site_id'] ?? 0), $compteId)) {
        repondre(403, ['ok' => false, 'message' => "Ce projet ne vous est pas ouvert."]);
    }
    repondre(200, ['ok' => true]);
}

$actif = $sites->pourCompte($compteId);
repondre(200, [
    'ok' => true,
    'csrf' => $auth->jeton(),
    // Le déploiement en ligne : gabarit.js le compare à celui de son chargement, et prévient s'il a changé.
    'deploiement' => Syntropia\Version::marqueDeploiement(),
    'actif' => $actif === null ? null : (int) $actif['id'],
    'projets' => array_map(static fn (array $s): array => [
        'id' => (int) $s['id'],
        'nom' => (string) $s['nom'],
        'role' => (string) $s['role'],
    ], $sites->deCompte($compteId)),
]);
