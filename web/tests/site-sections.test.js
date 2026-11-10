// Growth's site visits by section (js/admin/site-sections.js): the counts' days as a row a section, guest and account
// together, every day of the week there (a quiet day as 0), other kinds left out, in the sections' order.
import { datesTo, SECTIONS, sectionRows } from '/js/admin/site-sections.js';
import { assert, test } from './harness.js';

test('site sections: the last 7 days, oldest first, across a month end', () => {
    const dates = datesTo('2026-11-03');
    assert(dates.length === 7 && dates[0] === '2026-10-28' && dates[6] === '2026-11-03', dates.join(','));
});

test('site sections: a row a section in order, each day summed, quiet days 0, other kinds left out', () => {
    const dates = datesTo('2026-11-08');
    const rows = sectionRows([
        { date: '2026-11-08', kind: 'sv_tools', guest: 40, account: 2 },
        { date: '2026-11-07', kind: 'sv_tools', guest: 10, account: 0 },
        { date: '2026-11-08', kind: 'sv_home', guest: 5, account: 1 },
        { date: '2026-11-08', kind: 'lesson_done', guest: 99, account: 99 },
        { date: '2026-10-01', kind: 'sv_home', guest: 7, account: 0 }, // outside the week
    ], dates);
    assert(rows.map((r) => r.kind).join(',') === Object.keys(SECTIONS).join(','), rows.map((r) => r.kind).join(','));
    const tools = rows.find((r) => r.kind === 'sv_tools');
    assert(tools.total === 52 && tools.daily.at(-1).n === 42 && tools.daily.at(-2).n === 10 && tools.daily[0].n === 0, JSON.stringify(tools));
    assert(rows.find((r) => r.kind === 'sv_home').total === 6, 'home');
    assert(rows.find((r) => r.kind === 'sv_guides').daily.every((d) => d.n === 0), 'a quiet section');
});
