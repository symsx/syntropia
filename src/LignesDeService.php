<?php

declare(strict_types=1);

namespace Syntropia;

/**
 * Proposer les plantes de service des lignes d'une planche. Pur.
 *
 * Voir `chantier-peuplement-parcelle.md`, Phase 6, et `skills-map/nodes/conception-de-la-tortue.md`. Ce que le porteur a
 * décidé (2026-09-23) : **les lignes d'une planche portent les plantes de service ; les productions se plantent de part
 * et d'autre** (Joala, module 5 : « une tranchée centrale… cultures de production de part et d'autre ») ; la règle,
 * première version validée : **compléter les strates vides de la planche, la biomasse sur la ligne centrale**. S'y
 * ajoutent, sourcés : **une ligne, une espèce** (Théry, ch. 8 § 8) ; **la forme pyramidale** — le plus haut au centre,
 * les strates descendent vers les bords (ch. 8 § 5 ; Joala : bordures en cultures basses) : les strates à compléter se
 * posent donc de la ligne la plus proche du centre vers les bords, de la plus haute à la plus basse.
 *
 * **La nature de chaque ligne** (`natureDeLigne()`) fait passer d'abord les espèces du cycle qui lui convient — une
 * préférence, jamais une exclusion : une planche potagère ordinaire est annuelle ; en tortue zèbre, la ligne prend la
 * nature de sa planche ; en tortue abeille, celle de la bande où elle tombe, et la ligne médiane, qui porte maïs ou
 * tournesol la première année (Théry, ch. 15 § 8), est annuelle.
 *
 * Ce que la règle ne sait pas faire, et qui se dit : choisir entre deux espèces d'une même strate (la liste est donnée,
 * la première ayant un espacement est proposée) ; une production dont la strate n'est pas connue (ni saisie, ni au
 * référentiel) ne compte pas — la planche peut sembler avoir plus de strates vides qu'elle n'en a.
 */
final class LignesDeService
{
    /** Les strates d'un potager, de la plus haute à la plus basse (`Cultures::STRATES`, syntropie). */
    public const STRATES = ['emergente', 'haute', 'moyenne', 'basse'];

    /** Comment on les écrit. */
    public const LIBELLES = ['emergente' => 'émergente', 'haute' => 'haute', 'moyenne' => 'moyenne', 'basse' => 'basse'];

    /** Les fonctions qui ne sont pas de service : l'espèce qui n'a que celle-là ne va pas sur une ligne de service. */
    private const PAS_DE_SERVICE = ['production'];

    /**
     * @param int $n le nombre de lignes de la planche
     * @param list<string> $stratesProductions les strates des productions posées sur la planche (connues seulement)
     * @param list<array<string, mixed>> $especes le référentiel du site (`Especes::toutes()`)
     * @return list<array{indice: int, centrale: bool, strate: ?string, raison: string, candidats: list<array<string, mixed>>, proposee: ?array<string, mixed>}>
     */
    public static function proposer(int $n, array $stratesProductions, array $especes, ?array $naturePlanche = null): array
    {
        if ($n < 1) {
            return [];
        }
        $centrale = Planches::centrale($n);
        // Les espèces du cycle de la ligne d'abord (tri stable) — une préférence, non un filtre.
        $trier = static function (array $liste, int $indice) use ($n, $naturePlanche): array {
            $nature = self::natureDeLigne($indice, $n, $naturePlanche);
            usort($liste, static fn (array $a, array $b): int =>
                (Planches::natureDuCycle($a['cycle'] ?? null) !== $nature) <=> (Planches::natureDuCycle($b['cycle'] ?? null) !== $nature));
            return $liste;
        };
        $vides = array_values(array_diff(self::STRATES, $stratesProductions));

        // La ligne centrale : la biomasse, émergente de préférence (Théry ch. 8 ; Joala 5-5, 5-6).
        $biomasse = array_values(array_filter($especes, static fn (array $e): bool => in_array('biomasse', self::roles($e), true)));
        $natureC = self::natureDeLigne($centrale, $n, $naturePlanche);
        usort($biomasse, static fn (array $a, array $b): int => [!in_array('emergente', self::strates($a), true), Planches::natureDuCycle($a['cycle'] ?? null) !== $natureC, $a['espacement_retenu_m'] === null]
            <=> [!in_array('emergente', self::strates($b), true), Planches::natureDuCycle($b['cycle'] ?? null) !== $natureC, $b['espacement_retenu_m'] === null]);
        $strateCentrale = $biomasse !== [] && in_array('emergente', self::strates($biomasse[0]), true) ? 'emergente' : null;
        $vides = array_values(array_diff($vides, $strateCentrale !== null ? [$strateCentrale] : []));

        // Les autres lignes, de la plus proche du centre à la plus éloignée ; les strates vides, de la plus haute à la plus basse.
        $autres = array_values(array_diff(range(0, $n - 1), [$centrale]));
        usort($autres, static fn (int $a, int $b): int => [abs($a - $centrale), $a] <=> [abs($b - $centrale), $b]);
        $strateDe = [];
        foreach ($autres as $k => $indice) {
            $strateDe[$indice] = $vides[$k] ?? null;
        }

        $out = [];
        for ($i = 0; $i < $n; $i++) {
            if ($i === $centrale) {
                $out[] = ['indice' => $i, 'centrale' => true, 'strate' => $strateCentrale, 'candidats' => $biomasse,
                    'proposee' => self::premiere($biomasse),
                    'raison' => $biomasse === []
                        ? 'La ligne centrale porte la biomasse : aucune espèce du référentiel n’a ce rôle.'
                        : 'La ligne centrale porte la biomasse et la structure — jamais de tuteurs.'];
                continue;
            }
            $s = $strateDe[$i];
            if ($s === null) {
                $out[] = ['indice' => $i, 'centrale' => false, 'strate' => null, 'candidats' => [], 'proposee' => null,
                    'raison' => 'Plus de strate vide à compléter sur cette planche.'];
                continue;
            }
            $candidats = $trier(array_values(array_filter($especes, static fn (array $e): bool =>
                in_array($s, self::strates($e), true) && array_diff(self::roles($e), self::PAS_DE_SERVICE) !== [])), $i);
            $out[] = ['indice' => $i, 'centrale' => false, 'strate' => $s, 'candidats' => $candidats,
                'proposee' => self::premiere($candidats),
                'raison' => $candidats === []
                    ? 'Strate ' . self::LIBELLES[$s] . ' vide : aucune plante de service de cette strate au référentiel.'
                    : 'Strate ' . self::LIBELLES[$s] . ' vide sur la planche : une plante de service la complète.'];
        }
        return $out;
    }

    /**
     * La nature d'une ligne (d'indice 0 à `$n - 1`) : `$naturePlanche` est l'entrée de `Planches::natures()` pour cette
     * planche, ou `null` pour une planche potagère ordinaire (annuelle).
     */
    public static function natureDeLigne(int $indice, int $n, ?array $naturePlanche): string
    {
        if ($naturePlanche === null) {
            return 'annuelle';
        }
        if ($naturePlanche['bandes'] !== null) {
            $c = Planches::centrale($n);
            return $n % 2 === 1 && $indice === $c ? 'annuelle' : ($indice <= ($n - 1) / 2 ? $naturePlanche['bandes'][0] : $naturePlanche['bandes'][1]);
        }
        return (string) ($naturePlanche['nature'] ?? 'annuelle');
    }

    /** La première espèce ayant un espacement (elle se compte), sinon la première tout court. */
    private static function premiere(array $candidats): ?array
    {
        foreach ($candidats as $e) {
            if ($e['espacement_retenu_m'] !== null) {
                return $e;
            }
        }
        return $candidats[0] ?? null;
    }

    /** @return list<string> */
    private static function roles(array $e): array
    {
        return array_values(array_filter(explode('|', (string) ($e['role'] ?? ''))));
    }

    /** @return list<string> */
    public static function strates(array $e): array
    {
        return array_values(array_filter(explode('|', (string) ($e['strates_possibles'] ?? ''))));
    }
}
