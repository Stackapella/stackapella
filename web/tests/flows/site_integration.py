"""The public site in every theme (docs/public-site-plan.md, "Tests"; Hillel: the person's theme runs
through every public page): each page in all eight themes in the serious skin, and in the game skin
(dark, and in two light themes since the site follows light), at 320, 360, 375, 390, 768, 1440 and 1920 wide, takes the theme chosen (its
<html data-theme>), never scrolls sideways (Hillel's iPhone, 2026-10-29: the landing's Share card ran
past the edge), has one h1, and shows no page errors; and at 390 wide every visible word passes AA against what's behind it (4.5:1, or 3:1 for
large words; docs/ux-spec.md 7). On a phone (390 wide or less), no word, link, button or field on a
course page sticks out past either edge: their sections clip what overflows, so the page's own width
never shows it (Yodeling's valley echo and Barbershop's game buttons, 2026-10-30). Once a page at 390 wide, the speed budget (SPEED: nothing from another site blocks the first paint, lazy images
below the fold, its own weight). Needs the server's pages:
`python3 -I tests/flows/site_integration.py http://127.0.0.1:8000`.
"""
import json
import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[2] / "tool"))
from cdp import Chrome  # noqa: E402

# The landing (as everyone and as a mudeo singer), a page of each kind, and the pages other agents
# share the frame with. SitePagesTest renders every one of the rest.
PAGES = ["/", "/tools", "/tools/embed", "/tools/tuner", "/tools/metronome", "/tools/vocal-range-test", "/tools/pitch-pipe", "/tools/tuning-forks", "/tools/drone", "/tools/chord-transposer", "/tools/four-part-harmony", "/tools/pitch-matching", "/tools/note-frequency-chart", "/tools/tap-tempo", "/tools/mic-test", "/tools/vocal-key-finder", "/tools/chord-ear-training", "/tools/interval-ear-training", "/tools/audio-latency-test", "/tools/just-intonation-tuner", "/?ref=mudeo", "/how-it-works", "/courses", "/courses/other-languages", "/courses/barbershop", "/courses/yodeling", "/courses/counterpoint", "/courses/vibrato", "/courses/breath-and-stamina", "/courses/perfect-your-pitch", "/courses/ear-training", "/courses/folk-and-early-music", "/courses/pop-and-country", "/courses/improvising-harmony", "/courses/sight-singing", "/courses/range-and-registers", "/courses/a-cappella-bass", "/courses/musical-theatre", "/courses/scat", "/courses/gospel-runs", "/courses/diction", "/courses/belting", "/features", "/features/course", "/features/choirs", "/compare", "/compare/howli", "/compare/cyberbass-and-choralia", "/features/learn-a-part", "/features/gallery", "/for/choir-directors", "/for/a-cappella", "/for/worship-teams", "/for/voice-teachers", "/for/coaches", "/for/friends-and-family", "/about", "/press", "/whats-new", "/guides", "/guides/how-to-sing-harmony", "/guides/satb-explained", "/guides/singing-in-tune", "/guides/practise-a-choir-part", "/guides/hold-your-part", "/guides/practise-intonation-with-a-drone", "/legal/privacy", "/songs", "/songs/amazing-grace", "/songs/christmas-carols", "/pricing", "/help", "/help/mic", "/help/getting-started"]
# The speed budget (the coordinator's site speed pass, 2026-11-02; measured on a slowed phone by
# tests/flows/site_speed_integration.py): nothing from another site holds back the first paint (the
# fonts' stylesheet had blocked a course page for 7 s); images far below the fold load lazily, and videos
# don't preload; and a page's own weight stays in budget (uncompressed, as the local server sends it).
OWN_KB = 650  # the page and its own files, its stylesheets aside (the landing is about 500)
CSS_KB = 700  # site.css (about 385 since the course pages' rules went to courses.css)
COURSES_KB = 90  # the page's course sheet, its courses only (/course-css/, about 17 to 55; courses.css whole is 310), only where a .cp- class shows
REQUESTS = 45  # the page's own files (the landing's demo about 32)
SPEED = """(() => {
  const bad = [];
  const res = performance.getEntriesByType('resource');
  for (const r of res) if (r.renderBlockingStatus === 'blocking' && !r.name.startsWith(location.origin)) bad.push(`another site blocks the first paint: ${r.name.slice(0, 70)}`);
  for (const img of document.images) if (img.getBoundingClientRect().top > innerHeight * 1.5 && img.loading !== 'lazy') bad.push(`an image far below the fold loads at once: ${(img.currentSrc || img.src).replace(location.origin, '').slice(0, 60)}`);
  for (const v of document.querySelectorAll('video')) if (v.preload === 'auto' && !v.autoplay) bad.push(`a video preloads: ${(v.currentSrc || v.src || '').replace(location.origin, '').slice(0, 60)}`);
  const own = res.filter((r) => r.name.startsWith(location.origin));
  const css = own.filter((r) => /\\/css\\/site\\.css(\\?|$)/.test(r.name)).reduce((s, r) => s + r.decodedBodySize, 0) / 1024;
  const courseSheet = (r) => /\\/(css\\/courses|course-css\\/[a-z0-9.]+)\\.css(\\?|$)/.test(r.name);
  const courses = own.filter(courseSheet).reduce((s, r) => s + r.decodedBodySize, 0) / 1024;
  const sheet = (r) => /\\/css\\/site\\.css(\\?|$)/.test(r.name) || courseSheet(r);
  const kb = (performance.getEntriesByType('navigation')[0].decodedBodySize + own.filter((r) => !sheet(r)).reduce((s, r) => s + r.decodedBodySize, 0)) / 1024;
  // The course pages' rules where a .cp- class shows, and nowhere else: the page's own sheet (App\\Support\\Site\\CourseCss).
  const showsCourse = [...document.querySelectorAll('[class]')].some((el) => [...el.classList].some((c) => c.startsWith('cp-')));
  const linksCourses = [...document.querySelectorAll('link[rel=stylesheet]')].some((l) => /^\\/(css\\/courses|course-css\\/[a-z0-9.]+)\\.css$/.test((l.getAttribute('href') ?? '').split('?')[0]));
  if (showsCourse && !linksCourses) bad.push('a course class shows, but no course sheet is linked');
  if (!showsCourse && linksCourses) bad.push('a course sheet is linked, but no course class shows');
  if (courses > COURSES_KB) bad.push(`the course sheet is ${Math.round(courses)} KB (budget COURSES_KB)`);
  if (kb > OWN_KB) bad.push(`its own weight is ${Math.round(kb)} KB (budget OWN_KB)`);
  if (css > CSS_KB) bad.push(`site.css is ${Math.round(css)} KB (budget CSS_KB)`);
  if (own.length > REQUESTS) bad.push(`${own.length} requests for its own files (budget REQUESTS)`);
  return bad;
})()""".replace("OWN_KB", str(OWN_KB)).replace("COURSES_KB", str(COURSES_KB)).replace("CSS_KB", str(CSS_KB)).replace("REQUESTS", str(REQUESTS))
THEMES = [f"{family}-{mode}" for family in ("warm", "midnight", "graphite", "stage") for mode in ("light", "dark")]
WIDTHS = [320, 360, 375, 390, 768, 1440, 1920]
# The serious skin in every theme, then the game skin in dark and in two light themes (Hillel, 2026-11-02: "Whole site
# follows light"; game mode keeps its own light or dark, `gameMode`; the course pages stay dark for now).
LOOKS = [("serious", theme) for theme in THEMES] + [("game", "stage-dark"), ("game", "warm-light"), ("game", "midnight-light")]

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
                chrome.evaluate(f"localStorage.setItem('stackapella.appearance', {json.dumps(json.dumps({'mode': mode, 'gameMode': mode, 'colour': family, 'skin': skin, 'at': 1}))})")
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
                    if width == 390 and (skin, theme) == LOOKS[0]:
                        for problem in chrome.evaluate(SPEED):
                            failures.append(f"{page} speed: {problem}")
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
