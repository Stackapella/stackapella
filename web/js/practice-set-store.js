// Practise the set's kept files (IndexedDB version 6, `sets`; docs/practice-playlist-plan.md, slice 4): a set made on
// this device opens at once the next time. One per group and sound; a set made from other songs replaces it, and any
// older than KEEP_DAYS goes. Device-only: never moved (move.js) or synced.

import { all, get, put, remove } from './db.js';
import { KEEP_DAYS } from './practice-set.js';

const STORE = 'sets';

export const setKey = (groupId, kind) => `${groupId}:${kind}`;

/** The kept set for `key`, or null (storage away: as if none). */
export async function keptSet(key) {
    try {
        return (await get(STORE, key)) ?? null;
    } catch (error) {
        console.warn('A kept set could not be read', error);
        return null;
    }
}

/** Keeps a made set (a full device only loses the instant open next time). */
export async function keepSet(record) {
    try {
        await put(STORE, { ...record, savedAt: Date.now(), bytes: record.blob.size });
    } catch (error) {
        console.warn('The set could not be kept on this device', error);
    }
}

/** Clears every kept set older than KEEP_DAYS. */
export async function sweepSets(now = Date.now()) {
    try {
        for (const record of await all(STORE)) if (now - record.savedAt >= KEEP_DAYS * 864e5) await remove(STORE, record.key);
    } catch (error) {
        console.warn('Old sets could not be cleared', error);
    }
}
