<?php

declare(strict_types=1);

namespace Syntropia;

/**
 * Les associations de plantes — ce qui s'entend bien, ce qu'il vaut mieux éviter de placer côte à côte.
 *
 * Lit les trois tables de `contenu/associations/` (Weinrich, abbaye de Fulda — importées telles quelles du
 * projet `syntropie` le 2026-09-20, voir `chantier-associations.md`) : le potager (l'espèce, ses voisinages
 * favorables et défavorables), le verger (les treize porteurs et leurs compagnons) et la succession (préculture,
 * postculture). Pur : aucune base, un fichier lu une fois.
 *
 * **Ce que ces tables sont, et ne sont pas** — la source le dit elle-même :
 * - des **ordres de conduite, jamais des preuves** : une relation bénéfique peut ne pas se vérifier partout, elle
 *   s'éprouve localement (le témoin est fait pour cela). Contexte : Allemagne moyenne, Jura souabe et Hesse.
 * - **savoir ce qu'il ne faut pas associer est une information de conception, pas une interdiction** ;
 * - **elles ne sont pas symétriques** : une table dit ce que chaque plante déclare, sans contrôle de réciprocité
 *   (le pommier est défavorable au groseillier ; le groseillier ne le dit pas). Une relation est donc **connue si
 *   l'une des deux plantes la cite** — jamais « inconnue » parce que l'autre se tait — et le verger n'est pas un graphe ;
 * - deux plantes **toxiques** y sont données comme favorables (la digitale au poirier, l'aconit au pommier) : elles
 *   se **signalent** toujours (`toxique()`), jamais ne se recopient dans une liste de plantation sans mention ;
 * - l'itinéraire de la source (sol ameubli chaque printemps) n'est pas celui de l'atelier : **ce qui se transporte,
 *   ce sont les couples et les mécanismes, jamais la conduite**.
 */
final class Associations
{
    public const FAVORABLE = 'favorable';
    public const DEFAVORABLE = 'defavorable';
    /** Une plante dit favorable, l'autre défavorable : ne se produit pas dans les tables actuelles, se gère quand même. */
    public const DISCORDANT = 'discordant';
    public const INCONNUE = 'inconnue';

    /** Les plantes toxiques que les tables donnent comme favorables — à signaler à chaque affichage. */
    public const TOXIQUES = ['digitale', 'aconit'];

    private const DOSSIER = __DIR__ . '/../contenu/associations';

    /** @var array<string, array<string, array<string, mixed>>> */
    private static array $tables = [];

    /** Un nom sans accent ni majuscule, apostrophes et traits d'union rendus en espaces : « Œillet d'Inde » → « oeillet d inde ». */
    public static function normaliser(string $nom): string
    {
        // Sans l'extension intl (rien ne garantit qu'elle soit là) : une table des accents du français.
        $nom = strtr(mb_strtolower($nom), [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'î' => 'i', 'ï' => 'i',
            'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ÿ' => 'y', 'ç' => 'c', 'œ' => 'oe', 'æ' => 'ae',
        ]);
        return trim((string) preg_replace("/[\s'’\-]+/u", ' ', $nom));
    }

    /** Deux noms désignent-ils la même plante ? Sans accent ni casse, et au singulier comme au pluriel (« carotte », « carottes »). */
    public static function memeNom(string $a, string $b): bool
    {
        $a = self::normaliser($a);
        $b = self::normaliser($b);
        return $a !== '' && ($a === $b || $a . 's' === $b || $a === $b . 's');
    }

    public static function toxique(string $nom): bool
    {
        foreach (self::TOXIQUES as $t) {
            if (self::memeNom($nom, $t)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Une table lue : nom normalisé → sa ligne. `favorables`/`defavorables` sont les noms tels qu'écrits dans la
     * table (« aucun connu » n'est pas un nom : la liste est alors vide).
     *
     * @param 'potager'|'verger'|'succession' $nom
     * @return array<string, array<string, mixed>>
     */
    public static function table(string $nom, ?string $dossier = null): array
    {
        $dossier ??= self::DOSSIER;
        $cle = $dossier . '|' . $nom;
        if (isset(self::$tables[$cle])) {
            return self::$tables[$cle];
        }
        $fichier = $dossier . '/associations-' . $nom . '.csv';
        $lignes = [];
        if (is_readable($fichier) && ($h = fopen($fichier, 'rb')) !== false) {
            $entete = fgetcsv($h, 0, ';', '"', '');
            while ($entete !== false && $entete !== null && ($r = fgetcsv($h, 0, ';', '"', '')) !== false) {
                if ($r === [null] || count($r) < count($entete)) {
                    continue;
                }
                $r = array_combine($entete, array_slice($r, 0, count($entete)));
                $liste = static fn (string $v): array => array_values(array_filter(
                    array_map('trim', explode('|', $v)),
                    static fn (string $x): bool => $x !== '' && !str_starts_with(mb_strtolower($x), 'aucun')));
                $premiere = $entete[0];
                $ligne = ['nom' => (string) $r[$premiere], 'complet' => ($r['complet'] ?? '') === 'oui',
                    'source' => (string) ($r['source'] ?? ''), 'note' => (string) ($r['note'] ?? '')];
                if ($nom === 'succession') {
                    $ligne['preculture'] = $liste((string) ($r['preculture'] ?? ''));
                    $ligne['postculture'] = $liste((string) ($r['postculture_ou_engrais_vert'] ?? ''));
                } else {
                    $ligne['favorables'] = $liste((string) ($r['favorables'] ?? ''));
                    $ligne['defavorables'] = $liste((string) ($r['defavorables'] ?? ''));
                }
                $lignes[self::normaliser($ligne['nom'])] = $ligne;
            }
            fclose($h);
        }
        return self::$tables[$cle] = $lignes;
    }

    /**
     * Ce que les tables disent d'une plante : ses voisinages favorables et défavorables, ce qu'elle porte si c'est un
     * porteur du verger. `null` si elle n'y figure pas — ce qui ne dit rien de ses voisinages.
     *
     * @return array{nom: string, table: string, favorables: list<string>, defavorables: list<string>, source: string, note: string, complet: bool}|null
     */
    public static function fiche(string $plante, ?string $dossier = null): ?array
    {
        foreach (['potager', 'verger'] as $table) {
            foreach (self::table($table, $dossier) as $ligne) {
                if (self::memeNom($ligne['nom'], $plante)) {
                    return ['nom' => $ligne['nom'], 'table' => $table, 'favorables' => $ligne['favorables'],
                        'defavorables' => $ligne['defavorables'], 'source' => $ligne['source'], 'note' => $ligne['note'],
                        'complet' => $ligne['complet']];
                }
            }
        }
        return null;
    }

    /**
     * Ce que la succession dit d'une plante : ce qui la précède, ce qui la suit (ou l'engrais vert).
     *
     * @return array{nom: string, preculture: list<string>, postculture: list<string>, source: string}|null
     */
    public static function succession(string $plante, ?string $dossier = null): ?array
    {
        foreach (self::table('succession', $dossier) as $ligne) {
            if (self::memeNom($ligne['nom'], $plante)) {
                return ['nom' => $ligne['nom'], 'preculture' => $ligne['preculture'], 'postculture' => $ligne['postculture'], 'source' => $ligne['source']];
            }
        }
        return null;
    }

    /**
     * La relation de deux plantes voisines. **Connue si l'une des deux la cite** : les tables ne sont pas
     * symétriques, et un silence de l'autre côté n'est pas un désaccord.
     *
     * @return array{relation: string, source: string}
     */
    public static function relation(string $a, string $b, ?string $dossier = null): array
    {
        $signaux = [];
        $sources = [];
        foreach ([[$a, $b], [$b, $a]] as [$de, $vers]) {
            $fiche = self::fiche($de, $dossier);
            if ($fiche === null) {
                continue;
            }
            foreach ([self::FAVORABLE => $fiche['favorables'], self::DEFAVORABLE => $fiche['defavorables']] as $signe => $liste) {
                foreach ($liste as $nom) {
                    if (self::memeNom($nom, $vers)) {
                        $signaux[$signe] = true;
                        $sources[$fiche['source']] = true;
                    }
                }
            }
        }
        $source = implode(' ; ', array_keys($sources));
        return match (true) {
            isset($signaux[self::FAVORABLE], $signaux[self::DEFAVORABLE]) => ['relation' => self::DISCORDANT, 'source' => $source],
            isset($signaux[self::FAVORABLE]) => ['relation' => self::FAVORABLE, 'source' => $source],
            isset($signaux[self::DEFAVORABLE]) => ['relation' => self::DEFAVORABLE, 'source' => $source],
            default => ['relation' => self::INCONNUE, 'source' => ''],
        };
    }

    /**
     * Les relations connues entre les plantes d'une liste, deux à deux. Les paires sans relation connue n'y figurent
     * pas : elles ne disent rien.
     *
     * @param list<string> $plantes
     * @return list<array{a: string, b: string, relation: string, source: string}>
     */
    public static function entre(array $plantes, ?string $dossier = null): array
    {
        $plantes = array_values(array_unique($plantes));
        $paires = [];
        for ($i = 0, $n = count($plantes); $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $r = self::relation($plantes[$i], $plantes[$j], $dossier);
                if ($r['relation'] !== self::INCONNUE) {
                    $paires[] = ['a' => $plantes[$i], 'b' => $plantes[$j]] + $r;
                }
            }
        }
        return $paires;
    }
}
