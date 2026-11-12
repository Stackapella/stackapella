// Anonymous usage counts (decision 2026-10-28, "anonymous usage counts"; contract: counts.create):
// a few moments are counted, guests included, and sent as a batch of daily totals to our own
// server: each kind and how many, and whether someone was signed in. Nothing else: no ID and no
// device details. The onboarding funnel (js/funnel.js; Hillel's "Yes") adds its firsts and asks,
// kept on the device only to count each once, a variant ("onb1-b") that half of all devices share,
// and the ?ref= tag the device arrived with (a source many share, never an identifier). A batch goes when the browser is idle, and when the page is hidden or closed
// (sendBeacon); offline or refused, it's simply dropped. Off when the server
// says so (auth.config.usageCounts: a self-hosted install, unless its owner turned it on).

import { api, OPERATIONS } from './api.js';
import { mockWanted } from './api-mode.js';
import { account, ref } from './stores/account.js';
import { variant as funnelVariant } from './funnel.js';
import { enrolledVariants } from './experiments.js';

// Sign-ups are counted by the server itself (every way in, web and apps), so the app never sends one.
export const KINDS = new Set(['app_open', 'app_day', 'app_week', 'app_month', 'lesson_start', 'lesson_done', 'unit_done', 'song_created', 'take_recorded', 'share_created', 'harmonise_used', 'export_done', 'result_shared']);
// The funnel's steps and asks (docs/onboarding-review.md, section 4), each counted once per device by js/funnel.js.
const FUNNEL = ['f_day7', 'f_plus', 'f_arrive', 'f_arrive_start', 'f_mic', 'f_note', 'f_note_skip', 'f_lesson1', 'f_chord', 'f_lesson2', 'f_lesson3', 'f_unit1', 'f_goal', 'f_install', 'f_name', 'f_account', 'f_share', 'f_remind', 'f_take', 'f_harmony', 'f_day2', 'f_week1', 'f_goal_met', 'f_day14', 'f_course_lesson'];
const NUDGES = ['acct_week', 'acct_songs', 'acct_unit', 'acct_risk', 'acct_settings', 'acct_course']; // the account nudges' asks
const ASKED = ['install', 'account', 'name', 'share', 'goal', 'remind', 'nudge', ...NUDGES].flatMap((ask) => [`ask_${ask}_shown`, `ask_${ask}_yes`]);
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
    'ask_nudge_not', // the wrap's Nudge me tomorrow, put off (docs/retention-plan.md, idea 2)
    // Coming back (docs/retention-plan.md, section 8): days 1, 7, 14 and 30 since this device arrived, and once in days 1 to 6.
    'back_d1', 'back_d7', 'back_d14', 'back_d30', 'back_w1',
    // A choir group's path, each a device's first (js/funnel.js first): the start sheet shown, a group made, its link first
    // sent, an invite accepted, a director's first part given, a member's first take on a group's song.
    'grp_start', 'grp_made', 'grp_link', 'grp_joined', 'grp_assigned', 'grp_sang',
    // The thread back (docs/retention-plan.md, idea 2): the wall's Send myself the link and Add tomorrow to my calendar.
    'back_link_tap', 'back_cal_tap',
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
// cl_s, cl_d and cs_r: the same for a lesson in any other course, by the lesson's own id (ids are unique across courses;
// pl_s and pl_d stay, by course, so earlier weeks still read).
const DETAILED = new Set(['pl_s', 'pl_d', 'fl_s', 'fl_d', 'fs_r', 'cl_s', 'cl_d', 'cs_r']);
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

// The song library's filters (Home's Browse all): library_filter:<group>, :all, :search or :part:<voice>, one a tap. The
// groups mirror server/config/counts.php (SongCollections::GROUPS and "more").
export const LIBRARY_FILTERS = new Set(['all', 'search', 'christmas', 'jewish-songs', 'rounds', 'spirituals', 'hymns', 'sea-shanties', 'folk-songs', 'classical', 'barbershop-songs', 'more', 'part:soprano', 'part:alto', 'part:tenor', 'part:bass']);
const libraryFilter = (kind) => kind.startsWith('library_filter:') && LIBRARY_FILTERS.has(kind.slice('library_filter:'.length));

/** Counts one `kind` (an unknown one is ignored). */
export function count(kind) {
    if (KINDS.has(kind) || detailed(kind) || libraryFilter(kind)) enqueue(kind);
}

// A share, counted as sh:<surface>.<method>: what was shared and how. The lists mirror server/config/counts.php
// (share_surfaces and share_methods); anything else is dropped, as count() drops an unknown kind.
export const SHARE_SURFACES = new Set(['song', 'lesson', 'note', 'daily', 'invite', 'challenge', 'group', 'class', 'arrangement', 'warmup', 'app', 'embed', 'page-song', 'page-part', 'page-course', 'page-lesson', 'page-carols']);
export const SHARE_METHODS = new Set(['sheet', 'copy', 'video', 'image', 'embed']);

/** Counts a share of `surface` by `method`: countShare('daily', 'sheet') is sh:daily.sheet. */
export function countShare(surface, method) {
    if (!SHARE_SURFACES.has(surface) || !SHARE_METHODS.has(method)) return;
    enqueue(`sh:${surface}.${method}`);
    tried('share');
}

// ---- Breadth: the features this device has tried (docs/retention-plan.md, section 8; config/counts.php `features`).
// Kept here only, each with the day first used, never sent as a set: ft:<feature> once on its first use, br:<n> beside
// each day's app_day. Signed in, the account keeps the same set (account.update `tried`, merged), so a feature first used
// on another device isn't counted again here.
export const TRIED = ['lesson', 'warmup', 'singalong', 'record', 'stack', 'whole', 'course', 'create', 'share', 'together', 'tool'];
const TRIED_KEY = 'stackapella.tried'; // { at: { feature: 'YYYY-MM-DD' }, backfilled: bool }
const localDay = (at = Date.now()) => new Date(at - new Date(at).getTimezoneOffset() * 60000).toISOString().slice(0, 10);

/** What this device has tried: { at: { feature: day }, backfilled }. */
export function triedSet() {
    try {
        const kept = JSON.parse(localStorage.getItem(TRIED_KEY) || 'null');
        return { at: kept?.at && typeof kept.at === 'object' ? kept.at : {}, backfilled: !!kept?.backfilled };
    } catch (error) {
        console.info('The tried set was unreadable', error?.message ?? error);
        return { at: {}, backfilled: false };
    }
}

function keepTried(set) {
    try {
        localStorage.setItem(TRIED_KEY, JSON.stringify(set));
        return true;
    } catch (error) {
        console.info('The tried set was not kept', error?.message ?? error);
        return false;
    }
}

/** A feature used: on its first use here (and not on the account already), ft:<feature>, and the account told. */
export function tried(feature, { at = Date.now() } = {}) {
    if (!TRIED.includes(feature)) return;
    const set = triedSet();
    if (set.at[feature]) return;
    set.at[feature] = localDay(at);
    if (!keepTried(set)) return; // without the record it would count on every use
    enqueue(`ft:${feature}`);
    if (account.me && !mockWanted()) api.account.update({ tried: { [feature]: set.at[feature] } }).catch((error) => console.info('The tried feature was not kept on the account', error?.code ?? error));
}

/**
 * What the device already holds (stars, songs, takes, groups), filled in once WITHOUT counting (a device that had them
 * before breadth was counted), and the account's own set (signed in), also never counted again here.
 */
export function addTried(features, { at = Date.now() } = {}) {
    const set = triedSet();
    for (const [feature, day] of Object.entries(features)) {
        if (TRIED.includes(feature) && !set.at[feature]) set.at[feature] = typeof day === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(day) ? day : localDay(at);
    }
    set.backfilled = true;
    keepTried(set);
}

/** br:<n>, how many features this device has tried: once a day, and only once the set has been filled in. */
export function countBreadth(at = Date.now()) {
    const set = triedSet();
    if (!set.backfilled) return;
    const day = localDay(at);
    try {
        if (localStorage.getItem('stackapella.breadthDay') === day) return;
        localStorage.setItem('stackapella.breadthDay', day);
    } catch (error) {
        console.info('The breadth day was not kept', error?.message ?? error);
        return;
    }
    enqueue(`br:${Object.keys(set.at).filter((f) => TRIED.includes(f)).length}`);
}

/** A shared link's ?ref= tag for `surface` (the server's source for it): share-daily. */
export const shareRef = (surface) => `share-${surface}`;

/** Adds one to `kind` in the next batch, which goes when the browser is idle. */
function enqueue(kind) {
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
    if (changed.includes('app_day')) {
        countReturn(at);
        if (String(ref() ?? '').startsWith('tool-')) tried('tool', { at }); // arrived from a free tool
        countBreadth(at); // breadth, once a day (after the set is first filled in: js/tried-backfill.js)
    }
}

const RETURNED = 'stackapella.returned'; // '1' once back_w1 has gone: nothing else

/**
 * Coming back, beside the day's app_day: back_d1, back_d7, back_d14 or back_d30 on exactly that day since this device
 * arrived (the funnel's own day, kept on the device), and back_w1 once on the first open in days 1 to 6. Daily totals
 * only, with no ID; read against the new devices of the matching earlier day.
 */
export function countReturn(at = Date.now()) {
    let arrived = null;
    try {
        arrived = JSON.parse(localStorage.getItem('stackapella.funnel') || 'null')?.arrived ?? null;
    } catch (error) {
        console.info('The arrival day was unreadable', error?.message ?? error);
    }
    if (!arrived) return;
    const local = new Date(at - new Date(at).getTimezoneOffset() * 60000).toISOString().slice(0, 10);
    const days = Math.round((Date.parse(local) - Date.parse(arrived)) / 86400000);
    if ([1, 7, 14, 30].includes(days)) count(`back_d${days}`);
    if (days >= 1 && days <= 6) {
        try {
            if (localStorage.getItem(RETURNED) === '1') return;
            localStorage.setItem(RETURNED, '1');
        } catch (error) {
            console.info('The week-one return was not kept', error?.message ?? error);
            return; // without the flag it would count on every day of the week
        }
        count('back_w1');
    }
}
