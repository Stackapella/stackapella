"""The ghost of your best take (docs/game-elements-plan.md; docs/game-server-spec.md, section 3): in game
mode, while you record a part, your own best take of it is drawn as a faint dashed line on the lane under
your live dot. A guest's is kept on the device (seeded here from the part's own notes); the Ghost of
your best switch and serious mode draw none.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from game_hud import record, stop  # noqa: E402
from help_shots import AUDIO, SESSION, clear_projects, new_song, t  # noqa: E402

SEED = """const s = (await import('/js/stores/session.js')); const g = await import('/js/game/ghost.js');
    const part = s.session.parts.find((p) => p.templatePartKey === 'alto'); const tp = s.templatePart(part);
    const bpm = s.session.project.bpm || 80; const notes = tp.guide.notes; const end = Math.max(...notes.map((n) => n.beat + n.length)) * 60 / bpm;
    const frames = Math.floor(end * 20); const values = new Int16Array(frames);
    for (let i = 0; i < frames; i++) { const beat = (i / 20) * bpm / 60; const n = notes.find((m) => beat >= m.beat && beat < m.beat + m.length); values[i] = n ? Math.round((n.pitch + 0.3 * Math.sin(i / 3)) * 100) : 0; }
    const bytes = new Uint8Array(values.buffer); let text = ''; for (const b of bytes) text += String.fromCharCode(b);
    const { SCORING_VERSION } = await import('/js/scoring.js');
    g.keepDeviceGhost({ templateId: s.session.project.template.id, templateVersion: s.session.project.template.version, partKey: 'alto', scoringVersion: SCORING_VERSION }, { score: 88, rate: 20, startSeconds: 0, keyOffset: 0, pitches: btoa(text) });"""


def journey(f):
    f.go("/voice?api=mock&as=signedOut", "document.querySelector('#app header, #app main')")
    f.mic_checked()
    f.js(f"{AUDIO}.unlock()")
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    f.run("sessionStorage.setItem('stackapella.headphones', '1'); localStorage.removeItem('stackapella.gameEffects'); localStorage.removeItem('stackapella.ghosts')")
    f.run(f"""const a = {AUDIO}; await a.openMic();
        for (const route of ['speakers', 'wired', 'bluetooth']) localStorage.setItem(`stackapella.latency.${{a.audio.mic?.label ?? 'default'}}.${{route}}.${{a.engine.sampleRate}}`, '0');
        a.chooseRoute('wired');""")
    clear_projects(f)
    song = new_song(f)
    f.go(f"/p/{song}/sing", "document.querySelector('#app header')")
    f.wait(f"{SESSION}.session.parts.length > 3", what="the parts")
    f.run(SEED)  # before the part is chosen: choosing it loads the ghost
    f.run(f"""const s = {SESSION}; s.session.practice.sing = {{ ...(s.session.practice.sing ?? {{}}), way: 'whole', fitSaid: true, keyChosen: true }};
        s.practise(s.session.parts.find((p) => p.templatePartKey === 'alto')); s.savePractice();""")
    f.wait("!document.body.innerText.includes(" + repr(t(f, "sing.chooser.yourPart")) + ")", what="past the chooser")
    f.run("await new Promise((r) => setTimeout(r, 500))")
    record(f)
    f.shot("ghost")
    stop(f)
    clear_projects(f)


if __name__ == "__main__":
    run("game_ghost", journey)
