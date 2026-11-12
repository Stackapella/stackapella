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

// The whole song (Hillel: "let's always play the full songs"): the rest joins the opening line on the audio clock, and a
// card's part rows follow whoever is singing.
test('public mixer: the rest of the song joins at the line\'s end, or where the song has got to when it comes late', async () => {
    const { joinAt } = await import('/js/public/mixer.js');
    const on = joinAt(10, 12, 15);
    assert(on.when === 22 && on.offset === 12, `in time: ${JSON.stringify(on)}`);
    const late = joinAt(10, 12, 25.5);
    assert(late.when === 25.5 && late.offset === 15.5, `late: ${JSON.stringify(late)}`);
});

test('public mixer: a card\'s part rows light while their part sings a note', async () => {
    const { sounding } = await import('/js/public/mixer.js');
    const parts = [
        { key: 'soprano', notes: [{ beat: 0, length: 2 }, { beat: 4, length: 1 }] },
        { key: 'bass', notes: [{ beat: 1, length: 3 }] },
    ];
    const at = (seconds) => [...sounding(parts, seconds, 0.5)].sort().join(',');
    assert(at(0) === 'soprano', at(0));
    assert(at(0.75) === 'bass,soprano', at(0.75));
    assert(at(1.5) === 'bass', `a rest between notes: ${at(1.5)}`);
    assert(at(2.25) === 'soprano', `the bass has ended: ${at(2.25)}`);
    assert(at(3) === '', 'after the song');
});

test('play state: a press shows loading at once, a slow load a quiet hint, a failure its words; playing clears them', async () => {
    const { playState } = await import('/js/public/play-state.js');
    const root = document.createElement('div');
    const button = document.createElement('button');
    button.setAttribute('aria-label', 'Play');
    root.append(button);
    document.body.append(root);
    const words = { loading: 'Loading...', slow: 'Still loading', failed: 'Couldn\'t play, tap to try again' };
    const states = playState(button, root, { slow: 30, words });
    const hint = () => button.querySelector('.s-play-hint').textContent;
    states.loading();
    assert(root.hasAttribute('data-loading') && button.getAttribute('aria-busy') === 'true' && hint() === '', 'loading at once, no hint yet');
    await new Promise((resolve) => setTimeout(resolve, 60));
    assert(root.hasAttribute('data-slow') && hint() === 'Still loading', 'a slow load says so');
    states.playing();
    assert(!root.hasAttribute('data-loading') && !root.hasAttribute('data-slow') && !button.hasAttribute('aria-busy') && hint() === '', 'playing clears it');
    states.failed();
    assert(root.hasAttribute('data-failed') && hint() === words.failed && button.getAttribute('aria-label') === words.failed, 'a failure says so');
    states.loading();
    assert(!root.hasAttribute('data-failed') && button.getAttribute('aria-label') === 'Play', 'trying again puts the label back');
    states.idle();
    root.remove();
});
