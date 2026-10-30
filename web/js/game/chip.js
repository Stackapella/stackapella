// The 16-bit cues (docs/game-elements-plan.md; the proposal's board 10): short original chip
// phrases in square and triangle waves for the moments around singing. Game sounds is off until
// chosen in Settings. Every note is scheduled on the AudioContext clock, quietly under the app's
// other sounds, and a cue never plays while the engine plays anything (a take, a scored turn, a
// count-in): the mic must hear only the voice; one already scheduled stops if the engine starts. The
// count-in itself can be chip blips (chipCountIn), played by the engine in place of its clicks.

import { engine } from '../audio.js';
import { effect } from './effects.js';

// [midi, start s, length s, wave]: original phrases, none copied from a game.
export const CUES = {
    cursor: [[88, 0, 0.04, 'square']],
    select: [[83, 0, 0.07, 'square'], [88, 0.07, 0.16, 'square']],
    tick: [[96, 0, 0.025, 'square']],
    tallyEnd: [[96, 0, 0.025, 'square'], [96, 0.07, 0.025, 'square'], [91, 0.14, 0.14, 'triangle']],
    star1: [[84, 0, 0.12, 'square']],
    star2: [[88, 0, 0.12, 'square']],
    star3: [[91, 0, 0.24, 'square']],
    record: [[72, 0, 0.09, 'square'], [76, 0.1, 0.09, 'square'], [79, 0.2, 0.09, 'square'], [84, 0.3, 0.5, 'square'], [48, 0.3, 0.5, 'triangle']],
    levelUp: [[72, 0, 0.06, 'square'], [79, 0.06, 0.06, 'square'], [84, 0.12, 0.06, 'square'], [88, 0.18, 0.06, 'square'], [91, 0.24, 0.06, 'square'], [96, 0.3, 0.3, 'square']],
    gotIt: [[79, 0, 0.1, 'square'], [81, 0.11, 0.1, 'square'], [83, 0.22, 0.1, 'square'], [86, 0.33, 0.45, 'square'], [55, 0.33, 0.45, 'triangle']],
    challenger: [[64, 0, 0.1, 'square'], [71, 0.14, 0.22, 'square']],
};

const LEVEL = 0.06; // a square wave is loud for its size: well under the app's sounds (0.4)
const TRIANGLE = 1.6; // a triangle is softer: lifted to sit with the squares

const hz = (midi) => 440 * 2 ** ((midi - 69) / 12);

// Cues scheduled and not yet over. A cue is short, but if a take or playback starts while one is still
// to sound, it stops then: the mic hears only the voice. Watched per frame while any is waiting.
const waiting = new Set();
let watching = false;
function watch() {
    const now = engine.ctx?.currentTime ?? Infinity;
    for (const sound of waiting) {
        if (engine.playing) sound.stop();
        else if (now > sound.end) waiting.delete(sound);
    }
    watching = waiting.size > 0;
    if (watching) requestAnimationFrame(watch);
}

/** Whether a cue may sound now: Game sounds on, the context running, and nothing playing. */
export function cueAllowed() {
    return effect('sounds') && !!engine.ctx && engine.ctx.state === 'running' && !engine.playing;
}

/** How long `name` lasts, in seconds. */
export const cueLength = (name) => Math.max(...CUES[name].map(([, start, length]) => start + length));

/**
 * Plays cue `name` at `at` (the audio clock's seconds), else now. Returns `{ end, stop }` (the
 * audio-clock time it ends, and a stop for a moment skipped before it sounds), or null when it may
 * not sound.
 */
export function cue(name, { at = null } = {}) {
    if (!CUES[name]) throw new Error(`No cue called ${name}`);
    if (!cueAllowed()) return null;
    const ctx = engine.ctx;
    const t0 = Math.max(ctx.currentTime + 0.02, at ?? 0);
    const master = ctx.createGain();
    master.gain.value = LEVEL;
    master.connect(engine.playBus);
    const notes = [];
    for (const [midi, start, length, wave] of CUES[name]) {
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        const peak = wave === 'triangle' ? TRIANGLE : 1;
        osc.type = wave;
        osc.frequency.setValueAtTime(hz(midi), t0 + start);
        gain.gain.setValueAtTime(0, t0 + start);
        gain.gain.linearRampToValueAtTime(peak, t0 + start + 0.005);
        gain.gain.setValueAtTime(peak, t0 + start + length * 0.7);
        gain.gain.linearRampToValueAtTime(0, t0 + start + length);
        osc.connect(gain).connect(master);
        osc.start(t0 + start);
        osc.stop(t0 + start + length + 0.02);
        notes.push(osc);
    }
    const end = t0 + cueLength(name);
    // The master is let go when its last note ends (an ended event, not a timer).
    const last = ctx.createConstantSource();
    last.offset.value = 0;
    last.connect(master);
    last.onended = () => master.disconnect();
    last.start(t0);
    last.stop(end + 0.05);
    const sound = {
        end,
        stop() {
            waiting.delete(sound);
            master.gain.value = 0;
            for (const osc of notes) osc.stop();
        },
    };
    waiting.add(sound);
    if (!watching) {
        watching = true;
        requestAnimationFrame(watch);
    }
    return sound;
}

// The count-in's chip blips (Game sounds on): the engine schedules them in place of its two clicks on
// the audio clock, for the count-in only, so nothing chip-like sounds once the take starts. About the
// click's own energy (a 0.7 sine gone in 30 ms), so the count-in is no louder than before.
const BLIP_LEVEL = 0.22;
const BLIP_DECAY = 40; // per second
const countIns = new Map(); // by sample rate

/** A chip blip as samples: a square wave at `midi`, decaying over `seconds`. Pure. */
export function blip(sampleRate, midi, seconds, level = BLIP_LEVEL) {
    const out = new Float32Array(Math.round(seconds * sampleRate));
    const period = sampleRate / hz(midi);
    for (let i = 0; i < out.length; i++) {
        const fade = Math.min(1, (out.length - i) / (0.004 * sampleRate)); // no click at its end
        out[i] = (i % period < period / 2 ? level : -level) * Math.exp((-i / sampleRate) * BLIP_DECAY) * fade;
    }
    return out;
}

/**
 * Game mode's count-in, with Game sounds on: `{ accent, normal }` buffers for the engine's count-in
 * (a high blip on the bar line, a lower one on the other beats), else null for its plain clicks.
 */
export function chipCountIn() {
    if (!effect('sounds') || !engine.ctx) return null;
    const rate = engine.ctx.sampleRate;
    if (!countIns.has(rate)) countIns.set(rate, { accent: engine.toBuffer(blip(rate, 84, 0.07), rate), normal: engine.toBuffer(blip(rate, 79, 0.05), rate) });
    return countIns.get(rate);
}

/** A run of cues one after another (a tally's ticks, then its end): each starts as the one before ends. */
export function cues(names, { at = null, gap = 0.04 } = {}) {
    let next = at;
    for (const name of names) {
        const sound = cue(name, { at: next });
        if (sound === null) return null;
        next = sound.end + gap;
    }
    return next;
}
