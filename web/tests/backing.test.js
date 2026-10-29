// The song builder's backing (js/backing.js): a progression voiced for alto, tenor and bass with no
// tune (each chord's third there, no parallels, in range, the last on its root), the keys struck on
// every beat (and the swung off-beat in a shuffle), and a loop exactly its length.
import { backingFor, keysStrikes, renderBackingLoop } from '/js/backing.js';
import { blues, PROGRESSIONS } from '/js/theory.js';
import { assert, test } from './harness.js';

const pc = (n) => ((n % 12) + 12) % 12;
const at = (notes, beat) => notes.find((n) => n.beat <= beat + 1e-6 && n.beat + n.length > beat + 1e-6)?.pitch ?? null;
const THIRDS = { maj: 4, min: 3, dim: 3, dom7: 4, min7: 3, maj7: 4, dim7: 3 };

test('backing: every progression and the blues, voiced: the third there, nothing crossing, no parallels', () => {
    const progressions = [...PROGRESSIONS, blues({ form: 'twelve' }), blues({ form: 'minor' }), blues({ form: 'twelve', feel: 'gospel', turnaround: 'jazz' })];
    for (const p of progressions) {
        for (const tonic of [0, 7, 3]) {
            const key = { tonic, mode: p.mode };
            const { chords, parts } = backingFor(p.bars, { key, timeSignature: '4/4' });
            for (const [i, c] of chords.entries()) {
                const [a, t, b] = ['alto', 'tenor', 'bass'].map((k) => at(parts[k], c.beat));
                assert(a > t && t > b, `${p.id} in ${tonic}: crossing at ${c.beat}: ${a} ${t} ${b}`);
                assert([a, t, b].map(pc).includes(pc(c.root + THIRDS[c.quality])), `${p.id} in ${tonic}: no third at ${c.beat}`);
                assert(b >= 40 && b <= 60 && a <= 74 && t >= 48, `${p.id}: out of range at ${c.beat}: ${a} ${t} ${b}`);
                if (i) {
                    const prev = chords[i - 1];
                    const before = ['alto', 'tenor', 'bass'].map((k) => at(parts[k], prev.beat));
                    const after = [a, t, b];
                    for (let x = 0; x < 3; x++) for (let y = x + 1; y < 3; y++) {
                        if (before[x] === after[x] || before[y] === after[y]) continue;
                        const was = pc(before[x] - before[y]);
                        assert(!((was === 7 || was === 0) && pc(after[x] - after[y]) === was), `${p.id} in ${tonic}: parallels at ${c.beat}`);
                    }
                }
            }
            assert(pc(parts.bass.at(-1).pitch) === chords.at(-1).root, `${p.id}: the last chord on its root`);
            assert(parts.alto.every((n) => n.lyric === 'oo'), 'sung on oo');
        }
    }
});

test('backing: the keys strike every beat, with a lighter swung off-beat in a shuffle', () => {
    const { chords, endBeat } = backingFor(PROGRESSIONS.find((p) => p.id === 'four-chords').bars, { key: { tonic: 0, mode: 'major' } });
    assert(keysStrikes(chords, { endBeat }).length === 16, 'four bars of four');
    const shuffle = keysStrikes(chords, { endBeat, swing: 2 / 3 });
    assert(shuffle.length === 32 && Math.abs(shuffle[1].beat - 2 / 3) < 1e-9, 'and the swung off-beats');
    assert(shuffle[8].root === 7, 'beat 4 strikes the G (V in C)');
});

test('backing: the loop is exactly its bars long, and sounds', () => {
    const backing = backingFor(PROGRESSIONS.find((p) => p.id === 'doo-wop').bars, { key: { tonic: 0, mode: 'major' } });
    const samples = renderBackingLoop(backing, { bpm: 96, sampleRate: 8000 });
    assert(samples.length === Math.round(((16 * 60) / 96) * 8000), `${samples.length} frames`);
    let peak = 0;
    for (const v of samples) peak = Math.max(peak, Math.abs(v));
    assert(peak > 0.01, 'not silent');
    const keysOnly = renderBackingLoop(backing, { bpm: 96, sampleRate: 8000, voices: false });
    assert(keysOnly.some((v) => Math.abs(v) > 0.01), 'the keys alone');
});
