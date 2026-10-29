// Sharing a harmony (docs/more-features-plan.md features 4 and 12; contract/api.md "Sharing"):
// what the account needs besides the mix itself. Pure, unit-tested in tests/share.test.js.

const PEAK_SECONDS = 0.05; // tracks' peaks: one byte per 50 ms of the mix

/**
 * A part's loudness as heard in the mix, one byte (0 to 255) per 50 ms from the mix's start, as
 * base64: the video's waveforms and the card's. `samples` at `sampleRate` start `offsetSeconds`
 * into the mix; `volume` is the part's level in the mix.
 */
export function trackPeaks(samples, sampleRate, { offsetSeconds = 0, volume = 1, mixSeconds }) {
    const count = Math.ceil(mixSeconds / PEAK_SECONDS);
    const bytes = new Uint8Array(count);
    const per = Math.round(PEAK_SECONDS * sampleRate);
    const lead = Math.round(offsetSeconds * sampleRate);
    for (let k = 0; k < count; k++) {
        let peak = 0;
        const from = k * per - lead;
        for (let i = Math.max(0, from); i < Math.min(samples.length, from + per); i++) peak = Math.max(peak, Math.abs(samples[i]));
        bytes[k] = Math.min(255, Math.round(peak * volume * 255));
    }
    let text = '';
    for (const b of bytes) text += String.fromCharCode(b);
    return btoa(text);
}

/** "Post to X": X's intent page, with the post pre-filled (the person posts it from there). */
export function xIntent(text) {
    return `https://x.com/intent/post?text=${encodeURIComponent(text)}`;
}

/** "Post to Bluesky": Bluesky's compose page, pre-filled. */
export function blueskyIntent(text) {
    return `https://bsky.app/intent/compose?text=${encodeURIComponent(text)}`;
}

/**
 * The video's section: the chorus when the song has one (a section named so), else the start, at
 * most 60 seconds and at least 3, inside the mix.
 */
export function videoSection({ mixSeconds, sections = [], beatSeconds = 0 }) {
    const chorus = sections.find((s) => /chorus|refrain/i.test(s.name ?? ''));
    let start = chorus && beatSeconds ? chorus.startBeat * beatSeconds : 0;
    let end = chorus && beatSeconds ? chorus.endBeat * beatSeconds : mixSeconds;
    end = Math.min(end, start + 60, mixSeconds);
    if (end - start < 3) {
        start = 0;
        end = Math.min(mixSeconds, 60);
    }
    return { startSeconds: Math.round(start * 10) / 10, endSeconds: Math.round(end * 10) / 10 };
}
