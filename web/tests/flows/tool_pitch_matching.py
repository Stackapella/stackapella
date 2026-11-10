"""The pitch matching game for /tools (js/public/tools/pitch-matching.js; docs/tools/competitors-tap-tempo-mic-test-pitch-memory.md
section 3) on the tools' one layout, in the frame (web/tests/pitch-matching-demo.html), with a stand-in singer 8 cents
flat (?sing=-8): Play note plays it on the audio clock, the stages light in turn (Listen, Wait 2 s, Sing now), the
ring fills while the note is held, and the result says Spot on, 8 cents flat, scored 1 / 1. Play is above the fold
at 390 x 844 and 320 x 568.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

TOOL = "document.querySelector('#game [data-ui=tool]')"
PRIMARY = f"{TOOL}.querySelector('[data-ui=tool-primary]')"


def journey(f):
    ready = "window.__game && document.querySelector('header [data-ui=tool-key]')"
    f.go("/tests/pitch-matching-demo.html?sing=-8", ready)
    # Level 2 (a 2-second wait) and a high voice, as a first visit has them.
    f.run("localStorage.removeItem('stackapella.tools.pitch-matching')")
    f.go("/tests/pitch-matching-demo.html?sing=-8", ready)
    f.no_sideways("the pitch matching game")
    assert f.js(f"{PRIMARY}.getBoundingClientRect().bottom <= innerHeight + 1"), "Play above the fold"
    assert f.js(f"{TOOL}.querySelectorAll('[data-dots] span').length") == 10, "a round of ten"
    assert f.js(f"!{TOOL}.querySelector('[data-no-headphones]').hidden"), "no headphones needed, said up front"
    f.shot("ready")
    f.run(f"{PRIMARY}.click()")
    f.wait(f"{TOOL}.dataset.stage === 'wait'", what="the wait")
    f.shot("wait")
    f.wait(f"{TOOL}.dataset.stage === 'result'", timeout=15, what="the result")
    assert f.js(f"{TOOL}.dataset.verdict") == "spot", f.js(f"{TOOL}.dataset.verdict")
    assert f.js(f"{TOOL}.querySelector('[data-sub]').textContent") == "8 cents flat", f.js(f"{TOOL}.querySelector('[data-sub]').textContent")
    assert f.js(f"{TOOL}.querySelector('[data-ui=tool-stat]').textContent") == "1 / 1", "scored"
    f.no_sideways("the result")
    f.shot("result")
    assert not f.js("window.__lessons"), "the lesson moment waits for a whole round, not one note"
    f.run(f"{TOOL}.querySelector('[data-ui=tool-more]').click()")
    f.wait(f"{TOOL}.querySelector('[data-ui=tool-sheet]').open", what="the sheet")
    f.shot("more")
    f.run("window.__game.stop()")
    if f.size == "phone":
        whole_round(f)


def whole_round(f):
    """A whole round at level 1 (no wait), every note sung 8 cents flat: the lesson moment once, and a round with five or
    more right offers its card (Share my round) with the tool's link (?ref=share-pitch-matching), counted by the page."""
    ready = "window.__game && document.querySelector('header [data-ui=tool-key]')"
    f.run("localStorage.setItem('stackapella.tools.pitch-matching', JSON.stringify({ level: 0 }))")
    f.go("/tests/pitch-matching-demo.html?sing=-8", ready)
    row = f"{TOOL}.querySelector('[data-share-row]')"
    for n in range(10):
        f.run(f"{PRIMARY}.click()")
        f.wait(f"{TOOL}.dataset.stage === 'result' && {TOOL}.querySelector('[data-ui=tool-stat]').textContent === '{n + 1} / {n + 1}'", timeout=15, what=f"note {n + 1}")
        assert f.js(f"{row}.hidden") == (n < 9), f"after note {n + 1} the share is {'hidden' if f.js(f'{row}.hidden') else 'shown'}"
    assert f.js("window.__lessons") == 1, "a round sung: the lesson moment"
    assert f.js(f"{TOOL}.querySelector('[data-share]').textContent") == "Share my round"
    f.no_sideways("the round's share")
    f.shot("round-share")
    f.run("""window.__sent = null; navigator.canShare = (d) => !!d?.files?.length;
navigator.share = async (d) => { window.__sent = { type: d.files[0].type, size: d.files[0].size, text: d.text, url: d.url }; };""")
    f.run(f"{TOOL}.querySelector('[data-share]').click()")
    f.wait("window.__sent && window.__shared === 1", what="the card shared and counted")
    sent = f.js("window.__sent")
    assert sent["type"] == "image/png" and sent["size"] > 20000, sent
    assert sent["url"].endswith("/tools/pitch-matching?ref=share-pitch-matching"), sent["url"]
    assert sent["text"] == "I sang back 10 of 10 notes in tune. Can you?", sent["text"]
    f.run(f"{PRIMARY}.click()")
    f.wait(f"{TOOL}.dataset.stage !== 'result'", what="a new round")
    assert f.js(f"{row}.hidden"), "a new round puts it away"
    f.run("window.__game.stop()")


if __name__ == "__main__":
    run("tool_pitch_matching", journey, sizes=("phone", "se", "desktop"))
