// Usage counts (js/usage.js): only the allowlisted moments, added up into one batch with whether
// someone was signed in and nothing else; none sent while the server hasn't turned counting on.
import { count, send } from '/js/usage.js';
import { account } from '/js/stores/account.js';
import { assert, test } from './harness.js';

test('usage counts: a batch of allowlisted moments, with no ID, only when the server says so', async () => {
    const sent = [];
    const beacon = navigator.sendBeacon;
    const had = { config: account.config, me: account.me };
    navigator.sendBeacon = (url, blob) => {
        sent.push({ url, blob });
        return true;
    };
    try {
        account.config = { usageCounts: false };
        count('lesson_done');
        send();
        assert(sent.length === 0, 'nothing goes while counting is off');
        account.config = { usageCounts: true };
        account.me = null;
        count('lesson_done');
        count('lesson_done');
        count('song_created');
        count('somebody@example.com'); // not a moment: ignored
        send();
        assert(sent.length === 1 && sent[0].url === '/api/counts', 'one batch, to our own server');
        const body = JSON.parse(await sent[0].blob.text());
        assert(JSON.stringify(Object.keys(body).sort()) === '["events","signedIn"]', `only the moments and signed in: ${Object.keys(body)}`);
        assert(body.signedIn === false, 'a guest');
        const n = Object.fromEntries(body.events.map((e) => [e.kind, e.n]));
        assert(n.lesson_done === 2 && n.song_created === 1 && Object.keys(n).length === 2, JSON.stringify(n));
        send();
        assert(sent.length === 1, 'nothing more to send');
    } finally {
        navigator.sendBeacon = beacon;
        Object.assign(account, had);
    }
});
