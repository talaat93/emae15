#!/usr/bin/env bash
# Assistant d'aide EMAE — à lancer sur le PC où Claude Code est installé.
#
# Va chercher les questions des techniciens et les rapports à corriger ou relire sur le site (aucun port à ouvrir chez vous), les
# fait répondre par Claude Code en mode « réponse seule » — aucun outil, aucun serveur MCP :
# il ne peut rien exécuter ni modifier sur l'ordinateur — puis renvoie la réponse au site.
#
#   cle.txt (à côté de ce script) : la clé créée dans Dispatcher → Réglages → Aide (Claude Code).
#   EMAE_SITE  : adresse du site (https://www.emaee.fr par défaut).
#   EMAE_PAUSE : secondes entre deux passages (20 par défaut).
set -u
DIR="$(cd "$(dirname "$0")" && pwd)"
SITE="${EMAE_SITE:-https://www.emaee.fr}"
PAUSE="${EMAE_PAUSE:-20}"
CLE="$(tr -d ' \r\n' < "$DIR/cle.txt" 2>/dev/null || true)"
[ -n "$CLE" ] || { echo "Clé absente : collez-la dans $DIR/cle.txt (Réglages → Aide (Claude Code))."; exit 1; }
command -v claude >/dev/null 2>&1 || { echo "Commande « claude » introuvable : installez Claude Code."; exit 1; }
command -v curl >/dev/null 2>&1 || { echo "Commande « curl » introuvable."; exit 1; }
cd "$DIR" || exit 1   # dossier neutre : aucun fichier de projet chargé par Claude Code

echo "Assistant EMAE démarré pour $SITE (passage toutes les ${PAUSE} s). Ctrl+C pour arrêter."
while true; do
  REP="$(curl -sS -m 30 -w $'\n%{http_code}' -H "Authorization: Bearer $CLE" "$SITE/api/assist.php?action=next&format=plain&v=2" 2>/dev/null)" || { sleep "$PAUSE"; continue; }
  CODE="${REP##*$'\n'}"
  CORPS="${REP%$'\n'*}"
  case "$CODE" in
    204) sleep "$PAUSE"; continue ;;
    401) echo "$(date '+%H:%M:%S') clé refusée par le site : recréez-la dans Réglages → Aide, puis collez-la dans cle.txt."; sleep 300; continue ;;
    200) ;;
    *)   echo "$(date '+%H:%M:%S') réponse inattendue du site (code $CODE)"; sleep "$PAUSE"; continue ;;
  esac
  # Identifiant : « aide-12 » (question d'un technicien) ou « travail-7 » (correction ou relecture d'un rapport).
  ID="$(printf '%s\n' "$CORPS" | head -n 1 | tr -cd 'a-z0-9-')"
  case "$ID" in aide-[0-9]*|travail-[0-9]*|[0-9]*) ;; *) sleep "$PAUSE"; continue ;; esac
  printf '%s\n' "$CORPS" | tail -n +2 > "$DIR/.question.txt"
  echo "$(date '+%H:%M:%S') $ID"
  # Réponse seule : --tools "" (aucun outil) et --strict-mcp-config sans configuration (aucun serveur MCP).
  if claude -p --tools "" --strict-mcp-config --output-format text < "$DIR/.question.txt" > "$DIR/.reponse.txt" 2> "$DIR/.erreur.txt" && [ -s "$DIR/.reponse.txt" ]; then
    # Chemin relatif (le script est déjà dans son dossier) : sous Windows, curl ne lit pas « /c/... ».
    CODE_REP="$(curl -sS -m 200 -o /dev/null -w '%{http_code}' -H "Authorization: Bearer $CLE" \
         --data-urlencode "id=$ID" --data-urlencode "answer@.reponse.txt" "$SITE/api/assist.php?action=answer")"
    case "$CODE_REP" in
      200) echo "  → réponse envoyée" ;;
      409) echo "  → déjà traitée par le relais ou par le bureau" ;;
      422) echo "  → réponse illisible : le site fait le travail avec la clé API" ;;
      *)   echo "  → envoi impossible (code ${CODE_REP:-aucun}) : le site prendra le relais" ;;
    esac
  else
    echo "  → Claude Code n'a pas répondu : $(head -c 300 "$DIR/.erreur.txt")"
  fi
  rm -f "$DIR/.question.txt" "$DIR/.reponse.txt" "$DIR/.erreur.txt"
done
