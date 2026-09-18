<?php

declare(strict_types=1);

namespace Syntropia;

/**
 * Dans quel monde on se trouve — syntropie, ou jardin-forêt.
 *
 * Port réduit de `Conduite` du dépôt de référence (`Syntropie\Conduite`),
 * chantier `chantier-scenario-plantation.md`, Phase 2. **Les deux
 * disciplines partagent le terrain et divergent sur la conduite** : un sol se
 * lit pareil, un climat se relève pareil. Ce qui change, c'est ce qu'on fait
 * pousser dessus et comment on l'entretient — le vocabulaire des strates, la
 * présence d'un axe du temps, l'unité de conception, le sens de la taille.
 *
 * D'où la place de cette énumération : **sur le site et sur la parcelle**,
 * jamais sur l'espèce. Un cognassier n'est ni syntropique ni forestier ; c'est
 * la conduite qu'on lui donne qui l'est.
 *
 * Ne sont pas portés, faute d'usage ici : `marquages()` (les citations entre
 * crochets ne sont pas un mécanisme de syntropia), `calendrierSeCaleSur()` et
 * `distancesDePlantation()`.
 */
enum Conduite: string
{
    case Syntropique = 'syntropique';
    case JardinForet = 'jardin-foret';

    public function libelle(): string
    {
        return match ($this) {
            self::Syntropique => 'Syntropie',
            self::JardinForet => 'Jardin-forêt',
        };
    }

    public function explication(): string
    {
        return match ($this) {
            self::Syntropique => 'Planter dense et divers en une seule campagne, en pensant '
                . 'la succession. La taille est le moteur du système : elle rajeunit, libère '
                . 'de la lumière et dépose la biomasse au sol. On éclaircit à mesure.',
            self::JardinForet => 'Un système nourricier, multi-étagé et pérenne, à récolte '
                . 'manuelle. Chaque plante est posée à sa place définitive, avec sa place '
                . "adulte. La taille est un soin d'entretien, non un moteur.",
        };
    }

    /**
     * Le critère qui décide de la strate — c'est là que tout se joue.
     *
     * **La syntropie stratifie par besoin en lumière, le jardin-forêt par
     * place et hauteur adulte.** Deux plantes de même hauteur peuvent occuper
     * deux strates syntropiques différentes ; deux plantes de même exigence
     * lumineuse, deux strates forestières différentes. **Les deux vocabulaires
     * ne se convertissent pas.**
     */
    public function critereDeStrate(): string
    {
        return match ($this) {
            self::Syntropique => 'le besoin en lumière',
            self::JardinForet => 'la place et la hauteur adulte',
        };
    }

    /**
     * Le vocabulaire des strates de cette conduite : quatre en syntropie,
     * neuf en jardin-forêt. Les codes sont ceux de l'énumération SQL de
     * `scenario_ligne.strate`, qui porte les treize — c'est ici, et nulle
     * part ailleurs, que la restriction à une conduite se fait, la base ne
     * voyant pas `parcelle.conduite` depuis cette table.
     *
     * @return array<string, string> code interne => libellé affiché
     */
    public function strates(): array
    {
        return match ($this) {
            self::Syntropique => [
                'emergente' => 'Émergente',
                'haute' => 'Haute',
                'moyenne' => 'Moyenne',
                'basse' => 'Basse',
            ],
            self::JardinForet => [
                '1_canopee' => 'Canopée',
                '2_arbre' => 'Arbre',
                '3_buisson' => 'Buisson / arbuste',
                '4_herbacee' => 'Herbacée',
                '5_couvre_sol' => 'Couvre-sol / rampante',
                '6_racine' => 'Racine / tubercule',
                '7_liane' => 'Liane / grimpante',
                '8_aquatique' => 'Aquatique',
                '9_epiphyte' => 'Épiphyte',
            ],
        };
    }

    /**
     * Le scénario porte-t-il un axe du temps ? **C'est la divergence
     * structurante.** En syntropie chaque espèce entre et sort du film : un
     * tableau temps × strates. En jardin-forêt une plante est posée à sa
     * place, et elle y reste.
     */
    public function scenarioADesTemps(): bool
    {
        return $this === self::Syntropique;
    }

    /** L'unité autour de laquelle on conçoit. */
    public function uniteDeConception(): string
    {
        return match ($this) {
            self::Syntropique => 'consortium',
            self::JardinForet => 'guilde',
        };
    }

    /** Ce que la taille est ici — et les deux réponses s'opposent. */
    public function sensDeLaTaille(): string
    {
        return match ($this) {
            self::Syntropique => 'le moteur du système',
            self::JardinForet => "un soin d'entretien",
        };
    }

    /**
     * Ce que l'atelier ne sait pas encore faire pour cette conduite — dit à
     * côté du choix, plutôt que de laisser croire à une parité. Le
     * vocabulaire de strates et le tableau existent pour les deux ; c'est
     * l'accompagnement écrit qui suit la syntropie.
     */
    public function reserve(): ?string
    {
        return match ($this) {
            self::Syntropique => null,
            self::JardinForet => "Les textes d'aide de l'atelier sont écrits pour la syntropie. "
                . 'En jardin-forêt, les neuf strates et le scénario sont là, mais '
                . "l'accompagnement ne les suit pas encore.",
        };
    }

    /**
     * La conduite d'une valeur venue de la base, sans jamais lever. `NULL` sur
     * une parcelle signifie « hérite du site » — c'est `Web\Heritage` qui
     * tranche, non cette méthode. Une valeur inconnue retombe sur la
     * syntropie plutôt que de casser une page.
     */
    public static function depuis(?string $valeur, self $defaut = self::Syntropique): self
    {
        if ($valeur === null || trim($valeur) === '') {
            return $defaut;
        }
        return self::tryFrom(trim($valeur)) ?? $defaut;
    }

    /** Cette strate appartient-elle au vocabulaire de cette conduite ? */
    public function strateValide(?string $strate): bool
    {
        return $strate !== null && isset($this->strates()[trim($strate)]);
    }

    /**
     * Toutes les strates des deux vocabulaires, dans l'ordre — pour vérifier
     * que l'énumération SQL et ce fichier disent la même chose (une strate
     * corrigée dans l'un a déjà survécu dans l'autre, côté référence).
     *
     * @return list<string>
     */
    public static function toutesLesStrates(): array
    {
        $toutes = [];
        foreach (self::cases() as $cas) {
            foreach (array_keys($cas->strates()) as $code) {
                $toutes[] = $code;
            }
        }
        return $toutes;
    }

    /** @return list<self> */
    public static function toutes(): array
    {
        return self::cases();
    }
}
