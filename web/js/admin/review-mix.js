// The review's player (docs/beta-singers-plan.md, "The review queue"): a beta take in the mix with
// the template's other parts, as accepted voices or the synth, A/B against this part's synth
// guide, with the light fixes heard as they're set. Everything plays through the one engine, on
// the audio clock. Each file is decoded at its own rate, so nothing is resampled before playing,
// and every offset is in seconds.

import { engine, unlock } from '../audio.js';
import { renderGuide } from '../guides.js';
import { guideKey, loadRecordedGuide } from '../recorded-guides.js';
import { guideGain } from '../levels.js';

export { guideGain as takeGain, peakDbfs } from '../levels.js';

const TAKE = 'review:take';
const FADE_SECONDS = 0.01;

/** Decodes audio at `sampleRate` without an AudioContext (no tap needed, no resampling). */
export function decodeAt(bytes, sampleRate) {
    return new OfflineAudioContext(1, 1, sampleRate).decodeAudioData(bytes);
}

/**
 * The master WAV, as the singer sent it: `{ samples, sampleRate }`. A presigned address that has
 * expired answers 403; the caller asks the server for a fresh one.
 */
export async function loadMaster(submission, url, fetchImpl = fetch) {
    const response = await fetchImpl(url, { mode: 'cors', credentials: 'omit' });
    if (!response.ok) throw new Error(`The master answered ${response.status}`);
    const buffer = await decodeAt(await response.arrayBuffer(), submission.sampleRate);
    return { samples: buffer.getChannelData(0).subarray(0, submission.frames), sampleRate: submission.sampleRate };
}

/**
 * The other voices: each part's accepted default (`otherParts`) where there is one, and the synth
 * for every part (this one's too, for the A/B). `{ real: Map, synth: Map, failed: [partKey] }`.
 */
export async function loadVoices({ template, otherParts, version }) {
    const real = new Map();
    const failed = [];
    await Promise.all(
        otherParts.map(async (recording) => {
            try {
                const voice = await loadRecordedGuide(recording, { key: guideKey(template.id, version, recording.partKey, recording.id), sampleRate: recording.sampleRate, decode: (bytes) => decodeAt(bytes, recording.sampleRate) });
                real.set(recording.partKey, { ...voice, credit: recording.credit });
            } catch (error) {
                console.error(`The ${recording.partKey} voice failed to load`, error);
                failed.push(recording.partKey);
            }
        }),
    );
    const sampleRate = engine.sampleRate;
    const synth = new Map();
    await Promise.all(
        template.parts.map(async (part) => {
            synth.set(part.key, { samples: await renderGuide({ templateId: template.id, templateVersion: template.version, part, tempo: template.tempo, sampleRate }), sampleRate, offsetSeconds: 0 });
        }),
    );
    return { real, synth, failed };
}

/**
 * The take as it will be published (server/app/Jobs/PublishGuide.php): the trims silenced with
 * short fades, so what's heard is what learners get. The start trim is song time (where the guide
 * starts, after the nudge), the end trim is cut from the take's own end. `adjust` is the
 * submission's ({ offsetMs, trimStartMs, trimEndMs }).
 */
export function trimmed(samples, sampleRate, { offsetMs = 0, trimStartMs = 0, trimEndMs = 0 }) {
    const out = Float32Array.from(samples);
    const start = Math.max(0, Math.min(out.length, Math.round(((trimStartMs - offsetMs) / 1000) * sampleRate)));
    const end = Math.max(start, out.length - Math.round((trimEndMs / 1000) * sampleRate));
    const fade = Math.round(FADE_SECONDS * sampleRate);
    out.fill(0, 0, start);
    out.fill(0, end);
    for (let i = 0; i < fade; i++) {
        if (start + i < end) out[start + i] *= i / fade;
        if (end - 1 - i >= start) out[end - 1 - i] *= i / fade;
    }
    return out;
}

/**
 * Puts what's chosen into the engine: the take (or this part's synth, for B), and the others as
 * `others` says ('real': accepted voices, else the synth; 'synth'; or 'off'). The engine's other
 * parts (a project's) are taken out; the studio reloads its own when it next plays.
 */
export function arrange({ take, sampleRate, adjust, loudnessDbfs, peak, partKey, voices, others, hear }) {
    [...engine.parts.keys()].forEach((id) => engine.removePart(id));
    engine.projectId = 'review';
    if (hear === 'take') {
        engine.addPart(TAKE, takeBuffer(take, sampleRate, adjust), { volume: guideGain(loudnessDbfs, adjust.gainDb, peak), pan: 0, audible: true }, (adjust.offsetMs ?? 0) / 1000);
    } else {
        const synth = voices.synth.get(partKey);
        if (synth) engine.addPart(`review:synth:${partKey}`, engine.toBuffer(synth.samples, synth.sampleRate), { volume: 1, pan: 0, audible: true }, 0);
    }
    if (others === 'off') return;
    for (const [key, synth] of voices.synth) {
        if (key === partKey) continue;
        const voice = others === 'real' ? voices.real.get(key) ?? synth : synth;
        engine.addPart(`review:${key}`, engine.toBuffer(voice.samples, voice.sampleRate), { volume: 0.8, pan: 0, audible: true }, voice.offsetSeconds);
    }
}

let kept = null; // the last trimmed take, so a change of level or of the other parts doesn't copy it again

function takeBuffer(take, sampleRate, { offsetMs = 0, trimStartMs = 0, trimEndMs = 0 }) {
    const key = `${offsetMs}:${trimStartMs}:${trimEndMs}`;
    if (kept?.take !== take || kept.key !== key) kept = { take, key, buffer: engine.toBuffer(trimmed(take, sampleRate, { offsetMs, trimStartMs, trimEndMs }), sampleRate) };
    return kept.buffer;
}

/** Sets the take's level while it plays. */
export function setTakeLevel(volume) {
    const part = engine.parts.get(TAKE);
    if (part) engine.updatePart(TAKE, { volume, pan: 0, audible: true, offsetSeconds: part.offsetSeconds });
}

/** Starts playing from `fromSeconds` (after a tap: unlocks first). */
export async function playFrom(fromSeconds) {
    await unlock();
    engine.play({ fromSeconds, leadSeconds: 0.1 });
}

export function stop() {
    return engine.stop();
}

/** Where playback is, in seconds, or null when stopped; and whether it has reached the end. */
export function position() {
    const info = engine.positionInfo();
    return info ? { seconds: Math.max(0, info.position), done: !!info.done } : null;
}

/** Leaves the engine empty, for the studio to load its project again. */
export function clear() {
    kept = null;
    engine.stop();
    [...engine.parts.keys()].forEach((id) => engine.removePart(id));
    engine.projectId = null;
}
