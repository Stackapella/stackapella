"""New badges, one celebration at a time (game/badge-news.js; docs/onboarding-review-2.md, Must 5): lesson
1's badges (First note, First lesson, Three stars) are said once, merged, as a line in its result, with
no badge toast over the stars or carried into lesson 2; outside a lesson a badge is one short toast.
Serious mode says no badges.
"""
import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from game_frames import dark, light  # noqa: E402
from journey_qa import AUDIO, t, tone  # noqa: E402

BADGE_TOAST = "document.querySelector('[data-toast-tone=badge]')"
LINE = "document.querySelector('[data-badge-line]')"


def journey(f):
    f.go("/?api=mock&scenario=&as=signedOut", "document.querySelector('#app header')")
    f.run("localStorage.removeItem('stackapella.gameEffects'); await new Promise((r) => { const q = indexedDB.deleteDatabase('stackapella'); q.onsuccess = q.onerror = q.onblocked = r; });")
    f.go("/learn/1-comfortable?api=mock&scenario=&as=signedOut", "document.querySelector('main h1')")
    f.js(f"{AUDIO}.unlock()")
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    f.go_on()
    f.wait("['listening', 'hearing', 'holding'].includes(document.querySelector('[data-listening]')?.dataset.phase)", what="listening")
    tone(f, [{"beat": 0, "length": 6, "pitch": 57}])
    f.wait("document.querySelector('[data-note]')?.textContent === 'A3'", what="the note found")
    # The note is kept (First note earned): in a lesson, no toast.
    time.sleep(0.6)
    assert f.js(f"!{BADGE_TOAST}"), "no badge toast mid-lesson"
    finish = t(f, "course.finish")
    f.wait(f"[...document.querySelectorAll('button')].some((b) => b.textContent.trim() === {finish!r} && !b.disabled)", what="Finish")
    f.click_t("course.finish")
    f.wait("document.querySelector('[data-note-result]')", what="lesson 1's result")
    if f.skin == "serious":
        time.sleep(1.5)
        assert f.js(f"!{LINE} && !{BADGE_TOAST}"), "serious mode: no badges"
        return
    f.wait(f"{LINE}?.innerText.includes({t(f, 'badges.firstNote.name')!r})", what="the badge line, with First note")
    words = f.js(f"{LINE}.innerText")
    assert f.js(f"document.querySelectorAll('[data-badge-line]').length") == 1, "one line"
    assert "," in words or " and " in words, f"merged: {words}"
    time.sleep(1.8)
    assert f.js(f"!{BADGE_TOAST}"), "no badge toast over the result"
    f.shot("lesson1-result")
    light(f)
    f.shot("lesson1-result-light")
    dark(f)
    # Lesson 2: nothing carried in.
    f.click_t("course.next")
    f.wait("location.pathname.startsWith('/learn/1-match')", what="lesson 2")
    time.sleep(2.0)  # past the stage's title card
    assert f.js(f"!{BADGE_TOAST} && !{LINE}"), "nothing carried into lesson 2"
    f.shot("lesson2")
    # Outside a lesson: one short toast, replacing any badge toast still up.
    f.run("(await import('/js/router.js')).navigate('/')")
    f.wait("document.querySelector('#app header')", what="Home")
    f.run("const b = await import('/js/game/badge-news.js'); b.sayBadges(['One']); b.sayBadges(['Two', 'Three']);")
    f.wait(BADGE_TOAST, what="the toast")
    assert f.js("document.querySelectorAll('[data-toast-tone=badge]').length") == 1, "one badge toast at a time"
    f.wait(f"!{BADGE_TOAST}", timeout=6, what="gone after its 3 s")


if __name__ == "__main__":
    run("game_badges", journey)
