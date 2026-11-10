#!/bin/sh
# Runs an agent's whole landing (rebase on main, its tests, the leak check, the fast-forward into
# Hillel's checkout) as one turn on this machine, so landings stop racing each other for main.
# Waits for the turn, then runs its arguments; a lock left by a landing that died is taken over.
#   sh server/tool/land-lock.sh sh -c 'git rebase main && PORT=8332 web/tool/test.sh && sh server/tool/leak-check.sh && git -C ~/Code/stackapella merge --ff-only agent/x'
# Keep the turn short: run the long checks before taking it, then only the final rebase, the quick
# re-check and the merge inside it.
#
# First come, first served (the coordinator, 2026-11-08: polling mkdir let whoever polled first win, and a deploy
# waited over an hour behind back-to-back landings): a waiter takes a numbered ticket in the queue folder and takes
# the lock only when its ticket is the oldest. A live waiter refreshes its ticket on every poll; a ticket whose
# process ps can't see and that hasn't been refreshed for two minutes is a waiter that died, and is dropped. A
# script still on the old way (no ticket) can take a free lock between two waiters; that's all it can do.
# STK_LAND_LOCK, STK_LAND_QUEUE and STK_LAND_POLL (seconds) are for the test only (tool/land-lock-test.sh).
set -u
LOCK=${STK_LAND_LOCK:-/tmp/stackapella-land.lock}
QUEUE=${STK_LAND_QUEUE:-/tmp/stackapella-land.queue}
POLL=${STK_LAND_POLL:-5}
mkdir -p "$QUEUE"

# The next number, under a mkdir mutex (one left by a waiter killed inside it goes after a minute).
while ! mkdir "$QUEUE/.mutex" 2> /dev/null; do
    if [ -n "$(find "$QUEUE/.mutex" -maxdepth 0 -mmin +1 2> /dev/null)" ]; then
        rmdir "$QUEUE/.mutex" 2> /dev/null
        continue
    fi
    sleep 1
done
number=$(( $(cat "$QUEUE/.next" 2> /dev/null || echo 0) + 1 ))
echo "$number" > "$QUEUE/.next"
TICKET="$QUEUE/$(printf '%012d' "$number")-$$"
{ echo $$; pwd; } > "$TICKET"
rmdir "$QUEUE/.mutex"
trap 'rm -f "$TICKET"' EXIT
trap 'exit 130' INT TERM

# Drops tickets of waiters that died: ps can't see the process (kill -0 fails in a sandbox even for a live one) and
# the ticket hasn't been refreshed for two minutes (a live waiter refreshes it every poll).
prune() {
    for ticket in "$QUEUE"/[0-9]*; do
        [ -f "$ticket" ] || continue
        [ "$ticket" = "$TICKET" ] && continue
        pid=$(head -n 1 "$ticket" 2> /dev/null)
        if [ -n "$pid" ] && ! ps -p "$pid" > /dev/null 2>&1 \
            && [ -n "$(find "$ticket" -maxdepth 0 -mmin +2 2> /dev/null)" ]; then
            rm -f "$ticket"
        fi
    done
}

said=
while :; do
    touch "$TICKET"
    prune
    # Our place: 1 when ours is the oldest ticket.
    place=1
    for ticket in "$QUEUE"/[0-9]*; do
        [ -f "$ticket" ] || continue
        [ "$ticket" = "$TICKET" ] && break
        place=$((place + 1))
    done
    if [ "$place" -eq 1 ] && mkdir "$LOCK" 2> /dev/null; then
        break
    fi
    if [ "$place" -eq 1 ] && [ -d "$LOCK" ]; then
        pid=$(cat "$LOCK/pid" 2> /dev/null)
        # Take over only a lock whose holder is surely gone: ps can't see it (kill -0 fails inside a
        # sandbox even for a live process, which let two landings overlap) and the lock is over 20 minutes
        # old, longer than any landing's turn should be.
        if [ -n "$pid" ] && ! ps -p "$pid" > /dev/null 2>&1 \
            && [ -n "$(find "$LOCK" -maxdepth 0 -mmin +20 2> /dev/null)" ]; then
            rm -rf "$LOCK"
            continue
        fi
    fi
    if [ "$said" != "$place" ]; then
        echo "Waiting for the landing in $(cat "$LOCK/who" 2> /dev/null || echo 'another worktree') (place $place in the queue)" >&2
        said=$place
    fi
    sleep "$POLL"
done
echo $$ > "$LOCK/pid"
pwd > "$LOCK/who"
rm -f "$TICKET"
trap 'rm -rf "$LOCK"' EXIT
"$@"
