"""The embedded Sing lane (js/embed/lane.js) on a page without the app (web/tests/embed-demo.html), as the
arcade landing's hero mounts it: it loads when shown, plays the take on the audio clock with the
voice's dot live, reports each note hit or missed ("the", sung flat, misses), zooms out and in, and
pauses where it is.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

LANE = "window.__lane"


def journey(f):
    f.go("/tests/embed-demo.html", "window.__lane")
    f.wait(f"{LANE}.duration() > 10", what="the take and its song, loaded when seen")
    f.shot("ready")
    f.run(f"{LANE}.play()")
    f.wait(f"{LANE}.playing() && {LANE}.time() > 1.2", timeout=15, what="playing on the audio clock")
    f.wait("document.querySelector('#lane').dataset.head === 'live'", what="the voice's dot live")
    f.shot("playing")
    f.wait("window.__notes.length >= 7", timeout=20, what="the first line's notes reported")
    notes = f.js("window.__notes.map((n) => n.word + ':' + (n.hit ? 'hit' : 'miss'))")
    print(f"    notes: {notes}")
    assert "the:miss" in notes and sum(1 for n in notes if n.endswith(":miss")) == 1, f"only 'the' should miss: {notes}"
    assert f.js(f"{LANE}.zoom(0)") == 0 and f.js(f"{LANE}.zoom(2)") == 2, "zoom out and in"
    f.shot("zoomed-in")
    f.run(f"{LANE}.pause()")
    held = f.js(f"{LANE}.time()")
    f.js("new Promise((r) => setTimeout(r, 500))")
    assert abs(f.js(f"{LANE}.time()") - held) < 0.01, "time moves while paused"
    # Muted (the landing's game mode opens silent): the picture plays with no AudioContext; a tap
    # brings the sound in from where the picture is, with no jump.
    f.go("/tests/embed-demo.html?muted", "window.__lane")
    f.wait(f"{LANE}.duration() > 10", what="loaded, muted")
    f.run(f"{LANE}.play()")
    f.wait(f"{LANE}.playing() && {LANE}.muted() && {LANE}.time() > 1.5", timeout=15, what="the picture playing muted")
    assert f.js("document.querySelector('#lane').dataset.head") == "live", "no voice dot while muted"
    before = f.js(f"{LANE}.time()")
    f.run(f"{LANE}.unmute()")
    f.wait(f"{LANE}.playing() && !{LANE}.muted()", timeout=10, what="sound on")
    after = f.js(f"{LANE}.time()")
    print(f"    muted at {before:.2f}, with sound at {after:.2f}")
    assert 0 <= after - before < 0.6, f"the time jumped when the sound came in: {before} to {after}"
    f.run(f"{LANE}.pause()")


if __name__ == "__main__":
    run("embed_lane", journey)
