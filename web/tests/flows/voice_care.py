"""Voice care (docs/voice-care-plan.md, Hillel's picks: free, no badge, never a lock): the cool-down at
/cooldown, a guest's, runs through its steps (the why, the slides, the trills, the hum and the sigh)
and ends with its own line and Done, no stars and nothing kept as progress; the course map's quiet
"Sung a lot today?" link goes there. After 20 minutes sung today on this device, a lesson's end
says so in one quiet line with the cool-down a tap away, once a day; Settings' Voice-care notes off,
it says nothing.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

I18N = "(await import('/js/i18n.js'))"
STORE = "(await import('/js/stores/learning.js'))"
DAY = "stackapella.voiceDay"


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def sung(f, minutes, shown=None):
    f.run(f"localStorage.setItem('{DAY}', JSON.stringify({{ day: new Date().toDateString(), sungSeconds: {minutes * 60}, shown: {json.dumps(shown or [])} }}))")


def through(f):
    """Every step, on: Continue where it can go on, Skip where it's sung."""
    for _ in range(12):
        if f.js("!!document.querySelector('[data-unscored-end], [data-lesson-end], [data-note-result]')"):
            return
        go = t(f, "course.continue")
        finish = t(f, "course.finish")
        f.run(f"""const b = [...document.querySelectorAll('main button')].filter((b) => b.getClientRects().length && !b.disabled);
            const want = [{json.dumps(go)}, {json.dumps(finish)}, {json.dumps(t(f, 'course.skip'))}, {json.dumps(t(f, 'course.home.skip'))}];
            (want.map((w) => b.find((x) => x.textContent.trim() === w)).find(Boolean))?.click();""")
        f.js("new Promise((r) => setTimeout(r, 500))")


def started(f, days_ago):
    f.run(f"""const s = {STORE}; await s.loadLearning(); const db = await import('/js/db.js');
        const p = {{ ...s.learning.progress, startedAt: Date.now() - {days_ago} * 86400000 }}; s.learning.progress = p; await db.put('learning', JSON.parse(JSON.stringify(p)), 'progress');""")


def journey(f):
    # A new singer's first week: neither the cool-down's line on the map nor the day's note.
    f.go("/learn?api=mock&scenario=&as=signedOut", "document.querySelector('main h1, h1')")
    started(f, 2)
    f.go("/learn?api=mock&scenario=&as=signedOut", "document.querySelector('main h1, h1')")
    f.js("new Promise((r) => setTimeout(r, 600))")
    assert not f.js("!!document.querySelector('[data-cooldown-link]')"), "the cool-down offered in week one"
    sung(f, 25)
    f.run("sessionStorage.removeItem('stackapella.lesson.1-comfortable')")
    f.go("/learn/1-comfortable?api=mock&scenario=&as=signedOut", "document.querySelector('[data-go-on]')")
    through(f)
    f.wait("document.querySelector('[data-lesson-end], [data-note-result]')", what="the lesson's end in week one")
    f.js("new Promise((r) => setTimeout(r, 600))")
    assert not f.js("!!document.querySelector('[data-voice-care]')"), "the note in week one"
    f.run(f"localStorage.removeItem('{DAY}')")
    started(f, 10)  # past the first week from here on

    # The cool-down, as a guest: it ends with its own line, no stars, nothing kept.
    f.go("/learn?api=mock&scenario=&as=signedOut", "document.querySelector('[data-cooldown-link]')")
    xp_before = f.js(f"(await {STORE}.loadLearning()).progress.xp?.total ?? 0")
    f.run("document.querySelector('[data-cooldown-link] a').click()")
    f.wait("location.pathname === '/cooldown' && document.querySelector('main h1')", what="the cool-down")
    f.shot("cooldown-start")
    through(f)
    f.wait("document.querySelector('[data-unscored-end]')", timeout=30, what="the cool-down's own end")
    end_line = f.js("(await import('/js/i18n.js')).localized((await (await fetch('/course/cooldown.json')).json()).units[0].lessons[0].endLine)")
    assert f.js("document.querySelector('[data-unscored-end] h1').textContent.trim()") == end_line, "its own last line"
    assert not f.js("!!document.querySelector('[data-lesson-end], [data-voice-care]')"), "no stars, no note on the cool-down"
    progress = json.loads(f.js(f"JSON.stringify((await {STORE}.loadLearning()).progress)"))  # a plain copy: a reactive one comes back empty
    assert "cool-down-1" not in (progress.get("lessons") or {}), "the cool-down kept as a lesson"
    assert (progress.get("xp") or {}).get("total", 0) == xp_before, "XP from the cool-down"
    f.shot("cooldown-end")

    # 21 minutes sung today: a lesson's end says so, once.
    f.run("sessionStorage.removeItem('stackapella.lesson.1-comfortable')")
    sung(f, 21)
    f.go("/learn/1-comfortable?api=mock&scenario=&as=signedOut", "document.querySelector('[data-go-on]')")
    through(f)
    f.wait("document.querySelector('[data-voice-care]')", timeout=20, what="the 20 minute note")
    assert f.js("document.querySelector('[data-voice-care]').dataset.minutes") == "20", "the 20 minute note"
    assert f.js("!!document.querySelector('[data-voice-care-cooldown]')"), "the cool-down a tap away"
    f.run("document.querySelector('[data-voice-care]').scrollIntoView({ block: 'center' })")
    f.shot("note")
    f.run("sessionStorage.removeItem('stackapella.lesson.1-comfortable')")
    f.go("/learn/1-comfortable?api=mock&scenario=&as=signedOut", "document.querySelector('[data-go-on]')")
    through(f)
    f.wait("document.querySelector('[data-lesson-end], [data-note-result]')", what="the lesson's end again")
    f.js("new Promise((r) => setTimeout(r, 600))")
    assert not f.js("!!document.querySelector('[data-voice-care]')"), "the note again the same day"

    # Settings' switch off: no note, even at 40.
    f.go("/settings?api=mock&scenario=&as=signedOut", "document.querySelector('[data-voice-notes] [role=switch]')")
    f.run("document.querySelector('[data-voice-notes] [role=switch]').click()")
    f.wait("localStorage.getItem('stackapella.voiceNotesOff') === '1'", what="the notes off")
    sung(f, 41, [20])
    f.run("sessionStorage.removeItem('stackapella.lesson.1-comfortable')")
    f.go("/learn/1-comfortable?api=mock&scenario=&as=signedOut", "document.querySelector('[data-go-on]')")
    through(f)
    f.wait("document.querySelector('[data-lesson-end], [data-note-result]')", what="the lesson's end")
    f.js("new Promise((r) => setTimeout(r, 600))")
    assert not f.js("!!document.querySelector('[data-voice-care]')"), "a note with the switch off"
    f.run(f"localStorage.removeItem('stackapella.voiceNotesOff'); localStorage.removeItem('{DAY}')")


run("voice_care", journey)
