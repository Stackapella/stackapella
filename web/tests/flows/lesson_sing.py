"""Sing opened from a lesson's song step (Hillel's "Both", 2026-11-11; the free course's 4-words had 12 starts and 1
finish): no chooser and no part picker, in either skin. A use step opens on its part, part by part, on Sing along with
the step's first phrase ready; once the step is done (20 s sung) "Back to the lesson" shows, and the lesson's Finish is
ready there. A record step opens on Record, and its button waits for a take."""

import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from flow import run  # noqa: E402

I18N = "(await import('/js/i18n.js'))"
SESSION = "(await import('/js/stores/session.js'))"
CURRENT = "document.querySelector('[data-coach] [aria-current=step]')?.textContent.trim()"


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def lesson_song(f, lesson):
    """The lesson's own copy of Amazing Grace, as the lesson makes it (learn.js courseProject)."""
    f.run(f"""const s = await import('/js/stores/projects.js'); const {{ loadTemplate }} = await import('/js/templates.js');
        const p = await s.startFromTemplate(await loadTemplate('amazing-grace'), {{ singers: 4, focusKey: 'soprano', keyChosen: true, course: {{ lessonId: {json.dumps(lesson)} }} }});
        window.lessonProject = p.id;""")
    return f.js("window.lessonProject")


def journey(f):
    f.go("/?api=mock&scenario=&as=signedOut", "document.querySelector('#app header')")
    f.run("const s = await import('/js/stores/projects.js'); await s.loadProjects(); for (const p of s.projects.all) await s.deleteProject(p.id);")

    # A use step (4-phrasing: Amazing Grace's soprano, its first two phrases): straight to Sing along, phrase 1 ready.
    project = lesson_song(f, "4-phrasing")
    f.go(f"/p/{project}/sing", "document.querySelector('[data-coach]')")
    assert not f.js("!!document.querySelector('[data-way], [data-game-select]')"), "no chooser, no part picker"
    f.wait(f"{CURRENT} === {json.dumps(t(f, 'sing.coach.step.singAlong'))}", what="on Sing along")
    focus = f.js(f"(async () => {{ const s = {SESSION}.session; return s.parts.find((p) => p.id === s.practice.focus)?.templatePartKey; }})()")
    assert focus == "soprano", focus
    assert f.js(f"{SESSION}.session.practice.sing.way") == "parts"
    assert not f.js("!!document.querySelector('[data-lesson-back]')"), "not before the step is done"
    f.shot("lesson-sing-along")
    # Sung long enough (as if 20 s had passed): the way back shows, and takes you there with Finish ready.
    f.run(f"{SESSION}.session.practice.sungSeconds = 20;")
    f.wait("document.querySelector('[data-lesson-back]')", what="Back to the lesson")
    assert f.js("document.querySelector('[data-lesson-back]').textContent.trim()") == t(f, "sing.lessonBack")
    f.shot("lesson-back-shown")
    f.run("document.querySelector('[data-lesson-back] button').click()")
    f.wait("location.pathname === '/learn/4-phrasing'", what="back in the lesson")

    # The sung time is kept with the song, so the lesson's own check (checkUse) sees the step done.
    sung = f.js(f"(async () => {{ const s = await import('/js/stores/projects.js'); await s.loadProjects(); return s.projects.all.find((p) => p.id === {json.dumps(project)})?.practice?.sungSeconds ?? null; }})()")
    assert sung is not None and sung >= 20, f"the sung time kept with the song: {sung}"

    # A record step (4-challenge): straight to Record; its way back waits for a take.
    project = lesson_song(f, "4-challenge")
    f.go(f"/p/{project}/sing", "document.querySelector('[data-coach]')")
    assert not f.js("!!document.querySelector('[data-way], [data-game-select]')"), "no chooser, no part picker"
    f.wait(f"{CURRENT} === {json.dumps(t(f, 'sing.coach.step.record'))}", what="on Record")
    f.run(f"{SESSION}.session.practice.sungSeconds = 60;")
    assert not f.js("!!document.querySelector('[data-lesson-back]')"), "a record step wants a take, not time"
    f.shot("lesson-record")

    # A song whose lesson has no such step (or none at all): Sing opens as it always has, on the chooser.
    project = lesson_song(f, "1-match")
    f.go(f"/p/{project}/sing", "document.querySelector('[data-way], [data-game-select], [data-coach]')")
    assert f.js("!!document.querySelector('[data-way], [data-game-select]')"), "the chooser, as usual"

    f.run("const s = await import('/js/stores/projects.js'); await s.loadProjects(); for (const p of s.projects.all) await s.deleteProject(p.id);")


if __name__ == "__main__":
    run("lesson_sing", journey)
