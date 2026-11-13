// A range carried in from the free range test (js/tool-range.js; docs/retention-plan.md, idea 7): letter names read as
// MIDI, a range a voice could have, nothing else.
import { noteMidi, parseRange } from '/js/tool-range.js';
import { assert, test } from './harness.js';

test('tool range: letter names read as MIDI, sharps and flats included', () => {
    assert(noteMidi('A2') === 45 && noteMidi('D4') === 62 && noteMidi('C4') === 60, 'naturals');
    assert(noteMidi('C#4') === 61 && noteMidi('C♯4') === 61 && noteMidi('Bb3') === 58 && noteMidi('B♭3') === 58, 'accidentals');
    assert(noteMidi('H2') === null && noteMidi('A') === null && noteMidi('<b>') === null && noteMidi('') === null, 'not a note');
});

test("tool range: a voice's range or nothing", () => {
    assert(JSON.stringify(parseRange('A2-D4')) === '{"low":45,"high":62}', JSON.stringify(parseRange('A2-D4')));
    assert(parseRange('D4-A2') === null, 'backwards');
    assert(parseRange('C4-D4') === null, 'narrower than a fourth');
    assert(parseRange('C0-C8') === null, 'past what a voice sings');
    assert(parseRange('A2-D4-E5') === null && parseRange('A2') === null && parseRange(null) === null, 'not two names');
});
