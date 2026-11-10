// Today's workout (docs/vocal-coaching-plan.md, "Today's workout"; js/workout.js picks it; Hillel's
// 85.png, ux-spec 5.7f): a warm-up in rounds a half step apart, one weak spot, one lesson to review,
// each as listen-then-your-turn. The header says where you are and how long it takes; the headline
// says what the exercise is; the lane shows one round at a time; Ready when you are with Hear it first
// and Start; the starting note with Lower and Higher (a semitone, remembered); and a foot that says
// what Continue waits for, with Skip as a clear way out. Then a phrase of the song you're learning.
// Done, it counts the day toward the weekly goal with its XP. Free: one a day; Plus: as many as you
// like (docs/features.md). With a teacher's warm-up kept on this device (docs/warmup-builder-plan.md),
// the warm-up step offers it in place of ours, remembered on the device; nobody else sees the choice.

import { VoiceCareLine } from '../components/voice-care.js';
import { nextTick } from 'vue';
import { AppScreen } from '../components/page.js';
import { midiName } from '../dsp.js';
import { localized, t, tk } from '../i18n.js';
import { goBack, navigate } from '../router.js';
import { pickWorkout, shifted, stepSeconds, exerciseSeconds } from '../workout.js';
import { readingToday } from '../reading-lines.js';
import { account } from '../stores/account.js';
import { allLessons, learning, loadLearning, setWorkoutShift, workoutDone } from '../stores/learning.js';
import { loadProjects, projects, shownTitle } from '../stores/projects.js';
import { Exercise } from '../components/exercise.js';
import { Icon } from '../components/icon.js';
import { game, sk } from '../skin.js';
import { decodeWarmup, warmupStep } from '../warmup.js';
import { chooseWarmup, chosenWarmup, keptWarmups } from '../stores/warmups.js';

const DEFAULT_HOME = 57; // as a lesson's, until unit 1 finds the learner's own
const REST = 2; // beats of rest before each round: Get ready, then Breathe between rounds (on the audio clock)
const SHIFT_LIMIT = 7; // stores/learning.js keeps the shift within this, either way
const TITLES = { warmUp: tk('workout.step.warmUp'), weakSpot: tk('workout.step.weakSpot'), review: tk('workout.step.review'), read: tk('workout.step.read') };
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
    components: { VoiceCareLine, AppScreen, Exercise, Icon },
    data: () => ({ kept: [], choice: null, plan: null, step: 0, round: 0, running: false, previewing: false, previewRound: 0, done: 0, ran: false, sang: false, skippedAll: false, finished: false, xp: 0 }),
    setup: () => ({ learning, navigate, goBack, TITLES, WHY, SHIFT_LIMIT, REST, midiName, game, sk }),
    template: `
        <AppScreen :title="sk('workout.title', 'game.workout.title')" back="/learn" measure="max-w-2xl" foot="pb-[max(1rem,env(safe-area-inset-bottom))]">
                <template #title>
                    <span class="block text-xs font-medium tracking-normal text-muted">{{ sk('workout.title', 'game.workout.title') }}</span>
                    <span class="block text-base font-bold tracking-normal">{{ plan && !finished ? t(TITLES[current.kind]) : sk('workout.title', 'game.workout.title') }}</span>
                </template>
                <template #aside>
                    <div v-if="plan && !finished" class="flex items-center gap-3">
                        <span class="text-xs text-muted tabular-nums">{{ t('workout.stepOf', { step: step + 1, count: plan.steps.length }) }}</span>
                        <span class="flex w-16 gap-1" aria-hidden="true"><span v-for="(s, i) in plan.steps" :key="i" class="h-1 flex-1 rounded-full" :class="i <= step ? 'bg-accent' : 'bg-line'"></span></span>
                    </div>
                </template>

            <p v-if="!learning.loaded" class="text-muted" role="status">{{ t('settings.loading') }}</p>
            <section v-else-if="finished" class="card mt-4 space-y-4 p-6 text-center" aria-live="polite" data-workout-end>
                <span class="bubble bubble-lg mx-auto grid size-14 place-items-center" style="--bubble: var(--color-part-4); --bubble-ink: var(--color-part-text-4)"><Icon name="star" :size="28" /></span>
                <h2 class="text-xl font-bold text-cream">{{ t('workout.doneTitle') }}</h2>
                <p class="text-muted">{{ skippedAll ? t('workout.skippedAll') : xp && game() ? t('workout.doneXp', { xp }) : t('workout.doneHint') }}</p>
                <VoiceCareLine />
                <button v-if="plan.song" type="button" class="btn-primary" @click="navigate('/p/' + plan.song.projectId + '/sing')">{{ t('workout.song', { title: plan.song.title }) }}</button>
                <button type="button" class="btn-go" @click="navigate('/learn')">{{ t('workout.toCourse') }}</button>
            </section>
            <template v-else-if="plan">
                <section class="mt-2 space-y-5" :aria-labelledby="'workout-step-' + step">
                    <div>
                        <h2 :id="'workout-step-' + step" class="text-2xl font-extrabold leading-tight tracking-tight text-cream md:text-3xl">{{ headline }}</h2>
                        <p v-if="hint" class="mt-1 text-muted">{{ hint }}</p>
                    </div>
                    <!-- Only with a teacher's warm-up kept on this device: which warm-up, ours or theirs. -->
                    <label v-if="current.kind === 'warmUp' && kept.length" class="flex flex-wrap items-center gap-3" data-warmup-choice>
                        <span class="text-sm font-semibold text-cream">{{ t('workout.warmUpChoice') }}</span>
                        <select class="input w-auto min-w-0 max-w-full" :value="choice ?? ''" :disabled="running" @change="choose($event.target.value || null)">
                            <option value="">{{ t('workout.warmUpOurs') }}</option>
                            <option v-for="w in kept" :key="w.code" :value="w.code">{{ w.name || t('warmup.untitled') }}</option>
                        </select>
                    </label>
                    <!-- Hillel's warm-up design: the rounds as tabs over the notes, each with its starting note,
                         ticked when sung; a tap hears that round. -->
                    <div v-if="rounds > 1 && !current.roundShifts" class="grid grid-cols-3 overflow-hidden rounded-2xl border border-edge" role="tablist" :aria-label="t('workout.roundsLabel')" data-round-tabs>
                        <button v-for="r in rounds" :key="r" type="button" role="tab" class="flex min-h-14 flex-col items-start justify-center gap-0.5 border-b-2 px-3 py-2 text-start transition disabled:cursor-default"
                            :class="[r - 1 === round ? 'border-accent bg-raised/60' : 'border-transparent hover:bg-raised/40', r > 1 ? 'border-s border-s-edge' : '']" :aria-selected="r - 1 === round ? 'true' : 'false'" :disabled="running"
                            :aria-label="t('workout.hearRound', { number: r, note: roundNote(r - 1) })" @click="hearRound(r - 1)">
                            <span class="flex items-center gap-1 text-xs text-muted">{{ t('workout.round', { number: r }) }}<Icon v-if="r <= done" name="check" :size="12" class="text-good" /></span>
                            <span class="text-sm font-semibold text-cream" translate="no">{{ t('workout.startsOn', { note: roundNote(r - 1) }) }}</span>
                        </button>
                    </div>
                    <Exercise ref="exercise" bare :key="step + ':' + shift + ':' + round + (previewing ? ':hear' + previewRound : '')" :exercise="previewing ? preview : roundExercise" :base="base"
                        :mode="previewing ? 'listen' : 'play'" strictness="relaxed" :color="paint('#4ccfb0')" :rest-beats="previewing || current.kind === 'read' ? 0 : REST" :rest-label="round === 0 ? t('workout.getReady') : t('workout.breathe')" @done="roundDone" />
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
                    <!-- The starting note: a compact − and +, held while a round plays (a line to read keeps your do). -->
                    <div v-if="current.kind !== 'read'" class="card flex items-center gap-3 p-4">
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
        </AppScreen>`,
    computed: {
        base: () => learning.progress.home ?? DEFAULT_HOME,
        shift: () => learning.progress.workoutShift ?? 0,
        /** The step under way: the warm-up is the kept one chosen on this device, when there is one. */
        current() {
            const step = this.plan.steps[this.step];
            if (step.kind !== 'warmUp' || !this.chosen) return step;
            return warmupStep(this.chosen, { home: this.base + this.shift, range: learning.progress.range ?? null });
        },
        chosen() {
            return this.choice ? decodeWarmup(this.choice) : null;
        },
        rounds() {
            return this.current.rounds ?? 1;
        },
        /** This round's exercise: moved by Lower or Higher, and a half step up for each round before. */
        roundExercise() {
            // A line to read (Sight-singing): as written, in the key of your do; its look and count-in lead in.
            if (this.current.kind === 'read') return this.current.exercise;
            const ex = shifted(this.current.exercise, this.shift + this.roundShift(this.round));
            return { ...ex, notes: ex.notes.map((n) => ({ ...n, beat: n.beat + REST })) };
        },
        /** Hear it first (or a round's tab): the notes once, as they'll be sung in that round. */
        preview() {
            // A line to read, heard first: played by the guide, on the stave (a take after it still reads cold).
            if (this.current.kind === 'read') return { ...this.current.exercise, turn: false, guide: true, hearFirst: false, drone: undefined };
            return { ...shifted(this.current.exercise, this.shift + this.roundShift(this.previewRound)), turn: false };
        },
        startingNote() {
            const first = [...this.current.exercise.notes].sort((a, b) => a.beat - b.beat)[0];
            return midiName(this.base + (this.current.kind === 'read' ? 0 : this.shift) + first.step);
        },
        seconds() {
            return Math.round(stepSeconds(this.current) / 5) * 5 || Math.round(exerciseSeconds(this.current.exercise));
        },
        readyLine() {
            return this.rounds > 1 ? t('workout.readyRounds', { rounds: this.rounds, note: this.startingNote, seconds: this.seconds }) : t('workout.readyOnce', { note: this.startingNote, seconds: this.seconds });
        },
        headline() {
            if (this.current.roundShifts) return this.current.name || t('warmup.from');
            if (this.current.kind === 'warmUp') return t('workout.warmUpTitle', { climb: this.current.climb });
            if (this.current.kind === 'review') return t('workout.why.review', { lesson: this.lessonTitle(this.current.lessonId) });
            if (this.current.kind === 'read') return t('workout.why.read');
            return this.current.insight ? t(WHY[this.current.insight]) : t('workout.why.steady');
        },
        hint() {
            if (this.current.roundShifts) return t('warmup.safety');
            if (this.current.kind === 'read') return t('workout.readHint');
            return this.current.kind === 'warmUp' ? t('workout.warmUpHint') : '';
        },
    },
    async created() {
        await Promise.all([loadLearning(), loadProjects()]);
        this.kept = keptWarmups().filter((w) => decodeWarmup(w.code));
        this.choice = chosenWarmup();
        const lessons = allLessons()
            .map(({ lesson }) => ({ id: lesson.id, exercise: lesson.steps.find((s) => s.kind === 'play' && s.exercise)?.exercise }))
            .filter((l) => l.exercise);
        // The song you're learning: your most recent project from a song.
        const song = projects.list.filter((p) => p.template && !p.beta).sort((a, b) => b.updatedAt - a.updatedAt)[0];
        // Started Sight-singing (and past the first week): a line to read now and then (js/reading-lines.js).
        this.plan = pickWorkout(learning.progress, lessons, song ? { projectId: song.id, title: shownTitle(song) } : null, readingToday(learning.progress));
    },
    methods: {
        /** A round's first note ("C#3"): each round starts a half step higher. */
        roundNote(r) {
            const first = [...this.current.exercise.notes].sort((a, b) => a.beat - b.beat)[0];
            return midiName(this.base + this.shift + this.roundShift(r) + first.step);
        },
        /** How far round `r` is moved: a half step a round for ours; a kept warm-up's own, up and back down. */
        roundShift(r) {
            return this.current.roundShifts?.[r] ?? r;
        },
        /** Ours or a kept warm-up (null: ours), remembered on this device. */
        choose(code) {
            chooseWarmup(code);
            this.choice = code;
            this.round = 0;
            this.done = 0;
            this.ran = false;
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
