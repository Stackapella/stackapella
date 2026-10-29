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
