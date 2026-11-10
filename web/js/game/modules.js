// Game mode's own modules (js/game/modules.js), loaded only in game mode (the coordinator, 2026-11-02: serious mode never downloads them).
// The small pieces both skins use stay static (effects, combo, popups, motion, hud, crt, title-screen, fonts); the rest
// are fetched here: started by theme-boot.js's modulepreload when the page opens in game mode, imported as game mode
// comes on (at boot, or the Game mode switch mid-session, no reload), and kept by the service worker for offline.
// Shared code reaches them through this file: async components, and wrappers that do nothing until their module is in
// (a cue that can't sound yet stays silent; a daily stamp not yet known shows none), since every one of them is game
// mode's extra, never something the singer needs.

import { defineAsyncComponent, markRaw, reactive, watch } from 'vue';
import { game } from '../stores/appearance.js';
import { liveSession } from '../stores/session-live.js';
import { effect } from './effects.js';

const LOADERS = {
    chip: () => import('./chip.js'),
    daily: () => import('./daily.js'),
    ghost: () => import('./ghost.js'),
    titleCard: () => import('./title-card.js'),
    tally: () => import('./tally.js'),
    badgeNews: () => import('./badge-news.js'),
    stageMap: () => import('./stage-map.js'),
    partSelect: () => import('./part-select.js'),
    settings: () => import('./settings.js'),
    boards: () => import('../components/boards.js'),
};

/** The modules loaded so far, by name (reactive, so a template using one draws again once it's in). */
export const loaded = reactive({});
const pending = {};

/** Module `name`, loading it once. */
export function load(name) {
    return (pending[name] ??= LOADERS[name]().then((module) => (loaded[name] = markRaw(module))));
}

// Sing's part chooser needs Sing's session store (the studio's mixing, trimming and sync): it loads when the chooser
// first draws it (its lazyComponent), with Sing, never as game mode comes on, or every first visit's lesson 1 loaded
// that store for a screen it never shows (2026-11-08).
const WITH_SING = new Set(['partSelect']);

/** Every game module but those that come with Sing (game mode coming on). */
export const loadGame = () => Promise.all(Object.keys(LOADERS).filter((name) => !WITH_SING.has(name)).map(load));

// In game mode, at boot and whenever it comes on.
watch(game, (on) => {
    if (on) loadGame().catch((error) => console.error('Game mode could not load its pieces', error));
}, { immediate: true });

// Those that come with Sing, as soon as Sing's store is in (Sing opening, or fetched ahead once the first screen is up),
// so its chooser never waits for its game piece.
watch(() => game() && !!liveSession.value, (ready) => {
    if (ready) for (const name of WITH_SING) load(name).catch((error) => console.error('A game piece for Sing could not load', error));
}, { immediate: true });

/** A component of a game module, drawn once its module is in. */
export const lazyComponent = (name, exportName) => defineAsyncComponent(() => load(name).then((module) => module[exportName]));

// chip.js: the 16-bit cues. Silent until loaded (cue returns null when a cue may not sound).
export const cue = (name, options) => loaded.chip?.cue(name, options) ?? null;
export const chipCountIn = () => loaded.chip?.chipCountIn() ?? null;

// title-card.js: a lesson's title card, once a device.
export const titleSeen = (lessonId) => loaded.titleCard?.titleSeen(lessonId) ?? true;
export const markTitleSeen = (lessonId) => loaded.titleCard?.markTitleSeen(lessonId);
export const clearMs = () => loaded.titleCard?.CLEAR_MS ?? 2200;

// tally.js: whether the lesson end's XP counts up (a switch, so no module needed).
export const tallyOn = () => effect('celebrations');

// badge-news.js: new badges, said in game mode.
export function sayBadges(names) {
    load('badgeNews').then((module) => module.sayBadges(names)).catch((error) => console.error('Badges not said', error));
}

// daily.js: today's challenge.
const NO_DAILY = { today: null, loading: false, asked: null };
export const dailyState = () => loaded.daily?.daily ?? NO_DAILY;
export const stamped = (day) => loaded.daily?.stamped(day) ?? false;
export const stampDaily = (templateId, partKey) => load('daily').then((module) => module.stampDaily(templateId, partKey));
export const loadDaily = () => load('daily').then((module) => module.loadDaily());

// ghost.js: the ghost of your best take.
export const ghost = () => loaded.ghost ?? null;
export const ghostModule = () => load('ghost');

// components/boards.js: the leaderboards (game mode's only).
export const setGlobal = (on) => load('boards').then((module) => module.setGlobal(on));
