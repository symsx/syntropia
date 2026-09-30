<?php

declare(strict_types=1);

/**
 * Préférences — le compte : ce qu'on sait de soi, ses projets, et deux
 * réglages (le nom affiché, le mot de passe).
 *
 * Le lien existait, grisé, dans le menu du compte depuis le début ; et
 * `Authentification::exige()` renvoie déjà ici (`?mdp=1`) un compte dont le
 * mot de passe doit changer. **Le courriel est en lecture seule** : rien
 * n'envoie de message ni ne vérifie une adresse, la changer ici ne serait
 * qu'une saisie sans preuve. Trois onglets en CSS pur — un panneau à la fois,
 * pour qu'aucun ne défile.
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
$auth->exige(Role::Utilisateur);

$compteId = (int) $auth->compteId();
$comptes = new Comptes();
$compte = $comptes->parId($compteId);
if ($compte === null) {
    header('Location: ' . Configuration::base() . 'connexion.php');
    exit;
}

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$doitChanger = $auth->doitChangerMotDePasse();

$onglet = in_array($_GET['onglet'] ?? '', ['recap', 'nom', 'mdp'], true) ? $_GET['onglet'] : 'recap';
if ($doitChanger || isset($_GET['mdp'])) {
    $onglet = 'mdp';
}
$message = null;   // [onglet, 'ok'|'erreur', texte]

$flash = $_SESSION['syntropia_flash_compte'] ?? null;
unset($_SESSION['syntropia_flash_compte']);
if (is_array($flash)) {
    [$onglet, $genre, $texte] = $flash;
    $message = [$onglet, $genre, $texte];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if (!$auth->jetonValide($_POST['csrf'] ?? null)) {
        $message = [$action === 'mdp' ? 'mdp' : 'nom', 'erreur', 'Formulaire expiré — rechargez la page.'];
        $onglet = $message[0];
    } elseif ($action === 'nom') {
        $onglet = 'nom';
        $nom = trim((string) ($_POST['nom_affiche'] ?? ''));
        if ($nom === '') {
            $message = ['nom', 'erreur', "Le nom affiché est nécessaire : c'est ce que les autres verront."];
        } elseif (mb_strlen($nom) > 120) {
            $message = ['nom', 'erreur', 'Le nom affiché fait 120 caractères au plus.'];
        } else {
            $comptes->changerNomAffiche($compteId, $nom);
            $auth->rafraichirNomAffiche($nom);
            $_SESSION['syntropia_flash_compte'] = ['nom', 'ok', 'Nom affiché mis à jour.'];
            header('Location: ' . Configuration::base() . 'compte.php');
            exit;
        }
    } elseif ($action === 'mdp') {
        $onglet = 'mdp';
        $adresse = Authentification::adresse($_SERVER);
        $identifiant = (string) $compte['identifiant'];
        $actuel = (string) ($_POST['actuel'] ?? '');
        $nouveau = (string) ($_POST['nouveau'] ?? '');
        $delai = $comptes->delaiAvantTentative($identifiant, $adresse);
        if ($delai > 0) {
            $message = ['mdp', 'erreur', "Trop de tentatives. Réessayez dans {$delai} secondes."];
        } elseif (!$comptes->motDePasseCorrespond($compteId, $actuel)) {
            // Une session volée ne doit pas pouvoir deviner le mot de passe
            // en changer : chaque échec compte, comme à la connexion.
            $comptes->enregistrerEchec($identifiant, $adresse);
            $message = ['mdp', 'erreur', "Le mot de passe actuel n'est pas le bon."];
        } elseif (mb_strlen($nouveau) < 12) {
            $message = ['mdp', 'erreur', "Le nouveau mot de passe fait au moins 12 caractères. Une phrase vaut mieux qu'un mot."];
        } elseif ($nouveau !== (string) ($_POST['confirmer'] ?? '')) {
            $message = ['mdp', 'erreur', 'Les deux saisies du nouveau mot de passe diffèrent.'];
        } elseif ($nouveau === $actuel) {
            $message = ['mdp', 'erreur', "Le nouveau mot de passe est le même que l'actuel."];
        } else {
            $comptes->changerMotDePasse($compteId, $nouveau);
            $comptes->oublierTentatives($identifiant, $adresse);
            $auth->motDePasseChange();
            session_regenerate_id(true);
            $_SESSION['syntropia_flash_compte'] = ['mdp', 'ok', 'Mot de passe changé.'];
            header('Location: ' . Configuration::base() . ($doitChanger ? 'conception-parcelles.php' : 'compte.php'));
            exit;
        }
    }
}

$roleCompte = $auth->role();
$sitesService = new Sites();
$projets = $sitesService->deCompte($compteId);
$actif = $sitesService->pourCompte($compteId);
$parrain = (new Invitations())->parrainDe($compteId);
$date = static fn (?string $d): string => $d === null || $d === '' ? '—' : date('d/m/Y', (int) strtotime($d));
$libelleRole = ['porteur' => 'porteur', 'contributeur' => 'contribue', 'lecteur' => 'lit seulement'];
const PROJETS_VISIBLES = 3;
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Préférences — Syntropia</title>
<link rel="stylesheet" href="assets/css/gabarit.css">
<style>
  :root{ --haut-bandeaux: 3.4rem; }

  .cp-centre{ flex:1; display:flex; align-items:center; justify-content:center; padding:1rem 1.5rem; overflow:hidden; }
  .cp-cadre{ width:100%; max-width:27rem; }
  .cp-cadre h1{ font:600 1.25rem var(--titre); color:var(--accent-titre); margin:0 0 .6rem; text-align:center; }
  .cp-radio{ position:absolute; opacity:0; pointer-events:none; }
  .cp-onglets{ display:flex; gap:.3rem; margin-bottom:.8rem; border-bottom:1px solid var(--line); }
  .cp-onglet{
    flex:1; text-align:center; padding:.4rem .3rem; cursor:pointer; border-radius:.5rem .5rem 0 0;
    font:500 .8rem var(--titre); color:var(--muted); border:1px solid transparent; border-bottom:0;
  }
  .cp-onglet:hover{ color:var(--accent-titre); }
  .cp-cadre:has(#o-recap:checked) label[for=o-recap],
  .cp-cadre:has(#o-nom:checked) label[for=o-nom],
  .cp-cadre:has(#o-mdp:checked) label[for=o-mdp]{
    color:var(--accent-titre); background:var(--accent-fond); border-color:var(--line); font-weight:600;
  }
  .cp-cadre:has(.cp-radio:focus-visible) .cp-onglets{ outline:2px solid var(--accent); outline-offset:2px; }
  .cp-panneau{ display:none; }
  .cp-cadre:has(#o-recap:checked) .cp-recap,
  .cp-cadre:has(#o-nom:checked) .cp-nom,
  .cp-cadre:has(#o-mdp:checked) .cp-mdp{ display:block; }

  .cp-liste{ margin:0 0 .7rem; }
  .cp-liste div{ display:flex; gap:.6rem; padding:.28rem 0; border-top:1px solid var(--line); font:400 .85rem/1.25 var(--texte); }
  .cp-liste dt{ flex:none; width:7.5rem; color:var(--muted); }
  .cp-liste dd{ margin:0; color:var(--ink); min-width:0; overflow:hidden; text-overflow:ellipsis; }
  .cp-titre{ font:500 .7rem var(--titre); text-transform:uppercase; letter-spacing:.07em; color:var(--muted); margin:.7rem 0 .25rem; }
  .cp-projets{ list-style:none; margin:0; padding:0; }
  .cp-projets li{ display:flex; gap:.5rem; padding:.28rem 0; border-top:1px solid var(--line); font:400 .85rem/1.25 var(--texte); }
  .cp-projets li span:first-child{ flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .cp-projets small{ color:var(--muted); font-size:.74rem; white-space:nowrap; }
  .cp-actif{ font-weight:600; color:var(--accent-titre); }
  .cp-liens{ display:flex; gap:1rem; justify-content:center; margin-top:.6rem; font:400 .82rem var(--texte); }
  .cp-liens a{ color:var(--accent-titre); font-weight:600; text-decoration:none; }
  .cp-champ{ margin-bottom:.6rem; }
  .cp-champ label{ display:block; font:600 .82rem var(--titre); color:var(--ink); margin-bottom:.2rem; }
  .cp-champ small{ display:block; font:400 .72rem/1.3 var(--texte); color:var(--muted); margin-bottom:.2rem; }
  .cp-champ input{
    width:100%; padding:.42rem .6rem; font:400 .9rem var(--texte); color:var(--ink);
    background:var(--surface); border:1px solid var(--line); border-radius:.5rem;
  }
  .cp-champ input:focus{ outline:2px solid var(--accent); outline-offset:1px; }
  .cp-bouton{
    width:100%; padding:.55rem; border:0; border-radius:.6rem;
    background:var(--accent); color:var(--bouton-encre); font:600 .9rem var(--titre); cursor:pointer;
  }
  .cp-bouton:hover{ opacity:.92; }
  .cp-message{ border-radius:.5rem; padding:.45rem .7rem; font-size:.84rem; margin-bottom:.6rem;
    background:var(--accent-fond); border:1px solid var(--accent); color:var(--accent-titre); }
  .cp-message--erreur{ border-color:var(--alerte); color:var(--alerte); }
  .cp-note{ font:400 .78rem/1.35 var(--texte); color:var(--muted); margin:.3rem 0 0; }

  /* écran peu haut : le titre répète celui du bandeau — il s'efface avant
     que le contenu ne soit tronqué (rien ne défile) */
  @media (max-height:44rem){
    .cp-cadre h1{ display:none; }
  }
  @media (max-width:30rem){
    .cp-centre{ padding:.5rem 1rem; align-items:flex-start; }
    .cp-liste dt{ width:6rem; }
    .cp-champ{ margin-bottom:.4rem; }
    .cp-cadre h1{ font-size:1.05rem; margin-bottom:.4rem; }
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
      <span class="bg-centre-titre">Préférences</span>
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
        <p class="pg-pied">Votre compte, et ses réglages.</p>
      </div>
    </nav>

    <main class="bureau" id="bureau">
      <div class="cp-centre">
        <div class="cp-cadre">
          <h1>Préférences</h1>
          <input class="cp-radio" type="radio" name="onglet" id="o-recap"<?= $onglet === 'recap' ? ' checked' : '' ?>>
          <input class="cp-radio" type="radio" name="onglet" id="o-nom"<?= $onglet === 'nom' ? ' checked' : '' ?>>
          <input class="cp-radio" type="radio" name="onglet" id="o-mdp"<?= $onglet === 'mdp' ? ' checked' : '' ?>>
          <div class="cp-onglets" role="tablist">
            <label class="cp-onglet" for="o-recap">Mon compte</label>
            <label class="cp-onglet" for="o-nom">Nom affiché</label>
            <label class="cp-onglet" for="o-mdp">Mot de passe</label>
          </div>

          <section class="cp-panneau cp-recap">
<?php if ($message !== null && $message[0] === 'recap'): ?>
            <p class="cp-message"><?= $h($message[2]) ?></p>
<?php endif; ?>
            <dl class="cp-liste">
              <div><dt>Nom affiché</dt><dd><?= $h($compte['nom_affiche']) ?></dd></div>
              <div><dt>Identifiant</dt><dd><?= $h($compte['identifiant']) ?></dd></div>
              <div><dt>Courriel</dt><dd><?= $compte['courriel'] !== null ? $h($compte['courriel']) : '—' ?></dd></div>
              <div><dt>Rôle</dt><dd><?= $h($roleCompte?->libelle() ?? '—') ?></dd></div>
              <div><dt>Inscrit le</dt><dd><?= $h($date($compte['cree_le'])) ?></dd></div>
<?php if ($parrain !== null): ?>
              <div><dt>Arrivé par</dt><dd>l'invitation de <?= $h($parrain['parrain']) ?> (<?= $h($date($parrain['utilise_le'])) ?>)</dd></div>
<?php endif; ?>
            </dl>
            <p class="cp-titre">Vos projets · <?= count($projets) ?></p>
<?php if ($projets === []): ?>
            <p class="cp-note">Aucun pour l'instant.</p>
<?php else: ?>
            <ul class="cp-projets">
<?php foreach (array_slice($projets, 0, PROJETS_VISIBLES) as $p): $estActif = $actif !== null && (int) $actif['id'] === (int) $p['id']; ?>
              <li><span<?= $estActif ? ' class="cp-actif"' : '' ?>><?= $estActif ? '● ' : '' ?><?= $h($p['nom']) ?></span>
                  <small><?= $h($libelleRole[$p['role']] ?? $p['role']) ?></small></li>
<?php endforeach; ?>
<?php if (count($projets) > PROJETS_VISIBLES): ?>
              <li><span class="cp-note">et <?= count($projets) - PROJETS_VISIBLES ?> autre<?= count($projets) - PROJETS_VISIBLES > 1 ? 's' : '' ?> — dans « Mes projets », panneau de gauche.</span></li>
<?php endif; ?>
            </ul>
<?php endif; ?>
            <p class="cp-liens"><a href="rejoindre.php">Rejoindre un projet</a><a href="inviter.php">Inviter</a></p>
          </section>

          <section class="cp-panneau cp-nom">
<?php if ($message !== null && $message[0] === 'nom'): ?>
            <p class="cp-message<?= $message[1] === 'erreur' ? ' cp-message--erreur' : '' ?>"><?= $h($message[2]) ?></p>
<?php endif; ?>
            <form method="post" action="compte.php">
              <input type="hidden" name="csrf" value="<?= $h($auth->jeton()) ?>">
              <input type="hidden" name="action" value="nom">
              <div class="cp-champ">
                <label for="nom_affiche">Nom affiché</label>
                <small>C'est ce que les autres voient — dans la liste des participants, par exemple.</small>
                <input id="nom_affiche" name="nom_affiche" required maxlength="120" autocomplete="name"
                       value="<?= $h($_POST['nom_affiche'] ?? $compte['nom_affiche']) ?>">
              </div>
              <button type="submit" class="cp-bouton">Enregistrer le nom</button>
            </form>
            <p class="cp-note">Dans un projet déjà rejoint, le nom que vous portiez en y entrant reste celui de vos
               réponses ; c'est le nom affiché qui apparaît dans la liste des participants.</p>
          </section>

          <section class="cp-panneau cp-mdp">
<?php if ($doitChanger): ?>
            <p class="cp-message cp-message--erreur">Choisissez un nouveau mot de passe avant de continuer.</p>
<?php endif; ?>
<?php if ($message !== null && $message[0] === 'mdp'): ?>
            <p class="cp-message<?= $message[1] === 'erreur' ? ' cp-message--erreur' : '' ?>"><?= $h($message[2]) ?></p>
<?php endif; ?>
            <form method="post" action="compte.php">
              <input type="hidden" name="csrf" value="<?= $h($auth->jeton()) ?>">
              <input type="hidden" name="action" value="mdp">
              <div class="cp-champ">
                <label for="actuel">Mot de passe actuel</label>
                <input id="actuel" name="actuel" type="password" required autocomplete="current-password">
              </div>
              <div class="cp-champ">
                <label for="nouveau">Nouveau mot de passe</label>
                <small>Douze caractères au minimum — une phrase vaut mieux qu'un mot.</small>
                <input id="nouveau" name="nouveau" type="password" required minlength="12" autocomplete="new-password">
              </div>
              <div class="cp-champ">
                <label for="confirmer">Confirmer</label>
                <input id="confirmer" name="confirmer" type="password" required minlength="12" autocomplete="new-password">
              </div>
              <button type="submit" class="cp-bouton">Changer le mot de passe</button>
            </form>
          </section>
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
      <p><strong>Mon compte</strong> réunit ce que l'atelier sait de vous : qui vous êtes, par qui vous
         êtes arrivé, et les projets auxquels vous avez accès, avec votre rôle sur chacun.</p>
      <p>Le <strong>nom affiché</strong> est celui que les autres voient. Le <strong>mot de passe</strong>
         se change en donnant l'actuel : une session ouverte par quelqu'un d'autre ne suffit pas à vous
         en fermer l'accès. Le courriel n'est pas modifiable ici : rien ne l'envoie ni ne le vérifie.</p>
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
