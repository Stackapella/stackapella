"""Hillel's review of every place that shows notes as glossy bricks (desktop): Sing's Words, Notes and
Staff (and a breath mark), part by part's phrase, the chooser, a lesson's exercise and a unit's challenge, the workout and
the studio; in dark, and a few in light. Shots only: tool/notes_sheet.py joins them into one picture."""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import AUDIO, SESSION, clear_projects, new_song, t, theme  # noqa: E402

LIGHT = ("sing-notes", "coach", "lesson", "studio")


def sing_on(f, grace, way, view="words"):
    f.go(f"/p/{grace}/sing", "document.querySelector('#app header')")
    f.wait(f"{SESSION}.session.parts.length > 3", what="the parts")
    f.run(f"""const s = {SESSION}; s.session.practice.sing = {{ ...(s.session.practice.sing ?? {{}}), way: '{way}', fitSaid: true, keyChosen: true }};
        s.practise(s.session.parts.find((p) => p.templatePartKey === 'alto')); s.session.practice.sing.view = '{view}'; s.savePractice();""")
    f.wait("!document.body.innerText.includes(" + repr(t(f, "sing.chooser.yourPart")) + ")", what="past the chooser")


def lesson(f, lesson_id, kind="play", last=False):
    f.run(f"""const {{ findLesson, loadLearning, setHome }} = await import('/js/stores/learning.js'); await loadLearning(); await setHome(57);
        const steps = findLesson('{lesson_id}').lesson.steps; const i = {'steps.map((s) => s.kind).lastIndexOf' if last else 'steps.map((s) => s.kind).indexOf'}('{kind}');
        sessionStorage.setItem('stackapella.lesson.{lesson_id}', String(i));""")
    f.go(f"/learn/{lesson_id}", "document.querySelector('.lane-stage, [data-wait-lane]')")


def shots(f, mode):
    keep = (lambda name: True) if mode == "dark" else (lambda name: name in LIGHT)
    theme(f, mode, "stage")
    clear_projects(f)
    grace = new_song(f)
    if keep("sing-words"):
        sing_on(f, grace, "whole", "words")
        f.run(f"{SESSION}.seek(14 * 60 / {SESSION}.session.project.bpm)")
        f.shot(f"{mode}-sing-words")
    for view in ("notes", "staff"):
        if keep(f"sing-{view}"):
            sing_on(f, grace, "whole", view)
            f.run(f"{SESSION}.seek(14 * 60 / {SESSION}.session.project.bpm)")
            f.wait("document.querySelector('main canvas, main svg')", what=f"the {view}")
            f.shot(f"{mode}-sing-{view}")
    if keep("sing-breath"):
        # The breath mark: the end of the first line, the second line coming.
        sing_on(f, grace, "whole", "notes")
        f.run(f"{SESSION}.seek(9 * 60 / {SESSION}.session.project.bpm)")
        f.wait("document.querySelector('main canvas')", what="the lane")
        f.shot(f"{mode}-sing-breath")
    if keep("coach"):
        sing_on(f, grace, "parts")
        f.wait("document.querySelector('[data-phrase-stage]')", what="the phrase")
        f.shot(f"{mode}-coach")
    if keep("chooser"):
        other = new_song(f)
        f.go(f"/p/{other}/sing", "document.querySelector('#app h1')")
        f.shot(f"{mode}-chooser")
    if keep("lesson"):
        lesson(f, "1-high-low")
        f.shot(f"{mode}-lesson")
    if keep("challenge"):
        lesson(f, "1-challenge", last=True)
        f.shot(f"{mode}-challenge")
    if keep("workout"):
        f.go("/workout", "document.querySelector('#app main')")
        f.wait("document.querySelector('.lane-stage, [data-wait-lane], canvas')", what="the workout's notes")
        f.shot(f"{mode}-workout")
    if keep("studio"):
        f.go(f"/p/{grace}", "document.querySelectorAll('canvas').length > 3")
        f.shot(f"{mode}-studio")


def journey(f):
    if f.size != "desktop":
        return
    f.go("/", "document.querySelector('#app header')")
    f.js(f"{AUDIO}.unlock()")
    for mode in ("dark", "light"):
        shots(f, mode)
    theme(f, "dark", "stage")


if __name__ == "__main__":
    run("notes_sheet", journey)
