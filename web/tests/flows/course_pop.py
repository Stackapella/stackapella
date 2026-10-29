"""The Pop and country styling course (docs/courses/pop-country.md), as staff: the course page,
every lesson opening on its Why, the scoop lesson's listening round, and a tone colour's
self-check. With OUT=<dir>,
shots in Warm light and Stage dark at both sizes:

    OUT=~/Code/shots-review/pop PORT=8300 tool/flows.sh course_pop
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from course_folk import ROUTER, STORE, open_course  # noqa: E402
from flow import run  # noqa: E402
from help_shots import compress  # noqa: E402
from plus_courses import picture, t  # noqa: E402

LESSONS = {
    "pop-1-style": "A plain tune and a styled one are the same notes",
    "pop-1-scoop": "A scoop slides up into a note",
    "pop-1-fall": "A fall-off lets the end of a note drop away",
    "pop-1-challenge": "The whole verse with two scoops",
    "pop-2-slide": "A slide glides from one note to the next",
    "pop-2-cry": "Country singers add a little cry",
    "pop-2-hear": "Four moves so far",
    "pop-2-challenge": "A cowboy lament, styled",
    "pop-3-colours": "The same line can be breathy, bright or twangy",
    "pop-3-crooner": "Crooning became popular",
    "pop-3-twang": "A little twang gives country singing its edge",
    "pop-3-challenge": "Aura Lee's verse in the colour",
    "pop-4-where": "A rule of thumb",
    "pop-4-answer": "The guide styles a line its way",
    "pop-4-yours": "Beautiful Dreamer's first line",
    "pop-4-challenge": "Back to lesson 1's song",
}


def journey(f):
    open_course(f, "pop")
    f.run(f"{ROUTER}.navigate('/learn/c/pop')")
    f.wait("document.querySelector('[data-course-map]')", what="the map")
    f.wait_text("Plain to styled")
    f.no_sideways("the Pop and country course page")
    picture(f, "pop-course-page")
    for lesson, headline in LESSONS.items():
        if lesson in ("pop-2-slide", "pop-3-colours", "pop-4-where"):
            # A unit opens on two thirds of the unit before's stars, for staff too: finish it first.
            unit = int(lesson.split("-")[1])
            for done in [k for k in LESSONS if k.startswith(f"pop-{unit - 1}-")]:
                f.js(f"{STORE}.finishLesson('{done}', {{ percent: 95, stars: 3 }})")
        f.run(f"{ROUTER}.navigate('/learn/{lesson}')")
        f.wait_text(headline)
        f.no_sideways(lesson)
        if lesson == "pop-1-style":
            picture(f, "pop-lesson-1")
        if lesson == "pop-1-scoop":
            f.click_t("course.continue")
            f.wait_text("Which one scooped?")
            f.no_sideways("the scoop round")
            picture(f, "pop-scoop-choose")
        if lesson == "pop-3-colours":
            f.click_t("course.continue")
            f.wait_text("First the line plain")
            f.click_t("course.skip")
            f.wait_text("Breathy first")
            f.no_sideways("a colour's self-check")
            picture(f, "pop-colour-selfcheck")
    compress()


if __name__ == "__main__":
    run("course_pop", journey)
