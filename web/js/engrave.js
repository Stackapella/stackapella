// A song engraved as a choral score (docs/song-sheet-music-plan.md): print-score.js's layout drawn as a
// printed octavo is, every sign a music-font outline (js/engrave-glyphs.js), quick notes beamed by the
// beat, a slur over each syllable sung to several notes, the words hyphenated between their syllables
// with a line after a held last one, a bracket joining the staves and the bar number on each system.
// Pure: a template in, SVG text out, a system at a time, so the page, the print and the tests share it.

import { GLYPHS, WIDTHS } from './engrave-glyphs.js';
import { GAP, STAFF, printLayout, signaturePositions } from './print-score.js';

const HALF = GAP / 2;
const HEAD = 11.8; // a notehead's width
const STEM = 35; // a stem's length: three and a half spaces
const STEM_W = 1.2;
const LINE_W = 1.1; // a staff line
const BAR_W = 1.6;
const BEAM = 5; // a beam's thickness: half a space
const BEAM_GAP = 7.5; // from one beam's top to the next's
const LYRIC = 15; // the words' size
const TEXT = "Georgia, 'Times New Roman', Times, serif"; // i18n-ignore: the words' face
// The words are spaced by the face's own widths, so the page's font-size-adjust (game mode's screen type) never reaches
// them: each system resets it.
const FLAG = ['', '8th', '16th', '32nd'];
const REST = { whole: 'restWhole', half: 'restHalf', quarter: 'restQuarter', eighth: 'rest8th', '16th': 'rest16th', '32nd': 'rest32nd' };
const REST_AT = { whole: 6, half: 4, quarter: 4, eighth: 4, '16th': 4, '32nd': 4 };

const r = (n) => Math.round(n * 100) / 100;
const esc = (text) => String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

/** The glyphs as one hidden SVG of symbols, once a page; each system draws them with <use>. */
export function glyphDefs(glyphs = GLYPHS) {
    return `<svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false"><defs>${Object.entries(glyphs).map(([name, g]) => `<path id="eg-${name}" d="${g.d}"/>`).join('')}</defs></svg>`;
}

/** A text's width at a size, from the face's own widths (per 1000). */
export function textWidth(text, size, widths = WIDTHS) {
    let w = 0;
    for (const ch of String(text)) w += widths[ch] ?? 560;
    return (w * size) / 1000;
}

/**
 * A round's voices, when the song is one whose every voice sings the first one's notes from its own entry (an octave
 * apart at most, a later voice perhaps stopping early to finish with the others): `{ lead, voices: [{ key, off }] }`,
 * `off` in beats after the first voice, in order of entry. Else null.
 */
export function roundOf(template) {
    if (!(template.genre ?? []).includes('round')) return null;
    const parts = (template.parts ?? []).filter((p) => p.guide?.notes?.length);
    if (parts.length < 2) return null;
    const sorted = (part) => [...part.guide.notes].sort((a, b) => a.beat - b.beat);
    const lead = sorted(parts[0]);
    const voices = [];
    for (const part of parts) {
        const notes = sorted(part);
        const off = notes[0].beat - lead[0].beat;
        const copy = notes.length <= lead.length && notes.every((n, i) => Math.abs(n.beat - off - lead[i].beat) < 1e-6 && Math.abs(n.length - lead[i].length) < 1e-6 && (n.pitch - lead[i].pitch) % 12 === 0 && (n.lyric ?? '') === (lead[i].lyric ?? ''));
        if (!copy || (voices.length && off <= voices.at(-1).off)) return null;
        voices.push({ key: part.key, off });
    }
    return { lead: parts[0], voices };
}

/**
 * The song as engraved systems: `{ systems: [{ svg, width, height, bars, staves }], layout }` (`bars` the system's first
 * and last bar numbers, `staves` each staff's key and the top and bottom of its five lines, for a page's own controls).
 * `nameOf(key, first)` a part's name (whole on the first system, short after; '' for none, where the page has its own);
 * `tempo` false to leave the tempo mark out; the rest is printLayout's. Each note and word carries its part, its
 * `data-beat` and `data-length` (the song's beats), so a player can light it as it sounds.
 * A round (Hillel's choice, 2026-11-16) is written as a round is printed: its tune once, on one staff, with each voice's
 * entry numbered over the note it comes in on (no names, unless a width is asked for). Each of its notes also carries
 * `data-echo` ("alto:8 tenor:16"), each later voice and how many beats after the first it sings that note.
 */
export function engrave(template, { glyphs = GLYPHS, widths = WIDTHS, nameOf = (key) => key, tempo = true, ...options } = {}) {
    // An a cappella octavo carries no chord names: the parts are the harmony.
    // A breath written out as a sliver of rest (a note cut short by less than a quarter before the next breath) reads
    // as the whole note and a breath mark, as a printed score has it. Only the drawing: each note still carries its own
    // beat and length for the player, and the MusicXML keeps the notes as they are sung.
    const quarter = /\/8$/.test(template.timeSignature ?? '') ? 2 / 3 : 1; // in the song's beats
    const whole = (part) => {
        const notes = [...(part.guide?.notes ?? [])].sort((x, y) => x.beat - y.beat);
        return { ...part, guide: { ...part.guide, notes: notes.map((n, i) => {
            const gap = notes[i + 1]?.br ? notes[i + 1].beat - (n.beat + n.length) : 0;
            return gap > 1e-6 && gap < quarter - 1e-6 ? { ...n, length: n.length + gap } : n;
        }) } };
    };
    // A round, written whole (not one of its parts on its own sheet).
    const round = options.partKeys ? null : roundOf(template);
    if (round && (options.names === undefined || options.names === true)) options = { ...options, names: false };
    const song = { ...template, chords: [], parts: (round ? [round.lead] : template.parts).map(whole) };
    // Even systems: the fewest systems the words allow, then as few bars a system as keeps that count, so the last
    // system is never two lonely bars; and it ends at the margin like the rest.
    const measure = (text) => (text ? textWidth(text, LYRIC, widths) : 0);
    // Whole part names need a little more room than the app's print gives them ("Soprano" in the words' face).
    if (options.names === undefined || options.names === true) options = { ...options, names: 80 };
    let layout = printLayout(song, { fill: true, measure, ...options });
    for (let most = Math.max(...layout.systems.map((sys) => sys.bars.length)) - 1; most >= 1; most--) {
        const tighter = printLayout(song, { fill: true, measure, ...options, most });
        if (tighter.systems.length > layout.systems.length) break;
        layout = tighter;
    }
    const beat = (/\/8$/.test(template.timeSignature ?? '') ? 36 : 24);
    const use = (name, x, y, extra = '') => `<use href="#eg-${name}" x="${r(x)}" y="${r(y)}"${extra}/>`;
    const tw = (text, size = LYRIC) => textWidth(text, size, widths);
    // Each part's notes in the order the layout numbers them (by beat), for when each sounds.
    const sung = new Map(template.parts.map((p) => [p.key, [...(p.guide?.notes ?? [])].sort((a, b) => a.beat - b.beat)]));
    const echo = round ? ` data-echo="${round.voices.slice(1).map((v) => `${esc(v.key)}:${r(v.off)}`).join(' ')}"` : '';
    const when = (key, note) => {
        const n = sung.get(key)?.[note];
        return n ? ` data-beat="${r(n.beat)}" data-length="${r(n.length)}"${echo}` : '';
    };
    // A round's entries: the note each voice comes in on, numbered 1, 2, 3 in order.
    const entries = new Map();
    if (round) {
        const lead = sung.get(round.lead.key);
        round.voices.forEach((v, i) => {
            const at = lead.findIndex((n) => n.beat >= lead[0].beat + v.off - 1e-6);
            if (at >= 0 && !entries.has(at)) entries.set(at, i + 1);
        });
    }
    const yOf = (st, position) => st.y + (8 - position) * HALF;

    // Each staff's notes through the whole song, to find what runs over a system's end: a syllable's notes (its slur
    // and the line after its word) and the next syllable (its hyphen).
    const staffCount = layout.systems[0].staves.length;
    const marks = layout.systems.map(() => Array.from({ length: staffCount }, () => ({ slurs: [], hyphens: [], lines: [] })));
    for (let k = 0; k < staffCount; k++) {
        const all = [];
        layout.systems.forEach((sys, s) => {
            let bar = -1;
            let at = 0;
            for (const it of sys.staves[k].items) {
                if (it.bar !== bar) [bar, at] = [it.bar, 0];
                it.at = at;
                at += it.d;
                all.push({ it, s });
            }
        });
        // Syllables: a note with a word, and every note after it until the next word or a rest.
        const groups = [];
        let open = null;
        for (const e of all) {
            if (e.it.kind === 'rest') open = null;
            else if (e.it.word) groups.push((open = { word: e.it.word, notes: [e] }));
            else if (open) open.notes.push(e);
        }
        groups.forEach((g, i) => {
            const distinct = new Set(g.notes.map((e) => e.it.note)).size;
            const goesOn = /-$/.test(g.word);
            g.text = g.word.replace(/-$/, '');
            g.melisma = distinct > 1;
            const first = g.notes[0];
            // The word: centred under its note; under a run of notes it starts at the first one's left edge.
            first.it.lyric = { text: g.text, x: g.melisma ? first.it.x - HEAD / 2 : first.it.x - tw(g.text) / 2 };
            const end = first.it.lyric.x + tw(g.text);
            // A slur over a syllable's few notes; a vowel held through bar after bar (a bass's "oo") has its line alone.
            // (and so has a hummed syllable over long notes, where a slur would only tangle with its ties).
            const pad = /^(o+h?|a+h+|m+|h+m+)$/i.test(g.text) && g.notes.some((e) => e.it.hollow);
            if (g.melisma && !pad && g.notes.at(-1).it.bar - first.it.bar < 2) {
                for (const s of new Set(g.notes.map((e) => e.s))) marks[s][k].slurs.push({ notes: g.notes.filter((e) => e.s === s).map((e) => e.it), from: s > first.s, on: s < g.notes.at(-1).s });
            }
            const next = groups[i + 1];
            if (goesOn) marks[first.s][k].hyphens.push({ from: end, to: next && next.notes[0].s === first.s ? next.notes[0].it.x - (new Set(next.notes.map((e) => e.it.note)).size > 1 ? HEAD / 2 : tw(next.word.replace(/-$/, '')) / 2) : null });
            else if (g.notes.length > 1) {
                // A last syllable held over more notes (or a tie): a line from the word to the last of them.
                for (const s of new Set(g.notes.map((e) => e.s))) {
                    const mine = g.notes.filter((e) => e.s === s).map((e) => e.it);
                    marks[s][k].lines.push({ from: s === first.s ? end + 2 : null, to: s < g.notes.at(-1).s ? null : mine.at(-1).x + HEAD / 2 });
                }
            }
        });
    }

    const systems = layout.systems.map((sys, s) => {
        const edge = sys.edge ?? layout.edge;
        const out = [];
        const top = sys.staves[0].y;
        const bottom = sys.staves.at(-1).y + STAFF;
        const named = options.names !== false;
        // The line that starts the system, and the bracket joining its staves.
        if (sys.staves.length > 1) {
            out.push(`<rect x="${edge - BAR_W / 2}" y="${top}" width="${BAR_W}" height="${bottom - top}"/>`);
            if (named) out.push(`<rect x="${edge - 10}" y="${top - 2}" width="4.5" height="${bottom - top + 4}"/>`, use('bracketTop', edge - 10, top - 2), use('bracketBottom', edge - 10, bottom + 2));
        }
        if (sys.barNumber) out.push(`<text x="${edge + 1}" y="${r(top - 20)}" font-size="11" font-style="italic">${sys.barNumber}</text>`);
        const boxes = []; // a round's entry numbers on this system: each box's left, top and number
        sys.staves.forEach((st, k) => {
            const y = (position) => yOf(st, position);
            const ink = st.cue ? ' opacity="0.55"' : '';
            const g = [];
            for (let line = 0; line < 5; line++) g.push(`<rect x="${edge}" y="${r(st.y + line * GAP - LINE_W / 2)}" width="${r(sys.right - edge)}" height="${LINE_W}"/>`);
            const name = named ? nameOf(st.key, sys.first) : '';
            if (name) g.push(`<text x="${edge - 16}" y="${st.y + STAFF / 2 + 5}" font-size="${sys.first ? 14 : 13}" font-style="italic" text-anchor="end">${esc(name)}</text>`);
            // The clef, the key and (on the first system) the time.
            g.push(use(st.clef === 'bass' ? 'fClef' : st.clef === 'treble8' ? 'gClef8vb' : 'gClef', edge + 4, st.clef === 'bass' ? y(6) : y(2)));
            const sig = signaturePositions(template.key || 'C', st.clef);
            sig.forEach((p, i) => g.push(use(layout.signature.type === 'sharp' ? 'accidentalSharp' : 'accidentalFlat', edge + 33 + i * 9, y(p))));
            const digits = (n, cx, cy) => {
                const text = String(n);
                let x = cx - [...text].reduce((a, c) => a + glyphs['timeSig' + c].w, 0) / 2;
                return [...text].map((c) => {
                    const u = use('timeSig' + c, x, cy);
                    x += glyphs['timeSig' + c].w;
                    return u;
                }).join('');
            };
            if (sys.first) g.push(digits(layout.time.beats, edge + 33 + sig.length * 9 + 14, y(6)), digits(layout.time.beatType, edge + 33 + sig.length * 9 + 14, y(2)));
            // Bar lines, each staff its own (the words sit between the staves); the song ends thin then thick.
            sys.bars.forEach((bar, b) => {
                const x = bar.x + bar.width;
                const end = sys.last && b === sys.bars.length - 1;
                if (end) g.push(`<rect x="${r(x - 9.5)}" y="${st.y}" width="${BAR_W}" height="${STAFF}"/>`, `<rect x="${r(x - 5)}" y="${st.y}" width="5" height="${STAFF}"/>`);
                else g.push(`<rect x="${r(x - BAR_W / 2)}" y="${st.y}" width="${BAR_W}" height="${STAFF}"/>`);
                if (bar.time) g.push(digits(bar.time.beats, bar.x + 12, y(6)), digits(bar.time.beatType, bar.x + 12, y(2)));
            });

            // Beams: quick notes side by side inside one beat share a beam; alone, a note keeps its flag.
            const notes = st.items.filter((it) => it.kind === 'note');
            const beams = [];
            let run = [];
            const close = () => {
                if (run.length > 1) beams.push(run);
                run = [];
            };
            for (const it of st.items) {
                const quick = it.kind === 'note' && it.flags > 0;
                if (quick && run.length && run.at(-1).bar === it.bar && Math.floor(run.at(-1).at / beat) === Math.floor(it.at / beat) && run.at(-1).at + run.at(-1).d === it.at) run.push(it);
                else {
                    close();
                    if (quick) run = [it];
                }
            }
            close();
            const stemX = (it) => it.x + (it.stem === 'up' ? HEAD / 2 - STEM_W / 2 : -HEAD / 2 + STEM_W / 2);
            for (const it of notes) {
                // A stem reaches the middle line at least, so a note on ledger lines still joins the staff.
                it.stemEnd = it.stem === 'up' ? Math.min(y(it.position) - STEM, y(4)) : Math.max(y(it.position) + STEM, y(4));
            }
            for (const group of beams) {
                // One direction for the group, by its note furthest from the middle line; the beam leans with the notes,
                // by a space at most, and moves out until its shortest stem is long enough.
                const far = group.reduce((a, it) => (Math.abs(it.position - 4) > Math.abs(a.position - 4) ? it : a));
                const up = far.position < 4;
                const dir = up ? -1 : 1;
                group.forEach((it) => (it.stem = up ? 'up' : 'down'));
                const [a, z] = [group[0], group.at(-1)];
                const lean = Math.max(-GAP, Math.min(GAP, (y(z.position) - y(a.position)) / 2));
                const slope = lean / (stemX(z) - stemX(a));
                let base = y(a.position) + dir * STEM;
                const at = (it) => base + (stemX(it) - stemX(a)) * slope;
                const short = Math.min(...group.map((it) => dir * (at(it) - y(it.position))));
                const least = STEM - 7 + (Math.max(...group.map((it) => it.flags)) - 1) * 5;
                if (short < least) base += dir * (least - short);
                group.forEach((it) => {
                    it.stemEnd = at(it);
                    it.beamed = true;
                });
                const strip = (x1, x2, level) => {
                    const off = -dir * level * BEAM_GAP;
                    const [y1, y2] = [base + (x1 - stemX(a)) * slope + off, base + (x2 - stemX(a)) * slope + off];
                    return `<path d="M${r(x1)} ${r(y1)}L${r(x2)} ${r(y2)}L${r(x2)} ${r(y2 - dir * BEAM)}L${r(x1)} ${r(y1 - dir * BEAM)}Z"/>`;
                };
                const [x1, x2] = [stemX(a) - STEM_W / 2, stemX(z) + STEM_W / 2];
                g.push(strip(x1, x2, 0));
                for (let level = 1; level < 3; level++) {
                    let i = 0;
                    while (i < group.length) {
                        if (group[i].flags <= level) {
                            i++;
                            continue;
                        }
                        let j = i;
                        while (j + 1 < group.length && group[j + 1].flags > level) j++;
                        if (j > i) g.push(strip(stemX(group[i]) - STEM_W / 2, stemX(group[j]) + STEM_W / 2, level));
                        else g.push(i === 0 ? strip(x1, x1 + 12, level) : strip(stemX(group[i]) + STEM_W / 2 - 12, stemX(group[i]) + STEM_W / 2, level));
                        i = j + 1;
                    }
                }
            }

            for (const it of st.items) {
                if (it.kind === 'rest') {
                    const name = REST[it.type] ?? 'restQuarter';
                    g.push(use(name, it.x - glyphs[name].w / 2, y(REST_AT[it.type] ?? 4)));
                    if (it.dot) g.push(use('augmentationDot', it.x + glyphs[name].w / 2 + 3, y(5)));
                    continue;
                }
                const n = [];
                const head = it.stemless ? 'noteheadWhole' : it.hollow ? 'noteheadHalf' : 'noteheadBlack';
                const hw = glyphs[head].w;
                for (const p of it.ledgers) n.push(`<rect x="${r(it.x - hw / 2 - 3.5)}" y="${r(y(p) - 0.8)}" width="${r(hw + 7)}" height="1.6"/>`);
                if (it.accidental) {
                    const name = { sharp: 'accidentalSharp', flat: 'accidentalFlat', natural: 'accidentalNatural', doubleSharp: 'accidentalDoubleSharp', doubleFlat: 'accidentalDoubleFlat' }[it.accidental];
                    if (name) n.push(use(name, it.x - hw / 2 - 2.5 - glyphs[name].w, y(it.position)));
                }
                n.push(use(head, it.x - hw / 2, y(it.position)));
                if (!it.stemless) {
                    const sx = stemX(it);
                    const [from, to] = [y(it.position) + (it.stem === 'up' ? -1.6 : 1.6), it.stemEnd];
                    n.push(`<rect x="${r(sx - STEM_W / 2)}" y="${r(Math.min(from, to))}" width="${STEM_W}" height="${r(Math.abs(to - from))}"/>`);
                    if (FLAG[it.flags] && !it.beamed) n.push(use(`flag${FLAG[it.flags]}${it.stem === 'up' ? 'Up' : 'Down'}`, sx - STEM_W / 2, to)); // i18n-ignore: a sign's name
                }
                if (it.dot) n.push(use('augmentationDot', it.x + hw / 2 + 3, y(it.position % 2 === 0 ? it.position + 1 : it.position)));
                g.push(`<g class="eg-note" data-part="${esc(st.key)}" data-note="${it.note}"${st.cue ? '' : when(st.key, it.note)}>${n.join('')}</g>`);
                if (entries.has(it.note) && !it.tieIn) {
                    // Over the note and its stem, and clear of the staff.
                    const head = y(it.position) - 7;
                    const above = Math.min(st.y - 6, head - 5, it.stem === 'up' && !it.stemless ? it.stemEnd - 5 : head - 5);
                    boxes.push({ x: it.x - 8, top: above - 16, n: entries.get(it.note) });
                    entries.delete(it.note);
                }
                if (it.breath && st.items[st.items.indexOf(it) + 1]?.kind !== 'rest') g.push(use('breathMarkComma', it.x + 13, st.y - 4));
                if (it.lyric) g.push(`<text class="eg-word" data-part="${esc(st.key)}" data-note="${it.note}"${st.cue ? '' : when(st.key, it.note)} x="${r(it.lyric.x)}" y="${r(st.lyricY)}" font-size="${LYRIC}">${esc(it.lyric.text)}</text>`);
            }
            for (const tr of st.triplets) g.push(use('tuplet3', tr.x - 4, st.y - Math.max(10, (st.high - 8) * HALF + 10)));

            // A curve, thicker in its middle: ties and slurs.
            const curve = (x1, y1, x2, y2, up) => {
                const lift = (up ? -1 : 1) * Math.max(5, Math.min(13, (x2 - x1) * 0.16));
                const [c1, c2] = [x1 + (x2 - x1) * 0.25, x1 + (x2 - x1) * 0.75];
                const thick = up ? -1.9 : 1.9;
                return `<path d="M${r(x1)} ${r(y1)}C${r(c1)} ${r(y1 + lift)} ${r(c2)} ${r(y2 + lift)} ${r(x2)} ${r(y2)}C${r(c2)} ${r(y2 + lift + thick)} ${r(c1)} ${r(y1 + lift + thick)} ${r(x1)} ${r(y1)}Z"/>`;
            };
            for (const tie of st.ties) {
                const ty = y(tie.position) + (tie.up ? -5.5 : 5.5);
                g.push(curve(tie.x1 + 1, ty, tie.x2 - 1, ty, tie.up));
            }
            const mark = marks[s][k];
            for (const slur of mark.slurs) {
                // Tied pieces of one note are the tie's; the slur runs over the notes that move.
                const [a, z] = [slur.notes[0], slur.notes.at(-1)];
                if (!slur.from && !slur.on && new Set(slur.notes.map((it) => it.note)).size < 2) continue;
                const up = slur.notes.some((it) => it.stem === 'down');
                // On the heads' side when every stem points away; else over the stems.
                const endY = (it) => (up ? Math.min(y(it.position), it.stem === 'up' ? it.stemEnd : 1e9) - 8 : y(it.position) + 8);
                let [x1, y1, x2, y2] = [slur.from ? sys.left - 4 : a.x + (a.tieOut ? 0 : 1), endY(a), slur.on ? sys.right - 2 : z.x, endY(z)];
                const inner = slur.notes.map(endY);
                const reach = up ? Math.min(...inner) : Math.max(...inner);
                // Level with its outermost note where a note between would cut the curve.
                if (up ? reach < Math.min(y1, y2) : reach > Math.max(y1, y2)) [y1, y2] = [(y1 + reach) / 2, (y2 + reach) / 2];
                g.push(curve(x1, y1, x2, y2, up));
            }
            for (const h of mark.hyphens) {
                const to = h.to ?? h.from + 14;
                const gap = to - h.from;
                if (gap < 5) continue;
                const count = Math.max(1, Math.floor(gap / 70));
                for (let i = 1; i <= count; i++) {
                    const cx = h.from + (gap * i) / (count + 1);
                    g.push(`<rect x="${r(cx - 2.6)}" y="${r(st.lyricY - 4.6)}" width="5.2" height="1.1"/>`);
                }
            }
            for (const line of mark.lines) {
                const from = line.from ?? sys.left;
                const to = line.to ?? sys.right - 3;
                if (to - from > 18) g.push(`<rect x="${r(from)}" y="${r(st.lyricY - 0.4)}" width="${r(to - from)}" height="0.9"/>`);
            }
            out.push(`<g data-staff="${esc(st.key)}"${ink}>${g.join('')}</g>`);
        });
        for (const box of boxes) out.push(`<g class="eg-entry"><rect x="${r(box.x)}" y="${r(box.top)}" width="16" height="16" rx="2" fill="none" stroke="currentColor" stroke-width="1.1"/><text x="${r(box.x + 8)}" y="${r(box.top + 12.5)}" font-size="12.5" font-weight="700" text-anchor="middle">${box.n}</text></g>`);
        // The tempo over the first system: above an entry's number where the two would meet.
        let lift = 0;
        if (sys.first && tempo && template.tempo) {
            const x = edge + 30 + signaturePositions(template.key || 'C', 'treble').length * 9;
            const dotted = beat === 36;
            const end = x + (dotted ? 17 : 13) + tw(`= ${template.tempo}`, 14);
            for (const box of boxes) if (box.x < end + 4 && box.x + 16 > x - 4 && box.top < top - 14) lift = Math.max(lift, top - 14 - box.top + 3);
            out.push(`<g transform="translate(${r(x)} ${r(top - 20 - lift)}) scale(0.72)">${use('metNoteQuarterUp', 0, 0)}${dotted ? use('metAugmentationDot', 16, 0) : ''}</g>`, `<text x="${r(x + (dotted ? 17 : 13))}" y="${r(top - 19 - lift)}" font-size="14" font-weight="700">= ${esc(template.tempo)}</text>`);
        }
        const width = Math.max(sys.right + 2, options.page ?? layout.width ?? 0);
        // A staff with no word on this system may still carry the line after one held from the system before.
        const tall = Math.max(sys.height, ...sys.staves.map((st, k) => (marks[s][k].lines.length ? st.lyricY + 8 : 0)));
        // Room over the top staff for the tempo, the bar number and the clef's curl (and a round's entry numbers).
        const pad = boxes.length ? Math.max(24 + lift, Math.ceil(4 - Math.min(...boxes.map((box) => box.top), top - 38 - lift))) : 24;
        return {
            width,
            height: tall + pad,
            pad,
            bars: [sys.bars[0].index, sys.bars.at(-1).index],
            staves: sys.staves.map((st) => ({ key: st.key, top: st.y + pad, bottom: st.y + STAFF + pad })),
            svg: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 ${-pad} ${r(width)} ${r(tall + pad)}" fill="currentColor" font-family="${TEXT}" style="font-size-adjust:none" data-system="${s}">${out.join('')}</svg>`,
        };
    });
    return { systems, layout };
}
