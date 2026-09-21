<?php

declare(strict_types=1);

namespace Syntropia\Web;

use PDO;
use Syntropia\Database;

/**
 * Les cultures d'une parcelle de type « potager » (`succession_ligne`) : ce qui est semé ou repiqué, sur quelle
 * planche, à quelles dates.
 *
 * Voir `chantier-plan-de-culture.md`, Phase 1. Le placement se fait **par planche** (le porteur, 2026-09-20) : la
 * planche est un numéro du plan de la parcelle, de 1 à `nb_planches`. Une planche porte plusieurs cultures — c'est
 * le principe même de l'association.
 *
 * Même conduite que `Interventions` et `Constats` : le garde-fou d'appartenance d'une parcelle à un site se fait à
 * l'appel, chaque requête est bornée à la parcelle, la modification se fait sous verrou de version. Ne touche que les
 * colonnes qu'il écrit ; les autres de `succession_ligne` (code, rôle, couleur, emplacement, perturbation…) attendent
 * les phases suivantes.
 */
final class Cultures
{
    public const LIMITE = 300;

    /** Les quatre strates d'une succession de potager (ch. 5 et 8 : quatre, non davantage). */
    public const STRATES = [
        'emergente' => 'Émergente',
        'haute' => 'Haute',
        'moyenne' => 'Moyenne',
        'basse' => 'Basse',
    ];

    public function __construct(private ?PDO $pdo = null) {}

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::syntropia();
    }

    /** @return list<array<string, mixed>> par planche, puis dans l'ordre d'ajout ; les non placées à la fin */
    public function dUneParcelle(int $parcelleId): array
    {
        $req = $this->pdo()->prepare(
            'SELECT * FROM succession_ligne WHERE parcelle_id = ?
              ORDER BY (planche IS NULL), planche, id LIMIT ' . self::LIMITE);
        $req->execute([$parcelleId]);
        return $req->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function obtenir(int $id, int $parcelleId): ?array
    {
        $req = $this->pdo()->prepare('SELECT * FROM succession_ligne WHERE id = ? AND parcelle_id = ?');
        $req->execute([$id, $parcelleId]);
        return $req->fetch() ?: null;
    }

    public static function version(array $ligne): string
    {
        return hash('sha256', json_encode($ligne, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<string, mixed> $v la saisie brute
     * @param int $nbPlanches les planches du plan de la parcelle (0 : pas de planche, rien ne se place)
     * @return array{valeurs: array<string, mixed>, erreurs: array<string, string>}
     */
    public static function valider(array $v, int $nbPlanches): array
    {
        $erreurs = [];

        $espece = trim((string) ($v['espece'] ?? ''));
        if ($espece === '') {
            $erreurs['espece'] = 'Dites quelle culture : un nom suffit.';
        } elseif (mb_strlen($espece) > 160) {
            $erreurs['espece'] = 'Le nom est trop long (160 caractères au plus).';
        }

        $planche = null;
        $brut = trim((string) ($v['planche'] ?? ''));
        if ($brut !== '') {
            $n = filter_var($brut, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($n === false || $nbPlanches < 1) {
                $erreurs['planche'] = $nbPlanches < 1
                    ? 'Le plan de cette parcelle n’a pas de planche : ouvrez le plan pour en définir, ou laissez la culture non placée.'
                    : 'La planche est un numéro du plan, de 1 à ' . $nbPlanches . '.';
            } elseif ($n > $nbPlanches) {
                $erreurs['planche'] = 'Le plan compte ' . $nbPlanches . ' planche' . ($nbPlanches > 1 ? 's' : '') . ' : choisissez de 1 à ' . $nbPlanches . '.';
            } else {
                $planche = $n;
            }
        }

        $strate = trim((string) ($v['strate'] ?? ''));
        if ($strate !== '' && !isset(self::STRATES[$strate])) {
            $erreurs['strate'] = 'Choisissez une strate proposée, ou laissez vide.';
        }

        $espacement = null;
        $brut = str_replace(',', '.', trim((string) ($v['espacement_m'] ?? '')));
        if ($brut !== '') {
            if (!is_numeric($brut) || (float) $brut < 0.01 || (float) $brut > 20) {
                $erreurs['espacement_m'] = 'L’espacement se compte en mètres, de 0,01 à 20.';
            } else {
                $espacement = round((float) $brut, 2);
            }
        }

        $nbPlants = null;
        $brut = trim((string) ($v['nb_plants'] ?? ''));
        if ($brut !== '') {
            $n = filter_var($brut, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100000]]);
            if ($n === false) {
                $erreurs['nb_plants'] = 'Le nombre de plants est un entier, de 1 à 100 000.';
            } else {
                $nbPlants = $n;
            }
        }

        // Des dates PLANIFIÉES : un plan de culture se prépare avant de semer, elles peuvent être à venir.
        $date = static function (string $cle, string $nom) use ($v, &$erreurs): ?string {
            $valeur = trim((string) ($v[$cle] ?? ''));
            if ($valeur === '') {
                return null;
            }
            $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $valeur);
            if ($d === false || $d->format('Y-m-d') !== $valeur || $d < new \DateTimeImmutable('2000-01-01') || $d > new \DateTimeImmutable('2100-12-31')) {
                $erreurs[$cle] = $nom . ' doit être une date valide.';
                return null;
            }
            return $valeur;
        };
        $semisAbri = $date('date_semis', 'La date de semis à l’abri');
        $semis = $date('date_semis_repiquage', 'La date de mise en place');
        $recolte = $date('date_recolte', 'La date de récolte');
        if ($semisAbri !== null && $semis !== null && $semis < $semisAbri) {
            $erreurs['date_semis_repiquage'] = 'La mise en place ne peut pas précéder le semis à l’abri.';
        }
        // La récolte ne précède ni la mise en place, ni — à défaut — le semis.
        $depart = $semis ?? $semisAbri;
        if ($depart !== null && $recolte !== null && $recolte < $depart) {
            $erreurs['date_recolte'] = 'La récolte ne peut pas précéder le semis ni la mise en place.';
        }

        // Une plante de biomasse : celle qu'on taille (le maïs, le tournesol de la tranchée centrale). Une case, un seul rôle.
        $biomasse = in_array((string) ($v['biomasse'] ?? ''), ['1', 'on'], true);

        $notes = trim((string) ($v['notes'] ?? ''));
        if (mb_strlen($notes) > 2000) {
            $erreurs['notes'] = 'Les notes : 2000 caractères au plus.';
        }

        return ['valeurs' => [
            'espece' => $espece,
            'planche' => $planche,
            'role' => $biomasse ? \Syntropia\SautDuChat::ROLE_BIOMASSE : null,
            'strate' => $strate !== '' && isset(self::STRATES[$strate]) ? $strate : null,
            'espacement_m' => $espacement,
            'nb_plants' => $nbPlants,
            'date_semis' => $semisAbri,
            'date_semis_repiquage' => $semis,
            'date_recolte' => $recolte,
            'notes' => $notes !== '' ? $notes : null,
        ], 'erreurs' => $erreurs];
    }

    /** @return array<string, string> les erreurs, vide si la culture est écrite */
    public function ajouter(int $parcelleId, int $nbPlanches, array $v): array
    {
        ['valeurs' => $val, 'erreurs' => $erreurs] = self::valider($v, $nbPlanches);
        if ($erreurs !== []) {
            return $erreurs;
        }
        $cols = array_keys($val);
        $this->pdo()->prepare(
            'INSERT INTO succession_ligne (parcelle_id, `' . implode('`, `', $cols) . '`)
             VALUES (?, ' . implode(', ', array_fill(0, count($cols), '?')) . ')')
            ->execute([$parcelleId, ...array_values($val)]);
        return [];
    }

    /** @return array<string, string> les erreurs, vide si la culture est écrite */
    public function modifier(int $id, int $parcelleId, int $nbPlanches, string $version, array $v): array
    {
        ['valeurs' => $val, 'erreurs' => $erreurs] = self::valider($v, $nbPlanches);
        if ($erreurs !== []) {
            return $erreurs;
        }
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $req = $pdo->prepare('SELECT * FROM succession_ligne WHERE id = ? AND parcelle_id = ? FOR UPDATE');
            $req->execute([$id, $parcelleId]);
            $ligne = $req->fetch();
            if (!$ligne) {
                $pdo->rollBack();
                return ['culture' => 'Cette culture n’existe plus.'];
            }
            if (!hash_equals(self::version($ligne), $version)) {
                $pdo->rollBack();
                return ['version' => 'Cette culture a été modifiée entre-temps : rechargez-la avant de la corriger, pour ne rien écraser.'];
            }
            $pdo->prepare(
                'UPDATE succession_ligne SET ' . implode(', ', array_map(static fn (string $c): string => '`' . $c . '` = ?', array_keys($val)))
                . ' WHERE id = ? AND parcelle_id = ?')
                ->execute([...array_values($val), $id, $parcelleId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        return [];
    }

    /**
     * Place des cultures sur des planches — ce que la personne a accepté d'une proposition. **Ne touche que les cultures
     * encore non placées** (`planche IS NULL`) : ce qui est déjà placé ne bouge jamais, même si l'état a changé entre-temps.
     * Bornée à la parcelle ; une planche hors du plan est ignorée.
     *
     * @param array<int, int> $affectations identifiant de culture → numéro de planche
     * @return int le nombre de cultures placées
     */
    public function placer(int $parcelleId, int $nbPlanches, array $affectations): int
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $req = $pdo->prepare('UPDATE succession_ligne SET planche = ? WHERE id = ? AND parcelle_id = ? AND planche IS NULL');
            $n = 0;
            foreach ($affectations as $id => $planche) {
                if ($planche >= 1 && $planche <= $nbPlanches) {
                    $req->execute([$planche, $id, $parcelleId]);
                    $n += $req->rowCount();
                }
            }
            $pdo->commit();
            return $n;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public function retirer(int $id, int $parcelleId): bool
    {
        $req = $this->pdo()->prepare('DELETE FROM succession_ligne WHERE id = ? AND parcelle_id = ?');
        $req->execute([$id, $parcelleId]);
        return $req->rowCount() > 0;
    }
}
