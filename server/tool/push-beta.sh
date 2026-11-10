#!/bin/sh
# The coordinator's push to the beta: rebuilds the stylesheets (web/tool/css-restore.sh --list names all five) when
# input.css changed since the last push, then tests exactly that commit (H) in a detached worktree of its own (2026-11-02: the
# suite once ran in the main checkout while agents ff-merged into it, saw mixed files and failed two
# deploys), and pushes only H (newer commits that landed meanwhile wait for the next run).
# `sh server/tool/push-beta.sh`
set -eu
cd "$(dirname "$0")/../.."
[ -z "$(git status --porcelain)" ] || { echo "The checkout isn't clean; not pushing." >&2; exit 1; }
git fetch -q origin
# Always rebuilt (under a second): new utility classes in a JS template change it too, not only
# input.css, and CI fails a stale app.css (2026-10-31).
# Through the land lock, so an agent's ff-merge never races this commit (2026-11-01).
SHEETS=$(sh web/tool/css-restore.sh --list)
export SHEETS
sh server/tool/land-lock.sh sh -c '(cd web && sh tool/tailwind.sh > /dev/null) && { git diff --quiet -- $SHEETS || git commit -q -m "App: the stylesheets are rebuilt" -- $SHEETS; }' \
    || { echo "Rebuilding the stylesheet failed; not pushing." >&2; exit 1; }
H=$(git rev-parse HEAD)
MAIN=$(pwd)

# H, checked out alone: landings move the main checkout on, never this one. Its .env is the main
# checkout's (a symlink, removed by name before the worktree goes, so nothing it points at is
# touched). Its vendor is its own: a symlinked vendor makes Composer autoload the main checkout's
# app classes, not H's (PHP resolves the real path; backend, 2026-11-02). The content is linked in at
# H's own content.lock.
SCRATCH=$(mktemp -d)
W="$SCRATCH/h"
cleanup() {
    for link in "$W/server/.env"; do
        if [ -L "$link" ]; then rm "$link"; fi
    done
    git -C "$MAIN" worktree remove --force "$W" > /dev/null 2>&1 || true
    git -C "$MAIN" worktree prune
    rm -rf "$SCRATCH"
}
trap cleanup EXIT
git worktree add -q --detach "$W" "$H" || { echo "Couldn't check out $H on its own; not pushing." >&2; exit 1; }
ln -s "$MAIN/server/.env" "$W/server/.env"
cd "$W"
[ "$(git rev-parse HEAD)" = "$H" ] || { echo "The test checkout isn't $H; not pushing." >&2; exit 1; }
(cd server && composer install -q --no-interaction) || { echo "Installing H's PHP packages failed; not pushing." >&2; exit 1; }
# CI fails a stale stylesheet; a build in the main checkout can differ from H's clean one (2026-11-02).
mkdir -p web/bin && { cp -p "$MAIN"/web/bin/tailwindcss-* web/bin/ 2> /dev/null || true; }
# shellcheck disable=SC2086 # the five paths, split on purpose
(cd web && sh tool/tailwind.sh > /dev/null 2>&1) && git diff --quiet -- $SHEETS \
    && test -z "$(git ls-files --others --exclude-standard -- web/css)" \
    || { echo "A stylesheet at $H ($SHEETS) differs from a clean build; not pushing." >&2; exit 1; }

# The courses and songs come from their own repo at the commit .do/content.lock pins.
sh server/tool/content.sh link > /dev/null || { echo "Linking the pinned content failed; not pushing." >&2; exit 1; }
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
# The pushes are from the main checkout's repository (the same one: a worktree shares it).
cd "$MAIN"
# Agents may land more commits meanwhile: only the tested commit goes out; they wait for the next run.
# CI gates production (Hillel, 2026-10-31: "make sure tests pass before we push to prod"), on the
# public repo since 2026-11-10 (Hillel: "can we change to running the tests on the public
# Stackapella/stackapella repo instead? It's free there"; Actions on this private repo stopped for
# billing). public-ci.sh exports H as the public tree (export.py, every scan), pushes it to the public
# `release` branch and waits for test.yml's own verdict on that commit. Only green lets H reach main,
# which App Platform deploys; then the public main moves to that same tested tree, so the public repo
# refreshes with every deploy.
sh server/tool/open-source/public-ci.sh check "$H" "$SCRATCH/public-commit" \
    || { echo "The public CI gate failed for $H (above); not pushing." >&2; exit 1; }
P=$(cat "$SCRATCH/public-commit")
echo "CI passed for $H"
git push -q origin "$H:main"
echo "Pushed $H"
sh server/tool/open-source/public-ci.sh publish "$P" || echo "The public repo's main stays where it was; the deploy is unaffected." >&2
# Every agent's branch is backed up too (backup/agent/*, overwritten each time), so unlanded work is
# saved in GitHub as well; these branches never deploy.
git push -q --force origin '+refs/heads/agent/*:refs/heads/backup/agent/*' && echo "Backed up the agent branches"
