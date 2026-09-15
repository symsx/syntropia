<?php

declare(strict_types=1);

/**
 * S'inscrire, avec une invitation.
 *
 * Adapté de `application/public/inscription.php` (dépôt de référence),
 * sur le gabarit du bureau. **On entre parce que quelqu'un l'a
 * proposé** : pas d'inscription libre, voir `src/Web/Invitations.php`.
 *
 * Ce qui n'est pas encore là : aucune page « inviter quelqu'un » ne
 * peut encore ouvrir un code — cette page attend un code déjà ouvert
 * par quelqu'un d'autre (voir `Invitations::ouvrir()`), donné de vive
 * voix ou par écrit. Le rattachement à un site (`invitation.site_id`)
 * n'est pas câblé non plus : ce service (`Sites`) n'existe pas encore
 * dans syntropia.
 */

require __DIR__ . '/../src/autoload.php';
require __DIR__ . '/../src/Configuration.php';
require __DIR__ . '/../src/Database.php';

use Syntropia\Configuration;
use Syntropia\Role;
use Syntropia\Web\Authentification;
use Syntropia\Web\Comptes;
use Syntropia\Web\Invitations;

Authentification::demarrer($_SERVER);
$auth = new Authentification();

if ($auth->estConnecte()) {
    header('Location: ' . Configuration::base() . 'conception-parcelles.php');
    exit;
}

$comptes = new Comptes();
$invitations = new Invitations();

$code = strtoupper(trim((string) ($_GET['code'] ?? $_POST['code'] ?? '')));
$lu = $code === '' ? ['invitation' => null, 'souci' => null] : $invitations->parCode($code);
$invitation = $lu['invitation'];
$message = $lu['souci'];
$classe = 'erreur';
$fait = false;

$identifiant = '';
$nomAffiche = '';
$courriel = '';

if ($invitation !== null && $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'inscrire') {

    if (!$auth->jetonValide($_POST['csrf'] ?? null)) {
        $message = 'Jeton de sécurité invalide — réessayez.';
    } else {
        $identifiant = strtolower(trim((string) ($_POST['identifiant'] ?? '')));
        $nomAffiche = trim((string) ($_POST['nom_affiche'] ?? ''));
        $courriel = trim((string) ($_POST['courriel'] ?? ''));
        $mot = (string) ($_POST['mot_de_passe'] ?? '');
        $confirme = (string) ($_POST['confirmer'] ?? '');

        if (preg_match('/^[a-z0-9._-]{3,64}$/', $identifiant) !== 1) {
            $message = "L'identifiant prend minuscules, chiffres, point, tiret et "
                . 'souligné — de 3 à 64 caractères. C\'est ce que vous taperez pour '
                . 'vous connecter.';
        } elseif ($nomAffiche === '') {
            $message = "Le nom affiché est nécessaire : c'est ce que les autres verront.";
        } elseif (filter_var($courriel, FILTER_VALIDATE_EMAIL) === false) {
            $message = 'Une adresse de courriel valide est nécessaire. Sans elle, '
                . 'un mot de passe perdu serait un compte perdu.';
        } elseif (mb_strlen($mot) < 12) {
            $message = 'Le mot de passe fait au moins 12 caractères. '
                . "Une phrase vaut mieux qu'un mot.";
        } elseif ($mot !== $confirme) {
            $message = 'Les deux saisies diffèrent.';
        } else {
            try {
                $role = Role::tryFrom((string) $invitation['role_accorde']) ?? Role::Utilisateur;
                $nouveau = $comptes->creer($identifiant, $mot, $nomAffiche, $role, $courriel);

                // L'invitation se consomme APRÈS la création, et la base
                // tranche les simultanés — voir Invitations::consommer().
                if (!$invitations->consommer((int) $invitation['id'], $nouveau)) {
                    $classe = 'succes';
                    $message = "Cette invitation venait de servir à quelqu'un d'autre. "
                        . 'Votre compte est créé — vous pouvez vous connecter.';
                } else {
                    $classe = 'succes';
                    $message = 'Compte créé. Vous pouvez vous connecter.';
                }
                $fait = true;
            } catch (\PDOException $e) {
                $message = str_contains($e->getMessage(), 'Duplicate')
                    ? 'Cet identifiant ou ce courriel est déjà pris.'
                    : "Le compte n'a pas pu être créé.";
            }
        }
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>S'inscrire — Syntropia</title>
<link rel="stylesheet" href="assets/css/gabarit.css">
<style>
  /* propre à cette page : pas de bandeau d'outils (rien à choisir) ;
     formulaire compact pour tenir dans le bureau sans défilement, même
     à l'étape d'inscription (cinq champs). */
  :root{ --haut-bandeaux: 3.4rem; }

  .inscription-centre{ flex:1; display:flex; align-items:center; justify-content:center; padding:1.4rem 1.5rem; overflow:hidden; }
  .inscription-cadre{ width:100%; max-width:23rem; }
  .inscription-cadre h1{ font:600 1.25rem var(--titre); color:var(--accent-titre); margin:0 0 .6rem; text-align:center; }
  .inscription-chapeau{ font:400 .85rem/1.4 var(--texte); color:var(--muted); text-align:center; margin:0 0 1.1rem; }
  .inscription-champ{ margin-bottom:.75rem; }
  .inscription-champ label{ display:block; font:500 .82rem var(--titre); color:var(--ink); margin-bottom:.2rem; }
  .inscription-champ small{ display:block; font:400 .72rem/1.3 var(--texte); color:var(--muted); margin-bottom:.25rem; }
  .inscription-champ input{
    width:100%; padding:.5rem .65rem; font:400 .92rem var(--texte); color:var(--ink);
    background:var(--surface); border:1px solid var(--line); border-radius:.5rem;
  }
  .inscription-champ input:focus{ outline:2px solid var(--accent); outline-offset:1px; }
  .inscription-code input{ text-transform:uppercase; letter-spacing:.25em; text-align:center; }
  .inscription-bouton{
    width:100%; margin-top:.3rem; padding:.6rem; border:0; border-radius:.6rem;
    background:var(--accent); color:var(--bouton-encre); font:600 .92rem var(--titre); cursor:pointer;
  }
  .inscription-bouton:hover{ opacity:.92; }
  .inscription-message{
    background:var(--accent-fond); border:1px solid var(--alerte); color:var(--alerte);
    border-radius:.5rem; padding:.55rem .75rem; font-size:.85rem; margin-bottom:1rem;
  }
  .inscription-message--succes{ border-color:var(--accent); color:var(--accent-titre); }
  .inscription-invitation{ font:400 .85rem/1.4 var(--texte); color:var(--ink); text-align:center; margin:0 0 1rem; }
  .inscription-note{ font:400 .78rem/1.3 var(--texte); color:var(--muted); text-align:center; margin-top:.9rem; }
  .inscription-lien{ text-align:center; }
  .inscription-lien a{ color:var(--accent-titre); font:600 .95rem var(--titre); text-decoration:none; }
  .inscription-lien a:hover{ text-decoration:underline; }
</style>
</head>
<body>
<div class="app" id="app">

  <header class="bandeau-general">
    <div class="bg-gauche">
      <button class="hamburger" id="hamburger" aria-expanded="false" aria-controls="panneau-gauche"
              aria-label="Afficher le menu">
        <span></span><span></span><span></span>
      </button>
      <span class="logo-espace" aria-hidden="true" title="Emplacement du logo">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v11M12 3C8 5 6 8 6 12M12 3c4 2 6 5 6 9M6 12c0 4 2.5 7 6 8M18 12c0 4-2.5 7-6 8"/></svg>
      </span>
      <div class="marque">
        <a class="marque-nom" href="./">Syntropia</a>
      </div>
    </div>
    <div class="bg-centre">
      <span class="bg-centre-titre">S'inscrire</span>
    </div>
    <div class="bg-droite">
      <button class="theme-bascule" id="theme-bascule" aria-label="Changer de thème" title="Thème">
        <span class="theme-icone" id="theme-icone" aria-hidden="true">◐</span>
      </button>
    </div>
  </header>

  <div class="bulle" id="bulle" role="status"></div>

  <div class="corps">
    <div class="scrim" id="scrim-gauche"></div>
    <nav class="panneau-gauche" id="panneau-gauche" aria-label="Navigation de l'atelier" aria-hidden="true">
      <div class="pg-entete">
        <a class="marque-nom" href="./">Syntropia</a>
      </div>
      <div class="pg-corps">
        <div class="pg-section">
          <p class="pg-titre">Le site</p>
          <ul class="pg-liste">
            <li><a href="./"><span class="pg-puce">⌂</span><span class="pg-libelle">Accueil</span></a></li>
            <li><a href="connexion.php"><span class="pg-puce">→</span><span class="pg-libelle">Se connecter</span></a></li>
            <li><a href="inscription.php" class="active" aria-current="page"><span class="pg-puce">+</span><span class="pg-libelle">S'inscrire</span></a></li>
          </ul>
        </div>
      </div>
    </nav>

    <main class="bureau" id="bureau">
      <div class="inscription-centre">
        <div class="inscription-cadre">

          <?php if ($message !== null): ?>
            <p class="inscription-message inscription-message--<?= htmlspecialchars($classe, ENT_QUOTES, 'UTF-8') ?>">
              <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
            </p>
          <?php endif; ?>

          <?php if ($fait && $classe === 'succes'): ?>
            <h1>Compte créé</h1>
            <p class="inscription-lien"><a href="connexion.php">Se connecter →</a></p>

          <?php elseif ($invitation === null): ?>
            <h1>Rejoindre</h1>
            <p class="inscription-chapeau">On entre ici sur invitation. Quelqu'un qui a
               déjà un compte vous a donné un code de huit caractères — il se dicte à
               voix haute, sans I, ni O, ni zéro, ni un.</p>
            <form method="get" class="inscription-code">
              <div class="inscription-champ">
                <label for="code">Le code</label>
                <input id="code" name="code" required maxlength="8"
                       value="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>">
              </div>
              <button type="submit" class="inscription-bouton">Continuer</button>
            </form>
            <p class="inscription-note">Pas de code ? Ce site n'est pas ouvert à
               l'inscription libre — demandez à qui vous en a parlé.</p>

          <?php else: ?>
            <h1>Rejoindre</h1>
            <p class="inscription-invitation">Invitation de
               <strong><?= htmlspecialchars((string) $invitation['parrain'], ENT_QUOTES, 'UTF-8') ?></strong><?php if (($invitation['intention'] ?? null) !== null): ?>
               — <em><?= htmlspecialchars((string) $invitation['intention'], ENT_QUOTES, 'UTF-8') ?></em>
            <?php endif; ?></p>

            <form method="post">
              <input type="hidden" name="action" value="inscrire">
              <input type="hidden" name="code" value="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($auth->jeton(), ENT_QUOTES, 'UTF-8') ?>">
              <div class="inscription-champ">
                <label for="identifiant">Identifiant</label>
                <small>Minuscules, chiffres, point, tiret, souligné — pour vous connecter.</small>
                <input id="identifiant" name="identifiant" required pattern="[a-z0-9._-]{3,64}"
                       autocomplete="username" value="<?= htmlspecialchars($identifiant, ENT_QUOTES, 'UTF-8') ?>">
              </div>
              <div class="inscription-champ">
                <label for="nom_affiche">Nom affiché</label>
                <input id="nom_affiche" name="nom_affiche" required
                       value="<?= htmlspecialchars($nomAffiche, ENT_QUOTES, 'UTF-8') ?>">
              </div>
              <div class="inscription-champ">
                <label for="courriel">Courriel</label>
                <input id="courriel" name="courriel" type="email" required autocomplete="email"
                       value="<?= htmlspecialchars($courriel !== '' ? $courriel : (string) ($invitation['courriel_vise'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
              </div>
              <div class="inscription-champ">
                <label for="mot_de_passe">Mot de passe</label>
                <small>Douze caractères au minimum — une phrase vaut mieux qu'un mot.</small>
                <input id="mot_de_passe" name="mot_de_passe" type="password" required
                       minlength="12" autocomplete="new-password">
              </div>
              <div class="inscription-champ">
                <label for="confirmer">Confirmer</label>
                <input id="confirmer" name="confirmer" type="password" required
                       minlength="12" autocomplete="new-password">
              </div>
              <button type="submit" class="inscription-bouton">Ouvrir le compte</button>
            </form>
          <?php endif; ?>

        </div>
      </div>
    </main>
  </div>

  <div class="scrim" id="scrim-info"></div>
  <button class="tirette-info" id="tirette-info" aria-expanded="false" aria-controls="panneau-info"
          aria-label="Afficher les informations">
    <svg class="tirette-triangle" viewBox="0 0 10 10" aria-hidden="true"><polygon points="1,1 9,5 1,9" fill="currentColor"/></svg>
    <span class="tirette-texte">Informations</span>
  </button>
  <aside class="panneau-info" id="panneau-info" aria-hidden="true">
    <div class="pi-corps">
      <p><strong>Pourquoi sur invitation</strong> — un formulaire d'inscription
         ouvert à tous attire du bruit avant d'attirer des porteurs de projet.
         Le code, dicté de vive voix, ne demande ni courriel ni service tiers
         pour circuler.</p>
      <p><strong>Ce qui n'est pas encore là</strong> — le courriel est demandé
         mais non confirmé, et le compte est actif aussitôt créé ; il n'existe
         pas encore de page pour ouvrir soi-même une invitation.</p>
    </div>
  </aside>

  <footer class="pied">
    <span class="pied-mention">Syntropia — en construction</span>
    <span class="pied-spacer"></span>
  </footer>

</div>

<script src="assets/js/etapes.js"></script>
<script src="assets/js/gabarit.js"></script>
<script>
  Gabarit.init({});
</script>
</body>
</html>
