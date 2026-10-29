// Moving a device's songs to the app's new address (docs/app-subdomain-plan.md, "Old installs"): the
// app moved from beta.stackapella.com to app.stackapella.com, and a browser keeps each address's
// storage apart. The old address's "Stackapella has moved" page (js/public/moving.js) reads what this
// device kept there, and hands it to the new address's /moved page (js/views/moved.js): in a window
// the person opens, record by record by postMessage, or as a file where a home-screen app on iPhone
// can't share a window with the browser. Nothing on the new address is overwritten: what's there
// already stays. Plain module, no Vue: the old page loads it without the app.

/** What moves: songs, takes, the course, the outbox and the account's sync state, own templates, drafts, beta takes. */
export const STORES = ['projects', 'parts', 'audio', 'learning', 'outbox', 'meta', 'templates', 'templateDrafts', 'betaTakes'];
const LOCAL_PREFIX = 'stackapella.';
const DATABASE = 'stackapella';
const MAGIC = 'STACKAPELLA-MOVE-1\n';

export const MESSAGE = { ready: 'stackapella-move-ready', batch: 'stackapella-move-batch', ack: 'stackapella-move-ack', done: 'stackapella-move-done' };

/** Whether a message may come from `origin`: Stackapella's own hosts, or this machine while testing. */
export function trustedOrigin(origin) {
    try {
        const { hostname, protocol } = new URL(origin);
        if (['localhost', '127.0.0.1'].includes(hostname) || hostname.endsWith('.test')) return true;
        return protocol === 'https:' && (hostname === 'stackapella.com' || hostname.endsWith('.stackapella.com'));
    } catch {
        return false;
    }
}

const done = (request) =>
    new Promise((resolve, reject) => {
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });

/**
 * The old address's database, opened as it is (never created or upgraded here), or null when this
 * device never kept anything there.
 */
export function openOld(name = DATABASE) {
    return new Promise((resolve) => {
        const request = indexedDB.open(name);
        // Nothing was kept here: the open is undone, so no empty database is made.
        request.onupgradeneeded = () => request.transaction.abort();
        request.onsuccess = () => resolve(request.result);
        request.onerror = (event) => {
            event.preventDefault(); // the abort above, or storage blocked: either way, nothing to move
            resolve(null);
        };
    });
}

/** Every record to move, as `{ store, key, value }`, in STORES' order (projects before their parts and takes). */
export async function readRecords(db) {
    const records = [];
    for (const store of STORES.filter((s) => db.objectStoreNames.contains(s))) {
        const objects = db.transaction(store).objectStore(store);
        const [keys, values] = await Promise.all([done(objects.getAllKeys()), done(objects.getAll())]);
        keys.forEach((key, i) => records.push({ store, key, value: values[i] }));
    }
    return records;
}

/** The songs worth saying: projects, own templates and drafts, not counting deleted ones. */
export function countSongs(records) {
    return records.filter((r) => (r.store === 'projects' || r.store === 'templates' || r.store === 'templateDrafts') && !r.value?.deleted).length;
}

/** The app's own remembered settings (theme, mic, ...) on this address. */
export function localItems(storage = window.localStorage) {
    const items = {};
    try {
        for (let i = 0; i < storage.length; i++) {
            const key = storage.key(i);
            if (key?.startsWith(LOCAL_PREFIX)) items[key] = storage.getItem(key);
        }
    } catch (error) {
        console.warn('Settings on this address could not be read', error);
    }
    return items;
}

/** Records in batches for postMessage: takes one at a time (they're big), the rest 50 at a time. */
export function batches(records) {
    const out = [];
    let batch = [];
    for (const record of records) {
        if (record.store === 'audio') {
            if (batch.length) out.push(batch);
            out.push([record]);
            batch = [];
            continue;
        }
        batch.push(record);
        if (batch.length === 50) {
            out.push(batch);
            batch = [];
        }
    }
    if (batch.length) out.push(batch);
    return out;
}

// ---- As a file (an iPhone's home-screen app can't hand over by window) ---------------------------

const TYPED = { Int16Array, Float32Array, Uint8Array, Int8Array, Uint16Array, Int32Array, Uint32Array, Float64Array };

/** The records and settings as one file: a header (JSON, the binary parts by index) and the binary parts after it. */
export function packFile(records, local) {
    const binaries = [];
    const lift = (value) => {
        if (ArrayBuffer.isView(value)) {
            binaries.push(new Uint8Array(value.buffer, value.byteOffset, value.byteLength));
            return { $bin: binaries.length - 1, $type: value.constructor.name };
        }
        if (Array.isArray(value)) return value.map(lift);
        if (value && typeof value === 'object') return Object.fromEntries(Object.entries(value).map(([k, v]) => [k, lift(v)]));
        return value;
    };
    const body = { records: records.map((r) => ({ store: r.store, key: lift(r.key), value: lift(r.value) })), local, sizes: [] };
    body.sizes = binaries.map((b) => b.byteLength);
    const header = new TextEncoder().encode(JSON.stringify(body));
    const length = new TextEncoder().encode(String(header.byteLength).padStart(12, '0'));
    return new Blob([MAGIC, length, header, ...binaries], { type: 'application/octet-stream' });
}

/** A moving file back as `{ records, local }`; throws when it isn't one. */
export async function unpackFile(blob) {
    const bytes = new Uint8Array(await blob.arrayBuffer());
    const magic = new TextEncoder().encode(MAGIC);
    if (bytes.length < magic.length + 12 || !magic.every((b, i) => bytes[i] === b)) throw new Error('Not a Stackapella moving file');
    const headerLength = Number(new TextDecoder().decode(bytes.subarray(magic.length, magic.length + 12)));
    let at = magic.length + 12;
    const body = JSON.parse(new TextDecoder().decode(bytes.subarray(at, at + headerLength)));
    at += headerLength;
    const parts = body.sizes.map((size) => {
        const part = bytes.slice(at, at + size); // a copy, aligned for the typed array
        at += size;
        return part.buffer;
    });
    const drop = (value) => {
        if (value && typeof value === 'object' && '$bin' in value) {
            const Type = TYPED[value.$type];
            if (!Type) throw new Error(`Unknown binary type ${value.$type}`);
            return new Type(parts[value.$bin]);
        }
        if (Array.isArray(value)) return value.map(drop);
        if (value && typeof value === 'object') return Object.fromEntries(Object.entries(value).map(([k, v]) => [k, drop(v)]));
        return value;
    };
    return { records: body.records.map((r) => ({ store: r.store, key: drop(r.key), value: drop(r.value) })), local: body.local ?? {} };
}

// ---- On the new address --------------------------------------------------------------------------

/**
 * Keeps moved records in this address's database (opened by the app's own db.js, so it's the
 * current version): one already here stays as it is. Returns how many were added.
 */
export async function keepRecords(db, records) {
    let added = 0;
    for (const { store, key, value } of records) {
        if (!STORES.includes(store)) continue;
        if ((await db.get(store, key)) !== undefined) continue;
        await (store === 'audio' || store === 'learning' || store === 'meta' ? db.put(store, value, key) : db.put(store, value));
        added++;
    }
    return added;
}

/** Keeps moved settings that aren't set on this address yet. */
export function keepLocal(items, storage = window.localStorage) {
    for (const [key, value] of Object.entries(items ?? {})) {
        if (!key.startsWith(LOCAL_PREFIX) || typeof value !== 'string') continue;
        try {
            if (storage.getItem(key) === null) storage.setItem(key, value);
        } catch (error) {
            console.warn('A setting was not kept', key, error);
        }
    }
}
