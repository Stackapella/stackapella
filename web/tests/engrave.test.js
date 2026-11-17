// The score engraver (js/engrave.js; docs/song-sheet-music-plan.md): on the pinned songs (a hymn with an upbeat, a
// round, a 6/8 tune) every written note is drawn once with when it sounds, every word is under its staff, quick notes
// in a beat share a beam, every system stays inside its page and names its bars in order, and the SVG is well formed
// with only signs the glyph module has.
import { engrave, glyphDefs, roundOf, textWidth } from '/js/engrave.js';
import { GLYPHS } from '/js/engrave-glyphs.js';
import { barsOf, measuresOf } from '/js/musicxml.js';
import { assert, test } from './harness.js';

const SONGS = ['amazing-grace', 'are-you-sleeping', 'greensleeves'];
const song = async (id) => (await fetch(`/tests/fixtures/export-pin/${id}.json`)).json();
const parse = (svg) => new DOMParser().parseFromString(svg, 'image/svg+xml');

test('engrave: every note once with its beat, every word, and well-formed systems inside the page, at three widths', async () => {
    for (const id of SONGS) {
        const template = await song(id);
        for (const options of [{}, { page: 560, perSystem: 3, names: 54 }, { page: 430, perSystem: 3, names: 54 }, { page: 1150, perSystem: 6 }]) {
            const { systems } = engrave(template, options);
            const what = `${id} at ${options.page ?? 1000}`;
            const docs = systems.map((sys) => parse(sys.svg));
            docs.forEach((doc, s) => assert(!doc.querySelector('parsererror'), `${what}: system ${s} parses`));
            assert(!systems.some((sys) => /NaN|undefined|Infinity/.test(sys.svg)), `${what}: no broken numbers`);
            // The bars, once each and in order.
            const order = systems.flatMap((sys) => Array.from({ length: sys.bars[1] - sys.bars[0] + 1 }, (_, i) => sys.bars[0] + i));
            // (A round's bars are its tune's: the first voice's, sung while the others come in.)
            const round = roundOf(template);
            const bars = barsOf(round ? { ...template, parts: [round.lead] } : template);
            assert(order.join() === bars.map((_, i) => i).join(), `${what}: bars ${order}`);
            for (const sys of systems) assert(sys.width <= (options.page ?? 1000) + 0.5 && sys.height > 0, `${what}: a system ${sys.width} wide`);
            // A round's tune is written once: its first voice's notes stand for every voice's.
            for (const part of round ? [round.lead] : template.parts.filter((p) => p.guide?.notes?.length)) {
                const drawn = docs.flatMap((doc) => [...doc.querySelectorAll(`.eg-note[data-part="${part.key}"]`)]);
                // Every written note is drawn, in one piece or tied over a bar line: never one missing, never one made up. (The
                // pieces can differ from the export's: a breath's sliver of rest is drawn as the whole note.)
                const sorted = [...part.guide.notes].sort((a, b) => a.beat - b.beat);
                const seen = new Set(drawn.map((el) => Number(el.getAttribute('data-note'))));
                assert(seen.size === sorted.length && [...seen].every((n) => n >= 0 && n < sorted.length), `${what} ${part.key}: ${seen.size} notes drawn of ${sorted.length}`);
                assert(drawn.length <= measuresOf(part, template, bars).flatMap((m) => m.items).filter((it) => !it.rest).length + sorted.length, `${what} ${part.key}: ${drawn.length} pieces`);
                for (const el of drawn) {
                    const n = sorted[Number(el.getAttribute('data-note'))];
                    assert(Math.abs(Number(el.getAttribute('data-beat')) - n.beat) < 0.006 && Math.abs(Number(el.getAttribute('data-length')) - n.length) < 0.006, `${what} ${part.key}: note ${el.getAttribute('data-note')} sounds at its beat`);
                }
                const words = docs.flatMap((doc) => [...doc.querySelectorAll(`.eg-word[data-part="${part.key}"]`)]).map((el) => el.textContent);
                const sung = sorted.filter((n) => n.lyric).map((n) => n.lyric.replace(/-$/, ''));
                assert(words.join('|') === sung.join('|'), `${what} ${part.key}: its words`);
            }
            // Only signs we carry.
            for (const doc of docs) for (const use of doc.querySelectorAll('use')) assert(use.getAttribute('href').slice(4) in GLYPHS, `${what}: ${use.getAttribute('href')}`);
        }
    }
});

test('engrave: quick notes in one beat share a beam and lose their flags; one alone keeps its flag', async () => {
    const template = await song('amazing-grace');
    const doc = parse(engrave(template).systems[0].svg);
    const soprano = doc.querySelector('[data-staff="soprano"]');
    // "-ing" in bar 1: two eighths in one beat.
    assert(soprano.querySelectorAll(':scope > path').length >= 2, 'a beam and its slur are drawn');
    assert(!soprano.querySelector('use[href^="#eg-flag"]'), 'beamed notes carry no flags');
    const alone = { ...template, parts: template.parts.map((p) => (p.key === 'soprano' ? { ...p, guide: { ...p.guide, notes: p.guide.notes.filter((n) => n.beat !== 3.5) } } : p)) };
    assert(parse(engrave(alone).systems[0].svg).querySelector('[data-staff="soprano"] use[href^="#eg-flag8th"]'), 'an eighth alone keeps its flag');
});

test('engrave: part names where asked, none where the page has its own; the signs come as one hidden block', async () => {
    const template = await song('amazing-grace');
    const named = parse(engrave(template, { nameOf: (key, first) => (first ? key.toUpperCase() : key[0]) }).systems[0].svg);
    assert([...named.querySelectorAll('text')].some((t) => t.textContent === 'SOPRANO'), 'the first system names its parts');
    const bare = parse(engrave(template, { nameOf: () => '' }).systems[0].svg);
    assert(![...bare.querySelectorAll('text')].some((t) => /soprano/i.test(t.textContent)), 'no names');
    assert(/^<svg[^>]*aria-hidden="true"/.test(glyphDefs()) && glyphDefs().includes('id="eg-gClef"'), 'the signs');
    assert(textWidth('grace,', 15) > 30 && textWidth('grace,', 15) < 50, `a word's width: ${textWidth('grace,', 15)}`);
});

test('engrave: a round is its tune on one staff, each entry numbered over its note, each note saying when the others sing it', async () => {
    const template = await song('are-you-sleeping');
    const round = roundOf(template);
    assert(round && round.voices.map((v) => `${v.key}:${v.off}`).join() === 'soprano:0,alto:8,tenor:16,bass:24', `the voices: ${JSON.stringify(round?.voices)}`);
    assert(roundOf(await song('amazing-grace')) === null, 'a hymn is no round');
    const docs = engrave(template, { page: 560, perSystem: 3, names: 84 }).systems.map((sys) => parse(sys.svg));
    assert(docs.every((doc) => doc.querySelectorAll('[data-staff]').length === 1 && doc.querySelector('[data-staff="soprano"]')), 'one staff, the first voice');
    const numbers = docs.flatMap((doc) => [...doc.querySelectorAll('.eg-entry text')].map((el) => el.textContent));
    assert(numbers.join() === '1,2,3,4', `the entries: ${numbers}`);
    const sorted = [...template.parts[0].guide.notes].sort((a, b) => a.beat - b.beat);
    for (const doc of docs) {
        for (const box of doc.querySelectorAll('.eg-entry')) {
            // On the system of the note it numbers, over it.
            const x = Number(box.querySelector('rect').getAttribute('x')) + 8;
            const n = Number(box.querySelector('text').textContent);
            const note = [...doc.querySelectorAll('.eg-note')].find((el) => Math.abs(Number(el.getAttribute('data-beat')) - sorted[0].beat - round.voices[n - 1].off) < 0.006);
            assert(note, `entry ${n} has its note on the system`);
            const head = note.querySelector('use[href^="#eg-notehead"]');
            assert(Math.abs(Number(head.getAttribute('x')) + 6 - x) < 4, `entry ${n} over its note`);
        }
    }
    const first = docs[0].querySelector('.eg-note');
    assert(first.getAttribute('data-echo') === 'alto:8 tenor:16 bass:24', `a note's echoes: ${first.getAttribute('data-echo')}`);
    // Its own part's sheet is that voice as it sings, with no entries.
    const alto = parse(engrave(template, { partKeys: ['alto'], names: 14 }).systems[0].svg);
    assert(alto.querySelector('[data-staff="alto"]') && !alto.querySelector('.eg-entry') && !alto.querySelector('[data-echo]'), "a voice's own sheet");
});
