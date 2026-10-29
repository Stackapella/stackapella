"""The Perfect your pitch course (docs/courses/perfect-pitch.md), as staff: the course page with its
map, every lesson opening on its Why, and Bullseye: the guide sings, you answer, and each
answer lands as a dart, then Hear yourself says where. While the course is still "soon" in the
catalogue (hidden even from staff), the flow opens it in memory as if it were live. Over loopback
the mic hears only what plays, so the take echoes your own note over a drone on it: every answer
lands dead centre. With OUT=<dir> it shoots the course page, lesson 1, the board and its result in
Warm light and Stage dark at both sizes:

    OUT=~/Code/shots-review/pitch PORT=8431 tool/flows.sh course_pitch
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import compress  # noqa: E402
from plus_courses import picture, t  # noqa: E402

STORE = "(await import('/js/stores/learning.js'))"
ROUTER = "(await import('/js/router.js'))"
AUDIO = "(await import('/js/audio.js'))"

LESSONS = {
    "pp-1-bullseye": "Land right on a note",
    "pp-1-scoop": "A scoop is sliding up into a note",
    "pp-1-leaps": "Do is your note",
    "pp-1-challenge": "The Westminster chimes",
    "pp-2-ring": "Two notes a little apart beat",
    "pp-2-fifth": "A drone's strongest partners",
    "pp-2-home": "Stepping away and back",
    "pp-2-long": "Breath running out and repeated notes",
    "pp-2-challenge": "An old North American folk song",
    "pp-3-chords": "When the chord changes, your note's job changes",
    "pp-3-pull": "A part close to yours pulls at your ear",
    "pp-3-stack": "Record mi, then hold do under it",
    "pp-3-challenge": "A pedal note: you hold do",
    "pp-4-cold": "Many people start a song they know well",
    "pp-4-keep": "Holding a note in your head",
    "pp-4-from": "One anchor gives you every note",
    "pp-4-challenge": "No note given: find your do",
}


def open_pitch(f):
    """Staff, with Perfect your pitch shown as if it were live (it stays "soon" until every lesson plays)."""
    f.go("/?api=mock&scenario=&as=admin", "document.querySelector('#app header')")
    f.run("localStorage.setItem('stackapella.installDismissed', '1');")
    f.go("/learn", "document.querySelector('[data-course-map]')")
    f.wait(f"{STORE}.learning.catalogue.some((c) => c.id === 'pitch')", what="the catalogue")
    f.run(f"""
        const store = {STORE};
        const data = await (await fetch('/course/plus/pitch/course.json')).json();
        store.learning.catalogue.find((c) => c.id === 'pitch').status = 'live';
        store.learning.courses = {{ ...store.learning.courses, pitch: data }};
    """)


def journey(f):
    open_pitch(f)
    f.wait("document.querySelector('[data-more-courses] [data-course=pitch]')", what="Perfect your pitch in More courses")
    f.run(f"{ROUTER}.navigate('/learn/c/pitch')")
    f.wait("document.querySelector('[data-up-next]') && document.querySelector('[data-course-map]')", what="Up next and the map")
    f.wait_text("Bullseye")
    f.no_sideways("the course page")
    picture(f, "pitch-course-page")

    for lesson, headline in LESSONS.items():
        if lesson == "pp-2-ring":
            # Each unit opens at two thirds of the stars of the unit before: every lesson sung, with three stars.
            f.run(f"""
                const store = {STORE};
                const lessons = Object.fromEntries(store.learning.courses.pitch.units.flatMap((u) => u.lessons).map((l) => [l.id, {{ stars: 3, bestPercent: 95, at: '2026-10-29T10:00:00Z' }}]));
                store.learning.progress.courses = {{ ...(store.learning.progress.courses ?? {{}}), pitch: {{ lessons }} }};
            """)
        f.run(f"{ROUTER}.navigate('/learn/{lesson}')")
        f.wait_text(headline)
        f.no_sideways(lesson)
        if lesson == "pp-1-bullseye":
            picture(f, "pitch-lesson-1")

    # Bullseye over loopback: two echoes of your own note over a drone on it, so each lands centre.
    f.mic_checked()
    f.run(f"{ROUTER}.navigate('/learn/pp-1-bullseye')")
    f.wait_text(LESSONS["pp-1-bullseye"])
    f.run(f"""
        const lesson = {STORE}.learning.courses.pitch.units[0].lessons[0];
        lesson.steps[1].exercise = {{ tempo: 90, echoes: [0, 0], drone: {{ step: 0 }}, generous: true, keep: 'flow.pitch', thenAndNow: 'flow.pitch' }};
    """)
    f.js(f"{AUDIO}.unlock()")
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    f.click_t("course.continue")
    f.wait("document.querySelector('[data-bullseye-step] [data-bullseye]')", what="the board")
    f.wait_text(t(f, "bullseye.ready", {"count": 2}))
    f.no_sideways("the board")
    picture(f, "pitch-bullseye")
    f.click_t("bullseye.start")
    f.click_t("headphones.on")
    f.wait(f"{AUDIO}.engine.playing?.recording", what="recording the take")
    f.wait("document.querySelector('[data-bullseye-result] [data-bullseye-line]')", timeout=40, what="Hear yourself, with where the notes landed")
    line = f.js("document.querySelector('[data-bullseye-line]').textContent")
    assert line == t(f, "bullseye.landed", {"inside": 2, "count": 2}), f"both landed in the middle: {line}"
    f.wait("document.querySelector('[data-then-and-now]')?.textContent.includes('Then: 2 of 2')", what="then and now, the round kept on this device")
    assert f.js("document.querySelector('[data-bullseye-said]').textContent.startsWith('Note ')"), "each dart is said for a screen reader as it lands"
    darts = f.js("document.querySelectorAll('[data-bullseye] g circle[r=\"6.5\"]').length")
    assert darts == 2, f"two darts on the board ({darts})"
    assert f.js("!document.querySelector('.sticky button:last-child').disabled"), "Continue once it's sung"
    f.js(f"{AUDIO}.engine.setLoopback(false)")
    f.no_sideways("the board's result")
    picture(f, "pitch-bullseye-result")

    # The lock lane (Hear the ring's last step): a flat ribbon over the drone that glows as you near it.
    f.run("sessionStorage.setItem('stackapella.lesson.pp-2-ring', '3')")
    f.run(f"{ROUTER}.navigate('/learn/pp-2-ring')")
    f.wait("document.querySelector('[data-wave-lane] canvas')", what="the lock lane")
    f.wait_text("Hold your note over the drone until it rings")
    f.no_sideways("the lock lane")
    picture(f, "pitch-lock-lane")

    # Sing it with the guide: over loopback the mic hears a drone four semitones from the note, so
    # each take is weak; after two, the button offers a take with the guide singing too, unscored.
    f.run("sessionStorage.setItem('stackapella.lesson.pp-3-chords', '2')")
    f.run(f"""
        const lesson = {STORE}.learning.courses.pitch.units[2].lessons[0];
        lesson.steps[2].exercise = {{ tempo: 120, notes: [{{ beat: 1, length: 2, step: 4, lyric: 'oo' }}], turn: false, drone: {{ step: 0 }}, score: 'lock' }};
    """)
    f.run(f"{ROUTER}.navigate('/learn/pp-3-chords')")
    f.wait("document.querySelector('[data-exercise]') || document.querySelector('canvas')", what="the play step")
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    f.run("document.querySelector('.glass-bubble-solid.size-20').closest('button').click()")
    f.wait(f"{AUDIO}.engine.playing?.recording", what="weak take 1")
    # Play again's words: "for more stars" in the game skin, plain in the serious one.
    again = "results.playAgainStars" if f.js("document.documentElement.dataset.skin !== 'serious'") else "course.replay"
    f.wait_text(t(f, again), timeout=30)
    assert not f.js("[...document.querySelectorAll('[data-sing-with-guide]')].some((b) => b.offsetParent)"), "not after one weak take"
    f.click_t(again)  # Play again starts the next take itself
    f.wait(f"{AUDIO}.engine.playing?.recording", what="weak take 2")
    f.wait(f"[...document.querySelectorAll('[data-sing-with-guide]')].some((b) => b.offsetParent)", timeout=30, what="Sing it with the guide beside Play again, after two weak takes")
    f.no_sideways("Sing it with the guide")
    picture(f, "pitch-with-guide", "[...document.querySelectorAll('[data-sing-with-guide]')].find((b) => b.offsetParent).scrollIntoView({ block: 'center' })")
    f.run("[...document.querySelectorAll('[data-sing-with-guide]')].find((b) => b.offsetParent).click()")
    f.wait_text(t(f, "course.withGuideNote"))
    f.wait(f"!!{AUDIO}.engine.playing && !{AUDIO}.engine.playing.recording", what="the guide singing along, not recorded")
    f.wait(f"!{AUDIO}.engine.playing", timeout=20, what="the guided take ending")
    f.wait_text(t(f, again), timeout=10)  # the kept result is back, and Finish with it
    assert f.js("!document.querySelector('.sticky button:last-child').disabled"), "going on still allowed on the kept result"
    f.js(f"{AUDIO}.engine.setLoopback(false)")
    compress()


if __name__ == "__main__":
    run("course_pitch", journey)
