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
    const fetched = window.fetch;
    // The song's own answer and the catalogue are the server's: here neither has a real voice (the song isn't one), so the
    // synth sings every part.
    window.fetch = (url, ...rest) => (String(url).includes('/api/templates') ? Promise.resolve(new Response(JSON.stringify({ data: [] }), { status: String(url).endsWith('/api/templates') ? 200 : 404, headers: { 'Content-Type': 'application/json' } })) : fetched(url, ...rest));
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

// The song's render (Slow 4G, 2026-11-13: four voices took 14 s, the render under 5): Play streams it while every part
// sounds as mixed, a part heard alone moves to the voices apart, and a part's tap while nothing plays plays it.
test('public mixer: the render this browser plays is the m4a where it can, else the mp3', async () => {
    const { renderUrl } = await import('/js/public/mixer.js');
    const both = { m4a: 'a.m4a', mp3: 'a.mp3' };
    assert(renderUrl(both, () => 'maybe') === 'a.m4a', 'the m4a where it plays');
    assert(renderUrl(both, () => '') === 'a.mp3', 'else the mp3');
    assert(renderUrl({ m4a: null, mp3: 'a.mp3' }, () => 'maybe') === 'a.mp3', 'only an mp3');
    assert(renderUrl(null, () => 'maybe') === null && renderUrl(undefined, () => 'maybe') === null, 'no render');
});

test('public mixer: Play streams the render, a part alone moves to the voices apart, and a part tap while stopped plays it', async () => {
    const root = document.createElement('div');
    root.dataset.player = '';
    root.hidden = true;
    // A render the test server has (any short file stands in), and two voices in rows as a song page has them.
    const song = { song: 'test-render', version: 1, tempo: 120, beats: 8, audio: { m4a: null, mp3: '/sounds/stars-3.mp3' }, parts: ['soprano', 'bass'].map((key, i) => ({ key, notes: [{ beat: 0, length: 8, pitch: 72 - i * 12 }] })) };
    root.innerHTML = `<script type="application/json" data-song>${JSON.stringify(song)}</script>
        <button data-play data-label-play="Play" data-label-stop="Stop"></button>
        ${song.parts.map(({ key }) => `<div data-voice="${key}"><button data-hear-alone aria-pressed="false" data-label="${key} alone" data-label-back="All"></button>
            <button data-pick aria-checked="false" data-label="I sing ${key}" data-label-you="You"></button></div>`).join('')}`;
    document.body.append(root);
    const asked = [];
    const played = [];
    const fetched = window.fetch;
    window.fetch = (url, ...rest) => {
        asked.push(String(url));
        return String(url).includes('/api/templates') ? Promise.resolve(new Response(JSON.stringify({ data: [] }), { status: String(url).endsWith('/api/templates') ? 200 : 404, headers: { 'Content-Type': 'application/json' } })) : fetched(url, ...rest);
    };
    const playMedia = HTMLMediaElement.prototype.play;
    HTMLMediaElement.prototype.play = function () {
        played.push(this.currentSrc || this.src);
        return playMedia.call(this);
    };
    const until = async (what, ok) => {
        for (let i = 0; i < 200 && !ok(); i++) await new Promise((r) => setTimeout(r, 50));
        assert(ok(), what);
    };
    try {
        await import('/js/public/mixer.js?render');
        const play = root.querySelector('[data-play]');
        play.click();
        await until('Play plays', () => root.hasAttribute('data-playing'));
        assert(played.some((src) => src.endsWith('/sounds/stars-3.mp3')), `the render plays: ${played}`);
        assert(!asked.some((url) => url.endsWith('/api/templates')), `no catalogue for the render: ${asked}`);
        // The bass alone: the voices apart take over, at the same point.
        const bassAlone = root.querySelector('[data-voice="bass"] [data-hear-alone]');
        bassAlone.click();
        assert(bassAlone.getAttribute('aria-pressed') === 'true', 'the bass is heard alone');
        assert(root.hasAttribute('data-playing'), 'it carries on playing');
        play.click();
        assert(!root.hasAttribute('data-playing'), 'Stop stops it');
        // A tap on a part while nothing plays plays that part (it used to only switch the others off).
        played.length = 0;
        const sopranoAlone = root.querySelector('[data-voice="soprano"] [data-hear-alone]');
        sopranoAlone.click();
        await until('a part tap plays', () => root.hasAttribute('data-playing'));
        assert(sopranoAlone.getAttribute('aria-pressed') === 'true', 'the soprano alone');
        assert(!played.length, `a part alone is the voices apart, not the render: ${played}`);
        play.click();
        assert(!root.hasAttribute('data-playing'), 'Stop stops the part too');
    } finally {
        window.fetch = fetched;
        HTMLMediaElement.prototype.play = playMedia;
        root.remove();
    }
});

// A render that won't play is told to our Sentry once a page (public/report.js), with the browser and nothing about
// who it was, so a browser that can't stream it shows up before a singer tells us.
test('public report: a note is a warning with the page and the browser only, sent once a page through the tunnel', async () => {
    const { noteEvent, note } = await import('/js/public/report.js');
    const ua = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.1 Mobile/15E148 Safari/604.1';
    const event = noteEvent('A song render could not play, the voices sang it', { error: 'NotSupportedError' }, { path: '/songs/danny-boy', origin: 'https://stackapella.com', ua, hostname: 'stackapella.com' });
    assert(event.level === 'warning' && event.message.formatted.startsWith('A song render') && event.environment === 'production', 'a warning in production');
    assert(event.tags.browser === 'Safari 18' && event.tags.os === 'iOS' && event.tags.error === 'NotSupportedError', `the browser and the error: ${JSON.stringify(event.tags)}`);
    assert(event.request.url === 'https://stackapella.com/songs/danny-boy' && !('user' in event), 'the page, never who');

    const posted = [];
    const fetched = window.fetch;
    window.fetch = (url, init) => {
        if (String(url).endsWith('/api/auth/config')) return Promise.resolve(new Response(JSON.stringify({ data: { sentryDsn: 'https://key@o1.ingest.sentry.io/2' } }), { headers: { 'Content-Type': 'application/json' } }));
        if (String(url).endsWith('/log/errors')) {
            posted.push(init.body);
            return Promise.resolve(new Response('', { status: 200 }));
        }
        return fetched(url, init);
    };
    try {
        note('A test note', { error: 'X' });
        note('A test note', { error: 'X' });
        for (let i = 0; i < 40 && !posted.length; i++) await new Promise((r) => setTimeout(r, 25));
        await new Promise((r) => setTimeout(r, 50));
        assert(posted.length === 1, `sent once: ${posted.length}`);
        const [header, , body] = posted[0].split('\n');
        assert(JSON.parse(header).dsn === 'https://key@o1.ingest.sentry.io/2', 'with our DSN, for the tunnel');
        assert(JSON.parse(body).message.formatted === 'A test note', 'the note itself');
    } finally {
        window.fetch = fetched;
    }
});

test('site mixer: a song asks for passes and joins only in its own data, and a plain song asks for neither', async () => {
    const { buildOf } = await import('/js/public/mixer.js');
    const parts = ['soprano', 'alto', 'tenor', 'bass'].map((key) => ({ key }));
    const plain = buildOf({ parts }, 48);
    assert(plain.passes === 1 && plain.joins === null, 'a plain song: one pass, no joins');
    const built = buildOf({ parts, passes: 2, joins: { alto: 12, tenor: 24, bass: 36 } }, 48);
    assert(built.passes === 2 && JSON.stringify([...built.joins]) === JSON.stringify([['alto', 12], ['tenor', 24], ['bass', 36]]), 'two passes, three joins');
    // What isn't a pass count or a join is left out: a voice from the start, one that isn't the song's, a beat outside it.
    const odd = buildOf({ parts, passes: 2.5, joins: { soprano: 0, alto: 12, piano: 8, tenor: 48, bass: '36' } }, 48);
    assert(odd.passes === 1 && JSON.stringify([...odd.joins]) === JSON.stringify([['alto', 12]]), `only the alto joins: ${JSON.stringify([...odd.joins])}`);
    assert(buildOf({ parts, passes: 9 }, 48).passes === 1 && buildOf({ parts, passes: 1 }, 48).passes === 1, 'a pass count out of range is one');
    assert(buildOf({ parts, joins: [] }, 48).joins === null && buildOf({ parts, joins: null }, 48).joins === null, 'no joins is none');
});

test('site mixer: every pass starts on the same clock, and a join is a ramp that ends on its beat', async () => {
    const { passStarts, joinRamps, joinAt } = await import('/js/public/mixer.js');
    assert(JSON.stringify(passStarts(10.1, 36, 2)) === JSON.stringify([10.1, 46.1]), 'the second pass starts as the first ends');
    assert(JSON.stringify(passStarts(3, 36, 1)) === JSON.stringify([3]), 'one pass: its own start');
    const ramps = joinRamps(new Map([['alto', 12], ['tenor', 24], ['bass', 36]]), 100, 0.75, 0.12);
    const near = (a, b) => Math.abs(a - b) < 1e-9;
    assert(near(ramps.get('alto').to, 109) && near(ramps.get('alto').from, 108.88), `the alto is full on beat 12, 9 s in: ${JSON.stringify(ramps.get('alto'))}`);
    assert(near(ramps.get('tenor').to, 118) && near(ramps.get('bass').to, 127), 'the tenor on beat 24, the bass on 36');
    // A join in the song's first moments never starts before the play does.
    const early = joinRamps(new Map([['alto', 0.1]]), 100, 0.75, 0.12).get('alto');
    assert(early.from === 100 && near(early.to, 100.075), `clamped to the start: ${JSON.stringify(early)}`);
    // A further pass whose voices came late starts now, that far into the pass (the same sum as the rest joining a line).
    const late = joinAt(46.1, 0, 47);
    assert(late.when === 47 && near(late.offset, 0.9), `late into the second pass: ${JSON.stringify(late)}`);
    const onTime = joinAt(46.1, 0, 20);
    assert(onTime.when === 46.1 && onTime.offset === 0, 'on time: at the pass\'s own start');
});

test('site mixer: a song in two passes plays as the voices apart, twice as long, and a voice alone still switches', async () => {
    const root = document.createElement('div');
    root.dataset.player = '';
    root.hidden = true;
    const keys = ['soprano', 'alto'];
    const song = { song: 'test-build', version: 1, tempo: 120, beats: 4, passes: 2, joins: { alto: 2 }, audio: { mp3: '/never/asked-for.mp3' }, parts: keys.map((key, i) => ({ key, notes: [{ beat: 0, length: 4, pitch: 72 - i * 5 }] })) };
    root.innerHTML = `<script type="application/json" data-song>${JSON.stringify(song)}</script>
        <button data-play data-label-play="Play" data-label-stop="Stop"></button><span data-time></span>
        ${keys.map((k) => `<div data-voice="${k}"><button data-hear-alone aria-pressed="false" data-label="Hear" data-label-back="All"></button></div>`).join('')}`;
    document.body.append(root);
    // As a page with such lanes does: a press on a lane's button stays the button's (the voice's own element also switches).
    for (const button of root.querySelectorAll('[data-hear-alone]')) button.addEventListener('click', (event) => event.stopPropagation());
    const fetched = window.fetch;
    const asked = [];
    window.fetch = (url, ...rest) => {
        asked.push(String(url));
        return String(url).includes('/api/templates') ? Promise.resolve(new Response(JSON.stringify({ data: [] }), { status: String(url).endsWith('/api/templates') ? 200 : 404, headers: { 'Content-Type': 'application/json' } })) : fetched(url, ...rest);
    };
    try {
        // The module is loaded already (the Studio test): a player set up by hand, as the module does for a page's own.
        const mixer = await import('/js/public/mixer.js?build');
        assert(typeof mixer.buildOf === 'function', 'the module');
        assert(root.hasAttribute('data-ready'), 'the player is set up');
        assert(root.querySelector('[data-time]').textContent === '0:00 / 0:04', `the time is both passes: ${root.querySelector('[data-time]').textContent}`);
        root.querySelector('[data-play]').click();
        for (let i = 0; i < 200 && !root.hasAttribute('data-playing'); i++) await new Promise((r) => setTimeout(r, 50));
        assert(root.hasAttribute('data-playing'), 'it plays');
        assert(!asked.some((url) => url.includes('asked-for.mp3')) && !performance.getEntriesByType('resource').some((r) => r.name.includes('asked-for.mp3')), 'the render is never asked for');
        // A voice alone while it builds, then all again: nothing throws, and it plays on.
        const alto = root.querySelector('[data-voice="alto"] [data-hear-alone]');
        alto.click();
        assert(alto.getAttribute('aria-pressed') === 'true' && root.querySelector('[data-voice="alto"]').hasAttribute('data-solo'), 'the alto alone');
        alto.click();
        assert(alto.getAttribute('aria-pressed') === 'false' && root.hasAttribute('data-playing'), 'all again, still playing');
        root.querySelector('[data-play]').click();
        assert(!root.hasAttribute('data-playing') && root.querySelector('[data-time]').textContent === '0:00 / 0:04', 'Stop puts it back at the start');
    } finally {
        window.fetch = fetched;
        root.remove();
    }
});

test('site voices: a download is told as it arrives, with the file\'s length when the server says it', async () => {
    const { readBytes } = await import('/js/public/app-voices.js');
    const pieces = [new Uint8Array([1, 2, 3]), new Uint8Array([4, 5]), new Uint8Array([6])];
    const stream = () => new ReadableStream({ start(controller) { pieces.forEach((piece) => controller.enqueue(piece)); controller.close(); } });
    const told = [];
    const bytes = await readBytes(new Response(stream(), { headers: { 'Content-Length': '6' } }), (loaded, total) => told.push([loaded, total]));
    assert(JSON.stringify([...new Uint8Array(bytes)]) === '[1,2,3,4,5,6]', 'every byte, in order');
    assert(JSON.stringify(told[0]) === '[0,6]' && JSON.stringify(told.at(-1)) === '[6,6]', `from nothing to all of it: ${JSON.stringify(told)}`);
    assert(told.every(([loaded], i) => i === 0 || loaded >= told[i - 1][0]), 'never backwards');
    // No length from the server: the total is 0 until the end, when it's what arrived.
    const unknown = [];
    await readBytes(new Response(stream()), (loaded, total) => unknown.push([loaded, total]));
    assert(JSON.stringify(unknown[0]) === '[0,0]' && JSON.stringify(unknown.at(-1)) === '[6,6]', `the length unknown: ${JSON.stringify(unknown)}`);
});

test('site mixer: a song that asks for the recorded voices only never sings with the practice voice, and says when they can\'t be had', async () => {
    const root = document.createElement('div');
    root.dataset.player = '';
    root.hidden = true;
    const keys = ['soprano', 'alto'];
    const song = { song: 'test-recorded', version: 1, tempo: 120, beats: 4, realOnly: true, parts: keys.map((key, i) => ({ key, notes: [{ beat: 0, length: 4, pitch: 72 - i * 5 }] })) };
    root.innerHTML = `<script type="application/json" data-song>${JSON.stringify(song)}</script><button data-play data-label-play="Play" data-label-stop="Stop"></button>`;
    document.body.append(root);
    const fetched = window.fetch;
    const Made = window.Worker;
    const workers = [];
    window.Worker = class extends Made {
        constructor(url, options) {
            workers.push(String(url));
            super(url, options);
        }
    };
    // One second of a tone as a WAV, 8000 frames a second.
    const wav = () => {
        const frames = 8000;
        const view = new DataView(new ArrayBuffer(44 + frames * 2));
        const text = (at, value) => [...value].forEach((c, i) => view.setUint8(at + i, c.charCodeAt(0)));
        text(0, 'RIFF'); view.setUint32(4, 36 + frames * 2, true); text(8, 'WAVEfmt '); view.setUint32(16, 16, true); view.setUint16(20, 1, true); view.setUint16(22, 1, true);
        view.setUint32(24, 8000, true); view.setUint32(28, 16000, true); view.setUint16(32, 2, true); view.setUint16(34, 16, true); text(36, 'data'); view.setUint32(40, frames * 2, true);
        for (let i = 0; i < frames; i++) view.setInt16(44 + i * 2, Math.round(Math.sin(i / 3) * 4000), true);
        return new Uint8Array(view.buffer);
    };
    let has = ['soprano']; // the parts whose file can be had: the alto's is refused at first
    window.fetch = (url, ...rest) => {
        const name = String(url);
        if (name.endsWith('/api/templates/test-recorded')) {
            const voices = keys.map((key) => ({ id: key, partKey: key, sampleRate: 8000, frames: 8000, offsetFrames: 0, default: true, url: `/never/${key}.wav` }));
            return Promise.resolve(new Response(JSON.stringify({ data: { template: { version: 1, parts: [] }, voices } }), { headers: { 'Content-Type': 'application/json' } }));
        }
        if (name.includes('/never/')) return Promise.resolve(has.some((key) => name.endsWith(`/${key}.wav`)) ? new Response(wav(), { headers: { 'Content-Length': String(44 + 16000) } }) : new Response('', { status: 503 }));
        return fetched(url, ...rest);
    };
    const loads = [];
    root.addEventListener('mixer-load', (event) => loads.push(event.detail));
    const settled = async (done) => {
        for (let i = 0; i < 200 && !done(); i++) await new Promise((r) => setTimeout(r, 50));
    };
    try {
        await import('/js/public/mixer.js?recorded');
        const play = root.querySelector('[data-play]');
        play.click();
        assert(root.hasAttribute('data-loading'), 'the press says it is loading');
        await settled(() => root.hasAttribute('data-failed'));
        assert(root.hasAttribute('data-failed') && !root.hasAttribute('data-playing'), 'a recording that can\'t be had: it fails, and nothing plays');
        assert(workers.length === 0, `the practice voice is never made: ${workers}`);
        // The recordings can all be had now: the next press asks again, tells the download, and plays.
        has = keys;
        loads.length = 0;
        play.click();
        await settled(() => root.hasAttribute('data-playing'));
        assert(root.hasAttribute('data-playing') && !root.hasAttribute('data-failed'), 'the next press plays');
        const last = loads.at(-1);
        assert(last && last.total === 2 * (44 + 16000) && last.loaded === last.total, `the download was told, over both voices: ${JSON.stringify(last)}`);
        assert(loads.every((load) => load.total === 0 || load.loaded <= load.total), 'never more than all of it');
        assert(workers.length === 0, 'still no practice voice');
        play.click();
        assert(!root.hasAttribute('data-playing'), 'stopped');
    } finally {
        window.fetch = fetched;
        window.Worker = Made;
        root.remove();
    }
});

test('site mixer: a page that asks has each recorded voice\'s breaths ducked before it plays, in the gaps of its own notes', async () => {
    const root = document.createElement('div');
    root.dataset.player = '';
    root.hidden = true;
    // One part: a note of two beats, two beats' rest, a note of two beats, at 120 a minute (three seconds).
    const notes = [{ beat: 0, length: 2, pitch: 57 }, { beat: 4, length: 2, pitch: 57 }];
    const song = { song: 'test-breaths', version: 1, tempo: 120, beats: 6, realOnly: true, quietBreaths: true, parts: [{ key: 'tenor', notes }] };
    root.innerHTML = `<script type="application/json" data-song>${JSON.stringify(song)}</script><button data-play data-label-play="Play" data-label-stop="Stop"></button>`;
    document.body.append(root);
    // Its recording: the two notes, and a breath (noise) in the rest, 1.3 to 1.7 s in.
    const rate = 16000;
    const frames = 3 * rate;
    const view = new DataView(new ArrayBuffer(44 + frames * 2));
    const text = (at, value) => [...value].forEach((c, i) => view.setUint8(at + i, c.charCodeAt(0)));
    text(0, 'RIFF'); view.setUint32(4, 36 + frames * 2, true); text(8, 'WAVEfmt '); view.setUint32(16, 16, true); view.setUint16(20, 1, true); view.setUint16(22, 1, true);
    view.setUint32(24, rate, true); view.setUint32(28, rate * 2, true); view.setUint16(32, 2, true); view.setUint16(34, 16, true); text(36, 'data'); view.setUint32(40, frames * 2, true);
    let seed = 99;
    const noise = () => ((seed = (seed * 1103515245 + 12345) % 2147483648) / 1073741824) - 1;
    for (let i = 0; i < frames; i++) {
        const t = i / rate;
        const value = t < 1 || t >= 2 ? 0.3 * Math.sin(2 * Math.PI * 220 * t) : t >= 1.3 && t < 1.7 ? 0.1 * noise() : 0;
        view.setInt16(44 + i * 2, Math.round(value * 32767), true);
    }
    const fetched = window.fetch;
    window.fetch = (url, ...rest) => {
        const name = String(url);
        if (name.endsWith('/api/templates/test-breaths')) {
            const answer = { data: { template: { version: 1, parts: [{ key: 'tenor', guide: { notes } }] }, voices: [{ id: 'v', partKey: 'tenor', sampleRate: rate, frames, offsetFrames: 0, default: true, url: '/never/tenor-breath.wav' }] } };
            return Promise.resolve(new Response(JSON.stringify(answer), { headers: { 'Content-Type': 'application/json' } }));
        }
        if (name.includes('/never/tenor-breath.wav')) return Promise.resolve(new Response(view.buffer.slice(0), { headers: { 'Content-Length': String(view.byteLength) } }));
        return fetched(url, ...rest);
    };
    // What the player hands the audio: every buffer it fills is kept.
    const copy = AudioBuffer.prototype.copyToChannel;
    const filled = [];
    AudioBuffer.prototype.copyToChannel = function (source, ...rest) {
        filled.push(Float32Array.from(source));
        return copy.call(this, source, ...rest);
    };
    try {
        await import('/js/public/mixer.js?breaths');
        root.querySelector('[data-play]').click();
        for (let i = 0; i < 200 && !root.hasAttribute('data-playing'); i++) await new Promise((r) => setTimeout(r, 50));
        assert(root.hasAttribute('data-playing'), 'it plays');
        const sung = filled.find((samples) => Math.abs(samples.length - 3 * 48000) < 4800);
        assert(sung, `the voice was handed to the audio: ${filled.map((samples) => samples.length)}`);
        const rms = (from, to) => {
            let sum = 0;
            for (let i = Math.round(from * 48000); i < Math.round(to * 48000); i++) sum += sung[i] * sung[i];
            return Math.sqrt(sum / Math.round((to - from) * 48000));
        };
        assert(rms(1.35, 1.65) < 0.005, `the breath is ducked (it was about 0.058): ${rms(1.35, 1.65)}`);
        assert(Math.abs(rms(0.1, 0.6) - 0.3 / Math.SQRT2) < 0.02 && Math.abs(rms(2.2, 2.8) - 0.3 / Math.SQRT2) < 0.02, `the notes are as sung: ${rms(0.1, 0.6)}, ${rms(2.2, 2.8)}`);
        root.querySelector('[data-play]').click();
    } finally {
        window.fetch = fetched;
        AudioBuffer.prototype.copyToChannel = copy;
        root.remove();
    }
});
