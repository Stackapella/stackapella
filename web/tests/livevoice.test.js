// Your voice live (js/livevoice.js): a steady line that holds (never drops) through an unclear
// moment, a lone jump held back, another octave folded onto your note, and a note lit only within
// the scorer's tolerance.
import { follow, hintStep, voiceFollower } from '/js/livevoice.js';
import { assert, test } from './harness.js';

const hz = (midi) => 440 * 2 ** ((midi - 69) / 12);

test('livevoice: on the note lights it; an unclear reading holds the line where it was', () => {
    let s = voiceFollower();
    const g4 = { beat: 4, pitch: 67 };
    s = follow(s, { seconds: 1.0, hz: hz(67.2) }, g4);
    s = follow(s, { seconds: 1.03, hz: 0 }, g4);
    assert(s.points.length === 2 && Math.abs(s.points[1].midi - 67.2) < 1e-6, `held, not dropped: ${JSON.stringify(s.points)}`);
    s = follow(s, { seconds: 1.06, hz: hz(67.1) }, g4);
    assert(s.points.length === 3 && s.lit.has(4), `${s.points.length} points, lit ${[...s.lit]}`);
    const off = follow(voiceFollower(), { seconds: 1, hz: hz(67.5) }, g4);
    assert(!off.lit.has(4) && off.points.length === 1, 'half a semitone off: drawn, not lit');
});

test('livevoice: a man singing the alto an octave down is folded onto it', () => {
    const s = follow(voiceFollower(), { seconds: 1, hz: hz(55) }, { beat: 0, pitch: 67 });
    assert(Math.abs(s.points[0].midi - 67) < 1e-6 && s.lit.has(0), `${s.points[0].midi}`);
});

test('livevoice: a lone jump is held back from the light until the next reading agrees', () => {
    let s = voiceFollower();
    const c4 = { beat: 0, pitch: 60 };
    const e4 = { beat: 0, pitch: 64 };
    s = follow(s, { seconds: 1.0, hz: hz(60) }, null);
    s = follow(s, { seconds: 1.03, hz: hz(64) }, e4); // a glitch
    assert(!s.lit.has(0) && s.held, 'not lit by a lone jump');
    s = follow(s, { seconds: 1.06, hz: hz(64.05) }, e4); // it lasts: lit
    assert(s.lit.has(0), 'lit once confirmed');
    assert(s.points.every((p) => p.midi < 64.1), 'the line eases, never jumps past');
    void c4;
});

test('livevoice: only the last six seconds are kept', () => {
    let s = voiceFollower();
    for (let t = 0; t < 10; t += 0.05) s = follow(s, { seconds: t, hz: hz(60) }, null);
    assert(s.points[0].seconds > 3.9, `${s.points[0].seconds}`);
});

test('livevoice: singing an octave down, the line never drops at a note edge or across a rest', () => {
    // An alto part sung an octave down: readings before, between and after notes keep the same octave.
    let s = voiceFollower();
    const notes = [{ beat: 0, pitch: 67, from: 0, to: 1 }, { beat: 2, pitch: 69, from: 1.3, to: 2.3 }];
    for (let t = 0; t < 2.5; t += 0.02) {
        const note = notes.find((n) => t >= n.from && t < n.to) ?? null;
        const sung = t < 1.15 ? 55 : 57; // a late release, then an early entry, outside the notes
        s = follow(s, { seconds: t, hz: hz(sung) }, note ? { beat: note.beat, pitch: note.pitch } : null);
    }
    assert(s.offset === -12, `learned the octave: ${s.offset}`);
    assert(s.points.every((p) => p.midi > 65), `no drop: ${Math.min(...s.points.map((p) => p.midi)).toFixed(1)}`);
});

test('livevoice: where you were in tune is kept per note, for the lane to fill', () => {
    let s = voiceFollower();
    const g4 = { beat: 4, pitch: 67 };
    for (let t = 1; t < 1.4; t += 0.02) s = follow(s, { seconds: t, hz: hz(67) }, g4);
    for (let t = 1.4; t < 1.6; t += 0.02) s = follow(s, { seconds: t, hz: hz(68) }, g4); // off
    for (let t = 1.6; t < 1.8; t += 0.02) s = follow(s, { seconds: t, hz: hz(67) }, g4);
    const spans = s.hits.get(4);
    assert(spans.length === 2 && spans[0][1] < 1.45 && spans[1][0] > 1.55, JSON.stringify(spans));
});

test('livevoice: "a little higher" shows after 0.4 s flat, and never flickers on vibrato', () => {
    let s = { hint: null };
    for (let i = 0; i < 15; i++) s = hintStep(s, { cents: -60, dt: 0.02 });
    assert(!s.hint, 'not yet at 0.3 s');
    for (let i = 0; i < 6; i++) s = hintStep(s, { cents: -60, dt: 0.02 });
    assert(s.hint === 'higher', `shown: ${s.hint}`);
    for (let i = 0; i < 30; i++) s = hintStep(s, { cents: 40 * Math.sin(i), dt: 0.02 }); // a vibrato round the note
    assert(s.hint === 'higher' || s.hint === null, 'no flip to lower on vibrato');
    let v = { hint: null };
    for (let i = 0; i < 100; i++) v = hintStep(v, { cents: 30 * Math.sin((2 * Math.PI * 5.5 * i) / 50), dt: 0.02 });
    assert(!v.hint, 'vibrato within the tolerance never hints');
});
