// A song's shape, for its cover on Home (Hillel: "make the bars match the actual structure of the
// song parts"): for each part, where it sings, as a few dozen numbers. A dash per phrase (a run of
// notes; a rest of a beat or more, or a new line of words, starts the next), placed on the song's
// length so a late entry is indented and a rest is a gap, each nudged by its phrase's pitch against
// the part's own middle. The server sends the same shape in the catalogue (`parts[].shape`) for the
// songs whose notes the app doesn't carry; the rules here and there are the same. Pure.

/** Thousandths of the song's length: the shape's unit. */
const SCALE = 1000;
/** At most this many dashes a part: the closest are joined beyond it, so a cover stays readable. */
const MOST = 24;
/** A rest at least this long (in beats) ends a phrase. */
const REST = 1;
/** The pitch nudge, in semitones either way. */
const LEAN = 6;

/**
 * One part's shape: `[[start, end, lean], ...]`, start and end in thousandths of the song (from
 * `from` to `to` beats), lean the phrase's average pitch less the part's, in whole semitones within
 * ±6. Empty for a part with no notes.
 */
export function partShape(notes, { from, to }) {
    const sung = (notes ?? []).filter((n) => Number.isFinite(n?.pitch) && n.length > 0).sort((a, b) => a.beat - b.beat);
    if (!sung.length || !(to > from)) return [];
    const phrases = [];
    for (const n of sung) {
        const last = phrases.at(-1);
        const end = last ? last.end : -Infinity;
        if (last && !n.br && n.beat - end < REST) {
            last.end = Math.max(end, n.beat + n.length);
            last.weight += n.length;
            last.sum += n.pitch * n.length;
        } else phrases.push({ start: n.beat, end: n.beat + n.length, weight: n.length, sum: n.pitch * n.length });
    }
    // Too many: join the two neighbours with the smallest gap between them, until few enough.
    while (phrases.length > MOST) {
        let at = 0;
        for (let i = 1; i < phrases.length - 1; i++) if (phrases[i + 1].start - phrases[i].end < phrases[at + 1].start - phrases[at].end) at = i;
        const [a, b] = phrases.splice(at, 2);
        phrases.splice(at, 0, { start: a.start, end: b.end, weight: a.weight + b.weight, sum: a.sum + b.sum });
    }
    const middle = sung.reduce((s, n) => s + n.pitch * n.length, 0) / sung.reduce((s, n) => s + n.length, 0);
    const place = (beat) => Math.max(0, Math.min(SCALE, Math.round(((beat - from) / (to - from)) * SCALE)));
    return phrases.map((p) => [place(p.start), place(p.end), Math.max(-LEAN, Math.min(LEAN, Math.round(p.sum / p.weight - middle)))]);
}

/** Every part's shape (`{ key, shape }`), on the song's length: its first note to the end of its last. */
export function songShape(template) {
    const parts = template?.parts ?? [];
    const all = parts.flatMap((p) => p.guide?.notes ?? []).filter((n) => Number.isFinite(n?.pitch));
    if (!all.length) return parts.map((p) => ({ key: p.key, shape: [] }));
    const from = Math.min(...all.map((n) => n.beat));
    const to = Math.max(...all.map((n) => n.beat + n.length));
    return parts.map((p) => ({ key: p.key, shape: partShape(p.guide?.notes, { from, to }) }));
}

// Worked out once a song: by the template itself, so a draft edited since is worked out afresh.
const known = new WeakMap();

/**
 * The cover's rows for a song: the catalogue's shapes (`parts[].shape`) when it sends them, else
 * worked out from the notes the app carries, else null (the cover falls back to its plain dashes).
 */
export function coverShape(template) {
    const parts = template?.parts ?? [];
    if (!parts.length) return null;
    if (parts.every((p) => Array.isArray(p.shape))) return parts.map((p) => ({ key: p.key, shape: p.shape }));
    if (!parts.some((p) => Array.isArray(p.guide?.notes))) return null;
    if (!known.has(template)) known.set(template, songShape(template));
    return known.get(template);
}
