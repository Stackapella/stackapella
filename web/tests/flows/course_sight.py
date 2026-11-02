"""Sight-singing (docs/sight-singing-plan.md, slices 1 to 3), as staff while the course is "soon": a reading
step is drawn on a stave (components/read-stave.js) in the key of the singer's do, names under the
notes in solfege (Names switches them, remembered), the note lit as the clock reaches it. A cold read
offers Hear it first; then the take shows its look count, lights its notes, and its result says the
top score needs a cold read. Unit 2's finale, Ode to Joy, is read with the names off. Unit 3's waltz
shows 3/4, and its read counts in after the look, on the audio clock. Unit 4 reads a song's own line in\nits own key with its pickup, and a whole part scrolls in its card on a phone.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

STORE = "(await import('/js/stores/learning.js'))"
ROUTER = "(await import('/js/router.js'))"
AUDIO = "(await import('/js/audio.js'))"
I18N = "(await import('/js/i18n.js'))"


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def open_course(f):
    f.go("/?api=mock&scenario=&as=admin", "document.querySelector('#app header')")
    f.run("localStorage.setItem('stackapella.asks', JSON.stringify({ stopped: ['install'] }));")
    f.go("/learn", "document.querySelector('[data-course-map]')")
    f.wait(f"{STORE}.learning.catalogue.some((c) => c.id === 'sight-singing')", what="the catalogue")
    f.run(f"""
        const store = {STORE};
        const data = await (await fetch('/course/plus/sight-singing/course.json')).json();
        store.learning.catalogue.find((c) => c.id === 'sight-singing').status = 'live';
        store.learning.courses = {{ ...store.learning.courses, 'sight-singing': data }};
    """)


def open_step(f, lesson, step):
    f.run(f"sessionStorage.setItem('stackapella.lesson.{lesson}', '{step}')")
    f.run(f"{ROUTER}.navigate('/learn/c/sight-singing')")
    f.run(f"{ROUTER}.navigate('/learn/{lesson}')")
    f.wait("document.querySelector('[data-read-stave] svg')", what=f"{lesson}'s stave")


def journey(f):
    open_course(f)
    f.mic_checked()

    # Hear it, then sing it, on the stave: names on, in solfege.
    open_step(f, "ss-1-do", 1)
    names = f.js("[...document.querySelectorAll('[data-read-stave] svg text')].map((x) => x.textContent).filter((x) => /^(do|re|mi|fa|so)$/.test(x))")
    assert names[:3] == ["do", "re", "mi"] or names[0] == "do", f"solfege under the notes: {names}"
    f.no_sideways("the stave")
    f.shot("hear-with-stave")
    # Names: letters, remembered on the device.
    f.click(t(f, "course.read.style.letters"), "[data-read-stave] button")
    f.wait("(await import('/js/stores/prefs.js')).prefs.readNames === 'letters'", what="letters chosen")
    letters = f.js("[...document.querySelectorAll('[data-read-stave] svg text')].map((x) => x.textContent).filter((x) => /^[A-G][#b♯♭]?$/.test(x)).length")
    assert letters >= 3, "letter names under the notes"
    f.click(t(f, "course.read.style.solfege"), "[data-read-stave] button")

    # A cold read: Hear it first, then the take over loopback (the line sung by a partner voice).
    open_step(f, "ss-1-do", 2)
    assert f.js("!!document.querySelector('[data-hear-first]')"), "Hear it first is offered"
    f.shot("cold-read")
    f.js(f"{AUDIO}.unlock()")
    f.run("document.querySelector('[data-hear-first]').click()")
    f.wait_text(t(f, "course.read.heardFirst"), timeout=20)
    f.wait("!document.querySelector('[data-hear-first]').disabled", timeout=30, what="the line heard")
    f.run(f"""
        const lesson = {STORE}.learning.courses['sight-singing'].units[0].lessons[0];
        const ex = lesson.steps[2].exercise;
        ex.with = [{{ notes: ex.notes.map((n) => ({{ ...n, vibrato: {{ cents: 0 }} }})), volume: 1 }}];
    """)
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    f.run("document.querySelector('.glass-bubble-solid.size-20').closest('button').click()")
    if f.js(f"!!document.querySelector('[role=dialog]')"):
        f.click_t("headphones.on")
    f.wait("document.querySelector('[data-read-look]')?.textContent.trim().length > 0", timeout=10, what="the look count")
    f.shot("look")
    f.wait("document.querySelector('[data-read-stave] [data-lit=true]')", timeout=20, what="a note lit as it's read")
    f.shot("reading")
    f.wait("document.querySelector('[data-heard-first]')", timeout=60, what="the result, heard first")
    f.js(f"{AUDIO}.engine.setLoopback(false)")
    f.shot("result")

    # Unit 2's finale: Ode to Joy, names off.
    f.run(f"""
        const store = {STORE};
        const lessons = Object.fromEntries(store.learning.courses['sight-singing'].units.flatMap((u) => u.lessons).map((l) => [l.id, {{ stars: 3, bestPercent: 95, at: '2026-10-30T10:00:00Z' }}]));
        store.learning.progress.courses = {{ ...(store.learning.progress.courses ?? {{}}), 'sight-singing': {{ lessons }} }};
    """)
    open_step(f, "ss-2-challenge", 1)
    assert not f.js("[...document.querySelectorAll('[data-read-stave] svg text')].some((x) => /^(do|re|mi|fa|so|la|ti)$/.test(x.textContent))"), "names off in the finale"
    lines = f.js("document.querySelectorAll('[data-read-stave] svg').length")
    assert lines == (2 if f.size == "phone" else 1), f"four bars: two lines on a phone, one on a wide screen ({lines})"
    f.no_sideways("the finale's stave")
    f.shot("ode-to-joy")

    # Unit 3, reading time: the waltz in 3/4 on the stave, and a count of three after the look.
    open_step(f, "ss-3-dotted", 1)
    beats = f.js("[...document.querySelectorAll('[data-read-stave] svg text')].map((x) => x.textContent).slice(0, 12)")
    assert "3" in beats and "4" in beats, f"3/4 on the stave: {beats}"
    f.shot("waltz")
    f.js(f"{AUDIO}.unlock()")
    f.run("document.querySelector('.glass-bubble-solid.size-20').closest('button').click()")
    if f.js("!!document.querySelector('[role=dialog]')"):
        f.click_t("headphones.on")
    count = t(f, "course.read.counting", {"beat": 1}).rstrip("1").strip()
    f.wait(f"document.querySelector('[data-read-look]')?.textContent.includes({json.dumps(count)})", timeout=15, what="the count-in after the look")
    f.shot("count-in")
    f.run("document.querySelector('.glass-bubble-solid.size-20').closest('button').click()")

    # Unit 4: a song's own line, in its own key, with its pickup (Amazing Grace in G, one sharp, 3/4).
    open_step(f, "ss-4-signature", 1)
    sharps = f.js("[...document.querySelectorAll('[data-read-stave] svg')][0].querySelectorAll('text').length && [...[...document.querySelectorAll('[data-read-stave] svg')][0].querySelectorAll('text')].filter((x) => x.textContent === '\\u266F').length")
    assert sharps >= 1, f"the song's key signature: {sharps} sharps"
    f.shot("song-line")
    # A whole part (Michael, Row's alto): on a phone its two-bar lines scroll in their card.
    open_step(f, "ss-4-challenge", 1)
    lines = f.js("document.querySelectorAll('[data-read-stave] svg').length")
    if f.size == "phone":
        assert lines > 3 and f.js("!!document.querySelector('[data-read-stave] .overflow-y-auto').style.maxHeight"), f"a long part scrolls in its card ({lines} lines)"
    f.shot("whole-part")


if __name__ == "__main__":
    run("course_sight", journey)
