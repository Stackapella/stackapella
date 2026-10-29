// Today's workout (docs/vocal-coaching-plan.md, "Today's workout"; js/workout.js picks it; Hillel's
// 85.png, ux-spec 5.7f): a warm-up in rounds a half step apart, one weak spot, one lesson to review,
// each as listen-then-your-turn. The header says where you are and how long it takes; the headline
// says what the exercise is; the lane shows one round at a time; Ready when you are with Hear it first
// and Start; the starting note with Lower and Higher (a semitone, remembered); and a foot that says
// what Continue waits for, with Skip as a clear way out. Then a phrase of the song you're learning.
// Done, it counts the day toward the weekly goal with its XP. Free: one a day; Plus: as many as you
// like (docs/features.md).

import { nextTick } from 'vue';
import { PageHeader } from '../components/page.js';
import { midiName } from '../dsp.js';
import { localized, t, tk } from '../i18n.js';
import { goBack, navigate } from '../router.js';
import { pickWorkout, shifted, stepSeconds, exerciseSeconds, workoutUsed } from '../workout.js';
import { account } from '../stores/account.js';
import { allLessons, learning, loadLearning, setWorkoutShift, workoutDone } from '../stores/learning.js';
import { loadProjects, projects, shownTitle } from '../stores/projects.js';
import { Exercise } from '../components/exercise.js';
import { Icon } from '../components/icon.js';
import { game, sk } from '../skin.js';

const DEFAULT_HOME = 57; // as a lesson's, until unit 1 finds the learner's own
const REST = 2; // beats of rest before each round: Get ready, then Breathe between rounds (on the audio clock)
const SHIFT_LIMIT = 7; // stores/learning.js keeps the shift within this, either way
const TITLES = { warmUp: tk('workout.step.warmUp'), weakSpot: tk('workout.step.weakSpot'), review: tk('workout.step.review') };
const WHY = {
    flatHigh: tk('workout.why.flatHigh'),
    sharpHigh: tk('workout.why.sharpHigh'),
    flatOverall: tk('workout.why.flatOverall'),
    sharpOverall: tk('workout.why.sharpOverall'),
    scoops: tk('workout.why.scoops'),
    late: tk('workout.why.late'),
    early: tk('workout.why.early'),
    wavering: tk('workout.why.wavering'),
};

export const WorkoutView = {
    components: { PageHeader, Exercise, Icon },
    data: () => ({ plan: null, step: 0, round: 0, running: false, previewing: false, previewRound: 0, done: 0, ran: false, sang: false, skippedAll: false, finished: false, xp: 0 }),
    setup: () => ({ learning, navigate, goBack, TITLES, WHY, SHIFT_LIMIT, REST, midiName, game, sk }),
    template: `
        <div class="mx-auto flex min-h-dvh max-w-2xl flex-col px-4 pb-[max(1rem,env(safe-area-inset-bottom))] pt-[max(0.5rem,env(safe-area-inset-top))] md:px-8">
            <PageHeader :title="sk('workout.title', 'game.workout.title')" back="/learn">
                <template #title>
                    <span class="block text-xs font-medium tracking-normal text-muted">{{ sk('workout.title', 'game.workout.title') }}</span>
                    <span class="block text-base font-bold tracking-normal">{{ plan && !finished && !used ? t(TITLES[current.kind]) : sk('workout.title', 'game.workout.title') }}</span>
                </template>
                <template #aside>
                    <div v-if="plan && !finished && !used" class="flex items-center gap-3">
                        <span class="text-xs text-muted tabular-nums">{{ t('workout.stepOf', { step: step + 1, count: plan.steps.length }) }}</span>
                        <span class="flex w-16 gap-1" aria-hidden="true"><span v-for="(s, i) in plan.steps" :key="i" class="h-1 flex-1 rounded-full" :class="i <= step ? 'bg-accent' : 'bg-line'"></span></span>
                    </div>
                </template>
            </PageHeader>

            <p v-if="!learning.loaded" class="text-muted" role="status">{{ t('settings.loading') }}</p>
            <section v-else-if="used" class="card mt-4 space-y-4 p-6 text-center">
                <span class="mx-auto grid size-14 place-items-center rounded-full bg-part-4 text-part-text-4"><Icon name="check" :size="28" /></span>
                <h2 class="text-xl font-bold text-cream">{{ t('workout.usedTitle') }}</h2>
                <p class="text-muted">{{ t('workout.usedHint') }}</p>
                <div class="flex flex-wrap justify-center gap-3">
                    <button type="button" class="btn-secondary" @click="navigate('/learn')">{{ t('workout.toCourse') }}</button>
                    <button type="button" class="btn-primary" @click="navigate('/plus?from=workout')">{{ t('workout.morePlus') }}</button>
                </div>
            </section>
            <section v-else-if="finished" class="card mt-4 space-y-4 p-6 text-center" aria-live="polite" data-workout-end>
                <span class="mx-auto grid size-14 place-items-center rounded-full bg-part-4 text-part-text-4"><Icon name="star" :size="28" /></span>
                <h2 class="text-xl font-bold text-cream">{{ t('workout.doneTitle') }}</h2>
                <p class="text-muted">{{ skippedAll ? t('workout.skippedAll') : xp && game() ? t('workout.doneXp', { xp }) : t('workout.doneHint') }}</p>
                <button v-if="plan.song" type="button" class="btn-primary" @click="navigate('/p/' + plan.song.projectId + '/sing')">{{ t('workout.song', { title: plan.song.title }) }}</button>
                <button type="button" class="btn-go" @click="navigate('/learn')">{{ t('workout.toCourse') }}</button>
            </section>
            <template v-else-if="plan">
                <section class="mt-2 space-y-5" :aria-labelledby="'workout-step-' + step">
                    <div>
                        <h2 :id="'workout-step-' + step" class="text-2xl font-extrabold leading-tight tracking-tight text-cream md:text-3xl">{{ headline }}</h2>
                        <p v-if="hint" class="mt-1 text-muted">{{ hint }}</p>
                    </div>
                    <!-- Hillel's warm-up design: the rounds as tabs over the notes, each with its starting note,
                         ticked when sung; a tap hears that round. -->
                    <div v-if="rounds > 1" class="grid grid-cols-3 overflow-hidden rounded-2xl border border-edge" role="tablist" :aria-label="t('workout.roundsLabel')" data-round-tabs>
                        <button v-for="r in rounds" :key="r" type="button" role="tab" class="flex min-h-14 flex-col items-start justify-center gap-0.5 border-b-2 px-3 py-2 text-start transition disabled:cursor-default"
                            :class="[r - 1 === round ? 'border-accent bg-raised/60' : 'border-transparent hover:bg-raised/40', r > 1 ? 'border-s border-s-edge' : '']" :aria-selected="r - 1 === round ? 'true' : 'false'" :disabled="running"
                            :aria-label="t('workout.hearRound', { number: r, note: roundNote(r - 1) })" @click="hearRound(r - 1)">
                            <span class="flex items-center gap-1 text-xs text-muted">{{ t('workout.round', { number: r }) }}<Icon v-if="r <= done" name="check" :size="12" class="text-good" /></span>
                            <span class="text-sm font-semibold text-cream" translate="no">{{ t('workout.startsOn', { note: roundNote(r - 1) }) }}</span>
                        </button>
                    </div>
                    <Exercise ref="exercise" bare :key="step + ':' + shift + ':' + round + (previewing ? ':hear' + previewRound : '')" :exercise="previewing ? preview : roundExercise" :base="base"
                        :mode="previewing ? 'listen' : 'play'" strictness="relaxed" :color="paint('#4ccfb0')" :rest-beats="previewing ? 0 : REST" :rest-label="round === 0 ? t('workout.getReady') : t('workout.breathe')" @done="roundDone" />
                    <p v-if="running && !previewing && rounds > 1" class="-mt-2 text-center text-xs text-muted" data-round-status>{{ t('workout.roundStatus', { number: round + 1, count: rounds }) }}</p>
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <div class="min-w-0 flex-1">
                            <p class="font-bold text-cream">{{ t('workout.ready') }}</p>
                            <p class="text-sm text-muted">{{ readyLine }}</p>
                        </div>
                        <div class="flex gap-3">
                            <button v-if="!running || previewing" type="button" class="btn-secondary flex-1 sm:flex-none" @click="hear">{{ previewing ? t('studio.stop') : t('workout.hearFirst') }}</button>
                            <button type="button" class="btn-primary flex flex-1 items-center justify-center gap-2 sm:flex-none" :disabled="previewing" @click="running ? stop() : begin()"><Icon :name="running ? 'stop' : 'play'" :size="16" />{{ running ? t('studio.stop') : t('workout.start') }}</button>
                        </div>
                    </div>
                    <!-- The starting note: a compact − and +, held while a round plays. -->
                    <div class="card flex items-center gap-3 p-4">
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-cream">{{ t('workout.startingNoteTitle') }}</p>
                            <p class="text-sm text-muted">{{ t('workout.startingHint') }}</p>
                        </div>
                        <div class="flex shrink-0 items-center gap-1 rounded-full border border-line p-1" role="group" :aria-label="t('workout.startingNoteTitle')" data-starting-note>
                            <button type="button" class="grid size-10 place-items-center rounded-full glass-bubble text-cream disabled:opacity-40" :aria-label="t('keyControl.lower')" :disabled="running || shift <= -SHIFT_LIMIT" @click="move(-1)"><Icon name="minus" :size="16" /></button>
                            <span class="min-w-12 text-center text-sm font-semibold tabular-nums text-cream" role="status" translate="no">{{ startingNote }}</span>
                            <button type="button" class="grid size-10 place-items-center rounded-full glass-bubble text-cream disabled:opacity-40" :aria-label="t('keyControl.higher')" :disabled="running || shift >= SHIFT_LIMIT" @click="move(1)"><Icon name="plus" :size="16" /></button>
                        </div>
                    </div>
                </section>
                <!-- Right after the content (Hillel's design): Skip, what's left to do, and the way on, lit once done. -->
                <footer class="mt-6 grid grid-cols-[auto_1fr] items-center gap-x-3 gap-y-2 border-t border-line py-4 sm:flex">
                    <button type="button" class="btn-ghost btn-sm" @click="skip">{{ current.kind === 'warmUp' ? t('workout.skipWarmUp') : t('workout.skipStep') }}</button>
                    <p class="text-end text-sm text-muted sm:ms-auto" :class="ran ? 'invisible' : ''">{{ rounds > 1 ? t('workout.finishRounds', { count: rounds }) : t('workout.singToContinue') }}</p>
                    <button type="button" class="btn-primary col-span-2 w-full sm:w-auto" :disabled="!ran" @click="next">{{ step === plan.steps.length - 1 ? t('workout.finish') : t('workout.continueTo', { step: step + 2 }) }}</button>
                </footer>
            </template>
        </div>`,
    computed: {
        base: () => learning.progress.home ?? DEFAULT_HOME,
        shift: () => learning.progress.workoutShift ?? 0,
        plus: () => account.me?.plan === 'plus',
        /** Free has had today's (Plus has as many as it likes). A workout under way carries on. */
        used() {
            return !this.plus && !this.plan?.started && workoutUsed(learning.progress);
        },
        current() {
            return this.plan.steps[this.step];
        },
        rounds() {
            return this.current.rounds ?? 1;
        },
        /** This round's exercise: moved by Lower or Higher, and a half step up for each round before. */
        roundExercise() {
            const ex = shifted(this.current.exercise, this.shift + this.round);
            return { ...ex, notes: ex.notes.map((n) => ({ ...n, beat: n.beat + REST })) };
        },
        /** Hear it first (or a round's tab): the notes once, as they'll be sung in that round. */
        preview() {
            return { ...shifted(this.current.exercise, this.shift + this.previewRound), turn: false };
        },
        startingNote() {
            const first = [...this.current.exercise.notes].sort((a, b) => a.beat - b.beat)[0];
            return midiName(this.base + this.shift + first.step);
        },
        seconds() {
            return Math.round(stepSeconds(this.current) / 5) * 5 || Math.round(exerciseSeconds(this.current.exercise));
        },
        readyLine() {
            return this.rounds > 1 ? t('workout.readyRounds', { rounds: this.rounds, note: this.startingNote, seconds: this.seconds }) : t('workout.readyOnce', { note: this.startingNote, seconds: this.seconds });
        },
        headline() {
            if (this.current.kind === 'warmUp') return t('workout.warmUpTitle', { climb: this.current.climb });
            if (this.current.kind === 'review') return t('workout.why.review', { lesson: this.lessonTitle(this.current.lessonId) });
            return this.current.insight ? t(WHY[this.current.insight]) : t('workout.why.steady');
        },
        hint() {
            return this.current.kind === 'warmUp' ? t('workout.warmUpHint') : '';
        },
    },
    async created() {
        await Promise.all([loadLearning(), loadProjects()]);
        const lessons = allLessons()
            .map(({ lesson }) => ({ id: lesson.id, exercise: lesson.steps.find((s) => s.kind === 'play' && s.exercise)?.exercise }))
            .filter((l) => l.exercise);
        // The song you're learning: your most recent project from a song.
        const song = projects.list.filter((p) => p.template && !p.beta).sort((a, b) => b.updatedAt - a.updatedAt)[0];
        this.plan = pickWorkout(learning.progress, lessons, song ? { projectId: song.id, title: shownTitle(song) } : null);
    },
    methods: {
        /** A round's first note ("C#3"): each round starts a half step higher. */
        roundNote(r) {
            const first = [...this.current.exercise.notes].sort((a, b) => a.beat - b.beat)[0];
            return midiName(this.base + this.shift + r + first.step);
        },
        /** A round's tab: hear that round's notes once (again to stop). */
        async hearRound(r) {
            if (this.running) return;
            this.previewRound = r;
            await this.hear();
        },
        lessonTitle(id) {
            const entry = allLessons().find((e) => e.lesson.id === id);
            return entry ? localized(entry.lesson.title) : '';
        },
        move(direction) {
            setWorkoutShift(this.shift + direction).catch((error) => console.error('Not kept', error));
            this.round = 0;
            this.done = 0;
            this.ran = false;
        },
        /** Start: the rounds from the first, one after another. */
        async begin() {
            this.plan.started = true;
            this.round = 0;
            this.done = 0;
            this.ran = false;
            this.running = true;
            await nextTick();
            this.$refs.exercise?.start();
        },
        async stop() {
            this.running = false;
            this.previewing = false;
            await this.$refs.exercise?.stop();
        },
        /** Hear it first: the notes once, nothing sung (again to stop). */
        async hear(event) {
            if (this.previewing) {
                await this.stop();
                return;
            }
            if (event) this.previewRound = 0; // Hear it first: the first round
            this.previewing = true;
            this.running = true;
            await nextTick();
            this.$refs.exercise?.start();
        },
        /** A run ended: after Hear it first, back to ready; a round sung, the next a half step up, or done. */
        async roundDone() {
            if (this.previewing) {
                this.previewing = false;
                this.running = false;
                return;
            }
            if (!this.running) return; // stopped part way: nothing more
            this.done = Math.max(this.done, this.round + 1);
            if (this.round < this.rounds - 1) {
                this.round++;
                await nextTick();
                this.$refs.exercise?.start();
                return;
            }
            this.running = false;
            this.ran = true;
            this.sang = true;
        },
        async skip() {
            if (this.running) await this.stop();
            this.next();
        },
        async next() {
            this.plan.started = true;
            if (this.step < this.plan.steps.length - 1) {
                this.step++;
                this.round = 0;
                this.done = 0;
                this.ran = false;
                return;
            }
            // Everything skipped: nothing sung, so nothing counts (and today's workout is still there).
            if (!this.sang) {
                this.finished = true;
                this.xp = 0;
                this.skippedAll = true;
                return;
            }
            const before = learning.progress.xp.total;
            await workoutDone();
            this.xp = learning.progress.xp.total - before;
            this.finished = true;
        },
    },
};
