// "Let's get your mic working" (js/mic-help.js): each browser and device gets its own steps, and
// each of getUserMedia's failures its own plain cause.
import { adviceFor, deviceOf, diagnose, PLATFORMS } from '/js/mic-help.js';
import { assert, test } from './harness.js';

const UA = {
    iosSafari: 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1',
    iosChrome: 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/141.0.7390.41 Mobile/15E148 Safari/604.1',
    iosFirefox: 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) FxiOS/143.0 Mobile/15E148 Safari/605.1.15',
    instagram: 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 Instagram 350.0.0.0.0 (iPhone15,2; iOS 18_6; en_GB; en)',
    facebookAndroid: 'Mozilla/5.0 (Linux; Android 14; Pixel 8 Build/AP2A; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/129.0.0.0 Mobile Safari/537.36 [FB_IAB/FB4A;FBAV/480.0.0.0;]',
    androidChrome: 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36',
    samsung: 'Mozilla/5.0 (Linux; Android 14; SM-S921B) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/27.0 Chrome/125.0.0.0 Mobile Safari/537.36',
    androidFirefox: 'Mozilla/5.0 (Android 14; Mobile; rv:143.0) Gecko/143.0 Firefox/143.0',
    macChrome: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36',
    macSafari: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0 Safari/605.1.15',
    winEdge: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0',
    winFirefox: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:143.0) Gecko/20100101 Firefox/143.0',
};
const error = (name, message = '') => ({ name, message });

test('mic help: each browser and device is told apart from its user agent', () => {
    const expect = { iosSafari: 'iosSafari', iosChrome: 'iosChrome', iosFirefox: 'iosFirefox', androidChrome: 'androidChrome', samsung: 'samsung', androidFirefox: 'androidFirefox', macChrome: 'chrome', macSafari: 'safari', winEdge: 'edge', winFirefox: 'firefox' };
    for (const [ua, platform] of Object.entries(expect)) assert(deviceOf(UA[ua]).platform === platform, `${ua}: ${deviceOf(UA[ua]).platform}`);
    assert(deviceOf(UA.iosSafari, { standalone: true }).platform === 'iosApp', 'the installed app');
    assert(deviceOf(UA.macSafari, { touchPoints: 5 }).os === 'ios', 'an iPad asking for the desktop site');
    assert(deviceOf(UA.instagram).inApp === 'Instagram' && deviceOf(UA.facebookAndroid).inApp === 'Facebook', 'apps’ own browsers');
    assert(deviceOf(UA.macChrome).os === 'mac' && deviceOf(UA.winEdge).os === 'windows', 'systems');
    for (const id of Object.values(expect)) assert(PLATFORMS[id]?.steps.length >= 2, `${id} has steps`);
});

test('mic help: each failure is a plain cause', () => {
    assert(diagnose(error('NotAllowedError')) === 'blocked', 'blocked');
    assert(diagnose(error('NotFoundError')) === 'notFound', 'no mic');
    assert(diagnose(error('NotReadableError')) === 'inUse', 'in use');
    assert(diagnose(error('AbortError')) === 'inUse', 'aborted by the device');
    assert(diagnose(error('InvalidStateError', 'Failed to start the audio device')) === 'inUse', 'the audio device');
    // iOS 26's lost capture device says NotAllowedError, but nobody refused.
    assert(diagnose(error('NotAllowedError', 'No AVAudioSessionCaptureDevice device')) === 'inUse', 'iOS 26');
    assert(diagnose(error('SecurityError')) === 'insecure' && diagnose(error('NotAllowedError'), { framed: true }) === 'insecure', 'insecure');
    assert(diagnose(error('NotAllowedError'), { inApp: 'Instagram' }) === 'inApp', 'an app’s own browser');
    assert(diagnose(error('TypeError'), { hasMediaDevices: false }) === 'unsupported', 'no mic API');
    assert(diagnose(error('Whatever')) === 'other', 'anything else');
});

test('mic help: the advice fits the device', () => {
    const iphone = adviceFor(error('NotAllowedError'), { userAgent: UA.iosChrome });
    assert(iphone.cause === 'blocked' && iphone.platform === 'iosChrome' && iphone.showSteps && iphone.reload && !iphone.system, JSON.stringify(iphone));
    const stuck = adviceFor(error('NotReadableError'), { userAgent: UA.iosChrome });
    assert(stuck.cause === 'inUse' && !stuck.showSteps && stuck.reload, JSON.stringify(stuck));
    const insta = adviceFor(error('NotAllowedError'), { userAgent: UA.instagram });
    assert(insta.cause === 'inApp' && insta.copyLink && insta.platform === 'iosOther' && !insta.reload, JSON.stringify(insta));
    const mac = adviceFor(error('NotAllowedError'), { userAgent: UA.macChrome });
    assert(mac.system === 'mac' && !mac.reload, JSON.stringify(mac));
    const windows = adviceFor(error('NotFoundError'), { userAgent: UA.winEdge });
    assert(windows.system === 'windows' && windows.cause === 'notFound', JSON.stringify(windows));
    const android = adviceFor(error('NotAllowedError'), { userAgent: UA.androidChrome });
    assert(android.platform === 'androidChrome' && !android.reload && !android.system, JSON.stringify(android));
});
