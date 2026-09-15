<?php

declare(strict_types=1);

/**
 * Chargement automatique PSR-4 minimal pour l'espace de noms `Syntropia\`.
 *
 * Repris tel quel de `application/src/autoload.php` (dépôt de référence) —
 * seul le préfixe change, pour garder les deux codes isolés. Composer
 * n'est pas introduit : sans dépendance, déployable par simple copie.
 */
spl_autoload_register(static function (string $classe): void {
    $prefixe = 'Syntropia\\';
    if (!str_starts_with($classe, $prefixe)) {
        return;
    }
    $chemin = __DIR__ . '/' . str_replace('\\', '/', substr($classe, strlen($prefixe))) . '.php';
    if (is_file($chemin)) {
        require $chemin;
    }
});
