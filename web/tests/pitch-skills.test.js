// Pitch skills, measured (js/pitch-skills.js): a clean landing told from a scoop and a fall, its
// cents read from the settled start, an octave down not held against anyone; a hold's lock with a
// natural vibrato forgiven; a long note's sag; a note from memory; then the same through the real
// pitch tracker on a sung voice.
import { centre, drift, holdGoal, landing, landingPercent, lock, lockable, memory, release, skillPercent, sustain } from '/js/pitch-skills.js';
import { trackTake } from '/js/pitchtrack.js';
import { sing } from './fixtures.js';
import { assert, close, test } from './harness.js';

const RATE = 100;

/** Readings: `silent` s of nothing, then `slide` cents falling to `cents` over `slideMs`, a wave, noise and drift. */
function note({ seconds = 2, silent = 0.25, cents = 0, slide = 0, slideMs = 0, vibrato = null, drift: sag = 0, noise = 2, seed = 5 } = {}) {
    let s = seed;
    const random = () => ((s = (s * 16807) % 2147483647) / 2147483647) - 0.5;
    const out = [];
    for (let i = 0; i < silent * RATE; i++) out.push(null);
    for (let i = 0; i < seconds * RATE; i++) {
        const t = i / RATE;
        let c = cents + sag * (t / seconds) + noise * random();
        if (slideMs && t * 1000 < slideMs) c += slide * (1 - (t * 1000) / slideMs);
        if (vibrato) c += vibrato.cents * Math.sin(2 * Math.PI * vibrato.hz * t);
        out.push(c);
    }
    return out;
}

test('pitch skills: a clean landing, its cents from the settled start', () => {
    const l = landing(note({ cents: 18 }), RATE);
    assert(l.start === 'clean', `start ${l.start}`);
    close(l.cents, 18, 4, 'cents');
    assert(l.after <= 0.06, `after ${l.after}`);
});

test('pitch skills: a scoop up and a fall down are told apart, and cost half', () => {
    const scoop = landing(note({ slide: -250, slideMs: 200 }), RATE);
    assert(scoop.start === 'scoop', `scoop start ${scoop.start}`);
    close(scoop.cents, 0, 8, 'scoop landed');
    const fall = landing(note({ slide: 200, slideMs: 180 }), RATE);
    assert(fall.start === 'fall', `fall start ${fall.start}`);
    assert(landingPercent([scoop]) === 50, `scooped percent ${landingPercent([scoop])}`);
    assert(landingPercent([landing(note(), RATE)]) === 100, 'clean on the note is full credit');
});

test('pitch skills: a quick, tiny settle is still clean', () => {
    const l = landing(note({ slide: -40, slideMs: 120 }), RATE);
    assert(l.start === 'clean', `start ${l.start}`);
});

test('pitch skills: an octave down lands on the note, and a slide that never settles says so', () => {
    close(landing(note({ cents: -1205 }), RATE).cents, -5, 4, 'octave folded');
    const glide = [...Array(25).fill(null), ...Array.from({ length: 150 }, (_, i) => -300 + i * 4)];
    assert(landing(glide, RATE).start === 'slide', 'never settled');
    assert(landing(Array(50).fill(null), RATE) === null, 'nothing heard');
});

test('pitch skills: landing credit is full to 25 cents, two stars to 35, none at a semitone, and unheard costs nothing', () => {
    const at = (cents) => landingPercent([{ cents, start: 'clean' }]);
    assert(at(25) === 100 && at(-20) === 100, 'within 25');
    assert(at(30) === 80 && at(-35) === 80, 'within 35');
    close(at(67), 41, 2, 'halfway down');
    assert(at(120) === 0, 'past a semitone');
    assert(landingPercent([{ cents: 33, start: 'clean' }], { generous: true }) === 100, 'the first lesson gives full credit to 35');
    assert(landingPercent([null, { cents: 0, start: 'clean' }]) === 100, 'an unheard note is skipped');
    assert(landingPercent([null]) === null, 'all unheard');
    assert(landingPercent([{ cents: 90, start: 'scoop' }], { generous: true }) >= 35, 'generous lifts a fair try');
    assert(landingPercent([{ cents: 60, start: 'clean' }], { named: true }) === 0 && at(60) > 0, 'named: nearer another semitone is a miss');
});

test('pitch skills: a locked hold, with a natural vibrato forgiven', () => {
    const steady = lock(note({ seconds: 6, silent: 0, cents: 4 }), RATE);
    assert(steady.percent >= 95, `steady ${steady.percent}`);
    assert(steady.longest >= 5, `longest ${steady.longest}`);
    const waving = lock(note({ seconds: 6, silent: 0, vibrato: { hz: 5.5, cents: 40 } }), RATE);
    assert(waving.percent >= 80, `vibrato on the centre ${waving.percent}`);
    const off = lock(note({ seconds: 6, silent: 0, cents: 30 }), RATE);
    assert(off.percent < 70 && off.percent > 0, `30 cents off ${off.percent}`);
});

test('pitch skills: a sagging long note is caught, a steady one is not', () => {
    const sag = drift(note({ seconds: 8, silent: 0, drift: -45 }), RATE);
    assert(sag.drift < -25, `sag ${sag.drift}`);
    assert(sag.percent < 75, `sag percent ${sag.percent}`);
    const steady = drift(note({ seconds: 8, silent: 0 }), RATE);
    assert(steady.percent >= 95, `steady ${steady.percent}`);
    assert(drift(note({ seconds: 2, silent: 0 }), RATE).percent === null, 'too short to judge');
});

test('pitch skills: a third is judged on its centre, never on lock to a drone', () => {
    assert(lockable(0) && lockable(7) && lockable(-5) && lockable(12), 'do and so lock');
    assert(!lockable(4) && !lockable(-3) && !lockable(2), 'mi, la and re do not');
    const justThird = centre(note({ seconds: 3, silent: 0, cents: -14 }), RATE);
    assert(justThird.percent === 100, `a just third is fine on centre ${justThird.percent}`);
    const sampleRate = 44100;
    const notes = [{ beat: 0, length: 3, pitch: 57 }, { beat: 3, length: 3, pitch: 61 }];
    const take = trackTake(sing(sampleRate, { notes, bpm: 60, cents: (n) => (n.pitch === 61 ? -14 : 2) }), sampleRate);
    const judged = skillPercent('lock', take, notes, 1, { steps: [0, 4] });
    assert(judged.percent >= 90, `do locked and a just mi on centre ${judged.percent}`);
});

test('pitch skills: a note from memory, never wrong', () => {
    assert(memory(note({ cents: -90 }), RATE).percent === 100, 'within a semitone');
    assert(memory(note({ cents: 150 }), RATE).percent === 80, 'within two semitones');
    assert(memory(note({ cents: 350 }), RATE).percent === 50, 'far still earns a star');
    assert(memory(note({ cents: -1210 }), RATE).percent === 100, 'an octave down is the note');
});

test('pitch skills: through the real tracker, a clean echo and a scooped one', () => {
    const sampleRate = 44100;
    const notes = [{ beat: 1, length: 1.5, pitch: 57 }, { beat: 3, length: 1.5, pitch: 62 }, { beat: 5, length: 1.5, pitch: 64 }];
    const clean = skillPercent('landing', trackTake(sing(sampleRate, { notes, bpm: 60 }), sampleRate), notes, 1);
    assert(clean.notes.every((l) => l.start === 'clean'), `clean starts ${clean.notes.map((l) => l.start)}`);
    assert(clean.percent >= 90, `clean percent ${clean.percent}`);
    const scooped = skillPercent('landing', trackTake(sing(sampleRate, { notes, bpm: 60, scoop: { ms: 220, cents: -300 } }), sampleRate), notes, 1);
    assert(scooped.notes.filter((l) => l.start === 'scoop').length >= 2, `scoops ${scooped.notes.map((l) => l.start)}`);
    assert(scooped.percent < clean.percent - 25, `scooped ${scooped.percent} vs ${clean.percent}`);
});

test('pitch skills: through the real tracker, a hold locked and sagging', () => {
    const sampleRate = 44100;
    const notes = [{ beat: 0, length: 6, pitch: 57 }];
    const locked = skillPercent('lock', trackTake(sing(sampleRate, { notes, bpm: 60, cents: 3 }), sampleRate), notes, 1);
    assert(locked.percent >= 90, `locked ${locked.percent}`);
    const flat = skillPercent('lock', trackTake(sing(sampleRate, { notes, bpm: 60, cents: -35 }), sampleRate), notes, 1);
    assert(flat.percent < 60, `35 flat ${flat.percent}`);
});

/** A hold's readings: steady for `seconds`, then the last second sagging or wobbling, then silence. */
function holdThen({ seconds = 10, ending = 'steady', silent = 0.25, after = 1 } = {}) {
    const out = note({ seconds: seconds - 1, silent });
    for (let i = 0; i < RATE; i++) {
        const t = i / RATE;
        out.push(ending === 'sagged' ? -60 * t : ending === 'wobbled' ? 70 * Math.sin(2 * Math.PI * 3 * t) : 0);
    }
    for (let i = 0; i < after * RATE; i++) out.push(null);
    return out;
}

test('pitch skills: a long note that stops while steady is the win, and going past the goal earns nothing more', () => {
    const easy = sustain(holdThen({ seconds: 10 }), RATE, { goal: 8 });
    assert(easy.tail === 'steady', `tail ${easy.tail}`);
    close(easy.held, 10, 0.1, 'held');
    assert(easy.steady >= 9, `steady ${easy.steady}`);
    assert(easy.percent >= 95, `percent ${easy.percent}`);
    const longer = sustain(holdThen({ seconds: 14 }), RATE, { goal: 8 });
    assert(longer.percent <= easy.percent + 1, `past the goal: ${longer.percent} vs ${easy.percent}`);
});

test('pitch skills: a long note that ran out of air sags or wobbles at its end, and costs most', () => {
    const sagged = sustain(holdThen({ ending: 'sagged' }), RATE, { goal: 8 });
    assert(sagged.tail === 'sagged', `sagged tail ${sagged.tail}`);
    const wobbled = sustain(holdThen({ ending: 'wobbled' }), RATE, { goal: 8 });
    assert(wobbled.tail === 'wobbled', `wobbled tail ${wobbled.tail}`);
    const steady = sustain(holdThen({}), RATE, { goal: 8 });
    assert(sagged.percent < steady.percent - 25 && wobbled.percent < steady.percent - 25, `ending costs: ${sagged.percent}, ${wobbled.percent} vs ${steady.percent}`);
});

test('pitch skills: a hold ends at its first real gap, and a goal grows from your own hold, never past 15 seconds', () => {
    const readings = [...holdThen({ seconds: 6, after: 0.5 }), ...note({ seconds: 4, silent: 0 })];
    close(sustain(readings, RATE).held, 6, 0.1, 'the second sound is another note');
    const short = sustain(holdThen({ seconds: 6 }), RATE, { goal: 12 });
    const met = sustain(holdThen({ seconds: 13 }), RATE, { goal: 12 });
    assert(short.percent < met.percent, `the goal counts: ${short.percent} vs ${met.percent}`);
    assert(holdGoal(4, 2) === 6 && holdGoal(9.4, 2) === 11.4 && holdGoal(14, 6) === 15 && holdGoal(undefined) === null, 'goals from your own hold, never past 15, none without one');
    assert(sustain(Array(200).fill(null), RATE).percent === null, 'nothing held');
});

test('pitch skills: a natural vibrato to the end of a long note is steady, not running out of air', () => {
    const readings = [...note({ seconds: 10, vibrato: { hz: 5.5, cents: 40 } }), ...Array(RATE).fill(null)];
    const held = sustain(readings, RATE, { goal: 8 });
    assert(held.tail === 'steady', `tail ${held.tail}`);
    assert(held.steady >= 8, `steady ${held.steady}`);
});

test('pitch skills: a phrase is starred on its landings and on its last note ending steady', () => {
    const sampleRate = 44100;
    const notes = [{ beat: 1, length: 1, pitch: 60 }, { beat: 2, length: 1, pitch: 64 }, { beat: 3, length: 4, pitch: 67 }];
    const take = trackTake(sing(sampleRate, { notes, bpm: 60 }), sampleRate);
    const phrase = skillPercent('line', take, notes, 1);
    assert(phrase.tail === 'steady', `tail ${phrase.tail}`);
    assert(phrase.percent >= 90, `percent ${phrase.percent}`);
});

test('pitch skills: a note that stops on time and steady is a good release; stopping early, or sagging, is not', () => {
    const onTime = release(holdThen({ seconds: 8, silent: 0 }), RATE, 8);
    assert(Math.abs(onTime.off) <= 0.12 && onTime.tail === 'steady' && onTime.percent >= 95, `on time ${JSON.stringify(onTime)}`);
    const early = release(holdThen({ seconds: 6.5, silent: 0, after: 2.5 }), RATE, 8);
    close(early.off, -1.5, 0.05, 'stopped a second and a half early');
    assert(early.percent <= 40, `early ${early.percent}`);
    const sagged = release(holdThen({ seconds: 8, silent: 0, ending: 'sagged' }), RATE, 8);
    assert(sagged.tail === 'sagged' && sagged.percent < onTime.percent - 25, `sagged ${sagged.percent}`);
});

test('pitch skills: joined notes each land on their own pitch, never on the note before', () => {
    const sampleRate = 44100;
    const notes = [{ beat: 1, length: 1, pitch: 60 }, { beat: 2, length: 1, pitch: 62 }, { beat: 3, length: 1.5, pitch: 64 }];
    const take = trackTake(sing(sampleRate, { notes, bpm: 72 }), sampleRate);
    const result = skillPercent('landing', take, notes, 60 / 72);
    assert(result.notes.every((l) => Math.abs(l.cents) <= 25), `landings ${result.notes.map((l) => l.cents)}`);
    assert(result.percent >= 90, `percent ${result.percent}`);
});

test('pitch skills: back in after a breath, on time and on pitch, and late entries cost', () => {
    const sampleRate = 44100;
    const notes = [{ beat: 1, length: 1.5, pitch: 60 }, { beat: 3, length: 1.5, pitch: 62 }, { beat: 4.5, length: 1, pitch: 64 }];
    const onTime = skillPercent('entry', trackTake(sing(sampleRate, { notes, bpm: 80 }), sampleRate), notes, 60 / 80);
    assert(onTime.notes[2] === null, 'a joined note is no entry');
    assert(onTime.notes[1].percent >= 90 && Math.abs(onTime.notes[1].off) <= 0.08, `on time ${JSON.stringify(onTime.notes[1])}`);
    const late = skillPercent('entry', trackTake(sing(sampleRate, { notes, bpm: 80, lateMs: 250 }), sampleRate), notes, 60 / 80);
    assert(late.notes[1].off > 0.15 && late.percent < onTime.percent - 20, `late ${late.percent} vs ${onTime.percent}`);
});

test('pitch skills: a leap asked for by name must be sung, in its own octave', () => {
    assert(landingPercent([{ cents: 0, start: 'clean' }, null], { named: true }) === 50, 'an unsung leap is a miss');
    assert(landingPercent([{ cents: 0, start: 'clean' }, null]) === 100, 'elsewhere an unheard note costs nothing');
    const octaveDown = note({ cents: -1200 });
    assert(Math.abs(landing(octaveDown, RATE).cents) < 10, 'folded: an octave down is the note');
    assert(landing(octaveDown, RATE, { exact: true }).cents < -1100, 'exact: an octave down is an octave off');
});

test('pitch skills: short notes in a hold step are judged on their landing, and a tune from memory from its first note', () => {
    const sampleRate = 44100;
    const notes = [{ beat: 1, length: 0.5, pitch: 60 }, { beat: 1.5, length: 0.5, pitch: 64 }, { beat: 2, length: 2, pitch: 67 }];
    const short = skillPercent('centre', trackTake(sing(sampleRate, { notes, bpm: 72 }), sampleRate), notes, 60 / 72);
    assert(short.notes.every((m) => m.percent !== null), `every note judged ${short.notes.map((m) => m.percent)}`);
    const off = skillPercent('cold', trackTake(sing(sampleRate, { notes, bpm: 72, cents: 60 }), sampleRate), notes, 60 / 72);
    assert(off.percent >= 90, `a tune true to a do 60 cents off ${off.percent}`);
});

test('pitch skills: a deliberate stop with a natural release still ends steady', () => {
    // Steady, then the last 0.15 s falls 80 cents as the voice lets go (as real voices do).
    const readings = note({ seconds: 8, silent: 0 });
    for (let i = 0; i < 15; i++) readings.push(-80 * (i / 15));
    for (let i = 0; i < RATE; i++) readings.push(null);
    const held = sustain(readings, RATE);
    assert(held.tail === 'steady', `tail ${held.tail}`);
});

test('pitch skills: an entry after a breath is judged against your own usual timing, and singing through the breath earns nothing', () => {
    const sampleRate = 44100;
    const notes = [{ beat: 1, length: 1.5, pitch: 60 }, { beat: 3, length: 1.5, pitch: 62 }, { beat: 5, length: 1.5, pitch: 64 }];
    const usuallyLate = skillPercent('entry', trackTake(sing(sampleRate, { notes, bpm: 80, lateMs: (n) => (n.pitch === 60 ? 0 : 70) }), sampleRate), notes, 60 / 80);
    assert(usuallyLate.notes[0] === null, 'the first note is no entry');
    assert(usuallyLate.percent >= 90, `a steady 70 ms late is your own timing ${usuallyLate.percent}`);
    const joined = [{ beat: 1, length: 2, pitch: 60 }, { beat: 3.5, length: 1.5, pitch: 62 }];
    const through = skillPercent('entry', trackTake(sing(sampleRate, { notes: [{ beat: 1, length: 4, pitch: 60 }], bpm: 80 }), sampleRate), joined, 60 / 80);
    assert(through.notes[1].through && through.percent === 0, `sung through the breath ${JSON.stringify(through.notes[1])}`);
});

test('pitch skills: a tune from memory with only its first note sung is mostly missed', () => {
    const sampleRate = 44100;
    const notes = [{ beat: 1, length: 1, pitch: 60 }, { beat: 2, length: 1, pitch: 64 }, { beat: 3, length: 1, pitch: 67 }];
    const only = skillPercent('cold', trackTake(sing(sampleRate, { notes: [notes[0]], bpm: 72 }), sampleRate), notes, 60 / 72);
    assert(only.percent <= 34, `only do ${only.percent}`);
});
