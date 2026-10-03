<?php

declare(strict_types=1);

namespace Syntropia\Web;

use PDO;
use Syntropia\Database;
use Syntropia\Perturbation;

/**
 * Le journal des perturbations d'une parcelle (`intervention`).
 *
 * Une ligne par geste : une coupe, une taille, une fauche, un affaiblissement. Rien ici ne
 * refuse un geste : le journal note ce qui s'est fait, dans quel état étaient les plantes et
 * quelle eau il y avait (voir `Perturbation`). Le garde-fou d'appartenance d'une parcelle à
 * un site se fait à l'appel, comme pour `ReleveSol` ; chaque requête est en plus bornée à la
 * parcelle.
 *
 * `saisi_par` désigne un compte (la base `comptes`), comme le dit le schéma — jamais un
 * participant.
 */
final class Interventions
{
    /** Ce que la page charge : assez pour plusieurs saisons de plusieurs plantes. */
    public const LIMITE = 300;

    public function __construct(private ?PDO $pdo = null) {}

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::syntropia();
    }

    /**
     * Les interventions d'une parcelle, la plus récente d'abord.
     *
     * @return list<array<string, mixed>>
     */
    public function dUneParcelle(int $parcelleId): array
    {
        $req = $this->pdo()->prepare(
            'SELECT * FROM intervention WHERE parcelle_id = ? ORDER BY `date` DESC, id DESC LIMIT ' . self::LIMITE);
        $req->execute([$parcelleId]);
        return $req->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function obtenir(int $id, int $parcelleId): ?array
    {
        $req = $this->pdo()->prepare('SELECT * FROM intervention WHERE id = ? AND parcelle_id = ?');
        $req->execute([$id, $parcelleId]);
        return $req->fetch() ?: null;
    }

    /** Le jeton du verrou : un hash de la ligne entière, comme `Scenario::version()`. */
    public static function version(array $ligne): string
    {
        return hash('sha256', json_encode($ligne, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /**
     * Valide une saisie. Seuls la date et le geste sont exigés : le reste se note quand on le sait
     * — le résultat, par exemple, ne se connaît que plus tard.
     *
     * @param array<string, mixed> $v la saisie brute
     * @return array{valeurs: array<string, mixed>, erreurs: array<string, string>}
     */
    public static function valider(array $v): array
    {
        $erreurs = [];

        $date = trim((string) ($v['date'] ?? ''));
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if ($d === false || $d->format('Y-m-d') !== $date) {
            $erreurs['date'] = 'La date doit être une date valide.';
        } elseif ($d > new \DateTimeImmutable('today')) {
            $erreurs['date'] = 'Une intervention ne peut pas être datée du futur : notez-la quand elle est faite.';
        }

        $type = trim((string) ($v['type'] ?? ''));
        if (!isset(Perturbation::TYPES[$type])) {
            $erreurs['type'] = 'Choisissez le geste : une coupe, une taille, une fauche…';
        }

        $choix = static function (string $cle, array $liste, string $message) use ($v, &$erreurs): ?string {
            $valeur = trim((string) ($v[$cle] ?? ''));
            if ($valeur === '') {
                return null;
            }
            if (!isset($liste[$valeur])) {
                $erreurs[$cle] = $message;
                return null;
            }
            return $valeur;
        };
        $stade = $choix('stade_phenologique', Perturbation::STADES, 'Choisissez un état proposé, ou laissez vide.');
        $eau = $choix('conditions_eau', Perturbation::EAUX, 'Choisissez une situation proposée pour l’eau, ou laissez vide.');
        $pluies = $choix('pluies_a_venir', Perturbation::PLUIES, 'Choisissez une réponse proposée pour les pluies, ou laissez vide.');
        $biomasse = $choix('biomasse_deposee', Perturbation::BIOMASSES, 'Choisissez ce qu’il est advenu de la biomasse, ou laissez vide.');

        // Les végétaux : des cases (les lignes du scénario) et un mot libre pour ce qui n'y est pas.
        $noms = $v['especes'] ?? [];
        $noms = is_array($noms) ? array_map('strval', $noms) : [];
        $noms[] = (string) ($v['autre_vegetal'] ?? '');
        $especes = Perturbation::joindre($noms);
        if (mb_strlen($especes) > 255) {
            $erreurs['especes'] = 'Trop de végétaux cités (255 caractères au plus) : regroupez-les dans la zone ou les notes.';
        }

        $texte = static function (string $cle, int $max, string $nom) use ($v, &$erreurs): ?string {
            $valeur = trim((string) ($v[$cle] ?? ''));
            if ($valeur === '') {
                return null;
            }
            if (mb_strlen($valeur) > $max) {
                $erreurs[$cle] = $nom . ' compte ' . $max . ' caractères au plus.';
                return null;
            }
            return $valeur;
        };
        $zone = $texte('zone', 120, 'La zone');
        $outil = $texte('outil', 120, 'L’outil');
        $motif = $texte('motif', 2000, 'Le motif');
        $resultat = $texte('resultat_constate', 2000, 'Ce qui est constaté');
        $notes = $texte('notes', 2000, 'Les notes');

        $nombre = static function (string $cle, float $min, float $max, string $message) use ($v, &$erreurs): ?float {
            $brut = str_replace(',', '.', trim((string) ($v[$cle] ?? '')));
            if ($brut === '') {
                return null;
            }
            if (!is_numeric($brut) || (float) $brut < $min || (float) $brut > $max) {
                $erreurs[$cle] = $message;
                return null;
            }
            return round((float) $brut, 2);
        };
        $duree = $nombre('duree_h', 0.05, 99, 'La durée se compte en heures, de 0,05 à 99.');
        $personnes = $nombre('personnes', 1, 99, 'Le nombre de personnes est un entier de 1 à 99.');
        if ($personnes !== null && $personnes !== floor($personnes)) {
            $erreurs['personnes'] = 'Le nombre de personnes est un entier de 1 à 99.';
        }
        $quantite = $nombre('quantite_traitee', 0.01, 999999, 'La quantité traitée est un nombre positif.');
        $unite = trim((string) ($v['unite_quantite'] ?? ''));
        if ($quantite !== null && !in_array($unite, ['m', 'm2'], true)) {
            $erreurs['unite_quantite'] = 'Indiquez si la quantité se compte en mètres de ligne ou en m².';
        }
        if ($quantite === null) {
            $unite = '';
        }

        return ['valeurs' => [
            'date' => $date,
            'type' => $type,
            'zone' => $zone,
            'especes' => $especes !== '' ? $especes : null,
            'stade_phenologique' => $stade,
            'conditions_eau' => $eau,
            'pluies_a_venir' => $pluies,
            'outil' => $outil,
            'premiere_fois' => !empty($v['premiere_fois']) ? 1 : 0,
            'duree_h' => $duree,
            'personnes' => $personnes !== null ? (int) $personnes : null,
            'quantite_traitee' => $quantite,
            'unite_quantite' => $quantite !== null && $unite !== '' ? $unite : null,
            'biomasse_deposee' => $biomasse,
            'motif' => $motif,
            'resultat_constate' => $resultat,
            'notes' => $notes,
        ], 'erreurs' => $erreurs];
    }

    /**
     * Ajoute une intervention.
     *
     * @param array<string, mixed> $v la saisie brute
     * @return array<string, string> les erreurs, vide si elle est écrite
     */
    public function ajouter(int $parcelleId, int $compteId, array $v): array
    {
        ['valeurs' => $val, 'erreurs' => $erreurs] = self::valider($v);
        if ($erreurs !== []) {
            return $erreurs;
        }
        $cols = array_keys($val);
        $this->pdo()->prepare(
            'INSERT INTO intervention (parcelle_id, saisi_par, `' . implode('`, `', $cols) . '`)
             VALUES (?, ?, ' . implode(', ', array_fill(0, count($cols), '?')) . ')')
            ->execute([$parcelleId, $compteId > 0 ? $compteId : null, ...array_values($val)]);
        return [];
    }

    /**
     * Modifie une intervention de cette parcelle, sous verrou : si elle a changé depuis que la
     * personne l'a ouverte (un autre membre, un autre onglet), rien n'est écrasé.
     *
     * @param array<string, mixed> $v la saisie brute
     * @return array<string, string> les erreurs, vide si elle est écrite
     */
    public function modifier(int $id, int $parcelleId, string $version, array $v): array
    {
        ['valeurs' => $val, 'erreurs' => $erreurs] = self::valider($v);
        if ($erreurs !== []) {
            return $erreurs;
        }
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $req = $pdo->prepare('SELECT * FROM intervention WHERE id = ? AND parcelle_id = ? FOR UPDATE');
            $req->execute([$id, $parcelleId]);
            $ligne = $req->fetch();
            if (!$ligne) {
                $pdo->rollBack();
                return ['intervention' => 'Cette intervention n’existe plus dans ce journal.'];
            }
            if (!hash_equals(self::version($ligne), $version)) {
                $pdo->rollBack();
                return ['version' => 'Cette intervention a été modifiée entre-temps : rechargez-la avant de la corriger, pour ne rien écraser.'];
            }
            $pdo->prepare(
                'UPDATE intervention SET ' . implode(', ', array_map(static fn (string $c): string => '`' . $c . '` = ?', array_keys($val)))
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

    /** Retire une intervention — de cette parcelle seulement. */
    public function retirer(int $id, int $parcelleId): bool
    {
        $req = $this->pdo()->prepare('DELETE FROM intervention WHERE id = ? AND parcelle_id = ?');
        $req->execute([$id, $parcelleId]);
        return $req->rowCount() > 0;
    }
}
