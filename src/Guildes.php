<?php

declare(strict_types=1);

namespace Syntropia;

/**
 * Les guildes de Plantae — ce dont une plante a besoin, ce qu'elle rend aux autres.
 *
 * Pour un verger ou un jardin-forêt, l'association EST la guilde ou le consortium, le cœur de Plantae (le
 * porteur, 2026-09-20, `chantier-associations.md`). Lit `contenu/guildes-plantae.json`, un **instantané** de
 * `guilde.csv` exporté par `bin/catalogue-plantae.py` : une ligne par (plante, catégorie, relation), la relation
 * étant **« Service rendu »** ou **« Besoin requis »**. Sept catégories : azote, protection contre le vent, matière
 * organique, attraction des auxiliaires, attraction des pollinisateurs, limitation de la concurrence herbacée, ombrage.
 * Pur : aucune base.
 *
 * **Ce que ce code ne fait pas** : reclasser. La donnée est celle de Plantae, clé = le nom scientifique du catalogue
 * (celui du scénario). Il vieillit : à réexporter quand Plantae change.
 *
 * **Ce qu'il sait dire, et ce qu'il tait** : pour un ensemble de plantes, quels besoins un autre membre couvre par
 * un service, lesquels restent sans réponse, et **quelles plantes n'ont pas de guilde connue** (Plantae n'en a que
 * pour 179 des 1271 plantes du catalogue) — ce qui ne veut pas dire qu'elles n'ont pas de besoins. Il signale aussi
 * les catégories où Plantae ne connaît **aucun service** (la matière organique n'a que des besoins) : un besoin y est
 * « sans service connu », non « non couvert ».
 */
final class Guildes
{
    public const SERVICE = 'Service rendu';
    public const BESOIN = 'Besoin requis';

    private const FICHIER = __DIR__ . '/../contenu/guildes-plantae.json';

    /** @var array<string, array<string, list<array<string, mixed>>>> */
    private static array $cache = [];

    /** Un nom scientifique comparable : sans casse ni espaces en trop. */
    public static function cle(string $nom): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $nom)));
    }

    /**
     * @return array<string, list<array<string, mixed>>> nom scientifique (minuscules) → ses lignes de guilde
     */
    public static function plantes(?string $fichier = null): array
    {
        $fichier ??= self::FICHIER;
        if (isset(self::$cache[$fichier])) {
            return self::$cache[$fichier];
        }
        $plantes = [];
        if (is_readable($fichier)) {
            $json = json_decode((string) file_get_contents($fichier), true);
            foreach (is_array($json['plantes'] ?? null) ? $json['plantes'] : [] as $nom => $lignes) {
                if (is_array($lignes)) {
                    $plantes[self::cle((string) $nom)] = $lignes;
                }
            }
        }
        return self::$cache[$fichier] = $plantes;
    }

    /**
     * Ce que Plantae dit d'une plante : les catégories où elle rend un service, celles où elle a un besoin
     * (avec l'intensité, si elle est donnée). `null` si elle n'a pas de guilde connue — un nom en cultivar
     * (« Robinia pseudoacacia 'Frisia' ») se retrouve par son binôme.
     *
     * @return array{services: array<string, ?string>, besoins: array<string, ?string>}|null
     */
    public static function deLaPlante(string $nom, ?string $fichier = null): ?array
    {
        $plantes = self::plantes($fichier);
        $cle = self::cle($nom);
        $lignes = $plantes[$cle] ?? null;
        if ($lignes === null) {
            $mots = explode(' ', $cle);
            $lignes = count($mots) > 2 ? ($plantes[$mots[0] . ' ' . $mots[1]] ?? null) : null;
        }
        if ($lignes === null) {
            return null;
        }
        $sortie = ['services' => [], 'besoins' => []];
        foreach ($lignes as $l) {
            $cat = (string) ($l['categorie'] ?? '');
            $cle = ($l['relation'] ?? '') === self::SERVICE ? 'services' : (($l['relation'] ?? '') === self::BESOIN ? 'besoins' : null);
            if ($cat !== '' && $cle !== null) {
                $sortie[$cle][$cat] = isset($l['intensite']) ? (string) $l['intensite'] : null;
            }
        }
        return $sortie;
    }

    /** Les catégories où Plantae connaît au moins un service. */
    public static function categoriesAvecService(?string $fichier = null): array
    {
        $cats = [];
        foreach (self::plantes($fichier) as $lignes) {
            foreach ($lignes as $l) {
                if (($l['relation'] ?? '') === self::SERVICE) {
                    $cats[(string) $l['categorie']] = true;
                }
            }
        }
        return array_keys($cats);
    }

    /**
     * Le bilan d'un ensemble de plantes : catégorie par catégorie, qui a un besoin, qui rend le service, quels
     * besoins un AUTRE membre couvre, lesquels restent sans réponse.
     *
     * @param list<string> $noms les noms scientifiques du scénario
     * @return array{categories: list<array<string, mixed>>, sans_guilde: list<string>}
     */
    public static function bilan(array $noms, ?string $fichier = null): array
    {
        $noms = array_values(array_unique(array_filter(array_map('trim', $noms), static fn (string $n): bool => $n !== '')));
        $fiches = [];
        $sansGuilde = [];
        foreach ($noms as $n) {
            $f = self::deLaPlante($n, $fichier);
            $f === null ? $sansGuilde[] = $n : $fiches[$n] = $f;
        }
        $avecService = self::categoriesAvecService($fichier);
        $categories = [];
        foreach ($fiches as $n => $f) {
            foreach (array_merge(array_keys($f['services']), array_keys($f['besoins'])) as $cat) {
                $categories[$cat] = true;
            }
        }
        $sortie = [];
        foreach (array_keys($categories) as $cat) {
            $services = array_keys(array_filter($fiches, static fn (array $f): bool => array_key_exists($cat, $f['services'])));
            $besoins = array_keys(array_filter($fiches, static fn (array $f): bool => array_key_exists($cat, $f['besoins'])));
            $couverts = [];
            $manques = [];
            foreach ($besoins as $b) {
                // Un besoin est couvert par le service d'un AUTRE membre : une plante ne se rend pas service à elle-même.
                count(array_diff($services, [$b])) > 0 ? $couverts[] = $b : $manques[] = $b;
            }
            $sortie[] = ['categorie' => $cat, 'services' => $services, 'besoins' => $besoins, 'couverts' => $couverts,
                'manques' => $manques, 'sans_service_connu' => !in_array($cat, $avecService, true)];
        }
        usort($sortie, static fn (array $a, array $b): int => strcmp($a['categorie'], $b['categorie']));
        return ['categories' => $sortie, 'sans_guilde' => $sansGuilde];
    }
}
