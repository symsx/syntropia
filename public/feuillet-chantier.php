<?php

declare(strict_types=1);

/**
 * Le feuillet du chantier de plantation d'une parcelle — à imprimer et à emporter sur la parcelle le jour de la plantation.
 *
 * Voir `chantier-dossier-projet.md`, Phase 5 (décision 6 du porteur : « un sous-dossier par parcelle, le document de
 * chantier pour l'implantation »), cadrée le 2026-10-05 : il s'ouvre depuis l'étape « Outils et plans » de la Réalisation,
 * une parcelle à la fois ; le plan sur une page en paysage, puis « où va quoi » en portrait — chaque ligne, ses plantes
 * dans l'ordre, leur espacement (pas la position de chaque plant), le nombre prévu, l'origine, la date prévue, et trois
 * colonnes à remplir au crayon : planté, nombre, notes. L'origine est celle du cadre des espèces, en attendant la palette
 * végétale (`chantier-palette-vegetale.md`).
 *
 * HORS DU GABARIT « LE BUREAU », comme `dossier-projet.php` dont il est le sous-dossier : la même exception, admise par
 * le porteur le 2026-09-19 pour un document destiné au papier, et pour rien d'autre. Le plan, ses sigles et ses
 * couleurs viennent de `PlanImprime`, les quantités de `MaterielDuSite` — les mêmes que le dossier et l'écran. Il
 * n'écrit rien : un lecteur l'imprime comme un porteur.
 */

require __DIR__ . '/../src/autoload.php';
require __DIR__ . '/../src/Configuration.php';
require __DIR__ . '/../src/Database.php';

use Syntropia\Bordures;
use Syntropia\Configuration;
use Syntropia\DessinsDossier;
use Syntropia\Materiel;
use Syntropia\Planches;
use Syntropia\Role;
use Syntropia\Scenario;
use Syntropia\Web\Authentification;
use Syntropia\Web\CalendrierLignes;
use Syntropia\Web\Cultures;
use Syntropia\Web\Heritage;
use Syntropia\Web\LignesPlantation;
use Syntropia\Web\MaterielDuSite;
use Syntropia\Web\Parcelles;
use Syntropia\Web\PlanImprime;
use Syntropia\Web\Sites;

Authentification::demarrer($_SERVER);
$auth = new Authentification();
$auth->exige(Role::Utilisateur);

$compteId = (int) $auth->compteId();
$site = (new Sites())->pourCompte($compteId);
if ($site === null) {
    header('Location: ' . Configuration::base() . 'definition-projet.php');
    exit;
}
$siteId = (int) $site['id'];
$p = (new Parcelles())->parId((int) ($_GET['parcelle'] ?? 0), $siteId);
if ($p === null) {
    header('Location: ' . Configuration::base() . 'realisation-outils-et-plans.php');
    exit;
}
$idP = (int) $p['id'];

$mois = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
$aujourdhui = new DateTimeImmutable('now', new DateTimeZone('Europe/Paris'));
/** Une date de la base (`AAAA-MM-JJ`) en mots ; `null` si elle n'est pas donnée. */
$dateEnMots = static function (?string $iso) use ($mois): ?string {
    $d = $iso === null ? false : DateTimeImmutable::createFromFormat('!Y-m-d', substr($iso, 0, 10));
    return $d === false ? null : (int) $d->format('j') . ($d->format('j') === '1' ? 'er' : '') . ' ' . $mois[(int) $d->format('n') - 1] . ' ' . $d->format('Y');
};
$dateEdition = (string) $dateEnMots($aujourdhui->format('Y-m-d'));
$h = static fn (string $t): string => htmlspecialchars($t, ENT_QUOTES, 'UTF-8');
$metres = static fn (?float $v): string => $v === null ? '' : DessinsDossier::nombre($v, 2) . ' m';
/** Une quantité comme à l'écran : saisie, estimée (≈), ou à déterminer. */
$prevu = static fn (array $q): string => $q['n'] === null ? 'à déterminer'
    : (($q['mode'] ?? null) === 'estime' ? '≈ ' : '') . DessinsDossier::nombre((float) $q['n'], 0);
$origine = static fn (?string $o): string => Materiel::ORIGINES[(string) $o] ?? Materiel::ORIGINES[''];

$L = $p['longueur_m'] !== null ? (float) $p['longueur_m'] : null;
$l = $p['largeur_m'] !== null ? (float) $p['largeur_m'] : null;
$zone = Planches::zone($p);
$potager = ($p['type'] ?? '') === 'potager';
['conduite' => $conduite] = Heritage::conduite($p, $site);
$imprime = (new PlanImprime())->dUneParcelle($p, $siteId);
$calendrier = (new CalendrierLignes())->dUneParcelle($idP)['dates'];

/* ------------------------------------------------------------------ où va quoi */

// Les bordures et la ligne des lianes : une ligne par côté posé sur le plan, ses espèces dans l'ordre de la série, le
// nombre de chacune additionné sur toute la ligne.
$bordures = [];
foreach ($imprime['etat'] as $cle => $b) {
    if ($b['places'] === [] || $b['geometrie'] === null) {
        continue;
    }
    $ok = $b['compte'] !== null && $b['compte']['ok'];
    $especes = [];
    foreach ($b['places'] as $k => $pl) {
        $cleE = $pl['nom_scientifique'] . "\0" . $pl['variete'];
        $especes[$cleE] ??= ['sigle' => $imprime['especes'][$cleE]['sigle'] ?? '', 'couleur' => $imprime['especes'][$cleE]['couleur'] ?? '#26312A',
            'nom' => $pl['nom'] . ($pl['variete'] !== '' ? ' (' . $pl['variete'] . ')' : ''), 'espacement' => $pl['espacement'],
            'origine' => $pl['origine'], 'n' => $ok ? 0 : null];
        if ($ok) {
            $especes[$cleE]['n'] += (int) $b['compte']['comptes'][$k];
        }
    }
    $bordures[] = ['libelle' => Bordures::libelle($cle), 'longueur' => (float) $b['geometrie']['longueur'],
        'serie' => array_map(static fn (array $pl): string => $imprime['especes'][$pl['nom_scientifique'] . "\0" . $pl['variete']]['sigle'] ?? $pl['nom'], $b['places']),
        'total' => $ok ? $b['compte']['total'] : null, 'especes' => $especes,
        'prevue' => $dateEnMots($calendrier[$cle]['mise_en_place_prevue'] ?? null)];
}

// Les lignes de service des planches, planche par planche.
$natures = Planches::natures($p);
$planches = [];
if ($zone !== null) {
    foreach ((new LignesPlantation())->etatPlanches($p, $siteId) as $numero => $ep) {
        $lignes = array_filter($ep['lignes']);
        if ($lignes === []) {
            continue;
        }
        $nat = $natures[$numero] ?? null;
        $planches[] = ['numero' => $numero, 'longueur' => (float) $ep['longueur'], 'centrale' => Planches::centraleDe($p, $numero) + 1,
            'nature' => $nat === null ? null : ($nat['bandes'] !== null
                ? implode(' puis ', array_map(static fn (string $n): string => mb_strtolower(Planches::NATURES[$n]), $nat['bandes']))
                : mb_strtolower(Planches::NATURES[$nat['nature']])),
            'lignes' => array_map(static fn (array $li, int $k): array => $li + [
                'k' => $k, 'prevue' => $dateEnMots($calendrier[LignesPlantation::clePlanche($numero, $k)]['mise_en_place_prevue'] ?? null)],
                $lignes, array_keys($lignes))];
    }
}

// Le scénario (parcelle pérenne) ou les cultures (potager) : les quantités de « Matériel et eau ». Ce que les lignes du
// plan portent est déjà dit plus haut ; une plante du scénario déclarée en bordure y est comptée.
$entrees = (new MaterielDuSite())->entrees($siteId, [$p])['entrees'];
$scenario = [];
$cultures = [];
if ($potager) {
    $listeCultures = (new Cultures())->dUneParcelle($idP);
    $qtes = array_values(array_filter($entrees, static fn (array $e): bool => ($e['ligne']['origine'] ?? null) === 'potager'));
    foreach ($listeCultures as $i => $c) {
        $cultures[] = ['planche' => $c['planche'], 'nom' => (string) $c['espece'], 'qte' => $qtes[$i]['qte'] ?? ['n' => null],
            'mise' => $dateEnMots($c['date_semis_repiquage'] ?? null)];
    }
    usort($cultures, static fn (array $a, array $b): int => [$a['planche'] ?? PHP_INT_MAX, $a['nom']] <=> [$b['planche'] ?? PHP_INT_MAX, $b['nom']]);
} else {
    $strates = $conduite->strates();
    foreach ($entrees as $e) {
        if (!isset($e['ligne']['id']) || ($e['qte']['mode'] ?? null) === 'plan') {
            continue;
        }
        $ls = $e['ligne'];
        $ou = Scenario::details($ls)['emplacement'] ?? 'centre';
        $scenario[] = ['nom' => (string) $ls['espece'], 'ou' => Scenario::EMPLACEMENTS[$ou] ?? $ou,
            'strate' => $strates[(string) $ls['strate']] ?? (string) $ls['strate'],
            'entree' => isset(Scenario::PERIODES[(string) $ls['entree']]) ? Scenario::PERIODES[(string) $ls['entree']][0] : '',
            'origine' => $ls['origine'] ?? null, 'qte' => $e['qte']];
    }
}

$titre = 'Chantier de plantation — parcelle ' . $p['code'];
$nomProjet = (string) $site['nom'];
$cssTexte = static fn (string $t): string => (string) preg_replace_callback('/["\\\\<>&\r\n]/',
    static fn (array $m): string => '\\' . dechex(ord($m[0])) . ' ', $t);
$aPointer = '<td class="case-a-cocher">☐</td><td class="a-remplir"></td><td class="a-remplir notes"></td>';
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $h($titre) ?> — <?= $h($nomProjet) ?></title>
<link rel="stylesheet" href="assets/css/dessins-papier.css?v=<?= (int) @filemtime(__DIR__ . '/assets/css/dessins-papier.css') ?>">
<style>
  @font-face{ font-family:'Clash Display'; src:url('assets/polices/ClashDisplay-Semibold.woff2') format('woff2'); font-weight:600; font-style:normal; font-display:swap; }
  @font-face{ font-family:'Archivo'; src:url('assets/polices/Archivo-Regular.woff2') format('woff2'); font-weight:400; font-style:normal; font-display:swap; }
  @font-face{ font-family:'Archivo'; src:url('assets/polices/Archivo-Medium.woff2') format('woff2'); font-weight:500; font-style:normal; font-display:swap; }
  :root{
    --ink:#26312A; --muted:#4E5A50; --line:#C7D2BE;
    --fond:#F6EEDB; --surface:#FFFFFF; --accent-titre:#84651F; --accent:#C89B3C;
    --titre:'Clash Display', system-ui, sans-serif; --texte:'Archivo', system-ui, sans-serif;
  }
  *{ box-sizing:border-box; }
  body{ margin:0; background:var(--fond); color:var(--ink); font:400 .95rem/1.5 var(--texte); }
  .barre{
    position:sticky; top:0; z-index:2; display:flex; flex-wrap:wrap; gap:.6rem 1rem; justify-content:space-between;
    align-items:center; background:var(--surface); border-bottom:1px solid var(--line); padding:.7rem 1rem;
  }
  .barre a{ color:var(--accent-titre); font:600 .85rem var(--titre); text-decoration:none; }
  .barre button{ padding:.45rem .9rem; border:0; border-radius:.5rem; background:var(--accent); color:#fff; font:600 .85rem var(--titre); cursor:pointer; }
  .barre button:focus-visible{ outline:2px solid var(--accent-titre); outline-offset:2px; }
  .document{ max-width:62rem; margin:1rem auto 3rem; padding:0 1rem; }
  .feuille{ background:var(--surface); border:1px solid var(--line); border-radius:.6rem; padding:1.4rem 1.6rem; margin-bottom:1rem; }
  .sur-titre{ font:600 .75rem var(--titre); letter-spacing:.1em; text-transform:uppercase; color:var(--muted); margin:0 0 .2rem; }
  h1{ font:600 1.5rem/1.2 var(--titre); margin:0 0 .3rem; color:var(--accent-titre); }
  h2{ font:600 1.2rem/1.2 var(--titre); margin:0 0 .4rem; color:var(--accent-titre); }
  h3{ font:600 1rem/1.2 var(--titre); margin:1.2rem 0 .3rem; }
  .entete{ color:var(--muted); font-size:.85rem; margin:0 0 .8rem; }
  .vide{ color:var(--muted); font-style:italic; }
  .note{ color:var(--muted); font-size:.85rem; margin:.3rem 0 .6rem; }
  figure{ margin:.4rem 0 0; }
  figcaption{ color:var(--muted); font-size:.8rem; margin-top:.3rem; }
  .serie{ font-size:.85rem; margin:0 0 .4rem; }
  .bloc{ break-inside:avoid; page-break-inside:avoid; }
  table.pointage{ width:100%; border-collapse:collapse; font-size:.82rem; }
  table.pointage th, table.pointage td{ text-align:left; vertical-align:top; padding:.3rem .45rem .3rem 0; border-top:1px solid var(--line); }
  table.pointage th{ font-weight:500; color:var(--muted); font-size:.75rem; }
  table.pointage tr{ break-inside:avoid; }
  table.pointage .nombre{ text-align:right; white-space:nowrap; }
  table.pointage .case-a-cocher{ font-size:1.05rem; line-height:1; text-align:center; width:3.2rem; }
  table.pointage .a-remplir{ width:4.5rem; border-bottom:1px solid var(--muted); }
  table.pointage .a-remplir.notes{ width:auto; min-width:9rem; }
  table.pointage td.a-remplir{ height:1.6rem; }
  .pastille{ display:inline-block; width:.75rem; height:.75rem; border-radius:50%; vertical-align:-.08rem; margin-right:.3rem; }
  .lignes-notes{ height:6rem; background:repeating-linear-gradient(transparent 0 1.45rem, var(--line) 1.45rem 1.5rem); }

  @page{
    size:A4 portrait; margin:15mm;
    @bottom-center{ content:"<?= $cssTexte($nomProjet . ' — ' . $titre) ?> — page " counter(page) " sur " counter(pages); font:400 8pt 'Archivo', sans-serif; color:#4E5A50; }
  }
  @page paysage{ size:A4 landscape; margin:12mm; }
  @media print{
    body{ background:#fff; font-size:10pt; }
    .barre{ display:none !important; }
    .document{ max-width:none; margin:0; padding:0; }
    .feuille{ border:0; border-radius:0; padding:0; margin:0 0 7mm; }
    .page-plan{ page:paysage; break-after:page; }
    .page-plan .d-dessin{ max-height:150mm; }
    a{ color:inherit; text-decoration:none; }
    .lien-etape{ display:none; }
  }
</style>
</head>
<body>

<div class="barre">
  <a href="realisation-outils-et-plans.php" id="retour">← Retour à l’atelier</a>
  <button type="button" id="imprimer">Imprimer / Enregistrer en PDF</button>
</div>

<main class="document">
  <section class="feuille page-plan">
    <p class="sur-titre"><?= $h($nomProjet) ?></p>
    <h1><?= $h($titre) ?>, <?= $h((string) $p['nom']) ?></h1>
    <p class="entete">Édité le <?= $h($dateEdition) ?><?= $L !== null && $l !== null ? ' · ' . $h(DessinsDossier::nombre($L) . ' m × ' . DessinsDossier::nombre($l) . ' m') : '' ?><?= $zone !== null ? ' · ' . $zone['disp']['n'] . ' planche' . ($zone['disp']['n'] > 1 ? 's' : '') . ', sens ' . ($zone['nordSud'] ? 'nord-sud' : 'est-ouest') : '' ?><?= ($d = $dateEnMots($p['date_plantation'] ?? null)) !== null ? ' · campagne commencée le ' . $h($d) : '' ?></p>
<?php if ($L === null || $l === null): ?>
    <p class="vide">Le plan de cette parcelle n’est pas encore dessiné. Cela se fait à l’étape « Créer les parcelles » de la Conception.</p>
    <p class="lien-etape"><a href="conception-parcelles.php?parcelle=<?= $idP ?>">Aller à cette étape →</a></p>
<?php else: ?>
    <figure>
      <?= $imprime['plan'] ?>
      <figcaption><?= $h($imprime['legendePlan']) ?></figcaption>
    </figure>
<?php if ($imprime['legende'] !== []): ?>
    <ul class="legende">
<?php foreach ($imprime['legende'] as $e): ?>
      <li><i style="background:<?= $h($e['couleur']) ?>"></i><b><?= $h($e['sigle']) ?></b> <?= $h($e['nom'] . ($e['variete'] !== '' ? ' (' . $e['variete'] . ')' : '')) ?> : <?= (int) $e['n'] ?> plant<?= (int) $e['n'] > 1 ? 's' : '' ?></li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>
<?php endif; ?>
  </section>

  <section class="feuille">
    <h2>Où va quoi</h2>
    <p class="note">Pour chaque ligne, ses plantes dans l’ordre de la série, leur espacement et le nombre prévu. Cochez ce
      qui est planté, notez le nombre réellement planté et ce que vous voyez.</p>

<?php if ($bordures !== []): ?>
<?php foreach ($bordures as $b): ?>
    <div class="bloc">
      <h3><?= $h($b['libelle']) ?> — <?= $h($metres($b['longueur'])) ?><?= $b['total'] !== null ? ', ' . (int) $b['total'] . ' plants' : '' ?></h3>
      <p class="serie">Série, dans l’ordre : <?= $h(implode(' · ', $b['serie'])) ?><?= $b['prevue'] !== null ? ' — plantation prévue le ' . $h($b['prevue']) : '' ?></p>
      <table class="pointage">
        <thead><tr><th>Plante</th><th>Espacement</th><th class="nombre">Prévu</th><th>Origine</th><th>Planté</th><th>Nombre</th><th>Notes</th></tr></thead>
        <tbody>
<?php foreach ($b['especes'] as $e): ?>
          <tr>
            <td><span class="pastille" style="background:<?= $h($e['couleur']) ?>"></span><b><?= $h($e['sigle']) ?></b> <?= $h($e['nom']) ?></td>
            <td><?= $e['espacement'] !== null ? $h($metres((float) $e['espacement'])) : '<span class="vide">non noté</span>' ?></td>
            <td class="nombre"><?= $e['n'] !== null ? (int) $e['n'] : 'à déterminer' ?></td>
            <td><?= $h($origine($e['origine'])) ?></td>
            <?= $aPointer ?>
          </tr>
<?php endforeach; ?>
        </tbody>
      </table>
    </div>
<?php endforeach; ?>
<?php endif; ?>

<?php foreach ($planches as $pl): ?>
    <div class="bloc">
      <h3>Planche <?= (int) $pl['numero'] ?><?= $pl['nature'] !== null ? ', ' . $h($pl['nature']) : '' ?> — <?= $h($metres($pl['longueur'])) ?></h3>
      <table class="pointage">
        <thead><tr><th>Ligne</th><th>Plante</th><th>Espacement</th><th class="nombre">Prévu</th><th>Origine</th><th>Mise en place</th><th>Planté</th><th>Nombre</th><th>Notes</th></tr></thead>
        <tbody>
<?php foreach ($pl['lignes'] as $li): ?>
          <tr>
            <td><?= (int) $li['k'] ?><?= $li['k'] === $pl['centrale'] ? ', centrale' : '' ?></td>
            <td><?= $h($li['nom']) ?></td>
            <td><?= $li['espacement'] !== null ? $h($metres((float) $li['espacement'])) : '<span class="vide">non noté</span>' ?></td>
            <td class="nombre"><?= $li['compte'] !== null ? (int) $li['compte'] : 'à déterminer' ?></td>
            <td><?= $h($origine($li['espece']['origine'] ?? null)) ?></td>
            <td><?= $li['prevue'] !== null ? $h($li['prevue']) : '' ?></td>
            <?= $aPointer ?>
          </tr>
<?php endforeach; ?>
        </tbody>
      </table>
    </div>
<?php endforeach; ?>

<?php if ($scenario !== []): ?>
    <div class="bloc">
      <h3>Les végétaux du scénario</h3>
      <table class="pointage">
        <thead><tr><th>Plante</th><th>Où</th><th>Strate</th><th>Entrée</th><th class="nombre">Prévu</th><th>Origine</th><th>Planté</th><th>Nombre</th><th>Notes</th></tr></thead>
        <tbody>
<?php foreach ($scenario as $v): ?>
          <tr>
            <td><?= $h($v['nom']) ?></td>
            <td><?= $h($v['ou']) ?></td>
            <td><?= $h($v['strate']) ?></td>
            <td><?= $h($v['entree']) ?></td>
            <td class="nombre"><?= $h($prevu($v['qte'])) ?></td>
            <td><?= $h($origine($v['origine'])) ?></td>
            <?= $aPointer ?>
          </tr>
<?php endforeach; ?>
        </tbody>
      </table>
    </div>
<?php endif; ?>

<?php if ($cultures !== []): ?>
    <div class="bloc">
      <h3>Les cultures</h3>
      <table class="pointage">
        <thead><tr><th>Planche</th><th>Culture</th><th>Mise en place</th><th class="nombre">Prévu</th><th>Planté</th><th>Nombre</th><th>Notes</th></tr></thead>
        <tbody>
<?php foreach ($cultures as $c): ?>
          <tr>
            <td><?= $c['planche'] !== null ? (int) $c['planche'] : '<span class="vide">à placer</span>' ?></td>
            <td><?= $h($c['nom']) ?></td>
            <td><?= $c['mise'] !== null ? $h($c['mise']) : '' ?></td>
            <td class="nombre"><?= $h($prevu($c['qte'])) ?></td>
            <?= $aPointer ?>
          </tr>
<?php endforeach; ?>
        </tbody>
      </table>
    </div>
<?php endif; ?>

<?php if ($bordures === [] && $planches === [] && $scenario === [] && $cultures === []): ?>
    <p class="vide">Aucune plante n’est encore posée sur cette parcelle. Les bordures et les lignes se composent à l’étape
      « Créer les parcelles », les végétaux au « Scénario » ou au plan de culture du potager.</p>
    <p class="lien-etape"><a href="conception-parcelles.php?parcelle=<?= $idP ?>">Aller au plan de la parcelle →</a></p>
<?php endif; ?>

    <div class="bloc">
      <h3>Le jour du chantier</h3>
      <p class="note">La date, les personnes présentes, le temps qu’il fait, ce qui reste à faire.</p>
      <div class="lignes-notes"></div>
    </div>
  </section>
</main>

<script>
  document.getElementById('imprimer').addEventListener('click', function(){ window.print(); });
  document.getElementById('retour').addEventListener('click', function(e){
    if (document.referrer && new URL(document.referrer).origin === location.origin && history.length > 1){
      e.preventDefault(); history.back();
    }
  });
</script>
</body>
</html>
