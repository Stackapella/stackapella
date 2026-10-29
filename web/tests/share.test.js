// Sharing (js/share.js): each part's peaks every 50 ms from the mix's start, the pre-filled posts,
// and the video's section.
import { blueskyIntent, trackPeaks, videoSection, xIntent } from '/js/share.js';
import { assert, test } from './harness.js';

test('share: a part\'s peaks are one byte per 50 ms of the mix, from where it starts, at its level', () => {
    const sr = 1000;
    const samples = new Float32Array(1000).fill(0.5); // a second of sound
    const bytes = Uint8Array.from(atob(trackPeaks(samples, sr, { offsetSeconds: 0.5, volume: 0.8, mixSeconds: 2 })), (c) => c.charCodeAt(0));
    assert(bytes.length === 40, `${bytes.length} bytes`);
    assert(bytes[9] === 0 && bytes[10] === 102 && bytes[29] === 102 && bytes[30] === 0, `silent before, at its level during, silent after: ${[bytes[9], bytes[10], bytes[29], bytes[30]]}`);
});

test('share: the posts are pre-filled, and the video takes the chorus, at most a minute', () => {
    assert(xIntent('Hi & bye https://x.y/h/1').startsWith('https://x.com/intent/post?text=Hi%20%26%20bye'), xIntent('Hi & bye'));
    assert(blueskyIntent('a b').endsWith('text=a%20b'), 'bluesky');
    const chorus = videoSection({ mixSeconds: 200, sections: [{ name: 'Verse', startBeat: 0, endBeat: 32 }, { name: 'Chorus', startBeat: 32, endBeat: 64 }], beatSeconds: 0.5 });
    assert(chorus.startSeconds === 16 && chorus.endSeconds === 32, JSON.stringify(chorus));
    const whole = videoSection({ mixSeconds: 95 });
    assert(whole.startSeconds === 0 && whole.endSeconds === 60, JSON.stringify(whole));
    const short = videoSection({ mixSeconds: 20 });
    assert(short.endSeconds === 20, JSON.stringify(short));
});
