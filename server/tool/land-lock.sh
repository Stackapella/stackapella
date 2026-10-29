#!/bin/sh
# Runs an agent's whole landing (rebase on main, its tests, the leak check, the fast-forward into
# Hillel's checkout) as one turn on this machine, so landings stop racing each other for main.
# Waits for the turn, then runs its arguments; a lock left by a landing that died is taken over.
#   sh server/tool/land-lock.sh sh -c 'git rebase main && PORT=8332 web/tool/test.sh && sh server/tool/leak-check.sh && git -C ~/Code/stackapella merge --ff-only agent/x'
# Keep the turn short: run the long checks before taking it, then only the final rebase, the quick
# re-check and the merge inside it.
set -u
LOCK=/tmp/stackapella-land.lock
waited=
while ! mkdir "$LOCK" 2> /dev/null; do
    pid=$(cat "$LOCK/pid" 2> /dev/null)
    # Take over only a lock whose holder is surely gone: ps can't see it (kill -0 fails inside a
    # sandbox even for a live process, which let two landings overlap) and the lock is over 20 minutes
    # old, longer than any landing's turn should be.
    if [ -n "$pid" ] && ! ps -p "$pid" > /dev/null 2>&1 \
        && [ -n "$(find "$LOCK" -maxdepth 0 -mmin +20 2> /dev/null)" ]; then
        rm -rf "$LOCK"
        continue
    fi
    if [ -z "$waited" ]; then
        echo "Waiting for the landing in $(cat "$LOCK/who" 2> /dev/null || echo 'another worktree')" >&2
        waited=1
    fi
    sleep 5
done
echo $$ > "$LOCK/pid"
pwd > "$LOCK/who"
trap 'rm -rf "$LOCK"' EXIT
trap 'exit 130' INT TERM
"$@"
