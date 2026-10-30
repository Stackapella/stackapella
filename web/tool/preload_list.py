"""Writes what a first visit's lesson 1 needs (the coordinator's first-load steps, 2026-10-30), from
the import statements themselves:

- js/preload.js's module list: every module js/main.js imports, and everything those import,
  nearest first. A first visit fetches them all at once (modulepreload) instead of finding them one
  import at a time, a round trip each level down.
- locales/lesson1/en.json: only the words those modules use (t(), tk() and sk() keys, as the words
  check finds them), so a first visit's lesson 1 waits for about a quarter of en.json; the rest
  follows when the page is idle, or when a view loaded later needs it (js/i18n.js allWords).

No bundling and no build tool: the files stay as they are. Run by tool/test.sh with --check, which
fails when either is stale.

    python3 -I web/tool/preload_list.py           # rewrite both
    python3 -I web/tool/preload_list.py --check   # fail if either is out of date

LearnView is one of main.js's own imports, so the landing's Start (/learn/<lesson 1>) loads exactly
this graph before the lesson shows; the views loaded with import() (studio, settings and the rest)
aren't in it. Python standard library only.
"""
import json
import re
import sys
from pathlib import Path

WEB = Path(__file__).resolve().parent.parent
ENTRY = WEB / "js" / "main.js"
PRELOAD = WEB / "js" / "preload.js"
ENGLISH = WEB / "locales" / "en.json"
FIRST_WORDS = WEB / "locales" / "lesson1" / "en.json"
# A static import or re-export (`import x from './y.js'`, `import './y.js'`, `export { x } from './y.js'`),
# never import() and never a bare specifier (vue, which preload.js preloads on its own).
STATIC = re.compile(r"""^\s*(?:import|export)\s+(?:[^'"`;()]*?\s+from\s+)?['"](\.{1,2}/[^'"]+|/[^'"]+)['"]""", re.M)
BEGIN, END = "    // preload_list.py: begin", "    // preload_list.py: end"
# The words check's own patterns (tool/i18n_check.py): t('key'), tk('key'), sk('serious.key', 'game.key').
KEY = re.compile(r"""\bt[k]?\(\s*['"]([\w.]+)['"]""")
SKIN_KEYS = re.compile(r"""\bsk\(\s*['"]([\w.]+)['"]\s*,\s*['"]([\w.]+)['"]""")


def imports(path):
    for spec in STATIC.findall(path.read_text()):
        target = (WEB / spec.lstrip("/")) if spec.startswith("/") else (path.parent / spec)
        target = target.resolve()
        if not target.is_file():
            sys.exit(f"{path.relative_to(WEB)} imports {spec}, which isn't there")
        yield target


def graph():
    """Every module main.js reaches by static imports, breadth first (what the browser asks for first, first)."""
    seen, order, level = {ENTRY}, [], [ENTRY]
    while level:
        following = []
        for module in level:
            for target in imports(module):
                if target not in seen:
                    seen.add(target)
                    order.append(target)
                    following.append(target)
        level = following
    return [f"/{p.relative_to(WEB).as_posix()}" for p in order]


def written(modules):
    lines = [BEGIN, "    const MODULES = ["]
    lines += [f"        '{m}'," for m in modules]
    lines += ["    ];", END]
    text = PRELOAD.read_text()
    start, end = text.index(BEGIN), text.index(END) + len(END)
    return text[:start] + "\n".join(lines) + text[end:]


def words(modules):
    """en.json with only the keys these modules use, in en.json's order."""
    used = set()
    for path in [ENTRY, *(WEB / m.lstrip("/") for m in modules)]:
        # Comments don't count, as in the words check.
        source = "\n".join("" if line.strip().startswith(("//", "*", "/*")) else line for line in path.read_text().splitlines())
        used.update(KEY.findall(source))
        used.update(key for pair in SKIN_KEYS.findall(source) for key in pair)

    def keep(node, prefix):
        out = {}
        for name, value in node.items():
            key = f"{prefix}{name}"
            if key in used:
                out[name] = value
            elif isinstance(value, dict) and (inner := keep(value, f"{key}.")):
                out[name] = inner
        return out

    return json.dumps(keep(json.loads(ENGLISH.read_text()), ""), ensure_ascii=False, separators=(",", ":")) + "\n"


def main():
    modules = graph()
    new, lesson_words = written(modules), words(modules)
    count = len(modules)
    if "--check" in sys.argv:
        if new != PRELOAD.read_text():
            sys.exit("FAIL js/preload.js's module list is out of date: run python3 -I web/tool/preload_list.py")
        if not FIRST_WORDS.exists() or lesson_words != FIRST_WORDS.read_text():
            sys.exit("FAIL locales/lesson1/en.json is out of date: run python3 -I web/tool/preload_list.py")
        print(f"preload: {count} modules and their words, up to date")
        return
    PRELOAD.write_text(new)
    FIRST_WORDS.parent.mkdir(exist_ok=True)
    FIRST_WORDS.write_text(lesson_words)
    print(f"preload: wrote {count} modules into js/preload.js and their words into locales/lesson1/en.json ({len(lesson_words) // 1024} KB)")


if __name__ == "__main__":
    main()
