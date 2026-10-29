// The theme picker (docs/decisions.md, "themes", then "the theme picker, refined"): one panel that
// keeps it all together. Mode (Light, Dark or Auto, matching the device); Colour, the five palettes,
// each a tiny screen of that theme in the mode showing (its background, a panel with words and a
// button in the accent); and Accent, the palette's own or one of seven, fitted to every theme so it
// reads (tool/accents.py), recolouring links, highlights and focus. The chosen one is ringed and
// ticked. Choosing shows at once and never closes the panel, so themes can be compared. A colour not
// chosen is the mode's own (Stage in dark, Warm in light). At the top of the home it's one Theme
// button opening the panel (under the button; a sheet on a phone); in Settings' "How it looks" the
// same panel is inline. For everyone, signed out too.

import { t, tk } from '../i18n.js';
import { appearance, FAMILIES, game, modeChosen, setAppearance, setGameMode, setSkin, shownColour, shownMode } from '../stores/appearance.js';
import { enhance } from '../game-toggle.js';
import { ACCENT_COLOURS, ACCENT_NAMES, SURFACES } from '../accent-colours.js';
import { Icon } from './icon.js';
import { Modal } from './ui.js';

const MODES = ['light', 'dark', 'system'];
const MODE_WORDS = { light: tk('appearance.mode.light'), dark: tk('appearance.mode.dark'), system: tk('appearance.mode.auto') };
const MODE_ICONS = { light: 'sun', dark: 'moon', system: 'device' };
// Each colour's name in each mode (Hillel: "Light, Midnight" sounded wrong): the ids stay.
const FAMILY_WORDS = {
    stage: { dark: tk('appearance.colour.stage.dark'), light: tk('appearance.colour.stage.light') },
    warm: { dark: tk('appearance.colour.warm.dark'), light: tk('appearance.colour.warm.light') },
    midnight: { dark: tk('appearance.colour.midnight.dark'), light: tk('appearance.colour.midnight.light') },
    graphite: { dark: tk('appearance.colour.graphite.dark'), light: tk('appearance.colour.graphite.light') },
};
// Each theme in a word or two (73.png): what it looks like.
const FAMILY_SAYS = {
    stage: { dark: tk('appearance.says.stage.dark'), light: tk('appearance.says.stage.light') },
    warm: { dark: tk('appearance.says.warm.dark'), light: tk('appearance.says.warm.light') },
    midnight: { dark: tk('appearance.says.midnight.dark'), light: tk('appearance.says.midnight.light') },
    graphite: { dark: tk('appearance.says.graphite.dark'), light: tk('appearance.says.graphite.light') },
};
const ACCENT_WORDS = {
    own: tk('appearance.accent.own'),
    coral: tk('appearance.accent.coral'),
    amber: tk('appearance.accent.amber'),
    green: tk('appearance.accent.green'),
    teal: tk('appearance.accent.teal'),
    sky: tk('appearance.accent.sky'),
    indigo: tk('appearance.accent.indigo'),
    violet: tk('appearance.accent.violet'),
    rose: tk('appearance.accent.rose'),
};
const MODE_KEYS = { light: 'appearance.mode.light', dark: 'appearance.mode.dark', system: 'appearance.mode.auto' };

/** The look in a line, for Settings: "Dark · Stage theme · Teal accent". */
export function appearanceSummary() {
    const mode = game() ? appearance.gameMode ?? 'dark' : appearance.mode ?? 'system';
    const shown = shownMode();
    const colour = shownColour();
    const theme = t(FAMILY_WORDS[colour]?.[shown] ?? FAMILY_WORDS.stage.dark);
    const own = !appearance.accent || appearance.accent === 'own';
    if (own) return t('settings.appearanceOwn', { mode: t(MODE_WORDS[mode]), theme });
    const accent = appearance.accent === 'custom' ? t('appearance.accent.custom') : t(ACCENT_WORDS[appearance.accent] ?? ACCENT_WORDS.own);
    return t('settings.appearanceSummary', { mode: t(MODE_WORDS[mode]), theme, accent });
}

/** A theme in miniature, as wide as its card: its background, a panel with two lines of words, and a button in the accent. */
const MiniScreen = {
    props: { theme: { type: String, required: true }, accent: { type: String, default: null }, custom: { type: Object, default: null } },
    computed: {
        s: (vm) => SURFACES[vm.theme],
        button: (vm) => (vm.custom ? vm.custom.accent : ACCENT_COLOURS[vm.theme][vm.accent ?? 'own']),
    },
    template: `
        <span class="relative block h-14 w-full overflow-hidden rounded-lg" :style="{ background: s.ink }" aria-hidden="true">
            <span class="absolute inset-2 rounded-md border px-2 py-1.5" :style="{ background: s.panel, borderColor: s.line }">
                <i class="block h-[3px] w-3/5 rounded-full" :style="{ background: s.cream }"></i>
                <i class="mt-1.5 block h-[3px] w-2/5 rounded-full" :style="{ background: s.muted }"></i>
                <i class="absolute bottom-1.5 end-1.5 h-2.5 w-6 rounded-full" :style="{ background: button }"></i>
            </span>
        </span>`,
};

/** How far apart two colours look, roughly (RGB distance, weighted for the eye). */
function apart(a, b) {
    const [x, y] = [a, b].map((hex) => [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16)));
    return Math.hypot(2 * (x[0] - y[0]), 4 * (x[1] - y[1]), 3 * (x[2] - y[2]));
}

/** Where the mode stands, in words: always light, always dark, or matching the device (and what it is now). */
function modeSaid(mode, system) {
    if (mode === 'light') return tk('appearance.mode.alwaysLight');
    if (mode === 'dark') return tk('appearance.mode.alwaysDark');
    return system === 'dark' ? tk('appearance.mode.systemDark') : tk('appearance.mode.systemLight');
}

/** Arrow keys move through a radio group and choose (the usual radio group keys); Home and End too. */
function arrowKeys(event, list, current, choose) {
    const step = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 }[event.key];
    let next = null;
    if (step) next = list[(list.indexOf(current) + step + list.length) % list.length];
    if (event.key === 'Home') next = list[0];
    if (event.key === 'End') next = list.at(-1);
    if (next === null) return;
    event.preventDefault();
    choose(next);
    const group = event.currentTarget;
    group.ownerDocument.defaultView.requestAnimationFrame(() => group.querySelector(`[data-value="${next}"]`)?.focus());
}

/**
 * The Appearance panel (Hillel's 73.png): it restyles itself at once with every choice (the page's
 * own tokens), so there's no guessing from thumbnails. Mode as a quiet segmented control (the
 * choice a raised surface); the theme cards and the accents marked the same way, by an accent ring;
 * a line that says what the mode does now; each theme with a one-word description and its
 * thumbnail in your accent; the accent's name beside its heading; Reset to default and Done.
 * A theme is a light and dark pair (Stage and Spotlight, Ember and Warm...): under Auto each card
 * shows both, since one choice covers both modes.
 */
/**
 * Game mode, on or off (docs/skins-plan.md): in the theme sheet and in Settings. Off is the serious
 * skin, a plain practice tool; what's earned is kept.
 */
export const GameModeSwitch = {
    setup: () => ({ appearance, game }),
    // The design agent's toggle, its calm settings row (no breathing, Figtree): js/game-toggle.js owns
    // aria-checked and the moments; the hint below stays Vue's. Hillel: "love the game mode toggle".
    template: `
        <div data-game-mode>
            <button ref="toggle" type="button" class="gt gt--row" role="switch" data-game-toggle :aria-labelledby="'gt-' + $.uid + '-label'" :aria-describedby="'gt-' + $.uid + '-hint'"><span class="gt-track" aria-hidden="true"><span class="gt-glow"></span><span class="gt-screen"><span class="gt-picture"><span class="gt-tint"></span><span class="gt-stack"><i></i><i></i><i></i><i></i></span><span class="gt-notes"><i></i><i></i><i></i><i></i></span></span><span class="gt-flash"></span><span class="gt-beam"></span><span class="gt-dot"></span></span><span class="gt-knob"><span class="gt-halo"></span><span class="gt-body"><span class="gt-gem"></span><span class="gt-gem gt-gem--on"></span><span class="gt-glint"></span></span><span class="gt-burst"><i></i><i></i><i></i><i></i></span></span></span><span class="gt-text"><span class="gt-label" :id="'gt-' + $.uid + '-label'">{{ t('appearance.gameMode.label') }}</span><span class="gt-hint" :id="'gt-' + $.uid + '-hint'">{{ game() ? t('appearance.gameMode.onHint') : t('appearance.gameMode.offHint') }}</span></span></button>
        </div>`,
    mounted() {
        this.gt = enhance(this.$refs.toggle, { set: (on) => setSkin(on ? 'game' : 'serious'), sound: () => false });
    },
    unmounted() {
        this.gt?.destroy?.();
    },
};

export const ThemePanel = {
    components: { Icon, MiniScreen },
    emits: ['done'],
    data: () => ({ system: window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light', said: '', trying: null }),
    setup: () => ({ MODES, MODE_WORDS, MODE_ICONS, FAMILIES, FAMILY_WORDS, FAMILY_SAYS, ACCENT_NAMES, ACCENT_WORDS, ACCENT_COLOURS, SURFACES, appearance, game }),
    template: `
        <div class="space-y-5" data-theme-panel>
            <!-- Light or dark for the skin showing: game mode keeps its own (dark until chosen). -->
            <div class="space-y-2">
                <p :id="uid + '-mode'" class="label">{{ t('appearance.mode.title') }}</p>
                <div class="seg grid grid-cols-3" role="radiogroup" :aria-labelledby="uid + '-mode'" @keydown="arrowKeys($event, MODES, mode, pickMode)">
                    <button v-for="m in MODES" :key="m" type="button" role="radio" :data-value="m" :aria-checked="mode === m ? 'true' : 'false'" :tabindex="mode === m ? 0 : -1" :data-autofocus="mode === m ? '' : null"
                        class="seg-option gap-1.5 px-2" @click="pickMode(m)">
                        <Icon :name="MODE_ICONS[m]" :size="16" />{{ t(MODE_WORDS[m]) }}
                    </button>
                </div>
                <p class="text-xs text-muted" data-mode-said>{{ t(modeSaid(mode, system)) }}</p>
            </div>
            <div class="space-y-2">
                <p :id="uid + '-colour'" class="label">{{ mode === 'system' ? t('appearance.colour.titleBoth') : shown === 'light' ? t('appearance.colour.titleLight') : t('appearance.colour.titleDark') }}</p>
                <div class="grid grid-cols-2 gap-2" role="radiogroup" :aria-labelledby="uid + '-colour'" @keydown="arrowKeys($event, FAMILIES, colour, pickColour)">
                    <button v-for="f in FAMILIES" :key="f" type="button" role="radio" :data-value="f" :aria-checked="colour === f ? 'true' : 'false'" :tabindex="colour === f ? 0 : -1"
                        class="min-w-0 rounded-2xl border p-2 text-start transition" :class="colour === f ? 'border-transparent ring-2 ring-accent' : 'border-edge hover:border-line'" @click="pickColour(f)">
                        <span v-if="mode === 'system'" class="grid grid-cols-2 gap-1"><MiniScreen :theme="f + '-light'" :accent="accent" :custom="customFor(f + '-light')" /><MiniScreen :theme="f + '-dark'" :accent="accent" :custom="customFor(f + '-dark')" /></span>
                        <MiniScreen v-else :theme="f + '-' + shown" :accent="accent" :custom="customFor(f + '-' + shown)" />
                        <span class="mt-2 block truncate px-1 text-sm font-bold text-cream">{{ mode === 'system' ? t('appearance.colour.pair', { light: t(FAMILY_WORDS[f].light), dark: t(FAMILY_WORDS[f].dark) }) : t(FAMILY_WORDS[f][shown]) }}</span>
                        <span class="block truncate px-1 pb-0.5 text-xs text-muted">{{ mode === 'system' ? t('appearance.colour.pair', { light: t(FAMILY_SAYS[f].light), dark: t(FAMILY_SAYS[f].dark) }) : t(FAMILY_SAYS[f][shown]) }}</span>
                    </button>
                </div>
            </div>
            <div class="space-y-2">
                <p :id="uid + '-accent'" class="label">{{ t('appearance.accent.title') }} <span class="ms-1 normal-case tracking-normal text-accent-strong">{{ accentName }}</span></p>
                <div class="flex flex-wrap gap-1.5" role="radiogroup" :aria-labelledby="uid + '-accent'" @keydown="arrowKeys($event, accents, accentShown, pickAccent)">
                    <button v-for="a in accents" :key="a" type="button" role="radio" :data-value="a" :aria-checked="accentShown === a ? 'true' : 'false'" :tabindex="accentShown === a ? 0 : -1" :aria-label="t(ACCENT_WORDS[a])" :title="t(ACCENT_WORDS[a])"
                        class="gem tap-44 size-7 rounded-full ring-offset-2 ring-offset-panel transition" :class="accentShown === a ? 'ring-2 ring-accent' : 'hover:scale-110'" :style="{ '--gem': ACCENT_COLOURS[theme][a] }" @click="pickAccent(a)"></button>
                    <!-- Custom: a colour of your own, fitted to every theme so it stays readable. -->
                    <button type="button" role="radio" data-value="custom" :aria-checked="accentShown === 'custom' ? 'true' : 'false'" :tabindex="accentShown === 'custom' ? 0 : -1" :aria-label="t('appearance.accent.custom')" :title="t('appearance.accent.custom')"
                        class="tap-44 relative grid size-7 place-items-center rounded-full ring-offset-2 ring-offset-panel transition" :class="accentShown === 'custom' ? 'ring-2 ring-accent' : 'hover:scale-110'" :style="{ background: customShown ? customShown.accent : 'conic-gradient(#f87171, #fbbf24, #a3e635, #34d399, #38bdf8, #818cf8, #e879f9, #f87171)' }" @click="openCustom">
                        <Icon v-if="accentShown !== 'custom'" name="plus" :size="14" :style="{ color: customShown ? customShown.ink : '#111' }" />
                    </button>
                    <input ref="custom" type="color" class="sr-only" tabindex="-1" aria-hidden="true" :value="appearance.customAccent || '#e8a87c'" @input="previewCustom($event.target.value)" @change="pickCustom($event.target.value)" />
                </div>
                <p class="text-xs text-muted">{{ t('appearance.accent.hint') }}</p>
                <div v-if="accentShown === 'custom' && customColour" class="space-y-2" data-custom-preview>
                    <div class="grid grid-cols-2 gap-2">
                        <div v-for="m in ['dark', 'light']" :key="m" class="rounded-xl p-3" :style="{ background: SURFACES[colour + '-' + m].panel }">
                            <span class="inline-block rounded-full px-3 py-1 text-xs font-bold" :style="{ background: fitted(m).accent, color: fitted(m).ink }">{{ t('appearance.accent.previewButton') }}</span>
                            <span class="ms-2 text-xs font-semibold underline decoration-2 underline-offset-4" :style="{ color: SURFACES[colour + '-' + m].cream, textDecorationColor: fitted(m).accent }">{{ t('appearance.accent.previewLink') }}</span>
                        </div>
                    </div>
                    <p v-if="customNote" class="text-xs text-muted" role="status">{{ t(customNote) }}</p>
                </div>
            </div>
            <div class="flex items-center justify-between gap-3 border-t border-edge pt-4">
                <button type="button" class="btn-ghost btn-sm" :disabled="isDefault" @click="reset">{{ t('appearance.reset') }}</button>
                <button type="button" class="btn-primary" @click="$emit('done')">{{ t('appearance.done') }}</button>
            </div>
            <span class="sr-only" role="status">{{ said }}</span>
        </div>`,
    computed: {
        uid() {
            return `theme-${this.$.uid}`;
        },
        mode: () => modeChosen(),
        colour: () => (appearance.version, shownColour()),
        shown: () => (appearance.version, shownMode()),
        theme: () => (appearance.version, appearance.theme),
        accent: () => appearance.accent,
        accentShown: () => appearance.accent ?? 'own',
        customColour: (vm) => vm.trying ?? appearance.customAccent,
        /** The accent's name beside its heading. */
        accentName: (vm) => (vm.accentShown === 'custom' ? t('appearance.accent.customShort') : vm.accentShown === 'own' ? t('appearance.accent.ownShort') : t(ACCENT_WORDS[vm.accentShown])),
        /** Nothing chosen: Reset has nothing to do. */
        isDefault: () => (appearance.version, modeChosen() === 'dark' && !appearance.colour && !appearance.accent),
        /** The custom colour as it shows in this theme (fitted), or null. */
        customShown() {
            return this.customColour ? window.stackapellaTheme?.fitAccent?.(this.customColour, this.theme) ?? null : null;
        },
        /** A gentle word when the colour had to move to stay readable here. */
        customNote() {
            const shown = this.customShown;
            if (!shown?.adjusted) return null;
            const level = (hex) => [1, 3, 5].reduce((sum, i) => sum + parseInt(hex.slice(i, i + 2), 16), 0);
            return level(shown.accent) < level(this.customColour) ? tk('appearance.accent.madeDarker') : tk('appearance.accent.madeLighter');
        },
        /** The accents to offer: one that looks the same as the theme's own isn't offered twice (unless it's the one chosen). */
        accents() {
            const own = ACCENT_COLOURS[this.theme].own;
            return ACCENT_NAMES.filter((a) => a === 'own' || a === appearance.accent || apart(ACCENT_COLOURS[this.theme][a], own) > 160);
        },
    },
    mounted() {
        this.media = window.matchMedia('(prefers-color-scheme: dark)');
        this.onSystem = (event) => (this.system = event.matches ? 'dark' : 'light');
        this.media.addEventListener('change', this.onSystem);
    },
    unmounted() {
        this.media.removeEventListener('change', this.onSystem);
    },
    methods: {
        arrowKeys,
        modeSaid,
        pickMode(mode) {
            if (game()) setGameMode(mode);
            else setAppearance({ mode });
            this.said = t(modeSaid(mode, this.system));
        },
        pickColour(colour) {
            setAppearance({ colour });
            this.said = t('appearance.colour.now', { colour: t(FAMILY_WORDS[colour][shownMode()]) });
        },
        /** A thumbnail's button in your own colour, fitted to that theme (null for the named accents). */
        customFor(theme) {
            return this.accentShown === 'custom' && this.customColour ? window.stackapellaTheme?.fitAccent?.(this.customColour, theme) ?? null : null;
        },
        /** Back to how it starts: dark, the mode's own theme, its own accent. */
        reset() {
            if (game()) setGameMode('dark');
            setAppearance({ mode: 'dark', colour: null, accent: null, customAccent: null });
            this.said = t('appearance.resetDone');
        },
        fitted(mode) {
            return window.stackapellaTheme?.fitAccent?.(this.customColour, `${this.colour}-${mode}`) ?? { accent: this.customColour, ink: '#111' };
        },
        openCustom() {
            if (appearance.customAccent && appearance.accent !== 'custom') return this.pickCustom(appearance.customAccent);
            this.$refs.custom.click();
        },
        previewCustom(hex) {
            this.trying = hex;
        },
        pickCustom(hex) {
            this.trying = null;
            setAppearance({ accent: 'custom', customAccent: hex });
            this.said = t('appearance.accent.now', { accent: t('appearance.accent.custom') });
        },
        pickAccent(accent) {
            setAppearance({ accent: accent === 'own' ? null : accent });
            this.said = t('appearance.accent.now', { accent: t(ACCENT_WORDS[accent]) });
        },
    },
};

/** Settings' "How it looks": the same panel, inline. */
/**
 * At the top of the home: one Theme button (the mode's icon, and its name from a tablet up) opening the
 * panel, under the button from a tablet up, as a sheet on a phone. It closes on Done, Escape or a
 * click outside, never on a choice.
 */
export const ThemeSwitch = {
    components: { Icon, Modal, ThemePanel },
    data: () => ({ open: false, phone: window.matchMedia('(max-width: 767px)').matches }),
    setup: () => ({ MODE_ICONS, MODE_KEYS, FAMILY_WORDS }),
    template: `
        <div ref="root" class="relative">
            <button ref="button" type="button" class="flex h-11 min-w-11 items-center justify-center gap-2 rounded-full glass-bubble px-3 text-sm font-semibold text-cream transition hover:bg-raised" :aria-expanded="open ? 'true' : 'false'" aria-haspopup="dialog" :aria-label="t('appearance.button', { colour: t(FAMILY_WORDS[colour][shown]), mode: t(MODE_KEYS[mode]) })" @click="open = !open">
                <Icon :name="MODE_ICONS[mode]" :size="16" />
                <span class="hidden sm:inline">{{ t('appearance.theme') }}</span>
            </button>
            <Modal v-if="open && phone" :title="t('appearance.title')" @close="close">
                <p class="-mt-3 mb-4 text-xs text-muted">{{ t('appearance.instant') }}</p>
                <ThemePanel @done="close" />
            </Modal>
            <section v-else-if="open" ref="panel" role="dialog" :aria-labelledby="'appearance-title-' + $.uid" class="absolute end-0 top-full z-40 mt-2 w-96 max-w-[calc(100vw-2rem)] rounded-3xl border border-edge bg-panel p-5 shadow-2xl">
                <header class="mb-4 flex items-baseline justify-between gap-3">
                    <h2 :id="'appearance-title-' + $.uid" class="text-lg font-extrabold text-cream">{{ t('appearance.title') }}</h2>
                    <span class="text-xs text-muted">{{ t('appearance.instant') }}</span>
                </header>
                <ThemePanel @done="close" />
            </section>
        </div>`,
    computed: {
        mode: () => modeChosen(),
        colour: () => (appearance.version, shownColour()),
        shown: () => (appearance.version, shownMode()),
    },
    watch: {
        open(open) {
            if (!open || this.phone) return;
            // The chosen mode gets focus, so arrow keys work straight away.
            this.$nextTick(() => this.$refs.panel?.querySelector('[role=radio][tabindex="0"]')?.focus());
        },
    },
    mounted() {
        this.onOutside = (event) => this.open && !this.phone && !this.$refs.root.contains(event.target) && this.close({ focus: false });
        this.onKey = (event) => event.key === 'Escape' && this.open && !this.phone && this.close();
        document.addEventListener('pointerdown', this.onOutside);
        document.addEventListener('keydown', this.onKey);
    },
    unmounted() {
        document.removeEventListener('pointerdown', this.onOutside);
        document.removeEventListener('keydown', this.onKey);
    },
    methods: {
        close({ focus = true } = {}) {
            this.open = false;
            if (focus) this.$nextTick(() => this.$refs.button?.focus());
        },
    },
};
