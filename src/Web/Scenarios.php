<?php

declare(strict_types=1);

namespace Syntropia\Web;

use PDO;
use RuntimeException;
use Syntropia\Database;
use Syntropia\Scenario;

/**
 * Écrire le scénario d'une parcelle : ses végétaux. Ses productions viennent de sa palette depuis le 2026-10-07
 * (`chantier-palette-vegetale.md`, Phase 4) ; `souhaitsDuSite()` sert à la palette, qui rappelle les Objectifs.
 *
 * Port réduit de `Scenarios` du dépôt de référence, chantier
 * `chantier-scenario-plantation.md`, Phase 3. **Réduit** : rien ne touche
 * `parcelle.plan` (les affectations de bordures — `ImplantationBordures` —
 * restent hors périmètre), donc ni `bordures()` ni les garde-fous qui s'y
 * rattachent.
 *
 * Toute méthode d'écriture lève `RuntimeException` avec **un message à montrer
 * tel quel** (droit refusé, version périmée…) : la page en fait une erreur, pas
 * une page blanche. Les erreurs de base, elles, remontent en `PDOException`.
 * Chaque écriture relit sa ligne **sous verrou** (`FOR UPDATE`) et compare son
 * jeton de version — une modification concurrente est refusée, jamais écrasée.
 */
final class Scenarios
{
    public function __construct(private ?PDO $pdo = null) {}

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::syntropia();
    }

    /**
     * Les productions que ce participant peut retenir pour une parcelle, tirées
     * de sa réponse à « Quelles productions ? » (`definition-objectifs.php`).
     * À défaut de réponse, celle du porteur du projet : un contributeur qui n'a
     * pas encore répondu ne trouve pas un choix vide.
     *
     * Une seule liste, non « principales / secondaires » comme dans le rêve de
     * la référence : la distinction se fait ici, parcelle par parcelle.
     *
     * @return array{souhaits: list<string>, de: ?string} `de` : `soi`, `porteur` ou `null`
     */
    public function souhaitsDuSite(int $siteId, int $participantId): array
    {
        $req = $this->pdo()->prepare(
            "SELECT texte FROM reponse
              WHERE site_id = ? AND participant_id = ? AND question = 'objectifs_productions'");
        $req->execute([$siteId, $participantId]);
        $texte = $req->fetchColumn();
        if (is_string($texte) && Scenario::souhaits($texte) !== []) {
            return ['souhaits' => Scenario::souhaits($texte), 'de' => 'soi'];
        }
        $req = $this->pdo()->prepare(
            "SELECT r.texte FROM reponse r
               JOIN participant p ON p.id = r.participant_id
               JOIN membre_site m ON m.site_id = p.site_id AND m.compte_id = p.compte_id
              WHERE r.site_id = ? AND r.question = 'objectifs_productions' AND m.role = 'porteur'
              ORDER BY p.id LIMIT 1");
        $req->execute([$siteId]);
        $texte = $req->fetchColumn();
        return is_string($texte) && Scenario::souhaits($texte) !== []
            ? ['souhaits' => Scenario::souhaits($texte), 'de' => 'porteur']
            : ['souhaits' => [], 'de' => null];
    }

    /**
     * Ajoute, modifie ou retire un végétal du scénario.
     *
     * - `$id === 0` et `$ligne` : ajout ; `$id > 0` et `$ligne` : modification ;
     *   `$id > 0` et `$ligne === null` : retrait.
     * - Modifier sous **un autre nom** demande `$confirmerRenommage` : sans
     *   cela, réutiliser le formulaire d'un végétal pour en saisir un autre
     *   écraserait le premier sans que personne l'ait voulu.
     * - `details` (la production choisie, les fonctions, les repères fins) est
     *   fusionné avec l'existant plutôt que remplacé.
     *
     * @param array<string, mixed>|null $ligne la ligne validée (`Scenario::valider()`)
     * @return int l'identifiant du végétal
     * @throws RuntimeException
     */
    public function enregistrer(int $parcelleId, int $compteId, int $id, string $version,
                                ?array $ligne, bool $confirmerRenommage = false): int
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $req = $pdo->prepare('SELECT site_id FROM parcelle WHERE id = ? FOR UPDATE');
            $req->execute([$parcelleId]);
            $siteId = $req->fetchColumn();
            if ($siteId === false || !(new Sites($pdo))->peutEcrireSur((int) $siteId, $compteId)) {
                throw new RuntimeException('Vous ne pouvez pas modifier cette parcelle.');
            }

            $ancienne = null;
            if ($id > 0) {
                $req = $pdo->prepare('SELECT * FROM scenario_ligne WHERE id = ? AND parcelle_id = ? FOR UPDATE');
                $req->execute([$id, $parcelleId]);
                $ancienne = $req->fetch();
                if (!$ancienne || !hash_equals(Scenario::version($ancienne), $version)) {
                    throw new RuntimeException('Ce végétal a changé depuis l’ouverture du formulaire. '
                        . 'Rechargez la page avant de le modifier.');
                }
            }
            if ($id > 0 && $ligne !== null && !$confirmerRenommage
                && mb_strtolower(trim((string) $ligne['espece'])) !== mb_strtolower(trim((string) $ancienne['espece']))) {
                throw new RuntimeException('Vous modifiez « ' . $ancienne['espece'] . ' ». Pour conserver ce végétal '
                    . 'et en créer un autre, choisissez « Ajouter comme nouveau végétal ». Pour corriger son nom, '
                    . 'cochez la confirmation de changement de nom.');
            }
            if ($ligne !== null && $ancienne) {
                $ligne['details'] = json_encode(
                    array_replace(Scenario::details($ancienne), Scenario::details($ligne)),
                    JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            }

            if ($ligne === null) {
                if ($id <= 0) {
                    throw new RuntimeException('Choisissez le végétal à retirer du tableau.');
                }
                $pdo->prepare('DELETE FROM scenario_ligne WHERE id = ? AND parcelle_id = ?')
                    ->execute([$id, $parcelleId]);
            } elseif ($id > 0) {
                $colonnes = array_keys($ligne);
                $pdo->prepare('UPDATE scenario_ligne SET '
                    . implode(', ', array_map(static fn ($c) => "`$c` = ?", $colonnes))
                    . ' WHERE id = ? AND parcelle_id = ?')
                    ->execute([...array_values($ligne), $id, $parcelleId]);
            } else {
                $colonnes = array_keys($ligne);
                $pdo->prepare('INSERT INTO scenario_ligne (`parcelle_id`, `' . implode('`, `', $colonnes) . '`) VALUES ('
                    . implode(', ', array_fill(0, count($colonnes) + 1, '?')) . ')')
                    ->execute([$parcelleId, ...array_values($ligne)]);
                $id = (int) $pdo->lastInsertId();
            }
            $pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
