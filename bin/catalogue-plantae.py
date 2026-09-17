#!/usr/bin/env python3
"""Exporte seulement les noms botaniques pour le sélecteur du scénario.

Port de `application/bin/catalogue-scenario.py` (dépôt de référence,
19 lignes) — chantier « scénario de plantation », Phase 1. Voir
`chantier-scenario-plantation.md`.

Usage : python3 bin/catalogue-plantae.py /chemin/plantae-corpus/exports/csv/plantes.csv
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
