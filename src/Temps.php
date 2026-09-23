<?php

declare(strict_types=1);

namespace Syntropia;

/**
 * Le temps d'une plante : la semaine comme unité, sa fenêtre d'occupation, et l'échelle à laquelle on la regarde. Pur.
 *
 * Voir `chantier-film-de-la-parcelle.md`, Phase 2 — décisions du porteur (2026-09-23) : **l'unité est la semaine** (à
 * affiner avec l'expérience) ; **une plante occupe sa place de sa mise en place à sa récolte** (semis en place ou
 * repiquage — l'atelier ne gère pas la pépinière : le semis sous abri est l'affaire du porteur de projet) ; la **levée**
 * d'un semis en place suit le délai de germination de l'espèce ; **l'échelle se choisit** selon le cycle des espèces,
 * comme l'échelle d'une carte : semaine, mois ou année.
 *
 * Une semaine se désigne par son **lundi** (ISO 8601). Toutes les dates sont des `Y-m-d`.
 *
 * **Le scénario pérenne** ne porte pas de dates mais des périodes (placenta 1 au climax, découpées en quarts —
 * `Scenario::position()`). Elles se rapportent au calendrier par la date de plantation de la parcelle, avec les durées
 * du tableau du ch. 11 : placenta 1, 90 jours ; placenta 2, 180 jours ; placenta 3 jusqu'à 3 ans ; secondaire 1 jusqu'à
 * 15 ans ; secondaire 2 jusqu'à 25 ans ; secondaire 3 jusqu'à 60 ans ; le climax au-delà (borné ici à 100 ans pour le
 * dessin). Les durées « 1 à 3 ans » ou « 3 à 15 ans » sont des fourchettes : on prend leur borne haute comme fin de
 * période — **un ordre de grandeur, dit tel quel**, non une date.
 */
final class Temps
{
    public const ECHELLES = ['semaine' => 'Semaine', 'mois' => 'Mois', 'annee' => 'Année'];

    /** Le début de chaque période du scénario, en jours après la plantation ; la dernière valeur borne le climax. */
    private const DEBUTS_PERIODES = [0, 90, 270, 3 * 365, 15 * 365, 25 * 365, 60 * 365, 100 * 365];

    /** Le lundi de la semaine d'une date. */
    public static function semaine(string $date): string
    {
        $d = new \DateTimeImmutable($date);
        return $d->modify('-' . ((int) $d->format('N') - 1) . ' days')->format('Y-m-d');
    }

    /**
     * La fenêtre d'occupation d'une culture du potager (`succession_ligne`) : de sa mise en place à sa récolte, en
     * semaines (lundis). `null` si l'une des deux dates manque — la culture n'est alors placée nulle part dans le temps.
     * `levee` : la semaine de la levée, pour un semis en place (pas de semis sous abri noté) quand le délai de
     * germination de l'espèce est connu.
     *
     * @param array<string, mixed> $culture
     * @return array{debut: string, fin: string, levee: ?string}|null
     */
    public static function fenetreCulture(array $culture, ?int $delaiGermination = null): ?array
    {
        $mise = $culture['date_semis_repiquage'] ?? null;
        $recolte = $culture['date_recolte'] ?? null;
        if ($mise === null || $mise === '' || $recolte === null || $recolte === '' || $recolte < $mise) {
            return null;
        }
        $enPlace = ($culture['date_semis'] ?? null) === null || $culture['date_semis'] === '';
        $levee = $enPlace && $delaiGermination !== null && $delaiGermination > 0
            ? self::semaine((new \DateTimeImmutable((string) $mise))->modify('+' . $delaiGermination . ' days')->format('Y-m-d'))
            : null;
        return ['debut' => self::semaine((string) $mise), 'fin' => self::semaine((string) $recolte), 'levee' => $levee];
    }

    /**
     * La fenêtre d'une plante du scénario pérenne, rapportée au calendrier par la date de plantation de la parcelle :
     * `$debut` et `$fin` sont des positions de `Scenario::position()` (0 à 28, quarts de période).
     *
     * @return array{debut: string, fin: string}
     */
    public static function fenetrePeriodes(string $datePlantation, int $debut, int $fin): array
    {
        return ['debut' => self::semaine(self::dateDePosition($datePlantation, $debut)),
            'fin' => self::semaine(self::dateDePosition($datePlantation, $fin))];
    }

    /** La date d'une position du scénario (0 à 28) : le début de sa période, plus la part de quarts écoulée. */
    public static function dateDePosition(string $datePlantation, int $position): string
    {
        $position = max(0, min(28, $position));
        $periode = min(6, intdiv($position, 4));
        $quart = $position - 4 * $periode;
        if ($position === 28) {
            $periode = 6;
            $quart = 4;
        }
        $a = self::DEBUTS_PERIODES[$periode];
        $b = self::DEBUTS_PERIODES[$periode + 1];
        $jours = (int) round($a + ($b - $a) * $quart / 4);
        return (new \DateTimeImmutable($datePlantation))->modify('+' . $jours . ' days')->format('Y-m-d');
    }

    /** Une fenêtre est-elle présente à la date donnée (semaine comprise) ? */
    public static function presente(array $fenetre, string $date): bool
    {
        $s = self::semaine($date);
        return $s >= $fenetre['debut'] && $s <= $fenetre['fin'];
    }

    /**
     * Les pas d'une frise entre deux dates, à l'échelle choisie : le premier jour de chaque semaine (lundi), mois ou année.
     *
     * @return list<string>
     */
    public static function pas(string $debut, string $fin, string $echelle): array
    {
        $d = match ($echelle) {
            'mois' => new \DateTimeImmutable(substr($debut, 0, 7) . '-01'),
            'annee' => new \DateTimeImmutable(substr($debut, 0, 4) . '-01-01'),
            default => new \DateTimeImmutable(self::semaine($debut)),
        };
        $pas = match ($echelle) {
            'mois' => '+1 month',
            'annee' => '+1 year',
            default => '+7 days',
        };
        $out = [];
        while ($d->format('Y-m-d') <= $fin && count($out) < 2000) {
            $out[] = $d->format('Y-m-d');
            $d = $d->modify($pas);
        }
        return $out;
    }
}
