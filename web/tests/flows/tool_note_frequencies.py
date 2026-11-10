"""The note frequency chart for /tools (js/public/tools/note-frequencies.js; docs/tools/competitors-key-finder-intonation-frequencies.md
section 3) on the tools' one layout, in the frame (web/tests/note-frequencies-demo.html): A4 big with 440.00 Hz, the
table of C0 to B8 scrolled to it inside the panel (the page doesn't move), a tap on middle C plays it, Between two
a search for 261.6 finds C4, Compare two says C4 to G4 is 700 cents, and A4 = 432 shows as a chip. The whole tool fits 390 x 844 and 320 x 568.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

TOOL = "document.querySelector('#chart [data-ui=tool]')"
Q = lambda s: f"{TOOL}.querySelector('{s}')"  # noqa: E731


def journey(f):
    ready = "window.__chart && document.querySelector('header [data-theme-toggle]')"
    f.go("/tests/note-frequencies-demo.html", ready)
    f.run("localStorage.removeItem('stackapella.tools.note-frequency-chart')")
    f.go("/tests/note-frequencies-demo.html", ready)
    f.no_sideways("the chart")
    assert f.js(f"{Q('[data-note]')}.textContent") == "A4", "A4 first"
    assert f.js(f"{Q('[data-ui=tool-primary]')}.getBoundingClientRect().bottom <= innerHeight + 1"), "Play above the fold"
    f.wait(f"(() => {{ const s = {Q('[data-scroll]')}.getBoundingClientRect(); const r = {Q('[data-rows] [data-midi=\"69\"]')}.getBoundingClientRect(); return r.top >= s.top && r.bottom <= s.bottom; }})()", what="A4's row in view")
    assert f.js("scrollY") == 0, "the page stays put"
    f.shot("ready")
    # A frequency typed in the search: the nearest note, and how far off.
    f.run(f"const s = {Q('[data-search]')}; s.value = '261.6'; s.dispatchEvent(new Event('input', {{ bubbles: true }}))")
    assert f.js(f"{Q('[data-note]')}.textContent") == "C4", "261.6 Hz is a C4"
    f.shot("search")
    f.run(f"{Q('[data-pick=\"60\"]')}.click()")
    assert f.js(f"{Q('[data-hz]')}.textContent") == "261.63", "middle C"
    f.wait(f"{Q('[data-ui=tool-status]')}.dataset.tone === 'good'", what="playing")
    f.shot("middle-c")
    f.run(f"{Q('[data-compare]')}.click()")
    f.run(f"{Q('[data-pick=\"67\"]')}.click()")
    assert f.js(f"{Q('[data-note]')}.textContent") == "C4 → G4" and f.js(f"{Q('[data-hz]')}.textContent") == "+700", "700 cents"
    f.no_sideways("between two")
    f.shot("two")
    f.run(f"{Q('[data-ui=tool-more]')}.click()")
    f.wait(f"{Q('[data-ui=tool-sheet]')}.open", what="the sheet")
    f.run(f"{Q('[data-preset=\"432\"]')}.click()")
    f.shot("more")
    f.run(f"{Q('[data-sheet-done]')}.click()")
    assert f.js(f"{Q('[data-chip]')}.textContent") == "A4 = 432 Hz", "the chip"
    f.shot("a432")
    f.run("window.__chart.stop()")


if __name__ == "__main__":
    run("tool_note_frequencies", journey, sizes=("phone", "se", "desktop"))
