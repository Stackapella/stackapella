// The soundtrack: Amazing Grace sung by the app's real singers (the song's four default real-voice
// guides, fetched from the public catalogue by `render.py --voices` into voices/, never committed),
// with their breaths between phrases ducked (breaths.js), the app's soft "oo" pad (web/js/synth.js renderBacking) under the last chord and a few glass
// chimes, mixed on an OfflineAudioContext so it comes out the same every time. The arrangement follows
// the cut (score.js CUE): the soprano alone, then the alto, tenor and bass joining a phrase at a time;
// the second time through all four from the pickup; the last chord held under the pad, then it all
// folds back into the one voice whose pickup starts the next loop. Two loops are rendered and the
// second kept, so the reverb tail of the last chord carries over the loop's seam the way it will when
// the page loops it.

import { renderBacking, renderPart } from '/web/js/synth.js';
import { duckBreaths, gaps } from './breaths.js';
import { rng } from './lib.js';
import { BPM, CUE, CUT, DUR, END, FROM, JOIN_RAMP, PARTS, PASS2, PASSES, S, SC, SEASONAL, SPB, joinAt, sec } from './score.js';

const RATE = 48000;
const PRIMING_FRAMES = 1024; // ffmpeg's AAC encoder delay, as web/js/recorded-guides.js skips it

/** A small room: stereo noise under an exponential decay, from a fixed seed. */
function room(ctx, seconds = 2.4) {
    const n = Math.round(seconds * RATE), ir = ctx.createBuffer(2, n, RATE), r = rng(7);
    for (let c = 0; c < 2; c++) {
        const d = ir.getChannelData(c);
        const pre = Math.round(0.018 * RATE);
        for (let i = pre; i < n; i++) {
            const t = (i - pre) / RATE;
            d[i] = (r() * 2 - 1) * Math.exp(-t * 2.9) * (1 - Math.min(0.6, t * 0.3));
        }
    }
    return ir;
}

/** A glass chime: a few inharmonic partials, struck, high and quiet. */
function chime(ctx, out, at, midi, level = 0.05) {
    const hz = 440 * 2 ** ((midi - 69) / 12);
    [[1, 1, 1.6], [2.76, 0.4, 2.6], [5.4, 0.18, 4], [8.93, 0.08, 6]].forEach(([ratio, amp, decay]) => {
        const o = ctx.createOscillator(), g = ctx.createGain();
        o.frequency.value = hz * ratio;
        g.gain.setValueAtTime(0, at);
        g.gain.linearRampToValueAtTime(level * amp, at + 0.004);
        g.gain.setTargetAtTime(0, at + 0.004, 1 / decay / 2.2);
        o.connect(g).connect(out);
        o.start(at);
        o.stop(at + 3);
    });
}

/**
 * Each part's real voice, as samples at RATE starting at the template's beat 0: decoded, its priming
 * skipped (as recorded-guides.js does), cut to its exact length and levelled to the same loudness
 * while singing, so no singer's take is louder than another's.
 */
let voices = null;
async function realVoices() {
    if (voices) return voices;
    const meta = S.voices;
    if (!meta) throw new Error('No real voices: run `python3 render.py --voices` first (it fetches them from the public catalogue)');
    const ctx = new OfflineAudioContext(1, 1, RATE);
    voices = await Promise.all(PARTS.map(async (key) => {
        const v = meta[key];
        const decoded = (await ctx.decodeAudioData(await (await fetch(`voices/${key}.m4a`)).arrayBuffer())).getChannelData(0);
        const scale = RATE / v.sampleRate, expected = Math.round(v.frames * scale), priming = Math.round(PRIMING_FRAMES * scale);
        const start = decoded.length >= expected + priming ? priming : 0;
        const samples = decoded.slice(start, start + expected);
        // the level while singing: the RMS of the louder half of 50 ms windows
        const win = Math.round(0.05 * RATE), rms = [];
        for (let i = 0; i + win <= samples.length; i += win) {
            let s = 0;
            for (let k = i; k < i + win; k++) s += samples[k] * samples[k];
            rms.push(Math.sqrt(s / win));
        }
        rms.sort((a, b) => b - a);
        const loud = rms.slice(0, Math.max(1, rms.length >> 1)).reduce((a, b) => a + b, 0) / Math.max(1, rms.length >> 1);
        const gain = 0.12 / (loud || 1);
        for (let i = 0; i < samples.length; i++) samples[i] *= gain;
        // the breaths between phrases, ducked (breaths.js; Hillel: "can we cut the breaths from the audio?")
        duckBreaths(samples, RATE, gaps(S.song.parts.find((p) => p.key === key).guide.notes, SPB));
        return { samples, offset: v.offsetFrames / v.sampleRate };
    }));
    return voices;
}

/** The first note of part `i` at or after `beat` (a voice fades in just before it, on the breath). */

/** The whole mix: `loops` copies of the film, end to end. */
async function mix(loops) {
    const real = await realVoices();
    const ctx = new OfflineAudioContext(2, Math.round(DUR * loops * RATE), RATE);
    const comp = ctx.createDynamicsCompressor();
    comp.threshold.value = -16; comp.ratio.value = 2.5; comp.attack.value = 0.02; comp.release.value = 0.25; comp.knee.value = 8;
    comp.connect(ctx.destination);
    const dry = ctx.createGain(); dry.gain.value = 0.86; dry.connect(comp);
    const verb = ctx.createConvolver(); verb.normalize = true; verb.buffer = room(ctx);
    const wet = ctx.createGain(); wet.gain.value = 0.22; verb.connect(wet).connect(comp);
    const bus = ctx.createGain(); bus.connect(dry); bus.connect(verb);

    // [gain, pan] for each part: the tune a touch forward, the alto and tenor either side, the bass centred
    const PLACE = [[1.0, 0], [0.82, -0.3], [0.8, 0.3], [0.92, 0]];
    // the film's clock is the song's from FROM (v5's social cut starts at phrase three)
    const at0 = (beat) => sec(beat - FROM);
    const foldA = at0(CUE.fold[0]), foldB = at0(CUE.fold[1]);
    for (let loop = 0; loop < loops; loop++) {
        const t0 = loop * DUR;
        real.forEach((v, i) => {
            const buf = ctx.createBuffer(1, v.samples.length, RATE);
            buf.copyToChannel(v.samples, 0);
            const [level, pan] = PLACE[i];
            for (let pass = 0; pass < PASSES; pass++) {
                const src = ctx.createBufferSource(), g = ctx.createGain(), p = ctx.createStereoPanner();
                src.buffer = buf;
                p.pan.value = pan;
                const at = t0 + pass * sec(PASS2) + v.offset - sec(FROM);
                if (pass === 0 && i > 0) {
                    // joins at its phrase (the social: its half phrase): silent until just before its first note there
                    const from = t0 + joinAt(i) - sec(FROM);
                    g.gain.setValueAtTime(0, t0);
                    g.gain.setValueAtTime(0, from);
                    g.gain.linearRampToValueAtTime(level, from + JOIN_RAMP);
                } else g.gain.setValueAtTime(level, t0);
                // the fold: the lower voices step away first, the tune last
                if (pass === PASSES - 1) g.gain.setTargetAtTime(0, t0 + (i ? foldA : at0(CUE.fold[1] - 0.6)), i ? (foldB - foldA) / 3 : 0.18);
                src.connect(g).connect(p).connect(bus);
                // a pass that began before the film did (the social's) starts part way into the take
                if (at >= t0) src.start(at); else src.start(t0, t0 - at);
            }
        });

        // the soft "oo" pad, from the last line the second time, swelling under the held chord
        const padFrom = (PASSES - 1) * PASS2 + 36, padAt = t0 + at0(padFrom), lastEnd = t0 + at0(PASSES * PASS2);
        const chords = S.chords.filter((c) => c.beat >= padFrom).map((c) => ({ ...c }));
        const pad = renderBacking(chords.map((c) => ({ ...c, beat: c.beat - padFrom })), { bpm: BPM, sampleRate: RATE, endBeat: END - padFrom, voice: 'pad' });
        const pb = ctx.createBuffer(1, Math.max(1, pad.length), RATE); pb.copyToChannel(pad, 0);
        const ps = ctx.createBufferSource(); ps.buffer = pb;
        const pg = ctx.createGain();
        pg.gain.setValueAtTime(0, padAt);
        pg.gain.linearRampToValueAtTime(0.35, padAt + 1.5);
        pg.gain.setValueAtTime(0.35, lastEnd - 1.2);
        pg.gain.linearRampToValueAtTime(0.75, lastEnd + 0.3);
        pg.gain.setTargetAtTime(0, t0 + foldA, (foldB - foldA) / 3);
        ps.connect(pg).connect(bus);
        ps.start(padAt);

        // the chimes: every note you hit in the first line, each friend joining, and the end card
        const ch = ctx.createGain(); ch.connect(dry); ch.connect(verb);
        for (const n of S.parts[0]) if (n.beat >= FROM && n.beat < FROM + 6 && n.hit != null) chime(ctx, ch, t0 + at0(n.hit), n.pitch + 24, 0.03);
        if (CUT === 'hero') CUE.friends.forEach((b, i) => chime(ctx, ch, t0 + at0(b), [74, 79, 83][i] + 12, 0.04));
        [0, 0.07, 0.14, 0.21].forEach((d, i) => chime(ctx, ch, t0 + at0(CUE.end) + 0.9 + d, [79, 83, 86, 91][i], 0.035));
    }
    return ctx.startRendering();
}

/**
 * A season cut (the carols', Hanukkah's): the song's parts in the app's own sung guide voice (web/js/synth.js renderPart, as the
 * song's page plays them: the catalogue has no real voices for them), each from the beat it joins at, in the same
 * room, a quiet chime in the song's key as each joins. It does not loop: the last chord fades under the end card.
 */
async function mixCarols() {
    const ctx = new OfflineAudioContext(2, Math.round(DUR * RATE), RATE);
    const comp = ctx.createDynamicsCompressor();
    comp.threshold.value = -16; comp.ratio.value = 2.5; comp.attack.value = 0.02; comp.release.value = 0.25; comp.knee.value = 8;
    comp.connect(ctx.destination);
    const dry = ctx.createGain(); dry.gain.value = 0.86; dry.connect(comp);
    const verb = ctx.createConvolver(); verb.normalize = true; verb.buffer = room(ctx);
    const wet = ctx.createGain(); wet.gain.value = 0.26; verb.connect(wet).connect(comp);
    const bus = ctx.createGain(); bus.connect(dry); bus.connect(verb);
    const PLACE = [[1.0, 0], [0.8, -0.3], [0.78, 0.3], [0.9, 0]];
    // a cut in real voices (`real`): each take from the template's beat 0, let in at its join as mix() does
    const real = SC.real ? await realVoices() : null;
    PARTS.forEach((key, i) => {
        const level = (real ? 1 : 0.4) * PLACE[i][0];
        let pcm, at = 0;
        if (real) { pcm = real[i].samples; at = real[i].offset; }
        else pcm = renderPart(S.parts[i].filter((n) => n.beat >= CUE.enter[i] - 1e-6), { bpm: BPM, sampleRate: RATE, human: 'full', seed: 11 + i });
        const buf = ctx.createBuffer(1, pcm.length, RATE); buf.copyToChannel(pcm, 0);
        const src = ctx.createBufferSource(), g = ctx.createGain(), p = ctx.createStereoPanner();
        src.buffer = buf; p.pan.value = PLACE[i][1];
        if (real && i > 0) {
            g.gain.setValueAtTime(0, 0);
            g.gain.setValueAtTime(0, joinAt(i));
            g.gain.linearRampToValueAtTime(level, joinAt(i) + JOIN_RAMP);
        } else g.gain.setValueAtTime(level, 0);
        if (SC.fade) {
            // the voices fade out on the held chord (the Reddit cut), the card's chimes after them still heard
            g.gain.setValueAtTime(level, sec(SC.fade[0]));
            g.gain.linearRampToValueAtTime(0, sec(SC.fade[1]));
        } else g.gain.setTargetAtTime(0, DUR - 1.25, 0.3);
        src.connect(g).connect(p).connect(bus);
        if (at >= 0) src.start(at); else src.start(0, -at);
    });
    const ch = ctx.createGain(); ch.connect(dry); ch.connect(verb);
    [1, 2, 3].forEach((i) => chime(ctx, ch, sec(CUE.enter[i]), [88, 91, 96][i - 1] + SC.key, 0.022));
    [0, 0.07, 0.14, 0.21].forEach((d, i) => chime(ctx, ch, sec(CUE.end) + 0.9 + d, [79, 84, 88, 91][i] + SC.key, 0.03));
    return ctx.startRendering();
}

/** The soundtrack: exactly DUR seconds that loop, as an AudioBuffer (the second of two loops). */
export async function soundtrack() {
    const n = Math.round(DUR * RATE), out = new AudioBuffer({ numberOfChannels: 2, length: n, sampleRate: RATE });
    const off = SEASONAL ? 0 : n, two = SEASONAL ? await mixCarols() : await mix(2);
    let peak = 0;
    for (let c = 0; c < 2; c++) for (const v of two.getChannelData(c).subarray(off, off + n)) peak = Math.max(peak, Math.abs(v));
    // about -16 LUFS: a page's video someone chose to unmute should not jump out of the speakers
    const gain = 0.52 / (peak || 1);
    for (let c = 0; c < 2; c++) {
        const d = two.getChannelData(c).subarray(off, off + n), o = out.getChannelData(c);
        for (let i = 0; i < n; i++) o[i] = d[i] * gain;
    }
    return out;
}

/** The soundtrack as a 16-bit stereo WAV, base64 (render.py asks for this). */
export async function wav() {
    const b = await soundtrack(), n = b.length, data = new DataView(new ArrayBuffer(44 + n * 4));
    const str = (o, s) => [...s].forEach((ch, i) => data.setUint8(o + i, ch.charCodeAt(0)));
    str(0, 'RIFF'); data.setUint32(4, 36 + n * 4, true); str(8, 'WAVE'); str(12, 'fmt ');
    data.setUint32(16, 16, true); data.setUint16(20, 1, true); data.setUint16(22, 2, true);
    data.setUint32(24, RATE, true); data.setUint32(28, RATE * 4, true); data.setUint16(32, 4, true); data.setUint16(34, 16, true);
    str(36, 'data'); data.setUint32(40, n * 4, true);
    const L = b.getChannelData(0), R = b.getChannelData(1);
    for (let i = 0; i < n; i++) {
        data.setInt16(44 + i * 4, Math.max(-1, Math.min(1, L[i])) * 32767, true);
        data.setInt16(46 + i * 4, Math.max(-1, Math.min(1, R[i])) * 32767, true);
    }
    const bytes = new Uint8Array(data.buffer);
    let s = '';
    for (let i = 0; i < bytes.length; i += 0x8000) s += String.fromCharCode(...bytes.subarray(i, i + 0x8000));
    return btoa(s);
}
