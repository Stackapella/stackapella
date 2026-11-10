// The public site's find-your-note card (js/public/voice-capture.js): the app's capture without a framework.
// A held A3 is found after the hold, with the page's own words; a refused mic says why; the mic is let go.
import { mountCapture, WORDS } from '/js/public/voice-capture.js';
import { assert, test } from './harness.js';

const words = Object.fromEntries(WORDS.map((key) => [key, `[${key}]`]));
words.foundSub = '[found {note}]';
words.holdingSub = '[hold {seconds}]';

/** A stand-in mic: hands back the card's onFrame so the test can sing into it. */
function fakeMic() {
    const mic = { onFrame: null, stopped: false };
    mic.open = async ({ onFrame }) => {
        mic.onFrame = onFrame;
        return { stop: () => (mic.stopped = true) };
    };
    return mic;
}

const sing = (mic, hz, seconds, from = 10) => {
    for (let i = 0; i < Math.round(seconds * 60); i++) mic.onFrame?.({ at: from + i / 60, hz });
};

test('site capture: a held A3 is found, said in the page\'s words, and the mic let go', async () => {
    const el = document.createElement('div');
    document.body.append(el);
    const mic = fakeMic();
    let found = null;
    const capture = mountCapture(el, { words, open: mic.open, onFound: (midi) => (found = midi) });
    const card = el.querySelector('[data-ui=capture]');
    assert(el.querySelector('[data-start]').textContent === '[start]' && !el.querySelector('[data-start]').hidden, 'Start, in the page\'s word');
    await capture.start();
    assert(el.querySelector('[data-status]').textContent === '[listening]', 'listening');
    sing(mic, 220, 1);
    assert(card.dataset.phase === 'holding', `holding: ${card.dataset.phase}`);
    assert(el.querySelector('[data-note]').textContent === '·' && !el.querySelector('[data-tick] span + span'), 'no letter while it settles, in the ring or on the scale');
    assert(/^\[hold \d\]$/.test(el.querySelector('[data-status-sub]').textContent), 'the seconds left');
    assert(found === null && !mic.stopped, 'not found before the hold');
    sing(mic, 220, 1.3, 11);
    assert(found !== null && Math.round(found) === 57, `found A3: ${found}`);
    assert(card.dataset.phase === 'done' && mic.stopped, 'done, and the mic let go');
    assert(el.querySelector('[data-status-sub]').textContent === '[found A3]', el.querySelector('[data-status-sub]').textContent);
    assert(el.querySelector('[data-scale]').dataset.zoom === '2' && el.querySelector('[data-tick][data-active]'), 'locked on the note');
    assert(!el.querySelector('[data-again]').hidden && el.querySelector('[data-start]').hidden, 'Try another note');
    el.remove();
});

test('site capture: a wobble starts the hold again, and a refused mic says why', async () => {
    const el = document.createElement('div');
    document.body.append(el);
    const mic = fakeMic();
    let found = null;
    const capture = mountCapture(el, { words, open: mic.open, onFound: (midi) => (found = midi) });
    await capture.start();
    sing(mic, 220, 1.5);
    sing(mic, 330, 1.5, 11.5); // a new note: the hold starts over
    assert(found === null, 'a moved note isn\'t found from the old hold');
    el.remove();

    let reason = null;
    const refused = mountCapture(el, { words, open: async () => { throw Object.assign(new Error('denied'), { reason: 'denied' }); }, onError: (r) => (reason = r) });
    await refused.start();
    assert(reason === 'denied' && el.querySelector('[data-status]').textContent === '[noMic]', `refused: ${reason}`);
});
