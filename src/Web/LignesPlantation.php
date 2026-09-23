<?php

declare(strict_types=1);

namespace Syntropia\Web;

use PDO;
use Syntropia\Bordures;
use Syntropia\Database;

/**
 * Les lignes de plants d'une parcelle (`ligne_plantation`) — pour l'instant celles des bordures.
 *
 * Voir `chantier-peuplement-parcelle.md`, Phase 2. Une ligne = une série ordonnée d'espèces du référentiel, un
 * enregistrement par place (`rang`). Même conduite que `Interventions` : l'appartenance de la parcelle au site se
 * vérifie à l'appel, chaque requête est bornée à la parcelle, et l'écriture se fait sous verrou de version — ici la
 * version de toute la série d'un emplacement, qu'on remplace d'un bloc.
 */
final class LignesPlantation
{
    public function __construct(private ?PDO $pdo = null, private ?Especes $especes = null) {}

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::syntropia();
    }

    private function especes(): Especes
    {
        return $this->especes ??= new Especes($this->pdo());
    }

    /** @return array<string, list<array<string, mixed>>> emplacement → places de la série, dans l'ordre */
    public function dUneParcelle(int $parcelleId): array
    {
        $req = $this->pdo()->prepare('SELECT * FROM ligne_plantation WHERE parcelle_id = ? ORDER BY emplacement, rang');
        $req->execute([$parcelleId]);
        $out = [];
        foreach ($req->fetchAll() as $l) {
            $out[(string) $l['emplacement']][] = $l;
        }
        return $out;
    }

    /** @param list<array<string, mixed>> $serie les places d'un emplacement */
    public static function version(array $serie): string
    {
        $cles = array_map(static fn (array $l): array => [(int) $l['rang'], (string) $l['nom_scientifique'], (string) $l['variete']], $serie);
        return hash('sha256', json_encode($cles, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /**
     * Remplace la série d'un emplacement. Une série vide retire la ligne.
     *
     * @param list<int> $idsEspeces les espèces, dans l'ordre de l'alternance — des identifiants du référentiel, cadre
     *                              ou surcharge de ce site
     * @return array{ok: bool, erreurs: array<string, string>}
     */
    public function enregistrer(int $parcelleId, int $siteId, string $emplacement, array $idsEspeces, string $versionRecue): array
    {
        if (Bordures::depuisCle($emplacement) === null) {
            return ['ok' => false, 'erreurs' => ['emplacement' => 'Cette bordure n’existe pas.']];
        }
        if (count($idsEspeces) > Bordures::MAX_SERIE) {
            return ['ok' => false, 'erreurs' => ['serie' => 'Une ligne compte au plus ' . Bordures::MAX_SERIE . ' espèces dans sa série.']];
        }
        $places = [];
        foreach ($idsEspeces as $id) {
            $e = is_int($id) && $id > 0 ? $this->especes()->parId($id) : null;
            if ($e === null || !in_array((int) $e['site_id'], [0, $siteId], true)) {
                return ['ok' => false, 'erreurs' => ['serie' => 'Une espèce de la série n’est pas dans le référentiel de ce projet.']];
            }
            $places[] = [(string) $e['nom_scientifique'], (string) $e['variete']];
        }

        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $req = $pdo->prepare('SELECT * FROM ligne_plantation WHERE parcelle_id = ? AND emplacement = ? ORDER BY rang FOR UPDATE');
            $req->execute([$parcelleId, $emplacement]);
            if (!hash_equals(self::version($req->fetchAll()), $versionRecue)) {
                $pdo->rollBack();
                return ['ok' => false, 'erreurs' => ['version' => 'Cette ligne a changé depuis que vous l’avez ouverte : rechargez la page, puis refaites votre changement.']];
            }
            $pdo->prepare('DELETE FROM ligne_plantation WHERE parcelle_id = ? AND emplacement = ?')->execute([$parcelleId, $emplacement]);
            $ins = $pdo->prepare('INSERT INTO ligne_plantation (parcelle_id, emplacement, rang, nom_scientifique, variete) VALUES (?, ?, ?, ?, ?)');
            foreach ($places as $k => [$nom, $variete]) {
                $ins->execute([$parcelleId, $emplacement, $k + 1, $nom, $variete]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        return ['ok' => true, 'erreurs' => []];
    }

    /**
     * Ce que le plan affiche d'une parcelle : pour chaque ligne possible (côté actif) ou enregistrée (même d'un côté
     * devenu inactif), sa série résolue au référentiel du site, et le compte.
     *
     * @param array<string, mixed> $parcelle
     * @return array<string, array<string, mixed>>
     */
    public function etat(array $parcelle, int $siteId): array
    {
        $geometrie = Bordures::lignes($parcelle);
        $series = $this->dUneParcelle((int) $parcelle['id']);
        $cles = array_unique(array_merge(array_keys($geometrie),
            array_filter(array_keys($series), static fn (string $c): bool => Bordures::depuisCle($c) !== null)));
        $ordre = [];
        foreach (array_keys(Bordures::CADRES) as $cadre) {
            foreach (array_keys(Bordures::COTES) as $cote) {
                $ordre[] = Bordures::cle($cadre, $cote);
            }
        }
        $cles = array_values(array_intersect($ordre, $cles));

        $out = [];
        foreach ($cles as $cle) {
            $serie = $series[$cle] ?? [];
            $places = [];
            foreach ($serie as $l) {
                $e = $this->especes()->parNom((string) $l['nom_scientifique'], (string) $l['variete'], $siteId);
                $esp = $e !== null && $e['espacement_retenu_m'] !== null ? (float) $e['espacement_retenu_m'] : null;
                $places[] = ['nom_scientifique' => (string) $l['nom_scientifique'], 'variete' => (string) $l['variete'],
                    'nom' => $e !== null ? (string) ($e['nom_usuel'] ?: $e['nom_scientifique']) : (string) $l['nom_scientifique'],
                    'connue' => $e !== null, 'espacement' => $esp, 'id' => $e !== null ? (int) $e['id'] : null];
            }
            $geo = $geometrie[$cle] ?? null;
            $compte = $geo !== null && $places !== []
                ? Bordures::compter($geo['longueur'], array_column($places, 'espacement')) : null;
            $out[$cle] = ['geometrie' => $geo, 'places' => $places, 'compte' => $compte, 'version' => self::version($serie)];
        }
        return $out;
    }
}
