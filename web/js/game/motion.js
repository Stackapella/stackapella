// The game moments' motion (docs/game-elements-plan.md, section 1): a run drawn on
// requestAnimationFrame that a tap snaps to its end, and count-ups that land on the real number.
// Only what's seen moves this way; anything heard is scheduled on the AudioContext clock (chip.js).

import { still } from './effects.js';

/** Eases out (fast, then settling), for counts and slides. */
export const easeOut = (p) => 1 - (1 - p) ** 3;

/** The number shown `p` (0 to 1) of the way from `from` to `to`, whole and never past it. */
export function countAt(from, to, p) {
    const at = from + (to - from) * easeOut(Math.max(0, Math.min(1, p)));
    return to >= from ? Math.min(to, Math.floor(at + 1e-9)) : Math.max(to, Math.ceil(at - 1e-9));
}

/**
 * Runs `frame(p)` from 0 to 1 over `ms`. `skip()` jumps to the end (frame(1)), `stop()` leaves it where
 * it is; either way `done` resolves once. Reduced motion (or `ms` of 0) draws only the end.
 */
export function run(ms, frame, { motion = !still() } = {}) {
    let raf = null;
    let start = null;
    let over = false;
    let resolve;
    const done = new Promise((r) => (resolve = r));
    const end = (draw) => {
        if (over) return;
        over = true;
        if (raf !== null) cancelAnimationFrame(raf);
        if (draw) frame(1);
        resolve();
    };
    const tick = (now) => {
        start ??= now;
        const p = Math.min(1, (now - start) / ms);
        if (p >= 1) return end(true);
        frame(p);
        raf = requestAnimationFrame(tick);
    };
    if (!motion || ms <= 0) end(true);
    else raf = requestAnimationFrame(tick);
    return { skip: () => end(true), stop: () => end(false), done, get over() { return over; } };
}

/**
 * A sequence of steps, each `{ at, ms, frame }` (ms from the start), run as one: `skip()` lands
 * every step on its end at once. `p` per step is 0 before its `at`.
 */
export function sequence(steps, options = {}) {
    const total = Math.max(0, ...steps.map((s) => s.at + s.ms));
    return run(total, (p) => {
        const now = p * total;
        for (const step of steps) step.frame(p >= 1 ? 1 : Math.max(0, Math.min(1, (now - step.at) / Math.max(1, step.ms))));
    }, options);
}
