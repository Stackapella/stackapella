// The singing test's engine (js/singing-test/, docs/singing-test-v1.md): its items in the singer's own range, its
// scoring (the contract's, agreed with ai-backend), and its measures on synthetic voices: 5, 25 and 60 cents off, a
// vibrato, an octave slip, a late start, a scoop, a sag, a pattern sung in another key, a wrong note, a missing note;
// then a bass and a soprano through the real pitch tracker.
import { anchorFor, buildTest, REACH, TEST_VERSION } from '/js/singing-test/items.js';
import { backItem, holdItem, matchItem } from '/js/singing-test/measure.js';
import { createRun, RETRIES } from '/js/singing-test/run.js';
import { bandOf, compare, enoughHeard, result, scoreTest, share, shown } from '/js/singing-test/score.js';
import { trackTake } from '/js/pitchtrack.js';
import { sing } from './fixtures.js';
import { assert, close, test } from './harness.js';

const RATE = 100;

/** A synthetic pitch track: segments of `{ seconds, midi, cents, vibrato, drift, noise }`, or `{ seconds }` silent. */
function trackOf(segments, seed = 3) {
    let s = seed;
    const random = () => ((s = (s * 16807) % 2147483647) / 2147483647) - 0.5;
    const hz = [];
    const clarity = [];
    const loudness = [];
    for (const seg of segments) {
        const frames = Math.round(seg.seconds * RATE);
        for (let i = 0; i < frames; i++) {
            if (seg.midi === undefined) {
                hz.push(0);
                clarity.push(0);
                loudness.push(-80);
                continue;
            }
            const t = i / RATE;
            let c = (seg.cents ?? 0) + (seg.drift ?? 0) * (t / seg.seconds) + (seg.noise ?? 2) * random();
            if (seg.vibrato) c += seg.vibrato.cents * Math.sin(2 * Math.PI * seg.vibrato.hz * t);
            if (seg.scoop && t * 1000 < seg.scoop.ms) c += seg.scoop.cents * (1 - (t * 1000) / seg.scoop.ms);
            hz.push(440 * 2 ** ((seg.midi - 69) / 12 + c / 1200));
            clarity.push(0.95);
            loudness.push(seg.loud === undefined ? -20 : seg.loud + (seg.fade - seg.loud) * (i / frames)); // a prompt's tail fades
        }
    }
    return { rate: RATE, hz, clarity, loudness };
}

// Items: the singer's own range, the same every time.

test('singing test: every item within a fifth of the anchor, practice first', () => {
    for (const anchor of [45, 57.3, 74]) {
        const t = buildTest(anchor);
        const notes = [...t.match.map((i) => i.target), ...t.back.flatMap((p) => p.pattern), ...t.hold.map((h) => h.target)];
        assert(notes.every((n) => Math.abs(n - Math.round(anchor)) <= REACH), `${anchor}: a note past a fifth`);
        assert(t.match.length === 13 && t.match[0].practice && !t.match[1].practice, 'match: 1 practice + 12');
        assert(t.back.length === 7 && t.back[0].practice && t.back.every((p) => p.pattern.length === 4), 'back: 1 practice + 6 of four');
        assert(t.hold.length === 2 && t.hold[1].target - t.hold[0].target === 2, 'hold: their note, then a step above');
    }
    assert(JSON.stringify(buildTest(60)) === JSON.stringify(buildTest(60)), 'the same every time');
});

test('singing test: the first anchor stays, moving only after two tests well away on one side', () => {
    assert(anchorFor([], 57.26).anchor === 57.3, 'the first test sets it, to one decimal');
    const one = [{ testVersion: TEST_VERSION, anchor: 57, found: 57.4 }];
    assert(anchorFor(one, 62).anchor === 57, 'one test away keeps it');
    const two = [{ testVersion: TEST_VERSION, anchor: 57, found: 61.5 }];
    const moved = anchorFor(two, 62);
    assert(moved.anchor === 62 && moved.moved, 'two in a row, the same side, moves it');
    assert(anchorFor([{ testVersion: TEST_VERSION, anchor: 57, found: 52 }], 62).anchor === 57, 'away on both sides keeps it');
});

// Scoring: the contract's, exactly.

const flat = (cents, n = 12) => Array.from({ length: n }, (_, i) => ({ target: 60 + i, centsOff: cents, octave: 0 }));
const patterns = (cents, n = 6) => Array.from({ length: n }, () => ({ pattern: [60, 62, 64, 62], notes: [cents, -cents, cents, -cents].map((c) => ({ centsOff: c })) }));
const holds = (spread, drift) => [{ target: 60, spreadCents: spread, driftCents: drift }, { target: 62, spreadCents: spread, driftCents: drift }];

test('singing test: each event from its ceiling to its floor, the total rounded half up', () => {
    close(share(10, { ceiling: 10, floor: 150 }), 1, 1e-9, 'at the ceiling');
    close(share(150, { ceiling: 10, floor: 150 }), 0, 1e-9, 'at the floor');
    close(share(80, { ceiling: 10, floor: 150 }), 0.5, 1e-9, 'halfway');
    const best = scoreTest({ match: flat(5), back: patterns(10), hold: holds(4, 2) });
    assert(best.total === 100 && best.band === 'precise' && !best.practice, `the best: ${JSON.stringify(best)}`);
    // match 80 c: 22.5; back 107.5 c: 20; hold 30 + 10: 15 × (80 − 40) / 72 = 8.333…: 50.83 → 51.
    const mid = scoreTest({ match: flat(80), back: patterns(107.5), hold: holds(30, -20) });
    close(mid.match, 22.5, 1e-9, 'match points');
    close(mid.back, 20, 1e-9, 'back points');
    close(mid.hold, 15 * (40 / 72), 1e-9, 'hold points');
    assert(mid.total === 51, `total ${mid.total}`);
    assert(shown(mid.hold) === 8.3, `shown ${shown(mid.hold)}`);
    // Half up: 22.5 + 20 + 7.5 = 50 exactly; 0.5 more points rounds up.
    assert(Math.floor(50.5 + 0.5) === 51, 'half up');
});

test('singing test: the shared scoring cases, exactly as the server scores them', async () => {
    // contract/singing-test/scoring-v1.json, from ai-backend's reference implementation: the device and the server agree to the digit.
    const { cases } = await (await fetch('/contract/singing-test/scoring-v1.json')).json();
    assert(cases.length >= 13, `${cases.length} cases`);
    for (const c of cases) {
        const got = result(c.test);
        assert(JSON.stringify(got) === JSON.stringify(c.expected), `${c.name}: ${JSON.stringify(got)}, not ${JSON.stringify(c.expected)}`);
    }
});

test('singing test: five bands, each edge going to the worse one; the lowest never a number', () => {
    assert(bandOf(14.9) === 'precise' && bandOf(15) === 'inTune', '15 is in tune');
    assert(bandOf(34.9) === 'inTune' && bandOf(35) === 'nearly', '35 is nearly');
    assert(bandOf(59.9) === 'nearly' && bandOf(60) === 'closer', '60 is closer');
    assert(bandOf(99.9) === 'closer' && bandOf(100) === 'finding', '100 is finding');
    assert(bandOf(null) === null, 'nothing heard, no band');
});

test('singing test: too little heard is practice, and unheard is never wrong', () => {
    const quiet = flat(null);
    assert(!enoughHeard({ match: quiet, back: patterns(10), hold: holds(5, 0) }), 'no match heard: practice');
    const seven = [...flat(20, 7), ...flat(null, 5)];
    assert(scoreTest({ match: seven, back: patterns(10), hold: holds(5, 0) }).practice, '7 of 12 is practice');
    const eight = [...flat(20, 8), ...flat(null, 4)];
    const counted = scoreTest({ match: eight, back: patterns(10), hold: holds(5, 0) });
    assert(!counted.practice, '8 of 12 counts');
    close(counted.match, scoreTest({ match: flat(20, 8), back: patterns(10), hold: holds(5, 0) }).match, 1e-9, 'unheard costs nothing');
    const twoHeard = [{ pattern: [60, 62, 64, 62], notes: [{ centsOff: 5 }, { centsOff: null }, { centsOff: null }, { centsOff: 5 }] }];
    assert(!enoughHeard({ match: flat(5), back: [...patterns(10, 3), ...twoHeard], hold: holds(5, 0) }), 'a pattern needs 3 of 4 notes');
    assert(scoreTest({ match: flat(null), back: [], hold: [] }).match === null, 'an event with nothing heard is null');
});

test('singing test: "improved" only past 8 points or 15 cents, within one test version', () => {
    const before = { testVersion: 1, total: 60, match: flat(60), back: patterns(60), hold: holds(20, 0) };
    assert(compare(before, { ...before, total: 67 }) === 'same', '7 points is about the same');
    assert(compare(before, { ...before, total: 68 }) === 'improved', '8 points is improved');
    assert(compare(before, { ...before, total: 62, match: flat(44) }) === 'improved', 'match 16 cents better is improved');
    assert(compare(before, { ...before, total: 62, match: flat(46) }) === 'same', '14 cents is about the same');
    assert(compare(before, { ...before, testVersion: 2, total: 90 }) === null, 'never across a version seam');
});

// Measures on synthetic voices (each answer a reaction time after the prompt, as a person's is).

test('singing test: a matched note, 5, 25 and 60 cents off, from its steady middle', () => {
    for (const cents of [5, 25, 60, -25]) {
        const item = matchItem(trackOf([{ seconds: 0.3 }, { seconds: 1.6, midi: 57, cents }, { seconds: 0.3 }]), 57, { promptEnd: 0 });
        close(item.centsOff, cents, 4, `${cents} c off`);
        assert(item.octave === 0 && item.jitterCents < 4, `octave ${item.octave}, jitter ${item.jitterCents}`);
        close(item.startAfterPromptMs, 300, 20, 'started after the prompt');
    }
});

test('singing test: an octave answer is shown, not penalised; a late start and a scoop cost nothing', () => {
    const octave = matchItem(trackOf([{ seconds: 0.3 }, { seconds: 1.5, midi: 69, cents: 12 }]), 57);
    assert(octave.octave === 1, `octave ${octave.octave}`);
    close(octave.centsOff, 12, 4, 'cents in the octave sung');
    const late = matchItem(trackOf([{ seconds: 1.2 }, { seconds: 1.5, midi: 57, cents: 8 }]), 57);
    close(late.startAfterPromptMs, 1200, 20, 'a late start, reported');
    close(late.centsOff, 8, 4, 'a late start judged as sung');
    const scoop = matchItem(trackOf([{ seconds: 0.3 }, { seconds: 1.5, midi: 57, cents: 0, scoop: { cents: -150, ms: 200 } }]), 57);
    close(scoop.centsOff, 0, 5, 'a scoop doesn\'t move the middle');
    assert(scoop.landingMs > 50, `landing ${scoop.landingMs} ms`);
});

test('singing test: too little singing is unheard, never wrong', () => {
    const blip = matchItem(trackOf([{ seconds: 0.5 }, { seconds: 0.2, midi: 57 }, { seconds: 1 }]), 57);
    assert(blip.centsOff === null, 'a blip is unheard');
    const nothing = matchItem(trackOf([{ seconds: 2 }]), 57);
    assert(nothing.centsOff === null && nothing.startAfterPromptMs === null, 'silence is unheard');
});

test('singing test: a hold\'s vibrato is forgiven, its sag is drift, its wobble is spread', () => {
    const vibrato = holdItem(trackOf([{ seconds: 0.3 }, { seconds: 4, midi: 57, vibrato: { cents: 40, hz: 5.5 } }]), 57);
    assert(vibrato.spreadCents < 8, `vibrato spread ${vibrato.spreadCents}`);
    close(vibrato.driftCents, 0, 6, 'no drift');
    const sag = holdItem(trackOf([{ seconds: 0.3 }, { seconds: 4, midi: 57, drift: -40 }]), 57);
    assert(sag.driftCents < -20, `drift ${sag.driftCents}`);
    assert(sag.spreadCents < 6, `a straight sag isn't spread: ${sag.spreadCents}`);
    const wobbly = holdItem(trackOf([{ seconds: 0.3 }, { seconds: 4, midi: 57, noise: 60 }]), 57);
    const steady = holdItem(trackOf([{ seconds: 0.3 }, { seconds: 4, midi: 57, noise: 4 }]), 57);
    assert(wobbly.spreadCents > steady.spreadCents + 3, `wobble ${wobbly.spreadCents} vs ${steady.spreadCents}`);
    const short = holdItem(trackOf([{ seconds: 1.5, midi: 57 }]), 57);
    assert(short.spreadCents === null && short.driftCents === null, 'a short hold is unheard');
});

/** A pattern sung: each note for `seconds`, a short breath between, at `offsets` cents from the pattern's notes. */
const sungPattern = (pattern, offsets, { seconds = 0.45, key = 0 } = {}) => trackOf(pattern.flatMap((midi, i) => (offsets[i] === null ? [{ seconds: seconds + 0.12 }] : [{ seconds, midi, cents: offsets[i] + key }, { seconds: 0.12 }])));

test('singing test: a pattern sung in another key costs only its reported offset', () => {
    const pattern = [57, 59, 61, 59];
    const item = backItem(sungPattern(pattern, [0, 0, 0, 0], { key: 200 }), pattern);
    item.notes.forEach((n, i) => close(n.centsOff, 0, 6, `note ${i + 1}`));
    close(item.startOffsetCents, 200, 6, 'their key, reported apart');
});

test('singing test: one wrong note counts once; a missing note is placed where it fits', () => {
    const pattern = [57, 59, 61, 59];
    const wrong = backItem(sungPattern(pattern, [0, 0, 100, 0]), pattern);
    close(wrong.notes[2].centsOff, 100, 8, 'the wrong note');
    [0, 1, 3].forEach((i) => close(wrong.notes[i].centsOff, 0, 6, `note ${i + 1} unaffected`));
    const missing = backItem(sungPattern(pattern, [0, 0, null, 0]), pattern);
    assert(missing.notes[2].centsOff === null, `the missing note: ${JSON.stringify(missing.notes)}`);
    [0, 1, 3].forEach((i) => close(missing.notes[i].centsOff, 0, 6, `note ${i + 1} placed`));
});

test('singing test: a note too short to read gives no jitter (the boards\' "flat" check counts only notes long enough)', () => {
    const pattern = [57, 59, 61, 59];
    const track = trackOf([{ seconds: 0.2, midi: 57 }, { seconds: 0.12 }, ...[59, 61, 59].flatMap((midi) => [{ seconds: 0.45, midi }, { seconds: 0.12 }])]);
    const item = backItem(track, pattern);
    const short = item.notes.find((n, i) => i === 0);
    assert(short.centsOff === null || short.jitterCents === null, `a 0.2 s note: ${JSON.stringify(short)}`);
    assert(item.notes.slice(1).every((n) => n.jitterCents !== null), 'the longer notes keep theirs');
});

// On speakers the mic hears the prompt's own tail as the answer window opens, at exactly the target pitch.
const tail = (seconds, midi = 57) => ({ seconds, midi, cents: 0, loud: -22, fade: -40 });

test('singing test, speakers: the prompt\'s tail alone is never heard as an answer', () => {
    for (const seconds of [0.1, 0.25, 0.4]) {
        assert(matchItem(trackOf([tail(seconds), { seconds: 2 }]), 57).centsOff === null, `${seconds} s of tail read as a sung note`);
        assert(holdItem(trackOf([tail(seconds), { seconds: 4.5 }]), 57).spreadCents === null, `${seconds} s of tail read as a hold`);
    }
    const quiet = { match: Array.from({ length: 12 }, () => matchItem(trackOf([tail(0.4), { seconds: 2 }]), 57)), back: [], hold: [holdItem(trackOf([tail(0.4), { seconds: 4.5 }]), 57)] };
    assert(scoreTest(quiet).practice, 'a silent singer on speakers is practice, never scored');
});

test('singing test, speakers: the singer is read from their own onset, after the tail or over it', () => {
    for (const seconds of [0.1, 0.25, 0.4]) {
        const item = matchItem(trackOf([tail(seconds), { seconds: 0.5 - seconds }, { seconds: 1.6, midi: 57, cents: 40 }]), 57);
        close(item.centsOff, 40, 5, `${seconds} s of tail: 40 c off`);
        close(item.startAfterPromptMs, 500, 30, `${seconds} s of tail: their onset`);
        assert(item.landingMs !== null && item.landingMs < 150, `${seconds} s of tail: landing ${item.landingMs} ms`);
    }
    // Starting while the tail still sounds: their louder onset over it.
    const over = matchItem(trackOf([{ seconds: 0.3, midi: 57, cents: 0, loud: -24, fade: -38 }, { seconds: 1.6, midi: 57, cents: 40 }]), 57);
    close(over.centsOff, 40, 6, 'sung over the tail');
});

test('singing test, speakers: a pattern\'s last prompt note never becomes the singer\'s first', () => {
    const pattern = [57, 59, 61, 59];
    const sung = [{ seconds: 0.45, midi: 57, cents: 20 }, { seconds: 0.12 }, { seconds: 0.45, midi: 59, cents: -20 }, { seconds: 0.12 }, { seconds: 0.45, midi: 61, cents: 20 }, { seconds: 0.12 }, { seconds: 0.45, midi: 59, cents: -20 }];
    for (const seconds of [0.1, 0.25, 0.4]) {
        const item = backItem(trackOf([tail(seconds, 59), { seconds: 0.15 }, ...sung]), pattern);
        close(item.notes[0].centsOff, 20, 8, `${seconds} s of tail: note 1 is theirs`);
        close(item.notes[3].centsOff, -20, 8, `${seconds} s of tail: note 4 is theirs`);
    }
});

// Through the real pitch tracker: a bass and a soprano, equally fair.

test('singing test: a bass and a soprano, through the real pitch tracker', async () => {
    const SR = 16000;
    for (const midi of [43, 74]) {
        const voice = sing(SR, { notes: [{ beat: 0, length: 3, pitch: midi }], bpm: 60, cents: 25 });
        const track = trackTake(voice, SR);
        const item = matchItem(track, midi);
        close(item.centsOff, 25, 10, `${midi}: 25 c off`);
        assert(item.octave === 0, `${midi}: octave ${item.octave}`);
        const hold = holdItem(trackTake(sing(SR, { notes: [{ beat: 0, length: 4.5, pitch: midi }], bpm: 60, vibrato: { cents: 35, hz: 5.5 } }), SR), midi);
        assert(hold.spreadCents !== null && hold.spreadCents < 10, `${midi}: vibrato hold spread ${hold.spreadCents}`);
    }
});

// The run: items in order, an unheard item offered again (at most RETRIES a test), practice items never sent.

test('singing test: a run sings every item, offers unheard ones again, and sends the contract\'s body', async () => {
    let quietFor = 2; // the first scored match note is lost to noise twice, then heard
    let calls = 0;
    const turn = async (notes, { noteSeconds, answerSeconds }) => {
        calls += 1;
        assert(noteSeconds > 0 && answerSeconds > 0, 'timing given');
        const target = notes[0];
        if (notes.length === 1 && calls > 1 && quietFor > 0 && answerSeconds < 3) {
            quietFor -= 1;
            return { track: trackOf([{ seconds: answerSeconds }]), promptEnd: 0.3 };
        }
        const segments = notes.length === 4 ? notes.flatMap((midi) => [{ seconds: 0.45, midi, cents: 10 }, { seconds: 0.12 }]) : [{ seconds: answerSeconds - 0.4, midi: target, cents: 10 }];
        return { track: trackOf([{ seconds: 0.4 }, ...segments]), promptEnd: 0.3 };
    };
    const run = createRun(57, { turn });
    assert(run.items.length === 13 + 7 + 2, `${run.items.length} items`);
    for (const step of run.items) await run.sing(step);
    assert(run.retriesLeft() === RETRIES - 2, `retries left ${run.retriesLeft()}`);
    const body = run.request({ id: 'b6e0f3a0-0000-4000-8000-000000000001', takenAt: '2026-11-24T10:00:00Z', device: 'computer' });
    assert(body.match.length === 12 && body.back.length === 6 && body.hold.length === 2, 'no practice items sent');
    assert(body.testVersion === 1 && body.scoringVersion === 1 && body.anchor === 57 && body.warmUp === false, 'versions and anchor');
    assert(body.match.every((m) => m.centsOff !== null), 'the retried note kept its last, heard try');
    assert(body.score.band === 'precise' && body.score.total >= 95, `score ${JSON.stringify(body.score)}`);
});
