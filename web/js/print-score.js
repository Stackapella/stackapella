// Printable parts (docs/sheet-music-plan.md, slice 2): a song laid out as a printed score, systems of
// a few bars with every part's staff, the clef, key and time, notes with their accidentals, stems,
// flags and dots, rests, ties over bar lines, triplets, breath marks, the words under each staff and
// the chords over the tune. Pure geometry from the same bars the MusicXML export writes
// (musicxml.js's measuresOf), so the two never disagree; views/print.js draws it as SVG. Tested in
// tests/print-score.test.js. Vocal music's style: a flag on each note (a syllable each), no beams.

import { barsOf, clefNameOf, DIVISIONS, measuresOf, metreOf } from './musicxml.js';
import { chordName } from './notes.js';
import { keySignature, staffLayout } from './sing.js';

export const PAGE = 1000; // a system's width, in SVG units; the page scales it to the paper
export const GAP = 10; // between staff lines
const HALF = GAP / 2;
export const STAFF = 4 * GAP; // a staff's height, top line to bottom line
export const NAMES = 72; // the part names, left of the system
const CLEF = 30;
const ACCIDENTAL = 8; // a key signature's step
const TIME = 22;
const BAR_PAD = 14; // room inside a bar before its first note and after its last
const SHARP_AT = [8, 5, 9, 6, 3, 7, 4];
const FLAT_AT = [4, 7, 3, 6, 2, 5, 1];
const FLAGS = { eighth: 1, '16th': 2, '32nd': 3 };

/** How many bars a system holds: about sixteen quarter notes' worth, from two to eight bars. */
export function barsPerSystem(timeSignature) {
    const m = metreOf(timeSignature);
    return Math.max(2, Math.min(8, Math.floor(16 / (m.perBar * m.quarters))));
}

/** Where a key signature's sharps or flats sit (0 the bottom line), for a clef. */
export function signaturePositions(key, clef) {
    const s = keySignature(key);
    if (!s.type) return [];
    const shift = clef === 'bass' ? -2 : 0;
    return (s.type === 'sharp' ? SHARP_AT : FLAT_AT).slice(0, s.count).map((p) => p + shift);
}

/**
 * The song as printed systems: `{ systems, signature, time, melodyKey }`. `partKeys`: the parts, in
 * the template's order (default every part with notes). `names`: false for no part-names column (a
 * lesson's reading line). `perSystem`: bars a system, when not the page's (a phone reads two), and
 * `page` the width they share (a reading line's is narrower, so its stave draws larger). `cue`: with one part that isn't the tune,
 * the tune's staff above it, greyed (the plan's "melody small above"). Each system:
 * `{ height, barNumber, left, right, bars: [{ x, width }], staves }`, a staff
 * `{ key, cue, clef, y, items, ties, triplets, chords }` (`y` its top line), an item a note or rest
 * with its `x` and, for a note, its staff `position` (0 the bottom line), `accidental`, `ledgers`,
 * `stem`, `flags`, `hollow`, `dot`, `word` and `breath`.
 */
export function printLayout(template, { partKeys = null, cue = false, names = true, perSystem = null, page = PAGE } = {}) {
    const edge = names ? NAMES : 8; // where the staves start: after the part names, or at once (a reading line)
    const key = template.key || 'C';
    const metre = metreOf(template.timeSignature);
    const scale = metre.quarters * DIVISIONS;
    const bars = barsOf(template);
    const written = template.parts.filter((p) => (p.guide?.notes ?? []).length);
    const parts = written.filter((p) => !partKeys || partKeys.includes(p.key));
    if (!parts.length) throw new Error('No written parts to print');
    const melody = written.find((p) => p.melody) ?? written[0];
    const staves = parts.map((part) => ({ part, cue: false }));
    if (cue && parts.length === 1 && parts[0] !== melody) staves.unshift({ part: melody, cue: true });

    // Each staff's bars, the notes placed on it by the Staff's own spelling (accidentals kept per bar).
    const staffBars = staves.map(({ part, cue: isCue }) => {
        const clef = clefNameOf(part);
        const measures = measuresOf(part, template, bars, { melody: part === melody && !isCue });
        const notes = [];
        measures.forEach((m, b) => m.items.forEach((item) => {
            item.bar = b;
            if (!item.rest) notes.push(item);
        }));
        const laid = staffLayout(notes.map((it) => ({ beat: (bars[it.bar].start + it.at) / scale, length: it.d / scale, pitch: it.pitch })), { key, clef, beatsPerBar: metre.perBar, pickupBeats: template.pickupBeats ?? 0 }).notes;
        notes.forEach((it, i) => {
            const n = laid[i];
            Object.assign(it, { position: n.position, accidental: it.tieIn ? null : n.accidental, ledgers: n.ledgers, stem: n.position < 4 ? 'up' : 'down', name: n.name });
        });
        return { part, cue: isCue, clef, measures };
    });

    // Systems: the pickup goes with the first one; the bars after it in groups.
    const per = perSystem ?? barsPerSystem(template.timeSignature);
    const groups = [];
    let b = 0;
    while (b < bars.length) {
        const take = per + (b === 0 && bars[0].pickup ? 1 : 0);
        groups.push(bars.slice(b, b + take).map((_, i) => b + i));
        b += take;
    }
    const signatureWidth = (clef) => signaturePositions(key, clef).length * ACCIDENTAL;
    const fullBar = metre.perBar * scale;
    const systems = groups.map((indexes, s) => {
        const left = edge + CLEF + signatureWidth('treble') + (s === 0 ? TIME : 0) + 8;
        const right = page - 2;
        // One width per division for every system, so a short last system ends early (ragged right);
        // a system with the pickup too fits it in.
        const length = indexes.reduce((sum, i) => sum + Math.max(bars[i].length, fullBar / 3), 0);
        const unit = (right - left) / Math.max(per * fullBar, length);
        let x = left;
        const sysBars = indexes.map((i) => {
            const width = Math.max(bars[i].length, fullBar / 3) * unit;
            const bar = { index: i, x, width };
            x += width;
            return bar;
        });
        const widthUsed = x;
        const xOf = (bar, at) => bar.x + BAR_PAD + (at / bars[bar.index].length) * (bar.width - 2 * BAR_PAD);
        const laidStaves = staffBars.map((st) => {
            const items = [];
            const chords = [];
            for (const bar of sysBars) {
                const m = st.measures[bar.index];
                const wholeRest = m.items.length === 1 && m.items[0].rest && !bars[bar.index].pickup;
                for (const it of m.items) {
                    if (it.rest) {
                        items.push({ kind: 'rest', type: wholeRest ? 'whole' : it.type, dot: !wholeRest && !!it.dot, x: wholeRest ? bar.x + bar.width / 2 : xOf(bar, it.at), triplet: !!it.triplet, d: it.d, bar: bar.index });
                    } else {
                        items.push({
                            kind: 'note', x: xOf(bar, it.at), position: it.position, accidental: it.accidental, ledgers: it.ledgers, stem: it.stem,
                            flags: FLAGS[it.type] ?? 0, hollow: it.type === 'half' || it.type === 'whole', stemless: it.type === 'whole', dot: !!it.dot,
                            triplet: !!it.triplet, d: it.d, tieIn: it.tieIn, tieOut: it.tieOut, breath: it.breath, bar: bar.index, note: it.note, pitch: it.pitch, name: it.name,
                            word: it.word ? it.word.text + (it.word.syllabic === 'begin' || it.word.syllabic === 'middle' ? '-' : '') : null,
                        });
                    }
                }
                for (const c of m.chords) chords.push({ x: xOf(bar, c.at), name: chordName(c.chord, 0, key) });
            }
            // Triplets: each run of three-in-the-time-of-two marked "3" over its middle.
            const triplets = [];
            let run = [];
            const flush = () => {
                for (let i = 0; i + 2 < run.length; i += 3) triplets.push({ x: run[i + 1].x });
                run = [];
            };
            for (const it of items) {
                if (it.triplet && (!run.length || (run.at(-1).bar === it.bar && run.at(-1).d === it.d))) run.push(it);
                else {
                    flush();
                    if (it.triplet) run.push(it);
                }
            }
            flush();
            const positions = items.filter((i) => i.kind === 'note').map((i) => i.position);
            return { key: st.part.key, cue: st.cue, clef: st.clef, items, chords, triplets, high: Math.max(8, ...positions), low: Math.min(0, ...positions), words: items.some((i) => i.word) };
        });
        // Ties within the system; one leaving it runs to its edge, one arriving starts at its left.
        for (const st of laidStaves) {
            st.ties = [];
            const notes = st.items.filter((i) => i.kind === 'note');
            notes.forEach((n, i) => {
                if (n.tieOut) st.ties.push({ x1: n.x + 6, x2: notes[i + 1]?.tieIn ? notes[i + 1].x - 6 : widthUsed - 2, position: n.position, up: n.stem === 'down' });
                if (n.tieIn && i === 0) st.ties.push({ x1: Math.max(left - 6, n.x - 30), x2: n.x - 6, position: n.position, up: n.stem === 'down' });
            });
        }
        // Stacked: room above each staff for its high notes, chords and marks, below for low notes and words.
        let y = 0;
        laidStaves.forEach((st, i) => {
            const above = Math.max(14, (st.high - 8) * HALF + 16) + (st.chords.length ? 16 : 0) + (st.triplets.length ? 10 : 0) + (i === 0 ? 14 : 0);
            const below = Math.max(16, -st.low * HALF + 16) + (st.words ? 26 : 0);
            st.y = y + above;
            st.lyricY = st.y + STAFF + Math.max(16, -st.low * HALF + 14) + 9;
            y = st.y + STAFF + below;
        });
        const first = indexes[0];
        return {
            height: y + 6,
            barNumber: s === 0 ? null : first + (bars[0].pickup ? 0 : 1),
            first: s === 0,
            last: s === groups.length - 1,
            left,
            right: widthUsed,
            bars: sysBars,
            staves: laidStaves,
        };
    });
    return {
        systems,
        edge,
        width: names ? PAGE : null, // a page's systems share its width; a reading line is as wide as its music
        signature: keySignature(key),
        time: { beats: metre.beats, beatType: metre.beatType },
        melodyKey: melody.key,
    };
}
