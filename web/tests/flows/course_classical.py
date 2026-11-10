"""Classical singing basics (docs/courses/classical-singing.md), as staff: the course page with its map, then every
step of every lesson opening with its own words and nothing sideways: the learn cards, the listen-and-choose rounds,
the clean-start echoes, the joined lines (`legato`), the leaning note on the beat, the slides on purpose, Brahms's
lullaby and Caro mio ben over their chords, and the kept song. No lesson asks for headphones: nothing sings under you
but the chords, which a speaker rests. While the course is "soon" (hidden even from staff), the flow opens it in memory
as if it were live. With OUT=<dir> it shoots the course page, lesson 1 and Caro mio ben's opening in both skins and
sizes:

    OUT=~/Code/shots-review/classical PORT=8297 tool/flows.sh course_classical
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import compress  # noqa: E402
from plus_courses import picture  # noqa: E402
from course_folk import ROUTER, STORE, open_course  # noqa: E402

COURSE = "classical-singing"
SHOTS = {("cl-1-joined", 0): "classical-lesson-1", ("cl-4-first", 2): "classical-caro-mio-ben"}
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
    f.wait_text("Classical singing basics")
    f.no_sideways("the course page")
    assert not f.js(f"!!{BEST}"), "the course page doesn't ask for headphones"
    picture(f, "classical-course-page")

    course = json.loads((Path(__file__).parents[2] / "course/plus" / COURSE / "course.json").read_text())
    # A unit opens on two thirds of the unit before's stars, for staff too: finish every unit but the last.
    for unit in course["units"][:-1]:
        for done in unit["lessons"]:
            f.js(f"{STORE}.finishLesson('{done['id']}', {{ percent: 95, stars: 3 }})")
    for unit in course["units"]:
        for lesson in unit["lessons"]:
            assert "headphones" not in lesson, f"{lesson['id']}: no lesson needs headphones"
            for i, step in enumerate(lesson["steps"]):
                f.run(f"sessionStorage.setItem('stackapella.lesson.{lesson['id']}', '{i}')")
                f.run(f"{ROUTER}.navigate('/learn/c/{COURSE}')")
                f.wait("document.querySelector('[data-course-map]')", what="the course page between steps")
                f.run(f"{ROUTER}.navigate('/learn/{lesson['id']}')")
                where = f"{lesson['id']} step {i} ({step['kind']})"
                f.wait_text(words(step)[:30])
                f.no_sideways(where)
                if i == 0:
                    assert not f.js(f"!!{BEST}"), f"{lesson['id']}: Best with headphones shown, but nothing sings under you"
                if (lesson["id"], i) in SHOTS:
                    picture(f, SHOTS[(lesson["id"], i)])
    compress()


if __name__ == "__main__":
    run("course_classical", journey)
