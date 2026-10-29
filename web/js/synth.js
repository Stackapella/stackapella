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

/** One period of the vowel at `hz`: harmonics weighted by the "oo" formants. */
function wavetable(hz) {
    const table = new Float32Array(TABLE + 1);
    const harmonics = Math.max(1, Math.min(14, Math.floor(4500 / hz)));
    let peak = 0;
    for (let k = 1; k <= harmonics; k++) {
        const f = k * hz;
        let weight = 0.08 / k; // a little of everything, so high notes still sound voiced
        for (const formant of FORMANTS) {
            weight += formant.gain * Math.exp(-(((f - formant.hz) / formant.width) ** 2));
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
 * `transpose` is in semitones; `speed` below 1 slows the part without changing its pitch.
 */
export function renderPart(notes, { bpm, sampleRate, transpose = 0, speed = 1 }) {
    if (!notes.length) {
        return new Float32Array(0);
    }
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
