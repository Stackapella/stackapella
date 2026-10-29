// "More courses" on the Learn hub (docs/plus-courses-build-plan.md, 2.2): a card for each Plus course
// this account can see, with its picture, line, progress and the way in (Start, Continue, or Try the
// first lesson free). Shows nothing when no course is visible (everyone but staff, until launch).

import { localized, t } from '../i18n.js';
import { navigate } from '../router.js';
import { account } from '../stores/account.js';
import { learning, loadLearning, loadPlusCourses } from '../stores/learning.js';
import { courseSummary, courseVisible } from '../courses.js';
import { LearnVisual } from './learn-visual.js';

export const MoreCourses = {
    components: { LearnVisual },
    setup: () => ({ localized }),
    template: `
        <section v-if="shown.length" class="space-y-4" :aria-label="t('courses.more')" data-more-courses>
            <div>
                <h2 class="text-xl font-extrabold tracking-tight text-cream">{{ t('courses.more') }}</h2>
                <p class="text-sm text-muted">{{ t('courses.moreLine') }}</p>
            </div>
            <ul class="grid gap-4 md:grid-cols-2">
                <li v-for="c in shown" :key="c.entry.id" class="card flex min-w-0 flex-col gap-4 p-5 sm:flex-row sm:items-center" :data-course="c.entry.id">
                    <div class="w-40 shrink-0"><LearnVisual :type="c.entry.visual" /></div>
                    <div class="min-w-0 flex-1 space-y-2">
                        <p class="flex flex-wrap items-center gap-2">
                            <span class="text-lg font-extrabold tracking-tight text-cream" translate="no">{{ localized(c.entry.title) }}</span>
                            <span v-if="preview" class="chip">{{ t('courses.preview') }}</span>
                        </p>
                        <p class="text-sm text-muted" translate="no">{{ localized(c.entry.line) }}</p>
                        <p class="tabular-nums text-xs text-muted">{{ c.summary.started ? t('courses.progress', { done: c.summary.done, total: c.summary.total }) : t('courses.lessons', { count: c.summary.total }) }}</p>
                        <a :href="'/learn/c/' + c.entry.id" class="btn-primary btn-sm" @click.prevent="navigate('/learn/c/' + c.entry.id)">{{ c.summary.started ? t('courses.continue') : plus || preview ? t('courses.start') : t('courses.tryFree') }}</a>
                    </div>
                </li>
            </ul>
        </section>`,
    created() {
        loadLearning();
        loadPlusCourses();
    },
    computed: {
        plus: () => account.me?.plan === 'plus',
        /** Before launch only staff see the courses: the chip says it's a preview. */
        preview: () => !!account.me?.staff && account.me?.plusCourses !== true,
        shown() {
            return learning.catalogue
                .filter((entry) => courseVisible(entry, account.me) && learning.courses[entry.id])
                .map((entry) => ({ entry, summary: courseSummary(learning.courses[entry.id], learning.progress) }));
        },
    },
    methods: { navigate },
};
