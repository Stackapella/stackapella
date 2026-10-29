// Bonus pop-ups (docs/game-elements-plan.md; the proposal's board 05; Hillel: "Yes, HUD strip"):
// real events only, shown in the HUD's slot above the lane, never on it. One at a time for 1.2
// seconds of the take, none in a take's first 2 seconds, a newer one waiting for the one showing
// (only the newest waits). Times are the take's own position, from the audio clock, so a pause
// holds a pop-up where it is. A screen reader hears at most one a phrase (or, with no phrase to go
// by, one every SAY_GAP seconds); a `quiet` pop-up (the bonus that follows its combo) is only seen.

import { reactive } from 'vue';
import { tk } from '../i18n.js';
import { DAILY_XP_CAP, XP, dayOf } from '../course.js';

export const POPUP_SECONDS = 1.2;
export const QUIET_START = 2; // seconds into a take before the first
export const COMBO_STEP = 10; // "10 in a row!", then 20, 30...
export const BONUS_FROM = 10; // the combo bonus: a note lit while a run of this many or more holds earns 1 XP
export const SAY_GAP = 6; // seconds: with no phrase to go by, a screen reader hears at most one pop-up this often

/** The pop-ups' words, by kind. */
export const WORDS = { combo: tk('game.popup.combo'), phrase: tk('game.popup.phrase'), bonus: tk('game.popup.bonus') };

/**
 * How much combo bonus today's cap still has room for once the exercise's own award is in (its
 * least: one star), so the pop-up never promises XP the day won't pay.
 */
export function bonusRoom(progress, at = Date.now()) {
    const today = progress?.xp?.days?.[dayOf(at)] ?? 0;
    return Math.max(0, DAILY_XP_CAP - today - XP.exercise - XP.star);
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
