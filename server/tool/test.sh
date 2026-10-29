#!/bin/sh
# Runs the server tests, one run at a time on this machine: several worktrees testing at once slow
# every run (and the machine) to a crawl. Waits its turn, then passes its arguments to artisan test,
# e.g. `sh tool/test.sh --compact`. A lock left by a run that died is taken over.
set -u
cd "$(dirname "$0")/.." || exit 1
LOCK=/tmp/stackapella-tests.lock
waited=
while ! mkdir "$LOCK" 2> /dev/null; do
    pid=$(cat "$LOCK/pid" 2> /dev/null)
    # Take over only a lock whose holder is surely gone: ps can't see it (kill -0 fails inside a
    # sandbox even for a live process) and the lock is over 30 minutes old, longer than a run.
    if [ -n "$pid" ] && ! ps -p "$pid" > /dev/null 2>&1 \
        && [ -n "$(find "$LOCK" -maxdepth 0 -mmin +30 2> /dev/null)" ]; then
        rm -rf "$LOCK"
        continue
    fi
    if [ -z "$waited" ]; then
        echo "Waiting for the test run in $(cat "$LOCK/who" 2> /dev/null || echo 'another worktree')" >&2
        waited=1
    fi
    sleep 10
done
echo $$ > "$LOCK/pid"
pwd > "$LOCK/who"
trap 'rm -rf "$LOCK"' EXIT
trap 'exit 130' INT TERM
php artisan test "$@"
status=$?
exit $status
