// The chord transposer (js/public/tools/chord-transposer.js) on notes.js's chord symbols: a whole sheet
// moved with its lyrics kept and its chords over their syllables, inline [C] chords, any suffix kept,
// the new key spelled as it's written, and the screen.
import { chordsOf, flatsFor, guessKey, mountChordTransposer, readSheet, transposeSheet, transposeSymbol, WORDS } from '/js/public/tools/chord-transposer.js';
import { chordQuality, flatKey, parseChordSymbol } from '/js/notes.js';
import { capoFor } from '/js/theory.js';
import { assert, test } from './harness.js';

test('chord symbols: the root, the suffix as written and the slash bass; words that start with A to G are not chords', () => {
    assert(JSON.stringify(parseChordSymbol('F#m7/C#')) === '{"root":6,"suffix":"m7","bass":1}', JSON.stringify(parseChordSymbol('F#m7/C#')));
    assert(parseChordSymbol('Bbmaj9').root === 10 && parseChordSymbol('Bbmaj9').suffix === 'maj9' && parseChordSymbol('C7#9').suffix === '7#9', 'suffixes kept');
    assert(parseChordSymbol('D♭add9').root === 1 && parseChordSymbol('G/B').bass === 11 && parseChordSymbol('Esus4').suffix === 'sus4', 'unicode, slash, sus');
    assert(parseChordSymbol('Be') === null && parseChordSymbol('Dear') === null && parseChordSymbol('A') !== null && parseChordSymbol('Am') !== null, 'words are not chords');
    assert(chordQuality('') === 'maj' && chordQuality('m') === 'min' && chordQuality('7') === 'dom7' && chordQuality('maj7') === 'maj7' && chordQuality('m7b5') === 'hdim7'
        && chordQuality('ø') === 'hdim7' && chordQuality('sus') === 'sus4' && chordQuality('9') === 'dom7' && chordQuality('m9') === 'min7' && chordQuality('wat') === null, 'qualities');
    assert(flatKey(5) && flatKey(2, true) && !flatKey(7) && !flatKey(9, true), 'flat keys: F major, D minor; not G, not A minor');
    assert(capoFor(3).capo === 1, 'the capo, now in theory.js');
    assert(!flatsFor(6) && flatsFor(10) && !flatsFor(6, true), 'F# major with sharps, as charts write it; B flat with flats');
});

test('chord transposer: a sheet moves, lyrics kept, chords over their syllables, inline and suffixes too', () => {
    const sheet = ['[Verse 1]', 'G        C     G', 'Amazing grace, how sweet', 'D7/F#   Em7', 'That saved a wretch', '', '[G]Was blind [C]but now'].join('\n');
    const kinds = readSheet(sheet).map((l) => l.kind).join();
    assert(kinds === 'text,chords,text,chords,text,text,inline', kinds);
    assert(chordsOf(sheet).length === 7 && JSON.stringify(guessKey(sheet)) === '{"tonic":7,"minor":false}', 'seven chords, in G');
    const up = transposeSheet(sheet, 2).split('\n');
    assert(up[0] === '[Verse 1]' && up[2] === 'Amazing grace, how sweet' && up[4] === 'That saved a wretch', 'the words untouched');
    assert(up[1] === 'A        D     A', `columns kept: ${up[1]}`);
    assert(up[3] === 'E7/G#   F#m7', `suffix and slash: ${up[3]}`);
    assert(up[6] === '[A]Was blind [D]but now', up[6]);
    // A longer name pushes the next along, with a space, never onto it.
    assert(transposeSheet('C D', 1) === 'C# D#' && transposeSheet('C D', 1, { flats: true }) === 'Db Eb', 'spelled as asked');
    assert(transposeSymbol('Am7b5/G', -2, true) === 'Gm7b5/F' && transposeSymbol('hello', 3) === 'hello', 'symbols');
    assert(transposeSheet('| C | Am | F G | x2', 5, { flats: true }) === '| F | Dm | Bb C | x2', 'bar lines and repeats kept');
    assert(readSheet('Am I blue').at(0).kind === 'text' && readSheet('A man went down').at(0).kind === 'text', 'lyrics that start with a chord letter stay lyrics');
});

test('chord transposer: the screen moves the chart, says the keys and the capo, and spells flat keys with flats', () => {
    try {
        localStorage.removeItem('stackapella.tools.chord-transposer');
    } catch (error) {
        console.warn('storage', error);
    }
    const words = { ...Object.fromEntries(WORDS.map((k) => [k, `[${k}]`])), chordsFound: ':n chords', guitar: 'capo :n, :shape shapes', guitarOpen: 'no capo, :key', shiftUp: ':n up', shiftDown: ':n down' };
    const el = document.createElement('div');
    const slot = document.createElement('div');
    document.body.append(slot, el);
    const t = mountChordTransposer(el, { words, keySlot: slot });
    const card = el.querySelector('[data-ui=tool]');
    const $ = (s) => card.querySelector(s);
    assert($('[data-ui=tool-primary]').disabled, 'nothing to copy yet');
    t.set('G C D\nAmazing grace');
    assert($('[data-ui=tool-status]').textContent.includes('3 chords') && $('[data-keys]').textContent === 'G', $('[data-keys]').textContent);
    slot.querySelector('[data-step="1"]').click();
    slot.querySelector('[data-step="1"]').click();
    slot.querySelector('[data-step="1"]').click();
    assert(card.dataset.view === 'output' && $('[data-output]').textContent === 'Bb Eb F\nAmazing grace', `B flat, with flats: ${$('[data-output]').textContent}`);
    assert($('[data-keys]').textContent === 'G → Bb' && $('[data-shift]').textContent === '3 up' && $('[data-capo]').textContent === 'capo 1, A shapes', `${$('[data-keys]').textContent} ${$('[data-capo]').textContent}`);
    $('[data-spelling=sharps]').click();
    assert($('[data-output]').textContent.startsWith('A# D# F'), 'sharps when asked');
    const to = $('[data-to-key]');
    to.value = '4';
    to.dispatchEvent(new Event('change'));
    assert(card.dataset.shift === '-3', `to E: the nearer way, down 3 (${card.dataset.shift})`);
    t.stop();
    el.remove();
    slot.remove();
});
