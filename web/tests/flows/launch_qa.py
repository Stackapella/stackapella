"""A learner's QA pass over every launch course (docs/courses/launch-qa.md): as staff, each course's
page and every lesson, step by step (Continue, or Skip where a step needs singing), checking each
step for sideways scroll, raw translation keys, "undefined" or "NaN", and a step it can't get past;
then, as a free account, lesson 1 open and lesson 2 asking for Plus. It records what it finds in
$QA_OUT (one JSON file per size and skin) instead of stopping at the first, so one run covers every
lesson. QA_SHOTS_ONLY=1 visits only each course page and lesson 1 (for the other skin's shots).

    FLOW_SIZES=phone FLOW_SKINS=game QA_OUT=/tmp/qa OUT=~/Code/shots-review/launch-qa PORT=8300 tool/flows.sh launch_qa
"""
import json
import os
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import compress  # noqa: E402
from plus_courses import picture, t  # noqa: E402

STORE = "(await import('/js/stores/learning.js'))"
ROUTER = "(await import('/js/router.js'))"
ACCOUNT = "(await import('/js/stores/account.js')).account"
LAUNCH = os.environ.get("QA_COURSES", "vibrato,pitch,breath,ear,counterpoint,improvising-harmony,barbershop,folk,yodel,pop").split(",")
SHOTS_ONLY = os.environ.get("QA_SHOTS_ONLY") == "1"
MAX_STEPS = 14

CHECK = r"""(() => {
    const text = document.body.innerText;
    const raw = (text.match(/\b(course|courses|choose|selfcheck|styles|results|wave|breathhold|bullseye|exercise|plus)\.[a-z][A-Za-z]+(\.[A-Za-z]+)*\b/g) || []).filter((k) => !/\.(com|json|js|md)$/.test(k));
    const odd = (text.match(/\bundefined\b|\bNaN\b|\[object Object\]/g) || []);
    const over = document.documentElement.scrollWidth - window.innerWidth;
    return JSON.stringify({ raw: [...new Set(raw)].slice(0, 3), odd: [...new Set(odd)], over });
})()"""

ADVANCE = r"""(() => {
    const words = (b) => b.textContent.trim();
    if (document.querySelector('[data-lesson-end]') || [...document.querySelectorAll('button, a')].some((b) => b.offsetParent && ENDS.includes(words(b)))) return 'end';
    const on = document.querySelector('[data-go-on]:not(:disabled)');
    if (on) { on.click(); return 'on'; }
    const skip = [...document.querySelectorAll('button')].find((b) => b.offsetParent && b.textContent.trim() === SKIP);
    if (skip) { skip.click(); return 'skip'; }
    return 'stuck';
})()"""


def open_all(f, as_="admin"):
    """Signed in, with every launch course loaded and shown as live (some may still be coming)."""
    f.go(f"/?api=mock&scenario=&as={as_}", "document.querySelector('#app header')")
    f.run("localStorage.setItem('stackapella.installDismissed', '1');")
    f.go("/learn", "document.querySelector('[data-course-map]')")
    f.wait(f"{STORE}.learning.catalogue.length > 0", what="the catalogue")
    f.run(f"""
        const store = {STORE};
        const loaded = {{ ...store.learning.courses }};
        for (const id of {json.dumps(LAUNCH)}) {{
            const entry = store.learning.catalogue.find((c) => c.id === id);
            if (!entry?.file) continue;
            entry.status = 'live';
            loaded[id] = await (await fetch('/course/plus/' + entry.file)).json();
        }}
        store.learning.courses = loaded;
    """)


def journey(f):
    found = []
    note = lambda course, where, what: found.append({"course": course, "where": where, "what": what})
    open_all(f)
    f.mic_checked()
    ends = [t(f, "course.next"), t(f, "course.backToCourse")]
    advance = ADVANCE.replace("SKIP", json.dumps(t(f, "course.skip"))).replace("ENDS", json.dumps(ends))
    courses = f.js(f"JSON.stringify({STORE}.learning.catalogue.filter((c) => {json.dumps(LAUNCH)}.includes(c.id)).map((c) => c.id))")
    for course in json.loads(courses):
        lessons = json.loads(f.js(f"JSON.stringify({STORE}.learning.courses['{course}']?.units.flatMap((u) => u.lessons.map((l) => l.id)) ?? [])"))
        if not lessons:
            note(course, "the course", "its file didn't load")
            continue
        # Every lesson open for the walk: finish them all first (units open on the unit before's stars).
        for lesson in lessons:
            f.js(f"{STORE}.finishLesson('{lesson}', {{ percent: 95, stars: 3 }})")
        f.run(f"{ROUTER}.navigate('/learn/c/{course}')")
        try:
            f.wait("document.querySelector('[data-course-map]')", timeout=10, what=f"{course}'s page")
        except AssertionError:
            note(course, "the course page", "didn't open")
            continue
        page = json.loads(f.js(CHECK))
        if page["over"] > 1:
            note(course, "the course page", f"scrolls sideways by {page['over']}px")
        picture(f, f"qa-{course}-page")
        for index, lesson in enumerate(lessons[:1] if SHOTS_ONLY else lessons):
            f.run(f"{ROUTER}.navigate('/learn/{lesson}')")
            try:
                f.wait("document.querySelector('h1') && !document.querySelector('[data-loading]')", timeout=10, what=lesson)
            except AssertionError:
                note(course, lesson, "didn't open")
                continue
            if index == 0:
                picture(f, f"qa-{course}-lesson-1")
            if SHOTS_ONLY:
                continue
            for step in range(MAX_STEPS):
                seen = json.loads(f.js(CHECK))
                if seen["over"] > 1:
                    note(course, f"{lesson} step {step + 1}", f"scrolls sideways by {seen['over']}px")
                if seen["raw"]:
                    note(course, f"{lesson} step {step + 1}", f"raw words on screen: {seen['raw']}")
                if seen["odd"]:
                    note(course, f"{lesson} step {step + 1}", f"shows {seen['odd']}")
                went = f.js(advance)
                if went == "end":
                    break
                if went == "stuck":
                    f.run("await new Promise((r) => setTimeout(r, 1500))")  # the end screen may still be coming
                    went = f.js(advance)
                if went == "end":
                    break
                if went == "stuck":
                    buttons = f.js("JSON.stringify([...document.querySelectorAll('button, a')].filter((b) => b.offsetParent).map((b) => b.textContent.trim()).filter(Boolean).slice(0, 8))")
                    note(course, f"{lesson} step {step + 1}", f"no way on: neither Continue nor Skip; showing {buttons}")
                    break
                f.run("await new Promise((r) => setTimeout(r, 250))")  # the next step renders (no audio here)
            else:
                note(course, lesson, f"still not at its end after {MAX_STEPS} steps")
    save(f, found)
    # A free account: lesson 1 free, lesson 2 asks for Plus.
    if not SHOTS_ONLY:
        open_all(f, "member")
        f.wait(f"!!{ACCOUNT}.me", what="the member's account")
        f.js(f"{ACCOUNT}.me.plusCourses = true")
        plus_only = t(f, "courses.plusOnly")
        for course in json.loads(courses):
            first_two = json.loads(f.js(f"JSON.stringify(({STORE}.learning.courses['{course}']?.units[0]?.lessons ?? []).slice(0, 2).map((l) => l.id))"))
            if len(first_two) < 2:
                continue
            f.run(f"{ROUTER}.navigate('/learn/{first_two[0]}')")
            f.run("await new Promise((r) => setTimeout(r, 600))")
            if plus_only in f.js("document.body.innerText"):
                note(course, first_two[0], "the free first lesson asks for Plus")
            f.run(f"{ROUTER}.navigate('/learn/{first_two[1]}')")
            f.run("await new Promise((r) => setTimeout(r, 600))")
            if plus_only not in f.js("document.body.innerText"):
                note(course, first_two[1], "lesson 2 doesn't ask a free account for Plus")
    save(f, found)
    print(f"     {len(found)} findings ({f.size}, {f.skin})")
    compress()


def save(f, found):
    out = os.environ.get("QA_OUT")
    if out:
        Path(out).mkdir(parents=True, exist_ok=True)
        (Path(out) / f"{f.size}-{f.skin}.json").write_text(json.dumps(found, indent=1))


if __name__ == "__main__":
    run("launch_qa", journey)
