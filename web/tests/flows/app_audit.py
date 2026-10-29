"""The app's audit (9c, 2026-10-28; its shape agreed with the frontend agent): the public site's checks
(site_audit_integration) over the app's main screens and sheets on the mock, at 360, 768 and 1280
wide in Stage light and Stage dark, with motion reduced. Each screen has one main outside its
sheets, headings that never skip a level (they start again inside a sheet), a name for every link,
button and field, alt on every picture, no duplicate ids, no positive tabindex, no em dash, no
sideways scroll, AA contrast (disabled controls and words over the lane's canvas aside), no endless
animation while idle, nothing focusable hidden from assistive tech, Tab kept inside an open sheet,
and no page errors. The screens: the home, Learn and a lesson at each kind of step, a lesson's
result, today's workout, a song and the Sing chooser, Sing's steps, the studio with a part's panel, Trim, Export and
Share, Settings with How it looks, Reminders and Storage, sign-in and sign-up, Contact, and an
invite. Anything that needs a live mic (a turn, a take) is left out; widths.py, contrast.py and
console_clean.py check their own corners more closely.

    PORT=8414 tool/flows.sh app_audit
    AUDIT_SCREENS=studio,settings AUDIT_WIDTHS=360 PORT=8414 tool/flows.sh app_audit   # some of it
"""
import json
import os
import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
import flow  # noqa: E402
from help_shots import AUDIO, I18N, SESSION, clear_projects, guides_ready, home_page, into_part_by_part, new_song, progress, sung_takes, t  # noqa: E402
from site_audit_integration import AUDIT, ENDLESS  # noqa: E402
from site_integration import CONTRAST  # noqa: E402

WIDTHS = {"w360": (360, 780, True), "w768": (768, 1024, False), "w1280": (1280, 800, False)}
if os.environ.get("AUDIT_WIDTHS"):
    WIDTHS = {k: v for k, v in WIDTHS.items() if k[1:] in os.environ["AUDIT_WIDTHS"].split(",")}
MODES = ["light", "dark"]
ONLY = [s for s in os.environ.get("AUDIT_SCREENS", "").split(",") if s]


def tuned(script, swaps):
    """The site's check with the app's differences swapped in (each must still be there to swap)."""
    for old, new in swaps:
        if old not in script:
            raise AssertionError(f"the site's check changed: {old[:60]}")
        script = script.replace(old, new)
    return script


# A sheet is a dialog with its own heading, outside the page's main: one main outside the sheets,
# and the heading levels start again inside one.
APP_AUDIT = tuned(AUDIT, [
    ("if (document.querySelectorAll('main').length !== 1) bad.push(`${document.querySelectorAll('main').length} <main>`);",
     "const mains = [...document.querySelectorAll('main')].filter((m) => !m.closest('[role=dialog], dialog')).length; if (mains !== 1) bad.push(`${mains} <main>`);"),
    ("  for (const h of document.querySelectorAll('h1, h2, h3, h4, h5, h6')) {\n    if (!visible(h) && !h.closest('details')) continue;",
     "  let sheet = null;\n  for (const h of document.querySelectorAll('h1, h2, h3, h4, h5, h6')) {\n    if (!visible(h) && !h.closest('details')) continue;\n    const inside = h.closest('[role=dialog], dialog'); if (inside !== sheet) { sheet = inside; last = 0; }"),
])

# Disabled controls are faint on purpose, and words over the lane's canvas are read against it. A
# gradient with no solid colour under it (a note's glossy brick) can't be measured, so its words are
# left out; the glass buttons set a solid colour under their gloss for this.
APP_CONTRAST = tuned(CONTRAST, [
    ("const behind = (el) => { for (let e = el; e; e = e.parentElement) { const c = rgb(getComputedStyle(e).backgroundColor); if (c && c.a > 0.9) return c; }",
     "const behind = (el) => { for (let e = el; e; e = e.parentElement) { const st = getComputedStyle(e); const c = rgb(st.backgroundColor); if (c && c.a > 0.9) return c; if (st.backgroundImage.includes('gradient')) return null; }"),
    ("el.closest('[aria-hidden=\"true\"], .sr-only, svg')) continue;",
     # A closed <details> keeps its contents laid out (content-visibility: hidden), unseen: left out.
     "el.closest('[aria-hidden=\"true\"], .sr-only, svg, :disabled, [aria-disabled=\"true\"], details:not([open]) > :not(summary)') || overCanvas(el)) continue;"),
    ("  const bad = [];",
     "  const overCanvas = (el) => { const r = el.getBoundingClientRect(); return [...document.querySelectorAll('canvas')].some((c) => { const b = c.getBoundingClientRect(); return b.width * b.height < innerWidth * innerHeight * 0.6 && r.left < b.right && r.right > b.left && r.top < b.bottom && r.bottom > b.top; }); };\n  const bad = [];"),
])

FOCUSABLE = "a[href], button:not([disabled]), input:not([disabled]):not([type=hidden]), select:not([disabled]), textarea:not([disabled]), summary, [tabindex]:not([tabindex='-1'])"

# Nothing a keyboard can reach is hidden from a screen reader.
HIDDEN_FOCUS = f"""(() => {{
  const visible = (el) => !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length);
  return [...document.querySelectorAll({json.dumps(FOCUSABLE)})].filter((el) => visible(el) && el.tabIndex >= 0 && el.closest('[aria-hidden="true"]') && !el.closest('[inert]'))
    .slice(0, 3).map((el) => `focusable inside aria-hidden: ${{el.tagName.toLowerCase()}} "${{(el.textContent || el.getAttribute('aria-label') || '').trim().slice(0, 30)}}"`);
}})()"""

# The open sheet's last control, focused, so Tab can show whether focus stays inside.
LAST_IN_SHEET = f"""(() => {{
  const sheet = [...document.querySelectorAll('[role=dialog][aria-modal=true]')].at(-1);
  if (!sheet) return false;
  const visible = (el) => !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length);
  const last = [...sheet.querySelectorAll({json.dumps(FOCUSABLE)})].filter(visible).at(-1);
  if (!last) return false;
  last.focus();
  return true;
}})()"""


def look(f, mode):
    f.run(f"window.stackapellaTheme.choose({{ mode: {json.dumps(mode)}, colour: 'stage', accent: null, customAccent: null }});")
    f.wait(f"document.documentElement.dataset.theme === 'stage-{mode}'", what=f"stage-{mode}")


def check(f, screen, failures):
    """The screen as it is now, in both looks."""
    for mode in MODES:
        look(f, mode)
        time.sleep(0.4)
        where = f"{screen} stage-{mode} {f.size[1:]}px"
        failures += [f"{where}: {problem}" for problem in f.chrome.evaluate(APP_AUDIT)]
        failures += [f"{where}: below AA: {low}" for low in f.chrome.evaluate(APP_CONTRAST)]
        failures += [f"{where}: runs endlessly while idle: {name}" for name in f.chrome.evaluate(ENDLESS)]
        failures += [f"{where}: {problem}" for problem in f.chrome.evaluate(HIDDEN_FOCUS)]
    if f.chrome.evaluate(LAST_IN_SHEET):
        f.press("Tab")
        if not f.js("[...document.querySelectorAll('[role=dialog][aria-modal=true]')].at(-1)?.contains(document.activeElement)"):
            failures.append(f"{screen} {f.size[1:]}px: Tab from the sheet's last control leaves the sheet")
    errors = [e for e in f.chrome.errors if not any(x in e for x in f.expected_errors) and "favicon" not in e]
    failures += [f"{screen} {f.size[1:]}px: page error: {e[:160]}" for e in errors[:3]]
    f.chrome.errors.clear()


def screen(name):
    return not ONLY or name in ONLY


def from_menu(f, key):
    """A header action: its own button on a bigger screen, in the More menu on a phone."""
    label = json.dumps(t(f, key))
    shown = f"[...document.querySelectorAll('#app header button')].some((b) => b.getClientRects().length && b.textContent.trim().endsWith({label}))"
    if not f.js(shown):
        more = json.dumps(t(f, "common.more"))
        f.run(f"const menu = document.querySelector('#app header summary[aria-label={more}]').parentElement; if (!menu.open) menu.querySelector('summary').click();")
        f.wait(shown, what=f"{label} in the More menu")
    f.run(f"[...document.querySelectorAll('#app header button')].find((b) => b.getClientRects().length && b.textContent.trim().endsWith({label})).click()")
    f.wait("document.querySelector('[role=dialog]')", what=f"the {label} sheet")


def close_sheet(f):
    f.press("Escape")
    f.wait("!document.querySelector('[role=dialog][aria-modal=true]')", what="the sheet closed")


def audit(f):
    failures = []
    f.chrome.send("Emulation.setEmulatedMedia", {"features": [{"name": "prefers-reduced-motion", "value": "reduce"}]})
    f.expected_errors += ["/contract/examples/"]
    home_page(f, "member")
    f.run("localStorage.setItem('stackapella.installDismissed', '1'); sessionStorage.setItem('stackapella.headphones', '1');")
    clear_projects(f)

    def visit(name, path, ready, then=None):
        if not screen(name):
            return
        try:
            f.go(path, ready)
            if then:
                then()
            check(f, name, failures)
        except (AssertionError, TimeoutError) as error:
            failures.append(f"{name} {f.size[1:]}px: couldn't open: {error}")

    visit("home", "/?api=mock&scenario=&as=member", "document.querySelector('#lead-title')")

    # Learn, with units 1 to 4 done so every lesson opens, then a lesson at each kind of step.
    done = {lesson: {"stars": 3, "bestPercent": 95, "at": 0} for lesson in ("1-comfortable", "1-match", "1-high-low", "1-breath", "1-challenge", "2-hold", "2-drone", "2-vowels",
                                                                            "2-challenge", "3-steps-up", "3-steps-down", "3-jumps", "3-challenge", "4-words", "4-phrasing")}
    progress(f, done, 230, days=1, extra=", home: 57, range: { low: 55, high: 76 }")
    visit("learn", "/learn?api=mock&as=member", "document.querySelectorAll('main ol button, ol button').length > 5")
    visit("workout", "/workout?api=mock&as=member", "document.querySelector('#app h2')")
    for lesson, step, kind in (("1-comfortable", 0, "learn"), ("1-comfortable", 1, "home"), ("1-match", 1, "try"), ("1-match", 2, "play"), ("4-words", 1, "use"), ("4-challenge", 0, "record")):
        f.run(f"sessionStorage.setItem('stackapella.lesson.{lesson}', '{step}');")
        visit(f"lesson-{kind}", f"/learn/{lesson}?api=mock&as=member", "document.querySelector('#app h1')")

    # A song from the catalogue, then one of your own: the chooser and Sing's steps.
    def song_dialog():
        f.wait("document.querySelector('section[aria-labelledby=band-songs] li button:not([data-make])')", what="the songs")
        f.run("[...document.querySelectorAll('#home-songs [data-song-card]')].find((c) => c.textContent.includes('Amazing Grace')).querySelector('button').click()")
        f.wait("document.querySelector('[role=dialog]')?.innerText.includes('Amazing Grace')", what="the song")
    visit("song", "/?api=mock&scenario=&as=member", "document.querySelector('#lead-title')", song_dialog)
    grace = new_song(f)
    visit("chooser", f"/p/{grace}/sing?api=mock&as=member", "document.querySelector('h1')", lambda: f.wait_text(t(f, "sing.chooser.title")))
    if screen("sing"):
        try:
            into_part_by_part(f, grace)
            check(f, "sing", failures)
        except (AssertionError, TimeoutError) as error:
            failures.append(f"sing {f.size[1:]}px: couldn't open: {error}")

    # The studio with two takes, then a part's panel and each sheet.
    if any(screen(s) for s in ("studio", "part-panel", "trim", "export", "share")):
        try:
            f.go(f"/p/{grace}?api=mock&as=member", "document.querySelectorAll('canvas').length > 3")
            f.js(f"{AUDIO}.unlock()")
            sung_takes(f, ["soprano", "alto"])
            f.go(f"/p/{grace}?api=mock&as=member", "document.querySelectorAll('canvas').length > 3")
            guides_ready(f)
            if screen("studio"):
                check(f, "studio", failures)
            alto = t(f, "parts.alto")
            f.run(f"[...document.querySelectorAll('button[aria-expanded]')].find((b) => b.textContent.includes({alto!r})).click()")
            f.wait("document.querySelector('button[aria-expanded=true]')", what="the Alto's panel")
            if screen("part-panel"):
                check(f, "part-panel", failures)
            if screen("trim"):
                f.click_t("trim.button")
                f.wait("document.querySelector('[role=dialog]')", what="Trim")
                check(f, "trim", failures)
                close_sheet(f)
            for name, key in (("export", "studio.export"), ("share", "share.title")):
                if screen(name):
                    from_menu(f, key)
                    check(f, name, failures)
                    close_sheet(f)
        except (AssertionError, TimeoutError) as error:
            failures.append(f"studio {f.size[1:]}px: couldn't open: {error}")
    clear_projects(f)

    # Settings (every section is on its one page), How it looks, Reminders and Storage.
    visit("settings", "/settings?api=mock&scenario=&as=member", f"document.body.innerText.includes({json.dumps(t(f, 'settings.nav.account'))})")

    def appearance():
        f.wait_text(t(f, "settings.nav.account"))
        f.run("document.querySelector('#app button[aria-haspopup=dialog]').click()")
        f.wait("document.querySelector('[role=dialog]')", what="How it looks")
    visit("appearance", "/settings?api=mock&scenario=&as=member", "document.querySelector('#app h1')", appearance)
    visit("reminders", "/settings/reminders?api=mock&scenario=&as=member", "document.querySelector('#app h1')")
    visit("storage", "/storage?api=mock&scenario=&as=member", "document.querySelector('#app h1')")

    visit("sign-in", "/signin?api=mock&scenario=&as=signedOut", "document.querySelector('#app h1')")
    visit("sign-up", "/signin?mode=signup&api=mock&scenario=&as=signedOut", "document.querySelector('#app h1')")
    visit("contact", "/contact?api=mock&scenario=&as=member", "document.querySelector('#app h1')")
    visit("invite", "/invite/Pq4Rt7Vb2Nx9?api=mock&as=signedOut", "document.querySelector('h1')")
    return failures


def main():
    base = (sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:8081").rstrip("/")
    flow.SIZES.update(WIDTHS)
    failures = []
    for size in WIDTHS:
        f = flow.Flow(base, size, "app_audit")
        try:
            failures += audit(f)
        except Exception as error:  # report every width, then fail
            failures.append(f"{size[1:]}px: stopped: {error}")
        finally:
            f.chrome.errors.clear()
            f.close()
    print(f"{'ok  ' if not failures else 'FAIL'} app_audit ({len(WIDTHS)} widths, light and dark)")
    for failure in failures[:300]:
        print("     " + failure)
    sys.exit(1 if failures else 0)


if __name__ == "__main__":
    main()
