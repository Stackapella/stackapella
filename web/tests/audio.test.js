// The device's audio settings (js/audio.js): what can be checked without a person and a mic.
import { audio, engine, leadSeconds, looksWireless, MIC_SETTLE_SECONDS, RECHECK_TAKES, recheckDue, recheckShown, tookTake, unlock } from '/js/audio.js';
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

test('audio: a fresh timing check is offered over Bluetooth after a reconnect or every RECHECK_TAKES takes, never on an estimate or wired', () => {
    const was = { route: audio.route, mic: audio.mic, latencyFrames: audio.latencyFrames, latencyEstimated: audio.latencyEstimated, reconnected: audio.reconnected };
    try {
        audio.mic = { deviceId: 'x', label: 'Test wired mic (audio.test)' };
        audio.route = 'bluetooth';
        audio.latencyFrames = 9000;
        audio.latencyEstimated = false;
        audio.reconnected = false;
        recheckShown(); // a clean count for this mic and route
        assert(recheckDue() === null, 'nothing yet');
        for (let i = 0; i < RECHECK_TAKES - 1; i++) tookTake();
        assert(recheckDue() === null, 'one take short');
        tookTake();
        assert(recheckDue() === 'takes', 'after RECHECK_TAKES takes');
        recheckShown();
        assert(recheckDue() === null, 'offered once, then the count starts again');
        audio.reconnected = true;
        assert(recheckDue() === 'reconnect', 'headphones reconnected');
        recheckShown();
        assert(recheckDue() === null && !audio.reconnected, 'the reconnect offered once');
        audio.reconnected = true;
        audio.latencyEstimated = true;
        assert(recheckDue() === null, 'an estimate: the check is offered another way');
        audio.latencyEstimated = false;
        audio.route = 'wired';
        assert(recheckDue() === null, 'wired headphones with a wired mic: steady, never offered');
        for (let i = 0; i < RECHECK_TAKES; i++) tookTake();
        assert(recheckDue() === null, 'wired takes are not counted');
        audio.mic = { deviceId: 'y', label: 'AirPods Pro (audio.test)' };
        assert(recheckDue() === 'reconnect', 'a wireless mic counts as wireless listening');
        recheckShown();
    } finally {
        Object.assign(audio, was);
    }
});

test("audio: a device change as the mic opens isn't a reconnect; one with the mic settled is", async () => {
    await unlock();
    const was = { route: audio.route, mic: audio.mic, micOpenedAt: audio.micOpenedAt, reconnected: audio.reconnected };
    try {
        audio.route = 'bluetooth';
        audio.mic = null;
        audio.reconnected = false;
        audio.micOpenedAt = null;
        engine.onDeviceChange();
        assert(!audio.reconnected, 'no mic open: not counted');
        audio.micOpenedAt = engine.ctx.currentTime;
        engine.onDeviceChange();
        assert(!audio.reconnected, 'just opened (granting the mic, AirPods to hands-free): not counted');
        audio.micOpenedAt = engine.ctx.currentTime - 10;
        engine.onDeviceChange();
        assert(audio.reconnected, 'settled: a reconnect');
        audio.reconnected = false;
        audio.route = 'wired';
        engine.onDeviceChange();
        assert(!audio.reconnected, 'wired: never');
    } finally {
        Object.assign(audio, was);
    }
});
