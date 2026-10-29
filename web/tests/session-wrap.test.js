// The first session's wrap (js/components/session-wrap.js): one keep-it ask that fits the device, and
// "Not now" as a real answer (three days before it asks again, and never after three).
import { keepAskDue, keepAskFor } from '/js/components/session-wrap.js';
import { assert, test } from './harness.js';

const DAY = 86400000;

test('session wrap: install where it can, else an account, and nothing once both are done', () => {
    const base = { isInstalled: false, prompt: false, isIphone: false, isMac: false, signedIn: false };
    assert(keepAskFor({ ...base, prompt: true }) === 'prompt', 'Chrome: one tap');
    assert(keepAskFor({ ...base, isIphone: true }) === 'iphone', 'an iPhone: Share, then Add to Home Screen');
    assert(keepAskFor({ ...base, isMac: true }) === 'mac', 'Safari on a Mac: Add to Dock');
    assert(keepAskFor(base) === 'account', 'no install here: an account');
    assert(keepAskFor({ ...base, isInstalled: true, prompt: true }) === 'account', 'installed: an account');
    assert(keepAskFor({ ...base, isInstalled: true, signedIn: true }) === null, 'installed and signed in: nothing to ask');
});

test('session wrap: Not now waits three days, and three Not nows end the asking', () => {
    const now = 100 * DAY;
    assert(keepAskDue({ nots: 0, at: 0 }, now), 'never answered');
    assert(!keepAskDue({ nots: 1, at: now - DAY }, now), 'a day after Not now');
    assert(keepAskDue({ nots: 1, at: now - 3 * DAY }, now), 'three days after');
    assert(!keepAskDue({ nots: 3, at: now - 30 * DAY }, now), 'after three, never');
});
