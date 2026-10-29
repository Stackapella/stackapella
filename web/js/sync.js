// Cloud save (docs/backend-plan.md C5; contract/api.md "Projects and cloud save"). The device stays
// the working copy: every change goes into the outbox, and while signed in it's sent to the account
// in order (a project before its parts, a part before its takes, a take before the part that points
// at it); `sync.pull` brings back what other devices changed. A stale save is a conflict the person
// settles ("Changed on another device: keep mine or use theirs"). Beta singers' recording projects
// and the course's own lesson songs never sync (decided: they'd count toward the plan's projects).
//
// The device keeps each part's current take inline (its samples by part id, as before M4); the
// account keeps takes as their own immutable records. A new recording gets a take id (`takeId`)
// here, and `syncedTakeId` says which one the account has.
//
// `ownVersion` is the version this device's own save (or choice) gave a record. A screen still holding
// an older copy saves on top of that freely; but over a version that came from another device it
// saves at the version it read, so the account asks (js/stores/projects.js, keepSync).
//
// createSync() takes its dependencies, so tests/sync.test.js runs it against a fake account.

export const ORDER = { score: 0, project: 0, restoreProject: 0, part: 1, deletePart: 2, deleteProject: 3 };
const PROJECT_FIELDS = ['id', 'title', 'bpm', 'key', 'timeSignature', 'createdAt', 'updatedAt', 'version', 'ownVersion', 'binnedAt', 'readOnly', 'notSaved', 'bytes', 'shared'];
const PART_TAKE_FIELDS = ['offsetSeconds', 'sampleRate', 'frames', 'peaks', 'framesPerPeak'];
const PART_OWN_FIELDS = ['id', 'projectId', 'name', 'color', 'order', 'volume', 'pan', 'muted', 'solo', 'templatePartKey', 'createdAt', 'updatedAt', 'version', 'ownVersion', 'takeId', 'syncedTakeId', 'nudgeSeconds', 'takeWaiting', 'scoring', 'createdBy'];
// What the person sees and sets: a conflict on anything else (the practice state in `settings`) is
// merged without asking (contract/api.md, "Projects and cloud save").
const PROJECT_CORE = ['title', 'bpm', 'key', 'timeSignature'];
const PART_CORE = ['name', 'color', 'order', 'volume', 'pan', 'muted', 'solo', 'nudgeFrames', 'trimStartFrames', 'trimEndFrames', 'currentTakeId'];
// Worked out again on the device (the guide's outline), never sent.
const PART_DERIVED = ['guidePeaks', 'guidePeaksRate', 'guideSeconds'];

const SCORING_WAIT_MS = 120_000;
const iso = (ms) => new Date(ms ?? Date.now()).toISOString();
const ms = (text) => (text ? Date.parse(text) : Date.now());
const omit = (record, fields) => Object.fromEntries(Object.entries(record).filter(([key]) => !fields.includes(key)));

/**
 * A project never sent to the account: a beta singer's recording, a lesson's own song, or a
 * friend's project this person was removed from (kept here to listen to).
 */
export const staysOnDevice = (project) => !!(project?.beta || project?.course || project?.shared?.removed);

/**
 * In a friend's project (contract/api.md, "Collaboration"), a singer changes only the parts they
 * made: the project and everyone else's parts are the owner's and theirs.
 */
export const mayChangePart = (project, part, me) => !project?.shared || (!!me && part?.createdBy?.id === me);

/** What projects.save sends for a project on the device. */
export function projectBody(project, changeId) {
    return {
        changeId,
        version: project.version ?? 0,
        title: project.title ?? '',
        bpm: project.bpm ?? 90,
        key: project.key ?? null,
        timeSignature: project.timeSignature ?? '4/4',
        template: project.template ? { id: project.template.id, version: project.template.version } : null,
        settings: omit(project, PROJECT_FIELDS),
        createdAt: iso(project.createdAt),
    };
}

/** What parts.save sends for a part on the device, pointing at `currentTakeId`. */
export function partBody(part, changeId, currentTakeId) {
    return {
        changeId,
        projectId: part.projectId,
        version: part.version ?? 0,
        order: part.order ?? 0,
        name: (part.name?.trim() || String((part.order ?? 0) + 1)).slice(0, 60), // never blank on the account
        color: (part.color ?? '#4ccfb0').toLowerCase(),
        volume: part.volume ?? 1,
        pan: part.pan ?? 0,
        muted: !!part.muted,
        solo: !!part.solo,
        nudgeFrames: part.sampleRate ? Math.round((part.nudgeSeconds ?? 0) * part.sampleRate) : 0,
        trimStartFrames: part.trimStartFrames ?? 0,
        trimEndFrames: part.trimEndFrames ?? 0,
        templatePartKey: part.templatePartKey ?? null,
        ...(part.kind ? { kind: part.kind } : {}), // an instrument's credits say played by (left out, the stored one stays)
        currentTakeId,
        settings: omit(part, [...PART_OWN_FIELDS, ...PART_TAKE_FIELDS, ...PART_DERIVED, 'trimStartFrames', 'trimEndFrames']),
        createdAt: iso(part.createdAt),
    };
}

/** The account's project as the device keeps it (its settings spread back in). */
export function projectFromServer(server, local = null) {
    return {
        ...(local ?? {}),
        ...(server.settings ?? {}),
        id: server.id,
        title: server.title ?? '',
        bpm: server.bpm,
        key: server.key,
        timeSignature: server.timeSignature,
        createdAt: ms(server.createdAt),
        updatedAt: ms(server.updatedAt),
        version: server.version,
        ownVersion: null,
        readOnly: server.readOnly ?? null,
        notSaved: null,
        bytes: server.bytes ?? local?.bytes ?? null,
    };
}

/** A recording made here that the account doesn't have yet: never replaced by a pull. */
const unsent = (part) => part.frames > 0 && !!part.takeId && part.takeId !== part.syncedTakeId;

/** The account's part (and its current take, when there is one) as the device keeps it. */
export function partFromServer(server, take, local = null) {
    const sampleRate = take?.sampleRate ?? local?.sampleRate ?? null;
    return {
        ...(local ? omit(local, PART_TAKE_FIELDS) : {}),
        ...(server.settings ?? {}),
        id: server.id,
        projectId: server.projectId,
        name: server.name,
        color: server.color,
        order: server.order,
        volume: server.volume,
        pan: server.pan,
        muted: server.muted,
        solo: server.solo,
        templatePartKey: server.templatePartKey ?? null,
        createdBy: server.createdBy ?? null, // a friend's part: who sang it
        trimStartFrames: server.trimStartFrames ?? 0,
        trimEndFrames: server.trimEndFrames ?? 0,
        createdAt: ms(server.createdAt),
        version: server.version,
        ownVersion: null,
        takeId: server.currentTakeId ?? null,
        syncedTakeId: server.currentTakeId ?? null,
        sampleRate,
        frames: take ? take.frames : 0,
        offsetSeconds: take ? take.offsetFrames / take.sampleRate : 0,
        nudgeSeconds: take && sampleRate ? server.nudgeFrames / sampleRate : 0,
        peaks: take?.peaks ?? null,
        framesPerPeak: take?.framesPerPeak ?? 512,
    };
}

/**
 * The sync, over its dependencies:
 * - `api`: the contract's functions (api.js's `api`);
 * - `db`: db.js's get, put, remove, all, allBy, getAudio, putAudio;
 * - `upload(url, { method, headers, body })`: the presigned PUT (fetch);
 * - `loadTake(url, sampleRate)`: a take's FLAC, decoded at its own rate, as a Float32Array;
 * - `signedIn()`: whether to send at all; `uuid()`; `sha256(blob)`; `encode(samples, rate)`: a WAV Blob.
 * `onChange(kind, ids)` hears what a pull changed; `onState(state)` the state for the screen.
 */
export function createSync(deps) {
    const { api, db } = deps;
    let running = null;
    let again = false;

    const state = { pending: 0, conflicts: [], waiting: null, offline: false, lastSynced: null };
    let restUntil = 0;
    const report = () => deps.onState?.({ ...state });

    async function conflicts() {
        return (await db.get('meta', 'conflicts')) ?? [];
    }
    async function setConflicts(list) {
        state.conflicts = list;
        await db.put('meta', list, 'conflicts');
        report();
    }

    /** A record changed on the device: it goes to the account (with a fresh changeId). */
    async function enqueue(kind, targetId, extra = {}) {
        // Saved again before its delete went (an Undo): the delete never goes.
        if (kind === 'part' || kind === 'project') await db.remove('outbox', `${kind === 'part' ? 'deletePart' : 'deleteProject'}:${targetId}`);
        await db.put('outbox', { key: `${kind}:${targetId}`, kind, targetId, changeId: deps.uuid(), at: Date.now(), ...extra });
    }

    /** A project or part was deleted on the device: its saves go, and the account is told if it knows it. */
    async function enqueueDelete(kind, record) {
        const entries = await db.all('outbox');
        for (const e of entries) {
            if (e.targetId === record.id || (kind === 'project' && e.projectId === record.id)) await db.remove('outbox', e.key);
        }
        if (record.version > 0) await enqueue(kind === 'project' ? 'deleteProject' : 'deletePart', record.id, { version: record.version });
    }

    /** An Undo of a project's delete: never sent, the delete just goes; sent, it's restored from the bin. */
    async function undoDelete(project) {
        const sent = !(await db.get('outbox', `deleteProject:${project.id}`));
        await db.remove('outbox', `deleteProject:${project.id}`);
        if (sent && project.version > 0) await enqueue('restoreProject', project.id);
        await enqueue('project', project.id);
        for (const part of await db.allBy('parts', 'projectId', project.id)) await enqueue('part', part.id, { projectId: project.id });
    }

    /**
     * Another account signed in on this device: the last account's synced projects leave the device
     * (they're safe in that account), with the sync's own state; projects never saved stay, to move
     * into this account as on a first sign-in.
     */
    async function forget() {
        for (const project of await db.all('projects')) {
            if (!(project.version > 0)) continue;
            for (const part of await db.allBy('parts', 'projectId', project.id)) {
                await db.remove('audio', part.id);
                await db.remove('parts', part.id);
            }
            await db.remove('projects', project.id);
        }
        for (const entry of await db.all('outbox')) await db.remove('outbox', entry.key);
        await db.remove('meta', 'cursor');
        await db.remove('meta', 'recheck');
        await setConflicts([]);
    }

    /** Every project on the device goes to the account (the first sign-in, or a project that wasn't saved). */
    async function adopt() {
        for (const project of await db.all('projects')) {
            if (staysOnDevice(project)) continue;
            if (!project.shared) await enqueue('project', project.id);
            for (const part of await db.allBy('parts', 'projectId', project.id)) {
                if (mayChangePart(project, part, deps.me?.())) await enqueue('part', part.id, { projectId: project.id });
            }
        }
    }

    async function finished(entry) {
        const now = await db.get('outbox', entry.key);
        if (now?.changeId === entry.changeId) await db.remove('outbox', entry.key);
    }

    async function sendProject(entry) {
        const project = await db.get('projects', entry.targetId);
        if (!project || staysOnDevice(project) || project.shared) return; // a friend's project is theirs to change
        const { data } = await api.projects.save(project.id, projectBody(project, entry.changeId));
        const fresh = await db.get('projects', project.id);
        if (!fresh) {
            // Deleted here while it was on its way: the account is told, at the version it now has.
            await enqueue('deleteProject', project.id, { version: data.version });
            return;
        }
        await db.put('projects', { ...fresh, version: data.version, ownVersion: data.version, notSaved: null });
        // Saved at last (past the plan's limit before): its parts follow.
        if (project.notSaved) {
            for (const part of await db.allBy('parts', 'projectId', project.id)) await enqueue('part', part.id, { projectId: project.id });
            again = true;
        }
    }

    /** A take, as the contract has it: create, upload the 16-bit WAV, complete. Returns its status. */
    async function sendTake(part) {
        const samples = await db.getAudio(part.id);
        if (!samples) return null;
        const wav = deps.encode(samples, part.sampleRate);
        const body = {
            id: part.takeId,
            partId: part.id,
            sampleRate: part.sampleRate,
            frames: samples.length,
            offsetFrames: Math.round((part.offsetSeconds ?? 0) * part.sampleRate),
            bytes: wav.size,
            sha256: await deps.sha256(wav),
            // How it went, numbers only, kept with the take for the history (once it's worked out).
            ...(part.score && part.score.takeId === part.takeId ? { scores: omit(part.score, ['takeId']) } : {}),
        };
        const { data: take, upload } = await api.takes.create(body);
        if (take.status === 'uploading' && upload) {
            const response = await deps.upload(upload.url, { method: upload.method ?? 'PUT', headers: upload.headers ?? {}, body: wav });
            if (!response.ok) throw Object.assign(new Error(`The upload failed: ${response.status}`), { code: 'upload_failed', retry: true });
        }
        const { data: done } = take.status === 'uploading' ? await api.takes.complete(take.id) : { data: take };
        return done.status;
    }

    async function sendPart(entry) {
        let part = await db.get('parts', entry.targetId);
        if (!part) return;
        const project = await db.get('projects', part.projectId);
        if (!project || staysOnDevice(project) || project.notSaved) return;
        if (!mayChangePart(project, part, deps.me?.())) return; // someone else's part in a friend's project: changed here only
        if (part.frames > 0 && !part.takeId) {
            part = { ...part, takeId: deps.uuid() }; // a take recorded before cloud save: it gets its id now
            await db.put('parts', part);
        }
        // A new take whose How it went is still being worked out waits for it (two minutes at most),
        // so its report goes up with it: takes are immutable once sent.
        if (part.frames > 0 && part.takeId !== part.syncedTakeId && part.scoring && Date.now() - part.scoring < SCORING_WAIT_MS) {
            throw Object.assign(new Error('The take is being scored'), { code: 'scoring' });
        }
        if (part.frames > 0 && part.takeId !== part.syncedTakeId) {
            if (!part.version) {
                // The account needs the part before its take: made pointing at what it already has,
                // under a change id kept with the entry, so a retry after a lost answer is a replay.
                if (!entry.createChangeId) await db.put('outbox', Object.assign(entry, { createChangeId: deps.uuid() }));
                const { data } = await api.parts.save(part.id, partBody(part, entry.createChangeId, part.syncedTakeId ?? null));
                part = { ...part, version: data.version };
                const now = await db.get('parts', part.id);
                if (!now) {
                    await enqueue('deletePart', part.id, { version: data.version });
                    return;
                }
                await db.put('parts', { ...now, version: data.version, ownVersion: data.version });
            }
            const takeId = part.takeId;
            const status = await sendTake(part);
            // Another complete for this take is still checking it: try again on the next run.
            if (status === 'uploading') throw Object.assign(new Error('The take is still being checked'), { code: 'upload_pending', retry: true });
            if (status === 'failed') {
                // A failed take is final: it goes again under a new id on the next run.
                await db.put('parts', { ...((await db.get('parts', part.id)) ?? part), takeId: deps.uuid() });
                throw Object.assign(new Error('The take failed on the server'), { code: 'take_failed', retry: true });
            }
            const fresh = await db.get('parts', part.id);
            if (!fresh || fresh.takeId !== takeId) return; // recorded again meanwhile: the next run sends that
            part = fresh;
        }
        const current = part.frames > 0 ? part.takeId : null;
        const { data } = await api.parts.save(part.id, partBody(part, entry.changeId, current));
        const fresh = await db.get('parts', part.id);
        if (!fresh) {
            await enqueue('deletePart', part.id, { version: data.version });
            return;
        }
        await db.put('parts', { ...fresh, version: data.version, ownVersion: data.version, syncedTakeId: current ?? fresh.syncedTakeId ?? null, takeWaiting: null });
    }

    /**
     * A part the account deleted (part_gone: deleted elsewhere, or an Undo after the delete went
     * up) that's still wanted here comes back as a new part: a new id, its take a new take.
     */
    async function remake(id) {
        const part = await db.get('parts', id);
        if (!part) return;
        const fresh = { ...part, id: deps.uuid(), version: 0, syncedTakeId: null, takeId: part.frames > 0 ? deps.uuid() : null, takeWaiting: null };
        const samples = await db.getAudio(id);
        await db.put('parts', fresh);
        if (samples) await db.putAudio(fresh.id, samples);
        await db.remove('audio', id);
        await db.remove('parts', id);
        await enqueue('part', fresh.id, { projectId: part.projectId });
        again = true;
        deps.onChange?.([{ kind: 'part', id, projectId: part.projectId, removed: true }, { kind: 'part', id: fresh.id, projectId: part.projectId }]);
    }

    async function send(entry) {
        if (entry.kind === 'project') return sendProject(entry);
        if (entry.kind === 'part') return sendPart(entry);
        if (entry.kind === 'restoreProject') return api.projects.restore(entry.targetId);
        if (entry.kind === 'score') return api.scores.create(entry.body);
        if (entry.kind === 'deleteProject') return api.projects.delete(entry.targetId, { version: entry.version, changeId: entry.changeId });
        if (entry.kind === 'deletePart') return api.parts.delete(entry.targetId, { version: entry.version, changeId: entry.changeId });
        return null;
    }

    /** What to do when the account says no. Returns 'stop' to end this run, 'keep' to leave the entry, or 'drop'. */
    async function refused(entry, error) {
        const code = error?.code;
        // Not reachable (offline, a network or CORS failure on the upload), or busy: try again later.
        if (code === 'offline' || code === 'server_error' || error instanceof TypeError || error?.status === 423 || error?.status === 429 || error?.retry) {
            state.offline = code === 'offline';
            // Limited (the day's upload cap can be hours away): kept, and not tried again until then.
            if (error?.status === 429) restUntil = Date.now() + Math.max(30, error.retryAfter ?? 60) * 1000;
            return 'stop';
        }
        if (code === 'unauthenticated') return 'stop';
        if (code === 'version_conflict') {
            if ((entry.kind === 'project' || entry.kind === 'part') && (await mergedQuietly(entry, error.current))) return 'drop';
            const list = (await conflicts()).filter((c) => c.id !== entry.targetId);
            await setConflicts([...list, { kind: entry.kind, id: entry.targetId, projectId: entry.projectId ?? null, theirs: error.current }]);
            return 'drop';
        }
        if (code === 'project_missing') {
            const part = await db.get('parts', entry.targetId);
            if (part) await enqueue('project', part.projectId);
            return 'keep';
        }
        if (code === 'part_missing') return 'keep'; // the part goes first on the next run
        if (code === 'project_limit') {
            const project = await db.get('projects', entry.targetId);
            if (project) await db.put('projects', { ...project, notSaved: 'limit' });
            return 'drop';
        }
        // Waiting on the person (an email to confirm, room to make, a plan): kept, and sent again on
        // every run until it goes, so a recording is never dropped.
        if (code === 'storage_limit' || code === 'email_unverified' || code === 'project_read_only') {
            const part = await db.get('parts', entry.targetId);
            if (part) await db.put('parts', { ...part, takeWaiting: code });
            state.waiting = code;
            return 'keep';
        }
        if (code === 'scoring') return 'keep';
        // The upload didn't arrive whole: sent again from the start on the next run.
        if (code === 'upload_incomplete' || code === 'upload_mismatch') return 'keep';
        if (code === 'part_gone') {
            await remake(entry.targetId);
            return 'drop';
        }
        if (code === 'binned' || code === 'not_found') return 'drop';
        // A friend's project: the owner's settings or another singer's part (the app doesn't offer
        // those changes; one made on the device stays there).
        if (code === 'owner_only' || code === 'not_your_part') return 'drop';
        if (code === 'removed_from_project') {
            const part = await db.get('parts', entry.targetId);
            const project = part && (await db.get('projects', part.projectId));
            if (project?.shared) await db.put('projects', { ...project, shared: { ...project.shared, removed: true } });
            return 'drop';
        }
        console.error('Not saved to the account', entry, error);
        return 'drop';
    }

    /**
     * A stale save where only the practice state differs (the title, tempo, key, name, levels and take
     * are the same on both): merged without asking, this device's settings over theirs, and sent again.
     */
    async function mergedQuietly(entry, theirs) {
        if (!theirs) return false;
        const project = entry.kind === 'project';
        const local = await db.get(project ? 'projects' : 'parts', entry.targetId);
        if (!local) return false;
        const mine = project ? projectBody(local, '') : partBody(local, '', local.frames > 0 ? local.takeId : null);
        if (!(project ? PROJECT_CORE : PART_CORE).every((field) => JSON.stringify(mine[field] ?? null) === JSON.stringify(theirs[field] ?? null))) return false;
        await db.put(project ? 'projects' : 'parts', { ...(project ? projectFromServer(theirs, local) : local), ...mine.settings, version: theirs.version, ownVersion: theirs.version });
        await enqueue(entry.kind, entry.targetId, project ? {} : { projectId: local.projectId });
        again = true;
        return true;
    }

    async function push() {
        const entries = (await db.all('outbox')).sort((a, b) => ORDER[a.kind] - ORDER[b.kind] || a.at - b.at);
        for (const entry of entries) {
            try {
                await send(entry);
                await finished(entry);
            } catch (error) {
                const action = await refused(entry, error);
                if (action === 'stop') return false;
                if (action === 'drop') await finished(entry);
            }
        }
        return true;
    }

    /** Pulls every page since the cursor, then applies it (a part can come before its project across pages). */
    async function pull() {
        let cursor = (await db.get('meta', 'cursor')) ?? null;
        let full = !cursor;
        const changes = { projects: [], parts: [], takes: [], removed: [] };
        for (;;) {
            let answer;
            try {
                answer = await api.sync.pull({ cursor: cursor ?? undefined });
            } catch (error) {
                // Too old, or not a cursor the account knows any more: pull everything again.
                if (error?.code === 'cursor_expired' || (cursor && error?.code === 'validation_failed')) {
                    cursor = null;
                    full = true;
                    Object.values(changes).forEach((list) => (list.length = 0));
                    continue;
                }
                throw error;
            }
            for (const key of Object.keys(changes)) changes[key].push(...(answer.data[key] ?? []));
            cursor = answer.meta?.cursor ?? cursor;
            if (!answer.meta?.more) break;
        }
        // Projects to look at again: a take that was still on its way, or a download that failed.
        const recheck = (await db.get('meta', 'recheck')) ?? [];
        for (const id of recheck) {
            try {
                const shown = (await api.projects.show(id)).data;
                changes.parts.push(...shown.parts.filter((p) => !changes.parts.some((c) => c.id === p.id)));
                changes.takes.push(...shown.takes);
            } catch (error) {
                if (error?.code === 'offline') throw error;
            }
        }
        await db.put('meta', [], 'recheck');
        const changed = await apply(changes, full);
        await db.put('meta', cursor, 'cursor');
        state.lastSynced = Date.now();
        if (changed.length) deps.onChange?.(changed);
    }

    async function apply({ projects, parts, takes, removed }, full) {
        // Waiting to go up, a recording the account doesn't have yet, or waiting on Keep mine or Use
        // theirs: the device's copy stays as it is.
        const pending = new Set([...(await db.all('outbox')).map((e) => e.targetId), ...(await conflicts()).map((c) => c.id), ...(await db.all('parts')).filter(unsent).map((p) => p.id)]);
        const recheck = new Set();
        const takesById = new Map(takes.map((t) => [t.id, t]));
        const changed = [];
        const dropPart = async (id) => {
            await db.remove('audio', id);
            await db.remove('parts', id);
        };
        for (const server of projects) {
            if (pending.has(server.id)) continue;
            const local = await db.get('projects', server.id);
            if (server.binnedAt) {
                if (local) {
                    for (const part of await db.allBy('parts', 'projectId', server.id)) await dropPart(part.id);
                    await db.remove('projects', server.id);
                    changed.push({ kind: 'project', id: server.id, removed: true });
                }
                continue;
            }
            if (local && local.version === server.version) continue;
            await db.put('projects', projectFromServer(server, local));
            changed.push({ kind: 'project', id: server.id });
            // New to this device since the last pull (restored from the bin, say): its parts may
            // not have changed, so they come with the project, from projects.show.
            if (!local && !full) {
                try {
                    const shown = (await api.projects.show(server.id)).data;
                    parts = [...parts.filter((p) => p.projectId !== server.id), ...shown.parts];
                    for (const take of shown.takes) takesById.set(take.id, take);
                } catch (error) {
                    if (error?.code === 'offline') throw error;
                    recheck.add(server.id);
                }
            }
        }
        for (const server of parts) {
            if (pending.has(server.id)) continue;
            // One part that can't come down (a take still on its way, a download that failed) is
            // looked at again next time; the rest of the pull goes on.
            try {
                const local = await db.get('parts', server.id);
                if (local && local.version === server.version && local.takeId === server.currentTakeId) continue;
                let take = server.currentTakeId ? takesById.get(server.currentTakeId) : null;
                if (server.currentTakeId && !take) take = (await api.takes.show(server.currentTakeId)).data;
                const newTake = server.currentTakeId && server.currentTakeId !== local?.takeId;
                if (newTake && take?.status !== 'ready') {
                    if (take?.status !== 'failed') recheck.add(server.projectId);
                    continue;
                }
                if (newTake) {
                    const url = take.url ?? (await api.takes.show(take.id)).data.url;
                    await db.putAudio(server.id, await deps.loadTake(url, take.sampleRate));
                }
                if (!server.currentTakeId) await db.remove('audio', server.id);
                await db.put('parts', partFromServer(server, take, local));
                changed.push({ kind: 'part', id: server.id, projectId: server.projectId });
            } catch (error) {
                if (error?.code === 'offline') throw error;
                console.warn('A part did not come down; trying again next time', server.id, error);
                recheck.add(server.projectId);
            }
        }
        for (const gone of removed) {
            if (pending.has(gone.id)) continue;
            const goingPart = gone.kind === 'part' ? await db.get('parts', gone.id) : null;
            if (goingPart) {
                await dropPart(gone.id);
                changed.push({ kind: 'part', id: gone.id, projectId: goingPart.projectId, removed: true });
            }
            if (gone.kind === 'project' && (await db.get('projects', gone.id))) {
                for (const part of await db.allBy('parts', 'projectId', gone.id)) await dropPart(part.id);
                await db.remove('projects', gone.id);
                changed.push({ kind: 'project', id: gone.id, removed: true });
            }
        }
        if (recheck.size) await db.put('meta', [...new Set([...((await db.get('meta', 'recheck')) ?? []), ...recheck])], 'recheck');
        if (full) {
            // Pulled from the start: anything the account had (version > 0) but no longer lists is gone.
            const listed = new Set([...projects.map((p) => p.id), ...parts.map((p) => p.id)]);
            for (const project of await db.all('projects')) {
                if (project.version > 0 && !listed.has(project.id) && !pending.has(project.id) && !staysOnDevice(project) && !project.shared) {
                    for (const part of await db.allBy('parts', 'projectId', project.id)) await dropPart(part.id);
                    await db.remove('projects', project.id);
                    changed.push({ kind: 'project', id: project.id, removed: true });
                }
            }
        }
        return changed;
    }

    /** Sends what's waiting, then brings back what changed elsewhere. One run at a time; a call during a run runs again after. */
    async function run() {
        if (!deps.signedIn()) return;
        if (Date.now() < restUntil) return; // the server asked us to wait (429)
        if (running) {
            again = true;
            return running;
        }
        running = (async () => {
            try {
                do {
                    again = false;
                    state.offline = false;
                    state.waiting = null; // set again by a refusal that still holds
                    if (await push()) await pull();
                    state.pending = (await db.all('outbox')).length;
                    state.conflicts = await conflicts();
                    report();
                } while (again);
            } catch (error) {
                if (error?.code !== 'offline') console.warn('The sync stopped', error);
                state.offline = error?.code === 'offline';
                state.pending = (await db.all('outbox')).length;
                report();
            } finally {
                running = null;
            }
        })();
        return running;
    }

    /**
     * A conflict settled: 'mine' saves the device's copy (or its delete) over the account's, based on
     * the account's version; 'theirs' takes the account's copy as it is now (fetched fresh: the pulls
     * left this record alone while the person was asked), and a delete made here is undone.
     */
    async function resolve(conflict, choice) {
        const deleting = conflict.kind === 'deleteProject' || conflict.kind === 'deletePart';
        const isProject = conflict.kind === 'project' || conflict.kind === 'deleteProject';
        const projectId = isProject ? conflict.id : conflict.projectId ?? conflict.theirs?.projectId;
        const local = await db.get(isProject ? 'projects' : 'parts', conflict.id);
        let changes = [{ kind: isProject ? 'project' : 'part', id: conflict.id, projectId }];
        if (choice === 'mine' && deleting) {
            await enqueue(conflict.kind, conflict.id, { version: conflict.theirs.version });
        } else if (choice === 'mine' && local) {
            await db.put(isProject ? 'projects' : 'parts', { ...local, version: conflict.theirs.version, ownVersion: conflict.theirs.version });
            await enqueue(conflict.kind, conflict.id, isProject ? {} : { projectId: local.projectId });
        } else {
            let shown = null;
            try {
                shown = (await api.projects.show(projectId)).data;
            } catch (error) {
                if (error?.code !== 'not_found' && error?.status !== 410) throw error;
            }
            if (!shown) {
                // Gone from the account meanwhile: gone from here too.
                if (isProject) {
                    for (const part of await db.allBy('parts', 'projectId', conflict.id)) {
                        await db.remove('audio', part.id);
                        await db.remove('parts', part.id);
                    }
                }
                await db.remove(isProject ? 'projects' : 'parts', conflict.id);
            } else {
                const project = await db.get('projects', projectId);
                await db.put('projects', projectFromServer(shown.project, project));
                const takes = new Map(shown.takes.map((t) => [t.id, t]));
                const wanted = isProject ? shown.parts : shown.parts.filter((p) => p.id === conflict.id);
                for (const server of wanted) {
                    const had = await db.get('parts', server.id);
                    const take = server.currentTakeId ? takes.get(server.currentTakeId) ?? null : null;
                    const ready = take?.status === 'ready';
                    if (ready && take.id !== had?.takeId) await db.putAudio(server.id, await deps.loadTake(take.url ?? (await api.takes.show(take.id)).data.url, take.sampleRate));
                    // A take still on its way keeps what plays here until it arrives (looked at again on the next pull).
                    if (take && !ready) {
                        await db.put('parts', { ...partFromServer(server, null, had), ...Object.fromEntries(PART_TAKE_FIELDS.map((f) => [f, had?.[f] ?? null])), takeId: had?.takeId ?? null, syncedTakeId: had?.takeId ?? null });
                        await db.put('meta', [...new Set([...((await db.get('meta', 'recheck')) ?? []), projectId])], 'recheck');
                    } else {
                        await db.put('parts', partFromServer(server, take, had));
                    }
                }
                changes = [{ kind: 'project', id: projectId }, ...wanted.map((p) => ({ kind: 'part', id: p.id, projectId }))];
            }
        }
        await setConflicts((await conflicts()).filter((c) => c.id !== conflict.id));
        deps.onChange?.(changes);
    }

    /**
     * A friend's project as projects.shared or projects.show gives it (`{ project, parts, takes }`),
     * kept on the device with `shared` ({ role, owner }): the project, each part and its current take
     * (downloaded when it's new). This device's own unsent parts and changes stay as they are; parts
     * gone from the project go. Returns what changed, as onChange hears it.
     */
    async function keepShared(shown, shared) {
        const id = shown.project.id;
        const local = await db.get('projects', id);
        const pending = new Set([...(await db.all('outbox')).map((e) => e.targetId), ...(await db.all('parts')).filter(unsent).map((p) => p.id)]);
        const changed = [];
        if (!local || local.version !== shown.project.version || JSON.stringify(local.shared) !== JSON.stringify(shared)) {
            await db.put('projects', { ...projectFromServer(shown.project, local), shared });
            changed.push({ kind: 'project', id });
        }
        const takes = new Map(shown.takes.map((t) => [t.id, t]));
        const listed = new Set(shown.parts.map((p) => p.id));
        for (const server of shown.parts) {
            if (pending.has(server.id)) continue;
            try {
                const had = await db.get('parts', server.id);
                if (had && had.version === server.version && had.takeId === server.currentTakeId) continue;
                let take = server.currentTakeId ? takes.get(server.currentTakeId) ?? (await api.takes.show(server.currentTakeId)).data : null;
                const newTake = server.currentTakeId && server.currentTakeId !== had?.takeId;
                if (newTake && take?.status !== 'ready') continue; // still on its way: next time
                if (newTake) await db.putAudio(server.id, await deps.loadTake(take.url ?? (await api.takes.show(take.id)).data.url, take.sampleRate));
                if (!server.currentTakeId) {
                    await db.remove('audio', server.id);
                    take = null;
                }
                await db.put('parts', partFromServer(server, take, had));
                changed.push({ kind: 'part', id: server.id, projectId: id });
            } catch (error) {
                if (error?.code === 'offline') throw error;
                console.warn('A part of a shared project did not come down; trying again next time', server.id, error);
            }
        }
        for (const part of await db.allBy('parts', 'projectId', id)) {
            if (listed.has(part.id) || pending.has(part.id) || !(part.version > 0)) continue;
            await db.remove('audio', part.id);
            await db.remove('parts', part.id);
            changed.push({ kind: 'part', id: part.id, projectId: id, removed: true });
        }
        if (changed.length) deps.onChange?.(changed);
        return changed;
    }

    return { enqueue, enqueueDelete, undoDelete, adopt, forget, push, pull, run, resolve, keepShared, state };
}
