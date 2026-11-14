// Experiments in the app (js/experiments.js): an arm drawn by the weights, kept, and kept out by the
// audience, a new singer's first week, an experiment it excludes or four already; a lesson's variant
// swapped in for its arm.
import { assert, test } from './harness.js';
import { audienceFits, decide, pageExperiments, pickArm } from '../js/experiments.js';

const FACTS = { isNew: true, firstWeek: false, skin: 'game', size: 'phone', signedIn: false };
const ARMS = [{ arm: 'a', weight: 50 }, { arm: 'b', weight: 50 }];

test('experiments: an arm by the weights', () => {
    assert(pickArm(ARMS, 0) === 'a' && pickArm(ARMS, 0.49) === 'a' && pickArm(ARMS, 0.5) === 'b' && pickArm(ARMS, 0.99) === 'b', 'half and half');
    const third = [{ arm: 'a', weight: 1 }, { arm: 'b', weight: 1 }, { arm: 'c', weight: 1 }];
    assert(pickArm(third, 0.34) === 'b' && pickArm(third, 0.7) === 'c', 'three ways');
    assert(pickArm([{ arm: 'a', weight: 0 }, { arm: 'b', weight: 10 }], 0) === 'b', 'a zero weight never draws');
});

test('experiments: the audience, and a new singer\'s first week only when allowed', () => {
    assert(audienceFits({}, FACTS) && audienceFits({ skin: 'game', size: 'phone', new: true }, FACTS), 'fits');
    assert(!audienceFits({ skin: 'serious' }, FACTS) && !audienceFits({ size: 'desktop' }, FACTS) && !audienceFits({ signedIn: true }, FACTS), 'skin, size, signed in');
    assert(!audienceFits({ new: true }, { ...FACTS, isNew: false }), 'new devices only');
    assert(!audienceFits({}, { ...FACTS, firstWeek: true }) && audienceFits({ firstWeek: true }, { ...FACTS, firstWeek: true }), 'the first week only with firstWeek');
});

test('experiments: drawn once and kept; kept out stays out', () => {
    const lesson = { id: 'lesson1-short', arms: ARMS, enrol: true, audience: {} };
    const first = decide(lesson, { kept: {}, facts: FACTS, random: 0.7 });
    assert(first.arm === 'b' && first.keep === 'b', `drawn: ${JSON.stringify(first)}`);
    assert(decide(lesson, { kept: { 'lesson1-short': 'b' }, facts: FACTS, random: 0 }).arm === 'b', 'kept, whatever the next draw');
    assert(decide({ ...lesson, enrol: false }, { kept: { 'lesson1-short': 'a' }, facts: FACTS, random: 0 }).arm === 'a', 'still in after enrolment closes');
    assert(decide({ ...lesson, enrol: false }, { kept: {}, facts: FACTS, random: 0 }).arm === null, 'no new devices after it closes');
    const out = decide({ ...lesson, audience: { skin: 'serious' } }, { kept: {}, facts: FACTS, random: 0 });
    assert(out.arm === null && out.keep === '-', 'outside the audience: kept out');
    assert(decide(lesson, { kept: { 'lesson1-short': '-' }, facts: FACTS, random: 0 }).arm === null, 'kept out stays out');
});

test('experiments: never with one it excludes, never more than four', () => {
    const lesson = { id: 'lesson1-short', arms: ARMS, enrol: true, audience: {}, excludes: ['onb1'] };
    assert(decide(lesson, { kept: { onb1: 'b' }, facts: FACTS, random: 0 }).keep === '-', 'in onb1: kept out');
    assert(decide(lesson, { kept: { onb1: '-' }, facts: FACTS, random: 0 }).arm === 'a', 'out of onb1: may enrol');
    const four = { e1: 'a', e2: 'b', e3: 'a', e4: 'b' };
    assert(decide({ ...lesson, excludes: [] }, { kept: four, facts: FACTS, random: 0 }).keep === '-', 'a fifth: kept out');
});

test('experiments: the sample a test is read at, and the verdict only at the horizon', async () => {
    const { sampleFor, verdictOf } = await import('../js/admin/experiments.js');
    const n = sampleFor(30, 5);
    assert(n > 1000 && n < 1600 && n % 50 === 0, `30% to 35%: about 1,400 per arm, rounded to 50: ${n}`);
    assert(sampleFor(30, 10) < n && sampleFor(0, 5) === null && sampleFor(98, 5) === null, 'a bigger change needs fewer; impossible rates none');
    const steps = (metric, guard) => ({ enough: true, steps: [{ kind: 'f_lesson3', difference: metric }, { kind: 'f_plus', difference: guard }] });
    const reading = { status: 'reading', readFrom: '2026-11-01', metric: 'f_lesson3', guardrails: ['f_plus'] };
    const on = '2026-11-02';
    assert(verdictOf({ ...reading, status: 'running' }, steps({ low: 2, high: 5 }, null), on).key === 'running', 'running: no verdict, however good it looks');
    assert(verdictOf({ ...reading, readFrom: '2026-11-09' }, steps({ low: 2, high: 5 }, null), on).key === 'notYet', 'before the read date: not yet');
    assert(verdictOf(reading, { ...steps({ low: 2, high: 5 }, null), enough: false }, on).key === 'notYet', 'short of the sample: not yet');
    assert(verdictOf(reading, steps({ low: 2, high: 5 }, { low: -1, high: 1 }), on).key === 'better', 'better');
    assert(verdictOf(reading, steps({ low: -1, high: 4 }, null), on).key === 'none', 'the range includes zero: no clear difference');
    assert(verdictOf(reading, steps({ low: 2, high: 5 }, { low: -4, high: -1 }), on).key === 'guardrail', 'a worse guardrail outranks a better metric');
});

test('experiments: a brand-new device reads the running ones from the page, so lesson 1 needn\'t wait for auth.config', () => {
    const page = (content) => {
        const doc = document.implementation.createHTMLDocument('');
        if (content !== null) doc.head.append(Object.assign(doc.createElement('meta'), { name: 'experiments', content }));
        return doc;
    };
    const list = [{ id: 'mic2', arms: ARMS, enrol: true, audience: {}, excludes: [] }];
    assert(JSON.stringify(pageExperiments(page(JSON.stringify(list)))) === JSON.stringify(list), 'the page\'s list');
    assert(JSON.stringify(pageExperiments(page('[]'))) === '[]', 'none running is a list too: ready at once');
    assert(pageExperiments(page(null)) === null && pageExperiments(page('{oops')) === null && pageExperiments(page('{}')) === null, 'nothing readable: unknown, auth.config decides');
});
