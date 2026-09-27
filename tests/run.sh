#!/usr/bin/env bash
# Lance toute la suite de tests puis le contrôle de syntaxe du site.
#
#   bash tests/run.sh
#
# Les tests n'ont besoin d'aucune base de données : chacun remplace
# db_fetch / db_fetch_all / db_execute par une base en mémoire, puis charge
# les vrais fichiers de includes/. Ils tournent donc partout où PHP 8 est
# installé, y compris en local avant un téléversement.

set -uo pipefail
cd "$(dirname "$0")/.."

vert=$'\033[32m'; rouge=$'\033[31m'; gris=$'\033[90m'; fin=$'\033[0m'
total=0; casses=0

echo
echo "── Tests ──"
for f in tests/test_*.php; do
    sortie=$(php "$f" 2>&1)
    derniere=$(printf '%s' "$sortie" | tail -1)
    nom=$(basename "$f" .php)
    if [[ "$derniere" == *"PASSENT"* ]]; then
        n=$(printf '%s' "$derniere" | grep -o '[0-9]\+' | head -1)
        total=$((total + n))
        printf '  %s✔%s %-22s %s assertions\n' "$vert" "$fin" "$nom" "$n"
    else
        casses=$((casses + 1))
        printf '  %s✘%s %-22s %s\n' "$rouge" "$fin" "$nom" "$derniere"
        printf '%s\n' "$sortie" | grep -A3 FAIL | sed 's/^/      /'
    fi
done

echo
echo "── Syntaxe PHP ──"
erreurs=$(find . -name '*.php' -not -path './.git/*' -print0 \
          | xargs -0 -n1 php -l 2>&1 | grep -v '^No syntax errors' || true)
nb=$(find . -name '*.php' -not -path './.git/*' | wc -l | tr -d ' ')
if [[ -n "$erreurs" ]]; then
    casses=$((casses + 1))
    printf '  %s✘%s\n%s\n' "$rouge" "$fin" "$erreurs"
else
    printf '  %s✔%s %s fichiers sans erreur\n' "$vert" "$fin" "$nb"
fi

echo
if [[ $casses -eq 0 ]]; then
    printf '%sTout passe — %s assertions.%s\n\n' "$vert" "$total" "$fin"
else
    printf '%s%s bloc(s) en échec.%s %sVoir le détail ci-dessus.%s\n\n' "$rouge" "$casses" "$fin" "$gris" "$fin"
fi
exit $((casses == 0 ? 0 : 1))
