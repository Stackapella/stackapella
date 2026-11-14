"""The free tuner (js/public/tools/tuner.js, /tools/tuner) on its demo page in the tools' brand frame, readings played in
without a mic: Hillel's "Tuner v2" over the competitor review's MUSTs and SHOULDs
(docs/tools/competitors-tuner-metronome-range.md). Listen first; Voice is the start, ±10 cents; 8 cents sharp reads the pill
"In tune" / "Hold it there" with the neighbours either side; 30 cents sharp says "Sharp" / "Sing a little lower ↓", the cents
as ¢, and brightens the note it drifts toward; Voice listening points a bass to Instrument; the sound stopping holds "Last note", the note a button that plays it; stopped reads "Paused"; a hum just over
the room's level is "Too quiet"; Instrument wants ±5 ("Sharp" / "Tune a little lower ↓") and the trace says how much of it
was in tune; the A4 stepper sets 442 (and the presets in More); a B♭ transposition relabels the note; a guitar in Drop D
shows Drop D in the first screen's tuning picker (and Open C picked there moves the strings and Settings) and its strings, a tapped one the target, ticked once in tune; the settings stay after a reload. Both skins
(FLOW_SKINS=game,serious)."""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

CARD = "document.querySelector('[data-tool=tuner]')"
NOTE = "document.querySelector('[data-name]').textContent + document.querySelector('[data-octave]').textContent"
STATUS = "document.querySelector('[data-status-text]').textContent"
HINT = "document.querySelector('[data-status-hint]').textContent.replace(/^· /, '')"


def hz(cents, midi=69, a4=440):
    return a4 * 2 ** ((midi - 69) / 12 + cents / 1200)


def journey(f):
    f.go("/tests/tuner-demo.html?play=1", "window.__tuner")
    f.run("localStorage.removeItem('stackapella.tools.tuner'); window.__old = true; location.reload()")
    f.wait("!window.__old && window.__tuner && document.querySelector('[data-start]')", what="fresh")
    assert f.js("document.querySelector('[data-segment=voice]').getAttribute('aria-pressed')") == "true", "Voice first"
    assert f.js("document.querySelector('[data-start]').textContent") == "Listen"
    assert f.js("document.querySelector('[data-status-hint]').textContent") == "Tap Listen, then play or sing a note"
    f.run("document.querySelector('[data-start]').click()")
    f.wait(f"{CARD}.dataset.state === 'listening' && window.__play", what="listening")
    assert f.js(STATUS) == "Listening..."
    # Voice hears nothing under 60 Hz: while nothing's heard it points a bass player to Instrument (the tools review).
    assert f.js(HINT) == "Tuning a bass or a low string? Pick Instrument.", f.js(HINT)
    f.shot("listening")
    # A voice 8 cents sharp: inside Voice's ±10, after half a second.
    f.run(f"window.__play({hz(8)}, 1.2)")
    f.wait(f"{CARD}.dataset.state === 'in-tune'", what="in tune (voice)")
    assert f.js(NOTE) == "A4", f.js(NOTE)
    assert f.js(STATUS) == "In tune" and f.js(HINT) == "Hold it there"
    assert f.js("document.querySelector('[data-prev]').textContent + ' ' + document.querySelector('[data-next]').textContent") == "G♯ A♯", "the neighbours either side"
    assert f.js("document.querySelector('[data-start]').textContent") == "Stop listening"
    assert f.js("document.querySelector('[data-hz]').textContent").endswith(" Hz")
    assert f.js("!document.querySelector('[data-trail]').hidden"), "the trail shows"
    f.no_sideways("the tuner")
    f.shot("voice-in-tune")
    # Held in tune for 2 s: the moment for the page's free lesson (page.js fills the bar's line), once.
    assert not f.js("window.__lessons"), "not after a moment in tune"
    f.run(f"window.__play({hz(8)}, 2.5)")
    f.wait("window.__lessons === 1", what="held in tune: the lesson moment")
    f.run(f"window.__play({hz(30)}, 1)")
    f.wait(f"{CARD}.dataset.state === 'sharp'", what="sharp")
    assert f.js(STATUS) == "Sharp" and f.js(HINT) == "Sing a little lower ↓", f.js(STATUS)
    assert f.js("document.querySelector('[data-cents]').textContent").endswith("¢"), "cents as ¢"
    assert "text-cream" in f.js("document.querySelector('[data-next]').className"), "the note it drifts toward brightens"
    left = f.js("parseFloat(document.querySelector('[data-needle]').style.left)")
    assert 72 < left < 85, f"the needle at about +30 cents: {left}%"
    # The sound stops: the reading stays, dimmed, as the last note.
    f.run("window.__play(0, 0.6, -80)")
    f.wait(f"{CARD}.dataset.reading === 'held'", what="held")
    assert f.js(STATUS) == "Last note" and f.js(NOTE) == "A4"
    # The note is a button: it says so, and a tap plays it (the tools review, 2026-11-14).
    assert f.js(HINT) == "Tap the note to hear it", f.js(HINT)
    assert f.js("document.querySelector('[data-hear-note]').getAttribute('aria-label')") == "Hear A4"
    f.run("document.querySelector('[data-hear-note]').click()")
    f.shot("last-note")
    # Started in a room humming at -42 dB, a pitch only a little above it is too quiet.
    f.run("document.querySelector('[data-start]').click()")
    f.wait(f"{CARD}.dataset.state === 'paused'", what="stopped")
    assert f.js(STATUS) == "Paused" and f.js("document.querySelector('[data-start]').textContent") == "Listen"
    f.run("document.querySelector('[data-start]').click()")
    f.wait(f"{CARD}.dataset.state === 'listening'", what="listening again")
    f.run("window.__play(0, 1, -42); window.__play(220, 1, -38)")
    f.wait(f"document.querySelector('[data-status-text]').textContent === 'Too quiet, come closer'", what="too quiet")
    # Instrument: ±5, so 8 cents is sharp, not in tune.
    f.run("document.querySelector('[data-segment=instrument]').click()")
    f.wait("document.querySelector('[data-segment=instrument]').getAttribute('aria-pressed') === 'true'", what="instrument")
    f.wait("window.__play", what="listening again")
    f.run(f"window.__play({hz(8)}, 1.2)")
    f.wait(f"{CARD}.dataset.state === 'sharp'", what="8 cents sharp for an instrument")
    assert f.js(STATUS) == "Sharp" and f.js(HINT) == "Tune a little lower ↓", f.js(STATUS) + " / " + f.js(HINT)
    assert f.js("document.querySelector('[data-ui=tool-stat]').textContent").endswith("% in tune"), "the share of the trace in tune"
    # A4 lives in More (Hillel: assume 440): two taps up make 442, said in a chip on the first screen; a preset sets it too.
    assert f.js("document.querySelector('[data-tool-key]').children.length") == 0, "the brand row holds no key control"
    f.run("document.querySelector('[data-ui=tool-more]').click()")
    f.wait("document.querySelector('[data-ui=tool-sheet]').open", what="More open")
    # Hillel's phone checks: no segment's words wrap, and the seven presets share one row.
    tall = f.js("[...document.querySelectorAll('[data-segment]')].filter((b) => b.offsetParent && b.getBoundingClientRect().height > 48).map((b) => b.textContent)")
    assert not tall, f"segments that wrapped: {tall}"
    rows = f.js("new Set([...document.querySelectorAll('[data-preset]')].map((b) => b.offsetTop)).size")
    assert rows == 1, f"the presets on {rows} rows"
    assert f.js("document.querySelector('[data-ui=tuner-reference] [data-step-label]').hidden"), "A4 said once: no caption on the stepper"
    f.run("document.querySelector('[data-ui=tuner-reference] [data-step=\"1\"]').click(); document.querySelector('[data-ui=tuner-reference] [data-step=\"1\"]').click()")
    f.wait("document.querySelector('[data-ui=tuner-reference] [data-step-value]').textContent === '442 Hz'", what="A4 = 442")
    assert "A4 = 442" in f.js("document.querySelector('[data-ui=tuner-summary]').textContent"), "the chip says it"
    f.run("document.querySelector('[data-preset=\"440\"]').click()")
    f.wait("document.querySelector('[data-ui=tuner-reference] [data-step-value]').textContent === '440 Hz'", what="a preset")
    f.run("document.querySelector('[data-preset=\"442\"]').click()")
    f.wait("document.querySelector('[data-ui=tuner-reference] [data-step-value]').textContent === '442 Hz'", what="back to 442")
    f.run(f"window.__play({hz(0, 69, 442)}, 1.2)")
    f.wait(f"{CARD}.dataset.state === 'in-tune'", what="in tune at 442")
    # A B♭ instrument: concert A reads as its B.
    f.run("document.querySelector('[data-key=\"B♭\"]').click()")
    f.run(f"window.__play({hz(0, 69, 442)}, 1)")
    f.wait(f"({NOTE}) === 'B4'", what="B♭ transposition")
    assert "B♭ instrument" in f.js("document.querySelector('[data-ui=tuner-summary]').textContent")
    f.run("document.querySelector('[data-key=\"C\"]').click()")
    # A guitar in Drop D: six strings, the lowest D2; tapping it makes it the target.
    f.run("const s = document.querySelector('[data-instrument]'); s.value = 'guitar'; s.dispatchEvent(new Event('change'))")
    f.run("const t = document.querySelector('[data-tuning]'); t.value = 'dropD'; t.dispatchEvent(new Event('change'))")
    f.wait("document.querySelectorAll('[data-string]').length === 6", what="six strings")
    assert f.js("document.querySelector('[data-string]').textContent") == "D2"
    # The tuning shows, and is picked, on the first screen too (the tools review, 2026-11-14), the same as Settings'.
    assert f.js("!document.querySelector('[data-tuning-quick-field]').hidden && document.querySelector('[data-tuning-quick]').value") == "dropD"
    f.shot("settings")
    f.run("document.querySelector('[data-sheet-done]').click()")
    f.run("document.querySelector('[data-string=\"38\"]').click()")
    f.wait("document.querySelector('[data-string=\"38\"]').getAttribute('aria-pressed') === 'true'", what="string chosen")
    f.wait(f"({NOTE}) === 'D2'", what="the target shown")
    # Tuned once, the string keeps a tick (its own note's 1.9 s first, while the readings are ignored).
    f.run(f"window.__play({hz(0, 38, 442)}, 3.2)")
    f.wait("document.querySelector('[data-string=\"38\"]').textContent === 'D2 ✓'", what="the tuned string ticked")
    f.shot("guitar")
    # Open C from the first screen: the lowest string a C2, and Settings follows.
    f.run("const t = document.querySelector('[data-tuning-quick]'); t.value = 'openC'; t.dispatchEvent(new Event('change'))")
    f.wait("document.querySelector('[data-string]').textContent === 'C2'", what="open C's strings")
    assert f.js("document.querySelector('[data-tuning]').value") == "openC"
    f.run("const t = document.querySelector('[data-tuning-quick]'); t.value = 'dropD'; t.dispatchEvent(new Event('change'))")
    f.no_sideways("the tuner with strings")
    # The settings are kept on this device.
    f.run("window.__tuner.stop(); window.__old = true; location.reload()")
    f.wait("!window.__old && window.__tuner && document.querySelector('[data-ui=tuner-summary]')", what="reloaded")
    summary = f.js("document.querySelector('[data-ui=tuner-summary]').textContent")
    assert f.js("document.querySelector('[data-tuning-quick]').value") == "dropD" and f.js("document.querySelector('[data-ui=tuner-reference] [data-step-value]').textContent") == "442 Hz", summary
    f.run("localStorage.removeItem('stackapella.tools.tuner')")


if __name__ == "__main__":
    run("tool_tuner", journey)
