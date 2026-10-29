// The Sing screens' arithmetic (js/sing.js): where each part is, the now texts, part status and the
// phrase path's progress.
import { currentPhrase, nextStep, nowParts, nowText, partStatus, practised, progressOf, stepDone, timelineAt, toStep } from '/js/sing.js';
import { assert, test } from './harness.js';

const melody = [
    { beat: 0, length: 1, pitch: 62, lyric: 'A-' }, { beat: 1, length: 2, pitch: 62, lyric: 'maz-' }, { beat: 3, length: 1, pitch: 60 },
    { beat: 10, length: 2, pitch: 59, lyric: 'grace' },
];

test('sing: the timeline knows the note, its word, and the next two', () => {
    const at = timelineAt(melody, 1.5);
    assert(at.state === 'singing' && at.note.pitch === 62 && at.syllable === 'maz-', JSON.stringify(at));
    assert(at.next.map((n) => n.pitch).join() === '60,59', 'next two');
    assert(timelineAt(melody, 3.2).syllable === 'maz-', 'a held note continues its word');
});

test('sing: get ready comes a bar before an entry after a rest of a bar or more', () => {
    assert(timelineAt(melody, 5).state === 'resting', 'more than a bar before');
    const ready = timelineAt(melody, 6.5);
    assert(ready.state === 'getReady' && ready.entry.pitch === 59 && ready.beatsLeft === 3.5, JSON.stringify(ready));
    // A round: the alto comes in at bar 3 (beat 8), with nothing before it.
    const alto = [{ beat: 8, length: 1, pitch: 67, lyric: 'Are' }];
    assert(timelineAt(alto, 3).state === 'resting', 'two bars before the alto');
    assert(timelineAt(alto, 4).state === 'getReady' && timelineAt(alto, 4).beatsLeft === 4, 'a bar before the alto');
    // A short breath between notes is not a get ready.
    assert(timelineAt([{ beat: 0, length: 1, pitch: 60 }, { beat: 2, length: 1, pitch: 62 }], 1.5).state === 'resting', 'a short rest');
    assert(timelineAt(melody, 20).state === 'resting' && !timelineAt(melody, 20).entry, 'after the end');
});

test('sing: the now texts name the notes in the key being sung, with the direction', () => {
    const [key, values] = nowText(timelineAt(melody, 0.5), { transpose: 2 });
    assert(key === 'sing.now.nextThen' && values.now === 'E4' && values.next === 'E4' && values.then === 'D4' && values.direction === 'down', JSON.stringify(values));
    assert(nowText(timelineAt(melody, 20))[0] === 'sing.now.resting' && nowText(timelineAt(melody, 5))[0] === 'sing.now.comingUp', 'resting, then coming up');
    assert(nowText(timelineAt(melody, 6.5))[0] === 'sing.now.comingUp' && nowText(timelineAt(melody, 6.5))[1].note === 'B3', 'coming up');
    assert(nowText(timelineAt(melody, 11))[0] === 'sing.now.last', 'the last note');
});

test('sing: each part says what it sings now', () => {
    const template = { parts: [{ key: 'soprano', guide: { notes: melody } }, { key: 'alto', guide: { notes: [{ beat: 4, length: 4, pitch: 57, lyric: 'oo' }] } }] };
    const slots = [{ id: 'a', templatePartKey: 'soprano' }, { id: 'b', templatePartKey: 'alto' }, { id: 'c', templatePartKey: null }];
    const now = nowParts(slots, template, 1);
    assert(now.length === 2 && now[0].name === 'D4' && now[0].syllable === 'maz-' && now[1].state === 'getReady', JSON.stringify(now.map((p) => [p.name, p.state])));
});

test('sing: a part has one status', () => {
    assert(partStatus({ id: 'a' }, { recordingId: 'a', focusId: 'a' }) === 'recording', 'recording first');
    assert(partStatus({ id: 'a' }, { focusId: 'a' }) === 'you', 'yours');
    assert(partStatus({ id: 'b', frames: 10 }, { audible: false }) === 'muted', 'muted');
    assert(partStatus({ id: 'b', frames: 10 }, {}) === 'recorded', 'recorded, resting');
    assert(partStatus({ id: 'b' }, { singing: true }) === 'singing', 'singing');
});

test('sing: a step is done when every phrase was practised in it, and the next starts afresh', () => {
    let progress = progressOf({}, 'alto');
    assert(progress.step === 'listen' && currentPhrase(progress, 3) === 0, 'starts at listen, phrase 1');
    progress = practised(practised(progress, 0), 0);
    progress = practised(progress, 1);
    assert(progress.phrases[0] === 2 && !stepDone(progress, 3) && currentPhrase(progress, 3) === 2, JSON.stringify(progress));
    progress = practised(progress, 2);
    assert(stepDone(progress, 3), 'done');
    progress = toStep(progress, nextStep(progress.step));
    assert(progress.step === 'singAlong' && !stepDone(progress, 3), 'sing along, afresh');
    assert(nextStep('record') === null, 'record is last');
});
