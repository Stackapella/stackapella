"""The landing's players (js/public/mixer.js), on the real server's landing page: Play sings the
hero's first line on the audio clock and the words light up in turn; a voice switched off mid-line
goes quiet without stopping the others and without being chosen; tapping a row brings that voice
forward (the radio, "You"), by the arrow keys too, and brings a voice that was off back on; Stop
ends it, and the line plays to its end by itself.
The theme switch flips light and dark and is kept. Needs the server's page:
`python3 -I tests/flows/landing_integration.py http://127.0.0.1:8000`.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

HERO = "document.querySelector('.s-mixer')"


def journey(f):
    f.go("/", f"{HERO}?.hasAttribute('data-ready')")
    assert f.js(f"[...{HERO}.querySelectorAll('[data-toggle]')].every((b) => b.getAttribute('aria-checked') === 'true')"), "every voice on"
    assert f.js(f"{HERO}.querySelector('[role=radio][aria-checked=true]').closest('[data-voice]').dataset.voice") == "alto", "the alto forward"
    assert f.js("document.documentElement.scrollWidth <= innerWidth"), "no sideways scrolling"
    f.shot("ready")

    f.run(f"{HERO}.querySelector('[data-play]').click()")
    f.wait(f"{HERO}.hasAttribute('data-playing')", what="playing")
    f.wait(f"{HERO}.querySelectorAll('.s-lyrics [data-sung]').length >= 3", what="the words lit in turn")
    assert f.js(f"{HERO}.querySelectorAll('.s-lyrics [data-lit]').length") == 1, "one word lit at a time"
    play = f"{HERO}.querySelector('[data-play]')"
    assert f.js(f"{play}.getAttribute('aria-label') === {play}.dataset.labelStop"), "Play became Stop"

    # The tenor out, mid-line: still playing, and only its switch changed (the alto stays forward).
    f.run(f"{HERO}.querySelector('[data-voice=tenor] [data-toggle]').click()")
    assert f.js(f"{HERO}.querySelector('[data-voice=tenor] [data-toggle]').getAttribute('aria-checked')") == "false"
    assert f.js(f"{HERO}.querySelector('[data-voice=tenor]').hasAttribute('data-off')"), "the row looks off"
    assert f.js(f"{HERO}.querySelector('[data-voice=alto] [data-pick]').getAttribute('aria-checked')") == "true", "the switch didn't choose"
    assert f.js(f"{HERO}.hasAttribute('data-playing')"), "switching a voice doesn't stop the line"
    f.shot("tenor-out")

    # The soprano forward by its row: "You" moves with it, and the alto steps back.
    f.run(f"{HERO}.querySelector('[data-voice=soprano] [data-pick]').click()")
    assert f.js(f"[...{HERO}.querySelectorAll('[data-pick]')].map((b) => b.getAttribute('aria-checked')).join()") == "true,false,false,false"
    assert f.js(f"{HERO}.querySelector('[data-voice=soprano]').classList.contains('s-is-you') && !{HERO}.querySelector('[data-voice=alto]').classList.contains('s-is-you')")
    assert f.js(f"{HERO}.querySelector('[data-voice=soprano] [data-pick]').getAttribute('aria-label')") != "Soprano", "named as your part"
    # The arrow keys move it on, like any radio group, and a voice that was off comes back on.
    f.run(f"const s = {HERO}.querySelector('[data-voice=soprano] [data-pick]'); s.focus(); s.dispatchEvent(new KeyboardEvent('keydown', {{ key: 'ArrowDown', bubbles: true }})); s.closest('[data-player]').querySelector('[data-voice=alto] [data-pick]').dispatchEvent(new KeyboardEvent('keydown', {{ key: 'ArrowDown', bubbles: true }}))")
    assert f.js(f"document.activeElement === {HERO}.querySelector('[data-voice=tenor] [data-pick]')"), "focus moved to the tenor"
    assert f.js(f"{HERO}.querySelector('[data-voice=tenor] [data-pick]').getAttribute('aria-checked')") == "true"
    assert f.js(f"{HERO}.querySelector('[data-voice=tenor] [data-toggle]').getAttribute('aria-checked')") == "true", "forward means on"
    assert f.js(f"[...{HERO}.querySelectorAll('[data-pick]')].filter((b) => b.tabIndex === 0).length") == 1, "one tab stop"
    assert f.js(f"{HERO}.hasAttribute('data-playing')"), "choosing doesn't stop the line"
    f.shot("tenor-forward")

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


run("landing_integration", journey)
