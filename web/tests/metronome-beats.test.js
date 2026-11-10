// The free metronome's beats (js/public/metronome-beats.js): accents, groupings, subdivisions, the
// count-in, the speed trainer, silent bars, the timer, tempo names and tap tempo.
import { defaultAccents, defaultGroups, groupingsOf, markingOf, MARKINGS, nextAccent, nextStep, startPosition, tap, tapTempo, tempoAt } from '/js/public/metronome-beats.js';
import { assert, close, test } from './harness.js';

/** The first `count` steps as compact strings: "A" accent, "b" beat, "s" sub, "." silent. */
function play(settings, count) {
    let pos = startPosition();
    const out = [];
    for (let i = 0; i < count; i++) {
        const step = nextStep(pos, settings);
        out.push(step);
        pos = step.pos;
    }
    return out;
}
const letters = (steps) => steps.map((s) => (s.sound === 'accent' ? 'A' : s.sound === 'beat' ? 'b' : s.sound === 'sub' ? 's' : '.')).join('');
const base = { beats: 4, unit: 4, bpm: 120, subdivision: 'none', accents: defaultAccents(4), countIn: 0 };

test('metronome beats: 4/4 accents the one, a tapped dot goes silent, and every gap is the beat', () => {
    const steps = play(base, 8);
    assert(letters(steps) === 'AbbbAbbb', letters(steps));
    assert(steps.every((s) => s.gap === 0.5), 'half a second at 120');
    assert(steps.map((s) => s.beat).join() === '0,1,2,3,0,1,2,3', 'dots');
    const quiet = play({ ...base, accents: ['accent', 'normal', 'silent', 'accent'] }, 4);
    assert(letters(quiet) === 'Ab.A', letters(quiet));
    assert(nextAccent('accent') === 'normal' && nextAccent('normal') === 'silent' && nextAccent('silent') === 'accent', 'the cycle');
});

test('metronome beats: subdivisions fill each beat, quieter, with no dot of their own', () => {
    const triplets = play({ ...base, subdivision: 'triplets', beats: 2, accents: defaultAccents(2) }, 6);
    assert(letters(triplets) === 'Assbss', letters(triplets));
    close(triplets[0].gap, 0.5 / 3, 1e-9, 'a triplet');
    assert(triplets[1].beat === null && triplets[3].beat === 1 && triplets[1].level < triplets[3].level, 'subs are quieter');
});

test('metronome beats: 7/8 groups as 2+2+3, a group\'s first beat stronger; odd metres offer their rotations', () => {
    assert(defaultGroups(7, 8).join('+') === '2+2+3' && defaultGroups(6, 8).join('+') === '3+3' && defaultGroups(5, 8).join('+') === '3+2', 'groups');
    assert(defaultGroups(4, 4).join() === '1,1,1,1', 'quarter metres beat by beat');
    assert(groupingsOf(7, 8).map((g) => g.join('+')).join(' ') === '2+2+3 2+3+2 3+2+2', groupingsOf(7, 8).map((g) => g.join('+')).join(' '));
    const steps = play({ ...base, beats: 7, unit: 8, accents: defaultAccents(7), groups: [2, 2, 3] }, 7);
    assert(steps[2].level === 1 && steps[4].level === 1 && steps[1].level < 1 && steps[6].level < 1, `levels ${steps.map((s) => s.level)}`);
});

test('metronome beats: a bar of count-in, then the bar as set; the trainer speeds up; silent bars mute', () => {
    const counted = play({ ...base, subdivision: 'eighths', countIn: 1 }, 6);
    assert(letters(counted) === 'Abbb' + 'As', letters(counted));
    assert(counted[0].countIn && counted[0].bar === -1 && !counted[4].countIn && counted[4].bar === 0, 'count-in bars');
    assert(counted[0].gap === 0.5 && counted[4].gap === 0.25, 'the count-in has no subdivisions');
    const trainer = { ...base, trainer: { on: true, add: 5, every: 2, max: 130 } };
    assert(tempoAt(trainer, 0) === 120 && tempoAt(trainer, 2) === 125 && tempoAt(trainer, 4) === 130 && tempoAt(trainer, 40) === 130, 'adds 5 every 2 bars, to 130');
    const silent = play({ ...base, silent: { on: true, play: 1, mute: 1 } }, 12);
    assert(letters(silent) === 'Abbb....Abbb', letters(silent));
});

test('metronome beats: the timer ends it after its minutes', () => {
    const steps = play({ ...base, bpm: 60, minutes: 1 }, 62);
    assert(!steps[59].end && steps[60].end, `ends at a minute: ${steps.findIndex((s) => s.end)}`);
});

test('metronome beats: Italian names for every tempo, tap to jump inside its own range', () => {
    assert(markingOf(100).name === 'Andante' && markingOf(20).name === 'Grave' && markingOf(300).name === 'Prestissimo', 'names');
    for (const m of MARKINGS) assert(markingOf(m.jump).name === m.name, `${m.name} jumps to its own range`);
});

test('metronome beats: tap tempo shows after three taps, settles, ignores one odd tap, and resets after a pause', () => {
    let t = tapTempo();
    let bpm = null;
    for (const at of [0, 0.5]) ({ state: t, bpm } = tap(t, at));
    assert(bpm === null, 'not yet');
    ({ state: t, bpm } = tap(t, 1.0));
    assert(bpm === 120, `three taps: ${bpm}`);
    for (const at of [1.5, 2.1, 2.5]) ({ state: t, bpm } = tap(t, at));
    assert(bpm === 120, `one late tap doesn't move it: ${bpm}`);
    ({ state: t, bpm } = tap(t, 6));
    assert(bpm === null && t.taps.length === 1, 'a pause starts again');
});

test('metronome beats: a change of metre or subdivision mid-bar carries on from the next beat', () => {
    let pos = startPosition();
    for (let i = 0; i < 6; i++) pos = nextStep(pos, { ...base, beats: 7, accents: defaultAccents(7) }).pos; // on beat 6 of 7
    const step = nextStep(pos, base); // now 4/4
    assert(step.beat === 0 && step.sound === 'accent' && step.bar === 1, `a new bar: ${JSON.stringify(step)}`);
    let sub = startPosition();
    sub = nextStep(sub, { ...base, subdivision: 'sixteenths' }).pos;
    sub = nextStep(sub, { ...base, subdivision: 'sixteenths' }).pos;
    sub = nextStep(sub, { ...base, subdivision: 'sixteenths' }).pos; // on the third sixteenth
    const next = nextStep(sub, { ...base, subdivision: 'eighths' });
    assert(next.beat === 1 && next.sound === 'beat', `the next beat: ${JSON.stringify(next)}`);
});

test('metronome: a tempo handed over above 300 (tap tempo\'s Play in metronome) opens the tempos up to 400', async () => {
    const { mountMetronome } = await import('/js/public/tools/metronome.js');
    const STORE = 'stackapella.tools.metronome';
    const saved = localStorage.getItem(STORE);
    localStorage.removeItem(STORE);
    const el = document.createElement('div');
    document.body.append(el);
    try {
        const fast = mountMetronome(el, { bpm: 350 });
        assert(fast.settings.bpm === 350 && fast.settings.extended, `350 kept, extended: ${JSON.stringify({ bpm: fast.settings.bpm, extended: fast.settings.extended })}`);
        localStorage.removeItem(STORE);
        const usual = mountMetronome(el, { bpm: 120 });
        assert(usual.settings.bpm === 120 && !usual.settings.extended, 'a usual tempo leaves the range as it was');
        const wild = mountMetronome(el, { bpm: 900 });
        assert(wild.settings.bpm === 400, `clamped to 400: ${wild.settings.bpm}`);
    } finally {
        el.remove();
        if (saved === null) localStorage.removeItem(STORE);
        else localStorage.setItem(STORE, saved);
    }
});
