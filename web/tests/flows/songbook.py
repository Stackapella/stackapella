"""The songbook at /songs (Hillel's "Songbook page rebuild", 2026-11-11; js/public/songbook.js) on the server's own site:
the A to Z narrows in place by collection and by title, a letter with nothing left hides and the count follows, the hero's
search hands its words to the list, the song to try has a switch for every part, and nothing scrolls sideways.

    python3 -I tests/flows/songbook.py http://127.0.0.1:8494
"""
import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

READY = "document.readyState === 'complete' && !!document.querySelector('[data-songbook-index]')"
SHOWN = "document.querySelectorAll('[data-songbook-index] li[data-in]:not([hidden])').length"


def journey(f):
    f.go("/songs", READY)
    f.no_sideways("/songs")
    total = f.js("document.querySelectorAll('[data-songbook-index] li[data-in]').length")
    assert total > 0 and f.js(SHOWN) == total, f"/songs: every song shows at first ({total})"

    # A collection's chip leaves only its songs, and says how many.
    f.run("document.querySelector('[data-filter=rounds]').click()")
    rounds = f.js("document.querySelectorAll('[data-songbook-index] li[data-in~=rounds]').length")
    assert rounds > 0 and f.js(SHOWN) == rounds, f"rounds: {f.js(SHOWN)} shown, {rounds} rounds"
    assert f.js("document.querySelector('[data-filter=rounds]').getAttribute('aria-pressed')") == "true", "the chip says it's on"
    assert str(rounds) in f.js("document.querySelector('[data-shown]').textContent"), "the count follows"
    assert f.js("[...document.querySelectorAll('[data-letter]')].every((l) => l.hidden === !l.querySelector('li:not([hidden])'))"), "an empty letter hides"

    # The title search, on top of the chip, then alone.
    f.run("document.querySelector('[data-filter=\"\"]').click()")
    f.run("const s = document.querySelector('[data-index-search]'); s.value = 'shenandoah'; s.dispatchEvent(new Event('input'))")
    assert f.js(SHOWN) == 1, f"one song for 'shenandoah', not {f.js(SHOWN)}"
    f.run("const s = document.querySelector('[data-index-search]'); s.value = 'zzzz'; s.dispatchEvent(new Event('input'))")
    assert f.js(SHOWN) == 0 and not f.js("document.querySelector('[data-none]').hidden"), "nothing found says so"

    # The hero's search hands its words to the list.
    f.run("const i = document.querySelector('[data-songbook-search] input'); i.value = 'grace'; document.querySelector('[data-songbook-search]').requestSubmit()")
    time.sleep(0.6)
    assert f.js("document.querySelector('[data-index-search]').value") == "grace", "the list's search took the hero's words"
    assert f.js(SHOWN) >= 1 and f.js(f"{SHOWN} < {total}"), "the list narrowed"

    # The song to try: a switch for each part; switching one off doesn't error.
    toggles = f.js("document.querySelectorAll('#try-mixer [data-toggle]').length")
    assert toggles >= 2, f"the song to try has a switch per part ({toggles})"
    f.run("document.querySelector('#try-mixer [data-toggle]').click()")
    assert f.js("document.querySelector('#try-mixer [data-toggle]').getAttribute('aria-checked')") == "false", "a part switched off"
    f.shot("songbook")


run("songbook", journey)
