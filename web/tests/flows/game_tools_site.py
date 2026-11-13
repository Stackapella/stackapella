"""The free tools' real pages in game mode (docs/tools-plan.md; the game agent's arcade pass on the tools' one layout):
each page is an arcade page (<html data-gs>), its panel framed and its pieces square, the pitch pipe's ring round, the warm-up, song part, reference and lesson pages too, the
segmented words on one line at 360 px, and every page at AA in all eight themes with nothing scrolling sideways, on a
phone (360) and a desktop (1440); serious mode keeps the calm panel. The hub too. Runs against the server's own site:

    python3 -I tests/flows/game_tools_site.py http://127.0.0.1:8422
"""
import subprocess
import sys
import time
import urllib.error
import urllib.request
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
import flow  # noqa: E402
from app_audit import APP_CONTRAST  # noqa: E402
from game_frames import THEMES, pixel_face  # noqa: E402

SITE = sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:8412"
PAGES = ["/tools", "/tools/tuner", "/tools/metronome", "/tools/vocal-range-test", "/tools/pitch-pipe", "/tools/tuning-forks", "/tools/drone", "/warmups", "/warmups/humming-scale", "/songs/amazing-grace/alto", "/intervals/perfect-fifth", "/chords/major", "/voice-types/alto", "/courses/harmony/steady-notes", "/courses/perfect-your-pitch/scoop-or-clean", "/courses/vibrato/swing-and-settle", "/integrations", "/integrations/invoice-ninja", "/songs", "/songs/amazing-grace", "/compare", "/compare/smule", "/courses/other-languages", "/about", "/for/beginners", "/for/organizations", "/guides", "/guides/what-is-harmony", "/whats-new",
         "/songs/christmas-carols", "/songs/hymns", "/songs/spirituals", "/songs/sea-shanties", "/songs/rounds", "/songs/folk-songs", "/songs/barbershop-songs", "/songs/jewish-songs", "/guides/hanukkah-songs-for-choir", "/es/guides/hanukkah-songs-for-choir", "/es/courses", "/es", "/es/songs", "/es/pricing", "/es/tools", "/songs/scottish-songs", "/songs/shaker-songs", "/songs/thanksgiving-hymns", "/songs/villancicos", "/es/songs/villancicos", "/songs/silent-night", "/songs/danny-boy", "/songs/hark-the-herald", "/songs/jingle-bells", "/songs/noche-de-paz", "/songs/maoz-tzur", "/songs/la-paloma", "/songs/shchedryk", "/songs/riu-riu-chiu", "/songs/el-cant-dels-ocells", "/songs/coventry-carol", "/songs/deep-river", "/songs/drunken-sailor"]  # the songbook and collection pages
STYLE = "getComputedStyle(document.querySelector({sel!r}))"
# The site server's own folder (this checkout's), whose database says whether the courses are open: the lesson pages
# exist only for open courses (SitePages::courseOpen), so a run that finds them closed opens them. It never closes them
# again: the dev databases keep them open, as production does (the coordinator, 2026-11-08), so two runs can't race.
SERVER = Path(__file__).resolve().parents[3] / "server"


def courses_flag(value=None):
    """plus_courses in the site server's database: its value now, or set to `value` (app:flag, with a reason)."""
    if value is None:
        out = subprocess.run(["php", "artisan", "tinker", "--execute", "echo app(App\\Support\\Flags::class)->value('plus_courses');"], cwd=SERVER, capture_output=True, text=True, check=True)
        return out.stdout.strip().splitlines()[-1]
    subprocess.run(["php", "artisan", "app:flag", "plus_courses", value, "--reason=game_tools_site: the lesson pages need the courses open, as production has them"], cwd=SERVER, capture_output=True, text=True, check=True)
    return value


def listed(path):
    try:
        with urllib.request.urlopen(SITE + path):
            return True
    except urllib.error.HTTPError as error:
        if error.code == 404:
            return False
        raise


def themes(f, path):
    """Every theme at AA and nothing sideways, then the flow's own look back."""
    low = []
    for colour, mode in THEMES:
        f.run(f"window.stackapellaTheme.choose({{ colour: '{colour}', mode: '{mode}' }})")
        time.sleep(0.35)
        low += [f"{colour} {mode}: {item}" for item in f.chrome.evaluate(APP_CONTRAST)[:2]]
        f.no_sideways(f"{path} in {colour} {mode}")
    f.run("localStorage.removeItem('stackapella.appearance'); document.cookie = 'sa_theme=; path=/; max-age=0'; window.stackapellaTheme.apply()")
    assert not low, f"{path}: under AA: {low[:4]}"


def check(f, path):
    # The page's scripts run first (they draw a tool and wire the Theme menu): a module runs only once the page's sheets
    # are in, and the site's family sheets come through PHP locally, so a set sleep alone can read the page before them.
    f.go(path, "document.readyState === 'complete' && document.querySelector('main')")
    time.sleep(0.6)
    tool = f.js("!!document.querySelector('[data-ui=tool-panel]')")
    if f.skin == "serious":
        if tool:
            assert f.js(f"{STYLE.format(sel='[data-ui=tool-panel]')}.borderTopLeftRadius") != "0px", f"{path}: serious keeps the calm panel"
        return
    assert f.js("document.documentElement.hasAttribute('data-gs')"), f"{path}: an arcade page in game mode"
    # Reading text is never in the pixel face: it goes on headings and labels, not a guide's or a page's paragraphs.
    long = "[...document.querySelectorAll('main p, main li')].filter((e) => e.offsetWidth && e.textContent.trim().length > 120 && getComputedStyle(e).fontFamily.startsWith('\"Stackapella Pixel\"')).map((e) => e.textContent.trim().slice(0, 40))"
    assert not f.js(long), f"{path}: a paragraph in the pixel face: {f.js(long)}"
    # The frame: a tool's key control square (light and dark is the header's switch since 2026-11-13, checked below).
    if f.js("!!document.querySelector('.s-tbrand')"):
        if f.js("!!document.querySelector('[data-tool-key] > *')"):
            assert f.js("getComputedStyle(document.querySelector('[data-tool-key] > *')).borderTopLeftRadius") == "0px", f"{path}: a square key control"
    # The site header's icon buttons (the menu, light and dark) square.
    shown = "[...document.querySelectorAll('.s-top .s-iconbtn')].filter((b) => b.offsetWidth)"
    if f.js(f"{shown}.length"):
        assert f.js(f"{shown}.every((b) => getComputedStyle(b).borderTopLeftRadius === '0px')"), f"{path}: a round icon button in the header"
    # Below the tool: the About panel framed like the stage card, the directory's tiles square.
    if f.js("!!document.querySelector('.s-about-panel')"):
        assert f.js("getComputedStyle(document.querySelector('.s-about-panel')).boxShadow") != "none", f"{path}: the About panel framed"
        assert f.js("getComputedStyle(document.querySelector('.s-about-panel')).borderTopLeftRadius") == "0px", f"{path}: a square About panel"
    if f.js("!!document.querySelector('.s-tdir-grp a')"):
        assert f.js("getComputedStyle(document.querySelector('.s-tdir-grp a')).borderTopLeftRadius") == "0px", f"{path}: square tool tiles"
    if tool:
        assert f.js(f"{STYLE.format(sel='[data-ui=tool-panel]')}.boxShadow") != "none", f"{path}: the panel framed"
        assert f.js(f"{STYLE.format(sel='[data-ui=tool-panel]')}.borderTopLeftRadius") == "0px", f"{path}: a square panel"
        two = "[...document.querySelectorAll('[data-ui=tool] [data-segment]')].filter((b) => b.offsetWidth && !b.querySelector('span + span') && b.offsetHeight > 44).map((b) => b.textContent)"
        assert not f.js(two), f"{path}: a segment's word on two lines: {f.js(two)}"
    if f.js("!!document.querySelector('[data-part-player] [data-ui=part-lane]')"):
        # A song part page's player: the lane square, its ways to play framed.
        f.wait("document.querySelector('[data-part-player] [data-segment]')", what="the part player")
        assert f.js("getComputedStyle(document.querySelector('[data-part-player] [data-ui=part-lane]')).borderTopLeftRadius") == "0px", f"{path}: a square lane"
        assert f.js("getComputedStyle(document.querySelector('[data-part-player] :has(> [data-segment])')).boxShadow") != "none", f"{path}: the ways to play framed"
    if f.js("!!document.querySelector('[data-tool^=ref-]')"):
        # A reference page's card (frontend's reference-cards.js): framed, its links to the family's other pages square in a 2 px edge.
        f.wait("document.querySelector('[data-ui=ref-card]')", what="the reference card")
        assert f.js("getComputedStyle(document.querySelector('[data-ui=ref-card]')).boxShadow") != "none", f"{path}: the reference card framed"
        assert f.js("getComputedStyle(document.querySelector('[data-ui=ref-card]')).borderTopLeftRadius") == "0px", f"{path}: a square reference card"
        assert f.js("getComputedStyle(document.querySelector('.s-tool-cards .s-card')).borderTopWidth") == "2px", f"{path}: the link cards in a 2 px edge"
    if f.js("!!document.querySelector('[data-lesson] p > b')"):
        # A lesson page (docs/seo-lesson-pages-plan.md): the screen face has one weight, so a card's title wears the accent.
        title = f.js("getComputedStyle(document.querySelector('[data-lesson] p > b')).color")
        body = f.js("getComputedStyle(document.querySelector('[data-lesson] p')).color")
        assert title != body, f"{path}: a learn card's title looks like its detail ({title})"
    if f.js("!!document.querySelector('[data-lesson] .s-card')"):
        assert f.js("getComputedStyle(document.querySelector('[data-lesson] .s-card')).borderTopLeftRadius") == "0px", f"{path}: a square fact card"
    if path.endswith("pitch-pipe"):
        assert f.js("getComputedStyle(document.querySelector('[data-pipe-ring]')).borderTopLeftRadius") != "0px", "the pitch pipe's ring stays round"
        assert f.js("getComputedStyle(document.querySelector('.s-pipe-key')).borderTopLeftRadius") != "0px", "the pitch pipe's keys stay round"
    f.shot(path.strip("/").replace("/", "-"))
    pixel_face(f, path)
    themes(f, path)


def legal_plain(size, width, height):
    """The legal pages stay on the plain reading screen in game mode too (docs/decisions.md, 2026-11-10)."""
    f = flow.Flow(SITE, size, "game_tools_site", skin="game")
    try:
        f.chrome.send("Emulation.setDeviceMetricsOverride", {"width": width, "height": height, "deviceScaleFactor": 1, "mobile": size == "phone"})
        f.go("/legal/terms", "document.querySelector('main')")
        assert not f.js("document.documentElement.hasAttribute('data-gs')"), "/legal/terms: on the arcade screen"
        assert f.js("document.body.classList.contains('s-calm')"), "/legal/terms: not calm"
    finally:
        f.close()


HEADER_FIT = """(() => { const top = document.querySelector('.s-top'); const b = top.querySelector(':scope > .btn-primary');
    const edge = top.getBoundingClientRect().right - parseFloat(getComputedStyle(top).paddingRight);
    return { clip: b.scrollWidth - b.clientWidth, over: Math.round(b.getBoundingClientRect().right - edge), sideways: document.documentElement.scrollWidth - document.documentElement.clientWidth }; })()"""


def header_fits(skin):
    """The header's row fits from the nav's breakpoint up, English and Spanish (the game agent's header pass, 2026-11-13: the
    pixel caps ran 31 px into the margin at 900 and Spanish's 114 px at 960): Open the app whole, nothing past the
    row's edge or sideways; a page in another language takes the arcade screen as its English page does."""
    f = flow.Flow(SITE, "desktop", "game_tools_site", skin=skin)
    try:
        for path in ("/songs", "/es/courses"):
            if not listed(path):
                continue
            for width in (900, 960, 1024, 1100, 1152, 1280):
                f.chrome.send("Emulation.setDeviceMetricsOverride", {"width": width, "height": 800, "deviceScaleFactor": 1, "mobile": False})
                f.go(path, "document.readyState === 'complete' && document.querySelector('.s-top')")
                fit = f.js(HEADER_FIT)
                assert fit["clip"] <= 0 and fit["over"] <= 0 and fit["sideways"] <= 0, f"{path} at {width}: the header doesn't fit: {fit}"
            if skin == "game":
                assert f.js("document.documentElement.hasAttribute('data-gs')"), f"{path}: not on the arcade screen"
    finally:
        f.close()


def main():
    if courses_flag() != "on":
        courses_flag("on")
    pages = [p for p in PAGES if listed(p)]
    missing = [p for p in PAGES if p.startswith("/courses/") and p not in pages]
    assert not missing, f"lesson pages not served with the courses open: {missing}"
    failures = []
    for skin in ("game", "serious"):
        for size, width, height in (("phone", 360, 780), ("desktop", 1440, 900)):
            for path in pages:
                def one_page(path=path, skin=skin, size=size, width=width, height=height):
                    f = flow.Flow(SITE, size, "game_tools_site", skin=skin)
                    try:
                        f.chrome.send("Emulation.setDeviceMetricsOverride", {"width": width, "height": height, "deviceScaleFactor": 2 if size == "phone" else 1, "mobile": size == "phone"})
                        check(f, path)
                    except flow.ChromeDied:
                        f.chrome.close()
                        raise
                    except BaseException:
                        try:
                            f.close()
                        except AssertionError:
                            pass  # the page's own failure is the one to report
                        raise
                    f.close()  # page errors fail it too
                try:
                    # A Chrome that dies under a busy machine (2026-11-12) is tried once more on this page.
                    flow.once_more_if_chrome_dies(f"game_tools_site ({path}, {skin}, {size})", one_page)
                    print(f"ok   game_tools_site ({path}, {skin}, {size})")
                except Exception as error:  # noqa: BLE001 (every page, skin and size is tried)
                    failures.append(f"{path} {skin} {size}: {error}")
                    print(f"FAIL game_tools_site ({path}, {skin}, {size}): {error}")
    for skin in ("game", "serious"):
        try:
            header_fits(skin)
            print(f"ok   game_tools_site (header fits, {skin})")
        except Exception as error:  # noqa: BLE001
            failures.append(f"header {skin}: {error}")
            print(f"FAIL game_tools_site (header fits, {skin}): {error}")
    for size, width, height in (("phone", 360, 780), ("desktop", 1440, 900)):
        try:
            legal_plain(size, width, height)
            print(f"ok   game_tools_site (/legal/terms plain, {size})")
        except Exception as error:  # noqa: BLE001
            failures.append(f"/legal/terms {size}: {error}")
            print(f"FAIL game_tools_site (/legal/terms plain, {size}): {error}")
    if failures:
        sys.exit(1)


if __name__ == "__main__":
    main()
