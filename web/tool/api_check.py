"""Checks that web/js/api.js is the only module that calls /api (run by tool/test.sh), as the
contract requires (docs/parallel-agents-plan.md, "Testing the contract from both sides"). The public
pages' scripts, which can't load api.js, may call a contract path api.js lists."""
import re
import sys
from pathlib import Path

WEB = Path(__file__).resolve().parent.parent
problems = []
# The public pages (js/public) have no import map, so they can't load api.js: they may call a path
# api.js's operations list has (the contract's), and nothing else.
KNOWN = set(re.findall(r"""\[\s*'[A-Z]+',\s*'(/api/[^']+)'\s*\]""", (WEB / "js" / "api.js").read_text()))
for path in sorted((WEB / "js").rglob("*.js")):
    if path.name in ("api.js", "api-mock.js"):
        continue
    public = path.parent.name == "public"
    for number, line in enumerate(path.read_text().splitlines(), 1):
        if not re.search(r"""['"`]/api/""", line) or line.strip().startswith(("//", "*")):
            continue
        if public and all(p in KNOWN for p in re.findall(r"""['"`](/api/[^'"`?]+)""", line)):
            continue
        problems.append(f"{path.relative_to(WEB)}:{number}: calls /api directly; add an operation to api.js" + (" (a public page may call only an api.js operation's path)" if public else ""))
for problem in problems:
    print(problem)
sys.exit(1 if problems else 0)
