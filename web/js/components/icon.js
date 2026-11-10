import { LOGO_MARK } from '../logo-mark.js';
import { hatDay } from '../public/season.js';
// Inline SVG icons (24 x 24, currentColor). Paths are ours, so no icon library is loaded.

const paths = {
    play: '<path d="M8 5.5v13a1 1 0 0 0 1.5.86l10.4-6.5a1 1 0 0 0 0-1.72L9.5 4.64A1 1 0 0 0 8 5.5Z" fill="currentColor"/>',
    pause: '<rect x="6.5" y="5" width="4" height="14" rx="1" fill="currentColor"/><rect x="13.5" y="5" width="4" height="14" rx="1" fill="currentColor"/>',
    stop: '<rect x="7" y="7" width="10" height="10" rx="1.5" fill="currentColor"/>',
    record: '<circle cx="12" cy="12" r="6" fill="currentColor"/>',
    metronome: '<path d="M9 3.5h6l3.5 17h-13L9 3.5Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M12 15.5 17 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>',
    more: '<circle cx="12" cy="5.5" r="1.6" fill="currentColor"/><circle cx="12" cy="12" r="1.6" fill="currentColor"/><circle cx="12" cy="18.5" r="1.6" fill="currentColor"/>',
    back: '<path d="M14.5 5.5 8 12l6.5 6.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
    check: '<path d="m5 12.5 4.5 4.5L19 7.5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>',
    close: '<path d="m6.5 6.5 11 11m0-11-11 11" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
    lock: '<rect x="5.5" y="10.5" width="13" height="9.5" rx="2" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M8.5 10.5V8a3.5 3.5 0 0 1 7 0v2.5" fill="none" stroke="currentColor" stroke-width="1.8"/>',
    sliders: '<path d="M4 8h9m4 0h3M4 16h3m4 0h9" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><circle cx="15" cy="8" r="2.2" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="9" cy="16" r="2.2" fill="none" stroke="currentColor" stroke-width="1.8"/>',
    chevron: '<path d="m7 10 5 5 5-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
    star: '<path d="m12 3.8 2.5 5.1 5.6.8-4 3.9 1 5.6-5.1-2.7-5.1 2.7 1-5.6-4-3.9 5.6-.8L12 3.8Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>',
    rewind: '<rect x="5" y="6" width="2.2" height="12" rx="1" fill="currentColor"/><path d="M19 6.8v10.4a.8.8 0 0 1-1.25.66L10.5 12.66a.8.8 0 0 1 0-1.32l7.25-5.2A.8.8 0 0 1 19 6.8Z" fill="currentColor"/>',
    undo: '<path d="M9 7 5 11l4 4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M5.5 11H14a5 5 0 0 1 0 10h-3" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
    redo: '<path d="m15 7 4 4-4 4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M18.5 11H10a5 5 0 0 0 0 10h3" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
    starFilled: '<path d="m12 3.8 2.5 5.1 5.6.8-4 3.9 1 5.6-5.1-2.7-5.1 2.7 1-5.6-4-3.9 5.6-.8L12 3.8Z" fill="currentColor" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/>',
    plus: '<path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
    minus: '<path d="M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
    sparkle: '<path d="M12 3c.6 4.2 2.8 6.4 7 7-4.2.6-6.4 2.8-7 7-.6-4.2-2.8-6.4-7-7 4.2-.6 6.4-2.8 7-7Z" fill="currentColor"/><path d="M19 15.5c.25 1.6 1 2.3 2.5 2.5-1.5.25-2.25 1-2.5 2.5-.25-1.5-1-2.25-2.5-2.5 1.5-.2 2.25-.9 2.5-2.5Z" fill="currentColor"/>',
    headphones: '<path d="M4 15v-3a8 8 0 0 1 16 0v3" fill="none" stroke="currentColor" stroke-width="1.8"/><rect x="3.5" y="14" width="4.5" height="6.5" rx="1.8" fill="currentColor"/><rect x="16" y="14" width="4.5" height="6.5" rx="1.8" fill="currentColor"/>',
    mic: '<rect x="9" y="3.5" width="6" height="11" rx="3" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M6 11.5a6 6 0 0 0 12 0M12 17.5v3" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>',
    volume: '<path d="M4 9.5h3.5L12 5.5v13l-4.5-4H4z" fill="currentColor"/><path d="M15.5 9a4 4 0 0 1 0 6M18 6.5a7.5 7.5 0 0 1 0 11" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>',
    download: '<path d="M12 4v11m-4.5-4.5L12 15l4.5-4.5M5 19.5h14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
    timing: '<circle cx="12" cy="13" r="7.5" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M12 9v4.5l2.5 1.5M10 3.5h4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>',
    loop: '<path d="M17 4.5 20 7.5l-3 3M20 7.5H8.5a4.5 4.5 0 0 0-4.5 4.5M7 19.5l-3-3 3-3M4 16.5h11.5a4.5 4.5 0 0 0 4.5-4.5" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/>',
    repeat: '<path d="M4 7h11M4 12h7M4 17h5" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/><path d="M15 13.5v6l5-3-5-3Z" fill="currentColor"/>',
    // Make your own's ways in (Hillel's 93.png): a hash for chords, a wave for a hummed tune, an arrow down for a file.
    hash: '<path d="M10 4 8 20M16 4l-2 16M4.5 9h16M3.5 15h16" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/>',
    wave: '<path d="M3 12c1.5-4.5 3-4.5 4.5 0s3 4.5 4.5 0 3-4.5 4.5 0 3 4.5 4.5 0" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/>',
    arrowDown: '<path d="M12 4.5v14m-5.5-5.5 5.5 5.5 5.5-5.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
    note: '<path d="M9 18.5V6l10-2v12.5" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"/><circle cx="6.5" cy="18.5" r="2.5" fill="currentColor"/><circle cx="16.5" cy="16.5" r="2.5" fill="currentColor"/>',
    arrow: '<path d="M5 12h13m-5-5.5L18.5 12 13 17.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
    alert: '<circle cx="12" cy="12" r="8.5" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M12 7.5v5.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="12" cy="16.3" r="1.2" fill="currentColor"/>',
    info: '<circle cx="12" cy="12" r="8.5" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M12 11v5.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="12" cy="7.7" r="1.2" fill="currentColor"/>',
    shield: '<path d="M12 3.5 5 6v5.5c0 4.2 2.9 7.7 7 9 4.1-1.3 7-4.8 7-9V6l-7-2.5Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>',
    home: '<path d="M4.5 10.5 12 4l7.5 6.5V19a1 1 0 0 1-1 1h-4v-5.5h-5V20h-4a1 1 0 0 1-1-1v-8.5Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>',
    list: '<path d="M9 7h10M9 12h10M9 17h10" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/><circle cx="5" cy="7" r="1.2" fill="currentColor"/><circle cx="5" cy="12" r="1.2" fill="currentColor"/><circle cx="5" cy="17" r="1.2" fill="currentColor"/>',
    dot: '<circle cx="12" cy="12" r="4" fill="currentColor"/>',
    sun: '<circle cx="12" cy="12" r="4" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M12 3v2.2M12 18.8V21M3 12h2.2M18.8 12H21M5.6 5.6l1.6 1.6M16.8 16.8l1.6 1.6M5.6 18.4l1.6-1.6M16.8 7.2l1.6-1.6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>',
    moon: '<path d="M19.5 14.5A7.5 7.5 0 0 1 9.5 4.5a7.5 7.5 0 1 0 10 10Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>',
    device: '<rect x="7" y="3" width="10" height="18" rx="2.5" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M10.5 17.5h3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>',
    // A note cut in two, and two notes made one (the note editor's Split and Join).
    split: '<rect x="3" y="9" width="7" height="6" rx="2" fill="currentColor"/><rect x="14" y="9" width="7" height="6" rx="2" fill="currentColor"/><path d="M12 5v14" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-dasharray="2 2.5"/>',
    join: '<rect x="3" y="9" width="18" height="6" rx="2" fill="currentColor"/><path d="M8 5l4 3 4-3M8 19l4-3 4 3" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>',
    trash: '<path d="M5 7h14M10 7V5h4v2m-7.5 0 .8 12h7.4l.8-12" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>',
};

export const Icon = {
    props: { name: { type: String, required: true }, size: { type: Number, default: 20 } },
    template: `<svg :width="size" :height="size" viewBox="0 0 24 24" aria-hidden="true" focusable="false" v-html="svg"></svg>`,
    computed: {
        // Our own constant markup, never user text.
        svg() {
            return paths[this.name] ?? '';
        },
    },
};

/**
 * The mark (four bars, one per voice, on a black square: web/tool/icons.py) and the name, exactly as the site's header
 * sets them (.s-brand: a 30 px mark, 9 px apart, Figtree 800 18 px -0.01em; below 360 px the mark alone, the name kept
 * for screen readers), so the logo is the same size in the app and on the site (Hillel, 2026-11-08). Put in an app-top
 * bar (css/input.css), it sits where the site's does too.
 *
 * On Christmas Eve and Christmas Day, by the device's own date (public/season.js hatDay; Hillel asked on 2026-11-10 for
 * the hat in the app and on the site at Christmas, and on its eve too), the mark wears the site's Santa hat
 * (icons/santa-hat.svg, Hillel's hat3e), in the site's own box (.s-brand > .s-hat) so the two are the same to the pixel:
 * worn on the mark's top-left corner, the tilt in the drawing, out of the flow so the mark and the name don't move. In
 * game mode it's the site's pixel hat (icons/santa-hat-pixel.svg, a hard 2 px shadow), the same box, as the site's
 * game mode draws it: both are on the page on a hat day and the skin shows one, as the site's CSS does (no import of the
 * skin here: icon.js loads too early for it, a circular import). They come first, so the mark stays the name's previous sibling
 * (tests/flows/logo_match.py measures it so). The favicon and the app's icons stay as they are.
 */
export const Logo = {
    props: { size: { type: Number, default: 30 } },
    setup: () => ({ LOGO_MARK, hat: hatDay() }),
    computed: {
        // The site's box exactly (.s-brand > .s-hat: the drawing's -9 -12 39 42 over the 30 px mark), at this mark's size:
        // 0.3 of it out to the left and 0.4 above, 1.3 by 1.4 of it, the mark centred in the row as the site's is.
        hatBox: (vm) => ({ left: `${-0.3 * vm.size}px`, top: `calc(50% - ${0.9 * vm.size}px)`, width: `${1.3 * vm.size}px`, height: `${1.4 * vm.size}px` }),
    },
    template: `
        <span class="relative inline-flex items-center gap-[9px] font-extrabold text-cream" data-ui="logo">
            <template v-if="hat">
                <img src="/icons/santa-hat.svg" alt="" aria-hidden="true" class="pointer-events-none absolute max-w-none drop-shadow-[0_1px_1px_rgba(0,0,0,0.3)] [[data-skin=game]_&]:hidden" :style="hatBox" data-hat-christmas />
                <img src="/icons/santa-hat-pixel.svg" alt="" aria-hidden="true" class="pointer-events-none absolute hidden max-w-none drop-shadow-[0_2px_0_rgba(0,0,0,0.3)] [[data-skin=game]_&]:block" :style="hatBox" data-hat-christmas />
            </template>
            <span class="block shrink-0 [&>svg]:size-full" :style="{ width: size + 'px', height: size + 'px' }" aria-hidden="true" v-html="LOGO_MARK"></span>
            <span class="text-[18px] tracking-[-0.01em] max-[359px]:sr-only" translate="no" data-ui="wordmark">Stackapella</span>
        </span>`,
};
