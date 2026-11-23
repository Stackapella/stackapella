// The choir take's check before sending (the coordinator's failure spec): under 17 s of whole phrases is `short`; a whole
// take passes, a loud breath before a line included (no edge check on the app: ai-modal is loosening the worker's rule).
import { takeProblem } from '/js/own-choir.js';
import { assert, test } from './harness.js';

const RATE = 8000;
const BEAT = 0.5; // seconds a beat
const PHRASES = [[0, 12], [12, 24], [24, 36], [36, 48]]; // four 6 s lines
// A take from one second before beat 0: singing (0.3) inside each line but its last 0.4 s (a breath's room), else `gap`.
function take(seconds, gap = 0.0005) {
    const samples = new Float32Array(Math.round(seconds * RATE));
    for (let i = 0; i < samples.length; i++) {
        const song = i / RATE - 1;
        const inLine = PHRASES.some(([from, to]) => song >= from * BEAT && song < to * BEAT - 0.4);
        samples[i] = (inLine ? 0.3 : gap) * Math.sin(i / 3);
    }
    return samples;
}
const check = (samples) => takeProblem(samples, RATE, { startSeconds: -1, beatSeconds: BEAT, phrases: PHRASES });

test('take problem: a whole take passes, a breath before a line too', () => {
    assert(check(take(26)) === null, String(check(take(26))));
    const samples = take(26);
    const at = Math.round((1 + 12 * BEAT - 0.1) * RATE); // 100 ms before the second line
    for (let i = at; i < at + RATE * 0.06; i++) samples[i] = 0.2 * Math.sin(i / 2);
    assert(check(samples) === null, 'a breath before a line was refused');
});

test('take problem: stopped before 17 s of whole lines is short', () => {
    assert(check(take(14)) === 'short', String(check(take(14))));
});

test('take problem: no phrases, no check', () => {
    assert(takeProblem(take(5), RATE, { startSeconds: -1, beatSeconds: BEAT, phrases: [] }) === null, 'checked without phrases');
});
