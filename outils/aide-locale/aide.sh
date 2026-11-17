#!/usr/bin/env bash
# Assistant d'aide EMAE — à lancer sur le PC où Claude Code est installé.
#
# Va chercher les questions des techniciens sur le site (aucun port à ouvrir chez vous), les
# fait répondre par Claude Code en mode « réponse seule » — aucun outil, aucun serveur MCP :
# il ne peut rien exécuter ni modifier sur l'ordinateur — puis renvoie la réponse au site.
#
#   cle.txt (à côté de ce script) : la clé créée dans Dispatcher → Réglages → Aide (Claude Code).
#   EMAE_SITE  : adresse du site (https://emaee.fr par défaut).
#   EMAE_PAUSE : secondes entre deux passages (20 par défaut).
set -u
DIR="$(cd "$(dirname "$0")" && pwd)"
SITE="${EMAE_SITE:-https://emaee.fr}"
PAUSE="${EMAE_PAUSE:-20}"
CLE="$(tr -d ' \r\n' < "$DIR/cle.txt" 2>/dev/null || true)"
[ -n "$CLE" ] || { echo "Clé absente : collez-la dans $DIR/cle.txt (Réglages → Aide (Claude Code))."; exit 1; }
command -v claude >/dev/null 2>&1 || { echo "Commande « claude » introuvable : installez Claude Code."; exit 1; }
command -v curl >/dev/null 2>&1 || { echo "Commande « curl » introuvable."; exit 1; }
cd "$DIR" || exit 1   # dossier neutre : aucun fichier de projet chargé par Claude Code

echo "Assistant EMAE démarré pour $SITE (passage toutes les ${PAUSE} s). Ctrl+C pour arrêter."
while true; do
  REP="$(curl -sS -m 30 -w $'\n%{http_code}' -H "Authorization: Bearer $CLE" "$SITE/api/assist.php?action=next&format=plain" 2>/dev/null)" || { sleep "$PAUSE"; continue; }
  CODE="${REP##*$'\n'}"
  CORPS="${REP%$'\n'*}"
  case "$CODE" in
    204) sleep "$PAUSE"; continue ;;
    401) echo "$(date '+%H:%M:%S') clé refusée par le site : recréez-la dans Réglages → Aide, puis collez-la dans cle.txt."; sleep 300; continue ;;
    200) ;;
    *)   echo "$(date '+%H:%M:%S') réponse inattendue du site (code $CODE)"; sleep "$PAUSE"; continue ;;
  esac
  ID="$(printf '%s\n' "$CORPS" | head -n 1 | tr -cd '0-9')"
  [ -n "$ID" ] || { sleep "$PAUSE"; continue; }
  printf '%s\n' "$CORPS" | tail -n +2 > "$DIR/.question.txt"
  echo "$(date '+%H:%M:%S') question n° $ID"
  # Réponse seule : --tools "" (aucun outil) et --strict-mcp-config sans configuration (aucun serveur MCP).
  if claude -p --tools "" --strict-mcp-config --output-format text < "$DIR/.question.txt" > "$DIR/.reponse.txt" 2> "$DIR/.erreur.txt" && [ -s "$DIR/.reponse.txt" ]; then
    if curl -sS -m 30 -o /dev/null -w '%{http_code}' -H "Authorization: Bearer $CLE" \
         --data-urlencode "id=$ID" --data-urlencode "answer@$DIR/.reponse.txt" "$SITE/api/assist.php?action=answer" | grep -q '^200$'; then
      echo "  → réponse envoyée"
    else
      echo "  → réponse non acceptée (déjà répondue par le relais ou par le bureau)"
    fi
  else
    echo "  → Claude Code n'a pas répondu : $(head -c 300 "$DIR/.erreur.txt")"
  fi
  rm -f "$DIR/.question.txt" "$DIR/.reponse.txt" "$DIR/.erreur.txt"
done
