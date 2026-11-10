// Perfect your pitch's Bullseye in the shared demo slot (the How to sing in tune guide;
// docs/demo-pages-plan.md): the still frame is the board itself, drawn by the server
// (site/courses/_pt-bullseye.blade.php, inside a [data-pt] root); the start loads the course page's own
// script (js/public/perfect-your-pitch.js, which wires the page's one [data-pt] as it loads). Nothing
// sounds and no mic opens until the visitor presses one of Bullseye's own buttons.

export async function mount() {
    await import('../perfect-your-pitch.js');
}
