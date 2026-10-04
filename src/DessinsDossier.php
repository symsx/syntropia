<?php

declare(strict_types=1);

namespace Syntropia;

use Syntropia\Web\Calques;

/**
 * Les dessins du Dossier Projet — le terrain, le plan d'une parcelle, la frise du scénario —, en SVG pour le papier. Pur.
 *
 * Voir `chantier-dossier-projet.md`, Phase 2. Ce sont les dessins de l'écran (`calques.js`, `conception-parcelles.js`,
 * la frise de `conception-scenario.php`) **redessinés pour l'impression** : sans lien, sans survol, sans fond de carte
 * (décision du porteur, 2026-09-19 : les tracés seuls, sur fond blanc, avec échelle et nord). Chaque dessin a une
 * largeur fixe de 1000 unités : il s'imprime net à la largeur de la page, et ses traits et ses textes gardent la même
 * taille d'une parcelle à l'autre.
 */
final class DessinsDossier
{
    private const LARGEUR = 1000.0;

    /** Le texte sûr dans un SVG. */
    private static function h(string $t): string
    {
        return htmlspecialchars($t, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private static function n(float $v): string
    {
        return (string) round($v, 1);
    }

    /** Un nombre en français, sans zéros inutiles (« 4 », « 0,4 », « 17,5 »). Sans décimale, rien ne se retire : « 120 ». */
    public static function nombre(float $v, int $decimales = 2): string
    {
        $texte = number_format($v, $decimales, ',', ' ');
        return $decimales > 0 ? rtrim(rtrim($texte, '0'), ',') : $texte;
    }

    /** La longueur de la barre d'échelle : la plus grande valeur ronde qui tient dans `$maxM` mètres. */
    private static function echelleRonde(float $maxM): float
    {
        $m = 1.0;
        foreach ([1, 2, 5, 10, 20, 50, 100, 200, 500, 1000, 2000, 5000] as $c) {
            if ($c <= $maxM) {
                $m = (float) $c;
            }
        }
        return $m;
    }

    /** La barre d'échelle, posée en (x, y) : `$m` mètres valent `$m * $s` unités. */
    private static function barreEchelle(float $x, float $y, float $m, float $s): string
    {
        $w = $m * $s;
        $mot = $m >= 1000 ? self::nombre($m / 1000) . ' km' : self::nombre($m) . ' m';
        return '<g class="d-echelle">'
            . '<rect x="' . self::n($x) . '" y="' . self::n($y) . '" width="' . self::n($w / 2) . '" height="6" fill="#26312A"/>'
            . '<rect x="' . self::n($x + $w / 2) . '" y="' . self::n($y) . '" width="' . self::n($w / 2) . '" height="6" fill="#fff" stroke="#26312A" stroke-width="1.2"/>'
            . '<rect x="' . self::n($x) . '" y="' . self::n($y) . '" width="' . self::n($w) . '" height="6" fill="none" stroke="#26312A" stroke-width="1.2"/>'
            . '<text x="' . self::n($x) . '" y="' . self::n($y - 6) . '" class="d-texte">0</text>'
            . '<text x="' . self::n($x + $w) . '" y="' . self::n($y - 6) . '" class="d-texte" text-anchor="middle">' . self::h($mot) . '</text>'
            . '</g>';
    }

    /** La flèche du nord, posée en (x, y) — son sommet. */
    private static function nord(float $x, float $y): string
    {
        return '<g class="d-nord">'
            . '<polygon points="' . self::n($x) . ',' . self::n($y) . ' ' . self::n($x + 11) . ',' . self::n($y + 34) . ' ' . self::n($x) . ',' . self::n($y + 26)
            . '" fill="#26312A"/>'
            . '<polygon points="' . self::n($x) . ',' . self::n($y) . ' ' . self::n($x - 11) . ',' . self::n($y + 34) . ' ' . self::n($x) . ',' . self::n($y + 26)
            . '" fill="#fff" stroke="#26312A" stroke-width="1.2"/>'
            . '<text x="' . self::n($x) . '" y="' . self::n($y + 52) . '" class="d-texte d-gras" text-anchor="middle">N</text>'
            . '</g>';
    }

    // ------------------------------------------------------------------ le terrain

    /**
     * Les tracés de la carte, sur fond blanc, avec échelle et nord. `null` s'il n'y a rien de tracé.
     *
     * La projection est celle de `Calques` : locale et plane, en mètres, le nord en haut. Les éléments se dessinent dans
     * l'ordre des calques (le fond d'abord), chacun à la teinte de son calque et comme à l'écran : un polygone en
     * pointillés (plein pour une parcelle), une ligne pleine (fléchée pour un vent, plus épaisse s'il domine), un point.
     * Du relief, seules les courbes de niveau sont gardées, avec leur altitude.
     *
     * @param list<array<string, mixed>> $elements `Calques::dUnSite()`
     * @param array<string, array{teinte: string}> $calques `Calques::CALQUES`
     */
    public static function terrain(array $elements, array $calques): ?string
    {
        $points = [];
        foreach ($elements as $el) {
            foreach (self::pointsDe($el) as $p) {
                $points[] = $p;
            }
        }
        if ($points === []) {
            return null;
        }
        $sud = min(array_column($points, 0));
        $nord = max(array_column($points, 0));
        $ouest = min(array_column($points, 1));
        $est = max(array_column($points, 1));
        [$mLat, $mLon] = Calques::metresParDegre(($sud + $nord) / 2);
        $largeurM = max(1.0, ($est - $ouest) * $mLon);
        $hauteurM = max(1.0, ($nord - $sud) * $mLat);

        // La place des tracés : la largeur du dessin, une marge pour les étiquettes, et sous eux la bande de l'échelle.
        $marge = 60.0;
        $utile = self::LARGEUR - 2 * $marge;
        $s = min($utile / $largeurM, 1.25 * $utile / $hauteurM);
        $x0 = $marge + ($utile - $largeurM * $s) / 2;
        $hDessin = $hauteurM * $s + 2 * $marge;
        $total = $hDessin + 50;
        $xy = static fn (array $p): array => [$x0 + ($p[1] - $ouest) * $mLon * $s, $marge + ($nord - $p[0]) * $mLat * $s];
        $chemin = static fn (array $pts): string => implode(' ', array_map(static function (array $p) use ($xy): string {
            [$x, $y] = $xy($p);
            return self::n($x) . ',' . self::n($y);
        }, $pts));

        $o = '<svg class="d-dessin" viewBox="0 0 ' . self::LARGEUR . ' ' . self::n($total) . '" role="img" aria-label="Les tracés du terrain">';
        $etiquettes = '';
        $poserEtiquette = static function (array $ancre, string $mot, string $teinte, ?array $el) use ($xy, &$etiquettes): void {
            if ($mot === '') {
                return;
            }
            [$qx, $qy] = $xy($ancre);
            $propre = $el['proprietes']['etiquette'] ?? null;
            if (is_array($propre) && count($propre) === 2 && is_numeric($propre[0]) && is_numeric($propre[1])) {
                [$ox, $oy] = $xy([(float) $propre[0], (float) $propre[1]]);
                if (hypot($ox - $qx, $oy - $qy) > 24) {
                    $etiquettes .= '<line x1="' . self::n($qx) . '" y1="' . self::n($qy) . '" x2="' . self::n($ox) . '" y2="' . self::n($oy - 4)
                        . '" stroke="' . self::h($teinte) . '" stroke-width="1" stroke-dasharray="3 3"/>';
                }
            } else {
                [$ox, $oy] = [$qx + 8, $qy - 8];
            }
            $etiquettes .= '<text x="' . self::n($ox) . '" y="' . self::n($oy) . '" fill="' . self::h($teinte) . '" class="d-etiquette">' . self::h($mot) . '</text>';
        };

        foreach ($elements as $el) {
            $geo = $el['geometrie'] ?? [];
            if ($geo === []) {
                continue;
            }
            $teinte = (string) ($calques[$el['calque']]['teinte'] ?? '#333333');
            $t = self::h($teinte);
            $libelle = trim((string) ($el['libelle'] ?? ''));
            if ($el['type'] === 'grille') {
                $courbes = is_array($el['proprietes']['courbes'] ?? null) ? $el['proprietes']['courbes'] : [];
                $parNiveau = [];
                foreach ($courbes as $i => $c) {
                    if (!is_array($c['points'] ?? null) || count($c['points']) < 2) {
                        continue;
                    }
                    [$ax, $ay] = $xy($c['points'][0]);
                    [$bx, $by] = $xy($c['points'][1]);
                    $o .= '<line x1="' . self::n($ax) . '" y1="' . self::n($ay) . '" x2="' . self::n($bx) . '" y2="' . self::n($by)
                        . '" stroke="' . $t . '" stroke-width="1.2" stroke-linecap="round"/>';
                    $parNiveau[(string) ($c['altitude'] ?? '')][] = $i;
                }
                $rang = 0;
                foreach ($parNiveau as $altitude => $siens) {
                    if (count($parNiveau) > 8 && $rang++ % 2) {
                        continue;
                    }
                    $milieu = $courbes[$siens[intdiv(count($siens), 2)]];
                    $poserEtiquette($milieu['points'][0], round((float) $altitude) . ' m', $teinte, null);
                }
            } elseif ($el['type'] === 'point' || count($geo) === 1) {
                [$x, $y] = $xy($geo[0]);
                $o .= '<circle cx="' . self::n($x) . '" cy="' . self::n($y) . '" r="5" fill="' . $t . '" stroke="#fff" stroke-width="1.5"/>';
                $hauteur = $el['proprietes']['hauteur_m'] ?? null;
                $poserEtiquette($geo[0], $libelle . (is_numeric($hauteur) ? ' · ' . self::nombre((float) $hauteur) . ' m' : ''), $teinte, $el);
            } elseif ($el['type'] === 'ligne') {
                $vent = $el['calque'] === 'vents';
                $dominant = $vent && ($el['proprietes']['intensite'] ?? '') === 'dominant';
                $largeur = $vent ? ($dominant ? 5.0 : 2.2) : 2.6;
                $o .= '<polyline points="' . $chemin($geo) . '" fill="none" stroke="' . $t . '" stroke-width="' . $largeur
                    . '" stroke-linejoin="round" stroke-linecap="round"/>';
                if ($vent) {
                    $o .= self::pointe($xy($geo[count($geo) - 2]), $xy($geo[count($geo) - 1]), $teinte, $largeur);
                }
                $poserEtiquette($geo[intdiv(count($geo), 2)], $libelle . ($dominant ? ($libelle !== '' ? ' · ' : '') . 'dominant' : ''), $teinte, $el);
            } elseif ($el['type'] === 'polygone') {
                $parcelle = $el['calque'] === 'parcelles';
                $o .= '<polygon points="' . $chemin($geo) . '" fill="' . $t . '" fill-opacity="' . ($parcelle ? '.14' : '.18') . '" stroke="' . $t
                    . '" stroke-width="' . ($parcelle ? '2.6' : '1.8') . '"' . ($parcelle ? '' : ' stroke-dasharray="6 4"') . '/>';
                $poserEtiquette(self::centre($geo), $libelle, $teinte, $el);
            }
        }
        $o .= $etiquettes;

        // L'échelle, sous les tracés, à gauche ; le nord en haut à droite.
        $o .= self::barreEchelle($marge, $hDessin + 22, self::echelleRonde(0.3 * $utile / $s), $s);
        $o .= self::nord(self::LARGEUR - 26, 8);
        return $o . '</svg>';
    }

    /** Tous les points d'un élément, courbes de niveau comprises — ce que le dessin doit contenir. */
    private static function pointsDe(array $el): array
    {
        if (($el['type'] ?? '') === 'grille') {
            $pts = [];
            foreach ((array) ($el['proprietes']['courbes'] ?? []) as $c) {
                foreach ((array) ($c['points'] ?? []) as $p) {
                    if (is_array($p) && count($p) >= 2) {
                        $pts[] = [(float) $p[0], (float) $p[1]];
                    }
                }
            }
            return $pts;
        }
        return $el['geometrie'] ?? [];
    }

    /** @param list<array{0: float, 1: float}> $points */
    private static function centre(array $points): array
    {
        $la = 0.0;
        $lo = 0.0;
        foreach ($points as $p) {
            $la += $p[0];
            $lo += $p[1];
        }
        return [$la / count($points), $lo / count($points)];
    }

    /** La pointe d'un vent, au bout du dernier segment, à la taille du trait. */
    private static function pointe(array $a, array $b, string $teinte, float $largeur): string
    {
        $dx = $b[0] - $a[0];
        $dy = $b[1] - $a[1];
        $d = hypot($dx, $dy);
        if ($d < 1) {
            return '';
        }
        $dx /= $d;
        $dy /= $d;
        $l = $largeur * 3.2;
        $w = $largeur * 1.6;
        return '<polygon points="' . self::n($b[0]) . ',' . self::n($b[1]) . ' '
            . self::n($b[0] - $l * $dx + $w * $dy) . ',' . self::n($b[1] - $l * $dy - $w * $dx) . ' '
            . self::n($b[0] - $l * $dx - $w * $dy) . ',' . self::n($b[1] - $l * $dy + $w * $dx) . '" fill="' . self::h($teinte) . '"/>';
    }

    // ------------------------------------------------------------------ le plan d'une parcelle

    /**
     * Le plan d'une parcelle, à l'échelle : les bordures externe et interne, les planches et leurs lignes (la centrale
     * en trait plein), la ligne des lianes, les plants des bordures, les côtés, les dimensions, l'échelle. Le nord est en
     * haut, comme à l'écran. `null` tant que la parcelle n'a pas de dimensions.
     *
     * @param array<string, mixed> $parcelle une ligne de `parcelle`
     * @param list<array{x: float, y: float, couleur: string, sigle: string, r: float}> $plants en mètres ; `r` le rayon
     *        dessiné, en mètres
     */
    public static function plan(array $parcelle, array $plants): ?string
    {
        $L = (float) ($parcelle['longueur_m'] ?? 0);
        $H = (float) ($parcelle['largeur_m'] ?? 0);
        if ($L <= 0 || $H <= 0) {
            return null;
        }
        $marge = 58.0;
        $utile = self::LARGEUR - 2 * $marge;
        $s = min($utile / $L, 1.1 * $utile / $H);
        $x0 = $marge + ($utile - $L * $s) / 2;
        $y0 = $marge;
        $total = $y0 + $H * $s + $marge + 34;
        $X = static fn (float $x): string => self::n($x0 + $x * $s);
        $Y = static fn (float $y): string => self::n($y0 + $y * $s);
        $rect = static fn (float $x, float $y, float $w, float $hh, string $classe, string $remplissage = ''): string => $w <= 0 || $hh <= 0 ? ''
            : '<rect x="' . $X($x) . '" y="' . $Y($y) . '" width="' . self::n($w * $s) . '" height="' . self::n($hh * $s) . '" class="' . $classe . '"'
                . ($remplissage !== '' ? ' style="fill:' . $remplissage . '"' : '') . '/>';

        $o = '<svg class="d-dessin" viewBox="0 0 ' . self::LARGEUR . ' ' . self::n($total) . '" role="img" aria-label="Plan de la parcelle '
            . self::h((string) ($parcelle['code'] ?? '')) . '">';
        $o .= '<defs><pattern id="d-perenne-' . (int) ($parcelle['id'] ?? 0) . '" width="7" height="7" patternUnits="userSpaceOnUse" patternTransform="rotate(45)">'
            . '<rect width="7" height="7" fill="#DCE8D2"/><rect width="2" height="7" fill="#9DB98C"/></pattern></defs>';
        $o .= $rect(0, 0, $L, $H, 'd-parcelle');

        // Les bordures, comme `Bordures::lignes()` : l'externe au bord, l'interne dedans ; nord et sud sur toute la largeur.
        $plan = json_decode((string) ($parcelle['plan'] ?? ''), true);
        $larg = static function (string $cadre, string $cote) use ($plan): float {
            $b = is_array($plan) ? ($plan['bordures'][$cadre][$cote] ?? null) : null;
            return is_array($b) && !empty($b['actif']) ? max(0.0, (float) ($b['largeur_m'] ?? 0)) : 0.0;
        };
        $e = static fn (string $c): float => $larg('externe', $c);
        $i = static fn (string $c): float => $larg('interne', $c);
        $o .= $rect(0, 0, $L, $e('nord'), 'd-bordure') . $rect(0, $H - $e('sud'), $L, $e('sud'), 'd-bordure')
            . $rect(0, $e('nord'), $e('ouest'), $H - $e('nord') - $e('sud'), 'd-bordure')
            . $rect($L - $e('est'), $e('nord'), $e('est'), $H - $e('nord') - $e('sud'), 'd-bordure');
        $xi = $e('ouest');
        $wi = $L - $e('ouest') - $e('est');
        $o .= $rect($xi, $e('nord'), $wi, $i('nord'), 'd-bordure-interne')
            . $rect($xi, $H - $e('sud') - $i('sud'), $wi, $i('sud'), 'd-bordure-interne');
        $yi = $e('nord') + $i('nord');
        $hi = $H - $e('nord') - $e('sud') - $i('nord') - $i('sud');
        $o .= $rect($xi, $yi, $i('ouest'), $hi, 'd-bordure-interne') . $rect($L - $e('est') - $i('est'), $yi, $i('est'), $hi, 'd-bordure-interne');

        // Les planches : pérennes hachurées, annuelles claires ; en abeille, deux bandes par planche.
        $natures = Planches::natures($parcelle);
        $motif = 'url(#d-perenne-' . (int) ($parcelle['id'] ?? 0) . ')';
        foreach (Planches::dUneParcelle($parcelle) as $p) {
            $nat = $natures[$p['numero']] ?? null;
            $ns = $p['sens'] === 'nord-sud';
            [$w, $hh] = $ns ? [$p['largeur'], $p['longueur']] : [$p['longueur'], $p['largeur']];
            if ($nat !== null && $nat['bandes'] !== null) {
                foreach ($nat['bandes'] as $k => $bande) {
                    $fond = $bande === 'perenne' ? $motif : '';
                    $o .= $ns
                        ? $rect($p['x'] + $k * $w / 2, $p['y'], $w / 2, $hh, 'd-planche', $fond)
                        : $rect($p['x'], $p['y'] + $k * $hh / 2, $w, $hh / 2, 'd-planche', $fond);
                }
            } else {
                $o .= $rect($p['x'], $p['y'], $w, $hh, 'd-planche', ($nat['nature'] ?? null) === 'perenne' ? $motif : '');
            }
            foreach ($p['lignes'] as $l) {
                $o .= '<line x1="' . $X($l['x1']) . '" y1="' . $Y($l['y1']) . '" x2="' . $X($l['x2']) . '" y2="' . $Y($l['y2'])
                    . '" class="' . ($l['centrale'] ? 'd-ligne-centrale' : 'd-ligne') . '"/>';
            }
            // Le numéro de la planche, à son bout nord (ou ouest).
            $o .= $ns
                ? '<text x="' . $X($p['x'] + $w / 2) . '" y="' . self::n($y0 + $p['y'] * $s + 13) . '" class="d-numero" text-anchor="middle">' . $p['numero'] . '</text>'
                : '<text x="' . self::n($x0 + $p['x'] * $s + 4) . '" y="' . self::n($y0 + ($p['y'] + $hh / 2) * $s + 4) . '" class="d-numero">' . $p['numero'] . '</text>';
        }

        // La ligne des lianes, en tortue liane.
        $liane = ($parcelle['tortue'] ?? null) === 'liane' ? Planches::ligneDesLianes($parcelle) : null;
        if ($liane !== null) {
            $o .= '<line x1="' . $X($liane['x1']) . '" y1="' . $Y($liane['y1']) . '" x2="' . $X($liane['x2']) . '" y2="' . $Y($liane['y2']) . '" class="d-liane"/>';
        }

        // Les plants des bordures : un disque à la couleur de l'espèce, son sigle dedans quand il y a la place.
        foreach ($plants as $pl) {
            $r = max(2.5, min(11.0, $pl['r'] * $s));
            $o .= '<circle cx="' . $X($pl['x']) . '" cy="' . $Y($pl['y']) . '" r="' . self::n($r) . '" fill="' . self::h($pl['couleur']) . '" class="d-plant"/>';
            if ($r >= 8) {
                $o .= '<text x="' . $X($pl['x']) . '" y="' . self::n($y0 + $pl['y'] * $s + 3) . '" class="d-sigle" text-anchor="middle">' . self::h($pl['sigle']) . '</text>';
            }
        }

        // Les côtés, les dimensions, l'échelle.
        $cx = $x0 + $L * $s / 2;
        $cy = $y0 + $H * $s / 2;
        $bas = $y0 + $H * $s;
        $droite = $x0 + $L * $s;
        $o .= '<text x="' . self::n($cx) . '" y="' . self::n($y0 - 30) . '" class="d-cote" text-anchor="middle">nord</text>'
            . '<text x="' . self::n($cx) . '" y="' . self::n($bas + 20) . '" class="d-cote" text-anchor="middle">sud</text>'
            . '<text x="' . self::n($x0 - 30) . '" y="' . self::n($cy) . '" class="d-cote" text-anchor="middle" transform="rotate(-90 ' . self::n($x0 - 30) . ' ' . self::n($cy) . ')">ouest</text>'
            . '<text x="' . self::n($droite + 30) . '" y="' . self::n($cy) . '" class="d-cote" text-anchor="middle" transform="rotate(90 ' . self::n($droite + 30) . ' ' . self::n($cy) . ')">est</text>';
        $o .= '<line x1="' . self::n($x0) . '" y1="' . self::n($y0 - 10) . '" x2="' . self::n($droite) . '" y2="' . self::n($y0 - 10) . '" class="d-mesure"/>'
            . '<text x="' . self::n($cx) . '" y="' . self::n($y0 - 14) . '" class="d-texte" text-anchor="middle">' . self::nombre($L) . ' m</text>'
            . '<line x1="' . self::n($x0 - 10) . '" y1="' . self::n($y0) . '" x2="' . self::n($x0 - 10) . '" y2="' . self::n($bas) . '" class="d-mesure"/>'
            . '<text x="' . self::n($x0 - 14) . '" y="' . self::n($cy) . '" class="d-texte" text-anchor="middle" transform="rotate(-90 ' . self::n($x0 - 14) . ' ' . self::n($cy) . ')">'
            . self::nombre($H) . ' m</text>';
        $o .= self::barreEchelle($x0, $bas + 50, self::echelleRonde(0.3 * $L), $s);
        return $o . '</svg>';
    }

    // ------------------------------------------------------------------ la frise du scénario

    /**
     * La frise d'un scénario en syntropie : les sept périodes en colonnes, une rangée par strate, une barre par végétal,
     * de son entrée à sa sortie, hachurée après son retrait. La même lecture que l'écran, sans lien.
     *
     * @param array<string, string> $strates code → libellé
     * @param array<string, list<array<string, mixed>>> $groupes code → lignes de `scenario_ligne`, triées
     */
    public static function frise(array $strates, array $groupes): string
    {
        $xL = 118.0;
        $xR = 992.0;
        $u = ($xR - $xL) / Scenario::POSITION_MAX;
        $enteteH = 46;
        $voie = 26;
        $barreH = 20;
        $hauteur = $enteteH;
        foreach ($strates as $code => $_) {
            $hauteur += max(1, count($groupes[$code] ?? [])) * $voie + 10;
        }
        $hauteur += 6;
        $o = '<svg class="d-dessin" viewBox="0 0 1000 ' . $hauteur . '" role="img" aria-label="Le scénario, par strates et dans le temps">'
            . '<defs><pattern id="d-hachure" width="6" height="6" patternUnits="userSpaceOnUse" patternTransform="rotate(45)">'
            . '<rect width="3" height="6" fill="#26312A" fill-opacity=".3"/></pattern></defs>';
        $i = 0;
        foreach (Scenario::PERIODES as [$nom, $duree]) {
            $x = $xL + $i * 4 * $u;
            $o .= '<rect x="' . self::n($x) . '" y="0" width="' . self::n(4 * $u) . '" height="' . ($hauteur - 4) . '" class="d-periode' . ($i % 2 ? ' impair' : '') . '"/>'
                . '<text x="' . self::n($x + 2 * $u) . '" y="19" class="d-texte d-gras" text-anchor="middle">' . self::h($nom) . '</text>'
                . '<text x="' . self::n($x + 2 * $u) . '" y="35" class="d-texte" text-anchor="middle">' . self::h($duree) . '</text>';
            $i++;
        }
        $y = $enteteH;
        foreach ($strates as $code => $nom) {
            $lignes = $groupes[$code] ?? [];
            $rangeeH = max(1, count($lignes)) * $voie + 10;
            $o .= '<line x1="6" x2="' . $xR . '" y1="' . $y . '" y2="' . $y . '" class="d-trait"/>'
                . '<text x="10" y="' . ($y + $rangeeH / 2 + 4) . '" class="d-texte d-gras">' . self::h($nom) . '</text>';
            if ($lignes === []) {
                $o .= '<text x="' . self::n($xL + 14 * $u) . '" y="' . ($y + $rangeeH / 2 + 4) . '" class="d-texte d-vide" text-anchor="middle">aucun végétal</text>';
            }
            foreach ($lignes as $k => $l) {
                $f = Scenario::formulaire($l);
                $debut = max(0, min(27, (int) $f['debut']));
                $fin = max($debut + 1, min(Scenario::POSITION_MAX, (int) $f['fin']));
                $retrait = $f['retrait'] === '' ? $fin : max($debut, min($fin, (int) $f['retrait']));
                $couleur = in_array($l['couleur_plan'] ?? null, ['rouge', 'vert', 'orange', 'bleu'], true) ? (string) $l['couleur_plan'] : 'vert';
                $x = $xL + $debut * $u;
                $w = ($fin - $debut) * $u;
                $by = $y + 5 + $k * $voie + ($voie - $barreH) / 2;
                $o .= '<svg x="' . self::n($x) . '" y="' . self::n($by) . '" width="' . self::n($w) . '" height="' . $barreH . '" class="d-barre b-' . $couleur . '">'
                    . '<rect width="100%" height="100%" rx="4"/>';
                if ($retrait < $fin) {
                    $r = ($retrait - $debut) / ($fin - $debut) * 100;
                    $o .= '<rect x="' . round($r, 2) . '%" width="' . round(100 - $r, 2) . '%" height="100%" rx="4" fill="url(#d-hachure)"/>';
                }
                $o .= '<text x="6" y="14" class="d-texte">' . self::h((string) $l['espece'] . ($l['nb_pieds'] === null ? '' : ' ×' . (int) $l['nb_pieds'])) . '</text></svg>';
            }
            $y += $rangeeH;
        }
        $o .= '<line x1="6" x2="' . $xR . '" y1="' . $y . '" y2="' . $y . '" class="d-trait"/>';
        for ($p = 0; $p <= 7; $p++) {
            $x = self::n($xL + $p * 4 * $u);
            $o .= '<line x1="' . $x . '" x2="' . $x . '" y1="0" y2="' . $y . '" class="d-limite"/>';
        }
        return $o . '</svg>';
    }
}
