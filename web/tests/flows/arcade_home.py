"""The home in game mode is the arcade (/arcade; Hillel, 2026-11-24: "on the main home page when you enable game mode switch
to the view we used to show with the in-app game experience"), handed over before the first paint by
App\\Support\\Site\\ArcadeHandoff, on a stored choice of game mode only (docs/decisions.md, 2026-11-18: the default game look
is not a choice):
- a fresh visitor stays on the home, and /arcade sends them back to it;
- a stored choice of game mode goes from the home to /arcade, its ?ref kept, and in Spanish from /es to /es/arcade;
- Back from /arcade goes to the page before the home, never bouncing back;
- a stored choice of serious mode on /arcade goes to the home;
- the Game mode switch goes the one way on the home and the other on /arcade;
- the cabinet's PRESS START plays the take and nothing around it moves.
Needs the real server (site pages):

    python3 -I tests/flows/arcade_home.py http://127.0.0.1:8552
"""
import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
import flow  # noqa: E402

SITE = sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:8081"
HERE = "location.pathname + location.search"
STORE = "localStorage.setItem('stackapella.appearance', JSON.stringify({{ skin: '{skin}', gameMode: 'dark', at: Date.now() }}))"
SPOTS = "['[data-funnel-start]', '[data-cab-start]', '[data-cabinet]', '.s-factrow'].map((s) => { const b = document.querySelector(s).getBoundingClientRect(); return [Math.round(b.left), Math.round(b.top + scrollY), Math.round(b.width), Math.round(b.height)]; })"


def landed(f, path, what):
    f.wait(f"{HERE} === {path!r} && document.readyState === 'complete'", timeout=15, what=what)


def journey(f):
    # A fresh visitor: the default game look is not a choice, so the home stays the home, and /arcade sends them to it.
    f.go("/", "document.readyState === 'complete'")
    time.sleep(0.6)
    assert f.js(HERE) == "/" and f.js("!!document.getElementById('lnf')"), f"a fresh visitor stays on the home: {f.js(HERE)}"
    f.go("/arcade", "document.readyState === 'complete'")
    landed(f, "/", "a fresh visitor at /arcade goes to the home")

    # Game mode chosen: the home hands over, the ref kept; Back goes to the page before, with no bounce.
    f.run(STORE.format(skin="game"))
    f.go("/pricing", "document.readyState === 'complete'")
    f.go("/?ref=reddit", "document.readyState === 'complete'")
    landed(f, "/arcade?ref=reddit", "game mode chosen: the home goes to /arcade, its ref kept")
    assert f.js("!!document.querySelector('[data-cabinet]')"), "the arcade's cabinet is there"
    f.run("history.back()")
    landed(f, "/pricing", "Back from /arcade goes to the page before, never back to /arcade")
    f.go("/es", "document.readyState === 'complete'")
    landed(f, "/es/arcade", "in Spanish, /es goes to /es/arcade")

    # PRESS START plays the take, and nothing around it moves.
    f.go("/arcade", "document.querySelector('[data-cab-press]')")
    before = f.js(SPOTS)
    f.run("document.querySelector('[data-cab-press]').click()")
    time.sleep(2.5)
    assert f.js(SPOTS) == before, f"a press moves nothing: {before} then {f.js(SPOTS)}"

    # The switch on /arcade: game mode off goes to the home; on the home, game mode on goes to /arcade.
    f.run("([...document.querySelectorAll('[data-game-toggle]')].find((t) => t.getClientRects().length) ?? document.querySelector('[data-game-toggle]')).click()")
    landed(f, "/", "the switch off on /arcade goes to the home")
    f.run("([...document.querySelectorAll('[data-game-toggle]')].find((t) => t.getClientRects().length) ?? document.querySelector('[data-game-toggle]')).click()")
    landed(f, "/arcade", "the switch on on the home goes to /arcade")

    # Serious mode chosen: /arcade goes to the home.
    f.run(STORE.format(skin="serious"))
    f.go("/arcade", "document.readyState === 'complete'")
    landed(f, "/", "serious mode chosen: /arcade goes to the home")
    f.no_sideways("the home")


if __name__ == "__main__":
    flow.run("arcade_home", journey)
