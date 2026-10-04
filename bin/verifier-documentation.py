#!/usr/bin/env python3
"""Le livret « L'atelier » est-il à jour avec le code ?

    python3 bin/verifier-documentation.py           liste ce qui est à relire
    python3 bin/verifier-documentation.py --relu    note « le livret est à jour » (HEAD)
    python3 bin/verifier-documentation.py --court   deux lignes, pour deployer.py

Le livret (`contenu/documentation-atelier.md`) est à la charge de Claude et ne décrit
que ce qui est en production — voir `chantier-documentation-atelier.md`. Ce script ne
sait pas SI un changement se voit, seulement QU'un fichier lié à un chapitre a changé
depuis la dernière relecture : c'est la relecture du chapitre qui tranche.

Trois constats, du plus grave au plus léger :
  - une page de public/ que AUCUN chapitre ne couvre (code de sortie 2) ;
  - un chapitre qui n'existe plus, ou qui n'a pas de ligne dans COUVERTURE ;
  - des fichiers liés à un chapitre modifiés depuis la dernière relecture, commits
    ou modifications non commitées (code de sortie 1).

« Dernière relecture » = le commit noté dans bin/documentation-atelier.relu (écrit par
--relu). Un correctif de style qui ne change rien à ce que dit le livret se règle donc
par un --relu, sans toucher au texte. Aucune dépendance hors `git` et `python3`.
"""

import subprocess
import sys
from pathlib import Path

RACINE = Path(__file__).resolve().parents[1]
LIVRET = "contenu/documentation-atelier.md"
RELU = RACINE / "bin" / "documentation-atelier.relu"

# Chapitre (titre exact, ligne « # » du livret) -> fichiers qui déterminent ce qu'il dit.
# « L'intention » est la voix du porteur : aucun fichier, jamais signalé.
COUVERTURE = {
    "L'Atelier Syntropia": ["public/index.php"],
    "L'intention": [],
    "Limites": ["public/conception-parcelles.php", "src/Web/Especes.php"],
    "Où trouver quoi": [
        "public/index.php", "public/connexion.php", "public/glossaire.php",
        "public/documentation-site.php", "public/documentation-saf.php",
        "public/dossier-projet.php", "src/Web/Reponses.php", "public/documentation-sources.php",
    ],
    "Le parcours en quatre phases": ["public/assets/js/etapes.js"],
    "Définir son projet": [
        "public/definition-projet.php", "public/definition-vision.php",
        "public/definition-objectifs.php", "public/definition-moyens.php",
        "public/definition-site.php", "public/api-usda.php",
        "src/Web/ZoneUsda.php", "src/Conduite.php",
    ],
    "Concevoir : la carte du terrain": [
        "public/conception-etude-site.php", "public/assets/js/calques.js",
        "public/assets/js/carte.js", "public/api-calques.php", "public/api-cadastre.php",
        "public/api-eau.php", "public/api-altitude.php", "src/Web/Calques.php",
        "src/Web/Cadastre.php", "src/Web/Eau.php", "src/Web/Vents.php",
        "src/Web/Altimetrie.php", "src/Relief.php", "src/ShadeMap.php", "src/Geojson.php",
    ],
    "Concevoir : l'étude du sol": ["public/conception-etude-sol.php", "public/releve-vierge.php", "src/FicheTerrain.php"],
    "Concevoir : le plan de la parcelle": [
        "public/conception-parcelles.php", "public/conception-parcelles.js", "src/Web/Parcelles.php",
        "src/Planches.php", "src/Stade.php", "src/Tortue.php", "public/assets/js/gabarit.js", "src/Web/PartBiomasse.php",
    ],
    "Concevoir : les bordures": [
        "public/conception-parcelles.php", "public/conception-parcelles.js", "src/Bordures.php",
        "src/RepartitionBordures.php", "src/Sigles.php", "src/Web/LignesPlantation.php", "src/Web/CalendrierLignes.php",
    ],
    "Concevoir : la tortue liane": ["public/conception-parcelles.php", "src/Planches.php", "src/Bordures.php", "src/LignesDeService.php"],
    "Concevoir : le scénario": [
        "public/conception-scenario.php", "public/conception-calendrier.php", "src/Web/Scenarios.php",
        "src/Scenario.php", "src/Strates.php", "src/Web/Occupation.php", "src/SautDuChat.php",
        "public/assets/js/strates.js", "src/Web/PartBiomasse.php",
    ],
    "Concevoir : les espèces": ["public/especes.php", "src/Web/Especes.php", "src/Espacement.php"],
    "Concevoir : le potager": [
        "public/conception-potager.php", "src/Web/Cultures.php", "src/Associations.php", "src/PlanDeCulture.php",
        "src/CalendrierLopez.php", "src/Succession.php", "src/LignesDeService.php", "src/Temps.php",
        "src/Web/CalendrierLignes.php", "src/Web/PartBiomasse.php",
    ],
    "Concevoir : le matériel et l'eau": [
        "public/conception-materiel-et-eau.php", "src/Materiel.php", "src/BesoinEau.php", "src/Web/MaterielDuSite.php",
        "src/Web/ReleveSol.php",
    ],
    "Réaliser": [
        "public/realisation-preparation-parcelle.php", "public/realisation-materiel-vegetal.php",
        "public/realisation-outils-et-plans.php", "public/realisation-consignation.php",
    ],
    "Suivre": [
        "public/suivi-arrosage-germination.php", "public/suivi-perturbation.php",
        "public/suivi-saut-du-chat.php", "public/suivi-consignation.php",
        "src/Perturbation.php", "src/Web/Interventions.php", "src/SautDuChat.php", "src/Web/Constats.php",
    ],
    "Le Dossier Projet": ["public/dossier-projet.php", "src/DessinsDossier.php", "src/Web/Reponses.php",
                          "src/Web/MaterielDuSite.php"],
    "Le bureau": ["public/assets/css/gabarit.css", "public/assets/js/gabarit.js"],
    "Comptes, projets et rôles": [
        "public/compte.php", "public/inscription.php", "public/deconnexion.php",
        "public/api-projets.php", "src/Web/Sites.php", "src/Web/Comptes.php",
        "src/Web/Authentification.php", "public/exporter.php", "src/Web/Export.php", "src/Version.php",
        "public/importer.php", "src/Web/Import.php", "public/journal-connexions.php",
    ],
    "Inviter et rejoindre": [
        "public/inviter.php", "public/rejoindre.php", "public/membres.php",
        "src/Web/Invitations.php",
    ],
}


def git(*args):
    r = subprocess.run(["git", *args], cwd=RACINE, capture_output=True, text=True)
    # rstrip seulement : `git status --porcelain` commence par un espace significatif
    # (« ␣M fichier »), que strip() retirerait sur la première ligne.
    return r.stdout.rstrip("\n") if r.returncode == 0 else ""


def chapitres_du_livret():
    texte = (RACINE / LIVRET).read_text(encoding="utf-8")
    return [l[2:].strip() for l in texte.splitlines() if l.startswith("# ")]


def pages_publiques():
    return sorted(str(p.relative_to(RACINE)) for p in (RACINE / "public").glob("*.php"))


def modifies_depuis(base, fichiers):
    """Fichiers changés depuis `base` (commits) ou non commités."""
    if not fichiers:
        return set()
    changes = set()
    if base:
        sortie = git("log", f"{base}..HEAD", "--name-only", "--pretty=format:", "--", *fichiers)
        changes |= {l for l in sortie.splitlines() if l}
    sortie = git("status", "--porcelain", "--", *fichiers)
    changes |= {l[3:].strip() for l in sortie.splitlines() if l}
    return changes & set(fichiers)


def main():
    if "--relu" in sys.argv:
        tete = git("rev-parse", "HEAD").strip()
        RELU.write_text(tete + "\n", encoding="utf-8")
        print(f"livret noté à jour au commit {tete[:7]} — pense à commiter {RELU.relative_to(RACINE)}")
        return 0

    court = "--court" in sys.argv
    base = RELU.read_text(encoding="utf-8").strip() if RELU.exists() else ""
    presents = chapitres_du_livret()
    couvert = {f for fichiers in COUVERTURE.values() for f in fichiers}

    non_couvertes = [p for p in pages_publiques() if p not in couvert]
    orphelins = [t for t in COUVERTURE if t not in presents]        # ligne sans chapitre
    sans_ligne = [t for t in presents if t not in COUVERTURE]       # chapitre sans ligne
    a_relire = {}
    for titre, fichiers in COUVERTURE.items():
        if titre in presents:
            change = modifies_depuis(base, fichiers)
            if change:
                a_relire[titre] = sorted(change)

    if court:
        if non_couvertes or orphelins or sans_ligne:
            print(f"livret : {len(non_couvertes) + len(orphelins) + len(sans_ligne)} écart(s) de "
                  "couverture — lancer bin/verifier-documentation.py")
        if a_relire:
            print(f"livret : {len(a_relire)} chapitre(s) à relire ("
                  + " ; ".join(a_relire) + ") — bin/verifier-documentation.py")
    else:
        if not base:
            print("Aucune relecture notée (bin/documentation-atelier.relu absent) : tout est signalé.\n")
        for p in non_couvertes:
            print(f"✗ page non couverte : {p} — l'ajouter à COUVERTURE (un chapitre en parle-t-il ?)")
        for t in orphelins:
            print(f"✗ COUVERTURE cite un chapitre absent du livret : « {t} »")
        for t in sans_ligne:
            print(f"✗ chapitre sans ligne dans COUVERTURE : « {t} »")
        for titre, fichiers in a_relire.items():
            print(f"· À relire — « {titre} » : " + ", ".join(fichiers))
        if not (non_couvertes or orphelins or sans_ligne or a_relire):
            print("✓ le livret est à jour : rien n'a changé depuis la dernière relecture "
                  f"({base[:7] or 'aucune'}).")
        elif a_relire:
            print("\nRelire ces chapitres contre ce qui est EN PRODUCTION, les corriger, puis "
                  "python3 bin/verifier-documentation.py --relu")

    if non_couvertes or orphelins or sans_ligne:
        return 2
    return 1 if a_relire else 0


if __name__ == "__main__":
    sys.exit(main())
