// The course pages' little demos (server/resources/views/site/courses/*): voice-like tones made here
// with Web Audio, never a recording and never autoplayed. A voice is an oscillator with the overtones
// of a sung "ah" (a custom PeriodicWave), so two voices a little out of tune beat against each other
// the way singers do, and tuning one by cents, on the audio clock, makes the beating slow and stop.
// Every sound starts from a click or a key, fades in and out with a short ramp, and stops when the
// page is hidden.

let ctx = null;
let wave = null;
let head = null;
let reed = null;
let plucked = null;
const live = new Set();

// The "ah" vowel's overtones, loudest first, falling away: enough of them that the fourth to seventh
// line up (or nearly) in a barbershop chord and the near misses can be heard.
const AH = [0, 1, 0.72, 0.58, 0.5, 0.38, 0.3, 0.24, 0.18, 0.13, 0.1, 0.07, 0.05];
// Head voice (falsetto): nearly all its energy in the note itself, a hooty "oo".
const OO = [0, 1, 0.14, 0.05, 0.02];
// A pitch pipe's free reed: every harmonic, odd and even, falling slowly, a little nasal (a bump
// around the fourth to sixth), so it buzzes the way the real one does.
const REED = [0, 1, 0.82, 0.7, 0.74, 0.72, 0.6, 0.44, 0.36, 0.3, 0.26, 0.2, 0.17, 0.14, 0.11, 0.09, 0.07];

// A plucked string with a buzzing bridge (a tanpura's jawari): bright, the upper partials almost as
// strong as the low ones, so it shimmers.
const PLUCK = [0, 1, 0.55, 0.62, 0.48, 0.52, 0.44, 0.46, 0.38, 0.4, 0.32, 0.33, 0.26, 0.27, 0.2, 0.2, 0.15, 0.15, 0.11, 0.1];

// The timbres a voice can have (the tools, docs/tools-plan.md): the sung "ah" (the default), the
// head voice's "oo", a pitch pipe's reed, a plucked string's buzz (tones.js pluck()), and two plain
// waves for a pure tone (a tuning fork, a reference A).
export const TIMBRES = ['ah', 'oo', 'reed', 'pluck', 'sine', 'triangle'];

/** The AudioContext and its wave tables, made once (resumed only by audio(), inside a tap). */
function create() {
    if (!ctx) {
        const Context = window.AudioContext || window.webkitAudioContext;
        ctx = new Context({ latencyHint: 'interactive' });
        const imag = new Float32Array(AH);
        wave = ctx.createPeriodicWave(new Float32Array(AH.length), imag);
        head = ctx.createPeriodicWave(new Float32Array(OO.length), new Float32Array(OO));
        reed = ctx.createPeriodicWave(new Float32Array(REED.length), new Float32Array(REED));
        plucked = ctx.createPeriodicWave(new Float32Array(PLUCK.length), new Float32Array(PLUCK));
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) stopAll();
        });
    }
    return ctx;
}

export function audio() {
    create();
    if (ctx.state === 'suspended') ctx.resume();
    return ctx;
}

/**
 * Makes the AudioContext ahead, suspended, once the page is idle: for a tool whose first tap sounds (the drone, the forks,
 * the pitch pipe). Making one takes 80 to 130 ms on a slow phone, which held that tap's next paint near 200 ms (INP, the
 * game agent's Core Web Vitals sweep, 2026-11-08); the tap then only resumes it, as a gesture allows. Not a timer for audio:
 * nothing sounds from it.
 */
export function warmAudio() {
    if (ctx || !(window.AudioContext || window.webkitAudioContext)) return;
    if ('requestIdleCallback' in window) requestIdleCallback(create, { timeout: 3000 });
    else setTimeout(create, 1000); // Safari has no requestIdleCallback: soon after load instead
}

export const hz = (midi, cents = 0) => 440 * 2 ** ((midi - 69 + cents / 100) / 12);

// One sung tone: hz, an optional detune in cents, and a gentle vibrato (rate in Hz, depth in cents).
// `attack` and `release` are the fade in and out's time constants in seconds: shorter for a quick,
// clearly sounded note (Musical theatre's patter, Scat's swung eighths). `timbre` is one of TIMBRES.
// A voice stops when the page is hidden, unless `keepWhenHidden` (a tool's drone, which a singer may
// leave sounding while they read music in another tab).
export function voice(frequency, { cents = 0, level = 0.16, vibrato = null, at = null, attack = 0.06, release = 0.08, timbre = 'ah', keepWhenHidden = false } = {}) {
    const c = audio();
    const start = at ?? c.currentTime + 0.03;
    const osc = c.createOscillator();
    if (timbre === 'sine' || timbre === 'triangle') osc.type = timbre;
    else osc.setPeriodicWave({ oo: head, reed, pluck: plucked }[timbre] ?? wave);
    osc.frequency.value = frequency;
    osc.detune.value = cents;
    const gain = c.createGain();
    gain.gain.setValueAtTime(0, start);
    gain.gain.setTargetAtTime(level, start, attack);
    osc.connect(gain).connect(c.destination);
    let lfo = null;
    let depth = null;
    if (vibrato) {
        lfo = c.createOscillator();
        lfo.frequency.value = vibrato.rate;
        depth = c.createGain();
        depth.gain.value = vibrato.depth;
        lfo.connect(depth).connect(osc.detune);
        lfo.start(start);
    }
    osc.start(start);
    let target = cents;
    const ended = new Promise((resolve) => { osc.onended = resolve; });
    const handle = {
        ended,
        // Glide to a detune in cents over `over` seconds, starting `from` (on the audio clock) from
        // where the last glide ends, so glides can be queued.
        tune(to, over = 0.6, from = null) {
            const at = Math.max(from ?? c.currentTime, c.currentTime);
            osc.detune.cancelScheduledValues(at);
            osc.detune.setValueAtTime(target, at);
            osc.detune.linearRampToValueAtTime(to, at + over);
            target = to;
        },
        // The vibrato's rate (Hz) and width (± cents), glided on the audio clock (a voice made with a
        // `vibrato`, even { rate: 0.01, depth: 0 }, so it has one to change).
        wave(rate, width, over = 0.12) {
            if (!lfo) return;
            const now = c.currentTime;
            lfo.frequency.setTargetAtTime(Math.max(rate, 0.01), now, over / 3);
            depth.gain.setTargetAtTime(width, now, over / 3);
        },
        // The level, glided (for bringing one voice forward in a chord).
        level(to, over = 0.15) {
            const now = c.currentTime;
            gain.gain.cancelScheduledValues(now);
            gain.gain.setTargetAtTime(to, now, over / 3);
        },
        // Fade out `after` seconds from now, on the audio clock; `ended` resolves when it's silent.
        stop(after = 0) {
            const t = c.currentTime + after;
            gain.gain.cancelScheduledValues(t);
            gain.gain.setTargetAtTime(0, t, release);
            try {
                osc.stop(t + Math.max(0.6, release * 6));
                lfo?.stop(t + 0.6);
            } catch {
                // already stopped
            }
            if (!after) live.delete(handle);
        },
    };
    if (!keepWhenHidden) {
        live.add(handle);
        ended.then(() => live.delete(handle));
    }
    return handle;
}

// A sung line that can cross the break: notes [{ at, f, head, slide }] (at in seconds from the
// start, f in hz, head for head voice, slide in seconds to glide into the note instead of jumping).
// Two oscillators follow the same pitch, one with the chest voice's overtones and one with the head
// voice's, and only one sounds at a time: at a flip the colour changes in the same instant as the
// pitch, and a slide blends both. `length` is the line's length in seconds; `echo` sends it across a
// valley ({ time, feedback }: a delay, darkened and to one side, feeding back on itself). Returns a
// handle like voice's: stop(after) ends it, so a demo stops it at its length.
export function line(notes, length, { level = 0.16, at = null, echo = null } = {}) {
    const c = audio();
    const start = at ?? c.currentTime + 0.05;
    const out = c.createGain();
    out.connect(c.destination);
    if (echo) {
        const delay = c.createDelay(2);
        delay.delayTime.value = echo.time;
        const dark = c.createBiquadFilter();
        dark.type = 'lowpass';
        dark.frequency.value = 1600;
        const back = c.createGain();
        back.gain.value = echo.feedback;
        const side = c.createStereoPanner ? c.createStereoPanner() : c.createGain();
        if (side.pan) side.pan.value = 0.6;
        out.connect(delay).connect(dark).connect(back).connect(delay);
        dark.connect(side).connect(c.destination);
    }
    const parts = [wave, head].map((shape) => {
        const osc = c.createOscillator();
        osc.setPeriodicWave(shape);
        const gain = c.createGain();
        gain.gain.setValueAtTime(0, start);
        osc.connect(gain).connect(out);
        osc.frequency.setValueAtTime(notes[0].f, start);
        return { osc, gain };
    });
    notes.forEach((note, i) => {
        const t = start + note.at;
        const levels = [note.head ? 0 : level, note.head ? level * 1.15 : 0];
        parts.forEach(({ osc, gain }, k) => {
            if (i > 0) {
                osc.frequency.setValueAtTime(notes[i - 1].f, t);
                osc.frequency.linearRampToValueAtTime(note.f, t + (note.slide ?? 0.012));
            }
            if (note.slide) gain.gain.linearRampToValueAtTime(levels[k], t + note.slide);
            else gain.gain.setTargetAtTime(levels[k], t, i === 0 ? 0.03 : 0.006);
        });
    });
    const ended = new Promise((resolve) => { parts[0].osc.onended = resolve; });
    const handle = {
        ended,
        length,
        // Fade out `after` seconds from now (the line's own length, unless stopped sooner).
        stop(after = 0) {
            const t = c.currentTime + after;
            parts.forEach(({ osc, gain }) => {
                gain.gain.cancelScheduledValues(t);
                gain.gain.setTargetAtTime(0, t, 0.05);
                try {
                    osc.stop(t + 0.4);
                } catch {
                    // already stopped
                }
            });
            if (!after) live.delete(handle);
        },
    };
    parts.forEach(({ osc }) => osc.start(start));
    live.add(handle);
    ended.then(() => live.delete(handle));
    return handle;
}

// Several voices at once, all started on the same frame.
export function chord(frequencies, options = {}) {
    const c = audio();
    const at = c.currentTime + 0.05;
    return frequencies.map((f, i) => voice(f, { ...options, at, cents: options.cents?.[i] ?? 0, level: options.level ?? 0.12 }));
}

export function stopAll() {
    for (const handle of [...live]) handle.stop();
}

// A sound made elsewhere (tones.js) that should stop with the page like a voice: a handle with
// stop() and an `ended` promise.
export function track(handle) {
    live.add(handle);
    handle.ended.then(() => live.delete(handle));
    return handle;
}

export const reducedMotion = () => matchMedia('(prefers-reduced-motion: reduce)').matches;

// ---- The arranger's helpers for the course-page demos (docs/course-pages-polish.md) -----------------
// Built on `line` and `voice` above, everything on the audio clock, pressed to play. The ornaments'
// shapes are the app's own (js/style-scores.js: a scoop starts 100 to 450 cents under and arrives within
// 0.4 s; a fall-off drops 200 cents or more in the note's last 0.4 s; a slide glides 0.12 to 0.7 s; a cry
// flicks 100 to 400 cents up for 0.06 to 0.25 s), so a demo sounds like what the lessons teach and judge.

/** An ornament's pitch path for `line`, from 0 seconds: `[{ at, f, slide?, head? }]`. Pure (the tests check its shape). */
export function ornamentPath(kind, { midi, length = 1.6, to = null }) {
    const f = (cents = 0, note = midi) => hz(note, cents);
    if (kind === 'scoop') return [{ at: 0, f: f(-250) }, { at: 0.02, f: f(), slide: 0.26 }];
    if (kind === 'fall') return [{ at: 0, f: f() }, { at: Math.max(0.3, length - 0.38), f: f(-500), slide: 0.34 }];
    if (kind === 'slide') {
        const half = Math.max(0.4, length / 2);
        return [{ at: 0, f: f() }, { at: half - 0.15, f: f(0, to ?? midi + 4), slide: 0.3 }];
    }
    if (kind === 'cry' || kind === 'flip') return [{ at: 0, f: f() }, { at: 0.32, f: f(300), slide: 0.025, head: true }, { at: 0.45, f: f(), slide: 0.04 }];
    return [{ at: 0, f: f() }]; // 'plain': the same note, sung straight, to compare
}

/**
 * One sung note with an ornament (Pop and country): `kind` 'scoop', 'fall', 'slide' (to `to`, a MIDI
 * note), 'cry' (or 'flip') or 'plain'. Starts `at` (audio clock seconds, default now) and lasts `length`
 * seconds; returns line's handle (`ended`, `stop(after)`, `length`), stopping on its own at its end.
 */
export function ornament(kind, { midi, at = null, length = 1.6, to = null, level = 0.16 } = {}) {
    const c = audio();
    const start = at ?? c.currentTime + 0.05;
    const handle = line(ornamentPath(kind, { midi, length, to }), length, { level, at: start });
    handle.stop(start - c.currentTime + length);
    return handle;
}

// A short tune in a minor mode, as steps from its tonic: the sixth (9 in Dorian, 8 in Aeolian) falls on
// beats 5 and 10, where it can be heard (Folk: the Dorian sixth).
const MODE_TUNE = [0, 2, 3, 5, 7, 'sixth', 7, 5, 7, 'sixth', 7, 5, 3, 2, 0];
const MODE_BEAT = 0.42; // seconds a note
const MODE_GAP = 0.9; // seconds between the two versions

/** The mode tune's notes for `line` with this `sixth` (9 or 8), from `at` seconds. Pure. */
export function modeTune(tonicMidi, sixth, at = 0) {
    return MODE_TUNE.map((step, i) => ({ at: at + i * MODE_BEAT, f: hz(tonicMidi + (step === 'sixth' ? sixth : step)) }));
}

/**
 * The Dorian sixth, heard (Folk): the same short tune twice, first with the raised sixth (Dorian),
 * then with the minor sixth (Aeolian). Returns `{ ended, length, stop(after), parts }`, `parts` each
 * `{ start, length, sixth }` in audio-clock seconds, for captions that follow the sound.
 */
export function modeCompare(tonicMidi, { at = null, level = 0.15 } = {}) {
    const c = audio();
    const start = at ?? c.currentTime + 0.05;
    const one = MODE_TUNE.length * MODE_BEAT + 0.3;
    const parts = [{ start, length: one, sixth: 9 }, { start: start + one + MODE_GAP, length: one, sixth: 8 }];
    const lines = parts.map((p) => {
        const handle = line(modeTune(tonicMidi, p.sixth), p.length, { level, at: p.start });
        handle.stop(p.start - c.currentTime + p.length);
        return handle;
    });
    return {
        parts,
        length: parts[1].start + one - start,
        ended: Promise.all(lines.map((l) => l.ended)),
        stop(after = 0) {
            lines.forEach((l) => l.stop(after));
        },
    };
}

/**
 * The Dorian sixth over a drone (Folk): the tonic and its fifth held, and a voice on the sixth that
 * `setSixth(9 | 8)` glides between the raised (Dorian) and the minor (Aeolian) sixth on the audio
 * clock. Returns `{ setSixth, sixth, stop(after) }`; it holds until stopped.
 */
export function modeDrone(tonicMidi, { sixth = 9, level = 0.12 } = {}) {
    const c = audio();
    const at = c.currentTime + 0.05;
    const drone = [voice(hz(tonicMidi - 12), { level, at }), voice(hz(tonicMidi - 5), { level: level * 0.8, at, cents: 2 })];
    const top = voice(hz(tonicMidi + 9), { level: level * 1.1, at, cents: sixth === 9 ? 0 : -100 });
    const state = {
        sixth,
        setSixth(next) {
            top.tune(next === 9 ? 0 : -100, 0.25);
            state.sixth = next;
        },
        stop(after = 0) {
            [...drone, top].forEach((v) => v.stop(after));
        },
    };
    return state;
}

/**
 * A sung phrase in the course file's shape, each note with its move (Pop and country's Plain to
 * styled): `notes` are `[{ beat, length, step, style? }]` (step in semitones from `midi`, style
 * 'scoop', 'fall', 'slide' into the next note, or 'cry'), `beat` the seconds a beat lasts, `from` the
 * beat the phrase starts on. Returns line's points, each move in ornamentPath's shape, so the same
 * points can be sung and drawn. Pure (the tests check it).
 */
export function styledLine(notes, { midi, beat, from = 0 }) {
    const points = [];
    notes.forEach((note, i) => {
        const at = (note.beat - from) * beat;
        const m = midi + note.step;
        // A slide from the note before replaces this note's start (and any scoop); its fall or cry stays.
        const into = notes[i - 1]?.style === 'slide';
        const kind = ['fall', 'cry'].includes(note.style) || (note.style === 'scoop' && !into) ? note.style : 'plain';
        const path = ornamentPath(kind, { midi: m, length: note.length * beat }).map((p) => ({ ...p, at: at + p.at }));
        if (into) path[0] = { at: at - 0.15, f: hz(m), slide: 0.3 };
        points.push(...path);
    });
    return points;
}
