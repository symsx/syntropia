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
     * Les réponses de Définition à imprimer dans le Dossier Projet — celles de la personne si elle en a donné, à défaut
     * celles du porteur (même règle que `preparationDuSite()`). Lecture seule : contrairement à
     * `Sites::participantDuCompte()`, ne crée jamais de participant. `nom` : le participant dont viennent les réponses,
     * `null` s'il n'y en a aucune.
     *
     * @return array{reponses: array<string, string>, nom: ?string}
     */
    public function definitionDuProjet(int $siteId, int $compteId): array
    {
        return $this->duProjet($siteId, $compteId, 'definition');
    }

    /**
     * Le calendrier de la Conception (`calendrier_printemps`… `calendrier_hiver`), pour le Dossier Projet, sur la même
     * règle que `definitionDuProjet()` : la personne si elle l'a rempli, à défaut le porteur.
     *
     * @return array{reponses: array<string, string>, nom: ?string}
     */
    public function calendrierDuProjet(int $siteId, int $compteId): array
    {
        return $this->duProjet($siteId, $compteId, 'conception', ['calendrier_printemps', 'calendrier_ete', 'calendrier_automne', 'calendrier_hiver']);
    }

    /**
     * Les réponses d'une phase, limitées à quelques questions si `$questions` les nomme : celles de la personne si elle
     * en a donné au moins une, à défaut celles du porteur. Lecture seule.
     *
     * @param list<string> $questions
     * @return array{reponses: array<string, string>, nom: ?string}
     */
    private function duProjet(int $siteId, int $compteId, string $phase, array $questions = []): array
    {
        $lire = function (string $sql, array $params): ?array {
            $req = $this->pdo()->prepare($sql);
            $req->execute($params);
            $p = $req->fetch(PDO::FETCH_ASSOC);
            return is_array($p) ? $p : null;
        };
        $candidats = [
            $lire('SELECT id, nom FROM participant WHERE site_id = ? AND compte_id = ?', [$siteId, $compteId]),
            $lire("SELECT p.id, p.nom FROM participant p
                     JOIN membre_site m ON m.site_id = p.site_id AND m.compte_id = p.compte_id
                    WHERE p.site_id = ? AND m.role = 'porteur'
                    ORDER BY p.id LIMIT 1", [$siteId]),
        ];
        foreach ($candidats as $p) {
            if ($p === null) {
                continue;
            }
            $req = $this->pdo()->prepare(
                "SELECT question, texte FROM reponse WHERE participant_id = ? AND phase = ? AND texte <> ''"
                . ($questions === [] ? '' : ' AND question IN (' . implode(', ', array_fill(0, count($questions), '?')) . ')'));
            $req->execute([(int) $p['id'], $phase, ...$questions]);
            $reponses = $req->fetchAll(PDO::FETCH_KEY_PAIR);
            if (is_array($reponses) && $reponses !== []) {
                return ['reponses' => $reponses, 'nom' => (string) $p['nom']];
            }
        }
        return ['reponses' => [], 'nom' => null];
    }

    /**
     * Ce que la Réalisation a noté de la préparation du sol — celui de la personne, à défaut celui du porteur
     * (même règle que `Scenarios::souhaitsDuSite()`) : la préparation est un geste du projet, et un lecteur
     * n'a rien écrit lui-même. Clés : `technique`, `realisee`.
     *
     * @return array<string, string>
     */
    public function preparationDuSite(int $siteId, int $participantId): array
    {
        $lire = function (string $sql, array $params): array {
            $req = $this->pdo()->prepare($sql);
            $req->execute($params);
            $lignes = $req->fetchAll(PDO::FETCH_KEY_PAIR);
            return is_array($lignes) ? $lignes : [];
        };
        $q = ['preparation_technique' => 'technique', 'preparation_realisee' => 'realisee'];
        $v = $lire("SELECT question, texte FROM reponse WHERE participant_id = ? AND question IN ('preparation_technique', 'preparation_realisee')", [$participantId]);
        if (trim((string) ($v['preparation_technique'] ?? '')) === '') {
            $v = $lire("SELECT r.question, r.texte FROM reponse r
                          JOIN participant p ON p.id = r.participant_id
                          JOIN membre_site m ON m.site_id = p.site_id AND m.compte_id = p.compte_id
                         WHERE r.site_id = ? AND m.role = 'porteur'
                           AND r.question IN ('preparation_technique', 'preparation_realisee')
                           AND p.id = (SELECT MIN(p2.id) FROM participant p2
                                         JOIN membre_site m2 ON m2.site_id = p2.site_id AND m2.compte_id = p2.compte_id
                                        WHERE p2.site_id = ? AND m2.role = 'porteur')", [$siteId, $siteId]);
        }
        $sortie = [];
        foreach ($q as $question => $cle) {
            $sortie[$cle] = trim((string) ($v[$question] ?? ''));
        }
        return $sortie;
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
        // Un lecteur regarde : il n'écrit rien, pas même ses propres
        // réponses. Les pages gardent leur redirection (« Continuer » doit
        // encore avancer) — seule l'écriture est ignorée, ici, au point
        // unique par où passent toutes les pages de saisie.
        if ($this->estLecteur($participantId)) {
            return;
        }
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

    /** Ce participant est-il rattaché à un compte qui ne fait que lire ce site ? */
    private function estLecteur(int $participantId): bool
    {
        $req = $this->pdo()->prepare(
            "SELECT 1 FROM participant p
               JOIN membre_site m ON m.site_id = p.site_id AND m.compte_id = p.compte_id
              WHERE p.id = ? AND m.role = 'lecteur'");
        $req->execute([$participantId]);
        return (bool) $req->fetchColumn();
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
