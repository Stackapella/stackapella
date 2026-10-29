// "Let's get your mic working" (Hillel, after Find my note couldn't use the mic on his iPhone and
// all he saw was a line of red): every screen that opens the mic hands a failure to showMicHelp(),
// and one modal (components/mic-help.js) says what went wrong in plain words, shows the steps for
// this person's own browser and device (the others a tap away), and tries again by itself the
// moment the mic is allowed. Here: the diagnosis (pure, tested per user agent) and the shared state.

import { reactive } from 'vue';
import { tk } from './i18n.js';

/**
 * The browser and device from the user agent: `{ platform, os, inApp }`. `platform` picks the
 * steps (PLATFORMS); `os` adds the system's own switch (macOS, Windows); `inApp` names an app's
 * built-in browser (Instagram, Facebook and the like), which often can't use the mic at all.
 * `standalone` is the installed app; an iPad asking for the desktop site says Macintosh with touch.
 */
export function deviceOf(userAgent, { standalone = false, touchPoints = 0 } = {}) {
    const ua = userAgent ?? '';
    const ios = /iPhone|iPad|iPod/.test(ua) || (/Macintosh/.test(ua) && touchPoints > 1);
    const android = /Android/.test(ua);
    const inApp = IN_APP.find(([, pattern]) => pattern.test(ua))?.[0] ?? null;
    let platform;
    if (ios) {
        if (standalone) platform = 'iosApp';
        else if (/CriOS/.test(ua)) platform = 'iosChrome';
        else if (/FxiOS/.test(ua)) platform = 'iosFirefox';
        else if (/EdgiOS/.test(ua)) platform = 'iosEdge';
        else if (inApp || !/Safari/.test(ua)) platform = 'iosOther';
        else platform = 'iosSafari';
    } else if (android) {
        if (/SamsungBrowser/.test(ua)) platform = 'samsung';
        else if (/Firefox/.test(ua)) platform = 'androidFirefox';
        else platform = 'androidChrome';
    } else if (/Edg\//.test(ua)) platform = 'edge';
    else if (/Firefox\//.test(ua)) platform = 'firefox';
    else if (/Chrome\//.test(ua)) platform = 'chrome';
    else if (/Safari\//.test(ua)) platform = 'safari';
    else platform = 'chrome';
    const os = ios ? 'ios' : android ? 'android' : /Mac OS X|Macintosh/.test(ua) ? 'mac' : /Windows/.test(ua) ? 'windows' : 'other';
    return { platform, os, inApp };
}

// Apps whose built-in browser often refuses the mic, by what they add to the user agent.
const IN_APP = [
    ['Instagram', /Instagram/], // i18n-ignore: an app's name
    ['Facebook', /FBAN|FBAV|FB_IAB|FBIOS/], // i18n-ignore: an app's name
    ['Messenger', /MessengerForiOS|Orca-Android/], // i18n-ignore: an app's name
    ['TikTok', /TikTok|musical_ly|BytedanceWebview|Bytedance/], // i18n-ignore: an app's name
    ['Snapchat', /Snapchat/], // i18n-ignore: an app's name
    ['LinkedIn', /LinkedInApp/], // i18n-ignore: an app's name
    ['X', /Twitter/], // i18n-ignore: an app's name
    ['Pinterest', /Pinterest/], // i18n-ignore: an app's name
    ['Line', /\bLine\//], // i18n-ignore: an app's name
    ['Google', /GSA\/|GoogleApp/], // i18n-ignore: an app's name
    ['WhatsApp', /WhatsApp/], // i18n-ignore: an app's name
];

/**
 * What went wrong, from getUserMedia's error and where the page is: 'inApp' (an app's own browser),
 * 'insecure' (not https, or inside another site's frame), 'unsupported' (no mic API here),
 * 'blocked' (the person or the browser said no), 'notFound' (no mic), 'inUse' (another app has it,
 * or the device couldn't start it: on iOS 26 this can stick until the browser is quit), or 'other'.
 */
export function diagnose(error, { inApp = null, secure = true, framed = false, hasMediaDevices = true } = {}) {
    const name = error?.name ?? '';
    if (inApp && name !== 'NotFoundError') return 'inApp';
    if (!secure || framed || name === 'SecurityError') return 'insecure';
    if (!hasMediaDevices) return 'unsupported';
    // iOS 26's "No AVAudioSessionCaptureDevice device" says NotAllowedError, but nobody refused:
    // the system's audio lost the mic (WebKit bug 319706), and quitting the browser brings it back.
    if (/AVAudioSession|capture device/i.test(error?.message ?? '')) return 'inUse';
    if (name === 'NotAllowedError' || name === 'PermissionDeniedError') return 'blocked';
    if (name === 'NotFoundError' || name === 'DevicesNotFoundError' || name === 'OverconstrainedError') return 'notFound';
    if (['NotReadableError', 'TrackStartError', 'AbortError', 'InvalidStateError'].includes(name)) return 'inUse';
    return 'other';
}

/** Each cause: a heading and what it means, in plain words. */
export const CAUSES = {
    blocked: { title: tk('micHelp.cause.blocked'), body: tk('micHelp.cause.blockedBody'), short: tk('micHelp.cause.blockedShort') },
    notFound: { title: tk('micHelp.cause.notFound'), body: tk('micHelp.cause.notFoundBody') },
    inUse: { title: tk('micHelp.cause.inUse'), body: tk('micHelp.cause.inUseBody') },
    insecure: { title: tk('micHelp.cause.insecure'), body: tk('micHelp.cause.insecureBody') },
    inApp: { title: tk('micHelp.cause.inApp'), body: tk('micHelp.cause.inAppBody') },
    unsupported: { title: tk('micHelp.cause.unsupported'), body: tk('micHelp.cause.unsupportedBody') },
    other: { title: tk('micHelp.cause.other'), body: tk('micHelp.cause.otherBody') },
};

/**
 * The steps to allow the mic, per browser and device, short and numbered, with the menus' own
 * names (checked against current versions in October 2026). `browser` fills {browser} for the
 * iPhone browsers that share their steps.
 */
export const PLATFORMS = {
    iosSafari: { name: tk('micHelp.platform.iosSafari'), browser: 'Safari', steps: [tk('micHelp.steps.iosSafari.one'), tk('micHelp.steps.iosSafari.two'), tk('micHelp.steps.iosSafari.three'), tk('micHelp.steps.iosSafari.four')] }, // i18n-ignore: a product name
    iosChrome: { name: tk('micHelp.platform.iosChrome'), browser: 'Chrome', steps: [tk('micHelp.steps.iosBrowser.one'), tk('micHelp.steps.iosBrowser.two'), tk('micHelp.steps.iosBrowser.three')] }, // i18n-ignore: a product name
    iosFirefox: { name: tk('micHelp.platform.iosFirefox'), browser: 'Firefox', steps: [tk('micHelp.steps.iosBrowser.one'), tk('micHelp.steps.iosBrowser.two'), tk('micHelp.steps.iosBrowser.three')] }, // i18n-ignore: a product name
    iosEdge: { name: tk('micHelp.platform.iosEdge'), browser: 'Edge', steps: [tk('micHelp.steps.iosBrowser.one'), tk('micHelp.steps.iosBrowser.two'), tk('micHelp.steps.iosBrowser.three')] }, // i18n-ignore: a product name
    iosApp: { name: tk('micHelp.platform.iosApp'), browser: 'Stackapella', steps: [tk('micHelp.steps.iosApp.one'), tk('micHelp.steps.iosApp.two'), tk('micHelp.steps.iosApp.three')] }, // i18n-ignore: a product name
    iosOther: { name: tk('micHelp.platform.iosOther'), steps: [tk('micHelp.steps.iosOther.one'), tk('micHelp.steps.iosOther.two')] },
    androidChrome: { name: tk('micHelp.platform.androidChrome'), steps: [tk('micHelp.steps.androidChrome.one'), tk('micHelp.steps.androidChrome.two'), tk('micHelp.steps.androidChrome.three'), tk('micHelp.steps.androidChrome.four')] },
    samsung: { name: tk('micHelp.platform.samsung'), steps: [tk('micHelp.steps.samsung.one'), tk('micHelp.steps.samsung.two'), tk('micHelp.steps.samsung.three')] },
    androidFirefox: { name: tk('micHelp.platform.androidFirefox'), steps: [tk('micHelp.steps.androidFirefox.one'), tk('micHelp.steps.androidFirefox.two'), tk('micHelp.steps.androidFirefox.three')] },
    // On a computer, two steps (77.png): Try again, at the bottom, is the third.
    chrome: { name: tk('micHelp.platform.chrome'), steps: [tk('micHelp.steps.chrome.one'), tk('micHelp.steps.chrome.two')] },
    edge: { name: tk('micHelp.platform.edge'), steps: [tk('micHelp.steps.edge.one'), tk('micHelp.steps.edge.two')] },
    firefox: { name: tk('micHelp.platform.firefox'), steps: [tk('micHelp.steps.firefox.one'), tk('micHelp.steps.firefox.two')] },
    safari: { name: tk('micHelp.platform.safari'), steps: [tk('micHelp.steps.safari.one'), tk('micHelp.steps.safari.two')] },
};

/** "For Chrome on Mac": each platform's browser and device, in words. */
export const BROWSER_WORDS = {
    iosSafari: [tk('micHelp.browser.safari'), tk('micHelp.device.ios')],
    iosChrome: [tk('micHelp.browser.chrome'), tk('micHelp.device.ios')],
    iosFirefox: [tk('micHelp.browser.firefox'), tk('micHelp.device.ios')],
    iosEdge: [tk('micHelp.browser.edge'), tk('micHelp.device.ios')],
    iosApp: [tk('micHelp.browser.app'), tk('micHelp.device.ios')],
    iosOther: [tk('micHelp.browser.inApp'), tk('micHelp.device.ios')],
    androidChrome: [tk('micHelp.browser.chrome'), tk('micHelp.device.android')],
    samsung: [tk('micHelp.browser.samsung'), tk('micHelp.device.android')],
    androidFirefox: [tk('micHelp.browser.firefox'), tk('micHelp.device.android')],
    chrome: [tk('micHelp.browser.chrome'), null],
    edge: [tk('micHelp.browser.edge'), null],
    firefox: [tk('micHelp.browser.firefox'), null],
    safari: [tk('micHelp.browser.safari'), tk('micHelp.device.mac')],
};
export const DEVICE_WORDS = { mac: tk('micHelp.device.mac'), windows: tk('micHelp.device.windows'), ios: tk('micHelp.device.ios'), android: tk('micHelp.device.android'), other: tk('micHelp.device.computer') };

/** The small drawing beside the steps (77.png): the icon to click in the address bar, and the switch. */
export const DRAWINGS = {
    chrome: { icon: 'sliders', label: tk('micHelp.drawing.microphone') },
    edge: { icon: 'lock', label: tk('micHelp.drawing.microphone') },
    firefox: { icon: 'mic', label: tk('micHelp.drawing.microphone') },
    androidChrome: { icon: 'sliders', label: tk('micHelp.drawing.microphone') },
    androidFirefox: { icon: 'lock', label: tk('micHelp.drawing.microphone') },
};

/** A step's words in parts, **like this** in bold (the settings' own names), never as markup. */
export function boldParts(text) {
    return String(text).split(/\*\*(.+?)\*\*/).map((part, i) => ({ text: part, bold: i % 2 === 1 })).filter((p) => p.text);
}

/** The system's own switch, under the browser's steps, when the mic may be off for the whole browser. */
export const SYSTEMS = {
    mac: { name: tk('micHelp.system.mac'), steps: [tk('micHelp.steps.mac.one'), tk('micHelp.steps.mac.two')] },
    windows: { name: tk('micHelp.system.windows'), steps: [tk('micHelp.steps.windows.one'), tk('micHelp.steps.windows.two')] },
};

/**
 * Everything the modal shows for `error` on this device: the cause, this browser's steps first,
 * the system's switch when it may be off, whether to offer Reload (iOS, where a change in Settings
 * or a stuck mic needs it) and Copy link (an app's own browser). Pure: tested per user agent.
 */
export function adviceFor(error, { userAgent, standalone = false, touchPoints = 0, secure = true, framed = false, hasMediaDevices = true } = {}) {
    const device = deviceOf(userAgent, { standalone, touchPoints });
    const cause = diagnose(error, { inApp: device.inApp, secure, framed, hasMediaDevices });
    const platform = device.inApp && device.os === 'ios' ? 'iosOther' : device.platform;
    return {
        cause,
        device,
        platform,
        // The steps matter when something said no; when the mic is busy or missing, they're a fallback.
        showSteps: ['blocked', 'other', 'unsupported'].includes(cause),
        system: ['mac', 'windows'].includes(device.os) && ['blocked', 'notFound', 'other'].includes(cause) ? device.os : null,
        reload: device.os === 'ios' && cause !== 'inApp' && cause !== 'insecure',
        copyLink: cause === 'inApp' || cause === 'unsupported',
    };
}

// ---- The shared modal's state ---------------------------------------------------------------------------

/**
 * The open mic help, if any: `{ error, retry, attempts }`. `retry` is the screen's own way to open
 * the mic and carry on (a tap on Try again calls it); it resolves truthy once it worked.
 */
export const micHelp = reactive({ open: false, error: null, retry: null, attempts: [], trying: false, primer: null });

const EXPLAINED_KEY = 'stackapella.micExplained';

/**
 * Before the browser first asks for the mic: a few words on why, so fewer people tap Block.
 * Resolves true to go on to the browser's question, false if they'd rather not now. Skipped once
 * explained on this device, or when the mic is already allowed (or already blocked: then the
 * failure brings the help).
 */
export async function explainFirst() {
    try {
        if (localStorage.getItem(EXPLAINED_KEY)) return true;
    } catch (error) {
        console.warn('Could not read whether the mic was explained', error);
    }
    let state = null;
    try {
        state = (await navigator.permissions?.query({ name: 'microphone' }))?.state ?? null;
    } catch (error) {
        console.warn('The mic permission cannot be read here', error);
    }
    if (state === 'granted' || state === 'denied') return true;
    return new Promise((resolve) => {
        micHelp.primer = (go) => {
            micHelp.primer = null;
            if (go) {
                try {
                    localStorage.setItem(EXPLAINED_KEY, '1');
                } catch (error) {
                    console.warn('Could not keep that the mic was explained', error);
                }
            }
            resolve(go);
        };
    });
}

/** The why was said on the screen itself (a lesson's "Turn on my mic" with its line): no sheet before the browser asks. */
export function micExplained() {
    try {
        localStorage.setItem(EXPLAINED_KEY, '1');
    } catch (error) {
        console.warn('Could not keep that the mic was explained', error);
    }
}

const RELOAD_KEY = 'stackapella.micReload';

/** Shows the mic help for `error`, with `retry` to open the mic again and carry on from this screen. */
export function showMicHelp(error, retry = null, attempts = []) {
    console.warn('The mic did not start', error);
    Object.assign(micHelp, { open: true, error: { name: error?.name ?? 'Error', message: error?.message ?? String(error ?? '') }, retry, attempts });
}

export function closeMicHelp() {
    Object.assign(micHelp, { open: false, error: null, retry: null, attempts: [], trying: false });
}

/** Reloads the page to try again (iOS), coming back to this same screen. */
export function reloadForMic() {
    try {
        sessionStorage.setItem(RELOAD_KEY, location.pathname + location.search);
    } catch (error) {
        console.warn('Could not note the reload', error);
    }
    location.reload();
}

/** After a "Reload and try": whether this load came from one (once). */
export function cameFromMicReload() {
    try {
        const at = sessionStorage.getItem(RELOAD_KEY);
        sessionStorage.removeItem(RELOAD_KEY);
        return at === location.pathname + location.search;
    } catch (error) {
        console.warn('Could not read the reload note', error);
        return false;
    }
}
