// The site's four-voice player (js/public/mixer.js), the Studio page's controls: a level per voice,
// solo (winning over the switches, two solos together) and pan, with the landing's players unchanged.
import { assert, test } from './harness.js';

const PARTS = ['soprano', 'alto', 'tenor', 'bass'];

/** A Studio player in the page, then the module (it sets up the players it finds when it loads). */
async function studio() {
    const root = document.createElement('div');
    root.dataset.player = '';
    root.hidden = true;
    const song = { song: 'test-studio', version: 1, tempo: 120, beats: 2, parts: PARTS.map((key, i) => ({ key, notes: [{ beat: 0, length: 2, pitch: 72 - i * 5 }] })) };
    root.innerHTML = `<script type="application/json" data-song>${JSON.stringify(song)}</script>
        <button data-play data-label-play="Play" data-label-stop="Stop"></button>
        ${PARTS.map((k) => `<div data-voice="${k}"><button data-toggle aria-checked="true"></button>
            <button data-solo="${k}" aria-pressed="false"></button>
            <input type="range" min="0" max="1" step="0.01" value="1" data-level="${k}">
            <input type="range" min="-1" max="1" step="0.01" value="0" data-pan="${k}"></div>`).join('')}`;
    document.body.append(root);
    const mixer = await import('/js/public/mixer.js');
    return { root, mixer };
}

test('site mixer: a solo wins over the switches, two sound together, and the fader scales a voice', async () => {
    const { root, mixer } = await studio();
    const { voiceLevel } = mixer;
    const none = new Set();
    assert(voiceLevel('alto', { on: true, soloed: none, forward: null }) === 1, 'on, nothing soloed: full');
    assert(voiceLevel('alto', { on: false, soloed: none, forward: null }) === 0, 'switched off: silent');
    assert(voiceLevel('alto', { on: false, soloed: new Set(['alto']), forward: null }) === 1, 'a solo wins over its switch');
    assert(voiceLevel('tenor', { on: true, soloed: new Set(['alto', 'bass']), forward: null }) === 0, 'another voice soloed: silent');
    assert(voiceLevel('bass', { on: true, soloed: new Set(['alto', 'bass']), forward: null }) === 1, 'two solos sound together');
    assert(Math.abs(voiceLevel('alto', { on: true, soloed: none, forward: null, fader: 0.25 }) - 0.25) < 1e-9, 'the fader scales it');

    // In the page: Solo presses, and the voices it leaves out say so.
    const soloAlto = root.querySelector('[data-solo="alto"]');
    soloAlto.click();
    assert(soloAlto.getAttribute('aria-pressed') === 'true', 'the solo is pressed');
    assert(root.querySelector('[data-voice="tenor"]').hasAttribute('data-silent') && !root.querySelector('[data-voice="alto"]').hasAttribute('data-silent'), 'the others are silent');
    root.querySelector('[data-solo="bass"]').click();
    assert(!root.querySelector('[data-voice="bass"]').hasAttribute('data-silent'), 'a second solo joins');
    soloAlto.click();
    root.querySelector('[data-solo="bass"]').click();
    assert(![...root.querySelectorAll('[data-voice]')].some((v) => v.hasAttribute('data-silent')), 'no solos: everyone back');
    // A solo's click stays its own: the row's switch doesn't move.
    assert(root.querySelector('[data-voice="alto"] [data-toggle]').getAttribute('aria-checked') === 'true', 'the switch untouched');

    // It plays with panners and faders in place, and moving them while it plays throws nothing.
    const play = root.querySelector('[data-play]');
    // The catalogue (real voices) is the server's: here it has none, so the synth sings every part.
    const fetched = window.fetch;
    window.fetch = (url, ...rest) => (String(url).endsWith('/api/templates') ? Promise.resolve(new Response(JSON.stringify({ data: [] }), { headers: { 'Content-Type': 'application/json' } })) : fetched(url, ...rest));
    play.click();
    // The voices render first (a test waits on the page; the sound itself is on the audio clock).
    for (let i = 0; i < 200 && !root.hasAttribute('data-playing'); i++) await new Promise((r) => setTimeout(r, 50));
    window.fetch = fetched;
    assert(root.hasAttribute('data-playing'), 'it plays');
    const pan = root.querySelector('[data-pan="alto"]');
    pan.value = '-0.8';
    pan.dispatchEvent(new Event('input'));
    const level = root.querySelector('[data-level="tenor"]');
    level.value = '0.3';
    level.dispatchEvent(new Event('input'));
    play.click();
    assert(!root.hasAttribute('data-playing'), 'Stop stops it');
    root.remove();
});
