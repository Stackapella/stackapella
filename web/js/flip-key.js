// The flip lessons' song tag key (docs/style-courses-plan.md): from style-scores.js, on its own so a lesson that only
// needs the tag's key (views/learn.js) doesn't download every style's scoring (the first-visit speed pass, 2026-11-13).

export const FLIP_KEYS = [0, 7, 5]; // the song tag's key: the line's own, else its V, else its IV
export const FLIP_KEY_MOVES = [0, 1, -1, 2, 3]; // semitones the tag may move from the flip: its top stays 1 to 5 above it

/**
 * Where a song's yodel tag sits (`base: 'flipKey'`), in semitones above the comfortable note: the
 * flip note (`flip`), moved by the first of FLIP_KEY_MOVES that puts the tag's home note (two below
 * its flip note, as in On the chord) in the line's key (`key`, the line's home note in semitones
 * above the comfortable note), else in its V, else its IV. Five moves in a row always meet one of
 * the three keys, so this never fails.
 */
export function flipKeyBase(flip, key = 0) {
    const pc = (n) => ((n % 12) + 12) % 12;
    for (const k of FLIP_KEYS) {
        for (const move of FLIP_KEY_MOVES) {
            if (pc(flip + move - 2 - key - k) === 0) return flip + move;
        }
    }
    return flip;
}
