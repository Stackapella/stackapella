// The whole course at /learn (the Learn hub): what's next, the week and the level, then every unit
// and its lessons. The home's course card is the way in (components/home.js); this is the map behind it.

import { localized, t, tk } from '../i18n.js';
import { navigate } from '../router.js';
import { inFirstWeek } from '../components/voice-care.js';
import { levelFor, nextLesson, voiceType, weekDays, weekRing } from '../course.js';
import { lessonMinutes } from '../results.js';
import { account } from '../stores/account.js';
import { learning, loadLearning, setGoal } from '../stores/learning.js';
import { WhatsNext } from '../components/whatsnext.js';
import { CourseMap } from '../components/course-map.js';
import { MoreCourses } from '../components/more-courses.js';
import { Icon } from '../components/icon.js';
import { arrivedToday, askShown } from '../funnel.js';
import { SessionWrap } from '../components/session-wrap.js';
import { game } from '../skin.js';

const VOICES = { soprano: tk('parts.soprano'), alto: tk('parts.alto'), tenor: tk('parts.tenor'), bass: tk('parts.bass') };

const GOALS = [2, 3, 5];
const DAY_LETTERS = [tk('course.day.mon'), tk('course.day.tue'), tk('course.day.wed'), tk('course.day.thu'), tk('course.day.fri'), tk('course.day.sat'), tk('course.day.sun')];
const XP_A_LESSON = 25; // a lesson's XP at three stars: "about two lessons" to the next level

/**
 * The Learn hub (Hillel's 62.png): "Learn / Sing harmony" with your voice as a chip; Up next (the
 * lesson, its line, Start lesson, "~4 min · up to 3 stars") beside Today's workout; This week (the
 * day circles, the goal as 2, 3 or 5 days, Remind me) beside the level ("170 XP to Level 4, about
 * two lessons."); then the units. Current progress and Start in your accent; units in their colours.
 */
export const CourseView = {
    components: { CourseMap, Icon, MoreCourses, SessionWrap, WhatsNext },
    setup: () => ({ inFirstWeek, learning, localized, GOALS, DAY_LETTERS, account, lessonMinutes, XP_A_LESSON, game }),
    computed: {
        next() {
            return learning.course ? nextLesson(learning.course.units, learning.progress, { plus: account.me?.plan === 'plus' }) : null;
        },
        /** A lesson waits (the course has more for this learner): What's next shows only when none does. */
        waiting() {
            if (!learning.course) return true;
            return !!this.next && !learning.progress.lessons[this.next.lesson.id]?.stars;
        },
        unitNumber() {
            return this.next ? learning.course.units.indexOf(this.next.unit) + 1 : 1;
        },
        line() {
            return this.next?.lesson.steps.find((s) => s.headline)?.headline ?? null;
        },
        days: () => weekDays(learning.progress),
        ring: () => weekRing(learning.progress),
        level: () => levelFor(learning.progress.xp.total),
        toNext() {
            return this.level.needed - this.level.into;
        },
        /** The first session's wrap: on the day this device arrived, two lessons or more with stars. */
        wrap() {
            if (!learning.loaded || !arrivedToday()) return null;
            const sung = Object.values(learning.progress.lessons ?? {}).filter((l) => l?.stars);
            return sung.length >= 2 ? { lessons: sung.length, stars: sung.reduce((sum, l) => sum + l.stars, 0) } : null;
        },
        /** Your voice on the chip: the voice your range suits, once there's a range. */
        voice() {
            const type = voiceType(learning.progress.range);
            return type ? t(VOICES[type]) : null;
        },
    },
    created() {
        loadLearning();
    },
    watch: {
        // The funnel's asks: the weekly goal (and Remind me, signed in), counted the first time shown.
        'learning.loaded': {
            handler(loaded) {
                if (!loaded) return;
                askShown('goal');
                if (account.me) askShown('remind');
            },
            immediate: true,
        },
    },
    template: `
        <div class="mx-auto min-h-dvh max-w-4xl px-4 pb-16 pt-[max(1rem,env(safe-area-inset-top))] md:px-8">
            <header class="flex items-center gap-3 py-4">
                <button type="button" class="glass-bubble grid size-11 shrink-0 place-items-center rounded-full text-cream" :aria-label="t('home.back')" @click="navigate('/')"><Icon name="back" :size="20" /></button>
                <div class="min-w-0 flex-1">
                    <p class="text-sm text-muted">{{ t('course.hub.eyebrow') }}</p>
                    <h1 class="truncate text-xl font-bold text-cream">{{ t('course.hub.title') }}</h1>
                </div>
                <a href="/voice" class="flex h-11 shrink-0 items-center gap-2 rounded-full glass-bubble px-4 text-sm font-semibold text-cream hover:bg-raised" @click.prevent="navigate('/voice')"><span class="size-2 rounded-full bg-accent" aria-hidden="true"></span>{{ voice ? t('course.hub.voiceIs', { voice }) : t('voice.title') }}</a>
            </header>
            <div v-if="learning.loaded && learning.course" class="mt-2 grid gap-4 md:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
                <section v-if="next" class="card p-6" :aria-label="t('course.hub.upNext')">
                    <p class="tabular-nums text-xs font-bold uppercase tracking-[0.14em] text-accent-strong">{{ t('course.hub.upNextUnit', { number: unitNumber }) }}</p>
                    <h2 class="mt-1 text-3xl font-extrabold tracking-tight text-cream" translate="no">{{ localized(next.lesson.title) }}</h2>
                    <p v-if="line" class="mt-1 max-w-md text-muted" translate="no">{{ localized(line) }}</p>
                    <div class="mt-5 flex flex-wrap items-center gap-4">
                        <button type="button" class="btn-primary btn-lg" @click="navigate('/learn/' + next.lesson.id)"><Icon name="play" :size="16" /> {{ t('course.startLesson') }}</button>
                        <span class="tabular-nums text-sm text-muted">{{ game() ? t('course.hub.meta', { minutes: lessonMinutes(next.lesson) }) : t('course.aboutMin', { minutes: lessonMinutes(next.lesson) }) }}</span>
                    </div>
                </section>
                <section class="card flex flex-col p-6" :aria-label="t('workout.title')">
                    <p class="tabular-nums text-xs font-bold uppercase tracking-[0.14em] text-accent-strong">{{ t('workout.title') }}</p>
                    <h2 class="mt-1 text-xl font-bold text-cream">{{ t('course.hub.workoutTime') }}</h2>
                    <p class="mt-1 text-sm text-muted">{{ t('course.hub.workoutLine') }}</p>
                    <div class="mt-auto pt-4"><a href="/workout" class="btn-go" @click.prevent="navigate('/workout')">{{ t('course.hub.begin') }}</a></div>
                </section>
            </div>
            <section v-if="learning.loaded" class="card mt-4 grid grid-cols-[minmax(0,1fr)] gap-6 p-4 sm:p-6" :class="game() ? 'md:grid-cols-2' : ''" :aria-label="t('home.week.days')">
                <div class="space-y-4">
                    <div class="flex flex-wrap items-baseline justify-between gap-x-3"><h2 class="font-bold text-cream">{{ t('home.week.days') }}</h2><span class="text-sm text-muted">{{ t('course.hub.daysOf', { done: ring.done, goal: ring.goal }) }}</span></div>
                    <ol class="grid grid-cols-7 gap-1 sm:gap-2">
                        <li v-for="(d, i) in days" :key="d.day" class="flex flex-col items-center gap-1.5">
                            <span class="grid size-8 place-items-center rounded-full sm:size-9" :class="d.done ? 'gem' : d.today ? 'border-2 border-cream' : d.future ? 'border border-dashed border-line' : 'border border-line'" aria-hidden="true"><Icon v-if="d.done" name="check" :size="14" /></span>
                            <span class="text-[11px] font-semibold" :class="d.today ? 'text-cream' : 'text-muted'">{{ t(DAY_LETTERS[i]) }}</span>
                        </li>
                    </ol>
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="text-sm text-muted">{{ t('course.hub.goal') }}</span>
                        <div class="seg flex w-full sm:w-auto" role="radiogroup" :aria-label="t('course.goal.title')">
                            <button v-for="g in GOALS" :key="g" type="button" role="radio" :aria-checked="learning.progress.goal === g ? 'true' : 'false'" class="seg-option min-w-0 flex-1 px-2 leading-tight whitespace-normal sm:px-4 sm:whitespace-nowrap" @click="setGoal(g)">{{ t('course.goal.days', { count: g }) }}</button>
                        </div>
                        <button v-if="account.me" type="button" class="btn-ghost btn-sm" @click="navigate('/settings/reminders')">{{ t('reminders.ask') }}</button>
                    </div>
                </div>
                <!-- The level and its XP: game mode's (serious mode shows the week alone). -->
                <div v-if="game()" class="space-y-2 md:border-s md:border-line md:ps-6">
                    <p class="flex flex-wrap justify-between gap-x-3"><span class="font-bold text-cream">{{ t('course.levelName', { level: level.level, name: t(level.name) }) }}</span><span class="tabular-nums text-sm text-muted">{{ t('results.levelXp', { into: level.into, needed: level.needed }) }}</span></p>
                    <div class="h-3 overflow-hidden rounded-full bg-raised" role="progressbar" :aria-valuenow="level.into" aria-valuemin="0" :aria-valuemax="level.needed" :aria-label="t('course.xpLabel', { xp: level.into, needed: level.needed })"><div class="h-full rounded-full bg-accent" :style="{ width: Math.max(4, Math.round((level.into / level.needed) * 100)) + '%' }"></div></div>
                    <p class="text-sm text-muted">{{ t('course.hub.toLevel', { xp: toNext, level: level.level + 1, lessons: Math.max(1, Math.ceil(toNext / XP_A_LESSON)) }) }}</p>
                </div>
            </section>
            <!-- Back to the course on day one after two lessons or more (onboarding step 5): the session's wrap here too. -->
            <SessionWrap v-if="wrap" class="mt-4" :lessons="wrap.lessons" :stars="wrap.stars" />
            <WhatsNext v-if="learning.loaded && !waiting" class="mt-6" />
            <CourseMap class="mt-6" />
            <!-- Voice care's cool-down (docs/voice-care-plan.md): a quiet way in, never a card. -->
            <p v-if="!inFirstWeek()" class="mt-6 text-sm text-muted" data-cooldown-link>{{ t('voiceCare.mapLine') }} <a href="/cooldown" class="link" @click.prevent="navigate('/cooldown')">{{ t('voiceCare.coolDown') }}</a></p>
            <MoreCourses class="mt-10" />
        </div>`,
    methods: { navigate, setGoal },
};
