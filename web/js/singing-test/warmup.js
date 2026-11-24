// The singing test's warm-up (docs/singing-test-v1.md): a 20-second glide on "oo", the same every time, so warming up
// is never a hidden difference. Nothing is measured. A soft tone glides from a fifth under the singer's note up to a
// fifth over and back, twice, scheduled entirely on the AudioContext clock (frequency ramps, a gain envelope).

import { engine, unlock } from '../audio.js';

export const WARMUP_SECONDS = 20;
const LEVEL = 0.12;
const hz = (midi) => 440 * 2 ** ((midi - 69) / 12);

/**
 * Plays the glide around `midi` (the singer's comfortable note, or a middle one before it's known). Returns
 * `{ endsAt, stop }`: the context time it ends, and a stop for leaving early.
 */
export async function glide(midi, { seconds = WARMUP_SECONDS } = {}) {
    await unlock();
    await engine.stop();
    const ctx = engine.ctx;
    const start = ctx.currentTime + 0.08;
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.type = 'triangle';
    const low = hz(midi - 7);
    const high = hz(midi + 7);
    const quarter = seconds / 4;
    osc.frequency.setValueAtTime(low, start);
    for (let k = 0; k < 2; k++) {
        osc.frequency.exponentialRampToValueAtTime(high, start + (2 * k + 1) * quarter);
        osc.frequency.exponentialRampToValueAtTime(low, start + (2 * k + 2) * quarter);
    }
    gain.gain.setValueAtTime(0, start);
    gain.gain.linearRampToValueAtTime(LEVEL, start + 0.4);
    gain.gain.setValueAtTime(LEVEL, start + seconds - 0.6);
    gain.gain.linearRampToValueAtTime(0, start + seconds);
    osc.connect(gain).connect(engine.playBus);
    osc.start(start);
    osc.stop(start + seconds + 0.05);
    const stop = () => {
        const now = ctx.currentTime;
        gain.gain.cancelScheduledValues(now);
        gain.gain.setValueAtTime(gain.gain.value, now);
        gain.gain.linearRampToValueAtTime(0, now + 0.08);
        osc.stop(now + 0.1);
    };
    return { endsAt: start + seconds, stop };
}
