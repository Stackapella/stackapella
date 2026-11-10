// Scorers for a course `play` step's `score` (docs/plus-courses-build-plan.md, 6a): beyond the
// exercise's own ('hold' and the default, on pitch). Each takes `{ track, dots, target, notes,
// beatSeconds, strictness, exercise }` (the take's pitch track, its chart dots, the target notes in
// seconds, the notes in beats, the step's exercise) and returns `{ percent, tip? }`, percent null
// when it couldn't be judged. It may add `line: { key, values }` (what it measured, said plainly),
// `label: { key, stat }` (its name for the percent, "{percent}% ringing", for the result's heading, and `stat` the
// short caption under the percent in the result's tiles, "ringing", where "on pitch" would be wrong),
// `personal` (a value the course keeps) and `minStars`. `speaker` is true when the take was sung
// through a speaker, so what played under it rested while you sang (a chord to ring against wasn't
// sounding): a measure that needs it says so rather than scoring. A
// course agent registers its own here (with the lead); components/exercise.js asks this first.

import { bandOf, centsOf, pathCents, straightPercent } from './vibrato.js';
import { ENTRY_ON_TIME, RELEASE_ON_TIME, skillPercent } from './pitch-skills.js';
import { RING, ringTake } from './ring.js';

const SPEAKER_MOST = 85; // a ring step through a speaker: two stars at most
import { scoreTake } from './scoring.js';
import { holdsPercent, pitchAt, speciesPercent } from './harmony.js';
import { BREAK_EDGE, breaksIn, DRIFT_FULL, flipPercent, flipPoint, flips, settleFlip, keyDrift as lineKeyDrift, pitchParts, soundingFrames, steadyRange, styledMoves } from './style-scores.js';
import { midiName } from './dsp.js';
import { WHISTLE_CLEAN, whistleNoteOf } from './whistle.js';
import { starsFor } from './course.js';
import { formatList, formatNumber, t, tk } from './i18n.js';

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
    span: tk('styles.range.easy'), spanOne: tk('styles.range.one'), spanNone: tk('styles.range.none'),
    smooth: tk('styles.range.smooth'), jumped: tk('styles.range.jumped'), jumpedMore: tk('styles.range.jumpedMore'),
    whistleFound: tk('styles.whistle.found'), whistleUnheard: tk('styles.whistle.unheard'),
    whole: tk('styles.unbroken.whole'), stoppedOnce: tk('styles.unbroken.once'), stoppedMore: tk('styles.unbroken.more'), unheard: tk('styles.unbroken.unheard'),
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
    // Every heard siren earns its stars: the words say "Scored on singing the siren", and a light siren that blends
    // is the safe take (the scoring audit, 2026-11-08: it scored 70, under a pushed one that cracked, 85). The line
    // still says what was found.
    if (settled.seen.length) return { percent: 100, personal: kept, line: { key: STYLE_LINES.once, values: { note: midiName(home + Math.round(settled.seen[settled.seen.length - 1])) } } };
    return { percent: 100, personal: kept, line: { key: STYLE_LINES.none, values: {} } };
}

/** A wave step's slide or siren: its seconds, and whether enough of it was sung to judge. */
function sirenSpan(track, target) {
    const from = target[0].start;
    const to = target[target.length - 1].end;
    const span = track.hz.slice(Math.round(from * track.rate), Math.round(to * track.rate));
    return { from, to, heard: span.length > 0 && span.filter((hz) => hz > 0).length / span.length >= SIREN_HEARD };
}

/**
 * Your easy range, from a slide (Range and registers: docs/courses/range-registers.md): its lowest and
 * highest steady notes, said as notes and added to the range the app keeps (`sung`, through
 * noteSung). Scored on singing the slide, never on how far it went: nobody is rewarded for pushing.
 */
function span({ track, target }) {
    const { from, to, heard } = sirenSpan(track, target);
    if (!heard) return { percent: null };
    const found = steadyRange(track, { from, to });
    if (!found) return { percent: 70, line: { key: STYLE_LINES.spanNone, values: {} } };
    const values = { low: midiName(found.low), high: midiName(found.high) };
    return { percent: 100, sung: [found.low, found.high], line: { key: found.low === found.high ? STYLE_LINES.spanOne : STYLE_LINES.span, values } };
}

/**
 * Across the break without a jump (Range and registers' unit 4): Yodeling's flipPoint read the other
 * way, no jump the top score; a jump says where, so the next try goes lighter just before it.
 */
function smooth({ track, target, notes, exercise }) {
    const { from, to, heard } = sirenSpan(track, target);
    if (!heard) return { percent: null };
    const home = notes[0].pitch - (exercise.step ?? 0);
    const { jumps } = flipPoint(track, { home, from, to });
    if (!jumps.length) return { percent: 100, line: { key: STYLE_LINES.smooth, values: {} } };
    const where = [...new Set(jumps.map((j) => midiName(Math.round(home + j.middle))))];
    if (jumps.length === 1) return { percent: exercise.generous ? 75 : 60, line: { key: STYLE_LINES.jumped, values: { note: where[0] } } };
    return { percent: exercise.generous ? 55 : 40, line: { key: STYLE_LINES.jumpedMore, values: { count: jumps.length, notes: formatList(where) } } };
}

/**
 * One unbroken sound along a path (Humming and lip trills: a lip trill, a hum or an "oo", whichever you make): how much
 * of what was heard rode the ribbon, each reading folded to the octave nearest it, less for each break (breaksIn: a stop
 * of BREAK_SECONDS, judged by loudness), never anything for height or length. The ends of each sounding stretch
 * (BREAK_EDGE) aren't followed: a trill's start and release read an octave high. Not heard clearly is no score and no
 * break, and says what to try. A trill's take is tracked at TRILL_CLARITY (`"trill": true`, js/pitchtrack.js rangeFor).
 */
function unbroken({ track, target, exercise }) {
    const { from, to, heard } = sirenSpan(track, target);
    const unheard = { percent: null, line: { key: STYLE_LINES.unheard, values: {} } };
    if (!heard) return unheard;
    const shapes = exercise.target ?? [];
    const { first, sounding } = soundingFrames(track, { from, to });
    const edge = Math.round(BREAK_EDGE * track.rate);
    // Clear of every stop and of the take's own ends by `edge` frames.
    const settled = (i) => {
        for (let k = i - first - edge; k <= i - first + edge; k++) if (!sounding[k]) return false;
        return true;
    };
    let inside = 0;
    let voiced = 0;
    target.forEach((span, s) => {
        const segment = shapes[s];
        if (!segment || segment.kind !== 'path') return;
        const start = Math.max(0, Math.ceil(span.start * track.rate));
        const cents = centsOf(track, { from: span.start, to: span.end, midi: span.pitch });
        const band = bandOf(segment);
        cents.forEach((c, k) => {
            if (c === null || !Number.isFinite(c) || !settled(start + k)) return;
            const want = pathCents(segment, k / track.rate);
            voiced++;
            if (Math.abs(c - 1200 * Math.round((c - want) / 1200) - want) <= band) inside++;
        });
    });
    if (!voiced) return unheard;
    const follow = Math.min(1, inside / voiced / 0.9);
    const breaks = breaksIn(track, { from, to });
    const where = (at) => {
        const i = Math.max(0, target.findIndex((s) => at >= s.start && at < s.end));
        const segment = shapes[i];
        const cents = segment?.kind === 'path' ? pathCents(segment, at - target[i].start) : 0;
        return midiName(Math.round(target[i].pitch + cents / 100));
    };
    const keep = breaks.length === 0 ? 1 : breaks.length === 1 ? (exercise.generous ? 0.85 : 0.75) : exercise.generous ? 0.7 : 0.55;
    const percent = Math.round(100 * follow * keep);
    if (!breaks.length) return { percent, line: { key: STYLE_LINES.whole, values: {} } };
    const notes = [...new Set(breaks.map(where))];
    if (breaks.length === 1) return { percent, line: { key: STYLE_LINES.stoppedOnce, values: { note: notes[0] } } };
    return { percent, line: { key: STYLE_LINES.stoppedMore, values: { count: breaks.length, notes: formatList(notes) } } };
}

/**
 * Your whistle note (Whistling's first lesson: a held note where your whistle comes easiest): the steady part's median,
 * the scoop at its start left out (js/whistle.js whistleNoteOf), kept with the course (`personal.whistle`) for every step
 * laid from it (`base: "whistle"`). Lenient: any second of steady whistle, a breathy one too, finds it and earns its
 * stars; a later take of the step finds it again. Read from the take as heard (`heard`), not folded onto the line.
 */
function whistleNote({ track, heard = track, target }) {
    const found = whistleNoteOf(heard, { from: target[0].start, to: target[target.length - 1].end });
    if (!found) return { percent: null, line: { key: STYLE_LINES.whistleUnheard, values: {} } };
    return { percent: 100, personal: { whistle: found.midi }, line: { key: STYLE_LINES.whistleFound, values: { note: midiName(found.midi) } } };
}

/** Yodel flips: half the stars for clean flips on every leap between joined notes, half for the notes' pitch (yodeling). */
function flip({ track, notes, beatSeconds, strictness, exercise, personal }) {
    const score = scoreTake(track, notes, { beatSeconds, strictness, timing: 'none' });
    // Set from the flip note before one is found (a fallback a little above your note): the leaps may
    // not cross your flip, so only the notes are starred, and the line says why.
    if ((exercise?.base === 'flip' || exercise?.base === 'flipKey') && personal && personal.flip === undefined) {
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
    // Joined notes read from where the voice crossed onto them, as the default measure does: a slide's arrival isn't a miss.
    const score = scoreTake(track, pitchParts(notes, styles, { beatSeconds, window: exercise.window }), { beatSeconds, strictness, timing: 'none', crossing: true });
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

/** A whistle's start scoops a little longer and is still clean (js/whistle.js WHISTLE_CLEAN). */
const cleanFor = (exercise) => (exercise?.whistle ? { clean: WHISTLE_CLEAN } : {});

/** A pitch skill (js/pitch-skills.js): Perfect your pitch, Breath and stamina, and any course's step. */
const skill = (kind) => ({ track, notes, beatSeconds, exercise }) => {
    const { percent, notes: measured } = skillPercent(kind, track, notes, beatSeconds, { generous: !!exercise.generous, steps: sungSteps(exercise), ...cleanFor(exercise) });
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
    const label = { key: tk('ring.label'), stat: tk('ring.stat') }; // the heading: "92% ringing", never "on pitch"
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
    return { percent: rung.percent === null || rung.percent === undefined ? pitch : Math.round((pitch + rung.percent) / 2), line: rung.line, label: { key: tk('ring.labelPart'), stat: tk('ring.statPart') } };
}

const READ_STEADY_MS = 150; // a read note's start counts as on time within this of its beat (generous: a reader, not a drummer)
const READ_TIMING_SHARE = 0.3; // with rhythm on, timing's share of the score; pitch keeps the rest

/**
 * Sight-singing (docs/sight-singing-plan.md): a line read from the stave, scored on each note's
 * pitch; with `staff.rhythm` (unit 3 on), also on when each note starts (30%), measured against the
 * timing check (or estimated). With no timing to go on, pitch alone, so a slow but true reader isn't
 * punished for what couldn't be heard.
 */
function read({ track, notes, beatSeconds, strictness, exercise, timing = 'estimated' }) {
    const pitch = scoreTake(track, notes, { beatSeconds, startSeconds: 0, strictness: strictness ?? 'relaxed', timing });
    if (pitch.status !== 'scored') return { percent: null };
    if (!exercise?.staff?.rhythm) return { percent: pitch.percent };
    const timed = readTiming(pitch.notes, notes, timing);
    if (timed === null) return { percent: pitch.percent };
    const percent = Math.round(pitch.percent * (1 - READ_TIMING_SHARE) + timed * READ_TIMING_SHARE);
    return { percent, line: { key: tk('course.read.measured'), values: { pitch: Math.round(pitch.percent), timing: Math.round(timed) } } };
}

/**
 * A read line's timing: of the notes that start something (a new pitch, or after a rest) and were
 * sung, the share that started within READ_STEADY_MS of their beat, against the singer's usual offset
 * (up to 100 ms of it forgiven when the latency was measured, as the shared scorer does). A note
 * whose start wasn't heard near its beat (a rhythm misread: held too long, come in late) counts as
 * off. Null with fewer than three such notes heard, when there's nothing to judge.
 */
function readTiming(results, notes, timing) {
    const starts = results.filter((r) => r.level !== 'unheard' && r.index > 0 && (notes[r.index].beat > notes[r.index - 1].beat + notes[r.index - 1].length + 1e-6 || notes[r.index].pitch !== notes[r.index - 1].pitch));
    if (starts.length < 3) return null;
    const heard = starts.map((r) => r.entryMs ?? r.changeMs).filter((ms) => ms !== null && ms !== undefined).sort((a, b) => a - b);
    const usual = heard.length ? heard[heard.length >> 1] : 0;
    const reference = timing === 'measured' ? Math.max(-100, Math.min(100, usual)) : usual;
    const onTime = starts.filter((r) => {
        const ms = r.entryMs ?? r.changeMs;
        return ms !== null && ms !== undefined && Math.abs(ms - reference) <= READ_STEADY_MS;
    }).length;
    return Math.round((onTime / starts.length) * 100);
}

// ---- A play step's `rhythm` (docs/play-timing-plan.md; Hillel's yes, 2026-11-08) ----

const STAR_LINES = { 1: 0, 2: 70, 3: 90 }; // starsFor's lines: the least percent for each star
export const RHYTHM_SHARE = 0.3; // `rhythm`: timing's share of the stars (as the stave's), pitch the rest...
export const RHYTHM_SHARE_UNSURE = 0.15; // ...half that through a speaker, or with the latency only estimated

export const RHYTHM_FORGIVEN_MS = 75; // a beat step forgives an even offset up to this (80 ms behind scores full, 150 ms doesn't)

/**
 * The timing window for a step's `rhythm`: an eighth of a beat, kept between 60 and 70 ms, straight or swing. With
 * RHYTHM_FORGIVEN_MS forgiven, a take 80 ms behind (and a singer's ordinary jitter) still fits it at any tempo, and
 * one 150 ms behind doesn't. Undefined (no rhythm) keeps scoreTake's default.
 */
export function rhythmSteadyMs(rhythm, beatSeconds) {
    return rhythm ? Math.max(60, Math.min(70, Math.round((beatSeconds * 1000) / 8))) : undefined;
}

/**
 * scoreTake's timing options for a step's `rhythm` (the courses lead's timing simulation, 2026-11-08): the window
 * above; a smaller forgiven offset; the usual offset read from the opening moments, so a rush or a drag shows; a due
 * moment not found counted as a miss, so a very late take can't escape; swing judged on its off-beats too.
 * An empty object without rhythm.
 */
export function rhythmOptions(rhythm, beatSeconds) {
    if (!rhythm) return {};
    return { steadyMs: rhythmSteadyMs(rhythm, beatSeconds), forgiveMs: RHYTHM_FORGIVEN_MS, drift: true, countMissing: true, swing: rhythm === 'swing' };
}

/**
 * A default-scored play step with `rhythm` (true or "swing"): pitch 70 and timing 30 (scoreTake's timingPercent),
 * timing at half weight through a speaker or with only an estimated latency, so a device the app can't time well
 * doesn't cost stars. Null when there's no rhythm, or too few timed moments to judge (pitch alone, as before).
 */
export function withRhythm(score, { rhythm = null, speaker = false, measured = false } = {}) {
    if (!rhythm || score?.status !== 'scored' || score.percent === null || score.percent === undefined) return null;
    const timing = score.timingPercent;
    if (timing === null || timing === undefined) return null;
    const share = speaker || !measured ? RHYTHM_SHARE_UNSURE : RHYTHM_SHARE;
    // The beat never shuts a unit pitch alone would open (the courses lead, 2026-11-08): a take whose pitch earns two
    // or three stars keeps at least two, its percent raised to that line so the number and the stars agree. The beat
    // still decides the third star; a one-star pitch stays as mixed. (At first the floor was one star under the pitch's,
    // and a unit of timed steps could shut on a beginner whose pitch earned two: 0.7 × 78 + 0.3 × 50 is 70, just under.)
    const mixed = Math.round(score.percent * (1 - share) + timing * share);
    const pitchStars = starsFor({ percent: score.percent, timing: 'none' });
    const floor = pitchStars >= 2 ? STAR_LINES[Math.min(pitchStars, 2)] : 0;
    return {
        percent: Math.max(mixed, floor),
        label: { key: tk('course.rhythm.label'), stat: tk('course.rhythm.stat') }, // the percent is pitch and time together, never "on pitch"
        line: { key: tk('course.read.measured'), values: { pitch: Math.round(score.percent), timing: Math.round(timing) } },
    };
}

// ---- A play step's `score: "legato"` (Classical singing basics: joined notes, the sound kept through each change) ----

export const LEGATO_DIP_DB = 12; // a join breaks when the voice dips this far under the softer of its two notes
const LEGATO_GAP_SECONDS = 0.06; // a take without loudness readings (a synthetic track): a gap this long breaks it
const LEGATO_BEFORE = 0.2; // seconds of a join looked at before the written change...
const LEGATO_AFTER = 0.3; // ...and after it (a singer a little behind changes late)
const LEGATO_EDGE = 0.05; // never within this of a joined pair's outer ends (a rest after a short note isn't a break)
export const LEGATO_SHARE = 0.3; // the joins' share of the stars, pitch (landing) the rest; half through a speaker

/**
 * Each join of `notes` (two notes with no rest between them), kept or broken. Judged by loudness, not by whether a
 * pitch was read: the tracker's 43 ms window holds two pitches as it crosses a change, and reads nothing for up to
 * 50 ms of a truly joined line (low voices most), while a sung break of 100 ms loses only 10 to 50 ms of readings
 * (the voice's release and the tracker's smoothing) but dips 14 to 26 dB; a joined change dips 3 dB at most (the
 * study, 2026-11-08: the app's own voices from G2 to E4, steps, fourths and octaves, 60 and 100 ms breaks). So a join
 * breaks where the voice falls LEGATO_DIP_DB under the softer of its two notes (each one's median voiced level), whatever
 * the mic's gain: measured locally, never against the whole take, so a diminuendo, a soft phrase or a sudden piano on
 * the change (the coordinator, 2026-11-08: a classical course asks for dynamics) keeps its joins.
 */
export function legatoJoins(track, notes, beatSeconds) {
    const at = (seconds) => Math.max(0, Math.min(track.hz.length - 1, Math.round(seconds * track.rate)));
    const loud = track.loudness?.length === track.hz.length ? track.loudness : null;
    /** A note's own level: the median loudness of its voiced readings, its ends left out; null with none. */
    const level = (n) => {
        const voiced = [];
        for (let k = at(n.beat * beatSeconds + LEGATO_EDGE); k <= at((n.beat + n.length) * beatSeconds - LEGATO_EDGE); k++) if (track.hz[k] > 0 && Number.isFinite(loud[k])) voiced.push(loud[k]);
        return voiced.length ? voiced.sort((x, y) => x - y)[voiced.length >> 1] : null;
    };
    const joins = [];
    notes.forEach((b, i) => {
        const a = notes[i - 1];
        if (!a || Math.abs(a.beat + a.length - b.beat) > 1e-6) return;
        const change = b.beat * beatSeconds;
        const from = at(Math.max(change - LEGATO_BEFORE, a.beat * beatSeconds + LEGATO_EDGE));
        const to = at(Math.min(change + LEGATO_AFTER, (b.beat + b.length) * beatSeconds - LEGATO_EDGE));
        let broken;
        const levels = loud ? [level(a), level(b)].filter((l) => l !== null) : [];
        const reference = levels.length ? Math.min(...levels) : null;
        if (reference !== null) {
            let quietest = Infinity;
            for (let k = from; k <= to; k++) quietest = Math.min(quietest, Number.isFinite(loud[k]) ? loud[k] : -Infinity);
            broken = reference - quietest >= LEGATO_DIP_DB;
        } else {
            let run = 0;
            let longest = 0;
            for (let k = from; k <= to; k++) longest = Math.max(longest, (run = track.hz[k] > 0 ? 0 : run + 1));
            broken = longest / track.rate > LEGATO_GAP_SECONDS;
        }
        joins.push({ index: i, broken });
    });
    return joins;
}

/**
 * Joined notes (`score: "legato"`, a step whose words have no consonants: the courses lead's generator allows it only
 * there): pitch as `landing` stars it, and the joins kept, `LEGATO_SHARE` of the stars (half through a speaker, as
 * the beat's), so a beginner who breaks a few keeps most of the score. With no joins it's pitch alone.
 */
function legato({ track, notes, beatSeconds, exercise, speaker = false }) {
    const pitch = skillPercent('landing', track, notes, beatSeconds, { generous: !!exercise.generous, steps: sungSteps(exercise), ...cleanFor(exercise) });
    if (pitch.percent === null) return { percent: null };
    const joins = legatoJoins(track, notes, beatSeconds);
    if (!joins.length) return { percent: pitch.percent };
    const broken = joins.filter((j) => j.broken).length;
    const share = speaker ? LEGATO_SHARE / 2 : LEGATO_SHARE;
    const kept = ((joins.length - broken) / joins.length) * 100;
    return {
        percent: Math.round(pitch.percent * (1 - share) + kept * share),
        label: { key: tk('course.legato.label'), stat: tk('course.legato.stat') },
        line: broken ? { key: tk('skills.legato.broken'), values: { broken: formatNumber(broken), count: formatNumber(joins.length) } } : { key: tk('skills.legato.kept'), values: {} },
    };
}

export const PLAY_SCORERS = {
    read, // sight-singing: a line read from the stave, on each note's pitch
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
    span, // your easy range, from a slide (range and registers)
    smooth, // across the break without a jump (range and registers)
    unbroken, // one unbroken lip trill, hum or "oo" along a path (humming and lip trills)
    whistleNote, // your whistle note, from a held whistle (whistling's first lesson)
    styled, // scoops, fall-offs, slides and cries asked for (or chosen), and pitch (pop and country)
    ring, // Barbershop: held chord notes against their pure targets
    partring, // Barbershop: a part on pitch and its held chords ringing
    legato, // Classical singing basics: notes joined, the sound kept through each change, and pitch
};
