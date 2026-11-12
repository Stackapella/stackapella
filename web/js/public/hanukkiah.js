// The Jewish songs' hanukkiah (server App\Support\Pages\Hanukkiah; Hillel, 2026-11-12: "have it so the right number of
// lights are lit when you view it for the day of the holiday"). During Hanukkah the shamash and that night's candles are
// lit: candle 1 is the far right as you face the lamp, and each night adds the next to its left. A night begins at
// sundown and holds through the next day until the next evening. The page is cached, so it's counted here, in the
// visitor's own date and time; sundown is taken as SUNDOWN local time (no location is asked for or kept: decisions.md,
// 2026-11-12). Outside Hanukkah, and without this script, every candle is lit.

/** Sundown, as hours and minutes of the visitor's own day. */
export const SUNDOWN = [16, 30];

/** Hanukkah has eight nights. */
export const NIGHTS = 8;

/**
 * The night of Hanukkah at `now` (1 to 8), or null outside it. `firsts` are each year's first evening, as Y-m-d.
 * @param {Date} now
 * @param {string[]} firsts
 */
export function night(now, firsts) {
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const evening = now.getHours() * 60 + now.getMinutes() >= SUNDOWN[0] * 60 + SUNDOWN[1];
    for (const first of firsts) {
        const [y, m, d] = first.split('-').map(Number);
        if (!y || !m || !d) continue;
        // Whole local days from the first evening's date (rounded, so a clock change in between doesn't shift it), less
        // one before sundown: the night before is still on.
        const days = Math.round((today - new Date(y, m - 1, d)) / 864e5) - (evening ? 0 : 1);
        if (days >= 0 && days < NIGHTS) return days + 1;
    }
    return null;
}

/** Lights `lamp`'s shamash and its first `n` candles, the rest unlit (a wick, no flame); null lights them all. */
export function light(lamp, n) {
    for (const candle of lamp.querySelectorAll('[data-hk]')) candle.toggleAttribute('data-unlit', n !== null && Number(candle.dataset.hk) > n);
    if (n === null) delete lamp.dataset.night;
    else lamp.dataset.night = String(n);
}

for (const lamp of document.querySelectorAll('[data-hanukkiah]')) light(lamp, night(new Date(), lamp.dataset.hanukkiah.split(' ')));
