// Real instruments (contract/instruments.md): the CC0 sample sets the backend builds, listed by
// auth.config's instrumentsIndexUrl. An instrument loads only when something uses it; its files never
// change by version, so the service worker keeps them for good. Everything is placed on an audio clock:
// renderNotes() plays notes into an OfflineAudioContext (a part or a backing, rendered ahead, then
// played by the engine), playNote() plays one at once on the engine's context (a preview). When the
// samples aren't there (instruments off, offline, a download failing, a format this app doesn't know)
// the answer is null, and the caller uses js/synth.js as before, with nothing said.

import { engine } from './audio.js';
import { account, loadAuthConfig } from './stores/account.js';

const FORMAT = 1;
const INDEX_MAX_AGE = 5 * 60 * 1000; // the index is cached this long (the CDN's own five minutes)
const DEFAULT_VELOCITY = 90;
const LOADS_AT_ONCE = 6;

let index = null; // { at, instruments: [{ name, version, manifest }] }
const loaded = new Map(); // name → Promise of an instrument (or null)
const rr = new Map(); // a drum piece's next round robin

async function readIndex() {
    if (index && Date.now() - index.at < INDEX_MAX_AGE) return index.instruments;
    let url = account.config?.instrumentsIndexUrl;
    if (url === undefined) {
        url = (await loadAuthConfig().catch((error) => {
            console.info('No sign-in settings, so no instruments index', error?.message ?? error);
            return null;
        }))?.instrumentsIndexUrl;
    }
    if (!url) return [];
    const response = await fetch(url);
    if (!response.ok) throw new Error(`The instruments index answered ${response.status}`);
    const instruments = (await response.json()).instruments ?? [];
    index = { at: Date.now(), instruments };
    return instruments;
}

/** The instruments there are to play ([] when real instruments are off, or out of reach). */
export async function instrumentNames() {
    try {
        return (await readIndex()).map((i) => i.name);
    } catch (error) {
        console.info('No real instruments just now', error?.message ?? error);
        return [];
    }
}

/** Which zone plays a key (pitched) or a piece (drums) at a velocity; null when none does. */
export function chooseZone(manifest, { note = null, piece = null, velocity = DEFAULT_VELOCITY }) {
    const v = Math.max(1, Math.min(127, Math.round(velocity)));
    if (manifest.kind === 'drums') {
        const zones = manifest.zones.filter((z) => z.piece === piece && v >= z.velLo && v <= z.velHi);
        if (!zones.length) return null;
        const key = `${manifest.name}:${piece}:${zones[0].velLo}`;
        const turn = rr.get(key) ?? 0;
        rr.set(key, turn + 1);
        return [...zones].sort((a, b) => (a.rr ?? 1) - (b.rr ?? 1))[turn % zones.length];
    }
    const k = Math.round(note);
    return manifest.zones.find((z) => k >= z.lo && k <= z.hi && v >= z.velLo && v <= z.velHi)
        ?? manifest.zones.find((z) => k >= z.lo && k <= z.hi) ?? null;
}

/**
 * Where the sound starts in a decode (the contract's step 2), in seconds: past the encoder's
 * priming when the decoder kept it, else 0. `decodedLength` is in frames at `rate`.
 */
export function soundStart(decodedLength, zone, sampleRate, rate = sampleRate) {
    const kept = decodedLength >= ((zone.frames + zone.encoderDelay / 2) * rate) / sampleRate;
    return kept ? zone.encoderDelay / sampleRate : 0;
}

/** The gain for a note: the set's level, smoothed between velocity layers. */
export function noteGain(manifest, zone, velocity) {
    return (manifest.gain ?? 1) * Math.min(1, (velocity / (zone.velHi || 127)) ** 1.5);
}

async function decodeAll(base, manifest) {
    // Decoded at the set's own rate, so no resampling moves the priming (the start rule holds as is).
    const decoder = new OfflineAudioContext(manifest.channels ?? 1, 1, manifest.sampleRate);
    const files = [...new Set(manifest.zones.map((z) => z.file))];
    const buffers = new Map();
    for (let i = 0; i < files.length; i += LOADS_AT_ONCE) {
        await Promise.all(files.slice(i, i + LOADS_AT_ONCE).map(async (file) => {
            const response = await fetch(new URL(file, base));
            if (!response.ok) throw new Error(`${file} answered ${response.status}`);
            buffers.set(file, await decoder.decodeAudioData(await response.arrayBuffer()));
        }));
    }
    return buffers;
}

/**
 * Loads an instrument (once): `{ name, title, kind }`, or null when it can't be played here. The
 * whole set is decoded before it plays, so a part never mixes samples with gaps.
 */
export function loadInstrument(name) {
    if (!loaded.has(name)) {
        loaded.set(name, (async () => {
            const entry = (await readIndex()).find((i) => i.name === name);
            if (!entry) return null;
            const response = await fetch(entry.manifest);
            if (!response.ok) throw new Error(`The ${name} manifest answered ${response.status}`);
            const manifest = await response.json();
            if (manifest.format !== FORMAT) return null;
            const buffers = await decodeAll(new URL(entry.manifest, location.href), manifest);
            return { name, title: manifest.title, kind: manifest.kind, manifest, buffers };
        })().catch((error) => {
            console.info(`The ${name} samples aren't here; the synth plays instead`, error?.message ?? error);
            loaded.delete(name); // tried again next time (offline now, say)
            return null;
        }));
    }
    return loaded.get(name).then((i) => (i ? { name: i.name, title: i.title, kind: i.kind } : null));
}

/** One sample on a context: its source and gain, the attack at `when` (on that context's clock). */
function schedule(ctx, destination, instrument, { note = null, piece = null, when, duration = null, velocity = DEFAULT_VELOCITY }) {
    const { manifest, buffers } = instrument;
    const zone = chooseZone(manifest, { note, piece, velocity });
    const buffer = zone && buffers.get(zone.file);
    if (!buffer) return;
    const source = ctx.createBufferSource();
    source.buffer = buffer;
    if (manifest.kind !== 'drums') source.playbackRate.value = 2 ** ((note - zone.root) / 12 + (zone.tune ?? 0) / 1200);
    const gain = ctx.createGain();
    const level = noteGain(manifest, zone, velocity);
    gain.gain.setValueAtTime(level, 0);
    source.connect(gain).connect(destination);
    const offset = soundStart(buffer.length, zone, manifest.sampleRate, buffer.sampleRate);
    // The attack on the beat: started `onset` early (or that much further in, at the very start).
    const at = when - (zone.onset ?? 0);
    const start = Math.max(0, at);
    source.start(start, offset + (start - at));
    if (manifest.kind !== 'drums' && duration !== null) {
        const off = when + duration;
        const release = manifest.release ?? 0.3;
        gain.gain.setValueAtTime(level, off);
        gain.gain.linearRampToValueAtTime(0, off + release);
        source.stop(off + release + 0.01);
    }
}

/**
 * Notes played by an instrument into a mono Float32Array at `sampleRate`, from beat 0, or null (use
 * the synth). Pitched: `{ beat, length, pitch, velocity? }`; drums: `{ beat, piece, velocity? }`.
 * `transpose` moves pitched notes; `speed` below 1 slows the beat (the samples keep their pitch).
 */
export async function renderNotes(name, notes, { bpm, sampleRate, transpose = 0, speed = 1, endBeat = null }) {
    if (!notes.length || typeof OfflineAudioContext !== 'function') return null;
    await loadInstrument(name);
    const instrument = await loaded.get(name);
    if (!instrument) return null;
    const beatSeconds = 60 / bpm / speed;
    const last = Math.max(endBeat ?? 0, ...notes.map((n) => n.beat + (n.length ?? 0)));
    const seconds = last * beatSeconds + (instrument.manifest.release ?? 0.3) + 2; // the last note rings out
    const ctx = new OfflineAudioContext(1, Math.ceil(seconds * sampleRate), sampleRate);
    for (const n of notes) {
        schedule(ctx, ctx.destination, instrument, {
            note: n.pitch === undefined ? null : n.pitch + transpose,
            piece: n.piece ?? null,
            when: n.beat * beatSeconds,
            duration: n.length === undefined ? null : n.length * beatSeconds,
            velocity: n.velocity ?? DEFAULT_VELOCITY,
        });
    }
    const rendered = await ctx.startRendering();
    return rendered.getChannelData(0);
}

/** One note (or a drum piece) now on the engine's context, its attack at `when` (that clock's seconds). */
export async function playNote(name, { note = null, piece = null, when = null, duration = 1, velocity = DEFAULT_VELOCITY }) {
    await loadInstrument(name);
    const instrument = await loaded.get(name);
    if (!instrument || !engine.ctx) return false;
    schedule(engine.ctx, engine.playBus, instrument, { note, piece, when: when ?? engine.ctx.currentTime + 0.03, duration, velocity });
    return true;
}
