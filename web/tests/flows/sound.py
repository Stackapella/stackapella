"""Hillel: "I'm not hearing sounds any more". Play must make sound: the engine's output, tapped with
an analyser, is not silent when Sing, the studio and a lesson play, through their own Play buttons.
Silence anywhere fails, so it can't happen silently again."""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import AUDIO, SESSION, clear_projects, new_song, t  # noqa: E402

TAP = f"""(async () => {{
    const {{ engine }} = {AUDIO};
    if (!window.__tap) {{
        window.__tap = engine.ctx.createAnalyser();
        window.__tap.fftSize = 2048;
        engine.playBus.connect(window.__tap);
    }}
    window.__peak = 0;
    const data = new Float32Array(2048);
    const read = () => {{ window.__tap.getFloatTimeDomainData(data); for (const v of data) window.__peak = Math.max(window.__peak, Math.abs(v)); if (window.__listening) requestAnimationFrame(read); }};
    window.__listening = true;
    read();
}})()"""


def heard(f, where, seconds=3):
    f.js(TAP)
    f.js(f"new Promise((r) => setTimeout(r, {seconds * 1000}))")
    peak = f.js("(() => { window.__listening = false; return window.__peak; })()")
    print(f"    sound {where}: peak {peak:.3f}")
    assert peak > 0.01, f"{where}: Play made no sound (peak {peak})"


def journey(f):
    if f.size != "desktop":
        return
    f.go("/voice", "document.querySelector('#app header, #app main')")  # an app address (on the site's host, / is the landing)
    f.mic_checked()  # a lesson's exercise listens too
    f.js(f"{AUDIO}.unlock()")
    clear_projects(f)
    song = new_song(f)
    # Sing, the whole song, through the transport's Play.
    f.go(f"/p/{song}/sing", "document.querySelector('#app header')")
    f.wait(f"{SESSION}.session.parts.length > 3", what="the parts")
    f.run(f"""const s = {SESSION}; s.session.practice.sing = {{ ...(s.session.practice.sing ?? {{}}), way: 'whole', fitSaid: true, keyChosen: true }};
        s.practise(s.session.parts.find((p) => p.templatePartKey === 'soprano')); s.savePractice();""")
    f.wait("!document.body.innerText.includes(" + repr(t(f, "sing.chooser.yourPart")) + ")", what="past the chooser")
    f.click_t("studio.play")
    f.wait(f"{SESSION}.session.playing", what="Sing playing")
    heard(f, "Sing")
    # Hillel's repro: Pause, then Play again, must sound and move.
    f.click_t("studio.pause")
    f.wait(f"!{SESSION}.session.playing", what="paused")
    at = f.js(f"{SESSION}.session.position")
    f.click_t("studio.play")
    f.wait(f"{SESSION}.session.playing", what="playing again")
    heard(f, "Sing after Pause and Play")
    moved = f.js(f"{SESSION}.session.position")
    assert moved > at + 0.5, f"the playhead didn't move after Play again: {at} then {moved}"
    f.run(f"await {SESSION}.stopPlayback()")
    # The studio.
    f.go(f"/p/{song}", "document.querySelectorAll('canvas').length > 3")
    f.click_t("studio.play")
    f.wait(f"{SESSION}.session.playing", what="the studio playing")
    heard(f, "the studio")
    f.run(f"await {SESSION}.stopPlayback()")
    # A lesson's exercise (Higher and lower), through its own Play.
    f.run("""const { findLesson, loadLearning, setHome } = await import('/js/stores/learning.js'); await loadLearning(); await setHome(57);
        const steps = findLesson('1-high-low').lesson.steps; sessionStorage.setItem('stackapella.lesson.1-high-low', String(steps.map((s) => s.kind).indexOf('play')));""")
    f.go("/learn/1-high-low", "document.querySelector('.lane-stage, [data-wait-lane]')")
    f.run("document.querySelector('button:has(> .glass-bubble-solid.ring-8)').click()")
    f.wait(f"{AUDIO}.engine.playing", what="the exercise playing")
    heard(f, "a lesson")


if __name__ == "__main__":
    run("sound", journey)
