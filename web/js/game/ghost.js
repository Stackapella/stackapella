// The ghost of your best take (docs/game-elements-plan.md; docs/game-server-spec.md, section 3; after
// Mario Kart 64's time-trial ghost): your own best take of a part, as a faint pitch line on the Sing
// lane under your live dot. Only ever your own: kept with your account (the server takes it only for
// the take whose score is your best on that part's board), or on this device for a guest. Its form is
// compact: 20 frames a second, each the MIDI pitch times 100 (0 for no pitch), as little-endian Int16
// in base64, with the song position of its first frame.

import { local } from '../storage.js';
import { call } from '../api.js';
import { account } from '../stores/account.js';
import { FRAME_RATE } from '../pitchtrack.js';
import { hzToMidi } from '../dsp.js';

export const GHOST_RATE = 20; // frames a second
export const GHOST_MAX_SECONDS = 120;
const KEY = 'stackapella.ghosts';
const KEEP = 20; // the most a device keeps (the oldest goes)

/**
 * A take's pitch track (pitchtrack.js: `{ hz, clarity }` at FRAME_RATE) as a ghost: `{ rate,
 * startSeconds, pitches }`. Each ghost frame is the middle voiced reading of its stretch, or 0.
 */
export function encodeGhost(track, { startSeconds = 0, minClarity = 0.5 } = {}) {
    const per = FRAME_RATE / GHOST_RATE;
    const frames = Math.min(Math.floor(track.hz.length / per), GHOST_RATE * GHOST_MAX_SECONDS);
    const values = new Int16Array(frames);
    for (let i = 0; i < frames; i++) {
        const heard = [];
        for (let k = Math.round(i * per); k < Math.round((i + 1) * per); k++) {
            if (track.hz[k] > 0 && (track.clarity?.[k] ?? 1) >= minClarity) heard.push(hzToMidi(track.hz[k]));
        }
        heard.sort((a, b) => a - b);
        values[i] = heard.length ? Math.round(heard[heard.length >> 1] * 100) : 0;
    }
    return { rate: GHOST_RATE, startSeconds, pitches: toBase64(values) };
}

/** A ghost as lane points: `[{ seconds, midi }]` in song time, with a gap (`null` midi) where nothing was sung. */
export function decodeGhost(ghost) {
    if (!ghost?.pitches) return [];
    const values = fromBase64(ghost.pitches);
    const rate = ghost.rate || GHOST_RATE;
    return Array.from(values, (v, i) => ({ seconds: (ghost.startSeconds ?? 0) + i / rate, midi: v ? v / 100 : null }));
}

function toBase64(values) {
    const bytes = new Uint8Array(values.length * 2);
    const view = new DataView(bytes.buffer);
    values.forEach((v, i) => view.setInt16(i * 2, v, true));
    let text = '';
    for (const b of bytes) text += String.fromCharCode(b);
    return btoa(text);
}

function fromBase64(text) {
    const raw = atob(text);
    const view = new DataView(new ArrayBuffer(raw.length));
    for (let i = 0; i < raw.length; i++) view.setUint8(i, raw.charCodeAt(i));
    return Array.from({ length: raw.length >> 1 }, (_, i) => view.getInt16(i * 2, true));
}

/** The key a ghost is kept under: the song's version, the part and the scorer (a new one starts over). */
export const ghostKey = ({ templateId, templateVersion, partKey, scoringVersion }) => `${templateId}:${templateVersion}:${partKey}:${scoringVersion}`;

function kept() {
    try {
        const all = JSON.parse(local.get(KEY) || '{}');
        return all && typeof all === 'object' ? all : {};
    } catch (error) {
        console.warn('The ghosts kept here are unreadable; starting again', error);
        return {};
    }
}

/** A guest's ghost for that part, from this device, or null. */
export const deviceGhost = (where) => kept()[ghostKey(where)] ?? null;

/** Keeps a guest's take as the ghost when it beats the one kept (the best only), within the device's few. */
export function keepDeviceGhost(where, ghost) {
    const all = kept();
    const key = ghostKey(where);
    if (all[key] && all[key].score >= ghost.score) return false;
    all[key] = { ...ghost, at: Date.now() };
    const keys = Object.keys(all).sort((a, b) => (all[b].at ?? 0) - (all[a].at ?? 0));
    for (const old of keys.slice(KEEP)) delete all[old];
    local.set(KEY, JSON.stringify(all));
    return true;
}

/**
 * A ghost's points where this part sits now: moved by any change of key since it was sung, and by
 * whole octaves so its middle is near the part's (a ghost sung an octave down still races the part).
 */
export function placeGhost(points, notes, { keyOffset = 0, transpose = 0 } = {}) {
    const sung = points.filter((p) => p.midi !== null).map((p) => p.midi).sort((a, b) => a - b);
    const part = notes.map((n) => n.pitch + transpose).sort((a, b) => a - b);
    if (!sung.length || !part.length) return points;
    const shift = transpose - keyOffset;
    const middle = sung[sung.length >> 1] + shift;
    const octaves = Math.round((part[part.length >> 1] - middle) / 12) * 12;
    return points.map((p) => (p.midi === null ? p : { ...p, midi: p.midi + shift + octaves }));
}

/**
 * Your ghost for a part (`where`: templateId, templateVersion, partKey, scoringVersion), or null:
 * your account's, signed in (none kept yet answers `data: null`, quietly), else this device's.
 */
export async function loadGhost(where) {
    if (!account.me) return deviceGhost(where);
    try {
        const ghost = (await call('boards.ghost.show', { params: { template: where.templateId, part: where.partKey } })).data;
        if (!ghost) return null;
        return ghost.templateVersion === where.templateVersion && ghost.scoringVersion === where.scoringVersion ? ghost : null;
    } catch (error) {
        if (error?.status !== 404) console.info('No ghost to race', error?.message ?? error);
        return null;
    }
}

/** Keeps a new best take's line with your account (only for the score that is now your best). */
export async function saveGhost(where, ghost) {
    await call('boards.ghost.update', { params: { template: where.templateId, part: where.partKey }, body: { templateVersion: where.templateVersion, scoringVersion: where.scoringVersion, ...ghost } });
}
