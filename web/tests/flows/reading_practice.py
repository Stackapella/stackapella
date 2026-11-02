"""Sight-singing's endless reading practice (docs/sight-singing-plan.md, slice 5), for a singer who
has started the course and is past their first week: the course page's Fresh lines card opens a new
line to read on the stave, Line 1, with the lesson's own play and Hear it first; and on an even day
the workout's third step, in the review's place, is a new line to read (its length unchanged).
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import progress  # noqa: E402

STORE = "(await import('/js/stores/learning.js'))"
ROUTER = "(await import('/js/router.js'))"
I18N = "(await import('/js/i18n.js'))"
# Today, as an even day: the workout's line to read takes the review's place on even days.
EVEN_DAY = "const realNow = Date.now.bind(Date); Date.now = () => { const t = realNow(); return Math.floor(t / 86400000) % 2 === 0 ? t : t + 86400000; };"


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def journey(f):
    f.chrome.send("Page.addScriptToEvaluateOnNewDocument", {"source": EVEN_DAY})
    f.go("/?api=mock&scenario=&as=admin", "document.querySelector('#app header')")
    f.run("localStorage.setItem('stackapella.asks', JSON.stringify({ stopped: ['install'] }));")
    sung = {"stars": 2, "bestPercent": 80, "at": "2026-10-20T10:00:00Z"}
    progress(f, {"1-comfortable": sung, "1-match": sung}, 120, extra=", startedAt: Date.now() - 30 * 86400000, home: 57, range: { low: 50, high: 67 }, courses: { 'sight-singing': { lessons: { 'ss-1-do': " + json.dumps(sung) + ", 'ss-2-chord': " + json.dumps(sung) + " } } }")

    # The course page (shown as if live, as staff): the Fresh lines card, then a line to read.
    f.go("/learn", "document.querySelector('[data-course-map]')")
    f.wait(f"{STORE}.learning.catalogue.some((c) => c.id === 'sight-singing')", what="the catalogue")
    f.run(f"""const store = {STORE}; const data = await (await fetch('/course/plus/sight-singing/course.json')).json();
        store.learning.catalogue.find((c) => c.id === 'sight-singing').status = 'live'; store.learning.courses = {{ ...store.learning.courses, 'sight-singing': data }};""")
    f.run(f"{ROUTER}.navigate('/learn/c/sight-singing')")
    f.wait("document.querySelector('[data-fresh-lines]')", what="Fresh lines on the course page")
    f.run("document.querySelector('[data-fresh-lines]').scrollIntoView({ block: 'center' })")
    f.shot("course-page")
    f.run("document.querySelector('[data-fresh-lines-start]').click()")
    f.wait("location.pathname === '/learn/c/sight-singing/lines' && document.querySelector('[data-read-lines] [data-read-stave] svg')", what="a fresh line on the stave")
    f.wait_text(t(f, "readLines.count", {"count": 1}))
    assert f.js("!!document.querySelector('[data-hear-first]')"), "Hear it first, as in a lesson"
    f.no_sideways("a fresh line")
    f.shot("fresh-line")

    # The workout, on an even day: its third step reads a new line in the review's place.
    f.go("/workout", "document.querySelector('#app h2, #app h1')")
    steps = f.js("document.querySelectorAll('header .h-1.flex-1').length")
    f.click_t("workout.skipWarmUp")
    f.click_t("workout.skipStep")
    f.wait("document.querySelector('[data-read-stave] svg')", what="the workout's line to read")
    f.wait_text(t(f, "workout.why.read"))
    assert f.js("!document.querySelector('[data-starting-note]')"), "a line to read keeps your do: no starting note to move"
    f.no_sideways("the workout's line")
    f.shot("workout-line")
    assert steps in (0, 3), f"the workout still has three steps ({steps})"


if __name__ == "__main__":
    run("reading_practice", journey)
