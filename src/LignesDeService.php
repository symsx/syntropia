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
 * **En tortue liane** (Théry, ch. 15 § 9 : « plus besoin de maïs ni de tournesols, sauf éventuellement les deux premières
 * années, le temps que les plantes vivaces grimpantes se mettent en place ») : la ligne centrale garde sa biomasse **les deux
 * premières années** après le début de la plantation, pour assurer le saut du chat (décision du porteur, 2026-09-30) ;
 * passé ce temps, elle n'en porte plus, et la strate émergente, tenue par les lianes de la structure, n'est plus à
 * compléter sur la planche.
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
     * @param array{installee: bool, jusqu: ?string}|null $liane en tortue liane : les deux premières années sont-elles
     *        passées, et jusqu'à quand (date lisible) elles courent — `null` pour une autre tortue (`self::liane()`)
     * @param bool $miroir la planche est la demi-planche du bord est ou sud (`Planches::estDemiPlancheDeFin()`) : sa
     *        ligne centrale est du côté de la bordure
     * @return list<array{indice: int, centrale: bool, strate: ?string, raison: string, candidats: list<array<string, mixed>>, proposee: ?array<string, mixed>}>
     */
    public static function proposer(int $n, array $stratesProductions, array $especes, ?array $naturePlanche = null, ?array $liane = null, bool $miroir = false): array
    {
        if ($n < 1) {
            return [];
        }
        $installee = $liane !== null && $liane['installee'];
        if ($installee) {
            // Les lianes de la structure tiennent la strate émergente : elle n'est plus à compléter sur la planche.
            $stratesProductions[] = 'emergente';
        }
        $centrale = Planches::centrale($n, $miroir);
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
        usort($biomasse, static fn (array $a, array $b): int => [!in_array('emergente', self::stratesService($a), true), Planches::natureDuCycle($a['cycle'] ?? null) !== $natureC, $a['espacement_retenu_m'] === null]
            <=> [!in_array('emergente', self::stratesService($b), true), Planches::natureDuCycle($b['cycle'] ?? null) !== $natureC, $b['espacement_retenu_m'] === null]);
        $strateCentrale = $biomasse !== [] && in_array('emergente', self::stratesService($biomasse[0]), true) ? 'emergente' : null;
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
            if ($i === $centrale && $installee) {
                $out[] = ['indice' => $i, 'centrale' => true, 'strate' => null, 'candidats' => [], 'proposee' => null,
                    'raison' => 'Tortue liane : les deux premières années sont passées' . ($liane['jusqu'] !== null ? ' (depuis le ' . $liane['jusqu'] . ')' : '')
                        . ' — les lianes de la structure ont pris le relais : plus de maïs ni de tournesol (Théry, ch. 15 § 9).'];
                continue;
            }
            if ($i === $centrale) {
                $raison = $biomasse === []
                    ? 'La ligne centrale porte la biomasse : aucune espèce du référentiel n’a ce rôle.'
                    : 'La ligne centrale porte la biomasse et la structure — jamais de tuteurs.';
                if ($liane !== null) {
                    $raison .= ' Tortue liane : elle assure le saut du chat les deux premières années seulement, le temps que les'
                        . ' lianes s’installent (Théry, ch. 15 § 9)'
                        . ($liane['jusqu'] !== null ? ' — jusqu’au ' . $liane['jusqu'] . '.' : ' — notez la date de plantation (Suivi, saut du chat) pour que l’atelier sache jusqu’à quand.');
                }
                $out[] = ['indice' => $i, 'centrale' => true, 'strate' => $strateCentrale, 'candidats' => $biomasse,
                    'proposee' => self::premiere($biomasse), 'raison' => $raison];
                continue;
            }
            $s = $strateDe[$i];
            if ($s === null) {
                $out[] = ['indice' => $i, 'centrale' => false, 'strate' => null, 'candidats' => [], 'proposee' => null,
                    'raison' => 'Plus de strate vide à compléter sur cette planche.'];
                continue;
            }
            $candidats = $trier(array_values(array_filter($especes, static fn (array $e): bool =>
                in_array($s, self::stratesService($e), true) && array_diff(self::roles($e), self::PAS_DE_SERVICE) !== []
                // Tortue liane installée : « une succession potagère sans biomasse 3D » sous la structure — plus de biomasse
                // annuelle (maïs, tournesol) sur aucune ligne.
                && !($installee && in_array('biomasse', self::roles($e), true) && Planches::natureDuCycle($e['cycle'] ?? null) === 'annuelle'))), $i);
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

    /** Combien d'années la biomasse de la ligne centrale d'une tortue liane assure le démarrage (Théry, ch. 15 § 9). */
    public const ANNEES_BIOMASSE_LIANE = 2;

    /**
     * L'état d'une tortue liane pour `proposer()` : les deux premières années courent depuis le début de la campagne de
     * plantation (`parcelle.date_plantation`) ; sans date, elles ne sont pas passées. `null` pour une autre tortue.
     *
     * @param array<string, mixed> $parcelle
     * @return array{installee: bool, jusqu: ?string}|null
     */
    public static function liane(array $parcelle, \DateTimeImmutable $aujourdhui): ?array
    {
        if (($parcelle['tortue'] ?? null) !== 'liane') {
            return null;
        }
        $debut = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($parcelle['date_plantation'] ?? ''));
        if ($debut === false) {
            return ['installee' => false, 'jusqu' => null];
        }
        $fin = $debut->modify('+' . self::ANNEES_BIOMASSE_LIANE . ' years');
        return ['installee' => $aujourdhui >= $fin, 'jusqu' => $fin->format('d/m/Y')];
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

    /**
     * Les strates où une espèce rend son service (biomasse, bloque-herbe) : sa strate de service si le référentiel la dit
     * (`espece.strate_service`, Théry ch. 05, schéma 18), sinon toutes ses strates possibles.
     *
     * @return list<string>
     */
    public static function stratesService(array $e): array
    {
        $dite = (string) ($e['strate_service'] ?? '');
        return isset(Strates::REPERES[$dite]) ? [$dite] : self::strates($e);
    }

    /** Les strates d'une espèce cultivée pour sa récolte : sa strate de production si elle est dite, sinon toutes. */
    public static function stratesProduction(array $e): array
    {
        $dite = (string) ($e['strate_production'] ?? '');
        return isset(Strates::REPERES[$dite]) ? [$dite] : self::strates($e);
    }
}
