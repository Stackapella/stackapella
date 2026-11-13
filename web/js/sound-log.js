// The last few sound problems on this device, for Send diagnostics (contract/api.md support.create, context.audioErrors;
// the finish-line audit, 2026-11-13): the mic refused or failing, the audio stopping under a take, an uncaught error in
// the sound code. Each line is the time, the screen (its token taken out) and what failed, never audio, words anyone
// wrote or who it was. Kept for this tab only (sessionStorage), at most MAX lines, and sent only when the person sends
// a message to us with their device's details switched on, after seeing every line.

import { session } from './storage.js';

const KEY = 'stackapella.soundLog';
export const MAX = 20; // the server takes 20 lines (context.audioErrors)
const LINE = 300; // and 500 characters a line: well under it
// Addresses that carry a token or a code (as errors.js reports them): their pattern only.
const TOKEN_PATHS = [/^\/(invite|early|join|choir|teacher|beta|challenge|class|notice)\/[^/]+/, /^\/(verify|reset|email\/confirm|email\/revert|signin\/link)(\/.*)?$/];

/** The screen as it goes in a line: no query or hash, and a token as its pattern. */
export function screenOf(path) {
    const bare = String(path).split(/[?#]/)[0] || '/';
    for (const pattern of TOKEN_PATHS) {
        const found = bare.match(pattern);
        if (found) return found[1] ? `/${found[1]}/:token` : found[0].replace(/\/[^/]+$/, '/:token');
    }
    return bare.replace(/\/p\/[\w-]+/, '/p/:song');
}

/** One line: `14:02:31 /learn/1-match mic: NotAllowedError Permission denied`. */
export function lineFor(what, error, { at = new Date(), path = location.pathname } = {}) {
    const why = error ? `: ${error.name ?? 'Error'}${error.message ? ` ${error.message}` : ''}` : ''; // i18n-ignore: sent to us, never shown as words
    return `${at.toISOString().slice(11, 19)} ${screenOf(path)} ${what}${why}`.slice(0, LINE);
}

/** The lines kept, oldest first. */
export function soundLog() {
    try {
        const kept = JSON.parse(session.get(KEY) ?? '[]');
        return Array.isArray(kept) ? kept.filter((line) => typeof line === 'string').slice(-MAX) : [];
    } catch {
        return [];
    }
}

/** Notes a sound problem (`what` in a word or two, the error if there is one). Never throws. */
export function noteSound(what, error = null) {
    try {
        const lines = soundLog();
        const line = lineFor(what, error);
        // The same failure again and again (a retry loop) is one line, not twenty.
        if (lines.at(-1)?.slice(9) === line.slice(9)) return;
        session.set(KEY, JSON.stringify([...lines, line].slice(-MAX)));
    } catch (failed) {
        console.warn('A sound problem was not noted', failed);
    }
}

/** Whether an uncaught error came from the sound code (the engine, its worklets, audio.js): its stack says. */
export function fromSound(error) {
    return /\/engine\/|\/js\/audio\.js|worklet|AudioContext|AudioBuffer|getUserMedia|MediaStream/i.test(`${error?.stack ?? ''} ${error?.message ?? ''}`);
}
