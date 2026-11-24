// Progress you can see (docs/practice-plan.md, "Seeing the progress"; Brief F, version 1): the sums behind Your voice's
// measures. The pitch chart made honest (a rolling average, each take a faint dot, an arrow only for a change that's
// large and well counted) and the range over time (progress.rangeHistory: one entry a date, merged keeping every date,
// the later `at` on a tie). Pure, no imports.

export const ROLLING = 5; // takes in the rolling average
export const CHANGE_TAKES = 10; // takes at least on each side of a change worth an arrow
export const CHANGE_POINTS = 8; // points at least between them
export const RANGE_MOST = 120; // range history entries kept (the contract)

/** The rolling average of `values` (oldest first) over the last `window` values at each point. */
export function rolling(values = [], window = ROLLING) {
    return values.map((_, i) => {
        const slice = values.slice(Math.max(0, i - window + 1), i + 1);
        return slice.reduce((sum, v) => sum + v, 0) / slice.length;
    });
}

/**
 * A change worth saying: the mean of the first CHANGE_TAKES against the last CHANGE_TAKES (oldest first), only with twice
 * that many takes and CHANGE_POINTS or more between them. `{ from, to, dir }` ('up' or 'down'), else null.
 */
export function change(values = []) {
    if (values.length < CHANGE_TAKES * 2) return null;
    const mean = (list) => list.reduce((sum, v) => sum + v, 0) / list.length;
    const from = Math.round(mean(values.slice(0, CHANGE_TAKES)));
    const to = Math.round(mean(values.slice(-CHANGE_TAKES)));
    if (Math.abs(to - from) < CHANGE_POINTS) return null;
    return { from, to, dir: to > from ? 'up' : 'down' };
}

const dayKey = (at) => new Date(at - new Date(at).getTimezoneOffset() * 60000).toISOString().slice(0, 10);

/** The range history with a range set now: measured or chosen only (a part picked or a range cleared isn't one). */
export function withRange(list = [], range, how, at = Date.now()) {
    if (!range || (how !== 'measured' && how !== 'chosen')) return list ?? [];
    const low = Math.round(range.low);
    const high = Math.round(range.high);
    if (!(low >= 0 && high <= 127 && low <= high)) return list ?? [];
    return mergeRangeHistory(list, [{ date: dayKey(at), low, high, how, at }]);
}

/** Every date from both sides, one entry a date (the later `at`), oldest first, the newest RANGE_MOST kept. */
export function mergeRangeHistory(mine = [], theirs = []) {
    const out = new Map();
    for (const entry of [...(mine ?? []), ...(theirs ?? [])]) {
        if (!entry?.date) continue;
        const had = out.get(entry.date);
        if (!had || entry.at > had.at) out.set(entry.date, entry);
    }
    return [...out.values()].sort((a, b) => a.date.localeCompare(b.date)).slice(-RANGE_MOST);
}

export const RANGE_STEP = 2; // semitones: a whole step at either end, the least change worth saying

/**
 * How the range widened since the first kept day: `{ top, bottom, since }` in semitones gained (higher at the top, lower
 * at the bottom), only with two days kept and a whole step or more at an end; a range that narrowed is never said. Pure.
 */
export function rangeChange(history = []) {
    if (history.length < 2) return null;
    const first = history[0];
    const last = history.at(-1);
    const top = Math.max(0, last.high - first.high);
    const bottom = Math.max(0, first.low - last.low);
    if (top < RANGE_STEP && bottom < RANGE_STEP) return null;
    return { top: top >= RANGE_STEP ? top : 0, bottom: bottom >= RANGE_STEP ? bottom : 0, since: first.date };
}
