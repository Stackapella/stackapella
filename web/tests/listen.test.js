// Finding a held note (js/listen.js) through the live tracker (trackFrame), on voices like real
// singers': vibrato, a little wander, breath noise, soft singing, octave slips; and what isn't a
// held note: a slide, speech-like jumps, silence.
import { HOLD_SECONDS, hear, LISTEN_CLARITY, listener, trouble } from '/js/listen.js';
import { trackFrame } from '/js/pitchtrack.js';
import { assert, test } from './harness.js';

const RATE = 48000;
const FRAME = 2048;
const STEP = 1 / 60; // readings at screen rate

/** A sung vowel: harmonics with falling strength, vibrato, jitter, shimmer and breath noise. */
function voice({ midi = 57, seconds = 3, vibratoCents = 60, vibratoHz = 5.5, db = -20, noise = 0.08, glide = 0, octaveSlipAt = null, seed = 1 }) {
    let r = seed;
    const random = () => ((r = (r * 16807) % 2147483647) / 2147483647) * 2 - 1;
    const n = Math.round(seconds * RATE);
    const out = new Float32Array(n);
    const amp = 10 ** (db / 20);
    let phase = 0;
    for (let i = 0; i < n; i++) {
        const t = i / RATE;
        let m = midi + glide * (t / seconds) + (vibratoCents / 100) * Math.sin(2 * Math.PI * vibratoHz * t) + 0.05 * random();
        if (octaveSlipAt !== null && t >= octaveSlipAt && t < octaveSlipAt + 0.25) m += 12;
        phase += (2 * Math.PI * 440 * 2 ** ((m - 69) / 12)) / RATE;
        let v = 0;
        for (let h = 1; h <= 8; h++) v += Math.sin(h * phase) / (h * (h < 3 ? 1 : 1.6));
        out[i] = amp * (0.45 * v * (1 + 0.04 * random()) + noise * random());
    }
    return out;
}

/** Feeds `samples` to a listener at screen rate, as the live meter does. */
function listen(samples) {
    let state = listener();
    const hop = Math.round(RATE * STEP);
    for (let end = FRAME; end <= samples.length; end += hop) {
        const reading = trackFrame(samples.subarray(end - FRAME, end), RATE, { minClarity: LISTEN_CLARITY });
        state = hear(state, reading, STEP);
        if (state.found) break;
    }
    return state;
}

test('listen: a sung note with vibrato and breath is found, near its pitch', () => {
    const s = listen(voice({ midi: 57 }));
    assert(s.found && Math.abs(s.note - 57) < 0.5, `found ${s.found}, note ${s.note}`);
});

test('listen: a soft, breathy voice is found too', () => {
    const s = listen(voice({ midi: 52, db: -38, noise: 0.25, seed: 7 }));
    assert(s.found && Math.abs(s.note - 52) < 0.6, `found ${s.found}, note ${s.note}, loudest ${s.loudest.toFixed(1)}`);
});

test('listen: a wide vibrato and a quick octave slip still count as one note', () => {
    const s = listen(voice({ midi: 64, vibratoCents: 90, vibratoHz: 6.5, octaveSlipAt: 0.9, seed: 3 }));
    assert(s.found && Math.abs(s.note - 64) < 0.6, `found ${s.found}, note ${s.note}`);
});

test('listen: a low voice (a bass) is found', () => {
    const s = listen(voice({ midi: 43, seconds: 3.5, seed: 5 }));
    assert(s.found && Math.abs(s.note - 43) < 0.6, `found ${s.found}, note ${s.note}`);
});

test('listen: a slide is not a held note, and says so', () => {
    const s = listen(voice({ midi: 50, glide: 14, seconds: 3, vibratoCents: 0 }));
    assert(!s.found && trouble(s) === 'unsteady', `found ${s.found}, held ${s.held.toFixed(2)}, ${trouble(s)}`);
});

test('listen: silence and a whisper say why', () => {
    const silent = listen(new Float32Array(RATE * 2));
    assert(!silent.found && trouble(silent) === 'silent', trouble(silent));
    const whisper = listen(voice({ db: -62, noise: 0.5, seconds: 2 }));
    assert(!whisper.found && trouble(whisper) === 'quiet', `${trouble(whisper)} at ${whisper.loudest.toFixed(1)} dBFS`);
});

test('listen: holding takes about two seconds', () => {
    const s = listen(voice({ midi: 60, seconds: 4 }));
    assert(s.found && s.total >= HOLD_SECONDS && s.total < HOLD_SECONDS + 0.6, `${s.total.toFixed(2)} s`);
});

test('listen: a first reading an octave out does not set the note’s octave', () => {
    let state = listener();
    state = hear(state, { hz: 659.26, loudness: -20 }, 0.02); // E5: an onset read an octave up
    for (let i = 0; i < 150; i++) state = hear(state, { hz: 329.63, loudness: -20 }, 0.02); // then E4, held
    assert(state.found && Math.round(state.note) === 64, `found ${state.note}`);
});
