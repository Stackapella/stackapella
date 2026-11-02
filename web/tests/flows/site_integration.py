"""The public site in every theme (docs/public-site-plan.md, "Tests"; Hillel: the person's theme runs
through every public page): each page in all eight themes in the serious skin, and in the game skin
(always dark on the site), at 320, 360, 375, 390, 768, 1440 and 1920 wide, takes the theme chosen (its
<html data-theme>), never scrolls sideways (Hillel's iPhone, 2026-10-29: the landing's Share card ran
past the edge), has one h1, and shows no page errors; and at 390 wide every visible word passes AA against what's behind it (4.5:1, or 3:1 for
large words; docs/ux-spec.md 7). On a phone (390 wide or less), no word, link, button or field on a
course page sticks out past either edge: their sections clip what overflows, so the page's own width
never shows it (Yodeling's valley echo and Barbershop's game buttons, 2026-10-30). Needs the server's pages:
`python3 -I tests/flows/site_integration.py http://127.0.0.1:8000`.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[2] / "tool"))
from cdp import Chrome  # noqa: E402

# The landing (as everyone and as a mudeo singer), a page of each kind, and the pages other agents
# share the frame with. SitePagesTest renders every one of the rest.
PAGES = ["/", "/?ref=mudeo", "/how-it-works", "/courses", "/courses/barbershop", "/courses/yodeling", "/courses/counterpoint", "/courses/vibrato", "/courses/breath-and-stamina", "/courses/perfect-your-pitch", "/courses/ear-training", "/courses/folk-and-early-music", "/courses/pop-and-country", "/courses/improvising-harmony", "/courses/sight-singing", "/courses/range-and-registers", "/courses/a-cappella-bass", "/courses/musical-theatre", "/courses/scat", "/courses/gospel-runs", "/courses/diction", "/features", "/features/course", "/features/choirs", "/compare", "/compare/howli", "/compare/cyberbass-and-choralia", "/features/learn-a-part", "/features/gallery", "/for/choir-directors", "/for/a-cappella", "/for/worship-teams", "/for/voice-teachers", "/for/coaches", "/for/friends-and-family", "/about", "/press", "/whats-new", "/guides", "/guides/how-to-sing-harmony", "/legal/privacy", "/songs", "/songs/amazing-grace", "/pricing", "/help", "/help/mic", "/help/getting-started"]
THEMES = [f"{family}-{mode}" for family in ("warm", "midnight", "graphite", "stage") for mode in ("light", "dark")]
WIDTHS = [320, 360, 375, 390, 768, 1440, 1920]
# The serious skin in every theme, then the game skin once (the site keeps game mode dark).
LOOKS = [("serious", theme) for theme in THEMES] + [("game", "stage-dark")]

# Every visible word's contrast against the first solid background behind it (WCAG's formula).
CONTRAST = r"""(() => {
  const rgb = (c) => { const m = c.match(/rgba?\(([^)]+)\)/); if (!m) return null; const p = m[1].split(/[ ,\/]+/).filter(Boolean).map(Number); return { r: p[0], g: p[1], b: p[2], a: p.length > 3 ? p[3] : 1 }; };
  const lum = ({ r, g, b }) => { const f = (v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4; }; return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b); };
  const behind = (el) => { for (let e = el; e; e = e.parentElement) { const c = rgb(getComputedStyle(e).backgroundColor); if (c && c.a > 0.9) return c; } return rgb(getComputedStyle(document.body).backgroundColor); };
  const bad = [];
  const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
  for (let n = walker.nextNode(); n; n = walker.nextNode()) {
    const el = n.parentElement; if (!n.textContent.trim() || !el || !el.offsetParent || el.closest('[aria-hidden="true"], .sr-only, svg')) continue;
    const st = getComputedStyle(el); if (+st.opacity < 1 || st.visibility !== 'visible') continue;
    // Gradient-filled words (background-clip: text, the game hero's "made by you.") are painted by their background, not their colour.
    let clipped = false;
    for (let e = el; e && e !== document.body && !clipped; e = e.parentElement) { const c = getComputedStyle(e); clipped = c.backgroundClip === 'text' || c.webkitBackgroundClip === 'text'; }
    if (clipped) continue;
    let op = 1; for (let e = el; e; e = e.parentElement) op *= +getComputedStyle(e).opacity; if (op < 1) continue;
    const fg = rgb(st.color), bg = behind(el); if (!fg || !bg) continue;
    const a = lum(fg) + 0.05, b = lum(bg) + 0.05, ratio = Math.max(a, b) / Math.min(a, b);
    const size = parseFloat(st.fontSize), large = size >= 24 || (size >= 18.66 && +st.fontWeight >= 700);
    if (ratio < (large ? 3 : 4.5)) bad.push(`${el.tagName.toLowerCase()}.${el.className || ''} "${n.textContent.trim().slice(0, 30)}" ${ratio.toFixed(2)}`);
  }
  return [...new Set(bad)].slice(0, 5);
})()"""

# Every visible word (its own box too), link, button and field that runs past either edge of the
# screen, by how far. The screen is the root's clientWidth: on a phone, something too wide stretches
# the layout, and innerWidth and scrollWidth stretch with it. Sideways scrollers (overflow-x auto or
# scroll, like Counterpoint's canon band) may hold more than fits, and hidden words and drawn art
# (svg, and aria-hidden pieces with no words of their own) are not read. Words a screen reader skips
# (aria-hidden, like Yodeling's echo) are still seen, so they count.
STICKS_OUT = r"""(() => {
  const W = document.documentElement.clientWidth, out = new Map();
  const scrolls = (el) => { for (let e = el; e && e !== document.body; e = e.parentElement) { const o = getComputedStyle(e).overflowX; if (o === 'auto' || o === 'scroll') return true; } return false; };
  const skip = (el) => !el || el.closest('svg, [hidden], .sr-only') || (el.closest('[aria-hidden="true"]') && !el.textContent.trim()) || getComputedStyle(el).visibility !== 'visible' || !el.getClientRects().length || scrolls(el);
  const by = (rects) => { let over = 0; for (const r of rects) { if (!r.width && !r.height) continue; if (r.right > W + 1) over = Math.max(over, r.right - W); if (r.left < -1) over = Math.max(over, -r.left); } return Math.round(over); };
  const note = (el, over, text) => { if (over <= 0) return; const cls = typeof el.className === 'string' && el.className.trim() ? '.' + el.className.trim().split(/\s+/).join('.') : ''; const key = `<${el.tagName.toLowerCase()}${cls} "${text.trim().replace(/\s+/g, ' ').slice(0, 30)}">`; out.set(key, Math.max(out.get(key) || 0, over)); };
  const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
  for (let n = walker.nextNode(); n; n = walker.nextNode()) {
    if (!n.textContent.trim() || skip(n.parentElement)) continue;
    const range = document.createRange(); range.selectNodeContents(n);
    note(n.parentElement, Math.max(by(range.getClientRects()), by(n.parentElement.getClientRects())), n.textContent);
  }
  for (const el of document.querySelectorAll('a, button, input')) if (!skip(el)) note(el, by(el.getClientRects()), el.textContent || el.value || el.getAttribute('aria-label') || '');
  return [...out].sort((a, b) => b[1] - a[1]).slice(0, 5);
})()"""


def main():
    base = (sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:8081").rstrip("/")
    failures = []
    for width in WIDTHS:
        chrome = Chrome(None, width, 900, width < 900)
        try:
            chrome.goto(base + "/", "document.readyState === 'complete'")
            for skin, theme in LOOKS:
                family, mode = theme.split("-")
                chrome.evaluate(f"localStorage.setItem('stackapella.appearance', {json.dumps(json.dumps({'mode': mode, 'colour': family, 'skin': skin, 'at': 1}))})")
                for page in PAGES:
                    # Each page as a new visitor: a mudeo visit's mark mustn't carry over to the next.
                    chrome.send("Network.clearBrowserCookies")
                    chrome.goto(base + page, "document.readyState === 'complete'")
                    # Against clientWidth, not innerWidth: on a phone the layout stretches to fit
                    # what's too wide, and innerWidth stretches with it (Yodeling's echo hid that way).
                    facts = chrome.evaluate("({ theme: document.documentElement.dataset.theme, over: document.documentElement.scrollWidth - document.documentElement.clientWidth, h1: document.querySelectorAll('h1').length })")
                    where = f"{page} {skin} {theme} {width}px"
                    if facts["theme"] != theme:
                        failures.append(f"{where}: shows {facts['theme']}")
                    if facts["over"] > 0:
                        failures.append(f"{where}: scrolls sideways by {facts['over']}px")
                    if facts["h1"] != 1:
                        failures.append(f"{where}: {facts['h1']} h1s")
                    if width <= 390 and page.startswith("/courses/"):
                        for thing, over in chrome.evaluate(STICKS_OUT):
                            failures.append(f"{where}: sticks out by {over}px: {thing}")
                    if width == 390:
                        for low in chrome.evaluate(CONTRAST):
                            failures.append(f"{where}: below AA: {low}")
            errors = [e for e in chrome.errors if "favicon" not in e]
            if errors:
                failures.append(f"{width}px: page errors {errors[:3]}")
        finally:
            chrome.close()
        print(f"{'ok  ' if not failures else 'FAIL'} site_integration ({width}px)")
    for failure in failures:
        print("     " + failure)
    sys.exit(1 if failures else 0)


if __name__ == "__main__":
    main()
