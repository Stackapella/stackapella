// Signals the app plays, and the maths that finds them again in a recording. Ported from the audio
// spike (web/spike_audio/lib/dsp.dart), where it was proven on real devices. No browser APIs here,
// so tests/dsp.test.js covers it all.

/** A short rising chirp under a Hann window: one sharp correlation peak, even through a laptop. */
export function chirp(sampleRate, { seconds = 0.01, fromHz = 1000, toHz = 8000, level = 0.8 } = {}) {
    const length = Math.round(seconds * sampleRate);
    const out = new Float32Array(length);
    const sweep = (toHz - fromHz) / seconds;
    for (let i = 0; i < length; i++) {
        const t = i / sampleRate;
        const window = 0.5 - 0.5 * Math.cos((2 * Math.PI * i) / (length - 1));
        out[i] = level * window * Math.sin(2 * Math.PI * (fromHz * t + 0.5 * sweep * t * t));
    }
    return out;
}

/** A metronome click: a decaying sine. */
export function click(sampleRate, { hz = 1500, seconds = 0.03, level = 0.7 } = {}) {
    const length = Math.round(seconds * sampleRate);
    const out = new Float32Array(length);
    for (let i = 0; i < length; i++) {
        const t = i / sampleRate;
        out[i] = level * Math.exp(-t * 120) * Math.sin(2 * Math.PI * hz * t);
    }
    return out;
}

/**
 * Chirps at uneven gaps, so a recording shifted by one gap lines up with at most one chirp and the
 * correlation can't pick the wrong one (a Bluetooth delay is longer than a gap).
 */
export function clickTrain(sampleRate, { count = 8, gapsSeconds = [0.31, 0.27, 0.35, 0.29, 0.33, 0.26, 0.37] } = {}) {
    const positions = [0];
    for (let i = 1; i < count; i++) {
        positions.push(positions[i - 1] + Math.round(gapsSeconds[(i - 1) % gapsSeconds.length] * sampleRate));
    }
    return buildTrain(sampleRate, positions);
}

/** Evenly spaced chirps, for checking drift over a long recording. */
export function evenTrain(sampleRate, { count, intervalSeconds }) {
    const step = Math.round(intervalSeconds * sampleRate);
    return buildTrain(sampleRate, Array.from({ length: count }, (_, i) => i * step));
}

function buildTrain(sampleRate, positions) {
    const template = chirp(sampleRate);
    const signal = new Float32Array(positions[positions.length - 1] + template.length);
    for (const position of positions) {
        signal.set(template, position);
    }
    return { signal, template, positions };
}

/** In-place iterative radix-2 FFT; `re` and `im` share a power-of-two length. */
export function fft(re, im, inverse = false) {
    const n = re.length;
    for (let i = 1, j = 0; i < n; i++) {
        let bit = n >> 1;
        for (; j & bit; bit >>= 1) {
            j ^= bit;
        }
        j ^= bit;
        if (i < j) {
            [re[i], re[j]] = [re[j], re[i]];
            [im[i], im[j]] = [im[j], im[i]];
        }
    }
    for (let size = 2; size <= n; size <<= 1) {
        const angle = ((inverse ? 2 : -2) * Math.PI) / size;
        const wr = Math.cos(angle);
        const wi = Math.sin(angle);
        const half = size >> 1;
        for (let start = 0; start < n; start += size) {
            let cr = 1;
            let ci = 0;
            for (let k = 0; k < half; k++) {
                const a = start + k;
                const b = a + half;
                const xr = re[b] * cr - im[b] * ci;
                const xi = re[b] * ci + im[b] * cr;
                re[b] = re[a] - xr;
                im[b] = im[a] - xi;
                re[a] += xr;
                im[a] += xi;
                const next = cr * wr - ci * wi;
                ci = cr * wi + ci * wr;
                cr = next;
            }
        }
    }
    if (inverse) {
        for (let i = 0; i < n; i++) {
            re[i] /= n;
            im[i] /= n;
        }
    }
}

/** `c[lag] = sum(reference[j] * signal[lag + j])` for lag 0..maxLag, through the FFT. */
export function crossCorrelate(signal, reference, maxLag) {
    let n = 1;
    while (n < signal.length + reference.length) {
        n <<= 1;
    }
    const sr = new Float64Array(n);
    sr.set(signal);
    const si = new Float64Array(n);
    const rr = new Float64Array(n);
    rr.set(reference);
    const ri = new Float64Array(n);
    fft(sr, si);
    fft(rr, ri);
    for (let i = 0; i < n; i++) {
        const re = sr[i] * rr[i] + si[i] * ri[i];
        const im = si[i] * rr[i] - sr[i] * ri[i];
        sr[i] = re;
        si[i] = im;
    }
    fft(sr, si, true);
    return sr.subarray(0, Math.min(maxLag, n - 1) + 1);
}

/**
 * Normalised cross-correlation of `template` against `signal` at whole-frame offsets within
 * `radius` of `center`, with a parabola through the best three points for the fraction.
 * Returns `{ lag, score }`: score 1 is a perfect match, below about 0.3 is noise.
 */
export function refinePeak(signal, template, center, radius) {
    let templateEnergy = 0;
    for (const v of template) {
        templateEnergy += v * v;
    }
    const scoreAt = (offset) => {
        let dot = 0;
        let energy = 0;
        for (let j = 0; j < template.length; j++) {
            const i = offset + j;
            const s = i >= 0 && i < signal.length ? signal[i] : 0;
            dot += template[j] * s;
            energy += s * s;
        }
        return energy === 0 || templateEnergy === 0 ? 0 : dot / Math.sqrt(energy * templateEnergy);
    };
    let best = center;
    let bestScore = -Infinity;
    for (let offset = center - radius; offset <= center + radius; offset++) {
        const score = scoreAt(offset);
        if (score > bestScore) {
            bestScore = score;
            best = offset;
        }
    }
    const left = scoreAt(best - 1);
    const right = scoreAt(best + 1);
    const curve = left - 2 * bestScore + right;
    const fraction = curve === 0 ? 0 : (0.5 * (left - right)) / curve;
    return { lag: best + Math.max(-0.5, Math.min(0.5, fraction)), score: bestScore };
}

/**
 * Finds a click train in a recording that started `preFrames` before the frame the train was
 * expected. `lagFrames` is the median offset of the chirps found; `reliable` needs half of them.
 */
export function analyzeTrain(recording, train, { maxLag, preFrames = 0, refineRadius = 96, threshold = 0.3 }) {
    const coarse = crossCorrelate(recording, train.signal, maxLag);
    let coarseLag = 0;
    for (let i = 1; i < coarse.length; i++) {
        if (coarse[i] > coarse[coarseLag]) {
            coarseLag = i;
        }
    }
    const lags = train.positions.map((position) => {
        const peak = refinePeak(recording, train.template, position + coarseLag, refineRadius);
        return peak.score >= threshold ? peak.lag - position - preFrames : null;
    });
    const found = lags.filter((lag) => lag !== null);
    return {
        lagFrames: found.length ? median(found) : NaN,
        spreadFrames: found.length ? Math.max(...found) - Math.min(...found) : NaN,
        detected: found.length,
        total: train.positions.length,
        reliable: found.length >= Math.ceil(train.positions.length / 2),
    };
}

export function median(values) {
    const sorted = [...values].sort((a, b) => a - b);
    const mid = sorted.length >> 1;
    return sorted.length % 2 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;
}

/** The loudest sample in each bucket of `framesPerPeak`, for drawing waveforms. */
export function peaks(samples, framesPerPeak) {
    const out = new Float32Array(Math.ceil(samples.length / framesPerPeak));
    for (let p = 0; p < out.length; p++) {
        let max = 0;
        const end = Math.min(samples.length, (p + 1) * framesPerPeak);
        for (let i = p * framesPerPeak; i < end; i++) {
            const v = Math.abs(samples[i]);
            if (v > max) {
                max = v;
            }
        }
        out[p] = max;
    }
    return out;
}

/** True when every sample is exactly zero: a mic that is open but hears nothing. */
export function isSilent(samples) {
    for (let i = 0; i < samples.length; i++) {
        if (samples[i] !== 0) {
            return false;
        }
    }
    return true;
}

// ---- Pitch -------------------------------------------------------------------------------------

/**
 * The pitch of a sung `frame` by YIN (de Cheveigne and Kawahara, 2002). Returns `{ hz, clarity }`
 * (clarity 0 to 1), or null when there's no clear pitch between `minHz` and `maxHz`.
 */
export function detectPitch(frame, sampleRate, { minHz = 60, maxHz = 1100, threshold = 0.15 } = {}) {
    const tauMin = Math.max(2, Math.floor(sampleRate / maxHz));
    const tauMax = Math.min(Math.floor(sampleRate / minHz), Math.floor(frame.length / 2));
    const n = frame.length - tauMax;
    if (tauMax <= tauMin || n <= 0) {
        return null;
    }
    const cmnd = new Float32Array(tauMax + 1);
    cmnd[0] = 1;
    let running = 0;
    for (let tau = 1; tau <= tauMax; tau++) {
        let d = 0;
        for (let j = 0; j < n; j++) {
            const diff = frame[j] - frame[j + tau];
            d += diff * diff;
        }
        running += d;
        cmnd[tau] = running === 0 ? 1 : (d * tau) / running;
    }
    let tau = tauMin;
    while (tau <= tauMax && cmnd[tau] >= threshold) tau++;
    if (tau > tauMax) {
        return null;
    }
    while (tau + 1 <= tauMax && cmnd[tau + 1] < cmnd[tau]) tau++;
    const left = cmnd[tau - 1];
    const right = tau + 1 <= tauMax ? cmnd[tau + 1] : cmnd[tau];
    const curve = left - 2 * cmnd[tau] + right;
    const exact = curve > 0 ? tau + (0.5 * (left - right)) / curve : tau;
    return { hz: sampleRate / exact, clarity: 1 - cmnd[tau] };
}

export function hzToMidi(hz) {
    return 69 + 12 * Math.log2(hz / 440);
}

const NOTE_NAMES = ['C', 'C#', 'D', 'Eb', 'E', 'F', 'F#', 'G', 'Ab', 'A', 'Bb', 'B'];

/** "C4" for MIDI 60. */
export function midiName(midi) {
    const m = Math.round(midi);
    return `${NOTE_NAMES[((m % 12) + 12) % 12]}${Math.floor(m / 12) - 1}`;
}

/**
 * The smallest shift (semitones, -7 to +7, ties toward 0) that puts `part` ({ low, high }, MIDI)
 * inside `singer`'s range. When nothing fits, the shift with the fewest semitones outside it.
 */
export function suggestTranspose(singer, part) {
    let best = null;
    for (const shift of [0, 1, -1, 2, -2, 3, -3, 4, -4, 5, -5, 6, -6, 7, -7]) {
        const outside = Math.max(0, singer.low - (part.low + shift)) + Math.max(0, part.high + shift - singer.high);
        if (!best || outside < best.outside) {
            best = { transpose: shift, outside };
        }
        if (outside === 0) break;
    }
    return { transpose: best.transpose, fits: best.outside === 0 };
}

/**
 * How much a part moves: the share of its steps from one note to the next that change pitch (Amazing
 * Grace: the soprano 29 of 31, the alto 19 of 26, the bass 9 of 14, the tenor 5 of 26).
 */
export function motion(notes = []) {
    const pitched = notes.filter((n) => Number.isFinite(n.pitch));
    if (pitched.length < 2) return 0;
    let changes = 0;
    for (let i = 1; i < pitched.length; i++) if (pitched[i].pitch !== pitched[i - 1].pitch) changes++;
    return changes / (pitched.length - 1);
}

/**
 * The octave a part is sung in to fit `singer` (0, then an octave down, up, two down, two up), with
 * the key shift that fits it there; null when it fits in none. Scoring hears any octave.
 */
export function octaveFit(singer, range) {
    for (const octave of [0, -12, 12, -24, 24]) {
        const fit = suggestTranspose(singer, { low: range.low + octave, high: range.high + octave });
        if (fit.fits) return { octave, transpose: fit.transpose };
    }
    return null;
}

/**
 * The part to suggest (Hillel, 2026-10-29: "Melody first"). `parts`: { key, range, melody, notes }, the
 * notes as the template's guide has them.
 * - A new singer (`melodyFirst`: no good take yet, and no other song started) gets the melody, in
 *   their own octave, whenever it fits in one.
 * - Otherwise the part that fits; between parts that fit, the one that moves most (a part that holds
 *   one note for a phrase is a flat first thing to learn), then the smallest key shift.
 * - When none fits, the closest.
 * Returns { part, fits, transpose, octave } or null; `octave` is -12 for "an octave lower".
 */
export function suggestPart(singer, parts, { melodyFirst = false } = {}) {
    const usable = parts.filter((p) => p.range);
    if (melodyFirst) {
        const melody = usable.find((p) => p.melody);
        const fit = melody && octaveFit(singer, melody.range);
        if (fit) return { part: melody, fits: true, transpose: fit.transpose, octave: fit.octave };
    }
    const ranked = usable
        .map((part) => ({ part, ...suggestTranspose(singer, part.range), octave: 0, moves: motion(part.notes) }))
        .sort((a, b) => Number(b.fits) - Number(a.fits) || (a.fits && b.fits ? b.moves - a.moves : 0) || Math.abs(a.transpose) - Math.abs(b.transpose));
    if (!ranked.length) return null;
    const { moves, ...best } = ranked[0];
    return best;
}

/** The parts ordered by how well they suit `singer`: fitting first, then the smallest shift. */
export function rankParts(singer, parts) {
    return parts
        .map((part) => ({ part, ...suggestTranspose(singer, part.range) }))
        .sort((a, b) => Number(b.fits) - Number(a.fits) || Math.abs(a.transpose) - Math.abs(b.transpose));
}
