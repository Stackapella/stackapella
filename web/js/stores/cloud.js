// Cloud save on this device (js/sync.js, wired to the real account): the state the screens show
// (what's waiting, conflicts, offline), what the projects store calls when something changes, and
// when the sync runs: soon after a change, when back online or back in the app, every minute
// while the app is open, and on signing in (when the device's projects first move in).

import { reactive } from 'vue';
import * as db from '../db.js';
import { api } from '../api.js';
import { mockWanted } from '../api-mock.js';
import { encodeWav } from '../wav.js';
import { createSync } from '../sync.js';
import { account, loadAccount } from './account.js';
import { syncLearning } from './learning.js';
import { forgetMine, loadMine } from './templates.js';

export const cloud = reactive({ pending: 0, conflicts: [], waiting: null, offline: false, lastSynced: null });

const listeners = new Set();

/** Hear what a pull changed on this device: [{ kind, id, projectId?, removed? }]. */
export function onCloudChange(listener) {
    listeners.add(listener);
    return () => listeners.delete(listener);
}

const hex = (buffer) => [...new Uint8Array(buffer)].map((b) => b.toString(16).padStart(2, '0')).join('');

/** A take's FLAC from the account, decoded at its own rate (so frame counts stay exact). */
async function loadTake(url, sampleRate) {
    const response = await fetch(url, { mode: 'cors' });
    if (!response.ok) throw new Error(`The take did not download: ${response.status}`);
    const decoded = await new OfflineAudioContext(1, 1, sampleRate).decodeAudioData(await response.arrayBuffer());
    return decoded.getChannelData(0);
}

const sync = createSync({
    api,
    db,
    // The mock answers with fixed examples, so cloud save stays off there (tests/sync.test.js and the
    // sync flow against the local server check it).
    signedIn: () => !!account.me && !mockWanted(),
    me: () => account.me?.id ?? null,
    uuid: () => crypto.randomUUID(),
    sha256: async (blob) => hex(await crypto.subtle.digest('SHA-256', await blob.arrayBuffer())),
    encode: (samples, sampleRate) => encodeWav([samples], sampleRate),
    upload: (url, init) => fetch(url, init),
    loadTake,
    onState: (state) => Object.assign(cloud, state),
    onChange: (changes) => listeners.forEach((listener) => listener(changes)),
});

let soon = null;
/** Runs the sync a moment after a change (several changes go together). */
export function syncSoon() {
    clearTimeout(soon);
    soon = setTimeout(() => sync.run(), 1500);
}

/** Runs the sync now (Sync now, and after a restore so it comes back down). */
export function syncNow() {
    clearTimeout(soon);
    return sync.run();
}

/** A project or part changed on the device. */
export async function changed(kind, record) {
    await sync.enqueue(kind, record.id, kind === 'part' ? { projectId: record.projectId } : {});
    syncSoon();
}

/**
 * A scored exercise for the history (`scores.create`: numbers only, never audio), kept in the
 * outbox until it goes. Free accounts' are kept too; the history shows with Plus.
 */
export async function keepScore({ lesson, summary }) {
    if (!account.me || mockWanted()) return;
    const id = crypto.randomUUID();
    await sync.enqueue('score', id, { body: { id, lesson, templateId: null, templateVersion: null, partKey: null, recordedAt: new Date().toISOString(), summary } });
    syncSoon();
}

/** A project or part was deleted on the device. */
export async function deleted(kind, record) {
    await sync.enqueueDelete(kind, record);
    syncSoon();
}

export async function undoDeleted(project) {
    await sync.undoDelete(project);
    syncSoon();
}

/** Settles "Changed on another device": 'mine' or 'theirs'. */
export async function settle(conflict, choice) {
    await sync.resolve(conflict, choice);
    syncSoon();
}

let started = false;
/** Starts the sync's rhythm (once, at start). */
export function startSync() {
    if (started) return;
    started = true;
    window.addEventListener('online', () => sync.run());
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState !== 'visible') return;
        sync.run();
        syncLearning().catch((error) => console.warn('Course progress did not sync', error));
    });
    setInterval(() => document.visibilityState === 'visible' && sync.run(), 60_000);
}

/**
 * Signed in: the device's projects move into the account (once per account), then the sync runs.
 * Another account than last time: the last one's synced projects leave the device first.
 */
export async function signedInSync() {
    if (!account.me || mockWanted()) return;
    const owner = await db.get('meta', 'owner');
    if (owner && owner !== account.me.id) {
        await sync.forget();
        await forgetMine();
        listeners.forEach((listener) => listener([{ kind: 'project', id: null, removed: true }]));
    }
    await db.put('meta', account.me.id, 'owner');
    const key = `adopted:${account.me.id}`;
    if (!(await db.get('meta', key))) {
        await sync.adopt();
        await db.put('meta', true, key);
    }
    await Promise.all([sync.run(), syncLearning(), loadMine()]);
}

// ---- Friends' projects (contract/api.md, "Collaboration: friends on a project") -----------------

/** A friend's project keeps its whole template (in its settings); when it came without, it's fetched. */
async function ensureTemplate(id) {
    const project = await db.get('projects', id);
    const ref = project?.template;
    if (!ref || Array.isArray(ref.parts)) return;
    const { loadTemplate } = await import('../templates.js');
    let template = await loadTemplate(ref.id).catch(() => null);
    if (!template || template.version !== ref.version) template = (await api.templates.show(ref.id, { version: ref.version })).data.template;
    await db.put('projects', { ...(await db.get('projects', id)), template });
}

async function markRemoved(project, reason) {
    await db.put('projects', { ...project, shared: { ...project.shared, removed: reason } });
    listeners.forEach((listener) => listener([{ kind: 'project', id: project.id }]));
}

/**
 * The projects friends share with this person (projects.shared), kept on the device. One no longer
 * shared (they left, were removed, or the owner binned it) stays here to listen to, marked.
 */
export async function loadShared() {
    if (!account.me || mockWanted()) return;
    const items = [];
    let cursor = null;
    do {
        const page = await api.projects.shared({ cursor: cursor ?? undefined });
        items.push(...page.data);
        cursor = page.meta?.nextCursor ?? null;
    } while (cursor);
    for (const item of items) {
        await sync.keepShared(item, { role: item.role, owner: item.owner });
        await ensureTemplate(item.project.id).catch((error) => console.warn('A shared project\'s song did not load', error));
    }
    const listed = new Set(items.map((item) => item.project.id));
    for (const project of await db.all('projects')) {
        if (project.shared && !project.shared.removed && !listed.has(project.id)) await markRemoved(project, 'gone');
    }
}

/** Brings a friend's project up to date (when it's opened, and when the app comes back). */
export async function refreshShared(id) {
    await loadAccount(); // opened straight from a link, the account may not be known yet
    const project = await db.get('projects', id);
    if (!project?.shared || project.shared.removed || !account.me || mockWanted()) return;
    try {
        const { data } = await api.projects.show(id);
        await sync.keepShared(data, project.shared);
        await ensureTemplate(id);
    } catch (error) {
        if (error?.code === 'removed_from_project') await markRemoved(project, 'removed');
        else if (error?.code === 'not_found' || error?.code === 'binned') await markRemoved(project, 'gone');
        else if (error?.code !== 'offline') console.warn('The shared project did not refresh', error);
    }
}

/** Joined a friend's project by an invite: it comes to this device (`shown` is invites.accept's answer). */
export async function joinedProject(shown, owner) {
    const local = await db.get('projects', shown.project.id);
    if (local && !local.shared) return; // your own project's link: it's yours already
    await sync.keepShared(shown, { role: 'singer', owner });
    await ensureTemplate(shown.project.id);
}

/** Left a friend's project: it stays on this device to listen to, no longer synced. */
export async function leftProject(id) {
    const project = await db.get('projects', id);
    if (project?.shared) await markRemoved(project, 'left');
}
