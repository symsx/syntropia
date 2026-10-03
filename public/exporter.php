<?php

declare(strict_types=1);

/**
 * Exporter le projet — un fichier JSON daté qui porte tout ce que le projet contient.
 *
 * Chantier `chantier-export-import-projet.md`, Phase 1. Réservée au porteur du projet actif :
 * le fichier porte les réponses de **tous** les participants, ce n'est pas un droit à donner
 * à un contributeur ou à un lecteur. Hors parcours (`etapes.js`), comme `membres.php`.
 *
 * La page annonce ce que le fichier contient et ne contient pas, avant de le produire. Le
 * téléchargement est un POST (jeton), pour qu'aucun lien ne puisse déclencher un export.
 * Tout se passe dans `Export` : cette page ne fait que l'annoncer, le servir, ou dire
 * pourquoi elle ne le sert pas.
 */

require __DIR__ . '/../src/autoload.php';
require __DIR__ . '/../src/Configuration.php';
require __DIR__ . '/../src/Database.php';

use Syntropia\Configuration;
use Syntropia\Role;
use Syntropia\Web\Authentification;
use Syntropia\Web\Export;
use Syntropia\Web\Sites;

Authentification::demarrer($_SERVER);
$auth = new Authentification();
$auth->exige(Role::Utilisateur);

$compteId = (int) $auth->compteId();
$sites = new Sites();
$site = $sites->pourCompte($compteId);
if ($site === null) {
    header('Location: ' . Configuration::base() . 'definition-projet.php');
    exit;
}
$siteId = (int) $site['id'];
$estPorteur = ($site['role'] ?? '') === 'porteur';

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$export = new Export();

/** Une réponse d'erreur en JSON, pour la page qui a demandé le fichier. */
$refuser = static function (int $statut, string $message, array $en_plus = []): never {
    http_response_code($statut);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(['erreur' => $message] + $en_plus, JSON_UNESCAPED_UNICODE);
    exit;
};

/** Ce qui pèse trop, dit en clair. */
$nomPartie = [
    'projet' => 'la fiche du projet', 'participants' => 'les participants', 'reponses' => 'les réponses',
    'syntheses' => 'les synthèses', 'ressources' => 'les ressources', 'gisements' => 'les gisements',
    'commandes' => 'les commandes', 'especes_locales' => 'les espèces du projet',
    'parcelles' => 'les parcelles', 'calques' => 'les tracés de la carte',
];
$partsLourdes = static function (array $parts) use ($nomPartie): string {
    $mots = [];
    foreach (array_slice($parts, 0, 3, true) as $cle => $octets) {
        if (isset($nomPartie[$cle])) {
            $mots[] = $nomPartie[$cle] . ' (' . number_format($octets / 1024, 0, ',', "\u{202F}") . ' Ko)';
        }
    }
    return implode(', ', $mots);
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$estPorteur) {
        $refuser(403, 'Seul le porteur du projet peut l’exporter.');
    }
    if (!$auth->jetonValide($_POST['csrf'] ?? null)) {
        $refuser(403, 'Le formulaire a expiré. Rechargez la page.');
    }
    $doc = $export->construire($siteId);
    if ($doc === null) {
        $refuser(404, 'Ce projet est introuvable.');
    }
    if (($desaccord = Export::accordCodeEtBase($doc['versions']['schema'])) !== null) {
        $refuser(409, $desaccord);
    }
    $fichier = $export->encoder($doc);
    if (!$fichier['ok']) {
        $refuser(413, 'Le projet dépasse ce qu’un fichier d’export peut porter (1 Mo) : il ne pourrait pas être réimporté. '
            . 'Ce qui pèse le plus : ' . $partsLourdes($fichier['parts']) . '.', ['octets' => $fichier['octets']]);
    }
    $nom = Export::nomDeFichier((string) $doc['projet']['nom'], (string) $doc['exporte_le']);
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nom . '"');
    header('X-Syntropia-Exporte-Le: ' . $doc['exporte_le']);
    header('X-Syntropia-Fichier: ' . $nom);
    header('Cache-Control: no-store');
    header('Content-Length: ' . strlen($fichier['json']));
    echo $fichier['json'];
    exit;
}

// Ce que le fichier contiendrait, annoncé avant qu'on le demande.
$inventaire = [];
$octets = 0;
$trop = null;
$desaccord = null;
$versions = null;
if ($estPorteur) {
    $doc = $export->construire($siteId);
    if ($doc !== null) {
        $inventaire = Export::inventaire($doc);
        $versions = $doc['versions'];
        $desaccord = Export::accordCodeEtBase($versions['schema']);
        $fichier = $export->encoder($doc);
        $octets = $fichier['octets'];
        if (!$fichier['ok']) {
            $trop = $partsLourdes($fichier['parts']);
        }
    }
}
$libelles = [
    'participants' => ['participant', 'participants'], 'reponses' => ['réponse', 'réponses'],
    'parcelles' => ['parcelle', 'parcelles'], 'vegetaux' => ['végétal du scénario', 'végétaux des scénarios'],
    'traces' => ['tracé de la carte', 'tracés de la carte'], 'releves_sol' => ['relevé de sol', 'relevés de sol'],
    'interventions' => ['intervention', 'interventions'], 'constats' => ['constat du saut du chat', 'constats du saut du chat'], 'celebrations' => ['célébration', 'célébrations'],
    'ressources' => ['ressource', 'ressources'], 'gisements' => ['gisement', 'gisements'],
    'commandes' => ['commande', 'commandes'], 'especes_locales' => ['espèce du projet', 'espèces du projet'],
];
$taille = $octets >= 1024 ? number_format($octets / 1024, 0, ',', "\u{202F}") . ' Ko' : $octets . ' octets';
$nomAffiche = $auth->nomAffiche() ?? '';
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Exporter le projet — Syntropia</title>
<link rel="stylesheet" href="assets/css/gabarit.css?v=<?= (int) @filemtime(__DIR__ . '/assets/css/gabarit.css') ?>">
<style>
  :root{ --haut-bandeaux: 3.4rem; }

  /* le bureau centre son contenu : ici le cadre remplit la place, pour pouvoir se réduire au lieu de déborder */
  #bureau{ align-items:stretch; }
  .ex-centre{ flex:1; min-height:0; display:flex; align-items:center; justify-content:center; padding:1rem 1.5rem; overflow:hidden; }
  .ex-cadre{ width:100%; max-width:32rem; }
  .ex-cadre h1{ font:600 1.25rem var(--titre); color:var(--accent-titre); margin:0 0 .3rem; text-align:center; }
  .ex-chapeau{ font:400 .84rem/1.4 var(--texte); color:var(--muted); text-align:center; margin:0 0 .8rem; }
  .ex-bloc{ border:1px solid var(--line); border-radius:.6rem; padding:.55rem .8rem; margin-bottom:.6rem; background:var(--surface); }
  .ex-bloc h2{ font:600 .82rem var(--titre); color:var(--accent-titre); margin:0 0 .35rem; }
  .ex-puces{ list-style:none; margin:0; padding:0; display:flex; flex-wrap:wrap; gap:.3rem .4rem; }
  .ex-puces li{ font:400 .8rem var(--texte); color:var(--ink); border:1px solid var(--line); border-radius:.45rem; padding:.12rem .5rem; }
  .ex-puces li b{ font:600 .82rem var(--titre); color:var(--accent-titre); }
  .ex-pas{ margin:0; font:400 .8rem/1.4 var(--texte); color:var(--muted); }
  .ex-actions{ display:flex; align-items:center; gap:.8rem; justify-content:center; margin-top:.7rem; flex-wrap:wrap; }
  .ex-bouton{
    font:600 .85rem var(--titre); color:var(--bouton-encre); background:var(--accent); border:0; border-radius:.5rem;
    padding:.5rem 1.1rem; cursor:pointer;
  }
  .ex-bouton:disabled{ opacity:.45; cursor:default; }
  .ex-taille{ font:400 .78rem var(--texte); color:var(--muted); }
  .ex-message{
    border-radius:.5rem; padding:.5rem .75rem; font:400 .84rem/1.4 var(--texte); margin-top:.7rem;
    background:var(--accent-fond); border:1px solid var(--accent); color:var(--accent-titre);
  }
  .ex-message--erreur{ border-color:var(--alerte); color:var(--alerte); }
  .ex-vide{ font:400 .85rem/1.4 var(--texte); color:var(--muted); text-align:center; }
  [hidden]{ display:none !important; }

  @media (max-width:30rem){
    .ex-centre{ padding:2.2rem 1rem .5rem; align-items:flex-start; }   /* 2.2rem : la place de l'onglet « Informations » */
    .ex-chapeau{ display:none; }
  }
  @media (max-height:38rem){ .ex-chapeau{ display:none; } .ex-bloc{ padding:.4rem .7rem; margin-bottom:.4rem; } }
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
      <span class="bg-centre-titre">Exporter le projet</span>
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
        <p class="pg-pied">Une copie datée du projet, qui se garde et se redonne.</p>
      </div>
    </nav>

    <main class="bureau" id="bureau">
      <div class="ex-centre">
        <div class="ex-cadre">
          <h1>Exporter le projet</h1>
<?php if (!$estPorteur): ?>
          <p class="ex-vide">L'export de « <?= $h($site['nom']) ?> » se demande à son porteur :
             le fichier porte les réponses de tous les participants.</p>
<?php else: ?>
          <p class="ex-chapeau">Une copie de « <?= $h($site['nom']) ?> » dans un fichier daté, à garder ou à
             redonner. Vous la téléchargez ici ; elle ne change rien au projet.</p>

          <div class="ex-bloc">
            <h2>Le fichier contiendra</h2>
            <ul class="ex-puces">
<?php foreach ($libelles as $cle => [$un, $plusieurs]): $n = $inventaire[$cle] ?? 0; if ($n === 0 && !in_array($cle, ['participants', 'reponses', 'parcelles'], true)) { continue; } ?>
              <li><b><?= $n ?></b> <?= $h($n > 1 ? $plusieurs : $un) ?></li>
<?php endforeach; ?>
            </ul>
          </div>
          <div class="ex-bloc">
            <h2>Il ne contiendra jamais</h2>
            <p class="ex-pas">Aucun compte (ni identifiant, ni courriel, ni mot de passe), aucun accès au projet, aucune
               invitation. Les participants n'y figurent que par leur nom.</p>
          </div>

<?php if ($desaccord !== null): ?>
          <p class="ex-message ex-message--erreur" role="alert"><?= $h($desaccord) ?></p>
<?php endif; ?>
<?php if ($trop !== null): ?>
          <p class="ex-message ex-message--erreur" role="alert">Ce projet dépasse ce qu'un fichier d'export peut porter
             (1 Mo) : il ne pourrait pas être réimporté, il n'est donc pas produit. Ce qui pèse le plus :
             <?= $h($trop) ?>.</p>
<?php endif; ?>
          <div class="ex-actions">
            <button type="button" class="ex-bouton" id="ex-telecharger"<?= $trop !== null || $desaccord !== null ? ' disabled' : '' ?>>Télécharger le fichier</button>
            <span class="ex-taille">≈ <?= $h($taille) ?><?= $versions !== null ? ' · Syntropia ' . $h($versions['application']) . ', schéma ' . $h($versions['schema']) : '' ?></span>
          </div>
          <p class="ex-message" id="ex-resultat" role="status" hidden></p>
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
      <p>Le fichier <strong>.syntropia.json</strong> est une copie du projet à l'instant où vous le demandez :
         sa <strong>date d'export</strong> est dans le nom du fichier et dans son contenu. Il garde le projet tel
         que vous le voyez — définition, terrain, parcelles et leur plan, scénarios, relevés, tracés de la carte —
         et les réponses de <strong>tous</strong> les participants, d'où la réserve au porteur.</p>
      <p>Il ne porte <strong>aucun compte ni aucun accès</strong> : qui peut voir le projet se décide là où le
         fichier arrive, pas dans le fichier. Le fichier se lit dans un éditeur de texte ; ne le modifiez pas à la main.</p>
      <p>Le fichier <strong>dit d'où il vient</strong> : la forme du fichier, celle des données (le « schéma ») et le
         numéro de l'atelier qui l'a écrit. Il porte aussi une <strong>somme de contrôle</strong> : un fichier dont une
         seule lettre a changé depuis son export ne se recharge pas, et un fichier venu d'un atelier dont les données
         n'ont pas la même forme non plus.</p>
      <p>Un projet qui dépasserait 1 Mo n'est pas exporté : le fichier ne pourrait pas être réimporté, et
         une sauvegarde qu'on ne peut pas rouvrir n'en est pas une. La page dit alors ce qui pèse.</p>
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

  // Le bureau ne défile jamais : si le cadre déborde (petit écran, message d'erreur en plus), il se réduit.
  (function(){
    var cadre = document.querySelector('.ex-cadre'), bureau = document.getElementById('bureau');
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

  (function(){
    var bouton = document.getElementById('ex-telecharger');
    var dit = document.getElementById('ex-resultat');
    if (!bouton) return;
    function afficher(texte, erreur){
      dit.textContent = texte;
      dit.hidden = false;
      dit.classList.toggle('ex-message--erreur', !!erreur);
      window.dispatchEvent(new Event('resize'));      // le message ajoute de la hauteur : le cadre se réajuste
    }
    bouton.addEventListener('click', function(){
      bouton.disabled = true;
      var corps = new FormData();
      corps.append('csrf', <?= json_encode($auth->jeton()) ?>);
      fetch('exporter.php', { method: 'POST', body: corps, credentials: 'same-origin' })
        .then(function(r){
          if (!r.ok) {
            return r.json().then(function(j){ throw new Error(j.erreur || 'L’export a échoué.'); },
                                 function(){ throw new Error('L’export a échoué (réponse illisible).'); });
          }
          var nom = r.headers.get('X-Syntropia-Fichier') || 'projet.syntropia.json';
          var quand = r.headers.get('X-Syntropia-Exporte-Le');
          return r.blob().then(function(b){ return { blob: b, nom: nom, quand: quand }; });
        })
        .then(function(f){
          var url = URL.createObjectURL(f.blob);
          var a = document.createElement('a');
          a.href = url; a.download = f.nom;
          document.body.appendChild(a); a.click(); a.remove();
          setTimeout(function(){ URL.revokeObjectURL(url); }, 2000);
          var d = f.quand ? new Date(f.quand) : new Date();
          afficher('Exporté le ' + d.toLocaleDateString('fr-FR') + ' à '
            + d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) + ' — ' + f.nom, false);
        })
        .catch(function(e){ afficher(e.message, true); })
        .then(function(){ bouton.disabled = false; });
    });
  })();
</script>
</body>
</html>
