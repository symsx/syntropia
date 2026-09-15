<?php

declare(strict_types=1);

namespace Syntropia;

/**
 * Les documentations, écrites en markdown — un chapitre par livret.
 *
 * **Les questions vivent dans le code, les réponses dans la base** —
 * `Web/Reponses.php` le pose déjà pour le parcours guidé. Une
 * documentation est l'inverse : un texte suivi, que le porteur écrit et
 * corrige sans redémarrer le site. `contenu/*.md`, hors de `public/`,
 * sert exactement ce besoin, sur le modèle de `FicheTerrain.php` : une
 * classe qui prépare des données, une page qui les affiche en bouclant
 * dessus — pas de gabarit inventé à la volée dans le texte.
 *
 * FORMAT, volontairement pauvre :
 *
 *   # Titre du chapitre
 *
 *   Un paragraphe. Les sauts de ligne à l'intérieur d'un paragraphe ne
 *   sont qu'une largeur de colonne pour l'écrire — ils disparaissent à
 *   l'affichage. Une ligne vide sépare deux paragraphes.
 *
 *   **Ce qui est important** se met en gras avec deux astérisques.
 *
 *   # Le chapitre suivant
 *
 *   ...
 *
 * Rien d'autre n'est lu : pas de listes, pas de liens, pas de
 * sous-titres. Le jour où un chapitre en a vraiment besoin, l'étendre
 * ici — jamais en glissant du HTML dans le fichier markdown, qui
 * resterait alors illisible pour qui ne code pas.
 */
final class Documentation
{
    /**
     * @return list<array{id: string, titre: string, paragraphes: list<string>}>
     */
    public static function chapitres(string $chemin): array
    {
        if (!is_file($chemin)) {
            return [];
        }
        $texte = str_replace("\r\n", "\n", (string) file_get_contents($chemin));

        // `PREG_SPLIT_DELIM_CAPTURE` donne, dans l'ordre : ce qu'il y a
        // avant le premier titre (ignoré), puis titre, corps, titre, corps…
        $blocs = preg_split('/^#[ \t]+(.+?)[ \t]*$/m', $texte, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];

        $chapitres = [];
        $idsVus = [];
        for ($i = 1; $i < count($blocs); $i += 2) {
            $titre = trim($blocs[$i]);
            $corps = $blocs[$i + 1] ?? '';
            if ($titre === '') {
                continue;
            }
            $id = self::idLibre(self::id($titre), $idsVus);
            $idsVus[] = $id;
            $chapitres[] = ['id' => $id, 'titre' => $titre, 'paragraphes' => self::paragraphes($corps)];
        }
        return $chapitres;
    }

    /** @return list<string> — HTML déjà sûr (échappé), gras compris */
    private static function paragraphes(string $corps): array
    {
        $blocs = preg_split('/\n[ \t]*\n/', trim($corps)) ?: [];
        $paragraphes = [];
        foreach ($blocs as $bloc) {
            $bloc = trim(preg_replace('/\s+/', ' ', $bloc) ?? $bloc);
            if ($bloc === '') {
                continue;
            }
            $sur = htmlspecialchars($bloc, ENT_QUOTES, 'UTF-8');
            $paragraphes[] = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $sur) ?? $sur;
        }
        return $paragraphes;
    }

    private static function id(string $titre): string
    {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $titre);
        $slug = strtolower(is_string($ascii) ? $ascii : $titre);
        $slug = trim(preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '', '-');
        return $slug !== '' ? $slug : 'chapitre';
    }

    /** @param list<string> $dejaPris */
    private static function idLibre(string $id, array $dejaPris): string
    {
        $base = $id;
        $n = 2;
        while (in_array($id, $dejaPris, true)) {
            $id = $base . '-' . $n;
            $n++;
        }
        return $id;
    }
}
