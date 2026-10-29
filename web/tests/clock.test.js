// The one visual clock (engine/clock.js): what's heard now, behind what's rendered by the output delay.
import { heardTime, outputDelay } from '/engine/clock.js';
import { close, test } from './harness.js';

const ctx = (fields) => ({ currentTime: 10, ...fields });

test('clock: with nothing reported, the screen follows the rendered time', () => {
    close(heardTime(ctx({})), 10, 1e-9, 'currentTime as it is');
    close(outputDelay(ctx({})), 0, 1e-9, 'no delay known');
});

test('clock: the base and output latency are taken off (Bluetooth\'s 200 ms too)', () => {
    close(heardTime(ctx({ baseLatency: 0.01, outputLatency: 0.2 })), 9.79, 1e-9, 'behind by both');
});

test('clock: the output timestamp, carried on to now, when it is further behind', () => {
    const stamp = { contextTime: 9.7, performanceTime: 1000 };
    close(heardTime(ctx({ getOutputTimestamp: () => stamp }), 1050), 9.75, 1e-9, 'the speaker\'s frame, 50 ms on');
});

test('clock: a timestamp that already counts the latency isn\'t taken off twice', () => {
    // The browser's stamp counts the device; currentTime less the same latency agrees with it.
    const stamp = { contextTime: 9.8, performanceTime: 1000 };
    close(heardTime(ctx({ outputLatency: 0.2, getOutputTimestamp: () => stamp }), 1000), 9.8, 1e-9, 'the same moment, once');
});

test('clock: a timestamp that leaves the device out loses to the reported latency', () => {
    const stamp = { contextTime: 9.99, performanceTime: 1000 };
    close(heardTime(ctx({ outputLatency: 0.2, getOutputTimestamp: () => stamp }), 1000), 9.8, 1e-9, 'the later of the two');
});

test('clock: an empty timestamp (before the first block) is ignored', () => {
    close(heardTime(ctx({ outputLatency: 0.05, getOutputTimestamp: () => ({ contextTime: 0, performanceTime: 0 }) })), 9.95, 1e-9, 'currentTime less the delay');
});

test('clock: a stale or foreign timestamp (a second or more behind) is ignored, never a wild reading', () => {
    close(heardTime(ctx({ outputLatency: 0.02, getOutputTimestamp: () => ({ contextTime: 3, performanceTime: 1000 }) }), 1000), 9.98, 1e-9, 'the rendered time less the delay');
    close(heardTime(ctx({ getOutputTimestamp: () => ({ contextTime: 9.9, performanceTime: 1e15 }) }), 1000), 10, 1e-9, 'a stamp from another clock');
});
