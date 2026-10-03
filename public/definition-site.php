<?php

declare(strict_types=1);

require __DIR__ . '/../src/autoload.php';
require __DIR__ . '/../src/Configuration.php';
require __DIR__ . '/../src/Database.php';

use Syntropia\Conduite;
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
    // Un lecteur regarde : les coordonnées du site (qui sont à tout le
    // projet, pas à lui) ne s'écrivent pas depuis son compte. `Reponses`
    // ignore de même ses réponses ; « Continuer » avance quand même.
    $erreurs = $sites->peutEcrireSur($siteId, $compteId)
        ? $sites->enregistrerParametres($siteId, (string) ($_POST['latitude'] ?? ''), (string) ($_POST['longitude'] ?? ''))
        : [];
    if ($erreurs === [] && $sites->peutEcrireSur($siteId, $compteId)
        && !$sites->enregistrerConduite($siteId, (string) ($_POST['conduite'] ?? ''))) {
        $erreurs = ['conduite' => 'La conduite est « Syntropie » ou « Jardin-forêt ».'];
    }
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
<link rel="stylesheet" href="assets/css/gabarit.css?v=<?= (int) @filemtime(__DIR__ . '/assets/css/gabarit.css') ?>">
<style>
  /* propre à cette étape : pas de bandeau d'outils (la saisie est le
     seul geste de la page) — recaler la tirette/panneau Informations
     sur un seul bandeau. Deux colonnes plutôt qu'une : cette étape a
     plus de champs que les précédentes, et une seule colonne les
     aurait fait déborder — RÈGLE DU BUREAU : aucun défilement. */
  :root{ --haut-bandeaux: 3.4rem; }

  .site-cadre{ width:100%; max-width:27rem; }
  .site-annonce{ font:400 .92rem/1.5 var(--texte); color:var(--muted); margin:0 0 .9rem; text-align:center; }
  /* trois onglets en CSS pur (un panneau à la fois) : les champs, tous
     ensemble, dépassaient le bureau sur un écran court, et le bureau ne
     défile jamais. Les groupes gardent leur sens — l'administratif (le
     lieu, mesurable sans expertise), l'observé (il faut y être allé),
     puis la conduite, qui n'est pas une observation mais un choix. Un
     champ d'un onglet caché reste dans le formulaire : « Continuer » envoie
     tout. */
  .site-radio{ position:absolute; opacity:0; pointer-events:none; }
  .site-onglets{ display:flex; gap:.3rem; margin-bottom:.8rem; border-bottom:1px solid var(--line); }
  .site-onglet{
    flex:1; text-align:center; padding:.4rem .3rem; cursor:pointer; border-radius:.5rem .5rem 0 0;
    font:500 .8rem var(--titre); color:var(--muted); border:1px solid transparent; border-bottom:0;
  }
  .site-onglet:hover{ color:var(--accent-titre); }
  .site-cadre:has(#o-lieu:checked) label[for=o-lieu],
  .site-cadre:has(#o-observe:checked) label[for=o-observe],
  .site-cadre:has(#o-conduite:checked) label[for=o-conduite]{
    color:var(--accent-titre); background:var(--accent-fond); border-color:var(--line); font-weight:600;
  }
  .site-cadre:has(.site-radio:focus-visible) .site-onglets{ outline:2px solid var(--accent); outline-offset:2px; }
  .site-panneau{ display:none; }
  .site-cadre:has(#o-lieu:checked) .site-lieu,
  .site-cadre:has(#o-observe:checked) .site-observe,
  .site-cadre:has(#o-conduite:checked) .site-conduite{ display:block; }

  .site-champ{ margin-bottom:.85rem; border:0; padding:0; }
  .site-champ label, .site-champ legend{
    display:block; font:600 .82rem var(--titre); color:var(--ink); margin-bottom:.3rem; padding:0;
  }
  .site-champ small{ display:block; font:400 .72rem/1.3 var(--texte); color:var(--muted); margin-bottom:.25rem; }
  .site-champ input[type=text], .site-champ input[type=number]{
    width:100%; padding:.45rem .6rem; font:400 .88rem var(--texte); color:var(--ink);
    background:var(--surface); border:1px solid var(--line); border-radius:.5rem;
  }
  .site-champ select{
    width:100%; padding:.45rem .6rem; font:400 .88rem var(--texte); color:var(--ink);
    background:var(--surface); border:1px solid var(--line); border-radius:.5rem;
  }
  .site-champ input:focus, .site-champ textarea:focus, .site-champ select:focus{ outline:2px solid var(--accent); outline-offset:1px; }
  .site-champ textarea{
    width:100%; padding:.45rem .6rem; font:400 .88rem/1.35 var(--texte); color:var(--ink);
    background:var(--surface); border:1px solid var(--line); border-radius:.5rem; resize:none;
  }
  .site-ligne{ display:flex; gap:.6rem; }
  .site-ligne .site-champ{ flex:1; }
  .site-unite{ display:flex; align-items:center; gap:.4rem; }
  .site-unite input{ flex:1; }
  .site-unite span{ font:400 .82rem var(--texte); color:var(--muted); }
  .site-estimer{
    padding:.42rem .8rem; border:1px solid var(--accent); border-radius:.5rem; background:transparent;
    color:var(--accent-titre); font:600 .8rem var(--titre); cursor:pointer; white-space:nowrap;
  }
  .site-estimer:hover:not(:disabled){ background:var(--accent-doux); }
  .site-estimer:disabled{ opacity:.6; cursor:progress; }
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

  /* petit écran : chaque onglet se resserre pour que le plus haut (le lieu,
     avec sa carte) tienne sous le bandeau sans défiler — capture d'écran
     sur téléphone du porteur, 2026-09-15. */
  @media (max-width:30rem){
    main#bureau{ padding:2.2rem 1rem .6rem; align-items:flex-start; }
    .site-annonce{ margin:0 0 .4rem; font-size:.78rem; line-height:1.3; }
    .site-onglets{ margin-bottom:.5rem; }
    .site-champ{ margin-bottom:.4rem; }
    .site-champ label, .site-champ legend{ margin-bottom:.15rem; }
    .site-champ small{ margin-bottom:.1rem; }
    .site-carte-champs{ margin-bottom:.25rem; }
    .site-carte{ height:6rem; }
    .site-choix{ gap:.3rem .5rem; }
    .site-champ textarea{ height:4.5rem; }
    .site-bouton{ margin-top:0; padding:.45rem; }
    .site-note{ margin-top:.25rem; font-size:.7rem; }
  }

  /* écran peu haut (portable 1280×720, 1024×600) : le bureau perd ses
     grandes marges, et la carte, seul élément dont la taille se règle,
     cède ce qu'il faut. Le bureau ne défile jamais. */
  @media (max-height:46rem){
    main#bureau{ padding:2.2rem 1.5rem .7rem; }   /* 2.2rem : la tirette « Informations » pend du bandeau */
    .site-annonce{ margin:0 0 .5rem; font-size:.84rem; line-height:1.35; }
    .site-onglets{ margin-bottom:.5rem; }
    .site-champ{ margin-bottom:.55rem; }
    .site-carte{ height:5.6rem; }
    .site-bouton{ margin-top:.1rem; padding:.5rem; }
    .site-note{ margin-top:.3rem; }
  }

  /* très bas (1024×600, téléphone 320×568) : l'annonce cède sa place — le
     panneau Informations dit la même chose — et la carte se réduit encore. */
  @media (max-height:38rem){
    .site-annonce{ display:none; }
    .site-carte{ height:3.6rem; }
    .site-champ{ margin-bottom:.4rem; }
    .site-champ small{ font-size:.68rem; line-height:1.2; }
    .site-champ-carte small{ display:none; }
    .site-note{ display:none; }
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
          <p class="pg-titre">Phase projet</p>
          <ul class="pg-liste pg-phase-projet"></ul>
        </div>
        <div class="pg-section">
          <p class="pg-titre">Définition</p>
          <ul class="pg-liste pg-etapes-phase"></ul>
        </div>
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
<?php
// Après une erreur, rouvrir l'onglet du champ fautif : un message dans un
// onglet caché ne dirait rien. La conduite est le seul champ hors du lieu
// qui peut échouer.
$ongletOuvert = isset($erreurs['conduite']) ? 'conduite' : 'lieu';
?>
          <input class="site-radio" type="radio" name="onglet" id="o-lieu"<?= $ongletOuvert === 'lieu' ? ' checked' : '' ?>>
          <input class="site-radio" type="radio" name="onglet" id="o-observe">
          <input class="site-radio" type="radio" name="onglet" id="o-conduite"<?= $ongletOuvert === 'conduite' ? ' checked' : '' ?>>
          <div class="site-onglets" role="tablist">
            <label class="site-onglet" for="o-lieu">Le lieu</label>
            <label class="site-onglet" for="o-observe">Observations</label>
            <label class="site-onglet" for="o-conduite">Conduite</label>
          </div>

          <div class="site-panneau site-lieu">
              <div class="site-champ">
                <label for="commune">Commune</label>
                <input type="text" id="commune" name="commune" placeholder="Ex. Saint-André-de-Cubzac"
                       value="<?= htmlspecialchars($reponses['site_commune'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
              </div>
              <div class="site-champ site-champ-carte">
                <label for="latitude">Position</label>
                <small>Cliquez sur la carte pour poser le point — ou saisissez les
                   coordonnées, la carte suivra.</small>
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
                <small id="usda-aide" data-defaut="Le froid hivernal minimal — à saisir, ou à estimer.">Le froid hivernal minimal — à saisir, ou à estimer.</small>
                <div class="site-unite">
                  <input type="text" id="usda" name="usda" placeholder="Ex. 8b"
                         value="<?= htmlspecialchars($reponses['site_usda'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                  <button type="button" class="site-estimer" id="usda-estimer">Estimer</button>
                </div>
              </div>
          </div>

          <div class="site-panneau site-observe">
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
              <div class="site-champ">
                <label for="climat">Conditions climatiques particulières</label>
                <small>Gel tardif, vents forts, sécheresse estivale…</small>
                <textarea id="climat" name="climat" rows="3" placeholder="Ce que vous observez d'inhabituel, ou dont on vous a parlé."><?= htmlspecialchars($reponses['site_climat'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
              </div>

          </div>

          <div class="site-panneau site-conduite">
<?php
$conduiteChoisie = Conduite::tryFrom((string) ($_POST['conduite'] ?? $site['conduite'] ?? ''));
$aideConduite = 'Décide du vocabulaire des strates du scénario : quatre en syntropie, neuf en jardin-forêt.';
$texteConduite = fn (?Conduite $c): string => $c === null ? $aideConduite
    : $c->explication() . ($c->reserve() !== null ? ' ' . $c->reserve() : '');
?>
              <div class="site-champ">
                <label for="conduite">Conduite</label>
                <small id="conduite-aide" data-defaut="<?= htmlspecialchars($aideConduite, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($texteConduite($conduiteChoisie), ENT_QUOTES, 'UTF-8') ?></small>
                <select id="conduite" name="conduite">
                  <option value="">— pas encore choisie —</option>
<?php foreach (Conduite::toutes() as $cas): ?>
                  <option value="<?= $cas->value ?>" data-explication="<?= htmlspecialchars($texteConduite($cas), ENT_QUOTES, 'UTF-8') ?>"<?= $conduiteChoisie === $cas ? ' selected' : '' ?>><?= htmlspecialchars($cas->libelle(), ENT_QUOTES, 'UTF-8') ?></option>
<?php endforeach; ?>
                </select>
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
      <p>La carte est un confort : ses tuiles viennent d'OpenStreetMap, un tiers, et rien ici
         n'en dépend — les deux coordonnées suffisent.</p>
      <p>La <strong>conduite</strong> dit dans quel monde vous plantez : en syntropie, on plante dense
         et on pense la succession, la taille est le moteur ; en jardin-forêt, chaque plante est posée à sa
         place définitive. Elle décide du vocabulaire des strates du scénario — quatre en syntropie, par le
         besoin en lumière ; neuf en jardin-forêt, par la place et la hauteur adulte. Une parcelle peut être
         conduite autrement que le site, et rien n'est gravé.</p>
      <p>La zone climatique USDA classe les lieux par leur froid hivernal minimal :
         elle ne dit rien de l'été, mais elle borne ce qui peut passer l'hiver sans
         protection.</p>
      <p>« Estimer » la calcule depuis la position posée sur la carte : la moyenne,
         sur trente ans, du jour le plus froid de chaque année. La maille météo fait
         quelques kilomètres — une cuvette où le froid s'accumule sera plus froide
         que l'estimation. Un écart d'une demi-zone est normal ; votre observation
         sur place prime.</p>
    </div>
  </aside>

  <footer class="pied">
    <span class="pied-mention">Syntropia — en construction</span>
    <span class="pied-spacer"></span>
  </footer>

</div>

<script src="assets/js/etapes.js?v=<?= (int) @filemtime(__DIR__ . '/assets/js/etapes.js') ?>"></script>
<script src="assets/js/gabarit.js?v=<?= (int) @filemtime(__DIR__ . '/assets/js/gabarit.js') ?>"></script>
<script src="assets/js/carte.js?v=<?= (int) @filemtime(__DIR__ . '/assets/js/carte.js') ?>"></script>
<script>
  Gabarit.init({ etape: 'definition-site' });

  // La carte se dessine à la taille de son cadre : un onglet caché lui donne
  // une taille nulle. Au retour sur « Le lieu », on la fait se redessiner.
  document.querySelectorAll('.site-radio').forEach(function(r){
    r.addEventListener('change', function(){ window.dispatchEvent(new Event('resize')); });
  });

  // La conduite choisie s'explique à l'endroit où on la choisit : le texte de
  // l'option prend la place de l'aide, sans ajouter de hauteur à une page qui
  // ne défile jamais.
  (function(){
    var choix = document.getElementById('conduite');
    var aide = document.getElementById('conduite-aide');
    choix.addEventListener('change', function(){
      var o = choix.options[choix.selectedIndex];
      aide.textContent = o.dataset.explication || aide.dataset.defaut;
    });
  })();

  // Estimer la zone USDA depuis la position du formulaire (même pas encore
  // enregistrée). Le résultat remplit le champ, qui reste modifiable ; le
  // détail (minimum moyen, période) prend la place de l'aide, pour ne pas
  // allonger une page qui ne défile jamais.
  (function(){
    var bouton = document.getElementById('usda-estimer');
    var champ = document.getElementById('usda');
    var aide = document.getElementById('usda-aide');
    bouton.addEventListener('click', function(){
      var corps = new URLSearchParams();
      corps.set('csrf', <?= json_encode($auth->jeton()) ?>);
      corps.set('site_id', <?= json_encode($siteId) ?>);
      corps.set('latitude', document.getElementById('latitude').value);
      corps.set('longitude', document.getElementById('longitude').value);
      bouton.disabled = true;
      bouton.textContent = 'Calcul…';
      aide.textContent = 'Trente ans de relevés météo à lire — quelques secondes.';
      fetch('api-usda.php', { method: 'POST', body: corps, credentials: 'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(j){
          if (j.ok) { champ.value = j.zone; }
          aide.textContent = j.message + (j.ok ? ' Corrigez si vous savez mieux.' : '');
        })
        .catch(function(){ aide.textContent = "Le calcul a échoué — saisissez la zone si vous la connaissez."; })
        .then(function(){ bouton.disabled = false; bouton.textContent = 'Estimer'; });
    });
  })();
</script>
</body>
</html>
