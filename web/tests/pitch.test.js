// Perfect your pitch (course/plus/pitch/course.json, docs/courses/perfect-pitch.md): the rules its outline
// promises, checked on the file. Every note stays from a fourth below to a fifth above your note,
// holds stay under 8 seconds, every scored step names a scorer the course measures with, every
// lesson fits in 5 minutes, and an echo answers every call. Then Bullseye's board and its echoes.
import { lessonMinutes } from '/js/results.js';
import { coldPercent, echoPlan, sungLeap } from '/js/components/bullseye.js';
import { assert, test } from './harness.js';

const SCORES = ['landing', 'lock', 'centre', 'drift', 'memory', 'cold'];
const SCORED = ['play', 'bullseye', 'wave'];
/** Every exercise's notes as `{ step, seconds }`: a play or listen's notes, a Bullseye echo's calls and answers, or a choose round's clips. */
const notesOf = (lesson) => lesson.steps.flatMap((s) => [s.exercise, s.listen].filter(Boolean)).flatMap((e) => {
    const beat = 60 / (e.tempo ?? 72);
    const notes = e.target ? e.target.map((t) => ({ step: e.step ?? 0, length: t.kind === 'path' ? 0 : t.seconds / beat })) : e.echoes ? [...echoPlan(e).calls, ...echoPlan(e).answers] : e.rounds ? e.rounds.flatMap((r) => r.clips.flatMap((c) => c.notes)) : e.notes;
    return notes.map((n) => ({ step: n.step, seconds: n.length * beat }));
});
const course = async () => (await fetch('/course/plus/pitch/course.json')).json();

test('pitch course: every note sits a fourth below to a fifth above your note, and no hold passes 8 seconds', async () => {
    for (const unit of (await course()).units) {
        for (const lesson of unit.lessons) {
            for (const n of notesOf(lesson)) {
                assert(n.step >= -5 && n.step <= 7, `${lesson.id}: step ${n.step} outside the span`);
                assert(n.seconds <= 8, `${lesson.id}: a ${n.seconds}-second note`);
            }
        }
    }
});

test('pitch course: every scored step names a measure, ends its lesson with what it measured, and fits 5 minutes', async () => {
    for (const unit of (await course()).units) {
        for (const lesson of unit.lessons) {
            const scored = lesson.steps.filter((s) => SCORED.includes(s.kind));
            assert(scored.length > 0, `${lesson.id}: a scored step`);
            for (const s of scored.filter((s) => s.kind !== 'bullseye' && !(s.kind === 'wave' && s.exercise.target.every((t) => t.scored === false)))) assert(SCORES.includes(s.exercise.score), `${lesson.id}: scored by ${s.exercise.score}`);
            assert(scored.at(-1).measures?.en, `${lesson.id}: its stars say what they measured`);
            assert(lessonMinutes(lesson) <= 5, `${lesson.id}: ${lessonMinutes(lesson)} minutes`);
            assert(lesson.steps[0].kind === 'learn', `${lesson.id}: starts with why`);
        }
    }
});

test('pitch course: an echo answers every call, the same notes, after a quiet beat to think', () => {
    const plan = echoPlan({ echoes: [0, [0, 7]], think: 2 });
    assert(plan.calls.length === 3 && plan.answers.length === 3, 'three calls, three answers');
    plan.calls.forEach((c, i) => assert(c.step === plan.answers[i].step && c.length === plan.answers[i].length, `answer ${i} matches its call`));
    assert(plan.answers[0].beat === plan.calls[0].beat + 1.5 + 2, 'the answer comes after the call and two beats to think');
    assert(plan.calls[1].beat >= plan.answers[0].beat + plan.answers[0].length, 'the next call waits for your answer');
    assert(plan.calls[2].length === 1.5 && plan.calls[1].length === 1, "a group's last note is the longer one");
    assert(plan.end > plan.answers.at(-1).beat + plan.answers.at(-1).length, 'it ends after your last answer');
});

test('pitch course: an echo can answer a call with other notes: a leap from the guide\'s note, or a chord\'s root', () => {
    const plan = echoPlan({ echoes: [{ call: [0], sing: [0, 5] }, { call: [4, 7], sing: [0], chord: { root: 0 } }] });
    assert(plan.calls.length === 3 && plan.answers.length === 3, 'one call note and two sung, then two call notes and one sung');
    assert(plan.answers[0].step === 0 && plan.answers[1].step === 5, 'the leap is sung from the called note');
    assert(plan.answers[1].length === 1.5 && plan.calls[0].length === 1.5, "each part's last note is the longer one");
    assert(plan.groups[1].chord.root === 0 && plan.groups[0].chord === null, 'the chord belongs to its group');
    assert(plan.groups[1].answer[0] === plan.groups[1].call[1] + 0.5, 'the answer comes after the call and a half beat to think');
    assert(plan.calls[1].beat === plan.groups[0].answer[1] + 1, 'the next call waits a beat after your answer');
});

test('pitch course: an echo can set its key first, play a chord alone, and reveal a note sung from memory', () => {
    const plan = echoPlan({ home: true, reveal: true, echoes: [{ call: [], sing: [0], chord: { root: 5 } }, { call: [2, 4], sing: [2], key: 2 }] });
    const [chordOnly, phrase] = plan.groups;
    assert(chordOnly.home[1] - chordOnly.home[0] === 2 && chordOnly.call[0] === chordOnly.home[1], 'two beats of the I chord, then the call');
    assert(chordOnly.call[1] - chordOnly.call[0] === 2, 'a chord with no notes sounds alone for two beats');
    assert(chordOnly.reveal[0] === chordOnly.answer[1] && chordOnly.reveal[1] === chordOnly.reveal[0] + 2, 'the drone reveals after your answer');
    assert(phrase.key === 2 && phrase.home[0] === chordOnly.reveal[1] + 1, "the next group's own key, after a beat's rest");
    assert(plan.answers.length === 2 && plan.calls.length === 2, 'the chord-only call has no notes to sing back');
});

test('pitch course: a group can call in its own rhythm, while answers keep the step\'s', () => {
    const plan = echoPlan({ echoes: [{ call: [-5, 0, 4], sing: [0], lengths: [1, 2, 0.5] }] });
    assert(plan.calls.map((n) => n.length).join() === '1,2,0.5', 'the call in its own rhythm');
    assert(plan.answers[0].length === 1.5 && plan.answers[0].beat === 1 + 3.5 + 0.5, 'the answer after it, at the usual length');
});

test('pitch course: after a cold note, the rest are judged from where it landed', () => {
    const plan = echoPlan({ echoes: [{ call: [], sing: [0, 4, 7] }] });
    const landings = [{ cents: 60, start: 'clean' }, { cents: 62, start: 'clean' }, { cents: 58, start: 'clean' }];
    const memories = [{ percent: 100 }, { percent: 100 }, { percent: 100 }];
    assert(coldPercent(plan, landings, memories, 'cold') === 100, 'mi and so true to a do 60 cents off');
    assert(coldPercent(plan, [landings[0], { cents: 0, start: 'clean' }, { cents: 0, start: 'clean' }], memories, 'cold') < 70, 'mi and so on the piano but not from your do');
    assert(coldPercent(plan, [landings[0], null, null], [{ percent: 100 }, null, null], 'cold') <= 34, 'only the do sung: mi and so are misses');
    const farDo = [{ cents: -590, start: 'clean' }, { cents: 600, start: 'clean' }, { cents: 600, start: 'clean' }];
    assert(coldPercent(plan, farDo, memories, 'cold') === 100, 'a do a tritone off with mi and so true to it never wraps');
});

test('pitch course: a wrong leap is named by what was sung, from the note before it', () => {
    const plan = echoPlan({ echoes: [{ call: [0], sing: [0, 7] }, { call: [0], sing: [4] }] });
    assert(JSON.stringify(sungLeap(plan, 1, -200)) === JSON.stringify({ semitones: 5, up: true }), 'asked for five, sang four');
    assert(JSON.stringify(sungLeap(plan, 2, 100)) === JSON.stringify({ semitones: 5, up: true }), "from the call's note when the answer has one note");
    assert(sungLeap(plan, 0, -300).semitones === 3 && !sungLeap(plan, 0, -300).up, 'a first note measured from the call, down');
});

test('pitch course: Bullseye puts a dart as far out as the note was off, sharp above and flat below, never off the board', async () => {
    const { dart, dartRadius, landingWord } = await import('/js/components/bullseye.js');
    assert(dartRadius(0) === 0 && Math.abs(dartRadius(10) - 0.2) < 1e-9 && Math.abs(dartRadius(25) - 0.45) < 1e-9, 'the gold and inner ring');
    assert(dartRadius(-50) > dartRadius(30) && dartRadius(400) <= 0.97, 'further out with the cents, pinned inside the rim');
    const sharp = dart({ cents: 20, start: 'clean' });
    const flat = dart({ cents: -20, start: 'scoop' });
    assert(sharp.y < 0 && flat.y > 0, 'sharp above, flat below');
    assert(sharp.trail === null && flat.trail.y > flat.y, 'a scoop trails from below');
    assert(dart({ cents: 70, start: 'clean' }).glass && !sharp.glass, 'outside 50 cents is glass, never red');
    const fan = [0, 1, 2].map((i) => dart({ cents: 5, start: 'clean' }, i, 3));
    assert(fan[0].x < fan[1].x && fan[1].x < fan[2].x, 'darts fan out so each one shows');
    assert(landingWord(4) === 'bullseye.word.on' && landingWord(-18) === 'bullseye.word.hairFlat' && landingWord(140) === 'bullseye.word.farSharp', 'plain words');
});

test('pitch course: every scorer the course names is registered, and a scooped echo earns the onsets tip', async () => {
    const { PLAY_SCORERS } = await import('/js/lesson-scores.js');
    const { trackTake } = await import('/js/pitchtrack.js');
    const { sing } = await import('./fixtures.js');
    for (const name of SCORES) assert(typeof PLAY_SCORERS[name] === 'function', `${name} is registered`);
    const notes = [{ beat: 1, length: 1.5, pitch: 57 }, { beat: 3, length: 1.5, pitch: 62 }, { beat: 5, length: 1.5, pitch: 64 }];
    const exercise = { notes: [{ step: 0 }, { step: 5 }, { step: 7 }], score: 'landing' };
    const take = (opts) => trackTake(sing(44100, { notes, bpm: 60, ...opts }), 44100);
    const clean = PLAY_SCORERS.landing({ track: take({}), notes, beatSeconds: 1, exercise });
    assert(clean.percent >= 90 && !clean.tip, `clean ${clean.percent} ${clean.tip}`);
    const scooped = PLAY_SCORERS.landing({ track: take({ scoop: { ms: 220, cents: -300 } }), notes, beatSeconds: 1, exercise });
    assert(scooped.tip === 'onsets', `scooped tip ${scooped.tip}`);
});
