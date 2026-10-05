<?php

declare(strict_types=1);

namespace Syntropia;

/**
 * Le saut du chat : le vocabulaire d'un constat et la lecture des conditions qui le rendent possible —
 * pur, sans base.
 *
 * Voir `chantier-saut-du-chat.md`, Phase 2. Le saut du chat est l'EFFET de la perturbation, non un
 * objectif indépendant : il se déclenche par la taille (pas par la seule densité) et se reconnaît à trois
 * signes (le porteur, 2026-10-05) : des végétaux implantés qui poussent vite et haut, une biomasse produite
 * importante, la structure du sol qui change par rapport au départ. Pas de témoin conduit classiquement :
 * on ne contraint personne à cultiver avec des intrants. Rien ici ne rend de verdict : `conditions()` dit ce que l'atelier SAIT (une
 * coupe est notée, l'eau y était…), ce qu'il faut REGARDER, et ce que personne n'a encore noté — jamais
 * « échec ».
 *
 * Ce que ce code ne sait pas : combien de biomasse suffit, quelle fréquence de coupe, quoi planter par
 * itinéraire. Ce sont des questions ouvertes du chantier, qui se ferment avec la formation ; elles ne
 * se codent pas ici avant d'avoir une réponse sourcée.
 */
final class SautDuChat
{
    public const VISIBLES = [
        'oui' => 'Oui, il est visible',
        'pas_encore' => 'Pas encore',
    ];

    /**
     * Une des fonctions de service (`Scenario::FONCTIONS`) qu'une culture de potager peut porter dans
     * `succession_ligne.role` : celle qui porte la biomasse — qu'on taille (`Scenario::fonctionsDe()` lit le
     * `role`, multivalué depuis le 2026-09-22).
     */
    public const ROLE_BIOMASSE = 'biomasse';

    /** Les trois états d'une condition. */
    public const OK = 'ok';
    public const ATTENTION = 'attention';
    public const VIDE = 'vide';

    /**
     * Des adventices reviennent-elles ? Si oui, c'est le signe que la plantation n'est pas assez dense : des
     * plantes de colonisation sont encore là.
     */
    public const ADVENTICES = [
        'non' => 'Non',
        'oui' => 'Oui, des adventices reviennent',
    ];

    /**
     * Les plantes du système d'accumulation que cite le support de formation (Joala, 2-2) : ce avec quoi l'on
     * densifie quand des adventices reviennent. Des exemples du support, non une liste à suivre : un végétal ne
     * se classe pas par son seul nom.
     */
    public const ACCUMULATION = ['saule', 'peuplier', 'bouleau', 'noisetier', 'aubépine', 'prunellier'];

    /** Des coupes faites à quelques jours d'écart sont une même intervention sur la zone. */
    public const FENETRE_JOURS = 3;

    /**
     * Une campagne de plantation qui tient dans à peu près six mois — de fin d'automne au printemps — est « dans
     * une même période » : le repère du corpus (la campagne d'implantation s'étale sur environ six mois, contrainte
     * par des fenêtres physiologiques qui ne coïncident pas). Un repère, non un seuil de refus.
     */
    public const CAMPAGNE_JOURS = 190;

    /** Les préparations que l'étape « Préparation de la parcelle » de la Réalisation enregistre. */
    public const PREPARATIONS = [
        'dechaumage' => 'Déchaumage',
        'bachage' => 'Couverture du sol (bâchage)',
        'les-deux' => 'Déchaumage et couverture du sol',
    ];
    public const REALISEES = [
        'manuellement' => 'manuellement',
        'animaux' => 'par un ou plusieurs animaux',
        'machine' => 'avec une machine',
        'les-deux' => 'avec plusieurs moyens',
        'autre' => 'autrement',
    ];

    /** Au-delà de ce repère du scénario (0 à 28), une plante n'est plus une plante de démarrage : fin de Placenta 3. */
    private const FIN_DEMARRAGE = 12;

    /** La biomasse produite, deuxième signe du saut du chat : une réponse à noter, non un seuil. */
    public const BIOMASSES = [
        'faible' => 'Faible',
        'moyenne' => 'Moyenne',
        'importante' => 'Importante',
    ];

    /** La structure du sol par rapport au départ, troisième signe : ce que la personne voit et sent à la bêche. */
    public const SOLS = [
        'plus_grumeleux' => 'Plus grumeleux',
        'pareil' => 'Pareil',
        'plus_tasse' => 'Plus tassé',
    ];

    /**
     * La hauteur de la plante observée, dite contre sa hauteur adulte d'Espèces (le porteur, 2026-10-05) :
     * « 1,20 m, pour 1,80 m adulte ». Sans hauteur adulte notée, la hauteur seule.
     */
    public static function hauteurDite(float $hauteurCm, ?float $adulteM): string
    {
        $m = static fn (float $v): string => rtrim(rtrim(number_format($v, 2, ',', ''), '0'), ',');
        return $m($hauteurCm / 100) . ' m' . ($adulteM !== null && $adulteM > 0 ? ', pour ' . $m($adulteM) . ' m adulte' : '');
    }

    /**
     * Les noms des plantes de biomasse : celles d'un scénario qui portent la fonction « biomasse » ou
     * « relancer par la perturbation », et celles d'un potager qu'on a marquées telles (`role` = `biomasse`,
     * sur une culture de `succession_ligne` : le maïs, le tournesol d'une tranchée centrale).
     *
     * @param list<array<string, mixed>> $lignes les lignes de `scenario_ligne`, et/ou de `succession_ligne`
     * @return array<string, string> nom en minuscules → nom
     */
    public static function plantesDeBiomasse(array $lignes): array
    {
        $plantes = [];
        foreach ($lignes as $ligne) {
            $fonctions = Scenario::details($ligne)['fonctions'] ?? [];
            if (in_array(self::ROLE_BIOMASSE, Scenario::fonctionsDe((string) ($ligne['role'] ?? '')), true)
                || (is_array($fonctions) && (in_array('perturbation', $fonctions, true) || in_array('biomasse', $fonctions, true)))) {
                $nom = trim((string) ($ligne['espece'] ?? ''));
                if ($nom !== '') {
                    $plantes[mb_strtolower($nom)] = $nom;
                }
            }
        }
        return $plantes;
    }

    /**
     * Les plantes de démarrage d'un scénario : celles qui ne restent pas au-delà du Placenta 3 (les annuelles
     * placentaires — maïs, tournesol, féveroles : Théry ch. 14 et 15). Repère du scénario, non un savoir botanique.
     *
     * @param list<array<string, mixed>> $lignes
     * @return list<string>
     */
    public static function plantesDeDemarrage(array $lignes): array
    {
        $noms = [];
        foreach ($lignes as $ligne) {
            $d = Scenario::details($ligne);
            $fin = isset($d['fin']) && is_numeric($d['fin'])
                ? (int) $d['fin']
                : Scenario::position((string) ($ligne['sortie'] ?? ''), true);
            $nom = trim((string) ($ligne['espece'] ?? ''));
            if ($nom !== '' && $fin > 0 && $fin <= self::FIN_DEMARRAGE) {
                $noms[$nom] = $nom;
            }
        }
        return array_values($noms);
    }

    /**
     * Les conditions du saut du chat, lues dans ce que l'atelier sait déjà.
     *
     * @param list<array<string, mixed>> $interventions le journal, la plus récente d'abord
     * @param list<array<string, mixed>> $lignes le scénario de la parcelle
     * @param string|null $debut le début de la campagne de plantation (`Y-m-d`) — le point zéro
     * @param string|null $fin la fin de la campagne (`Y-m-d`)
     * @param array<string, string> $preparation ce que la Réalisation a noté : `technique`, `realisee`
     * @param string|null $adventices la dernière réponse notée à « des adventices reviennent-elles ? » (`non`, `oui`)
     * @param list<array<string, mixed>> $cultures les cultures d'un potager (`succession_ligne`) — leurs plantes de biomasse comptent
     * @param bool $potager la parcelle est un potager : le message dit où marquer une plante de biomasse
     * @return list<array{cle: string, titre: string, etat: string, detail: string, source: string}>
     */
    public static function conditions(array $interventions, array $lignes, \DateTimeImmutable $aujourdhui,
        ?string $debut = null, ?string $fin = null, array $preparation = [], ?string $adventices = null,
        array $cultures = [], bool $potager = false): array
    {
        $perturbations = array_values(array_filter($interventions, static fn (array $i): bool => Perturbation::perturbe((string) ($i['type'] ?? ''))));
        $derniere = $perturbations[0] ?? null;
        $biomasse = self::plantesDeBiomasse($lignes) + self::plantesDeBiomasse($cultures);
        $demarrage = self::plantesDeDemarrage($lignes);
        $c = [];

        // 0. Le sol est préparé — avec la plantation, l'un des deux moments les plus coûteux du projet.
        $technique = trim((string) ($preparation['technique'] ?? ''));
        $realisee = trim((string) ($preparation['realisee'] ?? ''));
        $c[] = isset(self::PREPARATIONS[$technique])
            ? ['cle' => 'preparation', 'titre' => 'Le sol est préparé', 'etat' => self::OK,
                'detail' => self::PREPARATIONS[$technique] . (isset(self::REALISEES[$realisee]) ? ', ' . self::REALISEES[$realisee] : '')
                    . '. Préparer tôt, mais toujours couvrir le sol en attendant la plantation. Les semis se font sur un sol exempt d’adventices, dont la concurrence freine le saut du chat, surtout celui des pionniers de la succession.',
                'source' => 'Théry, ch. 3 et 14 ; Lopez ; RNA']
            : ['cle' => 'preparation', 'titre' => 'Le sol est préparé', 'etat' => self::VIDE,
                'detail' => 'La préparation n’est pas notée (Réalisation, étape 1). Préparation et plantation sont les deux moments les plus coûteux du projet : les bâcler se paie ensuite en désherbage et en rattrapage. Les semis se font sur un sol exempt d’adventices.',
                'source' => 'Théry, ch. 3 et 14 ; Lopez'];

        // 0 bis. La plantation dense se fait dans une même période, sans structurante ajoutée après coup.
        $d0 = $debut !== null && $debut !== '' ? \DateTimeImmutable::createFromFormat('!Y-m-d', $debut) : false;
        $d1 = $fin !== null && $fin !== '' ? \DateTimeImmutable::createFromFormat('!Y-m-d', $fin) : false;
        $titreCampagne = 'La plantation est faite dans une même période';
        $sourceCampagne = 'Théry, ch. 11 ; Faraj ; Joala 4-1';
        if ($d0 === false) {
            $c[] = ['cle' => 'campagne', 'titre' => $titreCampagne, 'etat' => self::VIDE,
                'detail' => 'Le point zéro n’est pas noté. Une plantation dense se fait dans une même période, qui peut courir sur trois saisons (fin d’automne, hiver, printemps) selon les besoins des végétaux. Ce qui compte est de n’ajouter aucune structurante après coup.',
                'source' => $sourceCampagne];
        } elseif ($d1 === false) {
            $c[] = ['cle' => 'campagne', 'titre' => $titreCampagne, 'etat' => self::VIDE,
                'detail' => 'Point zéro le ' . $d0->format('d/m/Y') . '. La fin de la campagne n’est pas notée : notez-la quand la plantation est terminée.',
                'source' => $sourceCampagne];
        } else {
            $jours = (int) $d0->diff($d1)->format('%a');
            $mois = max(1, (int) round($jours / 30.4));
            $etendue = 'Du ' . $d0->format('d/m/Y') . ' au ' . $d1->format('d/m/Y') . ($jours > 0 ? ', environ ' . $mois . ' mois' : ', en un jour') . '.';
            $c[] = $jours <= self::CAMPAGNE_JOURS
                ? ['cle' => 'campagne', 'titre' => $titreCampagne, 'etat' => self::OK,
                    'detail' => $etendue . ' Dans une même saison de plantation. Aucun repère chiffré ne dit encore ce qu’est une plantation dense.',
                    'source' => $sourceCampagne]
                : ['cle' => 'campagne', 'titre' => $titreCampagne, 'etat' => self::ATTENTION,
                    'detail' => $etendue . ' Plus d’une saison de plantation : des plants ajoutés bien après le point zéro occupent moins le sol que s’ils avaient été plantés avec le reste.',
                    'source' => $sourceCampagne];
        }

        // 0 ter. Les adventices ne reviennent pas. Le sol se prépare par déchaumage ; si elles reviennent en cours
        // de projet, la plantation n'est pas assez dense : on désherbe à la main ET l'on densifie.
        $titreAdv = 'Les adventices ne reviennent pas';
        if ($adventices === 'oui') {
            $c[] = ['cle' => 'adventices', 'titre' => $titreAdv, 'etat' => self::ATTENTION,
                'detail' => 'Des adventices reviennent : la plantation n’est pas assez dense, et des plantes de colonisation sont encore là. Désherbez à la main, et densifiez avec des plantes du système d’accumulation (le support cite '
                    . implode(', ', self::ACCUMULATION) . ').',
                'source' => 'Repère de l’atelier ; Joala 2-2'];
        } elseif ($adventices === 'non') {
            $c[] = ['cle' => 'adventices', 'titre' => $titreAdv, 'etat' => self::OK,
                'detail' => 'Aucune adventice ne revient au dernier constat qui l’a noté.', 'source' => 'Repère de l’atelier'];
        } else {
            $c[] = ['cle' => 'adventices', 'titre' => $titreAdv, 'etat' => self::VIDE,
                'detail' => 'À noter au constat. Si des adventices reviennent, la plantation n’est pas assez dense : on désherbe à la main et l’on densifie avec des plantes du système d’accumulation.',
                'source' => 'Repère de l’atelier ; Joala 2-2'];
        }

        // 1. Une perturbation est notée.
        if ($derniere === null) {
            $c[] = ['cle' => 'perturbation', 'titre' => 'Une perturbation est notée', 'etat' => self::VIDE,
                'detail' => 'Aucune coupe n’est notée dans le journal. Le saut du chat est l’effet de la perturbation : sans elle, il ne vient pas.',
                'source' => 'Théry, entretien « 11 questions »'];
        } else {
            $n = count($perturbations);
            $jours = Perturbation::joursDepuis((string) $derniere['date'], $aujourdhui);
            $c[] = ['cle' => 'perturbation', 'titre' => 'Une perturbation est notée', 'etat' => self::OK,
                'detail' => $n . ' perturbation' . ($n > 1 ? 's' : '') . ' notée' . ($n > 1 ? 's' : '') . ', la dernière il y a ' . $jours . ' j.',
                'source' => 'Théry, entretien « 11 questions »'];
        }

        // 2. De quoi couper : des plantes de biomasse au scénario.
        $c[] = $biomasse === []
            ? ['cle' => 'de_quoi_couper', 'titre' => 'De quoi couper', 'etat' => self::VIDE,
                'detail' => ($potager
                    ? 'Aucune culture de ce potager n’est marquée « plante de biomasse » (le maïs ou le tournesol d’une tranchée centrale, par exemple : on les ouvre dans le plan de culture pour les marquer).'
                    : 'Aucune plante de biomasse dans le scénario de cette parcelle.') . ' Sans masse suffisante à tailler, la perturbation n’est pas efficace : le framboisier en bordure vivante est écarté pour cette raison.',
                'source' => 'Théry, ch. 15']
            : ['cle' => 'de_quoi_couper', 'titre' => 'De quoi couper', 'etat' => self::OK,
                'detail' => count($biomasse) . ' plante' . (count($biomasse) > 1 ? 's' : '') . ' de biomasse ' . ($potager ? 'au potager' : 'au scénario') . ' : ' . implode(', ', array_slice(array_values($biomasse), 0, 4)) . (count($biomasse) > 4 ? '…' : '') . '. Aucun repère ne dit encore combien de masse suffit.',
                'source' => 'Théry, ch. 12 et 15'];

        // 3. L'état des plantes à la coupe — c'est lui qui dicte le moment.
        $stade = trim((string) ($derniere['stade_phenologique'] ?? ''));
        $c[] = ['cle' => 'etat', 'titre' => 'L’état des plantes est noté à la coupe',
            'etat' => $derniere !== null && $stade !== '' ? self::OK : self::VIDE,
            'detail' => $derniere === null
                ? 'À noter avec la première coupe : c’est l’état des plantes qui dit le bon moment.'
                : ($stade !== '' ? Perturbation::libelle(Perturbation::STADES, $stade) . '.' : 'L’état des plantes n’est pas noté sur la dernière coupe.'),
            'source' => 'Théry ch. 12 ; Faraj ; Joala 3-1'];

        // 4. L'eau autour de la coupe.
        $eau = trim((string) ($derniere['conditions_eau'] ?? ''));
        if ($derniere === null || $eau === '') {
            $c[] = ['cle' => 'eau', 'titre' => 'L’eau était là', 'etat' => self::VIDE,
                'detail' => $derniere === null ? 'À noter avec la première coupe : pluie, arrosage ou sol humide.' : 'L’eau n’est pas notée sur la dernière coupe.',
                'source' => 'Théry, ch. 12'];
        } elseif ($eau === 'sec') {
            $c[] = ['cle' => 'eau', 'titre' => 'L’eau était là', 'etat' => self::ATTENTION,
                'detail' => 'Sol sec, pas arrosé à la dernière coupe. L’eau porte le signal « il faut que ça pousse » : une bonne part du bénéfice se perd sans elle. Prévoyez de quoi arroser à la prochaine.',
                'source' => 'Théry, ch. 12'];
        } else {
            $c[] = ['cle' => 'eau', 'titre' => 'L’eau était là', 'etat' => self::OK,
                'detail' => Perturbation::libelle(Perturbation::EAUX, $eau) . '.', 'source' => 'Théry, ch. 12'];
        }

        // 5. La biomasse est restée au sol.
        $devenir = trim((string) ($derniere['biomasse_deposee'] ?? ''));
        $c[] = ['cle' => 'biomasse', 'titre' => 'La biomasse est restée au sol',
            'etat' => $devenir === '' ? self::VIDE : ($devenir === 'exportee' ? self::ATTENTION : self::OK),
            'detail' => $derniere === null ? 'À noter avec la première coupe : ce qui sort de la parcelle ne nourrit plus le sol.'
                : ($devenir === '' ? 'Ce que la biomasse est devenue n’est pas noté sur la dernière coupe.'
                : Perturbation::libelle(Perturbation::BIOMASSES, $devenir) . ($devenir === 'exportee' ? ' : ce qui sort ne nourrit plus le sol.' : '.')),
            'source' => 'Théry, ch. 12 ; Joala 2-4'];

        // 6. Toutes les plantes de biomasse coupées ensemble (à quelques jours près).
        if ($biomasse !== []) {
            if ($derniere === null) {
                $c[] = ['cle' => 'ensemble', 'titre' => 'Toutes les plantes de biomasse coupées ensemble', 'etat' => self::VIDE,
                    'detail' => 'L’information de perturbation est démultipliée par la masse : on perturbe toutes les plantes alliées d’une zone en même temps.',
                    'source' => 'Théry, ch. 12 ; Joala 3-7'];
            } else {
                $coupees = [];
                $nomme = false;
                foreach ($perturbations as $p) {
                    if (Perturbation::joursDepuis((string) $p['date'], (new \DateTimeImmutable((string) $derniere['date']))) > self::FENETRE_JOURS) {
                        continue;
                    }
                    foreach (Perturbation::vegetaux($p['especes'] ?? '') as $nom) {
                        $coupees[mb_strtolower($nom)] = true;
                        $nomme = true;
                    }
                }
                $manquantes = array_values(array_diff_key($biomasse, $coupees));
                if (!$nomme) {
                    $c[] = ['cle' => 'ensemble', 'titre' => 'Toutes les plantes de biomasse coupées ensemble', 'etat' => self::VIDE,
                        'detail' => 'Les végétaux coupés ne sont pas notés sur la dernière coupe : on ne peut pas dire si toute la zone l’a été.',
                        'source' => 'Théry, ch. 12 ; Joala 3-7'];
                } elseif ($manquantes === []) {
                    $c[] = ['cle' => 'ensemble', 'titre' => 'Toutes les plantes de biomasse coupées ensemble', 'etat' => self::OK,
                        'detail' => 'Toutes celles du scénario, à ' . self::FENETRE_JOURS . ' jours près de la dernière coupe.',
                        'source' => 'Théry, ch. 12 ; Joala 3-7'];
                } else {
                    $c[] = ['cle' => 'ensemble', 'titre' => 'Toutes les plantes de biomasse coupées ensemble', 'etat' => self::ATTENTION,
                        'detail' => 'Plantes non coupées avec les autres : ' . implode(', ', $manquantes) . '.',
                        'source' => 'Théry, ch. 12 ; Joala 3-7'];
                }
            }
        }

        // 7. Au démarrage, des plantes qui poussent vite (annuelles placentaires).
        $c[] = $demarrage === []
            ? ['cle' => 'demarrage', 'titre' => 'Des plantes de démarrage', 'etat' => self::VIDE,
                'detail' => 'Aucune plante de démarrage (annuelles placentaires) au scénario. Maïs, tournesols et féveroles sont indispensables au départ, pour le saut du chat et pour créer les strates en attendant les pérennes.',
                'source' => 'Théry, ch. 14']
            : ['cle' => 'demarrage', 'titre' => 'Des plantes de démarrage', 'etat' => self::OK,
                'detail' => implode(', ', array_slice($demarrage, 0, 4)) . (count($demarrage) > 4 ? '…' : '') . ' : des plantes qui ne restent pas au-delà du Placenta 3.',
                'source' => 'Théry, ch. 14 et 15'];

        return $c;
    }

    /** Le repère de Théry (ch. 11) : 70 à 80 % des plantes d'une plantation servent la biomasse et la perturbation. */
    public const PART_BIOMASSE_MIN = 0.7;

    /**
     * Ce que le **scénario** d'une parcelle prépare du saut du chat — la lecture de la conception, avant la plantation
     * (`conditions()` lit, elle, ce qui s'est passé). Des indicateurs **non bloquants**, dits « en place », « à regarder » ou
     * « rien de noté », jamais un refus : l'atelier montre ce que les sources demandent et laisse la personne décider.
     *
     * Ce que ce code ne sait pas : si 70 % est le bon seuil pour ce lieu (c'est un repère, non une règle), ni combien de
     * plants couvrent une ligne — la part se compte en **végétaux du scénario**, non en nombre de plants.
     *
     * @param list<array<string, mixed>> $lignes le scénario de la parcelle
     * @param bool $aDesTemps la conduite a un axe du temps (la syntropie ; le jardin-forêt n'en a pas)
     * @param string|null $ressourceEau `site.ressource_eau` (`reseau`, `puits_forage`, `reserve`, `aucune`), vide si non dite
     * @param array<string, string> $libellesStrates clé de strate → libellé, pour la dire en toutes lettres
     * @param Stade|null $stade le stade du terrain de la parcelle, s'il est dit (Phase 3)
     * @return list<array{cle: string, titre: string, etat: string, detail: string, source: string}>
     */
    public static function conception(array $lignes, bool $aDesTemps, ?string $ressourceEau, array $libellesStrates = [], ?Stade $stade = null): array
    {
        $c = [];
        // Le stade du terrain : d'où l'on part. Dit, on rappelle ce que la formation en dit, dont la densité et la taille
        // qu'elle conseille à ce stade (Joala 6-8) ; pas dit, on le demande. Sans scénario, c'est la seule ligne.
        if ($stade !== null) {
            $c[] = ['cle' => 'stade', 'titre' => 'Le stade du terrain : ' . mb_strtolower($stade->libelle()), 'etat' => self::OK,
                'detail' => $stade->explication() . ' ' . $stade->usage() . ' ' . $stade->repere(),
                'source' => 'Joala 2-1 à 2-3, et 6-8'];
        } elseif ($lignes !== []) {
            $c[] = ['cle' => 'stade', 'titre' => 'Le stade du terrain est dit', 'etat' => self::VIDE,
                'detail' => 'Le stade dit d’où part la parcelle : colonisation, accumulation ou abondance. Il se choisit sur le plan de la parcelle, dans le panneau Données. L’atelier donne alors ce que la formation conseille à ce stade : la densité de plantation et la fréquence de taille.',
                'source' => 'Joala 2-1 à 2-3, et 6-8'];
        }
        if ($lignes === []) {
            return $c;
        }
        $total = count($lignes);
        $biomasseLignes = array_values(array_filter($lignes, static function (array $l): bool {
            $f = Scenario::details($l)['fonctions'] ?? [];
            return is_array($f) && (in_array('perturbation', $f, true) || in_array('biomasse', $f, true));
        }));
        $noms = self::plantesDeBiomasse($lignes);

        // 1. Des alliées à perturber.
        $c[] = $noms === []
            ? ['cle' => 'allies', 'titre' => 'Des plantes à perturber', 'etat' => self::VIDE,
                'detail' => 'Aucun végétal ne porte « fournir de la biomasse » ni « relancer par la perturbation ». Le saut du chat est l’effet de la perturbation : il lui faut des plantes à tailler.',
                'source' => 'Théry, ch. 12']
            : ['cle' => 'allies', 'titre' => 'Des plantes à perturber', 'etat' => self::OK,
                'detail' => count($noms) . ' plante' . (count($noms) > 1 ? 's' : '') . ' : ' . implode(', ', array_slice(array_values($noms), 0, 4)) . (count($noms) > 4 ? '…' : '') . '.',
                'source' => 'Théry, ch. 12']
        ;

        // 2. La part de biomasse.
        $part = count($biomasseLignes) / $total;
        $pct = (int) round($part * 100);
        $c[] = $part >= self::PART_BIOMASSE_MIN
            ? ['cle' => 'part', 'titre' => '70 à 80 % de biomasse', 'etat' => self::OK,
                'detail' => count($biomasseLignes) . ' végétaux sur ' . $total . ' (' . $pct . ' %) servent la biomasse ou la perturbation. Le repère : 70 à 80 %.',
                'source' => 'Théry, ch. 11']
            : ['cle' => 'part', 'titre' => '70 à 80 % de biomasse', 'etat' => self::ATTENTION,
                'detail' => count($biomasseLignes) . ' végétaux sur ' . $total . ' (' . $pct . ' %) servent la biomasse ou la perturbation. Le repère est de 70 à 80 %. Il se compte en végétaux du scénario, et non en nombre de plants.',
                'source' => 'Théry, ch. 11'];

        // 3. Au moins deux espèces par strate.
        $parStrate = [];
        foreach ($lignes as $l) {
            $strate = (string) ($l['strate'] ?? '');
            $nom = mb_strtolower(trim((string) ($l['espece'] ?? '')));
            if ($strate !== '' && $nom !== '') {
                $parStrate[$strate][$nom] = true;
            }
        }
        $seules = array_keys(array_filter($parStrate, static fn (array $e): bool => count($e) < 2));
        $c[] = $parStrate === []
            ? ['cle' => 'strates', 'titre' => 'Au moins deux espèces par strate', 'etat' => self::VIDE,
                'detail' => 'Aucune strate n’est attribuée aux végétaux.', 'source' => 'Théry, ch. 11']
            : ($seules === []
                ? ['cle' => 'strates', 'titre' => 'Au moins deux espèces par strate', 'etat' => self::OK,
                    'detail' => 'Chaque strate occupée compte au moins deux espèces : la réussite d’un plant n’est jamais automatique.', 'source' => 'Théry, ch. 11']
                : ['cle' => 'strates', 'titre' => 'Au moins deux espèces par strate', 'etat' => self::ATTENTION,
                    'detail' => 'Une seule espèce en strate ' . implode(', ', array_map(static fn (string $k): string => mb_strtolower($libellesStrates[$k] ?? $k), $seules)) . ' : la réussite d’un plant n’est jamais automatique.', 'source' => 'Théry, ch. 11']);

        // 4. Des plantes de démarrage — seulement là où le temps existe.
        if ($aDesTemps) {
            $demarrage = self::plantesDeDemarrage($lignes);
            $c[] = $demarrage === []
                ? ['cle' => 'demarrage', 'titre' => 'Des plantes de démarrage', 'etat' => self::VIDE,
                    'detail' => 'Aucune annuelle placentaire (qui ne reste pas au-delà du Placenta 3). Maïs, tournesols et féveroles sont indispensables au départ, pour le saut du chat et pour créer les strates en attendant les pérennes.',
                    'source' => 'Théry, ch. 14']
                : ['cle' => 'demarrage', 'titre' => 'Des plantes de démarrage', 'etat' => self::OK,
                    'detail' => implode(', ', array_slice($demarrage, 0, 4)) . (count($demarrage) > 4 ? '…' : '') . '.', 'source' => 'Théry, ch. 14 et 15'];
        }

        // 5. Un geste de taille noté pour les plantes de biomasse.
        if ($biomasseLignes !== []) {
            $notes = count(array_filter($biomasseLignes, static fn (array $l): bool => trim((string) ($l['conduite_taille'] ?? '')) !== ''));
            $c[] = $notes === count($biomasseLignes)
                ? ['cle' => 'geste', 'titre' => 'Un geste de taille noté', 'etat' => self::OK,
                    'detail' => 'Chaque plante de biomasse a sa conduite de taille.', 'source' => 'Théry, ch. 12']
                : ['cle' => 'geste', 'titre' => 'Un geste de taille noté', 'etat' => self::VIDE,
                    'detail' => 'Conduite de taille notée pour ' . $notes . ' des ' . count($biomasseLignes) . ' végétaux de biomasse (basitone, cépée, trogne). C’est elle qui règle la hauteur de coupe et la fréquence.',
                    'source' => 'Théry, ch. 12 ; fiche des conduites de taille'];
        }

        // 6. De quoi arroser si la pluie ne vient pas — le porteur (2026-09-20) : couper au bon moment, et arroser si besoin.
        $ressource = trim((string) $ressourceEau);
        if ($ressource === '') {
            $c[] = ['cle' => 'eau', 'titre' => 'De quoi arroser à la taille', 'etat' => self::VIDE,
                'detail' => 'La ressource en eau du site n’est pas dite (étape Matériel et eau). La taille est un stress : on la cale sur la pluie, et l’on arrose si elle ne vient pas.',
                'source' => 'Théry, ch. 12'];
        } elseif ($ressource === 'aucune') {
            $c[] = ['cle' => 'eau', 'titre' => 'De quoi arroser à la taille', 'etat' => self::ATTENTION,
                'detail' => 'Le site n’a pas d’eau à portée. Sans eau, une bonne part du bénéfice de la taille se perd et la reprise ralentit : on la cale sur la pluie, et l’on garde la régénération naturelle assistée en tête.',
                'source' => 'Théry, ch. 12'];
        } else {
            $c[] = ['cle' => 'eau', 'titre' => 'De quoi arroser à la taille', 'etat' => self::OK,
                'detail' => 'Une ressource en eau est notée pour le site. On coupe au moment que disent les plantes, avant ou après une pluie, et l’on arrose si elle ne vient pas.',
                'source' => 'Théry, ch. 12'];
        }

        return $c;
    }
}
