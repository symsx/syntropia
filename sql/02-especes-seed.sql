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
  (0, 'Symphytum officinale', 'Consoude', 'biomasse|bloque_herbe', 'basse', 'placenta1', 'sec1',
   'ch11-construire-un-scenario-planter-un-film.md; strates-et-lumiere.md; plusieurs fiches formation-joala',
   'engrais vert immédiat, bordure vivante bloque-herbe — jusqu\'à six coupes annuelles rapportées (calendrier-et-taille-hivernale.md)'),
  (0, 'Cynara scolymus', 'Artichaut', 'production|biomasse|bloque_herbe', NULL, NULL, NULL,
   'ch11-construire-un-scenario-planter-un-film.md',
   'planté aux extrémités des planches — hors tableau de stratification dans l\'exemple du ch. 11');

UPDATE espece SET bloque_herbe = 1
  WHERE site_id = 0 AND nom_scientifique IN ('Melissa officinalis', 'Symphytum officinale', 'Cynara scolymus');

-- Le tableau des besoins en lumière — `strates-et-lumiere.md`, exemples
-- donnés pour le sud de la Dordogne. Explicitement signalé par la
-- source elle-même comme un point de départ, pas un catalogue
-- transposable tel quel à un autre climat — voir `statut_local`.
INSERT IGNORE INTO espece
  (site_id, nom_scientifique, nom_usuel, role, strates_possibles, source, statut_local)
VALUES
  (0, 'Lavandula', 'Lavande', 'production', 'emergente',
   'connaissances/formation-joala/strates-et-lumiere.md', 'exemple Dordogne, sud — non transposable tel quel ailleurs'),
  (0, 'Vitis vinifera', 'Vigne', 'production', 'haute',
   'connaissances/formation-joala/strates-et-lumiere.md',
   'exemple Dordogne, sud — le support signale que la vigne n\'est PAS une strate émergente, contrairement à l\'intuition courante'),
  (0, 'Allium ursinum', 'Ail des ours', 'production', 'haute',
   'connaissances/formation-joala/strates-et-lumiere.md', 'exemple Dordogne, sud'),
  (0, 'Vicia faba', 'Fève', 'production|azote', 'emergente',
   'connaissances/formation-joala/strates-et-lumiere.md; ch11-construire-un-scenario-planter-un-film.md',
   'émergente au moment de l\'année selon strates-et-lumiere.md ; entrée/sortie placenta1 dans l\'étude de cas du ch. 11'),
  (0, 'Phaseolus vulgaris', 'Haricots', 'production', 'moyenne',
   'connaissances/formation-joala/strates-et-lumiere.md', 'à l\'ombre du maïs selon l\'exemple — exemple Dordogne, sud'),
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
   'petite taille — ne se déploie qu\'en bordure ou au cœur des planches en tout début de saison, exemple Dordogne, sud'),
  (0, 'Zea mays', 'Maïs', 'production|biomasse', 'emergente|haute',
   'connaissances/formation-joala/strates-et-lumiere.md; ch11-construire-un-scenario-planter-un-film.md',
   'DIVERGENCE ENTRE SOURCES, conservée telle quelle : émergente pour le maïs de production selon strates-et-lumiere.md, haute (entrée placenta1, sortie placenta2) dans l\'étude de cas du ch. 11 — deux usages différents du maïs, pas une contradiction à trancher ici'),
  (0, 'Mentha', 'Menthe', 'biomasse|couvre_sol', 'basse',
   'connaissances/formation-joala/strates-et-lumiere.md',
   'peu de strates basses utilisables dans le contexte Dordogne selon la source — exemple Dordogne, sud');

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
    'Zea mays', 'Helianthus annuus', 'Vicia faba', 'Calendula officinalis', 'Phaseolus vulgaris', 'Cucurbita pepo',
    'Cucurbita maxima', 'Cucumis sativus');
UPDATE espece SET cycle = 'vivace'
  WHERE site_id = 0 AND cycle IS NULL AND nom_scientifique IN (
    'Melissa officinalis', 'Symphytum officinale', 'Mentha', 'Cynara scolymus', 'Helianthus tuberosus',
    'Silphium perfoliatum', 'Allium ursinum', 'Fragaria vesca', 'Fragaria ×ananassa');

-- Les espacements du corpus — chantier-peuplement-parcelle.md (accord du porteur, 2026-09-23). Les plans cotés du
-- ch. 14 (bords de carapace, p. 117, 119, 121), le ch. 11 (bordures vivantes d'une planche) et le ch. 08 (lignes d'une
-- planche) : `connaissances/livre-bienvenue-en-syntropie/`. **La source les dit « indicatifs et non définitifs »** : on
-- peut resserrer ou élargir — d'où `espacement_retenu_m`, que la personne corrige par une surcharge. Le goji a deux
-- cotes : 50 cm sur la ligne intérieure (p. 117), 20 cm conduit en tressage (p. 119) ; la première est retenue.
UPDATE espece SET espacement_retenu_m = 0.40
  WHERE site_id = 0 AND espacement_retenu_m IS NULL AND nom_scientifique IN ('Lavandula', 'Symphytum officinale', 'Melissa officinalis');
UPDATE espece SET espacement_retenu_m = 0.50
  WHERE site_id = 0 AND espacement_retenu_m IS NULL AND nom_scientifique IN ('Elaeagnus umbellata', 'Lycium barbarum', 'Mentha', 'Zea mays');
UPDATE espece SET espacement_retenu_m = 0.60
  WHERE site_id = 0 AND espacement_retenu_m IS NULL AND nom_scientifique = 'Ribes nigrum';
UPDATE espece SET espacement_retenu_m = 1.00
  WHERE site_id = 0 AND espacement_retenu_m IS NULL AND nom_scientifique = 'Sambucus nigra';
UPDATE espece SET espacement_retenu_m = 0.20
  WHERE site_id = 0 AND espacement_retenu_m IS NULL AND nom_scientifique = 'Helianthus annuus';
UPDATE espece SET espacement_retenu_m = 2.00
  WHERE site_id = 0 AND espacement_retenu_m IS NULL AND nom_scientifique = 'Cucurbita maxima';

-- La lavande, citée parmi les bordures extérieures bloque-herbe (ch. 03 § 4 ; ch. 14 § 4.2).
UPDATE espece SET bloque_herbe = 1
  WHERE site_id = 0 AND nom_scientifique = 'Lavandula' AND variete = '' AND bloque_herbe IS NULL;
UPDATE espece SET role = CONCAT(role, '|bloque_herbe')
  WHERE site_id = 0 AND nom_scientifique = 'Lavandula' AND variete = '' AND FIND_IN_SET('bloque_herbe', REPLACE(role, '|', ',')) = 0;

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
  (0, 'Salvia', 'Sauge', 'bloque_herbe', NULL, 0.40, 1,
   'livre-bienvenue-en-syntropie ch. 03 § 4 ; ch. 14 § 4.3, plan p. 119',
   'bande extérieure, face nord ou est — les sauges arbustives (sauge de Jérusalem) demandent bien plus de largeur'),
  (0, 'Rubus fruticosus', 'Mûrier sans épines', 'production|biomasse', 'ligneuse', 0.25, NULL,
   'livre-bienvenue-en-syntropie ch. 14 § 4.4, plan p. 121',
   'palissé sur les sureaux plantés chaque mètre, face nord ou est — trois pieds entre deux sureaux');

-- Le thym luisant — le porteur, 2026-09-23 : « lorsqu'une plante a la destination de bordure chez les jardineries et
-- pépiniéristes, on peut la considérer comme bloque-herbe ». Nom scientifique à préciser : le genre seul, la variété
-- dit l'usage courant. Sans espacement ni cycle : aucune source du dépôt ne les donne.
INSERT IGNORE INTO espece
  (site_id, nom_scientifique, variete, nom_usuel, role, bloque_herbe, source, statut_local)
VALUES
  (0, 'Thymus', 'luisant', 'Thym', 'bloque_herbe', 1,
   'remarque du porteur, 2026-09-23', 'plante « de bordure » chez les pépiniéristes — nom scientifique et espacement à préciser');
