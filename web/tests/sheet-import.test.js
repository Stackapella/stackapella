// Sheet music in (js/sheet-import.js; docs/sheet-music-plan.md, slices 3 and 4): a real chorale
// (Old Hundredth, written by music21, as .musicxml and .mxl), hand-made scores with repeats, endings,
// ties, chords in a voice, two verses, cut time and an instrument's intro, and MIDI files. Every
// library song goes out as MusicXML and MIDI and comes back note for note. And the patent rule
// (docs/sheet-music-patent-notes.md): no line is ever given a voice except from its own label.
import { toMusicXML } from '/js/musicxml.js';
import { toMidi } from '/js/midi.js';
import { keyFromFifths, metreFor, namedVoice, picksProblem, readMidi, readMusicXML, readSheetFile, toTemplate } from '/js/sheet-import.js';
import { validateTemplate } from '/js/templates.js';
import { assert, test } from './harness.js';

const fixture = async (name) => new Uint8Array(await (await fetch(`/tests/fixtures/${name}`)).arrayBuffer());
const SATB = ['soprano', 'alto', 'tenor', 'bass'];

async function freeSongs() {
    const index = await (await fetch('/templates/index.json')).json();
    const ids = (index.templates ?? index).map((e) => (typeof e === 'string' ? e : e.id));
    return Promise.all(ids.map(async (id) => (await fetch(`/templates/${id}.json`)).json()));
}

/** A one-part score: `measures` is the inside of each <measure>, the first with its attributes. */
function score(measures, { time = '4/4', fifths = 0, name = 'Melody', divisions = 2 } = {}) {
    const [beats, type] = time.split('/');
    const attributes = `<attributes><divisions>${divisions}</divisions><key><fifths>${fifths}</fifths></key><time><beats>${beats}</beats><beat-type>${type}</beat-type></time></attributes>`;
    return `<?xml version="1.0"?><score-partwise version="4.0"><work><work-title>Made up</work-title></work>
        <part-list><score-part id="P1"><part-name>${name}</part-name></score-part></part-list>
        <part id="P1">${measures.map((m, i) => `<measure number="${i + 1}">${i === 0 ? attributes : ''}${m}</measure>`).join('')}</part></score-partwise>`;
}
const note = (step, octave, duration, extra = '') => `<note><pitch><step>${step}</step><octave>${octave}</octave></pitch><duration>${duration}</duration><voice>1</voice>${extra}</note>`;
const word = (text, syllabic = 'single', number = 1) => `<lyric number="${number}"><syllabic>${syllabic}</syllabic><text>${text}</text></lyric>`;

test('sheet import: the chorale, as .musicxml and .mxl, is four lines as the file numbers them, named by the file, none given a voice', async () => {
    for (const name of ['old-hundredth.musicxml', 'old-hundredth.mxl']) {
        const s = await readSheetFile(new File([await fixture(name)], name));
        assert(s.title === 'Old Hundredth' && s.key === 'G' && s.timeSignature === '4/4' && s.tempo === 84 && s.pickupBeats === 1, `${name}: ${JSON.stringify([s.title, s.key, s.timeSignature, s.tempo, s.pickupBeats])}`);
        assert(s.lines.length === 4 && s.lines.map((l) => `${l.part} ${l.voice}`).join(', ') === 'Soprano Alto 1, Soprano Alto 2, Tenor Bass 1, Tenor Bass 2', `${name}: ${s.lines.map((l) => `${l.part} ${l.voice}`)}`);
        assert(s.lines.every((l) => l.notes.length === 32 && namedVoice(l) === null), `${name}: 32 notes a line, and two voices on a staff are the singer's to name`);
        assert(s.lines[0].words === 32 && s.credits.tune === 'Genevan Psalter, 1551', `${name}: the words and credits`);
        const t = toTemplate(s, { picks: s.lines.map((l, i) => ({ id: l.id, as: SATB[i] })), wordsFrom: s.lines[0].id });
        validateTemplate(t);
        assert(t.parts.map((p) => p.key).join() === 'soprano,alto,tenor,bass' && t.parts[0].melody, 'the parts as the singer set them, the soprano the tune');
        assert(t.parts[3].guide.notes[0].lyric === 'All' && t.parts[0].guide.notes[1].lyric === 'peo-', 'the words for every part, from the line picked, with our hyphens');
        assert(t.sections[0].phrases.length === 4 && t.licence.tune === 'by someone else: kept private', 'four lines, kept private');
    }
});

test('sheet import: repeats and endings written out, a tie held, a chord in a voice, verse 1, a breath, cut time as 4/4', () => {
    const xml = score([
        `<barline location="left"><repeat direction="forward"/></barline><harmony><root><root-step>C</root-step></root><kind>major</kind></harmony>${note('C', 4, 4, word('Glo', 'begin') + word('Sing', 'single', 2))}${note('D', 4, 4, word('ry,', 'end') + '<notations><articulations><breath-mark/></articulations></notations>')}`,
        `<barline location="left"><ending number="1" type="start"/></barline>${note('E', 4, 8, word('Lord'))}<barline location="right"><ending number="1" type="stop"/><repeat direction="backward"/></barline>`,
        `<barline location="left"><ending number="2" type="start"/></barline>${note('G', 4, 4, word('A', 'begin') + '<tie type="start"/>')}<note><chord/><pitch><step>E</step><octave>4</octave></pitch><duration>4</duration><voice>1</voice><tie type="start"/></note>${note('G', 4, 4, '<tie type="stop"/>')}<note><chord/><pitch><step>E</step><octave>4</octave></pitch><duration>4</duration><voice>1</voice><tie type="stop"/></note><barline location="right"><ending number="2" type="stop"/></barline>`,
    ], { time: '2/2', divisions: 2 });
    const s = readMusicXML(xml);
    assert(s.timeSignature === '4/4' && s.notices.some((n) => n.kind === 'metreWritten' && n.from === '2/2'), `cut time: ${JSON.stringify(s.notices)}`);
    assert(s.notices.some((n) => n.kind === 'repeats' && n.count === 1) && s.notices.some((n) => n.kind === 'verses' && n.count === 2), 'the repeat and the second verse are said');
    const notes = s.lines[0].notes;
    assert(notes.map((n) => n.pitches.join('+')).join(' ') === '60 62 64 60 62 64+67', `played through, the tie held: ${notes.map((n) => `${n.beat}:${n.pitches}/${n.length}`)}`);
    assert(notes.at(-1).length === 4 && notes.at(-1).beat === 12, 'the tied chord is one note of a bar');
    assert(notes[0].lyric === 'Glo-' && notes[1].lyric === 'ry,' && notes[2].br, 'verse 1, our hyphen, a breath after the mark');
    assert(s.chords.length === 2 && s.chords[1].beat === 8, 'the chord symbol comes round again with the repeat');
    const top = toTemplate(s, { picks: [{ id: s.lines[0].id, as: 'soprano' }] });
    const bottom = toTemplate(s, { picks: [{ id: s.lines[0].id, as: 'soprano', take: 'bottom' }] });
    assert(top.parts[0].guide.notes.at(-1).pitch === 67 && bottom.parts[0].guide.notes.at(-1).pitch === 64, 'the singer takes the top or the bottom notes');
    validateTemplate(top);
});

test('sheet import: an instrument intro and empty bars are dropped, and a MIDI upbeat becomes the pickup', () => {
    const rest = '<note><rest/><duration>8</duration><voice>1</voice></note>';
    const s = readMusicXML(score([rest, rest, note('G', 4, 6) + note('A', 4, 2, word('Now')), note('B', 4, 8, word('sing'))]));
    const t = toTemplate(s, { picks: [{ id: s.lines[0].id, as: 'soprano' }] });
    assert(t.pickupBeats === 0 && t.parts[0].guide.notes[0].beat === 0, `two empty bars dropped: ${JSON.stringify(t.parts[0].guide.notes)}`);
    const song = { id: 'up', title: 'Upbeat', tempo: 100, key: 'F', timeSignature: '3/4', pickupBeats: 0, credits: {}, parts: [{ key: 'soprano', name: 'Soprano', guide: { type: 'notes', notes: [{ beat: 5, length: 1, pitch: 65, lyric: 'O' }, { beat: 6, length: 3, pitch: 69, lyric: 'come' }] } }] };
    const m = readMidi(toMidi(song));
    const u = toTemplate(m, { picks: [{ id: m.lines[0].id, as: 'soprano' }] });
    assert(u.pickupBeats === 1 && u.parts[0].guide.notes.map((n) => n.beat).join() === '0,1' && u.key === 'F' && u.tempo === 100, `the upbeat: ${JSON.stringify([u.pickupBeats, u.parts[0].guide.notes, u.key, u.tempo])}`);
});

test('sheet import: every free song out as MusicXML and MIDI comes back note for note, words included', async () => {
    for (const song of await freeSongs()) {
        const parts = song.parts.filter((p) => p.guide?.notes?.length);
        for (const [kind, s] of [['MusicXML', readMusicXML(toMusicXML(song))], ['MIDI', readMidi(toMidi(song))]]) {
            assert(s.lines.length === parts.length && s.key === song.key && s.timeSignature === song.timeSignature, `${song.id} ${kind}: ${s.lines.length} lines, ${s.key}, ${s.timeSignature}`);
            assert(Math.abs(s.tempo - song.tempo) <= 1, `${song.id} ${kind}: tempo ${s.tempo}`);
            const picks = s.lines.map((l, i) => ({ id: l.id, as: namedVoice(l) ?? parts[i].key }));
            assert(picks.every((p, i) => p.as === parts[i].key), `${song.id} ${kind}: each track's own name is its voice`);
            const t = toTemplate(s, { picks });
            assert(t.pickupBeats === (song.pickupBeats || 0), `${song.id} ${kind}: the pickup ${t.pickupBeats}`);
            parts.forEach((part) => {
                const back = t.parts.find((p) => p.key === part.key).guide.notes;
                assert(back.length === part.guide.notes.length, `${song.id} ${kind} ${part.key}: ${back.length} notes of ${part.guide.notes.length}`);
                part.guide.notes.forEach((n, i) => {
                    assert(back[i].pitch === n.pitch && Math.abs(back[i].beat - n.beat) < 0.01 && Math.abs(back[i].length - n.length) < 0.01, `${song.id} ${kind} ${part.key} note ${i}`);
                    if (n.lyric) assert(back[i].lyric?.replace(/-$/, '') === n.lyric.replace(/-$/, ''), `${song.id} ${kind} ${part.key} note ${i}'s word: ${back[i].lyric}`);
                });
            });
        }
    }
});

test('sheet import: the singer decides every voice; only a part named for one voice comes named', () => {
    const line = (part, voicesInPart = 1) => ({ part, voicesInPart });
    assert(namedVoice(line('Soprano')) === 'soprano' && namedVoice(line('T.')) === 'tenor' && namedVoice(line('Bass 2')) === 'bass', 'the file says so');
    assert(namedVoice(line('Soprano Alto', 2)) === null && namedVoice(line('Women')) === null && namedVoice(line('Track 1')) === null && namedVoice(line('Piano')) === null, 'otherwise nothing');
    assert(picksProblem([{ as: 'soprano' }, { as: 'soprano' }]) === 'twice' && picksProblem([{ as: 'soprano' }, { as: 'lead' }]) === 'mixed', 'one line a voice, SATB or barbershop');
    assert(picksProblem([1, 2, 3, 4, 5].map((i) => ({ as: ['soprano', 'alto', 'tenor', 'bass', 'baritone'][i - 1] }))) !== null && picksProblem([{ as: null }]) === 'none' && picksProblem([{ as: 'alto' }]) === null, 'up to four, at least one');
    assert(keyFromFifths(-3) === 'Eb' && keyFromFifths(2, true) === 'Bm' && keyFromFifths(-7) === 'B' && metreFor(9, 8) === null, 'keys, and a metre we can\'t take');
    assert(readMusicXML('<nope').unreadable && readMusicXML(score([note('C', 4, 6)], { time: '9/8' })).unsupported === 'metre', 'a broken file and 9/8 are said, not guessed');
});
