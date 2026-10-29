// Hum a tune (js/hum.js, docs/hum-a-tune-plan.md): the library's tunes, hummed with a human's
// timing (a tempo that drifts, onsets a little early or late, notes let go early, breaths between
// lines), come back with their key, metre, bar lines and notes; with a click or Tap along the
// beats are given; the result keeps the template rules; and a hum sung into the pitch tracker
// comes through noteEvents the same way.
import { trackTake } from '/js/pitchtrack.js';
import { noteEvents } from '/js/transcribe.js';
import { SYLLABLE, beatsFromTaps, humToMelody, rehum } from '/js/hum.js';
import { VOICE_RANGES } from '/js/theory.js';
import { assert, test } from './harness.js';

const tunes = async () => (await (await fetch('/tests/fixtures/hum-tunes.json')).json()).tunes;
const PER_BAR = { '4/4': 4, '3/4': 3, '2/4': 2, '6/8': 2 };

/** A seeded random number generator, so a failure can be run again. */
function random(seed) {
    let r = seed;
    return () => ((r = (r * 16807) % 2147483647) / 2147483647);
}

/** A tune's notes as a person hums them: start and end times in seconds, a pitch each. */
function hum(tune, { seed = 7, jitter = 0.025, drift = 0.08 } = {}) {
    const rand = random(seed);
    const gauss = () => Math.sqrt(-2 * Math.log(rand() + 1e-12)) * Math.cos(2 * Math.PI * rand());
    const notes = tune.notes.map(([beat, length, pitch, br]) => ({ beat, length, pitch, br: !!br }));
    const end = Math.max(...notes.map((n) => n.beat + n.length));
    const base = 60 / tune.tempo;
    const slope = (rand() * 2 - 1) * drift; // slowing or rushing across the song
    const timeOf = (beat) => {
        let s = 0;
        for (let b = 0; b < beat; b += 0.05) s += base * (1 + slope * (b / end) + (drift ? 0.03 : 0) * Math.sin(b / 3)) * Math.min(0.05, beat - b);
        return s;
    };
    const events = notes.map((n, i) => {
        const start = 0.5 + timeOf(n.beat) + gauss() * jitter;
        let stop = 0.5 + timeOf(n.beat + n.length) - 0.06 - rand() * 0.05;
        if (notes[i + 1]?.br) stop = Math.min(stop, 0.5 + timeOf(notes[i + 1].beat) - 0.3 - rand() * 0.2); // a breath
        return { start, end: Math.max(stop, start + 0.08), pitch: n.pitch, sure: true };
    });
    return { events, notes };
}

/**
 * How a result compares with the tune: the notes in place (allowing the beat to come back doubled,
 * halved, or as 3/4 for 6/8 and 6/8 for 3/4, which write the same bars and rhythm), the bar lines (4/4's half bar counts, as
 * the arranger's chords change by the half bar) and the key.
 */
function score(tune, all, got) {
    // Only the first 32 bars are kept (Hillel's limit), so only they are expected.
    const notes = all.filter((n) => n.beat < tune.pickupBeats + 32 * PER_BAR[tune.timeSignature]);
    const first = notes[0].beat;
    // The notes in common, in order (a dropped or extra note costs only itself).
    const common = (s) => {
        const want = notes.map((n) => `${Math.round((n.beat - first) * s * 1000)}:${n.pitch}`);
        const have = new Set(got.notes.map((n) => `${Math.round(n.beat * 1000)}:${n.pitch}`));
        return want.filter((w) => have.has(w)).length;
    };
    let hits = 0;
    let scale = 1;
    for (const s of [1, 0.5, 2, 1.5, 2 / 3]) {
        const h = common(s);
        if (h > hits) [hits, scale] = [h, s];
    }
    const size = PER_BAR[got.timeSignature];
    const same = got.timeSignature === tune.timeSignature || (['4/4', '2/4'].includes(got.timeSignature) && ['4/4', '2/4'].includes(tune.timeSignature)) || (tune.timeSignature === '6/8' && got.timeSignature === '3/4' && scale === 1.5) || (tune.timeSignature === '3/4' && got.timeSignature === '6/8' && Math.abs(scale - 2 / 3) < 1e-6);
    const want = ((tune.pickupBeats - first) * scale) % size;
    const d = (((got.pickupBeats - want) % size) + size) % size;
    const bars = same && (d < 1e-3 || (size === 4 && Math.abs(d - 2) < 1e-3));
    return { hits, total: notes.length, bars, key: got.key === tune.key };
}

test('hum: the library\'s 20 tunes, hummed in free time, come back with their key, bars and notes', async () => {
    const all = await tunes();
    assert(all.length >= 20, `the tunes: ${all.length}`);
    for (const seed of [7, 11, 23]) {
        let hits = 0;
        let total = 0;
        let bars = 0;
        let keys = 0;
        const misses = [];
        for (const tune of all) {
            const { events, notes } = hum(tune, { seed });
            const result = score(tune, notes, humToMelody(events));
            hits += result.hits;
            total += result.total;
            bars += result.bars ? 1 : 0;
            keys += result.key ? 1 : 0;
            if (!result.bars || !result.key || result.hits < result.total * 0.8) misses.push(tune.id);
        }
        // The plan's bar: the bars on all but two and 90% of notes; the key on all but one.
        assert(keys >= all.length - 1, `seed ${seed}: the key on ${keys} of ${all.length} (${misses.join(', ')})`);
        assert(bars >= all.length - 2, `seed ${seed}: the bars on ${bars} of ${all.length} (${misses.join(', ')})`);
        assert(hits / total >= 0.9, `seed ${seed}: ${Math.round((100 * hits) / total)}% of notes (${misses.join(', ')})`);
    }
});

test('hum: a rough take whose beat came out wrong often says it isn\'t sure (so the app can ask for Tap along), and a good take seldom asks', async () => {
    const all = await tunes();
    let wrong = 0;
    let flagged = 0;
    let gentle = 0;
    let asked = 0;
    for (const seed of [7, 11, 23]) {
        for (const tune of all) {
            // Rough: onsets 40 ms out, the tempo drifting 12%.
            const rough = hum(tune, { seed, jitter: 0.04, drift: 0.12 });
            const got = humToMelody(rough.events);
            const result = score(tune, rough.notes, got);
            if (result.hits < result.total * 0.8) {
                wrong++;
                if (!got.sure.beat) flagged++;
            }
            // Gentle, and right: it should seldom ask.
            const take = hum(tune, { seed });
            const fine = humToMelody(take.events);
            if (score(tune, take.notes, fine).hits >= take.notes.length * 0.8) {
                gentle++;
                if (!fine.sure.beat) asked++;
            }
        }
    }
    assert(flagged >= wrong / 3, `flags ${flagged} of ${wrong} wrong rough takes`);
    assert(asked <= gentle * 0.1, `asks ${asked} of ${gentle} good gentle takes`);
});

test('hum: with the beats given (a click, or Tap along), a tune comes back note for note', async () => {
    const all = await tunes();
    for (const id of ['amazing-grace', 'ode-to-joy', 'silent-night', 'wade-in-the-water']) {
        const tune = all.find((t) => t.id === id);
        const { events, notes } = hum(tune, { jitter: 0.015, drift: 0 });
        const base = 60 / tune.tempo;
        const first = notes[0].beat;
        // The click's beats, on the tune's own beat 0.
        const beats = Array.from({ length: 200 }, (_, k) => 0.5 + k * base);
        const got = humToMelody(events, { beats, timeSignature: tune.timeSignature });
        const result = score(tune, notes, got);
        assert(result.hits === result.total, `${id}: ${result.hits} of ${result.total} notes`);
        assert(got.sure.beat && got.sure.metre, `${id}: sure with a click`);
        // Tap along: a few taps a beat apart give the same.
        const taps = beatsFromTaps(Array.from({ length: 16 }, (_, k) => 0.5 + k * base));
        assert(score(tune, notes, humToMelody(events, { beats: taps })).hits >= result.total * 0.95, `${id}: Tap along`);
    }
});

test('hum: every result keeps the template rules: "mm" on every note, lines marked, nothing overlapping, inside 32 bars', async () => {
    const all = await tunes();
    for (const tune of all) {
        const got = humToMelody(hum(tune).events);
        assert(got.notes.every((n) => n.lyric === SYLLABLE), `${tune.id}: every note sings "${SYLLABLE}"`);
        assert(got.notes[0].br, `${tune.id}: the first note starts a line`);
        for (let i = 1; i < got.notes.length; i++) {
            const a = got.notes[i - 1];
            const b = got.notes[i];
            assert(a.length > 0 && a.beat + a.length <= b.beat + 1e-6, `${tune.id}: notes ${i - 1} and ${i} overlap`);
        }
        const size = PER_BAR[got.timeSignature];
        const end = got.notes.at(-1).beat + got.notes.at(-1).length;
        assert(Math.abs((((end - got.pickupBeats) % size) + size) % size) < 1e-6, `${tune.id}: ends on a bar line (${end})`);
        assert(got.pickupBeats >= 0 && got.pickupBeats < size, `${tune.id}: the pickup ${got.pickupBeats}`);
        assert((end - got.pickupBeats) / size <= 32 + 1e-6, `${tune.id}: inside 32 bars`);
    }
    // A tune hummed three times over is cut at 32 bars, and says so.
    const long = all.find((t) => t.id === 'swing-low');
    const thrice = { ...long, notes: [0, 1, 2].flatMap((k) => long.notes.map(([beat, ...rest]) => [beat + k * 96, ...rest])) };
    const got = humToMelody(hum(thrice).events);
    assert(got.cut, 'a long take is cut');
    const size = PER_BAR[got.timeSignature];
    const end = got.notes.at(-1).beat + got.notes.at(-1).length;
    assert((end - got.pickupBeats) / size <= 32 + 1e-6, `cut at 32 bars (${(end - got.pickupBeats) / size})`);
});

test('hum: unsure of the beat on most wrong takes, and on few right ones (the labelled takes)', async () => {
    // tests/fixtures/hum-unsure.json: every tune hummed gently and roughly with seeds 1 to 8, and how
    // the flag did when it was last tuned. A change may catch more or ask less, never the reverse.
    const vector = await (await fetch('/tests/fixtures/hum-unsure.json')).json();
    const all = await tunes();
    const counts = {};
    for (const kind of ['gentle', 'rough']) {
        const c = { right: 0, wrong: 0, caught: 0, falseAlarms: 0 };
        for (const seed of vector.seeds) {
            for (const tune of all) {
                const { events, notes } = hum(tune, { seed, jitter: vector.jitter[kind] });
                const got = humToMelody(events);
                const result = score(tune, notes, got);
                if (result.bars && result.hits >= result.total * 0.8) {
                    c.right++;
                    if (!got.sure.beat) c.falseAlarms++;
                } else {
                    c.wrong++;
                    if (!got.sure.beat) c.caught++;
                }
            }
        }
        counts[kind] = c;
        const was = vector.counts[kind];
        assert(c.caught >= was.caught, `${kind}: caught ${c.caught} of ${c.wrong} wrong takes, was ${was.caught} of ${was.wrong}`);
        assert(c.falseAlarms <= was.falseAlarms, `${kind}: unsure on ${c.falseAlarms} of ${c.right} right takes, was ${was.falseAlarms} of ${was.right}`);
    }
    // The guard: on gentle takes it asks for Tap along at most one time in ten.
    assert(counts.gentle.falseAlarms <= counts.gentle.right / 10, `gentle: unsure on ${counts.gentle.falseAlarms} of ${counts.gentle.right} right takes`);
});

test('hum: a short take (a tune\'s first two lines) comes back in its key, the metre helping where it stops on the fifth', async () => {
    const all = await tunes();
    let right = 0;
    let takes = 0;
    for (const tune of all) {
        // The first two lines, as a singer trying an idea might hum them.
        const starts = tune.notes.map((n, i) => (i === 0 || n[3] ? i : -1)).filter((i) => i >= 0);
        const two = { ...tune, notes: tune.notes.slice(0, starts[2] ?? tune.notes.length) };
        for (let seed = 1; seed <= 6; seed++) {
            const got = humToMelody(hum(two, { seed }).events);
            takes++;
            if (got.key === tune.key) right++;
            // Two lines of Amazing Grace end on D, its fifth: the key is still G.
            if (tune.id === 'amazing-grace') assert(got.key === 'G', `Amazing Grace's two lines, seed ${seed}: ${got.key}`);
        }
    }
    // A whole tune's key is nearly always right; two lines often stop on the fifth, and were right on
    // 55 of 132 before the metre counted (74 with it).
    assert(right >= 66, `the key of ${right} of ${takes} two-line takes`);
});

const SR = 16000;

/** A voice humming `events` (seconds), with a flat drift, vibrato and a little noise. */
function voice(events, { drift = -25, vibrato = 30 } = {}) {
    const rand = random(5);
    const end = Math.max(...events.map((e) => e.end)) + 0.5;
    const out = new Float32Array(Math.ceil(end * SR));
    for (let i = 0; i < out.length; i++) out[i] = 0.003 * (rand() * 2 - 1);
    for (const e of events) {
        let phase = 0;
        const from = Math.round(e.start * SR);
        const to = Math.round(e.end * SR);
        for (let i = from; i < to && i < out.length; i++) {
            const t = (i - from) / SR;
            const hz = 440 * 2 ** ((e.pitch + (drift + vibrato * Math.sin(2 * Math.PI * 5.5 * t)) / 100 - 69) / 12);
            phase += (2 * Math.PI * hz) / SR;
            const env = Math.min(1, t / 0.04, (to - i) / SR / 0.05);
            out[i] += 0.25 * env * (Math.sin(phase) + Math.sin(2 * phase) / 3); // a hum: few harmonics
        }
    }
    return out;
}

test('hum: Amazing Grace hummed into the pitch tracker, flat and with vibrato, comes back through noteEvents', async () => {
    const tune = (await tunes()).find((t) => t.id === 'amazing-grace');
    const { events, notes } = hum(tune, { seed: 3 });
    const found = noteEvents(trackTake(voice(events), SR));
    assert(!found.empty && !found.noisy, 'heard');
    assert(found.driftCents < -10, `the flat drift is measured (${Math.round(found.driftCents)})`);
    const result = score(tune, notes, humToMelody(found.events));
    assert(result.hits >= result.total * 0.9 && result.bars && result.key, `${result.hits} of ${result.total} notes, bars ${result.bars}, key ${result.key}`);
});

test('hum: each phrase knows its notes and its seconds, and humming a line again swaps just that line', async () => {
    const all = await tunes();
    let same = 0;
    let tried = 0;
    for (const tune of all) {
        const { events } = hum(tune, { seed: 7 });
        const got = humToMelody(events);
        // The phrases cover every note, in order, each starting on a line's first note.
        let next = 0;
        for (const ph of got.phrases) {
            assert(ph.fromNote === next && ph.toNote >= ph.fromNote && got.notes[ph.fromNote].br, `${tune.id}: a phrase from note ${ph.fromNote}`);
            assert(ph.startSeconds < ph.endSeconds, `${tune.id}: a phrase's seconds`);
            next = ph.toNote + 1;
        }
        assert(next === got.notes.length, `${tune.id}: the phrases cover the notes`);
        if (got.phrases.length < 3) continue;
        // The second line hummed again, on its own clock (5 s later) and with its own wobble.
        const ph = got.phrases[1];
        const again = hum(tune, { seed: 99 }).events.slice(ph.fromNote, ph.toNote + 1).map((e) => ({ ...e, start: e.start + 5, end: e.end + 5 }));
        const re = rehum(got, 1, again);
        assert(re.tempo === got.tempo && re.key === got.key && re.timeSignature === got.timeSignature, `${tune.id}: rehum keeps the tempo, key and metre`);
        assert(re.notes.every((n, i) => i === 0 || re.notes[i - 1].beat + re.notes[i - 1].length <= n.beat + 1e-6), `${tune.id}: rehum leaves no overlaps`);
        assert(JSON.stringify(re.notes.slice(0, ph.fromNote)) === JSON.stringify(got.notes.slice(0, ph.fromNote)), `${tune.id}: the lines before are untouched`);
        tried++;
        if (JSON.stringify(re.notes.map((n) => [n.beat, n.length, n.pitch])) === JSON.stringify(got.notes.map((n) => [n.beat, n.length, n.pitch]))) same++;
    }
    assert(same >= tried - 2, `the same line hummed again comes back the same in ${same} of ${tried}`);
    // A different line in its place: its pitches, from the line's first beat, cut off before the next.
    const tune = all.find((t) => t.id === 'amazing-grace');
    const got = humToMelody(hum(tune).events);
    const ph = got.phrases[0];
    const other = [0, 0.75, 1.5, 2.25].map((t, i) => ({ start: 10 + t, end: 10 + t + 0.6, pitch: 64 + i, sure: true }));
    const re = rehum(got, 0, other);
    assert(re.notes.slice(0, 4).map((n) => n.pitch).join() === '64,65,66,67' && re.notes[0].beat === ph.startBeat && re.notes[0].br, 'the new line is in place');
    assert(re.notes[3].beat + re.notes[3].length <= got.phrases[1].startBeat + 1e-6, 'and ends before the next line');
    assert(re.phrases[1].fromNote === 4, 'the next line starts after it');
});

test('hum: the flows\' hummed fixture (two lines of Amazing Grace, flat, with vibrato) comes back as its two lines', async () => {
    const buffer = await (await fetch('/tests/fixtures/hum-two-lines.wav')).arrayBuffer();
    const view = new DataView(buffer);
    const rate = view.getUint32(24, true);
    const samples = new Float32Array((buffer.byteLength - 44) / 2);
    for (let i = 0; i < samples.length; i++) samples[i] = view.getInt16(44 + i * 2, true) / 32768;
    const found = noteEvents(trackTake(samples, rate));
    const got = humToMelody(found.events);
    const want = [62, 67, 71, 67, 71, 69, 67, 64, 62, 62, 67, 71, 67, 71, 69, 74];
    assert(got.notes.map((n) => n.pitch).join() === want.join(), `the notes: ${got.notes.map((n) => n.pitch).join()}`);
    assert(got.phrases.length === 2 && got.timeSignature === '3/4' && got.key === 'G', `two lines in 3/4, G (${got.phrases.length}, ${got.timeSignature}, ${got.key})`);
});

// ---- The harmony at 2 to 4 singers (docs/hum-a-tune-plan.md, step 4) ----

const THIRD = { maj: 4, min: 3, dom7: 4, maj6: 4, min7: 3, dim: 3, aug: 4, maj7: 4, dim7: 3, hdim7: 3, min6: 3, add9: 4 };
const KEYS = ['C', 'C#', 'D', 'Eb', 'E', 'F', 'F#', 'G', 'Ab', 'A', 'Bb', 'B'];

/** A tune from the fixture as a template the arranger takes: its phrases from the lines it marks. */
function templateOf(tune) {
    const notes = tune.notes.map(([beat, length, pitch, br]) => ({ beat, length, pitch, lyric: 'la', ...(br ? { br: true } : {}) }));
    const end = Math.max(...notes.map((n) => n.beat + n.length));
    const starts = notes.filter((n, i) => i === 0 || n.br).map((n) => n.beat);
    return {
        id: tune.id, tempo: tune.tempo, timeSignature: tune.timeSignature, pickupBeats: tune.pickupBeats, key: tune.key,
        sections: [{ name: 'Verse', startBeat: 0, endBeat: end, phrases: starts.map((s, i) => [s, starts[i + 1] ?? end]) }],
        parts: [{ key: 'soprano', name: 'Soprano', melody: true, range: { low: 0, high: 127 }, guide: { type: 'notes', notes } }],
    };
}

/**
 * How harmonised songs do: chord changes where the duet (soprano, alto; the trio adds the bass, on
 * the root) has no third, four-part chords without one, parallel fifths and octaves (both voices
 * moving the same way), crossings or unisons, and inner leaps over a fifth and over a sixth.
 */
function measure(templates) {
    const out = { changes: 0, duetNoThird: 0, fourNoThird: 0, parallels: 0, crossings: 0, leapsOverFifth: 0, leapsOverSixth: 0 };
    const keys = ['soprano', 'alto', 'tenor', 'bass'];
    for (const t of templates) {
        const P = Object.fromEntries(t.parts.map((p) => [p.key, p.guide.notes]));
        const at = (k, b) => P[k].find((n) => n.beat <= b + 1e-6 && b + 1e-6 < n.beat + n.length)?.pitch;
        for (const c of t.chords) {
            if (!(c.quality in THIRD)) continue;
            const third = (c.root + THIRD[c.quality]) % 12;
            const sung = keys.map((k) => at(k, c.beat));
            if (sung.some((p) => p === undefined)) continue;
            out.changes++;
            if (!sung.slice(0, 2).some((p) => p % 12 === third)) out.duetNoThird++;
            if (!sung.some((p) => p % 12 === third)) out.fourNoThird++;
        }
        const onsets = [...new Set(keys.flatMap((k) => P[k].map((n) => n.beat)))].sort((x, y) => x - y);
        let prev = null;
        for (const b of onsets) {
            const cur = keys.map((k) => at(k, b));
            if (cur.every((p) => p !== undefined) && !(cur[0] > cur[1] && cur[1] > cur[2] && cur[2] > cur[3])) out.crossings++;
            if (prev) {
                for (let i = 0; i < 4; i++) {
                    for (let j = i + 1; j < 4; j++) {
                        const [x1, y1, x2, y2] = [prev[i], prev[j], cur[i], cur[j]];
                        if ([x1, y1, x2, y2].some((p) => p === undefined) || x1 === x2 || y1 === y2) continue;
                        const was = (((x1 - y1) % 12) + 12) % 12;
                        if (was === (((x2 - y2) % 12) + 12) % 12 && [0, 7].includes(was) && (x2 - x1) * (y2 - y1) > 0) out.parallels++;
                    }
                }
            }
            prev = cur;
        }
        for (const k of ['alto', 'tenor']) {
            for (let i = 1; i < P[k].length; i++) {
                const leap = Math.abs(P[k][i].pitch - P[k][i - 1].pitch);
                if (leap > 7) out.leapsOverFifth++;
                if (leap > 9) out.leapsOverSixth++;
            }
        }
    }
    return out;
}

// The arranger before the 2-to-4-singer work (2026-10-29), on the same 20 tunes and measures.
const BEFORE = {
    simple: { duetNoThird: 167, fourNoThird: 2, parallels: 4, crossings: 0, leapsOverFifth: 2, leapsOverSixth: 1 },
    warm: { duetNoThird: 182, fourNoThird: 0, parallels: 0, crossings: 0, leapsOverFifth: 2, leapsOverSixth: 0 },
    bright: { duetNoThird: 169, fourNoThird: 2, parallels: 8, crossings: 0, leapsOverFifth: 4, leapsOverSixth: 2 },
};

test('arranger: the duet and trio keep the third far more often, and the four parts get no worse', async () => {
    const { harmonize } = await import('/js/arrange.js');
    const all = (await tunes()).filter((t) => !['row-row-row', 'are-you-sleeping'].includes(t.id)); // rounds aren't harmonised
    assert(all.length === 18, `the tunes: ${all.length}`);
    let leaps = 0;
    let leapsBefore = 0;
    for (const style of ['simple', 'warm', 'bright']) {
        const done = all.map((tune) => harmonize(templateOf(tune), { key: { tonic: KEYS.indexOf(tune.key.replace('m', '')), mode: tune.key.endsWith('m') ? 'minor' : 'major' }, style }).template);
        const m = measure(done);
        const was = BEFORE[style];
        const share = m.duetNoThird / m.changes;
        // Today 19 to 22% (it was a third); the plan's aim is 10%.
        assert(Math.round(share * 100) <= 22 && m.duetNoThird < was.duetNoThird, `${style}: the duet misses the third at ${Math.round(share * 100)}% of chord changes`);
        assert(m.fourNoThird <= was.fourNoThird, `${style}: four-part chords without a third ${m.fourNoThird} (was ${was.fourNoThird})`);
        assert(m.parallels <= was.parallels, `${style}: parallels ${m.parallels} (was ${was.parallels})`);
        assert(m.crossings <= was.crossings, `${style}: crossings or unisons ${m.crossings}`);
        assert(m.leapsOverSixth === 0, `${style}: inner leaps over a sixth ${m.leapsOverSixth}`);
        for (const t of done) {
            for (const p of t.parts.filter((q) => q.key !== 'soprano')) {
                // Inside the voice's range; the bass may reach E2, the arranger's own floor (it did before too).
                const { high } = VOICE_RANGES[p.key];
                const low = p.key === 'bass' ? 40 : VOICE_RANGES[p.key].low;
                assert(p.range.low >= low && p.range.high <= high, `${style} ${t.id}: the ${p.key} ${p.range.low} to ${p.range.high}, outside ${low} to ${high}`);
            }
        }
        assert(m.leapsOverFifth <= was.leapsOverFifth + 1, `${style}: inner leaps over a fifth ${m.leapsOverFifth} (was ${was.leapsOverFifth})`);
        leaps += m.leapsOverFifth;
        leapsBefore += was.leapsOverFifth;
    }
    assert(leaps <= leapsBefore, `inner leaps over a fifth ${leaps} (was ${leapsBefore})`);
});
