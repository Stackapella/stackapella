// A library song's versions (Hillel, 2026-11-18, "One song, US/UK switch"): where the UK sings a carol to another tune or
// harmony, that version is its own song file, and the catalogue lists the song once with `songVersions` (each one free
// here: its id, its name, "us" or "uk", and `here`, the one people where you are start on: the UK's in the UK). A tap on
// the song opens the version kept on this device, else `here`'s; the song's sheet switches between them, and the pick is
// kept (a convenience: nothing breaks without it). A link that names a version (a song page's Learn) opens that one.

import { local } from './storage.js';

const KEY = 'stackapella.songVersion.';

/** The song's versions, its own first, or [] when it has only one. */
export function versionsOf(song) {
    const versions = song?.songVersions ?? [];
    return versions.length > 1 ? versions : [];
}

// The pick is kept under the song's own id (the first version's), as the song's page keeps it.
const keyOf = (song) => KEY + (versionsOf(song)[0]?.id ?? song.id);

/** The id of the version a tap on the song opens: the kept pick, else the one people here start on, else the song's own. */
export function startingVersion(song) {
    const versions = versionsOf(song);
    if (!versions.length) return song.id;
    const pick = local.get(keyOf(song));
    return (versions.find((v) => v.name === pick) ?? versions.find((v) => v.here) ?? versions[0]).id;
}

/** Keeps `name` as this device's pick for the song. */
export function keepVersion(song, name) {
    if (versionsOf(song).length) local.set(keyOf(song), name);
}

/**
 * The library's entry for one of the song's versions: the song's summary under that version's id (the sheet then fetches
 * its own notes, makers and words), its versions kept for the switch.
 */
export function asVersion(song, id) {
    return id === song.id ? song : { ...song, id, songVersions: song.songVersions };
}

/** The library entry a link's id names: a song, or one of a song's versions; null when it's neither. */
export function entryFor(list, id) {
    const own = list.find((s) => s.id === id);
    if (own) return own;
    const song = list.find((s) => s.songVersions?.some((v) => v.id === id));
    return song ? asVersion(song, id) : null;
}
