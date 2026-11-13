// The live view's heartbeat (docs/realtime-dashboard-plan.md; Hillel, 2026-11-12: "Allow, cache only"): while the app
// is in view it tells the server which area and screen it's on, every 30 seconds and on each move, so Admin > Live can
// say how many people are here right now. No ID, no cookie of its own, nothing about who: the server adds it to counts
// it holds in cache for a few minutes and never stores. Staff screens (admin, the styleguide) never beat. A timer for
// the page, never for audio.

import { api } from './api.js';
import { route } from './router.js';
import { watch } from 'vue';

const EVERY_MS = 30000;
const SILENT = new Set(['admin', 'styleguide', 'styleguideGame']);
const AREAS = {
    courses: ['course', 'learn', 'readLines', 'plusCourse', 'songCourse', 'workout', 'cooldown', 'warmup', 'voice'],
    studio: ['studio', 'sing', 'print', 'create', 'template'],
};

/** The area a screen belongs to: courses, studio, or the rest of the app; null for staff screens, which never beat. */
export function areaFor(name) {
    if (SILENT.has(name)) return null;
    return Object.keys(AREAS).find((area) => AREAS[area].includes(name)) ?? 'app';
}

let last = 0;

/**
 * One beat for the screen now in view; a refused or failed beat is let go (the next one tries again). `view` marks a
 * screen opened (the first beat and each move), which the server counts as a page view; the 30-second and on-return
 * beats only say someone is still here.
 */
export async function beat(name = route.name, { view = false } = {}) {
    const area = areaFor(name);
    if (!area || document.visibilityState !== 'visible') return false;
    last = Date.now();
    try {
        await api.live.beat({ area, page: name, view });
    } catch (error) {
        console.info('Live beat not sent', error?.message ?? error);
    }
    return true;
}

export function startBeat() {
    beat(route.name, { view: true });
    watch(() => route.name, (name) => beat(name, { view: true }));
    setInterval(() => beat(), EVERY_MS);
    document.addEventListener('visibilitychange', () => document.visibilityState === 'visible' && Date.now() - last >= EVERY_MS && beat());
}
