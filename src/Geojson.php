<?php

declare(strict_types=1);

namespace Syntropia;

/**
 * Lire un fichier GeoJSON, et en tirer des tracés pour la carte.
 *
 * Port de `application/src/Geojson.php` (dépôt de référence, 263 lignes),
 * sans changement de fond — seul le namespace change. Voir CLAUDE.md
 * § Point de reprise, chantier « carte interactive du terrain », Phase 10.
 *
 * **Pourquoi cet import existe** : *ContourBuddy* —
 * `contour-buddy1.vercel.app` — génère des courbes de niveau depuis la
 * même altimétrie IGN que l'atelier et **les exporte en GeoJSON**. Plutôt
 * qu'un pont vers un seul outil, l'atelier lit le format : QGIS, Géoportail,
 * une trace GPS convertie ou un relevé de géomètre entrent par la même
 * porte.
 *
 * **Ce qui est lu** : `FeatureCollection`, `Feature`, `GeometryCollection`,
 * et les géométries `Point`, `MultiPoint`, `LineString`, `MultiLineString`,
 * `Polygon`, `MultiPolygon`. Un multiple donne **un tracé par morceau** :
 * une courbe de niveau coupée en trois reste trois courbes, et non une
 * ligne qui saute.
 *
 * ⚠ **Le GeoJSON écrit `[longitude, latitude]`** — l'inverse de l'atelier.
 * C'est l'erreur la plus fréquente sur ce format, et elle est silencieuse :
 * le tracé atterrit en Somalie sans qu'aucun contrôle ne proteste. La
 * conversion se fait ici, une seule fois.
 *
 * ⚠ **Et le format suppose WGS 84.** Un fichier en Lambert 93 — la
 * projection française usuelle — porte des coordonnées à six chiffres qui
 * ne ressemblent pas à des degrés : elles sont refusées, avec le motif.
 */
final class Geojson
{
    /** Au-delà, le fichier n'est plus un plan de terrain. */
    public const OCTETS_MAX = 4_000_000;

    /** Un tracé plus long ne tiendrait pas dans la colonne, et ne se lit pas. */
    public const POINTS_MAX = 1500;

    /** Ce qu'un import pose d'un coup — au-delà, la carte devient illisible. */
    public const TRACES_MAX = 300;

    /**
     * Les clés où chercher un nom, dans l'ordre de préférence. `elevation`
     * et `altitude` viennent en tête : sur une courbe de niveau, c'est
     * l'altitude qui la nomme.
     */
    private const CLES_NOM = ['elevation', 'altitude', 'alt', 'z', 'level', 'niveau',
        'name', 'nom', 'title', 'titre', 'label', 'libelle', 'description'];

    /**
     * @return array{traces: list<array{type: string, points: list<array{0: float, 1: float}>,
     *                libelle: ?string, proprietes: array<string, mixed>}>,
     *                erreurs: list<string>}
     */
    public static function lire(string $contenu): array
    {
        if ($contenu === '') {
            return ['traces' => [], 'erreurs' => ['Le fichier est vide.']];
        }
        if (strlen($contenu) > self::OCTETS_MAX) {
            return ['traces' => [], 'erreurs' => [sprintf(
                'Le fichier dépasse %d Mo. Découpez-le, ou n\'exportez que la zone du terrain.',
                (int) (self::OCTETS_MAX / 1_000_000))]];
        }
        $json = json_decode($contenu, true);
        if (!is_array($json)) {
            return ['traces' => [], 'erreurs' => ["Ce fichier n'est pas du JSON lisible."]];
        }

        $traces = [];
        $erreurs = [];
        self::parcourir($json, $traces, $erreurs, []);

        if ($traces === [] && $erreurs === []) {
            $erreurs[] = "Aucune géométrie trouvée. Le fichier doit être en GeoJSON : "
                . 'un KML ou un DXF se convertit d\'abord.';
        }
        if (count($traces) > self::TRACES_MAX) {
            $erreurs[] = sprintf('%d tracés trouvés. Seuls les %d premiers sont posés. '
                . 'Pour le reste, faites un export plus resserré.',
                count($traces), self::TRACES_MAX);
            $traces = array_slice($traces, 0, self::TRACES_MAX);
        }
        return ['traces' => $traces, 'erreurs' => $erreurs];
    }

    /**
     * @param array<string, mixed> $noeud
     * @param list<array<string, mixed>> $traces
     * @param list<string> $erreurs
     * @param array<string, mixed> $proprietes celles du `Feature` englobant
     */
    private static function parcourir(array $noeud, array &$traces, array &$erreurs,
                                      array $proprietes): void
    {
        $type = is_string($noeud['type'] ?? null) ? $noeud['type'] : '';

        if ($type === 'FeatureCollection' && is_array($noeud['features'] ?? null)) {
            foreach ($noeud['features'] as $entite) {
                if (is_array($entite)) {
                    self::parcourir($entite, $traces, $erreurs, $proprietes);
                }
            }
            return;
        }
        if ($type === 'Feature') {
            $propres = is_array($noeud['properties'] ?? null) ? $noeud['properties'] : [];
            if (is_array($noeud['geometry'] ?? null)) {
                self::parcourir($noeud['geometry'], $traces, $erreurs, $propres);
            }
            return;
        }
        if ($type === 'GeometryCollection' && is_array($noeud['geometries'] ?? null)) {
            foreach ($noeud['geometries'] as $geometrie) {
                if (is_array($geometrie)) {
                    self::parcourir($geometrie, $traces, $erreurs, $proprietes);
                }
            }
            return;
        }

        $coordonnees = $noeud['coordinates'] ?? null;
        if (!is_array($coordonnees)) {
            return;
        }
        // Un multiple devient plusieurs tracés : chacun garde son propre
        // trait, et une courbe coupée ne saute pas d'un bout à l'autre.
        $morceaux = match ($type) {
            'Point' => [[$coordonnees]],
            'MultiPoint' => array_map(static fn ($p): array => [$p], $coordonnees),
            'LineString' => [$coordonnees],
            'MultiLineString' => $coordonnees,
            // D'un polygone on garde l'anneau extérieur ; ses trous ne se
            // dessinent pas, et les inventer serait pire que les ignorer.
            'Polygon' => [$coordonnees[0] ?? []],
            'MultiPolygon' => array_map(static fn ($p): array => $p[0] ?? [], $coordonnees),
            default => [],
        };
        if ($morceaux === []) {
            $erreurs[] = "Une géométrie de type « {$type} » est ignorée : l'atelier ne sait pas la lire.";
            return;
        }
        $notre = match ($type) {
            'Point', 'MultiPoint' => 'point',
            'LineString', 'MultiLineString' => 'ligne',
            default => 'polygone',
        };

        foreach ($morceaux as $morceau) {
            if (!is_array($morceau)) {
                continue;
            }
            $points = self::points($morceau, $erreurs);
            if ($points === null) {
                return;   // le motif est déjà dit ; inutile de le répéter mille fois
            }
            $minimum = ['point' => 1, 'ligne' => 2, 'polygone' => 3][$notre];
            if (count($points) < $minimum) {
                continue;
            }
            $traces[] = ['type' => $notre, 'points' => $points,
                'libelle' => self::nom($proprietes), 'proprietes' => self::retenir($proprietes)];
        }
    }

    /**
     * @param list<mixed> $bruts
     * @param list<string> $erreurs
     * @return list<array{0: float, 1: float}>|null
     */
    private static function points(array $bruts, array &$erreurs): ?array
    {
        $points = [];
        foreach ($bruts as $point) {
            if (!is_array($point) || !is_numeric($point[0] ?? null) || !is_numeric($point[1] ?? null)) {
                $erreurs[] = 'Coordonnées illisibles dans le fichier.';
                return null;
            }
            $lon = (float) $point[0];
            $lat = (float) $point[1];
            if (abs($lat) > 90 || abs($lon) > 180) {
                $erreurs[] = "Ces coordonnées ne sont pas des degrés. Le fichier est "
                    . "sans doute en Lambert 93. Réexportez-le en WGS 84, "
                    . "le système qu'utilise le GeoJSON.";
                return null;
            }
            // GeoJSON : [longitude, latitude]. Ici : [latitude, longitude].
            $points[] = [round($lat, 6), round($lon, 6)];
        }
        // Un anneau fermé répète son premier point ; le polygone de la carte
        // se ferme tout seul.
        $n = count($points);
        if ($n > 3 && $points[0] === $points[$n - 1]) {
            array_pop($points);
        }
        return count($points) > self::POINTS_MAX
            ? self::alleger($points, self::POINTS_MAX) : $points;
    }

    /**
     * Alléger un tracé en gardant sa forme — un point sur n, jamais les
     * extrémités. **Grossier et honnête** : le nombre de points retenus est
     * dit à l'import, pour qu'on sache que le tracé a été simplifié.
     *
     * @param list<array{0: float, 1: float}> $points
     * @return list<array{0: float, 1: float}>
     */
    private static function alleger(array $points, int $cible): array
    {
        $n = count($points);
        $pas = (int) ceil($n / $cible);
        $garde = [];
        for ($i = 0; $i < $n; $i += $pas) {
            $garde[] = $points[$i];
        }
        if ($garde[count($garde) - 1] !== $points[$n - 1]) {
            $garde[] = $points[$n - 1];
        }
        return $garde;
    }

    /** @param array<string, mixed> $proprietes */
    private static function nom(array $proprietes): ?string
    {
        foreach (self::CLES_NOM as $cle) {
            foreach ($proprietes as $nom => $valeur) {
                if (strtolower((string) $nom) !== $cle || !is_scalar($valeur)) {
                    continue;
                }
                $mot = trim((string) $valeur);
                if ($mot === '') {
                    continue;
                }
                // Une altitude se lit mieux avec son unité.
                return in_array($cle, ['elevation', 'altitude', 'alt', 'z', 'level', 'niveau'], true)
                    && is_numeric($mot) ? rtrim(rtrim(number_format((float) $mot, 1, ',', ' '), '0'), ',') . ' m'
                    : mb_substr($mot, 0, 160);
            }
        }
        return null;
    }

    /**
     * Ce qu'on garde des propriétés du fichier : les scalaires, et pas plus
     * de dix. Un export porte parfois trente colonnes dont aucune ne sert.
     *
     * @param array<string, mixed> $proprietes
     * @return array<string, mixed>
     */
    private static function retenir(array $proprietes): array
    {
        $garde = [];
        foreach ($proprietes as $cle => $valeur) {
            if (!is_scalar($valeur) || count($garde) >= 10) {
                continue;
            }
            $garde[mb_substr((string) $cle, 0, 40)] = is_string($valeur)
                ? mb_substr($valeur, 0, 120) : $valeur;
        }
        return $garde;
    }
}
