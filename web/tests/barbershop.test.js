// Barbershop and tags (js/ring.js, docs/courses/barbershop.md): a held chord note is judged against
// its pure target, the piano's own third never earns ring credit, a seventh tuned low counts close
// across its wider band, and only held notes are judged; then through the real tracker.
import { ringCents, ringPercent, ringTake } from '/js/ring.js';
import { PLAY_SCORERS } from '/js/lesson-scores.js';
import { trackTake } from '/js/pitchtrack.js';
import { sing } from './fixtures.js';
import { assert, close, test } from './harness.js';

test('barbershop: ringing within 6 cents of pure, close within 10, and the piano\'s third earns nothing', () => {
    assert(ringPercent(4, -14) === 100, 'within 6 rings');
    assert(ringPercent(-9, -14) === 75, 'within 10 is close');
    assert(ringPercent(-11, -14, { generous: true }) === 75 && ringPercent(-11, -14) < 75, 'the first lesson counts 12 as close');
    assert(ringPercent(9, -14) < 75, 'a third only 5 cents under the piano is never close (the band is one-sided)');
    assert(ringPercent(14, -14) === 20, 'the piano\'s own third (14 cents above pure) earns no ring');
    assert(ringPercent(-13, -14) === 40, 'within 15 on the pure side of the piano still counts a little');
    assert(ringPercent(null, -14) === null, 'not heard');
});

test('barbershop: a seventh tuned low is close anywhere across its wider band', () => {
    assert(ringPercent(13, -31) === 75, '18 cents under the piano: a quartet\'s seventh, close');
    assert(ringPercent(25, -31) < 75, '6 cents under the piano: not low enough');
});

test('barbershop: through the real tracker, a pure third rings and the piano\'s third does not, and short notes are not judged', () => {
    const sampleRate = 44100;
    const notes = [{ beat: 0, length: 0.5, pitch: 64 }, { beat: 1, length: 4, pitch: 64 }];
    const target = notes.map((n) => ({ start: n.beat, end: n.beat + n.length, pitch: n.pitch - 0.14 }));
    const pure = ringTake(trackTake(sing(sampleRate, { notes, bpm: 60, cents: -14 }), sampleRate), target, [-14, -14]);
    assert(pure.notes[0] === null, 'a short note is not judged on ring');
    close(pure.notes[1].off, 0, 4, 'the pure third');
    assert(pure.percent === 100, `pure ${pure.percent}`);
    const piano = ringTake(trackTake(sing(sampleRate, { notes, bpm: 60 }), sampleRate), target, [-14, -14]);
    assert(piano.percent === 20, `the piano's third ${piano.percent}`);
    const scored = PLAY_SCORERS.ring({ track: trackTake(sing(sampleRate, { notes, bpm: 60, cents: -5 }), sampleRate), target, exercise: { notes: [{ step: 4, cents: -14 }, { step: 4, cents: -14 }] } });
    assert(scored.percent < 100 && scored.line.key === 'ring.line.high', `9 high says let it fall ${JSON.stringify(scored)}`);
    close(ringCents(trackTake(sing(sampleRate, { notes, bpm: 60, cents: -14 }), sampleRate), target[1]), 0, 4, 'ringCents alone');
});

const lessons = async () => (await (await fetch('/course/plus/barbershop/course.json')).json()).units.flatMap((u) => u.lessons);

test('barbershop course: your notes sit within +5 and -9, every ring step has straight guides and says headphones', async () => {
    for (const lesson of await lessons()) {
        for (const step of lesson.steps.filter((s) => s.kind === 'play')) {
            const e = step.exercise;
            for (const n of e.notes.filter((x) => !x.listenOnly)) assert(n.step >= -9 && n.step <= 5, `${lesson.id}: your note at ${n.step}`);
            if (e.score === 'ring' || e.score === 'partring') {
                assert(e.with?.length, `${lesson.id}: a ring step has voices to ring with`);
                for (const line of e.with) for (const g of line.notes) assert(g.vibrato?.cents === 0, `${lesson.id}: a guide with vibrato blurs the ring`);
                assert(/Headphones/.test(step.text.en), `${lesson.id}: a ring step says headphones`);
            }
        }
    }
});

test('barbershop course: about six holds a lesson at most, none over six seconds', async () => {
    for (const lesson of await lessons()) {
        const holds = lesson.steps.filter((s) => s.kind === 'play').flatMap((s) => s.exercise.notes.filter((x) => !x.listenOnly && (x.length * 60) / s.exercise.tempo >= 2).map((x) => (x.length * 60) / s.exercise.tempo));
        assert(holds.length <= 6, `${lesson.id}: ${holds.length} holds`);
        for (const seconds of holds) assert(seconds <= 6, `${lesson.id}: a ${seconds}-second hold`);
    }
});

test('barbershop course: in Who\'s out?, the answer names the voice that is out', async () => {
    const out = (await lessons()).find((l) => l.id === 'bb-1-out');
    const VOICE = [-16, -9, -4, 0];
    for (const round of out.steps.find((s) => s.kind === 'choose').exercise.rounds.filter((r) => r.options?.length === 4)) {
        const notes = round.clips[0].notes;
        const off = notes.findIndex((x, i) => Math.abs(x.step - VOICE[i] - (i === 3 ? -0.14 : 0)) > 0.1);
        assert(off === round.answer, `"${round.why.en}": voice ${off} is out, the answer says ${round.answer}`);
    }
});

test('barbershop: a part is starred half on pitch and half on its held chords ringing, and a diminished chord is pitch only', () => {
    const sampleRate = 44100;
    const notes = [{ beat: 1, length: 3, pitch: 62 - 0.14 }, { beat: 4, length: 3, pitch: 61 }, { beat: 7, length: 3, pitch: 57 - 0.14 }];
    const exercise = { notes: [{ step: 2, cents: -14 }, { step: 1, ring: false }, { step: -3, cents: -14 }] };
    const target = notes.map((n) => ({ start: n.beat, end: n.beat + n.length, pitch: n.pitch }));
    const take = trackTake(sing(sampleRate, { notes: notes.map((n) => ({ ...n, pitch: Math.round(n.pitch) })), bpm: 60, cents: (n) => (n.pitch === 61 ? 0 : -14) }), sampleRate);
    const scored = PLAY_SCORERS.partring({ track: take, target, notes, beatSeconds: 1, exercise });
    assert(scored.percent >= 95, `a part sung pure ${scored.percent}`);
    const rung = ringTake(take, target, [-14, 0, -14], { skip: [false, true, false] });
    assert(rung.notes[1] === null, 'the diminished chord is never ring-scored');
});

test('barbershop: a wave or a glide can\'t ring, a skipped note is a miss, and through a speaker it\'s pitch only', () => {
    const sampleRate = 44100;
    const notes = [{ beat: 0, length: 4, pitch: 64 }, { beat: 4, length: 4, pitch: 67 }];
    const target = notes.map((n) => ({ start: n.beat, end: n.beat + n.length, pitch: n.pitch - 0.14 }));
    const waved = ringTake(trackTake(sing(sampleRate, { notes, bpm: 60, cents: -14, vibrato: { hz: 5.5, cents: 40 } }), sampleRate), target, [-14, -14]);
    assert(waved.notes[0].waved && waved.percent <= 40, `a ±40 vibrato centred on pure ${waved.percent}`);
    const skipped = ringTake(trackTake(sing(sampleRate, { notes: [notes[0]], bpm: 60, cents: -14 }), sampleRate), target, [-14, -14]);
    assert(skipped.percent === 50, `one of two ringing notes sung ${skipped.percent}`);
    const exercise = { notes: [{ step: 4, cents: -14 }, { step: 7, cents: -14 }] };
    const take = trackTake(sing(sampleRate, { notes, bpm: 60, cents: -14 }), sampleRate);
    const speaker = PLAY_SCORERS.ring({ track: take, target, notes: notes.map((n) => ({ ...n, pitch: n.pitch - 0.14 })), beatSeconds: 1, exercise, speaker: true });
    assert(speaker.line.key === 'ring.line.headphones' && speaker.percent === 85, `through a speaker, two stars at most ${JSON.stringify(speaker)}`);
    const partly = PLAY_SCORERS.ring({ track: trackTake(sing(sampleRate, { notes: [notes[0]], bpm: 60, cents: -14 }), sampleRate), target, notes, beatSeconds: 1, exercise });
    assert(partly.line.key === 'ring.line.missed', `a held note not sung is said ${JSON.stringify(partly)}`);
});
