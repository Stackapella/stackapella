"""Range and registers (docs/courses/range-registers.md), as staff while the course is "soon": the
easy-range slides are trace lanes scored by `span`; before a break is found, a step laid from it says
to find it first; once found, the light-voice and blend steps are laid from it, the blend's siren
scored by `smooth`.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

STORE = "(await import('/js/stores/learning.js'))"
ROUTER = "(await import('/js/router.js'))"
AUDIO = "(await import('/js/audio.js'))"


def open_course(f):
    f.go("/?api=mock&scenario=&as=admin", "document.querySelector('#app header')")
    f.run("localStorage.setItem('stackapella.asks', JSON.stringify({ stopped: ['install'] }));")
    f.go("/learn", "document.querySelector('[data-course-map]')")
    f.wait(f"{STORE}.learning.catalogue.some((c) => c.id === 'range-and-registers')", what="the catalogue")
    f.run(f"""
        const store = {STORE};
        const data = await (await fetch('/course/plus/range/course.json')).json();
        store.learning.catalogue.find((c) => c.id === 'range-and-registers').status = 'live';
        store.learning.courses = {{ ...store.learning.courses, 'range-and-registers': data }};
    """)


def open_step(f, lesson, step, ready):
    f.run(f"sessionStorage.setItem('stackapella.lesson.{lesson}', '{step}')")
    f.run(f"{ROUTER}.navigate('/learn/c/range-and-registers')")
    f.run(f"{ROUTER}.navigate('/learn/{lesson}')")
    f.wait(ready, what=f"{lesson} step {step + 1}")


def journey(f):
    open_course(f)
    f.mic_checked()

    # Lesson 1 (free): the easy-range slide, a trace lane scored by span.
    open_step(f, "range-1-span", 1, "document.querySelector('[data-wave-lane]')")
    assert f.js(f"{STORE}.learning.courses['range-and-registers'].units[0].lessons[0].steps[1].exercise.score") == "span"
    f.no_sideways("the easy-range slide")
    f.shot("easy-range-slide")

    # Sung over loopback (a drone an octave above your note is what the mic hears): span's steady
    # note widens the range kept, clamped to a tone past its top, never by the step's own note.
    f.run(f"""
        const store = {STORE};
        store.learning.progress.home = 57;
        store.learning.progress.range = {{ low: 55, high: 59 }};
        store.learning.courses['range-and-registers'].units[0].lessons[0].steps[2].exercise.drone = {{ step: 12 }};
        sessionStorage.setItem('stackapella.headphones', '1');
    """)
    open_step(f, "range-1-span", 2, "document.querySelector('[data-wave-lane]')")
    f.js(f"{AUDIO}.unlock()")
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    f.click_t("wave.sing")
    f.wait(f"{AUDIO}.engine.playing?.recording", what="recording")
    f.wait("document.querySelector('[data-wave-result] [data-wave-line]')", timeout=40, what="the slide's result")
    f.js(f"{AUDIO}.engine.setLoopback(false)")
    f.wait(f"{STORE}.learning.progress.range.high === 61", what="the range's top raised a tone, to C#4")
    kept = f.js(f"JSON.parse(JSON.stringify({STORE}.learning.progress.range))")
    assert kept["low"] == 55 and kept["high"] == 61, f"widened by a tone at most, never to A4: {kept}"

    # Before a break is found, a step laid from it asks for the sirens first.
    open_step(f, "range-2-around", 1, "document.querySelector('[data-needs-flip]')")

    # With a break kept for the course, the light hoot and the blend's siren are laid from it.
    f.run(f"await {STORE}.setCoursePersonal('range-and-registers', {{ flip: 5 }})")
    open_step(f, "range-3-hoot", 2, "document.querySelector('[data-wave-lane]')")
    assert not f.js("document.querySelector('[data-needs-flip]')"), "the break is known"
    open_step(f, "range-4-blend", 2, "document.querySelector('[data-wave-lane]')")
    f.no_sideways("the blend's siren")
    f.shot("blend-siren")


run("course_range", journey)
