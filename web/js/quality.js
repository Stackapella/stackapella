// The quality check before a beta take is sent (docs/beta-singers-plan.md, "The quality check"),
// and the 10-second sound check at setup, which uses the same level checks. Pure functions, on the
// device, tested on synthesized takes (tests/quality.test.js). Takes are checked dry: nothing here
// changes the audio. Each problem comes with its fix. The verdict is the app's, against the bar
// Hillel sets for a guide (pitch and timing): only a take that meets it can be sent.

import { isSilent } from './dsp.js';
import { decibels, trackTake } from './pitchtrack.js';
import { scoreTake } from './scoring.js';

const WINDOW_SECONDS = 0.05;
const SILENT_DB = -55; // quieter than this throughout: nothing was heard
const QUIET_DB = -28; // the loudest moments under this: too far from the mic
const CLIP = 0.99; // a sample this loud has hit the top
const CLIP_RUN = 3; // samples in a row at the top: clipping, not one loud peak
const NOISE_DB = -50; // the quiet moments louder than this: a noisy room or mic
const BLEED_DB = -42; // the singer's rests louder than this, with a pitch: other parts in the mic
const REST_AFTER = 0.25; // seconds after a note before a rest counts: a held ending isn't bleed
const MIN_QUIET_WINDOWS = 10; // half a second of quiet, at least, to judge the room's noise

/** The bar for a guide when the server hasn't said (beta.me's `bar`): about 90% on pitch, steady timing. */
export const DEFAULT_BAR = { pitchPercent: 90, timingPercent: 80 };
// "Close": within this many points of the bar on pitch, and on timing.
const CLOSE_PITCH = 15;
const CLOSE_TIMING = 20;
// A guide is held to more than practice is: lit within 20 cents, timed within 70 ms.
export const GUIDE_STRICTNESS = 'precise';
export const GUIDE_STEADY_MS = 70;
// Tries under the bar before the report kindly suggests another part or the course.
export const TRIES_BEFORE_SUGGESTING = 3;

/**
 * The verdict on a take's `scores` against `bar`: 'ready' (it meets the bar on pitch and timing),
 * 'close' (a few phrases from it), or 'notReady'. A take whose timing couldn't be scored is judged
 * on its pitch.
 */
export function verdict(scores, bar = DEFAULT_BAR) {
    const pitch = scores?.pitchPercent;
    if (pitch === null || pitch === undefined) return 'notReady';
    const timing = scores.timingPercent ?? null;
    if (pitch >= bar.pitchPercent && (timing === null || timing >= bar.timingPercent)) return 'ready';
    if (pitch >= bar.pitchPercent - CLOSE_PITCH && (timing === null || timing >= bar.timingPercent - CLOSE_TIMING)) return 'close';
    return 'notReady';
}

/**
 * The phrases keeping a take from the bar, the weakest first: each `{ index, pitchPercent,
 * timingPercent, why }`, `why` being 'flat', 'sharp', 'mixed' (pitch), 'late', 'early' or 'uneven'
 * (timing).
 */
export function phrasesToFix(result, bar = DEFAULT_BAR) {
    if (!result.score || result.score.status !== 'scored') return [];
    return result.score.phrases
        .filter((p) => (p.percent !== null && p.percent < bar.pitchPercent) || (p.timingPercent !== null && p.timingPercent < bar.timingPercent))
        .map((p) => {
            const pitchShort = p.percent !== null ? bar.pitchPercent - p.percent : 0;
            const timingShort = p.timingPercent !== null ? bar.timingPercent - p.timingPercent : 0;
            const why = pitchShort >= timingShort ? phraseTendency(result.score, p.index, result.phrases, result.notes) : p.lean ?? 'uneven';
            return { index: p.index, pitchPercent: p.percent, timingPercent: p.timingPercent, why, short: Math.max(pitchShort, timingShort) };
        })
        .sort((a, b) => b.short - a.short)
        .map(({ short, ...p }) => p);
}

/** Loudness per 50 ms window, in dBFS. */
export function windows(samples, sampleRate) {
    const size = Math.max(1, Math.round(WINDOW_SECONDS * sampleRate));
    const out = [];
    for (let at = 0; at + size <= samples.length; at += size) {
        out.push(decibels(samples.subarray(at, at + size)));
    }
    return out;
}

/**
 * The level checks, for the sound check and the take: silence, too quiet, clipping and noise. The
 * noise is measured only in `quiet` stretches ([fromSeconds, toSeconds], when the singer isn't
 * singing): measured anywhere, a singer's soft notes would read as a noisy room. With too little
 * quiet to go on, the noise isn't judged ('low').
 */
export function levelChecks(samples, sampleRate, { quiet = [] } = {}) {
    if (!samples.length || isSilent(samples)) return { silent: true, quiet: false, clipping: false, noise: 'low', peakDb: -Infinity, floorDb: -Infinity };
    // Digital silence (-Infinity) counts as very quiet: it's the quietest a room can be.
    const all = windows(samples, sampleRate).map((db) => (Number.isFinite(db) ? db : -120));
    const levels = [...all].sort((a, b) => a - b);
    const loudest = levels.length ? levels[Math.floor(levels.length * 0.98)] : -Infinity;
    const rests = all.filter((_, i) => quiet.some(([from, to]) => i * WINDOW_SECONDS >= from && (i + 1) * WINDOW_SECONDS <= to)).sort((a, b) => a - b);
    const floorDb = rests.length >= MIN_QUIET_WINDOWS ? rests[Math.floor(rests.length * 0.2)] : -Infinity;
    let run = 0;
    let clipped = 0;
    for (let i = 0; i < samples.length; i++) {
        run = Math.abs(samples[i]) >= CLIP ? run + 1 : 0;
        if (run === CLIP_RUN) clipped++;
    }
    return {
        silent: loudest < SILENT_DB,
        quiet: loudest >= SILENT_DB && loudest < QUIET_DB,
        clipping: clipped >= 3,
        noise: floorDb > NOISE_DB + 10 ? 'high' : floorDb > NOISE_DB ? 'some' : 'low',
        peakDb: loudest,
        floorDb,
    };
}

/**
 * The singer's rests in a take of `notes` ([fromSeconds, toSeconds] in the take): a margin after
 * each note for a held ending, and one before the next for an early breath. Only rests of
 * `minSeconds` or more count.
 */
export function rests(notes, { beatSeconds, startSeconds = 0, takeSeconds, after = REST_AFTER, before = 0.1, minSeconds = 0.5 }) {
    const out = [];
    for (let i = 0; i <= notes.length; i++) {
        const from = i === 0 ? 0 : (notes[i - 1].beat + notes[i - 1].length) * beatSeconds - startSeconds + after;
        const to = i === notes.length ? takeSeconds : notes[i].beat * beatSeconds - startSeconds - before;
        if (to - from >= minSeconds) out.push([Math.max(0, from), to]);
    }
    return out;
}

/**
 * Other parts heard in the singer's mic: during the singer's own rests (half a second or more,
 * after a held ending), the take is loud and has a pitch. Without headphones the guides leak in.
 */
export function bleedCheck(samples, sampleRate, { notes, beatSeconds, startSeconds = 0, track = null }) {
    const pitches = track ?? trackTake(samples, sampleRate);
    let restFrames = 0;
    let leaking = 0;
    for (const [from, to] of rests(notes, { beatSeconds, startSeconds, takeSeconds: samples.length / sampleRate })) {
        for (let f = Math.ceil(from * pitches.rate); f < Math.min(pitches.hz.length, Math.floor(to * pitches.rate)); f++) {
            restFrames++;
            if (pitches.hz[f] && pitches.loudness[f] > BLEED_DB) leaking++;
        }
    }
    return restFrames >= 50 && leaking / restFrames > 0.3;
}

/**
 * Everything the report shows for a take of a template part: `{ checks, score, scores }`. Pass
 * `track` (trackInWorker's) so the slow part runs off the main thread.
 * `checks` are codes with a tone, problems first ('silent', 'quiet', 'clipping', 'noise', 'bleed',
 * 'pitch', 'timing') then what went well; `scores` is what's sent with the take (contract/api.md,
 * beta.submissions.create).
 */
export function checkTake(samples, sampleRate, { notes, beatSeconds, startSeconds = 0, phrases = [], timing = 'measured', track = null, strictness = GUIDE_STRICTNESS, steadyMs = GUIDE_STEADY_MS }) {
    const level = levelChecks(samples, sampleRate, { quiet: rests(notes, { beatSeconds, startSeconds, takeSeconds: samples.length / sampleRate }) });
    if (level.silent) {
        return { checks: [{ code: 'silent', tone: 'bad' }], score: null, phrases, notes, scores: { pitchPercent: null, timingPercent: null, timingMs: null, clipping: false, noise: level.noise, bleed: false, phrases: [] } };
    }
    track ??= trackTake(samples, sampleRate);
    const score = scoreTake(track, notes, { beatSeconds, startSeconds, phrases, timing, strictness, steadyMs });
    const bleed = bleedCheck(samples, sampleRate, { notes, beatSeconds, startSeconds, track });
    const checks = [];
    if (level.quiet) checks.push({ code: 'quiet', tone: 'warn' });
    if (level.clipping) checks.push({ code: 'clipping', tone: 'warn' });
    if (level.noise !== 'low') checks.push({ code: 'noise', tone: 'warn' });
    if (bleed) checks.push({ code: 'bleed', tone: 'warn' });
    if (score.status === 'unheard') checks.push({ code: 'unheard', tone: 'warn' });
    else if (score.percent < 80) checks.push({ code: 'pitch', tone: 'warn' });
    if (timing === 'measured' && score.entryMs !== null && Math.abs(score.entryMs) > 60) checks.push({ code: score.entryMs > 0 ? 'late' : 'early', tone: 'warn' });
    if (score.status === 'scored' && score.percent >= 80) checks.unshift({ code: 'inTune', tone: 'good' });
    if (!level.quiet && !level.clipping) checks.unshift({ code: 'levelGood', tone: 'good' });
    const weak = (score.phrases ?? []).filter((p) => p.percent !== null && p.percent < 70).map((p) => ({ index: p.index, pitchPercent: p.percent, tendency: phraseTendency(score, p.index, phrases, notes) }));
    return {
        checks,
        score,
        weakPhrases: weak,
        phrases,
        notes,
        scores: {
            pitchPercent: score.status === 'scored' ? score.percent : null,
            timingPercent: score.status === 'scored' ? score.timingPercent : null,
            timingMs: score.entryMs,
            clipping: level.clipping,
            noise: level.noise,
            bleed,
            phrases: weak,
        },
    };
}

/** "flat" or "sharp" for a phrase whose notes lean one way, otherwise "mixed". */
function phraseTendency(score, index, phrases, notes) {
    const [start, end] = phrases[index] ?? [0, Infinity];
    const cents = score.notes.filter((n) => n.cents !== null && notes[n.index].beat >= start && notes[n.index].beat < end).map((n) => n.cents);
    if (!cents.length) return 'mixed';
    const average = cents.reduce((a, b) => a + b, 0) / cents.length;
    return average < -15 ? 'flat' : average > 15 ? 'sharp' : 'mixed';
}
