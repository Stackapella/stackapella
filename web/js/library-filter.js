// The song library's filters on Home's Browse all (Hillel, 2026-11-12: "the list is unwieldy, maybe add filtering like we
// have on the songs page by category"). Pure: the categories /songs groups by, a title search, an "I sing" part and the
// list grouped by category (in season first) or A to Z once something narrows it. Home's song band draws it
// (components/library-filters.js); this file only sorts and filters.

import { suggestTranspose } from './dsp.js';

/**
 * The groups, in /songs's order, and the genres that put a song in each: the first that one of its genres names
 * (server SongCollections::GROUPS, which the catalogue's `group` comes from; this is the fallback for a song without it).
 */
export const GROUPS = [
    ['christmas', ['christmas', 'carol']],
    ['jewish-songs', ['jewish']],
    ['rounds', ['round']],
    ['spirituals', ['spiritual']],
    ['hymns', ['hymn', 'gospel']],
    ['sea-shanties', ['sea shanty']],
    ['folk-songs', ['folk', 'traditional', 'celebration']],
    ['classical', ['classical', 'latin', 'choral']],
    ['barbershop-songs', ['barbershop', 'parlour song', 'early popular song', 'musical theatre']],
];
export const MORE = 'more';
export const VOICES = ['soprano', 'alto', 'tenor', 'bass'];

/** A song's group: the catalogue's, else the first whose genres it has, else "more". */
export function groupOf(song) {
    if (song.group && GROUPS.some(([key]) => key === song.group)) return song.group;
    const genres = (song.genre ?? []).map((g) => String(g).toLowerCase());
    return GROUPS.find(([, names]) => names.some((name) => genres.includes(name)))?.[0] ?? MORE;
}

/** A song's title in `lang` where the catalogue has one ("Noche de paz"), else its own. */
export function titleIn(song, lang) {
    return song.titles?.[lang.split('-')[0]] || song.title;
}

/** Folded for matching: no case, no accents, no punctuation ("Él" finds "el"). */
const fold = (text) => String(text ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim();

/** Whether `query` matches the song's title as shown (`title`, the language's by default) or as written. */
export function matches(song, query, lang, title = (s) => titleIn(s, lang)) {
    const q = fold(query);
    return !q || fold(title(song)).includes(q) || fold(song.title).includes(q);
}

/**
 * Whether a singer of `voice` has a part in the song: a part of that voice (its key or its name), or, in a song whose
 * parts name no voice (a round, a unison tune), any part. With a measured `range` ({ low, high }, MIDI), the part must
 * fit it within the key change Sing offers (dsp.suggestTranspose).
 */
export function hasPartFor(song, voice, range = null) {
    if (!voice) return true;
    const parts = song.parts ?? [];
    const voiced = (p) => VOICES.find((v) => p.key === v || new RegExp(`\\b${v}\\b`, 'i').test(p.name ?? ''));
    const anyVoiced = parts.some(voiced);
    const candidates = anyVoiced ? parts.filter((p) => voiced(p) === voice) : parts;
    return candidates.some((p) => !range || !p.range || suggestTranspose(range, p.range).fits);
}

/**
 * The songs `filters` keep ({ group, voice, query }), in the order given. `title`: a song's title as the page shows it
 * (Home passes song-titles.js songTitle); the language's from the catalogue by default.
 */
export function filterSongs(songs, { group = null, voice = null, query = '' } = {}, { lang = 'en', range = null, title } = {}) {
    return songs.filter((s) => (!group || groupOf(s) === group) && hasPartFor(s, voice, range) && matches(s, query, lang, title));
}

/** Each group with its count of `songs` (the chips), in season first, then /songs's order; empty groups left out. */
export function groupCounts(songs) {
    const counts = new Map();
    for (const s of songs) counts.set(groupOf(s), (counts.get(groupOf(s)) ?? 0) + 1);
    const inSeason = new Set(songs.filter((s) => s.season).map(groupOf));
    const order = [...GROUPS.map(([key]) => key), MORE];
    return order
        .filter((key) => counts.get(key))
        .sort((a, b) => Number(!inSeason.has(a)) - Number(!inSeason.has(b)) || order.indexOf(a) - order.indexOf(b))
        .map((key) => ({ key, count: counts.get(key), season: inSeason.has(key) }));
}

/**
 * The list as it shows: with nothing narrowing it, a section a group (in season first, each in the order given);
 * once a category, a part or a search narrows it, one section of the matches A to Z.
 */
export function librarySections(songs, filters = {}, { lang = 'en', range = null, title = (s) => titleIn(s, lang) } = {}) {
    const kept = filterSongs(songs, filters, { lang, range, title });
    if (filters.group || filters.voice || fold(filters.query)) {
        const collator = new Intl.Collator(lang, { sensitivity: 'base' });
        return [{ key: null, songs: [...kept].sort((a, b) => collator.compare(title(a), title(b))) }];
    }
    return groupCounts(kept).map(({ key }) => ({ key, songs: kept.filter((s) => groupOf(s) === key) }));
}
