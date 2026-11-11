// The reference pages' cards (js/public/reference-cards.js): notes spelled by their interval, an interval's sizes
// equal and pure from just.js, the staff writing a spelled note's flat, and a voice type's bare card.
import { intervalSizes, mountVoiceRangeCard, pureOf, spellAbove, spelledName, spellRoot } from '/js/public/reference-cards.js';
import { grandStaff } from '/js/public/staff.js';
import { assert, close, test } from './harness.js';

test('reference cards: notes spelled by their interval, a black root as a flat except F sharp', () => {
    const c = spellRoot(60);
    assert(spelledName(c) === 'C4' && spelledName(spellRoot(61)) === 'D♭4' && spelledName(spellRoot(66)) === 'F♯4' && spelledName(spellRoot(70)) === 'B♭4', 'roots');
    assert(spelledName(spellAbove(c, 3, 2)) === 'E♭4' && spelledName(spellAbove(c, 6, 3)) === 'F♯4' && spelledName(spellAbove(c, 10, 6)) === 'B♭4', 'a minor third, a tritone, a minor seventh');
    assert(spelledName(spellAbove(c, 9, 6)) === 'B\u{1D12B}4', 'a diminished seventh\'s B double flat');
    assert(spelledName(spellAbove(spellRoot(71), 1, 1)) === 'C5' && spelledName(spellAbove(spellRoot(64), 12, 7)) === 'E5', 'across the octave');
    assert(spelledName(spellAbove(spellRoot(66), 4, 2)) === 'A♯4', 'F sharp\'s major third');
});

test('reference cards: an interval\'s size equal and pure, the seventh 9:5 unless 7:4 is asked for', () => {
    const third = intervalSizes(4);
    close(third.pure, 386.31, 0.01, 'a pure major third');
    close(third.offset, -13.69, 0.01, '13.7 narrower');
    assert(pureOf(10).join(':') === '9:5' && pureOf(10, 'harmonicSeventh').join(':') === '7:4', 'the sevenths');
    assert(intervalSizes(12).pure === 1200 && intervalSizes(0).pure === 0, 'the octave and the unison');
});

test('reference cards: the staff writes a spelled note\'s own flat, and a double flat', () => {
    const svg = grandStaff([{ midi: 70, letter: 6, octave: 4, accidental: -1 }, { midi: 69, letter: 6, octave: 4, accidental: -2 }]);
    const signs = [...svg.querySelectorAll('text')].map((t) => t.textContent);
    assert(signs.includes('♭') && signs.includes('\u{1D12B}') && !signs.includes('♯'), `the signs: ${signs}`);
    const ys = [...svg.querySelectorAll('ellipse')].map((e) => e.getAttribute('cy'));
    assert(ys[0] === ys[1], 'B flat and B double flat on the same line');
});

test('reference cards: a bare voice range card is the keyboard and its three plays, no staff, facts or link', () => {
    const words = { lowest: 'Lowest', highest: 'Highest', scale: 'The notes between', range: ':low to :high', rangeLabel: 'Usual range', partLabel: 'Choir part', find: 'Find your own range' };
    const full = document.createElement('div');
    const bare = document.createElement('div');
    mountVoiceRangeCard(full, { type: 'alto', words });
    const card = mountVoiceRangeCard(bare, { type: 'alto', words, bare: true });
    assert(full.querySelector('[data-ui=ref-staff]') && full.querySelector('[data-ui=ref-facts]') && full.querySelector('[data-ui=ref-find]'), 'the full card');
    assert(!bare.querySelector('[data-ui=ref-staff]') && !bare.querySelector('[data-ui=ref-facts]') && !bare.querySelector('[data-ui=ref-find]'), 'the bare card has none of them');
    assert(bare.querySelectorAll('[data-plays] button').length === 3 && bare.querySelector('[data-marked]'), 'its plays and its marked keys');
    assert(card.range.low === 53 && card.range.high === 74, 'the alto\'s F3 to D5');
});
