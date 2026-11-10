"""Humming and lip trills (docs/courses/humming.md), as staff: the course page with its map, then every step of
every lesson opening with its own words and nothing sideways: the learn cards, the trill paths on the lane scored on
one unbroken sound (`unbroken`, `"trill": true`), the "ng" sirens, the held notes and hummed lines, the self-checks,
and Then and now's roller coaster. No lesson asks for headphones: nothing sings under you. Lesson 1's roller coaster,
sung over loopback, ends in a result, scored or, with nothing heard, led by the course's own line saying what to try
("Try it on a hum"): never a dead end. While the course is "soon" (hidden even from staff), the flow opens it in
memory as if it were live. With OUT=<dir> it shoots the course page, lesson 1 and the roller coaster in both skins
and sizes:

    OUT=~/Code/shots-review/humming PORT=8297 tool/flows.sh course_humming
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import AUDIO, compress  # noqa: E402
from plus_courses import picture, t  # noqa: E402
from course_folk import ROUTER, STORE  # noqa: E402

COURSE = "humming-and-lip-trills"
FILE = "humming"
SHOTS = {("hum-1-coaster", 0): "humming-lesson-1", ("hum-1-coaster", 1): "humming-roller-coaster"}
BEST = "document.querySelector('[data-best-headphones]')"


def words(step):
    """The words a step shows first: a learn step's headline, else its line."""
    if step["kind"] == "learn":
        return step["headline"]["en"]
    return (step.get("text") or step.get("prompt") or {}).get("en", "")


def open_course(f):
    """Staff, with the course shown as if it were live; its file is in course/plus/humming/, not under its id."""
    f.go("/?api=mock&scenario=&as=admin", "document.querySelector('#app header')")
    f.run("localStorage.setItem('stackapella.asks', JSON.stringify({ stopped: ['install'] }));")
    f.go("/learn", "document.querySelector('[data-course-map]')")
    f.wait(f"{STORE}.learning.catalogue.some((c) => c.id === '{COURSE}')", what="the catalogue")
    f.run(f"""
        const store = {STORE};
        const data = await (await fetch('/course/plus/{FILE}/course.json')).json();
        store.learning.catalogue.find((c) => c.id === '{COURSE}').status = 'live';
        store.learning.courses = {{ ...store.learning.courses, '{COURSE}': data }};
    """)


def open_step(f, lesson, i):
    f.run(f"sessionStorage.setItem('stackapella.lesson.{lesson}', '{i}')")
    f.run(f"{ROUTER}.navigate('/learn/c/{COURSE}')")
    f.wait("document.querySelector('[data-course-map]')", what="the course page between steps")
    f.run(f"{ROUTER}.navigate('/learn/{lesson}')")


def journey(f):
    open_course(f)
    f.run(f"{ROUTER}.navigate('/learn/c/{COURSE}')")
    f.wait("document.querySelector('[data-up-next]') && document.querySelector('[data-course-map]')", what="Up next and the map")
    f.wait_text("Humming and lip trills")
    f.no_sideways("the course page")
    assert not f.js(f"!!{BEST}"), "the course page doesn't ask for headphones"
    picture(f, "humming-course-page")

    course = json.loads((Path(__file__).parents[2] / "course/plus" / FILE / "course.json").read_text())
    # A unit opens on two thirds of the unit before's stars, for staff too: finish every unit but the last.
    for unit in course["units"][:-1]:
        for done in unit["lessons"]:
            f.js(f"{STORE}.finishLesson('{done['id']}', {{ percent: 95, stars: 3 }})")
    for unit in course["units"]:
        for lesson in unit["lessons"]:
            assert "headphones" not in lesson, f"{lesson['id']}: no lesson needs headphones"
            for i, step in enumerate(lesson["steps"]):
                open_step(f, lesson["id"], i)
                where = f"{lesson['id']} step {i} ({step['kind']})"
                f.wait_text(words(step)[:30])
                f.no_sideways(where)
                if i == 0:
                    assert not f.js(f"!!{BEST}"), f"{lesson['id']}: Best with headphones shown, but nothing sings under you"
                if (lesson["id"], i) in SHOTS:
                    picture(f, SHOTS[(lesson["id"], i)])

    # The roller coaster over loopback ends in a result: scored, or with nothing heard, the course's own line
    # saying what to try. Never a dead end.
    f.mic_checked()
    f.run("sessionStorage.setItem('stackapella.headphones', '1');")
    open_step(f, "hum-1-coaster", 1)
    f.wait("document.querySelector('[data-wave-lane]')", what="the roller coaster's lane")
    f.js(f"{AUDIO}.unlock()")
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    f.click_t("wave.sing")
    f.wait(f"{AUDIO}.engine.playing?.recording", what="recording")
    f.wait("document.querySelector('[data-wave-result]')", timeout=40, what="the roller coaster's result")
    f.js(f"{AUDIO}.engine.setLoopback(false)")
    if f.js("!!document.querySelector('[data-wave-unheard]')"):
        heading = f.js("document.querySelector('[data-wave-unheard]').textContent.trim()")
        assert heading == t(f, "styles.unbroken.unheard"), f"what to try, not a dead end: {heading}"
    compress()


if __name__ == "__main__":
    run("course_humming", journey)
