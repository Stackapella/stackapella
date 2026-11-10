"""Sight-singing unit 4 (docs/sight-singing-plan.md), as staff while the course is "soon": a part read
from the stave takes its notes from the song (`exercise.song`, js/song-course.js), in the song's key
at the singer's fit; inside the chord the other parts sound while only your line is drawn. Names off.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from course_sight import open_course, open_step  # noqa: E402

STORE = "(await import('/js/stores/learning.js'))"
NAMES = "[...document.querySelectorAll('[data-read-stave] svg text')].map((x) => x.textContent).filter((x) => /^(do|re|mi|fa|so|la|ti)$/.test(x)).length"


def journey(f):
    open_course(f)
    f.mic_checked()
    unit = f.js(f"{STORE}.learning.courses['sight-singing'].units.at(-1).id")
    assert unit == "ss-part-in-chord", f"unit 4 is the course's last: {unit}"

    # Amazing Grace's second line, in its own key: the song's notes on the stave, names off (the first,
    # step 2, has them faint so the singer can check the do they found).
    open_step(f, "ss-4-signature", 3)
    f.wait("!document.querySelector('[data-song-step]')", what="the song's notes")
    assert f.js(NAMES) == 0, "names off in unit 4"
    f.no_sideways("a song read from the stave")
    f.shot("song-on-stave")

    # Inside the chord: Michael, Row's alto, the other parts as partner lines.
    open_step(f, "ss-4-chord", 1)
    f.wait("!document.querySelector('[data-song-step]')", what="the alto's notes")
    resolved = f.js(f"""(async () => {{
        const S = await import('/js/song-course.js');
        const T = await import('/js/templates.js');
        const lesson = {STORE}.learning.courses['sight-singing'].units.at(-1).lessons.find((l) => l.id === 'ss-4-chord');
        const raw = lesson.steps[1].exercise;
        const {{ exercise }} = S.songExercise(await T.loadTemplate(raw.song.template), raw);
        return {{ partners: exercise.with?.length ?? 0, staff: !!exercise.staff, tempo: exercise.tempo, notes: exercise.notes.length }};
    }})()""")
    assert resolved["partners"] == 3 and resolved["staff"] and resolved["tempo"] == 72 and resolved["notes"] > 5, f"the alto, read cold at 72, the three other parts sounding: {resolved}"
    f.no_sideways("reading inside the chord")
    f.shot("part-in-chord")


run("course_sight_parts", journey)
