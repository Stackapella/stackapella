// The device's storage (js/db.js), in databases of the tests' own, never the person's projects.
import * as db from '/js/db.js';
import { assert, close, test } from './harness.js';

function done(request) {
    return new Promise((resolve, reject) => {
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

async function fresh(name) {
    await done(indexedDB.deleteDatabase(name));
    db.useDatabase(name);
}

test('db: a take is stored as 16-bit, half the size, and comes back as floats within one step', async () => {
    await fresh('stackapella-test');
    const take = Float32Array.from({ length: 48000 }, (_, i) => 0.8 * Math.sin(i / 7) * Math.cos(i / 1300));
    take[0] = 1.5; // clipped, as a WAV would be
    take[1] = -1;
    take[2] = Number.NaN; // silence, never noise
    await db.putAudio('part-1', take);
    const stored = await db.get('audio', 'part-1');
    assert(stored instanceof Int16Array, `stored as ${stored?.constructor?.name}`);
    assert(stored.byteLength === take.byteLength / 2, `${stored.byteLength} bytes`);
    const back = await db.getAudio('part-1');
    assert(back instanceof Float32Array && back.length === take.length, 'came back as floats, every sample');
    assert(back[0] === 1 && back[1] === -1 && back[2] === 0, `clipped ends ${back[0]}, ${back[1]}, NaN as ${back[2]}`);
    let worst = 0;
    for (let i = 3; i < take.length; i++) worst = Math.max(worst, Math.abs(back[i] - take[i]));
    close(worst, 0, 0.5 / 32767 + 1e-7, 'worst sample difference (half a step)');
    assert((await db.getAudio('missing')) === undefined, 'a missing take is undefined');
});

test('db: version 2 converts version 1 takes to 16-bit, keeps projects and parts, and adds the new stores', async () => {
    const name = 'stackapella-test-upgrade';
    await done(indexedDB.deleteDatabase(name));
    // A device still on version 1, with one project and its take as 32-bit floats.
    const request = indexedDB.open(name, 1);
    request.onupgradeneeded = () => {
        const old = request.result;
        old.createObjectStore('projects', { keyPath: 'id' });
        old.createObjectStore('parts', { keyPath: 'id' }).createIndex('projectId', 'projectId');
        old.createObjectStore('audio');
    };
    const v1 = await done(request);
    const take = Float32Array.from({ length: 1000 }, (_, i) => Math.sin(i / 3) * 0.5);
    const tx = v1.transaction(['projects', 'parts', 'audio'], 'readwrite');
    tx.objectStore('projects').put({ id: 'p1', title: 'Old song' });
    tx.objectStore('parts').put({ id: 'a1', projectId: 'p1', name: 'Melody' });
    tx.objectStore('audio').put(take, 'a1');
    tx.objectStore('audio').put(take.slice(0, 10), 'a2');
    tx.objectStore('audio').put('not audio', 'odd'); // left as it is
    await new Promise((resolve) => (tx.oncomplete = resolve));
    v1.close();

    db.useDatabase(name);
    assert((await db.get('projects', 'p1')).title === 'Old song', 'the project survived');
    assert((await db.allBy('parts', 'projectId', 'p1')).length === 1, 'the part survived');
    assert((await db.get('audio', 'a1')) instanceof Int16Array, 'the take was converted');
    const back = await db.getAudio('a1');
    for (let i = 0; i < take.length; i++) close(back[i], take[i], 0.5 / 32767 + 1e-7, `sample ${i}`);
    assert((await db.get('audio', 'a2')) instanceof Int16Array, 'every take was converted');
    assert((await db.get('audio', 'odd')) === 'not audio', 'other values are left alone');
    for (const store of ['guides', 'betaTakes', 'learning']) {
        assert((await db.all(store)).length === 0, `${store} exists and is empty`);
    }
    await db.put('learning', { stars: 3 }, 'progress');
    assert((await db.get('learning', 'progress')).stars === 3, 'learning stores a record');
    db.useDatabase('stackapella-test');
});

test('db: an upgrade waits for an old tab, says so, and finishes when it closes', async () => {
    const name = 'stackapella-test-blocked';
    await done(indexedDB.deleteDatabase(name));
    const request = indexedDB.open(name, 1);
    request.onupgradeneeded = () => {
        request.result.createObjectStore('projects', { keyPath: 'id' });
        request.result.createObjectStore('parts', { keyPath: 'id' }).createIndex('projectId', 'projectId');
        request.result.createObjectStore('audio');
    };
    const oldTab = await done(request); // a version 1 tab, with no versionchange handler
    let told = false;
    db.storageEvents.blocked = () => {
        told = true;
        setTimeout(() => oldTab.close(), 100); // the person closes the old tab
    };
    db.useDatabase(name);
    const projects = await db.all('projects');
    db.storageEvents.blocked = null;
    assert(told, 'the person was told to close the other tab');
    assert(Array.isArray(projects), 'the upgrade finished');
    db.useDatabase('stackapella-test');
});

test('db: a newer version of the data than this build knows asks for a reload once, and never reports a fault', async () => {
    const name = 'stackapella-test-newer';
    await done(indexedDB.deleteDatabase(name));
    const later = await done(indexedDB.open(name, 99)); // stored by a later build
    later.close();
    let told = 0;
    db.storageEvents.replaced = () => told++;
    db.useDatabase(name);
    const first = await db.all('projects').then(() => null, (error) => error);
    const second = await db.get('projects', 'x').then(() => null, (error) => error);
    db.storageEvents.replaced = null;
    assert(first instanceof db.NewerVersion && second instanceof db.NewerVersion, `refused as a newer version: ${first?.name}, ${second?.name}`);
    assert(told === 1, `Reload asked ${told} times`);
    const { isNoise } = await import('/js/errors.js');
    assert(isNoise(first), 'left out of the error reports');
    db.useDatabase('stackapella-test');
});

test('db: a newer version upgrading in another tab closes this one cleanly, and it never reopens at the old version', async () => {
    const name = 'stackapella-test-replaced';
    await done(indexedDB.deleteDatabase(name));
    let told = 0;
    db.storageEvents.replaced = () => told++;
    db.useDatabase(name);
    await db.all('projects'); // this tab, open at this build's version
    const upgrade = indexedDB.open(name, 99); // a newer tab upgrades: this one is asked to step aside
    upgrade.onupgradeneeded = () => {};
    const newerTab = await done(upgrade);
    newerTab.close();
    const after = await db.all('projects').then(() => null, (error) => error);
    db.storageEvents.replaced = null;
    assert(told === 1, `Reload asked ${told} times`);
    assert(after instanceof db.NewerVersion, `not reopened at the old version: ${after?.name}`);
    db.useDatabase('stackapella-test');
});

// Sentry JAVASCRIPT-F: back from the background, iOS Safari can hand back a connection that's already closing, before
// onclose fires. The store is opened again and tried once more; a second failure rejects as before.
test('db: a connection found closing is opened again and the read goes through; twice, it fails as before', async () => {
    await fresh('stackapella-test-closing');
    await db.put('meta', 'kept', 'note');
    const real = IDBDatabase.prototype.transaction;
    let fails = 1;
    let calls = 0;
    IDBDatabase.prototype.transaction = function (...args) {
        calls++;
        if (fails > 0) {
            fails--;
            this.close(); // closing, as Safari leaves it
            throw new DOMException("Failed to execute 'transaction' on 'IDBDatabase': The database connection is closing.", 'InvalidStateError');
        }
        return real.apply(this, args);
    };
    try {
        assert((await db.get('meta', 'note')) === 'kept', 'read after opening again');
        assert(calls === 2, `tried twice (${calls})`);
        assert((await db.get('meta', 'note')) === 'kept' && calls === 3, 'the new connection is kept for the next read');
        fails = 2;
        const error = await db.get('meta', 'note').then(() => null, (e) => e);
        assert(error?.name === 'InvalidStateError', `a second failure rejects (${error?.name})`);
    } finally {
        IDBDatabase.prototype.transaction = real;
    }
    assert((await db.get('meta', 'note')) === 'kept', 'fine again once the connection is');
    db.useDatabase('stackapella-test');
});
