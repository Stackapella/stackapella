"""A song withheld where the singer is (js/withheld.js, docs/geo-rights-plan.md): with Are You Sleeping in auth.config's
withheldSongs (the mock's stackapella.mockWithheld), unit 5's round sings Hey, Ho, Nobody Home instead, its own words on
the step, and with nothing withheld the same step is Are You Sleeping as written. Against the mock; the lesson is opened
the way its public page sends a member (js/page-lesson.js), so unit 5 needn't be earned first.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

LESSON = "5-round"
LINE = "document.querySelector('[data-step-line]')?.innerText ?? ''"
SONG_STEP = "!!document.querySelector('[data-go-on][disabled]')"  # a reading step's Continue is never disabled


def course_step(f, i):
    return f.js(f"(async () => {{ const c = await (await fetch('/course/course.json')).json(); return c.units.flatMap((u) => u.lessons).find((l) => l.id === {json.dumps(LESSON)}).steps[{i}]; }})()")


def open_round(f, withheld):
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('#app header')")
    f.run(f"localStorage.clear(); sessionStorage.clear(); sessionStorage.setItem('stackapella.mockWithheld', {json.dumps(json.dumps(withheld))}); sessionStorage.setItem('stackapella.mockAs', 'member');")
    f.chrome.send("Page.navigate", {"url": f"{f.base}/learn/{LESSON}?ref=lesson-page&api=mock&scenario=&as=member", "referrer": f"{f.base}/courses/the-course/a-page"})
    f.wait("document.querySelector('[data-step-line]')", what="the round's first step")
    # On to the song step: the first is a reading step, so Continue is open (a lesson resumed on the song step has none to press).
    f.wait(f"document.querySelector('[data-go-on]:not([disabled])') || {SONG_STEP}", what="Continue on the first step, or the song step")
    if not f.js(SONG_STEP):
        f.run("document.querySelector('[data-go-on]').click()")


def journey(f):
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('#app header')")
    song = course_step(f, 1)
    alt = song["altSong"]["text"]["en"]
    own = song["text"]["en"]
    assert song["template"] == "are-you-sleeping" and alt != own, "the fixture changed: 5-round step 1 should be Are You Sleeping with an altSong"
    open_round(f, ["are-you-sleeping"])
    f.wait(f"{LINE}.includes({json.dumps(alt[:40])})", what="Hey Ho's own words on the round's song step")
    assert own[:40] not in f.js(LINE), "Are You Sleeping's words still show"
    swapped = f.js(f"(await import('/js/stores/learning.js')).findLesson({json.dumps(LESSON)}).lesson.steps[1].template")
    assert swapped == "hey-ho-nobody-home", f"the step sings {swapped}"
    f.shot("withheld")
    # Nothing withheld: the round as written.
    open_round(f, [])
    f.wait(f"{LINE}.includes({json.dumps(own[:40])})", what="Are You Sleeping's words, nothing withheld")
    f.shot("as-written")


if __name__ == "__main__":
    run("withheld_song", journey)
