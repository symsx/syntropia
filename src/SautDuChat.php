<?php

declare(strict_types=1);

namespace Syntropia;

/**
 * Le saut du chat : le vocabulaire d'un constat, la comparaison au témoin, et la lecture des
 * conditions qui le rendent possible — pur, sans base.
 *
 * Voir `chantier-saut-du-chat.md`, Phase 2. Le saut du chat est l'EFFET de la perturbation, non un
 * objectif indépendant : il se déclenche par la taille (pas par la seule densité) et se reconnaît en
 * comparant à un témoin. Rien ici ne rend de verdict : `conditions()` dit ce que l'atelier SAIT (une
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

    /** Les trois états d'une condition. */
    public const OK = 'ok';
    public const ATTENTION = 'attention';
    public const VIDE = 'vide';

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
        'les-deux' => 'à la main et par des animaux',
    ];

    /** Au-delà de ce repère du scénario (0 à 28), une plante n'est plus une plante de démarrage : fin de Placenta 3. */
    private const FIN_DEMARRAGE = 12;

    /** La pousse de la plante rapportée à celle du témoin — `null` sans témoin mesuré (ou nul). */
    public static function rapport(?float $pousse, ?float $temoin): ?float
    {
        if ($pousse === null || $temoin === null || $temoin <= 0.0) {
            return null;
        }
        return round($pousse / $temoin, 1);
    }

    /**
     * Les noms des plantes de biomasse d'un scénario : celles qui portent la fonction « biomasse » ou
     * « relancer par la perturbation ».
     *
     * @param list<array<string, mixed>> $lignes les lignes de `scenario_ligne`
     * @return array<string, string> nom en minuscules → nom
     */
    public static function plantesDeBiomasse(array $lignes): array
    {
        $plantes = [];
        foreach ($lignes as $ligne) {
            $fonctions = Scenario::details($ligne)['fonctions'] ?? [];
            if (is_array($fonctions) && (in_array('perturbation', $fonctions, true) || in_array('biomasse', $fonctions, true))) {
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
     * @return list<array{cle: string, titre: string, etat: string, detail: string, source: string}>
     */
    public static function conditions(array $interventions, array $lignes, \DateTimeImmutable $aujourdhui,
        ?string $debut = null, ?string $fin = null, array $preparation = []): array
    {
        $perturbations = array_values(array_filter($interventions, static fn (array $i): bool => Perturbation::perturbe((string) ($i['type'] ?? ''))));
        $derniere = $perturbations[0] ?? null;
        $biomasse = self::plantesDeBiomasse($lignes);
        $demarrage = self::plantesDeDemarrage($lignes);
        $c = [];

        // 0. Le sol est préparé — avec la plantation, l'un des deux moments les plus coûteux du projet.
        $technique = trim((string) ($preparation['technique'] ?? ''));
        $realisee = trim((string) ($preparation['realisee'] ?? ''));
        $c[] = isset(self::PREPARATIONS[$technique])
            ? ['cle' => 'preparation', 'titre' => 'Le sol est préparé', 'etat' => self::OK,
                'detail' => self::PREPARATIONS[$technique] . (isset(self::REALISEES[$realisee]) ? ', ' . self::REALISEES[$realisee] : '')
                    . '. Préparer tôt, mais toujours couvrir le sol en attendant la plantation.',
                'source' => 'Théry, ch. 14 ; annexes']
            : ['cle' => 'preparation', 'titre' => 'Le sol est préparé', 'etat' => self::VIDE,
                'detail' => 'La préparation n’est pas notée (Réalisation, étape 1). Préparation et plantation sont les deux moments les plus coûteux du projet : les brûler se paie ensuite en désherbage et en rattrapage.',
                'source' => 'Théry, ch. 14'];

        // 0 bis. La plantation dense se fait dans une même période, sans structurante ajoutée après coup.
        $d0 = $debut !== null && $debut !== '' ? \DateTimeImmutable::createFromFormat('!Y-m-d', $debut) : false;
        $d1 = $fin !== null && $fin !== '' ? \DateTimeImmutable::createFromFormat('!Y-m-d', $fin) : false;
        $titreCampagne = 'La plantation est faite dans une même période';
        $sourceCampagne = 'Théry, ch. 11 ; Faraj ; Joala 4-1';
        if ($d0 === false) {
            $c[] = ['cle' => 'campagne', 'titre' => $titreCampagne, 'etat' => self::VIDE,
                'detail' => 'Le point zéro n’est pas noté. Une plantation dense se fait dans une même période, qui peut courir sur trois saisons — fin d’automne, hiver, printemps — selon les besoins des végétaux ; ce qui compte : aucune structurante ajoutée après coup.',
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
                    'detail' => $etendue . ' Dans une même saison de plantation. « Dense » : pas encore de repère chiffré.',
                    'source' => $sourceCampagne]
                : ['cle' => 'campagne', 'titre' => $titreCampagne, 'etat' => self::ATTENTION,
                    'detail' => $etendue . ' Plus d’une saison de plantation : des plants ajoutés bien après le point zéro occupent moins le sol que s’ils avaient été plantés avec le reste.',
                    'source' => $sourceCampagne];
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
                'detail' => 'Aucune plante de biomasse dans le scénario de cette parcelle. Sans masse suffisante à tailler, pas de perturbation efficace : le framboisier en bordure vivante est écarté pour cette raison.',
                'source' => 'Théry, ch. 15']
            : ['cle' => 'de_quoi_couper', 'titre' => 'De quoi couper', 'etat' => self::OK,
                'detail' => count($biomasse) . ' plante' . (count($biomasse) > 1 ? 's' : '') . ' de biomasse au scénario : ' . implode(', ', array_slice(array_values($biomasse), 0, 4)) . (count($biomasse) > 4 ? '…' : '') . '. Combien de masse suffit : pas encore de repère.',
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
                        'detail' => 'Pas coupées avec les autres : ' . implode(', ', $manquantes) . '.',
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
}
