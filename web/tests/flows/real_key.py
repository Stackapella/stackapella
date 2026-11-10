"""Real voices decide the key and say the speed (Hillel, 2026-11-02): "Prefer the real key" and "Full speed only, said
plainly". The mock gives Amazing Grace's alto (B3 to B4, 59 to 71) a real voice. A singer whose range covers the
alto within two semitones (61 to 73: the old rule moved the key up 2) opens it in the song's own key, hears the real
voice, and is told "Real voices sing this key: Alto fits your range."; a range further off (66 to 78) moves the key
as before and hears the synth. Session settings says "Real voices play at full speed.", and slowing down plays the
synth.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import AUDIO, SESSION, progress, t  # noqa: E402

ALTO = f"{SESSION}.session.parts.find((p) => p.templatePartKey === 'alto')"
VOICE = f"JSON.parse(JSON.stringify({SESSION}.session.guideVoices[{ALTO}?.id] ?? null))"


def alto_song(f, low, high):
    progress(f, {}, 0, extra=f", range: {{ low: {low}, high: {high} }}, rangeSource: 'measured', rangeAt: Date.now()")
    song = f.js("""(async () => {
        const { startFromTemplate } = await import('/js/stores/projects.js');
        const { loadTemplate } = await import('/js/templates.js');
        return (await startFromTemplate(await loadTemplate('amazing-grace'), { singers: 4, focusKey: 'alto' })).id;
    })()""")
    f.go(f"/p/{song}/sing?api=mock&scenario=", "document.querySelector('#app header')")
    f.wait(f"{SESSION}.session.project?.id === {song!r} && {SESSION}.session.parts.length > 3", what="the song's parts")
    f.wait("document.querySelector('[data-way=whole]')", what="the ways")
    f.run("document.querySelector('[data-way=whole]').click()")
    # Game mode's part select comes next (the alto already picked): its Sing starts it.
    f.wait("!document.querySelector('[data-way]') || document.querySelector('[data-select-sing]')", what="past the ways")
    if f.js("!!document.querySelector('[data-select-sing]')"):
        f.run("document.querySelector('[data-select-sing]').click()")
    return song


def journey(f):
    f.go("/?api=mock&scenario=", "document.querySelector('#app header')")
    f.run(f"{AUDIO}.unlock()")

    # Within two semitones: the song's own key, the real voice, and the line saying why.
    alto_song(f, 61, 73)
    line = t(f, "sing.realKey", {"part": "Alto"})
    f.wait(f"document.body.textContent.includes({line!r})", what="the real key's line")
    f.wait(f"{SESSION}.session.guidesVersion > 0 && !{SESSION}.session.preparing && {VOICE}?.kind", timeout=30, what="the alto's guide ready")
    assert f.js(f"{SESSION}.session.project.transpose || 0") == 0, "the song's own key"
    voice = f.js(VOICE)
    assert voice["kind"] == "real", f"the alto's real voice: {voice}"
    f.shot("real-key")

    # The speed control says it plainly; slowing down plays the synth.
    f.run("document.querySelector('[data-settings-open]').click()")
    full = t(f, "sing.settings.realFullSpeed")
    f.wait(f"document.body.textContent.includes({full!r})", what="Real voices play at full speed")
    f.shot("full-speed")
    f.press("Escape")
    f.run(f"await (await import('/js/stores/session.js')).setSpeed(0.85)")
    f.wait(f"{VOICE}?.kind === 'synth' && {VOICE}?.why === 'speed'", timeout=30, what="slowed: the synth")
    f.run(f"await (await import('/js/stores/session.js')).setSpeed(1)")

    # Further off: the key moves as before, the synth sings, and the real line isn't said.
    alto_song(f, 66, 78)
    f.wait(f"({SESSION}.session.project.transpose || 0) !== 0", timeout=20, what="the key moved to fit")
    try:
        f.wait(f"{SESSION}.session.guidesVersion > 0 && !{SESSION}.session.preparing && {VOICE}?.why === 'key'", timeout=60, what="the synth, in the moved key")
    except AssertionError:  # said with what Sing had instead, so a failure says why
        print("     state:", f.js(f"JSON.stringify({{ transpose: {SESSION}.session.project.transpose, voice: {VOICE}, preparing: {SESSION}.session.preparing, version: {SESSION}.session.guidesVersion }})"))
        raise
    assert line not in f.js("document.body.textContent"), "the real key's line, though the key moved"


if __name__ == "__main__":
    run("real_key", journey)
