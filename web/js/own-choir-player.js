// The finished choir track (Hear yourself as a choir; Hillel, 2026-11-20: "some people will just want a finished
// track"): every part made from the singer's singing, played together on the choir page through the one engine on the
// audio clock (review-mix.js's way), and mixed on the device into one WAV to download, its name saying AI.

import { engine, unlock } from './audio.js';
import { guideKey, loadRecordedGuide, trimDecoded } from './recorded-guides.js';
import { encodeWav } from './wav.js';
import { aiMark } from './ai-mark.js';

const RATE = 48000; // every part decoded at one rate, so the download mixes them sample for sample
const PREFIX = 'choir:';

/** Decodes a ready request's parts (kept on the device after the first time): `[{ partKey, samples, sampleRate, offsetSeconds }]`. */
export async function loadChoir(request) {
    const decoder = new OfflineAudioContext(1, 1, RATE);
    const voices = [];
    for (const part of request.parts) {
        const voice = await loadRecordedGuide({ ...part, choir: true }, { key: guideKey(request.templateId, request.version, part.partKey, part.id), sampleRate: RATE, decode: (bytes) => decoder.decodeAudioData(bytes) });
        voices.push({ partKey: part.partKey, seed: request.id, ...voice });
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
        voices.push({ partKey: part.partKey, seed: request.id, ...trimDecoded(buffer.getChannelData(0), part, RATE) });
    }
    return voices;
}

/** Plays every part together from the start (after the tap: unlocks first); anything else in the engine is taken out. */
/** What's playing: its voices' part keys, whether mixed as parts or rendered into one (a room), for the screen and the flows. */
export const choirPlaying = { parts: [] };

export async function playChoir(voices, sound = null, fromSeconds = 0, { tune = null } = {}) {
    await unlock();
    choirPlaying.parts = voices.map((v) => v.partKey);
    [...engine.parts.keys()].forEach((id) => engine.removePart(id));
    engine.projectId = 'choir';
    if (shaped(sound)) {
        // Choir settings' Sound (Plus): the room and each part's balance, rendered by the app's own mix (polish.js), in stereo.
        const { channels, sampleRate } = await renderChoir(voices, sound, { tune });
        const buffer = new AudioBuffer({ length: Math.max(1, channels[0].length), numberOfChannels: 2, sampleRate });
        channels.forEach((channel, i) => buffer.copyToChannel(channel, i));
        engine.addPart(PREFIX + 'mix', buffer, { volume: 1, pan: 0, audible: true }, 0);
    } else {
        const level = 1 / Math.sqrt(Math.max(1, voices.length));
        const mix = choirMix(voices, sound, { tune });
        for (const v of voices) engine.addPart(PREFIX + v.partKey, engine.toBuffer(v.samples, v.sampleRate), { volume: level * 10 ** (mix[v.partKey].db / 20), pan: mix[v.partKey].pan, audible: true }, v.offsetSeconds);
    }
    engine.play({ fromSeconds, leadSeconds: 0.1 });
}

/** Whether a sound setting changes anything (a room, or any part not at 0 dB); else the plain mix plays, as ever. */
export function shaped(sound) {
    return !!sound && ((sound.room && sound.room !== 'dry') || (sound.balance ?? []).some((b) => b.db));
}

// Where each part sits, small (the coordinator for a singer's "old over-tracking trick", 2026-11-22): the soprano a little
// left, the alto right, the tenor left of centre, the bass right of it. Other parts alternate, narrowing.
const PANS = { soprano: -0.3, alto: 0.3, tenor: -0.15, bass: 0.15 };
const VARIANCE_DB = 1.5;

/** A number in [-1, 1] from a string, the same every time (FNV-1a), so a song's mix never changes between plays. */
export function seededUnit(text) {
    let h = 0x811c9dc5;
    for (const c of String(text)) h = Math.imul(h ^ c.codePointAt(0), 0x01000193) >>> 0;
    return (h / 0xffffffff) * 2 - 1;
}

/**
 * Every choir's default mix, so copies of one voice don't sound like one track: each part a little louder or softer
 * (seeded by the song, within ±1.5 dB) and a small choir-like stereo spread. The tune stays clear: never quieter than
 * any other part, and nearer the middle. Plus's Balance overrides a part's level; the lead take and their own
 * recording take the tune's place and the middle. `{ [partKey]: { db, pan } }`.
 */
export function choirMix(voices, sound = null, { tune = null } = {}) {
    // A level set (Plus's Balance, the lead blend, their recording's level) wins; a part left at 0 keeps its variance.
    const set = Object.fromEntries((sound?.balance ?? []).filter((b) => b.db).map((b) => [b.partKey, b.db]));
    const parts = voices.map((v) => v.partKey).filter((key) => key !== 'lead-take' && key !== 'your-recording');
    const melody = tune && parts.includes(tune) ? tune : parts[0];
    const varied = Object.fromEntries(parts.map((key) => [key, VARIANCE_DB * seededUnit(`${voices[0]?.seed ?? ''}:${key}`)]));
    const loudest = Math.max(0, ...Object.values(varied));
    const mix = {};
    parts.forEach((key, i) => {
        const pan = PANS[key] ?? (i % 2 ? 1 : -1) * Math.max(0.1, 0.3 - 0.05 * Math.floor(i / 2));
        mix[key] = { db: key in set ? set[key] : key === melody ? loudest : varied[key], pan: key === melody ? pan / 2 : pan };
    });
    if (voices.some((v) => v.partKey === 'lead-take')) mix['lead-take'] = { db: set['lead-take'] ?? mix[melody]?.db ?? 0, pan: mix[melody]?.pan ?? 0 };
    if (voices.some((v) => v.partKey === 'your-recording')) mix['your-recording'] = { db: set['your-recording'] ?? 0, pan: 0 };
    return mix;
}

/** The choir through the app's mix: each part at its level and place (choirMix), then the room. `{ channels, sampleRate }`. */
export async function renderChoir(voices, sound, { tune = null } = {}) {
    const { polishRender } = await import('./polish.js');
    const mix = choirMix(voices, sound, { tune });
    const lengthSeconds = Math.max(0, ...voices.map((v) => v.offsetSeconds + v.samples.length / v.sampleRate));
    return polishRender({ voices: voices.map((v) => ({ ...v, volume: 10 ** (mix[v.partKey].db / 20), pan: mix[v.partKey].pan, spread: 0, audible: true })), lengthSeconds, sampleRate: RATE, room: sound?.room ?? 'dry' });
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

/** The parts mixed into one stereo WAV (choirMix's levels and places, never clipping), for the singer to keep. */
export async function mixChoirShaped(voices, sound, { tune = null } = {}) {
    if (!shaped(sound)) return mixChoir(voices, { tune });
    const { channels, sampleRate } = await renderChoir(voices, sound, { tune });
    return encodeWav(channels, sampleRate, { aiMark: aiMark() }); // every part here is AI-made: marked (EU AI Act Article 50)
}

export function mixChoir(voices, { tune = null } = {}) {
    const length = Math.max(0, ...voices.map((v) => Math.round(v.offsetSeconds * RATE) + v.samples.length));
    const out = [new Float32Array(length), new Float32Array(length)];
    const level = 1 / Math.sqrt(Math.max(1, voices.length));
    const mix = choirMix(voices, null, { tune });
    for (const v of voices) {
        // Equal-power panning, as the engine's StereoPannerNode plays it.
        const gain = level * 10 ** (mix[v.partKey].db / 20);
        const x = (mix[v.partKey].pan + 1) / 2;
        const [left, right] = [gain * Math.cos((x * Math.PI) / 2), gain * Math.sin((x * Math.PI) / 2)];
        const at = Math.round(v.offsetSeconds * RATE);
        for (let i = 0; i < v.samples.length; i++) {
            out[0][at + i] += v.samples[i] * left;
            out[1][at + i] += v.samples[i] * right;
        }
    }
    const peak = out.reduce((most, channel) => channel.reduce((m, s) => Math.max(m, Math.abs(s)), most), 0);
    if (peak > 0.98) for (const channel of out) for (let i = 0; i < channel.length; i++) channel[i] *= 0.98 / peak;
    return encodeWav(out, RATE, { aiMark: aiMark() });
}
