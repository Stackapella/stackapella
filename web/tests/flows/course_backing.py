"""Backing vocals (docs/courses/backing-vocals.md), as staff: the course page with its map, then every step of
every lesson opening with its own words and nothing sideways: the learn cards, the pads under the lead
(`with`), the doo-wop parts stacked over earlier takes (`under`), the answers in the lead's gaps, and the
two songs kept. While the course is "soon" (hidden even from staff), the flow opens it in memory as
if it were live. With OUT=<dir> it shoots the course page and a layered step in both skins and sizes:

    OUT=~/Code/shots-review/backing-vocals PORT=8297 tool/flows.sh course_backing
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import AUDIO, compress  # noqa: E402
from plus_courses import picture  # noqa: E402
from course_folk import ROUTER, STORE, open_course  # noqa: E402

COURSE = "backing-vocals"
SHOTS = {("bv-1-pad", 0): "backing-lesson-1", ("bv-2-three", 3): "backing-three-parts"}


def words(step):
    """The words a step shows first: a learn step's headline, else its line."""
    if step["kind"] == "learn":
        return step["headline"]["en"]
    return (step.get("text") or step.get("prompt") or {}).get("en", "")


def journey(f):
    open_course(f, COURSE)
    f.run(f"{ROUTER}.navigate('/learn/c/{COURSE}')")
    f.wait("document.querySelector('[data-up-next]') && document.querySelector('[data-course-map]')", what="Up next and the map")
    f.wait_text("Backing vocals")
    f.no_sideways("the course page")
    picture(f, "backing-course-page")

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
                if (lesson["id"], i) in SHOTS:
                    picture(f, SHOTS[(lesson["id"], i)])
    speaker_calls(f, course)
    compress()


def speaker_calls(f, course):
    """Through a speaker (the courses lead's Sea shanties review): the lead's call in your rest keeps sounding in the
    scored turn, whole (exercise.js speakerPartnerNotes), where it used to rest from the turn on."""
    lesson = next(l for u in course["units"] for l in u["lessons"] if l["id"] == "bv-3-response")
    ex = lesson["steps"][1]["exercise"]
    bs = 60 / ex["tempo"]
    until = min(n["beat"] for n in ex["notes"])  # no listening round: the turn is the first note you sing
    f.mic_checked()
    f.js(f"{AUDIO}.unlock()")
    f.run("sessionStorage.setItem('stackapella.lesson.bv-3-response', '1')")
    f.run(f"{ROUTER}.navigate('/learn/c/{COURSE}')")
    f.wait("document.querySelector('[data-course-map]')", what="the course page")
    f.run(f"{ROUTER}.navigate('/learn/bv-3-response')")
    f.wait_text(words(lesson["steps"][1])[:30])
    # The speaker picked, and this lesson's headphones ask already answered.
    f.run("sessionStorage.removeItem('stackapella.headphones'); sessionStorage.setItem('stackapella.speaker', '1'); sessionStorage.setItem('stackapella.headphonesAsked', 'bv-3-response'); localStorage.removeItem('stackapella.headphones.never');")
    f.run("document.querySelector('[data-play-disc]').closest('button').click()")
    f.wait(f"{AUDIO}.engine.parts.get('ex:with0')", timeout=10, what="the lead's line, as a part")
    seconds = f.js(f"(async () => ({AUDIO}).engine.parts.get('ex:with0').buffer.duration)()")
    last = max(n["beat"] + n["length"] for n in ex["with"][0]["notes"])
    assert seconds > until * bs + 0.5, f"the lead's call in your rest sounds past the turn ({until * bs} s): {seconds} s"
    assert seconds <= last * bs + 0.2, f"and no further than the lead's line: {seconds} s"
    f.run(f"{ROUTER}.navigate('/learn/c/{COURSE}')")


if __name__ == "__main__":
    run("course_backing", journey)
