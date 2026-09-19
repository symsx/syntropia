<?php

declare(strict_types=1);

namespace Syntropia\Web;

use PDO;
use Syntropia\BesoinEau;
use Syntropia\Database;

/**
 * Les relevés de sol d'une parcelle (`releve_sol`) — ici, ceux qui servent à
 * régler l'arrosage : l'infiltration, l'état d'humidité et les jours écoulés
 * depuis la dernière pluie (la fiche 02, voir `FicheTerrain`).
 *
 * Les autres colonnes de la table (densité, slake, vers…) ne sont ni lues ni
 * écrites ici : elles appartiennent à l'étude du sol. Le garde-fou d'appartenance
 * d'une parcelle à un site se fait à l'appel, comme pour `Parcelles::scenario()`.
 */
final class ReleveSol
{
    public function __construct(private ?PDO $pdo = null) {}

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::syntropia();
    }

    /**
     * Les relevés d'arrosage d'une parcelle, le plus récent d'abord. Le témoin ne
     * compte pas : il dit ce que devient un sol laissé tel quel, pas celui qu'on arrose.
     *
     * @return list<array<string, mixed>>
     */
    public function recents(int $parcelleId, int $limite = 5): array
    {
        $req = $this->pdo()->prepare(
            'SELECT id, `date`, point, infiltration_mm_h, etat_humidite, jours_depuis_pluie, notes
               FROM releve_sol
              WHERE parcelle_id = ? AND temoin = 0
                AND (infiltration_mm_h IS NOT NULL OR etat_humidite IS NOT NULL)
              ORDER BY `date` DESC, id DESC LIMIT ' . max(1, $limite));
        $req->execute([$parcelleId]);
        return $req->fetchAll();
    }

    /**
     * Ajoute un relevé. Il faut au moins une infiltration ou un état d'humidité :
     * un relevé vide ne dit rien de l'arrosage.
     *
     * @param array<string, mixed> $v la saisie brute
     * @return array<string, string> les erreurs, vide si le relevé est écrit
     */
    public function ajouter(int $parcelleId, int $participantId, array $v): array
    {
        $erreurs = [];

        $date = trim((string) ($v['date'] ?? ''));
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if ($d === false || $d->format('Y-m-d') !== $date) {
            $erreurs['date'] = 'La date du relevé doit être une date valide.';
        } elseif ($d > new \DateTimeImmutable('today')) {
            $erreurs['date'] = 'Le relevé ne peut pas être daté du futur.';
        }

        $point = trim((string) ($v['point'] ?? ''));
        if ($point === '') {
            $point = 'Point 1';
        } elseif (mb_strlen($point) > 60) {
            $erreurs['point'] = 'Le nom du point est trop long (60 caractères au plus).';
        }

        $infiltration = null;
        $brut = str_replace(',', '.', trim((string) ($v['infiltration_mm_h'] ?? '')));
        if ($brut !== '') {
            if (!is_numeric($brut) || (float) $brut < 0.1 || (float) $brut > 2000) {
                $erreurs['infiltration_mm_h'] = 'L’infiltration se compte en mm/h, entre 0,1 et 2 000.';
            } else {
                $infiltration = round((float) $brut, 2);
            }
        }

        $etat = trim((string) ($v['etat_humidite'] ?? ''));
        if ($etat !== '' && !in_array($etat, BesoinEau::ETATS, true)) {
            $erreurs['etat_humidite'] = 'Choisissez un état proposé.';
        }

        $jours = null;
        $brut = trim((string) ($v['jours_depuis_pluie'] ?? ''));
        if ($brut !== '') {
            $jours = filter_var($brut, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 60]]);
            if ($jours === false) {
                $erreurs['jours_depuis_pluie'] = 'Les jours depuis la dernière pluie : un nombre entier, de 0 à 60.';
                $jours = null;
            }
        }

        if ($infiltration === null && $etat === '' && !isset($erreurs['infiltration_mm_h']) && !isset($erreurs['etat_humidite'])) {
            $erreurs['releve'] = 'Notez au moins une infiltration ou l’état du sol — sinon le relevé ne dit rien de l’arrosage.';
        }
        if ($erreurs !== []) {
            return $erreurs;
        }

        $this->pdo()->prepare(
            'INSERT INTO releve_sol (parcelle_id, `date`, point, temoin, etat_humidite, jours_depuis_pluie, infiltration_mm_h, saisi_par)
             VALUES (?, ?, ?, 0, ?, ?, ?, ?)')
            ->execute([$parcelleId, $date, $point, $etat !== '' ? $etat : null, $jours, $infiltration, $participantId]);
        return [];
    }

    /** Retire un relevé — de cette parcelle seulement. */
    public function retirer(int $id, int $parcelleId): bool
    {
        $req = $this->pdo()->prepare('DELETE FROM releve_sol WHERE id = ? AND parcelle_id = ?');
        $req->execute([$id, $parcelleId]);
        return $req->rowCount() > 0;
    }
}
