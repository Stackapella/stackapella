"""The Singing in another language course (docs/courses/other-languages.md), as staff: the course
page with its map, every lesson opening on its Why, lesson 1's line on Sakura's vowels, and a
listening round (One sound, one note). While the course is still "soon" in the catalogue (hidden even
from staff), the flow opens it in memory as if it were live. With OUT=<dir> it shoots the course page,
lesson 1, its vowel line and the listening round in Warm light and Stage dark at both sizes:

    OUT=~/Code/shots-review/languages PORT=8297 tool/flows.sh course_languages
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import compress  # noqa: E402
from plus_courses import picture  # noqa: E402
from course_folk import ROUTER, STORE, open_course  # noqa: E402

LESSONS = {
    "ol-1-sakura": "Choirs learn a song in another language by ear",
    "ol-1-vowels": "keep every vowel whole",
    "ol-1-mora": "every small sound gets its own beat",
    "ol-1-challenge": "to hear how far you",
    "ol-2-habanera": "Spanish vowels stay whole",
    "ol-2-join": "Spanish joins them",
    "ol-2-light": "Spanish consonants are light and quick",
    "ol-2-challenge": "The rhythm, the joins and every vowel whole",
    "ol-3-latin": "A medieval hymn for the Eucharist",
    "ol-3-letters": "Church Latin reads its letters the Roman way",
    "ol-3-line": "Mozart's line is long and joined",
    "ol-3-challenge": "The opening with the alto, tenor and bass",
    "ol-4-letters": "a Ukrainian New Year song",
    "ol-4-figure": "Four notes, round and round",
    "ol-4-swallow": "a little swallow has flown in",
    "ol-4-challenge": "sixteen lessons ago",
}


def journey(f):
    open_course(f, "other-languages")
    f.run(f"{ROUTER}.navigate('/learn/c/other-languages')")
    f.wait("document.querySelector('[data-up-next]') && document.querySelector('[data-course-map]')", what="Up next and the map")
    f.wait_text("Sakura")
    f.no_sideways("the languages course page")
    picture(f, "languages-course-page")

    for lesson, headline in LESSONS.items():
        unit = int(lesson.split("-")[1])
        if lesson in ("ol-2-habanera", "ol-3-latin", "ol-4-letters"):
            # A unit opens on two thirds of the unit before's stars, for staff too: finish it first.
            for done in [k for k in LESSONS if k.startswith(f"ol-{unit - 1}-")]:
                f.js(f"{STORE}.finishLesson('{done}', {{ percent: 95, stars: 3 }})")
        f.run(f"{ROUTER}.navigate('/learn/{lesson}')")
        f.wait_text(headline)
        f.no_sideways(lesson)
        if lesson == "ol-1-sakura":
            picture(f, "languages-lesson-1")
            f.click_t("course.continue")
            f.wait_text("on its vowels only")
            f.no_sideways("lesson 1's vowel line")
            picture(f, "languages-lesson-1-vowels")
        if lesson == "ol-1-mora":
            f.click_t("course.continue")
            f.wait("document.querySelector('[data-choose] [data-clip]')", what="the first round's clips")
            f.wait_text("gives every sound its own note")
            f.no_sideways("Every small sound")
            picture(f, "languages-choose")
    compress()


if __name__ == "__main__":
    run("course_languages", journey)
