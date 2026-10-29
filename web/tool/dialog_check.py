"""Fails on the browser's own dialogs in our JavaScript (docs/decisions.md, "no browser dialogs"):
confirm(, alert( and prompt( calls, with or without window. Method definitions (`confirm() {`) and
other objects' methods (the install event's `prompt.prompt()`) are fine. Use components/confirm.js.
"""
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
CALL = re.compile(r"(?<![\w.$])(?:window\.)?(confirm|alert|prompt)\s*\(")
DEFINITION = re.compile(r"(?:async\s+)?(confirm|alert|prompt)\s*\([^)]*\)\s*\{")

problems = []
for path in sorted((ROOT / "js").rglob("*.js")):
    for number, line in enumerate(path.read_text().splitlines(), 1):
        code = line.split("//")[0]
        for match in CALL.finditer(code):
            if DEFINITION.match(code, match.start()):
                continue
            problems.append(f"{path.relative_to(ROOT)}:{number}: the browser's {match.group(1)}(): use confirmAction() from components/confirm.js")
for problem in problems:
    print(problem)
print(f"dialogs: {len(problems)} problems")
sys.exit(1 if problems else 0)
