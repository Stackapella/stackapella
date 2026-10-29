// The pitch tracker, shared by the beta's quality check, Sing, the course, the creator and coaching
// (docs/vocal-coaching-plan.md, "How it works"). One frame every 10 ms (100 a second): the pitch in
// Hz (0 when unvoiced), how clear it is (0 to 1), and how loud (dBFS). Pure functions over samples,
// so tests/pitchtrack.test.js checks them on synthesized singing; trackInWorker runs a whole take
// off the main thread, and trackFrame reads the live mic (engine.inputFrame()).
//
// YIN (de Cheveigne and Kawahara, 2002) on windows of the same length in time at any sample rate
// (2048 samples at 48 kHz). The take is first brought down to about 24 kHz, which keeps every sung
// pitch (up to about C6) and makes a 3-minute take quick. The difference function is computed
// through the FFT. Then octave slips are repaired against the frames around them, and a short
// median filter smooths what's left.

import { fft } from './dsp.js';

// Bump with any change to how pitch is detected: scores carry it, and a new number starts new leaderboards.
export const PITCH_DETECTOR_VERSION = 1;

export const FRAME_RATE = 100; // frames a second
export const MIN_HZ = 60; // below a bass's low B1
export const MAX_HZ = 1100; // above a soprano's C6
const WINDOW_SECONDS = 2048 / 48000;
const TARGET_RATE = 24000;
const THRESHOLD = 0.15; // YIN's dip threshold
export const VOICED_CLARITY = 1 - THRESHOLD; // only a clear dip counts as a pitch
export const QUIET_DB = -50; // quieter than this is silence, whatever its pitch

/**
 * Tracks a whole take. Returns `{ rate, hz, clarity, loudness }`: `rate` frames a second, and a
 * Float32Array of each per frame. Frame i describes the window centred on i / rate seconds.
 */
export function trackTake(samples, sampleRate) {
    const { signal, rate } = downsample(samples, sampleRate);
    const window = Math.round(WINDOW_SECONDS * rate);
    const hop = rate / FRAME_RATE;
    const count = Math.max(0, Math.floor(samples.length / (sampleRate / FRAME_RATE)));
    const hz = new Float32Array(count);
    const clarity = new Float32Array(count);
    const loudness = new Float32Array(count);
    const yin = makeYin(window, rate);
    const frame = new Float32Array(window);
    for (let i = 0; i < count; i++) {
        const centre = Math.round(i * hop);
        const start = centre - (window >> 1);
        for (let j = 0; j < window; j++) {
            const k = start + j;
            frame[j] = k >= 0 && k < signal.length ? signal[k] : 0;
        }
        loudness[i] = decibels(frame);
        const found = loudness[i] > QUIET_DB ? yin(frame) : null;
        if (found && found.clarity >= VOICED_CLARITY) {
            hz[i] = found.hz;
            clarity[i] = found.clarity;
        } else {
            clarity[i] = found?.clarity ?? 0;
        }
    }
    repairOctaves(hz);
    return { rate: FRAME_RATE, hz: medianFilter(hz, 5), clarity, loudness };
}

/**
 * One live reading from the mic (`samples` is engine.inputFrame(): the latest 2048 samples at the
 * context's rate). Returns `{ hz, clarity, loudness }`, hz 0 when unvoiced.
 */
export function trackFrame(samples, sampleRate, { minClarity = VOICED_CLARITY } = {}) {
    const { signal, rate } = downsample(samples, sampleRate);
    const window = Math.min(signal.length, Math.round(WINDOW_SECONDS * rate));
    const frame = signal.subarray(signal.length - window);
    const loudness = decibels(frame);
    const found = loudness > QUIET_DB ? liveYin(window, rate)(frame) : null;
    const voiced = found && found.clarity >= minClarity;
    return { hz: voiced ? found.hz : 0, clarity: found?.clarity ?? 0, loudness };
}

// The live tracker's YIN, made once per window and rate (it's called every frame while singing).
const yins = new Map();
function liveYin(size, rate) {
    const key = `${size}:${rate}`;
    if (!yins.has(key)) yins.set(key, makeYin(size, rate));
    return yins.get(key);
}

/** Tracks a take in a worker, so a phone stays responsive. Falls back to this thread. */
export function trackInWorker(samples, sampleRate) {
    let worker;
    try {
        worker = new Worker(new URL('./pitchtrack-worker.js', import.meta.url), { type: 'module' });
    } catch {
        return Promise.resolve(trackTake(samples, sampleRate));
    }
    return new Promise((resolve, reject) => {
        worker.onmessage = (event) => {
            worker.terminate();
            resolve(event.data);
        };
        worker.onerror = (event) => {
            worker.terminate();
            console.warn('Tracking on the main thread instead', event.message);
            try {
                resolve(trackTake(samples, sampleRate));
            } catch (error) {
                reject(error);
            }
        };
        const copy = samples.slice(); // the worker takes this copy; the caller keeps its samples
        worker.postMessage({ samples: copy, sampleRate }, [copy.buffer]);
    });
}

// ---- The maths ----------------------------------------------------------------------------------

/** Lowpass (a windowed sinc) and keep every nth sample, to about 24 kHz. */
export function downsample(samples, sampleRate) {
    const factor = Math.max(1, Math.round(sampleRate / TARGET_RATE));
    if (factor === 1) {
        return { signal: samples, rate: sampleRate };
    }
    const taps = 8 * factor + 1;
    const kernel = new Float32Array(taps);
    const half = (taps - 1) / 2;
    let sum = 0;
    for (let i = 0; i < taps; i++) {
        const x = i - half;
        const sinc = x === 0 ? 1 : Math.sin((Math.PI * x) / factor) / ((Math.PI * x) / factor);
        const hann = 0.5 - 0.5 * Math.cos((2 * Math.PI * i) / (taps - 1));
        kernel[i] = sinc * hann;
        sum += kernel[i];
    }
    const out = new Float32Array(Math.floor(samples.length / factor));
    for (let o = 0; o < out.length; o++) {
        const centre = o * factor;
        let acc = 0;
        for (let i = 0; i < taps; i++) {
            const k = centre + i - half;
            if (k >= 0 && k < samples.length) acc += samples[k] * kernel[i];
        }
        out[o] = acc / sum;
    }
    return { signal: out, rate: sampleRate / factor };
}

/** RMS level in dBFS (-Infinity for silence). */
export function decibels(frame) {
    let sum = 0;
    for (let i = 0; i < frame.length; i++) sum += frame[i] * frame[i];
    return 10 * Math.log10(sum / frame.length);
}

/**
 * A YIN detector for windows of `size` samples at `rate`. The difference function
 * d(tau) = sum x[j]^2 + sum x[j+tau]^2 - 2 sum x[j] x[j+tau] takes its cross term from one FFT
 * correlation, and the energy terms from running sums.
 */
function makeYin(size, rate) {
    const tauMin = Math.max(2, Math.floor(rate / MAX_HZ));
    const tauMax = Math.min(Math.floor(rate / MIN_HZ), size >> 1);
    const n = size - tauMax;
    // The correlation's lags stop at tauMax, and the second signal ends at n, so a circular
    // correlation of `size` points never wraps: the next power of two is enough.
    let fftSize = 1;
    while (fftSize < size) fftSize <<= 1;
    const re = new Float64Array(fftSize);
    const im = new Float64Array(fftSize);
    const cmnd = new Float64Array(tauMax + 1);
    const squares = new Float64Array(size + 1);
    return (frame) => {
        if (n <= 0 || tauMax <= tauMin) return null;
        squares[0] = 0;
        for (let j = 0; j < size; j++) squares[j + 1] = squares[j] + frame[j] * frame[j];
        // Both real signals in one complex FFT: x (the frame) as the real part, y (its first n
        // samples) as the imaginary part. Then X(k) = (Z(k) + conj Z(-k)) / 2 and
        // Y(k) = (Z(k) - conj Z(-k)) / 2i, and the correlation is the inverse of X(k) conj Y(k).
        re.fill(0);
        im.fill(0);
        for (let j = 0; j < size; j++) re[j] = frame[j];
        for (let j = 0; j < n; j++) im[j] = frame[j];
        fft(re, im);
        for (let k = 0; k <= fftSize >> 1; k++) {
            const m = (fftSize - k) & (fftSize - 1);
            const xr = (re[k] + re[m]) / 2;
            const xi = (im[k] - im[m]) / 2;
            const yr = (im[k] + im[m]) / 2;
            const yi = -(re[k] - re[m]) / 2;
            // X conj(Y), and its mirror at m is the conjugate (the correlation is real).
            const pr = xr * yr + xi * yi;
            const pi = xi * yr - xr * yi;
            re[k] = pr;
            im[k] = pi;
            re[m] = pr;
            im[m] = -pi;
        }
        fft(re, im, true);
        const head = squares[n];
        cmnd[0] = 1;
        let running = 0;
        for (let tau = 1; tau <= tauMax; tau++) {
            const tail = squares[tau + n] - squares[tau];
            const d = Math.max(0, head + tail - 2 * re[tau]);
            running += d;
            cmnd[tau] = running === 0 ? 1 : (d * tau) / running;
        }
        let tau = tauMin;
        while (tau <= tauMax && cmnd[tau] >= THRESHOLD) tau++;
        if (tau > tauMax) {
            // No clear dip: report how clear the best one is (under VOICED_CLARITY, so unvoiced).
            let best = tauMin;
            for (let t = tauMin; t <= tauMax; t++) if (cmnd[t] < cmnd[best]) best = t;
            return { hz: rate / best, clarity: Math.min(VOICED_CLARITY - 0.01, Math.max(0, 1 - cmnd[best])) };
        }
        while (tau + 1 <= tauMax && cmnd[tau + 1] < cmnd[tau]) tau++;
        const left = cmnd[tau - 1];
        const right = tau + 1 <= tauMax ? cmnd[tau + 1] : cmnd[tau];
        const curve = left - 2 * cmnd[tau] + right;
        const exact = curve > 0 ? tau + (0.5 * (left - right)) / curve : tau;
        return { hz: rate / exact, clarity: Math.max(0, Math.min(1, 1 - cmnd[tau])) };
    };
}

/**
 * Octave slips: a voiced frame about twice or half the pitch of the frames around it (the median
 * of up to 8 voiced neighbours) is moved back into their octave.
 */
export function repairOctaves(hz) {
    const copy = Float32Array.from(hz);
    for (let i = 0; i < hz.length; i++) {
        if (!copy[i]) continue;
        const around = [];
        for (let k = Math.max(0, i - 4); k <= Math.min(hz.length - 1, i + 4); k++) {
            if (k !== i && copy[k]) around.push(copy[k]);
        }
        if (around.length < 4) continue;
        around.sort((a, b) => a - b);
        const typical = around[around.length >> 1];
        const octaves = Math.round(Math.log2(copy[i] / typical));
        if (octaves !== 0 && Math.abs(Math.log2(copy[i] / typical) - octaves) < 0.1) {
            hz[i] = copy[i] / 2 ** octaves;
        }
    }
    return hz;
}

/** A median over `width` frames, on voiced frames only (unvoiced stays 0, and isn't counted). */
export function medianFilter(hz, width) {
    const out = new Float32Array(hz.length);
    const half = width >> 1;
    for (let i = 0; i < hz.length; i++) {
        if (!hz[i]) continue;
        const near = [];
        for (let k = Math.max(0, i - half); k <= Math.min(hz.length - 1, i + half); k++) {
            if (hz[k]) near.push(hz[k]);
        }
        near.sort((a, b) => a - b);
        out[i] = near[near.length >> 1];
    }
    return out;
}

export function hzToCents(hz, midi) {
    return 1200 * Math.log2(hz / (440 * 2 ** ((midi - 69) / 12)));
}
