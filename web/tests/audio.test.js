// The device's audio settings (js/audio.js): what can be checked without a person and a mic.
import { audio, engine, leadSeconds, looksWireless, MIC_SETTLE_SECONDS, unlock } from '/js/audio.js';
import { assert, close, test } from './harness.js';

test('audio: wireless mics are spotted by their names, wired ones are not', () => {
    for (const label of ['AirPods Pro', "Hillel's AirPods", 'Hillel’s iPhone Microphone', 'Bose QC45 Bluetooth', 'Headset (Hands-Free AG Audio)', 'Default - AirPods Max', 'Galaxy Buds2']) {
        assert(looksWireless(label), `${label} should look wireless`);
    }
    for (const label of ['MacBook Pro Microphone', 'Scarlett 18i20 USB', 'External Microphone', 'Default - MacBook Pro Microphone', 'USB Audio CODEC', '', null]) {
        assert(!looksWireless(label), `${label} should look wired`);
    }
});

test("audio: an iPhone mic is a Continuity mic on a computer, but the phone's own mic on an iPhone", () => {
    assert(looksWireless('Hillel\u2019s iPhone Microphone', { appleMobile: false }), 'on a Mac it is wireless');
    assert(!looksWireless('iPhone Microphone', { appleMobile: true }), 'on the iPhone it is built in');
    assert(looksWireless('AirPods Pro', { appleMobile: true }), 'AirPods are wireless everywhere');
});

test('audio: the first click waits for the mic to settle, measured on the audio clock', async () => {
    await unlock();
    audio.micOpenedAt = null;
    close(leadSeconds(), 0.15, 1e-9, 'no mic opened: the usual lead');
    audio.micOpenedAt = engine.ctx.currentTime;
    close(leadSeconds(), MIC_SETTLE_SECONDS, 0.05, 'just opened: the whole settle');
    audio.micOpenedAt = engine.ctx.currentTime - 5;
    close(leadSeconds(), 0.15, 1e-9, 'long open: the usual lead');
    audio.micOpenedAt = null;
});
