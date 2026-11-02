// Vibrato's Straight, wave, wobble in the shared demo slot (Voice teachers; docs/demo-pages-plan.md):
// the stage's still frame is the deck, drawn by the server with the same hooks as the Vibrato course
// page (site/_demo-section.blade.php, kind `vibrato`); the start loads that page's own script
// (js/public/vibrato.js, which wires the page's one [data-vi-demo] as it loads) and plays the wave, so
// the first sound is a vibrato.

export async function mount(stage) {
    await import('../vibrato.js');
    stage.querySelector('[data-vi-play="wave"]')?.click();
}
