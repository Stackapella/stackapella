// Progress you can see (Brief F): the rolling average, a change worth an arrow, and the range history.
import { assert, test } from './harness.js';
import { change, mergeRangeHistory, rolling, withRange } from '/js/progress-measures.js';

test('progress: a rolling average over the last five, and a change only when large and well counted', () => {
    const avg = rolling([50, 60, 70, 80, 90, 100]);
    assert(avg[0] === 50 && avg[4] === 70 && avg[5] === 80, JSON.stringify(avg));
    assert(change(Array(19).fill(60)) === null, 'a change from too few takes');
    const up = change([...Array(10).fill(60), ...Array(10).fill(72)]);
    assert(up && up.dir === 'up' && up.from === 60 && up.to === 72, JSON.stringify(up));
    assert(change([...Array(10).fill(60), ...Array(10).fill(65)]) === null, 'a small change said');
    assert(change([...Array(10).fill(80), ...Array(10).fill(70)]).dir === 'down', 'a fall not said as down');
});

test('progress: the range kept a day at a time, measured or chosen only, merged by the later on a day', () => {
    const at = new Date(2026, 11, 1, 10).getTime();
    let list = withRange([], { low: 50, high: 70 }, 'measured', at);
    list = withRange(list, { low: 48, high: 72 }, 'chosen', at + 3600e3); // the same day, later: replaces
    assert(list.length === 1 && list[0].low === 48 && list[0].how === 'chosen', JSON.stringify(list));
    assert(withRange(list, { low: 40, high: 80 }, 'picked', at + 86400e3).length === 1, 'a picked range kept');
    assert(withRange(list, { low: 70, high: 50 }, 'measured', at + 86400e3).length === 1, 'a low above its high kept');
    const theirs = [{ date: list[0].date, low: 49, high: 71, how: 'measured', at: at - 1 }, { date: '2026-12-05', low: 47, high: 73, how: 'measured', at: at + 4 * 86400e3 }];
    const merged = mergeRangeHistory(list, theirs);
    assert(merged.length === 2 && merged[0].low === 48 && merged[1].date === '2026-12-05', JSON.stringify(merged));
});

test('progress: the range\'s widening said from a whole step at an end, never a narrowing', async () => {
    const { rangeChange } = await import('/js/progress-measures.js');
    const day = (date, low, high) => ({ date, low, high, how: 'measured', at: 0 });
    assert(rangeChange([day('2026-11-19', 55, 70)]) === null, 'one day compared');
    assert(JSON.stringify(rangeChange([day('2026-11-19', 55, 70), day('2026-11-24', 55, 72)])) === '{"top":2,"bottom":0,"since":"2026-11-19"}', 'a whole step at the top');
    assert(rangeChange([day('2026-11-19', 55, 70), day('2026-11-24', 54, 71)]) === null, 'half steps said');
    assert(rangeChange([day('2026-11-19', 55, 72), day('2026-11-24', 57, 70)]) === null, 'a narrowing said');
    const both = rangeChange([day('2026-11-19', 55, 70), day('2026-11-24', 52, 72)]);
    assert(both.top === 2 && both.bottom === 3, JSON.stringify(both));
});
