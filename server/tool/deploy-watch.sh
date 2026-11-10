#!/bin/sh
# After a push: wait until the live site serves that build, then run the launch smoke against the
# real hosts (read-only), once more after 30 s if it fails (a cold start). On a second failure it says
# how to roll back. Run it outside the land lock:
#   sh server/tool/deploy-watch.sh <commit>
set -u
cd "$(dirname "$0")/../.."
H=$(git rev-parse --short=8 "${1:-origin/main}")
echo "Waiting for app.stackapella.com to serve $H..."
i=0
until curl -s https://app.stackapella.com/up | grep -q "\"build\":\"$H"; do
    i=$((i + 1))
    if [ $i -gt 60 ]; then
        echo "The deploy of $H didn't go live within 20 minutes: check App Platform, Activity." >&2
        exit 1
    fi
    sleep 20
done
echo "Live: $H."
# Songs the deploy's sync skipped (each still on its live version): never a failed deploy since
# Sakura Sakura's (2026-11-09), so they're said here, loudly, rather than found later in Admin.
curl -s https://app.stackapella.com/up | python3 -I -c '
import json, sys
try:
    songs = json.load(sys.stdin).get("songsNotSynced") or []
except ValueError:
    songs = []
if songs:
    print(f"SONGS NOT SYNCED ({len(songs)}), each still on its live version; fix in the content repo, then pin and deploy:", file=sys.stderr)
    for line in songs:
        print("  " + line, file=sys.stderr)
'
echo "Waiting a minute for every instance and cache to settle..."
sleep 60
SMOKE=${SMOKE:-python3 -I web/tool/launch_smoke.py}
LOG=/tmp/stackapella-smoke.log
smoke() { $SMOKE > "$LOG" 2>&1; }
echo "Running the launch smoke..."
if smoke; then
    tail -1 "$LOG"
    echo "Deploy $H is healthy."
    exit 0
fi
# A fresh instance's first answers can be slow enough to fail a step (the landing's Start to lesson 1,
# twice in the launch week) and then pass every rerun: one more try after 30 s before calling it failed.
grep FAIL "$LOG" >&2
echo "Trying the smoke once more in ${SMOKE_RETRY_WAIT:-30} s (a cold start?)..."
sleep "${SMOKE_RETRY_WAIT:-30}"
if smoke; then
    tail -1 "$LOG"
    echo "Deploy $H is healthy: passed on retry (cold start)."
    exit 0
fi
grep FAIL "$LOG" >&2
echo "Deploy $H FAILED the smoke twice. Roll back: App Platform, the stackapella app, Activity, the previous" >&2
echo "deployment's menu, Rollback. Then fix on main and push again." >&2
exit 1
