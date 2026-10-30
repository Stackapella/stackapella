#!/bin/sh
# The coordinator's push to the beta: rebuilds web/css/app.css when input.css changed since the
# last push, runs the leak check, migrates the dev database and the full server suite (through the
# lock), and pushes only the hash it tested (newer commits that landed meanwhile wait for the next run).
# `sh server/tool/push-beta.sh`
set -eu
cd "$(dirname "$0")/../.."
[ -z "$(git status --porcelain)" ] || { echo "The checkout isn't clean; not pushing." >&2; exit 1; }
git fetch -q origin
if ! git diff --quiet origin/main HEAD -- web/css/input.css; then
    (cd web && sh tool/tailwind.sh > /dev/null)
    if ! git diff --quiet -- web/css/app.css; then
        git commit -q -m "App: the stylesheet is rebuilt" -- web/css/app.css
    fi
fi
# The courses and songs come from their own repo at the commit .do/content.lock pins.
sh server/tool/content.sh link > /dev/null || { echo "Linking the pinned content failed; not pushing." >&2; exit 1; }
H=$(git rev-parse HEAD)
sh server/tool/leak-check.sh > /dev/null 2>&1 || { echo "The leak check failed; not pushing." >&2; exit 1; }
# The web client must match the contract, and only api.js may call /api (the web suite's two fast
# checks, so a red web suite never reaches the beta).
if [ -f server/tool/contract-client-check.py ]; then
    python3 -I server/tool/contract-client-check.py . || { echo "api.js and the contract disagree; not pushing." >&2; exit 1; }
fi
python3 -I web/tool/api_check.py || { echo "A module calls /api outside api.js; not pushing." >&2; exit 1; }
# Every app module must parse: two clean merges can still declare a name twice, and the suites
# never load the views, so one bad line would stop the whole app booting.
T=$(mktemp -d)
for f in $(find web/js -name '*.js'); do
    cp "$f" "$T/m.mjs"
    node --check "$T/m.mjs" 2> /dev/null || { echo "$f doesn't parse as modules do; not pushing." >&2; rm -rf "$T"; exit 1; }
done
rm -rf "$T"
# The app must start in a real browser with a clean console: the suites never load the views, and
# a live site can't take a build that doesn't start (launch night shipped two).
PORT=8497 FLOW_SIZES=desktop bash web/tool/flows.sh console_clean > /dev/null 2>&1 \
    || { echo "The app doesn't start cleanly in a browser (flows.sh console_clean); not pushing." >&2; exit 1; }
(cd server && php artisan migrate --force > /dev/null)
out=$(sh server/tool/test.sh --compact 2>&1) || { printf '%s\n' "$out" | tail -5 >&2; echo "The server suite failed; not pushing $H." >&2; exit 1; }
printf '%s\n' "$out" | tail -1
# Agents may land more commits meanwhile: only the tested commit goes out; they wait for the next run.
git push -q origin "$H:main"
echo "Pushed $H"
# Every agent's branch is backed up too (backup/agent/*, overwritten each time), so unlanded work is
# saved in GitHub as well; these branches never deploy.
git push -q --force origin '+refs/heads/agent/*:refs/heads/backup/agent/*' && echo "Backed up the agent branches"
