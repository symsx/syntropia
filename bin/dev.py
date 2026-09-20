#!/usr/bin/env python3
"""Serveur de développement local.

Adapté de `application/bin/dev.py` (dépôt de référence, 709 lignes) —
réduit à ce dont syntropia a besoin aujourd'hui. Ce que le script de
référence fait et que celui-ci NE fait PAS : créer les bases et
l'utilisateur MySQL (nécessite des droits d'administration que ce
projet n'a pas — la base `syntropia` et `syntropia_comptes` sont créées
une fois pour toutes par le porteur, voir architecture-technique.md
§ Le backend), rejouer des évolutions (aucune encore écrite), contrôler
la portabilité MariaDB, peupler des simulations.

    python3 bin/dev.py            initialise le schéma si besoin, puis sert
    python3 bin/dev.py init       schéma seulement (idempotent)
    python3 bin/dev.py serve      sert seulement
    python3 bin/dev.py etat       diagnostic
    python3 bin/dev.py compte     créer ou modifier un compte — voir bin/compte.php
    python3 bin/dev.py verifier-export [id…]
                                  l'aller-retour export → import → export — voir bin/verifier-export-import.php

Aucune dépendance : le client `mysql` est appelé en sous-processus,
comme le serait `php`. La configuration vient de `config/local.env`,
hors dépôt, ou de l'environnement.
"""

import os
import signal
import subprocess
import sys
from pathlib import Path

RACINE = Path(__file__).resolve().parents[1]
CONFIG = RACINE / "config" / "local.env"
SQL = RACINE / "sql"
PUBLIC = RACINE / "public"

PHP_MIN = (8, 1)
EXTENSIONS = ("pdo_mysql", "mbstring", "json", "session")

DEFAUTS = {
    "SYNTROPIA_DB_HOTE": "127.0.0.1",
    "SYNTROPIA_DB_PORT": "3306",
    "SYNTROPIA_DB_USER": "syntropia",
    "SYNTROPIA_DB_MDP": "",
    "SYNTROPIA_DB_COMPTES": "syntropia_comptes",
    "SYNTROPIA_DB_BASE": "syntropia",
    "SYNTROPIA_HOTE": "127.0.0.1",
    "SYNTROPIA_PORT": "8081",
}

# Quelle base reçoit quel fichier. Les fichiers ne portent ni `CREATE
# DATABASE` ni `USE` — voir sql/00-comptes.sql et sql/01-syntropia.sql.
# `02-especes-seed.sql` est du DML (INSERT IGNORE), pas du schéma : à
# part pour que la table `espece` reste un CREATE TABLE pur, comme le
# reste du fichier — voir chantier-scenario-plantation.md, Phase 1.
SCHEMAS = [("SYNTROPIA_DB_COMPTES", "00-comptes.sql"),
           ("SYNTROPIA_DB_BASE", "01-syntropia.sql"),
           ("SYNTROPIA_DB_BASE", "02-especes-seed.sql")]

VERT, ROUGE, JAUNE, GRIS, FIN_ = "\033[32m", "\033[31m", "\033[33m", "\033[90m", "\033[0m"


def ok(m):     print(f"  {VERT}✓{FIN_} {m}")
def souci(m):  print(f"  {ROUGE}✗{FIN_} {m}")
def note(m):   print(f"  {JAUNE}·{FIN_} {m}")
def titre(m):  print(f"\n{m}")


# ---------------------------------------------------------------- config

def reglages():
    """L'environnement d'abord, le fichier à défaut."""
    valeurs = dict(DEFAUTS)
    if CONFIG.is_file():
        for ligne in CONFIG.read_text(encoding="utf-8").splitlines():
            ligne = ligne.strip()
            if ligne and not ligne.startswith("#") and "=" in ligne:
                cle, _, val = ligne.partition("=")
                valeurs[cle.strip()] = val.strip().strip('"').strip("'")
    valeurs.update({c: os.environ[c] for c in valeurs if c in os.environ})
    return valeurs


def env_php(cfg):
    """L'environnement que PHP recevra — `Configuration::reglage()` lit
    l'environnement d'abord, `config/local.env` à défaut."""
    env = dict(os.environ)
    env.update({c: v for c, v in cfg.items() if v != ""})
    env["SYNTROPIA_BASE"] = "/"
    return env


# ---------------------------------------------------------------- verifs

def verifier_php():
    try:
        brut = subprocess.run(["php", "-r", "echo PHP_VERSION;"],
                              capture_output=True, text=True, check=True).stdout
    except (FileNotFoundError, subprocess.CalledProcessError):
        souci("php absent du PATH")
        return False
    version = tuple(int(n) for n in brut.split("-")[0].split(".")[:2])
    if version < PHP_MIN:
        souci(f"php {brut} — la cible est {PHP_MIN[0]}.{PHP_MIN[1]} minimum")
        return False
    ok(f"php {brut}")

    modules = subprocess.run(["php", "-m"], capture_output=True, text=True).stdout.lower()
    manquantes = [e for e in EXTENSIONS if e not in modules]
    if manquantes:
        souci("extensions manquantes : " + ", ".join(manquantes))
        return False
    ok("extensions : " + ", ".join(EXTENSIONS))
    return True


def mysql(cfg, sql=None, fichier=None, base=None):
    """Appelle le client `mysql`. Le mot de passe passe par l'environnement,
    jamais par la ligne de commande, où il serait visible de tous."""
    cmd = ["mysql", "--protocol=TCP", f"-h{cfg['SYNTROPIA_DB_HOTE']}",
           f"-P{cfg['SYNTROPIA_DB_PORT']}", f"-u{cfg['SYNTROPIA_DB_USER']}", "--batch"]
    if base:
        cmd.append(base)
    env = dict(os.environ)
    if cfg["SYNTROPIA_DB_MDP"]:
        env["MYSQL_PWD"] = cfg["SYNTROPIA_DB_MDP"]
    entree = fichier.read_text(encoding="utf-8") if fichier else sql
    return subprocess.run(cmd, input=entree, capture_output=True, text=True, env=env)


def verifier_base(cfg):
    """Les deux bases sont-elles joignables ? Ne tente jamais de les créer
    — voir l'en-tête du fichier : ça demande des droits que ce projet n'a
    pas, et c'est déjà fait."""
    partout_ok = True
    # dict.fromkeys, pas un set : deux fichiers de SCHEMAS peuvent viser la
    # même base (02-especes-seed.sql vise SYNTROPIA_DB_BASE comme
    # 01-syntropia.sql) — la vérifier deux fois n'apporterait rien.
    for cle in dict.fromkeys(cle for cle, _ in SCHEMAS):
        base = cfg[cle]
        essai = mysql(cfg, sql="SELECT 1;", base=base)
        if essai.returncode != 0:
            souci(f"{base} — connexion refusée : "
                  + essai.stderr.strip().splitlines()[-1])
            print(f"""
{GRIS}  La base ou l'utilisateur n'existe peut-être pas encore.
  Voir architecture-technique.md § Le backend pour les commandes SQL
  (CREATE DATABASE / CREATE USER / GRANT), à exécuter une fois en tant
  qu'administrateur MySQL.{FIN_}""")
            partout_ok = False
        else:
            ok(f"{base} — joignable")
    return partout_ok


# ---------------------------------------------------------------- actions

def init(cfg):
    titre("Schéma")
    if not SQL.is_dir():
        note("aucun dossier sql/")
        return True
    reussite = True
    for cle, fichier in SCHEMAS:
        base = cfg[cle]
        chemin = SQL / fichier
        if not chemin.is_file():
            note(f"{fichier} absent, ignoré")
            continue
        resultat = mysql(cfg, fichier=chemin, base=base)
        if resultat.returncode != 0:
            souci(f"{fichier} → {base} : " + resultat.stderr.strip().splitlines()[-1])
            reussite = False
        else:
            ok(f"{fichier} → {base}")
    return reussite


def etat(cfg):
    titre("PHP")
    php_ok = verifier_php()
    titre("Bases")
    base_ok = verifier_base(cfg)
    return php_ok and base_ok


def serve(cfg):
    """Lance `php -S`, et le termine avec nous — pas orphelin derrière.

    `subprocess.run` seul ne propage pas un signal reçu par ce script à
    son enfant : un `kill` sur ce process, ou un arrêt en arrière-plan,
    laissait `php` tourner tout seul, le port occupé pour la prochaine
    fois. Le process group ci-dessous rend ce script responsable de le
    terminer, quelle que soit la façon dont on lui demande de s'arrêter.
    """
    titre("Serveur")
    hote, port = cfg["SYNTROPIA_HOTE"], cfg["SYNTROPIA_PORT"]
    ok(f"http://{hote}:{port}/ — Ctrl+C pour arrêter")
    # Un SIGTERM (kill, arrêt d'arrière-plan) ne lève rien par défaut : sans
    # ce relais, le bloc finally plus bas ne s'exécuterait jamais pour lui.
    signal.signal(signal.SIGTERM, lambda *_: sys.exit(0))
    processus = subprocess.Popen(["php", "-S", f"{hote}:{port}", "-t", str(PUBLIC)],
                                  env=env_php(cfg), cwd=RACINE, start_new_session=True)
    try:
        processus.wait()
    except (KeyboardInterrupt, SystemExit):
        pass
    finally:
        if processus.poll() is None:
            os.killpg(processus.pid, signal.SIGTERM)
            try:
                processus.wait(timeout=5)
            except subprocess.TimeoutExpired:
                os.killpg(processus.pid, signal.SIGKILL)
    return True


def compte(cfg, arguments):
    """Crée ou modifie un compte — c'est la voie du PREMIER compte :
    `inscription.php` exige une invitation, et la première invitation
    n'a personne pour l'ouvrir. Le mot de passe est lu au clavier sans
    être affiché, et ne transite jamais par la ligne de commande."""
    import getpass
    identifiant = arguments[0] if arguments else input("identifiant : ").strip()
    nom = arguments[1] if len(arguments) > 1 else input("nom affiché : ").strip()
    role = arguments[2] if len(arguments) > 2 else "administrateur"
    mot = getpass.getpass("mot de passe : ")
    if mot != getpass.getpass("confirmer    : "):
        souci("les deux saisies diffèrent")
        return False
    resultat = subprocess.run(["php", str(RACINE / "bin" / "compte.php"), identifiant, nom, role],
                              input=mot, capture_output=True, text=True, env=env_php(cfg))
    if resultat.returncode != 0:
        souci(resultat.stderr.strip() or "échec")
        return False
    ok(resultat.stdout.strip())
    return True


def verifier_export(cfg, arguments):
    """L'aller-retour d'un projet — à rejouer à chaque changement de schéma. Passe par ici, jamais par
    `php bin/verifier-export-import.php` : un appel direct retomberait sur `config/base.php`, donc la
    production (le script refuse de tourner hors de ce poste, mais ne comptez pas sur ce seul garde-fou)."""
    if not verifier_php() or not verifier_base(cfg):
        return False
    resultat = subprocess.run(["php", str(RACINE / "bin" / "verifier-export-import.php"), *arguments],
                              env=env_php(cfg))
    return resultat.returncode == 0


# ------------------------------------------------------------------ main

def main():
    action = sys.argv[1] if len(sys.argv) > 1 else "tout"
    cfg = reglages()

    if action == "etat":
        return 0 if etat(cfg) else 1
    if action == "init":
        return 0 if init(cfg) else 1
    if action == "serve":
        return 0 if serve(cfg) else 1
    if action == "compte":
        return 0 if compte(cfg, sys.argv[2:]) else 1
    if action == "verifier-export":
        return 0 if verifier_export(cfg, sys.argv[2:]) else 1
    if action == "tout":
        if not verifier_php():
            return 1
        if not verifier_base(cfg):
            return 1
        if not init(cfg):
            return 1
        return 0 if serve(cfg) else 1

    print(__doc__)
    return 1


if __name__ == "__main__":
    sys.exit(main())
