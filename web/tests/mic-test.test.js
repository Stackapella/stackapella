// The mic test's reading (js/public/tools/mic-check-reading.js) on the app's own level maths (js/mic-level.js):
// the level in a word, the fix for a blocked mic on this browser and system, and a busy mic told apart.
import { browserOf, fixesToShow, fixKeys, headsetLike, levelWord, LOUD_DBFS, problemOf, roomWord } from '/js/public/tools/mic-check-reading.js';
import { stepVoice, voiceWatch, VOICE_DBFS } from '/js/mic-level.js';
import { FIX_WORDS, mountMicTest, WORDS as SCREEN_WORDS } from '/js/public/tools/mic-test.js';
import { assert, test } from './harness.js';

const watch = (db, seconds) => {
    let w = voiceWatch();
    for (let t = 0; t < seconds; t += 0.05) w = stepVoice(w, db, 0.05);
    return w;
};

test('mic test: the level in a word, from the app\'s own voice line', () => {
    assert(levelWord(voiceWatch(), -90) === 'listening', 'nothing yet: say something');
    assert(levelWord(watch(-30, 1), -30) === 'good', 'a voice');
    assert(levelWord(watch(VOICE_DBFS + 3, 1), VOICE_DBFS + 3) === 'soft', 'heard, a little quiet');
    assert(levelWord(watch(-30, 1), LOUD_DBFS + 2) === 'loud', 'too loud');
    assert(levelWord(watch(-90, 6), -90) === 'flat', 'nothing for a while');
    assert(levelWord(watch(-58, 6), -58) === 'quiet', 'only a faint signal for a while');
});

test('mic test: a blocked mic\'s fix for this browser and system, most specific first', () => {
    const mac = browserOf('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36', 'MacIntel');
    assert(mac.browser === 'chrome' && mac.system === 'mac', JSON.stringify(mac));
    assert(JSON.stringify(fixKeys(mac)) === '["fixChromeMac","fixChrome","fixMac","fixOther"]', JSON.stringify(fixKeys(mac)));
    const iphone = browserOf('Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/130.0 Mobile/15E148 Safari/604.1', 'iPhone');
    assert(iphone.system === 'ios' && fixKeys(iphone)[0] === 'fixIos', JSON.stringify(iphone));
    assert(browserOf('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36 Edg/130.0', 'Win32').browser === 'edge', 'Edge');
    assert(browserOf('Mozilla/5.0 (X11; Linux x86_64; rv:131.0) Gecko/20100101 Firefox/131.0', 'Linux x86_64').browser === 'firefox', 'Firefox');
    assert(problemOf({ reason: 'no-mic', cause: { name: 'NotReadableError' } }) === 'busy' && problemOf({ reason: 'denied', cause: { name: 'NotAllowedError' } }) === 'denied', 'busy told apart');
});

test('mic test: the two layers of a fix shown, the headset mic and the room told in a word', () => {
    const has = (keys) => (key) => keys.includes(key);
    const mac = { browser: 'chrome', system: 'mac' };
    assert(JSON.stringify(fixesToShow(mac, has(['fixChrome', 'fixMac', 'fixOther']))) === '["fixChrome","fixMac"]', 'the browser, then the system');
    assert(JSON.stringify(fixesToShow({ browser: 'chrome', system: 'android' }, has(['fixChromeAndroid', 'fixChrome', 'fixOther']))) === '["fixChromeAndroid"]', 'the most specific');
    assert(JSON.stringify(fixesToShow({ browser: 'safari', system: 'ios' }, has(['fixIos', 'fixOther']))) === '["fixIos"]', 'an iPhone');
    assert(JSON.stringify(fixesToShow({ browser: 'safari', system: 'mac' }, has(['fixSafari', 'fixMac', 'fixOther']))) === '["fixSafari"]', 'Safari on a Mac: in Safari itself');
    assert(JSON.stringify(fixesToShow({ browser: 'other', system: 'linux' }, has(['fixChrome', 'fixOther']))) === '["fixOther"]', 'nothing closer');
    assert(headsetLike('AirPods Pro') && headsetLike('Headset Microphone (Jabra Evolve2)') && !headsetLike('MacBook Pro Microphone') && !headsetLike('Scarlett 2i2 USB'), 'headsets by name');
    assert(roomWord(-70) === 'quiet' && roomWord(-55) === 'some' && roomWord(-40) === 'noisy' && roomWord(null) === null, 'the room');
});

test('mic test: the screen says the level and the note, shows the next tools, and fixes a blocked or busy mic', async () => {
    const words = { ...Object.fromEntries([...SCREEN_WORDS, ...FIX_WORDS].map((k) => [k, `[${k}]`])), hz: ':n Hz' };
    const el = document.createElement('div');
    document.body.append(el);
    let onFrame = null;
    const fake = async (options) => {
        onFrame = options.onFrame;
        return { stop() {}, deviceId: () => '', use: async () => {}, stream: () => null, target() {} };
    };
    const test = mountMicTest(el, { words, open: fake, next: [{ label: 'Range', href: '/tools/vocal-range-test' }] });
    const card = el.querySelector('[data-ui=tool]');
    const pill = () => card.querySelector('[data-ui=tool-status]');
    assert(card.dataset.phase === 'idle' && card.querySelector('[data-ui=tool-primary]').textContent.includes('[start]'), 'idle');
    await test.start();
    assert(card.dataset.phase === 'listening', card.dataset.phase);
    for (let i = 0; i < 40; i++) onFrame({ at: i * 0.03, loudness: -30, midi: 57.1, clear: true, cents: 10 });
    assert(pill().textContent.includes('[good]') && pill().dataset.tone === 'good', pill().textContent);
    assert(card.querySelector('[data-ui=tool-stat]').textContent === 'A3', card.querySelector('[data-ui=tool-stat]').textContent);
    const lit = [...card.querySelectorAll('[data-ui=mic-segment]')].filter((b) => /bg-(good|good\/60|cream|warn)(\s|$)/.test(b.className)).length;
    assert(lit > 6 && lit < 24, `the meter: ${lit}`);
    assert(!card.querySelector('[data-next]').hidden && card.querySelector('[data-next] a').getAttribute('href') === '/tools/vocal-range-test', 'the next tools');
    assert(card.querySelector('[data-ui=tool-primary]').textContent.includes('[record]'), 'Record next');
    for (let i = 40; i < 60; i++) onFrame({ at: i * 0.03, loudness: -4, midi: null, clear: false, cents: null });
    assert(pill().textContent.includes('[loud]') && pill().dataset.tone === 'warn', pill().textContent);
    test.stop();
    assert(card.dataset.phase === 'idle', 'off');

    const refuse = (reason, name) => async () => { throw Object.assign(new Error(reason), { reason, cause: { name } }); };
    const blocked = mountMicTest(el, { words, open: refuse('denied', 'NotAllowedError'), env: { browser: 'chrome', system: 'mac' } });
    await blocked.start();
    const fixes = [...el.querySelectorAll('[data-fix-list] li')].map((li) => li.textContent);
    assert(el.querySelector('[data-ui=tool]').dataset.phase === 'problem' && JSON.stringify(fixes) === '["[fixChrome]","[fixMac]"]', JSON.stringify(fixes));
    blocked.stop();
    const busy = mountMicTest(el, { words, open: refuse('no-mic', 'NotReadableError') });
    await busy.start();
    assert(el.querySelector('[data-ui=tool-status]').textContent.includes('[busy]') && el.querySelector('[data-problem-hint]').textContent === '[busyHint]' && el.querySelector('[data-fixes]').hidden, 'busy: close the other app');
    busy.stop();
    el.remove();
});
