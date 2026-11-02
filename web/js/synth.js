// The guide voice: renders a part's notes to audio, an "oo" vowel, deterministically and in plain
// JS (no AudioContext), so tests can check every note's pitch and timing. Runs in a Web Worker
// in the app (synth-worker.js).

const TABLE = 2048;
const FORMANTS = [
    { hz: 300, width: 90, gain: 1 },
    { hz: 870, width: 140, gain: 0.35 },
];
const ATTACK = 0.04;
const RELEASE = 0.07;
const VIBRATO_DELAY = 0.3;
const VIBRATO_HZ = 5.5;
const VIBRATO_DEPTH = 0.005; // about 9 cents
const TARGET_RMS = 0.1; // about -20 dBFS, a typical take's loudness

export function midiToHz(midi) {
    return 440 * 2 ** ((midi - 69) / 12);
}

/** Seconds from the start of the song to `beat`, at `bpm` played at `speed`. */
export function beatToSeconds(beat, bpm, speed = 1) {
    return (beat * 60) / (bpm * speed);
}

/** One period of the vowel at `hz`: harmonics weighted by the "oo" formants (moved by `shift`, a vowel's colour). */
function wavetable(hz, shift = 1) {
    const table = new Float32Array(TABLE + 1);
    const harmonics = Math.max(1, Math.min(14, Math.floor(4500 / hz)));
    let peak = 0;
    for (let k = 1; k <= harmonics; k++) {
        const f = k * hz;
        let weight = 0.08 / k; // a little of everything, so high notes still sound voiced
        for (const formant of FORMANTS) {
            weight += formant.gain * Math.exp(-(((f - formant.hz * shift) / formant.width) ** 2));
        }
        for (let i = 0; i <= TABLE; i++) {
            table[i] += weight * Math.sin((2 * Math.PI * k * i) / TABLE);
        }
    }
    for (const v of table) peak = Math.max(peak, Math.abs(v));
    for (let i = 0; i <= TABLE; i++) table[i] /= peak || 1;
    return table;
}

/**
 * A note's own vibrato (the Vibrato course, docs/plus-courses-build-plan.md): `{ hz, cents, after,
 * jitter }`, any left out as the guide's usual (5.5 a second, about 9 cents, after 0.3 s, even).
 * `cents: 0` sings straight; `jitter` (0 to 1) varies each cycle's speed and size a little, from a
 * fixed seed, so a render is always the same. Without one, the note sounds exactly as it always has.
 */
function vibratoOf(note) {
    const v = note.vibrato;
    if (!v) return null;
    return { hz: v.hz ?? VIBRATO_HZ, depth: v.cents === undefined ? VIBRATO_DEPTH : 2 ** (v.cents / 1200) - 1, after: v.after ?? VIBRATO_DELAY, jitter: Math.max(0, Math.min(1, v.jitter ?? 0)) };
}

const BEND_LIMIT = 2400; // cents either way: a typo in a course can't make an ultrasonic note

/**
 * A note's pitch path at `t` seconds from its start (`bend: [[seconds, cents], ...]`, the course's
 * sirens, scoops, fall-offs and flips): eased between points, a sudden jump where two points share a
 * time, held before the first and after the last, kept within two octaves either way.
 */
export function bendAt(points, t) {
    if (!points?.length) return 0;
    let cents = points[0][1];
    if (t >= points[points.length - 1][0]) cents = points[points.length - 1][1];
    else {
        for (let i = 1; i < points.length; i++) {
            const [t0, c0] = points[i - 1];
            const [t1, c1] = points[i];
            if (t < t1) {
                if (t >= t0) {
                    const x = t1 > t0 ? (t - t0) / (t1 - t0) : 1;
                    cents = c0 + (c1 - c0) * x * x * (3 - 2 * x); // smoothstep: a voice eases in and out of a glide
                }
                break;
            }
            cents = c1;
        }
    }
    return Math.max(-BEND_LIMIT, Math.min(BEND_LIMIT, cents));
}

/** A head-voice tone (`voice: 'head'`): a purer, lighter colour than the "oo", few soft upper harmonics. */
function headWavetable() {
    const weights = [1, 0.22, 0.07, 0.025];
    const table = new Float32Array(TABLE + 1);
    let peak = 0;
    for (let i = 0; i <= TABLE; i++) {
        table[i] = weights.reduce((sum, w, k) => sum + w * Math.sin((2 * Math.PI * (k + 1) * i) / TABLE), 0);
        peak = Math.max(peak, Math.abs(table[i]));
    }
    for (let i = 0; i <= TABLE; i++) table[i] /= peak || 1;
    return table;
}

/**
 * Renders `notes` ({ beat, length, pitch, vibrato?, bend?, voice? }, MIDI pitch, one at a time) from beat 0.
 * `transpose` is in semitones; `speed` below 1 slows the part without changing its pitch. `human`
 * ('full' or 'guide') sings it as a person would (renderHuman); without it, exactly as written.
 */
export function renderPart(notes, { bpm, sampleRate, transpose = 0, speed = 1, human = null, seed = null }) {
    if (!notes.length) {
        return new Float32Array(0);
    }
    if (HUMAN[human]) return renderHuman(notes, { bpm, sampleRate, transpose, speed, style: HUMAN[human], seed });
    const last = notes.reduce((end, n) => Math.max(end, n.beat + n.length), 0);
    const out = new Float32Array(Math.ceil((beatToSeconds(last, bpm, speed) + RELEASE) * sampleRate));
    const tables = new Map();
    for (const note of notes) {
        const pitch = note.pitch + transpose;
        const head = note.voice === 'head';
        const key = head ? `${pitch}:head` : pitch;
        if (!tables.has(key)) tables.set(key, head ? headWavetable() : wavetable(midiToHz(pitch)));
        const table = tables.get(key);
        const bend = note.bend?.length ? note.bend : null;
        const hz = midiToHz(pitch);
        const start = Math.round(beatToSeconds(note.beat, bpm, speed) * sampleRate);
        const held = beatToSeconds(note.length, bpm, speed);
        const frames = Math.round((held + RELEASE) * sampleRate);
        let phase = 0;
        const own = vibratoOf(note);
        // A note's own vibrato runs on its own phase, a cycle at a time (so each cycle can vary).
        let seed = 7 + Math.round(note.beat * 16);
        const random = () => ((seed = (seed * 16807) % 2147483647) / 2147483647) - 0.5;
        let wave = 0;
        let cycleHz = own?.hz ?? 0;
        let cycleDepth = own?.depth ?? 0;
        for (let i = 0; i < frames && start + i < out.length; i++) {
            const t = i / sampleRate;
            const attack = Math.min(1, t / ATTACK);
            const release = t < held ? 1 : Math.max(0, 1 - (t - held) / RELEASE);
            let vibrato;
            if (own) {
                const swell = Math.min(1, Math.max(0, (t - own.after) / 0.2));
                const before = wave;
                wave += cycleHz / sampleRate;
                if (own.jitter && Math.floor(wave) !== Math.floor(before)) {
                    cycleHz = own.hz * (1 + own.jitter * 0.8 * random());
                    cycleDepth = own.depth * (1 + own.jitter * 0.8 * random());
                }
                vibrato = 1 + cycleDepth * swell * Math.sin(2 * Math.PI * wave);
            } else {
                const swell = Math.min(1, Math.max(0, (t - VIBRATO_DELAY) / 0.2));
                vibrato = 1 + VIBRATO_DEPTH * swell * Math.sin(2 * Math.PI * VIBRATO_HZ * t);
            }
            const at = phase * TABLE;
            const index = at | 0;
            const sample = table[index] + (table[index + 1] - table[index]) * (at - index);
            out[start + i] += sample * attack * release;
            phase += (bend ? hz * vibrato * 2 ** (bendAt(bend, t) / 1200) : hz * vibrato) / sampleRate;
            phase -= Math.floor(phase);
        }
    }
    return level(out);
}

// ---- A human voice (Hillel: "the singing is very unhuman, it's too perfect") -------------------

/**
 * How a person sings, in two measures. `full` for the demos people listen to (the landing, the song
 * pages); `guide` for the app's guide voice, which teaches pitch: the same warmth, but the held part
 * of every note stays within about 5 cents of it (tests/synth.test.js). Cents unless said.
 */
export const HUMAN = {
    full: { scoopShare: 0.45, scoop: [35, 85], overshoot: 9, glideWithin: 4, drift: 9, vibrato: [20, 32], vibratoAfter: [0.22, 0.4], vibratoFrom: 0.4, wander: 0.45, jitter: 2.5, shimmer: 0.05, loudness: 1.6, swell: 0.22, onset: 0.012, breath: 0.06, aspiration: 0.012, vowel: 0.12, tail: 0.32 },
    guide: { scoopShare: 0.3, scoop: [25, 45], overshoot: 3, glideWithin: 4, drift: 2, vibrato: [9, 13], vibratoAfter: [0.3, 0.45], vibratoFrom: 0.5, wander: 0.3, jitter: 0.8, shimmer: 0.025, loudness: 0.8, swell: 0.15, onset: 0.006, breath: 0.035, aspiration: 0.008, vowel: 0.05, tail: 0 },
};

/** A seeded random source, 0 to 1 (mulberry32): the same seed, the same voice, every render. */
export function seeded(seed) {
    let a = seed >>> 0;
    return () => {
        a = (a + 0x6d2b79f5) >>> 0;
        let t = Math.imul(a ^ (a >>> 15), 1 | a);
        t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
        return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
}

/** Low-passed noise around zero (about -1 to 1), changing about `hz` times a second. */
function wobble(random, hz, sampleRate) {
    const k = 1 - Math.exp((-2 * Math.PI * hz) / sampleRate);
    let y = 0;
    let z = 0;
    return () => {
        y += k * ((random() * 2 - 1) - y);
        z += k * (y - z);
        return z * 4;
    };
}

const ease = (x) => (x <= 0 ? 0 : x >= 1 ? 1 : x * x * (3 - 2 * x));

/**
 * The human voice: each note scoops into its pitch from below now and then (with a little overshoot
 * that settles), glides from a close note before it, drifts a few cents, and on a long note grows a
 * vibrato that starts late, wanders around 5.5 a second and swells then eases. Loudness swells and
 * fades across each phrase, varies a little note to note and shimmers; onsets wander a few ms. A soft
 * breath before each phrase, aspiration under the tone, and the vowel's colour moves a little.
 * Seeded, so a render is always the same. A note with its own vibrato or bend (a course's) keeps
 * exactly the pitch it asks for.
 */
function renderHuman(notes, { bpm, sampleRate, transpose, speed, style, seed }) {
    const random = seeded(seed ?? notes.reduce((sum, n, i) => sum + (n.pitch + 1) * (i + 3) * (n.beat * 7 + 1), 17));
    const between = (lo, hi) => lo + (hi - lo) * random();
    const last = notes.reduce((end, n) => Math.max(end, n.beat + n.length), 0);
    const out = new Float32Array(Math.ceil((beatToSeconds(last, bpm, speed) + RELEASE + (style.tail ? 0.8 : 0.1)) * sampleRate)); // room for the last phrase's tail
    const timed = notes.map((n) => ({ ...n, from: beatToSeconds(n.beat, bpm, speed), held: beatToSeconds(n.length, bpm, speed) }));
    // Phrases: a new one after a rest of a quarter second or more.
    let phrase = 0;
    timed.forEach((n, i) => {
        if (i > 0 && n.from - (timed[i - 1].from + timed[i - 1].held) >= 0.25) phrase++;
        n.phrase = phrase;
    });
    const spans = new Map();
    for (const n of timed) spans.set(n.phrase, [Math.min(spans.get(n.phrase)?.[0] ?? Infinity, n.from), Math.max(spans.get(n.phrase)?.[1] ?? 0, n.from + n.held)]);
    const tables = new Map();
    const air = airTable(random, sampleRate);
    const slow = (hz) => wobble(random, hz, sampleRate / CONTROL); // control-rate wobbles
    for (const [i, note] of timed.entries()) {
        const pitch = note.pitch + transpose;
        const own = note.vibrato || note.bend?.length; // a course's own shape: sung exactly
        const shift = 1 + style.vowel * [-1, 0, 0.8][Math.floor(random() * 3)];
        const key = `${pitch}:${shift.toFixed(3)}`;
        if (!tables.has(key)) tables.set(key, note.voice === 'head' ? headWavetable() : wavetable(midiToHz(pitch), shift));
        const table = tables.get(key);
        const hz = midiToHz(pitch);
        const before = timed[i - 1];
        const legato = before && note.from - (before.from + before.held) < 0.04 && Math.abs(before.pitch - note.pitch) <= style.glideWithin;
        const phraseStart = !before || before.phrase !== note.phrase;
        const scoop = !own && !legato && (phraseStart || random() < style.scoopShare) ? between(...style.scoop) : 0;
        const scoopTime = between(0.08, 0.13);
        const glideFrom = legato && !own ? (before.pitch - note.pitch) * 100 : 0;
        const glideTime = between(0.05, 0.09);
        const driftA = [between(0.25, 0.5), random() * 6.28];
        const driftB = [between(0.6, 0.9), random() * 6.28];
        const vibrato = !own && note.held >= style.vibratoFrom;
        const vibratoAfter = between(...style.vibratoAfter);
        const vibratoDepth = between(...style.vibrato);
        const rate = between(5.1, 5.8);
        const rateWander = [between(0.5, 0.9), random() * 6.28];
        const depthWander = [between(0.9, 1.4), random() * 6.28];
        const jitter = slow(18);
        const shimmer = slow(12);
        const [phraseFrom, phraseTo] = spans.get(note.phrase);
        const loud = 10 ** ((between(-1, 1) * style.loudness) / 20);
        const attack = between(0.05, 0.08);
        const release = between(0.08, 0.12);
        const start = Math.max(0, Math.round((note.from + between(-1, 1) * style.onset) * sampleRate));
        let airAt = Math.floor(random() * air.length);
        // A soft breath just before a phrase.
        if (phraseStart && style.breath) {
            const length = Math.round(between(0.16, 0.26) * sampleRate);
            const from = start - length - Math.round(0.03 * sampleRate);
            for (let k = 0; k < length; k++) {
                if (from + k >= 0) out[from + k] += air[(airAt + k) % air.length] * style.breath * Math.sin((Math.PI * k) / length) ** 2;
            }
            airAt += length;
        }
        const frames = Math.round((note.held + release) * sampleRate);
        let phase = random();
        let wave = random();
        // The pitch and the loudness move at the control rate, CONTROL samples at a time, in straight
        // lines between (a person's voice changes far slower than that): the tone itself is per sample.
        const control = (t) => {
            let cents = 0;
            if (!own) {
                if (glideFrom) cents += glideFrom * (1 - ease(t / glideTime));
                else if (scoop) cents += -scoop * (1 - ease(t / scoopTime)) + style.overshoot * Math.sin(Math.PI * Math.min(1, Math.max(0, (t - 0.6 * scoopTime) / (1.4 * scoopTime))));
                cents += style.drift * (0.6 * Math.sin(2 * Math.PI * driftA[0] * t + driftA[1]) + 0.4 * Math.sin(2 * Math.PI * driftB[0] * t + driftB[1]));
                cents += style.jitter * jitter();
                if (vibrato && t > vibratoAfter) {
                    const grown = ease((t - vibratoAfter) / 0.4) * (1 - 0.25 * ease((t - vibratoAfter - 0.8) / Math.max(0.5, note.held)));
                    const depth = vibratoDepth * grown * (1 + 0.15 * Math.sin(2 * Math.PI * depthWander[0] * t + depthWander[1]));
                    wave += ((rate + style.wander * Math.sin(2 * Math.PI * rateWander[0] * t + rateWander[1])) * CONTROL) / sampleRate;
                    cents += depth * Math.sin(2 * Math.PI * wave);
                }
            } else if (note.bend?.length) {
                cents = bendAt(note.bend, t);
            } else {
                const v = vibratoOf(note);
                cents = 1200 * Math.log2(1 + v.depth * Math.min(1, Math.max(0, (t - v.after) / 0.2)) * Math.sin(2 * Math.PI * v.hz * t));
            }
            const into = Math.min(1, t / attack);
            const fade = t < note.held ? 1 : Math.max(0, 1 - (t - note.held) / release);
            const x = (note.from + t - phraseFrom) / Math.max(0.2, phraseTo - phraseFrom);
            const swell = 1 - style.swell + style.swell * Math.sin(Math.PI * Math.min(1, Math.max(0, 0.15 + 0.85 * x)));
            const amp = into * into * (3 - 2 * into) * fade * loud * swell * (1 + style.shimmer * shimmer());
            // Aspiration: air under the tone, a little more as each note begins.
            return [(hz * 2 ** (cents / 1200)) / sampleRate, amp, style.aspiration * (1 + 4 * Math.max(0, 1 - t / 0.06))];
        };
        let [inc, amp, breathy] = control(0);
        for (let s0 = 0; s0 < frames && start + s0 < out.length; s0 += CONTROL) {
            const [inc2, amp2, breathy2] = control((s0 + CONTROL) / sampleRate);
            const n = Math.min(CONTROL, frames - s0, out.length - start - s0);
            for (let s = 0; s < n; s++) {
                const f = s / CONTROL;
                const at = phase * TABLE;
                const index = at | 0;
                const tone = table[index] + (table[index + 1] - table[index]) * (at - index);
                const a = amp + (amp2 - amp) * f;
                out[start + s0 + s] += a * (tone + (breathy + (breathy2 - breathy) * f) * air[airAt++ % air.length]);
                phase += inc + (inc2 - inc) * f;
                phase -= Math.floor(phase);
            }
            inc = inc2;
            amp = amp2;
            breathy = breathy2;
        }
        // The room answers at a phrase's end, not all along (Hillel: "more at the end of phrases than always").
        const lastOfPhrase = !timed[i + 1] || timed[i + 1].phrase !== note.phrase;
        if (lastOfPhrase && style.tail) bloom(out, start + Math.round(note.held * sampleRate), sampleRate, style.tail, random);
    }
    return level(out);
}

/**
 * A small room's tail after `end` (the phrase's last note letting go): the last 0.3 s of the voice,
 * darkened, echoed a few times as it fades over about half a second. Only at phrase ends, so the
 * notes themselves stay close and dry.
 */
export function bloom(out, end, sampleRate, amount, random) {
    const take = Math.round(0.3 * sampleRate);
    const from = Math.max(0, end - take);
    const source = out.slice(from, end + Math.round(0.12 * sampleRate));
    let low = 0;
    const lp = 1 - Math.exp((-2 * Math.PI * 2200) / sampleRate);
    for (let i = 0; i < source.length; i++) source[i] = low += lp * (source[i] - low);
    const taps = [[0.047, 0.55], [0.083, 0.42], [0.131, 0.3], [0.19, 0.2], [0.27, 0.12], [0.37, 0.07]];
    for (const [delay, gain] of taps) {
        const at = from + Math.round((delay + 0.01 * random()) * sampleRate);
        for (let i = 0; i < source.length && at + i < out.length; i++) {
            const fade = i < 240 ? i / 240 : 1;
            out[at + i] += source[i] * gain * amount * fade * (1 - i / source.length) ** 0.5;
        }
    }
}

const CONTROL = 32; // samples between the human voice's pitch and loudness steps (about 1.5 ms at 48 kHz)

/** A second of breathy air (noise between about 1 and 4 kHz), read from a different place each note. */
function airTable(random, sampleRate) {
    const table = new Float32Array(sampleRate);
    const hp = Math.exp((-2 * Math.PI * 1000) / sampleRate);
    const lp = 1 - Math.exp((-2 * Math.PI * 4000) / sampleRate);
    let x0 = 0;
    let high = 0;
    let low = 0;
    for (let i = 0; i < table.length; i++) {
        const x = random() * 2 - 1;
        high = hp * (high + x - x0);
        x0 = x;
        low += lp * (high - low);
        table[i] = low * 1.6;
    }
    return table;
}

const BACKING_RMS = 0.05; // under the guides: something to lean on, not to follow
const QUALITY = {
    maj: [0, 4, 7], min: [0, 3, 7], dim: [0, 3, 6], aug: [0, 4, 8], sus2: [0, 2, 7], sus4: [0, 5, 7],
    maj7: [0, 4, 7, 11], min7: [0, 3, 7, 10], dom7: [0, 4, 7, 10], dim7: [0, 3, 6, 9], hdim7: [0, 3, 6, 10],
    maj6: [0, 4, 7, 9], min6: [0, 3, 7, 9], add9: [0, 4, 7, 14],
};

/** A chord's notes: the bass (root, or `bass`) in octave 2, the rest close above C3. */
export function voicing(chord, transpose = 0) {
    const root = (((chord.root + transpose) % 12) + 12) % 12;
    const bass = (((chord.bass ?? chord.root) + transpose) % 12 + 12) % 12;
    const upper = (QUALITY[chord.quality] ?? QUALITY.maj).map((step) => 48 + ((root + step) % 12) + (step >= 12 ? 12 : 0));
    return [36 + bass, ...upper.sort((a, b) => a - b)];
}

/**
 * The chords underneath (docs/more-features-plan.md, feature 3): `chords` ({ beat, root, quality,
 * bass }, each until the next) to `endBeat`, as a soft sustained pad or a struck piano. Quieter than
 * a guide; at `speed`, slower, at the same pitch.
 */
export function renderBacking(chords, { bpm, sampleRate, endBeat, transpose = 0, speed = 1, voice = 'pad' }) {
    if (!chords?.length) return new Float32Array(0);
    const total = Math.ceil((beatToSeconds(endBeat, bpm, speed) + 0.6) * sampleRate);
    const out = new Float32Array(total);
    const piano = voice === 'piano';
    chords.forEach((chord, i) => {
        const from = beatToSeconds(chord.beat, bpm, speed);
        const to = beatToSeconds(chords[i + 1]?.beat ?? endBeat, bpm, speed);
        const start = Math.round(from * sampleRate);
        const held = to - from;
        const tail = piano ? 0.4 : 0.25;
        const frames = Math.min(total - start, Math.round((held + tail) * sampleRate));
        voicing(chord, transpose).forEach((pitch, n) => {
            const hz = midiToHz(pitch);
            const weight = n === 0 ? 0.9 : 0.6;
            for (let k = 0; k < frames; k++) {
                const t = k / sampleRate;
                const fade = t < held ? 1 : Math.max(0, 1 - (t - held) / tail);
                const envelope = piano ? Math.exp(-t * 1.6) * Math.min(1, t / 0.005) : Math.min(1, t / 0.18) * fade;
                const phase = 2 * Math.PI * hz * t;
                // A few soft harmonics; the pad slightly detuned against itself, for warmth.
                const tone = piano
                    ? Math.sin(phase) + 0.35 * Math.sin(2 * phase) * Math.exp(-t * 2) + 0.12 * Math.sin(3 * phase) * Math.exp(-t * 3)
                    : 0.5 * (Math.sin(phase) + Math.sin(phase * 1.003)) + 0.15 * Math.sin(2 * phase);
                out[start + k] += tone * envelope * fade * weight;
            }
        });
    });
    return level(out, BACKING_RMS);
}

/** Scales the voiced part of `samples` to the target loudness. */
function level(samples, target = TARGET_RMS) {
    let sum = 0;
    let voiced = 0;
    for (const v of samples) {
        if (Math.abs(v) > 1e-4) {
            sum += v * v;
            voiced++;
        }
    }
    const rms = voiced ? Math.sqrt(sum / voiced) : 0;
    if (rms > 0) {
        const gain = target / rms;
        for (let i = 0; i < samples.length; i++) samples[i] *= gain;
    }
    return samples;
}
