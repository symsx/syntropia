<?php

declare(strict_types=1);

/**
 * Créer ou modifier un compte, en ligne de commande.
 *
 * Adapté de `application/bin/compte.php` (dépôt de référence), sans
 * changement de fond. Le calcul de l'empreinte reste du côté PHP :
 * dupliquer `password_hash` en Python ferait deux implémentations à
 * garder d'accord, dont une qui n'aurait jamais servi à vérifier quoi
 * que ce soit.
 *
 * Appelé par `bin/dev.py compte`, qui lit le mot de passe sans
 * l'afficher. C'est la voie du PREMIER compte : `inscription.php`
 * exige une invitation, et la première invitation n'a personne pour
 * l'ouvrir.
 *
 *   php bin/compte.php <identifiant> <nom affiché> <rôle>
 *   (le mot de passe est lu sur l'entrée standard)
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../src/autoload.php';
require __DIR__ . '/../src/Configuration.php';
require __DIR__ . '/../src/Database.php';

use Syntropia\Role;
use Syntropia\Web\Comptes;

[$identifiant, $nom, $role] = [$argv[1] ?? '', $argv[2] ?? '', $argv[3] ?? 'administrateur'];
$motDePasse = rtrim((string) fgets(STDIN), "\r\n");

if ($identifiant === '' || $nom === '' || $motDePasse === '') {
    fwrite(STDERR, "identifiant, nom et mot de passe sont requis\n");
    exit(2);
}
$roleChoisi = Role::tryFrom($role);
if ($roleChoisi === null) {
    fwrite(STDERR, "role inconnu : {$role}\n");
    exit(2);
}

try {
    $comptes = new Comptes();
    $existant = $comptes->parIdentifiant($identifiant);
    if ($existant !== null) {
        $id = (int) $existant['id'];
        $comptes->changerMotDePasse($id, $motDePasse);
        $comptes->ouvrirAcces($id, Comptes::APPLICATION, $roleChoisi);
        echo "compte mis a jour : {$identifiant} ({$roleChoisi->value})\n";
    } else {
        $comptes->creer($identifiant, $motDePasse, $nom, $roleChoisi);
        echo "compte cree : {$identifiant} ({$roleChoisi->value})\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'echec : ' . $e->getMessage() . "\n");
    exit(1);
}
