"""The chord transposer for /tools (js/public/tools/chord-transposer.js; docs/tools/competitors-transposer-satb.md
section 1) on the tools' one layout, in the frame (web/tests/chord-transposer-demo.html): the sample sheet (Amazing
Grace in G, chords over lyrics) goes up 3 to B flat, spelled with flats, the lyrics untouched and the chords still
over their syllables, the capo said; then straight to E. Fits 390 x 844 and 320 x 568.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

TOOL = "document.querySelector('#transposer [data-ui=tool]')"
Q = lambda s: f"{TOOL}.querySelector('{s}')"  # noqa: E731
UP = "document.querySelector('header [data-tool-key] [data-step=\"1\"]')"


def journey(f):
    ready = "window.__transposer && document.querySelector('header [data-tool-key] [data-step]')"
    f.go("/tests/chord-transposer-demo.html", ready)
    f.run("localStorage.removeItem('stackapella.tools.chord-transposer')")
    f.go("/tests/chord-transposer-demo.html", ready)
    f.no_sideways("the transposer")
    assert f.js(f"{Q('[data-ui=tool-primary]')}.getBoundingClientRect().bottom <= innerHeight + 1"), "Copy above the fold"
    f.shot("empty")
    f.run(f"{Q('[data-try]')}.click()")
    assert "chords found" in f.js(f"{Q('[data-ui=tool-status]')}.textContent"), f.js(f"{Q('[data-ui=tool-status]')}.textContent")
    f.shot("sample")
    for _ in range(3):
        f.run(f"{UP}.click()")
    out = f.js(f"{Q('[data-output]')}.textContent").split("\n")
    print("    " + " / ".join(out[1:4]))
    assert out[1] == "Bb         Eb       Bb" and out[2] == "Amazing grace, how sweet the sound", out[1:3]
    assert f.js(f"{Q('[data-keys]')}.textContent") == "G → Bb", f.js(f"{Q('[data-keys]')}.textContent")
    assert f.js(f"{Q('[data-capo]')}.textContent") == "Guitar: capo 1, play A shapes", f.js(f"{Q('[data-capo]')}.textContent")
    f.no_sideways("transposed")
    f.shot("transposed")
    f.run(f"{Q('[data-ui=tool-more]')}.click()")
    f.wait(f"{Q('[data-ui=tool-sheet]')}.open", what="More, the settings sheet")
    f.no_sideways("More")
    f.shot("more")
    f.run(f"const s = {Q('[data-to-key]')}; s.value = '4'; s.dispatchEvent(new Event('change'))")
    assert f.js(f"{Q('[data-keys]')}.textContent") == "G → E", f.js(f"{Q('[data-keys]')}.textContent")
    f.run("window.__transposer.stop()")


if __name__ == "__main__":
    run("tool_chord_transposer", journey, sizes=("phone", "se", "desktop"))
