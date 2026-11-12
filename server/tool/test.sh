#!/bin/sh
# Runs the server tests, one run at a time on this machine: several worktrees testing at once slow
# every run (and the machine) to a crawl. Waits its turn, then passes its arguments to artisan test,
# e.g. `sh tool/test.sh --compact`. A lock left by a run that died is taken over. A whole run is parallel (below).
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
# A whole run is split over STK_TEST_PROCESSES workers (paratest, Hillel's yes of 2026-11-12; 4 by default, each with
# its own database: tests/TestDatabase.php). STK_TEST_PROCESSES=1 runs it serially, and so does a --filter run, where
# starting the workers costs more than it saves.
procs=${STK_TEST_PROCESSES:-4}
case " $* " in
    *" --filter"*|*" --parallel"*) procs=1 ;;
esac
# A worktree whose vendor/ predates paratest runs serially (and says so) until `composer install`.
if [ "$procs" -gt 1 ] 2> /dev/null && [ ! -d vendor/brianium/paratest ]; then
    echo "tool/test.sh: paratest isn't installed here (run composer install): running serially." >&2
    procs=1
fi
if [ "$procs" -gt 1 ] 2> /dev/null; then
    set -- --parallel --processes="$procs" "$@"
fi
php artisan test "$@"
status=$?
exit $status
