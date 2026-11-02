"""The Scat singing course (docs/courses/scat.md), as staff: the course page with its map, every
lesson opening on its Why, lesson 1's first trade, and a listening round (Long, short). While the course is still "soon" in the catalogue (hidden even from staff), the flow opens it
in memory as if it were live. With OUT=<dir> it shoots the course page, lesson 1, its try and the
listening round in Warm light and Stage dark at both sizes:

    OUT=~/Code/shots-review/scat PORT=8297 tool/flows.sh course_scat
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import compress  # noqa: E402
from plus_courses import picture  # noqa: E402
from course_folk import ROUTER, STORE, open_course  # noqa: E402

LESSONS = {
    "sc-1-trade": "Scat is singing like",
    "sc-1-swing": "Swing is long, short",
    "sc-1-and": "Jazz lines love to",
    "sc-1-challenge": "to hear how far",
    "sc-2-form": "The blues is twelve",
    "sc-2-finish": "The band plays a",
    "sc-2-third": "The third is the",
    "sc-2-challenge": "the first eight bars of the blues",
    "sc-3-ends": "and choose its last two notes",
    "sc-3-answer": "Now a whole answer",
    "sc-3-riff": "A riff is a",
    "sc-3-challenge": "four calls and your answers",
    "sc-4-blue": "The blue note is",
    "sc-4-scale": "The blues scale is",
    "sc-4-four": "Four bars, every note",
    "sc-4-challenge": "then a chorus",
}


def journey(f):
    open_course(f, "scat")
    f.run(f"{ROUTER}.navigate('/learn/c/scat')")
    f.wait("document.querySelector('[data-up-next]') && document.querySelector('[data-course-map]')", what="Up next and the map")
    f.wait_text("Trade with the band")
    f.no_sideways("the Scat course page")
    picture(f, "scat-course-page")

    for lesson, headline in LESSONS.items():
        unit = int(lesson.split("-")[1])
        if lesson in ("sc-2-form", "sc-3-ends", "sc-4-blue"):
            # A unit opens on two thirds of the unit before's stars, for staff too: finish it first.
            for done in [k for k in LESSONS if k.startswith(f"sc-{unit - 1}-")]:
                f.js(f"{STORE}.finishLesson('{done}', {{ percent: 95, stars: 3 }})")
        f.run(f"{ROUTER}.navigate('/learn/{lesson}')")
        f.wait_text(headline)
        f.no_sideways(lesson)
        if lesson == "sc-1-trade":
            picture(f, "scat-lesson-1")
            f.click_t("course.continue")
            f.wait_text("Echo one call")
            f.no_sideways("lesson 1's try")
            picture(f, "scat-lesson-1-try")
        if lesson == "sc-1-swing":
            f.click_t("course.continue")
            f.wait("document.querySelector('[data-choose] [data-clip]')", what="the first round's clips")
            f.wait_text("Which one swings")
            f.no_sideways("Long, short")
            picture(f, "scat-choose")
    compress()


if __name__ == "__main__":
    run("course_scat", journey)
