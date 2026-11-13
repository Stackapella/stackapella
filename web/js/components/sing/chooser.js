// How to sing (docs/singing-ux-plan.md, "Components"; Hillel: "make all 3 equal weighted options"):
// Part by part, The whole song and Open in the studio, three equal large buttons, each with a small
// picture, its line and how long it takes; a tap starts that way at once. The one chosen last time
// is marked "Last time", never pre-picked. Use this every time (kept on this device: opened by itself
// with your part known, it goes straight in). With no part chosen yet, your part follows, with its
// range and the range check to suggest one. On an iPhone in Safari, installing the app is suggested.

import { midiName } from '../../dsp.js';
import { formatNumber, t, tk } from '../../i18n.js';
import { paint } from '../../colors.js';
import { prefs } from '../../stores/prefs.js';
import { Gems } from '../gems.js';
import { Icon } from '../icon.js';
import { NoVoiceTag } from '../../be-the-voice.js';
import { lazyComponent } from '../../game/modules.js';

const PartSelect = lazyComponent('partSelect', 'PartSelect'); // game mode's only: loaded as it's first drawn (js/game/modules.js WITH_SING)
import { effect } from '../../game/effects.js';

const WAYS = ['parts', 'whole', 'studio'];
const LABELS = { parts: tk('sing.chooser.parts'), whole: tk('sing.chooser.whole'), studio: tk('sing.chooser.studio') };
const HINTS = { parts: tk('sing.chooser.partsHint'), whole: tk('sing.chooser.wholeHint'), studio: tk('sing.chooser.studioHint') };

export const Chooser = {
    components: { Gems, Icon, PartSelect, NoVoiceTag },
    props: {
        parts: { type: Array, required: true }, // [{ slot, name, words, range: { low, high }, recorded }] (words: part-words.js)
        transpose: { type: Number, default: 0 },
        way: { type: String, default: null }, // the way chosen before, when choosing again
        suggested: { type: String, default: null }, // a slot id the range check suggests
        title: { type: String, default: '' },
        focus: { type: Object, default: null }, // the part already yours: { slot, name, range }
        bpm: { type: Number, default: null },
        phrases: { type: Number, default: 0 },
        length: { type: Number, default: 0 }, // seconds
        auto: Boolean, // opened by itself (not to choose again): Use this every time may go straight in
        noVoice: { type: Array, default: () => [] }, // slot ids whose part no one has sung yet (be-the-voice.js)
    },
    emits: ['choose', 'range', 'back', 'studio'],
    data() {
        const remembered = prefs.singWay;
        // The way chosen last time is marked, never picked for you (Hillel).
        return { picked: null, last: this.way ?? prefs.singLast ?? null, partStep: false, always: !!remembered, installHint: /iPhone|iPad/.test(navigator.userAgent) && !(navigator.standalone || matchMedia?.('(display-mode: standalone)').matches) };
    },
    template: `
        <!-- Its header at the top; on a desktop the choices sit in the middle of the height, not over an empty half (docs/design-rules.md). -->
        <div class="flex w-full flex-col md:flex-1">
            <!-- Sing's own bar's place (frame.js SingHeader), Back near the window's edge: never further in than the site's logo
                 (Hillel, 2026-11-10, docs/design-rules.md). The choices keep their column under it. -->
            <header class="flex items-center gap-3 px-3 pb-2 pt-[max(0.75rem,env(safe-area-inset-top))] min-[900px]:px-6">
                <button type="button" class="glass-bubble grid size-11 shrink-0 place-items-center rounded-full text-cream" :aria-label="t('common.back')" @click="partStep ? back() : $emit('back')"><Icon name="back" :size="20" /></button>
                <div class="min-w-0 flex-1">
                    <p v-if="title" class="truncate text-sm text-muted" translate="no">{{ title }}</p>
                    <p v-if="focus" class="flex items-center gap-2 truncate font-bold text-cream" translate="no"><span class="size-2.5 shrink-0 rounded-full" :style="{ background: paint(focus.slot.color) }" aria-hidden="true"></span>{{ t('sing.chooser.yourPartName', { part: focus.name }) }}</p>
                </div>
                <p v-if="facts" class="shrink-0 tabular-nums text-xs text-muted sm:text-sm">{{ facts }}</p>
            </header>
            <div class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 px-4 pb-6 pt-4 md:pb-10 md:pt-6">
            <div class="flex flex-col gap-6 md:my-auto md:pb-10">

            <template v-if="!partStep">
                <div>
                    <h1 ref="heading" tabindex="-1" class="text-3xl font-extrabold tracking-tight text-cream outline-none md:text-4xl">{{ t('sing.chooser.title') }}</h1>
                    <p class="mt-1 text-muted">{{ t('sing.chooser.intro') }}</p>
                </div>
                <!-- Each way a large button that starts it at once (equal rows when stacked too). Hillel, 2026-11-02 (the
                     first-15-minutes review): Part by part is tagged for a first song, and the studio waits under More ways
                     unless it's the way used last time. -->
                <div class="grid auto-rows-fr gap-4" :class="shownWays.length === 3 ? 'md:grid-cols-3' : 'md:grid-cols-2'">
                    <button v-for="w in shownWays" :key="w" type="button" class="card-interactive flex flex-col gap-4 p-5 text-start" :data-way="w" @click="choose(w)">
                        <span class="flex items-center gap-3">
                            <span class="flex-1 text-lg font-bold text-cream">{{ t(LABELS[w]) }}</span>
                            <span v-if="last === w" class="rounded-full bg-raised px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-muted" data-last-time>{{ t('sing.chooser.lastTime') }}</span>
                            <span v-else-if="!last && w === 'parts'" class="rounded-full border border-accent px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-accent-strong" data-first-pick>{{ t('sing.chooser.firstPick') }}</span>
                        </span>
                        <!-- How it goes, drawn: a phrase at a time, every part straight through, or the studio's lanes. -->
                        <span class="relative block space-y-1.5 rounded-2xl border border-line bg-ink/40 p-3" aria-hidden="true">
                            <Gems measure />
                            <template v-if="w === 'parts'">
                                <span class="flex gap-1"><span v-for="i in 6" :key="i" class="h-2.5 flex-1" :data-gem="barColor" :data-gem-state="i % 2 ? 'dim' : null"></span></span>
                                <span class="flex gap-1"><span class="h-2.5 flex-1" data-gem="var(--color-part-4)"></span><span v-for="i in 3" :key="i" class="h-2.5 flex-1" :data-gem="barColor"></span><span class="h-2.5 flex-[2] rounded-full bg-raised"></span></span>
                                <span class="block h-2.5" :data-gem="barColor"></span>
                            </template>
                            <template v-else-if="w === 'whole'">
                                <span class="block h-2.5" data-gem="var(--color-part-3)"></span>
                                <span class="block h-2.5" data-gem="var(--color-part-4)"></span>
                                <span class="block h-2.5" :data-gem="barColor"></span>
                            </template>
                            <template v-else>
                                <span class="flex gap-1"><span class="h-2.5 w-1/3" data-gem="var(--color-part-3)"></span><span class="h-2.5 flex-1 rounded-full bg-raised"></span></span>
                                <span class="flex gap-1"><span class="h-2.5 flex-1 rounded-full bg-raised"></span><span class="h-2.5 w-1/2" data-gem="var(--color-part-4)"></span></span>
                                <span class="block h-2.5" :data-gem="barColor"></span>
                            </template>
                        </span>
                        <span class="text-muted">{{ t(HINTS[w]) }}</span>
                        <span class="mt-auto tabular-nums text-xs text-muted">{{ w === 'parts' ? partsTime : w === 'whole' ? wholeTime : t('sing.chooser.studioTime') }}</span>
                    </button>
                </div>
                <details v-if="shownWays.length < WAYS.length" class="group" data-more-ways>
                    <summary class="link-quiet inline-flex min-h-11 cursor-pointer list-none items-center gap-1.5 text-sm [&::-webkit-details-marker]:hidden">{{ t('sing.chooser.moreWays') }} <Icon name="chevron" :size="14" class="transition group-open:rotate-180" /></summary>
                    <button type="button" class="card-interactive mt-2 flex w-full flex-col gap-1 p-4 text-start" data-way="studio" @click="choose('studio')">
                        <span class="font-bold text-cream">{{ t(LABELS.studio) }}</span>
                        <span class="text-sm text-muted">{{ t(HINTS.studio) }}</span>
                    </button>
                </details>
                <p v-if="installHint" class="rounded-2xl border border-line p-4 text-sm text-muted">{{ t('sing.chooser.install') }}</p>
                <label class="check-row"><input v-model="always" type="checkbox" class="checkbox" /> {{ t('sing.chooser.always') }}</label>
            </template>
            <template v-else>
                <div>
                    <h1 ref="heading" tabindex="-1" class="text-3xl font-extrabold tracking-tight text-cream outline-none">{{ t('sing.chooser.yourPart') }}</h1>
                    <p class="mt-1 text-muted">{{ t('sing.chooser.yourPartHint') }}</p>
                </div>
                <!-- Game mode's character select (docs/game-elements-plan.md): the same parts and the same choice -->
                <PartSelect v-if="effect('select')" :parts="parts" :transpose="transpose" :suggested="focus?.slot.id ?? suggested" @choose="(slotId) => $emit('choose', { way: picked, slotId })" />
                <ul v-else class="grid gap-3 md:grid-cols-2">
                    <li v-for="part in parts" :key="part.slot.id">
                        <button type="button" class="card-interactive flex w-full items-center gap-4 p-4 text-start" @click="$emit('choose', { way: picked, slotId: part.slot.id })">
                            <span class="gem size-4 shrink-0 rounded-full" :style="{ '--gem': paint(part.slot.color) }" aria-hidden="true"></span>
                            <span class="min-w-0 flex-1">
                                <span class="block font-bold text-cream" translate="no">{{ part.name }}<span v-if="part.slot.id === suggested" class="ms-2 text-xs font-semibold text-part-ink-4">{{ t('range.bestForYou') }}</span><NoVoiceTag v-if="noVoice.includes(part.slot.id)" class="ms-2 align-middle" /></span>
                                <span class="text-sm text-muted" data-part-words>{{ part.words }}<template v-if="part.words && part.range"> · </template></span><span class="tabular-nums text-sm text-muted">{{ part.range ? t('sing.chooser.range', { low: midiName(part.range.low + transpose), high: midiName(part.range.high + transpose) }) : '' }}<template v-if="part.recorded"> · {{ t('sing.chooser.recorded') }}</template></span>
                            </span>
                            <Icon name="arrow" :size="20" class="shrink-0 text-muted" />
                        </button>
                    </li>
                </ul>
                <button type="button" class="btn-secondary self-start" @click="$emit('range')"><Icon name="mic" :size="16" /> {{ t('sing.chooser.checkRange') }}</button>
            </template>
            </div>
</div></div>`,
    setup: () => ({ midiName, WAYS, LABELS, HINTS, effect }),
    computed: {
        /** The ways shown as cards: the studio only when it was the way used last time; else it's under More ways. */
        shownWays() {
            return this.last === 'studio' ? WAYS : WAYS.filter((w) => w !== 'studio');
        },
        /** "B♭2–E♭3 · 90 BPM": your part's range in this key, and the tempo. */
        facts() {
            const r = this.focus?.range;
            const range = r ? t('sing.chooser.rangeShort', { low: midiName(r.low + this.transpose), high: midiName(r.high + this.transpose) }) : '';
            return [range, this.bpm ? t('studio.meta.bpm', { bpm: this.bpm }) : ''].filter(Boolean).join(' · ');
        },
        barColor() {
            return this.focus ? paint(this.focus.slot.color) : 'var(--color-part-1)';
        },
        /** About three minutes a phrase: hear it, sing along, sing it on your own, then record. */
        partsTime() {
            const n = Math.max(1, this.phrases);
            return t('sing.chooser.partsTime', { minutes: formatNumber(n * 3), count: n });
        },
        /** The song once through, and a minute to settle. */
        wholeTime() {
            return t('sing.chooser.wholeTime', { minutes: formatNumber(Math.max(1, Math.ceil(this.length / 60) + 1)) });
        },
    },
    mounted() {
        // Use this every time (Settings' How you sing): opened by itself with your part known, straight in.
        const remembered = prefs.singWay;
        if (this.auto && !this.way && this.focus && remembered) {
            this.$emit('choose', { way: remembered, slotId: this.focus.slot.id });
            return;
        }
        this.$refs.heading?.focus();
    },
    methods: {
        /** A way's button: that way at once (the studio, or your part first when it isn't chosen yet). */
        choose(way) {
            prefs.singLast = way;
            if (way === 'studio') {
                this.$emit('studio');
                return;
            }
            this.picked = way;
            prefs.singWay = this.always ? way : null;
            // Game mode's character select is shown even when Sing knows your part, with that part pre-selected: one
            // tap to confirm or change (Hillel, 2026-11-01). Serious mode goes straight in.
            if (this.focus && !effect('select')) {
                this.$emit('choose', { way: this.picked, slotId: this.focus.slot.id });
                return;
            }
            this.partStep = true;
            this.$nextTick(() => this.$refs.heading?.focus());
        },
        back() {
            this.partStep = false;
            this.$nextTick(() => this.$refs.heading?.focus());
        },
    },
};
