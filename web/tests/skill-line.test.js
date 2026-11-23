// One measured skill (the consultant's UX review, item 6): read across the last five scored takes, never fewer than two.
import { measuredSkill } from '/js/components/skill-line.js';
import { assert, test } from './harness.js';

test('measured skill: nothing under two takes', () => {
    assert(measuredSkill([]) === null && measuredSkill([{ percent: 80 }]) === null, 'one take is too few');
});

test('measured skill: the mean of the last five, and what they show most', () => {
    const takes = [70, 80, 90, 60, 50, 10].map((percent, i) => ({ percent, insights: i < 3 ? ['flatHigh'] : ['late'] }));
    const skill = measuredSkill(takes);
    assert(skill.count === 5 && skill.percent === 70, JSON.stringify(skill));
    assert(skill.insight === 'flatHigh', skill.insight);
    assert(measuredSkill([{ percent: 50 }, { at: 1 }, { percent: 70 }]).percent === 60, 'a take without a score is left out');
});
