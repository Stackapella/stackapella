// Scorers for a course `play` step's `score` (docs/plus-courses-build-plan.md, 6a): beyond the
// exercise's own ('hold' and the default, on pitch). Each takes `{ track, dots, target, notes,
// beatSeconds, strictness, exercise }` (the take's pitch track, its chart dots, the target notes in
// seconds, the notes in beats, the step's exercise) and returns `{ percent, tip? }`, percent null
// when it couldn't be judged. It may add `line: { key, values }` (what it measured, said plainly),
// `label: { key }` (its name for the percent, "{percent}% ringing", for the result's heading),
// `personal` (a value the course keeps) and `minStars`. `speaker` is true when the take was sung
// through a speaker, so what played under it rested while you sang (a chord to ring against wasn't
// sounding): a measure that needs it says so rather than scoring. A
// course agent registers its own here (with the lead); components/exercise.js asks this first.

import { centsOf, straightPercent } from './vibrato.js';
import { ENTRY_ON_TIME, RELEASE_ON_TIME, skillPercent } from './pitch-skills.js';
import { RING, ringTake } from './ring.js';

const SPEAKER_MOST = 85; // a ring step through a speaker: two stars at most
import { scoreTake } from './scoring.js';
import { holdsPercent, pitchAt, speciesPercent } from './harmony.js';
import { DRIFT_FULL, flipPercent, flipPoint, flips, settleFlip, keyDrift as lineKeyDrift, pitchParts, styledMoves } from './style-scores.js';
import { midiName } from './dsp.js';
import { formatNumber, t, tk } from './i18n.js';

/** A straight tone on every held note: the Vibrato course's measure, for any course's play step. */
function straight({ track, target }) {
    const held = target.filter((n) => n.end - n.start >= 1);
    if (!held.length) return { percent: null };
    const results = held.map((n) => straightPercent(centsOf(track, { from: n.start, to: n.end, midi: n.pitch }), track.rate)).filter((r) => r.percent !== null);
    if (!results.length) return { percent: null };
    return { percent: Math.round(results.reduce((sum, r) => sum + r.percent, 0) / results.length) };
}

// The styles courses' plain lines under a take (what was measured, in words: read with stars hidden too).
const STYLE_LINES = {
    held: tk('styles.keyDrift.held'), low: tk('styles.keyDrift.low'), high: tk('styles.keyDrift.high'),
    flips: tk('styles.flip.count'), flipsDown: tk('styles.flip.countDown'), pitchOnly: tk('styles.flip.pitchOnly'),
    found: tk('styles.flip.found'), once: tk('styles.flip.once'), none: tk('styles.flip.none'),
    asked: tk('styles.moves.asked'), yours: tk('styles.moves.yours'), noMoves: tk('styles.moves.none'), tooMany: tk('styles.moves.tooMany'),
};
const SIREN_HEARD = 0.4; // of the siren's readings voiced, or it wasn't sung enough to look for a flip

/** A line sung with nothing underneath: its intervals against the key you set, and how far it slid (folk). */
function keyDrift({ track, notes, beatSeconds, strictness }) {
    const score = scoreTake(track, notes, { beatSeconds, strictness, timing: 'none' });
    if (score.status !== 'scored') return { percent: null };
    const r = lineKeyDrift(score.notes, notes, { beatSeconds, strictness });
    if (r.status !== 'scored') return { percent: null };
    const values = { cents: Math.abs(r.drift), intervals: r.intervalsPercent };
    return { percent: r.percent, line: { key: Math.abs(r.drift) <= DRIFT_FULL ? STYLE_LINES.held : r.drift < 0 ? STYLE_LINES.low : STYLE_LINES.high, values } };
}

/**
 * Where your voice flips (Yodeling's sirens, `wave` paths): each siren's downward flips join those
 * kept with the course (`personal.flipSeen`), and the flip is set once two agree (settleFlip). A
 * siren that glided all the way still earns a fair try, and says so.
 */
function flipFinder({ track, target, notes, exercise, personal = {} }) {
    const home = notes[0].pitch - (exercise.step ?? 0);
    const from = target[0].start;
    const to = target[target.length - 1].end;
    const span = track.hz.slice(Math.round(from * track.rate), Math.round(to * track.rate));
    if (!span.length || span.filter((hz) => hz > 0).length / span.length < SIREN_HEARD) return { percent: null };
    const found = flipPoint(track, { home, from, to });
    const settled = settleFlip(personal.flipSeen, found);
    const kept = { flipSeen: settled.seen, ...(settled.flip !== null ? { flip: settled.flip } : {}) };
    if (settled.flip !== null) return { percent: 100, personal: kept, line: { key: STYLE_LINES.found, values: { note: midiName(home + settled.flip) } } };
    if (settled.seen.length) return { percent: 85, personal: kept, line: { key: STYLE_LINES.once, values: { note: midiName(home + Math.round(settled.seen[settled.seen.length - 1])) } } };
    return { percent: 70, personal: kept, line: { key: STYLE_LINES.none, values: {} } };
}

/** Yodel flips: half the stars for clean flips on every leap between joined notes, half for the notes' pitch (yodeling). */
function flip({ track, notes, beatSeconds, strictness, exercise, personal }) {
    const score = scoreTake(track, notes, { beatSeconds, strictness, timing: 'none' });
    // Set from the flip note before one is found (a fallback a little above your note): the leaps may
    // not cross your flip, so only the notes are starred, and the line says why.
    if (exercise?.base === 'flip' && personal && personal.flip === undefined) {
        return { percent: score.status === 'scored' ? score.percent : null, line: { key: STYLE_LINES.pitchOnly, values: {} } };
    }
    const found = flips(track, notes, { beatSeconds });
    const flipped = flipPercent(found, { generous: !!exercise?.generous });
    if (score.status !== 'scored' || flipped === null) return { percent: score.status === 'scored' ? score.percent : null };
    const clean = found.filter((f) => f.kind === 'clean').length;
    const down = found.filter((f) => f.direction === 'down' && f.kind !== 'clean').length;
    return { percent: Math.round((flipped + score.percent) / 2), line: { key: down ? STYLE_LINES.flipsDown : STYLE_LINES.flips, values: { clean, count: found.length, down } } };
}

/**
 * Styled notes (Pop and country): half the stars for the moves a line asked for (each note's `style`
 * in the step's own notes), half for pitch; with none asked, the learner's own way: pitch and any one
 * move heard, never more for more. `exercise.window` lengthens a lazy scoop's arrival.
 */
function styled({ track, notes, beatSeconds, strictness, exercise }) {
    // The notes you sing: a call and response's `answer`, else the step's notes (each with its `style`).
    const styles = (exercise.answer ?? exercise.notes ?? []).map((n) => n.style ?? null);
    const score = scoreTake(track, pitchParts(notes, styles, { beatSeconds, window: exercise.window }), { beatSeconds, strictness, timing: 'none' });
    if (score.status !== 'scored') return { percent: null };
    const moves = styledMoves(track, notes, styles, { beatSeconds, window: exercise.window });
    const movesPercent = moves.asked ? (moves.matched / moves.asked) * 100 : moves.matched * 100;
    const line = moves.asked ? { key: STYLE_LINES.asked, values: { matched: moves.matched, asked: moves.asked } }
        : moves.tooMany ? { key: STYLE_LINES.tooMany, values: {} }
            : moves.heard.length ? { key: STYLE_LINES.yours, values: { count: moves.heard.length } } : { key: STYLE_LINES.noMoves, values: {} };
    return { percent: Math.round((movesPercent + score.percent) / 2), line };
}

/** Each sung note's semitones from do (the answer, when the call differs; never a listen-only note), for lock's do-and-so rule. */
const sungSteps = (exercise) => (exercise.answer ?? exercise.notes).filter((n) => !n.listenOnly).map((n) => n.step);

/** A pitch skill (js/pitch-skills.js): Perfect your pitch, Breath and stamina, and any course's step. */
const skill = (kind) => ({ track, notes, beatSeconds, exercise }) => {
    const { percent, notes: measured } = skillPercent(kind, track, notes, beatSeconds, { generous: !!exercise.generous, steps: sungSteps(exercise) });
    const slid = measured.filter((m) => m && m.start && m.start !== 'clean').length;
    return { percent, tip: kind === 'landing' && slid >= Math.max(2, measured.length * 0.3) ? 'onsets' : undefined, line: percent === null ? null : skillLine(kind, measured) };
};

const ENDING = { steady: tk('skills.ending.steady'), sagged: tk('skills.ending.sagged'), wobbled: tk('skills.ending.wobbled') };
const seconds = (s) => formatNumber(Math.round(Math.abs(s) * 10) / 10);

/** What a release or an entry measured, in plain words (shown under the take's result): `{ key, values }` or null. */
export function skillLine(kind, measured) {
    const judged = measured.filter((m) => m && m.off !== null && m.off !== undefined);
    if (kind === 'release' && judged.length) {
        const off = judged.reduce((a, m) => a + m.off, 0) / judged.length;
        const ending = t(ENDING[judged.at(-1).tail] ?? ENDING.steady);
        if (Math.abs(off) <= RELEASE_ON_TIME) return { key: tk('skills.release.onTime'), values: { ending } };
        return { key: off < 0 ? tk('skills.release.early') : tk('skills.release.late'), values: { seconds: seconds(off), ending } };
    }
    if (kind === 'entry') {
        if (measured.some((m) => m?.through)) return { key: tk('skills.entry.through'), values: {} };
        if (!judged.length) return null;
        const off = judged.reduce((a, m) => a + m.off, 0) / judged.length;
        if (Math.abs(off) <= ENTRY_ON_TIME) return { key: tk('skills.entry.onTime'), values: {} };
        return { key: off < 0 ? tk('skills.entry.early') : tk('skills.entry.late'), values: { seconds: seconds(off) } };
    }
    return null;
}

/**
 * A line you chose against a cantus (Counterpoint's unit 5): on pitch, with three stars only when the
 * step's `rules` hold (js/harmony.js speciesPercent). The cantus is the first `with` line, in steps
 * from the same home as the notes.
 */
function species({ track, notes, beatSeconds, strictness, exercise }) {
    const score = scoreTake(track, notes, { beatSeconds, strictness, timing: 'none' });
    if (score.status !== 'scored') return { percent: null };
    const sung = exercise.notes.filter((n) => !n.listenOnly);
    const home = sung[0].choices ? notes[0].choices[0] - sung[0].choices[0] : notes[0].pitch - sung[0].step;
    const cantusLine = exercise.with[0].notes.map((n) => ({ ...n, pitch: home + n.step }));
    const cantus = notes.map((n) => pitchAt(cantusLine, n.beat + n.length / 2));
    if (cantus.some((p) => p === null)) return { percent: score.percent };
    return { percent: speciesPercent(score.percent, notes.map((n) => n.pitch), cantus, exercise.rules) };
}

/**
 * Barbershop's ring (js/ring.js): every held chord note against its pure target, with what the last
 * one measured in plain words ("6 cents high: let it fall").
 */
function ring(args) {
    const { track, target, exercise } = args;
    // Through a speaker the guides go silent once you sing: there's no chord to ring against, so pitch only.
    // ...and at most two stars: pitch alone can't tell a ringing third from the piano's.
    if (args.speaker) {
        const pitch = pitchOf(args);
        return { percent: pitch === null ? null : Math.min(pitch, SPEAKER_MOST), line: { key: tk('ring.line.headphones'), values: {} } };
    }
    const sung = (exercise.answer ?? exercise.notes).filter((n) => !n.listenOnly);
    const offsets = sung.map((n) => n.cents ?? 0);
    const { percent, notes } = ringTake(track, target, offsets, { generous: !!exercise.generous, skip: sung.map((n) => n.ring === false) });
    const last = notes.filter((m) => m && m.off !== null).at(-1);
    const label = { key: tk('ring.label') }; // the heading: "92% ringing", never "on pitch"
    if (!last) return { percent, label };
    // The line speaks for the whole take: a wave anywhere, or a held note not sung, before the last note's cents.
    if (notes.some((m) => m?.waved)) return { percent, label, line: { key: tk('ring.line.waved'), values: {} } };
    if (notes.some((m) => m && m.off === null)) return { percent, label, line: { key: tk('ring.line.missed'), values: {} } };
    const cents = formatNumber(Math.abs(last.off));
    const key = Math.abs(last.off) <= RING.locked ? tk('ring.line.locked') : last.off > 0 ? tk('ring.line.high') : tk('ring.line.low');
    return { percent, label, line: { key, values: { cents } } };
}

/** A take's pitch percent at the precise tolerance (within 20 cents), or null when it couldn't be heard. */
function pitchOf({ track, notes, beatSeconds }) {
    const pitch = scoreTake(track, notes, { beatSeconds, startSeconds: 0, strictness: 'precise', timing: 'none' });
    return pitch.status === 'scored' ? pitch.percent : null;
}

/** A barbershop part (not the lead): half on pitch (within 20 cents), half on its held chords ringing. */
function partring(args) {
    const pitch = pitchOf(args);
    if (pitch === null) return { percent: null };
    const rung = ring(args);
    if (args.speaker) return rung;
    return { percent: rung.percent === null || rung.percent === undefined ? pitch : Math.round((pitch + rung.percent) / 2), line: rung.line, label: { key: tk('ring.labelPart') } };
}

export const PLAY_SCORERS = {
    species, // a line you chose against a cantus, three stars only by the rules (counterpoint's unit 5)
    holds: ({ dots, target }) => ({ percent: holdsPercent(dots, target) }), // several holds with breaths between, each judged on its own (counterpoint's pedals)
    straight, // every note of a second or more held straight (folk, choirs, barbershop)
    landing: skill('landing'), // where each note landed, and whether it slid in
    lock: skill('lock'), // a hold locked to its target (do and so; other notes on their centre)
    centre: skill('centre'), // each note's centre
    drift: skill('drift'), // a long note's sag or creep
    memory: skill('memory'), // a note sung from memory
    line: skill('line'), // a phrase: where its notes landed, and its last note ending steady
    release: skill('release'), // a note ending on time, and steady to its end
    entry: skill('entry'), // back in after a breath, on time and on pitch
    cold: skill('cold'), // a tune started from memory: its first note, then the rest from it
    keyDrift, // a line sung on its own: intervals alone and the key held (folk's unit 4)
    flip, // yodel flips between joined notes, clean or slid, and the notes' pitch (yodeling)
    flipFinder, // where your voice flips, from a siren (yodeling's first lesson)
    styled, // scoops, fall-offs, slides and cries asked for (or chosen), and pitch (pop and country)
    ring, // Barbershop: held chord notes against their pure targets
    partring, // Barbershop: a part on pitch and its held chords ringing
};
