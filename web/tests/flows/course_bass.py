"""The A cappella bass course (docs/courses/a-cappella-bass.md), as staff: the course page with its
map, every lesson opening on its Why, lesson 1's line under the group, and a listening round (Bright or
dark?). While the course is still "soon" in the catalogue (hidden even from staff), the flow opens it
in memory as if it were live. With OUT=<dir> it shoots the course page, lesson 1, its try and the
listening round in Warm light and Stage dark at both sizes:

    OUT=~/Code/shots-review/bass PORT=8297 tool/flows.sh course_bass
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import compress  # noqa: E402
from plus_courses import picture  # noqa: E402
from course_folk import ROUTER, STORE, open_course  # noqa: E402

LESSONS = {
    "acb-1-root": "The bass sings the",
    "acb-1-low": "low notes come from ease",
    "acb-1-hear": "The same notes on top can make a bright",
    "acb-1-challenge": "Twice through the four chords",
    "acb-2-doom": "closing to a hum",
    "acb-2-dm": "a light tap of",
    "acb-2-buh": "Call and response",
    "acb-2-challenge": "now in bass",
    "acb-3-fifth": "The most common bass move",
    "acb-3-time": "The group listens to you for the beat",
    "acb-3-hear": "A bass that rushes",
    "acb-3-challenge": "root and fifth with a",
    "acb-4-walk": "A walking bass steps",
    "acb-4-lead": "A bass brings the group home",
    "acb-4-swing": "A real song now",
    "acb-4-challenge": "Everything at once",
}


def journey(f):
    open_course(f, "a-cappella-bass")
    f.run(f"{ROUTER}.navigate('/learn/c/a-cappella-bass')")
    f.wait("document.querySelector('[data-up-next]') && document.querySelector('[data-course-map]')", what="Up next and the map")
    f.wait_text("Under the group")
    f.no_sideways("the A cappella bass course page")
    picture(f, "bass-course-page")

    for lesson, headline in LESSONS.items():
        unit = int(lesson.split("-")[1])
        if lesson in ("acb-2-doom", "acb-3-fifth", "acb-4-walk"):
            # A unit opens on two thirds of the unit before's stars, for staff too: finish it first.
            for done in [k for k in LESSONS if k.startswith(f"acb-{unit - 1}-")]:
                f.js(f"{STORE}.finishLesson('{done}', {{ percent: 95, stars: 3 }})")
        f.run(f"{ROUTER}.navigate('/learn/{lesson}')")
        f.wait_text(headline)
        f.no_sideways(lesson)
        if lesson == "acb-1-root":
            picture(f, "bass-lesson-1")
            f.click_t("course.continue")
            f.wait_text("with the guide bass singing along")
            f.no_sideways("lesson 1's try")
            picture(f, "bass-lesson-1-try")
        if lesson == "acb-1-hear":
            f.click_t("course.continue")
            f.wait("document.querySelector('[data-choose] [data-clip]')", what="the first round's clips")
            f.wait_text("Which one is bright")
            f.no_sideways("Bright or dark?")
            picture(f, "bass-choose")
    compress()


if __name__ == "__main__":
    run("course_bass", journey)
