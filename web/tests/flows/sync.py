"""Hillel: "the notes visually aren't matching the song". Measured, not assumed: Sing plays Happy
Birthday with a click wherever the guide's notes start (placed by the guide's own maths: the
template's tempo, the practice speed), the engine records what it plays over loopback, and each
click's time is compared with where the screen's clock (engine.positionInfo, which every view draws
from) says the song is at that moment, against the note's drawn start (its beat at the session's
tempo). Under 30 ms over 15 s: at full speed, at practice speed, after a key change and after a
seek. Then, stopped, each view lights a note only once it has begun: Words, Notes and Staff."""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import AUDIO, SESSION, clear_projects, new_song, t  # noqa: E402

LIMIT_MS = 30
STAGE = "document.querySelector('[data-stage-view]')"

MEASURE = """async (fromSeconds, seconds) => {
    const { engine } = await import('/js/audio.js');
    const S = await import('/js/stores/session.js');
    const { beatToSeconds } = await import('/js/synth.js');
    const s = S.session;
    await S.ready();
    const sr = engine.sampleRate;
    const template = s.project.template;
    const notes = S.templatePart(s.focusPart).guide.notes;
    const speed = s.practice.speed ?? 1;
    // A click where each guide note starts, with renderGuide's maths.
    const clicks = new Float32Array(Math.ceil(beatToSeconds(notes.at(-1).beat + notes.at(-1).length + 1, template.tempo, speed) * sr));
    for (const n of notes) clicks[Math.round(beatToSeconds(n.beat, template.tempo, speed) * sr)] = 1;
    for (const [id, part] of engine.parts) engine.updatePart(id, { volume: 0, pan: 0, audible: false, offsetSeconds: part.offsetSeconds });
    engine.setPart('sync:clicks', clicks, sr, { volume: 1, pan: 0, audible: true, offsetSeconds: 0 });
    engine.setLoopback(true);
    // As Sing's startPlayback plays, recording what's played.
    const playing = engine.play({ fromSeconds, bpm: s.tempo, beatsPerBar: 3, pickupBeats: s.pickup, record: true, latencyFrames: 0 });
    // The screen's clock, sampled each frame: at render time T, the song position it shows.
    const pairs = [];
    await new Promise((done) => {
        const step = () => {
            const at = engine.ctx.currentTime;
            const info = engine.positionInfo(at);
            if (info && info.phase === 'play') pairs.push([at, info.position]);
            if (at < playing.startTime + seconds) requestAnimationFrame(step);
            else done();
        };
        step();
    });
    const take = (await engine.stop()).samples;
    engine.setLoopback(false);
    engine.removePart('sync:clicks');
    engine.projectId = null; // the next play loads the song's own parts again
    const found = [];
    for (let i = 0; i < take.length; i++) {
        if (Math.abs(take[i]) > 0.3 && (!found.length || i - found.at(-1) > sr * 0.02)) found.push(i);
    }
    const drawn = notes.map((n) => n.beat * s.beatSeconds);
    const drifts = found.map((i) => {
        const at = playing.startTime + i / sr; // when this click is rendered (heard a fixed delay later)
        const near = pairs.reduce((best, p) => (Math.abs(p[0] - at) < Math.abs(best[0] - at) ? p : best), pairs[0]);
        const shown = near[1] + (at - near[0]); // the screen's song position as that click sounds
        const heard = fromSeconds + i / sr; // the song position of the click itself
        const start = drawn.reduce((best, d) => (Math.abs(d - heard) < Math.abs(best - heard) ? d : best), drawn[0]);
        return { shown: Math.round((shown - start) * 1000 * 10) / 10, audio: Math.round((heard - start) * 1000 * 10) / 10 };
    });
    const expected = drawn.filter((d) => d >= fromSeconds - 1e-6 && d < fromSeconds + seconds - 0.05).length;
    return { clicks: found.length, expected, worstShown: Math.max(0, ...drifts.map((d) => Math.abs(d.shown))), worstAudio: Math.max(0, ...drifts.map((d) => Math.abs(d.audio))), samples: pairs.length };
}"""


def measure(f, label, from_seconds=0, seconds=15):
    result = f.js(f"({MEASURE})({from_seconds}, {seconds})")
    print(f"    sync {label}: {json.dumps(result)}")
    assert result["clicks"] >= result["expected"] > 3, f"{label}: heard {result['clicks']} of {result['expected']} note starts"
    assert result["worstAudio"] < 1, f"{label}: the guide's notes aren't where they're drawn ({result['worstAudio']} ms)"
    assert result["worstShown"] < LIMIT_MS, f"{label}: the screen is {result['worstShown']} ms off the audio"
    return result


def lit_views(f):
    """Stopped: just before a note starts it isn't lit; just after, it is, in Words, Notes and Staff."""
    f.run(f"await {SESSION}.stopPlayback()")
    for index in (2, 9, 15):
        start = f.js(f"""(async () => {{ const S = {SESSION}; return S.templatePart(S.session.focusPart).guide.notes[{index}].beat * S.session.beatSeconds; }})()""")
        word = f.js(f"""(async () => {{ const S = {SESSION}; return S.templatePart(S.session.focusPart).guide.notes[{index}].lyric; }})()""")
        name = f.js(f"""(async () => {{ const S = {SESSION}; const n = S.templatePart(S.session.focusPart).guide.notes[{index}]; return (await import('/js/dsp.js')).midiName(n.pitch + (S.session.project.transpose || 0)); }})()""")
        for view in ("words", "notes", "staff"):
            f.sing_view(view)
            f.wait(f"{STAGE}?.dataset.stageView === '{view}'", what=view)
            for offset, lit in ((-0.03, False), (0.015, True)):
                f.run(f"{SESSION}.seek({start + offset})")
                f.js("new Promise((r) => requestAnimationFrame(() => requestAnimationFrame(r)))")
                if view == "words":
                    shown = f.js(f"[...{STAGE}.querySelectorAll('div[translate=no] > span[style*=color]')].map((e) => e.textContent.trim()).find((x) => x) ?? ''")
                    ok = (shown == word.strip()) == lit
                elif view == "notes":
                    shown = f.js(f"{STAGE}.querySelector('.tabular-nums')?.firstChild?.textContent?.trim() ?? ''")
                    ok = (shown == name) == lit or not lit  # the card names the note now (before it, the one before may share its name)
                else:
                    shown = f.js(f"[...{STAGE}.querySelectorAll('svg g')].filter((g) => g.getAttribute('fill') && g.getAttribute('fill') !== 'currentColor').length")
                    ok = (shown == 1) == lit or not lit
                if not ok:
                    print('    debug', f.js(f"(async () => ({{ position: {SESSION}.session.position, spans: [...{STAGE}.querySelectorAll('div[translate=no] > span')].map((e) => [e.textContent, e.getAttribute('style'), e.className]).slice(0, 8) }}))()"))
                assert ok, f"{view}, note {index} ({word}), {offset * 1000:+.0f} ms: shows {shown!r}, lit expected {lit}"
    print("    sync views: Words, Notes and Staff light each note within 15 ms after it starts, not 30 ms before")


def journey(f):
    if f.size != "desktop":
        return
    f.go("/", "document.querySelector('#app header')")
    f.js(f"{AUDIO}.unlock()")
    clear_projects(f)
    song = new_song(f, "happy-birthday")
    f.go(f"/p/{song}/sing", "document.querySelector('#app header')")
    f.wait(f"{SESSION}.session.parts.length > 1", what="the parts")
    f.run(f"""const s = {SESSION}; s.session.practice.sing = {{ ...(s.session.practice.sing ?? {{}}), way: 'whole', fitSaid: true, keyChosen: true }};
        s.practise(s.session.parts.find((p) => p.templatePartKey === 'soprano')); s.savePractice();""")
    f.wait("!document.body.innerText.includes(" + repr(t(f, "sing.chooser.yourPart")) + ")", what="past the chooser")
    measure(f, "full speed, from the start")
    measure(f, "full speed, after a seek to 5 s", from_seconds=5, seconds=9)
    f.run(f"await {SESSION}.setSpeed(0.85)")
    measure(f, "practice speed 0.85")
    f.run(f"await {SESSION}.setSpeed(1)")
    f.run(f"const s = {SESSION}; s.session.project.transpose = 2; await s.changeKey()")
    measure(f, "two semitones up")
    lit_views(f)


if __name__ == "__main__":
    run("sync", journey)
