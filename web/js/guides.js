// Guide audio for template parts, rendered in a worker and kept in memory. The cache key covers
// everything that changes the sound: template version, part, key, speed and sample rate, and for an
// instrument part (js/instrument-parts.js) its samples or the synth, so the samples take over once
// they're there.

import { renderBacking, renderPart } from './synth.js';
import { sungEnd } from './scoring.js';
// The instruments load only for an instrument part: they reach the app's stores (and Vue), which the
// public pages (the landing's mixer renders guides here) don't have.

const cache = new Map();
let worker = null;
let nextId = 1;
const pending = new Map();

function getWorker() {
    if (worker === null) {
        try {
            worker = new Worker(new URL('./synth-worker.js', import.meta.url), { type: 'module' });
            worker.onmessage = (event) => {
                pending.get(event.data.id)?.(event.data.samples);
                pending.delete(event.data.id);
            };
        } catch {
            worker = false; // no module workers: render on the main thread
        }
    }
    return worker;
}

/** Renders in the worker (or here, without one), once per key. */
function rendered(key, message, here) {
    if (!cache.has(key)) {
        const w = getWorker();
        cache.set(
            key,
            new Promise((resolve) => {
                if (!w) {
                    resolve(here());
                    return;
                }
                const id = nextId++;
                pending.set(id, resolve);
                try {
                    w.postMessage({ id, ...message });
                } catch (error) {
                    console.warn('Rendering on the main thread', error);
                    pending.delete(id);
                    resolve(here());
                }
            }),
        );
    }
    return cache.get(key);
}

/** The guide audio for `part` of `template` (from `templateVersion`), as a Float32Array. */
export function renderGuide({ templateId, templateVersion, part, tempo, transpose = 0, speed = 1, sampleRate }) {
    if (part.kind === 'instrument') return renderInstrument({ templateId, templateVersion, part, tempo, transpose, speed, sampleRate });
    const key = `${templateId}@${templateVersion}:${part.key}:${transpose}:${speed}:${sampleRate}:breaths`;
    const options = { bpm: tempo, sampleRate, transpose, speed };
    // Plain copies: Vue's reactive objects can't be posted to a worker. Each note ends where its
    // singing does: before a new line (`br`) the voice breathes, the gap scoring forgives (a beta
    // singer: "in the tones, there is no place for breath"). Instruments play on.
    const notes = part.guide.notes.map((n, i, all) => ({ beat: n.beat, length: sungEnd(all, i) - n.beat, pitch: n.pitch }));
    return rendered(key, { notes, options }, () => renderPart(notes, options));
}

/**
 * An instrument part: its samples (js/instruments.js) when they're there, else the synth (the voice
 * for the piano, guitar and bass; a synthesized kit for the drums, which never move with the key).
 */
async function renderInstrument({ templateId, templateVersion, part, tempo, transpose, speed, sampleRate }) {
    const [{ instrumentOf, PIECES, synthGroove }, { loadInstrument, renderNotes }] = await Promise.all([import('./instrument-parts.js'), import('./instruments.js')]); // app-only: never on a public page
    const instrument = instrumentOf(part);
    if (!instrument) return renderPart(part.guide.notes.map((n) => ({ beat: n.beat, length: n.length, pitch: n.pitch })), { bpm: tempo, sampleRate, transpose, speed });
    const drums = instrument.role === 'groove';
    const shift = drums ? 0 : transpose;
    const played = (await loadInstrument(instrument.sample)) ? 'samples' : 'synth';
    const key = `${templateId}@${templateVersion}:${part.key}:instrument:${instrument.sample}:${played}:${shift}:${speed}:${sampleRate}`;
    if (!cache.has(key)) {
        const notes = part.guide.notes.map((n) => (drums ? { beat: n.beat, length: n.length, pitch: n.pitch, piece: PIECES[n.pitch] ?? 'hat-closed' } : { beat: n.beat, length: n.length, pitch: n.pitch }));
        const options = { bpm: tempo, sampleRate, transpose: shift, speed };
        cache.set(
            key,
            (async () => {
                const samples = played === 'samples' ? await renderNotes(instrument.sample, drums ? notes.map(({ beat, piece }) => ({ beat, piece })) : notes, options) : null;
                return samples ?? (drums ? synthGroove(notes, options) : renderPart(notes, options));
            })(),
        );
    }
    return cache.get(key);
}

/** The chords underneath a template (feature 3), as a pad or piano, as a Float32Array. */
export function renderChords({ templateId, templateVersion, chords, endBeat, tempo, transpose = 0, speed = 1, voice = 'pad', sampleRate }) {
    const key = `${templateId}@${templateVersion}:chords:${voice}:${transpose}:${speed}:${sampleRate}`;
    const options = { bpm: tempo, sampleRate, transpose, speed, voice, endBeat };
    const plain = chords.map((c) => ({ beat: c.beat, root: c.root, quality: c.quality, bass: c.bass ?? null }));
    return rendered(key, { chords: plain, options }, () => renderBacking(plain, options));
}
