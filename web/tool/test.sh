#!/usr/bin/env bash
# Runs the web tests (tests/index.html) in muted headless Chrome: the maths, and the audio engine
# over loopback. No npm: Chrome is driven by tool/cdp.py.
set -euo pipefail
cd "$(dirname "$0")/.."

PORT="${PORT:-8197}"
php -S "127.0.0.1:$PORT" tool/serve.php > /dev/null 2>&1 &
SERVER=$!
trap 'kill $SERVER 2> /dev/null || true' EXIT
sleep 0.5

# serve.php's /contract/ must never reach the rest of the repo (.env, .git).
if curl -s --path-as-is "http://127.0.0.1:$PORT/contract/../CLAUDE.md" | grep -q "project rules"; then
  echo "FAIL serve.php serves files outside contract/"; exit 1
fi

python3 -I tool/i18n_check.py
python3 -I tool/offline_check.py
python3 -I tool/api_check.py
python3 -I tool/dialog_check.py
python3 -I tool/component_keys_check.py
python3 -I tool/public_imports_check.py
python3 -I tool/course_check.py

python3 -I tool/cdp.py eval "http://127.0.0.1:$PORT/tests/" "JSON.stringify(window.testReport)" --wait "window.testReport" --timeout 240 \
  | python3 -I -c '
import json, sys
report = json.loads(json.load(sys.stdin))
for r in report["results"]:
    print(("ok   " if r["ok"] else "FAIL ") + r["name"] + ("" if r["ok"] else "\n" + r["error"]))
print("passed" if report["passed"] else "FAILED")
sys.exit(0 if report["passed"] else 1)'
