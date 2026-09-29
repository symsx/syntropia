-- ---------------------------------------------------------------------
-- ESPÈCES — premier seed curaté, chantier « scénario de plantation »
--
-- Du DML, pas du schéma : voir bin/dev.py § SCHEMAS pour pourquoi ce
-- fichier reste séparé de 01-syntropia.sql. `INSERT IGNORE` : rejoué
-- sans erreur, ne réécrase jamais une ligne déjà là (une surcharge ou
-- une correction faite depuis l'interface prime sur ce seed).
--
-- `site_id = 0` — valeur du cadre, partagée par tous les sites (voir le
-- commentaire de la table `espece` dans 01-syntropia.sql).
--
-- Sourcé uniquement de ce que le dépôt affirme déjà, jamais déduit :
--   - `connaissances/formation-joala/strates-et-lumiere.md` — la
--     table strate/lumière et ses exemples (Dordogne, sud).
--   - `connaissances/formation-joala/systemes-et-abondance.md` — la
--     grille colonisation/accumulation/abondance.
--   - `connaissances/livre-bienvenue-en-syntropie/
--     ch11-construire-un-scenario-planter-un-film.md` — l'étude de cas
--     chiffrée « Les eucalyptus », seul exemple complet du corpus avec
--     des entrées/sorties de strate réelles.
--
-- Ce qui n'est PAS rempli : `biomasse`, `rapport_cn`, `origine`,
-- `serre_necessaire`, `deplacable`, `espacement_*`. Aucune de ces
-- valeurs n'est donnée par les sources ci-dessus — les laisser NULL
-- plutôt que d'inventer un chiffre plausible. `systeme` ne se pose que
-- pour les six espèces que la grille de `systemes-et-abondance.md`
-- nomme explicitement ; les espèces de colonisation (mousses, lichens,
-- cactus, agaves) ne sont pas seedées ici — peu utiles à un scénario de
-- jardin cultivé, comme le note le plan de ce chantier.
-- ---------------------------------------------------------------------

-- Système d'accumulation — `systemes-et-abondance.md`, grille enseignée.
INSERT IGNORE INTO espece
  (site_id, nom_scientifique, nom_usuel, role, strates_possibles, systeme, source, statut_local)
VALUES
  (0, 'Robinia pseudoacacia', 'Robinier faux-acacia', 'biomasse', NULL, 'accumulation',
   'connaissances/formation-joala/systemes-et-abondance.md', 'repère de conception, non vérifié'),
  (0, 'Populus', 'Peuplier', 'biomasse', NULL, 'accumulation',
   'connaissances/formation-joala/systemes-et-abondance.md', 'repère de conception, non vérifié'),
  (0, 'Salix', 'Saule', 'biomasse', NULL, 'accumulation',
   'connaissances/formation-joala/systemes-et-abondance.md', 'repère de conception, non vérifié'),
  (0, 'Corylus avellana', 'Noisetier', 'biomasse|production', NULL, 'accumulation',
   'connaissances/formation-joala/systemes-et-abondance.md', 'repère de conception, non vérifié'),
  (0, 'Crataegus monogyna', 'Aubépine', 'biomasse|protection', NULL, 'accumulation',
   'connaissances/formation-joala/systemes-et-abondance.md',
   'épineuse — protection contre l\'abroutement au démarrage, non vérifié localement'),
  (0, 'Prunus spinosa', 'Prunellier', 'biomasse|protection', NULL, 'accumulation',
   'connaissances/formation-joala/systemes-et-abondance.md',
   'épineux — protection contre l\'abroutement au démarrage, non vérifié localement'),
  (0, 'Carpinus betulus', 'Charme', 'biomasse', NULL, 'accumulation',
   'connaissances/formation-joala/systemes-et-abondance.md', 'repère de conception, non vérifié'),
  (0, 'Betula pendula', 'Bouleau', 'biomasse', NULL, 'accumulation',
   'connaissances/formation-joala/systemes-et-abondance.md', 'repère de conception, non vérifié');

-- Système d'abondance — `systemes-et-abondance.md`, grille enseignée.
INSERT IGNORE INTO espece
  (site_id, nom_scientifique, nom_usuel, role, strates_possibles, systeme, entree_scenario, sortie_scenario, source, statut_local)
VALUES
  (0, 'Malus domestica', 'Pommier', 'production', 'haute', 'abondance', NULL, NULL,
   'connaissances/formation-joala/systemes-et-abondance.md; strates-et-lumiere.md',
   'strate confirmée par strates-et-lumiere.md (Dordogne)'),
  (0, 'Pyrus communis', 'Poirier', 'production', 'haute', 'abondance', NULL, NULL,
   'connaissances/formation-joala/systemes-et-abondance.md; strates-et-lumiere.md',
   'strate confirmée par strates-et-lumiere.md (Dordogne)'),
  (0, 'Cydonia oblonga', 'Cognassier', 'production', NULL, 'abondance', NULL, NULL,
   'connaissances/formation-joala/systemes-et-abondance.md', 'repère de conception, non vérifié'),
  (0, 'Juglans regia', 'Noyer', 'production', NULL, 'abondance', NULL, NULL,
   'connaissances/formation-joala/systemes-et-abondance.md', 'repère de conception, non vérifié'),
  (0, 'Prunus avium', 'Cerisier', 'production', NULL, 'abondance', NULL, NULL,
   'connaissances/formation-joala/systemes-et-abondance.md', 'repère de conception, non vérifié');

-- L'étude de cas « Les eucalyptus » du ch. 11 — seul exemple du corpus
-- avec entrées/sorties de strate réellement chiffrées. `systeme` non
-- posé : le chapitre ne classe pas ces espèces par système, seulement
-- par strate et par rôle dans le scénario.
INSERT IGNORE INTO espece
  (site_id, nom_scientifique, nom_usuel, role, strates_possibles, entree_scenario, sortie_scenario, source, statut_local)
VALUES
  (0, 'Eucalyptus gunnii', 'Eucalyptus gunni', 'biomasse', 'emergente', 'placenta3', 'climax',
   'ch11-construire-un-scenario-planter-un-film.md, étude de cas « Les eucalyptus »',
   'gélif hors climat doux — le chapitre propose frêne, orme, charme ou mûrier blanc en trogne comme émergent de remplacement ailleurs'),
  (0, 'Fraxinus excelsior', 'Frêne', 'secours', 'emergente', 'placenta3', 'sec2',
   'ch11-construire-un-scenario-planter-un-film.md, étude de cas « Les eucalyptus »',
   'espèce de secours semée en poquets si l\'émergent principal échoue — disponibilité locale à vérifier'),
  (0, 'Morus alba', 'Mûrier blanc', 'production', 'haute', 'placenta3', 'climax',
   'ch11-construire-un-scenario-planter-un-film.md, étude de cas « Les eucalyptus »',
   'peut vivre jusqu\'à un siècle et demi selon le chapitre'),
  (0, 'Sambucus nigra', 'Sureau', 'production|biomasse', 'haute', 'placenta3', NULL,
   'ch11-construire-un-scenario-planter-un-film.md, étude de cas « Les eucalyptus »',
   'sort du tableau vers 20-25 ans quand l\'ombre du mûrier blanc le rattrape, pas par fin de vie'),
  (0, 'Elaeagnus umbellata', 'Chalef d\'automne', 'biomasse', 'haute', 'placenta3', NULL,
   'ch11-construire-un-scenario-planter-un-film.md, étude de cas « Les eucalyptus »',
   'ne fructifie qu\'en strate émergente — ici recouvert d\'ombre, fournit boutures et biomasse seulement'),
  (0, 'Ribes nigrum', 'Cassis', 'production', 'moyenne', 'placenta3', 'sec2',
   'ch11-construire-un-scenario-planter-un-film.md, étude de cas « Les eucalyptus »; strates-et-lumiere.md',
   'longévité moyenne 20 ans dans l\'exemple ; strate confirmée par strates-et-lumiere.md sous le nom générique « ribes »'),
  (0, 'Lonicera fragrantissima', 'Lonicera fragrantissima', 'biomasse', 'moyenne', 'placenta3', NULL,
   'ch11-construire-un-scenario-planter-un-film.md, étude de cas « Les eucalyptus »', 'boutures en tipi'),
  (0, 'Lycium barbarum', 'Goji', 'biomasse|perturbation', 'moyenne', 'placenta3', NULL,
   'ch11-construire-un-scenario-planter-un-film.md; connaissances/formation-joala/calendrier-et-taille-hivernale.md; tortues-et-succession.md',
   'support de perturbation majeur — résistant au gel et aux limaces, racines profondes non concurrentes (tortues-et-succession.md)'),
  (0, 'Buddleja davidii', 'Buddleia', 'biomasse', 'moyenne', 'placenta3', NULL,
   'ch11-construire-un-scenario-planter-un-film.md; connaissances/formation-joala/tortues-et-succession.md',
   'ATTENTION espèce invasive selon le contexte régional — n\'utiliser que des variétés stériles (tortues-et-succession.md, visite abeille zèbre)'),
  (0, 'Melissa officinalis', 'Mélisse', 'biomasse', 'basse', 'placenta1', 'sec1',
   'ch11-construire-un-scenario-planter-un-film.md; strates-et-lumiere.md; calendrier-et-taille-hivernale.md',
   'bordure vivante — jusqu\'à trois-quatre coupes annuelles rapportées (calendrier-et-taille-hivernale.md) ; peu de strates basses utilisables selon strates-et-lumiere.md'),
  (0, 'Cynara scolymus', 'Artichaut', 'production|biomasse|bloque_herbe', NULL, NULL, NULL,
   'ch11-construire-un-scenario-planter-un-film.md',
   'planté aux extrémités des planches — hors tableau de stratification dans l\'exemple du ch. 11');

UPDATE espece SET bloque_herbe = 1
  WHERE site_id = 0 AND nom_scientifique IN ('Melissa officinalis', 'Cynara scolymus');

-- Le tableau des besoins en lumière — `strates-et-lumiere.md`, exemples
-- donnés pour le sud de la Dordogne. Explicitement signalé par la
-- source elle-même comme un point de départ, pas un catalogue
-- transposable tel quel à un autre climat — voir `statut_local`.
INSERT IGNORE INTO espece
  (site_id, nom_scientifique, nom_usuel, role, strates_possibles, source, statut_local)
VALUES
  (0, 'Vitis vinifera', 'Vigne', 'production', 'haute',
   'connaissances/formation-joala/strates-et-lumiere.md',
   'exemple Dordogne, sud — le support signale que la vigne n\'est PAS une strate émergente, contrairement à l\'intuition courante'),
  (0, 'Allium ursinum', 'Ail des ours', 'production', 'haute',
   'connaissances/formation-joala/strates-et-lumiere.md', 'exemple Dordogne, sud'),
  (0, 'Vicia faba', 'Fève', 'production|azote', 'emergente',
   'connaissances/formation-joala/strates-et-lumiere.md; ch11-construire-un-scenario-planter-un-film.md',
   'émergente au moment de l\'année selon strates-et-lumiere.md ; entrée/sortie placenta1 dans l\'étude de cas du ch. 11'),
  (0, 'Fragaria vesca', 'Fraise des bois', 'production', 'basse',
   'connaissances/formation-joala/strates-et-lumiere.md', 'exemple Dordogne, sud'),
  (0, 'Fragaria ×ananassa', 'Fraise du commerce', 'production', 'haute',
   'connaissances/formation-joala/strates-et-lumiere.md',
   'sélectionnée et manipulée pour produire en strate haute, contrairement à la fraise des bois — exemple Dordogne, sud'),
  (0, 'Cucurbita pepo', 'Courgette', 'production', 'moyenne',
   'connaissances/formation-joala/strates-et-lumiere.md', 'exemple Dordogne, sud'),
  (0, 'Cucurbita maxima', 'Courge', 'production', 'moyenne',
   'connaissances/formation-joala/strates-et-lumiere.md', 'exemple Dordogne, sud'),
  (0, 'Cucumis sativus', 'Concombre', 'production', 'moyenne',
   'connaissances/formation-joala/strates-et-lumiere.md', 'exemple Dordogne, sud'),
  (0, 'Solanum lycopersicum', 'Tomate', 'production', 'haute',
   'connaissances/formation-joala/strates-et-lumiere.md', 'solanacée — exemple Dordogne, sud'),
  (0, 'Capsicum annuum', 'Poivron', 'production', 'haute',
   'connaissances/formation-joala/strates-et-lumiere.md', 'solanacée — exemple Dordogne, sud'),
  (0, 'Solanum melongena', 'Aubergine', 'production', 'haute',
   'connaissances/formation-joala/strates-et-lumiere.md', 'solanacée — exemple Dordogne, sud'),
  (0, 'Solanum tuberosum', 'Pomme de terre', 'production', 'emergente',
   'connaissances/formation-joala/strates-et-lumiere.md',
   'commence son cycle en strate émergente dans un système qui évolue dans le temps et l\'espace — exemple Dordogne, sud'),
  (0, 'Calendula officinalis', 'Souci / calendula', 'production|refuge', 'emergente',
   'connaissances/formation-joala/strates-et-lumiere.md',
   'petite taille — ne se déploie qu\'en bordure ou au cœur des planches en tout début de saison, exemple Dordogne, sud');

-- Silphie et topinambour — occupation temporaire de l'espace central en
-- attendant la maturité des arbres, `tortues-et-succession.md`.
INSERT IGNORE INTO espece
  (site_id, nom_scientifique, nom_usuel, role, strates_possibles, source, statut_local)
VALUES
  (0, 'Silphium perfoliatum', 'Silphie', 'biomasse|couvre_sol', 'haute',
   'connaissances/formation-joala/tortues-et-succession.md',
   'très envahissante — occupation temporaire de l\'espace central en attendant la maturité des arbres, à surveiller selon le contexte'),
  (0, 'Helianthus tuberosus', 'Topinambour', 'biomasse|couvre_sol', 'haute',
   'connaissances/formation-joala/tortues-et-succession.md',
   'très envahissant — occupation temporaire de l\'espace central, à surveiller selon le contexte ; peut aussi tenir le rôle de carapace provisoire entre deux tortues');

-- Le tournesol — exemple posé par le porteur, 2026-09-18, pour la
-- distinction de variété : deux lignes, même `nom_scientifique`, la
-- `variete` seule change le rôle et la conduite attendue. Le ch. 11
-- rapporte un tournesol émergent (4,50 m en année 1, entrée/sortie
-- placenta1→placenta2) sans préciser de variété — c'est la ligne
-- `variete = ''` ci-dessous. Le tournesol à une fleur (peu de
-- ramifications, beaucoup de feuillage) n'est sourcé nulle part encore
-- dans `connaissances/` avec ce niveau de détail : `statut_local` le
-- dit explicitement, à vérifier avant de s'y fier pour un scénario réel.
INSERT IGNORE INTO espece
  (site_id, nom_scientifique, variete, nom_usuel, role, strates_possibles, entree_scenario, sortie_scenario, source, statut_local)
VALUES
  (0, 'Helianthus annuus', '', 'Tournesol', 'production|biomasse', 'emergente', 'placenta1', 'placenta2',
   'ch11-construire-un-scenario-planter-un-film.md, étude de cas « Les eucalyptus »',
   'tournesols emergents à 4,50 m en année 1 dans l\'exemple — variété non précisée par le chapitre'),
  (0, 'Helianthus annuus', 'à une fleur', 'Tournesol géant à une fleur', 'biomasse', 'emergente', NULL, NULL,
   'remarque du porteur, 2026-09-18',
   'préféré pour la biomasse — une seule fleur, peu de ramification, beaucoup de feuillage ; pas encore sourcé dans connaissances/, à vérifier avant usage réel');

-- Le cycle de vie — schéma 11, chantier-peuplement-parcelle.md (Phase 1 bis). Une donnée de la succession
-- syntropique (le porteur, 2026-09-23) : les bordures retiennent vivaces et ligneuses, les lignes de biomasse des
-- planches surtout des annuelles. `UPDATE … AND cycle IS NULL` : rejoué sans rien réécrire.
--   - `ligneuse` : les arbres, arbustes et la vigne du cadre — la « biomasse ligneuse » du corpus (ch. 14, ch. 15).
--   - `annuelle` : maïs, tournesol, féverole (« les annuelles de biomasse », ch. 08, ch. 11), souci (« une ligne
--     d'annuelles émergentes », ch. 14 § 4.2), haricot, courgette, courge, concombre.
--   - `vivace` : mélisse, consoude, menthe (les bordures vivantes, ch. 03, ch. 11, ch. 14), artichaut, topinambour,
--     silphie (« des bandes de grandes vivaces », Joala, tortues-et-succession.md), ail des ours, fraisiers.
--   - **Laissés vides, parce que la réponse dépend du climat ou de la conduite** : tomate, poivron, aubergine
--     (vivaces sous leur climat d'origine, cultivés en annuelles ici), pomme de terre (vivace par son tubercule,
--     cultivée en annuelle), lavande (sous-arbrisseau, entre vivace et ligneuse). À la personne d'en décider, par
--     une surcharge.
UPDATE espece SET cycle = 'ligneuse'
  WHERE site_id = 0 AND cycle IS NULL AND nom_scientifique IN (
    'Robinia pseudoacacia', 'Populus', 'Salix', 'Corylus avellana', 'Crataegus monogyna', 'Prunus spinosa',
    'Carpinus betulus', 'Betula pendula', 'Malus domestica', 'Pyrus communis', 'Cydonia oblonga', 'Juglans regia',
    'Prunus avium', 'Eucalyptus gunnii', 'Fraxinus excelsior', 'Morus alba', 'Sambucus nigra', 'Elaeagnus umbellata',
    'Ribes nigrum', 'Lonicera fragrantissima', 'Lycium barbarum', 'Buddleja davidii', 'Vitis vinifera');
UPDATE espece SET cycle = 'annuelle'
  WHERE site_id = 0 AND cycle IS NULL AND nom_scientifique IN (
    'Helianthus annuus', 'Vicia faba', 'Calendula officinalis', 'Cucurbita pepo',
    'Cucurbita maxima', 'Cucumis sativus');
UPDATE espece SET cycle = 'vivace'
  WHERE site_id = 0 AND cycle IS NULL AND nom_scientifique IN (
    'Melissa officinalis', 'Cynara scolymus', 'Helianthus tuberosus',
    'Silphium perfoliatum', 'Allium ursinum', 'Fragaria vesca', 'Fragaria ×ananassa');

-- Les espacements du corpus — chantier-peuplement-parcelle.md (accord du porteur, 2026-09-23). Les plans cotés du
-- ch. 14 (bords de carapace, p. 117, 119, 121), le ch. 11 (bordures vivantes d'une planche) et le ch. 08 (lignes d'une
-- planche) : `connaissances/livre-bienvenue-en-syntropie/`. **La source les dit « indicatifs et non définitifs »** : on
-- peut resserrer ou élargir — d'où `espacement_retenu_m`, que la personne corrige par une surcharge. Le goji a deux
-- cotes : 50 cm sur la ligne intérieure (p. 117), 20 cm conduit en tressage (p. 119) ; la première est retenue.
UPDATE espece SET espacement_retenu_m = 0.40
  WHERE site_id = 0 AND espacement_retenu_m IS NULL AND nom_scientifique = 'Melissa officinalis';
UPDATE espece SET espacement_retenu_m = 0.50
  WHERE site_id = 0 AND espacement_retenu_m IS NULL AND nom_scientifique IN ('Elaeagnus umbellata', 'Lycium barbarum');
UPDATE espece SET espacement_retenu_m = 0.60
  WHERE site_id = 0 AND espacement_retenu_m IS NULL AND nom_scientifique = 'Ribes nigrum';
UPDATE espece SET espacement_retenu_m = 1.00
  WHERE site_id = 0 AND espacement_retenu_m IS NULL AND nom_scientifique = 'Sambucus nigra';
UPDATE espece SET espacement_retenu_m = 0.20
  WHERE site_id = 0 AND espacement_retenu_m IS NULL AND nom_scientifique = 'Helianthus annuus';
UPDATE espece SET espacement_retenu_m = 2.00
  WHERE site_id = 0 AND espacement_retenu_m IS NULL AND nom_scientifique = 'Cucurbita maxima';

-- Les plantes des bords de carapace que le cadre n'avait pas — ch. 03 § 4 (« bordures extérieures, rôle : bloquer
-- l'entrée de l'herbe : consoude, romarin, hysope, sauge, lavande, artichaut ») et les plans cotés du ch. 14. Le cycle
-- de l'hysope, du romarin, du lavandin et des sauges reste vide, comme celui de la lavande : des sous-arbrisseaux,
-- entre vivace et ligneuse. Ne se transposent pas sur sol acide ou hydromorphe (ch. 03, ch. 14).
INSERT IGNORE INTO espece
  (site_id, nom_scientifique, nom_usuel, role, cycle, espacement_retenu_m, bloque_herbe, source, statut_local)
VALUES
  (0, 'Hyssopus officinalis', 'Hysope', 'bloque_herbe', NULL, 0.40, 1,
   'livre-bienvenue-en-syntropie ch. 03 § 4 ; ch. 14 § 4.2, plan p. 117',
   'bande extérieure, face sud ou ouest — espacement indicatif ; drainage et pH à vérifier'),
  (0, 'Salvia rosmarinus', 'Romarin', 'bloque_herbe', NULL, 0.40, 1,
   'livre-bienvenue-en-syntropie ch. 03 § 4 ; ch. 14 § 4.2, plan p. 117',
   'bande extérieure, face sud ou ouest — espacement indicatif ; drainage et pH à vérifier'),
  (0, 'Lavandula ×intermedia', 'Lavandin', 'bloque_herbe', NULL, 0.40, 1,
   'livre-bienvenue-en-syntropie ch. 14 § 4.2, plan p. 117',
   'bande extérieure, face sud ou ouest — espacement indicatif ; drainage et pH à vérifier'),
  (0, 'Rubus fruticosus', 'Mûrier sans épines', 'production|biomasse', 'ligneuse', 0.25, NULL,
   'livre-bienvenue-en-syntropie ch. 14 § 4.4, plan p. 121',
   'palissé sur les sureaux plantés chaque mètre, face nord ou est — trois pieds entre deux sureaux');

-- Le thym luisant (Thymus nitens) — le porteur, 2026-09-23 : « lorsqu'une plante a la destination de bordure chez les
-- jardineries et pépiniéristes, on peut la considérer comme bloque-herbe » ; « tapissant vigoureux » — en bordure, couvrir
-- le sol et bloquer l'herbe sont la même fonction (le porteur) : bloque-herbe seul. Sans espacement ni cycle : aucune
-- source du dépôt ne les donne.
INSERT IGNORE INTO espece
  (site_id, nom_scientifique, nom_usuel, role, bloque_herbe, source, statut_local)
VALUES
  (0, 'Thymus nitens', 'Thym luisant', 'bloque_herbe', 1,
   'remarque du porteur, 2026-09-23', 'plante « de bordure » chez les pépiniéristes, tapissant vigoureux — espacement à préciser');

-- Le rôle « bordure », hors du vocabulaire de `role` (liste fermée depuis le 2026-09-23), corrigé dans les INSERT
-- ci-dessus le même jour — mais `INSERT IGNORE` ne réécrit jamais une ligne déjà là : une base ancienne le garde.
-- Rattrapé ici, seulement s'il est encore là : la mélisse perd « bordure » (rien dans sa note ne le justifiait). La
-- consoude générique, qui le portait aussi, a quitté le cadre le 2026-09-29 (voir la fin de ce fichier).
UPDATE espece SET role = 'biomasse'
  WHERE site_id = 0 AND nom_scientifique = 'Melissa officinalis' AND variete = '' AND role = 'biomasse|bordure';

-- La température du sol qui permet le semis — schéma 15. « Le critère n'est pas la date mais la température du sol » :
-- 5 °C pour le tournesol (Théry, ch. 9, règle 9). Celle du maïs (6 °C) est portée par le maïs population, plus bas.
UPDATE espece SET temperature_sol_semis_c = 5.0
  WHERE site_id = 0 AND temperature_sol_semis_c IS NULL AND nom_scientifique = 'Helianthus annuus';

-- LES VARIÉTÉS REMPLACENT LES ENTRÉES GÉNÉRIQUES — le porteur, 2026-09-29 : « c'est au niveau de la variété que l'on
-- connaît les caractéristiques qui importent pour la syntropie » (la consoude Bocking 14, stérile, reste en place ; la
-- consoude officinale, fertile, s'étend sur les cultures). Chaque ligne reprend la fiche du porteur
-- (`contenu/especes-modele-import.csv`) et, là où la fiche ne dit rien, ce que l'entrée générique savait (strates,
-- entrée/sortie du scénario, bloque-herbe, statut). La capucine est nouvelle au cadre. `source` tient en 120 caractères,
-- `statut_local` en 60 : `INSERT IGNORE` couperait le surplus sans rien dire.
INSERT IGNORE INTO espece
  (site_id, nom_scientifique, variete, nom_usuel, role, strates_possibles, entree_scenario, sortie_scenario, periode_semis, periode_repiquage, periode_recolte, cycle, delai_germination_j, temperature_sol_semis_c, hauteur_m, largeur_m, type_plant, espacement_retenu_m, plants_par_ml, origine, bloque_herbe, notes, source, statut_local)
VALUES
  (0, 'Zea mays', 'population locale', 'Maïs population', 'biomasse|tuteur', 'emergente|haute', NULL, NULL, 'fin avril', 'mi mai', 'fin septembre', 'annuelle', 10, 6, 3, 0.25, 'direct, en poquets', 0.1, 4, 'multiplication', NULL, 'Tranchée centrale de planche, à tailler pour la biomasse. 6 °C = zéro de germination : levée en ~6 semaines à 6 °C, ~1 semaine à 10 °C — attendre un sol stable au-dessus de 10 °C', 'fiche du porteur 2026-09-29 ; formation-joala/strates-et-lumiere.md ; livre ch. 11', 'divergence entre sources : émergente ou haute'),
  (0, 'Symphytum ×uplandicum', 'Bocking 14', 'Consoude', 'biomasse|azote|mineraux|nectar|bloque_herbe', 'basse', 'placenta1', 'sec1', NULL, 'debut mars', 'mi a fin mai', 'vivace', NULL, NULL, 1, 0.6, 'éclat de racine', 0.6, 1.67, 'multiplication', 1, 'Fleurit (fleurs mauves visitées par les bourdons) mais ses graines sont stériles : ne se ressème pas, se multiplie seulement par racine. 4 à 5 coupes par an d\'avril à octobre, première fin avril-mai. Racines profondes qui remontent potasse et minéraux. Indéracinable une fois installée : bien choisir l\'emplacement, à bannir des bordures de planches où l\'on arrache (Théry ch. 15). Le rôle « azote » renvoie à sa richesse en azote (activateur de compost), pas à une fixation', 'fiche du porteur 2026-09-29 ; livre ch. 11 ; formation-joala/strates-et-lumiere.md', 'bordure vivante bloque-herbe ; stérile, reste en place'),
  (0, 'Phaseolus vulgaris', 'nain', 'Haricot nain', 'production|azote', 'moyenne', NULL, NULL, 'mi mai', NULL, 'fin juillet', 'annuelle', 8, 10, 0.4, 0.3, 'direct', 0.08, 12.5, 'achat', NULL, 'À semer un mois après un tuteur vivant si en association. Levée fiable au-dessus de 12 °C, risque de pourriture en sol froid. Récolte 60-70 jours après semis', 'fiche du porteur 2026-09-29 ; formation-joala/strates-et-lumiere.md', 'à l\'ombre du maïs — exemple Dordogne, sud'),
  (0, 'Tropaeolum majus', '', 'Capucine', 'couvre_sol|refuge|production|diversion|nectar', NULL, NULL, NULL, 'mi avril', 'mi mai', 'mi juin', 'annuelle', 12, 12, 0.3, 0.3, 'direct ou godet', 0.3, 3.33, 'multiplication', NULL, 'Fleurs et feuilles comestibles ; attire les pucerons loin des productions. Semis en godet sous abri mi-avril, ou direct mi-mai. Se ressème souvent seule. Hauteur en couvre-sol : les formes coureuses grimpent jusqu\'à 2-3 m sur un support', 'fiche du porteur 2026-09-29', NULL),
  (0, 'Lavandula angustifolia', '', 'Lavande', 'nectar|pollen|refuge|production|bloque_herbe', 'emergente', NULL, NULL, 'mi mars', 'mi mai', 'debut juillet', 'ligneuse', 21, 15, 0.6, 1, 'godet sous abri, ou bouture', 0.5, 2, 'mixte', 1, 'Stratifier les graines 3-4 semaines au froid avant semis. Sol drainé, plein soleil. Bande extérieure des bords sud et ouest (Théry ch. 14). Tailler après floraison sans couper le vieux bois. Vit 8-10 ans', 'fiche du porteur 2026-09-29 ; formation-joala/strates-et-lumiere.md ; livre ch. 03, ch. 14', 'exemple Dordogne, sud — non transposable tel quel'),
  (0, 'Mentha suaveolens', '', 'Menthe à feuilles rondes', 'bloque_herbe|couvre_sol|biomasse|production|nectar|pollen|refuge', 'basse', NULL, NULL, NULL, 'debut avril', 'debut juin', 'vivace', NULL, NULL, 0.6, 0.5, 'éclat de touffe ou stolon', 0.5, 2, 'multiplication', NULL, 'Choisie pour la fonction bloque-herbe : la plus haute et la plus dense des menthes courantes (40-80 cm), elle étouffe les adventices en tapis ; indigène, supporte mieux le sec et le soleil que les autres menthes. Comme toutes les menthes elle trace par stolons : aucune ne reste vraiment en place. Théry (ch. 14) la plante en fine bande côté intérieur de la bordure, d\'où elle gagne le chemin et le bord des planches — attention aux carottes et radis semés à côté. La préférer à la consoude sur les planches où l\'on arrache (ch. 15). Moins traçantes mais trop faibles pour bloquer l\'herbe : menthe ananas (M. suaveolens \'Variegata\'), menthe de Corse (M. requienii, 3-10 cm)', 'fiche du porteur 2026-09-29 ; formation-joala/strates-et-lumiere.md', 'peu de strates basses utilisables (Dordogne)'),
  (0, 'Salvia officinalis', '', 'Sauge', 'production|nectar|pollen|refuge|bloque_herbe', NULL, NULL, NULL, 'debut avril', 'mi mai', 'debut juin', 'ligneuse', 10, 12, 0.6, 1, 'godet sous abri, ou bouture', 0.5, 2, 'mixte', 1, 'Sous-arbrisseau 50-70 cm. Bande extérieure des bords nord et est (Théry ch. 14, espacée à 40 cm). Rabattre au printemps, renouveler tous les 4-5 ans. Feuilles récoltées de juin à l\'automne', 'fiche du porteur 2026-09-29 ; livre ch. 03 § 4, ch. 14 § 4.3', 'bande extérieure, face nord ou est');

-- … et les entrées génériques quittent le cadre d'une base déjà peuplée. Vérifié avant : aucune ligne de plantation
-- ni aucune surcharge de projet ne les citait en production ; en local, une ligne de bordure de La Vadaux citait la
-- consoude officinale.
DELETE FROM espece WHERE site_id = 0 AND variete = '' AND nom_scientifique IN
  ('Symphytum officinale', 'Mentha', 'Salvia', 'Lavandula', 'Zea mays', 'Phaseolus vulgaris');

-- LA FICHE DU PORTEUR, IMPORTÉE DANS LE CADRE — 2026-09-29 (`contenu/especes-modele-import.csv`, 21 espèces). Ses valeurs
-- priment sur celles du cadre ; une case vide de la fiche ne touche à rien, et ce que la fiche ne porte pas (strates,
-- entrée/sortie du scénario, bloque-herbe) reste tel quel. Réécrit à chaque rejeu : pour changer une valeur, changer la
-- fiche puis régénérer ce bloc — non le corriger ici seul. La source ne mentionne la fiche que si la place le permet.
UPDATE espece SET
  nom_usuel = 'Maïs population',
  role = 'biomasse|tuteur',
  periode_semis = 'fin avril',
  periode_repiquage = 'mi mai',
  periode_recolte = 'fin septembre',
  cycle = 'annuelle',
  delai_germination_j = 10,
  temperature_sol_semis_c = 6,
  type_plant = 'direct, en poquets',
  hauteur_m = 3,
  largeur_m = 0.25,
  espacement_retenu_m = 0.1,
  plants_par_ml = 4,
  origine = 'multiplication',
  notes = 'Tranchée centrale de planche, à tailler pour la biomasse. 6 °C = zéro de germination : levée en ~6 semaines à 6 °C, ~1 semaine à 10 °C — attendre un sol stable au-dessus de 10 °C',
  source = 'fiche du porteur 2026-09-29 ; formation-joala/strates-et-lumiere.md ; livre ch. 11',
  statut_local = 'divergence entre sources : émergente ou haute'
  WHERE site_id = 0 AND nom_scientifique = 'Zea mays' AND variete = 'population locale';
UPDATE espece SET
  nom_usuel = 'Tournesol',
  role = 'biomasse|tuteur|nectar|pollen',
  periode_semis = 'fin avril',
  periode_recolte = 'fin septembre',
  cycle = 'annuelle',
  delai_germination_j = 8,
  temperature_sol_semis_c = 5,
  type_plant = 'direct',
  hauteur_m = 2,
  largeur_m = 0.3,
  espacement_retenu_m = 0.05,
  plants_par_ml = 2,
  origine = 'achat',
  notes = 'Couper ou coucher les tiges après floraison pour la biomasse, laisser quelques capitules pour les oiseaux',
  source = IF(source LIKE 'fiche du porteur 2026-09-29%' OR CHAR_LENGTH(source) > 120 - 30, source, CONCAT_WS(' ; ', 'fiche du porteur 2026-09-29', source))
  WHERE site_id = 0 AND nom_scientifique = 'Helianthus annuus' AND variete = '';
UPDATE espece SET
  nom_usuel = 'Consoude',
  role = 'biomasse|azote|mineraux|nectar|bloque_herbe',
  periode_repiquage = 'debut mars',
  periode_recolte = 'mi a fin mai',
  cycle = 'vivace',
  type_plant = 'éclat de racine',
  hauteur_m = 1,
  largeur_m = 0.6,
  espacement_retenu_m = 0.6,
  plants_par_ml = 1.67,
  origine = 'multiplication',
  notes = 'Fleurit (fleurs mauves visitées par les bourdons) mais ses graines sont stériles : ne se ressème pas, se multiplie seulement par racine. 4 à 5 coupes par an d\'avril à octobre, première fin avril-mai. Racines profondes qui remontent potasse et minéraux. Indéracinable une fois installée : bien choisir l\'emplacement, à bannir des bordures de planches où l\'on arrache (Théry ch. 15). Le rôle « azote » renvoie à sa richesse en azote (activateur de compost), pas à une fixation',
  source = 'fiche du porteur 2026-09-29 ; livre ch. 11 ; formation-joala/strates-et-lumiere.md',
  statut_local = 'bordure vivante bloque-herbe ; stérile, reste en place'
  WHERE site_id = 0 AND nom_scientifique = 'Symphytum ×uplandicum' AND variete = 'Bocking 14';
UPDATE espece SET
  nom_usuel = 'Tomate',
  role = 'production',
  periode_semis = 'fin février',
  periode_repiquage = 'mi mai',
  periode_recolte = 'mi juillet',
  cycle = 'annuelle',
  delai_germination_j = 7,
  temperature_sol_semis_c = 12,
  type_plant = 'godet, sous abri',
  hauteur_m = 1.8,
  largeur_m = 0.5,
  espacement_retenu_m = 0.5,
  plants_par_ml = 2,
  origine = 'achat',
  notes = 'Tuteurer tôt. Semis sous abri chauffé (20-25 °C), récolte de mi-juillet aux gelées. Hauteur d\'une variété à croissance indéterminée tuteurée',
  source = IF(source LIKE 'fiche du porteur 2026-09-29%' OR CHAR_LENGTH(source) > 120 - 30, source, CONCAT_WS(' ; ', 'fiche du porteur 2026-09-29', source))
  WHERE site_id = 0 AND nom_scientifique = 'Solanum lycopersicum' AND variete = '';
UPDATE espece SET
  nom_usuel = 'Haricot nain',
  role = 'production|azote',
  periode_semis = 'mi mai',
  periode_recolte = 'fin juillet',
  cycle = 'annuelle',
  delai_germination_j = 8,
  temperature_sol_semis_c = 10,
  type_plant = 'direct',
  hauteur_m = 0.4,
  largeur_m = 0.3,
  espacement_retenu_m = 0.08,
  plants_par_ml = 12.5,
  origine = 'achat',
  notes = 'À semer un mois après un tuteur vivant si en association. Levée fiable au-dessus de 12 °C, risque de pourriture en sol froid. Récolte 60-70 jours après semis',
  source = 'fiche du porteur 2026-09-29 ; formation-joala/strates-et-lumiere.md',
  statut_local = 'à l\'ombre du maïs — exemple Dordogne, sud'
  WHERE site_id = 0 AND nom_scientifique = 'Phaseolus vulgaris' AND variete = 'nain';
UPDATE espece SET
  nom_usuel = 'Capucine',
  role = 'couvre_sol|refuge|production|diversion|nectar',
  periode_semis = 'mi avril',
  periode_repiquage = 'mi mai',
  periode_recolte = 'mi juin',
  cycle = 'annuelle',
  delai_germination_j = 12,
  temperature_sol_semis_c = 12,
  type_plant = 'direct ou godet',
  hauteur_m = 0.3,
  largeur_m = 0.3,
  espacement_retenu_m = 0.3,
  plants_par_ml = 3.33,
  origine = 'multiplication',
  notes = 'Fleurs et feuilles comestibles ; attire les pucerons loin des productions. Semis en godet sous abri mi-avril, ou direct mi-mai. Se ressème souvent seule. Hauteur en couvre-sol : les formes coureuses grimpent jusqu\'à 2-3 m sur un support',
  source = 'fiche du porteur 2026-09-29',
  statut_local = NULL
  WHERE site_id = 0 AND nom_scientifique = 'Tropaeolum majus' AND variete = '';
UPDATE espece SET
  nom_usuel = 'Artichaut',
  role = 'biomasse|production|racines|nectar|bloque_herbe',
  periode_semis = 'fin février',
  periode_repiquage = 'fin avril',
  periode_recolte = 'fin mai',
  cycle = 'vivace',
  delai_germination_j = 12,
  temperature_sol_semis_c = 12,
  type_plant = 'œilleton, ou godet sous abri',
  hauteur_m = 1.5,
  largeur_m = 1,
  espacement_retenu_m = 1,
  plants_par_ml = 1,
  origine = 'mixte',
  notes = 'Nom actuel : Cynara cardunculus var. scolymus (gardé sous l\'ancien nom, celui du référentiel de l\'atelier). Récolte mai-juin dès la 2e année. Renouveler par œilletons tous les 3-4 ans. Grandes feuilles à couper pour la biomasse après récolte. Planté aux extrémités des planches, bloque-herbe (Théry ch. 11). Craint l\'humidité hivernale : pailler',
  source = IF(source LIKE 'fiche du porteur 2026-09-29%' OR CHAR_LENGTH(source) > 120 - 30, source, CONCAT_WS(' ; ', 'fiche du porteur 2026-09-29', source))
  WHERE site_id = 0 AND nom_scientifique = 'Cynara scolymus' AND variete = '';
UPDATE espece SET
  nom_usuel = 'Hysope',
  role = 'nectar|pollen|refuge|production|bloque_herbe',
  periode_semis = 'debut avril',
  periode_repiquage = 'mi mai',
  periode_recolte = 'debut juillet',
  cycle = 'ligneuse',
  delai_germination_j = 14,
  temperature_sol_semis_c = 12,
  type_plant = 'godet sous abri, ou bouture',
  hauteur_m = 0.5,
  largeur_m = 1,
  espacement_retenu_m = 0.4,
  plants_par_ml = 2.5,
  origine = 'mixte',
  notes = 'Sous-arbrisseau 40-60 cm, plein soleil, sol sec et drainé. Bande extérieure des bords sud et ouest (Théry ch. 14). Très mellifère de juillet à septembre. Germination lente (10 à 30 jours). Rabattre au printemps',
  source = IF(source LIKE 'fiche du porteur 2026-09-29%' OR CHAR_LENGTH(source) > 120 - 30, source, CONCAT_WS(' ; ', 'fiche du porteur 2026-09-29', source))
  WHERE site_id = 0 AND nom_scientifique = 'Hyssopus officinalis' AND variete = '';
UPDATE espece SET
  nom_usuel = 'Lavande',
  role = 'nectar|pollen|refuge|production|bloque_herbe',
  periode_semis = 'mi mars',
  periode_repiquage = 'mi mai',
  periode_recolte = 'debut juillet',
  cycle = 'ligneuse',
  delai_germination_j = 21,
  temperature_sol_semis_c = 15,
  type_plant = 'godet sous abri, ou bouture',
  hauteur_m = 0.6,
  largeur_m = 1,
  espacement_retenu_m = 0.5,
  plants_par_ml = 2,
  origine = 'mixte',
  notes = 'Stratifier les graines 3-4 semaines au froid avant semis. Sol drainé, plein soleil. Bande extérieure des bords sud et ouest (Théry ch. 14). Tailler après floraison sans couper le vieux bois. Vit 8-10 ans',
  source = 'fiche du porteur 2026-09-29 ; formation-joala/strates-et-lumiere.md ; livre ch. 03, ch. 14',
  statut_local = 'exemple Dordogne, sud — non transposable tel quel'
  WHERE site_id = 0 AND nom_scientifique = 'Lavandula angustifolia' AND variete = '';
UPDATE espece SET
  nom_usuel = 'Lavandin',
  role = 'nectar|pollen|refuge|production|bloque_herbe',
  periode_repiquage = 'mi avril',
  periode_recolte = 'fin juillet',
  cycle = 'ligneuse',
  type_plant = 'bouture',
  hauteur_m = 0.9,
  largeur_m = 1,
  espacement_retenu_m = 0.8,
  plants_par_ml = 1.25,
  origine = 'multiplication',
  notes = 'Hybride stérile : ne se sème pas, bouture semi-aoûtée en août-septembre. Plus grand et plus productif que la lavande. Bande extérieure des bords sud et ouest (Théry ch. 14, qui l\'espace à 40 cm). Plantation au printemps ou à l\'automne',
  source = IF(source LIKE 'fiche du porteur 2026-09-29%' OR CHAR_LENGTH(source) > 120 - 30, source, CONCAT_WS(' ; ', 'fiche du porteur 2026-09-29', source))
  WHERE site_id = 0 AND nom_scientifique = 'Lavandula ×intermedia' AND variete = '';
UPDATE espece SET
  nom_usuel = 'Mélisse',
  role = 'production|nectar|pollen|refuge|biomasse',
  periode_semis = 'mi mars',
  periode_repiquage = 'mi mai',
  periode_recolte = 'debut juin',
  cycle = 'vivace',
  delai_germination_j = 14,
  temperature_sol_semis_c = 12,
  type_plant = 'godet sous abri, ou éclat de touffe',
  hauteur_m = 0.7,
  largeur_m = 0.7,
  espacement_retenu_m = 0.4,
  plants_par_ml = 2.5,
  origine = 'mixte',
  notes = 'Graines fines, semer en surface sans couvrir. Supporte la mi-ombre : bande intérieure des bordures (Théry ch. 14). 2 à 3 coupes par an avant floraison. Se ressème abondamment. Très mellifère',
  source = IF(source LIKE 'fiche du porteur 2026-09-29%' OR CHAR_LENGTH(source) > 120 - 30, source, CONCAT_WS(' ; ', 'fiche du porteur 2026-09-29', source))
  WHERE site_id = 0 AND nom_scientifique = 'Melissa officinalis' AND variete = '';
UPDATE espece SET
  nom_usuel = 'Menthe à feuilles rondes',
  role = 'bloque_herbe|couvre_sol|biomasse|production|nectar|pollen|refuge',
  periode_repiquage = 'debut avril',
  periode_recolte = 'debut juin',
  cycle = 'vivace',
  type_plant = 'éclat de touffe ou stolon',
  hauteur_m = 0.6,
  largeur_m = 0.5,
  espacement_retenu_m = 0.5,
  plants_par_ml = 2,
  origine = 'multiplication',
  notes = 'Choisie pour la fonction bloque-herbe : la plus haute et la plus dense des menthes courantes (40-80 cm), elle étouffe les adventices en tapis ; indigène, supporte mieux le sec et le soleil que les autres menthes. Comme toutes les menthes elle trace par stolons : aucune ne reste vraiment en place. Théry (ch. 14) la plante en fine bande côté intérieur de la bordure, d\'où elle gagne le chemin et le bord des planches — attention aux carottes et radis semés à côté. La préférer à la consoude sur les planches où l\'on arrache (ch. 15). Moins traçantes mais trop faibles pour bloquer l\'herbe : menthe ananas (M. suaveolens \'Variegata\'), menthe de Corse (M. requienii, 3-10 cm)',
  source = 'fiche du porteur 2026-09-29 ; formation-joala/strates-et-lumiere.md',
  statut_local = 'peu de strates basses utilisables (Dordogne)'
  WHERE site_id = 0 AND nom_scientifique = 'Mentha suaveolens' AND variete = '';
UPDATE espece SET
  nom_usuel = 'Romarin',
  role = 'production|nectar|pollen|refuge|bloque_herbe',
  periode_semis = 'mi mars',
  periode_repiquage = 'mi mai',
  cycle = 'ligneuse',
  delai_germination_j = 21,
  temperature_sol_semis_c = 15,
  type_plant = 'bouture, ou godet sous abri',
  hauteur_m = 1.2,
  largeur_m = 2,
  espacement_retenu_m = 0.8,
  plants_par_ml = 1.25,
  origine = 'mixte',
  notes = 'Synonyme : Rosmarinus officinalis. Bande extérieure des bords sud et ouest (Théry ch. 14, qui l\'espace à 40 cm). Récolte des feuilles toute l\'année. Floraison de fin d\'hiver précieuse pour les pollinisateurs précoces. Semis lent et peu fiable, préférer la bouture. Sol drainé, craint le froid humide',
  source = IF(source LIKE 'fiche du porteur 2026-09-29%' OR CHAR_LENGTH(source) > 120 - 30, source, CONCAT_WS(' ; ', 'fiche du porteur 2026-09-29', source))
  WHERE site_id = 0 AND nom_scientifique = 'Salvia rosmarinus' AND variete = '';
UPDATE espece SET
  nom_usuel = 'Sauge',
  role = 'production|nectar|pollen|refuge|bloque_herbe',
  periode_semis = 'debut avril',
  periode_repiquage = 'mi mai',
  periode_recolte = 'debut juin',
  cycle = 'ligneuse',
  delai_germination_j = 10,
  temperature_sol_semis_c = 12,
  type_plant = 'godet sous abri, ou bouture',
  hauteur_m = 0.6,
  largeur_m = 1,
  espacement_retenu_m = 0.5,
  plants_par_ml = 2,
  origine = 'mixte',
  notes = 'Sous-arbrisseau 50-70 cm. Bande extérieure des bords nord et est (Théry ch. 14, espacée à 40 cm). Rabattre au printemps, renouveler tous les 4-5 ans. Feuilles récoltées de juin à l\'automne',
  source = 'fiche du porteur 2026-09-29 ; livre ch. 03 § 4, ch. 14 § 4.3',
  statut_local = 'bande extérieure, face nord ou est'
  WHERE site_id = 0 AND nom_scientifique = 'Salvia officinalis' AND variete = '';
UPDATE espece SET
  nom_usuel = 'Silphie',
  role = 'biomasse|nectar|pollen|racines|erosion|refuge',
  periode_semis = 'mi mars',
  periode_repiquage = 'mi mai',
  periode_recolte = 'debut septembre',
  cycle = 'vivace',
  delai_germination_j = 15,
  temperature_sol_semis_c = 10,
  type_plant = 'godet après stratification, ou éclat de touffe',
  hauteur_m = 2.5,
  largeur_m = 2.5,
  espacement_retenu_m = 0.5,
  plants_par_ml = 2,
  origine = 'achat',
  notes = 'Stratifier les graines 8 semaines au froid humide avant semis. Première année en rosette seulement. Ensuite 2-3 m de haut, fauche fin août-septembre, pendant 10-15 ans. Floraison de juillet à septembre très mellifère. Les feuilles en coupe retiennent l\'eau pour les insectes et les oiseaux',
  source = IF(source LIKE 'fiche du porteur 2026-09-29%' OR CHAR_LENGTH(source) > 120 - 30, source, CONCAT_WS(' ; ', 'fiche du porteur 2026-09-29', source))
  WHERE site_id = 0 AND nom_scientifique = 'Silphium perfoliatum' AND variete = '';
UPDATE espece SET
  nom_usuel = 'Thym luisant',
  role = 'couvre_sol|bloque_herbe|nectar|pollen|refuge|production',
  periode_semis = 'mi mars',
  periode_repiquage = 'mi mai',
  periode_recolte = 'debut juin',
  cycle = 'ligneuse',
  delai_germination_j = 14,
  temperature_sol_semis_c = 12,
  type_plant = 'godet, bouture ou éclat',
  hauteur_m = 0.2,
  largeur_m = 1,
  espacement_retenu_m = 0.5,
  plants_par_ml = 3.33,
  origine = 'mixte',
  notes = 'Thym tapissant des Cévennes. Sol pauvre, drainé, acide à neutre (contrairement aux thyms calcicoles). Supporte mieux l\'humidité que le thym commun. Graines fines, semer en surface',
  source = IF(source LIKE 'fiche du porteur 2026-09-29%' OR CHAR_LENGTH(source) > 120 - 30, source, CONCAT_WS(' ; ', 'fiche du porteur 2026-09-29', source))
  WHERE site_id = 0 AND nom_scientifique = 'Thymus nitens' AND variete = '';
UPDATE espece SET
  nom_usuel = 'Topinambour',
  role = 'biomasse|production|vent|nectar|pollen',
  periode_repiquage = 'debut a fin mars',
  periode_recolte = 'debut novembre',
  cycle = 'vivace',
  type_plant = 'tubercule',
  hauteur_m = 2.5,
  largeur_m = 0.5,
  espacement_retenu_m = 0.4,
  plants_par_ml = 2.5,
  origine = 'multiplication',
  notes = 'Très envahissant : chaque tubercule oublié repousse, à confiner. 2-3 m de haut, bon brise-vent et grosse biomasse (coucher les tiges à l\'automne). Floraison tardive (septembre-octobre). Récolte de novembre à mars',
  source = IF(source LIKE 'fiche du porteur 2026-09-29%' OR CHAR_LENGTH(source) > 120 - 30, source, CONCAT_WS(' ; ', 'fiche du porteur 2026-09-29', source))
  WHERE site_id = 0 AND nom_scientifique = 'Helianthus tuberosus' AND variete = '';
UPDATE espece SET
  nom_usuel = 'Sureau',
  role = 'production|biomasse|tuteur|pollen|refuge|multiplication',
  periode_repiquage = 'fin novembre',
  periode_recolte = 'fin aout',
  cycle = 'ligneuse',
  type_plant = 'bouture de bois sec, ou plant racines nues',
  hauteur_m = 6,
  largeur_m = 1,
  espacement_retenu_m = 1,
  plants_par_ml = 1,
  origine = 'multiplication',
  notes = 'Planté chaque mètre sur les bords nord et est, il sert de support de palissage aux mûriers sans épines (Théry ch. 14). Bouture de bois sec en hiver, reprise très facile. Supporte le recépage : bonne biomasse. Fleurs début juin (pollen), baies fin août-septembre, à cuire (crues, légèrement toxiques). Hauteur libre 5-8 m, bien moins s\'il est taillé. Sort du système vers 20-25 ans, quand l\'ombre des grands arbres le rattrape (ch. 11)',
  source = IF(source LIKE 'fiche du porteur 2026-09-29%' OR CHAR_LENGTH(source) > 120 - 30, source, CONCAT_WS(' ; ', 'fiche du porteur 2026-09-29', source))
  WHERE site_id = 0 AND nom_scientifique = 'Sambucus nigra' AND variete = '';
UPDATE espece SET
  nom_usuel = 'Goji',
  role = 'production|biomasse|perturbation|racines|nectar|pollen',
  periode_semis = 'mi mars',
  periode_repiquage = 'mi mai',
  periode_recolte = 'debut aout',
  cycle = 'ligneuse',
  delai_germination_j = 10,
  temperature_sol_semis_c = 15,
  type_plant = 'bouture, drageon ou godet',
  hauteur_m = 2.5,
  largeur_m = 1,
  espacement_retenu_m = 0.5,
  plants_par_ml = 2,
  origine = 'multiplication',
  notes = 'Ligne intérieure des bords sud et ouest à 50 cm, ou conduit en tressage à 20 cm sur les bords nord et est (Théry ch. 14). Épitone : conduit « tressé », on taille et pose au sol les deux tiers de la plante chaque saison — grosse biomasse équilibrée. Racines plongeantes qui remontent eau et nutriments. Résiste au gel et aux limaces. Drageonne. Récolte d\'août à octobre. Semis facile à 20 °C sous abri, repiqué mi-mai, bouture ou plant se mettent en place de novembre à mars',
  source = IF(source LIKE 'fiche du porteur 2026-09-29%' OR CHAR_LENGTH(source) > 120 - 30, source, CONCAT_WS(' ; ', 'fiche du porteur 2026-09-29', source))
  WHERE site_id = 0 AND nom_scientifique = 'Lycium barbarum' AND variete = '';
UPDATE espece SET
  nom_usuel = 'Chalef d\'automne',
  role = 'azote|biomasse|production|nectar|pollen|ombre|vent',
  periode_repiquage = 'fin novembre',
  periode_recolte = 'debut octobre',
  cycle = 'ligneuse',
  type_plant = 'plant ou marcotte',
  hauteur_m = 3.5,
  largeur_m = 1,
  espacement_retenu_m = 0.5,
  plants_par_ml = 2,
  origine = 'achat',
  notes = 'Fixe l\'azote (actinobactéries Frankia). Ligne intérieure des bords sud et ouest, à 50 cm, à 30-40 cm du bord intérieur (Théry ch. 14). Culmine à 3-3,50 m, pousse rapide, supporte la chaleur une fois installé. Fleurs très mellifères tôt au printemps. Baies riches en antioxydants, à cueillir très mûres en septembre-octobre — ne fructifie qu\'en plein soleil. Graines à stratifier 3 mois au froid. Invasif en Amérique du Nord (graines dispersées par les oiseaux) : à surveiller. Ne pas confondre avec les autres éléagnus, peu sont comestibles',
  source = IF(source LIKE 'fiche du porteur 2026-09-29%' OR CHAR_LENGTH(source) > 120 - 30, source, CONCAT_WS(' ; ', 'fiche du porteur 2026-09-29', source))
  WHERE site_id = 0 AND nom_scientifique = 'Elaeagnus umbellata' AND variete = '';
UPDATE espece SET
  nom_usuel = 'Mûrier sans épines',
  role = 'production|biomasse',
  periode_repiquage = 'fin novembre',
  periode_recolte = 'fin juillet',
  cycle = 'ligneuse',
  type_plant = 'bouture, marcotte ou plant',
  hauteur_m = 2,
  largeur_m = 3,
  espacement_retenu_m = 4,
  plants_par_ml = 4,
  origine = 'mixte',
  notes = 'Mûre à ronce sans épines (cultivars \'Thornless Evergreen\', \'Loch Ness\', \'Black Satin\'…), pas le mûrier arbre (Morus). Bords nord et est : trois pieds entre deux sureaux plantés chaque mètre, palissés dessus (Théry ch. 14). Taille de formation aérée pour que les mûres mûrissent, une partie des tiges prélevée pour la biomasse. Récolte de fin juillet à septembre. Se marcotte seul par le bout des tiges',
  source = IF(source LIKE 'fiche du porteur 2026-09-29%' OR CHAR_LENGTH(source) > 120 - 30, source, CONCAT_WS(' ; ', 'fiche du porteur 2026-09-29', source))
  WHERE site_id = 0 AND nom_scientifique = 'Rubus fruticosus' AND variete = '';
