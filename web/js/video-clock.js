// The video's clock for drawing (docs/youtube-sync-plan.md, The sync engine): a smooth position in video seconds from
// the YouTube player's getCurrentTime(), which steps (the iframe posts it now and then) and is never pushed to us. Read
// once per animation frame by whoever draws; nothing here keeps time with a timer, and nothing here plays audio (in
// video mode the video is the only sound). Pure: the player and the time source are given, so tests/video-clock.test.js
// drives it with a fake.
//
// Each read: when the player's reading has changed, re-anchor to it, unless it is within JITTER of our own estimate (a
// late post, not a seek); between readings, carry the anchor on by the elapsed time at the player's rate, only while the
// state is playing AND the reading has moved since play began (buffering, a paused ad and a stalled start all report
// "playing" with the time standing still: the lane waits with them). State and rate changes re-anchor at once.

export const STATE = { UNSTARTED: -1, ENDED: 0, PLAYING: 1, PAUSED: 2, BUFFERING: 3, CUED: 5 };
export const JITTER = 0.15; // seconds: a reading this close to our estimate is a late post, not a jump
export const STALL = 0.6; // seconds of "playing" with the reading frozen before the lane stops carrying on

/**
 * A clock over `player` (getCurrentTime, getPlayerState, getPlaybackRate, as the IFrame API's player has them).
 * `now()` is performance.now() in ms by default; `nudge` (seconds, + later) is the singer's speaker delay setting.
 */
export function videoClock(player, { now = () => performance.now(), nudge = 0 } = {}) {
    let anchorTime = safe(() => player.getCurrentTime(), 0);
    let anchorAt = now();
    let lastReading = anchorTime;
    let lastMovedAt = anchorAt;
    let moving = false; // the reading has advanced since the last re-anchor on a state change
    let state = safe(() => player.getPlayerState(), STATE.UNSTARTED);
    let rate = safe(() => player.getPlaybackRate(), 1) || 1;

    const estimate = (at) => {
        if (state !== STATE.PLAYING || !moving || (at - lastMovedAt) / 1000 > STALL) return anchorTime;
        return anchorTime + ((at - anchorAt) / 1000) * rate;
    };

    const reanchor = (time, at) => {
        anchorTime = time;
        anchorAt = at;
    };

    return {
        /** The video's position now, in seconds, smoothed; with the singer's nudge. */
        read() {
            const at = now();
            const reading = safe(() => player.getCurrentTime(), lastReading);
            if (reading !== lastReading) {
                const ahead = reading > lastReading;
                lastReading = reading;
                lastMovedAt = at;
                if (ahead && state === STATE.PLAYING) {
                    if (!moving) {
                        moving = true;
                        reanchor(reading, at);
                    } else if (Math.abs(reading - estimate(at)) > JITTER) reanchor(reading, at);
                } else reanchor(reading, at); // a seek back, or a change while paused
            } else if (state === STATE.PLAYING && moving && (at - lastMovedAt) / 1000 > STALL) {
                // Frozen while "playing" (buffering, an ad): hold where the video really is, so it resumes from there.
                reanchor(reading, at);
                moving = false;
            }
            return Math.max(0, estimate(at) - nudge);
        },
        /** The player's onStateChange: anchor to where it really is now. */
        stateChanged(next) {
            const at = now();
            state = next;
            moving = false;
            lastReading = safe(() => player.getCurrentTime(), lastReading);
            lastMovedAt = at;
            reanchor(lastReading, at);
        },
        /** The player's onPlaybackRateChange: carry on at the rate it reports. */
        rateChanged(next) {
            const at = now();
            reanchor(estimate(at), at);
            rate = next || 1;
        },
        /** The singer's speaker delay, in seconds (+ later). */
        setNudge(seconds) {
            nudge = seconds;
        },
        get playing() {
            return state === STATE.PLAYING && moving;
        },
    };
}

function safe(read, fallback) {
    try {
        const value = read();
        return Number.isFinite(value) ? value : fallback;
    } catch (error) {
        // A player torn down mid-read (the singer left): the last known value stands.
        return fallback;
    }
}
