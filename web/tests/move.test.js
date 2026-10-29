// Moving songs to the app's new address (js/move.js; docs/app-subdomain-plan.md): which hosts may
// hand songs over, takes sent one at a time, a moving file that comes back exactly as it went, and
// nothing already on the new address overwritten.
import { batches, countSongs, keepLocal, keepRecords, packFile, trustedOrigin, unpackFile } from '/js/move.js';
import { assert, test } from './harness.js';

test('move: only Stackapella hosts (or this machine) may hand songs over', () => {
    for (const ok of ['https://beta.stackapella.com', 'https://stackapella.com', 'http://127.0.0.1:8330', 'http://localhost:8330', 'https://stackapella.test']) assert(trustedOrigin(ok), ok);
    for (const no of ['https://evil.com', 'http://beta.stackapella.com', 'https://stackapella.com.evil.com', 'https://notstackapella.com', 'null', '']) assert(!trustedOrigin(no), no);
});

test('move: takes go one to a batch, the rest fifty at a time, in order', () => {
    const records = [
        ...Array.from({ length: 60 }, (_, i) => ({ store: 'projects', key: `p${i}`, value: { id: `p${i}` } })),
        { store: 'audio', key: 'a1', value: new Int16Array(4) },
        { store: 'parts', key: 'x', value: { id: 'x' } },
    ];
    const out = batches(records);
    assert(out.map((b) => b.length).join() === '50,10,1,1', out.map((b) => b.length).join());
    assert(out.flat().map((r) => r.key).join() === records.map((r) => r.key).join(), 'in order');
});

test('move: a moving file comes back as it went, typed arrays and all', async () => {
    const take = Int16Array.from([1, -2, 32767, -32768]);
    const peaks = Float32Array.from([0.5, 0.25]);
    const records = [
        { store: 'projects', key: 'p1', value: { id: 'p1', title: 'Lullaby ♪', deleted: false } },
        { store: 'parts', key: 'x1', value: { id: 'x1', projectId: 'p1', peaks } },
        { store: 'audio', key: 'x1', value: take },
        { store: 'templates', key: 't1', value: { id: 't1', deleted: true } },
    ];
    const back = await unpackFile(packFile(records, { 'stackapella.mic': 'abc' }));
    assert(back.records.length === 4, 'all of them');
    assert(back.records[0].value.title === 'Lullaby ♪', 'words intact');
    assert(back.records[2].value instanceof Int16Array && back.records[2].value.join() === take.join(), 'the take, as 16-bit');
    assert(back.records[1].value.peaks instanceof Float32Array && back.records[1].value.peaks[1] === 0.25, 'peaks inside a record');
    assert(back.local['stackapella.mic'] === 'abc', 'settings');
    assert(countSongs(back.records) === 1, 'a deleted template is not a song');
    let refused = false;
    await unpackFile(new Blob(['not a moving file at all, sorry'])).catch(() => (refused = true));
    assert(refused, 'another file is refused');
});

test('move: what is on the new address already stays as it is', async () => {
    const kept = new Map([['projects:p1', { id: 'p1', title: 'Here first' }]]);
    const fake = {
        get: async (store, key) => kept.get(`${store}:${key}`),
        put: async (store, value, key) => kept.set(`${store}:${key ?? value.id}`, value),
    };
    const added = await keepRecords(fake, [
        { store: 'projects', key: 'p1', value: { id: 'p1', title: 'From the old address' } },
        { store: 'projects', key: 'p2', value: { id: 'p2', title: 'New' } },
        { store: 'audio', key: 'x1', value: new Int16Array(2) },
        { store: 'guides', key: 'g', value: {} },
    ]);
    assert(added === 2, `${added} added`);
    assert(kept.get('projects:p1').title === 'Here first', 'not overwritten');
    assert(kept.has('audio:x1') && !kept.has('guides:g'), 'takes by their key; guides are not moved');
    const storage = new Map([['stackapella.mic', 'here']]);
    const fakeStorage = { getItem: (k) => storage.get(k) ?? null, setItem: (k, v) => storage.set(k, v) };
    keepLocal({ 'stackapella.mic': 'old', 'stackapella.appearance': '{}', other: 'x' }, fakeStorage);
    assert(storage.get('stackapella.mic') === 'here' && storage.get('stackapella.appearance') === '{}' && !storage.has('other'), 'settings: only new ones, only ours');
});
