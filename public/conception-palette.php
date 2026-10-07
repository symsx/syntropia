<?php

declare(strict_types=1);

/**
 * La palette végétale d'une parcelle — l'étape avant son plan.
 *
 * Chantier `chantier-palette-vegetale.md`, Phase 1. La parcelle naît ici (code, nom, type, conduite, tortue) ; puis sa
 * palette se compose en trois onglets, dans l'ordre du ch. 11 de Théry : les productions principales, les productions
 * secondaires, les plantes de service par fonction (`Palette::FONCTIONS`). Chaque plante dit ce qu'elle demande pour
 * s'obtenir (son type de plant, au cadre des espèces). Les plantes de service d'une autre parcelle se reprennent d'un
 * geste.
 *
 * Le plan (bordures, lignes, lianes), le scénario et le potager puisent dans la palette (Phase 4) ; une espèce qui y est
 * employée ne se retire pas (`Palettes::emplois()`).
 *
 * Toute écriture passe par `Palettes` (vérifie la parcelle et l'espèce, sous verrou) ; un POST finit par une
 * redirection, avec son message.
 */

require __DIR__ . '/../src/autoload.php';
require __DIR__ . '/../src/Configuration.php';
require __DIR__ . '/../src/Database.php';

use Syntropia\Conduite;
use Syntropia\Configuration;
use Syntropia\Documentation;
use Syntropia\Palette;
use Syntropia\Role;
use Syntropia\Tortue;
use Syntropia\Web\Authentification;
use Syntropia\Web\Especes;
use Syntropia\Web\Heritage;
use Syntropia\Web\Palettes;
use Syntropia\Web\Parcelles;
use Syntropia\Web\Reponses;
use Syntropia\Web\Scenarios;
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
$peutEcrire = $sites->peutEcrireSur($siteId, $compteId);
$parcellesService = new Parcelles();
$palettes = new Palettes();

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$base = Configuration::base() . 'conception-palette.php';

$erreursCreation = [];
$saisie = ['code' => '', 'nom' => '', 'type' => 'perenne', 'conduite' => '', 'tortue' => ''];
$flash = $_SESSION['syntropia_flash_palette'] ?? null;
unset($_SESSION['syntropia_flash_palette']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $auth->jetonValide($_POST['csrf'] ?? null)) {
    $action = (string) ($_POST['action'] ?? '');
    $parcelleId = (int) ($_POST['parcelle_id'] ?? 0);
    $onglet = (string) ($_POST['onglet'] ?? '');
    $fonction = (string) ($_POST['fonction'] ?? '');
    $retour = $base . '?parcelle=' . $parcelleId . (isset(Palette::PLACES[$onglet]) ? '&onglet=' . $onglet : '')
        . (isset(Palette::FONCTIONS[$fonction]) ? '&fonction=' . $fonction : '');
    if (!$peutEcrire) {
        $erreursCreation = ['droit' => 'Vous lisez ce projet : il ne se modifie pas depuis votre compte.'];
        $_SESSION['syntropia_flash_palette'] = ['alerte', $erreursCreation['droit']];
        if ($action !== 'creer') {
            header('Location: ' . $retour);
            exit;
        }
    } elseif ($action === 'creer') {
        foreach (array_keys($saisie) as $cle) {
            $saisie[$cle] = (string) ($_POST[$cle] ?? $saisie[$cle]);
        }
        $r = $parcellesService->creer($siteId, $saisie['code'], $saisie['nom'], $saisie['type'], $saisie['conduite'], $saisie['tortue']);
        if ($r['erreurs'] === []) {
            $_SESSION['syntropia_flash_palette'] = ['ok', 'La parcelle est ouverte. Choisissez maintenant ce qu’elle doit produire.'];
            header('Location: ' . $base . '?parcelle=' . $r['id']);
            exit;
        }
        $erreursCreation = $r['erreurs'];
    } else {
        try {
            $message = match ($action) {
                'choisir' => $palettes->choisir($parcelleId, $siteId, (int) ($_POST['espece'] ?? 0), $onglet,
                    $onglet === 'service' ? $fonction : null),
                'retirer' => $palettes->retirer($parcelleId, $siteId, (int) ($_POST['ligne'] ?? 0),
                    $onglet === 'service' ? $fonction : null),
                'reprendre' => $palettes->reprendre($parcelleId, $siteId, (int) ($_POST['depuis'] ?? 0)),
                default => throw new RuntimeException('Ce geste n’est pas reconnu.'),
            };
            $_SESSION['syntropia_flash_palette'] = ['ok', $message];
        } catch (RuntimeException $e) {
            $_SESSION['syntropia_flash_palette'] = ['alerte', $e->getMessage()];
        }
        header('Location: ' . $retour);
        exit;
    }
}

$parcelles = $parcellesService->dUnSite($siteId);
$nouvelle = ($_GET['nouvelle'] ?? '') === '1' || $parcelles === [] || $erreursCreation !== [];
$parcelle = null;
if (!$nouvelle) {
    $demande = (int) ($_GET['parcelle'] ?? 0);
    foreach ($parcelles as $p) {
        if ((int) $p['id'] === $demande) {
            $parcelle = $p;
            break;
        }
    }
    $parcelle ??= $parcelles[0];
}
$idP = $parcelle !== null ? (int) $parcelle['id'] : 0;

$nomAffiche = $auth->nomAffiche() ?? '';
$initiales = '';
foreach (preg_split('/\s+/', trim($nomAffiche)) ?: [] as $mot) {
    if ($mot !== '') {
        $initiales .= mb_strtoupper(mb_substr($mot, 0, 1));
    }
}
$initiales = mb_substr($initiales, 0, 2) ?: '?';
$termes = Documentation::glossaire(__DIR__ . '/../contenu/glossaire.md');

/* ------------------------------------------------------------------ la palette de la parcelle */

$especes = [];
$lignes = [];
$parPlace = ['principale' => [], 'secondaire' => [], 'service' => []];
$parFonction = array_fill_keys(array_keys(Palette::FONCTIONS), []);
$autresParcelles = [];
$emplois = [];
$souhaits = ['souhaits' => [], 'de' => null];
$moyens = [];
$onglet = 'principale';
$fonctionActive = array_key_first(Palette::FONCTIONS);
if ($parcelle !== null) {
    foreach ((new Especes())->toutes($siteId) as $e) {
        $especes[mb_strtolower((string) $e['nom_scientifique']) . '|' . $e['variete']] = $e;
    }
    $lignes = $palettes->dUneParcelle($idP);
    // Où chaque espèce sert déjà : une espèce employée ne sort pas de la palette (décision du porteur, 2026-10-05).
    $emplois = $palettes->emplois($idP, $siteId);
    foreach ($lignes as $l) {
        $l['espece'] = $especes[mb_strtolower((string) $l['nom_scientifique']) . '|' . $l['variete']] ?? null;
        $l['nom'] = Palette::nom($l['espece'] ?? $l);
        if ($l['place'] !== 'service') {
            $parPlace[$l['place']][] = $l;
        }
        foreach (Palette::fonctionsDe($l['fonctions']) as $f) {
            $parFonction[$f][] = $l;
        }
        if ($l['place'] === 'service') {
            $parPlace['service'][] = $l;
        }
    }
    $services = $palettes->servicesParParcelle($siteId);
    foreach ($parcelles as $p) {
        if ((int) $p['id'] !== $idP && ($services[(int) $p['id']] ?? 0) > 0) {
            $autresParcelles[] = $p + ['n_services' => $services[(int) $p['id']]];
        }
    }
    $souhaits = (new Scenarios())->souhaitsDuSite($siteId, $sites->participantDuCompte($siteId, $compteId, $nomAffiche ?: 'Participant'));
    $moyens = (new Reponses())->definitionDuProjet($siteId, $compteId)['reponses'];

    $onglet = (string) ($_GET['onglet'] ?? '');
    if (!isset(Palette::PLACES[$onglet])) {
        $onglet = 'principale';
    }
    if (isset(Palette::FONCTIONS[(string) ($_GET['fonction'] ?? '')])) {
        $fonctionActive = (string) $_GET['fonction'];
    }
}
$conduite = $parcelle !== null ? Heritage::conduite($parcelle, $site) : null;
$tortue = $parcelle !== null ? Tortue::depuis($parcelle['tortue'] ?? null) : null;

/** Ce que demande une espèce pour s'obtenir : son type de plant, au cadre. */
$typePlant = static fn (?array $e): string => trim((string) ($e['type_plant'] ?? ''));

/**
 * Les options du choix d'une espèce : d'abord celles que le référentiel conseille, puis les autres ; sans celles qui ont
 * déjà cette place ou cette fonction.
 *
 * @param callable(array): bool $conseillee
 * @param list<array<string, mixed>> $deja
 */
$options = static function (array $especes, callable $conseillee, array $deja, string $titreConseillees) use ($h, $typePlant): string {
    $pris = [];
    foreach ($deja as $l) {
        $pris[mb_strtolower((string) $l['nom_scientifique']) . '|' . $l['variete']] = true;
    }
    $groupes = [[], []];
    foreach ($especes as $cle => $e) {
        if (isset($pris[$cle])) {
            continue;
        }
        $t = $typePlant($e);
        $groupes[$conseillee($e) ? 0 : 1][] = '<option value="' . (int) $e['id'] . '">' . $h(Palette::nom($e) . ($t !== '' ? ' — ' . $t : '')) . '</option>';
    }
    $html = '<option value="">Choisir une espèce…</option>';
    if ($groupes[0] !== []) {
        $html .= '<optgroup label="' . $h($titreConseillees) . '">' . implode('', $groupes[0]) . '</optgroup>';
    }
    if ($groupes[1] !== []) {
        $html .= '<optgroup label="Les autres espèces">' . implode('', $groupes[1]) . '</optgroup>';
    }
    return $html;
};

/** Une plante de la palette, en pastille : son nom, ce qu'elle demande pour s'obtenir, et la croix qui la retire. */
$puce = static function (array $l, string $onglet, ?string $fonction, string $repere = '') use ($h, $auth, $idP, $peutEcrire, $typePlant, &$emplois): string {
    $t = $typePlant($l['espece']);
    $ou = $emplois[Palettes::cle($l)] ?? [];
    // Le retrait qui ferait sortir l'espèce de la palette : une production sans service, ou le dernier service.
    $sortirait = $fonction === null ? Palette::fonctionsDe($l['fonctions']) === []
        : ($l['place'] === 'service' && Palette::fonctionsDe($l['fonctions']) === [$fonction]);
    $info = ($t !== '' ? 'Pour l’obtenir : ' . $t . '.' : 'Le type de plant n’est pas encore noté pour cette espèce.')
        . ($ou !== [] ? ' Employée dans ' . implode(' et ', $ou) . '.' : '')
        . ($l['espece'] === null ? ' Cette espèce n’est plus dans le référentiel du projet.' : '');
    $html = '<li class="pa-puce" tabindex="0" data-info="' . $h($info) . '"><span>' . $h($l['nom']) . '</span>'
        . ($repere !== '' ? '<small>' . $h($repere) . '</small>' : '');
    if ($peutEcrire && !($sortirait && $ou !== [])) {
        $html .= '<form method="post"><input type="hidden" name="csrf" value="' . $h($auth->jeton()) . '">'
            . '<input type="hidden" name="action" value="retirer"><input type="hidden" name="parcelle_id" value="' . $idP . '">'
            . '<input type="hidden" name="onglet" value="' . $h($onglet) . '"><input type="hidden" name="ligne" value="' . (int) $l['id'] . '">'
            . ($fonction !== null ? '<input type="hidden" name="fonction" value="' . $h($fonction) . '">' : '')
            . '<button type="submit" class="pa-retirer" aria-label="Retirer ' . $h($l['nom']) . '" title="Retirer">×</button></form>';
    }
    return $html . '</li>';
};

/** Le formulaire d'ajout d'une espèce à une place ou à une fonction. */
$ajout = static function (string $onglet, ?string $fonction, string $optionsHtml, string $bouton) use ($h, $auth, $idP): string {
    return '<form method="post" class="pa-ajout"><input type="hidden" name="csrf" value="' . $h($auth->jeton()) . '">'
        . '<input type="hidden" name="action" value="choisir"><input type="hidden" name="parcelle_id" value="' . $idP . '">'
        . '<input type="hidden" name="onglet" value="' . $h($onglet) . '">'
        . ($fonction !== null ? '<input type="hidden" name="fonction" value="' . $h($fonction) . '">' : '')
        . '<select name="espece" required aria-label="Espèce à ajouter">' . $optionsHtml . '</select>'
        . '<button type="submit" class="pa-bouton">' . $h($bouton) . '</button></form>';
};
$repereDePlace = static fn (array $l): string => match ($l['place']) {
    'principale' => 'production principale',
    'secondaire' => 'production secondaire',
    default => '',
};
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>La palette — Syntropia</title>
<link rel="stylesheet" href="assets/css/gabarit.css?v=<?= (int) @filemtime(__DIR__ . '/assets/css/gabarit.css') ?>">
<style>
  /* pas de bandeau d'outils sur cette page : les tirettes se calent sur le seul bandeau général */
  :root{ --haut-bandeaux: 3.4rem; }

  /* la naissance d'une parcelle */
  .parc-creation{ width:100%; max-width:26rem; padding-top:1.9rem; }   /* la place de l’onglet « Informations » */
  .parc-annonce{ font:400 .92rem/1.5 var(--texte); color:var(--muted); margin:0 0 1.1rem; text-align:center; }
  .parc-champ{ margin-bottom:.8rem; border:0; padding:0; }
  .parc-champ label, .parc-champ legend{ display:block; font:600 .85rem var(--titre); color:var(--ink); margin-bottom:.3rem; padding:0; }
  .parc-champ small{ display:block; font:400 .76rem/1.3 var(--texte); color:var(--muted); margin-bottom:.3rem; }
  .parc-champ input[type=text], .parc-champ select{
    width:100%; padding:.45rem .7rem; font:400 .9rem var(--texte); color:var(--ink);
    background:var(--surface); border:1px solid var(--line); border-radius:.5rem;
  }
  .parc-champ input:focus, .parc-champ select:focus{ outline:2px solid var(--accent); outline-offset:1px; }
  .parc-deux{ display:grid; grid-template-columns:1fr 1fr; gap:.7rem; }
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
  /* un écran bas : les aides sous les libellés se retirent, le formulaire tient sans défiler */
  @media (max-height:40rem){
    .parc-champ small{ display:none; }
    .parc-annonce{ margin-bottom:.6rem; font-size:.85rem; }
    .parc-champ{ margin-bottom:.55rem; }
    .parc-annuler{ margin-top:.4rem; }
  }

  /* la palette : elle remplit le bureau, et se réduit au lieu de déborder */
  #bureau{ padding:.4rem .6rem; align-items:stretch; }
  /* en haut, la place de l'onglet « Informations », qui recouvre le haut du bureau */
  .pa-centre{ width:100%; max-width:62rem; margin:0 auto; min-height:0; display:flex; flex-direction:column; gap:.55em;
    padding:1.9rem .4em .2em; overflow:hidden; font-size:16px; }
  .pa-entete{ display:flex; align-items:center; gap:.7em; flex-wrap:wrap; }
  .pa-titre{ font:600 1.05em var(--titre); color:var(--accent-titre); margin:0; }
  .pa-badge{ font:500 .72em var(--titre); color:var(--muted); border:1px solid var(--line); border-radius:1em; padding:.1em .6em; }
  .pa-suite{ margin-left:auto; font:600 .8em var(--titre); color:var(--accent-titre); text-decoration:none; }
  .pa-suite:hover{ text-decoration:underline; }
  .pa-message{ margin:0; padding:.4em .7em; border-radius:.5em; font:400 .82em/1.35 var(--texte);
    background:var(--accent-fond); border:1px solid var(--accent); color:var(--accent-titre); }
  .pa-message--alerte{ border-color:var(--alerte); color:var(--alerte); background:transparent; }
  .pa-message a{ color:inherit; font-weight:600; }

  .pa-radio{ position:absolute; opacity:0; pointer-events:none; }
  .pa-onglets{ display:flex; gap:.3em; border-bottom:1px solid var(--line); flex:none; }
  .pa-onglet{ flex:1; text-align:center; padding:.45em .4em; cursor:pointer; border-radius:.5em .5em 0 0;
    font:500 .84em var(--titre); color:var(--muted); border:1px solid transparent; border-bottom:0; }
  .pa-onglet b{ font-weight:600; font-variant-numeric:tabular-nums; }
  .pa-radio:focus-visible + .pa-onglet{ outline:2px solid var(--accent); }
  .pa-centre:has(#o-principale:checked) label[for=o-principale],
  .pa-centre:has(#o-secondaire:checked) label[for=o-secondaire],
  .pa-centre:has(#o-service:checked) label[for=o-service]{
    color:var(--accent-titre); background:var(--accent-fond); border-color:var(--line); font-weight:600; }
  .pa-panneau{ display:none; flex:1; min-height:0; flex-direction:column; gap:.6em; }
  .pa-centre:has(#o-principale:checked) .pa-p-principale,
  .pa-centre:has(#o-secondaire:checked) .pa-p-secondaire,
  .pa-centre:has(#o-service:checked) .pa-p-service{ display:flex; }

  .pa-intro{ margin:0; font:400 .86em/1.5 var(--texte); color:var(--ink); }
  .pa-rappel{ margin:0; padding:.45em .7em; border-left:3px solid var(--accent); font:400 .8em/1.45 var(--texte); color:var(--muted); }
  .pa-rappel b{ color:var(--ink); font-weight:600; }
  .pa-rappel a{ color:var(--accent-titre); }
  .pa-puces{ list-style:none; margin:0; padding:0; display:flex; flex-wrap:wrap; gap:.35em; }
  .pa-puce{ display:inline-flex; align-items:center; gap:.35em; padding:.2em .25em .2em .65em; border-radius:.55em;
    font:500 .86em/1.25 var(--texte); color:var(--ink); background:var(--surface);
    border:1px solid color-mix(in srgb, var(--ink) 28%, transparent); }
  .pa-puce small{ color:var(--muted); font-size:.8em; }
  .pa-puce form{ margin:0; display:flex; }
  .pa-puce:focus{ outline:2px solid var(--accent); outline-offset:1px; }
  .pa-retirer{ border:0; background:transparent; color:var(--muted); font:600 1.05em var(--texte); cursor:pointer;
    width:1.4em; height:1.4em; border-radius:50%; line-height:1; padding:0; }
  .pa-retirer:hover, .pa-retirer:focus{ color:var(--alerte); background:var(--accent-fond); outline:none; }
  .pa-vide{ margin:0; font:italic .84em var(--texte); color:var(--muted); }
  .pa-ajout{ display:flex; gap:.45em; align-items:center; flex-wrap:wrap; }
  .pa-ajout select{ flex:1 1 16em; min-width:0; padding:.4em .5em; font:400 .86em var(--texte); color:var(--ink);
    background:var(--surface); border:1px solid var(--line); border-radius:.45em; }
  .pa-ajout select:focus{ outline:2px solid var(--accent); outline-offset:1px; }
  .pa-bouton{ font:600 .8em var(--titre); color:var(--bouton-encre); background:var(--accent); border:0; border-radius:.5em;
    padding:.45em .85em; cursor:pointer; white-space:nowrap; }
  .pa-bouton--calme{ background:transparent; color:var(--accent-titre); border:1px solid var(--accent); }
  .pa-note{ margin:0; font:400 .76em/1.4 var(--texte); color:var(--muted); }
  .pa-note a{ color:var(--accent-titre); }

  /* les plantes de service : les neuf fonctions à gauche, celle qu'on regarde à droite */
  /* à sa hauteur naturelle : ce qui dépasse fait réduire toute la palette (plus bas), au lieu de passer sous la reprise */
  .pa-service{ display:grid; grid-template-columns:minmax(11em, 15em) 1fr; gap:.8em; flex:none; }
  .pa-fonctions{ list-style:none; margin:0; padding:0; display:flex; flex-direction:column; gap:.15em; }
  .pa-fonction{ display:flex; justify-content:space-between; gap:.5em; padding:.32em .6em; border-radius:.45em; cursor:pointer;
    font:500 .8em var(--titre); color:var(--ink); border:1px solid transparent; }
  .pa-fonction b{ font-weight:500; color:var(--muted); font-variant-numeric:tabular-nums; }
  .pa-fonction:hover{ background:var(--accent-fond); }
  .pa-radio:focus-visible + .pa-fonction{ outline:2px solid var(--accent); }
  .pa-detail{ display:none; flex-direction:column; gap:.6em; min-width:0; }
  .pa-detail h2{ margin:0; font:600 .95em var(--titre); color:var(--accent-titre); }
<?php foreach (array_keys(Palette::FONCTIONS) as $f): ?>
  .pa-centre:has(#f-<?= $f ?>:checked) label[for=f-<?= $f ?>]{ background:var(--accent-fond); border-color:var(--line); color:var(--accent-titre); font-weight:600; }
  .pa-centre:has(#f-<?= $f ?>:checked) .pa-d-<?= $f ?>{ display:flex; }
<?php endforeach; ?>
  .pa-reprise{ display:flex; gap:.45em; align-items:center; flex-wrap:wrap; margin-top:auto; padding-top:.5em; border-top:1px solid var(--line); }
  .pa-reprise span{ font:400 .8em var(--texte); color:var(--ink); }
  .pa-reprise select{ padding:.35em .5em; font:400 .82em var(--texte); color:var(--ink); background:var(--surface);
    border:1px solid var(--line); border-radius:.45em; }

  @media (max-width:40rem){
    .pa-service{ grid-template-columns:1fr; grid-template-rows:auto 1fr; }
    .pa-fonctions{ flex-direction:row; flex-wrap:wrap; }
    .pa-fonction{ border-color:var(--line); }
    .pa-fonction b{ display:none; }
    .pa-onglet{ font-size:.76em; padding:.4em .2em; }
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
          <span class="compte-avatar"><?= $h($initiales) ?></span>
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
          <p class="pg-titre pg-nomsite"><?= $h($site['nom']) ?></p>
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
<?php foreach ($parcelles as $p): $estActive = $parcelle !== null && (int) $p['id'] === $idP; ?>
            <li><a href="conception-palette.php?parcelle=<?= (int) $p['id'] ?>"<?= $estActive ? ' class="active" aria-current="page"' : '' ?>><span class="pg-puce">✿</span><span class="pg-libelle"><?= $h($p['code']) ?> — <?= $h($p['nom']) ?></span></a></li>
<?php endforeach; ?>
            <li><a href="conception-palette.php?nouvelle=1"<?= $nouvelle ? ' class="active" aria-current="page"' : '' ?>><span class="pg-puce">+</span><span class="pg-libelle">Nouvelle parcelle</span></a></li>
          </ul>
        </div>
<?php if ($parcelle !== null): ?>
        <div class="pg-section">
          <p class="pg-titre"><?= $h($parcelle['code']) ?> — <?= $h($parcelle['nom']) ?></p>
          <ul class="pg-liste">
            <li><a href="conception-palette.php?parcelle=<?= $idP ?>" class="active" aria-current="page"><span class="pg-puce">✿</span><span class="pg-libelle">La palette</span></a></li>
            <li><a href="conception-parcelles.php?parcelle=<?= $idP ?>"><span class="pg-puce">▤</span><span class="pg-libelle">Le plan</span></a></li>
            <li><a href="conception-scenario.php?parcelle=<?= $idP ?>"><span class="pg-puce">≋</span><span class="pg-libelle">Le scénario</span></a></li>
            <li><a href="conception-materiel-et-eau.php?parcelle=<?= $idP ?>"><span class="pg-puce">◍</span><span class="pg-libelle">Matériel et eau</span></a></li>
            <li><a href="#" class="a-venir" tabindex="-1" aria-disabled="true"><span class="pg-puce">✎</span><span class="pg-libelle">Le suivi</span></a></li>
          </ul>
        </div>
<?php endif; ?>
        <p class="pg-pied">Une parcelle commence par sa palette : ce qu'elle doit produire, et les plantes qui l'y aideront.</p>
      </div>
    </nav>

    <main class="bureau" id="bureau">
<?php if ($nouvelle): ?>
      <div class="parc-creation">
        <p class="parc-annonce"><?= $parcelles === [] ? 'Ouvrez votre première parcelle. Vous pourrez en ajouter d’autres ensuite.' : 'Ouvrez une nouvelle parcelle.' ?>
          Vous choisirez ensuite ce qu'elle doit produire, puis les plantes qui l'y aideront.</p>
<?php if (!$peutEcrire): ?>
        <p class="parc-erreur">Vous lisez ce projet : il ne se modifie pas depuis votre compte.</p>
<?php else: ?>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= $h($auth->jeton()) ?>">
          <input type="hidden" name="action" value="creer">
<?php if ($erreursCreation !== []): ?>
          <p class="parc-erreur"><?= $h(implode(' ', $erreursCreation)) ?></p>
<?php endif; ?>
          <div class="parc-deux">
            <div class="parc-champ">
              <label for="code">Code</label>
              <input type="text" id="code" name="code" placeholder="A, B, P1…" maxlength="32" required value="<?= $h($saisie['code']) ?>">
            </div>
            <div class="parc-champ">
              <label for="nom">Nom</label>
              <input type="text" id="nom" name="nom" placeholder="Le verger, la butte du bas…" required value="<?= $h($saisie['nom']) ?>">
            </div>
          </div>
          <fieldset class="parc-champ">
            <legend>Type</legend>
            <div class="parc-choix">
              <label><input type="radio" class="radio" name="type" value="perenne"<?= $saisie['type'] !== 'potager' ? ' checked' : '' ?>>Pérenne</label>
              <label><input type="radio" class="radio" name="type" value="potager"<?= $saisie['type'] === 'potager' ? ' checked' : '' ?>>Potager</label>
            </div>
          </fieldset>
          <div class="parc-champ">
            <label for="conduite">Conduite</label>
            <small>Elle donne le vocabulaire des strates du scénario.</small>
            <select id="conduite" name="conduite">
              <option value="">Comme le site : <?= $h(trim((string) ($site['conduite'] ?? '')) !== '' ? Conduite::depuis($site['conduite'])->libelle() : 'pas encore choisie') ?></option>
<?php foreach (Conduite::toutes() as $c): ?>
              <option value="<?= $h($c->value) ?>"<?= $saisie['conduite'] === $c->value ? ' selected' : '' ?>><?= $h($c->libelle()) ?></option>
<?php endforeach; ?>
            </select>
          </div>
          <div class="parc-champ">
            <label for="tortue">Le type de tortue</label>
            <small>Il dit ce que la parcelle portera sur ses planches, et oriente ce que l'atelier propose d'y planter.</small>
            <select id="tortue" name="tortue">
              <option value="">Pas encore dit</option>
<?php foreach (Tortue::tous() as $t): ?>
              <option value="<?= $h($t->value) ?>"<?= $saisie['tortue'] === $t->value ? ' selected' : '' ?>><?= $h($t->libelle()) ?></option>
<?php endforeach; ?>
            </select>
          </div>
          <button type="submit" class="parc-bouton">Ouvrir la parcelle</button>
        </form>
<?php endif; ?>
<?php if ($parcelles !== []): ?>
        <a class="parc-annuler" href="conception-palette.php?parcelle=<?= (int) $parcelles[0]['id'] ?>">Annuler, revenir à mes parcelles</a>
<?php endif; ?>
      </div>
<?php else: ?>
      <div class="pa-centre" id="pa-centre">
        <div class="pa-entete">
          <h1 class="pa-titre">La palette de <?= $h($parcelle['code']) ?>, <?= $h($parcelle['nom']) ?></h1>
          <span class="pa-badge"><?= ($parcelle['type'] ?? '') === 'potager' ? 'Potager' : 'Pérenne' ?></span>
          <span class="pa-badge"><?= $h($conduite['conduite']->libelle()) ?><?= $conduite['niveau'] === 'site' ? ' · comme le site' : ($conduite['niveau'] === 'aucun' ? ' · pas encore choisie' : '') ?></span>
<?php if ($tortue !== null): ?>
          <span class="pa-badge">Tortue <?= $h(mb_strtolower($tortue->libelle())) ?></span>
<?php endif; ?>
          <a class="pa-suite" href="conception-parcelles.php?parcelle=<?= $idP ?>">Le plan de la parcelle →</a>
        </div>
<?php if (is_array($flash)): ?>
        <p class="pa-message<?= $flash[0] === 'alerte' ? ' pa-message--alerte' : '' ?>" role="status"><?= $h($flash[1]) ?></p>
<?php endif; ?>

        <input class="pa-radio" type="radio" name="onglet" id="o-principale"<?= $onglet === 'principale' ? ' checked' : '' ?>>
        <input class="pa-radio" type="radio" name="onglet" id="o-secondaire"<?= $onglet === 'secondaire' ? ' checked' : '' ?>>
        <input class="pa-radio" type="radio" name="onglet" id="o-service"<?= $onglet === 'service' ? ' checked' : '' ?>>
        <div class="pa-onglets" role="tablist">
          <label class="pa-onglet" for="o-principale">1. Productions principales · <b><?= count($parPlace['principale']) ?></b></label>
          <label class="pa-onglet" for="o-secondaire">2. Productions secondaires · <b><?= count($parPlace['secondaire']) ?></b></label>
          <label class="pa-onglet" for="o-service">3. Plantes de service · <b><?= count(array_filter($lignes, static fn (array $l): bool => Palette::fonctionsDe($l['fonctions']) !== [])) ?></b></label>
        </div>

<?php foreach (['principale', 'secondaire'] as $place): ?>
        <section class="pa-panneau pa-p-<?= $place ?>">
<?php if ($place === 'principale'): ?>
          <p class="pa-intro">Ce que vous voulez <strong>impérativement</strong> produire sur cette parcelle. Tout se construit autour
            de ces plantes : elles se choisissent en premier.</p>
<?php else: ?>
          <p class="pa-intro">Un plus, que la parcelle donnera si la place le permet. Elles se choisissent après les productions
            principales, et ne sont pas obligatoires.</p>
<?php endif; ?>
<?php if ($souhaits['souhaits'] !== []): ?>
          <p class="pa-rappel"><b><?= $souhaits['de'] === 'porteur' ? 'Les productions souhaitées par le porteur du projet' : 'Les productions que vous souhaitez' ?></b>
            (étape <a href="definition-objectifs.php">Objectifs</a>) : <?= $h(implode(', ', $souhaits['souhaits'])) ?>.</p>
<?php endif; ?>
<?php if ($parPlace[$place] === []): ?>
          <p class="pa-vide"><?= $place === 'principale' ? 'Aucune production principale n’est encore choisie.' : 'Aucune production secondaire n’est choisie.' ?></p>
<?php else: ?>
          <ul class="pa-puces">
<?php foreach ($parPlace[$place] as $l): ?>
            <?= $puce($l, $place, null, Palette::fonctionsDe($l['fonctions']) !== [] ? 'rend aussi un service' : '') ?>

<?php endforeach; ?>
          </ul>
<?php endif; ?>
<?php if ($peutEcrire): ?>
          <?= $ajout($place, null, $options($especes, [Palette::class, 'deProduction'], $parPlace[$place], 'Notées comme productions dans Espèces'),
              $place === 'principale' ? 'Ajouter aux productions principales' : 'Ajouter aux productions secondaires') ?>

          <p class="pa-note">À côté de chaque espèce, ce qu'elle demande pour s'obtenir. Une espèce manque ? Ajoutez-la dans
            <a href="especes.php">Espèces</a>.</p>
<?php endif; ?>
        </section>
<?php endforeach; ?>

        <section class="pa-panneau pa-p-service">
          <p class="pa-intro">Les plantes qui aident la parcelle, rangées par fonction. Choisissez-les selon vos moyens et vos
            compétences : ce que chacune demande pour s'obtenir est noté à côté de son nom.</p>
<?php
$rappelMoyens = [];
if (trim($moyens['moyens_connaissances'] ?? '') !== '') {
    $rappelMoyens[] = '<b>ce que vous savez faire</b> : ' . $h(trim($moyens['moyens_connaissances']));
}
if (trim($moyens['moyens_temps'] ?? '') !== '') {
    $rappelMoyens[] = '<b>votre temps</b> : ' . $h(trim($moyens['moyens_temps']));
}
$budget = ['faible' => 'faibles', 'modere' => 'modérés', 'confortable' => 'confortables'][$moyens['moyens_financier'] ?? ''] ?? null;
if ($budget !== null) {
    $rappelMoyens[] = '<b>vos moyens financiers</b> : ' . $budget;
}
?>
<?php if ($rappelMoyens !== []): ?>
          <p class="pa-rappel">Vos moyens (étape <a href="definition-moyens.php">Moyens</a>) : <?= implode(' ; ', $rappelMoyens) ?>.<?php if (($moyens['moyens_financier'] ?? '') === 'faible'): ?>
            Avec peu de moyens, la <?= Documentation::terme($termes, 'rna', 'régénération naturelle assistée') ?> et le semis direct
            permettent de commencer : préférez les plantes qui se sèment, se bouturent ou se divisent.<?php endif; ?></p>
<?php endif; ?>
          <div class="pa-service">
            <ul class="pa-fonctions" role="tablist" aria-label="Les fonctions de service">
<?php foreach (Palette::FONCTIONS as $f => $def): ?>
              <li><input class="pa-radio" type="radio" name="fonction" id="f-<?= $f ?>"<?= $f === $fonctionActive ? ' checked' : '' ?>><label class="pa-fonction" for="f-<?= $f ?>"><?= $h($def['libelle']) ?> <b><?= count($parFonction[$f]) ?></b></label></li>
<?php endforeach; ?>
            </ul>
<?php foreach (Palette::FONCTIONS as $f => $def): ?>
            <div class="pa-detail pa-d-<?= $f ?>">
              <h2><?= $h($def['libelle']) ?></h2>
              <p class="pa-intro"><?= $h($def['apport']) ?></p>
<?php if ($parFonction[$f] === []): ?>
              <p class="pa-vide">Aucune plante n'est encore choisie pour cette fonction.</p>
<?php else: ?>
              <ul class="pa-puces">
<?php foreach ($parFonction[$f] as $l): ?>
                <?= $puce($l, 'service', $f, $repereDePlace($l)) ?>

<?php endforeach; ?>
              </ul>
<?php endif; ?>
<?php if ($peutEcrire): ?>
              <?= $ajout('service', $f, $options($especes, static fn (array $e): bool => Palette::conseillee($e, $f), $parFonction[$f], 'Conseillées pour cette fonction'), 'Ajouter') ?>

<?php endif; ?>
            </div>
<?php endforeach; ?>
          </div>
<?php if ($peutEcrire && $autresParcelles !== []): ?>
          <form method="post" class="pa-reprise">
            <input type="hidden" name="csrf" value="<?= $h($auth->jeton()) ?>">
            <input type="hidden" name="action" value="reprendre">
            <input type="hidden" name="parcelle_id" value="<?= $idP ?>">
            <input type="hidden" name="onglet" value="service">
            <span>Les plantes de service peuvent être les mêmes d'une parcelle à l'autre. Reprendre celles de</span>
            <select name="depuis" aria-label="Parcelle dont reprendre les plantes de service">
<?php foreach ($autresParcelles as $p): ?>
              <option value="<?= (int) $p['id'] ?>"><?= $h($p['code'] . ' — ' . $p['nom']) ?> (<?= (int) $p['n_services'] ?>)</option>
<?php endforeach; ?>
            </select>
            <button type="submit" class="pa-bouton pa-bouton--calme">Reprendre</button>
          </form>
<?php endif; ?>
        </section>
      </div>
<?php endif; ?>
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
<?php if ($nouvelle): ?>
      <p>Une parcelle naît ici, avec son code, son nom et son type. Une parcelle <strong>pérenne</strong> est portée par des
         arbres : verger, haie. Un <strong>potager</strong> porte des cultures de légumes, de feuilles et de racines. La conduite
         et le type de tortue se disent maintenant ou plus tard, dans le plan de la parcelle.</p>
      <p>Viennent ensuite la palette de la parcelle, puis son plan : ses dimensions, ses bordures et ses planches.</p>
<?php else: ?>
      <p>La palette réunit les plantes de la parcelle avant son dessin. Le plan, le scénario et le potager y puisent. Elle se compose dans l'ordre que donne Théry : les
         productions principales d'abord, car tout se construit autour d'elles, puis les productions secondaires, puis les
         plantes de service.</p>
      <p>Les plantes de service se rangent en neuf fonctions. Pour chacune, l'atelier met en tête les espèces que le
         référentiel lui associe. Une même plante peut rendre plusieurs services, et une production peut aussi en rendre un :
         un fruitier offre du nectar à sa floraison.</p>
      <p>À côté de chaque espèce est noté ce qu'elle demande pour s'obtenir : un plant en conteneur, une bouture, un éclat de
         touffe, un semis. Choisissez selon vos moyens et vos compétences. Le survol d'une plante choisie le rappelle.</p>
      <p>La croix retire une plante. Une production qui rend aussi un service reste dans la palette pour ce service.
         Une plante déjà employée dans le plan, le scénario ou le potager ne se retire pas : le survol dit où elle sert.
         « Reprendre » ajoute les plantes de service d'une autre parcelle du projet, que vous ajustez ensuite.</p>
<?php endif; ?>
    </div>
  </aside>

  <footer class="pied">
    <form class="pied-recherche" onsubmit="return false;">
      <input type="search" placeholder="Rechercher dans le site…" aria-label="Rechercher dans le site">
      <button type="submit" aria-label="Rechercher">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="10.5" cy="10.5" r="6.5"/><line x1="15.5" y1="15.5" x2="21" y2="21"/></svg>
      </button>
    </form>
    <span class="pied-spacer"></span>
  </footer>

</div>

<script src="assets/js/etapes.js?v=<?= (int) @filemtime(__DIR__ . '/assets/js/etapes.js') ?>"></script>
<script src="assets/js/gabarit.js?v=<?= (int) @filemtime(__DIR__ . '/assets/js/gabarit.js') ?>"></script>
<script>
  Gabarit.init({
    site: <?= json_encode((string) $site['nom'], JSON_UNESCAPED_UNICODE) ?>,
    etape: 'conception-palette'<?php if ($parcelle !== null): ?>,
    contexte: <?= json_encode($parcelle['code'] . ' — ' . $parcelle['nom'], JSON_UNESCAPED_UNICODE) ?><?php elseif ($nouvelle): ?>,
    contexte: 'nouvelle parcelle'<?php endif; ?>

  });

  (function(){
    // La palette ne défile jamais : si l'onglet ouvert déborde, sa taille se réduit jusqu'à tenir.
    var c = document.getElementById('pa-centre');
    function ajuster(){
      if (!c) return;
      var t = 16;
      c.style.fontSize = t + 'px';
      while (c.scrollHeight > c.clientHeight + 1 && t > 9) { t -= 0.5; c.style.fontSize = t + 'px'; }
    }
    ajuster();
    window.addEventListener('resize', ajuster);
    document.querySelectorAll('.pa-radio').forEach(function(r){ r.addEventListener('change', ajuster); });
    // Une bulle dit ce que chaque plante demande pour s'obtenir — jamais le title natif.
    document.querySelectorAll('.pa-puce[data-info]').forEach(function(p){
      function montrer(){ Gabarit.bulle.montrer(p, p.dataset.info, 'horizontal'); }
      p.addEventListener('mouseenter', montrer);
      p.addEventListener('focus', montrer);
      p.addEventListener('mouseleave', Gabarit.bulle.cacher);
      p.addEventListener('blur', Gabarit.bulle.cacher);
    });
  })();
</script>
</body>
</html>
