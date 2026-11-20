// The AI review's player (docs/ai-voices-build-plan.md, phase 1c): an AI guide heard alone, its written part alone (the
// practice voice, from the song's own notes), or both together to hear where it drifts. One engine, the audio clock
// (review-mix.js's way): each file decoded at its own rate, offsets in seconds, nothing on a timer.

import { engine } from '../audio.js';
import { renderGuide } from '../guides.js';
import { guideKey, loadRecordedGuide } from '../recorded-guides.js';
import { decodeAt, playFrom, position, stop, clear } from './review-mix.js';

export { playFrom, position, stop, clear };

const AI = 'ai-review:ai';
const WRITTEN = 'ai-review:written';

/** The AI guide and its written part, ready to play: `{ ai: { samples, sampleRate, offsetSeconds }, written: {...} }`. */
export async function loadPair({ template, partKey, recording }) {
    const part = template.parts.find((p) => p.key === partKey);
    if (!part) throw new Error(`The song has no ${partKey}`);
    const [ai, samples] = await Promise.all([
        loadRecordedGuide(recording, { key: guideKey(template.id, template.version, partKey, recording.id), sampleRate: recording.sampleRate, decode: (bytes) => decodeAt(bytes, recording.sampleRate) }),
        renderGuide({ templateId: template.id, templateVersion: template.version, part, tempo: template.tempo, sampleRate: engine.sampleRate }),
    ]);
    return { ai, written: { samples, sampleRate: engine.sampleRate, offsetSeconds: 0 } };
}

/**
 * Puts `hear` into the engine: 'ai' (the AI guide alone), 'written' (the practice voice on the written notes) or 'both'
 * (the AI guide, with the written part under it, a little quieter). Anything else in the engine is taken out.
 */
export function arrangePair(pair, hear) {
    [...engine.parts.keys()].forEach((id) => engine.removePart(id));
    engine.projectId = 'ai-review';
    if (hear !== 'written') engine.addPart(AI, engine.toBuffer(pair.ai.samples, pair.ai.sampleRate), { volume: 1, pan: 0, audible: true }, pair.ai.offsetSeconds);
    if (hear !== 'ai') engine.addPart(WRITTEN, engine.toBuffer(pair.written.samples, pair.written.sampleRate), { volume: hear === 'both' ? 0.55 : 1, pan: 0, audible: true }, 0);
}
