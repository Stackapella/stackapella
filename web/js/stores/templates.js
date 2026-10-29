// The person's own templates and the creator's drafts (docs/template-creator-plan.md, "Storage";
// contract/api.md, "Your own templates"). Kept on the device first (IndexedDB version 5), so making
// one never needs an account; signed in, each save goes to the account as a new version
// (`templates.save`, private unless chosen), and the account's come down to a new device. Past the
// free limit a template stays on the device, marked, as projects do. Projects keep their own copy
// of the template they started from, so a new version never changes them.

import { reactive } from 'vue';
import * as db from '../db.js';
import { api, ApiError } from '../api.js';
import { mockWanted } from '../api-mock.js';
import { repairTemplate, validateTemplate } from '../templates.js';
import { account } from './account.js';

/**
 * `list`: the person's templates, newest first, each `{ id, template, visibility, libraryOptIn,
 * savedVersion, state }`; `state` is `saved`, `device` (signed out, or not sent yet), `limit`
 * (past the free plan's one), `readOnly` (after a downgrade), `unverified` (the email isn't
 * confirmed) or `offline`. `drafts`: unfinished ones, newest first.
 */
export const mine = reactive({ list: [], drafts: [], loaded: false, syncing: false });

const signedIn = () => !!account.me && !mockWanted();
const byNewest = (a, b) => (b.updatedAt ?? 0) - (a.updatedAt ?? 0);
// What a refusal leaves the template as, on the device.
const STATES = { template_limit: 'limit', template_read_only: 'readOnly', email_unverified: 'unverified' };
/** What a refusal of the template itself (422) said: every message (each names the part and note), shown, never resent as it is. */
const problemsIn = (error) => Object.values(error.errors ?? {}).flat().filter((m) => typeof m === 'string');

let loading = null;

/**
 * Loads the device's templates and drafts, then (signed in) brings them level with the account.
 * The device's read is kept; the account is asked again whenever this is called signed in and no
 * sync is running (the home calls it before the account has loaded; signing in calls it again).
 */
export function loadMine() {
    loading ??= readDevice().then(() => {
        mine.loaded = true;
    });
    return loading.then(async () => {
        if (signedIn() && !syncing) {
            syncing = syncMine()
                .catch((error) => console.warn('Templates not synced', error))
                .finally(() => (syncing = null));
        }
        await syncing;
        return mine;
    });
}

let syncing = null;

/**
 * Another account signed in on this device: the templates the last one had on its account go
 * (they're on its account still). Ones never sent stay, as drafts do: they were made here.
 */
export async function forgetMine() {
    for (const record of await db.all('templates')) {
        if (record.savedVersion > 0) await db.remove('templates', record.id);
    }
    await readDevice();
}

const DELETE_AFTER_MS = 12000; // the account hears of a delete after its Undo (10 s) has gone

async function readDevice() {
    mine.list = (await db.all('templates')).filter((r) => !r.deleted).sort(byNewest);
    mine.drafts = (await db.all('templateDrafts')).sort(byNewest);
}

/** A template of the person's own, from the device (null when it isn't theirs here). */
export async function ownTemplate(id) {
    const record = await db.get('templates', id);
    return record && !record.deleted ? record.template : null;
}

/**
 * Saves a template: on the device at once, then to the account. A change to one already on the
 * account is a new version. Returns the record.
 */
export async function saveTemplate(template, { visibility = null, libraryOptIn = null } = {}) {
    validateTemplate(template);
    const before = await db.get('templates', template.id);
    const next = JSON.parse(JSON.stringify(template)); // plain: the screens pass Vue's reactive copies
    if (before) {
        const same = JSON.stringify(before.template) === JSON.stringify(next);
        if (same && visibility === null && libraryOptIn === null) return before;
        // A version the account has is never changed: the change is the next one. One not sent yet is
        // simply replaced.
        if (!same) next.version = (before.savedVersion ?? 0) >= before.template.version ? before.template.version + 1 : before.template.version;
    }
    const record = {
        id: next.id,
        template: next,
        visibility: visibility ?? before?.visibility ?? 'private',
        libraryOptIn: libraryOptIn ?? before?.libraryOptIn ?? false,
        savedVersion: before?.savedVersion ?? 0,
        state: before?.state ?? 'device',
        pending: true, // something to send: a version, or who may open it
        deleted: false,
        createdAt: before?.createdAt ?? Date.now(),
        updatedAt: Date.now(),
    };
    await db.put('templates', record);
    await readDevice();
    if (signedIn()) await send(record);
    return (await db.get('templates', record.id)) ?? record;
}

/** Sends one template's latest version (and who may open it) to the account. */
async function send(record, { retried = false } = {}) {
    // Put right first (a note without a pitch, chords past the end), and kept that way.
    const repaired = repairTemplate(record.template);
    if (repaired.changed) {
        record = { ...record, template: repaired.template };
        await db.put('templates', record);
    }
    try {
        const changed = record.template.version > (record.savedVersion ?? 0);
        const { data } = changed
            ? await api.templates.save(record.id, { changeId: crypto.randomUUID(), template: record.template, visibility: record.visibility, libraryOptIn: record.libraryOptIn })
            : await api.templates.update(record.id, { visibility: record.visibility, libraryOptIn: record.libraryOptIn });
        // Kept onto the record as it is now: a save made while this was on its way stays, and is
        // still to send when it's newer than what went.
        const now = (await db.get('templates', record.id)) ?? record;
        const newer = JSON.stringify(now.template) !== JSON.stringify(record.template) || now.visibility !== record.visibility || now.libraryOptIn !== record.libraryOptIn;
        return keep({ ...now, savedVersion: data.version, state: data.readOnly ? 'readOnly' : 'saved', pending: newer, ...(newer ? {} : { visibility: data.visibility, libraryOptIn: data.libraryOptIn }) });
    } catch (error) {
        if (!(error instanceof ApiError)) throw error;
        const now = (await db.get('templates', record.id)) ?? record;
        if (error.code === 'version_exists' && !retried) {
            // Another device saved this version number first: this change is the next one.
            const next = { ...now, template: { ...now.template, version: Math.max(now.template.version, now.savedVersion ?? 0) + 1 } };
            await db.put('templates', next);
            return send(next, { retried: true });
        }
        if (error.code === 'validation_failed') {
            // The account refused this version: said once, with its reason, and not sent again
            // until the template changes (a 422 used to be resent on every sync).
            console.warn('The account refused the template', error.errors);
            return keep({ ...now, state: 'invalid', invalid: { reasons: problemsIn(error), for: JSON.stringify(now.template) } });
        }
        if (!STATES[error.code] && error.status !== 0 && error.code !== 'offline') console.warn('The template was not saved to the account', error);
        return keep({ ...now, state: STATES[error.code] ?? (error.status === 0 || error.code === 'offline' ? 'offline' : now.state === 'saved' ? 'offline' : 'device') });
    }
}

/** Refused by the account (422) and not changed since: waits for an edit instead of going again. */
function refusedAsItIs(record) {
    return record.state === 'invalid' && record.invalid?.for === JSON.stringify(repairTemplate(record.template).template);
}

async function keep(record) {
    await db.put('templates', record);
    await readDevice();
    return record;
}

/** Who may open it: `private` or `unlisted` (anyone with the link). */
export async function setVisibility(id, visibility) {
    const record = await db.get('templates', id);
    if (!record) return null;
    record.visibility = visibility;
    record.pending = true;
    record.updatedAt = Date.now();
    await db.put('templates', record);
    await readDevice();
    if (signedIn()) await send(record);
    return db.get('templates', id);
}

/** Deletes a template here and on the account (it goes from groups it was shared with; projects keep their copy). */
/**
 * Deletes a template of your own; returns an Undo (null when there was nothing to delete). On the
 * account, it's marked deleted here at once and the account hears after the Undo's time (or at the
 * next sync, signed out or offline: else that sync would bring it back). Undone after the account
 * heard, it's saved there again.
 */
export async function deleteTemplate(id) {
    const record = await db.get('templates', id);
    if (!record) return null;
    if (record.savedVersion > 0) await db.put('templates', { ...record, deleted: true, updatedAt: Date.now() });
    else await db.remove('templates', id);
    await readDevice();
    const later = record.savedVersion > 0 ? setTimeout(() => sendDelete(id), DELETE_AFTER_MS) : null;
    return async () => {
        clearTimeout(later);
        const now = await db.get('templates', id);
        if (now && !now.deleted) return;
        await db.put('templates', record);
        await readDevice();
        if (!now && record.savedVersion > 0 && signedIn()) {
            await api.templates.save(id, record.template).catch((error) => console.warn('The template will be saved again at the next sync', error));
        }
    };
}

/** A template marked deleted here: the account told (it stays marked if that fails, for the next sync). */
async function sendDelete(id) {
    const record = await db.get('templates', id);
    if (!record?.deleted || !signedIn()) return;
    try {
        await api.templates.delete(id);
        await db.remove('templates', id);
    } catch (error) {
        console.warn('The template will be deleted from the account later', error);
    }
}

/**
 * Brings the device level with the account: deletions and unsent versions go up; templates made
 * on another device, and newer versions, come down (`templates.show` has the JSON).
 */
async function syncMine() {
    mine.syncing = true;
    try {
        const local = await db.all('templates');
        for (const record of local.filter((r) => r.deleted)) {
            await api.templates.delete(record.id);
            await db.remove('templates', record.id);
        }
        const server = [];
        let cursor;
        do {
            const page = await api.templates.mine({ cursor });
            server.push(...page.data);
            cursor = page.meta?.nextCursor ?? null;
        } while (cursor);
        const here = new Map(local.filter((r) => !r.deleted).map((r) => [r.id, r]));
        for (const item of server) {
            const record = here.get(item.id);
            const unsent = record?.pending;
            if (record && (unsent || item.version <= record.savedVersion)) {
                if (record.state !== 'saved' && !unsent) await db.put('templates', { ...record, state: item.readOnly ? 'readOnly' : 'saved' });
                continue;
            }
            const { data } = await api.templates.show(item.id);
            await db.put('templates', {
                id: item.id,
                template: validateTemplate(data.template),
                visibility: item.visibility,
                libraryOptIn: item.libraryOptIn,
                savedVersion: item.version,
                state: item.readOnly ? 'readOnly' : 'saved',
                pending: false,
                deleted: false,
                createdAt: Date.parse(item.createdAt) || Date.now(),
                updatedAt: Date.parse(item.updatedAt) || Date.now(),
            });
        }
        // Saved before but gone from the account: deleted on another device, unless it was changed
        // here since (then it goes back up, as a template of its own again).
        const onServer = new Set(server.map((s) => s.id));
        for (const record of here.values()) {
            if (record.savedVersion > 0 && !onServer.has(record.id) && !record.pending) await db.remove('templates', record.id);
            else if (record.pending && !refusedAsItIs(record)) await send(record.savedVersion > 0 && !onServer.has(record.id) ? { ...record, savedVersion: 0 } : record);
        }
    } finally {
        mine.syncing = false;
        await readDevice();
    }
}

/** Saves a draft (the creator autosaves); returns its time. */
export async function saveDraft(draft) {
    const record = { ...JSON.parse(JSON.stringify(draft)), updatedAt: Date.now() };
    await db.put('templateDrafts', record);
    const i = mine.drafts.findIndex((d) => d.id === record.id);
    if (i >= 0) mine.drafts.splice(i, 1);
    mine.drafts.unshift(record);
    return record.updatedAt;
}

/** A draft, put right as a template is (a note without a pitch, chords past the end). */
export async function loadDraft(id) {
    const draft = await db.get('templateDrafts', id);
    if (!draft?.template?.parts) return draft;
    const repaired = repairTemplate(draft.template);
    if (!repaired.changed) return draft;
    const fixed = { ...draft, template: repaired.template };
    await db.put('templateDrafts', fixed);
    return fixed;
}

/**
 * A draft discarded. Hands back an undo that puts it back exactly as it was (the same record, its
 * sung lines and its place among the drafts), or null when there was nothing to discard.
 */
export async function discardDraft(id) {
    const kept = await db.get('templateDrafts', id);
    await db.remove('templateDrafts', id);
    mine.drafts = mine.drafts.filter((d) => d.id !== id);
    if (!kept) return null;
    return async () => {
        await db.put('templateDrafts', kept);
        mine.drafts = [...mine.drafts.filter((d) => d.id !== id), kept].sort((a, b) => (b.updatedAt ?? 0) - (a.updatedAt ?? 0));
    };
}
