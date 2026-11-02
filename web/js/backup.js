// A backup file (docs/account-nudges-plan.md, Hillel's answer 3): a guest's way to keep their work
// without an account. Settings' guest card downloads everything that's only here as one ZIP (songs,
// takes, the course's progress and voice takes, made templates and drafts, and the singer's own
// choices), and restores from it on any device. A restore adds, never takes away: a song comes back
// unless this device has a newer copy, progress is merged as two devices' are (mergeProgress), and
// nothing here is overwritten by an older choice.

import * as db from './db.js';
import { local } from './storage.js';
import { mergeProgress } from './course.js';
import { unzip, zip } from './zip.js';

const FORMAT = 1;
const INDEX = 'stackapella-backup.json';
const RECORDS = ['projects', 'templates', 'templateDrafts'];
// The singer's own choices in the browser's storage (never this device's mic, tests or counts).
const PREFS = ['appearance', 'ghosts', 'dailyDays', 'warmups', 'warmups.chosen', 'sharpen', 'singPrefs', 'singWay', 'shareName', 'titlesSeen', 'firstWeekSeen', 'headphones.never', 'gameEffects', 'lang', 'voiceNotesOff'];

/** JSON that keeps typed arrays (a part's peaks, say) as what they were. */
const replacer = (key, value) => (ArrayBuffer.isView(value) ? { $typed: value.constructor.name, values: Array.from(value) } : value);
const TYPED = { Float32Array, Float64Array, Int16Array, Int32Array, Uint8Array, Uint16Array, Uint32Array, Int8Array };
const reviver = (key, value) => (value && typeof value === 'object' && TYPED[value.$typed] && Array.isArray(value.values) ? TYPED[value.$typed].from(value.values) : value);

const asInt16 = (audio) => (audio instanceof Int16Array ? audio : audio instanceof Float32Array ? db.toInt16(audio) : null);
const isCourseKey = (key) => typeof key === 'string' && key.startsWith('course:');

/** The backup's file name for a day: stackapella-backup-2026-10-31.zip. */
export function backupName(at = new Date()) {
    const day = [at.getFullYear(), String(at.getMonth() + 1).padStart(2, '0'), String(at.getDate()).padStart(2, '0')].join('-');
    return `stackapella-backup-${day}.zip`;
}

/** Everything that's only on this device, as a ZIP Blob: one JSON index and each take's samples. */
export async function makeBackup(at = new Date()) {
    const records = {};
    for (const name of RECORDS) records[name] = (await db.all(name)).filter((r) => !r.deleted);
    const projectIds = new Set(records.projects.map((p) => p.id));
    records.parts = (await db.all('parts')).filter((p) => projectIds.has(p.projectId));
    const learningKeys = await db.keys('learning');
    records.learning = await Promise.all(learningKeys.map(async (key) => [key, await db.get('learning', key)]));
    const prefs = {};
    for (const key of PREFS) {
        const value = local.get(`stackapella.${key}`);
        if (value !== null) prefs[key] = value;
    }
    const files = [];
    const audioKeys = [...records.parts.map((p) => p.id), ...learningKeys.filter(isCourseKey)];
    for (const key of audioKeys) {
        const audio = asInt16(await db.get('audio', key));
        if (audio) files.push({ name: `audio/${encodeURIComponent(key)}.pcm`, bytes: new Uint8Array(audio.buffer, audio.byteOffset, audio.byteLength) });
    }
    const index = { app: 'stackapella', kind: 'backup', format: FORMAT, madeAt: at.getTime(), records, prefs };
    return zip([{ name: INDEX, bytes: new TextEncoder().encode(JSON.stringify(index, replacer)) }, ...files]);
}

/** A backup's contents from its file's bytes; throws `not_backup` or `newer_backup`. */
export async function readBackup(bytes) {
    const entries = unzip(bytes);
    const indexEntry = entries?.find((e) => e.name === INDEX);
    const raw = indexEntry ? await indexEntry.read() : null;
    let index = null;
    try {
        index = raw ? JSON.parse(new TextDecoder().decode(raw), reviver) : null;
    } catch (error) {
        console.info('The backup file was unreadable', error?.message ?? error);
    }
    if (!index || index.app !== 'stackapella' || index.kind !== 'backup' || typeof index.records !== 'object') throw Object.assign(new Error('Not a Stackapella backup'), { code: 'not_backup' });
    if (index.format > FORMAT) throw Object.assign(new Error('A backup from a newer version'), { code: 'newer_backup' });
    const audio = new Map();
    for (const entry of entries) {
        const match = /^audio\/(.+)\.pcm$/.exec(entry.name);
        if (!match) continue;
        const data = await entry.read();
        if (data && data.byteLength % 2 === 0) audio.set(decodeURIComponent(match[1]), new Int16Array(data.buffer.slice(data.byteOffset, data.byteOffset + data.byteLength)));
    }
    return { index, audio };
}

const newer = (theirs, mine) => !mine || (theirs.updatedAt ?? 0) > (mine.updatedAt ?? 0);

/**
 * Puts a backup's contents on this device, adding to what's here: { songs, lessons } brought back.
 * The caller reloads the app after, so every store reads the restored records.
 */
export async function restoreBackup({ index, audio }) {
    const records = index.records;
    let songs = 0;
    const taken = new Set();
    for (const project of records.projects ?? []) {
        if (!project?.id || !newer(project, await db.get('projects', project.id))) continue;
        await db.put('projects', project);
        taken.add(project.id);
        if (!project.course) songs++;
    }
    for (const part of records.parts ?? []) {
        if (!taken.has(part?.projectId)) continue;
        await db.put('parts', part);
        if (audio.has(part.id)) await db.put('audio', audio.get(part.id), part.id);
    }
    for (const name of ['templates', 'templateDrafts']) {
        for (const record of records[name] ?? []) if (record?.id && newer(record, await db.get(name, record.id))) await db.put(name, record);
    }
    let lessons = 0;
    for (const [key, value] of records.learning ?? []) {
        const mine = await db.get('learning', key);
        if (key === 'progress') {
            lessons = Object.values(value?.lessons ?? {}).filter((l) => l?.stars).length;
            await db.put('learning', mine ? mergeProgress(mine, value) : value, key);
        } else if (mine === undefined) {
            await db.put('learning', value, key);
            if (isCourseKey(key) && audio.has(key)) await db.put('audio', audio.get(key), key);
        }
    }
    for (const [key, value] of Object.entries(index.prefs ?? {})) {
        if (PREFS.includes(key) && typeof value === 'string' && local.get(`stackapella.${key}`) === null) local.set(`stackapella.${key}`, value);
    }
    return { songs, lessons };
}
