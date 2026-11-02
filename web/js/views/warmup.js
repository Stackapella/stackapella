// A teacher's warm-up from a link (docs/warmup-builder-plan.md, the student's side): /warmup#<code>
// opens a card that says what it is (a name if the teacher gave one, its sound, its rounds and about
// how long), with the plan's safety line, Hear it first and Sing it. It's sung in your own range (from
// your comfortable note, as the teacher placed it), a half step up each round and back down, each
// round said plainly ("Round 4: 9 of 9 notes on pitch."). Add to my warm-ups keeps it on this device
// only. Nothing reaches a server: the warm-up is in the link's fragment. A broken link says so, with
// the way to today's workout. Never part of a new singer's first week: only a link opens it.
// With no code, the page is the teacher's side: making one (views/warmup-builder.js).

import { nextTick } from 'vue';
import { PageHeader } from '../components/page.js';
import { Exercise } from '../components/exercise.js';
import { Icon } from '../components/icon.js';
import { midiName } from '../dsp.js';
import { t, tk } from '../i18n.js';
import { navigate } from '../router.js';
import { shifted } from '../workout.js';
import { learning, loadLearning } from '../stores/learning.js';
import { codeFrom, decodeWarmup, warmupExercise, warmupRounds, warmupSeconds } from '../warmup.js';
import { keepWarmup, keptWarmup } from '../stores/warmups.js';
import { WarmupBuilder } from './warmup-builder.js';

const DEFAULT_HOME = 57; // as a lesson's, until the course finds the singer's own
const REST = 2; // beats before each round: Get ready, then Breathe (on the audio clock)
const SHIFT_LIMIT = 7;
const SOUND_NAMES = { ah: tk('warmup.sound.ah'), ee: tk('warmup.sound.ee'), oo: tk('warmup.sound.oo'), oh: tk('warmup.sound.oh'), hum: tk('warmup.sound.hum'), trill: tk('warmup.sound.trill') };

export const WarmupView = {
    components: { PageHeader, Exercise, Icon, WarmupBuilder },
    data: () => ({ code: null, warmup: null, round: 0, running: false, previewing: false, shift: 0, results: [], finished: false, kept: false }),
    setup: () => ({ REST, SHIFT_LIMIT, SOUND_NAMES }),
    template: `
        <div class="mx-auto flex min-h-dvh max-w-2xl flex-col px-4 pb-[max(1rem,env(safe-area-inset-bottom))] pt-[max(0.5rem,env(safe-area-inset-top))] md:px-8" data-warmup>
            <PageHeader :title="learning.loaded && !code ? t('warmup.make.title') : t('warmup.title')" :back="code ? '/' : '/settings'" />
            <p v-if="!learning.loaded" class="text-muted" role="status">{{ t('settings.loading') }}</p>
            <WarmupBuilder v-else-if="!code" />
            <section v-else-if="!warmup" class="card mt-2 space-y-4 p-6" data-warmup-broken>
                <h2 class="text-xl font-bold text-cream">{{ t('warmup.brokenTitle') }}</h2>
                <p class="text-muted">{{ t('warmup.brokenHint') }}</p>
                <button type="button" class="btn-primary" @click="navigate('/workout')">{{ t('warmup.toWorkout') }}</button>
            </section>
            <template v-else>
                <section class="card mt-2 space-y-3 p-5" data-warmup-card>
                    <p class="text-xs font-bold uppercase tracking-[0.14em] text-muted">{{ t('warmup.from') }}</p>
                    <h2 class="text-2xl font-extrabold leading-tight tracking-tight text-cream" :translate="warmup.name ? 'no' : null" data-warmup-name>{{ warmup.name || t('warmup.untitled') }}</h2>
                    <p class="text-cream">{{ t('warmup.about', { sound: t(SOUND_NAMES[warmup.sound]), rounds: rounds.length, minutes }) }}</p>
                    <p class="text-sm text-muted" data-warmup-safety>{{ t('warmup.safety') }}</p>
                </section>
                <section class="mt-5 space-y-5" aria-labelledby="warmup-run">
                    <h2 id="warmup-run" class="sr-only">{{ t('warmup.title') }}</h2>
                    <Exercise ref="exercise" bare :key="shift + ':' + round + (previewing ? ':hear' : '')" :exercise="previewing ? preview : roundExercise" :base="base"
                        :mode="previewing ? 'listen' : 'play'" strictness="relaxed" :color="paint('#4ccfb0')" :rest-beats="previewing ? 0 : REST" :rest-label="round === 0 ? t('workout.getReady') : t('workout.breathe')" @done="roundDone" />
                    <p v-if="running && !previewing" class="-mt-2 text-center text-xs text-muted" data-round-status>{{ t('workout.roundStatus', { number: round + 1, count: rounds.length }) }}</p>
                    <ul v-if="results.length" class="space-y-1 text-sm text-cream" aria-live="polite" data-warmup-results>
                        <li v-for="r in results" :key="r.round">{{ r.lit === null ? t('warmup.roundUnheard', { number: r.round }) : t('warmup.roundResult', { number: r.round, lit: r.lit, count: r.count }) }}</li>
                    </ul>
                    <p v-if="finished" class="font-bold text-cream" role="status" data-warmup-done>{{ t('warmup.done') }}</p>
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <p class="min-w-0 flex-1 text-sm text-muted">{{ t('warmup.ready', { note: startingNote }) }}</p>
                        <div class="flex gap-3">
                            <button v-if="!running || previewing" type="button" class="btn-secondary flex-1 sm:flex-none" @click="hear">{{ previewing ? t('studio.stop') : t('workout.hearFirst') }}</button>
                            <button type="button" class="btn-primary flex flex-1 items-center justify-center gap-2 sm:flex-none" :disabled="previewing" data-warmup-sing @click="running ? stop() : begin()"><Icon :name="running ? 'stop' : 'play'" :size="16" />{{ running ? t('studio.stop') : finished ? t('warmup.again') : t('warmup.sing') }}</button>
                        </div>
                    </div>
                    <div class="card flex items-center gap-3 p-4">
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-cream">{{ t('workout.startingNoteTitle') }}</p>
                            <p class="text-sm text-muted">{{ t('warmup.startingHint') }}</p>
                        </div>
                        <div class="flex shrink-0 items-center gap-1 rounded-full border border-line p-1" role="group" :aria-label="t('workout.startingNoteTitle')">
                            <button type="button" class="grid size-11 place-items-center rounded-full glass-bubble text-cream disabled:opacity-40" :aria-label="t('keyControl.lower')" :disabled="running || shift <= -SHIFT_LIMIT" @click="move(-1)"><Icon name="minus" :size="16" /></button>
                            <span class="min-w-12 text-center text-sm font-semibold tabular-nums text-cream" role="status" translate="no">{{ startingNote }}</span>
                            <button type="button" class="grid size-11 place-items-center rounded-full glass-bubble text-cream disabled:opacity-40" :aria-label="t('keyControl.higher')" :disabled="running || shift >= SHIFT_LIMIT" @click="move(1)"><Icon name="plus" :size="16" /></button>
                        </div>
                    </div>
                    <button type="button" class="btn-ghost btn-sm" :disabled="kept" data-warmup-keep @click="keep"><Icon :name="kept ? 'check' : 'plus'" :size="16" /> {{ kept ? t('warmup.kept') : t('warmup.keep') }}</button>
                </section>
            </template>
        </div>`,
    computed: {
        learning: () => learning,
        home: () => learning.progress.home ?? DEFAULT_HOME,
        base() {
            return this.home + this.shift;
        },
        /** The rounds, as half steps: up, then back down, within your range where it's known. */
        rounds() {
            return this.warmup ? warmupRounds(this.warmup, { home: this.base, range: learning.progress.range ?? null }) : [];
        },
        minutes() {
            return Math.max(1, Math.round(warmupSeconds(this.warmup, this.rounds.length) / 60));
        },
        exercise() {
            return warmupExercise(this.warmup);
        },
        roundExercise() {
            const ex = shifted(this.exercise, this.rounds[this.round] ?? 0);
            return { ...ex, notes: ex.notes.map((n) => ({ ...n, beat: n.beat + REST })) };
        },
        preview() {
            return { ...this.exercise, turn: false };
        },
        startingNote() {
            return midiName(this.base + this.exercise.notes[0].step);
        },
    },
    async created() {
        await loadLearning();
        this.read();
        // Another link opened with this page already open changes only the fragment: read it afresh.
        this.onHash = () => (this.running ? this.stop() : Promise.resolve()).then(() => this.read());
        window.addEventListener('hashchange', this.onHash);
    },
    unmounted() {
        window.removeEventListener('hashchange', this.onHash);
    },
    methods: {
        navigate,
        read() {
            this.code = codeFrom(location.hash);
            this.warmup = this.code ? decodeWarmup(this.code) : null;
            this.kept = !!(this.code && keptWarmup(this.code));
            this.round = 0;
            this.results = [];
            this.finished = false;
            this.shift = 0;
        },
        move(direction) {
            this.shift = Math.max(-SHIFT_LIMIT, Math.min(SHIFT_LIMIT, this.shift + direction));
        },
        async begin() {
            this.round = 0;
            this.results = [];
            this.finished = false;
            this.running = true;
            await nextTick();
            this.$refs.exercise?.start();
        },
        async stop() {
            this.running = false;
            this.previewing = false;
            await this.$refs.exercise?.stop();
        },
        async hear() {
            if (this.previewing) {
                await this.stop();
                return;
            }
            this.previewing = true;
            this.running = true;
            await nextTick();
            this.$refs.exercise?.start();
        },
        /** A round sung: said plainly, then the next (a half step up, or on the way back down), or done. */
        async roundDone(result) {
            if (this.previewing) {
                this.previewing = false;
                this.running = false;
                return;
            }
            if (!this.running) return;
            const count = this.exercise.notes.length;
            this.results = [...this.results, { round: this.round + 1, lit: result?.percent === null || result?.percent === undefined ? null : result?.chart?.lit ?? null, count }];
            if (this.round < this.rounds.length - 1) {
                this.round++;
                await nextTick();
                this.$refs.exercise?.start();
                return;
            }
            this.running = false;
            this.finished = true;
        },
        async keep() {
            this.kept = true;
            await keepWarmup(this.code, this.warmup.name);
        },
    },
};
