"""Hillel: the dot that shows your pitch flashed. On your turn in the Notes view, a voice with a
dropout (120 ms of silence mid-note, as when the tracker loses you) keeps its dot: the lane's head
never goes off around the dropout, and comes back to full strength after it. Shots of the dot and
its ribbon, quiet and loud.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import AUDIO, SESSION, clear_projects, light_first, new_song, t  # noqa: E402

SING = f"""const {{ engine }} = {AUDIO}; const s = {SESSION}; const {{ renderPart }} = await import('/js/synth.js');
    const alto = s.templatePart(s.session.focusPart).guide.notes.filter((n) => n.beat >= 8 && n.beat < 16).map((n) => ({{ ...n, beat: n.beat - 8 }}));
    const sr = engine.sampleRate;
    const voice = renderPart(alto, {{ bpm: 80, sampleRate: sr, transpose: s.session.project.transpose || 0 }});
    for (let i = 0; i < voice.length; i++) voice[i] *= GAIN;
    voice.fill(0, Math.round(1.5 * sr), Math.round(1.62 * sr)); // the dropout
    window.__heads = [];
    const started = performance.now();
    const watch = () => {{ const t = (performance.now() - started) / 1000; window.__heads.push([t, document.querySelector('[data-head]')?.dataset.head ?? 'none']); if (t < 2.4) requestAnimationFrame(watch); }};
    engine.preview(voice, sr); watch();"""


def journey(f):
    f.go("/voice", "document.querySelector('#app header, #app main')")
    f.mic_checked()
    light_first(f)
    clear_projects(f)
    song = new_song(f)
    f.go(f"/p/{song}/sing", "document.querySelector('#app header')")
    f.wait(f"{SESSION}.session.parts.length > 3", what="the parts")
    f.run(f"""const s = {SESSION}; s.session.practice.sing = {{ ...(s.session.practice.sing ?? {{}}), way: 'whole', view: 'notes', fitSaid: true, keyChosen: true }};
        s.practise(s.session.parts.find((p) => p.templatePartKey === 'alto')); s.setMode('own'); s.savePractice();
        for (const p of s.session.parts) if (p.templatePartKey !== 'alto') s.toggleHearing(p.id);""")
    f.wait("document.querySelector('main canvas')", what="the notes lane")
    f.js(f"{AUDIO}.unlock()")
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    f.run(f"await {AUDIO}.openMic(); {SESSION}.seek(8 * 60 / 80);")
    f.click_t("studio.play")
    f.wait_text(t(f, "sing.turn.band"), timeout=15)
    f.run(SING.replace("GAIN", "1"))
    f.js("new Promise((r) => setTimeout(r, 1550))")
    f.shot("dot-loud")
    f.js("new Promise((r) => setTimeout(r, 1000))")
    heads = f.js("window.__heads")
    around = [h for t, h in heads if 1.2 <= t <= 2.0]
    assert around and all(h == "live" for h in around), f"the dot went off around the dropout: {[h for h in around if h != 'live'][:5]} of {len(around)}"
    # Quieter singing: a smaller dot and a thinner ribbon.
    f.run(SING.replace("GAIN", "0.15"))
    f.js("new Promise((r) => setTimeout(r, 1500))")
    f.shot("dot-quiet")
    f.click_t("studio.pause")


if __name__ == "__main__":
    run("pitch_dot", journey)
