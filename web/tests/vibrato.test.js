// Vibrato, measured (js/vibrato.js): each kind told apart on synthetic readings (straight, a natural
// vibrato, a wobble, a tremble, an uneven shake), the tracker's slips and gaps forgiven, the delayed
// bloom found, and the three percents the Vibrato course stars on. Then the same through the real
// pitch tracker, on a sung voice with and without vibrato.
import { bloomPercent, centsOf, freePercent, isNaturalVibrato, kindOf, measureHold, peaksOf, scoreWave, straightPercent, targetAt, targetPlan, wavePercent } from '/js/vibrato.js';
import { guideNotes, measuredLine } from '/js/components/wave-lane.js';
import { renderPart } from '/js/synth.js';
import { trackTake } from '/js/pitchtrack.js';
import { sing } from './fixtures.js';
import { assert, close, test } from './harness.js';

const RATE = 100;

/** Readings of a held note: a wave of `hz` and ± `cents` from `onset` s, noise, jitter and drift. */
function hold({ seconds = 4, hz = 0, cents = 0, onset = 0, noise = 2, jitter = 0, drift = 0, seed = 3 } = {}) {
    let s = seed;
    const random = () => ((s = (s * 16807) % 2147483647) / 2147483647) - 0.5;
    const out = [];
    let phase = 0;
    let rateNow = hz;
    for (let i = 0; i < seconds * RATE; i++) {
        const t = i / RATE;
        if (jitter && i % 10 === 0) rateNow = hz * (1 + jitter * 2 * random());
        phase += rateNow / RATE;
        const wave = t >= onset ? cents * Math.sin(2 * Math.PI * phase) : 0;
        out.push(wave + drift * (t / seconds) + noise * random());
    }
    return out;
}

test('vibrato: a straight tone is straight, and earns its stars', () => {
    const m = measureHold(hold(), RATE);
    assert(m.kind === 'straight', `kind ${m.kind}`);
    const s = straightPercent(hold({ seconds: 5 }), RATE);
    assert(s.percent >= 90, `straight percent ${s.percent}`);
    assert(s.straightSeconds >= 4, `straight for ${s.straightSeconds} s`);
});

test('vibrato: a natural vibrato is measured, its rate, width and evenness', () => {
    const m = measureHold(hold({ hz: 5.5, cents: 40 }), RATE);
    assert(m.kind === 'vibrato', `kind ${m.kind}`);
    close(m.hz, 5.5, 0.3, 'rate');
    close(m.width, 40, 8, 'width (± cents)');
    assert(m.evenness < 0.1, `even: ${m.evenness}`);
    assert(m.onset !== null && m.onset < 0.6, `starts at once: ${m.onset}`);
    assert(isNaturalVibrato(m), 'worth the reveal');
    close(m.centre, 0, 5, 'the centre is the note');
    assert(straightPercent(hold({ hz: 5.5, cents: 40 }), RATE).percent < 30, 'not straight');
});

test('vibrato: wobbles, trembles and uneven shakes are never called vibrato', () => {
    assert(measureHold(hold({ hz: 3, cents: 60 }), RATE).kind === 'wobble', 'slow and wide');
    assert(measureHold(hold({ hz: 5, cents: 130 }), RATE).kind === 'wobble', 'far too wide');
    assert(measureHold(hold({ hz: 8.5, cents: 18 }), RATE).kind === 'tremble', 'fast and narrow');
    for (const seed of [3, 11]) {
        const shaky = measureHold(hold({ hz: 5.5, cents: 40, jitter: 0.8, seed }), RATE);
        assert(shaky.kind === 'uneven' && !isNaturalVibrato(shaky), `uneven cycles (tremor guard): ${shaky.kind}, ${shaky.evenness}`);
    }
    const natural = measureHold(hold({ hz: 5.5, cents: 40, jitter: 0.2 }), RATE);
    assert(natural.kind === 'vibrato', `a real voice's small unevenness is still vibrato: ${natural.evenness}`);
    assert(kindOf({ hz: 6, width: 8, evenness: 0 }) === 'straight', 'a shimmer under ±12 is straight');
    assert(kindOf({ hz: 6, width: 90, evenness: 0.05 }) === 'vibrato', 'wide but natural');
});

test('vibrato: too short or too little heard is unclear; slips and short gaps are forgiven', () => {
    assert(measureHold(hold({ seconds: 0.9 }), RATE).kind === 'unclear', 'too short to judge');
    const slipped = hold();
    for (let i = 150; i < 155; i++) slipped[i] += 1200; // the tracker an octave up for 50 ms
    assert(measureHold(slipped, RATE).kind === 'straight', 'an octave slip is no wave');
    const gap = hold({ hz: 5.5, cents: 40 });
    for (let i = 200; i < 208; i++) gap[i] = null; // 80 ms not heard
    assert(measureHold(gap, RATE).kind === 'vibrato', 'a short gap is bridged');
    const broken = hold();
    for (let i = 150; i < 190; i++) broken[i] = null; // 400 ms: a breath
    assert(measureHold(broken, RATE).kind === 'unclear', 'a real break is not one held note');
    assert(straightPercent(hold({ seconds: 0.9 }), RATE).percent === null, 'no percent for an unclear note');
});

test('vibrato: the bloom is found when the wave starts late, and a straight start still earns stars', () => {
    const bloom = hold({ seconds: 7, hz: 5.5, cents: 35, onset: 2 });
    const m = measureHold(bloom, RATE);
    close(m.onset, 2, 0.35, 'the wave starts');
    const b = bloomPercent(bloom, RATE);
    assert(b.bloomed && b.percent >= 90, `bloomed: ${JSON.stringify({ bloomed: b.bloomed, percent: b.percent })}`);
    const flat = bloomPercent(hold({ seconds: 7 }), RATE);
    assert(!flat.bloomed && flat.percent === 75, `no vibrato yet: two stars, "not yet" (${flat.percent})`);
    const early = bloomPercent(hold({ seconds: 7, hz: 5.5, cents: 35 }), RATE);
    assert(early.bloomed && early.percent < 90, `waving from the start isn't the delayed bloom (${early.percent})`);
});

test('vibrato: a steered wave is scored on its target, with no credit for wider', () => {
    const good = wavePercent(hold({ hz: 3, cents: 40 }), RATE, { hz: 3, cents: 40 });
    assert(good.percent >= 90 && good.tip === null, `on target: ${good.percent}`);
    const wide = wavePercent(hold({ hz: 3, cents: 80 }), RATE, { hz: 3, cents: 40 });
    assert(wide.tip === 'smaller' && wide.percent < good.percent, `wider says smaller: ${wide.percent}`);
    const fast = wavePercent(hold({ hz: 4.5, cents: 40 }), RATE, { hz: 3, cents: 40 });
    assert(fast.tip === 'slower' && fast.percent < 90, `too fast: ${fast.percent}`);
    const none = wavePercent(hold(), RATE, { hz: 3, cents: 40 });
    assert(none.tip === 'wave' && none.percent < 70, `no wave yet: ${none.percent}`);
    const fair = wavePercent(hold({ hz: 3.5, cents: 25, jitter: 0.15 }), RATE, { hz: 3, cents: 40 }, { generous: true });
    assert(fair.percent >= 70, `the first lesson lifts a fair try: ${fair.percent}`);
});

test('vibrato: through the real tracker, a sung vibrato and a sung straight tone', () => {
    const sampleRate = 44100;
    const note = { beat: 0, length: 5, pitch: 57 };
    for (const [vibrato, kind] of [[{ hz: 5.5, cents: 40 }, 'vibrato'], [null, 'straight']]) {
        const audio = sing(sampleRate, { notes: [note], bpm: 60, vibrato, noise: 0.02 });
        const track = trackTake(audio, sampleRate);
        const m = measureHold(centsOf(track, { from: 0, to: 5, midi: 57 }), track.rate);
        assert(m.kind === kind, `${kind}: measured ${m.kind}`);
        if (vibrato) {
            close(m.hz, 5.5, 0.4, 'the sung rate');
            close(m.width, 40, 12, 'the sung width');
        }
    }
});

const LADDER = { tempo: 60, lead: 2, target: [{ seconds: 4, kind: 'wave', hz: 2, cents: 50 }, { seconds: 4, kind: 'wave', hz: 3, cents: 40 }, { seconds: 4, kind: 'wave', hz: 4, cents: 30 }] };

test('vibrato lane: the target is one unbroken wave across a ladder, with its peaks', () => {
    const plan = targetPlan(LADDER);
    assert(plan.start === 2 && plan.end === 14, `sung from 2 to 14 s: ${plan.start} to ${plan.end}`);
    for (const at of [6, 10]) close(targetAt(plan, at - 1e-4).cents, targetAt(plan, at).cents, 1, `no jump at ${at} s`);
    assert(targetAt(plan, 1.9) === null && targetAt(plan, 14.1) === null, 'nothing before or after');
    const peaks = peaksOf(plan);
    assert(peaks.length === 2 * (2 * 4 + 3 * 4 + 4 * 4), `a peak and a trough a cycle: ${peaks.length}`);
    assert(peaks.every((p, i) => i === 0 || Math.sign(p.cents) !== Math.sign(peaks[i - 1].cents)), 'up, down, up');
    close(peaks[0].t, 2.125, 1e-6, 'the first peak, a quarter cycle in');
    const ghost = targetPlan({ tempo: 60, lead: 2, target: [{ seconds: 6, kind: 'wave', hz: 4, cents: 30, fade: 2 }] });
    assert(peaksOf(ghost).every((p) => p.t < 4), 'the ghost wave\'s gems stop as it fades');
    close(targetAt(ghost, 3).fade, 0.5, 1e-6, 'half faded after a second');
});

/** The guide singing a plan, through the real tracker: what a singer following it perfectly would give. */
function sungAs(exercise, midi = 57, sampleRate = 44100) {
    const plan = targetPlan(exercise);
    const audio = renderPart(guideNotes(plan, midi), { bpm: exercise.tempo, sampleRate });
    return { plan, track: trackTake(audio, sampleRate) };
}

test('vibrato lane: a take that follows the target scores high; the wrong kind of take scores low', () => {
    const { plan, track } = sungAs(LADDER);
    const good = scoreWave(track, plan, { midi: 57 });
    assert(good.percent >= 85, `the ladder followed: ${good.percent} (${good.parts.map((p) => p.percent).join(', ')})`);
    const straight = sungAs({ tempo: 60, lead: 2, target: [{ seconds: 6, kind: 'straight' }] });
    const s = scoreWave(straight.track, straight.plan, { midi: 57 });
    assert(s.percent >= 90 && measuredLine(s.parts[0], 6).values.total !== undefined, `straight held: ${s.percent}`);
    // A straight tone where a wave was asked for.
    const wrong = scoreWave(straight.track, targetPlan({ tempo: 60, lead: 2, target: [{ seconds: 6, kind: 'wave', hz: 3, cents: 40 }] }), { midi: 57 });
    assert(wrong.percent < 70 && wrong.tip === 'wave', `no wave: ${wrong.percent}, ${wrong.tip}`);
    // The right shape on the wrong note costs.
    const off = scoreWave(straight.track, straight.plan, { midi: 59 });
    assert(off.percent < s.percent, `a whole tone off: ${off.percent}`);
    const bloom = sungAs({ tempo: 60, lead: 2, target: [{ seconds: 8, kind: 'bloom', straight: 2 }] });
    const b = scoreWave(bloom.track, bloom.plan, { midi: 57 });
    assert(b.parts[0].bloomed && b.percent >= 90, `the bloom: ${b.percent}`);
    const free = sungAs({ tempo: 60, lead: 2, target: [{ seconds: 6, kind: 'free' }] });
    const f = scoreWave(free.track, free.plan, { midi: 57 });
    assert(f.percent >= 90 && f.parts[0].measure.kind === 'vibrato' && measuredLine(f.parts[0], 6).values.rate !== undefined, `a natural hold: ${f.percent}, ${f.parts[0].measure.kind}`);
    assert(freePercent(Array(100).fill(null), 100).percent === null, 'nothing heard: no percent');
});

test('vibrato lane: a rung marked scored: false is shown but never starred', () => {
    const plan = targetPlan({ tempo: 60, lead: 2, target: [{ seconds: 4, kind: 'straight' }, { seconds: 4, kind: 'wave', hz: 5, cents: 20, scored: false }] });
    const { track } = sungAs({ tempo: 60, lead: 2, target: [{ seconds: 8, kind: 'straight' }] });
    const score = scoreWave(track, plan, { midi: 57 });
    assert(score.parts.length === 2 && score.parts[1].percent < 70, 'the rung was measured (straight, not a wave)');
    assert(score.percent >= 90, `but only the straight part counts: ${score.percent}`);
});

test('vibrato lane: a contour (path) is ridden or not, and its line says how much', () => {
    const SIREN = { tempo: 60, lead: 2, target: [{ seconds: 4, kind: 'path', points: [[0, 0], [1.5, 5], [2.5, 5], [4, 0]] }] };
    const { plan, track } = sungAs(SIREN);
    const followed = scoreWave(track, plan, { midi: 57 });
    assert(followed.percent >= 85, `the siren followed: ${followed.percent}`);
    assert(measuredLine(followed.parts[0], 4).values.percent !== undefined, 'the line says how much of it was ridden');
    const flat = sungAs({ tempo: 60, lead: 2, target: [{ seconds: 4, kind: 'straight' }] });
    const missed = scoreWave(flat.track, plan, { midi: 57 });
    assert(missed.percent < 70, `a straight note on a siren: ${missed.percent}`);
    close(targetAt(plan, 2 + 2).cents, 500, 1, 'the top of the siren, a fourth up');
});
