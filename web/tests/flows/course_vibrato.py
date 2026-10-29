"""The Vibrato course (web/course/plus/vibrato/course.json, docs/plus-courses-build-plan.md section 4),
as staff, who preview every unit: every lesson opens and every step shows its own screen (a Learn
step's headline, the trace lane, listen and choose, a play-along), skipping the sung ones. Then over
loopback, where the mic hears the straight drone: the ghost wave's free hold is a calm, steady note,
and the bloom honestly says the wave hasn't come yet, while the straight start still earns stars.
With OUT=<dir>, shots of the course page and those two results in Warm light and Stage dark:

    OUT=~/Code/shots-review/vibrato PORT=8297 tool/flows.sh course_vibrato
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import OUT, capture, compress, light_first, shoot, theme  # noqa: E402

I18N = "(await import('/js/i18n.js'))"
AUDIO = "(await import('/js/audio.js'))"
STORE = "(await import('/js/stores/learning.js'))"
SCREEN = {"learn": "h1", "wave": "[data-wave-lane] canvas", "choose": "[data-choose]", "play": ".lane-stage", "selfcheck": "[data-selfcheck]"}


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def picture(f, shot_id, scroll="window.scrollTo(0, 0)"):
    if OUT:
        light_first(f)
        shoot(f, shot_id, scroll=scroll)
        theme(f, "dark", "stage")
        f.wait("document.documentElement.dataset.theme === 'stage-dark'", what=f"Stage dark for {shot_id}")
        capture(f, shot_id + "-dark", "", scroll)
        light_first(f)
    else:
        f.shot(shot_id)


def sing_wave(f):
    """Sings the lane step on screen (loopback: the drone is what the mic hears) and waits for its result."""
    f.click_t("wave.sing")
    f.wait(f"{AUDIO}.engine.playing?.recording", what="recording")
    f.wait("document.querySelector('[data-wave-result] [data-wave-line]')", timeout=40, what="Hear yourself")
    return f.js("document.querySelector('[data-wave-line]').textContent")


def journey(f):
    f.go("/?api=mock&scenario=&as=admin", "document.querySelector('#app header')")
    f.run("localStorage.setItem('stackapella.installDismissed', '1'); sessionStorage.setItem('stackapella.headphones', '1');")
    f.mic_checked()
    f.go("/learn/c/vibrato", "document.querySelector('[data-course-map]')")
    picture(f, "vibrato-course-page")
    lessons = f.js(f"(async () => {{ const {{ learning }} = {STORE}; await (await import('/js/stores/learning.js')).loadLearning(); return learning.courses.vibrato.units.flatMap((u) => u.lessons.map((l) => ({{ id: l.id, kinds: l.steps.map((s) => s.kind) }}))); }})()")
    assert len(lessons) == 17, f"17 lessons: {len(lessons)}"
    # Every lesson, every step: its own screen shows (staff open every unit).
    for lesson in lessons:
        f.go(f"/learn/{lesson['id']}", "document.querySelector('h1')")
        for i, kind in enumerate(lesson["kinds"]):
            f.wait(f"document.querySelector({json.dumps(SCREEN[kind])})", what=f"{lesson['id']} step {i + 1} ({kind})")
            if i + 1 == len(lesson["kinds"]):
                break
            if kind == "learn":
                f.click_t("course.continue")
            else:
                f.click_t("course.skip")
            f.wait(f"document.querySelector('[aria-label] .tabular-nums, header .tabular-nums')?.textContent.includes('{i + 2}')", what=f"{lesson['id']} on to step {i + 2}")

    # The ghost wave over loopback: the free hold after the fade is the drone, a steady straight note.
    f.go("/learn/vib-3-ghost", "document.querySelector('h1')")
    f.js(f"{AUDIO}.unlock()")
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    f.click_t("course.continue")
    f.wait("document.querySelector('[data-wave-lane] canvas')", what="the ghost wave")
    line = sing_wave(f)
    assert line == t(f, "wave.line.freeStraight"), f"a calm, steady note: {line}"
    picture(f, "vibrato-ghost-result")
    # The lesson end's "Did you know?" card: the lesson's fact, its source a quiet link.
    f.js(f"{AUDIO}.engine.setLoopback(false)")
    f.click_t("course.finish")
    f.wait("document.querySelector('[data-fact=\"vibrato-muscle-pulses\"] a[href^=\"https://\"]')", what="the fact card, with its source")
    picture(f, "vibrato-fact-card", "document.querySelector('[data-fact]').scrollIntoView({ block: 'center' })")

    # The bloom: straight from start to end is honest: the wave "not yet", two stars for the start.
    f.go("/learn/vib-4-bloom", "document.querySelector('h1')")
    f.js(f"{AUDIO}.unlock()")
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    f.click_t("course.continue")
    f.wait("document.querySelector('[data-wave-lane] canvas')", what="the bloom")
    line = sing_wave(f)
    assert "not yet" in line, f"no wave yet, said kindly: {line}"
    picture(f, "vibrato-bloom-result")
    f.js(f"{AUDIO}.engine.setLoopback(false)")
    f.click_t("course.finish")
    f.wait(f"({STORE}.learning.progress.courses?.vibrato?.lessons?.['vib-4-bloom']?.stars ?? 0) === 2", what="two stars for the straight start")
    compress()


if __name__ == "__main__":
    run("course_vibrato", journey)
