// Parts said in words before their notes (js/part-words.js; the first-session review's proposal 2): by where each part
// sits in its own song, so SATB, a barbershop tenor above the tune, a duet and a one-part song each read right.
import { partWords, partWordsKey } from '/js/part-words.js';
import { assert, test } from './harness.js';

const part = (key, pitch, melody = false) => ({ key, name: key, ...(melody ? { melody: true } : {}), guide: { type: 'notes', notes: [pitch - 2, pitch, pitch + 2].map((p, i) => ({ beat: i, length: 1, pitch: p })) } });
const keysOf = (template) => template.parts.map((p) => partWordsKey(template, p.key)?.key ?? null).join();

test('part words: SATB with the tune on top reads tune, under the tune, under the alto, lowest', () => {
    const satb = { parts: [part('soprano', 72, true), part('alto', 65), part('tenor', 57), part('bass', 48)] };
    assert(keysOf(satb) === 'tuneHighest,underTune,underMen,lowest', keysOf(satb));
    assert(partWords(satb, 'soprano') === 'The tune, the highest part', partWords(satb, 'soprano'));
    assert(partWords(satb, 'tenor') === 'Under the alto, often men', partWords(satb, 'tenor'));
    assert(partWords(satb, 'bass') === 'The lowest part', partWords(satb, 'bass'));
});

test('part words: a barbershop tenor sits above the tune, the lead is the tune', () => {
    const shop = { parts: [part('tenor', 67), part('lead', 62, true), part('baritone', 57), part('bass', 48)] };
    assert(keysOf(shop) === 'aboveTune,tune,underTune,lowest', keysOf(shop));
    assert(partWords(shop, 'lead') === 'The tune', partWords(shop, 'lead'));
});

test('part words: a duet, a tune in the middle, and a part that can\'t be placed', () => {
    assert(keysOf({ parts: [part('soprano', 72, true), part('alto', 65)] }) === 'tuneHighest,lowest', 'a duet');
    const middle = { parts: [part('soprano', 72), part('alto', 65, true), part('bass', 48)] };
    assert(keysOf(middle) === 'aboveTune,tune,lowest', `the tune in the middle: ${keysOf(middle)}`);
    assert(keysOf({ parts: [part('melody', 64, true)] }) === 'tune', 'one part');
    assert(partWords({ parts: [part('soprano', 72, true)] }, 'nobody') === '', 'no such part');
    assert(partWords({ parts: [{ key: 'drums', name: 'Drums', guide: { notes: [] } }, part('soprano', 72, true)] }, 'drums') === '', 'a part with no notes');
});
