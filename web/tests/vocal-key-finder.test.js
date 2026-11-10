// The vocal key finder (js/public/tools/vocal-key-finder.js): the key to sing a song in, in the singer's
// own octave first (dsp.js octaveFit), all 12 keys at a glance, the guitar's capo, and the screen.
import { capoFor, keyFor, keyName, keyStrip, mountKeyFinder, WORDS } from '/js/public/tools/vocal-key-finder.js';
import { RANGE_STORE, savedRange } from '/js/public/saved-range.js';
import { assert, test } from './harness.js';

const tenor = { low: 48, high: 69 };
const amazingGrace = { tonic: 7, minor: false, low: 62, high: 76 }; // in G, D4 to E5

test('key finder: a man sings a high song an octave down in its own key; a too-wide song says how far out', () => {
    const r = keyFor(tenor, amazingGrace);
    assert(r.fits && r.transpose === 0 && r.octave === -12 && keyName(r.tonic) === 'G', JSON.stringify(r));
    const low = keyFor({ low: 45, high: 64 }, { tonic: 2, minor: false, low: 57, high: 72 });
    assert(low.fits && low.octave === -12 && low.transpose === 0, JSON.stringify(low));
    const shifted = keyFor({ low: 57, high: 72 }, { tonic: 7, minor: false, low: 62, high: 76 });
    assert(shifted.fits && shifted.transpose === -4 && keyName(shifted.tonic) === 'E♭', JSON.stringify(shifted));
    const wide = keyFor({ low: 55, high: 67 }, { tonic: 0, minor: false, low: 55, high: 72 });
    assert(!wide.fits && wide.over + wide.under === 5, JSON.stringify(wide));
    const once = keyFor({ low: 57, high: 74 }, { tonic: 0, minor: false, low: 60, high: 79 }, { topOnce: true });
    assert(once.fits && once.transpose === -3 && once.over === 2, `the top only once, said as 2 over: ${JSON.stringify(once)}`);
    assert(keyName(1, true) === 'C♯m' && keyName(10) === 'B♭', 'key names');
});

test('key finder: the 12 keys at a glance, and where the capo goes', () => {
    const strip = keyStrip(tenor, amazingGrace);
    assert(strip.length === 12 && strip[6].transpose === 0 && strip[6].status === 'fits', JSON.stringify(strip[6]));
    assert(strip.some((k) => k.status !== 'fits'), 'not every key fits a tenor');
    assert(JSON.stringify(capoFor(2)) === '{"capo":0,"shape":2}', 'D: open');
    assert(capoFor(3).capo === 1 && capoFor(3).shape === 2, 'E flat: capo 1, D shapes');
    assert(capoFor(5).capo === 1 && capoFor(5).shape === 4, 'F: capo 1, E shapes');
    assert(capoFor(1, true).capo === 4 && capoFor(1, true).shape === 9, 'C sharp minor: capo 4, Am shapes');
    assert(capoFor(1, true, { maxFret: 3 }) === null, 'not past the fret allowed');
});

test('key finder: the screen answers in one line for singer and guitarist, from the saved range', () => {
    try {
        localStorage.removeItem('stackapella.tools.vocal-key-finder');
        localStorage.setItem(RANGE_STORE, JSON.stringify({ history: [{ at: 1, low: 48, high: 69 }] }));
    } catch (error) {
        console.warn('storage', error);
    }
    assert(JSON.stringify(savedRange()) === '{"low":48,"high":69}', 'the saved range');
    const words = { ...Object.fromEntries(WORDS.map((k) => [k, `[${k}]`])), guitar: 'capo :n, :shape shapes', guitarOpen: 'no capo, :key', range: ':low to :high', shiftDown: ':n down', play: 'Hear it in :key' };
    const el = document.createElement('div');
    const slot = document.createElement('div');
    document.body.append(slot, el);
    const finder = mountKeyFinder(el, { words, keySlot: slot, songs: [{ name: 'Amazing Grace', key: 'G', low: 62, high: 76 }] });
    const card = el.querySelector('[data-ui=tool]');
    const $ = (s) => card.querySelector(s);
    assert($('[data-voice-pick]').value === 'yours' && $('[data-voice-pick] option').textContent.includes('[yourRange]'), 'your range first');
    const voice = (v) => { $('[data-voice-pick]').value = v; $('[data-voice-pick]').dispatchEvent(new Event('change')); };
    assert($('[data-answer]').textContent === 'G' && $('[data-shift]').textContent.includes('[octaveDown]'), $('[data-shift]').textContent);
    assert($('[data-guitar]').textContent === 'no capo, G', $('[data-guitar]').textContent);
    assert($('[data-ui=tool-status]').dataset.tone === 'good' && card.querySelectorAll('[data-strip] button').length === 12, 'fits, and the strip');
    voice('mezzo');
    assert($('[data-answer]').textContent === 'G' && $('[data-shift]').textContent === '[asWritten]', 'a mezzo sings it as written');
    voice('alto');
    assert($('[data-answer]').textContent === 'F' && $('[data-shift]').textContent === '2 down' && $('[data-guitar]').textContent === 'capo 1, E shapes', `${$('[data-answer]').textContent} ${$('[data-shift]').textContent} ${$('[data-guitar]').textContent}`);
    slot.querySelector('[data-step="1"]').click();
    assert(slot.querySelector('[data-step-value]').textContent === 'A♭', 'the song key moves');
    $('[data-strip] [data-strip="0"]').click();
    assert($('[data-ui=tool-primary]').textContent.includes('Hear it in'), 'Play names the key');
    finder.stop();
    el.remove();
    slot.remove();
    try {
        localStorage.removeItem(RANGE_STORE);
    } catch (error) {
        console.warn('storage', error);
    }
});
