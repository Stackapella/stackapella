// What's next (docs/more-features-plan.md, feature 13): "the vision's measure is the second song".
// After a finished harmony (and on the course map when no lesson waits), up to three songs, each
// with why: one whose parts fit your range, one a step harder, and the song of the month. Pure.

import { suggestTranspose } from './dsp.js';

const LEVELS = ['beginner', 'intermediate', 'advanced'];

/** The part of `song` that best suits `range` (in its key, or within a few semitones), or null. */
function fittingPart(song, range) {
    if (!range) return null;
    const ranked = song.parts.map((part) => ({ part, ...suggestTranspose(range, part.range) })).sort((a, b) => Number(b.fits) - Number(a.fits) || Math.abs(a.transpose) - Math.abs(b.transpose));
    return ranked[0]?.fits ? ranked[0].part : null;
}

/**
 * Up to three suggestions: `[{ kind: 'fits' | 'harder' | 'month', song, part? }]`, never the song
 * just sung or one already finished.
 * - `songs`: the shelf (listTemplates: full songs and the catalogue's summaries);
 * - `current`: the song just finished (its id and difficulty), or null;
 * - `range`: the learner's range ({ low, high } MIDI), or null;
 * - `finished`: ids of songs already finished;
 * - `startable(song)`: whether it can be started now (free, the song of the month, or Plus).
 */
export function whatsNext({ songs, current = null, range = null, finished = [], startable = () => true, now = Date.now() }) {
    const skip = new Set([current?.id, ...finished].filter(Boolean));
    // Songs that can be started now come first (a free learner sees Plus songs only when nothing else fits).
    const open = songs.filter((s) => !skip.has(s.id)).sort((a, b) => Number(startable(b)) - Number(startable(a)));
    const out = [];
    const taken = new Set();
    const add = (kind, song, part = null) => {
        if (!song || taken.has(song.id)) return;
        taken.add(song.id);
        out.push({ kind, song, part });
    };
    // One whose parts fit your range, at the same difficulty first.
    const level = current?.difficulty ?? 'beginner';
    const fits = open.map((song) => ({ song, part: fittingPart(song, range) })).filter((s) => s.part);
    const fit = fits.find((s) => startable(s.song) && s.song.difficulty === level) ?? fits.find((s) => startable(s.song)) ?? fits.find((s) => s.song.difficulty === level) ?? fits[0];
    if (fit) add('fits', fit.song, fit.part);
    // One step harder.
    const harder = LEVELS[Math.min(LEVELS.length - 1, LEVELS.indexOf(level) + 1)];
    add('harder', open.find((s) => s.difficulty === harder && !taken.has(s.id)));
    // The song of the month.
    add('month', open.find((s) => s.freeUntil && Date.parse(s.freeUntil) > now));
    return out.slice(0, 3);
}
