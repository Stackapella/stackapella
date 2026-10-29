"""Sharpen up (js/sharpen.js), on Perfect your pitch as staff: with four lessons done, the course
page offers the three weakest (game: their star dots; serious: "Best 40%"). Start opens the
weakest at its scored part, "Sharpen up · 1 of 3"; over loopback the take lands dead centre, and
the lesson's own result rises to three stars. Next lesson goes on to the second; Back ends the
session on the course page, which now picks again. A lesson opened from the map plays as ever.
With OUT=<dir> it shoots the card and the session's first lesson:

    OUT=~/Code/shots-review/sharpen FLOW_SKINS=game,serious PORT=8431 tool/flows.sh course_sharpen
"""
import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import compress  # noqa: E402
from plus_courses import picture, t  # noqa: E402
from course_pitch import open_pitch  # noqa: E402

STORE = "(await import('/js/stores/learning.js'))"
ROUTER = "(await import('/js/router.js'))"
AUDIO = "(await import('/js/audio.js'))"
PICKS = "[...document.querySelectorAll('[data-sharpen-lesson]')].map((li) => li.querySelector('span').textContent.trim())"


def titles(f, ids):
    return [f.js(f"(await import('/js/i18n.js')).localized({STORE}.learning.courses.pitch.units[0].lessons.find((l) => l.id === '{i}').title)") for i in ids]


def journey(f):
    open_pitch(f)
    serious = f.js("document.documentElement.dataset.skin === 'serious'")
    f.run(f"""
        const store = {STORE};
        store.learning.progress.courses = {{ ...(store.learning.progress.courses ?? {{}}), pitch: {{ lessons: {{
            'pp-1-bullseye': {{ stars: 3, bestPercent: 95, at: 1000 }},
            'pp-1-scoop': {{ stars: 1, bestPercent: 40, at: 2000 }},
            'pp-1-leaps': {{ stars: 2, bestPercent: 72, at: 3000 }},
            'pp-1-challenge': {{ stars: 2, bestPercent: 80, at: 4000 }},
        }} }} }};
    """)
    f.run(f"{ROUTER}.navigate('/learn/c/pitch')")
    f.wait("document.querySelectorAll('[data-sharpen-lesson]').length === 3", what="Sharpen up with three lessons")
    picks = f.js(PICKS)
    assert picks == titles(f, ["pp-1-scoop", "pp-1-leaps", "pp-1-challenge"]), f"the three weakest, weakest first: {picks}"
    card = f.js("document.querySelector('[data-sharpen]').innerText")
    if serious:
        assert t(f, "sharpen.best", {"percent": 40}) in card, "serious mode: each lesson's best, as a measure"
        assert not f.js("document.querySelector('[data-sharpen] [role=img]')"), "serious mode: no star dots"
        assert not re.search(r"\bstars?\b", card, re.I), f"serious mode: no star words ({card})"
    else:
        assert f.js("document.querySelectorAll('[data-sharpen] [role=img]').length") == 3, "game mode: each lesson's star dots"
    f.no_sideways("the course page with Sharpen up")
    picture(f, "sharpen-card", "document.querySelector('[data-sharpen]').scrollIntoView({ block: 'center' })")

    # The weakest, at its scored part: over loopback its round echoes your own note, so it lands centre.
    f.mic_checked()
    f.run(f"""
        const lesson = {STORE}.learning.courses.pitch.units[0].lessons.find((l) => l.id === 'pp-1-scoop');
        lesson.steps[3].exercise = {{ tempo: 90, echoes: [0, 0], drone: {{ step: 0 }}, generous: true }};
    """)
    f.run("document.querySelector('[data-sharpen-start]').click()")
    f.wait(f"document.querySelector('[data-sharpen-place]')?.textContent.trim() === {t(f, 'sharpen.place', {'place': 1, 'total': 3})!r}", what="Sharpen up, 1 of 3")
    f.wait("document.querySelector('[data-bullseye-step] [data-bullseye]')", what="the scored round, past Learn and the practice")
    assert f.js("location.pathname") == "/learn/pp-1-scoop", "the weakest first"
    assert not f.js("document.querySelector('[data-title-card]')"), "no title card mid-session"
    f.no_sideways("a Sharpen up lesson")
    picture(f, "sharpen-lesson")
    f.js(f"{AUDIO}.unlock()")
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    f.click_t("bullseye.start")
    f.click_t("headphones.on")
    f.wait("document.querySelector('[data-bullseye-result]')", timeout=40, what="the round's result")
    f.js(f"{AUDIO}.engine.setLoopback(false)")
    f.go_on()
    f.wait("document.querySelector('[data-lesson-end]')", what="the lesson's end")
    kept = f.js(f"JSON.parse(JSON.stringify({STORE}.learning.progress.courses.pitch.lessons['pp-1-scoop'] ?? null))")
    assert (kept or {}).get("stars") == 3 and kept["bestPercent"] >= 90, f"the lesson's own result rose: {kept}"
    f.wait_text(titles(f, ["pp-1-leaps"])[0])  # Up next is the session's next lesson

    f.click_t("course.next")
    f.wait(f"document.querySelector('[data-sharpen-place]')?.textContent.trim() === {t(f, 'sharpen.place', {'place': 2, 'total': 3})!r}", what="Sharpen up, 2 of 3")
    assert f.js("location.pathname") == "/learn/pp-1-leaps", "the second weakest next"
    f.wait("document.querySelector('[data-bullseye-step] [data-bullseye]')", what="its scored round")

    # Back ends the session, on the course page, which picks again: the first lesson is sharp now.
    f.run("document.querySelector('header button').click()")
    f.wait("location.pathname === '/learn/c/pitch' && document.querySelectorAll('[data-sharpen-lesson]').length === 3", what="the course page again")
    assert not f.js("sessionStorage.getItem('stackapella.sharpen')"), "the session is over"
    picks = f.js(PICKS)
    assert picks == titles(f, ["pp-1-leaps", "pp-1-challenge", "pp-1-bullseye"]), f"picked again: {picks}"

    # Outside a session a lesson opens at its start, with its unit's name, as ever.
    f.run(f"{ROUTER}.navigate('/learn/pp-1-leaps')")
    f.wait_text("Do is your note")
    assert not f.js("document.querySelector('[data-sharpen-place]')"), "no session line on a normal visit"
    compress()


if __name__ == "__main__":
    run("course_sharpen", journey)
