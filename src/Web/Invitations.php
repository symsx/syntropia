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
 * `inviter.php` ouvre les codes (chantier `chantier-inscription-
 * parrainage.md`, Phase 2). Un code sert deux gestes : créer un compte
 * (`site_id` nul), ou créer un compte **et** rejoindre un projet (`site_id`
 * et `role_site`).
 */
final class Invitations
{
    /** Sans I, O, 0 ni 1 : ces quatre-là se confondent à l'oreille. */
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /** Un mois. Une invitation qui traîne est une porte ouverte. */
    public const JOURS = 30;

    /** Les codes qu'une même personne peut avoir ouverts en même temps. */
    public const MAX_OUVERTES = 10;

    /** Les rôles qu'un code peut donner dans un projet — jamais « porteur ». */
    public const ROLES_SITE = ['contributeur', 'lecteur'];

    public function __construct(private ?PDO $pdo = null) {}

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::comptes();
    }

    // ------------------------------------------------------------------
    // Ouvrir
    // ------------------------------------------------------------------

    /**
     * Ouvre un code. Avec `$siteId`, le code rattache aussi au projet, dans
     * le rôle `$roleSite`.
     *
     * Les garde-fous sont ici, pas seulement dans la page : seul le porteur
     * d'un projet peut y inviter, et personne n'a plus de
     * `MAX_OUVERTES` codes ouverts à la fois.
     *
     * @throws \DomainException avec un message à montrer tel quel
     */
    public function ouvrir(int $parrainId, ?int $siteId = null,
                           string $roleSite = 'contributeur',
                           string $intention = '', string $courriel = '',
                           Role $role = Role::Utilisateur): string
    {
        if ($siteId !== null) {
            if ((new Sites())->roleSur($siteId, $parrainId) !== 'porteur') {
                throw new \DomainException("Seul le porteur d'un projet peut y inviter.");
            }
            if (!in_array($roleSite, self::ROLES_SITE, true)) {
                throw new \DomainException('Le rôle dans le projet est « contributeur » ou « lecteur ».');
            }
        }
        if ($this->nombreOuvertes($parrainId) >= self::MAX_OUVERTES) {
            throw new \DomainException(sprintf(
                'Vous avez déjà %d invitations ouvertes. Retirez-en une, ou attendez '
                . "qu'elle serve, avant d'en ouvrir une autre.", self::MAX_OUVERTES));
        }
        $intention = mb_substr(trim($intention), 0, 190);
        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
            try {
                $this->pdo()->prepare(
                    'INSERT INTO invitation
                        (code, parrain_id, site_id, role_site, courriel_vise, intention,
                         role_accorde, expire_le)
                     VALUES (?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? DAY))')
                    ->execute([$code, $parrainId, $siteId,
                        $siteId !== null ? $roleSite : null,
                        trim($courriel) !== '' ? trim($courriel) : null,
                        $intention !== '' ? $intention : null,
                        $role->value, self::JOURS]);
                return $code;
            } catch (\PDOException $e) {
                if (!str_contains($e->getMessage(), 'Duplicate')) {
                    throw $e;
                }
            }
        } while (true);
    }

    /** Les codes de ce parrain qui ouvrent encore quelque chose. */
    public function nombreOuvertes(int $parrainId): int
    {
        $req = $this->pdo()->prepare(
            'SELECT COUNT(*) FROM invitation
              WHERE parrain_id = ? AND utilise_le IS NULL AND revoque_le IS NULL
                AND (expire_le IS NULL OR expire_le >= NOW())');
        $req->execute([$parrainId]);
        return (int) $req->fetchColumn();
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

    /**
     * Une page des invitations d'un parrain, les plus récentes d'abord —
     * `dUnParrain()` en rend tout, ce que l'écran d'un bureau sans
     * défilement ne peut pas montrer.
     *
     * @return list<array<string, mixed>>
     */
    public function pageDUnParrain(int $parrainId, int $limite, int $decalage): array
    {
        $req = $this->pdo()->prepare(
            'SELECT i.*, f.nom_affiche AS filleul
               FROM invitation i
               LEFT JOIN compte f ON f.id = i.filleul_id
              WHERE i.parrain_id = ?
              ORDER BY i.cree_le DESC, i.id DESC
              LIMIT ' . max(1, $limite) . ' OFFSET ' . max(0, $decalage));
        $req->execute([$parrainId]);
        return $req->fetchAll();
    }

    public function nombreDUnParrain(int $parrainId): int
    {
        $req = $this->pdo()->prepare('SELECT COUNT(*) FROM invitation WHERE parrain_id = ?');
        $req->execute([$parrainId]);
        return (int) $req->fetchColumn();
    }

    /**
     * Par qui ce compte est arrivé : la **première** invitation qu'il a
     * utilisée. Un compte qui rejoint ensuite d'autres projets par code en
     * accumule d'autres (`rejoindre.php`) ; c'est la première qui dit
     * comment il est entré.
     *
     * @return array<string, mixed>|null
     */
    public function parrainDe(int $compteId): ?array
    {
        $req = $this->pdo()->prepare(
            'SELECT i.cree_le, i.utilise_le, c.nom_affiche AS parrain
               FROM invitation i
               JOIN compte c ON c.id = i.parrain_id
              WHERE i.filleul_id = ?
              ORDER BY i.utilise_le, i.id
              LIMIT 1');
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
