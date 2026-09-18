<?php

declare(strict_types=1);

namespace Syntropia\Web;

use PDO;
use Syntropia\Database;

/**
 * La carte à calques — ce qu'on dessine sur le terrain, et où.
 *
 * Porté de `application/src/Web/Calques.php` (dépôt de référence, 379
 * lignes), sans changement de fond — schéma déjà identique
 * (`calque_element`, `parcelle`), donc rien à adapter côté données.
 * Seul le namespace change. Voir CLAUDE.md, chantier « carte
 * interactive du terrain », et `chantier-carte-interactive.md` § Phase 4.
 *
 * **La géométrie est une liste de couples [latitude, longitude]**, cinq
 * décimales — environ un mètre —, en JSON dans une colonne texte. Les
 * calculs de surface et de longueur se font ici en mètres, par une
 * projection locale plane : à l'échelle d'un terrain, l'erreur est
 * négligeable, et rien ne dépend d'une bibliothèque.
 */
final class Calques
{
    /**
     * Les dix calques, dans l'ordre du voyage.
     *
     * @var array<string, array{libelle: string, teinte: string, aide: string, types: list<string>}>
     */
    public const CALQUES = [
        'fond' => ['libelle' => 'Le fond — les limites du terrain',
            'teinte' => '#3A3A3A',
            'aide' => "Les limites du terrain, en polygone. Le nom du lieu. L'échelle et le nord sont donnés par la carte.",
            'types' => ['polygone', 'point']],
        'relief' => ['libelle' => 'Le relief',
            'teinte' => '#8A6A16',
            'aide' => "Les courbes de niveau, les points hauts et bas, le sens de la pente. La grille d'altitudes IGN se relève depuis cette carte.",
            'types' => ['ligne', 'point', 'grille']],
        'ombres' => ['libelle' => 'Les ombres — ce qui en fait',
            'teinte' => '#2F5A6B',
            'aide' => "Un arbre, un bâtiment, un coteau : sa position et sa hauteur. La période d'ombre se relève sur ShadeMap — les liens sont juste en dessous.",
            'types' => ['point', 'polygone']],
        'vents' => ['libelle' => 'Les vents',
            'teinte' => '#5E4A6E',
            'aide' => "Une flèche par vent, dans le sens où il souffle. Les dominants en trait épais, les autres en trait fin — et les couloirs que la topographie crée n'ont pas toujours la direction du dominant.",
            'types' => ['ligne']],
        'eau' => ["libelle" => "L'eau",
            'teinte' => '#2E6E9E',
            'aide' => "Où elle entre, par où elle passe, où elle sort. Les zones humides et sèches, les surfaces qui captent, les réserves.",
            'types' => ['ligne', 'polygone', 'point']],
        'sol' => ['libelle' => 'Le sol',
            'teinte' => '#5A4632',
            'aide' => "Les zones de nature différente, et les points où les relevés sont faits — toujours au même endroit.",
            'types' => ['polygone', 'point']],
        'circulations' => ['libelle' => 'Circulations et constructions',
            'teinte' => '#9A4A2C',
            'aide' => "Les accès, les chemins — même peu utilisés —, les bâtiments, les clôtures, les surfaces dures.",
            'types' => ['ligne', 'polygone']],
        'vegetation' => ['libelle' => 'La végétation existante',
            'teinte' => '#28684A',
            'aide' => "Bois, haies, arbres isolés, souches qui rejettent. Un arbre ici nourrit aussi le calque des ombres.",
            'types' => ['point', 'polygone']],
        'ressenti' => ['libelle' => 'Le ressenti',
            'teinte' => '#B95F4A',
            'aide' => "Où l'on se sent bien, où l'on s'assiérait, ce qui ferme et ce qui ouvre. Le premier à remplir, avant l'analyse.",
            'types' => ['point']],
        'parcelles' => ['libelle' => 'Les parcelles',
            'teinte' => '#606B22',
            'aide' => "Le contour de chaque parcelle, posé par ses dimensions ou tracé à la main.",
            'types' => ['polygone']],
    ];

    public const TYPES = ['point', 'ligne', 'polygone', 'grille'];

    public function __construct(private ?PDO $pdo = null) {}

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::syntropia();
    }

    /**
     * Les éléments d'un site, géométrie et propriétés décodées.
     *
     * @return list<array<string, mixed>>
     */
    public function dUnSite(int $siteId): array
    {
        $req = $this->pdo()->prepare(
            'SELECT * FROM calque_element WHERE site_id = ? ORDER BY calque, id');
        $req->execute([$siteId]);
        return array_map([self::class, 'decoder'], $req->fetchAll());
    }

    /** @return array<string, mixed>|null */
    public function parId(int $id, int $siteId): ?array
    {
        $req = $this->pdo()->prepare(
            'SELECT * FROM calque_element WHERE id = ? AND site_id = ?');
        $req->execute([$id, $siteId]);
        $ligne = $req->fetch();
        return $ligne === false ? null : self::decoder($ligne);
    }

    /**
     * Les parcelles d'un site avec ce qu'il faut pour les poser : leurs
     * dimensions, et l'élément qui les porte déjà s'il existe.
     *
     * `largeur_m` se lit directement depuis la colonne depuis le chantier
     * « plan de parcelle », Phase 2 (2026-09-18) — avant cette date,
     * cette méthode la recalculait depuis `nb_planches`/
     * `largeur_planche_m`/`passe_pied_m`, seules valeurs alors fiables ;
     * `Parcelles::enregistrerPlan()` écrit maintenant une vraie
     * `largeur_m` à chaque enregistrement du plan, plus besoin de la
     * reconstituer ici. Voir `chantier-plan-de-parcelle.md` § Phase 2.
     *
     * @return list<array<string, mixed>>
     */
    public function parcellesAPlacer(int $siteId): array
    {
        $req = $this->pdo()->prepare(
            'SELECT p.id, p.code, p.nom, p.surface_m2, p.nb_planches, p.longueur_m, p.largeur_m,
                    (SELECT c.id FROM calque_element c
                      WHERE c.parcelle_id = p.id AND c.calque = "parcelles" LIMIT 1) AS element_id
               FROM parcelle p WHERE p.site_id = ? ORDER BY p.code');
        $req->execute([$siteId]);
        $parcelles = [];
        foreach ($req->fetchAll() as $p) {
            $p['largeur_m'] = $p['largeur_m'] !== null ? (float) $p['largeur_m'] : null;
            $p['longueur_m'] = $p['longueur_m'] !== null ? (float) $p['longueur_m'] : null;
            $parcelles[] = $p;
        }
        return $parcelles;
    }

    /**
     * L'emprise des limites du terrain — la boîte des polygones du fond.
     *
     * **C'est le prérequis de tout ce qui se cherche « autour du terrain »** :
     * sans limites, une recherche d'eau ramènerait la rivière du département.
     *
     * @param float $margeM ce qu'on garde au-delà des limites
     * @return array{sud: float, ouest: float, nord: float, est: float}|null
     */
    public function empriseDuTerrain(int $siteId, float $margeM = 0.0): ?array
    {
        $sud = 90.0; $nord = -90.0; $ouest = 180.0; $est = -180.0;
        $vus = 0;
        foreach ($this->dUnSite($siteId) as $element) {
            if ($element['calque'] !== 'fond' || $element['type'] !== 'polygone') {
                continue;
            }
            foreach ($element['geometrie'] as $point) {
                $sud = min($sud, $point[0]); $nord = max($nord, $point[0]);
                $ouest = min($ouest, $point[1]); $est = max($est, $point[1]);
                $vus++;
            }
        }
        if ($vus < 3) {
            return null;
        }
        if ($margeM > 0) {
            [$mLat, $mLon] = self::metresParDegre(($nord + $sud) / 2);
            $sud -= $margeM / $mLat; $nord += $margeM / $mLat;
            if ($mLon > 1.0) {
                $ouest -= $margeM / $mLon; $est += $margeM / $mLon;
            }
        }
        return ['sud' => $sud, 'ouest' => $ouest, 'nord' => $nord, 'est' => $est];
    }

    /**
     * Ajoute un élément. Refuse ce qui n'est pas une géométrie.
     *
     * @param array<string, mixed> $valeurs
     * @return array{id: ?int, erreurs: array<string, string>}
     */
    public function ajouter(int $siteId, array $valeurs): array
    {
        [$propre, $erreurs] = $this->valider($siteId, $valeurs, true);
        if ($erreurs !== []) {
            return ['id' => null, 'erreurs' => $erreurs];
        }
        $this->pdo()->prepare(
            'INSERT INTO calque_element (site_id, parcelle_id, calque, type, libelle, geometrie, proprietes)
             VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$siteId, $propre['parcelle_id'], $propre['calque'], $propre['type'],
                $propre['libelle'], $propre['geometrie'], $propre['proprietes']]);
        return ['id' => (int) $this->pdo()->lastInsertId(), 'erreurs' => []];
    }

    /**
     * Modifie la géométrie, le libellé ou les propriétés d'un élément.
     *
     * @param array<string, mixed> $valeurs
     * @return array<string, string> les erreurs
     */
    public function modifier(int $id, int $siteId, array $valeurs): array
    {
        $existant = $this->parId($id, $siteId);
        if ($existant === null) {
            return ['id' => 'Élément inconnu.'];
        }
        $valeurs += ['calque' => $existant['calque'], 'type' => $existant['type']];
        if (!array_key_exists('geometrie', $valeurs)) {
            $valeurs['geometrie'] = json_encode($existant['geometrie']);
        }
        [$propre, $erreurs] = $this->valider($siteId, $valeurs, false);
        if ($erreurs !== []) {
            return $erreurs;
        }
        $this->pdo()->prepare(
            'UPDATE calque_element SET libelle = ?, geometrie = ?, proprietes = ?, parcelle_id = ?
              WHERE id = ? AND site_id = ?')
            ->execute([$propre['libelle'] ?? $existant['libelle'], $propre['geometrie'],
                $propre['proprietes'] ?? ($existant['proprietes'] === null ? null : json_encode($existant['proprietes'])),
                $propre['parcelle_id'] ?? $existant['parcelle_id'], $id, $siteId]);
        return [];
    }

    public function retirer(int $id, int $siteId): void
    {
        $this->pdo()->prepare('DELETE FROM calque_element WHERE id = ? AND site_id = ?')
            ->execute([$id, $siteId]);
    }

    // ------------------------------------------------------------------
    // Validation et géométrie
    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed> $valeurs
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    private function valider(int $siteId, array $valeurs, bool $creation): array
    {
        $erreurs = [];
        $calque = is_scalar($valeurs['calque'] ?? null) ? (string) $valeurs['calque'] : '';
        if (!isset(self::CALQUES[$calque])) {
            $erreurs['calque'] = 'Calque inconnu.';
        }
        $type = is_scalar($valeurs['type'] ?? null) ? (string) $valeurs['type'] : '';
        if (!in_array($type, self::TYPES, true)) {
            $erreurs['type'] = 'Type inconnu.';
        }
        $points = self::pointsDepuis($valeurs['geometrie'] ?? null);
        $minimum = ['point' => 1, 'ligne' => 2, 'polygone' => 3, 'grille' => 2][$type] ?? 1;
        // Deux refus distincts, parce qu'ils appellent deux gestes
        // différents : un tracé illisible vient du code qui l'envoie, un
        // tracé trop court vient de la main qui dessine.
        if ($points === null) {
            $erreurs['geometrie'] = "J'attends une liste de couples latitude, longitude, "
                . 'chacun sur Terre.';
        } elseif (count($points) < $minimum) {
            $erreurs['geometrie'] = "Un tracé demande au moins {$minimum} point(s).";
        }
        $libelle = is_scalar($valeurs['libelle'] ?? null) ? trim((string) $valeurs['libelle']) : null;
        if ($libelle !== null && mb_strlen($libelle) > 160) {
            $erreurs['libelle'] = 'Le libellé dépasse 160 caractères.';
        }
        $proprietes = null;
        if (isset($valeurs['proprietes']) && is_scalar($valeurs['proprietes'])
            && trim((string) $valeurs['proprietes']) !== '') {
            $decode = json_decode((string) $valeurs['proprietes'], true);
            if (!is_array($decode)) {
                $erreurs['proprietes'] = 'Propriétés illisibles.';
            } else {
                // La place d'une étiquette déplacée : un couple [latitude, longitude]
                // sur Terre, ou rien — jamais un texte, qui ferait disparaître l'étiquette.
                if (array_key_exists('etiquette', $decode)) {
                    $e = $decode['etiquette'];
                    if ($e === null) {
                        unset($decode['etiquette']);
                    } elseif (!is_array($e) || count($e) !== 2 || !is_numeric($e[0] ?? null)
                        || !is_numeric($e[1] ?? null) || abs((float) $e[0]) > 90 || abs((float) $e[1]) > 180) {
                        $erreurs['proprietes'] = "La position de l'étiquette est illisible.";
                    } else {
                        $decode['etiquette'] = [round((float) $e[0], 5), round((float) $e[1], 5)];
                    }
                }
                $proprietes = json_encode($decode, JSON_UNESCAPED_UNICODE);
            }
        }
        $parcelleId = null;
        if (isset($valeurs['parcelle_id']) && (string) $valeurs['parcelle_id'] !== '') {
            $parcelleId = (int) $valeurs['parcelle_id'];
            // Une parcelle d'un autre site ne se pose pas ici.
            $req = $this->pdo()->prepare('SELECT COUNT(*) FROM parcelle WHERE id = ? AND site_id = ?');
            $req->execute([$parcelleId, $siteId]);
            if ((int) $req->fetchColumn() === 0) {
                $erreurs['parcelle_id'] = "Cette parcelle n'est pas de ce site.";
            }
        }
        return [[
            'calque' => $calque, 'type' => $type, 'libelle' => $libelle === '' ? null : $libelle,
            'geometrie' => json_encode($points ?? []), 'proprietes' => $proprietes,
            'parcelle_id' => $parcelleId,
        ], $erreurs];
    }

    /**
     * Une liste de couples [latitude, longitude], depuis du JSON ou un tableau.
     *
     * @return list<array{0: float, 1: float}>|null
     */
    public static function pointsDepuis(mixed $brut): ?array
    {
        $liste = is_string($brut) ? json_decode($brut, true) : $brut;
        if (!is_array($liste)) {
            return null;
        }
        $points = [];
        foreach ($liste as $p) {
            if (!is_array($p) || count($p) < 2 || !is_numeric($p[0]) || !is_numeric($p[1])) {
                return null;
            }
            $lat = (float) $p[0];
            $lon = (float) $p[1];
            if (abs($lat) > 90 || abs($lon) > 180) {
                return null;
            }
            $points[] = [round($lat, 5), round($lon, 5)];
        }
        return $points;
    }

    /** @param array<string, mixed> $ligne */
    private static function decoder(array $ligne): array
    {
        $ligne['geometrie'] = self::pointsDepuis($ligne['geometrie']) ?? [];
        $ligne['proprietes'] = is_string($ligne['proprietes'] ?? null)
            ? (json_decode($ligne['proprietes'], true) ?: null) : null;
        return $ligne;
    }

    /**
     * Les mètres par degré, à une latitude : la projection locale plane.
     *
     * @return array{0: float, 1: float} mètres par degré de latitude, de longitude
     */
    public static function metresParDegre(float $latitude): array
    {
        return [111320.0, 111320.0 * cos(deg2rad($latitude))];
    }

    /**
     * La surface d'un polygone en m², par la formule du lacet sur la
     * projection locale.
     *
     * @param list<array{0: float, 1: float}> $points
     */
    public static function surfaceM2(array $points): float
    {
        if (count($points) < 3) {
            return 0.0;
        }
        [$mLat, $mLon] = self::metresParDegre($points[0][0]);
        $aire = 0.0;
        $n = count($points);
        for ($i = 0; $i < $n; $i++) {
            $a = $points[$i];
            $b = $points[($i + 1) % $n];
            $xa = ($a[1] - $points[0][1]) * $mLon; $ya = ($a[0] - $points[0][0]) * $mLat;
            $xb = ($b[1] - $points[0][1]) * $mLon; $yb = ($b[0] - $points[0][0]) * $mLat;
            $aire += $xa * $yb - $xb * $ya;
        }
        return round(abs($aire) / 2, 1);
    }

    /**
     * La longueur d'une ligne en mètres.
     *
     * @param list<array{0: float, 1: float}> $points
     */
    public static function longueurM(array $points): float
    {
        if (count($points) < 2) {
            return 0.0;
        }
        [$mLat, $mLon] = self::metresParDegre($points[0][0]);
        $total = 0.0;
        for ($i = 1, $n = count($points); $i < $n; $i++) {
            $dx = ($points[$i][1] - $points[$i - 1][1]) * $mLon;
            $dy = ($points[$i][0] - $points[$i - 1][0]) * $mLat;
            $total += sqrt($dx * $dx + $dy * $dy);
        }
        return round($total, 1);
    }
}
