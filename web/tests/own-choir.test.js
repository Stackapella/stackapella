// Hear yourself as a choir (js/own-choir.js): the clip is the take from the song's beat 0, at most 30 seconds, a 16-bit
// mono WAV, with where beat 0 falls in it; a take that started after beat 0 says so (null).
import { clipFrom, MAX_SECONDS } from '/js/own-choir.js';
import { assert, test } from './harness.js';

const RATE = 8000;
const take = (seconds) => new Float32Array(Math.round(seconds * RATE)).fill(0.25);

test('own choir: the clip starts at the song’s beat 0 and is at most 30 seconds', () => {
    // The take started 1 s before beat 0 (a count-in): the clip starts at beat 0, which is its frame 0.
    const early = clipFrom(take(40), RATE, -1);
    assert(early.offsetFrames === 0, `beat 0 at the clip’s start: ${early.offsetFrames}`);
    assert(early.frames === MAX_SECONDS * RATE, `30 seconds: ${early.frames}`);
    assert(early.wav.size === 44 + early.frames * 2, 'a 16-bit mono WAV');
    // A short take from beat 0: all of it.
    const short = clipFrom(take(12), RATE, 0);
    assert(short.offsetFrames === 0 && Math.abs(short.seconds - 12) < 1e-9, `${short.seconds}`);
});

test('own choir: a take that started after beat 0 has no beat 0 in it', () => {
    const late = clipFrom(take(20), RATE, 5);
    assert(late.offsetFrames === null, `${late.offsetFrames}`);
    assert(late.frames === 20 * RATE, 'all of it, from its start');
});
