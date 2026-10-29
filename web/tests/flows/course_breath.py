"""The Breath and stamina course (docs/courses/breath.md), as staff: the course page with its map,
every lesson opening on its Why, and a long note: the breath bar fills as you hold, the take ends
when you stop, and Hear yourself says how it ended. While the course is still "soon" in the
catalogue (hidden even from staff), the flow opens it in memory as if it were live. Over loopback
the mic hears the drone on your own note, a perfectly steady hold, so the step is shortened to
three seconds. With OUT=<dir> it shoots the course page, lesson 1, the bar and its result in Warm
light and Stage dark at both sizes:

    OUT=~/Code/shots-review/breath PORT=8431 tool/flows.sh course_breath
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
    "br-1-long": "A long note isn't about big lungs",
    "br-1-wide": "Good support keeps your ribs from closing too fast",
    "br-1-song": "In many songs, the long notes come",
    "br-1-challenge": "A little longer than your first hold",
    "br-2-buzz": "A buzz shows whether your air comes out evenly",
    "br-2-hill": "The air stays even while the pitch moves",
    "br-2-end": "The end of a breath lets pitch fall",
    "br-2-challenge": "Three long holds that stack into a chord",
    "br-3-onset": "Breathe in, then let the breath turn straight into the note",
    "br-3-bounce": "Short notes start with a small bounce",
    "br-3-challenge": "The French tune known in English as Twinkle",
    "br-4-catch": "The breath comes out of the last note",
    "br-4-plan": "Breathe where the music lets you",
    "br-4-turns": "A choir holds a long chord by breathing",
    "br-4-challenge": "Simple Gifts' first verse",
}


def open_breath(f):
    """Staff, with Breath and stamina shown as if it were live (it stays "soon" until every lesson plays)."""
    f.go("/?api=mock&scenario=&as=admin", "document.querySelector('#app header')")
    f.run("localStorage.setItem('stackapella.installDismissed', '1');")
    f.go("/learn", "document.querySelector('[data-course-map]')")
    f.wait(f"{STORE}.learning.catalogue.some((c) => c.id === 'breath')", what="the catalogue")
    f.run(f"""
        const store = {STORE};
        const data = await (await fetch('/course/plus/breath/course.json')).json();
        store.learning.catalogue.find((c) => c.id === 'breath').status = 'live';
        store.learning.courses = {{ ...store.learning.courses, breath: data }};
    """)


def journey(f):
    open_breath(f)
    f.wait("document.querySelector('[data-more-courses] [data-course=breath]')", what="Breath and stamina in More courses")
    f.run(f"{ROUTER}.navigate('/learn/c/breath')")
    f.wait("document.querySelector('[data-up-next]') && document.querySelector('[data-course-map]')", what="Up next and the map")
    f.wait_text("The long note")
    f.no_sideways("the course page")
    picture(f, "breath-course-page")

    for lesson, headline in LESSONS.items():
        if lesson == "br-2-buzz":
            # Each unit opens at two thirds of the stars of the unit before: every lesson sung, with three stars.
            f.run(f"""
                const store = {STORE};
                const lessons = Object.fromEntries(store.learning.courses.breath.units.flatMap((u) => u.lessons).map((l) => [l.id, {{ stars: 3, bestPercent: 95, at: '2026-10-29T10:00:00Z' }}]));
                store.learning.progress.courses = {{ ...(store.learning.progress.courses ?? {{}}), breath: {{ lessons }} }};
            """)
        f.run(f"{ROUTER}.navigate('/learn/{lesson}')")
        f.wait_text(headline)
        f.no_sideways(lesson)
        if lesson == "br-1-long":
            picture(f, "breath-lesson-1")

    # The long note over loopback: the drone on your note is a steady hold; three seconds of it.
    f.mic_checked()
    f.run(f"{ROUTER}.navigate('/learn/br-1-long')")
    f.wait_text(LESSONS["br-1-long"])
    f.run(f"""
        const lesson = {STORE}.learning.courses.breath.units[0].lessons[0];
        lesson.steps[2].exercise = {{ ...lesson.steps[2].exercise, max: 3, thenAndNow: 'breath.first' }};
    """)
    f.js(f"{AUDIO}.unlock()")
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    f.click_t("course.continue")
    f.wait_text("Before your first hold")  # the safety card before the first hold
    f.click_t("course.continue")
    f.wait("document.querySelector('[data-breath-hold] [data-breath-bar]')", what="the breath bar")
    f.wait_text(t(f, "breathhold.ready"))
    f.no_sideways("the breath bar")
    picture(f, "breath-bar")
    f.click_t("breathhold.start")
    f.click_t("headphones.on")
    f.wait(f"{AUDIO}.engine.playing?.recording", what="recording the hold")
    f.wait("document.querySelector('[data-breath-result] [data-breath-line]')", timeout=40, what="Hear yourself, with how the hold ended")
    line = f.js("document.querySelector('[data-breath-line]').textContent")
    assert line.startswith("Steady to the end"), f"the drone's hold ended steady: {line}"
    f.wait("document.querySelector('[data-then-and-now]')?.textContent.includes('Then:')", what="then and now, the first hold kept on this device")
    assert f.js("!document.querySelector('.sticky button:last-child').disabled"), "Continue once it's held"
    f.js(f"{AUDIO}.engine.setLoopback(false)")
    f.no_sideways("the hold's result")
    picture(f, "breath-bar-result")
    compress()


if __name__ == "__main__":
    run("course_breath", journey)
