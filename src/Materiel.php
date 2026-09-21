<?php

declare(strict_types=1);

namespace Syntropia;

/**
 * Le matériel végétal à se procurer — ce que le scénario d'une parcelle demande.
 *
 * Étape 5 de Conception (`conception-materiel-et-eau.php`). Pur : ni base ni
 * session, tout arrive en paramètre — comme `Scenario` et `Relief`.
 *
 * **Une quantité saisie prime toujours.** Sans saisie, on estime depuis
 * l'espacement et le plan de la parcelle, et l'estimation se dit « estimée » :
 * une ligne de plants par planche ou par bande de bordure, à l'espacement de la
 * ligne du scénario (à défaut, celui du référentiel de végétaux). C'est une
 * estimation haute quand plusieurs végétaux se partagent une même ligne — la
 * page le dit.
 */
final class Materiel
{
    /** Les groupes du récapitulatif, dans l'ordre où on s'en occupe. */
    public const ORIGINES = [
        'achat' => 'À acheter',
        'mixte' => 'Acheté et multiplié',
        'multiplication' => 'À multiplier soi-même',
        'rna' => 'À laisser venir (RNA)',
        'potager' => 'Cultures du potager',
        '' => 'Origine à préciser',
    ];

    /**
     * Les bandes de plantation d'une parcelle, en mètres : combien de planches et
     * quelle longueur, puis la longueur de chaque côté actif des bordures externe
     * et interne (l'interne est dans l'externe, comme sur le plan).
     * `null` tant que la parcelle n'a pas de dimensions.
     *
     * @param array<string, mixed> $parcelle
     * @return array{centre: array{nb: int, longueur: float}, bord_ext: list<float>, bord_int: list<float>}|null
     */
    public static function bandes(array $parcelle): ?array
    {
        $longueur = (float) ($parcelle['longueur_m'] ?? 0);
        $largeur = (float) ($parcelle['largeur_m'] ?? 0);
        if ($longueur <= 0 || $largeur <= 0) {
            return null;
        }
        $plan = json_decode((string) ($parcelle['plan'] ?? ''), true);
        $larg = static function (mixed $bordures, string $cote): float {
            $b = is_array($bordures) ? ($bordures[$cote] ?? null) : null;
            return is_array($b) && !empty($b['actif']) ? max(0.0, (float) ($b['largeur_m'] ?? 0)) : 0.0;
        };
        $ext = is_array($plan) ? ($plan['bordures']['externe'] ?? []) : [];
        $int = is_array($plan) ? ($plan['bordures']['interne'] ?? []) : [];
        $e = static fn (string $c): float => $larg($ext, $c);
        $i = static fn (string $c): float => $larg($int, $c);

        $bandes = static function (array $cotes, array $portees): array {
            $out = [];
            foreach ($cotes as $cote => $actif) {
                if ($actif) {
                    $out[] = max(0.0, $portees[$cote]);
                }
            }
            return $out;
        };
        $actifs = static fn (callable $f): array => [
            'nord' => $f('nord') > 0, 'sud' => $f('sud') > 0, 'est' => $f('est') > 0, 'ouest' => $f('ouest') > 0,
        ];

        // Externe : nord et sud sur toute la longueur, est et ouest entre les deux.
        $bordExt = $bandes($actifs($e), [
            'nord' => $longueur, 'sud' => $longueur,
            'est' => $largeur - $e('nord') - $e('sud'), 'ouest' => $largeur - $e('nord') - $e('sud'),
        ]);
        // Interne : dans l'externe ; est et ouest s'arrêtent aux côtés nord et sud internes.
        $bordInt = $bandes($actifs($i), [
            'nord' => $longueur - $e('ouest') - $e('est'), 'sud' => $longueur - $e('ouest') - $e('est'),
            'est' => $largeur - $e('nord') - $e('sud') - $i('nord') - $i('sud'),
            'ouest' => $largeur - $e('nord') - $e('sud') - $i('nord') - $i('sud'),
        ]);

        // Les planches courent dans le sens choisi ; leur longueur est ce qui reste
        // de l'autre dimension une fois toutes les bordures retirées.
        $nordSud = ($parcelle['orientation_lignes'] ?? 'nord-sud') !== 'est-ouest';
        $utile = $nordSud
            ? $largeur - $e('nord') - $e('sud') - $i('nord') - $i('sud')
            : $longueur - $e('ouest') - $e('est') - $i('ouest') - $i('est');
        $nb = (int) ($parcelle['nb_planches'] ?? 0);

        return [
            'centre' => ['nb' => $nb, 'longueur' => max(0.0, $utile)],
            'bord_ext' => $bordExt,
            'bord_int' => $bordInt,
        ];
    }

    /**
     * Le taux de réussite d'un semis, tel que Lopez en fait une convention de planification : environ 80 % — la
     * médiane exacte de son tableau, sept cultures sur dix-sept pile dessus. **Ce n'est pas une mesure** : le
     * rapport va du simple au double selon l'espèce (91 % pour le poireau, 50 % pour l'aubergine), et le livre dit
     * qu'elle est « à adapter à vos propres taux de réussite ». Un point de départ à remplacer.
     */
    public const TAUX_SEMIS = 0.8;

    /** Combien de plants sur une bande de cette longueur, à cet espacement. */
    private static function surBande(float $longueur, float $espacement): int
    {
        return $longueur <= 0 ? 0 : max(1, (int) floor($longueur / $espacement + 1e-9));
    }

    /**
     * La quantité d'une ligne du scénario.
     *
     * @param array<string, mixed> $ligne          une ligne de `scenario_ligne`
     * @param array<string, mixed>|null $bandes    `bandes()` de sa parcelle
     * @param float|null $espacementReferentiel    l'espacement du référentiel, à défaut
     * @return array{n: ?int, mode: ?string, espacement: ?float}
     */
    public static function quantite(array $ligne, ?array $bandes, ?float $espacementReferentiel = null): array
    {
        $esp = $ligne['espacement_m'] !== null && $ligne['espacement_m'] !== '' ? (float) $ligne['espacement_m'] : null;
        if ($esp === null && $espacementReferentiel !== null && $espacementReferentiel > 0) {
            $esp = $espacementReferentiel;
        }
        if ($ligne['nb_pieds'] !== null && $ligne['nb_pieds'] !== '') {
            return ['n' => (int) $ligne['nb_pieds'], 'mode' => 'saisi', 'espacement' => $esp];
        }
        if ($bandes === null || $esp === null || $esp <= 0) {
            return ['n' => null, 'mode' => null, 'espacement' => $esp];
        }
        $n = match (Scenario::details($ligne)['emplacement'] ?? 'centre') {
            'centre' => $bandes['centre']['nb'] * self::surBande($bandes['centre']['longueur'], $esp),
            'bord_ext' => array_sum(array_map(static fn (float $l): int => self::surBande($l, $esp), $bandes['bord_ext'])),
            'bord_int' => array_sum(array_map(static fn (float $l): int => self::surBande($l, $esp), $bandes['bord_int'])),
            default => 0,
        };
        return $n > 0 ? ['n' => $n, 'mode' => 'estime', 'espacement' => $esp] : ['n' => null, 'mode' => null, 'espacement' => $esp];
    }

    /**
     * La quantité d'une culture de potager (`succession_ligne`).
     *
     * **Une quantité saisie prime toujours.** Sans saisie, une ligne de plants sur la planche, à l'espacement de la
     * culture (à défaut celui du référentiel) : un ordre de grandeur, à corriger. **Des graines** — `plants / 0,8` —
     * seulement pour une culture semée à l'abri (`date_semis` notée) : celle qui se sème en place n'a pas de
     * « plants » à démarrer, et l'atelier ne devine pas combien de graines elle demande.
     *
     * @param array<string, mixed> $culture une ligne de `succession_ligne`
     * @param array<string, mixed>|null $bandes `bandes()` de sa parcelle
     * @return array{n: ?int, mode: ?string, espacement: ?float, graines: ?int}
     */
    public static function quantiteCulture(array $culture, ?array $bandes, ?float $espacementReferentiel = null): array
    {
        $esp = ($culture['espacement_m'] ?? '') !== '' && $culture['espacement_m'] !== null ? (float) $culture['espacement_m'] : null;
        if ($esp === null && $espacementReferentiel !== null && $espacementReferentiel > 0) {
            $esp = $espacementReferentiel;
        }
        $n = null;
        $mode = null;
        if (($culture['nb_plants'] ?? '') !== '' && $culture['nb_plants'] !== null) {
            $n = (int) $culture['nb_plants'];
            $mode = 'saisi';
        } elseif ($bandes !== null && $esp !== null && $esp > 0 && $bandes['centre']['longueur'] > 0) {
            $n = self::surBande($bandes['centre']['longueur'], $esp);
            $mode = 'estime';
        }
        $graines = $n !== null && $n > 0 && ($culture['date_semis'] ?? '') !== '' && $culture['date_semis'] !== null
            ? (int) ceil($n / self::TAUX_SEMIS - 1e-9) : null;
        return ['n' => $n, 'mode' => $mode, 'espacement' => $esp, 'graines' => $graines];
    }

    /**
     * Le récapitulatif : une ligne par végétal et origine — plusieurs implantations
     * d'un même végétal (ou de plusieurs parcelles, en vue « tout le projet ») se
     * cumulent. `n` est `null` dès qu'une des implantations n'a pas de quantité :
     * on n'additionne pas des quantités qu'on ne connaît pas, on le dit.
     *
     * @param list<array{ligne: array<string, mixed>, parcelle: string, qte: array{n: ?int, mode: ?string, espacement: ?float, graines?: ?int}}> $entrees
     * @return list<array{espece: string, origine: string, parcelles: list<string>, n: ?int, connu: int, inconnu: int, estime: bool, graines: int}>
     */
    public static function regrouper(array $entrees): array
    {
        $groupes = [];
        foreach ($entrees as $e) {
            $origine = (string) ($e['ligne']['origine'] ?? '');
            $espece = trim((string) $e['ligne']['espece']);
            $cle = $origine . '|' . mb_strtolower($espece);
            $groupes[$cle] ??= ['espece' => $espece, 'origine' => $origine, 'parcelles' => [], 'n' => 0,
                'connu' => 0, 'inconnu' => 0, 'estime' => false, 'graines' => 0];
            $g = &$groupes[$cle];
            if (!in_array($e['parcelle'], $g['parcelles'], true)) {
                $g['parcelles'][] = $e['parcelle'];
            }
            if ($e['qte']['n'] === null) {
                $g['inconnu']++;
            } else {
                $g['connu'] += $e['qte']['n'];
                $g['estime'] = $g['estime'] || $e['qte']['mode'] === 'estime';
                $g['graines'] += (int) ($e['qte']['graines'] ?? 0);
            }
            unset($g);
        }
        $ordre = array_flip(array_keys(self::ORIGINES));
        $liste = array_values($groupes);
        foreach ($liste as &$g) {
            $g['n'] = $g['inconnu'] === 0 ? $g['connu'] : null;
        }
        unset($g);
        usort($liste, static fn (array $a, array $b): int =>
            [$ordre[$a['origine']] ?? 99, mb_strtolower($a['espece'])] <=> [$ordre[$b['origine']] ?? 99, mb_strtolower($b['espece'])]);
        return $liste;
    }

    /**
     * Combien de végétaux et de plants par origine, pour le bandeau de synthèse.
     *
     * @param list<array{origine: string, n: ?int, connu: int, inconnu: int}> $recap
     * @return array<string, array{especes: int, plants: int, incomplet: bool}>
     */
    public static function synthese(array $recap): array
    {
        $s = [];
        foreach ($recap as $r) {
            $s[$r['origine']] ??= ['especes' => 0, 'plants' => 0, 'incomplet' => false];
            $s[$r['origine']]['especes']++;
            $s[$r['origine']]['plants'] += $r['connu'];
            $s[$r['origine']]['incomplet'] = $s[$r['origine']]['incomplet'] || $r['inconnu'] > 0;
        }
        return $s;
    }
}
