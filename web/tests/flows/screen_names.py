"""Each screen named (js/screen-name.js): the tab's title is the screen's heading, from the first
load; after a move within the app (Settings from Home, a lesson from Learn, back) focus is on the new
heading, so a screen reader says where you are; a lesson that places focus itself keeps it.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

TITLE = "document.title"
HEADING = "(document.querySelector('#app main h1') ?? document.querySelector('#app h1'))"


def named(f, what):
    f.wait(f"{HEADING} && {TITLE} === ({HEADING}.textContent.trim().replace(/\\s+/g, ' ') + ' · Stackapella')", what=f"{what}: the title from its heading")


def moved_to(f, path, what):
    """A move within the app, as a link does it."""
    f.run(f"(await import('/js/router.js')).navigate({json.dumps(path)})")
    named(f, what)
    f.wait(f"document.activeElement === {HEADING} || document.activeElement?.closest('#app main')", what=f"{what}: focus on the new screen")


def journey(f):
    f.go("/settings?api=mock&as=signedOut", "document.querySelector('#app h1')")
    named(f, "Settings, the first load")
    assert f.js("document.activeElement === document.body"), "the first load leaves focus where the browser put it"
    moved_to(f, "/learn", "Learn")
    assert f.js(f"document.activeElement === {HEADING}"), "Learn: focus on its heading"
    moved_to(f, "/learn/1-comfortable", "lesson 1")
    moved_to(f, "/settings", "Settings again")
    assert f.js(f"document.activeElement === {HEADING}"), "Settings: focus on its heading"
    assert f.js(f"getComputedStyle({HEADING}).outlineStyle") == "none", "no focus ring round a heading"


run("screen_names", journey)
