// A Plus course's page at /learn/c/<id> (docs/plus-courses-build-plan.md, 2.2): the free hub's
// layout for one course, Up next and its course map, in the course's colour, then that no app
// replaces a teacher, and Restart this course. Once a few lessons are done, Sharpen up replays the
// weakest (js/sharpen.js). The week, the level and the workout stay on /learn.
// A course this account can't see (everyone but staff, until launch) reads as not here.
// A song course (docs/song-courses-plan.md) shows here too, at /learn/s/<song id>: the same page for
// one song, opened from that song's sheet on Home (a `?part=` the unit to start in), with no Sharpen up.

import { LoadingBlock } from '../components/screen-loading.js';
import { localized, t } from '../i18n.js';
import { goBack, navigate } from '../router.js';
import { account } from '../stores/account.js';
import { CourseWall } from '../components/course-wall.js';
import { game } from '../stores/appearance.js';
import { learning, loadLearning, loadPlusCourses, loadSongCourse, restartPlusCourse } from '../stores/learning.js';
import { courseSummary, courseVisible, lessonAccess, nextInCourse, songCourseVisible } from '../courses.js';
import { lessonMinutes } from '../results.js';
import { CourseMap } from '../components/course-map.js';
import { CoursePart } from '../components/course-part.js';
import { confirmAction } from '../components/confirm.js';
import { toast } from '../components/ui.js';
import { Icon } from '../components/icon.js';
import { AppScreen } from '../components/page.js';
import { PART_COLORS } from '../theme.js';
import { sk } from '../skin.js';
import { publicSiteUrl } from '../hosts.js';
import { endSharpen, sharpenPicks, startSharpen } from '../sharpen.js';
import { COURSE as READING, readingLevel } from '../reading-lines.js';

export const PlusCourseView = {
    components: { LoadingBlock, AppScreen, CourseMap, CoursePart, CourseWall, Icon },
    props: { id: { type: String, required: true }, song: Boolean }, // song: id is a song's, for its song course
    setup: () => ({ learning, localized, lessonMinutes }),
    data: () => ({ songLooked: false, part: new URLSearchParams(location.search).get('part') }),
    template: `
        <AppScreen :title="course ? localized(song ? course.title : entry.title) : t('courses.title')" :eyebrow="song ? t('songCourse.eyebrow') : t('courses.eyebrow')" :back="song ? '/' : '/learn'" :back-label="t('home.back')" title-is-content measure="">
            <LoadingBlock v-if="!learning.loaded || (!(song ? songLooked : learning.coursesLoaded) && !course)" :failed="learning.failed" @retry="retryLoad" />
            <p v-else-if="!course && song" class="text-cream">{{ t('songCourse.notHere') }} <a href="/" class="link" @click.prevent="navigate('/')">{{ t('songCourse.backToSong') }}</a></p>
            <p v-else-if="!course" class="text-cream">{{ t('courses.notHere') }} <a href="/learn" class="link" @click.prevent="navigate('/learn')">{{ t('course.backToCourse') }}</a></p>
            <template v-else>
                <!-- Your part (course-parts.js): only a course with parts; under the title, before what's next. -->
                <CoursePart :course="course" class="mb-4" />
                <p v-if="course.headphones === 'needed'" class="mb-4 flex items-center gap-2 text-sm text-muted" data-best-headphones><Icon name="headphones" :size="16" class="shrink-0" />{{ t('course.bestWithHeadphones') }}</p>
                <section v-if="next" class="card p-6" :aria-label="t('course.hub.upNext')" data-up-next>
                    <p class="tabular-nums text-xs font-bold uppercase tracking-[0.14em]" :style="{ color: paintInk(colour) }">{{ t('course.hub.upNextUnit', { number: next.unitIndex + 1 }) }}</p>
                    <h2 class="mt-1 text-3xl font-extrabold tracking-tight text-cream" translate="no">{{ localized(next.lesson.title) }}</h2>
                    <p v-if="line" class="mt-1 max-w-md text-muted" translate="no">{{ localized(line) }}</p>
                    <div class="mt-5 flex flex-wrap items-center gap-4">
                        <button v-if="!needsAccount" type="button" class="btn-primary btn-lg" @click="navigate('/learn/' + next.lesson.id)"><Icon name="play" :size="16" /> {{ t('course.startLesson') }}</button>
                        <!-- Signed out past lesson 1 (Hillel, 2026-11-06): a free account to keep going, never a price. -->
                        <CourseWall v-else compact place="page" :course="course" :lesson-id="next.lesson.id" class="w-full" />
                        <span v-if="!needsAccount" class="tabular-nums text-sm text-muted">{{ game() ? t('course.hub.meta', { minutes: lessonMinutes(next.lesson) }) : t('course.aboutMin', { minutes: lessonMinutes(next.lesson) }) }}</span>
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
                <!-- Sight-singing's Fresh lines (docs/sight-singing-plan.md, slice 5): endless new lines at the level reached, once started. -->
                <section v-if="freshLines" class="card mt-4 flex flex-wrap items-center justify-between gap-4 p-6" :aria-label="t('readLines.title')" data-fresh-lines>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-bold uppercase tracking-[0.14em]" :style="{ color: paintInk(colour) }">{{ t('readLines.eyebrow') }}</p>
                        <h2 class="mt-1 text-xl font-bold text-cream">{{ t('readLines.title') }}</h2>
                        <p class="mt-1 max-w-md text-sm text-muted">{{ t('readLines.line') }}</p>
                    </div>
                    <button type="button" class="btn-secondary" data-fresh-lines-start @click="navigate('/learn/c/' + READING + '/lines')">{{ t('readLines.start') }}</button>
                </section>
                <!-- The care line once (the finish-line audit: three on one screen). A course with its own safety note says the
                     teacher line in that note's box; others keep the general line here. -->
                <p class="mt-4 text-sm text-muted" translate="no">{{ song ? t('songCourse.line') : localized(entry.line) }} <template v-if="!entry.safety">{{ t('courses.notTeacher') }}</template></p>
                <!-- The course's own page on the site: what it covers, unit by unit, who wrote and checked it. -->
                <a v-if="aboutUrl" :href="aboutUrl" target="_blank" rel="noopener" class="link mt-2 inline-flex min-h-6 items-center gap-1 text-sm" data-course-about>{{ t('courses.about') }} <Icon name="arrow" :size="14" /></a>
                <!-- A course's own safety note (its catalogue entry's safety), where its singing needs one. -->
                <p v-if="entry.safety" class="mt-2 rounded-2xl border border-line p-3 text-sm text-cream" role="note" translate="no" data-course-safety>{{ localized(entry.safety) }} {{ t('courses.teacher') }}</p>
                <CourseMap class="mt-6" :course="course" :focus-unit="focusUnit" :up-next="next?.lesson.id ?? null" />
                <div v-if="summary.started" class="mt-8 flex justify-center">
                    <button type="button" class="btn-ghost btn-sm" @click="restart">{{ t('courses.restart') }}</button>
                </div>
            </template>
        </AppScreen>`,
    computed: {
        /** The course's id, from the address's id or slug. */
        courseId() {
            return this.entry?.id ?? this.id;
        },
        entry() {
            // A song course: by its song's id, in the song courses' own catalogue.
            if (this.song) return learning.songs?.find((c) => c.template === this.id) ?? null;
            // By id, or by the course's slug (the site's links, and links already shared, use either).
            return learning.catalogue.find((c) => c.id === this.id || c.slug === this.id) ?? null;
        },
        /** The course's data, when this account can see it. */
        course() {
            const visible = this.song ? songCourseVisible(this.entry, account.me) : courseVisible(this.entry, account.me, account.config);
            return this.entry && visible ? learning.courses[this.courseId] ?? null : null;
        },
        /** A song course's unit for the part picked on the song's page, when it has one. */
        focusUnit() {
            const id = this.song && this.part ? `${this.courseId}-${this.part}` : null;
            return id && this.course?.units.some((u) => u.id === id) ? id : null;
        },
        next() {
            if (!this.course) return null;
            const found = nextInCourse(this.course, learning.progress, this.focusUnit);
            return found ? { ...found, unitIndex: this.course.units.indexOf(found.unit) } : null;
        },
        /** Up next is past lesson 1, signed out: a free account to keep going. */
        needsAccount() {
            return !!this.next && lessonAccess(this.course, this.next.lesson.id, learning.progress, { signedIn: !!account.me, staff: !!account.me?.staff }).needsAccount;
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
        READING: () => READING,
        /** Sight-singing's Fresh lines: once the course is started (and never in the first week). */
        freshLines() {
            return this.course?.id === READING && !this.song && !!readingLevel(learning.progress);
        },
        /** Sharpen up's lessons (js/sharpen.js), once enough are done; none until then. */
        sharpen() {
            return this.course && !this.song ? sharpenPicks(this.course, learning.progress, { staff: !!account.me?.staff }) : [];
        },
        colour() {
            return this.paint(PART_COLORS[((this.entry?.colour ?? 4) - 1) % PART_COLORS.length]);
        },
    },
    created() {
        endSharpen(); // back on the course page: any session in this tab is over
        loadLearning();
        if (this.song) {
            loadSongCourse({ template: this.id })
                .catch((error) => console.error('The song course could not load', error))
                .finally(() => (this.songLooked = true));
        } else loadPlusCourses();
    },
    methods: {
        /** Try again after the course's data didn't load (LoadingBlock's Retry). */
        retryLoad() {
            loadLearning();
        },
        goBack,
        navigate,
        game, // serious mode shows no stars
        sk,
        /** Sharpen up: its lessons queued in this tab, then the first, at its scored part. */
        sharpenUp() {
            const ids = this.sharpen.map((pick) => pick.lesson.id);
            startSharpen(this.courseId, ids);
            navigate('/learn/' + ids[0]);
        },
        /** Restart this course: a styled confirm naming the course, then only its lessons go. */
        async restart() {
            const name = localized(this.song ? this.course.title : this.entry.title);
            if (!(await confirmAction({ title: t('courses.restartTitle', { course: name }), body: sk('courses.restartBodySerious', 'courses.restartBody', { course: name }), action: t('courses.restartAction', { course: name }) }))) return;
            try {
                await restartPlusCourse(this.courseId);
                toast(t('courses.restarted', { course: name }));
            } catch (error) {
                console.error('The course could not restart', error);
                toast(t('courses.restartFailed'), { tone: 'error' });
            }
        },
    },
};
