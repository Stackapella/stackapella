// The singing test's items (docs/singing-test-v1.md, test version 1): the same every time, in the singer's own range.
// Every note sits within a fifth of their anchor (the "Find your note" note), so a bass and a soprano sing equally hard
// tests. Framework-free: the flow (views) and the unit tests both use it.

export const TEST_VERSION = 1;
export const SCORING_VERSION = 1;

/** Within a fifth of the anchor: no item ever asks for more. */
export const REACH = 7;

// Match a note: one practice note, then 12, as steps from the anchor (small and larger leaps, up and down).
const MATCH = [0, 2, -3, 5, 0, -5, 4, -2, 7, -4, 3, -7, 1];
// Sing it back: one practice pattern, then 6 patterns of four notes, as steps from the anchor (scale-wise and small
// leaps, both directions, each starting somewhere new).
const BACK = [
    [0, 2, 4, 2],
    [0, 2, 4, 5],
    [4, 2, 0, -1],
    [0, 4, 2, 5],
    [-3, 0, 2, 0],
    [5, 4, 2, 0],
    [-1, 2, 0, -3],
];
// Hold it: twice for 4 seconds, their note, then a step above.
const HOLD = [0, 2];

export const HOLD_SECONDS = 4;

/**
 * The test for an anchor (a MIDI note, rounded to the nearest semitone for the items): practice items first, marked.
 * `{ match: [{ target, practice }], back: [{ pattern, practice }], hold: [{ target }] }`.
 */
export function buildTest(anchor) {
    const root = Math.round(anchor);
    return {
        match: MATCH.map((step, i) => ({ target: root + step, practice: i === 0 })),
        back: BACK.map((steps, i) => ({ pattern: steps.map((s) => root + s), practice: i === 0 })),
        hold: HOLD.map((step) => ({ target: root + step })),
    };
}

/** A comfortable note moved this far, in two tests in a row, moves the test's anchor (else the first test's stays). */
export const ANCHOR_MOVE = 3;

/**
 * The anchor this test uses: the first test's (its anchor and octave stay, so tests compare), unless the note found now
 * and the one found last time are both more than ANCHOR_MOVE semitones from it, on the same side. `history`: earlier
 * tests of this testVersion, oldest first, each with `anchor` and `found` (the note found that day). Returns
 * `{ anchor, moved }`.
 */
export function anchorFor(history, found) {
    const same = history.filter((t) => t.testVersion === TEST_VERSION && Number.isFinite(t.anchor));
    if (!same.length) return { anchor: roundAnchor(found), moved: false };
    const kept = same.at(-1).anchor;
    const last = same.at(-1).found;
    const away = (note) => Number.isFinite(note) && Math.abs(note - kept) > ANCHOR_MOVE;
    const sameSide = Number.isFinite(last) && Math.sign(found - kept) === Math.sign(last - kept);
    if (away(found) && away(last) && sameSide) return { anchor: roundAnchor(found), moved: true };
    return { anchor: kept, moved: false };
}

/** The contract keeps the anchor to one decimal. */
export const roundAnchor = (midi) => Math.round(midi * 10) / 10;
