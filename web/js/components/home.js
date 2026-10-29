// The home's pieces (docs/decisions.md, "the home page", direction C: the stack). The course leads
// as the first band; the bands under it each start with a small bar in a part colour, like the
// logo's stacked parts. The course band carries its unit's colour in the path, the ring and a soft
// glow, never as a stripe down an edge (Hillel's rule).

import { localized, t, tk } from '../i18n.js';
import { navigate } from '../router.js';
import { dayOf, levelFor, nextLesson, weekDays, weekOf, weekRing, weeksInARow } from '../course.js';
import { allLessons, learning, loadLearning, loadPlusCourses, setGoal, startCourse } from '../stores/learning.js';
import { pickWorkout } from '../workout.js';
import { lessonMinutes } from '../results.js';
import { account } from '../stores/account.js';
import { PART_COLORS } from '../theme.js';
import { paint } from '../colors.js';
import { appearance } from '../stores/appearance.js';
import { Icon } from './icon.js';
import { courseSummary, courseVisible } from '../courses.js';
import { game, sk } from '../skin.js';
import { effect } from '../game/effects.js';
import { daysSinceArrival } from '../funnel.js';
import { count } from '../usage.js';
import { midiName } from '../dsp.js';

const WELCOME = { yesterday: tk('home.lead.welcomeYesterday'), last: tk('home.lead.welcomeLast'), yesterdaySerious: tk('home.lead.welcomeYesterdaySerious'), lastSerious: tk('home.lead.welcomeLastSerious') };

export const unitColor = (unit) => PART_COLORS[((unit?.colour ?? 4) - 1) % PART_COLORS.length];

/** The week's ring: one arc toward the goal, with "2/3" in the middle. */
export const GoalRing = {
    props: { size: { type: Number, default: 44 }, color: { type: String, default: 'var(--color-accent)' } },
    setup: () => ({ learning, game }),
    template: `
        <!-- The goal ring is game mode's; serious mode says the week in plain words beside it (docs/skins-plan.md row 7). -->
        <span v-if="game()" class="relative inline-grid shrink-0 place-items-center" :style="{ width: size + 'px', height: size + 'px' }">
            <svg viewBox="0 0 36 36" class="absolute inset-0 size-full" role="img" :aria-label="t('course.weekLabel', { done: ring.done, goal: ring.goal })">
                <circle cx="18" cy="18" r="15" fill="none" stroke="var(--color-line)" stroke-width="3.5" />
                <circle v-if="ring.done" cx="18" cy="18" r="15" fill="none" :stroke="color" stroke-width="3.5" stroke-linecap="round" :stroke-dasharray="(Math.min(1, ring.done / ring.goal) * 94.2) + ' 94.2'" transform="rotate(-90 18 18)" />
            </svg>
            <span class="text-xs font-extrabold tabular-nums text-cream" aria-hidden="true">{{ ring.done }}/{{ ring.goal }}</span>
        </span>`,
    computed: {
        ring: () => weekRing(learning.progress),
    },
};

/** The voice level, its name, the XP and the bar to the next level. */
export const LevelBar = {
    props: { color: { type: String, default: 'var(--color-accent)' } },
    setup: () => ({ learning, game }),
    template: `
        <!-- Levels and XP are game mode's (docs/skins-plan.md): serious mode shows none. -->
        <div v-if="game()" class="min-w-0 flex-1">
            <p class="flex justify-between gap-3 text-sm"><span class="truncate font-semibold text-cream">{{ t('course.levelName', { level: level.level, name: t(level.name) }) }}</span><span class="shrink-0 text-muted">{{ t('course.xp', { xp: learning.progress.xp.total }) }}</span></p>
            <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-ink" data-level-bar role="progressbar" :aria-valuenow="level.into" aria-valuemin="0" :aria-valuemax="level.needed" :aria-label="t('course.xpLabel', { xp: level.into, needed: level.needed })">
                <div class="h-full rounded-full" :style="{ width: Math.round((level.into / level.needed) * 100) + '%', background: color }"></div>
            </div>
        </div>`,
    computed: {
        level: () => levelFor(learning.progress.xp.total),
    },
};

const GOALS = [2, 3, 5];

/**
 * The ring, the level and weeks in a row together: the course page (where the goal can be changed:
 * `editable`), and the home's side on a wide screen. A week missed shows only "New week, fresh start".
 */
export const CourseStats = {
    components: { GoalRing, LevelBar },
    props: { editable: { type: Boolean, default: false } },
    setup: () => ({ learning, GOALS, account, navigate, game, sk }),
    template: `
        <div class="space-y-4">
            <div class="flex items-center gap-4">
                <GoalRing :size="56" />
                <div>
                    <p class="font-semibold text-cream">{{ game() ? t('course.week', { done: ring.done, goal: ring.goal }) : t('home.week.daysThisWeek', { count: ring.done }) }}</p>
                    <p v-if="weeks && game()" class="text-sm text-muted" data-weeks>{{ sk('course.weeksInARow', 'game.home.weeks', { count: weeks }) }}</p>
                    <p v-else-if="freshStart" class="text-sm text-muted">{{ t('course.freshStart') }}</p>
                </div>
            </div>
            <LevelBar />
            <fieldset v-if="editable" class="flex flex-wrap items-center gap-2">
                <legend class="mb-2 text-sm text-muted">{{ t('course.goal.title') }}</legend>
                <button v-for="days in GOALS" :key="days" type="button" class="filter-chip" :aria-pressed="learning.progress.goal === days" @click="setGoal(days)">{{ t('course.goal.days', { count: days }) }}</button>
                <a v-if="account.me" href="/settings/reminders" class="btn-go" @click.prevent="navigate('/settings/reminders')">{{ t('reminders.ask') }}</a>
            </fieldset>
        </div>`,
    computed: {
        ring: () => weekRing(learning.progress),
        weeks: () => weeksInARow(learning.progress),
        /** Practised in an earlier week, nothing yet this week, and no run of weeks going: a fresh start, no blame. */
        freshStart(vm) {
            const monday = weekOf(dayOf());
            return vm.ring.done === 0 && Object.keys(learning.progress.practice).some((day) => day < monday);
        },
    },
    methods: { setGoal },
};

/** A unit's lessons in a row: done (filled), the next one (bigger, glowing), the rest, the challenge last. */
export const LessonPath = {
    components: { Icon },
    props: { unit: { type: Object, required: true }, current: { type: String, default: null } },
    setup: () => ({ learning, localized }),
    template: `
        <ol class="flex items-center" :aria-label="t('home.lead.path')" data-lesson-path>
            <template v-for="(lesson, i) in unit.lessons" :key="lesson.id">
                <li v-if="i" class="mx-1.5 h-0.5 min-w-3 flex-1 rounded-full" :style="{ background: stars(unit.lessons[i - 1]) ? color : 'var(--color-line)' }" aria-hidden="true"></li>
                <li class="shrink-0">
                    <!-- Glass gems (2.6a): done and the one now; the rest hollow; the challenge a diamond. -->
                    <button type="button" class="grid place-items-center transition" :class="[lesson.id === current ? 'size-12' : 'size-9', lesson.challenge && !stars(lesson) ? 'rotate-45 rounded-lg' : 'rounded-full', stars(lesson) || lesson.id === current ? 'gem' : 'border-2 border-line bg-ink text-muted']" :style="lesson.id === current ? { boxShadow: '0 0 0 6px color-mix(in srgb, var(--color-accent) 22%, transparent)' } : null"
                        :aria-label="t('course.lessonLabel', { title: localized(lesson.title), stars: stars(lesson) })" :aria-current="lesson.id === current ? 'step' : null" @click="navigate('/learn/' + lesson.id)">
                        <Icon :name="stars(lesson) ? 'check' : lesson.id === current ? 'play' : lesson.challenge ? 'starFilled' : 'note'" :size="lesson.id === current ? 20 : 15" :class="lesson.challenge && !stars(lesson) ? '-rotate-45' : ''" />
                    </button>
                </li>
            </template>
        </ol>`,
    computed: {
        // The accent, not a part colour: nothing on the course band is a part (Hillel's call).
        color: () => 'var(--color-accent)',
    },
    methods: {
        navigate,
        stars: (lesson) => learning.progress.lessons[lesson.id]?.stars ?? 0,
        look(lesson) {
            if (lesson.id === this.current) return { background: this.color, color: 'var(--color-accent-ink)', boxShadow: `0 0 0 5px color-mix(in srgb, ${this.color} 25%, transparent)` };
            if (this.stars(lesson)) return { background: this.color, color: 'var(--color-accent-ink)' };
            return { background: 'var(--color-raised)', color: 'var(--color-muted)', border: '1.5px solid var(--color-line)' };
        },
    },
};

// Four voices, one of them you: a fixed little waveform for each lane of the welcome.
const LANES = [
    { part: 'parts.soprano', color: 0, seed: 3 },
    { part: 'parts.alto', color: 1, seed: 8, you: true },
    { part: 'parts.tenor', color: 2, seed: 5 },
    { part: 'parts.bass', color: 3, seed: 9 },
];
const BARS = 48;
const laneBars = (seed) => Array.from({ length: BARS }, (_, i) => 0.25 + 0.75 * Math.abs(Math.sin(i * 0.61 * seed) * Math.cos(i * 0.19 + seed)));

/**
 * The course's band, first on the home: before the first lesson, what Stackapella is and Start
 * singing; then the next lesson with its unit's path, Continue, and the week and level (on a phone;
 * a wide screen has them at the side). `compact` keeps it short on a phone when your own work comes
 * next, with the unit's path and place still in it.
 */
export const HomeLead = {
    components: { Icon, LessonPath, GoalRing, LevelBar },
    props: { compact: { type: Boolean, default: false } },
    setup: () => ({ learning, localized, LANES, PART_COLORS, laneBars, BARS, effect, game }),
    data: () => ({ starting: false, wide: window.matchMedia('(min-width: 1024px)').matches }),
    template: `
        <section class="relative overflow-hidden rounded-[1.75rem] border border-edge bg-panel p-5 md:p-8" :style="{ backgroundImage: glow }" aria-labelledby="lead-title" data-lead>
            <div v-if="!learning.loaded" class="space-y-3" aria-hidden="true"><div class="skeleton h-8 w-2/3"></div><div class="skeleton h-12 w-full"></div></div>

            <div v-else-if="learning.failed" class="space-y-3">
                <h1 id="lead-title" class="text-2xl font-extrabold tracking-tight text-cream">{{ t('course.title') }}</h1>
                <p class="text-muted" role="alert">{{ t('home.lead.failed') }}</p>
                <button type="button" class="btn-secondary" @click="retry">{{ t('common.tryAgain') }}</button>
            </div>

            <template v-else-if="!started">
                <h1 id="lead-title" class="max-w-md text-3xl font-extrabold leading-tight tracking-tight text-cream md:text-4xl">{{ t('home.lead.title') }}</h1>
                <ul class="mt-5 space-y-2" aria-hidden="true">
                    <li v-for="lane in LANES" :key="lane.part" class="flex items-center gap-3">
                        <span class="w-20 shrink-0 text-xs font-bold" :class="lane.you ? 'text-cream' : 'text-muted'">{{ lane.you ? t('home.lead.you', { part: t(lane.part) }) : t(lane.part) }}</span>
                        <svg :viewBox="'0 0 ' + BARS * 4 + ' 24'" preserveAspectRatio="none" class="h-6 flex-1 rounded-lg bg-ink" :style="lane.you ? { boxShadow: 'inset 0 0 0 1.5px ' + paint(PART_COLORS[lane.color]) } : null">
                            <rect v-for="(h, i) in laneBars(lane.seed)" :key="i" :x="i * 4 + 1" :y="12 - h * 9" width="2" :height="h * 18" rx="1" :fill="paint(PART_COLORS[lane.color])" :opacity="lane.you ? 1 : 0.7" />
                        </svg>
                    </li>
                </ul>
                <p class="mt-4 text-sm text-muted">{{ t('home.lead.body') }}</p>
                <button type="button" class="btn-primary btn-lg mt-5 w-full sm:w-auto" :disabled="starting || !learning.course" @click="begin">{{ t('course.start') }} <Icon name="arrow" :size="18" /></button>
                <p class="mt-3 text-xs text-muted">{{ t('home.lead.free') }}</p>
            </template>

            <div v-else-if="next && !short" class="grid gap-x-10 gap-y-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
              <div class="min-w-0">
                <!-- Back on another day of the first week (onboarding step 7): what last time gave. -->
                <p v-if="welcome" class="mb-4 font-semibold text-cream" data-welcome>{{ welcome }}</p>
                <!-- 42.png and Hillel's Home design: the unit as an eyebrow, its path of glass gems, the lesson's label and title, the unit's progress, Continue lesson. -->
                <div class="flex items-start justify-between gap-3">
                    <p class="tabular-nums text-xs font-bold uppercase tracking-[0.14em] text-accent-strong" translate="no" data-lead-eyebrow>{{ effect('title') ? t('game.home.stage', { number: unitNumber, title: localized(next.unit.title) }) : t('home.lead.unit', { number: unitNumber, title: localized(next.unit.title) }) }}</p>
                    <a href="/learn" class="btn-go" @click.prevent="navigate('/learn')">{{ t('home.lead.map') }}</a>
                </div>
                <LessonPath class="mt-6" :unit="next.unit" :current="finished ? null : next.lesson.id" />
                <p class="mt-8 text-sm text-muted">{{ finished ? t('home.lead.allDone') : t('home.lead.lessonOf', { place: place, total: next.unit.lessons.length, minutes: minutes }) }}</p>
                <h1 id="lead-title" class="mt-1 text-4xl font-extrabold leading-tight tracking-tight text-cream md:text-5xl" translate="no">{{ finished ? t('home.lead.allDoneTitle') : localized(next.lesson.title) }}</h1>
                <div class="mt-5 flex max-w-md items-center gap-3" data-unit-progress>
                    <span class="h-1.5 flex-1 overflow-hidden rounded-full bg-raised" role="progressbar" :aria-valuenow="done" aria-valuemin="0" :aria-valuemax="next.unit.lessons.length" :aria-label="t('home.lead.unitProgress', { done, total: next.unit.lessons.length })"><span class="block h-full rounded-full bg-accent" :style="{ width: (100 * done) / next.unit.lessons.length + '%' }"></span></span>
                    <span v-if="!finished && left" class="shrink-0 text-sm text-muted">{{ t('home.lead.left', { count: left }) }}</span>
                </div>
                <div class="mt-6">
                    <button v-if="!finished && effect('title')" type="button" class="btn-primary w-full sm:w-auto" data-press-start @click="navigate('/learn/' + next.lesson.id)"><span class="sr-only">{{ t('home.lead.continueLesson') }}: </span><span class="ge-press-arrow" aria-hidden="true">▸</span> {{ t('game.home.pressStart') }}</button>
                    <button v-else-if="!finished" type="button" class="btn-primary w-full sm:w-auto" @click="navigate('/learn/' + next.lesson.id)">{{ t('home.lead.continueLesson') }} <Icon name="arrow" :size="16" /></button>
                    <button v-else type="button" class="btn-secondary w-full sm:w-auto" @click="navigate('/learn')">{{ t('home.lead.seeCourse') }}</button>
                </div>
                <div v-if="game()" class="mt-6 flex items-center gap-3 border-t border-line pt-4 lg:hidden">
                    <GoalRing :color="color" />
                    <LevelBar :color="color" />
                </div>
              </div>
              <!-- A wide screen: the week beside the lesson, inside the card, so every band below lines up with it. -->
              <div class="hidden border-s border-line ps-10 lg:block"><slot name="week" /></div>
            </div>

            <template v-else-if="next">
                <!-- Short, so your own work stays above the fold on a phone, but still the unit's progress (Hillel: "why don't I see course progress"). -->
                <p v-if="welcome" class="mb-3 text-sm font-semibold text-cream" data-welcome>{{ welcome }}</p>
                <div class="flex items-start justify-between gap-3">
                    <p class="tabular-nums text-xs font-bold uppercase tracking-[0.14em] text-accent-strong" translate="no" data-lead-eyebrow>{{ effect('title') ? t('game.home.stage', { number: unitNumber, title: localized(next.unit.title) }) : t('home.lead.unit', { number: unitNumber, title: localized(next.unit.title) }) }}</p>
                    <a href="/learn" class="btn-go" @click.prevent="navigate('/learn')">{{ t('home.lead.map') }}</a>
                </div>
                <LessonPath class="mt-4" :unit="next.unit" :current="finished ? null : next.lesson.id" />
                <div class="mt-4 flex items-center gap-3">
                    <GoalRing :color="color" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-xs text-muted">{{ finished ? t('home.lead.allDone') : t('home.lead.lessonOf', { place: place, total: next.unit.lessons.length, minutes: minutes }) }}</p>
                        <h1 id="lead-title" class="truncate text-lg font-bold text-cream" translate="no">{{ finished ? t('home.lead.allDoneTitle') : localized(next.lesson.title) }}</h1>
                    </div>
                </div>
                <button type="button" class="mt-4 w-full sm:w-auto" :class="finished ? 'btn-secondary' : 'btn-primary'" @click="navigate(finished ? '/learn' : '/learn/' + next.lesson.id)">{{ finished ? t('home.lead.seeCourse') : t('course.continue') }} <Icon v-if="!finished" name="arrow" :size="16" /></button>
            </template>
        </section>`,
    created() {
        loadLearning();
    },
    watch: {
        // The first week's "Welcome back" card, counted once a visit when it shows (the progress loads after the home).
        welcome: {
            immediate: true,
            handler(now) {
                if (!now || this.welcomeCounted) return;
                this.welcomeCounted = true;
                count('card_day2');
            },
        },
    },
    mounted() {
        this.media = window.matchMedia('(min-width: 1024px)');
        this.onMedia = (event) => (this.wide = event.matches);
        this.media.addEventListener('change', this.onMedia);
    },
    unmounted() {
        this.media.removeEventListener('change', this.onMedia);
    },
    computed: {
        /**
         * "Welcome back. Yesterday: A3, 8 stars." on a later day of the device's first week: the note,
         * and the stars earned on the last day sung before today.
         */
        welcome() {
            const days = daysSinceArrival();
            const home = learning.progress.home;
            if (days === null || days < 1 || days > 6 || home === null) return null;
            const startOfToday = new Date().setHours(0, 0, 0, 0);
            const earlier = Object.values(learning.progress.lessons ?? {}).filter((l) => l?.stars && l.at && l.at < startOfToday);
            if (!earlier.length) return null;
            const last = new Date(Math.max(...earlier.map((l) => l.at))).setHours(0, 0, 0, 0);
            const thatDay = earlier.filter((l) => new Date(l.at).setHours(0, 0, 0, 0) === last);
            const stars = thatDay.reduce((sum, l) => sum + l.stars, 0);
            const yesterday = startOfToday - last <= 86400000 * 1.5;
            // Serious mode counts lessons, never stars (docs/skins-plan.md).
            const words = game() ? (yesterday ? WELCOME.yesterday : WELCOME.last) : yesterday ? WELCOME.yesterdaySerious : WELCOME.lastSerious;
            return t(words, { note: midiName(Math.round(home)), stars, lessons: thatDay.length });
        },
        /** Compact only on a phone or tablet: a wide screen has room for the whole card beside the week. */
        short() {
            return this.compact && !this.wide;
        },
        started: () => Object.keys(learning.progress.lessons).length > 0 || !!learning.progress.startedAt,
        next: () => (learning.course ? nextLesson(learning.course.units, learning.progress, { plus: account.me?.plan === 'plus' }) : null),
        /** Every lesson open to them has stars: the band points to the course to play for more. */
        finished() {
            return !!this.next && !!learning.progress.lessons[this.next.lesson.id]?.stars;
        },
        unitNumber() {
            return learning.course.units.indexOf(this.next.unit) + 1;
        },
        /** The lesson's place in its unit, and the lessons after it still to sing. */
        place() {
            return this.next.unit.lessons.indexOf(this.next.lesson) + 1;
        },
        left() {
            return this.next.unit.lessons.filter((l) => !learning.progress.lessons[l.id]?.stars).length - 1;
        },
        /** The unit's lessons with stars. */
        done() {
            return this.next.unit.lessons.filter((l) => learning.progress.lessons[l.id]?.stars).length;
        },
        minutes() {
            return lessonMinutes(this.next.lesson);
        },
        color: () => 'var(--color-accent)',
        /** The course card in the accent (42.png): a gradient from the accent's dark tint. */
        glow() {
            return this.started && this.next && !this.short
                ? `linear-gradient(135deg, color-mix(in srgb, ${this.color} 22%, var(--color-panel)), var(--color-panel) 70%)`
                : `radial-gradient(110% 80% at 100% 0%, color-mix(in srgb, ${this.color} 16%, transparent), transparent 65%)`;
        },
    },
    methods: {
        navigate,
        retry: () => loadLearning(),
        /** The first Start: straight into singing, no questions first. */
        async begin() {
            this.starting = true;
            try {
                navigate(`/learn/${await startCourse()}`);
            } finally {
                this.starting = false;
            }
        },
    },
};

const DAY_LETTERS = [tk('course.day.mon'), tk('course.day.tue'), tk('course.day.wed'), tk('course.day.thu'), tk('course.day.fri'), tk('course.day.sat'), tk('course.day.sun')];
const DAY_NAMES = [tk('course.day.monLong'), tk('course.day.tueLong'), tk('course.day.wedLong'), tk('course.day.thuLong'), tk('course.day.friLong'), tk('course.day.satLong'), tk('course.day.sunLong')];

/** The home's week and level (42.png): the ring, "Sing 2 days this week", the day bars, the level and its XP, and Range, badges and history. */
export const WeekCard = {
    components: { GoalRing, Icon },
    props: { bare: Boolean }, // inside the course card on a wide screen: no card of its own
    setup: () => ({ learning, DAY_LETTERS, DAY_NAMES, navigate, game }),
    template: `
        <section class="space-y-5" :class="bare ? '' : 'card p-5'" :aria-label="t('home.week.title')">
            <div class="flex items-center gap-4">
                <GoalRing :size="56" />
                <div class="min-w-0"><p class="font-bold text-cream">{{ game() ? t('home.week.title') : t('home.week.practised') }}</p><p class="text-sm text-muted">{{ game() ? t('home.week.goal', { count: learning.progress.goal }) : t('home.week.daysThisWeek', { count: sungDays }) }}</p></div>
            </div>
            <ol class="grid grid-cols-7 gap-1.5" :aria-label="t('home.week.days')">
                <li v-for="(d, i) in days" :key="d.day" class="flex flex-col items-center gap-1.5"  data-week-day :data-day-done="d.done ? '' : null" :data-day-today="d.today ? '' : null">
                    <span class="relative h-1.5 w-full overflow-hidden rounded-full" :class="d.today ? 'bg-cream/25' : 'bg-line'"><span class="absolute inset-y-0 start-0 rounded-full bg-accent" :style="{ width: d.share * 100 + '%' }"></span></span>
                    <span class="text-[10px] font-semibold" :class="d.today ? 'text-cream' : 'text-muted'" :aria-label="d.done ? t('home.week.dayDone', { day: t(DAY_NAMES[i]) }) : t('home.week.dayNot', { day: t(DAY_NAMES[i]) })">{{ t(DAY_LETTERS[i]) }}</span>
                </li>
            </ol>
            <div v-if="game()" class="border-t border-line pt-4">
                <p class="flex justify-between gap-3 text-sm"><span class="font-semibold text-cream">{{ t('course.levelName', { level: level.level, name: t(level.name) }) }}</span><span class="tabular-nums text-xs text-muted">{{ t('results.levelXp', { into: level.into, needed: level.needed }) }}</span></p>
                <div class="mt-2 h-2.5 overflow-hidden rounded-full bg-raised" data-level-bar role="progressbar" :aria-valuenow="level.into" aria-valuemin="0" :aria-valuemax="level.needed" :aria-label="t('course.xpLabel', { xp: level.into, needed: level.needed })"><div class="h-full rounded-full bg-accent" :style="{ width: Math.max(4, Math.round((level.into / level.needed) * 100)) + '%' }"></div></div>
            </div>
            <a href="/voice" class="flex items-center justify-between text-sm text-cream hover:text-accent-strong" @click.prevent="navigate('/voice')">{{ t('home.week.voice') }}<Icon name="arrow" :size="16" /></a>
        </section>`,
    computed: {
        days: () => weekDays(learning.progress),
        sungDays: (vm) => vm.days.filter((d) => d.done).length,
        level: () => levelFor(learning.progress.xp.total),
    },
};

const STEP_WORDS = { warmUp: tk('home.workout.warmUp'), weakSpot: tk('home.workout.focus'), review: tk('home.workout.review') };
const STEP_MINUTES = { warmUp: 2, weakSpot: 3, review: 2 };
const WHAT = { warmUp: tk('home.workout.warmUpWhat'), weakSpot: tk('home.workout.focusWhat') };
const INSIGHT_WHAT = {
    flatHigh: tk('home.workout.insight.flatHigh'), sharpHigh: tk('home.workout.insight.sharpHigh'), flatOverall: tk('home.workout.insight.flatOverall'), sharpOverall: tk('home.workout.insight.sharpOverall'),
    scoops: tk('home.workout.insight.scoops'), late: tk('home.workout.insight.late'), early: tk('home.workout.insight.early'), wavering: tk('home.workout.insight.wavering'),
};

/** Today's workout (42.png) as a strip: its steps with what each is, and Start workout. */
export const WorkoutStrip = {
    components: { Icon },
    setup: () => ({ STEP_WORDS, STEP_MINUTES, localized, navigate, sk }),
    template: `
        <section aria-labelledby="band-workout">
            <div class="flex flex-wrap items-baseline justify-between gap-3">
                <h2 id="band-workout" class="text-2xl font-extrabold tracking-tight text-cream">{{ sk('workout.title', 'game.workout.title') }}</h2>
                <span class="text-sm text-muted">{{ t('home.workout.total', { minutes: total }) }}</span>
            </div>
            <!-- Hillel's design: each step numbered with its own length, each a quarter of the row, and
                 Start workout the fourth ("each section takes up a third of the space and put start
                 workout in the 4th column"), as the courses' rows end on Show more. A phone stacks them. -->
            <ol class="mt-3 grid gap-3 md:grid-cols-4">
                <li v-for="(step, i) in steps" :key="step.kind" class="min-w-0 rounded-[var(--radius-card)] border border-edge bg-panel px-4 py-3" data-workout-step>
                    <p class="flex items-center gap-2.5">
                        <span class="grid size-6 shrink-0 place-items-center rounded-full border border-line text-xs font-semibold tabular-nums text-cream" aria-hidden="true">{{ i + 1 }}</span>
                        <span class="min-w-0 flex-1 truncate text-[11px] font-bold uppercase tracking-[0.12em] text-accent-strong">{{ t(STEP_WORDS[step.kind]) }}</span>
                        <span class="shrink-0 text-xs tabular-nums text-muted">{{ t('home.workout.minutes', { minutes: STEP_MINUTES[step.kind] }) }}</span>
                    </p>
                    <p class="mt-1.5 truncate font-semibold text-cream">{{ what(step) }}</p>
                </li>
                <li class="min-w-0">
                    <button type="button" class="btn-primary h-full min-h-14 w-full rounded-[var(--radius-card)]" data-start-workout @click="navigate('/workout')">{{ t('home.workout.start') }} <Icon name="arrow" :size="16" /></button>
                </li>
            </ol>
        </section>`,
    computed: {
        steps() {
            const lessons = allLessons().map(({ lesson }) => ({ id: lesson.id, exercise: lesson.steps.find((s) => s.kind === 'play' && s.exercise)?.exercise })).filter((l) => l.exercise);
            return pickWorkout(learning.progress, lessons).steps;
        },
        total() {
            return this.steps.reduce((sum, s) => sum + STEP_MINUTES[s.kind], 0);
        },
    },
    methods: {
        what(step) {
            if (step.kind === 'review') {
                const entry = allLessons().find((e) => e.lesson.id === step.lessonId);
                return entry ? localized(entry.lesson.title) : t('workout.step.review');
            }
            return t(step.kind === 'weakSpot' && INSIGHT_WHAT[step.insight] ? INSIGHT_WHAT[step.insight] : WHAT[step.kind]);
        },
    },
};

/** The Plus course groups, as Hillel names them. */
const COURSE_GROUPS = [
    { id: 'skills', name: tk('courses.group.skills') },
    { id: 'harmony', name: tk('courses.group.harmony') },
    { id: 'styles', name: tk('courses.group.styles') },
];

/**
 * The Plus courses on Home (Hillel: "the new courses should be at the bottom of the page. We show the
 * 3 of each we've done and the 4th col says show more to see the rest coming"; "don't show any double
 * width"): a row per group (Singing skills, Harmony, Styles), every card the same size, its first
 * three and a fourth tile, "Show more", that opens the row in place to the rest.
 * A live course this account can see opens its page (its first lesson free); the rest say Coming
 * soon. Drawn from the catalogue (course/plus/courses.json: group, order, featured, launch, status),
 * so a course that goes live shows by itself. Game mode calls it Bonus stages.
 */
export const HomeCourses = {
    setup: () => ({ localized, navigate, sk }),
    data: () => ({ opened: [] }),
    template: `
        <section v-if="groups.length" aria-labelledby="band-courses" data-home-courses>
            <div class="flex flex-wrap items-baseline justify-between gap-3">
                <h2 id="band-courses" class="text-2xl font-extrabold tracking-tight text-cream">{{ sk('courses.homeTitle', 'courses.homeTitleGame') }}</h2>
                <span class="text-sm text-muted">{{ t('courses.homeLine') }}</span>
            </div>
            <div class="mt-3 space-y-5">
                <div v-for="g in groups" :key="g.id" :data-course-group="g.id">
                    <h3 class="mb-2 text-xs font-bold uppercase tracking-[0.12em] text-muted">{{ t(g.name) }}</h3>
                    <ul :id="'courses-' + g.id" class="-mx-4 flex snap-x scroll-px-4 gap-3 overflow-x-auto px-4 pb-1 [scrollbar-width:none] md:mx-0 md:grid md:grid-cols-4 md:overflow-visible md:px-0">
                        <li v-for="c in shown(g)" :key="c.entry.id" class="w-60 shrink-0 snap-start md:w-auto" :data-course-card="c.entry.id">
                            <component :is="c.open ? 'a' : 'div'" :href="c.open ? '/learn/c/' + c.entry.id : null" class="card flex h-full flex-col gap-1.5 p-4 transition" :class="c.open ? 'hover:border-muted/60' : ''" :aria-disabled="c.open ? null : 'true'" @click="c.open && ($event.preventDefault(), navigate('/learn/c/' + c.entry.id))">
                                <span class="font-bold text-cream" translate="no">{{ localized(c.entry.title) }}</span>
                                <span class="line-clamp-2 text-sm text-muted" translate="no">{{ localized(c.entry.line) }}</span>
                                <span class="mt-auto pt-1 text-xs font-semibold" :class="c.open ? 'text-accent-strong' : 'text-muted'" data-course-status>{{ c.status }}</span>
                            </component>
                        </li>
                        <!-- The fourth tile: the row's courses still to come, opened in place (and closed again). -->
                        <li v-if="g.later.length" class="w-60 shrink-0 snap-start md:w-auto">
                            <button type="button" class="flex h-full min-h-28 w-full flex-col items-center justify-center gap-1 rounded-[var(--radius-card)] border border-dashed border-line p-4 text-center hover:border-muted" :aria-expanded="opened.includes(g.id) ? 'true' : 'false'" :aria-controls="'courses-' + g.id" data-course-more @click="toggle(g.id)">
                                <span class="font-semibold text-cream">{{ opened.includes(g.id) ? t('courses.showFewer') : t('courses.showMore') }}</span>
                                <span v-if="!opened.includes(g.id)" class="text-xs text-muted">{{ t('courses.moreComing', { count: g.later.length }) }}</span>
                            </button>
                        </li>
                    </ul>
                </div>
            </div>
        </section>`,
    created() {
        loadLearning();
        loadPlusCourses();
    },
    computed: {
        cards() {
            const me = account.me;
            const plus = me?.plan === 'plus';
            return (learning.catalogue ?? []).map((entry) => {
                const open = courseVisible(entry, me) && !!learning.courses?.[entry.id];
                const summary = open ? courseSummary(learning.courses[entry.id], learning.progress) : null;
                const status = !open ? t('courses.soon') : summary.started ? t('courses.progress', { done: summary.done, total: summary.total }) : plus || me?.staff ? t('courses.lessons', { count: summary.total }) : t('courses.plusFirstFree');
                return { entry, open, status };
            });
        },
        /**
         * Each group's first three and the rest (Hillel: "show the 3 courses then a show more button in
         * the 4th"): the featured course first (Vibrato), then the launch courses in order, then the
         * rest in order; so Singing skills shows Vibrato, Perfect your pitch and Breath and stamina, and
         * Show more opens Ear training, then the courses still to come.
         */
        groups() {
            const rank = (c) => (c.entry.featured ? 0 : c.entry.launch ? 1 : 2);
            const byPlace = (a, b) => rank(a) - rank(b) || (a.entry.order ?? 99) - (b.entry.order ?? 99);
            return COURSE_GROUPS.map((g) => {
                const all = this.cards.filter((c) => (c.entry.group ?? 'skills') === g.id).sort(byPlace);
                return { ...g, first: all.slice(0, 3), later: all.slice(3) };
            }).filter((g) => g.first.length);
        },
    },
    methods: {
        shown(g) {
            return this.opened.includes(g.id) ? [...g.first, ...g.later] : g.first;
        },
        toggle(id) {
            this.opened = this.opened.includes(id) ? this.opened.filter((o) => o !== id) : [...this.opened, id];
        },
    },
};

/** One band of the stack: a small bar in a part colour, the title, and something on the right. */
export const HomeBand = {
    props: { title: { type: String, required: true }, color: { type: String, required: true }, id: { type: String, required: true }, count: { type: String, default: null } },
    template: `
        <section :aria-labelledby="id">
            <div class="flex flex-wrap items-center gap-3">
                <div class="flex flex-1 items-baseline gap-3 whitespace-nowrap">
                    <h2 :id="id" class="text-2xl font-extrabold tracking-tight text-cream">{{ title }}</h2>
                    <span v-if="count" class="text-sm tabular-nums text-muted">{{ count }}</span>
                </div>
                <slot name="aside" />
            </div>
            <div class="mt-3"><slot /></div>
        </section>`,
};

/** The voices in their colours, as Home's covers and their legend show them. */
export const VOICE_KEYS = ['soprano', 'alto', 'tenor', 'bass'];

/** A row's dashes, the same every time for a seed: an indent, then dashes of varied lengths. */
function coverRow(seed, row) {
    let x = (seed * 7919 + row * 104729 + 1) % 2147483647;
    const next = () => (x = (x * 48271) % 2147483647) / 2147483647;
    const dashes = Array.from({ length: 6 + Math.floor(next() * 4) }, () => 2 + Math.round(next() * 6));
    return { indent: Math.round(next() * 10), end: Math.round(next() * 6), dashes };
}

/**
 * A song's cover (Hillel's Home design): a row of dashes per part (up to four), like the phrases of
 * the parts stacked, each in its voice's colour (soprano, alto, tenor, bass, as the legend under the
 * library says; a part that isn't one of those takes its place's colour). `voices` are the parts'
 * keys when known, else `parts` counts them; `seed` varies the dashes so no two covers match.
 */
export const SongCover = {
    props: { parts: { type: Number, default: 0 }, voices: { type: Array, default: null }, seed: { type: Number, default: 0 } },
    template: `
        <span class="flex h-28 flex-col justify-end gap-2.5 rounded-2xl border border-edge bg-panel px-5 pb-6" aria-hidden="true">
            <span v-for="row in rows" :key="row.i" class="flex h-1.5 gap-1" :style="{ paddingInlineStart: row.indent + '%', paddingInlineEnd: row.end + '%' }">
                <span v-for="(grow, k) in row.dashes" :key="k" class="h-full rounded-full" :style="{ flexGrow: grow, background: row.color }"></span>
            </span>
        </span>`,
    computed: {
        rows() {
            const keys = this.voices ?? Array.from({ length: this.parts }, (_, i) => VOICE_KEYS[i] ?? null);
            return keys.slice(0, 4).map((key, i) => {
                const place = VOICE_KEYS.indexOf(key);
                return { i, color: paint(PART_COLORS[place >= 0 ? place : i]), ...coverRow(this.seed, i) };
            });
        },
    },
};

/** A project's parts as thin stacked waveforms. */
export const Thumbnail = {
    props: { parts: Array },
    template: `<canvas ref="canvas" class="touch-pan-y block h-14 w-full"></canvas>`,
    setup: () => ({ appearance }),
    watch: {
        // A new theme: drawn again in its colours.
        'appearance.version'() {
            this.draw();
        },
    },
    mounted() {
        this.draw();
    },
    methods: {
        draw() {
            const canvas = this.$refs.canvas;
            const ratio = window.devicePixelRatio || 1;
            const width = canvas.clientWidth;
            const height = canvas.clientHeight;
            canvas.width = width * ratio;
            canvas.height = height * ratio;
            const g = canvas.getContext('2d');
            g.scale(ratio, ratio);
            const parts = this.parts.slice(0, 6);
            const lane = height / Math.max(parts.length, 1);
            parts.forEach((part, row) => {
                const peaks = part.peaks ?? [];
                g.fillStyle = paint(part.color);
                const middle = lane * row + lane / 2;
                for (let x = 0; x < width; x += 3) {
                    const peak = peaks[Math.floor((x / width) * peaks.length)] ?? 0;
                    const bar = Math.max(0.75, Math.sqrt(peak) * lane * 0.45);
                    g.fillRect(x, middle - bar, 2, bar * 2);
                }
            });
        },
    },
};
