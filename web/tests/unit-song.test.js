// A unit's song at its end (retention row 8; js/unit-song.js): when it's offered, which part, "oo" for the units that
// sing on it, and its counts once a device a place.
import { countUnitSong, onSyllable, songPartKey, unitSongOffer } from '/js/unit-song.js';
import { assert, test } from './harness.js';

const song = { template: 'michael-row', part: 'melody', title: { en: 'Hear it in a song' }, text: { en: 'Sing the tune.' } };
const course = { units: [{ id: 'u1' }, { id: 'u2' }, { id: 'u3' }, { id: 'u4' }, { id: 'u5' }] };
const unit = (id, extra = {}) => ({ id, song, lessons: [{ id: `${id}-a` }, { id: `${id}-challenge`, challenge: true }], ...extra });
const at = (u, lesson = u.lessons.at(-1)) => ({ unit: u, lesson });

test('unit song: offered at a unit\'s challenge sung with stars, units 1 to 4 only', () => {
    const u1 = unit('u1');
    const offer = unitSongOffer({ entry: at(u1), done: { stars: 2 }, upNext: null, progress: {}, course });
    assert(offer?.place === 'u1' && offer.template === 'michael-row', JSON.stringify(offer));
    assert(unitSongOffer({ entry: at(unit('u4')), done: { stars: 1 }, upNext: null, progress: {}, course })?.place === 'u4', 'unit 4');
    assert(!unitSongOffer({ entry: at(u1), done: { stars: 0 }, upNext: null, progress: {}, course }), 'no stars: no song');
    assert(unitSongOffer({ entry: at(u1), done: { stars: 0 }, upNext: null, progress: { lessons: { 'u1-challenge': { stars: 3 } } }, course }), 'stars from before count');
    assert(!unitSongOffer({ entry: at(u1, u1.lessons[0]), done: { stars: 3 }, upNext: null, progress: {}, course }), 'not the challenge');
    assert(unitSongOffer({ entry: at(u1), done: { stars: 3 }, upNext: { lesson: {} }, progress: {}, course }), 'the next unit\'s lesson up next: still offered, beside it');
    assert(!unitSongOffer({ entry: { ...at(u1), boss: true }, done: { stars: 3 }, upNext: null, progress: {}, course }), 'never a boss');
    assert(!unitSongOffer({ entry: at(unit('u5')), done: { stars: 3 }, upNext: null, progress: {}, course }), 'unit 5 waits for its review');
    assert(!unitSongOffer({ entry: at(unit('u2', { song: undefined })), done: { stars: 3 }, upNext: null, progress: {}, course }), 'a unit with no song');
});

test('unit song: "melody" is the template\'s tune, "oo" on the singer\'s part only', () => {
    const template = { parts: [{ key: 'soprano', guide: { notes: [{ beat: 0, lyric: 'A-' }, { beat: 1 }] } }, { key: 'alto', melody: true, guide: { notes: [{ beat: 0, lyric: 'Glo-' }, { beat: 1, lyric: 'ry' }, { beat: 2 }] } }] };
    assert(songPartKey(template, 'melody') === 'alto' && songPartKey(template, 'bass') === 'bass', 'the tune, or the part named');
    const oo = onSyllable(template, 'alto', 'oo');
    assert(oo.parts[1].guide.notes.map((n) => n.lyric ?? '_').join(' ') === 'oo oo _', 'every worded note on oo, a melisma still none');
    assert(oo.parts[0].guide.notes[0].lyric === 'A-' && template.parts[1].guide.notes[0].lyric === 'Glo-', 'the others keep their words, the song itself untouched');
});

test('unit song: each count once a device a place', () => {
    const KEY = 'stackapella.unitSong.counted';
    const before = localStorage.getItem(KEY);
    try {
        localStorage.removeItem(KEY);
        countUnitSong('shown', 'u1');
        countUnitSong('shown', 'u1');
        countUnitSong('tap', 'wall');
        countUnitSong('shown', 'u9');
        assert(JSON.stringify(JSON.parse(localStorage.getItem(KEY))) === JSON.stringify({ 'us_shown:u1': true, 'us_tap:wall': true }), localStorage.getItem(KEY));
    } finally {
        if (before === null) localStorage.removeItem(KEY);
        else localStorage.setItem(KEY, before);
    }
});
