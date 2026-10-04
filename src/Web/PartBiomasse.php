<?php

declare(strict_types=1);

namespace Syntropia\Web;

use PDO;
use Syntropia\Database;
use Syntropia\Espacement;
use Syntropia\Materiel;

/**
 * La part de plants de biomasse d'une parcelle, face aux 75 % visés (`Espacement::PART_BIOMASSE`).
 *
 * Voir `chantier-espacement-des-services.md`, Phase 4. Décisions du porteur (2026-10-04) : **au potager, par planche**
 * — ses cultures et ses lignes de service ; **en pérenne, par ligne** — la ligne centrale du scénario, comptée en série
 * comme dans Matériel et eau (`Materiel::quantitesScenario()`). Sous 75 %, **une information, jamais une alerte**.
 *
 * Un plant est de biomasse quand sa fonction sur la ligne l'est : une ligne de production ne l'est jamais ; une ligne de
 * service l'est si ses fonctions (à défaut le rôle de l'espèce) donnent le service biomasse (`Espacement::serviceDe()`) ;
 * une culture de potager, si son rôle le donne. Lecture seule.
 */
final class PartBiomasse
{
    public function __construct(private ?PDO $pdo = null) {}

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::syntropia();
    }

    /**
     * @param array<string, mixed> $parcelle une ligne de `parcelle`
     * @return list<array{libelle: string, biomasse: int, total: int, part: ?float, atteinte: ?bool, sans_nombre: list<string>}>
     *         une entrée par planche (potager) ou pour la ligne centrale (pérenne) ; vide sans rien à compter
     */
    public function dUneParcelle(array $parcelle, int $siteId): array
    {
        $bandes = Materiel::bandes($parcelle);
        $especes = new Especes($this->pdo());
        $parNom = [];
        foreach ($especes->toutes($siteId) as $e) {
            foreach ([$e['nom_usuel'], $e['nom_scientifique']] as $nom) {
                if ((string) $nom !== '') {
                    $parNom[mb_strtolower(trim((string) $nom))] ??= $e;
                }
            }
        }
        $trouver = static fn (string $nom): ?array => $parNom[mb_strtolower(trim($nom))] ?? $especes->parNomUsage($nom, $siteId);

        if (($parcelle['type'] ?? '') === 'potager') {
            $planches = [];
            foreach ((new Cultures($this->pdo()))->dUneParcelle((int) $parcelle['id']) as $c) {
                if ($c['planche'] === null) {
                    continue;
                }
                $e = $trouver((string) $c['espece']);
                $esp = $e === null ? null : (float) ($e['espacement_retenu_m'] ?: $e['espacement_monoculture_m']);
                $planches[(int) $c['planche']][] = ['nom' => (string) $c['espece'],
                    'n' => Materiel::quantiteCulture($c, $bandes, $esp !== null && $esp > 0 ? $esp : null)['n'],
                    'biomasse' => Espacement::service($c['role'] ?? null) === 'biomasse'];
            }
            foreach ((new LignesPlantation($this->pdo(), $especes))->etatPlanches($parcelle, $siteId) as $numero => $ep) {
                foreach ($ep['lignes'] as $l) {
                    if ($l !== null) {
                        $planches[(int) $numero][] = ['nom' => $l['nom'], 'n' => $l['compte'],
                            'biomasse' => Espacement::service($l['espece']['role'] ?? null) === 'biomasse'];
                    }
                }
            }
            ksort($planches);
            $out = [];
            foreach ($planches as $numero => $plantes) {
                $out[] = ['libelle' => 'Planche ' . $numero] + Espacement::partBiomasse($plantes);
            }
            return $out;
        }

        $lignes = array_values((new Parcelles($this->pdo()))->scenario((int) $parcelle['id']));
        $quantites = Materiel::quantitesScenario($lignes, $bandes, $trouver);
        $plantes = [];
        foreach ($lignes as $rang => $l) {
            if (!$quantites[$rang]['sur_ligne']) {
                continue;
            }
            $plantes[] = ['nom' => (string) $l['espece'], 'n' => $quantites[$rang]['n'],
                'biomasse' => Espacement::ligneDeBiomasse($l, $trouver((string) $l['espece']))];
        }
        return $plantes === [] ? [] : [['libelle' => 'Ligne centrale'] + Espacement::partBiomasse($plantes)];
    }
}
