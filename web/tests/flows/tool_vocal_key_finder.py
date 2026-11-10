"""The vocal key finder for /tools (js/public/tools/vocal-key-finder.js; docs/tools/competitors-key-finder-intonation-frequencies.md
section 1) on the tools' one layout, in the frame (web/tests/vocal-key-finder-demo.html), with a tenor's range saved
(?range=48-69): Amazing Grace in G fits an octave down in G, guitar open; an alto sings it in F, capo 1, E shapes;
the song picker and the 12-key strip work, and Play sings it in the new key. Fits 390 x 844 and 320 x 568.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

TOOL = "document.querySelector('#finder [data-ui=tool]')"
Q = lambda s: f"{TOOL}.querySelector('{s}')"  # noqa: E731


def journey(f):
    ready = "window.__finder && document.querySelector('header [data-tool-key] [data-step]')"
    f.go("/tests/vocal-key-finder-demo.html?range=48-69", ready)
    f.run("localStorage.removeItem('stackapella.tools.vocal-key-finder')")
    f.go("/tests/vocal-key-finder-demo.html?range=48-69", ready)
    f.no_sideways("the key finder")
    assert f.js(f"{Q('[data-ui=tool-primary]')}.getBoundingClientRect().bottom <= innerHeight + 1"), "Play above the fold"
    # On the first screen (on a phone above Play; on a desktop the hero card holds Play and the panel sits under it).
    assert f.js(f"{Q('[data-low-slot] [data-step]')}.getBoundingClientRect().bottom <= innerHeight"), "the song's notes on the first screen"
    f.run(f"const s = {Q('[data-song]')}; s.value = '0'; s.dispatchEvent(new Event('change'))")
    assert f.js(f"{Q('[data-answer]')}.textContent") == "G", f.js(f"{Q('[data-answer]')}.textContent")
    assert f.js(f"{Q('[data-learn]')}.getAttribute('href')") == "/songs/amazing-grace", "learn it"
    assert "octave lower" in f.js(f"{Q('[data-shift]')}.textContent"), f.js(f"{Q('[data-shift]')}.textContent")
    f.shot("tenor")
    f.run(f"const v = {Q('[data-voice-pick]')}; v.value = 'alto'; v.dispatchEvent(new Event('change'))")
    assert f.js(f"{Q('[data-answer]')}.textContent") == "F", f.js(f"{Q('[data-answer]')}.textContent")
    assert f.js(f"{Q('[data-guitar]')}.textContent") == "Guitar: capo 1, play E shapes", f.js(f"{Q('[data-guitar]')}.textContent")
    f.run(f"{Q('[data-ui=tool-primary]')}.click()")
    f.wait(f"{Q('[data-ui=tool-primary]')}.getAttribute('aria-pressed') === 'true'", what="playing")
    f.no_sideways("the alto's key")
    f.shot("alto")
    f.run(f"{Q('[data-ui=tool-more]')}.click()")
    f.wait(f"{Q('[data-ui=tool-sheet]')}.open", what="the sheet")
    f.shot("more")
    f.run("window.__finder.stop(); localStorage.removeItem('stackapella.tools.range')")


if __name__ == "__main__":
    run("tool_vocal_key_finder", journey, sizes=("phone", "se", "desktop"))
