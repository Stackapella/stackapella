// How it went (js/report.js): what's kept of a score, the change since last time, and personal bests.
import { phraseBests, phraseStars, scoreSummary, sinceLast } from '/js/report.js';
import { assert, test } from './harness.js';

const scored = { status: 'scored', percent: 82, word: 'close', tendency: -8.4, entryMs: 12, timing: 'measured', insights: ['flatHigh'], phrases: [{ index: 0, percent: 95 }, { index: 1, percent: 60 }, { index: 2, percent: null }] };

test('report: a score keeps its numbers only, with the stars and the phrases sung', () => {
    const summary = scoreSummary(scored);
    assert(summary.pitchPercent === 82 && summary.stars === 2 && summary.level === 'close' && summary.tendency === -8, JSON.stringify(summary));
    assert(summary.phrases.length === 2 && summary.phrases[0].pitchPercent === 95, 'the phrases sung');
    assert(JSON.stringify(summary).length < 400, 'small');
    assert(scoreSummary({ status: 'unheard' }).stars === 0, 'unheard keeps nothing to celebrate');
});

test('report: since last time, and personal bests that are beaten, not first tries', () => {
    assert(sinceLast({ pitchPercent: 76 }, { pitchPercent: 82 }) === 6 && sinceLast(null, { pitchPercent: 82 }) === null, 'the change');
    const first = phraseBests({}, scoreSummary(scored).phrases);
    assert(first.bests[0] === 95 && !first.improved.length, 'a first try sets the bests quietly');
    const again = phraseBests(first.bests, [{ index: 0, pitchPercent: 90 }, { index: 1, pitchPercent: 72 }]);
    assert(again.bests[0] === 95 && again.bests[1] === 72 && again.improved.join() === '1', 'phrase 2 beat its best');
    assert(phraseStars(95) === 3 && phraseStars(70) === 2 && phraseStars(10) === 1 && phraseStars(null) === 0, 'phrase stars');
});

test('report: the review names the phrase, the trouble and its words (79.png)', async () => {
    const { phraseReview, oneThing } = await import('/js/report.js');
    const notes = [
        { beat: 0, length: 1, pitch: 60, lyric: 'A-' }, { beat: 1, length: 2, pitch: 64, lyric: 'ma-' }, { beat: 3, length: 2, pitch: 67, lyric: 'grace' },
        { beat: 5, length: 2, pitch: 65, lyric: 'sound' }, { beat: 8, length: 2, pitch: 64, lyric: 'that' }, { beat: 10, length: 2, pitch: 62, lyric: 'saved' },
    ];
    const r = (index, level, steady = true, scoop = false) => ({ index, level, steady, scoop });
    const score = {
        status: 'scored',
        notes: [r(0, 'lit'), r(1, 'lit'), r(2, 'lit', false), r(3, 'close', false), r(4, 'lit'), r(5, 'lit', true, true)],
        phrases: [{ index: 0, percent: 84 }, { index: 1, percent: 92 }],
    };
    const review = phraseReview(score, notes, [[0, 7], [8, 12]]);
    assert(review[0].troubles.map((x) => `${x.lyric}:${x.kind}`).join(' ') === 'grace:wavered sound:wavered', JSON.stringify(review[0]));
    assert(review[1].troubles.length === 1 && review[1].troubles[0].kind === 'scooped', 'a scoop is named too');
    const one = oneThing(review);
    assert(one.phrase === 0 && one.kind === 'wavered' && one.words.join(',') === 'grace,sound', JSON.stringify(one));
    assert(oneThing(phraseReview({ ...score, notes: score.notes.map((n) => ({ ...n, steady: true, scoop: false })) }, notes, [[0, 7], [8, 12]])) === null, 'nothing to try when it all went well');
});
