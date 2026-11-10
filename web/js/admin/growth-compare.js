// Growth's fair comparisons in words (docs/growth-compare-spec.md, sections 3 and 4; the server decides every verdict
// and change in admin.growth.show, App\Support\Stats\FairCompare, and this only says them): a comparison's name with
// its cut time, a change ("+3", "+24%", "3 times as many"), an expected number ("about 31" when estimated), the usual
// range, and the "How we're tracking" sentence from the four headline verdicts. Pure, so tests/growth-compare.test.js
// checks the spec's worked examples word for word.

import { formatDate, formatNumber, t, tk } from '../i18n.js';

/** The periods, as the selector offers them and the server takes them (?period=). */
export const PERIODS = ['today', '7d', '28d', 'month', '12w'];
/** A remembered choice from before (days): 30 days is 4 weeks now (spec section 1). */
export const FROM_DAYS = { 1: 'today', 7: '7d', 30: '28d', 84: '12w' };

/** Each verdict's chip: its words, its arrow and its tone (up and down wear --color-up and --color-down). */
export const VERDICTS = {
    up: { words: tk('admin.growth.verdict.up'), arrow: '↑', tone: 'up', frame: 'tint' },
    down: { words: tk('admin.growth.verdict.down'), arrow: '↓', tone: 'down', frame: 'tint' },
    maybeUp: { words: tk('admin.growth.verdict.maybeUp'), arrow: '↑', tone: 'up', frame: 'outline' },
    maybeDown: { words: tk('admin.growth.verdict.maybeDown'), arrow: '↓', tone: 'down', frame: 'outline' },
    same: { words: tk('admin.growth.verdict.same'), arrow: '', tone: '', frame: 'outline' },
    tooFew: { words: tk('admin.growth.verdict.tooFew'), arrow: '', tone: '', frame: 'outline' },
    none: { words: tk('admin.growth.verdict.none'), arrow: '', tone: '', frame: 'none' },
    new: { words: tk('admin.growth.verdict.new'), arrow: '', tone: '', frame: 'outline' },
    noHistory: { words: tk('admin.growth.verdict.noHistory'), arrow: '', tone: '', frame: 'none' },
};

const whole = (n) => formatNumber(Math.round(n));
const day = (date, options = { month: 'short', day: 'numeric' }) => formatDate(`${date}T12:00:00Z`, { ...options, timeZone: 'UTC' });

/** The change in words: "+3" (a count), "+24%" (a percentage), "3 times as many" (above +200%), or ''. */
export function changeText(change) {
    if (!change || change.value === null || change.value === undefined) return '';
    const sign = change.value > 0 ? '+' : change.value < 0 ? '-' : '';
    const size = formatNumber(Math.abs(change.value));
    if (change.kind === 'count') return `${sign}${size}`;
    if (change.kind === 'percent') return `${sign}${size}%`;
    if (change.kind === 'times') return t('admin.growth.compare.times', { n: formatNumber(change.value) });
    return '';
}

/** An expected number, "about 31" when it was estimated from a day's total. */
export function expectedText(row) {
    if (row?.expected === null || row?.expected === undefined) return '';
    return row.estimated ? t('admin.growth.compare.about', { n: whole(row.expected) }) : whole(row.expected);
}

/**
 * A comparison's name, with its cut time (spec 7, F1): "Yesterday by 10:23", "A usual Monday by 10:23", "The 7 days
 * before, to 10:23", "A usual week (the 4 before)", "The 4 weeks before, to 10:23", "Oct 1 to 9, to 10:23", "The 12
 * weeks before". `weekday` is today's in the report zone ("Monday").
 */
export function comparisonName(period, key, { cut, weekday = '', from = null, to = null, scaledDays = null, available = 4 } = {}) {
    if (key === 'usual') {
        // "(the 2 before)" only when fewer than 4 windows are there to average and enough (2) to say anything.
        const few = available >= 2 && available < 4;
        if (period.key === 'today') return few ? t('admin.growth.compare.usualDayFew', { weekday, cut, count: available }) : t('admin.growth.compare.usualDay', { weekday, cut });
        return t('admin.growth.compare.usualWeek', { count: few ? available : 4 });
    }
    if (period.key === 'today') return t('admin.growth.compare.yesterday', { cut });
    if (period.key === '7d') return t('admin.growth.compare.weekBefore', { cut });
    if (period.key === '28d') return t('admin.growth.compare.fourWeeksBefore', { cut });
    if (period.key === '12w') return t('admin.growth.compare.twelveWeeksBefore');
    if (period.key === 'month' && from && to) {
        if (scaledDays) return t('admin.growth.compare.monthScaled', { month: day(from, { month: 'long' }), days: formatNumber(scaledDays) });
        return t('admin.growth.compare.monthTo', { from: day(from), to: day(to, { day: 'numeric' }), cut });
    }
    return '';
}

/** What the period's number covers: "so far today", "in the last 7 days", "since Nov 1". */
export function periodWords(period) {
    if (period.key === 'today') return t('admin.growth.compare.soFarToday');
    if (period.key === 'month') return t('admin.growth.compare.sinceDate', { date: day(period.from) });
    return IN_LAST[period.key] ? t(IN_LAST[period.key]) : '';
}

/** The usual range in words: "usually 20 to 42 by 10:23" (a card) or "Usually 31 to 52 by now" (the summary). */
export function rangeText(row, { cut = null } = {}) {
    if (row?.low === null || row?.low === undefined || row?.high === null || row?.high === undefined) return '';
    return cut ? t('admin.growth.compare.usuallyBy', { low: whole(row.low), high: whole(row.high), cut }) : t('admin.growth.compare.usuallyNow', { low: whole(row.low), high: whole(row.high) });
}

/** Too few to tell, said with both counts (spec 4.6, rule 1): "3 so far, 2 by 10:23 yesterday". */
export function tooFewText(period, now, row, { cut }) {
    const then = expectedText(row) || '0';
    return FEW[period.key] ? t(FEW[period.key], { now: whole(now), then, cut }) : '';
}

/** Where a row's comparison starts, when there isn't enough history yet: "Compared from Nov 15". */
export function noHistoryText(row, since) {
    if (row?.comparesFrom) return t('admin.growth.compare.comparesFrom', { date: day(row.comparesFrom) });
    return since ? t('admin.growth.compare.countingSince', { date: day(since) }) : t('admin.growth.compare.noneYet');
}

/** The headline metrics' names, for the sentence: lower case mid-sentence (spec 3). */
const SHORT = { visits: tk('admin.growth.track.short.visits'), arrivals: tk('admin.growth.track.short.arrivals'), firstLessons: tk('admin.growth.track.short.firstLessons'), lesson1: tk('admin.growth.track.short.lesson1'), signUps: tk('admin.growth.track.short.signUps') };

/** Each group's clause: the first one names what it's set against, a later one doesn't. */
const CLAUSES = {
    up: [tk('admin.growth.track.upRef'), tk('admin.growth.track.up')],
    down: [tk('admin.growth.track.downRef'), tk('admin.growth.track.down')],
    same: [tk('admin.growth.track.sameRef'), tk('admin.growth.track.same')],
};
const HISTORY = [tk('admin.growth.track.history'), tk('admin.growth.track.historyAlso')];
const FEW = { today: tk('admin.growth.compare.few.today'), '7d': tk('admin.growth.compare.few.7d'), '28d': tk('admin.growth.compare.few.28d'), month: tk('admin.growth.compare.few.month'), '12w': tk('admin.growth.compare.few.12w') };
const IN_LAST = { '7d': tk('admin.growth.compare.inLast.7d'), '28d': tk('admin.growth.compare.inLast.28d'), '12w': tk('admin.growth.compare.inLast.12w') };

/** "a", "a and b", "a, b and c". */
export function listOf(names) {
    if (names.length < 2) return names[0] ?? '';
    return t('admin.growth.track.list', { first: names.slice(0, -1).join(', '), last: names.at(-1) });
}

const capital = (text) => text.replace(/^./, (c) => c.toLocaleUpperCase());

/**
 * The "How we're tracking" sentence from the headline metrics' verdicts on the usual row (spec 3): the moved and the
 * steady in one sentence ("Visits and arrivals are up on a usual week; sign-ups are about the same."), the too-few in a
 * second ("First lessons: too few to tell yet."). All steady: "A usual week so far: nothing has moved more than chance
 * would." All too few: "Too few numbers this week to say how we're tracking. The counts are below." Never more than two
 * sentences. Maybe up and maybe down count as about the same here (not past chance); new counts as up.
 */
export function trackingSentence(metrics) {
    const group = { up: [], down: [], same: [], few: [], history: [] };
    for (const m of metrics) {
        const v = m.usual?.verdict;
        const name = t(SHORT[m.key === 'firstLessons' && m.measure === 'lesson1' ? 'lesson1' : m.key] ?? m.key);
        if (v === 'up' || v === 'new') group.up.push(name);
        else if (v === 'down') group.down.push(name);
        else if (v === 'same' || v === 'maybeUp' || v === 'maybeDown') group.same.push(name);
        else if (v === 'noHistory') group.history.push(name);
        else group.few.push(name);
    }
    if (!group.up.length && !group.down.length && !group.same.length) return t('admin.growth.track.allFew');
    if (!group.up.length && !group.down.length && !group.few.length && !group.history.length) return t('admin.growth.track.allSame');
    // The first clause names what they're set against ("up on a usual week"); the ones after it don't repeat it.
    const clauses = [];
    for (const [key, names] of [['up', group.up], ['down', group.down], ['same', group.same]]) {
        if (names.length) clauses.push(t(CLAUSES[key][clauses.length ? 1 : 0], { names: listOf(names) }));
    }
    const first = `${capital(clauses.join('; '))}.`;
    // The rest in a second sentence: too few to tell, and new enough to have nothing to compare with yet.
    const rest = [];
    if (group.few.length) rest.push(t('admin.growth.track.few', { names: listOf(group.few) }));
    if (group.history.length) rest.push(t(HISTORY[rest.length ? 1 : 0], { names: listOf(group.history) }));
    return rest.length ? `${first} ${capital(rest.join('; '))}.` : first;
}
