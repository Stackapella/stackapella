// A sync's start-over rule (js/stores/learning.js epochStep): the account's progress replaces the device's only when the
// account started over since; a device that started over where the account didn't keeps its lessons and merges.
import { epochStep, starredLessons } from '/js/stores/learning.js';
import { assert, test } from './harness.js';

test('learning: a start-over on the account replaces this device\'s progress; one on this device alone never loses it', () => {
    assert(epochStep(2, 1) === 'take', 'the account started over elsewhere: its progress wins');
    assert(epochStep(0, 1) === 'adopt', 'a guest who started over signs up to a new account: merge, and take its epoch');
    assert(epochStep(1, 1) === 'merge', 'the same epoch: merge as always');
    assert(epochStep(0, 0) === 'merge', 'neither ever started over');
});

test('learning: sign-up names the lessons finished with a star, the rest left out', () => {
    const progress = { lessons: { '1-comfortable': { stars: 2 }, '2-match': { stars: 0 }, 'bad id!': { stars: 3 }, '3-slide': { stars: 1 } } };
    assert(starredLessons(progress).join() === '1-comfortable,3-slide', starredLessons(progress).join());
    assert(starredLessons(null).length === 0 && starredLessons({}).length === 0, 'nothing kept: an empty list');
    const many = { lessons: Object.fromEntries(Array.from({ length: 250 }, (_, i) => [`l-${i}`, { stars: 1 }])) };
    assert(starredLessons(many).length === 200, 'at most 200, as the server takes');
});
