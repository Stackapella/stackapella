import { analyzeTrain, chirp, clickTrain, crossCorrelate, fft, isSilent, peaks } from '/js/dsp.js';
import { encodeWav } from '/js/wav.js';
import { assert, close, test } from './harness.js';

const sr = 48000;

/** `train` played into a room: delayed, quieter, with noise and an echo. */
function throughARoom(train, delay, extra = 48000) {
    let seed = 1;
    const random = () => ((seed = (seed * 16807) % 2147483647) / 2147483647) * 2 - 1;
    const out = new Float32Array(train.signal.length + delay + extra);
    train.signal.forEach((v, i) => {
        out[i + delay] += 0.3 * v;
        if (i + delay + 700 < out.length) out[i + delay + 700] += 0.1 * v;
    });
    for (let i = 0; i < out.length; i++) out[i] += 0.02 * random();
    return out;
}

test('fft: a forward and inverse transform gives back the input', () => {
    const re = Float64Array.from([1, 2, 3, 4, 0, -1, 0.5, 2]);
    const original = Float64Array.from(re);
    const im = new Float64Array(8);
    fft(re, im);
    fft(re, im, true);
    original.forEach((v, i) => close(re[i], v, 1e-9, `re[${i}]`));
});

test('cross-correlation peaks at the delay', () => {
    const reference = Float32Array.from([0, 1, -1, 0.5]);
    const signal = new Float32Array(32);
    signal.set(reference, 10);
    const c = crossCorrelate(signal, reference, 20);
    assert(c.indexOf(Math.max(...c)) === 10, 'peak not at 10');
});

test('calibration finds the round trip of a noisy recording with an echo, to the sample', () => {
    const train = clickTrain(sr);
    const result = analyzeTrain(throughARoom(train, 1234), train, { maxLag: sr });
    assert(result.detected === 8, `found ${result.detected} of 8`);
    close(result.lagFrames, 1234, 0.5, 'lag');
});

test('a round trip longer than any chirp gap (Bluetooth) is not mistaken for a shorter one', () => {
    const train = clickTrain(sr);
    close(analyzeTrain(throughARoom(train, 19200), train, { maxLag: sr }).lagFrames, 19200, 0.5, 'lag');
});

test('a missing chirp is skipped without moving the answer', () => {
    const train = clickTrain(sr);
    const recording = throughARoom(train, 500);
    recording.fill(0, train.positions[1] + 500, train.positions[1] + 500 + train.template.length);
    const result = analyzeTrain(recording, train, { maxLag: sr });
    assert(result.detected === 7 && result.reliable, `found ${result.detected}`);
    close(result.lagFrames, 500, 0.5, 'lag');
});

test('silence is not a reliable calibration', () => {
    const train = clickTrain(sr);
    const result = analyzeTrain(new Float32Array(train.signal.length + sr), train, { maxLag: sr });
    assert(!result.reliable && result.detected === 0, 'silence was reliable');
});

test('a compensated recording reports the residual, which can be negative', () => {
    const train = clickTrain(sr);
    close(analyzeTrain(throughARoom(train, 2400 - 30, 4800), train, { maxLag: 4800, preFrames: 2400 }).lagFrames, -30, 0.5, 'residual');
});

test('peaks keep the loudest sample of each bucket', () => {
    const out = peaks(Float32Array.from([0.1, -0.5, 0.2, 0.3, 0, -0.9]), 2);
    assert(out.length === 3, 'length');
    close(out[0], 0.5, 1e-6, 'first');
    close(out[2], 0.9, 1e-6, 'last');
});

test('a silent mic is spotted', () => {
    assert(isSilent(new Float32Array(100)), 'zeros are silent');
    assert(!isSilent(Float32Array.from([0, 0, 1e-9])), 'a tiny signal is not silent');
});

test('a WAV export is 16-bit PCM, interleaved', async () => {
    const blob = encodeWav([Float32Array.from([1, -1]), Float32Array.from([0, 0.5])], 44100);
    const view = new DataView(await blob.arrayBuffer());
    assert(view.getUint16(22, true) === 2 && view.getUint32(24, true) === 44100 && view.getUint16(34, true) === 16, 'header');
    assert(view.getInt16(44, true) === 32767 && view.getInt16(46, true) === 0 && view.getInt16(48, true) === -32768, 'samples');
    assert(blob.size === 44 + 8, 'size');
});

test('a chirp starts and ends at silence', () => {
    const c = chirp(sr);
    assert(c.length === 480 && Math.abs(c[0]) < 1e-6 && Math.abs(c[479]) < 1e-3, 'window');
});
