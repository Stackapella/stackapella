"""The Ear training course (docs/courses/ear.md), as staff: the course page with its map, every
lesson opening on its Why, a listening round, and a named Bullseye take: the guide gives a note,
you sing it and a leap, and Hear yourself offers Hear it right and your answer over the guide's
note. While the course is still "soon" in the catalogue (hidden even from staff), the flow opens it
in memory as if it were live. Over loopback the mic hears only what plays, so the take sings your
own note over a drone on it. With OUT=<dir> it shoots the course page, lesson 1, a listening round
and the take's result in Warm light and Stage dark at both sizes:

    OUT=~/Code/shots-review/ear PORT=8431 tool/flows.sh course_ear
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import compress  # noqa: E402
from plus_courses import picture, t  # noqa: E402

STORE = "(await import('/js/stores/learning.js'))"
ROUTER = "(await import('/js/router.js'))"
AUDIO = "(await import('/js/audio.js'))"

LESSONS = {
    "ear-1-songs": "You already carry these leaps",
    "ear-1-updown": "Two questions about every new note",
    "ear-1-threefive": "A leap of three is Saints",
    "ear-1-challenge": "Three, four and five, mixed",
    "ear-2-fourfive": "A leap of four is Amazing Grace",
    "ear-2-steps": "A whole step has one piano key between its notes",
    "ear-2-thirds": "A leap of three comes big or small",
    "ear-2-wide": "A leap of six is My Bonnie",
    "ear-2-down": "The same leaps, falling",
    "ear-2-challenge": "Any leap, up or down",
    "ear-3-hear": "A chord's middle note, its third",
    "ear-3-third": "The guide sings so and do",
    "ear-3-tunes": "Lower mi to me and a happy tune turns sad",
    "ear-3-challenge": "The guide sings a chord's root and fifth",
    "ear-4-home": "Most tunes want to end at home",
    "ear-4-root": "Every chord is built on a root",
    "ear-4-challenge": "A bass sings the roots under a tune",
}


def open_ear(f):
    """Staff, with Ear training shown as if it were live (it stays "soon" until every lesson plays)."""
    f.go("/?api=mock&scenario=&as=admin", "document.querySelector('#app header')")
    f.run("localStorage.setItem('stackapella.installDismissed', '1');")
    f.go("/learn", "document.querySelector('[data-course-map]')")
    f.wait(f"{STORE}.learning.catalogue.some((c) => c.id === 'ear')", what="the catalogue")
    f.run(f"""
        const store = {STORE};
        const data = await (await fetch('/course/plus/ear/course.json')).json();
        store.learning.catalogue.find((c) => c.id === 'ear').status = 'live';
        store.learning.courses = {{ ...store.learning.courses, ear: data }};
    """)


def journey(f):
    open_ear(f)
    f.wait("document.querySelector('[data-more-courses] [data-course=ear]')", what="Ear training in More courses")
    f.run(f"{ROUTER}.navigate('/learn/c/ear')")
    f.wait("document.querySelector('[data-up-next]') && document.querySelector('[data-course-map]')", what="Up next and the map")
    f.wait_text("Song starts")
    f.no_sideways("the course page")
    picture(f, "ear-course-page")

    for lesson, headline in LESSONS.items():
        if lesson == "ear-2-fourfive":
            # Each unit opens at two thirds of the stars of the unit before: every lesson sung, with three stars.
            f.run(f"""
                const store = {STORE};
                const lessons = Object.fromEntries(store.learning.courses.ear.units.flatMap((u) => u.lessons).map((l) => [l.id, {{ stars: 3, bestPercent: 95, at: '2026-10-29T10:00:00Z' }}]));
                store.learning.progress.courses = {{ ...(store.learning.progress.courses ?? {{}}), ear: {{ lessons }} }};
            """)
        f.run(f"{ROUTER}.navigate('/learn/{lesson}')")
        f.wait_text(headline)
        f.no_sideways(lesson)
        if lesson == "ear-1-songs":
            picture(f, "ear-lesson-1")

    # A listening round: the clips and the question.
    f.run(f"{ROUTER}.navigate('/learn/ear-1-updown')")
    f.wait_text(LESSONS["ear-1-updown"])
    f.click_t("course.continue")
    f.wait("document.querySelector('[data-choose] [data-clip]')", what="the first round's clip")
    f.wait_text("Does it go up or down?")
    f.no_sideways("a listening round")
    picture(f, "ear-choose")

    # A named take over loopback: your note and the same note again, over a drone on it.
    f.mic_checked()
    f.run(f"{ROUTER}.navigate('/learn/ear-1-songs')")
    f.wait_text(LESSONS["ear-1-songs"])
    f.run(f"""
        const lesson = {STORE}.learning.courses.ear.units[0].lessons[0];
        lesson.steps[1].exercise = {{ tempo: 90, echoes: [{{ call: [0], sing: [0, 0], label: {{ en: 'The same note twice' }} }}], drone: {{ step: 0 }}, named: true, stack: true, generous: true }};
    """)
    f.js(f"{AUDIO}.unlock()")
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    f.click_t("course.continue")
    f.wait("document.querySelector('[data-bullseye-step] [data-bullseye]')", what="the board")
    f.click_t("bullseye.start")
    f.click_t("headphones.on")
    f.wait(f"{AUDIO}.engine.playing?.recording", what="recording the take")
    f.wait_text("The same note twice")
    f.wait("document.querySelector('[data-bullseye-result] [data-bullseye-line]')", timeout=40, what="Hear yourself")
    line = f.js("document.querySelector('[data-bullseye-line]').textContent")
    assert line == t(f, "bullseye.landed", {"inside": 2, "count": 2}), f"both landed in the middle: {line}"
    assert f.js("!!document.querySelector('[data-bullseye-stack]')"), "your answer over the guide's note"
    f.wait_text(t(f, "bullseye.hearRight"))
    f.js(f"{AUDIO}.engine.setLoopback(false)")
    f.no_sideways("the take's result")
    picture(f, "ear-bullseye-result")
    compress()


if __name__ == "__main__":
    run("course_ear", journey)
