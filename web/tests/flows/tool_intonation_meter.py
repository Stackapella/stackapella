"""The intonation meter for /tools (js/public/tools/intonation-meter.js; docs/tools/competitors-key-finder-intonation-frequencies.md
section 2) on the tools' one layout, in the frame (web/tests/intonation-meter-demo.html), with a stand-in singer holding
a pure third over C3 (?sing=63.863): Start sounds the drone and listens, the third lights and rings (0 cents from pure,
the piano's pitch marked 14 cents to the right), and Barbershop 7th adds the seventh. Start is above the fold at
390 x 844 and 320 x 568.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

TOOL = "document.querySelector('#meter [data-ui=tool]')"
Q = lambda s: f"{TOOL}.querySelector('{s}')"  # noqa: E731


def journey(f):
    ready = "window.__meter && document.querySelector('header [data-tool-key] [data-step]')"
    f.go("/tests/intonation-meter-demo.html?sing=63.863", ready)
    f.run("localStorage.removeItem('stackapella.tools.just-intonation-tuner')")
    f.go("/tests/intonation-meter-demo.html?sing=63.863", ready)
    f.no_sideways("the intonation meter")
    assert f.js(f"{Q('[data-ui=tool-primary]')}.getBoundingClientRect().bottom <= innerHeight + 1"), "Start above the fold"
    f.shot("ready")
    f.run(f"{Q('[data-ui=tool-primary]')}.click()")
    f.wait(f"{TOOL}.dataset.ringing === 'true'", what="the third ringing")
    assert f.js(f"{Q('[data-tone-name]')}.textContent") == "Major 3rd", f.js(f"{Q('[data-tone-name]')}.textContent")
    assert f.js(f"!{Q('[data-piano-tick]')}.hidden"), "the piano's pitch marked"
    f.no_sideways("ringing")
    f.shot("ringing")
    # Rung for 2 s: the moment for the page's free lesson, once.
    f.wait("window.__lessons === 1", timeout=8, what="rung 2 s: the lesson moment")
    f.run(f"{Q('[data-segment=dom7]')}.click()")
    assert f.js(f"{TOOL}.querySelectorAll('[data-tones] button').length") == 4, "the seventh"
    f.run(f"{Q('[data-ui=tool-more]')}.click()")
    f.wait(f"{Q('[data-ui=tool-sheet]')}.open", what="the sheet")
    f.shot("more")
    f.run("window.__meter.stop()")


if __name__ == "__main__":
    run("tool_intonation_meter", journey, sizes=("phone", "se", "desktop"))
