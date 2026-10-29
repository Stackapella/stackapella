"""The Counterpoint course (docs/courses/counterpoint.md), as staff: the course page with its map,
every unit 1 lesson on its Why, Which voice moved?'s first round, the pedal's lane, and the round itself: you sing Row, Row, Row while three
partner voices (`with`) come in behind you, then Hear it together plays your take with them. While
the course is still "soon" in the catalogue (hidden even from staff), the flow opens it in memory as
if it were live. With OUT=<dir> it shoots the course page, lesson 1, its lane and the round's result
in Warm light and Stage dark at both sizes:

    OUT=~/Code/shots-review/counterpoint PORT=8432 tool/flows.sh course_counterpoint
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
    "cp-1-round": "A round is one tune that fits itself",
    "cp-1-hear": "Your ear can follow two voices at once",
    "cp-1-pedal": "A note held under a moving tune is a pedal",
    "cp-1-ostinato": "A pattern sung over and over is an ostinato",
    "cp-1-challenge": "Under a whole tune: a pattern first",
    "cp-2-follow": "Now you come in second",
    "cp-2-minor": "This tune in minor",
    "cp-2-canon": "A round is a canon that loops back",
    "cp-2-challenge": "The hardest seat in a round",
    "cp-3-call": "In call and response, a leader sings and others answer",
    "cp-3-counter": "Your line keeps falling while the tune turns",
    "cp-3-descant": "A descant floats above the tune",
    "cp-3-partners": "Songs on the same chords, in the same time",
    "cp-3-challenge": "Two verses, two parts",
    "cp-4-consonance": "Some notes sound smooth together, some rub",
    "cp-4-motion": "Two voices can move the same way, opposite ways",
    "cp-4-first": "taught counterpoint in steps he called species",
    "cp-4-second": "Two notes to each cantus note",
    "cp-4-challenge": "Now you sing the cantus",
    "cp-5-yours": "Now you choose",
    "cp-5-hold": "Hold a note into the next chord",
    "cp-5-challenge": "A new cantus, and every note is your choice",
}


def open_counterpoint(f):
    """Staff, with Counterpoint shown as if it were live (it stays "soon" until every lesson plays)."""
    f.go("/?api=mock&scenario=&as=admin", "document.querySelector('#app header')")
    f.run("localStorage.setItem('stackapella.installDismissed', '1');")
    f.go("/learn", "document.querySelector('[data-course-map]')")
    f.wait(f"{STORE}.learning.catalogue.some((c) => c.id === 'counterpoint')", what="the catalogue")
    f.run(f"""
        const store = {STORE};
        const data = await (await fetch('/course/plus/counterpoint/course.json')).json();
        store.learning.catalogue.find((c) => c.id === 'counterpoint').status = 'live';
        store.learning.courses = {{ ...store.learning.courses, counterpoint: data }};
    """)


def journey(f):
    open_counterpoint(f)
    f.wait("document.querySelector('[data-more-courses] [data-course=counterpoint]')", what="Counterpoint in More courses")
    f.run(f"{ROUTER}.navigate('/learn/c/counterpoint')")
    f.wait("document.querySelector('[data-up-next]') && document.querySelector('[data-course-map]')", what="Up next and the map")
    f.wait_text("Be a round")
    f.no_sideways("the Counterpoint course page")
    picture(f, "counterpoint-course-page")

    for lesson, headline in LESSONS.items():
        unit = lesson.split("-")[1]
        if unit != "1" and lesson == next(k for k in LESSONS if k.split("-")[1] == unit):
            # A unit opens on two thirds of the unit before's stars, for staff too: finish it first.
            for done in [k for k in LESSONS if k.split("-")[1] == str(int(unit) - 1)]:
                f.js(f"{STORE}.finishLesson('{done}', {{ percent: 95, stars: 3 }})")
        f.run(f"{ROUTER}.navigate('/learn/{lesson}')")
        f.wait_text(headline)
        f.no_sideways(lesson)
        if lesson == "cp-1-round":
            picture(f, "counterpoint-lesson-1")
        if lesson == "cp-1-hear":
            # Listen and choose: two voices at once, which one held still; the right answer, then the why.
            f.click_t("course.continue")
            f.wait_text("Which voice held still?")
            f.run("document.querySelector('[data-clip=\"0\"] button').click()")  # hear the clip
            f.click("The low one")
            f.wait("document.querySelector('[data-choose-why]')", what="the why after an answer")
            f.wait_text(t(f, "choose.right"))
            f.no_sideways("which voice moved")
            picture(f, "counterpoint-hear-choose")
        if lesson == "cp-2-canon":
            f.click_t("course.continue")
            f.wait_text("The canon's line, at your own pace")
            f.click_t("course.skip")
            f.wait_text("You lead.")
            f.wait("document.querySelector('canvas')", what="the canon's lane")
            picture(f, "counterpoint-canon-lane")
        if lesson == "cp-3-call":
            # The answer first (`answer`), then four calls, each answered in turn (listen-only calls, faint).
            f.click_t("course.continue")
            f.wait_text("then sing the answer")
            f.click_t("course.skip")
            f.wait_text("Four calls, each with its answer")
            f.wait("document.querySelector('canvas')", what="the call and response lane")
            picture(f, "counterpoint-call-lane")
        if lesson == "cp-5-yours":
            # Your choice: every smooth note in your range lights at each cantus note.
            f.click_t("course.continue")
            f.wait_text("A practice pass")
            f.wait("document.querySelector('canvas')", what="the choices lane")
            picture(f, "counterpoint-choices-lane")
        if lesson == "cp-1-pedal":
            f.click_t("course.continue")
            f.wait_text("Find your do, and your so below it")
            f.click_t("course.skip")
            f.wait_text("Hold it four times")
            f.wait("document.querySelector('canvas')", what="the pedal's lane")
            picture(f, "counterpoint-pedal-lane")

    # The round, over loopback (Wait for me skipped),
    # so the mic hears what plays. The three partners sound on the engine as parts of their own.
    f.mic_checked()
    open_counterpoint(f)  # a reload forgets the course shown as live
    f.run(f"{ROUTER}.navigate('/learn/cp-1-round')")
    f.wait_text("A round is one tune that fits itself")
    f.click_t("course.continue")
    f.wait_text("The quick half")
    f.click_t("course.skip")  # Wait for me, skipped: the round is what this flow is about
    f.wait_text("sing it twice through")
    f.js(f"{AUDIO}.unlock()")
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    f.wait("document.querySelector('canvas')", what="the round's lane")
    f.no_sideways("the round's lane")
    picture(f, "counterpoint-round-lane")
    f.click_t("course.tap.sing")
    f.click_t("headphones.on")
    f.wait(f"{AUDIO}.engine.playing?.recording", what="recording the round")
    parts = f.js(f"[...{AUDIO}.engine.parts.keys()].sort().join()")
    assert parts == "ex:drone,ex:with0,ex:with1,ex:with2", f"the drone and three partner voices: {parts}"
    f.wait(f"{AUDIO}.engine.positionSeconds() > 12", what="the second and third voices in")
    picture(f, "counterpoint-round-singing")
    f.wait("document.querySelector('[data-take-result]')", timeout=150, what="the round's result")  # a 56-second take, then scoring
    f.js(f"{AUDIO}.engine.setLoopback(false)")
    f.wait_text(t(f, "results.hearTogether"))
    f.no_sideways("the round's result")
    picture(f, "counterpoint-round-result")
    f.wait_text("another voice's note")  # the pulled line, whatever the count
    picture(f, "counterpoint-round-lines", scroll="window.scrollTo(0, document.body.scrollHeight)")
    # The serious skin: the same measures in plain words, the Own line streak (a game reward) gone.
    f.run("window.stackapellaTheme.choose({ skin: 'serious' })")
    f.wait("document.documentElement.dataset.skin === 'serious'", what="the serious skin")
    f.run("(await import('/js/components/ui.js')).toasts.list.splice(0)")  # a badge earned in the game skin above, still on screen
    f.wait_text("another voice's note")
    assert not f.js("document.body.innerText.includes('Own line')"), "no streak in the serious skin"
    f.shot("counterpoint-round-lines-serious")
    f.run("window.stackapellaTheme.choose({ skin: 'game' })")
    compress()


if __name__ == "__main__":
    run("course_counterpoint", journey)
