// A library song's sheet music to take away (js/sheet-files.js): what a song page's link asks for, the part a "parts"
// link opens on, and the files, which are the studio's Export's own.
import { toMidi } from '/js/midi.js';
import { toMusicXML } from '/js/musicxml.js';
import { SHEET_GETS, sheetAsk, sheetFile, sheetPart, writtenParts } from '/js/sheet-files.js';
import { assert, test } from './harness.js';

const song = async () => (await fetch('/tests/fixtures/export-pin/amazing-grace.json')).json();

test('sheet files: a link asks for one of four things, and anything else for nothing', () => {
    assert(SHEET_GETS.join() === 'pdf,parts,musicxml,midi', 'the four');
    assert(JSON.stringify(sheetAsk('?get=pdf&ref=song-page')) === '{"get":"pdf","part":null}', 'the full score');
    assert(JSON.stringify(sheetAsk('?get=parts&part=alto')) === '{"get":"parts","part":"alto"}', 'a part');
    assert(sheetAsk('?get=exe').get === null && sheetAsk('').get === null, 'nothing else');
    assert(sheetAsk('?get=parts&part=<b>').part === null, 'a part is a plain key');
});

test('sheet files: "parts" opens on the part asked for, else the first; every other link on the full score', async () => {
    const template = await song();
    assert(writtenParts(template).map((p) => p.key).join() === 'soprano,alto,tenor,bass', 'score order');
    assert(sheetPart(template, { get: 'parts', part: 'tenor' }) === 'tenor', 'the tenor');
    assert(sheetPart(template, { get: 'parts', part: 'kazoo' }) === 'soprano', 'a part the song lacks: the first');
    assert(sheetPart(template, { get: 'parts', part: null }) === 'soprano', 'none named: the first');
    assert(sheetPart(template, { get: 'pdf', part: 'tenor' }) === null && sheetPart(template, { get: 'midi', part: null }) === null, 'the full score');
});

test("sheet files: the MusicXML and the MIDI are the Export's own, named after the song", async () => {
    const template = await song();
    const xml = sheetFile(template, 'musicxml');
    assert(xml.name === 'Amazing Grace.musicxml' && xml.type === 'application/vnd.recordare.musicxml+xml', xml.name);
    assert(xml.data === toMusicXML(template), 'the same MusicXML');
    const midi = sheetFile(template, 'midi', 'Sublime: gracia / 1');
    assert(midi.name === 'Sublime gracia 1.mid' && midi.type === 'audio/midi', midi.name);
    assert(midi.data.join() === toMidi(template).join(), 'the same MIDI');
    let threw = false;
    try {
        sheetFile(template, 'pdf');
    } catch {
        threw = true;
    }
    assert(threw, 'a PDF is the print, not a file we write');
});
