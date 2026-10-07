<?php

declare(strict_types=1);

namespace Syntropia\Web;

use PDO;
use Syntropia\Bordures;
use Syntropia\Database;
use Syntropia\Espacement;

/**
 * Les lignes de plants d'une parcelle (`ligne_plantation`) — celles des bordures (Phase 2), les lignes de service des
 * planches (Phase 6) et la ligne des lianes d'une tortue liane (`liane:coeur`… — même conduite qu'une bordure).
 *
 * Voir `chantier-peuplement-parcelle.md`, Phase 2. Une ligne = une série ordonnée d'espèces du référentiel, un
 * enregistrement par place (`rang`). Même conduite que `Interventions` : l'appartenance de la parcelle au site se
 * vérifie à l'appel, chaque requête est bornée à la parcelle, et l'écriture se fait sous verrou de version — ici la
 * version de toute la série d'un emplacement, qu'on remplace d'un bloc.
 */
final class LignesPlantation
{
    public function __construct(private ?PDO $pdo = null, private ?Especes $especes = null) {}

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::syntropia();
    }

    private function especes(): Especes
    {
        return $this->especes ??= new Especes($this->pdo());
    }

    /** @return array<string, list<array<string, mixed>>> emplacement → places de la série, dans l'ordre */
    public function dUneParcelle(int $parcelleId): array
    {
        $req = $this->pdo()->prepare('SELECT * FROM ligne_plantation WHERE parcelle_id = ? ORDER BY emplacement, rang');
        $req->execute([$parcelleId]);
        $out = [];
        foreach ($req->fetchAll() as $l) {
            $out[(string) $l['emplacement']][] = $l;
        }
        return $out;
    }

    /** @param list<array<string, mixed>> $serie les places d'un emplacement */
    public static function version(array $serie): string
    {
        $cles = array_map(static fn (array $l): array => [(int) $l['rang'], (string) $l['nom_scientifique'], (string) $l['variete']], $serie);
        return hash('sha256', json_encode($cles, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /**
     * Remplace la série d'un emplacement. Une série vide retire la ligne.
     *
     * @param list<int> $idsEspeces les espèces, dans l'ordre de l'alternance — des identifiants du référentiel, cadre
     *                              ou surcharge de ce site
     * @return array{ok: bool, erreurs: array<string, string>}
     */
    public function enregistrer(int $parcelleId, int $siteId, string $emplacement, array $idsEspeces, string $versionRecue): array
    {
        if (Bordures::depuisCle($emplacement) === null) {
            return ['ok' => false, 'erreurs' => ['emplacement' => 'Cette bordure n’existe pas.']];
        }
        if (count($idsEspeces) > Bordures::MAX_SERIE) {
            return ['ok' => false, 'erreurs' => ['serie' => 'Une ligne compte au plus ' . Bordures::MAX_SERIE . ' espèces dans sa série.']];
        }
        $places = [];
        foreach ($idsEspeces as $id) {
            $e = is_int($id) && $id > 0 ? $this->especes()->parId($id) : null;
            if ($e === null || !in_array((int) $e['site_id'], [0, $siteId], true)) {
                return ['ok' => false, 'erreurs' => ['serie' => 'Une espèce de la série n’est pas dans le référentiel de ce projet.']];
            }
            $places[] = [(string) $e['nom_scientifique'], (string) $e['variete']];
        }

        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $req = $pdo->prepare('SELECT * FROM ligne_plantation WHERE parcelle_id = ? AND emplacement = ? ORDER BY rang FOR UPDATE');
            $req->execute([$parcelleId, $emplacement]);
            $anciennes = $req->fetchAll();
            if (!hash_equals(self::version($anciennes), $versionRecue)) {
                $pdo->rollBack();
                return ['ok' => false, 'erreurs' => ['version' => 'Cette ligne a changé depuis que vous l’avez ouverte : rechargez la page, puis refaites votre changement.']];
            }
            if (($refus = $this->horsPalette($parcelleId, $places, $anciennes)) !== null) {
                $pdo->rollBack();
                return ['ok' => false, 'erreurs' => ['serie' => $refus]];
            }
            $pdo->prepare('DELETE FROM ligne_plantation WHERE parcelle_id = ? AND emplacement = ?')->execute([$parcelleId, $emplacement]);
            $ins = $pdo->prepare('INSERT INTO ligne_plantation (parcelle_id, emplacement, rang, nom_scientifique, variete) VALUES (?, ?, ?, ?, ?)');
            foreach ($places as $k => [$nom, $variete]) {
                $ins->execute([$parcelleId, $emplacement, $k + 1, $nom, $variete]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        return ['ok' => true, 'erreurs' => []];
    }

    /**
     * Pose d'un bloc les séries d'une répartition (`RepartitionBordures`, `chantier-repartition-bordures.md`, Phase 3) :
     * tous les côtés des bordures externe et interne, **en une transaction**. Refusé si le plan a changé depuis l'aperçu
     * (sa version), ou si une bordure externe ou interne porte déjà une ligne — la répartition part de bordures vides.
     *
     * @param array<string, list<array<string, mixed>>> $series emplacement (`externe:nord`…) → espèces de la série
     * @return array{ok: bool, erreurs: array<string, string>}
     */
    public function poserRepartition(int $parcelleId, int $siteId, array $series, string $versionPlan): array
    {
        $places = [];
        foreach ($series as $emplacement => $serie) {
            $c = Bordures::depuisCle((string) $emplacement);
            if ($c === null || !in_array($c[0], \Syntropia\RepartitionBordures::CADRES, true)) {
                return ['ok' => false, 'erreurs' => ['emplacement' => 'Cette bordure n’existe pas.']];
            }
            if ($serie === [] || count($serie) > Bordures::MAX_SERIE) {
                return ['ok' => false, 'erreurs' => ['serie' => 'Une ligne compte d’une à ' . Bordures::MAX_SERIE . ' places.']];
            }
            foreach ($serie as $e) {
                if (!in_array((int) ($e['site_id'] ?? -1), [0, $siteId], true)) {
                    return ['ok' => false, 'erreurs' => ['serie' => 'Une espèce de la série n’est pas dans le référentiel de ce projet.']];
                }
                $places[(string) $emplacement][] = [(string) $e['nom_scientifique'], (string) $e['variete']];
            }
        }
        if ($places === []) {
            return ['ok' => false, 'erreurs' => ['serie' => 'Rien à poser.']];
        }

        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $req = $pdo->prepare('SELECT * FROM parcelle WHERE id = ? AND site_id = ? FOR UPDATE');
            $req->execute([$parcelleId, $siteId]);
            $parcelle = $req->fetch();
            if ($parcelle === false) {
                $pdo->rollBack();
                return ['ok' => false, 'erreurs' => ['parcelle' => 'Cette parcelle n’appartient pas à ce projet.']];
            }
            if (!hash_equals(Parcelles::versionPlan($parcelle), $versionPlan)) {
                $pdo->rollBack();
                return ['ok' => false, 'erreurs' => ['version' => 'Le plan a changé depuis l’aperçu : recalculez la répartition.']];
            }
            $req = $pdo->prepare("SELECT COUNT(*) FROM ligne_plantation WHERE parcelle_id = ?
                AND (emplacement LIKE 'externe:%' OR emplacement LIKE 'interne:%') FOR UPDATE");
            $req->execute([$parcelleId]);
            if ((int) $req->fetchColumn() > 0) {
                $pdo->rollBack();
                return ['ok' => false, 'erreurs' => ['version' => 'Une bordure a reçu des plantes depuis l’aperçu : la répartition part de bordures vides.']];
            }
            if (($refus = $this->horsPalette($parcelleId, array_merge(...array_values($places)), [])) !== null) {
                $pdo->rollBack();
                return ['ok' => false, 'erreurs' => ['serie' => $refus]];
            }
            $ins = $pdo->prepare('INSERT INTO ligne_plantation (parcelle_id, emplacement, rang, nom_scientifique, variete) VALUES (?, ?, ?, ?, ?)');
            foreach ($places as $emplacement => $serie) {
                foreach ($serie as $k => [$nom, $variete]) {
                    $ins->execute([$parcelleId, $emplacement, $k + 1, $nom, $variete]);
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        return ['ok' => true, 'erreurs' => []];
    }

    /**
     * Les plantes d'une ligne viennent de la palette de la parcelle (chantier-palette-vegetale.md, Phase 4) : une espèce
     * posée qui n'y est pas est refusée, sauf si elle était déjà sur cette ligne — ce qui est posé reste, même hors
     * palette.
     *
     * @param list<array{0: string, 1: string}> $places nom scientifique et variété de chaque place
     * @param list<array<string, mixed>> $anciennes les lignes déjà enregistrées à cet endroit
     * @return string|null le refus à montrer, `null` si tout vient de la palette
     */
    private function horsPalette(int $parcelleId, array $places, array $anciennes): ?string
    {
        $permises = [];
        foreach ($anciennes as $l) {
            $permises[Palettes::cle($l)] = true;
        }
        foreach ((new Palettes($this->pdo()))->dUneParcelle($parcelleId) as $l) {
            $permises[Palettes::cle($l)] = true;
        }
        foreach ($places as [$nom, $variete]) {
            if (!isset($permises[Palettes::cle(['nom_scientifique' => $nom, 'variete' => $variete])])) {
                return 'Une espèce choisie n’est pas dans la palette de la parcelle : ajoutez-la d’abord à la palette.';
            }
        }
        return null;
    }

    /** La clé d'une ligne de planche : `planche:3:2` — planche 3, deuxième ligne (d'ouest en est, ou du nord au sud). */
    public static function clePlanche(int $numero, int $ligne): string
    {
        return "planche:$numero:$ligne";
    }

    /**
     * Les lignes de service d'une planche (Phase 6) : une espèce par ligne (« une ligne, une espèce », Théry ch. 8),
     * toutes réécrites d'un bloc, sous la version de l'ensemble des lignes de la planche.
     *
     * @param array<int, int> $choix numéro de ligne (de 1) → identifiant d'espèce du référentiel ; 0 : la ligne reste vide
     * @return array{ok: bool, erreurs: array<string, string>}
     */
    public function enregistrerPlanche(array $parcelle, int $siteId, int $numero, array $choix, string $versionRecue): array
    {
        $parcelleId = (int) $parcelle['id'];
        $nbPlanches = (int) ($parcelle['nb_planches'] ?? 0);
        if ($numero < 1 || $numero > $nbPlanches) {
            return ['ok' => false, 'erreurs' => ['planche' => 'Cette planche n’existe pas au plan.']];
        }
        $n = \Syntropia\Planches::nombreDeLignes(json_decode((string) ($parcelle['plan'] ?? ''), true), $numero);
        $places = [];
        foreach ($choix as $ligne => $id) {
            $ligne = (int) $ligne;
            if ($ligne < 1 || $ligne > $n) {
                return ['ok' => false, 'erreurs' => ['ligne' => 'La planche ' . $numero . ' compte ' . $n . ' lignes.']];
            }
            if ((int) $id === 0) {
                continue;
            }
            $e = $this->especes()->parId((int) $id);
            if ($e === null || !in_array((int) $e['site_id'], [0, $siteId], true)) {
                return ['ok' => false, 'erreurs' => ['serie' => 'Une espèce choisie n’est pas dans le référentiel de ce projet.']];
            }
            $places[$ligne] = [(string) $e['nom_scientifique'], (string) $e['variete']];
        }
        $prefixe = 'planche:' . $numero . ':';
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $req = $pdo->prepare('SELECT * FROM ligne_plantation WHERE parcelle_id = ? AND emplacement LIKE ? ORDER BY emplacement, rang FOR UPDATE');
            $req->execute([$parcelleId, $prefixe . '%']);
            $anciennes = $req->fetchAll();
            if (!hash_equals(self::versionPlanche($anciennes), $versionRecue)) {
                $pdo->rollBack();
                return ['ok' => false, 'erreurs' => ['version' => 'Les lignes de cette planche ont changé depuis que vous les avez ouvertes : rechargez la page, puis refaites votre choix.']];
            }
            if (($refus = $this->horsPalette($parcelleId, array_values($places), $anciennes)) !== null) {
                $pdo->rollBack();
                return ['ok' => false, 'erreurs' => ['serie' => $refus]];
            }
            $pdo->prepare('DELETE FROM ligne_plantation WHERE parcelle_id = ? AND emplacement LIKE ?')->execute([$parcelleId, $prefixe . '%']);
            $ins = $pdo->prepare('INSERT INTO ligne_plantation (parcelle_id, emplacement, rang, nom_scientifique, variete) VALUES (?, ?, 1, ?, ?)');
            foreach ($places as $ligne => [$nom, $variete]) {
                $ins->execute([$parcelleId, self::clePlanche($numero, $ligne), $nom, $variete]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        return ['ok' => true, 'erreurs' => []];
    }

    /** @param list<array<string, mixed>> $lignes toutes les lignes d'une planche */
    public static function versionPlanche(array $lignes): string
    {
        $cles = array_map(static fn (array $l): array => [(string) $l['emplacement'], (string) $l['nom_scientifique'], (string) $l['variete']], $lignes);
        return hash('sha256', json_encode($cles, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /**
     * Les lignes de service de chaque planche d'une parcelle, résolues au référentiel et comptées sur la longueur de la
     * planche (Phase 6).
     *
     * @return array<int, array{version: string, lignes: array<int, ?array{nom: string, espece: ?array, espacement: ?float, compte: ?int}>}>
     *         numéro de planche → ses lignes (de 1), `null` pour une ligne vide
     */
    public function etatPlanches(array $parcelle, int $siteId): array
    {
        $series = $this->dUneParcelle((int) $parcelle['id']);
        $out = [];
        foreach (\Syntropia\Planches::dUneParcelle($parcelle) as $p) {
            $brutes = [];
            $lignes = [];
            foreach ($p['lignes'] as $l) {
                $cle = self::clePlanche($p['numero'], $l['indice'] + 1);
                $place = $series[$cle][0] ?? null;
                if ($place === null) {
                    $lignes[$l['indice'] + 1] = null;
                    continue;
                }
                $brutes[] = $place;
                $e = $this->especes()->parNom((string) $place['nom_scientifique'], (string) $place['variete'], $siteId);
                $esp = Espacement::deService($e);
                $compte = $esp !== null ? Bordures::compter($p['longueur'], [$esp]) : null;
                $lignes[$l['indice'] + 1] = ['nom' => $e !== null ? (string) ($e['nom_usuel'] ?: $e['nom_scientifique']) : (string) $place['nom_scientifique'],
                    'espece' => $e, 'espacement' => $esp, 'compte' => $compte !== null && $compte['ok'] ? $compte['total'] : null];
            }
            // Des lignes enregistrées au-delà du nombre actuel de la planche comptent dans la version (on les efface en réécrivant).
            foreach ($series as $cle => $places) {
                if (str_starts_with($cle, 'planche:' . $p['numero'] . ':') && !in_array($places[0], $brutes, true)) {
                    $brutes[] = $places[0];
                }
            }
            usort($brutes, static fn (array $a, array $b): int => [$a['emplacement'], $a['rang']] <=> [$b['emplacement'], $b['rang']]);
            $out[$p['numero']] = ['version' => self::versionPlanche($brutes), 'lignes' => $lignes, 'longueur' => $p['longueur']];
        }
        return $out;
    }

    /**
     * Ce que le plan affiche d'une parcelle : pour chaque ligne possible (côté actif) ou enregistrée (même d'un côté
     * devenu inactif), sa série résolue au référentiel du site, et le compte.
     *
     * @param array<string, mixed> $parcelle
     * @return array<string, array<string, mixed>>
     */
    public function etat(array $parcelle, int $siteId): array
    {
        $geometrie = Bordures::lignes($parcelle);
        $series = $this->dUneParcelle((int) $parcelle['id']);
        $cles = array_unique(array_merge(array_keys($geometrie),
            array_filter(array_keys($series), static fn (string $c): bool => Bordures::depuisCle($c) !== null)));
        $cles = array_values(array_intersect(Bordures::cles(), $cles));

        $out = [];
        foreach ($cles as $cle) {
            $serie = $series[$cle] ?? [];
            $places = [];
            foreach ($serie as $l) {
                $e = $this->especes()->parNom((string) $l['nom_scientifique'], (string) $l['variete'], $siteId);
                $esp = Espacement::deService($e);
                $places[] = ['nom_scientifique' => (string) $l['nom_scientifique'], 'variete' => (string) $l['variete'],
                    'origine' => $e !== null ? (string) ($e['origine'] ?? '') : '',
                    'nom' => $e !== null ? (string) ($e['nom_usuel'] ?: $e['nom_scientifique']) : (string) $l['nom_scientifique'],
                    'connue' => $e !== null, 'espacement' => $esp, 'id' => $e !== null ? (int) $e['id'] : null,
                    // L'envergure adulte, pour dessiner le plant à sa taille réelle (schéma 17) ; `null` si inconnue.
                    'largeur' => $e !== null && ($e['largeur_m'] ?? null) !== null ? (float) $e['largeur_m'] : null];
            }
            $geo = $geometrie[$cle] ?? null;
            $compte = $geo !== null && $places !== []
                ? Bordures::compter($geo['longueur'], array_column($places, 'espacement')) : null;
            $out[$cle] = ['geometrie' => $geo, 'places' => $places, 'compte' => $compte, 'version' => self::version($serie)];
        }
        return $out;
    }
}
