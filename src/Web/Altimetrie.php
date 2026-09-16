<?php

declare(strict_types=1);

namespace Syntropia\Web;

/**
 * L'altitude, demandée à la Géoplateforme de l'IGN — un point, ou une
 * grille pour le relief.
 *
 * Port de `application/src/Web/Altimetrie.php` (dépôt de référence, 225
 * lignes), sans changement de fond — seul le namespace change. Voir
 * CLAUDE.md § Point de reprise, chantier « carte interactive du terrain »,
 * Phase 9.
 *
 * **Service public français, sans clé, et limité à la France.** Hors
 * couverture il répond `z = -99999.0` : une sentinelle, pas une altitude.
 * L'oublier enregistrerait un site à moins 99 999 mètres — et le mode
 * strict de la base l'accepterait sans broncher.
 *
 * L'appel se fait côté SERVEUR, jamais depuis le navigateur : cela évite
 * d'exposer le visiteur à un tiers, et permet de traiter l'échec au lieu de
 * le laisser tomber dans une console.
 */
final class Altimetrie
{
    private const URL = 'https://data.geopf.fr/altimetrie/1.0/calcul/alti/rest/elevation.json';

    /** Hors couverture, l'IGN répond ceci. Ce n'est pas une altitude. */
    private const HORS_COUVERTURE = -99999.0;

    /** Court : une carte qui attend est une carte qu'on ferme. */
    private const DELAI_SECONDES = 6;

    /**
     * @return array{altitude: ?int, message: string}
     */
    public static function altitude(float $latitude, float $longitude): array
    {
        if (abs($latitude) > 90.0 || abs($longitude) > 180.0) {
            return ['altitude' => null, 'message' => 'Coordonnées hors du monde.'];
        }
        if (!function_exists('curl_init')) {
            return ['altitude' => null,
                'message' => "Le calcul d'altitude n'est pas disponible ici."];
        }

        $curl = curl_init(self::URL . '?' . http_build_query([
            'lon' => $longitude, 'lat' => $latitude, 'resource' => 'ign_rge_alti_wld',
        ]));
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
            return ['altitude' => null,
                'message' => "L'IGN n'a pas répondu. Saisissez l'altitude à la main."];
        }
        $json = json_decode($corps, true);
        $z = $json['elevations'][0]['z'] ?? null;
        if (!is_numeric($z)) {
            return ['altitude' => null, 'message' => 'Réponse inattendue du service.'];
        }
        $z = (float) $z;
        if ($z <= self::HORS_COUVERTURE + 1.0) {
            return ['altitude' => null,
                'message' => "Hors de la couverture de l'IGN — France seulement. "
                    . "Saisissez l'altitude à la main."];
        }
        return ['altitude' => (int) round($z),
            'message' => 'Altitude IGN : ' . round($z) . ' m.'];
    }

    // ------------------------------------------------------------------
    // Le relief — une grille d'altitudes
    // ------------------------------------------------------------------

    /**
     * Ce que le service accepte par appel, mesuré le 2026-09-07.
     *
     * L'API prend des listes séparées par `|`. **En GET seulement** : le
     * POST répond 500. Au-delà d'environ 400 points l'URL est refusée —
     * *414 Request-URI Too Long*. On demande donc par lots.
     */
    private const POINTS_PAR_APPEL = 150;

    /**
     * Le plafond de politesse : un terrain de 200 m de côté au pas de 5 m
     * fait 1 681 points, soit 12 appels. Au-delà, c'est un massif, pas un
     * terrain — et le service est public.
     */
    public const POINTS_MAX = 2000;

    /**
     * La maille du modèle, mesurée : deux points distants de 2 m rendent la
     * MÊME altitude, deux points distants de 5 m diffèrent. Descendre sous
     * 5 m ajoute des appels sans ajouter d'information.
     */
    public const PAS_MINIMAL_M = 5.0;

    /**
     * Une grille d'altitudes autour d'un centre — le relief, en points.
     *
     * Les lignes vont du sud au nord, les colonnes d'ouest en est, pour que
     * l'ordre du tableau soit celui d'une carte lue à l'endroit.
     *
     * @param float $coteM le côté du carré couvert, en mètres
     * @param float $pasM  la distance entre deux points
     * @return array{grille: ?array{cote: int, pas: float, sud_ouest: array{0: float, 1: float},
     *               z: list<?float>}, appels: int, message: string}
     */
    public static function grille(float $latitude, float $longitude,
                                  float $coteM, float $pasM): array
    {
        $pasM = max(self::PAS_MINIMAL_M, $pasM);
        $cote = (int) max(2, min(80, round($coteM / $pasM) + 1));
        if ($cote * $cote > self::POINTS_MAX) {
            return ['grille' => null, 'appels' => 0, 'message' => sprintf(
                'Ce carré demande %d points ; je m\'arrête à %d. '
                . 'Élargissez le pas, ou réduisez l\'emprise.',
                $cote * $cote, self::POINTS_MAX)];
        }
        if (!function_exists('curl_init')) {
            return ['grille' => null, 'appels' => 0,
                'message' => "Le relevé d'altitude n'est pas disponible ici."];
        }

        // Projection locale plane : à l'échelle d'un terrain, l'erreur est
        // négligeable. Même formule que `Calques::metresParDegre()`.
        $mLat = 111320.0;
        $mLon = 111320.0 * cos(deg2rad($latitude));
        if ($mLon < 1.0) {
            return ['grille' => null, 'appels' => 0, 'message' => 'Trop près des pôles.'];
        }
        $demi = ($cote - 1) * $pasM / 2;
        $sudOuest = [$latitude - $demi / $mLat, $longitude - $demi / $mLon];

        $points = [];
        for ($i = 0; $i < $cote; $i++) {            // du sud vers le nord
            for ($j = 0; $j < $cote; $j++) {        // de l'ouest vers l'est
                $points[] = [
                    round($sudOuest[0] + $i * $pasM / $mLat, 6),
                    round($sudOuest[1] + $j * $pasM / $mLon, 6),
                ];
            }
        }

        $z = [];
        $appels = 0;
        foreach (array_chunk($points, self::POINTS_PAR_APPEL) as $lot) {
            $reponse = self::lot($lot);
            $appels++;
            if ($reponse === null) {
                return ['grille' => null, 'appels' => $appels,
                    'message' => "L'IGN n'a pas répondu jusqu'au bout. Rien n'est enregistré."];
            }
            foreach ($reponse as $valeur) {
                // Hors couverture : on garde le trou plutôt qu'un chiffre
                // faux. Le rendu saura ne pas dessiner ce qu'il ignore.
                $z[] = $valeur <= self::HORS_COUVERTURE + 1.0 ? null : round($valeur, 2);
            }
        }
        if (count($z) !== $cote * $cote) {
            return ['grille' => null, 'appels' => $appels,
                'message' => 'Le service a rendu ' . count($z) . ' altitudes pour '
                    . ($cote * $cote) . ' demandées. Rien n\'est enregistré.'];
        }
        $connus = array_values(array_filter($z, static fn (?float $v): bool => $v !== null));
        if ($connus === []) {
            return ['grille' => null, 'appels' => $appels,
                'message' => "Aucun point n'est dans la couverture de l'IGN — France seulement."];
        }
        return ['grille' => ['cote' => $cote, 'pas' => $pasM, 'sud_ouest' => $sudOuest, 'z' => $z],
            'appels' => $appels,
            'message' => sprintf('%d points relevés en %d appel(s) — de %s à %s m.',
                count($z), $appels, round(min($connus)), round(max($connus)))];
    }

    /**
     * Un lot de points. `null` si le service n'a pas répondu comme prévu.
     *
     * @param list<array{0: float, 1: float}> $points
     * @return list<float>|null
     */
    private static function lot(array $points): ?array
    {
        $curl = curl_init(self::URL . '?' . http_build_query([
            'lon' => implode('|', array_column($points, 1)),
            'lat' => implode('|', array_column($points, 0)),
            'resource' => 'ign_rge_alti_wld',
        ]));
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            // Plus long que pour un point : on en demande cent cinquante.
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_USERAGENT => 'syntropia (atelier agroforestier syntropique)',
        ]);
        $corps = curl_exec($curl);
        $code = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        if (!is_string($corps) || $code !== 200) {
            return null;
        }
        $json = json_decode($corps, true);
        if (!is_array($json['elevations'] ?? null)) {
            return null;
        }
        $z = [];
        foreach ($json['elevations'] as $point) {
            if (!is_numeric($point['z'] ?? null)) {
                return null;
            }
            $z[] = (float) $point['z'];
        }
        return $z;
    }
}
