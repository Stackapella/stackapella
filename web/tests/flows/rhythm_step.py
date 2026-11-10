"""A play step's `rhythm` (docs/play-timing-plan.md): an in-memory groove step with `rhythm: true`, sung over loopback
(a partner voice stands in for the singer, on the beat), scores pitch and timing, and its result says both, as the
stave's in-time lines do. A step without `rhythm` is scored on pitch alone, as every course flow checks.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from course_folk import ROUTER, STORE, open_course  # noqa: E402

COURSE = "choir-part"
LESSON = "chp-1-hear"
AUDIO = "(await import('/js/audio.js'))"
I18N = "(await import('/js/i18n.js'))"
GROOVE = [{"beat": b, "length": 1, "step": s} for b, s in enumerate([0, 7, 0, 7, 5, 4, 2, 0])]


def journey(f):
    open_course(f, COURSE)
    f.run(f"""
        const store = {STORE};
        const course = store.learning.courses['{COURSE}'];
        const notes = {json.dumps(GROOVE)};
        const step = {{ kind: 'play', text: {{ en: 'Sing the groove on the beat.' }}, exercise: {{ tempo: 100, turn: false, rhythm: true, notes, with: [{{ notes: notes.map((n) => ({{ ...n, vibrato: {{ cents: 0 }} }})), volume: 1 }}] }} }};
        course.units[0].lessons[0] = {{ ...course.units[0].lessons[0], steps: [step] }};
        store.learning.courses = {{ ...store.learning.courses, '{COURSE}': {{ ...course }} }};
    """)
    f.mic_checked()
    f.js(f"{AUDIO}.unlock()")
    f.run(f"sessionStorage.setItem('stackapella.lesson.{LESSON}', '0')")
    f.run(f"{ROUTER}.navigate('/learn/{LESSON}')")
    f.wait_text("Sing the groove on the beat.")
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    f.run("document.querySelector('[data-play-disc]').closest('button').click()")
    if f.js("!!document.querySelector('[role=dialog]')"):
        f.click_t("headphones.on")
    f.wait("document.querySelector('[data-result-heading]')", timeout=40, what="the result")
    f.js(f"{AUDIO}.engine.setLoopback(false)")
    on_time = f.js(f"{I18N}.t('course.read.measured', {{ pitch: 0, timing: 0 }}).split('0%')[2]")
    f.wait(f"document.body.innerText.includes({json.dumps(on_time.strip())})", what="the result says pitch and timing")
    # The percent tile names the mix too (game mode's only name for it: its heading is a word), never "on pitch".
    stat = f.js(f"{I18N}.t('course.rhythm.stat')")
    caption = f.js("document.querySelector('[data-stat-percent] dd span:last-child')?.textContent ?? ''")
    assert caption == stat, f"the percent tile says {caption!r}, not {stat!r}"
    f.shot("result")


if __name__ == "__main__":
    run("rhythm_step", journey)
