/**
 * Yodeling's five-minute flip cap (docs/courses/yodel.md, the voice-teacher review): about five
 * minutes of flips at a time, then a rest. Checked only as a flip lesson opens, never mid-lesson:
 * once this sitting has spent FLIP_CAP_MINUTES in flip lessons, the next one waits until
 * FLIP_REST_MINUTES after the last ended. A sitting ends after FLIP_REST_MINUTES without one. Kept
 * in memory only (a gentle cap, not a lock), and compared on open: no timers.
 */

export const FLIP_CAP_MINUTES = 5;
export const FLIP_REST_MINUTES = 10;

const MINUTE = 60000;
const SCORES = ['flip', 'flipFinder'];

/** A lesson that flips: any step scored on flips or finding the flip, or set from the flip note. */
export function flipLesson(lesson) {
    return !!lesson?.steps?.some((s) => SCORES.includes(s.exercise?.score) || ['flip', 'flipKey'].includes(s.exercise?.base));
}

/**
 * When flip lessons open again (ms), or null when they're open now, from `spells` (`{ from, to }`
 * in ms, oldest first: the time spent in flip lessons) at `now`.
 */
export function flipRestUntil(spells, now) {
    if (!spells.length) return null;
    const last = spells[spells.length - 1];
    const until = last.to + FLIP_REST_MINUTES * MINUTE;
    if (now >= until) return null;
    let spent = 0;
    for (let i = spells.length - 1; i >= 0; i--) {
        spent += spells[i].to - spells[i].from;
        if (i > 0 && spells[i].from - spells[i - 1].to >= FLIP_REST_MINUTES * MINUTE) break;
    }
    return spent >= FLIP_CAP_MINUTES * MINUTE ? until : null;
}

const spells = [];
let open = null;

/** A flip lesson opened (`at`, ms). */
export function flipStarted(at = Date.now()) {
    open = at;
}

/** The open flip lesson closed: its time joins the sitting. */
export function flipStopped(at = Date.now()) {
    if (open === null) return;
    spells.push({ from: open, to: Math.max(open, at) });
    open = null;
    if (spells.length > 20) spells.shift();
}

/** When flip lessons open again in this tab, or null. */
export function flipRest(now = Date.now()) {
    return flipRestUntil(spells, now);
}
