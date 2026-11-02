// Game mode's fonts (docs/decisions.md, 2026-10-29: "Yes, add both"; 2026-10-30: "Yes, add VT323"):
// Bungee for marquee headings, Press Start 2P for HUD labels and the arcade screen's headings and
// buttons, and VT323 for its body text, from Google Fonts like Figtree, one weight each. They load
// the first time game mode is on, so serious mode never fetches them.

import { watch } from 'vue';
import { game } from '../stores/appearance.js';
import './effects.js'; // <html data-game-off>, for the reskins in CSS, from the first paint of the app
import './arcade.js'; // <html data-gs>, the arcade screen's layer, on the screens it covers so far

export const GAME_FONTS = 'https://fonts.googleapis.com/css2?family=Bungee&family=Press+Start+2P&family=VT323&display=swap';

/** Adds the fonts' stylesheet once, when game mode is on. */
export function loadGameFonts(doc = document) {
    if (doc.querySelector(`link[href="${GAME_FONTS}"]`)) return;
    const link = doc.createElement('link');
    link.rel = 'stylesheet';
    link.href = GAME_FONTS;
    link.dataset.gameFonts = '';
    doc.head.append(link);
}

watch(game, (on) => on && loadGameFonts(), { immediate: true });
