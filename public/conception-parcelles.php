<?php

declare(strict_types=1);

require __DIR__ . '/../src/autoload.php';
require __DIR__ . '/../src/Configuration.php';
require __DIR__ . '/../src/Database.php';

use Syntropia\Configuration;
use Syntropia\Role;
use Syntropia\Web\Authentification;
use Syntropia\Web\Parcelles;
use Syntropia\Web\Sites;

Authentification::demarrer($_SERVER);
$auth = new Authentification();
$auth->exige(Role::Utilisateur);

$sites = new Sites();
$compteId = (int) $auth->compteId();
$site = $sites->pourCompte($compteId);
if ($site === null) {
    header('Location: ' . Configuration::base() . 'definition-projet.php');
    exit;
}
$siteId = (int) $site['id'];
$parcellesService = new Parcelles();

$erreursCreation = [];
$erreursDimensions = [];
$codeSaisi = '';
$nomSaisi = '';
$typeSaisi = 'perenne';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $auth->jetonValide($_POST['csrf'] ?? null)) {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'creer') {
        $codeSaisi = (string) ($_POST['code'] ?? '');
        $nomSaisi = (string) ($_POST['nom'] ?? '');
        $typeSaisi = (string) ($_POST['type'] ?? 'perenne');
        $resultat = $parcellesService->creer($siteId, $codeSaisi, $nomSaisi, $typeSaisi);
        if ($resultat['erreurs'] === []) {
            header('Location: ' . Configuration::base() . 'conception-parcelles.php?parcelle=' . $resultat['id']);
            exit;
        }
        $erreursCreation = $resultat['erreurs'];
    } elseif ($action === 'dimensions') {
        $parcelleId = (int) ($_POST['parcelle_id'] ?? 0);
        $erreursDimensions = $parcellesService->enregistrer($parcelleId, $siteId, [
            'nb_planches'       => $_POST['nb_planches'] ?? '',
            'largeur_planche_m' => $_POST['largeur_planche_m'] ?? '',
            'passe_pied_m'      => $_POST['passe_pied_m'] ?? '',
            'longueur_m'        => $_POST['longueur_m'] ?? '',
        ]);
        if ($erreursDimensions === []) {
            header('Location: ' . Configuration::base() . 'conception-parcelles.php?parcelle=' . $parcelleId);
            exit;
        }
    }
}

$parcelles = $parcellesService->dUnSite($siteId);
$demandeNouvelle = ($_GET['nouvelle'] ?? '') === '1' || ($parcelles === [] && $erreursCreation === []);
$parcelleActive = null;
if (!$demandeNouvelle) {
    $idDemande = (int) ($_GET['parcelle'] ?? 0);
    foreach ($parcelles as $p) {
        if ((int) $p['id'] === $idDemande) {
            $parcelleActive = $p;
            break;
        }
    }
    if ($parcelleActive === null && $parcelles !== []) {
        $parcelleActive = $parcelles[0];
    }
}
if ($parcelleActive === null && $parcelles === [] && $erreursCreation !== []) {
    // Échec de création avec zéro parcelle existante : rester sur le formulaire.
    $demandeNouvelle = true;
}

$nomAffiche = $auth->nomAffiche() ?? '';
$initiales = '';
foreach (preg_split('/\s+/', trim($nomAffiche)) ?: [] as $mot) {
    if ($mot !== '') {
        $initiales .= mb_strtoupper(mb_substr($mot, 0, 1));
    }
}
$initiales = mb_substr($initiales, 0, 2) ?: '?';
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Parcelles — Syntropia</title>
<link rel="stylesheet" href="assets/css/gabarit.css">
<style>
  /* propre à cette étape : la géométrie de la parcelle, en SVG */
  .bureau-carte{ width:100%; height:100%; max-width:64rem; filter:drop-shadow(0 10px 24px var(--ombre)); }
  .plan-parcelle-fond{ fill:var(--surface); stroke:var(--line); stroke-width:2; }
  .plan-bordure{ fill:var(--accent-doux); fill-opacity:.55; stroke:var(--accent); stroke-width:2; cursor:default; }
  .outil-deplacer-actif .plan-bordure{ cursor:ns-resize; }
  .outil-deplacer-actif .plan-bordure[data-cote="ouest"],
  .outil-deplacer-actif .plan-bordure[data-cote="est"]{ cursor:ew-resize; }
  .plan-bordure:hover{ fill-opacity:.78; }
  .plan-bordure.en-glissement{ fill-opacity:.9; stroke-width:3; }
  .plan-planche{ fill:var(--socle-accent-doux); fill-opacity:.5; }
  .plan-passe-pied{ fill:transparent; }
  .plan-cote{ font:500 15px var(--titre); fill:var(--muted); }
  .plan-mesure{ font:600 13px var(--texte); fill:var(--ink); font-variant-numeric:tabular-nums; }
  .plan-fleche{ stroke:var(--muted); stroke-width:1.4; fill:none; }
  .visually-hidden{ position:absolute; width:1px; height:1px; overflow:hidden; clip:rect(0 0 0 0); white-space:nowrap; }

  /* l'état « aucune parcelle encore » — un formulaire simple, pas de SVG */
  .parc-creation{ width:100%; max-width:26rem; }
  .parc-annonce{ font:400 .92rem/1.5 var(--texte); color:var(--muted); margin:0 0 1.4rem; text-align:center; }
  .parc-champ{ margin-bottom:1rem; }
  .parc-champ label, .parc-champ legend{ display:block; font:600 .85rem var(--titre); color:var(--ink); margin-bottom:.35rem; padding:0; }
  .parc-champ small{ display:block; font:400 .78rem/1.3 var(--texte); color:var(--muted); margin-bottom:.3rem; }
  .parc-champ input[type=text]{
    width:100%; padding:.5rem .7rem; font:400 .9rem var(--texte); color:var(--ink);
    background:var(--surface); border:1px solid var(--line); border-radius:.5rem;
  }
  .parc-champ input:focus{ outline:2px solid var(--accent); outline-offset:1px; }
  .parc-choix{ display:flex; gap:1rem; }
  .parc-choix label{ display:flex; align-items:center; gap:.35rem; font:400 .86rem var(--texte); color:var(--ink); }
  .parc-erreur{
    margin:0 0 .8rem; padding:.5rem .7rem; border-radius:.5rem; background:var(--alerte);
    color:var(--bouton-encre); font:500 .82rem var(--texte);
  }
  .parc-bouton{
    width:100%; margin-top:.3rem; padding:.6rem; border:0; border-radius:.6rem;
    background:var(--accent); color:var(--bouton-encre); font:600 .92rem var(--titre); cursor:pointer;
  }
  .parc-bouton:hover{ opacity:.92; }
  .parc-annuler{ display:block; text-align:center; margin-top:.7rem; font:400 .8rem var(--texte); color:var(--muted); }

  /* les trois mesures réelles, dans le panneau droit — distinctes du
     prototype de plan au-dessus (non persisté, voir sa note) */
  .pd-reel{ margin-top:1.4rem; padding-top:1.1rem; border-top:1px dashed var(--line); }
  .pd-reel .pd-ligne input[type=text]{ width:5rem; text-align:right; }
  .pd-reel-note{ font:400 .74rem/1.35 var(--texte); color:var(--muted); margin:.5rem 0 .6rem; }
  .pd-reel-bouton{
    width:100%; margin-top:.2rem; padding:.5rem; border:0; border-radius:.5rem;
    background:var(--accent); color:var(--bouton-encre); font:600 .82rem var(--titre); cursor:pointer;
  }
  .pd-reel-bouton:hover{ opacity:.92; }
  .pd-reel-erreur{ font:400 .76rem/1.3 var(--texte); color:var(--alerte); margin:.4rem 0 0; }
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
      <button class="etape-bouton" id="etape-precedente" aria-label="Étape précédente" title="Étape précédente">
        <svg class="etape-chevron" viewBox="0 0 10 10" aria-hidden="true"><polygon points="7,1 2,5 7,9" fill="currentColor"/></svg>
      </button>
      <span class="bg-centre-titre">&nbsp;</span>
      <button class="etape-bouton" id="etape-suivante" aria-label="Étape suivante" title="Étape suivante">
        <svg class="etape-chevron" viewBox="0 0 10 10" aria-hidden="true"><polygon points="3,1 8,5 3,9" fill="currentColor"/></svg>
      </button>
    </div>
    <div class="bg-droite">
      <button class="theme-bascule" id="theme-bascule" aria-label="Changer de thème" title="Thème">
        <span class="theme-icone" id="theme-icone" aria-hidden="true">◐</span>
      </button>
      <div class="compte-conteneur">
        <button class="compte" id="compte-bouton" type="button" aria-haspopup="true" aria-expanded="false" aria-controls="compte-menu">
          <span class="compte-avatar"><?= htmlspecialchars($initiales, ENT_QUOTES, 'UTF-8') ?></span>
          <span class="compte-nom"><?= htmlspecialchars($nomAffiche, ENT_QUOTES, 'UTF-8') ?></span>
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

<?php if (!$demandeNouvelle): ?>
  <div class="bandeau-outils" role="toolbar" aria-label="Outils du plan" id="bandeau-outils">
    <button type="button" class="outils-declencheur" id="outils-declencheur" aria-haspopup="true"
            aria-expanded="false" aria-controls="outils-liste">
      <span id="outils-icone-actif" aria-hidden="true">↖</span>
      <span id="outils-texte-actif">Sélection</span>
      <svg class="outils-declencheur-chevron" viewBox="0 0 10 10" aria-hidden="true"><polygon points="1,3 5,8 9,3" fill="currentColor"/></svg>
    </button>

    <div class="outils-liste" id="outils-liste">
      <input type="radio" name="outil" id="outil-selection" checked>
      <label class="outil-bouton" for="outil-selection">↖ Sélection</label>

      <input type="radio" name="outil" id="outil-deplacer">
      <label class="outil-bouton" for="outil-deplacer">⇔ Déplacer un bord</label>

      <span class="outil-separateur"></span>

      <input type="radio" name="outil" id="outil-ligne" disabled>
      <label class="outil-bouton a-venir" for="outil-ligne" title="À venir">✎ Tracer une ligne</label>

      <input type="radio" name="outil" id="outil-gomme" disabled>
      <label class="outil-bouton a-venir" for="outil-gomme" title="À venir">⌫ Gomme</label>
    </div>
  </div>
<?php endif; ?>

  <div class="bulle" id="bulle" role="status"></div>

  <div class="corps">
    <div class="scrim" id="scrim-gauche"></div>
    <nav class="panneau-gauche" id="panneau-gauche" aria-label="Navigation de l'atelier" aria-hidden="true">
      <div class="pg-entete">
        <a class="marque-nom" href="./">Syntropia</a>
      </div>
      <div class="pg-corps">
        <div class="pg-section">
          <p class="pg-titre pg-nomsite"><?= htmlspecialchars((string) $site['nom'], ENT_QUOTES, 'UTF-8') ?></p>
          <ul class="pg-liste">
            <li><a href="#" class="a-venir" tabindex="-1" aria-disabled="true"><span class="pg-puce">⌂</span><span class="pg-libelle">Le site</span></a></li>
            <li><a href="conception-etude-site.php"><span class="pg-puce">▦</span><span class="pg-libelle">La carte</span></a></li>
          </ul>
        </div>
        <div class="pg-section">
          <p class="pg-titre">Phase projet</p>
          <!-- rempli par Gabarit.init() à partir de public/assets/js/etapes.js -->
          <ul class="pg-liste pg-phase-projet"></ul>
        </div>
        <div class="pg-section">
          <p class="pg-titre">Parcelles</p>
          <ul class="pg-liste">
<?php foreach ($parcelles as $p):
        $estActive = !$demandeNouvelle && $parcelleActive !== null && (int) $p['id'] === (int) $parcelleActive['id']; ?>
            <li><a href="conception-parcelles.php?parcelle=<?= (int) $p['id'] ?>"<?= $estActive ? ' class="active" aria-current="page"' : '' ?>><span class="pg-puce">▤</span><span class="pg-libelle"><?= htmlspecialchars((string) $p['code'], ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars((string) $p['nom'], ENT_QUOTES, 'UTF-8') ?></span></a></li>
<?php endforeach; ?>
            <li><a href="conception-parcelles.php?nouvelle=1"<?= $demandeNouvelle ? ' class="active" aria-current="page"' : '' ?>><span class="pg-puce">+</span><span class="pg-libelle">Nouvelle parcelle</span></a></li>
          </ul>
        </div>
<?php if (!$demandeNouvelle && $parcelleActive !== null): ?>
        <div class="pg-section">
          <p class="pg-titre"><?= htmlspecialchars((string) $parcelleActive['code'], ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars((string) $parcelleActive['nom'], ENT_QUOTES, 'UTF-8') ?></p>
          <ul class="pg-liste">
            <li><a href="conception-parcelles.php?parcelle=<?= (int) $parcelleActive['id'] ?>" class="active" aria-current="page"><span class="pg-puce">▤</span><span class="pg-libelle">Le plan</span></a></li>
            <li><a href="#" class="a-venir" tabindex="-1" aria-disabled="true"><span class="pg-puce">≋</span><span class="pg-libelle">Le scénario</span></a></li>
            <li><a href="#" class="a-venir" tabindex="-1" aria-disabled="true"><span class="pg-puce">✎</span><span class="pg-libelle">Le suivi</span></a></li>
          </ul>
        </div>
<?php endif; ?>
        <p class="pg-pied">Les parcelles sont réelles, en base — le plan (planches, bordures) reste un prototype, voir architecture-technique.md.</p>
      </div>
    </nav>

<?php if ($demandeNouvelle): ?>
    <main class="bureau" id="bureau">
      <div class="parc-creation">
        <p class="parc-annonce"><?= $parcelles === [] ? 'Ouvrez votre première parcelle — vous pourrez en ajouter d\'autres ensuite.' : 'Ouvrez une nouvelle parcelle.' ?></p>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($auth->jeton(), ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="action" value="creer">
<?php if ($erreursCreation !== []): ?>
          <p class="parc-erreur"><?= htmlspecialchars(implode(' ', $erreursCreation), ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
          <div class="parc-champ">
            <label for="code">Code</label>
            <small>Un repère court — A, B, P1… pour la retrouver dans les listes.</small>
            <input type="text" id="code" name="code" placeholder="A" maxlength="32"
                   value="<?= htmlspecialchars($codeSaisi, ENT_QUOTES, 'UTF-8') ?>">
          </div>
          <div class="parc-champ">
            <label for="nom">Nom</label>
            <input type="text" id="nom" name="nom" placeholder="Le verger, la butte du bas…"
                   value="<?= htmlspecialchars($nomSaisi, ENT_QUOTES, 'UTF-8') ?>">
          </div>
          <fieldset class="parc-champ">
            <legend>Type</legend>
            <div class="parc-choix">
              <label><input type="radio" class="radio" name="type" value="perenne"<?= $typeSaisi === 'perenne' ? ' checked' : '' ?>>Pérenne</label>
              <label><input type="radio" class="radio" name="type" value="potager"<?= $typeSaisi === 'potager' ? ' checked' : '' ?>>Potager</label>
            </div>
          </fieldset>
          <button type="submit" class="parc-bouton">Créer la parcelle</button>
        </form>
<?php if ($parcelles !== []): ?>
        <a class="parc-annuler" href="conception-parcelles.php?parcelle=<?= (int) $parcelles[0]['id'] ?>">Annuler, revenir à mes parcelles</a>
<?php endif; ?>
      </div>
    </main>
<?php else: ?>
    <main class="bureau" id="bureau">
      <svg class="bureau-carte" id="plan" viewBox="0 0 1240 900" role="img"
           aria-label="Plan de la parcelle <?= htmlspecialchars((string) $parcelleActive['code'], ENT_QUOTES, 'UTF-8') ?> — prototype, 12 mètres sur 8">
        <g transform="translate(70,40)">
          <rect class="plan-parcelle-fond" x="0" y="0" width="1200" height="800"/>
          <g id="planches"></g>
          <rect class="plan-bordure" data-cote="nord"  id="bordure-nord"  x="0"    y="0"   width="1200" height="40"/>
          <rect class="plan-bordure" data-cote="sud"   id="bordure-sud"   x="0"    y="760" width="1200" height="40"/>
          <rect class="plan-bordure" data-cote="ouest" id="bordure-ouest" x="0"    y="0"   width="40"   height="800"/>
          <rect class="plan-bordure" data-cote="est"   id="bordure-est"   x="1160" y="0"   width="40"   height="800"/>

          <line class="plan-fleche" id="fleche-longueur" x1="0" y1="-16" x2="1200" y2="-16" marker-start="url(#fl)" marker-end="url(#fl)"/>
          <text class="plan-mesure" id="mesure-longueur" x="600" y="-24" text-anchor="middle">12,00 m</text>
          <line class="plan-fleche" id="fleche-largeur" x1="-16" y1="0" x2="-16" y2="800" marker-start="url(#fl)" marker-end="url(#fl)"/>
          <text class="plan-mesure" id="mesure-largeur" x="-24" y="400" text-anchor="middle" transform="rotate(-90 -24 400)">8,00 m</text>

          <text class="plan-cote" x="600" y="24" text-anchor="middle">nord</text>
          <text class="plan-cote" x="600" y="792" text-anchor="middle">sud</text>
          <text class="plan-cote" x="20" y="404" text-anchor="middle" transform="rotate(-90 20 404)">ouest</text>
          <text class="plan-cote" x="1180" y="404" text-anchor="middle" transform="rotate(90 1180 404)">est</text>
        </g>
        <defs>
          <marker id="fl" viewBox="0 0 10 10" refX="5" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
            <path d="M1,1 L9,5 L1,9" fill="none" stroke="var(--muted)" stroke-width="1.6"/>
          </marker>
        </defs>
      </svg>
    </main>

    <div class="scrim" id="scrim-droit"></div>
    <button class="tirette" id="tirette" aria-expanded="false" aria-controls="panneau-droit"
            aria-label="Afficher le panneau Données">
      <svg class="tirette-triangle" viewBox="0 0 10 10" aria-hidden="true"><polygon points="1,1 9,5 1,9" fill="currentColor"/></svg>
      <span class="tirette-texte">Données</span>
    </button>
    <aside class="panneau-droit" id="panneau-droit" aria-hidden="true">
      <div class="pd-corps">
        <p class="pd-titre">La parcelle <span class="pd-unite">(m)</span></p>
        <div class="pd-ligne">
          <span class="pd-ligne-nom">Longueur (est ↔ ouest)</span>
          <input type="text" id="in-longueur" value="12,00" inputmode="decimal">
        </div>
        <div class="pd-ligne">
          <span class="pd-ligne-nom">Largeur (nord ↔ sud)</span>
          <input type="text" id="in-largeur" value="8,00" inputmode="decimal">
        </div>

        <p class="pd-titre">Sens des planches</p>
        <div class="parc-choix">
          <label><input type="radio" class="radio" name="sens-planches" id="sens-nord-sud" value="nord-sud" checked>Nord ↔ Sud</label>
          <label><input type="radio" class="radio" name="sens-planches" id="sens-est-ouest" value="est-ouest">Est ↔ Ouest</label>
        </div>

        <p class="pd-titre">Bordure externe <span class="pd-unite">(m)</span></p>
        <div class="pd-ligne">
          <label class="pd-ligne-nom"><input type="checkbox" checked data-cote="nord" class="case bordure-active">Nord</label>
          <input type="text" class="bordure-largeur" data-cote="nord" value="0,40" inputmode="decimal">
        </div>
        <div class="pd-ligne">
          <label class="pd-ligne-nom"><input type="checkbox" checked data-cote="sud" class="case bordure-active">Sud</label>
          <input type="text" class="bordure-largeur" data-cote="sud" value="0,40" inputmode="decimal">
        </div>
        <div class="pd-ligne">
          <label class="pd-ligne-nom"><input type="checkbox" checked data-cote="ouest" class="case bordure-active">Ouest</label>
          <input type="text" class="bordure-largeur" data-cote="ouest" value="0,40" inputmode="decimal">
        </div>
        <div class="pd-ligne">
          <label class="pd-ligne-nom"><input type="checkbox" checked data-cote="est" class="case bordure-active">Est</label>
          <input type="text" class="bordure-largeur" data-cote="est" value="0,40" inputmode="decimal">
        </div>

        <p class="pd-titre">Ce que la géométrie donne</p>
        <dl class="pd-resultat" id="resultat">
          <div class="pd-champ"><dt>Surface utile</dt><dd id="r-surface">—</dd></div>
          <div class="pd-champ"><dt>Planches</dt><dd id="r-planches">—</dd></div>
          <div class="pd-champ"><dt>Linéaire de bordure</dt><dd id="r-lineaire">—</dd></div>
        </dl>
        <p class="pd-note">Prototype : le calcul ci-dessus est refait ici, en JavaScript. La persistance en base (<code>parcelle.plan</code>) n'est pas encore branchée — voir architecture-technique.md.</p>

        <div class="pd-reel">
          <p class="pd-titre">Pour la carte du terrain</p>
          <p class="pd-reel-note">Ces mesures servent à poser cette parcelle sur
             <a href="conception-etude-site.php">la carte interactive du terrain</a> — sans
             longueur ni largeur, elle s'y trace à la main plutôt que par un rectangle posé
             d'un clic.</p>
          <form method="post">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($auth->jeton(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="dimensions">
            <input type="hidden" name="parcelle_id" value="<?= (int) $parcelleActive['id'] ?>">
            <div class="pd-ligne">
              <span class="pd-ligne-nom">Nombre de planches</span>
              <input type="text" name="nb_planches" inputmode="numeric"
                     value="<?= htmlspecialchars((string) ($parcelleActive['nb_planches'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="pd-ligne">
              <span class="pd-ligne-nom">Largeur d'une planche (m)</span>
              <input type="text" name="largeur_planche_m" inputmode="decimal"
                     value="<?= htmlspecialchars((string) ($parcelleActive['largeur_planche_m'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="pd-ligne">
              <span class="pd-ligne-nom">Passe-pied (m)</span>
              <input type="text" name="passe_pied_m" inputmode="decimal"
                     value="<?= htmlspecialchars((string) ($parcelleActive['passe_pied_m'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="pd-ligne">
              <span class="pd-ligne-nom">Longueur d'une planche (m)</span>
              <input type="text" name="longueur_m" inputmode="decimal"
                     value="<?= htmlspecialchars((string) ($parcelleActive['longueur_m'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            </div>
<?php if ($erreursDimensions !== []): ?>
            <p class="pd-reel-erreur"><?= htmlspecialchars(implode(' ', $erreursDimensions), ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
            <button type="submit" class="pd-reel-bouton">Enregistrer ces mesures</button>
          </form>
        </div>
      </div>
    </aside>
<?php endif; ?>
  </div>

  <div class="scrim" id="scrim-info"></div>
  <button class="tirette-info" id="tirette-info" aria-expanded="false" aria-controls="panneau-info"
          aria-label="Afficher les informations">
    <svg class="tirette-triangle" viewBox="0 0 10 10" aria-hidden="true"><polygon points="1,1 9,5 1,9" fill="currentColor"/></svg>
    <span class="tirette-texte">Informations</span>
  </button>
  <aside class="panneau-info" id="panneau-info" aria-hidden="true">
    <div class="pi-corps">
<?php if ($demandeNouvelle): ?>
      <p>Le code et le nom suffisent pour ouvrir une parcelle — tout le reste (forme,
         dimensions, sol) se précise ensuite, ici ou dans les étapes suivantes.
         <strong>Pérenne</strong> : vergers, haies, système porté par des arbres.
         <strong>Potager</strong> : cultures de légumes, annuelles.</p>
<?php else: ?>
      <p>Vous allez créer la ou les parcelles du site, en fixant leur forme et leur place.
         Ici : les dimensions de la parcelle <code><?= htmlspecialchars((string) $parcelleActive['code'], ENT_QUOTES, 'UTF-8') ?></code>,
         où courent ses bordures, et la largeur voulue pour les planches. Le nombre de
         planches se calcule seul — et avec lui les linéaires, donc le nombre de plants
         et de graines. Prévoyez un mètre ruban et dix minutes sur place.</p>
      <p><strong>Sélection</strong> — cliquez une bordure du dessin pour la choisir.
         <strong>Déplacer un bord</strong> — tirez le bord d'une bordure sur le dessin
         pour changer sa largeur ; le tableau « Ce que la géométrie donne » se met à
         jour au relâchement.</p>
<?php endif; ?>
      <p>Connecté en tant que <?= htmlspecialchars($nomAffiche, ENT_QUOTES, 'UTF-8') ?>. <a href="deconnexion.php">Se déconnecter</a>.</p>
    </div>
  </aside>

  <footer class="pied">
    <form class="pied-recherche" onsubmit="return false;">
      <input type="search" placeholder="Rechercher dans le site…" aria-label="Rechercher dans le site">
      <button type="submit" aria-label="Rechercher">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="10.5" cy="10.5" r="6.5"/><line x1="15.5" y1="15.5" x2="21" y2="21"/></svg>
      </button>
    </form>
<?php if (!$demandeNouvelle): ?>
    <label class="pied-option"><input type="checkbox" id="afficher-cotes" class="case" checked> Afficher les côtes</label>
<?php endif; ?>
    <span class="pied-spacer"></span>
<?php if (!$demandeNouvelle): ?>
    <button class="pied-reset" id="pied-reset" aria-label="Réinitialiser" title="Réinitialiser">
      <span class="pied-reset-texte">Réinitialiser</span>
      <span class="pied-reset-icone" aria-hidden="true">R</span>
    </button>
<?php endif; ?>
  </footer>

</div>

<script src="assets/js/etapes.js"></script>
<script src="assets/js/gabarit.js"></script>
<script>
  Gabarit.init({
    site: <?= json_encode((string) $site['nom'], JSON_UNESCAPED_UNICODE) ?>,
    etape: 'conception-parcelles',
    contexte: <?= $demandeNouvelle ? json_encode('nouvelle parcelle', JSON_UNESCAPED_UNICODE)
      : json_encode($parcelleActive['code'] . ' — ' . $parcelleActive['nom'], JSON_UNESCAPED_UNICODE) ?>
  });
</script>
<?php if (!$demandeNouvelle): ?>
<script src="conception-parcelles.js"></script>
<?php endif; ?>
</body>
</html>
