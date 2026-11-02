// The four-voice mixer as a slotted demo (Choir directors' "Give out a part"; docs/demo-pages-plan.md):
// the stage's still frame is the mixer itself, drawn by the server (components/site/mixer.blade.php);
// the start loads the site's player (js/public/mixer.js, which wires every [data-player] as it loads)
// and plays it, still inside the tap, so the browser lets the sound start.

export async function mount(stage) {
    await import('../mixer.js');
    stage.querySelector('[data-player] [data-play]')?.click();
}
