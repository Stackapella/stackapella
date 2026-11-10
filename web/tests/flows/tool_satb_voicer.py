"""The four-part harmony voicer for /tools (js/public/tools/satb-voicer.js; docs/tools/competitors-transposer-satb.md
section 2) on the tools' one layout, in the frame (web/tests/satb-voicer-demo.html): the sample (C Am F G7 C) is voiced
for soprano, alto, tenor and bass on a grand staff with its chord names, Play lights each chord as it sounds, the alto
can be left out, and a new progression in A minor voices at once. Fits 390 x 844 and 320 x 568.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

TOOL = "document.querySelector('#voicer [data-ui=tool]')"
Q = lambda s: f"{TOOL}.querySelector('{s}')"  # noqa: E731


def journey(f):
    ready = "window.__voicer && document.querySelector('header [data-theme-toggle]')"
    f.go("/tests/satb-voicer-demo.html", ready)
    f.run("localStorage.removeItem('stackapella.tools.four-part-harmony')")
    f.go("/tests/satb-voicer-demo.html", ready)
    f.no_sideways("the voicer")
    assert f.js(f"{Q('[data-ui=tool-primary]')}.getBoundingClientRect().bottom <= innerHeight + 1"), "Play above the fold"
    assert f.js(f"{TOOL}.querySelectorAll('[data-staff] ellipse').length") == 20, "five chords of four"
    f.shot("sample")
    f.run(f"{Q('[data-ui=tool-primary]')}.click()")
    f.wait(f"[...{TOOL}.querySelectorAll('[data-staff] [data-column]')].some((g) => g.getAttribute('fill') === 'var(--primary)')", what="a chord lit as it sounds")
    f.shot("playing")
    f.run(f"{Q('[data-ui=tool-primary]')}.click()")
    f.run(f"{Q('[data-part=alto]')}.click()")
    assert f.js(f"{TOOL}.querySelectorAll('[data-staff] ellipse').length") == 15, "the alto left out"
    f.run(f"const i = {Q('[data-chords]')}; i.value = 'Am Dm E7 Am'; i.dispatchEvent(new Event('input', {{ bubbles: true }}))")
    assert f.js(f"{Q('[data-ui=tool-stat]')}.textContent") == "In Am", f.js(f"{Q('[data-ui=tool-stat]')}.textContent")
    f.no_sideways("A minor")
    f.shot("minor")
    f.run(f"{Q('[data-ui=tool-more]')}.click()")
    f.wait(f"{Q('[data-ui=tool-sheet]')}.open", what="the sheet")
    f.shot("more")
    f.run("window.__voicer.stop()")


if __name__ == "__main__":
    run("tool_satb_voicer", journey, sizes=("phone", "se", "desktop"))
