<?php

declare(strict_types=1);

/**
 * Inviter — ouvrir un code de parrainage.
 *
 * Chantier `chantier-inscription-parrainage.md`, Phase 2. Deux gestes, un
 * seul code (table `invitation`) : inviter quelqu'un à **créer un compte**
 * (elle ouvrira son propre projet), ou à **rejoindre le projet actif** — ce
 * que seul le porteur peut faire, en choisissant le rôle donné. Tout
 * utilisateur connecté peut inviter à créer un compte. Page hors parcours
 * (`etapes.js`), comme `definition-projet.php`.
 *
 * Les garde-fous vivent dans `Invitations::ouvrir()` (porteur, plafond de
 * codes ouverts) : cette page ne fait que montrer ce qu'il refuse.
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
$site = (new Sites())->pourCompte($compteId);
$estPorteur = $site !== null && ($site['role'] ?? '') === 'porteur';

const PAR_PAGE = 3;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$erreur = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$auth->jetonValide($_POST['csrf'] ?? null)) {
        $erreur = 'Le formulaire a expiré. Rechargez la page.';
    } elseif (($_POST['action'] ?? '') === 'revoquer') {
        $invitations->revoquer((int) ($_POST['id'] ?? 0), $compteId);
        header('Location: ' . Configuration::base() . 'inviter.php');
        exit;
    } elseif (($_POST['action'] ?? '') === 'ouvrir') {
        $pourProjet = ($_POST['pour'] ?? '') === 'projet' && $estPorteur;
        try {
            $code = $invitations->ouvrir(
                $compteId,
                $pourProjet ? (int) $site['id'] : null,
                (string) ($_POST['role_site'] ?? 'contributeur'),
                (string) ($_POST['intention'] ?? ''));
            // Lu une seule fois à l'affichage suivant : un code ne reste
            // pas dans l'historique du navigateur.
            $_SESSION['syntropia_code_ouvert'] = $code;
            header('Location: ' . Configuration::base() . 'inviter.php');
            exit;
        } catch (\DomainException $e) {
            $erreur = $e->getMessage();
        }
    }
}

$codeOuvert = $_SESSION['syntropia_code_ouvert'] ?? null;
unset($_SESSION['syntropia_code_ouvert']);

$total = $invitations->nombreDUnParrain($compteId);
$pages = max(1, (int) ceil($total / PAR_PAGE));
$page = max(1, min($pages, (int) ($_GET['page'] ?? 1)));
$liste = $invitations->pageDUnParrain($compteId, PAR_PAGE, ($page - 1) * PAR_PAGE);

$sitesParId = [];
$sitesService = new Sites();
foreach ($liste as $i) {
    $sid = $i['site_id'] !== null ? (int) $i['site_id'] : 0;
    if ($sid > 0 && !isset($sitesParId[$sid])) {
        $sitesParId[$sid] = (string) (($sitesService->parId($sid))['nom'] ?? 'un projet');
    }
}
$ouvertes = $invitations->nombreOuvertes($compteId);
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Inviter — Syntropia</title>
<link rel="stylesheet" href="assets/css/gabarit.css?v=<?= (int) @filemtime(__DIR__ . '/assets/css/gabarit.css') ?>">
<style>
  :root{ --haut-bandeaux: 3.4rem; }

  .inv-centre{ flex:1; display:flex; align-items:center; justify-content:center; padding:1rem 1.5rem; overflow:hidden; }
  .inv-cadre{ width:100%; max-width:27rem; }
  .inv-cadre h1{ font:600 1.25rem var(--titre); color:var(--accent-titre); margin:0 0 .35rem; text-align:center; }
  .inv-chapeau{ font:400 .84rem/1.4 var(--texte); color:var(--muted); text-align:center; margin:0 0 .8rem; }
  .inv-groupe{ border:0; padding:0; margin:0 0 .6rem; }
  .inv-groupe legend{ font:600 .82rem var(--titre); color:var(--ink); padding:0; margin-bottom:.25rem; }
  .inv-choix{ display:flex; flex-direction:column; gap:.3rem; }
  .inv-choix label{ display:flex; align-items:flex-start; gap:.45rem; font:400 .84rem/1.3 var(--texte); color:var(--ink); }
  .inv-choix label small{ display:block; color:var(--muted); font-size:.74rem; }
  .inv-champ{ margin-bottom:.6rem; }
  .inv-champ label{ display:block; font:600 .82rem var(--titre); color:var(--ink); margin-bottom:.2rem; }
  .inv-champ input[type=text], .inv-champ select{
    width:100%; padding:.42rem .6rem; font:400 .88rem var(--texte); color:var(--ink);
    background:var(--surface); border:1px solid var(--line); border-radius:.5rem;
  }
  .inv-champ input:focus, .inv-champ select:focus{ outline:2px solid var(--accent); outline-offset:1px; }
  .inv-role{ margin:.2rem 0 0 1.5rem; }
  .inv-bouton{
    width:100%; padding:.55rem; border:0; border-radius:.6rem;
    background:var(--accent); color:var(--bouton-encre); font:600 .9rem var(--titre); cursor:pointer;
  }
  .inv-bouton:hover{ opacity:.92; }
  .inv-erreur{
    background:var(--accent-fond); border:1px solid var(--alerte); color:var(--alerte);
    border-radius:.5rem; padding:.5rem .75rem; font-size:.84rem; margin-bottom:.7rem;
  }
  .inv-code{
    border:1px solid var(--accent); background:var(--accent-fond); border-radius:.6rem;
    padding:.55rem .75rem; margin-bottom:.8rem; text-align:center;
  }
  .inv-code strong{ display:block; font:600 1.5rem var(--titre); letter-spacing:.25em; color:var(--accent-titre); }
  .inv-code small{ display:block; font:400 .74rem/1.3 var(--texte); color:var(--muted); margin-top:.15rem; }
  .inv-code input{
    width:100%; margin-top:.35rem; padding:.3rem .5rem; font:400 .76rem var(--texte); color:var(--ink);
    background:var(--surface); border:1px solid var(--line); border-radius:.4rem; text-align:center;
  }
  .inv-titre{ font:500 .7rem var(--titre); text-transform:uppercase; letter-spacing:.07em; color:var(--muted); margin:.9rem 0 .3rem; }
  .inv-liste{ list-style:none; margin:0; padding:0; }
  .inv-liste li{
    display:flex; align-items:center; gap:.5rem; padding:.3rem 0; border-top:1px solid var(--line);
    font:400 .8rem/1.25 var(--texte); color:var(--ink);
  }
  .inv-liste .inv-ligne-code{ font:600 .82rem var(--titre); letter-spacing:.12em; }
  .inv-liste .inv-ligne-texte{ flex:1; min-width:0; }
  .inv-liste .inv-ligne-texte small{ display:block; color:var(--muted); font-size:.72rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .inv-etat{ font:500 .72rem var(--titre); color:var(--muted); }
  .inv-etat--ouverte{ color:var(--accent-titre); }
  .inv-retirer{
    padding:.2rem .5rem; border:1px solid var(--line); border-radius:.4rem; background:transparent;
    color:var(--muted); font:500 .72rem var(--titre); cursor:pointer;
  }
  .inv-retirer:hover{ border-color:var(--alerte); color:var(--alerte); }
  .inv-pages{ display:flex; justify-content:center; align-items:center; gap:.8rem; margin-top:.35rem; font:400 .78rem var(--texte); color:var(--muted); }
  .inv-pages a{ color:var(--accent-titre); text-decoration:none; font-weight:600; }
  .inv-vide{ font:400 .8rem var(--texte); color:var(--muted); text-align:center; margin:.2rem 0; }

  /* écran peu haut : rien ne défile, le texte d'accompagnement s'efface
     avant que le formulaire ne soit tronqué */
  @media (max-height:44rem){
    .inv-chapeau{ display:none; }
    .inv-choix label small{ display:none; }
  }
  @media (max-width:30rem){
    .inv-centre{ padding:.5rem 1rem; align-items:flex-start; }
    .inv-chapeau{ display:none; }
    .inv-cadre h1{ font-size:1.05rem; margin-bottom:.4rem; }
    .inv-groupe, .inv-champ{ margin-bottom:.35rem; }
    .inv-titre{ margin-top:.5rem; }
  }
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
      <span class="bg-centre-titre">Inviter</span>
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
          <a class="compte-menu-item" href="compte.php"><span class="compte-menu-puce">⚙</span>Préférences</a>
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
            <li><a href="conception-parcelles.php"><span class="pg-puce">▤</span><span class="pg-libelle">Mon projet</span></a></li>
            <li><a href="deconnexion.php"><span class="pg-puce">⏻</span><span class="pg-libelle">Se déconnecter</span></a></li>
          </ul>
        </div>
        <p class="pg-pied">Un code se donne de vive voix, ou par écrit.</p>
      </div>
    </nav>

    <main class="bureau" id="bureau">
      <div class="inv-centre">
        <div class="inv-cadre">
          <h1>Inviter</h1>
          <p class="inv-chapeau">L'inscription se fait sur invitation, avec un code de huit caractères.
             Le code est valable <?= Invitations::JOURS ?> jours et sert une seule fois. Il se donne de vive
             voix ou par écrit.</p>

<?php if ($codeOuvert !== null): ?>
          <div class="inv-code" id="inv-code">
            <strong><?= $h($codeOuvert) ?></strong>
            <small>Voici le code. Le lien ci-dessous l'ouvre directement sur l'inscription.</small>
            <input type="text" id="inv-lien" readonly value="inscription.php?code=<?= $h($codeOuvert) ?>" aria-label="Lien d'inscription">
          </div>
<?php endif; ?>
<?php if ($erreur !== null): ?>
          <p class="inv-erreur"><?= $h($erreur) ?></p>
<?php endif; ?>

          <form method="post">
            <input type="hidden" name="csrf" value="<?= $h($auth->jeton()) ?>">
            <input type="hidden" name="action" value="ouvrir">
            <fieldset class="inv-groupe">
              <legend>Inviter à…</legend>
              <div class="inv-choix">
                <label><input type="radio" class="radio" name="pour" value="compte" checked>
                  <span>Créer un compte<small>La personne ouvrira son propre projet.</small></span></label>
<?php if ($estPorteur): ?>
                <label><input type="radio" class="radio" name="pour" value="projet">
                  <span>Rejoindre « <?= $h($site['nom']) ?> »<small>Elle pourra aussi ouvrir son propre projet.</small></span></label>
<?php endif; ?>
              </div>
<?php if ($estPorteur): ?>
              <div class="inv-champ inv-role" id="inv-role" hidden>
                <label for="role_site">Avec le rôle</label>
                <select id="role_site" name="role_site">
                  <option value="contributeur">Contributeur : répond aux questions et dessine</option>
                  <option value="lecteur">Lecteur : consulte sans modifier</option>
                </select>
              </div>
<?php elseif ($site !== null): ?>
              <p class="inv-vide">Seul le porteur de « <?= $h($site['nom']) ?> » peut y inviter.</p>
<?php endif; ?>
            </fieldset>
            <div class="inv-champ">
              <label for="intention">Un mot pour vous y retrouver <small style="font-weight:400;color:var(--muted)">(facultatif)</small></label>
              <input type="text" id="intention" name="intention" maxlength="190" placeholder="Ex. pour la parcelle du bas">
            </div>
            <button type="submit" class="inv-bouton">Ouvrir une invitation</button>
          </form>

          <p class="inv-titre">Vos invitations · <?= $ouvertes ?> ouverte<?= $ouvertes > 1 ? 's' : '' ?> sur <?= Invitations::MAX_OUVERTES ?></p>
<?php if ($codeOuvert !== null): ?>
          <p class="inv-vide"><a href="inviter.php" style="color:var(--accent-titre);font-weight:600">Voir la liste de vos invitations</a></p>
<?php elseif ($liste === []): ?>
          <p class="inv-vide">Aucune pour l'instant.</p>
<?php else: ?>
          <ul class="inv-liste">
<?php foreach ($liste as $i): $etat = $invitations->etat($i); ?>
            <li>
              <span class="inv-ligne-code"><?= $h($i['code']) ?></span>
              <span class="inv-ligne-texte">
                <?= $i['site_id'] !== null ? 'Projet « ' . $h($sitesParId[(int) $i['site_id']] ?? 'un projet') . ' », ' . $h($i['role_site'] ?? '') : 'Compte seul' ?>
                <small><?= $h($i['intention'] ?? '') ?><?= $etat === 'utilisée' && $i['filleul'] !== null ? ', ' . $h($i['filleul']) : '' ?></small>
              </span>
<?php if ($etat === 'ouverte'): ?>
              <form method="post">
                <input type="hidden" name="csrf" value="<?= $h($auth->jeton()) ?>">
                <input type="hidden" name="action" value="revoquer">
                <input type="hidden" name="id" value="<?= (int) $i['id'] ?>">
                <button type="submit" class="inv-retirer">Retirer</button>
              </form>
<?php else: ?>
              <span class="inv-etat"><?= $h($etat) ?></span>
<?php endif; ?>
            </li>
<?php endforeach; ?>
          </ul>
<?php if ($pages > 1): ?>
          <p class="inv-pages">
            <?= $page > 1 ? '<a href="inviter.php?page=' . ($page - 1) . '">‹ Plus récentes</a>' : '<span></span>' ?>
            <span><?= $page ?> / <?= $pages ?></span>
            <?= $page < $pages ? '<a href="inviter.php?page=' . ($page + 1) . '">Plus anciennes ›</a>' : '<span></span>' ?>
          </p>
<?php endif; ?>
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
      <p>Avec <strong>Créer un compte</strong>, la personne s'inscrit avec le code, puis ouvre son
         propre projet. Avec <strong>Rejoindre un projet</strong>, elle rejoint aussi le vôtre, comme
         contributeur ou comme lecteur. Le contributeur répond aux questions et dessine avec le porteur.
         Le lecteur consulte le projet sans le modifier. Seul le porteur d'un projet peut y inviter. Dans
         les deux cas, la personne peut ouvrir son propre projet.</p>
      <p>Un code sert une seule fois, expire après <?= Invitations::JOURS ?> jours, et peut être retiré
         tant qu'il n'a pas servi. Le lien contient le code : le donner revient à donner la clé.</p>
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

  // Le lien complet se compose ici, depuis l'adresse réelle de la page — le
  // serveur n'a pas à deviner son nom de domaine.
  (function(){
    var lien = document.getElementById('inv-lien');
    if (lien) {
      lien.value = new URL(lien.value, window.location.href).href;
      lien.addEventListener('focus', function(){ lien.select(); });
    }
    // Le rôle ne se choisit que pour une invitation à un projet.
    var role = document.getElementById('inv-role');
    if (role) {
      document.querySelectorAll('input[name=pour]').forEach(function(r){
        r.addEventListener('change', function(){
          role.hidden = document.querySelector('input[name=pour]:checked').value !== 'projet';
        });
      });
    }
  })();
</script>
</body>
</html>
