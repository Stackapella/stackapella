"""The First steps to belting course (docs/courses/belting.md), as staff: the course page with its
map, every lesson opening on its Why with the stop line, lesson 1's light warm-up, and a listening
round (As easy as talking). While the course is still "soon" in the catalogue (hidden even from
staff), the flow opens it in memory as if it were live. With OUT=<dir> it shoots the course page,
lesson 1, its warm-up and the listening round in Warm light and Stage dark at both sizes:

    OUT=~/Code/shots-review/belting PORT=8297 tool/flows.sh course_belting
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import compress  # noqa: E402
from plus_courses import picture  # noqa: E402
from course_folk import ROUTER, STORE, open_course  # noqa: E402

LESSONS = {
    "belting-1-call": "Belt starts as a call",
    "belting-1-onset": "Every call starts on a sound that eases the voice in",
    "belting-1-easy": "A bright call carries without being loud",
    "belting-1-challenge": "beside today's, then the unit's calls",
    "belting-2-phrase": "teasing a friend across the room",
    "belting-2-come": "Two- and three-word calls",
    "belting-2-twang": "like a kid teasing",
    "belting-2-challenge": "The unit's phrases, said then sung",
    "belting-3-hold": "A call held two beats",
    "belting-3-colours": "The same note two ways",
    "belting-3-lighter": "let it go lighter, or let it flip",
    "belting-3-challenge": "Two short holds, then the light voice above",
    "belting-4-heyho": "is a round printed in 1609",
    "belting-4-haul": "Sailors hauled a rope to a shanty",
    "belting-4-wade": "Wade in the Water is a spiritual",
    "belting-4-challenge": "Lesson 1's call beside today's",
}


def journey(f):
    open_course(f, "belting")
    f.run(f"{ROUTER}.navigate('/learn/c/belting')")
    f.wait("document.querySelector('[data-up-next]') && document.querySelector('[data-course-map]')", what="Up next and the map")
    f.wait_text("The call")
    f.no_sideways("the belting course page")
    picture(f, "belting-course-page")

    for lesson, headline in LESSONS.items():
        unit = int(lesson.split("-")[1])
        if lesson in ("belting-2-phrase", "belting-3-hold", "belting-4-heyho"):
            # A unit opens on two thirds of the unit before's stars, for staff too: finish it first.
            for done in [k for k in LESSONS if k.startswith(f"belting-{unit - 1}-")]:
                f.js(f"{STORE}.finishLesson('{done}', {{ percent: 95, stars: 3 }})")
        f.run(f"{ROUTER}.navigate('/learn/{lesson}')")
        f.wait_text(headline)
        f.wait_text("Stop if it hurts")
        f.no_sideways(lesson)
        if lesson == "belting-1-call":
            picture(f, "belting-lesson-1")
            f.click_t("course.continue")
            f.wait_text("Warm up light first")
            f.no_sideways("lesson 1's warm-up")
            picture(f, "belting-lesson-1-warmup")
        if lesson == "belting-1-easy":
            f.click_t("course.continue")
            f.wait_text("Warm up light first")
            f.click("Skip")
            f.wait("document.querySelector('[data-choose] [data-clip]')", what="the first round's clips")
            f.wait_text("short and falls at the end")
            f.no_sideways("As easy as talking")
            picture(f, "belting-choose")
    compress()


if __name__ == "__main__":
    run("course_belting", journey)
