// Sheet music in (js/sheet-import.js; docs/sheet-music-plan.md, slices 3 and 4): a real chorale
// (Old Hundredth, written by music21, as .musicxml and .mxl), hand-made scores with repeats, endings,
// ties, chords in a voice, two verses, cut time and an instrument's intro, and MIDI files. Every
// library song goes out as MusicXML and MIDI and comes back note for note. And the patent rule
// (docs/sheet-music-patent-notes.md): no line is ever given a voice except from its own label.
import { toMusicXML } from '/js/musicxml.js';
import { toMidi } from '/js/midi.js';
import { barList, keyFromFifths, metreFor, namedVoice, picksProblem, playOrder, readMidi, readMusicXML, readSheetFile, toTemplate } from '/js/sheet-import.js';
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

test('sheet import: repeats and endings written out, a tie held, a chord in a voice, verse 2 on the repeat, a breath, cut time as 4/4', () => {
    const xml = score([
        `<barline location="left"><repeat direction="forward"/></barline><harmony><root><root-step>C</root-step></root><kind>major</kind></harmony>${note('C', 4, 4, word('Glo', 'begin') + word('Sing', 'single', 2))}${note('D', 4, 4, word('ry,', 'end') + '<notations><articulations><breath-mark/></articulations></notations>')}`,
        `<barline location="left"><ending number="1" type="start"/></barline>${note('E', 4, 8, word('Lord'))}<barline location="right"><ending number="1" type="stop"/><repeat direction="backward"/></barline>`,
        `<barline location="left"><ending number="2" type="start"/></barline>${note('G', 4, 4, word('A', 'begin') + '<tie type="start"/>')}<note><chord/><pitch><step>E</step><octave>4</octave></pitch><duration>4</duration><voice>1</voice><tie type="start"/></note>${note('G', 4, 4, '<tie type="stop"/>')}<note><chord/><pitch><step>E</step><octave>4</octave></pitch><duration>4</duration><voice>1</voice><tie type="stop"/></note><barline location="right"><ending number="2" type="stop"/></barline>`,
    ], { time: '2/2', divisions: 2 });
    const s = readMusicXML(xml);
    assert(s.timeSignature === '4/4' && s.notices.some((n) => n.kind === 'metreWritten' && n.from === '2/2'), `cut time: ${JSON.stringify(s.notices)}`);
    assert(s.notices.some((n) => n.kind === 'repeats' && n.count === 1) && !s.notices.some((n) => n.kind.startsWith('verses')), `the repeat is said, and both verses are sung: ${JSON.stringify(s.notices)}`);
    const notes = s.lines[0].notes;
    assert(notes.map((n) => n.pitches.join('+')).join(' ') === '60 62 64 60 62 64+67', `played through, the tie held: ${notes.map((n) => `${n.beat}:${n.pitches}/${n.length}`)}`);
    assert(notes.at(-1).length === 4 && notes.at(-1).beat === 12, 'the tied chord is one note of a bar');
    assert(notes[0].lyric === 'Glo-' && notes[1].lyric === 'ry,' && notes[2].br, 'verse 1, our hyphen, a breath after the mark');
    assert(notes[3].lyric === 'Sing' && !notes[4].lyric && notes[5].lyric === 'A-', `the second time through sings verse 2 (a bar with verse 2 words sings only them), and verse 1's words in a bar printed once: ${notes.map((n) => n.lyric)}`);
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

const editorScore = async (name) => readMusicXML(await (await fetch(`/tests/fixtures/editors/${name}.musicxml`)).text());
const notice = (s, kind) => s.notices.find((n) => n.kind === kind);
/** Every note on six decimals at most, and each one that touches the next meets it exactly. */
function sixPlaces(notes, label) {
    for (const [i, n] of notes.entries()) {
        for (const v of [n.beat, n.length]) assert(Math.abs(v * 1e6 - Math.round(v * 1e6)) < 1e-6, `${label} note ${i}: ${v} has more than six decimals`);
        const next = notes[i + 1];
        if (next && Math.abs(n.beat + n.length - next.beat) < 0.001) assert(Math.round((n.beat + n.length) * 1e6) === Math.round(next.beat * 1e6), `${label} note ${i} ends at ${n.beat + n.length}, the next starts at ${next.beat}`);
    }
}

test('sheet import: MuseScore\'s export: a pickup bar, D.C. al Fine followed, rehearsal marks as sections, an elision, a slurred pair held, a breath with no rest', async () => {
    const s = await editorScore('musescore-simple-gifts');
    const notes = s.lines[0].notes;
    assert(s.pickupBeats === 1 && s.tempo === 100 && s.key === 'F', `${s.pickupBeats} ${s.tempo} ${s.key}`);
    const jump = notice(s, 'jumpFollowed');
    assert(jump?.jump === 'dc' && jump.fine && !jump.coda && notes.at(-1).beat + notes.at(-1).length === 25, `back to the start, on to Fine: ${JSON.stringify(jump)}, ends at ${notes.at(-1).beat + notes.at(-1).length}`);
    assert(notes.filter((n) => n.lyric === "'Tis").length === 3 && notes.some((n) => n.lyric === 'to‿be'), 'the first strain twice, the elision kept as one word');
    const we = notes.find((n) => n.lyric === 'we');
    assert(we.length === 1.5 && notes[notes.indexOf(we) + 1].br, `the slurred pair on one pitch is one held note, half a beat shorter for the breath: ${we.length}`);
    assert(notes.find((n) => n.lyric === 'free,').length === 1.5, 'a breath mark with no rest takes half a beat from the note before');
    assert(s.sections.map((x) => `${x.name}@${x.beat}`).join() === '@0,A@1,B@9,A@17', `marks, met twice after the D.C.: ${JSON.stringify(s.sections)}`);
    const t = toTemplate(s, { picks: [{ id: s.lines[0].id, as: 'lead' }] });
    validateTemplate(t);
    assert(t.sections.map((x) => `${x.name}:${x.startBeat}-${x.endBeat}`).join() === 'A:0-9,B:9-16,A:16-25', `each section starts with its upbeat and the last ends with the notes: ${t.sections.map((x) => `${x.name}:${x.startBeat}-${x.endBeat}`)}`);
    assert(t.sections.every((x) => x.phrases.every(([a, b]) => a >= x.startBeat && b <= x.endBeat)), 'every phrase inside its section');
    sixPlaces(t.parts[0].guide.notes, 'MuseScore');
});

test('sheet import: Sibelius\'s export: 256 divisions, a metronome mark, D.S. al Coda followed (its To Coda drawn as a coda sign), two parts in step', async () => {
    const s = await editorScore('sibelius-ode-to-joy');
    const jump = notice(s, 'jumpFollowed');
    assert(jump?.jump === 'ds' && jump.coda && !jump.fine && s.tempo === 120, `back to the sign, on to the coda: ${JSON.stringify(jump)} at ${s.tempo}`);
    const [sop, alto] = s.lines;
    assert(sop.notes.length === 28 && alto.notes.length === 28 && sop.notes.at(-1).beat === 28 && alto.notes.at(-1).beat === 28, `bars 1 to 4, 2 and 3 again, the coda: ${sop.notes.length} ${alto.notes.length}`);
    assert(sop.notes[15].lyric === 'Lord' && sop.notes[23].lyric === 'love', 'the sign\'s bar sung again, then the coda');
    assert(notice(s, 'dynamics')?.bars === '1' && s.sections.length === 0, 'the dynamic named with its bar');
    const t = toTemplate(s, { picks: [{ id: sop.id, as: 'soprano' }, { id: alto.id, as: 'alto' }], wordsFrom: sop.id });
    validateTemplate(t);
    assert(t.sections.length === 1 && t.sections[0].endBeat === 32 && t.parts[1].guide.notes[0].lyric === 'Joy-', 'one section to the end, the alto singing the soprano\'s words');
});

test('sheet import: Finale\'s export: three verses stacked under a hymn sung one after another, each a section, with the report naming what isn\'t sung', async () => {
    const s = await editorScore('finale-amazing-grace');
    const notes = s.lines[0].notes;
    assert(s.sections.map((x) => `${x.name}@${x.beat}`).join() === 'Verse 1@0,Verse 2@21,Verse 3@42' && notice(s, 'versesWritten')?.count === 3, `the verses: ${JSON.stringify(s.sections)}`);
    assert(notes.length === 48 && notes[16].lyric === "'Twas" && notes[16].br && notes[32].lyric === 'Through', 'each verse its own pass, its own words');
    assert(notes[15].length === 1.5, `the verse before gives the next one's upbeat its breath: ${notes[15].length}`);
    assert(notes.some((n) => n.lyric === 'fear,') && !notes.some((n) => /—|_/.test(n.lyric ?? '')), 'an em dash read as a comma, an extender never a word');
    assert(notes.filter((n) => !n.lyric).length === 6, 'the melisma\'s second notes carry no words');
    assert(notice(s, 'fermatas')?.bars === '7' && notice(s, 'tempoWords')?.bars === '6' && notice(s, 'dynamics')?.bars === '7', `fermata, rit. and p by bar: ${JSON.stringify(s.notices)}`);
    const t = toTemplate(s, { picks: [{ id: s.lines[0].id, as: 'soprano' }] });
    validateTemplate(t);
    assert(t.sections.map((x) => x.name).join() === 'Verse 1,Verse 2,Verse 3' && t.sections[2].endBeat === 63 && t.sections.every((x) => x.phrases.length === 2), `three sections of two lines each: ${JSON.stringify(t.sections)}`);
});

test('sheet import: Dorico\'s export: 6/8 rounded to six places that still add up, two voices on a staff, divisi, Solo and Tutti, and the director picks the tune', async () => {
    const s = await editorScore('dorico-greensleeves');
    assert(s.timeSignature === '6/8' && s.tempo === 92 && s.key === 'Am' && s.pickupBeats === 0.333333, `${s.timeSignature} ${s.tempo} ${s.key} ${s.pickupBeats}`);
    assert(s.lines.map((l) => l.id).join() === 'P1:1,P1:2,P2:1', 'the women\'s two voices and the men');
    for (const l of s.lines) sixPlaces(l.notes, l.id);
    assert(s.lines[0].notes.at(-1).lyric === 'cour-' && s.lines[0].notes.at(-1).length === 2, 'the slurred pair on one pitch is one held note');
    const divisi = s.notices.find((n) => n.kind === 'divisi');
    assert(divisi?.line === 'P2:1' && divisi.bars === '3, 4', `the men split in bars 3 and 4: ${JSON.stringify(divisi)}`);
    assert(notice(s, 'soloTutti')?.bars === '0, 3' && notice(s, 'accents')?.bars === '2' && notice(s, 'fermatas')?.bars === '4' && notice(s, 'tempoWords')?.bars === '4', `the report: ${JSON.stringify(s.notices)}`);
    const picks = [{ id: 'P1:1', as: 'soprano' }, { id: 'P1:2', as: 'alto' }, { id: 'P2:1', as: 'bass', take: 'bottom' }];
    const usual = toTemplate(s, { picks });
    const chosen = toTemplate(s, { picks, melody: 'P2:1' });
    validateTemplate(chosen);
    assert(usual.parts.find((p) => p.melody).key === 'soprano' && chosen.parts.filter((p) => p.melody).map((p) => p.key).join() === 'bass', 'the soprano is the tune unless the director picks another line');
    for (const p of chosen.parts) sixPlaces(p.guide.notes, p.key);
    assert(chosen.parts.at(-1).guide.notes.at(-1).pitch === 45, 'the bottom of the men\'s divisi');
});

test('sheet import: after a D.C. repeats aren\'t taken again and only the last ending is sung; a metre change is refused; bars are listed short', () => {
    const fwd = '<barline location="left"><repeat direction="forward"/></barline>';
    const end1 = '<barline location="left"><ending number="1" type="start"/></barline>';
    const end1stop = '<barline location="right"><ending number="1" type="stop"/><repeat direction="backward"/></barline>';
    const end2 = '<barline location="left"><ending number="2" type="start"/></barline>';
    const end2stop = '<barline location="right"><ending number="2" type="stop"/></barline>';
    const xml = score([
        fwd + note('C', 4, 8),
        end1 + note('D', 4, 8) + end1stop,
        end2 + note('E', 4, 8) + '<direction><direction-type><words>Fine</words></direction-type></direction>' + end2stop,
        note('F', 4, 8) + '<direction><direction-type><words>D.C. al Fine</words></direction-type></direction>',
    ]);
    const measures = [...new DOMParser().parseFromString(xml, 'application/xml').querySelectorAll('measure')];
    const { order, passes, repeats, jump } = playOrder(measures);
    assert(order.join() === '0,1,0,2,3,0,2' && passes.join() === '1,1,2,2,1,1,1' && repeats === 1, `the order: ${order} passes ${passes}`);
    assert(jump.kind === 'dc' && jump.fine && !jump.coda, JSON.stringify(jump));
    const changes = score([note('C', 4, 8), `<attributes><time><beats>3</beats><beat-type>4</beat-type></time></attributes>${note('D', 4, 6)}`]);
    assert(readMusicXML(changes).unsupported === 'metreChange' && readMusicXML(changes).metre === '4/4', 'a metre change mid-song is said, not sung wrong');
    assert(barList(['1', '2', '3', '5', '7', '8', '8']) === '1–3, 5, 7, 8' && barList([]) === '', barList(['1', '2', '3', '5', '7', '8']));
});
