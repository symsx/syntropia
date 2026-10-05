<?php

declare(strict_types=1);

namespace Syntropia\Web;

use Syntropia\DessinsDossier;
use Syntropia\Planches;
use Syntropia\Sigles;

/**
 * Le plan d'une parcelle tel qu'il s'imprime : le dessin, les plants des bordures avec leur couleur et leur sigle, la
 * légende. Lecture seule.
 *
 * Sorti de `dossier-projet.php` le 2026-10-05 pour que le feuillet de chantier (`feuillet-chantier.php`,
 * `chantier-dossier-projet.md`, Phase 5) imprime le même plan, avec les mêmes sigles, que le Dossier Projet.
 */
final class PlanImprime
{
    /** Les couleurs des plants, dans l'ordre où les espèces apparaissent — celles de l'écran. */
    public const COULEURS = ['#285738', '#8b4528', '#365b8c', '#80528a', '#675b20', '#a13d53', '#1f6f78', '#b36b00', '#556b2f', '#7a3b3b'];

    public function __construct(private LignesPlantation $lignes = new LignesPlantation()) {}

    /**
     * @param array<string, mixed> $p une ligne de `parcelle`
     * @return array{etat: array<string, array<string, mixed>>, especes: array<string, array<string, mixed>>,
     *     plan: string, legendePlan: string, legende: list<array<string, mixed>>}
     *     `especes` : nom scientifique et variété → nom, variété, sigle, couleur, nombre de plants dessinés
     */
    public function dUneParcelle(array $p, int $siteId): array
    {
        $dimensionnee = $p['longueur_m'] !== null && $p['largeur_m'] !== null;
        $etat = $dimensionnee ? $this->lignes->etat($p, $siteId) : [];
        $especes = [];
        $plants = [];
        foreach ($etat as $b) {
            if ($b['places'] === []) {
                continue;
            }
            foreach ($b['places'] as $pl) {
                $especes[$pl['nom_scientifique'] . "\0" . $pl['variete']] ??= ['nom' => $pl['nom'], 'variete' => $pl['variete'], 'n' => 0];
            }
            $g = $b['geometrie'];
            $compte = $b['compte'];
            if ($g !== null && $compte !== null && $compte['ok']) {
                $dx = ($g['x2'] - $g['x1']) / $g['longueur'];
                $dy = ($g['y2'] - $g['y1']) / $g['longueur'];
                foreach ($compte['positions'] as [$k, $d]) {
                    $pl = $b['places'][$k];
                    $cle = $pl['nom_scientifique'] . "\0" . $pl['variete'];
                    $especes[$cle]['n']++;
                    $env = $pl['largeur'] !== null && $pl['espacement'] !== null ? min($pl['largeur'], 2 * $pl['espacement']) : ($pl['largeur'] ?? $pl['espacement'] ?? 0.3);
                    $plants[] = ['x' => $g['x1'] + $d * $dx, 'y' => $g['y1'] + $d * $dy, 'cle' => $cle, 'r' => $env / 2];
                }
            }
        }
        $sigles = Sigles::calculer(array_values(array_map(static fn (array $e): array => ['nom' => $e['nom'], 'variete' => $e['variete']], $especes)));
        $i = 0;
        foreach ($especes as $cle => $e) {
            $especes[$cle]['sigle'] = $sigles[$i];
            $especes[$cle]['couleur'] = self::COULEURS[$i % count(self::COULEURS)];
            $i++;
        }
        foreach ($plants as &$pl) {
            $pl['couleur'] = $especes[$pl['cle']]['couleur'];
            $pl['sigle'] = $especes[$pl['cle']]['sigle'];
        }
        unset($pl);

        return [
            'etat' => $etat,
            'especes' => $especes,
            'plan' => DessinsDossier::plan($p, $plants),
            // Ce que le dessin montre, et seulement cela : les hachures n'existent qu'en zèbre et en abeille, la ligne des
            // lianes qu'en liane.
            'legendePlan' => 'Bordures en ocre' . (Planches::natures($p) !== [] ? ', planches pérennes hachurées' : '')
                . ' ; la ligne centrale de chaque planche en trait plein' . (($p['tortue'] ?? null) === 'liane' ? ', la ligne des lianes en tirets violets' : '') . '.',
            'legende' => array_values(array_filter($especes, static fn (array $e): bool => $e['n'] > 0)),
        ];
    }
}
