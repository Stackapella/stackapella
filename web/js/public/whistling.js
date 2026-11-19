// Whistling's course page (site/courses/whistling): the hero's card plays its sample take of Hot Cross Buns, as lesson 4
// lays it (the view reads the notes from the course file), on a whistle-like tone from a sample whistle note, each note
// off by the sample's cents. Only when pressed, never autoplayed; every note is scheduled on the AudioContext clock
// (voices.js), and the card's line and colours follow that clock frame by frame (a picture, not a timer for sound).
// Stop puts the card back as it was; the end leaves the take's count. Reduced motion: the line hides while it plays and
// the notes still change as they're whistled. The Sources fold opens on a wide screen.

import { audio, hz, voice } from './voices.js';
import { playState } from './play-state.js';

function mount(card) {
    let notes;
    try {
        notes = JSON.parse(card.dataset.notes || '[]');
    } catch {
        return; // notes that won't parse: the card keeps its still frame and its button does nothing
    }
    const button = card.querySelector('[data-wh-play]');
    if (!notes.length || !button) return;

    const label = button.querySelector('[data-label]');
    const bars = [...card.querySelectorAll('[data-wh-i]')];
    const head = card.querySelector('[data-wh-head]');
    const count = card.querySelector('[data-wh-count]');
    const beats = Number(card.dataset.beats) || 16;
    const spb = 60 / (Number(card.dataset.tempo) || 72);
    const base = Number(card.dataset.base) || 84;
    const tolerance = Number(card.dataset.tolerance) || 25;
    const words = (template, n, total) => template.replace(':n', String(n)).replace(':total', String(total));
    const judged = (cents) => (Math.abs(cents) <= tolerance ? 'on' : cents < 0 ? 'flat' : 'sharp');
    const still = { states: bars.map((b) => b.dataset.state), at: head?.style.getPropertyValue('--at') ?? '', count: count?.textContent ?? '' };
    const state = playState(button, card);
    let run = null;

    const show = (playing) => {
        button.setAttribute('aria-pressed', playing ? 'true' : 'false');
        label.textContent = playing ? card.dataset.stop : card.dataset.play;
        card.toggleAttribute('data-playing', playing);
    };

    const reset = () => {
        bars.forEach((b, i) => (b.dataset.state = still.states[i]));
        head?.style.setProperty('--at', still.at);
        if (count) count.textContent = still.count;
        card.removeAttribute('data-done');
    };

    const stop = (keep = false) => {
        if (!run) return;
        cancelAnimationFrame(run.frame);
        run.handles.forEach((h) => h.stop());
        run = null;
        state.idle();
        show(false);
        if (!keep) reset();
    };

    const frame = () => {
        if (!run) return;
        const beat = (run.ctx.currentTime - run.start) / spb;
        let done = 0;
        let on = 0;
        notes.forEach(([at, length, , cents], i) => {
            const now = beat >= at + length ? judged(cents) : beat >= at ? 'now' : 'todo';
            if (bars[i] && bars[i].dataset.state !== now) bars[i].dataset.state = now;
            if (beat >= at + length) {
                done += 1;
                if (now === 'on') on += 1;
            }
        });
        head?.style.setProperty('--at', `${Math.max(0, Math.min(1, beat / beats)) * 100}%`);
        if (beat >= beats) {
            if (count) count.textContent = words(card.dataset.doneWords, on, notes.length);
            card.setAttribute('data-done', '');
            stop(true);
            return;
        }
        if (count) count.textContent = words(card.dataset.soFar, on, done);
        run.frame = requestAnimationFrame(frame);
    };

    const start = () => {
        state.loading();
        let ctx;
        let start;
        let handles;
        try {
            ctx = audio();
            start = ctx.currentTime + 0.12;
            handles = notes.map(([at, length, step, cents]) => {
                const h = voice(hz(base + step), { cents, at: start + at * spb, timbre: 'sine', level: 0.07, attack: 0.012, release: 0.03 });
                // Each note ends a little before the next, as a whistle's soft "h" starts it clean.
                h.stop(start + (at + length * 0.9) * spb - ctx.currentTime);
                return h;
            });
        } catch (error) {
            console.debug('The sample take could not play', error);
            state.failed();
            return;
        }
        card.removeAttribute('data-done');
        bars.forEach((b) => (b.dataset.state = 'todo'));
        run = { ctx, start, handles, frame: 0 };
        state.playing();
        show(true);
        run.frame = requestAnimationFrame(frame);
    };

    button.addEventListener('click', () => (run ? stop() : start()));
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) stop();
    });
}

document.querySelectorAll('[data-wh-hero]').forEach(mount);

// The Sources list: open on a wide screen (its desktop state), folded on a phone.
const wide = window.matchMedia('(min-width: 900px)');
const sources = document.querySelector('[data-wh-sources]');
if (sources && wide.matches) sources.open = true;
