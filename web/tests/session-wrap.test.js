// The first session's wrap (js/components/session-wrap.js): one keep-it ask that fits the device, and
// "Not now" as a real answer (the ask ledger's caps are tested in asks.test.js).
import { keepAskFor } from '/js/components/session-wrap.js';
import { installForm, riskShows } from '/js/components/install.js';
import { assert, test } from './harness.js';

test('session wrap: install where it can, else an account, and nothing once both are done', () => {
    const base = { isInstalled: false, prompt: false, isIphone: false, isMac: false, signedIn: false };
    assert(keepAskFor({ ...base, prompt: true }) === 'prompt', 'Chrome: one tap');
    // Safari's added app starts fresh (account-nudges plan, F1): a guest there is asked for the account.
    assert(keepAskFor({ ...base, isIphone: true }) === 'account', 'a guest on an iPhone: an account, never install');
    assert(keepAskFor({ ...base, isMac: true }) === 'account', 'a guest in Safari on a Mac: an account');
    assert(keepAskFor({ ...base, isIphone: true, signedIn: true }) === 'iphone', 'signed in on an iPhone: Share, then Add to Home Screen');
    assert(keepAskFor({ ...base, isMac: true, signedIn: true }) === 'mac', 'signed in, Safari on a Mac: Add to Dock');
    assert(keepAskFor(base) === 'account', 'no install here: an account');
    assert(keepAskFor({ ...base, isInstalled: true, prompt: true }) === 'account', 'installed: an account');
    assert(keepAskFor({ ...base, isInstalled: true, signedIn: true }) === null, 'installed and signed in: nothing to ask');
});

test('install card: never promises Safari keeps anything; there a guest with songs is asked for an account', () => {
    const base = { safari: false, prompt: false, songs: false, signedIn: false };
    assert(installForm({ ...base, prompt: true }) === 'offer' && installForm({ ...base, prompt: true, songs: true }) === 'songs', 'Chrome: install, plainly or for the songs');
    assert(installForm(base) === null, 'no way to install here: no card');
    assert(installForm({ ...base, safari: true }) === null, 'a guest on Safari with nothing recorded: no card (the wrap asks)');
    assert(installForm({ ...base, safari: true, songs: true }) === 'keep', 'a guest on Safari with songs: a free account');
    assert(installForm({ ...base, safari: true, signedIn: true, songs: true }) === 'offer', 'signed in on Safari: the plain offer');
    // N2 (account-nudges plan): from day 7, songs where installing is done or cannot keep them, a free account.
    assert(installForm({ ...base, songs: true, days: 6 }) === null, 'Firefox, week one: no card');
    assert(installForm({ ...base, songs: true, days: 7 }) === 'account', 'Firefox, day 7 with songs: a free account');
    assert(installForm({ ...base, songs: true, days: 7, isInstalled: true, safari: true }) === 'account', 'the iPhone app, day 7: a free account');
    assert(installForm({ ...base, songs: true, days: 9, prompt: true }) === 'songs', 'Chrome can still install: the install form');
    assert(installForm({ ...base, days: 9 }) === null && installForm({ ...base, songs: true, days: 9, signedIn: true }) === null, 'nothing recorded, or signed in: no account form');
    assert(installForm({ ...base, signedIn: true, isInstalled: true, prompt: true }) === null, 'installed and signed in: nothing');
});

test('install card: the Safari loss card only for a guest with something to keep, back after 4 days, storage not kept', () => {
    const base = { safari: true, isInstalled: false, persisted: false, signedIn: false, value: true, away: 4 };
    assert(riskShows(base), 'Safari, 4 days away');
    assert(!riskShows({ ...base, away: 3 }), '3 days away');
    assert(!riskShows({ ...base, safari: false }) && !riskShows({ ...base, isInstalled: true }), 'not Safari, or the added app');
    assert(!riskShows({ ...base, persisted: true }) && !riskShows({ ...base, persisted: null }), 'storage kept, or not known');
    assert(!riskShows({ ...base, signedIn: true }) && !riskShows({ ...base, value: false }), 'signed in, or nothing to keep');
});
