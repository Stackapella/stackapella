// The mic remembered by name across the site and the app (js/mic-choice.js): the name finds the same
// mic again, Chrome's "Default - " copies and aliases aside, and no name or no match keeps the default.
import { pickByLabel } from '/js/mic-choice.js';
import { assert, test } from './harness.js';

const MICS = [
    { deviceId: 'default', label: 'Default - MacBook Pro Microphone' },
    { deviceId: 'a1', label: 'MacBook Pro Microphone' },
    { deviceId: 'b2', label: 'Shure MV7' },
    { deviceId: 'communications', label: 'Communications - Shure MV7' },
];

test('mic choice: the remembered name picks that mic, ignoring case and Chrome\'s "Default - " copy', () => {
    assert(pickByLabel(MICS, 'Shure MV7') === 'b2', 'by its name');
    assert(pickByLabel(MICS, 'shure mv7') === 'b2', 'ignoring case');
    assert(pickByLabel(MICS, 'Default - MacBook Pro Microphone') === 'a1', 'a remembered "Default - " name is the mic itself, never the alias');
});

test('mic choice: no name, or a name that isn\'t there, keeps the default (null)', () => {
    assert(pickByLabel(MICS, '') === null, 'no name');
    assert(pickByLabel(MICS, 'AirPods Pro') === null, 'not plugged in here');
    assert(pickByLabel([], 'Shure MV7') === null, 'no mics listed yet');
    assert(pickByLabel(undefined, 'Shure MV7') === null, 'nothing listed');
});
