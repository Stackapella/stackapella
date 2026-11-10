// Sing's session as the rest of the app reads it (recording, playing, counting in), without loading its store: the
// store (js/stores/session.js, with the studio's mixing, trimming and sync) puts itself here when a screen that sings
// loads it. Until then nothing is being sung, and lesson 1 on a first visit doesn't download it (2026-11-08).
import { shallowRef } from 'vue';

/** The session store once it's loaded, else null. */
export const liveSession = shallowRef(null);

/** Whether Sing is recording, playing or counting in now (never before its store has loaded). */
export function sessionBusy() {
    const session = liveSession.value;
    return !!session && (session.recording || session.playing || session.countIn !== null);
}
