// The first screen a visit opens, once it can be used: lesson 1's way on showing (js/views/learn.js), or Home's first
// frame (js/views/projects.js). On a first visit main.js's fetchAhead waits for it before fetching the rest of the app,
// so the 113 KB of words and the next screens don't take the first screen's bandwidth (frontend2's look, Slow 4G,
// 2026-11-14: they loaded alongside lesson 1, before its Continue).

let mark;

/** Resolves when the first screen can be used. */
export const firstUsable = new Promise((resolve) => {
    mark = resolve;
});

/** Says the screen on show can be used (only the first call counts). */
export function markUsable() {
    mark();
}
