// Harmony (js/arrange.js): chords for the free songs' melodies in each style, cadences home, no
// crossings, every part in its range, versions for 2, 3 and 4, and "Try another chord" giving a
// different chord that still holds the tune.
import { arrange, chooseChords, harmonize, modeOf, STYLES } from '/js/arrange.js';
import { beatsPerBar } from '/js/theory.js';
import { findKey } from '/js/transcribe.js';
import { validateTemplate } from '/js/templates.js';
import { parseKey } from '/js/notes.js';
import { assert, test } from './harness.js';

const SONGS = ['amazing-grace', 'happy-birthday', 'michael-row', 'are-you-sleeping'];
const load = async (id) => (await fetch(`/templates/${id}.json`)).json();
const melodyOf = (t) => (t.parts.find((p) => p.melody) ?? t.parts[0]).guide.notes;
const keyOf = (t) => {
    const { tonic, minor } = parseKey(t.key);
    return { tonic, mode: minor ? 'minor' : 'major' };
};
const at = (notes, beat) => notes.find((n) => n.beat <= beat + 1e-9 && n.beat + n.length > beat + 1e-9)?.pitch ?? null;

test('arrange: each style harmonizes the free songs: no crossings, parts in range, ending at home', async () => {
    for (const id of SONGS) {
        const song = await load(id);
        const key = keyOf(song);
        for (const style of STYLES) {
            const { template, result } = harmonize(song, { style, key });
            if (id !== 'are-you-sleeping') validateTemplate(template); // a round: its sections run past the tune's one entry
            const [soprano, alto, tenor, bass] = ['soprano', 'alto', 'tenor', 'bass'].map((k) => template.parts.find((p) => p.key === k).guide.notes);
            for (const n of soprano) {
                const [s, a, tn, b] = [n.pitch, at(alto, n.beat), at(tenor, n.beat), at(bass, n.beat)];
                assert(a !== null && tn !== null && b !== null, `${id} ${style}: a part is silent at beat ${n.beat}`);
                assert(s >= a && a >= tn && tn >= b, `${id} ${style}: parts cross at beat ${n.beat}: ${s} ${a} ${tn} ${b}`);
            }
            // The bass may go below E2, to C2, when a low tune leaves E2 up no root (arrange.js, BASS_FLOOR).
            assert(Math.min(...bass.map((n) => n.pitch)) >= 36 && Math.max(...alto.map((n) => n.pitch)) <= 76, `${id} ${style}: a part left its range`);
            const last = result.chords.at(-1);
            assert(last.root === key.tonic, `${id} ${style}: ends on ${last.root}, not the tonic ${key.tonic}`);
            assert(result.chords[0].root === key.tonic || result.chords[0].root === (key.tonic + 7) % 12, `${id} ${style}: starts on ${result.chords[0].root}`);
            assert(JSON.stringify(template.voicings) === JSON.stringify({ 2: ['soprano', 'alto'], 3: ['soprano', 'alto', 'bass'], 4: ['soprano', 'alto', 'tenor', 'bass'] }), 'versions');
            // The alto and tenor sing the words with the tune.
            assert(alto.filter((n) => n.lyric).length === soprano.filter((n) => n.lyric).length, `${id} ${style}: the alto's words`);
        }
    }
});

test('arrange: Simple\'s chords mostly agree with the arranger who wrote the songs', async () => {
    let agree = 0;
    let total = 0;
    for (const id of SONGS) {
        const song = await load(id);
        if (!song.chords) continue;
        const { chords } = chooseChords(melodyOf(song), { key: keyOf(song), timeSignature: song.timeSignature, pickupBeats: song.pickupBeats, phraseEnds: song.sections.flatMap((s) => s.phrases.map(([, e]) => e)) });
        for (const c of chords) {
            const theirs = song.chords.findLast((x) => x.beat <= c.beat + 1e-9);
            if (!theirs) continue;
            total++;
            if (theirs.root === c.root) agree++;
        }
    }
    assert(total > 10 && agree / total >= 0.6, `agree on ${agree} of ${total}`);
});

test('arrange: Try another chord gives a different chord that still holds the tune\'s note', async () => {
    const song = await load('amazing-grace');
    const options = { key: keyOf(song), timeSignature: song.timeSignature, pickupBeats: song.pickupBeats, phraseEnds: [12, 24, 36, 48] };
    const first = chooseChords(melodyOf(song), options);
    const slot = 3;
    for (let choice = 1; choice < first.slots[slot].options.length; choice++) {
        const other = arrange(melodyOf(song), { ...options, choices: { [slot]: choice } });
        const a = first.chords[slot];
        const b = other.chords[slot];
        assert(a.root !== b.root || a.quality !== b.quality, `choice ${choice} is the same chord`);
        assert(other.slots[slot].choice === choice, 'the choice is kept');
    }
    assert(first.slots[slot].options.length >= 2, 'at least two chords to try');
});

test('arrange: a minor tune gets a minor home chord and a major V', () => {
    // A little A minor line: A C B A, E D C B, A.
    const pitches = [69, 72, 71, 69, 76, 74, 72, 71, 69];
    const melody = pitches.map((pitch, i) => ({ beat: i, length: i === 8 ? 3 : 1, pitch, ...(i === 0 ? { lyric: 'la', br: true } : { lyric: 'la' }) }));
    const key = findKey(melody);
    assert(key.name === 'Am', `key ${key.name}`);
    const { chords } = arrange(melody, { key, timeSignature: '4/4', phraseEnds: [11] });
    assert(chords[0].root === 9 && chords[0].quality === 'min', JSON.stringify(chords[0]));
    assert(chords.at(-1).root === 9, 'ends on A');
    assert(chords.some((c) => c.root === 4 && c.quality !== 'min'), `E major somewhere: ${JSON.stringify(chords)}`);
});

// ---- A choral arranger's rules (the review that reworked the voicing) ----------------------------

/** Laid end to end: [length, pitch] (null for a rest), every note a syllable. */
const tune = (items) => {
    const notes = [];
    let beat = 0;
    for (const [length, pitch] of items) {
        if (pitch !== null) notes.push({ beat: Math.round(beat * 1e6) / 1e6, length, pitch, lyric: 'la', ...(notes.length ? {} : { br: true }) });
        beat += length;
    }
    return notes;
};
const song = (notes, timeSignature, pickupBeats, phrases) => ({ id: 'tune', title: 'Tune', key: findKey(notes).name, timeSignature, pickupBeats, tempo: 90, sections: [{ name: 'Song', startBeat: 0, endBeat: notes.at(-1).beat + notes.at(-1).length, phrases }], parts: [{ key: 'melody', name: 'Melody', melody: true, range: { low: 0, high: 0 }, guide: { type: 'notes', notes } }] });
const E = 1 / 3;
const Q = 2 / 3;
// A 6/8 folk tune in G with an eighth's pickup.
const FOLK = song(tune([[E, 62], [Q, 67], [E, 67], [Q, 71], [E, 69], [Q, 67], [E, 71], [1, 74], [Q, 76], [E, 74], [Q, 71], [E, 67], [1 + Q, 69], [E, 62], [Q, 67], [E, 67], [Q, 71], [E, 69], [Q, 67], [E, 71], [1, 74], [Q, 76], [E, 72], [Q, 69], [E, 66], [2, 67]]), '6/8', E, [[0, 8], [8, 16 + E]]);
// Where a review found faults (both trad.): Loch Lomond's first lines in G (a pickup, the tune on the
// leading tone into home), and All Through the Night's first line (IV to V under the tune's E to F#).
const LOCH = song(tune([[0.5, 62], [0.5, 66], [1, 67], [0.5, 67], [0.5, 69], [1, 71], [0.75, 69], [0.25, 67], [1, 69], [0.5, 69], [0.5, 67], [1, 64], [0.5, 62], [0.5, 66], [1, 67], [1, 67], [1, 67], [0.5, 71], [0.5, 74], [2, 76], [1, 74], [1, 74], [1, 76], [0.5, 76], [0.5, 74], [1, 71], [0.5, 71], [0.5, 74], [0.5, 72], [0.5, 71], [0.5, 69], [0.5, 67], [1, 64], [0.5, 62], [0.5, 66], [2, 67]]), '4/4', 1, [[0, 13], [13, 27]]);
const NIGHT = song(tune([[1.5, 67], [0.5, 66], [1, 64], [1, 67], [1.5, 69], [0.5, 67], [1, 66], [1, 62], [2, 64], [1.5, 66], [0.5, 66], [4, 67]]), '4/4', 0, [[0, 16]]);
const keyFrom = (t) => {
    const k = findKey(melodyOf(t));
    return { tonic: k.tonic, mode: k.mode };
};

/** Every song (the library's and the two above) in every style: `{ id, style, key, parts, chords }`. */
async function everyArrangement() {
    const out = [];
    for (const [id, t] of [...(await Promise.all(SONGS.map(async (id) => [id, await load(id)]))), ['folk', FOLK], ['loch', LOCH], ['night', NIGHT]]) {
        const key = t.key && parseKey(t.key) ? keyOf(t) : keyFrom(t);
        for (const style of STYLES) {
            const { template, result } = harmonize(t, { style, key });
            const parts = Object.fromEntries(template.parts.map((p) => [p.key, p.guide.notes]));
            out.push({ id: `${id} ${style}`, key, parts, chords: result.chords, timeSignature: t.timeSignature, pickupBeats: t.pickupBeats ?? 0 });
        }
    }
    return out;
}

const VOICES = ['soprano', 'alto', 'tenor', 'bass'];
const just = (notes, beat) => notes.findLast((n) => n.beat < beat - 1e-9)?.pitch ?? null; // sounding just before

test('arranger: no parallel fifths or octaves at a chord change, and none at all among the lower voices', async () => {
    for (const a of await everyArrangement()) {
        let outer = 0;
        const changes = a.parts.bass.map((n) => n.beat).slice(1);
        for (const beat of changes) {
            const before = VOICES.map((v) => just(a.parts[v], beat));
            const after = VOICES.map((v) => at(a.parts[v], beat));
            for (let i = 0; i < 4; i++) {
                for (let j = i + 1; j < 4; j++) {
                    if ([before[i], before[j], after[i], after[j]].includes(null) || before[i] === after[i] || before[j] === after[j]) continue;
                    const was = ((before[i] - before[j]) % 12 + 12) % 12;
                    const now = ((after[i] - after[j]) % 12 + 12) % 12;
                    if ((now === 7 || now === 0) && was === now) {
                        assert(i === 0, `${a.id}: parallel ${now ? 'fifths' : 'octaves'} between ${VOICES[i]} and ${VOICES[j]} at beat ${beat}`);
                        outer++;
                    }
                }
            }
        }
        assert(outer <= 2, `${a.id}: ${outer} parallels with the tune at chord changes`);
    }
});

test('arranger: no unisons between neighbouring parts, the upper voices within an octave at each chord, the bass well down', async () => {
    for (const a of await everyArrangement()) {
        const onsets = [...new Set(VOICES.flatMap((v) => a.parts[v].map((n) => n.beat)))];
        for (const beat of onsets) {
            const [s, al, t, b] = VOICES.map((v) => at(a.parts[v], beat));
            if ([s, al, t, b].includes(null)) continue;
            assert(s > al && al > t && t > b, `${a.id}: a unison or crossing at beat ${beat}: ${s} ${al} ${t} ${b}`);
        }
        for (const n of a.parts.bass) {
            const s = at(a.parts.soprano, n.beat);
            const al = at(a.parts.alto, n.beat);
            const t = at(a.parts.tenor, n.beat);
            assert(s - al <= 12 && al - t <= 12, `${a.id}: the upper voices too far apart at beat ${n.beat}: ${s} ${al} ${t}`);
            assert(n.pitch <= 60 && n.pitch <= s - 9, `${a.id}: the bass too high at beat ${n.beat}: ${n.pitch} under ${s}`);
        }
    }
});

test('arranger: each chord has its third, and the song ends on a root-position home chord', async () => {
    for (const a of await everyArrangement()) {
        for (const [i, chord] of a.chords.entries()) {
            // The chord's first bass note (the bass breathes with the tune, so a chord may have two or none at its start).
            const beat = a.parts.bass.find((n) => n.beat >= chord.beat - 1e-6)?.beat ?? chord.beat;
            const classes = VOICES.map((v) => ((at(a.parts[v], beat) % 12) + 12) % 12);
            const third = (chord.root + (chord.quality === 'min' ? 3 : 4)) % 12;
            assert(classes.includes(third), `${a.id}: no third in the chord at beat ${beat}`);
        }
        const last = a.parts.bass.at(-1);
        assert(((last.pitch % 12) + 12) % 12 === a.key.tonic, `${a.id}: ends on ${last.pitch}, not the home note`);
    }
});

test('arranger: the inner voices hold or move by step, and seldom leap', async () => {
    for (const a of await everyArrangement()) {
        let smooth = 0;
        let moves = 0;
        for (const v of ['alto', 'tenor']) {
            const line = a.parts[v];
            for (let i = 1; i < line.length; i++) {
                const leap = Math.abs(line[i].pitch - line[i - 1].pitch);
                moves++;
                if (leap <= 2) smooth++;
                assert(leap <= 9 && leap !== 6, `${a.id}: the ${v} leaps ${leap} at beat ${line[i].beat}`);
            }
        }
        // A whole song's measure: in a one-line fixture (All Through the Night's 22 moves) the two leaps
        // its tune forces (F# held over D, then down to D, with no room under it) are 9% alone.
        if (moves >= 40) assert(smooth / moves >= 0.7, `${a.id}: only ${smooth} of ${moves} inner moves hold or step`);
    }
});

test('arranger: the leading tone is sung by one part at most, the tune\'s when it has it, and never by the bass', async () => {
    for (const a of await everyArrangement()) {
        const leading = (a.key.tonic + 11) % 12;
        const onsets = [...new Set(VOICES.flatMap((v) => a.parts[v].map((n) => n.beat)))];
        for (const beat of onsets) {
            const on = VOICES.filter((v) => ((at(a.parts[v], beat) % 12) + 12) % 12 === leading);
            assert(on.length <= 1, `${a.id}: the leading tone doubled (${on.join(' and ')}) at beat ${beat}`);
            assert(!on.includes('bass'), `${a.id}: the bass on the leading tone at beat ${beat}`);
        }
    }
});

test('arranger: from V to I an inner voice on the leading tone goes up to home (or down to the fifth)', async () => {
    for (const a of await everyArrangement()) {
        if (a.key.mode !== 'major') continue;
        const leading = (a.key.tonic + 11) % 12;
        a.chords.forEach((chord, i) => {
            const prev = a.chords[i - 1];
            if (!prev || (prev.root - a.key.tonic + 12) % 12 !== 7 || chord.root !== a.key.tonic) return;
            // The chord's first bass note (the bass breathes with the tune, so a chord may have two or none at its start).
            const beat = a.parts.bass.find((n) => n.beat >= chord.beat - 1e-6)?.beat ?? chord.beat;
            for (const v of ['alto', 'tenor']) {
                const [was, now] = [just(a.parts[v], beat), at(a.parts[v], beat)];
                if (was === null || ((was % 12) + 12) % 12 !== leading) continue;
                assert(now === was + 1 || now === was - 4, `${a.id}: the ${v}'s leading tone goes ${was} to ${now} at beat ${beat}`);
            }
        });
    }
});

test('arranger: no second under a strong or long note of the tune, and no leap inside a chord', async () => {
    for (const a of await everyArrangement()) {
        // Strong as the arranger counts it: a bar's first beat, and the third in 4/4.
        const perBar = beatsPerBar(a.timeSignature);
        const on = (beat, every) => Math.abs(((beat - a.pickupBeats) / every) - Math.round((beat - a.pickupBeats) / every)) < 1e-3;
        const strong = (beat) => on(beat, perBar) || (perBar === 4 && on(beat, 2));
        for (const n of a.parts.soprano) {
            if (!strong(n.beat) && n.length < 2) continue;
            const gap = n.pitch - at(a.parts.alto, n.beat);
            assert(gap > 2, `${a.id}: the alto a second under the tune at beat ${n.beat}`);
        }
    }
    // Steal Away's leap down an octave (trad.): the alto was a sixth over the tune's low note, then leapt a sixth.
    const steal = song(tune([[1.5, 65], [0.5, 65], [2, 65], [1.5, 69], [0.5, 69], [2, 69], [1.5, 72], [0.5, 72], [1.5, 72], [0.5, 65], [1, 65], [1, 65], [2, 65]]), '4/4', 0, [[0, 16]]);
    for (const style of STYLES) {
        const { template } = harmonize(steal, { style, key: { tonic: 5, mode: 'major' } });
        for (const k of ['alto', 'tenor']) {
            const line = template.parts.find((p) => p.key === k).guide.notes;
            line.slice(1).forEach((n, i) => assert(Math.abs(n.pitch - line[i].pitch) <= 7, `steal ${style}: the ${k} leaps ${line[i].pitch} to ${n.pitch} at beat ${n.beat}`));
        }
    }
});

test('arranger: a tune whose middle sits under C4 (a barbershop lead\'s) goes up an octave, with room under it', () => {
    const low = { ...NIGHT, parts: [{ ...NIGHT.parts[0], guide: { type: 'notes', notes: NIGHT.parts[0].guide.notes.map((n) => ({ ...n, pitch: n.pitch - 9 })) } }] };
    const { template } = harmonize(low, { key: { tonic: 10, mode: 'major' } });
    const part = (k) => template.parts.find((p) => p.key === k);
    assert(part('soprano').guide.notes[0].pitch === 67 - 9 + 12, `lifted: ${part('soprano').guide.notes[0].pitch}`);
    assert(part('alto').range.low >= 55 && part('tenor').range.low >= 48, `in range: ${JSON.stringify([part('alto').range, part('tenor').range])}`);
});

test('arranger: a tune over chords already chosen (the song builder) keeps those chords, voiced cleanly', async () => {
    const { chordsFor, PROGRESSIONS } = await import('/js/theory.js');
    // A tune over the four chords in C, twice round: C G Am F, a note a beat.
    const tune = [64, 67, 64, 60, 62, 67, 71, 67, 64, 69, 72, 69, 65, 69, 72, 65];
    const notes = [...tune, ...tune].map((pitch, i) => ({ beat: i, length: 1, pitch, lyric: 'la', ...(i % 16 === 0 ? { br: true } : {}) }));
    const bars = PROGRESSIONS.find((p) => p.id === 'four-chords').bars;
    const chords = [0, 16].flatMap((startBeat) => chordsFor(bars, { tonic: 0, startBeat })).map(({ beat, root, quality }) => ({ beat, root, quality, bass: null }));
    const song = { title: 'Tune', key: 'C', tempo: 90, timeSignature: '4/4', pickupBeats: 0, sections: [{ name: 'Song', startBeat: 0, endBeat: 32, phrases: [[0, 16], [16, 32]] }], parts: [{ key: 'soprano', name: 'Soprano', melody: true, range: { low: 60, high: 72 }, guide: { type: 'notes', notes } }] };
    const { template, result } = harmonize(song, { key: { tonic: 0, mode: 'major' }, chords });
    assert(result.chords.map((c) => `${c.root}${c.quality}`).join() === chords.map((c) => `${c.root}${c.quality}`).join(), `the chords kept: ${JSON.stringify(result.chords)}`);
    const [s, a, tn, b] = ['soprano', 'alto', 'tenor', 'bass'].map((k) => template.parts.find((p) => p.key === k).guide.notes);
    for (const c of chords) {
        const pitches = [s, a, tn, b].map((line) => at(line, c.beat));
        assert(pitches[0] > pitches[1] && pitches[1] > pitches[2] && pitches[2] > pitches[3], `crossing at ${c.beat}: ${pitches}`);
        assert(((pitches[3] % 12) + 12) % 12 === c.root, `the bass on the root at ${c.beat}`);
    }
});

// 31's port found these (the Flutter app's creator matches this module case for case).
const bare = (notes, extra = {}) => ({ key: 'C', timeSignature: '4/4', pickupBeats: 0, tempo: 90, sections: [{ name: 'verse', phrases: [[0, notes.at(-1).beat + notes.at(-1).length]] }], parts: [{ key: 'melody', name: 'Melody', melody: true, guide: { type: 'notes', notes } }], ...extra });
const pcOf = (p) => ((p % 12) + 12) % 12;

test('arrange: a tune sitting low over a chord on C to Eb still gets a bass (it goes down to C2), never a crash', () => {
    // Middle around A3 (not lifted), lowest G3: E2 to B2 is all the bass has, and C, C#, D, Eb have no root there.
    const notes = [57, 55, 57, 59, 57, 55, 57, 57].map((pitch, i) => ({ beat: i, length: 1, pitch }));
    for (const root of [0, 1, 2, 3]) {
        const chords = [{ beat: 0, root, quality: 'maj' }, { beat: 4, root, quality: 'maj' }];
        const { template } = harmonize(bare(notes), { key: { tonic: 0, mode: 'major' }, chords });
        const bass = template.parts.find((p) => p.key === 'bass').guide.notes;
        assert(bass.length && bass.every((n) => pcOf(n.pitch) === root && n.pitch >= 36 && n.pitch <= 60), `a bass on root ${root}: ${bass.map((n) => n.pitch)}`);
    }
    // Its own chords too (no chords given): every key's I under a low tune.
    for (const tonic of [0, 1, 2, 3]) {
        const low = notes.map((n) => ({ ...n, pitch: n.pitch - 12 + tonic + 12 }));
        const { template } = harmonize(bare(low), { key: { tonic, mode: 'major' } });
        assert(template.parts.every((p) => p.guide.notes.length), `tonic ${tonic}: every part has notes`);
    }
});

test('arrange: sus2 and 6 chords (the songs use them) are voiced from their own tones', () => {
    const notes = [64, 62, 64, 67, 69, 67, 64, 62].map((pitch, i) => ({ beat: i, length: 1, pitch }));
    const chords = [{ beat: 0, root: 0, quality: 'sus2' }, { beat: 4, root: 5, quality: 'maj6' }];
    const { template } = harmonize(bare(notes), { key: { tonic: 0, mode: 'major' }, chords });
    const tones = { 0: [0, 2, 7], 4: [5, 9, 0, 2] };
    for (const key of ['alto', 'tenor', 'bass']) {
        for (const n of template.parts.find((p) => p.key === key).guide.notes) {
            const slot = n.beat < 4 ? 0 : 4;
            assert(tones[slot].includes(pcOf(n.pitch)), `${key} at ${n.beat}: ${n.pitch} is in the chord`);
        }
    }
    assert(template.chords.map((c) => c.quality).join(' ') === 'sus2 maj6', 'the chords kept as given');
});

test('arrange: the creator\'s Parts step voices a Start from chords song on its own chords', async () => {
    const { PartsStep } = await import('/js/components/create/parts.js');
    const chords = [{ beat: 0, root: 7, quality: 'maj' }, { beat: 4, root: 0, quality: 'maj' }];
    const notes = [67, 65, 64, 62, 64, 64, 67, 67].map((pitch, i) => ({ beat: i, length: 1, pitch }));
    const draft = { mode: 'chords', harmony: 'suggest', style: 'simple', choices: {}, template: { ...bare(notes), chords } };
    const vm = { draft };
    vm.givenChords = PartsStep.computed.givenChords.call(vm);
    assert(vm.givenChords === chords, 'chords mode passes the chords');
    const result = PartsStep.computed.arranged.call(vm);
    assert(result.chords.map((c) => `${c.root}${c.quality}`).join(' ') === '7maj 0maj', `voiced on G then C: ${result.chords.map((c) => c.root).join(' ')}`);
    assert(PartsStep.computed.givenChords.call({ draft: { ...draft, mode: 'sing' } }) === null, 'a sung song picks its own');
});

test('arrange: the mobile app\'s test vectors (tests/vectors/arrange.json) still match the module', async () => {
    const { vectors } = await (await fetch('/tests/vectors/arrange.json')).json();
    // The file's keys are sorted (vectors.py): compare with the keys in order.
    const canon = (v) => (Array.isArray(v) ? v.map(canon) : v && typeof v === 'object' ? Object.fromEntries(Object.keys(v).sort().map((k) => [k, canon(v[k])])) : v);
    const same = (a, b) => JSON.stringify(canon(a)) === JSON.stringify(canon(b));
    assert(vectors.harmonize.length > 15, 'the songs in every style, and the edge cases');
    for (const v of vectors.harmonize) assert(same(JSON.parse(JSON.stringify(harmonize(v.input.template, v.input.options).template)), v.output), `harmonize ${v.input.song} ${v.input.options.style}: run web/tool/vectors.py`);
});

test('arrange: four-voice block chords (the voicer tool) are complete, the root doubled, a seventh never doubled', () => {
    const pcOf = (p) => ((p % 12) + 12) % 12;
    const voice = (chords, key, soprano) => {
        const melody = soprano.map((pitch, i) => ({ beat: i * 2, length: 2, pitch, lyric: 'oo' }));
        const given = chords.map((c, i) => ({ beat: i * 2, ...c }));
        const { parts } = arrange(melody, { key, timeSignature: '4/4', style: 'simple', chords: given, phraseEnds: [chords.length * 2], four: true });
        const at = (line, beat) => line.findLast((n) => n.beat <= beat + 1e-6 && n.beat + n.length > beat + 1e-6).pitch;
        return chords.map((_, i) => [soprano[i], at(parts.alto, i * 2), at(parts.tenor, i * 2), at(parts.bass, i * 2)]);
    };
    // C Am F G7 C under C C C B C: the textbook voicing.
    const c = voice([{ root: 0, quality: 'maj' }, { root: 9, quality: 'min' }, { root: 5, quality: 'maj' }, { root: 7, quality: 'dom7' }, { root: 0, quality: 'maj' }], { tonic: 0, mode: 'major' }, [72, 72, 72, 71, 72]);
    assert(JSON.stringify(c) === JSON.stringify([[72, 64, 55, 48], [72, 64, 57, 45], [72, 65, 57, 41], [71, 65, 55, 43], [72, 64, 55, 48]]), `C Am F G7 C: ${JSON.stringify(c)}`);
    // F Bb C7 F: every triad has its fifth, and C7 doesn't double its B flat.
    const f = voice([{ root: 5, quality: 'maj' }, { root: 10, quality: 'maj' }, { root: 0, quality: 'dom7' }, { root: 5, quality: 'maj' }], { tonic: 5, mode: 'major' }, [69, 70, 70, 65]);
    const roots = [5, 10, 0, 5];
    f.forEach((v, i) => {
        const classes = v.map(pcOf);
        if (i !== 2) assert(classes.includes((roots[i] + 7) % 12), `chord ${i}: the fifth is there (${v})`);
        assert(classes.filter((x) => x === roots[i]).length <= 2, `chord ${i}: the root isn't tripled (${v})`);
    });
    assert(f[2].map(pcOf).filter((x) => x === 10).length === 1, `C7's seventh sung once (${f[2]})`);
});

test('arrange: a given slash chord keeps its own bass (C G/B Am F/A G7 C walks down), the rest voiced over it', () => {
    const chords = [{ root: 0, quality: 'maj' }, { root: 7, quality: 'maj', bass: 11 }, { root: 9, quality: 'min' }, { root: 5, quality: 'maj', bass: 9 }, { root: 7, quality: 'dom7' }, { root: 0, quality: 'maj' }];
    const melody = [72, 74, 72, 72, 71, 72].map((pitch, i) => ({ beat: i * 2, length: 2, pitch, lyric: 'oo' }));
    for (const four of [true, false]) {
        const result = arrange(melody, { key: { tonic: 0, mode: 'major' }, timeSignature: '4/4', style: 'simple', chords: chords.map((c, i) => ({ beat: i * 2, ...c })), phraseEnds: [12], four, slashBass: true });
        const at = (line, beat) => line.findLast((n) => n.beat <= beat + 1e-6 && n.beat + n.length > beat + 1e-6).pitch;
        const bass = chords.map((_, i) => at(result.parts.bass, i * 2) % 12);
        assert(bass.join(' ') === '0 11 9 9 7 0', `the bass walks C B A A G C (${bass.join(' ')}, four ${four})`);
        assert(result.chords[1].bass === 11 && result.chords[3].bass === 9 && result.chords[0].bass === null, 'the chords keep their slash basses');
        const gb = ['alto', 'tenor', 'bass'].map((k) => at(result.parts[k], 2) % 12);
        assert(gb.filter((x) => x === 11).length === 1, `G/B's B, the leading tone, sung once (${gb})`);
    }
});
