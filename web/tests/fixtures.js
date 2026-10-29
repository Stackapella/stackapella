// Generated audio for tests and the demo project: sung-sounding parts, no recordings needed.

/** One MIDI note per `beatsPerNote` beats, with harmonics, a soft attack and a little vibrato. */
export function harmonyPart(sampleRate, { notes, bpm, beatsPerNote = 2, level = 0.25, rest = [] }) {
    const noteFrames = Math.round(((sampleRate * 60) / bpm) * beatsPerNote);
    const out = new Float32Array(noteFrames * notes.length);
    notes.forEach((midi, n) => {
        if (rest.includes(n)) return;
        const hz = 440 * 2 ** ((midi - 69) / 12);
        for (let i = 0; i < noteFrames; i++) {
            const t = i / sampleRate;
            const envelope = Math.min(1, t / 0.08) * Math.min(1, (noteFrames - i) / (0.08 * sampleRate));
            const phase = 2 * Math.PI * hz * (1 + 0.004 * Math.sin(2 * Math.PI * 5.5 * t)) * t;
            out[n * noteFrames + i] = (level * envelope * (Math.sin(phase) + 0.4 * Math.sin(2 * phase) + 0.15 * Math.sin(3 * phase))) / 1.55;
        }
    });
    return out;
}

/** Vocal-percussion stand-in: a short noise burst on every beat, louder on the backbeat. */
export function percussion(sampleRate, { bpm, beats }) {
    const beat = Math.round((sampleRate * 60) / bpm);
    const out = new Float32Array(beat * beats);
    let seed = 1;
    const random = () => ((seed = (seed * 16807) % 2147483647) / 2147483647) * 2 - 1;
    for (let b = 0; b < beats; b++) {
        const level = b % 2 ? 0.5 : 0.3;
        for (let i = 0; i < sampleRate * 0.08; i++) {
            out[b * beat + i] = level * random() * Math.exp(-i / (sampleRate * 0.02));
        }
    }
    return out;
}

/**
 * A synthesized singer for the pitch tests: `notes` ({ beat, length, pitch }) sung at `bpm`, with
 * a voice-like spectrum, and the faults to measure: `cents` off (a number, or a function of the
 * note), `vibrato` ({ hz, cents }), a `scoop` ({ cents, ms }) into each note, entries `lateMs`
 * late (a number, or a function of the note), an `octave` away (a number, or a function of the note), `legato` (notes that touch glide
 * from the one before over `legato` ms, without a new attack), and `noise` (0 to 1) added.
 */
export function sing(sampleRate, { notes, bpm, cents = 0, vibrato = null, scoop = null, lateMs = 0, octave = 0, legato = 0, level = 0.3, noise = 0, seed = 7 }) {
    const beat = 60 / bpm;
    const end = notes.reduce((last, n) => Math.max(last, n.beat + n.length), 0) * beat + 0.5;
    const out = new Float32Array(Math.ceil(end * sampleRate));
    const harmonics = [1, 0.6, 0.35, 0.25, 0.12, 0.08];
    const at = (fn, note) => (typeof fn === 'function' ? fn(note) : fn);
    const midiHz = (midi) => 440 * 2 ** ((midi - 69) / 12);
    let phase = 0;
    notes.forEach((note, n) => {
        const before = notes[n - 1];
        const after = notes[n + 1];
        const joined = legato && before && Math.abs(before.beat + before.length - note.beat) < 1e-9;
        const joinsNext = legato && after && Math.abs(note.beat + note.length - after.beat) < 1e-9;
        const start = note.beat * beat + (joined ? 0 : at(lateMs, note) / 1000);
        const stop = (note.beat + note.length) * beat;
        const off = at(cents, note);
        const base = midiHz(note.pitch + 12 * at(octave, note));
        const from = joined ? midiHz(before.pitch + 12 * at(octave, before)) : base;
        if (!joined) phase = 0;
        const first = Math.round(start * sampleRate);
        const frames = Math.round((stop - start) * sampleRate);
        for (let i = 0; i < frames && first + i < out.length; i++) {
            const t = i / sampleRate;
            let c = off;
            if (vibrato) c += vibrato.cents * Math.sin(2 * Math.PI * vibrato.hz * t);
            if (scoop && t * 1000 < scoop.ms) c += scoop.cents * (1 - (t * 1000) / scoop.ms);
            const glide = joined && t * 1000 < legato ? (1 - (t * 1000) / legato) * 1200 * Math.log2(from / base) : 0;
            phase += (base * 2 ** ((c + glide) / 1200)) / sampleRate;
            const attack = joined ? 1 : Math.min(1, t / 0.03);
            const release = joinsNext ? 1 : Math.min(1, (frames - i) / (0.04 * sampleRate));
            let v = 0;
            for (let h = 0; h < harmonics.length; h++) v += harmonics[h] * Math.sin(2 * Math.PI * (h + 1) * phase);
            out[first + i] += (level * attack * release * v) / 2.4;
        }
    });
    if (noise) {
        let s = seed;
        for (let i = 0; i < out.length; i++) {
            s = (s * 16807) % 2147483647;
            out[i] += noise * ((s / 2147483647) * 2 - 1);
        }
    }
    return out;
}
