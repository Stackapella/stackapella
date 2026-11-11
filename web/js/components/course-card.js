// A Plus course as a card (Hillel, 2026-10-28: the courses list was a lean placeholder to redesign;
// docs/plus-courses-build-plan.md, 2.2). Home's Courses band and More courses on the Learn hub use it.
// A course this account can open is the whole card, one link: its colour, how long it is, its line,
// how far you've got, and the way in in the accent with an arrow (Continue, Start, or Try the first
// lesson free). A course still to come is quieter: no edge, no link, its name and line in muted words
// and Coming soon. No buttons on either, so the screen's one primary stays its own.

import { inLanguage, localized, t } from '../i18n.js';
import { navigate } from '../router.js';
import { PART_COLORS } from '../theme.js';
import { Icon } from './icon.js';

export const CourseCard = {
    components: { Icon },
    // `summary`: courseSummary() for a course this account can open, or null for one still to come.
    props: { entry: { type: Object, required: true }, summary: { type: Object, default: null }, preview: Boolean },
    setup: () => ({ localized }),
    template: `
        <a v-if="summary" :href="href" class="card-interactive group flex h-full flex-col gap-2 p-4" :aria-label="localized(entry.title) + ', ' + action" @click.prevent="navigate(href)">
            <span class="flex items-center gap-2">
                <span class="size-2.5 shrink-0 rounded-full" :style="{ backgroundColor: colour }" aria-hidden="true"></span>
                <span class="tabular-nums text-xs font-bold uppercase tracking-[0.12em]" :style="{ color: paintInk(colour) }">{{ t('courses.lessons', { count: summary.total }) }}</span>
                <span v-if="preview" class="chip ms-auto">{{ t('courses.preview') }}</span>
                <!-- Not yet in the person's language (docs/languages-plan.md): its English, said plainly, never half translated. -->
                <span v-if="english" class="chip" :class="preview ? '' : 'ms-auto'" data-in-english>{{ t('courses.inEnglish') }}</span>
            </span>
            <span class="text-lg font-extrabold leading-snug tracking-tight text-cream" translate="no" :lang="english ? 'en' : null">{{ localized(entry.title) }}</span>
            <span class="line-clamp-2 text-sm text-muted" translate="no" :lang="english ? 'en' : null">{{ localized(entry.line) }}</span>
            <span v-if="summary.started" class="mt-1 flex items-center gap-3" data-course-progress>
                <span class="h-1.5 flex-1 overflow-hidden rounded-full bg-raised" aria-hidden="true"><span class="block h-full rounded-full" :style="{ width: percent + '%', backgroundColor: colour }"></span></span>
                <span class="tabular-nums text-xs text-muted">{{ t('courses.progress', { done: summary.done, total: summary.total }) }}</span>
            </span>
            <span class="mt-auto flex items-center gap-1.5 pt-2 text-sm font-semibold text-accent-strong" data-course-status>{{ action }}<Icon name="arrow" :size="16" class="transition group-hover:translate-x-0.5 rtl:-scale-x-100 rtl:group-hover:-translate-x-0.5" /></span>
        </a>
        <div v-else class="flex h-full flex-col gap-1.5 rounded-[var(--radius-card)] p-4 [background:var(--fill-2)]" aria-disabled="true">
            <span class="font-bold text-cream/80" translate="no">{{ localized(entry.title) }}</span>
            <span class="line-clamp-2 text-sm text-muted" translate="no">{{ localized(entry.line) }}</span>
            <span class="mt-auto pt-1 text-xs font-semibold text-muted" data-course-status>{{ t('courses.soon') }}</span>
        </div>`,
    computed: {
        /** The course isn't in the person's language yet: shown in English with a chip. */
        english() {
            return !inLanguage(this.entry.title);
        },
        href() {
            return '/learn/c/' + this.entry.id;
        },
        colour() {
            return this.paint(PART_COLORS[((this.entry.colour ?? 4) - 1) % PART_COLORS.length]);
        },
        percent() {
            return this.summary.total ? Math.round((this.summary.done / this.summary.total) * 100) : 0;
        },
        /** The way in: Continue once started, else Start (every course is free: Hillel, 2026-11-06). */
        action() {
            return this.summary.started ? t('courses.continue') : t('courses.start');
        },
    },
    methods: { navigate },
};
