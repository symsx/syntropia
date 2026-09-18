<?php

declare(strict_types=1);

/**
 * Rejoindre un projet avec un code — pour un compte qui existe déjà.
 *
 * Chantier `chantier-inscription-parrainage.md`, Phase 4. Le même code que
 * `inscription.php`, la même consommation, les mêmes garde-fous — mais pour
 * quelqu'un qui a déjà un compte, et qui garde ses projets : le projet
 * rejoint s'y ajoute. Deux temps : on lit le code et on **montre** le projet
 * et le rôle, puis on confirme. Un code qui n'ouvre qu'un compte (sans
 * projet) n'est pas consommé ici : il attend quelqu'un qui n'en a pas.
 */

require __DIR__ . '/../src/autoload.php';
require __DIR__ . '/../src/Configuration.php';
require __DIR__ . '/../src/Database.php';

use Syntropia\Configuration;
use Syntropia\Role;
use Syntropia\Web\Authentification;
use Syntropia\Web\Invitations;
use Syntropia\Web\Sites;

Authentification::demarrer($_SERVER);
$auth = new Authentification();
$auth->exige(Role::Utilisateur);

$compteId = (int) $auth->compteId();
$invitations = new Invitations();
$sites = new Sites();

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$dit = ['contributeur' => 'en contribuant', 'lecteur' => 'en lisant'];

$code = strtoupper(trim((string) ($_POST['code'] ?? $_GET['code'] ?? '')));
$message = null;      // ce qu'on dit à la personne
$classe = 'erreur';
$invitation = null;
$projet = null;
$fait = false;

if ($code !== '') {
    $lu = $invitations->parCode($code);
    $invitation = $lu['invitation'];
    $message = $lu['souci'];
}
if ($invitation !== null) {
    if ($invitation['site_id'] === null) {
        $message = "Ce code ouvre un compte, pas un projet : il attend quelqu'un qui n'en a pas "
            . 'encore. Il reste valable — rien n\'a été utilisé.';
        $invitation = null;
    } else {
        $projet = $sites->parId((int) $invitation['site_id']);
        if ($projet === null) {
            $message = "Le projet de cette invitation n'existe plus.";
            $invitation = null;
        } elseif (($role = $sites->roleSur((int) $projet['id'], $compteId)) !== null) {
            // Vérifié AVANT de consommer : un code ne se brûle pas pour rien.
            $message = sprintf('Vous avez déjà accès à « %s » (%s). Le code reste valable, rien n\'a été utilisé.',
                $projet['nom'], $role);
            $classe = 'info';
            $invitation = null;
        }
    }
}

if ($invitation !== null && $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'rejoindre') {
    if (!$auth->jetonValide($_POST['csrf'] ?? null)) {
        $message = 'Formulaire expiré — rechargez la page.';
    } elseif (!$invitations->consommer((int) $invitation['id'], $compteId)) {
        $message = "Cette invitation vient de servir à quelqu'un d'autre.";
        $invitation = null;
    } else {
        // Le code est consommé : le rattachement est la seule suite. S'il
        // échoue (projet supprimé à l'instant), on le dit sans détour.
        $entre = false;
        try {
            $entre = $sites->rejoindre((int) $projet['id'], $compteId,
                (string) $invitation['role_site'], $auth->nomAffiche() ?? 'Participant');
        } catch (\Throwable $e) {
            $entre = false;
        }
        if ($entre) {
            $sites->activer((int) $projet['id'], $compteId);
            $classe = 'succes';
            $fait = true;
            $message = sprintf('Vous êtes dans « %s », %s.', $projet['nom'],
                $dit[$invitation['role_site']] ?? 'dans le projet');
        } else {
            $message = "L'invitation a servi, mais le projet n'a pas pu vous être ouvert. "
                . 'Demandez une nouvelle invitation à qui vous a invité.';
            $invitation = null;
        }
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Rejoindre un projet — Syntropia</title>
<link rel="stylesheet" href="assets/css/gabarit.css">
<style>
  :root{ --haut-bandeaux: 3.4rem; }

  .rj-centre{ flex:1; display:flex; align-items:center; justify-content:center; padding:1.4rem 1.5rem; overflow:hidden; }
  .rj-cadre{ width:100%; max-width:23rem; }
  .rj-cadre h1{ font:600 1.25rem var(--titre); color:var(--accent-titre); margin:0 0 .5rem; text-align:center; }
  .rj-chapeau{ font:400 .85rem/1.4 var(--texte); color:var(--muted); text-align:center; margin:0 0 1rem; }
  .rj-champ{ margin-bottom:.8rem; }
  .rj-champ label{ display:block; font:500 .82rem var(--titre); color:var(--ink); margin-bottom:.25rem; }
  .rj-champ input{
    width:100%; padding:.5rem .65rem; font:400 .95rem var(--texte); color:var(--ink);
    background:var(--surface); border:1px solid var(--line); border-radius:.5rem;
    text-transform:uppercase; letter-spacing:.25em; text-align:center;
  }
  .rj-champ input:focus{ outline:2px solid var(--accent); outline-offset:1px; }
  .rj-bouton{
    width:100%; padding:.6rem; border:0; border-radius:.6rem;
    background:var(--accent); color:var(--bouton-encre); font:600 .92rem var(--titre); cursor:pointer;
  }
  .rj-bouton:hover{ opacity:.92; }
  .rj-message{
    background:var(--accent-fond); border:1px solid var(--alerte); color:var(--alerte);
    border-radius:.5rem; padding:.55rem .75rem; font-size:.85rem; margin-bottom:1rem;
  }
  .rj-message--succes, .rj-message--info{ border-color:var(--accent); color:var(--accent-titre); }
  .rj-projet{
    border:1px solid var(--accent); background:var(--accent-fond); border-radius:.6rem;
    padding:.7rem .85rem; margin-bottom:.9rem; text-align:center;
  }
  .rj-projet strong{ display:block; font:600 1.1rem var(--titre); color:var(--accent-titre); }
  .rj-projet span{ display:block; font:400 .84rem/1.4 var(--texte); color:var(--ink); margin-top:.2rem; }
  .rj-note{ font:400 .78rem/1.3 var(--texte); color:var(--muted); text-align:center; margin-top:.8rem; }
  .rj-lien{ text-align:center; }
  .rj-lien a{ color:var(--accent-titre); font:600 .95rem var(--titre); text-decoration:none; }
  .rj-lien a:hover{ text-decoration:underline; }

  @media (max-width:30rem){
    .rj-centre{ padding:.6rem 1rem; align-items:flex-start; }
    .rj-chapeau{ display:none; }
  }
  @media (max-height:38rem){ .rj-chapeau{ display:none; } }
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
      <span class="bg-centre-titre">Rejoindre un projet</span>
    </div>
    <div class="bg-droite">
      <button class="theme-bascule" id="theme-bascule" aria-label="Changer de thème" title="Thème">
        <span class="theme-icone" id="theme-icone" aria-hidden="true">◐</span>
      </button>
      <div class="compte-conteneur">
        <button class="compte" id="compte-bouton" type="button" aria-haspopup="true" aria-expanded="false" aria-controls="compte-menu">
          <span class="compte-avatar"><?= htmlspecialchars(mb_strtoupper(mb_substr($auth->nomAffiche() ?? '', 0, 2)), ENT_QUOTES, 'UTF-8') ?></span>
          <span class="compte-nom"><?= htmlspecialchars($auth->nomAffiche() ?? '', ENT_QUOTES, 'UTF-8') ?></span>
        </button>
        <div class="compte-menu" id="compte-menu">
          <a class="compte-menu-item" href="conception-parcelles.php"><span class="compte-menu-puce">▤</span>Mon projet</a>
          <a class="compte-menu-item a-venir" href="compte.php" tabindex="-1" aria-disabled="true"><span class="compte-menu-puce">⚙</span>Préférences</a>
          <div class="compte-menu-separateur"></div>
          <a class="compte-menu-item" href="deconnexion.php"><span class="compte-menu-puce">⏻</span>Se déconnecter</a>
        </div>
      </div>
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
            <li><a href="deconnexion.php"><span class="pg-puce">⏻</span><span class="pg-libelle">Se déconnecter</span></a></li>
          </ul>
        </div>
        <p class="pg-pied">Un code reçu, un projet qui vous attend.</p>
      </div>
    </nav>

    <main class="bureau" id="bureau">
      <div class="rj-centre">
        <div class="rj-cadre">
          <h1>Rejoindre un projet</h1>

<?php if ($message !== null): ?>
          <p class="rj-message rj-message--<?= $h($classe) ?>"><?= $h($message) ?></p>
<?php endif; ?>

<?php if ($fait): ?>
          <p class="rj-lien"><a href="conception-parcelles.php">Ouvrir le projet →</a></p>
          <p class="rj-note">Vos autres projets restent où ils sont : le sélecteur du panneau de gauche
             permet de passer de l'un à l'autre.</p>

<?php elseif ($invitation !== null): ?>
          <div class="rj-projet">
            <strong>« <?= $h($projet['nom']) ?> »</strong>
            <span>Invitation de <?= $h($invitation['parrain']) ?><?= ($invitation['intention'] ?? null) !== null ? ' — ' . $h($invitation['intention']) : '' ?>.<br>
              Vous le rejoindriez <?= $h($dit[$invitation['role_site']] ?? 'dans le projet') ?>.</span>
          </div>
          <form method="post">
            <input type="hidden" name="csrf" value="<?= $h($auth->jeton()) ?>">
            <input type="hidden" name="action" value="rejoindre">
            <input type="hidden" name="code" value="<?= $h($code) ?>">
            <button type="submit" class="rj-bouton">Rejoindre ce projet</button>
          </form>
          <p class="rj-note">Le code ne sert qu'une fois. Vos autres projets ne changent pas.</p>

<?php else: ?>
          <p class="rj-chapeau">Quelqu'un vous a donné un code de huit caractères pour entrer dans son
             projet. Il se dicte à voix haute : sans I, ni O, ni zéro, ni un.</p>
          <form method="get">
            <div class="rj-champ">
              <label for="code">Le code</label>
              <input id="code" name="code" required maxlength="8" autocomplete="off" autofocus
                     value="<?= $h($code) ?>">
            </div>
            <button type="submit" class="rj-bouton">Continuer</button>
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
      <p>Un code reçu d'un porteur de projet vous y donne accès — en contribuant (vous
         pouvez écrire) ou en lisant (vous regardez seulement), selon ce qu'il ou elle a choisi.
         Le projet s'ajoute aux vôtres.</p>
      <p>On vous montre le projet et votre rôle avant de confirmer. Un code qui ouvre seulement
         un compte, ou un projet auquel vous avez déjà accès, n'est pas utilisé.</p>
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
