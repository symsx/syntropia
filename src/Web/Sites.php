<?php

declare(strict_types=1);

namespace Syntropia\Web;

use PDO;
use Syntropia\Database;

/**
 * Les sites — plusieurs par compte depuis le 2026-09-18 : le sien, et ceux
 * qu'on a rejoints par invitation (chantier `chantier-inscription-
 * parrainage.md`).
 *
 * Inspiré d'`application/src/Web/Sites.php` (dépôt de référence). **Le
 * projet actif** : `pourCompte()` renvoie toujours UN site — celui que le
 * compte a activé (cookie `syntropia_projet`, revalidé à chaque lecture
 * contre `membre_site`), à défaut le premier. Les pages qui appellent
 * `pourCompte()` n'ont donc pas à savoir qu'il y en a plusieurs.
 *
 * `membre_site` (l'accès) et `participant` (une personne dont les
 * réponses comptent) restent deux tables distinctes, comme dans le
 * dépôt de référence : la première ouvre les données, la seconde nomme
 * quelqu'un. `creer()` pose les deux en une transaction, avec le même
 * compte des deux côtés.
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

    /** Le cookie qui retient le projet actif — l'identifiant seul, jamais un droit. */
    private const COOKIE_ACTIF = 'syntropia_projet';

    /**
     * Tous les sites d'un compte, avec son rôle sur chacun (`role`).
     * Dans l'ordre d'ouverture — le premier reste celui que l'on ouvrait
     * quand un compte n'en avait qu'un.
     *
     * @return list<array<string, mixed>>
     */
    public function deCompte(int $compteId): array
    {
        $req = $this->pdo()->prepare(
            'SELECT s.*, m.role FROM site s
               JOIN membre_site m ON m.site_id = s.id
              WHERE m.compte_id = ?
              ORDER BY s.id');
        $req->execute([$compteId]);
        return $req->fetchAll();
    }

    /**
     * Le site actif de ce compte, ou `null` s'il n'en a aucun.
     *
     * Le cookie dit lequel la personne a choisi ; il est revérifié contre
     * ses appartenances à chaque lecture — un projet dont elle a été
     * retirée, ou un cookie forgé, retombe sur le premier sans erreur.
     *
     * @return array<string, mixed>|null
     */
    public function pourCompte(int $compteId): ?array
    {
        $sites = $this->deCompte($compteId);
        if ($sites === []) {
            return null;
        }
        $voulu = (int) ($_COOKIE[self::COOKIE_ACTIF] ?? 0);
        foreach ($sites as $site) {
            if ((int) $site['id'] === $voulu) {
                return $site;
            }
        }
        return $sites[0];
    }

    /**
     * Retient ce site comme actif. Refuse — `false` — un site dont le
     * compte n'est pas membre : on n'active jamais ce qu'on ne peut pas
     * ouvrir.
     */
    public function activer(int $siteId, int $compteId): bool
    {
        if ($this->roleSur($siteId, $compteId) === null) {
            return false;
        }
        $_COOKIE[self::COOKIE_ACTIF] = (string) $siteId;
        if (!headers_sent()) {
            setcookie(self::COOKIE_ACTIF, (string) $siteId, [
                'expires' => time() + 365 * 24 * 3600,
                'path' => \Syntropia\Configuration::base(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        return true;
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
        $nom = $nom !== '' ? $nom : 'Porteur';
        $req = $this->pdo()->prepare(
            'INSERT INTO participant (site_id, nom, compte_id) VALUES (?, ?, ?)');
        try {
            $req->execute([$siteId, $nom, $compteId]);
        } catch (\PDOException $e) {
            // `uq_participant (site_id, nom)` : deux personnes du même nom dans
            // un projet. Le numéro du compte les distingue sans rien perdre.
            if (!str_contains($e->getMessage(), 'Duplicate')) {
                throw $e;
            }
            $req->execute([$siteId, $nom . ' (' . $compteId . ')', $compteId]);
        }
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
     * La conduite du site — `syntropique`, `jardin-foret`, ou rien (« pas
     * encore choisi », `NULL`). Une valeur hors de ces trois est refusée :
     * `false`, rien n'est écrit. Les parcelles qui ne précisent pas la leur
     * en héritent (`Heritage::conduite()`).
     */
    public function enregistrerConduite(int $siteId, string $valeur): bool
    {
        $valeur = trim($valeur);
        if ($valeur !== '' && \Syntropia\Conduite::tryFrom($valeur) === null) {
            return false;
        }
        $this->pdo()->prepare('UPDATE site SET conduite = ? WHERE id = ?')
            ->execute([$valeur !== '' ? $valeur : null, $siteId]);
        return true;
    }

    /**
     * La ressource en eau du site (`ressource_eau`, et son détail libre) — ce que
     * `conception-materiel-et-eau.php` demande pour conseiller le stockage. Une
     * ressource hors de la liste est refusée : `false`, rien n'est écrit. Vide =
     * « pas encore dite ».
     */
    public function enregistrerEau(int $siteId, string $ressource, string $detail): bool
    {
        $ressource = trim($ressource);
        $detail = trim($detail);
        if (($ressource !== '' && !isset(\Syntropia\BesoinEau::RESSOURCES[$ressource])) || mb_strlen($detail) > 190) {
            return false;
        }
        $this->pdo()->prepare('UPDATE site SET ressource_eau = ?, ressource_eau_detail = ? WHERE id = ?')
            ->execute([$ressource !== '' ? $ressource : null, $detail !== '' ? $detail : null, $siteId]);
        return true;
    }

    /**
     * Ceux qui ont accès à ce projet, le porteur d'abord, puis par ancienneté.
     * Les noms sont dans l'autre base : `Comptes::nomsParIds()` les donne.
     *
     * @return list<array{compte_id: int, role: string, depuis_le: string}>
     */
    public function membres(int $siteId): array
    {
        $req = $this->pdo()->prepare(
            "SELECT compte_id, role, depuis_le FROM membre_site
              WHERE site_id = ?
              ORDER BY role = 'porteur' DESC, depuis_le, compte_id");
        $req->execute([$siteId]);
        return array_map(static fn (array $m): array => [
            'compte_id' => (int) $m['compte_id'], 'role' => (string) $m['role'],
            'depuis_le' => (string) $m['depuis_le']], $req->fetchAll());
    }

    /**
     * Retire l'accès d'un compte à un projet. `null` si c'est fait, sinon le
     * message à montrer — les garde-fous sont ici, pas seulement dans la page.
     *
     * Seul un porteur retire, et **jamais un porteur** : le projet garde
     * toujours le sien. Ne touche que `membre_site` : la personne
     * (`participant`) et ses réponses restent dans le projet, comme une
     * invitation révoquée reste dans la table — la trace de ce qui a eu lieu.
     * Réinvitée plus tard, elle retrouve ses réponses.
     */
    public function retirerMembre(int $siteId, int $compteCible, int $compteActeur): ?string
    {
        if ($this->roleSur($siteId, $compteActeur) !== 'porteur') {
            return "Seul le porteur du projet peut retirer l'accès de quelqu'un.";
        }
        $role = $this->roleSur($siteId, $compteCible);
        if ($role === null) {
            return "Cette personne n'a plus accès à ce projet.";
        }
        if ($role === 'porteur') {
            return 'Un porteur ne se retire pas : le projet garde toujours le sien.';
        }
        $this->pdo()->prepare('DELETE FROM membre_site WHERE site_id = ? AND compte_id = ?')
            ->execute([$siteId, $compteCible]);
        return null;
    }

    /**
     * Fait entrer un compte dans un projet qui existe déjà — le rôle vient
     * de l'invitation, jamais « porteur » : on devient porteur en ouvrant un
     * projet, pas en y entrant. Pose l'accès (`membre_site`) et la personne
     * dont les réponses compteront (`participant`), en une transaction.
     *
     * `false` si le projet n'existe plus ou si le compte en est déjà
     * membre — rien n'est alors changé, surtout pas un rôle existant : un
     * porteur qui utiliserait son propre code ne doit pas se rétrograder.
     */
    public function rejoindre(int $siteId, int $compteId, string $role, string $nomParticipant): bool
    {
        if (!in_array($role, ['contributeur', 'lecteur'], true) || $this->parId($siteId) === null) {
            return false;
        }
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $req = $pdo->prepare('INSERT IGNORE INTO membre_site (site_id, compte_id, role) VALUES (?, ?, ?)');
            $req->execute([$siteId, $compteId, $role]);
            if ($req->rowCount() !== 1) {
                $pdo->rollBack();
                return false;
            }
            $this->participantDuCompte($siteId, $compteId, $nomParticipant);
            $pdo->commit();
            return true;
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
    /** Un `code` libre pour un projet de ce nom — pour l'import, qui écrit le site lui-même, dans sa transaction. */
    public function codePour(string $nom): string
    {
        return $this->codeLibre($nom);
    }

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
