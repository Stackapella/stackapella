"""The free course's song steps open in the singer's key (Hillel, 2026-11-02; docs/song-keys-plan.md):
unit 4's Amazing Grace step for a low voice says "Soprano fits your range, sung an octave lower" under
Open in Sing, and its copy of the song opens in that key, kept as chosen (Sing doesn't fit it again);
with only the comfortable note known, the key comes from it. A copy made before stays as it was.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import SESSION, clear_projects, progress, t  # noqa: E402

LESSON = "4-words"
STEP = 1
DONE = {lesson: {"stars": 3, "bestPercent": 95, "at": 0} for lesson in ("1-comfortable", "1-match", "1-high-low", "1-breath", "1-challenge", "2-hold", "2-drone", "2-vowels",
                                                                        "2-challenge", "3-steps-up", "3-steps-down", "3-jumps", "3-challenge")}
FIT = """(async () => { const { songFit } = await import('/js/song-course.js'); const { loadTemplate } = await import('/js/templates.js'); const L = await import('/js/stores/learning.js');
    await L.loadLearning(); return songFit(await loadTemplate('amazing-grace'), 'soprano', L.knownRange(), L.learning.progress.home ?? null); })()"""
LINE = "document.querySelector('[data-fit-line]')"


def voice(f, extra):
    progress(f, DONE, 230, days=1, extra=extra)
    clear_projects(f)


def open_step(f):
    f.run(f"sessionStorage.setItem('stackapella.lesson.{LESSON}', '{STEP}');")
    f.go(f"/learn/{LESSON}?api=mock&scenario=", "document.querySelector('#app h1')")
    f.wait("document.querySelector('[data-go-on], main button')", what="the song step")


def into_sing(f):
    f.click_t("course.openIn")
    f.wait(f"location.pathname.endsWith('/sing') && {SESSION}.session.project", timeout=20, what="the song in Sing")


def journey(f):
    f.go("/voice?api=mock&scenario=", "document.querySelector('#app main')")

    # A low voice (a range from G2 to C4): the tune an octave down, said under Open in Sing.
    voice(f, ", home: 50, range: { low: 43, high: 60 }")
    open_step(f)
    fit = f.js(FIT)
    assert fit and fit["octave"] == -12, f"a low voice takes the tune an octave down: {fit}"
    f.wait(LINE, what="the key line")
    words = t(f, "sing.fitsYourRangeLower", {"part": t(f, "parts.soprano")})
    assert f.js(f"{LINE}.textContent.trim()") == words, f.js(f"{LINE}.textContent")
    f.shot("low-voice-line")
    into_sing(f)
    project = f.js(f"JSON.parse(JSON.stringify((({{ id, transpose, practice, course }}) => ({{ id, transpose: transpose ?? 0, keyChosen: !!practice?.sing?.keyChosen, fit: course?.fit ?? null }}))({SESSION}.session.project)))")
    assert project["transpose"] == fit["transpose"], f"the copy opens in the key that fits: {project} vs {fit}"
    assert project["keyChosen"], "kept as chosen, so Sing doesn't fit it again"
    assert project["fit"] and project["fit"]["octave"] == -12, f"the fit kept with the copy: {project}"
    f.shot("low-voice-sing")

    # The voice changes later (a high range): the copy already made keeps its key.
    progress(f, DONE, 230, days=1, extra=", home: 69, range: { low: 64, high: 81 }")
    open_step(f)
    f.wait(LINE, what="the key line again")
    assert f.js(f"{LINE}.textContent.trim()") == words, "the copy's own fit is said, not a new one"
    into_sing(f)
    assert f.js(f"{SESSION}.session.project.transpose ?? 0") == project["transpose"], "the copy kept its key"

    # Only the comfortable note known (one note, no range yet): the key from a beginner's range around it.
    voice(f, ", home: 57, range: null")
    open_step(f)
    fit = f.js(FIT)
    assert fit is not None, "a key from the comfortable note"
    into_sing(f)
    assert f.js(f"{SESSION}.session.project.transpose ?? 0") == fit["transpose"], f"keyed from home: {fit}"
    f.shot("from-home")


if __name__ == "__main__":
    run("song_keys", journey)
