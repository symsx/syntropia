<?php

declare(strict_types=1);

namespace Syntropia\Web;

use Syntropia\Configuration;
use Syntropia\Profil;
use Syntropia\Role;

/**
 * Qui est là, et ce qu'il peut.
 *
 * Adapté de `application/src/Web/Authentification.php` (dépôt de
 * référence). Là-bas, l'isolation de session protégeait d'un cookie
 * partagé avec Plantae, sur la même origine. Ici, `syntropia_comptes`
 * n'est déjà plus partagée avec personne — voir architecture-technique.md
 * § Le backend. Le nom de cookie propre et le cantonnement sous une clé
 * à soi restent posés quand même : **le jour où syntropia parlera à une
 * autre application (Plantae, ou une autre)**, rien n'aura à être repensé.
 *
 * Deux règles, déjà appliquées :
 *
 * 1. **un nom de cookie propre** (`SYNTROPIA`) — pas de collision possible
 *    avec une session voisine ;
 * 2. **aucune écriture à la racine de `$_SESSION`.** Ce qui serait commun
 *    irait sous `auth`, ce qui est propre à syntropia sous `syntropia`.
 *
 * Et la règle qui rend un partage sûr, s'il vient un jour : **être connecté
 * ne donne aucun droit par soi-même.** Le rôle se vérifie en base,
 * application par application.
 */
final class Authentification
{
    private const COOKIE = 'SYNTROPIA';
    private const COMMUN = 'auth';
    private const PROPRE = 'syntropia';

    private Comptes $comptes;

    public function __construct(?Comptes $comptes = null)
    {
        $this->comptes = $comptes ?? new Comptes();
    }

    // ------------------------------------------------------------------
    // La session
    // ------------------------------------------------------------------

    /**
     * Démarre une session durcie, sous un nom qui n'est qu'à nous.
     *
     * @param array<string, mixed> $serveur généralement `$_SERVER`
     */
    public static function demarrer(array $serveur): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_name(self::COOKIE);
        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => self::chiffre($serveur),
            'path' => Configuration::base(),
        ]);
        session_start();
    }

    /**
     * Reprend une session déjà ouverte ; n'en ouvre jamais.
     *
     * @param array<string, mixed> $serveur
     * @param array<string, mixed>|null $cookies
     */
    public static function reprendre(array $serveur, ?array $cookies = null): bool
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return true;
        }
        if (headers_sent() || !isset(($cookies ?? $_COOKIE)[self::COOKIE])) {
            return false;
        }
        self::demarrer($serveur);
        return true;
    }

    // ------------------------------------------------------------------
    // Entrer, sortir
    // ------------------------------------------------------------------

    /**
     * Vérifie un couple identifiant / mot de passe.
     *
     * L'échec ne dit jamais **lequel** des deux est faux : le distinguer
     * confirmerait l'existence d'un identifiant à qui l'essaie.
     *
     * Renvoie le message à afficher, ou `null` si l'on entre.
     */
    public function connecter(string $identifiant, string $motDePasse, string $adresse): ?string
    {
        $delai = $this->comptes->delaiAvantTentative($identifiant, $adresse);
        if ($delai > 0) {
            return "Trop de tentatives. Réessayez dans {$delai} secondes.";
        }

        $compte = $this->comptes->parIdentifiant($identifiant);
        $empreinte = is_array($compte) ? (string) $compte['empreinte']
            : '$2y$12$' . str_repeat('.', 53);
        $motOk = password_verify($motDePasse, $empreinte);

        if (!is_array($compte) || !$motOk || (int) $compte['actif'] !== 1) {
            $this->comptes->enregistrerEchec($identifiant, $adresse);
            return 'Identifiant ou mot de passe incorrect.';
        }

        $id = (int) $compte['id'];
        $role = $this->comptes->role($id);
        if ($role === null) {
            return "Ce compte n'a pas accès à syntropia.";
        }

        session_regenerate_id(true);
        $_SESSION[self::COMMUN] = ['compte_id' => $id, 'le' => time()];
        $_SESSION[self::PROPRE] = [
            'nom_affiche' => (string) $compte['nom_affiche'],
            'role' => $role->value,
            'doit_changer_mdp' => (int) $compte['doit_changer_mdp'] === 1,
        ];
        $this->comptes->marquerConnexion($id);
        $this->comptes->oublierTentatives($identifiant, $adresse);
        return null;
    }

    /**
     * Ne vide que ce qui est à nous.
     */
    public function deconnecter(): void
    {
        unset($_SESSION[self::COMMUN], $_SESSION[self::PROPRE]);
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    // ------------------------------------------------------------------
    // Qui est là
    // ------------------------------------------------------------------

    public function estConnecte(): bool
    {
        return $this->compteId() !== null;
    }

    public function compteId(): ?int
    {
        $id = $_SESSION[self::COMMUN]['compte_id'] ?? null;
        return is_int($id) ? $id : null;
    }

    public function nomAffiche(): ?string
    {
        $nom = $_SESSION[self::PROPRE]['nom_affiche'] ?? null;
        return is_string($nom) ? $nom : null;
    }

    /**
     * Le rôle **dans cette application**.
     *
     * Lu en session pour l'affichage. Une page qui garde doit passer par
     * `exige()`, qui interroge la base : un rôle retiré pendant qu'une
     * session dure ne doit pas survivre à son retrait.
     */
    public function role(): ?Role
    {
        $valeur = $_SESSION[self::PROPRE]['role'] ?? null;
        return is_string($valeur) ? Role::tryFrom($valeur) : null;
    }

    public function profil(): ?Profil
    {
        $id = $this->compteId();
        return $id === null ? null : $this->comptes->profil($id);
    }

    public function doitChangerMotDePasse(): bool
    {
        return ($_SESSION[self::PROPRE]['doit_changer_mdp'] ?? false) === true;
    }

    /**
     * Ferme une page. **C'est la page qui garde** — cacher un lien n'a
     * jamais fermé une porte.
     *
     * Le rôle est relu en base à chaque appel, et non pris en session : un
     * accès retiré doit cesser sans attendre l'expiration du cookie.
     */
    public function exige(Role $minimum, string $versOu = 'connexion.php'): void
    {
        $id = $this->compteId();
        $role = $id === null ? null : $this->comptes->role($id);
        $suffisant = $role !== null && match ($minimum) {
            Role::Visiteur => true,
            Role::Utilisateur => $role->peutEcrire(),
            Role::Administrateur => $role->peutAdministrer(),
        };
        if ($suffisant) {
            $ici = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
            if ($this->doitChangerMotDePasse()
                && !in_array($ici, ['compte.php', 'connexion.php'], true)) {
                if (!headers_sent()) {
                    header('Location: ' . Configuration::base()
                        . 'compte.php?mdp=1');
                }
                exit;
            }
            return;
        }
        if (!headers_sent()) {
            header('Location: ' . Configuration::base() . $versOu);
        }
        exit;
    }

    /** Le nom affiché a changé : la session en garde une copie pour l'affichage. */
    public function rafraichirNomAffiche(string $nom): void
    {
        if (isset($_SESSION[self::PROPRE])) {
            $_SESSION[self::PROPRE]['nom_affiche'] = $nom;
        }
    }

    /**
     * Oublie le drapeau après un changement, sans redemander la session.
     */
    public function motDePasseChange(): void
    {
        if (isset($_SESSION[self::PROPRE])) {
            $_SESSION[self::PROPRE]['doit_changer_mdp'] = false;
        }
    }

    // ------------------------------------------------------------------
    // CSRF
    // ------------------------------------------------------------------

    /** Le jeton de la session, créé au premier appel. */
    public function jeton(): string
    {
        $jeton = $_SESSION[self::PROPRE]['csrf'] ?? null;
        if (!is_string($jeton)) {
            $jeton = bin2hex(random_bytes(32));
            $_SESSION[self::PROPRE]['csrf'] = $jeton;
        }
        return $jeton;
    }

    /**
     * Un `POST` qui écrit doit porter le jeton de la session.
     */
    public function jetonValide(?string $recu): bool
    {
        $attendu = $_SESSION[self::PROPRE]['csrf'] ?? null;
        return is_string($attendu) && is_string($recu) && hash_equals($attendu, $recu);
    }

    // ------------------------------------------------------------------
    // Transport
    // ------------------------------------------------------------------

    /**
     * La connexion est-elle protégée, ou explicitement dispensée de l'être ?
     *
     * `localhost` est admis sans condition : rien ne transite par un réseau.
     *
     * @param array<string, mixed> $serveur
     */
    public static function transportAcceptable(array $serveur, ?string $derogation = null): bool
    {
        $hote = (string) ($serveur['HTTP_HOST'] ?? '');
        $local = preg_match('/^(localhost|127\.0\.0\.1|\[::1\])(:\d+)?$/', $hote) === 1;
        if ($local || self::chiffre($serveur)) {
            return true;
        }
        return ($derogation ?? getenv('SYNTROPIA_AUTORISER_HTTP') ?: '') === '1';
    }

    /**
     * La requête est-elle arrivée chiffrée ?
     *
     * `X-Forwarded-Proto` n'est cru que d'un mandataire déclaré.
     *
     * @param array<string, mixed> $serveur
     */
    public static function chiffre(array $serveur, ?string $mandataires = null): bool
    {
        if ((($serveur['HTTPS'] ?? '') !== '' && ($serveur['HTTPS'] ?? '') !== 'off')
            || (int) ($serveur['SERVER_PORT'] ?? 0) === 443) {
            return true;
        }
        if (strtolower((string) ($serveur['HTTP_X_FORWARDED_PROTO'] ?? '')) !== 'https') {
            return false;
        }
        $declares = array_filter(array_map('trim', explode(',',
            $mandataires ?? (string) (getenv('SYNTROPIA_MANDATAIRES') ?: ''))));
        return $declares !== []
            && in_array((string) ($serveur['REMOTE_ADDR'] ?? ''), $declares, true);
    }

    /** @param array<string, mixed> $serveur */
    public static function adresse(array $serveur): string
    {
        $brut = (string) ($serveur['REMOTE_ADDR'] ?? '');
        return $brut === '' ? 'inconnue' : $brut;
    }
}
