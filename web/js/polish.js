// Make it sound good (docs/more-features-plan.md, feature 1): on the way out only (export, and the
// studio's Hear it polished), never on a take, a timing check, the recorder's input or a score.
// Each part is brought to the same loudness (the melody a little forward), gently compressed (in
// code, so nothing moves in time),
// spread a little by voice and put in a room made in code; the mix is then set to -14 LUFS with
// nothing above -1 dBFS. Everything is deterministic: the room's noise is seeded, and the room and
// the compression are worked out in code rather than by the browser's nodes.

import { fft } from './dsp.js';

export const ROOMS = {
    dry: null,
    small: { decay: 0.7, bright: 0.55, wet: 0.16, predelay: 0.008 },
    hall: { decay: 2.1, bright: 0.42, wet: 0.24, predelay: 0.022 },
    chapel: { decay: 3.4, bright: 0.33, wet: 0.3, predelay: 0.034 },
};
export const DEFAULT_ROOM = 'small';
export const TARGET_LUFS = -14;
export const CEILING_DB = -1;
const PART_LUFS = -24; // each part's level before the mix (room for several to add up)
const MELODY_DB = 1.5;
const UNDER_DB = 8; // the chords underneath sit well below the voices
const MOST_GAIN_DB = 18;

// ---- Loudness (ITU-R BS.1770-4: K-weighting, 400 ms blocks, absolute and relative gates) --------

/**
 * The two K-weighting biquads at `sampleRate` (a high shelf, then a high pass), designed as the
 * standard's own reference does, so at 48 kHz they are its published coefficients exactly.
 */
function kFilters(sampleRate) {
    const shelf = (() => {
        const q = 0.7071752369554196;
        const k = Math.tan((Math.PI * 1681.974450955533) / sampleRate);
        const vh = 10 ** (3.999843853973347 / 20);
        const vb = vh ** 0.4996667741545416;
        const a0 = 1 + k / q + k * k;
        return { b: [(vh + (vb * k) / q + k * k) / a0, (2 * (k * k - vh)) / a0, (vh - (vb * k) / q + k * k) / a0], a: [(2 * (k * k - 1)) / a0, (1 - k / q + k * k) / a0] };
    })();
    const highPass = (() => {
        const q = 0.5003270373238773;
        const k = Math.tan((Math.PI * 38.13547087602444) / sampleRate);
        const a0 = 1 + k / q + k * k;
        return { b: [1, -2, 1], a: [(2 * (k * k - 1)) / a0, (1 - k / q + k * k) / a0] };
    })();
    return [shelf, highPass];
}

function biquad(input, { b, a }) {
    const out = new Float32Array(input.length);
    let x1 = 0;
    let x2 = 0;
    let y1 = 0;
    let y2 = 0;
    for (let i = 0; i < input.length; i++) {
        const x = input[i];
        const y = b[0] * x + b[1] * x1 + b[2] * x2 - a[0] * y1 - a[1] * y2;
        out[i] = y;
        x2 = x1;
        x1 = x;
        y2 = y1;
        y1 = y;
    }
    return out;
}

/** Integrated loudness of `channels` (one for a part, two for a mix), in LUFS; -Infinity for silence. */
export function loudness(channels, sampleRate) {
    const filters = kFilters(sampleRate);
    const weighted = channels.map((c) => filters.reduce((signal, f) => biquad(signal, f), c));
    const block = Math.round(0.4 * sampleRate);
    const hop = Math.round(0.1 * sampleRate);
    const length = channels[0].length;
    const powers = [];
    for (let start = 0; start + block <= length; start += hop) {
        let sum = 0;
        for (const w of weighted) {
            let s = 0;
            for (let i = start; i < start + block; i++) s += w[i] * w[i];
            sum += s / block;
        }
        powers.push(sum);
    }
    const lufs = (power) => -0.691 + 10 * Math.log10(power);
    const loud = powers.filter((p) => lufs(p) > -70);
    if (!loud.length) return -Infinity;
    const mean = (list) => list.reduce((s, p) => s + p, 0) / list.length;
    const relative = lufs(mean(loud)) - 10;
    const gated = loud.filter((p) => lufs(p) > relative);
    return lufs(mean(gated));
}

// ---- The parts ------------------------------------------------------------------------------------

/**
 * Each voice's gain (linear) to bring it to the same loudness, the melody 1.5 dB forward and the
 * chords (`under`) 8 dB back. A silent voice keeps 1. `voices`: [{ samples, sampleRate, melody, under }].
 */
export function levelGains(voices) {
    return voices.map((v) => {
        const level = loudness([v.samples], v.sampleRate);
        if (!Number.isFinite(level)) return 1;
        const db = Math.max(-MOST_GAIN_DB, Math.min(MOST_GAIN_DB, PART_LUFS + (v.melody ? MELODY_DB : 0) - (v.under ? UNDER_DB : 0) - level));
        return 10 ** (db / 20);
    });
}

/**
 * Gentle compression, with no delay (the browser's compressor looks ahead a few milliseconds, which
 * would move the singing): a level follower in dB, a soft knee, the gain applied to each sample.
 */
export function compress(samples, sampleRate, { threshold = -26, ratio = 2.5, knee = 8, attack = 0.012, release = 0.25 } = {}) {
    const out = new Float32Array(samples.length);
    const rise = Math.exp(-1 / (attack * sampleRate));
    const fall = Math.exp(-1 / (release * sampleRate));
    const slope = 1 / ratio - 1;
    let level = -120;
    for (let i = 0; i < samples.length; i++) {
        const db = 20 * Math.log10(Math.abs(samples[i]) + 1e-9);
        level = db > level ? rise * level + (1 - rise) * db : fall * level + (1 - fall) * db;
        const over = level - threshold;
        const cut = over <= -knee / 2 ? 0 : over < knee / 2 ? (slope * (over + knee / 2) ** 2) / (2 * knee) : slope * over;
        out[i] = samples[i] * 10 ** (cut / 20);
    }
    return out;
}

/** A little left and right by voice, the lowest in the middle: an offset added to the part's own pan. */
export function spread(index, count) {
    if (count < 2) return 0;
    const lowest = index === count - 1;
    if (lowest) return 0;
    const side = index % 2 === 0 ? -1 : 1;
    return side * (0.18 + 0.08 * Math.floor(index / 2));
}

// ---- The room -------------------------------------------------------------------------------------

/** A small seeded random number generator (the same room every time). */
function seeded(seed) {
    let s = seed >>> 0;
    return () => {
        s = (s + 0x6d2b79f5) >>> 0;
        let r = Math.imul(s ^ (s >>> 15), 1 | s);
        r ^= r + Math.imul(r ^ (r >>> 7), 61 | r);
        return ((r ^ (r >>> 14)) >>> 0) / 4294967296;
    };
}

/** A room's impulse response, stereo: decaying, darkening noise after a short gap. */
export function impulseResponse(name, sampleRate) {
    const room = ROOMS[name];
    if (!room) return null;
    const length = Math.round((room.predelay + room.decay * 1.2) * sampleRate);
    const gap = Math.round(room.predelay * sampleRate);
    return [11, 23].map((seed) => {
        const random = seeded(seed);
        const out = new Float32Array(length);
        let low = 0;
        for (let i = gap; i < length; i++) {
            const t = (i - gap) / sampleRate;
            // -60 dB at `decay` seconds; the treble fades faster (a one-pole filter closing over time).
            const envelope = 10 ** ((-3 * t) / room.decay);
            const smoothing = room.bright * Math.exp(-t / room.decay);
            low += (random() * 2 - 1 - low) * Math.max(0.02, smoothing);
            out[i] = low * envelope;
        }
        // The first moments rise over 2 ms (no click at the start of the tail).
        for (let i = gap; i < Math.min(length, gap + sampleRate * 0.002); i++) out[i] *= (i - gap) / (sampleRate * 0.002);
        // Unit energy: a sustained note's room sits at the room's `wet` level under it.
        const energy = Math.sqrt(out.reduce((sum, v) => sum + v * v, 0));
        return out.map((v) => v / energy);
    });
}

/** `signal` through `response`, by FFT overlap-add (the browser's convolver differs run to run). */
export function convolve(signal, response) {
    let size = 1;
    while (size < response.length * 2) size <<= 1;
    const step = size - response.length + 1;
    const out = new Float32Array(signal.length + response.length - 1);
    const hr = new Float64Array(size);
    const hi = new Float64Array(size);
    hr.set(response);
    fft(hr, hi);
    const xr = new Float64Array(size);
    const xi = new Float64Array(size);
    for (let at = 0; at < signal.length; at += step) {
        xr.fill(0);
        xi.fill(0);
        xr.set(signal.subarray(at, Math.min(signal.length, at + step)));
        fft(xr, xi);
        for (let k = 0; k < size; k++) {
            const r = xr[k] * hr[k] - xi[k] * hi[k];
            xi[k] = xr[k] * hi[k] + xi[k] * hr[k];
            xr[k] = r;
        }
        fft(xr, xi, true);
        const upTo = Math.min(out.length, at + size);
        for (let i = at; i < upTo; i++) out[i] += xr[i - at];
    }
    return out;
}

// ---- The mix --------------------------------------------------------------------------------------

/**
 * A look-ahead peak limiter: nothing above `ceilingDb`, the gain easing down over 5 ms before a
 * peak and back up over 80 ms after. Works in place on `channels`.
 */
export function limit(channels, sampleRate, ceilingDb = CEILING_DB) {
    const ceiling = 10 ** (ceilingDb / 20);
    const length = channels[0].length;
    const gain = new Float32Array(length).fill(1);
    for (let i = 0; i < length; i++) {
        let peak = 0;
        for (const c of channels) peak = Math.max(peak, Math.abs(c[i]));
        if (peak > ceiling) gain[i] = ceiling / peak;
    }
    const attack = Math.round(0.005 * sampleRate);
    const release = 1 / Math.round(0.08 * sampleRate);
    // Backwards: the gain falls in a straight line over `attack` frames to each peak's gain.
    for (let i = length - 2; i >= 0; i--) gain[i] = Math.min(gain[i], gain[i + 1] + 1 / attack);
    // Forwards: it rises again slowly.
    for (let i = 1; i < length; i++) gain[i] = Math.min(gain[i], gain[i - 1] + release);
    for (const c of channels) for (let i = 0; i < length; i++) c[i] *= gain[i];
    return channels;
}

/** Brings the mix to `target` LUFS, then limits it (twice, so the limiting doesn't leave it quiet). */
export function finish(channels, sampleRate, target = TARGET_LUFS) {
    for (let pass = 0; pass < 2; pass++) {
        const level = loudness(channels, sampleRate);
        if (!Number.isFinite(level)) return channels;
        const gain = 10 ** ((target - level) / 20);
        if (Math.abs(target - level) < 0.1) break;
        for (const c of channels) for (let i = 0; i < c.length; i++) c[i] *= gain;
        limit(channels, sampleRate);
    }
    return channels;
}

/** A take at the mix's rate (one recorded on another device can differ): the browser resamples it. */
async function atRate(samples, from, to) {
    if (from === to) return samples;
    const length = Math.ceil((samples.length * to) / from);
    const offline = new OfflineAudioContext(1, length, to);
    const buffer = offline.createBuffer(1, samples.length, from);
    buffer.copyToChannel(samples, 0);
    const source = offline.createBufferSource();
    source.buffer = buffer;
    source.connect(offline.destination);
    source.start();
    return (await offline.startRendering()).getChannelData(0);
}

/**
 * The polished render, worked out in code (the browser's own render differs slightly run to run):
 * each voice may carry its `spread` (a pan offset) and `under` (the chords); `voices` [{ samples, sampleRate, offsetSeconds, volume, pan,
 * audible, melody }] in the order of the parts (highest first), placed exactly where the dry mix
 * puts them, the room's tail after the last note. Returns { channels, sampleRate }.
 */
export async function polishRender({ voices, lengthSeconds, sampleRate, room = DEFAULT_ROOM }) {
    const heard = voices.filter((v) => v.audible && v.samples?.length);
    const gains = levelGains(heard);
    const tail = ROOMS[room] ? ROOMS[room].decay : 0;
    const frames = Math.max(1, Math.ceil((lengthSeconds + tail) * sampleRate));
    const channels = [new Float32Array(frames), new Float32Array(frames)];
    for (const [n, voice] of heard.entries()) {
        // Level first, so every part meets the compressor at the same loudness.
        const levelled = (await atRate(voice.samples, voice.sampleRate, sampleRate)).map((v) => v * gains[n]);
        const samples = compress(levelled, sampleRate).map((v) => v * voice.volume);
        // Equal-power panning, as the dry mix's StereoPannerNode does for one channel; `spread` as
        // the studio's Hear it polished gives it (by part), else by place among the voices.
        const x = (Math.max(-1, Math.min(1, voice.pan + (voice.spread ?? spread(n, heard.length)))) + 1) / 2;
        const left = Math.cos((x * Math.PI) / 2);
        const right = Math.sin((x * Math.PI) / 2);
        const at = Math.round(voice.offsetSeconds * sampleRate);
        for (let i = Math.max(0, -at); i < samples.length && at + i < frames; i++) {
            channels[0][at + i] += samples[i] * left;
            channels[1][at + i] += samples[i] * right;
        }
    }
    const response = impulseResponse(room, sampleRate);
    if (response) {
        // The room hears the mix in mono and answers in stereo; `frames` has room for its tail.
        const send = channels[0].map((v, i) => (v + channels[1][i]) / 2);
        response.forEach((ir, k) => {
            const wet = convolve(send, ir);
            for (let i = 0; i < frames; i++) channels[k][i] += ROOMS[room].wet * wet[i];
        });
    }
    return { channels: finish(channels, sampleRate), sampleRate };
}
