// A grand staff (treble over bass) with a few notes on it, as SVG: the range test's result ("G2 to
// E4" written as music), and any tool that shows notes. Notes from middle C up sit on the treble
// staff, lower ones on the bass staff, with ledger lines and sharps; the clefs are the music font's
// (web/fonts/noto-music-signs.woff2), in the page's ink (currentColor). Tested in
// tests/public-staff.test.js.

const SVG = 'http://www.w3.org/2000/svg';
const MUSIC_FONT = "'Noto Music', 'Apple Symbols', 'Segoe UI Symbol', serif"; // i18n-ignore: fonts with the clefs
const STEP = [0, 0, 1, 1, 2, 3, 3, 4, 4, 5, 5, 6]; // each pitch class's letter (C = 0), sharps for the black keys
const SHARP = [false, true, false, true, false, false, true, false, true, false, true, false];
const TREBLE_LINES = [30, 32, 34, 36, 38]; // E4 G4 B4 D5 F5, as diatonic steps (C0 = 0)
const BASS_LINES = [18, 20, 22, 24, 26]; // G2 B2 D3 F3 A3
const MIDDLE_C = 28;

/** A note (MIDI) as its diatonic step from C0 (middle C is 28) and whether it takes a sharp. */
export function staffStep(midi) {
    const m = Math.round(midi);
    const pc = ((m % 12) + 12) % 12;
    return { step: (Math.floor(m / 12) - 1) * 7 + STEP[pc], sharp: SHARP[pc] };
}

const SIGNS = { 1: '♯', 2: '\u{1D12A}', '-1': '♭', '-2': '\u{1D12B}' }; // sharp, double sharp, flat, double flat

/**
 * A spelled note ({ midi, letter (C = 0 to B = 6), octave, accidental (-2 to 2) }: B♭4, E♭5, a diminished seventh's
 * B𝄫) as its diatonic step and its sign, for a staff that writes notes by name rather than as sharps.
 */
export function spelledStep({ letter, octave, accidental = 0 }) {
    return { step: octave * 7 + letter, sharp: false, sign: SIGNS[accidental] ?? '' };
}

/** The ledger lines a note at `step` needs (steps of the lines), on its own staff. */
export function ledgers(step) {
    const out = [];
    if (step >= MIDDLE_C) {
        if (step === MIDDLE_C) out.push(MIDDLE_C); // middle C's own line, under the treble staff
        for (let s = 40; s <= step; s += 2) out.push(s); // above it (A5 up)
    } else {
        for (let s = 16; s >= step; s -= 2) out.push(s); // below the bass staff (E2 down)
    }
    return out;
}

/** The ledger lines a note at `step` needs on the treble staff (`treble`) or the bass staff, whichever its part is written on. */
export function ledgersOn(step, treble) {
    const out = [];
    if (treble) {
        for (let s = 28; s >= step; s -= 2) out.push(s); // under the treble staff (C4 down)
        for (let s = 40; s <= step; s += 2) out.push(s); // over it (A5 up)
    } else {
        for (let s = 28; s <= step; s += 2) out.push(s); // over the bass staff (C4 up)
        for (let s = 16; s >= step; s -= 2) out.push(s); // under it (E2 down)
    }
    return out;
}

const make = (name, attrs) => {
    const el = document.createElementNS(SVG, name);
    for (const [k, v] of Object.entries(attrs)) el.setAttribute(k, String(v));
    return el;
};

/**
 * An SVG grand staff with `notes` side by side, `label` read to a screen reader. Each entry is a note
 * (MIDI, or a spelled note { midi, letter, octave, accidental }, which writes its own flat or sharp: the reference
 * cards' B♭) or a chord (an array of notes, stacked in one column: the four-part voicer's soprano, alto,
 * tenor and bass); a second in a chord sits beside the note under it, as printed music has it.
 * `names` puts a chord symbol over each column; each column is a group with data-column, so a tool
 * can light the one that's sounding. `upper`, for parts: in each chord, the first `upper` notes are
 * written on the treble staff and the rest on the bass staff, whatever their pitch (a choir's soprano and
 * alto over its tenor and bass); without it, notes from middle C up go on the treble staff.
 */
export function grandStaff(notes, { label = '', gap = 7, names = [], upper = null } = {}) {
    const columns = notes.map((n) => (Array.isArray(n) ? n : [n]).map((midi, k) => {
        const at = typeof midi === 'object' ? spelledStep(midi) : staffStep(midi);
        return { ...at, treble: upper === null ? at.step >= MIDDLE_C : k < upper };
    }));
    const all = columns.flat();
    const lowest = Math.min(14, ...all.map((s) => s.step));
    const highest = Math.max(42, ...all.map((s) => s.step)) + (names.length ? 4 : 0);
    // A line every `gap` px; the bass staff sits two spaces further down, so the two staves read as two
    // with middle C's ledger line between them.
    const y = (step, treble = step >= MIDDLE_C) => (highest - step) * (gap / 2) + gap + (treble ? 0 : 2 * gap);
    const width = 70 + notes.length * 46;
    const height = y(lowest) + gap;
    const svg = make('svg', { viewBox: `0 0 ${width} ${height}`, width, height, role: 'img', 'aria-label': label, fill: 'currentColor', stroke: 'currentColor' });
    for (const line of [...TREBLE_LINES, ...BASS_LINES]) svg.append(make('line', { x1: 4, x2: width - 4, y1: y(line), y2: y(line), 'stroke-width': 1, opacity: 0.55 }));
    svg.append(make('line', { x1: 4, x2: 4, y1: y(38), y2: y(18), 'stroke-width': 1.5, opacity: 0.55 }));
    const clef = (glyph, at, size) => {
        const text = make('text', { x: 9, y: at, 'font-size': size, 'font-family': MUSIC_FONT, stroke: 'none' });
        text.textContent = glyph;
        return text;
    };
    // As the printed parts place them (components/score-system.js): the treble clef curls round G4's
    // line, the bass clef's dots sit either side of F3's.
    svg.append(clef('\u{1D11E}', y(32) + gap * 1.4, gap * 4.4), clef('\u{1D122}', y(24) + gap * 1.4, gap * 3.4));
    columns.forEach((steps, i) => {
        const x = 70 + i * 46;
        const group = make('g', { 'data-column': i });
        if (names[i]) {
            const name = make('text', { x, y: y(highest) + gap * 0.6, 'font-size': gap * 1.9, 'font-weight': 700, 'text-anchor': 'middle', stroke: 'none' });
            name.textContent = names[i];
            group.append(name);
        }
        const sorted = [...steps].sort((a, b) => a.step - b.step);
        sorted.forEach(({ step, sharp, sign = '', treble }, k) => {
            // A second above the note under it, on the same staff: beside it, to the right.
            const below = sorted[k - 1];
            const nx = below && below.treble === treble && step - below.step === 1 && !below.shifted ? x + gap * 1.45 : x;
            sorted[k].shifted = nx !== x;
            const lines = upper === null ? ledgers(step) : ledgersOn(step, treble);
            for (const s of lines) group.append(make('line', { x1: nx - 11, x2: nx + 11, y1: y(s, treble), y2: y(s, treble), 'stroke-width': 1.2 }));
            group.append(make('ellipse', { cx: nx, cy: y(step, treble), rx: gap * 0.72, ry: gap * 0.52, transform: `rotate(-20 ${nx} ${y(step, treble)})`, stroke: 'none' }));
            if (sharp || sign) {
                // Left of its own note (a note set beside a second keeps its sign with it).
                const text = make('text', { x: nx - gap * 2.3, y: y(step, treble) + gap * 0.55, 'font-size': gap * 2.2, stroke: 'none', ...(sign.length > 1 ? { 'font-family': MUSIC_FONT } : {}) });
                text.textContent = sign || '♯';
                group.append(text);
            }
        });
        svg.append(group);
    });
    return svg;
}
