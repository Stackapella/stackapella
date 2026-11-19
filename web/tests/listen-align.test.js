// The sync tool's mic step (js/listen-align.js) on a synthesized performance whose truth we know: two verses of a small
// hymn, two semitones down and 25 cents sharp, slower than our tempo with a fermata on each verse's last bar, after a
// quiet intro and with an organ-less pause between the verses, plus room noise.
import { hearChroma, likelyShifts, scoreChroma, suggest, toVideo } from '/js/listen-align.js';
import { beatAt, segments, unroll } from '/js/sync-map.js';
import { assert, close, test } from './harness.js';

const RATE = 16000;

// Eight bars of 3/4 at 60, in C: a tune that moves, a bass on the roots, an alto a third or sixth under.
function hymn() {
    const tune = [[0, 3, 67], [3, 2, 69], [5, 1, 67], [6, 3, 64], [9, 3, 60], [12, 2, 62], [14, 1, 64], [15, 3, 65], [18, 3, 69], [21, 3, 67]];
    const bass = [[0, 3, 48], [3, 3, 53], [6, 3, 48], [9, 3, 45], [12, 3, 43], [15, 3, 41], [18, 3, 53], [21, 3, 48]];
    const alto = tune.map(([b, l, p]) => [b, l, p - (p % 12 === 7 || p % 12 === 9 ? 3 : 4)]);
    const notes = (list) => list.map(([beat, length, pitch]) => ({ beat, length, pitch, lyric: 'la' }));
    return {
        id: 'hymn', key: 'C', tempo: 60, timeSignature: '3/4', pickupBeats: 0,
        sections: [{ name: 'Verse 1', startBeat: 0, endBeat: 24, phrases: [[0, 12], [12, 24]] }],
        chords: [],
        parts: [
            { key: 'soprano', range: { low: 60, high: 69 }, guide: { type: 'notes', notes: notes(tune) } },
            { key: 'alto', range: { low: 55, high: 65 }, guide: { type: 'notes', notes: notes(alto) } },
            { key: 'bass', range: { low: 41, high: 53 }, guide: { type: 'notes', notes: notes(bass) } },
        ],
    };
}

// The truth: an anchor per bar line. Bars of about 3.5 s (slower than our 3), uneven, the last of each verse held 6 s,
// verse 1 starting 3 s in and verse 2 after a 3 s pause.
function truth() {
    const anchors = [];
    let t = 3;
    const lengths = [3.4, 3.7, 3.3, 3.6, 3.5, 3.4, 3.8, 6];
    for (let verse = 0; verse < 2; verse++) {
        for (let bar = 0; bar < 8; bar++) {
            anchors.push([t, verse * 24 + bar * 3]);
            t += lengths[bar];
        }
        if (verse === 0) {
            anchors.push([t, 24 - 1e-3]); // the end of verse 1's held bar (for synthesis only)
            t += 3;
        }
    }
    anchors.push([t, 48]);
    return anchors;
}

function perform(template, anchors, { shift = -2, cents = 25, seconds = 72 } = {}) {
    const whole = unroll(template, [{ kind: 'section', section: 0, shift }, { kind: 'section', section: 0, shift }]);
    const out = new Float32Array(Math.round(seconds * RATE));
    // The performance's beat to seconds, inverse of beatAt over the truth's anchors.
    const at = (beat) => {
        for (let i = 1; i < anchors.length; i++) {
            const [t0, b0] = anchors[i - 1];
            const [t1, b1] = anchors[i];
            if (beat <= b1) return t0 + ((t1 - t0) * (beat - b0)) / (b1 - b0);
        }
        return anchors[anchors.length - 1][0];
    };
    for (const part of whole.parts) {
        for (const note of part.guide.notes) {
            const start = at(note.beat);
            const end = at(note.beat + note.length - 0.05);
            const hz = 440 * 2 ** ((note.pitch - 69 + cents / 100) / 12);
            const from = Math.round(start * RATE);
            const to = Math.min(out.length, Math.round(end * RATE));
            for (let i = from; i < to; i++) {
                const t = (i - from) / RATE;
                const env = Math.min(1, t / 0.05) * Math.min(1, (to - i) / RATE / 0.08);
                const w = 2 * Math.PI * hz * t;
                out[i] += 0.1 * env * (Math.sin(w) + 0.5 * Math.sin(2 * w) + 0.25 * Math.sin(3 * w));
            }
        }
    }
    let seed = 7;
    for (let i = 0; i < out.length; i++) {
        seed = (seed * 16807) % 2147483647;
        out[i] += 0.004 * (seed / 2147483647 - 0.5); // the room
    }
    return out;
}

const forms = [1, 2, 3].map((verses) => Array.from({ length: verses }, () => ({ kind: 'section', section: 0, shift: 0 })));
let cached = null;
const heardOnce = () => (cached ??= hearChroma(perform(hymn(), truth()), RATE));

test('listen align: the tuning is heard (25 cents sharp)', () => {
    close(heardOnce().cents, 25, 6, 'cents');
});

test('listen align: the key shift is among the likely ones, first', () => {
    const shifts = likelyShifts(heardOnce(), scoreChroma(hymn(), forms[1]));
    assert(shifts[0] === -2, `two down first, got ${shifts.join(',')}`);
});

test('listen align: the form, the key, the interlude and every bar line are suggested', () => {
    const started = performance.now();
    const s = suggest(hymn(), heardOnce(), forms);
    const ms = performance.now() - started;
    const sections = s.form.filter((x) => x.kind === 'section');
    assert(sections.length === 2, `two verses, got ${sections.length}`);
    assert(sections.every((x) => x.shift === -2 && Math.abs(x.cents - 25) <= 6), 'each shifted and tuned');
    const gap = s.form.find((x) => x.kind === 'gap');
    assert(gap && gap.beats === 3, `the pause is a one-bar gap, got ${JSON.stringify(gap)}`);
    assert(segments(hymn(), s.form)[2].start === 27, 'verse 2 after it');
    // Each true bar line (verse 2's moved past the gap) against the suggestion's time for that beat.
    const real = truth().filter(([, b]) => Number.isInteger(b)).map(([t, b]) => [t, b >= 24 ? b + 3 : b]);
    const errors = real.map(([t, b]) => {
        const found = s.anchors.find(([, beat]) => Math.abs(beat - b) < 1e-6);
        return found ? Math.abs(found[0] - t) : Infinity;
    });
    const missing = errors.filter((e) => e === Infinity).length;
    const sorted = errors.filter(Number.isFinite).sort((a, b) => a - b);
    const median = sorted[sorted.length >> 1];
    assert(missing === 0, `every bar line found, missed ${missing}`);
    assert(median < 0.2, `bar lines within a hop or two (median ${median?.toFixed(3)} s)`);
    assert(sorted[sorted.length - 1] < 0.35, `no bar line far off (worst ${sorted[sorted.length - 1]?.toFixed(3)} s)`);
    // The suggestion reads as a map: inside verse 1's third bar at its true time.
    const third = truth()[2][0] + 0.5;
    const b = beatAt(s.anchors, third);
    assert(b > 6 && b < 9, `bar 3 at ${third.toFixed(1)} s, got beat ${b?.toFixed(2)}`);
    assert(ms < 8000, `in reasonable time (${Math.round(ms)} ms)`);
});

test('listen align: silence suggests nothing', () => {
    assert(suggest(hymn(), hearChroma(new Float32Array(RATE * 5), RATE), forms) === null, 'nothing heard');
});

test('listen align: heard seconds become the video\'s from the log kept while listening', () => {
    const log = [[0, 30], [10, 40], [20, 50.5]];
    close(toVideo(log, 5), 35, 1e-9, 'inside');
    close(toVideo(log, 15), 45.25, 1e-9, 'a seek-free stretch at its own pace');
    close(toVideo(log, 25), 55.75, 1e-9, 'past the end, carried on');
});
