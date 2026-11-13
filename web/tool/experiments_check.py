"""Every experiment the app's code names is in the registry list (docs/experiments-plan.md, section 2):
course/format/experiments.json, the same list the courses' checker reads for lesson variants. A
name not listed fails, so a test can't be wired to nothing, and one that's ended (taken off the list)
fails while code still calls it, so a losing arm's code is never left behind.

    python3 -I tool/experiments_check.py
"""
import json
import re
import sys
from pathlib import Path

WEB = Path(__file__).resolve().parents[1]
LIST = WEB / "course" / "format" / "experiments.json"
CALL = re.compile(r"\b(?:experiment|keptArm)\(\s*'([a-z0-9-]+)'")


def main():
    listed = set(json.loads(LIST.read_text())["experiments"]) if LIST.exists() else set()
    problems = []
    for path in sorted((WEB / "js").rglob("*.js")):
        for number, line in enumerate(path.read_text().splitlines(), 1):
            for name in CALL.findall(line):
                if name not in listed:
                    problems.append(f"{path.relative_to(WEB)}:{number}: experiment '{name}' isn't in course/format/experiments.json")
    for problem in problems:
        print(problem)
    print(f"experiments: {len(listed)} listed, {len(problems)} problems")
    sys.exit(1 if problems else 0)


if __name__ == "__main__":
    main()
