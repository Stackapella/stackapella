// The chord builder's engine (js/public/chord-builder.js): its tune is the free song's first line, each
// slot offers chords named in the key, the arranger's pick is the app's own, a swap changes only the
// chord tapped, and the parts never cross for 2, 3 or 4 singers.
import { harmonise, numeralOf, play, slotsOf, TUNE } from '/js/public/chord-builder.js';
import { chooseChords } from '/js/arrange.js';
import { assert, test } from './harness.js';

const at = (notes, beat) => notes.find((n) => n.beat <= beat + 1e-9 && n.beat + n.length > beat + 1e-9)?.pitch ?? null;
const sameChord = (a, b) => a.root === b.root && a.quality === b.quality;

test('chord builder: the tune is Amazing Grace\'s first line as the free song has it', async () => {
    const song = await (await fetch('/templates/amazing-grace.json')).json();
    const notes = song.parts.find((p) => p.melody).guide.notes.filter((n) => n.beat < 24);
    assert(notes.length === TUNE.melody.length, `${notes.length} notes, the demo has ${TUNE.melody.length}`);
    notes.forEach((n, i) => {
        const d = TUNE.melody[i];
        assert(n.beat === d.beat && n.length === d.length && n.pitch === d.pitch, `note ${i} differs`);
    });
    assert(song.timeSignature === TUNE.timeSignature && song.pickupBeats === TUNE.pickupBeats && song.key === 'G', 'same metre, pickup and key');
});

test('chord builder: numerals and names in the key', () => {
    const g = { tonic: 7, mode: 'major' };
    assert(numeralOf({ root: 0, quality: 'maj' }, g) === 'IV', 'C in G is IV');
    assert(numeralOf({ root: 4, quality: 'min' }, g) === 'vi', 'Em in G is vi');
    assert(numeralOf({ root: 2, quality: 'dom7' }, g) === 'V7', 'D7 in G is V7');
    assert(numeralOf({ root: 5, quality: 'maj' }, g) === 'bVII', 'F in G is bVII');
    const slots = slotsOf();
    assert(slots.length === 9, `the pickup and 8 bars: ${slots.length}`);
    for (const s of slots) {
        assert(s.options.length >= 2 && s.options.length <= 4, `slot ${s.beat}: ${s.options.length} options`);
        assert(s.pick >= 0 && s.pick < s.options.length, `slot ${s.beat}: pick ${s.pick}`);
        assert(s.options.every((o) => /^[A-G][#b]?(m|7|m7|dim)?$/.test(o.name) && o.numeral), `slot ${s.beat}: ${s.options.map((o) => o.name)}`);
        assert(new Set(s.options.map((o) => o.name)).size === s.options.length, `slot ${s.beat}: no chord twice`);
    }
    assert(slots.at(-1).options[slots.at(-1).pick].name === 'G', 'the line ends at home');
});

test('chord builder: the arranger\'s picks are the app\'s, and a swap changes only the chord tapped', () => {
    const { chords: app } = chooseChords(TUNE.melody, { key: TUNE.key, timeSignature: TUNE.timeSignature, pickupBeats: TUNE.pickupBeats, style: 'warm', phraseEnds: [12, 24] });
    const first = harmonise();
    assert(first.chords.length === app.length && first.chords.every((c, i) => sameChord(c, app[i]) && c.beat === app[i].beat), 'the app\'s chords');
    const slots = slotsOf();
    const picked = slots.map((s) => s.options[s.pick]);
    for (const i of [2, 5]) {
        const other = slots[i].options.find((o) => !sameChord(o, picked[i]));
        const chosen = picked.map((c, k) => (k === i ? other : c));
        const swapped = harmonise(TUNE, chosen);
        swapped.chords.forEach((c, k) => assert(sameChord(c, chosen[k]), `slot ${k} is ${c.name} after swapping slot ${i}`));
        assert(JSON.stringify(swapped.parts) !== JSON.stringify(first.parts), `swapping slot ${i} moves a part`);
    }
});

test('chord builder: 2, 3 and 4 singers, parts never crossing, every chord in every slot', () => {
    const slots = slotsOf();
    for (const singers of [2, 3, 4]) {
        for (let option = 0; option < 4; option++) {
            const chosen = slots.map((s) => s.options[Math.min(option, s.options.length - 1)]);
            const { parts } = harmonise(TUNE, chosen, { singers });
            const keys = parts.map((p) => p.key).join(' ');
            assert(keys === { 2: 'soprano alto', 3: 'soprano alto bass', 4: 'soprano alto tenor bass' }[singers], `${singers} singers: ${keys}`);
            assert(parts[0].notes === TUNE.melody || JSON.stringify(parts[0].notes) === JSON.stringify(TUNE.melody), 'the tune is the soprano');
            for (const n of TUNE.melody) {
                const pitches = parts.map((p) => at(p.notes, n.beat));
                assert(pitches.every((p) => p !== null), `${singers} singers, option ${option}: silent at ${n.beat}`);
                assert(pitches.every((p, i) => !i || p <= pitches[i - 1]), `${singers} singers, option ${option}: crossing at ${n.beat}: ${pitches}`);
            }
        }
    }
});

test('chord builder: play sings from a beat in demo-player\'s shape', () => {
    const run = play(harmonise(), { from: 12, level: 0 });
    assert(run.voices.length === 4 && run.voices.every((v) => v.ended instanceof Promise && typeof v.stop === 'function'), 'a voice a part');
    const end = Math.max(...TUNE.melody.map((n) => n.beat + n.length));
    assert(Math.abs(run.length - ((end - 12) * 60) / TUNE.tempo) < 1e-6, `from beat 12 to the end at 80: ${run.length} s`);
    run.voices.forEach((v) => v.stop());
});
