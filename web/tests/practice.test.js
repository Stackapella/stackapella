import { BACKING, guideId, MELODY_UNDER, nextSlot, PRACTICE_DEFAULTS, trackLevels, voiceLevels, withClicks } from '/js/practice.js';
import { assert, close, test } from './harness.js';

const slot = (id, fields = {}) => ({ id, templatePartKey: id, order: 0, volume: 1, pan: 0, muted: false, solo: false, frames: 0, useGuide: false, ...fields });
const heard = (levels) => [...levels].filter(([, v]) => v.audible).map(([id]) => id).sort().join(' ');

test('practice: with nothing recorded, every guide plays', () => {
    assert(heard(voiceLevels([slot('a'), slot('b')])) === 'a:guide b:guide', heard(voiceLevels([slot('a'), slot('b')])));
});

test('practice: a recorded slot plays its take instead of its guide, or its guide when asked', () => {
    assert(heard(voiceLevels([slot('a', { frames: 100 }), slot('b')])) === 'a b:guide', 'take replaces guide');
    assert(heard(voiceLevels([slot('a', { frames: 100, useGuide: true })])) === 'a:guide', 'useGuide');
});

test('practice: mute and solo work on whole slots', () => {
    assert(heard(voiceLevels([slot('a', { muted: true }), slot('b')])) === 'b:guide', 'mute');
    assert(heard(voiceLevels([slot('a', { solo: true }), slot('b', { frames: 9 })])) === 'a:guide', 'solo');
});

test('practice: muting your own part silences it, take or guide (the review\'s mixer)', () => {
    const listen = { ...PRACTICE_DEFAULTS, focus: 'a', mode: 'listen', compare: true, withOthers: true };
    assert(heard(voiceLevels([slot('a', { frames: 100 }), slot('b')], listen)).split(' ').includes('a'), 'your take plays');
    assert(!heard(voiceLevels([slot('a', { frames: 100, muted: true }), slot('b')], listen)).split(' ').includes('a'), 'muted, your take is silent');
    assert(!heard(voiceLevels([slot('a', { muted: true }), slot('b')], { ...listen, compare: false })).includes('a:guide'), 'muted, your guide is silent');
});

test('practice: split ears puts your part left and the others right, the chords in the middle', () => {
    const slots = [slot('a'), slot('b', { frames: 9 }), slot('c')];
    const p = { ...PRACTICE_DEFAULTS, focus: 'a', mode: 'singAlong', withOthers: true, chords: true, split: true };
    const levels = voiceLevels(slots, p);
    assert(levels.get('a:guide').pan === -1 && levels.get('b').pan === 1 && levels.get('c:guide').pan === 1 && levels.get(BACKING).pan === 0, JSON.stringify([...levels]));
    assert(voiceLevels(slots, { ...p, split: false }).get('b').pan === 0, 'off by default: as they were');
    const recording = voiceLevels(slots, { ...PRACTICE_DEFAULTS, split: true }, { recordingId: 'b' });
    assert(recording.get('b:guide') === undefined || recording.get('b').pan === -1, 'recording: the part being recorded is yours');
    assert(recording.get('a:guide').pan === 1, 'recording: the others right');
});

test('practice: Listen plays your guide alone, or with the others', () => {
    const slots = [slot('a'), slot('b', { frames: 9 }), slot('c')];
    const p = { ...PRACTICE_DEFAULTS, focus: 'a', mode: 'listen' };
    assert(heard(voiceLevels(slots, p)) === 'a:guide', heard(voiceLevels(slots, p)));
    assert(heard(voiceLevels(slots, { ...p, withOthers: true })) === 'a:guide b c:guide', 'with others');
});

test('practice: Hear the melody plays the melody under a harmony part you listen to, and nothing else changes', () => {
    const slots = [slot('alto'), slot('soprano', { frames: 9 }), slot('bass')];
    const p = { ...PRACTICE_DEFAULTS, focus: 'alto', mode: 'listen' };
    const levels = voiceLevels(slots, p, { melody: 'soprano' });
    assert(heard(levels) === 'alto:guide soprano', heard(levels));
    close(levels.get('soprano').volume, MELODY_UNDER, 1e-9, 'the melody under');
    close(levels.get(guideId('alto')).volume, 1, 1e-9, 'your guide');
    assert(heard(voiceLevels(slots, p)) === 'alto:guide', 'off: your guide alone');
    assert(heard(voiceLevels(slots, { ...p, focus: 'soprano' }, { melody: 'soprano' })) === 'soprano:guide', 'singing the melody: unchanged');
    assert(heard(voiceLevels(slots, p, { melody: 'nobody' })) === 'alto:guide', 'no melody part: unchanged');
    assert(heard(voiceLevels([slot('alto'), slot('soprano', { muted: true })], p, { melody: 'soprano' })) === 'alto:guide', 'a muted melody stays muted');
    const along = voiceLevels(slots, { ...p, mode: 'singAlong' }, { melody: 'soprano' });
    assert(heard(along) === 'alto:guide bass:guide soprano', 'Sing along: every part, as before');
    close(along.get('soprano').volume, 1, 1e-9, 'Sing along: the melody at its own level');
});

test('practice: Sing along plays your guide quieter, with the others', () => {
    const levels = voiceLevels([slot('a', { volume: 0.8 }), slot('b')], { ...PRACTICE_DEFAULTS, focus: 'a', mode: 'singAlong', guideLevel: 0.5 });
    assert(heard(levels) === 'a:guide b:guide', heard(levels));
    assert(Math.abs(levels.get(guideId('a')).volume - 0.4) < 1e-9, `guide at ${levels.get(guideId('a')).volume}`);
});

test('practice: On your own plays only the others', () => {
    const levels = voiceLevels([slot('a', { frames: 9 }), slot('b')], { ...PRACTICE_DEFAULTS, focus: 'a', mode: 'own' });
    assert(heard(levels) === 'b:guide', heard(levels));
});

test('practice: Compare in Listen plays your take where your guide would be', () => {
    const levels = voiceLevels([slot('a', { frames: 9 })], { ...PRACTICE_DEFAULTS, focus: 'a', mode: 'listen', compare: true });
    assert(heard(levels) === 'a', heard(levels));
});

test('practice: the slot being recorded is silent', () => {
    const levels = voiceLevels([slot('a', { frames: 9 }), slot('b')], PRACTICE_DEFAULTS, { recordingId: 'a' });
    assert(heard(levels) === 'b:guide', heard(levels));
});

test('practice: spreading pans the guides apart; parts without a guide have one voice', () => {
    const levels = voiceLevels([slot('a'), slot('b'), { ...slot('c'), templatePartKey: undefined, frames: 9 }], { ...PRACTICE_DEFAULTS, spread: true });
    assert(levels.get('a:guide').pan < 0 && levels.get('b:guide').pan > 0, 'not spread');
    assert(!levels.has('c:guide') && levels.get('c').audible, 'a plain part');
});

test('practice: the next part follows the template order and skips recorded parts', () => {
    const slots = [slot('melody', { order: 0, frames: 9 }), slot('low', { order: 1 }), slot('bass', { order: 2 })];
    assert(nextSlot(slots, { order: ['melody', 'bass', 'low'] })?.id === 'bass', 'bass is next');
    assert(nextSlot(slots, { order: ['melody', 'bass', 'low'] }, 'bass')?.id === 'low', 'after bass');
});

test('practice: while a part is recorded, the others play even in Listen', async () => {
    const { voiceLevels, PRACTICE_DEFAULTS, guideId } = await import('/js/practice.js');
    const slots = [
        { id: 'a', templatePartKey: 'alto', frames: 0, volume: 1, pan: 0, muted: false, solo: false },
        { id: 'b', templatePartKey: 'bass', frames: 0, volume: 1, pan: 0, muted: false, solo: false },
    ];
    const levels = voiceLevels(slots, { ...PRACTICE_DEFAULTS, focus: 'a', mode: 'listen' }, { recordingId: 'a' });
    if (!levels.get(guideId('b')).audible) throw new Error('the bass should play while the alto records');
    if (levels.get(guideId('a')).audible) throw new Error('the alto being recorded should be silent');
});

test('practice tracks: everything but my part leaves it out; my part louder puts it forward', () => {
    const levels = new Map([['a', { audible: true, volume: 1, pan: 0 }], ['a:guide', { audible: false, volume: 1, pan: 0 }], ['b', { audible: true, volume: 0.8, pan: 0 }], [BACKING, { audible: true, volume: 0.5, pan: 0 }]]);
    const without = trackLevels(levels, 'a', 'without');
    assert(!without.get('a').audible && !without.get('a:guide').audible && without.get('b').audible, 'mine is silent, the others play');
    const louder = trackLevels(levels, 'a', 'louder');
    assert(louder.get('a').volume > 1 && louder.get('b').volume < 0.8 && louder.get(BACKING).volume === 0.5, 'mine forward, the others back, the chords as they were');
});

test('practice tracks: split ears puts my part left and the others right, with no spread; alone leaves only mine', () => {
    const levels = new Map([['a', { audible: true, volume: 1, pan: 0.2 }], ['a:guide', { audible: false, volume: 1, pan: 0 }], ['b', { audible: true, volume: 0.8, pan: -0.3 }], [BACKING, { audible: true, volume: 0.5, pan: 0 }]]);
    const split = trackLevels(levels, 'a', 'split');
    assert(split.get('a').pan === -1 && split.get('a:guide').pan === -1 && split.get('a').spread === 0, 'mine hard left, unspread');
    assert(split.get('b').pan === 1 && split.get('b').volume === 0.8 && split.get('b').audible, 'the others hard right, as loud as they were');
    assert(split.get(BACKING).pan === 0, 'the chords stay in the middle');
    const alone = trackLevels(levels, 'a', 'alone');
    assert(alone.get('a').audible && alone.get('a').volume === 1 && !alone.get('b').audible && !alone.get(BACKING).audible, 'only mine plays, as loud as it was');
});

test('practice tracks: a bar of count-in, then a click on every beat, the singing moved by exactly that bar', () => {
    const sr = 8000;
    const song = new Float32Array(sr * 2).fill(0);
    song[sr + 100] = 1; // a mark just after one second in (between the clicks)
    const accent = new Float32Array([0.9]);
    const normal = new Float32Array([0.3]);
    const { channels } = withClicks({ channels: [song, song.slice()], sampleRate: sr }, { beatSeconds: 0.5, beatsPerBar: 3, pickupBeats: 1, accent, normal });
    const lead = 1.5 * sr;
    assert(channels[0].length === song.length + lead, 'longer by a bar');
    close(channels[0][lead + sr + 100], 1, 1e-6, 'the singing moved by exactly the count-in');
    const clicks = [];
    for (let i = 0; i < channels[0].length; i++) if (channels[0][i] > 0 && i !== lead + sr + 100) clicks.push([i / sr, channels[0][i] > 0.4 ? 'accent' : 'beat']);
    assert(clicks.length === 7 && clicks[0][0] === 0 && clicks[1][0] === 0.5, `a click every half second: ${JSON.stringify(clicks)}`);
    assert(clicks[1][1] === 'accent' && clicks[4][1] === 'accent' && clicks[0][1] === 'beat', 'the bar lines after the pickup are accented');
});

// A beta singer (2026-10-29: "the mute and solo buttons in the studio do not work reliably"): a beta
// project's studio keeps the singer's practice focus on their part, and Solo was ignored there, so
// the rows showed a solo while every part still played.
test('practice: with a part in focus (the beta studio), Solo and Mute still decide what plays', () => {
    const slots = [slot('soprano'), slot('alto', { solo: true }), slot('tenor'), slot('bass')];
    const focus = { ...PRACTICE_DEFAULTS, focus: 'tenor', mode: 'singAlong', guideLevel: 0.5 };
    assert(heard(voiceLevels(slots, focus)) === 'alto:guide', `the solo alone: ${heard(voiceLevels(slots, focus))}`);
    const tenorToo = slots.map((s) => (s.id === 'tenor' ? { ...s, solo: true } : s));
    assert(heard(voiceLevels(tenorToo, focus)) === 'alto:guide tenor:guide', `two solos: ${heard(voiceLevels(tenorToo, focus))}`);
    assert(heard(voiceLevels(slots, focus, { recordingId: 'tenor' })) === 'alto:guide', `recording the tenor, the solo still holds: ${heard(voiceLevels(slots, focus, { recordingId: 'tenor' }))}`);
    const muted = [slot('soprano', { muted: true }), slot('alto'), slot('tenor', { muted: true }), slot('bass')];
    assert(heard(voiceLevels(muted, focus)) === 'alto:guide bass:guide', `mutes, no solo: ${heard(voiceLevels(muted, focus))}`);
    assert(heard(voiceLevels(muted, { ...focus, mode: 'listen' }, { melody: 'soprano' })) === '', `listening alone: only the melody, and it's muted: ${heard(voiceLevels(muted, { ...focus, mode: 'listen' }, { melody: 'soprano' }))}`);
});
