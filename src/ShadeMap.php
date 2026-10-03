<?php

declare(strict_types=1);

namespace Syntropia;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Ouvrir ShadeMap au bon endroit, au bon jour, à la bonne heure.
 *
 * Port de `Syntropie\ShadeMap` (dépôt de référence). **ShadeMap est un lien,
 * jamais un import** : la référence n'y a trouvé aucun format d'export
 * lisible — elle en a lu l'URL dans le code de l'application, faute de
 * documentation publique. Ce que l'on y lit (les dates de début et de fin
 * d'ombre) se note à la main. Le calcul d'ombre portée demande la hauteur d'un
 * obstacle et sa distance — *« un arbre se mesure mal, et il est souvent chez
 * le voisin »* — ; ShadeMap prend le relief et les bâtiments et donne les
 * dates directement.
 *
 * ```
 * https://shademap.app/@LAT,LON,ZOOMz,EPOCH_MSt,BEARINGb,PITCHp,MODEm
 * ```
 *
 * `LAT`/`LON` à cinq décimales ; `t` en millisecondes depuis 1970 ; `b`/`p`
 * la rotation et l'inclinaison de la vue ; `m` le mode — **0 normal,
 * 1 ensoleillement d'une période, 2 exposition annuelle**.
 *
 * ⚠ Un service tiers, et il le reste : le lien s'ouvre dans un autre onglet,
 * rien du projet n'y part hors la position. Une période **observée sur place
 * prime toujours** sur ce qu'il affiche. ⚠ Son code ignore la date de l'URL
 * quand le navigateur se déclare piloté (`navigator.webdriver`) : une capture
 * automatique ne montre pas le jour demandé.
 */
final class ShadeMap
{
    public const MODE_NORMAL = 0;
    public const MODE_ENSOLEILLEMENT = 1;
    public const MODE_ANNUEL = 2;

    /** Assez près pour voir une parcelle et ce qui l'ombre. */
    public const ZOOM_TERRAIN = 17;

    public static function lien(float $latitude, float $longitude,
                                ?DateTimeImmutable $quand = null,
                                int $mode = self::MODE_NORMAL,
                                int $zoom = self::ZOOM_TERRAIN): string
    {
        $quand ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        return sprintf('https://shademap.app/@%s,%s,%dz,%dt,0b,0p,%dm',
            self::coordonnee($latitude), self::coordonnee($longitude),
            $zoom, $quand->getTimestamp() * 1000, $mode);
    }

    /**
     * Les moments qui valent d'être regardés : les deux solstices et un
     * équinoxe, chacun **au midi solaire du lieu** (l'ombre y est la plus
     * courte de la journée : ce qui masque encore masque vraiment), et
     * l'exposition sur l'année.
     *
     * @return list<array{titre: string, quoi: string, url: string}>
     */
    public static function moments(float $latitude, float $longitude, ?int $annee = null): array
    {
        $annee ??= (int) date('Y');
        $dates = [
            ['21 décembre, le soleil au plus bas', 12, 21,
             "L'ombre est à son maximum. Si le soleil passe ici ce jour-là, il passera toute l'année."],
            ['21 juin, le soleil au plus haut', 6, 21,
             "L'ombre est à son minimum. Ce qui reste ombragé ce jour-là l'est en permanence."],
            ["22 septembre, l'équinoxe", 9, 22,
             "À 45° de latitude, l'ombre égale la hauteur de l'obstacle. C'est la mesure la plus simple à vérifier sur place."],
        ];
        $moments = [];
        foreach ($dates as [$titre, $m, $j, $quoi]) {
            $moments[] = ['titre' => $titre, 'quoi' => $quoi,
                'url' => self::lien($latitude, $longitude, self::midiSolaire($annee, $m, $j, $longitude))];
        }
        $moments[] = ['titre' => "L'exposition sur l'année",
            'quoi' => 'Le cumul des heures de soleil, zone par zone.',
            'url' => self::lien($latitude, $longitude, null, self::MODE_ANNUEL)];
        return $moments;
    }

    /**
     * Le midi solaire vrai d'un lieu, en UTC : le midi de l'horloge corrigé de
     * **la longitude** (quatre minutes par degré à l'est de Greenwich) et de
     * **l'équation du temps** (±16 minutes, par l'orbite elliptique et
     * l'inclinaison de l'axe). L'approximation courante en géométrie solaire,
     * à la minute près — assez pour savoir si un obstacle masque à midi.
     */
    public static function midiSolaire(int $annee, int $mois, int $jour, float $longitude): DateTimeImmutable
    {
        $date = new DateTimeImmutable(sprintf('%04d-%02d-%02d 12:00:00', $annee, $mois, $jour),
            new DateTimeZone('UTC'));
        $n = (int) $date->format('z') + 1;
        $b = 2 * M_PI * ($n - 81) / 364;
        $eot = 9.87 * sin(2 * $b) - 7.53 * cos($b) - 1.5 * sin($b);   // minutes
        $decalage = (int) round(($longitude / 15.0 + $eot / 60.0) * 3600);
        return $date->modify(($decalage >= 0 ? '-' : '+') . abs($decalage) . ' seconds');
    }

    /** Cinq décimales : la précision que l'application elle-même écrit. */
    private static function coordonnee(float $valeur): string
    {
        return rtrim(rtrim(number_format($valeur, 5, '.', ''), '0'), '.');
    }
}
