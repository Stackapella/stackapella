"""Writes what a first visit's lesson 1 needs (the coordinator's first-load steps, 2026-10-30), from
the import statements themselves:

- js/preload.js's module list: every module js/main.js imports, and everything those import,
  nearest first. A first visit fetches them all at once (modulepreload) instead of finding them one
  import at a time, a round trip each level down.
- js/preload.js's game list: game mode's own modules (js/game/modules.js's import()s) and everything
  they import that the list above hasn't, so a game screen's own imports (motion, the daily pick)
  aren't found a round trip late.
- js/preload.js's lesson list: what a lesson draws once begun (js/components/lazy-lesson.js's import()s: the
  exercise, the results, the lesson's end), the song stores (js/lazy-stores.js's) and everything they import
  that main.js's list hasn't. They're off
  lesson 1's first screen, fetched all at once as it's drawn (lazy-lesson.js prefetchLesson).
- locales/lesson1/en.json: only the words the modules of the main and lesson lists use (t(), tk() and sk() keys, as the words
  check finds them), so a first visit's lesson 1 waits for about a quarter of en.json; the rest
  follows when the page is idle, or when a view loaded later needs it (js/i18n.js allWords).

No bundling and no build tool: the files stay as they are. Run by tool/test.sh with --check, which
fails when either is stale.

    python3 -I web/tool/preload_list.py           # rewrite both
    python3 -I web/tool/preload_list.py --check   # fail if either is out of date

LearnView is one of main.js's own imports, so the landing's Start (/learn/<lesson 1>) loads exactly
this graph before the lesson shows; the views loaded with import() (studio, settings and the rest)
aren't in it, nor the lesson's own later parts (the lesson list, above). Python standard library only.
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
GAME_BEGIN, GAME_END = "    // preload_list.py: game begin", "    // preload_list.py: game end"
GAME_LOADERS = WEB / "js" / "game" / "modules.js"
LESSON_BEGIN, LESSON_END = "    // preload_list.py: lesson begin", "    // preload_list.py: lesson end"
HOME_BEGIN, HOME_END = "    // preload_list.py: home begin", "    // preload_list.py: home end"
HOME_VIEW = WEB / "js" / "views" / "projects.js"
LESSON_LOADERS = (WEB / "js" / "components" / "lazy-lesson.js", WEB / "js" / "lazy-stores.js")
SING_STORE = (WEB / "js" / "stores" / "session.js").resolve()
LOADER = re.compile(r"""^\s*(\w+):\s*\(\)\s*=>\s*import\(\s*['"](\.{1,2}/[^'"]+)['"]\s*\)""", re.M)
WITH_SING = re.compile(r"WITH_SING\s*=\s*new Set\(\[([^\]]*)\]\)")
DYNAMIC = re.compile(r"""import\(\s*['"](\.{1,2}/[^'"]+)['"]\s*\)""")
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


def graph(entries=(ENTRY,), known=()):
    """Every module the entries reach by static imports, breadth first (what the browser asks for first, first),
    leaving out those `known` already."""
    seen, order, level = {*entries, *known}, [], list(entries)
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


def game_modules(modules):
    """Game mode's own modules (modules.js's import()s), then what they import that `modules` (main.js's) haven't. A
    game module that needs Sing's session store (part-select, Sing's part chooser) comes with Sing, which loads that
    store anyway: listed here, it brought the store and the studio's pieces back onto a first visit's lesson 1, which
    never shows them (2026-11-08)."""
    source = GAME_LOADERS.read_text()
    named = {name: (GAME_LOADERS.parent / spec).resolve() for name, spec in LOADER.findall(source)}
    own = [(GAME_LOADERS.parent / spec).resolve() for spec in DYNAMIC.findall(source)]
    known = {(WEB / m.lstrip("/")).resolve() for m in modules}
    resolved = lambda paths: {(WEB / p.lstrip("/")).resolve() for p in paths}
    sing = {SING_STORE} | resolved(graph((SING_STORE,), known))
    with_sing = {m for m in own if ({m} | resolved(graph((m,), known))) & sing}
    # modules.js's loadGame imports every loader as game mode comes on, but those it names WITH_SING: one that needs
    # Sing's store and isn't named would bring the store onto lesson 1 anyway, a round trip late.
    listed = set(re.findall(r"'(\w+)'", (WITH_SING.search(source) or [None, ""])[1]))
    for name, path in named.items():
        if path in with_sing and name not in listed:
            sys.exit(f"FAIL js/game/modules.js: {name} needs Sing's session store; add it to WITH_SING so it loads with Sing")
    kept = [m for m in own if m not in with_sing]
    return [f"/{p.relative_to(WEB).as_posix()}" for p in kept] + graph(kept, known)


def lesson_modules(modules):
    """lazy-lesson.js's and lazy-stores.js's import()s, then what they import that `modules` (main.js's) haven't."""
    own = [(loaders.parent / spec).resolve() for loaders in LESSON_LOADERS for spec in DYNAMIC.findall(loaders.read_text())]
    known = {(WEB / m.lstrip("/")).resolve() for m in modules}
    own = [m for m in dict.fromkeys(own) if m not in known]
    return [f"/{p.relative_to(WEB).as_posix()}" for p in own] + graph(own, known)


def home_modules(modules):
    """Home's view (views/projects.js) and what it imports that main.js's and the lesson's lists haven't: a first visit
    that opens the app at "/" asks for them at once instead of after Vue has run (2026-11-12, the speed pass)."""
    # Never one already listed (main.js's, the lesson's or game mode's): console_clean wants each preloaded once.
    known = {(WEB / m.lstrip("/")).resolve() for m in modules + lesson_modules(modules) + game_modules(modules)}
    view = HOME_VIEW.resolve()
    return ([] if view in known else [f"/{view.relative_to(WEB).as_posix()}"]) + graph((view,), known)


def block(text, begin, end, name, modules):
    lines = [begin, f"    const {name} = ["]
    lines += [f"        '{m}'," for m in modules]
    lines += ["    ];", end]
    start, stop = text.index(begin), text.index(end) + len(end)
    return text[:start] + "\n".join(lines) + text[stop:]


def written(modules):
    text = block(PRELOAD.read_text(), BEGIN, END, "MODULES", modules)
    text = block(text, LESSON_BEGIN, LESSON_END, "LESSON", lesson_modules(modules))
    text = block(text, HOME_BEGIN, HOME_END, "HOME", home_modules(modules))
    return block(text, GAME_BEGIN, GAME_END, "GAME", game_modules(modules))


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
    new, lesson_words = written(modules), words(modules + lesson_modules(modules))
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
