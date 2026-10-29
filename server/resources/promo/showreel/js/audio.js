// The soundtrack, rendered in code from the app's own guide voice (web/js/synth.js: renderPart sings a
// part on "oo", renderBacking the soft pad) and mixed on an OfflineAudioContext, so it comes out the
// same every time. The arrangement follows the cut (score.js CUE): one voice, then the alto, tenor
// and bass join as their lanes slide in, you hum alone in the creator until the harmony blooms, the
// pad comes in with the chord blocks, friends double the parts, and it all folds back into the one
// voice whose pickup starts the next loop. Two loops are rendered and the second kept, so the reverb
// tail of the last chord carries over the loop's seam the way it will when the page loops it.

import { renderBacking, renderPart } from '/web/js/synth.js';
import { rng } from './lib.js';
import { BEATS, BPM, CUE, DUR, S, sec } from './score.js';

const RATE = 48000;

function buffer(ctx, samples) {
    const b = ctx.createBuffer(1, Math.max(1, samples.length), RATE);
    b.copyToChannel(samples, 0);
    return b;
}

/** A small room: stereo noise under an exponential decay, from a fixed seed. */
function room(ctx, seconds = 2.4) {
    const n = Math.round(seconds * RATE), ir = ctx.createBuffer(2, n, RATE), r = rng(7);
    for (let c = 0; c < 2; c++) {
        const d = ir.getChannelData(c);
        const pre = Math.round(0.018 * RATE);
        for (let i = pre; i < n; i++) {
            const t = (i - pre) / RATE;
            // brighter early, darker late: a crude air absorption by averaging more of the tail
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

/** The whole mix: `loops` copies of the 30-second score, end to end. */
async function mix(loops) {
    const ctx = new OfflineAudioContext(2, Math.round(DUR * loops * RATE), RATE);
    const comp = ctx.createDynamicsCompressor();
    comp.threshold.value = -16; comp.ratio.value = 2.5; comp.attack.value = 0.02; comp.release.value = 0.25; comp.knee.value = 8;
    comp.connect(ctx.destination);
    const dry = ctx.createGain(); dry.gain.value = 0.82; dry.connect(comp);
    const verb = ctx.createConvolver(); verb.normalize = true; verb.buffer = room(ctx);
    const wet = ctx.createGain(); wet.gain.value = 0.3; verb.connect(wet).connect(comp);
    const bus = ctx.createGain(); bus.connect(dry); bus.connect(verb);

    const options = { bpm: BPM, sampleRate: RATE };
    const pick = (notes, keep) => notes.filter((n) => keep(n.beat)).map((n) => ({ beat: n.beat, length: n.length, pitch: n.pitch }));
    const inHum = (b) => b >= CUE.hum[0] && b < CUE.hum[1];
    const voices = [];
    // [part, notes, gain, pan, detune (semitones), delay (beats), lowpass, entry beat]
    const add = (part, notes, o) => voices.push({ part, notes, gain: 1, pan: 0, detune: 0, delay: 0, lowpass: 0, ...o });
    const [sop, alto, tenor, bass] = S.parts;
    add(0, pick(sop, (b) => !inHum(b)), { gain: 1.0 });
    add(0, pick(sop, inHum), { gain: 0.9, lowpass: 950 });
    add(1, pick(alto, (b) => b >= CUE.enter[1] && !inHum(b)), { gain: 0.78, pan: -0.28 });
    add(2, pick(tenor, (b) => b >= CUE.enter[2] && !inHum(b)), { gain: 0.74, pan: 0.28 });
    add(3, pick(bass, (b) => b >= CUE.enter[3] && !inHum(b)), { gain: 0.92, pan: 0 });
    // friends: each part sung again, a few cents off and a hair late, from the side
    const twin = (i, from, o) => add(i, pick(S.parts[i], (b) => b >= from).map((n) => ({ ...n, beat: n.beat + o.delay })), { ...o, delay: 0 });
    twin(1, CUE.friends[0], { gain: 0.5, pan: -0.7, detune: 0.07, delay: 0.02 });
    twin(2, CUE.friends[1], { gain: 0.48, pan: 0.7, detune: -0.06, delay: 0.025 });
    twin(3, CUE.friends[2], { gain: 0.55, pan: -0.4, detune: 0.05, delay: 0.015 });
    twin(0, CUE.friends[2], { gain: 0.45, pan: 0.45, detune: -0.05, delay: 0.02 });

    const foldA = sec(CUE.fold[0]), foldB = sec(CUE.fold[1]);
    for (let loop = 0; loop < loops; loop++) {
        const t0 = loop * DUR;
        for (const v of voices) {
            if (!v.notes.length) continue;
            const samples = renderPart(v.notes, { ...options, transpose: v.detune });
            const src = ctx.createBufferSource(); src.buffer = buffer(ctx, samples);
            const g = ctx.createGain(), p = ctx.createStereoPanner();
            p.pan.value = v.pan;
            g.gain.setValueAtTime(v.gain, t0);
            // the mixer brings one voice forward on each beat of "me." (the landing's FORWARD and BACK)
            if (v.detune === 0 && v.lowpass === 0) {
                CUE.forward.forEach((b, k) => {
                    const at = t0 + sec(b);
                    g.gain.setTargetAtTime(v.gain * (v.part === CUE.forwardPart[k] ? 1.5 : 0.62), at, 0.05);
                });
                g.gain.setTargetAtTime(v.gain, t0 + sec(CUE.hum[0]), 0.05);
            }
            // the fold: everyone but the tune steps away first
            if (v.part !== 0 || v.detune !== 0) g.gain.setTargetAtTime(0, t0 + foldA, (foldB - foldA) / 3);
            else g.gain.setTargetAtTime(0, t0 + sec(CUE.fold[1] - 0.6), 0.18);
            let node = src;
            if (v.lowpass) { const f = ctx.createBiquadFilter(); f.type = 'lowpass'; f.frequency.value = v.lowpass; f.Q.value = 0.6; node = node.connect(f); }
            node.connect(g).connect(p).connect(bus);
            src.start(t0);
        }
        // the soft "oo" pad under the chord blocks, to the fold
        const chords = S.chords.filter((c) => c.beat >= CUE.pad).map((c) => ({ ...c }));
        const pad = renderBacking(chords, { ...options, endBeat: BEATS, voice: 'pad' });
        const ps = ctx.createBufferSource(); ps.buffer = buffer(ctx, pad);
        const pg = ctx.createGain();
        pg.gain.setValueAtTime(0, t0 + sec(CUE.pad));
        pg.gain.linearRampToValueAtTime(1.15, t0 + sec(CUE.pad) + 0.4);
        pg.gain.setTargetAtTime(0, t0 + foldA, (foldB - foldA) / 3);
        ps.connect(pg).connect(bus);
        ps.start(t0);

        // the chimes: every note you hit in the first line, each lane landing, the mark, the
        // gems the hum becomes, the blocks, the copied link, each friend, and the end
        const ch = ctx.createGain(); ch.gain.value = 1; ch.connect(dry); ch.connect(verb);
        for (const n of sop) if (n.beat < 12 && n.hit != null) chime(ctx, ch, t0 + sec(n.hit), n.pitch + 24, 0.035);
        CUE.enter.slice(1).forEach((b, i) => chime(ctx, ch, t0 + sec(b), [62, 59, 55][i] + 24, 0.05));
        chime(ctx, ch, t0 + sec(CUE.mark), 79, 0.06); chime(ctx, ch, t0 + sec(CUE.mark) + 0.06, 86, 0.04);
        CUE.forward.forEach((b, i) => chime(ctx, ch, t0 + sec(b), [74, 66, 57, 50][CUE.forwardPart[i]] + 24, 0.03));
        for (const n of sop) if (n.beat >= CUE.hum[0] && n.beat < CUE.hum[1]) chime(ctx, ch, t0 + sec(n.beat + n.length), n.pitch + 24, 0.035);
        CUE.blocks.forEach((b, i) => chime(ctx, ch, t0 + sec(b), [60, 64, 67, 72][i] + 12, 0.04));
        chime(ctx, ch, t0 + sec(CUE.copied), 83, 0.035);
        CUE.friends.forEach((b, i) => chime(ctx, ch, t0 + sec(b), [74, 79, 83][i] + 12, 0.045));
        [0, 0.07, 0.14, 0.21].forEach((d, i) => chime(ctx, ch, t0 + sec(CUE.end) + d, [79, 83, 86, 91][i], 0.04));
    }
    return ctx.startRendering();
}

/** The soundtrack: exactly DUR seconds that loop, as an AudioBuffer (the second of two loops). */
export async function soundtrack() {
    const two = await mix(2);
    const n = Math.round(DUR * RATE), out = new AudioBuffer({ numberOfChannels: 2, length: n, sampleRate: RATE });
    let peak = 0;
    for (let c = 0; c < 2; c++) for (const v of two.getChannelData(c).subarray(n, 2 * n)) peak = Math.max(peak, Math.abs(v));
    // about -16 LUFS: a page's video someone chose to unmute should not jump out of the speakers
    const gain = 0.52 / (peak || 1);
    for (let c = 0; c < 2; c++) {
        const d = two.getChannelData(c).subarray(n, 2 * n), o = out.getChannelData(c);
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
