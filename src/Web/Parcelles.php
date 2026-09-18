<?php

declare(strict_types=1);

namespace Syntropia\Web;

use PDO;
use Syntropia\Database;

/**
 * Les parcelles d'un site — création, et les trois mesures dont la
 * carte du terrain aura besoin pour les y poser.
 *
 * Réduit par rapport à `application/src/Web/Parcelles.php` (dépôt de
 * référence, 121 lignes) : pas de `Heritage::PROPRES_A_LA_PARCELLE`, pas
 * de `Colonnes` (introspection de la base) — l'héritage site↔parcelle
 * (sol, exposition, production…) reste hors périmètre tant que la fiche
 * d'attributs complète n'est pas construite. Whitelist explicite, dans
 * le moule de `Sites.php`. Voir CLAUDE.md § Point de reprise, chantier
 * « carte interactive du terrain », Phase 3.
 */
final class Parcelles
{
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
     * Les trois mesures dont `Calques::parcellesAPlacer()` (chantier
     * « carte interactive », Phase 4) a besoin pour calculer la largeur de
     * la parcelle et proposer l'outil de placement par rectangle.
     *
     * `longueur_m` n'est plus ici depuis le chantier « plan de parcelle »
     * (2026-09-18) — c'est maintenant `enregistrerPlan()` qui l'écrit, en
     * même temps que `largeur_m` : les deux sont la longueur et la largeur
     * *réelles* de la parcelle, celles que dessine le plan juste au-dessus
     * dans le panneau, pas une mesure propre à cette section.
     *
     * @param array<string, mixed> $valeurs
     * @return array<string, string> erreurs, vide si enregistré
     */
    public function enregistrer(int $id, int $siteId, array $valeurs): array
    {
        $erreurs = [];

        $nbPlanches = null;
        $brut = trim((string) ($valeurs['nb_planches'] ?? ''));
        if ($brut !== '') {
            if (!ctype_digit($brut) || (int) $brut < 1 || (int) $brut > 255) {
                $erreurs['nb_planches'] = 'Le nombre de planches doit être un entier entre 1 et 255.';
            } else {
                $nbPlanches = (int) $brut;
            }
        }

        $largeurPlanche = null;
        // La virgule française passe : les champs (data-carte mis à part)
        // se saisissent comme le prototype SVG voisin le fait déjà en JS
        // (conception-parcelles.js, fonction `nombre()`) — même convention.
        $brut = str_replace(',', '.', trim((string) ($valeurs['largeur_planche_m'] ?? '')));
        if ($brut !== '') {
            if (!is_numeric($brut) || (float) $brut <= 0) {
                $erreurs['largeur_planche_m'] = 'La largeur de planche doit être un nombre positif.';
            } else {
                $largeurPlanche = round((float) $brut, 2);
            }
        }

        $passePied = null;
        $brut = str_replace(',', '.', trim((string) ($valeurs['passe_pied_m'] ?? '')));
        if ($brut !== '') {
            if (!is_numeric($brut) || (float) $brut < 0) {
                $erreurs['passe_pied_m'] = 'Le passe-pied doit être un nombre positif ou nul.';
            } else {
                $passePied = round((float) $brut, 2);
            }
        }

        if ($erreurs !== []) {
            return $erreurs;
        }

        $req = $this->pdo()->prepare(
            'UPDATE parcelle SET nb_planches = ?, largeur_planche_m = ?, passe_pied_m = ?
             WHERE id = ? AND site_id = ?');
        $req->execute([$nbPlanches, $largeurPlanche, $passePied, $id, $siteId]);
        return [];
    }

    /**
     * Les quatre bordures externes, l'orientation des planches et les
     * dimensions réelles — ce que `conception-parcelles.js` dessine.
     * Chantier « plan de parcelle », Phase 1 (2026-09-18) : voir
     * `chantier-plan-de-parcelle.md`. Réplique partielle et assumée de la
     * référence (`src/Plan.php`/`ImplantationBordures.php`) — bordures
     * externes seulement (la clé `bordures.externe` laisse la place à
     * `bordures.interne` plus tard sans changer la forme déjà écrite),
     * pas de rives, pas de rattrapage, pas d'assignation d'espèce.
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

            $plan = json_encode(['bordures' => ['externe' => $bordures]], JSON_UNESCAPED_UNICODE);
            $req = $pdo->prepare(
                'UPDATE parcelle SET longueur_m = ?, largeur_m = ?, orientation_lignes = ?, plan = ?
                 WHERE id = ? AND site_id = ?');
            $req->execute([$longueur, $largeur, $orientation, $plan, $id, $siteId]);

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
