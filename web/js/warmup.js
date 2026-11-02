// A teacher's warm-up, shared by link (docs/warmup-builder-plan.md): the whole warm-up travels in
// the link's fragment (stackapella.com/warmup#<code>), which browsers never send to a server, so no
// server sees a warm-up, who made it or who opened it. The code is a short base64url of compact
// JSON, version 1. Everything is kept within the plan's limits here, whatever a link says, and the
// student's own range places it. Pure: no DOM, no audio (tests/warmup.test.js).

/** The sounds a warm-up is sung on: a fixed list, so a link carries no lyric text. */
export const SOUNDS = ['ah', 'ee', 'oo', 'oh', 'hum', 'trill'];
/** The speeds, as tempos. */
export const SPEEDS = { slow: 66, medium: 84, quick: 104 };
export const LIMITS = { notes: 16, span: 18, lowest: -5, start: [-7, 5], climb: [0, 7], tempo: [50, 120], name: 40 };
/** The last note is held this many beats; the others are a beat each. */
const HELD = 3;
const VERSION = 1;

const clamp = (value, [low, high]) => Math.max(low, Math.min(high, Math.round(Number(value) || 0)));

/**
 * A warm-up made safe: `{ steps, sound, tempo, start, climb, name }`, or null when there's nothing
 * to sing. `steps` are semitones from the warm-up's first note (do = 0), up to 16 of them, within an
 * octave and a half; the name is plain text, at most 40 characters, with no line breaks.
 */
export function cleanWarmup(raw) {
    if (!raw || typeof raw !== 'object' || !Array.isArray(raw.steps)) return null;
    const steps = raw.steps.slice(0, LIMITS.notes).map((s) => Math.round(Number(s)));
    // A note outside the limits makes the link invalid, rather than a warm-up quietly different from the one sent.
    if (!steps.length || steps.some((s) => !Number.isFinite(s) || s < LIMITS.lowest || s > LIMITS.span + LIMITS.lowest)) return null;
    if (Math.max(...steps) - Math.min(...steps) > LIMITS.span) return null;
    const name = typeof raw.name === 'string' ? raw.name.replace(/[\u0000-\u001f\u007f]+/g, ' ').trim().slice(0, LIMITS.name) : '';
    return {
        steps,
        sound: SOUNDS.includes(raw.sound) ? raw.sound : 'ah',
        tempo: clamp(raw.tempo ?? SPEEDS.medium, LIMITS.tempo),
        start: clamp(raw.start ?? 0, LIMITS.start),
        climb: clamp(raw.climb ?? 0, LIMITS.climb),
        name,
    };
}

/** A warm-up as its link's code: base64url of compact JSON. */
export function encodeWarmup(warmup) {
    const clean = cleanWarmup(warmup);
    if (!clean) return null;
    const compact = { v: VERSION, p: clean.steps, s: SOUNDS.indexOf(clean.sound), t: clean.tempo, a: clean.start, c: clean.climb, ...(clean.name ? { n: clean.name } : {}) };
    const bytes = new TextEncoder().encode(JSON.stringify(compact));
    let binary = '';
    for (const b of bytes) binary += String.fromCharCode(b);
    return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

/** The warm-up in a link's code, made safe, or null when the code is broken or incomplete. */
export function decodeWarmup(code) {
    if (typeof code !== 'string' || !/^[A-Za-z0-9_-]{4,1000}$/.test(code)) return null;
    try {
        const binary = atob(code.replace(/-/g, '+').replace(/_/g, '/'));
        const compact = JSON.parse(new TextDecoder().decode(Uint8Array.from(binary, (c) => c.charCodeAt(0))));
        if (compact?.v !== VERSION) return null;
        return cleanWarmup({ steps: compact.p, sound: SOUNDS[compact.s], tempo: compact.t, start: compact.a, climb: compact.c, name: compact.n });
    } catch (error) {
        console.info('A warm-up link that could not be read', error?.message ?? error);
        return null;
    }
}

/**
 * The warm-up as the workout's exercise (`{ tempo, turn, notes: [{ beat, length, step, lyric }] }`,
 * steps from the singer's comfortable note): a beat a note, the last held, sung on its sound,
 * listen then your turn.
 */
export function warmupExercise(warmup) {
    const last = warmup.steps.length - 1;
    return { tempo: warmup.tempo, turn: true, notes: warmup.steps.map((step, i) => ({ beat: i, length: i === last ? HELD : 1, step: step + warmup.start, lyric: warmup.sound === 'trill' ? 'brr' : warmup.sound })) };
}

/**
 * Its rounds, as half steps from where it starts: up a half step a round to `climb`, then back down
 * ([0, 1, 2, 1, 0] for a climb of 2), within the singer's range: a round whose notes would leave
 * `range` (MIDI { low, high }, around `home`) is left out.
 */
export function warmupRounds(warmup, { home = null, range = null } = {}) {
    const up = Array.from({ length: warmup.climb + 1 }, (_, i) => i);
    const all = [...up, ...up.slice(0, -1).reverse()];
    if (!range || home === null) return all;
    const low = Math.min(...warmup.steps) + warmup.start;
    const high = Math.max(...warmup.steps) + warmup.start;
    const fits = all.filter((shift) => home + low + shift >= range.low && home + high + shift <= range.high);
    return fits.length ? fits : [0];
}

/** How long it takes, in seconds: each round heard, then sung (as the workout times it). */
export function warmupSeconds(warmup, rounds) {
    const beats = warmup.steps.length - 1 + HELD;
    return ((beats * 2 + 1.5 + 2) * 60 * rounds) / warmup.tempo;
}

/** The warm-up code in a link (`/warmup#<code>`), or null. */
export function codeFrom(hash) {
    const code = (hash ?? '').replace(/^#/, '').trim();
    return code || null;
}

/**
 * A kept warm-up as the workout's warm-up step (`{ kind, exercise, rounds, roundShifts, name }`):
 * its rounds as half steps (`roundShifts`, up then back down, within `range` around `home`).
 */
export function warmupStep(warmup, { home = null, range = null } = {}) {
    const roundShifts = warmupRounds(warmup, { home, range });
    return { kind: 'warmUp', exercise: warmupExercise(warmup), rounds: roundShifts.length, roundShifts, name: warmup.name };
}

/**
 * Kept warm-ups from two places (this device and the account, or two devices) as one list: by code,
 * the newer of keeping and removing wins (a removal is kept as `removedAt`, so another device's copy
 * can't bring it back), newest first, at most 40 entries with the removed ones. Pure.
 */
export function mergeWarmups(mine = [], theirs = []) {
    const stamp = (w) => Math.max(w.keptAt ?? 0, w.removedAt ?? 0);
    const byCode = new Map();
    for (const w of [...(theirs ?? []), ...(mine ?? [])]) {
        if (typeof w?.code !== 'string') continue;
        const had = byCode.get(w.code);
        if (!had || stamp(w) >= stamp(had)) byCode.set(w.code, { code: w.code, name: String(w.name ?? '').slice(0, LIMITS.name), keptAt: w.keptAt ?? 0, ...(w.removedAt ? { removedAt: w.removedAt } : {}) });
    }
    return [...byCode.values()].sort((a, b) => stamp(b) - stamp(a)).slice(0, 40);
}

/** The notes the builder offers (half steps from do): so below to the do above, the major scale. */
export const SCALE_STEPS = [-5, -3, -1, 0, 2, 4, 5, 7, 9, 11, 12];

/**
 * A course step's exercise (`{ tempo, notes: [{ beat, step, lyric }] }`, steps from the comfortable
 * note) as a warm-up to start the builder from (docs/warmup-builder-plan.md, slice 5): its notes in
 * order, moved so the pattern starts where the builder can show it, the sound from its lyric. Null
 * when it isn't one the builder can make: fewer than 3 notes, more than 16, a note off the scale's
 * chips, or wider than the limits. Pure.
 */
export function warmupFromExercise(exercise) {
    const notes = [...(exercise?.notes ?? [])].sort((a, b) => a.beat - b.beat);
    if (notes.length < 3 || notes.length > LIMITS.notes) return null;
    const first = notes[0].step;
    const steps = notes.map((n) => n.step - first);
    if (steps.some((s) => !SCALE_STEPS.includes(s))) return null;
    const lyric = String(notes[0].lyric ?? '').toLowerCase();
    const sound = lyric === 'brr' ? 'trill' : SOUNDS.includes(lyric) ? lyric : 'oo';
    return cleanWarmup({ steps, sound, tempo: exercise.tempo, start: first, climb: 4, name: '' });
}
