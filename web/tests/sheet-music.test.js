// Sheet music export (js/musicxml.js, js/midi.js; docs/sheet-music-plan.md, slice 1): every free song
// and a few made-up ones written out, then read back: the bars add up in every part, every note
// comes back at its beat and pitch with its words, triplets and 6/8 included, and the MIDI file's
// tracks hold the same notes.
import { asSung, barsOf, DIVISIONS, metreOf, rightsOf, toMusicXML, valuesFor } from '/js/musicxml.js';
import { TICKS, toMidi } from '/js/midi.js';
import { assert, test } from './harness.js';

const STEP = { C: 0, D: 2, E: 4, F: 5, G: 7, A: 9, B: 11 };

async function freeSongs() {
    const index = await (await fetch('/templates/index.json')).json();
    const ids = (index.templates ?? index).map((e) => (typeof e === 'string' ? e : e.id));
    return Promise.all(ids.map(async (id) => (await fetch(`/templates/${id}.json`)).json()));
}

/** A made-up song: a pickup, a triplet, a tie over the bar, two parts, words and a breath. */
function madeUp(timeSignature = '4/4', pickupBeats = 1) {
    const melody = [
        { beat: 0, length: 1, pitch: 67, lyric: 'Now', br: true },
        { beat: 1, length: 2 / 3, pitch: 69, lyric: 'sing-' }, { beat: 5 / 3, length: 2 / 3, pitch: 71, lyric: 'ing' }, { beat: 7 / 3, length: 2 / 3, pitch: 72, lyric: 'we' },
        { beat: 3, length: 3, pitch: 74, lyric: 'go,' },
        { beat: 6, length: 1, pitch: 72, lyric: 'and', br: true }, { beat: 7, length: 2, pitch: 71, lyric: 'rest.' },
    ];
    const low = melody.map((n) => ({ ...n, pitch: n.pitch - 4 }));
    return {
        id: 'made-up', title: 'Made up & "quoted"', tempo: 90, key: 'Bb', timeSignature, pickupBeats,
        credits: { words: 'Ada', tune: 'Ada' },
        chords: [{ beat: 0, root: 10, quality: 'maj', bass: null }, { beat: 3, root: 3, quality: 'dom7', bass: 7 }],
        parts: [{ key: 'soprano', name: 'Soprano', melody: true, guide: { type: 'notes', notes: melody } }, { key: 'alto', name: 'Alto', guide: { type: 'notes', notes: low } }],
    };
}

/** A MusicXML document's parts read back: each note start (beat), pitch and word, ties joined. */
function readBack(xml, template) {
    const doc = new DOMParser().parseFromString(xml, 'application/xml');
    assert(!doc.querySelector('parsererror'), 'the MusicXML parses');
    const scale = metreOf(template.timeSignature).quarters * DIVISIONS;
    return [...doc.querySelectorAll('part')].map((part) => {
        let at = 0;
        const notes = [];
        for (const measure of part.querySelectorAll('measure')) {
            for (const el of measure.children) {
                if (el.tagName !== 'note') continue;
                const duration = Number(el.querySelector('duration').textContent);
                const pitch = el.querySelector('pitch');
                if (pitch && !el.querySelector('tie[type="stop"]')) {
                    const midi = 12 * (Number(pitch.querySelector('octave').textContent) + 1) + STEP[pitch.querySelector('step').textContent] + Number(pitch.querySelector('alter')?.textContent ?? 0);
                    notes.push({ beat: at / scale, pitch: midi, lyric: el.querySelector('lyric text')?.textContent ?? null, syllabic: el.querySelector('lyric syllabic')?.textContent ?? null });
                }
                at += duration;
            }
        }
        return { doc, notes, measures: [...part.querySelectorAll('measure')] };
    });
}

function checkSong(template, label) {
    const xml = toMusicXML(template, { date: new Date('2026-10-30') });
    const bars = barsOf(template);
    const written = template.parts.filter((p) => p.guide?.notes?.length);
    const parts = readBack(xml, template);
    assert(parts.length === written.length, `${label}: ${parts.length} parts written of ${written.length}`);
    parts.forEach((part, i) => {
        assert(part.measures.length === bars.length, `${label}, ${written[i].key}: ${part.measures.length} bars, want ${bars.length}`);
        part.measures.forEach((m, b) => {
            const sum = [...m.querySelectorAll('note > duration')].reduce((s, d) => s + Number(d.textContent), 0);
            assert(sum === bars[b].length, `${label}, ${written[i].key}, bar ${b}: ${sum} divisions, want ${bars[b].length}`);
        });
        const want = written[i].guide.notes;
        assert(part.notes.length === want.length, `${label}, ${written[i].key}: ${part.notes.length} notes back of ${want.length}`);
        want.forEach((n, k) => {
            const got = part.notes[k];
            assert(Math.abs(got.beat - n.beat) < 0.02 && got.pitch === n.pitch, `${label}, ${written[i].key}, note ${k}: ${got.beat}/${got.pitch}, want ${n.beat}/${n.pitch}`);
            if (n.lyric) assert(got.lyric === n.lyric.replace(/-$/, ''), `${label}, note ${k}'s word: ${got.lyric}`);
        });
    });
    return { xml, parts };
}

test('sheet music: every free song as MusicXML reads back note for note, its bars full in every part', async () => {
    for (const song of await freeSongs()) checkSong(song, song.id);
});

test('sheet music: a pickup, triplets, a tie over the bar, words, breaths and chords, in 4/4 and 6/8', () => {
    const { xml, parts } = checkSong(madeUp(), '4/4');
    const first = parts[0].measures[0];
    assert(first.getAttribute('implicit') === 'yes' && first.getAttribute('number') === '0', 'the pickup bar is implicit, numbered 0');
    assert(parts[0].doc.querySelectorAll('time-modification').length >= 3, 'the triplets carry 3:2');
    assert(parts[0].doc.querySelectorAll('tie[type="start"]').length >= 1, 'a note over the bar is tied');
    assert(parts[0].notes[1].syllabic === 'begin' && parts[0].notes[2].syllabic === 'end', 'sing-ing: begin, end');
    assert(parts[0].doc.querySelectorAll('breath-mark').length === 2, 'a breath mark before the second line in each part (the first line has no note before it)');
    assert(parts[0].doc.querySelectorAll('part:first-of-type harmony').length === 2 && parts[0].doc.querySelectorAll('part:nth-of-type(2) harmony').length === 0, 'the chords over the melody only');
    assert(xml.includes('Made up &amp; &quot;quoted&quot;'), 'the title escaped');
    const six = madeUp('6/8', 0);
    const back = checkSong(six, '6/8');
    assert(back.parts[0].doc.querySelector('time beats').textContent === '6' && back.parts[0].doc.querySelector('beat-unit-dot'), '6/8 with a dotted-quarter beat');
});

test('sheet music: a library song carries CC BY-SA 4.0 and its public-domain credits; an own song only its own credits', () => {
    const library = { ...madeUp(), credits: { tune: 'traditional', words: 'traditional', arrangement: 'Stackapella (draft)' }, licence: { tune: 'public domain: traditional', words: 'public domain: traditional' } };
    const rights = rightsOf(library);
    assert(rights.includes('CC BY-SA 4.0') && rights.includes('Stackapella (https://stackapella.com)') && rights.includes('Tune: traditional (public domain)'), rights);
    assert(toMusicXML(library).includes('<rights>'), 'the rights field');
    const own = madeUp();
    assert(!rightsOf(own).includes('Stackapella'), `an own song: ${rightsOf(own)}`);
    // One part alone: only it.
    const alto = readBack(toMusicXML(madeUp(), { partKeys: ['alto'] }), madeUp());
    assert(alto.length === 1 && alto[0].notes[0].pitch === 63, 'the alto alone');
    // Every length is written with note values that add back to it.
    for (let d = 3; d <= 96 * 2; d++) assert(valuesFor(d).reduce((s, v) => s + v.d, 0) === d, `values for ${d}`);
});

test('sheet music: as sung in the studio, a tone up and faster: the key, notes and chords move, the song itself unchanged', () => {
    const song = madeUp();
    const up = asSung(song, { transpose: 2, tempo: 120 });
    assert(up.key === 'C' && up.tempo === 120 && up.parts[0].guide.notes[0].pitch === 69 && up.chords[0].root === 0 && up.chords[1].bass === 9, JSON.stringify([up.key, up.chords]));
    assert(song.key === 'Bb' && song.parts[0].guide.notes[0].pitch === 67 && song.chords[0].root === 10, 'the song untouched');
    assert(new DOMParser().parseFromString(toMusicXML(up), 'application/xml').querySelector('key fifths').textContent === '0', 'written in C');
    assert(asSung(song, { transpose: -2 }).key === 'Ab' && asSung(song, {}).tempo === 90, 'down a tone; the song\'s own tempo by default');
});

/** A MIDI file's tracks: each one's notes on (tick, pitch) and lyric texts. */
function readMidi(bytes) {
    const text = (from, n) => String.fromCharCode(...bytes.slice(from, from + n));
    assert(text(0, 4) === 'MThd', 'starts MThd');
    const count = (bytes[10] << 8) | bytes[11];
    const division = (bytes[12] << 8) | bytes[13];
    let at = 14;
    const tracks = [];
    for (let i = 0; i < count; i++) {
        assert(text(at, 4) === 'MTrk', `track ${i} is MTrk`);
        const length = (bytes[at + 4] << 24) | (bytes[at + 5] << 16) | (bytes[at + 6] << 8) | bytes[at + 7];
        let p = at + 8;
        const end = p + length;
        const vlq = () => {
            let v = 0;
            let b;
            do { b = bytes[p++]; v = (v << 7) | (b & 0x7f); } while (b & 0x80);
            return v;
        };
        let tick = 0;
        const track = { ons: [], lyrics: [], name: null };
        while (p < end) {
            tick += vlq();
            const status = bytes[p++];
            if (status === 0xff) {
                const type = bytes[p++];
                const n = vlq();
                const data = new TextDecoder().decode(bytes.slice(p, p + n));
                if (type === 0x05) track.lyrics.push(data);
                if (type === 0x03) track.name = data;
                p += n;
            } else if ((status & 0xf0) === 0x90) {
                track.ons.push({ tick, pitch: bytes[p] });
                p += 2;
            } else if ((status & 0xf0) === 0x80) p += 2;
            else if ((status & 0xf0) === 0xc0) p += 1;
            else throw new Error(`unexpected MIDI byte ${status}`);
        }
        assert(p === end, `track ${i} ends where it says`);
        tracks.push(track);
        at = end;
    }
    return { division, tracks };
}

test('sheet music: MIDI holds every part on its own track, notes at their beats with their words', async () => {
    for (const song of [...(await freeSongs()), madeUp()]) {
        const { division, tracks } = readMidi(toMidi(song));
        const written = song.parts.filter((p) => p.guide?.notes?.length);
        assert(division === TICKS && tracks.length === written.length + 1, `${song.id}: ${tracks.length} tracks`);
        const perBeat = metreOf(song.timeSignature).quarters * TICKS;
        written.forEach((part, i) => {
            const t = tracks[i + 1];
            assert(t.name === part.name, `${song.id}: track ${i + 1} named ${t.name}`);
            assert(t.ons.length === part.guide.notes.length, `${song.id}, ${part.key}: ${t.ons.length} notes of ${part.guide.notes.length}`);
            part.guide.notes.forEach((n, k) => assert(t.ons[k].pitch === n.pitch && Math.abs(t.ons[k].tick - n.beat * perBeat) <= 1, `${song.id}, ${part.key}, note ${k}`));
            assert(t.lyrics.length === part.guide.notes.filter((n) => n.lyric).length, `${song.id}, ${part.key}: the words`);
        });
    }
});
