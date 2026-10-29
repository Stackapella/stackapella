// Note names your way (js/notes.js).
import { chordName, noteLabel, parseKey } from '/js/notes.js';
import { assert, test } from './harness.js';

test('notes: letters, solfège and numbers, in the song\'s key', () => {
    assert(noteLabel(62, 'letters', 'G') === 'D4', noteLabel(62, 'letters', 'G'));
    assert(noteLabel(67, 'solfege', 'G') === 'do' && noteLabel(62, 'solfege', 'G') === 'so' && noteLabel(66, 'solfege', 'G') === 'ti', 'G major');
    assert(noteLabel(67, 'numbers', 'G') === '1' && noteLabel(62, 'numbers', 'G') === '5' && noteLabel(66, 'numbers', 'G') === '7', 'numbers');
    assert(noteLabel(65, 'numbers', 'G') === '♭7', 'a note outside the scale');
    assert(noteLabel(62, 'off', 'G') === '', 'off');
});

test('notes: chords are named for the key they\'re sung in', () => {
    assert(chordName({ root: 7, quality: 'maj', bass: null }, 0, 'G') === 'G', 'G');
    assert(chordName({ root: 7, quality: 'maj', bass: 11 }, 0, 'G') === 'G/B', 'an inversion');
    assert(chordName({ root: 2, quality: 'dom7', bass: null }, 2, 'A') === 'E7', 'up a tone');
    assert(chordName({ root: 9, quality: 'min7', bass: null }, 1, 'Ab') === 'B♭m7', 'flats in a flat key');
});

test('notes: a minor key is la-based in solfège, and its own tonic is 1', () => {
    assert(noteLabel(57, 'solfege', 'Am') === 'la' && noteLabel(60, 'solfege', 'Am') === 'do', 'A minor');
    assert(noteLabel(57, 'numbers', 'Am') === '1' && noteLabel(60, 'numbers', 'Am') === '♭3', 'numbers in A minor');
    assert(parseKey('F#m').tonic === 6 && parseKey('F#m').minor && parseKey('Bb').tonic === 10 && !parseKey('Bb').minor, 'keys parse');
});
