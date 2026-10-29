// Instrument parts (docs/mvp-music-plan.md, MVP 3): a template part can be played by an instrument
// instead of sung (`kind: 'instrument'`, credited "played by"). A guide is one note at a time on
// whole MIDI pitches (the template format), so each instrument plays the song's chords in a way
// that fits: the piano and the guitar break them into eighths, the bass plays the roots with a step
// into the next chord, and the drums play a groove as General MIDI drum notes (36 a kick, 38 a
// snare, 42 and 46 the hats). Which sample set plays a part comes from its key, so the part's name
// is free to be the person's own. The samples play through js/instruments.js; js/guides.js uses
// the synth when they aren't there.

import { TONES } from './arrange.js';
import { beatsPerBar } from './theme.js';

// key → the sample set (contract/instruments.md), what it plays, and where it sits.
export const INSTRUMENTS = {
    piano: { sample: 'piano', role: 'chords', base: 55, pattern: [0, 1, 2, 1] },
    guitar: { sample: 'nylon-guitar', role: 'chords', base: 48, pattern: [0, 2, 1, 2, 3, 2, 1, 2] },
    'bass-guitar': { sample: 'electric-bass', role: 'bass', base: 36 },
    drums: { sample: 'drums', role: 'groove' },
};

// The parts' English names are the file's (credits, the admin); the app words them from the key.
const NAMES = { piano: 'Piano', guitar: 'Guitar', 'bass-guitar': 'Bass guitar', drums: 'Drums' }; // i18n-ignore

// General MIDI drum notes, as the contract's pieces.
export const PIECES = { 36: 'kick', 37: 'rim', 38: 'snare', 42: 'hat-closed', 46: 'hat-open' };
const KICK = 36;
const SNARE = 38;
const HAT = 42;

/** The instrument that plays `part`, or null for a voice. */
export function instrumentOf(part) {
    return part?.kind === 'instrument' ? INSTRUMENTS[part.key] ?? null : null;
}

const pc = (n) => ((n % 12) + 12) % 12;
/** The lowest `pitchClass` at or above `floor`. */
const from = (floor, pitchClass) => floor + pc(pitchClass - floor);

/**
 * The part an instrument plays under a template (its `chords`, as the arranger leaves them), or
 * null when the template has no chords to play.
 */
export function instrumentPart(template, key) {
    const instrument = INSTRUMENTS[key];
    const chords = [...(template.chords ?? [])].sort((a, b) => a.beat - b.beat);
    if (!instrument || !chords.length) return null;
    const end = Math.max(...template.parts.flatMap((p) => (p.kind === 'instrument' ? [] : p.guide.notes.map((n) => n.beat + n.length))), chords.at(-1).beat + 1);
    const spans = chords.map((c, i) => ({ chord: c, from: c.beat, to: chords[i + 1]?.beat ?? end })).filter((s) => s.to > s.from);
    const notes =
        instrument.role === 'groove'
            ? groove(template, chords[0].beat, end)
            : instrument.role === 'bass'
              ? bassLine(spans, instrument.base)
              : broken(spans, instrument);
    if (!notes.length) return null;
    const pitches = notes.map((n) => n.pitch);
    return { key, name: NAMES[key], kind: 'instrument', range: { low: Math.min(...pitches), high: Math.max(...pitches) }, guide: { type: 'notes', notes } };
}

/** A chord's notes (its root, third, fifth and the root above) from the root nearest above `base`. */
function chordNotes(chord, base) {
    const root = from(base, chord.root);
    const tones = (TONES[chord.quality] ?? TONES.maj).slice(0, 3).map((step) => root + step);
    return [...tones, root + 12];
}

/** The chords broken into eighths, each chord's pattern from its start, its last note held. */
function broken(spans, { base, pattern }) {
    const notes = [];
    for (const { chord, from: start, to } of spans) {
        const tones = chordNotes(chord, base);
        for (let beat = start, i = 0; beat < to - 1e-9; beat += 0.5, i++) {
            const length = Math.min(0.5, to - beat);
            notes.push({ beat: round(beat), length: round(beat + 0.5 >= to - 1e-9 ? to - beat : length), pitch: tones[pattern[i % pattern.length]] });
        }
    }
    return notes;
}

/** The roots, each held until a beat before the next chord, then a step into it (from a semitone below). */
function bassLine(spans, base) {
    const notes = [];
    spans.forEach(({ chord, from: start, to }, i) => {
        const root = from(base, chord.root);
        const next = spans[i + 1]?.chord;
        if (next && to - start >= 2) {
            notes.push({ beat: round(start), length: round(to - start - 1), pitch: root });
            notes.push({ beat: round(to - 1), length: 1, pitch: from(base, next.root) - 1 });
        } else {
            notes.push({ beat: round(start), length: round(to - start), pitch: root });
        }
    });
    return notes;
}

/** A plain groove in eighths: the kick on the bar's first beat (and its middle in 4/4), the snare on the backbeats, the hat between. */
function groove(template, start, end) {
    const perBar = beatsPerBar(template.timeSignature);
    const barStart = template.pickupBeats ?? 0;
    const notes = [];
    for (let beat = start; beat < end - 1e-9; beat += 0.5) {
        const inBar = round((((beat - barStart) % perBar) + perBar) % perBar);
        const pitch = inBar === 0 || (perBar === 4 && inBar === 2) ? KICK : Number.isInteger(inBar) ? SNARE : HAT;
        notes.push({ beat: round(beat), length: round(Math.min(0.5, end - beat)), pitch });
    }
    return notes;
}

const round = (beat) => Math.round(beat * 10000) / 10000;

/**
 * The drums without their samples: a kick (a falling sine), a snare (a burst of noise) and a hat
 * (a short, bright tick), each on its beat, as a Float32Array.
 */
export function synthGroove(notes, { bpm, sampleRate, speed = 1 }) {
    const beatSeconds = 60 / bpm / speed;
    const end = Math.max(0, ...notes.map((n) => n.beat + n.length));
    const out = new Float32Array(Math.ceil((end * beatSeconds + 0.5) * sampleRate));
    let seed = 1;
    const noise = () => ((seed = (seed * 16807) % 2147483647) / 2147483647) * 2 - 1;
    for (const n of notes) {
        const at = Math.round(n.beat * beatSeconds * sampleRate);
        const piece = PIECES[n.pitch] ?? 'hat-closed';
        const seconds = piece === 'kick' ? 0.25 : piece === 'snare' ? 0.18 : 0.05;
        let phase = 0;
        let last = 0;
        for (let i = 0; i < seconds * sampleRate && at + i < out.length; i++) {
            const t = i / sampleRate;
            const decay = Math.exp(-t / (seconds / 4));
            let v;
            if (piece === 'kick') {
                phase += (2 * Math.PI * (50 + 100 * Math.exp(-t / 0.03))) / sampleRate;
                v = 0.6 * Math.sin(phase);
            } else {
                const white = noise();
                v = piece === 'snare' ? 0.35 * white : 0.2 * (white - last); // the hat: the noise's difference, bright
                last = white;
            }
            out[at + i] += v * decay;
        }
    }
    return out;
}

/** A template with these instruments' parts after its voices (any it had before are replaced). */
export function withInstruments(template, keys = []) {
    const voices = template.parts.filter((p) => p.kind !== 'instrument');
    return { ...template, parts: [...voices, ...keys.map((key) => instrumentPart({ ...template, parts: voices }, key)).filter(Boolean)] };
}
