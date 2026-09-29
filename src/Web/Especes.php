<?php

declare(strict_types=1);

namespace Syntropia\Web;

use PDO;
use Syntropia\Associations;
use Syntropia\CalendrierLopez;
use Syntropia\Conduite;
use Syntropia\Database;
use Syntropia\Scenario;

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
     * Retrouve une espèce par un nom d'usage — celui qu'on tape dans un scénario ou un plan de culture, pas
     * forcément `nom_scientifique` ni `variete` exacts. Chantier « caractéristiques des végétaux », Phase 3 :
     * même rapprochement de noms que les voisinages de la page (`Associations::memeNom()` — sans accent ni
     * casse, singulier ou pluriel), pour proposer le calendrier et les rôles d'une espèce connue. `null` si
     * rien ne correspond, y compris texte vide — ce n'est jamais un rapprochement forcé.
     *
     * @return array<string, mixed>|null
     */
    public function parNomUsage(string $nom, int $siteId): ?array
    {
        $nom = trim($nom);
        if ($nom === '') {
            return null;
        }
        foreach ($this->toutes($siteId) as $e) {
            if (Associations::memeNom((string) $e['nom_usuel'], $nom) || Associations::memeNom((string) $e['nom_scientifique'], $nom)) {
                return $e;
            }
        }
        return null;
    }

    /**
     * Des dates INDICATIVES depuis le calendrier d'une espèce — le milieu de chaque fenêtre, à la prochaine
     * occurrence, comme `CalendrierLopez::datesIndicatives()` (dont elle reprend la logique, étendue à la
     * récolte : Lopez ne la donne pas, `espece` le peut). La mise en place ne précède jamais le semis, la
     * récolte ne précède jamais la mise en place — ni, à défaut, le semis.
     *
     * @param array<string, mixed> $espece une ligne d'`espece` (periode_semis, periode_repiquage, periode_recolte)
     * @return array{semis: ?string, repiquage: ?string, recolte: ?string}
     */
    public static function datesIndicatives(array $espece, \DateTimeImmutable $reference): array
    {
        $periode = static fn (mixed $t): ?array => is_string($t) && $t !== '' ? CalendrierLopez::periode($t) : null;
        $ps = $periode($espece['periode_semis'] ?? null);
        $pr = $periode($espece['periode_repiquage'] ?? null);
        $prc = $periode($espece['periode_recolte'] ?? null);
        $annee = (int) $reference->format('Y');
        $aujourdhui = $reference->setTime(0, 0);
        $date = static fn (array $p, int $a): \DateTimeImmutable => new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $a, $p['mois'], $p['milieu']));

        $semis = null;
        if ($ps !== null) {
            $semis = $date($ps, $annee);
            if ($semis < $aujourdhui) {
                $semis = $date($ps, $annee + 1);
            }
        }
        $repiquage = null;
        if ($pr !== null) {
            $depart = $semis !== null ? (int) $semis->format('Y') : $annee;
            $repiquage = $date($pr, $depart);
            if ($semis !== null && $repiquage < $semis) {
                $repiquage = $date($pr, $depart + 1);
            } elseif ($semis === null && $repiquage < $aujourdhui) {
                $repiquage = $date($pr, $depart + 1);
            }
        }
        $recolte = null;
        if ($prc !== null) {
            $base = $repiquage ?? $semis;
            $depart = $base !== null ? (int) $base->format('Y') : $annee;
            $recolte = $date($prc, $depart);
            if ($base !== null && $recolte < $base) {
                $recolte = $date($prc, $depart + 1);
            } elseif ($base === null && $recolte < $aujourdhui) {
                $recolte = $date($prc, $depart + 1);
            }
        }
        return ['semis' => $semis?->format('Y-m-d'), 'repiquage' => $repiquage?->format('Y-m-d'), 'recolte' => $recolte?->format('Y-m-d')];
    }

    /**
     * Les valeurs possibles de `role` — une liste fermée, plusieurs valeurs à la fois (séparées par `|`).
     * `production` (l'espèce se cultive pour ce qu'elle donne), plus les fonctions de service du scénario
     * pérenne (`Scenario::FONCTIONS`) : le même vocabulaire, décidé par le porteur pour ne pas en tenir deux.
     * Une méthode plutôt qu'une constante : un `array_keys()` sur la constante d'une autre classe ne se calcule
     * pas à la déclaration. La page en construit les cases à cocher.
     *
     * @return list<string>
     */
    public static function roles(): array
    {
        return array_merge(['production'], array_keys(Scenario::FONCTIONS));
    }

    /**
     * Le cycle de vie d'une espèce — une donnée de la succession syntropique (le porteur, 2026-09-23,
     * `chantier-peuplement-parcelle.md`). Les bordures retiennent les vivaces et les ligneuses ; les lignes de
     * biomasse des planches, surtout les annuelles. Liste fermée, une valeur ; l'ENUM de `espece.cycle` la répète.
     */
    public const CYCLES = [
        'annuelle' => 'Annuelle',
        'bisannuelle' => 'Bisannuelle',
        'vivace' => 'Vivace (herbacée pérenne)',
        'ligneuse' => 'Ligneuse',
    ];

    /**
     * Whitelist des colonnes qu'un site peut renseigner sur sa propre
     * surcharge — tout sauf `id`/`site_id`, qui ne se saisissent pas.
     * Publique : la page (formulaire, en-tête attendu d'un import CSV)
     * s'en sert pour ne pas dupliquer la liste.
     *
     * @var list<string>
     */
    public const COLONNES = [
        'nom_usuel', 'role', 'strates_possibles', 'systeme',
        'entree_scenario', 'sortie_scenario',
        'espacement_monoculture_m', 'espacement_retenu_m', 'plants_par_ml',
        'periode_semis', 'periode_repiquage', 'periode_recolte', 'cycle', 'delai_germination_j', 'temperature_sol_semis_c',
        'hauteur_m', 'largeur_m', 'strate_production', 'strate_service', 'biomasse', 'rapport_cn', 'conduite_taille', 'periode_taille',
        'type_plant', 'origine', 'serre_necessaire', 'bloque_herbe',
        'orientation_bordure', 'deplacable', 'source', 'statut_local', 'notes',
    ];

    /**
     * Valide une saisie (ou une ligne d'import) contre la forme réelle des colonnes — types, longueurs, énumérations,
     * plages de sens. **Seules les clés présentes dans `$v` sont vérifiées et retournées** : une case absente n'écrase
     * rien (édition manuelle, un champ à la fois) ; une case présente mais vide efface la colonne (`null`). Chantier
     * `chantier-caracteristiques-vegetaux.md`, Phase 2 — utilisée aussi bien par le formulaire que par l'import CSV,
     * pour qu'un même repère (une date en mots, un espacement) subisse le même contrôle des deux côtés.
     *
     * @param array<string, mixed> $v la saisie brute — seules les clés de `COLONNES` qui y figurent sont traitées
     * @return array{valeurs: array<string, mixed>, erreurs: array<string, string>}
     */
    public static function valider(array $v): array
    {
        $erreurs = [];
        $valeurs = [];
        $texte = static function (string $cle, int $max) use ($v, &$erreurs, &$valeurs): void {
            if (!array_key_exists($cle, $v)) {
                return;
            }
            $t = trim((string) $v[$cle]);
            if (mb_strlen($t) > $max) {
                $erreurs[$cle] = "$max caractères au plus.";
                return;
            }
            $valeurs[$cle] = $t !== '' ? $t : null;
        };
        $enum = static function (string $cle, array $options, string $motif) use ($v, &$erreurs, &$valeurs): void {
            if (!array_key_exists($cle, $v)) {
                return;
            }
            $t = trim((string) $v[$cle]);
            if ($t !== '' && !in_array($t, $options, true)) {
                $erreurs[$cle] = $motif;
                return;
            }
            $valeurs[$cle] = $t !== '' ? $t : null;
        };
        // Tolère deux formes : un texte `|` (l'import CSV, comme espece.role) ou un tableau (les cases à cocher
        // d'un formulaire, `nom[]`) — la même valeur multiple, écrite différemment selon qui la fournit.
        $liste = static function (string $cle, array $connus, string $motif) use ($v, &$erreurs, &$valeurs): void {
            if (!array_key_exists($cle, $v)) {
                return;
            }
            $brut = $v[$cle];
            if (is_array($brut)) {
                $items = array_values(array_filter(array_map(static fn ($x): string => trim((string) $x), $brut), static fn (string $x): bool => $x !== ''));
            } else {
                $t = trim((string) $brut);
                $items = $t === '' ? [] : array_values(array_filter(array_map('trim', explode('|', $t)), static fn (string $x): bool => $x !== ''));
            }
            if ($items === []) {
                $valeurs[$cle] = null;
                return;
            }
            if (array_diff($items, $connus) !== []) {
                $erreurs[$cle] = $motif;
                return;
            }
            $valeurs[$cle] = implode('|', $items);
        };
        $nombre = static function (string $cle, float $min, float $max) use ($v, &$erreurs, &$valeurs): void {
            if (!array_key_exists($cle, $v)) {
                return;
            }
            $brut = str_replace(',', '.', trim((string) $v[$cle]));
            if ($brut === '') {
                $valeurs[$cle] = null;
                return;
            }
            if (!is_numeric($brut) || (float) $brut < $min || (float) $brut > $max) {
                $erreurs[$cle] = "Un nombre de $min à $max.";
                return;
            }
            $valeurs[$cle] = round((float) $brut, 2);
        };
        $bool = static function (string $cle) use ($v, &$valeurs): void {
            if (!array_key_exists($cle, $v)) {
                return;
            }
            $valeurs[$cle] = in_array((string) $v[$cle], ['1', 'on'], true) ? 1 : null;
        };
        $periode = static function (string $cle, string $nom) use ($v, &$erreurs, &$valeurs): void {
            if (!array_key_exists($cle, $v)) {
                return;
            }
            $t = trim((string) $v[$cle]);
            if ($t === '') {
                $valeurs[$cle] = null;
                return;
            }
            if (mb_strlen($t) > 60 || CalendrierLopez::periode($t) === null) {
                $erreurs[$cle] = "$nom se dit en mots, comme « fin avril » ou « mi à fin juillet ».";
                return;
            }
            $valeurs[$cle] = $t;
        };

        $texte('nom_usuel', 160);
        // Le rôle : une liste fermée, plusieurs valeurs possibles — `production`, plus les fonctions de service du
        // scénario pérenne (`Scenario::FONCTIONS`). Décision du porteur, 2026-09-23 (chantier-caracteristiques-
        // vegetaux.md) : revient sur le choix de la Phase 1 de le laisser libre — « production » manquait alors à ce
        // vocabulaire, il y est désormais explicitement ajouté. Le seed curé (`sql/02-especes-seed.sql`) a été
        // vérifié conforme (une valeur « bordure », hors vocabulaire, corrigée à cette occasion).
        $liste('role', self::roles(), 'Une valeur de la liste — production, ou une fonction de service du scénario.');
        $liste('strates_possibles', Conduite::toutesLesStrates(), 'Une strate proposée par la conduite syntropique ou jardin-forêt.');
        $enum('systeme', ['colonisation', 'accumulation', 'abondance'], 'Colonisation, accumulation ou abondance.');
        $enum('cycle', array_keys(self::CYCLES), 'Annuelle, bisannuelle, vivace ou ligneuse.');
        // La température du sol qui permet le semis, en °C (Théry ch. 9 ; schéma 15).
        $nombre('temperature_sol_semis_c', 0, 40);
        // La hauteur de la plante adulte, en mètres (schéma 16) — un séquoia dépasse à peine 60 m en Europe.
        $nombre('hauteur_m', 0.01, 60);
        // L'envergure de la plante adulte, en mètres (schéma 17) — dessine les plants des bordures à leur taille réelle.
        $nombre('largeur_m', 0.01, 60);
        // La strate selon l'usage — production, service (biomasse, bloque-herbe) : Théry ch. 05, schéma 18.
        $enum('strate_production', array_keys(\Syntropia\Strates::REPERES), 'Émergente, haute, moyenne ou basse.');
        $enum('strate_service', array_keys(\Syntropia\Strates::REPERES), 'Émergente, haute, moyenne ou basse.');
        // Le délai de germination, en jours entiers (chantier « film de la parcelle », schéma 13).
        if (array_key_exists('delai_germination_j', $v)) {
            $brut = trim((string) $v['delai_germination_j']);
            if ($brut === '') {
                $valeurs['delai_germination_j'] = null;
            } elseif (!ctype_digit($brut) || (int) $brut < 1 || (int) $brut > 365) {
                $erreurs['delai_germination_j'] = 'Un nombre entier de jours, de 1 à 365.';
            } else {
                $valeurs['delai_germination_j'] = (int) $brut;
            }
        }
        $enum('entree_scenario', array_keys(Scenario::PERIODES), 'Une période du scénario (placenta1…climax).');
        $enum('sortie_scenario', array_keys(Scenario::PERIODES), 'Une période du scénario (placenta1…climax).');
        $nombre('espacement_monoculture_m', 0.01, 50);
        $nombre('espacement_retenu_m', 0.01, 50);
        $nombre('plants_par_ml', 0.01, 1000);
        $periode('periode_semis', 'Le semis');
        $periode('periode_repiquage', 'Le repiquage');
        $periode('periode_recolte', 'La récolte');
        $enum('biomasse', ['+', '++', '+++'], 'Notée +, ++ ou +++.');
        $texte('rapport_cn', 24);
        $texte('conduite_taille', 120);
        $texte('periode_taille', 120);
        $texte('type_plant', 60);
        $enum('origine', ['achat', 'multiplication', 'rna', 'mixte'], 'Achat, multiplication, RNA ou mixte.');
        $bool('serre_necessaire');
        $bool('bloque_herbe');
        $texte('orientation_bordure', 60);
        $bool('deplacable');
        $texte('source', 120);
        $texte('statut_local', 60);
        $texte('notes', 2000);

        return ['valeurs' => $valeurs, 'erreurs' => $erreurs];
    }

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
        ['valeurs' => $propres, 'erreurs' => $erreursColonnes] = self::valider($valeurs);
        $erreurs += $erreursColonnes;
        if ($erreurs !== []) {
            return ['id' => null, 'erreurs' => $erreurs];
        }

        $propres = ['nom_scientifique' => $nom, 'variete' => $variete, 'site_id' => $siteId] + $propres;
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
     * Écrit plusieurs espèces d'un coup — l'import CSV (Phase 2). Une transaction : si l'une des lignes échoue à la
     * validation (revalidée ici, comme l'import d'un projet revalide à la confirmation), **rien n'est écrit** ; la
     * page l'a déjà annoncé à l'aperçu, mais un entre-temps (une autre plume sur le même site) reste possible.
     *
     * @param list<array<string, mixed>> $lignes chacune avec `nom_scientifique`, `variete` et les colonnes de COLONNES
     * @return array{ok: bool, ecrites: int, ligne: ?int, erreurs: array<string, string>}
     */
    public function enregistrerLot(int $siteId, array $lignes): array
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $n = 0;
            foreach ($lignes as $i => $ligne) {
                $res = $this->enregistrer($siteId, (string) ($ligne['nom_scientifique'] ?? ''), (string) ($ligne['variete'] ?? ''), $ligne);
                if ($res['erreurs'] !== []) {
                    $pdo->rollBack();
                    return ['ok' => false, 'ecrites' => 0, 'ligne' => (int) $i + 1, 'erreurs' => $res['erreurs']];
                }
                $n++;
            }
            $pdo->commit();
            return ['ok' => true, 'ecrites' => $n, 'ligne' => null, 'erreurs' => []];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
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
