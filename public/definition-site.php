<?php

declare(strict_types=1);

require __DIR__ . '/../src/autoload.php';
require __DIR__ . '/../src/Configuration.php';
require __DIR__ . '/../src/Database.php';

use Syntropia\Configuration;
use Syntropia\Documentation;
use Syntropia\Role;
use Syntropia\Web\Authentification;
use Syntropia\Web\Reponses;
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
$participantId = $sites->participantDuCompte($siteId, $compteId, $auth->nomAffiche() ?? 'Porteur');
$reponsesService = new Reponses();

$erreurs = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $auth->jetonValide($_POST['csrf'] ?? null)) {
    $erreurs = $sites->enregistrerParametres($siteId, (string) ($_POST['latitude'] ?? ''), (string) ($_POST['longitude'] ?? ''));
    if ($erreurs === []) {
        $reponsesService->enregistrerLot($siteId, $participantId, 'definition', [
            'site_commune'         => (string) ($_POST['commune'] ?? ''),
            'site_superficie'      => (string) ($_POST['superficie'] ?? ''),
            'site_usda'            => (string) ($_POST['usda'] ?? ''),
            'site_exposition'      => (string) ($_POST['exposition'] ?? ''),
            'site_pente'           => (string) ($_POST['pente'] ?? ''),
            'site_climat'          => (string) ($_POST['climat'] ?? ''),
        ]);
        header('Location: ' . Configuration::base() . 'conception-etude-sol.php');
        exit;
    }
}
$reponses = $reponsesService->pourParticipant($participantId);
// Après une erreur, retenir la saisie plutôt que de revenir à la base :
// la personne ne doit pas retaper ce qui a échoué pour une seule ligne.
$latitudeSaisie = $_POST['latitude'] ?? ($site['latitude'] ?? '');
$longitudeSaisie = $_POST['longitude'] ?? ($site['longitude'] ?? '');
$termes = Documentation::glossaire(__DIR__ . '/../contenu/glossaire.md');
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Site — Syntropia</title>
<link rel="stylesheet" href="assets/css/gabarit.css">
<style>
  /* propre à cette étape : pas de bandeau d'outils (la saisie est le
     seul geste de la page) — recaler la tirette/panneau Informations
     sur un seul bandeau. Deux colonnes plutôt qu'une : cette étape a
     plus de champs que les précédentes, et une seule colonne les
     aurait fait déborder — RÈGLE DU BUREAU : aucun défilement. */
  :root{ --haut-bandeaux: 3.4rem; }

  .site-cadre{ width:100%; max-width:40rem; }
  .site-annonce{ font:400 .92rem/1.5 var(--texte); color:var(--muted); margin:0 0 1.2rem; text-align:center; }
  .site-colonnes{ display:flex; gap:2rem; align-items:flex-start; }
  .site-colonne{ flex:1 1 0; min-width:0; }
  /* deux groupes distincts : l'administratif (le lieu, mesurable sans
     expertise) et l'observé (nécessite d'être sur place, ou d'y avoir
     déjà été) — remarque du porteur, 2026-09-15. */
  .site-groupe-titre{
    font:600 .72rem var(--titre); text-transform:uppercase; letter-spacing:.06em;
    color:var(--accent-titre); margin:0 0 .7rem; padding-bottom:.35rem;
    border-bottom:1px solid var(--line);
  }

  .site-champ{ margin-bottom:.85rem; border:0; padding:0; }
  .site-champ label, .site-champ legend{
    display:block; font:600 .82rem var(--titre); color:var(--ink); margin-bottom:.3rem; padding:0;
  }
  .site-champ small{ display:block; font:400 .72rem/1.3 var(--texte); color:var(--muted); margin-bottom:.25rem; }
  .site-champ input[type=text], .site-champ input[type=number]{
    width:100%; padding:.45rem .6rem; font:400 .88rem var(--texte); color:var(--ink);
    background:var(--surface); border:1px solid var(--line); border-radius:.5rem;
  }
  .site-champ input:focus, .site-champ textarea:focus{ outline:2px solid var(--accent); outline-offset:1px; }
  .site-champ textarea{
    width:100%; padding:.45rem .6rem; font:400 .88rem/1.35 var(--texte); color:var(--ink);
    background:var(--surface); border:1px solid var(--line); border-radius:.5rem; resize:none;
  }
  .site-ligne{ display:flex; gap:.6rem; }
  .site-ligne .site-champ{ flex:1; }
  .site-unite{ display:flex; align-items:center; gap:.4rem; }
  .site-unite input{ flex:1; }
  .site-unite span{ font:400 .82rem var(--texte); color:var(--muted); }
  .site-choix{ display:flex; gap:.7rem; flex-wrap:wrap; }
  .site-choix label{ display:flex; align-items:center; gap:.35rem; font:400 .82rem var(--texte); color:var(--ink); }

  /* le point du site — deux champs numériques, la carte comme confort */
  .site-carte-champs{ display:flex; gap:.5rem; margin-bottom:.4rem; }
  .site-carte-champs input{ flex:1; }
  .site-carte{
    position:relative; overflow:hidden; height:7.4rem; border:1px solid var(--line);
    border-radius:.5rem; background:var(--socle-fond); cursor:crosshair; touch-action:none;
  }
  .site-carte:focus{ outline:2px solid var(--accent); outline-offset:1px; }
  .site-carte .carte-tuiles img{ position:absolute; width:256px; height:256px; user-select:none; }
  .site-carte .carte-point{
    position:absolute; width:12px; height:12px; margin:-6px 0 0 -6px; border-radius:50%;
    background:var(--alerte); box-shadow:0 0 0 2px var(--surface), 0 1px 5px var(--ombre);
    pointer-events:none;
  }
  .site-carte .carte-credit{
    position:absolute; right:0; bottom:0; z-index:2; background:var(--surface); color:var(--muted);
    font:400 .62rem var(--texte); padding:.1rem .4rem; border-radius:.4rem 0 0 0; text-decoration:none;
  }
  .site-carte .carte-panne{
    position:absolute; inset:auto .5rem .5rem; z-index:2; margin:0; background:var(--alerte);
    color:var(--bouton-encre); padding:.4rem .6rem; border-radius:.5rem; font:400 .74rem/1.3 var(--texte);
  }

  .site-erreur{
    margin:0 0 .8rem; padding:.5rem .7rem; border-radius:.5rem; background:var(--alerte);
    color:var(--bouton-encre); font:500 .82rem var(--texte);
  }

  .site-bouton{
    width:100%; margin-top:.4rem; padding:.6rem; border:0; border-radius:.6rem;
    background:var(--accent); color:var(--bouton-encre); font:600 .92rem var(--titre); cursor:pointer;
  }
  .site-bouton:hover{ opacity:.92; }
  .site-note{ font:400 .76rem var(--texte); color:var(--muted); text-align:center; margin-top:.5rem; }

  /* petit écran : deux colonnes côte à côte ne tiennent plus — empilées,
     elles dépassent en hauteur sur un téléphone si rien d'autre ne se
     resserre. Chaque marge, chaque champ y perd un peu, pour que la
     somme tienne sans défilement. Remarque du porteur, 2026-09-15,
     capture d'écran sur téléphone à l'appui. */
  @media (max-width:30rem){
    main#bureau{ padding:.6rem 1rem; align-items:flex-start; }
    .site-annonce{ margin:0 0 .4rem; font-size:.78rem; line-height:1.3; }
    .site-colonnes{ flex-direction:column; gap:0; }
    .site-groupe-titre{ margin:.4rem 0 .3rem; }
    .site-champ{ margin-bottom:.4rem; }
    .site-champ label, .site-champ legend{ margin-bottom:.15rem; }
    .site-champ small{ margin-bottom:.1rem; }
    .site-carte-champs{ margin-bottom:.25rem; }
    .site-carte{ height:4.6rem; }
    .site-choix{ gap:.3rem .5rem; }
    .site-champ textarea{ height:3.1rem; }
    .site-bouton{ margin-top:0; padding:.45rem; }
    .site-note{ margin-top:.25rem; font-size:.7rem; }
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
          <p class="pg-titre">Phase projet</p>
          <ul class="pg-liste pg-phase-projet"></ul>
        </div>
        <div class="pg-section">
          <p class="pg-titre">Définition</p>
          <ul class="pg-liste pg-etapes-phase"></ul>
        </div>
        <p class="pg-pied">Quatrième et dernière étape de Définition — voir plan-du-site.md.</p>
      </div>
    </nav>

    <main class="bureau" id="bureau">
      <div class="site-cadre">
        <p class="site-annonce">Le terrain, tel qu'il est aujourd'hui — ce que vous savez
           déjà, ou pouvez observer en une visite.</p>

        <form method="post">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($auth->jeton(), ENT_QUOTES, 'UTF-8') ?>">
<?php if ($erreurs !== []): ?>
          <p class="site-erreur"><?= htmlspecialchars(implode(' ', $erreurs), ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
          <div class="site-colonnes">
            <div class="site-colonne">
              <p class="site-groupe-titre">Le lieu</p>
              <div class="site-champ">
                <label for="commune">Commune</label>
                <input type="text" id="commune" name="commune" placeholder="Ex. Saint-André-de-Cubzac"
                       value="<?= htmlspecialchars($reponses['site_commune'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
              </div>
              <div class="site-champ site-champ-carte">
                <label for="latitude">Position</label>
                <small>Cliquez sur la carte pour poser le point — ou saisissez les
                   coordonnées, la carte suivra. Un confort : les tuiles viennent
                   d'OpenStreetMap, un tiers, et rien ici n'en dépend.</small>
                <div class="site-carte-champs">
                  <input type="text" id="latitude" name="latitude" placeholder="45.02000"
                         value="<?= htmlspecialchars((string) $latitudeSaisie, ENT_QUOTES, 'UTF-8') ?>">
                  <input type="text" id="longitude" name="longitude" placeholder="-0.44000"
                         value="<?= htmlspecialchars((string) $longitudeSaisie, ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="site-carte" data-carte data-latitude="latitude" data-longitude="longitude"></div>
              </div>
              <div class="site-champ">
                <label for="superficie">Superficie</label>
                <div class="site-unite">
                  <input type="number" id="superficie" name="superficie" min="0" step="0.01" placeholder="0"
                         value="<?= htmlspecialchars($reponses['site_superficie'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                  <span>m²</span>
                </div>
              </div>
              <div class="site-champ">
                <label for="usda"><?= Documentation::terme($termes, 'zone-climatique-usda', 'Zone climatique USDA') ?></label>
                <small>Le froid hivernal minimal du lieu, si vous le connaissez.</small>
                <input type="text" id="usda" name="usda" placeholder="Ex. 8b"
                       value="<?= htmlspecialchars($reponses['site_usda'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
              </div>
              <fieldset class="site-champ">
                <legend>Exposition dominante</legend>
                <div class="site-choix">
                  <label><input type="radio" class="radio" name="exposition" value="nord"<?= ($reponses['site_exposition'] ?? 'plat') === 'nord' ? ' checked' : '' ?>>Nord</label>
                  <label><input type="radio" class="radio" name="exposition" value="sud"<?= ($reponses['site_exposition'] ?? 'plat') === 'sud' ? ' checked' : '' ?>>Sud</label>
                  <label><input type="radio" class="radio" name="exposition" value="est"<?= ($reponses['site_exposition'] ?? 'plat') === 'est' ? ' checked' : '' ?>>Est</label>
                  <label><input type="radio" class="radio" name="exposition" value="ouest"<?= ($reponses['site_exposition'] ?? 'plat') === 'ouest' ? ' checked' : '' ?>>Ouest</label>
                  <label><input type="radio" class="radio" name="exposition" value="plat"<?= ($reponses['site_exposition'] ?? 'plat') === 'plat' ? ' checked' : '' ?>>Plat</label>
                </div>
              </fieldset>
              <fieldset class="site-champ">
                <legend>Pente</legend>
                <div class="site-choix">
                  <label><input type="radio" class="radio" name="pente" value="plate"<?= ($reponses['site_pente'] ?? 'plate') === 'plate' ? ' checked' : '' ?>>Plate</label>
                  <label><input type="radio" class="radio" name="pente" value="legere"<?= ($reponses['site_pente'] ?? 'plate') === 'legere' ? ' checked' : '' ?>>Légère</label>
                  <label><input type="radio" class="radio" name="pente" value="marquee"<?= ($reponses['site_pente'] ?? 'plate') === 'marquee' ? ' checked' : '' ?>>Marquée</label>
                  <label><input type="radio" class="radio" name="pente" value="forte"<?= ($reponses['site_pente'] ?? 'plate') === 'forte' ? ' checked' : '' ?>>Forte</label>
                </div>
              </fieldset>
            </div>

            <div class="site-colonne">
              <p class="site-groupe-titre">Ce que vous observez</p>
              <div class="site-champ">
                <label for="climat">Conditions climatiques particulières</label>
                <small>Gel tardif, vents forts, sécheresse estivale…</small>
                <textarea id="climat" name="climat" rows="3" placeholder="Ce que vous observez d'inhabituel, ou dont on vous a parlé."><?= htmlspecialchars($reponses['site_climat'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
              </div>
            </div>
          </div>

          <button type="submit" class="site-bouton">Continuer</button>
          <p class="site-note">Enregistré dès que vous continuez — vous pouvez revenir sans rien perdre.</p>
        </form>
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
      <p>Cette étape situe le terrain, pas encore le projet dessus : rien ici n'est
         un diagnostic précis — celui-là vient en phase Conception (étude du site,
         étude du sol), fiche en main, sur place. Une estimation suffit partout où
         vous hésitez.</p>
      <p>La zone climatique USDA classe les lieux par leur froid hivernal minimal :
         elle ne dit rien de l'été, mais elle borne ce qui peut passer l'hiver sans
         protection.</p>
    </div>
  </aside>

  <footer class="pied">
    <span class="pied-mention">Syntropia — en construction</span>
    <span class="pied-spacer"></span>
  </footer>

</div>

<script src="assets/js/etapes.js"></script>
<script src="assets/js/gabarit.js"></script>
<script src="assets/js/carte.js"></script>
<script>
  Gabarit.init({ etape: 'definition-site' });
</script>
</body>
</html>
