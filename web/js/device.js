// What's kept on this device alone, and deleting it (Settings, "Delete everything on this device";
// the Storage page's course voice takes). Then and now keeps a lesson's first take here, never
// synced: `course:<id>` in `audio` (the voice) and in `learning` (its line and trace).

import * as db from './db.js';

const COURSE = 'course:';
const PREFIX = 'stackapella.'; // every key the app keeps in the browser's storage

/** The course voice takes kept here: their keys and their size. */
export async function courseTakes() {
    const keys = (await db.keys('learning')).filter((key) => typeof key === 'string' && key.startsWith(COURSE));
    const voices = await Promise.all(keys.map((key) => db.get('audio', key).catch(() => null)));
    return { keys, bytes: voices.reduce((sum, voice) => sum + (voice?.byteLength ?? 0), 0) };
}

/** Deletes the course voice takes (Then and now keeps the next one sung). Stars and progress stay. */
export async function deleteCourseTakes() {
    const { keys } = await courseTakes();
    for (const key of keys) {
        await db.remove('audio', key);
        await db.remove('learning', key);
    }
    return keys.length;
}

/**
 * What the device delete takes, counted for its confirm: the songs (and those not in the account),
 * the course voice takes, the creator's drafts and beta takes not yet sent.
 */
export async function deviceSummary() {
    const [projects, takes, drafts, beta] = await Promise.all([db.all('projects'), courseTakes(), db.all('templateDrafts'), db.all('betaTakes')]);
    const songs = projects.filter((p) => !p.course && !p.deleted);
    return { songs: songs.length, notBackedUp: songs.filter((p) => !(p.version > 0)).length, courseTakes: takes.keys.length, drafts: drafts.length, betaTakes: beta.length };
}

/** Everything kept here: the database, and the app's keys in the browser's storage. */
export async function deleteEverythingHere() {
    await db.deleteEverything();
    for (const area of [globalThis.localStorage, globalThis.sessionStorage]) {
        try {
            for (const key of Object.keys(area ?? {})) if (key.startsWith(PREFIX)) area.removeItem(key);
        } catch (error) {
            console.warn('The browser storage could not be cleared', error?.message ?? error);
        }
    }
}
