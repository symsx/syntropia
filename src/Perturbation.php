<?php

declare(strict_types=1);

namespace Syntropia;

/**
 * Le vocabulaire du journal des perturbations, et ce qu'on peut en lire — pur, sans base.
 *
 * Voir `chantier-saut-du-chat.md`, Phase 1. Les clés sont ce qui s'écrit dans
 * `intervention` (`type`, `stade_phenologique`, `conditions_eau`, `pluies_a_venir`,
 * `biomasse_deposee`) ; les libellés sont ce qui s'affiche. Une clé ne change jamais : un
 * export en porte la valeur brute.
 *
 * **Ce que ce vocabulaire ne fait pas : décider.** Il ne dit ni quand couper ni à quelle
 * fréquence — le porteur l'a tranché le 2026-09-20 : le moment se lit sur l'état végétatif
 * des plantes de biomasse, la fréquence suit leur développement, et l'on arrose si la pluie
 * ne vient pas. Le journal note ce qui s'est passé ; il ne pose aucun seuil.
 */
final class Perturbation
{
    /** Le geste. Seul « affaiblir » n'est pas une perturbation : voir `PERTURBENT`. */
    public const TYPES = [
        'coupe_vert' => 'Coupe en vert',
        'taille_hiver' => 'Taille d’hiver',
        'fauche' => 'Fauche',
        'recepage' => 'Recépage, cépée ou étêtage',
        'affaiblissement' => 'Affaiblir sans couper (coucher sous paille)',
    ];

    /**
     * Les gestes qui relancent la croissance. Affaiblir une couverture spontanée qu'on veut
     * planter n'en fait pas partie : une taille la ferait repartir « à fond la caisse »
     * (Joala, 4-3), on la couche donc sous la paille. Ce geste se note, mais ne compte
     * jamais comme une perturbation — ni ici, ni dans le diagnostic du saut du chat.
     */
    public const PERTURBENT = ['coupe_vert', 'taille_hiver', 'fauche', 'recepage'];

    /**
     * L'état végétatif de ce qu'on coupe : c'est lui qui dicte le moment. Repères du corpus —
     * la fin de floraison (Théry ch. 12, Faraj : « avant le déclin qui suit la floraison »),
     * le repos d'hiver avant le débourrement (Joala, 3-1), l'après-gel (Joala, 3-7).
     */
    public const STADES = [
        'croissance' => 'En pleine croissance',
        'avant_floraison' => 'Avant la floraison',
        'fleurs_fanees' => 'Fleurs fanées, les butineurs s’en désintéressent',
        'repos_hiver' => 'Repos d’hiver, avant le débourrement',
        'apres_gel' => 'Après un gel ou un dégât',
    ];

    /** L'eau autour de la coupe. « Sol sec, pas arrosé » se note, il ne se refuse pas. */
    public const EAUX = [
        'pluie_avant' => 'Pluie tombée juste avant',
        'pluie_apres' => 'Pluie tombée juste après',
        'arrose' => 'Arrosé, avant ou après la coupe',
        'sol_humide' => 'Sol déjà bien humide',
        'sec' => 'Sol sec, pas arrosé',
    ];

    public const PLUIES = [
        'annoncee' => 'Pluie annoncée',
        'aucune' => 'Aucune pluie annoncée',
    ];

    /** Ce qu'il est advenu de ce qui a été coupé : « ne jamais exporter » (Théry ch. 12 ; Joala, 2-4). */
    public const BIOMASSES = [
        'au_sol' => 'Tout laissé au sol, au pied des plantes',
        'en_partie' => 'En partie au sol (au moins un tiers)',
        'exportee' => 'Sortie de la parcelle',
    ];

    /** Séparateur des végétaux dans `intervention.especes`. */
    public const SEPARATEUR = ' · ';

    public static function perturbe(string $type): bool
    {
        return in_array($type, self::PERTURBENT, true);
    }

    /** Le libellé d'une clé, ou la clé elle-même si elle est inconnue (un vieil export, une autre version). */
    public static function libelle(array $liste, ?string $cle): string
    {
        $cle = (string) $cle;
        return $liste[$cle] ?? $cle;
    }

    /** @return list<string> les végétaux d'une intervention */
    public static function vegetaux(?string $texte): array
    {
        $texte = trim((string) $texte);
        if ($texte === '') {
            return [];
        }
        return array_values(array_filter(array_map('trim', explode(self::SEPARATEUR, $texte)), static fn (string $v): bool => $v !== ''));
    }

    /** @param list<string> $noms */
    public static function joindre(array $noms): string
    {
        return implode(self::SEPARATEUR, array_values(array_filter(array_map('trim', $noms), static fn (string $v): bool => $v !== '')));
    }

    /** Les jours écoulés entre une date (Y-m-d) et aujourd'hui — 0 pour aujourd'hui, jamais négatif. */
    public static function joursDepuis(string $date, \DateTimeImmutable $aujourdhui): int
    {
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if ($d === false) {
            return 0;
        }
        return max(0, (int) $d->diff($aujourdhui->setTime(0, 0))->format('%r%a'));
    }

    /**
     * La dernière perturbation de chaque végétal cité : nom (minuscules) → date `Y-m-d`.
     * Les gestes qui n'en sont pas (affaiblir) ne comptent pas ; la comparaison ne tient pas
     * compte de la casse.
     *
     * @param list<array<string, mixed>> $interventions
     * @return array<string, string>
     */
    public static function derniereParVegetal(array $interventions): array
    {
        $dernieres = [];
        foreach ($interventions as $i) {
            if (!self::perturbe((string) ($i['type'] ?? ''))) {
                continue;
            }
            $date = (string) ($i['date'] ?? '');
            foreach (self::vegetaux($i['especes'] ?? '') as $nom) {
                $cle = mb_strtolower($nom);
                if ($date !== '' && (!isset($dernieres[$cle]) || $date > $dernieres[$cle])) {
                    $dernieres[$cle] = $date;
                }
            }
        }
        return $dernieres;
    }
}
