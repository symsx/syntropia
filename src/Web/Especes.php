<?php

declare(strict_types=1);

namespace Syntropia\Web;

use PDO;
use Syntropia\Database;

/**
 * Le référentiel de végétaux — un cadre partagé, des surcharges par site.
 *
 * Chantier « scénario de plantation », Phase 1 — voir
 * `chantier-scenario-plantation.md`. La table `espece` existait déjà
 * dans le schéma (portée du dépôt de référence), sans qu'aucune page
 * ne la lise ou l'écrive encore : ni ici, ni côté référence, où le
 * formulaire du scénario reste un champ texte libre. Cette classe est
 * donc la première à s'en servir pour de vrai.
 *
 * **La base dit ce qu'est la plante, ce dépôt dit ce qu'on lui fait
 * faire.** Aucune donnée botanique ici — hauteur, houppier, rusticité
 * se lisent chez Plantae, par `nom_scientifique` ; la jointure reste
 * applicative (deux bases, deux collations), jamais une clé étrangère.
 *
 * `site_id = 0` porte le cadre, partagé par tous les sites (le seed de
 * `sql/02-especes-seed.sql`). Un site peut surcharger une espèce du
 * cadre — même `nom_scientifique`, même `variete`, son propre
 * `site_id` — et la surcharge prime toujours ; il peut aussi ajouter
 * une espèce que le cadre ne connaît pas. **Le cadre ne se supprime
 * jamais depuis l'interface** : seule une surcharge locale peut être
 * retirée, auquel cas le site retrouve la version du cadre si elle
 * existe.
 *
 * **Une variété n'est pas une autre espèce, mais elle peut changer de
 * strate ou de rôle du tout au tout** — remarque du porteur,
 * 2026-09-18 : le fraisier des bois et le fraisier du commerce sont
 * deux espèces distinctes (donc deux lignes de toute façon), mais un
 * tournesol conduit à une fleur (biomasse) et un tournesol classique
 * ramifié (graine) sont la MÊME espèce sous deux variétés — d'où la
 * clé `(site_id, nom_scientifique, variete)`, `variete` valant `''`
 * quand l'espèce ne s'en distingue pas.
 */
final class Especes
{
    public function __construct(private ?PDO $pdo = null) {}

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::syntropia();
    }

    /**
     * Le cadre et les surcharges de ce site, fusionnés — la surcharge
     * l'emporte sur le cadre pour un même couple `(nom_scientifique,
     * variete)` : deux variétés de la même espèce restent deux lignes
     * distinctes, chacune surchargeable séparément. Triée par nom
     * usuel puis nom scientifique, pour une liste stable à l'écran.
     *
     * @return list<array<string, mixed>>
     */
    public function toutes(int $siteId): array
    {
        $req = $this->pdo()->prepare(
            'SELECT * FROM espece WHERE site_id = 0 OR site_id = ? ORDER BY nom_scientifique, variete');
        $req->execute([$siteId]);
        $fusion = [];
        foreach ($req->fetchAll() as $ligne) {
            $cle = mb_strtolower((string) $ligne['nom_scientifique']) . '|' . (string) $ligne['variete'];
            // La ligne locale arrive après ou avant le cadre selon l'ordre
            // SQL, peu importe : (int) $ligne['site_id'] tranche toujours,
            // pas l'ordre de lecture.
            if (!isset($fusion[$cle]) || (int) $ligne['site_id'] === $siteId) {
                $fusion[$cle] = $ligne;
            }
        }
        usort($fusion, static fn (array $a, array $b): int =>
            strcasecmp((string) ($a['nom_usuel'] ?: $a['nom_scientifique']),
                       (string) ($b['nom_usuel'] ?: $b['nom_scientifique'])));
        return array_values($fusion);
    }

    /**
     * Une espèce (et sa variété) précise — le cadre, ou la surcharge de
     * ce site si elle existe. `null` si ni l'un ni l'autre ne connaît
     * ce couple. `$variete` vide désigne l'espèce sans variété notée,
     * pas « n'importe quelle variété ».
     *
     * @return array<string, mixed>|null
     */
    public function parNom(string $nomScientifique, string $variete, int $siteId): ?array
    {
        $req = $this->pdo()->prepare(
            'SELECT * FROM espece WHERE nom_scientifique = ? AND variete = ? AND site_id IN (0, ?)
             ORDER BY (site_id = ?) DESC LIMIT 1');
        $req->execute([$nomScientifique, $variete, $siteId, $siteId]);
        return $req->fetch() ?: null;
    }

    /** @return array<string, mixed>|null */
    public function parId(int $id): ?array
    {
        $req = $this->pdo()->prepare('SELECT * FROM espece WHERE id = ?');
        $req->execute([$id]);
        return $req->fetch() ?: null;
    }

    /**
     * Whitelist des colonnes qu'un site peut renseigner sur sa propre
     * surcharge — tout sauf `id`/`site_id`, qui ne se saisissent pas.
     *
     * @var list<string>
     */
    private const COLONNES = [
        'nom_usuel', 'role', 'strates_possibles', 'systeme',
        'entree_scenario', 'sortie_scenario',
        'espacement_monoculture_m', 'espacement_retenu_m', 'plants_par_ml',
        'biomasse', 'rapport_cn', 'conduite_taille', 'periode_taille',
        'type_plant', 'origine', 'serre_necessaire', 'bloque_herbe',
        'orientation_bordure', 'deplacable', 'source', 'statut_local', 'notes',
    ];

    /**
     * Crée ou met à jour la surcharge locale de ce site pour une espèce
     * (et sa variété — `''` pour l'espèce sans variété notée).
     *
     * Une seule méthode pour les deux cas — `INSERT ... ON DUPLICATE KEY
     * UPDATE` sur l'index unique `(site_id, nom_scientifique, variete)`
     * — parce que « ajouter une espèce inconnue du cadre » et
     * « surcharger une espèce du cadre » sont le même geste pour qui
     * remplit le formulaire : donner sa propre version d'une espèce,
     * sur son site.
     *
     * @param array<string, mixed> $valeurs
     * @return array{id: ?int, erreurs: array<string, string>}
     */
    public function enregistrer(int $siteId, string $nomScientifique, string $variete, array $valeurs): array
    {
        $nom = trim($nomScientifique);
        $variete = trim($variete);
        $erreurs = [];
        if ($nom === '') {
            $erreurs['nom_scientifique'] = 'Le nom scientifique est obligatoire — c\'est la clé vers Plantae.';
        } elseif (mb_strlen($nom) > 160) {
            $erreurs['nom_scientifique'] = 'Le nom scientifique est trop long (160 caractères au plus).';
        }
        if (mb_strlen($variete) > 120) {
            $erreurs['variete'] = 'Le nom de variété est trop long (120 caractères au plus).';
        }
        if ($siteId <= 0) {
            $erreurs['site_id'] = 'Une surcharge se pose toujours sur un site précis, jamais sur le cadre.';
        }
        $systeme = isset($valeurs['systeme']) ? trim((string) $valeurs['systeme']) : '';
        if ($systeme !== '' && !in_array($systeme, ['colonisation', 'accumulation', 'abondance'], true)) {
            $erreurs['systeme'] = 'Le système doit être colonisation, accumulation ou abondance.';
        }
        if ($erreurs !== []) {
            return ['id' => null, 'erreurs' => $erreurs];
        }

        $propres = ['nom_scientifique' => $nom, 'variete' => $variete, 'site_id' => $siteId];
        foreach (self::COLONNES as $colonne) {
            if (array_key_exists($colonne, $valeurs)) {
                $brut = $valeurs[$colonne];
                $propres[$colonne] = $brut === '' || $brut === null ? null : $brut;
            }
        }

        $colonnesSql = array_keys($propres);
        $placeholders = implode(', ', array_fill(0, count($colonnesSql), '?'));
        $affectations = implode(', ', array_map(
            static fn (string $c): string => "`$c` = VALUES(`$c`)",
            array_diff($colonnesSql, ['nom_scientifique', 'variete', 'site_id'])));
        $sql = 'INSERT INTO espece (`' . implode('`, `', $colonnesSql) . '`) VALUES (' . $placeholders . ')'
            . ' ON DUPLICATE KEY UPDATE ' . $affectations;
        $this->pdo()->prepare($sql)->execute(array_values($propres));

        $id = $this->pdo()->lastInsertId();
        if ((int) $id === 0) {
            // ON DUPLICATE KEY UPDATE ne redonne pas l'id : le relire.
            $req = $this->pdo()->prepare(
                'SELECT id FROM espece WHERE site_id = ? AND nom_scientifique = ? AND variete = ?');
            $req->execute([$siteId, $nom, $variete]);
            $id = $req->fetchColumn();
        }
        return ['id' => (int) $id, 'erreurs' => []];
    }

    /**
     * Retire la surcharge locale d'un site — jamais le cadre.
     *
     * Le `site_id` passé sert de garde : `WHERE site_id = ?` empêche à
     * lui seul de retirer la ligne d'un autre site, mais **ne suffit
     * pas** à protéger le cadre — un appelant qui passerait `siteId =
     * 0` par erreur matcherait la garde tout autant que n'importe quel
     * site réel. D'où le refus explicite ci-dessous, avant toute
     * requête : ce n'est pas la clause SQL qui protège le cadre, c'est
     * cette vérification.
     */
    public function retirer(int $id, int $siteId): void
    {
        if ($siteId <= 0) {
            return;
        }
        $req = $this->pdo()->prepare('DELETE FROM espece WHERE id = ? AND site_id = ?');
        $req->execute([$id, $siteId]);
    }
}
