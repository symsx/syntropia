<?php

declare(strict_types=1);

namespace Syntropia\Web;

use PDO;
use Syntropia\Database;
use Syntropia\SautDuChat;

/**
 * Les constats du saut du chat d'une parcelle (`constat`).
 *
 * Un constat est daté et dit si le saut du chat est visible ; quand on l'a mesuré, il donne la
 * pousse d'une plante sur une durée, à côté de celle du témoin. Une pousse sans durée ne dit rien, un
 * témoin sans pousse ne compare rien : la validation l'exige, et n'exige rien d'autre.
 *
 * Même conduite que `Interventions` : le garde-fou d'appartenance d'une parcelle à un site se fait à
 * l'appel, chaque requête est en plus bornée à la parcelle, la modification se fait sous verrou de
 * version, et `saisi_par` désigne un compte.
 */
final class Constats
{
    public const LIMITE = 200;

    public function __construct(private ?PDO $pdo = null) {}

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::syntropia();
    }

    /** @return list<array<string, mixed>> le plus récent d'abord */
    public function dUneParcelle(int $parcelleId): array
    {
        $req = $this->pdo()->prepare(
            'SELECT * FROM constat WHERE parcelle_id = ? ORDER BY `date` DESC, id DESC LIMIT ' . self::LIMITE);
        $req->execute([$parcelleId]);
        return $req->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function obtenir(int $id, int $parcelleId): ?array
    {
        $req = $this->pdo()->prepare('SELECT * FROM constat WHERE id = ? AND parcelle_id = ?');
        $req->execute([$id, $parcelleId]);
        return $req->fetch() ?: null;
    }

    public static function version(array $ligne): string
    {
        return hash('sha256', json_encode($ligne, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /**
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
            $erreurs['date'] = 'Un constat ne peut pas être daté du futur : notez-le quand vous l’avez fait.';
        }

        $visible = trim((string) ($v['visible'] ?? ''));
        if (!isset(SautDuChat::VISIBLES[$visible])) {
            $erreurs['visible'] = 'Dites si le saut du chat est visible, ou pas encore.';
        }

        $texte = static function (string $cle, int $max, string $nom) use ($v, &$erreurs): ?string {
            $valeur = trim((string) ($v[$cle] ?? ''));
            if ($valeur === '') {
                return null;
            }
            if (mb_strlen($valeur) > $max) {
                $erreurs[$cle] = $nom . ' : ' . $max . ' caractères au plus.';
                return null;
            }
            return $valeur;
        };
        $vegetal = $texte('vegetal', 160, 'La plante observée');
        $temoinDecrit = $texte('temoin_decrit', 190, 'La description du témoin');
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
            return round((float) $brut, 1);
        };
        $pousse = $nombre('pousse_cm', 0, 2000, 'La pousse se compte en centimètres, de 0 à 2 000.');
        $temoinPousse = $nombre('temoin_pousse_cm', 0, 2000, 'La pousse du témoin se compte en centimètres, de 0 à 2 000.');
        $jours = null;
        $brut = trim((string) ($v['sur_jours'] ?? ''));
        if ($brut !== '') {
            $jours = filter_var($brut, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 730]]);
            if ($jours === false) {
                $erreurs['sur_jours'] = 'La durée se compte en jours entiers, de 1 à 730.';
                $jours = null;
            }
        }

        // Une pousse sans durée ne dit rien ; un témoin sans pousse ne compare rien.
        if (($pousse !== null || $temoinPousse !== null) && $jours === null && !isset($erreurs['sur_jours'])) {
            $erreurs['sur_jours'] = 'Une pousse se mesure sur une durée : dites sur combien de jours.';
        }
        if ($temoinPousse !== null && $pousse === null && !isset($erreurs['pousse_cm'])) {
            $erreurs['pousse_cm'] = 'Le témoin sert à comparer : notez aussi la pousse de la plante.';
        }

        return ['valeurs' => [
            'date' => $date,
            'visible' => $visible,
            'vegetal' => $vegetal,
            'pousse_cm' => $pousse,
            'sur_jours' => $jours,
            'temoin_decrit' => $temoinDecrit,
            'temoin_pousse_cm' => $temoinPousse,
            'notes' => $notes,
        ], 'erreurs' => $erreurs];
    }

    /** @return array<string, string> les erreurs, vide si le constat est écrit */
    public function ajouter(int $parcelleId, int $compteId, array $v): array
    {
        ['valeurs' => $val, 'erreurs' => $erreurs] = self::valider($v);
        if ($erreurs !== []) {
            return $erreurs;
        }
        $cols = array_keys($val);
        $this->pdo()->prepare(
            'INSERT INTO constat (parcelle_id, saisi_par, `' . implode('`, `', $cols) . '`)
             VALUES (?, ?, ' . implode(', ', array_fill(0, count($cols), '?')) . ')')
            ->execute([$parcelleId, $compteId > 0 ? $compteId : null, ...array_values($val)]);
        return [];
    }

    /** @return array<string, string> les erreurs, vide si le constat est écrit */
    public function modifier(int $id, int $parcelleId, string $version, array $v): array
    {
        ['valeurs' => $val, 'erreurs' => $erreurs] = self::valider($v);
        if ($erreurs !== []) {
            return $erreurs;
        }
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $req = $pdo->prepare('SELECT * FROM constat WHERE id = ? AND parcelle_id = ? FOR UPDATE');
            $req->execute([$id, $parcelleId]);
            $ligne = $req->fetch();
            if (!$ligne) {
                $pdo->rollBack();
                return ['constat' => 'Ce constat n’existe plus.'];
            }
            if (!hash_equals(self::version($ligne), $version)) {
                $pdo->rollBack();
                return ['version' => 'Ce constat a été modifié entre-temps : rechargez-le avant de le corriger, pour ne rien écraser.'];
            }
            $pdo->prepare(
                'UPDATE constat SET ' . implode(', ', array_map(static fn (string $c): string => '`' . $c . '` = ?', array_keys($val)))
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

    public function retirer(int $id, int $parcelleId): bool
    {
        $req = $this->pdo()->prepare('DELETE FROM constat WHERE id = ? AND parcelle_id = ?');
        $req->execute([$id, $parcelleId]);
        return $req->rowCount() > 0;
    }
}
