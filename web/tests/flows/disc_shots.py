"""Serious mode's play and record discs (docs/bubble-circles-plan.md, step 5), shot for Hillel's review in Warm light
and Stage dark at both sizes: a lesson's Play, a take's result with Listen back, Sing's transport (Play and Record),
Sing while recording (Stop), and the studio's Play and Record.

    OUT=~/Code/shots-review/discs OUT_DARK=1 FLOW_SKINS=serious PORT=8410 tool/flows.sh disc_shots
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import OUT, compress, new_song, shoot  # noqa: E402

AUDIO = "(await import('/js/audio.js'))"
I18N = "(await import('/js/i18n.js'))"


def journey(f):
    assert OUT, "the shots need OUT=<dir>"
    f.go("/learn/1-match?api=mock&as=member", "document.querySelector('main h1')")
    f.mic_checked()
    # A lesson's Play: the exercise step, before it starts.
    f.run("sessionStorage.setItem('stackapella.lesson.1-match', '2'); sessionStorage.setItem('stackapella.headphones', '1');")
    f.go("/learn/1-match?api=mock&as=member", "document.querySelector('main h1')")
    f.wait("document.querySelector('main .size-20')", what="the lesson's Play")
    shoot(f, "disc-lesson-play", scroll="document.querySelector('main .size-20').scrollIntoView({ block: 'center' })")
    # A take's result: sung over loopback, then Listen back.
    f.js(f"{AUDIO}.unlock()")
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    f.click_t("course.tap.sing")
    f.wait(f"{AUDIO}.engine.playing?.recording", what="recording")
    beat = 60 / 72
    f.run(f"""const {{ engine }} = {AUDIO}; const {{ renderPart }} = await import('/js/synth.js');
        const until = performance.now() + 20000; while ((engine.positionSeconds() ?? 0) < {9 * beat - 0.12}) {{ if (performance.now() > until) throw new Error('the audio clock never reached ' + {9 * beat - 0.12} + ' s in 20 s: stalled?'); await new Promise((r) => requestAnimationFrame(r)); }}
        engine.preview(renderPart([{{ beat: 0, length: 2, pitch: 57 }}, {{ beat: 3, length: 2, pitch: 59 }}, {{ beat: 6, length: 2, pitch: 54 }}], {{ bpm: 72, sampleRate: engine.sampleRate }}), engine.sampleRate);""")
    f.wait("document.querySelector('[data-take-result]')", timeout=40, what="the take's result")
    shoot(f, "disc-result-listen", scroll="document.querySelector('[data-take-result]').scrollIntoView({ block: 'start' }); window.scrollBy(0, -16)", wait=1.6)
    f.js(f"{AUDIO}.engine.setLoopback(false)")
    # Sing: the transport, then recording (Stop).
    project = new_song(f)
    f.go(f"/p/{project}/sing", "document.querySelector('[data-way=whole], [data-play]')")
    if f.js("!!document.querySelector('[data-way=whole]')"):
        f.run("document.querySelector('[data-way=whole]').click()")
        alto = f.js(f"{I18N}.t('parts.alto')")
        alto_button = f"[...document.querySelectorAll('main button, .card-interactive')].find((b) => b.textContent.includes({alto!r}))"
        f.wait(f"document.querySelector('[data-play]') || {alto_button}", what="the transport or a part to choose")
        if f.js(f"!document.querySelector('[data-play]')"):
            f.run(f"{alto_button}.click()")
    f.wait("document.querySelector('[data-play]')", what="Sing's transport")
    shoot(f, "disc-sing", scroll="window.scrollTo(0, document.body.scrollHeight)")
    f.js(f"{AUDIO}.unlock()")
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    f.run("sessionStorage.setItem('stackapella.headphones', '1')")
    f.run(f"const name = {I18N}.t('sing.recordPart', {{ name: '' }}).trim().split(' ')[0]; [...document.querySelectorAll('.sing-controls button[aria-label]')].find((x) => x.offsetParent && x.getAttribute('aria-label').startsWith(name)).click();")
    f.wait(f"document.querySelector('[role=dialog] select') || {AUDIO}.engine.playing?.recording", timeout=20, what="the timing check or recording")
    if f.js("!!document.querySelector('[role=dialog] select')"):
        f.click_t("timing.start")
        f.wait(f"document.body.innerText.includes({I18N}.t('timing.startRecording'))", timeout=40, what="the timing measured")
        f.click_t("timing.startRecording")
    f.wait(f"{AUDIO}.engine.playing?.recording", timeout=20, what="recording")
    shoot(f, "disc-sing-recording", scroll="window.scrollTo(0, document.body.scrollHeight)", wait=1.2)
    f.run("document.querySelector('[data-stop]')?.click() ?? document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));")
    f.js(f"{AUDIO}.engine.setLoopback(false)")
    # The studio: Play and Record.
    f.go(f"/p/{project}", "document.querySelectorAll('canvas').length > 3")
    shoot(f, "disc-studio", scroll="window.scrollTo(0, document.body.scrollHeight)")
    compress()


if __name__ == "__main__":
    run("disc_shots", journey)
