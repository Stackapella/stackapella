// How a take went (js/scoring.js), on synthesized singing: the checks the plan asks for
// (docs/vocal-coaching-plan.md, "Order of work", M1).
import { trackTake } from '/js/pitchtrack.js';
import { liveNote, scoreTake, stars, sungEnd } from '/js/scoring.js';
import { assert, close, test } from './harness.js';
import { sing } from './fixtures.js';

const rate = 48000;
const bpm = 90;
const beatSeconds = 60 / bpm;
// A little tune with rests, so entries can be timed: low, middle and high notes.
const tune = [
    { beat: 0, length: 2, pitch: 57 }, { beat: 2, length: 1, pitch: 60 }, { beat: 3, length: 1, pitch: 62 },
    { beat: 5, length: 2, pitch: 64 }, { beat: 7, length: 1, pitch: 65 }, { beat: 8, length: 1, pitch: 67 },
    { beat: 10, length: 2, pitch: 69 }, { beat: 12, length: 1, pitch: 71 }, { beat: 13, length: 1, pitch: 72 },
    { beat: 15, length: 3, pitch: 69 },
];

function score(options, scoring = {}) {
    const track = trackTake(sing(rate, { notes: tune, bpm, ...options }), rate);
    return scoreTake(track, tune, { beatSeconds, phrases: [[0, 5], [5, 10], [10, 18]], ...scoring });
}

test('scoring: in a heard take, a note not heard counts as not yet; notes after the take ends don\'t count', () => {
    const sung = sing(rate, { notes: tune.slice(0, 4), bpm });
    const whole = new Float32Array(Math.ceil(19 * beatSeconds * rate));
    whole.set(sung.subarray(0, Math.min(sung.length, whole.length)));
    const silentAfter = scoreTake(trackTake(whole, rate), tune, { beatSeconds });
    assert(silentAfter.status === 'scored' && silentAfter.percent <= 40, `four of ten sung: ${silentAfter.percent}%`);
    const stopped = scoreTake(trackTake(sung, rate), tune, { beatSeconds });
    assert(stopped.status === 'scored' && stopped.percent >= 90, `stopped after four, those four in tune: ${stopped.percent}%`);
});

test('scoring: in tune is lit, all of it, with three stars when the timing was measured', () => {
    const result = score({});
    assert(result.status === 'scored' && result.percent === 100, `${result.percent}%`);
    assert(result.notes.every((n) => n.level === 'lit'), result.notes.map((n) => n.level).join());
    assert(result.word === 'excellent' && result.wentWell.includes('inTune'), result.word);
    assert(stars(result).stars === 3, `stars ${stars(result).stars}`);
    assert(stars({ ...result, timing: 'estimated' }).stars === 2 && stars({ ...result, timing: 'estimated' }).timingNote, 'an estimate gives no timing star');
    assert(stars({ ...result, timing: 'none' }).stars === 3, 'pitch-only exercises');
});

test('scoring: 20 cents flat reads as flat by 20, give or take 2', () => {
    const result = score({ cents: -20 });
    close(result.tendency, -20, 2, 'tendency');
    result.notes.forEach((n) => close(n.cents, -20, 2, `note ${n.index}`));
    assert(result.percent === 100, 'within standard strictness (35 cents)');
    assert(score({ cents: -30 }).notes.every((n) => n.level === 'lit'), '30 flat is lit at standard (35 cents)');
    assert(score({ cents: -30 }, { strictness: 'precise' }).notes.every((n) => n.level === 'close'), '30 flat is close at precise (20 cents)');
    assert(score({ cents: -30 }, { strictness: 'relaxed' }).percent === 100, '30 flat is lit at relaxed (50 cents)');
});

test('scoring: flat only on the high notes says so, with the breath support tip', () => {
    const result = score({ cents: (note) => (note.pitch >= 69 ? -30 : 0) });
    assert(result.registers.high.cents < -20 && Math.abs(result.registers.low.cents) < 5, JSON.stringify(result.registers));
    assert(result.insights[0] === 'flatHigh' && result.tips[0] === 'breathSupport', result.insights.join());
});

test('scoring: vibrato at 5.5 Hz and 40 cents is measured, and still on pitch', () => {
    const result = score({ vibrato: { hz: 5.5, cents: 40 } });
    const held = result.notes.filter((n) => n.vibrato);
    assert(held.length >= 4, `${held.length} notes with vibrato`);
    held.forEach((n) => {
        close(n.vibrato.hz, 5.5, 0.6, `note ${n.index} speed`);
        close(n.vibrato.cents, 40, 7, `note ${n.index} width`);
        assert(n.steady !== false, `note ${n.index}: vibrato isn't wavering`);
    });
    assert(result.percent >= 90, `${result.percent}%`);
    assert(score({}).notes.every((n) => !n.vibrato), 'no vibrato when there is none');
});

test('scoring: a scoop up into an entry is flagged, and not when there is none', () => {
    const result = score({ scoop: { cents: -250, ms: 140 } });
    const scooped = result.notes.filter((n) => n.scoop).length;
    assert(scooped === 4, `${scooped} of the 4 entries' scoops found`);
    assert(result.insights.includes('scoops') && result.tips.includes('onsets'), result.insights.join());
    assert(score({}).notes.every((n) => !n.scoop), 'false scoops');
});

test('scoring: entries 80 ms late are measured', () => {
    const result = score({ lateMs: 80 });
    close(result.entryMs, 80, 15, 'the entries');
    assert(result.insights.includes('late'), result.insights.join());
    close(score({}).entryMs, 0, 15, 'on time');
    assert(stars(result).stars < 3 || result.percent < 90, 'late entries cost the third star');
});

test('scoring: singing the part an octave down on purpose is scored within the octave', () => {
    const result = score({ octave: -1 });
    assert(result.octave === -1, `octave ${result.octave}`);
    assert(result.percent === 100, `${result.percent}%`);
});

test("scoring: silence and noise come back as couldn't hear, with no stars and nothing lost", () => {
    const silent = scoreTake(trackTake(new Float32Array(rate * 13), rate), tune, { beatSeconds });
    assert(silent.status === 'unheard' && silent.percent === null && stars(silent).stars === 0, silent.status);
    const noisy = scoreTake(trackTake(sing(rate, { notes: [], bpm, noise: 0.3 }), rate), tune, { beatSeconds });
    assert(noisy.status === 'unheard', noisy.status);
});

test('scoring: phrases get their own results, and stars change at 70% and 90%', () => {
    const result = score({ cents: (note) => (note.beat >= 10 ? 80 : 0) });
    assert(result.phrases[0].percent === 100 && result.phrases[2].percent === 0, JSON.stringify(result.phrases));
    assert(stars({ status: 'scored', percent: 69, timing: 'measured' }).stars === 1, '69%');
    assert(stars({ status: 'scored', percent: 70, timing: 'measured' }).stars === 2, '70%');
    assert(stars({ status: 'scored', percent: 90, timing: 'measured', entryMs: 20 }).stars === 3, '90%');
    assert(stars({ status: 'scored', percent: 90, timing: 'measured', entryMs: 120 }).stars === 2, '90% but late');
});

test('scoring: a take recorded from later in the song lines up by its start', () => {
    const track = trackTake(sing(rate, { notes: tune.map((n) => ({ ...n, beat: n.beat - 5 })).filter((n) => n.beat >= 0), bpm }), rate);
    const result = scoreTake(track, tune.filter((n) => n.beat >= 5), { beatSeconds, startSeconds: 5 * beatSeconds });
    assert(result.percent === 100, `${result.percent}%`);
});

test('scoring: live, a note lights after it is held a third of a second, and says which way otherwise', () => {
    const toHz = (midi, cents = 0) => 440 * 2 ** ((midi - 69 + cents / 100) / 12);
    const held = Array.from({ length: 40 }, (_, i) => ({ time: i / 100, hz: toHz(64, 5) }));
    assert(liveNote(held, 64).state === 'lit', liveNote(held, 64).state);
    assert(liveNote(held.slice(0, 10), 64).state === 'finding', 'not held long enough yet');
    const low = Array.from({ length: 150 }, (_, i) => ({ time: i / 100, hz: toHz(64, -150) }));
    assert(liveNote(low, 64).hint === 'higher', `hint ${liveNote(low, 64).hint}`);
    assert(liveNote(low.slice(0, 20), 64).hint === null, 'no hint in the first second');
    const octaveDown = Array.from({ length: 40 }, (_, i) => ({ time: i / 100, hz: toHz(52) }));
    assert(liveNote(octaveDown, 64).state === 'lit', 'an octave down counts');
});

// ---- From the review: what real singing does that a tidy synth doesn't ----

const short = [0, 1, 2, 3, 4, 5, 6, 7].map((i) => ({ beat: i * 1.25, length: 1, pitch: 60 + (i % 5) })); // 125 ms rests at 120 BPM

test('scoring: short rests are entries, timed from the voice, not the note before', () => {
    const track = trackTake(sing(rate, { notes: short, bpm: 120 }), rate);
    const result = scoreTake(track, short, { beatSeconds: 0.5 });
    close(result.entryMs, 0, 20, 'on-time entries after short rests');
    assert(!result.insights.includes('early'), result.insights.join());
});

test('scoring: noise and breath read as not heard, never as wrong octaves', () => {
    const track = trackTake(sing(rate, { notes: tune, bpm, noise: 0.25, level: 0.2 }), rate);
    const result = scoreTake(track, tune, { beatSeconds });
    const wild = result.notes.filter((n) => n.cents !== null && Math.abs(n.cents) > 300).length;
    assert(wild === 0, `${wild} notes read far off`);
    assert(result.status === 'unheard' || result.percent >= 70, `${result.status} ${result.percent}%`);
});

test('scoring: dropping only the high notes an octave is scored within the octave, note by note', () => {
    const result = score({ octave: (note) => (note.pitch >= 69 ? -1 : 0) });
    assert(result.percent === 100, `${result.percent}%`);
    assert(result.octave === 0 && result.notes.filter((n) => n.octave === -1).length === 4, result.notes.map((n) => n.octave).join());
    assert(!result.insights.length, result.insights.join());
});

test('scoring: one note far off does not set the advice', () => {
    const result = score({ cents: (note) => (note.beat === 13 ? 300 : 0) });
    assert(Math.abs(result.tendency) < 5 && !result.insights.includes('sharpHigh'), `${result.tendency} ${result.insights}`);
});

test('scoring: gliding between joined notes is singing, not a scoop', () => {
    const joined = [0, 1, 2, 3, 4, 5].map((i) => ({ beat: i, length: 1, pitch: [60, 64, 62, 65, 64, 60][i] }));
    const track = trackTake(sing(rate, { notes: joined, bpm: 90, legato: 80 }), rate);
    const result = scoreTake(track, joined, { beatSeconds });
    assert(result.scoops === 0 && !result.insights.includes('scoops'), `${result.scoops} scoops`);
});

test('scoring: vibrato on short notes is not called wavering, and a take all close is not 0%', () => {
    const eighths = Array.from({ length: 12 }, (_, i) => ({ beat: i * 0.5, length: 0.5, pitch: 60 + (i % 4) }));
    const track = trackTake(sing(rate, { notes: eighths, bpm: 140, vibrato: { hz: 5.5, cents: 35 } }), rate);
    const result = scoreTake(track, eighths, { beatSeconds: 60 / 140 });
    assert(result.wavering === 0 && !result.insights.includes('wavering'), `${result.wavering} wavering`);
    const allClose = score({ cents: 50 });
    assert(allClose.notes.every((n) => n.level === 'close') && allClose.percent === 50, `${allClose.percent}%`);
});

test('scoring: live, a dropped reading or two does not break a held note', () => {
    const toHz = (midi) => 440 * 2 ** ((midi - 69) / 12);
    const readings = Array.from({ length: 40 }, (_, i) => ({ time: i / 100, hz: i % 10 === 5 ? 0 : toHz(64) }));
    assert(liveNote(readings, 64).state === 'lit', liveNote(readings, 64).state);
});

test('scoring: a change of note is timed where the voice crosses to the new note', () => {
    const steps = [60, 62, 64, 65, 67].map((pitch, i) => ({ beat: i, length: 1, pitch }));
    for (const lateMs of [0, 120]) {
        const take = sing(48000, { notes: steps, bpm: 90, lateMs });
        const result = scoreTake(trackTake(take, 48000), steps, { beatSeconds: 60 / 90 });
        const changes = result.notes.map((n) => n.changeMs).filter((ms) => ms !== null);
        assert(changes.length === 4 && changes.every((ms) => Math.abs(ms - lateMs) <= 30), `${lateMs} ms late: ${changes}`);
    }
});

test('scoring: the last half beat before a new line is a breath, less any rest there, and never more than half the note', () => {
    const line = [{ beat: 0, length: 2, pitch: 60 }, { beat: 2, length: 1, pitch: 62, br: true }, { beat: 3.25, length: 0.5, pitch: 64, br: true }, { beat: 3.75, length: 1, pitch: 65, br: true }];
    close(sungEnd(line, 0), 1.5, 1e-9, 'held into a new line: half a beat off');
    close(sungEnd(line, 1), 2.75, 1e-9, 'a quarter beat rest already: a quarter more');
    close(sungEnd(line, 2), 3.5, 1e-9, 'a half-beat note: half of it at most');
    close(sungEnd(line, 3), 4.75, 1e-9, 'the last note: its end');
});

test('scoring: a singer who breathes before each new line is lit throughout, and the line after is timed as an entry', () => {
    const lines = [
        { beat: 0, length: 1, pitch: 60, br: true }, { beat: 1, length: 1, pitch: 62 }, { beat: 2, length: 1, pitch: 64 },
        { beat: 3, length: 1, pitch: 65, br: true }, { beat: 4, length: 1, pitch: 67 }, { beat: 5, length: 1, pitch: 64 },
    ];
    // Sung as people sing it: the note before the new line let go half a beat early.
    const sung = lines.map((n, i) => (lines[i + 1]?.br ? { ...n, length: n.length - 0.5 } : n));
    const result = scoreTake(trackTake(sing(rate, { notes: sung, bpm, legato: 1 }), rate), lines, { beatSeconds });
    assert(result.notes.every((n) => n.level === 'lit'), result.notes.map((n) => n.level).join());
    assert(result.notes[3].entryMs !== null, 'the new line is an entry');
    close(result.notes[3].entryMs, 0, 40, 'timed from the voice after the breath');
});
