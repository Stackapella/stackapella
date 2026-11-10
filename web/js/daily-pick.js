// Today's challenge, for both skins (docs/daily-challenge-plan.md): one song part a day, the same for everyone, picked
// by the server from the UTC date (boards.daily.show, public; docs/game-server-spec.md, section 1). This module is the
// part both skins share, kept out of game mode's own modules so serious mode never downloads those: the day's pick,
// when a singer may be offered it, taking it (your own copy of that song, on the day's part, not a new song every
// day) and counting it. Game mode's board, cup and stamps stay in js/game/daily.js, built on this.

import { reactive } from 'vue';
import * as db from './db.js';
import { call } from './api.js';
import { DAY_SECONDS, dayOf } from './course.js';
import { navigate } from './router.js';
import { session as tabStorage } from './storage.js';
import { learning } from './stores/learning.js';
import { projectsStore } from './lazy-stores.js';
import { count } from './usage.js';

const STARTED = 'stackapella.daily'; // this tab's challenge taken: { date, templateId, partKey }
const WEEK = 7 * 86400000; // the first week, as components/voice-care.js inFirstWeek counts it

/** Today in UTC, as the server dates the challenge: "2026-10-30". */
export const utcDay = (at = Date.now()) => new Date(at).toISOString().slice(0, 10);

/** Today's challenge, asked once a day a visit: { date, templateId, partKey, endsAt, ... } or null. */
export const dailyPick = reactive({ today: null, loading: false, asked: null });

/** Asks the server for today's pick (once a day a session); a closed or missing one isn't asked again until tomorrow. */
export async function loadPick() {
    if (dailyPick.asked === utcDay() || dailyPick.loading) return dailyPick.today;
    dailyPick.asked = utcDay();
    dailyPick.loading = true;
    try {
        dailyPick.today = (await call('boards.daily.show', { query: { scope: 'global' } })).data;
    } catch (error) {
        console.info('No challenge today', error?.message ?? error);
        dailyPick.today = null;
    } finally {
        dailyPick.loading = false;
    }
    return dailyPick.today;
}

/**
 * Whether the challenge may be offered: past the first week; in it, from its second day once that day's 5 minutes
 * are sung, so the first-week path is untouched until the day's lesson is done.
 */
export function dailyOpen(progress = learning.progress, now = Date.now()) {
    const started = progress?.startedAt;
    if (started && now - started >= WEEK) return true;
    const today = dayOf(now);
    return !!started && dayOf(started) !== today && (progress.practice?.[today] ?? 0) >= DAY_SECONDS;
}

/** The challenge date a take of this song part sings for, if it was started from today's challenge; else null. */
export function dailyFor(templateId, partKey) {
    try {
        const started = JSON.parse(tabStorage.get(STARTED) || 'null');
        return started && started.templateId === templateId && started.partKey === partKey && started.date === utcDay() ? started.date : null;
    } catch {
        return null;
    }
}

/** Your own copy of a song to sing again (not a course's, a friend's or a beta's), the most recently changed. */
const ownCopy = (projects, templateId) => projects.list.find((p) => p.template?.id === templateId && !p.course && !p.shared && !p.idea);

/**
 * Takes the challenge: your copy of the song, turned to the day's part, or a new one the first time; then Sing. A
 * scored take of it then counts as the challenge sung (and in game mode goes on the day's board).
 */
export async function takeChallenge(challenge, song) {
    tabStorage.set(STARTED, JSON.stringify({ date: challenge.date, templateId: challenge.templateId, partKey: challenge.partKey }));
    // The projects store loads with Home, and is fetched ahead everywhere else (lazy-stores.js), so a tap never waits for it.
    const { loadProjects, projects, saveProject, startFromTemplate } = await projectsStore();
    if (!projects.loaded) await loadProjects();
    const kept = ownCopy(projects, challenge.templateId);
    const slot = kept ? (await db.allBy('parts', 'projectId', kept.id)).find((p) => p.templatePartKey === challenge.partKey) : null;
    if (kept && slot) {
        const project = await db.get('projects', kept.id);
        await saveProject({ ...project, practice: { ...project.practice, focus: slot.id } });
        navigate(`/p/${kept.id}/sing`);
        return;
    }
    const project = await startFromTemplate(song, { focusKey: challenge.partKey });
    navigate(`/p/${project.id}/sing`);
}

/** A scored take of this song part: when it was started from today's challenge, the challenge was sung (any skin). */
export function countSung(templateId, partKey) {
    if (dailyFor(templateId, partKey)) count('daily_sung');
}
