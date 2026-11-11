// Lyrics (js/lyrics.js): the songs' words split as their files have them, and a line's syllables
// fitted to its notes, with melismas where they sit best and the note to split when there are too few.
import { fitLine, syllables } from '/js/lyrics.js';
import { assert, test } from './harness.js';

test('lyrics: the four free songs\' words split into the syllables their files have', async () => {
    for (const id of ['amazing-grace', 'happy-birthday', 'michael-row', 'are-you-sleeping']) {
        const template = await (await fetch(`/templates/${id}.json`)).json();
        const melody = template.parts.find((p) => p.melody) ?? template.parts[0];
        const filed = melody.guide.notes.map((n) => n.lyric).filter(Boolean);
        const words = filed.join(' ').replace(/- /g, '');
        const split = syllables(words);
        assert(split.join(' ') === filed.join(' '), `${id}\n got ${split.join(' ')}\nwant ${filed.join(' ')}`);
    }
});

test('lyrics: a line fits its notes: one each, an extra note held where it sits best', () => {
    const notes = [{ beat: 0, length: 1, pitch: 62 }, { beat: 1, length: 2, pitch: 67 }, { beat: 3, length: 0.5, pitch: 71 }, { beat: 3.5, length: 0.5, pitch: 67 }, { beat: 4, length: 2, pitch: 71 }];
    const { lyrics } = fitLine(syllables('Amazing grace,'), notes);
    assert(lyrics.join('|') === 'A-|maz-|ing||grace,', lyrics.join('|'));
    const short = fitLine(syllables('Amazing grace, how sweet'), notes.slice(0, 3));
    assert(short.mismatch?.syllables === 6 && short.split === 1, JSON.stringify(short));
});

test('lyrics: Spanish words split by Spanish rules, by the song\'s language', () => {
    const cases = {
        'para canción abrir transporte': 'pa- ra can- ción a- brir trans- por- te',
        'bien causa día leer': 'bien cau- sa dí- a le- er',
        'Noche de paz, noche de amor': 'No- che de paz, no- che de a- mor',
        'guitarra querida, construcción': 'gui- ta- rra que- ri- da, cons- truc- ción',
        'rey hoy y mayo': 'rey hoy y ma- yo',
        'cuidar aéreo raíz llegar': 'cui- dar a- é- re- o ra- íz lle- gar',
    };
    for (const [line, want] of Object.entries(cases)) {
        const got = syllables(line, 'es').join(' ');
        assert(got === want, `${line}: got ${got}`);
    }
    assert(syllables('Amazing grace', 'en-GB').join(' ') === 'A- maz- ing grace' && syllables('para', 'es-MX').join(' ') === 'pa- ra', 'regions follow their language');
});
