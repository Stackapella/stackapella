// The legato measure (js/lesson-scores.js `score: "legato"`, Classical singing basics): joins judged by the voice's
// loudness at each change, on the app's own voice run through the app's own tracker, as a take is.
import { LEGATO_DIP_DB, legatoJoins, PLAY_SCORERS } from '/js/lesson-scores.js';
import { trackTake } from '/js/pitchtrack.js';
import { renderPart } from '/js/synth.js';
import { layout, scorePlayTake } from '/js/components/exercise.js';
import { NATURAL, sing } from './fixtures/singer.js';
import { assert, test } from './harness.js';

const SR = 48000;
const BPM = 80;
const BS = 60 / BPM;

/** A line of `steps` from `base`, a beat each, joined, or with a `gap` (seconds) of silence before each change. */
function take(steps, base, gap = 0, gainDb = null) {
    const notes = steps.map((s, i) => ({ beat: i, length: 1, pitch: base + s }));
    const sung = notes.map((n) => ({ ...n, length: n.length - gap / BS }));
    const samples = renderPart(sung, { bpm: BPM, sampleRate: SR, human: 'guide', seed: 5 });
    // Dynamics: the gain in dB at each moment (seconds), put on the sung voice.
    if (gainDb) for (let i = 0; i < samples.length; i++) samples[i] *= 10 ** (gainDb(i / SR) / 20);
    return { notes, track: trackTake(samples, SR) };
}

test('legato: a joined line keeps every join, low voices too, though the tracker reads nothing for a moment at a change', () => {
    for (const base of [43, 48, 57, 64]) {
        for (const steps of [[0, 2, 4, 5, 7, 5, 4, 2, 0], [0, 5, 0, 5, 0], [12, 0, 10, 2]]) {
            const { notes, track } = take(steps, base);
            const broken = legatoJoins(track, notes, BS).filter((j) => j.broken).length;
            assert(broken === 0, `base ${base}, ${steps.join(' ')}: ${broken} joins read as broken`);
        }
    }
});

test(`legato: a line with 100 ms of silence before each change breaks every join (the voice dips ${LEGATO_DIP_DB} dB or more)`, () => {
    for (const base of [43, 57, 64]) {
        const { notes, track } = take([0, 2, 4, 5, 7], base, 0.1);
        const joins = legatoJoins(track, notes, BS);
        assert(joins.length === 4 && joins.every((j) => j.broken), `base ${base}: ${joins.filter((j) => j.broken).length} of ${joins.length} broken`);
    }
});

test('legato: pitch carries 70% and the joins 30% (15% through a speaker); a joined take scores as its pitch does', () => {
    const exercise = { notes: [0, 2, 4, 5, 7].map((step, i) => ({ beat: i, length: 1, step })), score: 'legato' };
    const at = (gap, speaker = false) => {
        const { notes, track } = take([0, 2, 4, 5, 7], 57, gap);
        return PLAY_SCORERS.legato({ track, notes, beatSeconds: BS, exercise, speaker });
    };
    const joined = at(0);
    const broken = at(0.1);
    assert(joined.percent >= 90 && joined.line.key === 'skills.legato.kept', `joined: ${joined.percent} ${joined.line.key}`);
    assert(broken.line.key === 'skills.legato.broken' && broken.line.values.broken === '4', `broken: ${JSON.stringify(broken.line)}`);
    assert(broken.percent <= joined.percent - 25 && broken.percent >= 60, `every join broken costs the joins' 30%: ${broken.percent} against ${joined.percent}`);
    assert(at(0.1, true).percent > broken.percent, 'through a speaker the joins weigh half');
    // A track without loudness (a synthetic one): a gap of readings over 60 ms breaks a join.
    const { notes } = take([0, 2], 57);
    const rate = 100;
    const hz = new Float32Array(2 * BS * rate + 20).fill(220);
    for (let k = Math.round((BS - 0.04) * rate); k < Math.round((BS + 0.05) * rate); k++) hz[k] = 0;
    assert(legatoJoins({ rate, hz }, notes, BS)[0].broken, '90 ms with no readings, no loudness to go by: broken');
});

test('legato: the scoring guard\'s takes, on a joined line through the lesson\'s own path: as asked and with a vibrato earn three stars, 100 ms gaps don\'t', () => {
    const exercise = { tempo: 72, turn: false, score: 'legato', notes: [0, 2, 4, 5, 7, 5, 4, 2, 0].map((step, i) => ({ beat: i, length: 1, step })) };
    const laid = layout(exercise, 50);
    const bs = 60 / exercise.tempo;
    const percent = (o) => scorePlayTake({ track: sing(laid.target, bs, laid.end * bs + 0.5, o), laid, exercise, beatSeconds: bs, strictness: 'standard' }).percent;
    assert(percent({}) >= 90, `as asked: ${percent({})}`);
    assert(percent({ vib: NATURAL }) >= 90, `with a natural vibrato: ${percent({ vib: NATURAL })}`);
    assert(percent({ gap: 0.1 }) < 90, `100 ms gaps at every join: ${percent({ gap: 0.1 })}`);
});

test('legato: dynamics keep their joins: a 15 dB diminuendo across a joined line, and a soft last phrase 15 dB down', () => {
    const steps = [0, 2, 4, 5, 7, 5, 4, 2, 0];
    const span = steps.length * BS;
    const fading = take(steps, 50, 0, (t) => -15 * Math.min(1, t / span));
    assert(legatoJoins(fading.track, fading.notes, BS).every((j) => !j.broken), 'a diminuendo read as broken joins');
    // A soft last phrase (its three notes 15 dB down): against the whole take's level, mostly loud, every soft join
    // sat 15 dB under it and read as broken.
    const subito = take(steps, 50, 0, (t) => (t < 6 * BS ? 0 : -15));
    assert(legatoJoins(subito.track, subito.notes, BS).every((j) => !j.broken), 'a soft last phrase read as broken joins');
    // A real break inside the soft passage is still a break: each join against the softer of its two notes.
    const soft = take(steps, 50, 0.1, (t) => -15 * Math.min(1, t / span));
    assert(legatoJoins(soft.track, soft.notes, BS).every((j) => j.broken), '100 ms breaks in a diminuendo went unheard');
});
