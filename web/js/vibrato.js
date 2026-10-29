import { bendAt } from './synth.js';

// Vibrato, measured (docs/plus-courses-build-plan.md, section 4, "What the app measures"): a held
// note's centre, the rate and width of its wave, how even the cycles are, when the wave starts, and
// what kind it is (straight, vibrato, wobble, tremble, uneven, unclear); then the stars' percent for
// the Vibrato course's three kinds of step (a straight tone, a steered wave, the delayed bloom). Pure,
// unit-tested in tests/vibrato.test.js. Widths are ± cents, half the full swing, as scoring.js's
// vibrato() gives them. The voice teacher's numbers; tuned on real voices on the beta before stars
// depend on them, so every one is a named constant here.

export const MIN_SECONDS = 0.8; // three cycles at 4.5 a second: shorter is too short to judge
const TRIM_START = 0.25; // the onset's scoop and settle
const TRIM_END = 0.2; // the release
const GAP_BRIDGED = 0.12; // a reading lost for this long is filled in; longer is a gap
const MIN_VOICED = 0.7; // at least this share of the note heard, or it's unclear
const CENTRE_WINDOW = 0.5; // the moving centre: averages out every wave of 2 a second or faster
const SMOOTH_WINDOW = 0.03; // takes the tracker's jitter off the wave
const HYSTERESIS = 3; // cents either side of the centre before a crossing counts

export const STRAIGHT_FULL = 12; // a wave under ± this is straight
export const STRAIGHT_ONE = 20; // under this still earns a star for straightness
export const DRIFT_MAX = 20; // the centre may wander this much and still be straight
export const VIBRATO_RATE = [4.5, 7.5]; // a natural vibrato's speed
export const VIBRATO_WIDTH = [15, 120]; // and its width
export const WIDE = 80; // over this, described as wide
export const UNEVEN = 0.2; // cycles varying more than this (in length) are uneven: never vibrato
export const WOBBLE_WIDE = 100; // wider than this, slower than WOBBLE_SLOW, is a wobble
const WOBBLE_SLOW = 5;
export const TREMBLE_RATE = 7.5; // faster is a tremble
const TREMBLE_NARROW = { rate: 7, width: 20 }; // or fast and narrow

export const BLOOM_STRAIGHT = 1.0; // a delayed vibrato: straight at least this long (or 40%)
const BLOOM_SHARE = 0.4;
export const BLOOM_SETTLED = 1.5; // and the wave settled this long before the note ends

const mean = (xs) => xs.reduce((a, b) => a + b, 0) / xs.length;
const sd = (xs) => {
    const m = mean(xs);
    return Math.sqrt(mean(xs.map((x) => (x - m) ** 2)));
};
const median = (xs) => {
    const s = [...xs].sort((a, b) => a - b);
    const mid = s.length >> 1;
    return s.length % 2 ? s[mid] : (s[mid - 1] + s[mid]) / 2;
};
const clamp01 = (x) => Math.max(0, Math.min(1, x));

/**
 * A held note's readings from a pitch track (pitchtrack.js trackTake: `{ rate, hz }`), from `from`
 * to `to` seconds, as cents from `midi` (null where unvoiced).
 */
export function centsOf(track, { from, to, midi }) {
    const out = [];
    const first = Math.max(0, Math.ceil(from * track.rate));
    const last = Math.min(track.hz.length - 1, Math.floor(to * track.rate));
    const ref = 440 * 2 ** ((midi - 69) / 12);
    for (let i = first; i <= last; i++) out.push(track.hz[i] > 0 ? 1200 * Math.log2(track.hz[i] / ref) : null);
    return out;
}

/** Short gaps filled in a straight line; returns null when too little was heard. */
function bridged(cents, rate) {
    const voiced = cents.filter((c) => c !== null && Number.isFinite(c)).length;
    if (voiced < cents.length * MIN_VOICED) return null;
    const out = [...cents];
    const maxGap = Math.round(GAP_BRIDGED * rate);
    let i = 0;
    while (i < out.length) {
        if (out[i] !== null) {
            i++;
            continue;
        }
        let j = i;
        while (j < out.length && out[j] === null) j++;
        const before = i > 0 ? out[i - 1] : null;
        const after = j < out.length ? out[j] : null;
        if (j - i > maxGap && before !== null && after !== null) return null; // a real break in the note
        for (let k = i; k < j; k++) out[k] = before === null ? after : after === null ? before : before + ((after - before) * (k - i + 1)) / (j - i + 1);
        i = j;
    }
    return out;
}

/** Each reading folded to the octave of the note's middle (a tracker's octave slip isn't a wave). */
function folded(cents) {
    const centre = median(cents);
    return cents.map((c) => c - 1200 * Math.round((c - centre) / 1200));
}

/** A centred moving average over `seconds` (an odd number of readings, at least three). */
function moving(xs, rate, seconds) {
    const half = Math.max(1, Math.floor((Math.round(seconds * rate) - 1) / 2));
    const out = new Array(xs.length);
    for (let i = 0; i < xs.length; i++) {
        const a = Math.max(0, i - half);
        const b = Math.min(xs.length - 1, i + half);
        let sum = 0;
        for (let k = a; k <= b; k++) sum += xs[k];
        out[i] = sum / (b - a + 1);
    }
    return out;
}

/**
 * The wave's half-cycles: between crossings of the centre (confirmed by a little hysteresis, timed
 * between readings), each with its start (s), length (s) and excursion (± cents, its furthest point
 * from the centre).
 */
function halfCycles(wave, rate) {
    const halves = [];
    let sign = 0;
    let from = null; // the crossing that began this half-cycle, in readings
    let lastZero = null;
    let peak = 0;
    for (let i = 0; i < wave.length; i++) {
        const v = wave[i];
        if (i > 0 && (wave[i - 1] < 0) !== (v < 0)) lastZero = i - 1 + wave[i - 1] / (wave[i - 1] - v);
        const now = v > HYSTERESIS ? 1 : v < -HYSTERESIS ? -1 : 0;
        if (now !== 0 && now !== sign) {
            const crossing = lastZero ?? i;
            if (sign !== 0 && from !== null) halves.push({ at: from / rate, seconds: (crossing - from) / rate, excursion: peak });
            sign = now;
            from = crossing;
            peak = 0;
        }
        if (sign !== 0) peak = Math.max(peak, Math.abs(v));
    }
    return halves;
}

/** How much the jitter smoothing (SMOOTH_WINDOW) shrinks a wave of `hz`: taken back off its width. */
function smoothingGain(hz, rate) {
    const half = Math.max(1, Math.floor((Math.round(SMOOTH_WINDOW * rate) - 1) / 2));
    let sum = 0;
    for (let k = -half; k <= half; k++) sum += Math.cos((2 * Math.PI * hz * k) / rate);
    return sum / (2 * half + 1);
}

/** The line split into its moving centre (over `window` s) and the wave around it, with its half-cycles. */
function split(line, rate, window) {
    const centreLine = moving(line, rate, window);
    const wave = line.map((c, i) => c - centreLine[i]);
    return { centreLine, wave, halves: halfCycles(wave, rate) };
}

/**
 * One held note, measured from its readings (`cents`, about 50 to 100 a second at `rate`):
 * `{ kind, centre, drift, hz, width, evenness, onset, seconds, halves }`, or `{ kind: 'unclear' }`
 * when it's too short or too little of it was heard. `evenness` is how much the cycles' lengths
 * vary (0 is perfectly even); `onset` the seconds (from the note's start) until the wave settled,
 * 0 when it waved from the start, null when it never did.
 */
export function measureHold(cents, rate) {
    const seconds = cents.length / rate;
    const start = Math.round(TRIM_START * rate);
    const end = cents.length - Math.round(TRIM_END * rate);
    if (end - start < MIN_SECONDS * rate) return { kind: 'unclear', seconds };
    const filled = bridged(cents.slice(start, end), rate);
    if (!filled) return { kind: 'unclear', seconds };
    const line = moving(folded(filled), rate, SMOOTH_WINDOW);
    // The centre's window holds a whole number of the wave's cycles, so no part of the wave leaks
    // into the centre (a half-second window would take an eighth off a 5-a-second wave's width).
    const first = split(line, rate, CENTRE_WINDOW);
    const waved = first.halves.filter((h) => h.excursion >= STRAIGHT_FULL);
    const period = waved.length >= 4 ? 2 * mean(waved.map((h) => h.seconds)) : null;
    const { centreLine, wave, halves: found } = period ? split(line, rate, period * Math.max(1, Math.round(CENTRE_WINDOW / period))) : first;
    const centre = median(line);
    const edge = Math.round(CENTRE_WINDOW * rate * 0.5);
    const inner = centreLine.slice(edge, centreLine.length - edge);
    const drift = inner.length ? Math.max(...inner) - Math.min(...inner) : 0;
    const halves = found.map((h) => ({ ...h, at: h.at + TRIM_START }));
    const rms = Math.sqrt(mean(wave.map((w) => w * w)));
    const base = { centre, drift, seconds, halves };
    // Waves that count: half-cycles wide enough to be more than straight.
    const waving = halves.filter((h) => h.excursion >= STRAIGHT_FULL);
    if (waving.length < 4) return { ...base, kind: 'straight', hz: null, width: Math.SQRT2 * rms, evenness: null, onset: null };
    // The settled wave: from the first of the last run of waving half-cycles (the bloom's start),
    // a single weak half-cycle inside it forgiven (a natural wave breathes a little).
    const waves = (h) => h.excursion >= STRAIGHT_FULL;
    let from = halves.length - 1;
    while (from > 0 && !waves(halves[from])) from--;
    while (from > 0 && (waves(halves[from - 1]) || (from > 1 && waves(halves[from - 2])))) from--;
    while (from < halves.length - 1 && !waves(halves[from])) from++;
    const settled = halves.slice(from);
    const counted = settled.length >= 4 ? settled : waving;
    // Full cycles (two half-cycles each, overlapping), so a crossing's timing error isn't doubled.
    const periods = counted.slice(1).map((h, k) => h.seconds + counted[k].seconds);
    const hz = 1 / mean(periods);
    const evenness = sd(periods) / mean(periods);
    // The width from the wave's RMS over the settled stretch (√2 × RMS is a sine's ± height): the
    // peaks fall between readings, so the highest reading always sits a little short.
    const a = Math.round((counted[0].at - TRIM_START) * rate);
    const z = Math.round((counted.at(-1).at - TRIM_START + counted.at(-1).seconds) * rate);
    const span = wave.slice(Math.max(0, a), Math.min(wave.length, z));
    const width = (Math.SQRT2 * Math.sqrt(mean(span.map((w) => w * w)))) / smoothingGain(hz, rate);
    const onset = settled.length >= 4 ? Math.max(0, settled[0].at) : null;
    return { ...base, kind: kindOf({ hz, width, evenness }), hz, width, evenness, onset };
}

/** What a wave of this rate, width and evenness is called. */
export function kindOf({ hz, width, evenness }) {
    if (width < STRAIGHT_FULL) return 'straight';
    if (evenness > UNEVEN) return 'uneven';
    if (hz < VIBRATO_RATE[0] || (width > WOBBLE_WIDE && hz < WOBBLE_SLOW) || width > VIBRATO_WIDTH[1]) return 'wobble';
    if (hz > TREMBLE_RATE || (hz > TREMBLE_NARROW.rate && width < TREMBLE_NARROW.width)) return 'tremble';
    if (hz >= VIBRATO_RATE[0] && width >= VIBRATO_WIDTH[0]) return 'vibrato';
    return 'straight'; // a natural-speed shimmer narrower than a vibrato: near enough straight
}

/** Whether a measured hold is a natural vibrato worth celebrating: even, at a natural rate, sure. */
export function isNaturalVibrato(m) {
    return m?.kind === 'vibrato' && m.evenness <= UNEVEN;
}

// ---- The stars' percent (course.js starsFor: 3 at 90, 2 at 70, 1 for finishing) ----------------

/**
 * A straight tone: how much of the hold stayed straight, judged in half-second stretches (under
 * ±12 counts fully, under ±20 half), less for a wandering centre. `{ percent, straightSeconds }`.
 */
export function straightPercent(cents, rate) {
    const m = measureHold(cents, rate);
    if (m.kind === 'unclear') return { percent: null, straightSeconds: 0, measure: m };
    const stretch = Math.round(0.5 * rate);
    const start = Math.round(TRIM_START * rate);
    const end = cents.length - Math.round(TRIM_END * rate);
    const line = moving(folded(bridged(cents.slice(start, end), rate)), rate, SMOOTH_WINDOW);
    const centreLine = moving(line, rate, CENTRE_WINDOW);
    let credit = 0;
    let straight = 0;
    let stretches = 0;
    for (let i = 0; i + stretch <= line.length; i += stretch) {
        const part = line.slice(i, i + stretch).map((c, k) => c - centreLine[i + k]);
        const halfSwing = (Math.max(...part) - Math.min(...part)) / 2;
        stretches++;
        if (halfSwing < STRAIGHT_FULL) {
            credit += 1;
            straight++;
        } else if (halfSwing < STRAIGHT_ONE) credit += 0.5;
    }
    if (!stretches) return { percent: null, straightSeconds: 0, measure: m };
    const driftCost = m.drift > DRIFT_MAX ? Math.min(0.3, (m.drift - DRIFT_MAX) / 100) : 0;
    const percent = Math.round(100 * Math.max(0, credit / stretches - driftCost));
    // Said of the whole note (its trimmed ends count as the stretches next to them did).
    return { percent, straightSeconds: (straight / stretches) * m.seconds, measure: m };
}

/** How close a steered rate is: within ±0.4 a second up to 3 a second, ±15% above. */
export function rateTolerance(target) {
    return target <= 3 ? 0.4 : target * 0.15;
}

/**
 * A steered wave against its target (`{ hz, cents }`): rate, evenness and staying in the band
 * (no credit for wider: past 1.5 times the band it says "smaller"). `{ percent, tip, measure }`,
 * tip 'smaller', 'slower', 'faster' or null. `generous` (the course's first lesson) lifts a fair try.
 */
export function wavePercent(cents, rate, target, { generous = false } = {}) {
    const m = measureHold(cents, rate);
    if (m.kind === 'unclear') return { percent: null, tip: null, measure: m };
    if (m.hz === null) return { percent: generous ? 40 : 20, tip: 'wave', measure: m };
    const off = Math.abs(m.hz - target.hz);
    const tolerance = rateTolerance(target.hz);
    const rateScore = clamp01(1 - Math.max(0, off - tolerance) / tolerance);
    const evenScore = clamp01(1 - Math.max(0, m.evenness - 0.1) / (UNEVEN * 1.5));
    const ratio = m.width / target.cents;
    const widthScore = ratio > 1.5 ? 0 : ratio >= 0.5 ? 1 : clamp01(ratio / 0.5);
    let score = 0.4 * rateScore + 0.35 * evenScore + 0.25 * widthScore;
    if (generous) score = Math.min(1, 0.35 + score * 0.75);
    const tip = ratio > 1.5 ? 'smaller' : off > tolerance ? (m.hz > target.hz ? 'slower' : 'faster') : null;
    return { percent: Math.round(100 * score), tip, measure: m };
}

/**
 * The delayed vibrato: straight for at least a second (or 40% of the note), then a wave settled at
 * least 1.5 s before the end. A learner with no natural vibrato yet still earns stars for the
 * straight start (up to 75%, two stars), and the wave says "not yet". `{ percent, straightFor,
 * bloomed, measure }`.
 */
export function bloomPercent(cents, rate) {
    const m = measureHold(cents, rate);
    if (m.kind === 'unclear') return { percent: null, straightFor: 0, bloomed: false, measure: m };
    const needStraight = Math.min(BLOOM_STRAIGHT, BLOOM_SHARE * m.seconds);
    const straightFor = m.onset ?? m.seconds;
    const startScore = clamp01(straightFor / needStraight);
    const bloomed = m.onset !== null && m.kind !== 'uneven' && m.seconds - m.onset >= BLOOM_SETTLED && m.hz >= VIBRATO_RATE[0] - 0.5;
    if (!bloomed) return { percent: Math.round(75 * startScore), straightFor, bloomed: false, measure: m };
    return { percent: Math.round(100 * (0.5 * startScore + 0.5)), straightFor, bloomed: true, measure: m };
}

// ---- The trace lane's target (components/wave-lane.js) -------------------------------------------

/** How wide the ribbon is either side of the target: what counts as riding it. */
export function bandOf(segment) {
    if (segment.kind === 'wave') return Math.max(14, Math.round(segment.cents * 0.4 + 6));
    if (segment.kind === 'path') return segment.band ?? 35; // a contour: within about a third of a semitone
    return segment.kind === 'straight' ? STRAIGHT_FULL : 25;
}

/**
 * A wave step's target in seconds: `exercise.target` is a list of `{ seconds, kind, hz?, cents?,
 * fade?, straight? }` (kind 'wave' a steered wave, 'straight' a flat line, 'bloom' straight for
 * `straight` s then free, 'free' a natural hold), sung after `lead` beats of the drone. The steered
 * wave's phase runs on across segments, so a ladder is one unbroken wave.
 */
export function targetPlan(exercise) {
    const beat = 60 / (exercise.tempo ?? 60);
    let at = (exercise.lead ?? 2) * beat;
    let phase = 0;
    const segments = exercise.target.map((s) => {
        const seg = { ...s, from: at, to: at + s.seconds, phase, band: bandOf(s) };
        if (s.kind === 'wave') phase += s.hz * s.seconds;
        at += s.seconds;
        return seg;
    });
    return { segments, start: segments[0].from, end: at, beatSeconds: beat };
}

/**
 * The target at `t` seconds: `{ cents, band, kind, fade }` (`fade` 0 to 1, the ghost wave fading
 * out), or null outside the sung part. A wave rises first (a sine from its centre).
 */
export function targetAt(plan, t) {
    const seg = plan.segments.find((s) => t >= s.from && t < s.to);
    if (!seg) return null;
    let fade = 1;
    if (seg.fade) fade = clamp01(1 - (t - seg.from) / seg.fade);
    if (seg.kind === 'path') return { cents: pathCents(seg, t - seg.from), band: seg.band, kind: 'path', fade };
    if (seg.kind !== 'wave') return { cents: 0, band: seg.band, kind: seg.kind, fade };
    const phase = seg.phase + seg.hz * (t - seg.from);
    return { cents: seg.cents * Math.sin(2 * Math.PI * phase), band: seg.band, kind: 'wave', fade };
}

/**
 * A contour's height (cents from the note) at `t` seconds into its segment: `points` are
 * `[seconds, steps]` (steps in semitones from the note), eased as the guide sings them
 * (synth.js bendAt), a jump where two points share a time (a yodel's flip).
 */
export function pathCents(segment, t) {
    return bendAt(segment.points.map(([s, steps]) => [s, steps * 100]), t);
}

/**
 * A contour followed: how much of the time it was heard it rode inside the ribbon (`{ percent,
 * inside, measure }`), each reading folded to the octave nearest the target. Too little heard is
 * unclear (percent null).
 */
export function pathPercent(cents, rate, segment) {
    const heard = cents.map((c, i) => ({ c, t: i / rate })).filter((r) => r.c !== null && Number.isFinite(r.c));
    if (heard.length < cents.length * 0.6 || heard.length < MIN_SECONDS * rate) return { percent: null, inside: 0, measure: { kind: 'unclear', seconds: cents.length / rate } };
    const band = segment.band ?? bandOf(segment);
    const ins = heard.filter(({ c, t }) => {
        const want = pathCents(segment, t);
        const folded = c - 1200 * Math.round((c - want) / 1200);
        return Math.abs(folded - want) <= band;
    }).length;
    const inside = ins / heard.length;
    return { percent: Math.round(100 * clamp01(inside / 0.9)), inside, measure: { kind: 'path', seconds: cents.length / rate, inside } };
}

/** The steered wave's peaks and troughs (the gems that pop): `[{ t, cents }]`, faded ones left out. */
export function peaksOf(plan) {
    const out = [];
    for (const seg of plan.segments) {
        if (seg.kind !== 'wave') continue;
        // Peaks fall where the phase is a quarter or three quarters through a cycle.
        const first = Math.ceil((seg.phase - 0.25) * 2) / 2 + 0.25;
        for (let p = first; p < seg.phase + seg.hz * seg.seconds; p += 0.5) {
            const t = seg.from + (p - seg.phase) / seg.hz;
            if (seg.fade && t - seg.from >= seg.fade) break;
            out.push({ t, cents: seg.cents * Math.sin(2 * Math.PI * p) });
        }
    }
    return out;
}

/**
 * A natural hold ('free', unit 3): a steady centre and the note held, whatever the wave; a
 * tremble or an uneven shake caps it at two stars. The wave is described, never demanded.
 */
export function freePercent(cents, rate) {
    const m = measureHold(cents, rate);
    if (m.kind === 'unclear') return { percent: null, measure: m };
    const heard = cents.filter((c) => c !== null).length / cents.length;
    const steady = clamp01(1 - Math.max(0, m.drift - DRIFT_MAX) / 40);
    let percent = Math.round(100 * (0.55 * clamp01(heard / 0.9) + 0.45 * steady));
    if (m.kind === 'tremble' || m.kind === 'uneven') percent = Math.min(percent, 75);
    return { percent, measure: m };
}

/**
 * A whole wave step's take, scored segment by segment on its own kind (`track` from pitchtrack.js,
 * `midi` the note sung), weighted by length. A centre far off the note (past `tolerance` cents)
 * costs, however well it waved. `{ percent, tip, parts: [{ kind, percent, measure }], measure }`
 * (`measure` the last held segment's, for the words), percent null when nothing was heard.
 */
export function scoreWave(track, plan, { midi, tolerance = 50, generous = false } = {}) {
    const parts = plan.segments.map((seg) => {
        const cents = centsOf(track, { from: seg.from, to: seg.to, midi });
        const got = seg.kind === 'path' ? pathPercent(cents, track.rate, seg)
            : seg.kind === 'wave' ? wavePercent(cents, track.rate, seg, { generous })
            : seg.kind === 'straight' ? straightPercent(cents, track.rate)
                : seg.kind === 'bloom' ? bloomPercent(cents, track.rate) : freePercent(cents, track.rate);
        const m = got.measure;
        let percent = got.percent;
        const centre = m?.centre ?? null;
        // (A contour moves on purpose: its own measure already checks the pitch the whole way.)
        if (seg.kind !== 'path' && percent !== null && centre !== null && Math.abs(centre - 1200 * Math.round(centre / 1200)) > tolerance) percent = Math.round(percent * 0.6);
        return { kind: seg.kind, seconds: seg.seconds, percent, tip: got.tip ?? null, measure: m, bloomed: got.bloomed ?? null, straightSeconds: got.straightSeconds ?? null, inside: got.inside ?? null };
    });
    // A segment marked `scored: false` (a rung to try, such as the ladder's fastest) is shown, never starred.
    const heard = parts.filter((p, i) => p.percent !== null && plan.segments[i].scored !== false);
    if (!heard.length) return { percent: null, tip: null, parts, measure: null };
    const total = heard.reduce((sum, p) => sum + p.seconds, 0);
    const percent = Math.round(heard.reduce((sum, p) => sum + p.percent * p.seconds, 0) / total);
    const weakest = [...heard].sort((a, b) => a.percent - b.percent)[0];
    return { percent, tip: weakest.tip, parts, measure: heard.at(-1).measure };
}
