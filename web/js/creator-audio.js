// The template creator's sound (docs/template-creator-plan.md): the click to check a tempo, a line
// sung to the click (a bar counted in, then a bar for a pickup before its first downbeat), the live
// reading for the pitch line, and hearing notes, a take or the whole template. Everything plays on
// the engine's audio clock; the screens only watch the position each frame (CLAUDE.md: no timers
// for audio). The creator's parts are its own in the engine, cleared when it's done.

import { heardTime } from '../engine/clock.js';
import { audio, engine, leadSeconds, unlock } from './audio.js';
import { isSilent } from './dsp.js';
import { micNeedsCheck, readyMic } from './mic-check.js';
import { trackFrame } from './pitchtrack.js';
import { renderPart } from './synth.js';
import { beatsPerBar } from './theme.js';
import { partsFor } from './templates.js';

const PREFIX = 'creator:';
const MAX_BARS = 24; // a line longer than this stops by itself
const SUSTAIN_FRAMES = 6; // a pitch held this many frames (about 100 ms) is singing, not the click

/** Takes the engine's parts away (the studio's, or the creator's last), so only ours play. */
function clearParts() {
    for (const id of [...engine.parts.keys()]) engine.removePart(id);
    engine.projectId = null; // the studio loads its own parts again next time
}

/**
 * Plays the click for two bars at `tempo`. Returns `{ stop, done }`: `done` resolves when the
 * clicks have played (watched on the audio clock).
 */
export async function hearClick({ tempo, timeSignature }) {
    await unlock();
    await engine.stop();
    clearParts();
    const perBar = beatsPerBar(timeSignature);
    engine.play({ fromSeconds: 0, bpm: tempo, beatsPerBar: perBar, countInBeats: perBar * 2, metronome: false });
    return watch(() => (engine.positionSeconds() ?? 1) >= 0);
}

/** Watches the audio clock each frame until `finished()`; `{ stop, done }`. */
function watch(finished, onFrame = () => {}) {
    let frame = 0;
    let resolve;
    const done = new Promise((r) => (resolve = r));
    const stop = async () => {
        cancelAnimationFrame(frame);
        await engine.stop();
        resolve();
    };
    const tick = () => {
        onFrame();
        if (finished()) stop();
        else frame = requestAnimationFrame(tick);
    };
    frame = requestAnimationFrame(tick);
    return { stop, done };
}

/**
 * Opens the mic (from a tap), with the mic check the first time (js/mic-check.js). Resolves false
 * when the browser's question or the check was closed. Throws as openMic does, for the screen to word.
 */
export async function readyToSing() {
    await unlock();
    if (audio.mic && !micNeedsCheck()) return true;
    return !!(await readyMic());
}

/**
 * Records one line: a bar counted in, then recording from the lead-in bar (where a pickup goes) on,
 * the click going, until `stop()` or a long silence after singing. `onFrame({ beat, reading })`
 * runs each frame: `beat` from the lead-in bar's start (negative during the count-in), `reading` the
 * live pitch (`{ hz, clarity, loudness }`) once it has held for a few frames (so the click, heard
 * through speakers, isn't singing), else null. Resolves `done` with `{ samples, sampleRate, silent }`,
 * or null when stopped before it began.
 */
export function recordLine({ tempo, timeSignature, onFrame }) {
    const perBar = beatsPerBar(timeSignature);
    const beatSeconds = 60 / tempo;
    clearParts();
    engine.play({ fromSeconds: 0, bpm: tempo, beatsPerBar: perBar, pickupBeats: 0, countInBeats: perBar, metronome: true, record: true, latencyFrames: audio.latencyFrames ?? 0, leadSeconds: leadSeconds() });
    let heard = false;
    let lastVoiced = 0;
    let run = 0; // frames voiced in a row: a click is a blip, singing holds
    let stopping = false;
    let frame = 0;
    let finish;
    const done = new Promise((resolve) => (finish = resolve));
    const end = async () => {
        if (stopping) return;
        stopping = true;
        cancelAnimationFrame(frame);
        const take = await engine.stop();
        if (!take || take.samples.length < take.sampleRate * 0.3) return finish(null);
        finish({ samples: take.samples, sampleRate: take.sampleRate, silent: isSilent(take.samples), interrupted: !!take.interrupted });
    };
    const tick = () => {
        const seconds = engine.positionSeconds();
        if (seconds === null) return end(); // stopped from elsewhere (an interruption)
        const beat = seconds / beatSeconds;
        const input = engine.inputFrame();
        const reading = input ? trackFrame(input, engine.sampleRate) : null;
        run = reading?.hz > 0 ? run + 1 : 0;
        const singing = run >= SUSTAIN_FRAMES;
        if (singing && beat >= 0) {
            heard = true;
            lastVoiced = beat;
        }
        onFrame({ beat, reading: singing ? reading : null });
        // Done: a bar and a half quiet after singing (at least two seconds), or very long.
        const quietBeats = Math.max(perBar * 1.5, 2 / beatSeconds);
        if ((heard && beat - lastVoiced > quietBeats) || beat > perBar * MAX_BARS) return end();
        frame = requestAnimationFrame(tick);
    };
    frame = requestAnimationFrame(tick);
    return { stop: end, done };
}

/** Plays notes ({ beat, length, pitch }) with the synth, at once, from their first beat. */
export async function hearNotes(notes, { tempo }) {
    if (!notes.length) return;
    await unlock();
    const first = notes[0].beat;
    const samples = renderPart(notes.map((n) => ({ beat: n.beat - first, length: n.length, pitch: n.pitch })), { bpm: tempo, sampleRate: engine.sampleRate });
    engine.preview(samples, engine.sampleRate);
}

/** Plays a take as it was sung. */
export async function hearTake(take) {
    await unlock();
    engine.preview(take.samples, take.sampleRate);
}

/**
 * Plays a template from `fromBeat` with the synth: every part, or `only` (part keys), the rest
 * quieter, or muted ones left out. Returns `{ stop, done, beat() }`; `beat()` is where it is now.
 */
export async function playTemplate(template, { fromBeat = 0, toBeat = null, singers = null, muted = [], focus = null, onFrame = () => {} } = {}) {
    await unlock();
    await engine.stop();
    clearParts();
    const beatSeconds = 60 / template.tempo;
    let end = 0;
    for (const part of partsFor(template, singers ?? undefined)) {
        if (!part.guide.notes.length) continue;
        const samples = renderPart(part.guide.notes, { bpm: template.tempo, sampleRate: engine.sampleRate });
        engine.setPart(PREFIX + part.key, samples, engine.sampleRate, { volume: focus && focus !== part.key ? 0.45 : 0.9, pan: 0, audible: !muted.includes(part.key), offsetSeconds: 0 });
        end = Math.max(end, ...part.guide.notes.map((n) => n.beat + n.length));
    }
    engine.play({ fromSeconds: fromBeat * beatSeconds, bpm: template.tempo, beatsPerBar: beatsPerBar(template.timeSignature), pickupBeats: template.pickupBeats });
    const beat = () => (engine.positionSeconds() ?? 0) / beatSeconds;
    // Just a stretch (`toBeat`): it stops there, with a moment for the last chord to ring.
    const last = toBeat === null ? end + 0.5 : Math.min(end + 0.5, toBeat + 0.25);
    return { ...watch(() => beat() >= last, () => onFrame(beat())), beat };
}

/**
 * Plays a backing loop (js/backing.js) round and round, on the engine's clock: a bar of clicks
 * first (unless `countIn` is false: a preview), then the loop; `record` takes the mic too. Returns
 * `{ stop, beat() }`: `stop` resolves with the take when recording; `beat()` is the beats since the
 * loop first started, counting every time round (negative in the count-in), read off the audio clock.
 */
export async function playBackingLoop(samples, { bpm, perBar, beats, record = false, countIn = true }) {
    await unlock();
    await engine.stop();
    clearParts();
    engine.setPart(PREFIX + 'backing', samples, engine.sampleRate, { volume: 0.9, pan: 0, audible: true, offsetSeconds: 0 });
    const loopSeconds = (beats * 60) / bpm;
    engine.play({ fromSeconds: 0, bpm, beatsPerBar: perBar, countInBeats: countIn ? perBar : 0, metronome: false, loop: { startSeconds: 0, endSeconds: loopSeconds }, record, latencyFrames: record ? audio.latencyFrames ?? 0 : 0, leadSeconds: leadSeconds() });
    const playing = engine.playing;
    const beat = () => (engine.playing === playing ? ((heardTime(engine.ctx) - playing.startTime) * bpm) / 60 : null);
    return { stop: () => engine.stop(), beat };
}

/** The creator is done with the engine: its parts go, anything playing stops. */
export async function leaveCreator() {
    await engine.stop();
    for (const id of [...engine.parts.keys()]) if (id.startsWith(PREFIX)) engine.removePart(id);
}
