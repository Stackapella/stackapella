"""Each free tool's module graph, for its page to preload (docs/performance.md, 2026-11-03): a tool page's script
(js/public/tools/page.js) can only start once site.css has arrived, and then imports the tool's module, which imports
the rest, one round trip a level. Listing the whole graph in the page's <head> as modulepreload links fetches it beside
the stylesheet instead. Writes js/public/tools/preloads.json, `{ "/js/public/tools/tuner.js": ["/js/public/tools/tuner.js", ...] }` (keyed by the module a page mounts, its data-src), the
static imports only (a dynamic import() is loaded on a tap and stays out). `--check` fails when the file is stale.

It also lists the graph of every module script a public page loads by its own <script type="module" src="/js/...">
(site.js on every page, a tool page's page.js, a course page's demo): the server's PreloadModules middleware names them
in the page's <head>, so a phone fetches each chain at once instead of an import at a time (the modules are no-cache, so
a repeat view revalidates every one; docs/performance.md, "Public modules at once").

    python3 -I web/tool/tool_preloads.py [--check]
"""
import json
import re
import sys
from pathlib import Path

WEB = Path(__file__).resolve().parent.parent
VIEWS = WEB.parent / "server" / "resources" / "views"
ENTRY = re.compile(r'<script type="module" src="(/js/[A-Za-z0-9/_.-]+\.js)"')
TOOLS = WEB / "js" / "public" / "tools"
OUT = TOOLS / "preloads.json"
IMPORT = re.compile(r"""^\s*(?:import|export)\s[^'"]*?\sfrom\s+['"]([^'"]+)['"]|^\s*import\s+['"]([^'"]+)['"]""", re.M)
SKIP = {"page.js", "kit.js", "embed.js"}
EXTRA = [WEB / "js" / "public" / "reference-cards.js"]  # mounted by page.js, but not a tool  # the page's own script, and helpers that aren't tools


def graph(entry):
    seen, todo = [], [entry]
    while todo:
        path = todo.pop(0)
        if path in seen:
            continue
        seen.append(path)
        source = path.read_text()
        source = re.sub(r"/\*.*?\*/", "", source, flags=re.S)
        for a, b in IMPORT.findall(source):
            spec = a or b
            if spec.startswith("."):
                todo.append((path.parent / spec).resolve())
    return ["/" + str(p.relative_to(WEB)) for p in seen]


def build():
    out = {}
    for module in sorted(TOOLS.glob("*.js")):
        if module.name in SKIP:
            continue
        if not re.search(r"^export (?:async )?function mount\w*\(", module.read_text(), re.M):
            continue
        out["/" + str(module.relative_to(WEB))] = graph(module.resolve())
    # The reference pages' cards (server ReferencePagesController), mounted the same way from outside tools/.
    for module in EXTRA:
        out["/" + str(module.relative_to(WEB))] = graph(module.resolve())
    # Every module script a public page loads itself (PreloadModules reads these by the script's src).
    for entry in sorted({m for view in VIEWS.rglob("*.blade.php") for m in ENTRY.findall(view.read_text())}):
        path = (WEB / entry.lstrip("/")).resolve()
        if path.is_file() and entry not in out:
            out[entry] = graph(path)
    return json.dumps(out, indent=2, ensure_ascii=False) + "\n"


def main():
    text = build()
    if "--check" in sys.argv:
        if not OUT.exists() or OUT.read_text() != text:
            print("js/public/tools/preloads.json is stale (an import, or a page's module script, changed): run python3 -I web/tool/tool_preloads.py")
            sys.exit(1)
        print("tool preloads: up to date")
        return
    OUT.write_text(text)
    print(f"wrote {OUT.relative_to(WEB)} ({len(json.loads(text))} modules' graphs)")


if __name__ == "__main__":
    main()
