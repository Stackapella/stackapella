#!/usr/bin/env bash
# Runs the flow scripts (tests/flows/*.py): whole journeys through the app in muted headless Chrome,
# at phone and desktop size, against the mock API. Screenshots land in tests/flows/shots/.
#
#   tool/flows.sh                       # every flow
#   tool/flows.sh record_interrupted    # one (or several) by name
#   API=http://127.0.0.1:8000 tool/flows.sh   # against the real server (the integration check)
set -euo pipefail
cd "$(dirname "$0")/.."

PORT="${PORT:-8198}"
API="${API:-mock}" php -S "127.0.0.1:$PORT" tool/serve.php > /dev/null 2>&1 &
SERVER=$!
trap 'kill $SERVER 2> /dev/null || true' EXIT
sleep 0.5

if [[ $# -gt 0 ]]; then
  flows=()
  for name in "$@"; do flows+=("tests/flows/$name.py"); done
else
  flows=(tests/flows/[a-z]*.py)
fi
rm -rf tests/flows/shots
status=0
for flow in "${flows[@]}"; do
  [[ "$(basename "$flow")" == "flow.py" ]] && continue
  # *_integration flows need the real server (API=http://127.0.0.1:8000).
  if [[ "$flow" == *_integration.py && "${API:-mock}" == "mock" && $# -eq 0 ]]; then
    echo "skip $(basename "$flow" .py) (needs API=http://127.0.0.1:8000)"; continue
  fi
  # A hard limit per flow (FLOW_SECONDS, 20 minutes by default; the coordinator, 2026-11-09: results hung 2.5 hours on
  # a stalled audio clock and held the land lock): past it the flow is sent TERM, which closes its browsers, and fails.
  python3 -I "$flow" "http://127.0.0.1:$PORT" &
  flow_pid=$!
  # (set -e: every step of the watchdog's own end may fail harmlessly, so none of them may end the run.)
  ( sleep "${FLOW_SECONDS:-1200}" && kill -TERM "$flow_pid" 2> /dev/null && echo "FAIL $(basename "$flow" .py): stopped after ${FLOW_SECONDS:-1200} s" ) 2> /dev/null &
  watchdog=$!
  wait "$flow_pid" || status=1
  pkill -P "$watchdog" 2> /dev/null || true
  wait "$watchdog" 2> /dev/null || true
done
[[ $status -eq 0 ]] && echo "flows passed" || echo "FLOWS FAILED"
exit $status
