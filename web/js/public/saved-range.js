// The singer's range as the free range test saved it on this device (js/public/tools/vocal-range-test.js
// writes it), for the tools that use it: the vocal key finder fits songs to it, the note frequency chart
// marks it. Only this device's storage; nothing is sent. Tested in tests/vocal-key-finder.test.js.

export const RANGE_STORE = 'stackapella.tools.range';

/** The latest saved range, `{ low, high }` in MIDI, or null (none saved, or storage blocked). */
export function savedRange() {
    try {
        const raw = JSON.parse(localStorage.getItem(RANGE_STORE) ?? 'null');
        // The settings store's record ({ v, updatedAt, settings }, tool-prefs.js), or the bare object kept before it.
        const saved = raw && typeof raw === 'object' && 'settings' in raw ? raw.settings : raw;
        const last = Array.isArray(saved?.history) ? saved.history[0] : null;
        return Number.isInteger(last?.low) && Number.isInteger(last?.high) && last.low < last.high ? { low: last.low, high: last.high } : null;
    } catch (error) {
        console.warn('The saved range could not be read', error);
        return null;
    }
}
