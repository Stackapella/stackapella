"""The course's Learn steps (Hillel): a headline takeaway, numbered step cards, and a picture
beside them on a wide screen (above them on a phone); breathing as a living flower that opens on
the in-breath and folds on "sss". Shot in Stage dark and Warm light.
"""
import json
import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

I18N = "(await import('/js/i18n.js'))"


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def journey(f):
    for theme in ["stage-dark", "warm-light"]:
        colour, mode = theme.split("-")
        f.go("/learn/1-breath", "document.querySelector('main h1')")
        f.run(f"localStorage.setItem('stackapella.appearance', {json.dumps(json.dumps({'mode': mode, 'colour': colour}))}); window.stackapellaTheme.apply();")
        f.wait_text("Breath that's steady gives notes that are steady.")
        assert f.js("document.querySelectorAll('main ol li').length") == 3, "three step cards"
        f.wait("document.querySelector('main canvas')", what="the breathing picture")
        f.wait_text(t(f, "learnVisual.breatheIn"))
        time.sleep(2.5)
        f.shot(f"breath-{theme}")
        f.go("/learn/1-high-low", "document.querySelector('main h1')")
        f.wait("document.querySelector('.learn-visual svg')", what="the ladder picture")
        f.shot(f"ladder-{theme}")
    f.run("localStorage.removeItem('stackapella.appearance'); window.stackapellaTheme.apply();")


if __name__ == "__main__":
    run("learn_steps", journey)
