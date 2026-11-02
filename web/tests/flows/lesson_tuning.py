"""Tune the chord in a lesson (exercise.tuning, the courses' slice 4): on a scored take, a held note
in the step's chord shows Sing's ring meter under the lane, as a view only; never through a speaker.
The free course's drone step stands in (the flag set on it here), sung over loopback: a held tone on
the drone's note is its root, so it rings.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

AUDIO = "(await import('/js/audio.js'))"
I18N = "(await import('/js/i18n.js'))"
TUNING = """const { learning } = await import('/js/stores/learning.js');
    const lesson = learning.course.units.flatMap((u) => u.lessons).find((l) => l.id === '2-drone');
    for (const step of lesson.steps) if (step.exercise) step.exercise.tuning = TUNING;"""


def sing(f, pitch=57, beats=9):
    f.js(f"{AUDIO}.unlock()")
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    f.click_t("course.tap.sing")
    f.wait(f"{AUDIO}.engine.playing?.recording", what="recording")
    f.run(f"""const {{ engine }} = {AUDIO}; const {{ renderPart }} = await import('/js/synth.js');
        engine.preview(renderPart([{{ beat: 0, length: {beats}, pitch: {pitch}, vibrato: {{ cents: 0 }} }}], {{ bpm: 72, sampleRate: engine.sampleRate }}), engine.sampleRate);""")


def journey(f):
    # Unit 1 done, the note found (A3), headphones on, straight to the drone's sung step.
    f.go("/", "document.querySelector('#app header')")
    f.mic_checked()
    f.run("""const { emptyProgress } = await import('/js/course.js'); const db = await import('/js/db.js');
        const done = { stars: 3, bestPercent: 95, at: Date.now() };
        const lessons = Object.fromEntries(['1-comfortable', '1-match', '1-high-low', '1-breath', '1-challenge'].map((id) => [id, done]));
        await db.put('learning', { ...emptyProgress(), startedAt: Date.now() - 9 * 86400000, home: 57, range: { low: 50, high: 64 }, lessons }, 'progress');
        sessionStorage.setItem('stackapella.lesson.2-drone', '1'); sessionStorage.setItem('stackapella.headphones', '1'); sessionStorage.removeItem('stackapella.speaker');""")
    f.go("/learn/2-drone", "document.querySelector('main h1')")
    f.run(TUNING.replace("TUNING", "true"))
    sing(f)
    f.wait("document.querySelector('[data-lesson-ring]')", timeout=20, what="the ring meter on the held note")
    words = f.js("document.querySelector('[data-lesson-ring]').innerText")
    assert words.startswith("The root"), f"the degree, no chord name: {words!r}"
    f.shot("ring")
    f.wait("document.querySelector('[data-take-result]')", timeout=40, what="the take's result")
    assert not f.js("document.querySelector('[data-lesson-ring]')"), "the meter goes with the take"

    # Through a speaker: no meter (the mic hears the chord as well).
    f.run("sessionStorage.removeItem('stackapella.headphones'); sessionStorage.setItem('stackapella.speaker', '1');")
    f.go("/learn/2-drone", "document.querySelector('main h1')")
    f.run(TUNING.replace("TUNING", "true"))
    sing(f)
    f.run("await new Promise((r) => setTimeout(r, 3000))")
    assert not f.js("document.querySelector('[data-lesson-ring]')"), "no meter through a speaker"

    # Barbershop's held seventh (bb-2-seventh's own step, with its `tuning`): over three partner
    # lines your note is the seventh, and sung 31 cents under the piano it rings. The course is still
    # "soon", so its step is sung in the free course's drone lesson, as it's written.
    f.run("""sessionStorage.setItem('stackapella.headphones', '1'); sessionStorage.removeItem('stackapella.speaker');""")
    f.go("/learn/2-drone", "document.querySelector('main h1')")
    f.run("""const { learning } = await import('/js/stores/learning.js');
        const bb = await (await fetch('/course/plus/barbershop/course.json')).json();
        const seventh = bb.units.flatMap((u) => u.lessons).find((l) => l.id === 'bb-2-seventh').steps.find((s) => s.exercise?.tuning);
        const lesson = learning.course.units.flatMap((u) => u.lessons).find((l) => l.id === '2-drone');
        const step = lesson.steps.find((s) => s.kind === 'play');
        step.exercise = seventh.exercise; step.text = seventh.text;""")
    f.wait("document.querySelector('main h1')?.textContent.includes('fa')", what="the held seventh's step")
    sing(f, pitch=57 + 5 - 0.31, beats=12)
    f.wait("document.querySelector('[data-lesson-ring]')", timeout=20, what="the ring meter on the held seventh")
    f.wait("document.querySelector('[data-lesson-ring]')?.dataset.ringState === 'ringing'", timeout=10, what="the seventh ringing")
    f.shot("seventh")
    words = f.js("document.querySelector('[data-lesson-ring]')?.innerText ?? ''")
    assert "The seventh" in words and "31" in words and "E7" not in words, f"the seventh's pure target, no chord name: {words!r}"

if __name__ == "__main__":
    run("lesson_tuning", journey)
