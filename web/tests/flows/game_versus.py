"""A challenge link (Hillel's 101.png, 2026-11-01): in game mode it opens on "You've been challenged!", a tilted red
banner between hazard stripes, then who challenges you (initials in a square, the name, "Challenges you"), the card (the
song, the part, the real score to beat and your own best) and Accept or Not now, each in turn; a tap shows it all at
once; the heading stays for screen readers. Accept starts the song on that part; Not now goes back. Serious mode and
the Versus screens switch off give the same layout, calm, with no score in serious.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from game_frames import dark, frames, light  # noqa: E402
from help_shots import t  # noqa: E402

VS = "document.querySelector('[data-game-versus]')"
URL = "/challenge/demo-token?api=mock&scenario=&as=member"


def journey(f):
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('#app header')")
    f.run("localStorage.removeItem('stackapella.gameEffects')")
    f.go(URL, "document.querySelector('[data-challenge] h1')")
    if f.skin == "serious":
        assert f.js(f"!{VS}"), "serious mode: the calm page"
        card = f.js("document.querySelector('[data-challenge-card]').innerText")
        assert "Amazing Grace" in card and "91" not in card, f"the song and part, no score: {card}"
        assert f.js("!!document.querySelector('[data-challenge-later]')"), "Not now"
        f.shot("challenge-serious")
        return
    f.wait("document.querySelector('[data-game-challenger]')", what="the challenger banner")
    frames(f, "intro", [0, 200, 500, 900, 1300])
    heading = t(f, "boards.challenge.from", {"name": "Ana Ruiz", "score": 91, "part": "Alto", "song": "Amazing Grace"})
    words = f.js(f"{VS}.innerText")
    assert heading in words, "the plain heading, for screen readers"
    assert "91" in words and "AR" in words and t(f, "game.versus.challengesYou").upper() in words.upper(), words
    f.no_sideways("the challenge")
    # A tap shows it all at once.
    f.go(URL, "document.querySelector('[data-game-challenger]')")
    f.run(f"{VS}.click()")
    f.wait(f"{VS}.classList.contains('is-done')", timeout=2, what="a tap shows it all")
    f.shot("challenge")
    light(f)
    f.shot("challenge-light")
    dark(f)
    # Not now: back, never the intro again.
    f.run("document.querySelector('[data-challenge-later]').click()")
    f.wait("!location.pathname.startsWith('/challenge')", timeout=10, what="Not now goes back")
    # Accept starts the part.
    f.go(URL, "document.querySelector('[data-challenge-accept]')")
    f.run("document.querySelector('[data-challenge-accept]').click()")
    f.wait("location.pathname.endsWith('/sing')", timeout=20, what="into the song")
    # The switch off: the calm page, with the score to beat (game mode).
    f.run("(await import('/js/game/effects.js')).effects.versus = false")
    f.go(URL, "document.querySelector('[data-challenge] h1')")
    assert f.js(f"!{VS}"), "the switch turns it off"
    assert "91" in f.js("document.querySelector('[data-challenge-card]').innerText"), "game mode keeps the score"
    f.run("localStorage.removeItem('stackapella.gameEffects')")


if __name__ == "__main__":
    run("game_versus", journey)
