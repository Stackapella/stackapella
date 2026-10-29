"""The Improvising a harmony course (docs/courses/improvising-harmony.md), as staff: the course page with
its map, every lesson on its Why, and lesson 1's part above: the guide sings Michael, Row the Boat
Ashore while you sing the part above it, every note lit, then Hear it together. While the course is
still "soon" in the catalogue, the flow opens it in memory as if it were live. With OUT=<dir> it
shoots the course page, lesson 1, the lane and the result in Warm light and Stage dark:

    OUT=~/Code/shots-review/improvising PORT=8432 tool/flows.sh course_improvising
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
    "imp-1-third": "The simplest harmony",
    "imp-1-switch": "A third above switches between major and minor t",
    "imp-1-fourth": "Hear why the part goes one note higher over so",
    "imp-1-ear": "A harmony makes chords with the tune; a copy jus",
    "imp-1-challenge": "A tune you know less",
    "imp-2-below": "A third above, an octave down, is a sixth below",
    "imp-2-hold": "Lost? Hold your note until it rubs, then step",
    "imp-2-echo": "Hear a line once, then harmonise it as it comes ",
    "imp-2-challenge": "Steal Away, the spiritual's refrain",
    "imp-3-chord": "A basic chord, a triad, has three notes",
    "imp-3-two-ways": "Two ways to make a part your own",
    "imp-3-challenge": "Swing Low, Sweet Chariot's chorus, your own harm",
    "imp-4-hints": "Fewer lights",
    "imp-4-free": "No lights at all",
    "imp-4-high": "The tenor, a part above the lead, is part of blu",
    "imp-4-challenge": "Red River Valley, a tune new to this course",
}


def open_improvising(f):
    """Staff, with Improvising a harmony shown as if it were live (it stays "soon" until every lesson plays)."""
    f.go("/?api=mock&scenario=&as=admin", "document.querySelector('#app header')")
    f.run("localStorage.setItem('stackapella.installDismissed', '1');")
    f.go("/learn", "document.querySelector('[data-course-map]')")
    f.wait(f"{STORE}.learning.catalogue.some((c) => c.id === 'improvising-harmony')", what="the catalogue")
    f.run(f"""
        const store = {STORE};
        const data = await (await fetch('/course/plus/improvising-harmony/course.json')).json();
        store.learning.catalogue.find((c) => c.id === 'improvising-harmony').status = 'live';
        store.learning.courses = {{ ...store.learning.courses, 'improvising-harmony': data }};
    """)


def journey(f):
    open_improvising(f)
    f.wait("document.querySelector('[data-more-courses] [data-course=improvising-harmony]')", what="Improvising in More courses")
    f.run(f"{ROUTER}.navigate('/learn/c/improvising-harmony')")
    f.wait("document.querySelector('[data-up-next]') && document.querySelector('[data-course-map]')", what="Up next and the map")
    f.wait_text("A part above")
    f.no_sideways("the Improvising course page")
    picture(f, "improvising-course-page")

    for lesson, headline in LESSONS.items():
        unit = lesson.split("-")[1]
        if unit != "1" and lesson == next(k for k in LESSONS if k.split("-")[1] == unit):
            # A unit opens on two thirds of the unit before's stars, for staff too: finish it first.
            for done in [k for k in LESSONS if k.split("-")[1] == str(int(unit) - 1)]:
                f.js(f"{STORE}.finishLesson('{done}', {{ percent: 95, stars: 3 }})")
        f.run(f"{ROUTER}.navigate('/learn/{lesson}')")
        f.wait_text(headline)
        f.no_sideways(lesson)
        if lesson == "imp-1-third":
            picture(f, "improvising-lesson-1")
        if lesson == "imp-1-switch":
            f.click_t("course.continue")
            f.wait_text("Over do, which third fits the key?")
            f.no_sideways("which third fits")
            picture(f, "improvising-switch-choose")
        if lesson == "imp-1-ear":
            # The lights fade: the second half of the part's bricks faint, with no names.
            f.click_t("course.continue")
            f.wait_text("Is the second voice singing a harmony")
            f.click_t("course.skip")
            f.wait_text("the lights fade")
            f.wait("document.querySelector('canvas')", what="the fading lane")
            picture(f, "improvising-fade-lane")
        if lesson == "imp-4-free":
            # Lights off: no bricks of yours on the lane, the tune faint, the rows still named.
            f.click_t("course.continue")
            f.wait_text("lights off")
            f.wait("document.querySelector('canvas')", what="the lights-off lane")
            picture(f, "improvising-lights-off")

    # Lesson 1's part above, over loopback: the tune sounds as a partner line, with the chords.
    f.mic_checked()
    open_improvising(f)
    f.run(f"{ROUTER}.navigate('/learn/imp-1-third')")
    f.wait_text("The simplest harmony")
    f.click_t("course.continue")
    f.wait_text("First sing the tune itself")
    f.click_t("course.skip")
    f.wait_text("Now hold the harmony on the long notes")
    f.click_t("course.skip")
    f.wait_text("Now the part above")
    f.js(f"{AUDIO}.unlock()")
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    f.wait("document.querySelector('canvas')", what="the part above's lane")
    picture(f, "improvising-part-lane")
    f.click_t("course.tap.sing")
    f.click_t("headphones.on")
    f.wait(f"{AUDIO}.engine.playing?.recording", what="recording the part above")
    parts = f.js(f"[...{AUDIO}.engine.parts.keys()].sort().join()")
    assert parts == "ex:chords,ex:with0", f"the chords and the tune: {parts}"
    f.wait("document.querySelector('[data-take-result]')", timeout=90, what="the result")
    f.js(f"{AUDIO}.engine.setLoopback(false)")
    f.wait_text(t(f, "results.hearTogether"))
    f.no_sideways("the part above's result")
    picture(f, "improvising-part-result")
    # The serious skin: lesson 1's result in plain words, and no star words in the course's own.
    f.run("window.stackapellaTheme.choose({ skin: 'serious' })")
    f.wait("document.documentElement.dataset.skin === 'serious'", what="the serious skin")
    f.run("(await import('/js/components/ui.js')).toasts.list.splice(0)")  # a badge earned in the game skin above, still on screen
    f.wait_text("Scored on your harmony's notes on pitch")
    f.shot("improvising-part-result-serious")
    f.run("window.stackapellaTheme.choose({ skin: 'game' })")
    compress()


if __name__ == "__main__":
    run("course_improvising", journey)
