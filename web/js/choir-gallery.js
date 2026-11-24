// The choir gallery, version 1 (docs/choir-gallery-plan.md; contract: ownChoir.gallery.add / .remove, ownChoir.show's
// `gallery` and `galleryCan` on each song, auth.config's `choirGallery`): a ready choir song its singer chooses to share
// with everyone, heard by staff first. The item is the song's own share (unlisted, its marked mix); approval covers that
// exact file. Pure helpers: what the choir page shows, and the consent's words hash. No imports but the words.

import { t, tk } from './i18n.js';

/** The switch and the consent's version (auth.config), or null while the gallery is off for this account. */
export function galleryConfig(config) {
    const g = config?.choirGallery;
    return g?.on ? { consentVersion: g.consentVersion ?? 1 } : null;
}

/**
 * What the choir page's gallery row shows for one ready song: 'add' (the row with its switch off), 'item' (the item's
 * status), or null (nothing: the gallery is off, AI is off with nothing added, or the server says it can't go in and
 * nothing is there). A song that can't go in for a reason the singer can act on still shows nothing in version 1: the
 * plan keeps the row quiet rather than explain rules.
 */
export function rowFor(song, { on, ai }) {
    if (!on || !song || song.status !== 'ready') return null;
    const item = song.gallery;
    if (item && item.status !== 'removed') return 'item';
    if (!ai) return null;
    return song.galleryCan == null ? 'add' : null;
}

/** The status line's key for an item: what the singer reads under the row. */
export function statusKey(item) {
    if (!item) return null;
    if (item.status === 'hidden') return item.hiddenBecause === 'changed' ? tk('ownChoir.gallery.status.changed') : tk('ownChoir.gallery.status.aiOff');
    return { pending: tk('ownChoir.gallery.status.pending'), approved: tk('ownChoir.gallery.status.approved'), declined: tk('ownChoir.gallery.status.declined'), held: tk('ownChoir.gallery.status.held') }[item.status] ?? null;
}

/** Whether the switch reads on: anything still in the gallery or on its way there (declined and removed read off). */
export const switchedOn = (item) => !!item && ['pending', 'approved', 'held', 'hidden'].includes(item.status);

/** The consent sheet's keys, in the order shown: what goes public, the label, whose voice, what happens next, removing. */
export const SHEET_KEYS = [tk('ownChoir.gallery.sheet.shown'), tk('ownChoir.gallery.sheet.label'), tk('ownChoir.gallery.sheet.whose'), tk('ownChoir.gallery.sheet.next'), tk('ownChoir.gallery.sheet.remove'), tk('ownChoir.gallery.sheet.confirm')];

/** The sheet's words as shown (title, each line, the button), one per line: what the consent's hash is taken over. */
export const sheetWords = (song) => [t(tk('ownChoir.gallery.sheet.title'), { song }), ...SHEET_KEYS.map((key) => t(key))].join('\n');

/** sha256 of the words, hex: the record of exactly what was agreed (the server keeps it with the version and the time). */
export async function wordsHash(words) {
    const digest = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(words));
    return [...new Uint8Array(digest)].map((b) => b.toString(16).padStart(2, '0')).join('');
}

/** A refusal's words (the add's error codes), or null for one the page reports as a plain failure. */
export function refusalKey(error) {
    if (error?.code === 'rate_limited') return tk('ownChoir.gallery.error.limitDay');
    if (error?.code === 'limit_reached') return tk('ownChoir.gallery.error.limitLive');
    if (error?.code === 'not_eligible') return error?.body?.reason === 'together' ? tk('ownChoir.gallery.error.together') : tk('ownChoir.gallery.error.notLibrary');
    return null;
}

const REASONS = {
    noise: tk('ownChoir.gallery.reason.noise'),
    broken: tk('ownChoir.gallery.reason.broken'),
    not_own: tk('ownChoir.gallery.reason.notOwn'),
    not_library: tk('ownChoir.gallery.reason.notLibrary'),
};

/** The status line as the singer reads it: a decline in their own language from its reason (staff's words only for "other"). */
export function statusLine(item, song) {
    if (item?.status !== 'declined') return statusKey(item) ? t(statusKey(item)) : '';
    const reason = item.reason === 'other' && item.reasonText ? item.reasonText : t(REASONS[item.reason] ?? tk('ownChoir.gallery.reason.plain'), { song });
    return t(tk('ownChoir.gallery.status.declined'), { reason });
}
