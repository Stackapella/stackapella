// What's new in the library for this singer (docs/retention-plan.md, section 4, idea 5): a song opened since this device
// last looked at the library, or one in a collection that's in season (carols until 26 December). Home's three picks
// carry at most one such song, with a small tag, and only one that fits the singer's range. The device keeps when it
// last looked; nothing goes to the server but the anonymous counts (home_new_shown, home_new_tap).

import { local } from './storage.js';

const KEY = 'stackapella.library.lookedAt';

/** When this device last looked at the library (ISO), or null before the first time. */
export function lastLooked() {
    const at = local.get(KEY);
    return at && !Number.isNaN(Date.parse(at)) ? at : null;
}

/** Looked at the library now (Browse all opened); a first visit starts the clock without calling anything new. */
export function lookedNow(now = new Date()) {
    local.set(KEY, now.toISOString());
}

/**
 * A song's news for this singer, or null: 'season' (its collection is in season today) or 'new' (opened after `since`).
 * A song in season wins: it says why it's there now. Songs the app carries (no catalogue entry) have neither.
 */
export function songNews(song, since, now = new Date()) {
    const until = song?.season?.until ? Date.parse(`${song.season.until}T23:59:59`) : NaN;
    if (!Number.isNaN(until) && now.getTime() <= until) return { kind: 'season', season: song.season.key };
    if (since && song?.openedAt && Date.parse(song.openedAt) > Date.parse(since)) return { kind: 'new' };
    return null;
}

/**
 * The one pick to tag among songs in Home's order (best for the singer first): the first with news that fits the
 * range (`fits(song)`), or null. `items` are `{ tpl }`.
 */
export function newsPick(items, { since, fits, now = new Date() }) {
    for (const item of items) {
        const news = songNews(item.tpl, since, now);
        if (news && fits(item.tpl)) return { item, ...news };
    }
    return null;
}
