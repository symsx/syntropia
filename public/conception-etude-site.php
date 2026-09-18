<?php

declare(strict_types=1);

require __DIR__ . '/../src/autoload.php';
require __DIR__ . '/../src/Configuration.php';
require __DIR__ . '/../src/Database.php';

use Syntropia\Geojson;
use Syntropia\Relief;
use Syntropia\Role;
use Syntropia\Web\Authentification;
use Syntropia\Web\Calques;
use Syntropia\Web\Sites;
use Syntropia\Web\Vents;

Authentification::demarrer($_SERVER);
$auth = new Authentification();
$auth->exige(Role::Utilisateur);

$sites = new Sites();
$compteId = (int) $auth->compteId();
$site = $sites->pourCompte($compteId);
if ($site === null) {
    header('Location: ' . \Syntropia\Configuration::base() . 'definition-projet.php');
    exit;
}
$siteId = (int) $site['id'];
$peutEcrire = $sites->peutEcrireSur($siteId, $compteId);

$calques = new Calques();
$elements = $calques->dUnSite($siteId);
$parcelles = $calques->parcellesAPlacer($siteId);

/* Importer des tracés depuis un fichier GeoJSON — ContourBuddy, QGIS,
 * Géoportail, une trace GPS convertie… tout GeoJSON entre par cette
 * porte, sur le calque choisi. Simple POST qui rend la même page, comme
 * la référence : un fichier ne se poste pas en fetch() sans complexité
 * ajoutée, et contrairement au cadastre/à l'eau/aux vents/au relief
 * (des services distants qu'on interroge depuis la page), importer est
 * un geste ponctuel qui n'a pas besoin de rester sur une vue précise de
 * la carte. */
$importMessage = null;
$importAlerte = false;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
    && $auth->jetonValide($_POST['csrf'] ?? null) && $peutEcrire
    && ($_POST['action'] ?? '') === 'importer') {
    $calqueCible = (string) ($_POST['calque'] ?? 'relief');
    $fichier = $_FILES['fichier'] ?? null;
    if (!is_array($fichier) || ($fichier['error'] ?? 1) !== UPLOAD_ERR_OK
        || !is_uploaded_file($fichier['tmp_name'] ?? '')) {
        $importMessage = "Le fichier n'est pas arrivé. Il dépasse peut-être la taille "
            . "que le serveur accepte.";
        $importAlerte = true;
    } else {
        $lu = Geojson::lire((string) file_get_contents($fichier['tmp_name']));
        $poses = 0;
        foreach ($lu['traces'] as $trace) {
            $ajout = $calques->ajouter($siteId, [
                'calque' => $calqueCible, 'type' => $trace['type'],
                'libelle' => $trace['libelle'],
                'geometrie' => json_encode($trace['points']),
                'proprietes' => json_encode($trace['proprietes'] + [
                    'source' => 'import ' . basename((string) ($fichier['name'] ?? 'geojson')),
                    'importe_le' => date('Y-m-d')]),
            ]);
            if ($ajout['id'] !== null) {
                $poses++;
            }
        }
        $importAlerte = $poses === 0;
        $importMessage = ($poses > 0
            ? $poses . ' tracé' . ($poses > 1 ? 's posés' : ' posé') . ' sur le calque « '
                . (Calques::CALQUES[$calqueCible]['libelle'] ?? $calqueCible) . ' ». '
            : '') . implode(' ', $lu['erreurs']);
        if (trim($importMessage) === '') {
            $importMessage = 'Rien à importer.';
        }
        $elements = $calques->dUnSite($siteId);
    }
}

/* Les courbes de niveau se dessinent sur la carte, non seulement au
 * moment du relevé : la grille garde les altitudes, les courbes s'en
 * tirent ici, en coordonnées géographiques, à chaque chargement — même
 * logique que la référence sur `carte.php`. Ainsi un tracé du fond
 * modifié après coup fait recalculer des courbes toujours justes. */
foreach ($elements as $rang => $element) {
    if ($element['calque'] !== 'relief' || $element['type'] !== 'grille'
        || !is_array($element['proprietes']['z'] ?? null) || $element['geometrie'] === []) {
        continue;
    }
    $elements[$rang]['proprietes']['courbes'] = Relief::courbesGeo(
        $element['proprietes']['z'], (int) ($element['proprietes']['cote'] ?? 0),
        (float) ($element['proprietes']['pas'] ?? 10), $element['geometrie'][0]);
}

/** Le relevé en base, s'il y en a un — pour afficher pente et exposition. */
$grille = null;
$pente = null;
foreach ($elements as $element) {
    if ($element['calque'] === 'relief' && $element['type'] === 'grille'
        && is_array($element['proprietes']['z'] ?? null)) {
        $grille = $element;
    }
}
if ($grille !== null) {
    $pente = Relief::pente($grille['proprietes']['z'], (int) $grille['proprietes']['cote'],
        (float) $grille['proprietes']['pas']);
}

$latitude = $site['latitude'] === null ? null : (float) $site['latitude'];
$longitude = $site['longitude'] === null ? null : (float) $site['longitude'];

/* La rose des vents, calculée à la demande — jamais gardée. Un simple
 * POST qui rend la même page, comme la référence : pas d'API JSON
 * séparée, rien à persister. */
$rose = null;
$roseMessage = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
    && $auth->jetonValide($_POST['csrf'] ?? null)
    && ($_POST['action'] ?? '') === 'rose'
    && $latitude !== null && $longitude !== null) {
    $anneesDemandees = (int) ($_POST['annees'] ?? Vents::ANNEES_DEFAUT);
    $resultat = Vents::rose($latitude, $longitude, $anneesDemandees);
    $rose = $resultat['rose'];
    $roseMessage = $resultat['message'];
}

// Ce que le script a besoin de savoir, et rien de plus : il ne refait
// aucun contrôle d'accès — l'API les tient tous.
$config = [
    'siteId' => $siteId,
    'csrf' => $auth->jeton(),
    'api' => 'api-calques.php',
    'apiCadastre' => 'api-cadastre.php',
    'apiEau' => 'api-eau.php',
    'apiAltitude' => 'api-altitude.php',
    'peutEcrire' => $peutEcrire,
    'calques' => Calques::CALQUES,
    'elements' => $elements,
    'parcelles' => $parcelles,
    'centre' => $latitude === null || $longitude === null ? null : [$latitude, $longitude],
];

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
<title>Étude du site — Syntropia</title>
<link rel="stylesheet" href="assets/css/gabarit.css">
<style>
  /* propre à cette étape : la carte occupe tout le bureau, sans la
     marge habituelle — un terrain regardé de près a besoin de place. */
  main#bureau{ padding:0; align-items:stretch; justify-content:stretch; }

  .carte-grande{
    position:relative; width:100%; height:100%; overflow:hidden;
    background:var(--socle-fond); cursor:grab; touch-action:none;
  }
  .carte-grande:active{ cursor:grabbing; }
  .carte-grande .carte-tuiles img{ position:absolute; width:256px; height:256px; user-select:none; }
  .carte-grande .carte-point{ display:none; }
  .carte-grande .carte-credit{
    position:absolute; right:0; bottom:0; z-index:4; background:var(--surface); color:var(--muted);
    font:400 .68rem var(--texte); padding:.15rem .5rem; border-radius:.5rem 0 0 0; text-decoration:none;
  }
  .carte-grande .carte-panne{
    position:absolute; inset:auto 1rem 3.2rem; z-index:5; margin:0; background:var(--alerte);
    color:var(--bouton-encre); padding:.5rem .8rem; border-radius:.6rem; font:400 .8rem/1.35 var(--texte);
  }
  svg.calques{ position:absolute; inset:0; z-index:3; width:100%; height:100%; pointer-events:none; touch-action:none; }
  .calque-etiquette{
    font:600 .78rem var(--texte); paint-order:stroke fill;
    /* #fff fixe, jamais var(--surface) : les tuiles OpenStreetMap restent
       toujours claires, thème sombre ou non — un contour calé sur le
       thème (sombre) devenait illisible sur ce fond qui, lui, ne change
       pas. Même logique que le cercle d'un point, déjà stroke:#fff plus
       haut dans ce fichier. */
    stroke:#fff; stroke-width:3px; stroke-linejoin:round;
  }

  .carte-reglures{
    position:absolute; left:1rem; bottom:1rem; z-index:4;
    display:flex; align-items:center; gap:.7rem;
  }
  .carte-echelle{
    display:inline-block; min-width:2.5rem; padding:.1rem .4rem;
    border:1px solid var(--line); border-top:0; background:var(--surface);
    color:var(--muted); font:400 .74rem var(--texte); text-align:center; font-variant-numeric:tabular-nums;
  }
  .carte-centrer{
    padding:.3rem .6rem; border:1px solid var(--line); border-radius:.5rem; background:var(--surface);
    color:var(--accent-titre); font:600 .72rem var(--titre); text-transform:uppercase; letter-spacing:.02em;
    cursor:pointer;
  }
  .carte-centrer:hover{ background:var(--accent-fond); }
  .carte-avis{
    position:absolute; left:1rem; top:1rem; right:1rem; z-index:4;
    display:flex; flex-direction:column; gap:.5rem;
  }
  .carte-sans-point{
    margin:0; background:var(--alerte); color:var(--bouton-encre); padding:.55rem .8rem;
    border-radius:.6rem; font:400 .82rem/1.4 var(--texte);
  }
  .carte-sans-point a{ color:inherit; font-weight:600; }

  /* les outils que le calque actif n'attend pas restent cliquables,
     seulement atténués — personne n'est mieux placé que le porteur. */
  .outils-liste label.mode--autre{ opacity:.5; }

  /* le panneau droit : calque actif, tracé en cours, pose d'une
     parcelle, puis ce qui est déjà tracé — dans cet ordre, celui du
     geste. */
  .pd-champ-texte{
    width:100%; padding:.4rem .55rem; font:400 .84rem var(--texte); color:var(--ink);
    background:var(--surface); border:1px solid var(--line); border-radius:.5rem; margin-top:.2rem;
  }
  .pd-champ-texte:focus{ outline:2px solid var(--accent); outline-offset:1px; }
  .pd-label{ display:block; font:600 .78rem var(--titre); color:var(--ink); margin:.7rem 0 0; }
  .pd-aide{ font:400 .74rem/1.3 var(--texte); color:var(--muted); margin:.75rem 0 .3rem; min-height:1.3em; }
  .pd-etat{ font:400 .8rem/1.4 var(--texte); color:var(--ink); margin:.5rem 0; }
  .pd-etat-alerte{ color:var(--alerte); }
  .pd-boutons{ display:flex; gap:.5rem; margin-top:.6rem; }
  .pd-bouton{
    flex:1; padding:.5rem; border:0; border-radius:.5rem; cursor:pointer;
    background:var(--accent); color:var(--bouton-encre); font:600 .82rem var(--titre);
  }
  .pd-bouton:disabled{ opacity:.4; cursor:default; }
  .pd-bouton:hover:not(:disabled){ opacity:.92; }
  .pd-bouton-discret{
    flex:none; padding:.5rem .8rem; border:1px solid var(--line); border-radius:.5rem; cursor:pointer;
    background:var(--surface); color:var(--muted); font:600 .78rem var(--titre);
  }
  .pd-bouton-discret:hover{ background:var(--accent-fond); }

  .chercher-eau{ border-left:2px solid #2E6E9E; padding-left:.7rem; margin:0 0 .9rem; }
  .chercher-eau .pd-aide{ display:block; margin:.3rem 0 0; }

  .cadastre-bascule{ border-left:2px solid var(--accent); padding-left:.7rem; margin:.7rem 0 0; }
  .cadastre-bascule label{ display:flex; align-items:center; gap:.4rem; font:600 .82rem var(--titre); color:var(--ink); cursor:pointer; }
  .cadastre-bascule .pd-aide{ margin:.3rem 0 0; }

  .calques-liste{ list-style:none; margin:0; padding:0; }
  .calque-ligne{ padding:.4rem 0; border-top:1px solid var(--line); }
  .calque-ligne:first-child{ border-top:0; }
  .calque-ligne > label{ display:flex; align-items:center; gap:.4rem; font:600 .82rem var(--titre); color:var(--ink); cursor:pointer; }
  .calque-pastille{ width:.7rem; height:.7rem; border-radius:50%; flex:none; }
  .calque-ligne small{ margin-left:auto; font:400 .72rem var(--texte); color:var(--muted); }
  .calque-ligne ul{ list-style:none; margin:.3rem 0 0; padding:0 0 0 1.1rem; }
  .calque-ligne ul li{ font:400 .78rem/1.4 var(--texte); color:var(--muted); margin:.15rem 0; }
  .calque-ligne ul li a{ color:var(--ink); }
  .discret{ border:0; background:none; color:var(--muted); font:400 .72rem var(--texte); text-decoration:underline; cursor:pointer; padding:0; margin-left:.3rem; }
  .discret:hover{ color:var(--alerte); }

  #vents-rose-bloc{ margin-top:.7rem; }
  .vents-formulaire{ display:flex; align-items:center; gap:.4rem; flex-wrap:wrap; font:400 .82rem var(--texte); color:var(--ink); margin:.5rem 0; }
  .vents-formulaire input{ width:3rem; padding:.3rem .4rem; font:400 .82rem var(--texte); color:var(--ink); background:var(--surface); border:1px solid var(--line); border-radius:.4rem; }
  .rose-vents{ margin:.8rem 0; }
  .rose-vents svg{ width:100%; max-width:13rem; display:block; margin:0 auto; }
  .rose-vents figcaption{ margin-top:.4rem; }
  .vents-table{ width:100%; border-collapse:collapse; margin:.7rem 0; font:400 .72rem var(--texte); color:var(--ink); }
  .vents-table th, .vents-table td{ padding:.25rem .3rem; border-top:1px solid var(--line); text-align:left; }
  .vents-table tr:first-child th{ border-top:0; color:var(--muted); font-weight:600; text-transform:uppercase; font-size:.65rem; letter-spacing:.03em; }

  /* Le panneau Données doit rester ouvert PENDANT qu'on clique sur la
     carte (choisir un calque, poser une parcelle, tracer) — seule page
     du gabarit où panneau et bureau s'utilisent en même temps. Le scrim
     partagé (clic en dehors = ferme) bloquerait ces clics ; on le
     neutralise ici seulement. Fermeture toujours possible par la
     tirette ou Échap. Trouvé en testant le placement par rectangle,
     2026-09-16. */
  #scrim-droit{ pointer-events:none !important; opacity:0 !important; }
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
          <a class="compte-menu-item" href="compte.php"><span class="compte-menu-puce">⚙</span>Préférences</a>
          <div class="compte-menu-separateur"></div>
          <a class="compte-menu-item" href="deconnexion.php"><span class="compte-menu-puce">⏻</span>Se déconnecter</a>
        </div>
      </div>
    </div>
  </header>

<?php if ($peutEcrire): ?>
  <div class="bandeau-outils" role="toolbar" aria-label="Outils de dessin" id="bandeau-outils">
    <button type="button" class="outils-declencheur" id="outils-declencheur" aria-haspopup="true"
            aria-expanded="false" aria-controls="outils-liste">
      <span id="outils-icone-actif" aria-hidden="true">↖</span>
      <span id="outils-texte-actif">Déplacer</span>
      <svg class="outils-declencheur-chevron" viewBox="0 0 10 10" aria-hidden="true"><polygon points="1,3 5,8 9,3" fill="currentColor"/></svg>
    </button>

    <div class="outils-liste" id="outils-liste">
      <input type="radio" name="outil" id="outil-deplacer" checked>
      <label class="outil-bouton" for="outil-deplacer">↖ Déplacer</label>

      <input type="radio" name="outil" id="outil-point">
      <label class="outil-bouton" for="outil-point">● Point</label>

      <input type="radio" name="outil" id="outil-ligne">
      <label class="outil-bouton" for="outil-ligne">╱ Ligne</label>

      <input type="radio" name="outil" id="outil-polygone">
      <label class="outil-bouton" for="outil-polygone">⬠ Contour</label>

      <input type="radio" name="outil" id="outil-libre">
      <label class="outil-bouton" for="outil-libre">✏ À main levée</label>
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
            <li><a href="conception-parcelles.php"><span class="pg-puce">▤</span><span class="pg-libelle">Les parcelles</span></a></li>
          </ul>
        </div>
        <div class="pg-section">
          <p class="pg-titre">Phase projet</p>
          <ul class="pg-liste pg-phase-projet"></ul>
        </div>
        <p class="pg-pied">La carte pose les limites du terrain et ce qui l'entoure — le détail d'une parcelle se dessine sur son propre plan.</p>
      </div>
    </nav>

    <main class="bureau" id="bureau">
      <div class="carte-grande" data-carte data-fixe
           data-calques="<?= htmlspecialchars((string) json_encode($config, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>"
           data-latitude="latitude" data-longitude="longitude">
        <input type="hidden" id="latitude" value="<?= htmlspecialchars((string) ($site['latitude'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" id="longitude" value="<?= htmlspecialchars((string) ($site['longitude'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        <div class="carte-avis" id="carte-avis">
<?php if ($latitude === null || $longitude === null): ?>
        <p class="carte-sans-point"><strong>Le lieu n'est pas encore posé.</strong> La carte ne sait
           donc pas où se placer. <a href="definition-site.php">Posez le point du site</a>, et revenez —
           elle s'ouvrira sur votre terrain.</p>
<?php endif; ?>
        <p class="carte-sans-point" id="carte-cadastre-info" hidden></p>
        </div>
        <p class="carte-reglures">
          <span id="carte-echelle" class="carte-echelle">échelle</span>
          <button type="button" id="carte-centrer" class="carte-centrer" hidden>cadrer sur mes tracés</button>
        </p>
      </div>
    </main>

<?php if ($peutEcrire): ?>
    <div class="scrim" id="scrim-droit"></div>
    <button class="tirette<?= $roseMessage !== null ? ' ouvert' : '' ?>" id="tirette"
            aria-expanded="<?= $roseMessage !== null ? 'true' : 'false' ?>" aria-controls="panneau-droit"
            aria-label="<?= $roseMessage !== null ? 'Masquer' : 'Afficher' ?> le panneau Données">
      <svg class="tirette-triangle" viewBox="0 0 10 10" aria-hidden="true"><polygon points="1,1 9,5 1,9" fill="currentColor"/></svg>
      <span class="tirette-texte">Données</span>
    </button>
    <aside class="panneau-droit<?= $roseMessage !== null ? ' ouvert' : '' ?>" id="panneau-droit"
           aria-hidden="<?= $roseMessage !== null ? 'false' : 'true' ?>">
      <div class="pd-corps">
        <p class="chercher-eau" hidden>
          <button type="button" id="eau-chercher" class="pd-bouton-discret">Chercher l'eau d'OpenStreetMap</button>
          <span class="pd-aide"><strong>Tracez d'abord les limites du terrain</strong> — à la main,
             ou en suivant le cadastre.</span>
          <span class="pd-aide" id="eau-etat"></span>
        </p>

        <p class="cadastre-bascule" hidden>
          <label><input type="checkbox" class="case" id="cadastre-visible"> <strong>Voir le cadastre</strong></label>
          <span class="pd-aide">Les limites des parcelles cadastrales, en trait fin — de quoi
             suivre leurs bords pour tracer les vôtres. C'est un repère : rien n'en est
             enregistré. Un plan cadastral est un document fiscal, et seul un bornage fait foi.
             <span id="cadastre-etat"></span></span>
        </p>

        <p class="pd-titre">Sur quel calque</p>
        <select id="calque-choix" class="pd-champ-texte">
<?php foreach (Calques::CALQUES as $cle => $def): ?>
          <option value="<?= htmlspecialchars($cle, ENT_QUOTES, 'UTF-8') ?>"<?= $roseMessage !== null && $cle === 'vents' ? ' selected' : '' ?>><?= htmlspecialchars($def['libelle'], ENT_QUOTES, 'UTF-8') ?></option>
<?php endforeach; ?>
        </select>
        <p class="pd-etat" id="trace-etat"></p>

        <div id="vents-rose-bloc" hidden>
          <p class="pd-aide">Le calque des vents se trace à la main : une flèche par vent, dans le
             sens où il souffle, épaisse pour un dominant. Pour savoir d'où ils viennent, la rose
             des vents se calcule sur ce point — quelques secondes, rien à mesurer. ⚠ La maille du
             modèle est d'environ 25 km : elle donne le régime général, jamais le couloir d'un
             vallon — d'où les flèches tracées à la main par-dessus.</p>
<?php if ($latitude === null || $longitude === null): ?>
          <p class="pd-aide"><strong>Posez d'abord le point du site.</strong></p>
<?php else: ?>
          <form method="post" class="vents-formulaire">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($auth->jeton(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="rose">
            <label for="annees">Sur</label>
            <input id="annees" name="annees" inputmode="numeric" size="2"
                   value="<?= htmlspecialchars((string) ($_POST['annees'] ?? Vents::ANNEES_DEFAUT), ENT_QUOTES, 'UTF-8') ?>"> années
            <button type="submit" class="pd-bouton-discret">Calculer la rose des vents</button>
          </form>
          <p class="pd-aide">Elle se calcule, elle ne se garde pas : rien n'entre dans votre projet ici.</p>
<?php endif; ?>
<?php if ($roseMessage !== null): ?>
          <p class="pd-etat"><?= htmlspecialchars($roseMessage, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
<?php if ($rose !== null):
    $maxPart = max(array_column($rose['secteurs'], 'part'));
    $maxForte = max(array_column($rose['secteurs'], 'part_forte'));
    $petale = static function (float $azimut, float $rayon): string {
        $points = [];
        for ($a = $azimut - 11.25; $a <= $azimut + 11.25; $a += 5.625) {
            $points[] = round(sin(deg2rad($a)) * $rayon, 2) . ',' . round(-cos(deg2rad($a)) * $rayon, 2);
        }
        return '0,0 ' . implode(' ', $points);
    };
?>
          <figure class="rose-vents">
            <svg viewBox="-120 -120 240 240" role="img"
                 aria-label="Rose des vents : dominante <?= htmlspecialchars(Vents::nomLong($rose['dominant']), ENT_QUOTES, 'UTF-8') ?>">
              <circle cx="0" cy="0" r="100" fill="none" stroke="var(--line)" stroke-width="1"/>
              <circle cx="0" cy="0" r="50" fill="none" stroke="var(--line)" stroke-width="1" stroke-dasharray="3 4"/>
<?php foreach ($rose['secteurs'] as $secteur): $r = $maxPart > 0 ? 100 * $secteur['part'] / $maxPart : 0; ?>
              <polygon points="<?= $petale((float) $secteur['azimut'], $r) ?>"
                       fill="#5E4A6E" fill-opacity="0.22" stroke="#5E4A6E" stroke-width="0.6"/>
<?php endforeach; ?>
<?php foreach ($rose['secteurs'] as $secteur):
        $r = $maxForte > 0 ? 100 * $secteur['part_forte'] / $maxForte : 0;
        if ($r <= 0) { continue; } ?>
              <polygon points="<?= $petale((float) $secteur['azimut'], $r) ?>" fill="#5E4A6E" fill-opacity="0.55"/>
<?php endforeach; ?>
<?php foreach (['N' => [0, -108], 'E' => [110, 4], 'S' => [0, 118], 'O' => [-110, 4]] as $point => $xy): ?>
              <text x="<?= $xy[0] ?>" y="<?= $xy[1] ?>" text-anchor="middle" font-size="14"
                    fill="var(--muted)"><?= $point ?></text>
<?php endforeach; ?>
            </svg>
            <figcaption class="pd-aide"><strong>D'où vient le vent</strong>, sur
               <?= (int) $rose['annees'] ?> années — <?= htmlspecialchars($rose['debut'], ENT_QUOTES, 'UTF-8') ?>
               à <?= htmlspecialchars($rose['fin'], ENT_QUOTES, 'UTF-8') ?>. L'aplat clair compte
               toutes les heures, <strong>le foncé les heures à plus de <?= (int) Vents::SEUIL_FORT_KMH ?> km/h</strong>.
               Source : réanalyse ERA5 via <a target="_blank" rel="noopener" href="https://open-meteo.com">Open-Meteo</a>, CC BY 4.0.</figcaption>
          </figure>
          <table class="vents-table">
            <tr><th>Secteur</th><th>Heures</th><th>Fortes</th><th>Vitesse</th></tr>
<?php $tries = $rose['secteurs'];
      usort($tries, static fn (array $a, array $b): int => $b['part'] <=> $a['part']);
      foreach (array_slice($tries, 0, 5) as $secteur): ?>
            <tr><th><?= htmlspecialchars(Vents::nomLong($secteur['nom']), ENT_QUOTES, 'UTF-8') ?></th>
                <td><?= htmlspecialchars(str_replace('.', ',', (string) $secteur['part']), ENT_QUOTES, 'UTF-8') ?> %</td>
                <td><?= htmlspecialchars(str_replace('.', ',', (string) $secteur['part_forte']), ENT_QUOTES, 'UTF-8') ?> %</td>
                <td><?= htmlspecialchars(str_replace('.', ',', (string) $secteur['vitesse_moyenne']), ENT_QUOTES, 'UTF-8') ?> km/h</td></tr>
<?php endforeach; ?>
          </table>
          <p class="pd-aide"><strong>Le vent le plus fréquent vient du <?= htmlspecialchars(Vents::nomLong($rose['dominant']), ENT_QUOTES, 'UTF-8') ?></strong><?php
              if ($rose['dominant_fort'] !== $rose['dominant']): ?>, mais
             <strong>les heures fortes viennent surtout du <?= htmlspecialchars(Vents::nomLong($rose['dominant_fort']), ENT_QUOTES, 'UTF-8') ?></strong> —
             et c'est celui-là qu'un brise-vent doit arrêter<?php else: ?>, et c'est aussi de là
             que viennent les heures fortes<?php endif; ?>.</p>
          <p class="pd-aide"><strong>Pour tracer la flèche</strong> : la rose dit d'où vient le
             vent, la flèche va dans l'autre sens. Un vent de
             <?= htmlspecialchars(Vents::nomLong($rose['dominant']), ENT_QUOTES, 'UTF-8') ?> se dessine
             <strong>en partant du <?= htmlspecialchars(Vents::nomLong($rose['dominant']), ENT_QUOTES, 'UTF-8') ?></strong>
             vers le centre du terrain.</p>
<?php endif; ?>
        </div>

        <div id="relief-bloc" hidden>
          <p class="pd-aide">La pente et son sens décident de l'orientation des lignes, et de
             l'endroit où l'eau s'en va. Je peux relever les altitudes IGN sur l'emprise du
             terrain déjà tracé — quelques secondes, rien à mesurer. ⚠ La maille du modèle est
             d'environ 5 m : elle donne la forme d'ensemble, jamais le détail d'une baissière ou
             d'un fossé.</p>
          <button type="button" id="altitude-relever" class="pd-bouton-discret">Relever le relief</button>
          <p class="pd-aide" id="altitude-etat"><?php if ($grille !== null): ?><?= htmlspecialchars(
              $grille['libelle'] . ', relevé le ' . ($grille['proprietes']['releve_le'] ?? '?'),
              ENT_QUOTES, 'UTF-8') ?><?php endif; ?></p>
<?php if ($pente !== null && $pente['pente_pct'] !== null): ?>
          <p class="pd-aide"><strong>Pente moyenne <?= htmlspecialchars(str_replace('.', ',', (string) $pente['pente_pct']), ENT_QUOTES, 'UTF-8') ?> %</strong>,
             exposée <?= htmlspecialchars((string) $pente['exposition'], ENT_QUOTES, 'UTF-8') ?>
             — dénivelé de <?= htmlspecialchars(str_replace('.', ',', (string) $pente['denivele_m']), ENT_QUOTES, 'UTF-8') ?> m
             (de <?= (int) round($pente['mini']) ?> à <?= (int) round($pente['maxi']) ?> m).</p>
          <p class="pd-aide"><?= htmlspecialchars(Relief::ceQuElleCommande($pente['pente_pct']), ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
        </div>

        <label class="pd-label" for="trace-libelle">Son nom</label>
        <input type="text" id="trace-libelle" class="pd-champ-texte" placeholder="le grand chêne, le chemin bas…">

        <div id="trace-intensite-champ" hidden>
          <label class="pd-label" for="trace-intensite">Son intensité</label>
          <select id="trace-intensite" class="pd-champ-texte">
            <option value="dominant" selected>dominant — trait épais</option>
            <option value="secondaire">secondaire — trait fin</option>
          </select>
        </div>
        <div id="trace-hauteur-champ" hidden>
          <label class="pd-label" for="trace-hauteur">Sa hauteur, en mètres</label>
          <input type="number" id="trace-hauteur" class="pd-champ-texte" step="0.5" inputmode="decimal">
        </div>

        <div class="pd-boutons">
          <button type="button" id="trace-enregistrer" class="pd-bouton" disabled>Enregistrer ce tracé</button>
          <button type="button" id="trace-annuler" class="pd-bouton-discret">annuler</button>
        </div>

        <p class="pd-titre">Poser une parcelle</p>
        <label class="pd-label" for="parcelle-choix">Laquelle</label>
        <select id="parcelle-choix" class="pd-champ-texte">
          <option value="">— aucune —</option>
<?php foreach ($parcelles as $p):
        $dims = $p['largeur_m'] !== null && $p['longueur_m'] !== null
            ? $p['largeur_m'] . ' × ' . $p['longueur_m'] . ' m' : ''; ?>
          <option value="<?= (int) $p['id'] ?>"
                  data-nom="<?= htmlspecialchars((string) $p['code'] . ' — ' . (string) $p['nom'], ENT_QUOTES, 'UTF-8') ?>"
                  data-dims="<?= htmlspecialchars($dims, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $p['code'] . ' — ' . (string) $p['nom'], ENT_QUOTES, 'UTF-8') ?></option>
<?php endforeach; ?>
        </select>
        <label class="pd-label" for="parcelle-angle">Son orientation, en degrés depuis le nord</label>
        <input id="parcelle-angle" type="range" min="0" max="179" step="1" value="0" style="width:100%">
        <output id="parcelle-angle-valeur" style="font:400 .78rem var(--texte); color:var(--muted);">0°</output>

        <p class="pd-titre">Les tracés</p>
        <ul class="calques-liste" id="calques-liste"></ul>

        <p class="pd-titre">Importer des tracés</p>
        <p class="pd-aide">Des courbes de niveau tracées ailleurs — <a target="_blank" rel="noopener"
           href="https://contour-buddy1.vercel.app">ContourBuddy</a> les génère depuis la même
           altimétrie IGN et les exporte en GeoJSON —, un relevé sorti de QGIS, une trace GPS
           convertie : tout fichier GeoJSON entre ici, sur le calque que vous choisissez. Un
           <strong>multiple</strong> donne un tracé par morceau : une courbe coupée en trois
           reste trois courbes. ⚠ Le GeoJSON suppose des degrés (WGS 84) — un export en
           Lambert 93 est refusé, avec son motif.</p>
<?php if ($importMessage !== null): ?>
        <p class="pd-etat<?= $importAlerte ? ' pd-etat-alerte' : '' ?>"><?= htmlspecialchars($importMessage, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($auth->jeton(), ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="action" value="importer">
          <label class="pd-label" for="import-calque">Sur le calque</label>
          <select id="import-calque" name="calque" class="pd-champ-texte">
<?php foreach (Calques::CALQUES as $cle => $def): ?>
            <option value="<?= htmlspecialchars($cle, ENT_QUOTES, 'UTF-8') ?>"<?= $cle === 'relief' ? ' selected' : '' ?>><?= htmlspecialchars($def['libelle'], ENT_QUOTES, 'UTF-8') ?></option>
<?php endforeach; ?>
          </select>
          <input type="file" name="fichier" accept=".geojson,.json,application/geo+json,application/json" class="pd-champ-texte">
          <button type="submit" class="pd-bouton-discret" style="margin-top:.5rem;">Importer</button>
        </form>
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
      <p>Vous allez dessiner votre terrain, calque par calque — les limites, l'eau, les vents, ce qui
         fait de l'ombre, et le contour de chaque parcelle. Un calque se complète le jour où l'on
         remarque quelque chose : commencez par ce qui se voit sans instrument.</p>
<?php if ($peutEcrire): ?>
      <p><strong>Cliquez sur la carte pour dessiner</strong> — un point d'un clic ; une ligne ou un
         contour point par point, double-clic ou Entrée pour finir, clic droit pour défaire le
         dernier point, Échap pour tout annuler. <strong>À main levée</strong> : maintenez le clic et
         suivez le contour, relâchez pour finir.</p>
      <p id="calque-aide"></p>
<?php else: ?>
      <p>Vous êtes lecteur sur ce site : la carte se lit, rien ne s'y dessine.</p>
<?php endif; ?>
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
  Gabarit.init({
    site: <?= json_encode((string) $site['nom'], JSON_UNESCAPED_UNICODE) ?>,
    etape: 'conception-etude-site'
  });
</script>
<script src="assets/js/carte.js"></script>
<script src="assets/js/calques.js"></script>
</body>
</html>
