"""Song pieces in lessons, phase 1 (docs/song-snippets-plan.md; js/song-pieces.js): with the song_pieces switch on, a
lesson's last step may be a real song's line. On 2-vowels with a piece appended (Michael, Row's first line, as the
courses lead's content will add one): the drill is sung and scored, Continue leads to the piece (relaxed, its own words),
and its Skip goes straight to the lesson's end, where the stars are the drill's. A piece whose song can't open is passed
over quietly: the drill's Continue lands on the lesson's end, never an error. (Off, and withheld where the singer is: the
unit tests, tests/song-pieces.test.js.)
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import AUDIO, progress, t  # noqa: E402

LESSON = "2-vowels"


def add_piece(f, template):
    """The lesson's song piece, written into the course as read (a page load would read course.json again)."""
    f.run(f"""const {{ learning, loadLearning }} = await import('/js/stores/learning.js'); await loadLearning();
        const lesson = learning.course.units.flatMap((u) => u.lessons).find((l) => l.id === {json.dumps(LESSON)});
        lesson.steps = lesson.steps.filter((s) => !s.songPiece);
        lesson.steps.push({{ kind: 'play', songPiece: true, text: {{ en: 'Now the same thing in a real song: Michael, row the boat ashore.' }},
            exercise: {{ song: {{ template: {json.dumps(template)}, part: 'soprano', phrases: [0] }} }} }});""")


def sing_drill(f):
    """Vowels' three notes on the home note (as help_shots lesson_end sings them), scored."""
    f.js(f"{AUDIO}.unlock()")
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    f.click_t("course.goOn.play")
    f.wait_text(t(f, "course.tap.sing"))
    f.click_t("course.tap.sing")
    f.wait(f"{AUDIO}.engine.playing?.recording", timeout=20, what="the take")
    f.run(f"""const {{ engine }} = {AUDIO}; const {{ sing }} = await import('/tests/fixtures.js'); const sr = engine.sampleRate; const beat = 60 / 72;
        const notes = [0, 2, 4].map((b) => ({{ beat: b, length: 2, pitch: 57 }}));
        const sung = sing(sr, {{ notes, bpm: 72, vibrato: {{ hz: 5.5, cents: 10 }}, cents: () => 4 }});
        const lead = Math.max(0, engine.playing.startTime - engine.ctx.currentTime) + 7 * beat;
        const voice = new Float32Array(Math.round(lead * sr) + sung.length); voice.set(sung, Math.round(lead * sr)); engine.preview(voice, sr);""")
    go_on = t(f, "course.goOn.play")
    f.wait(f"[...document.querySelectorAll('main button')].some((b) => b.textContent.trim() === {go_on!r} && !b.disabled)", timeout=40, what="the drill scored")
    f.js(f"{AUDIO}.engine.setLoopback(false)")


def open_lesson(f, template, scenario=""):
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('#app header')")
    f.run("sessionStorage.setItem('stackapella.mockSongPieces', '1'); sessionStorage.setItem('stackapella.headphones', '1');")
    f.run(f"sessionStorage.removeItem('stackapella.lesson.{LESSON}')")
    f.mic_checked()
    unit1 = {lesson: {"stars": 3, "bestPercent": 95, "at": 0} for lesson in ("1-comfortable", "1-match", "1-high-low", "1-breath", "1-challenge")}
    progress(f, {**unit1, "2-hold": {"stars": 3, "bestPercent": 100, "at": 0}, "2-drone": {"stars": 2, "bestPercent": 80, "at": 0}}, 230, days=3,
             extra=", home: 57, range: { low: 55, high: 76 }")
    # The progress just written, read by a fresh page; then the piece written into the course as read, and the lesson opened
    # in the app (a page load would read course.json again, without it).
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('#app header')")
    f.wait("(await import('/js/stores/learning.js')).learning.progress.lessons?.['2-drone']", what="the progress read")
    if scenario:
        f.run(f"sessionStorage.setItem('stackapella.scenario', {json.dumps(scenario)})")
    add_piece(f, template)
    f.run(f"(await import('/js/router.js')).navigate('/learn/{LESSON}')")
    f.wait_text(t(f, "course.goOn.play"))


def journey(f):
    open_lesson(f, "michael-row")
    sing_drill(f)
    f.click_t("course.goOn.play")
    # The song piece: its own words, the song's line, relaxed.
    f.wait("document.body.innerText.includes('Now the same thing in a real song')", timeout=20, what="the song piece")
    f.wait(f"!!document.querySelector('[data-skip]')", what="its Skip")
    f.no_sideways("the song piece")
    f.shot("piece")
    # Skip: straight to the lesson's end, the stars the drill's (never a skipped lesson).
    f.run("document.querySelector('[data-skip]').click()")
    f.wait("document.querySelector('[data-lesson-end]')", what="the lesson's end")
    kept = f.js("JSON.parse(JSON.stringify((await import('/js/stores/learning.js')).learning.progress.lessons?.['2-vowels'] ?? null))")
    assert (kept or {}).get("stars", 0) >= 2, f"the drill's stars kept: {kept}"
    assert not f.js("document.body.innerText.includes(" + json.dumps(t(f, "course.songFailed")) + ")"), "no error"
    f.shot("end")

    # A piece whose song can't open: passed over quietly, the drill's Continue lands on the lesson's end.
    # (A library song the server won't give: Row, Row, Row with templates.show refused.)
    open_lesson(f, "row-row-row", scenario="templates.show:not_found")
    sing_drill(f)
    f.click_t("course.goOn.play")
    f.wait("document.querySelector('[data-lesson-end]')", timeout=20, what="the lesson's end, the piece passed over")
    assert not f.js("document.body.innerText.includes(" + json.dumps(t(f, "course.songFailed")) + ")"), "no error"
    f.run("sessionStorage.removeItem('stackapella.scenario'); sessionStorage.removeItem('stackapella.mockSongPieces');")


if __name__ == "__main__":
    run("song_piece", journey)
