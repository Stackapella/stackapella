// The course map (docs/vocal-coaching-plan.md, "The course map"; Hillel's 62.png, then 98.png): the
// units as collapsible cards, the one you're in open. Each has its eyebrow in the unit's colour, its
// title, its bar the card's width with its count beside it, and its path of lessons as glass gems in
// the unit's colour (done ticked, the one now with a play ring in your accent, the rest hollow, the
// challenge a star), and the picked lesson's card (its number, title, line, minutes and exercises,
// and Start lesson, Sing it again or why it's locked), the one you're on at first. A unit still
// closed says how many stars open it. The library and Sing are never behind it. Past a singer's first week, a unit's
// boss (docs/boss-round-plan.md) is one row under its path: Boss in game mode, an optional Unit review in serious.

import { localized, t } from '../i18n.js';
import { navigate } from '../router.js';
import { maxStars, nextLesson, starsIn, starsToOpen, unitOpen } from '../course.js';
import { lessonMinutes } from '../results.js';
import { account } from '../stores/account.js';
import { learning, loadLearning } from '../stores/learning.js';
import { CourseWall } from './course-wall.js';
import { PART_COLORS } from '../theme.js';
import { Icon } from './icon.js';
import { guestOpen, lessonAccess, nextInCourse, openView } from '../courses.js';
import { lazyComponent } from '../game/modules.js';

const StageMap = lazyComponent('stageMap', 'StageMap'); // game mode's only: loaded with game mode
import { effect } from '../game/effects.js';
import { BOSS_PASS, bossBeaten, bossOpen, bossResult, bossShown } from '../boss.js';
import { formatNumber } from '../i18n.js';
import { game } from '../skin.js';

export const CourseMap = {
    components: { CourseWall, Icon, StageMap },
    // A Plus course's data (js/courses.js); the free course when none.
    // focusUnit: a song course's part, picked on the song page. upNext: the lesson the page's Up next already starts.
    props: { course: { type: Object, default: null }, focusUnit: { type: String, default: null }, upNext: { type: String, default: null } },
    data: () => ({ expanded: null, picked: {} }),
    setup: () => ({ learning, localized, lessonMinutes, effect, game, formatNumber, BOSS_PASS }),
    template: `
        <ol v-if="units.length" class="space-y-4" data-course-map>
            <li v-for="(unit, u) in units" :key="unit.id" class="card overflow-hidden" :data-unit="unit.id">
                <!-- Hillel's 98.png: the unit's eyebrow and title, then its bar the card's width with the count beside it; a chevron opens and closes the unit. -->
                <button type="button" class="block w-full p-5 text-start" :aria-expanded="isOpen(u) ? 'true' : 'false'" data-unit-head @click="toggle(u)">
                    <span class="flex items-start justify-between gap-3">
                        <span class="min-w-0">
                            <span class="block tabular-nums text-xs font-bold uppercase tracking-[0.14em]" :style="{ color: paintInk(colorOf(unit)) }">{{ t('course.unit', { number: u + 1 }) }}</span>
                            <span class="block text-xl font-extrabold tracking-tight" :class="open(u) ? 'text-cream' : 'text-muted'" translate="no">{{ localized(unit.title) }}</span>
                        </span>
                        <span class="flex shrink-0 items-center gap-2">
                            <span v-if="bossOf(unit)?.beaten" class="chip inline-flex items-center gap-1" data-unit-mastered><Icon name="shield" :size="14" /> {{ t('boss.map.mastered') }}</span>
                            <Icon name="chevron" :size="18" class="text-muted motion-safe:transition" :class="isOpen(u) ? 'rotate-180' : ''" />
                        </span>
                    </span>
                    <span class="mt-3 flex items-center gap-3">
                        <span class="block h-1.5 flex-1 overflow-hidden rounded-full bg-raised"><span class="block h-full rounded-full bg-accent" :style="{ width: barShare(u) * 100 + '%' }"></span></span>
                        <span class="shrink-0 tabular-nums text-xs text-muted">{{ game() ? (open(u) ? t('course.mapLessonsStars', { done: lessonsDone(unit), total: unit.lessons.length, stars: starsIn(unit), max: maxStars(unit) }) : t('course.mapToOpen', { stars: starsIn(units[u - 1]), need: starsToOpen(units[u - 1]) })) : open(u) ? t('course.mapLessons', { done: lessonsDone(unit), total: unit.lessons.length }) : t('course.mapOpensAfter', { number: u }) }}</span>
                    </span>
                </button>
                <div v-if="isOpen(u)" class="border-t border-edge px-5 pb-5 pt-6">
                    <!-- Game mode's stage select (docs/game-elements-plan.md): the open unit as a map -->
                    <StageMap v-if="effect('stages')" :nodes="mapNodes(u, unit)" :colour="colorOf(unit)" @pick="(id) => pick(u, id)" />
                    <!-- More than five lessons (a song course's seven): on a phone the path scrolls sideways, each lesson a column wide
                         enough for its name, instead of squeezing the bubbles over each other (the finish-line audit, 2026-11-13). -->
                    <ol v-else class="flex items-start" :class="unit.lessons.length > 5 ? 'max-md:-mx-5 max-md:snap-x max-md:overflow-x-auto max-md:px-5 max-md:pb-1 max-md:[scrollbar-width:none]' : ''" :aria-label="t('home.lead.path')" :data-lesson-path="u">
                        <li v-for="(lesson, i) in unit.lessons" :key="lesson.id" class="relative flex min-w-0 flex-1 flex-col items-center gap-2 text-center" :class="unit.lessons.length > 5 ? 'max-md:w-20 max-md:flex-none max-md:snap-center' : ''" :data-path-lesson="lesson.id">
                            <span v-if="i > 0" class="absolute end-1/2 top-7 h-0.5 w-full -translate-y-1/2" :style="{ background: stars(unit.lessons[i - 1]) ? colorOf(unit) : 'var(--color-line)' }" aria-hidden="true"></span>
                            <button type="button" class="relative z-[1] grid size-14 place-items-center transition disabled:cursor-not-allowed" :class="[nodeClass(unit, lesson), pickedId(u) === lesson.id ? pickedClass(lesson) : '']" :style="nodeStyle(unit, lesson)" :disabled="locked(u, unit, lesson)"
                                :aria-label="game() ? t('course.lessonLabel', { title: localized(lesson.title), stars: stars(lesson) }) : stars(lesson) ? t('course.lessonDone', { title: localized(lesson.title) }) : localized(lesson.title)" :aria-current="lesson.id === current ? 'step' : null" :aria-pressed="pickedId(u) === lesson.id ? 'true' : 'false'" :data-node="stars(lesson) ? 'done' : lesson.id === current ? 'now' : locked(u, unit, lesson) ? 'locked' : lesson.challenge ? 'challenge' : 'next'" @click="pick(u, lesson.id)">
                                <Icon :name="stars(lesson) ? 'check' : lesson.id === current ? 'play' : lesson.challenge && game() ? 'starFilled' : 'note'" :size="20" :class="lesson.challenge && !stars(lesson) && lesson.id !== current && game() ? '-rotate-45' : ''" />
                            </button>
                            <span v-if="game()" class="flex gap-1" aria-hidden="true"><span v-for="s in 3" :key="s" class="size-1.5 rounded-full" :style="{ background: s <= stars(lesson) ? colorOf(unit) : 'var(--color-line)' }"></span></span>
                            <span class="line-clamp-2 px-1 text-xs" :class="pickedId(u) === lesson.id ? 'font-bold text-cream' : 'text-muted'" translate="no">{{ localized(lesson.title) }}</span>
                        </li>
                    </ol>
                    <!-- The picked lesson's card (Hillel's 98.png), the one you're on at first, so Start lesson stays one tap from the map. -->
                    <div v-if="pickedLesson(u)" class="mt-6 rounded-2xl border border-line bg-raised/40 p-5 outline-none" tabindex="-1" role="group" :aria-label="localized(pickedLesson(u).title)" data-picked-lesson>
                        <p class="tabular-nums text-xs font-bold uppercase tracking-[0.14em] text-muted">{{ t('course.lessonNumber', { number: lessonIndex(u) + 1 }) }}</p>
                        <p class="mt-1 text-xl font-extrabold tracking-tight text-cream" translate="no">{{ localized(pickedLesson(u).title) }}</p>
                        <p v-if="lineOf(pickedLesson(u))" class="mt-2 text-sm text-muted" translate="no">{{ localized(lineOf(pickedLesson(u))) }}</p>
                        <p class="mt-3 tabular-nums text-xs text-muted">{{ t('course.aboutMin', { minutes: lessonMinutes(pickedLesson(u)) }) }} · {{ t('course.exercises', { count: exercisesIn(pickedLesson(u)) }) }}</p>
                        <p v-if="!open(u)" class="mt-4 text-sm text-muted" data-lesson-locked>{{ game() ? t('course.locked') : t('course.lockedSerious') }}</p>
                        <!-- Signed out past a course's first lesson (Hillel, 2026-11-06): a free account, never a price. -->
                        <CourseWall v-else-if="needsAccount(pickedLesson(u))" compact place="map" :course="course" :lesson-id="pickedLesson(u).id" class="mt-4" />
                        <!-- Up next above already starts this one: no second Start lesson. -->
                        <button v-else-if="pickedLesson(u).id !== upNext" type="button" class="btn-primary mt-4 w-full" @click="navigate('/learn/' + pickedLesson(u).id)">{{ stars(pickedLesson(u)) ? t('course.replay') : t('course.startLesson') }}</button>
                    </div>
                    <!-- The unit's boss (docs/boss-round-plan.md): optional, past week one; locked until the challenge has stars. -->
                    <div v-if="bossOf(unit) && open(u)" class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-3 rounded-2xl border border-line p-4" data-ui="boss-row" data-boss-row :data-open="bossOf(unit).open ? '' : null">
                        <span v-if="effect('boss')" class="ge-boss-gem is-small" :data-crack="bossOf(unit).beaten ? 4 : 0" aria-hidden="true"></span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-xs font-bold uppercase tracking-[0.14em] text-muted">{{ effect('boss') ? t('boss.map.boss') : t('boss.map.review') }} · {{ t('boss.map.optional') }}</span>
                            <span class="block font-bold text-cream" translate="no">{{ localized(unit.boss.title) }}</span>
                            <span class="block text-sm text-muted" data-boss-status>{{ !bossOf(unit).open ? (game() ? t('boss.locked') : t('boss.lockedSerious')) : bossOf(unit).beaten ? (effect('boss') ? t('boss.bestBeaten', { percent: formatNumber(bossOf(unit).best) }) : t('boss.review.bestPassed', { percent: formatNumber(bossOf(unit).best) })) : bossOf(unit).best !== null ? t('boss.best', { percent: formatNumber(bossOf(unit).best) }) : effect('boss') ? t('boss.toBeat', { percent: formatNumber(BOSS_PASS) }) : t('boss.review.toPass', { percent: formatNumber(BOSS_PASS) }) }}</span>
                        </span>
                        <button v-if="bossOf(unit).open" type="button" class="btn-secondary max-sm:w-full" data-boss-go @click="navigate('/learn/' + unit.boss.id)">{{ bossOf(unit).beaten ? t('boss.playAgain') : effect('boss') ? t('boss.takeItOn') : t('boss.review.start') }}</button>
                    </div>
                </div>
            </li>
        </ol>`,
    mounted() {
        this.centrePaths();
    },
    updated() {
        this.centrePaths();
    },
    created() {
        loadLearning();
    },
    computed: {
        staff: () => !!account.me?.staff,
        units: (vm) => (vm.course ? vm.course.units : learning.course?.units ?? []),
        /** The progress the map reads: a Plus course's own lessons, or the free course's. */
        progress: (vm) => (vm.course ? openView(learning.progress, vm.course) : learning.progress), // a song course's units all open
        /** The lesson to do next, marked on the map with a play ring. */
        current() {
            if (this.course) return nextInCourse(this.course, learning.progress, this.focusUnit)?.lesson.id ?? null;
            return learning.course ? nextLesson(learning.course.units, learning.progress)?.lesson.id ?? null : null;
        },
        /** The unit you're in: open at first. */
        currentUnit() {
            const units = this.units;
            const index = units.findIndex((unit) => unit.lessons.some((l) => l.id === this.current));
            return index >= 0 ? index : Math.max(0, units.length - 1);
        },
    },
    methods: {
        /** A path that scrolls (more than five lessons, on a phone): the lesson you're on in the middle of it, once. */
        centrePaths() {
            for (const ol of this.$el?.querySelectorAll?.('[data-lesson-path]') ?? []) {
                if (ol.dataset.centred || ol.scrollWidth <= ol.clientWidth) continue;
                const now = ol.querySelector('[aria-current]')?.closest('li');
                if (!now) continue;
                ol.scrollLeft = now.offsetLeft - (ol.clientWidth - now.offsetWidth) / 2;
                ol.dataset.centred = '1';
            }
        },
        navigate,
        maxStars,
        starsToOpen,
        /** The unit's boss as the map shows it (`{ open, best, beaten }`), or null: the free course's, past week one. */
        bossOf(unit) {
            if (this.course || !bossShown(unit, learning.progress)) return null;
            return { open: bossOpen(unit, learning.progress), best: bossResult(learning.progress, unit.id)?.best ?? null, beaten: bossBeaten(learning.progress, unit.id) };
        },
        /** Serious mode's measure: the unit's lessons done (no stars). */
        lessonsDone(unit) {
            return unit.lessons.filter((lesson) => this.progress.lessons[lesson.id]?.stars).length;
        },
        starsIn(unit) {
            return starsIn(unit, this.progress);
        },
        open(u) {
            // Staff preview every unit of a Plus course (js/courses.js lessonAccess).
            return (!!this.course && this.staff) || unitOpen(this.units, u, this.progress);
        },
        stars(lesson) {
            return this.progress.lessons[lesson.id]?.stars ?? 0;
        },
        /** A lesson that can't be started now: its unit closed (every lesson is free: Hillel, 2026-11-06). */
        locked(u) {
            return !this.open(u);
        },
        /** Signed out, past the course's first lesson (any course, the free one too): a free account to keep going. */
        needsAccount(lesson) {
            if (!lesson || account.me) return false;
            if (this.course) return lessonAccess(this.course, lesson.id, learning.progress, { signedIn: false, staff: this.staff }).needsAccount;
            return !!learning.course && !guestOpen(learning.course, lesson.id);
        },
        isOpen(u) {
            return this.expanded === null ? u === this.currentUnit : this.expanded === u;
        },
        toggle(u) {
            this.expanded = this.isOpen(u) ? -1 : u;
        },
        /** A lesson picked on the path: its card shows it, and the focus moves there (keyboard and screen readers). */
        pick(u, id) {
            this.picked = { ...this.picked, [u]: id };
            this.$nextTick(() => this.$el.querySelector(`[data-unit="${this.units[u].id}"] [data-picked-lesson]`)?.focus());
        },
        lessonIndex(u) {
            return this.units[u].lessons.findIndex((l) => l.id === this.pickedId(u));
        },
        /** The steps you sing in (every step but the Learn ones), of the lesson as written: the map never draws an experiment's arm (lessonFor), the lesson does when it opens. */
        exercisesIn: (lesson) => lesson.steps.filter((s) => s.kind !== 'learn').length,
        pickedId(u) {
            const unit = this.units[u];
            return this.picked[u] ?? (unit.lessons.some((l) => l.id === this.current) ? this.current : unit.lessons[0]?.id);
        },
        pickedLesson(u) {
            return this.units[u].lessons.find((l) => l.id === this.pickedId(u)) ?? null;
        },
        /** A lesson's one line: its first Learn step's headline. */
        lineOf: (lesson) => lesson.steps.find((s) => s.headline)?.headline ?? null,
        /** The unit's bar: its stars, or the stars toward opening it. */
        barShare(u) {
            const unit = this.units[u];
            if (this.open(u)) return Math.min(1, this.starsIn(unit) / this.maxStars(unit));
            const before = this.units[u - 1];
            return Math.min(1, this.starsIn(before) / starsToOpen(before));
        },
        /** The unit's lessons for game mode's map: the same stars, place, pick and locks as the path. */
        mapNodes(u, unit) {
            return unit.lessons.map((lesson) => ({ id: lesson.id, title: lesson.title, stars: this.stars(lesson), current: lesson.id === this.current, picked: this.pickedId(u) === lesson.id, locked: this.locked(u, unit, lesson), challenge: !!lesson.challenge }));
        },
        colorOf(unit) {
            return this.paint(PART_COLORS[((unit.colour ?? 4) - 1) % PART_COLORS.length]);
        },
        nodeClass(unit, lesson) {
            // Serious mode: bubbles (docs/bubble-circles-plan.md), done in the unit's colour, the one now filled in the accent
            // with its halo, the rest clear pearls (the challenge with a cream rim; a locked one faded, being disabled).
            if (!game()) {
                if (this.stars(lesson)) return 'bubble bubble-lg';
                if (lesson.id === this.current) return 'bubble bubble-lg bubble-now';
                return lesson.challenge ? 'bubble bubble-lg bubble-clear bubble-challenge' : 'bubble bubble-lg bubble-clear';
            }
            const shape = lesson.challenge && !this.stars(lesson) && lesson.id !== this.current ? 'rotate-45 rounded-xl' : 'rounded-full';
            if (this.stars(lesson)) return `gem ${shape}`;
            if (lesson.id === this.current) return `${shape} border-2 border-accent bg-ink text-cream`;
            return `${shape} border-2 border-line bg-ink text-muted`;
        },
        nodeStyle(unit, lesson) {
            if (this.stars(lesson)) return game() ? { '--gem': this.colorOf(unit), '--gem-ink': this.paintText(this.colorOf(unit)) } : { '--bubble': this.colorOf(unit), '--bubble-ink': this.paintText(this.colorOf(unit)) };
            if (lesson.id === this.current && game()) return { boxShadow: '0 0 0 5px color-mix(in srgb, var(--color-accent) 25%, transparent)' };
            return null;
        },
        /** The picked lesson: a ring in game mode; on a bubble an outline (a ring is a box-shadow, which a bubble draws its own), outside the halo. */
        pickedClass(lesson) {
            if (game()) return 'ring-2 ring-cream ring-offset-2 ring-offset-panel';
            return lesson.id === this.current ? 'outline-2 outline-offset-[9px] outline-cream' : 'outline-2 outline-offset-[3px] outline-cream';
        },
    },
};
