// The song library's filters (js/library-filter.js): the categories /songs groups by, the part you sing, a title search,
// and the list by category (in season first) or A to Z once something narrows it.
import { filterSongs, groupCounts, groupOf, hasPartFor, librarySections, matches, titleIn } from '/js/library-filter.js';
import { assert, test } from './harness.js';

const satb = (low = 60) => [
    { key: 'soprano', name: 'Soprano', range: { low: low + 7, high: low + 19 } },
    { key: 'alto', name: 'Alto', range: { low, high: low + 12 } },
    { key: 'tenor', name: 'Tenor', range: { low: low - 5, high: low + 7 } },
    { key: 'bass', name: 'Bass', range: { low: low - 17, high: low - 5 } },
];
const SONGS = [
    { id: 'silent-night', title: 'Silent Night', titles: { es: 'Noche de paz' }, genre: ['christmas', 'carol'], season: { key: 'carols' }, parts: satb() },
    { id: 'amazing-grace', title: 'Amazing Grace', genre: ['hymn', 'gospel'], parts: satb() },
    { id: 'frere-jacques', title: 'Frère Jacques', genre: ['round', 'folk'], parts: [{ key: 'v1', name: 'Voice 1', range: { low: 60, high: 69 } }] },
    { id: 'drunken-sailor', title: 'Drunken Sailor', genre: ['sea shanty', 'folk'], parts: satb() },
    { id: 'shenandoah', title: 'Shenandoah', genre: ['folk'], group: 'folk-songs', parts: satb() },
    { id: 'happy', title: 'Happy Birthday', genre: [], parts: satb() },
];

test('library: a song is in the first group its genres name, the catalogue\'s group first, else More songs', () => {
    assert(groupOf(SONGS[0]) === 'christmas' && groupOf(SONGS[1]) === 'hymns', 'by genre');
    assert(groupOf(SONGS[2]) === 'rounds', 'a round that is also folk: Rounds, as /songs has it');
    assert(groupOf(SONGS[3]) === 'sea-shanties', 'a shanty that is also folk');
    assert(groupOf({ ...SONGS[1], group: 'spirituals' }) === 'spirituals', 'the catalogue says');
    assert(groupOf(SONGS[5]) === 'more', 'no genre: More songs');
});

test('library: the chips count each group, the one in season first', () => {
    const counts = groupCounts(SONGS);
    assert(counts[0].key === 'christmas' && counts[0].season, JSON.stringify(counts[0]));
    assert(counts.map((c) => c.key).join() === 'christmas,rounds,hymns,sea-shanties,folk-songs,more', counts.map((c) => c.key).join());
    const off = groupCounts(SONGS.map((s) => ({ ...s, season: null })));
    assert(off.map((c) => c.key).join() === 'christmas,rounds,hymns,sea-shanties,folk-songs,more', 'out of season: /songs order');
});

test('library: search finds a title as written or in the language, without accents or case', () => {
    assert(matches(SONGS[0], 'noche', 'es') && matches(SONGS[0], 'silent', 'es'), 'Spanish and English');
    assert(!matches(SONGS[0], 'noche', 'en'), 'no Spanish title searched in English');
    assert(matches(SONGS[2], 'frere', 'en') && matches(SONGS[2], 'FRÈRE', 'en'), 'accents and case');
    assert(titleIn(SONGS[0], 'es-MX') === 'Noche de paz' && titleIn(SONGS[1], 'es') === 'Amazing Grace', 'its own title where none');
});

test('library: I sing keeps a song with that part, in the range when it\'s known, and a round for anyone', () => {
    assert(hasPartFor(SONGS[1], 'alto') && hasPartFor(SONGS[2], 'bass'), 'a part of that voice; a round has no voices');
    const high = { low: 72, high: 84 };
    assert(!hasPartFor({ ...SONGS[1], parts: satb(40) }, 'alto', high), 'an alto line far below this alto, beyond a key change');
    assert(hasPartFor(SONGS[1], 'alto', { low: 62, high: 74 }), 'two semitones out: Sing moves the key');
    assert(!hasPartFor({ ...SONGS[1], parts: SONGS[1].parts.filter((p) => p.key !== 'tenor') }, 'tenor'), 'no tenor part: not for a tenor');
});

test('library: the whole library by group; a filter turns it into one list A to Z', () => {
    const all = librarySections(SONGS, {});
    assert(all[0].key === 'christmas' && all.length === 6, all.map((s) => s.key).join());
    const searched = librarySections(SONGS, { query: 'a' }, { lang: 'en' });
    assert(searched.length === 1 && searched[0].key === null, 'one list');
    const titles = searched[0].songs.map((s) => s.title);
    assert(titles.join() === [...titles].sort((a, b) => a.localeCompare(b)).join(), titles.join());
    assert(filterSongs(SONGS, { group: 'hymns' }).map((s) => s.id).join() === 'amazing-grace', 'a category');
    assert(librarySections(SONGS, { query: 'zzz' })[0].songs.length === 0, 'nothing matches');
});
