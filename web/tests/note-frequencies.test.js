// The note frequency chart (js/public/tools/note-frequencies.js): every note C0 to B8 at any A4, the
// distance between two notes, and the screen's table, hero, A4 chip and Between two.
import { between, hzText, mountNoteFrequencies, noteRows, readQuery, WORDS } from '/js/public/tools/note-frequencies.js';
import { parsePitch } from '/js/notes.js';
import { assert, test } from './harness.js';

test('note frequencies: C0 to B8, A4 at 440 or any reference, middle C as C4', () => {
    const rows = noteRows(440);
    assert(rows.length === 108 && `${rows[0].name}${rows[0].octave}` === 'C0' && `${rows.at(-1).name}${rows.at(-1).octave}` === 'B8', 'C0 to B8');
    const at = (midi, list = rows) => list.find((r) => r.midi === midi);
    assert(hzText(at(69).hz) === '440.00' && hzText(at(60).hz) === '261.63' && hzText(at(12).hz) === '16.35' && hzText(at(119).hz) === '7902.13', 'the standard values');
    assert(hzText(at(69, noteRows(432)).hz) === '432.00' && hzText(at(60, noteRows(432)).hz) === '256.87', 'A4 = 432');
    assert(at(70, noteRows(440, { flats: true })).name === 'B♭' && at(71, noteRows(440, { naming: 'german' })).name === 'H', 'names');
    assert(parsePitch('F#3') === 54 && parsePitch('bb2') === 46 && parsePitch('B♭2') === 46 && parsePitch(' c 4 ') === 60 && parsePitch('b3') === 59 && parsePitch('H3') === null && parsePitch('440') === null, 'typed notes');
    assert(readQuery('C4').midi === 60 && readQuery('C3', 440, { c3: true }).midi === 60, 'middle C, or C3 by that naming');
    const q = readQuery('261.6 Hz');
    assert(q.midi === 60 && q.hz === 261.6 && q.cents === -0.2, JSON.stringify(q));
    assert(readQuery('432', 432).midi === 69 && readQuery('5 Hz') === null && readQuery('hello') === null, 'a frequency, at any A4');
    const d = between(69, 76);
    assert(d.semitones === 7 && d.cents === 700 && between(72, 60).cents === -1200, 'between');
});

test('note frequencies: the chart picks, plays, measures two notes and shows a changed A4', () => {
    try {
        localStorage.removeItem('stackapella.tools.note-frequency-chart');
    } catch (error) {
        console.warn('storage', error);
    }
    const words = { ...Object.fromEntries(WORDS.map((k) => [k, `[${k}]`])), hz: ':hz Hz', midi: 'MIDI :n', cents: ':n cents', semitones: ':n semitones', referenceChip: 'A4 = :hz', play: 'Play :note' };
    const el = document.createElement('div');
    document.body.append(el);
    const chart = mountNoteFrequencies(el, { words, search: '' });
    const card = el.querySelector('[data-ui=tool]');
    const $ = (s) => card.querySelector(s);
    assert($('[data-note]').textContent === 'A4' && $('[data-hz]').textContent === '440.00' && $('[data-sub]').textContent.startsWith('MIDI 69 · [tuningA]'), `${$('[data-note]').textContent} ${$('[data-hz]').textContent} ${$('[data-sub]').textContent}`);
    assert(card.querySelectorAll('[data-rows] tr').length === 108 && card.querySelectorAll('[data-rows] tr:not([hidden])').length === 49, 'every note, C2 to C6 shown');
    assert($('[data-rows] [data-midi="60"] button').textContent.includes('[middleC]') && $('[data-rows] [data-midi="40"] button').textContent.includes('[lowest]'), 'middle C and the bass\'s lowest named');
    assert($('[data-rows] [data-midi="69"]').classList.contains('bg-[var(--primary)]'), 'A4 marked');
    $('[data-pick="60"]').click();
    assert($('[data-note]').textContent === 'C4' && $('[data-hz]').textContent === '261.63', 'middle C');
    assert($('[data-ui=tool-primary]').textContent.includes('[stop]'), 'a tap plays it: Stop');
    $('[data-ui=tool-primary]').click();
    assert($('[data-ui=tool-primary]').textContent.includes('Play C4'), $('[data-ui=tool-primary]').textContent);
    chart.stop();
    $('[data-compare]').click();
    $('[data-pick="67"]').click();
    assert($('[data-note]').textContent === 'C4 → G4' && $('[data-hz]').textContent === '+700' && $('[data-sub]').textContent.startsWith('7 semitones'), $('[data-sub]').textContent);
    chart.stop();
    $('[data-compare]').click();
    assert(chart.find('261.6') && $('[data-hz]').textContent === '261.6' && $('[data-note]').textContent === 'C4', 'a frequency found');
    assert(chart.find('C8') && card.querySelectorAll('[data-rows] tr:not([hidden])').length === 108, 'a note outside C2 to C6 shows them all');
    assert(!chart.find('zz') && $('[data-search]').getAttribute('aria-invalid') === 'true', 'not understood');
    $('[data-preset="432"]').click();
    assert(!$('[data-chip]').hidden && $('[data-chip]').textContent === 'A4 = 432' && $('[data-rows] [data-midi="69"] td:nth-child(2)').textContent === '432.00', 'A4 = 432');
    $('[data-preset="440"]').click();
    assert($('[data-chip]').hidden, 'no chip at 440');
    chart.stop();
    const linked = mountNoteFrequencies(el, { words, search: '?a4=442' });
    assert(el.querySelector('[data-hz]').textContent === '442.00', 'a link sets A4 for the visit');
    linked.stop();
    chart.stop();
    el.remove();
});
