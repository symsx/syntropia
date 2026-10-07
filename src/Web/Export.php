<?php

declare(strict_types=1);

namespace Syntropia\Web;

use PDO;
use Syntropia\Database;
use Syntropia\Version;

/**
 * L'export d'un projet — un fichier JSON daté qui porte tout ce que le projet contient.
 *
 * Chantier `chantier-export-import-projet.md`, Phase 1. **Lecture seule** : rien ici ne
 * modifie la base, et rien ne sort de ce que le porteur peut déjà voir.
 *
 * **Le fichier suit le schéma, il ne le recopie pas à la main.** Chaque table du projet est
 * lue en entier (`SELECT *`) ; seules sont retirées les colonnes qu'un export ne doit
 * jamais porter (les identifiants de base, les comptes, les codes d'accès), et les clés
 * étrangères sont remplacées par des références de fichier. Une colonne ajoutée plus tard au
 * schéma sort donc **sans qu'on y touche** — c'est ce qui évite qu'une sauvegarde perde des
 * données en silence, le risque que le chantier avait nommé.
 *
 * **Ce que le fichier ne contient jamais** : aucun compte (ni identifiant, ni courriel, ni
 * empreinte), aucun accès (`membre_site`), aucune invitation. Les participants sortent avec
 * leur nom seulement. `saisi_par` désigne un compte, dans les tables qui l'ont : il est retiré
 * partout, pour ne pas faire sortir un identifiant de compte par la petite porte.
 *
 * **Un fichier qu'on ne pourrait pas réimporter n'est pas produit** : au-delà de `MAX_OCTETS`,
 * `encoder()` refuse et dit quelle part est trop lourde.
 *
 * **Le fichier dit d'où il vient et prouve qu'il n'a pas changé.** Il porte ses trois versions (voir
 * `Version` : la forme du fichier, celle des données, le numéro de l'atelier) et un contrôle d'intégrité
 * (SHA-256 du contenu). `controler()` est la porte d'entrée de l'import : elle refuse un fichier modifié,
 * puis un fichier dont le schéma n'est pas rechargeable ici. **Ce que la somme de contrôle garantit, et ce
 * qu'elle ne garantit pas** : elle repère toute modification, y compris celle d'une seule lettre — mais
 * quelqu'un qui connaît le format peut modifier le fichier *et* recalculer la somme. Elle protège d'un
 * fichier abîmé ou retouché sans précaution, non d'une falsification délibérée ; et elle ne dit pas qu'un
 * contenu est *valide* : l'import valide chaque valeur de toute façon.
 */
final class Export
{
    /** Le plafond d'un fichier — celui que l'import acceptera (1 Mo). */
    public const MAX_OCTETS = 1_048_576;

    /**
     * Les colonnes qui sont, dans la base, du JSON stocké en texte : le fichier les porte
     * décodées (lisibles, sans un format dans un format).
     */
    private const COLONNES_JSON = ['plan', 'details', 'geometrie', 'proprietes'];

    /** Ce qui ne sort jamais, quelle que soit la table. */
    public const JAMAIS = ['id', 'site_id', 'parcelle_id', 'celebration_id', 'participant_id',
        'compte_id', 'saisi_par'];

    /** Ce que porte chaque parcelle. */
    private const ENFANTS_PARCELLE = [
        'scenario' => 'scenario_ligne',
        'succession' => 'succession_ligne',
        'releves_sol' => 'releve_sol',
        'interventions' => 'intervention',
        'constats' => 'constat',
        'lignes' => 'ligne_plantation',
        'calendriers' => 'ligne_calendrier',
        'perturbations_prevues' => 'perturbation_prevue',
        'palette' => 'palette',
    ];

    /**
     * Ce que le site ne dit pas : son code (recalculé à l'arrivée), l'accès par code d'invitation, et la
     * trace d'un import (`origine`) — c'est l'import qui l'écrit, un projet ne la transmet pas.
     */
    public const SITE_JAMAIS = ['code', 'code_invitation', 'role_invitation', 'origine'];

    /** @var array<string, array<string, string>>|null table → colonne → type de données */
    private ?array $types = null;

    public function __construct(private ?PDO $pdo = null) {}

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::syntropia();
    }

    /**
     * Le type de chaque colonne de chaque table, lu dans le schéma réel — pour rendre un
     * nombre en nombre, et pour dater la forme de la base dans le fichier.
     *
     * @return array<string, array<string, string>>
     */
    private function types(): array
    {
        if ($this->types !== null) {
            return $this->types;
        }
        // Des alias : MySQL 8 rend les noms d'`information_schema` en majuscules, MariaDB en minuscules.
        $req = $this->pdo()->query(
            'SELECT table_name AS t, column_name AS c, data_type AS d
               FROM information_schema.columns WHERE table_schema = DATABASE()
              ORDER BY table_name, ordinal_position');
        $types = [];
        foreach ($req->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $types[strtolower((string) $r['t'])][(string) $r['c']] = strtolower((string) $r['d']);
        }
        return $this->types = $types;
    }

    /** Une valeur de la base, avec le type qu'elle a dans le schéma. */
    private function valeur(string $table, string $colonne, mixed $v): mixed
    {
        if ($v === null) {
            return null;
        }
        if (in_array($colonne, self::COLONNES_JSON, true) && is_string($v) && $v !== '') {
            $json = json_decode($v, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $json;
            }
            return $v;               // un texte qui n'est pas du JSON reste tel quel
        }
        return match ($this->types()[$table][$colonne] ?? 'varchar') {
            'tinyint', 'smallint', 'mediumint', 'int', 'bigint' => (int) $v,
            'decimal', 'float', 'double' => (float) $v,
            default => (string) $v,
        };
    }

    /**
     * Une ligne, prête pour le fichier : sans ce qui ne sort jamais, les valeurs typées.
     *
     * @param array<string, mixed> $ligne
     * @param list<string> $en_plus colonnes de plus à retirer
     * @return array<string, mixed>
     */
    private function ligne(string $table, array $ligne, array $en_plus = []): array
    {
        $out = [];
        foreach ($ligne as $colonne => $v) {
            if (in_array($colonne, self::JAMAIS, true) || in_array($colonne, $en_plus, true)) {
                continue;
            }
            $out[$colonne] = $this->valeur($table, $colonne, $v);
        }
        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function lire(string $sql, array $params): array
    {
        $req = $this->pdo()->prepare($sql);
        $req->execute($params);
        return $req->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Le document : un tableau prêt à être encodé. `null` si le projet n'existe pas.
     *
     * Les identifiants de base n'apparaissent nulle part : les participants et les parcelles sont
     * désignés par une référence de fichier (`p1`, `a1`…), qui n'a de sens
     * que dans ce fichier.
     *
     * @return array<string, mixed>|null
     */
    public function construire(int $siteId, ?\DateTimeImmutable $maintenant = null): ?array
    {
        $site = $this->lire('SELECT * FROM site WHERE id = ?', [$siteId])[0] ?? null;
        if ($site === null) {
            return null;
        }
        $maintenant ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $doc = [
            'format' => 'syntropia-projet',
            'exporte_le' => $maintenant->format('Y-m-d\TH:i:s\Z'),
            // Les trois versions : la forme du fichier, celle des données (lue dans la base, qui dit ce
            // qu'elle a réellement reçu), le numéro de l'atelier. Voir `Version`.
            'versions' => ['format' => Version::FORMAT, 'schema' => $this->schemaDeLaBase(), 'application' => Version::APPLICATION],
            'application' => ['nom' => 'Syntropia', 'commit' => $this->commit(), 'empreinte_schema' => $this->empreinteSchema()],
            'projet' => $this->ligne('site', $site, self::SITE_JAMAIS),
        ];

        // Les participants : leur nom, leur rôle dans le projet — jamais leur compte.
        $refParticipant = [];
        $doc['participants'] = [];
        foreach ($this->lire('SELECT * FROM participant WHERE site_id = ? ORDER BY id', [$siteId]) as $p) {
            $ref = 'p' . (count($refParticipant) + 1);
            $refParticipant[(int) $p['id']] = $ref;
            $doc['participants'][] = ['ref' => $ref] + $this->ligne('participant', $p);
        }

        $parSite = static fn (string $table): string => "SELECT * FROM $table WHERE site_id = ? ORDER BY id";

        $doc['reponses'] = [];
        foreach ($this->lire($parSite('reponse'), [$siteId]) as $r) {
            $doc['reponses'][] = ['participant' => $refParticipant[(int) $r['participant_id']] ?? null]
                + $this->ligne('reponse', $r);
        }
        foreach (['syntheses' => 'synthese', 'ressources' => 'ressource', 'gisements' => 'gisement', 'commandes' => 'commande'] as $partie => $table) {
            $doc[$partie] = array_map(fn (array $l): array => $this->ligne($table, $l), $this->lire($parSite($table), [$siteId]));
        }
        // Les surcharges d'espèces de ce site : le cadre partagé (site_id = 0) est déjà chez tout le monde.
        $doc['especes_locales'] = array_map(fn (array $l): array => $this->ligne('espece', $l),
            $this->lire('SELECT * FROM espece WHERE site_id = ? AND site_id <> 0 ORDER BY id', [$siteId]));

        // Les parcelles, chacune avec ce qu'elle porte.
        $refParcelle = [];
        $doc['parcelles'] = [];
        foreach ($this->lire('SELECT * FROM parcelle WHERE site_id = ? ORDER BY id', [$siteId]) as $p) {
            $ref = 'a' . (count($refParcelle) + 1);
            $refParcelle[(int) $p['id']] = $ref;
            $entree = ['ref' => $ref] + $this->ligne('parcelle', $p);
            foreach (self::ENFANTS_PARCELLE as $partie => $table) {
                $ordre = match (true) {
                    in_array($table, ['releve_sol', 'intervention', 'constat'], true) => '`date`, id',
                    $table === 'ligne_plantation' => 'emplacement, rang',
                    $table === 'ligne_calendrier' => 'emplacement',
                    $table === 'perturbation_prevue' => 'emplacement, date_prevue, id',
                    $table === 'palette' => 'place, id',
                    default => 'id',
                };
                $entree[$partie] = array_map(fn (array $l): array => $this->ligne($table, $l),
                    $this->lire("SELECT * FROM $table WHERE parcelle_id = ? ORDER BY $ordre", [(int) $p['id']]));
            }
            // Les célébrations, chacune avec la reconnaissance de chaque participant.
            $entree['celebrations'] = [];
            foreach ($this->lire('SELECT * FROM celebration WHERE parcelle_id = ? ORDER BY id', [(int) $p['id']]) as $c) {
                $reconnaissances = [];
                foreach ($this->lire('SELECT * FROM reconnaissance WHERE celebration_id = ? ORDER BY id', [(int) $c['id']]) as $r) {
                    $reconnaissances[] = ['participant' => $refParticipant[(int) $r['participant_id']] ?? null]
                        + $this->ligne('reconnaissance', $r);
                }
                $entree['celebrations'][] = $this->ligne('celebration', $c) + ['reconnaissances' => $reconnaissances];
            }
            $doc['parcelles'][] = $entree;
        }

        // Les tracés de la carte : ceux qui appartiennent à une parcelle la désignent par sa référence.
        $doc['calques'] = [];
        foreach ($this->lire($parSite('calque_element'), [$siteId]) as $c) {
            $doc['calques'][] = ['parcelle' => $c['parcelle_id'] !== null ? ($refParcelle[(int) $c['parcelle_id']] ?? null) : null]
                + $this->ligne('calque_element', $c);
        }

        // Le contrôle d'intégrité, en dernier : il porte sur tout le reste.
        $doc['integrite'] = ['algorithme' => 'sha256', 'valeur' => self::empreinte($doc)];
        return $doc;
    }

    /**
     * La version du schéma de CETTE base, telle qu'elle l'a reçue (`atelier_version`). `null` si la table
     * n'existe pas encore : la base n'a pas été rejouée depuis l'arrivée du versionnement.
     */
    public function schemaDeLaBase(): ?int
    {
        try {
            $v = $this->lire("SELECT valeur FROM atelier_version WHERE cle = 'schema'", [])[0]['valeur'] ?? null;
        } catch (\PDOException) {
            return null;
        }
        return $v !== null && ctype_digit((string) $v) ? (int) $v : null;
    }

    /**
     * Le code et la base s'accordent-ils sur la forme des données ? Sinon, un message — et on n'exporte
     * ni n'importe : le fichier décrirait mal ce qu'il porte. Le cas qui arrive : le code est déployé,
     * la base pas encore rejouée (`bin/deployer.py base`), ou l'inverse.
     */
    public static function accordCodeEtBase(?int $schemaDeLaBase): ?string
    {
        if ($schemaDeLaBase === null) {
            return 'La base de cet atelier n’a pas encore sa version de schéma : rejouez le schéma (bin/deployer.py base, ou bin/dev.py init en local).';
        }
        if ($schemaDeLaBase !== Version::SCHEMA) {
            return 'La base est au schéma ' . $schemaDeLaBase . ' et le code attend le schéma ' . Version::SCHEMA
                . ' : déployez la base et le code ensemble.';
        }
        return null;
    }

    /**
     * La somme de contrôle du contenu d'un document — sans son propre champ `integrite`. Calculée sur
     * l'écriture compacte de la structure, non sur les octets du fichier : un fichier rouvert et
     * réenregistré par un éditeur (espaces, retours à la ligne) n'a pas changé, un chiffre modifié, si.
     *
     * @param array<string, mixed> $doc
     */
    public static function empreinte(array $doc): string
    {
        unset($doc['integrite']);
        // Les décimaux s'écrivent au plus court qui se relit à l'identique : le même partout, quelle que
        // soit la configuration de PHP.
        $precision = ini_set('serialize_precision', '-1');
        try {
            $compact = json_encode($doc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
        } finally {
            if ($precision !== false) {
                ini_set('serialize_precision', $precision);
            }
        }
        return hash('sha256', $compact);
    }

    /**
     * La porte d'entrée de l'import : ce fichier peut-il être rechargé ici ? Ne lit rien dans la base et
     * n'écrit rien — `$schemaCible` est la version de schéma de la base qui recevrait le fichier.
     *
     * L'ordre compte : d'abord que c'est bien un export, puis **qu'il n'a pas été modifié** (avant toute
     * lecture de ses versions : un fichier retouché ne doit pas se faire passer pour « trop ancien »), puis
     * que ses versions sont compatibles.
     *
     * @return array{ok: bool, erreurs: list<string>, doc: ?array<string, mixed>, versions: ?array<string, mixed>}
     */
    public static function controler(string $json, int $schemaCible = Version::SCHEMA): array
    {
        $refus = static fn (string $message, ?array $versions = null): array =>
            ['ok' => false, 'erreurs' => [$message], 'doc' => null, 'versions' => $versions];

        if (strlen($json) > self::MAX_OCTETS) {
            return $refus('Ce fichier dépasse 1 Mo : ce n’est pas un export de Syntropia.');
        }
        try {
            $doc = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $refus('Ce fichier n’est pas un JSON lisible : il est peut-être tronqué ou abîmé.');
        }
        if (!is_array($doc) || ($doc['format'] ?? null) !== 'syntropia-projet') {
            return $refus('Ce fichier n’est pas un export de projet Syntropia.');
        }

        // Un fichier modifié ne se charge pas.
        $integrite = $doc['integrite'] ?? null;
        if (!is_array($integrite) || ($integrite['algorithme'] ?? null) !== 'sha256' || !is_string($integrite['valeur'] ?? null)) {
            return $refus('Ce fichier n’a pas de contrôle d’intégrité : il ne peut pas être vérifié, donc pas chargé.');
        }
        if (!hash_equals(self::empreinte($doc), $integrite['valeur'])) {
            return $refus('Ce fichier a été modifié depuis son export : il ne se charge pas. '
                . 'Exportez de nouveau le projet depuis l’atelier d’origine.');
        }

        // Les versions.
        $v = $doc['versions'] ?? null;
        if (!is_array($v) || !is_int($v['format'] ?? null) || !is_int($v['schema'] ?? null)) {
            return $refus('Ce fichier ne dit pas de quelles versions il vient : il ne peut pas être chargé.');
        }
        if ($v['format'] > Version::FORMAT) {
            return $refus('Ce fichier a été écrit dans un format plus récent (' . $v['format'] . ') que celui que cet atelier '
                . 'sait lire (' . Version::FORMAT . ') : mettez d’abord cet atelier à jour.', $v);
        }
        if ($v['format'] < Version::FORMAT) {
            return $refus('Ce fichier est écrit dans un ancien format (' . $v['format'] . ') que cet atelier ne sait plus lire.', $v);
        }
        if ($v['schema'] > $schemaCible) {
            return $refus('Ce fichier vient d’un atelier plus récent (schéma ' . $v['schema'] . ') que celui-ci (schéma '
                . $schemaCible . ') : mettez d’abord cet atelier à jour.', $v);
        }
        if ($v['schema'] < Version::SCHEMA_IMPORTABLE_DEPUIS) {
            return $refus('Ce fichier vient d’un schéma trop ancien (' . $v['schema'] . ') pour se charger tel quel. '
                . 'Seuls les fichiers de schéma ' . Version::SCHEMA_IMPORTABLE_DEPUIS . ' et plus se chargent.', $v);
        }
        return ['ok' => true, 'erreurs' => [], 'doc' => $doc, 'versions' => $v];
    }

    /**
     * Le document encodé, ou un refus s'il dépasse le plafond.
     *
     * @param array<string, mixed> $doc
     * @return array{ok: true, json: string, octets: int}
     *       | array{ok: false, octets: int, parts: array<string, int>}
     */
    public function encoder(array $doc): array
    {
        $json = self::encode($doc);
        if (strlen($json) <= self::MAX_OCTETS) {
            return ['ok' => true, 'json' => $json, 'octets' => strlen($json)];
        }
        // Ce qui pèse : la taille de chaque partie, de la plus lourde à la plus légère.
        $parts = [];
        foreach ($doc as $cle => $valeur) {
            $parts[$cle] = strlen(self::encode($valeur));
        }
        arsort($parts);
        return ['ok' => false, 'octets' => strlen($json), 'parts' => $parts];
    }

    /** La façon d'écrire un fichier d'export — la même pour mesurer et pour produire. */
    private static function encode(mixed $valeur): string
    {
        $json = json_encode($valeur, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
        // Une liste de nombres (un point de la carte : [latitude, longitude]) tient sur une ligne : le
        // fichier reste lisible et pèse le quart de ce qu'il pèserait un nombre par ligne. Sans risque
        // pour un texte : un retour à la ligne y est toujours écrit « \n », jamais un vrai saut de ligne,
        // et ce motif n'existe qu'entre deux sauts de ligne réels — c'est-à-dire hors de tout texte.
        $json = (string) preg_replace_callback('/\[\n\s*([\d.eE+\-,\s]+?)\n\s*\]/',
            static fn (array $m): string => '[' . preg_replace('/\s+/', ' ', $m[1]) . ']', $json);
        return $json . "\n";
    }

    /**
     * Ce que le fichier contient, pour l'annoncer avant de le produire.
     *
     * @param array<string, mixed> $doc
     * @return array<string, int>
     */
    public static function inventaire(array $doc): array
    {
        $n = static fn (string $cle): int => count($doc[$cle] ?? []);
        $parcelles = $doc['parcelles'] ?? [];
        $somme = static function (string $cle) use ($parcelles): int {
            return array_sum(array_map(static fn (array $p): int => count($p[$cle] ?? []), $parcelles));
        };
        return [
            'participants' => $n('participants'),
            'reponses' => $n('reponses'),
            'parcelles' => $n('parcelles'),
            'vegetaux' => $somme('scenario') + $somme('succession'),
            'traces' => $n('calques'),
            'releves_sol' => $somme('releves_sol'),
            'interventions' => $somme('interventions'),
            'constats' => $somme('constats'),
            'celebrations' => $somme('celebrations'),
            'ressources' => $n('ressources'),
            'gisements' => $n('gisements'),
            'commandes' => $n('commandes'),
            'especes_locales' => $n('especes_locales'),
        ];
    }

    /** `nom-du-projet_2026-09-20.syntropia.json` — le nom, sans accent ni caractère à risque, puis la date. */
    public static function nomDeFichier(string $nomProjet, string $exporteLe): string
    {
        $sans = strtr(mb_strtolower($nomProjet), [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'î' => 'i', 'ï' => 'i',
            'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'œ' => 'oe', 'æ' => 'ae', 'ÿ' => 'y',
        ]);
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', $sans), '-');
        return ($slug !== '' ? mb_substr($slug, 0, 60) : 'projet') . '_' . substr($exporteLe, 0, 10) . '.syntropia.json';
    }

    /**
     * L'empreinte de la forme de la base au moment de l'export : les tables du projet, leurs
     * colonnes et leurs types. Pour comprendre plus tard pourquoi un fichier ne s'importe plus.
     */
    private function empreinteSchema(): string
    {
        $tables = ['site', 'participant', 'reponse', 'synthese', 'ressource', 'gisement', 'commande', 'espece',
            'parcelle', 'scenario_ligne', 'succession_ligne', 'releve_sol', 'intervention', 'constat', 'ligne_plantation', 'ligne_calendrier', 'perturbation_prevue', 'palette', 'celebration',
            'reconnaissance', 'calque_element'];
        $lignes = [];
        foreach ($tables as $t) {
            foreach ($this->types()[$t] ?? [] as $colonne => $type) {
                $lignes[] = "$t.$colonne:$type";
            }
        }
        return substr(sha1(implode("\n", $lignes)), 0, 12);
    }

    /**
     * Le commit du code, quand on tourne depuis un dépôt (poste de développement) ; `null` en
     * production, où `.git` n'est pas déployé.
     */
    private function commit(): ?string
    {
        $git = dirname(__DIR__, 2) . '/.git';
        $tete = @file_get_contents($git . '/HEAD');
        if ($tete === false) {
            return null;
        }
        $tete = trim($tete);
        if (str_starts_with($tete, 'ref: ')) {
            $tete = trim((string) @file_get_contents($git . '/' . substr($tete, 5)));
        }
        return preg_match('/^[0-9a-f]{40}$/', $tete) === 1 ? substr($tete, 0, 12) : null;
    }
}
