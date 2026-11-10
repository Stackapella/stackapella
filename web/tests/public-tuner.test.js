// The free tuner's card (js/public/tools/tuner.js): the mic opened with the right reach for Voice or
// Instrument, "In tune" in words after half a second, a refused mic said in a word, transposition and
// strings, settings kept on the device; and the app's tracker reaching an instrument's low and high notes.
import { mountTuner, stringsOf, WARM, WORDS } from '/js/public/tools/tuner.js';
import { WARM as RANGE_WARM } from '/js/public/tools/vocal-range-test.js';
import { assert, test } from './harness.js';

const words = Object.fromEntries(WORDS.map((key) => [key, `[${key}]`]));
Object.assign(words, { cents: '[:cents c]', inKey: '[:key]', hz: '[:hz]', inTuneShare: '[:percent]' });
const STORE = 'stackapella.tools.tuner';

function fakeMic() {
    const mic = { onFrame: null, reach: null };
    mic.open = async ({ onFrame, ...reach }) => {
        mic.onFrame = onFrame;
        mic.reach = reach;
        return { stop: () => (mic.onFrame = null) };
    };
    return mic;
}
const play = (mic, hz, seconds, from) => {
    for (let i = 0; i < Math.round(seconds * 60); i++) mic.onFrame?.({ at: from + i / 60, hz, loudness: -25, clarity: 0.95 });
};
const tick = () => new Promise((r) => setTimeout(r, 0));

function mount(options = {}) {
    const el = document.createElement('div');
    document.body.append(el);
    const mic = fakeMic();
    const tuner = mountTuner(el, { words, open: mic.open, ...options });
    return { el, mic, tuner, card: el.querySelector('[data-tool=tuner]'), text: (sel) => el.querySelector(sel).textContent };
}

test('tuner card: Voice listens to a voice\'s reach, and 8 cents sharp is in tune after half a second', async () => {
    localStorage.removeItem(STORE);
    const { el, mic, tuner, card, text } = mount();
    el.querySelector('[data-start]').click();
    await tick();
    assert(mic.reach.maxHz === 1100 && mic.reach.minHz === 60 && mic.reach.window === 2048, `a voice: ${JSON.stringify(mic.reach)}`);
    assert(text('[data-status-text]') === '[listening]', text('[data-status-text]'));
    play(mic, 440 * 2 ** (8 / 1200), 0.3, 0);
    assert(card.dataset.state === 'settling' && text('[data-name]') === 'A' && text('[data-octave]') === '4', `settling: ${card.dataset.state}`);
    play(mic, 440 * 2 ** (8 / 1200), 0.4, 0.3);
    assert(card.dataset.state === 'in-tune' && text('[data-status-text]') === '[inTune]', `in tune: ${card.dataset.state}`);
    assert(text('[data-cents]') === '[+8 c]', text('[data-cents]'));
    tuner.stop();
    assert(card.dataset.state === 'paused' && text('[data-status-text]') === '[paused]' && mic.onFrame === null, 'stopped: paused, the mic let go');
    el.remove();
});

test('tuner card: Instrument reaches a five-string bass\'s B0 and a flute\'s top, and a refused mic says why', async () => {
    localStorage.removeItem(STORE);
    const { el, mic } = mount();
    el.querySelector('[data-segment=instrument]').click();
    el.querySelector('[data-start]').click();
    await tick();
    assert(mic.reach.minHz < 31 && mic.reach.maxHz === 1500 && mic.reach.window === 4096, `an instrument: ${JSON.stringify(mic.reach)}`);
    el.remove();
    let reason = null;
    const refused = mount({ open: async () => { throw Object.assign(new Error('denied'), { reason: 'denied' }); }, onError: (r) => (reason = r) });
    refused.el.querySelector('[data-start]').click();
    await tick();
    assert(reason === 'denied' && refused.text('[data-status-text]') === '[noMic]', `refused: ${reason}`);
    refused.el.remove();
    localStorage.removeItem(STORE);
});

test('tuner card: a B♭ instrument reads concert A as B, a guitar\'s strings follow its tuning, and the settings stay', async () => {
    localStorage.removeItem(STORE);
    const first = mount();
    first.el.querySelector('[data-key="B♭"]').click();
    first.el.querySelector('[data-start]').click();
    await tick();
    play(first.mic, 440, 0.5, 0);
    assert(first.text('[data-name]') + first.text('[data-octave]') === 'B4', `B♭ trumpet: ${first.text('[data-name]')}`);
    assert(first.text('[data-ui=tuner-summary]').includes('[B♭]'), 'the summary says so');
    const select = first.el.querySelector('[data-instrument]');
    select.value = 'bass5';
    select.dispatchEvent(new Event('change'));
    assert(first.el.querySelectorAll('[data-string]').length === 5 && first.el.querySelector('[data-segment=instrument]').getAttribute('aria-pressed') === 'true', 'a bass picks Instrument and shows five strings');
    first.tuner.stop();
    first.el.remove();
    const again = mount();
    assert(again.tuner.settings.transposition === 'B♭' && again.tuner.settings.instrument === 'bass5', 'kept on this device');
    again.el.remove();
    localStorage.removeItem(STORE);
    assert(stringsOf('guitar', 'dropD')[0] === 38 && stringsOf('guitar', 'openG').join() === '38,43,50,55,59,62' && stringsOf('chromatic') === null, 'tunings');
});

test('tuner: the app\'s tracker reads an instrument\'s high note, a bass\'s E1 and B0 with the longer window', async () => {
    const { trackFrame } = await import('/js/pitchtrack.js');
    const rate = 48000;
    const tone = (hz, length = 2048) => Float32Array.from({ length }, (_, i) => 0.3 * Math.sin((2 * Math.PI * hz * i) / rate) + 0.15 * Math.sin((4 * Math.PI * hz * i) / rate) + 0.1 * Math.sin((6 * Math.PI * hz * i) / rate));
    const high = trackFrame(tone(1400), rate, { minClarity: 0.6, maxHz: 1500 });
    assert(Math.abs(high.hz - 1400) < 8, `1400 Hz with an instrument's reach: ${high.hz}`);
    const voiceOnly = trackFrame(tone(1400), rate, { minClarity: 0.6 });
    assert(Math.abs(voiceOnly.hz - 1400) > 8, `a voice's reach stops below it: ${voiceOnly.hz}`);
    const reach = { minClarity: 0.6, maxHz: 1500, minHz: 28, windowSeconds: 4096 / rate };
    const e1 = trackFrame(tone(41.2, 4096), rate, reach);
    assert(Math.abs(e1.hz - 41.2) < 0.5, `a bass's E1: ${e1.hz}`);
    const b0 = trackFrame(tone(30.87, 4096), rate, reach);
    assert(Math.abs(b0.hz - 30.87) < 0.5, `a five-string's B0: ${b0.hz}`);
    // A phone mic loses the fundamental: the harmonics alone still read E1, not E2.
    const thin = Float32Array.from({ length: 4096 }, (_, i) => 0.25 * Math.sin((4 * Math.PI * 41.2 * i) / rate) + 0.2 * Math.sin((6 * Math.PI * 41.2 * i) / rate) + 0.1 * Math.sin((8 * Math.PI * 41.2 * i) / rate));
    const missing = trackFrame(thin, rate, reach);
    assert(Math.abs(missing.hz - 41.2) < 0.5, `E1 without its fundamental: ${missing.hz}`);
});

test('tuner and range test: the pitch tracker\'s files are kept offline before a first Listen or Start (WARM)', () => {
    for (const list of [WARM, RANGE_WARM]) {
        assert(['/js/public/listen-worker.js', '/js/pitchtrack.js', '/js/listen.js', '/js/dsp.js'].every((path) => list.includes(path)), JSON.stringify(list));
    }
});
