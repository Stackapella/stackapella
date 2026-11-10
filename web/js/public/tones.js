// The free tools' sounds (docs/tools-plan.md, "Shared modules"), built on voices.js so every tool
// shares one AudioContext and one set of timbres, and no tool carries its own oscillator code:
// - held(): a drone or chord that keeps sounding and can be retuned (the drone tool, a tuner's
//   reference note).
// - strike(): a tuning fork, a pure tone with a short strike that dies away, or holds until ring()
//   lets it die away (press and hold to sustain, let go to ring out).
// - pluck(): a plucked string (a tanpura), bright and buzzing, ringing away over a few seconds.
// - note(): a sung note of a set length (the reference cards' intervals and chords).
// - sound(): a short sample (a metronome click) at an exact time on the audio clock.
// Everything is scheduled on the AudioContext clock, never with timers. Tested in tests/tones.test.js.

import { audio, track, voice } from './voices.js';

const cents = (from, to) => 1200 * Math.log2(to / from);

/**
 * A held chord: one voice per frequency, started on the same frame. setNotes() glides the voices
 * there are to new pitches (each `cents` sharp, for a pure-tuned chord), fades new ones in and the
 * surplus out, so a drone can change chord without a gap.
 */
export function held(frequencies, { cents: offsets = [], level = 0.12, timbre = 'ah', glide = 0.25, keepWhenHidden = false } = {}) {
    const c = audio();
    let voices = [];
    const gone = [];
    let notes = [];
    let current = level;
    const start = (f, at, offset) => ({ base: f, handle: voice(f, { at, level: current, timbre, keepWhenHidden, cents: offset, attack: 0.08, release: 0.12 }) });
    const handle = {
        /** The frequencies sounding now, in order. */
        get notes() {
            return [...notes];
        },
        setNotes(next, nextCents = []) {
            const at = c.currentTime + 0.02;
            next.forEach((f, i) => {
                const offset = nextCents[i] ?? 0;
                if (voices[i]) voices[i].handle.tune(cents(voices[i].base, f) + offset, glide);
                else voices[i] = start(f, at, offset);
            });
            for (const extra of voices.slice(next.length)) extra.handle.stop();
            gone.push(...voices.slice(next.length));
            voices = voices.slice(0, next.length);
            notes = [...next];
        },
        level(to, over = 0.15) {
            current = to;
            for (const v of voices) v.handle.level(to, over);
        },
        stop(after = 0) {
            for (const v of voices) v.handle.stop(after);
            gone.push(...voices);
            voices = [];
            notes = [];
        },
        /** Resolves once every voice it has had is silent. */
        get ended() {
            return Promise.all([...gone, ...voices].map((v) => v.handle.ended));
        },
    };
    handle.setNotes(frequencies, offsets);
    return handle;
}

// A tuning fork's first overtone is far above the note (about 6.3 times), loud for a moment at the
// strike and gone within a tenth of a second: what makes it sound struck, not switched on.
const CLANG = 6.27;

/**
 * A struck tone: a sine at `frequency` with a short strike transient, dying away to silence over
 * about `decay` seconds (or held at `level` when `hold`, until ring() lets it die away from then, or
 * stop() damps it). `at` is a time on the audio clock (now when null). Stops with the page unless
 * `keepWhenHidden`.
 */
export function strike(frequency, { at = null, level = 0.3, decay = 7, hold = false, keepWhenHidden = false } = {}) {
    const c = audio();
    const t = Math.max(at ?? c.currentTime + 0.02, c.currentTime);
    const out = c.createGain();
    out.gain.setValueAtTime(0, t);
    out.gain.linearRampToValueAtTime(level, t + 0.004);
    // To a thousandth (-60 dB) over `decay` seconds: e^-6.9 is 0.001.
    if (!hold) out.gain.setTargetAtTime(0, t + 0.004, decay / 6.9);
    out.connect(c.destination);
    const tone = c.createOscillator();
    tone.frequency.value = frequency;
    tone.connect(out);
    tone.start(t);
    let clang = null;
    if (frequency * CLANG < Math.min(16000, c.sampleRate / 2.5)) {
        clang = c.createOscillator();
        clang.frequency.value = frequency * CLANG;
        const g = c.createGain();
        g.gain.setValueAtTime(0, t);
        g.gain.linearRampToValueAtTime(level * 0.25, t + 0.002);
        g.gain.setTargetAtTime(0, t + 0.002, 0.02);
        clang.connect(g).connect(c.destination);
        clang.start(t);
        clang.stop(t + 0.3);
    }
    const ended = new Promise((resolve) => { tone.onended = resolve; });
    // A later stop() replaces an earlier one that hasn't come yet (Web Audio's rule).
    const end = (when) => {
        try {
            tone.stop(when);
        } catch {
            // already stopped
        }
    };
    if (!hold) end(t + decay + 0.1);
    const handle = {
        ended,
        /** A held tone let go: it dies away over `decay` seconds from now, as if just struck and left. */
        ring() {
            const when = Math.max(c.currentTime, t + 0.004);
            out.gain.cancelScheduledValues(when);
            out.gain.setValueAtTime(level, when);
            out.gain.setTargetAtTime(0, when, decay / 6.9);
            end(when + decay + 0.1);
        },
        /** Fade out `after` seconds from now. */
        stop(after = 0) {
            const when = Math.max(c.currentTime + after, t);
            out.gain.cancelScheduledValues(when);
            out.gain.setTargetAtTime(0, when, 0.03);
            end(when + 0.2);
        },
    };
    return keepWhenHidden ? handle : track(handle);
}

/**
 * A plucked string at `frequency` (a tanpura's): a soft 10 ms attack, then ringing away to silence
 * over about `decay` seconds, in the plucked timbre (or any of voices.js's). `at` is a time on the
 * audio clock. Returns the voice's handle ({ stop, ended }).
 */
export function pluck(frequency, { at = null, level = 0.14, decay = 3.5, timbre = 'pluck', keepWhenHidden = false } = {}) {
    const c = audio();
    const start = Math.max(at ?? c.currentTime + 0.02, c.currentTime);
    const tone = voice(frequency, { at: start, level, timbre, attack: 0.01 / 3, release: decay / 6.9, keepWhenHidden });
    // Let go just after the attack: the release is the ring.
    tone.stop(start - c.currentTime + 0.02);
    return tone;
}

/**
 * A sung note of a set `length` (seconds) at `at` on the audio clock, in one of voices.js's timbres (the reference
 * cards' intervals and chords, played up, down or together). Returns the voice's handle ({ stop, ended }).
 */
export function note(frequency, { at = null, length = 0.8, level = 0.12, timbre = 'ah', keepWhenHidden = false } = {}) {
    const c = audio();
    const start = Math.max(at ?? c.currentTime + 0.03, c.currentTime);
    const tone = voice(frequency, { at: start, level, timbre, attack: 0.03, release: 0.12, keepWhenHidden });
    tone.stop(start + length - c.currentTime);
    return tone;
}

const buffers = new WeakMap();

/** A short sample (a Float32Array at the context's rate, such as a click) played at `at` on the audio clock. */
export function sound(samples, { at = null, level = 1 } = {}) {
    const c = audio();
    let buffer = buffers.get(samples);
    if (!buffer) {
        buffer = c.createBuffer(1, samples.length, c.sampleRate);
        buffer.getChannelData(0).set(samples);
        buffers.set(samples, buffer);
    }
    const source = c.createBufferSource();
    source.buffer = buffer;
    let out = source;
    if (level !== 1) {
        const g = c.createGain();
        g.gain.value = level;
        out = source.connect(g);
    }
    out.connect(c.destination);
    source.start(Math.max(at ?? c.currentTime, c.currentTime));
    const ended = new Promise((resolve) => { source.onended = resolve; });
    return {
        ended,
        stop() {
            try {
                source.stop();
            } catch {
                // not started or already stopped
            }
        },
    };
}
