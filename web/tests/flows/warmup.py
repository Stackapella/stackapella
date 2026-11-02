"""A teacher's warm-up by link (docs/warmup-builder-plan.md, the student's side): /warmup#<code> opens
its card (the name the teacher gave, its sound, rounds and minutes, the safety line); Sing it runs the
rounds in your range and says each one plainly; Add to my warm-ups keeps it with your progress (the account's too, signed in), and
today's workout then offers it for its warm-up; a broken link says so, with the way to today's workout.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

I18N = "(await import('/js/i18n.js'))"
AUDIO = "(await import('/js/audio.js'))"


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def journey(f):
    # Nothing kept: today's workout is as it was, with no choice of warm-up.
    f.go("/workout", "document.querySelector('[data-round-tabs]')")
    assert not f.js("document.querySelector('[data-warmup-choice]')"), "a choice with nothing kept"
    f.shot("workout-nothing-kept")

    # A broken link: said, with the way to the workout.
    f.go("/warmup#abc", "document.querySelector('[data-warmup-broken]')")
    f.wait_text(t(f, "warmup.brokenTitle"))
    f.shot("broken")

    # A teacher's link: a one-round warm-up, so the flow sings it once.
    code = f.js("(await import('/js/warmup.js')).encodeWarmup({ steps: [0, 2, 4, 2, 0], sound: 'oo', tempo: 104, start: 0, climb: 0, name: 'Easy five' })")
    f.go(f"/warmup#{code}", "document.querySelector('[data-warmup-card]')")
    assert f.js("document.querySelector('[data-warmup-name]').textContent.trim()") == "Easy five"
    f.wait_text(t(f, "warmup.safety"))
    assert t(f, "warmup.from") in f.js("document.querySelector('[data-warmup-card]').textContent"), "who it's from"  # shown in capitals: innerText would say so
    f.shot("card")

    f.js(f"{AUDIO}.unlock()")
    f.mic_checked()
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    f.run("sessionStorage.setItem('stackapella.headphones', '1')")
    f.run("document.querySelector('[data-warmup-sing]').click()")
    f.wait("document.querySelector('[data-warmup-results] li')", timeout=60, what="the round said")
    f.wait("document.querySelector('[data-warmup-done]')", timeout=30, what="the warm-up done")
    f.shot("sung")

    # Kept on this device, across a reload.
    f.run("document.querySelector('[data-warmup-keep]').click()")
    f.wait_text(t(f, "warmup.kept"))
    f.go(f"/warmup#{code}", "document.querySelector('[data-warmup-card]')")
    f.wait_text(t(f, "warmup.kept"))
    # Kept with the course's progress (so it follows a signed-in account), by its code.
    assert f.js("(async () => { const db = await import('/js/db.js'); return (await db.get('learning', 'progress')).warmups[0].code; })()") == code, "kept, by its code"
    assert f.js("localStorage.getItem('stackapella.warmups')") is None, "nothing left in the old place"


    # Today's workout offers it for the warm-up; chosen, it's sung in its own rounds and remembered.
    f.go("/workout", "document.querySelector('[data-warmup-choice] select')")
    assert f.js("document.querySelector('[data-warmup-choice] select').value") == "", "ours until chosen"
    f.run(f"const s = document.querySelector('[data-warmup-choice] select'); s.value = {json.dumps(code)}; s.dispatchEvent(new Event('change'))")
    f.wait("document.querySelector('h2')?.textContent.trim() === 'Easy five'", what="the kept warm-up's name as the headline")
    assert not f.js("document.querySelector('[data-round-tabs]')"), "its rounds aren't our three tabs"
    f.wait_text(t(f, "warmup.safety"))
    f.shot("workout-kept")
    f.go("/workout", "document.querySelector('[data-warmup-choice] select')")
    f.wait(f"document.querySelector('[data-warmup-choice] select').value === {json.dumps(code)}", what="the choice remembered")


if __name__ == "__main__":
    run("warmup", journey)
