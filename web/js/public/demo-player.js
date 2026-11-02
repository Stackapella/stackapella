// The course pages' demo player: one demo sounds at a time, as yodeling.js's start() and finish() do,
// for the pages built on it (first, js/public/perfect-your-pitch.js). A demo is a function that
// makes its sound with voices.js and returns `{ voices, length, done }`: the voices are stopped at
// `length` seconds on the audio clock (never a timer), the button resets once they're all quiet
// (Promise.all of their `ended`), and `done` tidies up after it, whether it ran out or was stopped.
// Its buttons carry data-play and data-stop (their two labels, shown in a [data-label] inside, or the
// button itself) and aria-pressed. Pressing the playing demo's button stops it; another demo's
// button stops it and starts its own. Everything stops when the page is hidden or left.

import { audio, stopAll } from './voices.js';

let playing = null; // { button, voices, done }

/**
 * Call inside the tap: makes and resumes the AudioContext and, on an iPhone, sounds even with the
 * silent switch on, as the site's four-voice player does (js/public/mixer.js). `session` is
 * 'play-and-record' just before the mic is opened (as the app's engine does), 'playback' otherwise.
 */
export function unlock(session = 'playback') {
    if ('audioSession' in navigator) {
        try {
            navigator.audioSession.type = session;
        } catch (error) {
            console.warn('audioSession.type could not be set', error);
        }
    }
    return audio();
}

/** A button's two labels: pressed, it reads data-stop; let go, data-play. */
export function label(button, on) {
    button.setAttribute('aria-pressed', String(on));
    const text = button.querySelector('[data-label]') ?? button;
    if (button.dataset.play) text.textContent = on ? button.dataset.stop : button.dataset.play;
}

/** Is `button`'s demo the one sounding? */
export const sounding = (button) => playing?.button === button;

/**
 * Plays `demo` from `button`, or stops it if it's the one playing. The demo gets `{ live }`, where
 * `live()` stays true while it's the one playing (for a demo whose later steps wait on the clock).
 */
export function play(button, demo) {
    const was = playing?.button;
    finish();
    if (was === button) return;
    unlock();
    const mine = { button, voices: [], done: null };
    playing = mine;
    const { voices, length, done = null } = demo({ live: () => playing === mine });
    mine.voices = voices;
    mine.done = done;
    voices.forEach((v) => v.stop(length));
    label(button, true);
    Promise.all(voices.map((v) => v.ended)).then(() => playing === mine && finish());
}

/** Stops the demo that's playing, if any, and resets its button. */
export function finish() {
    if (!playing) return;
    const was = playing;
    playing = null;
    was.done?.();
    label(was.button, false);
    was.voices.forEach((v) => v.stop());
    stopAll();
}

/**
 * A silent stretch to put among a demo's voices, so it lasts `length` even where nothing sounds
 * (a round that waits for the visitor's turn): a muted source on the audio clock, with a voice's
 * `ended` and `stop(after)`.
 */
export function rest() {
    const c = audio();
    const source = c.createConstantSource();
    const mute = c.createGain();
    mute.gain.value = 0;
    source.connect(mute).connect(c.destination);
    source.start();
    const ended = new Promise((resolve) => { source.onended = resolve; });
    ended.then(() => source.disconnect());
    return {
        ended,
        stop(after = 0) {
            try {
                source.stop(c.currentTime + after);
            } catch (error) {
                console.warn('the rest had stopped', error);
            }
        },
    };
}

/** Resolves when the audio clock reaches `at` (seconds), so a demo's steps follow its sound. */
export function when(at) {
    const mark = rest();
    mark.stop(Math.max(0, at - audio().currentTime));
    return mark.ended;
}

addEventListener('pagehide', finish);
