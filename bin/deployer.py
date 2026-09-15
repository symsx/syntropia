#!/usr/bin/env python3
"""Déploiement vers l'hébergement cible — alwaysdata, compte `jubile`.

Voir architecture-technique.md § Hébergement pour comment cet accès a
été retrouvé et vérifié (2026-09-15). Rien ici ne crée les bases ou
l'utilisateur MySQL — alwaysdata ne donne pas de CLI pour ça (vérifié :
aucun `aldcli` ni équivalent sur le compte), ça se fait dans le panneau
alwaysdata, à la main, une fois. Voir `config` ci-dessous pour la suite.

    python3 bin/deployer.py etat        diagnostic : SSH, PHP distant, config, bases distantes
    python3 bin/deployer.py fichiers    envoie l'arborescence servable vers jubile:~/www/syntropia/
    python3 bin/deployer.py fichiers --dry-run   montre ce qui serait envoyé, n'envoie rien
    python3 bin/deployer.py config      envoie config/base.php (séparé de `fichiers` — jamais
                                        écrasé sans le demander explicitement)
    python3 bin/deployer.py base        rejoue le schéma SQL sur les bases distantes
                                        (nécessite config/base.php, local et déjà envoyé)
    python3 bin/deployer.py preparer    assemble l'arborescence servable dans un dossier à
                                        part (../syntropia-depot), pour qui préfère déposer
                                        à la main (FileZilla…) plutôt que `fichiers`

Aucune action par défaut : contrairement à `dev.py`, un `deployer.py`
sans argument affiche l'aide plutôt que d'agir — on ne pousse rien en
production par accident.

Aucune dépendance hors `ssh`/`rsync`/`php`, déjà nécessaires par
ailleurs. L'accès SSH vient de `~/.ssh/config` (alias `jubile`), pas
d'ici — voir architecture-technique.md § Hébergement.
"""

import json
import shutil
import subprocess
import sys
from pathlib import Path

RACINE = Path(__file__).resolve().parents[1]
HOTE_SSH = "jubile"
# Sous-répertoire, pas la racine du compte : jubile.alwaysdata.net
# héberge Syntropia ET Plantae, chacun dans le sien — décision du
# porteur, 2026-09-15. Assorti à config/base.php ('base') et au
# RewriteBase de .htaccess (racine du dépôt) : les trois doivent
# toujours porter le même chemin.
WWW_DISTANT = "www/syntropia"

# Ce qui part vers le serveur — une liste explicite, pas une exclusion :
# plus sûr de dire ce qui part que d'espérer avoir exclu tout ce qui ne
# doit pas partir. `config/base.php` n'y est PAS : voir l'action `config`.
ELEMENTS = [
    ".htaccess",
    "bin",
    "config/.htaccess",
    "config/base.php.exemple",
    "contenu",
    "public",
    "src",
    "sql",
]

# Jamais envoyé, même à l'intérieur d'un dossier de la liste ci-dessus.
EXCLUSIONS = ["__pycache__", "*.pyc", ".DS_Store", "Thumbs.db", "*.tmp", "~$*"]

# Pour qui dépose à la main plutôt qu'avec `fichiers` — voisin du dépôt
# du projet, jamais dedans (un `rglob` un peu large ne le ramasserait
# pas par erreur).
DEPOT = RACINE.parent / "syntropia-depot"

# Un piège classique de FTP en mode ASCII : les fins de ligne se font
# réécrire. Sur un `.woff2`, cela le détruit — et une fonte cassée ne se
# voit pas, la page retombe simplement sur sa pile de secours. Même
# liste qu'`application/bin/preparer-depot.py` (dépôt de référence).
EXTENSIONS_BINAIRES = (".woff2", ".woff", ".ttf", ".otf", ".png", ".jpg",
                       ".jpeg", ".gif", ".webp", ".pdf", ".ico")

VERT, ROUGE, JAUNE, GRIS, FIN_ = "\033[32m", "\033[31m", "\033[33m", "\033[90m", "\033[0m"


def ok(m):     print(f"  {VERT}✓{FIN_} {m}")
def souci(m):  print(f"  {ROUGE}✗{FIN_} {m}")
def note(m):   print(f"  {JAUNE}·{FIN_} {m}")
def titre(m):  print(f"\n{m}")


# ---------------------------------------------------------------- config

def config_prod():
    """Lit `config/base.php` via PHP lui-même — une seule source de
    vérité pour les identifiants de production, celle qui part vraiment
    sur le serveur. `None` si le fichier n'existe pas encore : voir
    `config/base.php.exemple`."""
    chemin = RACINE / "config" / "base.php"
    if not chemin.is_file():
        return None
    resultat = subprocess.run(
        ["php", "-r", f"echo json_encode(require '{chemin}');"],
        capture_output=True, text=True)
    if resultat.returncode != 0:
        return None
    try:
        return json.loads(resultat.stdout)
    except json.JSONDecodeError:
        return None


# ---------------------------------------------------------------- verifs

def verifier_ssh():
    resultat = subprocess.run(
        ["ssh", "-o", "BatchMode=yes", "-o", "ConnectTimeout=8", HOTE_SSH,
         "echo ok"],
        capture_output=True, text=True)
    if resultat.returncode != 0 or "ok" not in resultat.stdout:
        souci(f"connexion SSH à « {HOTE_SSH} » refusée : "
              + (resultat.stderr.strip().splitlines()[-1] if resultat.stderr.strip() else "échec"))
        note("voir ~/.ssh/config — alias attendu : " + HOTE_SSH)
        return False
    ok(f"ssh {HOTE_SSH} — joignable")
    return True


def verifier_php_distant():
    resultat = subprocess.run(
        ["ssh", HOTE_SSH, "php -r 'echo PHP_VERSION;'"],
        capture_output=True, text=True)
    if resultat.returncode != 0:
        souci("php introuvable côté serveur")
        return False
    ok(f"php distant {resultat.stdout.strip()}")
    return True


def verifier_base_distante(cfg):
    if cfg is None:
        note("config/base.php absent — rien à vérifier côté bases (voir action `config`)")
        return True
    reussite = True
    for base in (cfg["base_comptes"], cfg["base_syntropia"]):
        commande = (
            f"mysql -h{cfg['hote']} -P{cfg['port']} -u{cfg['utilisateur']} "
            f"--batch -e 'SELECT 1;' {base}"
        )
        resultat = subprocess.run(
            ["ssh", HOTE_SSH, f"MYSQL_PWD={_echappe(cfg['mot_de_passe'])} {commande}"],
            capture_output=True, text=True)
        if resultat.returncode != 0:
            souci(f"{base} — connexion refusée : "
                  + (resultat.stderr.strip().splitlines()[-1] if resultat.stderr.strip() else "échec"))
            reussite = False
        else:
            ok(f"{base} — joignable depuis le serveur")
    return reussite


def _echappe(valeur):
    """Un mot de passe posé dans une commande shell distante — entre
    guillemets simples, avec les guillemets simples qu'il contiendrait
    échappés à la façon POSIX. Jamais interpolé tel quel."""
    return "'" + valeur.replace("'", "'\\''") + "'"


def etat():
    titre("SSH")
    ssh_ok = verifier_ssh()
    if not ssh_ok:
        return False
    titre("PHP distant")
    php_ok = verifier_php_distant()
    titre("Configuration de production")
    cfg = config_prod()
    if cfg is None:
        note("config/base.php absent localement — copier config/base.php.exemple et le remplir")
    else:
        ok("config/base.php lu")
    titre("Bases distantes")
    base_ok = verifier_base_distante(cfg)
    return ssh_ok and php_ok and base_ok


# ---------------------------------------------------------------- actions

def _dossiers_parents():
    """`config/.htaccess` a un parent (`config/`) que rsync ne crée pas
    tout seul pour un fichier isolé — `--mkpath` s'est montré capricieux
    ici (échoue une fois sur deux avec `dry-run`, y compris quand tout va
    bien). Plus sûr : les créer nous-mêmes, une bonne fois, avant tout
    transfert."""
    dossiers = sorted({str(Path(WWW_DISTANT) / Path(e).parent) for e in ELEMENTS})
    resultat = subprocess.run(["ssh", HOTE_SSH, "mkdir -p " + " ".join(dossiers)],
                              capture_output=True, text=True)
    return resultat.returncode == 0


def fichiers(dry_run):
    titre("Fichiers" + (" (simulation)" if dry_run else ""))
    if not verifier_ssh():
        return False
    if not _dossiers_parents():
        souci("impossible de préparer les dossiers distants")
        return False
    reussite = True
    for element in ELEMENTS:
        source = RACINE / element
        if not source.exists():
            note(f"{element} absent, ignoré")
            continue
        cmd = ["rsync", "-azv"]
        if dry_run:
            cmd.append("--dry-run")
        for motif in EXCLUSIONS:
            cmd += ["--exclude", motif]
        if source.is_dir():
            cmd.append(str(source) + "/")
            cmd.append(f"{HOTE_SSH}:{WWW_DISTANT}/{element}/")
        else:
            cmd.append(str(source))
            cmd.append(f"{HOTE_SSH}:{WWW_DISTANT}/{element}")
        resultat = subprocess.run(cmd, capture_output=True, text=True)
        if resultat.returncode != 0:
            souci(f"{element} : " + (resultat.stderr.strip().splitlines()[-1] if resultat.stderr.strip() else "échec rsync"))
            reussite = False
            continue
        # Les deux premières lignes sont l'en-tête (« sending incremental
        # file list »), la fin le résumé de stats — seul l'entre-deux liste
        # de vrais fichiers.
        lignes = [l for l in resultat.stdout.splitlines()[1:]
                  if l and not l.startswith(("sent ", "total size", "created "))]
        if dry_run:
            note(f"{element} — {len(lignes)} fichier(s) à envoyer" if lignes else f"{element} — déjà à jour")
        else:
            ok(f"{element} — envoyé ({len(lignes)} fichier(s))")
    return reussite


def config():
    titre("Configuration")
    chemin = RACINE / "config" / "base.php"
    if not chemin.is_file():
        souci("config/base.php absent — copier config/base.php.exemple et le remplir d'abord")
        return False
    if not verifier_ssh():
        return False
    subprocess.run(["ssh", HOTE_SSH, f"mkdir -p {WWW_DISTANT}/config"], capture_output=True)
    resultat = subprocess.run(
        ["rsync", "-az", str(chemin), f"{HOTE_SSH}:{WWW_DISTANT}/config/base.php"],
        capture_output=True, text=True)
    if resultat.returncode != 0:
        souci(resultat.stderr.strip().splitlines()[-1] if resultat.stderr.strip() else "échec rsync")
        return False
    ok("config/base.php envoyé")
    return True


def base():
    titre("Schéma, sur les bases distantes")
    cfg = config_prod()
    if cfg is None:
        souci("config/base.php absent — voir config/base.php.exemple, puis `deployer.py config`")
        return False
    if not verifier_ssh():
        return False
    schemas = [("base_comptes", "00-comptes.sql"), ("base_syntropia", "01-syntropia.sql")]
    reussite = True
    for cle, fichier in schemas:
        chemin = RACINE / "sql" / fichier
        if not chemin.is_file():
            note(f"{fichier} absent, ignoré")
            continue
        base_nom = cfg[cle]
        commande = (
            f"mysql -h{cfg['hote']} -P{cfg['port']} -u{cfg['utilisateur']} "
            f"--batch {base_nom}"
        )
        resultat = subprocess.run(
            ["ssh", HOTE_SSH, f"MYSQL_PWD={_echappe(cfg['mot_de_passe'])} {commande}"],
            input=chemin.read_text(encoding="utf-8"), capture_output=True, text=True)
        if resultat.returncode != 0:
            souci(f"{fichier} → {base_nom} : "
                  + (resultat.stderr.strip().splitlines()[-1] if resultat.stderr.strip() else "échec"))
            reussite = False
        else:
            ok(f"{fichier} → {base_nom}")
    return reussite


def preparer_depot():
    """Assemble `ELEMENTS` dans un dossier à part, voisin du dépôt —
    exactement ce que `fichiers` envoie par rsync, mais posé sur disque
    pour qui préfère déposer à la main (FileZilla, un autre client FTP).
    `config/base.php` n'y figure jamais : il n'est même pas dans
    `ELEMENTS`, et ce n'est pas un hasard — un secret qui voyagerait par
    ce chemin-là serait un secret qui traîne dans un dossier de plus."""
    titre("Préparation du dépôt")
    if DEPOT.exists():
        shutil.rmtree(DEPOT)
    DEPOT.mkdir(parents=True)

    ignore = shutil.ignore_patterns(*EXCLUSIONS)
    for element in ELEMENTS:
        source = RACINE / element
        if not source.exists():
            note(f"{element} absent, ignoré")
            continue
        cible = DEPOT / element
        if source.is_dir():
            shutil.copytree(source, cible, ignore=ignore)
        else:
            cible.parent.mkdir(parents=True, exist_ok=True)
            shutil.copy2(source, cible)
        ok(f"{element}")

    # Garde-fou : si jamais `ELEMENTS` venait à changer et à y glisser un
    # secret sans qu'on le remarque au premier coup d'œil.
    fuite = DEPOT / "config" / "base.php"
    if fuite.exists():
        souci("config/base.php s'est retrouvé dans le dépôt — supprimé")
        fuite.unlink()

    fichiers_ = [p for p in DEPOT.rglob("*") if p.is_file()]
    poids = sum(p.stat().st_size for p in fichiers_) / 1048576
    titre(f"{len(fichiers_)} fichiers, {poids:.1f} Mo — {DEPOT}")

    binaires = sorted(p for p in fichiers_ if p.suffix.lower() in EXTENSIONS_BINAIRES)
    if binaires:
        note(f"{len(binaires)} fichier(s) binaire(s) — à déposer en mode BINAIRE,"
             " jamais ASCII (FileZilla : Transfert → Type de transfert → Binaire,"
             " ou laisser sur Automatique, qui les détecte correctement)")

    note(f"local (FileZilla) : {DEPOT}")
    note(f"distant (FileZilla) : /home/jubile/{WWW_DISTANT}/")
    return True


# ------------------------------------------------------------------ main

def main():
    action = sys.argv[1] if len(sys.argv) > 1 else None
    dry_run = "--dry-run" in sys.argv

    if action == "etat":
        return 0 if etat() else 1
    if action == "fichiers":
        return 0 if fichiers(dry_run) else 1
    if action == "config":
        return 0 if config() else 1
    if action == "base":
        return 0 if base() else 1
    if action == "preparer":
        return 0 if preparer_depot() else 1

    print(__doc__)
    return 1


if __name__ == "__main__":
    sys.exit(main())
