// A YouTube video in a page of ours, by the IFrame Player API (docs/youtube-sync-plan.md). Nothing loads from Google
// until open() is called, which only follows a tap on a recording's card; the loader then comes from www.youtube.com
// (unpinned: Hillel's one exception, 2026-11-19) and the player is framed from youtube-nocookie.com. The player is
// never covered or hidden by us (YouTube's Required Minimum Functionality: 200x200 at least, nothing in front of it),
// plays inline on a phone, and pauses whenever the page is hidden (no background play, Developer Policies III.I.9).
// Driven only through the documented calls; js/video-clock.js reads its time for drawing.

import { STATE } from './video-clock.js';

const LOADER = 'https://www.youtube.com/iframe_api';
const HOST = 'https://www.youtube-nocookie.com';

// What the player's onError codes mean for us: the video is gone, can't be embedded here, or the page sent no Referer.
export const PLAYER_ERRORS = { 2: 'bad_id', 5: 'cannot_play', 100: 'removed', 101: 'not_embeddable', 150: 'not_embeddable', 153: 'no_referrer' };

let loading = null;

/** YouTube's API (window.YT), loaded once, after a tap. */
export function loadYouTube() {
    if (window.YT?.Player) return Promise.resolve(window.YT);
    loading ??= new Promise((resolve, reject) => {
        const before = window.onYouTubeIframeAPIReady;
        window.onYouTubeIframeAPIReady = () => {
            before?.();
            resolve(window.YT);
        };
        const script = document.createElement('script');
        script.src = LOADER;
        script.async = true;
        script.referrerPolicy = 'strict-origin-when-cross-origin';
        script.onerror = () => {
            loading = null;
            reject(new Error('youtube_unavailable'));
        };
        document.head.append(script);
    });
    return loading;
}

const READY_WITHIN = 20000; // ms: a player that hasn't said it's ready by now isn't coming (blocked, offline)

/**
 * A player for `videoId` in `el` (replaced by the iframe). `on`: { ready(player), state(code), rate(rate), error(why) }.
 * Resolves with `{ player, destroy() }` once it's ready; rejects (and tears down) on an error before then, or when it
 * never gets ready. (The wait is a plain timer: it times nothing anyone hears.)
 */
export async function openPlayer(el, videoId, on = {}) {
    const YT = await loadYouTube();
    return new Promise((resolve, reject) => {
        let player = null;
        let ready = false;
        const hidden = () => {
            if (document.hidden && player?.getPlayerState?.() === STATE.PLAYING) player.pauseVideo();
        };
        const destroy = () => {
            document.removeEventListener('visibilitychange', hidden);
            try {
                player?.destroy();
            } catch (error) {
                // Already torn down with its page: nothing left to stop.
                console.debug('youtube player gone', error);
            }
        };
        const give = (why) => {
            if (ready) return;
            ready = true; // settled: no second answer
            destroy();
            reject(Object.assign(new Error(why), { why }));
        };
        const late = setTimeout(() => give('not_ready'), READY_WITHIN);
        player = new YT.Player(el, {
            host: HOST,
            videoId,
            width: '100%',
            height: '100%',
            playerVars: { playsinline: 1, rel: 0, enablejsapi: 1, origin: location.origin },
            events: {
                onReady: () => {
                    if (ready) return;
                    ready = true;
                    clearTimeout(late);
                    document.addEventListener('visibilitychange', hidden);
                    on.ready?.(player);
                    resolve({ player, destroy });
                },
                onStateChange: (event) => on.state?.(event.data),
                onPlaybackRateChange: (event) => on.rate?.(event.data),
                onError: (event) => {
                    const why = PLAYER_ERRORS[event.data] ?? 'cannot_play';
                    if (!ready) {
                        clearTimeout(late);
                        give(why);
                    } else on.error?.(why);
                },
            },
        });
    });
}

/** A YouTube link or a bare id as the video's 11-character id, or null. */
export function videoIdFrom(text) {
    const s = String(text ?? '').trim();
    if (/^[A-Za-z0-9_-]{11}$/.test(s)) return s;
    const match = /(?:youtu\.be\/|[?&]v=|\/embed\/|\/shorts\/|\/live\/)([A-Za-z0-9_-]{11})/.exec(s);
    return match ? match[1] : null;
}
