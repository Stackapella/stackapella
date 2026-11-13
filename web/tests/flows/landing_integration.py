"""The landing's players (js/public/mixer.js), on the real server's landing page: Play sings the
hero's first line on the audio clock and the words light up in turn; a voice switched off mid-line
goes quiet without stopping the others and without being chosen; tapping a row brings that voice
forward (the radio, "You"), by the arrow keys too, and brings a voice that was off back on; Stop
ends it, and the line plays to its end by itself.
The theme switch flips light and dark and is kept. Needs the server's page:
`python3 -I tests/flows/landing_integration.py http://127.0.0.1:8000`.
"""
import os
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

HERO = "document.querySelector('.s-mixer')"


def journey(f):
    f.go("/", f"{HERO}?.hasAttribute('data-ready')")
    assert f.js(f"[...{HERO}.querySelectorAll('[data-voice]')].every((v) => !v.hasAttribute('data-off'))"), "every voice on"
    # Nothing picked until it is, as on the song pages (the song page review's rule, 2026-11-13): no row is yours yet.
    assert f.js(f"{HERO}.querySelectorAll('[role=radio][aria-checked=true], .s-is-you').length") == 0, "nothing picked at first"
    assert f.js("document.documentElement.scrollWidth <= innerWidth"), "no sideways scrolling"
    f.shot("ready")

    f.run(f"{HERO}.querySelector('[data-play]').click()")
    f.wait(f"{HERO}.hasAttribute('data-playing')", what="playing")
    f.wait(f"{HERO}.querySelectorAll('.s-lyrics [data-sung]').length >= 3", what="the words lit in turn")
    assert f.js(f"{HERO}.querySelectorAll('.s-lyrics [data-lit]').length") == 1, "one word lit at a time"
    play = f"{HERO}.querySelector('[data-play]')"
    assert f.js(f"{play}.getAttribute('aria-label') === {play}.dataset.labelStop"), "Play became Stop"

    # The tenor alone, mid-line, by tapping its row (Hillel, 2026-11-13: tap-to-solo): still playing, every other voice
    # off, its row lit; "You" stays where it was.
    row = lambda part: f"{HERO}.querySelector('[data-voice={part}]')"
    f.run(f"{row('tenor')}.querySelector('[data-hear-alone]').click()")
    assert f.js(f"{row('tenor')}.hasAttribute('data-solo') && {row('tenor')}.querySelector('[data-hear-alone]').getAttribute('aria-pressed') === 'true'"), "the tenor row lit, alone"
    assert f.js(f"[...{HERO}.querySelectorAll('[data-voice]')].filter((v) => v.hasAttribute('data-off')).map((v) => v.dataset.voice).sort().join()") == "alto,bass,soprano", "the others off"
    assert f.js(f"{HERO}.querySelectorAll('[role=radio][aria-checked=true]').length") == 0, "hearing a part alone doesn't choose it"
    assert f.js(f"{HERO}.hasAttribute('data-playing')"), "hearing one part doesn't stop the line"
    f.shot("tenor-alone")

    # Tapping it again brings every part back.
    f.run(f"{row('tenor')}.querySelector('[data-hear-alone]').click()")
    assert f.js(f"[...{HERO}.querySelectorAll('[data-voice]')].every((v) => !v.hasAttribute('data-off') && !v.hasAttribute('data-solo'))"), "all of them again"

    # The alto made yours by its small control: "You" moves with it, and the soprano steps back.
    f.run(f"{row('alto')}.querySelector('[data-pick]').click()")
    assert f.js(f"[...{HERO}.querySelectorAll('[data-pick]')].map((b) => b.getAttribute('aria-checked')).join()") == "false,true,false,false"
    assert f.js(f"{row('alto')}.classList.contains('s-is-you') && !{row('soprano')}.classList.contains('s-is-you')")
    assert f.js(f"{row('alto')}.querySelector('[data-pick]').getAttribute('aria-label')") == f.js(f"{row('alto')}.querySelector('[data-pick]').dataset.labelYou"), "named as your part"
    # The arrow keys move it on, like any radio group.
    f.run(f"const a = {row('alto')}.querySelector('[data-pick]'); a.focus(); a.dispatchEvent(new KeyboardEvent('keydown', {{ key: 'ArrowDown', bubbles: true }}))")
    assert f.js(f"document.activeElement === {row('tenor')}.querySelector('[data-pick]')"), "focus moved to the tenor"
    assert f.js(f"{row('tenor')}.querySelector('[data-pick]').getAttribute('aria-checked')") == "true"
    assert f.js(f"[...{HERO}.querySelectorAll('[data-pick]')].filter((b) => b.tabIndex === 0).length") == 1, "one tab stop"
    assert f.js(f"{HERO}.hasAttribute('data-playing')"), "choosing doesn't stop the line"
    f.shot("tenor-yours")

    f.run(f"{play}.click()")
    f.wait(f"!{HERO}.hasAttribute('data-playing')", what="stopped")
    assert f.js(f"{HERO}.querySelectorAll('[data-lit], [data-sung]').length") == 0, "the words reset"

    # Played to the end by itself (18 seconds), it's ready again.
    f.run(f"{play}.click()")
    f.wait(f"{HERO}.hasAttribute('data-playing')", what="playing again")
    f.wait(f"!{HERO}.hasAttribute('data-playing')", timeout=40, what="the line's end")

    # The theme switch (in the menu on phones): flips the mode and keeps it for the next page.
    was = f.js("document.documentElement.dataset.theme")
    f.run("const shown = [...document.querySelectorAll('[data-theme-toggle]')].find((b) => b.offsetParent); if (shown) shown.click(); else { document.querySelector('.s-menu').open = true; document.querySelector('.s-menu [data-theme-toggle]').click(); }")
    now = f.js("document.documentElement.dataset.theme")
    assert now != was and now.split("-")[1] != was.split("-")[1], f"{was} to {now}"
    f.go("/", f"{HERO}?.hasAttribute('data-ready')")
    assert f.js("document.documentElement.dataset.theme") == now, "the choice is kept"
    f.shot("theme")


# The four-voice mixer is the serious skin's hero; in game mode it waits hidden in the serious slot and the
# cabinet plays instead, so this journey runs in serious unless FLOW_SKINS says otherwise.
os.environ.setdefault("FLOW_SKINS", "serious")
run("landing_integration", journey)
