// The styles courses' measures (js/style-scores.js): key drift and intervals alone, on per-note
// results (a line in tune, a steady line sung low, a slow slide flat, one wrong note, quick ornaments,
// a take that stopped early), then the same through the real pitch tracker on a sung line that drifts.
// The flip: synthetic tracks with a jump, a dropout, a glide and an octave slip, then sung yodels.
// The moves (scoop, fall-off, slide, cry): each told from its look-alikes, then the styled scorer.
import { trackTake } from '/js/pitchtrack.js';
import { bendAt, renderPart } from '/js/synth.js';
import { fadeOut, layout, noteToGive } from '/js/components/exercise.js';
import { scoreTake } from '/js/scoring.js';
import { cryIn, fallOff, settleFlip, styleBend, flipPercent, flipPoint, flips, keyDrift, movesHeard, scoopInto, slideInto, styledMoves } from '/js/style-scores.js';
import { PLAY_SCORERS } from '/js/lesson-scores.js';
import { assert, test } from './harness.js';
import { sing } from './fixtures.js';

/** scoreTake-like per-note results with these centres (null: not heard). */
const results = (cents) => cents.map((c, index) => ({ index, level: c === null ? 'unheard' : Math.abs(c) <= 35 ? 'lit' : 'notYet', cents: c }));
/** A line of one-second notes with these centres, through keyDrift. */
const drift = (cents) => keyDrift(results(cents), cents.map((_, i) => ({ beat: i, length: 1 })), { beatSeconds: 1 });

test('key drift: a line in tune has no drift and full stars', () => {
    const r = drift([2, -3, 0, 4, -2, 1, 0, -1]);
    assert(r.status === 'scored', r.status);
    assert(Math.abs(r.drift) <= 5, `drift ${r.drift}`);
    assert(r.percent === 100, `percent ${r.percent}`);
});

test('key drift: a steady line 40 cents low is perfect intervals, a 40-cent key, and no drift', () => {
    const r = drift([-40, -42, -38, -41, -40, -39, -40, -42]);
    assert(r.key === -40, `key ${r.key}`);
    assert(Math.abs(r.drift) <= 3, `drift ${r.drift}`);
    assert(r.intervalsPercent === 100, `intervals ${r.intervalsPercent}`);
    assert(r.percent === 100, `percent ${r.percent}`);
});

test('key drift: a slow slide flat keeps the intervals but costs the key', () => {
    const r = drift([0, -8, -16, -24, -32, -40, -48, -56, -64, -72]);
    assert(r.drift <= -65 && r.drift >= -75, `drift ${r.drift}`);
    assert(r.intervalsPercent === 100, `intervals ${r.intervalsPercent}`);
    assert(r.driftPercent > 0 && r.driftPercent < 70, `drift percent ${r.driftPercent}`);
    const worse = drift([0, -15, -30, -45, -60, -75, -90, -105, -120, -135]);
    assert(worse.driftPercent === 0, `a semitone and more: ${worse.driftPercent}`);
});

test('key drift: one wrong note is one wrong interval, not a moved key', () => {
    const r = drift([0, 0, 0, 0, 100, 0, 0, 0]);
    assert(r.notes[4].level === 'notYet', `the wrong note ${r.notes[4].level}`);
    assert(r.notes.filter((n) => n.level === 'lit').length === 7, 'the rest lit');
    assert(r.drift === 0, `drift ${r.drift}`);
});

test('key drift: quick ornament notes sung high don\'t move the key', () => {
    const cents = [0, 80, 0, 0, 80, 0, 0, 80, 0, 0, 0];
    const notes = cents.map((_, i) => ({ beat: i, length: cents[i] ? 0.2 : 1 }));
    const r = keyDrift(results(cents), notes, { beatSeconds: 1 });
    assert(r.drift === 0 && r.key === 0, `key ${r.key}, drift ${r.drift}`);
});

test('key drift: a note missed mid-line is not yet, the notes after the take stopped don\'t count', () => {
    const r = drift([0, 0, null, 0, 0, 0, 0, null, null]);
    assert(r.notes[2].level === 'unheard', 'missed');
    assert(r.intervalsPercent === Math.round((6 / 7) * 100), `intervals ${r.intervalsPercent}`);
    assert(drift([0, 0, 0, null, null]).status === 'unheard', 'too little heard to judge');
});

test('key drift: through the pitch tracker, a sung line sliding 70 cents flat is heard as drift', () => {
    const rate = 48000;
    const bpm = 80;
    const tune = [62, 65, 67, 69, 67, 65, 64, 62, 60, 62].map((pitch, i) => ({ beat: i, length: 1, pitch }));
    const track = trackTake(sing(rate, { notes: tune, bpm, cents: (note) => -8 * note.beat }), rate);
    const r = keyDrift(scoreTake(track, tune, { beatSeconds: 60 / bpm }).notes, tune, { beatSeconds: 60 / bpm });
    assert(r.status === 'scored', r.status);
    assert(r.drift <= -55 && r.drift >= -85, `drift ${r.drift}`);
    assert(r.intervalsPercent >= 90, `intervals ${r.intervalsPercent}`);
    const steady = keyDrift(scoreTake(trackTake(sing(rate, { notes: tune, bpm }), rate), tune, { beatSeconds: 60 / bpm }).notes, tune, { beatSeconds: 60 / bpm });
    assert(Math.abs(steady.drift) <= 10 && steady.percent >= 95, `steady: drift ${steady.drift}, percent ${steady.percent}`);
});

test('key drift: as a play step\'s scorer, a steady line scores well and a slide flat costs the key', () => {
    const rate = 48000;
    const bpm = 80;
    const beatSeconds = 60 / bpm;
    const notes = [62, 65, 67, 69, 67, 65, 64, 62, 60, 62].map((pitch, i) => ({ beat: i, length: 1, pitch }));
    const run = (cents) => PLAY_SCORERS.keyDrift({ track: trackTake(sing(rate, { notes, bpm, cents }), rate), notes, beatSeconds, strictness: 'standard' });
    const steady = run(0);
    const sliding = run((note) => -12 * note.beat);
    assert(steady.percent >= 95, `steady ${steady.percent}`);
    assert(sliding.percent < steady.percent - 20 && sliding.percent >= 50, `sliding ${sliding.percent}`);
});

/** A pitch track (100 readings a second) from [seconds, midi or 0 for silence] stretches; glides are [s, from, to]. */
function track(parts) {
    const hz = [];
    for (const [seconds, from, to = from] of parts) {
        const n = Math.round(seconds * 100);
        for (let i = 0; i < n; i++) {
            const midi = from + ((to - from) * i) / Math.max(1, n - 1);
            hz.push(from === 0 ? 0 : 440 * 2 ** ((midi - 69) / 12));
        }
    }
    return { rate: 100, hz: Float32Array.from(hz) };
}
const HOOK = [{ beat: 0, length: 1, pitch: 57 }, { beat: 1, length: 1, pitch: 66 }, { beat: 2, length: 1, pitch: 57 }];

test('flip: a jump is clean, a short dropout at the joint too, a glide is slid', () => {
    const clean = flips(track([[1, 57], [1, 66], [1, 57]]), HOOK, { beatSeconds: 1 });
    assert(clean.length === 2 && clean.every((f) => f.kind === 'clean'), JSON.stringify(clean));
    const dropout = flips(track([[0.97, 57], [0.05, 0], [0.98, 66], [1, 57]]), HOOK, { beatSeconds: 1 });
    const slipBetween = flips(track([[0.97, 57], [0.04, 54], [0.99, 66], [1, 57]]), HOOK, { beatSeconds: 1 }); // half pitch as the folds settle
    assert(slipBetween[0].kind === 'clean', `an octave slip at the joint: ${JSON.stringify(slipBetween[0])}`);
    assert(dropout[0].kind === 'clean', `with a dropout: ${JSON.stringify(dropout[0])}`);
    const slid = flips(track([[0.85, 57], [0.3, 57, 66], [0.85, 66], [1, 57]]), HOOK, { beatSeconds: 1 });
    assert(slid[0].kind === 'slid' && slid[1].kind === 'clean', JSON.stringify(slid));
    assert(slid[0].direction === 'up' && slid[1].direction === 'down', 'up and down told apart');
    assert(flipPercent(slid) === 50, `percent ${flipPercent(slid)}`);
});

test('flip: small steps and notes with a breath between aren\'t flips; an octave slip still lands', () => {
    const steps = [{ beat: 0, length: 1, pitch: 57 }, { beat: 1, length: 1, pitch: 59 }, { beat: 2.5, length: 1, pitch: 66 }];
    assert(flips(track([[1, 57], [1, 59], [0.5, 0], [1, 66]]), steps, { beatSeconds: 1 }).length === 0, 'no leaps to judge');
    const slip = flips(track([[1, 57], [1, 78], [1, 57]]), HOOK, { beatSeconds: 1 }); // the top read an octave up
    assert(slip[0].kind === 'clean', `an octave slip: ${JSON.stringify(slip[0])}`);
    const octave = [{ beat: 0, length: 1, pitch: 57 }, { beat: 1, length: 1, pitch: 69 }];
    assert(flips(track([[1, 57], [1, 69]]), octave, { beatSeconds: 1 })[0].kind === 'clean', 'an octave leap, unfolded');
    assert(flips(track([[1, 57], [1, 57]]), octave, { beatSeconds: 1 })[0].kind === 'unheard', 'never reached the top');
});

test('flip: through the pitch tracker, joined notes sung with a quick change are clean, a long glide is slid', () => {
    const rate = 48000;
    const bpm = 60;
    const hook = HOOK.map((n) => ({ ...n }));
    const run = (legato) => flips(trackTake(sing(rate, { notes: hook, bpm, legato }), rate), hook, { beatSeconds: 1 });
    const quick = run(20);
    const glide = run(350);
    assert(quick.every((f) => f.kind === 'clean'), `quick: ${JSON.stringify(quick)}`);
    assert(glide.every((f) => f.kind === 'slid'), `glide: ${JSON.stringify(glide)}`);
    const scorer = PLAY_SCORERS.flip({ track: trackTake(sing(rate, { notes: hook, bpm, legato: 20 }), rate), notes: hook, beatSeconds: 1, strictness: 'standard' });
    assert(scorer.percent >= 90, `the scorer ${scorer.percent}`);
});

test('flip point: a siren that jumps up and down is found from the way down; one that glides has none; a small glitch isn\'t a flip', () => {
    const home = 57;
    // Up from home to an octave, jumping from 5 to 9 above home; then down, jumping from 8 to 4.
    const siren = track([[0.4, home], [1, home, home + 5], [1, home + 9, home + 12], [1, home + 12, home + 8], [1, home + 4, home]]);
    const found = flipPoint(siren, { home });
    assert(found.jumps.filter((j) => j.direction === 'up').length === 1 && found.jumps.filter((j) => j.direction === 'down').length === 1, JSON.stringify(found.jumps));
    assert(found.flip === 6 && found.reach >= 11, `flip ${found.flip}, reach ${found.reach}`);
    assert(flipPoint(track([[0.4, home], [1, home, home + 6], [1, home + 8, home + 12], [1, home + 12, home]]), { home }).jumps.length === 0, 'a two-semitone hop is too small to be a flip');
    const glide = flipPoint(track([[2, home, home + 12], [2, home + 12, home]]), { home });
    assert(glide.flip === null && glide.jumps.length === 0, JSON.stringify(glide));
});

test('flip point: a dropout across the jump still counts, an octave slip doesn\'t, onsets are left out', () => {
    const home = 50;
    const withGap = flipPoint(track([[1, home, home + 5], [0.04, 0], [1, home + 9, home + 12]]), { home });
    assert(withGap.flip === 7 || withGap.flip === 8, `across a dropout: ${withGap.flip}`);
    const slip = flipPoint(track([[1, home, home + 5], [0.3, home + 17], [1, home + 5, home + 10]]), { home });
    assert(slip.jumps.length === 0, `an octave slip: ${JSON.stringify(slip.jumps)}`);
    const onset = flipPoint(track([[0.05, home + 7], [1, home, home + 6]]), { home }); // a scooped start
    assert(onset.jumps.length === 0, `an onset: ${JSON.stringify(onset.jumps)}`);
});

test('settle flip: one siren\'s flip waits for a second to agree; flips outside 3 to 15 above, or near the top of what was reached, aren\'t believed', () => {
    const down = (middle, reach = 14) => ({ jumps: [{ direction: 'down', middle }], reach });
    const first = settleFlip([], down(7));
    assert(first.flip === null && first.seen.length === 1, JSON.stringify(first));
    const second = settleFlip(first.seen, down(7.5));
    assert(second.flip === 7 || second.flip === 8, JSON.stringify(second));
    assert(settleFlip([7], down(12)).flip === null, 'two that disagree confirm nothing');
    assert(settleFlip([], down(1)).seen.length === 0 && settleFlip([], down(16, 20)).seen.length === 0, 'outside 3 to 15: not believed');
    assert(settleFlip([], down(11, 12)).seen.length === 0, 'too near the top of the siren: the hook\'s top wasn\'t sung');
    const twoInOne = settleFlip([], { jumps: [{ direction: 'down', middle: 13 }, { direction: 'down', middle: 13.5 }], reach: 17 });
    assert(twoInOne.flip === 13 || twoInOne.flip === 14, `two downward flips in one siren agree: ${JSON.stringify(twoInOne)}`);
});

const NOTE = { beat: 1, length: 1, pitch: 60 }; // one second at 60, after a second's silence

test('moves: a scoop from a semitone or more below, rising smoothly; a flat start or a small dip isn\'t one', () => {
    const scoop = track([[1, 0], [0.15, 58, 60], [0.85, 60]]);
    assert(scoopInto(scoop, NOTE, { beatSeconds: 1 }), 'a scoop of two semitones over 150 ms');
    assert(!scoopInto(track([[1, 0], [0.04, 59.5], [0.96, 60]]), NOTE, { beatSeconds: 1 }), 'a 50-cent dip is a flat start');
    assert(!scoopInto(track([[1, 0], [0.2, 58.5], [0.1, 58.5, 60], [0.7, 60]]), NOTE, { beatSeconds: 1 }), 'held low 200 ms, then up: a flat entry');
    assert(!scoopInto(track([[1, 0], [0.6, 57, 60], [0.4, 60]]), NOTE, { beatSeconds: 1 }), 'too slow for a quick scoop');
    assert(scoopInto(track([[1, 0], [0.45, 57, 60], [0.55, 60]]), NOTE, { beatSeconds: 1, window: 0.5 }), 'a lazy scoop, in its lesson\'s window');
    assert(!scoopInto(track([[1, 0], [1, 60]]), NOTE, { beatSeconds: 1 }), 'a clean start');
});

test('moves: a fall-off drops 200 cents or more at the end, even fading out; a steady end doesn\'t', () => {
    assert(fallOff(track([[1, 0], [0.7, 60], [0.3, 60, 56.5]]), NOTE, { beatSeconds: 1 }), 'a fall of three and a half semitones');
    assert(fallOff(track([[1, 0], [0.75, 60], [0.15, 60, 57.8], [0.3, 0]]), NOTE, { beatSeconds: 1 }), 'a soft tail fading out after 220 cents');
    assert(!fallOff(track([[1, 0], [1, 60], [0.3, 0]]), NOTE, { beatSeconds: 1 }), 'a steady end');
    assert(!fallOff(track([[1, 0], [0.8, 60], [0.2, 60, 59]]), NOTE, { beatSeconds: 1 }), 'a sag of a semitone');
});

test('moves: a slide glides 150 to 700 ms between joined notes; a jump or a breath isn\'t one', () => {
    const a = { beat: 0, length: 1, pitch: 60 };
    const b = { beat: 1, length: 1, pitch: 64 };
    assert(slideInto(track([[0.85, 60], [0.3, 60, 64], [0.85, 64]]), a, b, { beatSeconds: 1 }), 'a 300 ms slide');
    assert(!slideInto(track([[1, 60], [1, 64]]), a, b, { beatSeconds: 1 }), 'a jump');
    assert(!slideInto(track([[1, 60], [1, 61]]), a, { ...b, pitch: 61 }, { beatSeconds: 1 }), 'a semitone is too small to slide');
});

test('moves: a cry flicks up for 60 to 250 ms and comes back; a blip or a held high note isn\'t one', () => {
    assert(cryIn(track([[1, 0], [0.3, 60], [0.12, 62.5], [0.58, 60]]), NOTE, { beatSeconds: 1 }), 'a 120 ms flick of 250 cents');
    assert(!cryIn(track([[1, 0], [0.3, 60], [0.03, 62.5], [0.67, 60]]), NOTE, { beatSeconds: 1 }), 'a 30 ms blip');
    assert(!cryIn(track([[1, 0], [0.3, 60], [0.7, 62.5]]), NOTE, { beatSeconds: 1 }), 'never came back');
});

test('moves: the line\'s moves heard and matched; your own way counts one move, and too many says less is more', () => {
    const line = [{ beat: 1, length: 1, pitch: 60 }, { beat: 2, length: 1, pitch: 62 }, { beat: 3, length: 1, pitch: 64 }, { beat: 4, length: 1, pitch: 60 }];
    const sung = track([[1, 0], [0.15, 58, 60], [0.85, 60], [1, 62], [1, 64], [0.7, 60], [0.3, 60, 56.5]]);
    const heard = movesHeard(sung, line, { beatSeconds: 1 });
    assert(heard.some((h) => h.index === 0 && h.move === 'scoop') && heard.some((h) => h.index === 3 && h.move === 'fall'), JSON.stringify(heard));
    const asked = styledMoves(sung, line, ['scoop', null, null, 'fall'], { beatSeconds: 1 });
    assert(asked.asked === 2 && asked.matched === 2 && !asked.tooMany, JSON.stringify(asked));
    const missed = styledMoves(sung, line, ['scoop', 'cry', null, 'fall'], { beatSeconds: 1 });
    assert(missed.matched === 2 && missed.asked === 3, 'the cry asked for wasn\'t there');
    const yours = styledMoves(sung, line, [null, null, null, null], { beatSeconds: 1 });
    assert(yours.asked === 0 && yours.matched === 1, 'your way: any move counts once');
    const busy = track([[1, 0], [0.15, 58, 60], [0.85, 60], [0.15, 59.2, 62], [0.85, 62], [0.15, 61.2, 64], [0.85, 64], [0.7, 60], [0.3, 60, 56.5]]); // each scoop from below the note before
    assert(styledMoves(busy, line, [null, null, null, null], { beatSeconds: 1 }).tooMany, 'a move on every note: less is more');
});

test('moves: the styled scorer, through the tracker, stars a sung scoop and not a plain start', () => {
    const rate = 48000;
    const bpm = 60;
    const notes = [{ beat: 1, length: 2, pitch: 60 }, { beat: 3, length: 2, pitch: 64 }];
    const exercise = { notes: [{ style: 'scoop' }, {}] };
    const run = (scoop) => PLAY_SCORERS.styled({ track: trackTake(sing(rate, { notes, bpm, scoop }), rate), notes, beatSeconds: 1, strictness: 'standard', exercise });
    const scooped = run({ cents: -250, ms: 180 });
    const plain = run(null);
    assert(scooped.percent >= 90, `scooped ${scooped.percent}`);
    assert(plain.percent <= 60, `plain ${plain.percent}`);
});

/** A guide note sung with its bend, as a 100-readings-a-second track: silence, then the note (`at` its start in seconds). */
function bentTrack(note, bend, { at = 1, beatSeconds = 1, after = null } = {}) {
    const n = Math.round(note.length * beatSeconds * 100);
    const hz = new Array(Math.round(at * 100)).fill(0);
    const centsAt = (t) => (bend ? bendAt(bend, t) : 0); // the synth's own easing, as the guide sings it
    for (let i = 0; i < n; i++) hz.push(440 * 2 ** ((note.pitch - 69 + centsAt(i / 100) / 100) / 12));
    if (after) for (let i = 0; i < Math.round(after.length * beatSeconds * 100); i++) hz.push(440 * 2 ** ((after.pitch - 69) / 12));
    hz.push(...new Array(30).fill(0));
    return { rate: 100, hz: Float32Array.from(hz) };
}

test('moves: the guide\'s own scoop, fall-off, slide and cry are each heard by the measure that stars them', () => {
    const exercise = { tempo: 60 };
    const note = (style) => ({ beat: 1, length: 1, pitch: 60, style });
    assert(scoopInto(bentTrack(note('scoop'), styleBend(note('scoop'), null, exercise)), note('scoop'), { beatSeconds: 1 }), 'the scoop');
    const lazy = { tempo: 60, window: 0.5 };
    assert(scoopInto(bentTrack(note('scoop'), styleBend(note('scoop'), null, lazy)), note('scoop'), { beatSeconds: 1, window: 0.5 }), 'the lazy scoop');
    assert(fallOff(bentTrack(note('fall'), styleBend(note('fall'), null, exercise)), note('fall'), { beatSeconds: 1 }), 'the fall-off');
    assert(cryIn(bentTrack(note('cry'), styleBend(note('cry'), null, exercise)), note('cry'), { beatSeconds: 1 }), 'the cry');
    const next = { beat: 2, length: 1, pitch: 64 };
    const slid = bentTrack(note('slide'), styleBend(note('slide'), next, exercise), { after: next });
    assert(slideInto(slid, note('slide'), next, { beatSeconds: 1 }), 'the slide');
    assert(styleBend(note(undefined), null, exercise) === null && styleBend(note('slide'), null, exercise) === null, 'a plain note, and a slide with nowhere to go, have no bend');
    const plain = bentTrack(note(null), null);
    assert(!scoopInto(plain, note(), { beatSeconds: 1 }) && !fallOff(plain, note(), { beatSeconds: 1 }) && !cryIn(plain, note(), { beatSeconds: 1 }), 'a plain note has no move');
});

test('flip finder: a siren that jumps keeps the flip with the course and says where; one that glides says that\'s fine', () => {
    const home = 57;
    const notes = [{ beat: 0, length: 12, pitch: home }];
    const target = [{ start: 1, end: 13, pitch: home }];
    const jumping = track([[1, 0], [1, home - 3, home + 5], [1, home + 9, home + 12], [1, home + 12, home + 9], [1, home + 5, home - 3], [1, home - 3, home + 5], [1, home + 9, home + 12], [1, home + 12, home + 9], [1, home + 5, home - 3], [4, home - 3]]);
    const found = PLAY_SCORERS.flipFinder({ track: jumping, target, notes, exercise: { step: 0 } });
    assert(found.percent === 100 && found.personal?.flip === 7, JSON.stringify(found));
    assert(found.line.key === 'styles.flip.found' && found.line.values.note, JSON.stringify(found.line));
    const once = PLAY_SCORERS.flipFinder({ track: track([[1, 0], [1, home - 3, home + 5], [1, home + 9, home + 12], [1, home + 12, home + 9], [1, home + 5, home - 3], [8, home - 3]]), target, notes, exercise: { step: 0 } });
    assert(once.percent === 85 && once.personal.flip === undefined && once.personal.flipSeen.length === 1 && once.line.key === 'styles.flip.once', JSON.stringify(once));
    const confirmed = PLAY_SCORERS.flipFinder({ track: track([[1, 0], [1, home - 3, home + 5], [1, home + 9, home + 12], [1, home + 12, home + 9], [1, home + 5, home - 3], [8, home - 3]]), target, notes, exercise: { step: 0 }, personal: once.personal });
    assert(confirmed.personal.flip === 7, `a second siren confirms it: ${JSON.stringify(confirmed)}`);
    const glide = PLAY_SCORERS.flipFinder({ track: track([[1, 0], [3, home - 3, home + 12], [3, home + 12, home - 3], [6, home - 3]]), target, notes, exercise: { step: 0 } });
    assert(glide.percent === 70 && glide.personal.flip === undefined && glide.line.key === 'styles.flip.none', JSON.stringify(glide));
    const silent = PLAY_SCORERS.flipFinder({ track: track([[14, 0]]), target, notes, exercise: { step: 0 } });
    assert(silent.percent === null, 'not sung: nothing to judge');
});

test('styles: every result line names words that exist, and a flip lesson says how the way down went', async () => {
    const { t } = await import('/js/i18n.js');
    const rate = 48000;
    const hook = [{ beat: 0, length: 1, pitch: 57 }, { beat: 1, length: 1, pitch: 66 }, { beat: 2, length: 1, pitch: 57 }];
    const slidDown = trackTake(sing(rate, { notes: hook, bpm: 60, legato: 350 }), rate);
    const result = PLAY_SCORERS.flip({ track: slidDown, notes: hook, beatSeconds: 1, strictness: 'standard', exercise: {} });
    assert(result.line?.key === 'styles.flip.countDown' && t(result.line.key, result.line.values) !== result.line.key, `the flip line: ${JSON.stringify(result.line)}`);
    for (const key of ['styles.keyDrift.held', 'styles.keyDrift.low', 'styles.keyDrift.high', 'styles.flip.count', 'styles.moves.asked', 'styles.moves.yours', 'styles.moves.none', 'styles.moves.tooMany']) {
        assert(t(key, { cents: 30, intervals: 90, clean: 2, count: 2, down: 1, matched: 1, asked: 2 }) !== key, `${key} has words`);
    }
});

test('flip point: through the tracker, a rendered siren with a four-semitone jump each way is found', () => {
    const rate = 48000;
    const home = 57;
    const once = (at) => [
        { beat: at, length: 1.5, pitch: home - 3, bend: [[0, 0], [1.5, 800]] },
        { beat: at + 1.5, length: 1.5, pitch: home + 9, bend: [[0, 0], [1.5, 300]] },
        { beat: at + 3, length: 1.5, pitch: home + 12, bend: [[0, 0], [1.5, -300]] },
        { beat: at + 4.5, length: 1.5, pitch: home + 5, bend: [[0, 0], [1.5, -800]] },
    ];
    const tracked = trackTake(renderPart([...once(0), ...once(6)], { bpm: 60, sampleRate: rate }), rate);
    const found = flipPoint(tracked, { home });
    const gaps = [];
    let run = 0;
    for (const hz of tracked.hz) { if (hz > 0) { if (run) gaps.push(run); run = 0; } else run++; }
    assert(found.flip !== null && found.flip >= 6 && found.flip <= 8 && settleFlip([], found).flip !== null, `flip ${found.flip}, jumps ${JSON.stringify(found.jumps.map((j) => [j.direction, Math.round(j.middle * 10) / 10]))}, dropouts ${JSON.stringify(gaps)}`);
});

test('exercise: a note\'s style is laid out as the guide\'s bend, a note\'s own bend and head voice pass through, and plain notes are untouched', () => {
    const exercise = { tempo: 60, turn: false, notes: [
        { beat: 0, length: 1, step: 0, style: 'scoop' },
        { beat: 1, length: 1, step: 2, style: 'slide' },
        { beat: 2, length: 1, step: 4, voice: 'head', bend: [[0, 0], [1, 300]] },
        { beat: 3, length: 1, step: 5 },
    ] };
    const { guide, target } = layout(exercise, 60);
    assert(JSON.stringify(guide[0].bend) === JSON.stringify(styleBend({ ...exercise.notes[0], pitch: 60 }, null, exercise)), 'the scoop as styleBend draws it');
    assert(guide[1].bend[guide[1].bend.length - 1][1] === 200, `the slide ends on the next note: ${JSON.stringify(guide[1].bend)}`);
    assert(guide[2].voice === 'head' && guide[2].bend[1][1] === 300, 'a note\'s own bend and voice');
    assert(!guide[3].bend && !guide[3].style, 'a plain note');
    assert(target[0].style === 'scoop', 'the sung notes keep their style for the scorer');
});

test('exercise: a drone fades to silence over its stretch, and Give me my note picks the note now or next', () => {
    const rate = 1000;
    const samples = fadeOut(new Float32Array(rate * 4).fill(1), rate, 1, 2);
    assert(samples[500] === 1 && Math.abs(samples[2000] - 0.5) < 0.01 && samples[3500] === 0, `the fade: ${samples[500]} ${samples[2000]} ${samples[3500]}`);
    const target = [{ beat: 3, length: 2, pitch: 60 }, { beat: 5, length: 1, pitch: 62 }];
    assert(noteToGive(target, 1).pitch === 60, 'before the line: its first note');
    assert(noteToGive(target, 4).pitch === 60, 'during a note: that note');
    assert(noteToGive(target, 5.2).pitch === 62, 'the next');
    assert(noteToGive(target, 7) === null, 'after the line: none');
});

test('moves: the guide\'s scoop after a joined note a step below is heard, plain legato into it isn\'t, and the guide\'s slide over a whole step is heard', () => {
    const exercise = { tempo: 60 };
    const prev = { beat: 0, length: 1, pitch: 58 };
    const note = { beat: 1, length: 1, pitch: 60, style: 'scoop' };
    const bend = styleBend(note, null, exercise, prev);
    assert(bend[0][1] < -250, `starts below the note before: ${JSON.stringify(bend)}`);
    const hz = [];
    for (let i = 0; i < 100; i++) hz.push(440 * 2 ** ((58 - 69) / 12));
    for (let i = 0; i < 100; i++) hz.push(440 * 2 ** ((60 - 69 + bendAt(bend, i / 100) / 100) / 12));
    assert(scoopInto({ rate: 100, hz: Float32Array.from(hz) }, note, { beatSeconds: 1, prev }), 'the guide\'s own scoop');
    const rate = 48000;
    const line = [{ beat: 0, length: 1, pitch: 58 }, { beat: 1, length: 1, pitch: 60 }, { beat: 2, length: 1, pitch: 62 }];
    const legato = trackTake(sing(rate, { notes: line, bpm: 60, legato: 70 }), rate);
    assert(!scoopInto(legato, line[1], { beatSeconds: 1, prev: line[0] }) && !scoopInto(legato, line[2], { beatSeconds: 1, prev: line[1] }), 'plain legato is no scoop');
    const plain = styledMoves(legato, line, [null, null, null], { beatSeconds: 1 });
    assert(plain.matched === 0, `plain legato, your way: no move: ${JSON.stringify(plain.heard)}`);
    const a = { beat: 0, length: 1, pitch: 60, style: 'slide' };
    const b = { beat: 1, length: 1, pitch: 62 };
    assert(slideInto(bentTrack(a, styleBend(a, b, exercise), { at: 0, after: b }), a, b, { beatSeconds: 1 }), 'the guide\'s slide over a whole step');
    const third = { beat: 1, length: 1, pitch: 64 };
    assert(slideInto(bentTrack(a, styleBend(a, third, exercise), { at: 0, after: third }), a, third, { beatSeconds: 1 }), 'and over a third');
});

test('moves: a late arrival from above isn\'t a cry, and a drill whose every note asks for a move isn\'t told less is more', () => {
    const note = { beat: 1, length: 1, pitch: 60 };
    assert(!cryIn(track([[1, 0], [0.12, 63], [0.88, 60]]), note, { beatSeconds: 1 }), 'coming in from a third above, late');
    const drill = [{ beat: 1, length: 1, pitch: 60 }, { beat: 3, length: 1, pitch: 62 }];
    const sung = track([[1, 0], [0.15, 58, 60], [0.85, 60], [1, 0], [0.15, 60, 62], [0.85, 62]]);
    const asked = styledMoves(sung, drill, ['scoop', 'scoop'], { beatSeconds: 1 });
    assert(asked.matched === 2 && !asked.tooMany, JSON.stringify(asked));
});

test('flip: a step set from the flip note before a flip is found stars the notes only, and says why', () => {
    const rate = 48000;
    const hook = [{ beat: 0, length: 1, pitch: 57 }, { beat: 1, length: 1, pitch: 66 }, { beat: 2, length: 1, pitch: 57 }];
    const sung = trackTake(sing(rate, { notes: hook, bpm: 60, legato: 350 }), rate);
    const args = { track: sung, notes: hook, beatSeconds: 1, strictness: 'standard', exercise: { base: 'flip' } };
    const unknown = PLAY_SCORERS.flip({ ...args, personal: {} });
    assert(unknown.line.key === 'styles.flip.pitchOnly' && unknown.percent >= 90, `pitch only: ${JSON.stringify(unknown)}`);
    const known = PLAY_SCORERS.flip({ ...args, personal: { flip: 7 } });
    assert(known.percent < unknown.percent, `with a flip, the slid leaps cost: ${known.percent}`);
});

test('moves: on the lessons\' own note lengths, a copy of the guide\'s slide is heard, with jitter too, and a plain release droop is no fall-off', () => {
    const beat = 60 / 96; // Down in the Valley's tempo: a one-beat note is 0.625 s
    const exercise = { tempo: 96 };
    const a = { beat: 0, length: 1, pitch: 60, style: 'slide' };
    const b = { beat: 1, length: 1, pitch: 62 };
    let seed = 5;
    const jitter = () => ((seed = (seed * 16807) % 2147483647) / 2147483647 - 0.5) * 30; // ±15 cents
    const run = (wobble) => {
        const bend = styleBend(a, b, exercise);
        const hz = [];
        for (let i = 0; i < Math.round(beat * 100); i++) hz.push(440 * 2 ** ((60 - 69 + (bendAt(bend, i / 100) + wobble()) / 100) / 12));
        for (let i = 0; i < Math.round(beat * 100); i++) hz.push(440 * 2 ** ((62 - 69 + wobble() / 100) / 12));
        return slideInto({ rate: 100, hz: Float32Array.from(hz) }, a, b, { beatSeconds: beat });
    };
    assert(run(() => 0), 'an exact copy of the guide\'s slide on a one-beat note');
    let caught = 0;
    for (let k = 0; k < 20; k++) if (run(jitter)) caught++;
    assert(caught >= 18, `with ±15 cents of jitter: ${caught} of 20`);
    const note = { beat: 1, length: 1, pitch: 60 };
    const droop = track([[1, 0], [0.92, 60], [0.08, 60, 57.5], [0.3, 0]]); // 250 cents in the last 80 ms
    assert(!fallOff(droop, note, { beatSeconds: 1 }), 'a release droop');
});
