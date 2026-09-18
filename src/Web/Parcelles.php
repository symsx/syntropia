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

        // Bordure externe — quatre côtés, chacun actif ou non et sa
        // largeur (5 cm à 5 m, comme la référence : une bordure ne
        // devient jamais ni un fil ni un champ).
        $bordures = [];
        foreach (['nord', 'sud', 'ouest', 'est'] as $cote) {
            $brute = $valeurs['bordures'][$cote] ?? [];
            $actif = !empty($brute['actif']);
            $brutLargeur = str_replace(',', '.', trim((string) ($brute['largeur_m'] ?? '')));
            if (!is_numeric($brutLargeur)) {
                $erreurs['bordure_' . $cote] = 'La largeur de la bordure ' . $cote . ' doit être un nombre.';
                continue;
            }
            $bordures[$cote] = ['actif' => $actif, 'largeur_m' => max(0.05, min(5.0, round((float) $brutLargeur, 2)))];
        }

        if ($erreurs !== []) {
            return ['erreurs' => $erreurs, 'version' => null];
        }

        // Le nombre de planches — un résultat du plan, pas une saisie.
        // Chantier « plan de parcelle », Phase 2 (2026-09-18) : même
        // formule que `redessiner()` côté client (conception-
        // parcelles.js), axe choisi par le sens des planches — largeur
        // utile (longueur moins bordures ouest/est) en nord-sud, hauteur
        // utile (largeur moins bordures nord/sud) en est-ouest.
        $largeurUtile = max(0.0, $longueur
            - ($bordures['ouest']['actif'] ? $bordures['ouest']['largeur_m'] : 0.0)
            - ($bordures['est']['actif'] ? $bordures['est']['largeur_m'] : 0.0));
        $hauteurUtile = max(0.0, $largeur
            - ($bordures['nord']['actif'] ? $bordures['nord']['largeur_m'] : 0.0)
            - ($bordures['sud']['actif'] ? $bordures['sud']['largeur_m'] : 0.0));
        $base = $orientation === 'nord-sud' ? $largeurUtile : $hauteurUtile;
        $pas = self::PLANCHE_M + self::PASSE_PIED_M;
        $nbPlanches = $base > self::PLANCHE_M
            ? max(1, (int) floor(($base - self::PLANCHE_M) / $pas) + 1)
            : ($base > 0 ? 1 : 0);

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

            $plan = json_encode(['bordures' => ['externe' => $bordures], 'rattrapage' => $rattrapage], JSON_UNESCAPED_UNICODE);
            $req = $pdo->prepare(
                'UPDATE parcelle SET longueur_m = ?, largeur_m = ?, orientation_lignes = ?, plan = ?, nb_planches = ?
                 WHERE id = ? AND site_id = ?');
            $req->execute([$longueur, $largeur, $orientation, $plan, $nbPlanches, $id, $siteId]);

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
