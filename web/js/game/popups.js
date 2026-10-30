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

export const POPUP_SECONDS = 1.2;
export const QUIET_START = 2; // seconds into a take before the first
export const COMBO_STEP = 10; // "10 in a row!", then 20, 30...
export const BONUS_FROM = 10; // the combo bonus: a note lit while a run of this many or more holds earns 1 XP
export const SAY_GAP = 6; // seconds: with no phrase to go by, a screen reader hears at most one pop-up this often

/** The pop-ups' words, by kind. */
export const WORDS = { combo: tk('game.popup.combo'), phrase: tk('game.popup.phrase'), bonus: tk('game.popup.bonus'), hold: tk('game.popup.hold') };

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

/** Whether a combo of `count` earns a pop-up: 10, 20, 30... */
export const comboMilestone = (count) => count > 0 && count % COMBO_STEP === 0;

/**
 * A HUD's pop-ups. `offer(popup, at)` (popup: `{ kind, count?, phrase? }`) shows it or keeps it next;
 * `tick(at)` moves on as the take plays; `clear(from)` when a take starts (at `from`) or stops. `now` is what shows,
 * `say` what a screen reader is told (at most once a phrase).
 */
export function popupQueue() {
    const queue = reactive({ now: null, next: null, say: null, saidPhrase: null, saidAt: -Infinity, shown: 0, origin: 0 });
    const show = (popup, at) => {
        queue.shown++;
        queue.now = { ...popup, id: queue.shown, until: at + POPUP_SECONDS };
        if (popup.quiet) return;
        const phrase = popup.phrase ?? null;
        const fresh = phrase !== null ? phrase !== queue.saidPhrase : at - queue.saidAt >= SAY_GAP;
        if (!fresh) return;
        queue.saidPhrase = phrase;
        queue.saidAt = at;
        queue.say = { ...popup, id: queue.shown };
    };
    queue.offer = (popup, at) => {
        if (at - queue.origin < QUIET_START) return false;
        if (queue.now && at < queue.now.until) {
            queue.next = popup;
            return true;
        }
        show(popup, at);
        return true;
    };
    queue.tick = (at) => {
        if (!queue.now || at < queue.now.until) return;
        queue.now = null;
        if (queue.next) {
            const next = queue.next;
            queue.next = null;
            show(next, at);
        }
    };
    queue.clear = (origin = 0) => {
        queue.origin = origin;
        queue.now = null;
        queue.next = null;
        queue.saidPhrase = null;
        queue.saidAt = -Infinity;
        queue.say = null;
    };
    return queue;
}
