"""Make it sound good and practice tracks (features 1 and 12): a project with two recorded parts at
very different levels. Export with Make it sound good on (in a Hall) gives a WAV at -14 LUFS with
nothing above -1 dBFS; a practice track of the quiet part, "Everything but this part" with a click,
is longer by a bar of count-in; "Split ears" puts the chosen part in the left channel. Hear it polished puts the room under the parts while playing.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

I18N = "(await import('/js/i18n.js'))"
AUDIO = "(await import('/js/audio.js'))"
SESSION = "(await import('/js/stores/session.js'))"

# Every download is caught: its WAV is read back (16-bit, stereo) for its loudness and peak.
CATCH = """
window.downloads = [];
HTMLAnchorElement.prototype.click = function () { if (this.download) window.downloads.push(this.href); };
"""
READ = """(async () => {
    const { loudness } = await import('/js/polish.js');
    const buffer = await (await fetch(window.downloads.at(-1))).arrayBuffer();
    const view = new DataView(buffer);
    const rate = view.getUint32(24, true);
    let at = 12;
    while (String.fromCharCode(...new Uint8Array(buffer, at, 4)) !== 'data') at += 8 + view.getUint32(at + 4, true);
    const frames = view.getUint32(at + 4, true) / 4;
    const channels = [new Float32Array(frames), new Float32Array(frames)];
    for (let i = 0; i < frames; i++) for (let c = 0; c < 2; c++) channels[c][i] = view.getInt16(at + 8 + (i * 2 + c) * 2, true) / 32768;
    let peak = 0;
    for (const c of channels) for (const v of c) peak = Math.max(peak, Math.abs(v));
    return { lufs: loudness(channels, rate), peakDb: 20 * Math.log10(peak), seconds: frames / rate };
})()"""


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def menu(f, key):
    """A header button on a wide screen; on a phone, the same in the More menu."""
    if f.size == "phone":
        f.run("document.querySelector('header details').open = true")
    f.click_t(key)


def polish(f):
    """Hear it polished: the transport's Polished chip on a wide screen, the More menu on a phone."""
    if f.size == "phone":
        menu(f, "polish.hear")
    else:
        f.click_t("polish.name")


def journey(f):
    f.go("/", "document.querySelector('#app header')")
    project_id = f.js("""(async () => {
        const store = await import('/js/stores/projects.js');
        const { peaks } = await import('/js/dsp.js');
        const project = await store.createProject({ title: 'Polish test', bpm: 120 });
        const sr = 48000;
        const take = (hz, gain) => new Float32Array(sr * 4).map((_, i) => (i > sr / 2 ? gain * Math.sin((2 * Math.PI * hz * i) / sr) * (1 + 0.3 * Math.sin(i / 3000)) : 0));
        for (const [n, hz, gain] of [[0, 392, 0.6], [1, 262, 0.04]]) {
            const samples = take(hz, gain);
            await store.savePart({ id: crypto.randomUUID(), projectId: project.id, name: n ? 'Quiet' : 'Loud', color: n ? '#93c26e' : '#f2a06b', order: n, volume: 1, pan: 0, muted: false, solo: false, createdAt: Date.now(),
                offsetSeconds: 0, nudgeSeconds: 0, sampleRate: sr, frames: samples.length, framesPerPeak: 512, peaks: peaks(samples, 512) }, samples);
        }
        return project.id;
    })()""")
    f.go(f"/p/{project_id}", "document.querySelector('#app')")
    f.wait(f"{SESSION}.session.parts.length === 2", what="the two parts")
    f.run(CATCH)
    f.js(f"{AUDIO}.unlock()")

    # The whole song, polished, in a Hall.
    menu(f, "studio.export")
    f.wait("document.querySelector('[data-export]')", what="the export sheet")
    assert f.js("document.querySelector('[data-export] [role=switch]').getAttribute('aria-checked') === 'true'"), "Polished starts on"
    f.click_t("polish.room.hall")
    f.shot("export")
    f.run("document.querySelector('[role=dialog] form').requestSubmit()")
    f.wait("window.downloads.length === 1", timeout=60, what="the polished WAV")
    song = f.js(READ)
    assert abs(song["lufs"] + 14) <= 0.5 and song["peakDb"] <= -0.99, f"polished: {song}"

    # A practice track of the quiet part: everything but it, with a click and a bar of count-in.
    menu(f, "studio.export")
    f.wait_text(t(f, "export.track"))
    f.click_t("export.track")
    f.run("const s = document.querySelector('[role=dialog] select'); s.value = s.options[1].value; s.dispatchEvent(new Event('change'))")
    f.shot("track")
    f.run("document.querySelector('[role=dialog] form').requestSubmit()")
    f.wait("window.downloads.length === 2", timeout=60, what="the practice track")
    track = f.js(READ)
    assert abs(track["seconds"] - song["seconds"] - 2) < 0.01, f"a bar (2 s at 120 bpm in 4/4) longer: {track} vs {song}"

    # Split ears (practice tracks, slice 1): the loud part as "my part", left; the quiet one right; no click.
    SIDES = READ.replace("return { lufs", "const rms = (c) => Math.sqrt(c.reduce((s, v) => s + v * v, 0) / c.length); return { left: rms(channels[0]), right: rms(channels[1]), lufs")
    menu(f, "studio.export")
    f.wait_text(t(f, "export.track"))
    f.click_t("export.track")
    f.run("const s = document.querySelector('[role=dialog] select'); s.value = s.options[0].value; s.dispatchEvent(new Event('change'))")
    f.run("document.querySelector('[role=dialog] input[value=split]').click()")
    f.run("const c = document.querySelector('[role=dialog] input[type=checkbox]'); if (c.checked) c.click();")
    # Polished levels every part to the same loudness first; dry, the loud part stays loud, so its side shows.
    f.run("const p = document.querySelector('[data-export] [role=switch]'); if (p.getAttribute('aria-checked') === 'true') p.click();")
    f.shot("track-split")
    f.run("document.querySelector('[role=dialog] form').requestSubmit()")
    f.wait("window.downloads.length === 3", timeout=60, what="the split-ears track")
    sides = f.js(SIDES)
    assert sides["left"] > 5 * sides["right"], f"the loud part should be in the left ear: {sides}"

    # Hear it polished (the transport's Polished on a wide screen): the room goes under the parts.
    polish(f)
    f.wait(f"{AUDIO}.engine.room !== null", what="the room under the parts")
    f.wait_text(t(f, "polish.hearOn"))
    f.shot("polished")
    polish(f)
    f.wait(f"{AUDIO}.engine.room === null || {AUDIO}.engine.room.wet === 0", what="the room taken away")


if __name__ == "__main__":
    run("export_polish", journey)
