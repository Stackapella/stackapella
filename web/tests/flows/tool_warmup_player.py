"""The warm-up player for marketing's /warmups/<exercise> pages (js/public/tools/warmup-player.js; docs/seo-growth-plan.md
item 8) on the tools' one layout, in the frame (web/tests/warmup-player-demo.html): the courses lead's oo-scale (course/warmups.json) climbing through
keys in a medium voice, Start plays it on the audio clock and the keyboard lights each note as it sounds, Stop stops it, the
Low voice moves it down, and More holds the way it's sung (Sing along, Hear it then sing) and the times through. Start
is above the fold at 390 x 844 and 320 x 568.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

TOOL = "document.querySelector('#warmup [data-ui=tool]')"
Q = lambda s: f"{TOOL}.querySelector('{s}')"  # noqa: E731


def journey(f):
    ready = "window.__warmup && document.querySelector('header [data-tool-key] [data-step]')"
    f.go("/tests/warmup-player-demo.html", ready)
    f.run("localStorage.removeItem('stackapella.tools.warmups-oo-scale')")
    f.go("/tests/warmup-player-demo.html", ready)
    f.no_sideways("the warm-up player")
    assert f.js(f"{Q('[data-ui=tool-primary]')}.getBoundingClientRect().bottom <= innerHeight + 1"), "Start above the fold"
    assert f.js(f"{Q('[data-round]')}.textContent").startswith("Round 1 of"), f.js(f"{Q('[data-round]')}.textContent")
    # When to stop, on the first screen, not only in More (the courses lead).
    stop = Q('[data-stop-line]')
    assert f.js(f"{stop}.textContent") and f.js(f"!{stop}.hidden && {stop}.getBoundingClientRect().bottom <= innerHeight"), "the stop line seen before singing"
    f.shot("ready")
    f.run(f"{Q('[data-ui=tool-primary]')}.click()")
    f.wait(f"{TOOL}.querySelector('[data-keyboard] [data-marked]')", what="a key lit as its note sounds")
    assert f.js(f"{TOOL}.dataset.playing") == "true", "playing"
    f.no_sideways("playing")
    f.shot("playing")
    f.run(f"{Q('[data-ui=tool-primary]')}.click()")
    assert f.js(f"{TOOL}.dataset.playing") == "false" and not f.js(f"{TOOL}.querySelector('[data-keyboard] [data-marked]')"), "stopped"
    f.run(f"{Q('[data-segment=low]')}.click()")
    assert f.js(f"{Q('[data-key]')}.textContent") == "In C", f.js(f"{Q('[data-key]')}.textContent")
    f.run(f"{Q('[data-ui=tool-more]')}.click()")
    f.wait(f"{Q('[data-ui=tool-sheet]')}.open", what="More")
    f.run(f"{Q('[data-segment=echo]')}.click()")
    f.no_sideways("More")
    f.shot("more")
    f.run("window.__warmup.destroy()")


if __name__ == "__main__":
    run("tool_warmup_player", journey, sizes=("phone", "se", "desktop"))
