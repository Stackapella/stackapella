// Anonymous usage counts (decision 2026-10-28, "anonymous usage counts"; contract: counts.create):
// a few moments are counted, guests included, and sent as a batch of daily totals to our own
// server: each kind and how many, and whether someone was signed in. Nothing else: no ID and no
// device details. The onboarding funnel (js/funnel.js; Hillel's "Yes") adds its firsts and asks,
// kept on the device only to count each once, a variant ("onb1-b") that half of all devices share,
// and the ?ref= tag the device arrived with (a source many share, never an identifier). A batch goes when the browser is idle, and when the page is hidden or closed
// (sendBeacon); offline or refused, it's simply dropped. Off when the server
// says so (auth.config.usageCounts: a self-hosted install, unless its owner turned it on).

import { OPERATIONS } from './api.js';
import { mockWanted } from './api-mock.js';
import { account, ref } from './stores/account.js';
import { variant as funnelVariant } from './funnel.js';

// Sign-ups are counted by the server itself (every way in, web and apps), so the app never sends one.
export const KINDS = new Set(['app_open', 'lesson_start', 'lesson_done', 'unit_done', 'song_created', 'take_recorded', 'share_created', 'harmonise_used', 'export_done', 'result_shared']);
// The funnel's steps and asks (docs/onboarding-review.md, section 4), each counted once per device by js/funnel.js.
const FUNNEL = ['f_arrive', 'f_arrive_start', 'f_mic', 'f_note', 'f_note_skip', 'f_lesson1', 'f_chord', 'f_lesson2', 'f_lesson3', 'f_unit1', 'f_goal', 'f_install', 'f_name', 'f_account', 'f_share', 'f_remind', 'f_take', 'f_harmony', 'f_day2', 'f_week1', 'f_goal_met'];
const ASKED = ['install', 'account', 'name', 'share', 'goal', 'remind'].flatMap((ask) => [`ask_${ask}_shown`, `ask_${ask}_yes`]);
for (const kind of [...FUNNEL, ...ASKED]) KINDS.add(kind);
// What's been built since (Hillel: "update the admin usage page with all of the new stuff, ie
// courses, etc"): the skins, the Plus courses' extras, Hum a tune, the studio, the onboarding's
// asks and Melody first. Still a number per kind per day; nothing about who.
for (const kind of [
    'open_game', 'open_serious', 'game_on', 'game_off',
    'sharpen_up', 'then_now', 'keep_song', 'fact_source',
    'hum_start', 'hum_click', 'hum_tap', 'hum_found', 'hum_saved',
    'take_use', 'take_undo', 'track_add', 'track_rename', 'exp_song', 'exp_practice', 'exp_stems', 'exp_sheet', 'sheet_import',
    'share_note', 'card_named', 'su_progress', 'su_reminders', 'su_share', 'su_boards', 'card_day2',
    'sug_melody', 'sug_part', 'sug_switch',
    'daily_viewed', 'daily_sung',
]) KINDS.add(kind);
// Kinds with a detail, `<prefix>:<id>`: a Plus course's lessons by course, the free course by lesson.
// The server keeps only ids the courses have.
const DETAILED = new Set(['pl_s', 'pl_d', 'fl_s', 'fl_d']);
const MOST_EACH = 20; // the server takes 1 to 20 of a kind, and 20 kinds, in one batch
const IDLE_WAIT_MS = 30000; // a batch goes within this, when the browser is idle (never for audio)

const detailed = (kind) => DETAILED.has(kind.split(':')[0]) && /^[a-z]+_[a-z]:[a-z0-9-]{1,24}$/.test(kind);

/** Counts a kind for one course or lesson: countFor('pl_d', 'vibrato'), countFor('fl_s', '1-match'). */
export function countFor(prefix, id) {
    if (id) count(`${prefix}:${id}`);
}

const queue = new Map(); // kind → how many since the last batch
let waiting = false;

/** Counts one `kind` (an unknown one is ignored). */
export function count(kind) {
    if (!KINDS.has(kind) && !detailed(kind)) return;
    queue.set(kind, (queue.get(kind) ?? 0) + 1);
    if (waiting) return;
    waiting = true;
    const run = () => {
        waiting = false;
        send();
    };
    if ('requestIdleCallback' in globalThis) requestIdleCallback(run, { timeout: IDLE_WAIT_MS });
    else setTimeout(run, IDLE_WAIT_MS);
}

/** The batch so far, taken off the queue. */
function batch() {
    const events = [...queue].slice(0, MOST_EACH).map(([kind, n]) => ({ kind, n: Math.min(MOST_EACH, n) }));
    queue.clear();
    return events;
}

/** Sends what's queued, unless counting is off or there's nothing; `leaving` when the page is going. */
export function send({ leaving = false } = {}) {
    if (!queue.size) return;
    // Until the server has said (its config not read yet), nothing goes: counting is opt-in for an install.
    if (account.config?.usageCounts !== true) {
        if (leaving || account.config) queue.clear();
        return;
    }
    const variant = funnelVariant(); // this device's experiment arm, or the landing's for this visit
    const source = ref(); // the stored ?ref= tag many people share, grouped by the server: Growth's sources
    const body = JSON.stringify({ events: batch(), signedIn: !!account.me, ...(variant ? { variant } : {}), ...(source ? { source } : {}) });
    if (mockWanted()) {
        (globalThis.countsSent ??= []).push(JSON.parse(body)); // the flows read what would have gone
        return;
    }
    const path = OPERATIONS['counts.create'][1];
    try {
        if (navigator.sendBeacon?.(path, new Blob([body], { type: 'application/json' }))) return;
        fetch(path, { method: 'POST', body, headers: { 'Content-Type': 'application/json' }, credentials: 'omit', keepalive: true }).catch((error) => console.info('Usage counts not sent', error?.message ?? error));
    } catch (error) {
        console.info('Usage counts not sent', error?.message ?? error);
    }
}

addEventListener('pagehide', () => send({ leaving: true }));
addEventListener('visibilitychange', () => document.visibilityState === 'hidden' && send({ leaving: true }));
