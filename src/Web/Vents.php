<?php

declare(strict_types=1);

namespace Syntropia\Web;

/**
 * La rose des vents d'un lieu, calculée à la demande.
 *
 * Port de `application/src/Web/Vents.php` (dépôt de référence, 187
 * lignes), sans changement de fond — seul le namespace change. Voir
 * CLAUDE.md § Point de reprise, chantier « carte interactive du terrain »,
 * Phase 8.
 *
 * **D'où viennent les données** : l'archive de réanalyse ERA5, servie par
 * `open-meteo.com` — sans clé, en **CC BY 4.0**, et **gratuite pour un
 * usage non commercial**, ce que l'atelier est et restera. L'attribution
 * s'affiche avec la rose : c'est la contrepartie de la licence.
 *
 * ⚠ **LA DIRECTION EST CELLE D'OÙ VIENT LE VENT** — convention
 * météorologique. Un secteur « sud » désigne un vent **venu du sud, qui va
 * vers le nord**.
 *
 * ⚠ **Et la maille d'ERA5 est d'environ 25 km** : la rose donne le régime
 * général, jamais le couloir d'un vallon. C'est exactement pour cela que le
 * calque des vents se trace à la main par-dessus.
 */
final class Vents
{
    private const URL = 'https://archive-api.open-meteo.com/v1/archive';

    /** Dix ans font 2,4 Mo de JSON ; cinq suffisent à une rose stable. */
    public const ANNEES_DEFAUT = 5;
    public const ANNEES_MAX = 10;

    private const DELAI_SECONDES = 25;

    /**
     * Le seuil au-dessus duquel un vent compte pour un brise-vent.
     *
     * **Aucun seuil de vent n'est chiffré dans le corpus syntropique.**
     * 20 km/h est une borne d'usage — la limite haute de la « jolie brise »
     * qui agite les branches — affichée telle quelle pour qu'on puisse la
     * discuter.
     */
    public const SEUIL_FORT_KMH = 20.0;

    /** Les seize secteurs, dans l'ordre trigonométrique depuis le nord. */
    public const SECTEURS = ['N', 'NNE', 'NE', 'ENE', 'E', 'ESE', 'SE', 'SSE',
                             'S', 'SSO', 'SO', 'OSO', 'O', 'ONO', 'NO', 'NNO'];

    /**
     * La rose d'un point, sur les dernières années complètes.
     *
     * @return array{rose: ?array{secteurs: list<array<string, mixed>>, heures: int,
     *               vitesse_moyenne: float, vitesse_max: float, dominant: string,
     *               dominant_fort: string, debut: string, fin: string, annees: int},
     *               message: string}
     */
    public static function rose(float $latitude, float $longitude,
                                int $annees = self::ANNEES_DEFAUT): array
    {
        $annees = max(1, min(self::ANNEES_MAX, $annees));
        if (!function_exists('curl_init')) {
            return ['rose' => null, 'message' => "Le calcul n'est pas disponible ici."];
        }
        // La dernière année complète : demander l'année en cours mêlerait une
        // saison à une moyenne, et une rose se lit sur des années entières.
        $fin = (int) date('Y') - 1;
        $debut = $fin - $annees + 1;

        $curl = curl_init(self::URL . '?' . http_build_query([
            'latitude' => round($latitude, 4), 'longitude' => round($longitude, 4),
            'start_date' => $debut . '-01-01', 'end_date' => $fin . '-12-31',
            'hourly' => 'wind_speed_10m,wind_direction_10m',
        ]));
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::DELAI_SECONDES,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_USERAGENT => 'syntropia (atelier agroforestier syntropique)',
        ]);
        $corps = curl_exec($curl);
        $code = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        if (!is_string($corps) || $code !== 200) {
            return ['rose' => null,
                'message' => "Le service météo n'a pas répondu. La rose se retrouve aussi "
                    . "sur les fiches climatologiques de la station la plus proche."];
        }
        $json = json_decode($corps, true);
        $directions = $json['hourly']['wind_direction_10m'] ?? null;
        $vitesses = $json['hourly']['wind_speed_10m'] ?? null;
        if (!is_array($directions) || !is_array($vitesses)) {
            return ['rose' => null, 'message' => 'Réponse inattendue du service.'];
        }
        return self::agreger($directions, $vitesses, $debut, $fin, $annees);
    }

    /**
     * Compter les heures par secteur — et distinguer le vent fréquent du
     * vent fort, qui ne viennent pas toujours du même côté.
     *
     * @param list<mixed> $directions
     * @param list<mixed> $vitesses
     * @return array{rose: ?array<string, mixed>, message: string}
     */
    private static function agreger(array $directions, array $vitesses,
                                    int $debut, int $fin, int $annees): array
    {
        $n = count(self::SECTEURS);
        $heures = array_fill(0, $n, 0);
        $sommeVitesse = array_fill(0, $n, 0.0);
        $forts = array_fill(0, $n, 0);
        $total = 0;
        $totalForts = 0;
        $somme = 0.0;
        $max = 0.0;

        foreach ($directions as $i => $angle) {
            $vitesse = $vitesses[$i] ?? null;
            if (!is_numeric($angle) || !is_numeric($vitesse)) {
                continue;
            }
            $angle = (float) $angle;
            $vitesse = (float) $vitesse;
            // Chaque secteur fait 22,5° et se centre sur sa direction : le
            // nord va donc de 348,75° à 11,25°, d'où le décalage.
            $secteur = (int) (fmod($angle + 11.25 + 360.0, 360.0) / 22.5) % $n;
            $heures[$secteur]++;
            $sommeVitesse[$secteur] += $vitesse;
            $total++;
            $somme += $vitesse;
            $max = max($max, $vitesse);
            if ($vitesse >= self::SEUIL_FORT_KMH) {
                $forts[$secteur]++;
                $totalForts++;
            }
        }
        if ($total === 0) {
            return ['rose' => null, 'message' => "Aucune heure exploitable — "
                . "le point est peut-être hors de la couverture du modèle."];
        }

        $secteurs = [];
        foreach (self::SECTEURS as $i => $nom) {
            $secteurs[] = [
                'nom' => $nom,
                'azimut' => $i * 22.5,
                'part' => round(100 * $heures[$i] / $total, 1),
                'part_forte' => $totalForts > 0 ? round(100 * $forts[$i] / $totalForts, 1) : 0.0,
                'vitesse_moyenne' => $heures[$i] > 0 ? round($sommeVitesse[$i] / $heures[$i], 1) : 0.0,
            ];
        }
        $parPart = $secteurs;
        usort($parPart, static fn (array $a, array $b): int => $b['part'] <=> $a['part']);
        $parForce = $secteurs;
        usort($parForce, static fn (array $a, array $b): int => $b['part_forte'] <=> $a['part_forte']);

        return ['rose' => [
            'secteurs' => $secteurs,
            'heures' => $total,
            'annees' => $annees,
            'debut' => (string) $debut,
            'fin' => (string) $fin,
            'vitesse_moyenne' => round($somme / $total, 1),
            'vitesse_max' => round($max),
            'part_forte' => round(100 * $totalForts / $total, 1),
            'dominant' => $parPart[0]['nom'],
            'dominant_fort' => $parForce[0]['nom'],
        ], 'message' => sprintf(
            '%d heures relevées de %d à %d. Vent moyen %s km/h, pointe à %d km/h.',
            $total, $debut, $fin, str_replace('.', ',', (string) round($somme / $total, 1)), (int) $max)];
    }

    /** Le nom long d'un secteur — ce qu'on écrit dans une phrase. */
    public static function nomLong(string $secteur): string
    {
        $noms = ['N' => 'nord', 'NNE' => 'nord-nord-est', 'NE' => 'nord-est',
            'ENE' => 'est-nord-est', 'E' => 'est', 'ESE' => 'est-sud-est',
            'SE' => 'sud-est', 'SSE' => 'sud-sud-est', 'S' => 'sud',
            'SSO' => 'sud-sud-ouest', 'SO' => 'sud-ouest', 'OSO' => 'ouest-sud-ouest',
            'O' => 'ouest', 'ONO' => 'ouest-nord-ouest', 'NO' => 'nord-ouest',
            'NNO' => 'nord-nord-ouest'];
        return $noms[$secteur] ?? $secteur;
    }
}
