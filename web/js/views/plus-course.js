// A Plus course's page at /learn/c/<id> (docs/plus-courses-build-plan.md, 2.2): the free hub's
// layout for one course, Up next and its course map, in the course's colour, then that no app
// replaces a teacher, and Restart this course. Once a few lessons are done, Sharpen up replays the
// weakest (js/sharpen.js). The week, the level and the workout stay on /learn.
// A course this account can't see (everyone but staff, until launch) reads as not here.

import { localized, t } from '../i18n.js';
import { navigate } from '../router.js';
import { account } from '../stores/account.js';
import { game } from '../stores/appearance.js';
import { learning, loadLearning, loadPlusCourses, restartPlusCourse } from '../stores/learning.js';
import { courseSummary, courseVisible, lessonAccess, nextInCourse } from '../courses.js';
import { lessonMinutes } from '../results.js';
import { CourseMap } from '../components/course-map.js';
import { confirmAction } from '../components/confirm.js';
import { toast } from '../components/ui.js';
import { Icon } from '../components/icon.js';
import { PART_COLORS } from '../theme.js';
import { sk } from '../skin.js';
import { publicSiteUrl } from '../hosts.js';
import { endSharpen, sharpenPicks, startSharpen } from '../sharpen.js';

export const PlusCourseView = {
    components: { CourseMap, Icon },
    props: { id: { type: String, required: true } },
    setup: () => ({ learning, localized, lessonMinutes }),
    template: `
        <div class="mx-auto min-h-dvh max-w-4xl px-4 pb-16 pt-[max(1rem,env(safe-area-inset-top))] md:px-8">
            <header class="flex items-center gap-3 py-4">
                <button type="button" class="glass-bubble grid size-11 shrink-0 place-items-center rounded-full text-cream" :aria-label="t('home.back')" @click="navigate('/learn')"><Icon name="back" :size="20" /></button>
                <div class="min-w-0 flex-1">
                    <p class="text-sm text-muted">{{ t('courses.eyebrow') }}</p>
                    <h1 class="truncate text-xl font-bold text-cream" translate="no">{{ course ? localized(entry.title) : t('courses.title') }}</h1>
                </div>
            </header>
            <p v-if="!learning.loaded || !learning.coursesLoaded" class="text-muted" role="status">{{ t('course.loading') }}</p>
            <p v-else-if="!course" class="text-cream">{{ t('courses.notHere') }} <a href="/learn" class="link" @click.prevent="navigate('/learn')">{{ t('course.backToCourse') }}</a></p>
            <template v-else>
                <section v-if="next" class="card p-6" :aria-label="t('course.hub.upNext')" data-up-next>
                    <p class="tabular-nums text-xs font-bold uppercase tracking-[0.14em]" :style="{ color: paintInk(colour) }">{{ t('course.hub.upNextUnit', { number: next.unitIndex + 1 }) }}</p>
                    <h2 class="mt-1 text-3xl font-extrabold tracking-tight text-cream" translate="no">{{ localized(next.lesson.title) }}</h2>
                    <p v-if="line" class="mt-1 max-w-md text-muted" translate="no">{{ localized(line) }}</p>
                    <div class="mt-5 flex flex-wrap items-center gap-4">
                        <button v-if="!nextLocked" type="button" class="btn-primary btn-lg" @click="navigate('/learn/' + next.lesson.id)"><Icon name="play" :size="16" /> {{ t('course.startLesson') }}</button>
                        <template v-else>
                            <p class="text-cream">{{ t('courses.plusOnly') }}</p>
                            <a href="/plus" class="btn-go" @click.prevent="navigate('/plus')">{{ t('export.seePlus') }}</a>
                        </template>
                        <span v-if="!nextLocked" class="tabular-nums text-sm text-muted">{{ game() ? t('course.hub.meta', { minutes: lessonMinutes(next.lesson) }) : t('course.aboutMin', { minutes: lessonMinutes(next.lesson) }) }}</span>
                    </div>
                </section>
                <!-- Sharpen up: the weakest lessons, straight to their scored part (game: their stars; serious: their best). -->
                <section v-if="sharpen.length" class="card mt-4 p-6" :aria-label="t('sharpen.title')" data-sharpen>
                    <p class="text-xs font-bold uppercase tracking-[0.14em]" :style="{ color: paintInk(colour) }">{{ t('sharpen.title') }}</p>
                    <h2 class="mt-1 text-xl font-bold text-cream">{{ sk('sharpen.headlineSerious', 'sharpen.headline', { count: sharpen.length }) }}</h2>
                    <p class="mt-1 max-w-md text-sm text-muted">{{ t('sharpen.line') }}</p>
                    <ul class="mt-4 space-y-2">
                        <li v-for="pick in sharpen" :key="pick.lesson.id" class="flex items-center justify-between gap-3 rounded-2xl border border-edge bg-panel px-4 py-3" data-sharpen-lesson>
                            <span class="min-w-0 truncate font-semibold text-cream" translate="no">{{ localized(pick.lesson.title) }}</span>
                            <span v-if="game()" class="flex shrink-0 gap-1" role="img" :aria-label="t('sharpen.stars', { title: localized(pick.lesson.title), stars: pick.kept.stars })"><span v-for="s in 3" :key="s" class="size-2 rounded-full" :style="{ background: s <= pick.kept.stars ? colour : 'var(--color-line)' }"></span></span>
                            <span v-else-if="pick.kept.bestPercent !== null" class="shrink-0 tabular-nums text-sm text-muted">{{ t('sharpen.best', { percent: pick.kept.bestPercent }) }}</span>
                        </li>
                    </ul>
                    <button type="button" class="btn-go mt-5" data-sharpen-start @click="sharpenUp">{{ t('sharpen.start') }}</button>
                </section>
                <p class="mt-4 text-sm text-muted" translate="no">{{ localized(entry.line) }} {{ t('courses.notTeacher') }}</p>
                <!-- The course's own page on the site: what it covers, unit by unit, who wrote and checked it. -->
                <a v-if="aboutUrl" :href="aboutUrl" target="_blank" rel="noopener" class="link mt-2 inline-flex items-center gap-1 text-sm" data-course-about>{{ t('courses.about') }} <Icon name="arrow" :size="14" /></a>
                <!-- A course's own safety note (its catalogue entry's safety), where its singing needs one. -->
                <p v-if="entry.safety" class="mt-2 rounded-2xl border border-line p-3 text-sm text-cream" role="note" translate="no" data-course-safety>{{ localized(entry.safety) }}</p>
                <CourseMap class="mt-6" :course="course" />
                <div v-if="summary.started" class="mt-8 flex justify-center">
                    <button type="button" class="btn-ghost btn-sm" @click="restart">{{ t('courses.restart') }}</button>
                </div>
            </template>
        </div>`,
    computed: {
        entry() {
            return learning.catalogue.find((c) => c.id === this.id) ?? null;
        },
        /** The course's data, when this account can see it. */
        course() {
            return this.entry && courseVisible(this.entry, account.me) ? learning.courses[this.id] ?? null : null;
        },
        next() {
            if (!this.course) return null;
            const found = nextInCourse(this.course, learning.progress);
            return found ? { ...found, unitIndex: this.course.units.indexOf(found.unit) } : null;
        },
        /** Up next is past the free lesson, on the free plan: the Plus moment instead of Start. */
        nextLocked() {
            return !!this.next && lessonAccess(this.course, this.next.lesson.id, learning.progress, { plus: account.me?.plan === 'plus', staff: !!account.me?.staff }).plusOnly;
        },
        line() {
            return this.next?.lesson.steps.find((s) => s.headline)?.headline ?? null;
        },
        /** The site's page for this course (/courses/<slug>), when the catalogue names one. */
        aboutUrl() {
            return this.entry?.slug ? new URL(`/courses/${this.entry.slug}`, new URL(publicSiteUrl(), location.href)).href : null;
        },
        summary() {
            return courseSummary(this.course, learning.progress);
        },
        /** Sharpen up's lessons (js/sharpen.js), once enough are done; none until then. */
        sharpen() {
            return this.course ? sharpenPicks(this.course, learning.progress, { plus: account.me?.plan === 'plus', staff: !!account.me?.staff }) : [];
        },
        colour() {
            return this.paint(PART_COLORS[((this.entry?.colour ?? 4) - 1) % PART_COLORS.length]);
        },
    },
    created() {
        endSharpen(); // back on the course page: any session in this tab is over
        loadLearning();
        loadPlusCourses();
    },
    methods: {
        navigate,
        game, // serious mode shows no stars
        sk,
        /** Sharpen up: its lessons queued in this tab, then the first, at its scored part. */
        sharpenUp() {
            const ids = this.sharpen.map((pick) => pick.lesson.id);
            startSharpen(this.id, ids);
            navigate('/learn/' + ids[0]);
        },
        /** Restart this course: a styled confirm naming the course, then only its lessons go. */
        async restart() {
            const name = localized(this.entry.title);
            if (!(await confirmAction({ title: t('courses.restartTitle', { course: name }), body: sk('courses.restartBodySerious', 'courses.restartBody', { course: name }), action: t('courses.restartAction', { course: name }) }))) return;
            try {
                await restartPlusCourse(this.id);
                toast(t('courses.restarted', { course: name }));
            } catch (error) {
                console.error('The course could not restart', error);
                toast(t('courses.restartFailed'), { tone: 'error' });
            }
        },
    },
};
