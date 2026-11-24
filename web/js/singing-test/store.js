// The singing test's history (docs/singing-test-v1.md): numbers only, never audio. A guest's tests are kept on this
// device; with an account each is sent once (singingTests.create, idempotent by its id) and the server's score is the one
// kept, as it re-scores the whole history when its scoringVersion moves. Tests compare only within one testVersion.

import { reactive } from 'vue';
import { api } from '../api.js';
import { account } from '../stores/account.js';
import { local } from '../storage.js';
import { inFirstWeek } from '../components/voice-care.js';
import { compare, result } from './score.js';

const KEY = 'stackapella.singingTests';

/** `tests`: newest first, each the create body plus `{ status, scores, total, band, synced }`. */
export const singingTests = reactive({ tests: readKept(), loaded: false });

function readKept() {
    try {
        const kept = JSON.parse(local.get(KEY) || '[]');
        return Array.isArray(kept) ? kept : [];
    } catch {
        return [];
    }
}

function keep() {
    local.set(KEY, JSON.stringify(singingTests.tests));
}

/** What the switch and the first week are read from: the server's switches and the course progress, loaded. */
export async function readyToDecide() {
    const [{ loadAuthConfig }, { loadLearning }] = await Promise.all([import('../stores/account.js'), import('../stores/learning.js')]);
    await Promise.all([loadAuthConfig().catch(() => null), loadLearning()?.catch?.(() => null)]);
}

/** The singing test is open to them: its switch (auth.config's singingTest), and past their first week. */
export function singingTestOn() {
    return account.config?.singingTest === true && !inFirstWeek();
}

/** A finished test, scored here first (the server's score replaces it once it answers). */
export function keepTest(body) {
    const own = result(body);
    const test = { ...body, ...own, synced: false };
    singingTests.tests = [test, ...singingTests.tests.filter((t) => t.id !== body.id)];
    keep();
    return test;
}

/** The server's answer for a test: its scores are the ones kept. */
function adopt(server) {
    singingTests.tests = singingTests.tests.map((t) => (t.id === server.id ? { ...t, ...server, synced: true } : t));
    keep();
}

/** Sends every test not yet on the account (a guest's, once they have one): each once, by its id. */
export async function syncTests() {
    if (!account.me || account.config?.singingTest !== true) return;
    for (const test of singingTests.tests.filter((t) => !t.synced)) {
        const { id, testVersion, scoringVersion, takenAt, anchor, device, warmUp, match, back, hold, score } = test;
        try {
            const { data } = await api.singingTests.create({ id, testVersion, scoringVersion, takenAt, anchor, device, warmUp, match, back, hold, score });
            adopt(data);
        } catch (error) {
            console.info('A singing test was not sent yet', error?.code ?? error?.message ?? error);
        }
    }
}

/** The account's history from the server (re-scored at today's scoringVersion), merged with what's only here. */
export async function loadTests() {
    if (!account.me || account.config?.singingTest !== true) {
        singingTests.loaded = true;
        return;
    }
    try {
        const { data } = await api.singingTests.list();
        const theirs = new Map(data.map((t) => [t.id, { ...t, synced: true }]));
        const onlyHere = singingTests.tests.filter((t) => !theirs.has(t.id));
        singingTests.tests = [...onlyHere, ...theirs.values()].sort((a, b) => Date.parse(b.takenAt) - Date.parse(a.takenAt));
        keep();
    } catch (error) {
        console.info('The singing tests were not loaded', error?.code ?? error?.message ?? error);
    } finally {
        singingTests.loaded = true;
    }
}

/** The first counted test can be marked a warm-up afterwards (and counted again); no later one. */
export function canMarkWarmUp(test) {
    const counted = [...singingTests.tests].reverse().filter((t) => t.status === 'counted' || t.status === 'warmup');
    return counted[0]?.id === test.id;
}

export async function markWarmUp(test, warmUp) {
    if (account.me && test.synced) {
        const { data } = await api.singingTests.update(test.id, { warmUp });
        adopt(data);
        return;
    }
    singingTests.tests = singingTests.tests.map((t) => (t.id === test.id ? { ...t, warmUp, status: warmUp ? 'warmup' : 'counted', synced: false } : t));
    keep();
}

/** Earlier tests (oldest first) for the anchor's rule: each with its anchor and the note found that day. */
export const anchorHistory = () => [...singingTests.tests].reverse().map((t) => ({ testVersion: t.testVersion, anchor: t.anchor, found: t.found ?? t.anchor }));

/** The latest counted test before `test`, in its test version: what "improved" compares against. */
export function previousCounted(test) {
    return singingTests.tests.find((t) => t.id !== test.id && t.status === 'counted' && t.testVersion === test.testVersion && Date.parse(t.takenAt) < Date.parse(test.takenAt)) ?? null;
}

/** "improved", "same" or null (nothing to compare, or across a version seam). */
export function progressOf(test) {
    if (test.status !== 'counted') return null;
    return compare(previousCounted(test), test);
}
