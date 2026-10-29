"""Home as the title screen, by reskinning what's there (docs/game-elements-plan.md; Hillel: "reskinned
these elements to be more game like"): in game mode the course card is a stage card ("Stage 1 · Find
your voice" in HUD type, its progress a power bar), Continue lesson is the Press start button (its
arrow blinks 5 times), the week's day bars are marquee bulbs (lit for each day that counted), and the
avatar chip says P1 at your level. Nothing new is stacked above the card. The Title screen switch and
serious mode give today's Home.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from game_frames import dark, frames, light  # noqa: E402
from home import home, progress, t  # noqa: E402

PRESS = "document.querySelector('[data-press-start]')"
EYEBROW = "document.querySelector('[data-lead-eyebrow]').innerText"


def journey(f):
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('#app header')")
    f.run("localStorage.removeItem('stackapella.gameEffects')")
    progress(f, ["1-comfortable", "1-match", "1-high-low"], 150)
    home(f, "member")
    unit = t(f, "home.lead.unit", {"number": 1, "title": "Find your voice"})
    if f.skin == "serious":
        assert f.js(f"!{PRESS} && !document.querySelector('[data-game-player]')"), "serious mode: today's Home"
        assert f.js(EYEBROW).upper() == unit.upper(), "the unit's own words"
        return
    f.wait(PRESS, what="Press start")
    assert f.js(EYEBROW).upper() == t(f, "game.home.stage", {"number": 1, "title": "Find your voice"}).upper(), f.js(EYEBROW)
    assert t(f, "home.lead.continueLesson") in f.js(f"{PRESS}.innerText") or f.js(f"{PRESS}.textContent").startswith(t(f, "home.lead.continueLesson")), "its name still says what it does"
    level = f.js("(await import('/js/course.js')).levelFor(150).level")
    if f.size == "desktop":
        assert t(f, "game.home.level", {"level": level}).upper() in f.js("document.querySelector('[data-game-player]').innerText").upper(), "P1 at your level in the avatar chip"
        assert f.js("document.querySelectorAll('[data-week-day][data-day-done]').length") >= 1, "today counted: a bulb lit"
    # Nothing stacked between the header and the course card.
    assert f.js("document.querySelector('#app header').nextElementSibling.contains(document.querySelector('[data-lead]'))"), "the course card comes straight after the header"
    f.no_sideways("the title screen")
    frames(f, "home", [0, 250, 600, 2600])
    f.shot("home")
    light(f)
    f.shot("home-light")
    dark(f)
    # The Title screen switch off: today's Home.
    f.run("(await import('/js/game/effects.js')).effects.title = false")
    f.wait(f"!{PRESS}", what="Continue lesson back")
    assert f.js(EYEBROW).upper() == unit.upper() and f.js("!document.querySelector('[data-game-player]')"), "the switch turns it all off"
    f.run("localStorage.removeItem('stackapella.gameEffects')")


if __name__ == "__main__":
    run("game_home", journey)
