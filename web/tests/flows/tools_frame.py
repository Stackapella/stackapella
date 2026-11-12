"""The free tools' brand row, as a visitor sees it (docs/tools-plan.md, "Tool page frame"; Hillel: "make sure it (and
all tools) have our logo and theme picker at the top"). On the hub and every listed tool, in a browser tab and
installed (the standalone flag theme-boot.js sets), the first thing on the page is the row: the mark (not its Christmas hat, hidden out of season), "Stackapella"
and the Theme button, all visible at the very top; the logo leads to the landing; and the Theme button opens the Theme menu.

It runs against the server's own site, like site_integration:

    python3 -I tests/flows/tools_frame.py http://127.0.0.1:8412
"""
import json
import sys
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
import flow  # noqa: E402

SITE = sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:8412"

ROW = """(() => {
  const row = document.querySelector('body > header.s-top');
  const bar = document.querySelector('body > header.s-top + [data-tool-bar]');
  const seen = (el) => { if (!el) return false; const r = el.getBoundingClientRect(); const s = getComputedStyle(el); return r.width > 0 && r.height > 0 && s.visibility !== 'hidden' && s.display !== 'none' && r.top < innerHeight; };
  const first = [...document.body.children].find((el) => el.tagName !== 'SCRIPT');
  const name = [...(row?.querySelectorAll('.s-brand span') ?? [])].find((s) => s.textContent.trim() === 'Stackapella');
  return JSON.stringify({ first: first === row, top: row ? Math.round(row.getBoundingClientRect().top) : null,
    mark: seen(row?.querySelector('.s-brand svg:not(.s-hat)')), name: seen(name), theme: seen(bar?.querySelector('[data-theme-menu-button]')),
    home: row?.querySelector('.s-brand')?.getAttribute('href') ?? null });
})()"""


def pages():
    """The hub and every tool it lists (the hub's own links, so the flow follows whatever is live)."""
    try:
        with urllib.request.urlopen(SITE + "/tools") as answer:
            html = answer.read().decode()
            landed = urllib.parse.urlparse(answer.geturl()).path
    except urllib.error.HTTPError as error:
        if error.code == 404:
            return []  # no tool listed yet: no hub, nothing to check
        raise
    if landed != "/tools":
        return [landed]  # the hub isn't live yet: /tools leads to the first tool
    tools = sorted({part.split('"')[0] for part in html.split('href="/tools/')[1:] if part.split('"')[0] and "/" not in part.split('"')[0]})
    return ["/tools", *[f"/tools/{tool}" for tool in tools]]


def main():
    failures = []
    paths = pages()
    if not paths:
        print("skip tools_frame (no tool is listed yet)")
        return
    for size in ("phone", "desktop"):
        for installed in (False, True):
            label = f"{size}{' installed' if installed else ''}"
            f = flow.Flow(SITE, size, "tools_frame", skin="serious")
            try:
                if installed:
                    f.chrome.send("Page.addScriptToEvaluateOnNewDocument", {"source": "Object.defineProperty(navigator, 'standalone', { value: true })"})
                for path in paths:
                    f.go(path, "document.readyState === 'complete'")
                    if installed:
                        assert f.js("document.documentElement.hasAttribute('data-standalone')"), f"{label} {path}: theme-boot didn't mark it installed"
                    seen = json.loads(f.js(ROW))
                    assert seen["first"], f"{label} {path}: the brand row isn't the first thing on the page"
                    assert seen["top"] is not None and seen["top"] <= 1, f"{label} {path}: the site header starts at {seen['top']}px, not the top"
                    for part in ("mark", "name", "theme"):
                        assert seen[part], f"{label} {path}: the row's {part} isn't showing"
                    # The logo leads to the landing, counted (Hillel: "if a user clicks the logo at the top of a tool it
                    # should take them to the main landing page").
                    ref = "tools" if path == "/tools" else "tool-" + path.split("/")[-1]
                    assert seen["home"] == f"/?ref={ref}", f"{label} {path}: the logo leads to {seen['home']}, not the landing"
                    f.run("document.querySelector('[data-tool-bar] [data-theme-menu-button]').click()")
                    f.wait("!document.querySelector('[data-theme-menu]').hidden", timeout=5, what=f"{label} {path}: the Theme menu")
                    f.run("document.querySelector('[data-tool-bar] [data-theme-menu-button]').click()")
                print(f"ok   tools_frame ({label}, {len(paths)} pages)")
            except Exception as error:  # noqa: BLE001 (every size and view is tried)
                failures.append(f"{label}: {error}")
                print(f"FAIL tools_frame ({label}): {error}")
            finally:
                f.close()
    if failures:
        sys.exit(1)


if __name__ == "__main__":
    main()
