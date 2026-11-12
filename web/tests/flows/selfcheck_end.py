"""A lesson that ends on a selfcheck after its scored step keeps the scored step's stars (the courses lead's scoring
audit, voice.md F1): opening the selfcheck cleared the scored result, so Finish said "couldn't hear" with 0 stars, and
Range and registers' units 3 and 4 and Yodeling's 2 to 4 could never open. An in-memory lesson: a scored play sung over
loopback (a partner voice stands in for the singer), then a selfcheck recorded and answered, then Finish.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from course_folk import ROUTER, STORE, open_course  # noqa: E402

COURSE = "choir-part"
LESSON = "chp-1-hear"
AUDIO = "(await import('/js/audio.js'))"
LINE = [{"beat": b, "length": 1, "step": s} for b, s in enumerate([0, 2, 4, 5, 7, 5, 4, 2])]


def lesson(f):
    """A scored play, then a selfcheck: the shape of gospel-runs' gr-2-groups and the others the audit names."""
    f.run(f"""
        const store = {STORE};
        const course = store.learning.courses['{COURSE}'];
        const line = {LINE!r};
        const play = {{ kind: 'play', text: {{ en: 'Sing the line.' }}, exercise: {{ tempo: 120, turn: false, notes: line, with: [{{ notes: line.map((n) => ({{ ...n, vibrato: {{ cents: 0 }} }})), volume: 1 }}] }} }};
        const check = {{ kind: 'selfcheck', text: {{ en: 'Once more, then hear yours.' }}, exercise: {{ tempo: 120, model: {{ notes: line.slice(0, 3) }}, questions: [{{ en: 'Did it stay light?' }}] }} }};
        course.units[0].lessons[0] = {{ ...course.units[0].lessons[0], steps: [play, check] }};
        store.learning.courses = {{ ...store.learning.courses, '{COURSE}': {{ ...course }} }};
    """.replace("'beat'", "beat").replace("'length'", "length").replace("'step'", "step"))


def journey(f):
    open_course(f, COURSE)
    lesson(f)
    f.mic_checked()
    f.js(f"{AUDIO}.unlock()")
    f.run(f"sessionStorage.setItem('stackapella.lesson.{LESSON}', '0')")
    f.run(f"{ROUTER}.navigate('/learn/{LESSON}')")
    f.wait_text("Sing the line.")
    # The scored step, over loopback.
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    # The step's words draw first and its Exercise a beat later (components/lazy-lesson.js): wait for the disc itself.
    f.wait("document.querySelector('[data-play-disc]')", what="the play step's disc")
    f.run("document.querySelector('[data-play-disc]').closest('button').click()")
    if f.js("!!document.querySelector('[role=dialog]')"):
        f.click_t("headphones.on")
    f.wait("document.querySelector('[data-result-heading]')", timeout=40, what="the scored step's result")
    f.click_t("course.continue")
    # The selfcheck: a take of yours (over loopback), then the question answered.
    f.wait("document.querySelector('[data-selfcheck] [data-selfcheck-record]')", what="the selfcheck")
    f.run("document.querySelector('[data-selfcheck-record]').click()")
    if f.js("new Promise((r) => setTimeout(r, 500)).then(() => !!document.querySelector('[role=dialog]'))"):
        f.click_t("headphones.on")
    # The take ends by itself at the line's end (stopped early, it isn't kept).
    f.wait("document.querySelector('[data-selfcheck-questions] [role=radio]')", timeout=20, what="the question, after the take")
    f.run("document.querySelector('[data-selfcheck-questions] [role=radio]').click()")
    f.js(f"{AUDIO}.engine.setLoopback(false)")
    f.wait("!document.querySelector('[data-go-on]').disabled", what="Finish, once the selfcheck is done")
    f.run("document.querySelector('[data-go-on]').click()")
    # The lesson ends with the scored step's stars, never "couldn't hear".
    f.wait(f"{STORE}.learning.progress.courses?.['{COURSE}']?.lessons?.['{LESSON}']?.stars >= 1", timeout=15, what="the lesson's stars kept")
    assert not f.js("!!document.querySelector('#done-title') && document.querySelector('#done-title').textContent.includes(" + repr("hear") + ")"), "the end says it couldn't hear"
    f.shot("lesson-end")


if __name__ == "__main__":
    run("selfcheck_end", journey)
