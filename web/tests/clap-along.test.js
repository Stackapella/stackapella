// Clap along (js/timing-check.js measureClaps): a simulated clapper on loopback, at known round trips. It hears the
// clicks late by the round trip and claps a little ahead of each (the usual lead), with a human's ±20 ms, finds the beat
// on its first claps, now and then misses one or claps twice, in a room with a little noise and the clicks' bleed.
import { CLAP_GAP_SECONDS, CLAP_LEAD_MS, clapClicks, clapDelay, clapOnsets, measureClaps } from '/js/timing-check.js';
import { assert, test } from './harness.js';

const RATE = 48000;

/** A seeded normal draw. */
function random(seed) {
    let s = seed;
    const uniform = () => ((s = (s * 16807) % 2147483647) / 2147483647);
    return () => Math.sqrt(-2 * Math.log(uniform() + 1e-12)) * Math.cos(2 * Math.PI * uniform());
}

/** A clap: a burst of noise that dies away in about 30 ms. */
function clapAt(out, at, level, normal) {
    const start = Math.round(at * RATE);
    for (let i = 0; i < 0.04 * RATE && start + i < out.length; i++) if (start + i >= 0) out[start + i] += level * Math.exp(-i / (0.008 * RATE)) * Math.max(-1, Math.min(1, normal() * 0.5));
}

/**
 * What the mic hears while `signal` (the clicks) plays: claps `roundTrip - lead` ms after each click, ± `jitter` ms; the
 * first `lockOn` claps late or missing; `missed` and `doubled` click indices; room noise and the clicks' bleed.
 */
function clapper({ roundTrip, lead = CLAP_LEAD_MS, jitter = 20, lockOn = 3, missed = [], doubled = [], only = Infinity, seed = 5 }) {
    return async (signal, captureLength) => {
        const normal = random(seed);
        const out = new Float32Array(captureLength);
        for (let i = 0; i < out.length; i++) out[i] = 0.002 * normal();
        for (let i = 0; i < signal.length; i++) out[i] += 0.01 * signal[i]; // earbuds leak a little into the mic
        const { positions } = clapClicks(RATE);
        positions.forEach((position, i) => {
            if (missed.includes(i) || i >= only) return;
            if (i < lockOn && i % 2 === 0) return; // finding the beat: some missed...
            const late = i < lockOn ? 0.12 : 0; // ...the rest late
            const at = position / RATE + (roundTrip - lead) / 1000 + late + (jitter * normal()) / 1000;
            clapAt(out, at, 0.5, normal);
            if (doubled.includes(i)) clapAt(out, at + 0.05, 0.3, normal);
        });
        return out;
    };
}

test('clap along: a clapper\'s round trip is found within 12 ms, from 120 to 450 ms, through a late lock-on, a missed clap and a double', async () => {
    for (const roundTrip of [120, 190, 280, 450]) {
        for (const seed of [3, 11]) {
            const found = await measureClaps({ measure: clapper({ roundTrip, missed: [7], doubled: [5], seed }), sampleRate: RATE });
            assert(found.ms !== null && Math.abs(found.ms - roundTrip) <= 12, `${roundTrip} ms (seed ${seed}): ${JSON.stringify(found)}`);
            assert(found.frames === Math.round((found.ms * RATE) / 1000) && found.claps >= 6, JSON.stringify(found));
        }
    }
});

test('clap along: a clapper who leads by 50 ms (the far end of the usual) reads 25 ms short, no more', async () => {
    const found = await measureClaps({ measure: clapper({ roundTrip: 200, lead: 50 }), sampleRate: RATE });
    assert(found.ms !== null && Math.abs(found.ms - 175) <= 12, JSON.stringify(found));
});

test('clap along: claps all over the place ask for another go; too few claps too', async () => {
    const uneven = await measureClaps({ measure: clapper({ roundTrip: 200, jitter: 80 }), sampleRate: RATE });
    assert(uneven.frames === null && uneven.reason === 'uneven', JSON.stringify(uneven));
    const few = await measureClaps({ measure: clapper({ roundTrip: 200, only: 7 }), sampleRate: RATE });
    assert(few.frames === null && few.reason === 'few', JSON.stringify(few));
    const silent = await measureClaps({ measure: clapper({ roundTrip: 200, only: 0 }), sampleRate: RATE });
    assert(silent.frames === null && silent.reason === 'few', `silence: ${JSON.stringify(silent)}`);
});

test('clap along: onsets are found where each clap starts, once each, and claps are read against the click before them', () => {
    const out = new Float32Array(RATE * 2);
    const normal = random(1);
    for (const at of [0.2, 0.8, 1.4]) clapAt(out, at, 0.5, normal);
    clapAt(out, 0.86, 0.25, normal); // an echo inside the first clap's ring
    const onsets = clapOnsets(out, RATE);
    assert(onsets.length === 3 && onsets.every((t, i) => Math.abs(t - [0.2, 0.8, 1.4][i]) < 0.004), JSON.stringify(onsets));
    // A 500 ms round trip lands claps nearly a whole gap later: still read whole, not as 0 ms.
    const claps = Array.from({ length: 9 }, (_, i) => (i + 3) * CLAP_GAP_SECONDS + 0.5 - CLAP_LEAD_MS / 1000);
    const found = clapDelay(claps);
    assert(Math.abs(found.ms - 500) < 1, JSON.stringify(found));
});

test('wireless default: 140 ms on Apple (its browser figure when it reads like Bluetooth), 280 on Android, 250 elsewhere', async () => {
    const { wirelessTypicalMs } = await import('/js/audio.js');
    const iphone = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_4 like Mac OS X) AppleWebKit/605.1.15';
    const mac = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/130.0 Safari/537.36';
    const android = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 Chrome/130.0 Mobile Safari/537.36';
    const windows = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/130.0 Safari/537.36';
    assert(wirelessTypicalMs({ ua: iphone, outputLatency: 0 }) === 140, 'iPhone, the context not running');
    assert(wirelessTypicalMs({ ua: iphone, outputLatency: 512 / 48000 }) === 140, 'Safari\'s anti-fingerprinting value');
    assert(wirelessTypicalMs({ ua: mac, outputLatency: 0.009 }) === 140, 'AirPods on the call profile');
    assert(wirelessTypicalMs({ ua: mac, outputLatency: 0.161 }) === 176, 'a Bluetooth-like figure, plus the mic');
    assert(wirelessTypicalMs({ ua: android, outputLatency: 0.2 }) === 280, 'Android\'s own figure is never used');
    assert(wirelessTypicalMs({ ua: windows, outputLatency: 0.2 }) === 250, 'elsewhere');
});
