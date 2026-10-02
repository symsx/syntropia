<?php

declare(strict_types=1);

/**
 * Le Dossier Projet — le projet de la personne, à imprimer ou à enregistrer en PDF.
 *
 * Voir `chantier-dossier-projet.md`. Phase 1 : la couverture et la Définition (vision, objectifs, moyens, site), le
 * choix des chapitres à imprimer, la mise en page papier.
 *
 * DÉLIBÉRÉMENT HORS DU GABARIT « LE BUREAU » — la seconde exception, admise par le porteur le 2026-09-19, comme
 * `releve-vierge.php` : c'est un document qu'on imprime, pas une page qu'on visite, et il fait plusieurs pages ; le
 * gabarit verrouille tout défilement. La page reprend seulement les polices et les couleurs du bureau. N'en faites pas
 * un modèle pour autre chose qu'un document destiné au papier.
 *
 * **Le choix des chapitres** se fait par des cases, en CSS pur (`:has()`) : une case décochée retire son chapitre de
 * l'écran comme du papier — ce qu'on voit est ce qui s'imprime. Il ne s'enregistre pas (décision du porteur) : toutes
 * les cases sont cochées à chaque ouverture.
 *
 * **Les réponses** sont celles de la personne qui ouvre le dossier, à défaut celles du porteur
 * (`Reponses::definitionDuProjet()`) ; le dossier dit de qui elles viennent. Il n'écrit rien : un lecteur l'imprime
 * comme un porteur. Un chapitre sans réponse le dit, et dit où il se remplit — jamais un chapitre vide.
 */

require __DIR__ . '/../src/autoload.php';
require __DIR__ . '/../src/Configuration.php';
require __DIR__ . '/../src/Database.php';

use Syntropia\Conduite;
use Syntropia\Configuration;
use Syntropia\Role;
use Syntropia\Web\Authentification;
use Syntropia\Web\Comptes;
use Syntropia\Web\Reponses;
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

$definition = (new Reponses())->definitionDuProjet($siteId, $compteId);
$r = $definition['reponses'];

$porteurs = array_values(array_filter($sites->membres($siteId), static fn (array $m): bool => $m['role'] === 'porteur'));
$noms = (new Comptes())->nomsParIds(array_column($porteurs, 'compte_id'));
$nomsPorteurs = array_values(array_filter(array_map(static fn (array $m): string => trim((string) ($noms[$m['compte_id']]['nom_affiche'] ?? '')) !== ''
    ? (string) $noms[$m['compte_id']]['nom_affiche'] : (string) ($noms[$m['compte_id']]['identifiant'] ?? ''), $porteurs)));

$conduite = Conduite::tryFrom((string) ($site['conduite'] ?? ''));

$mois = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
$aujourdhui = new DateTimeImmutable('now', new DateTimeZone('Europe/Paris'));
$dateEdition = (int) $aujourdhui->format('j') . ($aujourdhui->format('j') === '1' ? 'er' : '') . ' '
    . $mois[(int) $aujourdhui->format('n') - 1] . ' ' . $aujourdhui->format('Y');

/** Le texte sûr, ses retours à la ligne gardés. */
$h = static fn (string $t): string => nl2br(htmlspecialchars($t, ENT_QUOTES, 'UTF-8'), false);

/** Une réponse libre, ou `null` si elle n'est pas donnée. */
$texte = static function (string $cle) use ($r): ?string {
    $v = trim((string) ($r[$cle] ?? ''));
    return $v === '' ? null : $v;
};

/** Une réponse à choix, dite par son libellé — `null` si elle n'est pas donnée ou n'est plus proposée. */
$choix = static function (string $cle, array $libelles) use ($texte): ?string {
    $v = $texte($cle);
    return $v === null ? null : ($libelles[$v] ?? null);
};

$destinations = ['loisir' => 'Loisir', 'vivrier' => 'Vivrier', 'commercial' => 'Commercial', 'social' => 'Social'];
$listeDestinations = array_values(array_filter(array_map(
    static fn (string $d): ?string => $destinations[trim($d)] ?? null,
    explode(',', (string) ($r['objectifs_destinations'] ?? ''))
)));

$position = ($site['latitude'] ?? null) !== null && ($site['longitude'] ?? null) !== null
    ? number_format((float) $site['latitude'], 5, ',', '') . ' ; ' . number_format((float) $site['longitude'], 5, ',', '')
    : null;

/**
 * Les chapitres de la Définition : titre, l'étape où ils se remplissent, et leurs lignes (question → réponse, `null`
 * si elle n'est pas donnée).
 */
$chapitres = [
    'vision' => ['Vision', 'definition-vision.php', [
        'Qui êtes-vous ?'                => $choix('vision_qui', ['seul' => 'Seul', 'groupe' => 'En groupe']),
        'Votre motivation'               => $texte('vision_motivation'),
        'L’intention, au-delà de vous'   => $texte('vision_intention'),
    ]],
    'objectifs' => ['Objectifs', 'definition-objectifs.php', [
        'Quelles productions ?'          => $texte('objectifs_productions'),
        'Pour quelle destination ?'      => $listeDestinations === [] ? null : implode(', ', $listeDestinations),
        'À quel terme ?'                 => $choix('objectifs_terme', ['court' => 'Court — environ 3 ans', 'moyen' => 'Moyen — 5 à 10 ans', 'long' => 'Long — plus de 10 ans']),
    ]],
    'moyens' => ['Moyens', 'definition-moyens.php', [
        'Ce que vous savez déjà faire'   => $texte('moyens_connaissances'),
        'Le temps que vous pouvez y consacrer' => $texte('moyens_temps'),
        'Vos moyens financiers'          => $choix('moyens_financier', ['faible' => 'Faibles', 'modere' => 'Modérés', 'confortable' => 'Confortables']),
        'Le terrain'                     => $choix('moyens_terrain', ['oui' => 'Je l’ai déjà', 'non' => 'Je le cherche encore']),
    ]],
    'site' => ['Le site', 'definition-site.php', [
        'Commune'                        => $texte('site_commune'),
        'Position (latitude ; longitude)' => $position,
        'Superficie'                     => $texte('site_superficie') === null ? null
            : (is_numeric($texte('site_superficie')) ? number_format((float) $texte('site_superficie'), fmod((float) $texte('site_superficie'), 1.0) == 0.0 ? 0 : 2, ',', ' ') : $texte('site_superficie')) . ' m²',
        'Zone climatique USDA'           => $texte('site_usda'),
        'Exposition dominante'           => $choix('site_exposition', ['nord' => 'Nord', 'sud' => 'Sud', 'est' => 'Est', 'ouest' => 'Ouest', 'plat' => 'Plat']),
        'Pente'                          => $choix('site_pente', ['plate' => 'Plate', 'legere' => 'Légère', 'marquee' => 'Marquée', 'forte' => 'Forte']),
        'Conditions climatiques particulières' => $texte('site_climat'),
        'Conduite'                       => $conduite?->libelle(),
    ]],
];

$nomProjet = (string) $site['nom'];

/** Un texte dans une chaîne CSS : dans `<style>`, rien n'est décodé — on échappe à la manière du CSS (`\22 `), ce qui
 *  écarte aussi toute fermeture de la balise. */
$cssTexte = static fn (string $t): string => (string) preg_replace_callback('/["\\\\<>&\r\n]/',
    static fn (array $m): string => '\\' . dechex(ord($m[0])) . ' ', $t);
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dossier Projet — <?= htmlspecialchars($nomProjet, ENT_QUOTES, 'UTF-8') ?></title>
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
  .barre a:hover{ text-decoration:underline; }
  .barre button{
    padding:.45rem .9rem; border:0; border-radius:.5rem; background:var(--accent);
    color:#fff; font:600 .85rem var(--titre); cursor:pointer;
  }
  .barre button:focus-visible, .reglage input:focus-visible + span{ outline:2px solid var(--accent-titre); outline-offset:2px; }

  .reglage{ max-width:46rem; width:calc(100% - 2rem); margin:1rem auto 0; padding:.9rem 1rem; background:var(--surface); border:1px solid var(--line); border-radius:.6rem; }
  .reglage p{ margin:0 0 .5rem; }
  .reglage .titre-reglage{ font:600 .85rem var(--titre); text-transform:uppercase; letter-spacing:.04em; color:var(--accent-titre); }
  .reglage .cases{ display:flex; flex-wrap:wrap; gap:.4rem 1.2rem; }
  .reglage label{ display:inline-flex; align-items:center; gap:.4rem; cursor:pointer; }
  .reglage input{ position:absolute; opacity:0; width:1px; height:1px; }
  .reglage input + span::before{
    content:''; display:inline-block; width:1rem; height:1rem; margin-right:.4rem; vertical-align:-.15rem;
    border:1.5px solid var(--accent); border-radius:.25rem; background:var(--surface);
  }
  .reglage input:checked + span::before{ background:var(--accent) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 10 10'%3E%3Cpolyline points='2,5 4.2,7.2 8,2.8' fill='none' stroke='white' stroke-width='1.6'/%3E%3C/svg%3E") center/80% no-repeat; }
  .reglage .note{ margin:.5rem 0 0; color:var(--muted); font-size:.85rem; }

  .document{ max-width:46rem; margin:1rem auto 3rem; padding:0 1rem; }
  .feuille{ background:var(--surface); border:1px solid var(--line); border-radius:.6rem; padding:1.6rem 1.8rem; margin-bottom:1rem; }

  .couverture{ text-align:center; padding:4rem 1.8rem; }
  .couverture .sur-titre{ font:600 .9rem var(--titre); letter-spacing:.12em; text-transform:uppercase; color:var(--accent-titre); margin:0 0 1.5rem; }
  .couverture h1{ font:600 2.2rem/1.15 var(--titre); margin:0 0 1rem; }
  .couverture dl{ display:inline-grid; grid-template-columns:auto auto; gap:.3rem 1rem; text-align:left; margin:1.5rem 0 0; }
  .couverture dt{ color:var(--muted); }
  .couverture dd{ margin:0; font-weight:500; }

  h2{ font:600 1.35rem/1.2 var(--titre); margin:0 0 .3rem; color:var(--accent-titre); }
  .phase{ font:600 .75rem var(--titre); letter-spacing:.1em; text-transform:uppercase; color:var(--muted); margin:0 0 .2rem; }
  .titre-phase{ display:none; font:600 1.6rem/1.2 var(--titre); letter-spacing:.06em; text-transform:uppercase; margin:0 0 6mm; padding-bottom:3mm; border-bottom:1.5px solid var(--accent); }
  .provenance{ color:var(--muted); font-size:.85rem; margin:0 0 1rem; }
  .reponses{ margin:0; }
  .reponses div{ padding:.55rem 0; border-top:1px solid var(--line); break-inside:avoid; }
  .reponses dt{ font-weight:500; color:var(--muted); font-size:.85rem; }
  .reponses dd{ margin:.15rem 0 0; }
  .vide{ color:var(--muted); font-style:italic; }
  .a-faire{ margin:.8rem 0 0; color:var(--muted); font-size:.85rem; }

  /* Le choix des chapitres : une case décochée retire son chapitre, à l'écran comme sur le papier. */
<?php foreach (array_merge(['couverture'], array_keys($chapitres)) as $id): ?>
  body:has(#imprimer-<?= $id ?>:not(:checked)) #chapitre-<?= $id ?>{ display:none; }
<?php endforeach; ?>
  .aucun{ display:none; }
  body:not(:has(<?= implode(', ', array_map(static fn (string $id): string => '#imprimer-' . $id . ':checked', array_keys($chapitres))) ?>)) #phase-definition{ display:none !important; }
  body:not(:has(.reglage input:checked)) .aucun{ display:block; }

  @media (max-width:30rem){
    .feuille{ padding:1.1rem 1rem; }
    .couverture{ padding:2.5rem 1rem; }
    .couverture h1{ font-size:1.7rem; }
  }

  @page{
    size:A4 portrait; margin:15mm;
    @bottom-center{ content:"<?= $cssTexte($nomProjet) ?> — Dossier Projet — page " counter(page) " sur " counter(pages); font:400 8pt 'Archivo', sans-serif; color:#4E5A50; }
  }
  @page :first{ @bottom-center{ content:none; } }
  @media print{
    body{ background:#fff; font-size:10.5pt; }
    .barre, .reglage, .aucun{ display:none !important; }
    .document{ max-width:none; margin:0; padding:0; }
    /* Un saut de page après la couverture, et avant chaque phase (une seule pour l'instant) ; les chapitres d'une même
       phase s'enchaînent, un chapitre ne se coupe pas s'il tient sur une page. */
    .feuille{ border:0; border-radius:0; padding:0; margin:0 0 9mm; break-inside:avoid; }
    .couverture{ padding-top:35vh; break-after:page; margin:0; }
    .phase{ display:none; }
    .titre-phase{ display:block; }
    a{ color:inherit; text-decoration:none; }
    .lien-etape{ display:none; }
  }
</style>
</head>
<body>

<div class="barre">
  <a href="conception-parcelles.php" id="retour">← Retour à l’atelier</a>
  <button type="button" id="imprimer">Imprimer / Enregistrer en PDF</button>
</div>

<div class="reglage">
  <p class="titre-reglage">Chapitres à imprimer</p>
  <div class="cases">
    <label><input type="checkbox" id="imprimer-couverture" checked><span>Couverture</span></label>
<?php foreach ($chapitres as $id => [$titre]): ?>
    <label><input type="checkbox" id="imprimer-<?= $id ?>" checked><span><?= htmlspecialchars($titre, ENT_QUOTES, 'UTF-8') ?></span></label>
<?php endforeach; ?>
  </div>
  <p class="note">Ce que vous voyez ici est ce qui s’imprime. Pour un PDF, choisissez « Enregistrer en PDF » comme
     imprimante. Le choix des chapitres ne se garde pas : tout est coché à chaque ouverture.</p>
</div>

<main class="document">
  <p class="feuille aucun">Aucun chapitre n’est coché : rien à imprimer.</p>

  <section class="feuille couverture" id="chapitre-couverture">
    <p class="sur-titre">Dossier Projet</p>
    <h1><?= htmlspecialchars($nomProjet, ENT_QUOTES, 'UTF-8') ?></h1>
    <dl>
      <dt><?= count($nomsPorteurs) > 1 ? 'Porteurs' : 'Porteur' ?></dt>
      <dd><?= $nomsPorteurs === [] ? '<span class="vide">non dit</span>' : htmlspecialchars(implode(', ', $nomsPorteurs), ENT_QUOTES, 'UTF-8') ?></dd>
<?php if ($texte('site_commune') !== null): ?>
      <dt>Commune</dt>
      <dd><?= htmlspecialchars((string) $texte('site_commune'), ENT_QUOTES, 'UTF-8') ?></dd>
<?php endif; ?>
      <dt>Conduite</dt>
      <dd><?= $conduite !== null ? htmlspecialchars($conduite->libelle(), ENT_QUOTES, 'UTF-8') : '<span class="vide">pas encore choisie</span>' ?></dd>
      <dt>Édité le</dt>
      <dd><?= $dateEdition ?></dd>
    </dl>
  </section>

  <h2 class="titre-phase" id="phase-definition">Définition</h2>
<?php foreach ($chapitres as $id => [$titre, $etape, $lignes]): ?>
<?php $renseignees = array_filter($lignes, static fn (?string $v): bool => $v !== null); ?>
  <section class="feuille" id="chapitre-<?= $id ?>">
    <p class="phase">Définition</p>
    <h2><?= htmlspecialchars($titre, ENT_QUOTES, 'UTF-8') ?></h2>
<?php if ($definition['nom'] !== null): ?>
    <p class="provenance">Réponses de <?= htmlspecialchars($definition['nom'], ENT_QUOTES, 'UTF-8') ?>.</p>
<?php endif; ?>
<?php if ($renseignees === []): ?>
    <p class="vide">Pas encore renseigné — cela se fait à l’étape « <?= htmlspecialchars($titre, ENT_QUOTES, 'UTF-8') ?> » de la Définition.</p>
    <p class="a-faire lien-etape"><a href="<?= $etape ?>">Aller à cette étape →</a></p>
<?php else: ?>
    <dl class="reponses">
<?php foreach ($lignes as $question => $reponse): ?>
      <div>
        <dt><?= htmlspecialchars($question, ENT_QUOTES, 'UTF-8') ?></dt>
        <dd><?= $reponse !== null ? $h($reponse) : '<span class="vide">pas encore renseigné</span>' ?></dd>
      </div>
<?php endforeach; ?>
    </dl>
<?php endif; ?>
  </section>
<?php endforeach; ?>
</main>

<script>
  document.getElementById('imprimer').addEventListener('click', function(){ window.print(); });
  // Revenir là d'où l'on vient, s'il s'agit de l'atelier ; sinon le lien mène au plan des parcelles.
  document.getElementById('retour').addEventListener('click', function(e){
    if (document.referrer && new URL(document.referrer).origin === location.origin && history.length > 1){
      e.preventDefault(); history.back();
    }
  });
</script>
</body>
</html>
