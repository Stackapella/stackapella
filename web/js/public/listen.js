// The course pages' opt-in mic (Perfect your pitch's Bullseye, Find your note): listen() and
// reasonOf() are the shared live mic (live-mic.js, re-exported here so the demos keep their import),
// and dart() says where a sung note landed, judged as the Bullseye lesson judges it. Nothing is
// recorded or sent. Tested in tests/public-listen.test.js.

import { hz } from './voices.js';
import { landing, LANDING_FULL } from '../pitch-skills.js';

export { listen, reasonOf } from './live-mic.js';

const RATE = 100; // readings a second, as the lessons score them

/**
 * Where a sung note landed against `midi` (any octave), from listen()'s readings: `{ cents, landed,
 * clean, start }`, `landed` within the lesson's ±25 cents, `clean` when it started on the note
 * rather than sliding in ('scoop', 'fall' or 'slide' in `start`). Null when nothing was heard.
 */
export function dart(frames, midi) {
    if (!frames.length) return null;
    // The readings come as fast as the screen draws: laid on the lessons' even 100 a second.
    const from = frames[0].at;
    const cents = new Array(Math.max(1, Math.round((frames.at(-1).at - from) * RATE) + 1)).fill(null);
    for (const f of frames) if (f.clear && f.hz > 0) cents[Math.round((f.at - from) * RATE)] = 1200 * Math.log2(f.hz / hz(midi));
    // The gaps between readings (a slot or two), held from the one before.
    for (let i = 1; i < cents.length; i++) if (cents[i] === null && cents[i - 1] !== null && cents.slice(i + 1, i + 3).some((x) => x !== null)) cents[i] = cents[i - 1];
    const l = landing(cents, RATE);
    if (!l) return null;
    return { cents: l.cents, landed: Math.abs(l.cents) <= LANDING_FULL, clean: l.start === 'clean', start: l.start };
}
