// Your voice (docs/vocal-coaching-plan.md, "Your voice"; Hillel's 63.png): your range big, with Hear
// it and Measure again; a keyboard with your range shaded (tap a key to hear it) and how much of each
// part's usual range you can sing; the four numbers (lowest, highest, most comfortable, span) and a
// part to find; your badges by kind, the ones still to earn with how far there is to go; with Plus,
// your history (on pitch over time, the way you tend to lean, recent highlights; without Plus a
// sample shows what it would be, not a lock wall); and your week.

import { api } from '../api.js';
import { listTemplates } from '../templates.js';
import { midiName } from '../dsp.js';
import { formatDate, formatNumber, t, tk } from '../i18n.js';
import { goBack, navigate } from '../router.js';
import { levelFor, VOICE_TYPES, voiceType } from '../course.js';
import { hearNotes } from '../creator-audio.js';
import { RangeDialog } from '../components/dialogs.js';
import { BADGES, GROUPS } from '../badges.js';
import { account } from '../stores/account.js';
import { learning, loadLearning, setRange } from '../stores/learning.js';
import { Modal } from '../components/ui.js';
import { CourseStats } from '../components/home.js';
import { PageHeader } from '../components/page.js';
import { Icon, Logo } from '../components/icon.js';
import { game } from '../skin.js';

const LOW = 36; // C2
const HIGH = 84; // C6
const BLACK = new Set([1, 3, 6, 8, 10]);
const SUITS = { soprano: tk('voice.suits.soprano'), alto: tk('voice.suits.alto'), tenor: tk('voice.suits.tenor'), bass: tk('voice.suits.bass') };
const FIND = { soprano: tk('voice.findSongs.soprano'), alto: tk('voice.findSongs.alto'), tenor: tk('voice.findSongs.tenor'), bass: tk('voice.findSongs.bass') };
const PARTS = ['soprano', 'alto', 'tenor', 'bass'];
const PART_WORDS = { soprano: tk('parts.soprano'), alto: tk('parts.alto'), tenor: tk('parts.tenor'), bass: tk('parts.bass') };
const GROUP_COLORS = { firsts: 'var(--color-part-2)', range: 'var(--color-part-1)', skill: 'var(--color-part-3)', habit: 'var(--color-part-4)', community: 'var(--color-part-6)' };
const GROUP_WORDS = { firsts: tk('badges.group.firsts'), range: tk('badges.group.range'), skill: tk('badges.group.skill'), habit: tk('badges.group.habit'), community: tk('badges.group.community') };
// What the history looks like, for someone without Plus: plainly marked as a sample.
const SAMPLE = [52, 58, 55, 63, 61, 67, 70, 66, 72, 75, 74, 79, 81, 78, 84].map((pitchPercent, i) => ({ id: `sample-${i}`, recordedAt: null, summary: { pitchPercent, tendency: -9 + i * 0.4 } }));

/**
 * Your range on a keyboard: the keys you sing lit, your comfortable note marked. A keyboard looks
 * like one in every theme (ivory and near-black keys, as the voices keep their colours); on a
 * phone it scrolls sideways rather than squeeze four octaves into the width.
 */
const IVORY = '#f4efe6';
const EBONY = '#1a1614';
/**
 * Your voice's Change it (Hillel, 2026-10-28): a voice type, or your lowest and highest notes set a
 * semitone at a time (with Hear it). Saved as your range, "chosen by you", used everywhere from then on.
 */
const ChangeRange = {
    components: { Modal, Icon },
    props: { range: { type: Object, default: null } },
    emits: ['close', 'save'],
    setup: () => ({ PARTS, PART_WORDS, VOICE_TYPES, name: midiName }),
    data() {
        const r = this.range ?? VOICE_TYPES.alto;
        return { low: r.low, high: r.high };
    },
    template: `
        <Modal :title="t('voice.change.title')" :subtitle="t('voice.change.hint')" wide @close="$emit('close')">
            <div class="space-y-5" data-change-range>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4" role="radiogroup" :aria-label="t('voice.change.types')">
                    <button v-for="part in PARTS" :key="part" type="button" role="radio" :aria-checked="low === VOICE_TYPES[part].low && high === VOICE_TYPES[part].high ? 'true' : 'false'"
                        class="radio-card flex-col gap-0 p-3" @click="pick(part)">
                        <span class="block font-semibold text-cream">{{ t(PART_WORDS[part]) }}</span>
                        <span class="block text-xs tabular-nums text-muted" translate="no">{{ t('voice.rangeBig', { low: name(VOICE_TYPES[part].low), high: name(VOICE_TYPES[part].high) }) }}</span>
                    </button>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div v-for="end in ['low', 'high']" :key="end" class="card flex items-center justify-between gap-2 p-3">
                        <span class="text-sm text-muted">{{ end === 'low' ? t('voice.lowest') : t('voice.highest') }}</span>
                        <span class="flex items-center gap-1">
                            <button type="button" class="btn-ghost btn-sm" :aria-label="t('voice.change.lower', { end: end === 'low' ? t('voice.lowest') : t('voice.highest') })" :disabled="!can(end, -1)" @click="step(end, -1)"><Icon name="minus" :size="16" /></button>
                            <span class="w-12 text-center text-xl font-extrabold tabular-nums text-cream" translate="no" :data-range-end="end">{{ name(end === 'low' ? low : high) }}</span>
                            <button type="button" class="btn-ghost btn-sm" :aria-label="t('voice.change.higher', { end: end === 'low' ? t('voice.lowest') : t('voice.highest') })" :disabled="!can(end, 1)" @click="step(end, 1)"><Icon name="plus" :size="16" /></button>
                        </span>
                    </div>
                </div>
                <div class="flex flex-wrap justify-between gap-2">
                    <button type="button" class="btn-secondary" @click="hearIt"><Icon name="play" :size="16" /> {{ t('voice.hearIt') }}</button>
                    <button type="button" class="btn-primary" @click="$emit('save', { low, high })">{{ t('voice.change.save') }}</button>
                </div>
            </div>
        </Modal>`,
    methods: {
        pick(part) {
            this.low = VOICE_TYPES[part].low;
            this.high = VOICE_TYPES[part].high;
        },
        /** A range stays a fifth or more wide (else it's not enough to fit parts to), within C2 to C6. */
        can(end, by) {
            const low = end === 'low' ? this.low + by : this.low;
            const high = end === 'high' ? this.high + by : this.high;
            return low >= 36 && high <= 84 && high - low >= 7;
        },
        step(end, by) {
            if (!this.can(end, by)) return;
            this[end] += by;
            hearNotes([{ beat: 0, length: 0.9, pitch: this[end] }], { tempo: 90 });
        },
        hearIt() {
            hearNotes([{ beat: 0, length: 0.9, pitch: this.low }, { beat: 1, length: 0.9, pitch: this.high }], { tempo: 80 });
        },
    },
};

const RangeKeyboard = {
    setup: () => ({ IVORY, EBONY, PARTS, PART_WORDS, VOICE_TYPES }),
    props: { range: { type: Object, default: null }, home: { type: Number, default: null }, type: { type: String, default: null } },
    emits: ['key'],
    template: `
        <div ref="scroller" class="overflow-x-auto [scrollbar-width:thin]">
            <div class="min-w-[38rem] space-y-3">
                <div class="grid grid-cols-[4.5rem_1fr_2.75rem] gap-3">
                <span class="sticky start-0 z-10 bg-panel" aria-hidden="true"></span>
                <svg :viewBox="'0 0 ' + width + ' 64'" class="block w-full" role="img" :aria-label="label">
                    <rect v-for="key in whites" :key="'w' + key.midi" :x="key.x" y="0" width="13" height="62" rx="2" class="cursor-pointer" :style="{ fill: lit(key.midi) ? 'var(--color-accent)' : IVORY }" @click="$emit('key', key.midi)" />
                    <rect v-for="key in blacks" :key="'b' + key.midi" :x="key.x" y="0" width="9" height="38" rx="2" class="cursor-pointer" :style="{ fill: lit(key.midi) ? 'color-mix(in oklab, var(--color-accent) 70%, black)' : EBONY }" @click="$emit('key', key.midi)" />
                    <circle v-if="homeX !== null" :cx="homeX" cy="54" r="3.5" :fill="EBONY" />
                    <text v-for="key in whites.filter((k) => k.midi % 12 === 0)" :key="'c' + key.midi" :x="key.x + 2" y="60" :fill="EBONY" fill-opacity="0.6" class="pointer-events-none text-[7px]">{{ name(key.midi) }}</text>
                </svg>
                <span aria-hidden="true"></span>
                </div>
                <!-- Each part's usual range (outlined) and the part of it you can sing (filled), on the keyboard's scale. -->
                <ul v-if="range" class="space-y-2">
                    <li v-for="(part, i) in PARTS" :key="part" class="grid grid-cols-[4.5rem_1fr_2.75rem] items-center gap-3 text-xs">
                        <span class="sticky start-0 z-10 bg-panel py-0.5" :class="part === type ? 'font-bold text-cream' : 'text-muted'">{{ t(PART_WORDS[part]) }}</span>
                        <span class="relative block h-2.5 rounded-full bg-raised">
                            <span class="absolute inset-y-0 rounded-full border-2" :style="{ insetInlineStart: x(VOICE_TYPES[part].low) + '%', width: (x(VOICE_TYPES[part].high) - x(VOICE_TYPES[part].low)) + '%', borderColor: 'var(--color-part-' + (i + 1) + ')' }"></span>
                            <span v-if="fit(part).have" class="absolute inset-y-0 rounded-full" :style="{ insetInlineStart: x(fit(part).low) + '%', width: (x(fit(part).high) - x(fit(part).low)) + '%', background: 'var(--color-part-' + (i + 1) + ')' }"></span>
                        </span>
                        <span class="text-end tabular-nums" :class="part === type ? 'font-bold text-cream' : 'text-muted'">{{ fit(part).percent }}%</span>
                    </li>
                </ul>
            </div>
        </div>`,
    computed: {
        whites() {
            const keys = [];
            let x = 0;
            for (let midi = LOW; midi <= HIGH; midi++) {
                if (BLACK.has(midi % 12)) continue;
                keys.push({ midi, x });
                x += 14;
            }
            return keys;
        },
        blacks() {
            return this.whites.filter((k) => BLACK.has((k.midi + 1) % 12) && k.midi + 1 <= HIGH).map((k) => ({ midi: k.midi + 1, x: k.x + 9.5 }));
        },
        width() {
            return this.whites.length * 14;
        },
        homeX() {
            if (this.home === null) return null;
            const key = this.whites.find((k) => k.midi === this.home) ?? this.blacks.find((k) => k.midi === this.home);
            return key ? key.x + (BLACK.has(this.home % 12) ? 4.5 : 6.5) : null;
        },
        label() {
            return this.range ? t('voice.rangeLabel', { low: midiName(this.range.low), high: midiName(this.range.high) }) : t('voice.noRange');
        },
    },
    watch: {
        range() {
            this.$nextTick(() => this.toRange());
        },
    },
    mounted() {
        this.toRange();
    },
    methods: {
        lit(midi) {
            return !!this.range && midi >= this.range.low && midi <= this.range.high;
        },
        name: (midi) => midiName(midi),
        /** A note's place on the keyboard, as a percent of its width (a key's middle). */
        x(midi) {
            const m = Math.max(LOW, Math.min(HIGH, midi));
            const white = this.whites.find((k) => k.midi === m);
            const left = white ? white.x + 6.5 : (this.whites.find((k) => k.midi === m - 1)?.x ?? 0) + 14;
            return (left / this.width) * 100;
        },
        /** How much of a part's usual range is inside yours. */
        fit(part) {
            const r = VOICE_TYPES[part];
            const low = Math.max(r.low, this.range.low);
            const high = Math.min(r.high, this.range.high);
            const have = high > low;
            return { have, low, high, percent: have ? Math.round(((high - low) / (r.high - r.low)) * 100) : 0 };
        },
        /** On a phone the keyboard scrolls: it opens on your range. */
        toRange() {
            const box = this.$refs.scroller;
            if (!box || !this.range || box.scrollWidth <= box.clientWidth) return;
            const middle = ((this.x(this.range.low) + this.x(this.range.high)) / 2 / 100) * box.scrollWidth;
            box.scrollLeft = Math.max(0, middle - box.clientWidth / 2);
        },
    },
};

/** On pitch over time: a line of the percents, oldest to newest. */
const HistoryChart = {
    props: { scores: { type: Array, required: true }, sample: Boolean },
    template: `
        <svg viewBox="0 0 300 100" class="block w-full" :class="sample ? 'opacity-60' : ''" role="img" :aria-label="label">
            <line v-for="y in [25, 50, 75]" :key="y" x1="0" x2="300" :y1="100 - y" :y2="100 - y" class="stroke-line" stroke-width="0.5" />
            <polyline :points="points" fill="none" class="stroke-part-4" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" />
        </svg>`,
    computed: {
        ordered() {
            return [...this.scores].reverse().filter((s) => s.summary?.pitchPercent !== null && s.summary?.pitchPercent !== undefined);
        },
        points() {
            const n = Math.max(1, this.ordered.length - 1);
            return this.ordered.map((s, i) => `${((i / n) * 296 + 2).toFixed(1)},${(100 - s.summary.pitchPercent).toFixed(1)}`).join(' ');
        },
        label() {
            const list = this.ordered;
            if (!list.length) return t('voice.history.none');
            return t('voice.history.label', { first: list[0].summary.pitchPercent, last: list.at(-1).summary.pitchPercent, count: list.length });
        },
    },
};

export const VoiceView = {
    components: { PageHeader, CourseStats, ChangeRange, Icon, Logo, RangeDialog, RangeKeyboard, HistoryChart },
    data: () => ({ history: null, failed: false, titles: {}, group: 'all', measuring: false, changing: false }),
    setup: () => ({ learning, account, navigate, goBack, BADGES, GROUPS, GROUP_WORDS, GROUP_COLORS, SUITS, FIND, SAMPLE, formatDate, formatNumber, game }),
    template: `
        <div class="min-h-dvh bg-ink px-4 pb-16 pt-[max(0.5rem,env(safe-area-inset-top))] md:px-8">
            <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 md:gap-8">
                <PageHeader :title="t('voice.title')" back="/learn">
                    <template v-if="game()" #aside><span class="tabular-nums text-sm text-muted">{{ t('voice.levelXp', { level: level.level, xp: formatNumber(xp) }) }}</span></template>
                </PageHeader>

                <section class="space-y-4" aria-labelledby="range-heading">
                    <div class="flex flex-wrap items-end justify-between gap-4">
                        <div class="min-w-0 max-w-xl">
                            <p id="range-heading" class="text-xs font-bold tracking-widest text-accent uppercase">{{ t('voice.range') }}</p>
                            <p v-if="range" class="mt-1 text-5xl font-extrabold tracking-tight text-cream">{{ t('voice.rangeBig', { low: name(range.low), high: name(range.high) }) }}</p>
                            <p v-if="range && rangeFrom" class="mt-1 text-sm text-muted" data-range-source>{{ rangeFrom }}</p>
                            <p v-if="type" class="mt-2 text-muted">{{ t(SUITS[type]) }} {{ t('voice.suitsNote') }}</p>
                            <p v-else-if="!range" class="mt-2 text-muted">{{ t('voice.noRangeHint') }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button v-if="range" type="button" class="btn-secondary" @click="hearRange"><Icon name="play" :size="16" /> {{ t('voice.hearIt') }}</button>
                            <button type="button" class="btn-secondary" @click="measuring = true">{{ range ? t('voice.measureAgain') : t('voice.measure') }}</button>
                            <button type="button" class="btn-secondary" @click="changing = true">{{ range ? t('voice.change.open') : t('voice.change.choose') }}</button>
                        </div>
                    </div>
                    <div class="card space-y-3 p-4 md:p-5">
                        <RangeKeyboard :range="range" :home="learning.progress.home" :type="type" @key="hear" />
                        <p class="text-xs text-muted">{{ range ? t('voice.keyboardNote') : t('voice.keyboardTap') }}</p>
                    </div>
                    <dl v-if="range" class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <div v-for="tile in tiles" :key="tile.label" class="card p-4">
                            <dt class="text-xs text-muted">{{ tile.label }}</dt>
                            <dd class="mt-1 text-xl font-extrabold text-cream">{{ tile.value }}</dd>
                        </div>
                    </dl>
                    <button v-if="type" type="button" class="btn-primary self-start" @click="navigate('/')">{{ t(FIND[type]) }} <Icon name="arrow" :size="16" /></button>
                </section>

                <!-- Badges are game mode's: still earned, shown only there (docs/skins-plan.md row 6). -->
                <section v-if="game()" class="space-y-4" aria-labelledby="badges-heading">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 id="badges-heading" class="text-2xl font-extrabold tracking-tight text-cream">{{ t('voice.badges') }} <span class="ms-1 tabular-nums text-sm font-normal text-muted">{{ t('voice.badgeCount', { earned: earnedCount, total: BADGES.length }) }}</span></h2>
                        <div class="seg flex max-w-full overflow-x-auto" role="radiogroup" :aria-label="t('voice.badgeFilter')">
                            <button v-for="g in ['all', ...GROUPS]" :key="g" type="button" role="radio" :aria-checked="group === g ? 'true' : 'false'" class="seg-option shrink-0" @click="group = g">{{ g === 'all' ? t('voice.allBadges') : t(GROUP_WORDS[g]) }}</button>
                        </div>
                    </div>
                    <ul v-if="earnedShown.length" class="grid gap-3 sm:grid-cols-2 md:grid-cols-3">
                        <li v-for="badge in earnedShown" :key="badge.id" class="card flex items-start gap-3 p-4">
                            <span class="gem grid size-10 shrink-0 place-items-center rounded-xl" :style="{ '--gem': GROUP_COLORS[badge.group], '--gem-ink': 'var(--color-ink)' }" aria-hidden="true"><Icon :name="badge.icon" :size="18" /></span>
                            <span class="min-w-0">
                                <span class="block font-bold text-cream">{{ t(badge.name) }}</span>
                                <span class="block text-sm text-muted">{{ t(badge.line) }}</span>
                                <span class="mt-1 block tabular-nums text-xs text-muted">{{ t('voice.earnedOn', { date: formatDate(earned(badge)) }) }}</span>
                            </span>
                        </li>
                    </ul>
                    <template v-if="toEarn.length">
                        <h3 class="text-sm font-semibold text-muted">{{ t('voice.stillToEarn') }}</h3>
                        <ul class="card divide-y divide-line">
                            <li v-for="badge in toEarn" :key="badge.id" class="flex items-center gap-3 px-4 py-3">
                                <span class="grid size-9 shrink-0 place-items-center rounded-xl border border-dashed border-line text-muted" aria-hidden="true"><Icon :name="badge.icon" :size="16" /></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block font-semibold text-cream">{{ t(badge.name) }}</span>
                                    <span class="block text-sm text-muted">{{ t(badge.line) }}</span>
                                </span>
                                <span v-if="badge.goal" class="w-24 shrink-0 text-end">
                                    <span class="block tabular-nums text-xs text-muted">{{ t('voice.goal', { have: goalOf(badge)[0], need: goalOf(badge)[1] }) }}</span>
                                    <span class="mt-1 block h-1.5 overflow-hidden rounded-full bg-raised" role="progressbar" :aria-valuenow="goalOf(badge)[0]" aria-valuemin="0" :aria-valuemax="goalOf(badge)[1]" :aria-label="t(badge.name)"><span class="block h-full rounded-full bg-accent" :style="{ width: Math.min(100, (goalOf(badge)[0] / goalOf(badge)[1]) * 100) + '%' }"></span></span>
                                </span>
                            </li>
                        </ul>
                    </template>
                </section>

                <section class="card space-y-4 p-6" aria-labelledby="history-heading">
                    <h2 id="history-heading" class="text-lg font-bold text-cream">{{ t('voice.history.title') }}</h2>
                    <template v-if="plus && history">
                        <p v-if="!history.length" class="text-sm text-muted">{{ t('voice.history.empty') }}</p>
                        <template v-else>
                            <HistoryChart :scores="history" />
                            <p v-if="lean" class="text-sm text-cream">{{ lean }}</p>
                            <h3 class="text-sm font-semibold text-muted">{{ t('voice.history.highlights') }}</h3>
                            <ul class="space-y-1 text-sm text-cream">
                                <li v-for="s in highlights" :key="s.id">{{ t('voice.history.highlight', { percent: s.summary.pitchPercent, what: what(s), date: formatDate(s.recordedAt) }) }}</li>
                            </ul>
                        </template>
                    </template>
                    <p v-else-if="plus && failed" class="text-sm text-muted">{{ failed === 'offline' ? t('common.offline') : t('voice.history.notLoaded') }}</p>
                    <p v-else-if="plus" class="text-sm text-muted" role="status">{{ t('settings.loading') }}</p>
                    <template v-else>
                        <p class="text-sm text-muted">{{ t('voice.history.sample') }}</p>
                        <HistoryChart :scores="SAMPLE" sample />
                        <p class="text-sm text-cream">{{ t('voice.history.withPlus') }} <a href="/plus?from=history" class="link" @click.prevent="navigate('/plus?from=history')">{{ t('export.seePlus') }}</a></p>
                    </template>
                </section>

                <section class="card p-6" aria-labelledby="progress-heading">
                    <h2 id="progress-heading" class="mb-4 text-lg font-bold text-cream">{{ t('voice.progress') }}</h2>
                    <CourseStats editable />
                </section>
            </div>
            <RangeDialog v-if="measuring" :template="{ key: 'C', parts: [] }" :parts="[]" @close="measuring = false" />
            <ChangeRange v-if="changing" :range="range" @close="changing = false" @save="changeRange" />
        </div>`,
    computed: {
        range: () => learning.progress.range,
        /** Where the range came from: "Measured on 28 Oct" (sung), or "Chosen by you" (set here, or a part picked). */
        rangeFrom() {
            const { rangeSource: source, rangeAt: at } = learning.progress;
            if (source === 'chosen' || source === 'picked') return t('voice.chosenByYou');
            return at ? t('voice.measuredOn', { date: formatDate(at) }) : '';
        },
        type: () => voiceType(learning.progress.range),
        xp: () => learning.progress.xp?.total ?? 0,
        level() {
            return levelFor(this.xp);
        },
        tiles() {
            const r = this.range;
            return [
                { label: t('voice.lowest'), value: midiName(r.low) },
                { label: t('voice.highest'), value: midiName(r.high) },
                { label: t('voice.comfortable'), value: learning.progress.home !== null && learning.progress.home !== undefined ? midiName(learning.progress.home) : t('voice.notYetShort') },
                { label: t('voice.span'), value: t('voice.semitones', { count: r.high - r.low }) },
            ];
        },
        shownBadges() {
            return this.group === 'all' ? BADGES : BADGES.filter((b) => b.group === this.group);
        },
        earnedShown() {
            return this.shownBadges.filter((b) => this.earned(b));
        },
        /** Still to earn: the nearest first (most of the way there). */
        toEarn() {
            const left = this.shownBadges.filter((b) => !this.earned(b));
            const share = (b) => (b.goal ? this.goalOf(b)[0] / this.goalOf(b)[1] : 0);
            return left.sort((a, b) => share(b) - share(a));
        },
        plus: () => account.me?.plan === 'plus',
        earnedCount: () => BADGES.filter((b) => learning.progress.badges?.[b.id]).length,
        byGroup: () => Object.fromEntries(GROUPS.map((group) => [group, BADGES.filter((b) => b.group === group)])),
        /** The way you lean, from the recent takes: said only past 5 cents. */
        lean() {
            const values = (this.history ?? []).map((s) => s.summary?.tendency).filter((v) => typeof v === 'number');
            if (values.length < 3) return '';
            const mean = Math.round(values.reduce((sum, v) => sum + v, 0) / values.length);
            return mean <= -5 ? t('voice.history.flat', { cents: -mean }) : mean >= 5 ? t('voice.history.sharp', { cents: mean }) : t('voice.history.centred');
        },
        highlights() {
            return [...(this.history ?? [])].filter((s) => s.summary?.pitchPercent !== null && s.summary?.pitchPercent !== undefined).sort((a, b) => b.summary.pitchPercent - a.summary.pitchPercent).slice(0, 3);
        },
    },
    watch: {
        // Opened straight from a link, the account arrives after the page.
        plus: {
            handler(plus) {
                if (plus && !this.history) this.loadHistory();
            },
            immediate: true,
        },
    },
    created() {
        loadLearning();
    },
    methods: {
        async loadHistory() {
            try {
                this.history = (await api.scores.list()).data;
                this.titles = Object.fromEntries((await listTemplates()).map((tpl) => [tpl.id, tpl.title]));
            } catch (error) {
                console.warn('History not loaded', error);
                this.failed = error?.code === 'offline' ? 'offline' : true;
            }
        },
        name: (midi) => midiName(midi),
        goalOf(badge) {
            const [have, need] = badge.goal(learning.progress);
            return [Math.min(have, need), need];
        },
        /** Low, comfortable and high, one after another on the audio clock. */
        hearRange() {
            const r = this.range;
            const home = learning.progress.home ?? Math.round((r.low + r.high) / 2);
            hearNotes([r.low, home, r.high].map((pitch, i) => ({ beat: i, length: 0.9, pitch })), { tempo: 80 });
        },
        async changeRange({ low, high }) {
            await setRange(low, high, { source: 'chosen' });
            this.changing = false;
        },
        hear(midi) {
            hearNotes([{ beat: 0, length: 1, pitch: midi }], { tempo: 90 });
        },
        earned(badge) {
            return learning.progress.badges?.[badge.id] ?? null;
        },
        what(score) {
            return score.lesson ? t('voice.history.aLesson') : this.titles[score.templateId] ?? t('voice.history.aSong');
        },
    },
};
