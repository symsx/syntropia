<?php

declare(strict_types=1);

require __DIR__ . '/../src/autoload.php';
require __DIR__ . '/../src/Configuration.php';
require __DIR__ . '/../src/Database.php';

use Syntropia\Configuration;
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $auth->jetonValide($_POST['csrf'] ?? null)) {
    $present = array_map('strval', (array) ($_POST['present'] ?? []));
    $reponsesService->enregistrerLot($siteId, $participantId, 'definition', [
        'site_commune'         => (string) ($_POST['commune'] ?? ''),
        'site_coordonnees'     => (string) ($_POST['coordonnees'] ?? ''),
        'site_superficie'      => (string) ($_POST['superficie'] ?? ''),
        'site_usda'            => (string) ($_POST['usda'] ?? ''),
        'site_exposition'      => (string) ($_POST['exposition'] ?? ''),
        'site_pente'           => (string) ($_POST['pente'] ?? ''),
        'site_climat'          => (string) ($_POST['climat'] ?? ''),
        'site_present'         => implode(',', $present),
        'site_present_detail'  => (string) ($_POST['present_detail'] ?? ''),
    ]);
    header('Location: ' . Configuration::base() . 'conception-etude-sol.php');
    exit;
}
$reponses = $reponsesService->pourParticipant($participantId);
$presentChoisi = array_filter(explode(',', $reponses['site_present'] ?? ''));
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

  .site-bouton{
    width:100%; margin-top:.4rem; padding:.6rem; border:0; border-radius:.6rem;
    background:var(--accent); color:var(--bouton-encre); font:600 .92rem var(--titre); cursor:pointer;
  }
  .site-bouton:hover{ opacity:.92; }
  .site-note{ font:400 .76rem var(--texte); color:var(--muted); text-align:center; margin-top:.5rem; }
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
          <div class="site-colonnes">
            <div class="site-colonne">
              <div class="site-champ">
                <label for="commune">Commune</label>
                <input type="text" id="commune" name="commune" placeholder="Ex. Saint-André-de-Cubzac"
                       value="<?= htmlspecialchars($reponses['site_commune'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
              </div>
              <div class="site-champ">
                <label for="coordonnees">Coordonnées</label>
                <small>Si vous les avez — une carte suffit sinon.</small>
                <input type="text" id="coordonnees" name="coordonnees" placeholder="45.02, -0.44"
                       value="<?= htmlspecialchars($reponses['site_coordonnees'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
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
                <label for="usda">Zone climatique USDA</label>
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
              <div class="site-champ">
                <label for="climat">Conditions climatiques particulières</label>
                <small>Gel tardif, vents forts, sécheresse estivale…</small>
                <textarea id="climat" name="climat" rows="3" placeholder="Ce que vous observez d'inhabituel, ou dont on vous a parlé."><?= htmlspecialchars($reponses['site_climat'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
              </div>
              <fieldset class="site-champ">
                <legend>Déjà en présence sur le terrain</legend>
                <small>Ce qui existe peut se réutiliser à la phase Conception.</small>
                <div class="site-choix" style="flex-direction:column; align-items:flex-start; gap:.45rem;">
                  <label><input type="checkbox" class="case" name="present[]" value="pieds-meres"<?= in_array('pieds-meres', $presentChoisi, true) ? ' checked' : '' ?>>Pieds-mères pour le bouturage</label>
                  <label><input type="checkbox" class="case" name="present[]" value="graines"<?= in_array('graines', $presentChoisi, true) ? ' checked' : '' ?>>Graines à récolter</label>
                  <label><input type="checkbox" class="case" name="present[]" value="vegetaux-utiles"<?= in_array('vegetaux-utiles', $presentChoisi, true) ? ' checked' : '' ?>>Végétaux utiles au projet</label>
                </div>
              </fieldset>
              <div class="site-champ">
                <label for="present-detail">Précisions</label>
                <textarea id="present-detail" name="present_detail" rows="2" placeholder="Quoi, où, en quelle quantité approximative."><?= htmlspecialchars($reponses['site_present_detail'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
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
<script>
  Gabarit.init({ etape: 'definition-site' });
</script>
</body>
</html>
