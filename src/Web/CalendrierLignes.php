<?php

declare(strict_types=1);

namespace Syntropia\Web;

use PDO;
use Syntropia\Bordures;
use Syntropia\Database;
use Syntropia\Perturbation;

/**
 * Le calendrier PRÉVISIONNEL des lignes d'une parcelle (`ligne_calendrier`, `perturbation_prevue`).
 *
 * Voir `chantier-film-de-la-parcelle.md`, Phase 2 bis. Une ligne — un côté de bordure (`externe:nord`…) ou une ligne de
 * service d'une planche (`planche:3:2`) — a sa **mise en place prévue** (plantation ou semis), sa **fin prévue** (une
 * ligne de planche), et **une liste de perturbations prévues**, chacune une date et un geste (décision du porteur,
 * 2026-09-23). Ce sont des prévisions : la date réelle se donne en Réalisation, le jour d'une perturbation s'adapte au jour
 * opportun, et le journal (`Interventions`) garde ce qui a été fait.
 *
 * Même conduite que les autres services : l'appartenance de la parcelle au site se vérifie à l'appel, chaque requête est
 * bornée à la parcelle ; les dates d'une ligne se réécrivent sous le verrou de leur version.
 */
final class CalendrierLignes
{
    /** Les gestes d'une perturbation prévue : ceux du journal, sans le désherbage. */
    public const GESTES = ['coupe_vert', 'taille_hiver', 'fauche', 'recepage', 'affaiblissement'];

    public const MAX_PERTURBATIONS = 24;

    public function __construct(private ?PDO $pdo = null) {}

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::syntropia();
    }

    /** L'emplacement est-il celui d'une ligne : un côté de bordure, ou `planche:N:K` ? */
    public static function emplacementValide(string $emplacement): bool
    {
        return Bordures::depuisCle($emplacement) !== null || preg_match('/^planche:[1-9][0-9]{0,2}:[1-9][0-9]?$/D', $emplacement) === 1;
    }

    /** @return array{dates: array<string, array<string, mixed>>, perturbations: array<string, list<array<string, mixed>>>} */
    public function dUneParcelle(int $parcelleId): array
    {
        $req = $this->pdo()->prepare('SELECT * FROM ligne_calendrier WHERE parcelle_id = ?');
        $req->execute([$parcelleId]);
        $dates = [];
        foreach ($req->fetchAll() as $l) {
            $dates[(string) $l['emplacement']] = $l;
        }
        $req = $this->pdo()->prepare('SELECT * FROM perturbation_prevue WHERE parcelle_id = ? ORDER BY emplacement, date_prevue, id');
        $req->execute([$parcelleId]);
        $perturbations = [];
        foreach ($req->fetchAll() as $l) {
            $perturbations[(string) $l['emplacement']][] = $l;
        }
        return ['dates' => $dates, 'perturbations' => $perturbations];
    }

    /** @param array<string, mixed>|null $ligne */
    public static function version(?array $ligne): string
    {
        return hash('sha256', json_encode($ligne === null ? null : [$ligne['mise_en_place_prevue'], $ligne['fin_prevue']], JSON_THROW_ON_ERROR));
    }

    private static function date(string $valeur): ?string
    {
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $valeur);
        return $d !== false && $d->format('Y-m-d') === $valeur && $d >= new \DateTimeImmutable('2000-01-01') && $d <= new \DateTimeImmutable('2100-12-31')
            ? $valeur : null;
    }

    /**
     * Les dates prévues d'une ligne. Deux dates vides retirent la ligne du calendrier.
     *
     * @return array{ok: bool, erreurs: array<string, string>}
     */
    public function enregistrerDates(int $parcelleId, string $emplacement, string $mise, string $fin, string $versionRecue): array
    {
        if (!self::emplacementValide($emplacement)) {
            return ['ok' => false, 'erreurs' => ['emplacement' => 'Cette ligne n’existe pas.']];
        }
        $mise = trim($mise);
        $fin = trim($fin);
        $erreurs = [];
        $dMise = $mise === '' ? null : self::date($mise);
        $dFin = $fin === '' ? null : self::date($fin);
        if ($mise !== '' && $dMise === null) {
            $erreurs['mise_en_place_prevue'] = 'La mise en place prévue doit être une date valide.';
        }
        if ($fin !== '' && $dFin === null) {
            $erreurs['fin_prevue'] = 'La fin prévue doit être une date valide.';
        }
        if ($dMise !== null && $dFin !== null && $dFin < $dMise) {
            $erreurs['fin_prevue'] = 'La fin prévue ne peut pas précéder la mise en place.';
        }
        if ($erreurs !== []) {
            return ['ok' => false, 'erreurs' => $erreurs];
        }
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $req = $pdo->prepare('SELECT * FROM ligne_calendrier WHERE parcelle_id = ? AND emplacement = ? FOR UPDATE');
            $req->execute([$parcelleId, $emplacement]);
            $actuelle = $req->fetch() ?: null;
            if (!hash_equals(self::version($actuelle), $versionRecue)) {
                $pdo->rollBack();
                return ['ok' => false, 'erreurs' => ['version' => 'Ces dates ont changé depuis que vous les avez ouvertes : rechargez la page, puis refaites votre saisie.']];
            }
            if ($dMise === null && $dFin === null) {
                $pdo->prepare('DELETE FROM ligne_calendrier WHERE parcelle_id = ? AND emplacement = ?')->execute([$parcelleId, $emplacement]);
            } else {
                $pdo->prepare('INSERT INTO ligne_calendrier (parcelle_id, emplacement, mise_en_place_prevue, fin_prevue) VALUES (?, ?, ?, ?)
                               ON DUPLICATE KEY UPDATE mise_en_place_prevue = VALUES(mise_en_place_prevue), fin_prevue = VALUES(fin_prevue)')
                    ->execute([$parcelleId, $emplacement, $dMise, $dFin]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        return ['ok' => true, 'erreurs' => []];
    }

    /** @return array{ok: bool, erreurs: array<string, string>} */
    public function ajouterPerturbation(int $parcelleId, string $emplacement, string $date, string $geste): array
    {
        if (!self::emplacementValide($emplacement)) {
            return ['ok' => false, 'erreurs' => ['emplacement' => 'Cette ligne n’existe pas.']];
        }
        $d = self::date(trim($date));
        $erreurs = [];
        if ($d === null) {
            $erreurs['date_prevue'] = 'Dites la date prévue de la perturbation.';
        }
        if (!in_array($geste, self::GESTES, true)) {
            $erreurs['geste'] = 'Choisissez le geste.';
        }
        $req = $this->pdo()->prepare('SELECT COUNT(*) FROM perturbation_prevue WHERE parcelle_id = ? AND emplacement = ?');
        $req->execute([$parcelleId, $emplacement]);
        if ((int) $req->fetchColumn() >= self::MAX_PERTURBATIONS) {
            $erreurs['perturbations'] = 'Une ligne compte au plus ' . self::MAX_PERTURBATIONS . ' perturbations prévues.';
        }
        if ($erreurs !== []) {
            return ['ok' => false, 'erreurs' => $erreurs];
        }
        $this->pdo()->prepare('INSERT INTO perturbation_prevue (parcelle_id, emplacement, date_prevue, geste) VALUES (?, ?, ?, ?)')
            ->execute([$parcelleId, $emplacement, $d, $geste]);
        return ['ok' => true, 'erreurs' => []];
    }

    public function retirerPerturbation(int $id, int $parcelleId): bool
    {
        $req = $this->pdo()->prepare('DELETE FROM perturbation_prevue WHERE id = ? AND parcelle_id = ?');
        $req->execute([$id, $parcelleId]);
        return $req->rowCount() > 0;
    }

    /** Le libellé d'un geste, celui du journal. */
    public static function libelleGeste(string $geste): string
    {
        return Perturbation::TYPES[$geste] ?? $geste;
    }

    /**
     * Les dates que le calendrier de l'espèce au référentiel suggère pour une ligne : la mise en place (repiquage, à défaut
     * semis) et la fin (récolte). Une série de bordure prend la première espèce qui en a un. Des repères, jamais écrits sans
     * l'accord de la personne. `null` si aucune espèce de la ligne n'a de calendrier.
     *
     * @param list<array<string, mixed>|null> $especes les espèces de la ligne, dans l'ordre
     * @return array{mise: ?string, fin: ?string, espece: string}|null
     */
    public static function repere(array $especes, \DateTimeImmutable $reference): ?array
    {
        foreach ($especes as $e) {
            if ($e === null) {
                continue;
            }
            $d = Especes::datesIndicatives($e, $reference);
            $mise = $d['repiquage'] ?? $d['semis'] ?? null;
            if ($mise !== null) {
                return ['mise' => $mise, 'fin' => $d['recolte'] ?? null, 'espece' => (string) ($e['nom_usuel'] ?: $e['nom_scientifique'])];
            }
        }
        return null;
    }
}
