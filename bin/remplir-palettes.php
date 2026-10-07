<?php

declare(strict_types=1);

/**
 * Remplir la palette des parcelles qui n'en ont pas encore, depuis ce qu'elles portent (chantier-palette-vegetale.md,
 * Phase 6) : leurs bordures et lignes de plants, leur scénario, leur potager — règles dans `Palettes::remplir()`.
 *
 * Une palette déjà composée n'est jamais touchée : relancer ne change rien. **Sans `--ecrire`, rien ne s'écrit** : le
 * script dit ce qu'il ferait, parcelle par parcelle, et les noms qui resteraient hors palette.
 *
 *   php bin/remplir-palettes.php            simulation
 *   php bin/remplir-palettes.php --ecrire   écrit
 *
 * La base visée est celle de la configuration chargée (`config/base.php`, ou `config/local.env` exporté dans le
 * shell) : le script l'annonce en premier. Sur ce poste, exporter `config/local.env` d'abord, sinon c'est la
 * production qui est lue.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../src/autoload.php';
require __DIR__ . '/../src/Configuration.php';
require __DIR__ . '/../src/Database.php';

use Syntropia\Database;
use Syntropia\Palette;
use Syntropia\Web\Palettes;

$ecrire = in_array('--ecrire', $argv, true);
$pdo = Database::syntropia();
echo 'Base : ', $pdo->query('SELECT DATABASE()')->fetchColumn(), $ecrire ? ' — écriture' : ' — simulation, rien ne s’écrit', "\n\n";

$palettes = new Palettes($pdo);
$total = 0;
foreach ($pdo->query('SELECT * FROM parcelle ORDER BY site_id, id')->fetchAll() as $p) {
    $r = $palettes->remplir($p, (int) $p['site_id'], $ecrire);
    echo 'Parcelle ', $p['id'], ' (projet ', $p['site_id'], ') ', $p['code'], ' — ', $p['nom'], ' : ';
    if (!$r['vide']) {
        echo "palette déjà composée, pas touchée\n\n";
        continue;
    }
    echo count($r['entrees']), " espèce(s)\n";
    foreach ($r['entrees'] as $x) {
        echo '  ', str_pad($x['place'], 11), ' ', $x['nom'],
            $x['fonctions'] !== [] ? ' — ' . implode(', ', array_map(static fn (string $f): string => Palette::FONCTIONS[$f]['libelle'], $x['fonctions'])) : '',
            ' (', implode(', ', $x['de']), ")\n";
    }
    if ($r['hors'] !== []) {
        echo '  hors palette : ', implode(', ', $r['hors']), "\n";
    }
    echo "\n";
    $total += count($r['entrees']);
}
echo $total, ' espèce(s) ', $ecrire ? 'écrite(s)' : 'à écrire avec --ecrire', ".\n";
