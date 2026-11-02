// Experiments in the app (docs/experiments-plan.md, section 2; contract: auth.config's
// `experiments`). experiment(id) says this device's arm ('a', 'b', ...) or null, and whether it's
// ready to say. An arm is drawn the first time the screen that differs asks (at exposure, so the
// counts compare only devices that saw it), at random by the arms' weights, and kept here
// (stackapella.experiments: an arm label per experiment, never an id, a time or a seed). A device
// outside an experiment's audience, in a new singer's first week (unless the experiment allows it),
// in one it excludes, or already in four, is kept out, and stays out. One no longer running is
// dropped on the next load. ?x=<id>-<arm> previews an arm in this tab, never counted.
//
// No flicker: the last config's experiments are kept too, so a returning device draws at once. A
// brand-new device waits (ready false) until auth.config arrives, at most 1.5 seconds; past that it
// sees the control, unenrolled, for this visit, so a slow network never tilts an arm.

import { reactive, watch } from 'vue';
import { account } from './stores/account.js';
import { learning } from './stores/learning.js';
import { local, session as tabStorage } from './storage.js';

const KEPT = 'stackapella.experiments'; // { "<id>": "<arm>" | "-" (kept out) }
const CONFIG = 'stackapella.experimentsConfig'; // the last auth.config's running experiments
const PREVIEW = 'stackapella.experimentPreview'; // "<id>-<arm>", this tab only
const LATE = 'stackapella.experimentsLate'; // ids shown the control this visit, the config being late
const WAIT_MS = 1500; // a new device waits at most this long for the config (a page timer, never audio)
const MOST = 4; // experiments one device is in at once
const OUT = '-';
const WEEK = 7 * 86400000;
const PHONE_WIDTH = 640;

const state = reactive({ late: false });
setTimeout(() => (state.late = true), WAIT_MS);

function readJson(key, store = local) {
    try {
        return JSON.parse(store.get(key) || 'null');
    } catch (error) {
        console.info('An experiments record was unreadable', error?.message ?? error);
        return null;
    }
}

/** Whether a device with these `facts` may enrol in an experiment with this `audience` (and firstWeek). */
export function audienceFits(audience = {}, facts) {
    if (facts.firstWeek && !audience.firstWeek) return false;
    if (audience.new && !facts.isNew) return false;
    if (audience.skin && audience.skin !== facts.skin) return false;
    if (audience.size && audience.size !== facts.size) return false;
    if (typeof audience.signedIn === 'boolean' && audience.signedIn !== facts.signedIn) return false;
    return true;
}

/** An arm by the arms' weights, from `random` in [0, 1). */
export function pickArm(arms, random) {
    const total = arms.reduce((sum, a) => sum + Math.max(0, a.weight ?? 1), 0);
    let left = random * total;
    for (const a of arms) {
        left -= Math.max(0, a.weight ?? 1);
        if (left < 0) return a.arm;
    }
    return arms.at(-1)?.arm ?? null;
}

/**
 * This device's arm in `experiment` ({ id, arms, enrol, audience, excludes }): the one kept, else a
 * new draw (or OUT) when it enrols. Returns { arm, keep } (keep: what to store, or null for nothing).
 */
export function decide(experiment, { kept, facts, random }) {
    const had = kept[experiment.id];
    if (had) return { arm: had === OUT ? null : had, keep: null };
    if (!experiment.enrol) return { arm: null, keep: null };
    const inside = Object.values(kept).filter((arm) => arm && arm !== OUT).length;
    const excluded = (experiment.excludes ?? []).some((id) => kept[id] && kept[id] !== OUT);
    if (inside >= MOST || excluded || !audienceFits(experiment.audience, facts)) return { arm: null, keep: OUT };
    const arm = pickArm(experiment.arms ?? [], random);
    return { arm, keep: arm ?? OUT };
}

/** What this device knows of itself, for the audiences. */
function facts() {
    const started = learning.progress?.startedAt ?? null;
    return {
        isNew: !started && !account.me,
        firstWeek: !started || Date.now() - started < WEEK,
        skin: document.documentElement.dataset.skin === 'serious' ? 'serious' : 'game',
        size: innerWidth < PHONE_WIDTH ? 'phone' : 'desktop',
        signedIn: !!account.me,
    };
}

function random() {
    return crypto.getRandomValues(new Uint32Array(1))[0] / 2 ** 32;
}

/** The running experiments: the server's, else the last ones it sent (null while neither is known). */
function running() {
    return account.config ? account.config.experiments ?? [] : readJson(CONFIG);
}

/**
 * This device's arm in experiment `id`: { arm, ready, preview }. Call it where the screen that
 * differs renders; while `ready` is false, show that screen's own loading state.
 */
export function experiment(id) {
    const preview = tabStorage.get(PREVIEW);
    if (preview?.startsWith(`${id}-`)) return { arm: preview.slice(id.length + 1), ready: true, preview: true };
    const list = running();
    if (!list) return state.late ? lateFor(id) : { arm: null, ready: false, preview: false };
    const found = list.find((e) => e.id === id);
    if (!found) return { arm: null, ready: true, preview: false };
    if ((readJson(LATE, tabStorage) ?? []).includes(id)) return { arm: null, ready: true, preview: false };
    const kept = readJson(KEPT) ?? {};
    const { arm, keep } = decide(found, { kept, facts: facts(), random: random() });
    if (keep) {
        local.set(KEPT, JSON.stringify({ ...kept, [id]: keep }));
        // Drawn into an arm: its denominator, counted once (js/usage.js; loaded late, as usage needs this module).
        if (keep !== OUT) import('./usage.js').then((usage) => usage.countEnrol(`${id}-${keep}`), (error) => console.info('Enrolment not counted', error?.message ?? error));
    }
    return { arm, ready: true, preview: false };
}

/** The config was too slow: the control for this visit, and not enrolled (nothing kept, nothing counted). */
function lateFor(id) {
    const late = readJson(LATE, tabStorage) ?? [];
    if (!late.includes(id)) tabStorage.set(LATE, JSON.stringify([...late, id]));
    return { arm: null, ready: true, preview: false };
}

/** The arms this device is in, for counts.create's `variants` ("<id>-<arm>"): seen and running, never a preview. */
export function enrolledVariants() {
    const list = running() ?? [];
    const kept = readJson(KEPT) ?? {};
    return list
        .filter((e) => kept[e.id] && kept[e.id] !== OUT)
        .map((e) => `${e.id}-${kept[e.id]}`)
        .slice(0, MOST);
}

/**
 * A lesson as this device sees it: its `variants` ({ "<id>-<arm>": { steps, minutes? } }, written in
 * the course) swapped in for the arm this device is in, else the lesson as written. `ready` is false
 * while an experiment it varies can't say yet.
 */
export function lessonFor(lesson) {
    if (!lesson?.variants) return { lesson, ready: true };
    for (const key of Object.keys(lesson.variants)) {
        const cut = key.lastIndexOf('-');
        const { arm, ready } = experiment(key.slice(0, cut));
        if (!ready) return { lesson, ready: false };
        if (arm === key.slice(cut + 1)) return { lesson: { ...lesson, ...lesson.variants[key], variants: undefined }, ready: true };
    }
    return { lesson, ready: true };
}

// The server's list, kept for the next visit; an experiment it no longer runs is dropped from here.
watch(
    () => account.config?.experiments,
    (list) => {
        if (!Array.isArray(list)) return;
        local.set(CONFIG, JSON.stringify(list.map(({ id, arms, enrol, audience, excludes }) => ({ id, arms, enrol, audience, excludes }))));
        const kept = readJson(KEPT) ?? {};
        const ids = new Set(list.map((e) => e.id));
        const still = Object.fromEntries(Object.entries(kept).filter(([id]) => ids.has(id)));
        if (Object.keys(still).length !== Object.keys(kept).length) local.set(KEPT, JSON.stringify(still));
    },
    { immediate: true },
);

// ?x=<id>-<arm>: a preview for this tab (docs/experiments-plan.md), never counted.
const asked = new URLSearchParams(location.search).get('x');
if (asked && /^[a-z0-9-]{3,48}$/.test(asked)) tabStorage.set(PREVIEW, asked);
