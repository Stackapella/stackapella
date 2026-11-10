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

# The tests that ask for a file that isn't there on purpose (a language with no words, a guide that won't load) get a
# 404, as production answers; those two are expected, any other page error fails the run.
python3 -I tool/cdp.py eval "http://127.0.0.1:$PORT/tests/" "JSON.stringify(window.testReport)" --wait "window.testReport" --timeout 240 \
  --expect "/locales/xx" --expect "/tests/fixtures/missing.m4a" \
  | python3 -I -c '
import json, sys
report = json.loads(json.load(sys.stdin))
for r in report["results"]:
    print(("ok   " if r["ok"] else "FAIL ") + r["name"] + ("" if r["ok"] else "\n" + r["error"]))
print("passed" if report["passed"] else "FAILED")
sys.exit(0 if report["passed"] else 1)'
