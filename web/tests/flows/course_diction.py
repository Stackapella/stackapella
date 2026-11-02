"""The Diction for singers course (docs/courses/diction.md), as staff: the course page with its map,
every lesson opening on its Why, lesson 1's first line, and a listening round (Together). While the course is still "soon" in the catalogue (hidden even from staff), the flow opens it
in memory as if it were live. With OUT=<dir> it shoots the course page, lesson 1, its try and the
listening round in Warm light and Stage dark at both sizes:

    OUT=~/Code/shots-review/diction PORT=8297 tool/flows.sh course_diction
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import compress  # noqa: E402
from plus_courses import picture  # noqa: E402
from course_folk import ROUTER, STORE, open_course  # noqa: E402

LESSONS = {
    "di-1-heard": "Diction is being understood",
    "di-1-pure": "Hold each vowel pure",
    "di-1-two": "each glide between two vowels",
    "di-1-challenge": "stressed vowels full, small words light",
    "di-2-onset": "Hand on your throat",
    "di-2-finals": "last consonant goes exactly where",
    "di-2-together": "end together, or a stray",
    "di-2-challenge": "then Danny Boy",
    "di-3-hum": "An m has pitch",
    "di-3-n-l": "n and l have pitch too",
    "di-3-buzz": "v and z can buzz",
    "di-3-challenge": "first line, full of",
    "di-4-heard": "You always understand your own words",
    "di-4-choir": "A choir is understood when",
    "di-4-lining": "In lining out, a leader",
    "di-4-challenge": "to hear how far you",
}


def journey(f):
    open_course(f, "diction")
    f.run(f"{ROUTER}.navigate('/learn/c/diction')")
    f.wait("document.querySelector('[data-up-next]') && document.querySelector('[data-course-map]')", what="Up next and the map")
    f.wait_text("Be heard")
    f.no_sideways("the Diction course page")
    picture(f, "diction-course-page")

    for lesson, headline in LESSONS.items():
        unit = int(lesson.split("-")[1])
        if lesson in ("di-2-onset", "di-3-hum", "di-4-heard"):
            # A unit opens on two thirds of the unit before's stars, for staff too: finish it first.
            for done in [k for k in LESSONS if k.startswith(f"di-{unit - 1}-")]:
                f.js(f"{STORE}.finishLesson('{done}', {{ percent: 95, stars: 3 }})")
        f.run(f"{ROUTER}.navigate('/learn/{lesson}')")
        f.wait_text(headline)
        f.no_sideways(lesson)
        if lesson == "di-1-heard":
            picture(f, "diction-lesson-1")
            f.click_t("course.continue")
            f.wait_text("as you'd normally sing it")
            f.no_sideways("lesson 1's first line")
            picture(f, "diction-lesson-1-line")
        if lesson == "di-2-together":
            f.click_t("course.continue")
            f.wait("document.querySelector('[data-choose] [data-clip]')", what="the first round's clips")
            f.wait_text("stopped together")
            f.no_sideways("Together")
            picture(f, "diction-choose")
    compress()


if __name__ == "__main__":
    run("course_diction", journey)
