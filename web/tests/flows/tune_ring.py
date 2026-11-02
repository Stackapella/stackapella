"""Tune the chord's meter (docs/tune-chord-plan.md, slice 2): off until Sing's settings turn on
"Tuning in held chords" (Display). Then on your own, in the Notes view, a held alto note inside one of
Amazing Grace's chords shows the meter: what the note is in the chord ("Third of G: pure is 14 cents
under the piano"), the piano and pure marks, you, and a word. The alto sung tuned pure, over
loopback, rings. Game mode glows on the lock; it never scores it.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import AUDIO, SESSION, clear_projects, new_song  # noqa: E402

I18N = "(await import('/js/i18n.js'))"


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def journey(f):
    f.go("/voice", "document.querySelector('#app header, #app main')")
    clear_projects(f)
    song = new_song(f)
    f.go(f"/p/{song}/sing", "document.querySelector('#app header')")
    f.wait(f"{SESSION}.session.parts.length > 3", what="the parts")
    f.run(f"""const s = {SESSION}; s.session.practice.sing = {{ ...(s.session.practice.sing ?? {{}}), way: 'whole', fitSaid: true, keyChosen: true }};
        s.practise(s.session.parts.find((p) => p.templatePartKey === 'alto')); s.setMode('own'); s.savePractice();""")
    f.js(f"{AUDIO}.unlock()")
    f.wait("document.querySelector('header [data-settings-open]')", what="Sing's header")

    # The switch: off; on.
    f.run("document.querySelector('[data-settings-open]').click()")
    f.wait("document.querySelector('[data-ring-switch][role=switch]')?.getAttribute('aria-checked') === 'false'", what="Tuning in held chords, off")
    f.run("document.querySelector('[data-ring-switch]').scrollIntoView({ block: 'center' })")
    f.js("new Promise((r) => setTimeout(r, 300))")
    f.shot("switch-off")
    f.run("document.querySelector('[data-ring-switch][role=switch]').click()")
    f.wait("(await import('/js/stores/prefs.js')).prefs.tuningMeter === true", what="the meter on")
    f.shot("switch-on")
    f.press("Escape")
    f.sing_view("notes")

    # The alto, tuned pure in each chord, as your voice over loopback.
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    f.run(f"""const s = {SESSION}; for (const p of s.session.parts) if (p.templatePartKey !== 'alto') s.toggleHearing(p.id);
        await (await import('/js/audio.js')).openMic(); s.seek(0);""")
    f.click_t("studio.play")
    f.wait(f"{AUDIO}.engine.playing", what="playing")
    f.run(f"""const {{ engine }} = {AUDIO}; const s = {SESSION}; const {{ renderPart }} = await import('/js/synth.js');
        const {{ chordAt }} = await import('/js/tuning.js'); const {{ pureOffset }} = await import('/js/ring.js');
        const template = s.session.project.template; const tr = s.session.project.transpose || 0;
        const start = s.session.position / (60 / s.session.project.bpm);
        const alto = s.templatePart(s.session.focusPart).guide.notes.filter((n) => n.beat >= start + 0.5).map((n) => {{
            const chord = chordAt(template, n.beat, {{ focusKey: 'alto' }});
            const pure = chord ? pureOffset(n.pitch + tr, {{ root: (chord.root + tr) % 12, quality: chord.quality }}) : 0;
            return {{ ...n, beat: n.beat - start, pitch: n.pitch + (pure ?? 0) / 100, vibrato: {{ cents: 0 }} }};
        }});
        engine.preview(renderPart(alto, {{ bpm: s.session.project.bpm, sampleRate: engine.sampleRate, transpose: tr }}), engine.sampleRate);""")
    f.wait("document.querySelector('[data-ring-meter]')", timeout=20, what="the meter on a held chord note")
    f.wait("document.querySelector('[data-ring-meter][data-ring-state=ringing]')", timeout=20, what="the pure alto ringing")
    words = f.js("document.querySelector('[data-ring-meter]').innerText")
    assert t(f, "sing.ring.ringing") in words and " of " in words, f"the meter's words: {words}"
    f.shot("ringing")
    f.click_t("studio.pause")
    f.js(f"{AUDIO}.engine.setLoopback(false)")
    clear_projects(f)


if __name__ == "__main__":
    run("tune_ring", journey)
