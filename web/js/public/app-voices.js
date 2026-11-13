// The app's voices for a public page's player (the landing's lane and its four-voice mixer; Hillel: "as people
// record it the audio should update everywhere"): a song's current guides from the public catalogue
// (templates.catalogue, the source Sing uses), and a real voice decoded as the app decodes one (recorded-guides.js).
// No Vue, no import map (public_imports_check).

import { recordingApplies, trimDecoded } from '../recorded-guides.js';

// The song's own answer from the public catalogue (templates.show: its template and its voices), read once a page.
const answers = new Map();
function songAnswer(slug) {
    if (!answers.has(slug)) {
        const answer = (async () => {
            const response = await fetch('/api/templates/{id}'.replace('{id}', encodeURIComponent(slug)), { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error(`The song answered ${response.status}`);
            return (await response.json()).data;
        })();
        answer.catch(() => answers.delete(slug));
        answers.set(slug, answer);
    }
    return answers.get(slug);
}

/**
 * The song's current guides, by part key (the same voices Sing uses), only where a real voice belongs to this version
 * of the song; {} when it has none, null when neither the song nor the catalogue can be read. From the song's own
 * answer (a few KB, read anyway for the whole song's notes), else the whole catalogue (a Plus song, signed out, is
 * refused its own: Slow 4G, 2026-11-13: the catalogue alone was 1.9 s of every press's 1.5 s wait).
 */
export async function catalogueGuides(id, template) {
    try {
        const answer = await songAnswer(id);
        const guides = {};
        for (const voice of answer.voices ?? []) {
            if (voice.default === true && recordingApplies({ recording: voice, version: answer.template?.version, template })) guides[voice.partKey] = voice;
        }
        return guides;
    } catch {
        return listedGuides(id, template);
    }
}

async function listedGuides(id, template) {
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

/** A library song's template as the app reads it (templates.show; a Plus song, signed out, is refused: 403). */
export async function songTemplate(slug) {
    return (await songAnswer(slug)).template;
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
