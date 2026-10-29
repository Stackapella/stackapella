// The contract from the app's side (docs/parallel-agents-plan.md, "Testing the contract from both
// sides"): api.js has every operation, the examples pass their schemas, the templates pass
// template.json, and the rules (headers, CSRF, errors) hold, against the mock and a fake server.
import { api, ApiError, APP_VERSION, call, decodePeaks8, functionFor, OPERATIONS, useTransport } from '/js/api.js';
import { loadContract, mockFetch, parseContract, useScenario } from '/js/api-mock.js';
import { check } from '/js/schema.js';
import { assert, test } from './harness.js';

const loadSchema = async (name) => (await fetch(`/contract/schemas/${name}`)).json();
const loadExample = async (name) => (await fetch(`/contract/examples/${name}`)).json();

async function refusal(promise) {
    try {
        await promise;
    } catch (error) {
        return error;
    }
    throw new Error('expected a refusal');
}

test('contract: every operation in api.md has its function in api.js, with the same method and path', async () => {
    const { routes } = await loadContract();
    assert(Object.keys(routes).length >= 10, `${Object.keys(routes).length} operations read`);
    for (const [operation, route] of Object.entries(routes)) {
        assert(OPERATIONS[operation]?.join(' ') === route.join(' '), `${operation}: api.md ${route.join(' ')}, api.js ${OPERATIONS[operation]?.join(' ')}`);
        assert(typeof functionFor(operation) === 'function', `${operation} has no function`);
    }
    for (const operation of Object.keys(OPERATIONS)) {
        assert(routes[operation], `${operation} is in api.js but not in api.md`);
    }
});

test("contract: operations only the native apps call are not the web client's", () => {
    const { routes } = parseContract('## Operations\n\nWho may call each.\n\n| Operation | Route | Who | Milestone |\n|---|---|---|---|\n| `auth.signin` | `POST /api/auth/sign-in` | Anyone (signed out) | M3 |\n| `coach.profile.show` | `GET /api/coach/profile` | An approved coach | M8 |\n| `auth.token.create` | `POST /api/auth/token` | An app, signed out | M11 |\n\n');
    assert(routes['auth.signin'] && routes['coach.profile.show'] && !routes['auth.token.create'], JSON.stringify(routes));
});

test('contract: every example passes its schema', async () => {
    const { operations } = await loadContract();
    let checked = 0;
    for (const [operation, cases] of Object.entries(operations)) {
        for (const c of cases.filter((c) => c.schema && c.example)) {
            const problems = await check(await loadExample(c.example), c.schema, loadSchema);
            assert(!problems.length, `${c.example} against ${c.schema}: ${problems.join('; ')}`);
            checked++;
        }
        assert(cases.length, `${operation} lists no cases`);
    }
    assert(checked >= 15, `only ${checked} examples checked`);
});

test('contract: all the templates pass template.json, and a broken one does not', async () => {
    const index = await (await fetch('/templates/index.json')).json();
    for (const id of index.templates) {
        const problems = await check(await (await fetch(`/templates/${id}.json`)).json(), 'template.json', loadSchema);
        assert(!problems.length, `${id}: ${problems.join('; ')}`);
    }
    const broken = await (await fetch('/templates/amazing-grace.json')).json();
    broken.parts[0].guide.notes[0].pitch = 'D4';
    broken.tempo = '80';
    delete broken.title;
    broken.extra = true;
    const problems = await check(broken, 'template.json', loadSchema);
    assert(problems.length === 4, problems.join('; '));
});

test('mock: answers with the examples, and a scenario picks a refusal with its code', async () => {
    useTransport(mockFetch);
    useScenario('');
    const me = await api.admin.me();
    assert(me.data.role === 'admin', JSON.stringify(me));
    useScenario('not_admin');
    const refused = await refusal(api.admin.me());
    assert(refused instanceof ApiError && refused.status === 403 && refused.code === 'not_admin', String(refused));
    useScenario('rate_limited');
    const limited = await refusal(api.admin.flags.list());
    assert(limited.status === 429 && limited.retryAfter === 1200, `${limited.status} ${limited.retryAfter}`);
    useScenario('admin.flags.update:sudo_required');
    assert((await api.admin.flags.list()).data.length > 0, 'other operations answer as usual');
    const sudo = await refusal(api.admin.flags.update('shield_mode', { value: 'on', reason: 'Testing' }));
    assert(sudo.status === 403 && sudo.code === 'sudo_required', `${sudo.status} ${sudo.code}`);
    useScenario('csrf_mismatch');
    assert((await api.admin.me()).data, 'a stale token is refreshed, and the call retried once');
    useScenario('');
});

test('mock: the admin lists keep their changes, with the contract refusals', async () => {
    useTransport(mockFetch);
    useScenario('');
    const updated = await api.admin.flags.update('shield_mode', { value: 'on', reason: 'A wave of bots' });
    assert(updated.data.value === 'on' && updated.data.reason === 'A wave of bots', JSON.stringify(updated));
    assert((await api.admin.flags.list()).data.find((f) => f.name === 'shield_mode').value === 'on', 'the list shows it');
    const missing = await refusal(api.admin.flags.update('no_such_switch', { value: 'on', reason: 'x' }));
    assert(missing.status === 404 && missing.code === 'not_found', missing.code);
    const invalid = await refusal(api.admin.flags.update('shield_mode', { value: 'sideways', reason: 'x' }));
    assert(invalid.status === 422 && invalid.code === 'validation_failed' && invalid.errors, JSON.stringify(invalid.errors));
    const block = await api.admin.blocklist.create({ type: 'email', value: 'bot@example.com', reason: 'Bots' });
    assert(block.data.id && (await api.admin.blocklist.list()).data[0].id === block.data.id, 'the new block is first');
    assert((await api.admin.blocklist.delete(block.data.id)) === null, 'a delete answers 204 with no body');
    const lowered = await api.admin.limits.update('sign-up', 'ip_hour', { max: 1, reason: 'Bots' });
    assert(lowered.data.max === 1 && lowered.data.override === 1, JSON.stringify(lowered.data));
    const reset = await api.admin.limits.update('sign-up', 'ip_hour', { max: null, reason: 'Over' });
    assert(reset.data.max === reset.data.default && reset.data.override === null, JSON.stringify(reset.data));
});

test('api: every call sends the version and JSON, changes send the CSRF token, and a 419 retries once after a GET', async () => {
    const seen = [];
    document.cookie = 'XSRF-TOKEN=abc%3D; path=/';
    let refuse = true;
    useTransport(async (url, init) => {
        seen.push({ url, method: init.method ?? 'GET', headers: init.headers, body: init.body });
        if (init.method === 'PUT' && refuse) {
            refuse = false;
            return new Response(JSON.stringify({ message: 'x', code: 'csrf_mismatch' }), { status: 419 });
        }
        return new Response(JSON.stringify({ data: {} }), { status: 200 });
    });
    await call('admin.flags.update', { params: { name: 'shield mode' }, body: { value: 'on', reason: 'r' } });
    assert(seen.map((s) => `${s.method} ${s.url}`).join(', ') === 'PUT /api/admin/flags/shield%20mode, GET /api/auth/config, PUT /api/admin/flags/shield%20mode', seen.map((s) => `${s.method} ${s.url}`).join(', '));
    const put = seen[0].headers;
    assert(put['X-App-Version'] === APP_VERSION && put.Accept === 'application/json' && put['Content-Type'] === 'application/json', JSON.stringify(put));
    assert(put['X-XSRF-TOKEN'] === 'abc=', `token ${put['X-XSRF-TOKEN']}`);
    assert(!('X-XSRF-TOKEN' in seen[1].headers), 'a GET sends no token');
    await call('admin.blocklist.list', { query: { type: 'email', cursor: '' } });
    assert(seen.at(-1).url === '/api/admin/blocklist?type=email', seen.at(-1).url);
    document.cookie = 'XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';
});

test("api: a server's error page (not JSON) is a server_error, and Retry-After is read from the header", async () => {
    useTransport(async () => new Response('<html>Bad gateway</html>', { status: 502 }));
    const broken = await refusal(api.admin.me());
    assert(broken.status === 502 && broken.code === 'server_error', `${broken.status} ${broken.code}`);
    useTransport(async () => new Response('{"message":"x","code":"rate_limited"}', { status: 429, headers: { 'Retry-After': '30' } }));
    assert((await refusal(api.admin.me())).retryAfter === 30, 'Retry-After');
    useTransport(mockFetch);
});

test("api: no connection is an ApiError with the code offline, and a 426 tells the app once", async () => {
    const { apiEvents } = await import('/js/api.js');
    useTransport(async () => {
        throw new TypeError('Failed to fetch');
    });
    const offline = await refusal(api.admin.me());
    assert(offline instanceof ApiError && offline.code === 'offline' && offline.status === 0, `${offline.code}`);
    let told = 0;
    apiEvents.outdated = () => told++;
    useTransport(async () => new Response('{"message":"x","code":"app_outdated"}', { status: 426 }));
    const outdated = await refusal(api.admin.me());
    assert(outdated.code === 'app_outdated' && told === 1, `told ${told}`);
    apiEvents.outdated = null;
    useTransport(mockFetch);
});

test('contract: the schema checker refuses what the PHP one refuses', async () => {
    const load = async () => ({ type: 'object', properties: { at: { type: 'string', format: 'date-time' }, url: { type: 'string', format: 'uri' } }, minimum: 1 });
    const problems = await check({ at: '2026-10-26T14:05Z', url: 'not a url' }, 'x.json', load);
    assert(problems.some((p) => p.includes('minimum')), 'an unknown keyword is reported');
    assert(problems.some((p) => p.includes('$.at')), 'a time without seconds is refused');
    assert(problems.some((p) => p.includes('$.url')), 'a URI without a scheme and host is refused');
    const good = await check({ at: '2026-10-26T14:05:00Z', url: 'https://example.com/a' }, 'x.json', async () => ({ type: 'object', properties: { at: { type: 'string', format: 'date-time' }, url: { type: 'string', format: 'uri' } } }));
    assert(!good.length, good.join('; '));
});

test('mock: lists are filtered by their query, and a followed cursor ends the list', async () => {
    useTransport(mockFetch);
    useScenario('');
    const all = (await api.admin.audit.list()).data.length;
    const flags = await api.admin.audit.list({ action: 'flag.' });
    assert(flags.data.length && flags.data.length < all && flags.data.every((e) => e.action.startsWith('flag.')), `${flags.data.length} of ${all}`);
    const next = await api.admin.audit.list({ cursor: 'abc' });
    assert(next.data.length === 0 && next.meta.nextCursor === null, JSON.stringify(next.meta));
    const emails = await api.admin.blocklist.list({ type: 'email_domain' });
    assert(emails.data.every((b) => b.type === 'email_domain'), 'filtered by type');
    await api.admin.blocklist.create({ type: 'email', value: ' Bot@Example.com ', reason: 'Bots' });
    const again = await refusal(api.admin.blocklist.create({ type: 'email', value: 'bot@example.com', reason: 'Bots' }));
    assert(again.code === 'validation_failed', 'the same email, cased differently, is a duplicate');
    useScenario('');
});

test('beta: the agreement shows **bold** as text segments, never HTML, and old Safari is told to switch', async () => {
    const { boldSegments, browserCanRecord } = await import('/js/views/beta.js');
    const segments = boldSegments('**You record parts,** and <b>this</b> stays text.');
    assert(segments.length === 2 && segments[0].bold && segments[0].text === 'You record parts,' && segments[1].text.includes('<b>this</b>'), JSON.stringify(segments));
    const modern = { AudioWorkletNode: class {}, navigator: { mediaDevices: { getUserMedia() {} } }, indexedDB: {} };
    assert(browserCanRecord('Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1', modern), 'Safari 18');
    assert(!browserCanRecord('Mozilla/5.0 (iPhone; CPU iPhone OS 15_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/15.0 Mobile/15E148 Safari/604.1', modern), 'Safari 15');
    assert(!browserCanRecord('Chrome', { ...modern, AudioWorkletNode: undefined }), 'no AudioWorklet');
});

test("peaks8: the contract's worked example decodes, and a take without it keeps its numbers", async () => {
    const peaks = decodePeaks8('AID/QA==');
    assert(peaks.length === 4 && peaks[0] === 0 && peaks[2] === 1 && Math.abs(peaks[1] - 128 / 255) < 1e-9 && Math.abs(peaks[3] - 64 / 255) < 1e-9, JSON.stringify(peaks));
    const asked = [];
    useTransport(async (url) => {
        asked.push(url);
        const take = { id: 't1', peaks: null, peaks8: 'AID/QA==' };
        const data = url.startsWith('/api/takes/') ? take : { takes: [take, { id: 't2', peaks: [0.5] }] };
        return new Response(JSON.stringify({ data }), { status: 200, headers: { 'Content-Type': 'application/json' } });
    });
    const pulled = await api.sync.pull();
    assert(pulled.data.takes[0].peaks.length === 4 && !('peaks8' in pulled.data.takes[0]) && pulled.data.takes[1].peaks[0] === 0.5, JSON.stringify(pulled.data.takes));
    const one = await api.takes.show('t1');
    assert(one.data.peaks[2] === 1, JSON.stringify(one.data));
    assert(asked.every((url) => url.includes('peaks=8')), asked.join(' '));
    useTransport(mockFetch);
});
