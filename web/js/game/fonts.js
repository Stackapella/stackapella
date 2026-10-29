// Game mode's two fonts (docs/decisions.md, 2026-10-29: "Yes, add both"): Bungee for marquee
// headings and Press Start 2P for small HUD labels, from Google Fonts like Figtree, one weight each.
// They load the first time game mode is on, so serious mode never fetches them.

import { watch } from 'vue';
import { game } from '../stores/appearance.js';
import './effects.js'; // <html data-game-off>, for the reskins in CSS, from the first paint of the app

export const GAME_FONTS = 'https://fonts.googleapis.com/css2?family=Bungee&family=Press+Start+2P&display=swap';

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
