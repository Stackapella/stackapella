"""The tap tempo tool for /tools (js/public/tools/tap-tempo.js; docs/tools/competitors-tap-tempo-mic-test-pitch-memory.md
section 1) on the tools' one layout, in the frame (web/tests/tap-tempo-demo.html). Taps on the pad, half a
second apart, read 120, Allegro, steady; the brand row's time halves it; Play in metronome carries ?bpm=;
Escape starts again and the space bar taps; the pad and Play are above the fold at 390 x 844 and 320 x 568.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

TOOL = "document.querySelector('#tool [data-ui=tool]')"
BPM = f"{TOOL}.querySelector('[data-bpm]').textContent"
PILL = f"{TOOL}.querySelector('[data-ui=tool-status]').textContent"
KEY = "document.querySelector('header [data-ui=tool-key]')"


def journey(f):
    f.go("/tests/tap-tempo-demo.html", f"window.__tool && {KEY}")
    f.no_sideways("the tap tempo tool")
    assert f.js(f"{TOOL}.querySelector('[data-pad]').getBoundingClientRect().height >= 60"), "the pad has room"
    assert f.js(f"{TOOL}.querySelector('[data-ui=tool-primary]').getBoundingClientRect().bottom <= innerHeight + 1"), "Play above the fold"
    f.shot("ready")
    # Taps on the pad itself, half a second apart (pointerdown carries the time).
    f.run(f"""const pad = {TOOL}.querySelector('[data-pad]');
        for (let i = 0; i < 8; i++) {{
            pad.dispatchEvent(new PointerEvent('pointerdown', {{ bubbles: true }}));
            await new Promise((r) => setTimeout(r, 500));
        }}""")
    bpm = int(f.js(BPM))
    assert 115 <= bpm <= 125, bpm
    # The flow's taps run on page timers, so a little late: 115 to 125 is Moderato or Allegro.
    assert "Moderato" in f.js(PILL) or "Allegro" in f.js(PILL), f.js(PILL)
    f.no_sideways("the reading")
    f.shot("tapped")
    f.run(f"{TOOL}.querySelector('[data-ui=tool-primary]').click()")
    assert f.js("window.__went").startswith("/tools/metronome?bpm="), "the handoff"
    f.run(f"{KEY}.querySelector('[data-step=\"-1\"]').click()")
    assert 57 <= int(f.js(BPM)) <= 63, "half"
    # Escape starts again; the space bar taps.
    f.run("document.body.dispatchEvent(new KeyboardEvent('keydown', { code: 'Escape', key: 'Escape', bubbles: true }))")
    assert f.js(BPM) == "–", "Escape: " + f.js(BPM)
    f.run("""for (let i = 0; i < 4; i++) {
        document.body.dispatchEvent(new KeyboardEvent('keydown', { code: 'Space', key: ' ', bubbles: true }));
        await new Promise((r) => setTimeout(r, 400));
    }""")
    assert 140 <= int(f.js(BPM)) <= 160, f.js(BPM)
    f.run(f"{TOOL}.querySelector('[data-ui=tool-more]').click()")
    f.wait(f"{TOOL}.querySelector('[data-ui=tool-sheet]').open", what="the sheet")
    f.no_sideways("More")
    f.shot("more")
    f.run("window.__tool.stop()")


if __name__ == "__main__":
    run("tool_tap_tempo", journey, sizes=("phone", "se", "desktop"))
