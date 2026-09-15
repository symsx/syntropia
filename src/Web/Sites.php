<?php

declare(strict_types=1);

namespace Syntropia\Web;

use PDO;
use Syntropia\Database;

/**
 * Le site — un seul par compte, pour l'instant.
 *
 * Inspiré d'`application/src/Web/Sites.php` (dépôt de référence), mais
 * réduit à ce que `directives.txt` demande aujourd'hui : « un seul
 * projet par utilisateur avec un site et *n* parcelles ». Pas de liste
 * de sites, pas de code d'invitation à un site précis — ces pièces
 * reviendront avec `Web/Invitations.php` (rattachement à un site) et
 * `compte.php`, le jour où un compte pourra porter plus d'un projet ou
 * en rejoindre un autre que le sien.
 *
 * `membre_site` (l'accès) et `participant` (une personne dont les
 * réponses comptent) restent deux tables distinctes, comme dans le
 * dépôt de référence : la première ouvre les données, la seconde nomme
 * quelqu'un. `creer()` pose les deux en une transaction, avec le même
 * compte des deux côtés — c'est tout ce qu'un seul porteur, sans
 * personne d'autre encore invitée, demande.
 */
final class Sites
{
    public function __construct(private ?PDO $pdo = null) {}

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::syntropia();
    }

    // ------------------------------------------------------------------
    // Lire
    // ------------------------------------------------------------------

    /**
     * Le site de ce compte, ou `null` s'il n'en a pas encore ouvert.
     *
     * Un seul, pour l'instant : le premier trouvé si jamais il y en
     * avait plusieurs (accès donné à la main en base).
     *
     * @return array<string, mixed>|null
     */
    public function pourCompte(int $compteId): ?array
    {
        $req = $this->pdo()->prepare(
            'SELECT s.* FROM site s
               JOIN membre_site m ON m.site_id = s.id
              WHERE m.compte_id = ?
              ORDER BY s.id
              LIMIT 1');
        $req->execute([$compteId]);
        return $req->fetch() ?: null;
    }

    /** @return array<string, mixed>|null */
    public function parId(int $id): ?array
    {
        $req = $this->pdo()->prepare('SELECT * FROM site WHERE id = ?');
        $req->execute([$id]);
        return $req->fetch() ?: null;
    }

    public function roleSur(int $siteId, int $compteId): ?string
    {
        $req = $this->pdo()->prepare(
            'SELECT role FROM membre_site WHERE site_id = ? AND compte_id = ?');
        $req->execute([$siteId, $compteId]);
        $role = $req->fetchColumn();
        return is_string($role) ? $role : null;
    }

    public function peutEcrireSur(int $siteId, int $compteId): bool
    {
        return in_array($this->roleSur($siteId, $compteId), ['porteur', 'contributeur'], true);
    }

    /**
     * Le participant qui porte les réponses de ce compte sur ce site.
     *
     * `creer()` en pose toujours un — cette méthode ne devrait donc
     * jamais avoir à en créer un elle-même ; elle le fait quand même,
     * plutôt que de renvoyer `null` et fermer une page sur une
     * incohérence qu'elle peut réparer sans risque.
     */
    public function participantDuCompte(int $siteId, int $compteId, string $nom = ''): int
    {
        $req = $this->pdo()->prepare(
            'SELECT id FROM participant WHERE site_id = ? AND compte_id = ?');
        $req->execute([$siteId, $compteId]);
        $id = $req->fetchColumn();
        if (is_int($id) || is_string($id)) {
            return (int) $id;
        }
        $req = $this->pdo()->prepare(
            'INSERT INTO participant (site_id, nom, compte_id) VALUES (?, ?, ?)');
        $req->execute([$siteId, $nom !== '' ? $nom : 'Porteur', $compteId]);
        return (int) $this->pdo()->lastInsertId();
    }

    // ------------------------------------------------------------------
    // Écrire
    // ------------------------------------------------------------------

    /**
     * Ouvre un site : son créateur en devient le porteur, et le premier
     * participant dont les réponses compteront.
     *
     * Trois écritures dans une transaction : un site sans porteur, ou
     * sans personne pour répondre, serait un site que plus personne ne
     * peut faire avancer.
     */
    public function creer(string $nom, int $compteId, string $nomParticipant): int
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $req = $pdo->prepare('INSERT INTO site (code, nom) VALUES (?, ?)');
            $req->execute([$this->codeLibre($nom), $nom]);
            $id = (int) $pdo->lastInsertId();

            $pdo->prepare(
                "INSERT INTO membre_site (site_id, compte_id, role) VALUES (?, ?, 'porteur')")
                ->execute([$id, $compteId]);

            $pdo->prepare(
                'INSERT INTO participant (site_id, nom, compte_id) VALUES (?, ?, ?)')
                ->execute([$id, $nomParticipant !== '' ? $nomParticipant : 'Porteur', $compteId]);

            $pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Le point du site — `latitude`/`longitude`, colonnes propres de
     * `site`, distinctes de `reponse` (texte libre, une question ⇒ un
     * champ). Les deux vont ensemble : aucune n'a de sens seule sur une
     * carte. Whitelist explicite, dans le style de `creer()` ci-dessus —
     * pas de `Colonnes::normaliser()`/`Referentiel` (dépôt de référence),
     * absents ici et hors périmètre tant qu'un seul champ est concerné.
     *
     * @return array<string, string> erreurs, vide si enregistré
     */
    public function enregistrerParametres(int $siteId, string $latitude, string $longitude): array
    {
        $latBrut = trim($latitude);
        $lonBrut = trim($longitude);

        if ($latBrut === '' && $lonBrut === '') {
            $this->pdo()->prepare('UPDATE site SET latitude = NULL, longitude = NULL WHERE id = ?')
                ->execute([$siteId]);
            return [];
        }
        if ($latBrut === '' || $lonBrut === '') {
            return ['coordonnees' => 'La latitude et la longitude vont ensemble : les deux, ou aucune.'];
        }
        if (!is_numeric($latBrut) || (float) $latBrut < -90 || (float) $latBrut > 90) {
            return ['coordonnees' => 'La latitude doit être comprise entre -90 et 90.'];
        }
        if (!is_numeric($lonBrut) || (float) $lonBrut < -180 || (float) $lonBrut > 180) {
            return ['coordonnees' => 'La longitude doit être comprise entre -180 et 180.'];
        }

        // Cinq décimales : environ un mètre — la même précision que la
        // carte affiche, voir assets/js/carte.js.
        $req = $this->pdo()->prepare('UPDATE site SET latitude = ?, longitude = ? WHERE id = ?');
        $req->execute([round((float) $latBrut, 5), round((float) $lonBrut, 5), $siteId]);
        return [];
    }

    /**
     * Un `code` lisible et unique — `NNN-nom-du-site`, comme l'annonce
     * le schéma. La collision est rare (deux sites au même nom, le même
     * jour) ; la base tranche, cette méthode ne fait que réessayer.
     */
    private function codeLibre(string $nom): string
    {
        $base = strtolower(trim($nom));
        $base = preg_replace('/[^a-z0-9]+/', '-', $base) ?? '';
        $base = trim($base, '-');
        if ($base === '') {
            $base = 'site';
        }
        $base = substr($base, 0, 24);

        $numero = (int) $this->pdo()->query('SELECT COUNT(*) FROM site')->fetchColumn() + 1;
        do {
            $code = sprintf('%03d-%s', $numero, $base);
            $req = $this->pdo()->prepare('SELECT 1 FROM site WHERE code = ?');
            $req->execute([$code]);
            $pris = (bool) $req->fetchColumn();
            $numero++;
        } while ($pris);
        return $code;
    }
}
