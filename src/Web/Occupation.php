<?php

declare(strict_types=1);

namespace Syntropia\Web;

use PDO;
use Syntropia\Database;
use Syntropia\Espacement;
use Syntropia\Materiel;
use Syntropia\Scenario;
use Syntropia\Strates;

/**
 * L'occupation au sol d'une parcelle, strate par strate (Théry, ch. 05 — `Strates::occupation()`).
 *
 * Les plants se comptent comme dans Matériel et eau : **les lignes du plan font foi** (bordures, ligne des lianes, lignes
 * de service des planches) ; s'y ajoutent les cultures d'un potager, ou les végétaux du scénario d'une parcelle pérenne —
 * leur nombre saisi, à défaut l'estimation de `Materiel` ; un végétal du scénario déclaré en bordure ne compte pas deux
 * fois quand le plan porte déjà des lignes de ce côté. La strate est celle qui est saisie (scénario, culture), à défaut
 * celle que propose le référentiel pour son usage (`Strates::proposee()` — sa strate de production ou de service : une
 * plante de bordure ou d'une ligne de service rend un service, une culture de potager est une production). La surface est celle de la parcelle, longueur × largeur.
 */
final class Occupation
{
    public function __construct(private ?PDO $pdo = null) {}

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::syntropia();
    }

    /** @return array<string, array<string, mixed>>|null `Strates::occupation()`, `null` sans dimensions */
    public function dUneParcelle(array $parcelle, int $siteId): ?array
    {
        $surface = (float) ($parcelle['longueur_m'] ?? 0) * (float) ($parcelle['largeur_m'] ?? 0);
        if ($surface <= 0) {
            return null;
        }
        $especes = new Especes($this->pdo());
        $parId = [];
        $parNom = [];
        foreach ($especes->toutes($siteId) as $e) {
            $parId[(int) $e['id']] = $e;
            foreach ([$e['nom_usuel'], $e['nom_scientifique']] as $nom) {
                if ((string) $nom !== '') {
                    $parNom[mb_strtolower(trim((string) $nom))] ??= $e;
                }
            }
        }
        $trouver = static fn (string $nom): ?array => $parNom[mb_strtolower(trim($nom))] ?? $especes->parNomUsage($nom, $siteId);
        $largeur = static fn (?array $e): ?float => $e !== null && ($e['largeur_m'] ?? null) !== null ? (float) $e['largeur_m'] : null;
        $production = static fn (?array $e): bool => in_array('production', explode('|', (string) ($e['role'] ?? '')), true);

        $plantes = [];
        // Les lignes du plan : leurs plants comptés.
        $lignes = new LignesPlantation($this->pdo(), $especes);
        $bordAvecLignes = [];
        foreach ($lignes->etat($parcelle, $siteId) as $cle => $b) {
            if ($b['geometrie'] === null) {
                continue;
            }
            $cadre = explode(':', (string) $cle)[0];
            $bordAvecLignes[$cadre] = true;
            foreach ($b['places'] as $k => $pl) {
                $e = $pl['id'] !== null ? ($parId[$pl['id']] ?? null) : null;
                // Une bordure rend un service (bloque-herbe, biomasse) ; la ligne des lianes porte aussi des productions
                // (vigne, kiwi).
                $plantes[] = ['nom' => $pl['nom'], 'strate' => Strates::proposee($e, $cadre === 'liane' && $production($e)),
                    'n' => $b['compte'] !== null && $b['compte']['ok'] ? (int) $b['compte']['comptes'][$k] : null, 'largeur' => $largeur($e)];
            }
        }
        foreach ($lignes->etatPlanches($parcelle, $siteId) as $p) {
            foreach ($p['lignes'] as $l) {
                if ($l !== null) {
                    // Une ligne de service : la strate de la biomasse et du service, la plus basse.
                    $plantes[] = ['nom' => $l['nom'], 'strate' => Strates::proposee($l['espece'], false), 'n' => $l['compte'], 'largeur' => $largeur($l['espece'])];
                }
            }
        }

        $bandes = Materiel::bandes($parcelle);
        if (($parcelle['type'] ?? '') === 'potager') {
            foreach ((new Cultures($this->pdo()))->dUneParcelle((int) $parcelle['id']) as $c) {
                $e = $trouver((string) $c['espece']);
                $plantes[] = ['nom' => (string) $c['espece'],
                    'strate' => ($c['strate'] ?? null) !== null ? (string) $c['strate'] : Strates::proposee($e, true),
                    'n' => Materiel::quantiteCulture($c, $bandes, Espacement::deCulture($e))['n'], 'largeur' => $largeur($e)];
            }
        } else {
            $cadres = ['bord_ext' => 'externe', 'bord_int' => 'interne'];
            $scenario = (new Parcelles($this->pdo()))->scenario((int) $parcelle['id']);
            // La ligne centrale comptée en série, comme dans Matériel et eau.
            $quantites = Materiel::quantitesScenario($scenario, $bandes, $trouver);
            foreach (array_values($scenario) as $rang => $l) {
                $ou = Scenario::details($l)['emplacement'] ?? 'centre';
                if (isset($cadres[$ou], $bordAvecLignes[$cadres[$ou]])) {
                    continue; // le plan porte déjà ce qui pousse de ce côté
                }
                $plantes[] = ['nom' => (string) $l['espece'], 'strate' => (string) $l['strate'],
                    'n' => $quantites[$rang]['n'], 'largeur' => $largeur($trouver((string) $l['espece']))];
            }
        }
        return Strates::occupation($plantes, $surface);
    }
}
