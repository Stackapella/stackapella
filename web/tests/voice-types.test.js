// The range test's voice types (theory.js voiceTypes): the guess the result phrases as "fits the usual X range".
import { CHOIR_PARTS, VOICE_TYPES, voiceTypes } from '/js/theory.js';
import { assert, test } from './harness.js';

test('voice types: a range is placed by its low note first, and every type has a choir part', () => {
    const best = (low, high) => voiceTypes({ low, high })[0].type;
    assert(best(43, 64) === 'baritone', `G2 to E4: ${best(43, 64)}`);
    assert(best(40, 62) === 'bass', `E2 to D4: ${best(40, 62)}`);
    assert(best(48, 69) === 'tenor', `C3 to A4: ${best(48, 69)}`);
    assert(best(53, 72) === 'alto', `F3 to C5: ${best(53, 72)}`);
    assert(best(60, 81) === 'soprano', `C4 to A5: ${best(60, 81)}`);
    assert(best(57, 77) === 'mezzo', `A3 to F5: ${best(57, 77)}`);
    assert(Object.keys(VOICE_TYPES).every((t) => CHOIR_PARTS[t]), 'a part for each');
    const ranked = voiceTypes({ low: 43, high: 64 });
    assert(ranked.length === 6 && ranked.find((r) => r.type === 'soprano').overlap === 4, 'every type, with its overlap');
});
