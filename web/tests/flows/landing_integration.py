"""The landing's player (pages/landing.blade.php, js/public/mixer.js), on the real server's home page: Play sings the
song on the audio clock and the words light up in turn; a lane pressed mid-song is heard alone without stopping the
song, and pressed again brings all four back; Stop ends it and puts it back at its start. Language and appearance flips light
and dark and is kept, the visitor still on the home; game mode turned on goes to /arcade, where light and dark flip
and are kept, and turned off comes back. The recorded voices are stubbed as landing_page.py stubs them (this machine's database has none);
the whole page is landing_page.py's. Needs the server's page:
`python3 -I tests/flows/landing_integration.py http://127.0.0.1:8000`.
"""
import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from landing_page import FLIP, READY, STATE, VOICES  # noqa: E402

PAGE = "document.getElementById('lnf')"
THEME = "document.documentElement.dataset.theme"
HERE = "location.pathname + location.search"
# The Game mode switch on show (the header's pill or the menu's row), as arcade_home.py presses it.
TOGGLE = "([...document.querySelectorAll('[data-game-toggle]')].find((t) => t.getClientRects().length) ?? document.querySelector('[data-game-toggle]'))"
# Light or dark from the site header's Language and appearance (on /arcade, which has no home top line): the other look.
LOOK_FLIP = """(() => { const menu = [...document.querySelectorAll('[data-look-menu]')].find((m) => m.getClientRects().length) ?? document.querySelector('[data-look-menu]');
    menu.open = true; const light = (document.documentElement.dataset.theme ?? '').endsWith('-light');
    menu.querySelector(`[data-look-mode=${light ? 'dark' : 'light'}]`).click(); menu.open = false; })()"""


def landed(f, path, what):
    f.wait(f"{HERE} === {path!r} && document.readyState === 'complete'", timeout=15, what=what)


def journey(f):
    f.chrome.send("Page.addScriptToEvaluateOnNewDocument", {"source": VOICES})
    f.go("/", READY)
    assert f.js("[...document.querySelectorAll('[data-lnf-lanes] [data-voice]')].map((v) => v.dataset.voice)") == ["soprano", "alto", "tenor", "bass"], "the four lanes"
    assert f.js("document.querySelectorAll('[data-voice][data-solo], [data-voice][data-off]').length") == 0, "every voice on, none alone, at first"
    assert f.js("document.documentElement.scrollWidth <= innerWidth"), "no sideways scrolling"
    f.shot("ready")

    f.run("document.querySelector('[data-lnf-play]').click()")
    f.wait(f"{PAGE}.hasAttribute('data-playing')", timeout=40, what="playing")
    f.wait("document.querySelectorAll('.lnf-line [data-done]').length >= 2", timeout=20, what="the words lit in turn")
    assert f.js("document.querySelectorAll('.lnf-line [data-now]').length") == 1, "one word lit at a time"
    before = f.js(STATE)

    # A lane mid-song: that voice alone, the song carrying on; again, and all four are back.
    f.run("document.querySelector('[data-voice=alto] [data-hear-alone]').click()")
    f.wait("document.querySelector('[data-voice=alto]').hasAttribute('data-solo')", timeout=20, what="the alto alone")
    assert f.js("[...document.querySelectorAll('[data-voice][data-off]')].map((v) => v.dataset.voice)") == ["soprano", "tenor", "bass"], "the others step back"
    time.sleep(1)
    alone = f.js(STATE)
    assert alone["playing"] and alone["p"] > before["p"], f"the song carries on: {before['p']} then {alone['p']}"
    f.shot("alone")
    f.run("document.querySelector('[data-voice=alto] [data-hear-alone]').click()")
    f.wait("!document.querySelector('[data-voice][data-solo], [data-voice][data-off]')", timeout=20, what="all four again")

    # Stop: back at its start.
    f.run("document.querySelector('[data-play]').click()")
    f.wait(f"!{PAGE}.hasAttribute('data-playing')", timeout=10, what="stopped")
    time.sleep(0.5)
    assert f.js(STATE)["time"].startswith("0:00"), "Stop puts the song back at its start"

    # Light and dark by the top line's Language and appearance, kept for the next page. In a first visit's default game
    # look that's no choice of game mode (theme-boot.js stores no skin for it), so the visitor stays on the home.
    was = f.js(THEME)
    f.run(FLIP)
    f.wait(f"{THEME} !== {was!r}", timeout=5, what="the other look")
    time.sleep(0.8)
    assert f.js(HERE) == "/", f"a light or dark picked keeps the visitor on the home: {f.js(HERE)}"
    now = f.js(THEME)
    assert was.endswith("-light") != now.endswith("-light"), f"the switch flips light and dark: {was} then {now}"
    f.go("/pricing", "document.querySelector('main')")
    assert f.js(THEME) == now, "the choice is kept on the next page"
    f.go("/", READY)
    f.run(FLIP)
    f.wait(f"{THEME} === {was!r}", timeout=5, what="the first look again")

    # Game mode, chosen: its home is /arcade (App\Support\Site\ArcadeHandoff), so its checks are there. The switch turned on
    # hands the visitor over; light and dark flip there, in game mode, kept on the next page, and the home hands over
    # again; the switch off brings them back. A calm run (FLOW_SKINS=serious) lifts its pin to follow the switch.
    if f.skin_pin:
        f.chrome.send("Page.removeScriptToEvaluateOnNewDocument", {"identifier": f.skin_pin["identifier"]})
    if f.js(f"{TOGGLE}.getAttribute('aria-checked')") == "true":
        f.run(f"{TOGGLE}.click()")  # the default game look shows the switch on: off first, then on is the choice
        f.wait("document.documentElement.dataset.skin === 'serious'", timeout=10, what="game mode off, calm")
        assert f.js(HERE) == "/", f"game mode off stays on the home: {f.js(HERE)}"
    f.run(f"{TOGGLE}.click()")
    landed(f, "/arcade", "game mode on goes to /arcade")
    assert f.js("document.documentElement.dataset.skin") == "game" and f.js("!!document.querySelector('[data-cabinet]')"), "the arcade, in game mode"
    game_was = f.js(THEME)
    f.run(LOOK_FLIP)
    f.wait(f"{THEME} !== {game_was!r}", timeout=5, what="the arcade's other look")
    time.sleep(0.8)
    game_now = f.js(THEME)
    assert f.js(HERE) == "/arcade" and f.js("document.documentElement.dataset.skin") == "game", f"light or dark on /arcade stays there, in game mode: {f.js(HERE)}"
    assert game_was.endswith("-light") != game_now.endswith("-light"), f"the arcade flips light and dark: {game_was} then {game_now}"
    f.go("/pricing", "document.querySelector('main')")
    assert f.js(THEME) == game_now, "game mode's light or dark is kept on the next page"
    f.go("/", "document.readyState === 'complete'")
    landed(f, "/arcade", "in game mode the home hands over to /arcade")
    assert f.js(THEME) == game_now, "and /arcade shows it"
    f.no_sideways("the arcade")
    f.shot("arcade")
    f.run(f"{TOGGLE}.click()")
    landed(f, "/", "game mode off on /arcade goes to the home")

if __name__ == "__main__":
    run("landing_integration", journey)
