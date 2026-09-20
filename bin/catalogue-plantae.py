#!/usr/bin/env python3
"""Exporte seulement les noms botaniques pour le sélecteur du scénario.

Port de `application/bin/catalogue-scenario.py` (dépôt de référence,
19 lignes) — chantier « scénario de plantation », Phase 1. Voir
`chantier-scenario-plantation.md`.

Usage : python3 bin/catalogue-plantae.py /chemin/plantae-corpus/exports/csv/plantes.csv
(exporte aussi `guilde.csv`, s'il est à côté : voir plus bas)
Les critères agronomiques restent chez Plantae. Aucun classement local
n'est déduit — c'est `sql/02-especes-seed.sql` qui porte le peu de
donnée agronomique locale, curatée à la main, jamais ce script.
"""
import csv
import json
import sys
from pathlib import Path

source = Path(sys.argv[1])
with source.open(encoding='utf-8-sig', newline='') as fichier:
    noms = sorted({(r['nom_scientifique'], r['noms_francais'])
                   for r in csv.DictReader(fichier, delimiter=';') if r['nom_scientifique']})
cible = Path(__file__).resolve().parents[1] / 'contenu' / 'catalogue-scenario.json'
cible.write_text(json.dumps({'source': 'plantae-corpus — noms uniquement',
                             'vegetaux': [{'nom': nom, 'communs': communs} for nom, communs in noms]},
                            ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
print(f'{len(noms)} végétaux exportés.')

# Les guildes — un instantané de `guilde.csv`, voisin de `plantes.csv` (2026-09-20).
#
# Le catalogue ci-dessus n'exporte que des NOMS : « les critères agronomiques restent chez Plantae ». Les
# guildes en sont — et le porteur a tranché le 2026-09-20 : pour un verger ou un jardin-forêt, l'association
# EST la guilde ou le consortium, le cœur de Plantae ; l'atelier en garde un INSTANTANÉ pour pouvoir calculer
# (les besoins couverts, ce qui manque). C'est une dérogation assumée au principe du catalogue : la donnée est
# copiée telle que Plantae la donne, jamais reclassée ici, et VIEILLIT — à réexporter quand Plantae change.
# Une ligne de `guilde.csv` : (plante, catégorie, relation « Service rendu » ou « Besoin requis », intensité).
guilde = source.with_name('guilde.csv')
if guilde.exists():
    plantes = {}
    with guilde.open(encoding='utf-8-sig', newline='') as fichier:
        for r in csv.DictReader(fichier, delimiter=';'):
            if not r['nom_scientifique']:
                continue
            plantes.setdefault(r['nom_scientifique'], []).append(
                {'categorie': r['categorie'], 'relation': r['relation'],
                 'intensite': r['intensite'] or None, 'contexte': r['contexte'] or None})
    cible = Path(__file__).resolve().parents[1] / 'contenu' / 'guildes-plantae.json'
    cible.write_text(json.dumps({'source': 'plantae-corpus — exports/csv/guilde.csv, instantané ; à réexporter quand Plantae change',
                                 'plantes': dict(sorted(plantes.items()))},
                                ensure_ascii=False, indent=1) + '\n', encoding='utf-8')
    print(f'{len(plantes)} plantes avec une guilde exportées ({sum(len(v) for v in plantes.values())} lignes).')
else:
    print('Pas de guilde.csv à côté de plantes.csv : guildes non exportées.')
