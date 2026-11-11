// Anonymous usage counts (decision 2026-10-28, "anonymous usage counts"; contract: counts.create):
// a few moments are counted, guests included, and sent as a batch of daily totals to our own
// server: each kind and how many, and whether someone was signed in. Nothing else: no ID and no
// device details. The onboarding funnel (js/funnel.js; Hillel's "Yes") adds its firsts and asks,
// kept on the device only to count each once, a variant ("onb1-b") that half of all devices share,
// and the ?ref= tag the device arrived with (a source many share, never an identifier). A batch goes when the browser is idle, and when the page is hidden or closed
// (sendBeacon); offline or refused, it's simply dropped. Off when the server
// says so (auth.config.usageCounts: a self-hosted install, unless its owner turned it on).

import { OPERATIONS } from './api.js';
import { mockWanted } from './api-mode.js';
import { account, ref } from './stores/account.js';
import { variant as funnelVariant } from './funnel.js';
import { enrolledVariants } from './experiments.js';

// Sign-ups are counted by the server itself (every way in, web and apps), so the app never sends one.
export const KINDS = new Set(['app_open', 'app_day', 'app_week', 'app_month', 'lesson_start', 'lesson_done', 'unit_done', 'song_created', 'take_recorded', 'share_created', 'harmonise_used', 'export_done', 'result_shared']);
// The funnel's steps and asks (docs/onboarding-review.md, section 4), each counted once per device by js/funnel.js.
const FUNNEL = ['f_day7', 'f_plus', 'f_arrive', 'f_arrive_start', 'f_mic', 'f_note', 'f_note_skip', 'f_lesson1', 'f_chord', 'f_lesson2', 'f_lesson3', 'f_unit1', 'f_goal', 'f_install', 'f_name', 'f_account', 'f_share', 'f_remind', 'f_take', 'f_harmony', 'f_day2', 'f_week1', 'f_goal_met', 'f_day14', 'f_course_lesson'];
const NUDGES = ['acct_week', 'acct_songs', 'acct_unit', 'acct_risk', 'acct_settings', 'acct_course']; // the account nudges' asks
const ASKED = ['install', 'account', 'name', 'share', 'goal', 'remind', ...NUDGES].flatMap((ask) => [`ask_${ask}_shown`, `ask_${ask}_yes`]);
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
    // The account nudges: an account made, by what asked for it, and each nudge's Not now.
    'su_week', 'su_songs', 'su_unit', 'su_risk', 'su_school', 'su_groups', 'su_friends', 'su_voice', 'su_plus', 'su_template', 'su_sheet', 'su_settings', 'su_daily',
    ...NUDGES.map((ask) => `ask_${ask}_not`),
    'sug_melody', 'sug_part', 'sug_switch',
    'daily_viewed', 'daily_sung',
    'school_redeemed',
    // Home's Today line (docs/home-returning-plan.md, experiment ret1): each case shown, and its taps.
    'home_today_gap_shown', 'home_today_gap_tap', 'home_today_stuck_shown', 'home_today_stuck_tap',
    'home_today_done_shown', 'home_today_done_song_tap', 'home_today_done_course_tap', 'home_today_daily_shown', 'home_today_daily_tap',
    // Home's picks carrying a New or Carol season song (docs/retention-plan.md, idea 5): shown, and tapped.
    'home_new_shown', 'home_new_tap',
]) KINDS.add(kind);
// Kinds with a detail, `<prefix>:<id>`: a Plus course's lessons by course, the free course by lesson.
// The server keeps only ids the courses have.
// fs_r: a free lesson's step reached, `fs_r:<lessonId>.<step>` (step from 0), once per step each time it's run.
const DETAILED = new Set(['pl_s', 'pl_d', 'fl_s', 'fl_d', 'fs_r']);
const MOST_EACH = 20; // the server takes 1 to 20 of a kind, and 20 kinds, in one batch
const IDLE_WAIT_MS = 30000; // a batch goes within this, when the browser is idle (never for audio)

const detailed = (kind) => DETAILED.has(kind.split(':')[0]) && /^[a-z]+_[a-z]:[a-z0-9-]{1,24}(\.\d{1,2})?$/.test(kind);

/** Counts a kind for one course or lesson: countFor('pl_d', 'vibrato'), countFor('fl_s', '1-match'). */
export function countFor(prefix, id) {
    if (id) count(`${prefix}:${id}`);
}

const queue = new Map(); // kind → how many since the last batch
const enrols = []; // experiments' arms just drawn ("short1-b"), each counted once on its own (exp_enrol)
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

/**
 * An experiment's arm drawn on this device (js/experiments.js): exp_enrol, sent once and on its own
 * with only that arm, as the arm's denominator (a device joins at the screen that differs).
 */
export function countEnrol(variant) {
    if (!/^[a-z0-9-]{3,48}$/.test(variant)) return;
    enrols.push(variant);
    count('exp_enrol');
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
        if (leaving || account.config) {
            queue.clear();
            enrols.length = 0;
        }
        return;
    }
    // Each arm just drawn goes alone, with only its own arm; the rest of the batch carries every arm.
    queue.delete('exp_enrol');
    for (const drawn of enrols.splice(0)) post({ events: [{ kind: 'exp_enrol', n: 1 }], signedIn: !!account.me, variants: [drawn] });
    if (!queue.size) return;
    const variant = funnelVariant(); // this device's experiment arm, or the landing's for this visit
    const source = ref(); // the stored ?ref= tag many people share, grouped by the server: Growth's sources
    const variants = enrolledVariants(); // the experiments' arms this device has seen ("short1-b"), at most four
    post({ events: batch(), signedIn: !!account.me, ...(variant ? { variant } : {}), ...(variants.length ? { variants } : {}), ...(source ? { source } : {}) });
}

/** One batch to the server (a beacon when the page is going), or to the flows when the mock is on. */
function post(payload) {
    const body = JSON.stringify(payload);
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

// ---- Reach: this device counted once a day, a week and a month (the app_day, app_week and app_month
// kinds; Admin's "People using the app"). Only the last period counted for each is kept here, a date
// string, never an ID; the batch's signedIn splits guests from accounts.

const REACH = 'stackapella.reach'; // { app_day: '2026-10-31', app_week: '2026-W44', app_month: '2026-10' }

/** The UTC day, ISO week (Monday start) and month `at` falls in. */
export function periodKeys(at = Date.now()) {
    const date = new Date(at);
    const day = date.toISOString().slice(0, 10);
    const thursday = new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth(), date.getUTCDate() + 3 - ((date.getUTCDay() + 6) % 7)));
    const yearStart = Date.UTC(thursday.getUTCFullYear(), 0, 1);
    const week = 1 + Math.floor((thursday.getTime() - yearStart) / 86400000 / 7);
    return { app_day: day, app_week: `${thursday.getUTCFullYear()}-W${String(week).padStart(2, '0')}`, app_month: day.slice(0, 7) };
}

/** Counts this device once in each new day, week and month (at app open). */
export function countReach(at = Date.now()) {
    let kept = {};
    try {
        kept = JSON.parse(localStorage.getItem(REACH) || '{}') ?? {};
    } catch (error) {
        console.info('The reach record was unreadable', error?.message ?? error);
    }
    const keys = periodKeys(at);
    const changed = Object.keys(keys).filter((kind) => kept[kind] !== keys[kind]);
    if (!changed.length) return;
    try {
        localStorage.setItem(REACH, JSON.stringify(keys));
    } catch (error) {
        console.info('The reach record was not kept', error?.message ?? error);
        return; // without a record, counting would repeat on every open
    }
    for (const kind of changed) count(kind);
}
