// Two lines, measured (js/harmony.js): motion named as a musician would (sixths to sixths are
// parallel), the course's first-species line read as six contrary moves and no parallels, parallel
// fifths and octaves found, `choices` resolved to what was sung, and a pull onto a partner's note
// told apart from a note simply sung off.
import { awkwardLeap, compareLines, foldedCents, holdsPercent, speciesPercent, STARS_3, intervalClass, motionOf, openChoices, pitchAt, pulls, resolveChoices, speciesChecks, sungPitch } from '/js/harmony.js';
import { droneSpan, Exercise, heardNotes, layout } from '/js/components/exercise.js';
import { assert, close, test } from './harness.js';

const RATE = 100;
const BEAT = 1; // a second a beat
const hz = (midi) => 440 * 2 ** ((midi - 69) / 12);

/** A track that sings `pitches` (MIDI, null for silence), one per beat, with a small wobble. */
function track(pitches) {
    const out = [];
    pitches.forEach((p, k) => {
        for (let i = 0; i < BEAT * RATE; i++) out.push(p === null ? 0 : hz(p + 0.05 * Math.sin(i / 3 + k)));
    });
    return { rate: RATE, hz: out };
}

const notes = (pitches) => pitches.map((pitch, beat) => ({ beat, length: 1, pitch }));

test('harmony: motion is contrary, oblique, parallel (by size) or similar', () => {
    assert(motionOf(64, 62, 48, 50) === 'contrary', 'opposite ways');
    assert(motionOf(64, 64, 48, 50) === 'oblique', 'one holds');
    assert(motionOf(69, 67, 60, 59) === 'parallel', 'a major sixth to a minor sixth is still parallel sixths');
    assert(motionOf(67, 69, 60, 64) === 'similar', 'a fifth to a fourth, the same way');
    assert(motionOf(60, 60, 48, 48) === 'none', 'both hold');
});

test('harmony: the course\'s first species line is six contrary moves, no parallels', () => {
    const home = 60;
    const cantus = [-12, -10, -7, -8, -5, -7, -8, -10, -12].map((s) => home + s);
    const line = [0, -1, -3, 0, -1, 2, 0, -1, 0].map((s) => home + s);
    const result = compareLines(line, cantus);
    assert(result.counts.contrary === 6, `contrary ${result.counts.contrary}`);
    assert(result.counts.parallel === 2, `parallel sixths ${result.counts.parallel}`);
    assert(result.parallels.length === 0, 'no parallel fifths or octaves');
    line.forEach((p, i) => assert([0, 3, 4, 7, 8, 9].includes(intervalClass(cantus[i], p)), `note ${i} consonant`));
});

test('harmony: parallel fifths and octaves are found, contrary ones are not', () => {
    const fifths = compareLines([67, 69, 71], [60, 62, 64]);
    assert(fifths.parallels.length === 2 && fifths.parallels.every((p) => p.kind === 'fifth'), JSON.stringify(fifths.parallels));
    const octaves = compareLines([72, 74], [60, 62]);
    assert(octaves.parallels[0]?.kind === 'octave', 'parallel octaves');
    const unisonToOctave = compareLines([60, 74], [60, 62]);
    assert(unisonToOctave.parallels[0]?.kind === 'octave', 'a unison to an octave, the same way');
    const contrary = compareLines([67, 74], [60, 55]);
    assert(contrary.parallels.length === 0, 'fifth to octave by contrary motion is fine');
    const held = compareLines([67, 67], [60, 60]);
    assert(held.parallels.length === 0, 'a repeated fifth is not a parallel');
});

test('harmony: a line\'s pitch at a beat, and cents folded to the nearest octave', () => {
    const line = [{ beat: 0, length: 2, pitch: 60 }, { beat: 3, length: 1, pitch: 62 }];
    assert(pitchAt(line, 1.5) === 60 && pitchAt(line, 2.5) === null && pitchAt(line, 3) === 62, 'pitchAt');
    close(foldedCents(48.2, 60), 20, 0.01, 'an octave below, 20 cents sharp');
    close(foldedCents(59.7, 60), -30, 0.01, 'flat');
});

test('harmony: choices resolve to the one sung, in any octave', () => {
    const target = [{ beat: 0, length: 1, pitch: 64, choices: [64, 67, 69] }, { beat: 1, length: 1, pitch: 60, choices: [60, 64] }, { beat: 2, length: 1, pitch: 62 }];
    const resolved = resolveChoices(track([69, 52, 62]), target, BEAT);
    assert(resolved[0].pitch === 69 && resolved[0].chosen === 69, 'sang the sixth');
    assert(resolved[1].pitch === 64, 'sang mi an octave down');
    assert(resolved[2] === target[2], 'a note without choices is left alone');
    const silent = resolveChoices(track([null, null, null]), target, BEAT);
    assert(silent[0].pitch === 64 && silent[0].chosen === null, 'not heard: the first choice, nothing chosen');
    assert(sungPitch(track([null]), target[0], BEAT) === null, 'nothing heard');
});

test('harmony: a pull onto the partner is told apart from a note sung off', () => {
    const target = notes([64, 64, 64, 64]);
    const partner = notes([60, 67, 67, 64]);
    // Note 1: sang the partner's so (pulled). Note 2: sang fa, nobody's note (off, not pulled).
    // Note 3: the partner sings your own mi too: never a pull. Note 0: on pitch.
    const found = pulls(track([64, 67, 65, 67]), target, [partner], { beatSeconds: BEAT });
    assert(found.length === 1 && found[0].index === 1 && found[0].partner === 67, JSON.stringify(found));
    const octave = pulls(track([64, 55, 64, 64]), target, [partner], { beatSeconds: BEAT });
    assert(octave.length === 1 && octave[0].index === 1, 'the partner\'s so an octave down is still a pull');
    // A partner a fourth or more away (la below your mi): singing it is off pitch, not a pull.
    const far = pulls(track([57]), notes([64]), [notes([57])], { beatSeconds: BEAT });
    assert(far.length === 0, 'out of reach');
    // Two partners either side of your mi: singing either one is a pull onto it.
    const either = pulls(track([67]), notes([64]), [notes([62]), notes([67])], { beatSeconds: BEAT });
    assert(either.length === 1 && either[0].partner === 67, JSON.stringify(either));
    // Singing your own next note early, which the partner happens to sing now: not a pull.
    const early = pulls(track([62, 67, 67]), notes([62, 64, 67]), [notes([60, 67, 64])], { beatSeconds: BEAT });
    assert(early.length === 0, JSON.stringify(early));
});

test('harmony: a lit fifth or octave dims when it would make a parallel', () => {
    // Last pair: so over do (a fifth). Now the cantus steps up to re: la (another fifth, the same way) dims.
    const open = openChoices([65, 69, 71], 62, { sung: 67, cantus: 60 });
    assert(!open.includes(69) && open.includes(65) && open.includes(71), JSON.stringify(open));
    // The same fifth reached by contrary motion stays lit.
    assert(openChoices([57], 50, { sung: 67, cantus: 48 }).includes(57), 'contrary fifths');
    assert(openChoices([69], 62, null).length === 1, 'the first note: all lit');
});

test('harmony: the species checklist reads contrary moves, parallels and the cadence', () => {
    const home = 60;
    const cantus = [-12, -7, -8, -10, -5, -7, -8, -10, -12].map((s) => home + s);
    const good = speciesChecks([0, -3, 0, 2, -1, 2, 0, -1, 0].map((s) => home + s), cantus);
    assert(good.contrary === 6 && good.moves === 8 && good.parallels.length === 0 && good.cadence, JSON.stringify(good));
    const thirds = speciesChecks(cantus.map((p) => p + 16), cantus);
    assert(thirds.contrary === 0 && !thirds.cadence, 'parallel tenths: no contrary, no cadence');
});

// The exercise's layout with the new fields (components/exercise.js): every one optional, and an
// exercise with none of them laid out exactly as before.
const HOME = 60;
const call = [{ beat: 0, length: 1, step: 0 }, { beat: 1, length: 1, step: 2 }, { beat: 2, length: 2, step: 4 }];

test('exercise layout: an exercise with none of the new fields is laid out as before', () => {
    const turn = layout({ notes: call }, HOME);
    assert(JSON.stringify(Object.keys(turn)) === '["guide","target","turnFrom","end"]', Object.keys(turn).join());
    assert(turn.turnFrom === 5 && turn.end === 9.5 && turn.target[0].beat === 5 && turn.target[2].pitch === 64, JSON.stringify(turn));
    assert(Object.keys(turn.guide[0]).join() === 'beat,length,pitch,lyric,vibrato', 'no new keys on a note');
    const over = layout({ notes: call, turn: false }, HOME);
    assert(over.target === over.guide && over.turnFrom === 0 && over.end === 4.5, 'over a drone: the notes are the target');
    assert(heardNotes(turn, {}, 'play') === turn.guide && heardNotes(over, { turn: false }, 'play').length === 0 && heardNotes(over, { turn: false }, 'listen') === over.guide, 'the guide as before');
    const drone = droneSpan(over, {}, 4.5);
    assert(drone.from === 0 && drone.to === 4.5, 'the drone from the start');
});

test('exercise layout: cents tune a note off the piano\'s, and choices become pitches', () => {
    const laid = layout({ notes: [{ beat: 0, length: 2, step: 4, cents: -14 }, { beat: 2, length: 2, step: 0, choices: [3, 4, 7] }], turn: false }, HOME);
    close(laid.target[0].pitch, 63.86, 1e-9, 'a pure third');
    assert(laid.target[1].choices.join() === '63,64,67', laid.target[1].choices.join());
});

test('exercise layout: an answer differs from the call, and listen-only notes are heard, never sung', () => {
    const answered = layout({ notes: call, answer: [{ beat: 0, length: 3, step: -1 }] }, HOME);
    assert(answered.target.length === 1 && answered.target[0].pitch === 59 && answered.target[0].beat === 5 && answered.end === 8.5, JSON.stringify(answered));
    const echo = layout({ notes: [{ beat: 1, length: 1, step: 0, listenOnly: true }, { beat: 2.5, length: 1.5, step: 0 }, { beat: 4.5, length: 1, step: 2, listenOnly: true }, { beat: 6, length: 1.5, step: 2 }], turn: false, guide: false }, HOME);
    assert(echo.target.length === 2 && echo.target.every((n) => !n.listenOnly), 'only your notes are sung');
    assert(heardNotes(echo, { turn: false, guide: false }, 'play').length === 2, 'the guide sings the listen-only notes');
    const cold = droneSpan(echo, { guide: false, droneAfter: 2 }, 8);
    assert(cold.from === 3 && cold.to === 8, `the drone after 2 beats of the turn ${JSON.stringify(cold)}`);
    assert(droneSpan(echo, { guide: false }, 8).to === 1, 'a cold start without droneAfter: no drone in the turn');
});

test('exercise layout: partner lines, written out or the sung line delayed (a round)', () => {
    const round = layout({ notes: call, turn: false, with: [{ delay: 2 }, { delay: 4, steps: -12 }, { notes: [{ beat: 0, length: 4, step: -12 }], volume: 0.3 }] }, HOME);
    assert(round.partners.length === 3, 'three partners');
    assert(round.partners[0].notes[0].beat === 2 && round.partners[0].volume === 0.5, 'the tune 2 beats later, 6 dB under');
    assert(round.partners[1].notes[2].pitch === 52 && round.partners[1].notes[2].beat === 6, 'an octave down, 4 beats later');
    assert(round.partners[2].volume === 0.3 && round.partners[2].notes[0].pitch === 48, 'a written line');
    assert(round.end === 8.5, `the exercise ends with the last voice ${round.end}`);
    assert(round.target.length === 3 && round.target[0].beat === 0, 'you still sing your own line');
});

test('harmony: four holds with breaths between are each judged on their own', () => {
    // Dots every 0.1 s, on pitch through each of four 3.5 s holds, silent in the half-second breaths.
    const target = [0, 4, 8, 12].map((start) => ({ start, end: start + 3.5, pitch: 60 }));
    const dots = [];
    for (let t = 0; t < 16; t += 0.1) if (t % 4 < 3.5) dots.push({ t, cents: 0, in: true, pitch: 60 });
    const percent = holdsPercent(dots, target);
    assert(percent >= 90, `four full holds: ${percent}`);
    const half = holdsPercent(dots.filter((d) => d.t < 8), target);
    assert(half >= 40 && half <= 60, `two of four held: ${half}`);
    assert(holdsPercent([], target) === null, 'nothing heard');
});

test('harmony: a chosen line keeps its notes\' percent, and three stars only by the rules', () => {
    const home = 60;
    const cantus = [-12, -10, -7, -8, -5, -7, -8, -10, -12].map((s) => home + s);
    const good = [0, -1, -3, 0, -1, 2, 0, -1, 0].map((s) => home + s);
    assert(speciesPercent(95, good, cantus, 'all') === 95, 'every rule kept');
    assert(speciesPercent(95, good, cantus, 'contrary') === 95, 'three contrary moves and more');
    const tenths = cantus.map((p) => p + 16); // all parallel: no contrary motion, no cadence
    assert(speciesPercent(95, tenths, cantus, 'contrary') === STARS_3 - 1, 'short of three stars, not lower');
    assert(speciesPercent(60, tenths, cantus, 'all') === 60, 'a lower percent stays as it is');
    assert(speciesPercent(null, good, cantus, 'all') === null, 'not heard');
});

test('harmony: direct fifths and octaves dim, awkward leaps dim while another choice stays lit', () => {
    // Last pair: mi over do (a third). Cantus up to re; a fifth (la) reached with both rising is a direct fifth.
    const open = openChoices([65, 69, 71], 62, { sung: 64, cantus: 60 });
    assert(!open.includes(69) && open.includes(65), `direct fifth dimmed: ${open}`);
    // From so (67): a tritone leap to C# would dim, but not if it's the only choice left.
    assert(!openChoices([61, 64], 55, { sung: 67, cantus: 60 }).includes(61), 'a tritone leap dims');
    assert(openChoices([61], 57, { sung: 67, cantus: 60 }).length === 1, 'never every choice');
    assert(awkwardLeap(60, 69) && awkwardLeap(60, 66) && !awkwardLeap(60, 67) && !awkwardLeap(60, 72), 'sixths, tritones, sevenths');
    // Mi over do (a third), then both rise into so over fa (a fifth): a direct fifth, not a parallel.
    const checks = speciesChecks([52, 60], [48, 53]);
    assert(checks.directs.length === 1 && checks.directs[0].kind === 'fifth' && checks.parallels.length === 0, JSON.stringify(checks));
    const good = speciesChecks([60, 59, 57, 60, 59, 62, 60, 59, 60], [48, 50, 53, 52, 55, 53, 52, 50, 48]);
    assert(good.directs.length === 0 && good.leaps.length === 0, `the course's line: ${JSON.stringify(good)}`);
});

test('exercise lights: every note in full by default, and each mode draws as it says', () => {
    const drawn = Exercise.methods.drawn;
    const target = [0, 1, 2, 3].map((k) => ({ beat: 4 * k, length: 4, pitch: 60 }));
    const vm = (exercise, extra = {}) => ({ exercise, mode: 'play', firstTarget: 1, laid: { target }, running: true, beatNow: 0, litNow: () => false, changeBeats: new Set((exercise.chords ?? []).map((c) => c.beat)), ...extra });
    const all = (v) => target.map((n, k) => drawn.call(v, n, k + 1)).join();
    assert(all(vm({})) === 'full,full,full,full', 'the default: every note in full, as before');
    assert(all(vm({ lights: 'on' })) === 'full,full,full,full', 'on');
    assert(drawn.call(vm({ lights: 'off' }), { beat: 0 }, 0) === 'full', 'a note to listen to is always drawn');
    assert(all(vm({ lights: 'fade' })) === 'full,full,faint,faint', 'fade: the second half faint');
    assert(all(vm({ lights: 'changes', chords: [{ beat: 0 }, { beat: 8 }] })) === 'full,hidden,full,hidden', 'changes');
    assert(all(vm({ lights: 'late' }, { beatNow: 5 })) === 'full,full,hidden,hidden', 'late: shown a beat in');
    assert(all(vm({ lights: 'off' })) === 'hidden,hidden,hidden,hidden', 'off');
    assert(all(vm({ lights: 'off' }, { litNow: () => true })) === 'full,full,full,full', 'a lit note is always in full');
    assert(all(vm({ lights: 'off' }, { mode: 'listen' })) === 'full,full,full,full', 'listening: everything shown');
});

test('harmony: a close part keeps three stars only when four moves in five are a step or less', () => {
    const tune = [60, 62, 64, 65, 67, 65, 64];
    assert(speciesPercent(95, [64, 65, 67, 67, 71, 69, 67], tune, 'close') === 95, 'steps and a held note');
    assert(speciesPercent(95, [64, 72, 60, 69, 62, 70, 64], tune, 'close') === STARS_3 - 1, 'leaping about');
});

test('harmony: a part below the cantus is measured from the lower note up', () => {
    assert(intervalClass(60, 53) === 7 && intervalClass(53, 60) === 7, 'a fifth either way round');
    // Below the cantus: a parallel fifth (55 under 62, then 53 under 60) dims; a fourth below never does.
    assert(!openChoices([53, 57], 60, { sung: 55, cantus: 62 }).includes(53), 'a parallel fifth below dims');
    assert(openChoices([55], 60, { sung: 57, cantus: 62 }).includes(55), 'a fourth below is not a perfect fifth');
    const below = compareLines([53, 51], [60, 58]);
    assert(below.parallels.length === 1 && below.parallels[0].kind === 'fifth', JSON.stringify(below.parallels));
});

test('harmony: close needs real moving, and against needs half the moving notes against the tune', () => {
    const tune = [60, 62, 64, 65, 67, 65, 64];
    assert(speciesPercent(95, [64, 64, 64, 64, 64, 64, 64], tune, 'close') === STARS_3 - 1, 'one held note is not a part');
    assert(speciesPercent(95, [64, 65, 67, 67, 71, 69, 67], tune, 'close') === 95, 'small moves');
    assert(speciesPercent(95, [67, 65, 64, 62, 60, 62, 64], tune, 'against') === 95, 'against the tune all the way');
    assert(speciesPercent(95, [64, 65, 67, 69, 71, 69, 67], tune, 'against') === STARS_3 - 1, 'with the tune all the way');
});

test('harmony: a close part may move by a third, as chord tones do, but not leap about', () => {
    const tune = [60, 62, 64, 65, 67, 65, 64, 62];
    // Holding where it can, else the nearest chord tone: thirds, all of them close.
    assert(speciesPercent(95, [64, 64, 67, 69, 71, 69, 67, 67], tune, 'close') === 95, 'thirds and holds');
    assert(speciesPercent(95, [64, 71, 60, 69, 62, 70, 64, 72], tune, 'close') === STARS_3 - 1, 'sixths and sevenths are not close');
});

test('exercise lane label: hidden lights never named, choices all named, the free course as before', () => {
    const label = Exercise.computed.label;
    const target = [{ pitch: 60 }, { pitch: 64, choices: [64, 67] }];
    const vm = (exercise, extra = {}) => ({ exercise, mode: 'play', laid: { target, ...extra }, name: (p) => ({ 60: 'C4', 64: 'E4', 67: 'G4' })[p] });
    const off = label.call(vm({ lights: 'off' }));
    assert(!off.includes('C4') && !off.includes('E4'), `lights off names no note: ${off}`);
    const on = label.call(vm({}));
    assert(on.includes('C4') && on.includes('E4') && on.includes('G4'), `every choice named: ${on}`);
    const plain = label.call({ exercise: {}, mode: 'play', laid: { target: [{ pitch: 60 }] }, name: () => 'C4' });
    assert(!plain.includes('voices') && plain.includes('C4'), `a plain exercise as before: ${plain}`);
});
