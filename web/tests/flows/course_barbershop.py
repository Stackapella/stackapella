"""The Barbershop and tags course (docs/courses/barbershop.md), as staff: the course page with its
map, every lesson opening on its Why, a listening round, and a ring take: you hold mi in a chord and
the result says how close it came to ringing. While the course is still "soon" in the catalogue
(hidden even from staff), the flow opens it in memory as if it were live. Over loopback the mic
hears only what plays, so the take's guide sings your own pure mi, which rings. With OUT=<dir> it
shoots the course page, lesson 1, a listening round and the take's result in Warm light and Stage
dark at both sizes:

    OUT=~/Code/shots-review/barbershop PORT=8431 tool/flows.sh course_barbershop
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
    "bb-1-ring": "A barbershop chord rings when every voice is tuned",
    "bb-1-straight": "A chord rings on straight tones",
    "bb-1-out": "One voice out of tune makes the whole chord beat",
    "bb-1-challenge": "One chord, three seats",
    "bb-2-octave": "Build a chord from its floor",
    "bb-2-hunt": "Start on the piano's third and slide down",
    "bb-2-seventh": "The barbershop seventh has the dominant seventh's sound",
    "bb-2-challenge": "Your do is a different pitch as a root",
    "bb-3-lead": "The lead sings the tune",
    "bb-3-bass": "The bass is the floor",
    "bb-3-tenor": "The tenor floats above the lead",
    "bb-3-bari": "The baritone sings the note the chord still needs",
    "bb-3-challenge": "A quartet is four seats",
    "bb-4-swipe": "A swipe is two or more chords",
    "bb-4-post": "A post is one note held still",
    "bb-4-challenge": "A tag is the big finish",
}


def open_barbershop(f):
    """Staff, with Barbershop and tags shown as if it were live (it stays "soon" until every lesson plays)."""
    f.go("/?api=mock&scenario=&as=admin", "document.querySelector('#app header')")
    f.run("localStorage.setItem('stackapella.installDismissed', '1');")
    f.go("/learn", "document.querySelector('[data-course-map]')")
    f.wait(f"{STORE}.learning.catalogue.some((c) => c.id === 'barbershop')", what="the catalogue")
    f.run(f"""
        const store = {STORE};
        const data = await (await fetch('/course/plus/barbershop/course.json')).json();
        store.learning.catalogue.find((c) => c.id === 'barbershop').status = 'live';
        store.learning.courses = {{ ...store.learning.courses, barbershop: data }};
    """)


def journey(f):
    open_barbershop(f)
    f.wait("document.querySelector('[data-more-courses] [data-course=barbershop]')", what="Barbershop in More courses")
    f.run(f"{ROUTER}.navigate('/learn/c/barbershop')")
    f.wait("document.querySelector('[data-up-next]') && document.querySelector('[data-course-map]')", what="Up next and the map")
    f.wait_text("A chord that rings")
    f.no_sideways("the course page")
    picture(f, "barbershop-course-page")

    for lesson, headline in LESSONS.items():
        if lesson == "bb-2-octave":
            # Each unit opens at two thirds of the stars of the unit before: every lesson sung, with three stars.
            f.run(f"""
                const store = {STORE};
                const lessons = Object.fromEntries(store.learning.courses.barbershop.units.flatMap((u) => u.lessons).map((l) => [l.id, {{ stars: 3, bestPercent: 95, at: '2026-10-29T10:00:00Z' }}]));
                store.learning.progress.courses = {{ ...(store.learning.progress.courses ?? {{}}), barbershop: {{ lessons }} }};
            """)
        f.run(f"{ROUTER}.navigate('/learn/{lesson}')")
        f.wait_text(headline)
        f.no_sideways(lesson)
        if lesson == "bb-1-ring":
            picture(f, "barbershop-lesson-1")

    # The listening round: which chord rings?
    f.run(f"{ROUTER}.navigate('/learn/bb-1-ring')")
    f.wait_text(LESSONS["bb-1-ring"])
    f.click_t("course.continue")
    f.wait("document.querySelector('[data-choose] [data-clip]')", what="the first round's clips")
    f.wait_text("Which one rings?")
    f.no_sideways("a listening round")
    picture(f, "barbershop-choose")

    # A ring take over loopback: the guide sings your own pure mi, so it rings.
    f.mic_checked()
    f.run("sessionStorage.setItem('stackapella.lesson.bb-1-ring', '3')")
    f.run(f"""
        const lesson = {STORE}.learning.courses.barbershop.units[0].lessons[0];
        lesson.steps[3].exercise = {{ ...lesson.steps[3].exercise, with: [{{ notes: [{{ beat: 0, length: 5.5, step: 0, cents: -14, vibrato: {{ cents: 0 }} }}], volume: 1 }}] }};
    """)
    f.run(f"{ROUTER}.navigate('/learn/c/barbershop')")
    f.run(f"{ROUTER}.navigate('/learn/bb-1-ring')")
    f.wait_text("Now hold mi for four beats")
    f.js(f"{AUDIO}.unlock()")
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    f.run("document.querySelector('.glass-bubble-solid.size-20').closest('button').click()")
    f.click_t("headphones.on")
    f.wait(f"{AUDIO}.engine.playing?.recording", what="recording the take")
    f.wait("document.querySelector('[data-measured-line]')", timeout=40, what="the ring line")
    line = f.js("document.querySelector('[data-measured-line]').textContent")
    assert line.startswith("Ringing"), f"the guide's own pure mi rings: {line}"
    f.js(f"{AUDIO}.engine.setLoopback(false)")
    f.no_sideways("the take's result")
    picture(f, "barbershop-ring-result")
    compress()


if __name__ == "__main__":
    run("course_barbershop", journey)
