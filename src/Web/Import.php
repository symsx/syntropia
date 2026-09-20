<?php

declare(strict_types=1);

namespace Syntropia\Web;

use PDO;
use Syntropia\Database;
use Syntropia\Scenario;
use Syntropia\Version;

/**
 * L'import d'un projet — un fichier d'export devient un projet **neuf**.
 *
 * Chantier `chantier-export-import-projet.md`, Phase 2.
 *
 * - **Toujours un projet neuf** : jamais une fusion, jamais un écrasement. Celui qui importe en devient le
 *   porteur. Le `code` du site est recalculé, les identifiants sont refaits.
 * - **Un fichier est une entrée non fiable.** `examiner()` ne lit rien dans la base que le schéma, et
 *   n'écrit rien : d'abord `Export::controler()` (c'est un export, **il n'a pas été modifié**, ses versions
 *   sont compatibles), puis chaque valeur de chaque ligne est vérifiée contre **le schéma réel de la
 *   base** — type, longueur, plage, énumération, vide permis ou non. Rien n'est écrit avant que tout soit
 *   vérifié, puis tout s'écrit dans **une transaction** : un import qui échoue ne laisse rien.
 * - **Les règles suivent le schéma**, comme l'export : lues dans `information_schema`, pas recopiées à la
 *   main — une colonne ajoutée au schéma est vérifiée sans qu'on y touche. Seules les quatre colonnes de
 *   JSON (`parcelle.plan`, `scenario_ligne.details`, `calque_element.geometrie` et `.proprietes`), que la
 *   base voit comme du texte, ont leur contrôle écrit ici : la carte et le plan de parcelle en attendent
 *   une forme, et un tracé mal formé ne serait pas refusé par la base — c'est la carte qui n'afficherait
 *   plus rien.
 * - **Un nom de colonne du fichier ne devient jamais un nom de colonne SQL sans être reconnu** : les noms
 *   écrits viennent de `information_schema`, jamais du fichier ; le fichier ne fournit que des valeurs,
 *   liées par des paramètres. Une colonne inconnue, ou qui ne doit jamais entrer (`compte_id`, `saisi_par`,
 *   un identifiant), refuse le fichier.
 * - **Les dates d'origine sont gardées** (`cree_le`, `ecrit_le`, `depuis_le`… telles que dans le fichier) —
 *   décision du porteur, 2026-09-20. La date d'import, elle, est dans `site.origine`.
 * - **Les participants entrent sans compte.** L'importateur peut dire lequel d'entre eux il est : le projet
 *   restauré retrouve alors ses réponses ; sinon il est un nouveau participant.
 */
final class Import
{
    /** Combien d'erreurs on détaille avant de dire « et N autres ». */
    private const MAX_ERREURS = 15;

    /** Les clés que le fichier peut porter à sa racine. */
    private const RACINE = ['format', 'exporte_le', 'versions', 'application', 'projet', 'participants', 'reponses',
        'syntheses', 'ressources', 'gisements', 'commandes', 'especes_locales', 'parcelles', 'calques', 'integrite'];

    /** Les parties du fichier rattachées au site, et leur table. */
    private const PARTIES_DU_SITE = ['syntheses' => 'synthese', 'ressources' => 'ressource', 'gisements' => 'gisement',
        'commandes' => 'commande', 'especes_locales' => 'espece'];

    /** Ce que porte chaque parcelle. */
    private const ENFANTS_PARCELLE = ['scenario' => 'scenario_ligne', 'succession' => 'succession_ligne',
        'releves_sol' => 'releve_sol', 'interventions' => 'intervention'];

    /** Le nom d'une chose, pour dire où est l'erreur. */
    private const LIBELLE = ['synthese' => 'synthèse', 'ressource' => 'ressource', 'gisement' => 'gisement',
        'commande' => 'commande', 'espece' => 'espèce du projet', 'scenario_ligne' => 'végétal du scénario',
        'succession_ligne' => 'végétal de la succession', 'releve_sol' => 'relevé de sol', 'intervention' => 'intervention'];

    /** Les bornes des entiers, par type. [signé, non signé]. */
    private const BORNES = [
        'tinyint' => [[-128, 127], [0, 255]],
        'smallint' => [[-32768, 32767], [0, 65535]],
        'mediumint' => [[-8388608, 8388607], [0, 16777215]],
        'int' => [[-2147483648, 2147483647], [0, 4294967295]],
        'bigint' => [[PHP_INT_MIN, PHP_INT_MAX], [0, PHP_INT_MAX]],
    ];

    /**
     * Ce que le schéma ne peut pas dire : une latitude tient dans un `DECIMAL(8,5)` bien au-delà de 90. Les
     * seules plages de sens que l'atelier connaît, par « table.colonne ».
     */
    private const PLAGES = [
        'site.latitude' => [-90, 90], 'site.longitude' => [-180, 180],
        'site.part_achat_pct' => [0, 100], 'site.part_multiplication_pct' => [0, 100],
        'site.ph' => [0, 14], 'parcelle.ph' => [0, 14],
    ];

    /** @var array<string, array<string, array<string, mixed>>>|null table → colonne → règle */
    private ?array $regles = null;

    public function __construct(private ?PDO $pdo = null) {}

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::syntropia();
    }

    // ------------------------------------------------------------------
    // Examiner : tout vérifier, rien écrire
    // ------------------------------------------------------------------

    /**
     * Ce fichier peut-il devenir un projet ? Ne lit que le schéma et n'écrit rien.
     *
     * @return array{ok: bool, erreurs: list<string>, apercu: ?array<string, mixed>, plan: ?array<string, mixed>}
     */
    public function examiner(string $json): array
    {
        $refus = static fn (array $erreurs): array => ['ok' => false, 'erreurs' => $erreurs, 'apercu' => null, 'plan' => null];

        $export = new Export($this->pdo());
        $schemaBase = $export->schemaDeLaBase();
        if (($desaccord = Export::accordCodeEtBase($schemaBase)) !== null) {
            return $refus([$desaccord]);
        }
        $controle = Export::controler($json, (int) $schemaBase);
        if (!$controle['ok'] || $controle['doc'] === null) {
            return $refus($controle['erreurs']);
        }
        $doc = $controle['doc'];

        $erreurs = [];
        $plan = $this->valider($doc, $erreurs);
        if ($erreurs !== []) {
            return $refus($erreurs);
        }

        $participants = [];
        foreach ($plan['participants'] as $p) {
            $participants[$p['ref']] = (string) ($p['valeurs']['nom'] ?? $p['ref']);
        }
        return [
            'ok' => true, 'erreurs' => [], 'plan' => $plan,
            'apercu' => [
                'nom' => (string) $plan['projet']['nom'],
                'exporte_le' => (string) ($doc['exporte_le'] ?? ''),
                'versions' => $controle['versions'],
                'inventaire' => Export::inventaire($doc),
                'participants' => $participants,
            ],
        ];
    }

    /** Le nom choisi pour le projet : présent, et de la longueur que la colonne accepte. */
    public function verifierNom(string $nom): ?string
    {
        $nom = trim($nom);
        if ($nom === '') {
            return 'Le projet a besoin d’un nom.';
        }
        $max = (int) ($this->regles()['site']['nom']['longueur'] ?? 160);
        return mb_strlen($nom) > $max ? "Le nom du projet est trop long ($max caractères au plus)." : null;
    }

    // ------------------------------------------------------------------
    // Les règles du schéma
    // ------------------------------------------------------------------

    /**
     * Ce que la base exige de chaque colonne des tables du projet — lu dans `information_schema`.
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    private function regles(): array
    {
        if ($this->regles !== null) {
            return $this->regles;
        }
        $tables = ['site', 'participant', 'reponse', 'synthese', 'ressource', 'gisement', 'commande', 'espece', 'parcelle',
            'scenario_ligne', 'succession_ligne', 'releve_sol', 'intervention', 'celebration', 'reconnaissance', 'calque_element'];
        $in = implode(',', array_fill(0, count($tables), '?'));
        $req = $this->pdo()->prepare(
            "SELECT table_name AS t, column_name AS c, data_type AS d, column_type AS ct,
                    character_maximum_length AS lg, numeric_precision AS np, numeric_scale AS ns,
                    is_nullable AS nl, column_default AS df, extra AS ex
               FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name IN ($in)
              ORDER BY table_name, ordinal_position");
        $req->execute($tables);
        $regles = [];
        foreach ($req->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $type = strtolower((string) $r['d']);
            $ct = (string) $r['ct'];
            $enum = null;
            if ($type === 'enum' && preg_match_all("/'((?:[^']|'')*)'/", $ct, $m)) {
                $enum = array_map(static fn (string $v): string => str_replace("''", "'", $v), $m[1]);
            }
            $regles[strtolower((string) $r['t'])][(string) $r['c']] = [
                'type' => $type,
                'non_signe' => str_contains(strtolower($ct), 'unsigned'),
                'longueur' => $r['lg'] !== null ? (int) $r['lg'] : null,
                'precision' => $r['np'] !== null ? (int) $r['np'] : null,
                'echelle' => $r['ns'] !== null ? (int) $r['ns'] : null,
                'enum' => $enum,
                'vide_permis' => strtoupper((string) $r['nl']) === 'YES',
                // Une colonne est exigée quand la base ne saurait pas quoi y mettre.
                'requise' => strtoupper((string) $r['nl']) === 'NO' && $r['df'] === null
                    && !str_contains(strtolower((string) $r['ex']), 'auto_increment'),
            ];
        }
        return $this->regles = $regles;
    }

    // ------------------------------------------------------------------
    // Valider le document
    // ------------------------------------------------------------------

    /**
     * Vérifie tout le document et le range en un plan d'écriture : des lignes normalisées, prêtes à être
     * liées. Les erreurs s'accumulent (les premières seulement sont détaillées).
     *
     * @param array<string, mixed> $doc
     * @param list<string> $erreurs
     * @return array<string, mixed>
     */
    private function valider(array $doc, array &$erreurs): array
    {
        $plan = ['projet' => [], 'participants' => [], 'reponses' => [], 'parties' => [], 'parcelles' => [], 'calques' => []];
        $ajouter = static function (string $message) use (&$erreurs): void {
            $erreurs[] = $message;
        };

        foreach (array_keys($doc) as $cle) {
            if (!in_array($cle, self::RACINE, true)) {
                $ajouter("Le fichier contient une partie inconnue : « $cle ».");
            }
        }

        // Le projet.
        $projet = $doc['projet'] ?? null;
        if (!is_array($projet)) {
            $ajouter('Le fichier ne décrit pas de projet.');
            $plan['projet'] = ['nom' => ''];
        } else {
            $plan['projet'] = $this->ligne('site', $projet, 'Le projet', [], $erreurs, [...Export::JAMAIS, ...Export::SITE_JAMAIS]) ?? ['nom' => ''];
        }

        // Les participants — désignés par une référence de fichier.
        $refsParticipant = [];
        foreach ($this->liste($doc, 'participants', $erreurs) as $i => $p) {
            $chemin = 'Participant n° ' . ($i + 1);
            $ref = is_array($p) ? ($p['ref'] ?? null) : null;
            if (!is_string($ref) || preg_match('/^p[0-9]{1,5}$/', $ref) !== 1 || isset($refsParticipant[$ref])) {
                $ajouter("$chemin : sa référence est absente, mal formée ou en double.");
                continue;
            }
            $refsParticipant[$ref] = true;
            $v = $this->ligne('participant', $p, $chemin, ['ref'], $erreurs, Export::JAMAIS);
            if ($v !== null) {
                $plan['participants'][] = ['ref' => $ref, 'valeurs' => $v];
            }
        }

        // Les réponses : chacune désigne son participant.
        foreach ($this->liste($doc, 'reponses', $erreurs) as $i => $r) {
            $chemin = 'Réponse n° ' . ($i + 1);
            $ref = is_array($r) ? ($r['participant'] ?? null) : null;
            if (!is_string($ref) || !isset($refsParticipant[$ref])) {
                $ajouter("$chemin : son participant est absent ou inconnu.");
                continue;
            }
            $v = $this->ligne('reponse', $r, $chemin, ['participant'], $erreurs, Export::JAMAIS);
            if ($v !== null) {
                $plan['reponses'][] = ['participant' => $ref, 'valeurs' => $v];
            }
        }

        // Ce qui est rattaché au site.
        foreach (self::PARTIES_DU_SITE as $partie => $table) {
            foreach ($this->liste($doc, $partie, $erreurs) as $i => $l) {
                $v = $this->ligne($table, $l, ucfirst(self::LIBELLE[$table]) . ' n° ' . ($i + 1), [], $erreurs, Export::JAMAIS);
                if ($v !== null) {
                    $plan['parties'][$table][] = $v;
                }
            }
        }

        // Les parcelles, avec ce qu'elles portent.
        $refsParcelle = [];
        foreach ($this->liste($doc, 'parcelles', $erreurs) as $i => $p) {
            $chemin = 'Parcelle n° ' . ($i + 1);
            $ref = is_array($p) ? ($p['ref'] ?? null) : null;
            if (!is_string($ref) || preg_match('/^a[0-9]{1,5}$/', $ref) !== 1 || isset($refsParcelle[$ref])) {
                $ajouter("$chemin : sa référence est absente, mal formée ou en double.");
                continue;
            }
            $refsParcelle[$ref] = true;
            $chemin = "Parcelle $ref";
            $extra = ['ref', 'celebrations', ...array_keys(self::ENFANTS_PARCELLE)];
            $v = $this->ligne('parcelle', $p, $chemin, $extra, $erreurs, Export::JAMAIS);
            $entree = ['ref' => $ref, 'valeurs' => $v ?? [], 'enfants' => [], 'celebrations' => []];
            foreach (self::ENFANTS_PARCELLE as $partie => $table) {
                foreach ($this->liste($p, $partie, $erreurs, $chemin) as $j => $l) {
                    $lv = $this->ligne($table, $l, "$chemin, " . self::LIBELLE[$table] . ' n° ' . ($j + 1), [], $erreurs, Export::JAMAIS);
                    if ($lv !== null) {
                        $entree['enfants'][$table][] = $lv;
                    }
                }
            }
            foreach ($this->liste($p, 'celebrations', $erreurs, $chemin) as $j => $c) {
                $cc = "$chemin, célébration n° " . ($j + 1);
                $cv = $this->ligne('celebration', $c, $cc, ['reconnaissances'], $erreurs, Export::JAMAIS);
                $reconnaissances = [];
                foreach ($this->liste(is_array($c) ? $c : [], 'reconnaissances', $erreurs, $cc) as $k => $r) {
                    $rc = "$cc, reconnaissance n° " . ($k + 1);
                    $rp = is_array($r) ? ($r['participant'] ?? null) : null;
                    if (!is_string($rp) || !isset($refsParticipant[$rp])) {
                        $ajouter("$rc : son participant est absent ou inconnu.");
                        continue;
                    }
                    $rv = $this->ligne('reconnaissance', $r, $rc, ['participant'], $erreurs, Export::JAMAIS);
                    if ($rv !== null) {
                        $reconnaissances[] = ['participant' => $rp, 'valeurs' => $rv];
                    }
                }
                if ($cv !== null) {
                    $entree['celebrations'][] = ['valeurs' => $cv, 'reconnaissances' => $reconnaissances];
                }
            }
            $plan['parcelles'][] = $entree;
        }

        // Les tracés : ceux d'une parcelle la désignent.
        foreach ($this->liste($doc, 'calques', $erreurs) as $i => $c) {
            $chemin = 'Tracé n° ' . ($i + 1);
            $ref = is_array($c) ? ($c['parcelle'] ?? null) : null;
            if ($ref !== null && (!is_string($ref) || !isset($refsParcelle[$ref]))) {
                $ajouter("$chemin : sa parcelle est inconnue.");
                continue;
            }
            $v = $this->ligne('calque_element', $c, $chemin, ['parcelle'], $erreurs, Export::JAMAIS);
            if ($v !== null) {
                $plan['calques'][] = ['parcelle' => $ref, 'valeurs' => $v];
            }
        }

        if (count($erreurs) > self::MAX_ERREURS) {
            $reste = count($erreurs) - self::MAX_ERREURS;
            $erreurs = array_slice($erreurs, 0, self::MAX_ERREURS);
            $erreurs[] = "… et $reste autre" . ($reste > 1 ? 's' : '') . ' erreur' . ($reste > 1 ? 's' : '') . '.';
        }
        return $plan;
    }

    /**
     * Une partie du fichier : absente = vide ; sinon une liste.
     *
     * @param array<string, mixed> $conteneur
     * @param list<string> $erreurs
     * @return list<mixed>
     */
    private function liste(array $conteneur, string $cle, array &$erreurs, string $chemin = ''): array
    {
        if (!array_key_exists($cle, $conteneur) || $conteneur[$cle] === null) {
            return [];
        }
        if (!is_array($conteneur[$cle]) || !array_is_list($conteneur[$cle])) {
            $erreurs[] = ($chemin !== '' ? "$chemin : " : '') . "« $cle » devrait être une liste.";
            return [];
        }
        return $conteneur[$cle];
    }

    /**
     * Une ligne d'une table : ses clés sont-elles connues, ses valeurs valides ? Rend les valeurs prêtes à
     * être liées, ou `null` si une erreur a été signalée.
     *
     * @param mixed $ligne
     * @param list<string> $extra les clés du fichier qui ne sont pas des colonnes (références, listes filles)
     * @param list<string> $exclues les colonnes que le fichier ne doit pas porter
     * @param list<string> $erreurs
     * @return array<string, mixed>|null
     */
    private function ligne(string $table, mixed $ligne, string $chemin, array $extra, array &$erreurs, array $exclues): ?array
    {
        if (!is_array($ligne) || ($ligne !== [] && array_is_list($ligne))) {
            $erreurs[] = "$chemin : une ligne devrait être un ensemble de champs.";
            return null;
        }
        $regles = $this->regles()[$table] ?? [];
        $avant = count($erreurs);

        foreach (array_keys($ligne) as $cle) {
            $cle = (string) $cle;
            if (in_array($cle, $extra, true)) {
                continue;
            }
            if (in_array($cle, $exclues, true)) {
                $erreurs[] = "$chemin : le champ « $cle » ne doit jamais figurer dans un fichier d’export.";
            } elseif (!isset($regles[$cle])) {
                $erreurs[] = "$chemin : le champ « $cle » n’existe pas dans cet atelier.";
            }
        }

        $valeurs = [];
        foreach ($regles as $colonne => $regle) {
            if (in_array($colonne, $exclues, true)) {
                continue;
            }
            if (!array_key_exists($colonne, $ligne)) {
                if ($regle['requise']) {
                    $erreurs[] = "$chemin : le champ « $colonne » manque.";
                }
                continue;              // sinon la base met sa valeur par défaut
            }
            try {
                $valeurs[$colonne] = $this->valeur($table, $colonne, $regle, $ligne[$colonne], $ligne);
            } catch (\UnexpectedValueException $e) {
                $erreurs[] = "$chemin, champ « $colonne » : " . $e->getMessage();
            }
        }
        return count($erreurs) === $avant ? $valeurs : null;
    }

    /**
     * Une valeur, contre la règle de sa colonne. Rend ce qu'il faut lier, ou lève `UnexpectedValueException`.
     *
     * @param array<string, mixed> $regle
     * @param array<string, mixed> $ligne la ligne entière (une géométrie dépend du type de son tracé)
     */
    private function valeur(string $table, string $colonne, array $regle, mixed $v, array $ligne): mixed
    {
        if ($v === null) {
            if (!$regle['vide_permis']) {
                throw new \UnexpectedValueException('ne peut pas être vide.');
            }
            return null;
        }

        // Les quatre colonnes de JSON, que la base voit comme du texte.
        switch ("$table.$colonne") {
            case 'parcelle.plan':
                return self::plan($v);
            case 'scenario_ligne.details':
                return self::details($v);
            case 'calque_element.geometrie':
                return self::geometrie($v, is_string($ligne['type'] ?? null) ? $ligne['type'] : '');
            case 'calque_element.proprietes':
                return self::proprietes($v);
        }

        $type = $regle['type'];
        if (in_array($type, ['tinyint', 'smallint', 'mediumint', 'int', 'bigint'], true)) {
            if (!is_int($v)) {
                throw new \UnexpectedValueException('devrait être un nombre entier.');
            }
            [$min, $max] = self::BORNES[$type][$regle['non_signe'] ? 1 : 0];
            if ($v < $min || $v > $max) {
                throw new \UnexpectedValueException("doit être compris entre $min et $max.");
            }
            self::plage("$table.$colonne", $v);
            return $v;
        }
        if ($type === 'decimal') {
            if (!is_int($v) && !is_float($v)) {
                throw new \UnexpectedValueException('devrait être un nombre.');
            }
            $limite = 10 ** ((int) $regle['precision'] - (int) $regle['echelle']);
            if (abs($v) >= $limite || ($regle['non_signe'] && $v < 0)) {
                throw new \UnexpectedValueException('est hors de ce que la colonne peut porter.');
            }
            if (abs(round((float) $v, (int) $regle['echelle']) - $v) > 1e-9) {
                throw new \UnexpectedValueException('a plus de décimales que la colonne n’en garde (' . $regle['echelle'] . ').');
            }
            self::plage("$table.$colonne", $v);
            return $v;
        }
        if ($type === 'float' || $type === 'double') {
            if (!is_int($v) && !is_float($v)) {
                throw new \UnexpectedValueException('devrait être un nombre.');
            }
            return $v;
        }

        return $this->texte($table, $colonne, $regle, $v);
    }

    /** Une valeur qui n'est pas un nombre : un texte, une date, une énumération. */
    private function texte(string $table, string $colonne, array $regle, mixed $v): string
    {
        $type = $regle['type'];
        // Tout le reste se lit dans un texte.
        if (!is_string($v)) {
            throw new \UnexpectedValueException('devrait être un texte.');
        }
        if (str_contains($v, "\0")) {
            throw new \UnexpectedValueException('contient un caractère nul.');
        }
        switch ($type) {
            case 'varchar':
            case 'char':
                if (mb_strlen($v) > (int) $regle['longueur']) {
                    throw new \UnexpectedValueException('dépasse ' . $regle['longueur'] . ' caractères.');
                }
                return $v;
            case 'tinytext':
            case 'text':
            case 'mediumtext':
            case 'longtext':
                if ($regle['longueur'] !== null && strlen($v) > $regle['longueur']) {
                    throw new \UnexpectedValueException('est trop long pour la colonne (' . $regle['longueur'] . ' octets).');
                }
                return $v;
            case 'enum':
                if (!in_array($v, $regle['enum'] ?? [], true)) {
                    throw new \UnexpectedValueException('doit être l’une de : ' . implode(', ', $regle['enum'] ?? []) . '.');
                }
                return $v;
            case 'date':
                $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $v);
                if ($d === false || $d->format('Y-m-d') !== $v) {
                    throw new \UnexpectedValueException('devrait être une date (AAAA-MM-JJ).');
                }
                return $v;
            case 'datetime':
            case 'timestamp':
                $d = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $v);
                if ($d === false || $d->format('Y-m-d H:i:s') !== $v) {
                    throw new \UnexpectedValueException('devrait être une date et une heure (AAAA-MM-JJ HH:MM:SS).');
                }
                return $v;
        }
        throw new \UnexpectedValueException("a un type que l'import ne sait pas vérifier ($type).");
    }

    // ------------------------------------------------------------------
    // Les quatre colonnes de JSON
    // ------------------------------------------------------------------

    /** La plage de sens d'une colonne, quand l'atelier en connaît une. */
    private static function plage(string $cle, int|float $v): void
    {
        if (isset(self::PLAGES[$cle]) && ($v < self::PLAGES[$cle][0] || $v > self::PLAGES[$cle][1])) {
            throw new \UnexpectedValueException('doit être compris entre ' . self::PLAGES[$cle][0] . ' et ' . self::PLAGES[$cle][1] . '.');
        }
    }

    /** Un nombre, ni texte ni booléen. */
    private static function nombre(mixed $v): bool
    {
        return is_int($v) || is_float($v);
    }

    /**
     * `parcelle.plan` : les bordures de chaque côté et le rattrapage — ce que `conception-parcelles.php` lit.
     */
    private static function plan(mixed $v): ?string
    {
        if (!is_array($v)) {
            throw new \UnexpectedValueException('le plan de la parcelle est illisible.');
        }
        if ($v === []) {
            return null;
        }
        foreach (array_keys($v) as $cle) {
            if (!in_array($cle, ['bordures', 'rattrapage'], true)) {
                throw new \UnexpectedValueException("le plan contient une clé inconnue « $cle ».");
            }
        }
        if (isset($v['rattrapage']) && !in_array($v['rattrapage'], ['planches', 'passe-pieds'], true)) {
            throw new \UnexpectedValueException('le rattrapage doit être « planches » ou « passe-pieds ».');
        }
        $bordures = $v['bordures'] ?? [];
        if (!is_array($bordures)) {
            throw new \UnexpectedValueException('les bordures sont illisibles.');
        }
        foreach ($bordures as $anneau => $cotes) {
            if (!in_array($anneau, ['externe', 'interne'], true) || !is_array($cotes)) {
                throw new \UnexpectedValueException("les bordures contiennent « $anneau », qui n’est ni externe ni interne.");
            }
            foreach ($cotes as $cote => $b) {
                if (!in_array($cote, ['nord', 'sud', 'est', 'ouest'], true) || !is_array($b)
                    || !is_bool($b['actif'] ?? null) || !self::nombre($b['largeur_m'] ?? null)
                    || $b['largeur_m'] < 0.05 || $b['largeur_m'] > 5) {
                    throw new \UnexpectedValueException("la bordure $anneau « $cote » est mal formée (actif oui/non, largeur de 0,05 à 5 m).");
                }
            }
        }
        return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /**
     * `scenario_ligne.details` : les repères d'un végétal. Les clés connues sont vérifiées ; une clé de plus
     * est ignorée par ceux qui lisent, et n'est pas refusée.
     */
    private static function details(mixed $v): ?string
    {
        if (!is_array($v)) {
            throw new \UnexpectedValueException('le détail du végétal est illisible.');
        }
        if ($v === []) {
            return null;
        }
        if (isset($v['emplacement']) && (!is_string($v['emplacement']) || !isset(Scenario::EMPLACEMENTS[$v['emplacement']]))) {
            throw new \UnexpectedValueException('l’emplacement est inconnu.');
        }
        if (isset($v['fonctions'])) {
            if (!is_array($v['fonctions']) || !array_is_list($v['fonctions'])) {
                throw new \UnexpectedValueException('les fonctions devraient être une liste.');
            }
            foreach ($v['fonctions'] as $f) {
                if (!is_string($f) || !isset(Scenario::FONCTIONS[$f])) {
                    throw new \UnexpectedValueException('une fonction est inconnue.');
                }
            }
        }
        if (isset($v['categorie']) && !in_array($v['categorie'], ['production', 'mixte', 'service'], true)) {
            throw new \UnexpectedValueException('la catégorie est inconnue.');
        }
        if (isset($v['couleur']) && !in_array($v['couleur'], ['auto', 'rouge', 'vert', 'orange', 'bleu'], true)) {
            throw new \UnexpectedValueException('la couleur est inconnue.');
        }
        foreach (['debut', 'fin', 'retrait'] as $cle) {
            if (isset($v[$cle]) && (!is_int($v[$cle]) || $v[$cle] < 0 || $v[$cle] > Scenario::POSITION_MAX)) {
                throw new \UnexpectedValueException("le repère « $cle » doit être un entier de 0 à " . Scenario::POSITION_MAX . '.');
            }
        }
        foreach (['repere', 'beneficiaire', 'plantation', 'recolte', 'production_cle'] as $cle) {
            if (isset($v[$cle]) && (!is_string($v[$cle]) || mb_strlen($v[$cle]) > 500)) {
                throw new \UnexpectedValueException("« $cle » devrait être un texte de 500 caractères au plus.");
            }
        }
        if (isset($v['production']) && (!is_array($v['production']) || !is_string($v['production']['texte'] ?? null)
            || !in_array($v['production']['priorite'] ?? null, ['principale', 'secondaire'], true))) {
            throw new \UnexpectedValueException('la production visée est mal formée.');
        }
        return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /**
     * `calque_element.geometrie` : une liste de couples [latitude, longitude] sur Terre, au moins autant que
     * le type de tracé en demande — ce que `Calques` exige d'un tracé posé à la main.
     */
    private static function geometrie(mixed $v, string $type): string
    {
        $points = Calques::pointsDepuis($v);
        if ($points === null) {
            throw new \UnexpectedValueException('devrait être une liste de couples latitude, longitude, chacun sur Terre.');
        }
        $minimum = ['point' => 1, 'ligne' => 2, 'polygone' => 3, 'grille' => 2][$type] ?? 1;
        if (count($points) < $minimum) {
            throw new \UnexpectedValueException("un tracé de type « $type » demande au moins $minimum point(s).");
        }
        return json_encode($points, JSON_THROW_ON_ERROR);
    }

    /**
     * `calque_element.proprietes` : ce qui décrit un tracé. La position d'une étiquette et le centre d'une
     * parcelle sont des couples sur Terre, ou rien — jamais un texte, qui ferait disparaître l'étiquette.
     */
    private static function proprietes(mixed $v): ?string
    {
        if (!is_array($v)) {
            throw new \UnexpectedValueException('les propriétés du tracé sont illisibles.');
        }
        foreach (['etiquette' => 'la position de l’étiquette', 'centre' => 'le centre de la parcelle'] as $cle => $quoi) {
            if (!array_key_exists($cle, $v) || $v[$cle] === null) {
                unset($v[$cle]);
                continue;
            }
            $c = $v[$cle];
            if (!is_array($c) || count($c) !== 2 || !self::nombre($c[0] ?? null) || !self::nombre($c[1] ?? null)
                || abs($c[0]) > 90 || abs($c[1]) > 180) {
                throw new \UnexpectedValueException("$quoi est illisible.");
            }
        }
        return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    // ------------------------------------------------------------------
    // Écrire
    // ------------------------------------------------------------------

    /**
     * Crée le projet. **Tout ou rien** : une transaction, jamais rien d'écrit à moitié. Rend l'identifiant
     * du projet neuf. Le plan vient d'`examiner()` — ce qui n'a pas été vérifié ne s'écrit pas.
     *
     * @param array<string, mixed> $plan
     * @param array<string, mixed> $apercu `apercu` d'`examiner()`, pour la trace d'origine
     * @param string|null $moi la référence du participant que l'importateur est, ou `null`
     * @throws \RuntimeException un refus qu'on peut dire à la personne
     */
    public function creer(array $plan, array $apercu, int $compteId, string $nom, ?string $moi): int
    {
        if (($refus = $this->verifierNom($nom)) !== null) {
            throw new \RuntimeException($refus);
        }
        $refs = array_column($plan['participants'], 'ref');
        if ($moi !== null && !in_array($moi, $refs, true)) {
            throw new \RuntimeException('Ce participant n’est pas dans le fichier.');
        }

        $pdo = $this->pdo();
        $sites = new Sites($pdo);
        $pdo->beginTransaction();
        try {
            // Le site : ses champs, son nom (celui qu'on a choisi), son code neuf, et sa trace d'origine.
            $site = $plan['projet'];
            $site['nom'] = trim($nom);
            $site['code'] = $sites->codePour($site['nom']);
            $site['origine'] = json_encode([
                'nom' => $apercu['nom'], 'exporte_le' => $apercu['exporte_le'],
                'importe_le' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z'),
                'versions' => $apercu['versions'],
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $siteId = $this->inserer('site', $site);

            // Celui qui importe en est le porteur.
            $pdo->prepare("INSERT INTO membre_site (site_id, compte_id, role) VALUES (?, ?, 'porteur')")
                ->execute([$siteId, $compteId]);

            // Les participants : sans compte — sauf celui que l'importateur dit être.
            $participant = [];
            foreach ($plan['participants'] as $p) {
                $v = $p['valeurs'] + ['site_id' => $siteId];
                if ($moi === $p['ref']) {
                    $v['compte_id'] = $compteId;
                }
                $participant[$p['ref']] = $this->inserer('participant', $v);
            }
            foreach ($plan['reponses'] as $r) {
                $this->inserer('reponse', $r['valeurs'] + ['site_id' => $siteId, 'participant_id' => $participant[$r['participant']]]);
            }
            foreach ($plan['parties'] as $table => $lignes) {
                foreach ($lignes as $l) {
                    $this->inserer($table, $l + ['site_id' => $siteId]);
                }
            }

            $parcelle = [];
            foreach ($plan['parcelles'] as $p) {
                $idParcelle = $this->inserer('parcelle', $p['valeurs'] + ['site_id' => $siteId]);
                $parcelle[$p['ref']] = $idParcelle;
                foreach ($p['enfants'] as $table => $lignes) {
                    foreach ($lignes as $l) {
                        $this->inserer($table, $l + ['parcelle_id' => $idParcelle]);
                    }
                }
                foreach ($p['celebrations'] as $c) {
                    $idCelebration = $this->inserer('celebration', $c['valeurs'] + ['parcelle_id' => $idParcelle]);
                    foreach ($c['reconnaissances'] as $r) {
                        $this->inserer('reconnaissance', $r['valeurs']
                            + ['celebration_id' => $idCelebration, 'participant_id' => $participant[$r['participant']]]);
                    }
                }
            }
            foreach ($plan['calques'] as $c) {
                $this->inserer('calque_element', $c['valeurs']
                    + ['site_id' => $siteId, 'parcelle_id' => $c['parcelle'] !== null ? $parcelle[$c['parcelle']] : null]);
            }

            $pdo->commit();
            return $siteId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Une ligne. Les noms de colonnes viennent tous d'`information_schema` (vérifiés à l'examen) ou de ce
     * fichier de code — jamais du fichier importé ; les valeurs sont liées.
     *
     * @param array<string, mixed> $valeurs
     */
    private function inserer(string $table, array $valeurs): int
    {
        $connues = $this->regles()[$table] ?? [];
        $colonnes = array_keys($valeurs);
        foreach ($colonnes as $c) {
            if (!isset($connues[$c])) {
                throw new \LogicException("Colonne « $c » inconnue de « $table » — l'import ne l'écrit pas.");
            }
        }
        $sql = "INSERT INTO `$table` (" . implode(', ', array_map(static fn (string $c): string => "`$c`", $colonnes))
            . ') VALUES (' . implode(', ', array_fill(0, count($colonnes), '?')) . ')';
        try {
            $this->pdo()->prepare($sql)->execute(array_values($valeurs));
        } catch (\PDOException $e) {
            // « Duplicate entry » : la clé d'unicité de la table (deux fois le même code de parcelle, le même
            // nom de participant…). Le reste est une vraie panne, qu'on ne déguise pas.
            if (($e->errorInfo[1] ?? 0) === 1062) {
                throw new \RuntimeException('Le fichier contient deux fois la même chose (« ' . $table . ' ») : '
                    . 'un code, un nom ou une clé qui devrait être unique.');
            }
            throw $e;
        }
        return (int) $this->pdo()->lastInsertId();
    }
}
