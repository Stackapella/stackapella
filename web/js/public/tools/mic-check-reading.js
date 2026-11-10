// The mic test's reading (docs/tools/competitors-tap-tempo-mic-test-pitch-memory.md, section 2), pure and
// tested in tests/mic-test.test.js: what the level says in words, from the app's own level maths
// (js/mic-level.js, the same voice line and watch as the app's mic check), and which fix to show when the
// mic is blocked, for this browser on this system. The tool's screen (js/public/tools/mic-test.js) draws it.

import { checkState, VOICE_DBFS } from '../../mic-level.js';

export const LOUD_DBFS = -10; // louder than this, held: too loud, it'll distort (back off a little)
const SOFT_MARGIN = 8; // within this of the voice line: heard, but quietly (come a little closer)
/** Where "good" starts (RMS dBFS): under it, over the voice line, the level is "a little quiet". */
export const GOOD_DBFS = VOICE_DBFS + SOFT_MARGIN;

/**
 * The level in a word, from the voice watch (mic-level.js stepVoice) and the level now (RMS dBFS):
 * 'listening' (say something), 'good', 'soft' (heard, a little quiet), 'loud' (too loud), 'quiet'
 * (only a faint signal for a while: closer, or the input's gain up) or 'flat' (nothing for a while:
 * muted, or another mic).
 */
export function levelWord(watch, db) {
    const state = checkState(watch);
    if (state === 'flat' || state === 'quiet') return state;
    if (Number.isFinite(db) && db > LOUD_DBFS) return 'loud';
    if (watch.hearing) return Number.isFinite(db) && db < GOOD_DBFS ? 'soft' : 'good';
    return state === 'ok' ? 'good' : 'listening';
}

/** The browser and system from the user agent, for the right fix: { browser, system }. */
export function browserOf(ua = navigator.userAgent, platform = navigator.platform ?? '') {
    const system = /iPhone|iPad|iPod/.test(ua) || (platform === 'MacIntel' && navigator.maxTouchPoints > 1) ? 'ios'
        : /Android/.test(ua) ? 'android'
            : /Mac/.test(ua) ? 'mac'
                : /Windows/.test(ua) ? 'windows'
                    : /Linux|CrOS/.test(ua) ? 'linux' : 'other';
    const browser = /Edg\//.test(ua) ? 'edge'
        : /SamsungBrowser/.test(ua) ? 'samsung'
            : /Firefox|FxiOS/.test(ua) ? 'firefox'
                : /CriOS|Chrome/.test(ua) ? 'chrome'
                    : /Safari/.test(ua) ? 'safari' : 'other';
    return { browser, system };
}

/**
 * The words' keys for a blocked mic's fix, most specific first: the browser on this system
 * ('fixChromeAndroid'), then the browser ('fixChrome'), then the system's own privacy setting
 * ('fixMac', 'fixWindows'), else 'fixOther'. A page passes the steps it has; the first it has is shown.
 */
export function fixKeys({ browser, system }) {
    const cap = (word) => word.charAt(0).toUpperCase() + word.slice(1);
    // On an iPhone every browser is Safari underneath: its settings live in Settings.
    if (system === 'ios') return ['fixIos', 'fixOther'];
    const keys = [`fix${cap(browser)}${cap(system)}`, `fix${cap(browser)}`];
    if (system === 'mac' || system === 'windows') keys.push(`fix${cap(system)}`);
    return [...keys, 'fixOther'];
}

/** Why the mic couldn't be had, in the mic test's words: 'denied', 'busy' (another app has it), 'no-mic' or 'unsupported'. */
export function problemOf(error) {
    if (['NotReadableError', 'TrackStartError'].includes(error?.cause?.name)) return 'busy';
    return error?.reason ?? 'unsupported';
}

/**
 * The fixes to show for a blocked mic, from the keys the page `has` (a function): the most specific step
 * for this browser, and the system's own privacy setting beside it (the two layers: the site, and the
 * browser itself); 'fixOther' only when nothing more specific is there.
 */
export function fixesToShow(env, has) {
    const keys = fixKeys(env).filter(has);
    const systemKey = `fix${env.system.charAt(0).toUpperCase()}${env.system.slice(1)}`;
    const own = keys.find((k) => k !== 'fixOther' && k !== systemKey);
    // A Mac's Privacy & Security list holds only other makers' browsers: Safari is allowed in Safari itself.
    const system = keys.includes(systemKey) && !(env.browser === 'safari' && env.system === 'mac') ? systemKey : null;
    const shown = [own, system].filter(Boolean);
    return shown.length ? shown : keys.includes('fixOther') ? ['fixOther'] : [];
}

/** A Bluetooth headset or earbuds, by the name the mic gives: their own mic drops playback to call quality. */
export function headsetLike(label) {
    return /airpods|headset|hands-?free|\bbuds\b|galaxy buds|pixel buds|bluetooth|\bbeats\b|jabra|plantronics|\bbose\b|wh-1000|wf-1000/i.test(label ?? '');
}

/** The room in a word, from its noise between phrases (RMS dBFS): 'quiet', 'some' (some noise) or 'noisy'; null unknown. */
export function roomWord(db) {
    if (!Number.isFinite(db)) return null;
    if (db < -60) return 'quiet';
    if (db < -48) return 'some';
    return 'noisy';
}
