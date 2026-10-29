// A lesson's results (js/results.js): the level words, the take as dots against the target band and
// the longest hold, the line that says what was done, the keyboard around your note, the unit's
// path, the end screen's line, days in a row, and With the guide's mix.
import { WORDS, chartDots, daysInARow, easyRange, endLine, holdPercent, keyboard, lessonMinutes, levelWord, longestHold, noteLine, resultLine, unitPath, withGuide } from '/js/results.js';
import { assert, test } from './harness.js';

const hzOf = (midi) => 440 * 2 ** ((midi - 69) / 12);
/** A track at 100 readings a second: `pitchAt(t)` in MIDI, or null for no pitch. */
const track = (seconds, pitchAt) => {
    const rate = 100;
    const hz = new Float32Array(Math.round(seconds * rate));
    for (let i = 0; i < hz.length; i++) {
        const midi = pitchAt(i / rate);
        hz[i] = midi === null ? 0 : hzOf(midi);
    }
    return { rate, hz };
};

test('results: the level word follows the stars, and one star says Close or Keep going', () => {
    assert(levelWord(3, 95) === WORDS[3] && levelWord(2, 75) === WORDS[2], 'Brilliant, Great');
    assert(levelWord(1, 60) === WORDS.close, 'one star, more than half: Close');
    assert(levelWord(1, 30) === WORDS.keepGoing && levelWord(0, null) === WORDS.keepGoing, 'less: Keep going');
});

test('results: a scoop in, then a steady hold: dots out then in, and the hold timed', () => {
    const target = [{ start: 0, end: 8, pitch: 55 }];
    // A scoop from 3 semitones under over 0.6 s, then G3 with a little vibrato for 6.2 s, then quiet.
    const take = track(8, (t) => (t < 0.6 ? 52 + 5 * t : t < 6.8 ? 55 + 0.2 * Math.sin(t * 30) : null));
    const dots = chartDots(take, target, { cents: 50 });
    assert(!dots[0].in && dots.at(-1).in, 'the scoop is out, the hold is in');
    assert(dots.every((d) => d.t < 6.9), 'no dots in the quiet');
    const hold = longestHold(dots);
    assert(hold >= 6 && hold <= 6.4, `held ${hold} s`);
    const line = resultLine({ target, lit: 1, hold });
    assert(line.key === 'results.line.held' && line.values.note === 'G3', 'one note: how long it was held');
});

test('results: a man on a treble line is drawn in the line\'s octave, and still in tune', () => {
    const dots = chartDots(track(2, () => 43), [{ start: 0, end: 2, pitch: 55 }], { cents: 50 });
    assert(dots.every((d) => d.in && Math.abs(d.midi - 55) < 0.01), 'an octave under counts, drawn at the target');
});

test('results: a short gap does not end the hold; a wrong note does', () => {
    const gap = chartDots(track(4, (t) => (t > 1.95 && t < 2.1 ? null : 60)), [{ start: 0, end: 4, pitch: 60 }], { cents: 50 });
    assert(longestHold(gap) >= 3.8, `a breath-short gap: ${longestHold(gap)}`);
    const wrong = chartDots(track(4, (t) => (t > 1.9 && t < 2.3 ? 63 : 60)), [{ start: 0, end: 4, pitch: 60 }], { cents: 50 });
    assert(longestHold(wrong) < 2.2, `broken by a wrong note: ${longestHold(wrong)}`);
});

test('results: several notes say how many lit', () => {
    const line = resultLine({ target: [{ pitch: 60 }, { pitch: 62 }, { pitch: 64 }], lit: 2, hold: 1 });
    assert(line.key === 'results.line.lit' && line.values.lit === 2 && line.values.count === 3, 'lit of count');
});

test('results: the keyboard spans about two octaves round the note, with the easy range marked', () => {
    const range = easyRange(55, null);
    assert(range.guess && range.low === 52 && range.high === 60, 'a guess round G3 without a range');
    const checked = easyRange(55, { low: 45, high: 64 });
    assert(!checked.guess && checked.low === 45, 'the range check\'s when there is one');
    const { whites, blacks } = keyboard(55, range);
    assert(whites[0].name === 'C3' && whites.length >= 14, `from ${whites[0].name}, ${whites.length} white keys`);
    assert(whites.find((k) => k.isNote)?.name === 'G3', 'the note is a key');
    assert(whites.filter((k) => k.inRange).map((k) => k.name).join(' ') === 'E3 F3 G3 A3 B3 C4', 'the easy range');
    assert(blacks.every((k) => k.after >= 0 && k.after < whites.length - 1), 'each black key sits between two whites');
    const sharp = keyboard(54, easyRange(54));
    assert(sharp.blacks.some((k) => k.isNote), 'a note on a black key is marked there');
});

test('results: what the note means is said honestly', () => {
    const line = noteLine(55);
    assert(line.key === 'results.note.highMiddle' && line.values.note === 'G3', 'notes around G3, not a voice type');
    assert(noteLine(55, 'tenor').key === 'results.note.voice' && noteLine(55, 'tenor').voiceKey === 'results.voice.tenor', 'a voice type only from a range');
});

test('results: the unit path marks done, now, next and later', () => {
    const unit = { lessons: [{ id: 'a' }, { id: 'b' }, { id: 'c' }, { id: 'd' }, { id: 'e' }] };
    const path = unitPath(unit, { lessons: { a: { stars: 3 }, b: { stars: 1 }, c: { stars: 2 } } }, 'c');
    assert(path.map((p) => p.state).join(' ') === 'done done now next later', path.map((p) => p.state).join(' '));
    assert(lessonMinutes({ steps: [{ kind: 'learn' }, { kind: 'try' }, { kind: 'play' }] }) === 3, 'minutes from the steps');
});

test('results: the end line', () => {
    assert(endLine({ stars: 3, before: 0, scored: true }) === 'results.end.perfectFirst', 'perfect first try');
    assert(endLine({ stars: 3, before: 1, scored: true }) === 'results.end.upToThree', 'up to three');
    assert(endLine({ stars: 2, before: 1, scored: true }) === 'results.end.up', 'a step up');
    assert(endLine({ stars: 1, before: 0, scored: true }) === 'results.end.one', 'one star');
    assert(endLine({ stars: 3, before: 0, scored: false }) === 'results.end.done', 'nothing scored');
});

test('results: days in a row with singing', () => {
    const at = new Date(2026, 9, 27, 12).getTime();
    const progress = { practice: { '2026-10-27': 60, '2026-10-26': 300, '2026-10-25': 30, '2026-10-23': 100 }, xp: { days: {} } };
    assert(daysInARow(progress, at) === 3, `three days: ${daysInARow(progress, at)}`);
    const notYet = { practice: { '2026-10-26': 60, '2026-10-25': 60 }, xp: { days: {} } };
    assert(daysInARow(notYet, at) === 2, 'today not yet sung: the run up to yesterday');
    assert(daysInARow({ practice: {}, xp: { days: {} } }, at) === 0, 'none');
});

test('results: hold scoring rewards holding the note on pitch in one go, and steadily', () => {
    const target = [{ start: 0, end: 4, pitch: 57 }];
    const dots = (from, to, inTune = () => true) => Array.from({ length: Math.round((to - from) * 10) }, (_, i) => ({ t: from + i / 10 + 0.05, midi: 57, in: inTune(i) }));
    assert(holdPercent(dots(0, 4), target) === 100, 'held all four seconds, steady');
    const half = holdPercent(dots(0, 2), target);
    assert(half > 60 && half < 70, `held two of four seconds: ${half}`);
    const broken = holdPercent([...dots(0, 1.9), ...dots(2.6, 4)], target);
    assert(broken < 80, `a break in the middle: ${broken}`);
    const wobbly = holdPercent(dots(0, 4, (i) => i % 3 !== 0), target);
    assert(wobbly < 100, `off a third of the time: ${wobbly}`);
    assert(holdPercent([], target) === null, 'nothing heard');
});


test('results: With the guide mixes the guide under your take where you sang, keeps its length, and never clips', () => {
    const sampleRate = 8000;
    const take = { samples: new Float32Array(sampleRate * 3), sampleRate };
    const mixed = withGuide(take, [{ start: 1, end: 2, pitch: 60 }]);
    assert(mixed.samples.length === take.samples.length && mixed.sampleRate === sampleRate, 'the take\'s length and rate');
    const energy = (from, to) => mixed.samples.slice(from * sampleRate, to * sampleRate).reduce((sum, v) => sum + v * v, 0);
    assert(energy(1.1, 1.9) > 1 && energy(0, 0.9) < 1e-6, 'the guide sounds only under the note you sang');
    assert(take.samples.every((v) => v === 0), 'your take itself is untouched');
    const loud = { samples: new Float32Array(sampleRate * 3).fill(0.9), sampleRate };
    const peak = withGuide(loud, [{ start: 0, end: 3, pitch: 60 }]).samples.reduce((max, v) => Math.max(max, Math.abs(v)), 0);
    assert(peak <= 0.951, `never clips: ${peak}`);
});
