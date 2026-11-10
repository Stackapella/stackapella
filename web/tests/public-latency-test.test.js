// The latency test for /tools (js/public/tools/latency-test.js; docs/tools/competitors-pipe-forks-drone-latency-ear.md
// section 4). Tap along: taps become a number with uneven ones dropped and a spread; a tap answers the click
// it can. Let the mic hear it: the app's own timing check (js/timing-check.js) finds clicks heard 12 ms late
// at 12 ms; the card says the delay, its spread, the verdict and what to do, warns about a Bluetooth
// headset's mic, keeps this visit's results side by side, and says plainly when nothing was heard or the
// mic was refused.
import { measureDelay, mountLatencyTest, summarizeTaps, tapOffset, verdictOf, WORDS } from '/js/public/tools/latency-test.js';
import { measureLatency } from '/js/timing-check.js';
import { assert, test } from './harness.js';

const words = Object.fromEntries(WORDS.map((key) => [key, `[${key}]`]));
Object.assign(words, { ms: ':ms ms', plusMinus: '± :ms', browserSays: 'browser :ms' });

/** A stand-in device: what it "hears" is the click train, `lateMs` late (or silence). */
function fakeDevice({ lateMs = 12, silent = false, sampleRate = 48000 } = {}) {
    const device = { sampleRate, estimateMs: 30, closed: false, runs: 0 };
    device.measure = async (signal, captureLength) => {
        device.runs++;
        const heard = new Float32Array(captureLength);
        if (!silent) heard.set(signal.map((v) => v * 0.5), Math.round((lateMs * sampleRate) / 1000));
        return heard;
    };
    device.close = () => (device.closed = true);
    return device;
}

const settle = async (card, phase) => {
    for (let i = 0; i < 200 && card.dataset.phase !== phase; i++) await new Promise((r) => setTimeout(r, 5));
};

test('latency test: taps become a number, the uneven ones dropped, with a spread', () => {
    const steady = summarizeTaps([180, 176, 185, 190, 172, 181, 179, 184]);
    assert(Math.abs(steady.ms - 180.5) < 1 && steady.spreadMs < 10 && steady.kept === 8 && steady.steady, JSON.stringify(steady));
    const stray = summarizeTaps([60, 62, 58, 61, 340, 59, 63, 57]);
    assert(stray.kept === 7 && Math.abs(stray.ms - 60) < 2, `a stray tap dropped: ${JSON.stringify(stray)}`);
    const wide = summarizeTaps([40, 120, 80, 150, 60, 130, 90, 110]);
    assert(!wide.steady, `wide taps aren't steady: ${JSON.stringify(wide)}`);
    assert(summarizeTaps([]) === null, 'no taps, no number');
});

test('latency test: a tap answers the click it can, near the guess', () => {
    const clicks = [1000, 1600, 2200];
    assert(tapOffset(1790, clicks) === 190, 'late after the second');
    assert(tapOffset(1580, clicks, 0) === -20, 'a little early, before the second');
    assert(tapOffset(500, clicks) === null, 'before any click');
    assert(verdictOf(5) === 'tight' && verdictOf(12) === 'fine' && verdictOf(35) === 'fine' && verdictOf(60) === 'late' && verdictOf(150) === 'late' && verdictOf(260) === 'veryLate', 'the four words: singers live notice 10 ms');
});

test('latency test: the app\'s timing check finds the click train heard 12 ms late', async () => {
    const found = await measureDelay(fakeDevice({ lateMs: 12 }));
    assert(found && Math.abs(found.ms - 12) < 0.5 && found.runs === 3 && found.spreadMs < 0.1, JSON.stringify(found));
    assert((await measureDelay(fakeDevice({ silent: true }))) === null, 'nothing heard: null');
    const device = fakeDevice({ lateMs: 40 });
    assert(Math.round((await measureLatency({ measure: device.measure, sampleRate: device.sampleRate })).ms) === 40, 'the shared measurement');
});

test('latency test: the mic check says the delay, its verdict and what to do, and keeps the visit side by side', async () => {
    const el = document.createElement('div');
    document.body.append(el);
    // Heard 155 ms late: on Bluetooth, clap along reads that as perfectly steady claps, plus a clap's usual lead (25 ms).
    const device = fakeDevice({ lateMs: 155 });
    const results = [];
    const latency = mountLatencyTest(el, { words, route: 'bluetooth', open: async () => device, onResult: (r) => results.push(r) });
    const tool = el.querySelector('[data-ui=tool]');
    const pill = () => tool.querySelector('[data-ui=tool-status]').textContent.replace(/\s+/g, ' ').trim();
    assert(tool.querySelector('[data-route=bluetooth]').getAttribute('aria-pressed') === 'true', 'the route the page chose');
    assert(!tool.querySelector('[data-headset]').hidden, 'the headset-mic warning, for Bluetooth');
    assert(tool.querySelector('[data-ui=tool-primary]').textContent.includes('[start]'), 'tap along first, pinned');
    assert(tool.querySelector('[data-exact]').textContent === '[clapExact]' && tool.querySelector('[data-mic-start]').textContent === '[clapStart]' && !tool.querySelector('[data-clap-tip]').hidden, 'Bluetooth\'s more exact way is clap along, with the AirPods tip');
    tool.querySelector('[data-mic-start]').click(); // the more exact way, in the sheet
    await settle(tool, 'done');
    // Claps are found to the envelope's 2 ms step.
    assert(Math.abs(Number(tool.querySelector('[data-ms]').textContent) - 180) <= 3 && tool.querySelector('[data-unit]').textContent === 'ms ± 1', `${tool.querySelector('[data-ms]').textContent} ${tool.querySelector('[data-unit]').textContent}`);
    assert(tool.dataset.verdict === 'late' && pill().startsWith('[zoneLate]') && pill().includes('[late]'), pill());
    assert(tool.querySelectorAll('[data-what-now-list] li').length === 3, 'what to do with it');
    assert(tool.querySelector('[data-browser]').textContent === 'browser 30', 'the browser\'s own figure');
    assert(results[0].route === 'bluetooth' && results[0].method === 'clap' && tool.querySelector('[data-method-note]').textContent === '[clapNote]', JSON.stringify(results[0]));
    // Another pair: wired, the mic again; both side by side.
    tool.querySelector('[data-route=wired]').click();
    assert(tool.querySelector('[data-headset]').hidden, 'no headset warning for wired');
    assert(tool.querySelector('[data-mic-start]').textContent === '[micStart]' && tool.querySelector('[data-clap-tip]').hidden, 'wired: the click check');
    tool.querySelector('[data-mic-start]').click();
    await new Promise((r) => setTimeout(r, 50));
    await settle(tool, 'done');
    assert(!tool.querySelector('[data-visit]').hidden && tool.querySelectorAll('[data-visit-list] li').length === 2, 'this visit, side by side');
    latency.stop();
    assert(device.closed, 'the mic let go');
    el.remove();
});

test('latency test: nothing heard, or a refused mic, is said plainly', async () => {
    const el = document.createElement('div');
    document.body.append(el);
    const quiet = mountLatencyTest(el, { words, open: async () => fakeDevice({ silent: true }) });
    let tool = el.querySelector('[data-ui=tool]');
    tool.querySelector('[data-mic-start]').click();
    await settle(tool, 'failed');
    assert(tool.querySelector('[data-problem-words]').textContent === '[failed]', tool.querySelector('[data-problem-words]').textContent);
    quiet.stop();
    let reason = null;
    const refused = mountLatencyTest(el, { words, open: async () => { throw Object.assign(new Error('no'), { name: 'NotAllowedError' }); }, onError: (r) => (reason = r) });
    tool = el.querySelector('[data-ui=tool]');
    tool.querySelector('[data-mic-start]').click();
    await settle(tool, 'failed');
    assert(reason === 'denied' && tool.querySelector('[data-problem-words]').textContent === '[denied]', reason);
    refused.stop();
    el.remove();
});
