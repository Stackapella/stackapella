// The combo's rules and the pop-ups' queue (docs/game-feel-plan.md, slice 1), pure, so the app's views (through
// game/popups.js) and a public page's lane (the landing's demo, js/public/cabinet.js) count the same way.

export const POPUP_SECONDS = 1.2;
export const QUIET_START = 2; // seconds into a take before the first
export const COMBO_STEP = 10; // "10 in a row!", then 20, 30...
export const BONUS_FROM = 10; // the combo bonus: a note lit while a run of this many or more holds earns XP
export const RATE_STEP = 10; // the bonus's rate goes up every this many in a row
export const RATE_MAX = 3;
export const SAY_GAP = 6; // seconds: with no phrase to go by, a screen reader hears at most one pop-up this often

/**
 * XP each note lit earns while the run holds, 0 under 10 in a row, then 1, 2 from 20 and 3 from 30.
 * Paid only on a lesson's own step and within the day's cap; a board's score never depends on it.
 */
export const bonusRate = (combo) => Math.min(RATE_MAX, Math.floor(combo / RATE_STEP));

/** Whether the rate just went up (at 20 and 30 in a row): "Bonus x2!" takes the combo pop-up's place. */
export const rateRaised = (combo) => bonusRate(combo) >= 2 && combo === bonusRate(combo) * RATE_STEP;

/** Whether a combo of `count` earns a pop-up: 10, 20, 30... */
export const comboMilestone = (count) => count > 0 && count % COMBO_STEP === 0;

/**
 * A HUD's pop-ups. `offer(popup, at)` (popup: `{ kind, count?, phrase? }`) shows it or keeps it next;
 * `tick(at)` moves on as the take plays; `clear(from)` when a take starts (at `from`) or stops. `now` is what shows,
 * `say` what a screen reader is told (at most once a phrase). `wrap` makes its state reactive for a view.
 */
export function makePopupQueue(wrap = (state) => state) {
    const queue = wrap({ now: null, next: null, say: null, saidPhrase: null, saidAt: -Infinity, shown: 0, origin: 0 });
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
