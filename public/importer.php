<?php

declare(strict_types=1);

/**
 * Importer un projet — un fichier d'export devient un projet neuf.
 *
 * Chantier `chantier-export-import-projet.md`, Phase 2. Ouverte à tout compte connecté, comme « Nouveau
 * projet » : l'import crée **toujours un projet neuf** dont celui qui importe devient le porteur — il ne
 * touche à aucun projet existant. Hors parcours (`etapes.js`), comme `exporter.php` et `membres.php`.
 *
 * Trois temps, dont deux écrans :
 *   1. **Vérifier** — on envoie le fichier ; `Import::examiner()` le contrôle en entier (un fichier modifié,
 *      d'une version incompatible ou mal formé est refusé, avec la raison), sans rien écrire ;
 *   2. **Apercevoir** — le fichier est valide, on montre ce qu'il contient avant d'écrire (son nom, sa date
 *      d'export, ses versions, ce qu'il porte, ce qu'il ne porte pas). On y choisit le nom du projet, et qui
 *      l'on est parmi les participants ;
 *   3. **Créer** — `Import::creer()`, une transaction : tout ou rien.
 * Entre le 1 et le 2, le fichier attend dans la session ; il est **examiné de nouveau** à la confirmation.
 */

require __DIR__ . '/../src/autoload.php';
require __DIR__ . '/../src/Configuration.php';
require __DIR__ . '/../src/Database.php';

use Syntropia\Configuration;
use Syntropia\Role;
use Syntropia\Web\Authentification;
use Syntropia\Web\Export;
use Syntropia\Web\Import;
use Syntropia\Web\Sites;

Authentification::demarrer($_SERVER);
$auth = new Authentification();
$auth->exige(Role::Utilisateur);

$compteId = (int) $auth->compteId();
$sites = new Sites();
$import = new Import();
$retour = Configuration::base() . 'importer.php';

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$redirige = static function (string $url): never {
    header('Location: ' . $url);
    exit;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$auth->jetonValide($_POST['csrf'] ?? null)) {
        $_SESSION['syntropia_flash_import'] = ['erreur', ['Le formulaire a expiré. Rechargez la page.']];
        $redirige($retour);
    }
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'annuler') {
        unset($_SESSION['syntropia_import']);
        $redirige($retour);
    }

    if ($action === 'verifier') {
        unset($_SESSION['syntropia_import']);
        $envoi = $_FILES['fichier'] ?? null;
        if (!is_array($envoi) || ($envoi['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $_SESSION['syntropia_flash_import'] = ['erreur', ['Choisissez d’abord un fichier.']];
        } elseif ($envoi['error'] === UPLOAD_ERR_INI_SIZE || $envoi['error'] === UPLOAD_ERR_FORM_SIZE
            || ($envoi['size'] ?? 0) > Export::MAX_OCTETS) {
            $_SESSION['syntropia_flash_import'] = ['erreur', ['Ce fichier dépasse 1 Mo : ce n’est pas un export de Syntropia.']];
        } elseif ($envoi['error'] !== UPLOAD_ERR_OK || !is_uploaded_file((string) $envoi['tmp_name'])) {
            $_SESSION['syntropia_flash_import'] = ['erreur', ['Le fichier n’a pas pu être reçu. Réessayez.']];
        } else {
            $json = (string) file_get_contents((string) $envoi['tmp_name']);
            $examen = $import->examiner($json);
            if ($examen['ok']) {
                $_SESSION['syntropia_import'] = ['json' => $json, 'fichier' => mb_substr(basename((string) $envoi['name']), 0, 120)];
            } else {
                $_SESSION['syntropia_flash_import'] = ['erreur', $examen['erreurs']];
            }
        }
        $redirige($retour);
    }

    if ($action === 'confirmer') {
        $attente = $_SESSION['syntropia_import'] ?? null;
        if (!is_array($attente) || !is_string($attente['json'] ?? null)) {
            $_SESSION['syntropia_flash_import'] = ['erreur', ['Aucun fichier en attente : envoyez-le de nouveau.']];
            $redirige($retour);
        }
        // Ce qui a attendu dans la session est examiné de nouveau : on n'écrit que ce qui vient d'être vérifié.
        $examen = $import->examiner($attente['json']);
        if (!$examen['ok']) {
            unset($_SESSION['syntropia_import']);
            $_SESSION['syntropia_flash_import'] = ['erreur', $examen['erreurs']];
            $redirige($retour);
        }
        $nom = trim((string) ($_POST['nom'] ?? ''));
        $moi = (string) ($_POST['participant'] ?? '');
        try {
            $siteId = $import->creer($examen['plan'], $examen['apercu'], $compteId, $nom, $moi !== '' ? $moi : null);
        } catch (\RuntimeException $e) {
            $_SESSION['syntropia_flash_import'] = ['erreur', [$e->getMessage()]];   // le fichier reste en attente
            $redirige($retour);
        } catch (\Throwable $e) {
            error_log('Import : ' . $e->getMessage());
            unset($_SESSION['syntropia_import']);
            $_SESSION['syntropia_flash_import'] = ['erreur', ['L’import a échoué et n’a rien laissé : réessayez dans un instant.']];
            $redirige($retour);
        }
        unset($_SESSION['syntropia_import']);
        $sites->activer($siteId, $compteId);
        $_SESSION['syntropia_flash_import'] = ['fait', ['nom' => $nom, 'inventaire' => $examen['apercu']['inventaire']]];
        $redirige($retour);
    }

    $redirige($retour);
}

$flash = $_SESSION['syntropia_flash_import'] ?? null;
unset($_SESSION['syntropia_flash_import']);
$attente = $_SESSION['syntropia_import'] ?? null;

// Le deuxième écran : ce que le fichier en attente contient, examiné de nouveau.
$apercu = null;
if (is_array($attente) && is_string($attente['json'] ?? null)) {
    $examen = $import->examiner($attente['json']);
    if ($examen['ok']) {
        $apercu = $examen['apercu'];
    } else {
        unset($_SESSION['syntropia_import']);
        $flash = ['erreur', $examen['erreurs']];
    }
}

$nomAffiche = $auth->nomAffiche() ?? '';
$libelles = [
    'participants' => ['participant', 'participants'], 'reponses' => ['réponse', 'réponses'],
    'parcelles' => ['parcelle', 'parcelles'], 'vegetaux' => ['végétal du scénario', 'végétaux des scénarios'],
    'traces' => ['tracé de la carte', 'tracés de la carte'], 'releves_sol' => ['relevé de sol', 'relevés de sol'],
    'interventions' => ['intervention', 'interventions'], 'constats' => ['constat du saut du chat', 'constats du saut du chat'], 'celebrations' => ['célébration', 'célébrations'],
    'ressources' => ['ressource', 'ressources'], 'gisements' => ['gisement', 'gisements'],
    'commandes' => ['commande', 'commandes'], 'especes_locales' => ['espèce du projet', 'espèces du projet'],
];
$puces = static function (array $inventaire) use ($libelles, $h): string {
    $o = '';
    foreach ($libelles as $cle => [$un, $plusieurs]) {
        $n = (int) ($inventaire[$cle] ?? 0);
        if ($n === 0 && !in_array($cle, ['participants', 'reponses', 'parcelles'], true)) {
            continue;
        }
        $o .= '<li><b>' . $n . '</b> ' . $h($n > 1 ? $plusieurs : $un) . '</li>';
    }
    return $o;
};
$dateFr = static function (string $iso): string {
    try {
        return (new DateTimeImmutable($iso))->setTimezone(new DateTimeZone('Europe/Paris'))->format('d/m/Y à H:i');
    } catch (Throwable) {
        return $iso;
    }
};

// Le nom proposé : celui du fichier — sauf si l'on a déjà un projet de ce nom.
$nomPropose = '';
$participantPropose = '';
if ($apercu !== null) {
    $nomPropose = $apercu['nom'];
    foreach ($sites->deCompte($compteId) as $s) {
        if (mb_strtolower((string) $s['nom']) === mb_strtolower($nomPropose)) {
            $nomPropose .= ' (import du ' . date('d/m/Y') . ')';
            break;
        }
    }
    foreach ($apercu['participants'] as $ref => $nom) {
        if (mb_strtolower(trim($nom)) === mb_strtolower(trim($nomAffiche))) {
            $participantPropose = (string) $ref;
        }
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Importer un projet — Syntropia</title>
<link rel="stylesheet" href="assets/css/gabarit.css?v=<?= (int) @filemtime(__DIR__ . '/assets/css/gabarit.css') ?>">
<style>
  :root{ --haut-bandeaux: 3.4rem; }

  /* le bureau centre son contenu : ici le cadre remplit la place, pour pouvoir se réduire au lieu de déborder */
  #bureau{ align-items:stretch; }
  .im-centre{ flex:1; min-height:0; display:flex; align-items:center; justify-content:center; padding:1rem 1.5rem; overflow:hidden; }
  .im-cadre{ width:100%; max-width:34rem; }
  .im-cadre h1{ font:600 1.25rem var(--titre); color:var(--accent-titre); margin:0 0 .3rem; text-align:center; }
  .im-chapeau{ font:400 .84rem/1.4 var(--texte); color:var(--muted); text-align:center; margin:0 0 .8rem; }
  .im-bloc{ border:1px solid var(--line); border-radius:.6rem; padding:.55rem .8rem; margin-bottom:.6rem; background:var(--surface); }
  .im-bloc h2{ font:600 .82rem var(--titre); color:var(--accent-titre); margin:0 0 .35rem; }
  .im-bloc p{ margin:.2rem 0; font:400 .8rem/1.4 var(--texte); color:var(--muted); }
  .im-puces{ list-style:none; margin:0; padding:0; display:flex; flex-wrap:wrap; gap:.3rem .4rem; }
  .im-puces li{ font:400 .8rem var(--texte); color:var(--ink); border:1px solid var(--line); border-radius:.45rem; padding:.12rem .5rem; }
  .im-puces li b{ font:600 .82rem var(--titre); color:var(--accent-titre); }
  .im-cadre label.im-champ{ display:block; font:500 .78rem var(--titre); color:var(--ink); margin:.5rem 0 0; }
  .im-cadre input[type=text], .im-cadre select{
    display:block; width:100%; margin-top:.15rem; padding:.36rem .5rem; font:400 .84rem var(--texte); color:var(--ink);
    background:var(--surface); border:1px solid var(--line); border-radius:.45rem;
  }
  .im-cadre input:focus, .im-cadre select:focus{ outline:2px solid var(--accent); outline-offset:1px; }
  .im-fichier{ position:absolute; opacity:0; pointer-events:none; width:1px; height:1px; }
  .im-choisir{
    display:block; text-align:center; cursor:pointer; padding:.7rem .8rem; border:1px dashed var(--accent); border-radius:.6rem;
    font:500 .84rem var(--titre); color:var(--accent-titre); background:var(--accent-fond); word-break:break-all;
  }
  .im-actions{ display:flex; align-items:center; gap:.8rem; justify-content:center; margin-top:.7rem; flex-wrap:wrap; }
  .im-bouton{
    font:600 .85rem var(--titre); color:var(--bouton-encre); background:var(--accent); border:0; border-radius:.5rem;
    padding:.5rem 1.1rem; cursor:pointer; text-decoration:none;
  }
  .im-bouton:disabled{ opacity:.45; cursor:default; }
  .im-bouton--calme{ background:transparent; color:var(--accent-titre); border:1px solid var(--accent); }
  .im-message{
    border-radius:.5rem; padding:.5rem .75rem; font:400 .82rem/1.4 var(--texte); margin:0 0 .6rem;
    background:var(--accent-fond); border:1px solid var(--accent); color:var(--accent-titre);
  }
  .im-message--erreur{ border-color:var(--alerte); color:var(--alerte); }
  .im-message ul{ margin:.2rem 0 0 1rem; padding:0; }
  .im-cadre form{ margin:0; }

  @media (max-width:30rem){
    .im-centre{ padding:2.2rem 1rem .5rem; align-items:flex-start; }   /* 2.2rem : la place de l'onglet « Informations » */
    .im-chapeau{ display:none; }
  }
  @media (max-height:38rem){ .im-chapeau{ display:none; } .im-bloc{ padding:.4rem .7rem; margin-bottom:.4rem; } }
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
      <span class="bg-centre-titre">Importer un projet</span>
    </div>
    <div class="bg-droite">
      <button class="theme-bascule" id="theme-bascule" aria-label="Changer de thème" title="Thème">
        <span class="theme-icone" id="theme-icone" aria-hidden="true">◐</span>
      </button>
      <div class="compte-conteneur">
        <button class="compte" id="compte-bouton" type="button" aria-haspopup="true" aria-expanded="false" aria-controls="compte-menu">
          <span class="compte-avatar"><?= $h(mb_strtoupper(mb_substr($nomAffiche, 0, 2))) ?></span>
          <span class="compte-nom"><?= $h($nomAffiche) ?></span>
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
        <p class="pg-pied">Un projet exporté redevient un projet, le vôtre.</p>
      </div>
    </nav>

    <main class="bureau" id="bureau">
      <div class="im-centre">
        <div class="im-cadre">
          <h1>Importer un projet</h1>
<?php if (is_array($flash) && $flash[0] === 'fait'): $f = $flash[1]; ?>
          <p class="im-message" role="status">Le projet « <?= $h($f['nom']) ?> » est importé, et c’est maintenant votre projet actif.</p>
          <div class="im-bloc">
            <h2>Ce qui est arrivé</h2>
            <ul class="im-puces"><?= $puces($f['inventaire']) ?></ul>
          </div>
          <div class="im-actions">
            <a class="im-bouton" href="conception-parcelles.php">Ouvrir le projet →</a>
            <a class="im-bouton im-bouton--calme" href="importer.php">Importer un autre fichier</a>
          </div>
<?php elseif ($apercu !== null): ?>
<?php if (is_array($flash) && $flash[0] === 'erreur'): ?>
          <div class="im-message im-message--erreur" role="alert"><?= $h($flash[1][0] ?? '') ?></div>
<?php endif; ?>
          <div class="im-bloc">
            <h2>Ce que ce fichier contient</h2>
            <ul class="im-puces"><?= $puces($apercu['inventaire']) ?></ul>
            <p>Exporté le <?= $h($dateFr($apercu['exporte_le'])) ?> · Syntropia <?= $h($apercu['versions']['application'] ?? '?') ?>, schéma <?= $h($apercu['versions']['schema'] ?? '?') ?>.
               Les dates d’origine sont gardées. Ni compte ni accès : les participants entrent sans compte.</p>
          </div>
          <form method="post" action="importer.php">
            <input type="hidden" name="csrf" value="<?= $h($auth->jeton()) ?>">
            <input type="hidden" name="action" value="confirmer">
            <label class="im-champ">Nom du projet
              <input type="text" name="nom" maxlength="160" required value="<?= $h($nomPropose) ?>">
            </label>
            <label class="im-champ">Dans ce fichier, vous êtes…
              <select name="participant">
                <option value="">Personne de la liste — un nouveau participant</option>
<?php foreach ($apercu['participants'] as $ref => $nom): ?>
                <option value="<?= $h($ref) ?>"<?= $participantPropose === (string) $ref ? ' selected' : '' ?>><?= $h($nom) ?></option>
<?php endforeach; ?>
              </select>
            </label>
            <div class="im-actions">
              <button type="submit" class="im-bouton">Créer le projet</button>
            </div>
          </form>
          <form method="post" action="importer.php" class="im-actions" style="margin-top:.4rem">
            <input type="hidden" name="csrf" value="<?= $h($auth->jeton()) ?>">
            <input type="hidden" name="action" value="annuler">
            <button type="submit" class="im-bouton im-bouton--calme">Annuler</button>
          </form>
<?php else: ?>
          <p class="im-chapeau">Un fichier <strong>.syntropia.json</strong>, exporté depuis l’atelier, devient un projet neuf dont vous
             êtes le porteur. Il est vérifié en entier avant que rien ne soit écrit.</p>
<?php if (is_array($flash) && $flash[0] === 'erreur'): ?>
          <div class="im-message im-message--erreur" role="alert">
            <strong>Ce fichier n’a pas été chargé.</strong>
            <ul><?php foreach (array_slice($flash[1], 0, 6) as $e): ?><li><?= $h($e) ?></li><?php endforeach; ?>
<?php if (count($flash[1]) > 6): ?><li>… et <?= count($flash[1]) - 6 ?> autre<?= count($flash[1]) - 6 > 1 ? 's' : '' ?> — corrigez ceux-ci d’abord, ou exportez de nouveau le projet.</li><?php endif; ?></ul>
          </div>
<?php endif; ?>
          <form method="post" action="importer.php" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= $h($auth->jeton()) ?>">
            <input type="hidden" name="action" value="verifier">
            <input class="im-fichier" type="file" name="fichier" id="im-fichier" accept=".json,application/json" required>
            <label class="im-choisir" for="im-fichier" id="im-libelle">Choisir un fichier .syntropia.json</label>
            <div class="im-actions">
              <button type="submit" class="im-bouton" id="im-verifier" disabled>Vérifier le fichier</button>
            </div>
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
      <p>L’import crée <strong>toujours un projet neuf</strong> : il ne fusionne avec rien et n’écrase rien. Vous en
         devenez le porteur. Le fichier vient de la page « Exporter le projet » ; on l’envoie ici, on regarde ce qu’il
         contient, puis on crée le projet.</p>
      <p><strong>Le fichier est vérifié en entier avant que rien ne soit écrit.</strong> Il ne se charge pas s’il a été
         modifié depuis son export (même d’une lettre), s’il vient d’un atelier dont les données n’ont pas la même
         forme, ou si une valeur n’est pas ce que l’atelier accepte — la page dit alors laquelle. Et si l’écriture
         échoue en route, rien n’est laissé à moitié.</p>
      <p><strong>Les participants entrent sans compte</strong> : le fichier n’en porte aucun. Vous dites lequel d’entre eux
         vous êtes, et le projet retrouve alors vos réponses ; sinon vous arrivez comme un nouveau participant. Pour donner
         accès à d’autres, invitez-les ensuite, comme pour n’importe quel projet.</p>
      <p>Les dates d’origine — création, réponses, arrivée des participants — sont gardées. La date d’import est
         notée dans le projet, avec le nom et la date d’export du fichier.</p>
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

  // Le bureau ne défile jamais : si le cadre déborde (petit écran, beaucoup d'erreurs), il se réduit.
  (function(){
    var cadre = document.querySelector('.im-cadre'), bureau = document.getElementById('bureau');
    function ajuster(){
      cadre.style.zoom = 1;
      var z = 1;
      while (z > 0.55 && cadre.getBoundingClientRect().bottom > bureau.getBoundingClientRect().bottom - 2) {
        z = Math.round((z - 0.05) * 100) / 100; cadre.style.zoom = z;
      }
    }
    ajuster();
    window.addEventListener('resize', ajuster);
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(ajuster);
  })();

  // Le nom du fichier choisi, et le bouton qui s'active.
  (function(){
    var champ = document.getElementById('im-fichier');
    if (!champ) return;
    champ.addEventListener('change', function(){
      var f = champ.files && champ.files[0];
      document.getElementById('im-libelle').textContent = f ? f.name : 'Choisir un fichier .syntropia.json';
      document.getElementById('im-verifier').disabled = !f;
      window.dispatchEvent(new Event('resize'));
    });
  })();
</script>
</body>
</html>
