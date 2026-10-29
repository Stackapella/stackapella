"""Then and now in every course's finale (docs/courses-ideas.md, idea 4), as staff: each course's
lesson 1 keeps its first heard take (the step with `keep`), and its finale's step with the same
`thenAndNow` shows and plays it beside today's. The steps are the courses' own (their ids and
places read from the course files); only their notes are swapped, in memory, for one note on the
drone's, so over loopback the take is heard and scored. Play steps get js/components/then-and-now.js;
bullseye finales (Ear, Perfect your pitch) their own "Then: n of n". Phone size, both skins by default:

    FLOW_SKINS=game,serious OUT=~/Code/shots-review/then-and-now PORT=8300 tool/flows.sh then_and_now
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import compress  # noqa: E402
from launch_qa import open_all  # noqa: E402
from plus_courses import picture, t  # noqa: E402

STORE = "(await import('/js/stores/learning.js'))"
ROUTER = "(await import('/js/router.js'))"
AUDIO = "(await import('/js/audio.js'))"
COURSES = ["folk", "pop", "yodel", "barbershop", "counterpoint", "improvising-harmony", "ear", "pitch"]
ONE_NOTE = {"tempo": 72, "notes": [{"beat": 2, "length": 3, "step": 0, "lyric": "oo"}], "turn": False, "drone": {"step": 0}}
ECHOES = {"tempo": 90, "echoes": [0, 0], "drone": {"step": 0}, "generous": True}


def find(f, course, field):
    """(lesson id, step index, kind, id) of the course's step with `field` (keep or thenAndNow): the
    first keep, and the last Then and now (Perfect your pitch's unit challenges show it too)."""
    found = f.js(f"""JSON.stringify(await (async () => {{
        let found = null;
        for (const u of {STORE}.learning.courses['{course}'].units) for (const l of u.lessons)
            for (const [i, s] of l.steps.entries()) if (s.exercise?.{field}) {{ found = [l.id, i, s.kind, s.exercise.{field}]; if ('{field}' === 'keep') return found; }}
        return found; }})())""")
    return json.loads(found) if found and found != "null" else None


def sing(f, course, lesson, index, kind, field, key):
    """Opens the step, its notes swapped for one on the drone's, and sings it over loopback."""
    simple = dict(ECHOES if kind == "bullseye" else ONE_NOTE, **{field: key})
    f.run(f"""
        const l = {STORE}.learning.courses['{course}'].units.flatMap((u) => u.lessons).find((x) => x.id === '{lesson}');
        l.steps[{index}].exercise = {json.dumps(simple)};
        sessionStorage.setItem('stackapella.lesson.{lesson}', '{index}');
        sessionStorage.setItem('stackapella.headphones', '1');
    """)
    f.run(f"{ROUTER}.navigate('/learn/{lesson}')")
    f.wait("document.querySelector('main h1')", what=f"{lesson} open")
    f.js(f"{AUDIO}.unlock()")
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    if kind == "bullseye":
        f.wait("document.querySelector('[data-bullseye-step] [data-bullseye]')", what=f"{lesson}'s board")
        f.click_t("bullseye.start")
        if f.js(f"document.body.innerText.includes({json.dumps(t(f, 'headphones.title'))})"):
            f.click_t("headphones.on")
        f.wait("document.querySelector('[data-bullseye-result] [data-bullseye-line]')", timeout=40, what=f"{lesson}'s round heard")
    else:
        f.click_t("course.tap.sing")
        f.wait(f"{AUDIO}.engine.playing?.recording", what=f"{lesson}: recording")
        f.wait("document.querySelector('[data-take-result]')", timeout=40, what=f"{lesson}: the take's result")


def journey(f):
    open_all(f)
    f.mic_checked()
    for course in COURSES:
        lessons = json.loads(f.js(f"JSON.stringify({STORE}.learning.courses['{course}'].units.flatMap((u) => u.lessons.map((l) => l.id)))"))
        for lesson in lessons:  # every unit open, so the finale can be reached
            f.js(f"{STORE}.finishLesson('{lesson}', {{ percent: 95, stars: 3 }})")
        first, then = find(f, course, "keep"), find(f, course, "thenAndNow")
        if not first or not then or then[0] != lessons[-1]:
            # Content pinned before Then and now reached this course's finale: nothing to check yet.
            print(f"     skip {course}: no Then and now in its finale in this content")
            continue
        assert first[3] == then[3], f"{course}: lesson 1 keeps {first[3]} but the finale asks for {then[3]}"
        # A fresh start: nothing kept from an earlier run (the empty state is then-and-now.test.js's).
        if then[2] == "play":
            f.run(f"(await import('/js/db.js')).remove('learning', 'course:{then[3]}')")
        sing(f, course, *first[:3], "keep", first[3])
        f.wait(f"(await import('/js/db.js')).get('learning', 'course:{first[3]}')", timeout=10, what=f"{course}: lesson 1's take kept on this device")
        sing(f, course, *then[:3], "thenAndNow", then[3])
        if then[2] == "bullseye":
            f.wait("document.querySelector('[data-then-and-now]')?.textContent.includes('Then: 2 of 2')", what=f"{course}: then and now, the round kept")
        else:
            f.wait("document.querySelectorAll('[data-then-and-now] [data-then-row]').length === 2", what=f"{course}: then and now, both takes")
            assert t(f, "course.then.then") in f.js("document.querySelector('[data-then-and-now]').innerText"), "the first take named"
            f.run("document.querySelector('[data-then-row=then] button').click()")
            f.wait(f"document.querySelector('[data-then-row=then] button').getAttribute('aria-label') === {json.dumps(t(f, 'studio.stop'))}", what=f"{course}: the first take playing")
            f.run("document.querySelector('[data-then-row=then] button').click()")
        f.no_sideways(f"{course}'s finale")
        f.run("document.querySelector('[data-then-and-now]').scrollIntoView({ block: 'center' })")
        picture(f, f"then-and-now-{course}", "document.querySelector('[data-then-and-now]')?.scrollIntoView({ block: 'center' })")
    f.js(f"{AUDIO}.engine.setLoopback(false)")
    compress()


if __name__ == "__main__":
    run("then_and_now", journey, sizes=("phone",))
