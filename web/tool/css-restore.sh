#!/bin/sh
# Puts back, as committed, every stylesheet tool/tailwind.sh builds: app.css and site.css, game mode's own app-game.css
# and site-game.css (docs/css-split-plan.md, step 3), and courses.css. A land script runs it after building, so the
# rebuilt sheets never dirty a worktree or main (the coordinator rebuilds and commits them with each push).
#
#   sh web/tool/css-restore.sh          # from anywhere
#   sh web/tool/css-restore.sh --list   # their paths from the repo's root, for a script that names them
set -eu
cd "$(dirname "$0")/../.."
SHEETS="web/css/app.css web/css/app-game.css web/css/site.css web/css/site-game.css web/css/courses.css"
if [ "${1:-}" = "--list" ]; then
    echo "$SHEETS"
    exit 0
fi
# shellcheck disable=SC2086 # the five paths, split on purpose
git checkout -- $SHEETS
