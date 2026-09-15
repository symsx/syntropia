<?php

declare(strict_types=1);

namespace Syntropia;

use PDO;

/**
 * Connexions PDO — **deux bases distinctes**.
 *
 * Adapté de `application/src/Database.php` (dépôt de référence). Décision
 * révisée le 2026-09-13 : syntropia a ses **propres** bases, `comptes`
 * comme `syntropia` — rien de partagé avec `syntropie`, qui a vocation à
 * être supprimé ou archivé, pas à rester opérationnel à côté.
 */
final class Database
{
    /** Recherche : insensible à la casse ET aux accents — « Rene » = « René ». */
    public const COLLATION = 'utf8mb4_unicode_520_ci';

    /** Identité : deux noms qui ne diffèrent que par un accent sont deux noms. */
    public const COLLATION_IDENTITE = 'utf8mb4_bin';

    /** @var array<string, PDO> */
    private static array $connexions = [];

    /** Qui existe, et ce que chacun peut. Propre à syntropia. */
    public static function comptes(): PDO
    {
        return self::$connexions['comptes'] ??= self::connecter(
            Configuration::reglage('base_comptes', 'SYNTROPIA_DB_COMPTES', 'syntropia_comptes'));
    }

    /** Sites, parcelles, scénarios, interventions — base propre à syntropia. */
    public static function syntropia(): PDO
    {
        return self::$connexions['syntropia'] ??= self::connecter(
            Configuration::reglage('base_syntropia', 'SYNTROPIA_DB_BASE', 'syntropia'));
    }

    public static function reinitialiser(): void
    {
        self::$connexions = [];
    }

    private static function connecter(string $base): PDO
    {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            Configuration::reglage('hote', 'SYNTROPIA_DB_HOTE', '127.0.0.1'),
            (int) Configuration::reglage('port', 'SYNTROPIA_DB_PORT', '3306'),
            $base);

        $pdo = new PDO($dsn,
            Configuration::reglage('utilisateur', 'SYNTROPIA_DB_USER', ''),
            Configuration::reglage('mot_de_passe', 'SYNTROPIA_DB_MDP', ''), [
                // Requêtes préparées natives, jamais émulées : c'est ce qui
                // rend l'injection impossible plutôt qu'improbable.
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ]);

        $pdo->exec('SET NAMES utf8mb4 COLLATE ' . self::COLLATION);
        $pdo->exec("SET time_zone = '+00:00'");
        $pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ONLY_FULL_GROUP_BY,"
            . "ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
        return $pdo;
    }
}
