<?php

declare(strict_types=1);

namespace Syntropia\Web;

/**
 * L'eau déjà cartographiée, demandée à OpenStreetMap.
 *
 * Port de `application/src/Web/Eau.php` (dépôt de référence, 277 lignes),
 * sans changement de fond — seul le namespace change. Voir CLAUDE.md
 * § Point de reprise, chantier « carte interactive du terrain », Phase 7.
 *
 * **Ce que cela fait gagner** : rivières, ruisseaux, fossés, mares, étangs,
 * zones humides et sources sont souvent déjà relevés par la communauté. Les
 * redessiner à la main serait du temps perdu — et **ce qui est posé ainsi
 * reste modifiable et complétable** : le porteur voit ce que la carte
 * ignore, à commencer par les fossés qu'il a creusés lui-même.
 *
 * **Overpass est un service communautaire, servi par des bénévoles.** D'où
 * trois égards : un seul appel par clic, une emprise bornée, un agent
 * identifiable — et **un message honnête quand il est occupé**.
 *
 * ⚠ **Ce n'est pas un relevé, c'est une carte** — faite par des gens, avec
 * les manques et les erreurs d'une carte. Un ruisseau busé, une source
 * tarie ou un fossé récent n'y sont pas.
 */
final class Eau
{
    private const URL = 'https://overpass-api.de/api/interpreter';

    private const DELAI_SECONDES = 30;

    /** Au-delà, on ne regarde plus un terrain mais un bassin versant. */
    public const COTE_MAX_M = 3000.0;

    /**
     * Ce qu'on garde autour des limites du terrain.
     *
     * **L'eau du voisin compte** — un ruisseau qui longe la clôture, un
     * étang de l'autre côté du chemin —, mais **la rivière à vingt
     * kilomètres, non**. Cent mètres est la distance à laquelle un écoulement
     * concerne encore la conception : au-delà, c'est du paysage.
     */
    public const MARGE_M = 100.0;

    /**
     * Ce qu'on demande, et **le mot français qui le nomme sur la carte**.
     *
     * Les clés sont les valeurs OpenStreetMap ; l'ordre n'a pas d'importance.
     */
    private const NATURES = [
        'river' => 'rivière', 'stream' => 'ruisseau', 'brook' => 'ruisseau',
        'ditch' => 'fossé', 'drain' => 'drain', 'canal' => 'canal',
        'weir' => 'seuil', 'dam' => 'barrage',
        'water' => "plan d'eau", 'wetland' => 'zone humide', 'spring' => 'source',
        'reservoir' => 'retenue', 'basin' => 'bassin', 'pond' => 'mare',
    ];

    /**
     * L'eau d'une emprise, prête à poser sur le calque.
     *
     * @param array{0: float, 1: float} $sudOuest latitude, longitude
     * @param array{0: float, 1: float} $nordEst
     * @return array{traces: list<array{type: string, points: list<array{0: float, 1: float}>,
     *                libelle: string, proprietes: array<string, mixed>}>, message: string}
     */
    public static function dansEmprise(array $sudOuest, array $nordEst): array
    {
        [$sud, $ouest] = $sudOuest;
        [$nord, $est] = $nordEst;
        if ($nord <= $sud || $est <= $ouest || abs($nord) > 90 || abs($est) > 180) {
            return ['traces' => [], 'message' => 'Emprise incohérente.'];
        }
        $mLat = 111320.0;
        $mLon = 111320.0 * cos(deg2rad(($nord + $sud) / 2));
        $cote = max(($nord - $sud) * $mLat, ($est - $ouest) * $mLon);
        if ($cote > self::COTE_MAX_M) {
            return ['traces' => [], 'message' => sprintf(
                'Zoomez. La recherche de l\'eau se limite à %d m de côté, et la vue en fait %d.',
                (int) self::COTE_MAX_M, (int) $cote)];
        }
        if (!function_exists('curl_init')) {
            return ['traces' => [], 'message' => "La recherche n'est pas disponible ici."];
        }

        $boite = sprintf('%.6f,%.6f,%.6f,%.6f', $sud, $ouest, $nord, $est);
        // `out geom` rend les coordonnées avec les objets : sans lui, il
        // faudrait un second appel pour résoudre les nœuds.
        $requete = "[out:json][timeout:25];("
            . "way[\"natural\"=\"water\"]({$boite});"
            . "way[\"natural\"=\"wetland\"]({$boite});"
            . "way[\"landuse\"=\"reservoir\"]({$boite});"
            . "way[\"waterway\"]({$boite});"
            . "node[\"natural\"=\"spring\"]({$boite});"
            . ");out geom;";

        $curl = curl_init(self::URL);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(['data' => $requete]),
            CURLOPT_TIMEOUT => self::DELAI_SECONDES,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_USERAGENT => 'syntropia (atelier agroforestier syntropique)',
        ]);
        $corps = curl_exec($curl);
        $code = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        if ($code === 429 || $code === 504) {
            return ['traces' => [], 'message' => "OpenStreetMap est occupé. C'est un "
                . "service tenu par des bénévoles. Réessayez dans un moment. "
                . "En attendant, l'eau se trace à la main."];
        }
        if (!is_string($corps) || $code !== 200) {
            return ['traces' => [], 'message' => "OpenStreetMap n'a pas répondu. "
                . "L'eau se trace toujours à la main."];
        }
        $json = json_decode($corps, true);
        if (!is_array($json['elements'] ?? null)) {
            return ['traces' => [], 'message' => 'Le service a donné une réponse inattendue.'];
        }

        $traces = [];
        $coupes = 0;
        foreach ($json['elements'] as $element) {
            $trace = self::normaliser(is_array($element) ? $element : []);
            if ($trace === null) {
                continue;
            }
            // ON NE DÉTOURE PAS UN FLEUVE. Une rivière traverse le
            // département ; ce qui intéresse la conception, c'est la portion
            // qui longe le terrain.
            if ($trace['type'] === 'ligne') {
                $morceaux = self::decouper($trace['points'], $sud, $ouest, $nord, $est);
                if ($morceaux === []) {
                    continue;
                }
                if (count($morceaux) > 1 || count($morceaux[0]) < count($trace['points'])) {
                    $coupes++;
                }
                foreach ($morceaux as $morceau) {
                    $traces[] = ['type' => 'ligne', 'points' => $morceau,
                        'libelle' => $trace['libelle'], 'proprietes' => $trace['proprietes']];
                }
                continue;
            }
            // Une surface entièrement dehors ne dit rien du terrain ; une
            // surface qui le touche se garde entière — un étang se comprend
            // mieux en entier qu'en tranche.
            if (!self::touche($trace['points'], $sud, $ouest, $nord, $est)) {
                continue;
            }
            $traces[] = $trace;
        }
        if ($traces === []) {
            return ['traces' => [], 'message' => "Aucune eau n'est cartographiée ici. "
                . "Il peut pourtant y en avoir : OpenStreetMap est une carte faite par "
                . "des personnes, et un fossé récent n'y figure pas."];
        }
        return ['traces' => $traces, 'message' => count($traces) . ' élément'
            . (count($traces) > 1 ? 's' : '') . " d'eau trouvé"
            . (count($traces) > 1 ? 's' : '') . ' sur OpenStreetMap'
            . ($coupes > 0 ? ', dont ' . $coupes . ' cours d\'eau ramené'
                . ($coupes > 1 ? 's' : '') . ' à ce qui longe le terrain' : '') . '.'];
    }

    /**
     * Découper une ligne à la boîte : on garde les morceaux qui la touchent.
     *
     * Le découpage se fait **segment par segment**, et un segment compte dès
     * qu'une de ses extrémités est dedans — la ligne sort donc d'un point au
     * plus de chaque côté, ce qui évite un trait tranché net au ras du
     * cadre. Deux morceaux séparés restent séparés.
     *
     * @param list<array{0: float, 1: float}> $points
     * @return list<list<array{0: float, 1: float}>>
     */
    private static function decouper(array $points, float $sud, float $ouest,
                                     float $nord, float $est): array
    {
        $dedans = static fn (array $p): bool =>
            $p[0] >= $sud && $p[0] <= $nord && $p[1] >= $ouest && $p[1] <= $est;
        $morceaux = [];
        $courant = [];
        $n = count($points);
        for ($i = 0; $i < $n; $i++) {
            $garde = $dedans($points[$i])
                || ($i > 0 && $dedans($points[$i - 1]))
                || ($i + 1 < $n && $dedans($points[$i + 1]));
            if ($garde) {
                $courant[] = $points[$i];
                continue;
            }
            if (count($courant) >= 2) {
                $morceaux[] = $courant;
            }
            $courant = [];
        }
        if (count($courant) >= 2) {
            $morceaux[] = $courant;
        }
        return $morceaux;
    }

    /**
     * Un tracé touche-t-il la boîte ? Vrai dès qu'un point y est.
     *
     * @param list<array{0: float, 1: float}> $points
     */
    private static function touche(array $points, float $sud, float $ouest,
                                   float $nord, float $est): bool
    {
        foreach ($points as $p) {
            if ($p[0] >= $sud && $p[0] <= $nord && $p[1] >= $ouest && $p[1] <= $est) {
                return true;
            }
        }
        return false;
    }

    /**
     * Un objet OpenStreetMap, ramené à un tracé de la carte.
     *
     * @param array<string, mixed> $element
     * @return array<string, mixed>|null
     */
    private static function normaliser(array $element): ?array
    {
        $tags = is_array($element['tags'] ?? null) ? $element['tags'] : [];
        $cle = $tags['waterway'] ?? $tags['natural'] ?? $tags['landuse'] ?? '';
        $nature = self::NATURES[$cle] ?? null;
        if ($nature === null) {
            return null;
        }

        if (($element['type'] ?? '') === 'node') {
            if (!is_numeric($element['lat'] ?? null) || !is_numeric($element['lon'] ?? null)) {
                return null;
            }
            $points = [[round((float) $element['lat'], 6), round((float) $element['lon'], 6)]];
            $type = 'point';
        } else {
            $geometrie = $element['geometry'] ?? null;
            if (!is_array($geometrie) || count($geometrie) < 2) {
                return null;
            }
            $points = [];
            foreach ($geometrie as $point) {
                if (!is_numeric($point['lat'] ?? null) || !is_numeric($point['lon'] ?? null)) {
                    return null;
                }
                $points[] = [round((float) $point['lat'], 6), round((float) $point['lon'], 6)];
            }
            // Un contour fermé est une surface — mare, étang, zone humide ;
            // ouvert, c'est un cours d'eau. La géométrie le dit mieux que
            // les étiquettes, qui varient d'un contributeur à l'autre.
            $ferme = count($points) > 3 && $points[0] === $points[count($points) - 1];
            if ($ferme) {
                array_pop($points);
            }
            $type = $ferme ? 'polygone' : 'ligne';
        }

        $nom = is_string($tags['name'] ?? null) ? trim($tags['name']) : '';
        return [
            'type' => $type,
            'points' => $points,
            'libelle' => mb_substr($nom !== '' ? $nom . ' — ' . $nature : ucfirst($nature), 0, 160),
            'proprietes' => ['nature' => $nature, 'source' => 'OpenStreetMap',
                'osm' => ($element['type'] ?? '?') . '/' . ($element['id'] ?? '?'),
                'importe_le' => date('Y-m-d')],
        ];
    }
}
