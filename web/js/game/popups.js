// Bonus pop-ups (docs/game-elements-plan.md; the proposal's board 05; Hillel: "Yes, HUD strip"):
// real events only, shown in the HUD's slot above the lane, never on it. One at a time for 1.2
// seconds of the take, none in a take's first 2 seconds, a newer one waiting for the one showing
// (only the newest waits). Times are the take's own position, from the audio clock, so a pause
// holds a pop-up where it is. A screen reader hears at most one a phrase (or, with no phrase to go
// by, one every SAY_GAP seconds); a `quiet` pop-up (the bonus that follows its combo) is only seen.

import { reactive } from 'vue';
import { tk } from '../i18n.js';
import { DAILY_XP_CAP, XP, dayOf } from '../course.js';
import { STRICTNESS } from '../scoring.js';

// The rules and the queue are pure (game/combo.js), so a public page's lane can use them too (the landing's demo).
export { BONUS_FROM, COMBO_STEP, POPUP_SECONDS, QUIET_START, RATE_MAX, RATE_STEP, SAY_GAP, bonusRate, comboMilestone, rateRaised } from './combo.js';
import { makePopupQueue } from './combo.js';

/** The pop-ups' words, by kind. */
export const WORDS = { combo: tk('game.popup.combo'), phrase: tk('game.popup.phrase'), bonus: tk('game.popup.bonus'), hold: tk('game.popup.hold'), rate: tk('game.popup.rate') };

/**
 * How much combo bonus today's cap still has room for once the exercise's own award is in (its
 * least: one star), so the pop-up never promises XP the day won't pay.
 */
export function bonusRoom(progress, at = Date.now()) {
    const today = progress?.xp?.days?.[dayOf(at)] ?? 0;
    return Math.max(0, DAILY_XP_CAP - today - XP.exercise - XP.star);
}

export const HOLD_SECONDS = 1.5; // a note this long or longer can be "held"
const HOLD_SUNG = 0.8; // of its time with your voice on it
const HOLD_TUNED = 0.85; // of those readings in tune
const HOLD_TAIL = 0.25; // seconds at its end, every reading in tune: held to the end
const HOLD_GAP = 0.1; // seconds: a longer gap between readings is time not sung

const folded = (midi, target) => target + ((((midi - target) % 12) + 18) % 12) - 6; // any octave counts, as in the score

/**
 * "Held it!": a long note (`start` to `end` in song seconds, sounding `pitch`) sung through and in
 * tune to its end, judged from the live line's own points (livevoice.js, `{ seconds, midi, alpha }`)
 * at the scorer's standard tolerance. Pure.
 */
export function heldToEnd(points, { start, end, pitch }, tolerance = STRICTNESS.standard) {
    if (end - start < HOLD_SECONDS) return false;
    const inside = points.filter((p) => p.seconds >= start && p.seconds < end && p.alpha === 1 && p.midi !== null);
    if (inside.length < 2) return false;
    let sung = 0;
    for (let i = 1; i < inside.length; i++) sung += Math.min(HOLD_GAP, inside[i].seconds - inside[i - 1].seconds);
    if (sung < (end - start) * HOLD_SUNG) return false;
    const tuned = (p) => Math.abs(folded(p.midi, pitch) - pitch) * 100 <= tolerance;
    const tail = inside.filter((p) => p.seconds >= end - HOLD_TAIL);
    return tail.length > 0 && tail.every(tuned) && inside.filter(tuned).length >= inside.length * HOLD_TUNED;
}

/** A HUD's pop-ups (game/combo.js's queue), reactive for a view. */
export const popupQueue = () => makePopupQueue(reactive);
