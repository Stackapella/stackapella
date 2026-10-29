import { motion, octaveFit, suggestPart } from '/js/dsp.js';
import { isNewSinger } from '/js/practice.js';
import { assert, close, test } from './harness.js';

// Melody first (Hillel, 2026-10-29; first-week QA finding 8): a new singer gets the melody in their
// own octave; later, of the parts that fit, the one that moves most.

const grace = async () => {
    const template = await (await fetch('/templates/amazing-grace.json')).json();
    return template.parts.map((p) => ({ key: p.key, range: p.range, melody: !!p.melody, notes: p.guide.notes }));
};
// The QA singer: around A3, D3 to E4.
const LOW_VOICE = { low: 50, high: 64 };

test('melody first: a part moves as the arranger counted it (Amazing Grace)', async () => {
    const parts = Object.fromEntries((await grace()).map((p) => [p.key, motion(p.notes)]));
    close(parts.soprano, 29 / 31, 1e-9, 'soprano');
    close(parts.alto, 19 / 26, 1e-9, 'alto');
    close(parts.bass, 9 / 14, 1e-9, 'bass');
    close(parts.tenor, 5 / 26, 1e-9, 'tenor');
    assert(motion([]) === 0 && motion([{ pitch: 60 }]) === 0, 'no steps, no motion');
});

test('melody first: a new singer gets the melody, an octave down when that is where it fits', async () => {
    const best = suggestPart(LOW_VOICE, await grace(), { melodyFirst: true });
    assert(best.part.key === 'soprano', `the melody, not ${best.part.key}`);
    assert(best.fits && best.octave === -12 && best.transpose === 0, `an octave lower, in the song's key: ${JSON.stringify(best)}`);
});

test('melody first: the melody in their own octave when it fits there', async () => {
    const best = suggestPart({ low: 60, high: 76 }, await grace(), { melodyFirst: true });
    assert(best.part.key === 'soprano' && best.octave === 0, JSON.stringify(best));
});

test('melody first: after that, of the parts that fit, the one that moves most (never the one-note tenor)', async () => {
    const best = suggestPart(LOW_VOICE, await grace());
    assert(best.part.key === 'alto', `the alto moves most of the parts that fit, not ${best.part.key}`);
    assert(best.octave === 0 && best.fits, JSON.stringify(best));
});

test('melody first: a melody that fits in no octave falls back to the part that fits', async () => {
    const tiny = { low: 55, high: 61 }; // only the tenor's span fits in a range this small
    const best = suggestPart(tiny, await grace(), { melodyFirst: true });
    assert(best.part.key === 'tenor' && best.fits, JSON.stringify(best));
    assert(octaveFit(tiny, { low: 62, high: 74 }) === null, 'a 12-semitone melody fits no 6-semitone range');
});

test('melody first: nothing that fits gives the closest part, said as closest', async () => {
    const best = suggestPart({ low: 80, high: 84 }, await grace());
    assert(best && !best.fits, JSON.stringify(best));
    assert(suggestPart({ low: 50, high: 64 }, []) === null, 'no parts, no suggestion');
});

test('melody first: a singer is new until a good take or another song', () => {
    const projects = [
        { id: 'a', template: { id: 'amazing-grace' } },
        { id: 'a2', template: { id: 'amazing-grace' } },
        { id: 'b', template: { id: 'silent-night' } },
        { id: 'lesson', template: { id: 'lesson-song' }, course: { unit: 1 } },
        { id: 'own', template: null },
    ];
    const take = (projectId, extra = {}) => ({ projectId, frames: 48000, ...extra });
    assert(isNewSinger([], projects, { projectId: 'a' }), 'nothing sung yet');
    assert(isNewSinger([take('a', { score: { stars: 1 } })], projects, { projectId: 'a' }), 'a first, not yet good take on this song');
    assert(isNewSinger([take('a2')], projects, { templateId: 'amazing-grace' }), 'the same song started twice is still one song');
    assert(!isNewSinger([take('a', { score: { stars: 2 } })], projects, { projectId: 'b' }), 'a good take anywhere');
    assert(!isNewSinger([take('a')], projects, { projectId: 'b' }), 'another song started');
    assert(isNewSinger([take('lesson'), take('own')], projects, { projectId: 'a' }), "the course's lessons and own projects aren't songs");
    assert(isNewSinger([take('b', { createdBy: { id: 'friend' } })], projects, { projectId: 'a', me: 'me' }), "a friend's take isn't theirs");
    assert(isNewSinger([{ projectId: 'b', frames: 0 }], projects, { projectId: 'a' }), 'a part with no take');
});
