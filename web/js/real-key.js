// "Prefer the real key" (Hillel, 2026-11-02): a real voice plays only in the song's own key (recorded-guides.js
// recordingApplies), so when a part has one, the song opens in its own key whenever the singer's range covers the
// part within REAL_KEY_SLACK semitones, said in one line ("Real voices sing this key: Alto fits your range.").
// Otherwise the key moves to fit as before, and the synth sings. A key chosen from the header still wins (its
// callers ask only before a key is chosen).

import { recordingApplies } from './recorded-guides.js';
import { catalogueStore } from './lazy-stores.js';

export const REAL_KEY_SLACK = 2;

/** Whether `part` (a template part) sits in `range` ({ low, high } MIDI) within REAL_KEY_SLACK at the song's key. */
export function coversWithin(range, part) {
    return !!range && !!part?.range && part.range.low >= range.low - REAL_KEY_SLACK && part.range.high <= range.high + REAL_KEY_SLACK;
}

/**
 * The fit for `partKey` of `template` when its real voice decides the key: `{ transpose: 0, octave: 0, fits: true,
 * real: true }`, or null (no real voice for this version of the song, no range known, or the part too far from it).
 */
export async function realKeyFit(template, partKey, range) {
    const part = template?.parts?.find((p) => p.key === partKey);
    if (!coversWithin(range, part)) return null;
    const { loadCatalogue, versionsFor } = await catalogueStore();
    await loadCatalogue();
    const found = versionsFor(template.id, partKey);
    const recording = found ? (found.versions.find((v) => v.default) ?? found.versions[0]) : null;
    if (!recording || !recordingApplies({ recording, version: found.version, template })) return null;
    return { transpose: 0, octave: 0, fits: true, real: true };
}
