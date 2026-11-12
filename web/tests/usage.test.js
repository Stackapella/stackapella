// Usage counts (js/usage.js): only the allowlisted moments, added up into one batch with whether
// someone was signed in and nothing else; none sent while the server hasn't turned counting on.
import { count, countReturn, countShare, send, shareRef } from '/js/usage.js';
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

test('usage counts: a free lesson step reached goes as fs_r:<lesson>.<step>; a malformed one is dropped', async () => {
    const sent = [];
    const beacon = navigator.sendBeacon;
    const had = { config: account.config, me: account.me };
    navigator.sendBeacon = (url, blob) => {
        sent.push({ url, blob });
        return true;
    };
    try {
        account.config = { usageCounts: true };
        account.me = null;
        count('fs_r:4-phrasing.1');
        count('fs_r:4-phrasing.1');
        count('fs_r:1-match.0');
        count('fs_r:4-phrasing.x'); // not a step: dropped here
        send();
        const n = Object.fromEntries(JSON.parse(await sent[0].blob.text()).events.map((e) => [e.kind, e.n]));
        assert(n['fs_r:4-phrasing.1'] === 2 && n['fs_r:1-match.0'] === 1, JSON.stringify(n));
        assert(!('fs_r:4-phrasing.x' in n), JSON.stringify(n));
    } finally {
        navigator.sendBeacon = beacon;
        Object.assign(account, had);
    }
});

test('usage counts: a share counts as sh:<surface>.<method> only for the server\'s surfaces and methods', async () => {
    const sent = [];
    const beacon = navigator.sendBeacon;
    const had = { config: account.config, me: account.me };
    navigator.sendBeacon = (url, blob) => {
        sent.push({ url, blob });
        return true;
    };
    try {
        account.config = { usageCounts: true };
        account.me = null;
        send();
        sent.length = 0;
        countShare('daily', 'sheet');
        countShare('daily', 'sheet');
        countShare('daily', 'fax'); // not a method: dropped
        countShare('somewhere', 'copy'); // not a surface: dropped
        count('sh:daily.copy'); // count() never takes a share: countShare does
        send();
        const kinds = Object.fromEntries(JSON.parse(await sent[0].blob.text()).events.map((e) => [e.kind, e.n]));
        assert(JSON.stringify(kinds) === '{"sh:daily.sheet":2}', `only the known share, twice: ${JSON.stringify(kinds)}`);
        assert(shareRef('daily') === 'share-daily', 'a shared link\'s source tag');
    } finally {
        navigator.sendBeacon = beacon;
        Object.assign(account, had);
    }
});

test('usage counts: coming back on day 1, 7, 14 or 30 since arriving, and once in days 1 to 6', async () => {
    const sent = [];
    const beacon = navigator.sendBeacon;
    const had = { config: account.config, me: account.me, funnel: localStorage.getItem('stackapella.funnel'), returned: localStorage.getItem('stackapella.returned') };
    navigator.sendBeacon = (url, blob) => {
        sent.push({ url, blob });
        return true;
    };
    const kinds = async () => {
        send();
        const batch = sent.pop();
        return batch ? JSON.parse(await batch.blob.text()).events.map((e) => e.kind) : [];
    };
    try {
        account.config = { usageCounts: true };
        account.me = null;
        localStorage.removeItem('stackapella.returned');
        const day = (n) => new Date(2026, 10, 1 + n, 12).getTime(); // noon, local, n days after 1 November
        localStorage.setItem('stackapella.funnel', JSON.stringify({ firsts: {}, arrived: '2026-11-01' }));
        countReturn(day(1));
        assert(JSON.stringify((await kinds()).sort()) === '["back_d1","back_w1"]', 'day 1: back_d1 and the week\'s once');
        countReturn(day(3));
        assert((await kinds()).length === 0, 'day 3: the week was counted already');
        countReturn(day(7));
        assert(JSON.stringify(await kinds()) === '["back_d7"]', 'day 7');
        countReturn(day(30));
        assert(JSON.stringify(await kinds()) === '["back_d30"]', 'day 30');
        localStorage.removeItem('stackapella.funnel');
        countReturn(day(7));
        assert((await kinds()).length === 0, 'no arrival day kept: nothing');
    } finally {
        navigator.sendBeacon = beacon;
        account.config = had.config;
        account.me = had.me;
        if (had.funnel === null) localStorage.removeItem('stackapella.funnel');
        else localStorage.setItem('stackapella.funnel', had.funnel);
        if (had.returned === null) localStorage.removeItem('stackapella.returned');
        else localStorage.setItem('stackapella.returned', had.returned);
    }
});
