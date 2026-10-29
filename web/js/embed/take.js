// A take for the embedded lane (js/embed/lane.js): what a voice did, as the Sing lane draws it.
// A take file (web/templates/takes/<song>-<part>.json) names the song, the part and how far it goes; a real
// singer's take adds its `audio` and its `track` (the pitch and loudness every 10 ms, made from the
// recording with the app's own tracker), and nothing else changes. Without them, the voice is made
// here from the part's notes, sung the way a person sings: scooping up into a phrase, gliding
// between notes, vibrato blooming on long notes, a slow drift, loudness that swells on long notes
// and dips between them, breaths between lines, and any note in `flat` sung under the note (a miss).
// Pure: no audio, no DOM (tests/embed.test.js).

const STEP = 0.01; // seconds between track points
const TUNE = 0.35; // semitones either side that count as in tune
const HIT_SHARE = 0.6; // a note is hit when this much of it was in tune

/** The voice for `notes` ({ beat, length, pitch, br }) at `tempo`: [{ seconds, midi, level }], silent points left out. */
export function expressiveTrack(notes, { tempo, flat = [] }) {
    const beat = 60 / tempo;
    const track = [];
    const timed = notes.map((n, i) => ({ ...n, start: n.beat * beat, end: (n.beat + n.length) * beat, flat: flat.includes(n.beat), phraseStart: i === 0 || n.br || n.beat - (notes[i - 1].beat + notes[i - 1].length) > 0.5 }));
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
        if (n.phraseStart && into < 0.12) midi -= 0.7 * (1 - into / 0.12); // a scoop up into the phrase
        else if (!n.phraseStart && into < 0.06) {
            const before = timed[i - 1];
            midi = before.pitch + (midi - before.pitch) * (into / 0.06); // a glide from the note before
        }
        // Vibrato on a long note, growing after the first third of a second to about a third of a semitone.
        if (length > 0.6 && into > 0.3) midi += Math.min(0.33, (into - 0.3) * 0.5) * Math.sin(2 * Math.PI * 5.6 * into);
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
export function renderTrack(track, sampleRate) {
    if (!track.length) return new Float32Array(0);
    const out = new Float32Array(Math.ceil((track.at(-1).seconds + 0.3) * sampleRate));
    const harmonics = [1, 0.55, 0.35, 0.22, 0.12, 0.06];
    let phase = 0;
    let level = 0;
    let k = 0;
    for (let i = 0; i < out.length; i++) {
        const t = i / sampleRate;
        while (k < track.length - 1 && track[k + 1].seconds <= t) k++;
        const p = track[k];
        const voiced = t >= p.seconds && t - p.seconds < STEP * 1.5;
        const n = track[k + 1];
        const midi = voiced && n && n.seconds - p.seconds <= STEP * 1.5 ? p.midi + (n.midi - p.midi) * ((t - p.seconds) / (n.seconds - p.seconds)) : p.midi;
        level += ((voiced ? p.level : 0) - level) * 0.002; // smoothed, so nothing clicks
        phase += (2 * Math.PI * 440 * 2 ** ((midi - 69) / 12)) / sampleRate;
        let s = 0;
        for (let h = 0; h < harmonics.length; h++) s += harmonics[h] * Math.sin((h + 1) * phase);
        out[i] = 0.18 * level * s;
    }
    return out;
}
