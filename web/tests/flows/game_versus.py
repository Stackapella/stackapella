"""A challenge as a versus (docs/game-elements-plan.md; the proposal's board 06): in game mode a challenge
link opens with "Here comes a new challenger!" for a second (a tap skips it), then the challenger and you
head to head, their real score against your best on the part, a slanted VS, the board's rule in one
line and the same Sing button; the heading stays for screen readers. The Versus screens switch and
serious mode give today's challenge page.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from game_frames import dark, frames, light  # noqa: E402
from help_shots import t  # noqa: E402

VS = "document.querySelector('[data-game-versus]')"
BANNER = "document.querySelector('[data-game-challenger]')"
URL = "/challenge/demo-token?api=mock&scenario=&as=member"


def journey(f):
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('#app header')")
    f.run("localStorage.removeItem('stackapella.gameEffects')")
    f.go(URL, "document.querySelector('[data-challenge] h1')")
    if f.skin == "serious":
        assert f.js(f"!{VS}"), "serious mode: today's challenge page"
        return
    f.wait(BANNER, what="the challenger banner")
    frames(f, "banner", [0, 120, 320])
    f.wait(f"!{BANNER}", timeout=5, what="the banner gone by itself")
    heading = t(f, "boards.challenge.from", {"name": "Ana Ruiz", "score": 91, "part": "Alto", "song": "Amazing Grace"})
    assert heading in f.js(f"{VS}.innerText"), "the plain heading, for screen readers"
    words = f.js(f"{VS}.innerText")
    assert "91" in words and t(f, "game.versus.rule", {"score": 91}) in words, words
    f.no_sideways("the versus")
    f.go(URL, "document.querySelector('[data-game-challenger]')")
    f.run(f"{BANNER}.click()")
    f.wait(f"!{BANNER} && document.querySelector('.ge-vs-vs')", timeout=2, what="a tap skips the banner")
    frames(f, "versus", [0, 200, 400, 700])
    f.shot("versus")
    light(f)
    f.shot("versus-light")
    dark(f)
    # The same Sing button starts the part.
    f.click(t(f, "boards.challenge.sing", {"part": "Alto"}))
    f.wait("location.pathname.endsWith('/sing')", timeout=20, what="into the song")
    # The switch off: today's page.
    f.run("(await import('/js/game/effects.js')).effects.versus = false")
    f.go(URL, "document.querySelector('[data-challenge] h1')")
    assert f.js(f"!{VS}"), "the switch turns it off"
    f.run("localStorage.removeItem('stackapella.gameEffects')")


if __name__ == "__main__":
    run("game_versus", journey)
