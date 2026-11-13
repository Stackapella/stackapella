// A range the free range test found (docs/retention-plan.md, idea 7; docs/improvement-log.md): its result's link opens
// the app with ?range=A2-D4, letter names and nothing else, so the singer's range is known from the first screen: no
// range check asks, and Home's song picks are ranked to fit it. It's kept with the course's progress on this device (and
// the account's, once signed in), and only when no range is known yet: a range sung here is never overwritten.

import { count } from './usage.js';
import { knownRange, loadLearning, setRange } from './stores/learning.js';

const NOTE = /^([A-G])(#|♯|b|♭)?(-?\d)$/;
const STEPS = { C: 0, D: 2, E: 4, F: 5, G: 7, A: 9, B: 11 };
const LOWEST = 24; // C1
const HIGHEST = 96; // C7
const LEAST = 5; // a range narrower than a fourth isn't a range test's answer
const MOST = 48; // nor one wider than four octaves

/** A letter-named note ("A2", "C#4", "Bb3") as MIDI, or null. */
export function noteMidi(name) {
    const m = NOTE.exec(String(name ?? '').trim());
    if (!m) return null;
    const accidental = m[2] === '#' || m[2] === '♯' ? 1 : m[2] === 'b' || m[2] === '♭' ? -1 : 0;
    return (Number(m[3]) + 1) * 12 + STEPS[m[1]] + accidental;
}

/** ?range=A2-D4 as { low, high } in MIDI, or null when it isn't a range a voice could have. */
export function parseRange(value) {
    const parts = String(value ?? '').split('-');
    // A minus sign is only an octave's (C-1), never here: two names exactly.
    if (parts.length !== 2) return null;
    const [low, high] = parts.map(noteMidi);
    if (low === null || high === null || low < LOWEST || high > HIGHEST) return null;
    if (high - low < LEAST || high - low > MOST) return null;
    return { low, high };
}

/**
 * Takes ?range= from the address (once; the address made plain), and keeps it as the singer's range unless one is
 * known already. Counted as tool_range_in when it's kept. Returns the range kept, or null.
 */
export async function takeToolRange(search = location.search) {
    const params = new URLSearchParams(search);
    if (!params.has('range')) return null;
    const range = parseRange(params.get('range'));
    params.delete('range');
    const rest = params.toString();
    history.replaceState(history.state, '', location.pathname + (rest ? `?${rest}` : '') + location.hash);
    if (!range) return null;
    await loadLearning();
    if (knownRange()) return null;
    await setRange(range.low, range.high, { source: 'measured' });
    count('tool_range_in');
    return range;
}
