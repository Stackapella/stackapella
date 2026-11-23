// One measured skill and one next exercise (the consultant's UX review, item 6): progress said as something measured
// across the singer's recent takes ("On pitch 72% across your last 5 takes."), with the one
// exercise that works on what they show most ("Next: Reaching the high notes, in today's workout"). The same words in both skins:
// no stars, XP or streaks (docs/skins-plan.md). Nothing until there are two scored takes to read.

import { formatNumber, t, tk } from '../i18n.js';
import { navigate } from '../router.js';
import { learning } from '../stores/learning.js';
import { weakSpot } from '../workout.js';

const RECENT = 5;
const WHAT = {
    flatHigh: tk('home.workout.insight.flatHigh'), sharpHigh: tk('home.workout.insight.sharpHigh'), flatOverall: tk('home.workout.insight.flatOverall'), sharpOverall: tk('home.workout.insight.sharpOverall'),
    scoops: tk('home.workout.insight.scoops'), late: tk('home.workout.insight.late'), early: tk('home.workout.insight.early'), wavering: tk('home.workout.insight.wavering'),
};
const STEADY = tk('home.workout.focusWhat');

/** The recent takes' measure: `{ percent, count, insight }`, or null under two scored takes. Pure, for its test. */
export function measuredSkill(recent = []) {
    const takes = recent.filter((take) => typeof take?.percent === 'number').slice(0, RECENT);
    if (takes.length < 2) return null;
    const percent = Math.round(takes.reduce((sum, take) => sum + take.percent, 0) / takes.length);
    return { percent, count: takes.length, insight: weakSpot(takes) };
}

export const SkillLine = {
    computed: {
        skill: () => measuredSkill(learning.progress?.recent ?? []),
        next() {
            const insight = this.skill?.insight;
            return t(insight && WHAT[insight] ? WHAT[insight] : STEADY);
        },
    },
    methods: {
        open: () => navigate('/workout'),
    },
    template: `
        <section v-if="skill" class="space-y-2 rounded-2xl border border-line p-4 text-start" aria-labelledby="skill-line-title" data-skill-line :data-insight="skill.insight || null">
            <h3 id="skill-line-title" class="text-xs font-bold uppercase tracking-[0.14em] text-muted">{{ t('skillLine.title') }}</h3>
            <p class="text-cream" data-skill-measure>{{ t('skillLine.onPitch', { percent: formatNumber(skill.percent), count: skill.count }) }}</p>
            <p class="flex flex-wrap items-center gap-x-3 text-sm text-muted">{{ t('skillLine.next', { what: next }) }} <button type="button" class="link tap-44 font-semibold" data-skill-next @click="open">{{ t('skillLine.go') }}</button></p>
        </section>`,
    setup: () => ({ formatNumber }),
};
