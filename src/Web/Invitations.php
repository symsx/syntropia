<?php

declare(strict_types=1);

namespace Syntropia\Web;

use PDO;
use Syntropia\Database;
use Syntropia\Role;

/**
 * Les invitations — et le parrainage qu'elles laissent derrière elles.
 *
 * Adapté de `application/src/Web/Invitations.php` (dépôt de référence),
 * sans changement de fond. **On entre parce que quelqu'un l'a proposé** :
 * c'est ce qui ferme la porte aux robots sans fermer le site.
 *
 * **Le code se dicte de vive voix**, d'où son alphabet sans I, O, 0 ni 1
 * — ces quatre-là se confondent à l'oreille. Il n'a besoin d'aucun
 * courriel pour circuler.
 *
 * `ouvrir()` n'est pas encore appelée par aucune page de syntropia — il
 * n'y a pas encore de page « inviter quelqu'un » — mais elle est reprise
 * telle quelle, prête pour le jour où `compte.php` existera.
 */
final class Invitations
{
    /** Sans I, O, 0 ni 1 : ces quatre-là se confondent à l'oreille. */
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /** Un mois. Une invitation qui traîne est une porte ouverte. */
    public const JOURS = 30;

    public function __construct(private ?PDO $pdo = null) {}

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::comptes();
    }

    // ------------------------------------------------------------------
    // Ouvrir
    // ------------------------------------------------------------------

    public function ouvrir(int $parrainId, ?int $siteId = null,
                           string $courriel = '', string $intention = '',
                           Role $role = Role::Utilisateur): string
    {
        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
            try {
                $this->pdo()->prepare(
                    'INSERT INTO invitation
                        (code, parrain_id, site_id, courriel_vise, intention,
                         role_accorde, expire_le)
                     VALUES (?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? DAY))')
                    ->execute([$code, $parrainId, $siteId,
                        trim($courriel) !== '' ? trim($courriel) : null,
                        trim($intention) !== '' ? trim($intention) : null,
                        $role->value, self::JOURS]);
                return $code;
            } catch (\PDOException $e) {
                if (!str_contains($e->getMessage(), 'Duplicate')) {
                    throw $e;
                }
            }
        } while (true);
    }

    /** Ferme une invitation sans l'effacer : la trace du geste reste. */
    public function revoquer(int $id, int $parrainId): void
    {
        $this->pdo()->prepare(
            'UPDATE invitation SET revoque_le = NOW()
              WHERE id = ? AND parrain_id = ? AND utilise_le IS NULL')
            ->execute([$id, $parrainId]);
    }

    // ------------------------------------------------------------------
    // Lire
    // ------------------------------------------------------------------

    /**
     * L'invitation qu'ouvre ce code, si elle ouvre encore quelque chose.
     *
     * Un seul point d'entrée pour les quatre raisons de refuser —
     * inconnue, déjà utilisée, révoquée, périmée.
     *
     * @return array{invitation: array<string, mixed>|null, souci: string|null}
     */
    public function parCode(string $code): array
    {
        $code = strtoupper(trim($code));
        if (preg_match('/^[A-Z2-9]{8}$/', $code) !== 1) {
            return ['invitation' => null,
                    'souci' => "Ce code n'a pas la bonne forme : huit lettres "
                        . "ou chiffres, sans I, O, 0 ni 1."];
        }
        $req = $this->pdo()->prepare(
            'SELECT i.*, c.nom_affiche AS parrain
               FROM invitation i
               JOIN compte c ON c.id = i.parrain_id
              WHERE i.code = ?');
        $req->execute([$code]);
        $invitation = $req->fetch();

        if ($invitation === false) {
            return ['invitation' => null, 'souci' => "Ce code n'ouvre rien."];
        }
        if ($invitation['utilise_le'] !== null) {
            return ['invitation' => null,
                    'souci' => 'Cette invitation a déjà servi. Demandez-en une autre.'];
        }
        if ($invitation['revoque_le'] !== null) {
            return ['invitation' => null,
                    'souci' => 'Cette invitation a été retirée.'];
        }
        if ($invitation['expire_le'] !== null
            && strtotime((string) $invitation['expire_le']) < time()) {
            return ['invitation' => null,
                    'souci' => 'Cette invitation a expiré. Demandez-en une autre.'];
        }
        return ['invitation' => $invitation, 'souci' => null];
    }

    /** @return list<array<string, mixed>> */
    public function dUnParrain(int $parrainId): array
    {
        $req = $this->pdo()->prepare(
            'SELECT i.*, f.nom_affiche AS filleul
               FROM invitation i
               LEFT JOIN compte f ON f.id = i.filleul_id
              WHERE i.parrain_id = ?
              ORDER BY i.cree_le DESC');
        $req->execute([$parrainId]);
        return $req->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function parrainDe(int $compteId): ?array
    {
        $req = $this->pdo()->prepare(
            'SELECT i.cree_le, i.utilise_le, c.nom_affiche AS parrain
               FROM invitation i
               JOIN compte c ON c.id = i.parrain_id
              WHERE i.filleul_id = ?');
        $req->execute([$compteId]);
        return $req->fetch() ?: null;
    }

    /** L'état d'une invitation, en un mot. */
    public function etat(array $invitation): string
    {
        if ($invitation['utilise_le'] !== null) {
            return 'utilisée';
        }
        if ($invitation['revoque_le'] !== null) {
            return 'retirée';
        }
        if ($invitation['expire_le'] !== null
            && strtotime((string) $invitation['expire_le']) < time()) {
            return 'expirée';
        }
        return 'ouverte';
    }

    // ------------------------------------------------------------------
    // Consommer
    // ------------------------------------------------------------------

    /**
     * Note qu'une invitation a servi, et pour qui.
     *
     * La condition `utilise_le IS NULL` est dans la requête, non dans un
     * contrôle qui précéderait : deux inscriptions simultanées sur le
     * même code se départagent dans la base, pas dans une course.
     */
    public function consommer(int $id, int $filleulId): bool
    {
        $req = $this->pdo()->prepare(
            'UPDATE invitation SET utilise_le = NOW(), filleul_id = ?
              WHERE id = ? AND utilise_le IS NULL AND revoque_le IS NULL');
        $req->execute([$filleulId, $id]);
        return $req->rowCount() === 1;
    }
}
