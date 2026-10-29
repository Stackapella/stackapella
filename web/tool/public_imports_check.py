"""The public site's pages have no import map, so a module they load must never reach a bare import
(`vue`, or any name that isn't a path): the browser refuses it ("Module name 'vue' does not resolve
to a valid URL") and the whole module fails, the landing's mixer with it. This walks every module
web/js/public/ and web/js/embed/ start from, through static imports and dynamic import()s with a literal path, and
fails on any bare name it reaches, saying the chain. A dynamic import() on a line marked app-only is
one a public page never runs (an instrument part, say), so it isn't followed.

    python3 -I web/tool/public_imports_check.py
"""
import re
import sys
from pathlib import Path

WEB = Path(__file__).resolve().parents[1]
IMPORT = re.compile(r"""(?:^\s*import\s+(?:[^'";]*?\s+from\s+)?|^\s*export\s+[^'";]*?\s+from\s+|\bimport\s*\(\s*)['"]([^'"]+)['"]""", re.M)
COMMENT = re.compile(r"/\*.*?\*/|(?<![:'\"])//[^\n]*", re.S)


def specifiers(path):
    lines = [line for line in path.read_text().split("\n") if "app-only" not in line]
    return IMPORT.findall(COMMENT.sub("", "\n".join(lines)))


def walk(entry):
    """Every bare name reachable from `entry`, with the chain that reaches it."""
    found, seen, stack = [], set(), [(entry, [entry])]
    while stack:
        path, chain = stack.pop()
        if path in seen:
            continue
        seen.add(path)
        for spec in specifiers(path):
            if spec.startswith(("./", "../", "/")):
                target = (WEB / spec.lstrip("/")) if spec.startswith("/") else (path.parent / spec).resolve()
                if target.exists():
                    stack.append((target, chain + [target]))
            elif not spec.startswith(("http:", "https:", "data:")):
                found.append((spec, chain))
    return found


def main():
    problems = []
    # The public pages' modules, and the app pieces they embed (js/embed: the Sing lane on the landing).
    for entry in sorted([*(WEB / "js" / "public").glob("*.js"), *(WEB / "js" / "embed").glob("*.js")]):
        for spec, chain in walk(entry):
            problems.append(f"{entry.relative_to(WEB)} reaches '{spec}': " + " -> ".join(str(p.relative_to(WEB)) for p in chain))
    for problem in problems:
        print(problem)
    print(f"public imports: {len(problems)} problems")
    sys.exit(1 if problems else 0)


if __name__ == "__main__":
    main()
