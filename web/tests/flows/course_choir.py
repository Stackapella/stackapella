"""Learn your part for rehearsal (docs/courses/choir-part.md), as staff: the course page with its map, and
every step of every lesson opening, for each of the four parts the singer can pick (Your part,
docs/courses/choir-part-sections-plan.md): the learn cards in that part's words, the song's own lines
(the course's song's picked part, loaded with the song, the other parts with it where asked), the
stave, the chord echoes, the hum and the dress rehearsal's Record in Sing. While the course is still "soon" in the catalogue (hidden even from
staff), the flow opens it in memory as if it were live. With OUT=<dir> it shoots the course page, lesson 1,
a song line with the others and the stave in Warm light and Stage dark at both sizes:

    OUT=~/Code/shots-review/choir-part PORT=8297 tool/flows.sh course_choir
"""
import json
import os
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import compress  # noqa: E402
from plus_courses import picture  # noqa: E402
from course_folk import ROUTER, STORE, open_course  # noqa: E402

COURSE = "choir-part"
# The mock API answers a library song with its example (Silent Night) unless the song is on disk among the
# free ones: the flow serves the course's own song (its `parts.template`: O Come, All Ye Faithful, then It Is Well
# with My Soul from 2026-12-28; server/database/templates, from the content link) as if it were, so every step plays it.
SONG = json.loads((Path(__file__).parents[2] / "course/plus" / COURSE / "course.json").read_text())["parts"]["template"]


def serve_song(f):
    template = (Path(__file__).parents[3] / "server/database/templates" / f"{SONG}.json").read_text()
    f.run(f"""
        const song = {template};
        const real = window.fetch.bind(window);
        window.fetch = async (input, init) => {{
            const url = typeof input === 'string' ? input : input.url;
            const path = new URL(url, location.href).pathname;
            if (path === '/templates/index.json') {{
                const index = await (await real(input, init)).json();
                return new Response(JSON.stringify({{ ...index, templates: [...index.templates, '{SONG}'] }}), {{ headers: {{ 'content-type': 'application/json' }} }});
            }}
            if (path === '/templates/{SONG}.json') return new Response(JSON.stringify(song), {{ headers: {{ 'content-type': 'application/json' }} }});
            return real(input, init);
        }};
    """)


# Every part by default; PARTS=tenor (comma-separated) runs fewer while debugging.
PARTS = os.environ.get("PARTS", "soprano,alto,tenor,bass").split(",")
LEARNING = "import('/js/stores/learning.js')"


def pick(f, part):
    """Pick the singer's part as the course page does, and wait until lessons resolve to it."""
    f.run(f"window.__part = null; {LEARNING}.then(async (m) => {{ await m.setCoursePersonal('{COURSE}', {{ part: '{part}' }}); window.__part = m.findLesson('chp-1-hear')?.part ?? null; }})")
    f.wait(f"window.__part === '{part}'", what=f"the {part} picked")


def words(step, part):
    """The words a step shows for the picked part: its byPart or textByPart entry, else its own."""
    if step["kind"] == "learn":
        return (step.get("byPart", {}).get(part) or step)["headline"]["en"]
    return (step.get("textByPart", {}).get(part) or step.get("text", {})).get("en", "")


SONG_READY = "!document.querySelector('[data-song-step]') && document.querySelector('#app button')"
SHOTS = {("chp-1-hear", 0): "choir-lesson-1", ("chp-1-hear", 3): "choir-with-others", ("chp-1-page", 1): "choir-stave"}


def mounted(kind, exercise):
    """What shows once a step of this kind is ready."""
    if kind == "learn":
        return "document.querySelector('#app h2, #app h1')"
    if kind == "bullseye":
        return "document.querySelector('[data-bullseye-step]')"
    if kind == "wave":
        return "document.querySelector('[data-wave-lane]')"
    if kind == "record":
        return "[...document.querySelectorAll('#app button')].some((b) => b.textContent.includes('Sing'))"
    if exercise.get("staff"):
        return SONG_READY + " && document.querySelector('#app svg')"
    return SONG_READY


def journey(f):
    open_course(f, COURSE)
    serve_song(f)
    f.run(f"{ROUTER}.navigate('/learn/c/{COURSE}')")
    f.wait("document.querySelector('[data-up-next]') && document.querySelector('[data-course-map]')", what="Up next and the map")
    f.wait_text("Learn your part for rehearsal")
    f.no_sideways("the course page")
    picture(f, "choir-course-page")

    course = json.loads((Path(__file__).parents[2] / "course/plus" / COURSE / "course.json").read_text())
    for i, part in enumerate(PARTS):
        if i:
            # Each part on a fresh page: one page through all four parts' ~120 lesson opens timed out on the
            # third part's unit 2 (each part alone, and any two, pass).
            open_course(f, COURSE)
            serve_song(f)
        # A unit opens on two thirds of the unit before's stars, for staff too: finish every unit but the last.
        for unit in course["units"][:-1]:
            for done in unit["lessons"]:
                f.js(f"{STORE}.finishLesson('{done['id']}', {{ percent: 95, stars: 3 }})")
        pick(f, part)
        steps(f, course, part)
    compress()


def steps(f, course, part):
    for unit in course["units"]:
        for lesson in unit["lessons"]:
            for i, step in enumerate(lesson["steps"]):
                f.run(f"sessionStorage.setItem('stackapella.lesson.{lesson['id']}', '{i}')")
                f.run(f"{ROUTER}.navigate('/learn/c/{COURSE}')")
                f.wait("document.querySelector('[data-course-map]')", what="the course page between steps")
                f.run(f"{ROUTER}.navigate('/learn/{lesson['id']}')")
                where = f"{part}: {lesson['id']} step {i} ({step['kind']})"
                f.wait(mounted(step["kind"], step.get("exercise", {})), what=where)
                assert not f.js("document.querySelector('[data-song-step]')?.textContent.includes('could')"), f"{where}: the song loaded"
                f.wait_text(words(step, part)[:30])
                f.no_sideways(where)
                if part == "alto" and (lesson["id"], i) in SHOTS:
                    picture(f, SHOTS[(lesson["id"], i)])


if __name__ == "__main__":
    run("course_choir", journey)
