<?php

declare(strict_types=1);

namespace Syntropia;

/**
 * Les sigles des plantes d'un plan — deux ou trois lettres pour repérer chaque espèce sur le dessin et dans la légende.
 * Pur.
 *
 * Décision du porteur (2026-10-01) : les sigles sont **calculés** ; « le principal est de pouvoir les distinguer, les
 * repérer sur le plan ». Ils ne valent que pour un plan donné : deux espèces d'un même plan n'ont jamais le même sigle,
 * mais une espèce peut changer de sigle d'un plan à l'autre selon ses voisines.
 *
 * La règle, dans l'ordre où les espèces apparaissent :
 *  - les deux premières lettres du nom usuel (« La » pour lavande) ;
 *  - un nom qui revient (des variétés) : ces deux lettres et l'initiale de la variété, pour chacune (« FeA », « FeC »
 *    pour les feijoas Apollo et Coolidge) ;
 *  - deux noms qui commencent pareil : la première lettre et la première lettre suivante qui les distingue (lavande « La »,
 *    lavandin « Lv ») ;
 *  - à défaut, les deux lettres et un numéro.
 * Deux sigles qui ne diffèrent que par un accent ou la casse (« Me », « Mé ») se confondent à l'œil : ils comptent pour
 * un seul.
 */
final class Sigles
{
    /**
     * @param list<array{nom: string, variete?: string}> $especes dans l'ordre où elles apparaissent sur le plan
     * @return list<string> un sigle par espèce, dans le même ordre, tous différents
     */
    public static function calculer(array $especes): array
    {
        $pris = [];
        $out = [];
        $occurrences = [];
        foreach ($especes as $e) {
            $cle = self::cle(self::lettres((string) $e['nom']));
            $occurrences[$cle] = ($occurrences[$cle] ?? 0) + 1;
        }
        foreach ($especes as $i => $e) {
            $lettres = self::lettres((string) $e['nom']);
            $variete = self::lettres((string) ($e['variete'] ?? ''));
            $tete = self::majuscule(mb_substr($lettres, 0, 2));
            $avecVariete = $variete !== '' ? $tete . mb_strtoupper(mb_substr($variete, 0, 1)) : null;
            // Un nom qui revient : chaque variété prend son initiale, la première comme les suivantes.
            $candidats = $avecVariete !== null && $occurrences[self::cle($lettres)] > 1 ? [$avecVariete, $tete] : [$tete];
            if ($avecVariete !== null) {
                $candidats[] = $avecVariete;
            }
            // La première lettre, puis chacune des suivantes : la première qui distingue.
            for ($j = 2; $j < mb_strlen($lettres); $j++) {
                $candidats[] = self::majuscule(mb_substr($lettres, 0, 1) . mb_substr($lettres, $j, 1));
            }
            $sigle = null;
            foreach ($candidats as $c) {
                if ($c !== '' && !isset($pris[self::cle($c)])) {
                    $sigle = $c;
                    break;
                }
            }
            for ($n = 2; $sigle === null; $n++) {
                $sigle = isset($pris[self::cle($tete . $n)]) ? null : $tete . $n;
            }
            $pris[self::cle($sigle)] = true;
            $out[$i] = $sigle;
        }
        return array_values($out);
    }

    /** Les lettres d'un nom, sans espaces ni ponctuation (les accents restent : « Mé » pour mélisse). */
    private static function lettres(string $nom): string
    {
        return (string) preg_replace('/[^\p{L}]+/u', '', $nom);
    }

    /** Ce qui distingue deux sigles à l'œil : sans accents ni casse (« Mé » et « Me » se confondent). */
    private static function cle(string $s): string
    {
        $sans = class_exists(\Normalizer::class)
            ? (string) preg_replace('/\p{Mn}+/u', '', (string) \Normalizer::normalize($s, \Normalizer::FORM_D))
            : (string) iconv('UTF-8', 'ASCII//TRANSLIT', $s);
        return strtolower($sans);
    }

    private static function majuscule(string $s): string
    {
        return mb_strtoupper(mb_substr($s, 0, 1)) . mb_strtolower(mb_substr($s, 1));
    }
}
