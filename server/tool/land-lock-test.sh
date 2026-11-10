#!/bin/sh
# Tests land-lock.sh (first come, first served) on its own lock and queue in a temporary folder, never the real
# ones: (1) while one landing holds the lock, three more queue and run in the order they came, each told its place;
# (2) a ticket left by a waiter that died (its process gone, not refreshed for minutes) is dropped, not waited on.
#   sh server/tool/land-lock-test.sh
set -u
here=$(cd "$(dirname "$0")" && pwd)
dir=$(mktemp -d)
trap 'rm -rf "$dir"' EXIT
export STK_LAND_LOCK="$dir/lock" STK_LAND_QUEUE="$dir/queue" STK_LAND_POLL=0.2
log="$dir/log"
failed=0

# 1. A holder keeps the turn for 2 s; A, B and C come 0.3 s apart and must run A, B, C.
sh "${LANDLOCK_UNDER_TEST:-$here/land-lock.sh}" sh -c "echo holder >> '$log'; sleep 2" &
sleep 0.5
for name in A B C; do
    sh "${LANDLOCK_UNDER_TEST:-$here/land-lock.sh}" sh -c "echo $name >> '$log'; sleep 0.4" 2> "$dir/said-$name" &
    sleep 0.3
done
wait
order=$(tr '\n' ' ' < "$log")
if [ "$order" = "holder A B C " ]; then echo "ok   in the order they came: $order"; else echo "FAIL order: $order"; failed=1; fi
if grep -q 'place 3 in the queue' "$dir/said-C"; then echo "ok   C was told its place (3)"; else echo "FAIL C's words: $(cat "$dir/said-C")"; failed=1; fi
if [ -z "$(ls "$STK_LAND_QUEUE" 2> /dev/null | grep -v '^\.')" ] && [ ! -d "$STK_LAND_LOCK" ]; then echo "ok   no tickets and no lock left"; else echo "FAIL left behind: $(ls -a "$STK_LAND_QUEUE")"; failed=1; fi

# 2. A dead waiter's ticket, first in the queue and three minutes old, doesn't hold up a live one.
: > "$log"
printf '999999\n/nowhere\n' > "$STK_LAND_QUEUE/000000000000-999999"
touch -t "$(date -v-3M +%Y%m%d%H%M.%S)" "$STK_LAND_QUEUE/000000000000-999999"
sh "${LANDLOCK_UNDER_TEST:-$here/land-lock.sh}" sh -c "echo live >> '$log'" 2> /dev/null &
waiter=$!
sleep 3
if [ "$(cat "$log")" = "live" ]; then echo "ok   a dead waiter's stale ticket is dropped"; else echo "FAIL the live waiter is still waiting"; kill "$waiter" 2> /dev/null; failed=1; fi
wait

if [ "$failed" -eq 0 ]; then echo "land-lock: passed"; else echo "land-lock: FAILED"; exit 1; fi
exit "$failed"
