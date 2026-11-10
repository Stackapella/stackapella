// "More courses" on the Learn hub (docs/plus-courses-build-plan.md, 2.2; the lean list redesigned,
// 2026-11-01, now that there are many): the courses you've started first, as cards with how far you
// got and Continue; then every course in its group (Your voice, Harmony, Styles), one panel a group,
// a short row each (its colour, name, one-line promise, length or progress), one column on a phone and
// a 3 by 3 grid wider (Hillel, 2026-11-08); each group's courses still to come are quiet cells in it. The free first lesson is said once,
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

// A cell: a phone's row (dot, name and line, length, arrow), and in the 3-column grid the length under the line.
const CELL = 'grid h-full min-h-16 grid-cols-[auto_minmax(0,1fr)_auto_auto] items-center gap-x-3 px-4 py-3 lg:grid-cols-[auto_minmax(0,1fr)_auto] lg:content-start lg:items-start lg:py-4';
const STATE = 'shrink-0 text-end text-xs tabular-nums text-muted lg:col-start-2 lg:row-start-2 lg:mt-1 lg:text-start';

export const MoreCourses = {
    components: { CourseCard, Icon },
    setup: () => ({ localized, CELL, STATE }),
    template: `
        <section v-if="open.length" class="space-y-5" :aria-label="t('courses.more')" data-more-courses>
            <div>
                <h2 class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xl font-extrabold tracking-tight text-cream">{{ t('courses.more') }}<span v-if="preview" class="chip text-sm font-semibold tracking-normal" data-courses-preview>{{ t('courses.preview') }}</span></h2>
                <p class="text-sm text-muted">{{ t('courses.moreLine') }}</p>
            </div>
            <!-- Yours: started courses, with how far you got. -->
            <ul v-if="started.length" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3" :aria-label="t('courses.yours')" data-courses-started>
                <li v-for="c in started" :key="c.entry.id" class="min-w-0" :data-course="c.entry.id">
                    <CourseCard :entry="c.entry" :summary="c.summary" :preview="preview" />
                </li>
            </ul>
            <!-- Every course by group, one panel a group: a short row each. -->
            <div class="space-y-5">
                <section v-for="g in groups" :key="g.key" class="min-w-0 space-y-2" :aria-labelledby="'courses-group-' + g.key" :data-course-group="g.key">
                    <h3 :id="'courses-group-' + g.key" class="text-xs font-bold uppercase tracking-[0.12em] text-muted">{{ t(g.title) }}</h3>
                    <!-- Nine courses a group, the coming ones counted (Hillel, 2026-11-08: "3 x 3 grids since there will be 9 in
                         each"): one column on a phone and a tablet, a 3 by 3 grid from lg, never two columns with a lonely ninth.
                         Each cell draws its own lines. On a phone a row is as before (its length at the end); in the grid, the
                         line takes two lines and the length sits under it. A course to come is a quiet cell that does nothing,
                         in its place in the catalogue, until it opens. -->
                    <ul class="card grid overflow-hidden lg:grid-cols-3">
                        <li v-for="cell in g.cells" :key="cell.entry.id" class="min-w-0 border-t border-line first:border-t-0 lg:[&:nth-child(-n+3)]:border-t-0 lg:[&:not(:nth-child(3n))]:border-e" :data-course-row="cell.open ? cell.entry.id : null" :data-course-soon="cell.open ? null : cell.entry.id">
                            <a v-if="cell.open" :href="href(cell.open)" :class="CELL" class="group hover:bg-raised/50" :aria-label="label(cell.open)" @click.prevent="navigate(href(cell.open))">
                                <span class="size-2.5 shrink-0 rounded-full lg:mt-1.5" :style="{ backgroundColor: colour(cell.entry) }" aria-hidden="true"></span>
                                <span class="min-w-0">
                                    <span class="block font-bold leading-snug text-cream" translate="no">{{ localized(cell.entry.title) }}</span>
                                    <span class="block truncate text-sm text-muted lg:line-clamp-2 lg:whitespace-normal" translate="no">{{ localized(cell.entry.line) }}</span>
                                </span>
                                <span :class="STATE" data-course-state>{{ cell.open.summary.started ? t('courses.progressShort', { done: cell.open.summary.done, total: cell.open.summary.total }) : t('courses.lessons', { count: cell.open.summary.total }) }}</span>
                                <Icon name="chevron" :size="16" class="shrink-0 -rotate-90 text-muted transition group-hover:text-cream lg:col-start-3 lg:row-span-2 lg:row-start-1 lg:self-center rtl:rotate-90" />
                            </a>
                            <div v-else :class="CELL" data-course-soon-cell>
                                <span class="size-2.5 shrink-0 rounded-full opacity-40 lg:mt-1.5" :style="{ backgroundColor: colour(cell.entry) }" aria-hidden="true"></span>
                                <span class="min-w-0">
                                    <span class="block font-bold leading-snug text-muted" translate="no">{{ localized(cell.entry.title) }}</span>
                                    <span class="block truncate text-sm text-muted lg:line-clamp-2 lg:whitespace-normal" translate="no">{{ localized(cell.entry.line) }}</span>
                                </span>
                                <span :class="STATE" data-course-state>{{ t('courses.soonCell') }}</span>
                                <span class="w-4 lg:hidden" aria-hidden="true"></span>
                            </div>
                        </li>
                    </ul>
                </section>
            </div>
        </section>`,
    created() {
        loadLearning();
        loadPlusCourses();
    },
    computed: {
        /** Before launch only staff see the courses: the cards say it's a preview. */
        preview: () => !!account.me?.staff && account.me?.plusCourses !== true,
        /** Every course this account can open, with how far it got, in the catalogue's order. */
        open() {
            return learning.catalogue
                .filter((entry) => courseVisible(entry, account.me, account.config, account.loaded) && learning.courses[entry.id])
                .map((entry) => ({ entry, summary: courseSummary(learning.courses[entry.id], learning.progress) }));
        },
        started() {
            return this.open.filter((c) => c.summary.started);
        },
        /**
         * The groups, each a cell for every course in the catalogue's order: an open one (`open`: with how far it got, the
         * started ones too, so a group is whole) or one to come (`open` null), which becomes an open one when it opens.
         */
        groups() {
            const byId = new Map(this.open.map((c) => [c.entry.id, c]));
            return GROUPS.map((g) => ({
                ...g,
                cells: learning.catalogue.filter((e) => (e.group ?? 'skills') === g.key).map((entry) => ({ entry, open: byId.get(entry.id) ?? null })),
            })).filter((g) => g.cells.length);
        },
    },
    methods: {
        navigate,
        href: (c) => '/learn/c/' + c.entry.id,
        colour(entry) {
            return this.paint(PART_COLORS[((entry.colour ?? 4) - 1) % PART_COLORS.length]);
        },
        /** "Vibrato, 17 lessons" or "Vibrato, 3 of 17 lessons". */
        label(c) {
            const state = c.summary.started ? t('courses.progress', { done: c.summary.done, total: c.summary.total }) : t('courses.lessons', { count: c.summary.total });
            return `${localized(c.entry.title)}, ${state}`;
        },
    },
};
