"""The Musical theatre course (docs/courses/musical-theatre.md), as staff: the course page with its
map, every lesson opening on its Why, lesson 1's spoken try, and a listening round (The word that
matters). While the course is still "soon" in the catalogue (hidden even from staff), the flow opens it
in memory as if it were live. With OUT=<dir> it shoots the course page, lesson 1, its try and the
listening round in Warm light and Stage dark at both sizes:

    OUT=~/Code/shots-review/musical-theatre PORT=8297 tool/flows.sh course_mt
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import compress  # noqa: E402
from plus_courses import picture  # noqa: E402
from course_folk import ROUTER, STORE, open_course  # noqa: E402

LESSONS = {
    "mt-1-speak": "In a show, a song",
    "mt-1-stress": "In every line one word",
    "mt-1-thought": "A breath belongs at the",
    "mt-1-challenge": "first verse, sold to",
    "mt-2-two": "The Major-General knows everything but",
    "mt-2-consonants": "Put the consonant just before",
    "mt-2-quicker": "Clear first, then quicker",
    "mt-2-challenge": "Both lines, showing off",
    "mt-3-bright": "Say the line as if",
    "mt-3-legit": "Legit is the classic sound",
    "mt-3-diphthongs": "each is two vowels",
    "mt-3-challenge": "spins a sad tale",
    "mt-4-button": "The button is a",
    "mt-4-someone": "Henry Irving told the young",
    "mt-4-hail": "is the chorus the pirates sing",
    "mt-4-challenge": "Your button, then your part",
}


def journey(f):
    open_course(f, "musical-theatre")
    f.run(f"{ROUTER}.navigate('/learn/c/musical-theatre')")
    f.wait("document.querySelector('[data-up-next]') && document.querySelector('[data-course-map]')", what="Up next and the map")
    f.wait_text("Say it, then sing it")
    f.no_sideways("the Musical theatre course page")
    picture(f, "mt-course-page")

    for lesson, headline in LESSONS.items():
        unit = int(lesson.split("-")[1])
        if lesson in ("mt-2-two", "mt-3-bright", "mt-4-button"):
            # A unit opens on two thirds of the unit before's stars, for staff too: finish it first.
            for done in [k for k in LESSONS if k.startswith(f"mt-{unit - 1}-")]:
                f.js(f"{STORE}.finishLesson('{done}', {{ percent: 95, stars: 3 }})")
        f.run(f"{ROUTER}.navigate('/learn/{lesson}')")
        f.wait_text(headline)
        f.no_sideways(lesson)
        if lesson == "mt-1-speak":
            picture(f, "mt-lesson-1")
            f.click_t("course.continue")
            f.wait_text("Speak the line in rhythm")
            f.no_sideways("lesson 1's try")
            picture(f, "mt-lesson-1-try")
        if lesson == "mt-1-stress":
            f.click_t("course.continue")
            f.wait("document.querySelector('[data-choose] [data-clip]')", what="the first round's clips")
            f.wait_text("long note on the ONE")
            f.no_sideways("The word that matters")
            picture(f, "mt-choose")
    compress()


if __name__ == "__main__":
    run("course_mt", journey)
