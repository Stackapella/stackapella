"""Hillel: "when the song finishes the recording continues, it should auto stop". A take of the
whole song in Sing, with nobody pressing Stop, ends by itself just after the song's end and opens
the review (Just recorded), as Stop and keep does. Phone sizes only (it records the whole song): the review mustn't scroll sideways.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import AUDIO, SESSION, clear_projects, new_song, t  # noqa: E402
from record_mix import heard_while_recording  # noqa: E402

I18N = "(await import('/js/i18n.js'))"


def journey(f):
    if f.size == "desktop":
        return  # the phone sizes: the review is checked for sideways scrolling there
    f.go("/voice", "document.querySelector('#app header, #app main')")
    f.mic_checked()
    f.js(f"{AUDIO}.unlock()")
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    f.run("sessionStorage.setItem('stackapella.headphones', '1')")
    f.run(f"""const a = {AUDIO}; await a.openMic();
        for (const route of ['speakers', 'wired', 'bluetooth']) localStorage.setItem(`stackapella.latency.${{a.audio.mic?.label ?? 'default'}}.${{route}}.${{a.engine.sampleRate}}`, '0');
        a.chooseRoute('wired');""")
    clear_projects(f)
    song = new_song(f)
    f.go(f"/p/{song}/sing", "document.querySelector('#app header')")
    f.wait(f"{SESSION}.session.parts.length > 3", what="the parts")
    f.run(f"""const s = {SESSION}; s.session.practice.sing = {{ ...(s.session.practice.sing ?? {{}}), way: 'whole', fitSaid: true, keyChosen: true }};
        s.practise(s.session.parts.find((p) => p.templatePartKey === 'alto')); s.savePractice();""")
    f.wait("!document.body.innerText.includes(" + repr(t(f, "sing.chooser.yourPart")) + ")", what="past the chooser")
    f.press("r")
    heard_while_recording(f, "the whole song", seconds=0.5)
    length = f.js(f"{SESSION}.session.contentLength")
    f.wait(f"!{SESSION}.session.recording && document.body.innerText.includes({I18N}.t('sing.recorded.title', {{ part: {I18N}.t('parts.alto') }}))",
           timeout=length + 20, what="the take ending by itself at the song's end, into the review")
    f.wait("document.querySelector('[data-take-review]')", timeout=40, what="the review")
    # Your own harmony (onboarding item 6): with a second part of yours recorded, the review names the song and who sang it.
    assert not f.js("!!document.querySelector('[data-sung-by]')"), "sung by with one part"
    f.run(f"""const s = {SESSION}; const {{ engine }} = await import('/js/audio.js'); const {{ renderPart }} = await import('/js/synth.js');
        const part = s.session.parts.find((p) => p.templatePartKey === 'soprano');
        const samples = renderPart([{{ beat: 0, length: 4, pitch: 67 }}], {{ bpm: 80, sampleRate: engine.sampleRate }});
        await s.storeTake(part, {{ sampleRate: engine.sampleRate, frames: samples.length, offsetSeconds: 0, nudgeSeconds: 0, peaks: null }}, samples);""")
    you = f.js(f"{I18N}.t('sing.recorded.you')")
    f.wait(f"document.querySelector('[data-sung-by]')?.textContent.includes({json.dumps(you)})", what="sung by you and you")
    f.shot("review")
    f.no_sideways("the take's review")


if __name__ == "__main__":
    run("record_end", journey)
