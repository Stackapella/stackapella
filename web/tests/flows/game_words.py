"""Game mode's arcade words on screens that already exist (docs/game-elements-plan.md, batch 4): the
workout is "Training mode" in game mode and "Today's workout" in serious mode, as the skins' words
(sk()) decide; the screen is otherwise the same. It also lists the combo bonus's words for the copy
review: the pop-up as the rate goes up ("Bonus x2!") and the chip beside the combo ("x2", said "2 times the bonus").
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from game_frames import every_theme  # noqa: E402
from help_shots import t  # noqa: E402
from home import progress  # noqa: E402


def journey(f):
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('#app header')")
    progress(f, ["1-comfortable", "1-match", "1-high-low"], 150)
    f.go("/workout", "document.querySelector('main')")
    expected = t(f, "game.workout.title") if f.skin == "game" else t(f, "workout.title")
    f.wait_text(expected)
    f.shot("training-mode")
    every_theme(f, "training mode")
    if f.skin == "game":
        for count in (2, 3):
            words = [t(f, f"game.popup.{key}", {"count": count}) for key in ("rate", "rateChip", "rateChipLabel")]
            assert words == [f"Bonus x{count}!", f"x{count}", f"{count} times the bonus"], f"the bonus's words read {words}"
            print(f"[game_words] bonus x{count}: pop-up {words[0]!r}, chip {words[1]!r}, said {words[2]!r}")


if __name__ == "__main__":
    run("game_words", journey)
