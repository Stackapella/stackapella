// The stack in the shared demo slot (A cappella, Friends and family; docs/demo-pages-plan.md): a song's first line on the
// four-voice mixer (components/site/mixer.blade.php, its player js/public/mixer.js), built up one part
// at a time. It starts with the first part of the frame's data-order alone; Add brings in the next one
// over the others, and plays the line again if it had stopped. With data-forward (A cappella) the new
// part is brought forward (its row's radio: a little louder, and "You", since every part is you);
// without it (Friends and family) it's only switched on, and "You" stays on your first part. Once every part is in, Add starts again from
// the first part alone. The rows still work by hand, and Add follows them: it always offers the first
// part in the order that's off. Every sound is the mixer's, on the audio clock. The words are the view's
// (the frame's JSON).

export async function mount(stage) {
    const root = stage.querySelector('[data-stack]');
    const player = root.querySelector('[data-player]');
    const play = player.querySelector('[data-play]');
    const add = root.querySelector('[data-stack-add]');
    const say = root.querySelector('[data-stack-say]');
    const words = JSON.parse(root.querySelector('script[data-stack-words]').textContent);
    const order = root.dataset.order.split(',');
    const forward = 'forward' in root.dataset;
    const row = (key) => player.querySelector(`[data-voice="${key}"]`);
    const isOn = (key) => row(key)?.querySelector('[data-toggle]')?.getAttribute('aria-checked') !== 'false';
    const next = () => order.find((key) => !isOn(key)) ?? null;
    const busy = () => player.hasAttribute('data-playing') || play.hasAttribute('aria-busy');

    function refresh() {
        const key = next();
        add.querySelector('[data-label]').textContent = key ? words.add[key] : words.again;
        add.toggleAttribute('data-again', !key);
    }

    await import('../mixer.js');
    // The mixer's own switches stop their clicks, so Add hears every change on the way down.
    player.addEventListener('click', () => queueMicrotask(refresh), true);

    add.addEventListener('click', () => {
        const key = next();
        if (key) {
            row(key).querySelector(forward ? '[data-pick]' : '[data-toggle]').click();
            say.textContent = words.steps[key];
            if (!busy()) play.click();
        } else {
            for (const other of order.slice(1)) if (isOn(other)) row(other).querySelector('[data-toggle]').click();
            row(order[0]).querySelector('[data-pick]').click();
            say.textContent = words.steps[order[0]];
            if (busy()) play.click(); // stop, and sing it again from the top with the first part alone
            play.click();
        }
        refresh();
    });

    refresh();
    play.click(); // still inside the start's tap, so the browser lets the sound start
}
