"""Soften breaths, a take's own switch (Hillel, 2026-11-16: "Library voices on, own takes optional"; js/soften-breaths.js,
stores/session.js heardTake, the part's panel in components/dialogs.js). A library song with a take on the soprano whose
first gap has a breath in it (noise where the part rests):

- the part's panel has the switch in its Mix block, off; a part with no take has none;
- off, everything that makes sound from the take is the take as recorded: the mix, the polished mix and the stem;
- on (by the switch), the breath is ducked in all three, the sung notes as they were, and the take itself is unchanged;
- the choice is saved with the part and is still there after the project is opened again;
- off again, all three are byte for byte what they were before the switch was ever touched.

On the mock API (tool/flows.sh soften_breaths).
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

SESSION = "(await import('/js/stores/session.js'))"

# A project from the library song, with a take on the soprano: a held tone, and a breath (noise) in the middle of the
# part's first gap. Returns the project, the part, and where the breath and a sung stretch are (seconds).
MAKE = """(async () => {
    const store = await import('/js/stores/projects.js');
    const { loadTemplate } = await import('/js/templates.js');
    const { peaks } = await import('/js/dsp.js');
    const { breathWindows } = await import('/js/soften-breaths.js');
    const whole = await loadTemplate('amazing-grace');
    const project = await store.startFromTemplate(whole, { singers: 4 });
    const db = await import('/js/db.js');
    const part = (await db.allBy('parts', 'projectId', project.id)).find((p) => p.templatePartKey === 'soprano');
    const notes = whole.parts.find((p) => p.key === 'soprano').guide.notes;
    const spb = 60 / whole.tempo, sr = 48000;
    const [from, to] = breathWindows(notes, spb)[0];
    const breath = [from + 0.12, to - 0.12];
    const end = Math.max(...notes.map((n) => n.beat + n.length)) * spb;
    let seed = 7;
    const noise = () => ((seed = (seed * 1103515245 + 12345) % 2147483648) / 1073741824) - 1;
    const samples = new Float32Array(Math.round(end * sr)).map((_, i) => { const t = i / sr; return t >= breath[0] && t < breath[1] ? 0.12 * noise() : 0.3 * Math.sin(2 * Math.PI * 330 * t); });
    await store.savePart({ ...part, offsetSeconds: 0, nudgeSeconds: 0, sampleRate: sr, frames: samples.length, framesPerPeak: 512, peaks: peaks(samples, 512) }, samples);
    return { id: project.id, part: part.id, breath: [breath[0] + 0.04, breath[1] - 0.04], sung: [to + 0.3, to + 0.8] };
})()"""

# What the take sounds like everywhere it becomes sound: the mix of just this part, the same polished in a room, and
# its stem. Each as a hash of its samples, with how loud the breath and a sung stretch are.
HEARD = """(async (part, breath, sung) => {
    const s = await import('/js/stores/session.js');
    const hash = async (arrays) => { const all = new Uint8Array(arrays.reduce((n, a) => n + a.byteLength, 0)); let at = 0; for (const a of arrays) { all.set(new Uint8Array(a.buffer, a.byteOffset, a.byteLength), at); at += a.byteLength; }
        return [...new Uint8Array(await crypto.subtle.digest('SHA-256', all))].map((b) => b.toString(16).padStart(2, '0')).join(''); };
    const rms = (x, rate, [a, b]) => { let e = 0; const i0 = Math.round(a * rate), i1 = Math.round(b * rate); for (let i = i0; i < i1; i++) e += x[i] * x[i]; return Math.sqrt(e / (i1 - i0)); };
    const one = async (channels, rate) => ({ hash: await hash(channels), breath: rms(channels[0], rate, breath), sung: rms(channels[0], rate, sung) });
    const plain = await s.renderMix({ mix: true, only: part });
    const polished = await s.renderMix({ mix: true, only: part, room: 'hall' });
    const stem = s.renderStems().find((x) => x.part.id === part);
    return { mix: await one(plain.channels, plain.sampleRate), polished: await one(polished.channels, polished.sampleRate), stem: await one([stem.samples], stem.sampleRate) };
})"""
SWITCH = "document.querySelector('[data-part-panel] [data-ui=soften-breaths]')"


def panel(f, name):
    f.run(f"[...document.querySelectorAll('button[aria-expanded]')].find((b) => b.textContent.includes({name!r})).click()")
    f.click_t("studio.row.voice")
    f.wait("document.querySelector('[data-part-panel]')", what=f"{name}'s panel")


def journey(f):
    f.go("/", "document.querySelector('h1')")
    f.mic_checked()
    made = f.js(MAKE)
    heard = f"({HEARD})({made['part']!r}, {made['breath']!r}, {made['sung']!r})"
    open_it = lambda: (f.go(f"/p/{made['id']}", "document.querySelectorAll('canvas').length > 3"), f.js("(await import('/js/audio.js')).unlock()"), f.wait(f"await {SESSION}.ready()", timeout=60, what="the project ready"))  # noqa: E731
    open_it()
    raw = f.js(f"(async () => {{ const s = (await (await import('/js/db.js')).getAudio({made['part']!r})); let h = 0; for (let i = 0; i < s.length; i += 97) h = (h * 31 + Math.round(s[i] * 32767)) | 0; return h; }})()")

    # Off: the take as recorded, everywhere.
    off = f.js(heard)
    assert off["mix"]["breath"] > 0.03 and off["stem"]["breath"] > 0.03, f"off, the breath is there as recorded: {off}"

    # The switch: in the part's panel, in its Mix block, off; a part with no take has none.
    soprano, alto = f.js("(await import('/js/i18n.js')).t('parts.soprano')"), f.js("(await import('/js/i18n.js')).t('parts.alto')")
    panel(f, alto)
    assert f.js(f"{SWITCH} === null"), "a part with no take has no switch"
    f.press("Escape")
    f.wait("!document.querySelector('[data-part-panel]')", what="the panel closed")
    panel(f, soprano)
    f.wait(f"{SWITCH}", what="Soften breaths in the soprano's panel")
    label = f.js("(await import('/js/i18n.js')).t('part.softBreaths')")
    assert f.js(f"{SWITCH}.getAttribute('role') === 'switch' && {SWITCH}.getAttribute('aria-checked') === 'false' && {SWITCH}.textContent.includes({label!r})"), "the switch, named, off by default"
    assert f.js(f"{SWITCH}.getBoundingClientRect().height >= 44"), "big enough to press"
    f.no_sideways("the part's panel")
    f.shot("panel-off")
    f.run(f"{SWITCH}.click()")
    f.wait(f"{SWITCH}.getAttribute('aria-checked') === 'true'", what="the switch on")
    f.shot("panel-on")
    f.press("Escape")

    # On: the breath is ducked in the mix, the polished mix and the stem; the sung notes are as they were.
    on = f.js(heard)
    for what in ("mix", "polished", "stem"):
        # (In a room the notes' own reverb rings on through the gap, so the polished mix's gap is never as empty.)
        assert on[what]["breath"] < off[what]["breath"] * (0.5 if what == "polished" else 0.2), f"on, the {what}'s breath is ducked: {off[what]['breath']} then {on[what]['breath']}"
        assert abs(on[what]["sung"] - off[what]["sung"]) < off[what]["sung"] * 0.02, f"on, the {what}'s sung notes are as they were: {off[what]['sung']} then {on[what]['sung']}"
        assert on[what]["hash"] != off[what]["hash"], f"on, the {what} is a different file"

    # Saved with the part: opened again, it's still on, and the take itself is what was recorded.
    f.wait(f"(await (await import('/js/db.js')).get('parts', {made['part']!r})).softBreaths === true", timeout=10, what="the choice saved with the part")
    open_it()
    again = f.js(heard)
    assert again["mix"]["hash"] == on["mix"]["hash"] and again["stem"]["hash"] == on["stem"]["hash"], "opened again, it's still softened, the same"
    assert f.js(f"(async () => {{ const s = (await (await import('/js/db.js')).getAudio({made['part']!r})); let h = 0; for (let i = 0; i < s.length; i += 97) h = (h * 31 + Math.round(s[i] * 32767)) | 0; return h; }})()") == raw, "the take itself is never changed"

    # Off again: byte for byte what it was before the switch was ever touched.
    panel(f, soprano)
    f.wait(f"{SWITCH}?.getAttribute('aria-checked') === 'true'", what="the switch still on")
    f.run(f"{SWITCH}.click()")
    f.wait(f"{SWITCH}.getAttribute('aria-checked') === 'false'", what="the switch off")
    f.press("Escape")
    back = f.js(heard)
    for what in ("mix", "polished", "stem"):
        assert back[what]["hash"] == off[what]["hash"], f"off again, the {what} is byte for byte what it was"


if __name__ == "__main__":
    run("soften_breaths", journey)
