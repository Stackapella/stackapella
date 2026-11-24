#!/usr/bin/env bash
# Runs the web tests (tests/index.html) in muted headless Chrome: the maths, and the audio engine
# over loopback. No npm: Chrome is driven by tool/cdp.py.
set -euo pipefail
cd "$(dirname "$0")/.."

PORT="${PORT:-8197}"
php -S "127.0.0.1:$PORT" tool/serve.php > /dev/null 2>&1 &
SERVER=$!
disown $SERVER  # its end, at exit, unannounced: the last line stays the verdict
REPORT=$(mktemp "${TMPDIR:-/tmp}/stk-web-report.XXXXXX")
SAID=$(mktemp "${TMPDIR:-/tmp}/stk-web-said.XXXXXX")
trap 'kill $SERVER 2> /dev/null || true; rm -f "$REPORT" "$SAID"' EXIT
sleep 0.5

# serve.php's /contract/ must never reach the rest of the repo (.env, .git).
if curl -s --path-as-is "http://127.0.0.1:$PORT/contract/../LICENSE" | grep -q "ATTRIBUTION ASSURANCE"; then
  echo "FAIL serve.php serves files outside contract/"; exit 1
fi

# theme-boot.js runs before the import map: a module load there (a modulepreload, an import()) makes Firefox ignore
# the map, and the app never starts (2026-11-02). Module preloads belong in js/preload.js, after the map.
if grep -nE "modulepreload|import\(" js/theme-boot.js; then
  echo "FAIL js/theme-boot.js starts a module load before the import map (move it to js/preload.js)"; exit 1
fi
python3 -I tool/i18n_check.py
python3 -I tool/offline_check.py
python3 -I tool/api_check.py
python3 -I tool/dialog_check.py
python3 -I tool/counts_check.py
python3 -I tool/component_keys_check.py
python3 -I tool/template_names_check.py
python3 -I tool/public_imports_check.py
python3 -I tool/app_css.py --check
python3 -I tool/course_check.py
python3 -I tool/experiments_check.py
python3 -I tool/flags_check.py
python3 -I tool/skin_check.py
python3 -I tool/preload_list.py --check
python3 -I tool/tool_preloads.py --check
python3 -I tool/cdn_pins_check.py

# The tests that ask for a file that isn't there on purpose (a language with no words, a guide that won't load) get a
# 404, as production answers; those two are expected, any other page error fails the run.
# When no report comes (Chrome never started, or the tests never finished: a load of 25, 2026-11-17), the last line says
# why in cdp.py's own words, as a caller shows only the run's last lines.
CDP=0
python3 -I tool/cdp.py eval "http://127.0.0.1:$PORT/tests/" "JSON.stringify(window.testReport)" --wait "window.testReport" --timeout 240 \
  --expect "/locales/xx" --expect "/tests/fixtures/missing.m4a" > "$REPORT" 2> "$SAID" || CDP=$?
cat "$SAID" >&2
python3 -I -c '
import json, re, sys
report, said, code = open(sys.argv[1]).read().strip(), open(sys.argv[2]).read().splitlines(), int(sys.argv[3])
report = json.loads(json.loads(report)) if report else None
if not isinstance(report, dict):
    # The error it ended on (the last "Name: message" line of a traceback), else its last words.
    words = [line.strip() for line in said if line.strip()]
    why = next((w for w in reversed(words) if re.match(r"[A-Za-z_][\w.]*(Error|Exception|DidNotStart|Died)\b", w)), words[-1] if words else "it said nothing")
    print(f"FAILED: the web tests gave no report (tool/cdp.py exited {code}: {why})")
    sys.exit(1)
for r in report["results"]:
    print(("ok   " if r["ok"] else "FAIL ") + r["name"] + ("" if r["ok"] else "\n" + r["error"]))
if report["passed"] and code:
    print(f"FAILED: page errors (above; tool/cdp.py exited {code})")
    sys.exit(1)
print("passed" if report["passed"] else "FAILED")
sys.exit(0 if report["passed"] else 1)' "$REPORT" "$SAID" "$CDP"
