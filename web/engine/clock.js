// What's heard now, on an AudioContext's clock: the one clock every screen that follows the audio
// reads (Hillel, 2026-10-28: "the notes visually aren't matching the song"). ctx.currentTime is
// when the next block is rendered, not when it reaches the ears: the output buffer and the device
// (Bluetooth most of all) come after. The lit word, the lanes and their bricks, the playheads and
// the landing's mixer all draw at heardTime(), never at currentTime or performance.now().

/** The output delay the context reports, in seconds: its base latency and the device's output latency. */
export function outputDelay(ctx) {
    const base = Number.isFinite(ctx?.baseLatency) ? ctx.baseLatency : 0;
    const output = Number.isFinite(ctx?.outputLatency) ? ctx.outputLatency : 0;
    return base + output;
}

/**
 * The context time being heard now: the output timestamp carried on to `now` (the frame at the
 * speaker, where the browser gives one), or currentTime less the reported delay, whichever is
 * further behind. Browsers differ in which of the two counts the device's latency; taking the later
 * of them never shows a note before it sounds, and the two agree where both count it.
 */
export function heardTime(ctx, now = performance.now()) {
    const rendered = ctx.currentTime - outputDelay(ctx);
    const stamp = typeof ctx.getOutputTimestamp === 'function' ? ctx.getOutputTimestamp() : null;
    if (!stamp || !(stamp.contextTime > 0) || !Number.isFinite(stamp.performanceTime)) return rendered;
    const stamped = stamp.contextTime + (now - stamp.performanceTime) / 1000;
    // A stamp from another clock, or a stale one (a context that was suspended), is no reading: a
    // delay beyond a second isn't an output buffer, so the rendered time less the reported delay stands.
    if (!Number.isFinite(stamped) || rendered - stamped > 1) return rendered;
    return Math.min(rendered, stamped);
}
