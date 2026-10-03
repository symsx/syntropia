<?php

declare(strict_types=1);

/**
 * S'inscrire, avec une invitation.
 *
 * Adapté de `application/public/inscription.php` (dépôt de référence),
 * sur le gabarit du bureau. **On entre parce que quelqu'un l'a
 * proposé** : pas d'inscription libre, voir `src/Web/Invitations.php`.
 *
 * Un code est ouvert par `inviter.php`, donné de vive voix ou par écrit.
 * S'il porte un projet (`invitation.site_id`), le nouveau compte y entre
 * aussitôt, dans le rôle du code (`role_site`) — chantier
 * `chantier-inscription-parrainage.md`, Phase 3. Il pourra ouvrir son
 * propre projet ensuite, depuis le menu du compte.
 */

require __DIR__ . '/../src/autoload.php';
require __DIR__ . '/../src/Configuration.php';
require __DIR__ . '/../src/Database.php';

use Syntropia\Configuration;
use Syntropia\Role;
use Syntropia\Web\Authentification;
use Syntropia\Web\Comptes;
use Syntropia\Web\Invitations;
use Syntropia\Web\Sites;

Authentification::demarrer($_SERVER);
$auth = new Authentification();

if ($auth->estConnecte()) {
    // Un lien d'invitation ouvert par quelqu'un qui a déjà un compte : c'est
    // « rejoindre », pas « s'inscrire ». Le code suit.
    $codeLien = strtoupper(trim((string) ($_GET['code'] ?? '')));
    header('Location: ' . Configuration::base()
        . ($codeLien !== '' ? 'rejoindre.php?code=' . rawurlencode($codeLien) : 'conception-parcelles.php'));
    exit;
}

$comptes = new Comptes();
$invitations = new Invitations();

$code = strtoupper(trim((string) ($_GET['code'] ?? $_POST['code'] ?? '')));
$lu = $code === '' ? ['invitation' => null, 'souci' => null] : $invitations->parCode($code);
$invitation = $lu['invitation'];

// Le projet que le code ouvre, s'il en ouvre un — nom et rôle, pour le dire
// avant de demander quoi que ce soit.
$sitesService = new Sites();
$projetInvite = $invitation !== null && $invitation['site_id'] !== null
    ? $sitesService->parId((int) $invitation['site_id']) : null;
$roleInvite = $invitation['role_site'] ?? null;
$dit = ['contributeur' => 'avec le rôle de contributeur', 'lecteur' => 'avec le rôle de lecteur'];
$message = $lu['souci'];
$classe = 'erreur';
$fait = false;

$identifiant = '';
$nomAffiche = '';
$courriel = '';

if ($invitation !== null && $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'inscrire') {

    if (!$auth->jetonValide($_POST['csrf'] ?? null)) {
        $message = 'Le formulaire a expiré. Rechargez la page et réessayez.';
    } else {
        $identifiant = strtolower(trim((string) ($_POST['identifiant'] ?? '')));
        $nomAffiche = trim((string) ($_POST['nom_affiche'] ?? ''));
        $courriel = trim((string) ($_POST['courriel'] ?? ''));
        $mot = (string) ($_POST['mot_de_passe'] ?? '');
        $confirme = (string) ($_POST['confirmer'] ?? '');

        if (preg_match('/^[a-z0-9._-]{3,64}$/', $identifiant) !== 1) {
            $message = "L'identifiant compte de 3 à 64 caractères, en minuscules, chiffres, "
                . 'point, tiret ou souligné. Il sert à vous connecter.';
        } elseif ($nomAffiche === '') {
            $message = 'Le nom affiché est nécessaire. C\'est le nom que voient les autres personnes du projet.';
        } elseif (filter_var($courriel, FILTER_VALIDATE_EMAIL) === false) {
            $message = 'Une adresse de courriel valide est nécessaire. Elle est le seul moyen '
                . 'de retrouver un compte dont le mot de passe est perdu.';
        } elseif (mb_strlen($mot) < 12) {
            $message = 'Le mot de passe compte au moins 12 caractères. '
                . "Une phrase est plus sûre qu'un mot.";
        } elseif ($mot !== $confirme) {
            $message = 'Les deux mots de passe saisis sont différents.';
        } else {
            try {
                $role = Role::tryFrom((string) $invitation['role_accorde']) ?? Role::Utilisateur;
                $nouveau = $comptes->creer($identifiant, $mot, $nomAffiche, $role, $courriel);

                // L'invitation se consomme APRÈS la création, et la base
                // tranche les simultanés — voir Invitations::consommer().
                $classe = 'succes';
                if (!$invitations->consommer((int) $invitation['id'], $nouveau)) {
                    // Perdue de justesse : le compte existe, mais le projet
                    // n'est pas à lui — le code a servi à quelqu'un d'autre.
                    $message = 'Cette invitation vient de servir à une autre personne. '
                        . 'Votre compte est tout de même créé, et vous pouvez vous connecter.';
                } elseif ($invitation['site_id'] === null) {
                    $message = 'Votre compte est créé. Vous pouvez vous connecter.';
                } else {
                    // Le compte et le projet vivent dans deux bases : pas de
                    // transaction commune. Si le rattachement échoue, le
                    // compte reste — et le dit, plutôt que de le taire.
                    try {
                        $entre = $sitesService->rejoindre((int) $invitation['site_id'], $nouveau,
                            (string) $roleInvite, $nomAffiche);
                    } catch (\Throwable $e) {
                        $entre = false;
                    }
                    $message = $entre && $projetInvite !== null
                        ? sprintf('Votre compte est créé. Vous rejoignez le projet « %s » %s. Vous pouvez vous connecter.',
                            $projetInvite['nom'], $dit[$roleInvite] ?? '')
                        : "Votre compte est créé, mais le projet n'a pas pu vous être ouvert. "
                            . 'Demandez une nouvelle invitation à la personne qui vous a invité.';
                }
                $fait = true;
            } catch (\PDOException $e) {
                $message = str_contains($e->getMessage(), 'Duplicate')
                    ? 'Cet identifiant ou cette adresse de courriel est déjà utilisé par un autre compte.'
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
<link rel="stylesheet" href="assets/css/gabarit.css?v=<?= (int) @filemtime(__DIR__ . '/assets/css/gabarit.css') ?>">
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
            <p class="inscription-chapeau">L'inscription se fait sur invitation, avec un code
               de huit caractères donné par une personne déjà inscrite. Le code ne contient
               ni I, ni O, ni zéro, ni un, pour se dicter sans erreur.</p>
            <form method="get" class="inscription-code">
              <div class="inscription-champ">
                <label for="code">Le code</label>
                <input id="code" name="code" required maxlength="8"
                       value="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>">
              </div>
              <button type="submit" class="inscription-bouton">Continuer</button>
            </form>
            <p class="inscription-note">Pour obtenir un code, adressez-vous à la personne
               qui vous a parlé de l'atelier.</p>

          <?php else: ?>
            <h1>Rejoindre</h1>
            <p class="inscription-invitation">Invitation de
               <strong><?= htmlspecialchars((string) $invitation['parrain'], ENT_QUOTES, 'UTF-8') ?></strong><?php if (($invitation['intention'] ?? null) !== null): ?>
               — <em><?= htmlspecialchars((string) $invitation['intention'], ENT_QUOTES, 'UTF-8') ?></em>
            <?php endif; ?></p>
<?php if ($projetInvite !== null): ?>
            <p class="inscription-invitation">Cette invitation vous fait rejoindre le projet
               <strong>« <?= htmlspecialchars((string) $projetInvite['nom'], ENT_QUOTES, 'UTF-8') ?> »</strong>
               <?= htmlspecialchars($dit[$roleInvite] ?? '', ENT_QUOTES, 'UTF-8') ?>.
               Vous pourrez ensuite ouvrir votre propre projet.</p>
<?php endif; ?>

            <form method="post">
              <input type="hidden" name="action" value="inscrire">
              <input type="hidden" name="code" value="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($auth->jeton(), ENT_QUOTES, 'UTF-8') ?>">
              <div class="inscription-champ">
                <label for="identifiant">Identifiant</label>
                <small>Il sert à vous connecter. Minuscules, chiffres, point, tiret ou souligné.</small>
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
                <small>Douze caractères au minimum. Une phrase est plus sûre qu'un mot.</small>
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
      <p><strong>L'inscription sur invitation.</strong> L'inscription se fait sur
         invitation, avec un code de huit caractères. Le code se transmet de vive voix ou
         par écrit.</p>
      <p><strong>Le compte.</strong> Le compte est actif dès sa création. L'adresse de
         courriel n'est pas vérifiée.</p>
    </div>
  </aside>

  <footer class="pied">
    <span class="pied-mention">Syntropia — en construction</span>
    <span class="pied-spacer"></span>
  </footer>

</div>

<script src="assets/js/etapes.js?v=<?= (int) @filemtime(__DIR__ . '/assets/js/etapes.js') ?>"></script>
<script src="assets/js/gabarit.js?v=<?= (int) @filemtime(__DIR__ . '/assets/js/gabarit.js') ?>"></script>
<script>
  Gabarit.init({});
</script>
</body>
</html>
