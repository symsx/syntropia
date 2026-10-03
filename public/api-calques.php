<?php

declare(strict_types=1);

/**
 * Enregistrer ce qu'on dessine sur la carte — ajouter, modifier, retirer.
 *
 * Porté de `application/public/api-calques.php` (dépôt de référence, 83
 * lignes), sans changement de fond — `Vue::param()` n'existe pas côté
 * cible, remplacé par un accès direct à `$_POST` comme le reste du
 * dépôt. Appelée par `assets/js/calques.js` (Phase 5, à venir) — pour
 * l'instant testable au `curl` seul. Fermée aux anonymes, protégée par
 * le jeton, et vérifiant que l'on peut écrire sur ce site : un tracé est
 * une donnée du projet comme une autre.
 *
 * Rien ici n'est indispensable : la carte se lira sans script, et les
 * éléments seront listés dans la page. Ce point d'entrée ne sert qu'à
 * écrire.
 */

require __DIR__ . '/../src/autoload.php';
require __DIR__ . '/../src/Configuration.php';
require __DIR__ . '/../src/Database.php';

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
    repondre(403, ['ok' => false, 'message' => 'Le formulaire a expiré. Rechargez la page.']);
}

$siteId = (int) ($_POST['site_id'] ?? 0);
$sites = new Sites();
if ($siteId <= 0 || !$sites->peutEcrireSur($siteId, (int) $auth->compteId())) {
    repondre(403, ['ok' => false, 'message' => 'Vous ne pouvez pas écrire sur ce site.']);
}

$calques = new Calques();
$action = (string) ($_POST['action'] ?? '');

if ($action === 'ajouter') {
    $resultat = $calques->ajouter($siteId, $_POST);
    if ($resultat['id'] === null) {
        repondre(400, ['ok' => false, 'message' => implode(' ', $resultat['erreurs']),
            'erreurs' => $resultat['erreurs']]);
    }
    $element = $calques->parId($resultat['id'], $siteId);
    repondre(200, ['ok' => true, 'id' => $resultat['id'], 'element' => $element,
        'message' => 'Tracé enregistré.']);
}

if ($action === 'modifier') {
    $id = (int) ($_POST['id'] ?? 0);
    $erreurs = $calques->modifier($id, $siteId, $_POST);
    if ($erreurs !== []) {
        repondre(400, ['ok' => false, 'message' => implode(' ', $erreurs), 'erreurs' => $erreurs]);
    }
    repondre(200, ['ok' => true, 'id' => $id, 'element' => $calques->parId($id, $siteId),
        'message' => 'Tracé modifié.']);
}

if ($action === 'retirer') {
    $id = (int) ($_POST['id'] ?? 0);
    $calques->retirer($id, $siteId);
    repondre(200, ['ok' => true, 'id' => $id, 'message' => 'Tracé retiré.']);
}

repondre(400, ['ok' => false, 'message' => 'Action inconnue.']);
