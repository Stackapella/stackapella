"""Writes the music theory's and the arranger's test vectors (web/tests/vectors/theory.json and
arrange.json) for the mobile app, which ports js/theory.js and js/arrange.js and checks itself
against the same answers (docs/song-builder-plan.md). Runs the modules in headless Chrome against a
local server (no npm):

    PORT=8297 python3 -I web/tool/vectors.py

Run it after theory.js or arrange.js changes, and commit the JSON with it. Standard library only.
"""
import json
import os
import subprocess
import sys
import time
from pathlib import Path

WEB = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(WEB / "tool"))
from cdp import Chrome  # noqa: E402

SCRIPT = r"""(async () => {
    const T = await import('/js/theory.js');
    const numerals = [...new Set([...T.PROGRESSIONS.flatMap((p) => p.bars.flat()), ...Object.keys(T.BLUES_FORMS).flatMap((form) => Object.keys(T.TURNAROUNDS).flatMap((turnaround) => Object.keys(T.FEELS).flatMap((feel) => T.blues({ form, turnaround, feel }).bars.flat()))), 'Isus2', 'Vsus4', 'IVsus2', 'I6', 'IV6', 'ii6'])].sort();
    const keys = [{ tonic: 0, mode: 'major' }, { tonic: 3, mode: 'major' }, { tonic: 6, mode: 'major' }, { tonic: 9, mode: 'minor' }, { tonic: 2, mode: 'minor' }];
    const out = {
        chordRole: ['major', 'minor'].flatMap((mode) => numerals.concat(['x']).map((n) => ({ input: { numeral: n, mode }, output: T.chordRole(n, mode) }))),
        parseNumeral: numerals.concat(['VIII', 'x', '']).map((n) => ({ input: n, output: T.parseNumeral(n) })),
        chordName: keys.flatMap((key) => numerals.map((n) => ({ input: { numeral: n, key }, output: T.chordName(T.chordOf(n, key.tonic), key) }))),
        keyName: Array.from({ length: 12 }, (_, tonic) => ['major', 'minor'].map((mode) => ({ input: { tonic, mode }, output: T.keyName({ tonic, mode }) }))).flat(),
        chordsFor: T.PROGRESSIONS.flatMap((p) => [[0, 4], [7, 3]].map(([tonic, perBar]) => ({ input: { progression: p.id, tonic, perBar }, output: T.chordsFor(p.bars, { tonic, perBar }) }))),
        blues: Object.keys(T.BLUES_FORMS).flatMap((form) => Object.keys(T.TURNAROUNDS).flatMap((turnaround) => Object.keys(T.FEELS).map((feel) => ({ input: { form, turnaround, feel }, output: T.blues({ form, turnaround, feel }) })))),
        landHome: T.PROGRESSIONS.map((p) => ({ input: { bars: p.bars, mode: p.mode }, output: T.landHome(p.bars, p.mode) })),
        suggestChorus: T.PROGRESSIONS.map((p) => ({ input: p.id, output: T.suggestChorus(p) })),
        suggestBridge: T.PROGRESSIONS.map((p) => ({ input: p.id, output: T.suggestBridge(p, T.suggestChorus(p)[0]) })),
        lastChorus: T.PROGRESSIONS.flatMap((p) => [false, true].map((triumphant) => ({ input: { bars: p.bars, mode: p.mode, triumphant }, output: T.lastChorus(p.bars, { mode: p.mode, triumphant }) }))),
        suggestPreChorus: T.PROGRESSIONS.map((p) => ({ input: p.id, output: T.suggestPreChorus(p) })),
        suggestKeyOptions: Object.entries(T.VOICE_RANGES).flatMap(([voice, range]) => ['major', 'minor'].flatMap((mode) => [1, 2].map((headroom) => ({ input: { range, mode, sections: ['verse', 'chorus', 'bridge'], headroom, voice }, output: T.suggestKey(range, { mode, sections: ['verse', 'chorus', 'bridge'], headroom }) })))),
        nextKey: Object.entries(T.VOICE_RANGES).flatMap(([voice, range]) => ['major', 'minor'].flatMap((mode) => [-1, 1].flatMap((direction) => { const best = T.suggestKey(range, { mode, sections: ['verse', 'chorus'] }); const once = T.nextKey(range, best.tonicMidi, direction, { mode, sections: ['verse', 'chorus'] }); return [{ input: { range, from: best.tonicMidi, direction, mode, sections: ['verse', 'chorus'], voice }, output: once }, ...(once ? [{ input: { range, from: once.tonicMidi, direction, mode, sections: ['verse', 'chorus'], voice }, output: T.nextKey(range, once.tonicMidi, direction, { mode, sections: ['verse', 'chorus'] }) }] : [])]; }))),
        suggestKey: Object.entries(T.VOICE_RANGES).flatMap(([voice, range]) => ['major', 'minor'].flatMap((mode) => ['verse', 'chorus', 'bridge'].map((section) => ({ input: { range, mode, section, voice }, output: T.suggestKey(range, { mode, section }) })))).concat([{ input: { range: { low: 60, high: 66 }, mode: 'major', section: 'verse' }, output: T.suggestKey({ low: 60, high: 66 }) }]),
    };
    return JSON.stringify(out);
})()"""

# The arranger: harmonize on the bundled songs in every style, and the cases 31's port found (a tune
# sitting low over a chord on C to Eb; a song's own sus2 and 6 chords).
ARRANGE = r"""(async () => {
    const A = await import('/js/arrange.js');
    const { parseKey } = await import('/js/notes.js');
    const songs = ['amazing-grace', 'happy-birthday', 'michael-row', 'are-you-sleeping'];
    const keyOf = (t) => { const { tonic, minor } = parseKey(t.key); return { tonic, mode: minor ? 'minor' : 'major' }; };
    const bare = (notes) => ({ key: 'C', timeSignature: '4/4', pickupBeats: 0, tempo: 90, sections: [{ name: 'verse', phrases: [[0, notes.at(-1).beat + notes.at(-1).length]] }], parts: [{ key: 'melody', name: 'Melody', melody: true, guide: { type: 'notes', notes } }] });
    const harmonize = [];
    for (const id of songs) {
        const t = await (await fetch(`/templates/${id}.json`)).json();
        const melody = t.parts.find((p) => p.melody) ?? t.parts[0];
        const template = { ...t, parts: [melody] };
        for (const style of A.STYLES) harmonize.push({ input: { song: id, template, options: { style, key: keyOf(t) } }, output: A.harmonize(template, { style, key: keyOf(t) }).template });
        if (t.chords?.length) harmonize.push({ input: { song: id, template, options: { style: 'simple', key: keyOf(t), chords: t.chords } }, output: A.harmonize(template, { style: 'simple', key: keyOf(t), chords: t.chords }).template });
    }
    const low = [57, 55, 57, 59, 57, 55, 57, 57].map((pitch, i) => ({ beat: i, length: 1, pitch }));
    for (const root of [0, 1, 2, 3]) {
        const options = { style: 'simple', key: { tonic: 0, mode: 'major' }, chords: [{ beat: 0, root, quality: 'maj' }, { beat: 4, root, quality: 'maj' }] };
        harmonize.push({ input: { song: `low-tune-root-${root}`, template: bare(low), options }, output: A.harmonize(bare(low), options).template });
    }
    const tune = [64, 62, 64, 67, 69, 67, 64, 62].map((pitch, i) => ({ beat: i, length: 1, pitch }));
    const options = { style: 'simple', key: { tonic: 0, mode: 'major' }, chords: [{ beat: 0, root: 0, quality: 'sus2' }, { beat: 4, root: 5, quality: 'maj6' }] };
    harmonize.push({ input: { song: 'sus2-maj6', template: bare(tune), options }, output: A.harmonize(bare(tune), options).template });
    return JSON.stringify({ harmonize });
})()"""


def main():
    port = os.environ.get("PORT", "8297")
    server = subprocess.Popen(["php", "-S", f"127.0.0.1:{port}", "tool/serve.php"], cwd=WEB, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    try:
        time.sleep(0.5)
        # php -S exits at once when the port is taken: without this the browser would read whatever
        # else is serving there (another worktree's files) and write its answers as ours.
        if server.poll() is not None:
            sys.exit(f"vectors.py: php couldn't serve on port {port} (another server has it?); run with PORT=<a free port>")
        chrome = Chrome(None, 800, 600, False)
        try:
            chrome.goto(f"http://127.0.0.1:{port}/tests/fixtures/", None)
            vectors = json.loads(chrome.evaluate(SCRIPT))
            arranged = json.loads(chrome.evaluate(ARRANGE))
        finally:
            chrome.close()
    finally:
        server.terminate()
    out = WEB / "tests/vectors/theory.json"
    out.parent.mkdir(exist_ok=True)
    out.write_text(json.dumps({"module": "js/theory.js", "vectors": vectors}, indent=1, sort_keys=True) + "\n")
    print(f"{sum(len(v) for v in vectors.values())} vectors in {out.relative_to(WEB.parent)}")
    out = WEB / "tests/vectors/arrange.json"
    out.write_text(json.dumps({"module": "js/arrange.js", "vectors": arranged}, indent=1, sort_keys=True) + "\n")
    print(f"{sum(len(v) for v in arranged.values())} vectors in {out.relative_to(WEB.parent)}")


if __name__ == "__main__":
    main()
