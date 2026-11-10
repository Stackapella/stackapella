// Admin analytics (docs/admin-analytics-plan.md, slices 1 and 2): people using the app, signed in and
// guests, and revenue's currencies (never converted) and subscriber counts.
import { bestMeasure, dailyBars, todayPair, weekIn } from '/js/admin/reach.js';
import { countsBy, mrrChanges } from '/js/admin/revenue.js';
import { isTag, sourceName } from '/js/admin/growth.js';
import { loadLanguage } from '/js/i18n.js';
import { assert, test } from './harness.js';

test('admin analytics: people using the app in the best measure every week has, signed in and guests', () => {
    const opens = { account: 18.5, guest: 56.9 };
    assert(bestMeasure([{ opens, devices: null, reach: null }]) === 'opens', 'only opens so far');
    assert(bestMeasure([{ opens, devices: { account: 9, guest: 21 }, reach: null }, { opens, devices: null, reach: null }]) === 'opens', 'devices not yet in every week');
    assert(bestMeasure([{ opens, devices: { account: 9, guest: 21 }, reach: { account: 30, guest: 80 } }]) === 'reach', 'a whole week counted: devices a week');
    const w = weekIn({ from: 'a', to: 'b', opens }, 'opens');
    assert(w.account === 18.5 && w.guest === 56.9 && Math.abs(w.total - 75.4) < 1e-9, JSON.stringify(w));
    assert(weekIn({ devices: null }, 'devices') === null && weekIn(undefined, 'opens') === null, 'not counted: null');
});

test('admin analytics: MRR against a month ago in each currency, never converted', () => {
    const changes = mrrChanges([{ currency: 'usd', cents: 4658 }, { currency: 'gbp', cents: 898 }], [{ currency: 'usd', cents: 2329 }, { currency: 'eur', cents: 500 }]);
    assert(JSON.stringify(changes.map((c) => c.currency)) === '["eur","gbp","usd"]', 'each currency of either side, sorted');
    assert(changes[0].now === 0 && changes[0].diff === -500, 'a currency gone: down to 0');
    assert(changes[1].before === 0 && changes[1].diff === 898, 'a new currency: up from 0');
    assert(changes[2].diff === 2329, 'usd up by its own cents');
});

test('admin analytics: subscribers summed by state, store and plan, empty ones left out', () => {
    const rows = [{ state: 'paying', store: 'web', plan: 'month', count: 6 }, { state: 'paying', store: 'apple', plan: 'year', count: 2 }, { state: 'trialing', store: 'web', plan: 'year', count: 4 }];
    const order = { paying: 1, trialing: 1, pastDue: 1, cancelling: 1 };
    assert(JSON.stringify(countsBy(rows, 'state', order)) === '[{"key":"paying","count":8},{"key":"trialing","count":4}]', JSON.stringify(countsBy(rows, 'state', order)));
    assert(countsBy(rows, 'store', { web: 1, apple: 1, google: 1 }).map((r) => r.count).join() === '10,2', 'by store');
});

test('reach: a device is counted once in each UTC day, ISO week and month', async () => {
    const { periodKeys } = await import('/js/usage.js');
    const at = (iso) => periodKeys(Date.parse(iso));
    assert(JSON.stringify(at('2026-10-31T23:30:00Z')) === '{"app_day":"2026-10-31","app_week":"2026-W44","app_month":"2026-10"}', JSON.stringify(at('2026-10-31T23:30:00Z')));
    assert(at('2026-11-02T00:10:00Z').app_week === '2026-W45', 'Monday starts a week');
    assert(at('2027-01-01T12:00:00Z').app_week === '2026-W53' && at('2026-01-01T12:00:00Z').app_week === '2026-W01', 'the year ends on its own ISO week');
});

test('admin analytics: people using the app by day: each day beside the day a period earlier; today beside a week ago', () => {
    const day = (n, opens, devices = null) => ({ day: `2026-10-${String(n).padStart(2, '0')}`, opens: { account: opens, guest: opens * 2 }, devices });
    const days = Array.from({ length: 20 }, (_, i) => day(i + 1, i + 1));
    const week = dailyBars(days, 7);
    assert(week.bars.length === 7 && week.bars[0].key === '2026-10-14' && week.bars[0].before.account === 7, JSON.stringify(week.bars[0]));
    assert(week.measure === 'opens', 'no devices yet: opens');
    const month = dailyBars(days, 30);
    assert(month.bars.length === 20 && month.bars.every((b) => b.before === null), 'before the counts began: nothing to compare');
    // Today so far ends the chart, marked so, beside the day a period earlier.
    const today = { day: '2026-10-21', opens: { account: 3, guest: 4 }, devices: null };
    const withToday = dailyBars(days, 7, today);
    assert(withToday.bars.length === 7 && withToday.bars.at(-1).key === '2026-10-21' && withToday.bars.at(-1).soFar && withToday.bars.at(-1).now.total === 7, JSON.stringify(withToday.bars.at(-1)));
    assert(withToday.bars[0].key === '2026-10-15' && !withToday.bars[0].soFar && withToday.bars.at(-1).before.account === 14, 'each beside the day 7 earlier');
    assert(dailyBars([...days, today], 7, today).bars.filter((b) => b.key === '2026-10-21').length === 1, 'today once, even when the days hold it');
    const pair = todayPair(days, today);
    assert(pair.soFar && pair.now.total === 7 && pair.before.account === 14, JSON.stringify(pair));
    const fallback = todayPair(days, null);
    assert(!fallback.soFar && fallback.day === '2026-10-20' && fallback.before.account === 13, 'no today from the server: the last whole day');
});

test('admin search: a page shown by its path on the site, a search as typed', async () => {
    const { shortKey } = await import('/js/admin/search.js');
    assert(shortKey('https://stackapella.com/courses/perfect-your-pitch') === '/courses/perfect-your-pitch', 'the path');
    assert(shortKey('https://stackapella.com/') === '/', 'the home page');
    assert(shortKey('harmony app') === 'harmony app', 'a search');
});

test('admin: the report time zone said as a city, from the server, never written here', async () => {
    const { zoneName } = await import('/js/admin/common.js');
    assert(zoneName('UTC') === 'UTC' && zoneName(null) === 'UTC', 'UTC, and UTC when not sent');
    assert(zoneName('America/New_York') === 'New York', zoneName('America/New_York'));
    assert(zoneName('Not/AZone') === 'Not/AZone', 'an unknown id as it is');
});

test('admin growth: a tool, an embed and the song part pages read as words; a campaign tag stays as written', async () => {
    await loadLanguage(['en']);
    const name = (source) => sourceName({ source });
    assert(name('tool-tuner') === 'Tools: tuner' && !isTag('tool-tuner'), name('tool-tuner'));
    assert(name('embed-choir.example.com') === 'Embed: choir.example.com' && !isTag('embed-choir.example.com'), name('embed-choir.example.com'));
    assert(name('part-page') === 'Song part pages' && !isTag('part-page'), name('part-page'));
    assert(name('profile') === "Singers' profiles" && !isTag('profile'), name('profile'));
    assert(name('hn') === 'hn' && isTag('hn'), 'a campaign tag as written');
    assert(name('none') === 'No tag (search and direct)' && !isTag('none'), name('none'));
});
