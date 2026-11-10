"""A lesson that needs headphones (`headphones: "needed"`, on the lesson or its course): layered lessons (a partner
line or loop under you) rest everything but your voice through a speaker during a scored take, so the course page
and the lesson's first card say "Best with headphones", and the headphones ask comes firmly ("This lesson needs
headphones"), the speaker still a choice, never a wall. It asks again on such a lesson even when the speaker was
picked earlier in the visit, once a lesson. An in-memory course: the choir course flagged, its first lesson one
play step with a partner line under it.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from course_folk import ROUTER, STORE, open_course  # noqa: E402

COURSE = "choir-part"
LESSON = "chp-1-hear"
LINE = [{"beat": b, "length": 1, "step": s} for b, s in enumerate([0, 2, 4, 2])]
NEEDED = "document.querySelector('[data-headphones-needed]')"


def journey(f):
    open_course(f, COURSE)
    f.run(f"""
        const store = {STORE};
        const course = store.learning.courses['{COURSE}'];
        const notes = {json.dumps(LINE)};
        const step = {{ kind: 'play', text: {{ en: 'Sing your line over the lead.' }}, exercise: {{ tempo: 100, turn: false, notes, with: [{{ notes: notes.map((n) => ({{ ...n, step: n.step + 4 }})), volume: 0.8 }}] }} }};
        course.units[0].lessons[0] = {{ ...course.units[0].lessons[0], steps: [step] }};
        store.learning.courses = {{ ...store.learning.courses, '{COURSE}': {{ ...course, headphones: 'needed' }} }};
    """)
    # The course page says it quietly.
    f.run(f"{ROUTER}.navigate('/learn/c/{COURSE}')")
    f.wait("document.querySelector('[data-best-headphones]')", what="Best with headphones on the course page")
    f.shot("course-page")
    # The lesson's first card says it too, and the ask is firm.
    f.mic_checked()
    f.run("sessionStorage.removeItem('stackapella.headphones'); sessionStorage.removeItem('stackapella.speaker'); sessionStorage.removeItem('stackapella.headphonesAsked'); localStorage.removeItem('stackapella.headphones.never');")
    f.run(f"sessionStorage.setItem('stackapella.lesson.{LESSON}', '0')")
    f.run(f"{ROUTER}.navigate('/learn/{LESSON}')")
    f.wait("document.querySelector('[data-best-headphones]')", what="Best with headphones on the first card")
    f.run("document.querySelector('[data-play-disc]').closest('button').click()")
    f.wait(NEEDED, what="the firm ask")
    text = f.js("document.querySelector('[role=dialog]').innerText")
    assert "needs headphones" in text and "Use the speaker anyway" in text, f"the needed ask says why, and keeps the speaker: {text}"
    f.shot("needed-ask")
    f.click("Use the speaker anyway", "[role=dialog] button")
    f.wait(f"!{NEEDED}", what="the speaker chosen, the take begins")
    # Picked the speaker earlier this visit, on another lesson: a needed lesson asks again, once.
    f.run("sessionStorage.setItem('stackapella.speaker', '1'); sessionStorage.setItem('stackapella.headphonesAsked', 'another-lesson');")
    f.run(f"{ROUTER}.navigate('/learn/c/{COURSE}')")
    f.wait("document.querySelector('[data-course-map]')", what="the course page between")
    f.run(f"{ROUTER}.navigate('/learn/{LESSON}')")
    f.wait("document.querySelector('[data-play-disc]')", what="the play button")
    f.run("document.querySelector('[data-play-disc]').closest('button').click()")
    f.wait(NEEDED, what="asked again on this lesson")
    f.click("Use the speaker anyway", "[role=dialog] button")
    f.wait(f"!{NEEDED}", what="the speaker again")
    assert f.js("sessionStorage.getItem('stackapella.headphonesAsked')") == LESSON, "asked once on this lesson"


if __name__ == "__main__":
    run("headphones_needed", journey)
