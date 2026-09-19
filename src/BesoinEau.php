<?php

declare(strict_types=1);

namespace Syntropia;

/**
 * Les besoins en eau d'une parcelle, et la fréquence d'arrosage que ses tests de
 * sol suggèrent.
 *
 * Étape 5 de Conception. Pur : tout arrive en paramètre.
 *
 * **Deux sources, dites à l'écran.** Les volumes viennent de Lopez
 * (`connaissances/maraichage-naturel/lopez-irrigation.md` — maraîchage
 * d'annuelles en Haute-Vienne, rien n'existe de chiffré pour une ligne
 * pérenne) : ce sont des ordres de grandeur de dimensionnement, pas une
 * consommation attendue. La classe d'infiltration reprend les cinq textures de
 * la table 7 de l'annexe 2 du manuel d'irrigation de la FAO (sable > 30 mm/h,
 * limon sableux 20-30, limon 10-20, limon argileux 5-10, argile 1-5) — le
 * corpus du dépôt dit seulement qu'un sol qui boit vite demande des passages
 * plus courts et plus fréquents, et un sol qui fait tampon du « long et
 * espacé » (`FicheTerrain`, fiche 02). **Les doses par passage de `DOSES` sont
 * un repère de cet atelier, pas une norme** : l'arbitre reste le contrôle au
 * doigt de Lopez, avant chaque arrosage.
 */
final class BesoinEau
{
    /**
     * Les trois scénarios de sol de Lopez, du pire au meilleur. `litres` : le
     * besoin annuel par m² ; `null` = « moins », non chiffré par l'ouvrage.
     */
    public const SOLS = [
        'degrade' => [
            'titre' => 'Sol dégradé, année de forte sécheresse',
            'detail' => 'limono-sableux très drainant, peu de matière organique, biologie hésitante, aucune pluie de juin à octobre',
            'litres' => 750,
        ],
        'intermediaire' => [
            'titre' => 'Sol intermédiaire',
            'detail' => 'un peu de biologie, de matière organique et d’argile',
            'litres' => 300,
        ],
        'optimal' => [
            'titre' => 'Sol structuré',
            'detail' => 'argiles, matière organique, structuration biologique',
            'litres' => null,
        ],
    ];

    /** L'évapotranspiration d'été (Lopez) : en pointe, plantes à grand feuillage, et en moyenne. */
    public const POINTE_MM_JOUR = 5.0;
    public const MOYENNE_MM_JOUR = 3.0;

    /** Une mare stocke 300 m³ pour 400 m³ creusés : la marge couvre l'évaporation (Lopez). */
    public const MARGE_MARE = 4 / 3;

    /** Les ressources en eau que le site peut déclarer. */
    public const RESSOURCES = [
        'reseau' => 'Le réseau',
        'puits_forage' => 'Un puits ou un forage',
        'reserve' => 'Une réserve (mare, cuve, récupération de pluie)',
        'aucune' => 'Aucune',
    ];

    /** Ce que la ressource déclarée change, dit à l'écran. */
    public const CONSEIL_RESSOURCE = [
        'reseau' => 'L’eau du réseau est potable mais chlorée : Lopez la déconseille pour la vie du sol, et son coût pèse vite. Une réserve de pluie, même modeste, la relaie.',
        'puits_forage' => 'Vérifiez le débit en fin d’été, quand le besoin est à son maximum : c’est là qu’un puits faiblit. Une réserve tampon lisse la pointe.',
        'reserve' => 'Comparez son volume au besoin annuel de la parcelle, en sol dégradé comme en sol intermédiaire : prévoir large vaut mieux que prévoir juste.',
        'aucune' => 'Sans eau à portée, le curseur se déplace vers la régénération naturelle assistée : semer plutôt que transplanter, choisir ce qui repousse déjà, étaler l’implantation. Un plant transplanté a perdu ses racines fines, et ce sont les trois premières années d’arrosage qui paient cette dette.',
    ];

    /** Les classes d'infiltration, de la plus lente à la plus rapide (mm/h, seuil bas inclus). */
    private const CLASSES = [
        ['cle' => 'tres_lente', 'de' => 0.0, 'titre' => 'très lente', 'texture' => 'argile'],
        ['cle' => 'lente', 'de' => 5.0, 'titre' => 'lente', 'texture' => 'limon argileux'],
        ['cle' => 'moyenne', 'de' => 10.0, 'titre' => 'moyenne', 'texture' => 'limon'],
        ['cle' => 'assez_rapide', 'de' => 20.0, 'titre' => 'assez rapide', 'texture' => 'limon sableux'],
        ['cle' => 'rapide', 'de' => 30.0, 'titre' => 'rapide', 'texture' => 'sable'],
    ];

    /** La dose maximale d'un passage, par famille de sol — un repère de l'atelier (mm). */
    private const DOSES = ['drainant' => 12.0, 'moyen' => 18.0, 'tampon' => 25.0];

    /** Les trois états d'humidité de la fiche de relevé (`FicheTerrain`). */
    public const ETATS = ['détrempé', 'ressuyé', 'sec dur'];

    /**
     * La surface arrosée d'une parcelle : tout le jardin, passe-pieds et allées
     * compris (Lopez) — donc longueur × largeur, à défaut la surface enregistrée.
     *
     * @param array<string, mixed> $parcelle
     */
    public static function surfaceM2(array $parcelle): ?float
    {
        $l = (float) ($parcelle['longueur_m'] ?? 0);
        $w = (float) ($parcelle['largeur_m'] ?? 0);
        if ($l > 0 && $w > 0) {
            return round($l * $w, 1);
        }
        $s = (float) ($parcelle['surface_m2'] ?? 0);
        return $s > 0 ? $s : null;
    }

    /**
     * Le besoin d'une surface dans chaque scénario de sol.
     *
     * @return array<string, array{titre: string, detail: string, litres: ?int, m3_an: ?float, reserve_m3: ?float, mare_m3: ?float}>
     */
    public static function besoins(float $surfaceM2): array
    {
        $out = [];
        foreach (self::SOLS as $cle => $sol) {
            $m3 = $sol['litres'] === null ? null : round($surfaceM2 * $sol['litres'] / 1000, 1);
            $out[$cle] = $sol + [
                'm3_an' => $m3,
                // La réserve de Lopez égale le besoin annuel (300 m³ pour 400 m² en sol dégradé).
                'reserve_m3' => $m3,
                'mare_m3' => $m3 === null ? null : round($m3 * self::MARGE_MARE, 1),
            ];
        }
        return $out;
    }

    /** Le débit de pointe d'une surface : m³ par jour et par semaine. */
    public static function pointe(float $surfaceM2): array
    {
        return [
            'm3_jour' => round($surfaceM2 * self::POINTE_MM_JOUR / 1000, 2),
            'm3_semaine' => round($surfaceM2 * self::POINTE_MM_JOUR * 7 / 1000, 1),
        ];
    }

    /**
     * La classe d'infiltration d'une vitesse stabilisée.
     *
     * @return array{cle: string, titre: string, texture: string, famille: string}
     */
    public static function classeInfiltration(float $mmH): array
    {
        $classe = self::CLASSES[0];
        foreach (self::CLASSES as $c) {
            if ($mmH >= $c['de']) {
                $classe = $c;
            }
        }
        // Un sol qui boit vite garde peu (drainant) ; qui boit lentement, l'eau
        // doit lui être donnée par petits cycles (tampon) ; entre les deux, le milieu.
        $famille = $mmH >= 20 ? 'drainant' : ($mmH >= 10 ? 'moyen' : 'tampon');
        return ['cle' => $classe['cle'], 'titre' => $classe['titre'], 'texture' => $classe['texture'], 'famille' => $famille];
    }

    /**
     * Comment ce sol se ressuie, d'après l'état d'humidité relevé et le nombre de
     * jours écoulés depuis la dernière pluie (la fiche 02 les demande ensemble : ils
     * disent « à quelle vitesse ce sol se ressuie »).
     *
     * @return array{cle: string, titre: string, lecture: string}|null
     */
    public static function ressuyage(?string $etat, ?int $jours): ?array
    {
        if ($etat === null || $etat === '') {
            return null;
        }
        if ($etat === 'détrempé') {
            return $jours !== null && $jours >= 2
                ? ['cle' => 'lent', 'titre' => 'lent',
                    'lecture' => 'encore détrempé ' . $jours . ' jours après la pluie : l’eau s’évacue mal, le risque est l’excès (asphyxie, hydromorphie)']
                : ['cle' => 'trop_tot', 'titre' => 'à confirmer',
                    'lecture' => 'relevé trop près de la pluie pour dire à quelle vitesse le sol se ressuie — recommencez deux à trois jours après']
                ;
        }
        if ($etat === 'ressuyé') {
            return $jours !== null && $jours <= 1
                ? ['cle' => 'rapide', 'titre' => 'rapide',
                    'lecture' => 'déjà ressuyé un jour ou moins après la pluie : le sol se vide vite, et se dessèche vite']
                : ['cle' => 'normal', 'titre' => 'normal',
                    'lecture' => 'ressuyé en quelques jours : le tampon est correct, ni excès durable ni assèchement rapide'];
        }
        return ['cle' => 'sec', 'titre' => 'sol sec',
            'lecture' => 'relevé sur sol sec et dur : il ne dit rien du ressuyage, et l’infiltration mesurée ainsi n’est pas comparable'];
    }

    /**
     * Le conseil d'arrosage d'une parcelle : combien de passages par semaine, de
     * combien de millimètres, pour deux semaines de référence.
     *
     * @param array{infiltration_mm_h: mixed, etat_humidite: mixed, jours_depuis_pluie: mixed, date?: mixed}|null $releve
     * @return array{
     *   classe: ?array<string, string>, ressuyage: ?array<string, string>, mesure_a_refaire: bool,
     *   semaines: list<array{titre: string, mm: float, passages: int, dose_mm: float, minutes_mini: ?int}>
     * }
     */
    public static function frequence(?array $releve): array
    {
        $infiltration = $releve !== null && $releve['infiltration_mm_h'] !== null && $releve['infiltration_mm_h'] !== ''
            ? (float) $releve['infiltration_mm_h'] : null;
        $etat = $releve !== null && ($releve['etat_humidite'] ?? '') !== '' ? (string) $releve['etat_humidite'] : null;
        $jours = $releve !== null && ($releve['jours_depuis_pluie'] ?? null) !== null && $releve['jours_depuis_pluie'] !== ''
            ? (int) $releve['jours_depuis_pluie'] : null;

        $classe = $infiltration !== null ? self::classeInfiltration($infiltration) : null;
        $ressuyage = self::ressuyage($etat, $jours);
        // La fiche exige « toujours le même » état : une infiltration hors sol ressuyé ne se compare pas.
        $aRefaire = $infiltration !== null && $etat !== null && $etat !== 'ressuyé';

        // Sans infiltration mesurée, on ne dit rien de plus qu'un repère médian.
        $dose = self::DOSES[$classe['famille'] ?? 'moyen'];
        $semaines = [];
        foreach ([
            ['Une semaine moyenne d’été', self::MOYENNE_MM_JOUR * 7],
            ['Une semaine de pointe', self::POINTE_MM_JOUR * 7],
        ] as [$titre, $mm]) {
            $passages = max(1, (int) ceil($mm / $dose));
            // Un sol qui se ressuie mal ne supporte pas d'être remouillé sans cesse.
            if (($ressuyage['cle'] ?? '') === 'lent') {
                $passages = min($passages, 2);
            }
            $parPassage = round($mm / $passages, 1);
            $semaines[] = [
                'titre' => $titre, 'mm' => $mm, 'passages' => $passages, 'dose_mm' => $parPassage,
                // Verser au plus aussi vite que le sol boit : durée minimale d'un passage.
                'minutes_mini' => $infiltration !== null && $infiltration > 0 ? (int) ceil($parPassage / $infiltration * 60) : null,
            ];
        }
        return ['classe' => $classe, 'ressuyage' => $ressuyage, 'mesure_a_refaire' => $aRefaire, 'semaines' => $semaines];
    }
}
