"""Live looping's layers (js/under-mix.js; docs/courses/live-looping.md, "The engine piece"), ahead of the
content: an in-memory lesson of two play steps. The first is sung over loopback (a partner voice stands in
for the singer) and kept; the second has `under: { steps: [0], loop: 4 }`, so that take plays beneath it
from beat 0, cut to one bar and repeated, as one part on the audio clock. Its Hear it together carries the
layer too.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from course_folk import ROUTER, STORE, open_course  # noqa: E402

COURSE = "choir-part"
LESSON = "chp-1-hear"
AUDIO = "(await import('/js/audio.js'))"
EN = lambda text: {"en": text}  # noqa: E731
LINE = [{"beat": b, "length": 1, "step": s} for b, s in enumerate([0, 2, 4, 5, 7, 5, 4, 2])]


def lesson(f):
    """Two play steps over a drone-free line: a take, then that take looped beneath the next."""
    f.run(f"""
        const store = {STORE};
        const course = store.learning.courses['{COURSE}'];
        const line = {LINE!r};
        const layer = {{ kind: 'play', text: {{ en: 'Lay down the first loop.' }}, exercise: {{ tempo: 120, turn: false, notes: line, with: [{{ notes: line.map((n) => ({{ ...n, vibrato: {{ cents: 0 }} }})), volume: 1 }}] }} }};
        const over = {{ kind: 'play', text: {{ en: 'Now sing over your loop.' }}, exercise: {{ tempo: 120, turn: false, notes: line, under: {{ steps: [0], loop: 4 }} }} }};
        // Then a turn after a listening round (`turn` on), sung through a speaker: the layer plays until the turn, then rests.
        const echo = {{ kind: 'play', text: {{ en: 'Hear the loop, then sing your line.' }}, exercise: {{ tempo: 120, notes: line, under: {{ steps: [0], loop: 4 }} }} }};
        course.units[0].lessons[0] = {{ ...course.units[0].lessons[0], steps: [layer, over, echo] }};
        store.learning.courses = {{ ...store.learning.courses, '{COURSE}': {{ ...course }} }};
    """.replace("'beat'", "beat").replace("'length'", "length").replace("'step'", "step"))


def sing(f):
    """Sing the step's line over loopback, through headphones, and wait for its result."""
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    f.wait("document.querySelector('[data-play-disc]')", what="the step's Start (its exercise loads after the step's words)")
    f.run("document.querySelector('[data-play-disc]').closest('button').click()")
    if f.js("!!document.querySelector('[role=dialog]')"):
        f.click_t("headphones.on")


def journey(f):
    open_course(f, COURSE)
    lesson(f)
    f.mic_checked()
    f.js(f"{AUDIO}.unlock()")
    f.run(f"sessionStorage.setItem('stackapella.lesson.{LESSON}', '0')")
    f.run(f"{ROUTER}.navigate('/learn/{LESSON}')")
    f.wait_text("Lay down the first loop.")
    sing(f)
    f.wait("document.querySelector('[data-result-heading]')", timeout=40, what="the first take's result")
    f.js(f"{AUDIO}.engine.setLoopback(false)")
    f.click_t("course.continue")
    f.wait_text("Now sing over your loop.")
    sing(f)
    # The layer: one part from beat 0 to the line's end (8 beats at 120 bpm and its tail), its first bar (2 s) repeated.
    f.wait(f"{AUDIO}.engine.parts.get('ex:under')", timeout=10, what="the first take beneath, as a part")
    under = f.js(f"""(async () => {{
        const e = {AUDIO}.engine; const p = e.parts.get('ex:under'); const d = p.buffer.getChannelData(0); const sr = p.buffer.sampleRate;
        const loop = Math.round(2 * sr); let same = 0, loud = 0;
        for (let i = 0; i < loop; i += 97) {{ if (Math.abs(d[i] - d[i + loop]) < 1e-6) same++; if (Math.abs(d[i]) > 0.01) loud++; }}
        return {{ seconds: p.buffer.duration, offset: p.offsetSeconds, same, checked: Math.ceil(loop / 97), loud }};
    }})()""")
    assert 4 <= under["seconds"] <= 4.5 and under["offset"] == 0, f"the layer from beat 0 to the line's end (and its tail): {under}"
    assert under["same"] == under["checked"], f"the second bar repeats the first, sample for sample: {under}"
    assert under["loud"] > under["checked"] / 4, f"the layer has the first take's voice in it: {under}"
    f.wait("document.querySelector('[data-result-heading]')", timeout=40, what="the second take's result")
    f.js(f"{AUDIO}.engine.setLoopback(false)")
    f.shot("over-the-loop")
    # Through a speaker, a step with a listening round: the layer plays until the turn, then rests (fix 15; it used to
    # play nothing at all through a speaker).
    f.click_t("course.continue")
    f.wait_text("Hear the loop, then sing your line.")
    f.run("sessionStorage.removeItem('stackapella.headphones'); sessionStorage.setItem('stackapella.speaker', '1'); localStorage.removeItem('stackapella.headphones.never');")
    f.wait("document.querySelector('[data-play-disc]')", what="the step's Start (its exercise loads after the step's words)")
    f.run("document.querySelector('[data-play-disc]').closest('button').click()")
    f.wait(f"{AUDIO}.engine.parts.get('ex:under')", timeout=10, what="the layer under the listening round, through a speaker")
    until = f.js(f"(async () => {{ const p = {AUDIO}.engine.parts.get('ex:under'); return p.buffer.duration; }})()")
    # 8 beats heard, a beat's gap, then the turn: 9 beats at 120 bpm, 4.5 s.
    assert abs(until - 4.5) < 0.05, f"the layer stops at the turn through a speaker: {until} s"


if __name__ == "__main__":
    run("live_loop", journey)
