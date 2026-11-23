// The style guides' Patterns (the coordinator for Hillel, 2026-11-22: "a page of our design language"): the composed
// pieces the app reuses, drawn with the app's own classes so game mode reskins them as it does the screens. Shown by
// both /styleguide and /styleguide/game: the Reference layout (docs/design-rules.md), the stepper, the amber alert that
// says how to fix it, the AI chips, a choir's part rows, the count-in's big number, the guide chips, and a segmented
// row with its hint (Choir settings).

import { formatNumber, t, tk } from '../i18n.js';
import { AiVoiceLabel } from '../ai-voices.js';
import { Segmented } from '../components/controls.js';
import { Icon } from '../components/icon.js';
import { game as gameSkin } from '../stores/appearance.js';

const PARTS = ['soprano', 'alto', 'tenor', 'bass'];
const PART_WORDS = { soprano: tk('parts.soprano'), alto: tk('parts.alto'), tenor: tk('parts.tenor'), bass: tk('parts.bass') };
const STATUS = { ready: tk('ownChoir.wait.part.ready'), making: tk('ownChoir.wait.part.making'), waiting: tk('ownChoir.wait.part.waiting') };
const PROGRESS = [
    { part: 'soprano', status: 'ready' },
    { part: 'alto', status: 'making' },
    { part: 'tenor', status: 'waiting' },
    { part: 'bass', status: 'waiting' },
];
const GUIDES = [
    { part: 'soprano', kind: 'real', word: tk('styleguide.patterns.guideReal') },
    { part: 'alto', kind: 'ai', word: tk('styleguide.patterns.guideAi') },
    { part: 'tenor', kind: 'practice', word: tk('styleguide.patterns.guidePractice') },
];
const TIMING = ['tight', 'natural', 'loose'];
const TIMING_WORDS = { tight: tk('ownChoir.settings.timingOptions.tight'), natural: tk('ownChoir.settings.timingOptions.natural'), loose: tk('ownChoir.settings.timingOptions.loose') };

export const PatternsSection = {
    components: { AiVoiceLabel, Icon, Segmented },
    data: () => ({ timing: 'natural', count: 3 }),
    setup: () => ({ PARTS, PART_WORDS, STATUS, PROGRESS, GUIDES, formatNumber }),
    computed: {
        game: () => gameSkin(),
        timingOptions: () => TIMING.map((value) => ({ value, label: t(TIMING_WORDS[value]) })),
    },
    methods: {
        dot: (status) => (status === 'ready' ? 'bg-good' : status === 'making' ? 'bg-accent' : 'bg-line'),
        guideDot: (kind) => (kind === 'ai' ? 'bg-accent' : kind === 'real' ? 'bg-good' : 'bg-muted'),
    },
    template: `
        <section aria-labelledby="sg-patterns" class="space-y-8" data-sg-patterns>
            <div class="space-y-1">
                <h2 id="sg-patterns" class="text-2xl font-bold text-cream">{{ t('styleguide.patterns.title') }}</h2>
                <p class="max-w-prose text-sm text-muted">{{ t('styleguide.patterns.intro') }}</p>
            </div>

            <!-- 1. The Reference layout: a section label, option cards, a row with one action, a quiet footer bar. -->
            <div class="space-y-3" data-sg-pattern="reference">
                <h3 class="font-bold text-cream">{{ t('styleguide.patterns.reference') }}</h3>
                <div class="card space-y-4 p-5">
                    <p class="text-xs font-bold uppercase tracking-[0.14em] text-muted">{{ t('styleguide.patterns.sectionLabel') }}</p>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <button v-for="n in 2" :key="n" type="button" class="flex min-h-16 items-center gap-3 rounded-2xl border border-line p-4 text-start hover:border-muted">
                            <span class="min-w-0 flex-1"><span class="block font-semibold text-cream">{{ t('styleguide.patterns.optionTitle', { n }) }}</span><span class="block text-sm text-muted">{{ t('styleguide.patterns.optionHint') }}</span></span>
                            <Icon name="chevron" :size="18" class="shrink-0 -rotate-90 text-muted rtl:rotate-90" />
                        </button>
                    </div>
                    <div class="flex min-h-11 items-center justify-between gap-4 border-t border-line pt-4">
                        <span class="min-w-0"><span class="block font-semibold text-cream">{{ t('ownChoir.title') }}</span><span class="block text-sm text-muted">{{ t('styleguide.patterns.rowHint') }}</span></span>
                        <button type="button" class="btn-secondary btn-sm shrink-0 rounded-full">{{ t('styleguide.patterns.rowAction') }}</button>
                    </div>
                    <div class="flex flex-wrap items-center gap-3 border-t border-line pt-4">
                        <p class="me-auto text-sm text-muted">{{ t('sing.review.saved') }}</p>
                        <button type="button" class="btn-secondary max-sm:w-full">{{ t('sing.recorded.again') }}</button>
                        <button type="button" class="btn-primary max-sm:w-full">{{ t('styleguide.patterns.keep') }}</button>
                    </div>
                </div>
            </div>

            <!-- 2. The stepper: the step you're on filled, the next ringed, a thin line between; step 1 holds the one action. -->
            <div class="space-y-3" data-sg-pattern="stepper">
                <h3 class="font-bold text-cream">{{ t('styleguide.patterns.stepper') }}</h3>
                <ol class="card p-5">
                    <li class="grid grid-cols-[32px_minmax(0,1fr)] gap-x-3.5">
                        <span class="flex flex-col items-center" aria-hidden="true">
                            <span :style="game ? { fontSize: '14px' } : null" :class="game ? 'chip-accent size-8 justify-center p-0' : 'flex size-8 items-center justify-center rounded-full bg-accent text-[14px] font-bold text-accent-ink'">{{ formatNumber(1) }}</span>
                            <span class="my-1.5 w-px flex-1 bg-line"></span>
                        </span>
                        <div class="flex min-w-0 flex-col gap-4 pb-8 pt-1">
                            <div class="space-y-1.5"><p class="text-lg font-semibold text-cream">{{ t('ownChoir.record.title') }}</p><p class="text-[15px] text-muted">{{ t('styleguide.patterns.stepBody') }}</p></div>
                            <div class="flex flex-wrap items-center gap-x-5 gap-y-2">
                                <button type="button" class="btn-primary h-[52px] gap-2.5 px-[26px] text-[17px] font-bold"><span class="size-2.5 rounded-full bg-current" aria-hidden="true"></span>{{ t('ownChoir.record.start') }}</button>
                                <button type="button" class="link inline-flex min-h-11 items-center py-3 text-[15px] font-medium">{{ t('ownChoir.voice.record') }}</button>
                            </div>
                        </div>
                    </li>
                    <li class="grid grid-cols-[32px_minmax(0,1fr)] gap-x-3.5">
                        <span class="flex flex-col items-center" aria-hidden="true"><span :style="game ? { fontSize: '14px' } : null" :class="game ? 'chip size-8 justify-center p-0' : 'flex size-8 items-center justify-center rounded-full border-[1.5px] border-line text-[14px] font-semibold text-muted'">{{ formatNumber(2) }}</span></span>
                        <div class="pt-[5px]"><p class="font-semibold text-cream/80">{{ t('styleguide.patterns.stepTwo') }}</p></div>
                    </li>
                </ol>
            </div>

            <!-- 3. The amber alert: first on the page, what went wrong and how to fix it. -->
            <div class="space-y-3" data-sg-pattern="alert">
                <h3 class="font-bold text-cream">{{ t('styleguide.patterns.alert') }}</h3>
                <!-- The language's inline alert, no box: the amber dot, the bold lead, the one fix. -->
                <div class="alert-inline text-[15px]" role="alert"><p><strong>{{ t('ownChoir.failed.lead.take') }}</strong> {{ t('ownChoir.failed.detail.quiet') }}</p></div>
            </div>

            <!-- 4. AI, always said: the tag on a card or a row, the line under a title, a song sheet's quiet row. -->
            <div class="space-y-3" data-sg-pattern="ai">
                <h3 class="font-bold text-cream">{{ t('styleguide.patterns.ai') }}</h3>
                <div class="card flex flex-col gap-4 p-5">
                    <p class="flex flex-wrap items-center gap-3"><AiVoiceLabel tag /><span class="chip-accent">{{ t('aiVoice.tag') }}</span></p>
                    <p class="text-sm"><AiVoiceLabel choir /></p>
                    <p class="text-sm text-muted"><AiVoiceLabel :parts="[t('parts.alto').toLowerCase(), t('parts.tenor').toLowerCase()]" dot /></p>
                </div>
            </div>

            <!-- 5. A choir's parts while it's made: each part's state, the count in a quiet footer (game: part chips). -->
            <div class="space-y-3" data-sg-pattern="parts">
                <h3 class="font-bold text-cream">{{ t('styleguide.patterns.parts') }}</h3>
                <div class="card space-y-2 p-5">
                    <ul :class="game ? 'flex flex-wrap gap-2' : 'divide-y divide-line'" class="pb-1">
                        <li v-for="p in PROGRESS" :key="p.part" :class="game ? 'chip gap-2' : 'flex min-h-11 items-center justify-between gap-3 py-1'">
                            <span :class="game ? '' : 'font-semibold text-cream'">{{ t(PART_WORDS[p.part]) }}</span>
                            <span class="inline-flex items-center gap-2" :class="game ? '' : 'text-sm'"><span class="size-2.5 shrink-0 rounded-full" :class="dot(p.status)" aria-hidden="true"></span><span :class="p.status === 'waiting' ? 'text-muted' : 'text-cream'">{{ t(STATUS[p.status]) }}</span></span>
                        </li>
                    </ul>
                    <p class="border-t border-line pt-3 text-sm tabular-nums text-muted">{{ t('ownChoir.wait.partsReady', { n: 1, count: 4 }) }}</p>
                </div>
            </div>

            <!-- 6. The count-in: one big number at a time over the lanes, fading as the next comes. -->
            <div class="space-y-3" data-sg-pattern="count-in">
                <h3 class="font-bold text-cream">{{ t('styleguide.patterns.countIn') }}</h3>
                <div class="card relative grid h-48 place-items-center overflow-hidden">
                    <span class="text-[min(9rem,30vh)] font-extrabold leading-none text-cream tabular-nums" aria-hidden="true">{{ formatNumber(count) }}</span>
                    <p class="absolute bottom-3 start-4 text-xs text-muted">{{ t('styleguide.patterns.countInHint') }}</p>
                </div>
            </div>

            <!-- 7. What you'll hear: a chip a part, a dot and its word (never colour alone). -->
            <div class="space-y-3" data-sg-pattern="guides">
                <h3 class="font-bold text-cream">{{ t('styleguide.patterns.guides') }}</h3>
                <ul class="card flex flex-wrap gap-2 p-5">
                    <li v-for="g in GUIDES" :key="g.part" class="chip min-h-8 px-3 text-sm"><span class="size-2 shrink-0 rounded-full" :class="guideDot(g.kind)" aria-hidden="true"></span><span class="text-cream">{{ t(PART_WORDS[g.part]) }}: {{ t(g.word) }}</span></li>
                </ul>
            </div>

            <!-- 8. A setting with its hint: the label, one muted line, a full-width segmented control (Choir settings). -->
            <div class="space-y-3" data-sg-pattern="segmented">
                <h3 class="font-bold text-cream">{{ t('styleguide.patterns.segmented') }}</h3>
                <div class="card divide-y divide-line px-5">
                    <div class="space-y-2 py-4">
                        <div><p class="font-semibold text-cream">{{ t('ownChoir.settings.timing') }}</p><p class="text-sm text-muted">{{ t('ownChoir.settings.timingHint') }}</p></div>
                        <Segmented v-model="timing" :options="timingOptions" :label="t('ownChoir.settings.timing')" full class="[&_.seg-option]:min-h-11" />
                    </div>
                </div>
            </div>
        </section>`,
};
