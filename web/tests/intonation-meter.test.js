// The intonation meter (js/public/tools/intonation-meter.js): a sung pitch placed in the chord against
// pure (just) tuning from just.js, in any octave; and the screen ringing on a pure third.
import { mountIntonationMeter, toneOf, WORDS } from '/js/public/tools/intonation-meter.js';
import { assert, test } from './harness.js';

test('intonation meter: a pure third rings, the piano\'s is 14 cents sharp of it, any octave', () => {
    const pureThird = toneOf(64 - 0.137, 48, 'maj');
    assert(pureThird.semis === 4 && pureThird.cents === 0 && pureThird.pure === -13.7, JSON.stringify(pureThird));
    const pianoThird = toneOf(64, 48, 'maj');
    assert(pianoThird.semis === 4 && pianoThird.cents === 14, JSON.stringify(pianoThird));
    assert(toneOf(55, 48, 'maj').cents === -2 && toneOf(55, 48, 'maj').semis === 7, 'the piano\'s fifth is 2 cents flat of pure');
    assert(toneOf(58, 48, 'dom7').semis === 10 && toneOf(58, 48, 'dom7').cents === 31, 'the piano\'s seventh is 31 cents sharp of the barbershop one');
    assert(toneOf(63 + 0.156, 48, 'min').semis === 3 && toneOf(63 + 0.156, 48, 'min').cents === 0, 'a pure minor third, 16 over the piano\'s');
    assert(toneOf(40 - 0.137, 48, 'maj').semis === 4, 'an octave below the root still counts');
    assert(toneOf(62, 48, 'maj').semis === null, 'a D over C major is not in the chord');
});

test('intonation meter: the screen says ringing on a pure third, and which way when off', async () => {
    try {
        localStorage.removeItem('stackapella.tools.just-intonation-tuner');
    } catch (error) {
        console.warn('storage', error);
    }
    const words = { ...Object.fromEntries(WORDS.map((k) => [k, `[${k}]`])), heldFor: ':n s' };
    const el = document.createElement('div');
    const slot = document.createElement('div');
    document.body.append(slot, el);
    let onFrame = null;
    const meter = mountIntonationMeter(el, { words, keySlot: slot, open: async (o) => { onFrame = o.onFrame; return { stop() {}, deviceId: () => '' }; } });
    const card = el.querySelector('[data-ui=tool]');
    const $ = (s) => card.querySelector(s);
    assert(slot.querySelector('[data-step-value]').textContent === 'C3', 'the root, C3');
    assert(card.querySelectorAll('[data-tones] button').length === 3, 'major: root, third, fifth');
    await meter.start();
    const hz = (midi) => 440 * 2 ** ((midi - 69) / 12);
    const sing = (midi, from) => { for (let i = 0; i < 60; i++) onFrame({ at: from + i / 60, hz: hz(midi), loudness: -24, clarity: 0.95 }); };
    sing(64 - 0.137, 0);
    assert(card.dataset.ringing === 'true' && $('[data-ui=tool-status]').dataset.tone === 'good', $('[data-ui=tool-status]').textContent);
    assert($('[data-tone-name]').textContent === '[tone4]' && $('[data-tones] [data-tone="4"]').getAttribute('aria-pressed') === 'true', 'the third lit');
    sing(64.2, 1);
    assert(card.dataset.ringing === 'false' && $('[data-ui=tool-status]').textContent.includes('[high]'), $('[data-ui=tool-status]').textContent);
    $('[data-segment=dom7]').click();
    assert(card.querySelectorAll('[data-tones] button').length === 4, 'the barbershop seventh adds a tone');
    meter.stop();
    assert(card.dataset.live === 'false', 'off');
    el.remove();
    slot.remove();
});
