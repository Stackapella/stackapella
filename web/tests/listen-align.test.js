// The sync tool's mic step (js/listen-align.js) on a synthesized performance whose truth we know: two verses of a small
// hymn, two semitones down and 25 cents sharp, slower than our tempo with a fermata on each verse's last bar, after a
// quiet intro and with an organ-less pause between the verses, plus room noise.
import { hearChroma, likelyShifts, matchScore, scoreChroma, suggest, toVideo } from '/js/listen-align.js';
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

function perform(template, anchors, { shift = -2, cents = 25, seconds = 72, verses = 2, echo = false, noise = 0.004, drone = 0 } = {}) {
    const whole = unroll(template, Array.from({ length: verses }, () => ({ kind: 'section', section: 0, shift })));
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
    // A chapel: two feedback echoes smear each chord into the next (a long tail, soft onsets), as a webcam mic hears it.
    if (echo) {
        const a = Math.round(0.071 * RATE);
        const b = Math.round(0.133 * RATE);
        for (let i = b; i < out.length; i++) out[i] += 0.55 * out[i - a] + 0.3 * out[i - b];
    }
    // An organ holding the key's tonic and fifth under everything, intro and breaths too: chroma that matches every
    // frame a little, so the right path stands out less (a real room's mic hears much more than the choir).
    if (drone) {
        const tonic = 440 * 2 ** ((48 + shift - 69 + cents / 100) / 12);
        for (let i = 0; i < out.length; i++) {
            const w = (2 * Math.PI * i) / RATE;
            out[i] += drone * (Math.sin(w * tonic) + 0.7 * Math.sin(w * tonic * 1.5) + 0.4 * Math.sin(w * tonic * 2));
        }
    }
    let seed = 7;
    for (let i = 0; i < out.length; i++) {
        seed = (seed * 16807) % 2147483647;
        out[i] += noise * (seed / 2147483647 - 0.5); // the room
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

// Hillel's first real sync (2026-11-19): a chapel choir singing three verses, slower than our tempo, heard through a
// webcam mic, came back as four verses rushed at the fastest step allowed. The truth here is that shape: an 8 s intro,
// three verses of bars about 3.9 s (ours are 3), each ending on a held bar, a breath between, a long echo.
function slowThree() {
    const anchors = [];
    let t = 8;
    const lengths = [3.8, 4.1, 3.7, 4.0, 3.9, 3.8, 4.2, 6.5];
    for (let verse = 0; verse < 3; verse++) {
        for (let bar = 0; bar < 8; bar++) {
            anchors.push([t, verse * 24 + bar * 3]);
            t += lengths[bar];
        }
        if (verse < 2) {
            anchors.push([t, verse * 24 + 24 - 1e-3]);
            t += 1.2;
        }
    }
    anchors.push([t, 72]);
    return anchors;
}

test('listen align: three slow verses in an echoing chapel are three verses, at a steady pace, not four rushed', () => {
    const truthNow = slowThree();
    const heard = hearChroma(perform(hymn(), truthNow, { verses: 3, seconds: 112, echo: true, noise: 0.12, drone: 0.05 }), RATE);
    const five = [1, 2, 3, 4, 5].map((verses) => Array.from({ length: verses }, () => ({ kind: 'section', section: 0, shift: 0 })));
    const s = suggest(hymn(), heard, five);
    const sections = s.form.filter((x) => x.kind === 'section');
    assert(sections.length === 3, `three verses, got ${sections.length}`);
    assert(sections.every((x) => x.shift === -2), `two down, got ${sections.map((x) => x.shift).join(',')}`);
    // Each true bar line against the suggestion's, wherever the gaps put verse 2 and 3 (match by bar within its verse).
    const gaps = segments(hymn(), s.form).filter((x) => x.kind === 'section').map((x) => x.start);
    const errors = truthNow.filter(([, b]) => Number.isInteger(b) && b < 72).map(([t, b]) => {
        const verse = Math.floor(b / 24);
        const beat = gaps[verse] + (b % 24);
        const found = s.anchors.find(([, x]) => Math.abs(x - beat) < 1e-6);
        return found ? Math.abs(found[0] - t) : Infinity;
    });
    const sorted = [...errors].sort((a, b) => a - b);
    const median = sorted[sorted.length >> 1];
    assert(errors.filter((e) => e === Infinity).length <= 1, `bar lines found (missed ${errors.filter((e) => e === Infinity).length})`);
    assert(median < 0.3, `bar lines within a few hops (median ${median.toFixed(3)} s)`);
    // The organ holds each verse's opening chord through the intro and the breaths, so a verse's first bar line may land
    // early (the sync tool's tapping fixes it); every other is close.
    assert(errors.filter((e) => e > 0.6).length <= 3, `at most each verse's start off (${errors.filter((e) => e > 0.6).length} over 0.6 s)`);
    // A steady pace: no bar in a verse comes back faster than half the true pace's.
    const quick = s.anchors.slice(1).filter(([t, b], i) => b - s.anchors[i][1] === 3 && t - s.anchors[i][0] < 1.9).length;
    assert(quick <= 2, `no rushed bars (${quick} under 1.9 s)`);
});


test('listen align: an excerpt of our arrangement matches it better than the same tune harmonised another way', () => {
    // Ours (the hymn), and a "different arrangement": the same tune over a drone with no moving parts.
    const ours = hymn();
    const other = { ...hymn(), parts: [hymn().parts[0], { key: 'bass', range: { low: 36, high: 50 }, guide: { type: 'notes', notes: [{ beat: 0, length: 48, pitch: 43, lyric: 'oo' }] } }] };
    const excerpt = (template) => {
        const audio = perform(template, truth(), { seconds: 40 });
        return hearChroma(audio.subarray(Math.round(12 * RATE), Math.round(40 * RATE)), RATE);
    };
    const same = matchScore(ours, excerpt(ours));
    const differs = matchScore(ours, excerpt(other));
    assert(same && differs, 'both scored');
    assert(same.shift === -2, `the key heard, got ${same.shift}`);
    assert(same.match > differs.match + 0.05, `ours ${same.match.toFixed(2)} over another ${differs.match.toFixed(2)}`);
    assert(matchScore(ours, hearChroma(new Float32Array(RATE * 10), RATE)) === null, 'silence scores nothing');
});
