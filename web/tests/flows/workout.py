"""Today's workout (M5; Hillel's 85.png): from the course map, a warm-up in three rounds, each a half
step up, then something to work on and a review, over loopback (the guide stands in for the voice),
with Higher moving it and Skip; skipping everything counts nothing, and today's workout stays (a
sung one counts the day with its XP).
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

I18N = "(await import('/js/i18n.js'))"
AUDIO = "(await import('/js/audio.js'))"
LEARNING = "(await import('/js/stores/learning.js'))"


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def journey(f):
    f.go("/learn", "document.querySelector('h1')")
    f.mic_checked()  # the mic check (5.1) has its own journeys
    f.run(f"const l = {LEARNING}; await l.loadLearning(); await l.setHome(57); await l.finishLesson('1-match', {{ percent: 60, stars: 1 }}); await l.remember({{ percent: 60, insights: ['flatHigh'] }});")
    f.go("/learn", "document.querySelector('h1')")
    f.wait_text(t(f, "course.hub.begin"))  # the hub's workout card comes once the course is in
    f.click_t("course.hub.begin")
    f.wait_text(t(f, "workout.step.warmUp"))
    f.js(f"{AUDIO}.unlock()")
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    f.run("sessionStorage.setItem('stackapella.headphones', '1')")
    # Hillel's 85.png: the headline says the exercise, three rounds, the starting note with Lower and Higher.
    climb = f.js(f"(await import('/js/workout.js')).pickWorkout({LEARNING}.learning.progress, []).steps[0].climb")
    f.wait_text(t(f, "workout.warmUpTitle", {"climb": climb}))
    # Hillel's warm-up design: "Step 1 of 3"; the rounds as tabs with their starting notes, a half step apart;
    # the starting note as a compact − and +; each row of notes named.
    name = lambda midi: f.js(f"(await import('/js/dsp.js')).midiName({midi})")
    f.wait_text(t(f, "workout.stepOf", {"step": 1, "count": 3}))
    tabs = f.js("[...document.querySelectorAll('[data-round-tabs] [role=tab]')].map((b) => b.innerText.replace(/\\s+/g, ' ').trim())")
    assert len(tabs) == 3 and t(f, "workout.startsOn", {"note": name(57)}) in tabs[0] and t(f, "workout.startsOn", {"note": name(58)}) in tabs[1], f"the round tabs: {tabs}"
    assert name(57) in f.js("document.querySelector('[data-starting-note]').innerText"), "the starting note"
    f.click_t("workout.start")
    f.wait(f"{AUDIO}.engine.playing", what="the warm-up playing")
    f.wait_text(t(f, "workout.getReady"))  # two beats of rest, on the audio clock, before round 1
    f.shot("warm-up")
    # The rounds follow on: the second a half step up, after a breath.
    f.wait(f"document.querySelector('[data-round-status]')?.textContent.includes({t(f, 'workout.roundStatus', {'number': 2, 'count': 3})!r})", timeout=45, what="round 2")
    f.wait_text(t(f, "workout.breathe"))
    assert f.js("!!document.querySelector('[data-round-tabs] [role=tab] svg')"), "round 1 isn't ticked"
    f.shot("round-2")
    f.click_t("studio.stop")
    f.click_t("workout.skipWarmUp")
    f.wait_text(t(f, "workout.why.flatHigh"))
    f.run("document.querySelectorAll('[data-starting-note] button')[1].click()")
    f.wait(f"{LEARNING}.learning.progress.workoutShift === 1", what="Higher remembered")
    f.click_t("workout.skipStep")
    f.wait_text(t(f, "workout.step.review"))
    f.click_t("workout.skipStep")  # the last step: Skip finishes too
    # Nothing was sung through: it doesn't count, and today's workout is still there.
    f.wait_text(t(f, "workout.skippedAll"))
    f.shot("done")
    assert not f.js(f"{LEARNING}.learning.progress.counts?.workouts"), "skipping everything counted"
    f.go("/workout", "document.querySelector('h1')")
    f.wait_text(t(f, "workout.step.warmUp"))


if __name__ == "__main__":
    run("workout", journey)
