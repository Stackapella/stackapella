"""The app's logo is the site's (Hillel, 2026-11-08: "make the logo in the top left corner the same size in the marketing
and app pages ... make the app match the marketing size and position"; docs/design-rules.md, "One logo"). On the server's
own site and app, at 320 to 1440 px (each step of the wordmark): the mark's box (30 px, its left inset and its height from the top)
and the wordmark's box, face, weight and size are the same on a site page as on the app's Home and its sign-in page; below
360 px both show the mark alone. Both skins (FLOW_SKINS=game,serious). Runs against the server's own site:

    FLOW_SKINS=game,serious python3 -I tests/flows/logo_match.py http://127.0.0.1:8422
"""
import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

WIDTHS = (320, 360, 375, 390, 414, 768, 1024, 1440, 1520, 1720, 1920)
SITE = "/features"
APP = ("/?app", "/signin")  # ?app: the app's Home, not the landing a first visit gets
LOGO = """(() => {
  const word = document.querySelector('header .s-brand .s-word, #app header [data-ui=wordmark]');
  if (!word) return null;
  const mark = word.previousElementSibling;
  const box = (e) => { const b = e.getBoundingClientRect(); return [Math.round(b.left * 2) / 2, Math.round(b.top * 2) / 2, Math.round(b.width * 2) / 2, Math.round(b.height * 2) / 2]; };
  const cs = getComputedStyle(word);
  const shown = word.getBoundingClientRect().width > 2;
  return { mark: box(mark), word: shown ? box(word) : null, face: cs.fontFamily.split(',')[0], weight: cs.fontWeight, size: cs.fontSize, adjust: cs.fontSizeAdjust };
})()"""


# The app's Home lines up with its bar as the site's page does with its header: the first card's left edge on the mark's,
# the last control's right edge on the card's (from 900 px, where both have the row's 40 px gutters).
EDGES = """(() => {
  const mark = document.querySelector('#app header [data-ui=wordmark]').previousElementSibling.getBoundingClientRect();
  const card = [...document.querySelectorAll('#app header ~ div > *')].find((e) => e.offsetParent).getBoundingClientRect(); // Home's first block: the lesson card's row
  const right = Math.max(...[...document.querySelectorAll('#app header a, #app header button')].filter((e) => e.offsetParent).map((e) => e.getBoundingClientRect().right));
  return { mark: Math.round(mark.left), card: Math.round(card.left), cardRight: Math.round(card.right), controls: Math.round(right) };
})()"""


def edges(f):
    """Home's edges, once its controls are there (they come with the account's answer)."""
    f.wait("[...document.querySelectorAll('#app header a, #app header button')].filter((e) => e.offsetParent).length >= 2", what="the bar's controls")
    return f.js(EDGES)


def logo_at(f, path, width, text=16):
    f.chrome.send("Emulation.setDeviceMetricsOverride", {"width": width, "height": 900, "deviceScaleFactor": 1, "mobile": width < 768})
    f.chrome.send("Page.setFontSizes", {"fontSizes": {"standard": text}})
    f.go(path, "document.querySelector('header .s-brand .s-word, #app header [data-ui=wordmark]')")
    # Game mode: its sheet first (it sets the wordmark's face and size adjust), then a layout so the face it names is
    # asked for, and only then are the fonts done (fonts.status is 'loaded' before the sheet has asked for any).
    f.wait("document.documentElement.dataset.skin !== 'game' || !document.querySelector('meta[name=\"game-css\"]')"
           " || !!document.querySelector('link[data-game-css]')?.sheet", what=f"{path}: the game sheet")
    f.wait("document.body.offsetWidth > 0 && document.fonts.status === 'loaded'", what=f"{path}: fonts")
    time.sleep(0.3)
    return f.js(LOGO)


def journey(f):
    # The app signed out on the real server: its account answers 401, as it should.
    f.expected_errors.append("401 (Unauthorized) " + f.base + "/api/account")
    for width in WIDTHS:
        site = logo_at(f, SITE, width)
        assert site and site["mark"][2] == 30 and site["mark"][3] == 30, f"the site's mark at {width}: {site}"
        assert (site["word"] is None) == (width < 360), f"the site's wordmark at {width}: {site}"
        for path in APP:
            app = logo_at(f, path, width)
            assert app == site, f"{path} at {width} px: the app's logo {app}, the site's {site}"
        f.shot(f"logo-{width}")
    # Chrome's text size set larger (Hillel's: the app's 72rem column grew past its 1200 px bar, so "the whole header is
    # narrower"): the logo stays the site's, and Home's content stays on the bar's row.
    for width in (1440, 1720, 1920):
        site = logo_at(f, SITE, width, text=20)
        app = logo_at(f, "/?app", width, text=20)
        assert app == site, f"with larger text at {width} px: the app's logo {app}, the site's {site}"
        e = edges(f)
        assert abs(e["card"] - e["mark"]) <= 1 and abs(e["cardRight"] - e["controls"]) <= 1, f"with larger text at {width} px, Home's edges: {e}"
        f.shot(f"logo-{width}-larger-text")
    for width in (1024, 1440, 1720, 1920):
        logo_at(f, "/?app", width)
        e = edges(f)
        assert abs(e["card"] - e["mark"]) <= 1 and abs(e["cardRight"] - e["controls"]) <= 1, f"at {width} px, Home's edges: {e}"


if __name__ == "__main__":
    run("logo_match", journey, sizes=("desktop",))
