// A page's score card (public/_song-score: a song page's, and /songs's song to try) loads its engraver (js/public/score.js,
// the page's largest script) as soon as the page's fonts are in: never before them, since a font landing after the first
// paint reflowed the lead and moved what's under it (a layout shift of 0.12 on a phone), and never later, since waiting
// for the page's load and then for the browser to idle left the card's staves empty for a second or two (the coordinator,
// 2026-11-18). score.js then draws at once.

if (document.querySelector('[data-score]')) {
    (document.fonts?.ready ?? Promise.resolve()).then(() => import('./score.js'));
}
