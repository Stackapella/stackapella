"""NEW RECORD (docs/game-elements-plan.md; the proposal's board 03): after a take the board counted as a
new best, game mode drops NEW RECORD in letter by letter in the best line, "up from 82" from the
server's own previous best; a best that wasn't new, Celebrations off and serious mode show today's line.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from boards import ANSWER, MOUNT, as_adult  # noqa: E402
from game_frames import dark, frames, light  # noqa: E402
from help_shots import t  # noqa: E402

RECORD = "document.querySelector('[data-game-record]')"


def answer(f, improved):
    f.run(ANSWER % json.dumps({"boards.scores.submit": {"data": {"counted": True, "best": 87, "improved": improved, "before": 82 if improved else 87}}}))


def journey(f):
    f.go("/?app&api=mock&scenario=&as=member", "document.querySelector('#app header')")
    as_adult(f, on_global=True)
    f.run("localStorage.setItem('stackapella.boards.asked', '1')")  # the worldwide question, already answered
    f.run("localStorage.removeItem('stackapella.gameEffects')")
    answer(f, True)
    f.run(MOUNT)
    f.wait_text(t(f, "boards.best", {"score": 87}))
    if f.skin == "serious":
        assert f.js(f"!{RECORD}"), "serious mode: the plain line only"
        return
    f.wait(RECORD, what="NEW RECORD")
    assert t(f, "game.record.from", {"before": 82}) in f.js(f"{RECORD}.innerText"), "where the best came from"
    frames(f, "record", [0, 120, 240, 400, 1000, 1200])
    f.run(f"{RECORD}.click()")
    f.wait(f"{RECORD}.classList.contains('is-landed')", what="a tap lands it")
    f.shot("record")
    light(f)
    f.shot("record-light")
    dark(f)
    # A best that isn't new: no banner.
    answer(f, False)
    f.run(MOUNT)
    f.wait_text(t(f, "boards.best", {"score": 87}))
    assert f.js(f"!{RECORD}"), "no banner without a new best"
    # Celebrations off: the plain line.
    f.run("(await import('/js/game/effects.js')).effects.celebrations = false")
    answer(f, True)
    f.run(MOUNT)
    f.wait_text(t(f, "boards.newBest"))
    assert f.js(f"!{RECORD}"), "Celebrations off: no banner"
    f.run("localStorage.removeItem('stackapella.gameEffects')")


if __name__ == "__main__":
    run("game_record", journey)
