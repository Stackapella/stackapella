// The backing band (Hillel's MVP, docs/mvp-music-plan.md): a song's chords played by keys, a bass and
// an optional groove, in three styles, by the real instruments (js/instruments.js) when they're there
// and by the synth when they aren't (nothing said). It's the song builder's backing, Sing's chords
// underneath and Export's piano. bandFor() is the arrangement (pure: notes in beats); renderBand()
// plays it into a mono Float32Array on an OfflineAudioContext, which the engine then plays on the
// AudioContext clock. Good defaults and few options: a style, and the groove on or off.

import { PIECES, synthGroove } from './instrument-parts.js';
import { renderNotes } from './instruments.js';
import { renderBacking, voicing } from './synth.js';

export const STYLES = ['gentle', 'pop', 'swing'];
// Which sample set plays each track in each style.
const SETS = {
    gentle: { keys: 'piano', bass: 'upright-bass', drums: 'brushes' },
    pop: { keys: 'electric-piano', bass: 'electric-bass', drums: 'drums' },
    swing: { keys: 'piano', bass: 'upright-bass', drums: 'brushes' },
};
const LEVELS = { keys: 0.7, bass: 0.85, drums: 0.45 };
const SWING = 2 / 3; // a swung off-beat lands two thirds of the way through the beat
const GM = Object.fromEntries(Object.entries(PIECES).map(([note, piece]) => [piece, Number(note)]));
const pc = (n) => ((n % 12) + 12) % 12;

/** The style a stored value means: the three styles, and the old pad and piano as Gentle. */
export const styleOf = (value) => (STYLES.includes(value) ? value : 'gentle');

/** The chord sounding at a beat (each until the next). */
function chordAt(chords, beat) {
    return chords.findLast((c) => c.beat <= beat + 1e-6) ?? chords[0];
}

/** A chord's keys voicing an octave up from synth.js's (around middle C), without the bass. */
function keysOf(chord) {
    return voicing(chord).slice(1).map((p) => p + 12);
}

/** A chord's root (or its written bass) for a bass player: E1 to D#2 lifted into E2 up. */
function bassOf(chord, step = 0) {
    const note = 36 + pc((chord.bass ?? chord.root) + step);
    return note < 40 ? note + 12 : note;
}

/**
 * The band's notes for `chords` ({ beat, root, quality, bass }, each until the next) to `endBeat`,
 * `perBar` beats a bar, in `style`: `{ keys, strikes, bass, drums }`. Keys and bass are pitched notes
 * `{ beat, length, pitch, velocity }`; drums are `{ beat, piece, velocity }`; `strikes` are the keys'
 * chords for the synth (renderBacking's form).
 */
export function bandFor(chords, { endBeat, perBar = 4, style = 'gentle' }) {
    const keys = [];
    const strikes = [];
    const bass = [];
    const drums = [];
    if (!chords?.length) return { keys, strikes, bass, drums };
    const s = styleOf(style);
    const strike = (beat, length, velocity) => {
        const chord = chordAt(chords, beat);
        strikes.push({ ...chord, beat });
        for (const pitch of keysOf(chord)) keys.push({ beat, length, pitch, velocity });
    };
    for (let bar = 0; bar < endBeat - 1e-6; bar += perBar) {
        const last = Math.min(endBeat, bar + perBar);
        // A chord change inside the bar (two to a bar, say) splits it.
        const changes = [bar, ...chords.map((c) => c.beat).filter((b) => b > bar + 1e-6 && b < last - 1e-6)];
        changes.forEach((from, i) => {
            const to = changes[i + 1] ?? last;
            const span = to - from;
            const chord = chordAt(chords, from);
            const next = chordAt(chords, to) ?? chord;
            if (s === 'gentle') {
                strike(from, span * 0.95, 64);
                bass.push({ beat: from, length: span * 0.9, pitch: bassOf(chord), velocity: 80 });
            } else if (s === 'pop') {
                for (let b = from; b < to - 1e-6; b += 1) strike(b, 0.8, b === bar ? 92 : 74);
                for (let b = from; b < to - 1e-6; b += 1) bass.push({ beat: b, length: 0.85, pitch: bassOf(chord), velocity: b === bar ? 96 : 82 });
            } else {
                // Swing: the keys comp on the back beats; the bass walks root, third or fifth, fifth,
                // and a step into the next chord's root.
                for (let b = from; b < to - 1e-6; b += 1) if ((b - bar) % 2 === 1 || span < 2) strike(b, 0.45, 78);
                const walk = [0, (chord.quality ?? 'maj').startsWith('min') ? 3 : 4, 7];
                for (let b = from, k = 0; b < to - 1e-6; b += 1, k++) {
                    const approach = b + 1 >= to - 1e-6 && span > 1;
                    const pitch = approach ? bassOf(next) + (pc(next.root - chord.root) <= 6 ? -1 : 1) : bassOf(chord, walk[k % walk.length]);
                    bass.push({ beat: b, length: 0.9, pitch, velocity: 84 });
                }
            }
        });
        // The groove.
        for (let b = bar; b < last - 1e-6; b += 1) {
            const beatInBar = b - bar;
            if (s === 'pop') {
                if (beatInBar % 2 === 0) drums.push({ beat: b, piece: 'kick', velocity: 100 });
                else drums.push({ beat: b, piece: 'snare', velocity: 92 });
                drums.push({ beat: b, piece: 'hat-closed', velocity: 70 }, { beat: b + 0.5, piece: 'hat-closed', velocity: 55 });
            } else if (s === 'swing') {
                drums.push({ beat: b, piece: 'brush-sweep', velocity: 60 });
                if (beatInBar % 2 === 1) drums.push({ beat: b, piece: 'brush-tap', velocity: 85 });
                drums.push({ beat: b + SWING, piece: 'brush-tap', velocity: 45 });
            } else if (beatInBar % 2 === 1) {
                drums.push({ beat: b, piece: 'brush-tap', velocity: 60 });
            }
        }
    }
    return { keys, strikes, bass, drums };
}

/**
 * The band as audio: a mono Float32Array at `sampleRate`, from beat 0. Each track plays on its
 * sample set when it's there, else the synth (the keys as a struck piano with the bass in it, the
 * groove on synth.js's small kit). `tracks` picks which play (the groove only with `drums`).
 * `loop`: exactly `endBeat` long, a tail past the end sounding at the start, as it does going round.
 */
export async function renderBand(band, { bpm, sampleRate, endBeat, style = 'gentle', transpose = 0, speed = 1, drums = true, tracks = ['keys', 'bass', 'drums'], loop = false, level = 1 }) {
    const s = styleOf(style);
    const beatSeconds = 60 / bpm / speed;
    const length = Math.round(endBeat * beatSeconds * sampleRate);
    const out = new Float32Array(loop ? length : length + Math.round(2 * sampleRate));
    const add = (samples, gain) => {
        if (!samples) return;
        for (let i = 0; i < samples.length; i++) {
            const at = loop && i >= length ? i - length : i;
            if (at < out.length) out[at] += samples[i] * gain * level;
        }
    };
    const options = { bpm, sampleRate, transpose, speed, endBeat };
    const keys = tracks.includes('keys') ? await renderNotes(SETS[s].keys, band.keys, options) : null;
    if (keys) add(keys, LEVELS.keys);
    const bass = tracks.includes('bass') ? await renderNotes(SETS[s].bass, band.bass, options) : null;
    if (bass) add(bass, LEVELS.bass);
    // No samples for the keys: the synth's struck piano, which carries the bass too when that has none.
    if (!keys && tracks.includes('keys')) add(renderBacking(band.strikes, { bpm, sampleRate, endBeat, transpose, speed, voice: 'piano' }), bass ? 0.45 : 0.6);
    if (drums && tracks.includes('drums') && band.drums.length) {
        const sampled = await renderNotes(SETS[s].drums, band.drums, { ...options, transpose: 0 });
        if (sampled) add(sampled, LEVELS.drums);
        else add(synthGroove(band.drums.filter((d) => GM[d.piece] || d.piece === 'brush-tap').map((d) => ({ beat: d.beat, length: 0.25, pitch: GM[d.piece] ?? GM.snare, velocity: d.velocity })), { bpm, sampleRate, speed }), LEVELS.drums * 0.6);
    }
    return out;
}
