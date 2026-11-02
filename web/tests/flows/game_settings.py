"""Game mode's own switches (docs/game-elements-plan.md, section 3): Settings' Game mode section lists
a switch for each arcade moment under the Game mode toggle, Game sounds off and the rest on; a change
is kept through a reload; game light and game dark both read; serious mode shows none of them.
Signed out (they're kept on the device).
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from game_frames import dark, light  # noqa: E402

ROWS = "document.querySelectorAll('[data-game-effect]')"
NAMES = ["arcade", "scanlines", "all", "sounds", "hud", "popups", "titles", "celebrations", "title", "stages", "versus", "select", "ghost", "daily", "monthly"]  # the look, then the moments


def on(f, name):
    return f.js(f"document.querySelector('[data-game-effect={name}] [role=switch]').getAttribute('aria-checked')") == "true"


def journey(f):
    f.go("/settings?api=mock&as=signedOut", "document.querySelector('#game-heading')")
    if f.skin == "serious":
        assert f.js(f"{ROWS}.length") == 0, "serious mode lists no game switches"
        return
    f.run("localStorage.removeItem('stackapella.gameEffects')")
    f.go("/settings?api=mock&as=signedOut", f"{ROWS}.length === {len(NAMES)}")
    assert f.js(f"[...{ROWS}].map((r) => r.dataset.gameEffect)") == NAMES, "every switch, in order"
    assert not on(f, "sounds") and all(on(f, n) for n in NAMES if n != "sounds"), "game sounds off, the rest on"
    f.run("document.getElementById('game').scrollIntoView()")
    f.no_sideways("the game switches")
    f.shot("game-dark")
    f.run("document.querySelector('[data-game-effect=sounds] [role=switch]').click(); document.querySelector('[data-game-effect=hud] [role=switch]').click()")
    f.wait("JSON.parse(localStorage.getItem('stackapella.gameEffects')).sounds === true", what="kept on the device")
    f.go("/settings?api=mock&as=signedOut", f"{ROWS}.length === {len(NAMES)}")
    assert on(f, "sounds") and not on(f, "hud"), "kept through a reload"
    light(f)
    f.run("document.getElementById('game').scrollIntoView()")
    f.shot("game-light")
    dark(f)
    # Game effects, the master switch: off quiets every moment at once (their switches greyed), the arcade look stays.
    f.run("document.querySelector('[data-game-effect=all] [role=switch]').click()")
    f.wait("document.documentElement.dataset.gameOff.split(' ').includes('popups')", what="the moments off")
    assert not f.js("document.documentElement.dataset.gameOff.split(' ').includes('arcade')"), "the arcade look stays"
    assert not f.js("document.documentElement.dataset.gameOff.split(' ').includes('scanlines')"), "its scanlines stay too (a look, not a moment)"
    assert f.js("document.querySelector('[data-game-effect=popups] [role=switch]').disabled || document.querySelector('[data-game-effect=popups] [role=switch]').getAttribute('aria-disabled') === 'true'"), "their switches greyed"
    f.run("document.getElementById('game').scrollIntoView()")
    f.shot("game-effects-off")
    f.run("localStorage.removeItem('stackapella.gameEffects')")


run("game_settings", journey)
