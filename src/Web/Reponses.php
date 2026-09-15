<?php

declare(strict_types=1);

namespace Syntropia\Web;

use PDO;
use Syntropia\Database;

/**
 * Les réponses aux questions guidées — le patrimoine documentaire du projet.
 *
 * Inspiré de la partie « réponses » d'`application/src/Web/Reves.php`
 * (dépôt de référence), réduit à lire et écrire : pas de synthèse
 * commune, pas de code d'invitation, pas de second participant — voir
 * `Web/Sites.php` pour ce qui a été volontairement laissé de côté et
 * pourquoi.
 *
 * `question` est le nom du champ tel qu'il apparaît dans le formulaire
 * de l'étape (`productions`, `motivation`, `commune`…) : les questions
 * vivent dans la page qui les pose, pas dans une classe séparée — il
 * n'y a pas encore, contrairement au dépôt de référence, plusieurs
 * registres de questions ni d'anciennes clés à faire correspondre.
 */
final class Reponses
{
    public function __construct(private ?PDO $pdo = null) {}

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::syntropia();
    }

    /**
     * Les réponses déjà données par ce participant, question → texte.
     *
     * @return array<string, string>
     */
    public function pourParticipant(int $participantId): array
    {
        $req = $this->pdo()->prepare(
            'SELECT question, texte FROM reponse WHERE participant_id = ?');
        $req->execute([$participantId]);
        $lignes = $req->fetchAll(PDO::FETCH_KEY_PAIR);
        return is_array($lignes) ? $lignes : [];
    }

    /**
     * Enregistre une réponse — ou l'efface si le texte est vide.
     *
     * **Un champ vidé n'est pas la même chose qu'un champ jamais
     * rempli** : le premier ne laisse pas de ligne fantôme à `''`, le
     * second n'en a simplement jamais eu.
     */
    public function enregistrer(int $siteId, int $participantId, string $phase,
                                string $question, string $texte): void
    {
        $texte = trim($texte);
        if ($texte === '') {
            $this->pdo()->prepare(
                'DELETE FROM reponse WHERE participant_id = ? AND question = ?')
                ->execute([$participantId, $question]);
            return;
        }
        $this->pdo()->prepare(
            'INSERT INTO reponse (site_id, participant_id, phase, question, texte)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE texte = VALUES(texte), phase = VALUES(phase)')
            ->execute([$siteId, $participantId, $phase, $question, $texte]);
    }

    /**
     * Enregistre plusieurs réponses d'un coup — un `$_POST` de formulaire.
     *
     * @param array<string, string> $reponses question => texte
     */
    public function enregistrerLot(int $siteId, int $participantId, string $phase,
                                   array $reponses): void
    {
        foreach ($reponses as $question => $texte) {
            $this->enregistrer($siteId, $participantId, $phase, $question, $texte);
        }
    }
}
