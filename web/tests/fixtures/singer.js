// The synthetic singer of the scoring guard (tests/score-guard.test.js), from the courses lead's scoring audit
// (2026-11-08, its free/lib.mjs `sing`), as they agreed it: 60 ms behind the beat, 6 cents flat, a 3-cent wander,
// 50 ms glides between joined notes, stopping 60 ms before a rest, and (when asked) a natural 5.5 Hz ±50 vibrato
// after 0.3 s on notes of 0.8 s or longer. A pitch track at 100 readings a second, as pitchtrack.js gives.

export const RATE = 100;

let seed = 1;
const rand = () => ((seed = (seed * 16807) % 2147483647) / 2147483647) * 2 - 1;

/**
 * A sung take of `notes` ([{ beat, length, pitch, br? }]) at `beatSeconds`, `totalSeconds` long. Options: lag (s,
 * every onset this late), offset (cents, the whole take), jitter (cents), glide (s, a joined note's slide from the
 * one before), release (s, stopping this early before a rest), breath (beats, stopping this much before a `br` note),
 * vib ({ hz, cents, after }, on notes of 0.8 s or more), octave (whole octaves off), per(note, i, tIn, dur, sounding) =>
 * extra cents (scoops, shakes, falls: `sounding` is how long the voice is on it), pitchOf(note, i) => the pitch sung (a
 * choice), delay(i) => extra seconds late for that note's onset (uneven timing, a late entry), glideOf(i) => its glide
 * seconds instead (0: none, when `per` shapes the move), onsetJitter (s, each onset's own random lateness, a normal
 * spread: the pitch tracker's onset noise, as the courses lead's timing simulation adds 25 ms), silent ([i]: notes not
 * sung), gap (s of silence before every change, joined notes too: legato broken), seed. The track carries a loudness
 * line too (voiced -14 dB, silence none), as the app's tracker gives, for the measures that read it (legato).
 */
export function sing(notes, beatSeconds, totalSeconds, o = {}) {
    const { lag = 0.06, offset = -6, jitter = 3, glide = 0.05, release = 0.06, breath = 0.3, vib = null, octave = 0, per = null, pitchOf = null, delay = null, glideOf = null, onsetJitter = 0, gap = 0, silent = [] } = o;
    seed = o.seed ?? 7;
    // A normal spread from two uniform draws (Box-Muller), drawn only when asked so the other takes stay as they were.
    const normal = () => Math.sqrt(-2 * Math.log((rand() + 1) / 2 + 1e-12)) * Math.cos(Math.PI * (rand() + 1));
    const noise = notes.map(() => (onsetJitter ? onsetJitter * normal() : 0));
    const hz = new Float32Array(Math.round(totalSeconds * RATE));
    const sec = notes.map((n, i) => ({ ...n, pitch: (pitchOf ? pitchOf(n, i) : n.pitch) + 12 * octave, s: n.beat * beatSeconds + lag + (delay?.(i) ?? 0) + noise[i], e: (n.beat + n.length) * beatSeconds + lag }));
    for (let i = 0; i < sec.length; i++) {
        const n = sec[i];
        const next = sec[i + 1];
        const prev = sec[i - 1];
        const joined = prev && Math.abs(prev.e - n.s) < 0.02 && !n.br;
        let end = n.e;
        if (!next || next.s - n.e > 0.02) end = n.e - release; // a rest follows: the voice stops just before it
        else if (gap) end = n.e - gap; // a break before a joined change
        else if (next.br) end = Math.min(n.e - Math.min(breath * beatSeconds, (n.e - n.s) / 2), n.e); // a breath before the next line
        if (silent.includes(i)) continue;
        const slide = glideOf?.(i) ?? glide;
        for (let f = Math.ceil(n.s * RATE); f < Math.min(hz.length, end * RATE); f++) {
            const t = f / RATE;
            const tIn = t - n.s;
            let cents = offset + jitter * (Math.sin(t * 37.1) + 0.5 * rand());
            if (joined && tIn < slide) cents += (prev.pitch - n.pitch) * 100 * (1 - tIn / slide);
            if (vib && n.e - n.s >= 0.8 && tIn > (vib.after ?? 0.3)) cents += vib.cents * Math.sin(2 * Math.PI * vib.hz * (tIn - (vib.after ?? 0.3)));
            if (per) cents += per(n, i, tIn, n.e - n.s, end - n.s) ?? 0;
            hz[f] = 440 * 2 ** ((n.pitch + cents / 100 - 69) / 12);
        }
    }
    const loudness = Float32Array.from(hz, (v) => (v > 0 ? -14 : -Infinity));
    return { rate: RATE, hz, loudness };
}

/** The agreed natural vibrato for a trusted take. */
export const NATURAL = { hz: 5.5, cents: 50, after: 0.3 };
