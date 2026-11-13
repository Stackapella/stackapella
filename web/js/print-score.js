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
const TIME_CHANGE = 18; // room after a bar line where the metre changes, for its new time signature
const WORD_CHAR = 8.5; // a word's width per letter at the print's 15px (and a chord name's at 14px bold)
const HEAD_GAP = 16; // two notes' heads never closer
const WORD_GAP = 6; // between two words

const wordWidth = (text) => (text ? text.length * WORD_CHAR : 0);
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
        // A song whose metre changes: each note's bar is known, so accidentals go by bar index (exact in any metre).
        const changing = bars.some((bar) => bar.timeSignature);
        const laid = changing
            ? staffLayout(notes.map((it) => ({ beat: it.bar * 1e6 + it.at, length: it.d, pitch: it.pitch })), { key, clef, beatsPerBar: 1e6, pickupBeats: 0 }).notes
            : staffLayout(notes.map((it) => ({ beat: (bars[it.bar].start + it.at) / scale, length: it.d / scale, pitch: it.pitch })), { key, clef, beatsPerBar: metre.perBar, pickupBeats: template.pickupBeats ?? 0 }).notes;
        notes.forEach((it, i) => {
            const n = laid[i];
            Object.assign(it, { position: n.position, accidental: it.tieIn ? null : n.accidental, ledgers: n.ledgers, stem: n.position < 4 ? 'up' : 'down', name: n.name });
        });
        return { part, cue: isCue, clef, measures };
    });

    // Each bar's spacing needs, across every staff (the parts share their onsets): from each onset to the next, room for the
    // widest pair of words or chord names and for the heads; at its bar lines, room for half its first and last word, so
    // no word runs into a neighbour's (quick 6/8 pairs ran together at the bar's proportional spacing).
    const spacingOf = bars.map((bar, i) => {
        const at = new Map(); // onset -> { word, head }
        const mark = (onset, text, accidental) => {
            const was = at.get(onset) ?? { word: 0, accidental: false };
            at.set(onset, { word: Math.max(was.word, wordWidth(text)), accidental: was.accidental || accidental });
        };
        for (const st of staffBars) {
            const m = st.measures[i];
            for (const it of m.items) mark(it.at, it.rest || it.tieIn ? null : it.word ? it.word.text + '-' : null, !!it.accidental);
            for (const c of m.chords) mark(c.at, chordName(c.chord, 0, key), false);
        }
        const onsets = [...at.keys()].sort((a, b) => a - b);
        const needs = onsets.map((o, k) => (k === 0 ? 0 : Math.max(HEAD_GAP + (at.get(o).accidental ? 12 : 0), (at.get(onsets[k - 1]).word + at.get(o).word) / 2 + WORD_GAP)));
        const startPad = Math.max(BAR_PAD, (onsets.length ? at.get(onsets[0]).word / 2 : 0) + 4 + (onsets.length && at.get(onsets[0]).accidental ? 12 : 0));
        const endPad = Math.max(BAR_PAD, (onsets.length ? at.get(onsets.at(-1)).word / 2 : 0) + 4);
        return { onsets, needs, startPad, endPad, min: startPad + needs.reduce((a, b) => a + b, 0) + endPad, length: bar.length };
    });

    // Systems: the pickup goes with the first one; then as many bars as fit their words, at most the page's usual count.
    const per = perSystem ?? barsPerSystem(template.timeSignature);
    const signatureWidth = (clef) => signaturePositions(key, clef).length * ACCIDENTAL;
    const fullBar = metre.perBar * scale;
    const leftOf = (s) => edge + CLEF + signatureWidth('treble') + (s === 0 ? TIME : 0) + 8;
    const right = page - 2;
    // One width per division for every system, so a short last system ends early (ragged right), as before.
    const unit = (right - leftOf(1)) / (per * fullBar);
    const natural = (i) => Math.max(bars[i].length, fullBar / 3) * unit;
    // A bar where the metre changes (docs/meters-plan.md) keeps room for its new time signature too.
    const changesAt = (i) => i > 0 && bars[i].timeSignature && bars[i].timeSignature !== bars[i - 1].timeSignature;
    const widthOf = (i) => Math.max(natural(i), spacingOf[i].min + (changesAt(i) ? TIME_CHANGE : 0));
    const groups = [];
    let b = 0;
    while (b < bars.length) {
        const most = per + (b === 0 && bars[0].pickup ? 1 : 0);
        const room = right - leftOf(groups.length);
        const group = [b];
        let used = widthOf(b);
        while (group.length < most && b + group.length < bars.length && used + widthOf(b + group.length) <= room + 0.5) {
            used += widthOf(b + group.length);
            group.push(b + group.length);
        }
        groups.push(group);
        b += group.length;
    }
    const systems = groups.map((indexes, s) => {
        const left = leftOf(s);
        // Every system but the last fills the line; the last ends where its bars do (and may not run past the page).
        const widths = indexes.map(widthOf);
        const total = widths.reduce((a, w) => a + w, 0);
        const stretch = s < groups.length - 1 || total > right - left ? (right - left) / total : 1;
        let x = left;
        const sysBars = indexes.map((i, k) => {
            const width = widths[k] * stretch;
            const bar = { index: i, x, width };
            // The metre changes here (docs/meters-plan.md): its time signature after the bar line, and what a beat was.
            const changed = i > 0 && bars[i].timeSignature && bars[i].timeSignature !== bars[i - 1].timeSignature;
            if (changed) {
                const before = metreOf(bars[i - 1].timeSignature);
                const after = metreOf(bars[i].timeSignature);
                bar.time = { beats: after.beats, beatType: after.beatType, from: before.beatType === 8 ? 'dotted' : 'plain', to: after.beatType === 8 ? 'dotted' : 'plain' };
            }
            x += width;
            return bar;
        });
        const widthUsed = x;
        /** An onset's x in its bar: by its time, pushed on where words or heads need room, and fitted inside the bar. */
        const placed = new Map(); // bar index -> Map(onset -> x from the bar's start)
        const xOf = (bar, at) => {
            if (!placed.has(bar.index)) {
                const sp = spacingOf[bar.index];
                const room = bar.time ? TIME_CHANGE : 0; // a time signature written after this bar line
                const inner = bar.width - sp.startPad - room - sp.endPad;
                const gaps = sp.onsets.map((o, k) => (k === 0 ? (o / sp.length) * inner : Math.max(sp.needs[k], ((o - sp.onsets[k - 1]) / sp.length) * inner)));
                let tail = sp.onsets.length ? ((sp.length - sp.onsets.at(-1)) / sp.length) * inner : 0;
                const over = gaps.reduce((a, g) => a + g, 0) + tail - inner;
                if (over > 0) {
                    // Pushed past the bar: give back from the time-spaced gaps (never from what the words need).
                    const spare = gaps.reduce((a, g, k) => a + Math.max(0, g - (k === 0 ? 0 : sp.needs[k])), 0) + tail;
                    const keep = spare > 0 ? Math.max(0, 1 - over / spare) : 0;
                    gaps.forEach((g, k) => (gaps[k] = (k === 0 ? 0 : sp.needs[k]) + Math.max(0, g - (k === 0 ? 0 : sp.needs[k])) * keep));
                    tail *= keep;
                }
                const xs = new Map();
                let along = sp.startPad + room;
                sp.onsets.forEach((o, k) => {
                    along += gaps[k];
                    xs.set(o, along);
                });
                placed.set(bar.index, xs);
            }
            return bar.x + (placed.get(bar.index).get(at) ?? BAR_PAD);
        };
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
