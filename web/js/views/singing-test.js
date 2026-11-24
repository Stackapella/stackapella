// The singing test (docs/singing-test-v1.md), about 4 minutes, wordless, in the singer's own range: a glide to warm up,
// their note found, Match a note (1 practice + 12), Sing it back (1 + 6 patterns of four), Hold it (2 × 4 s), then the
// result. Nothing else sounds while they sing (js/singing-test/play.js); after each item only a neutral "heard" tick,
// never a running score; after each event its note map. An item lost to noise is offered again (three times a test at
// most); too little heard and it's kept as practice. Every step is announced (a polite live region), every control is a
// button, and nothing is said by colour alone.

import { AppScreen } from '../components/page.js';
import { VoiceCapture } from '../components/voice-capture.js';
import { Icon } from '../components/icon.js';
import { learning } from '../stores/learning.js';
import { navigate } from '../router.js';
import { count } from '../usage.js';
import { formatNumber, t, tk } from '../i18n.js';
import { engine } from '../audio.js';
import { mockWanted } from '../api-mode.js';
import { anchorFor, SCORING_VERSION, TEST_VERSION } from '../singing-test/items.js';
import { createRun } from '../singing-test/run.js';
import { cancelTurn, turn } from '../singing-test/play.js';
import { glide, WARMUP_SECONDS } from '../singing-test/warmup.js';
import { canMarkWarmUp, anchorHistory, keepTest, markWarmUp, progressOf, readyToDecide, singingTestOn, singingTests, syncTests } from '../singing-test/store.js';
import { EVENTS as POINTS, errors } from '../singing-test/score.js';

const EVENT_NAMES = { match: tk('singingTest.event.match'), back: tk('singingTest.event.back'), hold: tk('singingTest.event.hold') };
const EVENT_HOW = { match: tk('singingTest.how.match'), back: tk('singingTest.how.back'), hold: tk('singingTest.how.hold') };
const BANDS = { finding: tk('singingTest.band.finding'), closer: tk('singingTest.band.closer'), nearly: tk('singingTest.band.nearly'), inTune: tk('singingTest.band.inTune'), precise: tk('singingTest.band.precise') };
const BAND_LINES = { finding: tk('singingTest.bandLine.finding'), closer: tk('singingTest.bandLine.closer'), nearly: tk('singingTest.bandLine.nearly'), inTune: tk('singingTest.bandLine.inTune'), precise: tk('singingTest.bandLine.precise') };
const MEASURE = { match: tk('singingTest.measure.match'), back: tk('singingTest.measure.back'), hold: tk('singingTest.measure.hold') };
// The lesson that trains each event: one thing to work on, linked.
const LESSONS = { match: { id: '1-match', name: tk('singingTest.lesson.match') }, back: { id: '1-challenge', name: tk('singingTest.lesson.back') }, hold: { id: '2-hold', name: tk('singingTest.lesson.hold') } };
const ORDER = ['match', 'back', 'hold'];
// The result's breakdown: one plain number a part.
const ROWS = { match: tk('singingTest.row.match'), back: tk('singingTest.row.back'), hold: tk('singingTest.row.hold') };
// The start's five parts, and How it's scored's measures, as their words' keys.
const START_PARTS = ['warmup', 'note', 'match', 'back', 'hold'].map((key) => ({ key, name: { warmup: tk('singingTest.start.part.warmup'), note: tk('singingTest.start.part.note'), match: tk('singingTest.start.part.match'), back: tk('singingTest.start.part.back'), hold: tk('singingTest.start.part.hold') }[key], hint: { warmup: tk('singingTest.start.partHint.warmup'), note: tk('singingTest.start.partHint.note'), match: tk('singingTest.start.partHint.match'), back: tk('singingTest.start.partHint.back'), hold: tk('singingTest.start.partHint.hold') }[key] }));
const HOW_MEASURES = { match: tk('singingTest.howPage.measures.match'), back: tk('singingTest.howPage.measures.back'), hold: tk('singingTest.howPage.measures.hold') };
// Where the steps are: the warm-up and finding the note, then the three events.
const PARTS = 5;

const deviceKind = () => (matchMedia('(pointer: coarse)').matches || innerWidth < 768 ? 'phone' : 'computer');
const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

/** One event's note map: each item's dot against its target (a line), or a ring where it wasn't heard. */
const NoteMap = {
    props: { event: { type: String, required: true }, marks: { type: Array, required: true } },
    computed: {
        dots() {
            const clamp = (c) => Math.max(-150, Math.min(150, c));
            if (this.event === 'match') return this.marks.map((m) => [{ cents: m.measures.centsOff === null ? null : clamp(m.measures.centsOff) }]);
            if (this.event === 'back') return this.marks.map((m) => m.measures.notes.map((n) => ({ cents: n.centsOff === null ? null : clamp(n.centsOff) })));
            return this.marks.map((m) => [{ cents: m.measures.spreadCents === null ? null : clamp(m.measures.spreadCents) }]);
        },
        heardCount: (vm) => vm.marks.filter((m) => m.heard).length,
        width: (vm) => vm.dots.reduce((w, group) => w + group.length * 18 + 14, 0),
    },
    methods: {
        x(gi, di) {
            return this.dots.slice(0, gi).reduce((w, g) => w + g.length * 18 + 14, 0) + di * 18 + 9;
        },
    },
    template: `
        <figure class="space-y-2" data-note-map :data-event="event">
            <svg :viewBox="'0 0 ' + width + ' 80'" class="h-20 w-full max-w-full" preserveAspectRatio="xMinYMid meet" role="img" :aria-label="t('singingTest.map.label', { heard: heardCount, count: marks.length })">
                <line x1="0" :x2="width" y1="40" y2="40" class="stroke-line" stroke-width="1" />
                <template v-for="(group, gi) in dots" :key="gi">
                    <template v-for="(d, di) in group" :key="di">
                        <line :x1="x(gi, di)" :x2="x(gi, di)" y1="34" y2="46" class="stroke-line" stroke-width="1" />
                        <circle v-if="d.cents !== null" :cx="x(gi, di)" :cy="40 - d.cents * 0.25" r="4.5" class="fill-cream" />
                        <circle v-else :cx="x(gi, di)" cy="40" r="4" class="fill-none stroke-subtle" stroke-width="1.5" />
                    </template>
                </template>
            </svg>
            <figcaption class="t-hint">{{ t('singingTest.map.caption', { heard: heardCount, count: marks.length }) }}</figcaption>
        </figure>`,
};

export const SingingTestView = {
    components: { AppScreen, VoiceCapture, Icon, NoteMap },
    data: () => ({ step: 'start', event: null, phase: null, current: null, marks: [], maps: {}, run: null, anchor: null, found: null, moved: false, said: '', warm: null, warmLeft: 0, test: null, leaving: false, saving: false, failed: '' }),
    setup: () => ({ formatNumber, ORDER, EVENT_NAMES, EVENT_HOW, START_PARTS, navigate }),
    computed: {
        on: () => singingTestOn(),
        /** The step's place out of five: the warm-up, the note, then each event. */
        part() {
            if (this.step === 'warmup') return 1;
            if (this.step === 'note') return 2;
            if (this.event) return 3 + ORDER.indexOf(this.event);
            return null;
        },
        title() {
            if (this.step === 'warmup') return t('singingTest.warmup.title');
            if (this.step === 'note') return t('singingTest.note.title');
            if (this.event) return t(EVENT_NAMES[this.event]);
            if (this.step === 'result') return t('singingTest.result.title');
            return t('singingTest.title');
        },
        eyebrow: (vm) => (vm.part ? t('singingTest.part', { n: vm.part, count: PARTS }) : null),
        /** What the screen says now, as a caption (and to a screen reader). */
        caption() {
            if (this.phase === 'listen') return t('singingTest.say.listen');
            if (this.phase === 'sing') return this.event === 'hold' ? t('singingTest.say.hold') : t('singingTest.say.sing');
            if (this.phase === 'heard') return t('singingTest.say.heard');
            if (this.phase === 'again') return t('singingTest.say.again');
            if (this.phase === 'missed') return t('singingTest.say.missed');
            return '';
        },
        itemLabel() {
            if (!this.current) return '';
            if (this.current.practice) return t('singingTest.practiceItem');
            const steps = this.run.items.filter((s) => s.event === this.event && !s.practice);
            return t('singingTest.itemOf', { n: steps.indexOf(this.current) + 1, count: steps.length });
        },
        /** The part's scored items as dots: done, now, or to come (the practice item before them has none). */
        dots() {
            if (!this.run || !this.event) return [];
            const steps = this.run.items.filter((s) => s.event === this.event && !s.practice);
            const done = this.marks.filter((m) => !m.step.practice).length;
            return steps.map((s, i) => (i < done ? 'done' : s === this.current ? 'now' : 'next'));
        },
        // The result's lines.
        result() {
            const test = this.test;
            if (!test) return null;
            const e = errors(test);
            const shares = ORDER.filter((k) => test.scores[k] !== null && e[k] !== null).map((k) => ({ k, share: test.scores[k] / POINTS[k].weight, error: e[k] }));
            const best = [...shares].sort((a, b) => b.share - a.share)[0] ?? null;
            const weak = [...shares].sort((a, b) => a.share - b.share)[0] ?? null;
            const plain = (s) => (s ? t(MEASURE[s.k], { cents: formatNumber(Math.round(s.error)) }) : '');
            return {
                practice: test.status === 'practice',
                band: test.band ? t(BANDS[test.band]) : '',
                bandLine: test.band ? t(BAND_LINES[test.band]) : '',
                // The lowest band shows its words, never a number.
                total: test.band && test.band !== 'finding' ? formatNumber(test.total) : null,
                well: best ? { name: t(EVENT_NAMES[best.k]), line: plain(best) } : null,
                // One thing to work on only where points were lost (under 95% of the event's): never a nitpick at the top.
                work: weak && weak !== best && weak.share < 0.95 ? { name: t(EVENT_NAMES[weak.k]), line: plain(weak), lesson: LESSONS[weak.k] } : null,
                progress: progressOf(test),
                rows: ORDER.map((k) => ({ k, name: t(EVENT_NAMES[k]), points: test.scores[k] === null ? null : formatNumber(test.scores[k]), of: formatNumber(POINTS[k].weight), line: e[k] === null ? t('singingTest.row.unheard') : t(ROWS[k], { cents: formatNumber(Math.round(e[k])) }) })),
                warmUp: canMarkWarmUp(test),
            };
        },
    },
    async created() {
        // Off, or still in their first week: Your voice (once the switches and the course progress are known).
        await readyToDecide();
        if (!singingTestOn()) navigate('/voice', { replace: true });
    },
    beforeUnmount() {
        this.leaving = true;
        this.warm?.stop();
        cancelTurn();
    },
    methods: {
        say(words) {
            this.said = '';
            this.$nextTick(() => (this.said = words));
        },
        begin() {
            count('st_start');
            this.step = 'warmup';
            this.warmUp();
        },
        async warmUp() {
            const note = Number.isFinite(learning.progress?.home) ? learning.progress.home : 57;
            try {
                this.warm = await glide(note);
            } catch (error) {
                console.warn('The warm-up did not play', error);
                this.step = 'note';
                return;
            }
            this.say(t('singingTest.warmup.say'));
            while (!this.leaving && this.step === 'warmup') {
                this.warmLeft = Math.max(0, Math.ceil(this.warm.endsAt - engine.ctx.currentTime));
                if (!this.warmLeft) break;
                await wait(250);
            }
            if (this.step === 'warmup') this.step = 'note';
        },
        skipWarmUp() {
            this.warm?.stop();
            this.step = 'note';
        },
        /** Their note, found: the test's anchor (the first test's stays, unless it moves twice in a row). */
        noteFound(note) {
            this.found = Math.round(note * 10) / 10;
            const { anchor, moved } = anchorFor(anchorHistory(), this.found);
            this.anchor = anchor;
            this.moved = moved;
            // The flows sing through a stand-in (a synthetic voice, as the engine's unit tests do), with the mock API only.
            const stand = mockWanted() && typeof globalThis.stSingingTurn === 'function' ? globalThis.stSingingTurn : null;
            this.run = createRun(anchor, { turn: stand ?? turn });
            this.startEvent('match');
        },
        startEvent(event) {
            this.event = event;
            this.step = 'intro';
            this.phase = null;
            this.marks = [];
            this.say(t(EVENT_HOW[event]));
        },
        async singEvent() {
            this.step = 'items';
            for (const step of this.run.items.filter((s) => s.event === this.event)) {
                if (this.leaving) return;
                this.current = step;
                this.phase = 'listen';
                this.say(this.caption);
                const res = await this.run.sing(step, {
                    onAnswer: (at) => wait(Math.max(0, (at - engine.ctx.currentTime) * 1000)).then(() => {
                        if (this.current === step && !this.leaving) {
                            this.phase = 'sing';
                            this.say(this.caption);
                        }
                    }),
                    onRetry: () => {
                        this.phase = 'again';
                        this.say(this.caption);
                    },
                });
                if (this.leaving) return;
                this.phase = res.heard ? 'heard' : 'missed';
                this.say(this.caption);
                this.marks.push({ step, ...res });
                await wait(700);
            }
            this.current = null;
            this.phase = null;
            count(`st_event:${this.event}`);
            this.maps = { ...this.maps, [this.event]: this.marks.filter((m) => !m.step.practice) };
            this.step = 'map';
            this.say(t('singingTest.map.done', { name: t(EVENT_NAMES[this.event]) }));
        },
        next() {
            const at = ORDER.indexOf(this.event);
            if (at < ORDER.length - 1) this.startEvent(ORDER[at + 1]);
            else this.finish();
        },
        async finish() {
            this.event = null;
            this.step = 'result';
            const body = this.run.request({ id: crypto.randomUUID(), takenAt: new Date().toISOString(), device: deviceKind() });
            this.test = keepTest({ ...body, found: this.found });
            count(this.test.status === 'practice' ? 'st_practice' : 'st_done');
            this.say(this.test.status === 'practice' ? t('singingTest.result.practice') : t('singingTest.result.said', { band: this.result.band }));
            await syncTests();
            this.test = singingTests.tests.find((x) => x.id === this.test.id) ?? this.test;
        },
        async toggleWarmUp() {
            try {
                await markWarmUp(this.test, this.test.status !== 'warmup');
                this.test = singingTests.tests.find((x) => x.id === this.test.id) ?? this.test;
            } catch (error) {
                console.warn('The warm-up mark was not kept', error);
            }
        },
    },
    template: `
        <AppScreen :title="title" back="/voice" :eyebrow="eyebrow" column="max-w-[560px]" measure="max-w-[560px]" data-ui="singing-test">
            <p class="sr-only" aria-live="polite" data-said>{{ said }}</p>

            <!-- The start: what it is, how long, and the early version said plainly. -->
            <section v-if="step === 'start'" class="flex flex-col gap-6" data-st-start>
                <p class="t-lead">{{ t('singingTest.start.lead') }}</p>
                <ol class="space-y-2.5" :aria-label="t('singingTest.start.parts')">
                    <li v-for="(p, i) in START_PARTS" :key="p.key" class="flex items-baseline gap-3">
                        <span class="t-counter w-5 shrink-0 tabular-nums">{{ formatNumber(i + 1) }}</span>
                        <span><span class="font-semibold text-cream">{{ t(p.name) }}</span> <span class="t-hint">{{ t(p.hint) }}</span></span>
                    </li>
                </ol>
                <p class="t-hint">{{ t('singingTest.start.quiet') }}</p>
                <p class="alert-inline" style="--alert-c: var(--color-accent)" data-st-early><span>{{ t('singingTest.early') }}</span></p>
                <button type="button" class="btn-primary h-[52px] self-start px-7 text-[17px] max-sm:w-full" data-st-begin @click="begin">{{ t('singingTest.start.begin') }}</button>
            </section>

            <!-- The warm-up: a glide to follow, nothing measured. -->
            <section v-else-if="step === 'warmup'" class="flex flex-col gap-5" data-st-warmup>
                <p class="t-lead">{{ t('singingTest.warmup.lead') }}</p>
                <p class="t-clock tabular-nums" aria-hidden="true">{{ formatNumber(warmLeft) }}<span class="t-clock-of"> s</span></p>
                <button type="button" class="link inline-flex min-h-11 items-center self-start" data-st-skip @click="skipWarmUp">{{ t('singingTest.warmup.skip') }}</button>
            </section>

            <!-- Their note: VoiceCapture's note mode, as lesson 1 finds it. -->
            <section v-else-if="step === 'note'" class="flex flex-col gap-5" data-st-note>
                <p class="t-lead">{{ t('singingTest.note.lead') }}</p>
                <VoiceCapture mode="note" color="var(--color-accent)" @found="noteFound" />
            </section>

            <!-- An event: what to do, then Start. -->
            <section v-else-if="step === 'intro'" class="flex flex-col gap-6" data-st-intro :data-event="event">
                <p class="t-lead">{{ t(EVENT_HOW[event]) }}</p>
                <p v-if="event === 'match' && moved" class="t-hint" data-st-moved>{{ t('singingTest.note.moved') }}</p>
                <button type="button" class="btn-primary h-[52px] self-start px-7 text-[17px] max-sm:w-full" data-st-event-start @click="singEvent">{{ t('singingTest.eventStart') }}</button>
            </section>

            <!-- The items: listen, then your turn; a neutral tick after each, never a score. -->
            <section v-else-if="step === 'items'" class="flex min-h-[55dvh] flex-col items-center gap-6 pt-6 text-center" data-st-items :data-phase="phase" :data-event="event">
                <p class="t-counter" data-st-item>{{ itemLabel }}</p>
                <span class="grid size-24 place-items-center rounded-full" :class="phase === 'sing' ? 'bg-accent text-accent-ink' : 'bg-raised text-cream'" aria-hidden="true">
                    <Icon :name="phase === 'listen' ? 'volume' : phase === 'heard' ? 'check' : 'mic'" :size="36" />
                </span>
                <p class="text-[22px] font-semibold text-cream" data-st-caption>{{ caption }}</p>
                <!-- Where they are in the part: a dot an item, filled once sung, ringed for the one now (its label says it in words). -->
                <ol class="mt-auto flex flex-wrap justify-center gap-2 pb-4" aria-hidden="true" data-st-dots>
                    <li v-for="(dot, i) in dots" :key="i" class="size-2.5 rounded-full" :class="dot === 'done' ? 'bg-cream' : dot === 'now' ? 'ring-2 ring-accent ring-offset-2 ring-offset-ink' : 'bg-line'"></li>
                </ol>
            </section>

            <!-- After an event: its note map, then the next. -->
            <section v-else-if="step === 'map'" class="flex flex-col gap-6" data-st-map :data-event="event">
                <p class="t-lead">{{ t('singingTest.map.lead') }}</p>
                <NoteMap :event="event" :marks="maps[event]" />
                <button type="button" class="btn-primary h-[52px] self-start px-7 text-[17px] max-sm:w-full" data-st-next @click="next">{{ event === 'hold' ? t('singingTest.seeResult') : t('singingTest.nextEvent', { name: t(EVENT_NAMES[ORDER[ORDER.indexOf(event) + 1]]) }) }}</button>
            </section>

            <!-- The result: the band (its number only above the lowest), what went well, one thing to work on, the early version. -->
            <section v-else-if="step === 'result' && result" class="flex flex-col gap-6" data-st-result :data-status="test.status" :data-band="test.band">
                <template v-if="result.practice">
                    <p class="t-lead" data-st-practice>{{ t('singingTest.result.practice') }}</p>
                </template>
                <template v-else>
                    <div class="flex flex-col gap-1.5">
                        <p v-if="result.total !== null" class="t-hero tabular-nums text-cream" data-st-total>{{ result.total }}<span class="t-clock-of"> / {{ formatNumber(100) }}</span></p>
                        <p class="text-[22px] font-semibold text-cream" data-st-band>{{ result.band }}</p>
                        <p class="t-lead">{{ result.bandLine }}</p>
                    </div>
                    <!-- How the score is made, at a glance: each part's points and one plain number (never in the lowest band). -->
                    <ul v-if="result.total !== null" class="card divide-y divide-line" :aria-label="t('singingTest.result.breakdown')" data-st-breakdown>
                        <li v-for="row in result.rows" :key="row.k" class="flex items-baseline justify-between gap-4 px-5 py-3" :data-st-row="row.k">
                            <span class="min-w-0"><span class="block font-semibold text-cream">{{ row.name }}</span><span class="t-hint">{{ row.line }}</span></span>
                            <span class="shrink-0 tabular-nums text-cream"><template v-if="row.points !== null">{{ row.points }}</template><template v-else>–</template><span class="t-hint"> / {{ row.of }}</span></span>
                        </li>
                    </ul>
                    <p v-if="result.progress" class="font-semibold text-cream" data-st-progress>{{ result.progress === 'improved' ? t('singingTest.result.improved') : t('singingTest.result.same') }}</p>
                    <dl class="space-y-4">
                        <div v-if="result.well" data-st-well>
                            <dt class="t-section">{{ t('singingTest.result.wellTitle') }}</dt>
                            <dd class="mt-1 text-cream"><span class="font-semibold">{{ result.well.name }}.</span> {{ result.well.line }}</dd>
                        </div>
                        <div v-if="result.work" data-st-work>
                            <dt class="t-section">{{ t('singingTest.result.workTitle') }}</dt>
                            <dd class="mt-1 text-cream"><span class="font-semibold">{{ result.work.name }}.</span> {{ result.work.line }}
                                <a :href="'/learn/' + result.work.lesson.id" class="link inline-flex min-h-11 items-center" data-st-lesson @click.prevent="navigate('/learn/' + result.work.lesson.id)">{{ t('singingTest.result.lesson', { lesson: t(result.work.lesson.name) }) }}</a>
                            </dd>
                        </div>
                    </dl>
                    <button v-if="result.warmUp" type="button" class="link inline-flex min-h-11 items-center self-start" :aria-pressed="test.status === 'warmup' ? 'true' : 'false'" data-st-warmup-mark @click="toggleWarmUp">{{ test.status === 'warmup' ? t('singingTest.result.countIt') : t('singingTest.result.warmUp') }}</button>
                </template>
                <p class="alert-inline" style="--alert-c: var(--color-accent)" data-st-early><span>{{ t('singingTest.early') }}</span></p>
                <div class="flex flex-wrap items-center gap-x-5 gap-y-2">
                    <button type="button" class="btn-primary h-[52px] px-7 text-[17px] max-sm:w-full" data-st-done @click="navigate('/voice')">{{ t('singingTest.result.done') }}</button>
                    <a href="/voice/test/how" class="link inline-flex min-h-11 items-center" data-st-how @click.prevent="navigate('/voice/test/how')">{{ t('singingTest.how.link') }}</a>
                </div>
            </section>
        </AppScreen>`,
};

/**
 * How it's scored (Your voice, docs/singing-test-v1.md): the events, what each measures, its tolerances and weight, the
 * bands and the versions, from the scoring itself (js/singing-test/score.js), so the page can never disagree with it.
 */
export const SingingTestHowView = {
    components: { AppScreen },
    setup: () => ({ POINTS, ORDER, EVENT_NAMES, BANDS, HOW_MEASURES, formatNumber, TEST_VERSION, SCORING_VERSION }),
    computed: {
        bands: () => [
            { band: 'precise', range: t('singingTest.howPage.under', { cents: formatNumber(15) }) },
            { band: 'inTune', range: t('singingTest.howPage.between', { from: formatNumber(15), to: formatNumber(35) }) },
            { band: 'nearly', range: t('singingTest.howPage.between', { from: formatNumber(35), to: formatNumber(60) }) },
            { band: 'closer', range: t('singingTest.howPage.between', { from: formatNumber(60), to: formatNumber(100) }) },
            { band: 'finding', range: t('singingTest.howPage.over', { cents: formatNumber(100) }) },
        ],
    },
    template: `
        <AppScreen :title="t('singingTest.howPage.title')" back="/voice" column="max-w-[620px]" measure="max-w-[620px]" data-ui="singing-test-how">
            <div class="flex flex-col gap-7">
                <p class="t-lead">{{ t('singingTest.howPage.lead') }}</p>
                <section class="space-y-3" aria-labelledby="st-how-events">
                    <h2 id="st-how-events" class="t-section">{{ t('singingTest.howPage.eventsTitle') }}</h2>
                    <ul class="card divide-y divide-line">
                        <li v-for="k in ORDER" :key="k" class="space-y-1 px-5 py-4" :data-how-event="k">
                            <p class="flex items-baseline justify-between gap-3"><span class="font-semibold text-cream">{{ t(EVENT_NAMES[k]) }}</span><span class="t-hint tabular-nums">{{ t('singingTest.howPage.points', { n: formatNumber(POINTS[k].weight) }) }}</span></p>
                            <p class="text-sm text-muted">{{ t(HOW_MEASURES[k]) }}</p>
                            <p class="t-hint">{{ t('singingTest.howPage.range', { full: formatNumber(POINTS[k].ceiling), none: formatNumber(POINTS[k].floor) }) }}</p>
                        </li>
                    </ul>
                </section>
                <section class="space-y-3" aria-labelledby="st-how-bands">
                    <h2 id="st-how-bands" class="t-section">{{ t('singingTest.howPage.bandsTitle') }}</h2>
                    <p class="text-sm text-muted">{{ t('singingTest.howPage.bandsLead') }}</p>
                    <ul class="card divide-y divide-line">
                        <li v-for="b in bands" :key="b.band" class="flex items-baseline justify-between gap-3 px-5 py-3"><span class="text-cream">{{ t(BANDS[b.band]) }}</span><span class="t-hint tabular-nums">{{ b.range }}</span></li>
                    </ul>
                </section>
                <section class="space-y-2" aria-labelledby="st-how-fair">
                    <h2 id="st-how-fair" class="t-section">{{ t('singingTest.howPage.fairTitle') }}</h2>
                    <ul class="list-disc space-y-1.5 ps-5 text-sm text-muted">
                        <li>{{ t('singingTest.howPage.fair.range') }}</li>
                        <li>{{ t('singingTest.howPage.fair.octave') }}</li>
                        <li>{{ t('singingTest.howPage.fair.unheard') }}</li>
                        <li>{{ t('singingTest.howPage.fair.quiet') }}</li>
                    </ul>
                </section>
                <p class="t-hint" data-how-versions>{{ t('singingTest.howPage.versions', { test: formatNumber(TEST_VERSION), scoring: formatNumber(SCORING_VERSION) }) }}</p>
                <p class="alert-inline" style="--alert-c: var(--color-accent)"><span>{{ t('singingTest.early') }}</span></p>
            </div>
        </AppScreen>`,
};
