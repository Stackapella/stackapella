// The sync tool's mic step (docs/youtube-sync-plan.md, "The mic helps inside the sync tool"; Hillel, 2026-11-19): the
// video plays through the speakers once, the mic hears the room, and this suggests how the performance runs against our
// template: the key (a shift in semitones), the tuning (cents), the form (how many verses) and an anchor at each bar
// line. A person checks every suggestion in the tool. Only the map is kept: the heard audio and these features live in
// the browser for the few seconds this takes, and are dropped.
//
// The method is the 2003 one (chroma and one global dynamic time warping, Hu, Dannenberg and Tzanetakis; Turetsky and
// Ellis): our notes become chroma directly (no synthesis), the mic's audio becomes chroma by FFT, and one warping path
// runs through the whole performance. There is no per-note matching, no cross-correlation of note clips and no
// per-note confidence (the plan's design-arounds). Pure, unit-tested in tests/listen-align.test.js on synthesized
// performances.

import { fft } from './dsp.js';
import { barAt } from './theory.js';
import { barLines, performanceBeats, placeOf, segments, unroll } from './sync-map.js';

export const HOP = 0.1; // seconds between chroma frames
const LOW_HZ = 65; // C2: under it, room rumble
const HIGH_HZ = 2100; // above it, mostly harmonics and consonants
const ACTIVE = 0.04; // a frame quieter than this fraction of the loud frames is silence (an intro's hush, the room)
// What a heard frame outside the song costs (an intro, an interlude, applause): over sound, about what a frame that
// doesn't match costs (a matched one is about 0.1 to 0.3); in silence, next to nothing.
const OUTSIDE = 0.5;
const QUIET = 0.05;
const INTERLUDE = 1.5; // seconds of waiting between verses that make a gap in the form
// Steps through the cost grid as [template frames, heard frames]: a performance from a third of our tempo to three times
// it, locally (a held fermata, a rushed pickup).
const STEPS = [[1, 1], [2, 1], [1, 2], [1, 3], [3, 1]];
// What leaving a steady pace costs, per frame of difference in a step (Hillel's first real sync, 2026-11-19: through a
// webcam mic in a chapel, matches scored little better than leaving audio out, and a fourth verse rushed at the
// fastest step "explained" more of it). A held fermata or a rubato phrase still bends the path; a whole verse can't
// be squeezed in for free.
const SLOPE = 0.1;
const BEND = STEPS.map(([di, dj]) => SLOPE * Math.abs(di - dj));

/**
 * Chroma of heard audio: `{ frames: Float32Array[12][], times: seconds[], energy: number[], cents }`, each frame the
 * 12 pitch classes' share of the sound from LOW_HZ to HIGH_HZ, folded with the performance's tuning taken off (cents
 * from A440 equal temperament, from the spectrum's peaks).
 */
export function hearChroma(samples, sampleRate, { hop = HOP } = {}) {
    const size = fftSize(sampleRate);
    const step = Math.round(hop * sampleRate);
    const window = hann(size);
    const re = new Float32Array(size);
    const im = new Float32Array(size);
    const low = Math.max(1, Math.floor((LOW_HZ * size) / sampleRate));
    const high = Math.min(size / 2 - 1, Math.ceil((HIGH_HZ * size) / sampleRate));
    const spectra = [];
    const times = [];
    for (let start = 0; start + size <= samples.length; start += step) {
        for (let i = 0; i < size; i++) {
            re[i] = samples[start + i] * window[i];
            im[i] = 0;
        }
        fft(re, im);
        const mags = new Float32Array(high + 1);
        for (let k = low; k <= high; k++) mags[k] = Math.hypot(re[k], im[k]);
        spectra.push(mags);
        times.push((start + size / 2) / sampleRate);
    }
    const cents = tuning(spectra, size, sampleRate, low, high);
    const frames = [];
    const energy = [];
    for (const mags of spectra) {
        const chroma = new Float32Array(12);
        let sum = 0;
        for (let k = low; k <= high; k++) {
            const m = mags[k];
            if (!m) continue;
            const midi = 69 + 12 * Math.log2((k * sampleRate) / size / 440) - cents / 100;
            chroma[pc(Math.round(midi))] += m * m;
            sum += m * m;
        }
        energy.push(Math.sqrt(sum));
        frames.push(normalise(compress(chroma)));
    }
    return { frames, times, energy, cents };
}

/**
 * Chroma of our notes for the performance `form` (js/sync-map.js), at the template's tempo: `{ frames, beats }`, a frame
 * every `hop` seconds with the beat it sits on. Each sounding note adds its pitch class, and a little of its fifth and
 * third above (the overtones a mic hears in a voice).
 */
export function scoreChroma(template, form, { hop = HOP } = {}) {
    const whole = unroll(template, form);
    const secondsPerBeat = 60 / template.tempo;
    const length = performanceBeats(template, form);
    const count = Math.ceil((length * secondsPerBeat) / hop);
    const frames = Array.from({ length: count }, () => new Float32Array(12));
    const beats = Array.from({ length: count }, (_, i) => (i * hop) / secondsPerBeat);
    for (const part of whole.parts) {
        for (const note of part.guide.notes) {
            const from = Math.floor((note.beat * secondsPerBeat) / hop);
            const to = Math.min(count, Math.ceil(((note.beat + note.length) * secondsPerBeat) / hop));
            for (let i = from; i < to; i++) {
                frames[i][pc(note.pitch)] += 1;
                frames[i][pc(note.pitch + 7)] += 0.3;
                frames[i][pc(note.pitch + 4)] += 0.12;
            }
        }
    }
    return { frames: frames.map((f) => normalise(compress(f))), beats };
}

/** The best key shifts, best first, by the heard sound's overall chroma against ours rotated (`count` of them). */
export function likelyShifts(heard, score, count = 3) {
    const profile = (frames) => normalise(frames.reduce((sum, f) => sum.map((v, i) => v + f[i]), new Float32Array(12)));
    const ours = profile(score.frames);
    const theirs = profile(heard.frames.filter((_, i) => heard.energy[i] > activeLevel(heard.energy)));
    const scored = [];
    for (let shift = -6; shift <= 5; shift++) {
        let dot = 0;
        for (let i = 0; i < 12; i++) dot += ours[i] * theirs[pc(i + shift)];
        scored.push({ shift, dot });
    }
    return scored.sort((a, b) => b.dot - a.dot).slice(0, count).map((s) => s.shift);
}

/**
 * One warping path from our chroma (rotated by `shift`) through the heard chroma. Every heard frame is paid for exactly
 * once: inside a step of the path at its own cell's cost, or waiting outside the song (before it, after it, or between
 * two segments of the form where `boundaries` say one ends), which costs little in silence and OUTSIDE over sound. So
 * no path gains by leaving audio out, and forms of different lengths compare fairly by their totals.
 * `{ path: [[scoreFrame, heardFrame]], cost }`.
 */
export function warp(score, heard, shift, { boundaries = [], cost: match = null } = {}) {
    const m = score.frames.length;
    const n = heard.frames.length;
    const rotated = match ? null : score.frames.map((f) => rotate(f, shift));
    // How far our frame i is from heard frame j: given (a table worked out once per key, see suggest), or worked out here.
    const d = match ?? ((i, j) => 1 - dot(rotated[i], heard.frames[j]));
    const level = activeLevel(heard.energy);
    const wait = heard.energy.map((e) => (e > level ? OUTSIDE : QUIET));
    const waits = new Uint8Array(m);
    for (const b of boundaries) if (b > 0 && b <= m) waits[b - 1] = 1;
    const cost = new Float32Array(m * n).fill(Infinity);
    const from = new Uint8Array(m * n); // the step taken into a cell, 1-based; 0: the path starts here; 255: waited
    let before = 0;
    for (let j = 0; j < n; j++) {
        cost[j] = before + d(0, j);
        before += wait[j];
    }
    for (let i = 0; i < m; i++) {
        for (let j = 0; j < n; j++) {
            const here = i * n + j;
            if (i > 0) {
                let best = Infinity;
                let how = 0;
                for (let s = 0; s < STEPS.length; s++) {
                    const [di, dj] = STEPS[s];
                    if (i - di < 0 || j - dj < 0) continue;
                    let c = cost[(i - di) * n + (j - dj)] + BEND[s];
                    if (c === Infinity) continue;
                    for (let k = 0; k < dj; k++) c += d(i, j - k); // each heard frame the step covers, at its own cost
                    if (c < best) {
                        best = c;
                        how = s + 1;
                    }
                }
                if (how) {
                    cost[here] = best;
                    from[here] = how;
                }
            }
            if (waits[i] && j > 0 && cost[here - 1] + wait[j] < cost[here]) {
                cost[here] = cost[here - 1] + wait[j];
                from[here] = 255;
            }
        }
    }
    let end = -1;
    let total = Infinity;
    let after = 0;
    for (let j = n - 1; j >= 0; j--) {
        const c = cost[(m - 1) * n + j] + after;
        if (c < total) {
            total = c;
            end = j;
        }
        after += wait[j];
    }
    if (end < 0) return { path: [], cost: Infinity };
    const path = [];
    let i = m - 1;
    let j = end;
    for (;;) {
        path.push([i, j]);
        const how = from[i * n + j];
        if (how === 0) break;
        if (how === 255) j -= 1;
        else {
            i -= STEPS[how - 1][0];
            j -= STEPS[how - 1][1];
        }
    }
    path.reverse();
    return { path, cost: total };
}

/**
 * The whole suggestion: for each candidate form (`forms`, e.g. one to four verses) and each likely key, one warp; the
 * cheapest wins. `{ form, shift, cents, anchors: [[heardSeconds, performanceBeat]] }`, an anchor at each bar line the
 * path reaches, in the heard recording's seconds (the caller turns them into the video's, from the times it logged
 * while listening). Null when nothing was heard.
 */
export function suggest(template, heard, forms) {
    if (!heard.frames.length || !(Math.max(...heard.energy) > 0)) return null;
    const n = heard.frames.length;
    // One pass through the song's sections: every form's frames are this pass's again (a verse sung twice is the same
    // music), so each key's distances are worked out once, for this pass, and every form reads them by its place.
    const once = template.sections.map((_, section) => ({ kind: 'section', section }));
    const base = scoreChroma(template, once);
    const onceAt = segments(template, once).map((s) => s.start);
    const tables = new Map();
    const tableFor = (shift) => {
        if (!tables.has(shift)) {
            const table = new Float32Array(base.frames.length * n);
            base.frames.forEach((frame, i) => {
                const r = rotate(frame, shift);
                for (let j = 0; j < n; j++) table[i * n + j] = 1 - dot(r, heard.frames[j]);
            });
            tables.set(shift, table);
        }
        return tables.get(shift);
    };
    const secondsPerBeat = 60 / template.tempo;
    let best = null;
    for (const form of forms) {
        const score = scoreChroma(template, form);
        // Each of this form's frames is the one-pass frame at the same place in the same section (past the end: the last).
        const rows = Int32Array.from(score.beats, (beat) => {
            const place = placeOf(template, form, beat);
            if (place?.templateBeat == null) return base.frames.length - 1;
            const section = template.sections[place.segment.section];
            const at = onceAt[place.segment.section] + place.templateBeat - section.startBeat;
            return Math.min(base.frames.length - 1, Math.round((at * secondsPerBeat) / HOP));
        });
        const boundaries = segments(template, form).slice(1).map((s) => Math.round((s.start * secondsPerBeat) / HOP));
        for (const shift of likelyShifts(heard, base)) {
            const table = tableFor(shift);
            const { path, cost } = warp(score, heard, shift, { boundaries, cost: (i, j) => table[rows[i] * n + j] });
            if (path.length && (!best || cost < best.cost)) best = { form, shift, path, cost, score };
        }
    }
    if (!best) return null;
    const anchors = [];
    for (const beat of barLines(template, best.form)) {
        const frame = Math.min(Math.round((beat * secondsPerBeat) / HOP), best.score.frames.length - 1);
        const cell = best.path.find(([i]) => i >= frame);
        if (!cell) continue;
        const seconds = heard.times[cell[1]];
        if (anchors.length && (seconds <= anchors[anchors.length - 1][0] || beat <= anchors[anchors.length - 1][1])) continue;
        anchors.push([seconds, beat]);
    }
    let form = best.form.map((s) => (s.kind === 'section' ? { ...s, shift: (s.shift ?? 0) + best.shift, cents: Math.round(heard.cents) } : s));
    // Where the path waited between segments for INTERLUDE seconds or more, the recording plays something of its own
    // there: a gap of whole bars goes into the form, with an anchor where it starts, so the lane doesn't stretch the
    // last bar over it. Last first, so the earlier beats stay put.
    const placed = segments(template, best.form);
    for (let k = placed.length - 1; k >= 1; k--) {
        const row = Math.round((placed[k].start * 60) / template.tempo / HOP) - 1;
        const held = best.path.filter(([i]) => i === row);
        const next = best.path.find(([i]) => i > row);
        if (!held.length || !next) continue;
        const start = heard.times[held[0][1]] + HOP;
        const end = heard.times[next[1]];
        if (end - start < INTERLUDE) continue;
        const perBar = barAt(template, template.pickupBeats ?? 0).beats;
        const beats = Math.max(1, Math.round(((end - start) * template.tempo) / 60 / perBar)) * perBar;
        const at = placed[k].start;
        for (const a of anchors) if (a[1] >= at - 1e-6) a[1] += beats;
        const before = anchors.findIndex((a) => a[1] > at);
        anchors.splice(before < 0 ? anchors.length : before, 0, [start, at]);
        form = [...form.slice(0, k), { kind: 'gap', beats }, ...form.slice(k)];
    }
    for (let i = anchors.length - 1; i > 0; i--) if (anchors[i][0] <= anchors[i - 1][0] || anchors[i][1] <= anchors[i - 1][1]) anchors.splice(i, 1);
    return { form, shift: best.shift, cents: Math.round(heard.cents), anchors };
}

/** Heard seconds to the video's: `log` is [[heardSeconds, videoSeconds], ...] read while listening, in order. */
export function toVideo(log, heardSeconds) {
    if (!log.length) return null;
    let k = 1;
    while (k < log.length - 1 && log[k][0] < heardSeconds) k++;
    const [h0, v0] = log[Math.max(0, k - 1)];
    const [h1, v1] = log[Math.min(k, log.length - 1)];
    return h1 === h0 ? v0 + (heardSeconds - h0) : v0 + ((v1 - v0) * (heardSeconds - h0)) / (h1 - h0);
}

// The tuning: where the spectrum's peaks sit against A440's semitones, a weighted circular mean in cents (-50 to 50).
function tuning(spectra, size, sampleRate, low, high) {
    let x = 0;
    let y = 0;
    for (const mags of spectra) {
        let loudest = 0;
        for (let k = low; k <= high; k++) loudest = Math.max(loudest, mags[k]);
        for (let k = low + 1; k < high; k++) {
            const m = mags[k];
            if (m < loudest * 0.1 || m < mags[k - 1] || m < mags[k + 1]) continue;
            const a = Math.log(mags[k - 1] + 1e-12);
            const b = Math.log(m + 1e-12);
            const c = Math.log(mags[k + 1] + 1e-12);
            const offset = (0.5 * (a - c)) / (a - 2 * b + c || 1e-12);
            const midi = 69 + 12 * Math.log2(((k + offset) * sampleRate) / size / 440);
            const angle = 2 * Math.PI * (midi - Math.round(midi));
            x += m * Math.cos(angle);
            y += m * Math.sin(angle);
        }
    }
    return x || y ? (Math.atan2(y, x) / (2 * Math.PI)) * 100 : 0;
}

function activeLevel(energy) {
    const sorted = [...energy].sort((a, b) => a - b);
    return (sorted[Math.floor(sorted.length * 0.9)] ?? 0) * ACTIVE;
}

function fftSize(sampleRate) {
    // About 0.17 s: a semitone apart at C2 (65 Hz is 3.9 Hz from its neighbour) in a few bins.
    let size = 1024;
    while (size / sampleRate < 0.17) size *= 2;
    return size;
}

function hann(size) {
    const w = new Float32Array(size);
    for (let i = 0; i < size; i++) w[i] = 0.5 - 0.5 * Math.cos((2 * Math.PI * i) / (size - 1));
    return w;
}

function compress(chroma) {
    let max = 0;
    for (const v of chroma) max = Math.max(max, v);
    return max ? chroma.map((v) => Math.log1p((100 * v) / max)) : chroma;
}

function normalise(v) {
    const norm = Math.hypot(...v);
    return norm ? v.map((x) => x / norm) : v.fill(1 / Math.sqrt(12));
}

function rotate(chroma, shift) {
    const out = new Float32Array(12);
    for (let i = 0; i < 12; i++) out[pc(i + shift)] = chroma[i];
    return out;
}

function dot(a, b) {
    let s = 0;
    for (let i = 0; i < 12; i++) s += a[i] * b[i];
    return s;
}

function pc(n) {
    return ((n % 12) + 12) % 12;
}
