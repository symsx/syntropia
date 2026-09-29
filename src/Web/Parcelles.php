<?php

declare(strict_types=1);

namespace Syntropia\Web;

use PDO;
use Syntropia\Database;

/**
 * Les parcelles d'un site — création, et le plan (dimensions, bordures,
 * sens des planches) dont la carte du terrain aura besoin pour les y
 * poser.
 *
 * Réduit par rapport à `application/src/Web/Parcelles.php` (dépôt de
 * référence, 121 lignes) : pas de `Heritage::PROPRES_A_LA_PARCELLE`, pas
 * de `Colonnes` (introspection de la base) — l'héritage site↔parcelle
 * (sol, exposition, production…) reste hors périmètre tant que la fiche
 * d'attributs complète n'est pas construite. Whitelist explicite, dans
 * le moule de `Sites.php`. Voir CLAUDE.md § Point de reprise, chantier
 * « carte interactive du terrain », Phase 3.
 *
 * **`enregistrer()` (nb_planches/largeur_planche_m/passe_pied_m saisis à
 * la main) a disparu avec la Phase 2 du chantier « plan de parcelle »**
 * (2026-09-18) : `enregistrerPlan()` calcule maintenant `nb_planches`
 * lui-même, et `Calques::parcellesAPlacer()` lit `largeur_m` directement
 * — plus rien ne consommait `largeur_planche_m`/`passe_pied_m` une fois
 * ce calcul déplacé ici, la section « Pour la carte du terrain » de
 * `conception-parcelles.php` a donc disparu avec la méthode. Les deux
 * colonnes restent en base (non migrées, juste plus lues ni écrites) —
 * voir `chantier-plan-de-parcelle.md` § Phase 2.
 */
final class Parcelles
{
    /**
     * La largeur d'une planche et le passe-pied entre deux — fixes pour
     * l'instant, pas encore un réglage par parcelle (voir
     * `chantier-plan-de-parcelle.md` § Ce que ce plan exclut). Seule
     * source pour ces deux nombres : `conception-parcelles.js` les lit
     * d'ici (via la config JSON de la page) plutôt que de les recopier en
     * dur une seconde fois — sans quoi le calcul serveur de `nb_planches`
     * et l'aperçu du plan à l'écran pourraient un jour diverger.
     */
    public const PLANCHE_M = 1.2;
    public const PASSE_PIED_M = 0.5;

    /**
     * Une planche dont un grand côté touche une bordure plutôt qu'un
     * passe-pied ne se travaille que d'un seul côté — environ 0,60 m de
     * portée, contre 1,20 m en approchant des deux côtés. Les deux
     * planches de bord d'un rang (la première et la dernière) font donc
     * toujours cette largeur, jamais `PLANCHE_M` plein pot. Chantier
     * « plan de parcelle », Phase 3 (2026-09-18).
     */
    public const BORD_PLANCHE_M = 0.6;

    public function __construct(private ?PDO $pdo = null) {}

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::syntropia();
    }

    /** @return list<array<string, mixed>> */
    public function dUnSite(int $siteId): array
    {
        $req = $this->pdo()->prepare('SELECT * FROM parcelle WHERE site_id = ? ORDER BY code');
        $req->execute([$siteId]);
        return $req->fetchAll();
    }

    /**
     * La campagne de plantation d'une parcelle : son début — le point zéro — et sa fin, facultative.
     *
     * La plantation dense se fait dans une même période, qui s'étale sur trois saisons (fin d'automne,
     * hiver, printemps) selon les besoins des végétaux : ce qui compte est un point zéro unique, aucune
     * structurante ajoutée après coup, non un jour unique. Le début situe le projet dans son film (« il y a
     * cent jours, on est en Placenta 2 ») ; la fin dit si la campagne s'est étalée. Une date vidée s'efface ;
     * la fin ne s'écrit pas sans début, et ne le précède pas.
     *
     * Ne touche que ces deux colonnes, bornées au site : le verrou de version du plan ne s'en mêle pas.
     *
     * @return array<string, string> les erreurs, vide si la campagne est écrite
     */
    public function enregistrerPlantation(int $id, int $siteId, string $debut, string $fin): array
    {
        $erreurs = [];
        $lire = static function (string $valeur, string $cle, string $nom) use (&$erreurs): ?\DateTimeImmutable {
            $valeur = trim($valeur);
            if ($valeur === '') {
                return null;
            }
            $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $valeur);
            if ($d === false || $d->format('Y-m-d') !== $valeur) {
                $erreurs[$cle] = $nom . ' doit être une date valide.';
                return null;
            }
            if ($d > new \DateTimeImmutable('today')) {
                $erreurs[$cle] = $nom . ' ne peut pas être dans le futur : notez-la quand elle est passée.';
                return null;
            }
            if ($d < new \DateTimeImmutable('2000-01-01')) {
                $erreurs[$cle] = $nom . ' est trop ancienne pour une plantation suivie ici.';
                return null;
            }
            return $d;
        };
        $d = $lire($debut, 'date_plantation', 'La date de début');
        $f = $lire($fin, 'fin_plantation', 'La date de fin');
        if (trim($fin) !== '' && trim($debut) === '' && !isset($erreurs['fin_plantation'])) {
            $erreurs['fin_plantation'] = 'La fin de la campagne ne se note pas sans son début : notez d’abord le point zéro.';
        }
        if ($d !== null && $f !== null && $f < $d) {
            $erreurs['fin_plantation'] = 'La fin de la campagne ne peut pas précéder son début.';
        }
        if ($erreurs !== []) {
            return $erreurs;
        }
        $req = $this->pdo()->prepare('UPDATE parcelle SET date_plantation = ?, fin_plantation = ? WHERE id = ? AND site_id = ?');
        $req->execute([$d?->format('Y-m-d'), $f?->format('Y-m-d'), $id, $siteId]);
        return [];
    }

    /**
     * Les végétaux du scénario d'une parcelle, dans l'ordre de remplissage
     * (production principale, secondaire, biomasse, placentaires en dernier).
     * Le contrôle d'appartenance de la parcelle à un site se fait à l'appel :
     * `parId($id, $siteId)` d'abord.
     *
     * @return list<array<string, mixed>>
     */
    public function scenario(int $parcelleId): array
    {
        $req = $this->pdo()->prepare(
            'SELECT * FROM scenario_ligne WHERE parcelle_id = ? ORDER BY ordre, id');
        $req->execute([$parcelleId]);
        return $req->fetchAll();
    }

    /** Garde de site incluse — une parcelle ne se lit jamais hors de son site.
     * @return array<string, mixed>|null */
    public function parId(int $id, int $siteId): ?array
    {
        $req = $this->pdo()->prepare('SELECT * FROM parcelle WHERE id = ? AND site_id = ?');
        $req->execute([$id, $siteId]);
        return $req->fetch() ?: null;
    }

    /**
     * Ouvre une parcelle — code et nom seulement, le strict minimum pour
     * qu'elle existe et se distingue des autres. Tout le reste
     * s'ajoute ensuite via `enregistrer()`.
     *
     * @return array{id: ?int, erreurs: array<string, string>}
     */
    public function creer(int $siteId, string $code, string $nom, string $type): array
    {
        $code = trim($code);
        $nom = trim($nom);
        $erreurs = [];

        if ($code === '') {
            $erreurs['code'] = 'Le code est obligatoire — une lettre ou un repère court suffit.';
        } elseif (mb_strlen($code) > 32) {
            $erreurs['code'] = 'Le code est trop long (32 caractères au plus).';
        }
        if ($nom === '') {
            $erreurs['nom'] = 'Le nom est obligatoire.';
        }
        if (!in_array($type, ['perenne', 'potager'], true)) {
            $erreurs['type'] = 'Type de parcelle inconnu.';
        }
        if ($erreurs === []) {
            $req = $this->pdo()->prepare('SELECT 1 FROM parcelle WHERE site_id = ? AND code = ?');
            $req->execute([$siteId, $code]);
            if ($req->fetchColumn()) {
                $erreurs['code'] = 'Ce code est déjà pris par une autre parcelle de ce site.';
            }
        }
        if ($erreurs !== []) {
            return ['id' => null, 'erreurs' => $erreurs];
        }

        $req = $this->pdo()->prepare(
            'INSERT INTO parcelle (site_id, code, nom, type) VALUES (?, ?, ?, ?)');
        $req->execute([$siteId, $code, $nom, $type]);
        return ['id' => (int) $this->pdo()->lastInsertId(), 'erreurs' => []];
    }

    /**
     * Les quatre bordures externes, l'orientation des planches et les
     * dimensions réelles — ce que `conception-parcelles.js` dessine.
     * Chantier « plan de parcelle » (2026-09-18) : voir
     * `chantier-plan-de-parcelle.md`. Réplique partielle et assumée de la
     * référence (`src/Plan.php`/`ImplantationBordures.php`) — bordures
     * externes seulement (la clé `bordures.externe` laisse la place à
     * `bordures.interne` plus tard sans changer la forme déjà écrite),
     * pas de rives, pas d'assignation d'espèce. Le rattrapage (Phase 3)
     * est enregistré tel quel (`planches`/`passe-pieds`) — la répartition
     * elle-même est un calcul purement client, voir
     * `conception-parcelles.js`.
     *
     * **Verrou optimiste**, à l'identique de la référence
     * (`ImplantationBordures::version()`) : `SELECT … FOR UPDATE`, hash de
     * la ligne `parcelle` actuelle comparé au jeton reçu — si une autre
     * personne (ou un autre onglet) a écrit entretemps, le hash ne
     * correspond plus, et on refuse plutôt que d'écraser silencieusement.
     *
     * @param array<string, mixed> $valeurs
     * @return array{erreurs: array<string, string>, version: ?string}
     */
    public function enregistrerPlan(int $id, int $siteId, array $valeurs, string $versionRecue): array
    {
        $erreurs = [];

        $longueur = null;
        $brut = str_replace(',', '.', trim((string) ($valeurs['longueur_m'] ?? '')));
        if (!is_numeric($brut) || (float) $brut <= 0) {
            $erreurs['longueur_m'] = 'La longueur doit être un nombre positif.';
        } else {
            $longueur = round((float) $brut, 2);
        }

        $largeur = null;
        $brut = str_replace(',', '.', trim((string) ($valeurs['largeur_m'] ?? '')));
        if (!is_numeric($brut) || (float) $brut <= 0) {
            $erreurs['largeur_m'] = 'La largeur doit être un nombre positif.';
        } else {
            $largeur = round((float) $brut, 2);
        }

        $orientation = (string) ($valeurs['orientation_lignes'] ?? '');
        if (!in_array($orientation, ['nord-sud', 'est-ouest'], true)) {
            $erreurs['orientation_lignes'] = 'Le sens des planches doit être nord-sud ou est-ouest.';
        }

        // Rattrapage — Phase 3 (2026-09-18) : le reste entre le nombre
        // idéal de planches et la largeur utile réelle se répartit soit
        // sur les planches intérieures, soit sur les passe-pieds, jamais
        // les deux — voir chantier-plan-de-parcelle.md § Phase 3.
        $rattrapage = (string) ($valeurs['rattrapage'] ?? '');
        if (!in_array($rattrapage, ['planches', 'passe-pieds'], true)) {
            $erreurs['rattrapage'] = 'Le rattrapage doit se faire sur les planches ou sur les passe-pieds.';
        }
        // Un passe-pied le long des deux bords, avant la première planche et après la dernière (le porteur, 2026-09-29) —
        // `null` (clé absente) : on garde celui du plan enregistré.
        $bords = null;
        if (array_key_exists('passe_pieds_bords', $valeurs)) {
            $bords = in_array((string) $valeurs['passe_pieds_bords'], ['1', 'on', 'true'], true);
        }

        // La conduite propre à cette parcelle — « » (vide) : elle hérite du site ;
        // `null` (clé absente) : on n'y touche pas. Chantier « scénario de
        // plantation », Phase 2.
        $conduite = null;
        if (($valeurs['conduite'] ?? null) !== null) {
            $conduite = trim((string) $valeurs['conduite']);
            if ($conduite !== '' && \Syntropia\Conduite::tryFrom($conduite) === null) {
                $erreurs['conduite'] = 'La conduite est « Syntropie » ou « Jardin-forêt » — ou celle du site.';
            }
        }

        // Le stade du terrain — « » (vide) : pas dit ; `null` (clé absente) : on n'y touche pas. Chantier « saut du chat », Phase 3.
        $stade = null;
        if (($valeurs['stade'] ?? null) !== null) {
            $stade = trim((string) $valeurs['stade']);
            if ($stade !== '' && \Syntropia\Stade::tryFrom($stade) === null) {
                $erreurs['stade'] = 'Le stade du terrain est « Colonisation » ou « Accumulation » — ou pas encore dit.';
            }
        }

        // Le type de tortue — même convention : « » : pas dit ; clé absente : on n'y touche pas. Chantier « peuplement de
        // la parcelle », Phase 1 bis.
        $tortue = null;
        if (($valeurs['tortue'] ?? null) !== null) {
            $tortue = trim((string) $valeurs['tortue']);
            if ($tortue !== '' && \Syntropia\Tortue::tryFrom($tortue) === null) {
                $erreurs['tortue'] = 'Le type de tortue est « Classique », « Zèbre », « Abeille » ou « Liane » — ou pas encore dit.';
            }
        }

        // Bordure externe (bloque-herbe, coupe-vent, défense) et bordure
        // interne (fournisseurs de biomasse) — même forme, quatre côtés
        // chacun actif ou non et sa largeur (5 cm à 5 m, comme la
        // référence : une bordure ne devient jamais ni un fil ni un
        // champ). Chantier « plan de parcelle », Phase 4 (2026-09-18) pour
        // l'interne — géométrie seulement, pas d'assignation d'espèce.
        // Le nombre de lignes de chaque planche (numérotée de 1) — chantier « peuplement de la parcelle », Phase 4.
        // `null` (clé absente) : on garde ceux du plan déjà enregistré.
        $lignes = null;
        if (is_array($valeurs['lignes'] ?? null)) {
            $lignes = [];
            foreach ($valeurs['lignes'] as $numero => $n) {
                $n = trim((string) $n);
                if ((string) (int) $numero !== (string) $numero || (int) $numero < 1 || (int) $numero > 999
                    || !ctype_digit($n) || (int) $n < \Syntropia\Planches::LIGNES_MIN || (int) $n > \Syntropia\Planches::LIGNES_MAX) {
                    $erreurs['lignes'] = 'Une planche porte de ' . \Syntropia\Planches::LIGNES_MIN . ' à '
                        . \Syntropia\Planches::LIGNES_MAX . ' lignes.';
                    break;
                }
                $lignes[(string) (int) $numero] = (int) $n;
            }
        }

        // La nature des planches (tortues zèbre et abeille) — Phase 5 : la première planche ou bande, puis l'alternance ;
        // en zèbre, une planche peut être changée. `null` (clé absente) : on garde celles du plan enregistré.
        $natures = null;
        if (is_array($valeurs['natures'] ?? null)) {
            $natures = [];
            $premiere = trim((string) ($valeurs['natures']['premiere'] ?? ''));
            if ($premiere !== '') {
                if (!isset(\Syntropia\Planches::NATURES[$premiere])) {
                    $erreurs['natures'] = 'Une planche est pérenne ou annuelle.';
                } else {
                    $natures['premiere'] = $premiere;
                }
            }
            foreach (is_array($valeurs['natures']['planches'] ?? null) ? $valeurs['natures']['planches'] : [] as $numero => $nature) {
                if ((string) (int) $numero !== (string) $numero || (int) $numero < 1 || !isset(\Syntropia\Planches::NATURES[(string) $nature])) {
                    $erreurs['natures'] = 'Une planche est pérenne ou annuelle.';
                    break;
                }
                $natures['planches'][(string) (int) $numero] = (string) $nature;
            }
        }

        // La position de la ligne des lianes (tortue liane — le porteur, 2026-09-30). `null` (clé absente) : on garde celle
        // du plan enregistré.
        $liane = null;
        if (($valeurs['liane'] ?? null) !== null) {
            $liane = trim((string) $valeurs['liane']);
            if (!isset(\Syntropia\Planches::POSITIONS_LIANE[$liane])) {
                $erreurs['liane'] = 'La ligne des lianes court au cœur, côté nord ou côté est.';
            }
        }

        $bordures = $this->litBordures($valeurs['bordures'] ?? [], $erreurs, 'bordure_');
        $bordureInterne = $this->litBordures($valeurs['bordures_interne'] ?? [], $erreurs, 'bordure_interne_');

        if ($erreurs !== []) {
            return ['erreurs' => $erreurs, 'version' => null];
        }

        // Le nombre de planches — un résultat du plan, pas une saisie.
        // Chantier « plan de parcelle », Phase 2 (2026-09-18) : même
        // formule que `redessiner()` côté client (conception-
        // parcelles.js), axe choisi par le sens des planches — largeur
        // utile (longueur moins bordures ouest/est, externe et interne
        // cumulées) en nord-sud, hauteur utile (largeur moins bordures
        // nord/sud) en est-ouest.
        $totalCote = static fn (string $cote): float =>
            ($bordures[$cote]['actif'] ? $bordures[$cote]['largeur_m'] : 0.0)
            + ($bordureInterne[$cote]['actif'] ? $bordureInterne[$cote]['largeur_m'] : 0.0);
        $largeurUtile = max(0.0, $longueur - $totalCote('ouest') - $totalCote('est'));
        $hauteurUtile = max(0.0, $largeur - $totalCote('nord') - $totalCote('sud'));
        $base = $orientation === 'nord-sud' ? $largeurUtile : $hauteurUtile;

        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $req = $pdo->prepare('SELECT * FROM parcelle WHERE id = ? AND site_id = ? FOR UPDATE');
            $req->execute([$id, $siteId]);
            $ligne = $req->fetch();
            if ($ligne === false) {
                $pdo->rollBack();
                return ['erreurs' => ['version' => 'Cette parcelle est introuvable.'], 'version' => null];
            }
            if (!hash_equals(self::versionPlan($ligne), $versionRecue)) {
                $pdo->rollBack();
                return ['erreurs' => ['version' => 'Le plan a changé, rechargez la page avant de modifier.'], 'version' => null];
            }

            $ancien = json_decode((string) ($ligne['plan'] ?? ''), true);
            $bords ??= \Syntropia\Planches::passePiedsAuxBords($ancien);
            // Le compte vient de la même disposition que le dessin et les lignes (`Planches::disposition()`).
            $nbPlanches = \Syntropia\Planches::disposition($base, $rattrapage, $bords)['n'];
            if ($natures === null) {
                $natures = is_array($ancien['natures'] ?? null) ? $ancien['natures'] : [];
            }
            if (is_array($natures['planches'] ?? null)) {
                $natures['planches'] = array_filter($natures['planches'], static fn (mixed $n, mixed $numero): bool => (int) $numero <= $nbPlanches,
                    ARRAY_FILTER_USE_BOTH);
                ksort($natures['planches'], SORT_NUMERIC);
                if ($natures['planches'] === []) {
                    unset($natures['planches']);
                } else {
                    $natures['planches'] = (object) $natures['planches'];
                }
            }
            if ($lignes === null) {
                $lignes = is_array($ancien['lignes'] ?? null) ? $ancien['lignes'] : [];
            }
            // Seules les planches qui existent encore gardent leur nombre de lignes ; les autres reprendront le défaut.
            $lignes = array_filter($lignes, static fn (mixed $n, mixed $numero): bool => (int) $numero <= $nbPlanches,
                ARRAY_FILTER_USE_BOTH);
            ksort($lignes, SORT_NUMERIC);
            $contenu = ['bordures' => ['externe' => $bordures, 'interne' => $bordureInterne], 'rattrapage' => $rattrapage];
            if ($bords) {
                $contenu['passe_pieds_bords'] = true;
            }
            if ($lignes !== []) {
                $contenu['lignes'] = (object) $lignes;
            }
            if ($natures !== []) {
                $contenu['natures'] = $natures;
            }
            $liane ??= is_array($ancien['liane'] ?? null) ? ($ancien['liane']['position'] ?? null) : null;
            if ($liane !== null && isset(\Syntropia\Planches::POSITIONS_LIANE[$liane])) {
                $contenu['liane'] = ['position' => $liane];
            }
            $plan = json_encode($contenu, JSON_UNESCAPED_UNICODE);
            $req = $pdo->prepare(
                'UPDATE parcelle SET longueur_m = ?, largeur_m = ?, orientation_lignes = ?, plan = ?, nb_planches = ?'
                . ($conduite !== null ? ', conduite = ?' : '')
                . ($stade !== null ? ', stade = ?' : '')
                . ($tortue !== null ? ', tortue = ?' : '')
                . ' WHERE id = ? AND site_id = ?');
            $req->execute(array_merge(
                [$longueur, $largeur, $orientation, $plan, $nbPlanches],
                $conduite !== null ? [$conduite !== '' ? $conduite : null] : [],
                $stade !== null ? [$stade !== '' ? $stade : null] : [],
                $tortue !== null ? [$tortue !== '' ? $tortue : null] : [],
                [$id, $siteId]));

            $req = $pdo->prepare('SELECT * FROM parcelle WHERE id = ? AND site_id = ?');
            $req->execute([$id, $siteId]);
            $nouvelle = $req->fetch();
            $pdo->commit();
            return ['erreurs' => [], 'version' => self::versionPlan($nouvelle)];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Une bordure (externe ou interne) — quatre côtés, chacun actif ou
     * non et sa largeur bornée à 0,05–5 m. Les erreurs de validation
     * s'ajoutent à `$erreurs` (passé par référence, clés préfixées pour
     * distinguer externe/interne) plutôt que d'être retournées à part.
     *
     * @param array<string, mixed> $brutes
     * @param array<string, string> $erreurs
     * @return array<string, array{actif: bool, largeur_m: float}>
     */
    private function litBordures(array $brutes, array &$erreurs, string $prefixeErreur): array
    {
        $bordures = [];
        foreach (['nord', 'sud', 'ouest', 'est'] as $cote) {
            $brute = $brutes[$cote] ?? [];
            $actif = !empty($brute['actif']);
            $brutLargeur = str_replace(',', '.', trim((string) ($brute['largeur_m'] ?? '')));
            if (!is_numeric($brutLargeur)) {
                $erreurs[$prefixeErreur . $cote] = 'La largeur de la bordure ' . $cote . ' doit être un nombre.';
                continue;
            }
            $bordures[$cote] = ['actif' => $actif, 'largeur_m' => max(0.05, min(5.0, round((float) $brutLargeur, 2)))];
        }
        return $bordures;
    }

    /**
     * Le jeton du verrou optimiste — un hash de la ligne `parcelle`
     * entière. Recalculé à chaque affichage (`conception-parcelles.php`),
     * comparé à chaque écriture (`enregistrerPlan()`). Port direct de
     * `ImplantationBordures::version()` côté référence, réduit à la seule
     * ligne `parcelle` : pas de `scenario_ligne` à y mêler, cette table
     * n'existe pas encore ici.
     *
     * @param array<string, mixed> $parcelle
     */
    public static function versionPlan(array $parcelle): string
    {
        return hash('sha256', json_encode($parcelle, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}
