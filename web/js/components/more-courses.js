// "More courses" on the Learn hub (docs/plus-courses-build-plan.md, 2.2; the lean list redesigned,
// 2026-11-01, now that there are many): the courses you've started first, as cards with how far you
// got and Continue; then every course in its group (Your voice, Harmony, Styles), one panel a group,
// a short row each (its colour, name, one-line promise, length or progress), one column on a phone and
// two wider;
// each group's courses still to come in one quiet line under it. The free first lesson is said once,
// in the heading's line. Rows and cards are links, so the hub's own Continue stays its one primary.
// Shows nothing when no course is open to this account (everyone but staff, until launch).

import { localized, t, tk } from '../i18n.js';
import { navigate } from '../router.js';
import { account } from '../stores/account.js';
import { learning, loadLearning, loadPlusCourses } from '../stores/learning.js';
import { courseSummary, courseVisible } from '../courses.js';
import { PART_COLORS } from '../theme.js';
import { CourseCard } from './course-card.js';
import { Icon } from './icon.js';

// The catalogue's groups (course/plus/courses.json `group`), in this order.
const GROUPS = [
    { key: 'skills', title: tk('courses.group.skills') },
    { key: 'harmony', title: tk('courses.group.harmony') },
    { key: 'styles', title: tk('courses.group.styles') },
];

export const MoreCourses = {
    components: { CourseCard, Icon },
    setup: () => ({ localized }),
    template: `
        <section v-if="open.length" class="space-y-5" :aria-label="t('courses.more')" data-more-courses>
            <div>
                <h2 class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xl font-extrabold tracking-tight text-cream">{{ t('courses.more') }}<span v-if="preview" class="chip text-sm font-semibold tracking-normal" data-courses-preview>{{ t('courses.preview') }}</span></h2>
                <p class="text-sm text-muted">{{ plus || preview ? t('courses.moreLinePlus') : t('courses.moreLine') }}</p>
            </div>
            <!-- Yours: started courses, with how far you got. -->
            <ul v-if="started.length" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3" :aria-label="t('courses.yours')" data-courses-started>
                <li v-for="c in started" :key="c.entry.id" class="min-w-0" :data-course="c.entry.id">
                    <CourseCard :entry="c.entry" :summary="c.summary" :plus="plus" :preview="preview" />
                </li>
            </ul>
            <!-- Every course by group, one panel a group: a short row each. -->
            <div class="space-y-5">
                <section v-for="g in groups" :key="g.key" class="min-w-0 space-y-2" :aria-labelledby="'courses-group-' + g.key" :data-course-group="g.key">
                    <h3 :id="'courses-group-' + g.key" class="text-xs font-bold uppercase tracking-[0.12em] text-muted">{{ t(g.title) }}</h3>
                    <!-- One column on a phone, two on a wider screen; each row draws its own line, so an odd last row leaves no hole. -->
                    <ul v-if="g.open.length" class="card grid overflow-hidden sm:grid-cols-2">
                        <li v-for="c in g.open" :key="c.entry.id" class="border-t border-line first:border-t-0 sm:odd:border-e sm:[&:nth-child(2)]:border-t-0" :data-course-row="c.entry.id">
                            <a :href="href(c)" class="group flex h-full min-h-16 items-center gap-3 px-4 py-3 hover:bg-raised/50" :aria-label="label(c)" @click.prevent="navigate(href(c))">
                                <span class="size-2.5 shrink-0 rounded-full" :style="{ backgroundColor: colour(c) }" aria-hidden="true"></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block font-bold leading-snug text-cream" translate="no">{{ localized(c.entry.title) }}</span>
                                    <span class="block truncate text-sm text-muted" translate="no">{{ localized(c.entry.line) }}</span>
                                </span>
                                <span class="shrink-0 text-end text-xs tabular-nums text-muted" data-course-state>{{ c.summary.started ? t('courses.progressShort', { done: c.summary.done, total: c.summary.total }) : t('courses.lessons', { count: c.summary.total }) }}</span>
                                <Icon name="chevron" :size="16" class="shrink-0 -rotate-90 text-muted transition group-hover:text-cream rtl:rotate-90" />
                            </a>
                        </li>
                    </ul>
                    <p v-if="g.soon.length" class="px-1 text-sm text-muted" data-courses-soon>
                        <span class="font-semibold">{{ t('courses.soonShort') }}</span> <span translate="no">{{ g.soon.map((e) => localized(e.title)).join(' · ') }}</span>
                    </p>
                </section>
            </div>
        </section>`,
    created() {
        loadLearning();
        loadPlusCourses();
    },
    computed: {
        plus: () => account.me?.plan === 'plus',
        /** Before launch only staff see the courses: the cards say it's a preview. */
        preview: () => !!account.me?.staff && account.me?.plusCourses !== true,
        /** Every course this account can open, with how far it got, in the catalogue's order. */
        open() {
            return learning.catalogue
                .filter((entry) => courseVisible(entry, account.me, account.config) && learning.courses[entry.id])
                .map((entry) => ({ entry, summary: courseSummary(learning.courses[entry.id], learning.progress) }));
        },
        started() {
            return this.open.filter((c) => c.summary.started);
        },
        /** The groups with their open courses (the started ones too, so a group is whole) and those to come. */
        groups() {
            const isOpen = new Set(this.open.map((c) => c.entry.id));
            return GROUPS.map((g) => ({
                ...g,
                open: this.open.filter((c) => (c.entry.group ?? 'skills') === g.key),
                soon: learning.catalogue.filter((e) => (e.group ?? 'skills') === g.key && !isOpen.has(e.id)),
            })).filter((g) => g.open.length || g.soon.length);
        },
    },
    methods: {
        navigate,
        href: (c) => '/learn/c/' + c.entry.id,
        colour(c) {
            return this.paint(PART_COLORS[((c.entry.colour ?? 4) - 1) % PART_COLORS.length]);
        },
        /** "Vibrato, 17 lessons" or "Vibrato, 3 of 17 lessons". */
        label(c) {
            const state = c.summary.started ? t('courses.progress', { done: c.summary.done, total: c.summary.total }) : t('courses.lessons', { count: c.summary.total });
            return `${localized(c.entry.title)}, ${state}`;
        },
    },
};
