// Ear training's Name that leap as a slotted demo (Music teachers and schools; docs/demo-pages-plan.md):
// the stage's still frame is the game itself, drawn by the server (site/courses/_ear-leap.blade.php);
// the start loads its script (js/public/ear-training.js, which wires [data-ear-demo] as it loads) and
// plays the first leap, so the class hears it at once.

export async function mount(stage) {
    await import('../ear-training.js');
    stage.querySelector('[data-ear-play]')?.click();
}
