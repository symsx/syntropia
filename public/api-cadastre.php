<?php

declare(strict_types=1);

/**
 * La couche cadastrale de la carte — les parcelles d'une emprise.
 *
 * Porté de `application/public/api-cadastre.php` (dépôt de référence, 77
 * lignes), sans changement de fond — `Vue::param()` n'existe pas côté
 * cible, remplacé par un accès direct à `$_POST` comme le reste du dépôt.
 * Appelée par `assets/js/calques.js` quand on allume le calque « cadastre »,
 * et à chaque fois que la vue change. **C'est un repère pour tracer**, non
 * une donnée du projet : rien de ce qui passe ici n'est enregistré.
 *
 * L'appel à l'IGN part du serveur, jamais du navigateur du visiteur. Fermée
 * aux anonymes et protégée par le jeton, parce qu'un point d'entrée ouvert
 * deviendrait un relais gratuit vers un service public. Lecture seule
 * suffit — `roleSur()`, pas `peutEcrireSur()` : on regarde une carte, on
 * n'écrit rien.
 */

require __DIR__ . '/../src/autoload.php';
require __DIR__ . '/../src/Configuration.php';
require __DIR__ . '/../src/Database.php';

use Syntropia\Web\Authentification;
use Syntropia\Web\Cadastre;
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
if ($siteId <= 0 || $sites->roleSur($siteId, (int) $auth->compteId()) === null) {
    repondre(403, ['ok' => false, 'message' => 'Ce site ne vous est pas ouvert.']);
}

$nombre = static fn (string $cle): ?float =>
    is_numeric($_POST[$cle] ?? null) ? (float) $_POST[$cle] : null;

$sud = $nombre('sud');
$ouest = $nombre('ouest');
$nord = $nombre('nord');
$est = $nombre('est');
if ($sud === null || $ouest === null || $nord === null || $est === null) {
    repondre(400, ['ok' => false, 'message' => 'Emprise incomplète.']);
}

$resultat = Cadastre::dansEmprise([$sud, $ouest], [$nord, $est]);

// Seulement ce que le dessin demande : le contour, le nom, la surface. Les
// identifiants complets restent au serveur tant qu'on ne pose rien.
$parcelles = array_map(static fn (array $p): array => [
    'libelle' => Cadastre::libelle($p),
    'contenance_m2' => $p['contenance_m2'],
    'points' => $p['points'],
], $resultat['parcelles']);

repondre(200, ['ok' => true, 'parcelles' => $parcelles, 'message' => $resultat['message']]);
