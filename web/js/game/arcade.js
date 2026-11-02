// The arcade screen (Hillel's Home design, 2026-10-30: "make it look as close to the image I shared as
// possible", then "use these new styles everywhere for game mode"): one layer in CSS (the
// arcade-screen block in input.css) under <html data-gs>: pixel type for headings and buttons, a
// screen font for body text, square pixel edges with hard shadows, buttons that press down, the
// dotted screen with faint scanlines, hazard stripes on the asks. It landed a few screens at a time
// (docs/game-style-plan.md) and is now on every screen of game mode, but the admin's and the styleguide.
// The Arcade screen switch (on) and serious mode give today's look.

import { watch } from 'vue';
import { route } from '../router.js';
import { effect } from './effects.js';

/** The screens that stay as they are: the admin's tools and the styleguide (router.js route names). */
export const OUTSIDE_ROUTES = new Set(['admin', 'styleguide']);

/** Whether the arcade layer shows now: game mode, its switch on, on any screen but those. */
export const arcadeOn = () => effect('arcade') && !OUTSIDE_ROUTES.has(route.name);

watch(arcadeOn, (on) => {
    if (on) document.documentElement.dataset.gs = '';
    else delete document.documentElement.dataset.gs;
}, { immediate: true });
