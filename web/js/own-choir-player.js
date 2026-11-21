// The finished choir track (Hear yourself as a choir; Hillel, 2026-11-20: "some people will just want a finished
// track"): every part made from the singer's singing, played together on the choir page through the one engine on the
// audio clock (review-mix.js's way), and mixed on the device into one WAV to download, its name saying AI.

import { engine, unlock } from './audio.js';
import { guideKey, loadRecordedGuide, trimDecoded } from './recorded-guides.js';
import { encodeWav } from './wav.js';

const RATE = 48000; // every part decoded at one rate, so the download mixes them sample for sample
const PREFIX = 'choir:';

/** Decodes a ready request's parts (kept on the device after the first time): `[{ partKey, samples, sampleRate, offsetSeconds }]`. */
export async function loadChoir(request) {
    const decoder = new OfflineAudioContext(1, 1, RATE);
    const voices = [];
    for (const part of request.parts) {
        const voice = await loadRecordedGuide({ ...part, choir: true }, { key: guideKey(request.templateId, request.version, part.partKey, part.id), sampleRate: RATE, decode: (bytes) => decoder.decodeAudioData(bytes) });
        voices.push({ partKey: part.partKey, ...voice });
    }
    return voices;
}

/** Download's full-quality parts, fetched for this one file and never kept (the device keeps the listening copy). */
export async function loadChoirFull(request) {
    const decoder = new OfflineAudioContext(1, 1, RATE);
    const voices = [];
    for (const part of request.parts) {
        const response = await fetch(part.url, { mode: 'cors', credentials: 'omit' });
        if (!response.ok) throw new Error(`The choir part answered ${response.status}`);
        const buffer = await decoder.decodeAudioData(await response.arrayBuffer());
        voices.push({ partKey: part.partKey, ...trimDecoded(buffer.getChannelData(0), part, RATE) });
    }
    return voices;
}

/** Plays every part together from the start (after the tap: unlocks first); anything else in the engine is taken out. */
export async function playChoir(voices) {
    await unlock();
    [...engine.parts.keys()].forEach((id) => engine.removePart(id));
    engine.projectId = 'choir';
    const level = 1 / Math.sqrt(Math.max(1, voices.length));
    for (const v of voices) engine.addPart(PREFIX + v.partKey, engine.toBuffer(v.samples, v.sampleRate), { volume: level, pan: 0, audible: true }, v.offsetSeconds);
    engine.play({ fromSeconds: 0, leadSeconds: 0.1 });
}

/** Stops it and leaves the engine empty for the studio to load its project again. */
export function stopChoir() {
    engine.stop();
    [...engine.parts.keys()].filter((id) => id.startsWith(PREFIX)).forEach((id) => engine.removePart(id));
    engine.projectId = null;
}

/** Where it's playing, or null when stopped; `done` at the end (read once a frame from the engine's own clock). */
export function choirPosition() {
    const info = engine.positionInfo();
    return info ? { seconds: Math.max(0, info.position), done: !!info.done } : null;
}

/** The parts mixed into one mono WAV (each at the same level, never clipping), for the singer to keep. */
export function mixChoir(voices) {
    const length = Math.max(0, ...voices.map((v) => Math.round(v.offsetSeconds * RATE) + v.samples.length));
    const out = new Float32Array(length);
    const level = 1 / Math.sqrt(Math.max(1, voices.length));
    for (const v of voices) {
        const at = Math.round(v.offsetSeconds * RATE);
        for (let i = 0; i < v.samples.length; i++) out[at + i] += v.samples[i] * level;
    }
    const peak = out.reduce((most, s) => Math.max(most, Math.abs(s)), 0);
    if (peak > 0.98) for (let i = 0; i < out.length; i++) out[i] *= 0.98 / peak;
    return encodeWav([out], RATE);
}
