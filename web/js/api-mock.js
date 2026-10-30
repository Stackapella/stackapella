// The mock API: `?api=mock`, on this machine only (localhost, or a Herd *.test site). It answers
// api.js's calls from the contract itself, so screens are built and tested against the JSON the
// backend is checked against (docs/parallel-agents-plan.md, "The mock"):
// - contract/api.md says each operation's cases (status, code, example);
// - contract/examples/<operation>.<case>.json is the answer;
// - `?scenario=` picks another case: `rate_limited` for every call, or
//   `admin.flags.update:sudo_required` for one operation (several, comma-separated). The generic
//   codes in api.md's errors table work for any operation; `csrf_mismatch` refuses once, so the
//   retry can be seen.
// - It keeps a little state where screens need it: the admin's switches, limits and blocklist
//   change when they're changed; the review queue keeps its decisions.
// - `?as=` says who's signed in: `member` (the examples' account, the default), `singer` (a beta
//   singer), `admin` (staff, for the version badge) or `signedOut`. Signing in, out and accepting a beta invite change it.
//
// The choice is remembered for the tab (sessionStorage), so it survives moving around the app;
// `?api=real` turns it off.

import { OPERATIONS } from './api.js';
import { session as tabStorage } from './storage.js';

const THIS_MACHINE = () => ['localhost', '127.0.0.1'].includes(location.hostname) || location.hostname.endsWith('.test');

/** Whether calls go to the mock: asked for with ?api=mock, on this machine only. */
export function mockWanted() {
    if (!THIS_MACHINE()) return false;
    const params = new URLSearchParams(location.search);
    if (params.has('api')) tabStorage.set('stackapella.api', params.get('api') === 'mock' ? 'mock' : '');
    if (params.has('scenario')) tabStorage.set('stackapella.scenario', params.get('scenario'));
    if (params.has('as')) tabStorage.set('stackapella.mockAs', params.get('as'));
    return tabStorage.get('stackapella.api') === 'mock';
}

function scenarios() {
    return (tabStorage.get('stackapella.scenario') ?? '').split(',').map((s) => s.trim()).filter(Boolean);
}

/** Tests only: set the scenario directly, and start the kept state afresh. */
export function useScenario(scenario) {
    tabStorage.set('stackapella.scenario', scenario);
    refused.clear();
    for (const key of Object.keys(state)) delete state[key];
}

let contract = null;

/** The contract's cases: per operation [{ status, code, schema, example }], and the generic codes' statuses. */
export async function loadContract() {
    contract ??= fetch('/contract/api.md').then((r) => r.text()).then(parseContract);
    return contract;
}

/** Reads api.md's tables. Shared with the contract test (tests/api.test.js). */
export function parseContract(markdown) {
    const operations = {};
    const generic = {};
    let current = null;
    let inErrors = false;
    for (const line of markdown.split('\n')) {
        const heading = line.match(/^#{3,4} `([\w.]+)`/);
        if (heading) {
            current = heading[1];
            operations[current] ??= []; // a later "(changed)" section adds to the operation, never empties it
            inErrors = false;
            continue;
        }
        if (/^#{2,4} /.test(line)) {
            current = null;
            inErrors = /^### Errors/.test(line);
            continue;
        }
        const cells = line.split('|').slice(1, -1).map((c) => c.trim());
        if (current && /^\d{3}$/.test(cells[0] ?? '')) {
            const unquote = (c) => c.replace(/`/g, '') || null;
            operations[current].push({ status: Number(cells[0]), code: unquote(cells[1]), schema: unquote(cells[2]), example: unquote(cells[3]) });
        } else if (inErrors && /^\d{3}$/.test(cells[0] ?? '')) {
            const code = cells[1].match(/^`(\w+)`$/);
            if (code) generic[code[1]] = Number(cells[0]);
        }
    }
    const table = markdown.match(/## Operations\n[\s\S]*?\n\n((?:\|.*\n)+)/);
    const routes = {};
    for (const row of table ? table[1].split('\n') : []) {
        const match = row.match(/^\| `([\w.]+)` \| `(\w+) ([^`]+)` \| ([^|]*)\|/);
        // Operations only the native apps call ("An app ...") aren't the web client's.
        if (match && !/^An app\b/.test(match[4].trim())) routes[match[1]] = [match[2], match[3]]; // i18n-ignore: the contract's words
    }
    return { operations, generic, routes };
}

// ---- Answering ----------------------------------------------------------------------------------

// Codes api.md says any operation of a family may answer, without repeating them per operation.
const SPREAD = { sudo_required: 403, not_admin: 403, second_factor_required: 403, not_beta_singer: 403, singer_revoked: 403 };

const refused = new Set(); // operations already refused once with csrf_mismatch
const state = {};
const ME = { id: 'Xk3pQ9', name: 'Hillel Coren' }; // i18n-ignore: the examples' admin

/** Answers like fetch() would, from the contract. `init.operation` names the call (api.js). */
export async function mockFetch(url, init = {}) {
    const { operations, generic } = await loadContract();
    const operation = init.operation ?? 'admin.me';
    (globalThis.mockCalls ??= []).push(operation); // for the flows: which operations a screen called
    if (operation === 'upload') {
        // A presigned PUT: the mock has no storage, so it takes the file (or refuses it, by scenario).
        await new Promise((resolve) => setTimeout(resolve, 120));
        return new Response(null, { status: scenarios().includes('upload:upload_failed') ? 403 : 200 });
    }
    const cases = operations[operation] ?? [];
    await new Promise((resolve) => setTimeout(resolve, 120)); // like a network, so loading states show
    const wanted = scenarios().map((s) => s.split(':')).find(([op, code]) => (code ? op === operation : true));
    const code = wanted ? wanted[wanted.length - 1] : null;
    if (code) {
        if (code === 'csrf_mismatch' && refused.has(operation)) {
            // Refused once already: the retry goes through.
        } else {
            if (code === 'csrf_mismatch') refused.add(operation);
            const known = cases.find((c) => c.code === code);
            if (known?.example) return respond(known.status, await example(known.example));
            const status = known?.status ?? generic[code] ?? SPREAD[code] ?? 400;
            return respond(status, { message: code, code, ...(status === 429 ? { retryAfter: 1200 } : {}) });
        }
    }
    const address = new URL(url, location.origin);
    const params = matchPath(OPERATIONS[operation]?.[1], address.pathname);
    const query = Object.fromEntries(address.searchParams);
    const body = init.body && !(init.body instanceof FormData) ? JSON.parse(init.body) : null;
    (globalThis.mockBodies ??= []).push({ operation, body }); // for the flows: what a screen sent
    const handled = await stateful(operation, params, body, cases, query);
    if (handled) return handled;
    const success = cases.find((c) => c.status < 300);
    if (!success) return respond(404, { message: 'not_found', code: 'not_found' });
    return respond(success.status, success.example ? listed(await example(success.example), query) : null);
}

/**
 * A list example, as the server would answer the query: filtered by `type` or an `action` prefix,
 * and with no further page once a cursor has been followed (the examples have one page).
 */
function listed(json, query) {
    if (!Array.isArray(json?.data) || !json.meta) return json;
    let data = json.data;
    if (query.type) data = data.filter((item) => item.type === query.type);
    if (query.action) data = data.filter((item) => item.action?.startsWith(query.action));
    return { ...json, data: query.cursor ? [] : data, meta: { ...json.meta, nextCursor: query.cursor ? null : json.meta.nextCursor } };
}

function respond(status, json) {
    return new Response(status === 204 || json === null ? null : JSON.stringify(json), {
        status,
        headers: { 'Content-Type': 'application/json', ...(status === 429 ? { 'Retry-After': '1200' } : {}) },
    });
}

async function example(name) {
    const response = await fetch(`/contract/examples/${name}`);
    if (!response.ok) throw new Error(`The contract has no example ${name}`);
    const json = await response.json();
    // The mock's account has given its birth date (an email sign-up asks it), so "When were you
    // born?" stays shut unless a flow takes it away.
    if (json?.data?.birthGiven === false) json.data.birthGiven = true;
    return json;
}

function matchPath(pattern, path) {
    if (!pattern) return {};
    const names = [];
    const regex = new RegExp(`^${pattern.replace(/\{(\w+)\}/g, (_, name) => (names.push(name), '([^/]+)'))}$`);
    const match = path.match(regex);
    return match ? Object.fromEntries(names.map((name, i) => [name, decodeURIComponent(match[i + 1])])) : {};
}

/** The few operations that change something keep it, so the admin's lists stay true. */
async function stateful(operation, params, body, cases, query = {}) {
    const caseFor = async (code) => {
        const known = cases.find((c) => c.code === code);
        return respond(known?.status ?? 422, known?.example ? await example(known.example) : { message: code, code });
    };
    const now = new Date().toISOString().replace(/\.\d+Z$/, 'Z');
    if (operation === 'templates.catalogue') {
        // The examples' real voices live on a CDN the mock can't reach: they play a local fixture
        // (3 seconds of chirps, made as the server makes a guide) instead.
        const { data } = await example('templates.catalogue.200.json');
        for (const song of data) {
            for (const part of song.parts) {
                const local = (voice) => Object.assign(voice, { url: `${location.origin}/tests/fixtures/guide-48000.m4a?part=${song.id}-${part.key}-${voice.id}`, sampleRate: 48000, frames: 144000, offsetFrames: 0 });
                if (part.guide) local(part.guide);
                (part.versions ?? []).forEach(local);
            }
        }
        return respond(200, { data });
    }
    // Ideas (its server flag, as auth.config says it): open in the mock, closed with ideas.list:not_found.
    if (operation === 'auth.config') {
        const { data } = await example('auth.config.200.json');
        return respond(200, { data: { ...data, ideas: !scenarios().includes('ideas.list:not_found') } });
    }
    // Who's signed in, and what that allows.
    const as = tabStorage.get('stackapella.mockAs') || 'member';
    const accountAs = async (who) => {
        tabStorage.set('stackapella.mockAs', who);
        const { data } = await example('account.show.200.json');
        // The look this tab saved (account.update's appearance, merged as the server merges it).
        // Nothing saved and none in the example: null, as the server says before anyone chooses.
        const saved = JSON.parse(tabStorage.get('stackapella.mockAppearance') || 'null');
        const appearance = saved || data.appearance ? { ...(data.appearance ?? {}), ...(saved ?? {}) } : null;
        // Hide profile, as this tab last set it.
        const hidden = tabStorage.get('stackapella.mockHideProfile');
        if (hidden !== null) data.hideProfile = hidden === '1';
        // Initials on the boards, as this tab last set them; `young` is under 18 (initials only).
        const naming = JSON.parse(tabStorage.get('stackapella.mockInitials') || '{}');
        Object.assign(data, naming);
        if (who === 'young') Object.assign(data, { isAdult: false, boardsName: 'initials' });
        // `plus` and `singerPlus` are on Plus; `singer` and `singerPlus` are beta singers.
        return { data: { ...data, appearance, betaSinger: who === 'singer' || who === 'singerPlus', plan: who === 'plus' || who === 'singerPlus' ? 'plus' : data.plan, staff: who === 'admin' ? 'admin' : null } };
    };
    const signedOut = () => respond(401, { message: 'unauthenticated', code: 'unauthenticated' });
    if (operation === 'auth.signout' || operation === 'auth.signout.everywhere') {
        tabStorage.set('stackapella.mockAs', 'signedOut');
        return respond(204, null);
    }
    if (operation === 'auth.register' && body?.invite) tabStorage.set('stackapella.mockInvite', body.invite);
    if (['auth.signin', 'auth.verify', 'auth.password.reset'].includes(operation)) {
        // A sign-up that carried an invite is a singer once verified (the server accepts it then).
        const invited = operation === 'auth.verify' && tabStorage.get('stackapella.mockInvite');
        tabStorage.set('stackapella.mockInvite', '');
        if (invited) tabStorage.set('stackapella.mockFresh', '1');
        return respond(200, await accountAs(invited || as === 'singer' ? 'singer' : 'member'));
    }
    // An invite the signed-in singer took (at the sign-up's verification) shows as accepted.
    if (operation === 'beta.invite.show' && as === 'singer') {
        const json = await example('beta.invite.show.200.json');
        return respond(200, { data: { ...json.data, status: 'accepted' } });
    }
    if (operation === 'account.show') return as === 'signedOut' ? signedOut() : respond(200, await accountAs(as));
    if (operation === 'account.update' && typeof body?.hideProfile === 'boolean' && as !== 'signedOut') {
        tabStorage.set('stackapella.mockHideProfile', body.hideProfile ? '1' : '0');
        return respond(200, await accountAs(as));
    }
    if (operation === 'account.update' && body?.appearance && as !== 'signedOut') {
        // The look is kept for the tab (game mode, the theme): absent fields unchanged, as on the server.
        const kept = JSON.parse(tabStorage.get('stackapella.mockAppearance') || '{}');
        tabStorage.set('stackapella.mockAppearance', JSON.stringify({ ...kept, ...body.appearance }));
        const { appearance, ...rest } = body;
        const account = await accountAs(as);
        return respond(200, { data: { ...account.data, ...rest } });
    }
    if (operation === 'account.update' && ('initials' in (body ?? {}) || 'boardsName' in (body ?? {})) && as !== 'signedOut') {
        // As the server: three letters A to Z, never one of its blocked combinations (the mock's sample: XXX).
        const naming = JSON.parse(tabStorage.get('stackapella.mockInitials') || '{}');
        const initials = 'initials' in body ? body.initials : naming.initials ?? null;
        if (initials !== null && (!/^[A-Z]{3}$/.test(initials) || initials === 'XXX')) return respond(422, { message: 'Unprocessable Content.', code: 'validation_failed', errors: { initials: ['Choose other letters.'] } }); // i18n-ignore: the server's words
        if (body.boardsName === 'initials' && !initials) return respond(422, { message: 'Unprocessable Content.', code: 'validation_failed', errors: { boardsName: ['Choose your initials first.'] } }); // i18n-ignore: the server's words
        tabStorage.set('stackapella.mockInitials', JSON.stringify({ initials, boardsName: body.boardsName ?? naming.boardsName ?? 'name' }));
        return respond(200, await accountAs(as));
    }
    if (operation.startsWith('account.') && as === 'signedOut') return signedOut();
    if (operation.startsWith('beta.') && !['beta.invite.show', 'beta.agreement.show'].includes(operation)) {
        if (as === 'signedOut') return signedOut();
        // A singer who has just accepted an invite hasn't agreed to the agreement yet.
        const fresh = async (name) => {
            const json = await example(name);
            if (tabStorage.get('stackapella.mockFresh')) json.data.consent = { version: null, acceptedAt: null, current: json.data.consent.current };
            return json;
        };
        if (operation === 'beta.invite.accept') {
            tabStorage.set('stackapella.mockAs', 'singer');
            tabStorage.set('stackapella.mockFresh', '1');
            return respond(200, await fresh('beta.invite.accept.200.json'));
        }
        if (as !== 'singer' && as !== 'singerPlus') return respond(403, { message: 'not_beta_singer', code: 'not_beta_singer' });
        if (operation === 'beta.me.show') return respond(200, await fresh('beta.me.show.200.json'));
        if (operation === 'beta.consent.create') tabStorage.set('stackapella.mockFresh', '');
    }
    if (operation === 'beta.parts.list') {
        // As the catalogue: the parts' real voices play the local fixture.
        const parts = await example('beta.parts.list.200.json');
        for (const part of parts.data) {
            part.guides = part.guides.map((g) => ({ ...g, url: `${location.origin}/tests/fixtures/guide-48000.m4a?part=${part.template?.id}-${g.partKey}`, sampleRate: 48000, frames: 144000, offsetFrames: 0 }));
        }
        return respond(200, parts);
    }
    if (operation.startsWith('admin.beta.submissions.')) {
        // The review queue's take keeps its decisions; its audio is the local fixtures (a 3-second
        // master at 48 kHz, and the bass as a published guide at 44.1 kHz).
        if (!state.submission) {
            state.submission = (await example('admin.beta.submissions.show.200.json')).data;
            Object.assign(state.submission, { sampleRate: 48000, frames: 144000, durationSeconds: 3 });
            // Sung against the song as it ships now, as a freshly sent take is (the example's version
            // falls behind whenever the arrangement moves on, and the chart is only drawn on matching notes).
            const song = await fetch(`/templates/${state.submission.template.id}.json`).then((r) => r.json());
            Object.assign(state.submission.template, { version: song.version, currentVersion: song.version });
            // A course clip's take (no song, its brief instead), when the tab asks for one (the flows).
            if (tabStorage.get('stackapella.mockClipTake') === '1') {
                const clip = (await example('admin.beta.clips.list.200.json')).data[0];
                const { status, assignee, published, retired, ...brief } = clip;
                Object.assign(state.submission, { kind: 'clip', template: null, partKey: null, clip: brief, scores: { ...state.submission.scores, pitchPercent: null, timingPercent: null } });
            }
        }
        const take = state.submission;
        if (params.id && params.id !== take.id) return respond(404, { message: 'not_found', code: 'not_found' });
        const decidable = ['submitted', 'in_review'].includes(take.status);
        if (operation === 'admin.beta.submissions.list') {
            const shown = !query.status || (query.status === 'waiting' ? decidable : take.status === query.status);
            return respond(200, { data: shown && !query.cursor ? [take] : [], meta: { nextCursor: null } });
        }
        if (operation === 'admin.beta.submissions.show') {
            const detail = await example('admin.beta.submissions.show.200.json');
            return respond(200, {
                ...detail,
                data: take,
                master: { url: `${location.origin}/tests/fixtures/guide-48000.wav`, expiresAt: detail.master.expiresAt },
                otherParts: detail.otherParts.map((p) => ({ ...p, url: `${location.origin}/tests/fixtures/guide-44100.m4a?part=${p.partKey}`, sampleRate: 44100, frames: 132300, offsetFrames: 0 })),
            });
        }
        if (operation === 'admin.beta.submissions.open') {
            if (take.status === 'submitted') take.status = 'in_review';
            return respond(200, { data: take });
        }
        if (operation === 'admin.beta.submissions.adjust') {
            if (!decidable && take.status !== 'accepted') return caseFor('not_reviewable');
            Object.assign(take.adjust, body);
            return respond(200, { data: take });
        }
        if (operation === 'admin.beta.submissions.publish') {
            if (take.status !== 'accepted') return caseFor('not_publishable');
            take.publish = 'pending';
            return respond(200, { data: take });
        }
        if (!decidable) return caseFor('not_reviewable');
        if (operation === 'admin.beta.submissions.accept') Object.assign(take, { status: 'accepted', publish: 'pending', reviewedAt: now });
        if (operation === 'admin.beta.submissions.retake') {
            if (!body?.note?.trim()) return caseFor('validation_failed');
            Object.assign(take, { status: 'retake', adminNote: body.note, adminNoteBeat: body.beat ?? null, reviewedAt: now });
        }
        if (operation === 'admin.beta.submissions.reject') Object.assign(take, { status: 'rejected', adminNote: body?.note ?? null, reviewedAt: now });
        return respond(200, { data: take });
    }
    if (operation.startsWith('admin.flags.')) {
        state.flags ??= (await example('admin.flags.list.200.json')).data;
        if (operation === 'admin.flags.list') return respond(200, { data: state.flags });
        const flag = state.flags.find((f) => f.name === params.name);
        if (!flag) return caseFor('not_found');
        if (!flag.values.includes(body?.value) || !body?.reason?.trim()) return caseFor('validation_failed');
        Object.assign(flag, { value: body.value, reason: body.reason, changedAt: now, changedBy: ME });
        return respond(200, { data: flag });
    }
    if (operation.startsWith('admin.limits.')) {
        state.limits ??= (await example('admin.limits.list.200.json')).data;
        if (operation === 'admin.limits.list') return respond(200, { data: state.limits });
        const limit = state.limits.find((l) => l.limiter === params.limiter && l.rule === params.rule);
        if (!limit) return caseFor('not_found');
        const max = body?.max;
        if ((max !== null && !(Number.isInteger(max) && max >= 1 && max <= 100000)) || !body?.reason?.trim()) return caseFor('validation_failed');
        Object.assign(limit, { override: max, max: max ?? limit.default, reason: max === null ? null : body.reason, changedAt: max === null ? null : now, changedBy: max === null ? null : ME });
        return respond(200, { data: limit });
    }
    if (operation.startsWith('admin.blocklist.')) {
        state.blocks ??= (await example('admin.blocklist.list.200.json')).data;
        if (operation === 'admin.blocklist.list') return respond(200, { data: query.type ? state.blocks.filter((b) => b.type === query.type) : state.blocks, meta: { nextCursor: null } });
        if (operation === 'admin.blocklist.create') {
            const types = ['email', 'email_domain', 'ip', 'ip_range', 'card_fingerprint'];
            // Stored as the server stores it: trimmed, and lower-cased for emails and domains.
            const value = ['email', 'email_domain'].includes(body?.type) ? body?.value?.trim().toLowerCase() : body?.value?.trim();
            if (!types.includes(body?.type) || !value || !body?.reason?.trim() || state.blocks.some((b) => b.type === body.type && b.value === value)) {
                return caseFor('validation_failed');
            }
            const block = { id: Math.random().toString(36).slice(2, 8), type: body.type, value, reason: body.reason, expiresAt: body.expiresAt ?? null, createdAt: now, createdBy: ME };
            state.blocks.unshift(block);
            return respond(201, { data: block });
        }
        const index = state.blocks.findIndex((b) => b.id === params.id);
        if (index < 0) return caseFor('not_found');
        state.blocks.splice(index, 1);
        return respond(204, null);
    }
    return null;
}
