// A song's full mix for its page (Hillel: "expose the main audio for the song ... so previews on Google or AI
// responses can hear the audio"): every part together at the song's own key and tempo, with no metronome and no
// count-in. Each part sings as the app plays it: its real voice where the catalogue has one for this version of
// the song (cut as web/js/recorded-guides.js cuts it, its breaths between phrases ducked as the showreel's are),
// otherwise the app's own guide voice (web/js/guides.js renderGuide, sung in full as the public players sing it).
// The parts play at equal level, as the app's players mix them; render.py levels the whole mix afterwards.
// Mixed on an OfflineAudioContext, so the same song always gives the same file.

import { renderGuide } from '/web/js/guides.js';
import { trimDecoded } from '/web/js/recorded-guides.js';
import { duckBreaths, gaps } from '/server/resources/promo/showreel/js/breaths.js';

const RATE = 48000;
const LEAD = 0.12; // seconds of quiet before the first note
const TAIL = 1.6; // seconds after the last sound, for the room to settle
const FADE = 0.9; // the fade over the end of that
// Where each part sits, highest voice first: a gentle spread that still sums cleanly to one speaker.
const PANS = { 1: [0], 2: [-0.3, 0.3], 3: [-0.35, 0.35, 0], 4: [-0.4, 0.4, -0.15, 0.15] };
const ROOM = 0.1; // how much of the mix goes through the room (stereo only)

/** A small room: stereo noise under an exponential decay, from a fixed seed. */
function room(ctx, seconds = 1.8) {
    const n = Math.round(seconds * RATE), ir = ctx.createBuffer(2, n, RATE);
    let s = 7;
    const r = () => ((s = (s * 1664525 + 1013904223) >>> 0) / 4294967296);
    for (let c = 0; c < 2; c++) {
        const d = ir.getChannelData(c), pre = Math.round(0.015 * RATE);
        for (let i = pre; i < n; i++) d[i] = (r() * 2 - 1) * Math.exp(-((i - pre) / RATE) * 3.4);
    }
    return ir;
}

function wav(channels) {
    const n = channels[0].length, c = channels.length, bytes = new DataView(new ArrayBuffer(44 + n * c * 2));
    const text = (at, s) => [...s].forEach((ch, i) => bytes.setUint8(at + i, ch.charCodeAt(0)));
    text(0, 'RIFF'); bytes.setUint32(4, 36 + n * c * 2, true); text(8, 'WAVEfmt ');
    bytes.setUint32(16, 16, true); bytes.setUint16(20, 1, true); bytes.setUint16(22, c, true);
    bytes.setUint32(24, RATE, true); bytes.setUint32(28, RATE * c * 2, true); bytes.setUint16(32, c * 2, true); bytes.setUint16(34, 16, true);
    text(36, 'data'); bytes.setUint32(40, n * c * 2, true);
    for (let i = 0; i < n; i++) for (let k = 0; k < c; k++) bytes.setInt16(44 + (i * c + k) * 2, Math.round(Math.max(-1, Math.min(1, channels[k][i])) * 32767), true);
    return new Uint8Array(bytes.buffer);
}

let bytes = null;

/**
 * Mixes the song `slug` (its template, as linked here). `guides`: { partKey: the catalogue's recording, with `file`
 * (where render.py put its audio) } for the parts with a real voice. Returns what was mixed; the WAV is then read
 * in pieces with wavChunk (a long song is too much for one answer).
 */
window.mixSong = async (slug, guides = {}, { stereo = true } = {}) => {
    // the library's songs (the content, linked in), or one of the app's own
    let found = await fetch(`/server/database/templates/${slug}.json`);
    if (!found.ok) found = await fetch(`/web/templates/${slug}.json`);
    if (!found.ok) throw new Error(`no template here for ${slug}`);
    const song = await found.json();
    const spb = 60 / song.tempo;
    if (song.parts.some((p) => p.kind === 'instrument')) throw new Error(`${slug} has an instrument part: this mix sings voices only`);
    const decoder = new OfflineAudioContext(1, 1, RATE);
    const voices = await Promise.all(song.parts.map(async (part) => {
        const recording = guides[part.key];
        if (recording) {
            const decoded = (await decoder.decodeAudioData(await (await fetch(recording.file)).arrayBuffer())).getChannelData(0);
            const voice = trimDecoded(decoded, recording, RATE);
            const samples = Float32Array.from(voice.samples);
            duckBreaths(samples, RATE, gaps(part.guide.notes, spb));
            return { key: part.key, samples, offset: voice.offsetSeconds, guide: recording.id, credit: recording.credit ?? null };
        }
        const samples = await renderGuide({ templateId: song.id, templateVersion: song.version, part, tempo: song.tempo, sampleRate: RATE, human: 'full' });
        return { key: part.key, samples, offset: 0, guide: null, credit: null };
    }));
    // highest voice first, by where its notes sit
    const height = (part) => part.guide.notes.reduce((sum, n) => sum + n.pitch * n.length, 0) / part.guide.notes.reduce((sum, n) => sum + n.length, 0);
    const byHeight = [...song.parts].sort((a, b) => height(b) - height(a)).map((p) => p.key);
    const pans = PANS[Math.min(4, voices.length)];
    const sung = Math.max(...voices.map((v) => v.offset + v.samples.length / RATE));
    const length = LEAD + sung + TAIL;
    const ctx = new OfflineAudioContext(stereo ? 2 : 1, Math.ceil(length * RATE), RATE);
    const master = ctx.createGain();
    master.gain.setValueAtTime(1, 0);
    master.gain.setValueAtTime(1, length - FADE);
    master.gain.linearRampToValueAtTime(0, length);
    master.connect(ctx.destination);
    let wet = null;
    if (stereo) {
        const verb = ctx.createConvolver();
        verb.buffer = room(ctx);
        wet = ctx.createGain();
        wet.gain.value = ROOM;
        wet.connect(verb).connect(master);
    }
    const level = 0.6 / Math.sqrt(voices.length / 4 || 1); // the app's players: four guides at 0.6 each
    for (const v of voices) {
        const buffer = ctx.createBuffer(1, v.samples.length, RATE);
        buffer.copyToChannel(v.samples, 0);
        const source = ctx.createBufferSource(), gain = ctx.createGain();
        source.buffer = buffer;
        gain.gain.value = level;
        source.connect(gain);
        if (stereo) {
            const pan = ctx.createStereoPanner();
            pan.pan.value = pans[byHeight.indexOf(v.key) % pans.length] ?? 0;
            gain.connect(pan);
            pan.connect(master);
            pan.connect(wet);
        } else gain.connect(master);
        source.start(LEAD + v.offset);
    }
    const out = await ctx.startRendering();
    bytes = wav(stereo ? [out.getChannelData(0), out.getChannelData(1)] : [out.getChannelData(0)]);
    return {
        slug, title: song.title, version: song.version, tempo: song.tempo, key: song.key, credits: song.credits ?? null,
        duration: length, stereo, wavBytes: bytes.length,
        parts: voices.map((v) => ({ key: v.key, guide: v.guide, credit: v.credit, seconds: +(v.samples.length / RATE).toFixed(3) })),
    };
};

const CHUNK = 3 << 20;
window.wavChunks = () => Math.ceil(bytes.length / CHUNK);
window.wavChunk = (i) => {
    const piece = bytes.subarray(i * CHUNK, (i + 1) * CHUNK);
    let s = '';
    for (let k = 0; k < piece.length; k += 0x8000) s += String.fromCharCode(...piece.subarray(k, k + 0x8000));
    return btoa(s);
};

window.__ready = true;
