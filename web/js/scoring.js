// How a take went, from its pitch track (pitchtrack.js) and the part's notes. Shared by the beta's
// quality check, Sing, the course, the creator and coaching (docs/vocal-coaching-plan.md). Pure
// functions, unit-tested on synthesized singing in tests/scoring.test.js.
//
// The rules the scores keep (the vision): effort over talent, no red marks, no 0%. A note is lit
// (on pitch), close, or not yet; never "wrong". Silence or noise is "couldn't hear", which earns and
// costs nothing. Everything here returns codes; the screens word them.

import { hzToCents } from './pitchtrack.js';

/** How close counts as on pitch, in cents, by strictness. */
// Bump with any change to how a take is scored: scores carry it, and a new number starts new leaderboards.
export const SCORING_VERSION = 1;
export const STRICTNESS = { relaxed: 50, standard: 35, precise: 20 };

const SCOOP_CENTS = 100; // starting more than a semitone away...
const SCOOP_SECONDS = 0.15; // ...then arriving within 150 ms
const STEADY_CENTS = 15; // drift (vibrato aside) under this is steady
const VIBRATO_MIN_SECONDS = 0.6;
const ENTRY_AFTER_REST_SECONDS = 0.1; // a note after a gap this long is an entry
const ENTRY_WINDOW = [-0.25, 0.4]; // where to look for an entry around the note's start
const STEADY_MIN_FRAMES = 22; // shorter notes can't tell vibrato from wavering: not judged
const LIVE_GAP_SECONDS = 0.06; // a dropped reading this short doesn't break a held note
const HEARD_SHARE = 0.4; // a note needs this share of its middle voiced to be scored
const ON_TIME_MS = 80; // entries within this are on time
const STEADY_MS = 90; // for the timing score: entries and note changes within this of the beat (by default)
const CHANGE_WINDOW = [-0.25, 0.3]; // where to look for a change of note around its start
const OFFSET_FORGIVEN_MS = 100; // an even offset up to this isn't held against the timing score
const TIMED_AT_LEAST = 3; // fewer timed moments than this: no timing score
const BREATH_BEATS = 0.5; // the last half beat before a new line (a `br` note) is a breath

/**
 * Scores a take. `track` is from trackTake(); `notes` are the part's ({ beat, length, pitch });
 * the take's first frame is project time `startSeconds` (a take recorded from 0 has 0).
 * `timing` is 'measured' (the timing check was done), 'estimated', or 'none' (an exercise scored
 * on pitch only).
 *
 * Returns `{ status, notes, percent, word, octave, tendency, registers, entryMs, timingPercent,
 * scoops, wavering, phrases, insights, wentWell, tips, strictness, timing }`, with status 'scored'
 * or 'unheard'. `timingPercent` is how steady the timing was: the share of the timed moments (each
 * entry after a rest, each change to a note a semitone or more away) within `steadyMs` of the
 * singer's own usual offset (no more than 100 ms from the beat when the latency was measured);
 * null with fewer than three to go on, or with `timing` 'none'. Each phrase has its `percent`,
 * `timingPercent` and `lean` ('late', 'early', 'uneven' or null).
 */
/**
 * Where a note's singing ends, in beats: its end, less the breath when the next note starts a line
 * (`br`). The breath is the last half beat before that line, less any rest already there, and never
 * more than half the note. The arrangements write phrase ends full length; singers breathe anyway.
 */
export function sungEnd(notes, i) {
    const note = notes[i];
    const end = note.beat + note.length;
    const next = notes[i + 1];
    if (!next?.br) return end;
    const breath = Math.max(0, BREATH_BEATS - Math.max(0, next.beat - end));
    return end - Math.min(breath, note.length / 2);
}

export function scoreTake(track, notes, { beatSeconds, startSeconds = 0, transpose = 0, strictness = 'standard', timing = 'measured', phrases = [], steadyMs = STEADY_MS } = {}) {
    const tolerance = STRICTNESS[strictness] ?? STRICTNESS.standard;
    // Each note ends where its singing does: a breath before a new line isn't held against anyone.
    const timed = notes.map((note, i) => ({
        index: i,
        pitch: note.pitch + transpose,
        start: note.beat * beatSeconds - startSeconds,
        end: sungEnd(notes, i) * beatSeconds - startSeconds,
        previousEnd: i === 0 ? -Infinity : sungEnd(notes, i - 1) * beatSeconds - startSeconds,
        afterRest: i === 0 || note.beat * beatSeconds - sungEnd(notes, i - 1) * beatSeconds >= ENTRY_AFTER_REST_SECONDS,
        previousPitch: i === 0 ? null : notes[i - 1].pitch + transpose,
        previousStart: i === 0 ? -Infinity : notes[i - 1].beat * beatSeconds - startSeconds,
    }));
    const results = timed.map((note) => scoreNote(track, note, { tolerance }));
    const octaves = results.filter((r) => r.level !== 'unheard').map((r) => r.octave);
    const octave = octaves.length ? mostCommon(octaves) : 0;
    const scored = results.filter((r) => r.level !== 'unheard');
    const base = { strictness, timing, octave, notes: results };
    if (scored.length < Math.max(1, notes.length * 0.3)) {
        return { ...base, status: 'unheard', percent: null, word: null, tendency: null, registers: null, entryMs: null, timingPercent: null, scoops: 0, wavering: 0, phrases: [], insights: [], wentWell: [], tips: [] };
    }
    const lit = scored.filter((r) => r.level === 'lit').length;
    const close = scored.filter((r) => r.level === 'close').length;
    // In a heard take, a note not heard counts as not yet (the course review: two of five notes sung
    // well isn't 100%). Notes the take doesn't cover to their end (stopped early) don't count either way.
    const takeEnd = track.hz.length / track.rate;
    const counted = results.filter((r, i) => r.level !== 'unheard' || timed[i].end <= takeEnd);
    // Close counts half: effort shows, and a take that's all close isn't 0%.
    const percent = Math.round(((lit + close / 2) / counted.length) * 100);
    // Tendencies come from the notes nearly there; one note far off doesn't set the advice.
    const near = scored.filter((r) => r.level !== 'notYet');
    const tendency = near.length ? median(near.map((r) => r.cents)) : 0;
    const registers = byRegister(near.length ? near : scored);
    const entries = results.filter((r) => r.entryMs !== null).map((r) => r.entryMs);
    const entryMs = entries.length ? median(entries) : null;
    const scoops = scored.filter((r) => r.scoop).length;
    const wavering = scored.filter((r) => r.steady === false).length;
    // The timing: entries and changes of note against the beat (or, estimated, the singer's usual offset).
    const moments = timing === 'none' ? [] : results.flatMap((r) => [r.entryMs, r.changeMs].filter((ms) => ms !== null).map((ms) => ({ index: r.index, ms })));
    // Steady is against the singer's own usual offset (a take that's evenly a little late lines up
    // with a shift), up to OFFSET_FORGIVEN_MS when the latency was measured; estimated, any offset.
    const usual = moments.length ? median(moments.map((t) => t.ms)) : 0;
    const reference = timing === 'measured' ? Math.max(-OFFSET_FORGIVEN_MS, Math.min(OFFSET_FORGIVEN_MS, usual)) : usual;
    const steadyShare = (list, least = TIMED_AT_LEAST) => (list.length >= least ? Math.round((list.filter((t) => Math.abs(t.ms - reference) <= steadyMs).length / list.length) * 100) : null);
    const timingPercent = steadyShare(moments);
    const phraseResults = phrases.map(([startBeat, endBeat], index) => {
        const within = (i) => notes[i].beat >= startBeat - 1e-9 && notes[i].beat < endBeat - 1e-9;
        const inside = counted.filter((r) => within(r.index));
        const points = inside.reduce((sum, r) => sum + (r.level === 'lit' ? 1 : r.level === 'close' ? 0.5 : 0), 0);
        const mine = moments.filter((t) => within(t.index));
        // A phrase's lean: late or early when most of its timed moments miss the same way.
        const off = mine.filter((t) => Math.abs(t.ms - reference) > steadyMs);
        const lean = off.length >= 2 && off.length >= mine.length / 2 ? (off.filter((t) => t.ms > reference).length >= off.length * 0.75 ? 'late' : off.filter((t) => t.ms < reference).length >= off.length * 0.75 ? 'early' : 'uneven') : null;
        return { index, percent: inside.length ? Math.round((points / inside.length) * 100) : null, timingPercent: steadyShare(mine, 2), lean };
    });
    const result = { ...base, status: 'scored', percent, word: levelWord(percent), tendency, registers, entryMs, timingPercent, scoops, wavering, phrases: phraseResults };
    result.insights = insights(result, scored.length);
    result.tips = result.insights.map((code) => TIPS[code]).filter(Boolean);
    result.wentWell = wentWell(result, scored.length);
    return result;
}

/**
 * One note: its error in the steady middle, steadiness, scoop, entry and vibrato. Each note is
 * scored within the nearest octave (-1, 0 or +1), so someone who drops the high notes an octave,
 * or a tracker slip, isn't marked down; the take's `octave` says which octave was usual.
 */
function scoreNote(track, note, { tolerance }) {
    const duration = note.end - note.start;
    const trim = Math.min(Math.max(0.1, duration * 0.2), duration * 0.3);
    const middle = frames(track, note.start + trim, note.end - Math.min(0.06, duration * 0.15));
    const raw = middle.filter((f) => f.hz).map((f) => hzToCents(f.hz, note.pitch));
    const result = { index: note.index, pitch: note.pitch, level: 'unheard', cents: null, octave: 0, steady: null, scoop: false, entryMs: null, changeMs: null, vibrato: null };
    if (raw.length < 3 || raw.length < middle.length * HEARD_SHARE) {
        return result;
    }
    const octave = Math.max(-1, Math.min(1, Math.round(median(raw) / 1200)));
    const cents = raw.map((c) => foldCents(c, octave));
    result.octave = octave;
    result.cents = median(cents);
    const off = Math.abs(result.cents);
    result.level = off <= tolerance ? 'lit' : off <= tolerance * 2 ? 'close' : 'notYet';
    // Steadiness: the drift of the line once vibrato is smoothed away (about one cycle, 0.18 s).
    // A short note can't tell vibrato from wavering, so it isn't judged.
    if (cents.length >= STEADY_MIN_FRAMES) {
        result.steady = spread(movingAverage(cents, 18)) < STEADY_CENTS;
    }
    // A scoop: at an entry, the first sound far off, then on pitch within 150 ms. Notes joined to
    // the one before (legato) aren't judged: gliding between notes is singing, not a scoop.
    const around = note.afterRest ? frames(track, Math.max(note.start - 0.03, note.previousEnd + 0.05), note.start + SCOOP_SECONDS + 0.15) : [];
    // From the first run of three voiced frames: one stray frame at the onset doesn't count.
    const runStart = around.findIndex((f, i, all) => all.slice(i, i + 3).length === 3 && all.slice(i, i + 3).every((g) => g.hz));
    const opening = runStart < 0 ? [] : around.slice(runStart).filter((f) => f.hz);
    if (opening.length >= 3) {
        // Each frame within its nearest octave: a scoop is a semitone or two, and the tracker can
        // flip octaves on a note's first moment.
        const first = nearestOctave(hzToCents(opening[0].hz, note.pitch));
        const arrived = opening.find((f) => Math.abs(nearestOctave(hzToCents(f.hz, note.pitch))) <= tolerance);
        result.scoop = Math.abs(first) > SCOOP_CENTS && !!arrived && arrived.time - opening[0].time <= SCOOP_SECONDS + 0.011;
    }
    // An entry after a rest: when the voice starts (a voiced run after silence), against the note's
    // start. The search never reaches back into the note before.
    if (note.afterRest) {
        const window = frames(track, Math.max(note.start + ENTRY_WINDOW[0], note.previousEnd + 0.05), note.start + ENTRY_WINDOW[1]);
        const onset = window.find((f, i, all) => f.hz && (i === 0 || !all[i - 1].hz) && all.slice(i, i + 3).every((g) => g.hz));
        if (onset) result.entryMs = Math.round((onset.time + ONSET_BIAS - note.start) * 1000);
    }
    // A change of note (joined to the one before, a semitone or more away): when the voice crosses
    // over to the new note and stays, against the note's start.
    if (!note.afterRest && note.previousPitch !== null && Math.abs(note.pitch - note.previousPitch) >= 1) {
        const window = frames(track, Math.max(note.start + CHANGE_WINDOW[0], (note.previousStart + note.start) / 2), Math.min(note.end, note.start + CHANGE_WINDOW[1]));
        const nearer = (f) => {
            if (!f?.hz) return false;
            const sung = note.pitch + foldCents(hzToCents(f.hz, note.pitch), octave) / 100;
            return Math.abs(sung - note.pitch) < Math.abs(sung - note.previousPitch);
        };
        // Sung on the old note first (else the crossing wasn't heard), then over to the new one for 3 frames.
        const before = window.findIndex((f) => f.hz && !nearer(f));
        const crossing = before < 0 ? -1 : window.findIndex((f, i) => i > before && nearer(f) && nearer(window[i + 1]) && nearer(window[i + 2]));
        if (crossing > 0) result.changeMs = Math.round((window[crossing].time - note.start) * 1000);
    }
    if (duration >= VIBRATO_MIN_SECONDS) {
        result.vibrato = vibrato(cents);
    }
    return result;
}

/**
 * A voice needs a moment to be loud and clear enough to track, so an onset reads late by about
 * this much (measured on synthesized singing with a 30 ms attack, in the tests).
 */
const ONSET_BIAS = -0.008;

function mostCommon(values) {
    const counts = new Map();
    for (const v of values) counts.set(v, (counts.get(v) ?? 0) + 1);
    return [...counts].sort((a, b) => b[1] - a[1] || Math.abs(a[0]) - Math.abs(b[0]))[0][0];
}

function foldCents(cents, octave) {
    return cents - octave * 1200;
}

/** The voiced frames between two times (seconds into the take). */
function frames(track, from, to) {
    const out = [];
    const first = Math.max(0, Math.ceil(from * track.rate));
    const last = Math.min(track.hz.length - 1, Math.floor(to * track.rate));
    for (let i = first; i <= last; i++) {
        out.push({ time: i / track.rate, hz: track.hz[i] });
    }
    return out;
}

/** Vibrato on a held note: its speed (Hz) and width (± cents), or null when there's none to speak of. */
export function vibrato(cents, rate = 100) {
    if (cents.length < 30) return null;
    // Take out the line's slow drift with a straight-line fit; what's left is the wobble.
    const n = cents.length;
    const xMean = (n - 1) / 2;
    const yMean = mean(cents);
    let num = 0;
    let den = 0;
    for (let i = 0; i < n; i++) {
        num += (i - xMean) * (cents[i] - yMean);
        den += (i - xMean) ** 2;
    }
    const slope = num / den;
    const residual = cents.map((c, i) => c - (yMean + slope * (i - xMean)));
    const width = Math.SQRT2 * Math.sqrt(mean(residual.map((r) => r * r)));
    let crossings = 0;
    for (let i = 1; i < n; i++) {
        if ((residual[i - 1] < 0) !== (residual[i] < 0)) crossings++;
    }
    const hz = crossings / 2 / (n / rate);
    return width >= 10 && hz >= 3 && hz <= 9 ? { hz, cents: width } : null;
}

/** The average signed error by register: the low, middle and high thirds of the notes sung. */
function byRegister(scored) {
    const pitches = scored.map((r) => r.pitch);
    const low = Math.min(...pitches);
    const span = Math.max(...pitches) - low;
    const third = (pitch) => (span < 3 ? 'middle' : pitch < low + span / 3 ? 'low' : pitch > low + (2 * span) / 3 ? 'high' : 'middle');
    const out = {};
    for (const name of ['low', 'middle', 'high']) {
        const inside = scored.filter((r) => third(r.pitch) === name);
        out[name] = inside.length ? { cents: mean(inside.map((r) => r.cents)), notes: inside.length } : null;
    }
    return out;
}

/** The level word's key: what the summary says first, before the number. */
function levelWord(percent) {
    if (percent >= 90) return 'excellent';
    if (percent >= 70) return 'close';
    if (percent >= 40) return 'gettingThere';
    return 'tricky';
}

const TIPS = {
    flatHigh: 'breathSupport',
    sharpHigh: 'relaxHigh',
    flatOverall: 'listenFirst',
    sharpOverall: 'listenFirst',
    scoops: 'onsets',
    late: 'breatheEarly',
    early: 'waitForBeat',
    wavering: 'breathSupport',
};

/** Up to 3 things to try, most useful first, as codes the screens word. */
function insights(result, count) {
    const found = [];
    const high = result.registers.high;
    if (high && high.notes >= 3 && high.cents <= -15) found.push('flatHigh');
    else if (high && high.notes >= 3 && high.cents >= 15) found.push('sharpHigh');
    else if (result.tendency <= -15) found.push('flatOverall');
    else if (result.tendency >= 15) found.push('sharpOverall');
    if (result.scoops >= Math.max(2, count * 0.2)) found.push('scoops');
    if (result.timing === 'measured' && result.entryMs !== null) {
        // The same line as the third star's "entries on time".
        if (result.entryMs > ON_TIME_MS) found.push('late');
        else if (result.entryMs < -ON_TIME_MS) found.push('early');
    }
    if (result.wavering >= Math.max(2, count * 0.3)) found.push('wavering');
    return found.slice(0, 3);
}

/** What went well, said first (codes). */
function wentWell(result, count) {
    const good = [];
    if (result.percent >= 70) good.push('inTune');
    if (result.wavering <= count * 0.1) good.push('steady');
    if (result.timing === 'measured' && result.entryMs !== null && Math.abs(result.entryMs) < 40) good.push('onTime');
    const best = result.phrases.filter((p) => p.percent !== null).sort((a, b) => b.percent - a.percent)[0];
    if (best && best.percent >= 50) good.push('bestPhrase');
    return good.length ? good : ['finished'];
}

/**
 * Stars for an exercise or song section: none for "couldn't hear" (nothing lost), one for
 * finishing, two for 70% on pitch, three for 90% with entries on time. Timing only counts when it
 * was measured; with an estimate, three stars aren't given for timing, and `timingNote` says so.
 */
export function stars(result) {
    if (!result || result.status !== 'scored') return { stars: 0, timingNote: false };
    if (result.percent < 70) return { stars: 1, timingNote: false };
    if (result.percent < 90) return { stars: 2, timingNote: false };
    if (result.timing === 'none') return { stars: 3, timingNote: false };
    if (result.timing === 'estimated') return { stars: 2, timingNote: true };
    const onTime = result.entryMs === null || Math.abs(result.entryMs) <= ON_TIME_MS;
    return { stars: onTime ? 3 : 2, timingNote: false };
}

/**
 * Live, for the play-along screen and Wait for me: given the recent readings (`{ time, hz }`,
 * oldest first) and the note being sung, whether it's lit, how long it's been held there, and
 * which way to go ("higher" or "lower") once the singer has been searching for a moment.
 */
export function liveNote(readings, pitch, { strictness = 'standard', holdSeconds = 0.33, hintAfterSeconds = 1 } = {}) {
    const tolerance = STRICTNESS[strictness] ?? STRICTNESS.standard;
    const latest = readings[readings.length - 1];
    if (!latest) return { state: 'waiting', held: 0, hint: null, cents: null };
    // Held: back through the readings while they're on pitch; a dropped reading (no pitch) for
    // under 60 ms is a breath of the tracker, not the singer leaving the note.
    let held = 0;
    let gapFrom = null;
    for (let i = readings.length - 1; i > 0; i--) {
        const r = readings[i];
        if (!r.hz) {
            gapFrom ??= r.time;
            if (gapFrom - r.time > LIVE_GAP_SECONDS) break;
            continue;
        }
        gapFrom = null;
        if (Math.abs(nearestOctave(hzToCents(r.hz, pitch))) > tolerance) break;
        held = latest.time - readings[i - 1].time;
    }
    const cents = latest.hz ? nearestOctave(hzToCents(latest.hz, pitch)) : null;
    const searching = latest.time - readings[0].time;
    const state = held >= holdSeconds ? 'lit' : cents !== null && Math.abs(cents) <= tolerance ? 'finding' : cents !== null && Math.abs(cents) <= tolerance * 2 ? 'close' : 'searching';
    const hint = state !== 'lit' && cents !== null && searching >= hintAfterSeconds && Math.abs(cents) > tolerance ? (cents < 0 ? 'higher' : 'lower') : null;
    return { state, held, hint, cents };
}

/** Within the octave: a man singing the alto line an octave down is on pitch. */
function nearestOctave(cents) {
    return cents - Math.round(cents / 1200) * 1200;
}

// ---- Small statistics ---------------------------------------------------------------------------

export function median(values) {
    const sorted = [...values].sort((a, b) => a - b);
    const mid = sorted.length >> 1;
    return sorted.length % 2 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;
}

function mean(values) {
    return values.reduce((sum, v) => sum + v, 0) / values.length;
}

function spread(values) {
    const m = mean(values);
    return Math.sqrt(mean(values.map((v) => (v - m) ** 2)));
}

function movingAverage(values, width) {
    if (values.length < width) return [];
    const out = [];
    let sum = 0;
    for (let i = 0; i < values.length; i++) {
        sum += values[i];
        if (i >= width) sum -= values[i - width];
        if (i >= width - 1) out.push(sum / width);
    }
    return out;
}
