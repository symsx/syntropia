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

    /**
     * Le glossaire, prêt pour la bulle au survol — texte brut, une phrase.
     *
     * Même fichier, même format que les deux livrets (`chapitres()`) :
     * un terme = un `# titre`, sa bulle = le premier paragraphe qui suit,
     * débarrassé de tout HTML (`bulle.textContent`, jamais `innerHTML`
     * — voir `assets/js/gabarit.js`, `initTermes()`). Les paragraphes
     * suivants, s'il y en a, ne servent qu'à la page `glossaire.php`
     * elle-même, pas à la bulle : une bulle qui déborde du bureau est
     * illisible, une phrase ne déborde jamais.
     *
     * @return array<string, string> id (slug du titre) => définition
     */
    public static function glossaire(string $chemin): array
    {
        $termes = [];
        foreach (self::chapitres($chemin) as $chapitre) {
            $premier = $chapitre['paragraphes'][0] ?? '';
            // paragraphes() a déjà échappé le texte (HTML sûr pour un
            // <p>) ; ici on veut l'inverse — du texte brut pour
            // `bulle.textContent`, jamais du HTML dans une bulle.
            $termes[$chapitre['id']] = trim(html_entity_decode(strip_tags($premier), ENT_QUOTES, 'UTF-8'));
        }
        return $termes;
    }

    /**
     * Un mot technique, souligné en pointillés, expliqué au survol.
     *
     * `$glossaire` vient de `self::glossaire()` — appelée une fois par
     * page, jamais par mot : lire et découper le fichier à chaque mot
     * serait un travail refait pour rien. Si le mot n'a pas d'entrée
     * dans `contenu/glossaire.md`, le texte s'affiche seul, sans bulle
     * ni soulignement — un mot mal orthographié ici ne casse rien,
     * il redevient juste un mot ordinaire.
     *
     * @param array<string, string> $glossaire
     */
    public static function terme(array $glossaire, string $id, string $texte): string
    {
        $explication = $glossaire[$id] ?? null;
        $sur = htmlspecialchars($texte, ENT_QUOTES, 'UTF-8');
        if ($explication === null || $explication === '') {
            return $sur;
        }
        return '<span class="terme" data-explique="'
            . htmlspecialchars($explication, ENT_QUOTES, 'UTF-8') . '">' . $sur . '</span>';
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
