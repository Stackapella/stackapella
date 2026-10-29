// Real voices as guides (docs/beta-singers-plan.md, "How a recorded guide plays"). A part's
// guide.recording (contract/schemas/guide.recording.json) is an AAC in an MP4 on the CDN. It's kept
// on the device (the `guides` store, by templateId@version:partKey) so an installed app plays it
// offline, and decoded at the AudioContext's rate.
//
// Sample-exact: every frame count is at the file's own rate, so it's scaled to the context's
// (decodeAudioData resamples). The MP4's edit list hides the encoder's priming; a browser that
// ignores it decodes the priming too, which is spotted by the length and skipped.

import * as db from './db.js';

const PRIMING_FRAMES = 1024; // ffmpeg's AAC encoder delay, at the file's rate

/**
 * When a part plays its recorded guide: the template's own key, full speed, the version the
 * recording belongs to (`version`, its catalogue entry's), and the singer hasn't chosen the synth.
 */
export function recordingApplies({ recording, version, template, transpose = 0, speed = 1, voice = 'real' }) {
    return !!recording && transpose === 0 && speed === 1 && voice !== 'synth' && version === template.version;
}

/**
 * The guide's audio at `sampleRate` (the AudioContext's), decoded with `decode` (the context's
 * decodeAudioData): `{ samples, sampleRate, offsetSeconds }`.
 */
export async function loadRecordedGuide(recording, { key, sampleRate, decode, fetchImpl = fetch }) {
    // The bytes are this call's own (fetched, or read from the device), so decoding may take them.
    const bytes = await fileFor(recording, key, fetchImpl);
    const buffer = await decode(bytes);
    return trimDecoded(buffer.getChannelData(0), recording, sampleRate);
}

/** The decoded samples, priming skipped and cut to the exact length, and the guide's offset. */
export function trimDecoded(decoded, recording, sampleRate) {
    const scale = sampleRate / recording.sampleRate;
    const expected = Math.round(recording.frames * scale);
    const priming = Math.round(PRIMING_FRAMES * scale);
    // Priming still there: the decode is longer by at least the encoder's delay.
    const start = decoded.length >= expected + priming ? priming : 0;
    const samples = decoded.subarray(start, start + expected); // a view: no copy of a long voice
    return { samples, sampleRate, offsetSeconds: recording.offsetFrames / recording.sampleRate, primingSkipped: start > 0 };
}

/** The file's bytes: from the device when kept, otherwise fetched and kept. */
async function fileFor(recording, key, fetchImpl) {
    const kept = await db.get('guides', key).catch(() => null);
    if (kept && kept.url === recording.url) {
        return kept.bytes;
    }
    const response = await fetchImpl(recording.url, { mode: 'cors', credentials: 'omit' });
    if (!response.ok) {
        throw new Error(`The guide ${recording.url} answered ${response.status}`);
    }
    const bytes = await response.arrayBuffer();
    // Kept on the device (IndexedDB copies it), so the caller's copy is free to be decoded.
    await db.put('guides', { url: recording.url, bytes }, key).catch((error) => console.warn('The guide was not kept on the device', error));
    return bytes;
}

/** The key a recorded guide is kept under: templateId@version:partKey, and its version's id. */
export function guideKey(templateId, version, partKey, id = null) {
    return `${templateId}@${version}:${partKey}${id ? `#${id}` : ''}`;
}

/**
 * Several voices sung together as one part (the custom choir, later): mixed into one track at
 * their offsets, each at an equal share so the part isn't louder. One voice is returned as it is.
 */
export function mixVoices(voices) {
    if (voices.length === 1) return voices[0];
    const { sampleRate } = voices[0];
    const start = Math.min(...voices.map((v) => v.offsetSeconds));
    const length = Math.max(...voices.map((v) => Math.round((v.offsetSeconds - start) * sampleRate) + v.samples.length));
    const out = new Float32Array(length);
    const share = 1 / Math.sqrt(voices.length);
    for (const v of voices) {
        const at = Math.round((v.offsetSeconds - start) * sampleRate);
        for (let i = 0; i < v.samples.length; i++) out[at + i] += v.samples[i] * share;
    }
    return { samples: out, sampleRate, offsetSeconds: start };
}
