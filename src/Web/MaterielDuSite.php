<?php

declare(strict_types=1);

namespace Syntropia\Web;

use Syntropia\Materiel;
use Syntropia\Scenario;

/**
 * Le matériel végétal de quelques parcelles d'un site : chaque végétal, son origine et sa quantité, avant regroupement
 * (`Materiel::regrouper()`).
 *
 * Sorti de `conception-materiel-et-eau.php` le 2026-10-04 pour que le Dossier Projet (`chantier-dossier-projet.md`,
 * Phase 3) imprime la même liste que l'écran, sans la recompter à sa façon. Lecture seule.
 *
 * Les règles, inchangées : un potager compte son plan de culture ; une autre parcelle, son scénario. Les plants posés sur
 * les lignes du plan (bordures, lignes de service des planches) font foi : une plante du scénario déclarée en bordure
 * prend leur compte, et ce que le plan porte sans que le scénario le nomme s'ajoute (chantier « peuplement de la
 * parcelle », Phase 7). Sans quantité saisie, l'espacement de la ligne, à défaut celui du référentiel, l'estime ; la
 * ligne centrale d'une parcelle pérenne se compte en série (`Materiel::quantitesScenario()`).
 */
final class MaterielDuSite
{
    public function __construct(
        private Parcelles $parcelles = new Parcelles(),
        private Cultures $cultures = new Cultures(),
        private LignesPlantation $lignes = new LignesPlantation(),
        private Especes $especes = new Especes(),
    ) {}

    /**
     * @param list<array<string, mixed>> $parcelles des lignes de `parcelle`, toutes du site
     * @return array{entrees: list<array<string, mixed>>, sansScenario: list<string>, sansCulture: list<string>}
     */
    public function entrees(int $siteId, array $parcelles): array
    {
        // Les fiches du référentiel, par nom usuel ou scientifique : leurs espacements et leur cycle.
        $fiches = [];
        foreach ($this->especes->toutes($siteId) as $e) {
            foreach ([$e['nom_usuel'], $e['nom_scientifique']] as $nom) {
                if ((string) $nom !== '') {
                    $fiches[mb_strtolower((string) $nom)] ??= $e;
                }
            }
        }
        $especeDe = static fn (string $nom): ?array => $fiches[mb_strtolower(trim($nom))] ?? null;
        // L'espacement du référentiel pour une culture du potager : de service, à défaut de production.
        $espacementDe = static function (string $nom) use ($especeDe): ?float {
            $e = $especeDe($nom);
            $v = $e === null ? 0.0 : (float) ($e['espacement_retenu_m'] ?: $e['espacement_monoculture_m']);
            return $v > 0 ? $v : null;
        };

        $entrees = [];
        $sansScenario = [];
        $sansCulture = [];
        foreach ($parcelles as $p) {
            $bandes = Materiel::bandes($p);
            $duPlan = Materiel::lignesDuPlan($this->lignes->etat($p, $siteId), $this->lignes->etatPlanches($p, $siteId));
            $bordAvecLignes = array_flip(array_column($duPlan, 'ou'));
            $pris = [];
            // Un potager n'a pas de scénario de strates et de temps : ses cultures sont son plan de culture.
            if (($p['type'] ?? '') === 'potager') {
                $cultures = $this->cultures->dUneParcelle((int) $p['id']);
                if ($cultures === []) {
                    $sansCulture[] = (string) $p['code'];
                }
                foreach ($cultures as $c) {
                    $entrees[] = ['ligne' => ['espece' => $c['espece'], 'origine' => 'potager'], 'parcelle' => (string) $p['code'],
                        'qte' => Materiel::quantiteCulture($c, $bandes, $espacementDe((string) $c['espece']))];
                }
                foreach ($duPlan as $d) {
                    $entrees[] = ['ligne' => ['espece' => $d['espece'], 'origine' => $d['origine']], 'parcelle' => (string) $p['code'],
                        'qte' => ['n' => $d['n'], 'mode' => 'plan', 'espacement' => null]];
                }
                continue;
            }
            $lignes = $this->parcelles->scenario((int) $p['id']);
            if ($lignes === []) {
                $sansScenario[] = (string) $p['code'];
            }
            // La ligne centrale comptée en série (`Materiel::quantitesScenario()`).
            $quantites = Materiel::quantitesScenario($lignes, $bandes, $especeDe);
            foreach (array_values($lignes) as $rang => $l) {
                $ou = Scenario::details($l)['emplacement'] ?? 'centre';
                if (in_array($ou, ['bord_ext', 'bord_int'], true) && isset($bordAvecLignes[$ou])) {
                    $nom = mb_strtolower(trim((string) $l['espece']));
                    $n = 0;
                    $trouve = false;
                    foreach ($duPlan as $k => $d) {
                        if ($d['ou'] === $ou && in_array($nom, [mb_strtolower($d['espece']), mb_strtolower($d['nom_scientifique'])], true)) {
                            $trouve = true;
                            $pris[$k] = true;
                            $n = $n === null || $d['n'] === null ? null : $n + $d['n'];
                        }
                    }
                    $entrees[] = ['ligne' => $l, 'parcelle' => (string) $p['code'], 'qte' => ['n' => $trouve ? $n : null, 'mode' => 'plan', 'espacement' => null]];
                    continue;
                }
                $entrees[] = ['ligne' => $l, 'parcelle' => (string) $p['code'], 'qte' => $quantites[$rang]];
            }
            // Ce que le plan porte sans que le scénario le nomme.
            foreach ($duPlan as $k => $d) {
                if (!isset($pris[$k])) {
                    $entrees[] = ['ligne' => ['espece' => $d['espece'], 'origine' => $d['origine']], 'parcelle' => (string) $p['code'],
                        'qte' => ['n' => $d['n'], 'mode' => 'plan', 'espacement' => null]];
                }
            }
        }
        return ['entrees' => $entrees, 'sansScenario' => $sansScenario, 'sansCulture' => $sansCulture];
    }
}
