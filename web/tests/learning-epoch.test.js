// A sync's start-over rule (js/stores/learning.js epochStep): the account's progress replaces the device's only when the
// account started over since; a device that started over where the account didn't keeps its lessons and merges.
import { epochStep } from '/js/stores/learning.js';
import { assert, test } from './harness.js';

test('learning: a start-over on the account replaces this device\'s progress; one on this device alone never loses it', () => {
    assert(epochStep(2, 1) === 'take', 'the account started over elsewhere: its progress wins');
    assert(epochStep(0, 1) === 'adopt', 'a guest who started over signs up to a new account: merge, and take its epoch');
    assert(epochStep(1, 1) === 'merge', 'the same epoch: merge as always');
    assert(epochStep(0, 0) === 'merge', 'neither ever started over');
});
