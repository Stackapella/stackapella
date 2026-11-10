// Game mode's switches on <html>, from the first paint of the app: data-game-off (effects.js, the reskins in CSS)
// and data-gs (arcade.js, the arcade screen's layer on the screens it covers so far). Game mode's fonts (docs/
// decisions.md, 2026-10-29: "Yes, add both"; 2026-10-30: "Yes, add VT323") are @font-face rules in css/input.css,
// served from our own address: Bungee for marquee headings, Stackapella Pixel (Press Start 2P, cut) for HUD labels and the arcade screen's
// headings and buttons, VT323 for its body text. A browser fetches them only once a game screen draws in them,
// so serious mode never does.

import './effects.js';
import './arcade.js';
