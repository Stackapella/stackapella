// Home's New / Carol season pick (docs/retention-plan.md, idea 5): which song has news, and that only one in range is picked.
import { newsPick, songNews } from '/js/song-news.js';
import { assert, test } from './harness.js';

const now = new Date('2026-12-01T12:00:00Z');
const carol = { id: 'silent-night', openedAt: '2026-01-01T00:00:00Z', season: { key: 'carols', until: '2026-12-26' } };
const fresh = { id: 'fresh', openedAt: '2026-11-30T09:00:00Z', season: null };
const old = { id: 'old', openedAt: '2026-01-01T00:00:00Z', season: null };

test('song news: in season until its last day, new when opened after the last look', () => {
    assert(songNews(carol, null, now)?.kind === 'season', 'a carol in December');
    assert(songNews(carol, null, new Date('2026-12-26T20:00:00'))?.kind === 'season', 'all of the 26th');
    assert(songNews(carol, null, new Date('2026-12-27T09:00:00')) === null, 'not after the season');
    assert(songNews(fresh, '2026-11-20T00:00:00Z', now)?.kind === 'new', 'opened after the last look');
    assert(songNews(fresh, '2026-11-30T10:00:00Z', now) === null, 'looked since');
    assert(songNews(fresh, null, now) === null, 'no last look yet: nothing is new');
    assert(songNews(old, '2026-11-20T00:00:00Z', now) === null && songNews({ id: 'bundled' }, '2026-11-20T00:00:00Z', now) === null, 'old, or carried by the app');
});

test('song news: the first in Home order with news that fits, and only one', () => {
    const items = [old, fresh, carol].map((tpl) => ({ tpl }));
    const pick = newsPick(items, { since: '2026-11-20T00:00:00Z', fits: () => true, now });
    assert(pick?.item.tpl.id === 'fresh' && pick.kind === 'new', JSON.stringify(pick));
    const inRange = newsPick(items, { since: '2026-11-20T00:00:00Z', fits: (tpl) => tpl.id !== 'fresh', now });
    assert(inRange?.item.tpl.id === 'silent-night' && inRange.kind === 'season', 'out of range is passed over');
    assert(newsPick(items, { since: null, fits: () => false, now }) === null, 'nothing that fits: no tag');
});
