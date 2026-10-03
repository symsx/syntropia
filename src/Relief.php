<?php

declare(strict_types=1);

namespace Syntropia;

/**
 * Le relief — ce qu'une grille d'altitudes permet de voir et de décider.
 *
 * Port de `application/src/Relief.php` (dépôt de référence, 226 lignes),
 * réduit : `iso()` (projection isométrique, pour la vue 3D) n'est pas
 * porté — chantier « carte interactive », Phase 9, exclut volontairement
 * la vue 3D et `relief.php`. Voir CLAUDE.md § Point de reprise.
 *
 * **Trois choses en sortent, et chacune commande un geste de terrain** :
 *
 * - **la pente et son sens**, qui décident de l'orientation des lignes —
 *   nord-sud pour l'ombre, ou suivant les courbes pour retenir l'eau ;
 * - **les courbes de niveau**, que le corpus demande de tracer avant tout
 *   terrassement — c'est sur elles que se posent les baissières ;
 * - rien de plus ici : la vue en volume reste hors périmètre.
 *
 * ⚠ **Ce n'est pas un modèle du terrain, c'est un modèle de l'IGN.** La
 * maille est de 5 m ; un talus d'un mètre, une baissière, un fossé n'y
 * figurent pas. Le relevé au niveau égyptien reste le seul qui voie ce
 * qu'on va creuser. La grille dit la forme d'ensemble, non le détail.
 */
final class Relief
{
    /**
     * La pente moyenne et sa direction, par régression sur toute la grille.
     *
     * On ajuste un plan z = a·x + b·y + c aux points connus — moindres
     * carrés —, et la normale de ce plan donne la pente et son azimut. Un
     * simple écart entre deux bords se ferait piéger par un creux ; la
     * régression, non.
     *
     * L'azimut est celui de la **descente**, en degrés depuis le nord, dans
     * le sens des aiguilles d'une montre : 180° veut dire « ça descend vers
     * le sud », donc une pente exposée au sud.
     *
     * @param list<?float> $z ligne par ligne, du sud au nord
     * @return array{pente_pct: ?float, azimut: ?int, exposition: ?string,
     *               denivele_m: ?float, mini: ?float, maxi: ?float}
     */
    public static function pente(array $z, int $cote, float $pas): array
    {
        $n = 0;
        $sx = $sy = $sz = $sxx = $syy = $sxy = $sxz = $syz = 0.0;
        $connus = [];
        foreach ($z as $i => $valeur) {
            if ($valeur === null) {
                continue;
            }
            // x vers l'est, y vers le nord, en mètres depuis le coin sud-ouest.
            $x = ($i % $cote) * $pas;
            $y = intdiv($i, $cote) * $pas;
            $n++;
            $sx += $x; $sy += $y; $sz += $valeur;
            $sxx += $x * $x; $syy += $y * $y; $sxy += $x * $y;
            $sxz += $x * $valeur; $syz += $y * $valeur;
            $connus[] = $valeur;
        }
        if ($n < 3) {
            return ['pente_pct' => null, 'azimut' => null, 'exposition' => null,
                'denivele_m' => null, 'mini' => null, 'maxi' => null];
        }
        // Système normal 3×3, résolu par les déterminants.
        $m = [[$sxx, $sxy, $sx], [$sxy, $syy, $sy], [$sx, $sy, (float) $n]];
        $v = [$sxz, $syz, $sz];
        $det = self::det3($m);
        if (abs($det) < 1e-9) {
            return ['pente_pct' => null, 'azimut' => null, 'exposition' => null,
                'denivele_m' => round(max($connus) - min($connus), 2),
                'mini' => min($connus), 'maxi' => max($connus)];
        }
        $a = self::det3(self::remplace($m, 0, $v)) / $det;   // pente vers l'est
        $b = self::det3(self::remplace($m, 1, $v)) / $det;   // pente vers le nord

        $pente = sqrt($a * $a + $b * $b);
        // La descente va dans le sens opposé au gradient.
        $azimut = null;
        $exposition = null;
        if ($pente > 1e-6) {
            $azimut = (int) round(fmod(rad2deg(atan2(-$a, -$b)) + 360.0, 360.0));
            $exposition = self::rose($azimut);
        }
        return ['pente_pct' => round($pente * 100, 1), 'azimut' => $azimut,
            'exposition' => $exposition,
            'denivele_m' => round(max($connus) - min($connus), 2),
            'mini' => min($connus), 'maxi' => max($connus)];
    }

    /** Le point cardinal d'un azimut — huit secteurs de 45°. */
    public static function rose(int $azimut): string
    {
        $noms = ['nord', 'nord-est', 'est', 'sud-est', 'sud', 'sud-ouest', 'ouest', 'nord-ouest'];
        return $noms[(int) round(fmod($azimut + 360, 360) / 45) % 8];
    }

    /**
     * Les courbes de niveau, par la méthode des carrés marchants.
     *
     * Chaque carré de quatre points est coupé par le niveau cherché ; on
     * interpole sur les arêtes, et l'on obtient des segments. Ils ne sont
     * pas chaînés : pour dessiner, cela suffit, et cela évite de coudre des
     * boucles là où la grille a des trous.
     *
     * @param list<?float> $z
     * @return list<array{0: float, 1: float, 2: float, 3: float}> segments en mètres
     */
    public static function courbe(array $z, int $cote, float $pas, float $niveau): array
    {
        $segments = [];
        for ($i = 0; $i < $cote - 1; $i++) {
            for ($j = 0; $j < $cote - 1; $j++) {
                // Les quatre coins, dans le sens trigonométrique.
                $coins = [
                    [$j * $pas, $i * $pas, $z[$i * $cote + $j]],
                    [($j + 1) * $pas, $i * $pas, $z[$i * $cote + $j + 1]],
                    [($j + 1) * $pas, ($i + 1) * $pas, $z[($i + 1) * $cote + $j + 1]],
                    [$j * $pas, ($i + 1) * $pas, $z[($i + 1) * $cote + $j]],
                ];
                $points = [];
                for ($k = 0; $k < 4; $k++) {
                    $p = $coins[$k];
                    $q = $coins[($k + 1) % 4];
                    if ($p[2] === null || $q[2] === null) {
                        continue;   // un trou : cette arête ne dit rien
                    }
                    if (($p[2] < $niveau && $q[2] >= $niveau) || ($q[2] < $niveau && $p[2] >= $niveau)) {
                        $t = ($niveau - $p[2]) / ($q[2] - $p[2]);
                        $points[] = [$p[0] + $t * ($q[0] - $p[0]), $p[1] + $t * ($q[1] - $p[1])];
                    }
                }
                // Deux intersections : un segment. Quatre : un col — on trace
                // les deux paires dans l'ordre trouvé, ce qui suffit à l'œil.
                for ($k = 0; $k + 1 < count($points); $k += 2) {
                    $segments[] = [round($points[$k][0], 2), round($points[$k][1], 2),
                                   round($points[$k + 1][0], 2), round($points[$k + 1][1], 2)];
                }
            }
        }
        return $segments;
    }

    /**
     * Les niveaux à tracer : un pas rond, et jamais plus de 25 courbes.
     *
     * @return list<float>
     */
    public static function niveaux(float $mini, float $maxi): array
    {
        $amplitude = $maxi - $mini;
        if ($amplitude <= 0) {
            return [];
        }
        $pas = 1.0;
        foreach ([0.5, 1.0, 2.0, 5.0, 10.0, 20.0, 50.0] as $candidat) {
            $pas = $candidat;
            if ($amplitude / $candidat <= 25) {
                break;
            }
        }
        $niveaux = [];
        for ($n = ceil($mini / $pas) * $pas; $n <= $maxi; $n += $pas) {
            $niveaux[] = round($n, 2);
        }
        return $niveaux;
    }

    /**
     * Les courbes d'une grille, **en coordonnées géographiques**, prêtes à
     * poser sur la carte.
     *
     * La grille garde ses altitudes et son coin sud-ouest ; les courbes se
     * calculent en mètres, puis se ramènent en degrés par la projection
     * locale plane — la même que partout ailleurs dans l'atelier.
     *
     * **Une borne de lisibilité** : au-delà de `$maximum` segments, on
     * espace les niveaux plutôt que de noircir la carte.
     *
     * @param list<?float> $z
     * @param array{0: float, 1: float} $sudOuest
     * @return list<array{altitude: float, points: list<array{0: float, 1: float}>}>
     */
    public static function courbesGeo(array $z, int $cote, float $pas,
                                      array $sudOuest, int $maximum = 700): array
    {
        $connus = array_values(array_filter($z, static fn (?float $v): bool => $v !== null));
        if (count($connus) < 4) {
            return [];
        }
        $mLat = 111320.0;
        $mLon = 111320.0 * cos(deg2rad($sudOuest[0]));
        if ($mLon < 1.0) {
            return [];
        }
        $niveaux = self::niveaux(min($connus), max($connus));
        $courbes = [];
        $rendus = 0;
        // Un niveau sur deux, puis sur trois, tant que le compte dépasse :
        // mieux vaut des courbes espacées qu'un dessin illisible.
        $saut = 1;
        do {
            $courbes = [];
            $rendus = 0;
            foreach ($niveaux as $rang => $niveau) {
                if ($rang % $saut !== 0) {
                    continue;
                }
                foreach (self::courbe($z, $cote, $pas, $niveau) as $segment) {
                    $courbes[] = ['altitude' => $niveau, 'points' => [
                        [round($sudOuest[0] + $segment[1] / $mLat, 6),
                         round($sudOuest[1] + $segment[0] / $mLon, 6)],
                        [round($sudOuest[0] + $segment[3] / $mLat, 6),
                         round($sudOuest[1] + $segment[2] / $mLon, 6)],
                    ]];
                    $rendus++;
                }
            }
            $saut++;
        } while ($rendus > $maximum && $saut <= 6);

        return $courbes;
    }

    /** @param array<int, array<int, float>> $m */
    private static function det3(array $m): float
    {
        return $m[0][0] * ($m[1][1] * $m[2][2] - $m[1][2] * $m[2][1])
             - $m[0][1] * ($m[1][0] * $m[2][2] - $m[1][2] * $m[2][0])
             + $m[0][2] * ($m[1][0] * $m[2][1] - $m[1][1] * $m[2][0]);
    }

    /**
     * @param array<int, array<int, float>> $m
     * @param list<float> $v
     * @return array<int, array<int, float>>
     */
    private static function remplace(array $m, int $colonne, array $v): array
    {
        foreach ($v as $i => $valeur) {
            $m[$i][$colonne] = $valeur;
        }
        return $m;
    }
}
