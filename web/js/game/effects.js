// Game mode's arcade moments, one switch each (docs/game-elements-plan.md, section 3; Hillel: "let
// people choose sub-elements of game mode in the settings to customize to suit their tastes"). Every
// one shows only in game mode and only while its switch is on; Game sounds is off until chosen. Kept
// on this device. Off, a screen falls back to today's game look, never to serious mode.

import { reactive, watch } from 'vue';
import { local } from '../storage.js';
import { game } from '../stores/appearance.js';

const KEY = 'stackapella.gameEffects';

/** The switches in Settings' order, with their defaults. */
export const EFFECTS = { all: true, sounds: false, hud: true, popups: true, titles: true, celebrations: true, title: true, stages: true, versus: true, boss: true, select: true, ghost: true, daily: true, monthly: true, arcade: true, scanlines: true };
/** Looks, not moments: the master switch leaves them be. */
export const LOOKS = ['arcade', 'scanlines'];

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

/**
 * A course's own rules for game mode, for the lesson open (docs/game-elements-plan.md, "A course's own game rules"):
 * course.json's "game" ({ replayXp, combo, streak, meter }, each absent = as everywhere) and a lesson's "quiet". The
 * lesson view sets them as a lesson opens and clears them as it closes, so every other screen keeps everything.
 */
export const courseRules = reactive({ combo: true, streak: true, meter: true, quiet: false });
export function setCourseRules(course = null, lesson = null) {
    const rules = course?.game ?? {};
    Object.assign(courseRules, { combo: rules.combo !== false, streak: rules.streak !== false, meter: rules.meter !== 'plain', quiet: !!lesson?.quiet });
}

/** The switches that are off, as <html data-game-off="hud title">, so a reskin in CSS follows them too; a course's rules
 * add theirs (a quiet lesson's moments, "meter" for the plain look on its takes). */
export const offList = (all = effects, rules = courseRules) => [
    ...Object.keys(EFFECTS).filter((name) => !all[name] || (!all.all && !LOOKS.includes(name)) || (rules.quiet && !LOOKS.includes(name) && name !== 'all') || (name === 'popups' && !rules.combo)),
    ...(rules.meter ? [] : ['meter']),
].join(' ');
watch([effects, courseRules], () => (document.documentElement.dataset.gameOff = offList()), { deep: true, immediate: true });

/** Whether `name` shows now: game mode on, its switch on, and Game effects (`all`, the master switch) on; the arcade look and its scanlines are looks, not moments, so the master leaves them be. */
export function effect(name) {
    if (!(name in EFFECTS)) throw new Error(`No game effect called ${name}`);
    // A quiet lesson (Wade in the Water) has no moments; a course without combos has no pop-ups. The looks stay.
    if (courseRules.quiet && !LOOKS.includes(name) && name !== 'all') return false;
    if (name === 'popups' && !courseRules.combo) return false;
    return game() && effects[name] && (effects.all || LOOKS.includes(name));
}

const REDUCE = window.matchMedia?.('(prefers-reduced-motion: reduce)');

/** Reduced motion: every moment shows its still, complete frame instead of moving. */
export const still = () => REDUCE?.matches ?? false;
