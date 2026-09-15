<?php

declare(strict_types=1);

require __DIR__ . '/../src/autoload.php';
require __DIR__ . '/../src/Configuration.php';
require __DIR__ . '/../src/Database.php';

use Syntropia\Role;
use Syntropia\Web\Authentification;

Authentification::demarrer($_SERVER);
$auth = new Authentification();
$auth->exige(Role::Utilisateur);

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
<title>Créer les parcelles — Syntropia</title>
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

  <div class="bulle" id="bulle" role="status"></div>

  <div class="corps">
    <div class="scrim" id="scrim-gauche"></div>
    <nav class="panneau-gauche" id="panneau-gauche" aria-label="Navigation de l'atelier" aria-hidden="true">
      <div class="pg-entete">
        <a class="marque-nom" href="./">Syntropia</a>
      </div>
      <div class="pg-corps">
        <div class="pg-section">
          <p class="pg-titre pg-nomsite">La Vadaux</p>
          <ul class="pg-liste">
            <li><a href="#"><span class="pg-puce">⌂</span><span class="pg-libelle">Le site</span></a></li>
            <li><a href="#"><span class="pg-puce">▦</span><span class="pg-libelle">La carte</span></a></li>
          </ul>
        </div>
        <div class="pg-section">
          <p class="pg-titre">Phase projet</p>
          <!-- rempli par Gabarit.init() à partir de public/assets/js/etapes.js -->
          <ul class="pg-liste pg-phase-projet"></ul>
        </div>
        <div class="pg-section">
          <p class="pg-titre">Parcelle A</p>
          <ul class="pg-liste">
            <li><a href="#" class="active"><span class="pg-puce">▤</span><span class="pg-libelle">Le plan</span></a></li>
            <li><a href="#"><span class="pg-puce">≋</span><span class="pg-libelle">Le scénario</span></a></li>
            <li><a href="#"><span class="pg-puce">✎</span><span class="pg-libelle">Le suivi</span></a></li>
          </ul>
        </div>
        <p class="pg-pied">Première étape branchée sur l'authentification réelle — voir architecture-technique.md.</p>
      </div>
    </nav>

    <main class="bureau" id="bureau">
      <svg class="bureau-carte" id="plan" viewBox="0 0 1240 900" role="img"
           aria-label="Plan de la parcelle A : 12 mètres sur 8, quatre bordures externes actives">
        <g transform="translate(70,40)">
          <rect class="plan-parcelle-fond" x="0" y="0" width="1200" height="800"/>
          <g id="planches"></g>
          <rect class="plan-bordure" data-cote="nord"  id="bordure-nord"  x="0"    y="0"   width="1200" height="40"/>
          <rect class="plan-bordure" data-cote="sud"   id="bordure-sud"   x="0"    y="760" width="1200" height="40"/>
          <rect class="plan-bordure" data-cote="ouest" id="bordure-ouest" x="0"    y="0"   width="40"   height="800"/>
          <rect class="plan-bordure" data-cote="est"   id="bordure-est"   x="1160" y="0"   width="40"   height="800"/>

          <line class="plan-fleche" x1="0" y1="-16" x2="1200" y2="-16" marker-start="url(#fl)" marker-end="url(#fl)"/>
          <text class="plan-mesure" id="mesure-longueur" x="600" y="-24" text-anchor="middle">12,00 m</text>
          <line class="plan-fleche" x1="-16" y1="0" x2="-16" y2="800" marker-start="url(#fl)" marker-end="url(#fl)"/>
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
      </div>
    </aside>
  </div>

  <div class="scrim" id="scrim-info"></div>
  <button class="tirette-info" id="tirette-info" aria-expanded="false" aria-controls="panneau-info"
          aria-label="Afficher les informations">
    <svg class="tirette-triangle" viewBox="0 0 10 10" aria-hidden="true"><polygon points="1,1 9,5 1,9" fill="currentColor"/></svg>
    <span class="tirette-texte">Informations</span>
  </button>
  <aside class="panneau-info" id="panneau-info" aria-hidden="true">
    <div class="pi-corps">
      <p>Vous allez créer la ou les parcelles du site, en fixant leur forme et leur place. Ici : les dimensions de la parcelle <code>A</code>, où courent ses bordures, et la largeur voulue pour les planches. Le nombre de planches se calcule seul — et avec lui les linéaires, donc le nombre de plants et de graines. Prévoyez un mètre ruban et dix minutes sur place.</p>
      <p><strong>Sélection</strong> — cliquez une bordure du dessin pour la choisir. <strong>Déplacer un bord</strong> — tirez le bord d'une bordure sur le dessin pour changer sa largeur ; le tableau « Ce que la géométrie donne » se met à jour au relâchement.</p>
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
    <label class="pied-option"><input type="checkbox" id="afficher-cotes" class="case" checked> Afficher les côtes</label>
    <span class="pied-spacer"></span>
    <button class="pied-reset" id="pied-reset" aria-label="Réinitialiser" title="Réinitialiser">
      <span class="pied-reset-texte">Réinitialiser</span>
      <span class="pied-reset-icone" aria-hidden="true">R</span>
    </button>
  </footer>

</div>

<script src="assets/js/etapes.js"></script>
<script src="assets/js/gabarit.js"></script>
<script>
  Gabarit.init({ site: 'La Vadaux', etape: 'conception-parcelles', contexte: 'parcelle A' });
</script>
<script src="conception-parcelles.js"></script>
</body>
</html>
