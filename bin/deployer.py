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
    python3 bin/deployer.py base        rejoue le schéma SQL sur les bases distantes, après une
                                        sauvegarde (nécessite config/base.php, local et déjà envoyé)
    python3 bin/deployer.py sauvegarde  sauvegarde les deux bases de production dans
                                        ../syntropia-sauvegardes (mysqldump, daté, compressé)
    python3 bin/deployer.py maintenance on|off|etat
                                        ferme l'atelier (« Mise à jour en cours », 503) ou le rouvre
    python3 bin/deployer.py mettre-a-jour
                                        tout dans l'ordre : vérifications, maintenance, sauvegarde,
                                        schéma, fichiers, réouverture — la voie normale d'une mise à jour
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

import gzip
import json
import os
import shutil
import subprocess
import sys
from datetime import datetime
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

# Les sauvegardes des bases de production, avant chaque `base` (chantier-mises-a-jour.md, Phase 1). Voisin du dépôt,
# jamais dedans : elles contiennent les comptes (empreintes de mots de passe) et les projets des personnes.
SAUVEGARDES = RACINE.parent / "syntropia-sauvegardes"
# On garde les cinq dernières (décision du porteur, 2026-10-01) — une sauvegarde = les deux bases d'un même horodatage.
SAUVEGARDES_GARDEES = 5

# L'adresse publique de l'atelier — le contrôle de fin de `mettre-a-jour` (la page d'accueil répond-elle 200 ?).
URL_PUBLIQUE = "https://jubile.alwaysdata.net/syntropia/"

# La première ligne du .htaccess de maintenance : c'est elle qui dit qu'une maintenance est en cours.
MARQUE_MAINTENANCE = "# MAINTENANCE SYNTROPIA"

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


def avertir_documentation():
    """Le livret « L'atelier » suit-il le code ? Un avertissement, jamais un refus :
    une correction de style n'a pas à toucher le livret (bin/verifier-documentation.py)."""
    r = subprocess.run([sys.executable, str(RACINE / "bin" / "verifier-documentation.py"), "--court"],
                       capture_output=True, text=True)
    for ligne in r.stdout.splitlines():
        note(ligne)


def fichiers(dry_run):
    titre("Fichiers" + (" (simulation)" if dry_run else ""))
    avertir_documentation()
    if not verifier_ssh():
        return False
    # Pendant une maintenance, le .htaccess de la racine est celui de la maintenance : ne pas le remplacer, sans quoi
    # l'atelier rouvrirait au milieu de la mise à jour. `maintenance off` remet le vrai.
    en_maintenance = maintenance_active()
    if not _dossiers_parents():
        souci("impossible de préparer les dossiers distants")
        return False
    reussite = True
    for element in ELEMENTS:
        source = RACINE / element
        if not source.exists():
            note(f"{element} absent, ignoré")
            continue
        if element == ".htaccess" and en_maintenance:
            note(".htaccess — gardé tel quel : maintenance en cours (rétabli par `maintenance off`)")
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


# ------------------------------------------------------------ maintenance

def _rewrite_base():
    """Le chemin de l'atelier sur le serveur (`/syntropia/`), lu dans le .htaccess normal — la même source que la
    réécriture, pour que la maintenance ne puisse pas viser un autre chemin."""
    for ligne in (RACINE / ".htaccess").read_text(encoding="utf-8").splitlines():
        morceaux = ligne.split()
        if len(morceaux) == 2 and morceaux[0] == "RewriteBase":
            return morceaux[1] if morceaux[1].endswith("/") else morceaux[1] + "/"
    raise RuntimeError("RewriteBase introuvable dans .htaccess")


def htaccess_maintenance(base=None):
    """Le .htaccess de la racine pendant une mise à jour (chantier-mises-a-jour.md, Phase 2) : tout répond 503 avec
    `public/maintenance.html`, sauf cette page et ses feuilles de style et fontes. `Retry-After` dit aux navigateurs et
    aux robots de revenir ; `no-store`, qu'aucune réponse de ce moment ne soit gardée en cache."""
    base = base or _rewrite_base()
    motif = "^" + base.replace(".", "\\.")
    return f"""{MARQUE_MAINTENANCE} — posé par bin/deployer.py le temps d'une mise à jour (chantier-mises-a-jour.md).
# Le .htaccess normal est rétabli par `python3 bin/deployer.py maintenance off`. Ne pas modifier à la main.
ErrorDocument 503 {base}public/maintenance.html
<IfModule mod_headers.c>
    Header always set Retry-After "300"
    Header always set Cache-Control "no-store"
</IfModule>
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase {base}
    # La page de maintenance, et ce dont elle a besoin, restent servies.
    RewriteCond %{{REQUEST_URI}} {motif}public/(maintenance\\.html|assets/)
    RewriteRule ^ - [L]
    RewriteRule ^assets/(.*)$ public/assets/$1 [L]
    # Tout le reste : 503.
    RewriteRule ^ - [R=503,L]
</IfModule>
"""


def maintenance_active():
    resultat = subprocess.run(["ssh", HOTE_SSH, f"head -n 1 {WWW_DISTANT}/.htaccess"], capture_output=True, text=True)
    return resultat.returncode == 0 and resultat.stdout.startswith(MARQUE_MAINTENANCE)


def maintenance(geste):
    """`on` : pose le .htaccess de maintenance (et la page qu'il sert) ; `off` : remet le .htaccess normal ; `etat` : le dit.
    Chaque pose passe par un fichier temporaire renommé — le .htaccess n'est jamais à moitié écrit."""
    titre(f"Maintenance — {geste}")
    if geste not in ("on", "off", "etat"):
        souci("geste attendu : on, off ou etat")
        return False
    if not verifier_ssh():
        return False
    active = maintenance_active()
    if geste == "etat":
        (note if active else ok)("maintenance EN COURS — l'atelier répond 503" if active else "pas de maintenance — l'atelier est ouvert")
        return True
    if geste == "on":
        page = RACINE / "public" / "maintenance.html"
        envoi = subprocess.run(["rsync", "-az", str(page), f"{HOTE_SSH}:{WWW_DISTANT}/public/maintenance.html"],
                               capture_output=True, text=True)
        if envoi.returncode != 0:
            souci("page de maintenance non envoyée — rien n'est fermé")
            return False
        contenu = htaccess_maintenance()
    else:
        contenu = (RACINE / ".htaccess").read_text(encoding="utf-8")
    pose = subprocess.run(
        ["ssh", HOTE_SSH, f"cat > {WWW_DISTANT}/.htaccess.nouveau && mv {WWW_DISTANT}/.htaccess.nouveau {WWW_DISTANT}/.htaccess"],
        input=contenu, capture_output=True, text=True)
    if pose.returncode != 0:
        souci(".htaccess non posé : " + (pose.stderr.strip().splitlines()[-1] if pose.stderr.strip() else "échec"))
        return False
    if geste == "on":
        ok("maintenance posée — toute page répond « Mise à jour en cours » (503)")
    else:
        ok("maintenance levée — .htaccess normal rétabli")
    return True


def repond(url):
    """Le code HTTP d'une adresse, sans suivre les redirections — `None` si elle ne répond pas."""
    r = subprocess.run(["curl", "-s", "-o", "/dev/null", "-w", "%{http_code}", "--max-time", "15", url],
                       capture_output=True, text=True)
    return int(r.stdout) if r.returncode == 0 and r.stdout.isdigit() else None


def mettre_a_jour():
    """Tout, dans l'ordre (chantier-mises-a-jour.md, Phase 2) : vérifications, maintenance, sauvegarde et schéma,
    fichiers, fin de maintenance, contrôle. Une étape qui échoue laisse l'atelier EN MAINTENANCE — mieux vaut fermé
    qu'à moitié à jour — et dit quoi faire."""
    if not etat():
        souci("vérifications en échec — rien n'a été touché")
        return False
    if not maintenance("on"):
        return False
    code = repond(URL_PUBLIQUE)
    if code != 503:
        souci(f"l'accueil répond {code}, pas 503 — la maintenance ne prend pas ; on la lève, rien d'autre n'est fait")
        maintenance("off")
        return False
    ok("l'accueil répond 503 : personne ne voit la mise à jour en cours")
    for etape, faire in (("schéma", base), ("fichiers", lambda: fichiers(False))):
        if not faire():
            titre("Arrêt")
            souci(f"étape « {etape} » en échec — l'atelier RESTE EN MAINTENANCE")
            note("corriger, relancer l'étape (`deployer.py base` ou `fichiers`), puis `deployer.py maintenance off`")
            return False
    if not maintenance("off"):
        souci("maintenance non levée — relancer `deployer.py maintenance off`")
        return False
    code = repond(URL_PUBLIQUE)
    (ok if code == 200 else souci)(f"l'accueil répond {code}")
    return code == 200


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


def sauvegarde():
    """Un `mysqldump` des deux bases de production, compressé sur le serveur, rapatrié dans `SAUVEGARDES` (chantier-
    mises-a-jour.md, Phase 1). Une sauvegarde n'est réputée faite que si elle se décompresse et se termine par la ligne
    de fin de `mysqldump` ; sinon le fichier est retiré et l'on rend False. Restaurer reste un geste à la main."""
    titre("Sauvegarde des bases de production")
    cfg = config_prod()
    if cfg is None:
        souci("config/base.php absent — voir config/base.php.exemple")
        return False
    if not verifier_ssh():
        return False
    SAUVEGARDES.mkdir(mode=0o700, exist_ok=True)
    horodatage = datetime.now().strftime("%Y-%m-%d_%H%M%S")
    reussite = True
    for cle in ("base_comptes", "base_syntropia"):
        base_nom = cfg[cle]
        cible = SAUVEGARDES / f"{horodatage}_{base_nom}.sql.gz"
        # `pipefail` : un échec de mysqldump ne doit pas se cacher derrière un gzip qui, lui, réussit.
        commande = (
            f"set -o pipefail; MYSQL_PWD={_echappe(cfg['mot_de_passe'])} mysqldump -h{cfg['hote']} -P{cfg['port']} "
            f"-u{cfg['utilisateur']} --single-transaction --no-tablespaces --default-character-set=utf8mb4 "
            f"{base_nom} | gzip -c"
        )
        fd = os.open(cible, os.O_WRONLY | os.O_CREAT | os.O_TRUNC, 0o600)
        with os.fdopen(fd, "wb") as sortie:
            resultat = subprocess.run(["ssh", HOTE_SSH, f"bash -c {_echappe(commande)}"], stdout=sortie,
                                      stderr=subprocess.PIPE)
        if resultat.returncode != 0 or not _sauvegarde_complete(cible):
            erreur = resultat.stderr.decode("utf-8", "replace").strip().splitlines()
            souci(f"{base_nom} — sauvegarde manquée : " + (erreur[-1] if erreur else "fichier incomplet"))
            cible.unlink(missing_ok=True)
            reussite = False
            continue
        ok(f"{base_nom} — {cible.name} ({cible.stat().st_size // 1024} Ko)")
    if not reussite:
        # Un jeu incomplet ne sert à rien et prête à confusion : on ne garde que des jeux entiers.
        for f in SAUVEGARDES.glob(f"{horodatage}_*.sql.gz"):
            f.unlink()
    if reussite:
        _elaguer_sauvegardes()
        note(f"dans {SAUVEGARDES} — restaurer : gunzip -c FICHIER | mysql … NOM_DE_LA_BASE (voir chantier-mises-a-jour.md)")
    return reussite


def _elaguer_sauvegardes():
    """Ne garder que les SAUVEGARDES_GARDEES plus récentes ; appelé seulement après une sauvegarde réussie, pour
    qu'un échec ne fasse jamais disparaître la dernière bonne."""
    horodatages = sorted({f.name[:17] for f in SAUVEGARDES.glob("*.sql.gz") if f.name[:17].count("_") == 1}, reverse=True)
    for ancien in horodatages[SAUVEGARDES_GARDEES:]:
        for f in SAUVEGARDES.glob(f"{ancien}_*.sql.gz"):
            f.unlink()
        note(f"sauvegarde du {ancien} retirée (on garde les {SAUVEGARDES_GARDEES} dernières)")


def _sauvegarde_complete(chemin):
    """Le fichier se décompresse en entier et se termine par « -- Dump completed », la dernière ligne qu'écrit
    mysqldump quand il est allé au bout."""
    try:
        derniere = b""
        with gzip.open(chemin, "rb") as f:
            for ligne in f:
                if ligne.strip():
                    derniere = ligne
        return derniere.startswith(b"-- Dump completed")
    except (OSError, EOFError):
        return False


def base():
    cfg = config_prod()
    if cfg is None:
        souci("config/base.php absent — voir config/base.php.exemple, puis `deployer.py config`")
        return False
    if not verifier_ssh():
        return False
    # Jamais sans sauvegarde : des personnes travaillent en production (chantier-mises-a-jour.md, Phase 1).
    if not sauvegarde():
        souci("schéma non rejoué : la sauvegarde a échoué")
        return False
    titre("Schéma, sur les bases distantes")
    # `02-especes-seed.sql` : du DML (INSERT IGNORE), rejoué comme le
    # schéma — voir bin/dev.py § SCHEMAS, tenu assorti à cette liste.
    schemas = [("base_comptes", "00-comptes.sql"), ("base_syntropia", "01-syntropia.sql"),
               ("base_syntropia", "02-especes-seed.sql")]
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
    if action == "sauvegarde":
        return 0 if sauvegarde() else 1
    if action == "maintenance":
        return 0 if maintenance(sys.argv[2] if len(sys.argv) > 2 else "etat") else 1
    if action == "mettre-a-jour":
        return 0 if mettre_a_jour() else 1
    if action == "preparer":
        return 0 if preparer_depot() else 1

    print(__doc__)
    return 1


if __name__ == "__main__":
    sys.exit(main())
