"""Accessibility of a new singer's first week (the coordinator's pass, 2026-11-01): a brand-new guest's
path (Home on day 0, Learn, lesson 1 and 2 at each kind of step, a song's Sing chooser, sign-up and
sign-in) audited in all eight colour themes (four families, light and dark) in the skin it runs in,
at a phone and a desktop size: one main and headings in order, a name for every control, AA contrast,
nothing focusable hidden from a screen reader, targets of 24 px or room round them, visible focus on
the first Tab stops, Tab kept inside an open sheet, no page errors. app_audit's checks, in every theme.

    FLOW_SKINS=game,serious PORT=8362 tool/flows.sh first_week_a11y
    A11Y_THEMES=stage-dark,warm-light ...   # some of the themes
"""
import json
import os
import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import font_blip, run  # noqa: E402
from help_shots import new_song, t  # noqa: E402
from app_audit import APP_AUDIT, APP_CONTRAST, HIDDEN_FOCUS, LAST_IN_SHEET, SMALL_TARGETS, focus_shows  # noqa: E402

THEMES = [f"{c}-{m}" for c in ["stage", "warm", "midnight", "graphite"] for m in ["dark", "light"]]
if os.environ.get("A11Y_THEMES"):
    THEMES = os.environ["A11Y_THEMES"].split(",")


def look(f, theme):
    colour, mode = theme.split("-")
    f.run(f"window.stackapellaTheme.choose({{ mode: {json.dumps(mode)}, colour: {json.dumps(colour)}, accent: null, customAccent: null }});")
    f.wait(f"document.documentElement.dataset.theme === {json.dumps(theme)}", what=theme)


def check(f, screen, failures):
    for i, theme in enumerate(THEMES):
        look(f, theme)
        time.sleep(0.3)
        where = f"{screen} {theme}"
        failures += [f"{where}: {p}" for p in f.chrome.evaluate(APP_AUDIT)] if i == 0 else []
        failures += [f"{where}: below AA: {low}" for low in f.chrome.evaluate(APP_CONTRAST)]
        if i == 0:
            failures += [f"{where}: {p}" for p in f.chrome.evaluate(HIDDEN_FOCUS)]
            failures += [f"{where}: {p}" for p in f.chrome.evaluate(SMALL_TARGETS)]
    if not f.js("!!document.querySelector('[role=dialog][aria-modal=true]')"):
        focus_shows(f, screen, failures)
    if f.chrome.evaluate(LAST_IN_SHEET):
        f.press("Tab")
        if not f.js("[...document.querySelectorAll('[role=dialog][aria-modal=true]')].at(-1)?.contains(document.activeElement)"):
            failures.append(f"{screen}: Tab from the sheet's last control leaves the sheet")
    errors = [e for e in f.chrome.errors if not any(x in e for x in f.expected_errors) and "favicon" not in e and not font_blip(e)]
    failures += [f"{screen}: page error: {e[:160]}" for e in errors[:3]]
    f.chrome.errors.clear()


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
    for lesson, step, kind in (("1-comfortable", 0, "learn"), ("1-comfortable", 1, "home"), ("1-match", 1, "try"), ("1-match", 2, "play")):
        f.run(f"sessionStorage.setItem('stackapella.lesson.{lesson}', '{step}');")
        visit(f"lesson-{kind}", f"/learn/{lesson}?api=mock&scenario=&as=signedOut", "document.querySelector('#app h1')")
    song = new_song(f, "amazing-grace")
    visit("sing-chooser", f"/p/{song}/sing?api=mock&scenario=&as=signedOut", "document.querySelector('h1')", lambda: f.wait_text(t(f, "sing.chooser.title")))
    visit("signup", "/signin?mode=signup&api=mock&scenario=&as=signedOut", "document.querySelector('form')")
    visit("signin", "/signin?api=mock&scenario=&as=signedOut", "document.querySelector('form')")

    if failures:
        unique = list(dict.fromkeys(failures))
        raise AssertionError(f"{len(unique)} problems:\n  " + "\n  ".join(unique[:60]))


if __name__ == "__main__":
    run("first_week_a11y", journey, sizes=("phone", "desktop"))
