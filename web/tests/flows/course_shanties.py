"""Sea shanties and work songs (docs/courses/sea-shanties.md), as staff: the course page with its map, then every
step of every lesson opening with its own words and nothing sideways: the learn cards, the hear-then-answer steps
(`turn` with `answer`), the crew's answers under the shantyman's calls (`with`), the parts over your own takes
(`under`), and the kept song. Headphones are asked only where a speaker would drop what sings under you: lesson 1
says nothing about them, unit 4's first lesson says "Best with headphones". Through a speaker, the shantyman's
calls in your rests keep sounding. While the course is "soon" (hidden even from staff), the flow opens it in
memory as if it were live. With OUT=<dir> it shoots the course page, lesson 1 and a layered step in both skins
and sizes:

    OUT=~/Code/shots-review/sea-shanties PORT=8297 tool/flows.sh course_shanties
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import AUDIO, compress  # noqa: E402
from plus_courses import picture  # noqa: E402
from course_folk import ROUTER, STORE, open_course  # noqa: E402

COURSE = "sea-shanties"
SHOTS = {("sh-1-answer", 0): "shanties-lesson-1", ("sh-4-challenge", 2): "shanties-your-crew"}
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
    f.wait_text("Sea shanties")
    f.no_sideways("the course page")
    assert not f.js(f"!!{BEST}"), "the course page doesn't ask for headphones: most lessons don't need them"
    picture(f, "shanties-course-page")

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
    speaker_calls(f, course)
    compress()


def speaker_calls(f, course):
    """Through a speaker: the shantyman's calls between your answers keep sounding in the scored turn
    (exercise.js speakerPartnerNotes), so On the pull needs no headphones."""
    lesson = next(l for u in course["units"] for l in u["lessons"] if l["id"] == "sh-2-pull")
    ex = lesson["steps"][1]["exercise"]
    bs = 60 / ex["tempo"]
    until = min(n["beat"] for n in ex["notes"])
    f.mic_checked()
    f.js(f"{AUDIO}.unlock()")
    f.run("sessionStorage.setItem('stackapella.lesson.sh-2-pull', '1')")
    f.run(f"{ROUTER}.navigate('/learn/c/{COURSE}')")
    f.wait("document.querySelector('[data-course-map]')", what="the course page")
    f.run(f"{ROUTER}.navigate('/learn/sh-2-pull')")
    f.wait_text(words(lesson["steps"][1])[:30])
    f.run("sessionStorage.removeItem('stackapella.headphones'); sessionStorage.setItem('stackapella.speaker', '1'); localStorage.removeItem('stackapella.headphones.never');")
    f.run("document.querySelector('[data-play-disc]').closest('button').click()")
    f.wait(f"{AUDIO}.engine.parts.get('ex:with0')", timeout=10, what="the shantyman's line, as a part")
    seconds = f.js(f"(async () => ({AUDIO}).engine.parts.get('ex:with0').buffer.duration)()")
    last = max(n["beat"] + n["length"] for n in ex["with"][0]["notes"])
    assert seconds > until * bs + 0.5, f"the calls in your rests sound past the turn ({until * bs} s): {seconds} s"
    assert seconds <= last * bs + 0.2, f"and no further than the shantyman's line: {seconds} s"
    f.run(f"{ROUTER}.navigate('/learn/c/{COURSE}')")


if __name__ == "__main__":
    run("course_shanties", journey)
