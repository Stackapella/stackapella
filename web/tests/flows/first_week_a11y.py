"""Accessibility of a new singer's first week (the coordinator's pass, 2026-11-01; the WCAG 2.2 AA sweep,
2026-11-02, docs/a11y-review-2026-11.md): a brand-new guest's
path (Home on day 0, Learn, lesson 1 and 2 at each kind of step, a song's Sing chooser, sign-up and
sign-in) audited in all eight colour themes (four families, light and dark) in the skin it runs in,
at a phone and a desktop size: one main and headings in order, a name for every control, AA contrast,
nothing focusable hidden from a screen reader, targets of 24 px or room round them, visible focus on
the first Tab stops, Tab kept inside an open sheet, no page errors. app_audit's checks, in every theme.
The sweep adds, on every screen: reflow at 320 px (WCAG 1.4.10, which also stands for 200% zoom) with
nothing scrolling sideways, and nothing still moving under prefers-reduced-motion (2.3.3: no animation
running more than once). And Escape closes a sheet (2.1.2), Sing's Paused blink stops under reduced
motion, and more of the path: sign-up's Check your email, Home's Today line, a song's sheet, Sing's coach,
and the site's landing when SITE is given (the server's own site, as journey_landing):

    SITE=http://127.0.0.1:8791 FLOW_SKINS=game,serious tool/flows.sh first_week_a11y

    FLOW_SKINS=game,serious PORT=8362 tool/flows.sh first_week_a11y
    A11Y_THEMES=stage-dark,warm-light ...   # some of the themes
"""
import json
import os
import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import SIZES, run  # noqa: E402
from help_shots import new_song, t  # noqa: E402
from app_audit import APP_AUDIT, APP_CONTRAST, HIDDEN_FOCUS, LAST_IN_SHEET, SMALL_TARGETS, focus_shows  # noqa: E402

THEMES = [f"{c}-{m}" for c in ["stage", "warm", "midnight", "graphite"] for m in ["dark", "light"]]
if os.environ.get("A11Y_THEMES"):
    THEMES = os.environ["A11Y_THEMES"].split(",")


def look(f, theme):
    colour, mode = theme.split("-")
    f.run(f"window.stackapellaTheme.choose({{ mode: {json.dumps(mode)}, colour: {json.dumps(colour)}, accent: null, customAccent: null }});")
    f.wait(f"document.documentElement.dataset.theme === {json.dumps(theme)}", what=theme)


# Animations still running under prefers-reduced-motion: anything set to play more than once.
MOVING = """(() => document.getAnimations().filter((a) => a.playState === 'running').map((a) => {
    const timing = a.effect?.getTiming?.() ?? {}; const el = a.effect?.target;
    return { name: a.animationName ?? a.constructor.name, times: timing.iterations, el: el ? el.tagName.toLowerCase() + '.' + [...el.classList].slice(0, 3).join('.') : '' };
}).filter((a) => a.times === Infinity || a.times > 1).map((a) => `${a.name} on ${a.el} (${a.times === Infinity ? 'forever' : a.times + ' times'})`))()"""
# Boxes that scroll sideways (rows meant to, with their scrollbar hidden, are left out), and the page's own overflow.
SIDEWAYS = """(() => { const over = document.documentElement.scrollWidth - document.documentElement.clientWidth;
    const inner = [...document.querySelectorAll('body *')].filter((e) => { const cs = getComputedStyle(e);
        return ['auto', 'scroll'].includes(cs.overflowX) && cs.scrollbarWidth !== 'none' && e.scrollWidth > e.clientWidth + 1 && e.clientWidth > 0 && e.tagName !== 'TEXTAREA'; })
        .map((e) => `${e.tagName.toLowerCase()}.${[...e.classList].slice(0, 3).join('.')} by ${e.scrollWidth - e.clientWidth}px`);
    return (over > 0 ? [`the page by ${over}px`] : []).concat(inner.slice(0, 3)); })()"""


def reflow(f, screen, failures):
    """320 CSS px (WCAG 1.4.10 reflow; a 1280 px window at 400%, or 640 px at 200%): nothing sideways."""
    width, height, mobile = SIZES[f.size]
    f.chrome.send("Emulation.setDeviceMetricsOverride", {"width": 320, "height": 720, "deviceScaleFactor": 2, "mobile": True})
    time.sleep(0.35)
    failures += [f"{screen}: at 320 px scrolls sideways: {p}" for p in f.chrome.evaluate(SIDEWAYS)]
    f.chrome.send("Emulation.setDeviceMetricsOverride", {"width": width, "height": height, "deviceScaleFactor": 2, "mobile": mobile})
    time.sleep(0.2)


def still(f, screen, failures):
    """Under prefers-reduced-motion, no animation plays more than once (WCAG 2.3.3, 2.2.2)."""
    failures += [f"{screen}: moves under reduced motion: {m}" for m in f.chrome.evaluate(MOVING)]


# Today's dot in the week row (Hillel, "Dot under today"): there, under today's letter, at 3:1 against what's
# behind it (WCAG 1.4.11), so today isn't told by colour alone.
TODAY_DOT = """(() => {
    const day = document.querySelector('[data-week-day][data-day-today]');
    if (!day || !day.offsetParent) return [];
    const dot = day.querySelector('[data-today-dot]');
    if (!dot || !dot.offsetWidth) return ['no dot under today'];
    const rgb = (c) => (c.match(/[\\d.]+/g) || []).map(Number);
    const lum = ([r, g, b]) => { const f = (v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4; }; return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b); };
    let el = dot.parentElement, back = null;
    while (el) { const c = rgb(getComputedStyle(el).backgroundColor); if (c.length >= 3 && (c[3] ?? 1) > 0.5) { back = c; break; } el = el.parentElement; }
    back ??= rgb(getComputedStyle(document.body).backgroundColor);
    const a = lum(rgb(getComputedStyle(dot).backgroundColor)), b = lum(back);
    const ratio = (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05);
    return ratio < 3 ? [`today's dot at ${ratio.toFixed(2)}:1`] : [];
})()"""


def check(f, screen, failures):
    for i, theme in enumerate(THEMES):
        look(f, theme)
        time.sleep(0.3)
        where = f"{screen} {theme}"
        failures += [f"{where}: {p}" for p in f.chrome.evaluate(APP_AUDIT)] if i == 0 else []
        failures += [f"{where}: below AA: {low}" for low in f.chrome.evaluate(APP_CONTRAST)]
        failures += [f"{where}: {p}" for p in f.chrome.evaluate(TODAY_DOT)]
        if i == 0:
            failures += [f"{where}: {p}" for p in f.chrome.evaluate(HIDDEN_FOCUS)]
            failures += [f"{where}: {p}" for p in f.chrome.evaluate(SMALL_TARGETS)]
    if not f.js("!!document.querySelector('[role=dialog][aria-modal=true]')"):
        focus_shows(f, screen, failures)
    if f.chrome.evaluate(LAST_IN_SHEET):
        f.press("Tab")
        if not f.js("[...document.querySelectorAll('[role=dialog][aria-modal=true]')].at(-1)?.contains(document.activeElement)"):
            failures.append(f"{screen}: Tab from the sheet's last control leaves the sheet")
    still(f, screen, failures)
    reflow(f, screen, failures)
    # A sheet open on this screen: Escape closes it (WCAG 2.1.2, no keyboard trap).
    if f.js("!!document.querySelector('[role=dialog][aria-modal=true]')"):
        f.press("Escape")
        time.sleep(0.3)
        if f.js("!!document.querySelector('[role=dialog][aria-modal=true]')"):
            failures.append(f"{screen}: Escape doesn't close the sheet")
    errors = [e for e in f.chrome.errors if not any(x in e for x in f.expected_errors) and "favicon" not in e]
    failures += [f"{screen}: page error: {e[:160]}" for e in errors[:3]]
    f.chrome.errors.clear()


# Where focus is, and whether the tab is named for the screen's one heading (the first-week a11y pass, 2026-11-14).
FOCUS_ON_HEADING = """(() => { const h = [...document.querySelectorAll('#app h1')]; const e = document.activeElement;
    const words = h[0]?.textContent.trim().replace(/\\s+/g, ' ') ?? '';
    return { headings: h.length, onHeading: !!h[0] && e === h[0], named: !!words && document.title.startsWith(words.slice(0, 30)), focus: e === document.body ? 'body' : e.tagName.toLowerCase() + ' ' + (e.innerText || e.getAttribute('aria-label') || '').trim().slice(0, 40), title: document.title }; })()"""


PASSED = []  # what the last tab_to went past, for its failure message


def tab_to(f, test, limit=40):
    """Tabs until the focused element passes `test` (a JS expression over `e`); False if it never does."""
    PASSED.clear()
    for _ in range(limit):
        f.press("Tab")
        if f.js(f"(() => {{ const e = document.activeElement; return !!({test}); }})()"):
            return True
        PASSED.append(f.js("(() => { const e = document.activeElement; return (e.innerText || e.getAttribute('aria-label') || e.tagName).trim().replace(/\\s+/g, ' ').slice(0, 30); })()"))
    return False


def on_heading(f, screen, failures):
    """After a change made with the keyboard: focus on the new screen's heading, and the tab named for it."""
    state = f.js(FOCUS_ON_HEADING)
    if state["headings"] != 1:
        failures.append(f"{screen}: {state['headings']} h1s")
    if not state["onHeading"]:
        failures.append(f"{screen}: focus isn't on the new heading but on {state['focus']}")
    if not state["named"]:
        failures.append(f"{screen}: the tab says {state['title']!r}, not the screen's heading")


def keyboard_lesson(f, failures):
    """Lesson 1 by Tab and Enter alone: Continue moves focus to the next step's heading and names the tab; the mic's
    live words stay few while it listens (nothing said on every reading); a guest's account ask has its own heading."""
    f.run("sessionStorage.removeItem('stackapella.lesson.1-comfortable')")
    f.go("/learn/1-comfortable?api=mock&scenario=&as=signedOut", "document.querySelector('[data-go-on]:not([disabled])')")
    if not tab_to(f, "e.matches('[data-go-on]')"):
        failures.append("keyboard lesson: Continue never reached by Tab")
        return
    f.press("Enter")
    f.wait("document.querySelector('[data-listening]')", what="the note step")
    time.sleep(0.6)
    on_heading(f, "keyboard lesson: after Continue", failures)
    f.run("""window.__live = 0; const said = new Map(); new MutationObserver(() => { for (const r of document.querySelectorAll('[aria-live]:not([aria-live=off]), [role=status], [role=alert]')) {
        const t = r.textContent.trim(); if (t && said.get(r) !== t) { said.set(r, t); window.__live++; } } }).observe(document.body, { subtree: true, childList: true, characterData: true });""")
    time.sleep(8)
    if f.js("window.__live") > 6:
        failures.append(f"keyboard lesson: {f.js('window.__live')} live announcements in 8 s while listening")
    f.go("/learn/1-match?api=mock&scenario=&as=signedOut", "document.querySelector('[data-needs-account]')")
    time.sleep(0.6)
    state = f.js(FOCUS_ON_HEADING)
    if state["headings"] != 1 or not state["named"]:
        failures.append(f"keyboard lesson: the account ask has {state['headings']} h1s and the tab says {state['title']!r}")


def keyboard_sing(f, failures, song):
    """Sing by Tab and Enter alone: the way, the part, the coach's steps (each takes focus to its heading and names the
    tab), Record, the setup sheets, and the take: its heading has focus, and Stop is a few Tabs away."""
    f.go(f"/p/{song}/sing?api=mock&scenario=&as=signedOut", "document.querySelector('[data-way=parts]')")
    if not tab_to(f, "e.matches('[data-way=parts]')"):
        failures.append("keyboard sing: Part by part never reached by Tab")
        return
    f.press("Enter")
    time.sleep(0.8)
    alto = t(f, "parts.alto")
    if not tab_to(f, f"(e.innerText || e.getAttribute('aria-label') || '').includes({alto!r}) && e.matches('button, [role=radio]')"):
        failures.append(f"keyboard sing: the alto never reached by Tab, past {PASSED[:12]}")
        return
    f.press("Enter")
    time.sleep(0.8)
    if f.js("!!document.querySelector('[data-select-sing]')") and tab_to(f, "e.matches('[data-select-sing]')"):
        f.press("Enter")  # game mode's part select: its Sing button
    f.wait("document.querySelector('[data-coach]')", what="the coach")
    time.sleep(0.6)
    on_heading(f, "keyboard sing: the coach", failures)
    for n in range(3):
        if not tab_to(f, "/^\\S+:/.test((e.innerText || '').trim()) && e.closest('[data-coach]')"):
            failures.append(f"keyboard sing: the coach's Next {n + 1} never reached by Tab")
            return
        f.press("Enter")
        time.sleep(0.6)
        on_heading(f, f"keyboard sing: the coach's step {n + 2}", failures)
    record = t(f, "sing.coach.action.record", {"part": alto})
    if not tab_to(f, f"e.tagName === 'BUTTON' && (e.innerText || '').trim() === {record!r}"):
        failures.append("keyboard sing: Record never reached by Tab")
        return
    f.press("Enter")
    for _ in range(4):  # the first take's setup sheets: headphones, the mic, the timing check
        time.sleep(1.2)
        if not f.js("!!document.querySelector('[role=dialog][aria-modal=true]')"):
            break
        if not tab_to(f, "e.closest('[role=dialog]') && (e.classList.contains('btn-primary') || /estimate|start|continue|^skip$/i.test((e.innerText || '').trim()))", 15):
            failures.append(f"keyboard sing: a setup sheet's way on never reached by Tab, past {PASSED[:12]}")
            return
        f.press("Enter")
    f.wait("document.querySelector('[data-recording-stage]')", timeout=15, what="the take")
    time.sleep(0.4)
    on_heading(f, "keyboard sing: the take", failures)
    if not tab_to(f, "e.matches('[data-stop]')", 6):
        failures.append("keyboard sing: Stop isn't within 6 Tabs of the take's heading")
    else:
        f.press("Enter")


def journey(f):
    failures = []
    f.chrome.send("Emulation.setEmulatedMedia", {"features": [{"name": "prefers-reduced-motion", "value": "reduce"}]})
    f.expected_errors += ["/contract/examples/"]

    def visit(name, path, ready, then=None):
        try:
            f.go(path, ready)
            if then:
                then()
            check(f, name, failures)
        except (AssertionError, TimeoutError) as error:
            failures.append(f"{name}: couldn't open: {error}")

    # A brand-new guest: nothing kept.
    f.go("/voice?api=mock&scenario=&as=signedOut", "document.querySelector('#app main')")
    f.run("localStorage.setItem('stackapella.asks', JSON.stringify({ stopped: ['install'] }));")
    visit("home-day0", "/?api=mock&scenario=&as=signedOut", "document.querySelector('#app main h1, #lead-title')")
    visit("learn", "/learn?api=mock&scenario=&as=signedOut", "document.querySelector('#app main h1, #app h1')")
    # Lesson 1 as a guest; from lesson 2 a guest is asked for a free account (Hillel, 2026-11-06), so lesson 2's
    # steps are checked as the new free account, and the guest's ask as a screen of its own.
    for lesson, step, kind in (("1-comfortable", 0, "learn"), ("1-comfortable", 1, "home"), ("1-match", 1, "try"), ("1-match", 2, "play")):
        f.run(f"sessionStorage.setItem('stackapella.lesson.{lesson}', '{step}');")
        who = "signedOut" if lesson == "1-comfortable" else "member"
        visit(f"lesson-{kind}", f"/learn/{lesson}?api=mock&scenario=&as={who}", "document.querySelector('#app h1')")
    visit("lesson-account", "/learn/1-match?api=mock&scenario=&as=signedOut", "document.querySelector('[data-needs-account]')")
    song = new_song(f, "amazing-grace")
    visit("sing-chooser", f"/p/{song}/sing?api=mock&scenario=&as=signedOut", "document.querySelector('h1')", lambda: f.wait_text(t(f, "sing.chooser.title")))
    # A song's sheet (a sheet: Tab stays in it, Escape closes it), then Sing's coach on a part.
    def song_sheet():
        f.wait("document.querySelector('[data-song-card] button')", what="a song card")
        f.run("document.querySelector('[data-song-card] button').click()")
        f.wait("document.querySelector('[role=dialog][aria-modal=true] [data-song-start]')", what="the song's sheet")
    visit("song-sheet", "/?api=mock&scenario=&as=signedOut", "document.querySelector('#app main')", song_sheet)

    def coach():
        f.wait_text(t(f, "sing.chooser.title"))
        f.run("document.querySelector('[data-way=parts]').click()")
        time.sleep(0.8)
        f.run("document.querySelector('main .card-interactive, main [data-game-select] [data-slot]')?.click()")
        f.wait("document.querySelector('[data-coach]')", what="Sing's coach")
    visit("sing-coach", f"/p/{song}/sing?api=mock&scenario=&as=signedOut", "document.querySelector('h1')", coach)
    try:
        keyboard_sing(f, failures, new_song(f, "amazing-grace"))
    except (AssertionError, TimeoutError) as error:
        failures.append(f"keyboard sing: couldn't walk: {error}")
    # Game mode's Paused (stopped part of the way through): its blinking word and ring stop under reduced motion.
    try:
        f.run("document.querySelector('.h-dvh.flex-col')?.setAttribute('data-paused', '')")
        time.sleep(0.4)
        still(f, "sing-paused", failures)
    except (AssertionError, TimeoutError) as error:
        failures.append(f"sing-paused: couldn't check: {error}")

    try:
        keyboard_lesson(f, failures)
    except (AssertionError, TimeoutError) as error:
        failures.append(f"keyboard lesson: couldn't walk: {error}")

    visit("signup", "/signin?mode=signup&api=mock&scenario=&as=signedOut", "document.querySelector('form')")

    def sent():
        f.type("form input[autocomplete=name]", "Robin Vale")
        f.type("form input[type=email]", "robin@example.com")
        f.type("form input[autocomplete=new-password]", "a long enough password")
        f.run("""const [month, year] = document.querySelectorAll('form select');
            month.value = month.options[3].value; month.dispatchEvent(new Event('change', { bubbles: true }));
            year.value = [...year.options].map((o) => o.value).find((v) => +v && +v < 2000); year.dispatchEvent(new Event('change', { bubbles: true }));""")
        f.run("document.querySelector('form button[type=submit]').click()")
        f.wait_text(t(f, "auth.sent.title"), timeout=10)
    visit("signup-sent", "/signin?mode=signup&api=mock&scenario=&as=signedOut", "document.querySelector('form')", sent)
    visit("signin", "/signin?api=mock&scenario=&as=signedOut", "document.querySelector('form')")

    # Past the first week: Home's Today line (experiment ret1's arm b, previewed) on a singer back after days away.
    def today():
        f.run("""const db = await import('/js/db.js'); const { emptyProgress } = await import('/js/course.js');
            await db.put('learning', { ...emptyProgress(), startedAt: Date.now() - 12 * 86400000, home: 57,
                lessons: { '1-comfortable': { stars: 3, bestPercent: 95, at: Date.now() - 9 * 86400000 } } }, 'progress');
            const d = new Date(Date.now() - 5 * 86400000); localStorage.setItem('stackapella.lastVisit', new Date(d - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10));
            sessionStorage.removeItem('stackapella.daysAway');""")
        f.go("/?api=mock&scenario=&as=signedOut&x=ret1-b", "document.querySelector('[data-lead]')")
        f.wait("document.querySelector('[data-today]')", what="Home's Today line")
    visit("home-today", "/?api=mock&scenario=&as=signedOut", "document.querySelector('#app main')", today)
    visit("course-map", "/learn?api=mock&scenario=&as=signedOut", "document.querySelector('[data-course-map], #app main h1')")

    # The site's landing (the server's own pages), when SITE is given: reflow, stillness and the first Tab stops.
    site = os.environ.get("SITE")
    if site:
        try:
            f.chrome.goto(site + "/?landing", "document.querySelector('main')")
            time.sleep(0.6)
            still(f, "landing", failures)
            reflow(f, "landing", failures)
            focus_shows(f, "landing", failures)
            failures += [f"landing: {p}" for p in f.chrome.evaluate(SMALL_TARGETS)]
            f.chrome.errors.clear()  # a guest's 401 from /api/account on the site is expected
        except (AssertionError, TimeoutError) as error:
            failures.append(f"landing: couldn't open: {error}")

    if failures:
        unique = list(dict.fromkeys(failures))
        raise AssertionError(f"{len(unique)} problems:\n  " + "\n  ".join(unique[:60]))


if __name__ == "__main__":
    run("first_week_a11y", journey, sizes=("phone", "desktop"))
