<?php

declare(strict_types=1);

/**
 * L'aller-retour d'un projet : export → import → export doit redonner le même fichier.
 *
 * Chantier `chantier-export-import-projet.md`, Phase 3. À rejouer **à chaque changement de schéma**
 * (`sql/01-syntropia.sql`) : c'est ce qui dit qu'une colonne nouvelle sort de l'export ET rentre par
 * l'import, sans avoir été oubliée d'un côté.
 *
 * **Appelé par `python3 bin/dev.py verifier-export`, JAMAIS en direct** : un appel direct retombe
 * silencieusement sur `config/base.php`, donc la production (voir CLAUDE.md § Point de reprise). Ce script
 * refuse de tourner si la base n'est pas sur ce poste, et n'écrit que ce qu'il défait aussitôt.
 *
 *     python3 bin/dev.py verifier-export            tous les projets de la base locale, plus un projet riche
 *     python3 bin/dev.py verifier-export 2 5        seulement ces projets (identifiants de `site`)
 *
 * **Ce qu'il fait, pour chaque projet** : l'exporte, examine le fichier, l'importe comme un projet neuf sous un
 * compte de passage, exporte la copie, compare les deux documents — **aux identifiants et à la date d'export
 * près** (les nombres égaux comptent pour égaux : `45.0` et `45`) — puis supprime la copie. Les projets
 * d'origine ne sont que lus. **Le « projet riche »** est bâti pour l'occasion, puis supprimé : un projet réel
 * local est souvent pauvre, et une table jamais remplie n'éprouve rien. Il est écrit colonne par colonne :
 * si le schéma change au point qu'il ne s'insère plus, le script le dit — c'est alors lui qu'il faut mettre à jour.
 *
 * **En plus, trois refus** qui gardent la porte : un fichier modifié d'une lettre, un fichier d'un schéma plus
 * récent (somme recalculée, pour que ce soit bien la version qui refuse), une colonne interdite (idem).
 *
 * Code de sortie : 0 si tout est identique et refusé comme il faut, 1 sinon. Le compte de passage et tout ce
 * qu'il a créé sont supprimés à la fin, même en cas d'erreur.
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}
require __DIR__ . '/../src/autoload.php';
require __DIR__ . '/../src/Configuration.php';
require __DIR__ . '/../src/Database.php';

use Syntropia\Database;
use Syntropia\Role;
use Syntropia\Web\Comptes;
use Syntropia\Web\Export;
use Syntropia\Web\Import;
use Syntropia\Web\Sites;

$hote = getenv('SYNTROPIA_DB_HOTE');
if ($hote !== '127.0.0.1' && $hote !== 'localhost') {
    fwrite(STDERR, "Refus : la base n'est pas sur ce poste (SYNTROPIA_DB_HOTE=" . var_export($hote, true) . ").\n"
        . "Lancez ce script par « python3 bin/dev.py verifier-export ».\n");
    exit(2);
}

$pdo = Database::syntropia();
$comptes = new Comptes();
$export = new Export($pdo);
$import = new Import($pdo);
$sites = new Sites($pdo);

/** Les nombres égaux sont égaux, qu'ils s'écrivent `45` ou `45.0`. */
function ecarts(mixed $a, mixed $b, string $chemin = ''): array
{
    if (is_array($a) && is_array($b)) {
        $sortie = [];
        foreach (array_unique(array_merge(array_keys($a), array_keys($b))) as $cle) {
            if (!array_key_exists($cle, $a)) {
                $sortie[] = "$chemin/$cle : absent de l'original";
            } elseif (!array_key_exists($cle, $b)) {
                $sortie[] = "$chemin/$cle : absent de la copie";
            } else {
                $sortie = array_merge($sortie, ecarts($a[$cle], $b[$cle], "$chemin/$cle"));
            }
        }
        return $sortie;
    }
    $nombres = (is_int($a) || is_float($a)) && (is_int($b) || is_float($b));
    return ($nombres ? $a == $b : $a === $b) ? []
        : ["$chemin : " . json_encode($a, JSON_UNESCAPED_UNICODE) . ' ≠ ' . json_encode($b, JSON_UNESCAPED_UNICODE)];
}

/** Un projet qui porte une ligne de chaque table que l'export lit. Voir l'en-tête. */
function fixture(PDO $pdo, Sites $sites, int $compteId): int
{
    $siteId = $sites->creer('Projet riche de vérification', $compteId, 'Porteur');
    $porteur = (int) $pdo->query("SELECT id FROM participant WHERE site_id = $siteId")->fetchColumn();
    $pdo->prepare("UPDATE site SET conduite = 'syntropique', latitude = 45.12345, longitude = 1.54321, ressource_eau = 'reserve',
                   ressource_eau_detail = 'une mare', notes = 'Une note', cree_le = '2026-01-02 03:04:05' WHERE id = ?")->execute([$siteId]);
    $pdo->prepare("INSERT INTO participant (site_id, nom, compte_id, role_projet, depuis_le) VALUES (?, 'Voisin', NULL, 'voisin', '2026-02-03 04:05:06')")
        ->execute([$siteId]);
    $voisin = (int) $pdo->lastInsertId();
    $plan = json_encode(['bordures' => [
        'externe' => ['nord' => ['actif' => true, 'largeur_m' => 0.4], 'sud' => ['actif' => false, 'largeur_m' => 0.4],
                      'est' => ['actif' => true, 'largeur_m' => 0.4], 'ouest' => ['actif' => true, 'largeur_m' => 0.4]],
        'interne' => ['nord' => ['actif' => true, 'largeur_m' => 0.3], 'sud' => ['actif' => true, 'largeur_m' => 0.3],
                      'est' => ['actif' => true, 'largeur_m' => 0.3], 'ouest' => ['actif' => true, 'largeur_m' => 0.3]],
    ], 'rattrapage' => 'planches', 'lignes' => ['1' => 2, '3' => 5], 'natures' => ['premiere' => 'annuelle', 'planches' => ['2' => 'annuelle']]], JSON_UNESCAPED_UNICODE);
    $pdo->prepare('INSERT INTO parcelle (site_id, code, nom, type, longueur_m, largeur_m, orientation_lignes, nb_planches, plan, notes,
                   surface_m2, production_principale, cree_le) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([$siteId, 'P1', 'Verger', 'perenne', 12.5, 8, 'nord-sud', 6, $plan, "Note [\n1, 2\n] \"guillemets\" é", 100, 'Fruits', '2026-08-01 10:00:00']);
    $p1 = (int) $pdo->lastInsertId();
    $pdo->prepare("UPDATE parcelle SET date_plantation = '2025-11-20', fin_plantation = '2026-04-15', stade = 'accumulation', tortue = 'zebre' WHERE id = ?")->execute([$p1]);
    $lp = $pdo->prepare('INSERT INTO ligne_plantation (parcelle_id, emplacement, rang, nom_scientifique, variete) VALUES (?, ?, ?, ?, ?)');
    $lp->execute([$p1, 'externe:nord', 1, 'Lavandula', '']);
    $lp->execute([$p1, 'externe:nord', 2, 'Symphytum officinale', 'bocking 14']);
    $lp->execute([$p1, 'interne:est', 1, 'Elaeagnus umbellata', '']);
    $lp->execute([$p1, 'planche:2:2', 1, 'Zea mays', '']);
    $pdo->prepare("INSERT INTO parcelle (site_id, code, nom, type) VALUES (?, 'P2', 'Potager', 'potager')")->execute([$siteId]);
    $p2 = (int) $pdo->lastInsertId();
    $ligne = $pdo->prepare('INSERT INTO scenario_ligne (parcelle_id, ordre, strate, espece, origine, espacement_m, nb_pieds, details, couleur_plan, role)
                            VALUES (?,?,?,?,?,?,?,?,?,?)');
    $ligne->execute([$p1, 1, 'basse', 'Consoude', 'achat', 0.5, null, json_encode(['quantite_mode' => 'manuel', 'version' => 1,
        'production_cle' => '', 'production' => null, 'fonctions' => ['biomasse', 'azote'], 'categorie' => 'service', 'couleur' => 'auto',
        'debut' => 0, 'fin' => 28, 'retrait' => null, 'emplacement' => 'bord_ext', 'repere' => '', 'beneficiaire' => '',
        'plantation' => '', 'recolte' => ''], JSON_UNESCAPED_UNICODE), 'vert', 'support']);
    $ligne->execute([$p1, 2, 'haute', 'Noyer', 'multiplication', 8, 4, json_encode(['emplacement' => 'centre', 'fonctions' => [],
        'categorie' => 'production', 'couleur' => 'rouge', 'debut' => 4, 'fin' => 20, 'retrait' => 12,
        'production' => ['texte' => 'Noix', 'priorite' => 'principale']], JSON_UNESCAPED_UNICODE), 'rouge', 'production_principale']);
    $pdo->prepare("INSERT INTO succession_ligne (parcelle_id, espece, role, planche, demi_planche, strate, nb_plants, date_semis, date_semis_repiquage) VALUES (?, 'Carotte', 'biomasse|bloque_herbe', 2, 'annuelle', 'basse', 40, '2026-03-10', '2026-04-01')")->execute([$p2]);
    $pdo->prepare("INSERT INTO releve_sol (parcelle_id, `date`, point, temoin, infiltration_mm_h, etat_humidite, jours_depuis_pluie, saisi_par)
                   VALUES (?, '2026-09-10', 'Point 1', 0, 15.5, 'ressuyé', 3, ?)")->execute([$p1, $porteur]);
    $pdo->prepare("INSERT INTO intervention (parcelle_id, `date`, type, duree_h, premiere_fois, saisi_par) VALUES (?, '2026-09-11', 'taille', 2.5, 1, ?)")
        ->execute([$p1, $compteId]);
    $pdo->prepare("INSERT INTO constat (parcelle_id, `date`, visible, vegetal, pousse_cm, sur_jours, temoin_decrit, temoin_pousse_cm, adventices, notes, saisi_par)
                   VALUES (?, '2026-09-13', 'oui', 'Maïs', 30.5, 1, 'mêmes plants, à 10 m', 12, 'oui', 'feuillage plus vert', ?)")->execute([$p1, $compteId]);
    $pdo->prepare("INSERT INTO celebration (parcelle_id, jalon, `date`, saisi_par, presents) VALUES (?, 'chantier', '2026-09-12', ?, 'Tous')")
        ->execute([$p1, $compteId]);
    $celebration = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO reconnaissance (celebration_id, participant_id, a_porte, a_appris) VALUES (?, ?, 'des plants', 'à tailler')")
        ->execute([$celebration, $voisin]);
    $pdo->prepare("INSERT INTO gisement (site_id, lieu, espece, nb_sujets) VALUES (?, 'Chez le voisin', 'Sureau', 3)")->execute([$siteId]);
    $pdo->prepare("INSERT INTO commande (site_id, espece, quantite, prix_unitaire) VALUES (?, 'Figuier', 3, 12.5)")->execute([$siteId]);
    $pdo->prepare("INSERT INTO ressource (site_id, categorie, libelle, manque) VALUES (?, 'materielle', 'Une brouette', 1)")->execute([$siteId]);
    $pdo->prepare("INSERT INTO synthese (site_id, phase, question, texte) VALUES (?, 'definition', 'vision', 'Un verger vivant')")->execute([$siteId]);
    $pdo->prepare("INSERT INTO espece (site_id, nom_scientifique, variete, nom_usuel, espacement_retenu_m, serre_necessaire, periode_semis, periode_repiquage, periode_recolte, cycle, delai_germination_j)
                   VALUES (?, 'Symphytum officinale', 'bocking 14', 'Consoude locale', 0.6, 0, 'mars-avril', 'mai', NULL, 'vivace', 12)")->execute([$siteId]);
    $reponse = $pdo->prepare("INSERT INTO reponse (site_id, participant_id, phase, question, texte) VALUES (?, ?, 'definition', ?, ?)");
    $reponse->execute([$siteId, $porteur, 'vision', 'Cultiver en forêt']);
    $reponse->execute([$siteId, $porteur, 'objectifs_productions', 'Fruits, noix']);
    $reponse->execute([$siteId, $voisin, 'vision', 'Voir un jardin']);
    $calque = $pdo->prepare('INSERT INTO calque_element (site_id, parcelle_id, calque, type, libelle, geometrie, proprietes) VALUES (?,?,?,?,?,?,?)');
    $calque->execute([$siteId, $p1, 'parcelles', 'polygone', 'P1', '[[45.1,1.1],[45.1,1.2],[45.2,1.2]]', '{"centre":[45.1,1.15],"etiquette":[45.11,1.12],"angle":30}']);
    $calque->execute([$siteId, null, 'fond', 'polygone', 'Terrain', '[[45,1],[45,1.3],[45.3,1.3]]', null]);
    $calque->execute([$siteId, null, 'relief', 'grille', 'Altitudes', '[[45,1],[45,1.1]]', '{"z":[312,315],"pas_m":30}']);
    return $siteId;
}

// ---------------------------------------------------------------------------------------------------------------

$echecs = 0;
$comptesCrees = [];
$sitesCrees = [];
$compteId = 0;

try {
    $identifiant = 'verif-' . bin2hex(random_bytes(4));
    $compteId = $comptes->creer($identifiant, bin2hex(random_bytes(12)), 'Compte de vérification', Role::Utilisateur);
    $comptesCrees[] = $compteId;

    $demandes = array_map('intval', array_slice($argv, 1));
    $cibles = $demandes !== [] ? $demandes
        : array_map('intval', $pdo->query('SELECT id FROM site ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));
    if ($demandes === []) {
        $fixture = fixture($pdo, $sites, $compteId);
        $sitesCrees[] = $fixture;
        $cibles[] = $fixture;
    }

    printf("Schéma de la base : %s (le code attend %d)\n\n", var_export($export->schemaDeLaBase(), true), \Syntropia\Version::SCHEMA);
    foreach ($cibles as $id) {
        $doc = $export->construire($id);
        if ($doc === null) {
            printf("  ✗ projet %d : introuvable\n", $id);
            $echecs++;
            continue;
        }
        $nom = (string) $doc['projet']['nom'];
        $encode = $export->encoder($doc);
        if (!$encode['ok']) {
            printf("  · projet %d « %s » : trop lourd pour un fichier d'export (%d octets), non vérifié\n", $id, $nom, $encode['octets']);
            continue;
        }
        $examen = $import->examiner($encode['json']);
        if (!$examen['ok']) {
            printf("  ✗ projet %d « %s » : le fichier exporté est refusé à l'import — %s\n", $id, $nom, $examen['erreurs'][0]);
            $echecs++;
            continue;
        }
        $moi = isset($examen['apercu']['participants']['p1']) ? 'p1' : null;
        $copie = $import->creer($examen['plan'], $examen['apercu'], $compteId, $nom, $moi);
        $sitesCrees[] = $copie;
        $docCopie = $export->construire($copie);
        foreach ([&$doc, &$docCopie] as &$d) {
            unset($d['exporte_le'], $d['integrite']);
        }
        unset($d);
        $diff = ecarts($doc, $docCopie);
        $lignes = Export::inventaire($doc);
        $resume = 'participants ' . $lignes['participants'] . ', réponses ' . $lignes['reponses'] . ', parcelles ' . $lignes['parcelles']
            . ', végétaux ' . $lignes['vegetaux'] . ', tracés ' . $lignes['traces'] . ', interventions ' . $lignes['interventions'] . ', constats ' . $lignes['constats'];
        if ($diff === []) {
            printf("  ✓ projet %d « %s » (%d octets) — identique. %s\n", $id, $nom, $encode['octets'], $resume);
        } else {
            $echecs++;
            printf("  ✗ projet %d « %s » — %d différence(s) :\n", $id, $nom, count($diff));
            foreach (array_slice($diff, 0, 8) as $ligne) {
                echo "      $ligne\n";
            }
        }
    }

    // Les trois refus qui gardent la porte, sur le dernier projet exporté.
    echo "\nRefus attendus :\n";
    $base = $export->construire($cibles[array_key_last($cibles)]);
    $refuse = static function (string $quoi, callable $modifie, bool $recalcule) use ($base, $import, &$echecs): void {
        $d = $base;
        $modifie($d);
        if ($recalcule) {
            $d['integrite']['valeur'] = Export::empreinte($d);
        }
        $r = $import->examiner(json_encode($d, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
        if ($r['ok']) {
            $echecs++;
            echo "  ✗ $quoi : ACCEPTÉ, à tort\n";
        } else {
            echo "  ✓ $quoi : refusé — " . $r['erreurs'][0] . "\n";
        }
    };
    $refuse('fichier modifié d\'une lettre', static function (array &$d): void { $d['projet']['nom'] .= 'x'; }, false);
    $refuse('schéma plus récent que la base', static function (array &$d): void { $d['versions']['schema'] = 99; }, true);
    $refuse('colonne interdite (compte_id)', static function (array &$d): void { $d['participants'][0]['compte_id'] = 1; }, true);
} catch (\Throwable $e) {
    $echecs++;
    fwrite(STDERR, "\n✗ Interrompu : " . $e->getMessage() . "\n");
} finally {
    // Tout ce que le script a créé disparaît — les projets d'origine n'ont jamais été touchés.
    foreach (array_unique($sitesCrees) as $id) {
        $pdo->prepare('DELETE FROM site WHERE id = ?')->execute([$id]);
    }
    foreach ($comptesCrees as $id) {
        Database::comptes()->prepare('DELETE FROM compte WHERE id = ?')->execute([$id]);
    }
}

echo "\n" . ($echecs === 0 ? 'Tout est identique, et les refus tiennent.' : "$echecs échec(s).") . "\n";
exit($echecs === 0 ? 0 : 1);
