"""Whistling (docs/courses/whistling.md), as staff: the course page with its map, then every step of every lesson
opening with its own words and nothing sideways: the getting-a-whistle card and the free lane, the choose rounds
with whistled clips, the whistle-note finder (`score: "whistleNote"`), the held notes, the clean-start echoes, the
tongue's steps and slides, the leaps, the tunes over their chords, the held note under you, the wave, and the
finale kept as a song. Every scored step is a whistle step (`whistle: true`), laid from your whistle note
(`base: "whistle"`). Only the finale asks for headphones: your own kept tune plays under you there. While the
course is "soon" (hidden even from staff), the flow opens it in memory as if it were live. With OUT=<dir> it shoots
the course page, lesson 1 and the finder in both skins and sizes:

    OUT=~/Code/shots-review/whistling PORT=8297 tool/flows.sh course_whistling
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import compress  # noqa: E402
from plus_courses import picture  # noqa: E402
from course_folk import ROUTER, STORE, open_course  # noqa: E402

COURSE = "whistling"
SHOTS = {("wh-1-find", 0): "whistling-lesson-1", ("wh-1-find", 3): "whistling-finder"}
BEST = "document.querySelector('[data-best-headphones]')"


def words(step):
    """The words a step shows first: a learn step's headline, else its line."""
    if step["kind"] == "learn":
        return step["headline"]["en"]
    return (step.get("text") or step.get("prompt") or {}).get("en", "")


def journey(f):
    open_course(f, COURSE)
    f.run(f"{ROUTER}.navigate('/learn/c/{COURSE}')")
    f.wait("document.querySelector('[data-up-next]') && document.querySelector('[data-course-map]')", what="Up next and the map")
    f.wait_text("Whistling")
    f.no_sideways("the course page")
    assert not f.js(f"!!{BEST}"), "the course page doesn't ask for headphones"
    picture(f, "whistling-course-page")

    course = json.loads((Path(__file__).parents[2] / "course/plus" / COURSE / "course.json").read_text())
    # A unit opens on two thirds of the unit before's stars, for staff too: finish every unit but the last.
    for unit in course["units"][:-1]:
        for done in unit["lessons"]:
            f.js(f"{STORE}.finishLesson('{done['id']}', {{ percent: 95, stars: 3 }})")
    for unit in course["units"]:
        for lesson in unit["lessons"]:
            for i, step in enumerate(lesson["steps"]):
                f.run(f"sessionStorage.setItem('stackapella.lesson.{lesson['id']}', '{i}')")
                f.run(f"{ROUTER}.navigate('/learn/c/{COURSE}')")
                f.wait("document.querySelector('[data-course-map]')", what="the course page between steps")
                f.run(f"{ROUTER}.navigate('/learn/{lesson['id']}')")
                where = f"{lesson['id']} step {i} ({step['kind']})"
                f.wait_text(words(step)[:30])
                f.no_sideways(where)
                if i == 0:
                    needed = lesson.get("headphones") == "needed"
                    shown = f.js(f"!!{BEST}")
                    assert shown == needed, f"{lesson['id']}: Best with headphones shown {shown}, needed {needed}"
                if (lesson["id"], i) in SHOTS:
                    picture(f, SHOTS[(lesson["id"], i)])
    compress()


if __name__ == "__main__":
    run("course_whistling", journey)
