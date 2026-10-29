// The song builder's backing (docs/song-builder-plan.md, "The backing"; Hillel's answer: the guide
// voices sing the chords on "oo", with a keys sound one switch away, all synthesised): a section's
// progression in a key becomes alto, tenor and bass (voiced by the arranger, so the backing the
// singer hums over is the harmony others will sing), rendered on "oo" by the guide voice, and a keys
// part struck on every beat (with a lighter swung off-beat in a shuffle). Rendering is pure; the
// player loops it on the engine, on the AudioContext clock, with a bar's count-in.

import { voiceChords } from './arrange.js';
import { bandFor, renderBand } from './band.js';
import { renderBacking, renderPart } from './synth.js';
import { beatsPerBar } from './theme.js';
import { chordsFor } from './theory.js';

/**
 * A section's backing as notes: `{ chords, parts: { alto, tenor, bass }, endBeat, perBar }` for its
 * `bars` (theory.js's form) in `key` ({ tonic, mode }) and `timeSignature`.
 */
export function backingFor(bars, { key, timeSignature = '4/4', style = 'simple' }) {
    const perBar = beatsPerBar(timeSignature);
    const chords = chordsFor(bars, { tonic: key.tonic, perBar });
    const endBeat = bars.length * perBar;
    return { chords, parts: voiceChords(chords, { key, style, endBeat }), endBeat, perBar };
}

/**
 * The keys part's strikes: every beat, and in a swung feel (`swing` past 0.5) a lighter off-beat
 * at `swing` of the beat, each the chord sounding then. As renderBacking's chords (each held until the next).
 */
export function keysStrikes(chords, { endBeat, swing = 0.5 }) {
    const out = [];
    const at = (beat) => chords.findLast((c) => c.beat <= beat + 1e-6) ?? chords[0];
    for (let beat = 0; beat < endBeat - 1e-6; beat++) {
        out.push({ ...at(beat), beat });
        if (swing > 0.5) out.push({ ...at(beat + swing), beat: beat + swing });
    }
    return out;
}

/**
 * The backing as audio, one loop long (exactly `endBeat` beats at `bpm`, so it loops seamlessly):
 * the "oo" voices and the keys, as asked. Returns a Float32Array at `sampleRate`.
 */
export function renderBackingLoop(backing, { bpm, sampleRate, voices = true, keys = true, swing = 0.5 }) {
    const frames = Math.round(((backing.endBeat * 60) / bpm) * sampleRate);
    const out = new Float32Array(frames);
    const add = (samples, gain) => {
        for (let i = 0; i < Math.min(frames, samples.length); i++) out[i] += samples[i] * gain;
        // A note's tail past the loop's end sounds at its start, as it would going round.
        for (let i = frames; i < samples.length; i++) out[i - frames] += samples[i] * gain;
    };
    if (voices) {
        for (const notes of Object.values(backing.parts)) add(renderPart(notes, { bpm, sampleRate }), 0.55);
    }
    if (keys) add(renderBacking(keysStrikes(backing.chords, { endBeat: backing.endBeat, swing }), { bpm, sampleRate, endBeat: backing.endBeat, voice: 'piano' }), voices ? 0.7 : 1);
    return out;
}

/**
 * The song builder's backing (Hillel's MVP, docs/mvp-music-plan.md): the band in `style` (keys, a
 * bass, and the groove when `drums`), with the harmony on "oo" over it when `voices`. One loop long.
 */
export async function renderSongBacking(backing, { bpm, sampleRate, style = 'gentle', drums = true, voices = false, swing = 0.5 }) {
    const band = bandFor(backing.chords, { endBeat: backing.endBeat, perBar: backing.perBar, style });
    const out = await renderBand(band, { bpm, sampleRate, endBeat: backing.endBeat, style, drums, loop: true });
    if (voices) {
        const oo = renderBackingLoop(backing, { bpm, sampleRate, voices: true, keys: false, swing });
        for (let i = 0; i < Math.min(out.length, oo.length); i++) out[i] += oo[i] * 0.8;
    }
    return out;
}

