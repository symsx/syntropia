<?php

declare(strict_types=1);

namespace Syntropia\Web;

/**
 * Les parcelles cadastrales, demandées à l'API Carto de l'IGN.
 *
 * Port de `application/src/Web/Cadastre.php` (dépôt de référence, 264
 * lignes), sans changement de fond — seul le namespace change. Voir
 * CLAUDE.md § Point de reprise, chantier « carte interactive du terrain »,
 * Phase 6.
 *
 * **Ce que cela change pour la carte** : les limites du terrain se
 * dessinaient à la main, au jugé, sur une photo aérienne dont un point
 * d'écran vaut 40 cm. Elles peuvent maintenant venir **du cadastre**, avec
 * leur identifiant, leur commune et **leur contenance officielle**.
 *
 * ⚠ **Une limite cadastrale n'est pas une limite de propriété au sens
 * juridique** : le plan cadastral est un document fiscal, sa précision
 * varie d'une commune à l'autre, et seul un bornage par géomètre fait foi.
 * Pour concevoir, il est largement suffisant ; pour une clôture mitoyenne,
 * non.
 *
 * **Service public français, sans clé, limité à la France** — hors
 * couverture, il répond une collection vide, jamais une erreur. L'appel
 * part du serveur, comme pour l'altitude : le visiteur n'est pas exposé au
 * tiers.
 */
final class Cadastre
{
    private const URL = 'https://apicarto.ign.fr/api/cadastre/parcelle';

    private const DELAI_SECONDES = 10;

    /**
     * Au-delà, ce n'est plus un terrain qu'on regarde, c'est un quartier.
     *
     * 600 m était injoignable en pratique : au zoom maximal de `carte.js`
     * (18, ~0,4 m/pixel sous nos latitudes), un écran ordinaire affiche déjà
     * 700 à 900 m de côté — le message « zoomez » restait donc affiché même
     * zoom au maximum. Remonté à 2000 m, signalé par le porteur le
     * 2026-09-18 (« le zoom est au maximum possible », capture à l'appui) —
     * large marge sur les écrans les plus larges, et cohérent avec le
     * terrain agroforestier visé ici, souvent bien plus grand qu'un jardin.
     */
    public const COTE_MAX_M = 2000.0;

    /**
     * Les parcelles qui touchent un carré centré sur un point.
     *
     * @param float $coteM le côté du carré, en mètres
     * @return array{parcelles: list<array<string, mixed>>, message: string}
     */
    public static function autour(float $latitude, float $longitude, float $coteM): array
    {
        $coteM = max(20.0, min(self::COTE_MAX_M, $coteM));
        $mLat = 111320.0;
        $mLon = 111320.0 * cos(deg2rad($latitude));
        if ($mLon < 1.0) {
            return ['parcelles' => [], 'message' => 'Trop près des pôles.'];
        }
        $dLat = $coteM / 2 / $mLat;
        $dLon = $coteM / 2 / $mLon;
        $anneau = [
            [$longitude - $dLon, $latitude - $dLat], [$longitude + $dLon, $latitude - $dLat],
            [$longitude + $dLon, $latitude + $dLat], [$longitude - $dLon, $latitude + $dLat],
            [$longitude - $dLon, $latitude - $dLat],
        ];
        $geom = json_encode(['type' => 'Polygon', 'coordinates' => [array_map(
            static fn (array $p): array => [round($p[0], 7), round($p[1], 7)], $anneau)]]);

        $resultat = self::demander(['geom' => $geom]);

        // LES PLUS PROCHES D'ABORD. En ville, un carré de 200 m rend cent
        // cinquante parcelles : sans ordre, celle qu'on cherche est perdue au
        // milieu. La distance se mesure du point du site au centre de chaque
        // parcelle, dans la projection locale plane.
        foreach ($resultat['parcelles'] as $i => $parcelle) {
            $resultat['parcelles'][$i]['distance_m'] =
                round(self::distance($latitude, $longitude, $parcelle['points'], $mLat, $mLon));
        }
        usort($resultat['parcelles'],
            static fn (array $a, array $b): int => $a['distance_m'] <=> $b['distance_m']);
        return $resultat;
    }

    /**
     * Les parcelles d'une emprise rectangulaire — ce que la carte affiche.
     *
     * **C'est l'usage principal** : la couche cadastrale sert de repère pour
     * tracer les limites du terrain, et l'emprise est celle qu'on voit.
     *
     * @param array{0: float, 1: float} $sudOuest latitude, longitude
     * @param array{0: float, 1: float} $nordEst
     * @return array{parcelles: list<array<string, mixed>>, message: string}
     */
    public static function dansEmprise(array $sudOuest, array $nordEst): array
    {
        [$sud, $ouest] = $sudOuest;
        [$nord, $est] = $nordEst;
        if ($nord <= $sud || $est <= $ouest || abs($nord) > 90 || abs($est) > 180) {
            return ['parcelles' => [], 'message' => 'Emprise incohérente.'];
        }
        // Une garde de politesse envers un service public : au-delà, la
        // réponse compterait des milliers de parcelles qu'aucun écran ne
        // montre. Le côté se mesure sur la diagonale la plus longue.
        $mLat = 111320.0;
        $mLon = 111320.0 * cos(deg2rad(($nord + $sud) / 2));
        $cote = max(($nord - $sud) * $mLat, ($est - $ouest) * $mLon);
        if ($cote > self::COTE_MAX_M) {
            return ['parcelles' => [], 'message' => sprintf(
                'Zoomez pour voir le cadastre. Il s\'affiche jusqu\'à %d m de côté, '
                . 'et la vue en fait %d.', (int) self::COTE_MAX_M, (int) $cote)];
        }
        $anneau = [[$ouest, $sud], [$est, $sud], [$est, $nord], [$ouest, $nord], [$ouest, $sud]];
        $geom = json_encode(['type' => 'Polygon', 'coordinates' => [array_map(
            static fn (array $p): array => [round($p[0], 7), round($p[1], 7)], $anneau)]]);
        return self::demander(['geom' => $geom]);
    }

    /**
     * La distance d'un point au centre d'un tracé, en mètres.
     *
     * @param list<array{0: float, 1: float}> $points
     */
    private static function distance(float $latitude, float $longitude, array $points,
                                     float $mLat, float $mLon): float
    {
        if ($points === []) {
            return INF;
        }
        $lat = $lon = 0.0;
        foreach ($points as $point) {
            $lat += $point[0];
            $lon += $point[1];
        }
        $lat /= count($points);
        $lon /= count($points);
        return hypot(($lat - $latitude) * $mLat, ($lon - $longitude) * $mLon);
    }

    /**
     * Une parcelle nommée — commune, section, numéro.
     *
     * **La géométrie se redemande à l'IGN plutôt que d'être renvoyée par le
     * navigateur** : ce qui entre en base vient ainsi toujours de la source,
     * jamais d'un formulaire qu'on pourrait avoir modifié.
     *
     * @return array{parcelles: list<array<string, mixed>>, message: string}
     */
    public static function parReference(string $codeInsee, string $section, string $numero): array
    {
        if (!preg_match('/^[0-9AB]{5}$/', $codeInsee)
            || !preg_match('/^[0-9A-Z]{1,3}$/', $section)
            || !preg_match('/^[0-9]{1,4}$/', $numero)) {
            return ['parcelles' => [], 'message' => 'Référence cadastrale mal formée.'];
        }
        return self::demander(['code_insee' => $codeInsee, 'section' => $section,
            'numero' => str_pad($numero, 4, '0', STR_PAD_LEFT)]);
    }

    /**
     * @param array<string, string> $parametres
     * @return array{parcelles: list<array<string, mixed>>, message: string}
     */
    private static function demander(array $parametres): array
    {
        if (!function_exists('curl_init')) {
            return ['parcelles' => [], 'message' => "Le cadastre n'est pas joignable d'ici."];
        }
        $curl = curl_init(self::URL . '?' . http_build_query($parametres));
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::DELAI_SECONDES,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_USERAGENT => 'syntropia (atelier agroforestier syntropique)',
        ]);
        $corps = curl_exec($curl);
        $code = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        if (!is_string($corps) || $code !== 200) {
            return ['parcelles' => [],
                'message' => "L'IGN n'a pas répondu. Les limites se tracent toujours à la main."];
        }
        $json = json_decode($corps, true);
        if (!is_array($json['features'] ?? null)) {
            return ['parcelles' => [], 'message' => 'Le service a donné une réponse inattendue.'];
        }
        $parcelles = [];
        foreach ($json['features'] as $entite) {
            $parcelle = self::normaliser($entite);
            if ($parcelle !== null) {
                $parcelles[] = $parcelle;
            }
        }
        if ($parcelles === []) {
            return ['parcelles' => [], 'message' => "Aucune parcelle cadastrale ici. "
                . "Le service ne couvre que la France, et quelques communes manquent "
                . "au plan numérisé."];
        }
        return ['parcelles' => $parcelles, 'message' => count($parcelles) . ' parcelle'
            . (count($parcelles) > 1 ? 's' : '') . ' trouvée' . (count($parcelles) > 1 ? 's' : '')
            . '.'];
    }

    /**
     * Une entité GeoJSON, ramenée à ce dont la carte a besoin.
     *
     * **Deux conversions, et elles comptent** : le GeoJSON écrit
     * `[longitude, latitude]`, l'atelier écrit `[latitude, longitude]` ; et
     * un `MultiPolygon` peut porter plusieurs polygones, chacun avec des
     * trous. **On garde l'anneau extérieur du plus grand polygone**, et
     * l'on dit combien d'autres ont été laissés.
     *
     * @param array<string, mixed> $entite
     * @return array<string, mixed>|null
     */
    private static function normaliser(array $entite): ?array
    {
        $p = $entite['properties'] ?? [];
        $g = $entite['geometry'] ?? [];
        $type = $g['type'] ?? '';
        $coordonnees = $g['coordinates'] ?? null;
        if (!is_array($coordonnees)) {
            return null;
        }
        $polygones = $type === 'Polygon' ? [$coordonnees]
            : ($type === 'MultiPolygon' ? $coordonnees : []);
        $meilleur = null;
        foreach ($polygones as $polygone) {
            $exterieur = $polygone[0] ?? null;
            if (!is_array($exterieur) || count($exterieur) < 4) {
                continue;
            }
            if ($meilleur === null || count($exterieur) > count($meilleur)) {
                $meilleur = $exterieur;
            }
        }
        if ($meilleur === null) {
            return null;
        }
        $points = [];
        foreach ($meilleur as $point) {
            if (!is_array($point) || !is_numeric($point[0] ?? null) || !is_numeric($point[1] ?? null)) {
                return null;
            }
            // GeoJSON : [longitude, latitude]. Ici : [latitude, longitude].
            $points[] = [round((float) $point[1], 6), round((float) $point[0], 6)];
        }
        // Un anneau GeoJSON est fermé — son dernier point répète le premier.
        // Le polygone de la carte se ferme tout seul : on retire le doublon.
        if (count($points) > 3 && $points[0] === $points[count($points) - 1]) {
            array_pop($points);
        }
        return [
            'idu' => (string) ($p['idu'] ?? ''),
            'commune' => (string) ($p['nom_com'] ?? ''),
            'code_insee' => (string) ($p['code_insee'] ?? ''),
            'section' => (string) ($p['section'] ?? ''),
            'numero' => (string) ($p['numero'] ?? ''),
            'contenance_m2' => isset($p['contenance']) ? (int) $p['contenance'] : null,
            'points' => $points,
            'autres_polygones' => max(0, count($polygones) - 1),
        ];
    }

    /** Le nom court d'une parcelle — ce qu'on lit sur un plan. */
    public static function libelle(array $parcelle): string
    {
        return trim(($parcelle['section'] ?? '') . ' ' . ltrim((string) ($parcelle['numero'] ?? ''), '0'));
    }
}
