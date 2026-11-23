// The 2026-11-23 language in the styleguides (Hillel's six redesigns, ~/Code/design-inbox-2026-11-23 and its
// DESIGN-LANGUAGE.md): every new shared piece in every state, by the class it is styled by. Mounted in /styleguide
// and /styleguide/game alike, so the game skin's restyle of each piece is checked on the same markup.

import { formatNumber, t, tk } from '../i18n.js';
import { Segmented, Switch } from '../components/controls.js';
import { Icon } from '../components/icon.js';

const LEVELS = { soft: tk('styleguide.lang.soft'), medium: tk('styleguide.lang.medium'), loud: tk('styleguide.lang.loud') };
const VIEWS = { words: tk('styleguide.view.words'), notes: tk('styleguide.view.notes'), staff: tk('styleguide.view.staff') };
const STEPS = [tk('styleguide.lang.step1'), tk('styleguide.lang.step2'), tk('styleguide.lang.step3')];
const KEYS = [
    { key: tk('styleguide.lang.keySpace'), does: tk('styleguide.lang.keyPlay') },
    { key: 'R', does: tk('styleguide.lang.keyRecord') },
    { key: 'L', does: tk('styleguide.lang.keyLoop') },
    { key: 'N', does: tk('styleguide.lang.keyNext') },
];
// Each lane state: its dot (filled or hollow), its words and their colour class.
const STATES = [
    { name: tk('styleguide.lang.laneYou'), word: tk('styleguide.lang.stateYou'), on: true, tone: 'text-part-ink-1' },
    { name: tk('parts.soprano'), word: tk('styleguide.lang.stateReady'), on: true, tone: 'text-cream' },
    { name: tk('parts.alto'), word: tk('styleguide.lang.stateMaking'), on: false, tone: 'text-part-ink-1' },
    { name: tk('parts.tenor'), word: tk('styleguide.lang.stateWaiting'), on: false, tone: 'text-subtle' },
];

export const LanguageSection = {
    components: { Icon, Segmented, Switch },
    data: () => ({ guide: true, level: 'medium', view: 'notes', speed: 100, loop: true, mic: false }),
    setup: () => ({ KEYS, STATES, STEPS, formatNumber }),
    template: `
        <section aria-labelledby="sg-language" data-sg-language>
            <h2 id="sg-language" class="text-2xl font-bold text-cream">{{ t('styleguide.lang.title') }}</h2>
            <p class="mt-2 max-w-prose text-sm text-muted">{{ t('styleguide.lang.line') }}</p>

            <div class="mt-6 grid gap-6 lg:grid-cols-2">
                <div class="card space-y-3 p-6" data-sg-lang-type>
                    <p class="eyebrow-step">{{ t('styleguide.lang.eyebrow') }}</p>
                    <p class="t-title-lg text-cream">{{ t('styleguide.lang.titleLg') }}</p>
                    <p class="t-title text-cream">{{ t('styleguide.lang.titleSm') }}</p>
                    <p class="t-hero text-cream" translate="no">F♯2</p>
                    <p class="t-lead">{{ t('styleguide.lang.lead') }}</p>
                    <p class="t-section">{{ t('styleguide.lang.section') }}</p>
                    <p class="t-counter">{{ t('styleguide.lang.counter') }}</p>
                    <p class="t-hint">{{ t('styleguide.lang.hint') }}</p>
                    <p class="flex items-baseline gap-1.5 text-cream"><span class="t-clock">{{ t('styleguide.lang.clockNow') }}</span><span class="t-clock-of">{{ t('styleguide.lang.clockOf') }}</span></p>
                </div>

                <div class="card space-y-6 p-6" data-sg-lang-controls>
                    <div class="flex flex-wrap items-center gap-3">
                        <button type="button" class="icon-btn" :aria-label="t('common.back')"><Icon name="back" :size="20" /></button>
                        <button type="button" class="icon-btn-quiet" :aria-label="t('common.back')"><Icon name="back" :size="20" /></button>
                        <button type="button" class="icon-btn" :aria-label="t('common.close')"><Icon name="close" :size="20" /></button>
                        <Segmented v-model="view" kind="header" :options="views" :label="t('styleguide.lang.viewLabel')" />
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <button type="button" class="btn-pill" :aria-pressed="loop ? 'true' : 'false'" @click="loop = !loop"><Icon name="loop" :size="16" />{{ t('styleguide.lang.loop') }}</button>
                        <button type="button" class="btn-pill" style="--pill-c: var(--color-record)" :aria-pressed="mic ? 'true' : 'false'" @click="mic = !mic"><span class="size-2.5 rounded-full bg-record" aria-hidden="true"></span>{{ mic ? t('styleguide.lang.micOn') : t('styleguide.lang.record') }}</button>
                    </div>
                    <div class="space-y-3">
                        <button type="button" class="btn-primary btn-xl w-full">{{ t('styleguide.lang.primaryXl') }}</button>
                        <button type="button" class="btn-secondary w-full">{{ t('styleguide.lang.secondary') }}</button>
                    </div>
                    <ol class="step-progress" :aria-label="t('styleguide.lang.stepsLabel')">
                        <li v-for="(step, i) in STEPS" :key="i" :aria-current="i === 1 ? 'step' : null" :data-done="i < 1 ? '' : null">{{ t(step) }}</li>
                    </ol>
                </div>

                <div class="card overflow-hidden lg:col-span-2" data-sg-lang-settings>
                    <div class="hairline-grid sm:grid-cols-2">
                        <div class="space-y-[18px] px-6 py-[22px]">
                            <h3 class="t-section">{{ t('styleguide.lang.section') }}</h3>
                            <div class="set-row">
                                <div class="min-w-0 flex-1"><p class="set-row-title">{{ t('styleguide.lang.guide') }}</p><p class="t-hint mt-[3px]">{{ t('styleguide.lang.guideHint') }}</p></div>
                                <Switch :on="guide" @change="guide = $event" />
                            </div>
                            <div class="set-row items-center" :data-off="guide ? null : ''">
                                <p id="sg-lang-level" class="set-row-title flex-1">{{ t('styleguide.lang.level') }}</p>
                                <Segmented v-model="level" kind="inset" :options="levels" labelledby="sg-lang-level" />
                            </div>
                            <p class="alert-inline"><span><b>{{ t('styleguide.lang.alertLead') }}</b> {{ t('styleguide.lang.alertRest') }}</span></p>
                        </div>
                        <div class="space-y-[18px] px-6 py-[22px]">
                            <h3 class="t-section">{{ t('styleguide.lang.sectionTwo') }}</h3>
                            <div class="space-y-2">
                                <p class="flex items-baseline gap-3"><span id="sg-lang-speed" class="set-row-title flex-1">{{ t('styleguide.lang.speed') }}</span><span class="text-xs text-subtle tabular-nums">{{ t('styleguide.lang.speedNow', { n: speed }) }}</span></p>
                                <Segmented v-model="speed" kind="inset" full :options="speeds" labelledby="sg-lang-speed" />
                            </div>
                            <p class="flex flex-wrap items-center gap-2"><span class="set-row-title">{{ t('styleguide.lang.tuning') }}</span><span class="chip-tag">{{ t('styleguide.lang.tag') }}</span></p>
                            <ul class="grid grid-cols-2 gap-x-4 gap-y-2.5 text-[0.8125rem] text-cream">
                                <li v-for="k in KEYS" :key="k.key" class="flex items-center gap-2.5"><kbd class="kbd-key" translate="no">{{ k.key.length > 1 ? t(k.key) : k.key }}</kbd><span>{{ t(k.does) }}</span></li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="card overflow-hidden lg:col-span-2" data-sg-lang-lanes>
                    <ul class="px-6">
                        <li v-for="(s, i) in STATES" :key="i" class="flex min-h-14 items-center gap-4 border-t border-edge first:border-t-0">
                            <span class="w-24 text-base font-semibold text-cream">{{ t(s.name) }}</span>
                            <span class="flex-1 border-t-2 border-dashed border-faint" aria-hidden="true"></span>
                            <span class="flex items-center gap-2 text-[0.8125rem]" :class="s.tone"><span :class="s.on ? 'status-dot-on' : 'status-dot'" aria-hidden="true"></span>{{ t(s.word) }}</span>
                        </li>
                    </ul>
                    <div class="footer-bar" style="position: relative">
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-3 px-6 py-3.5">
                            <p class="min-w-56 flex-1 text-sm text-subtle">{{ t('styleguide.lang.footerNote') }}</p>
                            <div class="flex w-full gap-2.5 sm:w-auto"><button type="button" class="btn-secondary flex-1 sm:flex-none">{{ t('styleguide.lang.again') }}</button><button type="button" class="btn-primary flex-1 sm:flex-none">{{ t('styleguide.lang.make') }}</button></div>
                        </div>
                    </div>
                </div>
            </div>
        </section>`,
    computed: {
        views: () => Object.entries(VIEWS).map(([value, word]) => ({ value, label: t(word) })),
        levels: () => Object.entries(LEVELS).map(([value, word]) => ({ value, label: t(word) })),
        speeds: () => [100, 85, 70].map((value) => ({ value, label: t('styleguide.lang.percent', { n: formatNumber(value) }) })),
    },
};
