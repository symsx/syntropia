<?php

declare(strict_types=1);

namespace Syntropia;

/**
 * Le scénario décrit une implantation vivante, avec plusieurs fonctions.
 *
 * Port de `Scenario` du dépôt de référence (`Syntropie\Scenario`), chantier
 * `chantier-scenario-plantation.md`, Phase 3 — les règles, sans base : valider
 * une saisie, placer une plante dans le temps, en tirer la ligne à écrire.
 *
 * **Ce qui diffère de la référence**, et pourquoi :
 * - `quantite_mode` reste `manuel` : le mode `plan_bordure` (la quantité
 *   déduite d'un plan de bordures) suppose `ImplantationBordures`, hors
 *   périmètre ;
 * - les lignes « historiques » (rôles d'avant les `details`, venues de CSV)
 *   ne sont pas adaptées : syntropia n'en a pas ;
 * - **en jardin-forêt il n'y a pas d'axe du temps** (`Conduite::
 *   scenarioADesTemps()`) : une plante est posée à sa place et y reste, ses
 *   repères temporels valent alors toujours « de la première période au
 *   climax » — la saisie n'est pas demandée, jamais devinée ;
 * - les messages nomment le champ en français plutôt que par sa clé.
 */
final class Scenario
{
    /** Les sept périodes du film, de la première plantation au climax. */
    public const PERIODES = [
        'placenta1' => ['Placenta 1', '90 jours'],
        'placenta2' => ['Placenta 2', '180 jours'],
        'placenta3' => ['Placenta 3', '1 à 3 ans'],
        'sec1' => ['Secondaire 1', '3 à 15 ans'],
        'sec2' => ['Secondaire 2', '15 à 25 ans'],
        'sec3' => ['Secondaire 3', '25 à 60 ans'],
        'climax' => ['Climax', '60 ans et +'],
    ];

    /** Ce qu'une plante peut faire pour le système — cumulable. */
    public const FONCTIONS = [
        'biomasse' => 'Fournir de la biomasse au sol',
        'perturbation' => 'Relancer par la perturbation',
        'protection' => 'Protéger les jeunes plants de l’abroutement',
        'ombre' => 'Ombrer et protéger des brûlures',
        'vent' => 'Freiner le vent',
        'bloque_herbe' => 'Bloquer l’avancée de l’herbe',
        'couvre_sol' => 'Couvrir le sol',
        'azote' => 'Fixer l’azote atmosphérique',
        'mineraux' => 'Recycler les minéraux',
        'nitrates' => 'Intercepter les nitrates',
        'racines' => 'Structurer le sol par les racines',
        'sol' => 'Entretenir la vie du sol',
        'eau' => 'Conserver l’humidité et favoriser l’infiltration',
        'erosion' => 'Limiter l’érosion',
        'nectar' => 'Offrir du nectar',
        'pollen' => 'Offrir du pollen aux insectes',
        'pollinisation' => 'Permettre la pollinisation d’un autre végétal',
        'refuge' => 'Abriter et nourrir les auxiliaires',
        'diversion' => 'Détourner un ravageur des productions',
        'tuteur' => 'Servir de tuteur vivant',
        'multiplication' => 'Fournir graines, boutures ou plants',
        'porte_greffe' => 'Servir de porte-greffe',
        'secours' => 'Assurer un relais ou un secours',
        'nourrice' => 'Préparer l’installation des suivants',
    ];

    /** Où la plante est posée sur la parcelle — les bordures sont celles du plan. */
    public const EMPLACEMENTS = [
        'centre' => 'Planches de culture',
        'bord_ext' => 'Bordure externe',
        'bord_int' => 'Bordure interne',
        'autre' => 'Autre emplacement / végétal existant',
    ];

    /** Le dernier repère temporel : « le climax, horizon ouvert ». */
    public const POSITION_MAX = 28;

    /** Ce que la colonne `parcelle.production_*` peut porter. */
    public const PRODUCTION_MAX = 190;

    private const LIMITES = [
        'espece' => ['Le nom du végétal', 160], 'repere' => ['Le repère', 160],
        'beneficiaire' => ['Le bénéficiaire', 500], 'plantation' => ['La plantation', 160],
        'recolte' => ['La récolte', 160], 'conduite_taille' => ['La conduite de taille', 120],
        'type_plant' => ['Le type de plant', 60], 'source_valeur' => ['La source', 120],
        'notes' => ['Les notes', 12000],
    ];

    public static function texte(array $data, string $key): string
    {
        return isset($data[$key]) && is_scalar($data[$key]) ? trim((string) $data[$key]) : '';
    }

    /**
     * Segmentation prudente d'un texte libre en souhaits : un par ligne ou
     * séparé par `;` ou `,`, sans puce, sans doublon à la casse près.
     * **Aucun rapprochement botanique automatique** — « tomates » reste
     * « tomates ».
     *
     * @return list<string>
     */
    public static function souhaits(string $texte): array
    {
        $resultat = [];
        foreach (preg_split('/[\r\n;,]+/u', $texte) ?: [] as $item) {
            $item = preg_replace('/^\s*(?:[-*•]|\d+[.)])\s*/u', '', trim($item));
            if ($item !== '') {
                // La première graphie est gardée : « tomates » puis « TOMATES »
                // reste « tomates » (la référence gardait la dernière).
                $resultat[mb_strtolower($item)] ??= $item;
            }
        }
        return array_values($resultat);
    }

    /** L'identifiant stable d'un souhait — sa priorité et son texte. */
    public static function cleSouhait(string $priorite, string $texte): string
    {
        return hash('sha256', $priorite . ':' . $texte);
    }

    /**
     * Les productions retenues pour une parcelle, telles que la base les porte
     * (`production_principale`/`production_secondaire`, un souhait par ligne).
     *
     * @param array<string, mixed> $parcelle
     * @return array<string, array{texte: string, priorite: string}> clé de souhait ⇒ souhait
     */
    public static function selectionDe(array $parcelle): array
    {
        $selection = [];
        foreach (['principale', 'secondaire'] as $priorite) {
            foreach (self::souhaits((string) ($parcelle['production_' . $priorite] ?? '')) as $texte) {
                $selection[self::cleSouhait($priorite, $texte)] = ['texte' => $texte, 'priorite' => $priorite];
            }
        }
        return $selection;
    }

    /** Le jeton du verrou sur le choix des productions — un changement entre-temps le périme. */
    public static function versionProductions(array $parcelle): string
    {
        return hash('sha256', json_encode([$parcelle['production_principale'] ?? null,
            $parcelle['production_secondaire'] ?? null]));
    }

    /** @return array<string, mixed> */
    public static function details(array $ligne): array
    {
        $json = json_decode((string) ($ligne['details'] ?? ''), true);
        return is_array($json) ? $json : [];
    }

    /** Le jeton du verrou sur une ligne : un hash de la ligne entière, relue sous verrou. */
    public static function version(array $ligne): string
    {
        return hash('sha256', json_encode($ligne, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /**
     * Le repère temporel (0 à 28) du début — ou de la fin — d'une période :
     * quatre repères par période (début, premier quart, milieu, dernier quart),
     * le 28 étant « climax, horizon ouvert ».
     */
    public static function position(string $phase, bool $fin = false): int
    {
        $i = array_search($phase, array_keys(self::PERIODES), true);
        return $i === false ? ($fin ? self::POSITION_MAX : 0) : (int) $i * 4 + ($fin ? 4 : 0);
    }

    public static function libellePosition(int $position): string
    {
        if ($position >= self::POSITION_MAX) {
            return 'Climax — horizon ouvert';
        }
        $phase = array_values(self::PERIODES)[intdiv(max(0, $position), 4)][0];
        return $phase . ' — ' . ['début', 'premier quart', 'milieu', 'dernier quart'][max(0, $position) % 4];
    }

    /**
     * La ligne de formulaire d'un végétal : ce qu'on affiche pour l'éditer, ou
     * les valeurs vides d'un nouveau.
     *
     * @param array<string, mixed> $ligne une ligne de `scenario_ligne`, ou `[]`
     * @return array<string, mixed>
     */
    public static function formulaire(array $ligne = []): array
    {
        $d = self::details($ligne);
        return [
            'id' => (string) ($ligne['id'] ?? '0'),
            'version' => $ligne === [] ? '' : self::version($ligne),
            'espece' => (string) ($ligne['espece'] ?? ''),
            'strate' => (string) ($ligne['strate'] ?? ''),
            'production' => (string) ($d['production_cle'] ?? ''),
            'fonctions' => is_array($d['fonctions'] ?? null) ? $d['fonctions'] : [],
            'debut' => (string) ($d['debut'] ?? self::position((string) ($ligne['entree'] ?? ''))),
            'fin' => (string) ($d['fin'] ?? self::position((string) ($ligne['sortie'] ?? ''), true)),
            'retrait' => isset($d['retrait']) ? (string) $d['retrait'] : '',
            'emplacement' => (string) ($d['emplacement'] ?? 'centre'),
            'repere' => (string) ($d['repere'] ?? ''),
            'beneficiaire' => (string) ($d['beneficiaire'] ?? ''),
            'plantation' => (string) ($d['plantation'] ?? ''),
            'recolte' => (string) ($d['recolte'] ?? ''),
            'couleur' => (string) ($d['couleur'] ?? 'auto'),
            'nb_pieds' => (string) ($ligne['nb_pieds'] ?? ''),
            'espacement_m' => (string) ($ligne['espacement_m'] ?? ''),
            'conduite_taille' => (string) ($ligne['conduite_taille'] ?? ''),
            'type_plant' => (string) ($ligne['type_plant'] ?? ''),
            'origine' => (string) ($ligne['origine'] ?? ''),
            'source_valeur' => (string) ($ligne['source_valeur'] ?? ''),
            'notes' => (string) ($ligne['notes'] ?? ''),
        ];
    }

    /**
     * Valide une saisie, tout ou rien : aucune troncature, aucune strate ni
     * durée devinée. Rend soit des erreurs, soit la ligne prête à écrire.
     *
     * @param array<string, mixed> $data la saisie brute
     * @param array<string, array{texte?: string, priorite: string}> $productions
     *        les productions que la parcelle peut viser, par clé de souhait
     * @return array{erreurs: list<string>, ligne: ?array<string, mixed>}
     */
    public static function valider(array $data, Conduite $conduite, array $productions): array
    {
        $erreurs = [];
        $textes = [];
        foreach (self::LIMITES as $key => [$libelle, $max]) {
            $textes[$key] = self::texte($data, $key);
            if (mb_strlen($textes[$key]) > $max) {
                $erreurs[] = "$libelle : limitez à $max caractères.";
            }
        }
        if ($textes['espece'] === '') {
            $erreurs[] = 'Choisissez ou nommez un végétal.';
        }

        $strate = self::texte($data, 'strate');
        if (!array_key_exists($strate, $conduite->strates())) {
            $erreurs[] = 'Choisissez une strate de cette conduite.';
        }

        $productionCle = self::texte($data, 'production');
        $production = $productions[$productionCle] ?? null;
        if ($productionCle !== '' && $production === null) {
            $erreurs[] = 'Choisissez une production de cette parcelle.';
        }

        $fonctions = $data['fonctions'] ?? [];
        if (!is_array($fonctions) || count($fonctions) > count(self::FONCTIONS)
            || array_filter($fonctions, static fn ($v) => !is_string($v) || !isset(self::FONCTIONS[$v])) !== []) {
            $erreurs[] = 'Les fonctions choisies ne sont pas reconnues.';
            $fonctions = [];
        }
        $fonctions = array_values(array_unique($fonctions));
        if ($production === null && $fonctions === []) {
            $erreurs[] = 'Choisissez une production ou au moins une fonction d’accompagnement.';
        }

        // Le temps : sans axe du temps (jardin-forêt), la plante est là de la
        // première période au climax, et la saisie n'est pas lue.
        if ($conduite->scenarioADesTemps()) {
            $positions = [];
            foreach (['debut', 'fin', 'retrait'] as $key) {
                $v = self::texte($data, $key);
                $positions[$key] = $v === '' && $key === 'retrait' ? null : filter_var($v, FILTER_VALIDATE_INT);
                if ($positions[$key] === false
                    || ($positions[$key] !== null && ($positions[$key] < 0 || $positions[$key] > self::POSITION_MAX))) {
                    $erreurs[] = 'Choisissez des repères temporels valides.';
                }
            }
            ['debut' => $debut, 'fin' => $fin, 'retrait' => $retrait] = $positions;
            if (is_int($debut) && is_int($fin) && $debut >= $fin) {
                $erreurs[] = 'La sortie doit suivre l’arrivée dans la strate.';
            }
            if ($retrait !== null && is_int($retrait) && is_int($debut) && is_int($fin)
                && ($retrait < $debut || $retrait >= $fin)) {
                $erreurs[] = 'Le retrait progressif commence pendant la période de présence.';
            }
        } else {
            $debut = 0;
            $fin = self::POSITION_MAX;
            $retrait = null;
        }

        $emplacement = self::texte($data, 'emplacement');
        if (!isset(self::EMPLACEMENTS[$emplacement])) {
            $erreurs[] = 'Choisissez un emplacement.';
        }
        $couleur = self::texte($data, 'couleur');
        if (!in_array($couleur, ['auto', 'rouge', 'vert', 'orange', 'bleu'], true)) {
            $erreurs[] = 'Choisissez une couleur proposée.';
        }
        $origine = self::texte($data, 'origine');
        if (!in_array($origine, ['', 'achat', 'multiplication', 'rna', 'mixte'], true)) {
            $erreurs[] = 'Choisissez une origine proposée.';
        }
        $nb = self::texte($data, 'nb_pieds');
        $quantite = $nb === '' ? null : filter_var($nb, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
        if ($quantite === false) {
            $erreurs[] = 'La quantité doit être un entier entre 1 et 65 535.';
        }
        $esp = str_replace(',', '.', self::texte($data, 'espacement_m'));
        if ($esp !== '' && (!is_numeric($esp) || (float) $esp < 0.01 || (float) $esp > 999.99)) {
            $erreurs[] = 'L’espacement doit être compris entre 0,01 et 999,99 mètres.';
        }

        if ($erreurs !== []) {
            return ['erreurs' => array_values(array_unique($erreurs)), 'ligne' => null];
        }

        $categorie = $production !== null ? ($fonctions !== [] ? 'mixte' : 'production') : 'service';
        $role = $production !== null ? 'production_' . $production['priorite'] : match ($emplacement) {
            'bord_ext' => 'bordure_exterieure',
            'bord_int' => 'bordure_interieure',
            default => in_array('secours', $fonctions, true) ? 'secours' : 'support',
        };
        $d = [
            'quantite_mode' => 'manuel', 'version' => 1,
            'production_cle' => $productionCle, 'production' => $production,
            'fonctions' => $fonctions, 'categorie' => $categorie, 'couleur' => $couleur,
            'debut' => $debut, 'fin' => $fin, 'retrait' => $retrait, 'emplacement' => $emplacement,
        ];
        foreach (['repere', 'beneficiaire', 'plantation', 'recolte'] as $key) {
            $d[$key] = $textes[$key];
        }
        $phases = array_keys(self::PERIODES);
        $ligne = [
            // Le remplissage suit un ordre prescrit : production principale,
            // secondaire, biomasse, PLACENTAIRES EN DERNIER (voir le schéma).
            'ordre' => $production !== null ? ($production['priorite'] === 'principale' ? 1 : 2) : ($fin <= 12 ? 4 : 3),
            'strate' => $strate, 'espece' => $textes['espece'], 'role' => $role,
            'couleur_plan' => $couleur === 'auto'
                ? ['production' => 'rouge', 'mixte' => 'orange', 'service' => 'vert'][$categorie] : $couleur,
            'type_plant' => $textes['type_plant'] ?: null, 'origine' => $origine ?: null,
            'entree' => $phases[intdiv($debut, 4)], 'sortie' => $phases[intdiv($fin - 1, 4)],
            'espacement_m' => $esp === '' ? null : round((float) $esp, 2), 'nb_pieds' => $quantite,
            'conduite_taille' => $textes['conduite_taille'] ?: null, 'source_valeur' => $textes['source_valeur'] ?: null,
            'notes' => $textes['notes'] ?: null,
            'details' => json_encode($d, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ];
        return ['erreurs' => [], 'ligne' => $ligne];
    }
}
