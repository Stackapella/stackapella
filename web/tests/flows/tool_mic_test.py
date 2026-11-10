"""The mic test for /tools (js/public/tools/mic-test.js; docs/tools/competitors-tap-tempo-mic-test-pitch-memory.md
section 2) on the tools' one layout, in the frame (web/tests/mic-test-demo.html), on Chrome's fake mic: Test my mic
opens it and the meter moves; Record 5 s records a take on the audio clock, kept in the page; Play my take plays it
back; More shows the details. Then a blocked mic (?blocked) shows this browser's fix. Test my mic is above the
fold at 390 x 844 and 320 x 568.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

TOOL = "document.querySelector('#mic [data-ui=tool]')"
PRIMARY = f"{TOOL}.querySelector('[data-ui=tool-primary]')"


def journey(f):
    f.go("/tests/mic-test-demo.html", "window.__mic && document.querySelector('header [data-theme-toggle]')")
    f.no_sideways("the mic test")
    assert f.js(f"{PRIMARY}.getBoundingClientRect().bottom <= innerHeight + 1"), "Test my mic above the fold"
    assert f.js(f"{TOOL}.querySelectorAll('[data-ui=mic-segment]').length") == 24, "the meter"
    f.shot("ready")
    f.run(f"{PRIMARY}.click()")
    f.wait(f"{TOOL}.dataset.phase === 'listening'", what="the mic")
    f.wait(f"[...{TOOL}.querySelectorAll('[data-ui=mic-segment]')].some((b) => /bg-(good|good\\/60|cream|warn)(\\s|$)/.test(b.className))", what="the meter moving")
    assert f.js(f"{TOOL}.querySelector('[data-mic-name]').textContent"), "the mic's name"
    f.no_sideways("listening")
    f.shot("listening")
    # Record 5 s: it ends by itself on the audio clock, and the take is drawn.
    f.run(f"{PRIMARY}.click()")
    f.wait(f"{TOOL}.dataset.phase === 'recording'", what="recording")
    f.shot("recording")
    f.wait(f"{TOOL}.dataset.phase === 'recorded'", timeout=15, what="the take")
    assert f.js(f"!{TOOL}.querySelector('[data-wave-box]').hidden && {TOOL}.querySelector('[data-wave]').width > 0"), "the take's waveform"
    f.shot("recorded")
    f.run(f"{PRIMARY}.click()")
    f.wait(f"{TOOL}.dataset.phase === 'playing'", what="playing the take")
    f.wait(f"{TOOL}.dataset.phase === 'recorded'", timeout=10, what="the take played through")
    f.run(f"{TOOL}.querySelector('[data-ui=tool-more]').click()")
    f.wait(f"{TOOL}.querySelector('[data-ui=tool-sheet]').open", what="the sheet")
    assert f.js(f"{TOOL}.querySelectorAll('[data-details] dd').length") >= 3, "the details"
    f.no_sideways("More")
    f.shot("more")
    f.run(f"{TOOL}.querySelector('[data-mic-off]').click()")
    assert f.js(f"{TOOL}.dataset.phase") == "idle", "the mic off"
    # A blocked mic: this browser's own steps.
    f.go("/tests/mic-test-demo.html?blocked", "window.__mic && document.querySelector('header [data-theme-toggle]')")
    f.run(f"{PRIMARY}.click()")
    f.wait(f"{TOOL}.dataset.phase === 'problem'", what="the blocked mic")
    assert f.js(f"{TOOL}.querySelectorAll('[data-fix-list] li').length") >= 1, "a fix for this browser"
    f.no_sideways("blocked")
    f.shot("blocked")


if __name__ == "__main__":
    run("tool_mic_test", journey, sizes=("phone", "se", "desktop"))
