// Cloud save (js/sync.js) against a fake account that keeps the contract's rules ("Projects and
// cloud save"): versions and conflicts, a project before its parts and a part before its takes,
// replays by changeId, the take's create, upload and complete, the plan's project limit, and the
// pull's cursor.
import { createSync, partBody, partFromServer, projectBody } from '/js/sync.js';
import { assert, test } from './harness.js';

class Refusal extends Error {
    constructor(status, code, current = null) {
        super(code);
        Object.assign(this, { status, code, current });
    }
}

/** An account: what the server keeps, and its API. */
function fakeAccount({ projectLimit = 3 } = {}) {
    const projects = new Map();
    const parts = new Map();
    const takes = new Map();
    const uploads = new Map();
    const log = [];
    const deletedParts = new Set();
    const scores = new Map();
    const takeScores = new Map();
    const limits = { full: false, failDownloads: false, capped: false };
    let changeNo = 0;
    const changed = (kind, record) => {
        record.change = ++changeNo;
        log.push({ kind, id: record.id, change: changeNo });
    };
    const save = (map, id, body, extra) => {
        const had = map.get(id);
        if (had && had.changeId === body.changeId) return { data: had };
        if ((had?.version ?? 0) !== body.version) throw new Refusal(409, 'version_conflict', had);
        const record = { ...had, ...body, ...extra, id, version: (had?.version ?? 0) + 1, updatedAt: new Date().toISOString() };
        map.set(id, record);
        changed(map === projects ? 'project' : 'part', record);
        return { data: record };
    };
    const api = {
        projects: {
            save: async (id, body) => {
                if (!projects.has(id) && [...projects.values()].filter((p) => !p.binnedAt).length >= projectLimit) throw new Refusal(403, 'project_limit');
                return save(projects, id, body, { binnedAt: null });
            },
            delete: async (id, { version }) => {
                const p = projects.get(id);
                if (p && p.version !== version) throw new Refusal(409, 'version_conflict', p);
                if (p) {
                    p.binnedAt = new Date().toISOString();
                    changed('project', p);
                }
            },
            restore: async (id) => {
                const p = projects.get(id);
                p.binnedAt = null;
                changed('project', p);
                return { data: p };
            },
            show: async (id) => {
                const own = [...parts.values()].filter((part) => part.projectId === id);
                return { data: { project: projects.get(id), parts: own, takes: own.map((part) => takes.get(part.currentTakeId)).filter(Boolean) } };
            },
        },
        parts: {
            save: async (id, body) => {
                if (deletedParts.has(id)) throw new Refusal(409, 'part_gone');
                if (!projects.has(body.projectId)) throw new Refusal(409, 'project_missing');
                if (body.currentTakeId && takes.get(body.currentTakeId)?.partId !== id) throw new Refusal(422, 'validation_failed');
                return save(parts, id, body);
            },
            delete: async (id, { version } = {}) => {
                const p = parts.get(id);
                if (p && version !== undefined && p.version !== version) throw new Refusal(409, 'version_conflict', p);
                parts.delete(id);
                deletedParts.add(id);
                log.push({ kind: 'removed', id, change: ++changeNo });
            },
        },
        takes: {
            create: async (body) => {
                if (body.scores) takeScores.set(body.id, body.scores);
                if (limits.full) throw new Refusal(403, 'storage_limit');
                if (limits.capped) throw Object.assign(new Refusal(429, 'rate_limited'), { retryAfter: 3600 });
                if (!parts.has(body.partId)) throw new Refusal(409, 'part_missing');
                const had = takes.get(body.id);
                const take = had ?? { ...body, status: 'uploading', peaks: null, framesPerPeak: 512 };
                takes.set(body.id, take);
                return { data: take, upload: take.status === 'uploading' ? { url: `upload://${body.id}`, method: 'PUT', headers: {} } : null };
            },
            complete: async (id) => {
                const take = takes.get(id);
                if (!uploads.has(id)) throw new Refusal(409, 'upload_incomplete');
                take.status = 'ready';
                take.url = `take://${id}`;
                changed('take', take);
                return { data: take };
            },
            show: async (id) => ({ data: takes.get(id) }),
        },
        sync: {
            pull: async ({ cursor } = {}) => {
                const since = Number(cursor ?? 0);
                const newer = (map) => [...map.values()].filter((r) => r.change > since);
                return {
                    data: {
                        projects: newer(projects),
                        parts: newer(parts),
                        takes: newer(takes),
                        removed: log.filter((l) => l.kind === 'removed' && l.change > since).map((l) => ({ kind: 'part', id: l.id })),
                    },
                    meta: { cursor: String(changeNo), more: false },
                };
            },
        },
    };
    api.scores = { create: async (body) => (scores.set(body.id, body), { data: body }) };
    return { api, projects, parts, takes, uploads, limits, scores, takeScores };
}

/** A device: an in-memory IndexedDB with the stores sync.js uses. */
function fakeDevice() {
    const stores = { projects: new Map(), parts: new Map(), audio: new Map(), outbox: new Map(), meta: new Map() };
    const keyOf = (store, value, key) => key ?? (store === 'outbox' ? value.key : value.id);
    const db = {
        get: async (store, key) => structuredClone(stores[store].get(key)),
        put: async (store, value, key) => void stores[store].set(keyOf(store, value, key), structuredClone(value)),
        remove: async (store, key) => void stores[store].delete(key),
        all: async (store) => [...stores[store].values()].map((v) => structuredClone(v)),
        allBy: async (store, index, value) => [...stores[store].values()].filter((v) => v[index] === value).map((v) => structuredClone(v)),
        getAudio: async (key) => stores.audio.get(key),
        putAudio: async (key, samples) => void stores.audio.set(key, samples),
    };
    return { db, stores };
}

function sync(account, device, extra = {}) {
    let n = 0;
    return createSync({
        api: account.api,
        db: device.db,
        signedIn: () => true,
        uuid: () => `id-${++n}-${Math.random().toString(36).slice(2, 8)}`,
        sha256: async () => 'f'.repeat(64),
        encode: (samples) => ({ size: 44 + samples.length * 2, samples }),
        upload: async (url, { body }) => {
            account.uploads.set(url.replace('upload://', ''), body);
            return { ok: true, status: 200 };
        },
        loadTake: async (url) => {
            if (account.limits.failDownloads) throw new Error('The download failed');
            return account.uploads.get(url.replace('take://', ''))?.samples ?? new Float32Array(0);
        },
        ...extra,
    });
}

const project = (id, fields = {}) => ({ id, title: 'Amazing Grace', bpm: 80, key: 'G', timeSignature: '3/4', createdAt: 1, updatedAt: 1, practice: { focus: null }, ...fields });
const part = (id, projectId, fields = {}) => ({ id, projectId, name: 'Alto', color: '#C9B456', order: 1, volume: 1, pan: 0, muted: false, solo: false, offsetSeconds: 0.5, nudgeSeconds: 0, sampleRate: 48000, frames: 4800, peaks: [0.2], createdAt: 1, ...fields });

test('sync: a project with a recorded part goes up in order: the project, the part, its take, then the part pointing at it', async () => {
    const account = fakeAccount();
    const device = fakeDevice();
    const s = sync(account, device);
    await device.db.put('projects', project('p1'));
    await device.db.put('parts', part('a1', 'p1', { takeId: 't1' }));
    await device.db.putAudio('a1', new Float32Array(4800).fill(0.1));
    await s.enqueue('part', 'a1', { projectId: 'p1' }); // the part first, before its project
    await s.enqueue('project', 'p1');
    await s.run();
    const server = account.parts.get('a1');
    assert(account.projects.get('p1')?.version === 1, 'the project');
    assert(server?.currentTakeId === 't1' && account.takes.get('t1').status === 'ready', `the take: ${JSON.stringify(server)}`);
    assert(account.takes.get('t1').offsetFrames === 24000, 'offsetFrames at the take\'s rate');
    const local = await device.db.get('parts', 'a1');
    assert(local.version === server.version && local.syncedTakeId === 't1', `kept: ${JSON.stringify(local)}`);
    assert((await device.db.all('outbox')).length === 0, 'nothing waiting');
});

test('sync: a stale save is a conflict to settle; keep mine saves over theirs, use theirs takes it', async () => {
    const account = fakeAccount();
    const phone = fakeDevice();
    const s = sync(account, phone);
    await phone.db.put('projects', project('p1'));
    await s.enqueue('project', 'p1');
    await s.run();
    // Another device renames it.
    await account.api.projects.save('p1', { ...projectBody({ ...project('p1'), version: 1 }, 'other-change'), title: 'Grace (choir)' });
    // This one changes it too, based on version 1 (the pull hasn't run).
    await phone.db.put('projects', { ...(await phone.db.get('projects', 'p1')), title: 'Grace for Mum', version: 1 });
    await s.enqueue('project', 'p1');
    await s.run(); // the pull after it leaves this device's copy alone until the person chooses
    assert(s.state.conflicts.length === 1 && s.state.conflicts[0].theirs.title === 'Grace (choir)', JSON.stringify(s.state.conflicts));
    assert((await phone.db.get('projects', 'p1')).title === 'Grace for Mum', 'mine still here while asked');
    await s.resolve(s.state.conflicts[0], 'mine');
    await s.run();
    assert(account.projects.get('p1').title === 'Grace for Mum' && s.state.conflicts.length === 0, 'mine kept');
    // And the other way.
    await account.api.projects.save('p1', { ...projectBody(await phone.db.get('projects', 'p1'), 'x'), title: 'Theirs' });
    await phone.db.put('projects', { ...(await phone.db.get('projects', 'p1')), title: 'Mine', version: 2 });
    await s.enqueue('project', 'p1');
    await s.push();
    await s.resolve(s.state.conflicts[0], 'theirs');
    assert((await phone.db.get('projects', 'p1')).title === 'Theirs', 'theirs taken');
});

test('sync: a replay of the same change answers as it is, never a conflict', async () => {
    const account = fakeAccount();
    const body = projectBody(project('p1'), 'change-1');
    await account.api.projects.save('p1', body);
    const again = await account.api.projects.save('p1', body);
    assert(again.data.version === 1, 'the record as it is');
});

test('sync: another device\'s new take comes down; a change still waiting here isn\'t overwritten', async () => {
    const account = fakeAccount();
    const mac = fakeDevice();
    const phone = fakeDevice();
    const fromPhone = sync(account, phone);
    await phone.db.put('projects', project('p1'));
    await phone.db.put('parts', part('a1', 'p1', { takeId: 't1' }));
    await phone.db.putAudio('a1', new Float32Array(4800).fill(0.25));
    await fromPhone.adopt();
    await fromPhone.run();
    const onMac = sync(account, mac);
    await onMac.run();
    const local = await mac.db.get('parts', 'a1');
    assert(local?.takeId === 't1' && local.frames === 4800 && Math.abs(local.offsetSeconds - 0.5) < 1e-9, JSON.stringify(local));
    assert((await mac.db.getAudio('a1'))?.[0] === 0.25, 'the audio');
    assert((await mac.db.get('projects', 'p1')).practice !== undefined, 'the project\'s settings came back');
    // The Mac renames the part but hasn't sent it; the phone's change to it arrives: the Mac's stays.
    await mac.db.put('parts', { ...local, name: 'Alto (Mac)' });
    await onMac.enqueue('part', 'a1', { projectId: 'p1' });
    await phone.db.put('parts', { ...(await phone.db.get('parts', 'a1')), volume: 0.5 });
    await fromPhone.enqueue('part', 'a1', { projectId: 'p1' });
    await fromPhone.run();
    await onMac.pull();
    assert((await mac.db.get('parts', 'a1')).name === 'Alto (Mac)', 'the waiting change stays');
});

test('sync: a project restored from the bin comes back whole, its parts and takes with it', async () => {
    const account = fakeAccount();
    const phone = fakeDevice();
    const s = sync(account, phone);
    await phone.db.put('projects', project('p1'));
    await phone.db.put('parts', part('a1', 'p1', { takeId: 't1' }));
    await phone.db.putAudio('a1', new Float32Array(4800).fill(0.1));
    await s.enqueue('project', 'p1');
    await s.enqueue('part', 'a1', { projectId: 'p1' });
    await s.run();
    await s.enqueueDelete('project', await phone.db.get('projects', 'p1'));
    await phone.db.remove('parts', 'a1'); // as deleteProject does
    await phone.db.remove('audio', 'a1');
    await phone.db.remove('projects', 'p1');
    await s.run();
    // Restored on another device: only the project changed, so the pull carries only the project.
    await account.api.projects.restore('p1');
    await s.run();
    const back = await phone.db.get('parts', 'a1');
    assert((await phone.db.get('projects', 'p1')) && back?.frames === 4800, `the part came back: ${JSON.stringify(back)}`);
    assert((await phone.db.getAudio('a1'))?.length === 4800, 'with its take');
});

test('sync: the fourth project past the plan stays on the device, marked not saved', async () => {
    const account = fakeAccount({ projectLimit: 3 });
    const device = fakeDevice();
    const s = sync(account, device);
    for (const id of ['p1', 'p2', 'p3', 'p4']) {
        await device.db.put('projects', project(id, { createdAt: Number(id.slice(1)) }));
        await s.enqueue('project', id);
    }
    await s.run();
    assert(account.projects.size === 3 && (await device.db.get('projects', 'p4')).notSaved === 'limit', 'p4 not saved');
});

test('sync: a delete is sent only for what the account has; lesson songs and beta takes never go', async () => {
    const account = fakeAccount();
    const device = fakeDevice();
    const s = sync(account, device);
    await device.db.put('projects', project('kept'));
    await device.db.put('projects', project('lesson', { course: { lessonId: '4-words' } }));
    await device.db.put('projects', project('beta', { beta: { partKey: 'alto' } }));
    await s.adopt();
    await s.run();
    assert(account.projects.has('kept') && !account.projects.has('lesson') && !account.projects.has('beta'), [...account.projects.keys()].join());
    await s.enqueueDelete('project', { id: 'never-sent', version: 0 });
    assert(!(await device.db.get('outbox', 'deleteProject:never-sent')), 'nothing to tell');
    await s.enqueueDelete('project', await device.db.get('projects', 'kept'));
    await s.run();
    assert(account.projects.get('kept').binnedAt, 'binned on the account');
});

test('sync: the device keeps its own fields out of what it sends, and back on the way in', () => {
    const body = partBody({ ...part('a1', 'p1'), guidePeaks: [1, 2], nudgeSeconds: 0.01, useGuide: true }, 'c', 't1');
    assert(body.nudgeFrames === 480 && body.settings.useGuide === true && !('guidePeaks' in body.settings) && !('frames' in body.settings), JSON.stringify(body.settings));
    const back = partFromServer({ ...body, id: 'a1', version: 2, createdAt: new Date(1).toISOString() }, { id: 't1', sampleRate: 48000, frames: 9600, offsetFrames: 24000, peaks: [0.3], framesPerPeak: 512 });
    assert(back.offsetSeconds === 0.5 && Math.abs(back.nudgeSeconds - 0.01) < 1e-9 && back.useGuide === true && back.takeId === 't1', JSON.stringify(back));
});

// ---- From the M4 review --------------------------------------------------------------------------

/** A project with one recorded part, saved to the account from `device`. */
async function recorded(account, device, s, { take = 't1', fill = 0.1 } = {}) {
    await device.db.put('projects', project('p1'));
    await device.db.put('parts', part('a1', 'p1', { takeId: take }));
    await device.db.putAudio('a1', new Float32Array(4800).fill(fill));
    await s.enqueue('project', 'p1');
    await s.enqueue('part', 'a1', { projectId: 'p1' });
    await s.run();
}

test('sync: a take refused for a full account waits, and a pull never replaces it', async () => {
    const account = fakeAccount();
    const phone = fakeDevice();
    const mac = fakeDevice();
    const p = sync(account, phone);
    await recorded(account, phone, p);
    const m = sync(account, mac);
    await m.run();
    account.limits.full = true;
    // The phone records again; the account is full.
    await phone.db.put('parts', { ...(await phone.db.get('parts', 'a1')), takeId: 't2' });
    await phone.db.putAudio('a1', new Float32Array(4800).fill(0.7));
    await p.enqueue('part', 'a1', { projectId: 'p1' });
    await p.run();
    assert(p.state.waiting === 'storage_limit' && (await phone.db.get('outbox', 'part:a1')), 'kept, waiting');
    // The Mac changes the part meanwhile; the phone's pull leaves the new recording alone.
    await mac.db.put('parts', { ...(await mac.db.get('parts', 'a1')), volume: 0.5 });
    await m.enqueue('part', 'a1', { projectId: 'p1' });
    await m.run();
    await p.run();
    assert((await phone.db.getAudio('a1'))[0] > 0.6, 'the new recording is still here');
    // Room again: the take goes up, and as the Mac changed the part meanwhile, the phone is asked.
    account.limits.full = false;
    await p.run();
    assert(account.takes.get('t2')?.status === 'ready' && p.state.conflicts[0]?.kind === 'part', 'sent once there was room, then asked');
    await p.resolve(p.state.conflicts[0], 'mine');
    await p.run();
    assert(account.parts.get('a1').currentTakeId === 't2' && !p.state.waiting, 'the new recording is the part\'s');
});

test('sync: a delete that meets a change elsewhere asks; Delete it deletes, Keep their version brings it back', async () => {
    for (const choice of ['mine', 'theirs']) {
        const account = fakeAccount();
        const phone = fakeDevice();
        const mac = fakeDevice();
        const p = sync(account, phone);
        await recorded(account, phone, p);
        const m = sync(account, mac);
        await m.run();
        await mac.db.put('parts', { ...(await mac.db.get('parts', 'a1')), name: 'Alto (Mac)' });
        await m.enqueue('part', 'a1', { projectId: 'p1' });
        await m.run();
        const gone = await phone.db.get('parts', 'a1');
        await phone.db.remove('parts', 'a1');
        await p.enqueueDelete('part', gone);
        await p.run();
        const conflict = p.state.conflicts[0];
        assert(conflict?.kind === 'deletePart', `asked: ${JSON.stringify(p.state.conflicts)}`);
        await p.resolve(conflict, choice);
        await p.run();
        if (choice === 'mine') assert(!account.parts.has('a1'), 'deleted');
        else assert((await phone.db.get('parts', 'a1'))?.name === 'Alto (Mac)' && account.parts.has('a1'), 'back, as the Mac has it');
    }
});

test('sync: an Undo after the delete went up brings the part back as a new part', async () => {
    const account = fakeAccount();
    const phone = fakeDevice();
    const s = sync(account, phone);
    await recorded(account, phone, s);
    const had = await phone.db.get('parts', 'a1');
    await phone.db.remove('parts', 'a1');
    await s.enqueueDelete('part', had);
    await s.run();
    // Undo: the part is saved again as it was.
    await phone.db.put('parts', had);
    await s.enqueue('part', 'a1', { projectId: 'p1' });
    await s.run();
    const parts = await phone.db.all('parts');
    assert(parts.length === 1 && parts[0].id !== 'a1' && account.parts.has(parts[0].id), `a new part: ${JSON.stringify(parts.map((x) => x.id))}`);
    assert((await phone.db.getAudio(parts[0].id))?.length === 4800 && account.takes.get(parts[0].takeId)?.status === 'ready', 'with its take, sent again');
});

test('sync: an Undo before the delete went up just cancels the delete', async () => {
    const account = fakeAccount();
    const phone = fakeDevice();
    const s = sync(account, phone);
    await recorded(account, phone, s);
    const had = await phone.db.get('parts', 'a1');
    await phone.db.remove('parts', 'a1');
    await s.enqueueDelete('part', had);
    await phone.db.put('parts', had);
    await s.enqueue('part', 'a1', { projectId: 'p1' });
    await s.run();
    assert(account.parts.has('a1') && !s.state.conflicts.length, 'never deleted, no question');
});

test('sync: a project saved after being past the limit takes its parts with it', async () => {
    const account = fakeAccount({ projectLimit: 0 });
    const phone = fakeDevice();
    const s = sync(account, phone);
    await recorded(account, phone, s);
    assert((await phone.db.get('projects', 'p1')).notSaved === 'limit' && !account.parts.size, 'not saved');
    account.limits.projectLimit = 3;
    // The limit isn't the fake's to lift: a new account would be; here the project is just saved again.
    const fresh = fakeAccount();
    const t = sync(fresh, phone);
    await t.enqueue('project', 'p1');
    await t.run();
    assert(fresh.parts.get('a1')?.currentTakeId === 't1', 'its part and take followed');
});

test('sync: a conflict over practice state only is merged without asking', async () => {
    const account = fakeAccount();
    const phone = fakeDevice();
    const s = sync(account, phone);
    await recorded(account, phone, s);
    const server = account.projects.get('p1');
    await account.api.projects.save('p1', { ...projectBody({ ...project('p1'), version: server.version }, 'elsewhere'), settings: { practice: { focus: 'b' } } });
    await phone.db.put('projects', { ...(await phone.db.get('projects', 'p1')), practice: { focus: 'a1' } });
    await s.enqueue('project', 'p1');
    await s.run();
    assert(!s.state.conflicts.length && account.projects.get('p1').settings.practice.focus === 'a1', 'merged, this device\'s practice kept');
});

test("sync: a part that can't come down is looked at again; the rest of the pull goes on", async () => {
    const account = fakeAccount();
    const phone = fakeDevice();
    const p = sync(account, phone);
    await recorded(account, phone, p);
    await phone.db.put('projects', project('p2'));
    await phone.db.put('parts', part('b1', 'p2', { takeId: 't9' }));
    await phone.db.putAudio('b1', new Float32Array(4800).fill(0.2));
    await p.enqueue('project', 'p2');
    await p.enqueue('part', 'b1', { projectId: 'p2' });
    await p.run();
    const mac = fakeDevice();
    const m = sync(account, mac);
    account.limits.failDownloads = true;
    await m.run();
    assert((await mac.db.get('projects', 'p1')) && (await mac.db.get('projects', 'p2')), 'the projects came anyway');
    assert(!(await mac.db.get('parts', 'a1')), 'the part without its audio waits');
    account.limits.failDownloads = false;
    await m.run();
    assert((await mac.db.getAudio('a1'))?.length === 4800 && (await mac.db.getAudio('b1'))?.length === 4800, 'and comes next time');
});

test("sync: another account signing in clears the last one's synced projects, and keeps unsaved ones", async () => {
    const account = fakeAccount();
    const phone = fakeDevice();
    const s = sync(account, phone);
    await recorded(account, phone, s);
    await phone.db.put('projects', project('local'));
    await s.forget();
    assert(!(await phone.db.get('projects', 'p1')) && !(await phone.db.get('parts', 'a1')) && (await phone.db.get('projects', 'local')), 'synced gone, unsaved kept');
    assert(!(await phone.db.get('meta', 'cursor')) && !(await phone.db.all('outbox')).length, 'the sync starts afresh');
});

test("sync: a take's score goes with its take, and an exercise's score on its own", async () => {
    const account = fakeAccount();
    const phone = fakeDevice();
    const s = sync(account, phone);
    await phone.db.put('projects', project('p1'));
    await phone.db.put('parts', part('a1', 'p1', { takeId: 't1', score: { pitchPercent: 82, takeId: 't1' } }));
    await phone.db.putAudio('a1', new Float32Array(4800).fill(0.1));
    await s.enqueue('project', 'p1');
    await s.enqueue('part', 'a1', { projectId: 'p1' });
    await s.enqueue('score', 's1', { body: { id: 's1', lesson: '1-match.2', summary: { pitchPercent: 70 } } });
    await s.run();
    assert(account.takeScores.get('t1')?.pitchPercent === 82 && !('takeId' in account.takeScores.get('t1')), 'the take report, without the device\'s own field');
    assert(account.scores.get('s1')?.lesson === '1-match.2', 'the exercise');
    assert(!(await phone.db.all('outbox')).length, 'nothing waiting');
});

test('sync: in a friend\'s project, only the singer\'s own parts go up; the owner\'s settings and parts stay as they are, and a pull keeps the project', async () => {
    const account = fakeAccount();
    // The owner's project, with their alto recorded.
    const owner = fakeDevice();
    const o = sync(account, owner);
    await owner.db.put('projects', project('p1'));
    await owner.db.put('parts', part('a1', 'p1', { takeId: 't1' }));
    await owner.db.putAudio('a1', new Float32Array(4800).fill(0.1));
    await o.enqueue('project', 'p1');
    await o.enqueue('part', 'a1', { projectId: 'p1' });
    await o.run();
    // Sam joins: the project comes to Sam's device from projects.show. Sam's own pulls list none of it.
    const sam = fakeDevice();
    const api = { ...account.api, sync: { pull: async () => ({ data: { projects: [], parts: [], takes: [], removed: [] }, meta: { cursor: '9', more: false } }) } };
    const s = sync(account, sam, { api, me: () => 'sam' });
    const changed = await s.keepShared((await account.api.projects.show('p1')).data, { role: 'singer', owner: { id: 'ana', name: 'Ana' } });
    assert(changed.some((c) => c.kind === 'part' && c.id === 'a1'), JSON.stringify(changed));
    const kept = await sam.db.get('projects', 'p1');
    assert(kept.shared?.owner.name === 'Ana' && (await sam.db.get('parts', 'a1')).frames === 4800, 'the project and the owner\'s take are here');
    // Sam renames the song and turns the alto down (here only), and records a tenor of their own.
    await sam.db.put('projects', { ...kept, title: 'Sam\'s title' });
    await sam.db.put('parts', { ...(await sam.db.get('parts', 'a1')), volume: 0.3 });
    await sam.db.put('parts', part('s1', 'p1', { name: 'Tenor', takeId: 't2', createdBy: { id: 'sam', name: 'Sam' } }));
    await sam.db.putAudio('s1', new Float32Array(4800).fill(0.2));
    await s.adopt();
    await s.enqueue('project', 'p1');
    await s.enqueue('part', 'a1', { projectId: 'p1' });
    await s.run();
    assert(account.projects.get('p1').title === 'Amazing Grace' && account.projects.get('p1').version === 1, 'the owner\'s settings are untouched');
    assert(account.parts.get('a1').volume === 1, 'the owner\'s part is untouched');
    assert(account.parts.get('s1')?.currentTakeId === 't2', 'Sam\'s own part and take went up');
    assert(await sam.db.get('projects', 'p1'), 'a full pull of Sam\'s own projects leaves the friend\'s project here');
    assert((await sam.db.all('outbox')).length === 0, 'nothing left waiting');
});

test("sync: a take over the day's upload cap stays waiting, and isn't tried again until it may", async () => {
    const account = fakeAccount();
    const phone = fakeDevice();
    const p = sync(account, phone);
    await recorded(account, phone, p);
    account.limits.capped = true;
    await phone.db.put('parts', { ...(await phone.db.get('parts', 'a1')), takeId: 't2' });
    await phone.db.putAudio('a1', new Float32Array(4800).fill(0.7));
    await p.enqueue('part', 'a1', { projectId: 'p1' });
    await p.run();
    assert(await phone.db.get('outbox', 'part:a1'), 'kept in the outbox');
    // The cap lifts on the server, but the hour asked for hasn't passed: nothing is sent yet.
    account.limits.capped = false;
    await p.run();
    assert(!account.takes.get('t2'), 'tried again before the time asked');
});
