<?php

declare(strict_types=1);

namespace Syntropia\Web;

/**
 * La zone de rusticité USDA d'un lieu, estimée depuis l'historique météo.
 *
 * **La définition** : la zone USDA est la moyenne, sur trente ans, du
 * minimum de température de chaque année (le jour le plus froid de
 * l'année), rangée par tranches de 10 °F — 5 °F pour la sous-zone a/b.
 * Aucun service USDA n'accepte de coordonnées hors des États-Unis : on
 * refait le calcul nous-mêmes, sur la période de référence 1991-2020
 * (celle de la carte USDA 2023).
 *
 * **D'où viennent les données** : l'archive de réanalyse d'Open-Meteo, la
 * même source que la rose des vents (`Vents.php`) — sans clé, CC BY 4.0,
 * gratuite pour un usage non commercial. Un seul appel, minima quotidiens.
 *
 * ⚠ **C'est une estimation.** La maille est de quelques kilomètres : une
 * cuvette où l'air froid s'accumule est plus froide que sa maille, un
 * coteau abrité l'est moins. Un écart d'une demi-zone avec une carte
 * publiée est normal ; le froid réel du terrain, lui, ne s'observe que
 * sur place.
 */
final class ZoneUsda
{
    private const URL = 'https://archive-api.open-meteo.com/v1/archive';

    public const PERIODE_DEBUT = 1991;
    public const PERIODE_FIN = 2020;

    private const DELAI_SECONDES = 30;

    /**
     * @return array{zone: ?string, minimum_c: ?float, annees: int, message: string}
     */
    public static function estimer(float $latitude, float $longitude): array
    {
        $vide = static fn (string $message): array =>
            ['zone' => null, 'minimum_c' => null, 'annees' => 0, 'message' => $message];

        if (!function_exists('curl_init')) {
            return $vide("Le calcul n'est pas disponible ici.");
        }
        $curl = curl_init(self::URL . '?' . http_build_query([
            'latitude' => round($latitude, 4), 'longitude' => round($longitude, 4),
            'start_date' => self::PERIODE_DEBUT . '-01-01', 'end_date' => self::PERIODE_FIN . '-12-31',
            'daily' => 'temperature_2m_min', 'timezone' => 'UTC',
        ]));
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::DELAI_SECONDES,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_USERAGENT => 'syntropia (atelier agroforestier syntropique)',
        ]);
        $corps = curl_exec($curl);
        $code = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        if (!is_string($corps) || $code !== 200) {
            return $vide("Le service météo n'a pas répondu — réessayez dans un instant, "
                . 'ou saisissez la zone si vous la connaissez.');
        }
        $json = json_decode($corps, true);
        $jours = $json['daily']['time'] ?? null;
        $minima = $json['daily']['temperature_2m_min'] ?? null;
        if (!is_array($jours) || !is_array($minima)) {
            return $vide('Réponse inattendue du service.');
        }
        return self::depuisMinima($jours, $minima);
    }

    /**
     * @param list<mixed> $jours   dates « AAAA-MM-JJ »
     * @param list<mixed> $minima  minimum quotidien, en °C
     * @return array{zone: ?string, minimum_c: ?float, annees: int, message: string}
     */
    public static function depuisMinima(array $jours, array $minima): array
    {
        $parAnnee = [];
        foreach ($jours as $i => $jour) {
            $t = $minima[$i] ?? null;
            if (!is_string($jour) || !is_numeric($t)) {
                continue;
            }
            $annee = substr($jour, 0, 4);
            $parAnnee[$annee] = min($parAnnee[$annee] ?? INF, (float) $t);
        }
        if (count($parAnnee) < 10) {
            return ['zone' => null, 'minimum_c' => null, 'annees' => count($parAnnee),
                'message' => 'Trop peu d\'années sont exploitables. Le point est peut-être hors de la '
                    . 'couverture du modèle météo.'];
        }
        $moyenne = array_sum($parAnnee) / count($parAnnee);
        $zone = self::zoneDepuisCelsius($moyenne);

        return ['zone' => $zone, 'minimum_c' => round($moyenne, 1), 'annees' => count($parAnnee),
            'message' => sprintf(
                'Zone %s. Le jour le plus froid de l\'année est à %s °C en moyenne (%d ans, %d-%d).',
                $zone, str_replace('.', ',', (string) round($moyenne, 1)), count($parAnnee),
                self::PERIODE_DEBUT, self::PERIODE_FIN)];
    }

    /** 1a commence à −60 °F ; chaque tranche de 5 °F ouvre la sous-zone suivante. */
    public static function zoneDepuisCelsius(float $moyenneC): string
    {
        $fahrenheit = $moyenneC * 9 / 5 + 32;
        $tranche = (int) floor(($fahrenheit + 60) / 5);
        $tranche = max(0, min(25, $tranche));
        return (intdiv($tranche, 2) + 1) . ($tranche % 2 === 0 ? 'a' : 'b');
    }
}
