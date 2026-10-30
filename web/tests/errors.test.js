// Error reporting (js/errors.js): the envelope Sentry reads, and what never goes in it.
import { describeBrowser, envelopeFor, eventFor, framesOf, isNoise, scrubPath } from '/js/errors.js';
import { assert, test } from './harness.js';

test('errors: token addresses are reported as their pattern, never with the token or a query', () => {
    assert(scrubPath('/invite/AbC123xyz?ref=hn') === '/invite/:token', scrubPath('/invite/AbC123xyz?ref=hn'));
    assert(scrubPath('/join/tok') === '/join/:token' && scrubPath('/early/code') === '/early/:token', 'join and early');
    assert(scrubPath('/reset?token=abc&email=a@example.com') === '/reset/:token', scrubPath('/reset?token=abc'));
    assert(scrubPath('/p/abc-123/sing#x') === '/p/abc-123/sing', 'a song keeps its id');
    assert(scrubPath('/t/' + 'a'.repeat(40)) === '/t/:token', 'a long opaque segment');
});

test('errors: the envelope has the DSN, an event with the error and its frames, and no personal data', () => {
    const error = new TypeError('Cannot read x');
    error.stack = 'TypeError: Cannot read x\n    at play (https://app.example.com/js/views/sing.js?v=2:120:7)\n    at https://app.example.com/js/main.js:9:1';
    const event = eventFor(error, { path: '/invite/secret?x=1', ua: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/131.0 Safari/537.36', accountId: 'acc1', build: 'abc' }); // an account id given is never sent
    const [header, item, body] = envelopeFor(event, 'https://key@o1.ingest.sentry.io/2').trim().split('\n');
    assert(JSON.parse(header).dsn === 'https://key@o1.ingest.sentry.io/2', 'the DSN the tunnel checks');
    assert(JSON.parse(item).type === 'event' && JSON.parse(item).length === new TextEncoder().encode(body).length, 'the item header');
    const sent = JSON.parse(body);
    const ex = sent.exception.values[0];
    assert(ex.type === 'TypeError' && ex.value === 'Cannot read x', JSON.stringify(ex));
    assert(ex.stacktrace.frames.length === 2 && ex.stacktrace.frames.at(-1).function === 'play' && ex.stacktrace.frames.at(-1).filename === 'https://app.example.com/js/views/sing.js', JSON.stringify(ex.stacktrace.frames));
    assert(sent.platform === 'javascript' && sent.release.endsWith('+abc'), sent.release);
    assert(sent.request.url.endsWith('/invite/:token') && !body.includes('secret'), sent.request.url);
    assert(!('user' in sent) && !body.includes('acc1'), 'no user at all: no id, name, email or IP');
    assert(sent.contexts.browser.name === 'Chrome' && sent.contexts.os.name === 'macOS', JSON.stringify(sent.contexts));
    assert(!eventFor(error, {}).user, 'signed out: no user either');
});

test('errors: extensions, resize loops and aborted fetches are noise; Firefox frames are read', () => {
    assert(isNoise({ name: 'Error', message: 'ResizeObserver loop completed with undelivered notifications.' }), 'resize');
    assert(isNoise({ name: 'AbortError', message: 'The user aborted a request.' }), 'aborted');
    assert(isNoise({ name: 'Error', message: 'x', stack: 'f@chrome-extension://abc/x.js:1:1' }), 'extension');
    assert(!isNoise(new Error('real')), 'a real error');
    assert(isNoise({ name: 'Error', message: 'Script error.', filename: '', stack: '' }), "a cross-origin script's hidden error, as window.onerror gives it");
    assert(framesOf('play@https://h/js/a.js:3:4').length === 1, 'Firefox frames');
    assert(describeBrowser('Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) Version/18.0 Mobile/15E148 Safari/604.1').os.name === 'iOS', 'iOS');
});
