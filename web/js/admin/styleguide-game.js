// The game mode styleguide (/styleguide/game; Hillel, 2026-10-31: "a similar style guide for game mode we can
// reference"): the arcade screen's tokens (input.css, arcade-tokens) and its components in every state, each with
// the class or data-ui hook it is styled by, in dark and light. It renders the real components and classes, never
// copies, so it is what a new screen is checked against (docs/game-style-cleanup-plan.md, docs/conventions.md).
// Shown on this machine or to a signed-in admin, like /styleguide. While it is open the page shows the game skin
// with the arcade layer on, whatever this device's own choice; nothing is saved, and leaving puts the look back.

import { api } from '../api.js';
import { t, tk } from '../i18n.js';
import { appearance } from '../stores/appearance.js';
import { Notice, StatusChip } from '../components/form.js';
import { Checkbox, Range, Segmented, Stepper, Switch } from '../components/controls.js';
import { Icon, Logo } from '../components/icon.js';
import { Modal, toast } from '../components/ui.js';
import { arcadeOn } from '../game/arcade.js';
import { HomeBand, SongCover } from '../components/home.js';
import { sendToSignIn } from './auth.js';

const KINDS = { primary: tk('styleguide.kind.primary'), secondary: tk('styleguide.kind.secondary'), ghost: tk('styleguide.kind.ghost'), danger: tk('styleguide.kind.danger') };
// The tokens, by group (each a CSS variable from the arcade-tokens block).
const COLOURS = ['--arc-bg', '--arc-panel', '--arc-well', '--arc-line', '--arc-shadow-c', '--arc-purple', '--arc-pink', '--arc-cyan', '--arc-yellow', '--arc-red', '--arc-green'];
const FACES = [
    { token: '--arc-pixel', use: tk('styleguideGame.facePixel') },
    { token: '--arc-screen', use: tk('styleguideGame.faceScreen') },
    { token: '--arc-display', use: tk('styleguideGame.faceDisplay') },
];
const SHAPES = [
    { token: '--arc-frame', style: { border: 'var(--arc-frame) solid var(--color-accent)', boxShadow: 'var(--arc-shadow-lg)' } },
    { token: '--arc-edge-w', style: { boxShadow: 'inset 0 0 0 var(--arc-edge-w) var(--arc-purple)' } },
    { token: '--arc-shadow', style: { boxShadow: 'inset 0 0 0 var(--arc-edge-w) var(--arc-line), var(--arc-shadow)' } },
    { token: '--arc-shadow-lg', style: { boxShadow: 'inset 0 0 0 var(--arc-edge-w) var(--arc-line), var(--arc-shadow-lg)' } },
    { token: '--arc-ground', style: { backgroundImage: 'var(--arc-ground)', backgroundSize: 'var(--arc-ground-size)', backgroundColor: 'var(--arc-bg)', boxShadow: 'inset 0 0 0 var(--arc-edge-w) var(--arc-line)' } },
    { token: '--arc-ground-still', style: { backgroundImage: 'var(--arc-ground-still)', backgroundSize: 'var(--arc-ground-size)', backgroundColor: 'var(--arc-bg)', boxShadow: 'inset 0 0 0 var(--arc-edge-w) var(--arc-line)' } },
    { token: '--arc-hazard', style: { border: '6px solid transparent', borderImage: 'var(--arc-hazard) 6', background: 'var(--arc-panel)' } },
];
const CHIPS = { soprano: tk('parts.soprano'), alto: tk('parts.alto'), tenor: tk('parts.tenor') };
const RULES = [tk('styleguideGame.ruleSkins'), tk('styleguideGame.ruleTokens'), tk('styleguideGame.ruleGlass'), tk('styleguideGame.rulePeople'), tk('styleguideGame.ruleWords')];

export const StyleguideGameView = {
    components: { Checkbox, HomeBand, Icon, Logo, Modal, Notice, Range, Segmented, SongCover, StatusChip, Stepper, Switch },
    // Sample content, as people would type it (not our words). i18n-ignore
    data: () => ({ allowed: false, sheet: false, mode: 'dark', view: 'all', sounds: true, others: false, level: 60, bpm: 96, chips: ['alto'], was: null }), // i18n-ignore
    setup: () => ({ KINDS, COLOURS, FACES, SHAPES, RULES, CHIPS }),
    computed: {
        modes: () => [
            { value: 'dark', label: t('styleguideGame.dark') },
            { value: 'light', label: t('styleguideGame.light') },
        ],
        views: () => [
            { value: 'all', label: t('home.songs.filter.all') },
            { value: 'free', label: t('home.songs.filter.free') },
            { value: 'plus', label: t('home.songs.filter.plus') },
        ],
    },
    template: `
        <div data-ui="screen" class="min-h-dvh bg-ink">
            <div v-if="allowed" class="container-page space-y-14 py-10" data-sg-game>
                <header class="flex flex-wrap items-end justify-between gap-6">
                    <div>
                        <Logo />
                        <p class="eyebrow mt-8">{{ t('styleguide.eyebrow') }}</p>
                        <h1 class="mt-2 text-4xl text-cream">{{ t('styleguideGame.title') }}</h1>
                        <p class="mt-3 max-w-prose text-muted">{{ t('styleguideGame.intro') }}</p>
                    </div>
                    <div class="space-y-1.5"><p id="sg-mode" class="label">{{ t('styleguideGame.mode') }}</p><Segmented :model-value="mode" :options="modes" labelledby="sg-mode" @update:model-value="setMode" /></div>
                </header>

                <section aria-labelledby="sg-rules" class="card p-5" data-sg-rules>
                    <h2 id="sg-rules" class="text-2xl text-cream">{{ t('styleguideGame.rules') }}</h2>
                    <ul class="mt-4 list-disc space-y-2 ps-5 text-cream"><li v-for="rule in RULES" :key="rule">{{ t(rule) }}</li></ul>
                </section>

                <section aria-labelledby="sg-type">
                    <h2 id="sg-type" class="text-2xl text-cream">{{ t('styleguide.type') }}</h2>
                    <div class="card mt-6 divide-y divide-line px-5">
                        <div v-for="face in FACES" :key="face.token" class="py-4">
                            <p class="text-2xl text-cream" :style="{ fontFamily: 'var(' + face.token + ')' }">{{ t('styleguideGame.sample') }}</p>
                            <p class="mt-1 text-sm text-muted"><code translate="no">{{ face.token }}</code> · {{ t(face.use) }}</p>
                        </div>
                        <div class="py-4">
                            <h1 class="text-4xl text-cream">{{ t('styleguideGame.h1') }}</h1>
                            <h2 class="mt-3 text-2xl text-cream">{{ t('styleguideGame.h2') }}</h2>
                            <h3 class="mt-3 text-lg text-cream">{{ t('styleguideGame.h3') }}</h3>
                            <p class="mt-3 text-cream">{{ t('styleguideGame.body') }}</p>
                            <p class="mt-1 text-sm text-muted"><code translate="no">{{ 'h1, h2, h3' }}</code> · <code translate="no">{{ 'body' }}</code> · <code translate="no">{{ '--arc-title' }}</code> · <code translate="no">{{ '--arc-h2' }}</code></p>
                        </div>
                    </div>
                </section>

                <section aria-labelledby="sg-colour">
                    <h2 id="sg-colour" class="text-2xl text-cream">{{ t('styleguide.colour') }}</h2>
                    <ul class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-6">
                        <li v-for="token in COLOURS" :key="token" class="card p-2">
                            <span class="block h-14" :style="{ background: 'var(' + token + ')', boxShadow: 'inset 0 0 0 1px var(--arc-line)' }"></span>
                            <code class="mt-2 block truncate text-xs text-muted" translate="no">{{ token }}</code>
                        </li>
                    </ul>
                </section>

                <section aria-labelledby="sg-shape">
                    <h2 id="sg-shape" class="text-2xl text-cream">{{ t('styleguideGame.shapes') }}</h2>
                    <p class="mt-2 max-w-prose text-sm text-muted">{{ t('styleguideGame.shapesHelp') }}</p>
                    <ul class="mt-6 grid grid-cols-2 gap-6 sm:grid-cols-4">
                        <li v-for="shape in SHAPES" :key="shape.token"><span class="block h-20" :style="shape.style"></span><code class="mt-3 block text-xs text-muted" translate="no">{{ shape.token }}</code></li>
                    </ul>
                </section>

                <section aria-labelledby="sg-buttons">
                    <h2 id="sg-buttons" class="text-2xl text-cream">{{ t('styleguide.buttons') }}</h2>
                    <p class="mt-2 max-w-prose text-sm text-muted">{{ t('styleguideGame.buttonsHelp') }}</p>
                    <div class="card mt-6 space-y-5 p-5">
                        <div v-for="(word, kind) in KINDS" :key="kind" class="flex flex-wrap items-center gap-3">
                            <code class="w-28 text-xs text-muted" translate="no">{{ '.btn-' + kind }}</code>
                            <button type="button" :class="'btn-' + kind">{{ t('styleguide.save') }}</button>
                            <button type="button" :class="'btn-' + kind" disabled>{{ t('styleguide.disabled') }}</button>
                            <button type="button" :class="['btn-' + kind, 'btn-sm', 'tap-44']"><Icon name="plus" :size="16" /> {{ t('styleguide.small') }}</button>
                            <button type="button" :class="['btn-' + kind, 'btn-lg']">{{ t('styleguide.large') }}</button>
                        </div>
                        <div class="flex flex-wrap items-center gap-3 border-t border-line pt-5">
                            <code class="w-28 text-xs text-muted" translate="no">{{ '.btn-go' }}</code>
                            <a href="#sg-buttons" class="btn-go">{{ t('home.lead.map') }}</a>
                        </div>
                    </div>
                </section>

                <section aria-labelledby="sg-chips">
                    <h2 id="sg-chips" class="text-2xl text-cream">{{ t('styleguideGame.chips') }}</h2>
                    <div class="card mt-6 space-y-5 p-5">
                        <div class="flex flex-wrap items-center gap-3">
                            <code class="w-28 text-xs text-muted" translate="no">{{ '.filter-chip' }}</code>
                            <button v-for="(word, chip) in CHIPS" :key="chip" type="button" class="filter-chip" :aria-pressed="chips.includes(chip) ? 'true' : 'false'" @click="toggleChip(chip)">{{ t(word) }}</button>
                            <span class="chip-accent">{{ t('styleguide.accentChip') }}</span>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 border-t border-line pt-5">
                            <code class="w-28 text-xs text-muted" translate="no">{{ 'StatusChip' }}</code>
                            <StatusChip tone="good" :label="t('styleguide.statusGood')" />
                            <StatusChip tone="warn" :label="t('styleguide.statusWarn')" />
                            <StatusChip tone="bad" :label="t('styleguide.statusBad')" />
                        </div>
                        <div class="flex flex-wrap items-center gap-3 border-t border-line pt-5">
                            <code class="w-28 text-xs text-muted" translate="no">{{ '.ge-p1' }}</code>
                            <span class="ge-p1"><span class="ge-p1-tag">{{ t('game.home.p1') }}</span><span>{{ t('game.home.level', { level: 4 }) }}</span></span>
                            <code class="text-xs text-muted" translate="no">{{ '.gs-score' }}</code>
                            <p class="gs-score hidden">{{ t('game.arcade.hi', { score: '000150' }) }}</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-3 border-t border-line pt-5">
                            <code class="w-28 text-xs text-muted" translate="no">{{ '.seg' }}</code>
                            <Segmented v-model="view" :options="views" :label="t('home.songs.filter.label')" />
                        </div>
                    </div>
                </section>

                <section aria-labelledby="sg-controls">
                    <h2 id="sg-controls" class="text-2xl text-cream">{{ t('styleguide.controls') }}</h2>
                    <div class="mt-6 grid gap-4 md:grid-cols-2">
                        <div class="card divide-y divide-line px-5">
                            <Switch :on="sounds" :label="t('styleguide.switchOne')" :hint="t('styleguide.switchOneHint')" class="py-3" @change="sounds = $event" />
                            <Switch :on="false" :label="t('styleguide.switchOff')" disabled class="py-3" />
                        </div>
                        <div class="card space-y-5 p-5">
                            <Checkbox v-model="others" :label="t('styleguide.switchTwo')" />
                            <div class="space-y-1.5"><p class="label">{{ t('styleguide.stepper') }}</p><Stepper v-model="bpm" :min="40" :max="200" :label="t('styleguide.stepper')" :unit="t('styleguide.bpm')" :decrease="t('styleguide.slower')" :increase="t('styleguide.faster')" /></div>
                            <div class="space-y-1.5"><label for="sgg-range" class="label">{{ t('styleguide.slider') }}</label><Range id="sgg-range" v-model="level" /></div>
                            <div class="space-y-1.5"><label for="sgg-input" class="label">{{ t('styleguide.fieldTitle') }}</label><input id="sgg-input" class="input" type="text" :value="t('styleguideGame.sample')" /></div><code class="block text-xs text-muted" translate="no">{{ '.input · Switch · Checkbox · Stepper · Range' }}</code>
                        </div>
                    </div>
                </section>

                <section aria-labelledby="sg-cards">
                    <h2 id="sg-cards" class="text-2xl text-cream">{{ t('styleguideGame.cards') }}</h2>
                    <div class="mt-6 grid gap-6 md:grid-cols-2">
                        <div>
                            <div class="card p-5"><p class="text-cream">{{ t('styleguideGame.body') }}</p></div>
                            <code class="mt-2 block text-xs text-muted" translate="no">{{ '.card' }}</code>
                        </div>
                        <div class="md:col-span-2">
                            <div class="relative overflow-hidden rounded-stage border border-edge bg-panel p-5" data-lead>
                                <p class="gs-hi hidden"><span class="gs-hi-stage">{{ t('game.arcade.stage', { number: '01' }) }}</span><span class="gs-hi-score">{{ t('game.arcade.hi', { score: '000150' }) }}</span></p>
                                <h1 class="max-w-md text-3xl text-cream">{{ t('styleguideGame.h2') }}</h1>
                                <button type="button" class="btn-primary mt-5">{{ t('game.home.pressStart') }}</button>
                            </div>
                            <code class="mt-2 block text-xs text-muted" translate="no">{{ '[data-lead] · .gs-hi' }}</code>
                        </div>
                        <div class="md:col-span-2">
                            <div class="card flex flex-wrap items-center gap-4 p-5" data-ask>
                                <Icon name="download" :size="22" />
                                <div class="min-w-52 flex-1"><h2 class="font-bold text-cream">{{ t('install.title') }}</h2><p class="text-sm text-muted">{{ t('styleguideGame.body') }}</p></div>
                                <button type="button" class="btn-primary">{{ t('install.button') }}</button>
                            </div>
                            <code class="mt-2 block text-xs text-muted" translate="no">{{ '[data-ask]' }}</code>
                        </div>
                    </div>
                </section>

                <section aria-labelledby="sg-library">
                    <h2 id="sg-library" class="text-2xl text-cream">{{ t('styleguideGame.library') }}</h2>
                    <p class="mt-2 max-w-prose text-sm text-muted">{{ t('styleguideGame.libraryHelp') }}</p>
                    <div class="mt-6 space-y-8">
                        <HomeBand id="sgg-band" :title="t('home.songs.library')" :count="t('home.songs.count', { count: 5 })" color="var(--color-accent)" tone="yellow">
                            <ul class="grid grid-cols-2 gap-3 md:grid-cols-4">
                                <li v-for="n in 2" :key="n"><button type="button" class="flex w-full flex-col gap-2 rounded-3xl p-1 text-start" data-ui="cartridge">
                                    <span class="relative block"><SongCover :parts="4" :seed="n * 7" />
                                        <span class="absolute start-3 top-3 flex flex-wrap gap-1"><span data-ui="tag" :data-tone="n === 1 ? 'cyan' : 'green'" class="rounded-full bg-raised px-2 py-0.5 text-[10px] font-bold text-accent-strong">{{ n === 1 ? t('home.songs.realVoices') : t('home.songs.freeMonth') }}</span><span v-if="n === 2" data-ui="tag" data-tone="pink" class="rounded-full bg-raised px-2 py-0.5 text-[10px] text-muted">{{ t('projects.draft') }}</span></span></span>
                                    <span class="px-1 font-bold leading-tight text-cream" translate="no" data-ui="cartridge-title">{{ t('styleguideGame.h3') }}</span>
                                    <span class="px-1 text-xs text-muted">{{ t('styleguideGame.body') }}</span>
                                </button></li>
                            </ul>
                        </HomeBand>
                        <code class="block text-xs text-muted" translate="no">{{ 'HomeBand tone · [data-ui=cartridge] · [data-ui=tag][data-tone] · [data-cover]' }}</code>
                        <HomeBand id="sgg-rows" :title="t('groups.homeTitle')" color="var(--color-accent)" tone="green">
                            <template #aside><a href="#sg-library" class="btn-go">{{ t('home.seeAll') }}</a></template>
                            <ul class="card divide-y divide-line px-4" data-ui="rows">
                                <li v-for="n in 2" :key="n"><a href="#sg-library" class="flex items-center gap-3 py-3"><span class="gs-slot hidden" :data-slot="n" aria-hidden="true">{{ t('game.arcade.slot', { number: n }) }}</span><span class="min-w-0 flex-1 truncate font-semibold text-cream" translate="no">{{ t('styleguideGame.h3') }}</span><span class="text-sm text-muted" data-ui="rows-count">{{ t('groups.songCount', { count: n * 3 }) }}</span><Icon name="arrow" :size="18" class="text-muted" /></a></li>
                            </ul>
                        </HomeBand>
                        <code class="block text-xs text-muted" translate="no">{{ '[data-ui=rows] · .gs-slot · [data-ui=band] .btn-go' }}</code>
                    </div>
                </section>

                <section aria-labelledby="sg-gems">
                    <h2 id="sg-gems" class="text-2xl text-cream">{{ t('styleguideGame.gems') }}</h2>
                    <p class="mt-2 max-w-prose text-sm text-muted">{{ t('styleguideGame.gemsHelp') }}</p>
                    <div class="card mt-6 flex flex-wrap items-center gap-5 p-5">
                        <span v-for="n in 4" :key="n" class="gem size-10 rounded-full" :style="{ '--gem': 'var(--color-part-' + n + ')' }"></span>
                        <span class="h-2.5 w-48 overflow-hidden rounded-full bg-raised" data-level-bar><span class="block h-full w-2/3 rounded-full bg-accent"></span></span>
                        <code class="text-xs text-muted" translate="no">{{ '.gem · [data-level-bar]' }}</code>
                    </div>
                </section>

                <section aria-labelledby="sg-messages">
                    <h2 id="sg-messages" class="text-2xl text-cream">{{ t('styleguide.messages') }}</h2>
                    <div class="mt-6 grid gap-3 md:grid-cols-2">
                        <Notice tone="info">{{ t('styleguide.noticeInfo') }}</Notice>
                        <Notice tone="bad">{{ t('styleguide.noticeBad') }}</Notice>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-3">
                        <button type="button" class="btn-secondary" @click="sheet = true">{{ t('styleguide.openSheet') }}</button>
                        <button type="button" class="btn-secondary" @click="toast(t('styleguide.toast'))">{{ t('styleguide.showToast') }}</button>
                    </div>
                </section>

                <Modal v-if="sheet" :title="t('styleguide.sheetTitle')" @close="sheet = false">
                    <p class="text-cream">{{ t('styleguide.sheetBody') }}</p>
                    <div class="mt-5 flex justify-end gap-3"><button type="button" class="btn-secondary" @click="sheet = false">{{ t('common.cancel') }}</button><button type="button" class="btn-primary" @click="sheet = false">{{ t('common.done') }}</button></div>
                </Modal>
            </div>
        </div>`,
    async created() {
        const thisMachine = ['localhost', '127.0.0.1'].includes(location.hostname) || location.hostname.endsWith('.test');
        if (!thisMachine) {
            try {
                await api.admin.me();
            } catch (error) {
                if (error?.status === 401) sendToSignIn();
                return;
            }
        }
        this.allowed = true;
        this.show();
    },
    unmounted() {
        // Put this device's own look back.
        document.removeEventListener('stackapella:theme', this.again);
        if (!this.was) return;
        const root = document.documentElement;
        root.dataset.skin = this.was.skin;
        root.dataset.theme = this.was.theme;
        appearance.skin = this.was.skin === 'serious' ? 'serious' : 'game';
        if (arcadeOn()) root.dataset.gs = '';
        else delete root.dataset.gs;
    },
    methods: {
        toast,
        /** The game skin with the arcade layer on, for this page only (nothing saved). */
        show() {
            const root = document.documentElement;
            this.mode = root.dataset.theme?.endsWith('-light') ? 'light' : 'dark';
            this.was = { skin: root.dataset.skin, theme: root.dataset.theme };
            this.force();
            // The look can be applied again while the page is open (the account's choice arriving, another tab):
            // that becomes the look to put back, and the preview comes back over it.
            this.again = () => this.force();
            document.addEventListener('stackapella:theme', this.again);
        },
        force() {
            const root = document.documentElement;
            if (root.dataset.skin !== 'game' || !('gs' in root.dataset) || root.dataset.theme !== this.theme()) this.was = { skin: root.dataset.skin, theme: root.dataset.theme };
            appearance.skin = 'game';
            root.dataset.skin = 'game';
            root.dataset.gs = '';
            root.dataset.theme = this.theme();
        },
        theme() {
            return this.mode === 'light' ? 'warm-light' : 'stage-dark';
        },
        setMode(mode) {
            this.mode = mode;
            document.documentElement.dataset.theme = this.theme();
        },
        toggleChip(chip) {
            this.chips = this.chips.includes(chip) ? this.chips.filter((c) => c !== chip) : [...this.chips, chip];
        },
    },
};
