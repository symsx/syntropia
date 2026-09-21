<?php

declare(strict_types=1);

namespace Syntropia;

/**
 * La succession des cultures d'une planche — pur, sans base.
 *
 * Voir `chantier-plan-de-culture.md`, Phase 5. Une planche ne porte pas seulement des cultures qui se
 * côtoient (les voisinages, Phase 2) : elle en porte aussi **l'une après l'autre**. Ce que l'atelier sait de cet
 * enchaînement vient d'une seule source, la table de succession de Weinrich (`contenu/associations/associations-
 * succession.csv`, 30 plantes : ce qui les précède, ce qui les suit ou l'engrais vert) — des ordres de conduite d'Allemagne
 * moyenne, **non des preuves**, et non symétriques : un enchaînement est lu dès que l'une des deux plantes le cite.
 *
 * **Trois verdicts, jamais un quatrième.** `conseille` : la table le cite (ou n'exclut pas ce que l'autre autorise) ;
 * `deconseille` : la table l'exclut expressément (l'épinard s'accommode de presque toutes les plantes, « sauf bette et
 * betterave rouge ») ; `sans_repere` : elle n'en dit rien — **ce qui ne veut pas dire neutre**, et l'atelier ne le
 * range ni parmi les bons ni parmi les mauvais.
 *
 * **Ce qu'il ne fait pas** : deviner une famille botanique (deux plantes de la même famille se suivent mal, dit la
 * formation Joala pour fèves-pois puis haricots — mais la table de Weinrich ne porte pas de familles, et une règle sans
 * source dans le dépôt ne se code pas), ni imposer un ordre. Les trois temps du module 5 (pré-culture, culture principale,
 * post-culture) se lisent dans les dates de la planche, non dans une colonne de plus.
 */
final class Succession
{
    public const CONSEILLE = 'conseille';
    public const DECONSEILLE = 'deconseille';
    public const SANS_REPERE = 'sans_repere';

    /** Ce que la table appelle d'un mot pour plusieurs plantes. */
    private const GROUPES = [
        'legumineuses' => ['feve', 'feverole', 'pois', 'petit pois', 'haricot', 'lentille', 'pois chiche', 'trefle', 'lupin', 'luzerne'],
        'salade' => ['salade', 'laitue', 'batavia', 'romaine'],
    ];

    /** Des précisions qui ne changent pas la plante : « carotte primeur » est une carotte. */
    private const QUALIFICATIFS = ['primeur', 'precoce', 'tardif', 'tardive', 'd hiver', 'd automne', 'de printemps', 'a couper', 'pommee', 'rouge', 'nain', 'a rames', 'd ete'];

    /** Le nom réduit à la plante : sans accent, sans parenthèse, sans qualificatif. */
    private static function racine(string $nom): string
    {
        $nom = (string) preg_replace('/\(.*?\)/u', ' ', $nom);
        $nom = Associations::normaliser($nom);
        foreach (self::QUALIFICATIFS as $q) {
            $nom = (string) preg_replace('/(^| )' . preg_quote($q, '/') . '( |$)/u', ' ', $nom);
        }
        $nom = trim((string) preg_replace('/\s+/u', ' ', $nom));
        return $nom;
    }

    /** Une plante, ou son pluriel, est-elle ce nom de la table (ou l'un des mots d'un groupe) ? */
    private static function correspond(string $plante, string $mot): bool
    {
        $p = self::racine($plante);
        $m = self::racine($mot);
        if ($p === '' || $m === '') {
            return false;
        }
        if ($p === $m || $p . 's' === $m || $p === $m . 's') {
            return true;
        }
        foreach (self::GROUPES[$m] ?? [] as $membre) {
            if ($p === $membre || $p . 's' === $membre || $p === $membre . 's') {
                return true;
            }
        }
        return false;
    }

    /**
     * La ligne de la table pour une plante — une ligne peut nommer deux plantes (« radis et radis blanc »).
     *
     * @return array<string, mixed>|null
     */
    private static function ligne(string $plante, ?string $dossier): ?array
    {
        foreach (Associations::table('succession', $dossier) as $ligne) {
            foreach (explode(' et ', (string) $ligne['nom']) as $nom) {
                if (self::correspond($plante, $nom)) {
                    return $ligne;
                }
            }
        }
        return null;
    }

    /** « presque toutes sauf bette et betterave rouge » → les exceptions, ou null si ce n'est pas cette forme. */
    private static function exceptions(string $item): ?array
    {
        $n = Associations::normaliser($item);
        if (!str_starts_with($n, 'presque toutes sauf ')) {
            return null;
        }
        return array_values(array_filter(array_map('trim', preg_split('/ et |,/u', substr($n, strlen('presque toutes sauf '))) ?: [])));
    }

    /**
     * Ce que la table dit de l'enchaînement de deux plantes sur une planche : `$avant`, puis `$apres`.
     *
     * @return self::CONSEILLE|self::DECONSEILLE|self::SANS_REPERE
     */
    public static function enchainement(string $avant, string $apres, ?string $dossier = null): string
    {
        $cites = false;
        // Deux lectures du même enchaînement : ce que `$apres` accepte avant lui, ce que `$avant` laisse derrière lui.
        foreach ([[$apres, 'preculture', $avant], [$avant, 'postculture', $apres]] as [$plante, $liste, $autre]) {
            $ligne = self::ligne($plante, $dossier);
            foreach ($ligne[$liste] ?? [] as $item) {
                $exceptions = self::exceptions((string) $item);
                if ($exceptions !== null) {
                    foreach ($exceptions as $e) {
                        if (self::correspond($autre, $e)) {
                            return self::DECONSEILLE;
                        }
                    }
                    $cites = true;
                } elseif (self::correspond($autre, (string) $item)) {
                    $cites = true;
                }
            }
        }
        return $cites ? self::CONSEILLE : self::SANS_REPERE;
    }

    /**
     * Ce que la table cite comme **suite** d'une plante : ce qui se sème quand sa planche se libère — sa postculture, et
     * les plantes qui la citent comme préculture.
     *
     * @return list<string>
     */
    public static function apres(string $plante, ?string $dossier = null): array
    {
        $noms = [];
        foreach (self::ligne($plante, $dossier)['postculture'] ?? [] as $item) {
            $noms[] = (string) $item;
        }
        foreach (Associations::table('succession', $dossier) as $ligne) {
            foreach ($ligne['preculture'] as $item) {
                if (self::exceptions((string) $item) === null && self::correspond($plante, (string) $item)) {
                    $noms[] = (string) $ligne['nom'];
                }
            }
        }
        return self::sansDoublon($noms);
    }

    /**
     * Ce que la table cite comme **précédent** d'une plante : ce qui occupe la planche avant elle.
     *
     * @return list<string>
     */
    public static function avant(string $plante, ?string $dossier = null): array
    {
        $noms = [];
        foreach (self::ligne($plante, $dossier)['preculture'] ?? [] as $item) {
            $noms[] = (string) $item;
        }
        foreach (Associations::table('succession', $dossier) as $ligne) {
            foreach ($ligne['postculture'] as $item) {
                if (self::exceptions((string) $item) === null && self::correspond($plante, (string) $item)) {
                    $noms[] = (string) $ligne['nom'];
                }
            }
        }
        return self::sansDoublon($noms);
    }

    /** @param list<string> $noms @return list<string> */
    private static function sansDoublon(array $noms): array
    {
        $vus = [];
        $sortie = [];
        foreach ($noms as $nom) {
            $nom = (string) preg_replace('/^presque toutes sauf /u', 'presque toutes, sauf ', $nom);
            $cle = Associations::normaliser($nom);
            if ($cle !== '' && !isset($vus[$cle])) {
                $vus[$cle] = true;
                $sortie[] = $nom;
            }
        }
        return $sortie;
    }

    /** La plante figure-t-elle dans la table de succession ? */
    public static function connue(string $plante, ?string $dossier = null): bool
    {
        return self::ligne($plante, $dossier) !== null;
    }

    /**
     * Les enchaînements des cultures d'une planche, dans l'ordre du temps. Une culture en **suit** une autre quand
     * celle-ci est récoltée au plus tard le jour où elle est mise en place ; parmi les cultures déjà libérées, la
     * dernière récoltée est sa devancière. Deux cultures qui se chevauchent ne se suivent pas : elles se côtoient, et c'est
     * l'affaire des voisinages. Une culture sans date de mise en place (ni de semis) ou sans récolte n'est pas datée, donc
     * placée nulle part dans le temps.
     *
     * @param list<array<string, mixed>> $cultures les cultures d'UNE planche (`espece`, `date_semis`, `date_semis_repiquage`, `date_recolte`)
     * @return array{suites: list<array{avant: string, apres: string, verdict: string}>, libere: array{espece: string, date: string}|null, sans_date: int}
     */
    public static function chaine(array $cultures, ?string $dossier = null): array
    {
        $datees = [];
        $sansDate = 0;
        foreach ($cultures as $c) {
            $debut = (string) (($c['date_semis_repiquage'] ?? '') !== '' ? $c['date_semis_repiquage'] : ($c['date_semis'] ?? ''));
            $fin = (string) ($c['date_recolte'] ?? '');
            if ($debut === '' || $fin === '') {
                $sansDate++;
                continue;
            }
            $datees[] = ['espece' => (string) $c['espece'], 'debut' => $debut, 'fin' => $fin];
        }
        usort($datees, static fn (array $a, array $b): int => [$a['debut'], $a['fin'], $a['espece']] <=> [$b['debut'], $b['fin'], $b['espece']]);

        $suites = [];
        foreach ($datees as $i => $b) {
            $devanciere = null;
            foreach ($datees as $j => $a) {
                if ($j !== $i && $a['fin'] <= $b['debut'] && ($devanciere === null || $a['fin'] > $devanciere['fin'])) {
                    $devanciere = $a;
                }
            }
            if ($devanciere !== null) {
                $suites[] = ['avant' => $devanciere['espece'], 'apres' => $b['espece'],
                    'verdict' => self::enchainement($devanciere['espece'], $b['espece'], $dossier)];
            }
        }

        // La culture qui libère la planche en dernier : c'est après elle que l'on cherche quoi semer.
        $libere = null;
        foreach ($datees as $c) {
            if ($libere === null || $c['fin'] > $libere['date']) {
                $libere = ['espece' => $c['espece'], 'date' => $c['fin']];
            }
        }
        return ['suites' => $suites, 'libere' => $libere, 'sans_date' => $sansDate];
    }
}
