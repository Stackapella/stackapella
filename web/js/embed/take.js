// A take for the embedded lane (js/embed/lane.js): what a voice did, as the Sing lane draws it.
// A take file (web/templates/takes/<song>-<part>.json) names the song, the part and how far it goes; a real
// singer's take adds its `audio` and its `track` (the pitch and loudness every 10 ms, made from the
// recording with the app's own tracker), and nothing else changes. Without them, the voice is made
// here from the part's notes, sung the way a person sings: scooping up into a phrase, gliding
// between notes, vibrato blooming on long notes, a slow drift, loudness that swells on long notes
// and dips between them, breaths between lines, and any note whose beat is in `flat` (beats, not
// note numbers) sung 60 cents under (a miss). The content check keeps a miss to a short passing note.
// With `human` (the landing's take), the vibrato starts at its own moment and wanders, the scoops
// come now and then, and the sound has breath and air under it (js/synth.js's human voice).
// Pure: no audio, no DOM (tests/embed.test.js).

import { bloom, seeded } from '../synth.js';

const STEP = 0.01; // seconds between track points
const TUNE = 0.35; // semitones either side that count as in tune
const HIT_SHARE = 0.6; // a note is hit when this much of it was in tune

/** The voice for `notes` ({ beat, length, pitch, br }) at `tempo`: [{ seconds, midi, level }], silent points left out. */
export function expressiveTrack(notes, { tempo, flat = [], human = null }) {
    const beat = 60 / tempo;
    const track = [];
    const random = seeded(notes.length * 97 + Math.round(tempo));
    const between = (lo, hi) => lo + (hi - lo) * random();
    // Each note's own vibrato (when, how fast, how wide) and whether it scoops: a person's, not a machine's.
    const timed = notes.map((n, i) => ({ ...n, start: n.beat * beat, end: (n.beat + n.length) * beat, flat: flat.includes(n.beat), phraseStart: i === 0 || n.br || n.beat - (notes[i - 1].beat + notes[i - 1].length) > 0.5,
        vibrato: human ? { after: between(0.22, 0.4), rate: between(5.1, 5.8), depth: between(0.22, 0.38), wander: random() * 6.28 } : { after: 0.3, rate: 5.6, depth: 0.33, wander: 0 },
        scoop: !human || i === 0 || random() < 0.45 ? 1 : 0 }));
    const last = timed.at(-1);
    for (let t = 0; last && t < last.end; t += STEP) {
        const i = timed.findIndex((n) => t >= n.start && t < n.end);
        if (i < 0) continue; // a rest: no voice
        const n = timed[i];
        const next = timed[i + 1];
        const into = t - n.start;
        const left = n.end - t;
        const length = n.end - n.start;
        // A breath before the next line: the voice stops a little early.
        if (next?.phraseStart && left < 0.18) continue;
        let midi = n.pitch + (n.flat ? -0.6 : 0);
        if (n.phraseStart && into < 0.12) midi -= 0.7 * n.scoop * (1 - into / 0.12); // a scoop up into the phrase
        else if (!n.phraseStart && into < 0.06) {
            const before = timed[i - 1];
            midi = before.pitch + (midi - before.pitch) * (into / 0.06); // a glide from the note before
        }
        // Vibrato on a long note, growing after the first third of a second to about a third of a semitone.
        const v = n.vibrato;
        if (length > 0.6 && into > v.after) {
            const rate = v.rate + (human ? 0.4 * Math.sin(0.7 * into + v.wander) : 0);
            const depth = Math.min(v.depth, (into - v.after) * 0.5) * (human ? 1 + 0.15 * Math.sin(1.1 * into + v.wander) : 1);
            midi += depth * Math.sin(2 * Math.PI * rate * into);
        }
        midi += 0.08 * Math.sin(t * 0.7); // a slow drift
        // Loudness: an attack, a swell on a long note, a dip as it ends into the next.
        let level = 0.55 * Math.min(1, into / 0.06);
        if (length > 0.6) level += 0.35 * Math.sin(Math.PI * Math.min(1, into / length));
        if (left < 0.05) level *= 0.75 + 0.25 * (left / 0.05);
        track.push({ seconds: Math.round(t * 1000) / 1000, midi: Math.round(midi * 1000) / 1000, level: Math.round(Math.min(1, level) * 1000) / 1000 });
    }
    return track;
}

/** Whether each note was sung in tune: [{ beat, hit, inTune: [[from, to]] }] (the in-tune stretches, in seconds). */
export function noteResults(notes, track, { tempo }) {
    const beat = 60 / tempo;
    return notes.map((n) => {
        const start = n.beat * beat;
        const end = (n.beat + n.length) * beat;
        const during = track.filter((p) => p.seconds >= start && p.seconds < end);
        const inTune = [];
        let tuned = 0;
        for (const p of during) {
            if (Math.abs(p.midi - n.pitch) > TUNE) continue;
            tuned++;
            const run = inTune.at(-1);
            if (run && p.seconds - run[1] <= STEP * 1.5) run[1] = p.seconds + STEP;
            else inTune.push([p.seconds, p.seconds + STEP]);
        }
        const share = tuned / Math.max(1, Math.round((end - start) / STEP));
        return { beat: n.beat, hit: share >= HIT_SHARE, inTune };
    });
}

/**
 * The voice's sound from its track, at `sampleRate`: a few harmonics shaped like a sung "ah",
 * following the pitch and loudness, quiet where the track has no voice.
 */
export function renderTrack(track, sampleRate, { human = null } = {}) {
    if (!track.length) return new Float32Array(0);
    const out = new Float32Array(Math.ceil((track.at(-1).seconds + (human ? 0.9 : 0.3)) * sampleRate));
    const harmonics = [1, 0.55, 0.35, 0.22, 0.12, 0.06];
    let phase = 0;
    let level = 0;
    let k = 0;
    // The human voice: air under the tone, a breath before each phrase, and no perfectly steady pitch or level.
    const random = seeded(track.length * 13 + 5);
    const noise = () => random() * 2 - 1;
    let air = 0;
    let wob = 0;
    let shim = 0;
    const breaths = human ? phraseStarts(track) : [];
    for (let i = 0; i < out.length; i++) {
        const t = i / sampleRate;
        while (k < track.length - 1 && track[k + 1].seconds <= t) k++;
        const p = track[k];
        const voiced = t >= p.seconds && t - p.seconds < STEP * 1.5;
        const n = track[k + 1];
        const midi = voiced && n && n.seconds - p.seconds <= STEP * 1.5 ? p.midi + (n.midi - p.midi) * ((t - p.seconds) / (n.seconds - p.seconds)) : p.midi;
        level += ((voiced ? p.level : 0) - level) * 0.002; // smoothed, so nothing clicks
        let cents = 0;
        let gain = 1;
        let extra = 0;
        if (human) {
            air += 0.35 * (noise() - air);
            wob += 0.002 * (noise() - wob);
            shim += 0.003 * (noise() - shim);
            cents = 60 * wob;
            gain = 1 + 1.2 * shim;
            extra = 0.015 * air * level;
            for (const at of breaths) {
                const before = at - t;
                if (before > 0 && before < 0.24) extra += 0.03 * air * Math.sin((Math.PI * before) / 0.24) ** 2;
            }
        }
        phase += (2 * Math.PI * 440 * 2 ** ((midi + cents / 100 - 69) / 12)) / sampleRate;
        let s = 0;
        for (let h = 0; h < harmonics.length; h++) s += harmonics[h] * Math.sin((h + 1) * phase);
        out[i] = 0.18 * level * gain * s + extra;
    }
    // The room answers where a phrase ends (Hillel: "more at the end of phrases than always").
    if (human) for (const at of phraseEnds(track)) bloom(out, Math.round(at * sampleRate), sampleRate, 0.32, random);
    return out;
}

/** Where the voice stops before a silence of a fifth of a second or more, and at the very end. */
function phraseEnds(track) {
    const ends = [];
    track.forEach((p, i) => {
        if (i === track.length - 1 || track[i + 1].seconds - p.seconds >= 0.2) ends.push(p.seconds + STEP);
    });
    return ends;
}

/** Where the voice starts after a silence of a fifth of a second or more: a breath goes just before. */
function phraseStarts(track) {
    const starts = [];
    track.forEach((p, i) => {
        if (i === 0 || p.seconds - track[i - 1].seconds >= 0.2) starts.push(p.seconds);
    });
    return starts;
}
