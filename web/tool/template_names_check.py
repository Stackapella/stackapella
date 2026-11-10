"""A template calls only what its component gives it (Sentry, 2026-11-10: the course wall's sign-in link called
navigate, which components/course-wall.js imported and used in its methods but never gave its template, so the link
threw "navigate is not a function" and did nothing). For every component in web/js (an object with a `template:`), each
name its module imports that the template calls (`name(`) must reach the template: returned from `setup`, a method
(in `methods`, as `name,` or `name()`), a computed or a prop, or one of the app's template globals (main.js
globalProperties). Fails naming the file, the component and the name.

    python3 -I web/tool/template_names_check.py
"""
import re
import sys
from pathlib import Path

WEB = Path(__file__).resolve().parents[1]
# main.js: app.config.globalProperties, which every template has.
GLOBALS = {"t", "formatNumber", "formatDate", "timeAgo", "paint", "paintText", "paintInk"}
IMPORT = re.compile(r"^\s*import\s+\{([^}]*)\}\s+from\s+['\"][^'\"]+['\"]", re.M)
TEMPLATE = re.compile(r"\btemplate:\s*`")


def imported(source):
    """The names a module imports by name (`import { a, b as c } from`), as used in it."""
    names = set()
    for group in IMPORT.findall(source):
        for part in group.split(","):
            part = part.strip()
            if part:
                names.add(part.split(" as ")[-1].strip())
    return names


def template_end(source, start):
    """The end of the template literal opened just before `start` (its ${...} skipped)."""
    i, depth = start, 0
    while i < len(source):
        ch = source[i]
        if ch == "\\":
            i += 2
            continue
        if depth == 0 and ch == "`":
            return i
        if source.startswith("${", i):
            depth += 1
            i += 2
            continue
        if depth and ch == "}":
            depth -= 1
        i += 1
    return len(source)


def enclosing_object(source, at):
    """The object literal around position `at`: the nearest unmatched `{` before it, and its matching `}`."""
    depth, i = 0, at
    while i > 0:
        i -= 1
        ch = source[i]
        if ch == "}":
            depth += 1
        elif ch == "{":
            if depth == 0:
                break
            depth -= 1
    start, depth, j = i, 0, i
    while j < len(source):
        if source[j] == "`":  # a template literal: skip it whole
            j = template_end(source, j + 1) + 1
            continue
        if source[j] == "{":
            depth += 1
        elif source[j] == "}":
            depth -= 1
            if depth == 0:
                return start, j + 1
        j += 1
    return start, len(source)


def exposed(name, component):
    """Whether the component gives its template `name`: a key or shorthand in setup's object or methods/computed/props,
    a method defined with that name, or a data or prop of that name."""
    key = re.compile(r"(?:[{,]\s*)" + re.escape(name) + r"\s*(?:[,}:]|\(\))", re.M)
    method = re.compile(r"^\s*(?:async\s+)?" + re.escape(name) + r"\s*\([^)]*\)\s*\{", re.M)  # a definition, not a call in a method
    return bool(key.search(component) or method.search(component))


def problems_in(path):
    source = path.read_text()
    names = imported(source) - GLOBALS
    if not names:
        return []
    found = []
    for match in TEMPLATE.finditer(source):
        body_start = match.end()
        body_end = template_end(source, body_start)
        template = source[body_start:body_end]
        start, end = enclosing_object(source, match.start())
        component = source[start:match.start()] + source[body_end + 1:end]
        component = re.sub(r"(?<![:'\"])//[^\n]*", "", component)  # comments out, so `game, // ...` then `sk,` reads as a list
        for name in sorted(names):
            if re.search(r"(?<![\w.$])" + re.escape(name) + r"\s*\(", template) and not exposed(name, component):
                line = source.count("\n", 0, match.start()) + 1
                found.append(f"{path.relative_to(WEB)}:{line}: the template calls {name}(), which the component doesn't give it (return it from setup, or add it to methods)")
    return found


def main():
    problems = []
    for path in sorted((WEB / "js").rglob("*.js")):
        if "/public/" in str(path) or "/embed/" in str(path):
            continue  # framework-free pages: no Vue templates
        problems += problems_in(path)
    for problem in problems:
        print(problem)
    print(f"template names: {len(problems)} problems")
    sys.exit(1 if problems else 0)


if __name__ == "__main__":
    main()
