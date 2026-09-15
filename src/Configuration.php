<?php

declare(strict_types=1);

namespace Syntropia;

/**
 * Les réglages, et d'où ils viennent.
 *
 * Adapté de `application/src/Configuration.php` (dépôt de référence) —
 * même logique, préfixe `SYNTROPIA_` plutôt que `SYNTROPIE_`, pour que
 * les deux applications puissent tourner en local sans se marcher dessus.
 *
 * L'environnement d'abord, un fichier à défaut. L'hébergement cible ne
 * donne aucun terminal, donc aucune variable d'environnement n'y est
 * déclarable — c'est `config/base.php` qui sert en production. En local
 * c'est l'inverse qui est commode. Aucun identifiant n'est jamais inscrit
 * dans le dépôt.
 */
final class Configuration
{
    /** @var array<string, mixed>|null */
    private static ?array $fichier = null;

    /** @var array<string, string>|null */
    private static ?array $local = null;

    public static function reglage(string $cle, string $variable, string $defaut = ''): string
    {
        $env = getenv($variable);
        if (is_string($env) && $env !== '') {
            return $env;
        }
        $valeur = self::fichier()[$cle] ?? self::local()[$variable] ?? $defaut;
        return is_scalar($valeur) ? (string) $valeur : $defaut;
    }

    /**
     * `config/local.env`, en dernier recours — pour le développement seul.
     *
     * @return array<string, string>
     */
    private static function local(): array
    {
        if (self::$local !== null) {
            return self::$local;
        }
        self::$local = [];
        $chemin = dirname(__DIR__) . '/config/local.env';
        foreach (is_file($chemin) ? file($chemin) ?: [] : [] as $ligne) {
            $ligne = trim($ligne);
            if ($ligne !== '' && !str_starts_with($ligne, '#') && str_contains($ligne, '=')) {
                [$cle, $valeur] = explode('=', $ligne, 2);
                self::$local[trim($cle)] = trim(trim($valeur), '"\'');
            }
        }
        return self::$local;
    }

    /** @return array<string, mixed> */
    public static function fichier(): array
    {
        if (self::$fichier !== null) {
            return self::$fichier;
        }
        $chemin = getenv('SYNTROPIA_CONFIG') ?: dirname(__DIR__) . '/config/base.php';
        $lu = is_file($chemin) ? require $chemin : null;
        return self::$fichier = is_array($lu) ? $lu : [];
    }

    /** Utile aux tests, et à rien d'autre. */
    public static function reinitialiser(): void
    {
        self::$fichier = null;
        self::$local = null;
    }

    /**
     * Le chemin sous lequel l'application est servie.
     *
     * `/` sous le serveur intégré de PHP en local. Sert à bâtir les liens
     * et à cantonner le cookie de session — voir `Web\Authentification`,
     * à adapter du dépôt de référence quand la connexion sera reprise ici.
     */
    public static function base(): string
    {
        return rtrim(self::reglage('base', 'SYNTROPIA_BASE', '/'), '/') . '/';
    }
}
