// Practise the set's render (docs/practice-playlist-plan.md, section 3): each song's parts as Home's preview plays them
// (guide-voice.js: the real voice where the catalogue has one, else the synth), mixed with Export's practice-track
// levels (practice.js trackLevels: your part louder, or without it), then written into ONE MP3 song by song, with a
// quiet gap between. One song is held as raw audio at a time; a phone never holds the whole set that way.

import { partsFor } from './templates.js';
import { trackLevels } from './practice.js';
import { mp3Writer } from './mp3.js';
import { GAP_SECONDS } from './practice-set.js';

export const SAMPLE_RATE = 44100;
/** Mono at 128 kbps: about a megabyte a minute (the plan's 17 MB for six 3-minute songs). */
export const KBPS = 128;
const TAIL_SECONDS = 1; // the last chord rings before the gap
const PEAK = 0.89; // each song as loud as the next

/** The parts a song plays: its full voicing, every part when yours isn't in it; only parts with notes. */
function partsToPlay(template, partKey) {
    const sung = (list) => list.filter((p) => p.guide?.notes?.length);
    const parts = sung(partsFor(template));
    return partKey && !parts.some((p) => p.key === partKey) ? sung(template.parts) : parts;
}

/**
 * One song's voices (`{ key, samples, offsetSeconds, ai }`, at `sampleRate`) mixed into one mono track: `partKey`
 * louder ('louder') or left out ('without'); every part even when `partKey` is null. Each song is brought to the same
 * peak. Returns `{ samples, beatZero, ai }` (`beatZero`: where beat 0 falls, after a voice that starts early). Pure.
 */
export function mixSong(voices, partKey, kind, sampleRate) {
    const origin = Math.min(0, ...voices.map((v) => v.offsetSeconds ?? 0));
    const length = Math.max(1, ...voices.map((v) => Math.round(((v.offsetSeconds ?? 0) - origin) * sampleRate) + v.samples.length)) + Math.round(TAIL_SECONDS * sampleRate);
    const base = 1 / Math.sqrt(Math.max(1, voices.length));
    const even = new Map(voices.map((v) => [v.key, { volume: base, pan: 0, audible: true }]));
    const levels = partKey && even.has(partKey) ? trackLevels(even, partKey, kind) : even;
    const out = new Float32Array(length);
    for (const v of voices) {
        const level = levels.get(v.key);
        if (!level?.audible) continue;
        const at = Math.round(((v.offsetSeconds ?? 0) - origin) * sampleRate);
        for (let i = 0; i < v.samples.length; i++) out[at + i] += v.samples[i] * level.volume;
    }
    let peak = 0;
    for (let i = 0; i < out.length; i++) peak = Math.max(peak, Math.abs(out[i]));
    if (peak > 0) for (let i = 0; i < out.length; i++) out[i] *= PEAK / peak;
    return { samples: out, beatZero: -origin, ai: voices.some((v) => v.ai) };
}

/**
 * The set as one MP3: `songs` ([{ template, partKey }], in order), `kind` 'louder' or 'without'. `onProgress(0..1)` as
 * it goes. Returns `{ blob, songs: [{ seconds, beatZero, ai }] }`, each song's length as written (the gaps are
 * GAP_SECONDS, practice-set.js timeline()).
 */
export async function renderSet(songs, { kind = 'louder', onProgress = () => {}, sampleRate = SAMPLE_RATE } = {}) {
    const [{ renderGuide }, { partVoice }] = await Promise.all([import('./guides.js'), import('./guide-voice.js')]);
    const writer = await mp3Writer(1, sampleRate, KBPS);
    const made = [];
    for (const [i, { template, partKey }] of songs.entries()) {
        const parts = partsToPlay(template, partKey);
        const voices = [];
        // One part at a time: decoding every real voice at once can use hundreds of MB on a phone (as Home's preview).
        for (const part of parts) {
            const synth = () => renderGuide({ templateId: template.id, templateVersion: template.version, part, tempo: template.tempo, sampleRate });
            voices.push({ key: part.key, ...(await partVoice(template, part, sampleRate, synth)) });
            onProgress((i + (voices.length / Math.max(1, parts.length)) * 0.8) / songs.length);
        }
        const song = mixSong(voices, partKey, kind, sampleRate);
        voices.length = 0;
        await writer.write([song.samples]);
        if (i < songs.length - 1) await writer.write([new Float32Array(Math.round(GAP_SECONDS * sampleRate))]);
        made.push({ seconds: song.samples.length / sampleRate, beatZero: song.beatZero, ai: song.ai });
        onProgress((i + 1) / songs.length);
    }
    return { blob: writer.end(), songs: made };
}
