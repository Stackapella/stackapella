"""Game mode's own sheet (docs/css-split-plan.md, step 3; tool/app_css.py game_source) changes nothing anyone sees: each
of css/app.css and css/site.css less its game skin's rules, with css/app-game.css or css/site-game.css right after it in
game mode, looks exactly as the whole sheet did. On every public page site_css_exact visits and on the app's screens,
in both skins, at a phone's and a desktop's width, each element's computed style and box with the served sheets is
compared with the same page given the whole build (built here from css/input.css, as tool/tailwind.sh built it before
step 3) in the served sheet's place. Not one element may differ. And serious mode never fetches a game sheet, while game
mode links its own right after the page's sheet, before the first paint.

    python3 -I tests/flows/game_css_exact.py http://127.0.0.1:8000 http://127.0.0.1:8441   # the site, then the mock app
"""
import re
import subprocess
import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from course_css_exact import PROPS  # noqa: E402
from flow import Flow  # noqa: E402
from site_css_exact import EXTRA  # noqa: E402
from site_integration import PAGES  # noqa: E402

WEB = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(WEB / "tool"))
import app_css  # noqa: E402

APP_SCREENS = ["/", "/settings", "/learn", "/learn/1-comfortable", "/plus", "/groups", "/voice", "/storage", "/create", "/t/amazing-grace",
               "/workout", "/styleguide/game", "{studio}", "{studio}/sing"]
LOOKS = (("serious", ("warm", "light")), ("game", ("stage", "dark")), ("game", ("warm", "light")))
# course_css_exact's snapshot, over the whole page (the app's header and dialogs too), not just main.
SNAP = """(() => { for (const a of document.getAnimations()) a.cancel();
    if (!document.getElementById('cv-all')) document.head.append(Object.assign(document.createElement('style'), { id: 'cv-all', textContent: '*{content-visibility:visible!important}' }));
    document.body.getBoundingClientRect();
    return [...document.querySelectorAll('body *:not(script):not(style)')].map((e) => { const c = getComputedStyle(e);
    const r = e.getBoundingClientRect(); const box = [r.left, r.top + scrollY, r.width, r.height].map((n) => Math.round(n)).join(',');
    return [e.tagName + '.' + [...e.classList].join('.'), PROPS.map((p) => c.getPropertyValue(p)).join('|') + '|' + box]; }); })()""".replace("PROPS", repr(PROPS))


def build_whole():
    """css/.app-whole.css and css/.site-whole.css: app.css and site.css as tailwind.sh built them before step 3. And the
    served pair as one sheet, css/.app-split.css and css/.site-split.css (app.css then app-game.css): the same cascade as
    the two links one after the other, since the game sheet has no @layer, @import or @charset of its own, so each side
    is one sheet swapped for another (the landing's cabinet sizes its canvas from the layout it saw last, so a swap of
    two sheets one by one would leave it a few pixels apart for no difference in any rule)."""
    for name in ("app", "site"):
        game = (WEB / f"css/{name}-game.css").read_text(encoding="utf-8")
        if re.search(r"@(layer|import|charset)\b", game):
            raise SystemExit(f"game css: css/{name}-game.css has an @layer, @import or @charset, so it can't be read as one with css/{name}.css")
        (WEB / f"css/.{name}-split.css").write_text((WEB / f"css/{name}.css").read_text(encoding="utf-8") + "\n" + game, encoding="utf-8")
    version = re.search(r"^VERSION=(\S+)", (WEB / "tool/tailwind.sh").read_text(), re.M).group(1)
    text = (WEB / "css/input.css").read_text(encoding="utf-8")
    for name, source in (("app", app_css.app_source(text)), ("site", app_css.site_source(text))):
        src = WEB / f"css/.{name}-whole-input.css"
        src.write_text(source, encoding="utf-8")
        try:
            subprocess.run([str(WEB / f"bin/tailwindcss-{version}"), "-i", str(src), "-o", str(WEB / f"css/.{name}-whole.css"), "--minify"], check=True, capture_output=True)
        finally:
            src.unlink()


def swap(base, whole):
    """The page's sheet and its game sheet out, the whole build in the page sheet's place."""
    return f"""const old = [...document.querySelectorAll('link[rel=stylesheet]')].find((l) => (l.getAttribute('href') ?? '').split('?')[0] === {base!r});
    await new Promise((done) => {{ const l = Object.assign(document.createElement('link'), {{ rel: 'stylesheet', href: {whole!r} }}); l.onload = done; l.onerror = done; old.after(l); }});
    old.remove(); document.querySelector('link[data-game-css]')?.remove();"""


# What the page fetched and linked: the game sheets it asked for (any way), and the order of its stylesheet links.
FETCHED = "performance.getEntriesByType('resource').map((r) => new URL(r.name).pathname).filter((p) => /-game\\.css$/.test(p))"
LINKS = "[...document.querySelectorAll('link[rel=stylesheet]')].map((l) => new URL(l.href).pathname + (l.dataset.gameCss !== undefined ? '#game' : ''))"


def compare(f, path, base, whole, skin, width, failed):
    links = f.js(LINKS)
    fetched = f.js(FETCHED)
    if skin == "serious":
        if fetched and path != "/styleguide/game":  # the one page that shows the game skin whatever the skin is
            failed.append(f"serious {width} {path}: fetched {fetched}")
    else:
        mine = base.replace(".css", "-game.css")
        at = links.index(base) if base in links else -1
        if at < 0 or at + 1 >= len(links) or links[at + 1] != mine + "#game":
            failed.append(f"game {width} {path}: the game sheet isn't right after {base}: {links}")
    # With its game sheet (game mode, or the game styleguide in either): the served pair as one sheet (build_whole); without
    # (serious): the served sheet alone, swapped for itself.
    paired = f.js("!!document.querySelector('link[data-game-css]')")
    mine = "/css/." + base.split("/")[-1].replace(".css", "-split.css") if paired else base + "?again=1"
    f.run(swap(base, mine))
    time.sleep(0.4)
    own = f.js(SNAP)
    f.run(swap(mine.split("?")[0], whole))
    time.sleep(0.4)
    full = f.js(SNAP)
    if len(own) != len(full):
        failed.append(f"{skin} {width} {path}: {len(own)} elements with the split sheets, {len(full)} with the whole")
    for (tag, a), (_, b) in zip(own, full):
        if a != b:
            changed = [f"{([*PROPS, 'box'])[i]} {x} (whole: {y})" for i, (x, y) in enumerate(zip(a.split("|"), b.split("|"))) if x != y]
            failed.append(f"{skin} {width} {path} {tag[:60]}: {', '.join(changed[:3])}")


def look(f, skin, colour, mode):
    f.run(f"window.stackapellaTheme.choose({{ colour: '{colour}', mode: '{mode}', skin: '{skin}' }})")
    time.sleep(0.3)


def main():
    site, app = sys.argv[1].rstrip("/"), sys.argv[2].rstrip("/")
    build_whole()
    failed, checked = [], 0
    try:
        for skin, (colour, mode) in LOOKS:
            for size, width in (("phone", 390), ("desktop", 1440)):
                # The public pages.
                f = Flow(site, size, "game-css", skin)
                try:
                    f.chrome.send("Emulation.setDeviceMetricsOverride", {"width": width, "height": 900, "deviceScaleFactor": 1, "mobile": width < 768})
                    f.go("/", "document.querySelector('body')")
                    look(f, skin, colour, mode)
                    for path in dict.fromkeys(PAGES + EXTRA):
                        f.go(path, "document.querySelector('body')")  # loaded in this look from the first paint
                        time.sleep(1.5)
                        if not f.js("[...document.querySelectorAll('link[rel=stylesheet]')].some((l) => new URL(l.href).pathname === '/css/site.css')"):
                            continue
                        compare(f, path, "/css/site.css", "/css/.site-whole.css", skin, width, failed)
                        checked += 1
                finally:
                    f.chrome.close()
                # The app's screens.
                f = Flow(app, size, "game-css", skin)
                try:
                    f.chrome.send("Emulation.setDeviceMetricsOverride", {"width": width, "height": 900, "deviceScaleFactor": 1, "mobile": width < 768})
                    f.go("/?api=mock&as=admin", "document.querySelector('#app header')")
                    look(f, skin, colour, mode)
                    studio = f.js("(async () => { const { startFromTemplate } = await import('/js/stores/projects.js'); const { loadTemplate } = await import('/js/templates.js'); return (await startFromTemplate(await loadTemplate('amazing-grace'), { singers: 4 })).id; })()")
                    for screen in APP_SCREENS:
                        path = screen.format(studio=f"/p/{studio}")
                        f.go(path, "document.querySelector('#app header, #app h1, #app main')")
                        time.sleep(1.5)
                        compare(f, path, "/css/app.css", "/css/.app-whole.css", skin, width, failed)
                        checked += 1
                finally:
                    f.chrome.close()
    finally:
        for name in ("app", "site"):
            (WEB / f"css/.{name}-whole.css").unlink(missing_ok=True)
            (WEB / f"css/.{name}-split.css").unlink(missing_ok=True)
    if failed:
        print(f"game css: with the split sheets these differ from the whole build, or the sheets are linked wrong ({len(failed)} in all):")
        pages = {}
        for line in failed:
            key = " ".join(line.split(" ")[:3]).rstrip(":")
            pages[key] = pages.get(key, 0) + 1
        print("By page: " + "; ".join(f"{k} ({n})" for k, n in pages.items()))
        print("\n".join(failed[:40]))
        raise SystemExit(1)
    print(f"game css: {checked} page views (the site's and the app's), serious and game in dark and light, 390 and 1440: every element as with the whole build; serious fetched no game sheet, game linked its own right after the page's")


if __name__ == "__main__":
    main()
