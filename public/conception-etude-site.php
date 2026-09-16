<?php

declare(strict_types=1);

require __DIR__ . '/../src/autoload.php';
require __DIR__ . '/../src/Configuration.php';
require __DIR__ . '/../src/Database.php';

use Syntropia\Role;
use Syntropia\Web\Authentification;
use Syntropia\Web\Calques;
use Syntropia\Web\Eau;
use Syntropia\Web\Sites;

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

$latitude = $site['latitude'] === null ? null : (float) $site['latitude'];
$longitude = $site['longitude'] === null ? null : (float) $site['longitude'];

// Ce que le script a besoin de savoir, et rien de plus : il ne refait
// aucun contrôle d'accès — l'API les tient tous.
$config = [
    'siteId' => $siteId,
    'csrf' => $auth->jeton(),
    'api' => 'api-calques.php',
    'apiCadastre' => 'api-cadastre.php',
    'apiEau' => 'api-eau.php',
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
    stroke:var(--surface); stroke-width:3px; stroke-linejoin:round;
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
  .carte-sans-point{
    position:absolute; left:1rem; top:1rem; right:1rem; z-index:4; margin:0;
    background:var(--alerte); color:var(--bouton-encre); padding:.55rem .8rem;
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
  .chercher-eau .pd-aide{ margin:.3rem 0 0; }

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
          <a class="compte-menu-item a-venir" href="compte.php" tabindex="-1" aria-disabled="true"><span class="compte-menu-puce">⚙</span>Préférences</a>
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
<?php if ($latitude === null || $longitude === null): ?>
        <p class="carte-sans-point"><strong>Le lieu n'est pas encore posé.</strong> La carte ne sait
           donc pas où se placer. <a href="definition-site.php">Posez le point du site</a>, et revenez —
           elle s'ouvrira sur votre terrain.</p>
<?php endif; ?>
        <p class="carte-reglures">
          <span id="carte-echelle" class="carte-echelle">échelle</span>
          <button type="button" id="carte-centrer" class="carte-centrer" hidden>cadrer sur mes tracés</button>
        </p>
      </div>
    </main>

<?php if ($peutEcrire): ?>
    <div class="scrim" id="scrim-droit"></div>
    <button class="tirette" id="tirette" aria-expanded="false" aria-controls="panneau-droit"
            aria-label="Afficher le panneau Données">
      <svg class="tirette-triangle" viewBox="0 0 10 10" aria-hidden="true"><polygon points="1,1 9,5 1,9" fill="currentColor"/></svg>
      <span class="tirette-texte">Données</span>
    </button>
    <aside class="panneau-droit" id="panneau-droit" aria-hidden="true">
      <div class="pd-corps">
        <p class="chercher-eau" hidden>
          <button type="button" id="eau-chercher" class="pd-bouton-discret">Chercher l'eau d'OpenStreetMap</button>
          <span class="pd-aide"><strong>Tracez d'abord les limites du terrain</strong> — à la main,
             ou en suivant le cadastre. <strong>Ce sont elles qui bornent la recherche</strong> :
             sinon je ramènerais la rivière du département. Je cherche à
             <?= (int) Eau::MARGE_M ?> m autour, parce que l'eau du voisin compte, et je ramène
             les cours d'eau à ce qui longe le terrain.</span>
          <span class="pd-aide">Rivières, ruisseaux, fossés, mares, zones humides et sources déjà
             cartographiés se posent sur le calque de l'eau. <strong>Ce qui est posé reste
             modifiable</strong>, et vous complétez : un fossé récent n'est jamais sur la carte.</span>
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
          <option value="<?= htmlspecialchars($cle, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($def['libelle'], ENT_QUOTES, 'UTF-8') ?></option>
<?php endforeach; ?>
        </select>
        <p class="pd-etat" id="trace-etat"></p>

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

        <p class="pd-titre">Ce qui est tracé</p>
        <ul class="calques-liste" id="calques-liste"></ul>
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
