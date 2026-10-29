"""Fails on a Vue component object with the same option twice (two `setup:`, two `watch:`...): the
second silently replaces the first, so whatever the first exposed or did is gone (2026-10-29: the
sign-up screen's reasons, a result's heading words, and Settings' account watch were each lost
this way). Checks every top-level `const X = { ... };` object in web/js.
"""
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
OBJECT = re.compile(r"^(?:export )?const (\w+) = \{\n(.*?)^\};", re.M | re.S)
KEY = re.compile(r"^    (?:async )?(\w+)(?::| ?\()", re.M)

problems = []
for path in sorted((ROOT / "js").rglob("*.js")):
    text = path.read_text(encoding="utf-8")
    for match in OBJECT.finditer(text):
        keys = KEY.findall(match.group(2))
        for key in sorted({k for k in keys if keys.count(k) > 1}):
            line = text[: match.start()].count("\n") + 1
            problems.append(f"{path.relative_to(ROOT)}:{line}: {match.group(1)} has `{key}` twice; the second replaces the first")

for problem in problems:
    print(problem)
print(f"component keys: {len(problems)} problems")
sys.exit(1 if problems else 0)
