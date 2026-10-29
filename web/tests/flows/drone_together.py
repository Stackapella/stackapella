"""Unit 2's drone (onboarding step 7, "you and one other note, ringing"): after a take against the
drone, the result's Listen back is Hear it together, your take with the drone under it. Sung over
loopback: a held tone stands in for a voice.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

AUDIO = "(await import('/js/audio.js'))"
I18N = "(await import('/js/i18n.js'))"


def journey(f):
    # Unit 1 done (unit 2 opens after it), the note found, headphones on, straight to the sung step.
    f.go("/", "document.querySelector('#app header')")
    f.mic_checked()
    f.run("""const { emptyProgress } = await import('/js/course.js'); const db = await import('/js/db.js');
        const done = { stars: 3, bestPercent: 95, at: Date.now() };
        const lessons = Object.fromEntries(['1-comfortable', '1-match', '1-high-low', '1-breath', '1-challenge'].map((id) => [id, done]));
        await db.put('learning', { ...emptyProgress(), startedAt: Date.now() - 86400000, home: 57, range: { low: 50, high: 64 }, lessons }, 'progress');
        sessionStorage.setItem('stackapella.lesson.2-drone', '1'); sessionStorage.setItem('stackapella.headphones', '1');""")
    f.go("/learn/2-drone", "document.querySelector('main h1')")
    f.js(f"{AUDIO}.unlock()")
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    f.click_t("course.tap.sing")
    f.wait(f"{AUDIO}.engine.playing?.recording", what="recording")
    f.run(f"""const {{ engine }} = {AUDIO}; const {{ renderPart }} = await import('/js/synth.js');
        engine.preview(renderPart([{{ beat: 0, length: 9, pitch: 57 }}], {{ bpm: 72, sampleRate: engine.sampleRate }}), engine.sampleRate);""")
    f.wait("document.querySelector('[data-take-result]')", timeout=40, what="the take's result")
    f.wait(f"document.body.innerText.includes({I18N}.t('results.hearTogether'))", what="Hear it together")
    assert not f.js("!!document.querySelector('[data-with-guide]')"), "With the guide instead of the drone"
    f.shot("drone-together")


if __name__ == "__main__":
    run("drone_together", journey)
