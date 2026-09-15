<?php

declare(strict_types=1);

namespace Syntropia;

/**
 * Les relevés de sol, sous la forme qu'ils prennent SUR LE TERRAIN : du papier.
 *
 * Porté de `application/src/FicheTerrain.php` (dépôt de référence), sans
 * changement de contenu — un protocole de relevé de sol ne dépend pas du
 * nom de l'application qui l'imprime. Voir `skills-map/nodes/diagnostic-des-sols.md`
 * pour le domaine de connaissance ; cette classe ne porte que la grille à
 * remplir, pas le protocole complet (geste, matériel, pièges), qui reste
 * hors dépôt.
 *
 * POURQUOI DU PAPIER, DANS UNE APPLICATION WEB
 *
 * Ce n'est pas le protocole qu'on emporte. On emporte **la grille à
 * remplir**, accroupi au bord d'un trou, les mains terreuses, souvent
 * sans réseau. Le téléphone ne remplace pas un crayon dans cette
 * position-là, et un relevé mal saisi vaut moins qu'un relevé griffonné.
 *
 * TROIS TEMPS, ET LE PREMIER EST CELUI QU'ON OUBLIE
 *
 *   `avant` — **ce qu'on va vivre** : le geste, la durée, ce qu'on va
 *             rencontrer. Porté par la fiche.
 *   `sens`  — ce que le mot veut dire, quand le libellé ne suffit pas.
 *             Porté par le champ. Absent quand il se suffit.
 *   `sert`  — ce que la mesure permet de décider ensuite. Porté par la
 *             fiche.
 *
 * `sert` dit **où l'on est**, jamais **quel chemin prendre ensuite** —
 * un densimètre ne prescrit pas un labour.
 *
 * La table `releve_sol` n'existe pas encore côté syntropia — la saisie
 * numérique reste à construire, comme dans le dépôt de référence. La
 * fiche papier, elle, fonctionne dès aujourd'hui.
 */
final class FicheTerrain
{
    /**
     * Ce qui se note pour TOUTE mesure, quelle qu'elle soit.
     *
     * L'état d'humidité et le délai depuis la dernière pluie conditionnent
     * presque tous les résultats : une densité apparente mesurée sur sol sec
     * et une autre sur sol ressuyé ne se comparent pas. Les demander une
     * fois en tête évite de les répéter — et évite surtout de les oublier.
     *
     * @return list<array{libelle: string, unite: ?string, champ: ?string,
     *     sens: ?string}>
     */
    public static function conditions(): array
    {
        return [
            ['libelle' => 'Date', 'unite' => null, 'champ' => 'date',
             'sens' => null],
            ['libelle' => 'Parcelle', 'unite' => null, 'champ' => null,
             'sens' => "Le sol appartient à la parcelle, non au site : "
                 . "un bas-fond argileux et un haut de coteau sableux sont deux mondes."],
            ['libelle' => 'Point de relevé', 'unite' => 'un repère qu\'on retrouvera',
             'champ' => 'point',
             'sens' => "Un piquet, des coordonnées GPS, un repère fixe. "
                 . "**On reviendra mesurer ici même dans cinq ans** — sans cela, "
                 . "la comparaison ne vaut rien."],
            ['libelle' => 'Campagne', 'unite' => 'point zéro · N+1 · N+3…',
             'champ' => 'campagne',
             'sens' => "Quel passage est-ce. Le **point zéro** se prend avant "
                 . "le bâchage et avant tout travail du sol : une fois la prairie "
                 . "décomposée, on ne peut plus savoir d'où l'on partait."],
            ['libelle' => 'Est-ce le témoin ?', 'unite' => 'oui / non',
             'champ' => 'temoin',
             'sens' => "Le **témoin** est un point de comparaison laissé tel quel, "
                 . "à quelques mètres, sur le même sol — prairie voisine, zone non "
                 . "ouverte, ou parcelle conduite classiquement. **Chaque relevé se "
                 . "fait en double** : un point dans la parcelle, un dans le témoin."],
            ['libelle' => "État d'humidité", 'unite' => 'détrempé · ressuyé · sec dur',
             'champ' => 'etat_humidite',
             'sens' => "**Ressuyé** = l'eau de la dernière pluie a fini de "
                 . "s'égoutter, la terre est fraîche sans coller. C'est l'état où "
                 . "l'on mesure — et il faut **toujours le même** d'une campagne "
                 . "à l'autre."],
            ['libelle' => 'Jours depuis la dernière pluie', 'unite' => 'j',
             'champ' => 'jours_depuis_pluie',
             'sens' => "Avec l'état d'humidité, il dit **à quelle vitesse ce sol "
                 . "se ressuie**."],
            ['libelle' => 'Relevé par', 'unite' => null, 'champ' => 'saisi_par',
             'sens' => "Le geste varie d'une personne à l'autre — la profondeur "
                 . "d'un coup de bêche, la lecture d'un agrégat."],
        ];
    }

    /**
     * Ce que le bloc « conditions » permet de décider.
     *
     * Rendu en tête de la fiche imprimée, sous le titre du bloc.
     */
    public static function sertLesConditions(): string
    {
        return "Ces huit lignes ne mesurent rien — **elles rendent les mesures "
            . "comparables**. Sans le point, on ne revient pas au même endroit ; "
            . "sans le témoin, on ne sait pas distinguer l'effet du système de "
            . "celui d'une année pluvieuse ; sans l'état d'humidité, deux "
            . "densités ne se comparent pas.";
    }

    /**
     * Les sept fiches, dans l'ordre où on les fait sur le terrain.
     *
     * L'ordre n'est pas celui des numéros : **le profil s'ouvre en premier**
     * parce que le trou sert ensuite à l'infiltration et au comptage, et
     * qu'on ne rebouche qu'une fois. La flore se relève avant de piétiner.
     *
     * @return array<string, array{numero: string, titre: string, quand: ?string,
     *     avant: string, sert: string, note: ?string, champs: list<array{libelle: string, unite: ?string,
     *     champ: ?string, lignes?: int}>, lecture: array<string, string>}>
     */
    public static function fiches(): array
    {
        return [
            'flore' => [
                'numero' => '06',
                'titre' => 'Relevé de flore',
                'quand' => 'Début mai à fin juillet, toujours à la même période',
                'note' => "Avant de piétiner quoi que ce soit. Une plante n'est "
                    . "jamais indicatrice seule : on raisonne par groupes, et sur "
                    . "une pratique répétée, non sur un événement.",
                'avant' => 'Vous allez marcher lentement sur la zone, vous accroupir quatre fois, et regarder longtemps la même chose. **Comptez une heure. Rien à porter** — une fiche et un crayon. C\'est l\'exercice qui demande le plus de patience et le moins d\'effort : l\'œil met un moment à séparer les espèces, puis elles se détachent d\'un coup.',
                'sert' => '**Choisir des espèces adaptées** : ce qui pousse déjà spontanément dit ce qui poussera, et c\'est la seule méthode de régionalisation que le corpus autorise. Le relevé sert aussi à lire le sol — mais jamais par une plante seule : on raisonne par groupes, et sur une pratique répétée depuis plus de dix ans.',
                'champs' => [
                    ['libelle' => 'Pente et exposition', 'unite' => null, 'champ' => null],
                    ['libelle' => 'Nombre de strates', 'unite' => '1 · 2 · 3 · > 3', 'champ' => null],
                    ['libelle' => 'Densité du couvert', 'unite' => 'faible · moyenne · forte',
                     'champ' => null],
                    ['libelle' => 'Carré 1 — espèces et points', 'unite' => 'total = 6',
                     'champ' => null, 'lignes' => 3],
                    ['libelle' => 'Carré 2', 'unite' => 'total = 6', 'champ' => null, 'lignes' => 3],
                    ['libelle' => 'Carré 3', 'unite' => 'total = 6', 'champ' => null, 'lignes' => 3],
                    ['libelle' => 'Carré 4', 'unite' => 'total = 6', 'champ' => null, 'lignes' => 3],
                ],
                'lecture' => [
                    'Une espèce compte' => "si elle occupe au moins la surface d'une main étalée = 1 point",
                    'Six points par carré, exactement' => "si le total ne fait pas 6, recommencer le carré",
                    'Le sol nu et la litière comptent' => "ce sont des « espèces » du relevé",
                ],
            ],
            'profil' => [
                'numero' => '04',
                'titre' => 'Profil de sol et structure',
                'quand' => 'Sol ressuyé. Ni détrempé, ni sec',
                'note' => "Le trou sert ensuite à l'infiltration et au comptage : "
                    . "on ne le rebouche qu'à la fin.",
                'avant' => 'Vous allez ouvrir un trou de 30 cm à la bêche et regarder la tranche. **Une demi-heure, à genoux.** Vous verrez des couches de couleur et de texture différentes, et peut-être des racines qui s\'arrêtent net à une profondeur : c\'est ce qu\'on cherche. **Ce trou servira aussi aux deux mesures suivantes** — on ne le rebouche qu\'à la fin.',
                'sert' => '**Savoir s\'il faut décompacter avant de planter, et jusqu\'où.** Une semelle de labour au-delà de 30 cm est le seul cas où le corpus admet une machine. Et l\'endroit où les racines s\'arrêtent dit la profondeur réellement disponible.',
                'champs' => [
                    ['libelle' => 'Profondeur ouverte', 'unite' => 'cm', 'champ' => 'profondeur_cm'],
                    ['libelle' => 'Horizons repérés', 'unite' => 'de … à … cm, couleur, structure',
                     'champ' => null, 'lignes' => 3],
                    ['libelle' => 'Structure dominante', 'unite' => null,
                     'champ' => 'structure_dominante'],
                    ['libelle' => 'Test du couteau — résistance nette à', 'unite' => 'cm',
                     'champ' => 'semelle_profondeur_cm'],
                    ['libelle' => 'Racines — arrêt ou coude horizontal à', 'unite' => 'cm',
                     'champ' => 'racines_arret_cm'],
                    ['libelle' => 'Porosité, galeries', 'unite' => null, 'champ' => 'porosite'],
                    ['libelle' => 'Hydromorphie — taches rouille ou grises à', 'unite' => 'cm',
                     'champ' => 'hydromorphie_cm'],
                    ['libelle' => 'Odeur', 'unite' => null, 'champ' => 'odeur'],
                    ['libelle' => 'Photo prise', 'unite' => 'nom du fichier', 'champ' => 'photo'],
                ],
                'lecture' => [
                    'Agrégats arrondis et friables, qui se séparent seuls' => 'bonne structure biologique',
                    'Mottes anguleuses, à faces lisses et planes' => 'structure compactée',
                    'Blocs massifs sans agrégats visibles' => 'tassement',
                ],
            ],
            'densite' => [
                'numero' => '01',
                'titre' => 'Densité apparente',
                'quand' => 'Sol ressuyé. Trois prélèvements par point',
                'note' => "Le volume du cylindre se calcule une fois pour toutes, "
                    . "avant de partir.",
                'avant' => 'Vous allez enfoncer un tube dans la terre, trois fois, et rapporter les carottes à la maison. **20 minutes sur place**, puis un séchage au four et une pesée. C\'est la mesure la moins spectaculaire des sept, et **celle qui se compare le mieux d\'une année à l\'autre**.',
                'sert' => '**Voir si le sol se décompacte au fil des ans.** C\'est le meilleur candidat pour un avant/après : chiffré, reproductible, matériel dérisoire. Ce qui compte n\'est pas la valeur absolue mais **l\'écart** — un sol argileux et un sol sableux n\'ont pas la même densité de départ.',
                'champs' => [
                    ['libelle' => 'Profondeur du prélèvement', 'unite' => 'cm',
                     'champ' => 'profondeur_cm'],
                    ['libelle' => 'Volume du cylindre', 'unite' => 'cm³', 'champ' => null],
                    ['libelle' => 'Masses sèches — 1 · 2 · 3', 'unite' => 'g', 'champ' => null],
                    ['libelle' => 'Mode de séchage', 'unite' => 'four 105 °C, 24 h', 'champ' => null],
                    ['libelle' => 'Densité apparente — moyenne', 'unite' => 'g/cm³',
                     'champ' => 'densite_apparente'],
                ],
                'lecture' => [
                    '≈ 1,0' => 'sol très poreux — ce qu\'on trouve en prairie et en forêt',
                    '1,1 à 1,4' => 'intermédiaire',
                    '1,5 à 1,7' => 'sol tassé — « là ça commence à aller mal »',
                    '> 1,7' => 'très tassé',
                ],
            ],
            'infiltration' => [
                'numero' => '02',
                'titre' => 'Infiltration',
                'quand' => 'Toujours dans les mêmes conditions d\'humidité',
                'note' => "Deux anneaux : celui du dehors force l'eau du dedans à "
                    . "descendre au lieu de s'étaler.",
                'avant' => 'Vous allez verser de l\'eau dans un trou et regarder le niveau descendre, montre en main. **De 30 minutes à une heure, dont l\'essentiel à attendre.** C\'est le moment le plus lent du relevé — profitez-en pour remplir les autres lignes.',
                'sert' => '**Mesurer ce que le sol garde l\'eau, chiffré.** Et c\'est ce qui informe le rythme d\'arrosage des trois premières années — un sol qui boit vite demande des passages plus courts et plus fréquents, un sol qui fait tampon supporte « long et espacé ».',
                'champs' => [
                    ['libelle' => 'Version', 'unite' => 'flaque · double anneau', 'champ' => null],
                    ['libelle' => "Hauteur d'eau initiale", 'unite' => 'mm', 'champ' => null],
                    ['libelle' => 'Relevés — 5 min · 10 min · 20 min', 'unite' => 'mm',
                     'champ' => null, 'lignes' => 2],
                    ['libelle' => 'Vitesse stabilisée', 'unite' => 'mm/h',
                     'champ' => 'infiltration_mm_h'],
                ],
                'lecture' => [],
            ],
            'slake' => [
                'numero' => '03',
                'titre' => 'Slake test — stabilité structurale',
                'quand' => 'Agrégats séchés à l\'air, comparables entre eux',
                'note' => "**La photo est le vrai relevé de ce test.** Elle se "
                    . "relit dans cinq ans.",
                'avant' => 'Vous allez poser deux mottes sèches dans deux bocaux d\'eau et les regarder. **Un quart d\'heure.** C\'est le plus frappant des sept tests : en dix minutes, une motte instable se défait en nuage sous vos yeux, l\'autre reste entière. **Photographiez** — c\'est le vrai relevé.',
                'sert' => '**Savoir si le sol se refermera à la première pluie.** Il mesure la stabilité des agrégats, donc l\'effet cumulé de la biologie et de la matière organique — ce qu\'aucune analyse chimique ne montre.',
                'champs' => [
                    ['libelle' => 'Points comparés', 'unite' => 'parcelle / témoin', 'champ' => null],
                    ['libelle' => 'Taille des agrégats', 'unite' => 'cm', 'champ' => null],
                    ['libelle' => "Durée de séchage à l'air", 'unite' => 'j', 'champ' => null],
                    ['libelle' => 'À 1 min', 'unite' => null, 'champ' => null, 'lignes' => 2],
                    ['libelle' => 'À 10 min', 'unite' => null, 'champ' => null, 'lignes' => 2],
                    ['libelle' => 'Verdict', 'unite' => null, 'champ' => 'slake_verdict'],
                    ['libelle' => 'Photo prise', 'unite' => 'nom du fichier', 'champ' => 'photo'],
                ],
                'lecture' => [
                    'Garde sa forme, eau claire, peu de dépôt' => 'structure stable — bonne agrégation biologique',
                    'Gonfle, se fissure, quelques fragments tombent' => 'intermédiaire',
                    'S\'effondre en nuage, eau trouble, dépôt important' => 'structure instable — elle se refermera à la première pluie',
                ],
            ],
            'vers' => [
                'numero' => '05',
                'titre' => 'Comptage des vers de terre',
                'quand' => 'Automne ou printemps. Jamais en été ni en gel',
                'note' => "La fenêtre est étroite, et l'heure doit être la même à "
                    . "chaque campagne.",
                'avant' => 'Vous allez extraire un bloc de terre de 20 cm de côté et le défaire à la main, morceau par morceau, pour compter ce qui bouge. **Une demi-heure, accroupi, les mains dans la terre.** Trois blocs, et on remet tout en place.',
                'sert' => '**Suivre la vie du sol, et c\'est l\'indicateur qui bouge le plus vite** : mesurable dès la fin de la première année, quand les autres mettent trois ans.',
                'champs' => [
                    ['libelle' => 'Saison', 'unite' => 'automne · printemps', 'champ' => null],
                    ['libelle' => 'Volume du bloc', 'unite' => 'cm — 20 × 20 × 20',
                     'champ' => 'volume_bloc_cm'],
                    ['libelle' => 'Comptages — 1 · 2 · 3', 'unite' => 'vers', 'champ' => null],
                    ['libelle' => 'Moyenne', 'unite' => 'vers / bloc', 'champ' => 'vers_par_bloc'],
                    ['libelle' => 'Dont anéciques', 'unite' => null, 'champ' => 'dont_aneciques'],
                    ['libelle' => 'Turricules sur 50 × 50', 'unite' => null,
                     'champ' => 'turricules_50x50'],
                    ['libelle' => 'Référence locale', 'unite' => 'prairie voisine, bois…',
                     'champ' => null],
                ],
                'lecture' => [
                    'Épigés' => 'petits, rouges vifs, en surface et dans la litière',
                    'Anéciques' => 'grands, sombres à l\'avant. Galeries verticales. **Les plus précieux**',
                    'Endogés' => 'pâles, peu pigmentés, galeries horizontales',
                ],
            ],
            'texture' => [
                'numero' => '07',
                'titre' => 'Texture, drainage, hydromorphie',
                'quand' => 'Sol humide pour le boudin. Le drainage s\'observe après une pluie',
                'note' => "Un sol peut être drainant en surface et engorgé à 40 cm : "
                    . "c'est le cas le plus trompeur, et le test du trou seul ne le voit pas.",
                'avant' => 'Vous allez humidifier un peu de terre et la rouler entre vos doigts pour en faire un boudin. **20 minutes, rien à porter.** C\'est le seul test qui se fait entièrement à la main, et le plus facile à refaire ailleurs sur la parcelle — n\'hésitez pas à le répéter à trois endroits.',
                'sert' => '**Deux décisions.** Le drainage commande le choix des espèces de bordure, souvent bien avant le pH. Et avec la texture, il commande la réserve en eau, donc le rythme d\'arrosage.',
                'champs' => [
                    ['libelle' => 'Texture — test du boudin', 'unite' => null, 'champ' => 'texture'],
                    ['libelle' => 'Drainage constaté', 'unite' => null, 'champ' => 'drainage'],
                    ['libelle' => 'Hydromorphie', 'unite' => null, 'champ' => 'hydromorphie'],
                    ['libelle' => 'Profondeur des traces', 'unite' => 'cm', 'champ' => 'hydromorphie_cm'],
                ],
                'lecture' => [
                    'Ne tient pas en boule, crisse' => 'sableuse',
                    'Boule oui, boudin non' => 'limono-sableuse',
                    'Boudin qui casse à moins de 3 cm' => 'limoneuse',
                    'Boudin de 3 à 7 cm, mat et doux' => 'limono-argileuse',
                    'Boudin de plus de 7 cm, anneau qui craquelle' => 'argilo-limoneuse',
                    'Anneau fermé sans fissure, brillant sous le pouce' => 'argileuse',
                ],
            ],
        ];
    }

    /**
     * Ce qu'on emporte, et ce qu'on ne laisse pas au hasard.
     *
     * @return list<string>
     */
    public static function avantDePartir(): array
    {
        return [
            "De l'eau — pour boire, et pour le slake test",
            'Gants, bêche, couteau, mètre ruban, crayon de papier',
            'Un appareil photo : la photo est le relevé qui se relit dans cinq ans',
            'Repérer les points AVANT de creuser, et les marquer pour les retrouver',
        ];
    }
}
