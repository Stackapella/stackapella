// A piano keyboard's colours, shared by every keyboard the tools draw (the range test's strip, the
// ear trainers' chord keys): ivory white keys and near-black black ones in every theme, never the
// theme's text and ground colours, which would turn the keys over in a light theme. The shaded and
// found notes are mixed from the theme's green, so a black key stays darker than the white ones
// beside it. Tailwind reads the class names from here (keep them whole strings).

export const PIANO = {
    white: 'bg-[#f4f1ea]',
    black: 'bg-[#24211e]',
    ink: 'text-[#24211e]', // a name written on a white key
    edge: 'border-[#24211e]/40',
    // A marked white key keeps one light green in every theme, as the ivory does: a light theme's deep green under the
    // key's dark name read at 2.5:1 (game's contrast check, 2026-11-03); this is 9:1.
    markWhite: 'bg-[#6fd69a] ring-2 ring-inset ring-[#24211e]',
    markBlack: 'bg-good ring-2 ring-inset ring-[#f4f1ea]',
    shadeWhite: 'bg-[color-mix(in_oklab,var(--color-good)_55%,#f4f1ea)]',
    shadeBlack: 'bg-[color-mix(in_oklab,var(--color-good)_40%,#24211e)]',
    strongWhite: 'bg-[color-mix(in_oklab,var(--color-good)_85%,#f4f1ea)]',
    strongBlack: 'bg-[color-mix(in_oklab,var(--color-good)_70%,#24211e)]',
};

/** Whether a note (MIDI, or a pitch class) is a black key. */
export const isBlack = (midi) => [1, 3, 6, 8, 10].includes(((Math.round(midi) % 12) + 12) % 12);

const WHITE_PCS = [0, 2, 4, 5, 7, 9, 11];

/**
 * A piano keyboard from `low` to `high` (MIDI, widened to white keys at both ends) drawn in `container`, in a
 * piano's colours in every theme: `mark` lights notes (a chord's, an interval's ends), `shade` tints a span (a voice
 * type's range), `names` writes a word on a marked white key ({ [midi]: 'C4' }). Each key is a span with
 * data-midi (and data-marked or data-shaded), so a page or the game skin can style one. Returns
 * { element, set({ mark, shade, names }) } to light other notes later. The reference cards draw theirs with it.
 */
export function keyboard(container, { low, high, mark = [], shade = [], names = {}, label = '' } = {}) {
    let first = Math.round(low);
    let last = Math.round(high);
    while (isBlack(first)) first -= 1;
    while (isBlack(last)) last += 1;
    const whites = [];
    for (let m = first; m <= last; m++) if (!isBlack(m)) whites.push(m);
    const width = 100 / whites.length;
    const element = document.createElement('div');
    element.className = 'relative h-28 w-full select-none overflow-hidden rounded-xl sm:h-32';
    element.dataset.ui = 'keyboard';
    element.setAttribute('role', 'img');
    if (label) element.setAttribute('aria-label', label);
    const keys = new Map();
    whites.forEach((m, i) => {
        const key = document.createElement('span');
        key.className = 'absolute inset-y-0 flex items-end justify-center rounded-b-md border pb-1.5 text-[10px] font-bold leading-none';
        Object.assign(key.style, { left: `${i * width}%`, width: `${width}%` });
        key.dataset.midi = String(m);
        keys.set(m, key);
        element.append(key);
    });
    for (let m = first; m <= last; m++) {
        if (!isBlack(m)) continue;
        const left = whites.indexOf(m - 1) + 1; // the white key it sits after
        const key = document.createElement('span');
        key.className = 'absolute top-0 z-10 h-[60%] rounded-b-md';
        Object.assign(key.style, { left: `calc(${left * width}% - ${width * 0.3}%)`, width: `${width * 0.6}%` });
        key.dataset.midi = String(m);
        keys.set(m, key);
        element.append(key);
    }
    container.replaceChildren(element);

    function set({ mark: marked = [], shade: shaded = [], names: words = {} } = {}) {
        const on = new Set(marked.map((m) => Math.round(m)));
        const tint = new Set(shaded.map((m) => Math.round(m)));
        for (const [m, key] of keys) {
            const black = isBlack(m);
            const lit = on.has(m);
            const tinted = !lit && tint.has(m);
            const fill = lit ? (black ? PIANO.markBlack : PIANO.markWhite) : tinted ? (black ? PIANO.shadeBlack : PIANO.shadeWhite) : black ? PIANO.black : PIANO.white;
            key.className = black
                ? `absolute top-0 z-10 h-[60%] rounded-b-md ${fill}`
                : `absolute inset-y-0 flex items-end justify-center rounded-b-md border ${PIANO.edge} pb-1.5 text-[10px] font-bold leading-none ${PIANO.ink} ${fill}`;
            key.toggleAttribute('data-marked', lit);
            key.toggleAttribute('data-shaded', tinted);
            if (!black) key.textContent = lit ? words[m] ?? '' : '';
        }
    }
    set({ mark, shade, names });
    return { element, set };
}

/** Every note from `low` to `high` (MIDI), for a keyboard's shade. */
export const span = (low, high) => Array.from({ length: Math.max(0, high - low + 1) }, (_, i) => low + i);
