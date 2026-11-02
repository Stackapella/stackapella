// The app's voices for a public page's player (the landing's lane and its four-voice mixer; Hillel: "as people
// record it the audio should update everywhere"): a song's current guides from the public catalogue
// (templates.catalogue, the source Sing uses), and a real voice decoded as the app decodes one (recorded-guides.js).
// No Vue, no import map (public_imports_check).

import { recordingApplies, trimDecoded } from '../recorded-guides.js';

/**
 * The song's current guides, by part key, from the public catalogue (the same source Sing uses), only where a real
 * voice belongs to this version of the song; {} when it has none, null when the catalogue can't be read.
 */
export async function catalogueGuides(id, template) {
    try {
        const response = await fetch('/api/templates', { headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error(`The catalogue answered ${response.status}`);
        const entry = (await response.json()).data?.find((t) => t.id === id);
        const guides = {};
        for (const part of entry?.parts ?? []) {
            if (recordingApplies({ recording: part.guide, version: entry.version, template })) guides[part.key] = part.guide;
        }
        return guides;
    } catch (error) {
        console.warn('No catalogue: the bundled take sings', error);
        return null;
    }
}

/** A real voice at `context`'s rate: `{ samples, offsetSeconds }` (its samples start that far into the song). */
export async function realVoice(guide, context) {
    const decoded = await context.decodeAudioData(await (await fetch(guide.url)).arrayBuffer());
    const { samples, offsetSeconds } = trimDecoded(decoded.getChannelData(0), guide, context.sampleRate);
    return { samples, offsetSeconds };
}

/** `samples` placed at `offsetSeconds` into the song (silence before, or the start cut), `length` samples long. */
export function placed(samples, offsetSeconds, rate, length = null) {
    const lead = Math.max(0, Math.round(offsetSeconds * rate));
    const skip = Math.max(0, Math.round(-offsetSeconds * rate));
    const body = samples.subarray(Math.min(skip, samples.length));
    const out = new Float32Array(length ?? lead + body.length);
    out.set(body.subarray(0, Math.max(0, out.length - lead)), Math.min(lead, out.length));
    return out;
}
