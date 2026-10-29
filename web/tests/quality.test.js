// The quality check before sending a beta take, and the sound check (js/quality.js), on
// synthesized takes: a good one passes; silent, quiet, clipped, noisy and bleeding ones are caught.
import { bleedCheck, checkTake, DEFAULT_BAR, levelChecks, phrasesToFix, rests, verdict } from '/js/quality.js';
import { assert, test } from './harness.js';
import { sing } from './fixtures.js';

const rate = 48000;
const bpm = 90;
const beatSeconds = 60 / bpm;
const notes = [
    { beat: 0, length: 2, pitch: 60 }, { beat: 2, length: 2, pitch: 64 },
    { beat: 6, length: 2, pitch: 62 }, { beat: 8, length: 2, pitch: 65 },
];
const codes = (result) => result.checks.map((c) => c.code);

test('quality: a good take passes, and says what went well first', () => {
    const result = checkTake(sing(rate, { notes, bpm }), rate, { notes, beatSeconds, phrases: [[0, 4], [6, 10]] });
    assert(codes(result).join() === 'levelGood,inTune', codes(result).join());
    assert(result.scores.pitchPercent === 100 && !result.scores.bleed && !result.scores.clipping && result.scores.noise === 'low', JSON.stringify(result.scores));
});

test('quality: silence, too quiet and clipping are caught, each with its own code', () => {
    assert(codes(checkTake(new Float32Array(rate * 8), rate, { notes, beatSeconds })).join() === 'silent', 'silent');
    const quiet = sing(rate, { notes, bpm, level: 0.01 });
    assert(levelChecks(quiet, rate).quiet, `quiet at ${levelChecks(quiet, rate).peakDb.toFixed(1)} dB`);
    const loud = sing(rate, { notes, bpm, level: 1.6 }).map((v) => Math.max(-1, Math.min(1, v)));
    assert(codes(checkTake(loud, rate, { notes, beatSeconds })).includes('clipping'), 'clipping');
});

test('quality: a noisy room is caught in the rests, and a quiet one is not', () => {
    assert(checkTake(sing(rate, { notes, bpm, noise: 0.02 }), rate, { notes, beatSeconds }).scores.noise !== 'low', 'noisy');
    assert(checkTake(sing(rate, { notes, bpm, noise: 0.0005 }), rate, { notes, beatSeconds }).scores.noise === 'low', 'quiet room');
});

test('quality: soft singing is never taken for a noisy room', () => {
    // A busy part with hardly a rest, sung softly: the floor isn't measured on the singing.
    const busy = Array.from({ length: 12 }, (_, i) => ({ beat: i, length: 1, pitch: 60 + (i % 5) }));
    for (const level of [0.05, 0.1, 0.2]) {
        const take = sing(rate, { notes: busy, bpm, level, noise: 0.0003 });
        assert(checkTake(take, rate, { notes: busy, beatSeconds }).scores.noise === 'low', `soft singing at ${level}`);
        assert(levelChecks(take, rate).noise === 'low', `the sound check, no quiet given, at ${level}`);
    }
    // The sound check's first seconds are quiet: a noisy room shows there.
    const room = sing(rate, { notes: [{ beat: 3, length: 9, pitch: 60 }], bpm, noise: 0.02 });
    assert(levelChecks(room, rate, { quiet: [[0.2, 1.8]] }).noise !== 'low', 'a noisy room in the quiet start');
});

test('quality: rests leave room for a held ending and an early breath', () => {
    const found = rests(notes, { beatSeconds, takeSeconds: 8 });
    // The gap from beat 4 to 6 (1.33 s), less 0.25 s after and 0.1 s before.
    assert(found.some(([from, to]) => Math.abs(from - (4 * beatSeconds + 0.25)) < 1e-9 && Math.abs(to - (6 * beatSeconds - 0.1)) < 1e-9), JSON.stringify(found));
});

test('quality: the other parts heard during the singer’s rests are bleed', () => {
    const take = sing(rate, { notes, bpm });
    const other = sing(rate, { notes: [{ beat: 4, length: 2, pitch: 67 }], bpm, level: 0.2 });
    const leaked = take.map((v, i) => v + (other[i] ?? 0));
    assert(bleedCheck(leaked, rate, { notes, beatSeconds }), 'bleed found');
    assert(!bleedCheck(take, rate, { notes, beatSeconds }), 'no bleed in a clean take');
    assert(codes(checkTake(leaked, rate, { notes, beatSeconds })).includes('bleed'), 'in the report');
});

test('quality: a flat phrase is named, with its tendency', () => {
    const take = sing(rate, { notes, bpm, cents: (note) => (note.beat >= 6 ? -60 : 0) });
    const result = checkTake(take, rate, { notes, beatSeconds, phrases: [[0, 4], [6, 10]] });
    assert(result.weakPhrases.length === 1 && result.weakPhrases[0].index === 1 && result.weakPhrases[0].tendency === 'flat', JSON.stringify(result.weakPhrases));
});

// ---- The bar (Hillel's "beta singers' scores"): calibrated on good and poor synthesized takes ----

const tune = (() => {
    const out = [];
    let beat = 0;
    [60, 62, 64, 65, 67, 65, 64, 62, 64, 65, 67, 69, 67, 65, 64, 60].forEach((pitch, i) => {
        const long = i % 4 === 3;
        out.push({ beat, length: long ? 2 : 1, pitch });
        beat += long ? 3 : 1;
    });
    return out;
})();
const tunePhrases = [[0, 6], [6, 12], [12, 18], [18, 24]];
/** A fault that differs note by note, the same every run: up to `amount` either way. */
const jitter = (amount, seed = 1) => {
    let s = seed;
    const by = new Map();
    return (note) => {
        if (!by.has(note)) {
            s = (s * 16807) % 2147483647;
            by.set(note, ((s / 2147483647) * 2 - 1) * amount);
        }
        return by.get(note);
    };
};
const judged = (faults, options = {}) => checkTake(sing(rate, { notes: tune, bpm, ...faults }), rate, { notes: tune, beatSeconds, phrases: tunePhrases, ...options });

test('the bar: a good singer is ready to send, with or without vibrato and legato', () => {
    for (const faults of [{ cents: jitter(10), lateMs: jitter(35), vibrato: { hz: 5.5, cents: 15 } }, { cents: jitter(10), lateMs: jitter(35), legato: 60 }]) {
        const result = judged(faults);
        assert(result.scores.pitchPercent >= 95 && result.scores.timingPercent >= 95, JSON.stringify(result.scores));
        assert(verdict(result.scores) === 'ready', verdict(result.scores));
        assert(!phrasesToFix(result).length, 'nothing to fix');
    }
});

test('the bar: a careful amateur is close, a shaky one close too, a poor one not ready yet', () => {
    const amateur = judged({ cents: jitter(28), lateMs: jitter(70) });
    assert(verdict(amateur.scores) === 'close', `amateur ${JSON.stringify(amateur.scores)}`);
    assert(phrasesToFix(amateur).length >= 1, 'the phrases to fix are named');
    const shaky = judged({ cents: jitter(40), lateMs: jitter(110) });
    assert(verdict(shaky.scores) !== 'ready', `shaky ${JSON.stringify(shaky.scores)}`);
    const poor = judged({ cents: jitter(70), lateMs: jitter(180) });
    assert(verdict(poor.scores) === 'notReady', `poor ${JSON.stringify(poor.scores)}`);
});

test('the bar: in tune but sloppy timing, or steady but flat, does not pass', () => {
    const sloppy = judged({ cents: jitter(10), lateMs: jitter(160) });
    assert(sloppy.scores.pitchPercent >= 95 && sloppy.scores.timingPercent < DEFAULT_BAR.timingPercent, JSON.stringify(sloppy.scores));
    assert(verdict(sloppy.scores) !== 'ready', 'sloppy');
    assert(phrasesToFix(sloppy).some((p) => ['late', 'early', 'uneven'].includes(p.why)), JSON.stringify(phrasesToFix(sloppy)));
    const flat = judged({ cents: () => -30, lateMs: jitter(30) });
    assert(flat.scores.pitchPercent < DEFAULT_BAR.pitchPercent && verdict(flat.scores) !== 'ready', JSON.stringify(flat.scores));
    assert(phrasesToFix(flat).every((p) => p.why === 'flat'), JSON.stringify(phrasesToFix(flat)));
});

test('the bar: an even offset is forgiven up to 100 ms when measured, any offset when estimated', () => {
    assert(judged({ lateMs: () => 80 }).scores.timingPercent === 100, 'evenly 80 ms late');
    assert(judged({ lateMs: () => 250 }).scores.timingPercent < DEFAULT_BAR.timingPercent, 'evenly 250 ms late, measured');
    assert(judged({ lateMs: () => 250 }, { timing: 'estimated' }).scores.timingPercent === 100, 'evenly 250 ms late, estimated');
    assert(judged({}, { timing: 'none' }).scores.timingPercent === null, 'no timing');
});

test('the bar: the verdict from the numbers, against any bar', () => {
    assert(verdict({ pitchPercent: 90, timingPercent: 80 }) === 'ready', 'on the bar');
    assert(verdict({ pitchPercent: 95, timingPercent: null }) === 'ready', 'no timing to judge');
    assert(verdict({ pitchPercent: 89, timingPercent: 100 }) === 'close', 'a point under');
    assert(verdict({ pitchPercent: 75, timingPercent: 60 }) === 'close', 'the edge of close');
    assert(verdict({ pitchPercent: 74, timingPercent: 100 }) === 'notReady', 'pitch too far');
    assert(verdict({ pitchPercent: 100, timingPercent: 59 }) === 'notReady', 'timing too far');
    assert(verdict({ pitchPercent: null, timingPercent: null }) === 'notReady', 'unheard');
    assert(verdict({ pitchPercent: 85, timingPercent: 70 }, { pitchPercent: 85, timingPercent: 70 }) === 'ready', 'a lower bar');
});
