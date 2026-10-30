// Game mode's arcade moments, one switch each (docs/game-elements-plan.md, section 3; Hillel: "let
// people choose sub-elements of game mode in the settings to customize to suit their tastes"). Every
// one shows only in game mode and only while its switch is on; Game sounds is off until chosen. Kept
// on this device. Off, a screen falls back to today's game look, never to serious mode.

import { reactive, watch } from 'vue';
import { local } from '../storage.js';
import { game } from '../stores/appearance.js';

const KEY = 'stackapella.gameEffects';

/** The switches in Settings' order, with their defaults. */
export const EFFECTS = { sounds: false, hud: true, popups: true, titles: true, celebrations: true, title: true, stages: true, versus: true, select: true, ghost: true, daily: true, monthly: true };

function read() {
    let kept = {};
    try {
        kept = JSON.parse(local.get(KEY) || '{}');
    } catch (error) {
        console.warn('Game mode settings unreadable; the defaults apply', error);
    }
    const known = Object.fromEntries(Object.entries(kept ?? {}).filter(([name, on]) => name in EFFECTS && typeof on === 'boolean'));
    return { ...EFFECTS, ...known };
}

export const effects = reactive(read());
watch(effects, () => local.set(KEY, JSON.stringify(effects)), { deep: true });

/** The switches that are off, as <html data-game-off="hud title">, so a reskin in CSS follows them too. */
export const offList = (all = effects) => Object.keys(EFFECTS).filter((name) => !all[name]).join(' ');
watch(effects, () => (document.documentElement.dataset.gameOff = offList()), { deep: true, immediate: true });

/** Whether `name` shows now: game mode on and its switch on. */
export function effect(name) {
    if (!(name in EFFECTS)) throw new Error(`No game effect called ${name}`);
    return game() && effects[name];
}

const REDUCE = window.matchMedia?.('(prefers-reduced-motion: reduce)');

/** Reduced motion: every moment shows its still, complete frame instead of moving. */
export const still = () => REDUCE?.matches ?? false;
