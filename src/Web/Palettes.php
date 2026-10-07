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
        return $this->sousVerrou($parcelleId, $siteId, function (PDO $pdo) use ($parcelleId, $ligneId, $fonction, $parNom): string {
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
