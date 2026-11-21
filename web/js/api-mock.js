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

export { mockWanted } from './api-mode.js';

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
    // A library song's page: the song itself (its own template file), so what it plays matches the catalogue's
    // version and real voices; any other id, the example (Silent Night).
    if (operation === 'templates.show' && /^[a-z0-9-]+$/.test(params?.id ?? '')) {
        // Only a song on disk (web/templates/index.json lists the free ones): asking for any other would be a 404.
        const onDisk = (await fetch('/templates/index.json').then((r) => (r.ok ? r.json() : null)).catch(() => null))?.templates ?? [];
        const file = onDisk.includes(params.id) ? await fetch(`/templates/${params.id}.json`).catch(() => null) : null;
        // Only a real JSON answer: the local server gives a missing file the app's page, with a 200 (a library song
        // that isn't among the free ones on disk, like silent-night, then stays the example as before).
        const template = file?.ok && (file.headers.get('content-type') ?? '').includes('json') ? await file.json().catch(() => null) : null;
        if (template) {
            const { data } = await example('templates.show.200.json');
            return respond(200, { data: { ...data, template } });
        }
        // An open library song as the server sends it (stackapella.mockOpenSong, this tab): reviewed, to a guest as well
        // (`grant: "open"`: a guest sings its first line, the app's cut, before the account's invitation).
        if (tabStorage.get('stackapella.mockOpenSong') === params.id) {
            const { data } = await example('templates.show.200.json');
            const as = tabStorage.get('stackapella.mockAs') || 'member';
            return respond(200, { data: { ...data, grant: as === 'signedOut' ? 'open' : 'account', template: { ...data.template, id: params.id, reviewed: true } } });
        }
        // With AI voices on for the tab (stackapella.mockAiVoices): the example song (Silent Night) at the catalogue's
        // version, as the server answers it, so its AI guides apply (a guide plays only on its own version).
        if (tabStorage.get('stackapella.mockAiVoices') === '1' && !query?.version && (tabStorage.get('stackapella.mockAs') || 'member') !== 'signedOut' && !scenarios().some((s) => s.startsWith('templates.show:'))) {
            const { data } = await example('templates.show.200.json');
            const listed = (await example('templates.catalogue.200.json')).data.find((song) => song.id === data.template.id);
            if (params.id === data.template.id && listed) return respond(200, { data: { ...data, template: { ...data.template, version: listed.version } } });
        }
        // Any other library song to a guest: its first phrase (`grant: "phrase"`, as the server cuts it: templates.firstPhrase),
        // no recorded voices; a scenario (templates.show:account_required) still answers as it says.
        if ((tabStorage.get('stackapella.mockAs') || 'member') === 'signedOut' && !scenarios().some((s) => s.startsWith('templates.show:'))) {
            const { data } = await example('templates.show.200.json');
            const { firstPhrase } = await import('./first-phrase.js');
            return respond(200, { data: { ...data, grant: 'phrase', voices: [], boards: false, freeUntil: null, template: firstPhrase({ ...data.template, id: params.id }) } });
        }
    }
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
        // Only the experiments a flow asks for (stackapella.mockExperiments, this tab): the example's running
        // one would draw an arm at random on every mock page, so no flow could know which lesson it gets.
        const experiments = JSON.parse(tabStorage.get('stackapella.mockExperiments') || '[]');
        // Lend your voice switched on for a flow (stackapella.mockLendVoice, this tab).
        const lendVoice = tabStorage.get('stackapella.mockLendVoice') === '1' || !!data.lendVoice;
        // A self-hosted install, for this tab (`stackapella.mockSelfHosted`): the licence's credit shows.
        const selfHosted = tabStorage.get('stackapella.mockSelfHosted') === '1' || !!data.selfHosted;
        // Songs withheld where the visitor is, for a flow (stackapella.mockWithheld, this tab): js/withheld.js swaps them.
        const withheldSongs = JSON.parse(tabStorage.get('stackapella.mockWithheld') || 'null') ?? data.withheldSongs ?? [];
        // AI guide voices switched on for a flow (stackapella.mockAiVoices, this tab): the catalogue's AI guides then play.
        const aiVoices = tabStorage.get('stackapella.mockAiVoices') === '1' || !!data.aiVoices;
        // Hear yourself as a choir, on for the tab (stackapella.mockOwnChoir): its own state below.
        const ownChoir = tabStorage.get('stackapella.mockOwnChoir') === '1' || !!data.ownChoir;
        const ownChoirSongs = tabStorage.get('stackapella.mockOwnChoirSongs') === '1' || !!data.ownChoirSongs; // their own songs too
        // Shield mode's site key, for a flow (stackapella.mockTurnstile, this tab): the flow stands in for Cloudflare's script.
        const turnstileSiteKey = tabStorage.get('stackapella.mockTurnstile') === '1' ? '1x00000000000000000000AA' : data.turnstileSiteKey ?? null;
        return respond(200, { data: { ...data, ideas: !scenarios().includes('ideas.list:not_found'), experiments, lendVoice, selfHosted, withheldSongs, aiVoices, ownChoir, ownChoirSongs, turnstileSiteKey } });
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
        // Fields a flow sets for this tab (`stackapella.mockAccount`, JSON: createdAt, isAdult and the like).
        Object.assign(data, JSON.parse(tabStorage.get('stackapella.mockAccount') || '{}'));
        // `plus` and `singerPlus` are on Plus; `singer` and `singerPlus` are beta singers; `editor` is
        // staff without the admin gate (`admin` is true only for `admin`, as the server says it).
        return { data: { ...data, appearance, betaSinger: who === 'singer' || who === 'singerPlus', plan: who === 'plus' || who === 'singerPlus' ? 'plus' : data.plan, staff: who === 'admin' ? 'admin' : who === 'editor' ? 'editor' : null, admin: who === 'admin' } };
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
    if (operation === 'account.show') {
        if (as === 'signedOut') return signedOut();
        // An email not confirmed yet, for a flow (stackapella.mockUnverified, this tab).
        const me = await accountAs(as);
        if (tabStorage.get('stackapella.mockUnverified') === '1') me.data = { ...me.data, emailVerified: false };
        return respond(200, me);
    }
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
    // A choir group as one of its members sees it, for a tab that asks (`stackapella.mockChoirMember`, the
    // groups flow; anyone but `plus`, who directs it): the examples' Kim Ode is the signed-in account, with
    // no link to copy. Other tabs keep the example, so Home has no parts to learn for every mock account.
    if (operation === 'groups.show' && tabStorage.get('stackapella.mockChoirMember') === '1' && as !== 'plus' && as !== 'signedOut' && !scenarios().some((s) => s.startsWith(operation + ':'))) {
        const json = await example(`${operation}.200.json`);
        const me = (await example('account.show.200.json')).data.id;
        json.data = { ...json.data, role: 'member', link: null, members: json.data.members.map((m) => (m.name === 'Kim Ode' ? { ...m, id: me } : m)) };
        return respond(200, json);
    }
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
        // A volunteer (Lend your voice: stackapella.mockVolunteer, set once voices.join answers) is a beta singer too.
        if (operation === 'beta.me.show' && tabStorage.get('stackapella.mockVolunteer') === '1') return respond(200, { data: { ...(await fresh('beta.me.show.200.json')).data, volunteer: true } });
        if (as !== 'singer' && as !== 'singerPlus') return respond(403, { message: 'not_beta_singer', code: 'not_beta_singer' });
        if (operation === 'beta.me.show') return respond(200, await fresh('beta.me.show.200.json'));
        if (operation === 'beta.consent.create') tabStorage.set('stackapella.mockFresh', '');
    }
    // Hear yourself as a choir (track B): its terms from a fixture, and its state kept for the tab. `stackapella.mockOwnChoir`
    // turns it on; `stackapella.mockChoirState` `fresh` starts with no consent and no songs, else the example's. A new song
    // goes uploading, queued on complete, then ready at the next look (as if the render ran in between).
    if (operation === 'beta.agreement.show' && query.for === 'own_choir') {
        const terms = await fetch('/tests/fixtures/ai-voice-licence.json').then((r) => r.json());
        const { termsVersion } = (await example('ownChoir.show.200.json')).data; // the version the choir's state names
        return respond(200, { data: { ...terms.data, version: termsVersion, title: 'Hear yourself as a choir: the terms' } }); // i18n-ignore: a fixture's title
    }
    if (operation.startsWith('ownChoir.') && !scenarios().some((s) => s.startsWith(operation + ':'))) {
        if (tabStorage.get('stackapella.mockOwnChoir') !== '1') return respond(404, { message: 'not_found', code: 'not_found' });
        const shown = (await example('ownChoir.show.200.json')).data;
        const local = (p) => ({ ...p, url: `${location.origin}/tests/fixtures/guide-48000.m4a?choir=${p.id}`, sampleRate: 48000, frames: 144000, offsetFrames: 0 });
        // Kept in the tab (a page load keeps it), saved after each change.
        state.ownChoir ??= JSON.parse(tabStorage.get('stackapella.mockChoir') || 'null') ?? (tabStorage.get('stackapella.mockChoirState') === 'fresh'
            ? { ...shown, consent: null, voice: null, requests: [], quota: { ...shown.quota, freeLeft: 1, plus: false } }
            : { ...shown, requests: shown.requests.map((r) => ({ ...r, parts: r.parts.map(local) })) });
        const choir = state.ownChoir;
        // Download is Plus's (the server decides; Hillel, 2026-11-21): here, the Plus account (`as=plus`) only.
        for (const r of choir.requests) r.canDownload = as === 'plus';
        // Plus counts singing minutes (60 part-minutes a month here, 47 left); today's ceiling and the first song's check
        // are a flow's to turn on (stackapella.mockChoirBusy, stackapella.mockChoirCheck).
        const monthEnd = new Date(Date.UTC(new Date().getUTCFullYear(), new Date().getUTCMonth() + 1, 1)).toISOString().replace(/\.\d+Z$/, 'Z');
        if (as === 'plus') choir.quota = { ...choir.quota, plus: true, freeLeft: 0, unit: 'minutes', minutesLeft: 47, minutesPerMonth: 60, resetsAt: monthEnd };
        else choir.quota = { ...choir.quota, unit: 'songs', minutesLeft: null, minutesPerMonth: null, resetsAt: monthEnd };
        // The free song already had (once per email, ever), for a flow (stackapella.mockChoirFreeUsed, this tab).
        if (as !== 'plus' && tabStorage.get('stackapella.mockChoirFreeUsed') === '1') choir.quota = { ...choir.quota, plus: false, freeLeft: 0 };
        choir.busyToday = tabStorage.get('stackapella.mockChoirBusy') === '1';
        choir.checkNeeded = tabStorage.get('stackapella.mockChoirCheck') === '1' && !choir.requests.length;
        const unverified = tabStorage.get('stackapella.mockUnverified') === '1';
        const save = (answer) => {
            tabStorage.set('stackapella.mockChoir', JSON.stringify(state.ownChoir));
            return answer;
        };
        if (operation === 'ownChoir.show') {
            for (const r of choir.requests) {
                if (r.status !== 'queued' || r.startsAfter) continue; // tomorrow's (today's ceiling) stays queued
                Object.assign(r, { status: 'ready', readyAt: now, parts: shown.requests[0].parts.map((p) => local({ ...p, id: `${p.id}-${r.id}` })) });
                // The first song made from a clip leaves its voice kept for the next ones (the contract's `voice`).
                choir.voice ??= { savedAt: now, lastUsedAt: now, expiresAt: now };
            }
            return save(respond(200, { data: choir }));
        }
        if (operation === 'ownChoir.consent') {
            if (body?.version !== choir.termsVersion) return caseFor('agreement_changed');
            choir.consent = { version: body.version, acceptedAt: now };
            return save(respond(200, { data: choir }));
        }
        if (operation === 'ownChoir.voice.delete') {
            if (!choir.voice) return caseFor('not_found');
            choir.voice = null;
            return save(respond(204, null));
        }
        if (operation === 'ownChoir.delete') {
            state.ownChoir = { ...choir, consent: null, voice: null, requests: [] };
            return save(respond(204, null));
        }
        if (operation === 'ownChoir.requests.create') {
            if (!choir.consent) return caseFor('consent_required');
            if (!body?.sha256 && !choir.voice) return caseFor('voice_required');
            if (choir.requests.some((r) => ['uploading', 'queued', 'rendering'].includes(r.status))) return caseFor('request_open');
            if (unverified && !choir.quota.plus && choir.quota.freeLeft > 0) return respond(403, { message: 'email_unverified', code: 'email_unverified' });
            if (choir.checkNeeded && !body?.turnstileToken) return respond(403, { message: 'turnstile_required', code: 'turnstile_required' });
            const listed = (await example('templates.catalogue.200.json')).data.find((song) => song.id === body.templateId);
            const request = { id: `Rq${choir.requests.length + 7}x`, templateId: body.templateId, title: listed?.title ?? body.templateId, version: listed?.version ?? 1, status: 'uploading', failure: null, createdAt: now, readyAt: null, seen: false, canDownload: as === 'plus', startsAfter: choir.busyToday ? new Date(Date.now() + 864e5).toISOString().replace(/\.\d+Z$/, 'Z') : null, parts: [] };
            choir.requests.unshift(request);
            globalThis.mockChoirClip = body; // what was sent, for the flows
            // From the saved voice ({ templateId } alone): queued at once, nothing to upload.
            if (!body?.sha256) {
                request.status = 'queued';
                return save(respond(201, { data: request, upload: null }));
            }
            return save(respond(201, { data: request, upload: { url: `${location.origin}/dev/upload/choir`, method: 'PUT', headers: { 'Content-Type': 'audio/wav' }, expiresAt: now } }));
        }
        const request = choir.requests.find((r) => r.id === params.id);
        if (!request) return caseFor('not_found');
        if (operation === 'ownChoir.requests.complete') {
            if (request.status === 'uploading') request.status = 'queued';
            return save(respond(200, { data: request }));
        }
        if (operation === 'ownChoir.requests.download') {
            if (!request.canDownload) return respond(403, { message: 'plus_required', code: 'plus_required' });
            globalThis.mockChoirDownloads = (globalThis.mockChoirDownloads ?? 0) + 1; // what the flows count
            return respond(200, { data: { parts: request.parts.map((p) => ({ partKey: p.partKey, url: p.url, sampleRate: p.sampleRate, frames: p.frames, offsetFrames: p.offsetFrames })) } });
        }
        if (operation === 'ownChoir.requests.seen') {
            request.seen = true;
            return save(respond(200, { data: request }));
        }
        if (operation === 'ownChoir.requests.delete') {
            choir.requests = choir.requests.filter((r) => r !== request);
            return save(respond(204, null));
        }
    }
    // The AI voice licence (phase 1b), kept for the tab: `stackapella.mockAiLicence` starts it `none` (offered, not
    // taken) or `off` (not offered); otherwise the example's (accepted). Its terms come from a test fixture.
    if (operation === 'beta.agreement.show' && query.for === 'ai_voice') {
        return respond(200, await fetch('/tests/fixtures/ai-voice-licence.json').then((r) => r.json()));
    }
    if (operation.startsWith('beta.aiLicence.') && !scenarios().some((s) => s.startsWith(operation + ':'))) {
        const shown = (await example('beta.aiLicence.show.200.json')).data;
        const start = tabStorage.get('stackapella.mockAiLicence');
        state.aiLicence ??= start === 'none' || start === 'off' ? { offered: start === 'none', version: shown.version, accepted: null, withdrawnAt: null, voice: null } : shown;
        const licence = state.aiLicence;
        if (operation === 'beta.aiLicence.accept') {
            if (!licence.offered) return caseFor('not_offered');
            if (body?.version !== licence.version) return caseFor('agreement_changed');
            state.aiLicence = { ...licence, accepted: { version: body.version, acceptedAt: now, credited: !!body.credited }, withdrawnAt: null, voice: licence.voice ?? shown.voice };
        } else if (operation === 'beta.aiLicence.update') {
            if (!licence.accepted) return caseFor('not_accepted');
            state.aiLicence = { ...licence, accepted: { ...licence.accepted, credited: !!body?.credited } };
        } else if (operation === 'beta.aiLicence.withdraw') {
            if (!licence.accepted) return caseFor('not_accepted');
            state.aiLicence = { ...licence, accepted: null, withdrawnAt: now, voice: null };
            return respond(200, { data: state.aiLicence, meta: { retired: licence.voice?.guides?.length ?? 0 } });
        }
        return respond(200, { data: state.aiLicence });
    }
    if (operation === 'voices.join' && !scenarios().some((s) => s.startsWith('voices.join:'))) tabStorage.set('stackapella.mockVolunteer', '1');
    // The AI review (phase 1c): the example's parts, their decisions kept for the tab's state, filtered by ?status=.
    if (operation.startsWith('admin.aiGuides.') && !scenarios().some((s) => s.startsWith(operation + ':'))) {
        // Their audio is on a CDN the mock can't reach: the local 3-second fixture, as the catalogue's real voices are.
        state.aiReview ??= (await example('admin.aiGuides.list.200.json')).data.map((song) => ({ ...song, parts: song.parts.map((p) => (p.recording ? { ...p, recording: { ...p.recording, url: `${location.origin}/tests/fixtures/guide-48000.m4a?ai=${p.id}`, sampleRate: 48000, frames: 144000, offsetFrames: 0 } } : p)) }));
        const songs = state.aiReview;
        const all = songs.flatMap((song) => song.parts.map((part) => ({ song, part })));
        if (operation === 'admin.aiGuides.list') {
            const wanted = query.status || 'pending';
            const counts = { pending: 0, approved: 0, rejected: 0 };
            all.forEach(({ part }) => counts[part.status]++);
            const data = songs.map((song) => ({ ...song, parts: song.parts.filter((p) => p.status === wanted) })).filter((song) => song.parts.length);
            return respond(200, { data, meta: { counts } });
        }
        const chosen = all.filter(({ part }) => (body?.ids ?? []).includes(part.id));
        if (operation === 'admin.aiGuides.reject') {
            if (!body?.note?.trim()) return respond(422, { message: 'The note field is required.', code: 'validation_failed', errors: { note: ['The note field is required.'] } }); // i18n-ignore: the server's words
            const changed = chosen.filter(({ part }) => part.status !== 'rejected');
            changed.forEach(({ part }) => Object.assign(part, { status: 'rejected', note: body.note, recording: null, reviewedAt: now, reviewedBy: { id: ME.id, name: ME.name } }));
            return respond(200, { data: { rejected: changed.length } });
        }
        const changed = chosen.filter(({ part }) => part.status === 'pending');
        changed.forEach(({ part }) => Object.assign(part, { status: 'approved', reviewedAt: now, reviewedBy: { id: ME.id, name: ME.name } }));
        return respond(200, { data: { approved: changed.length } });
    }
    // Admin's AI voices: a withdrawn voice stays withdrawn for the tab's kept state, its guides gone, as on the server.
    if (operation === 'admin.aiVoices.list' && !scenarios().some((s) => s.startsWith(operation + ':'))) {
        const { data } = await example('admin.aiVoices.list.200.json');
        state.aiWithdrawn ??= {};
        return respond(200, { data: data.map((v) => (state.aiWithdrawn[v.id] ? { ...v, status: 'withdrawn', withdrawnAt: state.aiWithdrawn[v.id], guides: [] } : v)) });
    }
    if (operation === 'admin.aiVoices.withdraw' && !scenarios().some((s) => s.startsWith(operation + ':'))) {
        const { data } = await example('admin.aiVoices.list.200.json');
        const voice = data.find((v) => v.id === params.id);
        state.aiWithdrawn ??= {};
        if (!voice) return caseFor('not_found');
        if (state.aiWithdrawn[voice.id] || voice.status === 'withdrawn') return caseFor('already_withdrawn');
        state.aiWithdrawn[voice.id] = now;
        return respond(200, { data: { ...voice, status: 'withdrawn', withdrawnAt: now, guides: [] }, meta: { retired: voice.guides.length } });
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
