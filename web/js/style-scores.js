// The styles courses' measures (docs/courses/folk.md, yodel.md and pop-country.md, "What the app
// measures here"). The moves: a scoop, a fall-off, a slide and a cry, each judged by its shape.
// The flip: how quickly a voice crosses a leap between two joined notes, a clean yodel flip or a
// slide through the notes between. Key drift: how
// far a line sung with nothing underneath has wandered from the key the singer set at its start, by
// its end; and its intervals judged against that key as it moves, so a first note a little low isn't
// wrong all the way through. Read from scoreTake's per-note results (each heard note's centre in
// cents from its target, octave-folded). Pure, unit-tested in tests/style-scores.test.js. Every
// threshold is a named constant, tuned on the beta before stars depend on it.

import { STRICTNESS } from './scoring.js';
import { hzToCents } from './pitchtrack.js';

export const MIN_HEARD = 6; // fewer heard notes, and there's no line to judge
export const SHORT_SECONDS = 0.25; // shorter notes (ornaments, passing notes) don't set the key
export const RUNNING_NOTES = 5; // an interval is judged against the median of the five heard notes around it
export const DRIFT_FULL = 25; // a quarter of a semitone or less: in tune for unaccompanied singing
export const DRIFT_NONE = 100; // a semitone: no credit for the key
export const INTERVALS_SHARE = 0.6; // of the stars; the rest is the key held

const median = (xs) => {
    const s = [...xs].sort((a, b) => a - b);
    const mid = s.length >> 1;
    return s.length % 2 ? s[mid] : (s[mid - 1] + s[mid]) / 2;
};

/**
 * The key's line through the notes: a Theil-Sen fit (the median of every pair's slope), so one
 * wrong note or a scale degree a singer always sings a little high doesn't tilt it.
 */
function keyLine(points) {
    const slopes = [];
    for (let i = 0; i < points.length; i++) {
        for (let j = i + 1; j < points.length; j++) slopes.push((points[j].cents - points[i].cents) / (points[j].at - points[i].at));
    }
    const slope = median(slopes);
    const intercept = median(points.map((p) => p.cents - slope * p.at));
    return (at) => intercept + slope * at;
}

/**
 * Key drift and intervals alone, from scoreTake's `notes` (`{ index, level, cents }`) and the
 * exercise's notes (`{ beat, length }`, for when each was sung and which are too short to set the
 * key) at `beatSeconds`. Returns `{ status: 'scored', key, ended, drift, driftPercent,
 * intervalsPercent, percent, notes }`, where `key` and `ended` are cents from the written key at the
 * line's start and end (minus is low), `drift` is `ended - key`, and `notes` gives each note's
 * interval `offset` from the running key and its `level` ('lit', 'close', 'notYet', or 'unheard').
 * With too little heard, `{ status: 'unheard', percent: null }`. A note not heard before the last
 * heard one counts as not yet (a take that stopped early isn't punished for notes it never reached).
 */
export function keyDrift(results, notes, { beatSeconds, strictness = 'standard' } = {}) {
    const tolerance = STRICTNESS[strictness] ?? STRICTNESS.standard;
    const heard = results.filter((r) => r.level !== 'unheard' && r.cents !== null);
    const long = heard.filter((r) => notes[r.index].length * beatSeconds >= SHORT_SECONDS);
    if (heard.length < MIN_HEARD || long.length < MIN_HEARD) return { status: 'unheard', percent: null };
    const at = (r) => notes[r.index].beat * beatSeconds;
    const line = keyLine(long.map((r) => ({ at: at(r), cents: r.cents })));
    const key = line(at(long[0]));
    const ended = line(at(long[long.length - 1]));
    const drift = ended - key;
    const half = RUNNING_NOTES >> 1;
    const judged = new Map(
        heard.map((r, i) => {
            const around = heard.slice(Math.max(0, i - half), i + half + 1).map((h) => h.cents);
            const offset = r.cents - median(around);
            const off = Math.abs(offset);
            return [r.index, { offset, level: off <= tolerance ? 'lit' : off <= tolerance * 2 ? 'close' : 'notYet' }];
        }),
    );
    const lastHeard = heard[heard.length - 1].index;
    const out = results.map((r) => ({ index: r.index, ...(judged.get(r.index) ?? { offset: null, level: 'unheard' }) }));
    const counted = out.filter((n) => n.level !== 'unheard' || n.index < lastHeard);
    const points = counted.reduce((sum, n) => sum + (n.level === 'lit' ? 1 : n.level === 'close' ? 0.5 : 0), 0);
    const intervalsPercent = Math.round((points / counted.length) * 100);
    const away = Math.abs(drift);
    const driftPercent = Math.round(away <= DRIFT_FULL ? 100 : away >= DRIFT_NONE ? 0 : ((DRIFT_NONE - away) / (DRIFT_NONE - DRIFT_FULL)) * 100);
    const percent = Math.round(intervalsPercent * INTERVALS_SHARE + driftPercent * (1 - INTERVALS_SHARE));
    return { status: 'scored', key: Math.round(key), ended: Math.round(ended), drift: Math.round(drift), driftPercent, intervalsPercent, percent, notes: out };
}

export const FLIP_LEAP = 4; // semitones: a smaller step isn't a flip
export const FLIP_NEAR = 100; // cents: arriving within a semitone counts (the head note often scoops in flat); pitch is judged on the note's centre
export const FLIP_CLEAN = 0.1; // seconds: a flip this quick or quicker is clean
export const FLIP_SLID = 0.2; // slower than this is a slide
export const FLIP_GLIDE_SHARE = 1 / 3; // a clean flip has fewer readings than this passing between the notes
const FLIP_SEARCH = 0.35; // seconds either side of the joint to look for the crossing
const JOINED = 0.02; // beats: a note starting this close to the last one's end is joined to it

/**
 * Cents from `midi`, folded into the octave nearest it when `fold` (the tracker can slip an octave
 * at a flip); never on a leap of about an octave, where folding would put both notes in one place.
 */
const near = (hz, midi, fold) => {
    const c = hzToCents(hz, midi);
    return fold ? c - 1200 * Math.round(c / 1200) : c;
};
const FOLD_UNDER = 10; // semitones: leaps smaller than this are folded

/**
 * Each flip in a take: every leap of FLIP_LEAP or more between joined notes (`{ beat, length,
 * pitch }`, at `beatSeconds`), from the pitch track (`{ rate, hz }`). Returns one entry per leap:
 * `{ index, direction, seconds, kind }`: the index of the note leapt to, 'up' or 'down' (reported
 * apart: the way down is the one singers slam), the seconds from the last reading on the first note
 * to the first on the second (a dropout or an octave slip between counts as part of the flip), and
 * 'clean', 'nearly', 'slid' or 'unheard'. The tracker's median smoothing keeps a jump a jump, so
 * the readings between are read as they come.
 */
export function flips(track, notes, { beatSeconds }) {
    const out = [];
    for (let i = 1; i < notes.length; i++) {
        const a = notes[i - 1];
        const b = notes[i];
        if (Math.abs(b.pitch - a.pitch) < FLIP_LEAP || Math.abs(b.beat - (a.beat + a.length)) > JOINED) continue;
        const direction = b.pitch > a.pitch ? 'up' : 'down';
        const c = crossing(track, a, b, beatSeconds);
        if (!c) {
            out.push({ index: i, direction, seconds: null, kind: 'unheard' });
            continue;
        }
        const kind = c.seconds <= FLIP_CLEAN && c.gliding < FLIP_GLIDE_SHARE ? 'clean' : c.seconds > FLIP_SLID || c.gliding > 0.5 ? 'slid' : 'nearly';
        out.push({ index: i, direction, seconds: c.seconds, kind });
    }
    return out;
}

/**
 * How the voice crossed from note `a` to the joined note `b`: the seconds from the last reading on
 * `a` (within a semitone, octave-folded unless the leap is about an octave) to the first two on `b`,
 * and the share of the readings between that glided through the notes between (a dropout or an
 * octave slip of either note isn't a glide). Null when either side wasn't heard near the joint.
 */
function crossing(track, a, b, beatSeconds) {
    const joint = b.beat * beatSeconds;
    const from = Math.max(0, Math.round((joint - Math.min(FLIP_SEARCH, (a.length * beatSeconds) / 2)) * track.rate));
    const to = Math.min(track.hz.length - 1, Math.round((joint + Math.min(FLIP_SEARCH, (b.length * beatSeconds) / 2)) * track.rate));
    const fold = Math.abs(b.pitch - a.pitch) < FOLD_UNDER;
    const onA = (k) => track.hz[k] > 0 && Math.abs(near(track.hz[k], a.pitch, fold)) <= FLIP_NEAR;
    const onB = (k) => track.hz[k] > 0 && Math.abs(near(track.hz[k], b.pitch, fold)) <= FLIP_NEAR;
    let firstB = -1;
    for (let k = from; k <= to; k++) if (onB(k) && (k + 1 > to || onB(k + 1))) { firstB = k; break; }
    let lastA = -1;
    for (let k = firstB - 1; k >= from && firstB >= 0; k--) if (onA(k)) { lastA = k; break; }
    if (firstB < 0 || lastA < 0) return null;
    const low = Math.min(a.pitch, b.pitch);
    const high = Math.max(a.pitch, b.pitch);
    let gliding = 0;
    for (let k = lastA + 1; k < firstB; k++) {
        if (!(track.hz[k] > 0)) continue;
        if (Math.abs(near(track.hz[k], a.pitch, true)) <= FLIP_NEAR || Math.abs(near(track.hz[k], b.pitch, true)) <= FLIP_NEAR) continue;
        const midi = 69 + 12 * Math.log2(track.hz[k] / 440);
        if (midi > low && midi < high) gliding++;
    }
    // Flip time counts the voiced readings between the notes only: an unvoiced "h" (in "hee") or a
    // dropout isn't the voice gliding.
    let voiced = 0;
    for (let k = lastA + 1; k < firstB; k++) if (track.hz[k] > 0) voiced++;
    return { seconds: (voiced + 1) / track.rate, gliding: gliding / Math.max(1, voiced) };
}

/**
 * The flips' share of the stars: clean 1, nearly a half, slid or unheard none; null with no leaps.
 * `generous` (Yodeling's first lesson): nearly counts as clean, and a slide as half.
 */
export function flipPercent(found, { generous = false } = {}) {
    if (!found.length) return null;
    const credit = generous ? { clean: 1, nearly: 1, slid: 0.5 } : { clean: 1, nearly: 0.5, slid: 0 };
    const points = found.reduce((sum, f) => sum + (credit[f.kind] ?? 0), 0);
    return Math.round((points / found.length) * 100);
}

export const JUMP_SEMITONES = 2.5; // a siren glides well under this in 30 ms; a flip jumps it (a smaller glitch isn't one)
const JUMP_GAP = 6; // readings: a dropout up to 60 ms across a jump still joins it
const JUMP_SPAN = 3; // readings: a jump is measured against the reading this far before
const JUMP_ONCE = 10; // readings: one jump, counted once
const RUN_EDGE = 15; // readings: a run's first and last 150 ms (its onset and release) are left out
const OCTAVE_SLIP = 0.6; // semitones from an octave: a tracker's octave slip, not a flip
export const FLIPS_SPREAD = 4; // semitones: flips further apart than this are "in a few places"

/**
 * Where a voice flips, from a siren (`track`: `{ rate, hz }`) between `from` and `to` seconds: every
 * jump of JUMP_SEMITONES or more between readings (across a dropout up to 60 ms; octave slips left
 * out), up or down. The flip is the median of the downward jumps' midpoints (the break comes higher
 * going up than coming down), or of the upward ones when none came down, in semitones above `home`;
 * when they spread more than FLIPS_SPREAD, the lowest. Returns `{ flip, jumps, spread }`, flip null
 * when the voice glided all the way (that's fine: many voices blend).
 */
export function flipPoint(track, { home, from = 0, to = Infinity }) {
    const first = Math.max(0, Math.ceil(from * track.rate));
    const last = Math.min(track.hz.length - 1, Math.floor(to * track.rate));
    const midi = [];
    for (let i = first; i <= last; i++) midi.push(track.hz[i] > 0 ? 69 + 12 * Math.log2(track.hz[i] / 440) : null);
    // Leave out each voiced run's onset and release; a dropout up to JUMP_GAP doesn't end a run.
    const usable = midi.slice();
    let start = null;
    let lastVoiced = null;
    const closeRun = () => {
        if (start === null) return;
        for (let k = start; k <= lastVoiced; k++) if (k < start + RUN_EDGE || k > lastVoiced - RUN_EDGE) usable[k] = null;
    };
    midi.forEach((m, i) => {
        if (m === null) return;
        if (lastVoiced === null || i - lastVoiced > JUMP_GAP + 1) {
            closeRun();
            start = i;
        }
        lastVoiced = i;
    });
    closeRun();
    // A jump: a change of JUMP_SEMITONES or more against the reading about 30 ms before (a flip can
    // smear over a reading or two; a siren moves a fraction of a semitone in that time), across a
    // dropout up to 60 ms, each counted once.
    const voiced = usable.map((m, i) => (m === null ? -1 : i)).filter((i) => i >= 0);
    const jumps = [];
    let lastJump = -Infinity;
    for (let n = 1; n < voiced.length; n++) {
        const k = voiced[n];
        let m = n - 1;
        while (m > 0 && k - voiced[m] < JUMP_SPAN) m--;
        const before = voiced[m];
        if (k - before > JUMP_GAP + JUMP_SPAN || k - lastJump < JUMP_ONCE) continue;
        const delta = usable[k] - usable[before];
        if (Math.abs(delta) < JUMP_SEMITONES || Math.abs(Math.abs(delta) - 12) < OCTAVE_SLIP) continue;
        jumps.push({ at: (first + (before + k) / 2) / track.rate, direction: delta > 0 ? 'up' : 'down', middle: (usable[k] + usable[before]) / 2 - home });
        lastJump = k;
    }
    // How high the voice reached: the 95th percentile of its readings, in semitones above home.
    const sung = usable.filter((m) => m !== null).sort((x, y) => x - y);
    const reach = sung.length ? sung[Math.floor(sung.length * 0.95)] - home : null;
    const down = jumps.filter((j) => j.direction === 'down').map((j) => j.middle);
    const chosen = down.length ? down : jumps.map((j) => j.middle);
    if (!chosen.length) return { flip: null, jumps, spread: 0, reach };
    const spread = Math.max(...chosen) - Math.min(...chosen);
    const sorted = [...chosen].sort((a, b) => a - b);
    const flip = spread > FLIPS_SPREAD ? sorted[0] : sorted.length % 2 ? sorted[sorted.length >> 1] : (sorted[(sorted.length >> 1) - 1] + sorted[sorted.length >> 1]) / 2;
    return { flip: Math.round(flip), jumps, spread: Math.round(spread * 10) / 10, reach };
}

export const FLIP_RANGE = [3, 15]; // semitones above the comfortable note where a flip is believed
export const FLIP_HEADROOM = 3; // and the voice reached at least this far above it, so the hook's top was sung
export const FLIP_AGREE = 2; // two flips within this many semitones confirm each other

/**
 * The flip kept with the course, from every siren so far: `seen` (the downward flips already kept)
 * plus this siren's downward jumps (`found` from flipPoint), each believed only inside FLIP_RANGE
 * and with FLIP_HEADROOM of reached voice above it. The flip is set once two agree within
 * FLIP_AGREE (the median of the biggest such group); until then it's null. Returns `{ flip, seen }`.
 */
export function settleFlip(seen, found) {
    const fresh = found.jumps.filter((j) => j.direction === 'down').map((j) => Math.round(j.middle * 10) / 10)
        .filter((m) => m >= FLIP_RANGE[0] && m <= FLIP_RANGE[1] && (found.reach === null || found.reach >= m + FLIP_HEADROOM));
    const all = [...(seen ?? []), ...fresh].slice(-6);
    let best = [];
    for (const m of all) {
        const group = all.filter((x) => Math.abs(x - m) <= FLIP_AGREE);
        if (group.length > best.length) best = group;
    }
    if (best.length < 2) return { flip: null, seen: all };
    const sorted = [...best].sort((x, y) => x - y);
    return { flip: Math.round(sorted[sorted.length >> 1]), seen: all };
}

export const MOVE_MIN_SECONDS = 0.5; // moves are judged only on notes this long: each keeps a centre for its pitch
export const SCOOP_FROM = [100, 450]; // cents below: a smaller dip is an accidental flat start, not a style (the guide starts at most 400 below)
export const SCOOP_WITHIN = 0.4; // seconds to arrive (a lazy scoop's lesson gives `window: 0.5`)
const SCOOP_FLAT = 8; // readings (80 ms): a start held this long before climbing is a flat entry, not a scoop
const SCOOP_RISING = 3; // readings on the way up: a scoop glides, a step from the note before jumps
const SCOOP_BELOW_PREV = 50; // cents: after a joined note, a scoop starts at least this far below it
const ON_NOTE = 50; // cents: arrived, or back on the note
export const FALL_BY = 200; // cents: a fall-off drops at least this far
const FALL_FROM = 0.4; // seconds before the note's end: where a fall-off may begin
const FALL_AFTER = 0.2; // and after its end: a soft tail fades out of hearing
const FALL_RELEASE = 10; // readings (100 ms): a fall-off descends at least this long; a release's droop is quicker
export const SLIDE_SECONDS = [0.12, 0.7]; // a deliberate glide between joined notes, timed between the two notes' ±50 cents (legato takes well under this)
export const CRY_CENTS = [100, 400]; // the small version: real cries often flip a third to a fifth
export const CRY_SECONDS = [0.06, 0.25];
const CRY_ON_FIRST = 5; // readings on the note just before the flick
export const YOUR_WAY_SECONDS = 1; // a move you chose yourself counts only on a note this long
const SLIDE_STEP = 2; // semitones: a slide spans at least this
const TASTE_SHARE = 0.5; // moves on more than this share of the notes: "less is more"

/** The readings of `track` from `from` to `to` seconds, as cents from `midi` folded into its nearest octave (null when unvoiced). */
function centsAround(track, midi, from, to) {
    const out = [];
    for (let k = Math.max(0, Math.round(from * track.rate)); k <= Math.min(track.hz.length - 1, Math.round(to * track.rate)); k++) {
        out.push(track.hz[k] > 0 ? near(track.hz[k], midi, true) : null);
    }
    return out;
}

/**
 * A styled scoop into `note` (`{ beat, length, pitch }`): from 100 to 400 cents below, rising
 * smoothly, arriving within `window` seconds. After a joined note (`prev`, no gap between), it must
 * start at least 50 cents below that note too, so plain legato from the note before never counts.
 */
export function scoopInto(track, note, { beatSeconds, window = SCOOP_WITHIN, prev = null }) {
    const start = note.beat * beatSeconds;
    const joined = !!prev && Math.abs(prev.beat + prev.length - note.beat) <= JOINED;
    const cents = centsAround(track, note.pitch, joined ? start : start - 0.05, start + window + 0.1);
    const first = cents.findIndex((c, i) => c !== null && cents[i + 1] != null && cents[i + 2] != null);
    if (first < 0) return false;
    const from = [cents[first], cents[first + 1], cents[first + 2]].sort((x, y) => x - y)[1];
    if (-from < SCOOP_FROM[0] || -from > SCOOP_FROM[1]) return false;
    if (joined && from > Math.min(0, (prev.pitch - note.pitch) * 100) - SCOOP_BELOW_PREV) return false;
    const climb = cents.findIndex((c, i) => i > first && c !== null && c > from + 30);
    if (climb < 0 || climb - first > SCOOP_FLAT * Math.max(1, window / SCOOP_WITHIN)) return false;
    const arrive = cents.findIndex((c, i) => i > first && c !== null && Math.abs(c) <= ON_NOTE && [1, 2].every((d) => cents[i + d] != null && Math.abs(cents[i + d]) <= ON_NOTE));
    if (arrive <= 0 || (arrive - first) / track.rate > window) return false;
    // A scoop glides up through the pitches between; a step up from the note before just jumps.
    const rising = cents.slice(climb, arrive).filter((c) => c !== null && c > from + 30 && c < -ON_NOTE).length;
    return rising >= SCOOP_RISING;
}

/**
 * A fall-off at the end of `note`: at least 200 cents down from its steady pitch, in its last 400 ms,
 * counted until the readings stop, and past the note's end only up to `next` (the next note's start
 * in seconds), so the next note is never read as the fall.
 */
export function fallOff(track, note, { beatSeconds, next = Infinity }) {
    const end = (note.beat + note.length) * beatSeconds;
    const steady = centsAround(track, note.pitch, end - FALL_FROM - 0.25, end - FALL_FROM).filter((c) => c !== null);
    if (steady.length < 5) return false;
    const level = steady.sort((x, y) => x - y)[steady.length >> 1];
    const tail = centsAround(track, note.pitch, end - FALL_FROM, Math.min(end + FALL_AFTER, next - 0.02));
    // The readings until the tail fades out. A fall-off takes its time: from leaving the note (50 cents
    // down) to its lowest it lasts at least 100 ms, so a plain note's quick droop as the voice stops
    // isn't one.
    const heard = [];
    let gap = 0;
    for (const c of tail) {
        if (c === null) {
            if (++gap > 3 && heard.length) break; // the readings stopped: the tail faded out
            continue;
        }
        gap = 0;
        heard.push(c);
    }
    if (!heard.length) return false;
    const lowest = Math.min(...heard);
    const deepest = heard.indexOf(lowest);
    const left = heard.findIndex((c) => c < level - 50);
    return level - lowest >= FALL_BY && left >= 0 && deepest - left + 1 >= FALL_RELEASE;
}

/**
 * A slide from `a` into the joined note `b`: from leaving `a` to reaching `b` (each within 50
 * cents) in 150 to 700 ms, most readings on the way between the two.
 */
export function slideInto(track, a, b, { beatSeconds }) {
    if (Math.abs(b.pitch - a.pitch) < SLIDE_STEP || Math.abs(b.beat - (a.beat + a.length)) > JOINED) return false;
    const joint = b.beat * beatSeconds;
    const from = Math.max(0, Math.round((joint - a.length * beatSeconds) * track.rate)); // the whole first note: an eased slide leaves it early
    const to = Math.min(track.hz.length - 1, Math.round((joint + Math.min(SLIDE_SECONDS[1], (b.length * beatSeconds) / 2)) * track.rate));
    const off = (k, pitch) => (track.hz[k] > 0 ? Math.abs(hzToCents(track.hz[k], pitch)) : Infinity);
    let reached = -1;
    for (let k = from; k <= to; k++) if (off(k, b.pitch) <= ON_NOTE && off(k + 1, b.pitch) <= ON_NOTE) { reached = k; break; }
    let left = -1;
    for (let k = reached - 1; k >= from && reached >= 0; k--) if (off(k, a.pitch) <= ON_NOTE) { left = k; break; }
    if (reached < 0 || left < 0) return false;
    const low = Math.min(a.pitch, b.pitch);
    const high = Math.max(a.pitch, b.pitch);
    let between = 0;
    let voiced = 0;
    for (let k = left + 1; k < reached; k++) {
        if (!(track.hz[k] > 0)) continue;
        voiced++;
        const midi = 69 + 12 * Math.log2(track.hz[k] / 440);
        if (midi > low && midi < high) between++;
    }
    const seconds = (reached - left) / track.rate;
    return seconds >= SLIDE_SECONDS[0] && seconds <= SLIDE_SECONDS[1] && voiced > 0 && between / voiced > 0.5;
}

/** A cry in `note`: a flick 100 to 400 cents above it for 60 to 250 ms, then back on it. */
export function cryIn(track, note, { beatSeconds }) {
    const start = note.beat * beatSeconds;
    const cents = centsAround(track, note.pitch, start, (note.beat + note.length) * beatSeconds - 0.05);
    for (let i = 0; i < cents.length; i++) {
        if (cents[i] === null || cents[i] < CRY_CENTS[0] || cents[i] > CRY_CENTS[1]) continue;
        let j = i;
        while (j + 1 < cents.length && cents[j + 1] !== null && cents[j + 1] >= CRY_CENTS[0] * 0.6 && cents[j + 1] <= CRY_CENTS[1]) j++;
        const seconds = (j - i + 1) / track.rate;
        const back = cents.slice(j + 1, j + 15).some((c) => c !== null && Math.abs(c) <= ON_NOTE);
        // On the note first: a late arrival from above isn't a cry.
        const before = cents.slice(Math.max(0, i - 15), i).filter((c) => c !== null && Math.abs(c) <= ON_NOTE).length;
        if (seconds >= CRY_SECONDS[0] && seconds <= CRY_SECONDS[1] && back && before >= CRY_ON_FIRST) return true;
        i = j;
    }
    return false;
}

/**
 * Every move heard on a line (`notes`: `{ beat, length, pitch }` at `beatSeconds`), on notes of half
 * a second or more: `[{ index, move }]`, the move 'scoop', 'fall', 'cry', or 'slide' (from that note
 * into the next).
 */
export function movesHeard(track, notes, { beatSeconds, window, minSeconds = MOVE_MIN_SECONDS } = {}) {
    const out = [];
    notes.forEach((note, i) => {
        if (note.length * beatSeconds < minSeconds) return;
        if (scoopInto(track, note, { beatSeconds, window, prev: notes[i - 1] ?? null })) out.push({ index: i, move: 'scoop' });
        if (cryIn(track, note, { beatSeconds })) out.push({ index: i, move: 'cry' });
        const next = notes[i + 1];
        if (fallOff(track, note, { beatSeconds, next: next ? next.beat * beatSeconds : Infinity })) out.push({ index: i, move: 'fall' });
        if (next && slideInto(track, note, next, { beatSeconds })) out.push({ index: i, move: 'slide' });
    });
    return out;
}

/**
 * The moves a line asked for (`styles[i]`: 'scoop', 'fall', 'slide', 'cry' or none, per note) against
 * what was heard: `{ asked, matched, heard, tooMany }`. With none asked, the learner chose: `matched`
 * is 1 when any move was heard. `tooMany` when moves were heard on more than half the notes.
 */
export function styledMoves(track, notes, styles, { beatSeconds, window } = {}) {
    const asked = styles.map((style, i) => (style ? { index: i, style } : null)).filter(Boolean);
    // Your own way: only moves on notes of a second or more count, so legato's small glides don't.
    const heard = movesHeard(track, notes, { beatSeconds, window, minSeconds: asked.length ? MOVE_MIN_SECONDS : YOUR_WAY_SECONDS });
    const has = (i, move) => heard.some((h) => h.index === i && h.move === move);
    const matched = asked.length ? asked.filter((a) => has(a.index, a.style)).length : heard.length ? 1 : 0;
    const styledNotes = new Set(heard.map((h) => h.index)).size;
    return { asked: asked.length, matched, heard, tooMany: !asked.length && styledNotes > notes.length * TASTE_SHARE };
}

/**
 * The notes to judge pitch on, with each asked-for scoop's arrival and fall-off's tail left out, so a
 * styled note's centre is the note itself.
 */
export function pitchParts(notes, styles, { beatSeconds, window = SCOOP_WITHIN }) {
    return notes.map((note, i) => {
        if (styles[i] === 'scoop') {
            const cut = Math.min(window / beatSeconds, note.length * 0.4);
            return { ...note, beat: note.beat + cut, length: note.length - cut };
        }
        if (styles[i] === 'fall') return { ...note, length: note.length - Math.min(FALL_FROM / beatSeconds, note.length * 0.5) };
        // A slide out of a note: its pitch is judged before the glide, keeping a quarter of a second.
        if (styles[i] === 'slide') return { ...note, length: Math.max(SLID_KEEP / beatSeconds, note.length - GUIDE_SLIDE / beatSeconds) };
        return note;
    });
}

// How the guide sings each move (synth.js's per-note `bend`: [seconds from the note's start, cents]),
// each shape inside the window its measure accepts, so the model and the stars come from one place;
// tests/style-scores.test.js checks the measure hears the guide's own move.
const GUIDE_SCOOP = { cents: -200, seconds: 0.15, lazy: 0.35, widest: 400 }; // SCOOP_FROM and SCOOP_WITHIN, never at their edge
const GUIDE_FALL = { cents: -350, seconds: 0.3 }; // FALL_BY, within FALL_FROM of the end
const SLID_KEEP = 0.25; // seconds of a slid note kept for its pitch
const GUIDE_SLIDE = 0.5; // seconds: eased, it leaves and reaches its notes (within 50 cents) inside SLIDE_SECONDS, even over a whole step
const GUIDE_CRY = { at: 0.4, cents: 250, rise: 0.03, hold: 0.09 }; // CRY_CENTS and CRY_SECONDS

/**
 * The guide's bend for a note with a `style` (`{ length, pitch or step, style }`, `next` the note
 * after it, `exercise` for its tempo and a lazy scoop's `window`), or null for a plain note.
 */
export function styleBend(note, next, exercise = {}, prev = null) {
    const beat = 60 / (exercise.tempo ?? 72);
    const seconds = note.length * beat;
    switch (note.style) {
        case 'scoop': {
            const rise = (exercise.window ?? SCOOP_WITHIN) > SCOOP_WITHIN ? GUIDE_SCOOP.lazy : GUIDE_SCOOP.seconds;
            // After a joined note, from below that note (as the measure asks), never past the scoop's widest.
            const joined = prev && Math.abs(prev.beat + prev.length - note.beat) <= JOINED;
            const below = joined ? Math.min(0, ((prev.pitch ?? prev.step) - (note.pitch ?? note.step)) * 100) - 100 : 0;
            return [[0, Math.max(-GUIDE_SCOOP.widest, Math.min(GUIDE_SCOOP.cents, below))], [rise, 0]];
        }
        case 'fall':
            return [[Math.max(0, seconds - GUIDE_FALL.seconds), 0], [seconds, GUIDE_FALL.cents]];
        case 'slide': {
            if (!next) return null;
            const to = ((next.pitch ?? next.step) - (note.pitch ?? note.step)) * 100;
            return [[Math.max(0, seconds - GUIDE_SLIDE), 0], [seconds, to]];
        }
        case 'cry': {
            const at = seconds * GUIDE_CRY.at;
            return [[at, 0], [at + GUIDE_CRY.rise, GUIDE_CRY.cents], [at + GUIDE_CRY.rise + GUIDE_CRY.hold, GUIDE_CRY.cents], [at + 2 * GUIDE_CRY.rise + GUIDE_CRY.hold, 0]];
        }
        default:
            return null;
    }
}
