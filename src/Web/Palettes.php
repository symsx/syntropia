<?php

declare(strict_types=1);

namespace Syntropia\Web;

use PDO;
use RuntimeException;
use Syntropia\Database;
use Syntropia\Palette;

/**
 * Lire et écrire la palette végétale d'une parcelle (`palette`). Chantier `chantier-palette-vegetale.md`, Phase 1.
 *
 * Une ligne par espèce : sa place et ses fonctions de service. Les gestes :
 * - choisir une production (principale ou secondaire) : l'espèce y prend sa place, ou y passe si elle était ailleurs ;
 * - choisir une plante pour une fonction : la fonction s'ajoute à l'espèce, qui entre comme plante de service si elle
 *   n'était pas encore dans la palette ;
 * - retirer : une production qui rend encore un service reste comme plante de service ; une plante de service sans
 *   plus aucune fonction sort de la palette ;
 * - reprendre les plantes de service d'une autre parcelle du même projet.
 *
 * L'espèce choisie se vérifie contre le référentiel du site (`Especes::toutes()`) ; la parcelle, contre le site.
 * Toute erreur à montrer lève `RuntimeException`, avec un message à lire tel quel.
 */
final class Palettes
{
    public function __construct(private ?PDO $pdo = null) {}

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::syntropia();
    }

    /** @return list<array<string, mixed>> les lignes, dans l'ordre où elles ont été choisies */
    public function dUneParcelle(int $parcelleId): array
    {
        $req = $this->pdo()->prepare('SELECT * FROM palette WHERE parcelle_id = ? ORDER BY id');
        $req->execute([$parcelleId]);
        return $req->fetchAll();
    }

    /**
     * Les espèces de la palette, telles que le référentiel du site les décrit, chacune avec sa ligne de palette
     * (`palette` : id, place, fonctions). C'est la liste où le plan, le scénario et le potager puisent (Phase 4). Une ligne
     * dont l'espèce n'est plus au référentiel est laissée de côté.
     *
     * @return list<array<string, mixed>>
     */
    public function especes(int $parcelleId, int $siteId): array
    {
        $parCle = [];
        foreach ((new Especes($this->pdo()))->toutes($siteId) as $e) {
            $parCle[self::cle($e)] = $e;
        }
        $especes = [];
        foreach ($this->dUneParcelle($parcelleId) as $l) {
            if (isset($parCle[self::cle($l)])) {
                $especes[] = $parCle[self::cle($l)] + ['palette' => $l];
            }
        }
        return $especes;
    }

    /** La clé d'une espèce : son nom scientifique (sans casse) et sa variété. */
    public static function cle(array $e): string
    {
        return mb_strtolower((string) $e['nom_scientifique']) . '|' . (string) $e['variete'];
    }

    /**
     * Où chaque espèce de la palette est employée : le plan (bordures, lignes des planches, ligne des lianes), le
     * scénario, le potager. Une espèce employée ne se retire pas de la palette (décision du porteur, 2026-10-05). Les
     * lignes du scénario et du potager se reconnaissent par leur nom (`Palette::trouver()`).
     *
     * @return array<string, list<string>> clé d'espèce → où elle sert (« le plan », « le scénario », « le potager »)
     */
    public function emplois(int $parcelleId, int $siteId): array
    {
        $especes = $this->especes($parcelleId, $siteId);
        $cles = array_flip(array_map([self::class, 'cle'], $especes));
        $emplois = [];
        $req = $this->pdo()->prepare('SELECT DISTINCT nom_scientifique, variete FROM ligne_plantation WHERE parcelle_id = ?');
        $req->execute([$parcelleId]);
        foreach ($req->fetchAll() as $l) {
            if (isset($cles[self::cle($l)])) {
                $emplois[self::cle($l)][] = 'le plan';
            }
        }
        foreach (['scenario_ligne' => 'le scénario', 'succession_ligne' => 'le potager'] as $table => $ou) {
            $req = $this->pdo()->prepare("SELECT DISTINCT espece FROM `$table` WHERE parcelle_id = ?");
            $req->execute([$parcelleId]);
            foreach ($req->fetchAll(PDO::FETCH_COLUMN) as $nom) {
                $e = Palette::trouver((string) $nom, $especes);
                if ($e !== null && !in_array($ou, $emplois[self::cle($e)] ?? [], true)) {
                    $emplois[self::cle($e)][] = $ou;
                }
            }
        }
        return $emplois;
    }

    /**
     * Combien de plantes de service chaque parcelle du site porte — pour proposer de les reprendre.
     *
     * @return array<int, int> parcelle_id → nombre
     */
    public function servicesParParcelle(int $siteId): array
    {
        $req = $this->pdo()->prepare(
            "SELECT p.parcelle_id, COUNT(*) FROM palette p JOIN parcelle a ON a.id = p.parcelle_id
              WHERE a.site_id = ? AND p.place = 'service' GROUP BY p.parcelle_id");
        $req->execute([$siteId]);
        return array_map('intval', $req->fetchAll(PDO::FETCH_KEY_PAIR));
    }

    /**
     * Choisit une espèce pour une place (`principale`, `secondaire`) ou pour une fonction de service.
     *
     * @return string ce qui s'est passé, à dire à la personne
     * @throws RuntimeException
     */
    public function choisir(int $parcelleId, int $siteId, int $especeId, string $place, ?string $fonction): string
    {
        $espece = $this->espece($especeId, $siteId);
        if (!isset(Palette::PLACES[$place]) || ($place === 'service') !== ($fonction !== null)
            || ($fonction !== null && !isset(Palette::FONCTIONS[$fonction]))) {
            throw new RuntimeException('Ce choix n’est pas reconnu.');
        }
        $nom = Palette::nom($espece);
        return $this->sousVerrou($parcelleId, $siteId, function (PDO $pdo) use ($parcelleId, $espece, $place, $fonction, $nom): string {
            $req = $pdo->prepare('SELECT * FROM palette WHERE parcelle_id = ? AND nom_scientifique = ? AND variete = ?');
            $req->execute([$parcelleId, $espece['nom_scientifique'], $espece['variete']]);
            $ligne = $req->fetch();
            if ($fonction !== null) {
                $libelle = Palette::FONCTIONS[$fonction]['libelle'];
                if ($ligne === false) {
                    $pdo->prepare('INSERT INTO palette (parcelle_id, nom_scientifique, variete, place, fonctions) VALUES (?, ?, ?, ?, ?)')
                        ->execute([$parcelleId, $espece['nom_scientifique'], $espece['variete'], 'service', $fonction]);
                    return "$nom entre dans la palette pour « $libelle ».";
                }
                $fonctions = Palette::fonctionsDe($ligne['fonctions']);
                if (in_array($fonction, $fonctions, true)) {
                    return "$nom est déjà dans la palette pour « $libelle ».";
                }
                $pdo->prepare('UPDATE palette SET fonctions = ? WHERE id = ?')
                    ->execute([Palette::fonctionsEnTexte([...$fonctions, $fonction]), $ligne['id']]);
                return "$nom rend aussi le service « $libelle ».";
            }
            $libelle = $place === 'principale' ? 'production principale' : 'production secondaire';
            if ($ligne === false) {
                $pdo->prepare('INSERT INTO palette (parcelle_id, nom_scientifique, variete, place) VALUES (?, ?, ?, ?)')
                    ->execute([$parcelleId, $espece['nom_scientifique'], $espece['variete'], $place]);
                return "$nom entre dans la palette comme $libelle.";
            }
            if ($ligne['place'] === $place) {
                return "$nom est déjà une $libelle de cette parcelle.";
            }
            $pdo->prepare('UPDATE palette SET place = ? WHERE id = ?')->execute([$place, $ligne['id']]);
            return "$nom devient une $libelle.";
        });
    }

    /**
     * Retire une espèce d'une place de production, ou d'une fonction de service (`$fonction`).
     *
     * @return string ce qui s'est passé
     * @throws RuntimeException
     */
    public function retirer(int $parcelleId, int $siteId, int $ligneId, ?string $fonction): string
    {
        if ($fonction !== null && !isset(Palette::FONCTIONS[$fonction])) {
            throw new RuntimeException('Ce choix n’est pas reconnu.');
        }
        $parNom = $this->nomsDuSite($siteId);
        $emplois = $this->emplois($parcelleId, $siteId);
        // Une espèce employée ne sort pas de la palette : elle peut changer de place ou perdre une fonction, pas partir.
        $sortir = static function (array $ligne, string $nom) use ($emplois): void {
            $ou = $emplois[self::cle($ligne)] ?? [];
            if ($ou !== []) {
                throw new RuntimeException("$nom est employée dans " . implode(' et ', $ou) . ' : elle reste dans la palette. '
                    . 'Retirez-la d’abord de ' . (count($ou) > 1 ? 'ces étapes' : 'cette étape') . '.');
            }
        };
        return $this->sousVerrou($parcelleId, $siteId, function (PDO $pdo) use ($parcelleId, $ligneId, $fonction, $parNom, $sortir): string {
            $req = $pdo->prepare('SELECT * FROM palette WHERE id = ? AND parcelle_id = ?');
            $req->execute([$ligneId, $parcelleId]);
            $ligne = $req->fetch();
            if ($ligne === false) {
                throw new RuntimeException('Cette plante n’est plus dans la palette. La page a été rechargée.');
            }
            $nom = $parNom[mb_strtolower($ligne['nom_scientifique']) . '|' . $ligne['variete']] ?? Palette::nom($ligne);
            $fonctions = Palette::fonctionsDe($ligne['fonctions']);
            if ($fonction !== null) {
                $reste = array_values(array_diff($fonctions, [$fonction]));
                if ($reste === [] && $ligne['place'] === 'service') {
                    $sortir($ligne, $nom);
                    $pdo->prepare('DELETE FROM palette WHERE id = ?')->execute([$ligneId]);
                    return "$nom sort de la palette.";
                }
                $pdo->prepare('UPDATE palette SET fonctions = ? WHERE id = ?')->execute([Palette::fonctionsEnTexte($reste), $ligneId]);
                return "$nom ne compte plus pour « " . Palette::FONCTIONS[$fonction]['libelle'] . ' ».';
            }
            if ($ligne['place'] === 'service') {
                throw new RuntimeException('Une plante de service se retire de chacune de ses fonctions.');
            }
            if ($fonctions !== []) {
                $pdo->prepare("UPDATE palette SET place = 'service' WHERE id = ?")->execute([$ligneId]);
                return "$nom n’est plus une production, mais reste dans la palette pour ses services.";
            }
            $sortir($ligne, $nom);
            $pdo->prepare('DELETE FROM palette WHERE id = ?')->execute([$ligneId]);
            return "$nom sort de la palette.";
        });
    }

    /**
     * Reprend les plantes de service d'une autre parcelle du même projet : chaque espèce qui n'est là-bas qu'une plante de
     * service y entre ici avec ses fonctions ; ses productions, propres à chaque parcelle, ne se reprennent pas. Une espèce
     * déjà présente garde sa place et gagne les fonctions qui lui manquaient.
     *
     * @return string ce qui s'est passé
     * @throws RuntimeException
     */
    public function reprendre(int $parcelleId, int $siteId, int $depuisId): string
    {
        if ($depuisId === $parcelleId || (new Parcelles($this->pdo()))->parId($depuisId, $siteId) === null) {
            throw new RuntimeException('Choisissez une autre parcelle de ce projet.');
        }
        $source = array_filter($this->dUneParcelle($depuisId),
            static fn (array $l): bool => $l['place'] === 'service' && Palette::fonctionsDe($l['fonctions']) !== []);
        return $this->sousVerrou($parcelleId, $siteId, function (PDO $pdo) use ($parcelleId, $source): string {
            $ici = [];
            foreach ($this->dUneParcelle($parcelleId) as $l) {
                $ici[mb_strtolower($l['nom_scientifique']) . '|' . $l['variete']] = $l;
            }
            $ajoutees = 0;
            $completees = 0;
            foreach ($source as $l) {
                $existante = $ici[mb_strtolower($l['nom_scientifique']) . '|' . $l['variete']] ?? null;
                if ($existante === null) {
                    $pdo->prepare('INSERT INTO palette (parcelle_id, nom_scientifique, variete, place, fonctions) VALUES (?, ?, ?, ?, ?)')
                        ->execute([$parcelleId, $l['nom_scientifique'], $l['variete'], 'service', Palette::fonctionsEnTexte(Palette::fonctionsDe($l['fonctions']))]);
                    $ajoutees++;
                    continue;
                }
                $avant = Palette::fonctionsDe($existante['fonctions']);
                $apres = Palette::fonctionsDe(implode('|', [...$avant, ...Palette::fonctionsDe($l['fonctions'])]));
                if ($apres !== $avant) {
                    $pdo->prepare('UPDATE palette SET fonctions = ? WHERE id = ?')->execute([Palette::fonctionsEnTexte($apres), $existante['id']]);
                    $completees++;
                }
            }
            if ($ajoutees === 0 && $completees === 0) {
                return 'Toutes ces plantes de service sont déjà dans la palette.';
            }
            return trim(($ajoutees > 0 ? $ajoutees . ' plante' . ($ajoutees > 1 ? 's' : '') . ' de service reprise' . ($ajoutees > 1 ? 's' : '') . '. ' : '')
                . ($completees > 0 ? $completees . ' plante' . ($completees > 1 ? 's' : '') . ' déjà présente' . ($completees > 1 ? 's' : '')
                    . ' gagne' . ($completees > 1 ? 'nt' : '') . ' des fonctions.' : ''));
        });
    }

    /**
     * Remplit la palette d'une parcelle depuis ce qu'elle porte déjà (Phase 6) — **seulement si sa palette est vide** : une
     * palette déjà composée n'est jamais touchée, et relancer ne change rien. Règles du porteur (2026-10-07) :
     * - une plante posée sur une ligne (bordure, ligne de planche, lianes) entre comme plante de service, avec les
     *   fonctions que son rôle au référentiel désigne ; sans aucune, avec celle de sa ligne : bordure externe →
     *   bloque-herbe, bordure interne et ligne de planche → biomasse (une liane sans fonction, cas non rencontré en
     *   production, entre en production secondaire) ;
     * - une ligne du scénario : production principale ou secondaire d'après sa production, sinon plante de service avec
     *   ses fonctions (à défaut, celles de son rôle au référentiel, puis la biomasse) ;
     * - une culture du potager : plante de service si elle a des fonctions de service, sinon production principale ;
     * - un nom du scénario ou du potager qui n'est pas une espèce du référentiel reste hors palette, et se signale.
     * Une espèce venue de plusieurs endroits garde la place la plus forte (principale, secondaire, service) et toutes
     * ses fonctions.
     *
     * @return array{vide: bool, entrees: list<array{nom: string, place: string, fonctions: list<string>, de: list<string>}>, hors: list<string>}
     */
    public function remplir(array $parcelle, int $siteId, bool $ecrire): array
    {
        $parcelleId = (int) $parcelle['id'];
        if ($this->dUneParcelle($parcelleId) !== []) {
            return ['vide' => false, 'entrees' => [], 'hors' => []];
        }
        $especesService = new Especes($this->pdo());
        $referentiel = $especesService->toutes($siteId);
        $parCle = [];
        foreach ($referentiel as $e) {
            $parCle[self::cle($e)] = $e;
        }
        $rang = ['principale' => 3, 'secondaire' => 2, 'service' => 1];
        $entrees = [];
        $hors = [];
        $poser = static function (array $e, string $place, array $fonctions, string $de) use (&$entrees, $rang): void {
            $cle = self::cle($e);
            $x = $entrees[$cle] ?? ['espece' => $e, 'nom' => Palette::nom($e), 'place' => $place, 'fonctions' => [], 'de' => []];
            if ($rang[$place] > $rang[$x['place']]) {
                $x['place'] = $place;
            }
            $x['fonctions'] = Palette::fonctionsDe(implode('|', [...$x['fonctions'], ...$fonctions]));
            if (!in_array($de, $x['de'], true)) {
                $x['de'][] = $de;
            }
            $entrees[$cle] = $x;
        };
        $rolesDe = static fn (?array $e): array => $e === null ? [] : array_map('trim', explode('|', (string) ($e['role'] ?? '')));

        $req = $this->pdo()->prepare('SELECT emplacement, nom_scientifique, variete FROM ligne_plantation WHERE parcelle_id = ? ORDER BY emplacement, rang');
        $req->execute([$parcelleId]);
        foreach ($req->fetchAll() as $l) {
            $e = $parCle[self::cle($l)] ?? null;
            if ($e === null) {
                $hors[] = Palette::nom($l);
                continue;
            }
            $cadre = explode(':', (string) $l['emplacement'])[0];
            $fonctions = Palette::depuisRoles($rolesDe($e));
            if ($fonctions === [] && $cadre === 'liane') {
                $poser($e, 'secondaire', [], 'la ligne des lianes');
                continue;
            }
            if ($fonctions === []) {
                $fonctions = [$cadre === 'externe' ? 'bloque_herbe' : 'biomasse'];
            }
            $poser($e, 'service', $fonctions, $cadre === 'planche' ? 'les lignes des planches' : ($cadre === 'liane' ? 'la ligne des lianes' : 'les bordures'));
        }

        $req = $this->pdo()->prepare('SELECT espece, details FROM scenario_ligne WHERE parcelle_id = ? ORDER BY ordre, id');
        $req->execute([$parcelleId]);
        foreach ($req->fetchAll() as $l) {
            $e = $especesService->parNomUsage((string) $l['espece'], $siteId);
            if ($e === null) {
                $hors[] = (string) $l['espece'];
                continue;
            }
            $d = \Syntropia\Scenario::details($l);
            $fonctions = Palette::depuisRoles(is_array($d['fonctions'] ?? null) ? $d['fonctions'] : []);
            $priorite = is_array($d['production'] ?? null) ? (string) ($d['production']['priorite'] ?? '') : '';
            if (in_array($priorite, ['principale', 'secondaire'], true)) {
                $poser($e, $priorite, $fonctions, 'le scénario');
                continue;
            }
            $fonctions = $fonctions ?: (Palette::depuisRoles($rolesDe($e)) ?: ['biomasse']);
            $poser($e, 'service', $fonctions, 'le scénario');
        }

        $req = $this->pdo()->prepare('SELECT espece, role FROM succession_ligne WHERE parcelle_id = ? ORDER BY id');
        $req->execute([$parcelleId]);
        foreach ($req->fetchAll() as $l) {
            $e = $especesService->parNomUsage((string) $l['espece'], $siteId);
            if ($e === null) {
                $hors[] = (string) $l['espece'];
                continue;
            }
            $fonctions = Palette::depuisRoles(\Syntropia\Scenario::fonctionsDe((string) ($l['role'] ?? '')));
            $fonctions === [] ? $poser($e, 'principale', [], 'le potager') : $poser($e, 'service', $fonctions, 'le potager');
        }

        $hors = array_values(array_unique($hors));
        if ($ecrire && $entrees !== []) {
            $this->sousVerrou($parcelleId, $siteId, function (PDO $pdo) use ($parcelleId, $entrees): string {
                $req = $pdo->prepare('SELECT COUNT(*) FROM palette WHERE parcelle_id = ?');
                $req->execute([$parcelleId]);
                if ((int) $req->fetchColumn() > 0) {
                    return '';
                }
                $ins = $pdo->prepare('INSERT INTO palette (parcelle_id, nom_scientifique, variete, place, fonctions) VALUES (?, ?, ?, ?, ?)');
                foreach ($entrees as $x) {
                    $ins->execute([$parcelleId, $x['espece']['nom_scientifique'], $x['espece']['variete'], $x['place'],
                        Palette::fonctionsEnTexte($x['fonctions'])]);
                }
                return '';
            });
        }
        return ['vide' => true, 'hors' => $hors, 'entrees' => array_values(array_map(
            static fn (array $x): array => ['nom' => $x['nom'], 'place' => $x['place'], 'fonctions' => $x['fonctions'], 'de' => $x['de']], $entrees))];
    }

    /**
     * L'écriture, sous le verrou de la ligne `parcelle` : deux gestes simultanés sur la même palette se suivent au lieu
     * de se croiser.
     *
     * @param callable(PDO): string $ecrire
     */
    private function sousVerrou(int $parcelleId, int $siteId, callable $ecrire): string
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $req = $pdo->prepare('SELECT id FROM parcelle WHERE id = ? AND site_id = ? FOR UPDATE');
            $req->execute([$parcelleId, $siteId]);
            if ($req->fetchColumn() === false) {
                throw new RuntimeException('Cette parcelle n’appartient pas à ce projet.');
            }
            $message = $ecrire($pdo);
            $pdo->commit();
            return $message;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** @return array<string, mixed> l'espèce du référentiel de ce site */
    private function espece(int $especeId, int $siteId): array
    {
        foreach ((new Especes($this->pdo()))->toutes($siteId) as $e) {
            if ((int) $e['id'] === $especeId) {
                return $e;
            }
        }
        throw new RuntimeException('Choisissez une espèce de la liste.');
    }

    /** @return array<string, string> « nom_scientifique|variete » → nom affiché */
    private function nomsDuSite(int $siteId): array
    {
        $noms = [];
        foreach ((new Especes($this->pdo()))->toutes($siteId) as $e) {
            $noms[mb_strtolower((string) $e['nom_scientifique']) . '|' . $e['variete']] = Palette::nom($e);
        }
        return $noms;
    }
}
