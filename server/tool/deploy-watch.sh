#!/bin/sh
# After a push: wait until the live site serves that build, then run the launch smoke against the
# real hosts (read-only). On a failure it says how to roll back. Run it outside the land lock:
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
echo "Live: $H. Waiting a minute for every instance and cache to settle..."
sleep 60
echo "Running the launch smoke..."
if python3 -I web/tool/launch_smoke.py > /tmp/stackapella-smoke.log 2>&1; then
    tail -1 /tmp/stackapella-smoke.log
    echo "Deploy $H is healthy."
else
    grep FAIL /tmp/stackapella-smoke.log >&2
    echo "Deploy $H FAILED the smoke. Roll back: App Platform, the stackapella app, Activity, the previous" >&2
    echo "deployment's menu, Rollback. Then fix on main and push again." >&2
    exit 1
fi
