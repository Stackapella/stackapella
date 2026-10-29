"""The song builder's "Pick the chords you'll sing over", every family, as Hillel reviews it: each
chord its family's shape (IV, I and V, each relative minor washed out: chord-walk.js) with its name
under it in letters (in C), numbers or numerals, and no text under the cards. CHORD_FAMILIES=classic
and CHORD_LABELS=letters,numbers,numerals choose what's shot. help_shots.py's atlas mode, both sizes:

    OUT=~/Code/shots-review/chords PORT=8410 tool/flows.sh chords_shots
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import OUT, compress, light_first, shoot, t  # noqa: E402

import os

FAMILIES = os.environ.get("CHORD_FAMILIES", "foundations,pop,soulful,minor,classic,folk,blues").split(",")
LABELS = os.environ.get("CHORD_LABELS", "letters").split(",")  # the names under the shapes: letters, numbers, numerals



def journey(f):
    assert OUT, "the shots need OUT=<dir>"
    f.go("/?api=mock&as=member", "document.querySelector('[data-new-song]')")
    light_first(f)
    f.run("document.querySelector('[data-new-song]').click()")
    f.click(t(f, "home.new.make"), "[data-new-sheet] button")
    f.click(t(f, "create.home.chords"), "[role=dialog] button")
    f.wait("location.pathname.startsWith('/create/') && document.querySelector('[data-chords]')", what="the chords step")
    assert "Taller" not in f.js("document.body.innerText"), "the height legend is back"
    assert not f.js("document.querySelector('#chords-show-as')"), "Show as is back: there's one style (Hillel)"
    names = f.js("[...document.querySelector('[data-chords]').closest('li').querySelectorAll('[data-chord]')].map((c) => c.textContent.trim())")
    assert names and all(names), f"a chord without its name: {names}"
    for family in FAMILIES:
        name = t(f, f"theory.family.{family}")
        f.run(f"[...document.querySelectorAll('[role=radiogroup] [role=radio]')].find((b) => b.textContent.trim() === {json.dumps(name)}).click()")
        f.wait("document.querySelector('[data-chords]')", what=family)
        for label in LABELS:
            f.run(f"[...document.querySelectorAll('[data-chord-labels] [role=radio]')].find((b) => b.textContent.trim() === {json.dumps(t(f, 'create.chords.labels.' + label))}).click()")
            shoot(f, f"chords-{family}-{label}", scroll="window.scrollTo(0, document.querySelector('[data-chords]').closest('ul').getBoundingClientRect().top + scrollY - 160)")
    # Hillel: the choice is remembered on the device.
    # (Letters alone, the default, is never written: nothing changed.)
    assert f.js("(JSON.parse(localStorage.getItem('stackapella.singPrefs')) ?? {}).chordLabels ?? 'letters'") == LABELS[-1], "the labels choice isn't kept"
    compress()


if __name__ == "__main__":
    run("chords_shots", journey)
