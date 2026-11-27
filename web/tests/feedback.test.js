// Feedback's pure parts (js/feedback.js; docs/feedback-plan.md): the form's address and how it's read back, where Back
// goes, the device as a kind and a browser family, and the choir ask's once-a-song rule.
import { backPath, browserFamily, choirAskDue, contactPath, deviceKind, readContact } from '/js/feedback.js';
import { assert, test } from './harness.js';

const IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.1 Mobile/15E148 Safari/604.1';
const IPAD_AS_MAC = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.1 Safari/605.1.15';
const ANDROID_PHONE = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Mobile Safari/537.36';
const ANDROID_TABLET = 'Mozilla/5.0 (Linux; Android 14; SM-X710) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';
const SAMSUNG = 'Mozilla/5.0 (Linux; Android 14; SM-S921B) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/26.0 Chrome/122.0.0.0 Mobile Safari/537.36';
const EDGE = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36 Edg/131.0.0.0';
const FIREFOX = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14.7; rv:133.0) Gecko/20100101 Firefox/133.0';
const OPERA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36 OPR/115.0.0.0';
const CHROME_IOS = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/131.0.6778.73 Mobile/15E148 Safari/604.1';

test('deviceKind: phone, tablet or computer, never more', () => {
    assert(deviceKind(IPHONE) === 'phone', 'iPhone');
    assert(deviceKind(ANDROID_PHONE) === 'phone', 'an Android phone');
    assert(deviceKind(ANDROID_TABLET) === 'tablet', 'an Android tablet (no Mobile)');
    assert(deviceKind(IPAD_AS_MAC, 5) === 'tablet', 'an iPad that says it is a Mac, with touch');
    assert(deviceKind(IPAD_AS_MAC, 0) === 'computer', 'a Mac');
    assert(deviceKind(EDGE) === 'computer', 'Windows');
    assert(deviceKind('') === 'computer', 'nothing known');
});

test('browserFamily: the families support.create takes', () => {
    assert(browserFamily(IPHONE) === 'safari', 'Safari');
    assert(browserFamily(ANDROID_PHONE) === 'chrome', 'Chrome');
    assert(browserFamily(CHROME_IOS) === 'chrome', 'Chrome on iOS');
    assert(browserFamily(SAMSUNG) === 'samsung', 'Samsung Internet, before Chrome');
    assert(browserFamily(EDGE) === 'edge', 'Edge, before Chrome');
    assert(browserFamily(OPERA) === 'opera', 'Opera, before Chrome');
    assert(browserFamily(FIREFOX) === 'firefox', 'Firefox');
    assert(browserFamily('curl/8') === 'other', 'anything else');
});

test('backPath: an app path on this host, else Settings', () => {
    for (const path of ['/', '/settings', '/t/abc/choir', '/learn/1-match?ref=x', '/sing#top']) assert(backPath(path) === path, `kept: ${path}`);
    for (const bad of ['//evil.example', 'https://evil.example/x', 'javascript:alert(1)', '/\\evil', '/api/me', '/auth/google', '/dev/login', '/contact?from=/', 'settings', '', null, 7, `/${'a'.repeat(300)}`]) {
        assert(backPath(bad) === '/settings', `refused: ${String(bad).slice(0, 30)}`);
    }
});

test('contactPath and readContact: what a link carries, checked both ways', () => {
    const path = contactPath({ from: '/t/abc/choir', moment: 'own_choir', topic: 'broken', choirRequestId: 'Cr8tYp', templateId: 'amazing-grace', partKey: 'alto', errorCode: 'a1b2c3d4' });
    const read = readContact(path.slice('/contact'.length));
    assert(read.from === '/t/abc/choir' && read.back === '/t/abc/choir', 'from');
    assert(read.moment === 'own_choir' && read.topic === 'broken', 'moment and topic');
    assert(read.choirRequestId === 'Cr8tYp' && read.templateId === 'amazing-grace' && read.partKey === 'alto' && read.errorCode === 'a1b2c3d4', 'the context');
    assert(!read.choirAsk, 'not the choir ask');
    assert(readContact(contactPath({ choirRequestId: 'Cr8tYp', moment: 'choir_ask', ask: 'choir' }).slice(8)).choirAsk, 'the choir ask, with its song');
    assert(!readContact('?ask=choir').choirAsk, 'the choir ask needs its song');
    assert(contactPath({ from: 'https://evil.example', moment: 'nope', topic: 'hack', choirRequestId: '<x>' }) === '/contact', 'nothing uncheckable goes in');
    const bad = readContact('?from=//evil.example&moment=x&topic=y&choir=a%20b&error=' + 'z'.repeat(80) + '&page=http://x');
    assert(bad.from === null && bad.back === '/settings' && bad.moment === null && bad.topic === null && bad.choirRequestId === null && bad.errorCode === null && bad.page === null, 'a typed link is checked again');
    assert(readContact('?diagnostics=1&from=/sing').diagnostics, 'Send diagnostics');
});

test('choirAskDue: the first two AI choir songs, once each', () => {
    assert(choirAskDue([], 'a'), 'the first song');
    assert(choirAskDue(['a'], 'b'), 'the second');
    assert(!choirAskDue(['a'], 'a'), 'not the same song again');
    assert(!choirAskDue(['a', 'b'], 'c'), 'never after two');
    assert(!choirAskDue([], ''), 'no song');
    assert(choirAskDue(null, 'a'), 'nothing kept yet');
});
