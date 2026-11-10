// Fresh lines (docs/sight-singing-plan.md, slice 5): Sight-singing's endless reading practice, from the
// course page's Sharpen up. A new two-bar line each time (js/reading-lines.js), at the level reached,
// in the singer's range, read from the stave with the lesson's own play, Hear it first and look count.
// Nothing is kept: a line read is practice, and Next line draws another.

import { t } from '../i18n.js';
import { navigate } from '../router.js';
import { learning, loadLearning } from '../stores/learning.js';
import { COURSE, readingLevel, readingLine } from '../reading-lines.js';
import { Exercise } from '../components/exercise.js';
import { Icon } from '../components/icon.js';
import { AppScreen } from '../components/page.js';

const DEFAULT_HOME = 57; // as a lesson's, until unit 1 finds the learner's own

export const ReadLinesView = {
    components: { AppScreen, Exercise, Icon },
    setup: () => ({ learning }),
    data: () => ({ seed: Math.floor(Date.now() / 1000), result: null, count: 1 }),
    template: `
        <AppScreen :title="t('readLines.title')" :eyebrow="t('readLines.eyebrow')" :leave="() => navigate('/learn/c/' + COURSE)" :back-label="t('home.back')" measure="max-w-2xl" data-read-lines>
            <template #aside><span v-if="level" class="tabular-nums text-sm text-muted">{{ t('readLines.count', { count }) }}</span></template>
            <p v-if="!learning.loaded" class="text-muted" role="status">{{ t('course.loading') }}</p>
            <p v-else-if="!level" class="text-cream">{{ t('readLines.notYet') }} <a :href="'/learn/c/' + COURSE" class="link" @click.prevent="navigate('/learn/c/' + COURSE)">{{ t('course.backToCourse') }}</a></p>
            <template v-else>
                <p class="mb-5 text-muted">{{ t('readLines.hint') }}</p>
                <Exercise :key="seed" :exercise="line" :base="home" mode="play" strictness="relaxed" :color="'var(--color-part-2)'" @done="done" />
                <div v-if="result" class="mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-line pt-4" role="status" data-read-result>
                    <p class="text-cream">{{ result.percent === null ? t('readLines.unheard') : t('readLines.scored', { percent: Math.round(result.percent) }) }}</p>
                    <button type="button" class="btn-primary" data-next-line @click="next">{{ t('readLines.next') }}</button>
                </div>
            </template>
        </AppScreen>`,
    computed: {
        COURSE: () => COURSE,
        level: () => readingLevel(learning.progress),
        home: () => learning.progress.home ?? DEFAULT_HOME,
        line() {
            return readingLine({ level: this.level, seed: this.seed, home: this.home, range: learning.progress.range ?? null });
        },
    },
    created() {
        loadLearning();
    },
    methods: {
        navigate,
        done(result) {
            if (result?.percent === undefined) return; // a run stopped early: nothing to say
            this.result = result;
        },
        next() {
            this.seed++;
            this.count++;
            this.result = null;
        },
    },
};
