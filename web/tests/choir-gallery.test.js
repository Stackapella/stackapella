// The choir gallery's rules (js/choir-gallery.js): when the choir page shows the row, what the status line says (a decline
// in the singer's own words from its reason, staff's own sentence only for "other"), and the consent's words hash.
import { rowFor, statusLine, switchedOn, wordsHash } from '/js/choir-gallery.js';
import { t } from '/js/i18n.js';
import { assert, test } from './harness.js';

const ready = (extra = {}) => ({ status: 'ready', gallery: null, galleryCan: null, ...extra });

test('choir gallery: the row shows for a ready song that can go in, and for any item, even with AI off', () => {
    assert(rowFor(ready(), { on: true, ai: true }) === 'add', 'can go in');
    assert(rowFor(ready(), { on: false, ai: true }) === null, 'the switch off');
    assert(rowFor(ready({ galleryCan: 'together' }), { on: true, ai: true }) === null, "can't go in");
    assert(rowFor(ready(), { on: true, ai: false }) === null, 'AI off, nothing added');
    assert(rowFor(ready({ gallery: { status: 'hidden', hiddenBecause: 'ai_off' } }), { on: true, ai: false }) === 'item', 'AI off, still removable');
    assert(rowFor(ready({ gallery: { status: 'removed' } }), { on: true, ai: true }) === 'add', 'removed reads as never added');
    assert(rowFor({ status: 'rendering' }, { on: true, ai: true }) === null, 'not ready');
});

test('choir gallery: the status line and the switch follow the item', () => {
    assert(switchedOn({ status: 'pending' }) && switchedOn({ status: 'hidden' }) && !switchedOn({ status: 'declined' }) && !switchedOn(null), 'switch');
    const noise = statusLine({ status: 'declined', reason: 'noise' }, 'Silent Night');
    assert(noise === t('ownChoir.gallery.status.declined', { reason: t('ownChoir.gallery.reason.noise', { song: 'Silent Night' }) }), noise);
    assert(statusLine({ status: 'declined', reason: 'other', reasonText: 'Staff words.' }, 'X').startsWith('Staff words.'), 'staff words for other');
    assert(statusLine({ status: 'hidden', hiddenBecause: 'changed' }, 'X') === t('ownChoir.gallery.status.changed'), 'changed');
    assert(statusLine({ status: 'hidden', hiddenBecause: 'ai_off' }, 'X') === t('ownChoir.gallery.status.aiOff'), 'AI off');
});

test('choir gallery: the words hash is sha256 hex, the same words the same hash', async () => {
    const a = await wordsHash('Add it');
    assert(a.length === 64 && a === (await wordsHash('Add it')) && a !== (await wordsHash('Add it.')), a);
});
