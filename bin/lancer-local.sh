#!/bin/bash
# Lance le serveur de développement local et ouvre Syntropia dans Firefox
# dès qu'il répond — pensé pour le lanceur de bureau (voir le lanceur
# « Syntropia (local) » sur le Bureau). Pour l'usage habituel en ligne de
# commande, `bin/dev.py` seul suffit ; ce script n'ajoute que l'ouverture
# automatique du navigateur, en tâche de fond pendant que le serveur
# démarre au premier plan (Ctrl+C l'arrête normalement, comme dev.py).
set -e
cd "$(dirname "$0")/.."

HOTE=127.0.0.1
PORT=8081
if [ -f config/local.env ]; then
    H=$(grep -oP '^SYNTROPIA_HOTE=\K.*' config/local.env 2>/dev/null || true)
    P=$(grep -oP '^SYNTROPIA_PORT=\K.*' config/local.env 2>/dev/null || true)
    [ -n "$H" ] && HOTE="$H"
    [ -n "$P" ] && PORT="$P"
fi
URL="http://$HOTE:$PORT/"

# 40 x 0,5 s = 20 s d'attente au plus — au-delà, le serveur n'a
# probablement pas démarré (voir son propre message d'erreur ci-dessus)
# et il est inutile d'ouvrir un onglet sur rien.
(
  for _ in $(seq 1 40); do
    if curl -s -o /dev/null "$URL" 2>/dev/null; then
      firefox "$URL"
      exit 0
    fi
    sleep 0.5
  done
) &

exec python3 bin/dev.py
