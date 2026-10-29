// Projects and their parts. The only module that knows where they're stored: IndexedDB, the
// working copy, and through the outbox (stores/cloud.js) the account, while signed in.

import { markRaw, reactive, toRaw } from 'vue';
import * as db from '../db.js';
import { t } from '../i18n.js';
import { local } from '../storage.js';
import { renderGuide } from '../guides.js';
import { peaks } from '../dsp.js';
import { isNewSinger, PRACTICE_DEFAULTS } from '../practice.js';
import { partsFor } from '../templates.js';
import { PART_COLORS, transposeKey } from '../theme.js';
import { changed, deleted, onCloudChange, undoDeleted } from './cloud.js';
import { account } from './account.js';
import { count } from '../usage.js';

export const FREE_PROJECT_LIMIT = 3;

// Another device changed something: the lists show it.
onCloudChange(() => projects.loaded && loadProjects().catch((error) => console.error('The projects did not reload', error)));

// `list` is the person's projects (what the projects page shows and the free limit counts);
// `all` adds the beta singers' recording projects, which are kept apart (docs/beta-singers-plan.md).
export const projects = reactive({ list: [], all: [], loaded: false });

export class LimitReached extends Error {}

/** A project as the list shows it, with its parts' sizes and waveform peaks for the thumbnail. */
async function summarise(project) {
    const parts = (await db.allBy('parts', 'projectId', project.id)).sort((a, b) => a.order - b.order);
    const length = parts.reduce((max, p) => Math.max(max, p.offsetSeconds + Math.max(p.frames / p.sampleRate, p.guideSeconds ?? 0)), 0);
    // A template part with no take yet shows its guide.
    // deviceBytes: the takes as 16-bit, as they're kept here and sent up (Storage shows it until the account counts it).
    return { ...project, partCount: parts.length, recordedCount: parts.filter((p) => p.frames > 0).length, length, recorded: parts.some((p) => p.frames > 0), everyPart: parts.length > 0 && parts.every((p) => p.frames > 0), deviceBytes: parts.reduce((sum, p) => sum + ((p.frames || 0) + (p.keptTakes ?? []).reduce((k, t) => k + (t.frames || 0), 0)) * 2, 0), thumbnail: markRaw(parts.map((p) => ({ peaks: p.peaks ?? p.guidePeaks, color: p.color }))) };
}

export async function loadProjects() {
    const list = await db.all('projects');
    projects.all = (await Promise.all(list.map(summarise))).sort((a, b) => b.updatedAt - a.updatedAt);
    projects.list = projects.all.filter((p) => !p.beta);
    projects.loaded = true;
}

/** The projects the free plan's limit counts: not the course's own songs, nor friends' projects. */
export const countsToLimit = (project) => !project.course && !project.shared;

/** Plus has no limit; free is under 3 (docs/decisions.md). */
export function canCreateProject() {
    return account.me?.plan === 'plus' || projects.list.filter(countsToLimit).length < FREE_PROJECT_LIMIT;
}

/** What the last-changed project used: a new one starts the same (docs/decisions.md). */
const LAST_SETTINGS = 'stackapella.newProject';
const STARTING = { bpm: 90, key: null, timeSignature: '4/4', countInBars: 1, metronome: true };

export function lastSettings() {
    try {
        const kept = JSON.parse(local.get(LAST_SETTINGS) || '{}');
        return { ...STARTING, ...Object.fromEntries(Object.entries(kept).filter(([key]) => key in STARTING)) };
    } catch {
        return { ...STARTING };
    }
}

/** Remembers a project's settings for the next new one (a plain project's, not a template's). */
export function rememberSettings({ bpm, key, timeSignature, countInBars, metronome }) {
    local.set(LAST_SETTINGS, JSON.stringify({ bpm, key, timeSignature, countInBars, metronome }));
}

/** A project's parts, its template, and each part's take (samples, by part id), for a lesson to score. */
export async function takesOf(projectId) {
    const project = await db.get('projects', projectId);
    const parts = await db.allBy('parts', 'projectId', projectId);
    const takes = new Map();
    for (const part of parts) {
        if (part.frames > 0) takes.set(part.id, await db.getAudio(part.id));
    }
    return { project, parts, template: project?.template ?? null, takes };
}

/** Whether this singer is still new (practice.js isNewSinger): offered the melody first. */
export async function newSinger({ projectId = null, templateId = null, me = null } = {}) {
    const [parts, list] = await Promise.all([db.all('parts'), db.all('projects')]);
    return isNewSinger(parts, list, { projectId, templateId, me });
}

/** The project as kept on the device now (the sync may have saved it since a screen read it). */
export async function storedProject(id) {
    return (await db.get('projects', id)) ?? null;
}

/** A project's name as shown: its title, or "Untitled song" while it has none. */
export function shownTitle(project) {
    return project?.title?.trim() || t('project.untitled');
}

export async function createProject(fields) {
    if (!projects.loaded) {
        await loadProjects();
    }
    if (!fields.beta && !fields.course && !canCreateProject()) {
        throw new LimitReached();
    }
    const now = Date.now();
    const project = {
        id: crypto.randomUUID(),
        title: '', // named later, in the studio; lists show a placeholder until then
        ...lastSettings(),
        createdAt: now,
        updatedAt: now,
        ...fields,
    };
    await db.put('projects', project);
    await changed('project', project);
    const summary = await summarise(project);
    projects.all.unshift(summary);
    if (!project.beta) projects.list.unshift(summary);
    if (!project.beta && !project.course) count('song_created'); // a song of their own (a copy or an import too), not a beta or lesson one
    return project;
}

/**
 * A deep copy without Vue's proxies, which IndexedDB can't store: a reactive object spread into a
 * plain one keeps its nested objects as proxies.
 */
function plainCopy(value) {
    const raw = toRaw(value);
    if (Array.isArray(raw)) return raw.map(plainCopy);
    if (raw && typeof raw === 'object' && Object.getPrototypeOf(raw) === Object.prototype) {
        return Object.fromEntries(Object.entries(raw).map(([key, item]) => [key, plainCopy(item)]));
    }
    return raw; // numbers, strings, typed arrays and the like are stored as they are
}

// The sync's own fields: kept as the account last said, whatever an open screen's copy has. The
// version is the one exception: a copy read before another device's change (pulled while it was
// open) saves at the version it read, so the account answers "Changed on another device" instead of
// losing that change; behind only this device's own saves, it takes the latest (js/sync.js).
const PROJECT_SYNC = ['notSaved', 'readOnly', 'ownVersion'];
const PART_SYNC = ['syncedTakeId', 'takeWaiting', 'ownVersion'];
function keepSync(fields, plain, had) {
    const read = plain.version;
    Object.assign(plain, Object.fromEntries(fields.map((f) => [f, had?.[f] ?? plain[f] ?? null])));
    const pulledSince = had && typeof read === 'number' && had.version !== read && had.ownVersion !== had.version;
    plain.version = pulledSince ? read : had?.version ?? read ?? null;
    return plain;
}

export async function saveProject(project) {
    const plain = { ...plainCopy(project), updatedAt: Date.now() };
    keepSync(PROJECT_SYNC, plain, await db.get('projects', plain.id));
    await db.put('projects', plain);
    await changed('project', plain);
    const index = projects.list.findIndex((p) => p.id === plain.id);
    const indexAll = projects.all.findIndex((p) => p.id === plain.id);
    if (index >= 0 || indexAll >= 0) {
        const summary = await summarise(plain);
        if (index >= 0) projects.list[index] = summary;
        if (indexAll >= 0) projects.all[indexAll] = summary;
    }
    return plain;
}

/**
 * Deletes a project with its parts and takes. Returns an undo that puts it all back (the toast's
 * Undo); signed in, the account keeps it in Recently deleted for 30 days too.
 */
export async function deleteProject(id) {
    const project = await db.get('projects', id);
    const parts = await db.allBy('parts', 'projectId', id);
    const audio = new Map();
    for (const part of parts) {
        for (const key of [part.id, ...(part.keptTakes ?? []).map((k) => keptKey(k.takeId))]) {
            audio.set(key, await db.get('audio', key));
            await db.remove('audio', key);
        }
        await db.remove('parts', part.id);
    }
    await db.remove('projects', id);
    projects.list = projects.list.filter((p) => p.id !== id);
    projects.all = projects.all.filter((p) => p.id !== id);
    if (project) await deleted('project', project);
    return async () => {
        if (!project) return;
        await db.put('projects', project);
        for (const part of parts) await db.put('parts', part);
        for (const [key, stored] of audio) if (stored) await db.put('audio', stored, key);
        await undoDeleted(project);
        await loadProjects();
    };
}

const GUIDE_FRAMES_PER_PEAK = 512;
let persisting = null;

/**
 * Makes a project from a harmony template: one slot per part, each with its guide's length and
 * waveform outline (for the projects page). Counts toward the free limit like any project, except
 * a beta singer's recording project (`beta`), which is kept apart.
 */
export async function startFromTemplate(template, { transpose = 0, focusKey = null, singers = null, beta = null, course = null, keyChosen = false } = {}) {
    const project = await createProject({
        title: template.title,
        bpm: template.tempo,
        key: transposeKey(template.key, transpose),
        timeSignature: template.timeSignature,
        countInBars: 1,
        metronome: false,
        template: structuredClone(toRaw(template)),
        transpose,
        singers: partsFor(template, singers ?? undefined).filter((p) => p.kind !== 'instrument').length,
        // A new project hears its real voices without being told they're new.
        practice: { ...PRACTICE_DEFAULTS, seedVoices: true, ...(keyChosen ? { sing: { keyChosen: true } } : {}) },
        ...(beta ? { beta } : {}),
        ...(course ? { course } : {}), // a song a lesson opened: listed, but not counted toward the limit
    });
    for (const part of partsFor(template, singers ?? undefined)) {
        const slot = await makeSlot(project, template, part, transpose);
        if (part.key === focusKey) project.practice.focus = slot.id;
    }
    return saveProject(project);
}

/**
 * Harmonise my song (docs/harmonise-my-song-plan.md): an own song takes the template made from one
 * of its takes. That take's part becomes the tune's (its take and offsets untouched), and each other
 * part of the template is added as a guide part to learn and record over, after the song's own parts.
 */
export async function adoptTemplate(projectId, template, melodySlotId) {
    const project = await db.get('projects', projectId);
    if (!project) throw new Error('not_found');
    const plain = structuredClone(toRaw(template));
    const parts = await db.allBy('parts', 'projectId', projectId);
    const melodyKey = (plain.parts.find((p) => p.melody) ?? plain.parts[0]).key;
    const tune = parts.find((p) => p.id === melodySlotId);
    if (tune) await savePart({ ...tune, templatePartKey: melodyKey });
    let order = Math.max(-1, ...parts.map((p) => p.order ?? 0));
    for (const part of plain.parts) {
        if (part.key !== melodyKey) await makeSlot(project, plain, part, 0, { order: ++order });
    }
    return saveProject({ ...project, template: plain, transpose: 0, key: plain.key, bpm: plain.tempo, timeSignature: plain.timeSignature, singers: Math.max(project.singers ?? 0, partsFor(plain).filter((p) => p.kind !== 'instrument').length) });
}

/** A slot for `part` of a template project, with its guide's length and outline. */
export async function makeSlot(project, template, part, transpose = 0, extra = {}) {
    const sampleRate = 48000;
    const order = template.parts.indexOf(part);
    const guide = await renderGuide({ templateId: template.id, templateVersion: template.version, part, tempo: template.tempo, transpose, sampleRate });
    const id = crypto.randomUUID();
    const slot = {
        id,
        projectId: project.id,
        name: part.name,
        color: PART_COLORS[order % PART_COLORS.length],
        order,
        volume: 1,
        pan: 0,
        muted: false,
        solo: false,
        offsetSeconds: 0,
        nudgeSeconds: 0,
        sampleRate,
        frames: 0,
        framesPerPeak: GUIDE_FRAMES_PER_PEAK,
        peaks: null,
        templatePartKey: part.key,
        ...(part.kind === 'instrument' ? { kind: 'instrument' } : {}),
        useGuide: false,
        guideSeconds: guide.length / sampleRate,
        guidePeaks: peaks(guide, GUIDE_FRAMES_PER_PEAK),
        createdAt: Date.now(),
        ...extra,
    };
    await savePart(slot);
    return slot;
}

/** A project with its parts (in order) and each part's samples. */
export async function openProject(id) {
    const project = await db.get('projects', id);
    if (!project) {
        return null;
    }
    const parts = (await db.allBy('parts', 'projectId', id)).sort((a, b) => a.order - b.order);
    const samples = new Map();
    for (const part of parts) {
        const data = await db.getAudio(part.id);
        if (data) {
            samples.set(part.id, data);
        }
    }
    return { project, parts, samples };
}

/** Saves a part's settings, and its audio when `samples` is given. */
export async function savePart(part, samples = null) {
    // Recording over a take keeps it (Hillel: "keep all takes so users can swap to try"): its audio
    // under its own key, its details in the part's keptTakes, newest first.
    const before = samples ? await db.get('parts', part.id) : null;
    let replaced = null;
    if (before?.frames > 0) {
        const old = await db.getAudio(part.id);
        if (old) {
            replaced = keptFrom(before);
            await db.putAudio(keptKey(replaced.takeId), old);
        }
    }
    if (samples) {
        await db.putAudio(part.id, samples);
        // Asked on the first recording, not before: Firefox asks the person (operations-plan.md).
        persisting ??= db.persist();
    }
    const plain = plainCopy(part); // deep: a nested object set from a screen (a score) can be a proxy
    const had = await db.get('parts', plain.id);
    // A part made in a friend's project is the singer's own (the owner's are the project's).
    if (!had && !plain.createdBy && account.me && (await db.get('projects', plain.projectId))?.shared) plain.createdBy = { id: account.me.id, name: account.me.name };
    keepSync(PART_SYNC, plain, had);
    // A new recording is a new take (the account's takes are immutable); otherwise it's the same one.
    plain.takeId = samples ? crypto.randomUUID() : plain.frames > 0 ? had?.takeId ?? plain.takeId ?? null : null;
    // The kept takes are the device's record: a screen's copy of the part never replaces them.
    plain.keptTakes = replaced ? [replaced, ...(had?.keptTakes ?? [])] : had?.keptTakes ?? plain.keptTakes ?? [];
    await db.put('parts', plain);
    await changed('part', plain);
}

/** Where a kept take's audio is (the audio store, beside the parts' current takes). */
export const keptKey = (takeId) => `take:${takeId}`;

/** A part's current take as a kept one: its id, whether the account has it, and how to play and show it. */
function keptFrom(part) {
    return {
        takeId: part.takeId ?? crypto.randomUUID(),
        synced: !!part.takeId && part.takeId === part.syncedTakeId,
        recordedAt: part.recordedAt ?? part.updatedAt ?? null,
        frames: part.frames,
        sampleRate: part.sampleRate,
        framesPerPeak: part.framesPerPeak ?? null,
        peaks: part.peaks ?? null,
        offsetSeconds: part.offsetSeconds ?? 0,
        score: part.score ?? null,
    };
}

/**
 * A kept take back as the part's current one; the current one is kept in its place. Nudge and trim
 * start again (a trim made for one take rarely fits another). A take the account already has stays
 * its own (the sync switches the part to it); one it lacks goes up as a new take.
 */
export async function useKeptTake(partId, takeId) {
    const had = await db.get('parts', partId);
    const entry = had?.keptTakes?.find((k) => k.takeId === takeId);
    const samples = entry ? await db.getAudio(keptKey(takeId)) : null;
    if (!samples) return null;
    const current = had.frames > 0 ? await db.getAudio(partId) : null;
    const rest = had.keptTakes.filter((k) => k.takeId !== takeId);
    const keptTakes = current ? [keptFrom(had), ...rest] : rest;
    if (current) await db.putAudio(keptKey(keptTakes[0].takeId), current);
    await db.putAudio(partId, samples);
    await db.remove('audio', keptKey(takeId));
    const plain = {
        ...had,
        frames: entry.frames,
        sampleRate: entry.sampleRate,
        framesPerPeak: entry.framesPerPeak ?? had.framesPerPeak,
        peaks: entry.peaks,
        offsetSeconds: entry.offsetSeconds,
        recordedAt: entry.recordedAt,
        score: entry.score,
        nudgeSeconds: 0,
        trimStartFrames: 0,
        trimEndFrames: 0,
        takeId,
        syncedTakeId: entry.synced ? takeId : had.syncedTakeId ?? null,
        keptTakes,
    };
    await db.put('parts', plain);
    await changed('part', plain);
    return { part: plain, samples };
}

/** A kept take gone from the device (never the current one). Returns what it was, or null. */
export async function deleteKeptTake(partId, takeId) {
    const had = await db.get('parts', partId);
    const entry = had?.keptTakes?.find((k) => k.takeId === takeId);
    if (!entry) return null;
    await db.remove('audio', keptKey(takeId));
    await db.put('parts', { ...had, keptTakes: had.keptTakes.filter((k) => k.takeId !== takeId) });
    return entry;
}

/** Removes a part's recording but keeps the part (a template slot goes back to its guide). */
export async function clearTake(part) {
    await db.remove('audio', part.id);
    await savePart({ ...toRaw(part), frames: 0, peaks: null });
}

export async function deletePart(id) {
    const part = await db.get('parts', id);
    await db.remove('audio', id);
    await db.remove('parts', id);
    if (part) await deleted('part', part);
}
