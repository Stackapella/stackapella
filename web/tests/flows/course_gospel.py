"""The Gospel and soul runs course (docs/courses/gospel-runs.md), as staff: the course page with its
map, every lesson opening on its Why, lesson 1's hummed try, and a listening round (Five notes only). While the course is still "soon" in the catalogue (hidden even from staff), the flow opens it
in memory as if it were live. With OUT=<dir> it shoots the course page, lesson 1, its try and the
listening round in Warm light and Stage dark at both sizes:

    OUT=~/Code/shots-review/gospel-runs PORT=8297 tool/flows.sh course_gospel
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import compress  # noqa: E402
from plus_courses import picture  # noqa: E402
from course_folk import ROUTER, STORE, open_course  # noqa: E402

LESSONS = {
    "gr-1-stairs": "A run is a few notes on one syllable",
    "gr-1-scale": "Runs are built from five notes",
    "gr-1-turn": "The smallest run is a turn",
    "gr-1-challenge": "Five stairs again, then a real line",
    "gr-2-groups": "Fast runs are groups of three or four",
    "gr-2-clean": "A clean run keeps each note separate",
    "gr-2-place": "One run a line, on a held word",
    "gr-2-challenge": "then Wade in the Water",
    "gr-3-bend": "The blue note sits between",
    "gr-3-up": "A run can climb into a note too",
    "gr-3-hear": "A bend has to arrive",
    "gr-3-challenge": "a bend on the line",
    "gr-4-ladder": "The same run, a rung at a time",
    "gr-4-swing": "plain, then with a turn on",
    "gr-4-steal": "A quiet spiritual",
    "gr-4-challenge": "with a run of your own on",
}


def journey(f):
    open_course(f, "gospel-runs")
    f.run(f"{ROUTER}.navigate('/learn/c/gospel-runs')")
    f.wait("document.querySelector('[data-up-next]') && document.querySelector('[data-course-map]')", what="Up next and the map")
    f.wait_text("Five stairs")
    f.no_sideways("the Gospel and soul runs course page")
    picture(f, "gospel-course-page")

    for lesson, headline in LESSONS.items():
        unit = int(lesson.split("-")[1])
        if lesson in ("gr-2-groups", "gr-3-bend", "gr-4-ladder"):
            # A unit opens on two thirds of the unit before's stars, for staff too: finish it first.
            for done in [k for k in LESSONS if k.startswith(f"gr-{unit - 1}-")]:
                f.js(f"{STORE}.finishLesson('{done}', {{ percent: 95, stars: 3 }})")
        f.run(f"{ROUTER}.navigate('/learn/{lesson}')")
        f.wait_text(headline)
        f.no_sideways(lesson)
        if lesson == "gr-1-stairs":
            picture(f, "gospel-lesson-1")
            f.click_t("course.continue")
            f.wait_text("Hum the run slowly")
            f.no_sideways("lesson 1's try")
            picture(f, "gospel-lesson-1-try")
        if lesson == "gr-1-scale":
            f.click_t("course.continue")
            f.wait("document.querySelector('[data-choose] [data-clip]')", what="the first round's clips")
            f.wait_text("used only the five notes")
            f.no_sideways("Five notes only")
            picture(f, "gospel-choose")
        if lesson == "gr-3-bend":
            f.click_t("course.continue")
            f.wait("document.querySelector('[data-wave-lane] canvas')", what="the bend's lane")
            f.no_sideways("the bend")
            picture(f, "gospel-bend")
    compress()


if __name__ == "__main__":
    run("course_gospel", journey)
