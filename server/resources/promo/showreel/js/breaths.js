// Hillel: "can we cut the breaths from the audio?" A singer's breath between phrases is unpitched air,
// so inside each gap of a part (where the template rests, or a new phrase starts), every stretch that
// isn't pitched is ducked to a whisper; pitched sound (a note held on, an early entry) is left alone.
// The gain opens a little before the voice and closes a little after it, through short fades, so no
// note loses its start and nothing clicks. Pure: the same samples always give the same result.

const FLOOR = 0.03; // about -30 dB: a breath left this quiet is gone under the others and the room
const HOP = 0.01, WIN = 0.04; // seconds: 10 ms steps over 40 ms frames
const BEFORE = 0.03, AFTER = 0.06; // seconds the gain opens before a pitched stretch and holds after it
const FADE = 0.008; // seconds of each fade

/** How periodic a frame is (0 to 1): the best normalised autocorrelation for a pitch of 70 to 1000 Hz. */
function periodicity(x, rate) {
    let e = 0;
    for (const v of x) e += v * v;
    if (e < 1e-9) return 0;
    let best = 0;
    for (let lag = Math.floor(rate / 1000); lag <= Math.ceil(rate / 70) && lag < x.length / 2; lag++) {
        let s = 0, e1 = 0, e2 = 0;
        for (let i = 0; i + lag < x.length; i++) { s += x[i] * x[i + lag]; e1 += x[i] * x[i]; e2 += x[i + lag] * x[i + lag]; }
        const r = s / Math.sqrt(e1 * e2 || 1);
        if (r > best) best = r;
    }
    return best;
}

/**
 * The gaps of a part (seconds into its recording): before its first note, and around each rest or
 * phrase start, from a little before the written end of a note to a little after the next begins.
 */
export function gaps(notes, spb) {
    const out = [];
    if (notes[0].beat > 0.5) out.push([0, notes[0].beat * spb + 0.1]);
    for (let k = 0; k + 1 < notes.length; k++) {
        const n = notes[k], next = notes[k + 1], end = n.beat + n.length;
        if (next.br || next.beat - end > 0.1) out.push([end * spb - 0.35, next.beat * spb + 0.1]);
    }
    return out;
}

/** `samples` (at `rate`) with its breaths ducked inside `windows` ([from, to] seconds). Changes it in place. */
export function duckBreaths(samples, rate, windows) {
    const dec = 4, r = rate / dec; // pitch is judged at a quarter of the rate: plenty for a voice
    const hop = Math.round(HOP * rate), win = Math.round(WIN * rate);
    for (const [w0, w1] of windows) {
        const a = Math.max(0, Math.round(w0 * rate)), b = Math.min(samples.length, Math.round(w1 * rate));
        if (b - a < win) continue;
        // pitched or not, every 10 ms
        const frames = [];
        for (let i = a; i + win <= b; i += hop) {
            const x = new Float32Array(win / dec);
            for (let k = 0; k < x.length; k++) x[k] = samples[i + k * dec];
            frames.push(periodicity(x, r) > 0.5);
        }
        // the gain each frame wants: open around pitched frames, the floor elsewhere
        const pre = Math.ceil(BEFORE / HOP), post = Math.ceil(AFTER / HOP), want = frames.map(() => FLOOR);
        frames.forEach((on, f) => { if (on) for (let g = Math.max(0, f - pre); g <= Math.min(frames.length - 1, f + post); g++) want[g] = 1; });
        // the window's edges stay open, so it joins the untouched voice either side without a step
        const edge = Math.ceil(0.03 / HOP);
        for (let f = 0; f < Math.min(edge, want.length); f++) want[f] = 1;
        for (let f = Math.max(0, want.length - edge); f < want.length; f++) want[f] = 1;
        // applied sample by sample through a short one-pole fade
        const k = 1 - Math.exp(-1 / (FADE * rate));
        let g = 1;
        for (let i = a; i < b; i++) {
            const f = Math.min(want.length - 1, Math.floor((i - a) / hop));
            g += (want[f] - g) * k;
            samples[i] *= g;
        }
    }
    return samples;
}
