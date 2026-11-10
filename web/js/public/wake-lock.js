// Keeps the screen on while a tool is in use (the tuner listening, the metronome playing): a Screen
// Wake Lock, asked for again whenever the page comes back into view (the browser lets it go when the
// page is hidden). Where the browser has none, nothing happens: the tool still works, and its FAQ
// says to keep the screen on. Shared by the free tools (docs/tools-plan.md, "Shared modules").

/** Holds the screen on until release(). Call it from a tap (some browsers want one). */
export function keepAwake() {
    let lock = null;
    let on = true;
    async function ask() {
        if (!on || document.hidden || !navigator.wakeLock?.request) return;
        try {
            lock = await navigator.wakeLock.request('screen');
            if (!on) lock.release().catch(() => {});
        } catch (error) {
            // Refused (low battery, a policy): the tool carries on without it.
            console.warn('The screen may sleep: no wake lock', error);
        }
    }
    const again = () => {
        if (!document.hidden && (!lock || lock.released)) ask();
    };
    document.addEventListener('visibilitychange', again);
    ask();
    return {
        /** Whether the screen is being kept on right now. */
        get held() {
            return Boolean(lock && !lock.released);
        },
        release() {
            on = false;
            document.removeEventListener('visibilitychange', again);
            lock?.release().catch(() => {});
            lock = null;
        },
    };
}
