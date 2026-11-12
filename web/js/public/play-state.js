// The public site's play controls, one way of answering a press (Hillel: "make sure there is instant feedback if we
// need to load for good UX"): within the same frame the control says it's loading (a ring turning in the button, or
// still with reduced motion; aria-busy, and "Loading..." said to a screen reader), it plays when the sound really
// starts, after SLOW still loading a quiet "Still loading" shows, and a failure says "Couldn't play, tap to try again".
// A second press while loading cancels: the control's own job (mixer.js, part-player.js); this only shows it.
// The words are the page's (public/layout.blade.php's #s-play-words, from the lang files).
//
// It marks the control's root: data-loading, data-slow, data-failed (input.css draws them), and keeps a hint inside the
// button (aria-hidden; the live region says it). A timer here only shows a hint: it never schedules audio.

/** How long a load runs before the quiet "Still loading" hint shows (ms). */
export const SLOW = 4000;

let region = null;

/** The page's one polite live region for the players, made on first use. */
function say(text) {
    if (!region) {
        region = document.createElement('p');
        region.className = 'sr-only';
        region.setAttribute('role', 'status');
        region.dataset.playStatus = '';
        document.body.append(region);
    }
    region.textContent = '';
    if (text) requestAnimationFrame(() => (region.textContent = text));
}

/** The page's words for a player's states. */
export function playWords() {
    const el = document.getElementById('s-play-words');
    return { loading: el?.dataset.loading ?? '', slow: el?.dataset.slow ?? '', failed: el?.dataset.failed ?? '' };
}

/**
 * The states of a play control: `button` (its aria-label is its own, put back when a failure is cleared) inside
 * `root`. Returns { loading(), playing(), idle(), failed(), state }.
 */
export function playState(button, root = button, { slow = SLOW, words = playWords() } = {}) {
    let state = 'idle';
    let timer = 0;
    let label = null; // the button's own label, while a failure has replaced it
    const hint = document.createElement('span');
    hint.className = 's-play-hint';
    hint.setAttribute('aria-hidden', 'true');
    button.append(hint);

    const clear = () => {
        clearTimeout(timer);
        timer = 0;
        root.removeAttribute('data-loading');
        root.removeAttribute('data-slow');
        root.removeAttribute('data-failed');
        button.removeAttribute('aria-busy');
        hint.textContent = '';
        if (label !== null) {
            button.setAttribute('aria-label', label);
            label = null;
        }
    };

    return {
        get state() {
            return state;
        },
        loading() {
            clear();
            state = 'loading';
            root.setAttribute('data-loading', '');
            button.setAttribute('aria-busy', 'true');
            say(words.loading);
            timer = setTimeout(() => {
                if (state !== 'loading') return;
                root.setAttribute('data-slow', '');
                hint.textContent = words.slow;
                say(words.slow);
            }, slow);
        },
        playing() {
            clear();
            state = 'playing';
        },
        idle() {
            clear();
            state = 'idle';
        },
        failed() {
            clear();
            state = 'failed';
            root.setAttribute('data-failed', '');
            hint.textContent = words.failed;
            label = button.getAttribute('aria-label');
            button.setAttribute('aria-label', words.failed);
            say(words.failed);
        },
    };
}
