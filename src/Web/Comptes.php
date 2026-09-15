<?php

declare(strict_types=1);

namespace Syntropia\Web;

use PDO;
use Syntropia\Database;
use Syntropia\Profil;
use Syntropia\Role;

/**
 * Le dépôt des comptes — la base `syntropia_comptes`.
 *
 * Adapté de `application/src/Web/Comptes.php` (dépôt de référence).
 * Contrairement à la base d'origine, `syntropia_comptes` n'est PAS
 * partagée avec une autre application — voir architecture-technique.md
 * § Le backend. `application` reste une colonne dans `acces` par souci
 * de cohérence avec le schéma emprunté, et pour ne rien fermer si un
 * jour une seconde application s'y relie.
 *
 * Il ne connaît ni session ni requête : il lit et écrit trois tables. Ce qui
 * décide de laisser entrer est dans `Authentification`.
 */
final class Comptes
{
    public const APPLICATION = 'syntropia';

    /** Au-delà, chaque tentative attend. Un mot de passe seul ne résiste qu'au rythme. */
    private const ECHECS_AVANT_DELAI = 5;
    private const DELAI_MAX_SECONDES = 30;
    private const FENETRE_MINUTES = 60;

    public function __construct(private ?PDO $pdo = null) {}

    /**
     * La connexion, ouverte au premier besoin — **pas avant**.
     */
    private function pdo(): PDO
    {
        return $this->pdo ??= Database::comptes();
    }

    // ------------------------------------------------------------------
    // Lire
    // ------------------------------------------------------------------

    /** @return array<string, mixed>|null */
    public function parIdentifiant(string $identifiant): ?array
    {
        $req = $this->pdo()->prepare(
            'SELECT id, identifiant, courriel, nom_affiche, empreinte,
                    doit_changer_mdp, actif
               FROM compte WHERE identifiant = ?');
        $req->execute([$identifiant]);
        return $req->fetch() ?: null;
    }

    /** @return array<string, mixed>|null */
    public function parId(int $id): ?array
    {
        $req = $this->pdo()->prepare(
            'SELECT id, identifiant, courriel, nom_affiche, doit_changer_mdp, actif
               FROM compte WHERE id = ?');
        $req->execute([$id]);
        return $req->fetch() ?: null;
    }

    public function nombre(): int
    {
        return (int) $this->pdo()->query('SELECT COUNT(*) FROM compte')->fetchColumn();
    }

    // ------------------------------------------------------------------
    // Écrire
    // ------------------------------------------------------------------

    /**
     * Crée un compte et son accès à une application.
     *
     * L'empreinte est calculée ici : un appelant qui passerait un mot de
     * passe en clair au lieu d'une empreinte est une faute facile, et son
     * symptôme est trompeur — la connexion échoue en disant « identifiant ou
     * mot de passe incorrect », ce qui envoie chercher ailleurs.
     */
    public function creer(string $identifiant, string $motDePasse, string $nomAffiche,
                          Role $role = Role::Utilisateur, ?string $courriel = null,
                          string $application = self::APPLICATION): int
    {
        $req = $this->pdo()->prepare(
            'INSERT INTO compte (identifiant, courriel, nom_affiche, empreinte)
             VALUES (?, ?, ?, ?)');
        $req->execute([$identifiant, $courriel, $nomAffiche,
            password_hash($motDePasse, PASSWORD_DEFAULT)]);
        $id = (int) $this->pdo()->lastInsertId();
        $this->ouvrirAcces($id, $application, $role);
        return $id;
    }

    public function ouvrirAcces(int $compteId, string $application, Role $role): void
    {
        $req = $this->pdo()->prepare(
            'INSERT INTO acces (compte_id, application, role) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE role = VALUES(role)');
        $req->execute([$compteId, $application, $role->value]);
    }

    /**
     * Ce mot de passe est-il bien celui de ce compte ?
     *
     * Distinct de `Authentification::connecter()` : celle-là ouvre une
     * session et compte les échecs, celle-ci ne fait que vérifier — pour
     * redemander son mot de passe à quelqu'un déjà entré, avant de le
     * changer.
     */
    public function motDePasseCorrespond(int $compteId, string $motDePasse): bool
    {
        $req = $this->pdo()->prepare('SELECT empreinte FROM compte WHERE id = ?');
        $req->execute([$compteId]);
        $empreinte = $req->fetchColumn();
        return is_string($empreinte) && $empreinte !== ''
            && password_verify($motDePasse, $empreinte);
    }

    public function changerMotDePasse(int $compteId, string $motDePasse): void
    {
        $req = $this->pdo()->prepare(
            'UPDATE compte SET empreinte = ?, doit_changer_mdp = 0 WHERE id = ?');
        $req->execute([password_hash($motDePasse, PASSWORD_DEFAULT), $compteId]);
    }

    public function marquerConnexion(int $compteId): void
    {
        $this->pdo()->prepare('UPDATE compte SET derniere_connexion = NOW() WHERE id = ?')
            ->execute([$compteId]);
    }

    // ------------------------------------------------------------------
    // Rôle et profil
    // ------------------------------------------------------------------

    /**
     * Le rôle dans cette application, ou `null` si aucun accès n'est ouvert.
     *
     * **`null` n'est pas `Visiteur`** : l'un veut dire « aucun accès », l'autre
     * « un compte existe mais ne peut rien écrire ici ».
     */
    public function role(int $compteId, string $application = self::APPLICATION): ?Role
    {
        $req = $this->pdo()->prepare(
            'SELECT role FROM acces WHERE compte_id = ? AND application = ?');
        $req->execute([$compteId, $application]);
        $valeur = $req->fetchColumn();
        return is_string($valeur) ? Role::tryFrom($valeur) : null;
    }

    public function profil(int $compteId, string $application = self::APPLICATION): ?Profil
    {
        $req = $this->pdo()->prepare(
            'SELECT profil FROM acces WHERE compte_id = ? AND application = ?');
        $req->execute([$compteId, $application]);
        $valeur = $req->fetchColumn();
        return is_string($valeur) ? Profil::tryFrom($valeur) : null;
    }

    /** Toujours à la demande de la personne. Jamais déduit en silence. */
    public function definirProfil(int $compteId, Profil $profil,
                                  string $application = self::APPLICATION): void
    {
        $req = $this->pdo()->prepare(
            'UPDATE acces SET profil = ?, profil_choisi_le = NOW()
              WHERE compte_id = ? AND application = ?');
        $req->execute([$profil->value, $compteId, $application]);
    }

    // ------------------------------------------------------------------
    // Le rythme des tentatives
    // ------------------------------------------------------------------

    /**
     * Secondes d'attente avant une nouvelle tentative.
     *
     * Le délai croît avec les échecs et se remet à zéro dès qu'il est écoulé :
     * ce n'est pas une punition, c'est un ralentissement.
     */
    public function delaiAvantTentative(string $identifiant, string $adresse): int
    {
        $req = $this->pdo()->prepare(
            'SELECT COUNT(*) AS echecs, COALESCE(MAX(le), NOW()) AS dernier
               FROM tentative
              WHERE (identifiant = ? OR adresse = ?)
                AND application = ?
                AND le > NOW() - INTERVAL ? MINUTE');
        $req->execute([$identifiant, $adresse, self::APPLICATION, self::FENETRE_MINUTES]);
        $ligne = $req->fetch() ?: ['echecs' => 0, 'dernier' => null];

        $echecs = (int) $ligne['echecs'];
        if ($echecs < self::ECHECS_AVANT_DELAI) {
            return 0;
        }
        $attente = min(2 ** ($echecs - self::ECHECS_AVANT_DELAI + 1), self::DELAI_MAX_SECONDES);
        $ecoule = time() - strtotime((string) $ligne['dernier'] . ' UTC');
        return max(0, $attente - $ecoule);
    }

    public function enregistrerEchec(string $identifiant, string $adresse): void
    {
        $req = $this->pdo()->prepare(
            'INSERT INTO tentative (identifiant, adresse, application) VALUES (?, ?, ?)');
        $req->execute([mb_substr($identifiant, 0, 64), mb_substr($adresse, 0, 45),
            self::APPLICATION]);
    }

    /**
     * Oublie les tentatives de cette personne, et purge les vieilles.
     */
    public function oublierTentatives(string $identifiant, string $adresse): void
    {
        $this->pdo()->prepare(
            'DELETE FROM tentative WHERE identifiant = ? OR adresse = ?')
            ->execute([$identifiant, $adresse]);
        $this->pdo()->exec('DELETE FROM tentative WHERE le < NOW() - INTERVAL 1 DAY');
    }
}
