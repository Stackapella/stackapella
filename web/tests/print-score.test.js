// Printable parts (js/print-score.js; docs/sheet-music-plan.md, slice 2): every free song laid out as
// a printed score has every bar once and in order, every note inside its bar and the page, the same
// notes as the MusicXML export, each tie drawn, the key signature on every staff, and one part's
// sheet the tune above it only when asked.
import { barsOf, measuresOf } from '/js/musicxml.js';
import { barsPerSystem, PAGE, printLayout, signaturePositions } from '/js/print-score.js';
import { assert, test } from './harness.js';

async function freeSongs() {
    const index = await (await fetch('/templates/index.json')).json();
    const ids = (index.templates ?? index).map((e) => (typeof e === 'string' ? e : e.id));
    return Promise.all(ids.map(async (id) => (await fetch(`/templates/${id}.json`)).json()));
}

test('print: every free song, every bar once and in order, every note inside its bar and the page, as the export writes them', async () => {
    for (const song of await freeSongs()) {
        const layout = printLayout(song);
        const bars = barsOf(song);
        const order = layout.systems.flatMap((s) => s.bars.map((b) => b.index));
        assert(order.join() === bars.map((_, i) => i).join(), `${song.id}: bars ${order}`);
        const written = song.parts.filter((p) => p.guide?.notes?.length);
        written.forEach((part, k) => {
            const items = layout.systems.flatMap((s) => s.staves[k].items);
            const exported = measuresOf(part, song, bars).flatMap((m) => m.items);
            assert(items.length === exported.length, `${song.id} ${part.key}: ${items.length} printed of ${exported.length}`);
            assert(items.filter((i) => i.kind === 'note').length === exported.filter((i) => !i.rest).length, `${song.id} ${part.key}: the notes`);
        });
        for (const sys of layout.systems) {
            assert(sys.right <= PAGE && sys.left > 0, `${song.id}: a system ${sys.left} to ${sys.right}`);
            for (const st of sys.staves) {
                for (const it of st.items) {
                    const bar = sys.bars.find((b) => b.index === it.bar);
                    assert(it.x >= bar.x && it.x <= bar.x + bar.width, `${song.id} ${st.key}: an item outside its bar`);
                }
                const tiesOut = st.items.filter((i) => i.tieOut).length;
                assert(st.ties.length >= tiesOut, `${song.id} ${st.key}: ${st.ties.length} ties for ${tiesOut}`);
            }
            const ys = sys.staves.map((s) => s.y);
            assert(ys.every((y, i) => i === 0 || y > ys[i - 1] + 40) && sys.height > ys.at(-1) + 40, `${song.id}: staves stacked apart`);
        }
        assert(layout.systems.filter((s) => s.barNumber === null).length === 1, `${song.id}: a bar number on every system but the first`);
    }
});

test('print: one part has the tune greyed above only when asked, and only when it isn\'t the tune', async () => {
    const song = (await freeSongs()).find((s) => s.id === 'amazing-grace');
    const alto = printLayout(song, { partKeys: ['alto'], cue: true });
    assert(alto.systems[0].staves.map((s) => `${s.key}${s.cue ? '*' : ''}`).join() === 'soprano*,alto', 'the tune above the alto');
    assert(printLayout(song, { partKeys: ['alto'] }).systems[0].staves.length === 1, 'not unless asked');
    assert(printLayout(song, { partKeys: ['soprano'], cue: true }).systems[0].staves.length === 1, 'the tune needs no tune above it');
    assert(alto.systems.every((s) => s.staves.every((st) => !st.chords.length)), 'no chords over a greyed tune or the alto');
    assert(printLayout(song).systems[0].staves[0].chords.length > 0, 'the chords over the tune in the score');
});

test('print: systems hold about sixteen beats, and the key signature sits where each clef has it', () => {
    assert(barsPerSystem('4/4') === 4 && barsPerSystem('3/4') === 5 && barsPerSystem('2/4') === 8 && barsPerSystem('6/8') === 5, 'bars a system');
    assert(signaturePositions('A', 'treble').join() === '8,5,9' && signaturePositions('A', 'bass').join() === '6,3,7', 'three sharps: F, C, G');
    assert(signaturePositions('Bb', 'treble').join() === '4,7' && signaturePositions('C', 'treble').length === 0, 'two flats; none');
});
