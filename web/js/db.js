// IndexedDB, the app's storage on the device. Only the stores use it, so the API can sit behind
// them later. The versions are assigned in docs/architecture.md ("IndexedDB"), and each is claimed
// in contract/log.md before it's used; a version is never reused.
//
// Version 1:
//   projects  { id, title, bpm, key, timeSignature, countInBars, metronome, createdAt, updatedAt }
//   parts     { id, projectId, name, color, order, volume, pan, muted, solo, offsetSeconds,
//               sampleRate, frames, peaks, createdAt }  (index: projectId)
//   audio     a part's samples, keyed by part id
// Version 2 (M1):
//   audio     stored as 16-bit (Int16Array), half the space on phones. Converted here, at the edge:
//             the app only ever sees Float32Array (putAudio, getAudio). Version 1's takes are
//             converted in the upgrade.
//   guides    recorded guide audio (M2), keyed by `templateId@version:partKey`
//   betaTakes takes waiting to be sent by beta singers (M2), { id, ... }
//   learning  the course's progress (M3), one record per key ('progress', 'goal', ...)
// Version 3 (M4, cloud save; contract/api.md "Projects and cloud save"):
//   outbox    what's waiting to go to the account, one entry per record: { key: 'kind:id', kind,
//             targetId, changeId, at } (a newer change to the same record replaces its entry)
//   meta      the sync's own state, by key: 'cursor', 'conflicts', ...
//   Projects and parts gain `version` (the account's, 0 before the first save); a part gains
//   `takeId` (its current take's id, made when it's recorded) and `syncedTakeId`. No migration:
//   records without them are treated as never sent.
// Version 4 was set aside for a warm-ups log (docs/architecture.md); the workout keeps its own in
//   `learning`, so it was never used.
// Version 5 (M7, the template creator; docs/template-creator-plan.md, "Storage"):
//   templates       the person's own templates: { id, template (the latest version's JSON),
//                   visibility, libraryOptIn, savedVersion (the account's, 0 before the first
//                   save), state, deleted, updatedAt }
//   templateDrafts  the creator's drafts, autosaved: { id, step, template, lines, ..., updatedAt }

const VERSION = 5;
let name = 'stackapella';
let opening = null;
let deleted = false; // the whole database deleted (deleteDatabase): nothing opens it again until a reload

/**
 * What the app shows when storage needs the person: `blocked` when another tab still has an older
 * version open (this one waits until it closes), `replaced` when a newer version opened in
 * another tab (this one must reload before it saves again).
 */
export const storageEvents = { blocked: null, replaced: null };

/** Tests only: use another database, so they never touch the person's projects. */
export function useDatabase(other) {
    name = other;
    opening = null;
}

function open() {
    if (deleted) return Promise.reject(new Error('Storage on this device was deleted: reload'));
    opening ??= new Promise((resolve, reject) => {
        const request = indexedDB.open(name, VERSION);
        request.onupgradeneeded = (event) => upgrade(request.result, request.transaction, event.oldVersion);
        request.onsuccess = () => {
            const db = request.result;
            // A newer version opening in another tab: step aside, and ask for a reload.
            db.onversionchange = () => {
                db.close();
                opening = null;
                storageEvents.replaced?.();
            };
            // Safari can drop the connection while the app is in the background: open again.
            db.onclose = () => {
                opening = null;
            };
            resolve(db);
        };
        request.onerror = () => {
            opening = null; // try again next time, rather than failing for the whole session
            reject(request.error);
        };
        // Another tab still has an older version open: this open waits until it closes.
        request.onblocked = () => storageEvents.blocked?.();
    });
    return opening;
}

function upgrade(db, transaction, from) {
    if (from < 1) {
        db.createObjectStore('projects', { keyPath: 'id' });
        db.createObjectStore('parts', { keyPath: 'id' }).createIndex('projectId', 'projectId');
        db.createObjectStore('audio');
    }
    if (from < 2) {
        db.createObjectStore('guides');
        db.createObjectStore('betaTakes', { keyPath: 'id' });
        db.createObjectStore('learning');
        // Version 1 kept takes as 32-bit floats.
        transaction.objectStore('audio').openCursor().onsuccess = (event) => {
            const cursor = event.target.result;
            if (!cursor) return;
            if (cursor.value instanceof Float32Array) {
                cursor.update(toInt16(cursor.value));
            }
            cursor.continue();
        };
    }
    if (from < 3) {
        db.createObjectStore('outbox', { keyPath: 'key' });
        db.createObjectStore('meta');
    }
    if (from < 5) {
        db.createObjectStore('templates', { keyPath: 'id' });
        db.createObjectStore('templateDrafts', { keyPath: 'id' });
    }
}

function done(request) {
    return new Promise((resolve, reject) => {
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

async function store(storeName, mode = 'readonly') {
    return (await open()).transaction(storeName, mode).objectStore(storeName);
}

export async function all(storeName) {
    return done((await store(storeName)).getAll());
}

export async function allBy(storeName, index, key) {
    return done((await store(storeName)).index(index).getAll(key));
}

export async function keys(storeName) {
    return done((await store(storeName)).getAllKeys());
}

export async function get(storeName, key) {
    return done((await store(storeName)).get(key));
}

export async function put(storeName, value, key) {
    return done((await store(storeName, 'readwrite')).put(value, key));
}

export async function remove(storeName, key) {
    return done((await store(storeName, 'readwrite')).delete(key));
}

/**
 * Deletes everything kept on this device, every store (Settings, "Delete everything on this
 * device"). Another tab with it open steps aside (onversionchange), and the delete waits for that.
 */
export async function deleteEverything() {
    deleted = true;
    const db = await opening?.catch(() => null);
    db?.close();
    opening = null;
    await new Promise((resolve, reject) => {
        const request = indexedDB.deleteDatabase(name);
        request.onsuccess = () => resolve();
        request.onerror = () => reject(request.error);
    });
}

/** Saves a take for `key`, as 16-bit. Only for stores with keys of their own: `audio`, `guides`. */
export async function putAudio(key, samples, storeName = 'audio') {
    return put(storeName, toInt16(samples), key);
}

/** The audio saved for `key`, as a Float32Array, or undefined. */
export async function getAudio(key, storeName = 'audio') {
    const stored = await get(storeName, key);
    return stored instanceof Int16Array ? toFloat32(stored) : stored;
}

/** -1 to 1 as 16-bit, rounded and clipped, on the same scale as a 16-bit WAV (wav.js). */
export function toInt16(samples) {
    const out = new Int16Array(samples.length);
    for (let i = 0; i < samples.length; i++) {
        const v = Math.max(-1, Math.min(1, samples[i]));
        out[i] = Math.round(v < 0 ? v * 0x8000 : v * 0x7fff);
    }
    return out;
}

export function toFloat32(samples) {
    const out = new Float32Array(samples.length);
    for (let i = 0; i < samples.length; i++) {
        const v = samples[i];
        out[i] = v < 0 ? v / 0x8000 : v / 0x7fff;
    }
    return out;
}

/**
 * Asks the browser not to clear our storage under pressure. Safari otherwise clears a site's
 * storage after 7 days without a visit (not for installed apps).
 */
export async function persist() {
    try {
        return (await navigator.storage?.persist?.()) ?? false;
    } catch {
        return false;
    }
}
