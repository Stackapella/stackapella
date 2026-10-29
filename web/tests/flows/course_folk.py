"""The Folk and early music course (docs/courses/folk.md), as staff: the course page with its map,
every lesson opening on its Why, lesson 1's exercise lane, and the listening rounds (Ring with the drone,
Name that mode, Spot the ornament). While the
course is still "soon" in the catalogue (hidden even from staff), the flow opens it in memory as if
it were live. With OUT=<dir> it shoots the course page, lesson 1 and its lane in Warm light and Stage
dark at both sizes:

    OUT=~/Code/shots-review/folk PORT=8300 tool/flows.sh course_folk
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import compress  # noqa: E402
from plus_courses import picture, t  # noqa: E402

STORE = "(await import('/js/stores/learning.js'))"
ROUTER = "(await import('/js/router.js'))"

LESSONS = {
    "folk-1-crew": "Sing it strong, not loud, over a drone",
    "folk-1-ring": "A note a little out of tune beats against the drone",
    "folk-1-tell": "A ballad is a story",
    "folk-1-challenge": "The whole first verse, on your own over the drone",
    "folk-2-dorian": "You've sung this already",
    "folk-2-mixo": "Old Joe Clark is a major tune with one note lower",
    "folk-2-name": "Two short tunes over the same drone",
    "folk-2-challenge": "Drunken Sailor again, the whole verse",
    "folk-3-flick": "Folk singers decorate a note with a flick",
    "folk-3-turn": "A turn wraps around a note",
    "folk-3-spot": "A good ornament is small",
    "folk-3-challenge": "The whole verse, a flick on",
    "folk-4-chant": "About a thousand years ago, Guido of Arezzo",
    "folk-4-lining": "a leader sang each line and everyone sang it back",
    "folk-4-alone": "Many ballads were sung by one voice",
    "folk-4-challenge": "Back to the first song you sang here",
}


def open_course(f, course):
    """Staff, with a course shown as if it were live (it stays "soon" until every lesson plays)."""
    f.go("/?api=mock&scenario=&as=admin", "document.querySelector('#app header')")
    f.run("localStorage.setItem('stackapella.installDismissed', '1');")
    f.go("/learn", "document.querySelector('[data-course-map]')")
    f.wait(f"{STORE}.learning.catalogue.some((c) => c.id === '{course}')", what="the catalogue")
    f.run(f"""
        const store = {STORE};
        const data = await (await fetch('/course/plus/{course}/course.json')).json();
        store.learning.catalogue.find((c) => c.id === '{course}').status = 'live';
        store.learning.courses = {{ ...store.learning.courses, '{course}': data }};
    """)


def journey(f):
    open_course(f, 'folk')
    f.wait("document.querySelector('[data-more-courses] [data-course=folk]')", what="Folk in More courses")
    f.run(f"{ROUTER}.navigate('/learn/c/folk')")
    f.wait("document.querySelector('[data-up-next]') && document.querySelector('[data-course-map]')", what="Up next and the map")
    f.wait_text("Join the crew")
    f.no_sideways("the Folk course page")
    picture(f, "folk-course-page")

    for lesson, headline in LESSONS.items():
        unit = int(lesson.split("-")[1])
        if lesson in ("folk-2-dorian", "folk-3-flick", "folk-4-chant"):
            # A unit opens on two thirds of the unit before's stars, for staff too: finish it first.
            for done in [k for k in LESSONS if k.startswith(f"folk-{unit - 1}-")]:
                f.js(f"{STORE}.finishLesson('{done}', {{ percent: 95, stars: 3 }})")
        f.run(f"{ROUTER}.navigate('/learn/{lesson}')")
        f.wait_text(headline)
        f.no_sideways(lesson)
        if lesson == "folk-1-crew":
            picture(f, "folk-lesson-1")
            f.click_t("course.continue")
            f.wait("document.querySelector('canvas')", what="lesson 1's try, the lane")
            f.wait_text("The first line, at your own pace")
            f.no_sideways("lesson 1's lane")
            picture(f, "folk-lesson-1-lane")
        if lesson == "folk-1-ring":
            # Listen and choose: which clip rings with the drone; the right one, then the why.
            f.click_t("course.continue")
            f.wait("document.querySelector('[data-choose] [data-clip=\"1\"]')", what="the ring rounds")
            f.wait_text("Which one rings with the drone?")
            f.click(t(f, "choose.this"), "[data-choose] [data-clip='0'] button")
            f.wait("document.querySelector('[data-choose-why]')", what="the why after an answer")
            f.wait_text(t(f, "choose.right"))
            f.no_sideways("the ring rounds")
            picture(f, "folk-ring-choose")
        if lesson == "folk-2-name":
            f.click_t("course.continue")
            f.wait_text("First sing each")
            f.click_t("course.skip")  # the two modes sung first, then the rounds
            f.wait_text("And the Mixolydian")
            f.click_t("course.skip")
            f.wait_text("Which one has the bright note?")
            f.no_sideways("Name that mode")
            picture(f, "folk-name-mode")
        if lesson == "folk-3-spot":
            # A round with words to pick: which word had the ornament. Its try now comes first
            # (skipped here), and the rounds after it.
            f.click_t("course.continue")
            f.wait("document.body.innerText.includes('Which word had the ornament?') || document.body.innerText.includes('a flick on \"Town\", at your own pace')", what="the try or the rounds")
            if not f.js("document.body.innerText.includes('Which word had the ornament?')"):
                f.click_t("course.skip")
            f.wait_text("Which word had the ornament?")
            f.click("Town", "[data-choose] button")
            f.wait("document.querySelector('[data-choose-why]')", what="the why after an answer")
            f.no_sideways("Spot the ornament")
            picture(f, "folk-spot-ornament")
    compress()


if __name__ == "__main__":
    run("course_folk", journey)
