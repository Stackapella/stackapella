// Breadth's tried set (js/usage.js tried), filled in once from what this device already holds (a lesson's stars, a
// course's, a workout, a take, two parts of one song, every part, a share), WITHOUT counting: a device that had them
// before breadth was counted isn't counted again (docs/retention-plan.md, section 8). Signed in, the account's set and
// this device's are merged both ways (account.update `tried`), so a feature is counted once, wherever it came first.
import { watch } from 'vue';
import { api } from './api.js';
import { mockWanted } from './api-mode.js';
import * as db from './db.js';
import { account } from './stores/account.js';
import { learning, loadLearning } from './stores/learning.js';
import { addTried, countBreadth, TRIED, triedSet } from './usage.js';

const starred = (lessons) => Object.values(lessons ?? {}).some((kept) => (kept?.stars ?? 0) > 0);

/** What the device already holds, as features (each with today, as the day it's known by). */
export async function heldFeatures() {
    const held = {};
    await loadLearning();
    const progress = learning.progress ?? {};
    if (starred(progress.lessons)) held.lesson = true;
    if (Object.values(progress.courses ?? {}).some((c) => starred(c?.lessons))) Object.assign(held, { lesson: true, course: true });
    if ((progress.workouts ?? 0) > 0 || progress.lastWorkout) held.warmup = true;
    try {
        const parts = await db.all('parts');
        const byProject = new Map();
        for (const p of parts) {
            if (!p.templatePartKey) continue;
            const song = byProject.get(p.projectId) ?? { all: 0, sung: 0 };
            song.all++;
            if (p.frames > 0) song.sung++;
            byProject.set(p.projectId, song);
        }
        for (const song of byProject.values()) {
            if (song.sung) held.record = true;
            if (song.sung >= 2) held.stack = true;
            if (song.sung >= 2 && song.sung === song.all) held.whole = true;
        }
    } catch (error) {
        console.info('The songs here were not read for breadth', error?.message ?? error);
    }
    try {
        if (JSON.parse(localStorage.getItem('stackapella.funnel') || '{}')?.firsts?.f_share) held.share = true;
    } catch {
        // an unreadable funnel record: nothing known of shares
    }
    return held;
}

/** Once a device: the set filled in from what's here, uncounted. Then, signed in, merged with the account's both ways. */
export async function startTried() {
    if (!triedSet().backfilled) addTried(await heldFeatures());
    countBreadth(); // today's br:<n>, if countReach found the set not yet filled in
    watch(() => account.me?.id, () => mergeWithAccount(), { immediate: true });
}

/** The account's tried features kept here (never counted again), and this device's sent up where the account lacks them. */
export function mergeWithAccount() {
    const me = account.me;
    if (!me || mockWanted()) return;
    const theirs = me.tried && typeof me.tried === 'object' ? me.tried : {};
    addTried(theirs);
    const ours = triedSet().at;
    const missing = Object.fromEntries(Object.entries(ours).filter(([f]) => TRIED.includes(f) && !theirs[f]));
    if (Object.keys(missing).length) api.account.update({ tried: missing }).catch((error) => console.info('The tried set was not merged up', error?.code ?? error));
}
