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
     * « carte interactive », Phase 4 — pas encore construit) aura besoin
     * pour calculer la largeur de la parcelle et proposer l'outil de
     * placement par rectangle en Phase 5.
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

        // La longueur d'une planche — avec largeur_planche_m/nb_planches/
        // passe_pied_m, la quatrième mesure dont `Calques::parcellesAPlacer()`
        // a besoin pour proposer l'outil rectangle (Phase 4/5 de la carte) :
        // sans elle, `largeur_m` se calcule mais `longueur_m` reste vide, et
        // la parcelle ne se pose jamais que tracée à la main. Absente du
        // premier passage de cette méthode (2026-09-16) — trouvé en testant
        // la Phase 5, ajoutée ici plutôt que laissée de côté.
        $longueur = null;
        $brut = str_replace(',', '.', trim((string) ($valeurs['longueur_m'] ?? '')));
        if ($brut !== '') {
            if (!is_numeric($brut) || (float) $brut <= 0) {
                $erreurs['longueur_m'] = 'La longueur doit être un nombre positif.';
            } else {
                $longueur = round((float) $brut, 2);
            }
        }

        if ($erreurs !== []) {
            return $erreurs;
        }

        $req = $this->pdo()->prepare(
            'UPDATE parcelle SET nb_planches = ?, largeur_planche_m = ?, passe_pied_m = ?, longueur_m = ?
             WHERE id = ? AND site_id = ?');
        $req->execute([$nbPlanches, $largeurPlanche, $passePied, $longueur, $id, $siteId]);
        return [];
    }
}
