<?php

declare(strict_types=1);

namespace Syntropia;

/**
 * Les repères de semis et de repiquage du plan de culture de Yann Lopez — pur, sans base.
 *
 * Voir `chantier-plan-de-culture.md` (Phase 4). Lit `contenu/calendrier-lopez.csv`, le tableau « des semis et repiquages »
 * de `connaissances/maraichage-naturel/lopez-plan-de-culture.md` (« Les Jardins Sauvages », maraîcher en Haute-Vienne : la
 * première source du dépôt à la bonne latitude, **ses dates se lisent sans transposition**).
 *
 * **Ce que ce tableau est, et n'est pas** :
 * - **17 cultures seulement, toutes d'automne et d'hiver** (choux, rutabaga, navet, blette d'hiver, épinard, mesclun, salade
 *   d'hiver…) : rien pour la tomate, la courgette ou la carotte. Une culture absente n'a pas de repère — ce qui ne veut pas
 *   dire qu'elle n'a pas de date ;
 * - **des dates en mots** (« fin avril », « mi à fin juillet »), pas des jours : la source parle en décades. Une date proposée
 *   est donc **indicative** — le milieu de la fenêtre — et la personne la corrige ;
 * - **un point de départ à remplacer par sa propre mesure** : le livre le dit lui-même. Les taux du tableau (semé/repiqué,
 *   médiane 80 %) sont une convention de planification, non une mesure ;
 * - deux cellules que la source signale « à vérifier » (le repiquage du chou rouge, la surface de la chicorée) restent
 *   vides et **dites** : on n'y met pas ce qu'on croit y lire.
 */
final class CalendrierLopez
{
    private const FICHIER = __DIR__ . '/../contenu/calendrier-lopez.csv';

    private const MOIS = ['janvier' => 1, 'fevrier' => 2, 'mars' => 3, 'avril' => 4, 'mai' => 5, 'juin' => 6, 'juillet' => 7,
        'aout' => 8, 'septembre' => 9, 'octobre' => 10, 'novembre' => 11, 'decembre' => 12];

    /** Des noms d'usage qui désignent une culture du tableau — peu, et sûrs. */
    private const ALIAS = ['kale' => 'chou kale', 'chou vert' => 'chou vert frise', 'chou frise' => 'chou vert frise'];

    /** @var array<string, array<string, mixed>> */
    private static array $cache = [];

    /** Un nom comparable : sans accent ni casse, au singulier (« choux » → « chou »), sans ponctuation. */
    public static function cle(string $nom): string
    {
        $mots = preg_split('/[^a-z0-9]+/', Associations::normaliser($nom), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $mots = array_map(static fn (string $m): string => strlen($m) > 3 && ($m[-1] === 's' || $m[-1] === 'x') ? substr($m, 0, -1) : $m, $mots);
        return implode(' ', $mots);
    }

    /**
     * Une période de la source en fenêtre de jours d'un mois : « fin avril », « mi-juin », « mi à fin juillet ».
     *
     * @return array{mois: int, debut: int, fin: int, milieu: int}|null `null` si le texte n'est pas une période (« à vérifier », vide)
     */
    public static function periode(string $texte): ?array
    {
        $t = Associations::normaliser($texte);
        if (!preg_match('/^(debut|mi|fin)(?: a (debut|mi|fin))? ([a-z]+)$/', $t, $m) || !isset(self::MOIS[$m[3]])) {
            return null;
        }
        $mois = self::MOIS[$m[3]];
        $dernier = (int) (new \DateTimeImmutable(sprintf('2001-%02d-01', $mois)))->format('t');
        $fenetre = ['debut' => [1, 10], 'mi' => [11, 20], 'fin' => [21, $dernier]];
        $a = $fenetre[$m[1]];
        $b = $fenetre[$m[2] !== '' ? $m[2] : $m[1]];
        return ['mois' => $mois, 'debut' => $a[0], 'fin' => $b[1], 'milieu' => intdiv($a[0] + $b[1], 2)];
    }

    /** @return array<string, array<string, mixed>> clé normalisée → ligne */
    public static function table(?string $fichier = null): array
    {
        $fichier ??= self::FICHIER;
        if (isset(self::$cache[$fichier])) {
            return self::$cache[$fichier];
        }
        $lignes = [];
        if (is_readable($fichier) && ($h = fopen($fichier, 'rb')) !== false) {
            $entete = fgetcsv($h, 0, ';', '"', '');
            while ($entete !== false && $entete !== null && ($r = fgetcsv($h, 0, ';', '"', '')) !== false) {
                if ($r === [null] || count($r) < count($entete)) {
                    continue;
                }
                $r = array_combine($entete, array_slice($r, 0, count($entete)));
                $lignes[self::cle((string) $r['culture'])] = [
                    'culture' => (string) $r['culture'], 'semis' => trim((string) $r['semis']), 'repiquage' => trim((string) $r['repiquage']),
                    'a_verifier' => (string) $r['a_verifier'],
                ];
            }
            fclose($h);
        }
        return self::$cache[$fichier] = $lignes;
    }

    /**
     * Le repère de Lopez pour une culture, ou `null` : le tableau n'en couvre que dix-sept.
     *
     * @return array{culture: string, semis: string, repiquage: string, a_verifier: string}|null
     */
    public static function pour(string $nom, ?string $fichier = null): ?array
    {
        $table = self::table($fichier);
        $cle = self::cle($nom);
        return $table[self::ALIAS[$cle] ?? $cle] ?? null;
    }

    /**
     * Des dates INDICATIVES pour un repère : le milieu de chaque fenêtre, à la prochaine occurrence à partir d'une date de
     * référence (aujourd'hui). Le repiquage suit le semis, l'année d'après si besoin.
     *
     * @param array{semis: string, repiquage: string} $repere
     * @return array{semis: ?string, repiquage: ?string}
     */
    public static function datesIndicatives(array $repere, \DateTimeImmutable $reference): array
    {
        $ps = self::periode($repere['semis']);
        $pr = self::periode($repere['repiquage']);
        $annee = (int) $reference->format('Y');
        $date = static fn (array $p, int $a): \DateTimeImmutable => new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $a, $p['mois'], $p['milieu']));
        $semis = null;
        $repiquage = null;
        if ($ps !== null) {
            $semis = $date($ps, $annee);
            if ($semis < $reference->setTime(0, 0)) {
                $semis = $date($ps, $annee + 1);
            }
        }
        if ($pr !== null) {
            $depart = $semis !== null ? (int) $semis->format('Y') : $annee;
            $repiquage = $date($pr, $depart);
            if ($semis !== null && $repiquage < $semis) {
                $repiquage = $date($pr, $depart + 1);
            } elseif ($semis === null && $repiquage < $reference->setTime(0, 0)) {
                $repiquage = $date($pr, $depart + 1);
            }
        }
        return ['semis' => $semis?->format('Y-m-d'), 'repiquage' => $repiquage?->format('Y-m-d')];
    }
}
